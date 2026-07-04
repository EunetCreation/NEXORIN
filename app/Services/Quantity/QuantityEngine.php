<?php

namespace App\Services\Quantity;

use App\Models\Project;
use App\Models\TechnicalCalculation;

class QuantityEngine
{
    /**
     * Build a deterministic BOM draft from the verified or detailed technical calculation result.
     *
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>, warnings: array<int, string>, source_snapshot: array<string, mixed>}
     */
    public function generate(Project $project, TechnicalCalculation $calculation): array
    {
        $results = $calculation->results ?? [];
        $protection = $results['protection'] ?? [];
        $warnings = [];

        $moduleCount = $this->positiveInt($results['module_count'] ?? 0);
        $inverterCount = max(1, $this->positiveInt($results['inverter_count'] ?? 0));
        $stringCount = $this->positiveInt($results['string_count'] ?? 0);
        $batteryNominalKwh = $this->positiveFloat($results['battery_nominal_kwh'] ?? 0);
        $installedPvKw = $this->positiveFloat($results['installed_pv_kw'] ?? 0);
        $dcCableLengthM = $this->positiveFloat($calculation->dc_cable_length_m ?? 0);
        $acCableLengthM = $this->positiveFloat($calculation->ac_cable_length_m ?? 0);
        $dcCableAreaMm2 = $this->positiveFloat($calculation->dc_cable_area_mm2 ?? 0);
        $acCableAreaMm2 = $this->positiveFloat($calculation->ac_cable_area_mm2 ?? 0);
        $cableMaterial = (string) ($calculation->cable_material ?: 'copper');

        if ($moduleCount === 0) {
            $warnings[] = 'PV module quantity is zero. Run or review the technical calculation before creating a commercial quotation.';
        }

        if ($stringCount === 0 && $moduleCount > 0) {
            $warnings[] = 'String count is zero. Cable and DC protection quantities use a conservative single-string fallback.';
            $stringCount = 1;
        }

        if ($calculation->result_status !== 'verified') {
            $warnings[] = 'The technical calculation has not been engineer verified. This BOM should remain a draft.';
        }

        $items = [];
        $order = 10;

        $this->addItem($items, [
            'category' => 'pv_module',
            'item_code' => 'PV-MODULE',
            'item_name' => 'PV Module',
            'description' => 'Photovoltaic module from the selected datasheet input.',
            'quantity' => $moduleCount,
            'unit' => 'pcs',
            'source_key' => 'results.module_count',
            'sort_order' => $order,
            'specification' => [
                'module_power_wp' => $this->positiveFloat($calculation->module_power_wp),
                'module_area_m2' => $this->positiveFloat($calculation->module_area_m2),
                'array_capacity_kwp' => $installedPvKw,
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'inverter',
            'item_code' => 'INV-GRID-HYBRID',
            'item_name' => $calculation->system_type === 'hybrid' ? 'Hybrid Inverter' : 'Grid-Tied Inverter',
            'description' => 'Inverter quantity derived from total AC power requirement.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.inverter_count',
            'sort_order' => $order,
            'specification' => [
                'inverter_ac_power_kw' => $this->positiveFloat($calculation->inverter_ac_power_kw),
                'total_inverter_ac_kw' => $this->positiveFloat($results['total_inverter_ac_kw'] ?? 0),
                'dc_ac_ratio' => $this->positiveFloat($results['dc_ac_ratio'] ?? 0),
                'mppt_count' => $this->positiveInt($calculation->inverter_mppt_count),
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'mounting',
            'item_code' => 'MOUNT-ROOFTOP',
            'item_name' => 'Rooftop Mounting Set',
            'description' => 'Rails, clamps, fasteners, and rooftop mounting accessories estimated per PV module.',
            'quantity' => $moduleCount,
            'unit' => 'module-set',
            'source_key' => 'results.module_count',
            'sort_order' => $order,
            'specification' => [
                'project_building_type' => $project->building_type,
                'installed_pv_kw' => $installedPvKw,
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_cable',
            'item_code' => 'DC-CABLE-PV1F',
            'item_name' => 'DC Solar Cable',
            'description' => 'Two-pole DC cable length estimated from one-way cable route length and string count.',
            'quantity' => round($dcCableLengthM * max(1, $stringCount) * 2, 1),
            'unit' => 'm',
            'source_key' => 'inputs.dc_cable_length_m',
            'sort_order' => $order,
            'specification' => [
                'material' => $cableMaterial,
                'cross_section_mm2' => $dcCableAreaMm2,
                'string_count' => $stringCount,
                'one_way_length_m' => $dcCableLengthM,
                'voltage_drop_percent' => $this->positiveFloat($results['dc_voltage_drop_percent'] ?? 0),
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'ac_cable',
            'item_code' => 'AC-CABLE-LV',
            'item_name' => 'AC Low Voltage Cable',
            'description' => 'AC cable length from inverter output to the selected connection point.',
            'quantity' => round($acCableLengthM, 1),
            'unit' => 'm',
            'source_key' => 'inputs.ac_cable_length_m',
            'sort_order' => $order,
            'specification' => [
                'material' => $cableMaterial,
                'cross_section_mm2' => $acCableAreaMm2,
                'one_way_length_m' => $acCableLengthM,
                'voltage_drop_percent' => $this->positiveFloat($results['ac_voltage_drop_percent'] ?? 0),
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'connector',
            'item_code' => 'PV-CONNECTOR-PAIR',
            'item_name' => 'PV Connector Pair',
            'description' => 'Connector pairs for PV string terminations and field assembly allowance.',
            'quantity' => max(1, $stringCount * 2),
            'unit' => 'pair',
            'source_key' => 'results.string_count',
            'sort_order' => $order,
            'specification' => [
                'string_count' => $stringCount,
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'protection',
            'item_code' => 'DC-ISOLATOR',
            'item_name' => 'DC Isolator',
            'description' => 'DC isolator for inverter-side PV disconnection.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.dc_isolator_a',
            'sort_order' => $order,
            'specification' => [
                'rating_a' => $this->positiveFloat($protection['dc_isolator_a'] ?? 0),
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'protection',
            'item_code' => 'DC-FUSE',
            'item_name' => 'DC Fuse',
            'description' => 'String fuse quantity estimated from string count and polarity protection requirement.',
            'quantity' => max(1, $stringCount * 2),
            'unit' => 'pcs',
            'source_key' => 'results.protection.dc_fuse_a',
            'sort_order' => $order,
            'specification' => [
                'rating_a' => $this->positiveFloat($protection['dc_fuse_a'] ?? 0),
                'string_count' => $stringCount,
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'protection',
            'item_code' => 'DC-SPD',
            'item_name' => 'DC Surge Protection Device',
            'description' => 'DC SPD for PV input side.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.dc_spd',
            'sort_order' => $order,
            'specification' => [
                'type' => (string) ($protection['dc_spd'] ?? 'Type II DC SPD'),
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'protection',
            'item_code' => 'AC-BREAKER',
            'item_name' => 'AC Circuit Breaker',
            'description' => 'AC breaker for inverter output circuit.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.ac_breaker_a',
            'sort_order' => $order,
            'specification' => [
                'rating_a' => $this->positiveFloat($protection['ac_breaker_a'] ?? 0),
            ],
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'protection',
            'item_code' => 'AC-SPD',
            'item_name' => 'AC Surge Protection Device',
            'description' => 'AC SPD for inverter output side.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.ac_spd',
            'sort_order' => $order,
            'specification' => [
                'type' => (string) ($protection['ac_spd'] ?? 'Type II AC SPD'),
            ],
        ]);
        $order += 10;

        if ($calculation->system_type === 'hybrid' || $batteryNominalKwh > 0) {
            $this->addItem($items, [
                'category' => 'battery',
                'item_code' => 'BATTERY-PACK',
                'item_name' => 'Battery Energy Storage Pack',
                'description' => 'Nominal battery capacity based on critical load, autonomy, DoD, and efficiency.',
                'quantity' => round($batteryNominalKwh, 2),
                'unit' => 'kWh',
                'source_key' => 'results.battery_nominal_kwh',
                'sort_order' => $order,
                'specification' => [
                    'minimum_power_kw' => $this->positiveFloat($results['battery_minimum_power_kw'] ?? 0),
                    'backup_hours' => $this->positiveFloat($calculation->battery_backup_hours),
                    'dod_percent' => $this->positiveFloat($calculation->battery_dod_percent),
                    'efficiency_percent' => $this->positiveFloat($calculation->battery_efficiency_percent),
                ],
            ]);
            $order += 10;

            $this->addItem($items, [
                'category' => 'battery',
                'item_code' => 'BATTERY-PROTECTION-BMS',
                'item_name' => 'Battery Protection and BMS Interface Set',
                'description' => 'Protection, communication, and integration allowance for hybrid battery system.',
                'quantity' => 1,
                'unit' => 'set',
                'source_key' => 'results.battery_nominal_kwh',
                'sort_order' => $order,
                'specification' => [
                    'system_type' => $calculation->system_type,
                ],
            ]);
            $order += 10;
        }

        $this->addItem($items, [
            'category' => 'accessory',
            'item_code' => 'LABEL-SAFETY',
            'item_name' => 'Electrical Label and Safety Sign Set',
            'description' => 'Basic warning labels and safety markings for PV installation handover.',
            'quantity' => 1,
            'unit' => 'lot',
            'source_key' => 'bom.standard_allowance',
            'sort_order' => $order,
            'specification' => [
                'scope' => 'preliminary_bom',
            ],
        ]);

        $summary = [
            'project_number' => $project->project_number,
            'system_type' => $calculation->system_type,
            'technical_calculation_status' => $calculation->result_status,
            'installed_pv_kw' => $installedPvKw,
            'module_count' => $moduleCount,
            'inverter_count' => $inverterCount,
            'string_count' => $stringCount,
            'battery_nominal_kwh' => $batteryNominalKwh,
            'item_count' => count($items),
            'total_cost_idr' => 0,
            'total_price_idr' => 0,
            'pricing_status' => 'not_priced',
        ];

        return [
            'summary' => $summary,
            'items' => $items,
            'warnings' => array_values(array_unique($warnings)),
            'source_snapshot' => [
                'technical_calculation_id' => $calculation->getKey(),
                'technical_calculation_status' => $calculation->result_status,
                'calculated_at' => $calculation->calculated_at?->toISOString(),
                'generated_from_results' => [
                    'installed_pv_kw' => $installedPvKw,
                    'module_count' => $moduleCount,
                    'inverter_count' => $inverterCount,
                    'string_count' => $stringCount,
                    'battery_nominal_kwh' => $batteryNominalKwh,
                    'protection' => $protection,
                ],
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function addItem(array &$items, array $item): void
    {
        if (($item['quantity'] ?? 0) <= 0) {
            return;
        }

        $quantity = round((float) $item['quantity'], 3);
        $unitCost = round((float) ($item['unit_cost_idr'] ?? 0), 2);
        $unitPrice = round((float) ($item['unit_price_idr'] ?? 0), 2);

        $items[] = array_merge([
            'description' => null,
            'specification' => [],
            'unit_cost_idr' => $unitCost,
            'unit_price_idr' => $unitPrice,
            'line_cost_idr' => round($quantity * $unitCost, 2),
            'line_price_idr' => round($quantity * $unitPrice, 2),
            'metadata' => [],
        ], $item, [
            'quantity' => $quantity,
        ]);
    }

    private function positiveInt(mixed $value): int
    {
        return max(0, (int) ceil((float) $value));
    }

    private function positiveFloat(mixed $value): float
    {
        return max(0.0, round((float) $value, 4));
    }
}
