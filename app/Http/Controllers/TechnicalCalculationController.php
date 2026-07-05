<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\StoreTechnicalCalculationOverrideRequest;
use App\Http\Requests\UpdateTechnicalCalculationRequest;
use App\Models\Company;
use App\Models\Project;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Models\TechnicalCalculationHistory;
use App\Models\TechnicalCalculationOverride;
use App\Services\Calculation\TechnicalCalculationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TechnicalCalculationController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly TechnicalCalculationEngine $engine
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = ['all', 'not_started', 'preliminary', 'detailed', 'verified'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'technicalCalculation:id,project_id,result_status,calculated_at,verified_at',
            ]);

        if (! $user->hasCompanyPermission($company, 'calculation.view')) {
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
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'not_started', fn (Builder $builder) => $builder->whereDoesntHave('technicalCalculation'))
            ->when(
                $status !== 'all' && $status !== 'not_started',
                fn (Builder $builder) => $builder->whereHas(
                    'technicalCalculation',
                    fn (Builder $calculation) => $calculation->where('result_status', $status)
                )
            )
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasCompanyPermission($company, 'calculation.view')) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'calculated' => (clone $statsQuery)->whereHas('technicalCalculation')->count(),
            'detailed' => (clone $statsQuery)->whereHas(
                'technicalCalculation',
                fn (Builder $calculation) => $calculation->where('result_status', 'detailed')
            )->count(),
            'verified' => (clone $statsQuery)->whereHas(
                'technicalCalculation',
                fn (Builder $calculation) => $calculation->where('result_status', 'verified')
            )->count(),
        ];

        return view('technical_calculations.index', [
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
            ['calculation.view', 'calculation.run', 'calculation.revise', 'calculation.verify'],
            ['calculation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number',
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'systemRecommendation',
        ]);

        $calculation = $project->technicalCalculation()->first();
        $calculation ??= new TechnicalCalculation([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'system_type' => $this->defaultSystemType($project),
        ]);

        if ($calculation->exists) {
            $calculation->load([
                'histories.user:id,name',
                'activeOverrides.creator:id,name',
                'calculator:id,name',
                'verifier:id,name',
            ]);
        } else {
            $calculation->setRelation('histories', collect());
            $calculation->setRelation('activeOverrides', collect());
        }

        $sources = $this->collectSources($project);
        $canRun = $request->user()->hasAnyCompanyPermission($company, ['calculation.run', 'calculation.revise'])
            && ! $project->isArchived();
        $canVerify = $request->user()->hasCompanyPermission($company, 'calculation.verify')
            && ! $project->isArchived();
        $canOverride = $request->user()->hasCompanyPermission($company, 'calculation.revise')
            && ! $project->isArchived()
            && $calculation->exists
            && $calculation->calculated_at !== null
            && $calculation->result_status !== 'verified';
        $overrideFields = TechnicalCalculationOverride::fieldOptions();

        return view('technical_calculations.show', compact(
            'company',
            'project',
            'calculation',
            'sources',
            'canRun',
            'canVerify',
            'canOverride',
            'overrideFields'
        ));
    }

    public function run(
        UpdateTechnicalCalculationRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.run', 'calculation.revise'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot run technical calculations.');
        }

        $project->load([
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'systemRecommendation',
        ]);

        $inputs = $request->validated();
        $sources = $this->sourceSnapshot($this->collectSources($project));
        $result = $this->engine->calculate($project, $inputs, $sources);

        DB::transaction(function () use ($request, $company, $project, $inputs, $result, $sources): void {
            $calculation = $project->technicalCalculation()->firstOrNew();
            $previousStatus = $calculation->result_status;
            $isNew = ! $calculation->exists;

            $calculation->fill([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                ...$inputs,
                'result_status' => $result['result_status'],
                'results' => $this->resultPayload($result),
                'engine_results' => $this->resultPayload($result),
                'override_summary' => null,
                'warnings' => $result['warnings'],
                'source_snapshot' => $sources,
                'calculated_by' => $request->user()->getKey(),
                'calculated_at' => now(),
                'verified_by' => null,
                'verified_at' => null,
                'created_by' => $isNew
                    ? $request->user()->getKey()
                    : $calculation->created_by,
                'updated_by' => $request->user()->getKey(),
            ]);
            $calculation->save();

            if (! $isNew) {
                $calculation->activeOverrides()->update([
                    'is_active' => false,
                    'reset_reason' => 'Calculation was rerun. Previous overrides were superseded by a new engine result.',
                    'reset_by' => $request->user()->getKey(),
                    'reset_at' => now(),
                ]);
            }

            $this->recordHistory(
                $request,
                $calculation,
                'calculated',
                $previousStatus,
                $calculation->result_status,
                'Technical calculation was recalculated from the current engineering inputs and source snapshot.'
            );
        });

        return redirect()
            ->route('companies.technical-calculations.show', [$company, $project])
            ->with('success', 'Technical calculation completed successfully. Review warnings before using the result.');
    }

    public function verify(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.verify'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be verified.');
        }

        $project->load([
            'loadAnalysis',
            'solarProfile',
            'roofSiteSurvey',
            'systemRecommendation',
        ]);

        $calculation = $project->technicalCalculation()->firstOrFail();

        if (! $calculation->calculated_at || ! $calculation->results) {
            throw ValidationException::withMessages([
                'calculation' => 'Run the technical calculation before verification.',
            ]);
        }

        if ($calculation->isStale($project)) {
            throw ValidationException::withMessages([
                'calculation' => 'Source data changed after the last run. Recalculate before verification.',
            ]);
        }

        DB::transaction(function () use ($request, $calculation): void {
            $previousStatus = $calculation->result_status;

            $calculation->update([
                'result_status' => 'verified',
                'verified_by' => $request->user()->getKey(),
                'verified_at' => now(),
                'updated_by' => $request->user()->getKey(),
            ]);

            $this->recordHistory(
                $request,
                $calculation,
                'verified',
                $previousStatus,
                'verified',
                'Engineer verified the technical calculation result.'
            );
        });

        return redirect()
            ->route('companies.technical-calculations.show', [$company, $project])
            ->with('success', 'Technical calculation verified successfully.');
    }



    public function storeOverride(
        StoreTechnicalCalculationOverrideRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.revise'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be revised.');
        }

        $calculation = $project->technicalCalculation()->firstOrFail();

        if (! $calculation->calculated_at || ! $calculation->results) {
            throw ValidationException::withMessages([
                'override_value' => 'Run the technical calculation before applying an override.',
            ]);
        }

        if ($calculation->result_status === 'verified') {
            return back()->with('error', 'Verified calculations are locked. Recalculate or create a revision before applying an override.');
        }

        $field = $request->validated('field');
        $options = TechnicalCalculationOverride::fieldOptions();
        $definition = $options[$field];
        $overrideValue = $this->normalizeOverrideValue($request->validated('override_value'), $definition['type']);

        DB::transaction(function () use ($request, $company, $project, $calculation, $field, $definition, $overrideValue): void {
            $results = $calculation->results ?? [];
            $engineResults = $calculation->engine_results ?: $results;
            $previousValue = $results[$field] ?? null;
            $engineValue = $engineResults[$field] ?? $previousValue;

            $calculation->activeOverrides()
                ->where('field', $field)
                ->update([
                    'is_active' => false,
                    'reset_reason' => 'Replaced by a newer override for the same field.',
                    'reset_by' => $request->user()->getKey(),
                    'reset_at' => now(),
                ]);

            $override = TechnicalCalculationOverride::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'technical_calculation_id' => $calculation->getKey(),
                'field' => $field,
                'label' => $definition['label'],
                'unit' => $definition['unit'],
                'value_type' => $definition['type'],
                'engine_value' => $this->stringValue($engineValue),
                'previous_value' => $this->stringValue($previousValue),
                'override_value' => $this->stringValue($overrideValue),
                'reason' => $request->validated('reason'),
                'is_active' => true,
                'created_by' => $request->user()->getKey(),
            ]);

            $results[$field] = $overrideValue;
            $results = $this->markFormulaOverride(
                $results,
                $field,
                $overrideValue,
                true,
                $override->reason
            );

            $calculation->fill([
                'results' => $results,
                'engine_results' => $engineResults,
                'override_summary' => $this->activeOverrideSummary($calculation),
                'verified_by' => null,
                'verified_at' => null,
                'updated_by' => $request->user()->getKey(),
            ]);
            $calculation->save();

            $this->recordHistory(
                $request,
                $calculation,
                'overridden',
                $calculation->result_status,
                $calculation->result_status,
                sprintf(
                    '%s was overridden from %s to %s. Reason: %s',
                    $override->label,
                    $override->formattedPreviousValue(),
                    $override->formattedOverrideValue(),
                    $override->reason
                )
            );
        });

        return redirect()
            ->route('companies.technical-calculations.show', [$company, $project])
            ->with('success', 'Technical calculation override saved. Downstream BOM, financial analysis, quotation, and reports will use the effective overridden value.');
    }

    public function resetOverride(
        Request $request,
        Company $company,
        Project $project,
        TechnicalCalculationOverride $override
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.revise'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be revised.');
        }

        $calculation = $project->technicalCalculation()->firstOrFail();

        if ($calculation->result_status === 'verified') {
            return back()->with('error', 'Verified calculations are locked. Recalculate or create a revision before resetting an override.');
        }

        if (
            $override->company_id !== $company->getKey()
            || $override->project_id !== $project->getKey()
            || $override->technical_calculation_id !== $calculation->getKey()
        ) {
            abort(404);
        }

        DB::transaction(function () use ($request, $calculation, $override): void {
            $results = $calculation->results ?? [];
            $engineResults = $calculation->engine_results ?: $results;
            $field = $override->field;
            $engineValue = $engineResults[$field] ?? $override->engine_value;

            if ($override->is_active) {
                $override->update([
                    'is_active' => false,
                    'reset_reason' => 'Override reset by engineer. Effective value restored to engine result.',
                    'reset_by' => $request->user()->getKey(),
                    'reset_at' => now(),
                ]);
            }

            $results[$field] = $this->normalizeOverrideValue($engineValue, $override->value_type);
            $results = $this->markFormulaOverride($results, $field, $results[$field], false);

            $calculation->fill([
                'results' => $results,
                'override_summary' => $this->activeOverrideSummary($calculation),
                'updated_by' => $request->user()->getKey(),
            ]);
            $calculation->save();

            $this->recordHistory(
                $request,
                $calculation,
                'override_reset',
                $calculation->result_status,
                $calculation->result_status,
                sprintf('%s override was reset to the engine value.', $override->label)
            );
        });

        return redirect()
            ->route('companies.technical-calculations.show', [$company, $project])
            ->with('success', 'Technical calculation override reset. The field now uses the engine value again.');
    }

    /**
     * @return array<string, mixed>
     */
    private function collectSources(Project $project): array
    {
        $loadSummary = $project->loadAnalysis?->calculateSummary();
        $solarSummary = $project->solarProfile?->calculateSummary($project);
        $roofSummary = $project->roofSiteSurvey?->calculateSummary();

        if ($loadSummary !== null) {
            $loadSummary['supply_phase'] = $project->loadAnalysis?->supply_phase ?? 'single_phase';
            $loadSummary['tariff_group'] = $project->loadAnalysis?->tariff_group;
            $loadSummary['electricity_rate_per_kwh'] = $project->loadAnalysis?->electricity_rate_per_kwh;
        }

        return [
            'load' => $loadSummary ?? [],
            'solar' => $solarSummary ?? [],
            'roof' => $roofSummary ?? [],
            'recommendation' => $project->systemRecommendation,
        ];
    }

    /**
     * @param  array<string, mixed>  $sources
     * @return array<string, mixed>
     */
    private function sourceSnapshot(array $sources): array
    {
        $recommendation = $sources['recommendation'];

        return [
            'load' => $sources['load'],
            'solar' => $sources['solar'],
            'roof' => $sources['roof'],
            'recommendation' => $recommendation instanceof SystemRecommendation
                ? [
                    'recommended_system' => $recommendation->recommended_system,
                    'engine_recommended_system' => $recommendation->engine_recommended_system,
                    'result_status' => $recommendation->result_status,
                    'on_grid_score' => $recommendation->on_grid_score,
                    'hybrid_score' => $recommendation->hybrid_score,
                    'decisiveness_index' => $recommendation->decisiveness_index,
                    'generated_at' => $recommendation->generated_at?->toISOString(),
                ]
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function resultPayload(array $result): array
    {
        unset($result['warnings'], $result['source_snapshot']);

        return $result;
    }

    private function defaultSystemType(Project $project): string
    {
        $recommendedSystem = $project->systemRecommendation?->recommended_system;

        if (in_array($recommendedSystem, ['on_grid', 'hybrid'], true)) {
            return $recommendedSystem;
        }

        if (in_array($project->proposed_system, ['on_grid', 'hybrid'], true)) {
            return $project->proposed_system;
        }

        return 'on_grid';
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeOverrideSummary(TechnicalCalculation $calculation): array
    {
        return $calculation->activeOverrides()
            ->get()
            ->map(fn (TechnicalCalculationOverride $override): array => [
                'id' => $override->getKey(),
                'field' => $override->field,
                'label' => $override->label,
                'unit' => $override->unit,
                'engine_value' => $override->engine_value,
                'previous_value' => $override->previous_value,
                'override_value' => $override->override_value,
                'reason' => $override->reason,
                'created_by' => $override->created_by,
                'created_at' => $override->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $results
     * @return array<string, mixed>
     */
    private function markFormulaOverride(
        array $results,
        string $field,
        mixed $effectiveValue,
        bool $isOverridden,
        ?string $reason = null
    ): array {
        if (! isset($results['formula_snapshot']) || ! is_array($results['formula_snapshot'])) {
            return $results;
        }

        if (! isset($results['formula_snapshot'][$field]) || ! is_array($results['formula_snapshot'][$field])) {
            return $results;
        }

        $results['formula_snapshot'][$field]['effective_result'] = $effectiveValue;
        $results['formula_snapshot'][$field]['is_overridden'] = $isOverridden;

        if ($isOverridden) {
            $results['formula_snapshot'][$field]['override_reason'] = $reason;
        } else {
            unset($results['formula_snapshot'][$field]['override_reason']);
        }

        return $results;
    }

    private function normalizeOverrideValue(mixed $value, string $type): int|float|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($type === 'integer') {
            return (int) round((float) $value);
        }

        if ($type === 'numeric') {
            return round((float) $value, 6);
        }

        return (string) $value;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function recordHistory(
        Request $request,
        TechnicalCalculation $calculation,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        string $notes
    ): void {
        TechnicalCalculationHistory::query()->create([
            'technical_calculation_id' => $calculation->getKey(),
            'user_id' => $request->user()->getKey(),
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'snapshot' => [
                'result_status' => $calculation->result_status,
                'system_type' => $calculation->system_type,
                'installed_pv_kw' => $calculation->results['installed_pv_kw'] ?? null,
                'module_count' => $calculation->results['module_count'] ?? null,
                'dc_ac_ratio' => $calculation->results['dc_ac_ratio'] ?? null,
                'warning_count' => count($calculation->warnings ?? []),
            ],
            'changed_at' => now(),
        ]);
    }
}
