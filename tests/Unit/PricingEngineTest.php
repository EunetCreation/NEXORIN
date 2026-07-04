<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    public function test_it_prices_on_grid_bom_items_with_default_parameters(): void
    {
        $payload = (new PricingEngine())->price($this->bom('on_grid'));

        $this->assertSame('priced', $payload['summary']['pricing_status']);
        $this->assertGreaterThan(0, $payload['summary']['material_cost_subtotal_idr']);
        $this->assertGreaterThan(0, $payload['summary']['material_price_subtotal_idr']);
        $this->assertGreaterThan(0, $payload['summary']['labor_cost_idr']);
        $this->assertGreaterThan(0, $payload['summary']['tax_idr']);
        $this->assertGreaterThan(0, $payload['summary']['grand_price_idr']);
        $this->assertGreaterThan(0, $payload['summary']['gross_margin_percent']);

        $firstItem = $payload['items'][0];

        $this->assertSame('PV-MODULE', $firstItem['item_code']);
        $this->assertSame(1650000.0, $firstItem['unit_cost_idr']);
        $this->assertSame(1897500.0, $firstItem['unit_price_idr']);
        $this->assertSame(12 * 1650000.0, $firstItem['line_cost_idr']);
    }

    public function test_it_prices_hybrid_battery_capacity(): void
    {
        $payload = (new PricingEngine())->price($this->bom('hybrid'));
        $battery = collect($payload['items'])->firstWhere('item_code', 'BATTERY-PACK');

        $this->assertNotNull($battery);
        $this->assertSame(4500000.0, $battery['unit_cost_idr']);
        $this->assertGreaterThan(0, $battery['line_price_idr']);
        $this->assertGreaterThan(90000000, $payload['summary']['grand_price_idr']);
    }

    public function test_custom_pricing_parameters_change_totals(): void
    {
        $defaultPayload = (new PricingEngine())->price($this->bom('on_grid'));
        $customPayload = (new PricingEngine())->price($this->bom('on_grid'), [
            'material_markup_percent' => 25,
            'labor_rate_per_kwp_idr' => 1000000,
            'logistics_cost_idr' => 2500000,
            'contingency_percent' => 5,
            'tax_percent' => 11,
            'discount_percent' => 3,
        ]);

        $this->assertSame(25.0, $customPayload['parameters']['material_markup_percent']);
        $this->assertNotSame(
            $defaultPayload['summary']['grand_price_idr'],
            $customPayload['summary']['grand_price_idr']
        );
        $this->assertGreaterThan(0, $customPayload['summary']['discount_idr']);
    }

    private function bom(string $systemType): BillOfMaterial
    {
        $summary = [
            'project_number' => 'NXR-TEST-001',
            'system_type' => $systemType,
            'technical_calculation_status' => 'verified',
            'installed_pv_kw' => 6.6,
            'module_count' => 12,
            'inverter_count' => 1,
            'string_count' => 2,
            'battery_nominal_kwh' => $systemType === 'hybrid' ? 12.5 : 0,
            'item_count' => $systemType === 'hybrid' ? 5 : 4,
            'total_cost_idr' => 0,
            'total_price_idr' => 0,
            'pricing_status' => 'not_priced',
        ];

        $bom = new BillOfMaterial([
            'summary' => $summary,
            'warnings' => [],
        ]);

        $items = [
            new BillOfMaterialItem([
                'category' => 'pv_module',
                'item_code' => 'PV-MODULE',
                'item_name' => 'PV Module',
                'quantity' => 12,
                'unit' => 'pcs',
            ]),
            new BillOfMaterialItem([
                'category' => 'inverter',
                'item_code' => 'INV-GRID-HYBRID',
                'item_name' => $systemType === 'hybrid' ? 'Hybrid Inverter' : 'Grid-Tied Inverter',
                'quantity' => 1,
                'unit' => 'pcs',
            ]),
            new BillOfMaterialItem([
                'category' => 'mounting',
                'item_code' => 'MOUNT-ROOFTOP',
                'item_name' => 'Rooftop Mounting Set',
                'quantity' => 12,
                'unit' => 'module-set',
            ]),
            new BillOfMaterialItem([
                'category' => 'protection',
                'item_code' => 'AC-BREAKER',
                'item_name' => 'AC Circuit Breaker',
                'quantity' => 1,
                'unit' => 'pcs',
            ]),
        ];

        if ($systemType === 'hybrid') {
            $items[] = new BillOfMaterialItem([
                'category' => 'battery',
                'item_code' => 'BATTERY-PACK',
                'item_name' => 'Battery Energy Storage Pack',
                'quantity' => 12.5,
                'unit' => 'kWh',
            ]);
        }

        $bom->setRelation('items', new Collection($items));

        return $bom;
    }
}
