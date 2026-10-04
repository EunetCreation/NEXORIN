<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyDirectoryIndexRequest;
use App\Http\Requests\StoreCompanyDirectorySavedViewRequest;
use App\Http\Requests\StoreCompanyDraftRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\CompanyCredential;
use App\Models\CompanyDocument;
use App\Models\CompanyDirectorySavedView;
use App\Models\CompanyMembership;
use App\Services\CompanyDirectory\CompanyDirectoryService;
use App\Services\CompanyProfile\CompanyProfileWorkspaceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyController extends Controller
{
    /**
     * Display the company directory.
     */
    public function index(CompanyDirectoryIndexRequest $request, CompanyDirectoryService $directory): View
    {
        return view('companies.index', $directory->build($request->filters(), $request->user()));
    }

    /** Export the currently filtered company directory without hidden sensitive fields. */
    public function export(CompanyDirectoryIndexRequest $request, CompanyDirectoryService $directory): StreamedResponse
    {
        return $directory->export($request->filters());
    }

    /** Persist a user-owned directory filter preset. */
    public function storeDirectoryView(
        StoreCompanyDirectorySavedViewRequest $request,
        CompanyDirectoryService $directory
    ): RedirectResponse {
        $data = $request->validated();

        if ((bool) ($data['is_default'] ?? false)) {
            CompanyDirectorySavedView::query()
                ->where('user_id', $request->user()->getKey())
                ->update(['is_default' => false]);
        }

        CompanyDirectorySavedView::query()->updateOrCreate(
            [
                'user_id' => $request->user()->getKey(),
                'name' => trim($data['name']),
            ],
            [
                'filters' => $directory->safeFiltersForSavedView($data['filters']),
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]
        );

        return back()->with('success', 'Company directory view saved.');
    }

    /**
     * Show the form for creating a company.
     */
    public function create(): View
    {
        return view('companies.create');
    }

    /**
     * Store a newly created company.
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $company = DB::transaction(function () use ($request): Company {
            $data = $request->validated();
            $data['slug'] = $this->makeUniqueSlug(
                $data['name'],
                $data['code']
            );
            $data['created_by'] = $request->user()->getKey();

            $company = Company::query()->create($this->extractCompanyPayload($data));
            $this->syncExtendedCompanyData($company, $data, $request);

            return $company;
        });

        return redirect()
            ->route('companies.show', $company)
            ->with('success', 'Company created successfully.');
    }

    /**
     * Display a company profile.
     */
    public function show(Company $company, CompanyProfileWorkspaceService $workspace): View
    {
        return view('companies.show', $workspace->build($company));
    }

    /**
     * Show the form for editing a company.
     */
    public function edit(Company $company): View
    {
        $schema = $this->companyExtensionSchema();
        $company->load('creator:id,name,email');

        foreach ([
            'profile' => 'company_profiles',
            'contacts' => 'company_contacts',
            'regions' => 'company_regions',
            'documents' => 'company_documents',
            'credentials' => 'company_credentials',
        ] as $relation => $table) {
            if ($schema[$table]) {
                $company->load($relation);
            } else {
                $company->setRelation($relation, $relation === 'profile' ? null : collect());
            }
        }

        $company->loadCount([
            'memberships',
            'activeMemberships',
            'customers',
            'projects',
        ]);

        $activeMembers = $company->activeMemberships()
            ->select(['id','company_id','user_id','status','joined_at'])
            ->with(['user:id,name,email','roles:id,code,name'])
            ->latest('joined_at')->latest('id')->limit(4)->get();

        $recentProjects = $company->projects()
            ->current()
            ->select(['id','company_id','customer_id','project_number','name','proposed_system','city','province','status','archived_at','updated_at'])
            ->with('customer:id,name')
            ->latest('updated_at')->latest('id')->limit(5)->get();

        $company->setRelation(
            'financialSnapshots',
            $schema['company_financial_snapshots'] ? $company->financialSnapshots()->limit(8)->get() : collect()
        );

        $dashboard = $this->buildEditDashboard($company, $activeMembers, $recentProjects);
        $companyDraft = $schema['company_edit_drafts']
            ? $company->editDrafts()->where('user_id', request()->user()->getKey())->latest('updated_at')->first()
            : null;

        return view('companies.edit', [
            'company' => $company,
            'activeMembers' => $activeMembers,
            'recentProjects' => $recentProjects,
            'dashboard' => $dashboard,
            'companyDraft' => $companyDraft,
            'companyExtensionSchema' => $schema,
            'companyExtensionReady' => ! in_array(false, $schema, true),
        ]);
    }

    /**
     * Persist a non-authoritative edit draft for the current administrator.
     */
    public function storeDraft(StoreCompanyDraftRequest $request, Company $company): JsonResponse
    {
        if (! Schema::hasTable('company_edit_drafts')) {
            return response()->json([
                'status' => 'migration_required',
                'message' => 'Company draft storage is not available until the Page 005 migrations are applied.',
            ], 503);
        }

        $payload = collect($request->validated())->all();
        $draft = $company->editDrafts()->updateOrCreate(
            ['user_id' => $request->user()->getKey()],
            ['payload' => $payload, 'status' => 'draft']
        );

        return response()->json([
            'status' => 'saved',
            'saved_at' => $draft->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Update a company.
     */
    public function update(
        UpdateCompanyRequest $request,
        Company $company
    ): RedirectResponse {
        DB::transaction(function () use ($request, $company): void {
            $data = $request->validated();
            $companyPayload = $this->extractCompanyPayload($data);

            if (
                $company->name !== $companyPayload['name']
                || $company->code !== $companyPayload['code']
            ) {
                $companyPayload['slug'] = $this->makeUniqueSlug(
                    $companyPayload['name'],
                    $companyPayload['code'],
                    $company->getKey()
                );
            }

            $company->update($companyPayload);
            $this->syncExtendedCompanyData($company, $data, $request);
            if (Schema::hasTable('company_edit_drafts')) {
                $company->editDrafts()->where('user_id', $request->user()->getKey())->delete();
            }
        });

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', 'Company changes saved successfully.');
    }

    /**
     * Update a company status from the company directory.
     */
    public function updateStatus(
        Request $request,
        Company $company
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $company->update([
            'status' => $validated['status'],
        ]);

        return back()->with(
            'success',
            $validated['status'] === 'active'
                ? 'Company activated successfully.'
                : 'Company deactivated successfully.'
        );
    }

    /**
     * Soft delete a company.
     */
    public function destroy(Company $company): RedirectResponse
    {
        if ($company->isActive()) {
            return back()->with(
                'error',
                'Deactivate the company before archiving it.'
            );
        }

        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company archived successfully.');
    }

    /**
     * Extract the company base payload.
     */
    private function extractCompanyPayload(array $data): array
    {
        return collect($data)->only([
            'name',
            'slug',
            'code',
            'registration_number',
            'email',
            'phone',
            'address',
            'status',
            'created_by',
        ])->all();
    }

    /**
     * Synchronize the extended edit-company records.
     */
    private function syncExtendedCompanyData(Company $company, array $data, ?Request $request = null): void
    {
        $schema = $this->companyExtensionSchema();

        if ($schema['company_profiles']) {
            $profile = $company->profile()->firstOrNew(['company_id' => $company->getKey()]);
            $profile->fill(collect($data)->only([
                'legal_name','company_type','year_established','website','hq_country','hq_city','description',
                'data_confidence','categories_served','focus_markets','team_size','parent_company_name',
                'parent_company_country','annual_revenue_usd','ebitda_margin','current_ratio','total_assets_usd',
                'net_profit_usd','debt_to_equity','performance_score','last_reviewed_at',
            ])->all());

            if ($schema['company_profile_image'] && $request?->boolean('remove_profile_image') && $profile->profile_image_path) {
                Storage::disk('public')->delete($profile->profile_image_path);
                $profile->profile_image_path = null;
            }

            if ($schema['company_profile_image'] && $request?->hasFile('profile_image')) {
                if ($profile->profile_image_path) {
                    Storage::disk('public')->delete($profile->profile_image_path);
                }
                $profile->profile_image_path = $request->file('profile_image')->store('companies/profile-images', 'public');
            }

            $profile->save();
            $company->setRelation('profile', $profile);
        }

        if ($schema['company_financial_snapshots']) {
            $financialMetricMap = [
                'annual_revenue' => 'annual_revenue_usd',
                'ebitda_margin' => 'ebitda_margin',
                'current_ratio' => 'current_ratio',
                'total_assets' => 'total_assets_usd',
                'net_profit' => 'net_profit_usd',
                'debt_to_equity' => 'debt_to_equity',
            ];
            $financialValues = collect($financialMetricMap)
                ->mapWithKeys(fn (string $sourceKey, string $targetKey) => [$targetKey => $data[$sourceKey] ?? null]);
            if (filled($data['financial_period_end'] ?? null) && $financialValues->filter(fn ($value) => filled($value))->isNotEmpty()) {
                $company->financialSnapshots()->updateOrCreate(
                    ['period_end' => $data['financial_period_end']],
                    $financialValues->all() + ['currency' => 'USD', 'recorded_by' => $request?->user()?->getKey()]
                );
            }
        }

        if ($schema['company_contacts']) {
            $this->replaceRelatedRows($company, 'contacts', collect($data['contacts'] ?? [])->filter(fn (array $row) => filled($row['full_name'] ?? null))->values()->map(fn (array $row, int $index) => [
                'full_name' => $row['full_name'], 'job_title' => $row['job_title'] ?? null, 'email' => $row['email'] ?? null, 'phone' => $row['phone'] ?? null, 'is_primary' => (bool) ($row['is_primary'] ?? false), 'sort_order' => $index + 1,
            ]));
        }

        if ($schema['company_regions']) {
            $this->replaceRelatedRows($company, 'regions', collect($data['regions'] ?? [])->filter(fn (array $row) => filled($row['country_name'] ?? null))->values()->map(fn (array $row, int $index) => [
                'country_name' => $row['country_name'], 'country_code' => $row['country_code'] ?? null, 'region_name' => $row['region_name'] ?? null, 'latitude' => $row['latitude'] ?? null, 'longitude' => $row['longitude'] ?? null, 'project_count' => $row['project_count'] ?? 0, 'is_headquarters' => (bool) ($row['is_headquarters'] ?? false), 'sort_order' => $index + 1,
            ]));
        }

        if ($schema['company_documents']) {
            $this->replaceRelatedRows($company, 'documents', collect($data['documents'] ?? [])->filter(fn (array $row) => filled($row['title'] ?? null))->values()->map(fn (array $row, int $index) => [
                'category' => $row['category'] ?? 'general', 'title' => $row['title'], 'file_name' => $row['file_name'] ?? null, 'file_size_kb' => $row['file_size_kb'] ?? null, 'issued_at' => $row['issued_at'] ?? null, 'expires_at' => $row['expires_at'] ?? null, 'status' => $row['status'] ?? 'draft', 'sort_order' => $index + 1,
            ]));
        }

        if ($schema['company_credentials']) {
            $credentialRows = collect();
            foreach (['registrations', 'certifications', 'insurances'] as $kind) {
                $credentialRows = $credentialRows->merge(collect($data[$kind] ?? [])->filter(fn (array $row) => filled($row['title'] ?? null))->values()->map(function (array $row, int $index) use ($kind): array {
                    return [
                        'kind' => Str::singular($kind), 'title' => $row['title'], 'reference_number' => $row['reference_number'] ?? null, 'issuing_authority' => $row['issuing_authority'] ?? null, 'policy_provider' => $row['policy_provider'] ?? null, 'coverage_amount_usd' => $row['coverage_amount_usd'] ?? null, 'issued_at' => $row['issued_at'] ?? null, 'expires_at' => $row['expires_at'] ?? null, 'status' => $row['status'] ?? 'pending', 'sort_order' => $index + 1,
                    ];
                }));
            }
            $this->replaceRelatedRows($company, 'credentials', $credentialRows);
        }
    }

    /**
     * Return Page 005 extension-table availability so older databases degrade gracefully instead of throwing 500.
     *
     * @return array<string, bool>
     */
    private function companyExtensionSchema(): array
    {
        $tables = collect([
            'company_profiles',
            'company_contacts',
            'company_regions',
            'company_documents',
            'company_credentials',
            'company_edit_drafts',
            'company_financial_snapshots',
        ])->mapWithKeys(fn (string $table) => [$table => Schema::hasTable($table)])->all();

        $tables['company_profile_image'] = $tables['company_profiles']
            && Schema::hasColumn('company_profiles', 'profile_image_path');

        return $tables;
    }

    /**
     * Replace a simple has-many collection from posted rows.
     */
    private function replaceRelatedRows(Company $company, string $relation, Collection $rows): void
    {
        $company->{$relation}()->delete();

        if ($rows->isNotEmpty()) {
            $company->{$relation}()->createMany($rows->all());
        }
    }

    /**
     * Build the edit-company dashboard context.
     */
    private function buildEditDashboard(
        Company $company,
        Collection $activeMembers,
        Collection $recentProjects
    ): array {
        $profile = $company->profile;
        $credentials = $company->credentials;
        $documents = $company->documents;
        $contacts = $company->contacts;
        $regions = $company->regions;
        $financialSnapshots = $company->relationLoaded('financialSnapshots') ? $company->financialSnapshots : collect();

        $coreCompletenessFields = collect([
            $company->name,
            $company->code,
            $company->email,
            $company->phone,
            $company->address,
            $company->registration_number,
            $profile?->legal_name,
            $profile?->company_type,
            $profile?->year_established,
            $profile?->website,
            $profile?->hq_country,
            $profile?->hq_city,
            $profile?->description,
            $profile?->team_size,
            $profile?->parent_company_name,
            $profile?->performance_score,
        ]);

        $relationSignals = collect([
            $contacts->isNotEmpty(),
            $regions->isNotEmpty(),
            $documents->isNotEmpty(),
            $credentials->isNotEmpty(),
            $activeMembers->isNotEmpty(),
            $recentProjects->isNotEmpty(),
        ]);

        $filledCount = $coreCompletenessFields->filter(fn ($value) => filled($value))->count();
        $completeness = (int) round((($filledCount + $relationSignals->filter()->count()) / ($coreCompletenessFields->count() + $relationSignals->count())) * 100);

        $lastReviewFresh = $profile?->last_reviewed_at && $profile->last_reviewed_at->greaterThan(now()->subDays(180));
        $accuracy = min(100, max(25, (int) round(($filledCount / $coreCompletenessFields->count()) * 70) + ($lastReviewFresh ? 15 : 0) + ($documents->isNotEmpty() ? 10 : 0) + ($credentials->isNotEmpty() ? 5 : 0)));

        $complianceRelevant = $credentials->filter(fn ($item) => in_array($item->kind, ['registration', 'certification', 'insurance'], true));
        $healthyCompliance = $complianceRelevant->filter(function ($item): bool {
            if (in_array($item->status, ['verified', 'active'], true) === false) {
                return false;
            }

            return ! $item->expires_at || $item->expires_at->isFuture();
        })->count();
        $compliance = $complianceRelevant->isEmpty() ? 0 : (int) round(($healthyCompliance / $complianceRelevant->count()) * 100);

        $responsiveness = min(100, 20 + ($contacts->count() * 12) + ($activeMembers->count() * 8) + ($recentProjects->count() * 5));

        $profileHealth = [
            'score' => max(0, min(100, (int) round(($completeness * 0.45) + ($accuracy * 0.2) + ($compliance * 0.2) + ($responsiveness * 0.15)))),
            'completeness' => $completeness,
            'accuracy' => $accuracy,
            'compliance' => $compliance,
            'responsiveness' => $responsiveness,
        ];

        $complianceItems = $credentials->sortBy('expires_at')->take(4)->map(function ($item): array {
            $tone = match ($item->status) {
                'verified', 'active' => ($item->expires_at && $item->expires_at->lessThanOrEqualTo(now()->addDays(30))) ? 'warn' : 'good',
                'expired' => 'danger',
                default => 'warn',
            };

            $meta = [];
            if ($item->reference_number) {
                $meta[] = $item->reference_number;
            }
            if ($item->expires_at) {
                $meta[] = 'Exp ' . $item->expires_at->format('M d, Y');
            }

            return [
                'title' => $item->title,
                'meta' => implode(' · ', $meta) ?: ucfirst($item->kind),
                'tone' => $tone,
                'status' => Str::headline($item->status),
            ];
        })->values();

        $pendingReviews = collect();
        if (blank($company->registration_number)) {
            $pendingReviews->push(['title' => 'Registration number missing', 'tone' => 'warn', 'status' => 'Open']);
        }
        if ($profile?->last_reviewed_at === null) {
            $pendingReviews->push(['title' => 'Profile review has not been recorded', 'tone' => 'warn', 'status' => 'Medium']);
        }
        foreach ($credentials as $item) {
            if ($item->expires_at && $item->expires_at->lessThanOrEqualTo(now()->addDays(30))) {
                $pendingReviews->push([
                    'title' => $item->title,
                    'tone' => $item->expires_at->isPast() ? 'danger' : 'warn',
                    'status' => $item->expires_at->isPast() ? 'Expired' : 'Soon',
                ]);
            }
        }
        $pendingReviews = $pendingReviews->take(4)->values();

        $suggestedActions = collect();
        if ($documents->where('category', 'financial')->isEmpty()) {
            $suggestedActions->push(['title' => 'Upload financial documents', 'copy' => 'Strengthen due diligence with a finance document set.', 'action' => 'Add', 'target' => 'company-documents']);
        }
        if ($contacts->where('is_primary', true)->isEmpty()) {
            $suggestedActions->push(['title' => 'Assign a primary contact', 'copy' => 'Set the operational owner for this account profile.', 'action' => 'Add', 'target' => 'company-contacts']);
        }
        if ($regions->isEmpty()) {
            $suggestedActions->push(['title' => 'Define served regions', 'copy' => 'Capture operating coverage instead of leaving the profile blank.', 'action' => 'Add', 'target' => 'company-regions']);
        }
        if ($profile?->performance_score === null) {
            $suggestedActions->push(['title' => 'Record performance score', 'copy' => 'Enable portfolio benchmarking and profile scoring.', 'action' => 'Update', 'target' => 'company-performance']);
        }
        $suggestedActions = $suggestedActions->take(4)->values();

        $recentActivity = collect([
            [
                'title' => 'Company record updated',
                'meta' => optional($company->updated_at)->format('M d, Y h:i A'),
                'at' => $company->updated_at,
            ],
            $profile ? [
                'title' => 'Extended profile refreshed',
                'meta' => optional($profile->updated_at)->format('M d, Y h:i A'),
                'at' => $profile->updated_at,
            ] : null,
        ])->filter();

        foreach ($documents->take(3) as $document) {
            $recentActivity->push([
                'title' => $document->title,
                'meta' => 'Document record · ' . optional($document->updated_at)->format('M d, Y'),
                'at' => $document->updated_at,
            ]);
        }
        foreach ($credentials->take(3) as $credential) {
            $recentActivity->push([
                'title' => $credential->title,
                'meta' => Str::headline($credential->kind) . ' · ' . optional($credential->updated_at)->format('M d, Y'),
                'at' => $credential->updated_at,
            ]);
        }
        $recentActivity = $recentActivity
            ->sortByDesc(fn ($item) => $item['at'] ?? now()->subYears(5))
            ->take(6)
            ->values();

        return [
            'profileHealth' => $profileHealth,
            'complianceItems' => $complianceItems,
            'pendingReviews' => $pendingReviews,
            'suggestedActions' => $suggestedActions,
            'recentActivity' => $recentActivity,
            'registrations' => $credentials->where('kind', 'registration')->values(),
            'certifications' => $credentials->where('kind', 'certification')->values(),
            'insurances' => $credentials->where('kind', 'insurance')->values(),
            'financialDocuments' => $documents->where('category', 'financial')->values(),
            'financialTrend' => [
                'annual_revenue_usd' => $financialSnapshots->sortBy('period_end')->pluck('annual_revenue')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values(),
                'ebitda_margin' => $financialSnapshots->sortBy('period_end')->pluck('ebitda_margin')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values(),
                'current_ratio' => $financialSnapshots->sortBy('period_end')->pluck('current_ratio')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values(),
                'total_assets_usd' => $financialSnapshots->sortBy('period_end')->pluck('total_assets')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values(),
                'net_profit_usd' => $financialSnapshots->sortBy('period_end')->pluck('net_profit')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values(),
                'debt_to_equity' => $financialSnapshots->sortBy('period_end')->pluck('debt_to_equity')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values(),
            ],
            'libraryDocuments' => $documents->values(),
        ];
    }

    /**
     * Generate a unique company slug.
     */
    private function makeUniqueSlug(
        string $name,
        string $code,
        ?int $ignoreCompanyId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = Str::lower($code);
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (
            Company::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreCompanyId,
                    fn (Builder $query) => $query->whereKeyNot($ignoreCompanyId)
                )
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
