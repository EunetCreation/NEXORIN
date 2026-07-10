<?php

namespace App\Services\Quantity;

use App\Models\Project;
use App\Models\TechnicalCalculation;

class QuantityEngine
{
    /**
     * Build a deterministic EPC-level BOM/BOQ draft from the latest technical calculation.
     *
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>, warnings: array<int, string>, source_snapshot: array<string, mixed>}
     */
    public function generate(Project $project, TechnicalCalculation $calculation): array
    {
        $results = $calculation->results ?? [];
        $protection = $results['protection'] ?? [];
        $warnings = [];

        $systemType = (string) ($calculation->system_type ?: $project->proposed_system ?: 'on_grid');
        $scaleType = strtolower((string) ($project->building_type ?: 'residential'));
        $isCommercial = in_array($scaleType, ['commercial', 'industrial', 'enterprise'], true);
        $isHybrid = $systemType === 'hybrid';
        $isOffGrid = $systemType === 'off_grid';
        $isGridConnected = in_array($systemType, ['on_grid', 'hybrid'], true);
        $hasBattery = $isHybrid || $isOffGrid;
        $roofType = strtolower((string) ($project->relationLoaded('roofSiteSurvey') ? ($project->roofSiteSurvey?->roof_type ?? '') : ''));
        $mountingType = strtolower((string) ($project->relationLoaded('roofSiteSurvey') ? ($project->roofSiteSurvey?->mounting_type ?? '') : ''));
        $isGroundMounted = str_contains($mountingType, 'ground') || str_contains($roofType, 'ground');
        $isRooftop = ! $isGroundMounted;
        $isConcreteRoof = str_contains($roofType, 'concrete') || str_contains($roofType, 'dak') || str_contains($roofType, 'flat');
        $isStandingSeam = str_contains($roofType, 'standing');
        $isLargeProject = $isCommercial || $this->positiveFloat($results['installed_pv_kw'] ?? 0) >= 20;

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
        $rowCount = max(1, (int) ceil(max(1, $moduleCount) / 10));
        $dcCableFinalM = round($dcCableLengthM * max(1, $stringCount) * 2, 1);
        $acCableFinalM = round($acCableLengthM, 1);

        if ($moduleCount === 0) {
            $warnings[] = 'PV module quantity is not available. Complete the technical calculation before issuing a commercial quotation.';
        }

        if ($stringCount === 0 && $moduleCount > 0) {
            $warnings[] = 'String quantity is not available. The BOM uses a conservative single-string allowance until string design is reviewed.';
            $stringCount = 1;
        }

        if ($calculation->result_status !== 'verified') {
            $warnings[] = 'The technical calculation has not been engineer verified. Keep this BOM/BOQ as a draft until review is complete.';
        }

        if ($hasBattery && $batteryNominalKwh <= 0) {
            $warnings[] = 'Hybrid projects require battery capacity. Select or confirm the battery package before issuing a quotation.';
        }

        $items = [];
        $order = 10;

        $this->addItem($items, [
            'category' => 'pv_module',
            'item_code' => 'PV-MODULE',
            'item_name' => 'Solar PV Module',
            'description' => 'PV module quantity derived from the selected module rating and target array capacity.',
            'quantity' => $moduleCount,
            'unit' => 'pcs',
            'source_key' => 'results.module_count',
            'sort_order' => $order,
            'specification' => [
                'module_power_wp' => $this->positiveFloat($calculation->module_power_wp),
                'module_area_m2' => $this->positiveFloat($calculation->module_area_m2),
                'array_capacity_kwp' => $installedPvKw,
            ],
            'metadata' => $this->metadata('material', 'required', true, 'Calculated from PV target capacity and selected module power.', 1.0),
        ]);
        $order += 10;

        if ($isCommercial) {
            $this->addItem($items, [
                'category' => 'pv_module',
                'item_code' => 'PV-ASSET-TAG',
                'item_name' => 'PV Module Asset Tag Set',
                'description' => 'Barcode or asset tagging allowance for warranty tracking and O&M records.',
                'quantity' => $moduleCount,
                'unit' => 'pcs',
                'source_key' => 'results.module_count',
                'sort_order' => $order,
                'specification' => ['basis' => 'Commercial project asset tracking'],
                'metadata' => $this->metadata('material', 'conditional', true, 'Included for commercial projects to support warranty and O&M tracking.', 2.0),
            ]);
            $order += 10;
        }

        $this->addItem($items, [
            'category' => 'mounting',
            'item_code' => 'MOUNT-RAIL-ALU',
            'item_name' => 'Aluminium Mounting Rail',
            'description' => 'Rail length allowance based on module count, estimated row count, and cutting allowance.',
            'quantity' => round(max(0, $moduleCount) * 2.4, 1),
            'unit' => 'm',
            'source_key' => 'bom.mounting_layout_allowance',
            'sort_order' => $order,
            'specification' => [
                'module_count' => $moduleCount,
                'row_count' => $rowCount,
                'building_type' => $scaleType,
            ],
            'metadata' => $this->metadata('structural_bos', 'required', true, 'Estimated from PV module count and rooftop layout allowance.', 5.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'mounting',
            'item_code' => 'MOUNT-MID-CLAMP',
            'item_name' => 'Mid Clamp',
            'description' => 'Clamp between adjacent PV modules.',
            'quantity' => max(0, ($moduleCount - $rowCount) * 2),
            'unit' => 'pcs',
            'source_key' => 'bom.mounting_layout_allowance',
            'sort_order' => $order,
            'specification' => ['basis' => 'Module count and estimated row count'],
            'metadata' => $this->metadata('structural_bos', 'required', true, 'Estimated from modules per row and module spacing.', 5.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'mounting',
            'item_code' => 'MOUNT-END-CLAMP',
            'item_name' => 'End Clamp',
            'description' => 'Clamp for the end of each PV module row.',
            'quantity' => $rowCount * 4,
            'unit' => 'pcs',
            'source_key' => 'bom.mounting_layout_allowance',
            'sort_order' => $order,
            'specification' => ['row_count' => $rowCount],
            'metadata' => $this->metadata('structural_bos', 'required', true, 'Estimated from four end clamps per PV row.', 5.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'mounting',
            'item_code' => 'MOUNT-FOOT-HOOK',
            'item_name' => 'Roof Hook / L-Foot / Hanger Bolt Set',
            'description' => 'Roof interface hardware. Final type depends on roof survey and mounting method.',
            'quantity' => max(1, (int) ceil($moduleCount * 1.4)),
            'unit' => 'pcs',
            'source_key' => 'bom.rooftop_mounting_allowance',
            'sort_order' => $order,
            'specification' => [
                'roof_type' => $project->relationLoaded('roofSiteSurvey') ? ($project->roofSiteSurvey?->roof_type ?? 'To be confirmed by survey') : 'To be confirmed by survey',
                'basis' => 'Rooftop mounting allowance',
            ],
            'metadata' => $this->metadata('structural_bos', 'required', true, 'Estimated from PV module count and roof interface allowance.', 5.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'mounting',
            'item_code' => 'MOUNT-SPLICE-BONDING',
            'item_name' => 'Rail Splice, Bonding Clip, and Earthing Washer Set',
            'description' => 'Small structural and bonding accessories that are often missed in preliminary estimates.',
            'quantity' => max(1, (int) ceil($moduleCount / 8)),
            'unit' => 'set',
            'source_key' => 'bom.mounting_accessory_allowance',
            'sort_order' => $order,
            'specification' => ['basis' => 'Mounting accessory allowance'],
            'metadata' => $this->metadata('structural_bos', 'required', true, 'Allowance for rail continuity, bonding, and rooftop mounting accessories.', 5.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'mounting',
            'item_code' => 'ROOF-SEAL-FLASHING',
            'item_name' => 'Waterproofing Sealant and Roof Flashing',
            'description' => 'Waterproofing allowance for rooftop penetrations and mounting interface.',
            'quantity' => 1,
            'unit' => 'lot',
            'source_key' => 'bom.rooftop_waterproofing_allowance',
            'sort_order' => $order,
            'specification' => ['basis' => 'Rooftop installation allowance'],
            'metadata' => $this->metadata('structural_bos', 'conditional', true, 'Included for rooftop installation. Final quantity follows roof survey and installation method.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_electrical',
            'item_code' => 'DC-CABLE-PV1F',
            'item_name' => 'PV DC Cable Red / Black',
            'description' => 'Two-pole DC cable length based on route length, string count, and cable allowance.',
            'quantity' => $dcCableFinalM,
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
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'Calculated from one-way route length, polarity, string count, and cable allowance.', 8.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_electrical',
            'item_code' => 'PV-CONNECTOR-PAIR',
            'item_name' => 'PV Connector Pair',
            'description' => 'Connector pairs for PV string terminations and field assembly allowance.',
            'quantity' => max(1, $stringCount * 2),
            'unit' => 'pair',
            'source_key' => 'results.string_count',
            'sort_order' => $order,
            'specification' => ['string_count' => $stringCount],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'Estimated from string terminations and installation allowance.', 10.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_electrical',
            'item_code' => 'DC-CONSUMABLE-SET',
            'item_name' => 'Cable Lug, Ferrule, Heat Shrink, Gland, and Marker Set',
            'description' => 'Termination and labeling consumables for DC circuits.',
            'quantity' => max(1, $stringCount),
            'unit' => 'set',
            'source_key' => 'bom.dc_termination_allowance',
            'sort_order' => $order,
            'specification' => ['basis' => 'DC termination consumables'],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'Allowance for termination materials, cable identification, and enclosure entry.', 12.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_electrical',
            'item_code' => 'DC-CONDUIT-UV',
            'item_name' => 'UV-Resistant DC Conduit',
            'description' => 'Outdoor cable protection for PV DC routes.',
            'quantity' => max(1, round($dcCableLengthM * max(1, $stringCount), 1)),
            'unit' => 'm',
            'source_key' => 'inputs.dc_cable_length_m',
            'sort_order' => $order,
            'specification' => ['basis' => 'Outdoor DC cable route'],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'Estimated from outdoor DC route length and string count.', 8.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_electrical',
            'item_code' => 'DC-ISOLATOR',
            'item_name' => 'DC Isolator Switch',
            'description' => 'Manual DC disconnection device for PV input side.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.dc_isolator_a',
            'sort_order' => $order,
            'specification' => ['rating_a' => $this->positiveFloat($protection['dc_isolator_a'] ?? 0)],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'One isolator allowance per inverter input group.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_electrical',
            'item_code' => 'DC-FUSE-SET',
            'item_name' => 'String Fuse Holder and PV Fuse Link Set',
            'description' => 'String protection allowance for parallel PV strings and reverse current mitigation.',
            'quantity' => max(1, $stringCount * 2),
            'unit' => 'pcs',
            'source_key' => 'results.protection.dc_fuse_a',
            'sort_order' => $order,
            'specification' => [
                'rating_a' => $this->positiveFloat($protection['dc_fuse_a'] ?? 0),
                'string_count' => $stringCount,
            ],
            'metadata' => $this->metadata('electrical_bos', 'conditional', true, 'Included when string protection is required. Engineer may revise after final MPPT allocation.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'dc_electrical',
            'item_code' => 'DC-SPD',
            'item_name' => 'DC Surge Protection Device',
            'description' => 'Surge protection on PV DC side.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.dc_spd',
            'sort_order' => $order,
            'specification' => ['type' => (string) ($protection['dc_spd'] ?? 'Type II DC SPD')],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'One DC SPD allowance per inverter or DC input group.', 0.0),
        ]);
        $order += 10;

        if ($isCommercial || $stringCount > 2) {
            $this->addItem($items, [
                'category' => 'dc_electrical',
                'item_code' => 'DC-COMBINER-BOX',
                'item_name' => 'DC Combiner / Junction Box',
                'description' => 'String grouping and transition box for multi-string PV arrays.',
                'quantity' => max(1, (int) ceil($stringCount / 6)),
                'unit' => 'pcs',
                'source_key' => 'results.string_count',
                'sort_order' => $order,
                'specification' => ['string_count' => $stringCount],
                'metadata' => $this->metadata('electrical_bos', 'conditional', true, 'Included for commercial or multi-string projects.', 0.0),
            ]);
            $order += 10;
        }

        $this->addItem($items, [
            'category' => 'power_conversion',
            'item_code' => $isOffGrid ? 'INV-OFF-GRID' : ($isHybrid ? 'INV-HYBRID' : 'INV-ON-GRID'),
            'item_name' => $isOffGrid ? 'Off-Grid Inverter' : ($isHybrid ? 'Hybrid Inverter' : 'On-Grid String Inverter'),
            'description' => 'Inverter quantity derived from the required AC capacity and selected inverter rating.',
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
            'metadata' => $this->metadata('material', 'required', true, 'Calculated from required AC capacity and selected inverter size.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'power_conversion',
            'item_code' => 'INV-BRACKET-CANOPY',
            'item_name' => 'Inverter Bracket and Weather Protection Allowance',
            'description' => 'Mounting bracket and simple weather protection allowance for inverter installation.',
            'quantity' => $inverterCount,
            'unit' => 'set',
            'source_key' => 'results.inverter_count',
            'sort_order' => $order,
            'specification' => ['basis' => 'Per inverter installation set'],
            'metadata' => $this->metadata('material', 'conditional', true, 'Included as installation allowance. Final need depends on inverter location.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'ac_electrical',
            'item_code' => 'AC-CABLE-LV',
            'item_name' => 'AC Low Voltage Cable',
            'description' => 'Cable route from inverter output to connection panel.',
            'quantity' => $acCableFinalM,
            'unit' => 'm',
            'source_key' => 'inputs.ac_cable_length_m',
            'sort_order' => $order,
            'specification' => [
                'material' => $cableMaterial,
                'cross_section_mm2' => $acCableAreaMm2,
                'one_way_length_m' => $acCableLengthM,
                'voltage_drop_percent' => $this->positiveFloat($results['ac_voltage_drop_percent'] ?? 0),
            ],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'Calculated from inverter-to-panel route length and cable allowance.', 5.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'ac_electrical',
            'item_code' => 'ACDB-PANEL',
            'item_name' => 'AC Distribution Box / Inverter AC Panel',
            'description' => 'AC protection and metering panel for inverter output.',
            'quantity' => $inverterCount,
            'unit' => 'set',
            'source_key' => 'results.inverter_count',
            'sort_order' => $order,
            'specification' => ['phase_type' => $project->relationLoaded('loadAnalysis') ? ($project->loadAnalysis?->supply_phase ?? 'To be confirmed') : 'To be confirmed'],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'One AC panel allowance per inverter or inverter group.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'ac_electrical',
            'item_code' => 'AC-BREAKER',
            'item_name' => 'AC Circuit Breaker',
            'description' => 'AC breaker for inverter output circuit.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.ac_breaker_a',
            'sort_order' => $order,
            'specification' => ['rating_a' => $this->positiveFloat($protection['ac_breaker_a'] ?? 0)],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'One breaker allowance per inverter output circuit.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'ac_electrical',
            'item_code' => 'AC-SPD',
            'item_name' => 'AC Surge Protection Device',
            'description' => 'Surge protection on inverter AC output side.',
            'quantity' => $inverterCount,
            'unit' => 'pcs',
            'source_key' => 'results.protection.ac_spd',
            'sort_order' => $order,
            'specification' => ['type' => (string) ($protection['ac_spd'] ?? 'Type II AC SPD')],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'One AC SPD allowance per inverter output group.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'ac_electrical',
            'item_code' => 'AC-ISOLATOR-METER',
            'item_name' => 'AC Isolator and Internal Energy Meter Set',
            'description' => 'Manual AC isolation and internal production or consumption metering allowance.',
            'quantity' => $inverterCount,
            'unit' => 'set',
            'source_key' => 'results.inverter_count',
            'sort_order' => $order,
            'specification' => ['basis' => 'Inverter AC output monitoring and isolation'],
            'metadata' => $this->metadata('electrical_bos', 'required', true, 'Included for safe isolation and internal monitoring.', 0.0),
        ]);
        $order += 10;

        if ($hasBattery) {
            $this->addItem($items, [
                'category' => 'battery_ess',
                'item_code' => 'BATTERY-PACK',
                'item_name' => 'Battery Energy Storage Pack',
                'description' => 'Nominal battery capacity based on critical load, backup duration, DoD, and efficiency.',
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
                'metadata' => $this->metadata('material', 'required', true, 'Calculated from backup requirement and battery usability assumptions.', 0.0),
            ]);
            $order += 10;

            foreach ([
                ['BATTERY-BMS-CABINET', 'Battery Cabinet and BMS Interface Set', 'Battery enclosure, BMS communication, and integration allowance.'],
                ['BATTERY-FUSE-BREAKER', 'Battery Fuse, Breaker, and DC Isolator Set', 'Battery-side short circuit and isolation protection allowance.'],
                ['BATTERY-ESTOP-SAFETY', 'Battery Emergency Stop and Safety Label Set', 'Emergency shutdown and safety labeling allowance for ESS.'],
            ] as $batteryItem) {
                $this->addItem($items, [
                    'category' => 'battery_ess',
                    'item_code' => $batteryItem[0],
                    'item_name' => $batteryItem[1],
                    'description' => $batteryItem[2],
                    'quantity' => 1,
                    'unit' => 'set',
                    'source_key' => 'results.battery_nominal_kwh',
                    'sort_order' => $order,
                    'specification' => ['system_type' => $systemType],
                    'metadata' => $this->metadata('material', 'required', true, 'Required for hybrid battery integration and safe operation.', 0.0),
                ]);
                $order += 10;
            }

            if ($isCommercial || $batteryNominalKwh >= 50) {
                foreach ([
                    ['BESS-FIRE-DETECTION', 'BESS Fire Detection and Suppression Allowance', 'Fire safety package for commercial or larger battery systems.'],
                    ['BESS-THERMAL-CONTROL', 'Battery Room Ventilation / Thermal Control Allowance', 'Thermal management allowance for battery room or battery cabinet area.'],
                    ['EMS-CONTROLLER', 'Energy Management System Controller', 'Control package for battery operating mode, backup, and zero export strategy.'],
                ] as $bessItem) {
                    $this->addItem($items, [
                        'category' => 'battery_ess',
                        'item_code' => $bessItem[0],
                        'item_name' => $bessItem[1],
                        'description' => $bessItem[2],
                        'quantity' => 1,
                        'unit' => 'lot',
                        'source_key' => 'bom.bess_safety_allowance',
                        'sort_order' => $order,
                        'specification' => ['basis' => 'Commercial or larger ESS safety requirement'],
                        'metadata' => $this->metadata('material', 'conditional', true, 'Included when battery scale or site risk requires additional ESS safety systems.', 0.0),
                    ]);
                    $order += 10;
                }
            }
        }

        $this->addItem($items, [
            'category' => 'monitoring',
            'item_code' => 'MONITORING-DATALOGGER',
            'item_name' => 'Data Logger and Basic Monitoring Set',
            'description' => 'Monitoring device for inverter and energy data collection.',
            'quantity' => 1,
            'unit' => 'set',
            'source_key' => 'bom.monitoring_standard',
            'sort_order' => $order,
            'specification' => ['basis' => 'Standard monitoring requirement'],
            'metadata' => $this->metadata('material', 'required', true, 'Included for production monitoring and handover documentation.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'monitoring',
            'item_code' => 'MONITORING-COMM-CABLE',
            'item_name' => 'RS485 / Ethernet Communication Cable and Accessories',
            'description' => 'Communication cable allowance for inverter, meter, and monitoring gateway.',
            'quantity' => max(1, round(($dcCableLengthM + $acCableLengthM) * 0.25, 1)),
            'unit' => 'm',
            'source_key' => 'bom.monitoring_route_allowance',
            'sort_order' => $order,
            'specification' => ['basis' => 'Communication route allowance'],
            'metadata' => $this->metadata('material', 'conditional', true, 'Included when monitoring device requires wired communication.', 8.0),
        ]);
        $order += 10;

        if ($isCommercial) {
            $this->addItem($items, [
                'category' => 'monitoring',
                'item_code' => 'MONITORING-GATEWAY',
                'item_name' => 'Industrial Gateway / Router Allowance',
                'description' => 'Communication gateway allowance for commercial monitoring dashboard and alarm integration.',
                'quantity' => 1,
                'unit' => 'set',
                'source_key' => 'bom.commercial_monitoring_allowance',
                'sort_order' => $order,
                'specification' => ['basis' => 'Commercial monitoring requirement'],
                'metadata' => $this->metadata('material', 'conditional', true, 'Included for commercial projects that need reliable remote monitoring.', 0.0),
            ]);
            $order += 10;
        }

        foreach ([
            ['GROUND-ROD-PIT', 'Grounding Rod, Earth Pit, and Inspection Box Set', 'Grounding electrode and inspection point allowance.'],
            ['GROUND-CONDUCTOR-BUSBAR', 'Grounding Conductor and Grounding Busbar Set', 'Bonding conductor and grounding termination allowance.'],
            ['SAFETY-LABEL-LOTO', 'Warning Label, Cable Marker, and Safety Sign Set', 'Safety labels, circuit markers, and handover identification set.'],
        ] as $safetyItem) {
            $this->addItem($items, [
                'category' => 'grounding_safety',
                'item_code' => $safetyItem[0],
                'item_name' => $safetyItem[1],
                'description' => $safetyItem[2],
                'quantity' => 1,
                'unit' => 'lot',
                'source_key' => 'bom.grounding_safety_standard',
                'sort_order' => $order,
                'specification' => ['basis' => 'Standard grounding and safety requirement'],
                'metadata' => $this->metadata('electrical_bos', 'required', true, 'Required for safe commissioning, operation, and maintenance.', 0.0),
            ]);
            $order += 10;
        }

        $this->addItem($items, [
            'category' => 'grounding_safety',
            'item_code' => 'FIRE-PPE-ALLOWANCE',
            'item_name' => 'Portable Fire Extinguisher and Basic Safety Equipment Allowance',
            'description' => 'Basic project safety and fire response allowance for inverter, panel, and battery area.',
            'quantity' => 1,
            'unit' => 'lot',
            'source_key' => 'bom.site_safety_allowance',
            'sort_order' => $order,
            'specification' => ['basis' => 'Site safety requirement'],
            'metadata' => $this->metadata('electrical_bos', $isCommercial || $isHybrid ? 'required' : 'conditional', true, 'Included based on electrical panel, inverter, and battery safety needs.', 0.0),
        ]);
        $order += 10;

        foreach ([
            ['SURVEY-SITE', 'Site Survey and Load Confirmation', 'Field survey, electrical panel review, roof access review, and load confirmation.'],
            ['ENGINEERING-DESIGN', 'Engineering Design Package', 'SLD, layout, string sizing, cable sizing, protection review, and design assumptions.'],
            ['INSTALLATION-LABOR', 'Mechanical and Electrical Installation Labor', 'Installation labor allowance for PV module, mounting, cable, panel, inverter, and accessories.'],
            ['COMMISSIONING-REPORT', 'Testing, Commissioning, and Handover Report', 'Polarity test, insulation test, Voc/Isc check, grounding check, functional test, and handover records.'],
            ['ASBUILT-OM-TRAINING', 'As-Built Drawing, O&M Manual, and User Training', 'Handover documentation and basic owner/operator training.'],
        ] as $serviceItem) {
            $this->addItem($items, [
                'category' => 'engineering_tc',
                'item_code' => $serviceItem[0],
                'item_name' => $serviceItem[1],
                'description' => $serviceItem[2],
                'quantity' => 1,
                'unit' => 'lot',
                'source_key' => 'bom.service_scope_standard',
                'sort_order' => $order,
                'specification' => ['basis' => 'Project service scope'],
                'metadata' => $this->metadata($serviceItem[0] === 'INSTALLATION-LABOR' ? 'labor' : ($serviceItem[0] === 'COMMISSIONING-REPORT' ? 'testing' : 'engineering'), 'service', true, 'Service line required to avoid missing non-material project costs.', 0.0),
            ]);
            $order += 10;
        }

        if ($isCommercial) {
            foreach ([
                ['HSE-SUPERVISION', 'HSE Officer and Site Supervision Allowance', 'HSE and supervision allowance for commercial project execution.'],
                ['PROTECTION-COORDINATION', 'Protection Coordination Review', 'Commercial protection coordination and integration review.'],
            ] as $commercialService) {
                $this->addItem($items, [
                    'category' => 'engineering_tc',
                    'item_code' => $commercialService[0],
                    'item_name' => $commercialService[1],
                    'description' => $commercialService[2],
                    'quantity' => 1,
                    'unit' => 'lot',
                    'source_key' => 'bom.commercial_service_scope',
                    'sort_order' => $order,
                    'specification' => ['basis' => 'Commercial project requirement'],
                    'metadata' => $this->metadata('engineering', 'service', true, 'Included for commercial project governance and electrical safety review.', 0.0),
                ]);
                $order += 10;
            }
        }

        $this->addItem($items, [
            'category' => 'legal_logistics_om',
            'item_code' => 'UTILITY-APPLICATION-SLO',
            'item_name' => 'Utility Application and SLO Support',
            'description' => 'Administrative support for grid-connected PLTS rooftop application and inspection documentation.',
            'quantity' => $isGridConnected ? 1 : 0,
            'unit' => 'lot',
            'source_key' => 'bom.grid_connected_permit_scope',
            'sort_order' => $order,
            'specification' => ['basis' => 'Grid-connected project documentation'],
            'metadata' => $this->metadata('legal_permit', 'service', true, 'Included as support scope for grid-connected projects. Official utility charges remain subject to actual process.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'legal_logistics_om',
            'item_code' => 'ADVANCED-METER-UTILITY',
            'item_name' => 'Advanced Meter',
            'description' => 'Advanced meter is listed for scope clarity. It is not automatically charged to the customer when handled by the utility.',
            'quantity' => $isGridConnected ? 1 : 0,
            'unit' => 'set',
            'source_key' => 'regulatory.utility_scope',
            'sort_order' => $order,
            'specification' => ['basis' => 'Utility responsibility for eligible grid-connected PLTS rooftop process'],
            'metadata' => $this->metadata('legal_permit', 'utility', false, 'Listed as utility responsibility. It is excluded from customer BOQ total unless company policy changes the scope.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'legal_logistics_om',
            'item_code' => 'DELIVERY-MOBILIZATION',
            'item_name' => 'Delivery, Loading, Unloading, and Mobilization',
            'description' => 'Logistics and mobilization allowance for material and field team movement.',
            'quantity' => 1,
            'unit' => 'lot',
            'source_key' => 'bom.logistics_scope_standard',
            'sort_order' => $order,
            'specification' => ['basis' => 'Project logistics scope'],
            'metadata' => $this->metadata('logistics', 'service', true, 'Service line for delivery and mobilization cost visibility.', 0.0),
        ]);
        $order += 10;

        $this->addItem($items, [
            'category' => 'legal_logistics_om',
            'item_code' => 'OM-ANNUAL-OPTION',
            'item_name' => 'Annual O&M Service Package',
            'description' => 'Optional preventive maintenance, performance review, and reporting package.',
            'quantity' => 1,
            'unit' => 'package',
            'source_key' => 'bom.optional_om_scope',
            'sort_order' => $order,
            'specification' => ['basis' => 'Optional O&M enhancement'],
            'metadata' => $this->metadata('om', 'optional', false, 'Shown as an optional enhancement and excluded from the base BOQ total unless selected.', 0.0),
        ]);
        $order += 10;

        $scopeContext = [
            'is_commercial' => $isCommercial,
            'is_hybrid' => $isHybrid,
            'is_off_grid' => $isOffGrid,
            'is_grid_connected' => $isGridConnected,
            'has_battery' => $hasBattery,
            'is_large_project' => $isLargeProject,
            'is_rooftop' => $isRooftop,
            'is_ground_mounted' => $isGroundMounted,
            'is_concrete_roof' => $isConcreteRoof,
            'is_standing_seam' => $isStandingSeam,
            'module_count' => $moduleCount,
            'inverter_count' => $inverterCount,
            'string_count' => $stringCount,
            'row_count' => $rowCount,
            'dc_route_m' => $dcCableLengthM,
            'ac_route_m' => $acCableLengthM,
            'battery_kwh' => $batteryNominalKwh,
            'installed_pv_kw' => $installedPvKw,
        ];

        $this->appendMasterCatalogScope($items, $order, $scopeContext);

        $items = $this->applyScopeModeControls($items, $scopeContext);
        $scopeSummary = $this->scopeSummary($items);

        $summary = [
            'project_number' => $project->project_number,
            'system_type' => $systemType,
            'scale_type' => $scaleType,
            'technical_calculation_status' => $calculation->result_status,
            'installed_pv_kw' => $installedPvKw,
            'module_count' => $moduleCount,
            'inverter_count' => $inverterCount,
            'string_count' => $stringCount,
            'battery_nominal_kwh' => $batteryNominalKwh,
            'item_count' => count($items),
            'chargeable_item_count' => $scopeSummary['base_boq_item_count'],
            'base_boq_item_count' => $scopeSummary['base_boq_item_count'],
            'summary_cost_item_count' => $scopeSummary['summary_cost_item_count'],
            'optional_addon_item_count' => $scopeSummary['optional_addon_item_count'],
            'utility_responsibility_item_count' => $scopeSummary['utility_responsibility_item_count'],
            'owner_responsibility_item_count' => $scopeSummary['owner_responsibility_item_count'],
            'catalog_candidate_item_count' => $scopeSummary['catalog_candidate_item_count'],
            'not_applicable_item_count' => $scopeSummary['not_applicable_item_count'],
            'client_visible_item_count' => $scopeSummary['client_visible_item_count'],
            'internal_visible_item_count' => $scopeSummary['internal_visible_item_count'],
            'total_cost_idr' => 0,
            'total_price_idr' => 0,
            'pricing_status' => 'not_priced',
            'scope_model' => 'epc_professional_boq',
            'master_catalog_version' => '08B-scope-mode-chargeability-control',
            'master_catalog_item_count' => count($items),
            'scope_control_model' => 'base_boq_plus_internal_catalog_candidates',
            'scope_counters' => $scopeSummary,
        ];

        return [
            'summary' => $summary,
            'items' => $items,
            'warnings' => array_values(array_unique($warnings)),
            'source_snapshot' => [
                'technical_calculation_id' => $calculation->getKey(),
                'technical_calculation_status' => $calculation->result_status,
                'calculated_at' => $calculation->calculated_at?->toISOString(),
                'bom_scope_model' => 'epc_professional_boq',
                'scope_control_model' => '08B-scope-mode-chargeability-control',
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

    /**
     * Convert the expanded EPC catalog into clear report modes and chargeability rules.
     *
     * The master catalog remains visible in internal reports, but it must not silently
     * inflate the base customer price. Base BOQ lines are priced by default. Optional,
     * utility, owner, not-applicable, and internal catalog candidates stay excluded
     * until the company explicitly selects or re-scopes them.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    private function applyScopeModeControls(array $items, array $context): array
    {
        $isCommercial = (bool) ($context['is_commercial'] ?? false);
        $isGroundMounted = (bool) ($context['is_ground_mounted'] ?? false);
        $isOffGrid = (bool) ($context['is_off_grid'] ?? false);
        $isLargeProject = (bool) ($context['is_large_project'] ?? false);

        $essentialCatalogBaseCodes = [
            'GROUND-EQUIPOTENTIAL-BONDING-BAR',
        ];

        $commercialBaseCodes = [
            'PV-MODULE-SPARE',
            'MOUNT-WALKWAY-CATWALK',
            'HSE-LIFELINE-ANCHOR',
            'DC-CABLE-TRAY-LADDER',
            'INV-VENTILATION-THERMAL',
            'INV-SPARE-FAN-COMM',
            'AC-PROTECTION-RELAY',
            'POWER-QUALITY-METER',
            'LVMDP-INTEGRATION',
            'BATTERY-RACK',
            'BATTERY-COMBINER-BUSBAR',
            'BATTERY-SMOKE-HEAT-GAS-DETECTOR',
            'BATTERY-ROOM-VENTILATION',
            'MODBUS-GATEWAY-RTU',
            'ETHERNET-SWITCH-LAN',
            'GROUND-EXOTHERMIC-WELDING',
            'LOTO-KIT',
            'PPE-KIT',
            'IV-CURVE-THERMOGRAPHY',
            'HSE-WORK-PERMIT-PACKAGE',
            'PROJECT-MANAGEMENT',
            'WARRANTY-RESERVE-SPARE-PACK',
        ];

        $groundMountedBaseCodes = [
            'MOUNT-GROUND-PILE-POST',
            'MOUNT-HDG-STEEL-PURLIN',
            'CONCRETE-FOUNDATION',
            'SOIL-TEST-SERVICE',
            'TRENCHING-DUCTBANK-HDPE',
            'FENCE-GATE-SECURITY',
        ];

        $offGridBaseCodes = [
            'MPPT-CHARGE-CONTROLLER',
        ];

        $largeProjectBaseCodes = [
            'PCS-COMMERCIAL-BESS',
            'UTILITY-INTERCONNECTION-STUDY',
        ];

        return array_map(function (array $item) use (
            $isCommercial,
            $isGroundMounted,
            $isOffGrid,
            $isLargeProject,
            $essentialCatalogBaseCodes,
            $commercialBaseCodes,
            $groundMountedBaseCodes,
            $offGridBaseCodes,
            $largeProjectBaseCodes
        ): array {
            $metadata = $item['metadata'] ?? [];
            $itemCode = (string) ($item['item_code'] ?? '');
            $scope = (string) ($metadata['scope_status'] ?? 'required');
            $costGroup = (string) ($metadata['cost_group'] ?? 'material');
            $isSummaryCost = in_array($costGroup, ['labor', 'logistics'], true);
            $isMasterCatalog = ($metadata['catalog_layer'] ?? null) === 'master_catalog'
                || ($item['source_key'] ?? null) === 'bom.master_material_catalog';

            $includeInBasePrice = (bool) ($metadata['include_in_boq'] ?? true);
            $scopeMode = 'base_boq';
            $pricingState = $includeInBasePrice ? 'pending' : 'excluded_scope';
            $requiresEngineerSelection = false;

            if ($scope === 'utility') {
                $scopeMode = 'utility_responsibility';
                $includeInBasePrice = false;
                $pricingState = 'excluded_scope';
            } elseif ($scope === 'owner') {
                $scopeMode = 'owner_responsibility';
                $includeInBasePrice = false;
                $pricingState = 'excluded_scope';
            } elseif ($scope === 'not_applicable') {
                $scopeMode = 'not_applicable';
                $includeInBasePrice = false;
                $pricingState = 'not_applicable';
            } elseif ($scope === 'optional') {
                $scopeMode = 'optional_addon';
                $includeInBasePrice = false;
                $pricingState = 'excluded_scope';
                $requiresEngineerSelection = true;
            } elseif ($isSummaryCost) {
                $scopeMode = 'summary_cost';
                $includeInBasePrice = false;
                $pricingState = 'summary_cost';
            } elseif ($isMasterCatalog) {
                $isCatalogBase = in_array($itemCode, $essentialCatalogBaseCodes, true)
                    || ($isCommercial && in_array($itemCode, $commercialBaseCodes, true))
                    || ($isGroundMounted && in_array($itemCode, $groundMountedBaseCodes, true))
                    || ($isOffGrid && in_array($itemCode, $offGridBaseCodes, true))
                    || ($isLargeProject && in_array($itemCode, $largeProjectBaseCodes, true));

                if ($isCatalogBase) {
                    $scopeMode = 'base_boq';
                    $includeInBasePrice = true;
                    $pricingState = 'pending';
                } else {
                    $scopeMode = 'internal_catalog_candidate';
                    $includeInBasePrice = false;
                    $pricingState = 'catalog_candidate';
                    $requiresEngineerSelection = true;
                    if (! in_array($scope, ['optional', 'not_applicable'], true)) {
                        $metadata['scope_status'] = 'catalog_candidate';
                    }
                }
            }

            $metadata['scope_mode'] = $scopeMode;
            $metadata['include_in_boq'] = $includeInBasePrice;
            $metadata['included_in_base_price'] = $includeInBasePrice;
            $metadata['include_in_pdf'] = true;
            $metadata['include_in_internal_report'] = true;
            $metadata['include_in_client_pdf'] = in_array($scopeMode, ['base_boq', 'selected_addon', 'summary_cost'], true);
            $metadata['requires_engineer_selection'] = $requiresEngineerSelection;
            $metadata['pricing_state'] = $pricingState;
            $metadata['report_visibility'] = $metadata['include_in_client_pdf'] ? 'client_and_internal' : 'internal_only';

            $item['metadata'] = $metadata;

            return $item;
        }, $items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function scopeSummary(array $items): array
    {
        $summary = [
            'total_scope_rows' => count($items),
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

        foreach ($items as $item) {
            $metadata = $item['metadata'] ?? [];
            $mode = (string) ($metadata['scope_mode'] ?? 'base_boq');

            match ($mode) {
                'base_boq' => $summary['base_boq_item_count']++,
                'summary_cost' => $summary['summary_cost_item_count']++,
                'optional_addon' => $summary['optional_addon_item_count']++,
                'utility_responsibility' => $summary['utility_responsibility_item_count']++,
                'owner_responsibility' => $summary['owner_responsibility_item_count']++,
                'internal_catalog_candidate' => $summary['catalog_candidate_item_count']++,
                'not_applicable' => $summary['not_applicable_item_count']++,
                default => null,
            };

            if (($metadata['include_in_client_pdf'] ?? false) === true) {
                $summary['client_visible_item_count']++;
            }

            if (($metadata['include_in_internal_report'] ?? true) === true) {
                $summary['internal_visible_item_count']++;
            }
        }

        return $summary;
    }

    /**
     * Add EPC master-catalog scope lines that are often missed in preliminary PLTS estimates.
     *
     * These items do not force every project to buy every material. Required and applicable
     * conditional lines are priced. Optional, utility, and owner-scope lines are displayed for
     * scope clarity and excluded from the base customer total until selected or re-scoped.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $context
     */
    private function appendMasterCatalogScope(array &$items, int &$order, array $context): void
    {
        $moduleCount = max(1, (int) ($context['module_count'] ?? 1));
        $inverterCount = max(1, (int) ($context['inverter_count'] ?? 1));
        $stringCount = max(1, (int) ($context['string_count'] ?? 1));
        $rowCount = max(1, (int) ($context['row_count'] ?? 1));
        $dcRouteM = max(1.0, (float) ($context['dc_route_m'] ?? 1));
        $acRouteM = max(1.0, (float) ($context['ac_route_m'] ?? 1));
        $batteryKwh = max(0.0, (float) ($context['battery_kwh'] ?? 0));
        $isCommercial = (bool) ($context['is_commercial'] ?? false);
        $isLargeProject = (bool) ($context['is_large_project'] ?? false);
        $isRooftop = (bool) ($context['is_rooftop'] ?? true);
        $isGroundMounted = (bool) ($context['is_ground_mounted'] ?? false);
        $isConcreteRoof = (bool) ($context['is_concrete_roof'] ?? false);
        $isStandingSeam = (bool) ($context['is_standing_seam'] ?? false);
        $isGridConnected = (bool) ($context['is_grid_connected'] ?? true);
        $hasBattery = (bool) ($context['has_battery'] ?? false);
        $isOffGrid = (bool) ($context['is_off_grid'] ?? false);
        $isHybrid = (bool) ($context['is_hybrid'] ?? false);
        $isBessCommercial = $isCommercial || $batteryKwh >= 50;

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'pv_module',
                'item_code' => 'PV-MODULE-SPARE',
                'item_name' => 'PV Module Spare Allowance',
                'description' => 'Spare PV module allowance for warranty replacement and O&M continuity.',
                'quantity' => max(1, (int) ceil($moduleCount * ($isCommercial ? 0.02 : 0.01))),
                'unit' => 'pcs',
                'cost_group' => 'material',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial projects usually keep minimum spare modules; residential projects may keep it as an add-on.',
                'waste_factor_percent' => 0,
            ],
            [
                'category' => 'pv_module',
                'item_code' => 'PV-OPTIMIZER',
                'item_name' => 'Module Optimizer Allowance',
                'description' => 'Module-level optimizer option for shading, mixed orientation, or module-level monitoring needs.',
                'quantity' => $moduleCount,
                'unit' => 'pcs',
                'cost_group' => 'material',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Displayed as an optional enhancement when shading or module-level control is required.',
            ],
            [
                'category' => 'pv_module',
                'item_code' => 'PV-RAPID-SHUTDOWN',
                'item_name' => 'Module-Level Rapid Shutdown Device',
                'description' => 'Module-level shutdown allowance for projects that require rapid shutdown or special safety compliance.',
                'quantity' => $moduleCount,
                'unit' => 'pcs',
                'cost_group' => 'material',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Included in the master scope but excluded until required by code, insurer, or project specification.',
            ],
            [
                'category' => 'pv_module',
                'item_code' => 'PV-CLEANING-KIT',
                'item_name' => 'PV Module Cleaning Kit',
                'description' => 'Basic cleaning kit for owner-managed module cleaning and simple O&M activities.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'om',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Optional O&M add-on for owners who want self-service cleaning tools.',
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-STANDING-SEAM-CLAMP',
                'item_name' => 'Standing Seam Roof Clamp',
                'description' => 'Non-penetrating clamp for standing seam metal roof systems.',
                'quantity' => max(1, (int) ceil($moduleCount * 1.4)),
                'unit' => 'pcs',
                'cost_group' => 'structural_bos',
                'scope_status' => $isStandingSeam ? 'conditional' : 'optional',
                'include_in_boq' => $isStandingSeam,
                'basis' => 'Used when the roof survey confirms a standing seam interface.',
                'waste_factor_percent' => 3,
            ],
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-BALLAST-TRAY-BLOCK',
                'item_name' => 'Ballast Tray and Ballast Block Set',
                'description' => 'Non-penetrating ballast system for flat concrete or roof-deck installations.',
                'quantity' => max(1, (int) ceil($moduleCount / 2)),
                'unit' => 'set',
                'cost_group' => 'structural_bos',
                'scope_status' => $isConcreteRoof ? 'conditional' : 'optional',
                'include_in_boq' => $isConcreteRoof,
                'basis' => 'Used when roof penetration is avoided on concrete or flat roof areas.',
                'waste_factor_percent' => 5,
            ],
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-GROUND-PILE-POST',
                'item_name' => 'Ground Mounting Pile / Post Set',
                'description' => 'Ground-mounted pile or post allowance for field-mounted PV arrays.',
                'quantity' => max(1, (int) ceil($moduleCount / 2)),
                'unit' => 'set',
                'cost_group' => 'structural_bos',
                'scope_status' => $isGroundMounted ? 'required' : 'not_applicable',
                'include_in_boq' => $isGroundMounted,
                'basis' => 'Required for ground-mounted systems and excluded for rooftop projects.',
            ],
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-HDG-STEEL-PURLIN',
                'item_name' => 'HDG Steel Structure and Purlin Set',
                'description' => 'Hot-dip galvanized steel structure allowance for ground mount, carport, or large commercial support frames.',
                'quantity' => max(1, (int) ceil($moduleCount / 10)),
                'unit' => 'lot',
                'cost_group' => 'structural_bos',
                'scope_status' => ($isGroundMounted || $isCommercial) ? 'conditional' : 'optional',
                'include_in_boq' => $isGroundMounted,
                'basis' => 'Activated for ground-mounted, carport, or steel-frame project designs.',
            ],
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-TILT-TRIANGLE',
                'item_name' => 'Tilt Triangle Support Set',
                'description' => 'Tilt support allowance for special module angle requirements.',
                'quantity' => $rowCount,
                'unit' => 'set',
                'cost_group' => 'structural_bos',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Added when the engineering layout requires a dedicated tilt structure.',
            ],
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-ANCHOR-CHEMICAL',
                'item_name' => 'Anchor Bolt and Chemical Anchor Set',
                'description' => 'Concrete or steel anchoring consumables for mounting and equipment supports.',
                'quantity' => max(1, (int) ceil($moduleCount / 4)),
                'unit' => 'set',
                'cost_group' => 'structural_bos',
                'scope_status' => ($isConcreteRoof || $isGroundMounted || $isCommercial) ? 'conditional' : 'optional',
                'include_in_boq' => $isConcreteRoof || $isGroundMounted,
                'basis' => 'Used when the mounting interface requires concrete anchoring or structural fixing.',
            ],
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-ANTI-CORROSION-REPAIR',
                'item_name' => 'Anti-Corrosion Paint and Galvanizing Repair Kit',
                'description' => 'Corrosion protection allowance for cut steel, coastal environment, or site-modified metalwork.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'structural_bos',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => false,
                'basis' => 'Kept as a risk item until the site corrosion class or steel modification scope is confirmed.',
            ],
            [
                'category' => 'mounting',
                'item_code' => 'MOUNT-WALKWAY-CATWALK',
                'item_name' => 'Roof Walkway / Catwalk Allowance',
                'description' => 'Access walkway allowance for safe inspection, cleaning, and maintenance.',
                'quantity' => max(1, (int) ceil($moduleCount / 12)),
                'unit' => 'lot',
                'cost_group' => 'structural_bos',
                'scope_status' => $isCommercial && $isRooftop ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial && $isRooftop,
                'basis' => 'Included for commercial rooftop projects where O&M access must be controlled.',
            ],
            [
                'category' => 'mounting',
                'item_code' => 'HSE-LIFELINE-ANCHOR',
                'item_name' => 'Roof Safety Lifeline and Anchor Point Allowance',
                'description' => 'Fall-protection anchor and lifeline allowance for rooftop work and maintenance access.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'structural_bos',
                'scope_status' => $isCommercial && $isRooftop ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial && $isRooftop,
                'basis' => 'Commercial rooftop sites need controlled fall-protection planning.',
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'dc_electrical',
                'item_code' => 'MC4-BRANCH-CONNECTOR',
                'item_name' => 'MC4 Branch Connector Set',
                'description' => 'Branch connector allowance for approved parallel module branch designs.',
                'quantity' => max(1, $stringCount),
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Only included when the final string design permits branch connectors.',
            ],
            [
                'category' => 'dc_electrical',
                'item_code' => 'DC-JUNCTION-BOX',
                'item_name' => 'DC Junction Box',
                'description' => 'DC transition or grouping enclosure for PV string routing.',
                'quantity' => max(1, (int) ceil($stringCount / 4)),
                'unit' => 'pcs',
                'cost_group' => 'electrical_bos',
                'scope_status' => $stringCount > 2 ? 'conditional' : 'optional',
                'include_in_boq' => $stringCount > 2,
                'basis' => 'Used when routing or grouping requires a DC transition enclosure.',
            ],
            [
                'category' => 'dc_electrical',
                'item_code' => 'DC-BUSBAR-ENCLOSURE',
                'item_name' => 'DC Busbar and Panel Enclosure Allowance',
                'description' => 'Busbar and enclosure allowance for larger DC distribution or BESS DC circuits.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => ($isLargeProject || $hasBattery) ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial && $hasBattery,
                'basis' => 'Activated for larger DC distribution, BESS, or commercial DC grouping.',
            ],
            [
                'category' => 'dc_electrical',
                'item_code' => 'DC-CABLE-TRAY-LADDER',
                'item_name' => 'DC Cable Tray / Ladder and Support Set',
                'description' => 'Cable tray, ladder, and support allowance for controlled DC cable routing.',
                'quantity' => max(1, round($dcRouteM * 0.7, 1)),
                'unit' => 'm',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial projects usually need more controlled cable routing and support.',
                'waste_factor_percent' => 5,
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'power_conversion',
                'item_code' => 'MICRO-INVERTER-OPTION',
                'item_name' => 'Micro Inverter Option',
                'description' => 'Alternative inverter architecture for small systems, high shading, or module-level AC design.',
                'quantity' => $moduleCount,
                'unit' => 'pcs',
                'cost_group' => 'material',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Shown as an alternate architecture, not included in the base design.',
            ],
            [
                'category' => 'power_conversion',
                'item_code' => 'MPPT-CHARGE-CONTROLLER',
                'item_name' => 'MPPT Solar Charge Controller',
                'description' => 'PV charge controller allowance for DC-coupled off-grid or hybrid architectures.',
                'quantity' => max(1, (int) ceil($moduleCount / 12)),
                'unit' => 'pcs',
                'cost_group' => 'material',
                'scope_status' => $isOffGrid ? 'required' : 'optional',
                'include_in_boq' => $isOffGrid,
                'basis' => 'Required for DC-coupled off-grid systems when not integrated in the inverter.',
            ],
            [
                'category' => 'power_conversion',
                'item_code' => 'PCS-COMMERCIAL-BESS',
                'item_name' => 'PCS - Power Conversion System',
                'description' => 'PCS allowance for commercial BESS or AC-coupled storage architecture.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'material',
                'scope_status' => $isBessCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isBessCommercial,
                'basis' => 'Activated for commercial or larger AC-coupled BESS systems.',
            ],
            [
                'category' => 'power_conversion',
                'item_code' => 'INV-VENTILATION-THERMAL',
                'item_name' => 'Inverter Room Ventilation and Thermal Management',
                'description' => 'Ventilation or thermal control allowance for inverter/panel rooms.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'material',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial inverter rooms need thermal control review.',
            ],
            [
                'category' => 'power_conversion',
                'item_code' => 'INV-SPARE-FAN-COMM',
                'item_name' => 'Inverter Spare Fan and Communication Module Allowance',
                'description' => 'Spare part allowance for commercial inverter maintenance continuity.',
                'quantity' => max(1, $inverterCount),
                'unit' => 'set',
                'cost_group' => 'om',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Recommended for commercial O&M and spare-part readiness.',
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'ac_electrical',
                'item_code' => 'AC-RCCB-RCBO',
                'item_name' => 'RCCB / ELCB / RCBO Protection Set',
                'description' => 'Residual-current protection allowance when required by earthing design and inverter configuration.',
                'quantity' => max(1, $inverterCount),
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => 'conditional',
                'include_in_boq' => true,
                'basis' => 'Included as a protection allowance. Engineer may revise based on final earthing and inverter requirements.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'AC-CONTACTOR-INTERLOCK',
                'item_name' => 'AC Contactor and Interlock Set',
                'description' => 'Control switching and interlock allowance for hybrid, export control, or backup circuits.',
                'quantity' => max(1, $inverterCount),
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => ($isHybrid || $isOffGrid || $isGridConnected) ? 'conditional' : 'optional',
                'include_in_boq' => $isHybrid || $isOffGrid,
                'basis' => 'Used for source transfer, control switching, and safety interlock logic.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'AC-PROTECTION-RELAY',
                'item_name' => 'Protection Relay Set',
                'description' => 'Protection relay allowance for OV/UV, OF/UF, reverse power, or external anti-islanding requirements.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial interconnection commonly requires protection coordination and relay review.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'SYNC-PANEL',
                'item_name' => 'Synchronization Panel',
                'description' => 'Synchronization panel allowance for grid, inverter, battery, and generator integration.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => ($isHybrid || $isOffGrid) ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial && ($isHybrid || $isOffGrid),
                'basis' => 'Activated when multiple sources need coordinated operation.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'ATS-AMF-STS',
                'item_name' => 'ATS / AMF / STS Transfer Set',
                'description' => 'Transfer system allowance for backup or multi-source operation.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => ($isHybrid || $isOffGrid) ? 'conditional' : 'optional',
                'include_in_boq' => $isHybrid || $isOffGrid,
                'basis' => 'Required when the system transfers load between grid, inverter, battery, or generator.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'CT-PT-METERING-SET',
                'item_name' => 'CT / PT and Metering Set',
                'description' => 'Current and voltage transformer allowance for protection, export control, and monitoring.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isGridConnected || $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isGridConnected,
                'basis' => 'Included for grid-connected metering, monitoring, and export-control readiness.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'EXPORT-LIMITER-ZERO-EXPORT',
                'item_name' => 'Export Limiter / Zero Export Controller',
                'description' => 'Export limitation controller and control wiring allowance for grid-connected systems.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isGridConnected ? 'conditional' : 'not_applicable',
                'include_in_boq' => $isGridConnected,
                'basis' => 'Included as a default grid-connected control item until the final export policy is confirmed.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'POWER-QUALITY-METER',
                'item_name' => 'Power Quality Meter',
                'description' => 'Power quality metering allowance for THD, PF, voltage events, and load profile review.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Recommended for commercial sites and power quality-sensitive loads.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'HARMONIC-FILTER-OPTION',
                'item_name' => 'Harmonic Filter Option',
                'description' => 'Harmonic mitigation option if power-quality study requires correction.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Only selected after power-quality assessment or utility requirement.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'LVMDP-INTEGRATION',
                'item_name' => 'LVMDP Integration Package',
                'description' => 'Main low-voltage panel integration allowance for commercial sites.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial projects need integration with the main distribution panel.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'TRANSFORMER-LV-MV',
                'item_name' => 'LV/MV Transformer Option',
                'description' => 'Transformer allowance for MV interconnection or isolation requirements.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Only selected when interconnection voltage or isolation design requires it.',
            ],
            [
                'category' => 'ac_electrical',
                'item_code' => 'MV-SWITCHGEAR-CUBICLE',
                'item_name' => 'MV Switchgear / Cubicle Option',
                'description' => 'Medium-voltage switchgear option for larger commercial or MV interconnection projects.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'electrical_bos',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Kept outside the base BOQ until MV interconnection is confirmed.',
            ],
        ]);

        if ($hasBattery) {
            $this->addCatalogGroup($items, $order, [
                [
                    'category' => 'battery_ess',
                    'item_code' => 'BATTERY-RACK',
                    'item_name' => 'Battery Rack Set',
                    'description' => 'Rack allowance for modular battery installation and safe maintenance access.',
                    'quantity' => max(1, (int) ceil($batteryKwh / 20)),
                    'unit' => 'set',
                    'cost_group' => 'material',
                    'scope_status' => $isCommercial ? 'conditional' : 'optional',
                    'include_in_boq' => $isCommercial,
                    'basis' => 'Required for modular rack-mounted battery layouts and commercial ESS rooms.',
                ],
                [
                    'category' => 'battery_ess',
                    'item_code' => 'BATTERY-COMBINER-BUSBAR',
                    'item_name' => 'Battery Combiner Box and Busbar Set',
                    'description' => 'Battery combining and busbar allowance for parallel battery strings or higher-current ESS.',
                    'quantity' => max(1, (int) ceil($batteryKwh / 50)),
                    'unit' => 'set',
                    'cost_group' => 'material',
                    'scope_status' => ($isCommercial || $batteryKwh >= 20) ? 'conditional' : 'optional',
                    'include_in_boq' => $isCommercial || $batteryKwh >= 20,
                    'basis' => 'Activated when battery strings are paralleled or current level requires busbar distribution.',
                ],
                [
                    'category' => 'battery_ess',
                    'item_code' => 'BATTERY-SMOKE-HEAT-GAS-DETECTOR',
                    'item_name' => 'Battery Smoke, Heat, and Gas Detector Set',
                    'description' => 'Battery room or battery cabinet detection allowance for thermal and fire-risk monitoring.',
                    'quantity' => 1,
                    'unit' => 'set',
                    'cost_group' => 'material',
                    'scope_status' => $isBessCommercial ? 'conditional' : 'optional',
                    'include_in_boq' => $isBessCommercial,
                    'basis' => 'Required for commercial BESS risk control and recommended for enclosed battery spaces.',
                ],
                [
                    'category' => 'battery_ess',
                    'item_code' => 'BATTERY-ROOM-VENTILATION',
                    'item_name' => 'Battery Room Ventilation Package',
                    'description' => 'Battery room ventilation allowance for temperature control and gas accumulation risk reduction.',
                    'quantity' => 1,
                    'unit' => 'lot',
                    'cost_group' => 'material',
                    'scope_status' => $isBessCommercial ? 'conditional' : 'optional',
                    'include_in_boq' => $isBessCommercial,
                    'basis' => 'Activated for commercial BESS or battery room installations.',
                ],
            ]);
        }

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'monitoring',
                'item_code' => 'SMART-METER-ENERGY-METER',
                'item_name' => 'Smart Meter / Energy Meter Set',
                'description' => 'Internal smart meter allowance for PV production, consumption, import/export, or battery operation data.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'material',
                'scope_status' => 'required',
                'include_in_boq' => true,
                'basis' => 'Required for reliable monitoring, commissioning, and customer handover data.',
            ],
            [
                'category' => 'monitoring',
                'item_code' => 'MODBUS-GATEWAY-RTU',
                'item_name' => 'Modbus Gateway / RTU Set',
                'description' => 'Gateway allowance for inverter, meter, and device integration.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'material',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial projects need more robust device integration and data collection.',
            ],
            [
                'category' => 'monitoring',
                'item_code' => 'ETHERNET-SWITCH-LAN',
                'item_name' => 'Ethernet Switch and LAN Accessories',
                'description' => 'LAN equipment allowance for monitoring network integration.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'material',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Used when monitoring devices require a local communication network.',
            ],
            [
                'category' => 'monitoring',
                'item_code' => 'SIM-DATA-PLAN',
                'item_name' => 'SIM Card and Data Plan Allowance',
                'description' => 'Cellular data allowance for sites without reliable LAN access.',
                'quantity' => 1,
                'unit' => 'package',
                'cost_group' => 'om',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Recurring data plan is shown separately and excluded unless selected.',
            ],
            [
                'category' => 'monitoring',
                'item_code' => 'SCADA-HMI-OPTION',
                'item_name' => 'SCADA / HMI Option',
                'description' => 'SCADA or HMI option for commercial plant monitoring and alarm management.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'material',
                'scope_status' => $isCommercial ? 'optional' : 'not_applicable',
                'include_in_boq' => false,
                'basis' => 'Selected only when the client requires plant-level monitoring and alarm visualization.',
            ],
            [
                'category' => 'monitoring',
                'item_code' => 'WEATHER-STATION-OPTION',
                'item_name' => 'Weather Station Option',
                'description' => 'Weather station option for irradiance, temperature, and performance-ratio analysis.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'material',
                'scope_status' => $isCommercial ? 'optional' : 'not_applicable',
                'include_in_boq' => false,
                'basis' => 'Selected for commercial performance analysis and O&M contracts.',
            ],
            [
                'category' => 'monitoring',
                'item_code' => 'PYRANOMETER-REFCELL-SENSOR',
                'item_name' => 'Pyranometer / Reference Cell and Temperature Sensor Set',
                'description' => 'Irradiance and temperature sensor package for performance analysis.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'material',
                'scope_status' => $isCommercial ? 'optional' : 'not_applicable',
                'include_in_boq' => false,
                'basis' => 'Selected when performance ratio or contractual O&M reporting is required.',
            ],
            [
                'category' => 'monitoring',
                'item_code' => 'CLOUD-MONITORING-SUBSCRIPTION',
                'item_name' => 'Cloud Monitoring Subscription',
                'description' => 'Subscription allowance for cloud monitoring platform beyond the included period.',
                'quantity' => 1,
                'unit' => 'year',
                'cost_group' => 'om',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Recurring platform cost is shown separately unless included in the contract.',
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'grounding_safety',
                'item_code' => 'GROUND-EQUIPOTENTIAL-BONDING-BAR',
                'item_name' => 'Equipotential Bonding Bar',
                'description' => 'Bonding bar allowance for safe equipotential connection of equipment metalwork.',
                'quantity' => 1,
                'unit' => 'pcs',
                'cost_group' => 'electrical_bos',
                'scope_status' => 'required',
                'include_in_boq' => true,
                'basis' => 'Required to support safe grounding and bonding practice.',
            ],
            [
                'category' => 'grounding_safety',
                'item_code' => 'GROUND-EXOTHERMIC-WELDING',
                'item_name' => 'Exothermic Welding Allowance',
                'description' => 'Permanent grounding connection allowance for main earthing points.',
                'quantity' => max(1, $inverterCount),
                'unit' => 'joint',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Recommended for commercial grounding reliability and permanent earth connections.',
            ],
            [
                'category' => 'grounding_safety',
                'item_code' => 'LPS-AIR-TERMINAL-DOWNCONDUCTOR',
                'item_name' => 'Lightning Air Terminal and Down Conductor Allowance',
                'description' => 'Lightning protection allowance when risk assessment or site condition requires LPS integration.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'electrical_bos',
                'scope_status' => $isCommercial || $isRooftop ? 'conditional' : 'optional',
                'include_in_boq' => false,
                'basis' => 'Shown for LPS review. It stays excluded until lightning risk assessment confirms scope.',
            ],
            [
                'category' => 'grounding_safety',
                'item_code' => 'LIGHTNING-COUNTER-OPTION',
                'item_name' => 'Lightning Counter Option',
                'description' => 'Lightning event counter option for monitored LPS systems.',
                'quantity' => 1,
                'unit' => 'pcs',
                'cost_group' => 'electrical_bos',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Selected when the owner wants lightning event monitoring.',
            ],
            [
                'category' => 'grounding_safety',
                'item_code' => 'LOTO-KIT',
                'item_name' => 'Lockout-Tagout Kit',
                'description' => 'LOTO kit allowance for safe maintenance and commissioning isolation.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'om',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Included for commercial maintenance safety readiness.',
            ],
            [
                'category' => 'grounding_safety',
                'item_code' => 'PPE-KIT',
                'item_name' => 'Project PPE Kit',
                'description' => 'PPE allowance for construction, commissioning, and early O&M activities.',
                'quantity' => 1,
                'unit' => 'set',
                'cost_group' => 'om',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Included for commercial project HSE readiness.',
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'civil_site',
                'item_code' => 'SHADING-ANALYSIS-SERVICE',
                'item_name' => 'Shading Analysis Service',
                'description' => 'Site shading assessment used to validate layout and energy yield assumptions.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'engineering',
                'scope_status' => 'service',
                'include_in_boq' => true,
                'basis' => 'Required to reduce energy-yield and layout uncertainty.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'ROOF-STRUCTURAL-ASSESSMENT',
                'item_name' => 'Roof Structural Assessment',
                'description' => 'Structural assessment allowance for rooftop load capacity and mounting safety review.',
                'quantity' => $isRooftop ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'engineering',
                'scope_status' => 'service',
                'include_in_boq' => true,
                'basis' => 'Required for rooftop projects before final installation release.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'SOIL-TEST-SERVICE',
                'item_name' => 'Soil Test Service',
                'description' => 'Soil investigation allowance for ground-mounted foundation design.',
                'quantity' => $isGroundMounted ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'engineering',
                'scope_status' => 'service',
                'include_in_boq' => $isGroundMounted,
                'basis' => 'Required for ground-mounted or foundation-supported projects.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'ROOF-REPAIR-REINFORCEMENT-OPTION',
                'item_name' => 'Roof Repair / Reinforcement Option',
                'description' => 'Allowance placeholder for roof repair or reinforcement discovered during survey.',
                'quantity' => $isRooftop ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'civil',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Excluded until structural assessment identifies a confirmed repair scope.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'CONCRETE-FOUNDATION',
                'item_name' => 'Concrete Foundation Allowance',
                'description' => 'Concrete foundation allowance for ground structure, inverter pad, BESS pad, or equipment shelter.',
                'quantity' => $isGroundMounted || $isBessCommercial ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'civil',
                'scope_status' => ($isGroundMounted || $isBessCommercial) ? 'conditional' : 'optional',
                'include_in_boq' => $isGroundMounted,
                'basis' => 'Activated when equipment requires concrete pad or ground-mounted support.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'TRENCHING-DUCTBANK-HDPE',
                'item_name' => 'Trenching, Duct Bank, and HDPE Conduit',
                'description' => 'Underground cable route allowance for ground, long-route, or protected cable installations.',
                'quantity' => max(1, round(($dcRouteM + $acRouteM) * 0.25, 1)),
                'unit' => 'm',
                'cost_group' => 'civil',
                'scope_status' => ($isGroundMounted || $isCommercial) ? 'conditional' : 'optional',
                'include_in_boq' => $isGroundMounted,
                'basis' => 'Used when cables run underground or require duct-bank protection.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'DRAINAGE-WORK-OPTION',
                'item_name' => 'Drainage Work Option',
                'description' => 'Drainage improvement option for ground-mounted arrays or BESS areas.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'civil',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Selected only after site grading and water-flow review.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'FENCE-GATE-SECURITY',
                'item_name' => 'Fence and Gate Security Package',
                'description' => 'Fence and gate allowance for asset security on ground-mounted or commercial sites.',
                'quantity' => $isGroundMounted || $isCommercial ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'civil',
                'scope_status' => $isGroundMounted ? 'required' : 'optional',
                'include_in_boq' => $isGroundMounted,
                'basis' => 'Required for exposed ground assets and optional for commercial rooftop assets.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'CCTV-SECURITY-LIGHTING',
                'item_name' => 'CCTV and Security Lighting Option',
                'description' => 'Security monitoring and lighting option for protected PV, BESS, or ground-mounted assets.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'civil',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Optional owner security enhancement.',
            ],
            [
                'category' => 'civil_site',
                'item_code' => 'EQUIPMENT-SHELTER-BATTERY-ROOM',
                'item_name' => 'Equipment Shelter / Battery Room Allowance',
                'description' => 'Shelter or dedicated battery room allowance for inverter, panel, PCS, or BESS equipment.',
                'quantity' => $isCommercial || $hasBattery ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'civil',
                'scope_status' => $isBessCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isBessCommercial,
                'basis' => 'Activated for larger ESS, exposed equipment, or dedicated electrical rooms.',
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'engineering_tc',
                'item_code' => 'ENERGY-YIELD-SIMULATION',
                'item_name' => 'Energy Yield Simulation',
                'description' => 'Energy model allowance for annual production, loss assumptions, and performance forecast.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'engineering',
                'scope_status' => 'service',
                'include_in_boq' => true,
                'basis' => 'Required to support savings, payback, and performance assumptions.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'SLD-LAYOUT-STRING-DRAWINGS',
                'item_name' => 'SLD, Layout, String, and Cable Routing Drawings',
                'description' => 'Engineering drawing package for single-line diagram, layout, string mapping, and cable route planning.',
                'quantity' => 1,
                'unit' => 'package',
                'cost_group' => 'engineering',
                'scope_status' => 'service',
                'include_in_boq' => true,
                'basis' => 'Required for engineering review, procurement, installation, and handover control.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'STRUCTURAL-CALCULATION',
                'item_name' => 'Structural Calculation',
                'description' => 'Structural calculation allowance for rooftop or ground-mounted support design.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'engineering',
                'scope_status' => 'service',
                'include_in_boq' => $isRooftop || $isGroundMounted,
                'basis' => 'Required before final release of mounting and roof/ground structure scope.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'SCAFFOLDING-LIFTING-EQUIPMENT',
                'item_name' => 'Scaffolding / Lifting Equipment Allowance',
                'description' => 'Temporary access, lifting, hoist, crane, or forklift allowance for installation logistics.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'labor',
                'scope_status' => $isCommercial || $isRooftop ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial and difficult-access rooftops need lifting and safe access planning.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'IR-POLARITY-VOC-ISC-TEST',
                'item_name' => 'Insulation, Polarity, Voc/Isc String Test Package',
                'description' => 'Electrical testing package for PV string safety and commissioning readiness.',
                'quantity' => max(1, $stringCount),
                'unit' => 'string',
                'cost_group' => 'testing',
                'scope_status' => 'service',
                'include_in_boq' => true,
                'basis' => 'Required for DC commissioning and handover evidence.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'IV-CURVE-THERMOGRAPHY',
                'item_name' => 'I-V Curve and Thermography Test Option',
                'description' => 'Advanced performance and hotspot inspection option.',
                'quantity' => max(1, $stringCount),
                'unit' => 'string',
                'cost_group' => 'testing',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Recommended for commercial commissioning and quality documentation.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'GROUNDING-RESISTANCE-TEST',
                'item_name' => 'Grounding Resistance Test',
                'description' => 'Grounding test allowance for commissioning, safety verification, and handover documentation.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'testing',
                'scope_status' => 'service',
                'include_in_boq' => true,
                'basis' => 'Required to verify grounding performance before operation.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'INVERTER-COMMISSIONING-SHEET',
                'item_name' => 'Inverter Commissioning Sheet',
                'description' => 'Inverter firmware, grid profile, MPPT mapping, communication, and alarm verification record.',
                'quantity' => max(1, $inverterCount),
                'unit' => 'unit',
                'cost_group' => 'testing',
                'scope_status' => 'service',
                'include_in_boq' => true,
                'basis' => 'Required for commissioning control and final handover.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'BATTERY-COMMISSIONING-REPORT',
                'item_name' => 'Battery Commissioning Report',
                'description' => 'Battery SOC, BMS communication, breaker, E-stop, ventilation, and safety verification record.',
                'quantity' => $hasBattery ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'testing',
                'scope_status' => 'service',
                'include_in_boq' => $hasBattery,
                'basis' => 'Required for hybrid/off-grid battery safety and handover control.',
            ],
            [
                'category' => 'engineering_tc',
                'item_code' => 'HSE-WORK-PERMIT-PACKAGE',
                'item_name' => 'HSE Work Permit and Pre-Installation Checklist',
                'description' => 'HSE permit, material receiving, site access, and pre-installation control package.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'engineering',
                'scope_status' => $isCommercial ? 'service' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial projects need stronger HSE governance and pre-installation control.',
            ],
        ]);

        $this->addCatalogGroup($items, $order, [
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'SLO-TECHNICAL-INSPECTION',
                'item_name' => 'SLO / Technical Inspection Support',
                'description' => 'Support allowance for technical inspection documentation and compliance follow-up.',
                'quantity' => $isGridConnected ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'legal_permit',
                'scope_status' => 'service',
                'include_in_boq' => $isGridConnected,
                'basis' => 'Included for grid-connected projects that require inspection or SLO support.',
            ],
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'UTILITY-INTERCONNECTION-STUDY',
                'item_name' => 'Utility Interconnection Study Allowance',
                'description' => 'Utility interconnection study allowance for commercial, larger, or special grid-connected projects.',
                'quantity' => $isGridConnected && ($isCommercial || $isLargeProject) ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'legal_permit',
                'scope_status' => 'conditional',
                'include_in_boq' => $isGridConnected && ($isCommercial || $isLargeProject),
                'basis' => 'Activated when the grid connection needs additional utility study or interconnection review.',
            ],
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'PROJECT-INSURANCE-OPTION',
                'item_name' => 'Project Insurance Option',
                'description' => 'CAR/EAR, liability, or asset insurance option for project risk management.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'commercial',
                'scope_status' => $isCommercial ? 'optional' : 'optional',
                'include_in_boq' => false,
                'basis' => 'Excluded from the base price until insurance scope is selected.',
            ],
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'WAREHOUSING-SITE-STORAGE',
                'item_name' => 'Warehousing / Site Storage Allowance',
                'description' => 'Temporary warehousing or controlled site storage allowance for larger deliveries.',
                'quantity' => $isCommercial ? 1 : 0,
                'unit' => 'lot',
                'cost_group' => 'logistics',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => false,
                'basis' => 'Shown for commercial logistics planning and selected when storage scope is confirmed.',
            ],
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'PROJECT-MANAGEMENT',
                'item_name' => 'Project Management Allowance',
                'description' => 'Project coordination, reporting, meeting, and stakeholder management allowance.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'engineering',
                'scope_status' => $isCommercial ? 'service' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Commercial projects require project management and reporting controls.',
            ],
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'WARRANTY-RESERVE-SPARE-PACK',
                'item_name' => 'Warranty Reserve and Spare Parts Package',
                'description' => 'Spare parts and warranty reserve allowance for O&M continuity.',
                'quantity' => 1,
                'unit' => 'package',
                'cost_group' => 'om',
                'scope_status' => $isCommercial ? 'conditional' : 'optional',
                'include_in_boq' => $isCommercial,
                'basis' => 'Recommended for commercial warranty and O&M readiness.',
            ],
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'MONTHLY-PERFORMANCE-REPORT',
                'item_name' => 'Monthly Performance Report Option',
                'description' => 'Monthly production, PR, downtime, and corrective action reporting option.',
                'quantity' => 12,
                'unit' => 'month',
                'cost_group' => 'om',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Selected when the client purchases ongoing O&M reporting.',
            ],
            [
                'category' => 'legal_logistics_om',
                'item_code' => 'CORRECTIVE-MAINTENANCE-ALLOWANCE',
                'item_name' => 'Corrective Maintenance Allowance Option',
                'description' => 'Corrective maintenance allowance for troubleshooting visits and minor repairs after handover.',
                'quantity' => 1,
                'unit' => 'lot',
                'cost_group' => 'om',
                'scope_status' => 'optional',
                'include_in_boq' => false,
                'basis' => 'Excluded unless the O&M contract includes corrective maintenance response.',
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $catalogItems
     */
    private function addCatalogGroup(array &$items, int &$order, array $catalogItems): void
    {
        foreach ($catalogItems as $catalogItem) {
            $this->addCatalogItem($items, $order, $catalogItem);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $catalogItem
     */
    private function addCatalogItem(array &$items, int &$order, array $catalogItem): void
    {
        $quantity = (float) ($catalogItem['quantity'] ?? 0);

        if ($quantity <= 0) {
            return;
        }

        $metadata = $this->metadata(
            $catalogItem['cost_group'] ?? 'material',
            $catalogItem['scope_status'] ?? 'conditional',
            (bool) ($catalogItem['include_in_boq'] ?? true),
            $catalogItem['basis'] ?? 'Master material catalog rule',
            (float) ($catalogItem['waste_factor_percent'] ?? 0.0)
        );
        $metadata['catalog_layer'] = 'master_catalog';
        $metadata['catalog_version'] = '08A-epc-master-scope';
        $metadata['requires_engineer_selection'] = ($catalogItem['include_in_boq'] ?? true) !== true;

        $this->addItem($items, [
            'category' => $catalogItem['category'],
            'item_code' => $catalogItem['item_code'],
            'item_name' => $catalogItem['item_name'],
            'description' => $catalogItem['description'] ?? null,
            'quantity' => $quantity,
            'unit' => $catalogItem['unit'] ?? 'lot',
            'source_key' => $catalogItem['source_key'] ?? 'bom.master_material_catalog',
            'sort_order' => $order,
            'specification' => $catalogItem['specification'] ?? ['basis' => $catalogItem['basis'] ?? 'Master material catalog rule'],
            'metadata' => $metadata,
        ]);
        $order += 10;
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
        $metadata = $item['metadata'] ?? [];
        $metadata['include_in_boq'] = (bool) ($metadata['include_in_boq'] ?? true);
        $metadata['include_in_pdf'] = (bool) ($metadata['include_in_pdf'] ?? true);
        $metadata['pricing_state'] = $metadata['include_in_boq'] ? 'pending' : 'excluded_scope';

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
            'metadata' => $metadata,
        ]);
    }

    /** @return array<string, mixed> */
    private function metadata(string $costGroup, string $scopeStatus, bool $includeInBoq, string $basis, float $wasteFactorPercent): array
    {
        return [
            'cost_group' => $costGroup,
            'scope_status' => $scopeStatus,
            'scope_mode' => $includeInBoq ? 'base_boq' : 'optional_addon',
            'include_in_boq' => $includeInBoq,
            'included_in_base_price' => $includeInBoq,
            'include_in_pdf' => true,
            'include_in_internal_report' => true,
            'include_in_client_pdf' => $includeInBoq,
            'requires_engineer_selection' => ! $includeInBoq,
            'calculation_basis' => $basis,
            'waste_factor_percent' => $wasteFactorPercent,
        ];
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
