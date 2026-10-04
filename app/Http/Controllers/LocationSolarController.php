<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\UpdateLocationSolarRequest;
use App\Models\Company;
use App\Models\LocationSolarHistory;
use App\Models\MonthlySolarDatum;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use App\Models\User;
use App\Services\Solar\SolarDatasetAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationSolarController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(private readonly SolarDatasetAssessment $solarAssessment)
    {
    }

    private const INDEX_STATUSES = [
        'all',
        'no_dataset',
        'input_required',
        'complete',
        'reviewed',
    ];

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $requestedStatus = (string) $request->query('status', 'all');
        $status = match ($requestedStatus) {
            'not_started' => 'no_dataset',
            'incomplete' => 'input_required',
            'ready' => 'complete',
            'verified' => 'reviewed',
            default => $requestedStatus,
        };

        if (! in_array($status, self::INDEX_STATUSES, true)) {
            $status = 'all';
        }

        $baseQuery = $this->scopedCurrentProjects($company, $user);
        $filteredQuery = $this->applyIndexFilters(clone $baseQuery, $search, $status);

        $projects = (clone $filteredQuery)
            ->with([
                'customer:id,name,customer_number,portal_user_id',
                'solarProfile' => function ($profile): void {
                    $profile
                        ->select([
                            'id',
                            'company_id',
                            'project_id',
                            'quality_status',
                            'data_source',
                            'source_name',
                            'source_reference',
                            'source_date',
                            'updated_at',
                            'verified_at',
                            'verified_by',
                        ])
                        ->withCount([
                            'monthlyData as complete_months_count' => fn ($months) => $months
                                ->whereNotNull('ghi_daily_kwh_m2')
                                ->whereNotNull('peak_sun_hours')
                                ->whereNotNull('ambient_temperature_c'),
                            'monthlyData as poa_months_count' => fn ($months) => $months
                                ->whereNotNull('poa_daily_kwh_m2'),
                        ])
                        ->with('verifier:id,name');
                },
            ])
            ->latest('projects.updated_at')
            ->paginate(12)
            ->withQueryString();

        $canManageAll = $user->hasCompanyPermission($company, 'location_solar.manage');
        $canManageOwn = $user->hasCompanyPermission($company, 'location_solar.manage_own');

        $registryRows = $projects->getCollection()
            ->mapWithKeys(fn (Project $project): array => [
                (int) $project->getKey() => $this->registryProjection(
                    $project,
                    $user,
                    $canManageAll,
                    $canManageOwn
                ),
            ]);

        $statsRequiredSql = $this->solarAssessment->requiredDatasetSql('solar_stats');
        $statsReviewedSql = $this->solarAssessment->reviewedDatasetSql('solar_stats');
        $statsCompleteMonthsSql = $this->solarAssessment->completeMonthsSql('solar_stats');

        $statsRow = (clone $baseQuery)
            ->leftJoin('project_solar_profiles as solar_stats', 'solar_stats.project_id', '=', 'projects.id')
            ->selectRaw('COUNT(projects.id) AS projects')
            ->selectRaw('COALESCE(SUM(CASE WHEN solar_stats.id IS NULL THEN 1 ELSE 0 END), 0) AS no_dataset')
            ->selectRaw("COALESCE(SUM(CASE WHEN solar_stats.id IS NOT NULL AND NOT ({$statsRequiredSql}) THEN 1 ELSE 0 END), 0) AS input_required")
            ->selectRaw("COALESCE(SUM(CASE WHEN solar_stats.id IS NOT NULL AND ({$statsRequiredSql}) AND NOT ({$statsReviewedSql}) THEN 1 ELSE 0 END), 0) AS complete")
            ->selectRaw("COALESCE(SUM(CASE WHEN solar_stats.id IS NOT NULL AND ({$statsReviewedSql}) THEN 1 ELSE 0 END), 0) AS reviewed")
            ->selectRaw('COALESCE(SUM(CASE WHEN projects.latitude IS NOT NULL AND projects.longitude IS NOT NULL THEN 1 ELSE 0 END), 0) AS coordinates')
            ->selectRaw('COALESCE(SUM(CASE WHEN projects.latitude IS NULL OR projects.longitude IS NULL THEN 1 ELSE 0 END), 0) AS missing_coordinates')
            ->selectRaw("COALESCE(SUM(CASE WHEN solar_stats.id IS NOT NULL AND (solar_stats.source_name IS NULL OR TRIM(solar_stats.source_name) = '') THEN 1 ELSE 0 END), 0) AS missing_source_name")
            ->selectRaw("COALESCE(SUM(CASE WHEN solar_stats.id IS NOT NULL AND ({$statsCompleteMonthsSql}) < 12 THEN 1 ELSE 0 END), 0) AS incomplete_monthly")
            ->first();

        $stats = [
            'projects' => (int) ($statsRow?->projects ?? 0),
            'no_dataset' => (int) ($statsRow?->no_dataset ?? 0),
            'input_required' => (int) ($statsRow?->input_required ?? 0),
            'complete' => (int) ($statsRow?->complete ?? 0),
            'reviewed' => (int) ($statsRow?->reviewed ?? 0),
            'coordinates' => (int) ($statsRow?->coordinates ?? 0),
            'missing_coordinates' => (int) ($statsRow?->missing_coordinates ?? 0),
            'missing_source_name' => (int) ($statsRow?->missing_source_name ?? 0),
            'incomplete_monthly' => (int) ($statsRow?->incomplete_monthly ?? 0),
        ];
        $stats['started'] = max(0, $stats['projects'] - $stats['no_dataset']);
        $stats['coordinate_coverage_percent'] = $stats['projects'] > 0
            ? (int) round(($stats['coordinates'] / $stats['projects']) * 100)
            : 0;

        $mappedTotal = (clone $filteredQuery)
            ->whereNotNull('projects.latitude')
            ->whereNotNull('projects.longitude')
            ->count();

        $mapProjects = (clone $filteredQuery)
            ->whereNotNull('projects.latitude')
            ->whereNotNull('projects.longitude')
            ->with(['solarProfile' => function ($profile): void {
                $profile
                    ->select('id', 'project_id', 'quality_status', 'verified_at', 'data_source', 'source_name')
                    ->withCount([
                        'monthlyData as complete_months_count' => fn ($months) => $months
                            ->whereNotNull('ghi_daily_kwh_m2')
                            ->whereNotNull('peak_sun_hours')
                            ->whereNotNull('ambient_temperature_c'),
                    ]);
            }])
            ->latest('projects.updated_at')
            ->get([
                'projects.id',
                'projects.project_number',
                'projects.name',
                'projects.city',
                'projects.province',
                'projects.latitude',
                'projects.longitude',
                'projects.updated_at',
            ]);

        $mapPoints = $mapProjects
            ->map(fn (Project $project): array => $this->mapProjection($project))
            ->values();

        $nextActionProject = (clone $baseQuery)
            ->leftJoin('project_solar_profiles as solar_next', 'solar_next.project_id', '=', 'projects.id')
            ->select('projects.*')
            ->with([
                'customer:id,name,customer_number,portal_user_id',
                'solarProfile' => function ($profile): void {
                    $profile
                        ->select('id', 'project_id', 'quality_status', 'verified_at', 'source_name', 'updated_at')
                        ->withCount([
                            'monthlyData as complete_months_count' => fn ($months) => $months
                                ->whereNotNull('ghi_daily_kwh_m2')
                                ->whereNotNull('peak_sun_hours')
                                ->whereNotNull('ambient_temperature_c'),
                        ]);
                },
            ])
            ->orderByRaw("CASE
                WHEN solar_next.id IS NULL THEN 0
                WHEN NOT (".$this->solarAssessment->requiredDatasetSql('solar_next').") THEN 1
                WHEN ".$this->solarAssessment->reviewedDatasetSql('solar_next')." THEN 3
                ELSE 2
            END")
            ->orderByRaw('CASE WHEN solar_next.updated_at IS NULL THEN projects.updated_at ELSE solar_next.updated_at END ASC')
            ->first();

        $nextAction = $nextActionProject
            ? $this->nextActionProjection(
                $nextActionProject,
                $user,
                $canManageAll,
                $canManageOwn,
                $company
            )
            : null;

        return view('location_solar.index', [
            'company' => $company,
            'projects' => $projects,
            'registryRows' => $registryRows,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'mapPoints' => $mapPoints,
            'mappedTotal' => $mappedTotal,
            'nextAction' => $nextAction,
        ]);
    }

    public function show(Request $request, Company $company, Project $project): View
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['location_solar.view', 'location_solar.manage'],
            ['location_solar.view_own', 'location_solar.manage_own']
        );

        $persistedProfile = $project->solarProfile()
            ->where('company_id', $company->getKey())
            ->first();

        $canManage = $this->canManageProjectResource(
            $request,
            $company,
            $project,
            ['location_solar.manage'],
            ['location_solar.manage_own']
        ) && ! $project->isArchived();

        $canReview = $request->user()->hasCompanyPermission($company, 'location_solar.verify')
            && ! $project->isArchived();

        $profile = $persistedProfile ?? new ProjectSolarProfile([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'data_source' => 'manual',
            'timezone' => null,
            'quality_status' => 'incomplete',
        ]);

        $historyRows = collect();
        $historyTotal = 0;

        if ($profile->exists) {
            $profile->load([
                'monthlyData',
                'verifier:id,name',
                'creator:id,name',
                'updater:id,name',
            ]);

            $historyTotal = $profile->histories()->count();
            $historyRows = $profile->histories()
                ->with('changedBy:id,name')
                ->limit(24)
                ->get();
        } else {
            $profile->setRelation('monthlyData', collect());
        }

        $monthlyByMonth = $profile->monthlyData->keyBy('month');
        $monthlyRows = collect(range(1, 12))->map(function (int $month) use ($monthlyByMonth, $profile): MonthlySolarDatum {
            return $monthlyByMonth->get($month) ?? new MonthlySolarDatum([
                'company_id' => $profile->company_id,
                'project_solar_profile_id' => $profile->getKey(),
                'month' => $month,
            ]);
        });

        $profile->setRelation('monthlyData', $monthlyRows);
        $assessment = $this->solarAssessment->assess($project, $profile, $monthlyRows);
        $summary = $assessment;
        $project->loadMissing('customer:id,name,customer_number');

        return view('location_solar.show', compact(
            'company',
            'project',
            'profile',
            'monthlyRows',
            'historyRows',
            'historyTotal',
            'assessment',
            'summary',
            'canManage',
            'canReview'
        ));
    }

    public function start(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['location_solar.manage'],
            ['location_solar.manage_own']
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot start a solar dataset.');
        }

        $profile = DB::transaction(function () use ($request, $company, $project): ProjectSolarProfile {
            $profile = $this->createDefaultProfile($request, $company, $project);

            if ($profile->wasRecentlyCreated) {
                $this->recordHistory(
                    $request,
                    $company,
                    $project,
                    $profile,
                    'started',
                    null,
                    $profile->data_source,
                    null,
                    $profile->quality_status,
                    'The project solar dataset was explicitly started.'
                );
            }

            return $profile;
        });

        return redirect()
            ->route('companies.location-solar.show', [$company, $project])
            ->with(
                'success',
                $profile->wasRecentlyCreated
                    ? 'Solar dataset started. Record the project location, source basis, and monthly resource values.'
                    : 'Solar dataset already exists. The current record has been opened.'
            );
    }

    public function update(
        UpdateLocationSolarRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['location_solar.manage'],
            ['location_solar.manage_own']
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $profile = $project->solarProfile()
            ->where('company_id', $company->getKey())
            ->first();

        if (! $profile) {
            return redirect()
                ->route('companies.location-solar.show', [$company, $project])
                ->with('error', 'Start the solar dataset before saving location or resource values.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $company, $project, $profile, $validated): void {
            $previousSource = $profile->data_source;
            $previousStatus = $profile->quality_status;

            $project->update([
                'site_address' => $validated['site_address'],
                'city' => $validated['city'] ?? null,
                'province' => $validated['province'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'updated_by' => $request->user()->getKey(),
            ]);

            $profile->update([
                'data_source' => $validated['data_source'],
                'source_name' => $validated['source_name'] ?? null,
                'source_reference' => $validated['source_reference'] ?? null,
                'source_date' => $validated['source_date'] ?? null,
                'elevation_m' => $validated['elevation_m'] ?? null,
                'timezone' => $validated['timezone'],
                'notes' => $validated['notes'] ?? null,
                'verified_by' => null,
                'verified_at' => null,
                'updated_by' => $request->user()->getKey(),
            ]);

            foreach (range(1, 12) as $month) {
                $monthData = $validated['monthly'][$month] ?? [];

                MonthlySolarDatum::query()->updateOrCreate(
                    [
                        'project_solar_profile_id' => $profile->getKey(),
                        'month' => $month,
                    ],
                    [
                        'company_id' => $company->getKey(),
                        'ghi_daily_kwh_m2' => $monthData['ghi_daily_kwh_m2'] ?? null,
                        'poa_daily_kwh_m2' => $monthData['poa_daily_kwh_m2'] ?? null,
                        'peak_sun_hours' => $monthData['peak_sun_hours'] ?? null,
                        'ambient_temperature_c' => $monthData['ambient_temperature_c'] ?? null,
                    ]
                );
            }

            $profile->load('monthlyData');
            $newStatus = $profile->derivedQualityStatus($project->fresh());
            $profile->update(['quality_status' => $newStatus]);

            $this->recordHistory(
                $request,
                $company,
                $project,
                $profile,
                'updated',
                $previousSource,
                $profile->data_source,
                $previousStatus,
                $newStatus,
                'Location, source metadata, and monthly solar parameters were updated.'
            );
        });

        return redirect()
            ->route('companies.location-solar.show', [$company, $project])
            ->with('success', 'Location and solar data updated successfully.');
    }

    public function verify(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['location_solar.view', 'location_solar.manage'],
            ['location_solar.view_own', 'location_solar.manage_own']
        );

        if (! $request->user()->hasCompanyPermission($company, 'location_solar.verify')) {
            abort(403);
        }

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot review a solar dataset.');
        }

        $profile = $project->solarProfile()
            ->where('company_id', $company->getKey())
            ->with('monthlyData')
            ->firstOrFail();
        $assessment = $this->solarAssessment->assess($project, $profile);

        if (! $assessment['is_dataset_complete']) {
            throw ValidationException::withMessages([
                'review' => 'Complete the recorded coordinate pair, source name, and all 12 required monthly GHI, Peak Sun Hours, and ambient-temperature values before review.',
            ]);
        }

        $previousStatus = $profile->quality_status;

        // A review records workflow evidence only. Preserve the resource-data
        // updated_at timestamp so the existing timestamp-based downstream
        // freshness checks are not invalidated by review metadata alone.
        $profile->timestamps = false;
        try {
            $profile->update([
                'quality_status' => 'verified',
                'verified_by' => $request->user()->getKey(),
                'verified_at' => now(),
                'updated_by' => $request->user()->getKey(),
            ]);
        } finally {
            $profile->timestamps = true;
        }

        $this->recordHistory(
            $request,
            $company,
            $project,
            $profile,
            'reviewed',
            $profile->data_source,
            $profile->data_source,
            $previousStatus,
            'verified',
            'An authorized user reviewed the completeness of the recorded project location, source basis, and required monthly solar-resource values.'
        );

        return redirect()
            ->route('companies.location-solar.show', [$company, $project])
            ->with('success', 'Recorded location and solar dataset review completed.');
    }

    private function scopedCurrentProjects(Company $company, User $user): Builder
    {
        $query = Project::query()
            ->where('projects.company_id', $company->getKey())
            ->current();

        if (! $user->hasCompanyPermission($company, 'location_solar.view')) {
            $query->ownedBy($user);
        }

        return $query;
    }

    private function applyIndexFilters(Builder $query, string $search, string $status): Builder
    {
        return $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('projects.name', 'like', "%{$search}%")
                        ->orWhere('projects.project_number', 'like', "%{$search}%")
                        ->orWhere('projects.city', 'like', "%{$search}%")
                        ->orWhere('projects.province', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('solarProfile', fn (Builder $profileQuery) => $profileQuery
                            ->where('source_name', 'like', "%{$search}%")
                            ->orWhere('source_reference', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'no_dataset', fn (Builder $builder) => $builder->whereDoesntHave('solarProfile'))
            ->when($status === 'input_required', fn (Builder $builder) => $builder
                ->whereHas('solarProfile', fn (Builder $profile) => $profile
                    ->whereRaw('NOT ('.$this->solarAssessment->requiredDatasetSql('project_solar_profiles').')')))
            ->when($status === 'complete', fn (Builder $builder) => $builder
                ->whereHas('solarProfile', fn (Builder $profile) => $profile
                    ->whereRaw('('.$this->solarAssessment->requiredDatasetSql('project_solar_profiles').')')
                    ->whereRaw('NOT ('.$this->solarAssessment->reviewedDatasetSql('project_solar_profiles').')')))
            ->when($status === 'reviewed', fn (Builder $builder) => $builder
                ->whereHas('solarProfile', fn (Builder $profile) => $profile
                    ->whereRaw('('.$this->solarAssessment->reviewedDatasetSql('project_solar_profiles').')')));
    }

    private function registryProjection(
        Project $project,
        User $user,
        bool $canManageAll,
        bool $canManageOwn
    ): array {
        $profile = $project->solarProfile;
        $completeMonths = (int) ($profile?->getAttribute('complete_months_count') ?? 0);
        $state = $this->datasetState($project, $profile, $completeMonths);
        $hasCoordinates = $project->latitude !== null && $project->longitude !== null;
        $hasPartialCoordinates = ($project->latitude !== null) xor ($project->longitude !== null);
        $isOwned = (int) ($project->customer?->portal_user_id ?? 0) === (int) $user->getKey();
        $canManage = ! $project->isArchived()
            && ($canManageAll || ($canManageOwn && $isOwned));
        $poaMonths = (int) ($profile?->getAttribute('poa_months_count') ?? 0);

        return [
            'state' => $state,
            'state_label' => $this->datasetStateLabel($state),
            'profile_exists' => (bool) $profile,
            'can_manage' => $canManage,
            'coordinates_state' => $hasCoordinates
                ? 'recorded'
                : ($hasPartialCoordinates ? 'partial' : 'missing'),
            'coordinates_label' => $hasCoordinates
                ? 'Recorded'
                : ($hasPartialCoordinates ? 'Incomplete pair' : 'Not Recorded'),
            'coordinate_text' => $hasCoordinates
                ? number_format((float) $project->latitude, 5).', '.number_format((float) $project->longitude, 5)
                : null,
            'source_type_label' => $profile
                ? $this->sourceTypeLabel((string) $profile->data_source)
                : 'Not Available',
            'source_name' => filled($profile?->source_name) ? (string) $profile->source_name : 'Not Recorded',
            'source_reference' => filled($profile?->source_reference) ? (string) $profile->source_reference : null,
            'source_date' => $profile?->source_date?->format('d M Y'),
            'complete_months' => $completeMonths,
            'poa_months' => $poaMonths,
            'monthly_label' => $profile ? $completeMonths.'/12 required months' : 'Not Available',
            'updated_label' => $profile?->updated_at?->diffForHumans() ?? 'Not Recorded',
            'updated_at' => $profile?->updated_at?->format('d M Y · H:i'),
            'reviewed_by' => $state === 'reviewed'
                ? ($profile?->verifier?->name ?: 'Reviewer not recorded')
                : null,
            'reviewed_at' => $state === 'reviewed'
                ? $profile?->verified_at?->format('d M Y · H:i')
                : null,
        ];
    }

    private function mapProjection(Project $project): array
    {
        $profile = $project->solarProfile;
        $completeMonths = (int) ($profile?->getAttribute('complete_months_count') ?? 0);
        $state = $this->datasetState($project, $profile, $completeMonths);

        return [
            'id' => (int) $project->getKey(),
            'project_number' => (string) ($project->project_number ?: 'Project reference not recorded'),
            'name' => (string) $project->name,
            'city' => filled($project->city) ? (string) $project->city : null,
            'province' => filled($project->province) ? (string) $project->province : null,
            'latitude' => (float) $project->latitude,
            'longitude' => (float) $project->longitude,
            'state' => $state,
            'state_label' => $this->datasetStateLabel($state),
            'source_type_label' => $profile
                ? $this->sourceTypeLabel((string) $profile->data_source)
                : 'No Dataset',
            'source_name' => filled($profile?->source_name) ? (string) $profile->source_name : 'Not Recorded',
        ];
    }

    private function nextActionProjection(
        Project $project,
        User $user,
        bool $canManageAll,
        bool $canManageOwn,
        Company $company
    ): array {
        $profile = $project->solarProfile;
        $completeMonths = (int) ($profile?->getAttribute('complete_months_count') ?? 0);
        $state = $this->datasetState($project, $profile, $completeMonths);
        $isOwned = (int) ($project->customer?->portal_user_id ?? 0) === (int) $user->getKey();
        $canManage = ! $project->isArchived()
            && ($canManageAll || ($canManageOwn && $isOwned));

        if ($state === 'no_dataset' && $canManage) {
            return [
                'project_name' => $project->name,
                'project_number' => $project->project_number,
                'state_label' => 'No Dataset',
                'label' => 'Start solar dataset',
                'copy' => 'No persisted solar dataset exists for this accessible project.',
                'method' => 'post',
                'url' => route('companies.location-solar.start', [$company, $project]),
            ];
        }

        if ($profile) {
            return [
                'project_name' => $project->name,
                'project_number' => $project->project_number,
                'state_label' => $this->datasetStateLabel($state),
                'label' => $state === 'input_required' ? 'Continue dataset inputs' : 'Open solar dataset',
                'copy' => $state === 'input_required'
                    ? 'The stored dataset still requires current location, source, or monthly inputs.'
                    : 'Open the stored dataset and review its current source basis.',
                'method' => 'get',
                'url' => route('companies.location-solar.show', [$company, $project]),
            ];
        }

        return [
            'project_name' => $project->name,
            'project_number' => $project->project_number,
            'state_label' => 'No Dataset',
            'label' => 'Open project',
            'copy' => 'This read-only view cannot create a new solar dataset.',
            'method' => 'get',
            'url' => route('companies.projects.show', [$company, $project]),
        ];
    }

    private function datasetState(
        Project $project,
        ?ProjectSolarProfile $profile,
        int $completeMonths
    ): string {
        return $this->solarAssessment->state($project, $profile, $completeMonths);
    }

    private function datasetStateLabel(string $state): string
    {
        return $this->solarAssessment->stateLabel($state);
    }

    private function sourceTypeLabel(string $source): string
    {
        return $this->solarAssessment->sourceTypeLabel($source);
    }

    private function createDefaultProfile(Request $request, Company $company, Project $project): ProjectSolarProfile
    {
        $profile = ProjectSolarProfile::query()->firstOrCreate(
            ['project_id' => $project->getKey()],
            [
                'company_id' => $company->getKey(),
                'data_source' => 'manual',
                'timezone' => null,
                'quality_status' => 'incomplete',
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]
        );

        if ((int) $profile->company_id !== (int) $company->getKey()) {
            abort(404);
        }

        return $profile;
    }

    private function recordHistory(
        Request $request,
        Company $company,
        Project $project,
        ProjectSolarProfile $profile,
        string $eventType,
        ?string $previousSource,
        ?string $newSource,
        ?string $previousStatus,
        ?string $newStatus,
        string $summary
    ): void {
        LocationSolarHistory::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'project_solar_profile_id' => $profile->getKey(),
            'event_type' => $eventType,
            'previous_source' => $previousSource,
            'new_source' => $newSource,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'summary' => $summary,
            'changed_by' => $request->user()->getKey(),
            'changed_at' => now(),
        ]);
    }
}
