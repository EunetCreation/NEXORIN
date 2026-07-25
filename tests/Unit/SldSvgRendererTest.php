<?php

namespace Tests\Unit;

use App\Services\Sld\SldPaperComposer;
use App\Services\Sld\SldSvgRenderer;
use PHPUnit\Framework\TestCase;

class SldSvgRendererTest extends TestCase
{
    public function test_as_drawn_output_is_clean_and_print_controlled(): void
    {
        $graph = [
            'schema_version' => 'nexorin-sld-v1.2.0',
            'meta' => [
                'title' => 'Single-line diagram',
                'project_number' => 'KTN-TEST-001',
                'project_name' => 'Renderer test',
                'system_label' => 'On-grid PV system',
                'summary' => [],
            ],
            'nodes' => [],
            'connections' => [],
            'editor' => [
                'paper' => ['mode' => 'as_drawn'],
                'positions' => [
                    'pv' => ['x' => 100, 'y' => 100, 'w' => 136, 'h' => 126, 'tag_override' => 'PVA-USER-01'],
                    'inv' => ['x' => 520, 'y' => 320, 'w' => 136, 'h' => 126],
                ],
                'routes' => [
                    'wire_dc' => [['x' => 260, 'y' => 150], ['x' => 420, 'y' => 150]],
                    'deleted_wire' => [['x' => 9000, 'y' => 9000]],
                ],
                'manual_nodes' => [
                    ['id' => 'pv', 'kind' => 'pv_array', 'title' => 'PV array', 'tag' => 'PVA-01', 'rating' => 'Rating not specified', 'subtitle' => 'Generation source', 'state' => 'manual'],
                    ['id' => 'inv', 'kind' => 'inverter', 'title' => 'String inverter', 'tag' => 'INV-01', 'rating' => '5 kW', 'subtitle' => 'DC to AC conversion', 'state' => 'manual'],
                ],
                'manual_lines' => [[
                    'id' => 'leader_1', 'entity' => 'leader', 'x1' => 120, 'y1' => 80, 'x2' => 300, 'y2' => 40,
                    'line_type' => 'continuous', 'lineweight' => 1.4, 'layer' => 'SLD-ANNOTATION',
                ]],
                'annotations' => [[
                    'id' => 'note_1', 'text' => 'Verify DC isolator location', 'x' => 300, 'y' => 20, 'w' => 210, 'h' => 50,
                ]],
                'manual_connections' => [[
                    'id' => 'wire_dc', 'from' => 'pv', 'to' => 'inv', 'network' => 'dc_positive',
                    'wire_id' => 'W-DC-001', 'detail' => 'Cable data not specified', 'show_label' => true,
                ]],
            ],
        ];

        $svg = (new SldSvgRenderer(new SldPaperComposer()))->render($graph);

        self::assertStringContainsString('PRIMARY POWER TOPOLOGY · AS DRAWN', $svg);
        self::assertStringContainsString('>NTS<', $svg);
        self::assertStringContainsString('>AS DRAWN<', $svg);
        self::assertStringNotContainsString('>Rating not specified<', $svg);
        self::assertStringNotContainsString('Cable data not specified', $svg);
        self::assertStringContainsString('PVA-USER-01', $svg);
        self::assertStringContainsString('marker-start="url(#sld-leader-arrow)"', $svg);
        self::assertStringContainsString('Verify DC isolator location', $svg);
        self::assertStringNotContainsString('class="sld-wire-callout"><rect', $svg);
    }
}
