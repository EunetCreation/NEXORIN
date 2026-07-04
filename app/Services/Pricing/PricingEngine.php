<?php

namespace App\Services\Pricing;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;

class PricingEngine
{
    /**
     * Apply deterministic BOQ pricing to a generated BOM draft.
     *
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>, warnings: array<int, string>, parameters: array<string, mixed>}
     */
    public function price(BillOfMaterial $bom, array $parameters = []): array
    {
        $summary = $bom->summary ?? [];
        $warnings = [];
        $normalized = $this->normalizeParameters($parameters);
        $unitCosts = $this->unitCostBook();
        $categoryFallbacks = $this->categoryFallbackCostBook();

        $items = [];
        $materialCostSubtotal = 0.0;
        $materialPriceSubtotal = 0.0;

        foreach ($bom->items as $item) {
            $unitCost = $this->resolveUnitCost($item, $unitCosts, $categoryFallbacks);

            if ($unitCost <= 0) {
                $warnings[] = sprintf(
                    'No default price was found for %s. The item remains zero-priced.',
                    $item->item_code ?: $item->item_name
                );
            }

            $quantity = $this->positiveFloat($item->quantity);
            $unitPrice = round($unitCost * (1 + ($normalized['material_markup_percent'] / 100)), 2);
            $lineCost = round($quantity * $unitCost, 2);
            $linePrice = round($quantity * $unitPrice, 2);

            $materialCostSubtotal += $lineCost;
            $materialPriceSubtotal += $linePrice;

            $metadata = $item->metadata ?? [];
            $metadata['pricing_source'] = $unitCost > 0 ? 'default_price_book' : 'missing_default_price';
            $metadata['material_markup_percent'] = $normalized['material_markup_percent'];
            $metadata['priced_at'] = now()->toISOString();

            $items[] = [
                'id' => $item->getKey(),
                'item_code' => $item->item_code,
                'category' => $item->category,
                'unit_cost_idr' => $unitCost,
                'unit_price_idr' => $unitPrice,
                'line_cost_idr' => $lineCost,
                'line_price_idr' => $linePrice,
                'metadata' => $metadata,
            ];
        }

        $installedPvKw = $this->positiveFloat($summary['installed_pv_kw'] ?? 0);
        $laborCost = round($installedPvKw * $normalized['labor_rate_per_kwp_idr'], 2);
        $logisticsCost = $normalized['logistics_cost_idr'];
        $otherCost = $normalized['other_cost_idr'];

        $contingencyBase = $materialPriceSubtotal + $laborCost + $logisticsCost + $otherCost;
        $contingencyCost = round($contingencyBase * ($normalized['contingency_percent'] / 100), 2);
        $preTaxSubtotal = round($materialPriceSubtotal + $laborCost + $logisticsCost + $otherCost + $contingencyCost, 2);
        $tax = round($preTaxSubtotal * ($normalized['tax_percent'] / 100), 2);
        $beforeDiscount = round($preTaxSubtotal + $tax, 2);
        $discount = round($beforeDiscount * ($normalized['discount_percent'] / 100), 2);
        $grandPrice = round(max(0, $beforeDiscount - $discount), 2);
        $grandCost = round($materialCostSubtotal + $laborCost + $logisticsCost + $otherCost, 2);
        $grossMargin = round($grandPrice - $grandCost, 2);
        $grossMarginPercent = $grandPrice > 0
            ? round(($grossMargin / $grandPrice) * 100, 2)
            : 0.0;

        if ($installedPvKw <= 0) {
            $warnings[] = 'Installed PV capacity is zero. Labor cost based on kWp could not be estimated accurately.';
        }

        if (($summary['technical_calculation_status'] ?? null) !== 'verified') {
            $warnings[] = 'The technical calculation is not engineer verified. Treat BOQ pricing as a commercial draft.';
        }

        $pricingSummary = array_merge($summary, [
            'pricing_status' => 'priced',
            'material_cost_subtotal_idr' => $materialCostSubtotal,
            'material_price_subtotal_idr' => $materialPriceSubtotal,
            'labor_cost_idr' => $laborCost,
            'logistics_cost_idr' => $logisticsCost,
            'other_cost_idr' => $otherCost,
            'contingency_cost_idr' => $contingencyCost,
            'tax_idr' => $tax,
            'discount_idr' => $discount,
            'grand_cost_idr' => $grandCost,
            'grand_price_idr' => $grandPrice,
            'total_cost_idr' => $grandCost,
            'total_price_idr' => $grandPrice,
            'gross_margin_idr' => $grossMargin,
            'gross_margin_percent' => $grossMarginPercent,
            'priced_at' => now()->toISOString(),
        ]);

        return [
            'summary' => $pricingSummary,
            'items' => $items,
            'warnings' => array_values(array_unique($warnings)),
            'parameters' => $normalized,
        ];
    }

    /** @return array<string, mixed> */
    public function defaultParameters(): array
    {
        return [
            'material_markup_percent' => 15.0,
            'labor_rate_per_kwp_idr' => 850000.0,
            'logistics_cost_idr' => 1500000.0,
            'other_cost_idr' => 0.0,
            'contingency_percent' => 3.0,
            'tax_percent' => 11.0,
            'discount_percent' => 0.0,
            'pricing_note' => null,
        ];
    }

    /** @return array<string, float> */
    public function unitCostBook(): array
    {
        return [
            'PV-MODULE' => 1650000.0,
            'INV-GRID-HYBRID' => 18000000.0,
            'MOUNT-ROOFTOP' => 350000.0,
            'DC-CABLE-PV1F' => 35000.0,
            'AC-CABLE-LV' => 55000.0,
            'PV-CONNECTOR-PAIR' => 65000.0,
            'DC-ISOLATOR' => 450000.0,
            'DC-FUSE' => 125000.0,
            'DC-SPD' => 850000.0,
            'AC-BREAKER' => 350000.0,
            'AC-SPD' => 750000.0,
            'BATTERY-PACK' => 4500000.0,
            'BATTERY-PROTECTION-BMS' => 3500000.0,
            'LABEL-SAFETY' => 250000.0,
        ];
    }

    /** @return array<string, float> */
    private function categoryFallbackCostBook(): array
    {
        return [
            'pv_module' => 1650000.0,
            'inverter' => 18000000.0,
            'mounting' => 350000.0,
            'dc_cable' => 35000.0,
            'ac_cable' => 55000.0,
            'connector' => 65000.0,
            'protection' => 450000.0,
            'battery' => 4500000.0,
            'accessory' => 250000.0,
        ];
    }

    private function resolveUnitCost(
        BillOfMaterialItem $item,
        array $unitCosts,
        array $categoryFallbacks
    ): float {
        $itemCode = (string) $item->item_code;
        $category = (string) $item->category;

        return (float) ($unitCosts[$itemCode] ?? $categoryFallbacks[$category] ?? 0.0);
    }

    /** @return array<string, mixed> */
    private function normalizeParameters(array $parameters): array
    {
        $defaults = $this->defaultParameters();

        return [
            'material_markup_percent' => $this->boundedPercent(
                $parameters['material_markup_percent'] ?? $defaults['material_markup_percent'],
                0,
                200
            ),
            'labor_rate_per_kwp_idr' => $this->money(
                $parameters['labor_rate_per_kwp_idr'] ?? $defaults['labor_rate_per_kwp_idr']
            ),
            'logistics_cost_idr' => $this->money(
                $parameters['logistics_cost_idr'] ?? $defaults['logistics_cost_idr']
            ),
            'other_cost_idr' => $this->money(
                $parameters['other_cost_idr'] ?? $defaults['other_cost_idr']
            ),
            'contingency_percent' => $this->boundedPercent(
                $parameters['contingency_percent'] ?? $defaults['contingency_percent'],
                0,
                100
            ),
            'tax_percent' => $this->boundedPercent(
                $parameters['tax_percent'] ?? $defaults['tax_percent'],
                0,
                100
            ),
            'discount_percent' => $this->boundedPercent(
                $parameters['discount_percent'] ?? $defaults['discount_percent'],
                0,
                100
            ),
            'pricing_note' => isset($parameters['pricing_note']) && trim((string) $parameters['pricing_note']) !== ''
                ? trim((string) $parameters['pricing_note'])
                : null,
        ];
    }

    private function money(mixed $value): float
    {
        return max(0.0, round((float) $value, 2));
    }

    private function boundedPercent(mixed $value, float $min, float $max): float
    {
        return min($max, max($min, round((float) $value, 2)));
    }

    private function positiveFloat(mixed $value): float
    {
        return max(0.0, round((float) $value, 4));
    }
}
