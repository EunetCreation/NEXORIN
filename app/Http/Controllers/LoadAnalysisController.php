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
use Illuminate\View\View;

class LoadAnalysisController extends Controller
{
    use AuthorizesProjectResource;

    /**
     * Display projects available for load analysis.
     */
    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        if (! in_array($status, ['all', 'not_started', 'in_progress'], true)) {
            $status = 'all';
        }

        $baseQuery = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'loadAnalysis:id,project_id,updated_at',
            ])
            ->withCount([
                'electricityBills',
                'loadAppliances',
            ]);

        if (! $user->hasCompanyPermission($company, 'load_analysis.view')) {
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
                $status === 'not_started',
                fn (Builder $query) => $query->whereDoesntHave('loadAnalysis')
            )
            ->when(
                $status === 'in_progress',
                fn (Builder $query) => $query->whereHas('loadAnalysis')
            )
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasCompanyPermission($company, 'load_analysis.view')) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'started' => (clone $statsQuery)->whereHas('loadAnalysis')->count(),
            'with_appliances' => (clone $statsQuery)
                ->whereHas('loadAppliances')
                ->count(),
            'with_bills' => (clone $statsQuery)
                ->whereHas('electricityBills')
                ->count(),
        ];

        return view('load_analyses.index', [
            'company' => $company,
            'projects' => $projects,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display the load analysis workspace for a project.
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

        $analysis = $project->loadAnalysis()->first();
        $canManage = $this->canManageProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.manage'],
            ['load_analysis.manage_own']
        ) && ! $project->isArchived();

        if (! $analysis && $canManage) {
            $analysis = $this->createDefaultAnalysis(
                $request,
                $company,
                $project
            );
        }

        $analysis ??= new LoadAnalysis([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'source_mode' => 'combined',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
        ]);

        if ($analysis->exists) {
            $analysis->load([
                'electricityBills',
                'appliances',
                'creator:id,name',
                'updater:id,name',
            ]);
        } else {
            $analysis->setRelation('electricityBills', collect());
            $analysis->setRelation('appliances', collect());
        }

        $summary = $analysis->calculateSummary();
        $hourlyProfile = $analysis->calculateHourlyProfile(
            $analysis->appliances->all()
        );
        $profileMaximum = max($hourlyProfile ?: [0]);

        $project->loadMissing('customer:id,name,customer_number');

        return view('load_analyses.show', [
            'company' => $company,
            'project' => $project,
            'analysis' => $analysis,
            'summary' => $summary,
            'hourlyProfile' => $hourlyProfile,
            'profileMaximum' => $profileMaximum,
            'canManage' => $canManage,
        ]);
    }

    /**
     * Update load analysis settings.
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

        $analysis = $this->createDefaultAnalysis(
            $request,
            $company,
            $project
        );

        $analysis->update([
            ...$request->validated(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Load analysis settings updated successfully.');
    }

    /**
     * Create the default one-to-one load analysis record.
     */
    private function createDefaultAnalysis(
        Request $request,
        Company $company,
        Project $project
    ): LoadAnalysis {
        return LoadAnalysis::query()->firstOrCreate(
            [
                'project_id' => $project->getKey(),
            ],
            [
                'company_id' => $company->getKey(),
                'source_mode' => 'combined',
                'supply_phase' => 'single_phase',
                'billing_days' => 30,
                'daytime_start_hour' => 6,
                'daytime_end_hour' => 18,
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]
        );
    }
}
