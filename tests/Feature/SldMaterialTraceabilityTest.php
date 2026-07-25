<?php

namespace Tests\Feature;

use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\User;
use App\Services\Sld\SldDocumentControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SldMaterialTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_control_reads_product_snapshot_from_pricing_source_trace(): void
    {
        [$user, $company, $project, $material] = $this->projectFixture();
        $datasheet = $material->datasheets()->create([
            'title' => 'Current catalog datasheet',
            'revision' => 'Catalog Rev. 4',
            'issued_at' => today()->toDateString(),
            'is_active' => true,
            'uploaded_by' => $user->getKey(),
        ]);

        $bom = $this->bom($company, $project, $user);
        $bom->items()->create([
            'category' => 'pv_module',
            'item_code' => 'PV-MODULE',
            'item_name' => 'Solar PV Module',
            'quantity' => 20,
            'unit' => 'pcs',
            'metadata' => [
                'pricing_source' => 'company_price_book',
                'pricing_source_trace' => [
                    'material_id' => $material->getKey(),
                    'material_sku' => 'SNAP-PV-550',
                    'material_name' => 'Snapshot PV Module',
                    'material_brand' => 'Snapshot Solar',
                    'material_model' => 'SNAP-550',
                    'material_catalog_status' => 'approved',
                    'material_compliance_status' => 'compliant',
                    'material_datasheet_id' => $datasheet->getKey(),
                    'material_datasheet_revision' => 'Snapshot Rev. 2',
                    'material_datasheet_sha256' => str_repeat('b', 64),
                ],
            ],
        ]);

        $metadata = app(SldDocumentControl::class)->build($project->fresh(), 1, $user);
        $item = collect(data_get($metadata, 'equipment_register'))
            ->firstWhere('group', 'pv_module')['items'][0];

        $this->assertSame($material->getKey(), $item['material_id']);
        $this->assertSame('SNAP-PV-550', $item['material_sku']);
        $this->assertSame('Snapshot PV Module', $item['material_name']);
        $this->assertSame('Snapshot Solar', $item['brand']);
        $this->assertSame('SNAP-550', $item['model']);
        $this->assertSame($datasheet->getKey(), $item['datasheet_id']);
        $this->assertSame('Snapshot Rev. 2', $item['datasheet_revision']);
        $this->assertSame(str_repeat('b', 64), $item['datasheet_sha256']);
        $this->assertSame('APP67N.1', $item['traceability_version']);
        $this->assertSame('pricing_source_trace', $item['traceability_source']);
        $this->assertSame('APP67N.1', data_get($metadata, 'source_links.equipment_traceability_version'));
        $this->assertTrue(data_get($metadata, 'source_links.material_catalog_linked'));
        $this->assertSame(1, data_get($metadata, 'source_links.product_models_linked'));
        $this->assertSame(1, data_get($metadata, 'source_links.datasheets_linked'));
    }

    public function test_existing_pricing_trace_falls_back_to_current_catalog_identity_and_datasheet(): void
    {
        [$user, $company, $project, $material] = $this->projectFixture();
        $datasheet = $material->datasheets()->create([
            'title' => 'Fallback catalog datasheet',
            'revision' => 'Rev. 1',
            'issued_at' => today()->toDateString(),
            'is_active' => true,
            'uploaded_by' => $user->getKey(),
        ]);

        $bom = $this->bom($company, $project, $user);
        $bom->items()->create([
            'category' => 'pv_module',
            'item_code' => 'PV-MODULE',
            'item_name' => 'Solar PV Module',
            'quantity' => 20,
            'unit' => 'pcs',
            'metadata' => [
                'pricing_source_trace' => [
                    'material_id' => $material->getKey(),
                    'material_name' => $material->name,
                ],
            ],
        ]);

        $metadata = app(SldDocumentControl::class)->build($project->fresh(), 1, $user);
        $item = collect(data_get($metadata, 'equipment_register'))
            ->firstWhere('group', 'pv_module')['items'][0];

        $this->assertSame('Catalog Solar', $item['brand']);
        $this->assertSame('CAT-550', $item['model']);
        $this->assertSame($datasheet->getKey(), $item['datasheet_id']);
        $this->assertSame('Rev. 1', $item['datasheet_revision']);
        $this->assertSame('approved', $item['material_catalog_status']);
        $this->assertSame('compliant', $item['material_compliance_status']);
    }

    /** @return array{User, Company, Project, Material} */
    private function projectFixture(): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $company = Company::query()->create([
            'name' => 'NEXORIN Traceability Energy',
            'slug' => 'nexorin-traceability-energy',
            'code' => 'NXRTR',
            'status' => 'active',
            'created_by' => $user->getKey(),
        ]);
        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_number' => 'CUS-TRACE-001',
            'name' => 'Traceability Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $user->getKey(),
        ]);
        $project = Project::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => 'NXR-TRACE-001',
            'name' => 'SLD Traceability Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Palembang',
            'status' => 'engineering',
            'created_by' => $user->getKey(),
        ]);
        $category = MaterialCategory::query()->create([
            'code' => 'PV_MODULE_TRACE',
            'name' => 'PV Module Trace',
            'is_active' => true,
        ]);
        $material = Material::query()->create([
            'company_id' => $company->getKey(),
            'material_category_id' => $category->getKey(),
            'sku' => 'CAT-PV-550',
            'name' => 'Catalog PV Module',
            'brand' => 'Catalog Solar',
            'model' => 'CAT-550',
            'material_type' => 'PV module',
            'uom' => 'pcs',
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'is_active' => true,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        return [$user, $company, $project, $material];
    }

    private function bom(Company $company, Project $project, User $user): BillOfMaterial
    {
        return BillOfMaterial::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'bom_number' => 'BOM-TRACE-'.$project->getKey(),
            'status' => 'generated',
            'pricing_status' => 'priced',
            'generated_by' => $user->getKey(),
            'generated_at' => now(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);
    }
}
