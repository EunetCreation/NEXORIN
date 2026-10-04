<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Http/Controllers/CustomerController.php');
$view = file_get_contents($root.'/resources/views/customers/index.blade.php');
$css = file_get_contents($root.'/public/css/nexorin-customers-index-v6.css');
$js = file_get_contents($root.'/public/js/nexorin-customers-index-v6.js');
$test = file_get_contents($root.'/tests/Feature/Page010CustomerIndexTest.php');

$checks = [
    'Page 010 uses page-local visual assets' => str_contains($view, 'nexorin-customers-index-v6.css')
        && str_contains($view, 'nexorin-customers-index-v6.js')
        && str_contains($css, '.page-customers-index-v6'),
    'directory remains server-side paginated' => str_contains($controller, '->paginate(12)')
        && str_contains($controller, "'search' =>")
        && str_contains($controller, "'status' =>")
        && str_contains($controller, "'stage' =>")
        && str_contains($controller, "'owner' =>"),
    'Page 008 and 009 CRM truth is reused' => str_contains($controller, 'CustomerProfile::query()')
        && str_contains($controller, 'CustomerContact::query()')
        && str_contains($controller, 'CustomerOpportunity::query()')
        && str_contains($controller, 'CustomerDocument::query()'),
    'row loading avoids per-row CRM queries' => str_contains($controller, "'profile.accountOwner:id,name,email'")
        && str_contains($controller, "'contacts' => fn")
        && str_contains($controller, "'opportunities as open_opportunities_count'")
        && str_contains($controller, "->withCount('documents')"),
    'opportunity value is currency-separated' => str_contains($controller, "->groupBy('currency_code')")
        && str_contains($view, 'openOpportunityCurrencyTotals')
        && ! str_contains($view, '>Pipeline Value<'),
    'no V6 fake CRM intelligence copy' => ! str_contains($view, 'Won YTD')
        && ! str_contains($view, 'Conversion Rate')
        && ! str_contains($view, 'Relationship Score')
        && ! str_contains($view, 'Activity Heatmap')
        && ! str_contains($view, 'Expiring Contracts')
        && ! str_contains($view, 'Follow-up Tasks'),
    'map uses exact project coordinates only' => str_contains($controller, "->whereNotNull('latitude')")
        && str_contains($controller, "->whereNotNull('longitude')")
        && str_contains($view, 'Exact markers use stored project coordinates only')
        && str_contains($js, 'coordinateSystem: \'geo\''),
    'archive blocker remains deterministic and visible' => str_contains($controller, "->whereNotIn('status', ['completed', 'cancelled'])")
        && str_contains($view, 'Archive blocked by open project')
        && str_contains($view, 'Archive Customer · Blocked'),
    'human-readable unavailable states exist' => str_contains($view, 'Not Available')
        && str_contains($view, 'Not Recorded')
        && ! str_contains($view, '[object Object]')
        && ! str_contains($view, 'undefined'),
    'controlled horizontal directory scroll exists' => str_contains($view, 'nx10-table-scroll')
        && str_contains($css, 'overflow-x:auto')
        && str_contains($css, 'min-width:1510px'),
    'right rail is content-height not fixed-height' => str_contains($css, '.nx10-right-rail')
        && str_contains($css, 'align-content:start')
        && ! str_contains($css, '.nx10-right-rail{height:'),
    'reduced motion is honored by CSS and ECharts' => str_contains($css, '@media(prefers-reduced-motion:reduce)')
        && str_contains($js, 'prefers-reduced-motion')
        && str_contains($js, 'animation: !reducedMotion'),
    'action menu has keyboard escape handling' => str_contains($view, 'aria-haspopup="menu"')
        && str_contains($js, "event.key === 'Escape'")
        && str_contains($js, "setAttribute('aria-expanded'"),
    'Page 010 feature tests cover core truth and tenancy' => substr_count($test, 'public function test_') >= 8
        && str_contains($test, 'does_not_leak_other_company')
        && str_contains($test, 'mixed_currency')
        && str_contains($test, 'paginates_directory'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL').": {$label}\n";
    if (! $passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "\nPage 010 static contract validation failed: ".implode('; ', $failed)."\n");
    exit(1);
}

echo "\nPage 010 static contract validation PASS (".count($checks)." checks).\n";
