<?php

namespace App\Services\Sld;

use App\Models\Project;
use App\Models\TechnicalCalculation;

/**
 * Deterministic APP67L.2 physical PLTS topology plan.
 *
 * This service converts the verified calculation allocation into explicit
 * equipment instances. It deliberately keeps commercial/product selection in
 * Material Catalog/BOM authority while giving the SLD enough physical detail
 * for protection, PE, communication, routing, schedules, and downstream audit.
 */
final class SldProfessionalTopologyPlan
{
    public const VERSION = '67L.2-R4';
    public const PROFILE = 'professional_physical';

    public function __construct(private readonly SldSymbolRegistry $symbolRegistry)
    {
    }

    /**
     * @return array{
     *   nodes:array<int,array<string,mixed>>,
     *   connections:array<int,array<string,mixed>>,
     *   positions:array<string,array<string,mixed>>,
     *   meta:array<string,mixed>
     * }
     */
    public function build(Project $project, ?TechnicalCalculation $calculation, array $results, string $systemType): array
    {
        $assignments = $this->assignments($calculation, $results);
        $inverterCount = max(count($assignments), $this->integer($results['inverter_count'] ?? null));
        if ($inverterCount <= 0) {
            $inverterCount = 1;
        }
        if ($assignments === []) {
            $assignments = $this->fallbackAssignments($calculation, $results, $inverterCount);
        }

        $protection = is_array($results['protection'] ?? null) ? $results['protection'] : [];
        $modulePowerWp = $this->number($calculation?->module_power_wp);
        $unitAcKw = $this->number($results['inverter_unit_ac_kw'] ?? $calculation?->inverter_ac_power_kw);
        $aggregateAcKw = $this->number($results['total_inverter_ac_kw'] ?? ($unitAcKw * $inverterCount));
        $acVoltage = $this->number($results['ac_nominal_voltage_v'] ?? null);
        $unitAcCurrent = $this->number($results['inverter_unit_rated_current_a'] ?? null);
        $dcVoltage = $this->number($calculation?->inverter_max_dc_voltage_v);
        $dcIsolatorA = $this->number($protection['dc_isolator_a'] ?? null);
        $dcFuseA = $this->number($protection['string_fuse_a'] ?? $protection['dc_fuse_a'] ?? null);
        $acBreakerA = $this->number($protection['inverter_feeder_breaker_a'] ?? $protection['ac_breaker_a'] ?? null);
        $acIsolatorA = $this->number($protection['inverter_feeder_isolator_a'] ?? $acBreakerA);
        $mainAcBreakerA = $this->number($protection['main_ac_breaker_a'] ?? null);
        $pccIsolatorA = $this->number($protection['pcc_isolator_a'] ?? $mainAcBreakerA);
        $acCombinerBusA = $this->number($protection['ac_combiner_bus_a'] ?? $mainAcBreakerA);
        $exportPolicy = (string) ($project->systemRecommendation?->export_policy ?? '');
        $plantControlRequired = in_array($exportPolicy, ['zero_export', 'limited'], true);

        $nodes = [];
        $connections = [];
        $positions = [];
        $rowGap = 300.0;
        $firstRowY = 80.0;

        foreach ($assignments as $index => $assignment) {
            $instance = $index + 1;
            $suffix = sprintf('%02d', $instance);
            $rowY = $firstRowY + ($index * $rowGap);
            $strings = array_values(array_filter(
                is_array($assignment['strings'] ?? null) ? $assignment['strings'] : [],
                'is_array'
            ));
            $stringCounts = array_values(array_map(
                static fn (array $string): int => max(0, (int) ($string['modules'] ?? 0)),
                $strings
            ));
            $assignedModules = $this->integer($assignment['assigned_module_count'] ?? array_sum($stringCounts));
            $assignedDcKw = $this->number($assignment['assigned_dc_kw'] ?? (($assignedModules * $modulePowerWp) / 1000));
            $usedMppt = $this->integer($assignment['used_mppt_count'] ?? count($strings));
            $availableMppt = max(1, $this->integer($assignment['available_mppt_count'] ?? $calculation?->inverter_mppt_count));
            $dcAcRatio = $this->number($assignment['dc_ac_ratio'] ?? ($unitAcKw > 0 ? $assignedDcKw / $unitAcKw : 0));
            $stringLabel = $this->stringGroupLabel($strings, $assignedModules);

            $ids = [
                'pv' => "pv_group_{$suffix}",
                'dc_iso' => "dc_isolator_{$suffix}",
                'dc_spd' => "dc_spd_{$suffix}",
                'inv' => "inverter_{$suffix}",
                'ac_breaker' => "ac_breaker_{$suffix}",
                'ac_iso' => "ac_isolator_{$suffix}",
            ];

            $nodes[] = $this->node(
                $ids['pv'], 'pv_array', "PVA-{$suffix}", "PV string group {$instance}",
                $stringLabel,
                sprintf('%s kWp · %d/%d MPPT', $this->display($assignedDcKw, 2), $usedMppt, $availableMppt),
                'generation', 'pv_array', $this->position(40, $rowY), true,
                [
                    'rated_power' => $assignedDcKw,
                    'rated_voltage' => $this->number($results['string_vmp_hot_v'] ?? null),
                    'rated_current' => $this->number($results['dc_input_current_a'] ?? null),
                    'equipment_count' => 1,
                    'inverter_index' => $instance,
                    'assigned_module_count' => $assignedModules,
                    'assigned_string_count' => count($strings),
                    'used_mppt_count' => $usedMppt,
                    'available_mppt_count' => $availableMppt,
                    'strings' => $strings,
                    'dc_ac_ratio' => round($dcAcRatio, 3),
                ]
            );
            $nodes[] = $this->node(
                $ids['dc_iso'], 'dc_isolator', "QS-DC-{$suffix}", 'DC input isolator assembly',
                "Inverter {$suffix} independent MPPT disconnect channels",
                sprintf('%s A · %s VDC', $this->display($dcIsolatorA, 0), $this->display($dcVoltage, 0)),
                'protection', 'dc_isolator', $this->position(220, $rowY), false,
                ['rated_voltage' => $dcVoltage, 'rated_current' => $dcIsolatorA, 'inverter_index' => $instance, 'channel_count' => count($strings), 'equipment_count' => count($strings), 'ports' => $this->dcIsolatorAssemblyPorts()]
            );
            $nodes[] = $this->node(
                $ids['dc_spd'], 'dc_spd', "SPD-DC-{$suffix}", 'DC SPD',
                'Parallel PV surge-protection branch',
                (string) ($protection['dc_spd'] ?? 'DC SPD rating pending'),
                'protection', 'dc_spd', $this->position(580, $rowY + 135), false,
                ['rated_voltage' => $dcVoltage, 'inverter_index' => $instance, 'connection_mode' => 'parallel_shunt', 'channel_count' => count($strings)]
            );
            $nodes[] = $this->node(
                $ids['inv'], 'inverter', "INV-{$suffix}", 'String inverter',
                sprintf('%d strings · %d/%d MPPT used', count($strings), $usedMppt, $availableMppt),
                sprintf('%s kW AC · %s kWp DC · ratio %s', $this->display($unitAcKw, 2), $this->display($assignedDcKw, 2), $this->display($dcAcRatio, 3)),
                'conversion', 'inverter', $this->position(760, $rowY), true,
                [
                    'rated_power' => $unitAcKw,
                    'rated_voltage' => $acVoltage,
                    'rated_current' => $unitAcCurrent,
                    'aggregate_power' => $aggregateAcKw,
                    'equipment_count' => 1,
                    'inverter_index' => $instance,
                    'mppt_count' => $availableMppt,
                    'used_mppt_count' => $usedMppt,
                    'string_assignments' => $strings,
                    'assigned_module_count' => $assignedModules,
                    'assigned_dc_kw' => $assignedDcKw,
                    'dc_ac_ratio' => round($dcAcRatio, 3),
                    'ports' => $this->inverterPorts($systemType),
                ]
            );
            $nodes[] = $this->node(
                $ids['ac_breaker'], 'ac_breaker', "QF-AC-{$suffix}", 'AC circuit breaker',
                "Inverter {$suffix} feeder protection",
                sprintf('%s A · %s VAC', $this->display($acBreakerA, 0), $this->display($acVoltage, 0)),
                'protection', 'ac_breaker', $this->position(940, $rowY), false,
                ['rated_voltage' => $acVoltage, 'rated_current' => $acBreakerA, 'inverter_index' => $instance]
            );
            $nodes[] = $this->node(
                $ids['ac_iso'], 'ac_isolator', "QS-AC-{$suffix}", 'AC isolator',
                "Inverter {$suffix} load-break disconnect",
                sprintf('%s A · %s VAC', $this->display($acIsolatorA, 0), $this->display($acVoltage, 0)),
                'protection', 'ac_isolator', $this->position(1120, $rowY), false,
                ['rated_voltage' => $acVoltage, 'rated_current' => $acIsolatorA, 'inverter_index' => $instance]
            );

            $connections[] = $this->connection("C-DC-{$suffix}-01", $ids['pv'], $ids['dc_iso'], 'Independent PV string circuits', $stringLabel.' · external string fuse omitted provisionally for one string per MPPT', 'DC_OUT', 'DC_IN', 'dc_power');
            $connections[] = $this->connection("C-DC-{$suffix}-02", $ids['dc_iso'], $ids['inv'], 'Independent MPPT input circuits', sprintf('Three independent MPPT channels · Voc %s V · Vmp %s V', $this->display($results['string_voc_cold_v'] ?? null, 1), $this->display($results['string_vmp_hot_v'] ?? null, 1)), 'DC_OUT', 'DC_IN', 'dc_power');
            $connections[] = $this->connection("C-DC-SPD-{$suffix}", $ids['dc_iso'], $ids['dc_spd'], 'DC SPD branch', 'Parallel shunt from the inverter-side DC terminals to PE; polarity and backup protection TBC', 'DC_OUT', 'DC_TAP', 'dc_power', false);
            $connections[] = $this->connection("C-AC-{$suffix}-01", $ids['inv'], $ids['ac_breaker'], 'Inverter AC feeder', sprintf('%s kW · %s VAC', $this->display($unitAcKw, 2), $this->display($acVoltage, 0)), 'AC_OUT', 'AC_IN', 'ac_power');
            $connections[] = $this->connection("C-AC-{$suffix}-02", $ids['ac_breaker'], $ids['ac_iso'], 'Protected AC feeder', 'Circuit breaker to AC isolator', 'AC_OUT', 'AC_IN', 'ac_power');
            $connections[] = $this->connection("C-AC-{$suffix}-03", $ids['ac_iso'], 'ac_combiner_01', 'Inverter incoming', "INV-{$suffix} to AC combiner", 'AC_OUT', 'AC_IN', 'ac_power');
            $connections[] = $this->connection("C-COM-INV-{$suffix}", $ids['inv'], 'data_logger_01', 'Inverter communication', 'RS485 field communication', 'COM', 'COM_1', 'communication', false);
            $connections[] = $this->connection("C-PE-PVA-{$suffix}", $ids['pv'], 'pe_bus_01', 'PV frame PE', 'Protective-earth conductor', 'PE', 'PE_IN', 'protective_earth', false);
            $connections[] = $this->connection("C-PE-SPD-DC-{$suffix}", $ids['dc_spd'], 'pe_bus_01', 'DC SPD PE', 'Shortest practical SPD earth path', 'PE', 'PE_IN', 'protective_earth', false);
            $connections[] = $this->connection("C-PE-INV-{$suffix}", $ids['inv'], 'pe_bus_01', 'Inverter PE', 'Protective-earth conductor', 'PE', 'PE_IN', 'protective_earth', false);

            if ($systemType === 'hybrid') {
                $connections[] = $this->connection("C-BAT-{$suffix}", 'battery_01', $ids['inv'], 'Battery DC bus', 'Battery system to hybrid inverter', 'BAT_DC', 'BAT_DC', 'battery_dc');
                $connections[] = $this->connection("C-AC-BK-{$suffix}", $ids['inv'], 'backup_panel_01', 'Backup AC feeder', 'Hybrid inverter backup output', 'AC_BACKUP', 'AC_IN', 'ac_power');
            }
        }

        $centerY = $firstRowY + (($inverterCount - 1) * $rowGap / 2);
        $sharedBottomY = max($firstRowY + (($inverterCount - 1) * $rowGap) + 300, $centerY + 440);

        $nodes[] = $this->node(
            'ac_combiner_01', 'ac_combiner', 'ACCB-01', 'AC combiner panel',
            sprintf('%d inverter incomer%s', $inverterCount, $inverterCount === 1 ? '' : 's'),
            sprintf('%s kW connected · %s A bus provisional', $this->display($aggregateAcKw, 2), $this->display($acCombinerBusA, 0)),
            'distribution', 'ac_combiner', $this->position(1280, $centerY), false,
            [
                'rated_power' => $aggregateAcKw,
                'rated_voltage' => $acVoltage,
                'rated_current' => $unitAcCurrent * $inverterCount,
                'busbar_rating_a' => $acCombinerBusA,
                'equipment_count' => 1,
                'design_status' => 'preliminary',
            ]
        );
        $nodes[] = $this->node(
            'ac_spd_backup_01', 'ac_breaker', 'QF-SPD-01', 'AC SPD backup protection',
            'Dedicated SPD branch protection',
            'Rating / poles / coordination TBC',
            'protection', 'ac_breaker', $this->position(1280, $centerY + 180), false,
            [
                'rated_voltage' => $acVoltage,
                'rated_current' => 0.0,
                'design_status' => 'tbc',
                'ports' => $this->symbolRegistry->ports('ac_breaker'),
            ]
        );
        $nodes[] = $this->node(
            'ac_spd_01', 'ac_spd', 'SPD-AC-01', 'AC SPD',
            'Parallel AC bus surge-protection branch',
            (string) ($protection['ac_spd'] ?? 'AC SPD rating pending'),
            'protection', 'ac_spd', $this->position(1460, $centerY + 180), false,
            ['rated_voltage' => $acVoltage, 'connection_mode' => 'parallel_shunt']
        );

        $pccBreakerPorts = $this->symbolRegistry->ports('ac_breaker');
        $pccBreakerPorts[] = [
            'id' => 'TRIP', 'label' => 'Hardwired trip input', 'network' => 'control',
            'direction' => 'input', 'side' => 'bottom', 'terminal_class' => 'trip_input',
            'anchor' => ['x' => 0.62, 'y' => 1.0], 'max_connections' => 1,
        ];
        $nodes[] = $this->node(
            'pcc_breaker_01', 'ac_breaker', 'QF-PCC-01', 'PCC main circuit breaker',
            'Plant-main protection with hardwired export-trip input',
            sprintf('%s A · %s VAC · duty TBC', $this->display($mainAcBreakerA, 0), $this->display($acVoltage, 0)),
            'protection', 'ac_breaker', $this->position(1460, $centerY), false,
            [
                'rated_voltage' => $acVoltage,
                'rated_current' => $mainAcBreakerA,
                'equipment_count' => 1,
                'ports' => $pccBreakerPorts,
                'fault_level_status' => 'tbc',
            ]
        );

        $ctPorts = $this->symbolRegistry->ports('ct');
        if ($plantControlRequired) {
            $ctPorts[] = [
                'id' => 'S1P', 'label' => 'Protection core S1', 'network' => 'measurement',
                'direction' => 'output', 'side' => 'bottom', 'terminal_class' => 'ct_secondary',
                'anchor' => ['x' => 0.58, 'y' => 1.0], 'max_connections' => 1,
            ];
            $ctPorts[] = [
                'id' => 'S2P', 'label' => 'Protection core S2', 'network' => 'measurement',
                'direction' => 'output', 'side' => 'bottom', 'terminal_class' => 'ct_secondary',
                'anchor' => ['x' => 0.74, 'y' => 1.0], 'max_connections' => 1,
            ];
            $nodes[] = $this->node(
                'ct_01', 'ct', 'CT-01', 'PCC current-transformer set',
                'Three-phase CT set with metering and independent protection cores',
                'Ratio / class / burden / polarity TBC',
                'measurement', 'ct', $this->position(1640, $centerY), false,
                [
                    'rated_voltage' => $acVoltage,
                    'measurement_role' => 'pcc_export_import',
                    'ratio_status' => 'tbc',
                    'phase_quantity' => 3,
                    'secondary_core_count' => 2,
                    'equipment_count' => 3,
                    'ports' => $ctPorts,
                ]
            );
        }

        $pccDisconnectPorts = $this->symbolRegistry->ports('ac_isolator');
        $pccDisconnectPorts[] = [
            'id' => 'VREF_L', 'label' => 'Fused voltage reference phase', 'network' => 'measurement',
            'direction' => 'output', 'side' => 'bottom', 'terminal_class' => 'voltage_reference',
            'anchor' => ['x' => 0.40, 'y' => 1.0], 'max_connections' => 2,
        ];
        $pccDisconnectPorts[] = [
            'id' => 'VREF_N', 'label' => 'Fused voltage reference neutral', 'network' => 'measurement',
            'direction' => 'output', 'side' => 'bottom', 'terminal_class' => 'voltage_reference',
            'anchor' => ['x' => 0.64, 'y' => 1.0], 'max_connections' => 2,
        ];
        $nodes[] = $this->node(
            'pcc_disconnect_01', 'ac_isolator', 'QS-PCC-01', 'PCC main switch-disconnector',
            'Lockable, accessible utility isolation point',
            sprintf('%s A · %s VAC · poles / category TBC', $this->display($pccIsolatorA, 0), $this->display($acVoltage, 0)),
            'protection', 'ac_isolator', $this->position(1820, $centerY), false,
            [
                'rated_voltage' => $acVoltage,
                'rated_current' => $pccIsolatorA,
                'equipment_count' => 1,
                'ports' => $pccDisconnectPorts,
                'lockable' => true,
                'utility_accessible' => true,
            ]
        );

        $meterPorts = $this->symbolRegistry->ports('meter', true);
        if ($plantControlRequired) {
            $meterPorts = [
                [
                    'id' => 'AC_IN', 'label' => 'Voltage reference phase input', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'voltage_reference',
                    'anchor' => ['x' => 0.0, 'y' => 0.28], 'max_connections' => 1,
                ],
                [
                    'id' => 'AC_OUT', 'label' => 'Voltage reference neutral input', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'voltage_reference',
                    'anchor' => ['x' => 0.0, 'y' => 0.42], 'max_connections' => 1,
                ],
                [
                    'id' => 'MEAS_IN', 'label' => 'Metering core S1 input', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'measurement_input',
                    'anchor' => ['x' => 0.0, 'y' => 0.58], 'max_connections' => 1,
                ],
                [
                    'id' => 'MEAS_RETURN', 'label' => 'Metering core S2 input', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'measurement_input',
                    'anchor' => ['x' => 0.0, 'y' => 0.72], 'max_connections' => 1,
                ],
                [
                    'id' => 'COM', 'label' => 'RS485 / Modbus', 'network' => 'communication',
                    'direction' => 'bidirectional', 'side' => 'bottom', 'terminal_class' => 'communication',
                    'anchor' => ['x' => 0.5, 'y' => 1.0], 'max_connections' => 2,
                ],
            ];
        }
        $nodes[] = $this->node(
            'meter_01', 'meter', 'MTR-01', 'Power / energy meter',
            $plantControlRequired ? 'CT-operated import / export measurement; not in main-current path' : 'Bidirectional import / export metering',
            $project->systemRecommendation?->export_policy_label ?? 'Bidirectional metering',
            'control', 'meter', $this->position(1640, $centerY + ($plantControlRequired ? 200 : 0)), true,
            [
                'rated_voltage' => $acVoltage,
                'equipment_count' => 1,
                'ports' => $meterPorts,
                'metering_mode' => $plantControlRequired ? 'ct_operated' : 'direct_or_project_metering',
                'main_current_path' => $plantControlRequired ? false : true,
            ]
        );

        if ($plantControlRequired) {
            $relayPorts = [
                [
                    'id' => 'MEAS', 'label' => 'Protection CT input', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'measurement_input',
                    'anchor' => ['x' => 0.0, 'y' => 0.30], 'max_connections' => 1,
                ],
                [
                    'id' => 'MEAS_RETURN', 'label' => 'Protection CT return', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'measurement_input',
                    'anchor' => ['x' => 0.0, 'y' => 0.48], 'max_connections' => 1,
                ],
                [
                    'id' => 'VREF_L', 'label' => 'Voltage reference phase', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'voltage_reference',
                    'anchor' => ['x' => 0.0, 'y' => 0.66], 'max_connections' => 1,
                ],
                [
                    'id' => 'VREF_N', 'label' => 'Voltage reference neutral', 'network' => 'measurement',
                    'direction' => 'input', 'side' => 'left', 'terminal_class' => 'voltage_reference',
                    'anchor' => ['x' => 0.0, 'y' => 0.82], 'max_connections' => 1,
                ],
                [
                    'id' => 'TRIP', 'label' => 'Hardwired trip output', 'network' => 'control',
                    'direction' => 'output', 'side' => 'right', 'terminal_class' => 'trip_output',
                    'anchor' => ['x' => 1.0, 'y' => 0.45], 'max_connections' => 1,
                ],
                [
                    'id' => 'COM', 'label' => 'Status communication', 'network' => 'communication',
                    'direction' => 'bidirectional', 'side' => 'bottom', 'terminal_class' => 'communication',
                    'anchor' => ['x' => 0.5, 'y' => 1.0], 'max_connections' => 1,
                ],
            ];
            $nodes[] = $this->node(
                'export_relay_01', 'relay', 'RPR-01', 'Independent reverse-power relay',
                'Hardwired export failsafe independent of RS485 / Modbus control',
                'Threshold / delay / CT- and VT-input design TBC',
                'protection', 'relay', $this->position(1820, $centerY + 200), false,
                [
                    'ports' => $relayPorts,
                    'control_policy' => $exportPolicy,
                    'failsafe_role' => 'hardwired_trip',
                    'equipment_count' => 1,
                ]
            );
        }

        $nodes[] = $this->node(
            'grid_01', 'grid', 'GRID-01', 'Utility grid',
            ucfirst(str_replace('_', '-', (string) data_get($results, 'assumptions.supply_phase', 'utility'))).' supply',
            'Point of common coupling',
            'utility', 'grid', $this->position(2000, $centerY), true
        );
        $nodes[] = $this->node(
            'pe_bus_01', 'pe_bus', 'PE-BUS-01', 'PE busbar',
            'Protective-earth collection bus',
            'Common project PE reference',
            'safety', 'pe_bus', $this->position(420, $sharedBottomY), false,
            [
                'ac_earthing_system' => (string) data_get($results, 'assumptions.ac_earthing_system', 'TBC'),
            ]
        );
        $nodes[] = $this->node(
            'ground_rod_01', 'ground_rod', 'GND-01', 'Grounding electrode',
            'Project earth termination',
            'Final resistance by site test',
            'safety', 'ground_rod', $this->position(610, $sharedBottomY), false
        );
        $nodes[] = $this->node(
            'data_logger_01', 'data_logger', 'DL-01', 'Data logger',
            sprintf('%d inverter%s · monitoring network', $inverterCount, $inverterCount === 1 ? '' : 's'),
            'Plant monitoring gateway',
            'control', 'data_logger', $this->position(820, $sharedBottomY), false,
            ['device_capacity' => $inverterCount + 2, 'protocol' => 'RS485 / Ethernet']
        );

        if ($plantControlRequired) {
            $nodes[] = $this->node(
                'plant_controller_01', 'plant_controller', 'PPC-01', 'Plant controller',
                $exportPolicy === 'zero_export' ? 'Zero-export control' : 'Export-limitation control',
                'Closed-loop active-power control with direct inverter commands',
                'control', 'plant_controller', $this->position(1010, $sharedBottomY), false,
                ['control_policy' => $exportPolicy]
            );
        }

        if ($systemType === 'hybrid') {
            $batteryKwh = $this->number($results['battery_nominal_kwh'] ?? null);
            $batteryPowerKw = $this->number($results['battery_minimum_power_kw'] ?? null);
            $nodes[] = $this->node(
                'battery_01', 'battery', 'BAT-01', 'Battery system',
                sprintf('%s kWh nominal', $this->display($batteryKwh, 2)),
                sprintf('%s kW minimum discharge', $this->display($batteryPowerKw, 2)),
                'storage', 'battery', $this->position(40, $sharedBottomY), true,
                ['rated_power' => $batteryPowerKw, 'energy_capacity' => $batteryKwh]
            );
            $nodes[] = $this->node(
                'backup_panel_01', 'backup_panel', 'DB-BK-01', 'Backup loads panel',
                'Critical loads only',
                'Final circuit allocation requires site design',
                'load', 'backup_panel', $this->position(1200, $sharedBottomY), true
            );
            $connections[] = $this->connection('C-PE-BAT-01', 'battery_01', 'pe_bus_01', 'Battery PE', 'Protective-earth conductor', 'PE', 'PE_IN', 'protective_earth', false);
            $connections[] = $this->connection('C-COM-BAT-01', 'battery_01', 'data_logger_01', 'Battery/BMS communication', 'BMS communication link', 'COM', 'COM_2', 'communication', false);
            $connections[] = $this->connection('C-PE-DB-BK-01', 'backup_panel_01', 'pe_bus_01', 'Backup panel PE', 'Protective-earth conductor', 'PE', 'PE_IN', 'protective_earth', false);
        }

        if ($plantControlRequired) {
            $connections[] = $this->connection('C-AC-MAIN-01', 'ac_combiner_01', 'pcc_breaker_01', 'Plant main AC feeder', sprintf('%s kW connected load', $this->display($aggregateAcKw, 2)), 'AC_OUT', 'AC_IN', 'ac_power');
            $connections[] = $this->connection('C-AC-CT-01', 'pcc_breaker_01', 'ct_01', 'PCC feeder through CT primary', 'Main conductor passes through the CT aperture; meter is not in the primary path', 'AC_OUT', 'P1', 'ac_power');
            $connections[] = $this->connection('C-AC-DISC-01', 'ct_01', 'pcc_disconnect_01', 'PCC main disconnect feeder', 'CT primary P2 to lockable utility-accessible switch-disconnector', 'P2', 'AC_IN', 'ac_power');
            $connections[] = $this->connection('C-AC-PCC-01', 'pcc_disconnect_01', 'grid_01', 'Point of connection', 'Lockable PCC disconnect to utility interface', 'AC_OUT', 'AC_PCC', 'ac_power');

            $connections[] = $this->connection('C-MEAS-CT-MTR-S1', 'ct_01', 'meter_01', 'Metering CT core S1', 'Three-phase CT metering secondary via shorting/test terminal block; design TBC', 'S1', 'MEAS_IN', 'measurement', false);
            $connections[] = $this->connection('C-MEAS-CT-MTR-S2', 'ct_01', 'meter_01', 'Metering CT core S2', 'Three-phase CT metering secondary return; polarity and earthing design TBC', 'S2', 'MEAS_RETURN', 'measurement', false);
            $connections[] = $this->connection('C-MEAS-CT-RPR-S1', 'ct_01', 'export_relay_01', 'Protection CT core S1', 'Independent protection core for hardwired reverse-power relay', 'S1P', 'MEAS', 'measurement', false);
            $connections[] = $this->connection('C-MEAS-CT-RPR-S2', 'ct_01', 'export_relay_01', 'Protection CT core S2', 'Independent protection-core return', 'S2P', 'MEAS_RETURN', 'measurement', false);
            $connections[] = $this->connection('C-MEAS-MTR-VL-01', 'pcc_disconnect_01', 'meter_01', 'Meter voltage reference phase', 'Fused voltage-reference circuit; phase arrangement and fuse rating TBC', 'VREF_L', 'AC_IN', 'measurement', false);
            $connections[] = $this->connection('C-MEAS-MTR-VN-01', 'pcc_disconnect_01', 'meter_01', 'Meter voltage reference neutral', 'Fused voltage-reference neutral; earthing system and neutral arrangement TBC', 'VREF_N', 'AC_OUT', 'measurement', false);
            $connections[] = $this->connection('C-MEAS-RPR-VL-01', 'pcc_disconnect_01', 'export_relay_01', 'Relay voltage reference phase', 'Hardwired reverse-power relay voltage reference; fused circuit TBC', 'VREF_L', 'VREF_L', 'measurement', false);
            $connections[] = $this->connection('C-MEAS-RPR-VN-01', 'pcc_disconnect_01', 'export_relay_01', 'Relay voltage reference neutral', 'Hardwired reverse-power relay neutral reference; earthing system TBC', 'VREF_N', 'VREF_N', 'measurement', false);
            $connections[] = $this->connection('C-CTRL-RPR-TRIP-01', 'export_relay_01', 'pcc_breaker_01', 'Independent export-trip circuit', 'Hardwired normally-energized or project-approved fail-safe trip philosophy TBC', 'TRIP', 'TRIP', 'control', false);
            $connections[] = $this->connection('C-COM-RPR-01', 'export_relay_01', 'plant_controller_01', 'Protection relay status', 'Optional status-only communication; hardwired trip remains independent', 'COM', 'COM', 'communication', false);
        } else {
            $connections[] = $this->connection('C-AC-MAIN-01', 'ac_combiner_01', 'pcc_breaker_01', 'Plant main AC feeder', sprintf('%s kW connected load', $this->display($aggregateAcKw, 2)), 'AC_OUT', 'AC_IN', 'ac_power');
            $connections[] = $this->connection('C-AC-DISC-01', 'pcc_breaker_01', 'pcc_disconnect_01', 'PCC main disconnect feeder', 'Main breaker to lockable utility-accessible disconnect', 'AC_OUT', 'AC_IN', 'ac_power');
            $connections[] = $this->connection('C-AC-MTR-01', 'pcc_disconnect_01', 'meter_01', 'Direct meter feeder', 'Project-specific direct or utility metering path', 'AC_OUT', 'AC_IN', 'ac_power');
            $connections[] = $this->connection('C-AC-PCC-01', 'meter_01', 'grid_01', 'Point of connection', 'Meter output to utility interface', 'AC_OUT', 'AC_PCC', 'ac_power');
        }

        $connections[] = $this->connection('C-AC-SPD-BACKUP-01', 'ac_combiner_01', 'ac_spd_backup_01', 'AC SPD protected tap', 'Parallel bus tap through dedicated backup protection', 'AC_OUT', 'AC_IN', 'ac_power', false);
        $connections[] = $this->connection('C-AC-SPD-01', 'ac_spd_backup_01', 'ac_spd_01', 'AC SPD branch', 'Short parallel shunt from dedicated backup device to SPD', 'AC_OUT', 'AC_TAP', 'ac_power', false);
        $connections[] = $this->connection(
            'C-COM-MTR-01',
            'meter_01',
            $plantControlRequired ? 'plant_controller_01' : 'data_logger_01',
            'Meter communication',
            'RS485 / Modbus import-export measurement',
            'COM',
            $plantControlRequired ? 'COM' : 'COM_2',
            'communication',
            false
        );
        $connections[] = $this->connection('C-PE-ACCB-01', 'ac_combiner_01', 'pe_bus_01', 'AC combiner PE', 'Protective-earth conductor; cable core arrangement TBC', 'PE', 'PE_IN', 'protective_earth', false);
        $connections[] = $this->connection('C-PE-SPD-AC-01', 'ac_spd_01', 'pe_bus_01', 'AC SPD PE', 'Shortest practical SPD earth path', 'PE', 'PE_IN', 'protective_earth', false);
        $connections[] = $this->connection('C-PE-DL-01', 'data_logger_01', 'pe_bus_01', 'Data logger PE', 'Protective-earth conductor', 'PE', 'PE_IN', 'protective_earth', false);
        $connections[] = $this->connection('C-PE-GND-01', 'pe_bus_01', 'ground_rod_01', 'Main earthing conductor', 'PE bus to grounding electrode', 'PE_OUT', 'PE', 'protective_earth', false);
        if ($plantControlRequired) {
            $connections[] = $this->connection('C-COM-PPC-01', 'data_logger_01', 'plant_controller_01', 'Plant monitoring link', 'Ethernet Cat6 or project-approved network; final protocol TBC', 'ETH', 'ETH', 'communication', false);
            foreach ($assignments as $index => $assignment) {
                $suffix = sprintf('%02d', $index + 1);
                $connections[] = $this->connection("C-CTRL-PPC-INV-{$suffix}", 'plant_controller_01', "inverter_{$suffix}", 'Active-power limit command', "Direct fail-soft active-power command to INV-{$suffix}", 'CTRL', 'CTRL', 'control', false);
            }
            $connections[] = $this->connection('C-PE-PPC-01', 'plant_controller_01', 'pe_bus_01', 'Plant controller PE', 'Protective-earth conductor', 'PE', 'PE_IN', 'protective_earth', false);
        }


        return [
            'nodes' => $nodes,
            'connections' => $connections,
            'positions' => $positions = array_column($nodes, 'default_position', 'id'),
            'meta' => [
                'topology_profile' => self::PROFILE,
                'topology_version' => self::VERSION,
                'physical_inverter_count' => $inverterCount,
                'physical_equipment_count' => count($nodes),
                'physical_connection_count' => count($connections),
                'string_groups' => array_map(static fn (array $assignment): array => [
                    'inverter_index' => (int) ($assignment['inverter_index'] ?? 0),
                    'inverter_tag' => (string) ($assignment['tag'] ?? ''),
                    'assigned_module_count' => (int) ($assignment['assigned_module_count'] ?? 0),
                    'assigned_dc_kw' => (float) ($assignment['assigned_dc_kw'] ?? 0),
                    'used_mppt_count' => (int) ($assignment['used_mppt_count'] ?? 0),
                    'available_mppt_count' => (int) ($assignment['available_mppt_count'] ?? 0),
                    'dc_ac_ratio' => (float) ($assignment['dc_ac_ratio'] ?? 0),
                    'strings' => is_array($assignment['strings'] ?? null) ? $assignment['strings'] : [],
                ], $assignments),
                'monitoring_enabled' => true,
                'plant_control_enabled' => $plantControlRequired,
                'closed_loop_control_generated' => $plantControlRequired,
                'grounding_generated' => true,
                'protection_mode' => 'individual_components',
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function cableSchedule(Project $project): array
    {
        $project->loadMissing(['technicalCalculation', 'systemRecommendation']);
        $calculation = $project->technicalCalculation;
        $results = is_array($calculation?->results) ? $calculation->results : [];
        $systemType = $this->systemType($project, $calculation);
        $topology = $this->build($project, $calculation, $results, $systemType);
        $dcArea = $this->display($calculation?->dc_cable_area_mm2, 2);
        $acArea = $this->display($calculation?->ac_cable_area_mm2, 2);
        $dcLength = $this->display($calculation?->dc_cable_length_m, 1);
        $acLength = $this->display($calculation?->ac_cable_length_m, 1);
        $material = strtoupper((string) ($calculation?->cable_material ?: 'CU'));
        $rows = [];
        $groups = collect((array) data_get($topology, 'meta.string_groups', []))->keyBy(
            static fn (array $group): string => sprintf('%02d', (int) ($group['inverter_index'] ?? 0))
        );
        $handled = [];

        foreach ($topology['connections'] as $connection) {
            $network = (string) ($connection['network'] ?? '');
            $id = (string) ($connection['id'] ?? '');
            $label = (string) ($connection['label'] ?? $id);
            $from = (string) ($connection['from'] ?? '');
            $to = (string) ($connection['to'] ?? '');

            if (preg_match('/^C-DC-(\d{2})-(01|02)$/', $id, $match) === 1) {
                $suffix = $match[1];
                $stage = $match[2];
                $group = (array) ($groups->get($suffix) ?? []);
                $strings = array_values(array_filter((array) ($group['strings'] ?? []), 'is_array'));
                foreach ($strings as $index => $string) {
                    $stringTag = (string) ($string['tag'] ?? ('STR-'.sprintf('%02d', $index + 1)));
                    $mppt = (string) ($string['mppt_label'] ?? ('MPPT-'.sprintf('%02d', $index + 1)));
                    $isoTag = 'QS-DC-'.$suffix.'/'.($index + 1);
                    $invTag = 'INV-'.$suffix.':'.$mppt;
                    [$rowFrom, $rowTo, $segment] = $stage === '01'
                        ? [$stringTag, $isoTag, 'A']
                        : [$isoTag, $invTag, 'B'];
                    $knownRoute = $stage === '01' && is_numeric($dcLength) && (float) $dcLength > 0;
                    $row = $this->cableRow(
                        'C-DC-'.$suffix.'-'.$stringTag.'-'.$segment,
                        'Independent '.$stringTag.' circuit',
                        $rowFrom,
                        $rowTo,
                        'dc_power',
                        'PV1-F',
                        $dcArea,
                        2,
                        '1.5 kV DC',
                        $stage === '01'
                            ? 'Dedicated string route from array to inverter-side protection assembly'
                            : 'Local independent string interconnection; final internal route TBC',
                        $knownRoute ? $dcLength : '',
                        'DC-CABLE-PV1F',
                        ['inputs.dc_cable_area_mm2', 'inputs.dc_cable_length_m'],
                        $stage === '01' ? 'DC_OUT' : 'DC_OUT',
                        $stage === '02' ? $mppt : 'DC_IN',
                        1,
                        $stage === '01' ? 'array_to_inverter_route' : 'local_equipment_interconnect',
                        $knownRoute ? 'calculation_route_input' : 'tbc'
                    );
                    $row['string_tag'] = $stringTag;
                    $row['mppt'] = $mppt;
                    $row['size_display'] = '2 x 1C x '.$this->display($calculation?->dc_cable_area_mm2, 2).' mm²';
                    // The graph keeps one physical PV group and one multi-channel
                    // isolator/inverter envelope. Each paper-space string row is
                    // therefore reconciled to the aggregate graph connection that
                    // owns the same calculation-controlled conductor metadata.
                    $row['source_connection_key'] = $stage === '01'
                        ? 'pv_group_'.$suffix.':DC_OUT>dc_isolator_'.$suffix.':DC_IN|dc_power'
                        : 'dc_isolator_'.$suffix.':DC_OUT>inverter_'.$suffix.':DC_IN|dc_power';
                    $rows[] = $row;
                }
                continue;
            }

            if (preg_match('/^C-DC-SPD-(\d{2})$/', $id, $match) === 1) {
                $suffix = $match[1];
                $group = (array) ($groups->get($suffix) ?? []);
                $strings = array_values(array_filter((array) ($group['strings'] ?? []), 'is_array'));
                foreach ($strings as $index => $string) {
                    $mppt = (string) ($string['mppt_label'] ?? ('MPPT-'.sprintf('%02d', $index + 1)));
                    $row = $this->cableRow(
                        'C-DC-SPD-'.$suffix.'-CH'.($index + 1),
                        'DC SPD channel '.($index + 1),
                        'INV-'.$suffix.':'.$mppt,
                        'SPD-DC-'.$suffix.':CH'.($index + 1),
                        'dc_power',
                        'PV SPD lead set (+/-)',
                        '',
                        2,
                        '1.5 kV DC',
                        'Shortest practical parallel shunt; polarity, size, and backup-protection requirement TBC',
                        '',
                        'DC-SPD',
                        ['results.protection.dc_spd'],
                        'DC_TAP',
                        'DC_TAP',
                        1,
                        'local_spd_branch_tbc',
                        'tbc'
                    );
                    $row['size_display'] = '2 x 1C x TBC';
                    $row['source_connection_key'] = 'dc_isolator_'.$suffix.':DC_OUT>dc_spd_'.$suffix.':DC_TAP|dc_power';
                    $rows[] = $row;
                }
                continue;
            }

            // Aggregate the three-phase CT secondary conductors into one traceable
            // cable row per secondary core instead of pretending S1 and S2 are two
            // unrelated single circuits.
            if (in_array($id, ['C-MEAS-CT-MTR-S1', 'C-MEAS-CT-MTR-S2'], true)) {
                if (isset($handled['metering_ct_core'])) continue;
                $handled['metering_ct_core'] = true;
                $row = $this->cableRow(
                    'C-MEAS-CT-MTR-01', 'Three-phase metering CT secondary core',
                    'CT-01:METERING CORE', 'MTR-01', 'measurement', 'CT cable via test block', '', 6,
                    'ELV', '3 CTs · S1/S2 polarity · test/shorting terminal · burden and earthing TBC', '', '',
                    ['recommendation.export_policy', 'metering.ct_design'], 'S1/S2', 'MEAS_IN/RETURN', 1, 'metering_design_tbc', 'tbc'
                );
                $row['size_display'] = '6C x TBC';
                $rows[] = $row;
                continue;
            }
            if (in_array($id, ['C-MEAS-CT-RPR-S1', 'C-MEAS-CT-RPR-S2'], true)) {
                if (isset($handled['protection_ct_core'])) continue;
                $handled['protection_ct_core'] = true;
                $row = $this->cableRow(
                    'C-MEAS-CT-RPR-01', 'Three-phase protection CT secondary core',
                    'CT-01:PROTECTION CORE', 'RPR-01', 'measurement', 'Protection CT secondary cable', '', 6,
                    'ELV', 'Independent protection core · S1/S2 polarity · burden and terminal design TBC', '', '',
                    ['recommendation.export_policy', 'protection.reverse_power_relay'], 'S1P/S2P', 'MEAS/RETURN', 1, 'protection_metering_tbc', 'tbc'
                );
                $row['size_display'] = '6C x TBC';
                $rows[] = $row;
                continue;
            }
            if (in_array($id, ['C-MEAS-MTR-VL-01', 'C-MEAS-MTR-VN-01'], true)) {
                if (isset($handled['meter_voltage_ref'])) continue;
                $handled['meter_voltage_ref'] = true;
                $row = $this->cableRow(
                    'C-MEAS-MTR-VREF-01', 'Meter fused voltage-reference circuit',
                    'QS-PCC-01:PCC SIDE', 'MTR-01', 'measurement', 'Voltage-reference wiring', '', 4,
                    '400 VAC', 'L1/L2/L3/N or project-specific reference arrangement; fuse and earthing-system basis TBC', '', '',
                    ['recommendation.export_policy', 'project.earthing_design'], 'VREF_L/N', 'VREF_IN', 1, 'meter_voltage_reference_tbc', 'tbc'
                );
                $row['size_display'] = '4C x TBC';
                $rows[] = $row;
                continue;
            }
            if (in_array($id, ['C-MEAS-RPR-VL-01', 'C-MEAS-RPR-VN-01'], true)) {
                if (isset($handled['relay_voltage_ref'])) continue;
                $handled['relay_voltage_ref'] = true;
                $row = $this->cableRow(
                    'C-MEAS-RPR-VREF-01', 'Reverse-power relay fused voltage reference',
                    'QS-PCC-01:PCC SIDE', 'RPR-01', 'measurement', 'Protection voltage-reference wiring', '', 4,
                    '400 VAC', 'Independent relay voltage input; fuse, poles, and earthing-system basis TBC', '', '',
                    ['recommendation.export_policy', 'protection.reverse_power_relay'], 'VREF_L/N', 'VREF_L/N', 1, 'protection_voltage_reference_tbc', 'tbc'
                );
                $row['size_display'] = '4C x TBC';
                $rows[] = $row;
                continue;
            }

            $sourceKeys = [];
            $bomCode = '';
            $type = '';
            $size = '';
            $cores = '';
            $voltageClass = '';
            $method = (string) ($connection['detail'] ?? '');
            $length = '';
            $routeBasis = 'final_route_tbc';
            $lengthStatus = 'tbc';
            $sizeDisplay = '';

            if ($network === 'ac_power') {
                $type = $material.' XLPE/PVC';
                $size = $acArea;
                $voltageClass = '0.6/1 kV AC';
                $bomCode = 'AC-CABLE-LV';
                $sourceKeys = ['inputs.ac_cable_area_mm2', 'inputs.ac_cable_length_m'];
                $knownFeeder = preg_match('/^C-AC-\d{2}-03$/', $id) === 1;
                if ($knownFeeder) {
                    $length = $acLength;
                    $lengthStatus = is_numeric($acLength) && (float) $acLength > 0 ? 'calculation_route_input' : 'tbc';
                    $routeBasis = 'inverter_to_ac_combiner_route';
                }
                if (str_contains($id, 'SPD')) {
                    $method = 'Dedicated parallel SPD branch; shortest practical route and backup coordination TBC';
                    $sizeDisplay = '3P+N conductor set x TBC';
                } else {
                    $method = $knownFeeder
                        ? 'Inverter feeder route; neutral and PE arrangement require final inverter and earthing-system design'
                        : 'Plant-main or local AC interconnection; final route, neutral, and PE arrangement TBC';
                    $sizeDisplay = '3P x '.$this->display($calculation?->ac_cable_area_mm2, 2).' mm² + N/PE TBC';
                }
            } elseif ($network === 'protective_earth') {
                $type = 'Cu G/Y';
                $cores = 1;
                $voltageClass = 'PE';
                $method = 'Protective bonding conductor; size and route from final earthing design TBC';
                $sourceKeys = ['project.earthing_design'];
                $routeBasis = 'earthing_design_tbc';
                $sizeDisplay = '1C x TBC';
            } elseif ($network === 'communication') {
                if ($id === 'C-COM-PPC-01') {
                    $type = 'Ethernet Cat6';
                    $cores = 8;
                    $voltageClass = 'SELV';
                    $method = 'Four twisted pairs; segregation, shield, topology, and route TBC';
                    $sizeDisplay = '4 twisted pairs · Cat6';
                } else {
                    $type = 'RS485 shielded twisted pair';
                    $cores = 2;
                    $voltageClass = 'SELV';
                    $method = 'One shielded twisted pair; termination, bias, screen earthing, and route TBC';
                    $sizeDisplay = '1 shielded pair x TBC';
                }
                $sourceKeys = ['recommendation.export_policy'];
                $routeBasis = 'control_system_route_tbc';
            } elseif ($network === 'control') {
                $type = str_contains($id, 'RPR-TRIP') ? 'Hardwired trip cable' : 'RS485 / manufacturer control link';
                $cores = str_contains($id, 'RPR-TRIP') ? 2 : 2;
                $voltageClass = str_contains($id, 'RPR-TRIP') ? 'AUX SUPPLY TBC' : 'SELV';
                $method = str_contains($id, 'RPR-TRIP')
                    ? 'Preliminary hardwired export-trip path; auxiliary supply, supervision, trip-coil and fail-safe philosophy TBC'
                    : 'Direct active-power-limit command; actual inverter protocol and route TBC';
                $sourceKeys = ['recommendation.export_policy'];
                $routeBasis = 'control_system_route_tbc';
                $sizeDisplay = str_contains($id, 'RPR-TRIP') ? '2C x TBC' : '1 shielded pair x TBC';
            } elseif ($network === 'measurement') {
                $type = 'Measurement wiring';
                $cores = 2;
                $voltageClass = 'ELV';
                $method = 'Dedicated measurement circuit; final design TBC';
                $sourceKeys = ['recommendation.export_policy'];
                $routeBasis = 'metering_design_tbc';
                $sizeDisplay = '2C x TBC';
            } elseif ($network === 'battery_dc') {
                $type = 'Battery DC cable';
                $size = $dcArea;
                $cores = 2;
                $voltageClass = '1.5 kV DC';
                $bomCode = 'BATTERY-COMBINER-BUSBAR';
                $sourceKeys = ['results.battery_nominal_kwh'];
                $routeBasis = 'battery_route_tbc';
            }

            $row = $this->cableRow(
                $id, $label, $from, $to, $network, $type, $size, $cores,
                $voltageClass, $method, $length, $bomCode, $sourceKeys,
                (string) ($connection['from_port'] ?? ''), (string) ($connection['to_port'] ?? ''),
                1, $routeBasis, $lengthStatus
            );
            if ($sizeDisplay !== '') $row['size_display'] = $sizeDisplay;
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function protectionSchedule(Project $project): array
    {
        $project->loadMissing(['technicalCalculation', 'systemRecommendation']);
        $calculation = $project->technicalCalculation;
        $results = is_array($calculation?->results) ? $calculation->results : [];
        $protection = is_array($results['protection'] ?? null) ? $results['protection'] : [];
        $count = max(1, $this->integer($results['inverter_count'] ?? null));
        $phase = (string) data_get($results, 'assumptions.supply_phase', 'three_phase');
        $acVoltage = $this->number($results['ac_nominal_voltage_v'] ?? 0);
        $dcVoltage = $this->number($calculation?->inverter_max_dc_voltage_v);
        $acBreakerConfig = str_contains($phase, 'three') ? '3P' : '2P';
        $acIsolatorConfig = str_contains($phase, 'three') ? '3P / neutral arrangement TBC' : '2P';
        $plantControlRequired = in_array((string) ($project->systemRecommendation?->export_policy ?? ''), ['zero_export', 'limited'], true);
        $rows = [];

        for ($index = 1; $index <= $count; $index++) {
            $suffix = sprintf('%02d', $index);
            $rows[] = $this->protectionRow(
                "P-DC-ISO-{$suffix}", "dc_isolator_{$suffix}", 'DC isolator',
                $this->number($protection['dc_isolator_a'] ?? 0), 'A', 'PV DC load-break isolator per MPPT channel',
                ['DC-ISOLATOR'], ['results.protection.dc_isolator_a'],
                ['configuration' => '3 x 2P DC channels', 'system_voltage' => $dcVoltage, 'final_duty' => 'Poles / util. cat. / integration TBC']
            );
            $rows[] = $this->protectionRow(
                "P-DC-SPD-{$suffix}", "dc_spd_{$suffix}", 'DC SPD',
                $this->number($calculation?->inverter_max_dc_voltage_v), 'VDC', (string) ($protection['dc_spd'] ?? 'Type II DC SPD'),
                ['DC-SPD'], ['results.protection.dc_spd'],
                ['configuration' => 'Type 2 · parallel shunt', 'system_voltage' => $dcVoltage, 'final_duty' => 'Polarity / Up / In / Imax / backup TBC']
            );
            $rows[] = $this->protectionRow(
                "P-AC-CB-{$suffix}", "ac_breaker_{$suffix}", 'Inverter feeder breaker',
                $this->number($protection['inverter_feeder_breaker_a'] ?? $protection['ac_breaker_a'] ?? 0), 'A', 'Preliminary LV inverter feeder circuit breaker',
                ['AC-BREAKER'], ['results.protection.inverter_feeder_breaker_a', 'results.inverter_unit_rated_current_a'],
                [
                    'configuration' => $acBreakerConfig,
                    'system_voltage' => $acVoltage,
                    'final_duty' => 'Unit-current basis; Icu / Ics / trip / cable TBC',
                ]
            );
            $rows[] = $this->protectionRow(
                "P-AC-ISO-{$suffix}", "ac_isolator_{$suffix}", 'Inverter feeder isolator',
                $this->number($protection['inverter_feeder_isolator_a'] ?? $protection['ac_breaker_a'] ?? 0), 'A', 'AC load-break isolator',
                ['AC-ISOLATOR-METER'], ['results.protection.inverter_feeder_isolator_a'],
                ['configuration' => $acIsolatorConfig, 'system_voltage' => $acVoltage, 'final_duty' => 'Poles / neutral / util. cat. / lock TBC']
            );
        }

        $rows[] = $this->protectionRow(
            'P-AC-SPD-BACKUP-01', 'ac_spd_backup_01', 'AC SPD backup protection', 0, 'A',
            'Dedicated SPD backup fuse / MCB', ['AC-BREAKER', 'AC-SPD'], ['results.protection.ac_spd'],
            ['configuration' => '3P+N branch · device TBC', 'system_voltage' => $acVoltage, 'final_duty' => 'Device / poles / rating / fault duty TBC', 'rating_display' => 'RATING TBC']
        );
        $rows[] = $this->protectionRow(
            'P-AC-SPD-01', 'ac_spd_01', 'AC SPD', $acVoltage, 'VAC',
            (string) ($protection['ac_spd'] ?? 'Type II AC SPD'), ['AC-SPD'], ['results.protection.ac_spd'],
            ['configuration' => 'Type 2 / 3P+N', 'system_voltage' => $acVoltage, 'final_duty' => 'Uc / Up / In / Imax / backup / leads TBC']
        );
        $rows[] = $this->protectionRow(
            'P-PCC-CB-01', 'pcc_breaker_01', 'PCC main circuit breaker',
            $this->number($protection['main_ac_breaker_a'] ?? 0), 'A', 'Plant-main breaker with hardwired trip input',
            ['AC-BREAKER'], ['results.protection.main_ac_breaker_a', 'project.pcc_fault_level'],
            ['configuration' => '3P/4P and trip unit TBC', 'system_voltage' => $acVoltage, 'final_duty' => 'Ik / Icu / Ics / selectivity / trip TBC']
        );
        $rows[] = $this->protectionRow(
            'P-PCC-ISO-01', 'pcc_disconnect_01', 'PCC main switch-disconnector',
            $this->number($protection['pcc_isolator_a'] ?? 0), 'A', 'Lockable utility-accessible isolation device',
            ['AC-ISOLATOR-METER'], ['results.protection.pcc_isolator_a', 'recommendation.export_policy'],
            ['configuration' => '4P / visible / lockable · TBC', 'system_voltage' => $acVoltage, 'final_duty' => 'Utility access / poles / util. cat. / N TBC']
        );
        if ($plantControlRequired) {
            $rows[] = $this->protectionRow(
                'P-RPR-01', 'export_relay_01', 'Reverse-power / export relay', 0, '',
                'Independent hardwired zero-export failsafe relay', [], ['recommendation.export_policy', 'project.interconnection_requirements'],
                ['configuration' => 'CT+VT input · hardwired trip', 'system_voltage' => $acVoltage, 'final_duty' => 'Setpoint / delay / supervision / fail-safe TBC', 'rating_display' => 'SETTING TBC']
            );
        }

        return $rows;
    }

    /** @return array<int,string> */
    public function requiredNodeIds(Project $project): array
    {
        $project->loadMissing(['technicalCalculation', 'systemRecommendation']);
        $calculation = $project->technicalCalculation;
        $results = is_array($calculation?->results) ? $calculation->results : [];
        $systemType = $this->systemType($project, $calculation);
        return array_column($this->build($project, $calculation, $results, $systemType)['nodes'], 'id');
    }

    public function profileKeyForNodeId(string $nodeId): string
    {
        return match (true) {
            str_starts_with($nodeId, 'pv_group_') => 'pv_array',
            str_starts_with($nodeId, 'dc_isolator_') => 'dc_isolator',
            str_starts_with($nodeId, 'dc_fuse_') => 'dc_fuse',
            str_starts_with($nodeId, 'dc_spd_') => 'dc_spd',
            str_starts_with($nodeId, 'inverter_') => 'inverter',
            str_starts_with($nodeId, 'ac_breaker_') => 'ac_breaker',
            str_starts_with($nodeId, 'ac_isolator_') => 'ac_isolator',
            str_starts_with($nodeId, 'ac_combiner_') => 'ac_combiner',
            str_starts_with($nodeId, 'ac_spd_backup_') => 'ac_breaker',
            str_starts_with($nodeId, 'pcc_breaker_') => 'ac_breaker',
            str_starts_with($nodeId, 'pcc_disconnect_') => 'ac_isolator',
            str_starts_with($nodeId, 'ac_spd_') => 'ac_spd',
            str_starts_with($nodeId, 'ct_') => 'ct',
            str_starts_with($nodeId, 'meter_') => 'meter',
            str_starts_with($nodeId, 'grid_') => 'grid',
            str_starts_with($nodeId, 'pe_bus_') => 'pe_bus',
            str_starts_with($nodeId, 'ground_rod_') => 'ground_rod',
            str_starts_with($nodeId, 'data_logger_') => 'data_logger',
            str_starts_with($nodeId, 'plant_controller_') => 'plant_controller',
            str_starts_with($nodeId, 'export_relay_') => 'relay',
            str_starts_with($nodeId, 'battery_') => 'battery',
            str_starts_with($nodeId, 'backup_panel_') => 'backup_panel',
            default => $nodeId,
        };
    }

    /** @return array<int,array<string,mixed>> */
    private function assignments(?TechnicalCalculation $calculation, array $results): array
    {
        return array_values(array_filter(
            is_array($results['inverter_string_assignments'] ?? null) ? $results['inverter_string_assignments'] : [],
            'is_array'
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function fallbackAssignments(?TechnicalCalculation $calculation, array $results, int $inverterCount): array
    {
        $counts = array_values(array_filter(
            is_array($results['string_module_counts'] ?? null) ? $results['string_module_counts'] : [],
            static fn (mixed $value): bool => is_numeric($value) && (int) $value > 0
        ));
        $modulePowerWp = $this->number($calculation?->module_power_wp);
        $unitAcKw = $this->number($results['inverter_unit_ac_kw'] ?? $calculation?->inverter_ac_power_kw);
        $mpptCount = max(1, $this->integer($calculation?->inverter_mppt_count));
        $assignments = [];
        for ($index = 1; $index <= $inverterCount; $index++) {
            $assignments[$index] = [
                'inverter_index' => $index,
                'tag' => sprintf('INV-%02d', $index),
                'available_mppt_count' => $mpptCount,
                'used_mppt_count' => 0,
                'assigned_module_count' => 0,
                'assigned_dc_kw' => 0.0,
                'dc_ac_ratio' => 0.0,
                'strings' => [],
            ];
        }
        foreach ($counts as $offset => $modules) {
            $index = ($offset % $inverterCount) + 1;
            $mppt = (count($assignments[$index]['strings']) % $mpptCount) + 1;
            $dcKw = ((int) $modules * $modulePowerWp) / 1000;
            $assignments[$index]['strings'][] = [
                'string_index' => $offset + 1,
                'tag' => sprintf('STR-%02d', $offset + 1),
                'modules' => (int) $modules,
                'mppt_index' => $mppt,
                'mppt_label' => sprintf('MPPT-%02d', $mppt),
                'dc_kw' => round($dcKw, 3),
            ];
            $assignments[$index]['assigned_module_count'] += (int) $modules;
            $assignments[$index]['assigned_dc_kw'] += $dcKw;
        }
        foreach ($assignments as &$assignment) {
            $assignment['used_mppt_count'] = count($assignment['strings']);
            $assignment['assigned_dc_kw'] = round((float) $assignment['assigned_dc_kw'], 3);
            $assignment['dc_ac_ratio'] = $unitAcKw > 0 ? round((float) $assignment['assigned_dc_kw'] / $unitAcKw, 3) : 0.0;
        }
        unset($assignment);
        return array_values($assignments);
    }

    /** @return array<string,mixed> */
    private function node(
        string $id,
        string $symbol,
        string $tag,
        string $title,
        string $subtitle,
        string $rating,
        string $kind,
        string $engineeringProfile,
        array $position,
        bool $sourcePorts,
        array $attributes = []
    ): array {
        $ports = $sourcePorts ? $this->symbolRegistry->ports($symbol, true) : [];
        if ($ports === []) {
            $ports = $this->symbolRegistry->ports($symbol);
        }
        $registry = $this->symbolRegistry->symbol($symbol) ?? [];
        return array_replace([
            'id' => $id,
            'symbol' => $symbol,
            'tag' => $tag,
            'title' => $title,
            'subtitle' => $subtitle,
            'rating' => $rating,
            'kind' => $kind,
            'state' => 'ready',
            'ports' => $ports,
            'symbol_standard' => is_array($registry['standard'] ?? null) ? $registry['standard'] : [],
            'symbol_registry_version' => (string) ($this->symbolRegistry->all()['version'] ?? self::VERSION),
            'engineering_profile' => $engineeringProfile,
            'topology_profile' => self::PROFILE,
            'physical_equipment' => true,
            'default_position' => $position,
        ], $attributes);
    }

    /** @return array<string,mixed> */
    private function connection(
        string $id,
        string $from,
        string $to,
        string $label,
        string $detail,
        string $fromPort,
        string $toPort,
        string $network,
        bool $showLabel = true
    ): array {
        return [
            'id' => $id,
            'wire_id' => $id,
            'cable_tag' => $id,
            'from' => $from,
            'to' => $to,
            'from_port' => $fromPort,
            'to_port' => $toPort,
            'network' => $network,
            'label' => $label,
            'detail' => $detail,
            'show_label' => $showLabel,
            'routing_mode' => 'auto',
            'routing_clearance' => in_array($network, ['communication', 'protective_earth'], true) ? 'compact' : 'standard',
            'router_version' => SldSmartRouter::VERSION,
            'route_contract' => SldSmartRouter::CONTRACT,
            'route_strategy' => SldSmartRouter::ENGINE,
            'topology_profile' => self::PROFILE,
        ];
    }

    /** @return array<string,mixed> */
    private function cableRow(
        string $wireId,
        string $label,
        string $from,
        string $to,
        string $network,
        string $cableType,
        string $cableSize,
        int|string $cores,
        string $voltageClass,
        string $installationMethod,
        string $length,
        string $bomItemCode,
        array $sourceKeys,
        string $fromPort,
        string $toPort,
        int $parallelRuns = 1,
        string $routeBasis = 'final_route_tbc',
        string $lengthStatus = 'tbc'
    ): array {
        $coreText = $cores === '' ? '' : $cores.'C';
        $sizeText = $cableSize === '' ? '' : $cableSize.' mm²';
        $detail = implode(' · ', array_values(array_filter([$cableType, trim($coreText.' × '.$sizeText, ' ×'), $voltageClass])));
        return [
            'wire_id' => $wireId,
            'cable_tag' => $wireId,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'from_port' => $fromPort,
            'to_port' => $toPort,
            'network' => $network,
            'connection_key' => $from.':'.$fromPort.'>'.$to.':'.$toPort.'|'.$network,
            'cable_type' => $cableType,
            'cable_size' => $cableSize,
            'cores' => $cores,
            'voltage_class' => $voltageClass,
            'installation_method' => $installationMethod,
            'length_m' => $length,
            'bom_item_code' => $bomItemCode,
            'source_keys' => $sourceKeys,
            'parallel_runs' => max(1, $parallelRuns),
            'route_basis' => $routeBasis,
            'length_status' => $lengthStatus,
            'detail' => $detail !== '' ? $detail : $label,
        ];
    }

    /** @return array<string,mixed> */
    private function protectionRow(string $id, string $nodeId, string $label, float $rating, string $unit, string $designation, array $bomCodes, array $sourceKeys, array $attributes = []): array
    {
        return array_replace([
            'id' => $id,
            'node_id' => $nodeId,
            'label' => $label,
            'rating' => $rating,
            'unit' => $unit,
            'designation' => $designation,
            'bom_item_codes' => $bomCodes,
            'source_keys' => $sourceKeys,
            'configuration' => 'TBC',
            'system_voltage' => null,
            'final_duty' => 'Coordination TBC',
        ], $attributes);
    }

    /** @return array<int,array<string,mixed>> */
    private function dcIsolatorAssemblyPorts(): array
    {
        $ports = $this->symbolRegistry->ports('dc_isolator');
        foreach ($ports as &$port) {
            if (($port['id'] ?? null) === 'DC_IN') {
                $port['max_connections'] = 1;
                $port['label'] = 'Independent string inputs';
            }
            if (($port['id'] ?? null) === 'DC_OUT') {
                $port['max_connections'] = 2;
                $port['label'] = 'MPPT outputs / SPD shunt tap';
            }
        }
        unset($port);
        return $ports;
    }

    /** @return array<int,array<string,mixed>> */
    private function inverterPorts(string $systemType): array
    {
        $ports = $this->symbolRegistry->ports('inverter', true);
        if ($systemType !== 'hybrid') {
            $allowed = ['DC_IN', 'AC_OUT', 'PE', 'COM'];
            $ports = array_values(array_filter($ports, static fn (array $port): bool => in_array((string) ($port['id'] ?? ''), $allowed, true)));
        }
        $ports[] = [
            'id' => 'CTRL',
            'label' => 'Active-power limit input',
            'network' => 'control',
            'direction' => 'input',
            'side' => 'bottom',
            'terminal_class' => 'control_input',
            'anchor' => ['x' => 0.68, 'y' => 1.0],
            'max_connections' => 1,
        ];
        return $ports;
    }

    /** @return array<string,float|int|bool|null> */
    private function position(float $x, float $y): array
    {
        return ['x' => $x, 'y' => $y, 'w' => 144, 'h' => 120, 'locked' => false, 'show_tag' => true, 'show_rating' => true, 'show_subtitle' => true, 'show_ports' => true];
    }

    /** @param array<int,array<string,mixed>> $strings */
    private function stringGroupLabel(array $strings, int $assignedModules): string
    {
        $counts = array_map(static fn (array $string): int => (int) ($string['modules'] ?? 0), $strings);
        if ($counts === []) {
            return $assignedModules > 0 ? $assignedModules.' modules · allocation pending' : 'String allocation pending';
        }
        if (count(array_unique($counts)) === 1) {
            return count($counts).' strings × '.$counts[0].' modules';
        }
        return count($counts).' strings · '.implode(' + ', $counts).' modules';
    }

    private function systemType(Project $project, ?TechnicalCalculation $calculation): string
    {
        $candidate = $calculation?->system_type ?: $project->systemRecommendation?->recommended_system ?: $project->proposed_system;
        return in_array($candidate, ['on_grid', 'hybrid'], true) ? $candidate : 'on_grid';
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value) : 0;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function display(mixed $value, int $decimals): string
    {
        if (! is_numeric($value)) {
            return '—';
        }
        $formatted = number_format((float) $value, $decimals, '.', '');
        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
