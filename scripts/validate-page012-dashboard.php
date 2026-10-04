<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

$read = static fn (string $path): string => file_get_contents($root.'/'.$path) ?: '';
$has = static fn (string $haystack, string $needle): bool => str_contains($haystack, $needle);
$notHas = static fn (string $haystack, string $needle): bool => ! str_contains($haystack, $needle);
$check = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
};

$routes = $read('routes/web.php');
$controller = $read('app/Http/Controllers/DashboardController.php');
$view = $read('resources/views/dashboard.blade.php');
$css = $read('public/css/nexorin-dashboard-v6.css');
$js = $read('public/js/nexorin-dashboard-v6.js');
$test = $read('tests/Feature/DashboardUiTest.php');

$check('dashboard route maps to invokable DashboardController', $has($routes, "Route::get('/dashboard', DashboardController::class)->name('dashboard')"));
$check('controller resolves active company', $has($controller, 'ActiveCompanyResolver'));
$check('project query is company scoped', $has($controller, '->forCompany($company)') && $has($controller, '->current()'));
$check('own-project permission scope exists', $has($controller, "'projects_all'") && $has($controller, 'ownedBy($user)'));
$check('catalog metric requires equipment permission', $has($controller, <<<'TXT'
'catalog' => $user->hasCompanyPermission($company, 'equipment.view')
TXT));
$check('price-reference count has separate commercial permission', $has($controller, "'price_references'") && $has($controller, "'price.cost.view'"));
$check('selling aggregate has customer-facing price boundary', $has($controller, "['price.selling.view', 'quotation.view']"));
$check('engineering aggregate separates stale records', $has($controller, "'stale' => 0") && $has($controller, 'engineeringDashboardStatusExpression'));
$check('work queue corrects stale technical record page-locally', $has($controller, 'dashboardCockpit') && $has($controller, "'metric' => 'Recalculation required'"));
$check('no designed-capacity sum survives', $notHas($controller, "sum('inverter_ac_power_kw')"));
$check('no arbitrary needs-review aggregate survives', $notHas($controller, 'needsReviewCount'));
$check('view identifies Command Center', $has($view, '>Command Center<'));
$check('view labels exact coordinates explicitly', $has($view, 'Exact Project Coordinates'));
$check('view labels BOM/BOQ selling aggregate truthfully', $has($view, 'Recorded Selling Value'));
$check('view keeps methodology compact', $has($view, '<details class="nx12-basis"') && $has($view, 'Data basis'));
$check('portfolio analytics use recorded system configuration', $has($controller, 'projectSystemCounts') && $has($view, 'System Mix'));
$check('sparse analytics collapse to single-state summaries', $has($view, 'nx12-single-state') && $has($view, 'hasProjectSystemDistribution') && $has($view, 'hasEngineeringDistribution'));
$check('view carries shared V6 token contract', $has($view, 'nexorin-v6-token-contract.css'));
$check('view carries current luxury grammar', $has($view, 'nexorin-luxury-system-v2.css'));
$check('view uses page-scoped dashboard assets', $has($view, 'nexorin-dashboard-v6.css') && $has($view, 'nexorin-dashboard-v6.js'));
$check('legacy fake dashboard labels removed', $notHas($view, 'Needs Attention') && $notHas($view, 'Designed Capacity') && $notHas($view, 'Commercial Value'));
$check('ECharts has contained enterprise tooltip', $has($js, 'confine: true'));
$check('map groups coincident exact coordinates without geocoding', $has($js, 'coordinateGroups') && $has($js, 'Exact coordinate'));
$check('map uses registered authoritative geometry', $has($js, "registerMap('nx12-indonesia'") || $has($js, 'registerMap("nx12-indonesia"'));
$check('dashboard JS does not synthesize random data', $notHas($js, 'Math.random'));
$check('JS has duplicate-init guard', $has($js, '__nexorinDashboardV6'));
$check('CSS neutralizes legacy generic dashboard grid', $has($css, '.dashboard-page.nx12') && $has($css, 'display: block'));
$check('desktop work queue avoids forced horizontal scroll', $has($css, '.nx12-table {') && $has($css, 'min-width: 0;') && $has($css, '@media (max-width: 820px)'));
$check('CSS respects reduced motion', $has($css, 'prefers-reduced-motion'));
$check('CSS hides duplicate shell heading only for Page 012', $has($css, '.page-dashboard-v6 .topbar .page-heading'));
$check('feature tests assert tenant isolation', $has($test, 'Foreign Tenant Project') && $has($test, 'assertDontSee'));
$check('feature tests assert restricted metric visibility', $has($test, 'Catalog Approval') && $has($test, 'Recorded Selling Value'));

$failed = 0;
foreach ($checks as [$label, $ok]) {
    printf("[%s] %s\n", $ok ? 'PASS' : 'FAIL', $label);
    if (! $ok) {
        $failed++;
    }
}

printf("\nPage 012 static contract: %d/%d passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
