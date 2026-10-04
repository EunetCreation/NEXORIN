<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialSpec;
use App\Models\Role;
use App\Models\User;
use App\Support\EngineeringProductMasterDictionary;
use App\Support\ProfessionalSpecificationSchema;
use Database\Seeders\ProfessionalCatalogTaxonomySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Page021MaterialCreateTest extends TestCase
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

    public function test_get_create_is_read_only_and_explains_the_draft_and_evidence_boundary(): void
    {
        $before = Material::query()->count();

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.materials.create', $this->company))
            ->assertOk()
            ->assertSee('Create Material')
            ->assertSee('Create Draft Material')
            ->assertSee('Evidence starts after creation')
            ->assertSee('Engineering catalog')
            ->assertSee('Select a component class')
            ->assertSee('89 exact classes')
            ->assertSee('Engineering component image')
            ->assertSee('Draft · Needs Review');

        $this->assertSame($before, Material::query()->count());
    }

    public function test_page_021_create_copy_does_not_expose_internal_engineering_implementation_language(): void
    {
        $response = $this->actingAs($this->companyAdmin)
            ->get(route('companies.materials.create', $this->company))
            ->assertOk();

        foreach ([
            'MASTER Section 55',
            'MaterialSpec',
            'ProductRevision authority',
            'backward compatibility',
            'Schema inspector',
            'Canonical engineering class',
            'persisted Material',
            'create request',
        ] as $internalPhrase) {
            $response->assertDontSee($internalPhrase);
        }
    }

    public function test_canonical_master_component_class_is_seeded_and_uses_exact_section_55_schema(): void
    {
        $category = MaterialCategory::query()->where('code', 'EPM_MCCB')->firstOrFail();
        $schema = ProfessionalSpecificationSchema::for($category);

        $this->assertSame(89, EngineeringProductMasterDictionary::count());
        $this->assertSame(89, MaterialCategory::query()->where('code', 'like', 'EPM_%')->count());
        $this->assertSame('55.MCCB', $schema['master_reference']);
        $this->assertContains('frame_a', $schema['required_specs']);
        $this->assertContains('icu_ka', $schema['required_specs']);
        $this->assertContains('trip_unit', $schema['required_specs']);
        $this->assertNotContains('general', collect($schema['groups'])->pluck('key')->all());
    }

    public function test_store_ignores_browser_owned_tenant_creator_and_approval_fields(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Other Solar Company',
            'slug' => 'other-solar-company',
            'code' => 'OTHER',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);
        $otherUser = User::factory()->create(['status' => 'active']);
        $category = MaterialCategory::query()->where('code', 'EPM_MCCB')->firstOrFail();

        $response = $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.store', $this->company), [
                'company_id' => $otherCompany->getKey(),
                'created_by' => $otherUser->getKey(),
                'updated_by' => $otherUser->getKey(),
                'catalog_status' => 'approved',
                'compliance_status' => 'compliant',
                'approved_by' => $otherUser->getKey(),
                'approved_at' => now()->toDateTimeString(),
                'material_category_id' => $category->getKey(),
                'sku' => 'PAGE021-SCOPE-001',
                'name' => 'Page 021 Scope Test',
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
                'is_active' => '1',
            ]);

        $material = Material::query()->where('sku', 'PAGE021-SCOPE-001')->firstOrFail();

        $response->assertRedirect(route('companies.materials.show', [$this->company, $material]));
        $this->assertSame($this->company->getKey(), $material->company_id);
        $this->assertSame($this->companyAdmin->getKey(), $material->created_by);
        $this->assertSame($this->companyAdmin->getKey(), $material->updated_by);
        $this->assertSame('draft', $material->catalog_status);
        $this->assertSame('needs_review', $material->compliance_status);
        $this->assertNull($material->approved_by);
        $this->assertNull($material->approved_at);
    }

    public function test_store_requires_an_exact_component_class_before_creating_a_draft(): void
    {
        $this->actingAs($this->companyAdmin)
            ->from(route('companies.materials.create', $this->company))
            ->post(route('companies.materials.store', $this->company), [
                'sku' => 'PAGE021-NO-CLASS-001',
                'name' => 'Missing Component Class',
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
            ])
            ->assertRedirect(route('companies.materials.create', $this->company))
            ->assertSessionHasErrors('material_category_id');

        $this->assertDatabaseMissing('materials', ['sku' => 'PAGE021-NO-CLASS-001']);
    }

    public function test_store_rejects_legacy_non_master_category_on_create(): void
    {
        $category = MaterialCategory::query()->where('code', 'PV_TOPCON')->firstOrFail();

        $this->actingAs($this->companyAdmin)
            ->from(route('companies.materials.create', $this->company))
            ->post(route('companies.materials.store', $this->company), [
                'material_category_id' => $category->getKey(),
                'sku' => 'PAGE021-LEGACY-001',
                'name' => 'Legacy Category Create Attempt',
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
            ])
            ->assertRedirect(route('companies.materials.create', $this->company))
            ->assertSessionHasErrors('material_category_id');

        $this->assertDatabaseMissing('materials', ['sku' => 'PAGE021-LEGACY-001']);
    }

    public function test_store_allows_incomplete_category_schema_as_draft_without_filling_required_specs(): void
    {
        $category = MaterialCategory::query()->where('code', 'EPM_PV_MOD_STD')->firstOrFail();

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.store', $this->company), [
                'material_category_id' => $category->getKey(),
                'sku' => 'PAGE021-DRAFT-001',
                'name' => 'Incomplete TOPCon Draft',
                'brand' => 'Recorded Brand',
                'model' => 'Recorded Model',
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $material = Material::query()->where('sku', 'PAGE021-DRAFT-001')->firstOrFail();

        $this->assertSame('draft', $material->catalog_status);
        $this->assertSame('needs_review', $material->compliance_status);
        $this->assertSame(0, $material->specs()->count());
    }

    public function test_store_synchronizes_only_schema_managed_technical_keys_for_the_selected_subcategory(): void
    {
        $category = MaterialCategory::query()->where('code', 'EPM_PV_MOD_STD')->firstOrFail();

        $this->actingAs($this->companyAdmin)
            ->post(route('companies.materials.store', $this->company), [
                'material_category_id' => $category->getKey(),
                'sku' => 'PAGE021-SCHEMA-001',
                'name' => 'TOPCon Schema Test',
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
                'technical_specs' => [
                    'module_power_wp' => '620',
                    'voc' => '49.80',
                ],
            ])
            ->assertRedirect();

        $material = Material::query()->where('sku', 'PAGE021-SCHEMA-001')->firstOrFail();

        $this->assertDatabaseHas('material_specs', [
            'material_id' => $material->getKey(),
            'spec_key' => 'module_power_wp',
            'spec_value' => '620',
        ]);
        $this->assertDatabaseHas('material_specs', [
            'material_id' => $material->getKey(),
            'spec_key' => 'voc',
            'spec_value' => '49.8',
        ]);
        $this->assertSame(2, MaterialSpec::query()->where('material_id', $material->getKey())->count());
    }

    public function test_store_rejects_technical_key_not_managed_by_selected_category_schema(): void
    {
        $category = MaterialCategory::query()->where('code', 'EPM_PV_MOD_STD')->firstOrFail();

        $this->actingAs($this->companyAdmin)
            ->from(route('companies.materials.create', $this->company))
            ->post(route('companies.materials.store', $this->company), [
                'material_category_id' => $category->getKey(),
                'sku' => 'PAGE021-UNKNOWN-001',
                'name' => 'Unknown Technical Key Test',
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
                'technical_specs' => [
                    'module_power_wp' => '620',
                    'invented_engineering_rating' => '999',
                ],
            ])
            ->assertRedirect(route('companies.materials.create', $this->company))
            ->assertSessionHasErrors('technical_specs');

        $errors = session('errors');
        $this->assertNotNull($errors);
        $message = implode(' ', $errors->get('technical_specs'));
        $this->assertStringContainsString('selected component class', $message);
        $this->assertStringNotContainsString('invented_engineering_rating', $message);

        $this->assertDatabaseMissing('materials', ['sku' => 'PAGE021-UNKNOWN-001']);
    }

    public function test_page_021_uses_master_aligned_category_specific_material_schema_without_product_revision_claims(): void
    {
        $pv = ProfessionalSpecificationSchema::for('PV_TOPCON');
        $inverter = ProfessionalSpecificationSchema::for('INV_STRING_3P');
        $combinedProtection = ProfessionalSpecificationSchema::for('AC_PROTECTION');

        $this->assertContains('temp_coeff_isc', $pv['required_specs']);
        $this->assertContains('nmot_noct_c', $pv['required_specs']);
        $this->assertContains('max_series_fuse_rating_a', $pv['required_specs']);
        $this->assertContains('performance_warranty_curve', $pv['required_specs']);

        $this->assertContains('reactive_power_modes', $inverter['required_specs']);
        $this->assertContains('temperature_derating_curve', $inverter['required_specs']);
        $this->assertContains('logger_controller_compatibility', $inverter['required_specs']);

        $this->assertContains('frame_rating_a', $combinedProtection['conditional_specs']);
        $this->assertContains('residual_current_ma', $combinedProtection['conditional_specs']);
        $this->assertGreaterThan(20, $pv['required_count']);
        $this->assertGreaterThan(30, $inverter['required_count']);
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
}
