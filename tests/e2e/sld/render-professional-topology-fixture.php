<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Services\Calculation\TechnicalCalculationEngine;
use App\Services\Sld\SldDocumentControl;
use App\Services\Sld\SldGraphBuilder;
use App\Services\Sld\SldPaperComposer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

$basePath = dirname(__DIR__, 3);
$port = getenv('NEXORIN_SLD_E2E_PORT') ?: '4177';

$setEnvironment = static function (string $key, string $value): void {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
};

$setEnvironment('APP_ENV', 'e2e');
$setEnvironment('APP_URL', 'http://127.0.0.1:'.$port);
$setEnvironment('APP_KEY', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
$setEnvironment('APP_DEBUG', 'true');
$setEnvironment('SESSION_DRIVER', 'array');
$setEnvironment('CACHE_STORE', 'array');
$setEnvironment('QUEUE_CONNECTION', 'sync');
$setEnvironment('MAIL_MAILER', 'array');
$setEnvironment('LOG_CHANNEL', 'stderr');
$setEnvironment('APP_CONFIG_CACHE', 'bootstrap/cache/config-e2e.php');
$setEnvironment('APP_ROUTES_CACHE', 'bootstrap/cache/routes-e2e.php');
$setEnvironment('APP_EVENTS_CACHE', 'bootstrap/cache/events-e2e.php');
$setEnvironment('APP_PACKAGES_CACHE', 'bootstrap/cache/packages.php');
$setEnvironment('APP_SERVICES_CACHE', 'bootstrap/cache/services.php');

require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$company = new Company([
    'name' => 'NEXORIN Engineering Demo',
    'code' => 'NX',
    'registration_number' => 'REG-E2E-9201',
    'address' => 'Jakarta Engineering Office',
]);
$company->id = 9201;
$company->exists = true;

$now = Carbon::parse('2026-07-22T09:00:00+07:00');
$project = new Project([
    'name' => 'APP67L.4 Production Drawing Fixture',
    'project_number' => 'NX-E2E-2026-001',
    'proposed_system' => 'on_grid',
    'site_address' => 'Jl. Engineering Test 1',
    'city' => 'Jakarta',
    'province' => 'DKI Jakarta',
    'postal_code' => '10110',
]);
$project->id = 9202;
$project->company_id = $company->id;
$project->exists = true;
$project->created_at = $now->copy()->subDays(2);
$project->updated_at = $now->copy()->subHours(2);

$customer = new Customer([
    'name' => 'PT Production Client',
    'customer_number' => 'CUST-E2E-001',
    'type' => 'commercial',
    'status' => 'active',
]);
$customer->id = 9206;
$customer->company_id = $company->id;
$customer->exists = true;

$engineer = new User(['name' => 'E2E Design Engineer', 'email' => 'engineer-e2e@nexorin.test']);
$engineer->id = 9207;
$engineer->exists = true;

$sales = new User(['name' => 'E2E Project Coordinator', 'email' => 'coordinator-e2e@nexorin.test']);
$sales->id = 9208;
$sales->exists = true;

$actor = new User(['name' => 'E2E Drawing Controller', 'email' => 'controller-e2e@nexorin.test']);
$actor->id = 9209;
$actor->exists = true;

$project->customer_id = $customer->id;
$project->assigned_engineer_id = $engineer->id;
$project->assigned_sales_id = $sales->id;

$input = [
    'system_type' => 'on_grid',
    'energy_offset_target_percent' => 80,
    'performance_ratio' => 0.78,
    'module_power_wp' => 550,
    'module_area_m2' => 2.58,
    'module_voc_v' => 49.90,
    'module_vmp_v' => 41.80,
    'module_isc_a' => 13.90,
    'module_imp_a' => 13.16,
    'module_voc_temp_coeff_percent_c' => -0.25,
    'module_vmp_temp_coeff_percent_c' => -0.29,
    'minimum_site_temperature_c' => 15,
    'maximum_cell_temperature_c' => 70,
    'inverter_ac_power_kw' => 25,
    'inverter_max_dc_voltage_v' => 1100,
    'inverter_mppt_min_voltage_v' => 200,
    'inverter_mppt_max_voltage_v' => 1000,
    'inverter_max_input_current_a' => 32,
    'inverter_mppt_count' => 4,
    'desired_dc_ac_ratio' => 1.05,
    'inverter_efficiency_percent' => 98,
    'battery_backup_hours' => 0,
    'battery_dod_percent' => 90,
    'battery_efficiency_percent' => 95,
    'dc_cable_length_m' => 30,
    'dc_cable_area_mm2' => 6,
    'ac_cable_length_m' => 20,
    'ac_cable_area_mm2' => 35,
    'cable_material' => 'copper',
    'maximum_voltage_drop_percent' => 2,
];
$sources = [
    'load' => [
        'daily_energy_kwh' => 230,
        'critical_daily_energy_kwh' => 0,
        'peak_demand_kw' => 61,
        'supply_phase' => 'three_phase',
    ],
    'solar' => ['average_psh' => 4.5],
    'roof' => ['usable_area_m2' => 270, 'weighted_shading_percent' => 0, 'warnings' => []],
    'recommendation' => ['recommended_system' => 'on_grid'],
];
$result = $app->make(TechnicalCalculationEngine::class)->calculate($project, $input, $sources);

$calculation = new TechnicalCalculation(array_merge($input, [
    'result_status' => 'verified',
    'results' => $result,
    'warnings' => [],
]));
$calculation->id = 9203;
$calculation->exists = true;
$calculation->calculated_at = $now->copy()->subHour();
$calculation->verified_at = $now->copy()->subMinutes(50);
$calculation->created_at = $now->copy()->subDay();
$calculation->updated_at = $now->copy()->subHour();

$recommendation = new SystemRecommendation([
    'recommended_system' => 'on_grid',
    'export_policy' => 'zero_export',
]);
$recommendation->id = 9204;
$recommendation->exists = true;
$recommendation->created_at = $now->copy()->subDay();
$recommendation->updated_at = $now->copy()->subHours(2);

$project->setRelation('company', $company);
$project->setRelation('customer', $customer);
$project->setRelation('assignedEngineer', $engineer);
$project->setRelation('assignedSales', $sales);
$project->setRelation('technicalCalculation', $calculation);
$project->setRelation('systemRecommendation', $recommendation);
$project->setRelation('billOfMaterial', null);
$project->setRelation('financialAnalysis', null);
$project->setRelation('quotation', null);
$project->setRelation('loadAnalysis', null);
$project->setRelation('solarProfile', null);
$project->setRelation('roofSiteSurvey', null);

$graph = $app->make(SldGraphBuilder::class)->build($project);
$documentControl = $app->make(SldDocumentControl::class)->build(
    $project,
    8,
    $actor,
    [
        'drawing_number_prefix' => 'NX-E2E-2026-001-E-SLD',
        'drawing_title' => 'Controlled PV drawing package',
        'client_name' => $customer->name,
        'site_location' => 'Jakarta, DKI Jakarta',
    ],
    'draft'
);
$graph = $app->make(SldDocumentControl::class)->stampGraph($graph, $documentControl);
$revision = new ProjectSldRevision([
    'revision' => 8,
    'status' => 'draft',
    'issue_status' => 'draft',
    'drawing_number_prefix' => 'NX-E2E-2026-001-E-SLD',
    'drawing_title' => 'Controlled PV drawing package',
    'client_name' => $customer->name,
    'site_location' => 'Jakarta, DKI Jakarta',
    'document_metadata' => $documentControl,
    'issue_history' => [],
    'graph' => $graph,
]);
$revision->id = 9205;
$revision->company_id = $company->id;
$revision->project_id = $project->id;
$revision->exists = true;

$paper = $app->make(SldPaperComposer::class)->compose($graph);

// Keep browser startup deterministic and fast. The production SVG/PDF renderer
// remains covered by scripts/test-app67l2-professional-topology.php.
$nodeCount = count($graph['nodes'] ?? []);
$connectionCount = count($graph['connections'] ?? []);
$a3Svg = sprintf(<<<'SVG'
<svg viewBox="0 0 1680 1188" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="APP67L.4 production drawing workflow E2E sheet">
    <rect width="1680" height="1188" fill="#ffffff"/>
    <rect x="35" y="35" width="1610" height="1118" fill="none" stroke="#0f2630" stroke-width="3"/>
    <text x="80" y="100" font-family="Arial, sans-serif" font-size="34" fill="#0f2630">NEXORIN APP67L.4 PRODUCTION DRAWING WORKFLOW</text>
    <text x="80" y="148" font-family="Arial, sans-serif" font-size="22" fill="#334b55">%d physical devices · %d controlled circuits</text>
    <text x="80" y="1090" font-family="Arial, sans-serif" font-size="18" fill="#52666e">Browser fixture sheet. Production metadata and six-sheet output are validated by the APP67L.4 integration suites.</text>
</svg>
SVG, $nodeCount, $connectionCount);
$html = view('slds.editor', [
    'company' => $company,
    'project' => $project,
    'revision' => $revision,
    'graph' => $graph,
    'a3Svgs' => [$a3Svg],
    'paperSummary' => $paper['summary'],
    'paperSheets' => array_map(static fn (array $sheet): array => [
        'number' => $sheet['number'] ?? 1,
        'title' => $sheet['title'] ?? 'SLD Overview',
        'type' => $sheet['type'] ?? 'as_drawn',
        'node_count' => count($sheet['node_ids'] ?? []),
    ], $paper['sheets']),
    'canEdit' => true,
])->render();

$html = preg_replace(
    '/data-editor-save-url="[^"]*"/',
    'data-editor-save-url="/__e2e/save" data-editor-e2e="true"',
    $html,
    1,
) ?: $html;
$html = preg_replace(
    '/data-editor-close-url="[^"]*"/',
    'data-editor-close-url="/__e2e/closed"',
    $html,
    1,
) ?: $html;
$html = preg_replace(
    '/data-editor-controller-url="[^"]*"/',
    'data-editor-controller-url="/__e2e/controller"',
    $html,
    1,
) ?: $html;
$html = preg_replace('~\s*<link[^>]+fonts\.(?:googleapis|gstatic)\.com[^>]*>~i', '', $html) ?: $html;

if (! str_contains($html, 'data-sld-editor')) {
    fwrite(STDERR, "APP67L.4 fixture rendered HTML without the SLD root marker.\n");
    exit(2);
}

echo $html;
