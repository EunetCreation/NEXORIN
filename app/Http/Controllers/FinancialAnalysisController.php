<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\RunFinancialAnalysisRequest;
use App\Models\Company;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Services\Finance\FinancialAnalysisEngine;
use App\Services\Finance\FinancialBasisService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinancialAnalysisController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly FinancialAnalysisEngine $financialEngine,
        private readonly FinancialBasisService $financialBasis,
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = ['all', 'not_calculated', 'draft', 'calculated', 'locked'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $hasPortfolioVisibility = $user->hasAnyCompanyPermission(
            $company,
            ['project.view', 'calculation.view', 'quotation.view']
        );

        $query = Project::query()
            ->forCompany($company)
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'loadAnalysis:id,project_id,updated_at',
                'solarProfile:id,project_id,updated_at',
                'roofSiteSurvey:id,project_id,updated_at',
                'systemRecommendation:id,project_id,updated_at',
                'technicalCalculation:id,project_id,result_status,results,calculated_at,verified_at,updated_at',
                'billOfMaterial:id,project_id,technical_calculation_id,pricing_status,grand_price_idr,priced_at,updated_at',
                'financialAnalysis:id,project_id,technical_calculation_id,bill_of_material_id,analysis_number,status,currency,project_capex_idr,annual_energy_kwh,first_year_saving_idr,simple_payback_years,npv_idr,irr_percent,lcoe_idr_per_kwh,project_lifetime_years,warnings,source_snapshot,calculated_at,updated_at',
            ]);

        if (! $hasPortfolioVisibility) {
            $query->ownedBy($user);
        }

        $projects = $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereLike('name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('project_number', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('city', "%{$search}%", caseSensitive: false)
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->whereLike('name', "%{$search}%", caseSensitive: false)
                            ->orWhereLike('customer_number', "%{$search}%", caseSensitive: false));
                });
            })
            ->when($status === 'not_calculated', fn (Builder $builder) => $builder->whereDoesntHave('financialAnalysis'))
            ->when(
                $status !== 'all' && $status !== 'not_calculated',
                fn (Builder $builder) => $builder->whereHas(
                    'financialAnalysis',
                    fn (Builder $analysis) => $analysis->where('status', $status)
                )
            )
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $registryStates = [];

        foreach ($projects->getCollection() as $project) {
            $registryStates[$project->getKey()] = [
                'basis' => $this->financialBasisState($project),
                'analysis' => $this->financialRecordState($project),
                'freshness' => $project->financialAnalysis?->sourceFreshness($project),
            ];
        }

        $statsQuery = Project::query()
            ->forCompany($company)
            ->current();

        if (! $hasPortfolioVisibility) {
            $statsQuery->ownedBy($user);
        }

        $stats = $this->financialPortfolioStats($statsQuery);

        return view('financial_analyses.index', [
            'company' => $company,
            'projects' => $projects,
            'stats' => $stats,
            'registryStates' => $registryStates,
            'visibilityScope' => $hasPortfolioVisibility ? 'company' : 'owned',
            'filters' => compact('search', 'status'),
        ]);
    }

    /** @return array{key: string, label: string, detail: string, tone: string, ready: bool, annual_energy_kwh: float} */
    private function financialBasisState(Project $project): array
    {
        return $this->financialBasis->assess($project);
    }

    /** @return array{key: string, label: string, detail: string, tone: string} */
    private function financialRecordState(Project $project): array
    {
        $analysis = $project->financialAnalysis;

        if (! $analysis) {
            return [
                'key' => 'not_calculated',
                'label' => 'Not Calculated',
                'detail' => 'No financial analysis is stored for this project.',
                'tone' => 'neutral',
            ];
        }

        if ($analysis->status === 'locked') {
            return [
                'key' => 'legacy_locked',
                'label' => 'Legacy Locked',
                'detail' => 'Stored legacy state; no active financial lock workflow exists in the current application.',
                'tone' => 'legacy',
            ];
        }

        if ($analysis->status === 'calculated') {
            return [
                'key' => 'calculated',
                'label' => 'Calculated',
                'detail' => 'A stored deterministic financial result is available.',
                'tone' => 'calculated',
            ];
        }

        return [
            'key' => 'draft',
            'label' => 'Stored Draft',
            'detail' => 'A draft financial record exists but is not a calculated result.',
            'tone' => 'draft',
        ];
    }

    /**
     * Portfolio statistics are produced by one aggregate query rather than one query per metric card.
     * The readiness expression mirrors the current finance-engine prerequisites without executing the engine per row.
     *
     * @return array<string, int>
     */
    private function financialPortfolioStats(Builder $query): array
    {
        $connection = (string) config('database.default', 'sqlite');
        $annualEnergyExpression = match ($connection) {
            'pgsql' => "COALESCE(NULLIF(tc.results->>'annual_energy_kwh', '')::numeric, 0) > 0",
            'mysql', 'mariadb' => "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(tc.results, '$.annual_energy_kwh')) AS DECIMAL(20,4)), 0) > 0",
            default => "COALESCE(CAST(json_extract(tc.results, '$.annual_energy_kwh') AS REAL), 0) > 0",
        };

        $upstreamChanged = "(\n"
            ."EXISTS (SELECT 1 FROM load_analyses la WHERE la.project_id = tc.project_id AND la.updated_at > tc.calculated_at) OR "
            ."EXISTS (SELECT 1 FROM project_solar_profiles sp WHERE sp.project_id = tc.project_id AND sp.updated_at > tc.calculated_at) OR "
            ."EXISTS (SELECT 1 FROM roof_site_surveys rs WHERE rs.project_id = tc.project_id AND rs.updated_at > tc.calculated_at) OR "
            ."EXISTS (SELECT 1 FROM system_recommendations sr WHERE sr.project_id = tc.project_id AND sr.updated_at > tc.calculated_at)\n"
            .")";

        $technicalCurrent = "EXISTS (\n"
            ."SELECT 1 FROM technical_calculations tc "
            ."WHERE tc.project_id = projects.id "
            ."AND tc.company_id = projects.company_id "
            ."AND tc.calculated_at IS NOT NULL "
            ."AND tc.results IS NOT NULL "
            ."AND {$annualEnergyExpression} "
            ."AND NOT {$upstreamChanged}\n"
            .")";

        $technicalStale = "EXISTS (\n"
            ."SELECT 1 FROM technical_calculations tc "
            ."WHERE tc.project_id = projects.id "
            ."AND tc.company_id = projects.company_id "
            ."AND tc.calculated_at IS NOT NULL "
            ."AND {$upstreamChanged}\n"
            .")";

        $pricedBoq = "EXISTS (\n"
            ."SELECT 1 FROM bill_of_materials bom "
            ."INNER JOIN technical_calculations tc_bom ON tc_bom.project_id = bom.project_id "
            ."WHERE bom.project_id = projects.id "
            ."AND bom.company_id = projects.company_id "
            ."AND tc_bom.company_id = projects.company_id "
            ."AND bom.technical_calculation_id = tc_bom.id "
            ."AND bom.pricing_status = 'priced' "
            ."AND bom.grand_price_idr > 0 "
            ."AND bom.priced_at IS NOT NULL\n"
            .")";

        $storedAnalysis = "EXISTS (SELECT 1 FROM financial_analyses fa WHERE fa.project_id = projects.id)";
        $calculatedAnalysis = "EXISTS (SELECT 1 FROM financial_analyses fa WHERE fa.project_id = projects.id AND fa.status = 'calculated')";
        $legacyLocked = "EXISTS (SELECT 1 FROM financial_analyses fa WHERE fa.project_id = projects.id AND fa.status = 'locked')";
        $basisReady = "({$technicalCurrent} AND {$pricedBoq})";

        $row = $query->selectRaw("\n"
            ."COUNT(*) AS projects,\n"
            ."SUM(CASE WHEN {$technicalCurrent} THEN 1 ELSE 0 END) AS technical_current,\n"
            ."SUM(CASE WHEN {$technicalStale} THEN 1 ELSE 0 END) AS technical_stale,\n"
            ."SUM(CASE WHEN NOT {$technicalCurrent} AND NOT {$technicalStale} THEN 1 ELSE 0 END) AS technical_basis_required,\n"
            ."SUM(CASE WHEN {$pricedBoq} THEN 1 ELSE 0 END) AS priced_boq,\n"
            ."SUM(CASE WHEN {$basisReady} THEN 1 ELSE 0 END) AS basis_ready,\n"
            ."SUM(CASE WHEN {$storedAnalysis} THEN 1 ELSE 0 END) AS stored_analyses,\n"
            ."SUM(CASE WHEN {$calculatedAnalysis} THEN 1 ELSE 0 END) AS calculated,\n"
            ."SUM(CASE WHEN {$legacyLocked} THEN 1 ELSE 0 END) AS legacy_locked,\n"
            ."SUM(CASE WHEN {$basisReady} AND NOT {$storedAnalysis} THEN 1 ELSE 0 END) AS ready_without_analysis,\n"
            ."SUM(CASE WHEN {$technicalCurrent} AND NOT {$pricedBoq} THEN 1 ELSE 0 END) AS pricing_required\n"
        )->first();

        $keys = [
            'projects',
            'technical_current',
            'technical_stale',
            'technical_basis_required',
            'priced_boq',
            'basis_ready',
            'stored_analyses',
            'calculated',
            'legacy_locked',
            'ready_without_analysis',
            'pricing_required',
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => (int) ($row?->{$key} ?? 0)])
            ->all();
    }

    public function show(Request $request, Company $company, Project $project): View
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.view', 'price.selling.view', 'quotation.view', 'quotation.create', 'price.selling.update'],
            ['calculation.view_own', 'quotation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number',
            'loadAnalysis:id,project_id,updated_at',
            'solarProfile:id,project_id,updated_at',
            'roofSiteSurvey:id,project_id,updated_at',
            'systemRecommendation:id,project_id,updated_at',
            'technicalCalculation:id,company_id,project_id,result_status,system_type,results,calculated_at,updated_at',
            'billOfMaterial:id,company_id,project_id,technical_calculation_id,bom_number,pricing_status,grand_price_idr,priced_at,updated_at',
            'financialAnalysis.calculator:id,name',
        ]);

        $user = $request->user();
        $analysis = $project->financialAnalysis;
        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;
        $basisState = $this->financialBasis->assess($project);
        $freshness = $analysis?->sourceFreshness($project);
        $recordState = $this->financialRecordState($project);

        $canCalculate = $user->hasAnyCompanyPermission($company, ['price.selling.update', 'quotation.create']);
        $canRun = $basisState['ready'] && $canCalculate;
        $canOpenRegistry = $user->hasAnyCompanyPermission($company, ['calculation.view', 'price.selling.view', 'quotation.view', 'calculation.view_own', 'quotation.view_own']);
        $hasOwnCalculationView = $user->hasAnyCompanyPermission($company, ['calculation.view_own']);
        $canOpenTechnical = $user->hasAnyCompanyPermission($company, ['calculation.view', 'calculation.run', 'calculation.revise', 'calculation.verify']);
        $canOpenBom = $user->hasAnyCompanyPermission($company, ['calculation.view', 'calculation.run', 'calculation.revise']);

        if ((! $canOpenTechnical || ! $canOpenBom) && $hasOwnCalculationView && $project->isOwnedBy($user)) {
            $canOpenTechnical = true;
            $canOpenBom = true;
        }

        $defaults = $this->financialEngine->defaultParameters();
        $parameters = $defaults;
        $parameterSources = collect(array_keys($defaults))->mapWithKeys(fn (string $key) => [$key => 'default'])->all();

        if ($analysis) {
            $parameters = array_merge($defaults, [
                'electricity_tariff_idr_per_kwh' => $analysis->electricity_tariff_idr_per_kwh,
                'export_credit_percent' => $analysis->export_credit_percent,
                'self_consumption_percent' => $analysis->self_consumption_percent,
                'tariff_escalation_percent' => $analysis->tariff_escalation_percent,
                'annual_om_percent' => $analysis->annual_om_percent,
                'discount_rate_percent' => $analysis->discount_rate_percent,
                'energy_degradation_percent' => $analysis->energy_degradation_percent,
                'project_lifetime_years' => $analysis->project_lifetime_years,
                'replacement_year' => $analysis->replacement_year,
                'replacement_cost_idr' => $analysis->replacement_cost_idr,
                'project_capex_idr' => $analysis->project_capex_idr,
                'analysis_note' => $analysis->notes,
            ]);
            $parameterSources = collect(array_keys($defaults))->mapWithKeys(fn (string $key) => [$key => 'saved'])->all();
        } elseif ($bom && (float) $bom->grand_price_idr > 0) {
            $parameters['project_capex_idr'] = $bom->grand_price_idr;
            $parameterSources['project_capex_idr'] = 'priced_boq';
        }

        $snapshotBoq = (float) ($analysis?->source_snapshot['bom_grand_price_idr'] ?? 0);
        $analysisCapex = $analysis ? (float) $analysis->project_capex_idr : null;
        $capexReference = $analysis ? $snapshotBoq : (float) ($bom?->grand_price_idr ?? 0);
        $customCapex = $analysis
            ? ($snapshotBoq <= 0 || abs($analysisCapex - $snapshotBoq) > 0.01)
            : false;
        $capexBasis = [
            'analysis_capex_idr' => $analysisCapex,
            'priced_boq_reference_idr' => $capexReference > 0 ? $capexReference : null,
            'is_custom' => $customCapex,
            'label' => $analysis
                ? ($customCapex ? 'Custom analysis CAPEX' : 'Uses priced BOQ')
                : (($bom && (float) $bom->grand_price_idr > 0) ? 'Priced BOQ reference' : 'CAPEX basis unavailable'),
        ];

        return view('financial_analyses.show', compact(
            'company',
            'project',
            'analysis',
            'calculation',
            'bom',
            'basisState',
            'freshness',
            'recordState',
            'canCalculate',
            'canRun',
            'canOpenRegistry',
            'canOpenTechnical',
            'canOpenBom',
            'defaults',
            'parameters',
            'parameterSources',
            'capexBasis'
        ));
    }

    public function calculate(RunFinancialAnalysisRequest $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['price.selling.update', 'quotation.create'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot calculate financial analysis.');
        }

        $project->load([
            'loadAnalysis',
            'solarProfile',
            'roofSiteSurvey',
            'systemRecommendation',
            'technicalCalculation',
            'billOfMaterial',
        ]);

        $basisState = $this->financialBasis->assess($project);

        if (! $basisState['ready']) {
            throw ValidationException::withMessages([
                'financial_basis' => $basisState['label'].': '.$basisState['detail'],
            ]);
        }

        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;

        $payload = $this->financialEngine->calculate(
            $project,
            $calculation,
            $bom,
            $request->validated()
        );

        DB::transaction(function () use ($request, $company, $project, $calculation, $bom, $payload): void {
            $analysis = $project->financialAnalysis()->firstOrNew();
            $isNew = ! $analysis->exists;
            $summary = $payload['summary'];
            $parameters = $payload['parameters'];

            $analysis->fill([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'technical_calculation_id' => $calculation->getKey(),
                'bill_of_material_id' => $bom->getKey(),
                'analysis_number' => $analysis->analysis_number ?: $this->nextAnalysisNumber($company),
                'status' => 'calculated',
                'currency' => 'IDR',
                'electricity_tariff_idr_per_kwh' => $parameters['electricity_tariff_idr_per_kwh'],
                'export_credit_percent' => $parameters['export_credit_percent'],
                'self_consumption_percent' => $parameters['self_consumption_percent'],
                'tariff_escalation_percent' => $parameters['tariff_escalation_percent'],
                'annual_om_percent' => $parameters['annual_om_percent'],
                'discount_rate_percent' => $parameters['discount_rate_percent'],
                'energy_degradation_percent' => $parameters['energy_degradation_percent'],
                'project_lifetime_years' => $parameters['project_lifetime_years'],
                'replacement_year' => $parameters['replacement_year'],
                'replacement_cost_idr' => $parameters['replacement_cost_idr'],
                'project_capex_idr' => $summary['project_capex_idr'],
                'annual_energy_kwh' => $summary['annual_energy_kwh'],
                'first_year_saving_idr' => $summary['first_year_saving_idr'],
                'annual_om_cost_idr' => $summary['annual_om_cost_idr'],
                'simple_payback_years' => $summary['simple_payback_years'],
                'lifetime_roi_percent' => $summary['lifetime_roi_percent'],
                'npv_idr' => $summary['npv_idr'],
                'irr_percent' => $summary['irr_percent'],
                'lcoe_idr_per_kwh' => $summary['lcoe_idr_per_kwh'],
                'summary' => $summary,
                'cashflows' => $payload['cashflows'],
                'warnings' => $payload['warnings'],
                'source_snapshot' => $payload['source_snapshot'],
                'notes' => $parameters['analysis_note'],
                'calculated_by' => $request->user()->getKey(),
                'calculated_at' => now(),
                'created_by' => $isNew
                    ? $request->user()->getKey()
                    : $analysis->created_by,
                'updated_by' => $request->user()->getKey(),
            ]);

            $analysis->save();
        });

        return redirect()
            ->route('companies.financial-analyses.show', [$company, $project])
            ->with('success', 'Financial analysis calculated from technical and BOQ results.');
    }

    private function nextAnalysisNumber(Company $company): string
    {
        $prefix = 'FIN-'.$company->code.'-'.now()->format('ymd');
        $count = FinancialAnalysis::query()
            ->where('company_id', $company->getKey())
            ->where('analysis_number', 'like', $prefix.'%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $count);
    }
}
