<?php

namespace Tests\Unit;

use App\Services\Sld\SldElectricalRuleChecker;
use App\Services\Sld\SldSymbolRegistry;
use Tests\TestCase;

class SldElectricalRuleCheckerTest extends TestCase
{
    private SldElectricalRuleChecker $checker;
    private SldSymbolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(SldSymbolRegistry::class);
        $this->checker = app(SldElectricalRuleChecker::class);
    }

    public function test_valid_source_topology_has_no_blocking_error(): void
    {
        $validation = $this->checker->check($this->sourceGraph());

        $this->assertFalse($validation['blocking']);
        $this->assertSame(0, $validation['summary']['error']);
        $this->assertSame('review', $validation['status']);
        $this->assertSame(4, $validation['summary']['warning']);
    }

    public function test_duplicate_equipment_tag_is_blocking(): void
    {
        $graph = $this->sourceGraph();
        $graph['editor']['manual_nodes'][] = $this->manualNode('manual-monitor', 'monitoring', 'INV-01');

        $validation = $this->checker->check($graph);

        $this->assertTrue($validation['blocking']);
        $this->assertCount(2, array_filter($validation['issues'], fn (array $issue): bool => $issue['code'] === 'E007'));
    }

    public function test_incompatible_network_is_rejected(): void
    {
        $graph = $this->sourceGraph();
        $graph['editor']['manual_connections'][] = [
            'id' => 'bad-wire',
            'from' => 'pv_array',
            'to' => 'grid',
            'from_port' => 'DC_OUT',
            'to_port' => 'AC_PCC',
            'network' => 'dc_power',
        ];

        $validation = $this->checker->check($graph);

        $this->assertTrue($validation['blocking']);
        $this->assertContains('E003', array_column($validation['issues'], 'code'));
    }

    public function test_manual_power_wire_requires_cable_metadata(): void
    {
        $graph = $this->sourceGraph();
        $graph['editor']['manual_nodes'] = [
            $this->manualNode('panel-1', 'panel', 'DB-01'),
            $this->manualNode('load-1', 'load', 'LOAD-01'),
        ];
        $graph['editor']['manual_connections'][] = [
            'id' => 'manual-power',
            'from' => 'panel-1',
            'to' => 'load-1',
            'from_port' => 'AC_OUT',
            'to_port' => 'AC_IN',
            'network' => 'ac_power',
        ];

        $validation = $this->checker->check($graph);
        $codes = array_column($validation['issues'], 'code');

        $this->assertContains('W002', $codes);
        $this->assertContains('N001', $codes);
    }

    /** @return array<string, mixed> */
    private function sourceGraph(): array
    {
        $nodes = [
            $this->sourceNode('pv_array', 'pv_array', 'PVA-01'),
            $this->sourceNode('dc_protection', 'dc_protection', 'QSDC-01'),
            $this->sourceNode('inverter', 'inverter', 'INV-01'),
            $this->sourceNode('ac_protection', 'ac_protection', 'QFAC-01'),
            $this->sourceNode('meter', 'meter', 'MTR-01'),
            $this->sourceNode('grid', 'grid', 'GRID-01'),
        ];

        return [
            'schema_version' => 'nexorin-sld-v1.4.0',
            'nodes' => $nodes,
            'connections' => [
                ['id' => 'c1', 'from' => 'pv_array', 'to' => 'dc_protection', 'from_port' => 'DC_OUT', 'to_port' => 'DC_IN', 'network' => 'dc_power'],
                ['id' => 'c2', 'from' => 'dc_protection', 'to' => 'inverter', 'from_port' => 'DC_OUT', 'to_port' => 'DC_IN', 'network' => 'dc_power'],
                ['id' => 'c3', 'from' => 'inverter', 'to' => 'ac_protection', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN', 'network' => 'ac_power'],
                ['id' => 'c4', 'from' => 'ac_protection', 'to' => 'meter', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN', 'network' => 'ac_power'],
                ['id' => 'c5', 'from' => 'meter', 'to' => 'grid', 'from_port' => 'AC_OUT', 'to_port' => 'AC_PCC', 'network' => 'ac_power'],
            ],
            'editor' => ['excluded_nodes' => [], 'manual_nodes' => [], 'manual_connections' => []],
        ];
    }

    /** @return array<string, mixed> */
    private function sourceNode(string $id, string $symbol, string $tag): array
    {
        $ports = $this->registry->ports($symbol, true);

        return [
            'id' => $id,
            'symbol' => $symbol,
            'title' => $symbol,
            'tag' => $tag,
            'ports' => $ports !== [] ? $ports : $this->registry->ports($symbol),
        ];
    }

    /** @return array<string, mixed> */
    private function manualNode(string $id, string $symbol, string $tag): array
    {
        return [
            'id' => $id,
            'symbol' => $symbol,
            'kind' => $symbol,
            'title' => $symbol,
            'tag' => $tag,
            'ports' => $this->registry->ports($symbol),
        ];
    }
}
