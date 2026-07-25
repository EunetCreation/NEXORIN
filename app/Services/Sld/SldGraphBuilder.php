<?php

namespace App\Services\Sld;

use App\Models\Project;
use App\Models\TechnicalCalculation;
use App\Support\CalculationWarning;

class SldGraphBuilder
{
    public function __construct(
        private readonly SldSymbolRegistry $symbolRegistry,
        private readonly SldElectricalRuleChecker $electricalRuleChecker,
        private readonly SldEngineeringController $engineeringController,
        private readonly SldProfessionalTopologyPlan $professionalTopologyPlan,
        private readonly SldGeometryFoundation $geometryFoundation,
        private readonly SldVisualRegression $visualRegression,
    ) {
    }

    public const SCHEMA_VERSION = 'nexorin-sld-v1.9.0';

    /**
     * Build a deterministic, auditable SLD graph from the current project
     * engineering records. The graph is a display/review snapshot; it does
     * not replace the technical calculation or material specification data.
     *
     * @return array{schema_version:string,meta:array<string,mixed>,nodes:array<int,array<string,mixed>>,connections:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,source_snapshot:array<string,mixed>}
     */
    public function build(Project $project): array
    {
        $project->loadMissing([
            'technicalCalculation',
            'systemRecommendation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation',
            'loadAnalysis',
            'solarProfile',
            'roofSiteSurvey.surfaces',
        ]);

        $calculation = $project->technicalCalculation;
        $results = is_array($calculation?->results) ? $calculation->results : [];
        $systemType = $this->systemType($project, $calculation);
        $warnings = $this->warnings($project, $calculation, $results, $systemType);
        $protection = is_array($results['protection'] ?? null) ? $results['protection'] : [];

        $moduleCount = $this->integer($results['module_count'] ?? null);
        $stringCount = $this->integer($results['string_count'] ?? null);
        $modulesPerString = $this->integer($results['modules_per_string'] ?? null);
        $stringModuleCounts = $this->stringModuleCounts($results, $moduleCount, $stringCount, $modulesPerString);
        $stringLayout = $this->stringLayout($stringModuleCounts);
        $inverterCount = max($this->integer($results['inverter_count'] ?? null), 0);
        $inverterAcKw = $this->number($calculation?->inverter_ac_power_kw);
        $installedPvKw = $this->number($results['installed_pv_kw'] ?? null);
        $batteryKwh = $this->number($results['battery_nominal_kwh'] ?? null);
        $batteryPowerKw = $this->number($results['battery_minimum_power_kw'] ?? null);

        $topology = $this->professionalTopologyPlan->build($project, $calculation, $results, $systemType);
        $nodes = $topology['nodes'];
        $connections = $topology['connections'];
        $topologyMeta = is_array($topology['meta'] ?? null) ? $topology['meta'] : [];
        $initialPositions = is_array($topology['positions'] ?? null) ? $topology['positions'] : [];

        $nodes = $this->engineeringController->bindNodes($project, $nodes);
        $connections = $this->engineeringController->bindConnections($project, $connections);
        $sourceSnapshot = $this->sourceSnapshot($project, $calculation, $systemType);

        $graph = [
            'schema_version' => self::SCHEMA_VERSION,
            'meta' => [
                'project_id' => $project->getKey(),
                'project_number' => $project->project_number,
                'project_name' => $project->name,
                'system_type' => $systemType,
                'system_label' => $systemType === 'hybrid' ? 'Hybrid PV system' : 'On-grid PV system',
                'topology_profile' => (string) ($topologyMeta['topology_profile'] ?? SldProfessionalTopologyPlan::PROFILE),
                'topology_version' => (string) ($topologyMeta['topology_version'] ?? SldProfessionalTopologyPlan::VERSION),
                'physical_equipment_count' => (int) ($topologyMeta['physical_equipment_count'] ?? count($nodes)),
                'physical_connection_count' => (int) ($topologyMeta['physical_connection_count'] ?? count($connections)),
                'physical_string_count' => (int) ($topologyMeta['physical_string_count'] ?? 0),
                'physical_mppt_input_count' => (int) ($topologyMeta['physical_mppt_input_count'] ?? 0),
                'physical_dc_contract' => (string) ($topologyMeta['physical_dc_contract'] ?? ''),
                'protection_mode' => (string) ($topologyMeta['protection_mode'] ?? 'individual_components'),
                'grounding_generated' => (bool) ($topologyMeta['grounding_generated'] ?? false),
                'monitoring_enabled' => (bool) ($topologyMeta['monitoring_enabled'] ?? false),
                'plant_control_enabled' => (bool) ($topologyMeta['plant_control_enabled'] ?? false),
                'closed_loop_control_generated' => (bool) ($topologyMeta['closed_loop_control_generated'] ?? false),
                'title' => 'Single-line diagram',
                'generated_at' => now()->toISOString(),
                'calculation_status' => $calculation?->result_status,
                'calculation_verified' => $calculation?->result_status === 'verified',
                'summary' => [
                    'installed_pv_kw' => $installedPvKw,
                    'module_count' => $moduleCount,
                    'string_layout' => $stringLayout,
                    'string_module_counts' => $stringModuleCounts,
                    'inverter_string_assignments' => is_array($results['inverter_string_assignments'] ?? null) ? $results['inverter_string_assignments'] : [],
                    'inverter_count' => $inverterCount,
                    'inverter_unit_ac_kw' => $this->number($results['inverter_unit_ac_kw'] ?? $inverterAcKw),
                    'inverter_ac_kw' => $this->number($results['total_inverter_ac_kw'] ?? null),
                    'dc_ac_ratio' => $this->number($results['dc_ac_ratio'] ?? null),
                    'annual_energy_kwh' => $this->number($results['annual_energy_kwh'] ?? null),
                    'annual_energy_status' => 'preliminary_energy_model_reference_tbc',
                    'dc_voltage_drop_percent' => $this->number($results['dc_voltage_drop_percent'] ?? null),
                    'ac_voltage_drop_percent' => $this->number($results['ac_voltage_drop_percent'] ?? null),
                    'voltage_drop_status' => 'preliminary_until_route_lengths_confirmed',
                    'ac_earthing_system' => 'TBC',
                    'pcc_fault_level_ka' => null,
                    'engineering_release_status' => 'internal_review_not_for_construction',
                    'supply_phase' => data_get($results, 'assumptions.supply_phase'),
                    'module_power_wp' => $this->number($calculation?->module_power_wp),
                    'module_voc_v' => $this->number($calculation?->module_voc_v),
                    'module_vmp_v' => $this->number($calculation?->module_vmp_v),
                    'module_isc_a' => $this->number($calculation?->module_isc_a),
                    'module_imp_a' => $this->number($calculation?->module_imp_a),
                    'minimum_site_temperature_c' => $this->number($calculation?->minimum_site_temperature_c),
                    'maximum_cell_temperature_c' => $this->number($calculation?->maximum_cell_temperature_c),
                    'inverter_max_dc_voltage_v' => $this->number($calculation?->inverter_max_dc_voltage_v),
                    'inverter_mppt_min_voltage_v' => $this->number($calculation?->inverter_mppt_min_voltage_v),
                    'inverter_mppt_max_voltage_v' => $this->number($calculation?->inverter_mppt_max_voltage_v),
                    'inverter_max_input_current_a' => $this->number($calculation?->inverter_max_input_current_a),
                    'physical_inverter_count' => (int) ($topologyMeta['physical_inverter_count'] ?? $inverterCount),
                    'string_groups' => is_array($topologyMeta['string_groups'] ?? null) ? $topologyMeta['string_groups'] : [],
                ],
            ],
            'nodes' => $nodes,
            'connections' => $connections,
            'warnings' => $warnings,
            'source_snapshot' => $sourceSnapshot,
            'editor' => [
                'version' => 16,
                'engine' => 'maxgraph-0.24',
                'space' => 'model',
                'display_mode' => 'clean',
                'paper' => ['mode' => 'as_drawn'],
                'geometry' => $this->geometryFoundation->metadata($initialPositions),
                'routing' => [
                    'version' => SldSmartRouter::VERSION,
                    'contract' => SldSmartRouter::CONTRACT,
                    'engine' => SldSmartRouter::ENGINE,
                    'grid_size' => 10,
                    'wires' => count($connections),
                    'orthogonal_wires' => 0,
                    'crossings' => 0,
                    'clearance_conflicts' => 0,
                    'separation_conflicts' => 0,
                    'terminal_direction_violations' => 0,
                    'fallbacks' => 0,
                    'bends' => 0,
                    'length' => 0,
                ],
                'label_placement' => [
                    'version' => SldLabelPlacement::VERSION,
                    'contract' => SldLabelPlacement::CONTRACT,
                    'engine' => SldLabelPlacement::ENGINE,
                    'clearance' => SldLabelPlacement::CLEARANCE,
                    'wire_clearance' => SldLabelPlacement::WIRE_CLEARANCE,
                    'positions' => [],
                    'summary' => [
                        'labels' => 0,
                        'leaders' => 0,
                        'fallbacks' => 0,
                        'equipment_conflicts' => 0,
                        'label_conflicts' => 0,
                        'wire_conflicts' => 0,
                    ],
                ],
                'visual_regression' => $this->visualRegression->normalizeMetadata([
                    'canvas' => ['width' => 1600, 'height' => 920],
                    'positions' => $initialPositions,
                    'routes' => [],
                    'label_placement' => ['positions' => []],
                ]),
                'positions' => $initialPositions,
                'routes' => [],
                'manual_nodes' => [],
                'manual_connections' => [],
                'manual_lines' => [],
                'manual_shapes' => [],
                'annotations' => [],
                'layers' => [],
            ],
        ];

        $graph['electrical_validation'] = $this->electricalRuleChecker->check($graph);
        $graph['warnings'] = $this->electricalRuleChecker->mergeRevisionWarnings(
            $warnings,
            $graph['electrical_validation']
        );
        $graph['meta']['electrical_validation'] = $graph['electrical_validation']['summary'] ?? [];
        $graph['engineering_controller'] = $this->engineeringController->inspect($project, $graph);
        $graph['meta']['engineering_controller'] = $graph['engineering_controller']['summary'] ?? [];

        return $graph;
    }

    public function sourceFingerprint(Project $project): string
    {
        return $this->engineeringController->sourceFingerprint($project);
    }

    /** @return array<int, array<string, mixed>> */
    private function warnings(Project $project, ?TechnicalCalculation $calculation, array $results, string $systemType): array
    {
        $warnings = $calculation
            ? CalculationWarning::normalizeList($calculation->warnings ?? [], 'technical_calculation')
            : [];

        if (! $calculation || ! $calculation->calculated_at || $results === []) {
            $warnings[] = CalculationWarning::make(
                'SLD_CALCULATION_REQUIRED',
                'critical',
                'Technical calculation required',
                'The SLD cannot be accepted until a current technical calculation has produced electrical results.',
                'Run the technical calculation and review its warnings before regenerating the SLD.',
                'sld'
            );
        } elseif ($calculation->isStale($project)) {
            $warnings[] = CalculationWarning::make(
                'SLD_SOURCE_STALE',
                'warning',
                'SLD source is stale',
                'Project source data changed after the technical calculation was calculated.',
                'Recalculate the technical design, then generate a new SLD revision.',
                'sld'
            );
        }

        if ($calculation && $calculation->result_status !== 'verified') {
            $warnings[] = CalculationWarning::make(
                'SLD_CALCULATION_NOT_VERIFIED',
                'warning',
                'Technical calculation is not verified',
                'This SLD is a controlled design draft because the linked calculation is not engineer verified.',
                'Verify the technical calculation before approving the SLD for downstream release.',
                'sld'
            );
        }

        if (! in_array($systemType, ['on_grid', 'hybrid'], true)) {
            $warnings[] = CalculationWarning::make(
                'SLD_SYSTEM_TYPE_UNSUPPORTED',
                'critical',
                'System topology is not supported',
                'The current SLD generator supports on-grid and hybrid topology only.',
                'Select an on-grid or hybrid system, or keep this project in engineering review.',
                'sld'
            );
        }

        if ($systemType === 'hybrid' && $this->number($results['battery_nominal_kwh'] ?? null) <= 0) {
            $warnings[] = CalculationWarning::make(
                'SLD_BATTERY_SIZE_MISSING',
                'warning',
                'Hybrid battery size is unresolved',
                'The hybrid topology is present, but no positive battery nominal capacity is available in the calculation result.',
                'Complete the backup basis and rerun the technical calculation before final approval.',
                'sld'
            );
        }

        return CalculationWarning::unique($warnings);
    }

    private function systemType(Project $project, ?TechnicalCalculation $calculation): string
    {
        $candidate = $calculation?->system_type
            ?: $project->systemRecommendation?->recommended_system
            ?: $project->proposed_system;

        return in_array($candidate, ['on_grid', 'hybrid'], true) ? $candidate : 'on_grid';
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(Project $project, ?TechnicalCalculation $calculation, string $systemType): array
    {
        return [
            'fingerprint' => $this->sourceFingerprint($project),
            'system_type' => $systemType,
            'topology_profile' => SldProfessionalTopologyPlan::PROFILE,
            'topology_version' => SldProfessionalTopologyPlan::VERSION,
            'project' => [
                'id' => $project->getKey(),
                'project_number' => $project->project_number,
                'updated_at' => $project->updated_at?->toISOString(),
            ],
            'technical_calculation' => [
                'id' => $calculation?->getKey(),
                'result_status' => $calculation?->result_status,
                'calculated_at' => $calculation?->calculated_at?->toISOString(),
                'updated_at' => $calculation?->updated_at?->toISOString(),
            ],
            'recommendation' => [
                'id' => $project->systemRecommendation?->getKey(),
                'recommended_system' => $project->systemRecommendation?->recommended_system,
                'updated_at' => $project->systemRecommendation?->updated_at?->toISOString(),
            ],
            'bom' => [
                'id' => $project->billOfMaterial?->getKey(),
                'technical_calculation_id' => $project->billOfMaterial?->technical_calculation_id,
                'pricing_status' => $project->billOfMaterial?->pricing_status,
                'updated_at' => $project->billOfMaterial?->updated_at?->toISOString(),
            ],
            'financial_analysis' => [
                'id' => $project->financialAnalysis?->getKey(),
                'bill_of_material_id' => $project->financialAnalysis?->bill_of_material_id,
                'status' => $project->financialAnalysis?->status,
                'updated_at' => $project->financialAnalysis?->updated_at?->toISOString(),
            ],
            'quotation' => [
                'id' => $project->quotation?->getKey(),
                'bill_of_material_id' => $project->quotation?->bill_of_material_id,
                'financial_analysis_id' => $project->quotation?->financial_analysis_id,
                'status' => $project->quotation?->status,
                'updated_at' => $project->quotation?->updated_at?->toISOString(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function node(string $id, string $title, string $subtitle, string $rating, string $kind, string $state, array $attributes = []): array
    {
        $tag = match ($id) {
            'pv_array' => 'PVA-01',
            'dc_protection' => 'QSDC-01',
            'inverter' => 'INV-01',
            'ac_protection' => 'QFAC-01',
            'meter' => 'MTR-01',
            'grid' => 'GRID-01',
            'battery' => 'BAT-01',
            'backup_panel' => 'DB-BK-01',
            default => strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $id) ?: 'EQ', 0, 8)).'-01',
        };

        $symbol = $id;
        $ports = $this->symbolRegistry->ports($id, true);
        if ($ports === []) {
            $ports = $this->symbolRegistry->ports($id);
        }
        $registry = $this->symbolRegistry->symbol($id) ?? [];
        $symbol_standard = is_array($registry['standard'] ?? null) ? $registry['standard'] : [];
        $symbol_registry_version = (string) ($this->symbolRegistry->all()['version'] ?? '67L.1');

        return array_replace(
            compact('id', 'symbol', 'tag', 'title', 'subtitle', 'rating', 'kind', 'state', 'ports', 'symbol_standard', 'symbol_registry_version'),
            $attributes
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function inverterPortsForSystem(string $systemType): array
    {
        $ports = $this->symbolRegistry->ports('inverter', true);
        if ($systemType === 'hybrid') {
            return $ports;
        }

        $allowed = ['DC_IN', 'AC_OUT', 'PE', 'COM'];

        return array_values(array_filter(
            $ports,
            static fn (array $port): bool => in_array((string) ($port['id'] ?? ''), $allowed, true)
        ));
    }

    /** @return array<string, mixed> */
    private function connection(
        string $from,
        string $to,
        string $label,
        string $detail,
        string $fromPort,
        string $toPort,
        string $network
    ): array {
        return [
            'from' => $from,
            'to' => $to,
            'from_port' => $fromPort,
            'to_port' => $toPort,
            'network' => $network,
            'label' => $label,
            'detail' => $detail,
            'routing_mode' => 'auto',
            'routing_clearance' => 'standard',
            'router_version' => SldSmartRouter::VERSION,
            'route_contract' => SldSmartRouter::CONTRACT,
            'route_strategy' => SldSmartRouter::ENGINE,
        ];
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value) : 0;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** @return array<int, int> */
    private function stringModuleCounts(array $results, int $moduleCount, int $stringCount, int $modulesPerString): array
    {
        $stored = collect($results['string_module_counts'] ?? [])
            ->filter(fn (mixed $value): bool => is_numeric($value) && (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if ($stored !== [] && count($stored) === $stringCount && array_sum($stored) === $moduleCount) {
            return $stored;
        }

        if ($moduleCount <= 0) {
            return [];
        }

        if ($stringCount <= 0 && $modulesPerString > 0) {
            $stringCount = (int) ceil($moduleCount / $modulesPerString);
        }

        if ($stringCount <= 0) {
            return [];
        }

        $base = intdiv($moduleCount, $stringCount);
        $remainder = $moduleCount % $stringCount;

        return array_map(
            static fn (int $index): int => $base + ($index < $remainder ? 1 : 0),
            range(0, $stringCount - 1)
        );
    }

    /** @param array<int, int> $counts */
    private function stringLayout(array $counts): string
    {
        if ($counts === []) {
            return 'String layout pending';
        }

        if (count(array_unique($counts)) === 1) {
            return count($counts).' × '.$counts[0].' modules/string';
        }

        return implode(' + ', $counts).' modules/string';
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
