<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CommercialPricingEngineTest extends TestCase
{
    public function test_minimum_margin_guard_blocks_low_margin_boq(): void
    {
        $payload = (new PricingEngine())->price($this->bom(), [
            'material_markup_percent' => 5,
            'min_margin_percent' => 10,
        ]);

        $item = $payload['items'][0];

        $this->assertSame('margin_review_required', $payload['summary']['pricing_status']);
        $this->assertSame(1, $payload['summary']['low_margin_item_count']);
        $this->assertSame('margin_review_required', $item['metadata']['pricing_state']);
        $this->assertFalse($item['metadata']['commercial_breakdown']['margin_guard_passed']);
    }

    public function test_discount_limit_guard_blocks_excessive_global_discount(): void
    {
        $payload = (new PricingEngine())->price($this->bom(), [
            'discount_percent' => 15,
            'max_discount_percent' => 10,
        ]);

        $this->assertSame('discount_review_required', $payload['summary']['pricing_status']);
        $this->assertSame(1, $payload['summary']['discount_limit_exceeded_count']);
        $this->assertGreaterThan(0, $payload['summary']['global_discount_idr']);
    }

    public function test_tax_inclusive_mode_keeps_tax_inside_customer_total(): void
    {
        $exclusive = (new PricingEngine())->price($this->bom(), [
            'tax_percent' => 11,
            'tax_mode' => 'exclusive',
        ]);

        $inclusive = (new PricingEngine())->price($this->bom(), [
            'tax_percent' => 11,
            'tax_mode' => 'inclusive',
        ]);

        $this->assertSame('priced', $inclusive['summary']['pricing_status']);
        $this->assertSame('inclusive', $inclusive['summary']['tax_mode']);
        $this->assertGreaterThan(0, $inclusive['summary']['tax_idr']);
        $this->assertLessThan($exclusive['summary']['grand_price_idr'], $inclusive['summary']['grand_price_idr']);
    }

    public function test_item_margin_override_updates_customer_selling_price_without_exposing_cost_trace(): void
    {
        $payload = (new PricingEngine())->price($this->bom(), [
            'item_margin_overrides' => [
                'PV-MODULE' => 30,
            ],
        ]);

        $item = $payload['items'][0];

        $this->assertSame('priced', $payload['summary']['pricing_status']);
        $this->assertSame(2_145_000.0, $item['metadata']['commercial_breakdown']['unit_price_before_discount_idr']);
        $this->assertSame(30.0, $item['metadata']['commercial_breakdown']['margin_override_percent']);
        $this->assertArrayHasKey('commercial_breakdown', $item['metadata']);
    }

    private function bom(): BillOfMaterial
    {
        $bom = new BillOfMaterial([
            'summary' => [
                'technical_calculation_status' => 'verified',
                'installed_pv_kw' => 5.5,
                'item_count' => 1,
            ],
            'warnings' => [],
        ]);

        $bom->setRelation('items', new Collection([
            new BillOfMaterialItem([
                'category' => 'pv_module',
                'item_code' => 'PV-MODULE',
                'item_name' => 'PV Module',
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
}
