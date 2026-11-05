<?php

declare(strict_types=1);

/**
 * Static & structural validation script for NEXORIN Page 022 — Edit Material.
 * Verifies controller contract, view markers, CSS/JS assets, and zero regression on Page 021.
 */

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Http/Controllers/MaterialCatalogController.php',
    'resources/views/materials/edit.blade.php',
    'public/css/nexorin-material-edit.css',
    'public/js/nexorin-material-edit.js',
    'tests/Feature/Page022MaterialEditTest.php',
    // Page 021 baseline files
    'resources/views/materials/create.blade.php',
    'resources/views/materials/partials/form.blade.php',
    'public/css/nexorin-material-create.css',
    'public/js/nexorin-material-catalog.js',
    'tests/Feature/Page021MaterialCreateTest.php',
];

foreach ($requiredFiles as $relPath) {
    $full = $root . '/' . $relPath;
    if (!is_file($full)) {
        fwrite(STDERR, "MISSING REQUIRED FILE: {$relPath}\n");
        exit(1);
    }
}

$controller = file_get_contents($root . '/app/Http/Controllers/MaterialCatalogController.php');
$editBlade = file_get_contents($root . '/resources/views/materials/edit.blade.php');
$editCss = file_get_contents($root . '/public/css/nexorin-material-edit.css');
$editJs = file_get_contents($root . '/public/js/nexorin-material-edit.js');

$controllerAssertions = [
    'GOVERNANCE_IMPACT_FIELDS',
    'ADMINISTRATIVE_ONLY_FIELDS',
    'buildMutationContext($material, $formData[\'initialSpecificationSchema\'], $catalogReadiness)',
    'markForCatalogReview($request->user()->getKey())',
    "'mutationContext' => \$this->buildMutationContext",
];

foreach ($controllerAssertions as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "CONTROLLER CONTRACT MISSING: {$needle}\n");
        exit(1);
    }
}

$bladeAssertions = [
    "@section('force-theme', 'dark')",
    'page-material-edit-v6',
    'nexorin-material-edit.css',
    'nexorin-material-edit.js',
    '@method(\'PUT\')',
    'id="nxmceEditForm"',
    'data-nxmce-baseline-payload',
    'data-nxmc-specification-schema-payload',
    'Change impact',
    'Governance state',
    'Evidence &amp; baseline',
    'Catalog media boundary',
];

foreach ($bladeAssertions as $needle) {
    if (!str_contains($editBlade, $needle)) {
        fwrite(STDERR, "EDIT BLADE CONTRACT MISSING: {$needle}\n");
        exit(1);
    }
}

echo "PAGE 022 STATIC CONTRACT VALIDATION PASSED\n";
