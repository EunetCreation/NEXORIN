<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Services\Sld\SldElectricalRuleChecker;
use App\Services\Sld\SldSymbolRegistry;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__, 3);
$port = getenv('NEXORIN_SLD_E2E_PORT') ?: '4177';

$setEnvironment = static function (string $key, string $value): void {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
};

// Establish an isolated Laravel runtime before Composer or the framework boots.
$setEnvironment('APP_ENV', 'e2e');
$setEnvironment('APP_URL', 'http://127.0.0.1:'.$port);
$setEnvironment('APP_KEY', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
$setEnvironment('APP_DEBUG', 'true');
$setEnvironment('SESSION_DRIVER', 'array');
$setEnvironment('CACHE_STORE', 'array');
$setEnvironment('QUEUE_CONNECTION', 'sync');
$setEnvironment('MAIL_MAILER', 'array');
$setEnvironment('LOG_CHANNEL', 'stderr');

// Never let a developer's cached production/local configuration override the fixture.
$setEnvironment('APP_CONFIG_CACHE', 'bootstrap/cache/config-e2e.php');
$setEnvironment('APP_ROUTES_CACHE', 'bootstrap/cache/routes-e2e.php');
$setEnvironment('APP_EVENTS_CACHE', 'bootstrap/cache/events-e2e.php');
$setEnvironment('APP_PACKAGES_CACHE', 'bootstrap/cache/packages.php');
$setEnvironment('APP_SERVICES_CACHE', 'bootstrap/cache/services.php');

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$company = new Company(['name' => 'NEXORIN E2E']);
$company->id = 9001;
$company->exists = true;

$project = new Project([
    'name' => 'SLD Regression Fixture',
    'project_number' => 'E2E-SLD-001',
]);
$project->id = 9002;
$project->company_id = $company->id;
$project->exists = true;

$revision = new ProjectSldRevision([
    'revision' => 7,
    'status' => 'draft',
]);
$revision->id = 9003;
$revision->company_id = $company->id;
$revision->project_id = $project->id;
$revision->exists = true;

$graph = [
    'schema_version' => 'nexorin-sld-v1.6.0',
    'meta' => [
        'system_label' => 'On-grid PV single-line diagram',
        'calculation_verified' => true,
        'summary' => [
            'installed_pv_kw' => 12.40,
            'string_layout' => '2 × 10 modules',
            'inverter_ac_kw' => 10.00,
            'supply_phase' => 'three_phase',
        ],
    ],
    'nodes' => [
        ['id' => 'pv_array', 'tag' => 'PVA-01', 'title' => 'PV array', 'subtitle' => '2 strings × 10 modules', 'rating' => '12.40 kWp', 'kind' => 'generation', 'symbol' => 'pv_array', 'state' => 'ready'],
        ['id' => 'dc_protection', 'tag' => 'QSDC-01', 'title' => 'DC protection', 'subtitle' => 'Isolator, fuse, and SPD', 'rating' => '1000 VDC · 25 A', 'kind' => 'protection', 'symbol' => 'dc_protection', 'state' => 'ready'],
        ['id' => 'inverter', 'tag' => 'INV-01', 'title' => 'String inverter', 'subtitle' => 'DC to AC conversion', 'rating' => '10 kW · 400 VAC', 'kind' => 'conversion', 'symbol' => 'inverter', 'state' => 'ready'],
        ['id' => 'ac_protection', 'tag' => 'QFAC-01', 'title' => 'AC protection', 'subtitle' => 'Breaker and SPD', 'rating' => '400 VAC · 25 A', 'kind' => 'protection', 'symbol' => 'ac_protection', 'state' => 'ready'],
        ['id' => 'meter', 'tag' => 'MTR-01', 'title' => 'Energy meter', 'subtitle' => 'Bidirectional metering', 'rating' => '3P4W · Class 1', 'kind' => 'control', 'symbol' => 'meter', 'state' => 'ready'],
        ['id' => 'grid', 'tag' => 'GRID-01', 'title' => 'Utility grid', 'subtitle' => 'PLN connection', 'rating' => '400/230 VAC', 'kind' => 'utility', 'symbol' => 'grid', 'state' => 'ready'],
    ],
    'connections' => [
        ['id' => 'SRC-01-PV-DC', 'wire_id' => 'C-DC-01', 'cable_tag' => 'C-DC-01', 'from' => 'pv_array', 'to' => 'dc_protection', 'from_port' => 'DC_OUT', 'to_port' => 'DC_IN', 'network' => 'dc_power', 'label' => 'PV source circuit', 'detail' => 'PV1-F · 2C × 6 mm² · 1.5 kV DC', 'cable_type' => 'PV1-F', 'cable_size' => '6', 'cores' => 2, 'voltage_class' => '1.5 kV DC', 'installation_method' => 'PV array to DC protection', 'engineering_binding' => ['authority' => 'technical_calculation', 'controller_version' => '67K.1', 'connection_key' => 'pv_array>dc_protection|dc_power']],
        ['id' => 'SRC-02-DC-INV', 'wire_id' => 'C-DC-02', 'cable_tag' => 'C-DC-02', 'from' => 'dc_protection', 'to' => 'inverter', 'from_port' => 'DC_OUT', 'to_port' => 'DC_IN', 'network' => 'dc_power', 'label' => 'Protected DC input', 'detail' => 'PV1-F · 2C × 6 mm² · 1.5 kV DC', 'cable_type' => 'PV1-F', 'cable_size' => '6', 'cores' => 2, 'voltage_class' => '1.5 kV DC', 'installation_method' => 'DC protection to inverter', 'engineering_binding' => ['authority' => 'technical_calculation', 'controller_version' => '67K.1', 'connection_key' => 'dc_protection>inverter|dc_power']],
        ['id' => 'SRC-03-INV-AC', 'wire_id' => 'C-AC-01', 'cable_tag' => 'C-AC-01', 'from' => 'inverter', 'to' => 'ac_protection', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN', 'network' => 'ac_power', 'label' => 'Inverter AC feeder', 'detail' => 'CU XLPE/PVC · 5C × 6 mm² · 0.6/1 kV AC', 'cable_type' => 'CU XLPE/PVC', 'cable_size' => '6', 'cores' => 5, 'voltage_class' => '0.6/1 kV AC', 'installation_method' => 'Inverter to AC protection', 'engineering_binding' => ['authority' => 'technical_calculation', 'controller_version' => '67K.1', 'connection_key' => 'inverter>ac_protection|ac_power']],
        ['id' => 'SRC-04-AC-MTR', 'wire_id' => 'C-AC-02', 'cable_tag' => 'C-AC-02', 'from' => 'ac_protection', 'to' => 'meter', 'from_port' => 'AC_OUT', 'to_port' => 'AC_IN', 'network' => 'ac_power', 'label' => 'Protected AC feeder', 'detail' => 'CU XLPE/PVC · 5C × 6 mm² · 0.6/1 kV AC', 'cable_type' => 'CU XLPE/PVC', 'cable_size' => '6', 'cores' => 5, 'voltage_class' => '0.6/1 kV AC', 'installation_method' => 'AC protection to metering', 'engineering_binding' => ['authority' => 'technical_calculation', 'controller_version' => '67K.1', 'connection_key' => 'ac_protection>meter|ac_power']],
        ['id' => 'SRC-05-MTR-GRID', 'wire_id' => 'C-AC-03', 'cable_tag' => 'C-AC-03', 'from' => 'meter', 'to' => 'grid', 'from_port' => 'AC_OUT', 'to_port' => 'AC_PCC', 'network' => 'ac_power', 'label' => 'Point of connection', 'detail' => 'CU XLPE/PVC · 5C × 10 mm² · 0.6/1 kV AC', 'cable_type' => 'CU XLPE/PVC', 'cable_size' => '10', 'cores' => 5, 'voltage_class' => '0.6/1 kV AC', 'installation_method' => 'Meter to PCC', 'engineering_binding' => ['authority' => 'technical_calculation', 'controller_version' => '67K.1', 'connection_key' => 'meter>grid|ac_power']],
    ],
    'source_snapshot' => [
        'fingerprint' => 'fixture-source-fingerprint-67k',
        'technical_calculation' => ['id' => 9101, 'status' => 'verified', 'calculated_at' => '2026-07-21T08:00:00+07:00'],
        'bill_of_material' => ['id' => 9102, 'pricing_status' => 'priced'],
    ],
    'engineering_controller' => [
        'version' => '67K.1',
        'status' => 'blocked',
        'blocking' => true,
        'source_current' => false,
        'summary' => ['error' => 1, 'warning' => 1, 'notice' => 1, 'total' => 3],
        'message' => '1 engineering-controller error blocks review and controlled export.',
        'sources' => [
            ['key' => 'calculation', 'label' => 'Technical Calculation', 'status' => 'ready', 'detail' => 'Engineer Verified', 'authority' => true],
            ['key' => 'recommendation', 'label' => 'System Recommendation', 'status' => 'ready', 'detail' => 'On Grid', 'authority' => true],
            ['key' => 'sld', 'label' => 'SLD Controlled Graph', 'status' => 'stale', 'detail' => 'Synchronization required', 'authority' => false],
            ['key' => 'bom', 'label' => 'BOM / BOQ', 'status' => 'ready', 'detail' => 'Generated Draft · Priced', 'authority' => false],
            ['key' => 'financial', 'label' => 'Financial Analysis', 'status' => 'ready', 'detail' => 'Calculated', 'authority' => false],
            ['key' => 'quotation', 'label' => 'Quotation', 'status' => 'stale', 'detail' => 'Draft snapshot', 'authority' => false],
        ],
        'impacts' => [
            ['key' => 'topology', 'label' => 'Topology', 'count' => 1, 'status' => 'review'],
            ['key' => 'cable', 'label' => 'Cable schedule', 'count' => 0, 'status' => 'ready'],
            ['key' => 'protection', 'label' => 'Protection schedule', 'count' => 0, 'status' => 'ready'],
            ['key' => 'bom', 'label' => 'BOM / BOQ', 'count' => 0, 'status' => 'ready'],
            ['key' => 'quotation', 'label' => 'Quotation', 'count' => 1, 'status' => 'review'],
        ],
        'cable_schedule' => [
            ['wire_id' => 'C-DC-01', 'label' => 'PV source circuit', 'detail' => 'PV1-F · 2C × 6 mm² · 1.5 kV DC'],
            ['wire_id' => 'C-DC-02', 'label' => 'Protected DC input', 'detail' => 'PV1-F · 2C × 6 mm² · 1.5 kV DC'],
            ['wire_id' => 'C-AC-01', 'label' => 'Inverter AC feeder', 'detail' => 'CU XLPE/PVC · 5C × 6 mm² · 0.6/1 kV AC'],
            ['wire_id' => 'C-AC-02', 'label' => 'Protected AC feeder', 'detail' => 'CU XLPE/PVC · 5C × 6 mm² · 0.6/1 kV AC'],
            ['wire_id' => 'C-AC-03', 'label' => 'Point of connection', 'detail' => 'CU XLPE/PVC · 5C × 10 mm² · 0.6/1 kV AC'],
        ],
        'protection_schedule' => [
            ['id' => 'P-DC-ISO-01', 'label' => 'DC isolator', 'rating' => 32, 'unit' => 'A', 'designation' => 'Type II 1000 VDC SPD'],
            ['id' => 'P-DC-FUSE-01', 'label' => 'String fuse', 'rating' => 20, 'unit' => 'A', 'designation' => 'gPV fuse'],
            ['id' => 'P-AC-CB-01', 'label' => 'AC circuit breaker', 'rating' => 25, 'unit' => 'A', 'designation' => 'Type II 400 VAC SPD'],
        ],
        'conflicts' => [
            ['code' => 'K001', 'severity' => 'error', 'category' => 'source', 'title' => 'Engineering source changed', 'message' => 'Technical Calculation or BOM/BOQ changed after this draft was generated.', 'action' => 'Synchronize the draft from the current engineering source.', 'objects' => ['inverter'], 'connectionKey' => null, 'acknowledged' => false],
            ['code' => 'K003', 'severity' => 'warning', 'category' => 'calculation', 'title' => 'Calculation review item', 'message' => 'Fixture warning for acknowledgement workflow.', 'action' => 'Review before approval.', 'objects' => [], 'connectionKey' => null, 'acknowledged' => false],
            ['code' => 'K051', 'severity' => 'notice', 'category' => 'quotation', 'title' => 'Quotation is downstream-stale', 'message' => 'Quotation snapshot is behind the latest BOQ.', 'action' => 'Regenerate quotation.', 'objects' => [], 'connectionKey' => null, 'acknowledged' => false],
        ],
        'acknowledgements' => [],
        'events' => [],
        'checked_at' => '2026-07-21T08:15:00+07:00',
    ],
    'editor' => [
        'version' => 14,
        'engine' => 'maxgraph-0.24',
        'space' => 'model',
        'display_mode' => 'clean',
        'paper' => ['mode' => 'as_drawn'],
        'viewport' => [
            'scale' => 0.72,
            'translate_x' => 45,
            'translate_y' => 90,
            'grid_size' => 20,
            'grid_enabled' => true,
            'snap_enabled' => true,
            'ortho_enabled' => false,
            'polar_enabled' => true,
            'osnap_enabled' => true,
            'otrack_enabled' => true,
            'dynamic_enabled' => true,
        ],
        'positions' => [],
        'routes' => [],
        'manual_nodes' => [],
        'manual_connections' => [],
        'manual_lines' => [],
        'manual_shapes' => [],
        'annotations' => [],
        'layers' => [],
    ],
];

$symbolRegistry = $app->make(SldSymbolRegistry::class);
$graph['nodes'] = array_map(static function (array $node) use ($symbolRegistry): array {
    $symbol = (string) ($node['symbol'] ?? '');
    $sourcePorts = $symbolRegistry->ports($symbol, true);
    $node['ports'] = $sourcePorts !== [] ? $sourcePorts : $symbolRegistry->ports($symbol);
    $node['symbol_standard'] = data_get($symbolRegistry->symbol($symbol), 'standard.status_label');
    $node['symbol_registry_version'] = '67L.1';

    return $node;
}, $graph['nodes']);
$electricalRuleChecker = $app->make(SldElectricalRuleChecker::class);
$graph['electrical_validation'] = $electricalRuleChecker->check($graph);
$graph['meta']['electrical_validation'] = $graph['electrical_validation']['summary'];
$graph['meta']['engineering_controller'] = $graph['engineering_controller']['summary'];
$graph['warnings'] = $electricalRuleChecker->toRevisionWarnings($graph['electrical_validation']);

$a3Svg = <<<'SVG'
<svg viewBox="0 0 1680 1188" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="E2E A3 drawing sheet">
    <rect width="1680" height="1188" fill="#ffffff"/>
    <rect x="35" y="35" width="1610" height="1118" fill="none" stroke="#0f2630" stroke-width="3"/>
    <text x="80" y="100" font-family="Arial, sans-serif" font-size="34" fill="#0f2630">NEXORIN E2E A3 SHEET</text>
</svg>
SVG;

$html = view('slds.editor', [
    'company' => $company,
    'project' => $project,
    'revision' => $revision,
    'graph' => $graph,
    'a3Svgs' => [$a3Svg],
    'paperSummary' => [
        'mode' => 'as_drawn',
        'sheet_count' => 1,
        'message' => 'Deterministic APP67K engineering-controller regression fixture.',
    ],
    'paperSheets' => [[
        'number' => 1,
        'title' => 'SLD Overview',
        'type' => 'overview',
        'node_count' => count($graph['nodes']),
    ]],
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

if (!str_contains($html, 'data-sld-editor')) {
    fwrite(STDERR, "APP67K fixture rendered HTML without the SLD root marker.\n");
    exit(2);
}

echo $html;
