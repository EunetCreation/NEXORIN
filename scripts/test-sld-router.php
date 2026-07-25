<?php

declare(strict_types=1);

use App\Services\Sld\SldPaperComposer;
use App\Services\Sld\SldSmartRouter;
use App\Services\Sld\SldSvgRenderer;
use App\Services\Sld\SldSymbolRegistry;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function assertRoute(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[APP67M.2] PHP FAIL · {$message}\n");
        exit(1);
    }
}

function orthogonal(array $points): bool
{
    for ($i = 0; $i < count($points) - 1; $i++) {
        $a = $points[$i]; $b = $points[$i + 1];
        if (abs($a['x'] - $b['x']) > .001 && abs($a['y'] - $b['y']) > .001) return false;
    }
    return true;
}

function intersects(array $points, array $rect, float $clearance): bool
{
    $left = $rect['left'] - $clearance; $right = $rect['right'] + $clearance;
    $top = $rect['top'] - $clearance; $bottom = $rect['bottom'] + $clearance;
    for ($i = 0; $i < count($points) - 1; $i++) {
        $a = $points[$i]; $b = $points[$i + 1];
        if (abs($a['x'] - $b['x']) < .001) {
            if ($a['x'] > $left + .001 && $a['x'] < $right - .001
                && max(min($a['y'], $b['y']), $top) < min(max($a['y'], $b['y']), $bottom) - .001) return true;
        } elseif (abs($a['y'] - $b['y']) < .001) {
            if ($a['y'] > $top + .001 && $a['y'] < $bottom - .001
                && max(min($a['x'], $b['x']), $left) < min(max($a['x'], $b['x']), $right) - .001) return true;
        } else return true;
    }
    return false;
}

$router = new SldSmartRouter();
$source = ['id' => 'source', 'x' => 20, 'y' => 60, 'side' => 'right'];
$target = ['id' => 'target', 'x' => 220, 'y' => 60, 'side' => 'left'];
$obstacle = ['id' => 'equipment-middle', 'left' => 90, 'top' => 20, 'right' => 150, 'bottom' => 100];

$routed = $router->route($source, $target, [$obstacle], [], 'ac_power', 12, 12, 10);
assertRoute($routed['version'] === SldSmartRouter::VERSION, 'router version mismatch');
assertRoute($routed['contract'] === SldSmartRouter::CONTRACT, 'router contract mismatch');
assertRoute($routed['strategy'] === SldSmartRouter::ENGINE, 'router engine mismatch');
assertRoute($routed['network_class'] === 'ac', 'network class mismatch');
assertRoute((bool) preg_match('/^rt-[0-9a-f]{8}$/', (string) $routed['signature']), 'route signature is missing');
assertRoute(($routed['orthogonal'] ?? false) === true, 'router did not report orthogonal geometry');
assertRoute(($routed['terminal_direction_ok'] ?? false) === true, 'terminal lead direction is invalid');
assertRoute(orthogonal($routed['points']), 'obstacle route is not orthogonal');
assertRoute(! intersects($routed['points'], $obstacle, 12), 'obstacle route violates clearance');
assertRoute($routed['clearance_conflicts'] === 0, 'obstacle route reports a clearance conflict');

$existing = [['a' => ['x' => 110, 'y' => 20], 'b' => ['x' => 110, 'y' => 100], 'network' => 'communication']];
$crossingAware = $router->route($source, $target, [], $existing, 'ac_power', 12, 12, 10);
assertRoute($crossingAware['crossings'] === 0, 'router did not avoid the existing wire');
assertRoute($crossingAware['length'] > 200, 'crossing-aware route did not detour');

$sharedTerminal = [['a' => ['x' => 20, 'y' => 60], 'b' => ['x' => 20, 'y' => 120], 'network' => 'protective_earth']];
$sharedTerminalRoute = $router->route($source, $target, [], $sharedTerminal, 'ac_power', 12, 12, 10);
assertRoute($sharedTerminalRoute['crossings'] === 0, 'a true shared terminal was reported as a crossing');

$again = $router->route($source, $target, [$obstacle], $existing, 'ac_power', 12, 12, 10);
$repeat = $router->route($source, $target, [$obstacle], $existing, 'ac_power', 12, 12, 10);
assertRoute($again === $repeat, 'routing output is not deterministic');
assertRoute($again['signature'] === $repeat['signature'], 'deterministic route signature changed');

$batchRequests = [
    ['id' => 'SIG-02', 'source' => ['id' => 'sig-source', 'x' => 20, 'y' => 180, 'side' => 'right'], 'target' => ['id' => 'sig-target', 'x' => 260, 'y' => 180, 'side' => 'left'], 'obstacles' => [], 'network' => 'communication', 'clearance' => 12, 'channelSpacing' => 12, 'gridSize' => 10],
    ['id' => 'AC-02', 'source' => ['id' => 'ac-source', 'x' => 20, 'y' => 120, 'side' => 'right'], 'target' => ['id' => 'ac-target', 'x' => 260, 'y' => 120, 'side' => 'left'], 'obstacles' => [], 'network' => 'ac_power', 'clearance' => 12, 'channelSpacing' => 12, 'gridSize' => 10],
    ['id' => 'DC-02', 'source' => ['id' => 'dc-source', 'x' => 20, 'y' => 60, 'side' => 'right'], 'target' => ['id' => 'dc-target', 'x' => 260, 'y' => 60, 'side' => 'left'], 'obstacles' => [], 'network' => 'dc_power', 'clearance' => 12, 'channelSpacing' => 12, 'gridSize' => 10],
];
$batchA = $router->routeBatch($batchRequests);
$batchB = $router->routeBatch(array_reverse($batchRequests));
assertRoute($batchA === $batchB, 'batch routing depends on request input order');
assertRoute(array_column($batchA['routes'], 'id') === ['DC-02', 'AC-02', 'SIG-02'], 'batch routing did not use deterministic network priority');
assertRoute($batchA['contract'] === SldSmartRouter::CONTRACT && $batchA['engine'] === SldSmartRouter::ENGINE, 'batch contract mismatch');
assertRoute(($batchA['summary']['wires'] ?? 0) === 3, 'batch wire count mismatch');
assertRoute(($batchA['summary']['orthogonal_wires'] ?? 0) === 3, 'batch contains a non-orthogonal route');
assertRoute(($batchA['summary']['terminal_direction_violations'] ?? 0) === 0, 'batch contains a terminal-direction violation');
assertRoute(SldSmartRouter::networkClass('protective-earth') === 'earth', 'network class normalization failed');
assertRoute(SldSmartRouter::networkPriority('dc_power') < SldSmartRouter::networkPriority('communication'), 'network priority contract failed');

$wall = ['id' => 'wall', 'left' => 40, 'top' => -500, 'right' => 200, 'bottom' => 500];
$fallback = $router->route($source, $target, [$wall], [], 'ac_power', 12, 12, 10);
assertRoute(orthogonal($fallback['points']), 'fallback route is not orthogonal');
assertRoute($fallback['strategy'] === 'perimeter-fallback', 'impossible corridor did not use fallback');
assertRoute($fallback['clearance_conflicts'] > 0, 'impossible corridor was silently marked clear');

$registry = $app->make(SldSymbolRegistry::class);
$node = static fn (string $id, string $symbol, string $tag): array => [
    'id' => $id,
    'symbol' => $symbol,
    'kind' => $symbol,
    'title' => $symbol,
    'tag' => $tag,
    'ports' => $registry->ports($symbol, true) ?: $registry->ports($symbol),
];
$svgGraph = [
    'schema_version' => 'nexorin-sld-v1.5.0',
    'meta' => [
        'title' => 'APP67J routing verification',
        'project_number' => 'APP67J-TEST',
        'project_name' => 'Smart routing fixture',
        'system_label' => 'PV routing test',
        'calculation_verified' => true,
        'summary' => [],
    ],
    'nodes' => [
        $node('panel-left', 'panel', 'DB-01'),
        $node('monitoring-middle', 'monitoring', 'MON-01'),
        $node('load-right', 'load', 'LOAD-01'),
    ],
    'connections' => [[
        'id' => 'ac-route-test',
        'from' => 'panel-left',
        'to' => 'load-right',
        'from_port' => 'AC_OUT',
        'to_port' => 'AC_IN',
        'network' => 'ac_power',
        'routing_mode' => 'auto',
        'routing_clearance' => 'standard',
        'router_version' => 'legacy',
    ]],
    'editor' => [
        'version' => 15,
        'excluded_nodes' => [],
        'manual_nodes' => [],
        'manual_connections' => [],
        'positions' => [
            'panel-left' => ['x' => 100, 'y' => 100, 'w' => 144, 'h' => 120],
            'monitoring-middle' => ['x' => 410, 'y' => 100, 'w' => 144, 'h' => 120],
            'load-right' => ['x' => 720, 'y' => 100, 'w' => 144, 'h' => 120],
        ],
        'routes' => [],
        'paper' => ['mode' => 'as_drawn'],
    ],
];
$composition = $app->make(SldPaperComposer::class)->compose($svgGraph);
$sheet = $composition['sheets'][0] ?? [];
$svg = $app->make(SldSvgRenderer::class)->render($svgGraph);
assertRoute(str_contains($svg, 'data-router="'.SldSmartRouter::VERSION.'"'), 'SVG did not expose the smart-router version');
assertRoute(str_contains($svg, 'data-route-contract="'.SldSmartRouter::CONTRACT.'"'), 'SVG did not expose the APP67M.2 routing contract');
assertRoute(str_contains($svg, 'data-route-strategy="'.SldSmartRouter::ENGINE.'"'), 'SVG did not use deterministic orthogonal routing');
assertRoute(str_contains($svg, 'data-route-orthogonal="true"'), 'SVG route was not marked orthogonal');
assertRoute(str_contains($svg, 'data-route-terminal-direction-ok="true"'), 'SVG route terminal direction is invalid');
assertRoute((bool) preg_match('/data-route-signature="rt-[0-9a-f]{8}"/', $svg), 'SVG did not expose a stable route signature');
assertRoute((bool) preg_match('/<g class="sld-wire"[^>]*><path d="([^"]+)"/', $svg, $pathMatch), 'SVG wire path was not rendered');
preg_match_all('/[ML](-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/', $pathMatch[1], $pointMatches, PREG_SET_ORDER);
$svgPoints = array_map(static fn (array $match): array => ['x' => (float) $match[1], 'y' => (float) $match[2]], $pointMatches);
assertRoute(count($svgPoints) >= 4, 'SVG smart route did not contain an obstacle detour');
assertRoute(orthogonal($svgPoints), 'SVG smart route is not orthogonal');
$middle = $sheet['positions']['monitoring-middle'] ?? null;
assertRoute(is_array($middle), 'SVG paper composition omitted the obstacle node');
$middleRect = [
    'left' => (float) $middle['x'] - ((float) $middle['w'] / 2),
    'top' => (float) $middle['y'] - ((float) $middle['h'] / 2),
    'right' => (float) $middle['x'] + ((float) $middle['w'] / 2),
    'bottom' => (float) $middle['y'] + ((float) $middle['h'] / 2),
];
$paperClearance = max(8.0, 18.0 * (float) data_get($sheet, 'transform.scale', 1.0));
assertRoute(! intersects($svgPoints, $middleRect, $paperClearance), 'SVG smart route violates obstacle clearance');
assertRoute(str_contains($svg, 'data-route-clearance-conflicts="0"'), 'SVG route reported a clearance conflict');

$stressSeed = 0x670A0B;
$random = static function () use (&$stressSeed): float {
    $stressSeed = (int) ((1664525 * $stressSeed + 1013904223) & 0xFFFFFFFF);
    return $stressSeed / 4294967296;
};
for ($caseIndex = 0; $caseIndex < 40; $caseIndex++) {
    $stressObstacles = [];
    $obstacleCount = 1 + (int) floor($random() * 4);
    for ($obstacleIndex = 0; $obstacleIndex < $obstacleCount; $obstacleIndex++) {
        $left = 110 + (int) floor($random() * 360);
        $top = 20 + (int) floor($random() * 240);
        $width = 40 + (int) floor($random() * 70);
        $height = 40 + (int) floor($random() * 90);
        $stressObstacles[] = ['id' => "stress-{$caseIndex}-{$obstacleIndex}", 'left' => $left, 'top' => $top, 'right' => $left + $width, 'bottom' => $top + $height];
    }
    $clearances = [12.0, 18.0, 26.0];
    $spacings = [9.0, 12.0, 16.0];
    $networks = ['dc_power', 'ac_power', 'protective_earth', 'communication'];
    $stressSource = ['id' => "source-{$caseIndex}", 'x' => 20, 'y' => 40 + (int) floor($random() * 220), 'side' => 'right'];
    $stressTarget = ['id' => "target-{$caseIndex}", 'x' => 620, 'y' => 40 + (int) floor($random() * 220), 'side' => 'left'];
    $stressRoute = $router->route($stressSource, $stressTarget, $stressObstacles, [], $networks[$caseIndex % 4], $clearances[$caseIndex % 3], $spacings[$caseIndex % 3], 10);
    $stressRepeat = $router->route($stressSource, $stressTarget, $stressObstacles, [], $networks[$caseIndex % 4], $clearances[$caseIndex % 3], $spacings[$caseIndex % 3], 10);
    assertRoute($stressRoute === $stressRepeat, "stress case {$caseIndex} is not deterministic");
    assertRoute(orthogonal($stressRoute['points']), "stress case {$caseIndex} contains a diagonal segment");
    foreach ($stressRoute['points'] as $point) assertRoute(is_finite((float) $point['x']) && is_finite((float) $point['y']), "stress case {$caseIndex} contains a non-finite point");
    if ($stressRoute['clearance_conflicts'] === 0) {
        foreach ($stressObstacles as $stressObstacle) assertRoute(! intersects($stressRoute['points'], $stressObstacle, $clearances[$caseIndex % 3]), "stress case {$caseIndex} silently violates clearance");
    }
}

fwrite(STDOUT, '[APP67M.2] PHP PASS · '.SldSmartRouter::VERSION." · deterministic orthogonal channel routing, terminal leads, network-priority batches, SVG metadata parity, fallback, and 40 stress cases\n");
