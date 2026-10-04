<?php

namespace App\Services\CompanyDirectory;

use App\Models\Company;
use App\Models\CompanyCredential;
use App\Models\CompanyDirectorySavedView;
use App\Models\CompanyProfile;
use App\Models\CompanyRegion;
use App\Models\CompanyRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyDirectoryService
{
    public function __construct(
        private readonly CompanyOnboardingService $onboarding,
        private readonly CompanyGovernanceReadinessService $governance,
        private readonly CompanyPortfolioService $portfolio,
    ) {
    }

    public function build(array $filters, User $user): array
    {
        $companies = $this->query($filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        $requirements = $this->onboarding->requirements();
        $companyIds = $companies->getCollection()->pluck('id')->all();
        $activeCounts = $this->portfolio->activeProjectCounts($companyIds);
        $pagePortfolio = $this->portfolio->acceptedQuotationValues($companyIds);

        $companies->setCollection($companies->getCollection()->map(function (Company $company) use ($requirements, $activeCounts, $pagePortfolio): Company {
            $company->setAttribute('directory_onboarding', $this->onboarding->evaluate($company, $requirements));
            $company->setAttribute('directory_governance', $this->governance->evaluate($company));
            $company->setAttribute('directory_active_projects', (int) ($activeCounts[$company->getKey()] ?? 0));
            $company->setAttribute('directory_portfolio_value_idr', $pagePortfolio['by_company'][$company->getKey()] ?? null);

            return $company;
        }));

        return [
            'companies' => $companies,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'stats' => $this->stats(),
            'intelligence' => $this->intelligence($requirements),
            'savedViews' => CompanyDirectorySavedView::query()
                ->where('user_id', $user->getKey())
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'metricDefinitions' => [
                'portfolio_value' => [
                    'label' => 'Accepted Portfolio Value',
                    'basis' => 'Accepted quotations · commercial_summary.grand_total_idr · IDR only · no FX conversion',
                ],
                'active_projects' => [
                    'label' => 'Active Projects',
                    'basis' => 'Non-archived projects in Draft, Survey, Engineering, Quotation, or Approved states',
                ],
                'governance' => [
                    'label' => 'Governance Readiness',
                    'basis' => 'Profile evidence readiness; not statutory or jurisdictional compliance',
                    'version' => CompanyGovernanceReadinessService::FRAMEWORK_VERSION,
                ],
            ],
        ];
    }

    public function export(array $filters): StreamedResponse
    {
        $fileName = 'nexorin-company-directory-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Company', 'Code', 'Company Type', 'Specialization', 'Relationship Tier', 'Regions',
                'Governance Readiness', 'Accepted Portfolio Value IDR', 'Active Projects', 'Status', 'Onboarding Progress',
            ]);

            $requirements = $this->onboarding->requirements();
            $query = $this->query($filters);

            $query->chunkById(200, function (Collection $companies) use ($handle, $requirements): void {
                $ids = $companies->pluck('id')->all();
                $activeCounts = $this->portfolio->activeProjectCounts($ids);
                $portfolio = $this->portfolio->acceptedQuotationValues($ids);

                foreach ($companies as $company) {
                    $onboarding = $this->onboarding->evaluate($company, $requirements);
                    $governance = $this->governance->evaluate($company);
                    $specialization = collect($company->profile?->categories_served ?? [])->filter()->implode(' · ');

                    fputcsv($handle, [
                        $this->csvCell($company->name),
                        $this->csvCell($company->code),
                        $this->csvCell($company->profile?->company_type),
                        $this->csvCell($specialization),
                        $this->csvCell($company->relationship?->tier),
                        $this->csvCell($company->regions->pluck('region_name')->filter()->implode(' · ')),
                        $this->csvCell($governance['label']),
                        $portfolio['by_company'][$company->getKey()] ?? null,
                        (int) ($activeCounts[$company->getKey()] ?? 0),
                        $this->csvCell($company->status),
                        $onboarding['progress'],
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function safeFiltersForSavedView(array $filters): array
    {
        return collect($filters)
            ->except([])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }


    private function csvCell(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    private function query(array $filters): Builder
    {
        $today = now()->toDateString();
        $warning = now()->addDays(30)->toDateString();

        return Company::query()
            ->with([
                'profile',
                'relationship',
                'contacts',
                'regions',
                'credentials',
                'documents',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $companyQuery) use ($search): void {
                    $companyQuery
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('code', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('registration_number', "%{$search}%")
                        ->orWhereHas('profile', fn (Builder $profile) => $profile
                            ->whereLike('legal_name', "%{$search}%")
                            ->orWhereLike('company_type', "%{$search}%"))
                        ->orWhereHas('credentials', fn (Builder $credential) => $credential
                            ->whereLike('title', "%{$search}%")
                            ->orWhereLike('issuing_authority', "%{$search}%"));
                });
            })
            ->when($filters['type'], fn (Builder $query, string $type) => $query
                ->whereHas('profile', fn (Builder $profile) => $profile->where('company_type', $type)))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['tier'], function (Builder $query, string $tier): void {
                if ($tier === 'unassigned') {
                    $query->whereDoesntHave('relationship');
                    return;
                }
                $query->whereHas('relationship', fn (Builder $relationship) => $relationship->where('tier', $tier));
            })
            ->when($filters['region'], fn (Builder $query, string $region) => $query
                ->whereHas('regions', fn (Builder $regions) => $regions->where('region_name', $region)))
            ->when($filters['specialization'], fn (Builder $query, string $specialization) => $query
                ->whereHas('profile', fn (Builder $profile) => $profile->whereJsonContains('categories_served', $specialization)))
            ->when($filters['certification'], fn (Builder $query, string $certification) => $query
                ->whereHas('credentials', fn (Builder $credentials) => $credentials
                    ->where('kind', 'certification')->where('title', $certification)))
            ->when($filters['governance_state'], function (Builder $query, string $state) use ($today, $warning): void {
                $this->applyGovernanceFilter($query, $state, $today, $warning);
            })
            ->when($filters['onboarding_state'], function (Builder $query, string $state): void {
                $this->applyOnboardingFilter($query, $state);
            })
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id');
    }

    private function applyGovernanceFilter(Builder $query, string $state, string $today, string $warning): void
    {
        $expired = fn (Builder $q) => $q->whereNotNull('expires_at')->whereDate('expires_at', '<', $today);
        $expiring = fn (Builder $q) => $q->whereNotNull('expires_at')->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $warning);
        $credible = fn (Builder $q) => $q->whereIn('status', ['active', 'verified']);

        if ($state === 'not_assessed') {
            $query->whereDoesntHave('credentials')->whereDoesntHave('documents');
            return;
        }

        $query->where(function (Builder $q): void {
            $q->whereHas('credentials')->orWhereHas('documents');
        });

        if ($state === 'incomplete') {
            $query->where(function (Builder $q) use ($expired): void {
                $q->whereHas('credentials', $expired)->orWhereHas('documents', $expired);
            });
            return;
        }

        $query->whereDoesntHave('credentials', $expired)->whereDoesntHave('documents', $expired);

        if ($state === 'expiring') {
            $query->where(function (Builder $q) use ($expiring): void {
                $q->whereHas('credentials', $expiring)->orWhereHas('documents', $expiring);
            });
            return;
        }

        $query->whereDoesntHave('credentials', $expiring)->whereDoesntHave('documents', $expiring);

        if ($state === 'attention') {
            $query->where(function (Builder $q) use ($credible): void {
                $q->whereHas('credentials', fn (Builder $c) => $c->where('status', 'pending'))
                    ->orWhere(function (Builder $noCredible) use ($credible): void {
                        $noCredible->whereDoesntHave('credentials', $credible)
                            ->whereDoesntHave('documents', fn (Builder $d) => $d->where('category', 'legal')->where('status', 'active'));
                    });
            });
            return;
        }

        if ($state === 'ready') {
            $query->whereDoesntHave('credentials', fn (Builder $c) => $c->where('status', 'pending'))
                ->where(function (Builder $q) use ($credible): void {
                    $q->whereHas('credentials', $credible)
                        ->orWhereHas('documents', fn (Builder $d) => $d->where('category', 'legal')->where('status', 'active'));
                });
        }
    }

    private function applyOnboardingFilter(Builder $query, string $state): void
    {
        $complete = function (Builder $q): void {
            $q->whereNotNull('name')->whereNotNull('code')
                ->where(function (Builder $registration): void {
                    $registration->whereNotNull('registration_number')
                        ->orWhereHas('credentials', fn (Builder $c) => $c->where('kind', 'registration'));
                })
                ->whereHas('contacts', fn (Builder $c) => $c->where('is_primary', true))
                ->whereHas('regions')
                ->whereHas('profile', fn (Builder $p) => $p->whereNotNull('last_reviewed_at'));
        };

        if ($state === 'complete') {
            $complete($query);
            return;
        }

        if ($state === 'in_progress') {
            $query->whereNot(function (Builder $q) use ($complete): void {
                $complete($q);
            });
            return;
        }

        if ($state === 'not_started') {
            // Company name and code are mandatory, so a true 0% state is not reachable
            // under the default v1 onboarding definition.
            $query->whereRaw('1 = 0');
            return;
        }

        if ($state === 'not_configured') {
            $query->whereRaw('1 = 0');
        }
    }

    private function filterOptions(): array
    {
        $specializations = CompanyProfile::query()->pluck('categories_served')
            ->flatMap(fn ($value) => is_array($value) ? $value : (json_decode((string) $value, true) ?: []))
            ->filter()->map(fn ($value) => (string) $value)->unique()->sort()->values();

        return [
            'types' => CompanyProfile::query()->whereNotNull('company_type')->distinct()->orderBy('company_type')->pluck('company_type'),
            'tiers' => collect(CompanyRelationship::TIERS),
            'regions' => CompanyRegion::query()->whereNotNull('region_name')->distinct()->orderBy('region_name')->pluck('region_name'),
            'specializations' => $specializations,
            'certifications' => CompanyCredential::query()->where('kind', 'certification')->distinct()->orderBy('title')->pluck('title'),
        ];
    }

    private function stats(): array
    {
        $portfolio = $this->portfolio->acceptedQuotationValues();

        return [
            'total' => Company::query()->count(),
            'active' => Company::query()->active()->count(),
            'strategic' => CompanyRelationship::query()->where('tier', 'strategic')->count(),
            'portfolio' => $portfolio,
            'active_projects' => $this->portfolio->activeProjectCounts()->sum(),
        ];
    }

    private function intelligence(Collection $requirements): array
    {
        $segments = Company::query()
            ->leftJoin('company_profiles', 'company_profiles.company_id', '=', 'companies.id')
            ->selectRaw("COALESCE(company_profiles.company_type, 'Unclassified') as label, COUNT(companies.id) as aggregate")
            ->whereNull('companies.deleted_at')
            ->groupBy('company_profiles.company_type')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->aggregate]);

        $regions = CompanyRegion::query()
            ->whereNotNull('region_name')
            ->selectRaw('region_name, COUNT(DISTINCT company_id) as aggregate, AVG(latitude) as latitude, AVG(longitude) as longitude')
            ->groupBy('region_name')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'region' => $row->region_name,
                'companies' => (int) $row->aggregate,
                'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
                'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
            ]);

        $portfolio = $this->portfolio->acceptedQuotationValues();
        $relationshipTiers = CompanyRelationship::query()
            ->whereIn('company_id', array_keys($portfolio['by_company']))
            ->pluck('tier', 'company_id');
        $portfolioByTier = collect($portfolio['by_company'])->reduce(function (array $carry, float $value, int|string $companyId) use ($relationshipTiers): array {
            $tier = $relationshipTiers[$companyId] ?? 'unassigned';
            $carry[$tier] = ($carry[$tier] ?? 0.0) + $value;
            return $carry;
        }, []);

        $activeCounts = $this->portfolio->activeProjectCounts();
        $topCompanyIds = $activeCounts->sortDesc()->take(7)->keys()->all();
        $companyNames = Company::query()->whereIn('id', $topCompanyIds)->pluck('name', 'id');
        $activeProjectsByCompany = collect($topCompanyIds)->map(fn ($id) => [
            'label' => $companyNames[$id] ?? 'Company #'.$id,
            'value' => (int) ($activeCounts[$id] ?? 0),
        ]);

        $governanceOverview = collect(['ready', 'attention', 'expiring', 'incomplete', 'not_assessed'])
            ->map(fn (string $state) => [
                'state' => $state,
                'label' => Str::headline($state),
                'value' => $this->countWithGovernanceState($state),
            ]);

        $blockers = $requirements->filter->required_by_default->map(function ($requirement): array {
            $count = $this->countMissingOnboardingStrategy($requirement->evaluation_strategy);
            return ['code' => $requirement->code, 'label' => $requirement->label, 'count' => $count];
        })->filter(fn (array $item) => $item['count'] > 0)->sortByDesc('count')->values();

        $alerts = collect([
            ['label' => 'Expired governance evidence', 'count' => $this->countExpiredEvidence(), 'state' => 'critical', 'filter' => ['governance_state' => 'incomplete']],
            ['label' => 'Evidence expiring within 30 days', 'count' => $this->countExpiringEvidence(), 'state' => 'warning', 'filter' => ['governance_state' => 'expiring']],
            ['label' => 'Credentials awaiting verification', 'count' => CompanyCredential::query()->where('status', 'pending')->distinct('company_id')->count('company_id'), 'state' => 'warning', 'filter' => ['governance_state' => 'attention']],
            ['label' => 'Inactive companies', 'count' => Company::query()->where('status', 'inactive')->count(), 'state' => 'neutral', 'filter' => ['status' => 'inactive']],
        ])->filter(fn (array $item) => $item['count'] > 0)->values();

        return [
            'regional_distribution' => $regions,
            'segment_mix' => $segments,
            'portfolio_by_tier' => collect($portfolioByTier)->map(fn ($value, $tier) => ['label' => Str::headline($tier), 'value' => $value])->values(),
            'portfolio_currency' => 'IDR',
            'governance_overview' => $governanceOverview,
            'active_projects_by_company' => $activeProjectsByCompany,
            'onboarding_blockers' => $blockers,
            'alerts' => $alerts,
            'next_actions' => $this->nextActions($blockers, $alerts),
            'health' => [
                'state' => 'not_scored',
                'label' => 'Not scored',
                'reason' => 'No versioned weighted Company Health framework is configured. NEXORIN will not invent a composite score.',
                'factors' => [
                    ['label' => 'Governance readiness', 'status' => 'Available', 'source' => CompanyGovernanceReadinessService::FRAMEWORK_VERSION],
                    ['label' => 'Financial stability', 'status' => 'Inputs only', 'source' => 'Company financial snapshots'],
                    ['label' => 'Project performance', 'status' => 'Not modeled', 'source' => 'No approved company-level performance rule'],
                    ['label' => 'Safety record', 'status' => 'Not modeled', 'source' => 'No authoritative safety record domain'],
                    ['label' => 'Sustainability', 'status' => 'Not modeled', 'source' => 'No authoritative sustainability rule pack'],
                ],
            ],
        ];
    }

    private function countWithGovernanceState(string $state): int
    {
        $filters = [
            'search' => '', 'type' => null, 'status' => null, 'tier' => null, 'region' => null,
            'specialization' => null, 'certification' => null, 'governance_state' => $state,
            'onboarding_state' => null, 'sort' => 'name', 'direction' => 'asc', 'per_page' => 10,
        ];

        return $this->query($filters)->toBase()->getCountForPagination();
    }

    private function countMissingOnboardingStrategy(string $strategy): int
    {
        $query = Company::query();
        return match ($strategy) {
            'company_identity' => $query->where(fn (Builder $q) => $q->whereNull('name')->orWhereNull('code'))->count(),
            'legal_registration' => $query->whereNull('registration_number')->whereDoesntHave('credentials', fn (Builder $q) => $q->where('kind', 'registration'))->count(),
            'primary_contact' => $query->whereDoesntHave('contacts', fn (Builder $q) => $q->where('is_primary', true))->count(),
            'service_region' => $query->whereDoesntHave('regions')->count(),
            'company_review' => $query->whereDoesntHave('profile', fn (Builder $q) => $q->whereNotNull('last_reviewed_at'))->count(),
            default => 0,
        };
    }

    private function countExpiredEvidence(): int
    {
        $today = now()->toDateString();
        return Company::query()->where(function (Builder $q) use ($today): void {
            $q->whereHas('credentials', fn (Builder $e) => $e->whereNotNull('expires_at')->whereDate('expires_at', '<', $today))
                ->orWhereHas('documents', fn (Builder $e) => $e->whereNotNull('expires_at')->whereDate('expires_at', '<', $today));
        })->count();
    }

    private function countExpiringEvidence(): int
    {
        $today = now()->toDateString();
        $warning = now()->addDays(30)->toDateString();
        return Company::query()->where(function (Builder $q) use ($today, $warning): void {
            $q->whereHas('credentials', fn (Builder $e) => $e->whereBetween('expires_at', [$today, $warning]))
                ->orWhereHas('documents', fn (Builder $e) => $e->whereBetween('expires_at', [$today, $warning]));
        })->count();
    }

    private function nextActions(Collection $blockers, Collection $alerts): Collection
    {
        $actions = collect();
        if ($blockers->isNotEmpty()) {
            $actions->push(['label' => 'Review onboarding gaps', 'count' => $blockers->sum('count'), 'query' => ['onboarding_state' => 'in_progress']]);
        }
        foreach ($alerts->take(3) as $alert) {
            $actions->push(['label' => 'Review '.$alert['label'], 'count' => $alert['count'], 'query' => $alert['filter']]);
        }
        return $actions->take(4)->values();
    }
}
