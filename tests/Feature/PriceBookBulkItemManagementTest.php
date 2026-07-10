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
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PriceBookBulkItemManagementTest extends TestCase
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
            'name' => 'NEXORIN Energy Bulk',
            'slug' => 'nexorin-energy-bulk-price',
            'code' => 'NXBP',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $this->category = MaterialCategory::query()->create([
            'code' => 'PV_MODULE_BULK',
            'name' => 'PV Module Bulk',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'company_id' => $this->company->id,
            'supplier_code' => 'SUP-BULK-001',
            'name' => 'Bulk Solar Supplier',
            'status' => 'active',
            'lead_time_days' => 14,
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);

        $this->priceBook = PriceBook::query()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'code' => 'PB-BULK-2026',
            'name' => 'Bulk Supplier Price Book 2026',
            'currency' => 'IDR',
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'pricing_basis' => 'supplier_quote',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);
    }

    public function test_company_admin_can_bulk_update_price_book_items_with_warning(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $firstMaterial = $this->createMaterial('PV-BULK-550', 'Bulk PV Module 550 Wp');
        $secondMaterial = $this->createMaterial('INV-BULK-10K', 'Bulk Inverter 10 kW');

        $firstItem = $this->createPriceBookItem($firstMaterial, 1800000, 2200000);
        $secondItem = $this->createPriceBookItem($secondMaterial, 12500000, null);

        $response = $this
            ->actingAs($admin)
            ->patch(route('companies.price-books.items.bulk-update', [$this->company, $this->priceBook]), [
                'items' => [
                    $firstItem->id => [
                        'unit_cost' => 1900000,
                        'selling_price' => 1850000,
                        'margin_percent' => null,
                        'minimum_order_quantity' => 4,
                        'lead_time_days' => 21,
                        'availability_status' => 'limited',
                        'quote_status' => 'active',
                        'quoted_at' => '2026-04-01',
                        'valid_until' => '2026-07-01',
                    ],
                    $secondItem->id => [
                        'unit_cost' => 12600000,
                        'selling_price' => null,
                        'margin_percent' => 18,
                        'minimum_order_quantity' => 1,
                        'lead_time_days' => 35,
                        'availability_status' => 'backorder',
                        'quote_status' => 'obsolete',
                        'quoted_at' => '2026-04-10',
                        'valid_until' => '2026-06-10',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('companies.price-books.show', [$this->company, $this->priceBook]))
            ->assertSessionHas('success')
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('price_book_items', [
            'id' => $firstItem->id,
            'unit_cost' => 1900000,
            'selling_price' => 1850000,
            'minimum_order_quantity' => 4,
            'lead_time_days' => 21,
            'availability_status' => 'limited',
            'quote_status' => 'active',
        ]);

        $this->assertDatabaseHas('price_book_items', [
            'id' => $secondItem->id,
            'unit_cost' => 12600000,
            'selling_price' => null,
            'margin_percent' => 18,
            'availability_status' => 'backorder',
            'quote_status' => 'obsolete',
        ]);
    }

    public function test_company_admin_can_preview_and_commit_csv_import(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $material = $this->createMaterial('PV-CSV-550', 'CSV PV Module 550 Wp');

        $csv = implode("\n", [
            'material_key,supplier_code,supplier_sku,unit_cost,selling_price,margin_percent,minimum_order_quantity,lead_time_days,availability_status,quote_status,quoted_at,valid_until,warranty_note,notes',
            'PV-CSV-550,SUP-BULK-001,SUP-CSV-550,1810000,2260000,,2,28,available,active,2026-05-01,2026-08-01,12 years,CSV import valid row',
            'UNKNOWN-MATERIAL,SUP-BULK-001,BAD-ROW,1000000,1200000,,1,7,available,active,2026-05-01,2026-08-01,,Should fail',
        ]);

        $file = UploadedFile::fake()->createWithContent('supplier-price.csv', $csv);

        $this
            ->actingAs($admin)
            ->post(route('companies.price-books.items.import.preview', [$this->company, $this->priceBook]), [
                'import_file' => $file,
            ])
            ->assertOk()
            ->assertSee('Import Preview')
            ->assertSee('Ready to import')
            ->assertSee('Material not found');

        $this
            ->actingAs($admin)
            ->post(route('companies.price-books.items.import.commit', [$this->company, $this->priceBook]))
            ->assertRedirect(route('companies.price-books.show', [$this->company, $this->priceBook]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('price_book_items', [
            'price_book_id' => $this->priceBook->id,
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'supplier_sku' => 'SUP-CSV-550',
            'unit_cost' => 1810000,
            'selling_price' => 2260000,
            'availability_status' => 'available',
            'quote_status' => 'active',
            'notes' => 'CSV import valid row',
        ]);

        $this->assertDatabaseCount('price_book_items', 1);
    }

    public function test_csv_import_updates_existing_material_instead_of_creating_duplicate(): void
    {
        $admin = $this->createCompanyUser('company_admin');
        $material = $this->createMaterial('PV-CSV-UPD', 'CSV Update PV Module');
        $this->createPriceBookItem($material, 1700000, 2100000);

        $csv = implode("\n", [
            'material_key,unit_cost,selling_price,availability_status,quote_status,quoted_at,valid_until',
            'PV-CSV-UPD,1750000,2175000,limited,active,2026-06-01,2026-09-01',
        ]);

        $file = UploadedFile::fake()->createWithContent('supplier-price-update.csv', $csv);

        $this
            ->actingAs($admin)
            ->post(route('companies.price-books.items.import.preview', [$this->company, $this->priceBook]), [
                'import_file' => $file,
            ])
            ->assertOk()
            ->assertSee('Update');

        $this
            ->actingAs($admin)
            ->post(route('companies.price-books.items.import.commit', [$this->company, $this->priceBook]))
            ->assertRedirect(route('companies.price-books.show', [$this->company, $this->priceBook]));

        $this->assertDatabaseCount('price_book_items', 1);
        $this->assertDatabaseHas('price_book_items', [
            'price_book_id' => $this->priceBook->id,
            'material_id' => $material->id,
            'unit_cost' => 1750000,
            'selling_price' => 2175000,
            'availability_status' => 'limited',
            'quote_status' => 'active',
        ]);
    }

    public function test_engineer_cannot_bulk_update_or_import_price_items(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $material = $this->createMaterial('PV-BULK-DENIED', 'Denied Bulk PV Module');
        $item = $this->createPriceBookItem($material, 1800000, 2200000);

        $this
            ->actingAs($engineer)
            ->patch(route('companies.price-books.items.bulk-update', [$this->company, $this->priceBook]), [
                'items' => [
                    $item->id => [
                        'unit_cost' => 1900000,
                        'availability_status' => 'available',
                        'quote_status' => 'active',
                    ],
                ],
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this
            ->actingAs($engineer)
            ->get(route('companies.price-books.items.import.form', [$this->company, $this->priceBook]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'unit_cost' => 1800000,
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

    private function createPriceBookItem(Material $material, int $unitCost, ?int $sellingPrice): PriceBookItem
    {
        return PriceBookItem::query()->create([
            'price_book_id' => $this->priceBook->id,
            'material_id' => $material->id,
            'supplier_id' => $this->supplier->id,
            'supplier_sku' => 'SUP-'.$material->sku,
            'unit_cost' => $unitCost,
            'selling_price' => $sellingPrice,
            'margin_percent' => null,
            'minimum_order_quantity' => 1,
            'lead_time_days' => 14,
            'availability_status' => 'available',
            'quote_status' => 'active',
            'quoted_at' => '2026-01-01',
            'valid_until' => '2026-12-31',
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
