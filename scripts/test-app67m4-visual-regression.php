<?php

declare(strict_types=1);

use App\Http\Controllers\ProjectSldController;
use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Services\Sld\SldEngineeringController;
use App\Services\Sld\SldPdfRenderer;
use App\Services\Sld\SldSvgRenderer;
use App\Services\Sld\SldVisualRegression;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$passed = 0;
$check = static function (string $name, callable $callback) use (&$passed): void {
    $callback();
    $passed++;
    fwrite(STDOUT, sprintf("[APP67M.4] PASS %d/6 · %s\n", $passed, $name));
};
$assert = static function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};

$editor = [
    'canvas' => ['x' => 0, 'y' => 0, 'w' => 900, 'h' => 500],
    'positions' => [
        'A' => ['x' => 80, 'y' => 80, 'w' => 120, 'h' => 80, 'rotation' => 0, 'flip_h' => false, 'flip_v' => false],
        'B' => ['x' => 360, 'y' => 80, 'w' => 120, 'h' => 80, 'rotation' => 0, 'flip_h' => false, 'flip_v' => false],
    ],
    'routes' => [
        'W1' => [['x' => 200, 'y' => 120], ['x' => 280, 'y' => 120], ['x' => 280, 'y' => 120], ['x' => 360, 'y' => 120]],
    ],
    'label_placement' => [
        'positions' => [
            'A' => ['x' => 80, 'y' => 174, 'w' => 120, 'h' => 48, 'side' => 'bottom', 'leader' => true, 'signature' => 'lp-11111111'],
            'B' => ['x' => 360, 'y' => 174, 'w' => 120, 'h' => 48, 'side' => 'bottom', 'leader' => true, 'signature' => 'lp-22222222'],
        ],
    ],
];

$check('published PHP visual-regression contract', static function () use ($assert): void {
    $assert(SldVisualRegression::VERSION === '67M.4', 'version mismatch');
    $assert(SldVisualRegression::CONTRACT === 'deterministic-scene-fingerprint-v1', 'contract mismatch');
    $assert(SldVisualRegression::ENGINE === 'canonical-scene-auditor', 'engine mismatch');
});

$check('shared JavaScript/PHP scene fingerprint is stable', static function () use ($assert, $app, $editor): void {
    $service = $app->make(SldVisualRegression::class);
    $snapshot = $service->snapshot($editor);
    $assert(($snapshot['fingerprint'] ?? '') === 'vr-7bb557ca', 'shared fingerprint changed');
    $reverse = $editor;
    $reverse['positions'] = array_reverse($editor['positions'], true);
    $reverse['label_placement']['positions'] = array_reverse($editor['label_placement']['positions'], true);
    $assert($service->snapshot($reverse)['fingerprint'] === $snapshot['fingerprint'], 'input order changed fingerprint');
    $unicode = $service->snapshot([
        'canvas' => ['x' => 0, 'y' => 0, 'w' => 900, 'h' => 500],
        'positions' => ['Beban-α' => ['x' => 80, 'y' => 80, 'w' => 120, 'h' => 80]],
        'routes' => [], 'label_placement' => ['positions' => []],
    ]);
    $assert($unicode['fingerprint'] === 'vr-869817e5', 'UTF-8 fingerprint differs from JavaScript');
});

$check('controller recomputes server-owned visual metadata', static function () use ($assert, $app): void {
    $controller = $app->make(ProjectSldController::class);
    $method = new ReflectionMethod($controller, 'normalizeEditorState');
    $state = $method->invoke($controller, [
        'version' => 16,
        'canvas' => ['width' => 1600, 'height' => 920],
        'positions' => ['panel' => ['x' => 100, 'y' => 100, 'w' => 144, 'h' => 120]],
        'visual_regression' => [
            'version' => 'forged',
            'fingerprint' => 'vr-deadbeef',
            'summary' => ['issues' => 2, 'errors' => 1, 'warnings' => 1],
        ],
    ]);
    $assert(data_get($state, 'visual_regression.version') === SldVisualRegression::VERSION, 'version was not normalized');
    $assert(data_get($state, 'visual_regression.contract') === SldVisualRegression::CONTRACT, 'contract was not normalized');
    $assert((bool) preg_match('/^vr-[0-9a-f]{8}$/', (string) data_get($state, 'visual_regression.fingerprint')), 'fingerprint missing');
    $assert(data_get($state, 'visual_regression.fingerprint') !== 'vr-deadbeef', 'forged fingerprint was retained');
    $assert(data_get($state, 'visual_regression.status') === 'pass', 'forged visual status was retained');
    $assert(data_get($state, 'visual_regression.summary.errors') === 0, 'forged visual errors were retained');
});

$check('engineering synchronization upgrades visual metadata', static function () use ($assert, $app): void {
    $service = $app->make(SldEngineeringController::class);
    $method = new ReflectionMethod($service, 'preserveEditor');
    $editor = ['version' => 16, 'visual_regression' => ['version' => 'legacy'], 'positions' => ['panel' => ['x' => 100, 'y' => 100, 'w' => 144, 'h' => 120]]];
    $fresh = ['nodes' => [['id' => 'panel']], 'editor' => ['positions' => ['panel' => ['x' => 120, 'y' => 120, 'w' => 144, 'h' => 120]]]];
    $merged = $method->invoke($service, $editor, $fresh);
    $assert(data_get($merged, 'visual_regression.version') === SldVisualRegression::VERSION, 'sync version was not upgraded');
    $assert(data_get($merged, 'visual_regression.contract') === SldVisualRegression::CONTRACT, 'sync contract was not upgraded');
    $assert((bool) preg_match('/^vr-[0-9a-f]{8}$/', (string) data_get($merged, 'visual_regression.fingerprint')), 'sync fingerprint missing');
});

$check('SVG and fallback PDF publish visual-regression evidence', static function () use ($assert, $app): void {
    $graph = [
        'schema_version' => 'nexorin-sld-v1.9.0',
        'meta' => ['title' => 'APP67M.4', 'project_number' => 'M4-TEST', 'project_name' => 'Visual Closure', 'system_label' => 'PV', 'calculation_verified' => true, 'summary' => []],
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
    $svg = $app->make(SldSvgRenderer::class)->render($graph);
    $assert(str_contains($svg, 'data-visual-regression-version="'.SldVisualRegression::VERSION.'"'), 'SVG version missing');
    $assert(str_contains($svg, 'data-visual-regression-contract="'.SldVisualRegression::CONTRACT.'"'), 'SVG contract missing');
    $assert((bool) preg_match('/data-visual-regression-fingerprint="vr-[0-9a-f]{8}"/', $svg), 'SVG fingerprint missing');
    $assert(str_contains($svg, 'data-visual-regression-status="pass"'), 'SVG status missing');

    $project = new Project();
    $project->name = 'Visual Closure';
    $project->project_number = 'M4-TEST';
    $revision = new ProjectSldRevision();
    $revision->graph = $graph;
    $revision->revision = 1;
    $revision->issue_status = 'draft';
    $pdf = $app->make(SldPdfRenderer::class)->render($project, $revision);
    $assert(str_starts_with($pdf, '%PDF-1.4'), 'fallback PDF is invalid');
    $assert(str_contains($pdf, 'APP'.SldVisualRegression::VERSION), 'fallback PDF version evidence missing');
    $assert(str_contains($pdf, SldVisualRegression::CONTRACT), 'fallback PDF contract evidence missing');
    $assert((bool) preg_match('/vr-[0-9a-f]{8}/', $pdf), 'fallback PDF fingerprint missing');
});

$check('visual metadata preserves tolerance and recomputes server-owned closure status', static function () use ($assert, $app, $editor): void {
    $service = $app->make(SldVisualRegression::class);
    $normalized = $service->normalizeMetadata(array_replace($editor, [
        'visual_regression' => ['summary' => ['issues' => 1, 'errors' => 0, 'warnings' => 1]],
    ]));
    $assert(($normalized['coordinate_tolerance'] ?? null) === SldVisualRegression::COORDINATE_TOLERANCE, 'coordinate tolerance mismatch');
    $assert(($normalized['clipping_tolerance'] ?? null) === SldVisualRegression::CLIPPING_TOLERANCE, 'clipping tolerance mismatch');
    $assert(($normalized['status'] ?? null) === 'pass', 'forged browser status was retained');
    $assert(data_get($normalized, 'summary.issues') === 0, 'forged browser issue count was retained');

    $audit = $service->audit([
        'canvas' => ['x' => 0, 'y' => 0, 'w' => 240, 'h' => 180],
        'positions' => [
            'A' => ['x' => 80, 'y' => 80, 'w' => 120, 'h' => 80],
            'B' => ['x' => 160, 'y' => 80, 'w' => 120, 'h' => 80],
        ],
        'routes' => ['W1' => [['x' => 10, 'y' => 10], ['x' => 60, 'y' => 40]]],
        'label_placement' => ['positions' => ['A' => ['x' => 100, 'y' => 100, 'w' => 100, 'h' => 40]]],
    ]);
    $assert(($audit['status'] ?? null) === 'error', 'server audit failed to detect an invalid scene');
    $assert(data_get($audit, 'summary.overlaps', 0) > 0, 'server overlap audit missing');
    $assert(data_get($audit, 'summary.non_orthogonal_routes', 0) > 0, 'server orthogonality audit missing');
});

fwrite(STDOUT, sprintf("[APP67M.4] Visual Regression PHP contract · %d/6 passed\n", $passed));
