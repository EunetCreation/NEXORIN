<?php

declare(strict_types=1);

use App\Http\Controllers\ProjectSldController;
use App\Services\Sld\SldEngineeringController;
use App\Services\Sld\SldLabelPlacement;
use App\Services\Sld\SldPaperComposer;
use App\Services\Sld\SldSvgRenderer;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$passed = 0;
$check = static function (string $name, callable $callback) use (&$passed): void {
    $callback();
    $passed++;
    fwrite(STDOUT, sprintf("[APP67M.3] PASS %d/6 · %s\n", $passed, $name));
};
$assert = static function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};

$service = $app->make(SldLabelPlacement::class);
$items = [
    ['id' => 'A', 'x' => 80, 'y' => 80, 'w' => 120, 'h' => 80, 'tag' => 'PV-01', 'title' => 'PV array', 'rating' => '12.5 kWp', 'ports' => [['side' => 'right']]],
    ['id' => 'B', 'x' => 360, 'y' => 80, 'w' => 120, 'h' => 80, 'tag' => 'INV-01', 'title' => 'Inverter', 'rating' => '10 kW', 'ports' => [['side' => 'left'], ['side' => 'right']]],
    ['id' => 'C', 'x' => 640, 'y' => 80, 'w' => 120, 'h' => 80, 'tag' => 'MDB-01', 'title' => 'Distribution board', 'rating' => '400 V', 'ports' => [['side' => 'left']]],
];
$segments = [
    ['a' => ['x' => 200, 'y' => 120], 'b' => ['x' => 360, 'y' => 120]],
    ['a' => ['x' => 480, 'y' => 120], 'b' => ['x' => 640, 'y' => 120]],
];

$check('published PHP label placement contract', static function () use ($assert): void {
    $assert(SldLabelPlacement::VERSION === '67M.3', 'version mismatch');
    $assert(SldLabelPlacement::CONTRACT === 'deterministic-clearance-label-v1', 'contract mismatch');
    $assert(SldLabelPlacement::ENGINE === 'candidate-score-label-placer', 'engine mismatch');
});

$check('placement produces stable signatures and summaries', static function () use ($assert, $service, $items, $segments): void {
    $result = $service->place($items, $segments, ['canvas' => ['x' => 0, 'y' => 0, 'w' => 900, 'h' => 500]]);
    $assert(($result['summary']['labels'] ?? 0) === 3, 'label count mismatch');
    $assert(count($result['placements'] ?? []) === 3, 'placement count mismatch');
    $signatures = [];
    foreach ($result['placements'] as $placement) {
        $assert((bool) preg_match('/^lp-[0-9a-f]{8}$/', (string) ($placement['signature'] ?? '')), 'signature missing');
        $signatures[(string) $placement['id']] = (string) $placement['signature'];
    }
    $assert($signatures === ['A' => 'lp-3c4eac4f', 'B' => 'lp-c0978401', 'C' => 'lp-ac961b44'], 'shared APP67M.3 label signatures changed');
    $rotated = $service->place([['id' => 'R', 'x' => 200, 'y' => 200, 'w' => 120, 'h' => 80, 'title' => 'Rotated', 'rotation' => 90, 'ports' => [['side' => 'right']]]]);
    $assert(data_get($rotated, 'placements.0.side') === 'right', 'transformed port side was not respected');
});

$check('placement output is deterministic', static function () use ($assert, $service, $items, $segments): void {
    $forward = $service->place($items, $segments, ['canvas' => ['x' => 0, 'y' => 0, 'w' => 900, 'h' => 500]]);
    $reverse = $service->place(array_reverse($items), array_reverse($segments), ['canvas' => ['x' => 0, 'y' => 0, 'w' => 900, 'h' => 500]]);
    $assert($forward === $reverse, 'output depends on input order');
});

$check('controller normalizes persisted label metadata', static function () use ($assert, $app): void {
    $controller = $app->make(ProjectSldController::class);
    $method = new ReflectionMethod($controller, 'normalizeEditorState');
    $state = $method->invoke($controller, [
        'version' => 16,
        'label_placement' => [
            'positions' => ['panel' => ['side' => 'right', 'x' => 260, 'y' => 100, 'w' => 120, 'h' => 48, 'leader' => true, 'signature' => 'lp-1234abcd']],
            'summary' => ['labels' => 1, 'leaders' => 1],
        ],
    ]);
    $assert(data_get($state, 'label_placement.version') === SldLabelPlacement::VERSION, 'normalized version mismatch');
    $assert(data_get($state, 'label_placement.contract') === SldLabelPlacement::CONTRACT, 'normalized contract mismatch');
    $assert(data_get($state, 'label_placement.positions.panel.signature') === 'lp-1234abcd', 'signature was not retained');
});

$check('engineering synchronization upgrades label metadata', static function () use ($assert, $app): void {
    $service = $app->make(SldEngineeringController::class);
    $method = new ReflectionMethod($service, 'preserveEditor');
    $editor = ['version' => 16, 'label_placement' => ['version' => 'legacy'], 'positions' => ['panel' => ['x' => 100, 'y' => 100, 'w' => 144, 'h' => 120]]];
    $fresh = ['nodes' => [['id' => 'panel']], 'editor' => ['positions' => ['panel' => ['x' => 120, 'y' => 120, 'w' => 144, 'h' => 120]]]];
    $merged = $method->invoke($service, $editor, $fresh);
    $assert(data_get($merged, 'label_placement.version') === SldLabelPlacement::VERSION, 'sync version was not upgraded');
    $assert(data_get($merged, 'label_placement.contract') === SldLabelPlacement::CONTRACT, 'sync contract was not upgraded');
    $assert(data_get($merged, 'label_placement.engine') === SldLabelPlacement::ENGINE, 'sync engine was not upgraded');
});

$check('SVG export publishes automatic label evidence', static function () use ($assert, $app): void {
    $graph = [
        'schema_version' => 'nexorin-sld-v1.5.0',
        'meta' => ['title' => 'APP67M.3', 'project_number' => 'M3-TEST', 'project_name' => 'Labels', 'system_label' => 'PV', 'calculation_verified' => true, 'summary' => []],
        'nodes' => [
            ['id' => 'panel', 'symbol' => 'panel', 'kind' => 'panel', 'title' => 'Distribution panel', 'tag' => 'MDB-01', 'rating' => '400 V', 'ports' => []],
            ['id' => 'load', 'symbol' => 'load', 'kind' => 'load', 'title' => 'Load panel', 'tag' => 'LP-01', 'rating' => '100 A', 'ports' => []],
        ],
        'connections' => [],
        'editor' => [
            'version' => 16, 'excluded_nodes' => [], 'manual_nodes' => [], 'manual_connections' => [], 'routes' => [], 'paper' => ['mode' => 'as_drawn'],
            'positions' => [
                'panel' => ['x' => 160, 'y' => 160, 'w' => 144, 'h' => 120],
                'load' => ['x' => 560, 'y' => 160, 'w' => 144, 'h' => 120],
            ],
        ],
    ];
    $app->make(SldPaperComposer::class)->compose($graph);
    $svg = $app->make(SldSvgRenderer::class)->render($graph);
    $assert(str_contains($svg, 'data-label-placement-version="'.SldLabelPlacement::VERSION.'"'), 'SVG version missing');
    $assert(str_contains($svg, 'data-label-placement-contract="'.SldLabelPlacement::CONTRACT.'"'), 'SVG contract missing');
    $assert((bool) preg_match('/data-label-placement-signature="lp-[0-9a-f]{8}"/', $svg), 'SVG signature missing');
    $assert(str_contains($svg, 'sld-auto-label-box'), 'SVG automatic label box missing');
});

fwrite(STDOUT, sprintf("[APP67M.3] Automatic Label Placement PHP contract · %d/6 passed\n", $passed));
