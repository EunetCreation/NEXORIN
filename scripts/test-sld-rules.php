<?php

declare(strict_types=1);

use App\Services\Sld\SldElectricalRuleChecker;
use App\Services\Sld\SldSvgRenderer;
use App\Services\Sld\SldSymbolRegistry;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$registry = $app->make(SldSymbolRegistry::class);
$checker = $app->make(SldElectricalRuleChecker::class);
$sourceNode = static function (string $id, string $symbol, string $tag) use ($registry): array {
    $ports = $registry->ports($symbol, true);
    return ['id' => $id, 'symbol' => $symbol, 'title' => $symbol, 'tag' => $tag, 'ports' => $ports !== [] ? $ports : $registry->ports($symbol)];
};
$manualNode = static fn (string $id, string $symbol, string $tag): array => [
    'id' => $id, 'symbol' => $symbol, 'kind' => $symbol, 'title' => $symbol, 'tag' => $tag, 'ports' => $registry->ports($symbol),
];
$baseline = static function () use ($sourceNode): array {
    return [
        'schema_version' => 'nexorin-sld-v1.4.0',
        'nodes' => [
            $sourceNode('pv_array', 'pv_array', 'PVA-01'),
            $sourceNode('dc_protection', 'dc_protection', 'QSDC-01'),
            $sourceNode('inverter', 'inverter', 'INV-01'),
            $sourceNode('ac_protection', 'ac_protection', 'QFAC-01'),
            $sourceNode('meter', 'meter', 'MTR-01'),
            $sourceNode('grid', 'grid', 'GRID-01'),
        ],
        'connections' => [
            ['id' => 'c1', 'from' => 'pv_array', 'to' => 'dc_protection', 'from_port' => 'DC_OUT', 'to_port' => 'DC_IN', 'network' => 'dc_power'],
            ['id' => 'c2', 'from' => 'dc_protection', 'to' => 'inverter', 'from_port' => 'DC_OUT', 'to_port' => 'DC_IN', 'network' => 'dc_power'],
            ['id' => 'c3', 'from' => 'inverter', 'to' => 'ac_protection', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN', 'network' => 'ac_power'],
            ['id' => 'c4', 'from' => 'ac_protection', 'to' => 'meter', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN', 'network' => 'ac_power'],
            ['id' => 'c5', 'from' => 'meter', 'to' => 'grid', 'from_port' => 'AC_OUT', 'to_port' => 'AC_PCC', 'network' => 'ac_power'],
        ],
        'editor' => ['excluded_nodes' => [], 'manual_nodes' => [], 'manual_connections' => []],
    ];
};
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, "[APP67I] PHP FAIL · {$message}\n");
        exit(1);
    }
};
$issueCodes = static fn (array $validation): array => array_column($validation['issues'] ?? [], 'code');

$valid = $checker->check($baseline());
$assert($valid['blocking'] === false, 'Baseline topology must not be blocking.');
$assert((int) $valid['summary']['error'] === 0, 'Baseline topology must contain zero errors.');
$assert((int) $valid['summary']['warning'] === 4, 'Baseline topology must expose four grounding warnings.');

$duplicate = $baseline();
$duplicate['editor']['manual_nodes'][] = $manualNode('monitoring-1', 'monitoring', 'INV-01');
$duplicateResult = $checker->check($duplicate);
$assert($duplicateResult['blocking'] === true, 'Duplicate tags must be blocking.');
$assert(count(array_filter($duplicateResult['issues'], static fn (array $issue): bool => $issue['code'] === 'E007')) === 2, 'Duplicate tag must identify both objects.');

$incompatible = $baseline();
$incompatible['editor']['manual_connections'][] = ['id' => 'bad', 'from' => 'pv_array', 'to' => 'grid', 'from_port' => 'DC_OUT', 'to_port' => 'AC_PCC', 'network' => 'dc_power'];
$assert(in_array('E003', $issueCodes($checker->check($incompatible)), true), 'DC-to-AC connection must produce E003.');

$cable = $baseline();
$cable['editor']['manual_nodes'] = [$manualNode('panel-1', 'panel', 'DB-01'), $manualNode('load-1', 'load', 'LOAD-01')];
$cable['editor']['manual_connections'][] = ['id' => 'manual-power', 'from' => 'panel-1', 'to' => 'load-1', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN', 'network' => 'ac_power'];
$cableCodes = $issueCodes($checker->check($cable));
$assert(in_array('W002', $cableCodes, true), 'Incomplete manual power cable must produce W002.');
$assert(in_array('N001', $cableCodes, true), 'Missing voltage class must produce N001.');

$unratedProtection = $baseline();
$unratedProtection['editor']['manual_nodes'][] = $manualNode('dc-breaker-1', 'dc_breaker', 'QFDC-01');
$assert(in_array('W006', $issueCodes($checker->check($unratedProtection)), true), 'Manual protection without manufacturer-backed ratings must produce W006.');

$acBypass = [
    'schema_version' => 'nexorin-sld-v1.4.0',
    'nodes' => [
        $sourceNode('inverter-only', 'inverter', 'INV-02'),
        $sourceNode('grid-only', 'grid', 'GRID-02'),
    ],
    'connections' => [
        ['id' => 'direct-ac', 'from' => 'inverter-only', 'to' => 'grid-only', 'from_port' => 'AC_OUT', 'to_port' => 'AC_PCC', 'network' => 'ac_power'],
    ],
    'editor' => ['excluded_nodes' => [], 'manual_nodes' => [], 'manual_connections' => []],
];
$assert(in_array('E012', $issueCodes($checker->check($acBypass)), true), 'Direct inverter-to-utility connection must produce E012.');

$renderGraph = $baseline();
$renderGraph['electrical_validation'] = $valid;
$svg = $app->make(SldSvgRenderer::class)->render($renderGraph);
$assert(str_contains($svg, '>CHECK<'), 'Controlled SVG title block must expose the electrical-check field.');
$assert(str_contains($svg, '>R 0E/4W<'), 'Controlled SVG title block must expose the compact validation summary.');

echo "[APP67I] PHP PASS · baseline, duplicate tags, incompatible networks, cable metadata, protection ratings, AC bypass, and SVG check stamp\n";
