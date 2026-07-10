<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialDatasheet;
use App\Models\MaterialSpec;
use App\Services\Catalog\DatasheetParser;
use App\Support\MaterialCatalogStandards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaterialDatasheetController extends Controller
{
    public function store(Request $request, Company $company, string $material, DatasheetParser $parser): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'datasheet_file' => ['required_without:external_url', 'nullable', 'file', 'max:10240'],
            'external_url' => ['required_without:datasheet_file', 'nullable', 'url', 'max:255'],
            'revision' => ['nullable', 'string', 'max:60'],
            'issued_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $filePath = null;
        $originalFilename = null;
        $mimeType = null;
        $fileSize = null;
        $parseResult = [
            'status' => 'pending',
            'text' => '',
            'fields' => [],
            'confidence' => 0,
            'warnings' => ['No file was uploaded. External URL requires manual review.'],
            'parser_version' => MaterialCatalogStandards::PARSER_VERSION,
        ];

        if ($request->hasFile('datasheet_file')) {
            $file = $request->file('datasheet_file');
            $filePath = $file->store("material-datasheets/{$company->getKey()}/{$material->getKey()}");
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $fileSize = $file->getSize();
            $parseResult = $parser->parseUploadedFile($file, $material);
        }

        $datasheet = MaterialDatasheet::query()->create([
            'material_id' => $material->getKey(),
            'title' => $validated['title'],
            'original_filename' => $originalFilename,
            'file_path' => $filePath,
            'external_url' => $validated['external_url'] ?? null,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'revision' => $validated['revision'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'is_active' => true,
            'extraction_status' => $parseResult['status'],
            'parser_version' => $parseResult['parser_version'],
            'parse_confidence' => $parseResult['confidence'],
            'parsed_text' => $parseResult['text'],
            'parsed_fields' => $parseResult['fields'],
            'parse_warnings' => $parseResult['warnings'],
            'parsed_at' => $request->hasFile('datasheet_file') ? now() : null,
            'uploaded_by' => $request->user()->getKey(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $message = $datasheet->extraction_status === 'parsed'
            ? 'Datasheet uploaded and parsed. Review parsed fields before applying them to the catalog item.'
            : 'Datasheet uploaded. Manual review is required before it can be used as catalog evidence.';

        return redirect()
            ->route('companies.materials.show', [$company, $material->getKey()])
            ->with('success', $message);
    }

    public function applyParsedSpecs(Request $request, Company $company, string $material, MaterialDatasheet $datasheet): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);

        abort_unless((int) $datasheet->material_id === (int) $material->getKey(), 404);

        $fields = $datasheet->parsed_fields ?? [];

        if ($fields === []) {
            return back()->with('error', 'This datasheet has no parsed fields to apply.');
        }

        foreach ($fields as $key => $field) {
            $value = (string) ($field['value'] ?? '');

            if ($value === '') {
                continue;
            }

            MaterialSpec::query()->updateOrCreate(
                [
                    'material_id' => $material->getKey(),
                    'spec_key' => (string) $key,
                ],
                [
                    'spec_label' => (string) ($field['label'] ?? MaterialCatalogStandards::labelFor((string) $key)),
                    'spec_value' => $value,
                    'spec_unit' => $field['unit'] ?? null,
                    'sort_order' => $this->sortOrderFor((string) $key),
                    'is_filterable' => true,
                ]
            );
        }

        $material->fill($this->coreMaterialFieldsFromParsedSpecs($fields));
        $material->last_reviewed_at = now();
        $material->last_reviewed_by = $request->user()->getKey();
        $material->compliance_status = 'needs_review';
        $material->save();

        $datasheet->update([
            'extraction_status' => 'applied',
            'reviewed_at' => $datasheet->reviewed_at ?? now(),
            'reviewed_by' => $datasheet->reviewed_by ?? $request->user()->getKey(),
            'applied_at' => now(),
            'applied_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Parsed datasheet fields applied to the material technical specifications.');
    }

    public function archive(Company $company, string $material, MaterialDatasheet $datasheet): RedirectResponse
    {
        $material = $this->findCompanyMaterial($company, $material);

        abort_unless((int) $datasheet->material_id === (int) $material->getKey(), 404);

        $datasheet->update(['is_active' => false]);

        return back()->with('success', 'Datasheet archived successfully.');
    }

    private function findCompanyMaterial(Company $company, string $materialId): Material
    {
        $material = Material::query()
            ->visibleForCompany($company)
            ->whereKey($materialId)
            ->firstOrFail();

        abort_if((int) $material->company_id !== (int) $company->getKey(), 403, 'Global catalog items are read-only for this company.');

        return $material;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function coreMaterialFieldsFromParsedSpecs(array $fields): array
    {
        $map = [
            'module_power_wp' => 'nominal_power_wp',
            'rated_ac_power_kw' => 'rated_power_kw',
            'capacity_kwh' => 'nominal_capacity_kwh',
            'max_system_voltage' => 'voltage_v',
            'max_dc_voltage' => 'voltage_v',
            'voc' => 'voltage_v',
            'imp' => 'current_a',
            'isc' => 'current_a',
            'ip_rating' => 'ip_rating',
            'product_warranty_years' => 'warranty_years',
            'warranty_years' => 'warranty_years',
        ];

        $coreFields = [];

        foreach ($map as $specKey => $materialField) {
            if (! isset($fields[$specKey]['value'])) {
                continue;
            }

            $value = $fields[$specKey]['value'];

            if (in_array($materialField, ['warranty_years'], true)) {
                $coreFields[$materialField] = (int) round((float) $value);
            } elseif (in_array($materialField, ['nominal_power_wp', 'rated_power_kw', 'nominal_capacity_kwh', 'voltage_v', 'current_a'], true)) {
                $coreFields[$materialField] = (float) $value;
            } else {
                $coreFields[$materialField] = (string) $value;
            }
        }

        return $coreFields;
    }

    private function sortOrderFor(string $key): int
    {
        $keys = array_keys(MaterialCatalogStandards::labels());
        $index = array_search($key, $keys, true);

        return $index === false ? 900 : ((int) $index + 1) * 10;
    }
}
