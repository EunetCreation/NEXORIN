<?php

namespace App\Support;

use App\Models\Project;
use App\Models\TechnicalCalculation;

class ProjectCockpit
{
    /**
     * Build a project-level command center summary for the cockpit page.
     *
     * @return array<string, mixed>
     */
    public static function build(Project $project): array
    {
        $steps = self::steps($project);
        $warnings = self::warnings($project);
        $severityCounts = self::severityCounts($warnings);
        $completedSteps = collect($steps)->whereIn('state', ['complete', 'locked'])->count();
        $completionPercent = count($steps) > 0
            ? (int) round(($completedSteps / count($steps)) * 100)
            : 0;

        return [
            'completion_percent' => $completionPercent,
            'completed_steps' => $completedSteps,
            'total_steps' => count($steps),
            'steps' => $steps,
            'next_step' => self::nextStep($steps),
            'engineering_summary' => self::engineeringSummary($project),
            'commercial_summary' => self::commercialSummary($project),
            'financial_summary' => self::financialSummary($project),
            'warning_records' => $warnings,
            'warning_counts' => $severityCounts,
            'health' => self::health($severityCounts, $completionPercent),
            'charts' => self::charts($project),
            'insights' => self::insights($project, $severityCounts, $completionPercent),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function steps(Project $project): array
    {
        $load = $project->loadAnalysis;
        $solar = $project->solarProfile;
        $roof = $project->roofSiteSurvey;
        $recommendation = $project->systemRecommendation;
        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;
        $financial = $project->financialAnalysis;
        $quotation = $project->quotation;

        return [
            [
                'key' => 'project',
                'label' => 'Project',
                'caption' => 'Customer and site profile',
                'state' => 'complete',
                'status' => $project->status_label,
                'metric' => $project->project_number,
            ],
            [
                'key' => 'load',
                'label' => 'Load',
                'caption' => 'Bills and equipment profile',
                'state' => self::loadState($project),
                'status' => $load ? 'Recorded' : 'Missing',
                'metric' => $load ? self::loadMetric($project) : 'No load data',
            ],
            [
                'key' => 'solar',
                'label' => 'Solar',
                'caption' => 'Location and irradiation data',
                'state' => self::solarState($project),
                'status' => $solar?->quality_status_label ?? 'Missing',
                'metric' => $solar ? self::solarMetric($project) : 'No solar profile',
            ],
            [
                'key' => 'roof',
                'label' => 'Roof',
                'caption' => 'Usable area and site readiness',
                'state' => self::roofState($project),
                'status' => $roof?->quality_status_label ?? 'Missing',
                'metric' => $roof ? self::roofMetric($project) : 'No roof survey',
            ],
            [
                'key' => 'recommendation',
                'label' => 'Recommend',
                'caption' => 'Explainable system selection',
                'state' => $recommendation?->hasGeneratedResult() ? 'complete' : 'missing',
                'status' => $recommendation?->result_status_label ?? 'Missing',
                'metric' => $recommendation?->recommended_system_label ?? 'No result',
            ],
            [
                'key' => 'technical',
                'label' => 'Technical',
                'caption' => 'PV, inverter, string, cable, battery',
                'state' => self::technicalState($calculation),
                'status' => $calculation?->result_status_label ?? 'Not calculated',
                'metric' => $calculation ? self::formatNumber($calculation->results['installed_pv_kw'] ?? null, 2, ' kWp') : 'No calculation',
            ],
            [
                'key' => 'bom',
                'label' => 'BOM/BOQ',
                'caption' => 'Quantity and pricing build-up',
                'state' => $bom ? ($bom->pricing_status === 'priced' ? 'complete' : 'current') : 'missing',
                'status' => $bom ? $bom->status_label.' · '.ucfirst(str_replace('_', ' ', $bom->pricing_status)) : 'Missing',
                'metric' => $bom ? self::pricedMoney($bom, 'grand_price_idr', 'Pricing required') : 'No BOQ',
            ],
            [
                'key' => 'financial',
                'label' => 'Financial',
                'caption' => 'Saving, payback, NPV, IRR, LCOE',
                'state' => in_array($financial?->status, ['calculated', 'locked'], true) ? 'complete' : 'missing',
                'status' => $financial?->status_label ?? 'Missing',
                'metric' => $financial ? self::formatNumber($financial->simple_payback_years, 2, ' years') : 'No analysis',
            ],
            [
                'key' => 'quotation',
                'label' => 'Quotation',
                'caption' => 'Customer-facing offer and reports',
                'state' => $quotation ? ($quotation->status === 'issued' ? 'locked' : 'complete') : 'missing',
                'status' => $quotation?->status_label ?? 'Missing',
                'metric' => $quotation?->quote_number ?? 'No quote',
            ],
        ];
    }

    private static function loadState(Project $project): string
    {
        $load = $project->loadAnalysis;

        if (! $load) {
            return 'missing';
        }

        $summary = $load->calculateSummary();

        return ((int) ($summary['appliance_count'] ?? 0) > 0 || (int) ($summary['bill_count'] ?? 0) > 0)
            ? 'complete'
            : 'current';
    }

    private static function solarState(Project $project): string
    {
        $solar = $project->solarProfile;

        if (! $solar) {
            return 'missing';
        }

        return in_array($solar->quality_status, ['estimated', 'imported', 'verified'], true)
            ? ($solar->quality_status === 'verified' ? 'locked' : 'complete')
            : 'current';
    }

    private static function roofState(Project $project): string
    {
        $roof = $project->roofSiteSurvey;

        if (! $roof) {
            return 'missing';
        }

        return match ($roof->quality_status) {
            'verified' => 'locked',
            'ready' => 'complete',
            default => ((int) ($roof->calculateSummary()['surface_count'] ?? 0) > 0 ? 'current' : 'missing'),
        };
    }

    private static function technicalState(?TechnicalCalculation $calculation): string
    {
        if (! $calculation || ! $calculation->calculated_at) {
            return 'missing';
        }

        if ($calculation->result_status === 'verified') {
            return 'locked';
        }

        $counts = $calculation->warningSeverityCounts();

        return (($counts['critical'] ?? 0) + ($counts['warning'] ?? 0)) > 0
            ? 'warning'
            : 'complete';
    }

    /**
     * @return array<string, mixed>
     */
    private static function engineeringSummary(Project $project): array
    {
        $calculation = $project->technicalCalculation;
        $results = $calculation?->results ?? [];
        $summary = $project->loadAnalysis?->calculateSummary() ?? [];
        $roofSummary = $project->roofSiteSurvey?->calculateSummary() ?? [];

        return [
            'system_type' => $calculation?->system_type
                ? ucfirst(str_replace('_', '-', $calculation->system_type))
                : $project->proposed_system_label,
            'required_pv_kw' => self::formatNumber($results['required_pv_kw'] ?? null, 2, ' kWp'),
            'installed_pv_kw' => self::formatNumber($results['installed_pv_kw'] ?? null, 2, ' kWp'),
            'module_count' => self::formatNumber($results['module_count'] ?? null, 0, ' pcs'),
            'inverter_count' => self::formatNumber($results['inverter_count'] ?? null, 0, ' unit'),
            'dc_ac_ratio' => self::formatNumber($results['dc_ac_ratio'] ?? null, 2),
            'annual_energy_kwh' => self::formatNumber($results['annual_energy_kwh'] ?? null, 0, ' kWh/year'),
            'daily_load_kwh' => self::formatNumber($summary['daily_energy_kwh'] ?? null, 2, ' kWh/day'),
            'usable_roof_area_m2' => self::formatNumber($roofSummary['usable_area_m2'] ?? null, 1, ' m²'),
            'status' => $calculation?->result_status_label ?? 'Not calculated',
            'has_overrides' => $calculation?->hasActiveOverrides() ?? false,
            'override_count' => count($calculation?->override_summary ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function commercialSummary(Project $project): array
    {
        $bom = $project->billOfMaterial;
        $quotation = $project->quotation;

        return [
            'bom_status' => $bom?->status_label ?? 'Not generated',
            'pricing_status' => $bom ? ucfirst(str_replace('_', ' ', $bom->pricing_status)) : 'Not priced',
            'material_price' => self::pricedMoney($bom, 'material_price_subtotal_idr', 'Pricing required'),
            'labor_cost' => self::pricedMoney($bom, 'labor_cost_idr', 'Pricing required'),
            'tax' => self::pricedMoney($bom, 'tax_idr', 'Pricing required'),
            'discount' => $bom ? (($bom->pricing_status === 'priced') ? self::money($bom->discount_idr) : 'Pricing required') : '-',
            'grand_price' => self::pricedMoney($bom, 'grand_price_idr', 'Pricing required'),
            'pricing_required' => ! $bom || $bom->pricing_status !== 'priced',
            'quotation_status' => $quotation?->status_label ?? 'No quotation',
            'quote_number' => $quotation?->quote_number ?? '-',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function financialSummary(Project $project): array
    {
        $financial = $project->financialAnalysis;
        $bom = $project->billOfMaterial;
        $usesManualCapex = $financial && (! $bom || $bom->pricing_status !== 'priced');

        return [
            'status' => $financial?->status_label ?? 'Not calculated',
            'capex' => self::money($financial?->project_capex_idr),
            'first_year_saving' => self::money($financial?->first_year_saving_idr),
            'payback' => self::formatNumber($financial?->simple_payback_years, 2, ' years'),
            'roi' => self::formatNumber($financial?->lifetime_roi_percent, 2, '%'),
            'npv' => self::money($financial?->npv_idr),
            'irr' => self::formatNumber($financial?->irr_percent, 2, '%'),
            'lcoe' => $financial?->lcoe_idr_per_kwh === null
                ? '-'
                : 'IDR '.number_format((float) $financial->lcoe_idr_per_kwh, 0, ',', '.').'/kWh',
            'capex_source' => $financial
                ? ($usesManualCapex ? 'Manual or imported CAPEX' : 'Priced BOM/BOQ CAPEX')
                : '-',
            'capex_attention' => (bool) $usesManualCapex,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function warnings(Project $project): array
    {
        $warnings = [];

        $warnings = array_merge($warnings, CalculationWarning::normalizeList(
            $project->systemRecommendation?->warnings ?? [],
            'system_recommendation'
        ));

        if ($project->technicalCalculation) {
            $warnings = array_merge($warnings, $project->technicalCalculation->warningRecords());
        }

        if ($project->roofSiteSurvey) {
            $roofWarnings = $project->roofSiteSurvey->calculateSummary()['warnings'] ?? [];
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($roofWarnings, 'roof_site_survey'));
        }

        $warnings = array_merge($warnings, CalculationWarning::normalizeList(
            $project->billOfMaterial?->warnings ?? [],
            'bom_boq'
        ));

        $warnings = array_merge($warnings, CalculationWarning::normalizeList(
            $project->financialAnalysis?->warnings ?? [],
            'financial_analysis'
        ));

        $warnings = array_merge($warnings, CalculationWarning::normalizeList(
            $project->quotation?->warnings ?? [],
            'quotation'
        ));

        if ($project->technicalCalculation?->hasActiveOverrides()) {
            $warnings[] = CalculationWarning::make(
                'ACTIVE_ENGINEERING_OVERRIDE',
                'info',
                'Active Engineering Override',
                'One or more technical results have been manually overridden by an engineer.',
                'Review active override reasons before generating commercial or construction documents.',
                'technical_override'
            );
        }

        return CalculationWarning::unique($warnings);
    }

    /**
     * @param  array<int, array<string, string>>  $warnings
     * @return array<string, int>
     */
    private static function severityCounts(array $warnings): array
    {
        $counts = [
            'critical' => 0,
            'warning' => 0,
            'caution' => 0,
            'info' => 0,
        ];

        foreach ($warnings as $warning) {
            $severity = $warning['severity'] ?? 'info';
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<string, mixed>|null
     */
    private static function nextStep(array $steps): ?array
    {
        foreach ($steps as $step) {
            if (! in_array($step['state'], ['complete', 'locked'], true)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $severityCounts
     * @return array{label:string,state:string,description:string}
     */
    private static function health(array $severityCounts, int $completionPercent): array
    {
        if (($severityCounts['critical'] ?? 0) > 0) {
            return [
                'label' => 'Critical review required',
                'state' => 'critical',
                'description' => 'Resolve critical engineering warnings before using commercial outputs.',
            ];
        }

        if (($severityCounts['warning'] ?? 0) > 0) {
            return [
                'label' => 'Engineering review needed',
                'state' => 'warning',
                'description' => 'The project can continue, but warnings must be reviewed by an engineer.',
            ];
        }

        if ($completionPercent < 100) {
            return [
                'label' => 'Workflow in progress',
                'state' => 'progress',
                'description' => 'Complete the missing workflow modules before final quotation.',
            ];
        }

        return [
            'label' => 'Ready for management review',
            'state' => 'ready',
            'description' => 'Core engineering, commercial, and financial outputs are available.',
        ];
    }


    /**
     * Build no-JavaScript chart data for the project cockpit.
     *
     * @return array<string, mixed>
     */
    private static function charts(Project $project): array
    {
        $financial = $project->financialAnalysis;
        $calculation = $project->technicalCalculation;
        $results = $calculation?->results ?? [];
        $cashflows = array_slice($financial?->cashflows ?? [], 0, 8);
        $cumulative = 0.0;
        $cashflowRows = [];
        $maxCashflow = 1.0;

        foreach ($cashflows as $row) {
            $saving = (float) ($row['gross_saving_idr'] ?? $row['saving_idr'] ?? $row['annual_saving_idr'] ?? 0);
            $om = (float) ($row['om_cost_idr'] ?? 0);
            $net = (float) ($row['net_cashflow_idr'] ?? ($saving - $om));
            $cumulative += $net;
            $maxCashflow = max($maxCashflow, abs($saving), abs($net), abs($cumulative));

            $cashflowRows[] = [
                'year' => (int) ($row['year'] ?? (count($cashflowRows) + 1)),
                'saving_idr' => $saving,
                'net_cashflow_idr' => $net,
                'cumulative_idr' => $cumulative,
                'saving_label' => self::money($saving),
                'net_label' => self::money($net),
                'cumulative_label' => self::money($cumulative),
                'saving_percent' => 0,
                'net_percent' => 0,
                'cumulative_percent' => 0,
                'net_state' => $net >= 0 ? 'ready' : 'critical',
            ];
        }

        $cashflowRows = array_map(static function (array $row) use ($maxCashflow): array {
            $row['saving_percent'] = self::barPercent((float) $row['saving_idr'], $maxCashflow);
            $row['net_percent'] = self::barPercent((float) $row['net_cashflow_idr'], $maxCashflow);
            $row['cumulative_percent'] = self::barPercent((float) $row['cumulative_idr'], $maxCashflow);

            return $row;
        }, $cashflowRows);

        $dcAcRatio = isset($results['dc_ac_ratio']) ? (float) $results['dc_ac_ratio'] : null;
        $voltageDropDc = isset($results['dc_voltage_drop_percent']) ? (float) $results['dc_voltage_drop_percent'] : null;
        $voltageDropAc = isset($results['ac_voltage_drop_percent']) ? (float) $results['ac_voltage_drop_percent'] : null;
        $annualEnergy = isset($results['annual_energy_kwh']) ? (float) $results['annual_energy_kwh'] : null;
        $requiredPv = isset($results['required_pv_kw']) ? (float) $results['required_pv_kw'] : null;
        $installedPv = isset($results['installed_pv_kw']) ? (float) $results['installed_pv_kw'] : null;

        return [
            'cashflow' => $cashflowRows,
            'cashflow_available' => $cashflowRows !== [],
            'technical' => [
                [
                    'label' => 'DC/AC ratio',
                    'value' => self::formatNumber($dcAcRatio, 2),
                    'percent' => $dcAcRatio === null ? 0 : self::clamp((int) round(($dcAcRatio / 1.35) * 100), 0, 100),
                    'state' => $dcAcRatio === null ? 'muted' : ($dcAcRatio < 0.95 ? 'warning' : ($dcAcRatio > 1.35 ? 'caution' : 'ready')),
                    'note' => 'Reviewed against the preliminary design band of 0.95 to 1.35.',
                ],
                [
                    'label' => 'DC voltage drop',
                    'value' => self::formatNumber($voltageDropDc, 2, '%'),
                    'percent' => $voltageDropDc === null ? 0 : self::clamp((int) round(($voltageDropDc / 3) * 100), 0, 100),
                    'state' => $voltageDropDc === null ? 'muted' : ($voltageDropDc > 3 ? 'warning' : 'ready'),
                    'note' => 'Lower voltage drop improves delivery efficiency.',
                ],
                [
                    'label' => 'AC voltage drop',
                    'value' => self::formatNumber($voltageDropAc, 2, '%'),
                    'percent' => $voltageDropAc === null ? 0 : self::clamp((int) round(($voltageDropAc / 3) * 100), 0, 100),
                    'state' => $voltageDropAc === null ? 'muted' : ($voltageDropAc > 3 ? 'warning' : 'ready'),
                    'note' => 'Review conductor size when it approaches the design limit.',
                ],
            ],
            'energy' => [
                'annual_energy' => self::formatNumber($annualEnergy, 0, ' kWh/year'),
                'specific_yield' => $installedPv && $installedPv > 0
                    ? self::formatNumber($annualEnergy / $installedPv, 0, ' kWh/kWp/year')
                    : '-',
                'pv_utilization' => ($requiredPv && $installedPv)
                    ? self::clamp((int) round(($requiredPv / max($installedPv, 0.01)) * 100), 0, 140)
                    : 0,
                'pv_utilization_bar' => ($requiredPv && $installedPv)
                    ? self::clamp((int) round(($requiredPv / max($installedPv, 0.01)) * 100), 0, 100)
                    : 0,
                'pv_utilization_label' => ($requiredPv && $installedPv)
                    ? self::formatNumber(($requiredPv / max($installedPv, 0.01)) * 100, 0, '% design fit')
                    : '-',
            ],
        ];
    }

    /**
     * @param  array<string, int>  $severityCounts
     * @return array<int, array<string, string>>
     */
    private static function insights(Project $project, array $severityCounts, int $completionPercent): array
    {
        $insights = [];
        $financial = $project->financialAnalysis;
        $bom = $project->billOfMaterial;
        $calculation = $project->technicalCalculation;
        $results = $calculation?->results ?? [];

        if (($severityCounts['critical'] ?? 0) > 0) {
            $insights[] = [
                'state' => 'critical',
                'title' => 'Critical review required',
                'message' => 'Resolve critical warnings before issuing reports or using the design for procurement.',
                'metric' => ($severityCounts['critical'] ?? 0).' critical',
            ];
        } elseif (($severityCounts['warning'] ?? 0) > 0) {
            $insights[] = [
                'state' => 'warning',
                'title' => 'Engineering review needed',
                'message' => 'The workflow can continue, but warning items should be reviewed before customer submission.',
                'metric' => ($severityCounts['warning'] ?? 0).' warnings',
            ];
        } else {
            $insights[] = [
                'state' => 'ready',
                'title' => 'No major blocking warning',
                'message' => 'No critical or warning-level issue is recorded in the cockpit.',
                'metric' => 'Ready',
            ];
        }

        if (! $bom || $bom->pricing_status !== 'priced') {
            $insights[] = [
                'state' => 'caution',
                'title' => 'Commercial pricing needs review',
                'message' => 'Complete BOQ pricing before relying on quotation totals or financial feasibility.',
                'metric' => 'Pricing required',
            ];
        } else {
            $insights[] = [
                'state' => 'ready',
                'title' => 'Priced BOQ available',
                'message' => 'Commercial values are ready for quotation and financial review.',
                'metric' => self::money($bom->grand_price_idr),
            ];
        }

        if ($financial) {
            $payback = (float) ($financial->simple_payback_years ?? 0);
            $insights[] = [
                'state' => $payback > 0 && $payback <= 8 ? 'ready' : ($payback <= 12 ? 'caution' : 'warning'),
                'title' => 'Financial feasibility snapshot',
                'message' => $payback > 0 && $payback <= 8
                    ? 'Simple payback is within an attractive preliminary range.'
                    : 'Review tariff, CAPEX, and energy assumptions to improve feasibility.',
                'metric' => self::formatNumber($financial->simple_payback_years, 2, ' years'),
            ];
        } else {
            $insights[] = [
                'state' => 'muted',
                'title' => 'Financial analysis missing',
                'message' => 'Run financial analysis after engineering and pricing are complete.',
                'metric' => 'Not calculated',
            ];
        }

        if ($calculation?->hasActiveOverrides()) {
            $insights[] = [
                'state' => 'info',
                'title' => 'Engineer override is active',
                'message' => 'At least one technical value has been adjusted manually and remains traceable.',
                'metric' => count($calculation->override_summary ?? []).' overrides',
            ];
        }

        if (isset($results['dc_ac_ratio'])) {
            $ratio = (float) $results['dc_ac_ratio'];
            $insights[] = [
                'state' => $ratio < 0.95 ? 'warning' : ($ratio > 1.35 ? 'caution' : 'ready'),
                'title' => 'DC/AC ratio position',
                'message' => $ratio < 0.95
                    ? 'The inverter may be oversized compared with installed PV capacity.'
                    : ($ratio > 1.35 ? 'Review clipping risk and inverter operating limit.' : 'The ratio is within the expected preliminary review band.'),
                'metric' => self::formatNumber($ratio, 2),
            ];
        }

        if ($completionPercent < 100) {
            $insights[] = [
                'state' => 'progress',
                'title' => 'Workflow not complete yet',
                'message' => 'Finish missing modules before treating the project as final or ready for management approval.',
                'metric' => $completionPercent.'%',
            ];
        }

        return array_slice($insights, 0, 6);
    }

    private static function barPercent(float $value, float $max): int
    {
        if ($max <= 0) {
            return 0;
        }

        return self::clamp((int) round((abs($value) / $max) * 100), 4, 100);
    }


    private static function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private static function loadMetric(Project $project): string
    {
        $summary = $project->loadAnalysis?->calculateSummary() ?? [];

        return self::formatNumber($summary['daily_energy_kwh'] ?? null, 2, ' kWh/day');
    }

    private static function solarMetric(Project $project): string
    {
        $summary = $project->solarProfile?->calculateSummary($project) ?? [];

        return self::formatNumber($summary['weighted_psh'] ?? $summary['average_psh'] ?? null, 2, ' PSH');
    }

    private static function roofMetric(Project $project): string
    {
        $summary = $project->roofSiteSurvey?->calculateSummary() ?? [];

        return self::formatNumber($summary['usable_area_m2'] ?? null, 1, ' m² usable');
    }


    public static function pricedMoney(mixed $bom, string $field, string $fallback = 'Pricing required'): string
    {
        if (! $bom) {
            return '-';
        }

        if (($bom->pricing_status ?? null) !== 'priced') {
            return $fallback;
        }

        return self::money($bom->{$field} ?? null);
    }

    public static function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return 'IDR '.number_format((float) $value, 0, ',', '.');
    }

    public static function formatNumber(mixed $value, int $decimals = 2, string $suffix = ''): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimals, ',', '.').$suffix;
    }
}
