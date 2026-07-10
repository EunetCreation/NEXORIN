<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\ApplyBoqPricingRequest;
use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Project;
use App\Services\Pricing\PricingEngine;
use App\Services\Quantity\QuantityEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillOfMaterialController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly QuantityEngine $quantityEngine,
        private readonly PricingEngine $pricingEngine,
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = ['all', 'not_generated', 'draft', 'generated', 'priced', 'locked'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'technicalCalculation:id,project_id,result_status,calculated_at,verified_at',
                'billOfMaterial:id,project_id,bom_number,status,pricing_status,grand_price_idr,generated_at,priced_at',
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
            ->when($status === 'not_generated', fn (Builder $builder) => $builder->whereDoesntHave('billOfMaterial'))
            ->when($status === 'priced', fn (Builder $builder) => $builder->whereHas(
                'billOfMaterial',
                fn (Builder $bom) => $bom->where('pricing_status', 'priced')
            ))
            ->when(
                $status !== 'all' && ! in_array($status, ['not_generated', 'priced'], true),
                fn (Builder $builder) => $builder->whereHas(
                    'billOfMaterial',
                    fn (Builder $bom) => $bom->where('status', $status)
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
            'ready' => (clone $statsQuery)->whereHas('technicalCalculation', fn (Builder $calculation) => $calculation
                ->whereNotNull('calculated_at')
                ->whereNotNull('results'))
                ->count(),
            'generated' => (clone $statsQuery)->whereHas('billOfMaterial')->count(),
            'priced' => (clone $statsQuery)->whereHas('billOfMaterial', fn (Builder $bom) => $bom->where('pricing_status', 'priced'))->count(),
            'locked' => (clone $statsQuery)->whereHas('billOfMaterial', fn (Builder $bom) => $bom->where('status', 'locked'))->count(),
        ];

        return view('boms.index', [
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
            ['calculation.view', 'calculation.run', 'calculation.revise', 'price.selling.view', 'price.cost.view'],
            ['calculation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number',
            'technicalCalculation.calculator:id,name',
            'technicalCalculation.verifier:id,name',
            'billOfMaterial.items',
            'billOfMaterial.generator:id,name',
            'billOfMaterial.pricer:id,name',
        ]);

        $bom = $project->billOfMaterial;
        $calculation = $project->technicalCalculation;
        $canGenerate = $request->user()->hasAnyCompanyPermission($company, ['calculation.run', 'calculation.revise'])
            && ! $project->isArchived();
        $canPrice = $request->user()->hasAnyCompanyPermission($company, ['price.cost.update', 'price.selling.update'])
            && ! $project->isArchived()
            && (bool) $bom;
        $canViewCost = $request->user()->hasCompanyPermission($company, 'price.cost.view');
        $canViewSelling = $request->user()->hasAnyCompanyPermission($company, ['price.selling.view', 'quotation.view']);
        $pricingDefaults = $this->pricingEngine->defaultParameters();

        return view('boms.show', compact(
            'company',
            'project',
            'bom',
            'calculation',
            'canGenerate',
            'canPrice',
            'canViewCost',
            'canViewSelling',
            'pricingDefaults'
        ));
    }

    public function generate(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.run', 'calculation.revise'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot generate BOM/BOQ drafts.');
        }

        $project->load([
            'loadAnalysis',
            'solarProfile',
            'roofSiteSurvey',
            'systemRecommendation',
            'technicalCalculation',
        ]);

        $calculation = $project->technicalCalculation;

        if (! $calculation || ! $calculation->calculated_at || ! $calculation->results) {
            throw ValidationException::withMessages([
                'technical_calculation' => 'Run the technical calculation before generating BOM/BOQ.',
            ]);
        }

        if ($calculation->isStale($project)) {
            throw ValidationException::withMessages([
                'technical_calculation' => 'Source data changed after the last technical calculation. Recalculate before generating BOM/BOQ.',
            ]);
        }

        $payload = $this->quantityEngine->generate($project, $calculation);

        DB::transaction(function () use ($request, $company, $project, $calculation, $payload): void {
            $bom = $project->billOfMaterial()->firstOrNew();
            $isNew = ! $bom->exists;

            $bom->fill([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'technical_calculation_id' => $calculation->getKey(),
                'bom_number' => $bom->bom_number ?: $this->nextBomNumber($company),
                'status' => 'generated',
                'summary' => $payload['summary'],
                'warnings' => $payload['warnings'],
                'source_snapshot' => $payload['source_snapshot'],
                'pricing_status' => 'not_priced',
                'material_cost_subtotal_idr' => 0,
                'material_price_subtotal_idr' => 0,
                'labor_cost_idr' => 0,
                'logistics_cost_idr' => 0,
                'other_cost_idr' => 0,
                'contingency_cost_idr' => 0,
                'tax_idr' => 0,
                'discount_idr' => 0,
                'grand_cost_idr' => 0,
                'grand_price_idr' => 0,
                'gross_margin_idr' => 0,
                'gross_margin_percent' => 0,
                'pricing_parameters' => null,
                'priced_by' => null,
                'priced_at' => null,
                'generated_by' => $request->user()->getKey(),
                'generated_at' => now(),
                'created_by' => $isNew
                    ? $request->user()->getKey()
                    : $bom->created_by,
                'updated_by' => $request->user()->getKey(),
            ]);

            $bom->save();
            $bom->items()->delete();
            $bom->items()->createMany($payload['items']);
        });

        return redirect()
            ->route('companies.boms.show', [$company, $project])
            ->with('success', 'BOM/BOQ draft generated from the latest technical calculation.');
    }

    public function price(ApplyBoqPricingRequest $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['price.cost.update', 'price.selling.update'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be priced.');
        }

        $project->load(['billOfMaterial.items', 'technicalCalculation']);
        $bom = $project->billOfMaterial;

        if (! $bom || $bom->items->isEmpty()) {
            throw ValidationException::withMessages([
                'bom' => 'Generate BOM/BOQ items before applying pricing.',
            ]);
        }

        $calculation = $project->technicalCalculation;

        if ($calculation && $calculation->isStale($project)) {
            throw ValidationException::withMessages([
                'technical_calculation' => 'Source data changed after the last technical calculation. Recalculate and regenerate BOM/BOQ before pricing.',
            ]);
        }

        $payload = $this->pricingEngine->price($bom, $request->validated());

        DB::transaction(function () use ($request, $bom, $payload): void {
            foreach ($payload['items'] as $itemPayload) {
                if (! $itemPayload['id']) {
                    continue;
                }

                $bom->items()
                    ->whereKey($itemPayload['id'])
                    ->update([
                        'unit_cost_idr' => $itemPayload['unit_cost_idr'],
                        'unit_price_idr' => $itemPayload['unit_price_idr'],
                        'line_cost_idr' => $itemPayload['line_cost_idr'],
                        'line_price_idr' => $itemPayload['line_price_idr'],
                        'metadata' => $itemPayload['metadata'],
                    ]);
            }

            $bom->fill([
                'pricing_status' => $payload['summary']['pricing_status'] ?? 'priced',
                'summary' => $payload['summary'],
                'warnings' => array_values(array_unique(array_merge($bom->warnings ?? [], $payload['warnings']))),
                'material_cost_subtotal_idr' => $payload['summary']['material_cost_subtotal_idr'],
                'material_price_subtotal_idr' => $payload['summary']['material_price_subtotal_idr'],
                'labor_cost_idr' => $payload['summary']['labor_cost_idr'],
                'logistics_cost_idr' => $payload['summary']['logistics_cost_idr'],
                'other_cost_idr' => $payload['summary']['other_cost_idr'],
                'contingency_cost_idr' => $payload['summary']['contingency_cost_idr'],
                'tax_idr' => $payload['summary']['tax_idr'],
                'discount_idr' => $payload['summary']['discount_idr'],
                'grand_cost_idr' => $payload['summary']['grand_cost_idr'],
                'grand_price_idr' => $payload['summary']['grand_price_idr'],
                'gross_margin_idr' => $payload['summary']['gross_margin_idr'],
                'gross_margin_percent' => $payload['summary']['gross_margin_percent'],
                'pricing_parameters' => $payload['parameters'],
                'priced_by' => $request->user()->getKey(),
                'priced_at' => now(),
                'updated_by' => $request->user()->getKey(),
            ]);

            $bom->save();
        });

        return redirect()
            ->route('companies.boms.show', [$company, $project])
            ->with('success', 'BOQ pricing calculated using the company price resolver and fallback price book.');
    }

    private function nextBomNumber(Company $company): string
    {
        $prefix = 'BOM-'.$company->code.'-'.now()->format('ymd');
        $count = BillOfMaterial::query()
            ->where('company_id', $company->getKey())
            ->where('bom_number', 'like', $prefix.'%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $count);
    }
}
