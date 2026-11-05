<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialCertificate;
use App\Models\MaterialDatasheet;
use App\Models\MaterialDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\MaterialSpecificationSynchronizer;
use Database\Seeders\ProfessionalCatalogTaxonomySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Page022MaterialEditTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $companyAdmin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RbacSeeder::class, ProfessionalCatalogTaxonomySeeder::class]);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
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

        $this->companyAdmin = $this->createCompanyUser($this->company, 'company_admin');
    }

    private function createCompanyUser(Company $company, string $roleCode): User
    {
        $user = User::factory()->create(['status' => 'active']);

        $membership = CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
            'invited_by' => $this->superAdmin->getKey(),
            'joined_at' => now(),
        ]);

        $role = Role::query()
            ->where('code', $roleCode)
            ->where('scope', 'company')
            ->firstOrFail();

        $membership->roles()->attach($role->getKey(), [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);

        return $user;
    }

    private function createApprovedPersistedMaterial(MaterialCategory $category, User $creator): Material
    {
        $material = Material::query()->create([
            'company_id' => $this->company->getKey(),
            'material_category_id' => $category->getKey(),
            'sku' => 'TSM-NEG21C.20',
            'name' => 'Trina Vertex N TSM-NEG21C.20',
            'brand' => 'Trina Solar Co., Ltd.',
            'model' => 'TSM-NEG21C.20',
            'uom' => 'pcs',
            'material_type' => 'PV Module',
            'cost_group' => 'Main Equipment',
            'system_types' => ['on_grid', 'hybrid'],
            'project_scales' => ['commercial'],
            'suitable_roof_types' => ['pitched', 'flat'],
            'default_scope_mode' => 'base_boq',
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_at' => now()->subDay(),
            'approved_by' => $creator->getKey(),
            'is_active' => true,
            'description' => 'High-efficiency bifacial N-type TOPCon module.',
            'technical_notes' => 'IEC 61215 / IEC 61730 verified baseline.',
            'created_by' => $creator->getKey(),
            'updated_by' => $creator->getKey(),
        ]);

        app(MaterialSpecificationSynchronizer::class)->sync($material, $category, [
            'module_power_wp' => '620',
            'voc' => '49.8',
        ]);

        $material->forceFill([
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_at' => now()->subDay(),
            'approved_by' => $creator->getKey(),
        ])->save();

        return $material->fresh();
    }

    public function test_get_edit_renders_persisted_material_without_creating_record_and_enforces_company_and_permission_scope(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'External EPC Corp',
            'slug' => 'external-epc-corp',
            'code' => 'EPC',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $pvCategory = MaterialCategory::query()->where('code', 'EPM_PV_MOD_BIF')->firstOrFail();
        $viewerOnly = $this->createCompanyUser($this->company, 'viewer');
        $outsider = $this->createCompanyUser($otherCompany, 'company_admin');

        $material = $this->createApprovedPersistedMaterial($pvCategory, $this->companyAdmin);
        $countBefore = Material::query()->count();

        $response = $this->actingAs($this->companyAdmin)
            ->get(route('companies.materials.edit', [$this->company, $material]));

        $response->assertOk();
        $this->assertSame($countBefore, Material::query()->count());
        $response->assertSee('Edit Material');
        $response->assertSee('Trina Vertex N TSM-NEG21C.20');
        $response->assertSee('TSM-NEG21C.20');
        $response->assertSee('Change impact');
        $response->assertSee('Catalog media boundary');
        $response->assertDontSee('ProductRevision v');

        // Browser requests denied by EnsureCompanyPermission / EnsureCompanyAccess redirect to dashboard with error flash
        $this->actingAs($viewerOnly)
            ->get(route('companies.materials.edit', [$this->company, $material]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($outsider)
            ->get(route('companies.materials.edit', [$this->company, $material]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        // JSON requests return 403 Forbidden
        $this->actingAs($viewerOnly)
            ->getJson(route('companies.materials.edit', [$this->company, $material]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson(route('companies.materials.edit', [$this->company, $material]))
            ->assertForbidden();

        // Accessing a material that belongs to another company returns 404 Not Found
        $this->actingAs($outsider)
            ->get(route('companies.materials.edit', [$otherCompany, $material]))
            ->assertNotFound();
    }

    public function test_governance_affecting_mutation_invalidates_approval_while_preserving_creator_and_linked_evidence(): void
    {
        $pvCategory = MaterialCategory::query()->where('code', 'EPM_PV_MOD_BIF')->firstOrFail();
        $secondEditor = $this->createCompanyUser($this->company, 'company_admin');
        $material = $this->createApprovedPersistedMaterial($pvCategory, $this->companyAdmin);

        MaterialDatasheet::query()->create([
            'material_id' => $material->getKey(),
            'title' => 'TSM-NEG21C.20 Technical Datasheet',
            'original_filename' => 'TSM-NEG21C.20-Datasheet.pdf',
            'file_path' => 'datasheets/tsm-neg21c20.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 102400,
            'revision' => 'v1.0',
            'is_active' => true,
            'extraction_status' => 'parsed',
            'uploaded_by' => $this->companyAdmin->getKey(),
        ]);

        MaterialCertificate::query()->create([
            'material_id' => $material->getKey(),
            'certificate_type' => 'IEC 61215',
            'title' => 'IEC 61215 Design Qualification',
            'certificate_number' => 'CERT-IEC-61215-2026',
            'issuer' => 'TUV Rheinland',
            'is_active' => true,
            'verification_status' => 'verified',
            'uploaded_by' => $this->companyAdmin->getKey(),
        ]);

        MaterialDocument::query()->create([
            'material_id' => $material->getKey(),
            'document_type' => 'installation_manual',
            'title' => 'Vertex N Installation Manual',
            'original_filename' => 'Vertex-N-Manual.pdf',
            'file_path' => 'documents/vertex-n-manual.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 51200,
            'revision' => 'v2.1',
            'is_active' => true,
            'verification_status' => 'verified',
            'uploaded_by' => $this->companyAdmin->getKey(),
        ]);

        $response = $this->actingAs($secondEditor)
            ->put(route('companies.materials.update', [$this->company, $material]), [
                'material_category_id' => $pvCategory->getKey(),
                'sku' => $material->sku,
                'name' => $material->name,
                'brand' => $material->brand,
                'model' => $material->model,
                'uom' => $material->uom,
                'material_type' => $material->material_type,
                'cost_group' => $material->cost_group,
                'system_types' => $material->system_types,
                'project_scales' => $material->project_scales,
                'suitable_roof_types' => $material->suitable_roof_types,
                'default_scope_mode' => $material->default_scope_mode,
                'is_active' => '1',
                'description' => $material->description,
                'technical_notes' => $material->technical_notes,
                'technical_specs' => [
                    'module_power_wp' => '625',
                    'voc' => '49.8',
                ],
            ]);

        $response->assertRedirect(route('companies.materials.show', [$this->company, $material]));

        $material->refresh();
        $this->assertSame($this->companyAdmin->getKey(), $material->created_by);
        $this->assertSame($secondEditor->getKey(), $material->updated_by);
        $this->assertSame('needs_review', $material->catalog_status);
        $this->assertSame('needs_review', $material->compliance_status);
        $this->assertNull($material->approved_at);
        $this->assertNull($material->approved_by);
        $this->assertTrue($material->is_active);

        $this->assertSame(1, $material->datasheets()->count());
        $this->assertSame(1, $material->certificates()->count());
        $this->assertSame(1, $material->documents()->count());
        $this->assertSame('625', $material->specs()->where('spec_key', 'module_power_wp')->value('spec_value'));
    }

    public function test_administrative_only_mutation_preserves_existing_approval_status(): void
    {
        $pvCategory = MaterialCategory::query()->where('code', 'EPM_PV_MOD_BIF')->firstOrFail();
        $material = $this->createApprovedPersistedMaterial($pvCategory, $this->companyAdmin);

        $response = $this->actingAs($this->companyAdmin)
            ->put(route('companies.materials.update', [$this->company, $material]), [
                'material_category_id' => $pvCategory->getKey(),
                'sku' => $material->sku,
                'name' => $material->name,
                'brand' => $material->brand,
                'model' => $material->model,
                'uom' => $material->uom,
                'material_type' => 'Solar PV Module - Updated Admin Label',
                'cost_group' => 'Electrical BOS',
                'system_types' => $material->system_types,
                'project_scales' => $material->project_scales,
                'suitable_roof_types' => $material->suitable_roof_types,
                'default_scope_mode' => $material->default_scope_mode,
                'is_active' => '1',
                'description' => 'Updated commercial catalog summary without altering technical basis.',
                'technical_notes' => $material->technical_notes,
                'technical_specs' => [
                    'module_power_wp' => '620',
                    'voc' => '49.8',
                ],
            ]);

        $response->assertRedirect(route('companies.materials.show', [$this->company, $material]));

        $material->refresh();
        $this->assertSame('approved', $material->catalog_status);
        $this->assertSame('compliant', $material->compliance_status);
        $this->assertNotNull($material->approved_at);
        $this->assertSame('Solar PV Module - Updated Admin Label', $material->material_type);
        $this->assertSame('Electrical BOS', $material->cost_group);
    }

    public function test_category_switch_removes_stale_specs_and_rejects_unsupported_spec_keys(): void
    {
        $pvCategory = MaterialCategory::query()->where('code', 'EPM_PV_MOD_BIF')->firstOrFail();
        $cableCategory = MaterialCategory::query()->where('code', 'EPM_DC_CABLE')->firstOrFail();
        $material = $this->createApprovedPersistedMaterial($pvCategory, $this->companyAdmin);

        // 1. Unsupported spec key must fail validation
        $invalidResponse = $this->actingAs($this->companyAdmin)
            ->from(route('companies.materials.edit', [$this->company, $material]))
            ->put(route('companies.materials.update', [$this->company, $material]), [
                'material_category_id' => $cableCategory->getKey(),
                'sku' => $material->sku,
                'name' => $material->name,
                'uom' => 'm',
                'material_type' => 'DC Cable',
                'cost_group' => 'DC Cabling',
                'default_scope_mode' => 'base_boq',
                'technical_specs' => [
                    'module_power_wp' => '620',
                ],
            ]);

        $invalidResponse->assertRedirect(route('companies.materials.edit', [$this->company, $material]));
        $invalidResponse->assertSessionHasErrors('technical_specs');

        // 2. Valid category switch deletes old PV specs and stores only EPM_DC_CABLE specs
        $validSwitch = $this->actingAs($this->companyAdmin)
            ->put(route('companies.materials.update', [$this->company, $material]), [
                'material_category_id' => $cableCategory->getKey(),
                'sku' => 'CAB-PV1F-6MM',
                'name' => 'H1Z2Z2-K 1x6mm2 PV DC Cable',
                'brand' => 'Staubli / Helukabel',
                'model' => 'H1Z2Z2-K-6',
                'uom' => 'm',
                'material_type' => 'DC Cable',
                'cost_group' => 'DC Cabling',
                'system_types' => ['on_grid', 'hybrid'],
                'project_scales' => ['commercial'],
                'suitable_roof_types' => ['pitched'],
                'default_scope_mode' => 'base_boq',
                'is_active' => '1',
                'technical_specs' => [
                    'conductor_material' => 'Tinned Copper',
                    'cross_section_mm2' => '6',
                    'rated_voltage_v' => '1500',
                ],
            ]);

        $validSwitch->assertRedirect(route('companies.materials.show', [$this->company, $material]));

        $material->refresh();
        $this->assertSame($cableCategory->getKey(), $material->material_category_id);
        $this->assertSame('needs_review', $material->catalog_status);
        $this->assertNull($material->specs()->where('spec_key', 'module_power_wp')->first());
        $this->assertNull($material->specs()->where('spec_key', 'voc')->first());
        $this->assertSame('6', $material->specs()->where('spec_key', 'cross_section_mm2')->value('spec_value'));
    }
}
