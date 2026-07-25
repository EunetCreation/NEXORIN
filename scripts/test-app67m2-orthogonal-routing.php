<?php

declare(strict_types=1);

use App\Http\Controllers\ProjectSldController;
use App\Services\Sld\SldEngineeringController;
use App\Services\Sld\SldPaperComposer;
use App\Services\Sld\SldSmartRouter;
use App\Services\Sld\SldSvgRenderer;
use App\Services\Sld\SldSymbolRegistry;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$passed = 0;
$check = static function (string $name, callable $callback) use (&$passed): void {
    $callback();
    $passed++;
    fwrite(STDOUT, sprintf("[APP67M.2] PASS %d/6 · %s\n", $passed, $name));
};
$assert = static function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};

$router = $app->make(SldSmartRouter::class);
$source = ['id' => 'source', 'x' => 20, 'y' => 60, 'side' => 'right'];
$target = ['id' => 'target', 'x' => 260, 'y' => 60, 'side' => 'left'];

$check('published PHP routing contract', static function () use ($assert): void {
    $assert(SldSmartRouter::VERSION === '67M.2', 'version mismatch');
    $assert(SldSmartRouter::CONTRACT === 'deterministic-orthogonal-channel-v2', 'contract mismatch');
    $assert(SldSmartRouter::ENGINE === 'orthogonal-channel-a-star', 'engine mismatch');
    $assert(SldSmartRouter::networkClass('protective-earth') === 'earth', 'network class normalization failed');
    $assert(SldSmartRouter::networkPriority('dc_power') < SldSmartRouter::networkPriority('communication'), 'network priority failed');
});

$check('route evidence is orthogonal and terminal-safe', static function () use ($assert, $router, $source, $target): void {
    $route = $router->route($source, $target, [['id' => 'middle', 'left' => 100, 'top' => 20, 'right' => 160, 'bottom' => 100]], [], 'ac_power', 12, 12, 10);
    $assert(($route['orthogonal'] ?? false) === true, 'route is not orthogonal');
    $assert(($route['terminal_direction_ok'] ?? false) === true, 'terminal direction is invalid');
    $assert(($route['clearance_conflicts'] ?? 1) === 0, 'route violates clearance');
    $assert((bool) preg_match('/^rt-[0-9a-f]{8}$/', (string) ($route['signature'] ?? '')), 'signature missing');
});

$check('batch order is deterministic', static function () use ($assert, $router): void {
    $requests = [
        ['id' => 'SIG', 'source' => ['id' => 's3', 'x' => 20, 'y' => 180, 'side' => 'right'], 'target' => ['id' => 't3', 'x' => 260, 'y' => 180, 'side' => 'left'], 'network' => 'communication'],
        ['id' => 'AC', 'source' => ['id' => 's2', 'x' => 20, 'y' => 120, 'side' => 'right'], 'target' => ['id' => 't2', 'x' => 260, 'y' => 120, 'side' => 'left'], 'network' => 'ac_power'],
        ['id' => 'DC', 'source' => ['id' => 's1', 'x' => 20, 'y' => 60, 'side' => 'right'], 'target' => ['id' => 't1', 'x' => 260, 'y' => 60, 'side' => 'left'], 'network' => 'dc_power'],
    ];
    $forward = $router->routeBatch($requests);
    $reverse = $router->routeBatch(array_reverse($requests));
    $assert($forward === $reverse, 'batch output depends on input order');
    $assert(array_column($forward['routes'], 'id') === ['DC', 'AC', 'SIG'], 'priority order mismatch');
    $assert(($forward['summary']['orthogonal_wires'] ?? 0) === 3, 'batch contains non-orthogonal route');
    $assert(($forward['summary']['terminal_direction_violations'] ?? 1) === 0, 'batch terminal violation');
});

$check('controller normalizes persisted routing evidence', static function () use ($assert, $app): void {
    $controller = $app->make(ProjectSldController::class);
    $method = new ReflectionMethod($controller, 'normalizeEditorState');
    $state = $method->invoke($controller, [
        'version' => 16,
        'routing' => ['wires' => 1, 'orthogonal_wires' => 1, 'length' => 240],
        'manual_connections' => [[
            'id' => 'WIRE-01', 'from' => 'source', 'to' => 'target', 'network' => 'ac_power',
            'route_signature' => 'rt-1234abcd', 'route_orthogonal' => true, 'route_terminal_direction_ok' => true,
        ]],
    ]);
    $assert(data_get($state, 'routing.version') === SldSmartRouter::VERSION, 'normalized version mismatch');
    $assert(data_get($state, 'routing.contract') === SldSmartRouter::CONTRACT, 'normalized contract mismatch');
    $assert(data_get($state, 'routing.engine') === SldSmartRouter::ENGINE, 'normalized engine mismatch');
    $wire = data_get($state, 'manual_connections.0', []);
    $assert(($wire['route_signature'] ?? '') === 'rt-1234abcd', 'wire signature was not retained');
    $assert(($wire['route_orthogonal'] ?? false) === true, 'wire orthogonal evidence was not retained');
    $assert(($wire['route_terminal_direction_ok'] ?? false) === true, 'wire terminal evidence was not retained');
});

$check('engineering synchronization upgrades routing metadata', static function () use ($assert, $app): void {
    $service = $app->make(SldEngineeringController::class);
    $method = new ReflectionMethod($service, 'preserveEditor');
    $editor = ['version' => 16, 'routing' => ['version' => 'legacy', 'engine' => 'legacy'], 'positions' => ['panel' => ['x' => 100, 'y' => 100, 'w' => 144, 'h' => 120]]];
    $fresh = ['nodes' => [['id' => 'panel']], 'editor' => ['positions' => ['panel' => ['x' => 120, 'y' => 120, 'w' => 144, 'h' => 120]]]];
    $merged = $method->invoke($service, $editor, $fresh);
    $assert(data_get($merged, 'routing.version') === SldSmartRouter::VERSION, 'sync version was not upgraded');
    $assert(data_get($merged, 'routing.contract') === SldSmartRouter::CONTRACT, 'sync contract was not upgraded');
    $assert(data_get($merged, 'routing.engine') === SldSmartRouter::ENGINE, 'sync engine was not upgraded');
});

$check('SVG export publishes integrity attributes', static function () use ($assert, $app): void {
    $registry = $app->make(SldSymbolRegistry::class);
    $node = static fn (string $id, string $symbol): array => [
        'id' => $id, 'symbol' => $symbol, 'kind' => $symbol, 'title' => $symbol, 'tag' => strtoupper($id),
        'ports' => $registry->ports($symbol, true) ?: $registry->ports($symbol),
    ];
    $graph = [
        'schema_version' => 'nexorin-sld-v1.5.0',
        'meta' => ['title' => 'APP67M.2', 'project_number' => 'M2-TEST', 'project_name' => 'Router', 'system_label' => 'PV', 'calculation_verified' => true, 'summary' => []],
        'nodes' => [$node('panel-left', 'panel'), $node('monitoring-middle', 'monitoring'), $node('load-right', 'load')],
        'connections' => [[
            'id' => 'ac-test', 'from' => 'panel-left', 'to' => 'load-right', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN',
            'network' => 'ac_power', 'routing_mode' => 'auto', 'router_version' => 'legacy',
        ]],
        'editor' => [
            'version' => 16, 'excluded_nodes' => [], 'manual_nodes' => [], 'manual_connections' => [], 'routes' => [], 'paper' => ['mode' => 'as_drawn'],
            'positions' => [
                'panel-left' => ['x' => 100, 'y' => 100, 'w' => 144, 'h' => 120],
                'monitoring-middle' => ['x' => 410, 'y' => 100, 'w' => 144, 'h' => 120],
                'load-right' => ['x' => 720, 'y' => 100, 'w' => 144, 'h' => 120],
            ],
        ],
    ];
    $app->make(SldPaperComposer::class)->compose($graph);
    $svg = $app->make(SldSvgRenderer::class)->render($graph);
    $assert(str_contains($svg, 'data-route-contract="'.SldSmartRouter::CONTRACT.'"'), 'SVG contract missing');
    $assert(str_contains($svg, 'data-route-strategy="'.SldSmartRouter::ENGINE.'"'), 'SVG strategy missing');
    $assert(str_contains($svg, 'data-route-orthogonal="true"'), 'SVG orthogonal evidence missing');
    $assert(str_contains($svg, 'data-route-terminal-direction-ok="true"'), 'SVG terminal evidence missing');
    $assert((bool) preg_match('/data-route-signature="rt-[0-9a-f]{8}"/', $svg), 'SVG signature missing');
});

fwrite(STDOUT, sprintf("[APP67M.2] Orthogonal Routing PHP contract · %d/6 passed\n", $passed));
