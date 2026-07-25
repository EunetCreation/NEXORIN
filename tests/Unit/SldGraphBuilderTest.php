<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\Project;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Services\Sld\SldGraphBuilder;
use App\Services\Sld\SldPdfRenderer;
use App\Services\Sld\SldSvgRenderer;
use Tests\TestCase;

class SldGraphBuilderTest extends TestCase
{
    public function test_on_grid_graph_is_built_from_the_calculation_snapshot(): void
    {
        $project = $this->projectWithCalculation('on_grid');
        $graph = app(SldGraphBuilder::class)->build($project);

        $this->assertSame(
            SldGraphBuilder::SCHEMA_VERSION,
            $graph['schema_version']
        );

        $this->assertSame(
            'on_grid',
            $graph['meta']['system_type']
        );

        $this->assertSame(
            [
                'pv_array',
                'dc_protection',
                'inverter',
                'ac_protection',
                'meter',
                'grid',
            ],
            array_column($graph['nodes'], 'id')
        );

        $this->assertSame(
            'pv_array',
            $graph['connections'][0]['from']
        );

        $this->assertSame(
            'grid',
            $graph['connections'][4]['to']
        );

        $this->assertSame([], $graph['warnings']);
    }

    public function test_on_grid_inverter_uses_physical_ratings_and_excludes_battery_ports(): void
    {
        $project = $this->projectWithCalculation('on_grid');
        $project->technicalCalculation->inverter_ac_power_kw = 25;
        $project->technicalCalculation->inverter_max_dc_voltage_v = 1100;
        $project->technicalCalculation->inverter_mppt_count = 4;
        $project->technicalCalculation->results = array_merge(
            $project->technicalCalculation->results,
            [
                'inverter_count' => 2,
                'total_inverter_ac_kw' => 50,
                'inverter_unit_ac_kw' => 25,
                'inverter_unit_rated_current_a' => 36.821,
                'ac_nominal_voltage_v' => 400,
                'string_module_counts' => [16, 16, 16, 16, 16, 16],
                'inverter_string_assignments' => [
                    ['tag' => 'INV-01', 'assigned_module_count' => 48, 'used_mppt_count' => 3, 'strings' => []],
                    ['tag' => 'INV-02', 'assigned_module_count' => 48, 'used_mppt_count' => 3, 'strings' => []],
                ],
            ]
        );

        $graph = app(SldGraphBuilder::class)->build($project);
        $inverter = collect($graph['nodes'])->firstWhere('id', 'inverter');

        $this->assertNotNull($inverter);
        $this->assertSame(2, $inverter['equipment_count']);
        $this->assertSame(25.0, $inverter['rated_power']);
        $this->assertSame(50.0, $inverter['aggregate_power']);
        $this->assertSame(400.0, $inverter['rated_voltage']);
        $this->assertEqualsWithDelta(36.821, $inverter['rated_current'], 0.001);
        $this->assertSame(4, $inverter['mppt_count']);
        $this->assertCount(2, $inverter['string_assignments']);
        $this->assertSame(['DC_IN', 'AC_OUT', 'PE', 'COM'], array_column($inverter['ports'], 'id'));
        $this->assertNotContains('BAT_DC', array_column($inverter['ports'], 'id'));
        $this->assertNotContains('AC_BACKUP', array_column($inverter['ports'], 'id'));
    }

    public function test_hybrid_graph_adds_battery_and_backup_load_path(): void
    {
        $project = $this->projectWithCalculation('hybrid');
        $graph = app(SldGraphBuilder::class)->build($project);

        $nodeIds = array_column($graph['nodes'], 'id');

        $connectionPairs = collect($graph['connections'])
            ->map(
                fn (array $connection): string =>
                    $connection['from'].'>'.$connection['to']
            )
            ->all();

        $this->assertContains('battery', $nodeIds);
        $this->assertContains('backup_panel', $nodeIds);
        $this->assertContains('battery>inverter', $connectionPairs);
        $this->assertContains('inverter>backup_panel', $connectionPairs);
    }

    public function test_svg_renderer_outputs_a_self_contained_drawable_document(): void
    {
        $project = $this->projectWithCalculation('on_grid');

        $graph = app(SldGraphBuilder::class)->build($project);

        $svg = app(SldSvgRenderer::class)->render($graph);

        $this->assertStringStartsWith('<svg ', $svg);
        $this->assertStringContainsString('PV array', $svg);
        $this->assertStringContainsString('Utility grid', $svg);
        $this->assertStringContainsString('sld-arrow', $svg);
    }

    public function test_svg_renderer_honors_maxgraph_routes_and_annotations(): void
    {
        $project = $this->projectWithCalculation('on_grid');

        $graph = app(SldGraphBuilder::class)->build($project);

        /*
         * Route ID harus mengikuti format yang digunakan oleh
         * SldSvgRenderer::connectionId():
         *
         * source_{from}__{to}__{connection_index}
         */
        $routeId = sprintf(
            'source_%s__%s__0',
            $graph['connections'][0]['from'],
            $graph['connections'][0]['to']
        );

        $graph['editor'] = [
            'positions' => [
                'pv_array' => [
                    'x' => 70,
                    'y' => 250,
                    'w' => 188,
                    'h' => 104,
                ],
                'dc_protection' => [
                    'x' => 360,
                    'y' => 250,
                    'w' => 188,
                    'h' => 104,
                ],
            ],

            'routes' => [
                $routeId => [
                    [
                        'x' => 280,
                        'y' => 302,
                    ],
                    [
                        'x' => 320,
                        'y' => 302,
                    ],
                ],
            ],

            'annotations' => [
                [
                    'id' => 'note_1',
                    'text' => 'Verify utility protection settings.',
                    'x' => 90,
                    'y' => 560,
                    'w' => 300,
                    'h' => 58,
                ],
            ],
        ];

        $svg = app(SldSvgRenderer::class)->render($graph);

        /*
         * APP67B mengubah koordinat Model Space menjadi koordinat
         * Paper Space.
         *
         * Karena itu, test tidak boleh lagi mencari string koordinat
         * mentah seperti:
         *
         * L280 302L320 302
         *
         * Test harus memastikan route hasil render tetap mempunyai:
         *
         * 1. Titik awal.
         * 2. Dua stored route points.
         * 3. Titik akhir.
         */
        $matched = preg_match(
            '/<path d="([^"]+)" class="sld-connection dc-power"[^>]*\/>/',
            $svg,
            $pathMatch
        );

        $this->assertSame(
            1,
            $matched,
            'The DC source connection path was not rendered.'
        );

        $routePath = $pathMatch[1];

        /*
         * Route tersimpan dirender menggunakan command M dan L.
         *
         * Expected:
         *
         * M start
         * L stored point 1
         * L stored point 2
         * L end
         */
        $pointCount = preg_match_all(
            '/[ML](-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/',
            $routePath,
            $pathPoints,
            PREG_SET_ORDER
        );

        $this->assertSame(
            4,
            $pointCount,
            'The rendered path must contain the start point, '
                .'two saved route points, and the end point.'
        );

        $firstStoredRoutePoint = [
            'x' => (float) $pathPoints[1][1],
            'y' => (float) $pathPoints[1][2],
        ];

        $secondStoredRoutePoint = [
            'x' => (float) $pathPoints[2][1],
            'y' => (float) $pathPoints[2][2],
        ];

        /*
         * Dua stored route points pada test memiliki nilai Y yang sama.
         * Setelah transformasi Paper Space, segmen tersebut harus tetap
         * horizontal.
         */
        $this->assertEqualsWithDelta(
            $firstStoredRoutePoint['y'],
            $secondStoredRoutePoint['y'],
            0.01,
            'A horizontal maxGraph route segment must remain horizontal '
                .'after the Paper Space transform.'
        );

        /*
         * Urutan route juga harus tetap bergerak dari kiri ke kanan.
         */
        $this->assertGreaterThan(
            $firstStoredRoutePoint['x'],
            $secondStoredRoutePoint['x'],
            'The saved route points must retain their left-to-right order '
                .'after the Paper Space transform.'
        );

        /*
         * Annotation harus tetap muncul dalam hasil SVG.
         */
        $this->assertStringContainsString(
            'DRAWING NOTE',
            $svg
        );

        $this->assertStringContainsString(
            'Verify utility protection settings.',
            $svg
        );
    }

    public function test_uneven_legacy_string_layout_never_invents_a_module(): void
    {
        $project = $this->projectWithCalculation('on_grid');

        $project->technicalCalculation->results = array_merge(
            $project->technicalCalculation->results,
            [
                'module_count' => 13,
                'string_count' => 2,
                'modules_per_string' => 7,
            ]
        );

        $graph = app(SldGraphBuilder::class)->build($project);

        $this->assertSame(
            '7 + 6 modules/string',
            $graph['meta']['summary']['string_layout']
        );

        $this->assertStringContainsString(
            '7 + 6 modules/string',
            $graph['connections'][0]['detail']
        );
    }

    public function test_vector_pdf_fallback_is_a_valid_single_page_pdf(): void
    {
        $project = $this->projectWithCalculation('on_grid');

        $graph = app(SldGraphBuilder::class)->build($project);

        $revision = new \App\Models\ProjectSldRevision([
            'revision' => 1,
            'status' => 'draft',
            'graph' => $graph,
        ]);

        $pdf = app(SldPdfRenderer::class)->render(
            $project,
            $revision
        );

        $this->assertStringStartsWith('%PDF-1.4', $pdf);

        $this->assertStringContainsString(
            'SINGLE-LINE DIAGRAM',
            $pdf
        );

        $this->assertStringContainsString(
            '%%EOF',
            $pdf
        );

        $this->assertGreaterThan(
            1500,
            strlen($pdf)
        );
    }

    private function projectWithCalculation(string $systemType): Project
    {
        $project = new Project([
            'project_number' => 'NXR-TEST-001',
            'name' => 'SLD Test Project',
            'proposed_system' => $systemType,
        ]);

        $calculation = new TechnicalCalculation([
            'system_type' => $systemType,
            'result_status' => 'verified',

            'module_power_wp' => 550,
            'module_voc_v' => 49.9,
            'module_vmp_v' => 41.8,
            'module_isc_a' => 13.9,
            'module_imp_a' => 13.16,

            'inverter_ac_power_kw' => 5,
            'inverter_max_dc_voltage_v' => 600,
            'inverter_mppt_count' => 2,

            'results' => [
                'module_count' => 10,
                'string_count' => 2,
                'modules_per_string' => 5,
                'installed_pv_kw' => 5.5,

                'inverter_count' => 1,
                'total_inverter_ac_kw' => 5,

                'string_voc_cold_v' => 260,
                'string_vmp_hot_v' => 210,

                'battery_nominal_kwh' =>
                    $systemType === 'hybrid' ? 10 : 0,

                'battery_minimum_power_kw' =>
                    $systemType === 'hybrid' ? 4 : 0,

                'protection' => [
                    'dc_isolator_a' => 32,
                    'dc_spd' => 'Type 2 DC SPD 1000 Vdc',
                    'ac_breaker_a' => 25,
                    'ac_spd' => 'Type 2 AC SPD 1P+N',
                ],
            ],

            'warnings' => [],
            'calculated_at' => now(),
        ]);

        $recommendation = new SystemRecommendation([
            'recommended_system' => $systemType,
        ]);

        $project->setRelation(
            'technicalCalculation',
            $calculation
        );

        $project->setRelation(
            'systemRecommendation',
            $recommendation
        );

        $project->setRelation(
            'billOfMaterial',
            new BillOfMaterial()
        );

        $project->setRelation('loadAnalysis', null);
        $project->setRelation('solarProfile', null);
        $project->setRelation('roofSiteSurvey', null);

        return $project;
    }
}