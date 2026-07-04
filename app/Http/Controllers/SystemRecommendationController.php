<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\StoreSystemRecommendationOverrideRequest;
use App\Http\Requests\UpdateSystemRecommendationRequest;
use App\Models\Company;
use App\Models\Project;
use App\Models\SystemRecommendation;
use App\Models\SystemRecommendationHistory;
use App\Services\Recommendation\SystemRecommendationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SystemRecommendationController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly SystemRecommendationEngine $engine
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = [
            'all',
            'not_started',
            'incomplete',
            'generated',
            'review_required',
            'overridden',
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'systemRecommendation:id,project_id,recommended_system,engine_recommended_system,result_status,on_grid_score,hybrid_score,decisiveness_index,generated_at,updated_at',
            ]);

        if (! $user->hasCompanyPermission($company, 'recommendation.view')) {
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
            ->when($status === 'not_started', fn (Builder $builder) => $builder->whereDoesntHave('systemRecommendation'))
            ->when(
                $status !== 'all' && $status !== 'not_started',
                fn (Builder $builder) => $builder->whereHas(
                    'systemRecommendation',
                    fn (Builder $recommendation) => $recommendation->where('result_status', $status)
                )
            )
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasCompanyPermission($company, 'recommendation.view')) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'generated' => (clone $statsQuery)->whereHas(
                'systemRecommendation',
                fn (Builder $recommendation) => $recommendation->whereIn(
                    'result_status',
                    ['generated', 'review_required', 'overridden']
                )
            )->count(),
            'on_grid' => (clone $statsQuery)->whereHas(
                'systemRecommendation',
                fn (Builder $recommendation) => $recommendation->where('recommended_system', 'on_grid')
            )->count(),
            'hybrid' => (clone $statsQuery)->whereHas(
                'systemRecommendation',
                fn (Builder $recommendation) => $recommendation->where('recommended_system', 'hybrid')
            )->count(),
        ];

        return view('recommendations.index', [
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
            ['recommendation.view', 'recommendation.run', 'recommendation.override'],
            ['recommendation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number',
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'roofSurfaces',
        ]);

        $recommendation = $project->systemRecommendation()->first();
        $recommendation ??= new SystemRecommendation([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            ...$this->defaultInputs(),
            'result_status' => 'incomplete',
            'engine_version' => SystemRecommendationEngine::VERSION,
        ]);

        if ($recommendation->exists) {
            $recommendation->load([
                'scores',
                'histories.changedBy:id,name',
                'generator:id,name',
                'overrider:id,name',
            ]);
        } else {
            $recommendation->setRelation('scores', collect());
            $recommendation->setRelation('histories', collect());
        }

        $sourceAssessment = $this->engine->assessSources(
            $project,
            $this->recommendationInputs($recommendation)
        );
        $scoreMatrix = $recommendation->scores
            ->groupBy('criterion_code');
        $canRun = $request->user()->hasCompanyPermission($company, 'recommendation.run')
            && ! $project->isArchived();
        $canOverride = $request->user()->hasCompanyPermission($company, 'recommendation.override')
            && ! $project->isArchived();
        $isStale = $recommendation->exists
            && $recommendation->hasGeneratedResult()
            && $recommendation->isStale($project);

        return view('recommendations.show', compact(
            'company',
            'project',
            'recommendation',
            'sourceAssessment',
            'scoreMatrix',
            'canRun',
            'canOverride',
            'isStale'
        ));
    }

    public function run(
        UpdateSystemRecommendationRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['recommendation.run'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot generate a recommendation.');
        }

        $inputs = $request->validated();
        $result = $this->engine->evaluate($project, $inputs);

        DB::transaction(function () use ($request, $company, $project, $inputs, $result): void {
            $recommendation = $project->systemRecommendation()->firstOrNew();
            $previousSystem = $recommendation->recommended_system;
            $previousStatus = $recommendation->result_status;
            $isNew = ! $recommendation->exists;

            $recommendation->fill([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                ...$inputs,
                'engine_recommended_system' => $result['engine_recommended_system'],
                'recommended_system' => $result['recommended_system'],
                'on_grid_score' => $result['on_grid_score'],
                'hybrid_score' => $result['hybrid_score'],
                'decisiveness_index' => $result['decisiveness_index'],
                'result_status' => $result['result_status'],
                'engine_version' => $result['engine_version'],
                'hard_constraints' => $result['hard_constraints'],
                'warnings' => $result['warnings'],
                'reasons' => $result['reasons'],
                'source_snapshot' => $result['source_snapshot'],
                'generated_by' => $request->user()->getKey(),
                'generated_at' => now(),
                'override_system' => null,
                'override_reason' => null,
                'overridden_by' => null,
                'overridden_at' => null,
                'created_by' => $isNew
                    ? $request->user()->getKey()
                    : $recommendation->created_by,
                'updated_by' => $request->user()->getKey(),
            ]);
            $recommendation->save();

            $recommendation->scores()->delete();

            if ($result['scores'] !== []) {
                $recommendation->scores()->createMany(
                    collect($result['scores'])->map(fn (array $score): array => [
                        ...$score,
                        'company_id' => $company->getKey(),
                        'project_id' => $project->getKey(),
                    ])->all()
                );
            }

            $this->recordHistory(
                $request,
                $company,
                $project,
                $recommendation,
                'generated',
                $previousSystem,
                $recommendation->recommended_system,
                $previousStatus,
                $recommendation->result_status,
                $result['result_status'] === 'incomplete'
                    ? 'The recommendation engine evaluated the project but found blocking prerequisite conditions.'
                    : 'The recommendation engine recalculated on-grid and hybrid suitability using the current source data and user targets.'
            );
        });

        $redirect = redirect()->route(
            'companies.recommendations.show',
            [$company, $project]
        );

        if ($result['result_status'] === 'incomplete') {
            return $redirect->with(
                'error',
                'Recommendation not generated. Resolve the listed source-data blockers and run the engine again.'
            );
        }

        return $redirect->with('success', 'System recommendation generated successfully.');
    }

    public function override(
        StoreSystemRecommendationOverrideRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['recommendation.override'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be overridden.');
        }

        $recommendation = $project->systemRecommendation()->firstOrFail();

        if (! $recommendation->hasGeneratedResult()) {
            throw ValidationException::withMessages([
                'override_system' => 'Generate an engine recommendation before applying an override.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use (
            $request,
            $company,
            $project,
            $recommendation,
            $validated
        ): void {
            $previousSystem = $recommendation->recommended_system;
            $previousStatus = $recommendation->result_status;

            $recommendation->update([
                'recommended_system' => $validated['override_system'],
                'override_system' => $validated['override_system'],
                'override_reason' => $validated['override_reason'],
                'overridden_by' => $request->user()->getKey(),
                'overridden_at' => now(),
                'result_status' => 'overridden',
                'updated_by' => $request->user()->getKey(),
            ]);

            $this->recordHistory(
                $request,
                $company,
                $project,
                $recommendation,
                'overridden',
                $previousSystem,
                $recommendation->recommended_system,
                $previousStatus,
                'overridden',
                $validated['override_reason']
            );
        });

        return redirect()
            ->route('companies.recommendations.show', [$company, $project])
            ->with('success', 'Engineer override applied successfully.');
    }

    public function resetOverride(
        Request $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['recommendation.override'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $recommendation = $project->systemRecommendation()->firstOrFail();

        if ($recommendation->override_system === null) {
            return back()->with('error', 'No engineer override is currently applied.');
        }

        DB::transaction(function () use ($request, $company, $project, $recommendation): void {
            $previousSystem = $recommendation->recommended_system;
            $previousStatus = $recommendation->result_status;
            $restoredStatus = (float) $recommendation->decisiveness_index < 10
                ? 'review_required'
                : 'generated';

            $recommendation->update([
                'recommended_system' => $recommendation->engine_recommended_system,
                'override_system' => null,
                'override_reason' => null,
                'overridden_by' => null,
                'overridden_at' => null,
                'result_status' => $restoredStatus,
                'updated_by' => $request->user()->getKey(),
            ]);

            $this->recordHistory(
                $request,
                $company,
                $project,
                $recommendation,
                'override_reset',
                $previousSystem,
                $recommendation->recommended_system,
                $previousStatus,
                $restoredStatus,
                'The engineer override was removed and the original engine recommendation was restored.'
            );
        });

        return redirect()
            ->route('companies.recommendations.show', [$company, $project])
            ->with('success', 'Engineer override removed successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultInputs(): array
    {
        return [
            'grid_connection_status' => 'stable',
            'outage_frequency_per_month' => 0,
            'average_outage_duration_hours' => 0,
            'backup_target_hours' => 0,
            'target_self_sufficiency_percent' => 30,
            'budget_flexibility' => 'balanced',
            'export_policy' => 'unknown',
            'primary_objective' => 'balanced',
            'notes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recommendationInputs(SystemRecommendation $recommendation): array
    {
        return [
            'grid_connection_status' => $recommendation->grid_connection_status,
            'outage_frequency_per_month' => (int) $recommendation->outage_frequency_per_month,
            'average_outage_duration_hours' => (float) $recommendation->average_outage_duration_hours,
            'backup_target_hours' => (float) $recommendation->backup_target_hours,
            'target_self_sufficiency_percent' => (float) $recommendation->target_self_sufficiency_percent,
            'budget_flexibility' => $recommendation->budget_flexibility,
            'export_policy' => $recommendation->export_policy,
            'primary_objective' => $recommendation->primary_objective,
            'notes' => $recommendation->notes,
        ];
    }

    private function recordHistory(
        Request $request,
        Company $company,
        Project $project,
        SystemRecommendation $recommendation,
        string $eventType,
        ?string $previousSystem,
        ?string $newSystem,
        ?string $previousStatus,
        ?string $newStatus,
        string $summary
    ): void {
        SystemRecommendationHistory::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'system_recommendation_id' => $recommendation->getKey(),
            'event_type' => $eventType,
            'previous_system' => $previousSystem,
            'new_system' => $newSystem,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'summary' => $summary,
            'snapshot' => [
                'recommended_system' => $recommendation->recommended_system,
                'engine_recommended_system' => $recommendation->engine_recommended_system,
                'on_grid_score' => $recommendation->on_grid_score,
                'hybrid_score' => $recommendation->hybrid_score,
                'decisiveness_index' => $recommendation->decisiveness_index,
                'engine_version' => $recommendation->engine_version,
            ],
            'changed_by' => $request->user()->getKey(),
            'changed_at' => now(),
        ]);
    }
}
