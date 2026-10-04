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
        $canViewCost = $user->hasCompanyPermission($company, 'price.cost.view');
        $canViewSelling = $user->hasAnyCompanyPermission($company, ['price.selling.view', 'quotation.view']);
        $canViewMargin = $canViewCost && $canViewSelling;
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $system = (string) $request->query('system', 'all');
        $projectStatus = (string) $request->query('project_status', 'all');

        $allowedStatuses = ['all', 'not_generated', 'draft', 'generated', 'priced', 'released', 'locked'];
        $allowedSystems = ['all', ...Project::PROPOSED_SYSTEMS];
        $allowedProjectStatuses = ['all', ...Project::STATUSES];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        if (! in_array($system, $allowedSystems, true)) {
            $system = 'all';
        }

        if (! in_array($projectStatus, $allowedProjectStatuses, true)) {
            $projectStatus = 'all';
        }

        $baseScope = $company->projects()->current();

        if (! $user->hasCompanyPermission($company, 'calculation.view')) {
            $baseScope->ownedBy($user);
        }

        $bomColumns = [
            'id',
            'project_id',
            'bom_number',
            'status',
            'summary',
            'pricing_status',
            'generated_at',
            'priced_at',
            'updated_at',
        ];

        if ($canViewCost) {
            $bomColumns[] = 'grand_cost_idr';
        }

        if ($canViewSelling) {
            $bomColumns[] = 'grand_price_idr';
        }

        if ($canViewMargin) {
            $bomColumns[] = 'gross_margin_idr';
            $bomColumns[] = 'gross_margin_percent';
        }

        $directoryQuery = (clone $baseScope)
            ->with([
                'customer:id,name,customer_number',
                'technicalCalculation:id,project_id,result_status,calculated_at,verified_at,updated_at',
                'activeReleaseApproval',
                'billOfMaterial' => fn ($bomQuery) => $bomQuery->select($bomColumns),
            ]);

        $projects = $directoryQuery
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('projects.name', 'like', "%{$search}%")
                        ->orWhere('projects.project_number', 'like', "%{$search}%")
                        ->orWhere('projects.city', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%"));
                });
            })
            ->when($system !== 'all', fn (Builder $builder) => $builder->where('projects.proposed_system', $system))
            ->when($projectStatus !== 'all', fn (Builder $builder) => $builder->where('projects.status', $projectStatus))
            ->when($status === 'not_generated', fn (Builder $builder) => $builder->whereDoesntHave('billOfMaterial'))
            ->when($status === 'priced', fn (Builder $builder) => $builder->whereHas(
                'billOfMaterial',
                fn (Builder $bom) => $bom->where('pricing_status', 'priced')
            ))
            ->when($status === 'released', fn (Builder $builder) => $builder->whereHas('activeReleaseApproval'))
            ->when(
                $status !== 'all' && ! in_array($status, ['not_generated', 'priced', 'released'], true),
                fn (Builder $builder) => $builder->whereHas(
                    'billOfMaterial',
                    fn (Builder $bom) => $bom->where('status', $status)
                )
            )
            ->latest('projects.updated_at')
            ->paginate(8)
            ->withQueryString();

        // Aggregate each project once. Active client-release approvals are collapsed
        // to one project id before joining because the approval table is historical and
        // may contain multiple rows for the same project across revoke/re-approve cycles.
        $activeReleaseProjects = DB::table('project_release_approvals')
            ->select('project_id')
            ->where('status', 'active')
            ->groupBy('project_id');

        // A single aggregate query replaces independent count requests while also
        // producing mutually-exclusive lifecycle buckets. This matters because the
        // application permits draft BOM generation before engineer verification, so
        // simple subtraction of totals would not describe the real persisted states.
        $aggregate = (clone $baseScope)
            ->leftJoin('technical_calculations as technical', 'technical.project_id', '=', 'projects.id')
            ->leftJoin('bill_of_materials as bom', 'bom.project_id', '=', 'projects.id')
            ->leftJoinSub($activeReleaseProjects, 'active_release', fn ($join) => $join
                ->on('active_release.project_id', '=', 'projects.id'))
            ->selectRaw('COUNT(projects.id) as projects')
            ->selectRaw("SUM(CASE WHEN technical.result_status = 'verified' THEN 1 ELSE 0 END) as ready")
            ->selectRaw('SUM(CASE WHEN bom.id IS NOT NULL THEN 1 ELSE 0 END) as generated')
            ->selectRaw("SUM(CASE WHEN bom.pricing_status = 'priced' THEN 1 ELSE 0 END) as priced")
            ->selectRaw('SUM(CASE WHEN active_release.project_id IS NOT NULL THEN 1 ELSE 0 END) as released')
            ->selectRaw("SUM(CASE WHEN active_release.project_id IS NULL AND bom.pricing_status = 'priced' THEN 1 ELSE 0 END) as priced_unreleased")
            ->selectRaw("SUM(CASE WHEN active_release.project_id IS NULL AND bom.id IS NOT NULL AND COALESCE(bom.pricing_status, 'not_priced') <> 'priced' THEN 1 ELSE 0 END) as generated_unpriced")
            ->selectRaw("SUM(CASE WHEN active_release.project_id IS NULL AND bom.id IS NULL AND technical.result_status = 'verified' THEN 1 ELSE 0 END) as verified_not_generated")
            ->selectRaw("SUM(CASE WHEN active_release.project_id IS NULL AND bom.id IS NULL AND (technical.result_status IS NULL OR technical.result_status <> 'verified') THEN 1 ELSE 0 END) as engineering_pending")
            ->selectRaw("SUM(CASE WHEN bom.status = 'locked' THEN 1 ELSE 0 END) as legacy_locked")
            ->selectRaw($canViewCost
                ? "COALESCE(SUM(CASE WHEN bom.pricing_status = 'priced' THEN bom.grand_cost_idr ELSE 0 END), 0) as total_cost_idr"
                : '0 as total_cost_idr')
            ->selectRaw($canViewSelling
                ? "COALESCE(SUM(CASE WHEN bom.pricing_status = 'priced' THEN bom.grand_price_idr ELSE 0 END), 0) as total_price_idr"
                : '0 as total_price_idr')
            ->selectRaw($canViewMargin
                ? "COALESCE(SUM(CASE WHEN bom.pricing_status = 'priced' THEN bom.gross_margin_idr ELSE 0 END), 0) as gross_margin_idr"
                : '0 as gross_margin_idr')
            ->first();

        $stats = [
            'projects' => (int) ($aggregate?->projects ?? 0),
            'ready' => (int) ($aggregate?->ready ?? 0),
            'generated' => (int) ($aggregate?->generated ?? 0),
            'priced' => (int) ($aggregate?->priced ?? 0),
            'released' => (int) ($aggregate?->released ?? 0),
            'priced_unreleased' => (int) ($aggregate?->priced_unreleased ?? 0),
            'generated_unpriced' => (int) ($aggregate?->generated_unpriced ?? 0),
            'verified_not_generated' => (int) ($aggregate?->verified_not_generated ?? 0),
            'engineering_pending' => (int) ($aggregate?->engineering_pending ?? 0),
            'legacy_locked' => (int) ($aggregate?->legacy_locked ?? 0),
            'total_cost_idr' => (float) ($aggregate?->total_cost_idr ?? 0),
            'total_price_idr' => (float) ($aggregate?->total_price_idr ?? 0),
            'gross_margin_idr' => (float) ($aggregate?->gross_margin_idr ?? 0),
        ];

        $visibleProjectIds = (clone $baseScope)->select('projects.id');
        $categoryRows = DB::table('bill_of_material_items as item')
            ->join('bill_of_materials as bom', 'bom.id', '=', 'item.bill_of_material_id')
            ->where('bom.company_id', $company->getKey())
            ->whereIn('bom.project_id', clone $visibleProjectIds)
            ->selectRaw("COALESCE(NULLIF(item.category, ''), 'uncategorized') as category")
            ->selectRaw('COUNT(item.id) as line_items')
            ->selectRaw($canViewCost
                ? 'COALESCE(SUM(item.line_cost_idr), 0) as total_cost_idr'
                : '0 as total_cost_idr')
            ->selectRaw($canViewSelling
                ? 'COALESCE(SUM(item.line_price_idr), 0) as total_price_idr'
                : '0 as total_price_idr')
            ->groupBy('item.category')
            ->orderByDesc('line_items')
            ->get();

        $categoryLabels = [
            'pv_module' => 'Modules & PV',
            'mounting' => 'Structural BOS',
            'dc_electrical' => 'DC Electrical',
            'power_conversion' => 'Inverters',
            'ac_electrical' => 'AC Electrical',
            'battery_ess' => 'Battery & ESS',
            'monitoring' => 'Monitoring',
            'grounding_safety' => 'Grounding & Safety',
            'civil_site' => 'Civil & Site',
            'engineering_tc' => 'Engineering & Testing',
            'legal_logistics_om' => 'Legal, Logistics & O&M',
            'uncategorized' => 'Other',
        ];
        $totalLineItems = (int) $categoryRows->sum('line_items');
        $compositionCostTotal = (float) $categoryRows->sum('total_cost_idr');
        $compositionPriceTotal = (float) $categoryRows->sum('total_price_idr');
        $categoryBreakdown = $categoryRows
            ->map(fn ($row): array => [
                'key' => (string) $row->category,
                'label' => $categoryLabels[$row->category] ?? str($row->category)->replace('_', ' ')->title()->toString(),
                'line_items' => (int) $row->line_items,
                'total_cost_idr' => (float) $row->total_cost_idr,
                'total_price_idr' => (float) $row->total_price_idr,
                'share' => $totalLineItems > 0
                    ? round(((int) $row->line_items / $totalLineItems) * 100, 1)
                    : 0.0,
                'cost_share' => $compositionCostTotal > 0
                    ? round(((float) $row->total_cost_idr / $compositionCostTotal) * 100, 1)
                    : 0.0,
                'price_share' => $compositionPriceTotal > 0
                    ? round(((float) $row->total_price_idr / $compositionPriceTotal) * 100, 1)
                    : 0.0,
            ])
            ->values();

        // Portfolio control signals are derived from BOM summaries produced by the
        // quantity/pricing engines. Keeping the derivation server-side makes every
        // dashboard number traceable to persisted engineering data rather than UI mocks.
        $portfolioBoms = BillOfMaterial::query()
            ->where('company_id', $company->getKey())
            ->whereIn('project_id', clone $visibleProjectIds)
            ->get([
                'id',
                'project_id',
                'status',
                'pricing_status',
                'summary',
                'warnings',
                'generated_at',
                'priced_at',
                'updated_at',
            ]);

        $scopeTotals = [
            'base' => 0,
            'optional' => 0,
            'catalog_review' => 0,
            'utility_owner' => 0,
        ];
        $controlIssues = [
            'catalog_review' => 0,
            'missing_price' => 0,
            'expired_price' => 0,
            'unavailable_price' => 0,
            'low_margin' => 0,
            'warnings' => 0,
        ];
        $chargeableItems = 0;

        foreach ($portfolioBoms as $portfolioBom) {
            $summary = is_array($portfolioBom->summary) ? $portfolioBom->summary : [];
            $scope = (array) data_get($summary, 'scope_counters', []);
            $pricingHealth = (array) data_get($summary, 'pricing_health', []);

            $scopeTotals['base'] += (int) ($scope['base_boq_item_count'] ?? data_get($summary, 'base_boq_item_count', 0));
            $scopeTotals['optional'] += (int) ($scope['optional_addon_item_count'] ?? data_get($summary, 'optional_addon_item_count', 0));
            $scopeTotals['catalog_review'] += (int) ($scope['catalog_candidate_item_count'] ?? data_get($summary, 'catalog_candidate_item_count', 0));
            $scopeTotals['utility_owner'] += (int) ($scope['utility_responsibility_item_count'] ?? data_get($summary, 'utility_responsibility_item_count', 0));
            $scopeTotals['utility_owner'] += (int) ($scope['owner_responsibility_item_count'] ?? data_get($summary, 'owner_responsibility_item_count', 0));

            $chargeableItems += (int) ($pricingHealth['chargeable_item_count'] ?? data_get($summary, 'chargeable_item_count', 0));
            $controlIssues['catalog_review'] += (int) ($pricingHealth['catalog_review_item_count'] ?? data_get($summary, 'catalog_review_item_count', 0));
            $controlIssues['missing_price'] += (int) ($pricingHealth['missing_price_item_count'] ?? data_get($summary, 'missing_price_item_count', 0));
            $controlIssues['expired_price'] += (int) ($pricingHealth['expired_price_item_count'] ?? data_get($summary, 'expired_price_item_count', 0));
            $controlIssues['unavailable_price'] += (int) ($pricingHealth['unavailable_price_item_count'] ?? data_get($summary, 'unavailable_price_item_count', 0));
            $controlIssues['low_margin'] += (int) ($pricingHealth['low_margin_item_count'] ?? data_get($summary, 'low_margin_item_count', 0));
            $controlIssues['warnings'] += count((array) ($portfolioBom->warnings ?? []));
        }

        $statusBreakdown = collect([
            ['key' => 'not_ready', 'label' => 'Engineering pending', 'value' => $stats['engineering_pending']],
            ['key' => 'ready', 'label' => 'Verified / no BOM', 'value' => $stats['verified_not_generated']],
            ['key' => 'generated', 'label' => 'Generated / unpriced', 'value' => $stats['generated_unpriced']],
            ['key' => 'priced', 'label' => 'Priced / no active approval', 'value' => $stats['priced_unreleased']],
            ['key' => 'released', 'label' => 'Active release approval', 'value' => $stats['released']],
        ])->filter(fn (array $item): bool => $item['value'] > 0)->values();

        $stats['line_items'] = max(0, $totalLineItems);
        $stats['cost_composition_total_idr'] = $compositionCostTotal;
        $stats['price_composition_total_idr'] = $compositionPriceTotal;
        $stats['awaiting_generation'] = $stats['verified_not_generated'];
        $stats['unpriced'] = $stats['generated_unpriced'];
        $stats['uncontrolled'] = $stats['priced_unreleased'];
        $stats['engineering_readiness_percent'] = $stats['projects'] > 0
            ? (int) round(($stats['ready'] / $stats['projects']) * 100)
            : 0;
        $stats['pricing_coverage_percent'] = $stats['generated'] > 0
            ? (int) round(($stats['priced'] / $stats['generated']) * 100)
            : 0;
        $stats['control_coverage_percent'] = $stats['priced'] > 0
            ? (int) round(($stats['released'] / $stats['priced']) * 100)
            : 0;
        $stats['gross_margin_percent'] = $stats['total_price_idr'] > 0
            ? round(($stats['gross_margin_idr'] / $stats['total_price_idr']) * 100, 1)
            : 0.0;
        $stats['unresolved_controls'] = $controlIssues['catalog_review']
            + $controlIssues['missing_price']
            + $controlIssues['expired_price']
            + $controlIssues['unavailable_price']
            + $controlIssues['low_margin'];
        $stats['catalog_governance_percent'] = $stats['generated'] > 0
            ? ($chargeableItems > 0
                ? (int) round(max(0, 1 - ($controlIssues['catalog_review'] / $chargeableItems)) * 100)
                : 100)
            : 0;
        $stats['pricing_integrity_percent'] = $stats['priced'] > 0
            ? ($chargeableItems > 0
                ? (int) round(max(0, 1 - (($controlIssues['missing_price'] + $controlIssues['expired_price'] + $controlIssues['unavailable_price']) / $chargeableItems)) * 100)
                : 100)
            : 0;
        $stats['portfolio_readiness_percent'] = (int) round(
            ($stats['engineering_readiness_percent'] * .35)
            + ($stats['pricing_coverage_percent'] * .30)
            + ($stats['control_coverage_percent'] * .20)
            + ($stats['catalog_governance_percent'] * .15)
        );

        $readinessBreakdown = collect([
            [
                'key' => 'engineering',
                'label' => 'Engineering basis',
                'value' => $stats['ready'],
                'total' => $stats['projects'],
                'percent' => $stats['engineering_readiness_percent'],
            ],
            [
                'key' => 'generated',
                'label' => 'Material packages',
                'value' => $stats['generated'],
                'total' => $stats['projects'],
                'percent' => $stats['projects'] > 0 ? (int) round(($stats['generated'] / $stats['projects']) * 100) : 0,
            ],
            [
                'key' => 'priced',
                'label' => 'Commercial pricing',
                'value' => $stats['priced'],
                'total' => max(1, $stats['generated']),
                'percent' => $stats['pricing_coverage_percent'],
            ],
            [
                'key' => 'controlled',
                'label' => 'Active approval coverage',
                'value' => $stats['released'],
                'total' => max(1, $stats['priced']),
                'percent' => $stats['control_coverage_percent'],
            ],
        ]);

        $stats['portfolio_health_score'] = (int) round(
            ($stats['engineering_readiness_percent'] * .30)
            + ($stats['pricing_coverage_percent'] * .25)
            + ($stats['control_coverage_percent'] * .20)
            + ($stats['catalog_governance_percent'] * .15)
            + ($stats['pricing_integrity_percent'] * .10)
        );

        $scopeImpact = collect([
            ['key' => 'base', 'label' => 'Base BOQ', 'value' => $scopeTotals['base']],
            ['key' => 'optional', 'label' => 'Optional add-ons', 'value' => $scopeTotals['optional']],
            ['key' => 'catalog_review', 'label' => 'Catalog candidates', 'value' => $scopeTotals['catalog_review']],
            ['key' => 'utility_owner', 'label' => 'Utility / owner scope', 'value' => $scopeTotals['utility_owner']],
        ]);

        $issueBreakdown = collect([
            ['key' => 'catalog_review', 'label' => 'Catalog governance review', 'value' => $controlIssues['catalog_review']],
            ['key' => 'missing_price', 'label' => 'Missing price', 'value' => $controlIssues['missing_price']],
            ['key' => 'expired_price', 'label' => 'Expired quotation', 'value' => $controlIssues['expired_price']],
            ['key' => 'unavailable_price', 'label' => 'Unavailable price source', 'value' => $controlIssues['unavailable_price']],
            ['key' => 'low_margin', 'label' => 'Margin guard exception', 'value' => $controlIssues['low_margin']],
        ])->filter(fn (array $item): bool => $item['value'] > 0)->values();

        return view('boms.index', [
            'company' => $company,
            'projects' => $projects,
            'stats' => $stats,
            'categoryBreakdown' => $categoryBreakdown,
            'statusBreakdown' => $statusBreakdown,
            'readinessBreakdown' => $readinessBreakdown,
            'scopeImpact' => $scopeImpact,
            'issueBreakdown' => $issueBreakdown,
            'controlIssues' => $controlIssues,
            'canViewMaterialCatalog' => $user->hasCompanyPermission($company, 'equipment.view'),
            'canViewCost' => $canViewCost,
            'canViewSelling' => $canViewSelling,
            'canViewMargin' => $canViewMargin,
            'filters' => compact('search', 'status', 'system', 'projectStatus'),
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
            'activeReleaseApproval.approver:id,name',
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
