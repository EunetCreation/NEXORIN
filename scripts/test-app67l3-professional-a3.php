<?php

declare(strict_types=1);

use App\Models\BillOfMaterial;
use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Services\Calculation\TechnicalCalculationEngine;
use App\Services\Sld\SldDocumentControl;
use App\Services\Sld\SldEngineeringController;
use App\Services\Sld\SldGraphBuilder;
use App\Services\Sld\SldPaperComposer;
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
        fwrite(STDERR, "[APP67L.4] PHP FAIL · {$message}\n");
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
    'project_number' => 'NX-2026-0042',
    'name' => 'NEXORIN Smart PV Demonstration Project',
    'proposed_system' => 'on_grid',
    'site_address' => 'Jl. Engineering No. 42',
    'city' => 'Bandung',
    'province' => 'Jawa Barat',
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
$project->setRelation('company', null);
$project->setRelation('customer', null);
$project->setRelation('assignedEngineer', null);
$project->setRelation('assignedSales', null);

/** @var SldGraphBuilder $builder */
$builder = $app->make(SldGraphBuilder::class);
$graph = $builder->build($project);
$actor = new User(['name' => 'NEXORIN Engineer']);
$actor->id = 67001;
$actor->exists = true;
/** @var SldDocumentControl $documentControl */
$documentControl = $app->make(SldDocumentControl::class);
$documentMetadata = $documentControl->build($project, 4, $actor, [
    'drawing_number_prefix' => 'NX-2026-0042-E-SLD',
    'drawing_title' => 'PV single-line diagram and engineering schedules',
    'client_name' => 'PT Demonstration Client',
    'site_location' => 'Bandung, Jawa Barat',
], 'draft');
$graph = $documentControl->stampGraph($graph, $documentMetadata);
$nodes = collect($graph['nodes']);
$connections = collect($graph['connections']);
$nodeIds = $nodes->pluck('id')->all();

$assert($graph['schema_version'] === 'nexorin-sld-v1.9.0', 'Graph schema must remain nexorin-sld-v1.9.0.');
$assert(data_get($graph, 'meta.topology_profile') === 'professional_physical', 'Topology profile must remain professional_physical.');
$assert(data_get($graph, 'meta.topology_version') === '67L.2-R4', 'Topology version must identify the R4 engineering-integrity model.');
$assert(data_get($graph, 'meta.physical_equipment_count') === 25, 'R4 fixture must contain 25 controlled equipment nodes.');
$assert(data_get($graph, 'meta.physical_connection_count') === 45, 'R4 fixture must contain 45 port-aware graph connections.');
$assert(data_get($graph, 'electrical_validation.summary.total') === 0, 'R4 topology must create zero deterministic electrical findings.');
$assert(data_get($graph, 'electrical_validation.summary.connections_checked') === 45, 'Electrical checker must inspect all 45 graph connections.');

foreach (['pcc_breaker_01', 'ct_01', 'pcc_disconnect_01', 'meter_01', 'export_relay_01', 'ac_spd_backup_01', 'ac_spd_01'] as $id) {
    $assert(in_array($id, $nodeIds, true), "R4 topology must contain {$id}.");
}
$assert(! in_array('if_dc_01', $nodeIds, true) && ! in_array('IF-DC-01', $nodeIds, true), 'Ghost IF-DC device must not exist.');
$assert($nodes->where('symbol', 'dc_fuse')->isEmpty(), 'External string-fuse devices must not be asserted for one string per MPPT without final justification.');

$mainPath = [
    ['C-AC-MAIN-01', 'ac_combiner_01', 'pcc_breaker_01'],
    ['C-AC-CT-01', 'pcc_breaker_01', 'ct_01'],
    ['C-AC-DISC-01', 'ct_01', 'pcc_disconnect_01'],
    ['C-AC-PCC-01', 'pcc_disconnect_01', 'grid_01'],
];
foreach ($mainPath as [$id, $from, $to]) {
    $assert($connections->contains(fn (array $wire): bool => $wire['id'] === $id && $wire['from'] === $from && $wire['to'] === $to && $wire['network'] === 'ac_power'), "Main feeder segment {$id} must be correct.");
}
$assert(! $connections->contains(fn (array $wire): bool => $wire['network'] === 'ac_power' && ($wire['from'] === 'meter_01' || $wire['to'] === 'meter_01')), 'CT-operated meter must remain outside the primary power path.');
$assert($connections->contains(fn (array $wire): bool => $wire['id'] === 'C-CTRL-RPR-TRIP-01' && $wire['from'] === 'export_relay_01' && $wire['to'] === 'pcc_breaker_01' && $wire['network'] === 'control'), 'Independent reverse-power relay must hardwire-trip QF-PCC-01.');
$assert($connections->contains(fn (array $wire): bool => $wire['id'] === 'C-AC-SPD-BACKUP-01' && $wire['from'] === 'ac_combiner_01' && $wire['to'] === 'ac_spd_backup_01'), 'AC SPD branch must start at the combiner bus through dedicated backup protection.');
$assert($connections->contains(fn (array $wire): bool => $wire['id'] === 'C-AC-SPD-01' && $wire['from'] === 'ac_spd_backup_01' && $wire['to'] === 'ac_spd_01'), 'AC SPD must be a protected parallel branch.');

foreach ([1, 2] as $index) {
    $suffix = sprintf('%02d', $index);
    $assert($connections->contains(fn (array $wire): bool => $wire['id'] === "C-DC-SPD-{$suffix}" && $wire['from'] === "dc_isolator_{$suffix}" && $wire['to'] === "dc_spd_{$suffix}"), "DC SPD {$suffix} must remain a parallel branch from the switched MPPT assembly.");
    $assert($connections->contains(fn (array $wire): bool => $wire['id'] === "C-CTRL-PPC-INV-{$suffix}" && $wire['from'] === 'plant_controller_01' && $wire['to'] === "inverter_{$suffix}"), "PPC must issue a direct power-limit command to INV-{$suffix}.");
}
foreach (['C-MEAS-CT-MTR-S1', 'C-MEAS-CT-MTR-S2', 'C-MEAS-CT-RPR-S1', 'C-MEAS-CT-RPR-S2', 'C-MEAS-MTR-VL-01', 'C-MEAS-MTR-VN-01', 'C-MEAS-RPR-VL-01', 'C-MEAS-RPR-VN-01'] as $id) {
    $assert($connections->contains(fn (array $wire): bool => $wire['id'] === $id), "Metering/protection reference {$id} must exist.");
}

/** @var SldEngineeringController $controller */
$controller = $app->make(SldEngineeringController::class);
$cables = collect($controller->cableSchedule($project));
$protections = collect($controller->protectionSchedule($project));
$assert($cables->count() === 53, 'R4 cable schedule must contain 53 individually traceable rows.');
$assert($protections->count() === 13, 'R4 protection schedule must contain 13 controlled devices.');
$assert(! $cables->contains(fn (array $row): bool => str_contains(strtoupper(json_encode($row)), 'IF-DC')), 'Cable schedule must not contain IF-DC.');
$assert(! $cables->contains(fn (array $row): bool => str_contains(strtoupper((string) ($row['wire_id'] ?? '')), 'FU-DC')), 'Cable schedule must not use aggregate FU-DC tags.');
foreach (['STR-01', 'STR-02', 'STR-03', 'STR-04', 'STR-05', 'STR-06'] as $stringTag) {
    $assert($cables->contains(fn (array $row): bool => ($row['from'] ?? null) === $stringTag), "Cable schedule must include the array-side row for {$stringTag}.");
    $assert($cables->contains(fn (array $row): bool => ($row['string_tag'] ?? null) === $stringTag && str_contains((string) ($row['to'] ?? ''), 'MPPT-')), "Cable schedule must include the inverter-side MPPT row for {$stringTag}.");
}
$assert($cables->contains(fn (array $row): bool => str_starts_with((string) ($row['wire_id'] ?? ''), 'C-DC-01-STR-') && str_contains((string) ($row['size_display'] ?? ''), '2 x 1C x 6')), 'PV string cable notation must use two single-core conductors.');
$assert($cables->contains(fn (array $row): bool => ($row['wire_id'] ?? '') === 'C-CTRL-RPR-TRIP-01' && ($row['voltage_class'] ?? '') === 'AUX SUPPLY TBC'), 'Hardwired trip circuit must not assert SELV before auxiliary supply selection.');
$assert($cables->contains(fn (array $row): bool => $row['wire_id'] === 'C-COM-PPC-01' && $row['cable_type'] === 'Ethernet Cat6' && str_contains((string) ($row['size_display'] ?? ''), '4 twisted pairs')), 'Data logger to PPC link must use Cat6 with four twisted pairs.');
$assert(! $cables->contains(fn (array $row): bool => str_contains(strtolower((string) ($row['cable_type'] ?? '')), 'ethernet') && (int) ($row['cores'] ?? 0) === 2), 'Ethernet must never be represented as generic 2C cable.');
$assert(! $protections->contains(fn (array $row): bool => str_contains(strtolower((string) ($row['label'] ?? '')), 'string fuse')), 'Protection schedule must not claim external string fuses before product-specific justification.');
$assert(data_get($protections->firstWhere('node_id', 'ac_breaker_01'), 'rating') === 50.0, '25 kW inverter feeder breaker must use the preliminary 50 A sizing result.');
$assert(data_get($protections->firstWhere('node_id', 'pcc_breaker_01'), 'rating') === 100.0, 'PCC main breaker must use the preliminary 100 A sizing result.');
$assert(data_get($nodes->firstWhere('id', 'ac_combiner_01'), 'busbar_rating_a') === 125.0, 'AC combiner bus must include preliminary current margin at 125 A.');

$assert(data_get($graph, 'meta.summary.ac_earthing_system') === 'TBC', 'AC earthing system must be explicitly TBC.');
$assert(data_get($graph, 'meta.summary.pcc_fault_level_ka') === null, 'PCC fault level must remain null/TBC until utility data exists.');
$assert(data_get($graph, 'meta.summary.annual_energy_status') === 'preliminary_energy_model_reference_tbc', 'Annual energy must carry a preliminary/reference-TBC status.');
$assert(data_get($graph, 'meta.summary.voltage_drop_status') === 'preliminary_until_route_lengths_confirmed', 'Voltage drop must remain preliminary until final routes are confirmed.');
$assert(data_get($graph, 'meta.summary.engineering_release_status') === 'internal_review_not_for_construction', 'Release status must be internal review, not for construction.');

/** @var SldPaperComposer $paperComposer */
$paperComposer = $app->make(SldPaperComposer::class);
$composition = $paperComposer->compose($graph);
$assert(data_get($composition, 'summary.composition_version') === '67L.4-FINAL', 'Paper composition must identify APP67L.4-FINAL.');
$assert(data_get($composition, 'summary.sheet_count') === 6, 'APP67L.4 must use six A3 sheets to preserve schedule readability.');
$assert(array_column($composition['sheets'], 'type') === ['professional_sld', 'control_schematic', 'string_schedule', 'cable_schedule', 'cable_schedule', 'protection_schedule'], 'APP67L.4 sheet sequence must be deterministic.');
foreach ($composition['sheets'] as $sheet) {
    $assert(($sheet['paper'] ?? null) === 'A3' && ($sheet['orientation'] ?? null) === 'landscape', 'Every APP67L.4 sheet must be A3 landscape.');
}

/** @var SldSvgRenderer $svgRenderer */
$svgRenderer = $app->make(SldSvgRenderer::class);
$set = $svgRenderer->renderSet($graph);
$assert(count($set['pages']) === 6, 'SVG renderer must produce six standalone sheets.');
$paperSvg = implode('', $set['pages']);
foreach ($set['pages'] as $page) {
    $assert(str_starts_with($page, '<svg') && str_ends_with(trim($page), '</svg>'), 'Each SVG page must be standalone and well-formed.');
    $assert(! str_contains($page, 'ENGINEERING FINALIZATION'), 'APP67L.4 must not claim engineering finalization.');
    $assert(str_contains($page, 'NOT FOR CONSTRUCTION'), 'Every draft APP67L.4 title block must state not for construction.');
}
$assert(str_contains($set['pages'][0], 'QF-PCC-01') && str_contains($set['pages'][0], 'QS-PCC-01'), 'Main SLD must show PCC breaker and lockable disconnect.');
$assert(str_contains($set['pages'][0], 'METER OUTSIDE PRIMARY FEEDER'), 'Main SLD must explicitly keep the meter outside the feeder.');
$assert(str_contains($set['pages'][1], 'HARDWIRED TRIP') && str_contains($set['pages'][1], 'REVERSE-POWER RELAY'), 'Control sheet must show independent hardwired reverse-power trip.');
$assert(str_contains($set['pages'][2], 'PRELIM CALC PASS') && str_contains($set['pages'][2], 'PRODUCT SELECTION TBC'), 'String sheet must distinguish preliminary calculation from product verification.');
$assert(! str_contains(implode('', $set['pages']), '67,645'), 'A3 package must not display unsupported precision for annual energy.');
$assert(! str_contains(implode('', $set['pages']), 'IF-DC'), 'A3 package must not contain ghost IF-DC references.');
$assert(str_contains($set['pages'][3].$set['pages'][4], 'C-DC-01-STR-01-A') && str_contains($set['pages'][3].$set['pages'][4], 'INV-01:MPPT-01'), 'Cable sheets must preserve string-to-MPPT cross references.');
$assert(str_contains($set['pages'][5], 'PCC fault level') && str_contains($set['pages'][5], 'AC earthing system'), 'Protection sheet must expose release-blocking engineering gaps.');
$assert(str_contains($set['pages'][0], 'PVA-01') && ! str_contains($set['pages'][0], 'LOCKABLE 2P DC SWITCH'), 'Main SLD must use compact collision-safe column labels.');
$assert(str_contains($set['pages'][0], 'METERING / EXPORT TRIP') && ! str_contains($set['pages'][0], 'REVERSE-POWER FAILSAFE'), 'Main SLD must use a compact cross-reference and avoid an unverified fail-safe claim.');
$assert(str_contains($set['pages'][1], 'AUXILIARY SUPPLY TBC') && str_contains($set['pages'][1], 'FAIL-SAFE PHILOSOPHY IS NOT YET VERIFIED'), 'Control sheet must expose the preliminary trip philosophy without label collisions.');
$assert(str_contains($set['pages'][0], 'FILLED DOT: CONNECTED JUNCTION'), 'Rebuilt legend must retain junction meaning.');
$assert(str_contains($set['pages'][0], 'SPD-DC-01 · SHUNT TO PE') && str_contains($set['pages'][0], 'SPD-DC-02 · SHUNT TO PE') && ! str_contains($set['pages'][0], '3 PARALLEL SHUNT CHANNELS · PE'), 'DC SPD annotations must use opaque collision-safe bands.');
$assert(str_contains($set['pages'][0], 'EARTHING SYSTEM, PE SIZES AND FAULT LOOP: TBC') && str_contains($set['pages'][0], 'EARTH RESISTANCE: CONFIRM BY SITE TEST') && str_contains($set['pages'][0], '>GND-01</text>'), 'Earthing devices and notes must use deterministic print-safe label stacks.');
$assert(str_contains($set['pages'][0], 'CT DATA TBC') && ! str_contains($set['pages'][0], 'DETAIL S2 / S6'), 'CT callout must not collide with the PCC isolator label.');
$assert(str_contains($set['pages'][0], 'data-layout-grid="pcc-fixed-r58"')
    && str_contains($set['pages'][0], 'data-paper-route="pcc-main-01"')
    && str_contains($set['pages'][0], 'data-paper-route="pcc-main-05"'),
    'Main SLD must use the fixed R5.8 PCC drafting grid and deterministic route segments.');
$assert(substr_count($set['pages'][0], 'class="sld-junction"') < 40,
    'Main SLD must reserve junction dots for actual branches instead of decorating every feeder segment.');
$assert(str_contains($set['pages'][0], 'data-paper-route="ac-spd-tap"')
    && str_contains($set['pages'][0], 'data-paper-route="ac-spd-pe"'),
    'AC SPD branch must remain a dedicated parallel shunt with explicit PE termination.');
$assert(str_contains($set['pages'][1], 'data-layout-grid="control-fixed-r58"')
    && str_contains($set['pages'][1], 'data-paper-route="hardwired-export-trip-short"'),
    'Control sheet must use the fixed grid and short direct breaker trip path.');
$assert(str_contains($set['pages'][1], 'data-paper-terminal-strip="PPC-01"')
    && str_contains($set['pages'][1], '>MTR</text>')
    && str_contains($set['pages'][1], '>RPR</text>')
    && str_contains($set['pages'][1], '>CTRL</text>')
    && str_contains($set['pages'][1], '>COM</text>'),
    'PPC inputs and outputs must terminate on an attached named terminal strip.');
$assert(str_contains($set['pages'][1], 'data-paper-route="meter-to-ppc-strip"')
    && str_contains($set['pages'][1], 'data-paper-route="relay-to-ppc-strip"')
    && str_contains($set['pages'][1], 'data-paper-route="ppc-command-grid-inv1"')
    && str_contains($set['pages'][1], 'data-paper-route="dl-monitor-grid-inv1"'),
    'Meter, relay, command and monitoring circuits must use separate deterministic grid lanes.');
$assert(! str_contains($set['pages'][1], 'data-paper-route="meter-modbus-ppc"')
    && ! str_contains($set['pages'][1], 'data-paper-route="dl-monitor-trunk"'),
    'R5.8 must remove the former floating PPC routes and shared monitoring trunk.');
$assert(str_contains($paperSvg, 'aria-label="Production controlled title block"')
    && str_contains($paperSvg, 'NOT FOR CONSTRUCTION')
    && str_contains($paperSvg, 'NX-2026-0042-E-SLD-001')
    && str_contains($paperSvg, 'PT Demonstration Client'),
    'Every professional sheet must use dynamic APP67L.4 production document control.');
$assert(! str_contains($paperSvg, 'R5.4 PRINT READABILITY')
    && ! str_contains($paperSvg, 'R5.7 FINAL CLEARANCE'),
    'R5.8 package must not retain obsolete revision captions.');
$assert(str_contains($set['pages'][3], 'CONFIRM L1/L2/L3/N/PE') && str_contains($set['pages'][4], 'CONFIRM L1/L2/L3/N/PE'),
    'Cable schedule footnotes must remain split into readable lines on both sheets.');
$assert(str_contains($set['pages'][1], '<rect x="55" y="66" width="1570"') && str_contains($set['pages'][5], '<rect x="55" y="66" width="1570"'),
    'Non-main sheets must retain the full A3 drawing width.');
$assert(! str_contains($paperSvg, 'class="sld-device-tag">DC SPD</text>')
    && ! str_contains($paperSvg, 'class="sld-device-title">Wh</text>')
    && ! str_contains($paperSvg, 'class="sld-device-tag">PPC</text>'),
    'Paper-space symbols must not contain redundant microtext.');
$assert(str_contains($set['pages'][5], '67L.4-FINAL - A3 - NTS'),
    'Protection sheet must carry the APP67L.4 production document-control identifier.');


$revision = new ProjectSldRevision([
    'revision' => 4,
    'status' => 'draft',
    'issue_status' => 'draft',
    'drawing_number_prefix' => 'NX-2026-0042-E-SLD',
    'document_metadata' => $documentMetadata,
    'graph' => $graph,
]);
$revision->id = 67204;
$revision->exists = true;
$revision->generated_at = $now;
/** @var SldPdfRenderer $pdfRenderer */
$pdfRenderer = $app->make(SldPdfRenderer::class);
$pdf = $pdfRenderer->render($project, $revision);
$assert(str_starts_with($pdf, '%PDF-1.4'), 'Fallback export must be a PDF 1.4 document.');
$assert(substr_count($pdf, '/Type /Page ') === 6, 'Fallback PDF must contain six A3 pages.');
$assert(str_contains($pdf, '67L.4-FINAL') && str_contains($pdf, 'DRAFT / NOT FOR CONSTRUCTION'), 'Fallback PDF must preserve APP67L.4 issue and status language.');

fwrite(STDOUT, "[APP67L.4-FINAL] PHP PASS · production-drawing-workflow six-sheet drawing, 25 nodes, 45 graph connections, 53 traceable cable rows, corrected PCC feeder and meter topology, lockable PCC isolation, preliminary independent RPR hardwired trip path, optional string-fuse policy, six A3 sheets, preliminary status controls, open engineering gaps, and zero deterministic electrical findings\n");
