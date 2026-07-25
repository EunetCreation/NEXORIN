<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialSpec;
use App\Support\MaterialCatalogStandards;
use App\Support\ProfessionalSpecificationSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaterialSpecController extends Controller
{
    public function store(Request $request, Company $company, string $material): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);

        $validated = $request->validate([
            'spec_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'spec_label' => ['nullable', 'string', 'max:140'],
            'spec_value' => ['required', 'string', 'max:220'],
            'spec_unit' => ['nullable', 'string', 'max:40'],
            'is_filterable' => ['nullable', 'boolean'],
        ]);

        $key = strtolower(trim($validated['spec_key']));
        $this->assertCustomSpecificationKey($key);

        MaterialSpec::query()->updateOrCreate(
            ['material_id' => $material->getKey(), 'spec_key' => $key],
            [
                'spec_label' => ($validated['spec_label'] ?? null) ?: MaterialCatalogStandards::labelFor($key),
                'spec_value' => trim($validated['spec_value']),
                'spec_unit' => $validated['spec_unit'] ?? null,
                'sort_order' => $this->sortOrderFor($material, $key),
                'is_filterable' => $request->boolean('is_filterable'),
            ]
        );

        $this->touchGovernance($request, $material);

        return back()->with('success', 'Technical specification recorded and linked to the material audit context.');
    }

    public function destroy(Request $request, Company $company, string $material, MaterialSpec $spec): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);
        abort_unless((int) $spec->material_id === (int) $material->getKey(), 404);
        $this->assertCustomSpecificationKey((string) $spec->spec_key);

        $spec->delete();
        $this->touchGovernance($request, $material);

        return back()->with('success', 'Technical specification removed. Catalog readiness has been recalculated.');
    }

    private function sortOrderFor(Material $material, string $key): int
    {
        $required = MaterialCatalogStandards::requiredSpecKeysFor($material);
        $index = array_search($key, $required, true);

        return $index === false ? 900 : ((int) $index + 1) * 10;
    }

    private function touchGovernance(Request $request, Material $material): void
    {
        $material->markForCatalogReview($request->user()->getKey());
    }

    private function assertCustomSpecificationKey(string $key): void
    {
        if (in_array($key, ProfessionalSpecificationSchema::managedKeys(), true)) {
            throw ValidationException::withMessages([
                'spec_key' => 'This key is reserved by the professional component schema. Maintain it through Edit technical profile or the governed datasheet parser review.',
            ]);
        }
    }

    private function findCompanyMaterial(Company $company, string $materialId): Material
    {
        $material = Material::query()->visibleForCompany($company)->whereKey($materialId)->firstOrFail();
        abort_if((int) $material->company_id !== (int) $company->getKey(), 403, 'Global catalog items are read-only for this company.');

        return $material;
    }
}
