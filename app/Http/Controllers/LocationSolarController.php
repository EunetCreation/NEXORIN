<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\UpdateLocationSolarRequest;
use App\Models\Company;
use App\Models\LocationSolarHistory;
use App\Models\MonthlySolarDatum;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationSolarController extends Controller
{
    use AuthorizesProjectResource;

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        if (! in_array($status, ['all', 'not_started', 'incomplete', 'ready', 'verified'], true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'solarProfile:id,project_id,quality_status,data_source,updated_at,verified_at',
            ]);

        if (! $user->hasCompanyPermission($company, 'location_solar.view')) {
            $query->ownedBy($user);
        }

        $projects = $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('project_number', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'not_started', fn (Builder $builder) => $builder->whereDoesntHave('solarProfile'))
            ->when($status === 'incomplete', fn (Builder $builder) => $builder->whereHas('solarProfile', fn (Builder $profile) => $profile->where('quality_status', 'incomplete')))
            ->when($status === 'ready', fn (Builder $builder) => $builder->whereHas('solarProfile', fn (Builder $profile) => $profile->whereIn('quality_status', ['estimated', 'imported'])))
            ->when($status === 'verified', fn (Builder $builder) => $builder->whereHas('solarProfile', fn (Builder $profile) => $profile->where('quality_status', 'verified')))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasCompanyPermission($company, 'location_solar.view')) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'started' => (clone $statsQuery)->whereHas('solarProfile')->count(),
            'ready' => (clone $statsQuery)->whereHas('solarProfile', fn (Builder $profile) => $profile->whereIn('quality_status', ['estimated', 'imported', 'verified']))->count(),
            'verified' => (clone $statsQuery)->whereHas('solarProfile', fn (Builder $profile) => $profile->where('quality_status', 'verified'))->count(),
        ];

        return view('location_solar.index', [
            'company' => $company,
            'projects' => $projects,
            'stats' => $stats,
            'filters' => compact('search', 'status'),
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

        $profile = $project->solarProfile()->first();
        $canManage = $this->canManageProjectResource(
            $request,
            $company,
            $project,
            ['location_solar.manage'],
            ['location_solar.manage_own']
        ) && ! $project->isArchived();
        $canVerify = $request->user()->hasCompanyPermission($company, 'location_solar.verify')
            && ! $project->isArchived();

        if (! $profile && $canManage) {
            $profile = $this->createDefaultProfile($request, $company, $project);
        }

        $profile ??= new ProjectSolarProfile([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'data_source' => 'manual',
            'timezone' => 'Asia/Jakarta',
            'quality_status' => 'incomplete',
        ]);

        if ($profile->exists) {
            $profile->load([
                'monthlyData',
                'verifier:id,name',
                'histories.changedBy:id,name',
            ]);
        } else {
            $profile->setRelation('monthlyData', collect());
            $profile->setRelation('histories', collect());
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
        $summary = $profile->calculateSummary($project);
        $project->loadMissing('customer:id,name,customer_number');

        return view('location_solar.show', compact(
            'company',
            'project',
            'profile',
            'monthlyRows',
            'summary',
            'canManage',
            'canVerify'
        ));
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

        $validated = $request->validated();

        DB::transaction(function () use ($request, $company, $project, $validated): void {
            $profile = $this->createDefaultProfile($request, $company, $project);
            $previousSource = $profile->data_source;
            $previousStatus = $profile->quality_status;

            $project->update([
                'site_address' => $validated['site_address'],
                'city' => $validated['city'],
                'province' => $validated['province'],
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
        if ((int) $project->company_id !== (int) $company->getKey()) {
            abort(404);
        }

        if (! $request->user()->hasCompanyPermission($company, 'location_solar.verify')) {
            abort(403);
        }

        $profile = $project->solarProfile()->with('monthlyData')->firstOrFail();
        $summary = $profile->calculateSummary($project);

        if (! $summary['is_ready_for_verification']) {
            throw ValidationException::withMessages([
                'verification' => 'Complete the coordinates, source name, and all 12 monthly GHI, PSH, and temperature values before verification.',
            ]);
        }

        $previousStatus = $profile->quality_status;
        $profile->update([
            'quality_status' => 'verified',
            'verified_by' => $request->user()->getKey(),
            'verified_at' => now(),
            'updated_by' => $request->user()->getKey(),
        ]);

        $this->recordHistory(
            $request,
            $company,
            $project,
            $profile,
            'verified',
            $profile->data_source,
            $profile->data_source,
            $previousStatus,
            'verified',
            'An authorized engineer or company administrator verified the location and solar dataset.'
        );

        return redirect()
            ->route('companies.location-solar.show', [$company, $project])
            ->with('success', 'Location and solar data verified successfully.');
    }

    private function createDefaultProfile(Request $request, Company $company, Project $project): ProjectSolarProfile
    {
        return ProjectSolarProfile::query()->firstOrCreate(
            ['project_id' => $project->getKey()],
            [
                'company_id' => $company->getKey(),
                'data_source' => 'manual',
                'timezone' => 'Asia/Jakarta',
                'quality_status' => 'incomplete',
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]
        );
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
