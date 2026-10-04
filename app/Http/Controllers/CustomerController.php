<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerCreateDraftRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerCreateDraft;
use App\Models\CustomerDocument;
use App\Models\CustomerOpportunity;
use App\Models\CustomerProfile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Display the company customer directory.
     */
    public function index(
        Request $request,
        Company $company
    ): View {
        $relationshipStages = ['lead', 'prospect', 'active', 'on_hold', 'closed'];

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'type' => ['nullable', Rule::in(['residential', 'commercial'])],
            'stage' => ['nullable', Rule::in($relationshipStages)],
            'owner' => ['nullable', 'string', 'max:40', 'regex:/^(unassigned|[1-9][0-9]*)$/'],
            'region' => ['nullable', 'string', 'max:120'],
            'portal' => ['nullable', Rule::in(['linked', 'unlinked'])],
            'projects' => ['nullable', Rule::in(['open', 'none'])],
            'profile' => ['nullable', Rule::in(['recorded', 'missing'])],
            'evidence' => ['nullable', Rule::in(['recorded', 'missing'])],
            'sort' => [
                'nullable',
                Rule::in([
                    'created_at',
                    'updated_at',
                    'name',
                    'customer_number',
                    'type',
                    'status',
                    'relationship_stage',
                    'region',
                    'active_projects',
                ]),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $schema = $this->customerCrmSchema();
        $profileReady = (bool) $schema['customer_profiles'];
        $assessmentReady = (bool) $schema['customer_profile_assessment_metadata'];
        $contactsReady = (bool) $schema['customer_contacts'];
        $opportunitiesReady = (bool) $schema['customer_opportunities'];
        $documentsReady = (bool) $schema['customer_documents'];

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? null;
        $type = $validated['type'] ?? null;
        $stage = $profileReady ? ($validated['stage'] ?? null) : null;
        $owner = $profileReady ? ($validated['owner'] ?? null) : null;
        $region = $profileReady ? trim((string) ($validated['region'] ?? '')) : '';
        $portal = $validated['portal'] ?? null;
        $projects = $validated['projects'] ?? null;
        $profileState = $profileReady ? ($validated['profile'] ?? null) : null;
        $evidence = $documentsReady ? ($validated['evidence'] ?? null) : null;
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        if (! $profileReady && in_array($sort, ['relationship_stage', 'region'], true)) {
            $sort = 'created_at';
            $direction = 'desc';
        }

        $profileColumns = [
            'id',
            'customer_id',
            'preferred_name',
            'relationship_stage',
            'primary_industry',
            'sub_industry',
            'region',
            'account_owner_user_id',
        ];
        if ($assessmentReady) {
            $profileColumns[] = 'opportunity_assessment';
            $profileColumns[] = 'review_acknowledged_at';
        }

        $openProjectConstraint = static fn (Builder $query): Builder => $query
            ->whereNull('archived_at')
            ->whereNotIn('status', ['completed', 'cancelled']);

        $customers = $company->customers()
            ->with('portalUser:id,name,email')
            ->withCount([
                'projects',
                'projects as active_projects_count' => $openProjectConstraint,
            ])
            ->when(
                $profileReady,
                fn (Builder $query): Builder => $query
                    ->with([
                        'profile' => fn ($profileQuery) => $profileQuery->select($profileColumns),
                        'profile.accountOwner:id,name,email',
                    ])
            )
            ->when(
                $contactsReady,
                fn (Builder $query): Builder => $query
                    ->with([
                        'contacts' => fn ($contactQuery) => $contactQuery
                            ->select(['id', 'customer_id', 'full_name', 'job_title', 'email', 'phone', 'is_primary', 'sort_order'])
                            ->where('is_primary', true)
                            ->orderBy('sort_order')
                            ->orderBy('id'),
                    ])
                    ->withCount('contacts')
            )
            ->when(
                $opportunitiesReady,
                fn (Builder $query): Builder => $query->withCount([
                    'opportunities as open_opportunities_count' => fn (Builder $opportunityQuery): Builder => $opportunityQuery
                        ->where('status', 'open'),
                ])
            )
            ->when(
                $documentsReady,
                fn (Builder $query): Builder => $query->withCount('documents')
            )
            ->when(
                $search !== '',
                function (Builder $query) use (
                    $search,
                    $profileReady,
                    $contactsReady,
                    $opportunitiesReady
                ): void {
                    $query->where(function (Builder $customerQuery) use (
                        $search,
                        $profileReady,
                        $contactsReady,
                        $opportunitiesReady
                    ): void {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('province', 'like', "%{$search}%");

                        if ($profileReady) {
                            $customerQuery
                                ->orWhereHas('profile', fn (Builder $profileQuery): Builder => $profileQuery
                                    ->where('preferred_name', 'like', "%{$search}%")
                                    ->orWhere('primary_industry', 'like', "%{$search}%")
                                    ->orWhere('sub_industry', 'like', "%{$search}%")
                                    ->orWhere('region', 'like', "%{$search}%"))
                                ->orWhereHas('profile.accountOwner', fn (Builder $ownerQuery): Builder => $ownerQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%"));
                        }

                        if ($contactsReady) {
                            $customerQuery->orWhereHas('contacts', fn (Builder $contactQuery): Builder => $contactQuery
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%"));
                        }

                        if ($opportunitiesReady) {
                            $customerQuery->orWhereHas('opportunities', fn (Builder $opportunityQuery): Builder => $opportunityQuery
                                ->where('name', 'like', "%{$search}%"));
                        }
                    });
                }
            )
            ->when($status, fn (Builder $query): Builder => $query->where('status', $status))
            ->when($type, fn (Builder $query): Builder => $query->where('type', $type))
            ->when(
                $stage,
                fn (Builder $query): Builder => $query->whereHas(
                    'profile',
                    fn (Builder $profileQuery): Builder => $profileQuery->where('relationship_stage', $stage)
                )
            )
            ->when(
                $owner === 'unassigned',
                fn (Builder $query): Builder => $query->where(function (Builder $ownerQuery): void {
                    $ownerQuery
                        ->whereDoesntHave('profile')
                        ->orWhereHas('profile', fn (Builder $profileQuery): Builder => $profileQuery
                            ->whereNull('account_owner_user_id'));
                })
            )
            ->when(
                $owner !== null && $owner !== 'unassigned',
                fn (Builder $query): Builder => $query->whereHas(
                    'profile',
                    fn (Builder $profileQuery): Builder => $profileQuery
                        ->where('account_owner_user_id', (int) $owner)
                )
            )
            ->when(
                $region !== '',
                fn (Builder $query): Builder => $query->whereHas(
                    'profile',
                    fn (Builder $profileQuery): Builder => $profileQuery->where('region', $region)
                )
            )
            ->when(
                $portal === 'linked',
                fn (Builder $query): Builder => $query->whereNotNull('portal_user_id')
            )
            ->when(
                $portal === 'unlinked',
                fn (Builder $query): Builder => $query->whereNull('portal_user_id')
            )
            ->when(
                $projects === 'open',
                fn (Builder $query): Builder => $query->whereHas('projects', $openProjectConstraint)
            )
            ->when(
                $projects === 'none',
                fn (Builder $query): Builder => $query->whereDoesntHave('projects', $openProjectConstraint)
            )
            ->when(
                $profileState === 'recorded',
                fn (Builder $query): Builder => $query->whereHas('profile')
            )
            ->when(
                $profileState === 'missing',
                fn (Builder $query): Builder => $query->whereDoesntHave('profile')
            )
            ->when(
                $evidence === 'recorded',
                fn (Builder $query): Builder => $query->whereHas('documents')
            )
            ->when(
                $evidence === 'missing',
                fn (Builder $query): Builder => $query->whereDoesntHave('documents')
            );

        if ($sort === 'relationship_stage' && $profileReady) {
            $customers->orderBy(
                CustomerProfile::query()
                    ->select('relationship_stage')
                    ->whereColumn('customer_profiles.customer_id', 'customers.id')
                    ->limit(1),
                $direction
            )->orderBy('name');
        } elseif ($sort === 'region' && $profileReady) {
            $customers->orderBy(
                CustomerProfile::query()
                    ->select('region')
                    ->whereColumn('customer_profiles.customer_id', 'customers.id')
                    ->limit(1),
                $direction
            )->orderBy('name');
        } elseif ($sort === 'active_projects') {
            $customers->orderBy('active_projects_count', $direction)->orderBy('name');
        } else {
            $customers->orderBy($sort, $direction)->orderBy('id', 'desc');
        }

        $customers = $customers
            ->paginate(12)
            ->withQueryString();

        $core = $company->customers()
            ->selectRaw(
                "COUNT(*) AS total,
                 SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                 SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived,
                 SUM(CASE WHEN type = 'residential' THEN 1 ELSE 0 END) AS residential,
                 SUM(CASE WHEN type = 'commercial' THEN 1 ELSE 0 END) AS commercial,
                 SUM(CASE WHEN portal_user_id IS NOT NULL THEN 1 ELSE 0 END) AS portal_linked"
            )
            ->first();

        $openProjectAggregate = Project::query()
            ->where('company_id', $company->getKey())
            ->whereNull('archived_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->selectRaw('COUNT(*) AS total, COUNT(DISTINCT customer_id) AS customer_count')
            ->first();

        $stats = [
            'total' => (int) ($core?->total ?? 0),
            'active' => (int) ($core?->active ?? 0),
            'archived' => (int) ($core?->archived ?? 0),
            'residential' => (int) ($core?->residential ?? 0),
            'commercial' => (int) ($core?->commercial ?? 0),
            'portal_linked' => (int) ($core?->portal_linked ?? 0),
            'customers_with_open_projects' => (int) ($openProjectAggregate?->customer_count ?? 0),
            'open_projects' => (int) ($openProjectAggregate?->total ?? 0),
            'profiles_recorded' => 0,
            'owners_assigned' => 0,
            'primary_contacts_recorded' => 0,
            'customers_with_evidence' => 0,
            'documents_recorded' => 0,
            'open_opportunities' => 0,
            'customers_with_open_opportunities' => 0,
            'review_acknowledged' => 0,
        ];

        $stageLabels = [
            'lead' => 'Lead',
            'prospect' => 'Prospect',
            'active' => 'Active Relationship',
            'on_hold' => 'On Hold',
            'closed' => 'Closed',
        ];
        $relationshipStageData = [];

        if ($profileReady) {
            $profileAggregateSelect = 'COUNT(*) AS total, '
                .'SUM(CASE WHEN account_owner_user_id IS NOT NULL THEN 1 ELSE 0 END) AS owners_assigned';
            if ($assessmentReady) {
                $profileAggregateSelect .= ', SUM(CASE WHEN review_acknowledged_at IS NOT NULL THEN 1 ELSE 0 END) AS review_acknowledged';
            }

            $profileAggregate = CustomerProfile::query()
                ->where('company_id', $company->getKey())
                ->selectRaw($profileAggregateSelect)
                ->first();

            $stats['profiles_recorded'] = (int) ($profileAggregate?->total ?? 0);
            $stats['owners_assigned'] = (int) ($profileAggregate?->owners_assigned ?? 0);
            if ($assessmentReady) {
                $stats['review_acknowledged'] = (int) ($profileAggregate?->review_acknowledged ?? 0);
            }

            $stageCounts = CustomerProfile::query()
                ->where('company_id', $company->getKey())
                ->select('relationship_stage')
                ->selectRaw('COUNT(*) AS aggregate')
                ->groupBy('relationship_stage')
                ->get()
                ->mapWithKeys(fn (CustomerProfile $profile): array => [
                    (string) $profile->relationship_stage => (int) $profile->aggregate,
                ]);

            foreach ($relationshipStages as $stageKey) {
                $relationshipStageData[] = [
                    'key' => $stageKey,
                    'label' => $stageLabels[$stageKey],
                    'value' => (int) ($stageCounts[$stageKey] ?? 0),
                ];
            }

            $missingProfiles = max(0, $stats['total'] - $stats['profiles_recorded']);
            if ($missingProfiles > 0) {
                $relationshipStageData[] = [
                    'key' => 'profile_not_recorded',
                    'label' => 'Profile Not Recorded',
                    'value' => $missingProfiles,
                ];
            }
        }

        if ($contactsReady) {
            $stats['primary_contacts_recorded'] = CustomerContact::query()
                ->where('company_id', $company->getKey())
                ->where('is_primary', true)
                ->distinct()
                ->count('customer_id');
        }

        if ($documentsReady) {
            $documentAggregate = CustomerDocument::query()
                ->where('company_id', $company->getKey())
                ->selectRaw('COUNT(*) AS total, COUNT(DISTINCT customer_id) AS customer_count')
                ->first();
            $stats['customers_with_evidence'] = (int) ($documentAggregate?->customer_count ?? 0);
            $stats['documents_recorded'] = (int) ($documentAggregate?->total ?? 0);
        }

        $openOpportunityCurrencyTotals = collect();
        $openOpportunities = collect();

        if ($opportunitiesReady) {
            $openOpportunityAggregate = CustomerOpportunity::query()
                ->where('company_id', $company->getKey())
                ->where('status', 'open')
                ->selectRaw('COUNT(*) AS total, COUNT(DISTINCT customer_id) AS customer_count')
                ->first();
            $stats['open_opportunities'] = (int) ($openOpportunityAggregate?->total ?? 0);
            $stats['customers_with_open_opportunities'] = (int) ($openOpportunityAggregate?->customer_count ?? 0);

            $openOpportunityCurrencyTotals = CustomerOpportunity::query()
                ->where('company_id', $company->getKey())
                ->where('status', 'open')
                ->whereNotNull('estimated_value')
                ->whereNotNull('currency_code')
                ->select('currency_code')
                ->selectRaw('COUNT(*) AS opportunity_count, SUM(estimated_value) AS total_value')
                ->groupBy('currency_code')
                ->orderBy('currency_code')
                ->get();

            $openOpportunities = CustomerOpportunity::query()
                ->where('company_id', $company->getKey())
                ->where('status', 'open')
                ->with('customer:id,company_id,name,customer_number')
                ->latest('updated_at')
                ->limit(6)
                ->get();
        }

        $projectStatusLabels = [
            'draft' => 'Draft',
            'survey' => 'Survey',
            'engineering' => 'Engineering',
            'quotation' => 'Quotation',
            'approved' => 'Approved',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        $projectStatusData = Project::query()
            ->where('company_id', $company->getKey())
            ->whereNull('archived_at')
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (Project $project): array => [
                'key' => (string) $project->status,
                'label' => $projectStatusLabels[(string) $project->status] ?? 'Other Recorded State',
                'value' => (int) $project->aggregate,
            ])
            ->values();

        $coverage = [
            [
                'key' => 'profile',
                'label' => 'Customer Profile',
                'value' => $profileReady ? $stats['profiles_recorded'] : null,
                'available' => $profileReady,
            ],
            [
                'key' => 'owner',
                'label' => 'Account Owner',
                'value' => $profileReady ? $stats['owners_assigned'] : null,
                'available' => $profileReady,
            ],
            [
                'key' => 'contact',
                'label' => 'Primary Contact',
                'value' => $contactsReady ? $stats['primary_contacts_recorded'] : null,
                'available' => $contactsReady,
            ],
            [
                'key' => 'portal',
                'label' => 'Portal Link',
                'value' => $stats['portal_linked'],
                'available' => true,
            ],
            [
                'key' => 'evidence',
                'label' => 'Evidence',
                'value' => $documentsReady ? $stats['customers_with_evidence'] : null,
                'available' => $documentsReady,
            ],
        ];

        $regions = $profileReady
            ? CustomerProfile::query()
                ->where('company_id', $company->getKey())
                ->whereNotNull('region')
                ->where('region', '<>', '')
                ->distinct()
                ->orderBy('region')
                ->limit(100)
                ->pluck('region')
            : collect();

        $accountOwners = $profileReady ? $this->accountOwners($company) : collect();

        $exactProjectQuery = Project::query()
            ->where('company_id', $company->getKey())
            ->whereNull('archived_at')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $exactProjectCoordinateCount = (clone $exactProjectQuery)->count();
        $projectMapPoints = (clone $exactProjectQuery)
            ->with('customer:id,company_id,name,customer_number')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->getKey(),
                'project' => $project->name,
                'projectNumber' => $project->project_number,
                'customer' => $project->customer?->name,
                'customerNumber' => $project->customer?->customer_number,
                'status' => $projectStatusLabels[(string) $project->status] ?? 'Recorded Project',
                'city' => $project->city,
                'province' => $project->province,
                'latitude' => (float) $project->latitude,
                'longitude' => (float) $project->longitude,
            ])
            ->values();

        $projectRegionData = Project::query()
            ->where('company_id', $company->getKey())
            ->whereNull('archived_at')
            ->whereNotNull('province')
            ->where('province', '<>', '')
            ->select('province')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('province')
            ->orderByDesc('aggregate')
            ->limit(8)
            ->get()
            ->map(fn (Project $project): array => [
                'label' => (string) $project->province,
                'value' => (int) $project->aggregate,
            ])
            ->values();

        $recentCustomerUpdates = $company->customers()
            ->select(['id', 'company_id', 'customer_number', 'name', 'status', 'updated_at'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $activeFilters = collect([
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'stage' => $stage,
            'owner' => $owner,
            'region' => $region,
            'portal' => $portal,
            'projects' => $projects,
            'profile' => $profileState,
            'evidence' => $evidence,
        ])->filter(fn ($value): bool => filled($value))->count();

        return view('customers.index', [
            'company' => $company,
            'customers' => $customers,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
                'stage' => $stage,
                'owner' => $owner,
                'region' => $region,
                'portal' => $portal,
                'projects' => $projects,
                'profile' => $profileState,
                'evidence' => $evidence,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'activeFilters' => $activeFilters,
            'relationshipStageData' => $relationshipStageData,
            'projectStatusData' => $projectStatusData,
            'coverageData' => $coverage,
            'openOpportunityCurrencyTotals' => $openOpportunityCurrencyTotals,
            'openOpportunities' => $openOpportunities,
            'recentCustomerUpdates' => $recentCustomerUpdates,
            'regions' => $regions,
            'accountOwners' => $accountOwners,
            'projectMapPoints' => $projectMapPoints,
            'exactProjectCoordinateCount' => $exactProjectCoordinateCount,
            'projectRegionData' => $projectRegionData,
            'directoryCapabilities' => [
                'profile' => $profileReady,
                'assessment' => $assessmentReady,
                'contacts' => $contactsReady,
                'opportunities' => $opportunitiesReady,
                'documents' => $documentsReady,
            ],
        ]);
    }

    /**
     * Show the customer creation form.
     */
    public function create(Request $request, Company $company): View
    {
        $schema = $this->customerCrmSchema();
        $draft = $schema['customer_create_drafts']
            ? CustomerCreateDraft::query()
                ->where('company_id', $company->getKey())
                ->where('user_id', $request->user()->getKey())
                ->latest('updated_at')
                ->first()
            : null;

        return view('customers.create', [
            'company' => $company,
            'portalUsers' => $this->portalUsers($company),
            'accountOwners' => $this->accountOwners($company),
            'parentCustomers' => $company->customers()
                ->active()
                ->select(['id', 'customer_number', 'name', 'type'])
                ->orderBy('name')
                ->limit(200)
                ->get(),
            'customerDraft' => $draft,
            'customerDraftData' => is_array($draft?->payload) ? $draft->payload : [],
            'customerCrmSchema' => $schema,
            'customerCrmReady' => ! in_array(false, $schema, true),
        ]);
    }

    /**
     * Persist a non-authoritative Page 008 customer intake draft.
     */
    public function storeCreateDraft(
        StoreCustomerCreateDraftRequest $request,
        Company $company
    ): JsonResponse {
        if (! Schema::hasTable('customer_create_drafts')) {
            return response()->json([
                'status' => 'migration_required',
                'message' => 'Customer draft storage is not available until the Page 008 migration is applied.',
            ], 503);
        }

        $payload = $this->sanitizeDraftPayload($request->validated('payload'));
        if (strlen(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') > 65535) {
            return response()->json([
                'status' => 'too_large',
                'message' => 'The customer draft is too large to save. Remove oversized notes and try again.',
            ], 422);
        }

        $draft = CustomerCreateDraft::query()->updateOrCreate(
            [
                'company_id' => $company->getKey(),
                'user_id' => $request->user()->getKey(),
            ],
            [
                'payload' => $payload,
                'saved_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'saved',
            'saved_at' => $draft->saved_at?->toIso8601String(),
        ]);
    }

    /**
     * Store a newly created customer and its optional CRM handoff context.
     */
    public function store(
        StoreCustomerRequest $request,
        Company $company
    ): RedirectResponse {
        $data = $request->validated();
        $schema = $this->customerCrmSchema();
        $needsCrmStorage = $this->hasCrmPayload($data, $request);

        if ($needsCrmStorage && in_array(false, collect($schema)->except('customer_create_drafts')->all(), true)) {
            return back()
                ->withInput()
                ->with('error', 'Customer CRM handoff storage is not available until the Page 008 migration is applied. No customer record was created.');
        }

        $storedPaths = [];

        try {
            $customer = DB::transaction(function () use ($request, $company, $data, $schema, &$storedPaths): Customer {
                $userId = $request->user()->getKey();
                $customer = Customer::query()->create([
                    'company_id' => $company->getKey(),
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'province' => $data['province'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'portal_user_id' => $data['portal_user_id'] ?? null,
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $customer->update([
                    'customer_number' => sprintf(
                        '%s-CUS-%05d',
                        strtoupper($company->code),
                        $customer->getKey()
                    ),
                ]);

                if (! in_array(false, collect($schema)->except('customer_create_drafts')->all(), true)) {
                    $this->persistCustomerCrmHandoff($request, $company, $customer, $data, $storedPaths);
                }

                if ($schema['customer_create_drafts']) {
                    CustomerCreateDraft::query()
                        ->where('company_id', $company->getKey())
                        ->where('user_id', $userId)
                        ->delete();
                }

                return $customer;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        if (($data['submit_intent'] ?? 'create') === 'save_exit') {
            return redirect()
                ->route('companies.customers.index', $company)
                ->with('success', 'Customer created successfully.');
        }

        return redirect()
            ->route('companies.customers.show', [$company, $customer])
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display a customer profile.
     */
    public function show(
        Company $company,
        Customer $customer
    ): View {
        $schema = $this->customerCrmSchema();
        $profileReady = (bool) $schema['customer_profiles'];
        $assessmentReady = (bool) $schema['customer_profile_assessment_metadata'];
        $contactsReady = (bool) $schema['customer_contacts'];
        $opportunitiesReady = (bool) $schema['customer_opportunities'];
        $documentsReady = (bool) $schema['customer_documents'];

        $openProjectConstraint = static fn ($query) => $query
            ->whereNull('archived_at')
            ->whereNotIn('status', ['completed', 'cancelled']);

        $relations = [
            'portalUser:id,name,email,status',
            'creator:id,name,email',
            'updater:id,name,email',
            'projects' => fn ($query) => $query
                ->with([
                    'assignedEngineer:id,name',
                    'assignedSales:id,name',
                ])
                ->latest('updated_at')
                ->limit(8),
        ];

        if ($profileReady) {
            $relations = array_merge($relations, [
                'profile.parentCustomer:id,customer_number,name,type,status',
                'profile.accountOwner:id,name,email,status',
                'profile.reviewAcknowledgedBy:id,name,email',
            ]);
        }

        if ($contactsReady) {
            $relations['contacts'] = fn ($query) => $query
                ->reorder()
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(8);
        }

        if ($opportunitiesReady) {
            $relations['opportunities'] = fn ($query) => $query
                ->where('status', 'open')
                ->latest('updated_at')
                ->limit(8);
        }

        if ($documentsReady) {
            $relations['documents'] = fn ($query) => $query
                ->latest('created_at')
                ->limit(8);
        }

        $customer->load($relations);

        $counts = [
            'projects',
            'projects as current_projects_count' => fn ($query) => $query
                ->whereNull('archived_at'),
            'projects as blocking_projects_count' => $openProjectConstraint,
            'projects as exact_coordinate_projects_count' => fn ($query) => $query
                ->whereNotNull('latitude')
                ->whereNotNull('longitude'),
        ];

        if ($contactsReady) {
            $counts['contacts'] = fn ($query) => $query;
        }

        if ($opportunitiesReady) {
            $counts['opportunities as open_opportunities_count'] = fn ($query) => $query
                ->where('status', 'open');
        }

        if ($documentsReady) {
            $counts['documents'] = fn ($query) => $query;
        }

        $customer->loadCount($counts);

        $blockingProjects = $customer->projects()
            ->whereNull('archived_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest('updated_at')
            ->limit(4)
            ->get(['id', 'company_id', 'customer_id', 'project_number', 'name', 'status', 'updated_at']);

        $projectStatusCounts = $customer->projects()
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $openOpportunityCurrencyTotals = collect();
        if ($opportunitiesReady) {
            $openOpportunityCurrencyTotals = $customer->opportunities()
                ->where('status', 'open')
                ->whereNotNull('estimated_value')
                ->selectRaw('currency_code, COUNT(*) as opportunity_count, SUM(estimated_value) as total_value')
                ->groupBy('currency_code')
                ->orderBy('currency_code')
                ->get();
        }

        return view('customers.show', [
            'company' => $company,
            'customer' => $customer,
            'customerCrmSchema' => $schema,
            'profileReady' => $profileReady,
            'assessmentReady' => $assessmentReady,
            'contactsReady' => $contactsReady,
            'opportunitiesReady' => $opportunitiesReady,
            'documentsReady' => $documentsReady,
            'blockingProjects' => $blockingProjects,
            'projectStatusCounts' => $projectStatusCounts,
            'openOpportunityCurrencyTotals' => $openOpportunityCurrencyTotals,
        ]);
    }

    /**
     * Show the customer edit form.
     */
    public function edit(
        Company $company,
        Customer $customer
    ): View {
        if (! $customer->isActive()) {
            abort(404);
        }

        $schema = $this->customerCrmSchema();
        $crmReady = ! in_array(false, collect($schema)->except('customer_create_drafts')->all(), true);

        $relations = [
            'portalUser:id,name,email,status',
            'creator:id,name,email',
            'updater:id,name,email',
        ];

        if ($crmReady) {
            $relations = array_merge($relations, [
                'profile.parentCustomer:id,customer_number,name,type,status',
                'profile.accountOwner:id,name,email,status',
                'profile.reviewAcknowledgedBy:id,name,email',
                'contacts',
                'opportunities' => fn ($query) => $query
                    ->where('status', 'open')
                    ->latest('updated_at')
                    ->limit(12),
                'documents' => fn ($query) => $query
                    ->latest('created_at')
                    ->limit(12),
            ]);
        }

        $customer->load($relations);
        $customer->loadCount(['projects']);

        if ($crmReady) {
            $customer->loadCount([
                'documents',
                'opportunities as open_opportunities_count' => fn (Builder $query) => $query->where('status', 'open'),
            ]);
        }

        return view('customers.edit', [
            'company' => $company,
            'customer' => $customer,
            'portalUsers' => $this->portalUsers($company),
            'accountOwners' => $crmReady ? $this->accountOwners($company) : collect(),
            'parentCustomers' => $crmReady
                ? $company->customers()
                    ->active()
                    ->where('id', '<>', $customer->getKey())
                    ->select(['id', 'customer_number', 'name', 'type'])
                    ->orderBy('name')
                    ->limit(200)
                    ->get()
                : collect(),
            'customerCrmSchema' => $schema,
            'customerCrmReady' => $crmReady,
        ]);
    }

    /**
     * Update the authoritative customer relationship and Page 008 CRM handoff data.
     */
    public function update(
        UpdateCustomerRequest $request,
        Company $company,
        Customer $customer
    ): RedirectResponse {
        if (! $customer->isActive()) {
            return back()->with(
                'error',
                'Archived customers cannot be updated.'
            );
        }

        $data = $request->validated();
        $schema = $this->customerCrmSchema();
        $crmReady = ! in_array(false, collect($schema)->except('customer_create_drafts')->all(), true);
        $storedPaths = [];

        if (! $crmReady && $this->hasCustomerEditCrmPayload($data, $request)) {
            return back()
                ->withInput()
                ->with('error', 'Customer relationship storage is currently unavailable. No changes were saved.');
        }

        try {
            DB::transaction(function () use ($request, $company, $customer, $data, $crmReady, &$storedPaths): void {
                $userId = $request->user()->getKey();

                $customer->update([
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'province' => $data['province'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'portal_user_id' => $data['portal_user_id'] ?? null,
                    'updated_by' => $userId,
                ]);

                if (! $crmReady) {
                    return;
                }

                $this->updateCustomerCrmProfile($company, $customer, $data, $userId);
                $this->syncCustomerContacts($company, $customer, $data['contacts'] ?? [], $userId);
                $this->syncCustomerOpportunities($company, $customer, $data['opportunities'] ?? [], $userId);
                $this->persistCustomerDocuments($request, $company, $customer, $storedPaths);
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return redirect()
            ->route('companies.customers.show', [$company, $customer])
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Archive a customer record.
     */
    public function archive(
        Request $request,
        Company $company,
        Customer $customer
    ): RedirectResponse {
        if (! $customer->isActive()) {
            return back()->with('error', 'Customer is already archived.');
        }

        $hasOpenProjects = $customer->projects()
            ->whereNull('archived_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($hasOpenProjects) {
            return back()->with(
                'error',
                'Complete, cancel, or archive the customer’s open projects before archiving this customer.'
            );
        }

        $customer->update([
            'status' => 'archived',
            'archived_at' => now(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('companies.customers.index', $company)
            ->with('success', 'Customer archived successfully.');
    }

    /** @return array<string, bool> */
    private function customerCrmSchema(): array
    {
        return [
            'customer_profiles' => Schema::hasTable('customer_profiles'),
            'customer_contacts' => Schema::hasTable('customer_contacts'),
            'customer_opportunities' => Schema::hasTable('customer_opportunities'),
            'customer_documents' => Schema::hasTable('customer_documents'),
            'customer_profile_assessment_metadata' => Schema::hasTable('customer_profiles')
                && Schema::hasColumn('customer_profiles', 'parent_company_assessment')
                && Schema::hasColumn('customer_profiles', 'opportunity_assessment')
                && Schema::hasColumn('customer_profiles', 'review_acknowledged_at')
                && Schema::hasColumn('customer_profiles', 'review_acknowledged_by'),
            'customer_create_drafts' => Schema::hasTable('customer_create_drafts'),
        ];
    }

    private function hasCrmPayload(array $data, Request $request): bool
    {
        $keys = [
            'preferred_name', 'website', 'tax_id', 'registration_number', 'currency_code', 'timezone',
            'year_established', 'employee_count', 'annual_revenue_amount', 'description', 'primary_industry',
            'sub_industry', 'business_model', 'industry_tags', 'parent_customer_id', 'external_parent_name',
            'parent_company_country', 'ownership_pct', 'hq_country', 'operating_countries', 'account_owner_user_id',
            'sales_team', 'region', 'business_unit', 'contacts', 'preferred_contract_type', 'engagement_model',
            'payment_terms', 'contract_duration', 'key_considerations',
        ];

        foreach ($keys as $key) {
            if (filled($data[$key] ?? null)) {
                return true;
            }
        }

        if (($data['parent_company_assessment'] ?? 'not_assessed') !== 'not_assessed'
            || ($data['opportunity_assessment'] ?? 'not_assessed') !== 'not_assessed'
            || $request->boolean('review_acknowledged')) {
            return true;
        }

        $hasDocument = collect(['incorporation', 'tax_registration', 'financial_statements', 'company_profile'])
            ->contains(fn (string $category): bool => $request->hasFile("documents.$category"));

        return (bool) ($data['create_opportunity'] ?? false) || $hasDocument;
    }

    private function hasCustomerEditCrmPayload(array $data, Request $request): bool
    {
        $keys = [
            'preferred_name', 'website', 'tax_id', 'registration_number', 'currency_code', 'timezone',
            'year_established', 'employee_count', 'annual_revenue_amount', 'revenue_currency_code', 'description',
            'relationship_stage', 'primary_industry', 'sub_industry', 'business_model', 'industry_tags',
            'parent_company_assessment', 'parent_customer_id', 'external_parent_name', 'parent_company_country',
            'ownership_pct', 'hq_country', 'operating_countries', 'account_owner_user_id', 'sales_team', 'region',
            'business_unit', 'contacts', 'opportunity_assessment', 'opportunities', 'preferred_contract_type',
            'engagement_model', 'payment_terms', 'contract_duration', 'key_considerations',
        ];

        foreach ($keys as $key) {
            if (filled($data[$key] ?? null)) {
                return true;
            }
        }

        return collect(['incorporation', 'tax_registration', 'financial_statements', 'company_profile'])
            ->contains(fn (string $category): bool => $request->hasFile("documents.$category"));
    }

    /** @param array<int, string> $storedPaths */
    private function persistCustomerCrmHandoff(
        StoreCustomerRequest $request,
        Company $company,
        Customer $customer,
        array $data,
        array &$storedPaths
    ): void {
        $userId = $request->user()->getKey();

        CustomerProfile::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'preferred_name' => $data['preferred_name'] ?? null,
            'website' => $data['website'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'currency_code' => $data['currency_code'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'year_established' => $data['year_established'] ?? null,
            'employee_count' => $data['employee_count'] ?? null,
            'annual_revenue_amount' => $data['annual_revenue_amount'] ?? null,
            'revenue_currency_code' => $data['revenue_currency_code'] ?? ($data['currency_code'] ?? null),
            'description' => $data['description'] ?? null,
            'relationship_stage' => $data['relationship_stage'] ?? 'lead',
            'primary_industry' => $data['primary_industry'] ?? null,
            'sub_industry' => $data['sub_industry'] ?? null,
            'business_model' => $data['business_model'] ?? null,
            'industry_tags' => $data['industry_tags'] ?? null,
            'hq_country' => $data['hq_country'] ?? null,
            'operating_countries' => $data['operating_countries'] ?? null,
            'parent_company_assessment' => $data['parent_company_assessment'] ?? 'not_assessed',
            'parent_customer_id' => $data['parent_customer_id'] ?? null,
            'external_parent_name' => $data['external_parent_name'] ?? null,
            'parent_company_country' => $data['parent_company_country'] ?? null,
            'ownership_pct' => $data['ownership_pct'] ?? null,
            'opportunity_assessment' => $data['opportunity_assessment'] ?? 'not_assessed',
            'account_owner_user_id' => $data['account_owner_user_id'] ?? null,
            'sales_team' => $data['sales_team'] ?? null,
            'region' => $data['region'] ?? null,
            'business_unit' => $data['business_unit'] ?? null,
            'preferred_contract_type' => $data['preferred_contract_type'] ?? null,
            'engagement_model' => $data['engagement_model'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'contract_duration' => $data['contract_duration'] ?? null,
            'key_considerations' => $data['key_considerations'] ?? null,
            'review_acknowledged_at' => $request->boolean('review_acknowledged') ? now() : null,
            'review_acknowledged_by' => $request->boolean('review_acknowledged') ? $userId : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        collect($data['contacts'] ?? [])
            ->filter(fn (array $row): bool => filled($row['full_name'] ?? null))
            ->values()
            ->each(function (array $row, int $index) use ($company, $customer, $userId): void {
                CustomerContact::query()->create([
                    'company_id' => $company->getKey(),
                    'customer_id' => $customer->getKey(),
                    'full_name' => $row['full_name'],
                    'job_title' => $row['job_title'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                    'sort_order' => $index,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            });

        if ((bool) ($data['create_opportunity'] ?? false)) {
            CustomerOpportunity::query()->create([
                'company_id' => $company->getKey(),
                'customer_id' => $customer->getKey(),
                'name' => $data['opportunity_name'],
                'opportunity_type' => $data['opportunity_type'] ?? null,
                'estimated_value' => $data['opportunity_estimated_value'] ?? null,
                'currency_code' => $data['opportunity_currency_code'] ?? ($data['currency_code'] ?? null),
                'probability_pct' => $data['opportunity_probability_pct'] ?? null,
                'expected_close_date' => $data['opportunity_expected_close_date'] ?? null,
                'status' => 'open',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        $documentTitles = [
            'incorporation' => 'Certificate of Incorporation',
            'tax_registration' => 'Tax Registration',
            'financial_statements' => 'Financial Statements',
            'company_profile' => 'Company Profile',
        ];

        foreach ($documentTitles as $category => $title) {
            $file = $request->file("documents.$category");
            if (! $file) {
                continue;
            }

            $safeName = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(
                "customer-documents/{$company->getKey()}/{$customer->getKey()}",
                $safeName,
                'local'
            );
            $storedPaths[] = $path;

            CustomerDocument::query()->create([
                'company_id' => $company->getKey(),
                'customer_id' => $customer->getKey(),
                'category' => $category,
                'title' => $title,
                'original_name' => $file->getClientOriginalName(),
                'storage_disk' => 'local',
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                'status' => 'recorded',
                'created_by' => $userId,
            ]);
        }
    }

    private function updateCustomerCrmProfile(
        Company $company,
        Customer $customer,
        array $data,
        int $userId
    ): void {
        $profile = CustomerProfile::query()->firstOrNew([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
        ]);

        if (! $profile->exists) {
            $profile->created_by = $userId;
        }

        $profile->fill([
            'preferred_name' => $data['preferred_name'] ?? null,
            'website' => $data['website'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'currency_code' => $data['currency_code'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'year_established' => $data['year_established'] ?? null,
            'employee_count' => $data['employee_count'] ?? null,
            'annual_revenue_amount' => $data['annual_revenue_amount'] ?? null,
            'revenue_currency_code' => $data['revenue_currency_code'] ?? ($data['currency_code'] ?? null),
            'description' => $data['description'] ?? null,
            'relationship_stage' => $data['relationship_stage'] ?? 'lead',
            'primary_industry' => $data['primary_industry'] ?? null,
            'sub_industry' => $data['sub_industry'] ?? null,
            'business_model' => $data['business_model'] ?? null,
            'industry_tags' => $data['industry_tags'] ?? null,
            'hq_country' => $data['hq_country'] ?? null,
            'operating_countries' => $data['operating_countries'] ?? null,
            'parent_company_assessment' => $data['parent_company_assessment'] ?? 'not_assessed',
            'parent_customer_id' => $data['parent_customer_id'] ?? null,
            'external_parent_name' => $data['external_parent_name'] ?? null,
            'parent_company_country' => $data['parent_company_country'] ?? null,
            'ownership_pct' => $data['ownership_pct'] ?? null,
            'opportunity_assessment' => $data['opportunity_assessment'] ?? 'not_assessed',
            'account_owner_user_id' => $data['account_owner_user_id'] ?? null,
            'sales_team' => $data['sales_team'] ?? null,
            'region' => $data['region'] ?? null,
            'business_unit' => $data['business_unit'] ?? null,
            'preferred_contract_type' => $data['preferred_contract_type'] ?? null,
            'engagement_model' => $data['engagement_model'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'contract_duration' => $data['contract_duration'] ?? null,
            'key_considerations' => $data['key_considerations'] ?? null,
            'review_acknowledged_at' => now(),
            'review_acknowledged_by' => $userId,
            'updated_by' => $userId,
        ]);
        $profile->save();
    }

    private function syncCustomerContacts(
        Company $company,
        Customer $customer,
        array $rows,
        int $userId
    ): void {
        foreach (array_values($rows) as $index => $row) {
            $id = filled($row['id'] ?? null) ? (int) $row['id'] : null;
            $remove = (bool) ($row['_remove'] ?? false);

            if ($id !== null) {
                $contact = CustomerContact::query()
                    ->where('company_id', $company->getKey())
                    ->where('customer_id', $customer->getKey())
                    ->whereKey($id)
                    ->firstOrFail();

                if ($remove) {
                    $contact->delete();
                    continue;
                }

                $contact->update([
                    'full_name' => $row['full_name'],
                    'job_title' => $row['job_title'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                    'sort_order' => $index,
                    'updated_by' => $userId,
                ]);
                continue;
            }

            if ($remove || blank($row['full_name'] ?? null)) {
                continue;
            }

            CustomerContact::query()->create([
                'company_id' => $company->getKey(),
                'customer_id' => $customer->getKey(),
                'full_name' => $row['full_name'],
                'job_title' => $row['job_title'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'sort_order' => $index,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function syncCustomerOpportunities(
        Company $company,
        Customer $customer,
        array $rows,
        int $userId
    ): void {
        foreach (array_values($rows) as $row) {
            if (blank($row['name'] ?? null)) {
                continue;
            }

            $values = [
                'name' => $row['name'],
                'opportunity_type' => $row['opportunity_type'] ?? null,
                'estimated_value' => $row['estimated_value'] ?? null,
                'currency_code' => $row['currency_code'] ?? null,
                'probability_pct' => $row['probability_pct'] ?? null,
                'expected_close_date' => $row['expected_close_date'] ?? null,
                'updated_by' => $userId,
            ];

            if (filled($row['id'] ?? null)) {
                CustomerOpportunity::query()
                    ->where('company_id', $company->getKey())
                    ->where('customer_id', $customer->getKey())
                    ->where('status', 'open')
                    ->whereKey((int) $row['id'])
                    ->firstOrFail()
                    ->update($values);
                continue;
            }

            CustomerOpportunity::query()->create($values + [
                'company_id' => $company->getKey(),
                'customer_id' => $customer->getKey(),
                'status' => 'open',
                'created_by' => $userId,
            ]);
        }
    }

    /** @param array<int, string> $storedPaths */
    private function persistCustomerDocuments(
        Request $request,
        Company $company,
        Customer $customer,
        array &$storedPaths
    ): void {
        $documentTitles = [
            'incorporation' => 'Certificate of Incorporation',
            'tax_registration' => 'Tax Registration',
            'financial_statements' => 'Financial Statements',
            'company_profile' => 'Company Profile',
        ];

        foreach ($documentTitles as $category => $title) {
            $file = $request->file("documents.$category");
            if (! $file) {
                continue;
            }

            $safeName = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(
                "customer-documents/{$company->getKey()}/{$customer->getKey()}",
                $safeName,
                'local'
            );
            $storedPaths[] = $path;

            CustomerDocument::query()->create([
                'company_id' => $company->getKey(),
                'customer_id' => $customer->getKey(),
                'category' => $category,
                'title' => $title,
                'original_name' => $file->getClientOriginalName(),
                'storage_disk' => 'local',
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                'status' => 'recorded',
                'created_by' => $request->user()->getKey(),
            ]);
        }
    }

    /**
     * Keep only scalar/list customer intake values in the non-authoritative draft.
     * Files are deliberately excluded and must be uploaded at final submission.
     */
    private function sanitizeDraftPayload(array $payload): array
    {
        $allowed = [
            'name','preferred_name','type','email','phone','website','tax_id','registration_number','currency_code',
            'timezone','year_established','employee_count','annual_revenue_amount','revenue_currency_code','description',
            'relationship_stage','primary_industry','sub_industry','business_model','industry_tags','parent_company_assessment','parent_customer_id',
            'external_parent_name','parent_company_country','ownership_pct','hq_country','city','province','postal_code',
            'address','operating_countries','account_owner_user_id','sales_team','region','business_unit','contacts',
            'portal_user_id','opportunity_assessment','create_opportunity','opportunity_name','opportunity_type','opportunity_estimated_value',
            'opportunity_currency_code','opportunity_probability_pct','opportunity_expected_close_date',
            'preferred_contract_type','engagement_model','payment_terms','contract_duration','notes','key_considerations',
        ];

        return collect($payload)
            ->only($allowed)
            ->map(function (mixed $value): mixed {
                if (is_string($value)) {
                    return Str::limit(trim($value), 5000, '');
                }
                if (is_bool($value) || is_numeric($value) || $value === null) {
                    return $value;
                }
                if (is_array($value)) {
                    return collect($value)->take(30)->map(function (mixed $item): mixed {
                        if (is_string($item)) return Str::limit(trim($item), 500, '');
                        if (is_bool($item) || is_numeric($item) || $item === null) return $item;
                        if (is_array($item)) {
                            return collect($item)->take(12)->map(fn (mixed $nested) => is_string($nested) ? Str::limit(trim($nested), 500, '') : (is_scalar($nested) ? $nested : null))->all();
                        }
                        return null;
                    })->all();
                }
                return null;
            })
            ->all();
    }

    /**
     * Get active company members eligible to own the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function accountOwners(Company $company)
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.email'])
            ->where('users.status', 'active')
            ->whereHas('companyMemberships', fn (Builder $query) => $query
                ->where('company_id', $company->getKey())
                ->where('status', 'active'))
            ->orderBy('name')
            ->limit(250)
            ->get();
    }

    /**
     * Get active customer-role portal users for the company.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function portalUsers(Company $company)
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.email'])
            ->where('users.status', 'active')
            ->whereHas(
                'companyMemberships',
                fn (Builder $query) => $query
                    ->where('company_id', $company->getKey())
                    ->where('status', 'active')
                    ->whereHas(
                        'roles',
                        fn (Builder $roleQuery) => $roleQuery
                            ->where('roles.code', 'customer')
                            ->where('roles.scope', 'company')
                            ->where('roles.is_active', true)
                    )
            )
            ->orderBy('name')
            ->get();
    }
}
