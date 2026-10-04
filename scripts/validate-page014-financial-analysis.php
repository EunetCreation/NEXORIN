<?php

declare(strict_types=1);

use App\Models\BillOfMaterial;
use App\Models\FinancialAnalysis;
use App\Models\LoadAnalysis;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use App\Models\RoofSiteSurvey;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Services\Finance\FinancialAnalysisEngine;
use App\Services\Finance\FinancialBasisService;
use Illuminate\Support\Carbon;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = [];
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (! $condition) $failures[] = $message;
};

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Http/Controllers/FinancialAnalysisController.php');
$view = file_get_contents($root.'/resources/views/financial_analyses/show.blade.php');
$engineSource = file_get_contents($root.'/app/Services/Finance/FinancialAnalysisEngine.php');
$js = file_get_contents($root.'/public/js/nexorin-financial-analysis.js');

$assert(str_contains($controller, 'FinancialBasisService $financialBasis'), 'Controller must consume the shared FinancialBasisService.');
$assert(str_contains($controller, '$basisState = $this->financialBasis->assess($project);'), 'Show/calculate must assess the shared financial basis.');
$assert(! str_contains($view, 'Investment Signal'), 'Old Investment Signal UI must be absent.');
$assert(! str_contains($view, 'Favorable Economics'), 'Old favorable-economics verdict must be absent.');
$assert(! str_contains($view, 'Decision score'), 'Old decision score must be absent.');
$assert(! str_contains($view, 'Ready for Customer Release'), 'Financial analysis must not imply customer release readiness.');
$assert(! str_contains($view, 'Tariff vs LCOE'), 'LCOE must not be presented as a tariff pass/fail benchmark.');
$assert(! str_contains($engineSource, 'not engineer verified'), 'Unsupported engineer-verified warning language must be absent.');
$assert(str_contains($view, 'Modeled first-year benefit'), 'First-year result must be labeled as modeled.');
$assert(str_contains($view, 'Discount rate'), 'Discount rate assumption must retain its authoritative current name.');
$assert(str_contains($controller, 'Custom analysis CAPEX') && str_contains($view, 'A custom value changes this financial model only; it does not change the BOQ.'), 'CAPEX override semantics must remain distinguishable from BOQ truth.');
$assert(str_contains($view, 'does not model time-step import/export dispatch'), 'Current annual-energy/self-consumption limitation must be disclosed.');
$assert(str_contains($view, "asset('vendor/echarts/echarts.min.js')"), 'Page 014 must load the existing local ECharts asset.');
$assert(str_contains($js, 'smooth: false'), 'Cashflow line must not smooth stored annual data.');
$assert(! str_contains($js, 'scenario'), 'Page 014 chart must not synthesize scenario series.');

$makeProject = static function (): array {
    $project = new Project();
    $project->id = 101;
    $project->company_id = 7;
    $project->archived_at = null;

    $sourceTime = Carbon::parse('2026-08-15 08:00:00');
    foreach ([
        ['loadAnalysis', LoadAnalysis::class],
        ['solarProfile', ProjectSolarProfile::class],
        ['roofSiteSurvey', RoofSiteSurvey::class],
        ['systemRecommendation', SystemRecommendation::class],
    ] as [$relation, $class]) {
        $model = new $class();
        $model->project_id = 101;
        $model->updated_at = $sourceTime;
        $project->setRelation($relation, $model);
    }

    $calculation = new TechnicalCalculation();
    $calculation->id = 201;
    $calculation->company_id = 7;
    $calculation->project_id = 101;
    $calculation->result_status = 'calculated';
    $calculation->system_type = 'on_grid';
    $calculation->results = ['annual_energy_kwh' => 125000.0, 'installed_pv_kw' => 95.0];
    $calculation->calculated_at = Carbon::parse('2026-08-16 09:00:00');

    $bom = new BillOfMaterial();
    $bom->id = 301;
    $bom->company_id = 7;
    $bom->project_id = 101;
    $bom->technical_calculation_id = 201;
    $bom->pricing_status = 'priced';
    $bom->grand_price_idr = '1500000000';
    $bom->priced_at = Carbon::parse('2026-08-16 10:00:00');
    $bom->summary = ['annual_energy_kwh' => 999999.0];

    $project->setRelation('technicalCalculation', $calculation);
    $project->setRelation('billOfMaterial', $bom);

    return [$project, $calculation, $bom];
};

$basis = new FinancialBasisService();
[$project, $calculation, $bom] = $makeProject();
$assert($basis->assess($project)['key'] === 'ready', 'Ready project must resolve Financial Basis Ready.');

[$project, $calculation, $bom] = $makeProject();
$calculation->results = ['annual_energy_kwh' => 0];
$assert($basis->assess($project)['key'] === 'energy_required', 'Zero annual energy must block finance readiness.');

[$project, $calculation, $bom] = $makeProject();
$bom->technical_calculation_id = 999;
$assert($basis->assess($project)['key'] === 'boq_source_review', 'BOQ linked to another technical calculation must require source review.');

[$project, $calculation, $bom] = $makeProject();
$bom->pricing_status = 'not_priced';
$assert($basis->assess($project)['key'] === 'boq_pricing_required', 'Unpriced BOQ must block finance readiness.');

[$project, $calculation, $bom] = $makeProject();
$bom->priced_at = null;
$assert($basis->assess($project)['key'] === 'boq_pricing_review', 'Priced BOQ without pricing timestamp must require review.');

[$project, $calculation, $bom] = $makeProject();
$project->loadAnalysis->updated_at = Carbon::parse('2026-08-17 09:00:00');
$assert($basis->assess($project)['key'] === 'technical_stale', 'Changed upstream engineering source must stale the technical finance basis.');

[$project, $calculation, $bom] = $makeProject();
$project->archived_at = Carbon::parse('2026-08-17 09:00:00');
$assert($basis->assess($project)['key'] === 'project_archived', 'Archived project must not be calculation-ready.');

[$project, $calculation, $bom] = $makeProject();
$payload = (new FinancialAnalysisEngine())->calculate($project, $calculation, $bom, ['self_consumption_percent' => 80]);
$assert($payload['summary']['annual_energy_kwh'] === 125000.0, 'Finance annual energy must come from the technical result.');
$calculation->results = ['installed_pv_kw' => 95.0];
$fallbackPayload = (new FinancialAnalysisEngine())->calculate($project, $calculation, $bom, ['self_consumption_percent' => 80]);
$assert($fallbackPayload['summary']['annual_energy_kwh'] === 0.0, 'Finance must not silently fall back to BOQ summary annual energy.');

[$project, $calculation, $bom] = $makeProject();
$payload = (new FinancialAnalysisEngine())->calculate($project, $calculation, $bom);
$analysis = new FinancialAnalysis();
$analysis->company_id = 7;
$analysis->project_id = 101;
$analysis->technical_calculation_id = 201;
$analysis->bill_of_material_id = 301;
$analysis->currency = 'IDR';
$analysis->source_snapshot = $payload['source_snapshot'];
$assert($analysis->sourceFreshness($project)['state'] === 'current', 'Matching source snapshot must be current.');

$legacy = clone $analysis;
$legacy->source_snapshot = ['technical_calculation_id' => 201];
$assert($legacy->sourceFreshness($project)['state'] === 'review_required', 'Incomplete legacy source snapshot must require review.');

$changedLink = clone $analysis;
$bom->technical_calculation_id = 999;
$assert($changedLink->sourceFreshness($project)['state'] === 'source_changed', 'Changed BOQ technical linkage must stale the stored financial result.');

if ($failures !== []) {
    fwrite(STDERR, "Page 014 validation FAILED ({$checks} checks)\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

fwrite(STDOUT, "Page 014 validation PASS ({$checks}/{$checks})\n");
