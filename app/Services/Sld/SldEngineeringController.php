<?php

namespace App\Services\Sld;

use App\Models\Project;
use App\Support\CalculationWarning;

class SldEngineeringController
{
    public const VERSION = '67L.3-R5-SLD-REF-001';

    public function __construct(
        private readonly SldProfessionalTopologyPlan $professionalTopologyPlan,
        private readonly SldGeometryFoundation $geometryFoundation,
        private readonly SldVisualRegression $visualRegression,
    ) {
    }

    /**
     * Return a stable fingerprint for the sources that control SLD topology,
     * electrical ratings, cable schedules, and protection schedules.
     */
    public function sourceFingerprint(Project $project): string
    {
        $project->loadMissing([
            'technicalCalculation',
            'systemRecommendation',
            'billOfMaterial.items',
            'loadAnalysis',
            'solarProfile',
            'roofSiteSurvey.surfaces',
        ]);

        return hash('sha256', json_encode([
            'project' => [$project->getKey(), $project->updated_at?->toISOString(), $project->proposed_system],
            'calculation' => [
                $project->technicalCalculation?->getKey(),
                $project->technicalCalculation?->updated_at?->toISOString(),
                $project->technicalCalculation?->calculated_at?->toISOString(),
                $project->technicalCalculation?->result_status,
                $project->technicalCalculation?->override_summary,
            ],
            'recommendation' => [
                $project->systemRecommendation?->getKey(),
                $project->systemRecommendation?->updated_at?->toISOString(),
                $project->systemRecommendation?->recommended_system,
            ],
            'load' => [
                $project->loadAnalysis?->getKey(),
                $project->loadAnalysis?->updated_at?->toISOString(),
            ],
            'solar' => [
                $project->solarProfile?->getKey(),
                $project->solarProfile?->updated_at?->toISOString(),
            ],
            'roof' => [
                $project->roofSiteSurvey?->getKey(),
                $project->roofSiteSurvey?->updated_at?->toISOString(),
                $project->roofSiteSurvey?->surfaces?->max('updated_at')?->toISOString(),
            ],
            'bom' => [
                $project->billOfMaterial?->getKey(),
                $project->billOfMaterial?->updated_at?->toISOString(),
                $project->billOfMaterial?->technical_calculation_id,
                $project->billOfMaterial?->items?->max('updated_at')?->toISOString(),
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<int, array<string, mixed>> $nodes
     *  @return array<int, array<string, mixed>>
     */
    public function bindNodes(Project $project, array $nodes): array
    {
        $profiles = $this->nodeProfiles($project);

        return array_map(function (array $node) use ($profiles): array {
            $id = (string) ($node['id'] ?? '');
            $profileKey = (string) ($node['engineering_profile'] ?? $this->professionalTopologyPlan->profileKeyForNodeId($id));
            $profile = $profiles[$profileKey] ?? [
                'authority' => 'technical_calculation',
                'source_keys' => [],
                'bom_item_codes' => [],
                'controlled_fields' => ['rating', 'subtitle', 'ports'],
            ];
            $node['engineering_binding'] = $profile + [
                'controller_version' => self::VERSION,
                'profile_key' => $profileKey,
                'physical_equipment' => ($node['physical_equipment'] ?? false) === true,
            ];

            return $node;
        }, $nodes);
    }

    /** @param array<int, array<string, mixed>> $connections
     *  @return array<int, array<string, mixed>>
     */
    public function bindConnections(Project $project, array $connections): array
    {
        $schedule = collect();
        foreach ($this->cableSchedule($project) as $row) {
            $keys = array_values(array_filter(array_map(
                'strval',
                is_array($row['source_connection_keys'] ?? null)
                    ? $row['source_connection_keys']
                    : [($row['source_connection_key'] ?? $row['connection_key'] ?? '')]
            )));
            foreach ($keys as $key) {
                $schedule->put($key, $row);
            }
        }

        return array_map(static function (array $connection, int $index) use ($schedule): array {
            $key = self::connectionKey($connection);
            $expected = $schedule->get($key);
            $connection['id'] = (string) ($connection['id'] ?? sprintf('SRC-%02d-%s', $index + 1, strtoupper(substr(hash('sha1', $key), 0, 8))));

            if (! is_array($expected)) {
                $connection['engineering_binding'] = [
                    'authority' => 'technical_calculation',
                    'controller_version' => self::VERSION,
                    'connection_key' => $key,
                    'controlled_fields' => ['network', 'from_port', 'to_port'],
                ];

                return $connection;
            }

            foreach (['wire_id', 'cable_tag', 'cable_type', 'cable_size', 'cores', 'voltage_class', 'installation_method'] as $field) {
                if (($expected[$field] ?? '') !== '') {
                    $connection[$field] = $expected[$field];
                }
            }
            $connection['detail'] = $expected['detail'];
            $connection['engineering_binding'] = [
                'authority' => 'technical_calculation',
                'controller_version' => self::VERSION,
                'connection_key' => $key,
                'source_keys' => $expected['source_keys'],
                'bom_item_code' => $expected['bom_item_code'],
                'controlled_fields' => [
                    'network', 'from_port', 'to_port', 'cable_type', 'cable_size', 'cores',
                    'voltage_class', 'installation_method',
                ],
            ];

            return $connection;
        }, $connections, array_keys($connections));
    }

    /** @return array<string, mixed> */
    public function inspect(Project $project, array $graph): array
    {
        $project->loadMissing([
            'technicalCalculation',
            'systemRecommendation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation',
        ]);

        $currentFingerprint = $this->sourceFingerprint($project);
        $drawingFingerprint = (string) data_get($graph, 'source_snapshot.fingerprint', '');
        $sourceCurrent = $drawingFingerprint !== '' && hash_equals($currentFingerprint, $drawingFingerprint);
        $conflicts = [];

        if (! $sourceCurrent) {
            $conflicts[] = $this->conflict(
                'K001',
                'error',
                'source',
                'Engineering source changed',
                'Technical Calculation, System Recommendation, project source data, or BOM/BOQ changed after this SLD revision was generated.',
                'Synchronize the draft from the current engineering source before review or controlled export.'
            );
        }

        $calculation = $project->technicalCalculation;
        if (! $calculation || ! $calculation->calculated_at) {
            $conflicts[] = $this->conflict('K002', 'error', 'calculation', 'Technical calculation is unavailable', 'No current calculated engineering result is available.', 'Run Technical Calculation before synchronizing the SLD.');
        } elseif ($calculation->result_status !== 'verified') {
            $conflicts[] = $this->conflict('K003', 'warning', 'calculation', 'Calculation is not engineer verified', 'The current SLD source remains an engineering draft.', 'Verify Technical Calculation before approving the controlled drawing.');
        }

        $expectedNodes = $this->requiredNodeIds($project);
        $actualNodes = collect($graph['nodes'] ?? [])->pluck('id')->filter()->map(fn ($id) => (string) $id)->all();
        foreach (array_diff($expectedNodes, $actualNodes) as $nodeId) {
            $conflicts[] = $this->conflict('K010', 'error', 'topology', 'Required sourced equipment is missing', sprintf('The current engineering topology requires %s.', str_replace('_', ' ', $nodeId)), 'Synchronize the draft to restore the calculation-controlled equipment.', [$nodeId]);
        }
        foreach (array_diff($actualNodes, $expectedNodes) as $nodeId) {
            $conflicts[] = $this->conflict('K011', 'warning', 'topology', 'Sourced topology contains an unexpected object', sprintf('%s is not part of the current calculation-controlled topology.', str_replace('_', ' ', $nodeId)), 'Review the system type and synchronize the draft. Manual objects are not affected.', [$nodeId]);
        }

        $expectedCables = collect($this->cableSchedule($project))->keyBy('connection_key');
        $connections = collect($graph['connections'] ?? [])->keyBy(fn (array $connection): string => self::connectionKey($connection));
        foreach ($expectedCables as $key => $expected) {
            $graphKeys = array_values(array_filter(array_map(
                'strval',
                is_array($expected['source_connection_keys'] ?? null)
                    ? $expected['source_connection_keys']
                    : [($expected['source_connection_key'] ?? $key)]
            )));
            foreach ($graphKeys as $graphKey) {
                $actual = $connections->get($graphKey);
                if (! is_array($actual)) {
                    $conflicts[] = $this->conflict('K020', 'error', 'cable', 'Controlled circuit is missing', sprintf('%s conductor is not present in the sourced SLD topology.', $expected['label']), 'Synchronize the draft from Technical Calculation.', [$expected['from'], $expected['to']], $graphKey);
                    continue;
                }

                $differences = [];
                foreach (['cable_type', 'cable_size', 'cores', 'voltage_class'] as $field) {
                    $expectedValue = (string) ($expected[$field] ?? '');
                    $actualValue = (string) ($actual[$field] ?? '');
                    if ($expectedValue !== '' && $expectedValue !== $actualValue) {
                        $differences[] = sprintf('%s: %s → %s', str_replace('_', ' ', $field), $actualValue !== '' ? $actualValue : 'missing', $expectedValue);
                    }
                }
                if ($differences !== []) {
                    $conflicts[] = $this->conflict('K021', 'error', 'cable', 'Cable schedule differs from Technical Calculation', implode('; ', $differences), 'Apply the current engineering source. Cable ratings on sourced circuits are not drawing-authority values.', [$expected['from'], $expected['to']], $graphKey);
                }
            }
        }

        foreach ($this->protectionSchedule($project) as $protection) {
            if (($protection['rating'] ?? 0) <= 0 || trim((string) ($protection['designation'] ?? '')) === '') {
                $conflicts[] = $this->conflict('K030', 'warning', 'protection', 'Protection schedule is incomplete', sprintf('%s has no complete calculated rating or designation.', $protection['label']), 'Review Technical Calculation protection outputs before approval.', [$protection['node_id']]);
            }
        }

        $bomHealth = $this->bomHealth($project);
        if ($bomHealth['status'] === 'missing') {
            $conflicts[] = $this->conflict('K040', 'warning', 'bom', 'BOM/BOQ has not been generated', 'Cable and protection quantities cannot be reconciled with a BOM/BOQ snapshot.', 'Generate BOM/BOQ from the latest Technical Calculation.');
        } elseif ($bomHealth['status'] === 'stale') {
            $conflicts[] = $this->conflict('K041', 'error', 'bom', 'BOM/BOQ is not synchronized', $bomHealth['message'], 'Regenerate BOM/BOQ from the current Technical Calculation, then synchronize the SLD.');
        }

        foreach ($this->bomCoverageConflicts($project) as $conflict) {
            $conflicts[] = $conflict;
        }

        $downstream = $this->downstreamHealth($project);
        if ($downstream['financial']['status'] === 'stale') {
            $conflicts[] = $this->conflict('K050', 'notice', 'financial', 'Financial analysis is downstream-stale', $downstream['financial']['message'], 'Recalculate financial analysis after final BOQ pricing.');
        }
        if ($downstream['quotation']['status'] === 'stale') {
            $conflicts[] = $this->conflict('K051', 'notice', 'quotation', 'Quotation is downstream-stale', $downstream['quotation']['message'], 'Regenerate quotation after BOQ and financial analysis are current.');
        }

        $acknowledged = collect(data_get($graph, 'engineering_controller.acknowledgements', []))
            ->pluck('code')
            ->filter()
            ->map(fn ($code) => (string) $code)
            ->all();
        $conflicts = array_map(static function (array $conflict) use ($acknowledged): array {
            $conflict['acknowledged'] = in_array($conflict['code'], $acknowledged, true);
            return $conflict;
        }, $conflicts);

        $counts = ['error' => 0, 'warning' => 0, 'notice' => 0, 'total' => count($conflicts)];
        foreach ($conflicts as $conflict) {
            $severity = $conflict['severity'];
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }
        $blocking = $counts['error'] > 0;
        $status = $blocking ? 'blocked' : ($counts['warning'] > 0 ? 'review' : 'synchronized');

        return [
            'version' => self::VERSION,
            'status' => $status,
            'blocking' => $blocking,
            'source_current' => $sourceCurrent,
            'current_fingerprint' => $currentFingerprint,
            'drawing_fingerprint' => $drawingFingerprint,
            'summary' => $counts,
            'sources' => $this->sourceChain($project, $sourceCurrent, $bomHealth, $downstream),
            'impacts' => $this->impacts($project, $graph, $conflicts),
            'cable_schedule' => array_values($expectedCables->all()),
            'protection_schedule' => $this->protectionSchedule($project),
            'conflicts' => array_values($conflicts),
            'checked_at' => now()->toISOString(),
            'message' => match ($status) {
                'blocked' => sprintf('%d engineering-controller error(s) block review and controlled export.', $counts['error']),
                'review' => sprintf('%d engineering item(s) require review.', $counts['warning']),
                default => 'Technical Calculation, SLD, BOM/BOQ, and downstream status are synchronized.',
            },
            'acknowledgements' => data_get($graph, 'engineering_controller.acknowledgements', []),
            'events' => data_get($graph, 'engineering_controller.events', []),
        ];
    }

    /** @return array<string, mixed> */
    public function reconcile(array $currentGraph, array $freshGraph, ?int $actorId = null): array
    {
        $editor = is_array($currentGraph['editor'] ?? null) ? $currentGraph['editor'] : [];
        $acknowledgements = data_get($currentGraph, 'engineering_controller.acknowledgements', []);
        $events = collect(data_get($currentGraph, 'engineering_controller.events', []))->take(-49)->values()->all();
        $events[] = [
            'action' => 'accept_engineering_source',
            'actor_id' => $actorId,
            'at' => now()->toISOString(),
            'from_fingerprint' => data_get($currentGraph, 'source_snapshot.fingerprint'),
            'to_fingerprint' => data_get($freshGraph, 'source_snapshot.fingerprint'),
        ];

        $freshGraph['editor'] = $this->preserveEditor($editor, $freshGraph);
        $freshGraph['engineering_controller'] = [
            'version' => self::VERSION,
            'acknowledgements' => $acknowledgements,
            'events' => $events,
        ];

        return $freshGraph;
    }

    /** @return array<string, mixed> */
    public function acknowledge(array $graph, string $reason, ?int $actorId = null): array
    {
        $controller = is_array($graph['engineering_controller'] ?? null) ? $graph['engineering_controller'] : [];
        $codes = collect($controller['conflicts'] ?? [])
            ->filter(fn (array $conflict): bool => in_array($conflict['severity'] ?? '', ['warning', 'notice'], true))
            ->pluck('code')
            ->filter()
            ->values()
            ->all();
        $acknowledgements = collect($controller['acknowledgements'] ?? [])
            ->reject(fn (array $item): bool => in_array($item['code'] ?? null, $codes, true))
            ->values()
            ->all();
        foreach ($codes as $code) {
            $acknowledgements[] = [
                'code' => $code,
                'reason' => $reason,
                'actor_id' => $actorId,
                'at' => now()->toISOString(),
            ];
        }
        $controller['acknowledgements'] = $acknowledgements;
        $controller['events'][] = [
            'action' => 'acknowledge_review_items',
            'actor_id' => $actorId,
            'reason' => $reason,
            'at' => now()->toISOString(),
        ];
        $graph['engineering_controller'] = $controller;

        return $graph;
    }

    /** @return array<int, array<string, mixed>> */
    public function cableSchedule(Project $project): array
    {
        return $this->professionalTopologyPlan->cableSchedule($project);
    }

    /** @return array<int, array<string, mixed>> */
    public function protectionSchedule(Project $project): array
    {
        return $this->professionalTopologyPlan->protectionSchedule($project);
    }

    /** @return array<string, array<string, mixed>> */
    private function nodeProfiles(Project $project): array
    {
        $systemType = $project->technicalCalculation?->system_type
            ?: $project->systemRecommendation?->recommended_system
            ?: $project->proposed_system;
        $inverterItemCode = match ($systemType) {
            'hybrid' => 'INV-HYBRID',
            'off_grid' => 'INV-OFF-GRID',
            default => 'INV-ON-GRID',
        };

        $controlled = ['rating', 'subtitle', 'ports'];

        return [
            'pv_array' => ['authority' => 'technical_calculation', 'source_keys' => ['results.module_count', 'results.string_module_counts', 'results.inverter_string_assignments', 'results.installed_pv_kw'], 'bom_item_codes' => ['PV-MODULE'], 'controlled_fields' => $controlled],
            'pv_string' => ['authority' => 'technical_calculation', 'source_keys' => ['results.string_module_counts', 'results.inverter_string_assignments', 'inputs.module_power_wp', 'inputs.module_voc_v', 'inputs.module_vmp_v'], 'bom_item_codes' => ['PV-MODULE'], 'controlled_fields' => $controlled],
            'dc_isolator' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.dc_isolator_a', 'inputs.inverter_max_dc_voltage_v'], 'bom_item_codes' => ['DC-ISOLATOR'], 'controlled_fields' => $controlled],
            'dc_fuse' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.string_fuse_a'], 'bom_item_codes' => ['DC-FUSE-SET'], 'controlled_fields' => $controlled],
            'dc_busbar' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.dc_isolator_a', 'inputs.inverter_max_dc_voltage_v'], 'bom_item_codes' => [], 'controlled_fields' => $controlled],
            'dc_spd' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.dc_spd', 'inputs.inverter_max_dc_voltage_v'], 'bom_item_codes' => ['DC-SPD'], 'controlled_fields' => $controlled],
            'inverter' => ['authority' => 'technical_calculation', 'source_keys' => ['results.inverter_count', 'results.inverter_string_assignments', 'inputs.inverter_ac_power_kw', 'inputs.inverter_mppt_count'], 'bom_item_codes' => [$inverterItemCode], 'controlled_fields' => $controlled],
            'ac_breaker' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.inverter_feeder_breaker_a', 'results.protection.main_ac_breaker_a', 'results.protection.ac_breaker_a', 'results.ac_nominal_voltage_v'], 'bom_item_codes' => ['AC-BREAKER'], 'controlled_fields' => $controlled],
            'ac_isolator' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.inverter_feeder_isolator_a', 'results.protection.pcc_isolator_a', 'results.protection.ac_breaker_a', 'results.ac_nominal_voltage_v'], 'bom_item_codes' => ['AC-ISOLATOR-METER'], 'controlled_fields' => $controlled],
            'ac_combiner' => ['authority' => 'technical_calculation', 'source_keys' => ['results.total_inverter_ac_kw', 'results.inverter_count', 'results.protection.ac_combiner_bus_a'], 'bom_item_codes' => ['ACDB-PANEL'], 'controlled_fields' => $controlled],
            'ac_spd' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.ac_spd', 'results.ac_nominal_voltage_v'], 'bom_item_codes' => ['AC-SPD'], 'controlled_fields' => $controlled],
            'ct' => ['authority' => 'engineering_generator', 'source_keys' => ['results.inverter_unit_rated_current_a', 'results.ac_nominal_voltage_v', 'recommendation.export_policy'], 'bom_item_codes' => ['CURRENT-TRANSFORMER'], 'controlled_fields' => $controlled],
            'meter' => ['authority' => 'system_recommendation', 'source_keys' => ['recommendation.export_policy'], 'bom_item_codes' => ['SMART-METER-ENERGY-METER', 'ADVANCED-METER-UTILITY'], 'controlled_fields' => $controlled],
            'grid' => ['authority' => 'project_source', 'source_keys' => ['load.supply_phase'], 'bom_item_codes' => [], 'controlled_fields' => $controlled],
            'pe_bus' => ['authority' => 'engineering_generator', 'source_keys' => ['project.earthing_design'], 'bom_item_codes' => [], 'controlled_fields' => $controlled],
            'ground_rod' => ['authority' => 'engineering_generator', 'source_keys' => ['project.earthing_design'], 'bom_item_codes' => [], 'controlled_fields' => $controlled],
            'data_logger' => ['authority' => 'system_recommendation', 'source_keys' => ['results.inverter_count', 'recommendation.export_policy'], 'bom_item_codes' => [], 'controlled_fields' => $controlled],
            'plant_controller' => ['authority' => 'system_recommendation', 'source_keys' => ['recommendation.export_policy'], 'bom_item_codes' => [], 'controlled_fields' => $controlled],
            'relay' => ['authority' => 'engineering_generator', 'source_keys' => ['recommendation.export_policy', 'results.ac_nominal_voltage_v'], 'bom_item_codes' => [], 'controlled_fields' => $controlled],
            'battery' => ['authority' => 'technical_calculation', 'source_keys' => ['results.battery_nominal_kwh', 'results.battery_minimum_power_kw'], 'bom_item_codes' => ['BATTERY-PACK', 'BATTERY-RACK'], 'controlled_fields' => $controlled],
            'backup_panel' => ['authority' => 'technical_calculation', 'source_keys' => ['results.battery_nominal_kwh'], 'bom_item_codes' => ['ACDB-PANEL'], 'controlled_fields' => $controlled],
            // Legacy profiles remain supported for existing revisions and E2E fixtures.
            'dc_protection' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection'], 'bom_item_codes' => ['DC-ISOLATOR', 'DC-SPD', 'DC-FUSE-SET'], 'controlled_fields' => $controlled],
            'ac_protection' => ['authority' => 'technical_calculation', 'source_keys' => ['results.protection.ac_breaker_a', 'results.protection.ac_spd'], 'bom_item_codes' => ['AC-BREAKER', 'AC-SPD', 'AC-ISOLATOR-METER'], 'controlled_fields' => $controlled],
        ];
    }

    /** @return array<int, string> */
    private function requiredNodeIds(Project $project): array
    {
        return $this->professionalTopologyPlan->requiredNodeIds($project);
    }

    /** @return array<string, mixed> */
    private function bomHealth(Project $project): array
    {
        $bom = $project->billOfMaterial;
        if (! $bom) {
            return ['status' => 'missing', 'message' => 'BOM/BOQ has not been generated.'];
        }
        $calculation = $project->technicalCalculation;
        $snapshotId = data_get($bom->source_snapshot, 'technical_calculation_id');
        $stale = ! $calculation
            || (int) $bom->technical_calculation_id !== (int) $calculation->getKey()
            || (int) $snapshotId !== (int) $calculation->getKey()
            || (string) data_get($bom->source_snapshot, 'calculated_at') !== (string) $calculation->calculated_at?->toISOString();

        return [
            'status' => $stale ? 'stale' : ($bom->pricing_status === 'priced' ? 'ready' : 'draft'),
            'message' => $stale
                ? 'The BOM/BOQ snapshot does not match the current Technical Calculation.'
                : ($bom->pricing_status === 'priced' ? 'BOM/BOQ quantities and pricing are current.' : 'BOM quantities are current; BOQ pricing remains draft.'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function bomCoverageConflicts(Project $project): array
    {
        $bom = $project->billOfMaterial;
        if (! $bom) {
            return [];
        }

        $items = $bom->items->keyBy('item_code');
        $groups = [];
        foreach ($this->cableSchedule($project) as $cable) {
            if ($cable['bom_item_code']) {
                $groups[] = [
                    'codes' => [$cable['bom_item_code']],
                    'label' => $cable['label'],
                ];
            }
        }
        foreach ($this->protectionSchedule($project) as $protection) {
            foreach ($protection['bom_item_codes'] as $code) {
                $groups[] = ['codes' => [$code], 'label' => $protection['label']];
            }
        }

        $profiles = $this->nodeProfiles($project);
        foreach ($this->requiredNodeIds($project) as $nodeId) {
            $profileKey = $this->professionalTopologyPlan->profileKeyForNodeId($nodeId);
            $codes = array_values(array_filter((array) data_get($profiles, $profileKey.'.bom_item_codes', [])));
            if ($codes === []) {
                continue;
            }
            $isMeter = $profileKey === 'meter';
            $label = str($profileKey)->replace('_', ' ')->headline()->toString();
            $groups[] = [
                'codes' => $isMeter ? $codes : [reset($codes)],
                'label' => $label,
                'any' => $isMeter,
            ];
            if (! $isMeter) {
                foreach (array_slice($codes, 1) as $code) {
                    $groups[] = ['codes' => [$code], 'label' => $label];
                }
            }
        }

        $seen = [];
        $conflicts = [];
        foreach ($groups as $group) {
            $codes = array_values(array_unique($group['codes']));
            sort($codes);
            $dedupeKey = implode('|', $codes);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $covered = collect($codes)->contains(fn (string $code): bool => $items->has($code));
            if ($covered) {
                continue;
            }
            $conflicts[] = $this->conflict(
                'K042',
                'warning',
                'bom',
                'BOM coverage is incomplete',
                sprintf('%s is not represented by BOM item %s.', $group['label'], implode(' or ', $codes)),
                'Regenerate BOM/BOQ and review scope controls.'
            );
        }

        return $conflicts;
    }

    /** @return array<string, mixed> */
    private function downstreamHealth(Project $project): array
    {
        $bom = $project->billOfMaterial;
        $financial = $project->financialAnalysis;
        $quotation = $project->quotation;

        $financialStatus = 'missing';
        $financialMessage = 'Financial analysis has not been calculated.';
        if ($financial) {
            $financialCurrent = $bom
                && (int) $financial->bill_of_material_id === (int) $bom->getKey()
                && abs((float) $financial->project_capex_idr - (float) $bom->grand_price_idr) < 0.01;
            $financialStatus = $financialCurrent ? 'ready' : 'stale';
            $financialMessage = $financialCurrent
                ? 'Financial CAPEX matches the latest BOQ.'
                : 'Financial analysis does not reference the latest BOQ total.';
        }

        $quotationStatus = 'missing';
        $quotationMessage = 'Quotation has not been generated.';
        if ($quotation) {
            $quotationCurrent = $bom && $financial
                && (int) $quotation->bill_of_material_id === (int) $bom->getKey()
                && (int) $quotation->financial_analysis_id === (int) $financial->getKey();
            $quotationStatus = $quotationCurrent ? 'ready' : 'stale';
            $quotationMessage = $quotationCurrent
                ? 'Quotation references the latest BOQ and financial analysis.'
                : 'Quotation snapshot does not reference the latest BOQ and financial analysis.';
        }

        return [
            'financial' => ['status' => $financialStatus, 'message' => $financialMessage],
            'quotation' => ['status' => $quotationStatus, 'message' => $quotationMessage],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sourceChain(Project $project, bool $sourceCurrent, array $bomHealth, array $downstream): array
    {
        $calculation = $project->technicalCalculation;
        $recommendation = $project->systemRecommendation;
        $bom = $project->billOfMaterial;
        $financial = $project->financialAnalysis;
        $quotation = $project->quotation;

        return [
            ['key' => 'calculation', 'label' => 'Technical Calculation', 'status' => $calculation ? ($calculation->result_status === 'verified' ? 'ready' : 'review') : 'missing', 'detail' => $calculation ? str($calculation->result_status)->headline()->toString() : 'Not calculated', 'authority' => true],
            ['key' => 'recommendation', 'label' => 'System Recommendation', 'status' => $recommendation ? 'ready' : 'missing', 'detail' => $recommendation ? str($recommendation->recommended_system)->headline()->toString() : 'Not available', 'authority' => true],
            ['key' => 'sld', 'label' => 'SLD Controlled Graph', 'status' => $sourceCurrent ? 'ready' : 'stale', 'detail' => $sourceCurrent ? 'Current source fingerprint' : 'Synchronization required', 'authority' => false],
            ['key' => 'bom', 'label' => 'BOM / BOQ', 'status' => $bomHealth['status'], 'detail' => $bom ? sprintf('%s · %s', $bom->status_label, str($bom->pricing_status)->headline()) : 'Not generated', 'authority' => false],
            ['key' => 'financial', 'label' => 'Financial Analysis', 'status' => $downstream['financial']['status'], 'detail' => $financial ? $financial->status_label : 'Not calculated', 'authority' => false],
            ['key' => 'quotation', 'label' => 'Quotation', 'status' => $downstream['quotation']['status'], 'detail' => $quotation ? $quotation->status_label : 'Not generated', 'authority' => false],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function impacts(Project $project, array $graph, array $conflicts): array
    {
        $categories = collect($conflicts)->groupBy('category');
        return [
            ['key' => 'topology', 'label' => 'Topology', 'count' => $categories->get('topology', collect())->count(), 'status' => $categories->has('topology') ? 'review' : 'ready'],
            ['key' => 'cable', 'label' => 'Cable schedule', 'count' => $categories->get('cable', collect())->count(), 'status' => $categories->has('cable') ? 'review' : 'ready'],
            ['key' => 'protection', 'label' => 'Protection schedule', 'count' => $categories->get('protection', collect())->count(), 'status' => $categories->has('protection') ? 'review' : 'ready'],
            ['key' => 'bom', 'label' => 'BOM / BOQ', 'count' => $categories->get('bom', collect())->count(), 'status' => $categories->has('bom') ? 'review' : 'ready'],
            ['key' => 'quotation', 'label' => 'Quotation', 'count' => $categories->get('quotation', collect())->count(), 'status' => $categories->has('quotation') ? 'review' : 'ready'],
        ];
    }

    /** @return array<string, mixed> */
    private function preserveEditor(array $editor, array $freshGraph): array
    {
        $freshEditor = is_array($freshGraph['editor'] ?? null) ? $freshGraph['editor'] : [];
        $validNodeIds = collect($freshGraph['nodes'] ?? [])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $freshPositions = collect($freshEditor['positions'] ?? [])->only($validNodeIds)->all();
        $preservedPositions = collect($editor['positions'] ?? [])->only($validNodeIds)->all();
        $validConnectionIds = collect($freshGraph['connections'] ?? [])
            ->flatMap(fn (array $connection): array => array_values(array_filter([
                isset($connection['id']) ? (string) $connection['id'] : null,
                isset($connection['_id']) ? (string) $connection['_id'] : null,
            ])))
            ->unique()
            ->values()
            ->all();
        $manualConnectionIds = collect($editor['manual_connections'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();
        $validRouteIds = array_values(array_unique(array_merge($validConnectionIds, $manualConnectionIds)));
        $freshRoutes = collect($freshEditor['routes'] ?? [])->only($validRouteIds)->all();
        $preservedRoutes = collect($editor['routes'] ?? [])->only($validRouteIds)->all();
        $migratedPositions = $this->migrateLegacyPvGroupPositions(
            is_array($editor['positions'] ?? null) ? $editor['positions'] : [],
            is_array($freshGraph['nodes'] ?? null) ? $freshGraph['nodes'] : [],
            $freshPositions,
            $preservedPositions,
        );

        // Keep the fresh APP67L.2 professional layout for newly introduced
        // physical equipment, while retaining user-adjusted geometry for any
        // sourced node whose deterministic ID still exists.
        $merged = array_replace($freshEditor, $editor);
        $merged['positions'] = array_replace($freshPositions, $migratedPositions, $preservedPositions);
        $merged['routes'] = array_replace($freshRoutes, $preservedRoutes);
        $merged['excluded_nodes'] = collect($editor['excluded_nodes'] ?? [])
            ->filter(fn ($id) => in_array((string) $id, $validNodeIds, true))
            ->values()
            ->all();
        $freshLabelPlacement = is_array($freshEditor['label_placement'] ?? null) ? $freshEditor['label_placement'] : [];
        $preservedLabelPlacement = is_array($editor['label_placement'] ?? null) ? $editor['label_placement'] : [];
        $freshLabelPositions = collect($freshLabelPlacement['positions'] ?? [])->only($validNodeIds)->all();
        $preservedLabelPositions = collect($preservedLabelPlacement['positions'] ?? [])->only($validNodeIds)->all();
        $merged['label_placement'] = array_replace($freshLabelPlacement, $preservedLabelPlacement, [
            'positions' => array_replace($freshLabelPositions, $preservedLabelPositions),
        ]);
        $merged['version'] = max(16, (int) ($merged['version'] ?? 16));
        $merged['geometry'] = $this->geometryFoundation->metadata(
            is_array($merged['positions'] ?? null) ? $merged['positions'] : [],
            is_array($merged['excluded_nodes'] ?? null) ? $merged['excluded_nodes'] : [],
        );
        $merged['routing'] = array_replace(
            is_array($merged['routing'] ?? null) ? $merged['routing'] : [],
            [
                'version' => SldSmartRouter::VERSION,
                'contract' => SldSmartRouter::CONTRACT,
                'engine' => SldSmartRouter::ENGINE,
                'grid_size' => 10,
            ],
        );
        $merged['label_placement'] = array_replace(
            is_array($merged['label_placement'] ?? null) ? $merged['label_placement'] : [],
            [
                'version' => SldLabelPlacement::VERSION,
                'contract' => SldLabelPlacement::CONTRACT,
                'engine' => SldLabelPlacement::ENGINE,
                'clearance' => SldLabelPlacement::CLEARANCE,
                'wire_clearance' => SldLabelPlacement::WIRE_CLEARANCE,
            ],
        );
        $merged['visual_regression'] = $this->visualRegression->normalizeMetadata($merged);

        return $merged;
    }

    /**
     * APP67L revisions represented one inverter's PV strings as a single
     * `pv_group_XX` node. SLD-REF-001 replaces that aggregate with physical
     * `pv_string_###` nodes. Preserve the user's former group placement by
     * translating the fresh physical-string cluster around the old group
     * centre. Any already-saved physical string position remains authoritative.
     *
     * @param  array<string, array<string, mixed>>  $legacyPositions
     * @param  array<int, array<string, mixed>>  $freshNodes
     * @param  array<string, array<string, mixed>>  $freshPositions
     * @param  array<string, array<string, mixed>>  $preservedPositions
     * @return array<string, array<string, mixed>>
     */
    private function migrateLegacyPvGroupPositions(array $legacyPositions, array $freshNodes, array $freshPositions, array $preservedPositions): array
    {
        $migrated = [];
        $stringNodes = collect($freshNodes)
            ->filter(fn (array $node): bool => ($node['symbol'] ?? null) === 'pv_string')
            ->groupBy(fn (array $node): int => max(1, (int) $node['inverter_index'] ?? 1));

        foreach ($stringNodes as $inverterIndex => $nodes) {
            $legacyId = sprintf('pv_group_%02d', (int) $inverterIndex);
            $legacy = $legacyPositions[$legacyId] ?? null;
            if (! is_array($legacy)) {
                continue;
            }

            $cluster = collect($nodes)
                ->map(function (array $node) use ($freshPositions): ?array {
                    $id = (string) ($node['id'] ?? '');
                    $position = $freshPositions[$id] ?? null;
                    return $id !== '' && is_array($position) ? ['id' => $id, 'position' => $position] : null;
                })
                ->filter()
                ->values();
            if ($cluster->isEmpty()) {
                continue;
            }

            $freshCentreX = $cluster->avg(fn (array $item): float => $this->positionCentre($item['position'], 'x', 'w'));
            $freshCentreY = $cluster->avg(fn (array $item): float => $this->positionCentre($item['position'], 'y', 'h'));
            $legacyCentreX = $this->positionCentre($legacy, 'x', 'w');
            $legacyCentreY = $this->positionCentre($legacy, 'y', 'h');
            $deltaX = $legacyCentreX - $freshCentreX;
            $deltaY = $legacyCentreY - $freshCentreY;

            foreach ($cluster as $item) {
                $id = $item['id'];
                if (isset($preservedPositions[$id])) {
                    continue;
                }
                $position = $item['position'];
                $position['x'] = round((float) ($position['x'] ?? 0) + $deltaX, 3);
                $position['y'] = round((float) ($position['y'] ?? 0) + $deltaY, 3);
                $migrated[$id] = $position;
            }
        }

        return $migrated;
    }

    /** @param array<string, mixed> $position */
    private function positionCentre(array $position, string $axis, string $dimension): float
    {
        return (float) ($position[$axis] ?? 0) + ((float) ($position[$dimension] ?? 0) / 2);
    }

    /** @return array<string, mixed> */
    private function cableRow(string $wireId, string $label, string $from, string $to, string $network, string $type, string $size, int $cores, string $voltageClass, string $method, string $length, string $bomCode, array $sourceKeys): array
    {
        return [
            'wire_id' => $wireId,
            'cable_tag' => $wireId,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'connection_key' => $from.'>'.$to.'|'.$network,
            'network' => $network,
            'cable_type' => $type,
            'cable_size' => $size,
            'cores' => $cores,
            'voltage_class' => $voltageClass,
            'installation_method' => $method,
            'length_m' => $length,
            'bom_item_code' => $bomCode,
            'source_keys' => $sourceKeys,
            'detail' => trim(sprintf('%s · %sC × %s mm² · %s', $type, $cores, $size !== '' ? $size : 'TBC', $voltageClass)),
        ];
    }

    /** @return array<string, mixed> */
    private function conflict(string $code, string $severity, string $category, string $title, string $message, string $action, array $objects = [], ?string $connectionKey = null): array
    {
        return compact('code', 'severity', 'category', 'title', 'message', 'action', 'objects', 'connectionKey');
    }

    private static function connectionKey(array $connection): string
    {
        return sprintf(
            '%s:%s>%s:%s|%s',
            (string) ($connection['from'] ?? ''),
            (string) ($connection['from_port'] ?? ''),
            (string) ($connection['to'] ?? ''),
            (string) ($connection['to_port'] ?? ''),
            (string) ($connection['network'] ?? 'unclassified'),
        );
    }

    private function formatNumber(mixed $value): string
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return '';
        }
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
