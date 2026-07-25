<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Services\Catalog\DatasheetUploadPolicy;
use App\Services\Catalog\MaterialCatalogCompletenessEvaluator;
use App\Services\Catalog\MaterialSpecificationSynchronizer;
use App\Support\ProfessionalCatalogProfile;
use App\Support\ProfessionalSpecificationSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
            'governance' => ['nullable', Rule::in(['all', 'draft', 'needs_review', 'approved', 'rejected'])],
            'evidence' => ['nullable', Rule::in(['all', 'datasheet', 'certificate', 'document', 'price'])],
            'sort' => ['nullable', Rule::in(['name', 'sku', 'brand', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $categoryId = $validated['category'] ?? null;
        $systemType = $validated['system_type'] ?? 'all';
        $scope = $validated['scope'] ?? 'all';
        $status = $validated['status'] ?? 'all';
        $governance = $validated['governance'] ?? 'all';
        $evidence = $validated['evidence'] ?? 'all';
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        $categoryIds = [];
        if ($categoryId) {
            $selectedCategory = MaterialCategory::query()
                ->visibleForCompany($company)
                ->with('children:id,parent_id')
                ->find($categoryId);
            if ($selectedCategory) {
                $categoryIds = $selectedCategory->children->pluck('id')->prepend($selectedCategory->getKey())->all();
            }
        }

        $materials = Material::query()
            ->visibleForCompany($company)
            ->with(['category:id,parent_id,name,code', 'category.parent:id,name,code', 'company:id,name'])
            ->withCount(['priceBookItems', 'datasheets', 'certificates', 'documents'])
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
            ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('material_category_id', $categoryIds))
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
            ->when($governance !== 'all', fn (Builder $query) => $query->where('catalog_status', $governance))
            ->when($evidence === 'datasheet', fn (Builder $query) => $query->whereHas('datasheets', fn (Builder $evidenceQuery) => $evidenceQuery->where('is_active', true)))
            ->when($evidence === 'certificate', fn (Builder $query) => $query->whereHas('certificates', fn (Builder $evidenceQuery) => $evidenceQuery->where('is_active', true)->where('verification_status', 'verified')))
            ->when($evidence === 'document', fn (Builder $query) => $query->whereHas('documents', fn (Builder $evidenceQuery) => $evidenceQuery->where('is_active', true)->where('verification_status', 'verified')))
            ->when($evidence === 'price', fn (Builder $query) => $query->whereHas('priceBookItems', fn (Builder $evidenceQuery) => $evidenceQuery->where('unit_cost', '>', 0)))
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
            'approved' => (clone $visibleCatalog)->where('catalog_status', 'approved')->count(),
            'needs_review' => (clone $visibleCatalog)->whereIn('catalog_status', ['draft', 'needs_review'])->count(),
            'priced' => (clone $visibleCatalog)->whereHas('priceBookItems', fn (Builder $query) => $query->where('unit_cost', '>', 0))->count(),
            'verified_evidence' => (clone $visibleCatalog)->whereHas('datasheets', fn (Builder $query) => $query->where('is_active', true)->whereIn('extraction_status', ['parsed', 'reviewed', 'applied']))->count(),
        ];

        $rootCategories = MaterialCategory::query()
            ->visibleForCompany($company)
            ->active()
            ->whereNull('parent_id')
            ->with(['children:id,parent_id,code,name,sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categoryToRoot = [];
        foreach ($rootCategories as $rootCategory) {
            $categoryToRoot[$rootCategory->getKey()] = $rootCategory->getKey();
            foreach ($rootCategory->children as $childCategory) {
                $categoryToRoot[$childCategory->getKey()] = $rootCategory->getKey();
            }
        }

        $portfolioMaterials = Material::query()
            ->visibleForCompany($company)
            ->whereIn('material_category_id', array_keys($categoryToRoot))
            ->get(['id', 'material_category_id', 'catalog_status'])
            ->groupBy(fn (Material $material) => $categoryToRoot[$material->material_category_id] ?? 0);

        $categoryPortfolio = $rootCategories->map(function (MaterialCategory $category) use ($portfolioMaterials): array {
            $domainMaterials = $portfolioMaterials->get($category->getKey(), collect());
            $total = $domainMaterials->count();
            $approved = $domainMaterials->where('catalog_status', 'approved')->count();

            return [
                'id' => $category->getKey(),
                'code' => $category->code,
                'name' => $category->name,
                'description' => $category->description,
                'subcategories' => $category->children->count(),
                'materials' => $total,
                'approved' => $approved,
                'readiness_percent' => $total > 0 ? (int) round(($approved / $total) * 100) : 0,
            ];
        })->values();

        return view('materials.index', [
            'company' => $company,
            'materials' => $materials,
            'categories' => $categories,
            'stats' => $stats,
            'rootCategories' => $rootCategories,
            'categoryPortfolio' => $categoryPortfolio,
            'professionalProfiles' => ProfessionalCatalogProfile::profiles(),
            'filters' => [
                'search' => $search,
                'category' => $categoryId,
                'system_type' => $systemType,
                'scope' => $scope,
                'status' => $status,
                'governance' => $governance,
                'evidence' => $evidence,
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
        $material = new Material([
            'uom' => 'pcs',
            'default_scope_mode' => 'catalog_candidate',
            'is_active' => true,
        ]);

        return view('materials.create', array_merge([
            'company' => $company,
            'material' => $material,
        ], $this->materialFormData($company, $material)));
    }

    /**
     * Store a company-specific material item.
     */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $payload = $this->validatedMaterialPayload($request, $company);

        $material = DB::transaction(function () use ($request, $company, $payload): Material {
            $data = $payload['material'];
            $data['company_id'] = $company->getKey();
            $data['created_by'] = $request->user()->getKey();
            $data['updated_by'] = $request->user()->getKey();
            $data['is_active'] = $request->boolean('is_active');
            $data['catalog_status'] = 'draft';
            $data['compliance_status'] = 'needs_review';

            $material = Material::query()->create($data);

            app(MaterialSpecificationSynchronizer::class)->sync(
                $material,
                $payload['category'],
                $payload['technical_specs']
            );

            return $material;
        });

        return redirect()
            ->route('companies.materials.show', [$company, $material->getKey()])
            ->with('success', 'Material catalog item and category-specific technical profile created successfully.');
    }

    /**
     * Display a material profile.
     */
    public function show(
        Company $company,
        string $material,
        MaterialCatalogCompletenessEvaluator $evaluator,
        DatasheetUploadPolicy $uploadPolicy
    ): View
    {
        $material = $this->findMaterial($company, $material);

        $material->load([
            'category:id,parent_id,name,code',
            'category.parent:id,name,code',
            'company:id,name',
            'specs',
            'datasheets.applications.applier:id,name',
            'datasheets.applications.rollbackActor:id,name',
            'certificates.uploader:id,name',
            'certificates.verifier:id,name',
            'documents.uploader:id,name',
            'documents.verifier:id,name',
            'priceBookItems.priceBook.supplier:id,name,supplier_code',
            'priceBookItems.supplier:id,name,supplier_code',
            'creator:id,name',
            'updater:id,name',
        ]);

        $specificationSchema = ProfessionalSpecificationSchema::for($material);

        return view('materials.show', [
            'company' => $company,
            'material' => $material,
            'catalogReadiness' => $evaluator->evaluate($material),
            'canModify' => (int) $material->company_id === (int) $company->getKey(),
            'catalogProfile' => [
                'name' => $specificationSchema['title'],
                'description' => $specificationSchema['description'],
                'specs' => $specificationSchema['required_specs'],
                'certificates' => $specificationSchema['required_certificates'],
                'documents' => $specificationSchema['required_documents'],
            ],
            'specificationSchema' => $specificationSchema,
            'documentTypes' => ProfessionalCatalogProfile::supportingDocumentLabels(),
            'datasheetUploadPolicy' => $uploadPolicy->summary(),
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

        $material->loadMissing(['category.parent', 'specs']);

        return view('materials.edit', array_merge([
            'company' => $company,
            'material' => $material,
        ], $this->materialFormData($company, $material)));
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

        $material->loadMissing(['category.parent', 'specs']);
        $previousSchemaKeys = array_merge(
            ProfessionalSpecificationSchema::requiredKeysFor($material),
            ProfessionalSpecificationSchema::optionalKeysFor($material)
        );
        $payload = $this->validatedMaterialPayload($request, $company, $material);

        DB::transaction(function () use ($request, $material, $payload, $previousSchemaKeys): void {
            $data = $payload['material'];
            $data['updated_by'] = $request->user()->getKey();
            $data['is_active'] = $request->boolean('is_active');

            $material->fill($data);
            $baseChanged = $material->isDirty();
            $material->save();

            $specResult = app(MaterialSpecificationSynchronizer::class)->sync(
                $material,
                $payload['category'],
                $payload['technical_specs'],
                $previousSchemaKeys
            );

            if (($baseChanged || $specResult['changed']) && $material->catalog_status === 'approved') {
                $material->update([
                    'catalog_status' => 'needs_review',
                    'compliance_status' => 'needs_review',
                    'approved_at' => null,
                    'approved_by' => null,
                    'last_reviewed_at' => now(),
                    'last_reviewed_by' => $request->user()->getKey(),
                ]);
            }
        });

        return redirect()
            ->route('companies.materials.show', [$company, $material->getKey()])
            ->with('success', 'Material identity and category-specific technical profile updated successfully.');
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
    public function archive(Request $request, Company $company, string $material): RedirectResponse
    {
        $material = $this->findMaterial($company, $material);

        if ((int) $material->company_id !== (int) $company->getKey()) {
            return back()->with('error', 'Global catalog items cannot be archived by this company.');
        }

        $material->forceFill(['is_active' => false])->save();
        $material->markForCatalogReview($request->user()->getKey());

        return back()->with('success', 'Material catalog item archived successfully.');
    }

    /**
     * Reactivate a company-specific material item.
     */
    public function activate(Request $request, Company $company, string $material): RedirectResponse
    {
        $material = $this->findMaterial($company, $material);

        if ((int) $material->company_id !== (int) $company->getKey()) {
            return back()->with('error', 'Global catalog items cannot be modified by this company.');
        }

        $material->forceFill(['is_active' => true])->save();
        $material->markForCatalogReview($request->user()->getKey());

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
            ->with('parent:id,name,code')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN id ELSE parent_id END')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'code', 'company_id']);
    }

    /**
     * @return array{material:array<string,mixed>,technical_specs:array<string,mixed>,category:?MaterialCategory}
     */
    private function validatedMaterialPayload(Request $request, Company $company, ?Material $material = null): array
    {
        $skuRule = Rule::unique('materials', 'sku')
            ->where(fn ($query) => $query->where('company_id', $company->getKey()));

        if ($material) {
            $skuRule->ignore($material->getKey());
        }

        $category = null;
        if ($request->filled('material_category_id')) {
            $category = MaterialCategory::query()
                ->visibleForCompany($company)
                ->active()
                ->with(['parent', 'children:id,parent_id'])
                ->whereKey($request->integer('material_category_id'))
                ->first();
        }

        $schema = ProfessionalSpecificationSchema::for($category);
        $technicalFields = collect($schema['groups'])
            ->flatMap(fn (array $group): array => $group['fields'])
            ->keyBy('key');

        $rules = [
            'material_category_id' => [
                'nullable',
                'integer',
                Rule::exists('material_categories', 'id')->where(function ($query) use ($company) {
                    $query->where('is_active', true)->where(function ($scopeQuery) use ($company): void {
                        $scopeQuery
                            ->whereNull('company_id')
                            ->orWhere('company_id', $company->getKey());
                    });
                }),
            ],
            'sku' => ['required', 'string', 'max:80', $skuRule],
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
            'suitable_roof_types' => ['nullable', 'array'],
            'suitable_roof_types.*' => [Rule::in(array_keys($this->roofTypes()))],
            'default_scope_mode' => ['required', Rule::in(array_keys($this->scopeModes()))],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'technical_notes' => ['nullable', 'string', 'max:2000'],
            'technical_specs' => ['nullable', 'array'],
        ];

        foreach ($technicalFields as $key => $field) {
            $rules['technical_specs.'.$key] = $this->technicalFieldRules($field);
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request, $category, $technicalFields, $material): void {
            if ($request->filled('material_category_id') && ! $category) {
                $validator->errors()->add('material_category_id', 'The selected material category is unavailable for this company.');
            }

            $isProfessionalDomain = $category
                && $category->parent_id === null
                && $category->children->isNotEmpty();
            $editingSameLegacyDomain = $material
                && (int) $material->material_category_id === (int) $category?->getKey();

            if ($isProfessionalDomain && ! $editingSameLegacyDomain) {
                $validator->errors()->add(
                    'material_category_id',
                    'Select a specific professional component subcategory so the correct technical schema can be applied.'
                );
            }

            $submittedKeys = array_keys((array) $request->input('technical_specs', []));
            $unknown = array_values(array_diff($submittedKeys, $technicalFields->keys()->all()));
            if ($unknown !== []) {
                $validator->errors()->add('technical_specs', 'Unsupported technical fields were submitted for the selected subcategory: '.implode(', ', $unknown).'.');
            }
        });

        $validated = $validator->validate();
        $technicalSpecs = collect($validated['technical_specs'] ?? [])
            ->only($technicalFields->keys())
            ->all();

        unset($validated['technical_specs']);
        $validated['system_types'] = $validated['system_types'] ?? [];
        $validated['project_scales'] = $validated['project_scales'] ?? [];
        $validated['suitable_roof_types'] = $validated['suitable_roof_types'] ?? [];
        $validated['catalog_status'] = $material?->catalog_status ?? 'draft';
        $validated['compliance_status'] = $material?->compliance_status ?? 'needs_review';

        return [
            'material' => $validated,
            'technical_specs' => $technicalSpecs,
            'category' => $category,
        ];
    }

    /** @param array<string,mixed> $field */
    private function technicalFieldRules(array $field): array
    {
        $type = (string) ($field['input_type'] ?? 'text');
        $rules = ['nullable'];

        if ($type === 'number') {
            $rules[] = 'numeric';
            if (($field['min'] ?? null) !== null) {
                $rules[] = 'min:'.$field['min'];
            }
            if (($field['max'] ?? null) !== null) {
                $rules[] = 'max:'.$field['max'];
            }

            return $rules;
        }

        if ($type === 'date') {
            $rules[] = 'date';

            return $rules;
        }

        if ($type === 'select' && ($field['options'] ?? []) !== []) {
            $rules[] = Rule::in(array_map('strval', array_keys($field['options'])));

            return $rules;
        }

        $rules[] = 'string';
        $rules[] = $type === 'textarea' ? 'max:2000' : 'max:220';

        return $rules;
    }

    /** @return array<string,mixed> */
    private function materialFormData(Company $company, Material $material): array
    {
        $material->loadMissing(['category.parent', 'specs']);
        $categories = $this->categories($company);
        $selectedCategory = $material->category;
        $schema = ProfessionalSpecificationSchema::for($selectedCategory);

        return [
            'categories' => $categories,
            'systemTypes' => $this->systemTypes(),
            'projectScales' => $this->projectScales(),
            'roofTypes' => $this->roofTypes(),
            'scopeModes' => $this->scopeModes(),
            'catalogProfiles' => ProfessionalCatalogProfile::profilePayload(),
            'specificationSchemas' => ProfessionalSpecificationSchema::payload(),
            'initialSpecificationSchema' => $schema,
            'technicalSpecValues' => array_replace(
                ProfessionalSpecificationSchema::legacySpecValues($material),
                $material->specs->pluck('spec_value', 'spec_key')->all()
            ),
        ];
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

    private function roofTypes(): array
    {
        return [
            'pitched' => 'Pitched Roof',
            'flat' => 'Flat Roof',
            'canopy' => 'Canopy / Carport',
            'other' => 'Other Roof / Site Context',
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
