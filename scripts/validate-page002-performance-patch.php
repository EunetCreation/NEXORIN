<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'app/Http/Controllers/BillOfMaterialController.php',
    'app/Models/CompanyMembership.php',
    'app/Models/User.php',
    'public/css/nexorin-bom-library-v6.css',
    'public/js/nexorin-bom-library-v6.js',
    'public/js/nexorin-engineering-action.js',
    'public/js/nexorin-shell.js',
    'resources/views/boms/index.blade.php',
    'resources/views/layouts/app.blade.php',
    'resources/views/partials/sidebar.blade.php',
    'resources/views/partials/topbar.blade.php',
];

$failures = [];
foreach ($required as $file) {
    if (! is_file($root.DIRECTORY_SEPARATOR.$file)) {
        $failures[] = "Missing: {$file}";
    }
}

$read = static fn (string $file): string => @file_get_contents($root.'/'.$file) ?: '';
$controller = $read('app/Http/Controllers/BillOfMaterialController.php');
$view = $read('resources/views/boms/index.blade.php');
$css = $read('public/css/nexorin-bom-library-v6.css');
$pageJs = $read('public/js/nexorin-bom-library-v6.js');
$navigationJs = $read('public/js/nexorin-engineering-action.js');
$shellJs = $read('public/js/nexorin-shell.js');
$layout = $read('resources/views/layouts/app.blade.php');
$userModel = $read('app/Models/User.php');
$membershipModel = $read('app/Models/CompanyMembership.php');
$topbar = $read('resources/views/partials/topbar.blade.php');
$sidebar = $read('resources/views/partials/sidebar.blade.php');

$contracts = [
    'Page 002 uses its scoped V6 stylesheet' => str_contains($view, 'nexorin-bom-library-v6.css')
        && ! str_contains($view, 'nexorin-bom-boq.css'),
    'Page 002 uses its scoped controller-backed script' => str_contains($view, 'nexorin-bom-library-v6.js'),
    'Dark V6 page contract is explicit' => str_contains($view, "@section('force-theme', 'dark')")
        && str_contains($view, "@section('body-class', 'page-bom-library-v6')"),
    'Directory remains backed by real project and BOM routes' => str_contains($view, "route('companies.boms.show'")
        && str_contains($view, "route('companies.technical-calculations.index'"),
    'Material catalog action is permission gated' => substr_count($view, '@if ($canViewMaterialCatalog)') >= 2
        && str_contains($controller, "'canViewMaterialCatalog' =>"),
    'Cost, selling value, and margin are independently permission gated' => str_contains($controller, "'canViewCost' =>")
        && str_contains($controller, "'canViewSelling' =>")
        && str_contains($controller, "'canViewMargin' =>")
        && str_contains($view, '$canViewCost')
        && str_contains($view, '$canViewSelling')
        && str_contains($view, '$canViewMargin'),
    'Controller supports search, system, project, and BOM filters' => str_contains($controller, "query('search'")
        && str_contains($controller, "query('system'")
        && str_contains($controller, "query('project_status'")
        && str_contains($controller, "query('status'"),
    'Controller consolidates portfolio metrics' => str_contains($controller, 'A single aggregate query')
        && str_contains($controller, "selectRaw('COUNT(projects.id) as projects')"),
    'Charts use actual BOM aggregates' => str_contains($controller, "DB::table('bill_of_material_items as item')")
        && str_contains($view, 'data-nxbom-chart-data')
        && str_contains($pageJs, 'window.echarts.init'),
    'Engineering readiness uses persisted engineer verification state' => str_contains($controller, "technical.result_status = 'verified'")
        && str_contains($view, 'engineer-verified projects')
        && str_contains($view, "\$technicalReady = \$technical?->result_status === 'verified'"),
    'Release control uses persisted ProjectReleaseApproval rather than synthetic BOM lock state' => str_contains($controller, "DB::table('project_release_approvals')")
        && str_contains($controller, "->where('status', 'active')")
        && str_contains($controller, "leftJoinSub(\$activeReleaseProjects, 'active_release'")
        && str_contains($controller, "\$stats['released']")
        && str_contains($view, '$project->activeReleaseApproval')
        && str_contains($view, "'released' => 'Active release approval'"),
    'Lifecycle chart uses mutually-exclusive persisted project states' => str_contains($controller, 'priced_unreleased')
        && str_contains($controller, 'generated_unpriced')
        && str_contains($controller, 'verified_not_generated')
        && str_contains($controller, 'engineering_pending')
        && str_contains($view, '<h3>Package Status</h3>'),
    'Cost donut total and legend shares use the same line-item value universe' => str_contains($controller, '$compositionCostTotal')
        && str_contains($controller, '$compositionPriceTotal')
        && str_contains($controller, "'cost_share' =>")
        && str_contains($controller, "'price_share' =>")
        && str_contains($view, "\$item['cost_share']")
        && str_contains($view, "\$item['price_share']")
        && str_contains($view, "\$stats['cost_composition_total_idr']"),
    'Donut center is geometrically centered and canvas center graphics are not duplicated' => str_contains($pageJs, "center: ['50%', '50%']")
        && ! str_contains($pageJs, 'centerGraphic')
        && ! str_contains($pageJs, 'option.graphic'),
    'HTML donut center remains centered after ECharts render' => str_contains($css, '.nxbomlib-chart-shell.is-rendered .nxbomlib-chart-fallback')
        && str_contains($css, 'border-color: transparent;')
        && ! preg_match('/\.nxbomlib-chart-shell\.is-rendered \.nxbomlib-chart-fallback\s*\{[^}]*display\s*:\s*none/s', $css),
    'Portfolio Health primary score is centered on the gauge axis' => str_contains($view, 'nxbomlib-health-score')
        && str_contains($css, '.nxbomlib-health-score')
        && str_contains($css, 'text-align: center;')
        && str_contains($view, 'nxbomlib-health-copy')
        && str_contains($css, '.nxbomlib-health-copy')
        && str_contains($css, 'left: 50%;'),
    'ECharts is deferred instead of blocking initial render' => str_contains($pageJs, 'IntersectionObserver')
        && str_contains($pageJs, 'loadEcharts')
        && ! str_contains($pageJs, 'requestIdleCallback'),
    'GET navigation is native unless explicitly blocking' => str_contains($navigationJs, 'config.blockingNavigation !== true')
        && str_contains($navigationJs, 'return true;'),
    'Workspace entry no longer blocks for 1.18 seconds' => (str_contains($shellJs, 'const totalDuration = reduceMotion ? 0 : 180;')
            || str_contains($shellJs, 'const totalDuration = reduceMotion ? 0 : 420;'))
        && ! str_contains($shellJs, 'const totalDuration = reduceMotion ? 180 : 1180;'),
    'Remote fonts are non-render-blocking' => str_contains($layout, 'rel="preload"')
        && str_contains($layout, "this.rel='stylesheet'"),
    'Authorization is memoized per authenticated model instance' => str_contains($userModel, 'superAdminAuthorizationCache')
        && str_contains($userModel, 'companyMembershipAuthorizationCache')
        && str_contains($membershipModel, 'authorizationContextCache'),
    'Topbar and sidebar reuse membership resolution' => str_contains($topbar, 'membershipForCompany($topbarCompany)')
        && str_contains($sidebar, 'membershipForCompany($sidebarCompany)'),
    'Filter values are not disabled before GET serialization' => ! str_contains($pageJs, "querySelectorAll('button, select')"),
    'Responsive states exist for desktop, tablet, and mobile' => substr_count($css, '@media (max-width:') >= 4
        && str_contains($css, '@media (prefers-reduced-motion: reduce)'),
    'Page CSS remains scoped and below 50 KiB' => str_starts_with(ltrim($css), '/* NEXORIN Page 002')
        && strlen($css) < 50 * 1024,
];

foreach ($contracts as $label => $passed) {
    if (! $passed) {
        $failures[] = "Contract failed: {$label}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "PAGE-002 PERFORMANCE PATCH VALIDATION: FAIL\n".implode("\n", $failures)."\n");
    exit(1);
}

echo "PAGE-002 PERFORMANCE PATCH VALIDATION: PASS\n";
echo 'Page CSS bytes: '.strlen($css)."\n";
echo 'Page JS bytes: '.strlen($pageJs)."\n";
