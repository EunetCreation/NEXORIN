<?php

namespace App\Services\Report;

use App\Models\BillOfMaterialItem;
use App\Models\Project;
use App\Support\CalculationFormula;
use App\Support\CalculationWarning;
use App\Support\PhoneFormatter;
use DateTimeInterface;
use Illuminate\Support\Collection;

class ProjectReportPdfRenderer
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN = 42.0;
    private const BOTTOM_MARGIN = 56.0;

    /** @var array<int, string> */
    private array $pages = [];

    private string $content = '';

    private float $y = 0.0;

    private string $footerContext = '';

    public function render(Project $project): string
    {
        if ($project->exists) {
            $project->loadMissing([
                'company',
                'customer',
                'loadAnalysis.appliances',
                'loadAnalysis.electricityBills',
                'solarProfile.monthlyData',
                'roofSiteSurvey.surfaces',
                'systemRecommendation.scores',
                'technicalCalculation.activeOverrides.creator',
                'billOfMaterial.items',
                'financialAnalysis',
                'quotation.items',
            ]);
        }

        $this->pages = [];
        $this->content = '';
        $this->footerContext = $this->value($project->project_number ?: $project->name);
        $this->newPage();

        $this->drawCover($project);
        $this->newPage();
        $this->drawExecutiveSummary($project);
        $this->drawProjectProfile($project);
        $this->drawInputSummaries($project);
        $this->drawRecommendation($project);
        $this->drawTechnicalCalculation($project);
        $this->drawBomBoq($project);
        $this->drawFinancialAnalysis($project);
        $this->drawQuotationSummary($project);
        $this->drawWarningsAndDisclaimer($project);

        $this->finishPage();

        return $this->buildPdf();
    }

    public function filename(Project $project): string
    {
        $number = $project->project_number ?: 'project-'.$project->getKey();
        $safe = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $number) ?: 'project-report';

        return strtoupper(trim($safe, '-')).'-project-report.pdf';
    }

    private function drawCover(Project $project): void
    {
        $company = $this->relation($project, 'company');
        $customer = $this->relation($project, 'customer');
        $calculation = $this->relation($project, 'technicalCalculation');
        $analysis = $this->relation($project, 'financialAnalysis');
        $quotation = $this->relation($project, 'quotation');
        $bom = $this->relation($project, 'billOfMaterial');
        $result = is_array($calculation?->results ?? null) ? $calculation->results : [];
        $commercial = is_array($quotation?->commercial_summary ?? null) ? $quotation->commercial_summary : [];

        $this->rect(0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, '0.98 0.99 1', true);
        $this->rect(0, 650, self::PAGE_WIDTH, 192, '0.02 0.15 0.22', true);
        $this->rect(0, 650, 190, 192, '0.03 0.43 0.39', true);
        $this->rect(0, 650, self::PAGE_WIDTH, 18, '0.03 0.43 0.39', true);

        $this->text(self::MARGIN, 792, 'NEXORIN', 27, 'F2', '1 1 1');
        $this->text(self::MARGIN, 768, 'Solar Engineering and Project Report', 11, 'F1', '0.76 0.96 0.94');
        $this->text(553, 792, 'ENGINEERING REPORT', 12, 'F2', '1 1 1', 'right');
        $this->text(553, 772, 'Generated '.$this->dateTime(now()), 8.5, 'F1', '0.76 0.96 0.94', 'right');

        $this->y = 610;
        $this->text(self::MARGIN, $this->y, $this->value($project->name), 24, 'F2', '0.02 0.15 0.22');
        $this->y -= 23;
        $this->paragraph('Integrated engineering, commercial, and financial report for rooftop PV planning. This document consolidates workflow inputs, assumptions, formulas, warnings, overrides, and commercial outputs in one controlled report.');

        $this->y -= 12;
        $this->drawCoverMetaCard($project, $company?->name, $customer?->name);

        $this->y -= 14;
        $this->drawMetricGrid([
            ['label' => 'Installed PV', 'value' => $this->nullableNumber($result['installed_pv_kw'] ?? null, 2, ' kWp'), 'caption' => 'Effective design capacity'],
            ['label' => 'Annual Energy', 'value' => $this->nullableNumber($result['annual_energy_kwh'] ?? $analysis?->annual_energy_kwh, 0, ' kWh/year'), 'caption' => 'Estimated production'],
            ['label' => 'CAPEX', 'value' => $this->moneyOrPending($analysis?->project_capex_idr), 'caption' => 'Financial input'],
            ['label' => 'Grand Price', 'value' => $this->moneyOrPending($commercial['grand_total_idr'] ?? $bom?->grand_price_idr ?? null), 'caption' => 'Customer-facing amount'],
        ]);

        $this->y -= 12;
        $this->rect(self::MARGIN, $this->y - 82, 511, 92, '0.92 0.97 0.97', true);
        $this->rect(self::MARGIN, $this->y - 82, 511, 92, '0.78 0.86 0.86', false);
        $this->text(58, $this->y - 10, 'Document classification', 10.5, 'F2', '0.02 0.15 0.22');
        $this->y -= 28;
        $this->paragraph('Status: '.$this->reportStatus($project).'. Use this report for preliminary engineering review, proposal validation, and project decision support. It does not replace site verification, utility approval, structural assessment, commissioning, or professional engineering responsibility.');

        $this->y = 92;
        $this->text(self::MARGIN, $this->y, 'Prepared for', 8, 'F2', '0.45 0.51 0.55');
        $this->text(self::MARGIN, $this->y - 15, $this->value($customer?->name), 11, 'F2', '0.02 0.15 0.22');
        $this->text(553, $this->y, 'Prepared by', 8, 'F2', '0.45 0.51 0.55', 'right');
        $this->text(553, $this->y - 15, $this->value($company?->name ?: 'NEXORIN'), 11, 'F2', '0.02 0.15 0.22', 'right');
    }

    private function drawExecutiveSummary(Project $project): void
    {
        $this->sectionTitle('1. Executive Summary');

        $calculation = $this->relation($project, 'technicalCalculation');
        $bom = $this->relation($project, 'billOfMaterial');
        $analysis = $this->relation($project, 'financialAnalysis');
        $quotation = $this->relation($project, 'quotation');
        $result = is_array($calculation?->results ?? null) ? $calculation->results : [];
        $commercial = is_array($quotation?->commercial_summary ?? null) ? $quotation->commercial_summary : [];
        $warningRecords = $this->combinedWarningRecords($project);
        $severityCounts = $this->severityCounts($warningRecords);

        $this->drawMetricGrid([
            ['label' => 'System Type', 'value' => $this->label($calculation?->system_type ?? $project->proposed_system), 'caption' => 'Recommended or calculated configuration'],
            ['label' => 'PV Capacity', 'value' => $this->nullableNumber($result['installed_pv_kw'] ?? null, 2, ' kWp'), 'caption' => 'Installed DC capacity'],
            ['label' => 'Payback', 'value' => $this->nullableNumber($analysis?->simple_payback_years, 2, ' years'), 'caption' => 'Simple payback period'],
            ['label' => 'Report Health', 'value' => $this->healthLabel($severityCounts), 'caption' => 'Based on active warnings'],
        ]);

        $this->miniTitle('Document control');
        $this->twoColumnRows([
            ['Project number', $project->project_number],
            ['Project status', $this->label($project->status)],
            ['Engineering status', $calculation?->result_status_label],
            ['BOM/BOQ status', $bom ? $bom->status_label.' / '.$this->label($bom->pricing_status) : null],
            ['Financial status', $analysis?->status_label],
            ['Quotation status', $quotation?->status_label],
            ['Report revision', $this->reportRevision($quotation)],
            ['Generated at', $this->dateTime(now())],
        ]);

        $this->miniTitle('Executive interpretation');
        $this->paragraph($this->executiveInterpretation($project, $severityCounts));
        $this->drawSeveritySummary($severityCounts);
    }

    private function drawProjectProfile(Project $project): void
    {
        $this->sectionTitle('2. Customer and Project Information');

        $customer = $this->relation($project, 'customer');
        $this->twoColumnRows([
            ['Customer name', $customer?->name],
            ['Customer number', $customer?->customer_number],
            ['Email', $customer?->email],
            ['Phone', $this->formatPhone($customer?->phone)],
            ['Project name', $project->name],
            ['Project number', $project->project_number],
            ['Address', $project->site_address],
            ['Coordinates', $this->coordinates($project->latitude, $project->longitude)],
        ]);
    }

    private function drawInputSummaries(Project $project): void
    {
        $this->sectionTitle('3. Input Data Summary');

        $load = $this->relation($project, 'loadAnalysis');
        $loadSummary = $load ? $load->calculateSummary() : [];
        $this->miniTitle('Load Analysis');
        $this->twoColumnRows([
            ['Source mode', $this->label($load?->source_mode)],
            ['Supply phase', $this->label($load?->supply_phase)],
            ['Connected power', $this->nullableNumber($load?->connected_power_va, 0, ' VA')],
            ['Tariff group', $load?->tariff_group],
            ['Daily energy', $this->nullableNumber($loadSummary['daily_energy_kwh'] ?? null, 2, ' kWh/day')],
            ['Peak demand', $this->nullableNumber($loadSummary['peak_demand_kw'] ?? null, 2, ' kW')],
            ['Critical energy', $this->nullableNumber($loadSummary['critical_daily_energy_kwh'] ?? null, 2, ' kWh/day')],
            ['Day share', $this->nullableNumber($loadSummary['day_share_percent'] ?? null, 2, '%')],
        ]);

        $solar = $this->relation($project, 'solarProfile');
        $solarSummary = $solar ? $solar->calculateSummary($project) : [];
        $this->miniTitle('Location and Solar Profile');
        $this->twoColumnRows([
            ['Data source', $this->label($solar?->data_source)],
            ['Source name', $solar?->source_name],
            ['Quality status', $this->label($solar?->quality_status)],
            ['Elevation', $this->nullableNumber($solar?->elevation_m, 2, ' m')],
            ['Average PSH', $this->nullableNumber($solarSummary['average_psh'] ?? null, 2, ' h/day')],
            ['Average GHI', $this->nullableNumber($solarSummary['average_ghi'] ?? null, 2, ' kWh/m2/day')],
        ]);

        $roof = $this->relation($project, 'roofSiteSurvey');
        $roofSummary = $roof ? $roof->calculateSummary() : [];
        $this->miniTitle('Roof and Site Survey');
        $this->twoColumnRows([
            ['Survey date', $this->date($roof?->survey_date)],
            ['Quality status', $this->label($roof?->quality_status)],
            ['Surface count', $roofSummary['surface_count'] ?? null],
            ['Gross roof area', $this->nullableNumber($roofSummary['gross_area_m2'] ?? null, 2, ' m2')],
            ['Usable roof area', $this->nullableNumber($roofSummary['usable_area_m2'] ?? null, 2, ' m2')],
            ['Average shading', $this->nullableNumber($roofSummary['weighted_shading_percent'] ?? null, 2, '%')],
            ['Overall condition', $roofSummary['overall_condition'] ?? null],
            ['Structural review', $roof?->structural_review_status_label],
        ]);
    }

    private function drawRecommendation(Project $project): void
    {
        $recommendation = $this->relation($project, 'systemRecommendation');
        $this->sectionTitle('4. System Recommendation');

        if (! $recommendation) {
            $this->paragraph('System recommendation has not been generated for this project.');
            return;
        }

        $this->twoColumnRows([
            ['Recommended system', $recommendation->recommended_system_label],
            ['Engine recommendation', $recommendation->engine_recommended_system_label],
            ['Result status', $recommendation->result_status_label],
            ['Primary objective', $recommendation->primary_objective_label],
            ['On-grid score', $this->nullableNumber($recommendation->on_grid_score, 2, '')],
            ['Hybrid score', $this->nullableNumber($recommendation->hybrid_score, 2, '')],
            ['Decisiveness index', $this->nullableNumber($recommendation->decisiveness_index, 2, '')],
            ['Generated at', $this->dateTime($recommendation->generated_at)],
        ]);

        $reasons = $recommendation->reasons ?? [];
        if (is_array($reasons) && $reasons !== []) {
            $this->miniTitle('Reasons');
            foreach (array_slice($reasons, 0, 8) as $reason) {
                $this->bullet((string) $reason);
            }
            $this->y -= 5;
        }
    }

    private function drawTechnicalCalculation(Project $project): void
    {
        $calculation = $this->relation($project, 'technicalCalculation');
        $this->sectionTitle('5. Technical Calculation');

        if (! $calculation) {
            $this->paragraph('Technical calculation has not been run for this project.');
            return;
        }

        $result = $calculation->results ?? [];
        $this->twoColumnRows([
            ['Calculation status', $calculation->result_status_label],
            ['System type', $this->label($calculation->system_type ?? ($result['assumptions']['system_type'] ?? null))],
            ['Required PV', $this->nullableNumber($result['required_pv_kw'] ?? null, 2, ' kWp')],
            ['Installed PV', $this->nullableNumber($result['installed_pv_kw'] ?? null, 2, ' kWp')],
            ['Module count', $this->nullableNumber($result['module_count'] ?? null, 0, ' pcs')],
            ['Inverter count', $this->nullableNumber($result['inverter_count'] ?? null, 0, ' unit')],
            ['DC/AC ratio', $this->nullableNumber($result['dc_ac_ratio'] ?? null, 2, '')],
            ['Annual energy', $this->nullableNumber($result['annual_energy_kwh'] ?? null, 0, ' kWh/year')],
            ['Modules per string', $this->nullableNumber($result['modules_per_string'] ?? null, 0, ' pcs')],
            ['String count', $this->nullableNumber($result['string_count'] ?? null, 0, ' strings')],
            ['DC voltage drop', $this->nullableNumber($result['dc_voltage_drop_percent'] ?? null, 2, '%')],
            ['AC voltage drop', $this->nullableNumber($result['ac_voltage_drop_percent'] ?? null, 2, '%')],
            ['Battery capacity', $this->nullableNumber($result['battery_nominal_kwh'] ?? null, 2, ' kWh')],
            ['Calculated at', $this->dateTime($calculation->calculated_at)],
        ]);

        $this->drawCalculationBasis($result['formula_snapshot'] ?? []);
        $this->drawEngineeringAssumptions($result['assumption_snapshot'] ?? []);

        $overrides = $calculation->relationLoaded('activeOverrides')
            ? $calculation->activeOverrides
            : collect();

        if ($overrides->isNotEmpty()) {
            $this->miniTitle('Engineer overrides');
            foreach ($overrides->take(8) as $override) {
                $this->bullet(sprintf(
                    '%s changed from engine value %s to effective value %s. Reason: %s',
                    $override->label,
                    $override->formattedEngineValue(),
                    $override->formattedOverrideValue(),
                    $override->reason
                ));
            }
        } elseif (is_array($calculation->override_summary ?? null) && $calculation->override_summary !== []) {
            $this->miniTitle('Engineer overrides');
            foreach (array_slice($calculation->override_summary, 0, 8) as $override) {
                $label = $override['label'] ?? 'Calculation field';
                $engineValue = $override['engine_value'] ?? '-';
                $overrideValue = $override['override_value'] ?? '-';
                $unit = $override['unit'] ?? '';
                $reason = $override['reason'] ?? '-';
                $this->bullet(sprintf(
                    '%s changed from engine value %s %s to effective value %s %s. Reason: %s',
                    $label,
                    $engineValue,
                    $unit,
                    $overrideValue,
                    $unit,
                    $reason
                ));
            }
        }

        if (! empty($calculation->warnings)) {
            $this->miniTitle('Technical warnings');
            foreach (array_slice(CalculationWarning::normalizeList($calculation->warnings, 'technical_calculation'), 0, 8) as $warning) {
                $this->bullet(CalculationWarning::text($warning));
            }
        }
    }

    private function drawCalculationBasis(mixed $formulas): void
    {
        if (! is_array($formulas) || $formulas === []) {
            return;
        }

        $priority = [
            'required_pv_kw',
            'installed_pv_kw',
            'dc_ac_ratio',
            'annual_energy_kwh',
            'battery_nominal_kwh',
            'dc_voltage_drop_percent',
        ];

        $rows = [];
        foreach ($priority as $field) {
            $formula = $formulas[$field] ?? null;
            if (! is_array($formula)) {
                continue;
            }

            $label = (string) ($formula['label'] ?? 'Calculation formula');
            $display = (string) ($formula['display_formula'] ?? $formula['formula'] ?? '-');
            $result = CalculationFormula::formatValue(
                $formula['effective_result'] ?? $formula['result'] ?? null,
                (string) ($formula['unit'] ?? '')
            );
            $status = ! empty($formula['is_overridden']) ? ' | overridden effective value' : '';

            $rows[] = [$label, trim($display.' = '.$result.$status)];
        }

        if ($rows === []) {
            return;
        }

        $this->miniTitle('Calculation basis');
        $this->twoColumnRows($rows);
    }

    private function drawEngineeringAssumptions(mixed $assumptions): void
    {
        if (! is_array($assumptions) || $assumptions === []) {
            return;
        }

        $rows = [];
        foreach (array_slice($assumptions, 0, 10, true) as $assumption) {
            if (! is_array($assumption)) {
                continue;
            }

            $label = (string) ($assumption['label'] ?? 'Assumption');
            $value = $this->formatAssumptionValue($assumption);
            $source = $this->label($assumption['source'] ?? 'system');
            $description = (string) ($assumption['description'] ?? '');
            $rows[] = [
                $label,
                trim($value.' | '.$source),
            ];

            if ($description !== '') {
                $rows[] = ['Basis', $description];
            }
        }

        if ($rows === []) {
            return;
        }

        $this->miniTitle('Engineering assumptions');
        $this->twoColumnRows($rows);
    }

    /** @param array<string, mixed> $assumption */
    private function formatAssumptionValue(array $assumption): string
    {
        $value = $assumption['value'] ?? null;
        $unit = (string) ($assumption['unit'] ?? '');

        if ($value === null || $value === '') {
            return '-';
        }

        if (is_numeric($value)) {
            $formatted = rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
        } else {
            $formatted = (string) $value;
        }

        return trim($formatted.' '.$unit);
    }

    private function drawBomBoq(Project $project): void
    {
        $bom = $this->relation($project, 'billOfMaterial');
        $this->sectionTitle('6. BOM and BOQ Summary');

        if (! $bom) {
            $this->paragraph('BOM/BOQ has not been generated for this project.');
            return;
        }

        $this->twoColumnRows([
            ['BOM number', $bom->bom_number],
            ['BOM status', $bom->status_label],
            ['Pricing status', $this->label($bom->pricing_status)],
            ['Generated at', $this->dateTime($bom->generated_at)],
            ['Material cost subtotal', $this->money($bom->material_cost_subtotal_idr)],
            ['Material price subtotal', $this->money($bom->material_price_subtotal_idr)],
            ['Labor cost', $this->money($bom->labor_cost_idr)],
            ['Logistics cost', $this->money($bom->logistics_cost_idr)],
            ['Tax', $this->money($bom->tax_idr)],
            ['Discount', $this->money($bom->discount_idr)],
            ['Grand cost', $this->money($bom->grand_cost_idr)],
            ['Grand price', $this->money($bom->grand_price_idr)],
        ]);

        $this->drawBomItems($bom->items ?? collect());

        if (! empty($bom->warnings)) {
            $this->miniTitle('BOM/BOQ warnings');
            foreach (array_slice($bom->warnings, 0, 8) as $warning) {
                $this->bullet((string) $warning);
            }
        }
    }

    private function drawFinancialAnalysis(Project $project): void
    {
        $analysis = $this->relation($project, 'financialAnalysis');
        $this->sectionTitle('7. Financial Analysis');

        if (! $analysis) {
            $this->paragraph('Financial analysis has not been calculated for this project.');
            return;
        }

        $this->twoColumnRows([
            ['Status', $analysis->status_label],
            ['CAPEX', $this->money($analysis->project_capex_idr)],
            ['Annual energy', $this->nullableNumber($analysis->annual_energy_kwh, 0, ' kWh/year')],
            ['First year saving', $this->money($analysis->first_year_saving_idr)],
            ['Annual O&M', $this->money($analysis->annual_om_cost_idr)],
            ['Simple payback', $this->nullableNumber($analysis->simple_payback_years, 2, ' years')],
            ['Lifetime ROI', $this->nullableNumber($analysis->lifetime_roi_percent, 2, '%')],
            ['NPV', $this->money($analysis->npv_idr)],
            ['IRR', $this->nullableNumber($analysis->irr_percent, 2, '%')],
            ['LCOE', $this->nullableNumber($analysis->lcoe_idr_per_kwh, 2, ' IDR/kWh')],
            ['Lifetime', $this->nullableNumber($analysis->project_lifetime_years, 0, ' years')],
            ['Calculated at', $this->dateTime($analysis->calculated_at)],
        ]);

        $cashflows = is_array($analysis->cashflows) ? $analysis->cashflows : [];
        if ($cashflows !== []) {
            $this->ensure(66);
            $this->miniTitle('Cashflow Snapshot');
            $this->drawCashflowRows(array_slice($cashflows, 0, 8));
        }

        if (! empty($analysis->warnings)) {
            $this->miniTitle('Financial warnings');
            foreach (array_slice($analysis->warnings, 0, 8) as $warning) {
                $this->bullet((string) $warning);
            }
        }
    }

    private function drawQuotationSummary(Project $project): void
    {
        $quotation = $this->relation($project, 'quotation');
        $this->sectionTitle('8. Quotation Summary');

        if (! $quotation) {
            $this->paragraph('Quotation has not been generated for this project.');
            return;
        }

        $commercial = $quotation->commercial_summary ?? [];
        $this->twoColumnRows([
            ['Quote number', $quotation->quote_number],
            ['Revision', $quotation->revision_number],
            ['Status', $quotation->status_label],
            ['Valid until', $this->date($quotation->valid_until)],
            ['Grand total', $this->money($commercial['grand_total_idr'] ?? $quotation->items->sum('line_price_idr'))],
            ['Issued at', $this->dateTime($quotation->issued_at)],
        ]);

        $terms = $quotation->terms ?? [];
        if ($terms !== []) {
            $this->miniTitle('Commercial terms');
            foreach (['payment_terms' => 'Payment', 'delivery_terms' => 'Delivery', 'warranty_terms' => 'Warranty', 'exclusions' => 'Exclusions'] as $key => $label) {
                if (! empty($terms[$key])) {
                    $this->paragraph($label.': '.$terms[$key]);
                }
            }
        }
    }

    private function drawWarningsAndDisclaimer(Project $project): void
    {
        $this->ensure(260);
        $this->sectionTitle('9. Warning Matrix and Disclaimer');

        $warnings = $this->combinedWarningRecords($project);
        $severityCounts = $this->severityCounts($warnings);
        $this->drawSeveritySummary($severityCounts);

        if ($warnings === []) {
            $this->paragraph('No major warnings were recorded by the generated modules. The result still requires engineering review before procurement or construction.');
        } else {
            $this->ensure(210);
            $this->miniTitle('Warning matrix');
            foreach (array_slice($warnings, 0, 12) as $warning) {
                $this->warningPanel($warning);
            }
        }

        $this->miniTitle('Professional responsibility and limitation');
        $this->paragraph('This report is a preliminary engineering and commercial document generated by NEXORIN. It supports decision-making but does not replace site verification, utility approval, structural assessment, commissioning, or professional engineering responsibility. All results must be reviewed by qualified personnel before procurement or construction.');
        $this->drawSignatureBlock($project);
    }

    private function drawCoverMetaCard(Project $project, mixed $companyName, mixed $customerName): void
    {
        $this->ensure(76);
        $this->rect(self::MARGIN, $this->y - 66, 511, 76, '1 1 1', true);
        $this->rect(self::MARGIN, $this->y - 66, 511, 76, '0.84 0.89 0.90', false);
        $this->line(self::MARGIN, $this->y + 10, 553, $this->y + 10, '0.03 0.43 0.39');

        $this->text(58, $this->y - 8, 'Project', 7.8, 'F2', '0.45 0.51 0.55');
        $this->text(58, $this->y - 23, $this->value($project->project_number), 10.5, 'F2', '0.02 0.15 0.22');
        $this->text(58, $this->y - 39, $this->label($project->building_type).' | '.$this->label($project->proposed_system), 8.5, 'F1', '0.35 0.43 0.48');

        $this->text(222, $this->y - 8, 'Company', 7.8, 'F2', '0.45 0.51 0.55');
        $this->text(222, $this->y - 23, $this->value($companyName), 10.5, 'F2', '0.02 0.15 0.22');
        $this->text(222, $this->y - 39, 'Project owner data scope', 8.5, 'F1', '0.35 0.43 0.48');

        $this->text(386, $this->y - 8, 'Customer', 7.8, 'F2', '0.45 0.51 0.55');
        $this->text(386, $this->y - 23, $this->value($customerName), 10.5, 'F2', '0.02 0.15 0.22');
        $this->text(386, $this->y - 39, $this->value($project->city).' '.$this->value($project->province), 8.5, 'F1', '0.35 0.43 0.48');

        $this->y -= 82;
    }

    /** @param array<int, array{label:string,value:string,caption:string}> $cards */
    private function drawMetricGrid(array $cards): void
    {
        if ($cards === []) {
            return;
        }

        $columns = 4;
        $gap = 9.0;
        $width = (511 - ($gap * ($columns - 1))) / $columns;
        $height = 70.0;
        $x0 = self::MARGIN;

        foreach (array_chunk($cards, $columns) as $row) {
            $this->ensure($height + 8);
            $top = $this->y;

            foreach ($row as $index => $card) {
                $this->metricCard(
                    $x0 + (($width + $gap) * $index),
                    $top,
                    $width,
                    $height,
                    $card['label'],
                    $card['value'],
                    $card['caption']
                );
            }

            $this->y -= $height + 14;
        }
    }

    private function metricCard(float $x, float $top, float $width, float $height, string $label, string $value, string $caption): void
    {
        $this->rect($x, $top - $height, $width, $height, '1 1 1', true);
        $this->rect($x, $top - $height, $width, $height, '0.84 0.89 0.90', false);
        $this->rect($x, $top - 4, $width, 4, '0.03 0.43 0.39', true);
        $this->text($x + 8, $top - 17, $label, 7.4, 'F2', '0.45 0.51 0.55');

        $valueLines = $this->wrap($value, $this->charLimit($width - 16, 12));
        $this->text($x + 8, $top - 34, $valueLines[0] ?? '-', 12, 'F2', '0.02 0.15 0.22');

        if (isset($valueLines[1])) {
            $this->text($x + 8, $top - 47, $valueLines[1], 8.2, 'F1', '0.05 0.12 0.17');
            return;
        }

        $captionY = $top - 50;
        foreach (array_slice($this->wrap($caption, $this->charLimit($width - 16, 7.2)), 0, 2) as $line) {
            $this->text($x + 8, $captionY, $line, 7.2, 'F1', '0.35 0.43 0.48');
            $captionY -= 8;
        }
    }

    private function drawSeveritySummary(array $counts): void
    {
        $this->miniTitle('Warning severity summary');
        $this->drawMetricGrid([
            ['label' => 'Critical', 'value' => (string) ($counts['critical'] ?? 0), 'caption' => 'Must be resolved'],
            ['label' => 'Warning', 'value' => (string) ($counts['warning'] ?? 0), 'caption' => 'Requires review'],
            ['label' => 'Caution', 'value' => (string) ($counts['caution'] ?? 0), 'caption' => 'Check assumptions'],
            ['label' => 'Info', 'value' => (string) ($counts['info'] ?? 0), 'caption' => 'Advisory note'],
        ]);
    }

    /** @param array{code:string,severity:string,title:string,message:string,recommendation:string,source:string} $warning */
    private function warningPanel(array $warning): void
    {
        $height = 70.0;
        $messageLines = $this->wrap($warning['message'] ?? '-', 88);
        $recommendationLines = $this->wrap('Recommendation: '.($warning['recommendation'] ?? '-'), 88);
        $height = max($height, 28 + ((count($messageLines) + count($recommendationLines)) * 9));
        $this->ensure($height + 8);

        [$fill, $accent] = $this->severityPalette($warning['severity'] ?? 'info');
        $top = $this->y;
        $this->rect(self::MARGIN, $top - $height, 511, $height, $fill, true);
        $this->rect(self::MARGIN, $top - $height, 511, $height, '0.84 0.89 0.90', false);
        $this->rect(self::MARGIN, $top - $height, 6, $height, $accent, true);

        $this->text(56, $top - 16, '['.strtoupper($warning['severity'] ?? 'info').'] '.$this->value($warning['title'] ?? 'Project Notice'), 9.5, 'F2', '0.02 0.15 0.22');
        $this->text(553, $top - 16, $this->sourceLabel($warning['source'] ?? 'system'), 7.5, 'F2', '0.45 0.51 0.55', 'right');

        $lineY = $top - 31;
        foreach ($messageLines as $line) {
            $this->text(56, $lineY, $line, 8.2, 'F1', '0.28 0.34 0.38');
            $lineY -= 9;
        }

        foreach ($recommendationLines as $line) {
            $this->text(56, $lineY, $line, 8.1, 'F1', '0.35 0.43 0.48');
            $lineY -= 9;
        }

        $this->y -= $height + 8;
    }

    private function drawSignatureBlock(Project $project): void
    {
        $this->ensure(110);
        $this->miniTitle('Review and approval');

        $boxTop = $this->y;
        $boxWidth = 242.0;
        $boxHeight = 82.0;
        $leftX = self::MARGIN;
        $rightX = 311.0;

        foreach ([[$leftX, 'Prepared by', 'Sales / Engineering Representative'], [$rightX, 'Reviewed by', 'Qualified Engineer / Company Approver']] as $box) {
            [$x, $title, $role] = $box;
            $this->rect($x, $boxTop - $boxHeight, $boxWidth, $boxHeight, '1 1 1', true);
            $this->rect($x, $boxTop - $boxHeight, $boxWidth, $boxHeight, '0.84 0.89 0.90', false);
            $this->text($x + 10, $boxTop - 17, $title, 9, 'F2', '0.02 0.15 0.22');
            $this->text($x + 10, $boxTop - 33, $role, 7.8, 'F1', '0.45 0.51 0.55');
            $this->line($x + 10, $boxTop - 62, $x + $boxWidth - 10, $boxTop - 62, '0.45 0.51 0.55');
            $this->text($x + 10, $boxTop - 75, 'Name, signature, and date', 7.4, 'F1', '0.45 0.51 0.55');
        }

        $this->y -= $boxHeight + 12;
    }

    /** @return array<int, array{code:string,severity:string,title:string,message:string,recommendation:string,source:string}> */
    private function combinedWarningRecords(Project $project): array
    {
        $records = [];
        $roof = $this->relation($project, 'roofSiteSurvey');
        $recommendation = $this->relation($project, 'systemRecommendation');
        $calculation = $this->relation($project, 'technicalCalculation');
        $bom = $this->relation($project, 'billOfMaterial');
        $analysis = $this->relation($project, 'financialAnalysis');
        $quotation = $this->relation($project, 'quotation');

        foreach ([
            'roof_site_survey' => $roof?->calculateSummary()['warnings'] ?? [],
            'system_recommendation' => $recommendation?->warnings ?? [],
            'technical_calculation' => $calculation?->warnings ?? [],
            'bom_boq' => $bom?->warnings ?? [],
            'financial_analysis' => $analysis?->warnings ?? [],
            'quotation' => $quotation?->warnings ?? [],
        ] as $source => $group) {
            if (is_array($group)) {
                foreach (CalculationWarning::normalizeList($group, $source) as $warning) {
                    $records[] = $warning;
                }
            }
        }

        return CalculationWarning::unique($records);
    }

    /** @param array<int, array<string, mixed>> $warnings */
    private function severityCounts(array $warnings): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'caution' => 0, 'info' => 0];

        foreach ($warnings as $warning) {
            $severity = (string) ($warning['severity'] ?? 'info');
            if (! array_key_exists($severity, $counts)) {
                $severity = 'info';
            }
            $counts[$severity]++;
        }

        return $counts;
    }

    private function healthLabel(array $counts): string
    {
        if (($counts['critical'] ?? 0) > 0) {
            return 'Critical Review';
        }

        if (($counts['warning'] ?? 0) > 0) {
            return 'Review Required';
        }

        if (($counts['caution'] ?? 0) > 0) {
            return 'Caution';
        }

        return 'Clear';
    }

    private function executiveInterpretation(Project $project, array $severityCounts): string
    {
        $calculation = $this->relation($project, 'technicalCalculation');
        $bom = $this->relation($project, 'billOfMaterial');
        $analysis = $this->relation($project, 'financialAnalysis');
        $quotation = $this->relation($project, 'quotation');

        $parts = [];
        $parts[] = 'The project workflow currently indicates '.$this->healthLabel($severityCounts).' based on the active warning matrix.';
        $parts[] = $calculation ? 'Technical calculation status is '.$this->label($calculation->result_status_label).'.' : 'Technical calculation has not been completed.';
        $parts[] = $bom ? 'BOM/BOQ status is '.$this->label($bom->status_label).' with '.$this->label($bom->pricing_status).' pricing status.' : 'BOM/BOQ has not been generated.';
        $parts[] = $analysis ? 'Financial analysis is '.$this->label($analysis->status_label).' with simple payback of '.$this->nullableNumber($analysis->simple_payback_years, 2, ' years').'.' : 'Financial analysis has not been calculated.';
        $parts[] = $quotation ? 'Quotation status is '.$this->label($quotation->status_label).'.' : 'Quotation has not been generated.';

        return implode(' ', $parts);
    }

    private function reportStatus(Project $project): string
    {
        $calculation = $this->relation($project, 'technicalCalculation');
        $status = $calculation?->result_status_label ?: $this->label($project->status);

        return $this->value($status);
    }

    private function reportRevision(mixed $quotation): string
    {
        if (! $quotation) {
            return 'R0 - project report';
        }

        return 'R'.(int) ($quotation->revision_number ?? 0).' - aligned with quotation '.$this->value($quotation->quote_number ?? null);
    }

    private function moneyOrPending(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Pending';
        }

        return $this->money($value);
    }

    /** @return array{0:string,1:string} */
    private function severityPalette(string $severity): array
    {
        return match ($severity) {
            'critical' => ['0.99 0.94 0.94', '0.72 0.12 0.12'],
            'warning' => ['1 0.97 0.88', '0.80 0.40 0.05'],
            'caution' => ['0.96 0.98 0.90', '0.42 0.55 0.10'],
            default => ['0.92 0.97 0.97', '0.03 0.43 0.39'],
        };
    }

    private function sourceLabel(string $source): string
    {
        return $this->label($source);
    }

    private function drawBomItems(mixed $items): void
    {
        $items = $items instanceof Collection ? $items : collect($items);

        if ($items->isEmpty()) {
            return;
        }

        $this->ensure(66);
        $this->miniTitle('Major BOM items');
        $this->tableRow(['Item', 'Qty', 'Unit', 'Line Price'], true);

        foreach ($items->take(12) as $item) {
            if (! $item instanceof BillOfMaterialItem) {
                continue;
            }

            $this->tableRow([
                (string) $item->item_name,
                $this->number($item->quantity, 3),
                (string) $item->unit,
                $this->money($item->line_price_idr),
            ]);
        }

        $this->y -= 8;
    }

    /** @param array<int, array<string, mixed>> $cashflows */
    private function drawCashflowRows(array $cashflows): void
    {
        $this->ensure(42);
        $this->tableRow(['Year', 'Saving', 'O&M', 'Net Cashflow'], true);

        foreach ($cashflows as $row) {
            $this->tableRow([
                (string) ($row['year'] ?? '-'),
                $this->money($row['gross_saving_idr'] ?? $row['saving_idr'] ?? $row['annual_saving_idr'] ?? 0),
                $this->money($row['om_cost_idr'] ?? 0),
                $this->money($row['net_cashflow_idr'] ?? $row['cashflow_idr'] ?? 0),
            ]);
        }

        $this->y -= 8;
    }

    /** @param array<int, string> $cells */
    private function tableRow(array $cells, bool $header = false): void
    {
        $font = $header ? 'F2' : 'F1';
        $color = '0.05 0.12 0.17';
        $x = [50, 310, 365, 553];
        $widths = [235, 72, 80, 116];
        $align = ['left', 'right', 'left', 'right'];
        $lineSets = [];
        $maxLines = 1;

        foreach ($cells as $index => $cell) {
            $limit = $this->charLimit($widths[$index] ?? 100, 8);
            $lines = $this->wrap((string) $cell, $limit);
            $lineSets[$index] = $lines;
            $maxLines = max($maxLines, count($lines));
        }

        $height = max(22, 14 + ($maxLines * 9));
        $this->ensure($height + 4);
        $fill = $header ? '0.88 0.94 0.94' : '1 1 1';
        $this->rect(self::MARGIN, $this->y - $height + 6, 511, $height, $fill, true);
        $this->rect(self::MARGIN, $this->y - $height + 6, 511, $height, '0.84 0.89 0.90', false);

        foreach ($lineSets as $index => $lines) {
            $lineY = $this->y - 7;
            foreach ($lines as $line) {
                $this->text($x[$index] ?? 50, $lineY, $line, 8, $font, $color, $align[$index] ?? 'left');
                $lineY -= 9;
            }
        }

        $this->y -= $height;
    }

    /** @param array<int, array{0:string,1:mixed}> $rows */
    private function twoColumnRows(array $rows): void
    {
        $left = array_slice($rows, 0, (int) ceil(count($rows) / 2));
        $right = array_slice($rows, (int) ceil(count($rows) / 2));
        $count = max(count($left), count($right));
        $width = 222.0;

        for ($i = 0; $i < $count; $i++) {
            $height = max(
                isset($left[$i]) ? $this->keyValueHeight($left[$i][1], $width) : 0,
                isset($right[$i]) ? $this->keyValueHeight($right[$i][1], $width) : 0,
                26
            );

            $this->ensure($height + 4);
            $top = $this->y;

            if (isset($left[$i])) {
                $this->keyValue(50, $top, $left[$i][0], $left[$i][1], $width, $height);
            }

            if (isset($right[$i])) {
                $this->keyValue(310, $top, $right[$i][0], $right[$i][1], $width, $height);
            }

            $this->y -= $height;
        }

        $this->y -= 6;
    }

    private function keyValueHeight(mixed $value, float $width): float
    {
        $lineCount = count($this->wrap($this->value($value), $this->charLimit($width, 9)));

        return 15 + ($lineCount * 10);
    }

    private function keyValue(float $x, float $y, string $key, mixed $value, float $width, float $height): void
    {
        $this->text($x, $y, $key, 7.2, 'F2', '0.35 0.43 0.48');
        $lineY = $y - 10;

        foreach ($this->wrap($this->value($value), $this->charLimit($width, 9)) as $line) {
            $this->text($x, $lineY, $line, 9, 'F1', '0.05 0.12 0.17');
            $lineY -= 10;
        }

        $this->line($x, $y - $height + 4, $x + $width, $y - $height + 4, '0.88 0.91 0.92');
    }

    private function sectionTitle(string $title): void
    {
        $this->ensure(40);
        $this->text(self::MARGIN, $this->y, $title, 13, 'F2', '0.02 0.15 0.22');
        $this->line(self::MARGIN, $this->y - 8, 553, $this->y - 8, '0.78 0.86 0.86');
        $this->y -= 26;
    }

    private function miniTitle(string $title): void
    {
        $this->ensure(24);
        $this->text(self::MARGIN, $this->y, $title, 10.5, 'F2', '0.03 0.43 0.39');
        $this->y -= 17;
    }

    private function paragraph(string $text): void
    {
        foreach ($this->wrap($text, 96) as $line) {
            $this->ensure(14);
            $this->text(self::MARGIN, $this->y, $line, 8.5, 'F1', '0.28 0.34 0.38');
            $this->y -= 11;
        }
    }

    private function bullet(string $text): void
    {
        foreach ($this->wrap($text, 88) as $index => $line) {
            $this->ensure(14);
            $prefix = $index === 0 ? '- ' : '  ';
            $this->text(self::MARGIN, $this->y, $prefix.$line, 8.5, 'F1', '0.45 0.21 0.06');
            $this->y -= 11;
        }
    }

    /** @return array<int, string> */
    private function combinedWarnings(Project $project): array
    {
        $warnings = [];
        $roof = $this->relation($project, 'roofSiteSurvey');
        $recommendation = $this->relation($project, 'systemRecommendation');
        $calculation = $this->relation($project, 'technicalCalculation');
        $bom = $this->relation($project, 'billOfMaterial');
        $analysis = $this->relation($project, 'financialAnalysis');
        $quotation = $this->relation($project, 'quotation');

        foreach ([
            $roof?->calculateSummary()['warnings'] ?? [],
            $recommendation?->warnings ?? [],
            $calculation?->warnings ?? [],
            $bom?->warnings ?? [],
            $analysis?->warnings ?? [],
            $quotation?->warnings ?? [],
        ] as $group) {
            if (is_array($group)) {
                foreach ($group as $warning) {
                    $text = trim(CalculationWarning::text($warning));
                    if ($text !== '') {
                        $warnings[] = $text;
                    }
                }
            }
        }

        return array_values(array_unique($warnings));
    }


    private function relation(Project $project, string $name): mixed
    {
        return $project->relationLoaded($name) ? $project->getRelation($name) : null;
    }

    private function ensure(float $height): void
    {
        if ($this->y - $height < self::BOTTOM_MARGIN) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        if ($this->content !== '') {
            $this->finishPage();
        }

        $this->content = '';
        $this->y = self::PAGE_HEIGHT - self::MARGIN;
    }

    private function finishPage(): void
    {
        if ($this->content === '') {
            return;
        }

        $pageNo = count($this->pages) + 1;
        $this->line(self::MARGIN, 40, 553, 40, '0.86 0.90 0.91');
        $footer = 'Generated by NEXORIN - engineering project report';
        if ($this->footerContext !== '-') {
            $footer .= ' | '.$this->footerContext;
        }
        $this->text(self::MARGIN, 25, $footer, 7.5, 'F1', '0.45 0.51 0.55');
        $this->text(553, 25, 'Page '.$pageNo, 7.5, 'F1', '0.45 0.51 0.55', 'right');
        $this->pages[] = $this->content;
        $this->content = '';
    }

    private function text(float $x, float $y, string $text, float $size = 10, string $font = 'F1', string $color = '0.05 0.12 0.17', string $align = 'left'): void
    {
        $text = $this->pdfText($text);
        $width = strlen($text) * $size * 0.48;

        if ($align === 'right') {
            $x -= $width;
        }

        $this->content .= "q\n{$color} rg\nBT /{$font} {$size} Tf 1 0 0 1 ".$this->fmt($x).' '.$this->fmt($y)." Tm ({$this->escape($text)}) Tj ET\nQ\n";
    }

    private function rect(float $x, float $y, float $w, float $h, string $color, bool $fill): void
    {
        $operator = $fill ? 'f' : 'S';
        $colorOperator = $fill ? 'rg' : 'RG';
        $this->content .= "q\n{$color} {$colorOperator}\n".$this->fmt($x).' '.$this->fmt($y).' '.$this->fmt($w).' '.$this->fmt($h)." re {$operator}\nQ\n";
    }

    private function line(float $x1, float $y1, float $x2, float $y2, string $color): void
    {
        $this->content .= "q\n{$color} RG\n0.8 w\n".$this->fmt($x1).' '.$this->fmt($y1).' m '.$this->fmt($x2).' '.$this->fmt($y2)." l S\nQ\n";
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $limit): array
    {
        $limit = max(1, $limit);
        $text = trim(preg_replace('/\s+/', ' ', $this->value($text)) ?: '');

        if ($text === '') {
            return ['-'];
        }

        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (strlen($word) > $limit) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                foreach (str_split($word, $limit) as $chunk) {
                    $lines[] = $chunk;
                }

                continue;
            }

            $candidate = trim($current.' '.$word);

            if (strlen($candidate) > $limit && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function charLimit(float $width, float $fontSize): int
    {
        return max(8, (int) floor($width / ($fontSize * 0.48)));
    }

    private function money(mixed $value): string
    {
        return 'IDR '.number_format((float) $value, 0, '.', ',');
    }

    private function number(mixed $value, int $decimals): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimals, '.', ',');
    }

    private function nullableNumber(mixed $value, int $decimals, string $suffix): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $this->number($value, $decimals).$suffix;
    }

    private function label(mixed $value): string
    {
        $value = $this->value($value);

        return $value === '-' ? '-' : ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private function formatPhone(mixed $value): ?string
    {
        return PhoneFormatter::indonesia($value === null ? null : (string) $value);
    }

    private function value(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d M Y');
        }

        $value = trim((string) ($value ?? ''));

        return $value === '' ? '-' : $value;
    }

    private function date(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('d M Y') : $this->value($value);
    }

    private function dateTime(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('d M Y, H:i') : $this->value($value);
    }

    private function coordinates(mixed $lat, mixed $lng): string
    {
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return '-';
        }

        return $this->number($lat, 6).', '.$this->number($lng, 6);
    }

    private function truncate(string $text, int $length): string
    {
        $text = $this->value($text);

        return strlen($text) > $length ? substr($text, 0, $length - 3).'...' : $text;
    }

    private function pdfText(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = str_replace(['–', '—', '−'], '-', $text);
        $text = str_replace(['“', '”'], '"', $text);
        $text = str_replace(['‘', '’'], "'", $text);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function fmt(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function buildPdf(): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $kids = [];
        $objectId = 5;

        foreach ($this->pages as $pageContent) {
            $pageId = $objectId++;
            $contentId = $objectId++;
            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($pageContent)." >>\nstream\n{$pageContent}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
