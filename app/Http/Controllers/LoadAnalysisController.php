<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\UpdateLoadAnalysisRequest;
use App\Models\Company;
use App\Models\LoadAnalysis;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoadAnalysisController extends Controller
{
    use AuthorizesProjectResource;

    /**
     * Display the current, authorized project load-analysis registry.
     */
    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $requestedStatus = (string) $request->query('status', 'all');
        $status = match ($requestedStatus) {
            'not_started' => 'no_analysis',
            'in_progress' => 'available',
            default => $requestedStatus,
        };

        if (! in_array($status, [
            'all',
            'no_analysis',
            'available',
            'input_required',
            'equipment_model',
            'bill_reference',
        ], true)) {
            $status = 'all';
        }

        $canViewCompany = $user->hasCompanyPermission($company, 'load_analysis.view');
        $canManageCompany = $user->hasCompanyPermission($company, 'load_analysis.manage');
        $canManageOwn = $user->hasCompanyPermission($company, 'load_analysis.manage_own');
        $companyId = (int) $company->getKey();

        $baseQuery = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number,portal_user_id',
                'loadAnalysis' => function ($analysisQuery) use ($companyId): void {
                    $analysisQuery
                        ->where('company_id', $companyId)
                        ->with([
                            'appliances' => fn ($query) => $query
                                ->where('company_id', $companyId),
                            'electricityBills' => fn ($query) => $query
                                ->where('company_id', $companyId),
                        ]);
                },
                'systemRecommendation' => fn ($query) => $query
                    ->select(['id', 'project_id', 'generated_at', 'updated_at'])
                    ->where('company_id', $companyId),
                'technicalCalculation' => fn ($query) => $query
                    ->select(['id', 'project_id', 'calculated_at', 'updated_at'])
                    ->where('company_id', $companyId),
                'solarProfile' => fn ($query) => $query
                    ->select(['id', 'project_id', 'updated_at'])
                    ->where('company_id', $companyId),
                'roofSiteSurvey' => fn ($query) => $query
                    ->select(['id', 'project_id', 'updated_at'])
                    ->where('company_id', $companyId),
                'roofSurfaces' => fn ($query) => $query
                    ->select(['id', 'project_id', 'updated_at'])
                    ->where('company_id', $companyId),
            ]);

        if (! $canViewCompany) {
            $baseQuery->ownedBy($user);
        }

        $projects = $baseQuery
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $searchQuery) use ($search): void {
                        $searchQuery
                            ->where('project_number', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhereHas(
                                'customer',
                                fn (Builder $customerQuery) => $customerQuery
                                    ->where('name', 'like', "%{$search}%")
                            );
                    });
                }
            )
            ->when(
                $status === 'no_analysis',
                fn (Builder $query) => $query->whereDoesntHave(
                    'loadAnalysis',
                    fn (Builder $analysisQuery) => $analysisQuery
                        ->where('company_id', $companyId)
                )
            )
            ->when(
                $status === 'available',
                fn (Builder $query) => $query->whereHas(
                    'loadAnalysis',
                    fn (Builder $analysisQuery) => $analysisQuery
                        ->where('company_id', $companyId)
                )
            )
            ->when(
                $status === 'input_required',
                fn (Builder $query) => $query->whereHas(
                    'loadAnalysis',
                    fn (Builder $analysisQuery) => $analysisQuery
                        ->where('company_id', $companyId)
                        ->whereDoesntHave(
                            'appliances',
                            fn (Builder $inputQuery) => $inputQuery
                                ->where('company_id', $companyId)
                        )
                        ->whereDoesntHave(
                            'electricityBills',
                            fn (Builder $inputQuery) => $inputQuery
                                ->where('company_id', $companyId)
                        )
                )
            )
            ->when(
                $status === 'equipment_model',
                fn (Builder $query) => $query->whereHas(
                    'loadAnalysis',
                    fn (Builder $analysisQuery) => $analysisQuery
                        ->where('company_id', $companyId)
                        ->whereHas(
                            'appliances',
                            fn (Builder $inputQuery) => $inputQuery
                                ->where('company_id', $companyId)
                        )
                )
            )
            ->when(
                $status === 'bill_reference',
                fn (Builder $query) => $query->whereHas(
                    'loadAnalysis',
                    fn (Builder $analysisQuery) => $analysisQuery
                        ->where('company_id', $companyId)
                        ->whereHas(
                            'electricityBills',
                            fn (Builder $inputQuery) => $inputQuery
                                ->where('company_id', $companyId)
                        )
                )
            )
            ->latest('updated_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $registryRows = $projects->getCollection()->mapWithKeys(
            fn (Project $project): array => [
                $project->getKey() => $this->registryRow(
                    $project,
                    $user->getKey(),
                    $canManageCompany,
                    $canManageOwn,
                    $company
                ),
            ]
        );

        $scopeVisibleProjects = function (Builder $query) use ($canViewCompany, $user): Builder {
            $query->current();

            if (! $canViewCompany) {
                $query->ownedBy($user);
            }

            return $query;
        };

        $statsCompany = Company::query()
            ->whereKey($companyId)
            ->withCount([
                'projects as visible_projects_count' => fn (Builder $query) => $scopeVisibleProjects($query),
                'projects as analyses_count' => function (Builder $query) use ($scopeVisibleProjects, $companyId): void {
                    $scopeVisibleProjects($query)->whereHas(
                        'loadAnalysis',
                        fn (Builder $analysisQuery) => $analysisQuery
                            ->where('company_id', $companyId)
                    );
                },
                'projects as needs_analysis_count' => function (Builder $query) use ($scopeVisibleProjects, $companyId): void {
                    $scopeVisibleProjects($query)->whereDoesntHave(
                        'loadAnalysis',
                        fn (Builder $analysisQuery) => $analysisQuery
                            ->where('company_id', $companyId)
                    );
                },
                'projects as input_required_count' => function (Builder $query) use ($scopeVisibleProjects, $companyId): void {
                    $scopeVisibleProjects($query)->whereHas(
                        'loadAnalysis',
                        fn (Builder $analysisQuery) => $analysisQuery
                            ->where('company_id', $companyId)
                            ->whereDoesntHave(
                                'appliances',
                                fn (Builder $inputQuery) => $inputQuery
                                    ->where('company_id', $companyId)
                            )
                            ->whereDoesntHave(
                                'electricityBills',
                                fn (Builder $inputQuery) => $inputQuery
                                    ->where('company_id', $companyId)
                            )
                    );
                },
                'projects as with_appliances_count' => function (Builder $query) use ($scopeVisibleProjects, $companyId): void {
                    $scopeVisibleProjects($query)->whereHas(
                        'loadAnalysis',
                        fn (Builder $analysisQuery) => $analysisQuery
                            ->where('company_id', $companyId)
                            ->whereHas(
                                'appliances',
                                fn (Builder $inputQuery) => $inputQuery
                                    ->where('company_id', $companyId)
                            )
                    );
                },
                'projects as with_bills_count' => function (Builder $query) use ($scopeVisibleProjects, $companyId): void {
                    $scopeVisibleProjects($query)->whereHas(
                        'loadAnalysis',
                        fn (Builder $analysisQuery) => $analysisQuery
                            ->where('company_id', $companyId)
                            ->whereHas(
                                'electricityBills',
                                fn (Builder $inputQuery) => $inputQuery
                                    ->where('company_id', $companyId)
                            )
                    );
                },
            ])
            ->firstOrFail();

        $stats = [
            'projects' => (int) $statsCompany->visible_projects_count,
            'analyses' => (int) $statsCompany->analyses_count,
            'needs_analysis' => (int) $statsCompany->needs_analysis_count,
            'input_required' => (int) $statsCompany->input_required_count,
            'with_appliances' => (int) $statsCompany->with_appliances_count,
            'with_bills' => (int) $statsCompany->with_bills_count,
        ];

        return view('load_analyses.index', [
            'company' => $company,
            'projects' => $projects,
            'registryRows' => $registryRows,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Build presentation data from the existing authoritative load-analysis chain.
     * No Page 017 calculation formula is introduced here: summaries and stale
     * checks are delegated to the existing domain models.
     *
     * @return array<string, mixed>
     */
    private function registryRow(
        Project $project,
        int $userId,
        bool $canManageCompany,
        bool $canManageOwn,
        Company $company
    ): array {
        $analysis = $project->loadAnalysis;
        $customer = $project->customer;
        $canManage = $canManageCompany || (
            $canManageOwn
            && $customer !== null
            && (int) $customer->portal_user_id === $userId
        );

        if (! $analysis) {
            return [
                'state' => 'no_analysis',
                'state_label' => 'No Load Analysis',
                'state_detail' => 'Load modelling has not been started for this current project.',
                'basis_label' => 'Not recorded',
                'input_label' => 'No load inputs',
                'appliance_count' => 0,
                'bill_count' => 0,
                'critical_count' => 0,
                'annual_energy_kwh' => null,
                'peak_demand_kw' => null,
                'peak_basis' => 'Not available',
                'downstream_state' => 'not_assessed',
                'downstream_label' => 'Not assessed',
                'downstream_detail' => 'No current Load Analysis exists for downstream freshness checks.',
                'updated_at' => null,
                'can_manage' => $canManage,
                'action_label' => $canManage ? 'Start analysis' : 'View project',
                'action_method' => $canManage ? 'post' : 'get',
                'action_url' => $canManage
                    ? route('companies.load-analyses.start', [$company, $project])
                    : route('companies.projects.show', [$company, $project]),
            ];
        }

        $summary = $analysis->calculateSummary();
        $appliances = $analysis->appliances;
        $bills = $analysis->electricityBills;
        $applianceCount = $appliances->count();
        $billCount = $bills->count();
        $hasAppliances = $applianceCount > 0;
        $hasBills = $billCount > 0;
        $hasInputs = $hasAppliances || $hasBills;
        $criticalCount = $appliances->where('is_critical', true)->count();

        $basisLabel = match (true) {
            $hasAppliances && $hasBills => 'Equipment + bill reference',
            $hasAppliances => 'Equipment schedule',
            $hasBills => 'Electricity bill reference',
            default => 'No load inputs recorded',
        };

        $equipmentLabel = sprintf(
            '%d %s',
            $applianceCount,
            Str::plural('equipment group', $applianceCount)
        );
        $billLabel = sprintf(
            '%d %s',
            $billCount,
            Str::plural('bill month', $billCount)
        );

        $inputLabel = match (true) {
            $hasAppliances && $hasBills => $equipmentLabel.' · '.$billLabel,
            $hasAppliances => $equipmentLabel,
            $hasBills => $billLabel,
            default => 'No equipment or bills recorded',
        };

        $peakDemandKw = (float) ($summary['peak_demand_kw'] ?? 0);
        $peakBasis = match (true) {
            $hasAppliances && $peakDemandKw > 0 => 'Modeled 24-hour profile',
            ! $hasAppliances && ($summary['average_bill_peak_kw'] ?? null) !== null => 'Average recorded bill peak',
            default => 'Not available',
        };

        $recommendation = $project->systemRecommendation;
        $calculation = $project->technicalCalculation;
        $recommendationAssessed = $recommendation?->generated_at !== null;
        $calculationAssessed = $calculation?->calculated_at !== null;
        $recommendationStale = $recommendationAssessed
            ? $recommendation->isStale($project)
            : false;
        $calculationStale = $calculationAssessed
            ? $calculation->isStale($project)
            : false;
        $staleDomains = collect([
            $recommendationStale ? 'Recommendation' : null,
            $calculationStale ? 'Technical calculation' : null,
        ])->filter()->values();

        if ($staleDomains->isNotEmpty()) {
            $downstreamState = 'review_required';
            $downstreamLabel = 'Downstream review required';
            $downstreamDetail = $staleDomains->implode(' and ').' predate one or more current source timestamps.';
        } elseif ($recommendationAssessed || $calculationAssessed) {
            $downstreamState = 'no_stale_flag';
            $downstreamLabel = 'No downstream stale flag';
            $downstreamDetail = 'Existing timestamp checks do not currently flag the assessed downstream record.';
        } else {
            $downstreamState = 'not_assessed';
            $downstreamLabel = 'Not assessed';
            $downstreamDetail = 'Recommendation and technical calculation freshness are not yet available.';
        }

        return [
            'state' => $hasInputs ? 'preliminary' : 'input_required',
            'state_label' => $hasInputs ? 'Preliminary' : 'Input Required',
            'state_detail' => $hasInputs
                ? 'Current summary is derived from the existing simplified load model and recorded references.'
                : 'The Load Analysis exists, but no equipment or electricity bill inputs are recorded.',
            'basis_label' => $basisLabel,
            'input_label' => $inputLabel,
            'appliance_count' => $applianceCount,
            'bill_count' => $billCount,
            'critical_count' => $criticalCount,
            'annual_energy_kwh' => $hasInputs ? (float) ($summary['annual_energy_kwh'] ?? 0) : null,
            'peak_demand_kw' => $peakDemandKw > 0 ? $peakDemandKw : null,
            'peak_basis' => $peakBasis,
            'downstream_state' => $downstreamState,
            'downstream_label' => $downstreamLabel,
            'downstream_detail' => $downstreamDetail,
            'updated_at' => $analysis->updated_at,
            'can_manage' => $canManage,
            'action_label' => 'Open analysis',
            'action_method' => 'get',
            'action_url' => route('companies.load-analyses.show', [$company, $project]),
        ];
    }

    /**
     * Display the current project Load Engineering Workspace.
     *
     * GET is deliberately read-only. A missing Load Analysis is represented by
     * an unsaved placeholder so the user can review the project context before
     * explicitly starting the analysis.
     */
    public function show(
        Request $request,
        Company $company,
        Project $project
    ): View {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.view', 'load_analysis.manage'],
            ['load_analysis.view_own', 'load_analysis.manage_own']
        );

        $canManage = $this->canManageProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.manage'],
            ['load_analysis.manage_own']
        ) && ! $project->isArchived();

        $project->loadMissing([
            'customer:id,name,customer_number,portal_user_id',
            'systemRecommendation:id,company_id,project_id,generated_at,updated_at',
            'technicalCalculation:id,company_id,project_id,calculated_at,updated_at',
            'solarProfile:id,company_id,project_id,updated_at',
            'roofSiteSurvey:id,company_id,project_id,updated_at',
            'roofSurfaces:id,company_id,project_id,updated_at',
        ]);

        $analysis = $this->persistedAnalysis($company, $project);
        $analysisExists = $analysis instanceof LoadAnalysis;

        if ($analysisExists) {
            $analysis->load([
                'electricityBills',
                'appliances',
                'creator:id,name',
                'updater:id,name',
            ]);
            $project->setRelation('loadAnalysis', $analysis);
        } else {
            $analysis = new LoadAnalysis([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                ...$this->defaultAnalysisValues(),
            ]);
            $analysis->setRelation('electricityBills', collect());
            $analysis->setRelation('appliances', collect());
            $project->setRelation('loadAnalysis', null);
        }

        $summary = $analysis->calculateSummary();
        $hourlyProfile = $analysis->calculateHourlyProfile(
            $analysis->appliances->all()
        );
        $criticalHourlyProfile = $analysis->calculateHourlyProfile(
            $analysis->appliances->where('is_critical', true)->values()->all()
        );

        $workspace = $this->workspaceProjection(
            $request,
            $company,
            $project,
            $analysis,
            $summary,
            $hourlyProfile,
            $criticalHourlyProfile,
            $analysisExists
        );

        return view('load_analyses.show', [
            'company' => $company,
            'project' => $project,
            'analysis' => $analysis,
            'analysisExists' => $analysisExists,
            'summary' => $summary,
            'hourlyProfile' => $hourlyProfile,
            'criticalHourlyProfile' => $criticalHourlyProfile,
            'canManage' => $canManage,
            'workspace' => $workspace,
        ]);
    }

    /**
     * Explicitly create the project's current Load Analysis with neutral defaults.
     */
    public function start(
        Request $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.manage'],
            ['load_analysis.manage_own']
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot start a Load Analysis.');
        }

        $analysis = $this->persistedAnalysis($company, $project);

        if (! $analysis) {
            LoadAnalysis::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                ...$this->defaultAnalysisValues(),
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]);
        }

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', $analysis
                ? 'Load Analysis is already available for this project.'
                : 'Load Analysis started. Add equipment, bill history, or update the analysis settings.');
    }

    /**
     * Update Load Analysis settings. The PUT request is an explicit mutation and
     * may create the current analysis if it does not yet exist.
     */
    public function update(
        UpdateLoadAnalysisRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.manage'],
            ['load_analysis.manage_own']
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $analysis = $this->persistedAnalysis($company, $project);

        if (! $analysis) {
            $analysis = LoadAnalysis::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                ...$this->defaultAnalysisValues(),
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]);
        }

        $analysis->update([
            ...$request->validated(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Load Analysis settings updated successfully.');
    }

    /**
     * Resolve the exact persisted one-to-one analysis for the routed company/project.
     */
    private function persistedAnalysis(Company $company, Project $project): ?LoadAnalysis
    {
        return LoadAnalysis::query()
            ->where('company_id', $company->getKey())
            ->where('project_id', $project->getKey())
            ->first();
    }

    /**
     * Neutral persisted defaults. These are application initialization values,
     * not engineering evidence and must be shown as assumptions in the UI.
     *
     * @return array<string, int|string>
     */
    private function defaultAnalysisValues(): array
    {
        return [
            'source_mode' => 'combined',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
        ];
    }

    /**
     * Build Page 018 presentation data only from already loaded authoritative
     * records and the existing LoadAnalysis deterministic summary/profile.
     * No new engineering calculation authority is introduced here.
     *
     * @param  array<string, float|int|null>  $summary
     * @param  array<int, float>  $hourlyProfile
     * @param  array<int, float>  $criticalHourlyProfile
     * @return array<string, mixed>
     */
    private function workspaceProjection(
        Request $request,
        Company $company,
        Project $project,
        LoadAnalysis $analysis,
        array $summary,
        array $hourlyProfile,
        array $criticalHourlyProfile,
        bool $analysisExists
    ): array {
        $appliances = $analysis->appliances;
        $bills = $analysis->electricityBills;
        $hasEquipment = $appliances->isNotEmpty();
        $hasBills = $bills->isNotEmpty();
        $modeledPeak = $hasEquipment ? (float) ($summary['profile_peak_demand_kw'] ?? 0) : null;
        $billPeak = $hasBills && $summary['average_bill_peak_kw'] !== null
            ? (float) $summary['average_bill_peak_kw']
            : null;
        $variance = $hasEquipment && $hasBills && $summary['bill_variance_percent'] !== null
            ? (float) $summary['bill_variance_percent']
            : null;

        $basisLabel = match (true) {
            ! $analysisExists => 'Not recorded',
            $hasEquipment && $hasBills => 'Equipment schedule + bill reference',
            $hasEquipment => 'Equipment schedule recorded',
            $hasBills => 'Electricity bill reference recorded',
            default => 'No load inputs recorded',
        };
        $selectedBasisMissing = match ($analysis->source_mode) {
            'equipment' => ! $hasEquipment,
            'bill' => ! $hasBills,
            default => ! $hasEquipment || ! $hasBills,
        };
        $state = match (true) {
            ! $analysisExists => ['key' => 'no-analysis', 'label' => 'No Load Analysis', 'tone' => 'neutral'],
            $selectedBasisMissing => ['key' => 'input-required', 'label' => 'Input Required', 'tone' => 'attention'],
            default => ['key' => 'preliminary', 'label' => 'Preliminary', 'tone' => 'info'],
        };

        $sourceMode = match ($analysis->source_mode) {
            'equipment' => [
                'label' => 'Equipment schedule',
                'detail' => 'Daily and annualized energy use the repeated 24-hour equipment model.',
            ],
            'bill' => [
                'label' => 'Electricity bill history',
                'detail' => 'Daily and annualized energy use recorded monthly bill energy. No 24-hour bill profile is inferred.',
            ],
            default => [
                'label' => 'Equipment + bill reference',
                'detail' => 'The equipment schedule remains the modeled profile; bills are shown separately for reconciliation.',
            ],
        };

        $categories = $appliances
            ->groupBy(fn ($appliance) => trim((string) ($appliance->category ?: 'Uncategorized')) ?: 'Uncategorized')
            ->map(function ($items, string $category): array {
                return [
                    'category' => $category,
                    'count' => $items->count(),
                    'connected_kw' => round((float) $items->sum(fn ($item) => $item->connected_load_kw), 3),
                    'daily_kwh' => round((float) $items->sum(fn ($item) => $item->daily_energy_kwh), 3),
                ];
            })
            ->sortByDesc('daily_kwh')
            ->values();

        $billHistory = $bills->sortBy('billing_month')->values();
        $recordedRate = $summary['effective_rate_per_kwh'] === null
            ? null
            : (float) $summary['effective_rate_per_kwh'];

        $recommendation = $project->systemRecommendation;
        $technical = $project->technicalCalculation;
        $recommendationAssessed = $recommendation?->generated_at !== null;
        $technicalAssessed = $technical?->calculated_at !== null;
        $recommendationStale = $recommendationAssessed ? $recommendation->isStale($project) : false;
        $technicalStale = $technicalAssessed ? $technical->isStale($project) : false;
        $staleLabels = collect([
            $recommendationStale ? 'Recommendation' : null,
            $technicalStale ? 'Technical calculation' : null,
        ])->filter()->values();

        $downstream = match (true) {
            $staleLabels->isNotEmpty() => [
                'key' => 'review-required',
                'label' => 'Downstream review required',
                'detail' => $staleLabels->implode(' and ').' predate one or more current source timestamps.',
            ],
            $recommendationAssessed || $technicalAssessed => [
                'key' => 'no-stale-flag',
                'label' => 'No downstream stale flag',
                'detail' => 'Existing timestamp checks do not currently flag the assessed downstream record.',
            ],
            default => [
                'key' => 'not-assessed',
                'label' => 'Not Assessed',
                'detail' => 'Recommendation and Technical Calculation freshness are not yet available.',
            ],
        };

        $missing = collect();
        if (! $analysisExists) {
            $missing->push('Start the Load Analysis explicitly before recording child inputs.');
        } else {
            if (in_array($analysis->source_mode, ['equipment', 'combined'], true) && ! $hasEquipment) {
                $missing->push('No equipment groups are recorded for the selected source basis.');
            }
            if (in_array($analysis->source_mode, ['bill', 'combined'], true) && ! $hasBills) {
                $missing->push('No electricity bill history is recorded for the selected source basis.');
            }
            if ($analysis->connected_power_va === null) {
                $missing->push('Connected service power is not recorded.');
            }
        }

        $canViewSolarCompany = $request->user()->hasAnyCompanyPermission($company, [
            'location_solar.view', 'location_solar.manage',
        ]);
        $canViewSolarOwn = $request->user()->hasAnyCompanyPermission($company, [
            'location_solar.view_own', 'location_solar.manage_own',
        ]) && $project->isOwnedBy($request->user());

        $nextAction = match (true) {
            ! $analysisExists => [
                'label' => 'Start Load Analysis',
                'type' => 'start',
                'tab' => null,
            ],
            in_array($analysis->source_mode, ['equipment', 'combined'], true) && ! $hasEquipment => [
                'label' => 'Add equipment',
                'type' => 'equipment',
                'tab' => 'equipment',
            ],
            in_array($analysis->source_mode, ['bill', 'combined'], true) && ! $hasBills => [
                'label' => 'Add bill history',
                'type' => 'bill',
                'tab' => 'bills',
            ],
            $variance !== null && abs($variance) > 10 => [
                'label' => 'Review bill mismatch',
                'type' => 'bill',
                'tab' => 'bills',
            ],
            $canViewSolarCompany || $canViewSolarOwn => [
                'label' => 'Continue to Location & Solar',
                'type' => 'solar',
                'url' => route('companies.location-solar.show', [$company, $project]),
                'tab' => null,
            ],
            default => [
                'label' => 'Return to Project Cockpit',
                'type' => 'project',
                'url' => route('companies.projects.show', [$company, $project]),
                'tab' => null,
            ],
        };

        $criticalDaily = (float) ($summary['critical_daily_energy_kwh'] ?? 0);
        $equipmentDaily = (float) ($summary['equipment_daily_energy_kwh'] ?? 0);

        return [
            'state' => $state,
            'basis_label' => $basisLabel,
            'source_mode' => $sourceMode,
            'has_equipment' => $hasEquipment,
            'has_bills' => $hasBills,
            'modeled_peak_kw' => $modeledPeak,
            'recorded_bill_peak_kw' => $billPeak,
            'reconciliation_variance_percent' => $variance,
            'critical_share_percent' => $equipmentDaily > 0
                ? round(($criticalDaily / $equipmentDaily) * 100, 2)
                : null,
            'effective_bill_rate' => $recordedRate === null ? null : round($recordedRate, 2),
            'categories' => $categories->all(),
            'downstream' => $downstream,
            'missing_inputs' => $missing->all(),
            'next_action' => $nextAction,
            'profile_chart' => [
                'hours' => array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23)),
                'total' => array_values($hourlyProfile),
                'critical' => array_values($criticalHourlyProfile),
                'daytime_start' => (int) $analysis->daytime_start_hour,
                'daytime_end' => (int) $analysis->daytime_end_hour,
            ],
            'bill_chart' => [
                'labels' => $billHistory->map(fn ($bill) => $bill->billing_month->format('M Y'))->all(),
                'energy' => $billHistory->map(fn ($bill) => round((float) $bill->energy_kwh, 3))->all(),
                'peak' => $billHistory->map(fn ($bill) => $bill->peak_demand_kw === null ? null : round((float) $bill->peak_demand_kw, 3))->all(),
            ],
        ];
    }
}
