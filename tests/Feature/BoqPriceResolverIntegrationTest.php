<?php

namespace Tests\Feature;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BoqPriceResolverIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private MaterialCategory $category;

    private Supplier $supplier;

    private PriceBook $priceBook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => 'active']);

        $this->company = Company::query()->create([
            'name' => 'NEXORIN Resolver Energy',
            'slug' => 'nexorin-resolver-energy',
            'code' => 'NXRPR',
            'status' => 'active',
            'created_by' => $this->user->getKey(),
        ]);

        $this->category = MaterialCategory::query()->create([
            'code' => 'PV_MODULE_RESOLVER',
            'name' => 'PV Module Resolver',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'company_id' => $this->company->getKey(),
            'supplier_code' => 'SUP-RES-001',
            'name' => 'Resolver Supplier',
            'status' => 'active',
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);

        $this->priceBook = PriceBook::query()->create([
            'company_id' => $this->company->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'code' => 'PB-RES-2026',
            'name' => 'Resolver Price Book 2026',
            'currency' => 'IDR',
            'effective_from' => today()->subMonth()->toDateString(),
            'effective_until' => today()->addMonths(6)->toDateString(),
            'pricing_basis' => 'supplier_quote',
            'status' => 'active',
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);
    }

    public function test_pricing_engine_uses_active_company_price_book_item_first(): void
    {
        $material = $this->createMaterial('PV-MODULE', 'Solar PV Module');
        $this->createPriceBookItem($material, 1_700_000, 2_100_000);

        $payload = (new PricingEngine())->price($this->bom('PV-MODULE', 'pv_module'));
        $item = $payload['items'][0];

        $this->assertSame('priced', $payload['summary']['pricing_status']);
        $this->assertSame(1_700_000.0, $item['unit_cost_idr']);
        $this->assertSame(2_100_000.0, $item['unit_price_idr']);
        $this->assertSame('company_price_book', $item['metadata']['pricing_source']);
        $this->assertSame('PB-RES-2026', $item['metadata']['pricing_source_trace']['price_book_code']);
        $this->assertSame(1, $payload['summary']['company_price_book_item_count']);
        $this->assertSame(0, $payload['summary']['manual_fallback_item_count']);
    }

    public function test_obsolete_supplier_quote_is_not_used_as_default(): void
    {
        $material = $this->createMaterial('PV-MODULE', 'Solar PV Module');
        $this->createPriceBookItem($material, 1_111_111, 1_333_333, [
            'quote_status' => 'obsolete',
        ]);

        $payload = (new PricingEngine())->price($this->bom('PV-MODULE', 'pv_module'));
        $item = $payload['items'][0];

        $this->assertSame('priced', $payload['summary']['pricing_status']);
        $this->assertSame(1_650_000.0, $item['unit_cost_idr']);
        $this->assertSame('manual_fallback', $item['metadata']['pricing_source']);
        $this->assertSame('only_obsolete_quotes', $item['metadata']['pricing_source_trace']['fallback_reason']);
        $this->assertSame(1, $payload['summary']['manual_fallback_item_count']);
    }

    public function test_expired_supplier_price_sets_warning_status_and_trace(): void
    {
        $material = $this->createMaterial('PV-MODULE', 'Solar PV Module');
        $this->createPriceBookItem($material, 1_700_000, 2_100_000, [
            'valid_until' => today()->subDay()->toDateString(),
        ]);

        $payload = (new PricingEngine())->price($this->bom('PV-MODULE', 'pv_module'));
        $item = $payload['items'][0];

        $this->assertSame('expired_price_warning', $payload['summary']['pricing_status']);
        $this->assertSame('expired_price_warning', $item['metadata']['pricing_state']);
        $this->assertSame(1, $payload['summary']['expired_price_item_count']);
        $this->assertTrue(collect($payload['warnings'])->contains(fn (string $warning): bool => str_contains($warning, 'expired supplier quotes')));
    }

    public function test_unavailable_supplier_item_sets_warning_status(): void
    {
        $material = $this->createMaterial('PV-MODULE', 'Solar PV Module');
        $this->createPriceBookItem($material, 1_700_000, 2_100_000, [
            'availability_status' => 'unavailable',
        ]);

        $payload = (new PricingEngine())->price($this->bom('PV-MODULE', 'pv_module'));
        $item = $payload['items'][0];

        $this->assertSame('supplier_unavailable_warning', $payload['summary']['pricing_status']);
        $this->assertSame('supplier_unavailable_warning', $item['metadata']['pricing_state']);
        $this->assertSame(1, $payload['summary']['unavailable_price_item_count']);
    }

    public function test_missing_required_price_blocks_priced_status(): void
    {
        $payload = (new PricingEngine())->price($this->bom('UNKNOWN-NO-PRICE', 'unknown_category'));
        $item = $payload['items'][0];

        $this->assertSame('missing_price', $payload['summary']['pricing_status']);
        $this->assertSame(0.0, $item['unit_cost_idr']);
        $this->assertSame('missing_price', $item['metadata']['pricing_source']);
        $this->assertSame('needs_pricing', $item['metadata']['pricing_state']);
        $this->assertSame(1, $payload['summary']['missing_price_item_count']);
    }

    private function bom(string $itemCode, string $category): BillOfMaterial
    {
        $bom = new BillOfMaterial([
            'company_id' => $this->company->getKey(),
            'summary' => [
                'technical_calculation_status' => 'verified',
                'installed_pv_kw' => 5.5,
                'item_count' => 1,
            ],
            'warnings' => [],
        ]);

        $bom->setRelation('items', new Collection([
            new BillOfMaterialItem([
                'category' => $category,
                'item_code' => $itemCode,
                'item_name' => 'Solar PV Module',
                'quantity' => 10,
                'unit' => 'pcs',
                'metadata' => [
                    'scope_status' => 'required',
                    'scope_mode' => 'base_boq',
                    'include_in_boq' => true,
                    'included_in_base_price' => true,
                ],
            ]),
        ]));

        return $bom;
    }

    private function createMaterial(string $sku, string $name): Material
    {
        return Material::query()->create([
            'company_id' => $this->company->getKey(),
            'material_category_id' => $this->category->getKey(),
            'sku' => $sku,
            'name' => $name,
            'brand' => 'Resolver Solar',
            'model' => 'RS-550',
            'material_type' => 'PV module',
            'cost_group' => 'Material',
            'uom' => 'pcs',
            'system_types' => ['on_grid', 'hybrid'],
            'project_scales' => ['residential', 'commercial'],
            'nominal_power_wp' => 550,
            'default_scope_mode' => 'base_boq',
            'is_active' => true,
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);
    }

    private function createPriceBookItem(Material $material, int $unitCost, int $sellingPrice, array $overrides = []): PriceBookItem
    {
        return PriceBookItem::query()->create(array_merge([
            'price_book_id' => $this->priceBook->getKey(),
            'material_id' => $material->getKey(),
            'supplier_id' => $this->supplier->getKey(),
            'supplier_sku' => 'SUP-'.$material->sku,
            'unit_cost' => $unitCost,
            'selling_price' => $sellingPrice,
            'margin_percent' => null,
            'minimum_order_quantity' => 1,
            'lead_time_days' => 14,
            'availability_status' => 'available',
            'quote_status' => 'active',
            'quoted_at' => today()->subWeek()->toDateString(),
            'valid_until' => today()->addMonths(3)->toDateString(),
        ], $overrides));
    }
}
