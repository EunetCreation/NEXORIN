<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\RunFinancialAnalysisRequest;
use App\Models\Company;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Services\Finance\FinancialAnalysisEngine;
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

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'technicalCalculation:id,project_id,result_status,calculated_at,verified_at',
                'billOfMaterial:id,project_id,pricing_status,grand_price_idr,priced_at',
                'financialAnalysis:id,project_id,analysis_number,status,project_capex_idr,first_year_saving_idr,simple_payback_years,npv_idr,irr_percent,calculated_at',
            ]);

        if (! $user->hasAnyCompanyPermission($company, ['project.view', 'calculation.view', 'quotation.view'])) {
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

        $statsQuery = $company->projects()->current();

        if (! $user->hasAnyCompanyPermission($company, ['project.view', 'calculation.view', 'quotation.view'])) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'ready' => (clone $statsQuery)->whereHas('billOfMaterial', fn (Builder $bom) => $bom
                ->where('pricing_status', 'priced')
                ->where('grand_price_idr', '>', 0))
                ->whereHas('technicalCalculation', fn (Builder $calculation) => $calculation
                    ->whereNotNull('calculated_at')
                    ->whereNotNull('results'))
                ->count(),
            'calculated' => (clone $statsQuery)->whereHas('financialAnalysis', fn (Builder $analysis) => $analysis->where('status', 'calculated'))->count(),
            'locked' => (clone $statsQuery)->whereHas('financialAnalysis', fn (Builder $analysis) => $analysis->where('status', 'locked'))->count(),
        ];

        return view('financial_analyses.index', [
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
            ['calculation.view', 'price.selling.view', 'quotation.view', 'quotation.create', 'price.selling.update'],
            ['calculation.view_own', 'quotation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number',
            'technicalCalculation.calculator:id,name',
            'technicalCalculation.verifier:id,name',
            'billOfMaterial.items',
            'financialAnalysis.calculator:id,name',
        ]);

        $analysis = $project->financialAnalysis;
        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;
        $canRun = $request->user()->hasAnyCompanyPermission($company, ['price.selling.update', 'quotation.create'])
            && ! $project->isArchived();
        $defaults = $this->financialEngine->defaultParameters();
        $parameters = array_merge($defaults, $analysis?->summary ? [
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
        ] : []);

        if (! $parameters['project_capex_idr'] && $bom?->grand_price_idr) {
            $parameters['project_capex_idr'] = $bom->grand_price_idr;
        }

        return view('financial_analyses.show', compact(
            'company',
            'project',
            'analysis',
            'calculation',
            'bom',
            'canRun',
            'defaults',
            'parameters'
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
            'billOfMaterial.items',
        ]);

        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;

        if (! $calculation || ! $calculation->calculated_at || ! $calculation->results) {
            throw ValidationException::withMessages([
                'technical_calculation' => 'Run technical calculation before financial analysis.',
            ]);
        }

        if (! $bom || $bom->pricing_status !== 'priced' || (float) $bom->grand_price_idr <= 0) {
            throw ValidationException::withMessages([
                'bom' => 'Generate BOM/BOQ and calculate BOQ pricing before financial analysis.',
            ]);
        }

        if ($calculation->isStale($project)) {
            throw ValidationException::withMessages([
                'technical_calculation' => 'Source data changed after the last technical calculation. Recalculate before financial analysis.',
            ]);
        }

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
