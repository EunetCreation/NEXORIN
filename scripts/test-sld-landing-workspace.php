<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$view = file_get_contents($root.'/resources/views/slds/show.blade.php');
$css = file_get_contents($root.'/public/css/nexorin-sld.css');
$js = file_get_contents($root.'/public/js/nexorin-sld.js');

$requiredViewContracts = [
    'data-sld-review-workspace',
    'nxsld-page-header',
    'nxsld-status-strip',
    'nxsld-main-workspace',
    'nxsld-cad-stage',
    'nxsld-action-rail',
    "['overview' => 'Overview', 'issues' => 'Issues', 'traceability' => 'Traceability', 'revisions' => 'Revisions', 'release' => 'Release']",
    'data-sld-export-dialog',
    "@include('slds.partials.overview')",
    "@include('slds.partials.issues')",
    "@include('slds.partials.traceability')",
    "@include('slds.partials.revisions')",
    "@include('slds.partials.release')",
];

foreach ($requiredViewContracts as $contract) {
    if (! str_contains($view, $contract)) {
        fwrite(STDERR, "[SLD-LANDING-001] Missing view contract: {$contract}\n");
        exit(1);
    }
}

foreach (['nxsld-hero', 'nxsld-inspector', 'Production drawing control'] as $legacy) {
    if (str_contains($view, $legacy)) {
        fwrite(STDERR, "[SLD-LANDING-001] Legacy long-page contract still present: {$legacy}\n");
        exit(1);
    }
}

$requiredCssTokens = [
    '--sld-navy-950: #061321',
    '--sld-ivory: #f7f4ee',
    '--sld-gold: #c59757',
    '--sld-cad: #071725',
    '.nxsld-main-workspace',
    '.nxsld-detail-tabs',
    '.nxsld-export-dialog',
];
foreach ($requiredCssTokens as $token) {
    if (! str_contains($css, $token)) {
        fwrite(STDERR, "[SLD-LANDING-001] Missing CSS contract: {$token}\n");
        exit(1);
    }
}

foreach (['openReviewTab', 'data-sld-sync', 'data-sld-export-blocked', 'data-sld-monochrome', 'data-sld-fit-width'] as $token) {
    if (! str_contains($js, $token)) {
        fwrite(STDERR, "[SLD-LANDING-001] Missing JavaScript contract: {$token}\n");
        exit(1);
    }
}

$bladeFiles = [
    'resources/views/slds/show.blade.php',
    'resources/views/slds/partials/overview.blade.php',
    'resources/views/slds/partials/issues.blade.php',
    'resources/views/slds/partials/traceability.blade.php',
    'resources/views/slds/partials/revisions.blade.php',
    'resources/views/slds/partials/release.blade.php',
];
$filesystem = new Filesystem();
$cache = sys_get_temp_dir().'/nexorin-sld-landing-blade';
$filesystem->ensureDirectoryExists($cache);
$compiler = new BladeCompiler($filesystem, $cache);

foreach ($bladeFiles as $relativePath) {
    $source = file_get_contents($root.'/'.$relativePath);
    $compiled = $compiler->compileString($source);
    $temporary = tempnam($cache, 'blade-');
    file_put_contents($temporary, $compiled);
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temporary).' 2>&1', $output, $exitCode);
    @unlink($temporary);
    if ($exitCode !== 0) {
        fwrite(STDERR, "[SLD-LANDING-001] Blade syntax failed for {$relativePath}:\n".implode("\n", $output)."\n");
        exit(1);
    }
}

printf(
    "[SLD-LANDING-001] PASS · drawing-first light corporate workspace, dark CAD preview, persistent actions, five detail tabs, blocked-export dialog, source synchronization, and Blade syntax contracts\n"
);
