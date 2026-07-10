<?php

namespace App\Services\Pricing;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;

class PricingEngine
{
    public function __construct(
        private readonly ?PriceResolver $priceResolver = null,
    ) {
    }

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
        $taxableMaterialSubtotal = 0.0;
        $itemDiscountSubtotal = 0.0;
        $scopeCounters = $this->scopeCounters($bom);
        $pricingHealth = $this->emptyPricingHealth();
        $resolver = $this->priceResolver ?? new PriceResolver();

        foreach ($bom->items as $item) {
            $metadata = $item->metadata ?? [];
            $isSummaryCost = $this->isSummaryCost($metadata);
            $isChargeable = $this->isChargeable($metadata) && ! $isSummaryCost;
            $priceDecision = $isChargeable
                ? $this->resolveUnitPricing($bom, $item, $normalized, $unitCosts, $categoryFallbacks, $resolver)
                : $this->excludedPricingDecision($metadata, $isSummaryCost);

            $commercialDecision = $this->applyLineCommercialRules($item, $priceDecision, $normalized, $isChargeable);
            $unitCost = $priceDecision['unit_cost_idr'];
            $unitPrice = $commercialDecision['unit_price_idr'];
            $pricingState = $this->combinePricingState(
                (string) $priceDecision['pricing_state'],
                (string) $commercialDecision['pricing_state']
            );

            if ($isChargeable && $unitCost <= 0) {
                $warnings[] = sprintf(
                    'Price is required for %s before the quotation can be issued.',
                    $item->item_name
                );
            }

            foreach ($priceDecision['warnings'] as $warning) {
                $warnings[] = $warning;
            }

            foreach ($commercialDecision['warnings'] as $warning) {
                $warnings[] = $warning;
            }

            $this->registerPricingHealth($pricingHealth, $priceDecision, $isChargeable);
            $this->registerCommercialHealth($pricingHealth, $commercialDecision, $isChargeable);

            $quantity = $this->positiveFloat($item->quantity);
            $lineCost = $isChargeable ? round($quantity * $unitCost, 2) : 0.0;
            $linePrice = $isChargeable ? round($quantity * $unitPrice, 2) : 0.0;

            $materialCostSubtotal += $lineCost;
            $materialPriceSubtotal += $linePrice;
            $itemDiscountSubtotal += $isChargeable ? $commercialDecision['item_discount_idr'] : 0.0;

            if ($isChargeable && ! $commercialDecision['tax_exempt']) {
                $taxableMaterialSubtotal += $linePrice;
            }

            $metadata['pricing_source'] = $priceDecision['pricing_source'];
            $metadata['pricing_state'] = $pricingState;
            $metadata['pricing_source_trace'] = $priceDecision['pricing_trace'];
            $metadata['commercial_breakdown'] = $commercialDecision;
            $metadata['material_markup_percent'] = $isChargeable ? $normalized['material_markup_percent'] : 0;
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

        $serviceSubtotal = round($laborCost + $logisticsCost + $otherCost, 2);
        $contingencyBase = $materialPriceSubtotal + $serviceSubtotal;
        $contingencyCost = round($contingencyBase * ($normalized['contingency_percent'] / 100), 2);
        $commercialSubtotal = round($materialPriceSubtotal + $serviceSubtotal + $contingencyCost, 2);
        $taxableServiceSubtotal = $normalized['services_tax_exempt'] ? 0.0 : $serviceSubtotal;
        $taxableContingency = $normalized['contingency_tax_exempt'] ? 0.0 : $contingencyCost;
        $taxableSubtotal = round($taxableMaterialSubtotal + $taxableServiceSubtotal + $taxableContingency, 2);
        $tax = $this->calculateTax($taxableSubtotal, $normalized['tax_percent'], $normalized['tax_mode']);
        $beforeGlobalDiscount = $normalized['tax_mode'] === 'inclusive'
            ? $commercialSubtotal
            : round($commercialSubtotal + $tax, 2);
        $globalDiscount = round($beforeGlobalDiscount * ($normalized['discount_percent'] / 100), 2);
        $grandBeforeRounding = round(max(0, $beforeGlobalDiscount - $globalDiscount), 2);
        $grandPrice = $this->roundMoney($grandBeforeRounding, $normalized['rounding_unit_idr']);
        $roundingAdjustment = round($grandPrice - $grandBeforeRounding, 2);
        $discount = round($itemDiscountSubtotal + $globalDiscount, 2);
        $grandCost = round($materialCostSubtotal + $laborCost + $logisticsCost + $otherCost, 2);
        $netRevenueBeforeTax = round(max(0, $grandPrice - $tax), 2);
        $grossMargin = round($netRevenueBeforeTax - $grandCost, 2);
        $grossMarginPercent = $netRevenueBeforeTax > 0
            ? round(($grossMargin / $netRevenueBeforeTax) * 100, 2)
            : 0.0;

        if ($installedPvKw <= 0) {
            $warnings[] = 'Installed PV capacity is zero. Labor cost based on kWp could not be estimated accurately.';
        }

        if (($summary['technical_calculation_status'] ?? null) !== 'verified') {
            $warnings[] = 'The technical calculation is not engineer verified. Treat BOQ pricing as a commercial draft.';
        }

        if ($grandPrice <= 0) {
            $warnings[] = 'BOQ total is still zero. Complete required prices before releasing the quotation or premium PDF.';
        }

        if ($normalized['discount_percent'] > $normalized['max_discount_percent']) {
            $pricingHealth['discount_limit_exceeded_count']++;
            $warnings[] = sprintf(
                'Global discount is %.2f%%, above the %.2f%% commercial limit.',
                $normalized['discount_percent'],
                $normalized['max_discount_percent']
            );
        }

        $pricingHealth['commercial_total_consistent'] = $this->commercialTotalConsistent(
            $grandPrice,
            $commercialSubtotal,
            $tax,
            $globalDiscount,
            $roundingAdjustment,
            $normalized['tax_mode']
        );

        $finalPricingStatus = $this->finalPricingStatus($pricingHealth, $grandPrice);
        if ($finalPricingStatus !== 'priced') {
            $warnings[] = $this->pricingStatusWarning($finalPricingStatus);
        }

        $pricingSummary = array_merge($summary, [
            'pricing_status' => $finalPricingStatus,
            'scope_control_model' => 'base_boq_plus_internal_catalog_candidates',
            'scope_counters' => $scopeCounters,
            'base_boq_item_count' => $scopeCounters['base_boq_item_count'],
            'summary_cost_item_count' => $scopeCounters['summary_cost_item_count'],
            'optional_addon_item_count' => $scopeCounters['optional_addon_item_count'],
            'utility_responsibility_item_count' => $scopeCounters['utility_responsibility_item_count'],
            'owner_responsibility_item_count' => $scopeCounters['owner_responsibility_item_count'],
            'catalog_candidate_item_count' => $scopeCounters['catalog_candidate_item_count'],
            'not_applicable_item_count' => $scopeCounters['not_applicable_item_count'],
            'client_visible_item_count' => $scopeCounters['client_visible_item_count'],
            'internal_visible_item_count' => $scopeCounters['internal_visible_item_count'],
            'material_cost_subtotal_idr' => $materialCostSubtotal,
            'material_price_subtotal_idr' => $materialPriceSubtotal,
            'taxable_material_subtotal_idr' => $taxableMaterialSubtotal,
            'service_subtotal_idr' => $serviceSubtotal,
            'commercial_subtotal_idr' => $commercialSubtotal,
            'taxable_subtotal_idr' => $taxableSubtotal,
            'tax_mode' => $normalized['tax_mode'],
            'tax_percent' => $normalized['tax_percent'],
            'item_discount_idr' => $itemDiscountSubtotal,
            'global_discount_idr' => $globalDiscount,
            'rounding_adjustment_idr' => $roundingAdjustment,
            'net_revenue_before_tax_idr' => $netRevenueBeforeTax,
            'min_margin_percent' => $normalized['min_margin_percent'],
            'max_discount_percent' => $normalized['max_discount_percent'],
            'commercial_total_consistent' => $pricingHealth['commercial_total_consistent'],
            'pricing_health' => $pricingHealth,
            'company_price_book_item_count' => $pricingHealth['company_price_book_item_count'],
            'manual_fallback_item_count' => $pricingHealth['manual_fallback_item_count'],
            'missing_price_item_count' => $pricingHealth['missing_price_item_count'],
            'expired_price_item_count' => $pricingHealth['expired_price_item_count'],
            'unavailable_price_item_count' => $pricingHealth['unavailable_price_item_count'],
            'low_margin_item_count' => $pricingHealth['low_margin_item_count'],
            'discount_limit_exceeded_count' => $pricingHealth['discount_limit_exceeded_count'],
            'tax_review_issue_count' => $pricingHealth['tax_review_issue_count'],
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
            'min_margin_percent' => 10.0,
            'max_discount_percent' => 10.0,
            'labor_rate_per_kwp_idr' => 850000.0,
            'logistics_cost_idr' => 1500000.0,
            'other_cost_idr' => 0.0,
            'contingency_percent' => 3.0,
            'tax_percent' => 11.0,
            'tax_mode' => 'exclusive',
            'tax_exempt_item_codes' => '',
            'tax_exempt_categories' => '',
            'services_tax_exempt' => false,
            'contingency_tax_exempt' => false,
            'discount_percent' => 0.0,
            'rounding_unit_idr' => 1.0,
            'item_margin_overrides' => [],
            'item_discount_overrides' => [],
            'pricing_note' => null,
        ];
    }

    /** @return array<string, float> */
    public function unitCostBook(): array
    {
        return [
            'PV-MODULE' => 1650000.0,
            'INV-GRID-HYBRID' => 18000000.0,
            'INV-ON-GRID' => 18000000.0,
            'INV-HYBRID' => 28500000.0,
            'INV-BRACKET-CANOPY' => 850000.0,
            'MOUNT-ROOFTOP' => 350000.0,
            'MOUNT-RAIL-ALU' => 95000.0,
            'MOUNT-MID-CLAMP' => 18000.0,
            'MOUNT-END-CLAMP' => 22000.0,
            'MOUNT-FOOT-HOOK' => 45000.0,
            'MOUNT-SPLICE-BONDING' => 185000.0,
            'ROOF-SEAL-FLASHING' => 650000.0,
            'DC-CABLE-PV1F' => 35000.0,
            'AC-CABLE-LV' => 55000.0,
            'PV-CONNECTOR-PAIR' => 65000.0,
            'DC-CONSUMABLE-SET' => 250000.0,
            'DC-CONDUIT-UV' => 18000.0,
            'DC-ISOLATOR' => 450000.0,
            'DC-FUSE' => 125000.0,
            'DC-FUSE-SET' => 165000.0,
            'DC-SPD' => 850000.0,
            'DC-COMBINER-BOX' => 2800000.0,
            'ACDB-PANEL' => 2500000.0,
            'AC-BREAKER' => 350000.0,
            'AC-SPD' => 750000.0,
            'AC-ISOLATOR-METER' => 950000.0,
            'BATTERY-PACK' => 4500000.0,
            'BATTERY-PROTECTION-BMS' => 3500000.0,
            'BATTERY-BMS-CABINET' => 7500000.0,
            'BATTERY-FUSE-BREAKER' => 2800000.0,
            'BATTERY-ESTOP-SAFETY' => 1250000.0,
            'BESS-FIRE-DETECTION' => 8500000.0,
            'BESS-THERMAL-CONTROL' => 9500000.0,
            'EMS-CONTROLLER' => 18500000.0,
            'MONITORING-DATALOGGER' => 1800000.0,
            'MONITORING-COMM-CABLE' => 12000.0,
            'MONITORING-GATEWAY' => 3500000.0,
            'GROUND-ROD-PIT' => 1250000.0,
            'GROUND-CONDUCTOR-BUSBAR' => 1650000.0,
            'SAFETY-LABEL-LOTO' => 350000.0,
            'FIRE-PPE-ALLOWANCE' => 950000.0,
            'SURVEY-SITE' => 1250000.0,
            'ENGINEERING-DESIGN' => 2500000.0,
            'INSTALLATION-LABOR' => 0.0,
            'COMMISSIONING-REPORT' => 1500000.0,
            'ASBUILT-OM-TRAINING' => 1250000.0,
            'HSE-SUPERVISION' => 2000000.0,
            'PROTECTION-COORDINATION' => 2500000.0,
            'UTILITY-APPLICATION-SLO' => 2000000.0,
            'DELIVERY-MOBILIZATION' => 1500000.0,
            'LABEL-SAFETY' => 250000.0,
            'PV-MODULE-SPARE' => 1650000.0,
            'PV-OPTIMIZER' => 650000.0,
            'PV-RAPID-SHUTDOWN' => 725000.0,
            'PV-CLEANING-KIT' => 850000.0,
            'MOUNT-STANDING-SEAM-CLAMP' => 85000.0,
            'MOUNT-BALLAST-TRAY-BLOCK' => 375000.0,
            'MOUNT-GROUND-PILE-POST' => 525000.0,
            'MOUNT-HDG-STEEL-PURLIN' => 4200000.0,
            'MOUNT-TILT-TRIANGLE' => 550000.0,
            'MOUNT-ANCHOR-CHEMICAL' => 185000.0,
            'MOUNT-ANTI-CORROSION-REPAIR' => 750000.0,
            'MOUNT-WALKWAY-CATWALK' => 4500000.0,
            'HSE-LIFELINE-ANCHOR' => 3500000.0,
            'MC4-BRANCH-CONNECTOR' => 95000.0,
            'DC-JUNCTION-BOX' => 650000.0,
            'DC-BUSBAR-ENCLOSURE' => 1750000.0,
            'DC-CABLE-TRAY-LADDER' => 125000.0,
            'MICRO-INVERTER-OPTION' => 1800000.0,
            'MPPT-CHARGE-CONTROLLER' => 3200000.0,
            'PCS-COMMERCIAL-BESS' => 125000000.0,
            'INV-OFF-GRID' => 24000000.0,
            'INV-VENTILATION-THERMAL' => 3750000.0,
            'INV-SPARE-FAN-COMM' => 1500000.0,
            'AC-RCCB-RCBO' => 850000.0,
            'AC-CONTACTOR-INTERLOCK' => 1450000.0,
            'AC-PROTECTION-RELAY' => 6500000.0,
            'SYNC-PANEL' => 18500000.0,
            'ATS-AMF-STS' => 9500000.0,
            'CT-PT-METERING-SET' => 2750000.0,
            'EXPORT-LIMITER-ZERO-EXPORT' => 8500000.0,
            'POWER-QUALITY-METER' => 12500000.0,
            'HARMONIC-FILTER-OPTION' => 25000000.0,
            'LVMDP-INTEGRATION' => 15000000.0,
            'TRANSFORMER-LV-MV' => 185000000.0,
            'MV-SWITCHGEAR-CUBICLE' => 250000000.0,
            'BATTERY-RACK' => 6500000.0,
            'BATTERY-COMBINER-BUSBAR' => 8500000.0,
            'BATTERY-SMOKE-HEAT-GAS-DETECTOR' => 9500000.0,
            'BATTERY-ROOM-VENTILATION' => 12000000.0,
            'SMART-METER-ENERGY-METER' => 2500000.0,
            'MODBUS-GATEWAY-RTU' => 4500000.0,
            'ETHERNET-SWITCH-LAN' => 1850000.0,
            'SIM-DATA-PLAN' => 1800000.0,
            'SCADA-HMI-OPTION' => 75000000.0,
            'WEATHER-STATION-OPTION' => 35000000.0,
            'PYRANOMETER-REFCELL-SENSOR' => 18000000.0,
            'CLOUD-MONITORING-SUBSCRIPTION' => 3500000.0,
            'GROUND-EQUIPOTENTIAL-BONDING-BAR' => 850000.0,
            'GROUND-EXOTHERMIC-WELDING' => 450000.0,
            'LPS-AIR-TERMINAL-DOWNCONDUCTOR' => 12500000.0,
            'LIGHTNING-COUNTER-OPTION' => 2500000.0,
            'LOTO-KIT' => 1500000.0,
            'PPE-KIT' => 2500000.0,
            'SHADING-ANALYSIS-SERVICE' => 1850000.0,
            'ROOF-STRUCTURAL-ASSESSMENT' => 3500000.0,
            'SOIL-TEST-SERVICE' => 7500000.0,
            'ROOF-REPAIR-REINFORCEMENT-OPTION' => 15000000.0,
            'CONCRETE-FOUNDATION' => 25000000.0,
            'TRENCHING-DUCTBANK-HDPE' => 350000.0,
            'DRAINAGE-WORK-OPTION' => 12500000.0,
            'FENCE-GATE-SECURITY' => 35000000.0,
            'CCTV-SECURITY-LIGHTING' => 15000000.0,
            'EQUIPMENT-SHELTER-BATTERY-ROOM' => 45000000.0,
            'ENERGY-YIELD-SIMULATION' => 2500000.0,
            'SLD-LAYOUT-STRING-DRAWINGS' => 3750000.0,
            'STRUCTURAL-CALCULATION' => 3500000.0,
            'SCAFFOLDING-LIFTING-EQUIPMENT' => 8500000.0,
            'IR-POLARITY-VOC-ISC-TEST' => 300000.0,
            'IV-CURVE-THERMOGRAPHY' => 850000.0,
            'GROUNDING-RESISTANCE-TEST' => 1250000.0,
            'INVERTER-COMMISSIONING-SHEET' => 850000.0,
            'BATTERY-COMMISSIONING-REPORT' => 1500000.0,
            'HSE-WORK-PERMIT-PACKAGE' => 2500000.0,
            'SLO-TECHNICAL-INSPECTION' => 3500000.0,
            'UTILITY-INTERCONNECTION-STUDY' => 15000000.0,
            'PROJECT-INSURANCE-OPTION' => 5000000.0,
            'WAREHOUSING-SITE-STORAGE' => 7500000.0,
            'PROJECT-MANAGEMENT' => 8500000.0,
            'WARRANTY-RESERVE-SPARE-PACK' => 7500000.0,
            'MONTHLY-PERFORMANCE-REPORT' => 1500000.0,
            'CORRECTIVE-MAINTENANCE-ALLOWANCE' => 5000000.0,
        ];
    }

    /** @return array<string, float> */
    private function categoryFallbackCostBook(): array
    {
        return [
            'pv_module' => 1650000.0,
            'inverter' => 18000000.0,
            'mounting' => 95000.0,
            'dc_cable' => 35000.0,
            'dc_electrical' => 250000.0,
            'ac_cable' => 55000.0,
            'ac_electrical' => 750000.0,
            'connector' => 65000.0,
            'protection' => 450000.0,
            'power_conversion' => 18000000.0,
            'battery' => 4500000.0,
            'battery_ess' => 4500000.0,
            'monitoring' => 1250000.0,
            'grounding_safety' => 750000.0,
            'civil_site' => 1500000.0,
            'engineering_tc' => 1500000.0,
            'legal_logistics_om' => 1500000.0,
            'accessory' => 250000.0,
        ];
    }

    private function isSummaryCost(array $metadata): bool
    {
        return in_array((string) ($metadata['cost_group'] ?? ''), ['labor', 'logistics'], true)
            || (string) ($metadata['scope_mode'] ?? '') === 'summary_cost';
    }

    private function isChargeable(array $metadata): bool
    {
        if (array_key_exists('included_in_base_price', $metadata)) {
            return (bool) $metadata['included_in_base_price'];
        }

        $scope = (string) ($metadata['scope_status'] ?? 'required');

        if (in_array($scope, ['utility', 'owner', 'optional', 'not_applicable', 'catalog_candidate'], true)) {
            return false;
        }

        return ($metadata['include_in_boq'] ?? true) !== false;
    }

    /** @return array<string, int> */
    private function scopeCounters(BillOfMaterial $bom): array
    {
        $counters = [
            'total_scope_rows' => $bom->items->count(),
            'base_boq_item_count' => 0,
            'summary_cost_item_count' => 0,
            'optional_addon_item_count' => 0,
            'utility_responsibility_item_count' => 0,
            'owner_responsibility_item_count' => 0,
            'catalog_candidate_item_count' => 0,
            'not_applicable_item_count' => 0,
            'client_visible_item_count' => 0,
            'internal_visible_item_count' => 0,
        ];

        foreach ($bom->items as $item) {
            $metadata = $item->metadata ?? [];
            $mode = (string) ($metadata['scope_mode'] ?? (($metadata['include_in_boq'] ?? true) ? 'base_boq' : 'optional_addon'));

            match ($mode) {
                'base_boq' => $counters['base_boq_item_count']++,
                'summary_cost' => $counters['summary_cost_item_count']++,
                'optional_addon' => $counters['optional_addon_item_count']++,
                'utility_responsibility' => $counters['utility_responsibility_item_count']++,
                'owner_responsibility' => $counters['owner_responsibility_item_count']++,
                'internal_catalog_candidate' => $counters['catalog_candidate_item_count']++,
                'not_applicable' => $counters['not_applicable_item_count']++,
                default => null,
            };

            if (($metadata['include_in_client_pdf'] ?? false) === true) {
                $counters['client_visible_item_count']++;
            }

            if (($metadata['include_in_internal_report'] ?? true) === true) {
                $counters['internal_visible_item_count']++;
            }
        }

        return $counters;
    }

    /** @return array<string, mixed> */
    private function resolveUnitPricing(
        BillOfMaterial $bom,
        BillOfMaterialItem $item,
        array $parameters,
        array $unitCosts,
        array $categoryFallbacks,
        PriceResolver $resolver
    ): array {
        $resolved = $resolver->resolve($item, $bom->company_id, $parameters);

        if ($resolved['resolved'] ?? false) {
            return [
                'unit_cost_idr' => $this->money($resolved['unit_cost_idr'] ?? 0),
                'unit_price_idr' => $this->money($resolved['unit_price_idr'] ?? 0),
                'pricing_source' => 'company_price_book',
                'pricing_state' => (string) ($resolved['state'] ?? 'priced'),
                'pricing_trace' => $resolved['trace'] ?? [],
                'source_margin_percent' => $resolved['margin_percent'] ?? null,
                'warnings' => $resolved['warnings'] ?? [],
            ];
        }

        $fallbackUnitCost = $this->resolveUnitCost($item, $unitCosts, $categoryFallbacks);

        if ($fallbackUnitCost > 0) {
            return [
                'unit_cost_idr' => $fallbackUnitCost,
                'unit_price_idr' => round($fallbackUnitCost * (1 + ($parameters['material_markup_percent'] / 100)), 2),
                'pricing_source' => 'manual_fallback',
                'pricing_state' => 'priced',
                'pricing_trace' => array_merge($resolved['trace'] ?? [], [
                    'resolver' => 'manual_fallback',
                    'fallback_reason' => $resolved['reason'] ?? 'company_price_book_unavailable',
                    'fallback_message' => $resolved['message'] ?? null,
                    'fallback_unit_cost_idr' => $fallbackUnitCost,
                    'resolved_at' => now()->toISOString(),
                ]),
                'source_margin_percent' => $parameters['material_markup_percent'],
                'warnings' => [],
            ];
        }

        return [
            'unit_cost_idr' => 0.0,
            'unit_price_idr' => 0.0,
            'pricing_source' => 'missing_price',
            'pricing_state' => 'needs_pricing',
            'pricing_trace' => array_merge($resolved['trace'] ?? [], [
                'resolver' => 'missing_price',
                'fallback_reason' => $resolved['reason'] ?? 'no_price_available',
                'fallback_message' => $resolved['message'] ?? null,
                'resolved_at' => now()->toISOString(),
            ]),
            'source_margin_percent' => null,
            'warnings' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function excludedPricingDecision(array $metadata, bool $isSummaryCost): array
    {
        $nonChargeableState = (string) ($metadata['pricing_state'] ?? 'excluded_scope');

        if ($nonChargeableState === 'pending') {
            $nonChargeableState = 'excluded_scope';
        }

        return [
            'unit_cost_idr' => 0.0,
            'unit_price_idr' => 0.0,
            'pricing_source' => $isSummaryCost ? 'summary_cost' : $nonChargeableState,
            'pricing_state' => $isSummaryCost ? 'summary_cost' : $nonChargeableState,
            'pricing_trace' => [
                'resolver' => $isSummaryCost ? 'summary_cost' : 'excluded_scope',
                'scope_state' => $isSummaryCost ? 'summary_cost' : $nonChargeableState,
                'resolved_at' => now()->toISOString(),
            ],
            'source_margin_percent' => null,
            'warnings' => [],
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

    /** @return array<string, int> */
    private function emptyPricingHealth(): array
    {
        return [
            'chargeable_item_count' => 0,
            'company_price_book_item_count' => 0,
            'manual_fallback_item_count' => 0,
            'missing_price_item_count' => 0,
            'expired_price_item_count' => 0,
            'unavailable_price_item_count' => 0,
            'low_margin_item_count' => 0,
            'discount_limit_exceeded_count' => 0,
            'tax_review_issue_count' => 0,
            'commercial_total_consistent' => true,
        ];
    }

    private function registerPricingHealth(array &$pricingHealth, array $priceDecision, bool $isChargeable): void
    {
        if (! $isChargeable) {
            return;
        }

        $pricingHealth['chargeable_item_count']++;

        match ($priceDecision['pricing_source']) {
            'company_price_book' => $pricingHealth['company_price_book_item_count']++,
            'manual_fallback' => $pricingHealth['manual_fallback_item_count']++,
            'missing_price' => $pricingHealth['missing_price_item_count']++,
            default => null,
        };

        match ($priceDecision['pricing_state']) {
            'expired_price_warning' => $pricingHealth['expired_price_item_count']++,
            'supplier_unavailable_warning' => $pricingHealth['unavailable_price_item_count']++,
            default => null,
        };
    }

    private function finalPricingStatus(array $pricingHealth, float $grandPrice): string
    {
        if ($grandPrice <= 0 || $pricingHealth['missing_price_item_count'] > 0) {
            return 'missing_price';
        }

        if ($pricingHealth['expired_price_item_count'] > 0) {
            return 'expired_price_warning';
        }

        if ($pricingHealth['unavailable_price_item_count'] > 0) {
            return 'supplier_unavailable_warning';
        }

        if ($pricingHealth['low_margin_item_count'] > 0) {
            return 'margin_review_required';
        }

        if ($pricingHealth['discount_limit_exceeded_count'] > 0) {
            return 'discount_review_required';
        }

        if ($pricingHealth['tax_review_issue_count'] > 0) {
            return 'tax_review_required';
        }

        if (($pricingHealth['commercial_total_consistent'] ?? true) !== true) {
            return 'commercial_total_mismatch';
        }

        $pricedCount = $pricingHealth['company_price_book_item_count'] + $pricingHealth['manual_fallback_item_count'];
        if ($pricingHealth['chargeable_item_count'] > 0 && $pricedCount < $pricingHealth['chargeable_item_count']) {
            return 'partially_priced';
        }

        return 'priced';
    }

    private function pricingStatusWarning(string $status): string
    {
        return match ($status) {
            'missing_price' => 'BOQ pricing is incomplete because one or more required chargeable items have no price.',
            'partially_priced' => 'BOQ pricing is partially priced. Review required BOQ item prices before client release.',
            'expired_price_warning' => 'BOQ pricing uses one or more expired supplier quotes. Refresh the supplier quote before client release.',
            'supplier_unavailable_warning' => 'BOQ pricing uses one or more unavailable supplier items. Confirm replacement or stock before client release.',
            'margin_review_required' => 'BOQ commercial pricing is below the minimum margin guard. Adjust selling price or approval settings before client release.',
            'discount_review_required' => 'BOQ discount exceeds the allowed commercial limit. Reduce the discount or obtain approval before client release.',
            'tax_review_required' => 'BOQ tax settings require review. Confirm tax mode, tax percentage, and tax exempt items.',
            'commercial_total_mismatch' => 'BOQ commercial total does not match the calculated subtotal, tax, discount, and rounding trace.',
            default => 'BOQ pricing requires commercial review before client release.',
        };
    }

    /** @return array<string, mixed> */
    private function applyLineCommercialRules(
        BillOfMaterialItem $item,
        array $priceDecision,
        array $parameters,
        bool $isChargeable
    ): array {
        $unitCost = $this->money($priceDecision['unit_cost_idr'] ?? 0);
        $baseUnitPrice = $this->money($priceDecision['unit_price_idr'] ?? 0);
        $quantity = $this->positiveFloat($item->quantity);
        $pricingState = 'priced';
        $warnings = [];
        $metadata = $item->metadata ?? [];

        if (! $isChargeable) {
            return [
                'unit_price_before_discount_idr' => 0.0,
                'unit_price_idr' => 0.0,
                'line_price_before_discount_idr' => 0.0,
                'item_discount_percent' => 0.0,
                'item_discount_idr' => 0.0,
                'effective_margin_percent' => null,
                'minimum_margin_percent' => $parameters['min_margin_percent'],
                'margin_guard_passed' => true,
                'discount_limit_passed' => true,
                'tax_exempt' => false,
                'pricing_state' => 'excluded_scope',
                'warnings' => [],
            ];
        }

        $overrideMargin = $this->itemOverridePercent($parameters['item_margin_overrides'], $item);
        if ($overrideMargin !== null && $unitCost > 0) {
            $baseUnitPrice = round($unitCost * (1 + ($overrideMargin / 100)), 2);
        }

        $itemDiscountPercent = $this->itemOverridePercent($parameters['item_discount_overrides'], $item) ?? 0.0;
        $itemDiscountPercent = $this->boundedPercent($itemDiscountPercent, 0, 100);
        $lineBeforeDiscount = round($baseUnitPrice * $quantity, 2);
        $itemDiscount = round($lineBeforeDiscount * ($itemDiscountPercent / 100), 2);
        $lineAfterDiscount = round(max(0.0, $lineBeforeDiscount - $itemDiscount), 2);
        $unitPrice = $quantity > 0 ? round($lineAfterDiscount / $quantity, 2) : 0.0;
        $marginBase = $lineAfterDiscount;
        $lineCost = round($unitCost * $quantity, 2);
        $effectiveMargin = $marginBase > 0 && $lineCost > 0
            ? round((($marginBase - $lineCost) / $marginBase) * 100, 2)
            : null;

        $marginGuardPassed = $effectiveMargin === null || $effectiveMargin >= $parameters['min_margin_percent'];
        if (! $marginGuardPassed) {
            $pricingState = 'margin_review_required';
            $warnings[] = sprintf(
                'Margin for %s is %.2f%%, below the %.2f%% minimum guard.',
                $item->item_name,
                $effectiveMargin,
                $parameters['min_margin_percent']
            );
        }

        $discountLimitPassed = $itemDiscountPercent <= $parameters['max_discount_percent'];
        if (! $discountLimitPassed) {
            $pricingState = 'discount_review_required';
            $warnings[] = sprintf(
                'Item discount for %s is %.2f%%, above the %.2f%% limit.',
                $item->item_name,
                $itemDiscountPercent,
                $parameters['max_discount_percent']
            );
        }

        $taxExempt = $this->isTaxExemptItem($item, $metadata, $parameters);

        return [
            'unit_price_before_discount_idr' => $baseUnitPrice,
            'unit_price_idr' => $unitPrice,
            'line_price_before_discount_idr' => $lineBeforeDiscount,
            'item_discount_percent' => $itemDiscountPercent,
            'item_discount_idr' => $itemDiscount,
            'effective_margin_percent' => $effectiveMargin,
            'source_margin_percent' => $priceDecision['source_margin_percent'] ?? null,
            'margin_override_percent' => $overrideMargin,
            'minimum_margin_percent' => $parameters['min_margin_percent'],
            'margin_guard_passed' => $marginGuardPassed,
            'discount_limit_passed' => $discountLimitPassed,
            'tax_exempt' => $taxExempt,
            'pricing_state' => $pricingState,
            'warnings' => $warnings,
        ];
    }

    private function combinePricingState(string $resolverState, string $commercialState): string
    {
        if ($resolverState !== 'priced') {
            return $resolverState;
        }

        return $commercialState;
    }

    private function registerCommercialHealth(array &$pricingHealth, array $commercialDecision, bool $isChargeable): void
    {
        if (! $isChargeable) {
            return;
        }

        if (($commercialDecision['margin_guard_passed'] ?? true) !== true) {
            $pricingHealth['low_margin_item_count']++;
        }

        if (($commercialDecision['discount_limit_passed'] ?? true) !== true) {
            $pricingHealth['discount_limit_exceeded_count']++;
        }
    }

    private function calculateTax(float $taxableSubtotal, float $taxPercent, string $taxMode): float
    {
        if ($taxPercent <= 0 || $taxableSubtotal <= 0) {
            return 0.0;
        }

        if ($taxMode === 'inclusive') {
            return round($taxableSubtotal * ($taxPercent / (100 + $taxPercent)), 2);
        }

        return round($taxableSubtotal * ($taxPercent / 100), 2);
    }

    private function commercialTotalConsistent(
        float $grandPrice,
        float $commercialSubtotal,
        float $tax,
        float $globalDiscount,
        float $roundingAdjustment,
        string $taxMode
    ): bool {
        $expected = $taxMode === 'inclusive'
            ? $commercialSubtotal - $globalDiscount + $roundingAdjustment
            : $commercialSubtotal + $tax - $globalDiscount + $roundingAdjustment;

        return abs(round($expected, 2) - round($grandPrice, 2)) <= 1.0;
    }

    private function roundMoney(float $value, float $unit): float
    {
        $unit = max(1.0, $unit);

        return round(round($value / $unit) * $unit, 2);
    }

    /** @param array<string, mixed> $overrides */
    private function itemOverridePercent(array $overrides, BillOfMaterialItem $item): ?float
    {
        $keys = array_values(array_filter([
            (string) $item->getKey(),
            (string) $item->item_code,
            (string) $item->source_key,
            (string) $item->item_name,
        ], fn (string $value): bool => trim($value) !== ''));

        foreach ($keys as $key) {
            if (array_key_exists($key, $overrides) && is_numeric($overrides[$key])) {
                return $this->boundedPercent($overrides[$key], 0, 1000);
            }
        }

        return null;
    }

    private function isTaxExemptItem(BillOfMaterialItem $item, array $metadata, array $parameters): bool
    {
        if (($metadata['tax_exempt'] ?? false) === true) {
            return true;
        }

        $itemCode = trim((string) $item->item_code);
        $category = trim((string) $item->category);

        return ($itemCode !== '' && in_array($itemCode, $parameters['tax_exempt_item_codes'], true))
            || ($category !== '' && in_array($category, $parameters['tax_exempt_categories'], true));
    }

    /** @return array<int, string> */
    private function listParameter(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        return collect(explode(',', (string) $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
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
            'min_margin_percent' => $this->boundedPercent(
                $parameters['min_margin_percent'] ?? $defaults['min_margin_percent'],
                0,
                100
            ),
            'max_discount_percent' => $this->boundedPercent(
                $parameters['max_discount_percent'] ?? $defaults['max_discount_percent'],
                0,
                100
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
            'tax_mode' => in_array(($parameters['tax_mode'] ?? $defaults['tax_mode']), ['exclusive', 'inclusive'], true)
                ? (string) ($parameters['tax_mode'] ?? $defaults['tax_mode'])
                : $defaults['tax_mode'],
            'tax_exempt_item_codes' => $this->listParameter($parameters['tax_exempt_item_codes'] ?? $defaults['tax_exempt_item_codes']),
            'tax_exempt_categories' => $this->listParameter($parameters['tax_exempt_categories'] ?? $defaults['tax_exempt_categories']),
            'services_tax_exempt' => (bool) ($parameters['services_tax_exempt'] ?? $defaults['services_tax_exempt']),
            'contingency_tax_exempt' => (bool) ($parameters['contingency_tax_exempt'] ?? $defaults['contingency_tax_exempt']),
            'discount_percent' => $this->boundedPercent(
                $parameters['discount_percent'] ?? $defaults['discount_percent'],
                0,
                100
            ),
            'rounding_unit_idr' => in_array((float) ($parameters['rounding_unit_idr'] ?? $defaults['rounding_unit_idr']), [1.0, 100.0, 1000.0, 10000.0], true)
                ? (float) ($parameters['rounding_unit_idr'] ?? $defaults['rounding_unit_idr'])
                : $defaults['rounding_unit_idr'],
            'item_margin_overrides' => is_array($parameters['item_margin_overrides'] ?? null)
                ? $parameters['item_margin_overrides']
                : [],
            'item_discount_overrides' => is_array($parameters['item_discount_overrides'] ?? null)
                ? $parameters['item_discount_overrides']
                : [],
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
