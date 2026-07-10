<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialCertificate;
use App\Models\MaterialDatasheet;
use App\Models\MaterialSpec;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogStandardizationDatasheetParserTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->superAdmin = User::factory()->create([
            'status' => 'active',
        ]);

        $superAdminRole = Role::query()
            ->where('code', 'super_admin')
            ->firstOrFail();

        $this->superAdmin->systemRoles()->attach($superAdminRole, [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);

        $this->company = Company::query()->create([
            'name' => 'NEXORIN Energy',
            'slug' => 'nexorin-energy',
            'code' => 'NXR',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);
    }

    public function test_company_admin_can_upload_parse_and_apply_datasheet_specs(): void
    {
        Storage::fake('local');

        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);

        $datasheetText = <<<'TEXT'
Maximum Power Pmax: 550 W
Module Efficiency: 21.3%
Open Circuit Voltage Voc: 49.8 V
Maximum Power Voltage Vmp: 41.9 V
Short Circuit Current Isc: 14.0 A
Maximum Power Current Imp: 13.13 A
Temp coefficient Voc: -0.28 %/C
Length: 2279 mm
Width: 1134 mm
Weight: 28.5 kg
Maximum system voltage: 1500 V
Product warranty: 12 years
Performance warranty: 25 years
TEXT;

        $file = UploadedFile::fake()->createWithContent('pv-module-datasheet.pdf', $datasheetText);

        $this
            ->actingAs($admin)
            ->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
                'title' => 'PV module 550 Wp datasheet',
                'datasheet_file' => $file,
                'revision' => 'Rev. A',
                'issued_at' => '2026-01-15',
            ])
            ->assertRedirect(route('companies.materials.show', [$this->company, $material->id]));

        $datasheet = MaterialDatasheet::query()->firstOrFail();

        $this->assertSame('parsed', $datasheet->extraction_status);
        $this->assertArrayHasKey('voc', $datasheet->parsed_fields);
        $this->assertSame('49.8', $datasheet->parsed_fields['voc']['value']);
        $this->assertArrayHasKey('module_power_wp', $datasheet->parsed_fields);

        $this
            ->actingAs($admin)
            ->post(route('companies.materials.datasheets.apply', [$this->company, $material->id, $datasheet->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('material_specs', [
            'material_id' => $material->id,
            'spec_key' => 'module_power_wp',
            'spec_value' => '550',
            'spec_unit' => 'Wp',
        ]);

        $this->assertDatabaseHas('material_specs', [
            'material_id' => $material->id,
            'spec_key' => 'performance_warranty_years',
            'spec_value' => '25',
            'spec_unit' => 'years',
        ]);

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'nominal_power_wp' => 550,
            'warranty_years' => 12,
        ]);
    }

    public function test_incomplete_material_cannot_be_approved(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);

        $this
            ->actingAs($admin)
            ->patch(route('companies.materials.approve', [$this->company, $material->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'catalog_status' => 'draft',
            'compliance_status' => 'needs_review',
        ]);
    }

    public function test_complete_material_can_be_approved(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);

        foreach ([
            'module_power_wp' => ['550', 'Wp'],
            'voc' => ['49.8', 'V'],
            'vmp' => ['41.9', 'V'],
            'isc' => ['14', 'A'],
            'imp' => ['13.13', 'A'],
            'temp_coeff_voc' => ['-0.28', '%'],
            'length_mm' => ['2279', 'mm'],
            'width_mm' => ['1134', 'mm'],
            'weight_kg' => ['28.5', 'kg'],
            'max_system_voltage' => ['1500', 'V'],
            'product_warranty_years' => ['12', 'years'],
            'performance_warranty_years' => ['25', 'years'],
        ] as $key => [$value, $unit]) {
            MaterialSpec::query()->create([
                'material_id' => $material->id,
                'spec_key' => $key,
                'spec_label' => str($key)->replace('_', ' ')->title()->toString(),
                'spec_value' => $value,
                'spec_unit' => $unit,
                'sort_order' => 10,
                'is_filterable' => true,
            ]);
        }

        MaterialDatasheet::query()->create([
            'material_id' => $material->id,
            'title' => 'Approved text datasheet',
            'is_active' => true,
            'extraction_status' => 'parsed',
            'parser_version' => 'test',
            'parse_confidence' => 100,
            'parsed_fields' => ['module_power_wp' => ['value' => '550']],
            'uploaded_by' => $admin->id,
        ]);

        foreach (['IEC 61215', 'IEC 61730'] as $certificateType) {
            MaterialCertificate::query()->create([
                'material_id' => $material->id,
                'certificate_type' => $certificateType,
                'title' => $certificateType.' certificate',
                'is_active' => true,
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'uploaded_by' => $admin->id,
            ]);
        }

        $this
            ->actingAs($admin)
            ->patch(route('companies.materials.approve', [$this->company, $material->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_by' => $admin->id,
        ]);
    }

    private function createPvCategory(): MaterialCategory
    {
        return MaterialCategory::query()->create([
            'code' => 'PV_MODULE',
            'name' => 'PV Module',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    private function createPvMaterial(MaterialCategory $category): Material
    {
        return Material::query()->create([
            'company_id' => $this->company->id,
            'material_category_id' => $category->id,
            'sku' => 'PV-MOD-550-STD',
            'name' => 'Solar PV Module 550 Wp',
            'brand' => 'Reference Solar',
            'model' => 'RS-550',
            'material_type' => 'PV module',
            'cost_group' => 'Material',
            'uom' => 'pcs',
            'system_types' => ['on_grid', 'hybrid'],
            'project_scales' => ['residential', 'commercial'],
            'default_scope_mode' => 'base_boq',
            'catalog_status' => 'draft',
            'compliance_status' => 'needs_review',
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);
    }

    private function createCompanyUser(string $roleCode): User
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $membership = CompanyMembership::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by' => $this->superAdmin->id,
            'joined_at' => now(),
        ]);

        $role = Role::query()
            ->where('code', $roleCode)
            ->where('scope', 'company')
            ->firstOrFail();

        $membership->roles()->attach($role->id, [
            'assigned_by' => $this->superAdmin->id,
        ]);

        return $user;
    }
}
