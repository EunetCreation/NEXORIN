<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PriceBook;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialCatalogManagementTest extends TestCase
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

    public function test_company_admin_can_manage_material_catalog_item(): void
    {
        $admin = $this->createCompanyUser('company_admin');

        $category = MaterialCategory::query()->create([
            'code' => 'PV_MODULE',
            'name' => 'PV Module',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('companies.materials.index', $this->company))
            ->assertOk()
            ->assertSee('Material Catalog');

        $response = $this
            ->actingAs($admin)
            ->post(route('companies.materials.store', $this->company), [
                'material_category_id' => $category->id,
                'sku' => 'PV-MOD-TEST-550',
                'name' => 'Solar PV Module 550 Wp Test',
                'brand' => 'Test Solar',
                'model' => 'TS-550',
                'material_type' => 'PV module',
                'cost_group' => 'Material',
                'uom' => 'pcs',
                'system_types' => ['on_grid', 'hybrid'],
                'project_scales' => ['residential', 'commercial'],
                'nominal_power_wp' => 550,
                'default_scope_mode' => 'base_boq',
                'is_active' => '1',
            ]);

        $material = Material::query()->firstOrFail();

        $response->assertRedirect(route(
            'companies.materials.show',
            [$this->company, $material->id]
        ));

        $this->assertDatabaseHas('materials', [
            'company_id' => $this->company->id,
            'sku' => 'PV-MOD-TEST-550',
            'name' => 'Solar PV Module 550 Wp Test',
            'default_scope_mode' => 'base_boq',
            'is_active' => true,
        ]);
    }

    public function test_engineer_can_view_catalog_but_cannot_create_material(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->get(route('companies.materials.index', $this->company))
            ->assertOk()
            ->assertSee('Material Catalog');

        $this
            ->actingAs($engineer)
            ->post(route('companies.materials.store', $this->company), [
                'sku' => 'INV-NOT-ALLOWED',
                'name' => 'Unauthorized Inverter',
                'uom' => 'pcs',
                'default_scope_mode' => 'catalog_candidate',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('materials', [
            'sku' => 'INV-NOT-ALLOWED',
        ]);
    }

    public function test_company_admin_can_create_supplier_and_price_book(): void
    {
        $admin = $this->createCompanyUser('company_admin');

        $supplierResponse = $this
            ->actingAs($admin)
            ->post(route('companies.suppliers.store', $this->company), [
                'supplier_code' => 'SUP-001',
                'name' => 'Solar Procurement Partner',
                'contact_person' => 'Procurement Team',
                'email' => 'procurement@example.test',
                'phone' => '+62 761 123 456',
                'status' => 'active',
                'lead_time_days' => 14,
            ]);

        $supplier = Supplier::query()->firstOrFail();

        $supplierResponse->assertRedirect(route(
            'companies.suppliers.show',
            [$this->company, $supplier->id]
        ));

        $priceBookResponse = $this
            ->actingAs($admin)
            ->post(route('companies.price-books.store', $this->company), [
                'supplier_id' => $supplier->id,
                'code' => 'PB-2026-001',
                'name' => 'Supplier Price Book 2026',
                'currency' => 'IDR',
                'effective_from' => '2026-01-01',
                'effective_until' => '2026-12-31',
                'pricing_basis' => 'supplier_quote',
                'status' => 'active',
            ]);

        $priceBook = PriceBook::query()->firstOrFail();

        $priceBookResponse->assertRedirect(route(
            'companies.price-books.show',
            [$this->company, $priceBook->id]
        ));

        $this->assertDatabaseHas('suppliers', [
            'company_id' => $this->company->id,
            'supplier_code' => 'SUP-001',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('price_books', [
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'code' => 'PB-2026-001',
            'status' => 'active',
        ]);
    }

    public function test_archive_and_reactivation_cannot_preserve_stale_material_approval(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $material = Material::query()->create([
            'company_id' => $this->company->id,
            'sku' => 'APPROVED-ARCHIVE-001',
            'name' => 'Approved Archive Test Material',
            'uom' => 'pcs',
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('companies.materials.archive', [$this->company, $material]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $material->refresh();
        $this->assertFalse($material->is_active);
        $this->assertSame('needs_review', $material->catalog_status);
        $this->assertNull($material->approved_at);
        $this->assertNull($material->approved_by);

        $material->forceFill([
            'catalog_status' => 'approved',
            'compliance_status' => 'compliant',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ])->save();

        $this->actingAs($admin)
            ->patch(route('companies.materials.activate', [$this->company, $material]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $material->refresh();
        $this->assertTrue($material->is_active);
        $this->assertSame('needs_review', $material->catalog_status);
        $this->assertSame('needs_review', $material->compliance_status);
        $this->assertNull($material->approved_at);
        $this->assertNull($material->approved_by);
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
