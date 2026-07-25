<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialCertificate;
use App\Models\MaterialDocument;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProfessionalCatalogTaxonomySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfessionalMaterialCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $superAdmin;
    private User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RbacSeeder::class, ProfessionalCatalogTaxonomySeeder::class]);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $systemRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $this->superAdmin->systemRoles()->attach($systemRole, ['assigned_by' => $this->superAdmin->id]);

        $this->company = Company::query()->create([
            'name' => 'NEXORIN Energy',
            'slug' => 'nexorin-energy',
            'code' => 'NXR',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->companyAdmin = $this->createCompanyUser('company_admin');
    }

    public function test_professional_taxonomy_seeds_fifteen_master_catalogs_and_subcategories(): void
    {
        $this->assertSame(15, MaterialCategory::query()->whereNull('parent_id')->count());
        $this->assertGreaterThanOrEqual(60, MaterialCategory::query()->whereNotNull('parent_id')->count());
        $this->assertDatabaseHas('material_categories', ['code' => 'PANEL_DISTRIBUTION', 'name' => 'Panel & Distribution']);
        $this->assertDatabaseHas('material_categories', ['code' => 'INV_HYBRID', 'name' => 'Hybrid Inverter']);
        $this->assertDatabaseHas('material_categories', ['code' => 'OM_SERVICE', 'name' => 'Annual O&M Service']);
    }


    public function test_legacy_power_conversion_material_is_migrated_without_losing_the_record(): void
    {
        $legacy = MaterialCategory::query()->create([
            'code' => 'POWER_CONVERSION',
            'name' => 'Power Conversion',
            'sort_order' => 900,
            'is_active' => true,
        ]);

        $material = Material::query()->create([
            'company_id' => $this->company->id,
            'material_category_id' => $legacy->id,
            'sku' => 'LEGACY-HYB-001',
            'name' => 'Legacy Hybrid Inverter',
            'brand' => 'Reference',
            'model' => 'HYB-5K',
            'material_type' => 'Hybrid inverter',
            'uom' => 'pcs',
            'system_types' => ['hybrid'],
            'is_active' => true,
        ]);

        $this->seed(ProfessionalCatalogTaxonomySeeder::class);

        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertSame(
            MaterialCategory::query()->where('code', 'INVERTER_HYBRID_OFFGRID')->value('id'),
            $material->fresh()->material_category_id
        );
    }

    public function test_company_admin_can_register_verify_and_archive_certificate_evidence(): void
    {
        Storage::fake('local');
        $material = $this->createMaterial('PV_MODULE');

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.certificates.store', [$this->company, $material]), [
                'certificate_type' => 'IEC 61215',
                'title' => 'IEC 61215 Design Qualification',
                'certificate_number' => 'CERT-61215-2026',
                'issuer' => 'TUV Rheinland',
                'issued_at' => '2026-01-10',
                'expires_at' => '2031-01-10',
                'certificate_file' => UploadedFile::fake()->create('certificate.pdf', 150, 'application/pdf'),
            ])
            ->assertRedirect();

        $certificate = MaterialCertificate::query()->firstOrFail();
        $this->assertSame('pending', $certificate->verification_status);
        Storage::disk('local')->assertExists($certificate->file_path);

        $this->actingAs($this->companyAdmin)
            ->patch(route('companies.materials.certificates.verify', [$this->company, $material, $certificate]), [
                'verification_status' => 'verified',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('material_certificates', [
            'id' => $certificate->id,
            'verification_status' => 'verified',
            'verified_by' => $this->companyAdmin->id,
        ]);

        $this->actingAs($this->companyAdmin)
            ->delete(route('companies.materials.certificates.archive', [$this->company, $material, $certificate]))
            ->assertRedirect();

        $this->assertDatabaseHas('material_certificates', ['id' => $certificate->id, 'is_active' => false]);
    }

    public function test_company_admin_can_register_and_verify_supporting_document(): void
    {
        Storage::fake('local');
        $material = $this->createMaterial('PV_MODULE');

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.documents.store', [$this->company, $material]), [
                'document_type' => 'warranty',
                'title' => 'Product Warranty Statement',
                'revision' => 'Rev. 2',
                'issued_at' => '2026-02-01',
                'document_file' => UploadedFile::fake()->create('warranty.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = MaterialDocument::query()->firstOrFail();
        $this->assertSame('pending', $document->verification_status);

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.materials.documents.source', [$this->company, $material, $document]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($this->companyAdmin)
            ->patch(route('companies.materials.documents.verify', [$this->company, $material, $document]), [
                'verification_status' => 'verified',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('material_documents', [
            'id' => $document->id,
            'document_type' => 'warranty',
            'verification_status' => 'verified',
            'verified_by' => $this->companyAdmin->id,
        ]);
    }


    public function test_company_admin_can_normalize_and_remove_manual_specification(): void
    {
        $material = $this->createMaterial('PV_MODULE');

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.specs.store', [$this->company, $material]), [
                'spec_key' => 'vendor_glass_coating',
                'spec_label' => 'Vendor Glass Coating',
                'spec_value' => 'Anti-reflective',
                'is_filterable' => '1',
            ])
            ->assertRedirect();

        $spec = $material->specs()->firstOrFail();
        $this->assertSame('Vendor Glass Coating', $spec->spec_label);
        $this->assertTrue($spec->is_filterable);

        $this->actingAs($this->companyAdmin)
            ->delete(route('companies.materials.specs.destroy', [$this->company, $material, $spec]))
            ->assertRedirect();

        $this->assertDatabaseMissing('material_specs', ['id' => $spec->id]);
    }

    public function test_additional_specification_cannot_override_or_delete_a_schema_managed_field(): void
    {
        $material = $this->createMaterial('PV_MODULE');
        $schemaSpec = $material->specs()->create([
            'spec_key' => 'module_power_wp',
            'spec_label' => 'Module Power',
            'spec_value' => '550',
            'spec_unit' => 'Wp',
            'sort_order' => 10,
            'is_filterable' => true,
        ]);

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.specs.store', [$this->company, $material]), [
                'spec_key' => 'module_power_wp',
                'spec_value' => '999',
                'spec_unit' => 'Wp',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('spec_key');

        $this->assertDatabaseHas('material_specs', [
            'id' => $schemaSpec->id,
            'spec_value' => '550',
        ]);

        $this->actingAs($this->companyAdmin)
            ->delete(route('companies.materials.specs.destroy', [$this->company, $material, $schemaSpec]))
            ->assertRedirect()
            ->assertSessionHasErrors('spec_key');

        $this->assertDatabaseHas('material_specs', ['id' => $schemaSpec->id]);
    }

    public function test_evidence_change_clears_stale_material_approval_metadata(): void
    {
        $material = $this->createMaterial('PV_MODULE');
        $material->update([
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_at' => now(),
            'approved_by' => $this->companyAdmin->id,
        ]);

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.documents.store', [$this->company, $material]), [
                'document_type' => 'warranty',
                'title' => 'Updated warranty evidence',
                'external_url' => 'https://example.com/updated-warranty.pdf',
            ])
            ->assertRedirect();

        $material->refresh();
        $this->assertSame('needs_review', $material->catalog_status);
        $this->assertSame('needs_review', $material->compliance_status);
        $this->assertNull($material->approved_at);
        $this->assertNull($material->approved_by);
        $this->assertSame($this->companyAdmin->id, $material->last_reviewed_by);
    }

    public function test_global_material_evidence_cannot_be_modified_by_company_admin(): void
    {
        $material = Material::query()->create([
            'company_id' => null,
            'material_category_id' => MaterialCategory::query()->where('code', 'PV_MODULE')->value('id'),
            'sku' => 'GLOBAL-PV-001',
            'name' => 'Global PV Reference',
            'brand' => 'Global Reference',
            'model' => 'G-550',
            'uom' => 'pcs',
            'is_active' => true,
        ]);

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.documents.store', [$this->company, $material]), [
                'document_type' => 'warranty',
                'title' => 'Unauthorized document',
                'external_url' => 'https://example.com/warranty.pdf',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('material_documents', 0);
    }

    private function createMaterial(string $categoryCode): Material
    {
        return Material::query()->create([
            'company_id' => $this->company->id,
            'material_category_id' => MaterialCategory::query()->where('code', $categoryCode)->value('id'),
            'sku' => 'NXR-MAT-'.str()->upper(str()->random(8)),
            'name' => 'Professional Test Material',
            'brand' => 'NEXORIN Reference',
            'model' => 'NX-2026',
            'material_type' => 'PV module',
            'uom' => 'pcs',
            'system_types' => ['on_grid', 'hybrid'],
            'project_scales' => ['residential', 'commercial'],
            'catalog_status' => 'draft',
            'compliance_status' => 'needs_review',
            'is_active' => true,
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);
    }

    private function createCompanyUser(string $roleCode): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $membership = CompanyMembership::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by' => $this->superAdmin->id,
            'joined_at' => now(),
        ]);
        $role = Role::query()->where('code', $roleCode)->where('scope', 'company')->firstOrFail();
        $membership->roles()->attach($role->id, ['assigned_by' => $this->superAdmin->id]);

        return $user;
    }
}
