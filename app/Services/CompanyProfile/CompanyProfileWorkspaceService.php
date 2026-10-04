<?php

namespace App\Services\CompanyProfile;

use App\Models\Company;
use App\Models\ProjectStatusHistory;
use App\Models\QuotationEvent;
use App\Services\CompanyDirectory\CompanyGovernanceReadinessService;
use App\Services\CompanyDirectory\CompanyOnboardingService;
use App\Services\CompanyDirectory\CompanyPortfolioService;
use Illuminate\Support\Collection;

class CompanyProfileWorkspaceService
{
    public function __construct(
        private readonly CompanyOnboardingService $onboarding,
        private readonly CompanyGovernanceReadinessService $governance,
        private readonly CompanyPortfolioService $portfolio,
    ) {
    }

    public function build(Company $company): array
    {
        $company->load([
            'creator:id,name,email',
            'profile',
            'relationship.reviewer:id,name,email',
            'contacts',
            'regions',
            'documents',
            'credentials',
            'financialSnapshots' => fn ($query) => $query
                ->with('sourceDocument:id,company_id,title,status')
                ->limit(8),
        ]);

        $company->loadCount([
            'memberships',
            'activeMemberships',
            'customers as current_customers_count' => fn ($query) => $query->whereNull('archived_at'),
            'projects as current_projects_count' => fn ($query) => $query->whereNull('archived_at'),
        ]);

        $activeProjectCounts = $this->portfolio->activeProjectCounts([$company->getKey()]);
        $activeProjectCount = (int) ($activeProjectCounts[$company->getKey()] ?? 0);
        $acceptedPortfolio = $this->portfolio->acceptedQuotationValues([$company->getKey()]);

        $activeProjectLifecycle = $this->activeProjectLifecycle($company);
        $recentProjects = $company->projects()
            ->current()
            ->select([
                'id', 'company_id', 'customer_id', 'project_number', 'name', 'proposed_system',
                'city', 'province', 'status', 'archived_at', 'created_at',
            ])
            ->with('customer:id,name')
            ->latest('created_at')
            ->latest('id')
            ->limit(6)
            ->get();

        $recentAcceptedPortfolio = $this->recentAcceptedPortfolio($company);
        $recentActivity = $this->recentAuditableActivity($company);
        $financial = $this->financialSnapshotPresentation($company);
        $credentialRows = $this->credentialPresentation($company->credentials);
        $documentRows = $this->documentPresentation($company->documents);
        $governance = $this->governance->evaluate($company);
        $onboarding = $this->onboarding->evaluate($company);

        return [
            'company' => $company,
            'workspace' => [
                'identity' => $this->identityPresentation($company),
                'relationship' => $this->relationshipPresentation($company),
                'onboarding' => $onboarding,
                'governance' => $governance,
                'health' => $this->healthPresentation($company, $financial, $governance, $onboarding),
                'activeProjectCount' => $activeProjectCount,
                'activeProjectLifecycle' => $activeProjectLifecycle,
                'acceptedPortfolio' => [
                    ...$acceptedPortfolio,
                    'company_value' => $acceptedPortfolio['by_company'][$company->getKey()] ?? null,
                ],
                'recentAcceptedPortfolio' => $recentAcceptedPortfolio,
                'financial' => $financial,
                'capabilities' => $this->capabilityPresentation($company),
                'regions' => $this->regionPresentation($company),
                'contacts' => $this->contactPresentation($company->contacts),
                'credentials' => $credentialRows,
                'documents' => $documentRows,
                'recentProjects' => $recentProjects,
                'recentActivity' => $recentActivity,
                'alerts' => $this->alertPresentation($company, $onboarding, $governance, $credentialRows),
                'counts' => [
                    'memberships' => (int) $company->memberships_count,
                    'active_memberships' => (int) $company->active_memberships_count,
                    'customers' => (int) $company->current_customers_count,
                    'projects' => (int) $company->current_projects_count,
                ],
                'definitions' => [
                    'active_projects' => 'Non-archived projects in Draft, Survey, Engineering, Quotation, or Approved states.',
                    'portfolio' => 'Accepted quotations · commercial grand total · IDR only · no FX conversion.',
                    'governance' => 'Profile evidence readiness; not statutory or jurisdictional compliance.',
                    'regional_presence' => 'Company service regions. Stored coordinates are shown as reference coordinates, not verified site coordinates.',
                    'health' => 'No versioned company-health scoring framework is implemented. Availability factors are shown without calculating a score.',
                    'activity' => 'Only persisted project-status history and quotation lifecycle events are shown.',
                ],
            ],
        ];
    }

    private function identityPresentation(Company $company): array
    {
        $profile = $company->profile;
        $profileImagePath = $profile?->profile_image_path;
        $imagePath = $profileImagePath ?: $company->logo_path;

        $headquarters = collect([$profile?->hq_city, $profile?->hq_country])
            ->filter(fn ($value) => filled($value))
            ->implode(', ');

        if ($headquarters === '') {
            $hqRegion = $company->regions->first(fn ($region) => (bool) $region->is_headquarters);
            $headquarters = collect([$hqRegion?->region_name, $hqRegion?->country_name])
                ->filter(fn ($value) => filled($value))
                ->implode(', ');
        }

        return [
            'image_url' => $imagePath ? asset('storage/'.$imagePath) : null,
            'image_kind' => $profileImagePath ? 'Company profile media' : ($company->logo_path ? 'Company logo' : 'Corporate media'),
            'name' => $company->name,
            'code' => $company->code,
            'legal_name' => $profile?->legal_name ?: 'Not recorded',
            'company_type' => $profile?->company_type ?: 'Not recorded',
            'specializations' => collect($profile?->categories_served ?? [])->filter()->values()->all(),
            'focus_markets' => collect($profile?->focus_markets ?? [])->filter()->values()->all(),
            'headquarters' => $headquarters !== '' ? $headquarters : 'Not recorded',
            'year_established' => $profile?->year_established ?: null,
            'team_size' => $profile?->team_size ?: null,
            'website' => $this->safeWebsite($profile?->website),
            'email' => $company->email ?: null,
            'phone' => $company->formatted_phone ?: null,
            'registration_number' => $company->registration_number ?: null,
            'description' => $profile?->description ?: null,
            'data_confidence' => $this->dataConfidenceLabel($profile?->data_confidence),
            'status_label' => $company->isActive() ? 'Active' : 'Inactive',
            'status_state' => $company->isActive() ? 'active' : 'inactive',
        ];
    }

    private function relationshipPresentation(Company $company): array
    {
        $relationship = $company->relationship;

        if (! $relationship) {
            return [
                'tier' => null,
                'tier_label' => 'Unassigned',
                'qualification' => null,
                'qualification_label' => 'Not assessed',
                'source_reference' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ];
        }

        return [
            'tier' => $relationship->tier,
            'tier_label' => $this->relationshipTierLabel($relationship->tier),
            'qualification' => $relationship->qualification_state,
            'qualification_label' => $this->qualificationLabel($relationship->qualification_state),
            'source_reference' => $relationship->source_reference,
            'reviewed_at' => $relationship->reviewed_at,
            'reviewed_by' => $relationship->reviewer?->name,
        ];
    }

    private function activeProjectLifecycle(Company $company): array
    {
        $counts = $company->projects()
            ->current()
            ->whereIn('status', CompanyPortfolioService::ACTIVE_PROJECT_STATUSES)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(CompanyPortfolioService::ACTIVE_PROJECT_STATUSES)
            ->map(fn (string $status): array => [
                'key' => $status,
                'label' => $this->projectStatusLabel($status),
                'value' => (int) ($counts[$status] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->values()
            ->all();
    }

    private function recentAcceptedPortfolio(Company $company): array
    {
        return $this->portfolio->recentAcceptedQuotationValues($company, 5)->all();
    }

    private function financialSnapshotPresentation(Company $company): array
    {
        $snapshots = $company->financialSnapshots->take(8)->values();
        $latest = $snapshots->first();

        if (! $latest) {
            return [
                'state' => 'not_available',
                'label' => 'Not Available',
                'latest' => null,
                'trend' => [],
                'trend_available' => false,
                'basis' => 'No persisted financial snapshot is recorded.',
            ];
        }

        $latestCurrency = strtoupper((string) ($latest->currency ?: ''));
        $trend = $snapshots
            ->filter(fn ($snapshot): bool => strtoupper((string) $snapshot->currency) === $latestCurrency && is_numeric($snapshot->annual_revenue))
            ->sortBy('period_end')
            ->map(fn ($snapshot): array => [
                'period' => $snapshot->period_end?->format('Y-m-d'),
                'label' => $snapshot->period_end?->format('M Y') ?? 'Period not recorded',
                'annual_revenue' => (float) $snapshot->annual_revenue,
            ])
            ->values();

        return [
            'state' => 'available',
            'label' => 'Recorded',
            'latest' => [
                'period_end' => $latest->period_end,
                'currency' => $latestCurrency !== '' ? $latestCurrency : 'Currency not recorded',
                'annual_revenue' => is_numeric($latest->annual_revenue) ? (float) $latest->annual_revenue : null,
                'ebitda_margin' => is_numeric($latest->ebitda_margin) ? (float) $latest->ebitda_margin : null,
                'current_ratio' => is_numeric($latest->current_ratio) ? (float) $latest->current_ratio : null,
                'total_assets' => is_numeric($latest->total_assets) ? (float) $latest->total_assets : null,
                'net_profit' => is_numeric($latest->net_profit) ? (float) $latest->net_profit : null,
                'debt_to_equity' => is_numeric($latest->debt_to_equity) ? (float) $latest->debt_to_equity : null,
                'source_document' => $latest->sourceDocument?->title,
            ],
            'trend' => $trend->all(),
            'trend_available' => $trend->count() >= 2,
            'basis' => $trend->count() >= 2
                ? 'Historical revenue trend uses comparable persisted snapshots in '.$latestCurrency.'.'
                : 'One comparable persisted snapshot is available; no financial trend is inferred.',
        ];
    }

    private function healthPresentation(Company $company, array $financial, array $governance, array $onboarding): array
    {
        $factors = [
            ['label' => 'Governance evidence', 'available' => $governance['state'] !== 'not_assessed'],
            ['label' => 'Onboarding framework', 'available' => $onboarding['state'] !== 'not_configured'],
            ['label' => 'Financial snapshot', 'available' => $financial['state'] === 'available'],
            ['label' => 'Primary contact', 'available' => $company->contacts->contains(fn ($contact): bool => (bool) $contact->is_primary)],
            ['label' => 'Service region', 'available' => $company->regions->isNotEmpty()],
            ['label' => 'Relationship tier', 'available' => $company->relationship !== null],
        ];

        return [
            'state' => 'not_scored',
            'label' => 'Not Scored',
            'score' => null,
            'factors' => $factors,
            'available_factor_count' => collect($factors)->where('available', true)->count(),
            'total_factor_count' => count($factors),
        ];
    }

    private function capabilityPresentation(Company $company): array
    {
        $profile = $company->profile;
        $items = collect();

        foreach (collect($profile?->categories_served ?? [])->filter() as $label) {
            $items->push(['label' => (string) $label, 'source' => 'Recorded specialization']);
        }
        foreach (collect($profile?->focus_markets ?? [])->filter() as $label) {
            $items->push(['label' => (string) $label, 'source' => 'Recorded focus market']);
        }
        foreach ($company->credentials->where('kind', 'certification') as $credential) {
            if (filled($credential->title)) {
                $items->push(['label' => $credential->title, 'source' => 'Recorded credential']);
            }
        }

        return $items->unique(fn (array $row) => mb_strtolower($row['label']))->values()->all();
    }

    private function regionPresentation(Company $company): array
    {
        $rows = $company->regions->map(fn ($region): array => [
            'id' => $region->id,
            'region_name' => $region->region_name ?: 'Region not recorded',
            'country_name' => $region->country_name ?: 'Country not recorded',
            'country_code' => $region->country_code,
            'latitude' => is_numeric($region->latitude) ? (float) $region->latitude : null,
            'longitude' => is_numeric($region->longitude) ? (float) $region->longitude : null,
            'coordinate_type' => is_numeric($region->latitude) && is_numeric($region->longitude) ? 'Reference Coordinate' : 'Service Region',
            'is_headquarters' => (bool) $region->is_headquarters,
        ])->values();

        return [
            'items' => $rows->all(),
            'map_points' => $rows->filter(fn (array $row): bool => $row['latitude'] !== null && $row['longitude'] !== null)->values()->all(),
        ];
    }

    private function contactPresentation(Collection $contacts): array
    {
        return $contacts->take(8)->map(fn ($contact): array => [
            'name' => $contact->full_name ?: 'Name not recorded',
            'role' => $contact->job_title ?: 'Role not recorded',
            'email' => $contact->email ?: null,
            'phone' => $contact->phone ?: null,
            'is_primary' => (bool) $contact->is_primary,
        ])->values()->all();
    }

    private function credentialPresentation(Collection $credentials): array
    {
        $today = now()->startOfDay();
        $warning = $today->copy()->addDays(30);

        return $credentials->take(10)->map(function ($credential) use ($today, $warning): array {
            $expiryState = 'not_assessed';
            $expiryLabel = 'Not Assessed';
            if ($credential->expires_at) {
                if ($credential->expires_at->copy()->startOfDay()->lt($today)) {
                    $expiryState = 'expired';
                    $expiryLabel = 'Expired';
                } elseif ($credential->expires_at->copy()->startOfDay()->lte($warning)) {
                    $expiryState = 'expiring';
                    $expiryLabel = 'Expiring';
                } else {
                    $expiryState = 'valid';
                    $expiryLabel = 'Valid';
                }
            }

            return [
                'kind' => $this->credentialKindLabel($credential->kind),
                'title' => $credential->title ?: 'Credential title not recorded',
                'reference_number' => $credential->reference_number,
                'issuer' => $credential->issuing_authority ?: $credential->policy_provider,
                'issued_at' => $credential->issued_at,
                'expires_at' => $credential->expires_at,
                'record_status' => $this->credentialStatusLabel($credential->status),
                'record_state' => $credential->status,
                'expiry_status' => $expiryLabel,
                'expiry_state' => $expiryState,
            ];
        })->values()->all();
    }

    private function documentPresentation(Collection $documents): array
    {
        return $documents->take(10)->map(fn ($document): array => [
            'category' => $this->documentCategoryLabel($document->category),
            'title' => $document->title ?: 'Document title not recorded',
            'file_name' => $document->file_name ?: null,
            'file_size_kb' => $document->file_size_kb,
            'issued_at' => $document->issued_at,
            'expires_at' => $document->expires_at,
            'status' => $this->documentStatusLabel($document->status),
        ])->values()->all();
    }

    private function recentAuditableActivity(Company $company): array
    {
        $projectStatusEvents = ProjectStatusHistory::query()
            ->whereHas('project', fn ($query) => $query->where('company_id', $company->getKey()))
            ->with(['project:id,company_id,project_number,name', 'changedBy:id,name'])
            ->latest('changed_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (ProjectStatusHistory $event): array => [
                'type' => 'Project status',
                'title' => ($event->project?->project_number ?: $event->project?->name ?: 'Project').' moved to '.$this->projectStatusLabel($event->to_status),
                'detail' => $event->note ?: 'Recorded project lifecycle transition.',
                'actor' => $event->changedBy?->name,
                'event_at' => $event->changed_at,
            ]);

        $quotationEvents = QuotationEvent::query()
            ->where('company_id', $company->getKey())
            ->with(['project:id,company_id,project_number,name', 'actor:id,name'])
            ->latest('event_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (QuotationEvent $event): array => [
                'type' => 'Quotation',
                'title' => $event->event_label.($event->project ? ' · '.($event->project->project_number ?: $event->project->name) : ''),
                'detail' => $event->notes ?: 'Recorded quotation lifecycle event.',
                'actor' => $event->actor?->name,
                'event_at' => $event->event_at,
            ]);

        return $projectStatusEvents
            ->concat($quotationEvents)
            ->filter(fn (array $row): bool => $row['event_at'] !== null)
            ->sortByDesc(fn (array $row) => $row['event_at']->getTimestamp())
            ->take(6)
            ->values()
            ->all();
    }

    private function alertPresentation(Company $company, array $onboarding, array $governance, array $credentials): array
    {
        $alerts = collect();

        foreach ($onboarding['blockers'] ?? [] as $blocker) {
            $alerts->push([
                'severity' => 'attention',
                'title' => $blocker['label'].' Missing',
                'detail' => 'Required onboarding evidence or profile data has not been completed.',
            ]);
        }

        foreach ($governance['alerts'] ?? [] as $alert) {
            $alerts->push([
                'severity' => in_array($alert['type'] ?? '', ['expired_evidence'], true) ? 'critical' : 'attention',
                'title' => $alert['label'] ?? 'Governance evidence requires attention',
                'detail' => isset($alert['count']) ? number_format((int) $alert['count']).' recorded item(s) require review.' : $governance['basis'],
            ]);
        }

        foreach ($credentials as $credential) {
            if ($credential['expiry_state'] === 'expired') {
                $alerts->push([
                    'severity' => 'critical',
                    'title' => 'Credential Expired',
                    'detail' => $credential['title'].' is recorded as expired.',
                ]);
            } elseif ($credential['expiry_state'] === 'expiring') {
                $alerts->push([
                    'severity' => 'attention',
                    'title' => 'Credential Expiring',
                    'detail' => $credential['title'].' expires within 30 days.',
                ]);
            }
        }

        if (! $company->contacts->contains(fn ($contact): bool => (bool) $contact->is_primary)) {
            $alerts->push([
                'severity' => 'attention',
                'title' => 'Primary Contact Missing',
                'detail' => 'No contact is currently marked as the primary company contact.',
            ]);
        }

        if ($company->regions->isEmpty()) {
            $alerts->push([
                'severity' => 'attention',
                'title' => 'Service Region Missing',
                'detail' => 'No company service region has been recorded.',
            ]);
        }

        return $alerts
            ->unique(fn (array $row) => $row['title'])
            ->take(6)
            ->values()
            ->all();
    }

    private function safeWebsite(?string $website): ?string
    {
        if (! filled($website) || ! filter_var($website, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $website : null;
    }

    private function relationshipTierLabel(?string $value): string
    {
        return match ($value) {
            'strategic' => 'Strategic',
            'preferred' => 'Preferred',
            'approved' => 'Approved',
            'standard' => 'Standard',
            'not_qualified' => 'Not Qualified',
            default => 'Unassigned',
        };
    }

    private function qualificationLabel(?string $value): string
    {
        return match ($value) {
            'qualified' => 'Qualified',
            'review_required' => 'Review Required',
            'disqualified' => 'Disqualified',
            default => 'Not Assessed',
        };
    }

    private function dataConfidenceLabel(?string $value): string
    {
        return match ($value) {
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Not Assessed',
        };
    }

    private function projectStatusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'survey' => 'Survey',
            'engineering' => 'Engineering',
            'quotation' => 'Quotation',
            'approved' => 'Approved',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Not Available',
        };
    }

    private function credentialKindLabel(?string $kind): string
    {
        return match ($kind) {
            'registration' => 'Registration',
            'certification' => 'Certification',
            'insurance' => 'Insurance',
            default => 'Credential',
        };
    }

    private function credentialStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'verified' => 'Verified Record',
            'pending' => 'Pending Review',
            'expired' => 'Expired Record',
            'inactive' => 'Inactive',
            default => 'Not Assessed',
        };
    }

    private function documentCategoryLabel(?string $category): string
    {
        return match ($category) {
            'legal' => 'Legal',
            'financial' => 'Financial',
            'insurance' => 'Insurance',
            'certification' => 'Certification',
            'corporate' => 'Corporate',
            default => 'Document',
        };
    }

    private function documentStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'verified' => 'Verified Record',
            'pending' => 'Pending Review',
            'expired' => 'Expired',
            'inactive' => 'Inactive',
            default => 'Not Assessed',
        };
    }
}
