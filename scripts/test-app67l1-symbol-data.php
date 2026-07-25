<?php

declare(strict_types=1);

use App\Models\BillOfMaterial;
use App\Models\Project;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Services\Calculation\TechnicalCalculationEngine;
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
        fwrite(STDERR, "[APP67L.1] PHP FAIL · {$message}\n");
        exit(1);
    }
};

/** @var SldSymbolRegistry $registry */
$registry = $app->make(SldSymbolRegistry::class);
$symbols = $registry->symbols();
$assert(count($symbols) === 61, 'The controlled registry must contain 61 symbols.');
$assert(count(array_filter($symbols, static fn (array $symbol): bool => ($symbol['palette'] ?? false) === true)) === 60, 'The palette must publish 60 symbols.');
$assert(($registry->all()['version'] ?? null) === '67L.1', 'Registry version must be 67L.1.');
$assert(array_column($registry->ports('dc_isolator'), 'id') === ['DC_IN', 'DC_OUT'], 'DC isolator ports must be explicit.');
$assert(array_column($registry->ports('dc_spd'), 'id') === ['DC_TAP', 'PE'], 'DC SPD must be a bus tap with PE.');
$assert(array_column($registry->ports('ac_breaker'), 'id') === ['AC_IN', 'AC_OUT'], 'AC breaker ports must be explicit.');
$assert(array_column($registry->ports('ac_spd'), 'id') === ['AC_TAP', 'PE'], 'AC SPD must be a bus tap with PE.');
$assert(array_column($registry->ports('pe_bus'), 'id') === ['PE_IN', 'PE_OUT'], 'PE bus terminals must be explicit.');

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
$assert($result['module_count'] === 96, 'Baseline must retain exactly 96 physical modules.');
$assert($result['inverter_count'] === 2, 'Baseline must select two physical inverter units.');
$assert($result['string_module_counts'] === [16, 16, 16, 16, 16, 16], 'Baseline must use six balanced 16-module strings.');
$assert(array_sum($result['string_module_counts']) === 96, 'String architecture must not invent or lose modules.');
$assert($result['used_mppt_inputs'] === 6 && $result['available_mppt_inputs'] === 8, 'MPPT utilization must be 6 of 8 physical inputs.');
$assert(array_column($result['inverter_string_assignments'], 'assigned_module_count') === [48, 48], 'Each inverter must receive 48 modules.');
$assert(array_column($result['inverter_string_assignments'], 'used_mppt_count') === [3, 3], 'Each inverter must use three MPPT inputs.');
$assert(abs((float) $result['inverter_string_assignments'][0]['dc_ac_ratio'] - 1.056) < 0.001, 'INV-01 DC/AC ratio must match the physical allocation.');
$assert(abs((float) $result['inverter_string_assignments'][1]['dc_ac_ratio'] - 1.056) < 0.001, 'INV-02 DC/AC ratio must match the physical allocation.');

$now = Carbon::parse('2026-07-22T08:00:00+07:00');
$project = new Project(['project_number' => 'APP67L1-TEST', 'name' => 'APP67L.1 Baseline', 'proposed_system' => 'on_grid']);
$project->id = 67101;
$project->exists = true;
$project->created_at = $now->copy()->subDays(2);
$project->updated_at = $now->copy()->subHour();

$calculation = new TechnicalCalculation(array_merge($input, [
    'result_status' => 'verified',
    'results' => $result,
    'warnings' => [],
]));
$calculation->id = 67102;
$calculation->exists = true;
$calculation->calculated_at = $now->copy()->subMinutes(30);
$calculation->verified_at = $now->copy()->subMinutes(25);
$calculation->created_at = $now->copy()->subDay();
$calculation->updated_at = $now->copy()->subMinutes(30);

$recommendation = new SystemRecommendation(['recommended_system' => 'on_grid', 'export_policy' => 'zero_export']);
$recommendation->id = 67103;
$recommendation->exists = true;
$recommendation->created_at = $now->copy()->subDay();
$recommendation->updated_at = $now->copy()->subHour();

$project->setRelation('technicalCalculation', $calculation);
$project->setRelation('systemRecommendation', $recommendation);
$emptyBom = new BillOfMaterial();
$emptyBom->setRelation('items', collect());
$project->setRelation('billOfMaterial', $emptyBom);
$project->setRelation('financialAnalysis', null);
$project->setRelation('quotation', null);
$project->setRelation('loadAnalysis', null);
$project->setRelation('solarProfile', null);
$project->setRelation('roofSiteSurvey', null);

/** @var SldGraphBuilder $builder */
$builder = $app->make(SldGraphBuilder::class);
$graph = $builder->build($project);
$nodeIds = array_column($graph['nodes'], 'id');
$assert(data_get($graph, 'meta.topology_profile') === 'professional_physical', 'APP67L.1 data must feed the APP67L.2 physical topology profile.');
$assert(data_get($graph, 'meta.summary.physical_inverter_count') === 2, 'Generated graph must expose two physical inverter units.');
$assert(in_array('inverter_01', $nodeIds, true) && in_array('inverter_02', $nodeIds, true), 'Generated graph must contain both physical inverter nodes.');
$assert(! in_array('inverter', $nodeIds, true), 'Professional topology must not retain the legacy aggregate inverter node.');

foreach (['inverter_01', 'inverter_02'] as $inverterId) {
    $inverter = collect($graph['nodes'])->firstWhere('id', $inverterId);
    $assert(is_array($inverter), "Generated graph must contain {$inverterId}.");
    $assert(($inverter['equipment_count'] ?? null) === 1, "{$inverterId} must represent one physical unit.");
    $assert(abs((float) ($inverter['rated_power'] ?? 0) - 25.0) < 0.001, "{$inverterId} unit rating must be 25 kW.");
    $assert(abs((float) ($inverter['aggregate_power'] ?? 0) - 50.0) < 0.001, "{$inverterId} must retain the 50 kW project aggregate reference.");
    $assert(abs((float) ($inverter['rated_voltage'] ?? 0) - 400.0) < 0.001, "{$inverterId} AC voltage must be populated.");
    $assert((float) ($inverter['rated_current'] ?? 0) > 0, "{$inverterId} rated current must be populated.");
    $assert(($inverter['assigned_module_count'] ?? null) === 48, "{$inverterId} must receive 48 modules.");
    $assert(count($inverter['string_assignments'] ?? []) === 3, "{$inverterId} must expose three string assignments.");
    $assert(array_column($inverter['ports'] ?? [], 'id') === ['DC_IN', 'AC_OUT', 'PE', 'COM', 'CTRL'], "{$inverterId} must expose on-grid power, protective-earth, communication, and direct-control ports without battery or backup ports.");
}

foreach (['dc_isolator_01', 'dc_spd_01', 'ac_breaker_01', 'ac_isolator_01', 'ac_spd_01', 'pe_bus_01', 'data_logger_01', 'plant_controller_01'] as $physicalId) {
    $assert(in_array($physicalId, $nodeIds, true), "Generated graph must include {$physicalId}.");
}
$assert(data_get($graph, 'electrical_validation.summary.status') === 'pass', 'Professional graph must pass electrical validation.');
$assert(data_get($graph, 'electrical_validation.summary.total') === 0, 'Professional graph must not create electrical findings.');

/** @var SldSvgRenderer $svgRenderer */
$svgRenderer = $app->make(SldSvgRenderer::class);
$svgSet = $svgRenderer->renderSet($graph);
$svgUpper = mb_strtoupper(implode("\n", $svgSet['pages'] ?? []));
foreach (['QS-DC-01', 'SPD-DC-01', 'QF-AC-01', 'QS-AC-01', 'QF-PCC-01', 'QS-PCC-01', 'RPR-01', 'SPD-AC-01', 'PE-BUS-01', 'DL-01', 'PPC-01'] as $tag) {
    $assert(str_contains($svgUpper, $tag), "Professional SVG set must render {$tag}.");
}
$assert(count($svgSet['pages'] ?? []) === 6, 'Professional topology must render the six-sheet APP67L.3-R5 drawing set.');

$revision = new App\Models\ProjectSldRevision(['revision' => 1, 'status' => 'draft', 'graph' => $graph]);
/** @var SldPdfRenderer $pdfRenderer */
$pdfRenderer = $app->make(SldPdfRenderer::class);
$pdf = $pdfRenderer->render($project, $revision);
$assert(str_starts_with($pdf, '%PDF-1.4'), 'Vector PDF renderer must produce a valid PDF header.');
$assert(strlen($pdf) > 1500, 'Vector PDF renderer must contain a non-trivial drawing payload.');

fwrite(STDOUT, "[APP67L.1] PHP PASS · 61 symbols, 60 palette items, balanced 96-module allocation, physical inverter ratings, on-grid port profile, SVG/PDF rendering\n");
