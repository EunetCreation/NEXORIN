<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Services\Catalog\MaterialCatalogCompletenessEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialCatalogController extends Controller
{
    /**
     * Display the material catalog directory.
     */
    public function index(Request $request, Company $company): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer'],
            'system_type' => ['nullable', Rule::in(['all', 'on_grid', 'hybrid', 'off_grid'])],
            'scope' => ['nullable', Rule::in(['all', 'global', 'company'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', 'sku', 'brand', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $categoryId = $validated['category'] ?? null;
        $systemType = $validated['system_type'] ?? 'all';
        $scope = $validated['scope'] ?? 'all';
        $status = $validated['status'] ?? 'all';
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        $materials = Material::query()
            ->visibleForCompany($company)
            ->with(['category:id,name,code', 'company:id,name'])
            ->withCount('priceBookItems')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $materialQuery) use ($search): void {
                    $materialQuery
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('material_type', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn (Builder $query) => $query->where('material_category_id', $categoryId))
            ->when($systemType !== 'all', function (Builder $query) use ($systemType): void {
                $query->where(function (Builder $systemQuery) use ($systemType): void {
                    $systemQuery
                        ->whereNull('system_types')
                        ->orWhere('system_types', 'like', '%"'.$systemType.'"%');
                });
            })
            ->when($scope === 'global', fn (Builder $query) => $query->whereNull('company_id'))
            ->when($scope === 'company', fn (Builder $query) => $query->where('company_id', $company->getKey()))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $categories = $this->categories($company);

        $visibleCatalog = Material::query()->visibleForCompany($company);

        $stats = [
            'total' => (clone $visibleCatalog)->count(),
            'active' => (clone $visibleCatalog)->where('is_active', true)->count(),
            'company' => (clone $visibleCatalog)->where('company_id', $company->getKey())->count(),
            'global' => (clone $visibleCatalog)->whereNull('company_id')->count(),
        ];

        return view('materials.index', [
            'company' => $company,
            'materials' => $materials,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'category' => $categoryId,
                'system_type' => $systemType,
                'scope' => $scope,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Show the material creation form.
     */
    public function create(Company $company): View
    {
        return view('materials.create', [
            'company' => $company,
            'material' => new Material([
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
                'is_active' => true,
            ]),
            'categories' => $this->categories($company),
            'systemTypes' => $this->systemTypes(),
            'projectScales' => $this->projectScales(),
            'scopeModes' => $this->scopeModes(),
        ]);
    }

    /**
     * Store a company-specific material item.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validatedMaterialData($request, $company);
        $data['company_id'] = $company->getKey();
        $data['created_by'] = $request->user()->getKey();
        $data['updated_by'] = $request->user()->getKey();
        $data['is_active'] = $request->boolean('is_active');

        $material = Material::query()->create($data);

        return redirect()
            ->route('companies.materials.show', [$company, $material->getKey()])
            ->with('success', 'Material catalog item created successfully.');
    }

    /**
     * Display a material profile.
     */
    public function show(Company $company, string $material, MaterialCatalogCompletenessEvaluator $evaluator): View
    {
        $material = $this->findMaterial($company, $material);

        $material->load([
            'category:id,name,code',
            'company:id,name',
            'specs',
            'datasheets',
            'certificates',
            'priceBookItems.priceBook.supplier:id,name,supplier_code',
            'priceBookItems.supplier:id,name,supplier_code',
            'creator:id,name',
            'updater:id,name',
        ]);

        return view('materials.show', [
            'company' => $company,
            'material' => $material,
            'catalogReadiness' => $evaluator->evaluate($material),
            'canModify' => (int) $material->company_id === (int) $company->getKey(),
        ]);
    }

    /**
     * Show the material edit form.
     */
    public function edit(Company $company, string $material): View|RedirectResponse
    {
        $material = $this->findMaterial($company, $material);

        if ((int) $material->company_id !== (int) $company->getKey()) {
            return redirect()
                ->route('companies.materials.show', [$company, $material->getKey()])
                ->with('error', 'Global catalog items are read-only for this company. Create a company-specific material to customize it.');
        }

        return view('materials.edit', [
            'company' => $company,
            'material' => $material,
            'categories' => $this->categories($company),
            'systemTypes' => $this->systemTypes(),
            'projectScales' => $this->projectScales(),
            'scopeModes' => $this->scopeModes(),
        ]);
    }

    /**
     * Update a company-specific material item.
     */
    public function update(Request $request, Company $company, string $material): RedirectResponse
    {
        $material = $this->findMaterial($company, $material);

        if ((int) $material->company_id !== (int) $company->getKey()) {
            return redirect()
                ->route('companies.materials.show', [$company, $material->getKey()])
                ->with('error', 'Global catalog items are read-only for this company.');
        }

        $data = $this->validatedMaterialData($request, $company, $material);
        $data['updated_by'] = $request->user()->getKey();
        $data['is_active'] = $request->boolean('is_active');

        $material->update($data);

        return redirect()
            ->route('companies.materials.show', [$company, $material->getKey()])
            ->with('success', 'Material catalog item updated successfully.');
    }


    /**
     * Approve a company-specific catalog item after standardization checks pass.
     */
    public function approve(Request $request, Company $company, string $material, MaterialCatalogCompletenessEvaluator $evaluator): RedirectResponse
    {
        $material = $this->findMaterial($company, $material);

        if ((int) $material->company_id !== (int) $company->getKey()) {
            return back()->with('error', 'Global catalog items cannot be approved by this company.');
        }

        $readiness = $evaluator->evaluate($material);

        if (! $readiness['can_approve']) {
            return back()->with('error', 'Material cannot be approved yet. '.implode(' ', $readiness['notes']));
        }

        $material->update([
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_at' => now(),
            'approved_by' => $request->user()->getKey(),
            'last_reviewed_at' => now(),
            'last_reviewed_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Material approved for BOQ and quotation use.');
    }

    /**
     * Archive a company-specific material item.
     */
    public function archive(Company $company, string $material): RedirectResponse
    {
        $material = $this->findMaterial($company, $material);

        if ((int) $material->company_id !== (int) $company->getKey()) {
            return back()->with('error', 'Global catalog items cannot be archived by this company.');
        }

        $material->update(['is_active' => false]);

        return back()->with('success', 'Material catalog item archived successfully.');
    }

    /**
     * Reactivate a company-specific material item.
     */
    public function activate(Company $company, string $material): RedirectResponse
    {
        $material = $this->findMaterial($company, $material);

        if ((int) $material->company_id !== (int) $company->getKey()) {
            return back()->with('error', 'Global catalog items cannot be modified by this company.');
        }

        $material->update(['is_active' => true]);

        return back()->with('success', 'Material catalog item activated successfully.');
    }

    private function findMaterial(Company $company, string $materialId): Material
    {
        return Material::query()
            ->visibleForCompany($company)
            ->whereKey($materialId)
            ->firstOrFail();
    }

    private function categories(Company $company)
    {
        return MaterialCategory::query()
            ->visibleForCompany($company)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'company_id']);
    }

    private function validatedMaterialData(Request $request, Company $company, ?Material $material = null): array
    {
        $skuRule = Rule::unique('materials', 'sku')
            ->where(fn ($query) => $query->where('company_id', $company->getKey()));

        if ($material) {
            $skuRule->ignore($material->getKey());
        }

        $data = $request->validate([
            'material_category_id' => [
                'nullable',
                'integer',
                Rule::exists('material_categories', 'id')->where(function ($query) use ($company) {
                    $query->where(function ($scopeQuery) use ($company): void {
                        $scopeQuery
                            ->whereNull('company_id')
                            ->orWhere('company_id', $company->getKey());
                    });
                }),
            ],
            'sku' => [
                'required',
                'string',
                'max:80',
                $skuRule,
            ],
            'name' => ['required', 'string', 'max:180'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:140'],
            'material_type' => ['nullable', 'string', 'max:80'],
            'cost_group' => ['nullable', 'string', 'max:80'],
            'uom' => ['required', 'string', 'max:30'],
            'system_types' => ['nullable', 'array'],
            'system_types.*' => [Rule::in(array_keys($this->systemTypes()))],
            'project_scales' => ['nullable', 'array'],
            'project_scales.*' => [Rule::in(array_keys($this->projectScales()))],
            'nominal_power_wp' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'nominal_capacity_kwh' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'rated_power_kw' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'voltage_v' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'current_a' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'electrical_phase' => ['nullable', 'string', 'max:40'],
            'ip_rating' => ['nullable', 'string', 'max:40'],
            'warranty_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'default_scope_mode' => ['required', Rule::in(array_keys($this->scopeModes()))],
            'catalog_status' => ['nullable', Rule::in(['draft', 'needs_review', 'approved', 'rejected', 'archived'])],
            'compliance_status' => ['nullable', Rule::in(['needs_review', 'compliant', 'non_compliant', 'expired_document'])],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'technical_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['system_types'] = $data['system_types'] ?? [];
        $data['project_scales'] = $data['project_scales'] ?? [];
        $data['catalog_status'] = $data['catalog_status'] ?? ($material?->catalog_status ?? 'draft');
        $data['compliance_status'] = $data['compliance_status'] ?? ($material?->compliance_status ?? 'needs_review');

        return $data;
    }

    private function systemTypes(): array
    {
        return [
            'on_grid' => 'On-grid',
            'hybrid' => 'Hybrid',
            'off_grid' => 'Off-grid',
        ];
    }

    private function projectScales(): array
    {
        return [
            'residential' => 'Residential',
            'commercial' => 'Commercial',
        ];
    }

    private function scopeModes(): array
    {
        return [
            'base_boq' => 'Base BOQ',
            'summary_cost' => 'Summary Cost',
            'catalog_candidate' => 'Catalog Candidate',
            'optional_add_on' => 'Optional Add-on',
            'utility_responsibility' => 'Utility Responsibility',
            'owner_responsibility' => 'Owner Responsibility',
            'not_applicable' => 'Not Applicable',
        ];
    }
}
