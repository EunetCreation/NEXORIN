<?php

namespace App\Services\Report;

use App\Models\BillOfMaterialItem;
use App\Models\Project;
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
                'technicalCalculation',
                'billOfMaterial.items',
                'financialAnalysis',
                'quotation.items',
            ]);
        }

        $this->pages = [];
        $this->content = '';
        $this->newPage();

        $this->drawCover($project);
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

        $this->rect(0, self::PAGE_HEIGHT - 190, self::PAGE_WIDTH, 190, '0.02 0.15 0.22', true);
        $this->rect(0, self::PAGE_HEIGHT - 190, self::PAGE_WIDTH, 42, '0.03 0.43 0.39', true);
        $this->text(self::MARGIN, 765, 'NEXORIN', 24, 'F2', '1 1 1');
        $this->text(self::MARGIN, 742, 'Solar Engineering and Project Report', 11, 'F1', '0.76 0.96 0.94');
        $this->text(553, 765, 'PROJECT REPORT', 12, 'F2', '1 1 1', 'right');
        $this->text(553, 744, now()->format('d M Y'), 9, 'F1', '0.76 0.96 0.94', 'right');

        $this->y = 635;
        $this->text(self::MARGIN, $this->y, $this->value($project->name), 22, 'F2', '0.02 0.15 0.22');
        $this->y -= 20;
        $this->paragraph('Integrated technical, commercial, and financial summary generated from the NEXORIN project workflow.');
        $this->y -= 16;

        $this->twoColumnRows([
            ['Project number', $project->project_number],
            ['Company', $company?->name],
            ['Customer', $customer?->name],
            ['Building type', $this->label($project->building_type)],
            ['Proposed system', $this->label($project->proposed_system)],
            ['Project status', $this->label($project->status)],
            ['Site', $project->site_address],
            ['City', trim($this->value($project->city).' '.$this->value($project->province))],
        ]);

        $this->y -= 18;
        $this->rect(self::MARGIN, $this->y - 65, 511, 82, '0.92 0.97 0.97', true);
        $this->text(58, $this->y, 'Report scope', 11, 'F2', '0.02 0.15 0.22');
        $this->y -= 17;
        $this->paragraph('This report summarizes project data, load analysis, solar profile, roof survey, system recommendation, technical calculation, BOM/BOQ, financial analysis, quotation, assumptions, and warnings.');
        $this->y -= 24;
    }

    private function drawProjectProfile(Project $project): void
    {
        $this->sectionTitle('1. Customer and Project Information');

        $customer = $this->relation($project, 'customer');
        $this->twoColumnRows([
            ['Customer name', $customer?->name],
            ['Customer number', $customer?->customer_number],
            ['Email', $customer?->email],
            ['Phone', $customer?->phone],
            ['Project name', $project->name],
            ['Project number', $project->project_number],
            ['Address', $project->site_address],
            ['Coordinates', $this->coordinates($project->latitude, $project->longitude)],
        ]);
    }

    private function drawInputSummaries(Project $project): void
    {
        $this->sectionTitle('2. Input Data Summary');

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
        $this->sectionTitle('3. System Recommendation');

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
        $this->sectionTitle('4. Technical Calculation');

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

        if (! empty($calculation->warnings)) {
            $this->miniTitle('Technical warnings');
            foreach (array_slice($calculation->warnings, 0, 8) as $warning) {
                $this->bullet((string) $warning);
            }
        }
    }

    private function drawBomBoq(Project $project): void
    {
        $bom = $this->relation($project, 'billOfMaterial');
        $this->sectionTitle('5. BOM and BOQ Summary');

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
        $this->sectionTitle('6. Financial Analysis');

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
        $this->sectionTitle('7. Quotation Summary');

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
        $this->sectionTitle('8. Combined Warnings and Disclaimer');

        $warnings = $this->combinedWarnings($project);
        if ($warnings === []) {
            $this->paragraph('No major warnings were recorded by the generated modules. The result still requires engineering review before procurement or construction.');
        } else {
            foreach (array_slice($warnings, 0, 18) as $warning) {
                $this->bullet($warning);
            }
        }

        $this->miniTitle('Disclaimer');
        $this->paragraph('This report is a preliminary engineering and commercial document generated by NEXORIN. It supports decision-making but does not replace site verification, utility approval, structural assessment, commissioning, or professional engineering responsibility. All results must be reviewed by qualified personnel before procurement or construction.');
    }

    private function drawBomItems(mixed $items): void
    {
        $items = $items instanceof Collection ? $items : collect($items);

        if ($items->isEmpty()) {
            return;
        }

        $this->miniTitle('Major BOM items');
        $this->ensure(42);
        $this->tableRow(['Item', 'Qty', 'Unit', 'Line Price'], true);

        foreach ($items->take(12) as $item) {
            if (! $item instanceof BillOfMaterialItem) {
                continue;
            }

            $this->tableRow([
                $this->truncate($item->item_name, 42),
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
                $this->money($row['saving_idr'] ?? $row['annual_saving_idr'] ?? 0),
                $this->money($row['om_cost_idr'] ?? 0),
                $this->money($row['net_cashflow_idr'] ?? $row['cashflow_idr'] ?? 0),
            ]);
        }

        $this->y -= 8;
    }

    /** @param array<int, string> $cells */
    private function tableRow(array $cells, bool $header = false): void
    {
        $this->ensure(26);
        $height = 22;
        $fill = $header ? '0.88 0.94 0.94' : '1 1 1';
        $this->rect(self::MARGIN, $this->y - 16, 511, $height, $fill, true);
        $this->rect(self::MARGIN, $this->y - 16, 511, $height, '0.84 0.89 0.90', false);
        $font = $header ? 'F2' : 'F1';
        $color = '0.05 0.12 0.17';

        $x = [50, 310, 365, 553];
        $align = ['left', 'right', 'left', 'right'];
        foreach ($cells as $index => $cell) {
            $this->text($x[$index] ?? 50, $this->y - 6, (string) $cell, 8, $font, $color, $align[$index] ?? 'left');
        }
        $this->y -= $height;
    }

    /** @param array<int, array{0:string,1:mixed}> $rows */
    private function twoColumnRows(array $rows): void
    {
        $left = array_slice($rows, 0, (int) ceil(count($rows) / 2));
        $right = array_slice($rows, (int) ceil(count($rows) / 2));
        $count = max(count($left), count($right));

        for ($i = 0; $i < $count; $i++) {
            $this->ensure(20);
            $top = $this->y;
            if (isset($left[$i])) {
                $this->keyValue(50, $top, $left[$i][0], $left[$i][1], 222);
            }
            if (isset($right[$i])) {
                $this->keyValue(310, $top, $right[$i][0], $right[$i][1], 222);
            }
            $this->y -= 22;
        }

        $this->y -= 6;
    }

    private function keyValue(float $x, float $y, string $key, mixed $value, float $width): void
    {
        $this->text($x, $y, $key, 7.2, 'F2', '0.35 0.43 0.48');
        $display = $this->truncate($this->value($value), 38);
        $this->text($x, $y - 10, $display, 9, 'F1', '0.05 0.12 0.17');
        $this->line($x, $y - 15, $x + $width, $y - 15, '0.88 0.91 0.92');
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
                    $text = trim((string) $warning);
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
        $this->text(self::MARGIN, 25, 'Generated by NEXORIN - preliminary project report', 7.5, 'F1', '0.45 0.51 0.55');
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
        $text = trim(preg_replace('/\s+/', ' ', $this->value($text)) ?: '');

        if ($text === '') {
            return ['-'];
        }

        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
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
