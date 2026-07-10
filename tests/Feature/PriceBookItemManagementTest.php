<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceBookItemManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private MaterialCategory $category;

    private Supplier $supplier;

    private PriceBook $priceBook;

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
            'slug' => 'nexorin-energy-price-items',
            'code' => 'NXPI',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $this->category = MaterialCategory::query()->create([
            'code' => 'PV_MODULE',
            'name' => 'PV Module',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'company_id' => $this->company->id,
            'supplier_code' => 'SUP-PB-001',
            'name' => 'Solar Procurement Partner',
            'status' => 'active',
            'lead_time_days' => 14,
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);

        $this->priceBook = PriceBook::query()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'code' => 'PB-ITEM-2026',
            'name' => 'Supplier Price Book Items 2026',
            'currency' => 'IDR',
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'pricing_basis' => 'supplier_quote',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);
    }

    public function test_company_admin_can_create_update_and_remove_price_book_item(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $material = $this->createMaterial('PV-MOD-550', 'Solar PV Module 550 Wp');

        $storeResponse = $this
            ->actingAs($admin)
            ->post(route('companies.price-books.items.store', [$this->company, $this->priceBook]), [
                'material_id' => $material->id,
                'supplier_id' => $this->supplier->id,
                'supplier_sku' => 'SUP-PV-550',
                'unit_cost' => 1850000,
                'selling_price' => 2250000,
                'margin_percent' => 21.62,
                'minimum_order_quantity' => 2,
                'lead_time_days' => 21,
                'availability_status' => 'available',
                'quoted_at' => '2026-02-01',
                'valid_until' => '2026-05-01',
                'warranty_note' => 'Twelve year product warranty.',
                'notes' => 'Supplier quote baseline.',
            ]);

        $item = PriceBookItem::query()->firstOrFail();

        $storeResponse->assertRedirect(route(
            'companies.price-books.show',
            [$this->company, $this->priceBook]
        ));

        $this->assertDatabaseHas('price_book_items', [
            'price_book_id' => $this->priceBook->id,
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'supplier_sku' => 'SUP-PV-550',
            'unit_cost' => 1850000,
            'selling_price' => 2250000,
            'availability_status' => 'available',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('companies.price-books.items.edit', [$this->company, $this->priceBook, $item]))
            ->assertOk()
            ->assertSee('Edit Price Item')
            ->assertSee('Solar PV Module 550 Wp');

        $updateResponse = $this
            ->actingAs($admin)
            ->put(route('companies.price-books.items.update', [$this->company, $this->priceBook, $item]), [
                'material_id' => $material->id,
                'supplier_id' => $this->supplier->id,
                'supplier_sku' => 'SUP-PV-550-REV',
                'unit_cost' => 1900000,
                'selling_price' => null,
                'margin_percent' => 25,
                'minimum_order_quantity' => 4,
                'lead_time_days' => 30,
                'availability_status' => 'limited',
                'quoted_at' => '2026-03-01',
                'valid_until' => '2026-06-01',
            ]);

        $updateResponse->assertRedirect(route(
            'companies.price-books.show',
            [$this->company, $this->priceBook]
        ));

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'supplier_sku' => 'SUP-PV-550-REV',
            'unit_cost' => 1900000,
            'selling_price' => null,
            'margin_percent' => 25,
            'availability_status' => 'limited',
        ]);

        $destroyResponse = $this
            ->actingAs($admin)
            ->delete(route('companies.price-books.items.destroy', [$this->company, $this->priceBook, $item]));

        $destroyResponse->assertRedirect(route(
            'companies.price-books.show',
            [$this->company, $this->priceBook]
        ));

        $this->assertDatabaseMissing('price_book_items', [
            'id' => $item->id,
        ]);
    }

    public function test_price_book_item_requires_material_visible_to_company(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $otherCompany = Company::query()->create([
            'name' => 'Other EPC',
            'slug' => 'other-epc',
            'code' => 'OEP',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $foreignMaterial = Material::query()->create([
            'company_id' => $otherCompany->id,
            'material_category_id' => $this->category->id,
            'sku' => 'FOREIGN-PV-550',
            'name' => 'Foreign PV Module',
            'uom' => 'pcs',
            'default_scope_mode' => 'base_boq',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('companies.price-books.items.store', [$this->company, $this->priceBook]), [
                'material_id' => $foreignMaterial->id,
                'unit_cost' => 1800000,
                'availability_status' => 'available',
            ])
            ->assertSessionHasErrors('material_id');

        $this->assertDatabaseMissing('price_book_items', [
            'material_id' => $foreignMaterial->id,
        ]);
    }

    public function test_engineer_cannot_manage_price_book_items(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $material = $this->createMaterial('PV-MOD-DENIED', 'Denied PV Module');

        $this
            ->actingAs($engineer)
            ->post(route('companies.price-books.items.store', [$this->company, $this->priceBook]), [
                'material_id' => $material->id,
                'unit_cost' => 1850000,
                'availability_status' => 'available',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('price_book_items', [
            'material_id' => $material->id,
        ]);
    }

    private function createMaterial(string $sku, string $name): Material
    {
        return Material::query()->create([
            'company_id' => $this->company->id,
            'material_category_id' => $this->category->id,
            'sku' => $sku,
            'name' => $name,
            'brand' => 'Test Solar',
            'model' => 'TS-550',
            'material_type' => 'PV module',
            'cost_group' => 'Material',
            'uom' => 'pcs',
            'system_types' => ['on_grid', 'hybrid'],
            'project_scales' => ['residential', 'commercial'],
            'nominal_power_wp' => 550,
            'default_scope_mode' => 'base_boq',
            'is_active' => true,
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
