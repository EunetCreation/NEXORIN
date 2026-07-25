<?php

declare(strict_types=1);

use App\Models\BillOfMaterial;
use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Services\Calculation\TechnicalCalculationEngine;
use App\Services\Sld\SldEngineeringController;
use App\Services\Sld\SldGraphBuilder;
use App\Services\Sld\SldPdfRenderer;
use App\Services\Sld\SldSvgRenderer;
use App\Services\Sld\SldSymbolRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, "[APP67L.2] PHP FAIL · {$message}\n");
        exit(1);
    }
};

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

/** @var TechnicalCalculationEngine $engine */
$engine = $app->make(TechnicalCalculationEngine::class);
$result = $engine->calculate(new Project(), $input, $sources);
$assert($result['module_count'] === 96, 'Baseline calculation must retain 96 modules.');
$assert($result['inverter_count'] === 2, 'Baseline calculation must select two inverter units.');
$assert($result['string_module_counts'] === [16, 16, 16, 16, 16, 16], 'String allocation must be six balanced 16-module strings.');

$now = Carbon::parse('2026-07-22T09:00:00+07:00');
$project = new Project([
    'project_number' => 'APP67L2-TEST',
    'name' => 'APP67L.2 Professional Topology',
    'proposed_system' => 'on_grid',
]);
$project->id = 67201;
$project->exists = true;
$project->created_at = $now->copy()->subDays(2);
$project->updated_at = $now->copy()->subHours(2);

$calculation = new TechnicalCalculation(array_merge($input, [
    'result_status' => 'verified',
    'results' => $result,
    'warnings' => [],
]));
$calculation->id = 67202;
$calculation->exists = true;
$calculation->calculated_at = $now->copy()->subHour();
$calculation->verified_at = $now->copy()->subMinutes(50);
$calculation->created_at = $now->copy()->subDay();
$calculation->updated_at = $now->copy()->subHour();

$recommendation = new SystemRecommendation([
    'recommended_system' => 'on_grid',
    'export_policy' => 'zero_export',
]);
$recommendation->id = 67203;
$recommendation->exists = true;
$recommendation->created_at = $now->copy()->subDay();
$recommendation->updated_at = $now->copy()->subHours(2);

$project->setRelation('technicalCalculation', $calculation);
$project->setRelation('systemRecommendation', $recommendation);
$project->setRelation('billOfMaterial', null);
$project->setRelation('financialAnalysis', null);
$project->setRelation('quotation', null);
$project->setRelation('loadAnalysis', null);
$project->setRelation('solarProfile', null);
$project->setRelation('roofSiteSurvey', null);

/** @var SldGraphBuilder $builder */
$builder = $app->make(SldGraphBuilder::class);
$graph = $builder->build($project);
$nodes = collect($graph['nodes']);
$connections = collect($graph['connections']);
$ids = $nodes->pluck('id')->all();

$assert($graph['schema_version'] === 'nexorin-sld-v1.9.0', 'Schema version must remain nexorin-sld-v1.9.0.');
$assert(data_get($graph, 'meta.topology_profile') === 'professional_physical', 'Topology profile must be professional_physical.');
$assert(data_get($graph, 'meta.topology_version') === '67L.2-R4', 'Topology version must identify APP67L.2-R4.');
$assert(data_get($graph, 'meta.physical_equipment_count') === 25, 'R4 professional topology must contain 25 physical nodes.');
$assert(data_get($graph, 'meta.physical_connection_count') === 45, 'R4 professional topology must contain 45 graph connections.');
$assert(data_get($graph, 'electrical_validation.summary.total') === 0, 'R4 professional topology must generate zero electrical findings.');

foreach ([1,2] as $index) {
    $suffix=sprintf('%02d',$index);
    foreach (["pv_group_{$suffix}","dc_isolator_{$suffix}","dc_spd_{$suffix}","inverter_{$suffix}","ac_breaker_{$suffix}","ac_isolator_{$suffix}"] as $id) {
        $assert(in_array($id,$ids,true), "Topology must contain {$id}.");
    }
    $inverter=$nodes->firstWhere('id',"inverter_{$suffix}");
    $assert(($inverter['assigned_module_count']??null)===48, "INV-{$suffix} must receive 48 modules.");
    $assert(count($inverter['string_assignments']??[])===3, "INV-{$suffix} must expose three one-string-per-MPPT assignments.");
    $assert(array_column($inverter['ports']??[],'id')===['DC_IN','AC_OUT','PE','COM','CTRL'], "INV-{$suffix} must expose power, PE, communication and direct-control ports.");
    $assert($connections->contains(fn(array $wire):bool=>$wire['id']==="C-DC-SPD-{$suffix}" && $wire['from']==="dc_isolator_{$suffix}" && $wire['to']==="dc_spd_{$suffix}"), "DC SPD {$suffix} must be a parallel switched branch.");
    $assert($connections->contains(fn(array $wire):bool=>$wire['id']==="C-CTRL-PPC-INV-{$suffix}" && $wire['to']==="inverter_{$suffix}"), "PPC must directly control INV-{$suffix}.");
}
$assert($nodes->where('symbol','dc_fuse')->isEmpty(), 'External string fuses must not be asserted for one string per MPPT before product-specific justification.');
$assert(! in_array('dc_bus_01',$ids,true) && ! in_array('dc_bus_02',$ids,true), 'Ghost IF-DC/DC-bus devices must be removed.');
foreach (['ac_combiner_01','pcc_breaker_01','ct_01','pcc_disconnect_01','meter_01','export_relay_01','grid_01','ac_spd_backup_01','ac_spd_01','pe_bus_01','ground_rod_01','data_logger_01','plant_controller_01'] as $id) {
    $assert(in_array($id,$ids,true), "Shared topology must contain {$id}.");
}
$assert($connections->contains(fn(array $wire):bool=>$wire['id']==='C-AC-MAIN-01' && $wire['to']==='pcc_breaker_01'),'Main AC feeder must enter QF-PCC-01.');
$assert($connections->contains(fn(array $wire):bool=>$wire['id']==='C-AC-CT-01' && $wire['from']==='pcc_breaker_01' && $wire['to']==='ct_01'),'Main AC feeder must pass through CT-01.');
$assert($connections->contains(fn(array $wire):bool=>$wire['id']==='C-AC-DISC-01' && $wire['to']==='pcc_disconnect_01'),'Main AC feeder must include QS-PCC-01.');
$assert($connections->contains(fn(array $wire):bool=>$wire['id']==='C-AC-PCC-01' && $wire['from']==='pcc_disconnect_01' && $wire['to']==='grid_01'),'PCC disconnect must feed the utility interface.');
$assert(! $connections->contains(fn(array $wire):bool=>$wire['network']==='ac_power' && ($wire['from']==='meter_01'||$wire['to']==='meter_01')),'CT-operated meter must not sit in the primary feeder.');
$assert($connections->contains(fn(array $wire):bool=>$wire['id']==='C-CTRL-RPR-TRIP-01' && $wire['to']==='pcc_breaker_01'),'Independent reverse-power relay must hardwire-trip the PCC breaker.');

/** @var SldEngineeringController $controller */
$controller=$app->make(SldEngineeringController::class);
$cables=collect($controller->cableSchedule($project));
$protections=collect($controller->protectionSchedule($project));
$assert($cables->count()===53,'Cable schedule must contain 53 individual/cross-referenced rows.');
$assert($protections->count()===13,'Protection schedule must contain 13 rows.');
$assert(! $cables->contains(fn(array $row):bool=>str_contains(strtoupper(json_encode($row)),'IF-DC')),'Cable schedule must contain no IF-DC reference.');
$assert($cables->where('string_tag','STR-01')->count()>=2,'STR-01 must remain traceable through both cable segments.');
$assert($cables->contains(fn(array $row):bool=>$row['wire_id']==='C-COM-PPC-01' && $row['cable_type']==='Ethernet Cat6'),'PPC Ethernet link must use Cat6 metadata.');
$assert(data_get($protections->firstWhere('node_id','ac_breaker_01'),'rating')===50.0,'Inverter feeder breaker must be preliminarily sized at 50 A.');
$assert(data_get($protections->firstWhere('node_id','pcc_breaker_01'),'rating')===100.0,'PCC main breaker must be preliminarily sized at 100 A.');

$positions=data_get($graph,'editor.positions',[]);
$assert(count($positions)===25,'Default layout must contain one position per physical node.');

fwrite(STDOUT,"[APP67L.2-R4] PHP PASS · 25 nodes, 45 graph connections, corrected PCC main path, meter outside feeder, independent reverse-power hardwired trip, optional string-fuse policy, 53 cable rows, 13 protection rows, and zero electrical findings\n");
