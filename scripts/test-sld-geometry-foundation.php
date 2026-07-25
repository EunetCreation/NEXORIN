<?php

use App\Services\Sld\SldGeometryFoundation;

require __DIR__.'/../vendor/autoload.php';

$foundation = new SldGeometryFoundation;
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
        fwrite(STDERR, "[FAIL] {$message}\n");
    } else {
        fwrite(STDOUT, "[PASS] {$message}\n");
    }
};

$positions = [
    'a' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100],
    'b' => ['x' => 80, 'y' => 0, 'w' => 100, 'h' => 100],
    'c' => ['x' => 1630, 'y' => 920, 'w' => 100, 'h' => 100],
    'd' => ['x' => 195, 'y' => 0, 'w' => 100, 'h' => 100],
];

$report = $foundation->diagnose($positions);
$metadata = $foundation->metadata($positions);

$assert($report['version'] === SldGeometryFoundation::VERSION, 'geometry diagnostic version is APP67M.1');
$assert($report['contract'] === SldGeometryFoundation::CONTRACT, 'canonical model-space contract is recorded');
$assert(data_get($report, 'summary.overlaps') === 1, 'overlapping equipment is detected');
$assert(data_get($report, 'summary.outside_canvas') === 1, 'equipment outside the canonical canvas is detected');
$assert(data_get($report, 'summary.off_grid') >= 1, 'off-grid insertion points are advisory findings');
$assert(data_get($report, 'summary.clearance') >= 1, 'minimum equipment clearance is evaluated');
$assert(data_get($metadata, 'diagnostics.issues') === data_get($report, 'summary.issues'), 'persisted metadata is recomputed from canonical positions');

if ($failures !== []) {
    fwrite(STDERR, '[APP67M.1] Geometry Foundation PHP · '.count($failures)." failure(s)\n");
    exit(1);
}

fwrite(STDOUT, "[APP67M.1] Geometry Foundation PHP · 7/7 passed\n");
