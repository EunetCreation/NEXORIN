<?php

namespace App\Services\Finance;

use App\Models\BillOfMaterial;
use App\Models\Project;
use App\Models\TechnicalCalculation;

class FinancialAnalysisEngine
{
    /**
     * Calculate deterministic financial metrics from technical and BOQ results.
     *
     * @return array{summary: array<string, mixed>, cashflows: array<int, array<string, mixed>>, warnings: array<int, string>, parameters: array<string, mixed>, source_snapshot: array<string, mixed>}
     */
    public function calculate(
        Project $project,
        TechnicalCalculation $calculation,
        BillOfMaterial $bom,
        array $parameters = []
    ): array {
        $normalized = $this->normalizeParameters($parameters);
        $warnings = [];
        $technicalResults = $calculation->results ?? [];
        $bomSummary = $bom->summary ?? [];

        $annualEnergyKwh = $this->positiveFloat(
            $technicalResults['annual_energy_kwh'] ?? 0
        );

        $installedPvKw = $this->positiveFloat(
            $technicalResults['installed_pv_kw']
                ?? $bomSummary['installed_pv_kw']
                ?? 0
        );

        $capex = $this->positiveFloat(
            $normalized['project_capex_idr']
                ?: $bom->grand_price_idr
                ?: ($bomSummary['grand_price_idr'] ?? 0)
        );

        $selfConsumptionPercent = $normalized['self_consumption_percent']
            ?? $this->defaultSelfConsumptionPercent((string) $calculation->system_type);
        $selfConsumptionPercent = $this->boundedPercent($selfConsumptionPercent, 0, 100);

        $firstYearConsumedKwh = $annualEnergyKwh * ($selfConsumptionPercent / 100);
        $firstYearExportKwh = max(0, $annualEnergyKwh - $firstYearConsumedKwh);
        $tariff = $normalized['electricity_tariff_idr_per_kwh'];
        $exportCredit = $tariff * ($normalized['export_credit_percent'] / 100);
        $firstYearSaving = round(
            ($firstYearConsumedKwh * $tariff) + ($firstYearExportKwh * $exportCredit),
            2
        );
        $annualOmCost = round($capex * ($normalized['annual_om_percent'] / 100), 2);

        if ($annualEnergyKwh <= 0) {
            $warnings[] = 'Annual PV energy is zero or missing. Financial results are limited.';
        }

        if ($capex <= 0) {
            $warnings[] = 'Project CAPEX is zero or missing. Run BOQ pricing before financial analysis.';
        }

        if ($bom->pricing_status !== 'priced') {
            $warnings[] = 'BOQ pricing is not marked as priced. Financial analysis uses the available draft value.';
        }

        if ($calculation->result_status !== 'verified') {
            $technicalState = match ((string) $calculation->result_status) {
                'calculated' => 'Calculated',
                'preliminary' => 'Preliminary',
                'detailed' => 'Detailed',
                default => 'Stored',
            };
            $warnings[] = "The technical calculation is stored as {$technicalState}. Financial analysis does not upgrade or approve the engineering result.";
        }

        if ($normalized['export_credit_percent'] > 0) {
            $warnings[] = 'Export credit is a model assumption. Confirm the project-specific commercial or utility basis before relying on export revenue.';
        }

        $cashflows = $this->cashflows(
            $normalized,
            $annualEnergyKwh,
            $selfConsumptionPercent,
            $tariff,
            $exportCredit,
            $annualOmCost,
            $capex
        );

        $discountRate = $normalized['discount_rate_percent'] / 100;
        $npv = $this->npv($cashflows, $discountRate, $capex);
        $irr = $this->irr($cashflows, $capex);
        $payback = $this->simplePayback($cashflows, $capex);
        $lcoe = $this->lcoe($cashflows, $capex, $annualEnergyKwh, $normalized);

        $totalNetCashflow = array_sum(array_column($cashflows, 'net_cashflow_idr'));
        $roi = $capex > 0
            ? round((($totalNetCashflow - $capex) / $capex) * 100, 2)
            : 0.0;

        $summary = [
            'status' => 'calculated',
            'currency' => 'IDR',
            'system_type' => $calculation->system_type,
            'installed_pv_kw' => round($installedPvKw, 3),
            'annual_energy_kwh' => round($annualEnergyKwh, 2),
            'self_consumption_percent' => round($selfConsumptionPercent, 2),
            'first_year_self_consumed_kwh' => round($firstYearConsumedKwh, 2),
            'first_year_export_kwh' => round($firstYearExportKwh, 2),
            'project_capex_idr' => round($capex, 2),
            'first_year_saving_idr' => $firstYearSaving,
            'annual_om_cost_idr' => $annualOmCost,
            'net_first_year_cashflow_idr' => round($firstYearSaving - $annualOmCost, 2),
            'simple_payback_years' => $payback,
            'lifetime_roi_percent' => $roi,
            'npv_idr' => round($npv, 2),
            'irr_percent' => $irr,
            'lcoe_idr_per_kwh' => $lcoe,
            'project_lifetime_years' => $normalized['project_lifetime_years'],
            'discount_rate_percent' => $normalized['discount_rate_percent'],
            'electricity_tariff_idr_per_kwh' => $tariff,
            'tariff_escalation_percent' => $normalized['tariff_escalation_percent'],
            'energy_degradation_percent' => $normalized['energy_degradation_percent'],
            'replacement_cost_idr' => $normalized['replacement_cost_idr'],
            'replacement_year' => $normalized['replacement_year'],
            'total_net_cashflow_idr' => round($totalNetCashflow, 2),
            'calculated_at' => now()->toISOString(),
        ];

        return [
            'summary' => $summary,
            'cashflows' => $cashflows,
            'warnings' => array_values(array_unique($warnings)),
            'parameters' => array_merge($normalized, [
                'self_consumption_percent' => $selfConsumptionPercent,
            ]),
            'source_snapshot' => [
                'project_id' => $project->getKey(),
                'technical_calculation_id' => $calculation->getKey(),
                'technical_calculation_status' => $calculation->result_status,
                'technical_calculated_at' => optional($calculation->calculated_at)->toISOString(),
                'bill_of_material_id' => $bom->getKey(),
                'bom_pricing_status' => $bom->pricing_status,
                'bom_priced_at' => optional($bom->priced_at)->toISOString(),
                'bom_grand_price_idr' => $this->positiveFloat($bom->grand_price_idr),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function defaultParameters(): array
    {
        return [
            'electricity_tariff_idr_per_kwh' => 1444.7,
            'export_credit_percent' => 0.0,
            'self_consumption_percent' => null,
            'tariff_escalation_percent' => 3.0,
            'annual_om_percent' => 1.0,
            'discount_rate_percent' => 8.0,
            'energy_degradation_percent' => 0.5,
            'project_lifetime_years' => 25,
            'replacement_year' => 12,
            'replacement_cost_idr' => 0.0,
            'project_capex_idr' => null,
            'analysis_note' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeParameters(array $parameters): array
    {
        $defaults = $this->defaultParameters();

        return [
            'electricity_tariff_idr_per_kwh' => max(0.0, $this->nullableFloat($parameters['electricity_tariff_idr_per_kwh'] ?? null, $defaults['electricity_tariff_idr_per_kwh'])),
            'export_credit_percent' => $this->boundedPercent($parameters['export_credit_percent'] ?? $defaults['export_credit_percent'], 0, 100),
            'self_consumption_percent' => ($parameters['self_consumption_percent'] ?? '') === ''
                ? null
                : $this->boundedPercent($parameters['self_consumption_percent'], 0, 100),
            'tariff_escalation_percent' => $this->boundedPercent($parameters['tariff_escalation_percent'] ?? $defaults['tariff_escalation_percent'], 0, 100),
            'annual_om_percent' => $this->boundedPercent($parameters['annual_om_percent'] ?? $defaults['annual_om_percent'], 0, 100),
            'discount_rate_percent' => $this->boundedPercent($parameters['discount_rate_percent'] ?? $defaults['discount_rate_percent'], 0, 100),
            'energy_degradation_percent' => $this->boundedPercent($parameters['energy_degradation_percent'] ?? $defaults['energy_degradation_percent'], 0, 100),
            'project_lifetime_years' => min(40, max(1, (int) ($parameters['project_lifetime_years'] ?? $defaults['project_lifetime_years']))),
            'replacement_year' => min(40, max(0, (int) ($parameters['replacement_year'] ?? $defaults['replacement_year']))),
            'replacement_cost_idr' => max(0.0, $this->nullableFloat($parameters['replacement_cost_idr'] ?? null, $defaults['replacement_cost_idr'])),
            'project_capex_idr' => ($parameters['project_capex_idr'] ?? '') === '' || ($parameters['project_capex_idr'] ?? null) === null
                ? null
                : max(0.0, (float) $parameters['project_capex_idr']),
            'analysis_note' => $parameters['analysis_note'] ?? $defaults['analysis_note'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function cashflows(
        array $parameters,
        float $annualEnergyKwh,
        float $selfConsumptionPercent,
        float $tariff,
        float $exportCredit,
        float $annualOmCost,
        float $capex
    ): array {
        $cashflows = [];
        $cumulative = -$capex;
        $degradation = $parameters['energy_degradation_percent'] / 100;
        $tariffEscalation = $parameters['tariff_escalation_percent'] / 100;
        $omEscalation = $tariffEscalation;
        $lifetime = (int) $parameters['project_lifetime_years'];
        $replacementYear = (int) $parameters['replacement_year'];
        $replacementCost = (float) $parameters['replacement_cost_idr'];

        for ($year = 1; $year <= $lifetime; $year++) {
            $energyFactor = (1 - $degradation) ** ($year - 1);
            $tariffFactor = (1 + $tariffEscalation) ** ($year - 1);
            $omFactor = (1 + $omEscalation) ** ($year - 1);

            $energy = max(0, $annualEnergyKwh * $energyFactor);
            $consumedKwh = $energy * ($selfConsumptionPercent / 100);
            $exportKwh = max(0, $energy - $consumedKwh);
            $saving = round(
                ($consumedKwh * $tariff * $tariffFactor)
                    + ($exportKwh * $exportCredit * $tariffFactor),
                2
            );
            $omCost = round($annualOmCost * $omFactor, 2);
            $replacement = ($replacementYear > 0 && $year === $replacementYear)
                ? $replacementCost
                : 0.0;
            $net = round($saving - $omCost - $replacement, 2);
            $cumulative = round($cumulative + $net, 2);

            $cashflows[] = [
                'year' => $year,
                'energy_kwh' => round($energy, 2),
                'self_consumed_kwh' => round($consumedKwh, 2),
                'export_kwh' => round($exportKwh, 2),
                'gross_saving_idr' => $saving,
                'om_cost_idr' => $omCost,
                'replacement_cost_idr' => $replacement,
                'net_cashflow_idr' => $net,
                'cumulative_cashflow_idr' => $cumulative,
            ];
        }

        return $cashflows;
    }

    /** @param array<int, array<string, mixed>> $cashflows */
    private function npv(array $cashflows, float $discountRate, float $capex): float
    {
        $npv = -$capex;

        foreach ($cashflows as $row) {
            $year = (int) $row['year'];
            $npv += ((float) $row['net_cashflow_idr']) / ((1 + $discountRate) ** $year);
        }

        return $npv;
    }

    /** @param array<int, array<string, mixed>> $cashflows */
    private function irr(array $cashflows, float $capex): ?float
    {
        if ($capex <= 0 || $cashflows === []) {
            return null;
        }

        $low = -0.95;
        $high = 1.00;
        $npvLow = $this->npv($cashflows, $low, $capex);
        $npvHigh = $this->npv($cashflows, $high, $capex);

        if ($npvLow * $npvHigh > 0) {
            return null;
        }

        for ($i = 0; $i < 80; $i++) {
            $mid = ($low + $high) / 2;
            $npvMid = $this->npv($cashflows, $mid, $capex);

            if (abs($npvMid) < 1) {
                return round($mid * 100, 2);
            }

            if ($npvLow * $npvMid <= 0) {
                $high = $mid;
                $npvHigh = $npvMid;
            } else {
                $low = $mid;
                $npvLow = $npvMid;
            }
        }

        return round((($low + $high) / 2) * 100, 2);
    }

    /** @param array<int, array<string, mixed>> $cashflows */
    private function simplePayback(array $cashflows, float $capex): ?float
    {
        if ($capex <= 0) {
            return null;
        }

        $cumulativeBefore = -$capex;

        foreach ($cashflows as $row) {
            $net = (float) $row['net_cashflow_idr'];
            $cumulativeAfter = $cumulativeBefore + $net;

            if ($cumulativeAfter >= 0 && $net > 0) {
                $fraction = abs($cumulativeBefore) / $net;

                return round(((int) $row['year'] - 1) + $fraction, 2);
            }

            $cumulativeBefore = $cumulativeAfter;
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $cashflows */
    private function lcoe(array $cashflows, float $capex, float $annualEnergyKwh, array $parameters): ?float
    {
        if ($capex <= 0 || $annualEnergyKwh <= 0) {
            return null;
        }

        $discountRate = $parameters['discount_rate_percent'] / 100;
        $discountedCost = $capex;
        $discountedEnergy = 0.0;

        foreach ($cashflows as $row) {
            $year = (int) $row['year'];
            $discountFactor = (1 + $discountRate) ** $year;
            $discountedCost += (((float) $row['om_cost_idr']) + ((float) $row['replacement_cost_idr'])) / $discountFactor;
            $discountedEnergy += ((float) $row['energy_kwh']) / $discountFactor;
        }

        return $discountedEnergy > 0
            ? round($discountedCost / $discountedEnergy, 2)
            : null;
    }

    private function defaultSelfConsumptionPercent(string $systemType): float
    {
        return $systemType === 'hybrid' ? 92.0 : 80.0;
    }

    private function nullableFloat(mixed $value, float $default): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) $value;
    }

    private function positiveFloat(mixed $value): float
    {
        return max(0.0, (float) ($value ?? 0));
    }

    private function boundedPercent(mixed $value, float $min, float $max): float
    {
        return min($max, max($min, (float) ($value ?? 0)));
    }
}
