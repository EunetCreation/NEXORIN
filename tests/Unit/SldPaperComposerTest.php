<?php

namespace Tests\Unit;

use App\Services\Sld\SldPaperComposer;
use PHPUnit\Framework\TestCase;

class SldPaperComposerTest extends TestCase
{
    public function test_small_connected_drawing_remains_single_sheet(): void
    {
        $graph = $this->graph(6, true, 235.0, 0.0);

        $result = (new SldPaperComposer())->compose($graph);

        self::assertSame('single', $result['mode']);
        self::assertCount(1, $result['sheets']);
        self::assertSame('pass', $result['summary']['readability']);
    }

    public function test_large_connected_drawing_creates_overview_and_readable_detail_sheets(): void
    {
        $graph = $this->graph(32, true, 255.0, 165.0);

        $result = (new SldPaperComposer())->compose($graph);

        self::assertSame('multi', $result['mode']);
        self::assertGreaterThan(2, $result['summary']['sheet_count']);
        self::assertSame('overview', $result['sheets'][0]['type']);
        foreach (array_slice($result['sheets'], 1) as $sheet) {
            self::assertLessThanOrEqual(SldPaperComposer::MAX_DETAIL_NODES, count($sheet['node_ids']));
            self::assertGreaterThanOrEqual(SldPaperComposer::MIN_READABLE_SCALE, $sheet['transform']['scale']);
        }
    }

    public function test_dense_unconnected_symbol_board_becomes_paginated_catalog(): void
    {
        $graph = $this->graph(57, false, 190.0, 150.0);

        $result = (new SldPaperComposer())->compose($graph);

        self::assertSame('catalog', $result['mode']);
        self::assertSame(5, $result['summary']['sheet_count']);
        foreach ($result['sheets'] as $sheet) {
            self::assertSame('catalog', $sheet['type']);
            self::assertLessThanOrEqual(SldPaperComposer::CATALOG_PAGE_SIZE, count($sheet['node_ids']));
        }
    }


    public function test_as_drawn_ignores_stale_routes_and_non_printing_geometry(): void
    {
        $graph = $this->graph(5, true, 230.0, 165.0);
        $graph['editor']['paper'] = ['mode' => 'as_drawn'];
        $graph['editor']['routes'] = [
            'wire_1' => [['x' => 100, 'y' => 80], ['x' => 300, 'y' => 80]],
            'deleted_wire' => [['x' => 9000, 'y' => -4000], ['x' => 12000, 'y' => 7000]],
        ];
        $graph['editor']['layers'] = [
            'E-BOUNDARY' => ['print' => false],
        ];
        $graph['editor']['manual_shapes'] = [[
            'shape_type' => 'rectangle', 'x' => 15000, 'y' => 15000, 'w' => 800, 'h' => 800, 'layer' => 'E-BOUNDARY',
        ]];

        $withStaleState = (new SldPaperComposer())->compose($graph);

        unset($graph['editor']['routes']['deleted_wire'], $graph['editor']['manual_shapes']);
        $cleanState = (new SldPaperComposer())->compose($graph);

        self::assertSame('as_drawn', $withStaleState['mode']);
        self::assertSame(
            $cleanState['sheets'][0]['transform'],
            $withStaleState['sheets'][0]['transform'],
            'Invisible stale routes and non-printing geometry must never shrink or shift the A3 viewport.',
        );
    }

    /** @return array<string, mixed> */
    private function graph(int $count, bool $connected, float $spacingX, float $spacingY): array
    {
        $symbols = ['pv_array', 'combiner', 'dc_breaker', 'inverter', 'battery', 'ac_protection', 'meter', 'transformer', 'grid', 'monitoring'];
        $nodes = [];
        $positions = [];
        $connections = [];

        for ($index = 0; $index < $count; $index++) {
            $id = 'node_'.$index;
            $symbol = $symbols[$index % count($symbols)];
            $nodes[] = [
                'id' => $id,
                'symbol' => $symbol,
                'kind' => $symbol,
                'title' => ucfirst(str_replace('_', ' ', $symbol)).' '.($index + 1),
                'subtitle' => 'Engineering equipment',
                'rating' => 'Rating not specified',
            ];
            $positions[$id] = [
                'x' => ($index % 8) * $spacingX,
                'y' => intdiv($index, 8) * $spacingY,
                'w' => 136,
                'h' => 126,
            ];
            if ($connected && $index > 0) {
                $connections[] = [
                    'id' => 'wire_'.$index,
                    'from' => 'node_'.($index - 1),
                    'to' => $id,
                    'network' => $index < ($count / 2) ? 'dc_power' : 'ac_power',
                ];
            }
        }

        return [
            'meta' => ['title' => 'Test SLD'],
            'nodes' => $nodes,
            'connections' => $connections,
            'editor' => [
                'positions' => $positions,
                'paper' => ['mode' => 'auto'],
            ],
        ];
    }
}
