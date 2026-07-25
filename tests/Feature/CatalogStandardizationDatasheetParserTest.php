<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialCertificate;
use App\Models\MaterialDatasheet;
use App\Models\MaterialDatasheetApplication;
use App\Models\MaterialDocument;
use App\Models\MaterialSpec;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\DatasheetParser;
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

        $file = UploadedFile::fake()->createWithContent('pv-module-datasheet.txt', $datasheetText);

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
            ->post(route('companies.materials.datasheets.apply', [$this->company, $material->id, $datasheet->id]), [
                'decisions' => [
                    'module_power_wp' => ['action' => 'accept', 'value' => '550', 'unit' => 'Wp'],
                    'voc' => ['action' => 'accept', 'value' => '49.9', 'unit' => 'V'],
                    'performance_warranty_years' => ['action' => 'accept', 'value' => '25', 'unit' => 'years'],
                    'product_warranty_years' => ['action' => 'accept', 'value' => '12', 'unit' => 'years'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

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

        $application = MaterialDatasheetApplication::query()->firstOrFail();
        $this->assertSame('applied', $application->status);
        $this->assertSame(4, collect($application->field_decisions)->where('action', 'accept')->count());
        $this->assertTrue((bool) data_get($application->field_decisions, 'voc.manually_corrected'));
        $this->assertSame('49.8', data_get($application->field_decisions, 'voc.parser_value'));
        $this->assertSame(1, data_get($application->field_decisions, 'voc.source_page'));
        $this->assertDatabaseHas('material_specs', [
            'material_id' => $material->id,
            'spec_key' => 'voc',
            'spec_value' => '49.9',
            'spec_unit' => 'V',
        ]);

        $this
            ->actingAs($admin)
            ->post(route('companies.materials.datasheets.apply', [$this->company, $material->id, $datasheet->id]), [
                'decisions' => [
                    'module_power_wp' => ['action' => 'accept', 'value' => '550', 'unit' => 'Wp'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('datasheet');

        $this->assertDatabaseCount('material_datasheet_applications', 1);

        $this
            ->actingAs($admin)
            ->delete(route('companies.materials.datasheets.archive', [$this->company, $material->id, $datasheet->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($datasheet->fresh()->is_active);

        $this
            ->actingAs($admin)
            ->patch(route('companies.materials.datasheets.applications.rollback', [
                $this->company,
                $material->id,
                $datasheet->id,
                $application->id,
            ]), ['rollback_note' => 'Regression rollback'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('material_specs', [
            'material_id' => $material->id,
            'spec_key' => 'module_power_wp',
        ]);
        $this->assertDatabaseHas('material_datasheet_applications', [
            'id' => $application->id,
            'status' => 'rolled_back',
            'rolled_back_by' => $admin->id,
        ]);
    }

    public function test_datasheet_registration_reparse_and_archive_invalidate_existing_approval(): void
    {
        Storage::fake('local');

        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);
        $this->approveMaterialState($material, $admin);

        $this->actingAs($admin)
            ->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
                'title' => 'Governance lifecycle datasheet',
                'datasheet_file' => UploadedFile::fake()->createWithContent('governance.txt', "Maximum Power Pmax: 550 W\nOpen Circuit Voltage Voc: 49.8 V"),
            ])
            ->assertRedirect();

        $datasheet = MaterialDatasheet::query()->firstOrFail();
        $this->assertApprovalWasInvalidated($material, $admin);

        $this->approveMaterialState($material, $admin);
        $this->actingAs($admin)
            ->post(route('companies.materials.datasheets.reparse', [$this->company, $material->id, $datasheet->id]))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertApprovalWasInvalidated($material, $admin);

        $this->approveMaterialState($material, $admin);
        $this->actingAs($admin)
            ->delete(route('companies.materials.datasheets.archive', [$this->company, $material->id, $datasheet->id]))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertApprovalWasInvalidated($material, $admin);
        $this->assertFalse($datasheet->fresh()->is_active);
    }

    public function test_php_upload_failure_returns_an_actionable_runtime_message(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);
        $invalidUpload = new UploadedFile(
            '',
            'oversized-vendor-datasheet.pdf',
            'application/pdf',
            UPLOAD_ERR_INI_SIZE,
            true,
        );

        $this
            ->actingAs($admin)
            ->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
                'title' => 'Rejected PHP upload',
                'datasheet_file' => $invalidUpload,
            ])
            ->assertRedirect()
            ->assertInvalid([
                'datasheet_file' => [
                    'upload_max_filesize',
                    'did not reach the parser',
                ],
            ]);

        $this->assertSame(0, MaterialDatasheet::query()->count());
    }

    public function test_duplicate_datasheet_fingerprint_is_rejected(): void
    {
        Storage::fake('local');

        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);
        $payload = "PV Module Datasheet\nPmax: 550 Wp\nVoc: 49.8 V";

        $this->actingAs($admin)->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
            'title' => 'Vendor datasheet A',
            'datasheet_file' => UploadedFile::fake()->createWithContent('vendor-a.txt', $payload),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
            'title' => 'Vendor datasheet duplicate',
            'datasheet_file' => UploadedFile::fake()->createWithContent('vendor-copy.txt', $payload),
        ])->assertRedirect()->assertSessionHasErrors('datasheet_file');

        $this->assertSame(1, MaterialDatasheet::query()->where('material_id', $material->id)->count());
    }

    public function test_category_mismatch_blocks_datasheet_application(): void
    {
        Storage::fake('local');

        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createBatteryCategory();
        $material = $this->createBatteryMaterial($category);
        $inverterText = <<<'TEXT'
Grid-tied String Inverter
Rated AC Output Power: 10 kW
Maximum DC Input Voltage: 1100 V
MPPT Voltage Range: 200-1000 V
Anti-islanding protection
Grid code profile IEC 61727
Number of MPPT: 2
TEXT;

        $this->actingAs($admin)->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
            'title' => 'Wrong inverter datasheet',
            'datasheet_file' => UploadedFile::fake()->createWithContent('wrong-inverter.txt', $inverterText),
        ])->assertRedirect();

        $datasheet = MaterialDatasheet::query()->firstOrFail();
        $this->assertSame('category_mismatch', $datasheet->extraction_status);
        $this->assertTrue((bool) data_get($datasheet->extraction_meta, 'category.blocked'));

        $this->actingAs($admin)->post(route('companies.materials.datasheets.apply', [$this->company, $material->id, $datasheet->id]), [
            'decisions' => [],
        ])->assertRedirect()->assertSessionHasErrors('datasheet');

        $this->assertSame(0, MaterialSpec::query()->where('material_id', $material->id)->count());
    }


    public function test_exact_component_mismatch_blocks_topcon_datasheet_on_mono_perc_material(): void
    {
        Storage::fake('local');

        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);
        $topconText = <<<'TEXT'
N-type i-TOPCon Ultra Dual Glass Monocrystalline PV Module
Maximum Power Pmax: 470 W
Open Circuit Voltage Voc: 54.60 V
Maximum Power Voltage Vmp: 46.10 V
Short Circuit Current Isc: 10.89 A
Maximum Power Current Imp: 10.20 A
Module Efficiency: 23.5%
TEXT;

        $this->actingAs($admin)->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
            'title' => 'TOPCon source on Mono PERC record',
            'datasheet_file' => UploadedFile::fake()->createWithContent('vertex-topcon.txt', $topconText),
        ])->assertRedirect()->assertSessionHas('success');

        $datasheet = MaterialDatasheet::query()->firstOrFail();
        $this->assertSame('category_mismatch', $datasheet->extraction_status);
        $this->assertSame('exact_component_mismatch', data_get($datasheet->extraction_meta, 'category.review_reason'));
        $this->assertSame('PV_MONO_PERC', data_get($datasheet->extraction_meta, 'category.expected_exact'));
        $this->assertSame('PV_TOPCON', data_get($datasheet->extraction_meta, 'category.detected_exact'));
        $this->assertTrue((bool) data_get($datasheet->extraction_meta, 'category.exact_blocked'));

        $this->actingAs($admin)->post(route('companies.materials.datasheets.apply', [$this->company, $material->id, $datasheet->id]), [
            'decisions' => [
                'module_power_wp' => ['action' => 'accept', 'value' => '470', 'unit' => 'Wp'],
            ],
        ])->assertRedirect()->assertSessionHasErrors('datasheet');

        $this->assertDatabaseMissing('material_specs', [
            'material_id' => $material->id,
            'spec_key' => 'module_power_wp',
        ]);
    }

    public function test_local_datasheet_can_be_reparsed_and_archived_evidence_cannot_be_applied(): void
    {
        Storage::fake('local');

        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);

        $this->actingAs($admin)->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
            'title' => 'Reparse governance datasheet',
            'datasheet_file' => UploadedFile::fake()->createWithContent(
                'reparse.txt',
                "PV Module Datasheet\nMaximum Power Pmax: 550 W\nOpen Circuit Voltage Voc: 49.8 V"
            ),
        ])->assertRedirect();

        $datasheet = MaterialDatasheet::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('companies.materials.datasheets.reparse', [$this->company, $material->id, $datasheet->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $datasheet->refresh();
        $this->assertSame(DatasheetParser::VERSION, $datasheet->parser_version);
        $this->assertArrayHasKey('module_power_wp', $datasheet->parsed_fields);

        $this->actingAs($admin)
            ->delete(route('companies.materials.datasheets.archive', [$this->company, $material->id, $datasheet->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)->post(route('companies.materials.datasheets.apply', [$this->company, $material->id, $datasheet->id]), [
            'decisions' => [
                'module_power_wp' => ['action' => 'accept', 'value' => '550', 'unit' => 'Wp'],
            ],
        ])->assertRedirect()->assertSessionHasErrors('datasheet');

        $this->assertDatabaseMissing('material_specs', [
            'material_id' => $material->id,
            'spec_key' => 'module_power_wp',
        ]);
    }


    public function test_multi_variant_datasheet_requires_exact_column_then_reparses_selected_variant(): void
    {
        Storage::fake('local');

        $admin = $this->createCompanyUser('company_admin');
        $category = $this->createPvCategory();
        $material = $this->createPvMaterial($category);
        $material->update([
            'name' => 'PV Module Family',
            'model' => 'PV-FAMILY',
            'sku' => 'PV-FAMILY',
            'nominal_power_wp' => null,
        ]);
        $table = <<<'TEXT'
PV Module
ELECTRICAL DATA (STC)
Peak Power Watts-PMAX(Wp)* 445 450 455 460 465 470
Maximum Power Voltage-VMPP (V) 44.30 44.60 45.00 45.40 45.80 46.10
Maximum Power Current-IMPP (A) 10.05 10.09 10.11 10.14 10.16 10.20
Open Circuit Voltage-VOC (V) 52.60 52.90 53.40 53.80 54.20 54.60
Short Circuit Current-ISC (A) 10.71 10.74 10.77 10.81 10.85 10.89
Module Efficiency (%) 22.3 22.5 22.8 23.0 23.3 23.5
ELECTRICAL DATA (NOCT)
TEXT;

        $this->actingAs($admin)->post(route('companies.materials.datasheets.store', [$this->company, $material->id]), [
            'title' => 'Multi-variant PV family',
            'datasheet_file' => UploadedFile::fake()->createWithContent('pv-family.txt', $table),
        ])->assertRedirect()->assertSessionHas('success');

        $datasheet = MaterialDatasheet::query()->firstOrFail();
        $this->assertSame('variant_selection_required', $datasheet->extraction_status);
        $this->assertTrue((bool) data_get($datasheet->extraction_meta, 'variant.blocked'));
        $this->assertArrayNotHasKey('voc', $datasheet->parsed_fields);

        $this->actingAs($admin)->post(route('companies.materials.datasheets.apply', [$this->company, $material->id, $datasheet->id]), [
            'decisions' => [],
        ])->assertRedirect()->assertSessionHasErrors('datasheet');

        $this->actingAs($admin)->post(route('companies.materials.datasheets.reparse', [$this->company, $material->id, $datasheet->id]), [
            'variant_power_wp' => 470,
        ])->assertRedirect()->assertSessionHas('success');

        $datasheet->refresh();
        $this->assertSame('parsed', $datasheet->extraction_status);
        $this->assertFalse((bool) data_get($datasheet->extraction_meta, 'variant.blocked'));
        $this->assertSame(470.0, (float) data_get($datasheet->extraction_meta, 'variant.selected_variant'));
        $this->assertSame('470', data_get($datasheet->parsed_fields, 'module_power_wp.value'));
        $this->assertSame('54.6', data_get($datasheet->parsed_fields, 'voc.value'));
        $this->assertSame('10.89', data_get($datasheet->parsed_fields, 'isc.value'));
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
            'module_efficiency_percent' => ['21.3', '%'],
            'temp_coeff_voc' => ['-0.28', '%'],
            'temp_coeff_pmax' => ['-0.35', '%'],
            'length_mm' => ['2279', 'mm'],
            'width_mm' => ['1134', 'mm'],
            'weight_kg' => ['28.5', 'kg'],
            'cell_type' => ['Mono PERC', null],
            'cell_count' => ['144', 'cells'],
            'bifacial' => ['No', null],
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

        foreach (['warranty', 'installation_manual'] as $documentType) {
            MaterialDocument::query()->create([
                'material_id' => $material->id,
                'document_type' => $documentType,
                'title' => str($documentType)->replace('_', ' ')->title(),
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
        $root = MaterialCategory::query()->create([
            'code' => 'PV_MODULE',
            'name' => 'PV Module',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        return MaterialCategory::query()->create([
            'parent_id' => $root->id,
            'code' => 'PV_MONO_PERC',
            'name' => 'Mono PERC Module',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    private function approveMaterialState(Material $material, User $actor): void
    {
        $material->forceFill([
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ])->save();
    }

    private function assertApprovalWasInvalidated(Material $material, User $actor): void
    {
        $material->refresh();
        $this->assertSame('needs_review', $material->catalog_status);
        $this->assertSame('needs_review', $material->compliance_status);
        $this->assertNull($material->approved_at);
        $this->assertNull($material->approved_by);
        $this->assertSame($actor->id, $material->last_reviewed_by);
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

    private function createBatteryCategory(): MaterialCategory
    {
        $root = MaterialCategory::query()->create([
            'code' => 'BATTERY_ESS',
            'name' => 'Battery & ESS',
            'sort_order' => 40,
            'is_active' => true,
        ]);

        return MaterialCategory::query()->create([
            'parent_id' => $root->id,
            'code' => 'BAT_RACK',
            'name' => 'Rack Battery Module',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    private function createBatteryMaterial(MaterialCategory $category): Material
    {
        return Material::query()->create([
            'company_id' => $this->company->id,
            'material_category_id' => $category->id,
            'sku' => 'BAT-RACK-UAT',
            'name' => 'LFP Rack Battery 5.12 kWh',
            'brand' => 'Reference Storage',
            'model' => 'RS-LFP-100',
            'material_type' => 'Battery module',
            'cost_group' => 'Material',
            'uom' => 'pcs',
            'system_types' => ['hybrid', 'off_grid'],
            'project_scales' => ['residential', 'commercial'],
            'default_scope_mode' => 'catalog_candidate',
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
