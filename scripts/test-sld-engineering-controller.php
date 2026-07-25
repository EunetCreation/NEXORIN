<?php

declare(strict_types=1);

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Services\Sld\SldEngineeringController;
use App\Services\Sld\SldGraphBuilder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, "[APP67K] FAIL · {$message}\n");
        exit(1);
    }
};

$now = Carbon::parse('2026-07-21T08:00:00+07:00');
$project = new Project([
    'name' => 'APP67K Controller Test',
    'project_number' => 'K-TEST-001',
    'proposed_system' => 'on_grid',
]);
$project->id = 67001;
$project->exists = true;
$project->created_at = $now->copy()->subDays(10);
$project->updated_at = $now->copy()->subHours(2);

$calculation = new TechnicalCalculation([
    'system_type' => 'on_grid',
    'result_status' => 'verified',
    'module_power_wp' => 550,
    'module_vmp_v' => 41.8,
    'module_isc_a' => 13.9,
    'inverter_ac_power_kw' => 10,
    'inverter_max_dc_voltage_v' => 1000,
    'inverter_mppt_count' => 2,
    'dc_cable_area_mm2' => 6,
    'dc_cable_length_m' => 30,
    'ac_cable_area_mm2' => 10,
    'ac_cable_length_m' => 20,
    'cable_material' => 'copper',
    'results' => [
        'module_count' => 24,
        'string_count' => 2,
        'modules_per_string' => 12,
        'string_module_counts' => [12, 12],
        'installed_pv_kw' => 13.2,
        'inverter_count' => 1,
        'inverter_unit_ac_kw' => 10,
        'inverter_unit_rated_current_a' => 14.434,
        'total_inverter_ac_kw' => 10,
        'ac_nominal_voltage_v' => 400,
        'string_voc_cold_v' => 650,
        'string_vmp_hot_v' => 470,
        'inverter_string_assignments' => [[
            'inverter_index' => 1,
            'tag' => 'INV-01',
            'available_mppt_count' => 2,
            'used_mppt_count' => 2,
            'assigned_module_count' => 24,
            'assigned_dc_kw' => 13.2,
            'dc_ac_ratio' => 1.32,
            'strings' => [
                ['string_index' => 1, 'tag' => 'STR-01', 'modules' => 12, 'mppt_index' => 1, 'mppt_label' => 'MPPT-01', 'dc_kw' => 6.6],
                ['string_index' => 2, 'tag' => 'STR-02', 'modules' => 12, 'mppt_index' => 2, 'mppt_label' => 'MPPT-02', 'dc_kw' => 6.6],
            ],
        ]],
        'protection' => [
            'dc_isolator_a' => 32,
            'string_fuse_a' => 20,
            'dc_spd' => 'Type II 1000 VDC SPD',
            'ac_breaker_a' => 25,
            'ac_spd' => 'Type II 400 VAC SPD',
        ],
        'assumptions' => ['supply_phase' => 'three_phase'],
    ],
    'warnings' => [],
]);
$calculation->id = 67002;
$calculation->exists = true;
$calculation->calculated_at = $now->copy()->subHour();
$calculation->verified_at = $now->copy()->subMinutes(45);
$calculation->created_at = $now->copy()->subDays(3);
$calculation->updated_at = $now->copy()->subHour();

$recommendation = new SystemRecommendation([
    'recommended_system' => 'on_grid',
    'export_policy' => 'allowed',
]);
$recommendation->id = 67003;
$recommendation->exists = true;
$recommendation->created_at = $now->copy()->subDays(4);
$recommendation->updated_at = $now->copy()->subHours(2);

$bom = new BillOfMaterial([
    'technical_calculation_id' => $calculation->id,
    'status' => 'generated',
    'pricing_status' => 'priced',
    'grand_price_idr' => 250000000,
    'source_snapshot' => [
        'technical_calculation_id' => $calculation->id,
        'calculated_at' => $calculation->calculated_at->toISOString(),
    ],
]);
$bom->id = 67004;
$bom->exists = true;
$bom->created_at = $now->copy()->subMinutes(40);
$bom->updated_at = $now->copy()->subMinutes(30);

$itemCodes = [
    'PV-MODULE', 'DC-CABLE-PV1F', 'DC-ISOLATOR', 'DC-FUSE-SET', 'DC-SPD',
    'INV-ON-GRID', 'AC-CABLE-LV', 'AC-BREAKER', 'AC-SPD', 'AC-ISOLATOR-METER',
    'ACDB-PANEL', 'SMART-METER-ENERGY-METER',
];
$items = new Collection(array_map(static function (string $code, int $index) use ($bom, $now): BillOfMaterialItem {
    $item = new BillOfMaterialItem([
        'item_code' => $code,
        'item_name' => $code,
        'quantity' => 1,
        'unit' => 'set',
    ]);
    $item->id = 67100 + $index;
    $item->bill_of_material_id = $bom->id;
    $item->exists = true;
    $item->created_at = $now->copy()->subMinutes(35);
    $item->updated_at = $now->copy()->subMinutes(30);
    return $item;
}, $itemCodes, array_keys($itemCodes)));
$bom->setRelation('items', $items);

$financial = new FinancialAnalysis([
    'bill_of_material_id' => $bom->id,
    'project_capex_idr' => $bom->grand_price_idr,
    'status' => 'calculated',
]);
$financial->id = 67005;
$financial->exists = true;
$financial->created_at = $now->copy()->subMinutes(20);
$financial->updated_at = $now->copy()->subMinutes(20);

$quotation = new Quotation([
    'bill_of_material_id' => $bom->id,
    'financial_analysis_id' => $financial->id,
    'status' => 'draft',
]);
$quotation->id = 67006;
$quotation->exists = true;
$quotation->created_at = $now->copy()->subMinutes(10);
$quotation->updated_at = $now->copy()->subMinutes(10);

$project->setRelation('technicalCalculation', $calculation);
$project->setRelation('systemRecommendation', $recommendation);
$project->setRelation('billOfMaterial', $bom);
$project->setRelation('financialAnalysis', $financial);
$project->setRelation('quotation', $quotation);
$project->setRelation('loadAnalysis', null);
$project->setRelation('solarProfile', null);
$project->setRelation('roofSiteSurvey', null);

/** @var SldGraphBuilder $builder */
$builder = $app->make(SldGraphBuilder::class);
$graph = $builder->build($project);

/** @var SldEngineeringController $controller */
$controller = $app->make(SldEngineeringController::class);
$state = $controller->inspect($project, $graph);

$assert($state['status'] === 'review', 'a current source chain with unresolved protection duties must remain in review rather than claim synchronization.');
$assert($state['blocking'] === false, 'open protection-duty warnings must not block internal review.');
$assert(count($state['cable_schedule']) === 25, 'one-inverter SLD-REF-001 topology must expose twenty-five traceable cable rows.');
$assert(count($state['protection_schedule']) === 8, 'one-inverter APP67L.3-R4 topology must expose eight protection rows.');
$assert(data_get($graph, 'connections.0.cable_type') === 'PV1-F', 'controlled DC cable metadata must be bound');
$acFeeder = collect($graph['connections'])->firstWhere('id', 'C-AC-01-01');
$assert(data_get($acFeeder, 'cable_size') === '10', 'controlled AC cable size must be bound');
$assert(collect($graph['connections'])->where('network', 'dc_positive')->count() === 4, 'two strings must expose four controlled positive-polarity DC stages.');
$assert(collect($graph['connections'])->where('network', 'dc_negative')->count() === 4, 'two strings must expose four controlled negative-polarity DC stages.');
$assert(collect($state['conflicts'])->where('code', 'K030')->count() === 3, 'incomplete PCC and SPD protection duties must remain visible as three review warnings.');
$inverter = collect($graph['nodes'])->firstWhere('id', 'inverter_01');
$assert(data_get($inverter, 'engineering_binding.bom_item_codes.0') === 'INV-ON-GRID', 'physical inverter must bind to the system-specific BOM code');

$stale = $graph;
$stale['source_snapshot']['fingerprint'] = str_repeat('0', 64);
$staleState = $controller->inspect($project, $stale);
$assert($staleState['blocking'] === true, 'a stale source fingerprint must block controlled workflow');
$assert(collect($staleState['conflicts'])->contains(fn (array $finding): bool => $finding['code'] === 'K001'), 'stale source must report K001');

$mismatch = $graph;
$mismatch['connections'][0]['cable_size'] = '4';
$mismatchState = $controller->inspect($project, $mismatch);
$assert(collect($mismatchState['conflicts'])->contains(fn (array $finding): bool => $finding['code'] === 'K021'), 'physical cable schedule mismatch must report K021');

$originalPosition = data_get($graph, 'editor.positions.inverter_01');
$graph['editor']['positions']['inverter_01'] = array_merge((array) $originalPosition, ['x' => 420, 'y' => 240, 'w' => 220, 'h' => 160]);
$graph['editor']['positions'] = collect($graph['editor']['positions'])
    ->reject(fn (mixed $position, string $id): bool => str_starts_with($id, 'pv_string_'))
    ->all();
$graph['editor']['positions']['pv_group_01'] = ['x' => 80, 'y' => 120, 'w' => 160, 'h' => 240];
$graph['editor']['manual_lines'] = [['id' => 'manual-line-1', 'points' => [['x' => 20, 'y' => 20], ['x' => 140, 'y' => 20]]]];
$graph['editor']['manual_nodes'] = [['id' => 'manual-note-1', 'title' => 'Site note']];
$graph['editor']['manual_connections'] = [['id' => 'manual-wire-1', 'from' => 'manual-note-1', 'to' => 'inverter_01']];
$graph['editor']['routes'] = [
    'C-DC-01-01' => [['x' => 10, 'y' => 10], ['x' => 20, 'y' => 10]],
    'manual-wire-1' => [['x' => 20, 'y' => 20], ['x' => 420, 'y' => 240]],
];
$graph['editor']['label_placement']['positions'] = [
    'pv_group_01' => ['side' => 'bottom', 'x' => 80, 'y' => 380, 'w' => 120, 'h' => 48],
    'inverter_01' => ['side' => 'right', 'x' => 650, 'y' => 240, 'w' => 120, 'h' => 48],
];
$freshGraph = $builder->build($project);
$reconciled = $controller->reconcile($graph, $freshGraph, 991);
$assert(data_get($reconciled, 'editor.positions.inverter_01.x') === 420, 'source synchronization must preserve physical inverter layout');
$assert(data_get($reconciled, 'editor.positions.inverter_01.w') === 220, 'source synchronization must preserve physical inverter envelope width');
$assert(count(data_get($reconciled, 'editor.manual_lines', [])) === 1, 'source synchronization must preserve manual drafting lines');
$assert(count(data_get($reconciled, 'editor.manual_nodes', [])) === 1, 'source synchronization must preserve manual nodes');
$migratedStrings = collect(data_get($reconciled, 'editor.positions', []))->only(['pv_string_001', 'pv_string_002']);
$assert($migratedStrings->count() === 2, 'legacy aggregate PV group placement must migrate to physical string positions.');
$assert(abs((float) $migratedStrings->avg(fn (array $position): float => (float) $position['x'] + ((float) $position['w'] / 2)) - 160.0) < 0.001, 'migrated physical string cluster must retain the legacy PV group horizontal centre.');
$assert(abs((float) $migratedStrings->avg(fn (array $position): float => (float) $position['y'] + ((float) $position['h'] / 2)) - 240.0) < 0.001, 'migrated physical string cluster must retain the legacy PV group vertical centre.');
$assert(! array_key_exists('C-DC-01-01', data_get($reconciled, 'editor.routes', [])), 'obsolete sourced route evidence must be removed during topology synchronization.');
$assert(array_key_exists('manual-wire-1', data_get($reconciled, 'editor.routes', [])), 'manual wire route evidence must remain preserved.');
$assert(! array_key_exists('pv_group_01', data_get($reconciled, 'editor.label_placement.positions', [])), 'obsolete aggregate-node label placement must be removed.');
$assert(array_key_exists('inverter_01', data_get($reconciled, 'editor.label_placement.positions', [])), 'label placement for retained sourced equipment must remain preserved.');
$assert(data_get($reconciled, 'engineering_controller.events.0.action') === 'accept_engineering_source', 'source synchronization must create an audit event');

fwrite(STDOUT, "[SLD-REF-001 / APP67K] PHP PASS · physical string-to-MPPT reconciliation, paired cable authority, legacy PV-group placement migration, stale-source blocking, mismatch detection, and manual-layer preservation\n");
