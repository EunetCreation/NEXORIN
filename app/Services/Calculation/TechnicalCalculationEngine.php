<?php

namespace App\Services\Calculation;

use App\Models\Project;

class TechnicalCalculationEngine
{
    public const VERSION = 'nexorin-technical-lv-v1.1.0';

    /**
     * Calculate preliminary low-voltage rooftop PV technical design values.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $sources
     * @return array<string, mixed>
     */
    public function calculate(Project $project, array $input, array $sources = []): array
    {
        $warnings = [];

        $load = $sources['load'] ?? [];
        $solar = $sources['solar'] ?? [];
        $roof = $sources['roof'] ?? [];
        $recommendation = $sources['recommendation'] ?? [];

        $dailyLoadKwh = max((float) ($load['daily_energy_kwh'] ?? 0), 0.0);
        $criticalDailyLoadKwh = max((float) ($load['critical_daily_energy_kwh'] ?? 0), 0.0);
        $peakDemandKw = max((float) ($load['peak_demand_kw'] ?? 0), 0.0);
        $supplyPhase = (string) ($load['supply_phase'] ?? 'single_phase');

        $psh = max((float) ($solar['average_psh'] ?? 0), 0.0);
        $usableRoofAreaM2 = max((float) ($roof['usable_area_m2'] ?? 0), 0.0);
        $weightedShadingPercent = min(max((float) ($roof['weighted_shading_percent'] ?? 0), 0.0), 100.0);
        $hasRoofSource = isset($roof['usable_area_m2']) && $usableRoofAreaM2 > 0;

        if ($dailyLoadKwh <= 0) {
            $warnings[] = 'Load source is missing or has zero daily energy. PV sizing result is therefore limited.';
        }

        if ($psh <= 0) {
            $psh = 4.5;
            $warnings[] = 'Solar profile is missing. The calculation used a fallback peak sun hour value of 4.5 h/day.';
        }

        if (! $hasRoofSource) {
            $warnings[] = 'Usable roof area is missing. Roof limitation was not applied.';
        }

        if ($recommendation === []) {
            $warnings[] = 'System recommendation source is missing. The selected system type only follows the calculation form input.';
        }

        foreach (($roof['warnings'] ?? []) as $roofWarning) {
            if (is_string($roofWarning) && $roofWarning !== '') {
                $warnings[] = $roofWarning;
            }
        }

        $systemType = (string) ($input['system_type'] ?? 'on_grid');
        $offsetTarget = $this->percent((float) ($input['energy_offset_target_percent'] ?? 80));
        $performanceRatio = $this->clamp((float) ($input['performance_ratio'] ?? 0.78), 0.4, 1.0);
        $shadingFactor = 1 - ($weightedShadingPercent / 100);
        $effectivePerformanceRatio = $this->clamp($performanceRatio * $shadingFactor, 0.01, 1.0);

        $moduleWp = max((float) ($input['module_power_wp'] ?? 550), 0.0);
        $moduleAreaM2 = max((float) ($input['module_area_m2'] ?? 2.58), 0.0);
        $moduleVoc = max((float) ($input['module_voc_v'] ?? 49.90), 0.0);
        $moduleVmp = max((float) ($input['module_vmp_v'] ?? 41.80), 0.0);
        $moduleIsc = max((float) ($input['module_isc_a'] ?? 13.90), 0.0);
        $moduleImp = max((float) ($input['module_imp_a'] ?? 13.16), 0.0);
        $vocCoeffPercentC = (float) ($input['module_voc_temp_coeff_percent_c'] ?? -0.25);
        $vmpCoeffPercentC = (float) ($input['module_vmp_temp_coeff_percent_c'] ?? -0.29);
        $minimumSiteTemperatureC = (float) ($input['minimum_site_temperature_c'] ?? 20);
        $maximumCellTemperatureC = (float) ($input['maximum_cell_temperature_c'] ?? 70);

        $inverterAcPowerKw = max((float) ($input['inverter_ac_power_kw'] ?? 5), 0.0);
        $inverterMaxDcVoltageV = max((float) ($input['inverter_max_dc_voltage_v'] ?? 600), 0.0);
        $inverterMpptMinVoltageV = max((float) ($input['inverter_mppt_min_voltage_v'] ?? 120), 0.0);
        $inverterMpptMaxVoltageV = max((float) ($input['inverter_mppt_max_voltage_v'] ?? 550), 0.0);
        $inverterMaxInputCurrentA = max((float) ($input['inverter_max_input_current_a'] ?? 32), 0.0);
        $inverterMpptCount = max((int) ($input['inverter_mppt_count'] ?? 2), 1);
        $desiredDcAcRatio = $this->clamp((float) ($input['desired_dc_ac_ratio'] ?? 1.15), 0.7, 2.0);
        $inverterEfficiency = $this->percent((float) ($input['inverter_efficiency_percent'] ?? 96));

        $backupHours = max((float) ($input['battery_backup_hours'] ?? 0), 0.0);
        $batteryDod = $this->percent((float) ($input['battery_dod_percent'] ?? 90));
        $batteryEfficiency = $this->percent((float) ($input['battery_efficiency_percent'] ?? 95));

        $dcCableLengthM = max((float) ($input['dc_cable_length_m'] ?? 20), 0.0);
        $dcCableAreaMm2 = max((float) ($input['dc_cable_area_mm2'] ?? 6), 0.0001);
        $acCableLengthM = max((float) ($input['ac_cable_length_m'] ?? 15), 0.0);
        $acCableAreaMm2 = max((float) ($input['ac_cable_area_mm2'] ?? 6), 0.0001);
        $cableMaterial = (string) ($input['cable_material'] ?? 'copper');
        $maximumVoltageDropPercent = max((float) ($input['maximum_voltage_drop_percent'] ?? 2), 0.1);

        $targetDailyEnergyKwh = $dailyLoadKwh * $offsetTarget;
        $requiredPvKw = $targetDailyEnergyKwh > 0
            ? $targetDailyEnergyKwh / max($psh * $effectivePerformanceRatio, 0.0001)
            : 0.0;

        $requiredModuleCount = $moduleWp > 0
            ? (int) ceil(($requiredPvKw * 1000) / $moduleWp)
            : 0;

        $areaLimitedModuleCount = $hasRoofSource && $moduleAreaM2 > 0
            ? (int) floor($usableRoofAreaM2 / $moduleAreaM2)
            : $requiredModuleCount;

        $moduleCount = max(min($requiredModuleCount, $areaLimitedModuleCount), 0);
        $installedPvKw = $moduleWp > 0 ? ($moduleCount * $moduleWp) / 1000 : 0.0;

        if ($requiredModuleCount > 0 && $moduleCount < $requiredModuleCount) {
            $warnings[] = 'PV array is roof limited. Installed PV capacity is lower than the target offset requirement.';
        }

        $inverterCount = 0;

        if ($inverterAcPowerKw > 0 && $installedPvKw > 0) {
            $idealInverterCount = $installedPvKw / ($inverterAcPowerKw * $desiredDcAcRatio);
            $candidateCounts = array_unique([
                max(1, (int) floor($idealInverterCount)),
                max(1, (int) ceil($idealInverterCount)),
            ]);
            $inverterCount = collect($candidateCounts)
                ->sortBy(function (int $candidate) use ($installedPvKw, $inverterAcPowerKw, $desiredDcAcRatio): float {
                    $candidateRatio = $installedPvKw / ($candidate * $inverterAcPowerKw);

                    return abs($candidateRatio - $desiredDcAcRatio);
                })
                ->first();
        }

        $totalInverterAcKw = $inverterCount * $inverterAcPowerKw;
        $dcAcRatio = $totalInverterAcKw > 0 ? $installedPvKw / $totalInverterAcKw : 0.0;

        if ($dcAcRatio > 0 && ($dcAcRatio < 0.75 || $dcAcRatio > 1.50)) {
            $warnings[] = 'DC/AC ratio is outside the common preliminary design band of 0.75 to 1.50.';
        }

        $moduleVocCold = $this->temperatureCorrectedVoltage(
            $moduleVoc,
            $vocCoeffPercentC,
            $minimumSiteTemperatureC
        );
        $moduleVmpHot = $this->temperatureCorrectedVoltage(
            $moduleVmp,
            $vmpCoeffPercentC,
            $maximumCellTemperatureC
        );

        $maximumByVoc = $moduleVocCold > 0
            ? (int) floor($inverterMaxDcVoltageV / $moduleVocCold)
            : 0;
        $maximumByMppt = $moduleVmpHot > 0
            ? (int) floor($inverterMpptMaxVoltageV / $moduleVmpHot)
            : 0;
        $maximumModulesPerString = max(min($maximumByVoc, $maximumByMppt), 0);
        $minimumModulesPerString = $moduleVmpHot > 0
            ? (int) ceil($inverterMpptMinVoltageV / $moduleVmpHot)
            : 0;

        $modulesPerString = 0;
        $stringCount = 0;

        if ($moduleCount > 0 && $minimumModulesPerString > 0 && $maximumModulesPerString >= $minimumModulesPerString) {
            $modulesPerString = $moduleCount >= $maximumModulesPerString
                ? $maximumModulesPerString
                : max($minimumModulesPerString, $moduleCount);
            $modulesPerString = min($modulesPerString, $moduleCount);
            $stringCount = (int) ceil($moduleCount / max($modulesPerString, 1));
        }

        if ($minimumModulesPerString > $maximumModulesPerString || $maximumModulesPerString === 0) {
            $warnings[] = 'Selected module and inverter are not compatible within the MPPT and maximum DC voltage window.';
        }

        $availableMpptInputs = $inverterCount * $inverterMpptCount;
        if ($stringCount > 0 && $availableMpptInputs > 0 && $stringCount > $availableMpptInputs) {
            $warnings[] = 'String count exceeds the available MPPT inputs. Select a larger inverter set or adjust the module layout.';
        }

        $parallelStringsPerMppt = $availableMpptInputs > 0
            ? (int) ceil($stringCount / $availableMpptInputs)
            : 0;
        $dcInputCurrentA = $parallelStringsPerMppt * $moduleIsc * 1.25;

        if ($dcInputCurrentA > 0 && $inverterMaxInputCurrentA > 0 && $dcInputCurrentA > $inverterMaxInputCurrentA) {
            $warnings[] = 'Calculated DC input current exceeds the inverter input-current limit per inverter.';
        }

        $stringVocColdV = $modulesPerString * $moduleVocCold;
        $stringVmpHotV = $modulesPerString * $moduleVmpHot;
        $estimatedDailyGenerationKwh = $installedPvKw * $psh * $effectivePerformanceRatio;
        $annualEnergyKwh = $estimatedDailyGenerationKwh * 365;
        $targetAnnualEnergyKwh = $targetDailyEnergyKwh * 365;
        $energyShortfallKwh = max($targetAnnualEnergyKwh - $annualEnergyKwh, 0.0);
        $energyShortfallPercent = $targetAnnualEnergyKwh > 0
            ? ($energyShortfallKwh / $targetAnnualEnergyKwh) * 100
            : 0.0;

        $batteryNominalKwh = 0.0;
        $batteryMinimumPowerKw = 0.0;

        if ($systemType === 'hybrid') {
            if ($backupHours <= 0) {
                $warnings[] = 'Hybrid system is selected but backup duration is zero.';
            }

            $criticalLoadBasisKwh = $criticalDailyLoadKwh > 0
                ? $criticalDailyLoadKwh
                : $dailyLoadKwh * 0.30;
            $batteryUsableKwh = min($criticalLoadBasisKwh, $criticalLoadBasisKwh / 24 * $backupHours);
            $batteryNominalKwh = $batteryUsableKwh > 0
                ? $batteryUsableKwh / max($batteryDod * $batteryEfficiency, 0.0001)
                : 0.0;
            $batteryMinimumPowerKw = max(
                $backupHours > 0 ? $batteryUsableKwh / $backupHours : 0.0,
                $peakDemandKw > 0 ? min($peakDemandKw, $criticalLoadBasisKwh) : 0.0
            );
        }

        $dcVoltageDropPercent = $this->dcVoltageDropPercent(
            $moduleImp * max($parallelStringsPerMppt, 1),
            $dcCableLengthM,
            $dcCableAreaMm2,
            $stringVmpHotV,
            $cableMaterial
        );
        $acCurrentA = $this->acCurrent($totalInverterAcKw, $supplyPhase, $inverterEfficiency);
        $acVoltageDropPercent = $this->acVoltageDropPercent(
            $acCurrentA,
            $acCableLengthM,
            $acCableAreaMm2,
            $supplyPhase,
            $cableMaterial
        );

        if ($dcVoltageDropPercent > $maximumVoltageDropPercent) {
            $warnings[] = 'DC voltage drop exceeds the selected maximum design limit.';
        }

        if ($acVoltageDropPercent > $maximumVoltageDropPercent) {
            $warnings[] = 'AC voltage drop exceeds the selected maximum design limit.';
        }

        $protection = [
            'dc_isolator_a' => $this->nextStandardRating($dcInputCurrentA),
            'dc_fuse_a' => $this->nextStandardRating($moduleIsc * 1.56),
            'ac_breaker_a' => $this->nextStandardRating($acCurrentA * 1.25),
            'dc_spd' => $inverterMaxDcVoltageV > 1000 ? 'Type 2 DC SPD 1500 Vdc' : 'Type 2 DC SPD 1000 Vdc',
            'ac_spd' => $supplyPhase === 'three_phase' ? 'Type 2 AC SPD 3P+N' : 'Type 2 AC SPD 1P+N',
        ];

        $resultStatus = $this->determineStatus($warnings, $dailyLoadKwh, $installedPvKw, $moduleCount);

        return [
            'engine_version' => self::VERSION,
            'result_status' => $resultStatus,
            'required_pv_kw' => round($requiredPvKw, 3),
            'installed_pv_kw' => round($installedPvKw, 3),
            'module_count' => $moduleCount,
            'required_module_count' => $requiredModuleCount,
            'area_limited_module_count' => $areaLimitedModuleCount,
            'inverter_count' => $inverterCount,
            'total_inverter_ac_kw' => round($totalInverterAcKw, 3),
            'dc_ac_ratio' => round($dcAcRatio, 3),
            'battery_nominal_kwh' => round($batteryNominalKwh, 3),
            'battery_minimum_power_kw' => round($batteryMinimumPowerKw, 3),
            'estimated_daily_generation_kwh' => round($estimatedDailyGenerationKwh, 3),
            'annual_energy_kwh' => round($annualEnergyKwh, 3),
            'target_annual_energy_kwh' => round($targetAnnualEnergyKwh, 3),
            'energy_shortfall_kwh' => round($energyShortfallKwh, 3),
            'energy_shortfall_percent' => round($energyShortfallPercent, 2),
            'performance_ratio' => round($performanceRatio, 4),
            'effective_performance_ratio' => round($effectivePerformanceRatio, 4),
            'minimum_modules_per_string' => $minimumModulesPerString,
            'maximum_modules_per_string' => $maximumModulesPerString,
            'modules_per_string' => $modulesPerString,
            'string_count' => $stringCount,
            'string_voc_cold_v' => round($stringVocColdV, 3),
            'string_vmp_hot_v' => round($stringVmpHotV, 3),
            'dc_input_current_a' => round($dcInputCurrentA, 3),
            'dc_voltage_drop_percent' => round($dcVoltageDropPercent, 3),
            'ac_voltage_drop_percent' => round($acVoltageDropPercent, 3),
            'protection' => $protection,
            'assumptions' => [
                'daily_load_kwh' => round($dailyLoadKwh, 3),
                'critical_daily_load_kwh' => round($criticalDailyLoadKwh, 3),
                'peak_demand_kw' => round($peakDemandKw, 3),
                'average_psh' => round($psh, 3),
                'usable_roof_area_m2' => round($usableRoofAreaM2, 3),
                'weighted_shading_percent' => round($weightedShadingPercent, 3),
                'supply_phase' => $supplyPhase,
                'system_type' => $systemType,
            ],
            'warnings' => array_values(array_unique($warnings)),
            'source_snapshot' => $sources,
        ];
    }

    private function determineStatus(array $warnings, float $dailyLoadKwh, float $installedPvKw, int $moduleCount): string
    {
        if ($dailyLoadKwh <= 0 || $installedPvKw <= 0 || $moduleCount <= 0) {
            return 'preliminary';
        }

        $blockingMarkers = [
            'missing',
            'not compatible',
            'zero daily energy',
            'exceeds the inverter',
        ];

        foreach ($warnings as $warning) {
            $lower = strtolower((string) $warning);

            foreach ($blockingMarkers as $marker) {
                if (str_contains($lower, $marker)) {
                    return 'preliminary';
                }
            }
        }

        return 'detailed';
    }

    private function temperatureCorrectedVoltage(float $voltage, float $coefficientPercentC, float $temperatureC): float
    {
        return max($voltage * (1 + (($coefficientPercentC / 100) * ($temperatureC - 25))), 0.0);
    }

    private function dcVoltageDropPercent(float $currentA, float $lengthM, float $areaMm2, float $voltageV, string $material): float
    {
        if ($currentA <= 0 || $lengthM <= 0 || $areaMm2 <= 0 || $voltageV <= 0) {
            return 0.0;
        }

        $resistivity = $this->resistivity($material);
        $resistance = (2 * $resistivity * $lengthM) / $areaMm2;
        $voltageDrop = $currentA * $resistance;

        return ($voltageDrop / $voltageV) * 100;
    }

    private function acVoltageDropPercent(float $currentA, float $lengthM, float $areaMm2, string $phase, string $material): float
    {
        if ($currentA <= 0 || $lengthM <= 0 || $areaMm2 <= 0) {
            return 0.0;
        }

        $resistivity = $this->resistivity($material);
        $lineVoltage = $phase === 'three_phase' ? 400.0 : 230.0;
        $multiplier = $phase === 'three_phase' ? sqrt(3) : 2.0;
        $resistance = ($resistivity * $lengthM) / $areaMm2;
        $voltageDrop = $multiplier * $currentA * $resistance;

        return ($voltageDrop / $lineVoltage) * 100;
    }

    private function acCurrent(float $acPowerKw, string $phase, float $inverterEfficiency): float
    {
        if ($acPowerKw <= 0) {
            return 0.0;
        }

        $efficiency = max($inverterEfficiency, 0.01);

        return $phase === 'three_phase'
            ? ($acPowerKw * 1000) / (sqrt(3) * 400 * $efficiency)
            : ($acPowerKw * 1000) / (230 * $efficiency);
    }

    private function resistivity(string $material): float
    {
        return $material === 'aluminium' ? 0.028264 : 0.017241;
    }

    private function nextStandardRating(float $amps): int
    {
        $standards = [6, 10, 16, 20, 25, 32, 40, 50, 63, 80, 100, 125, 160, 200, 250, 315, 400, 500, 630, 800, 1000];
        $required = max((int) ceil($amps), 1);

        foreach ($standards as $rating) {
            if ($rating >= $required) {
                return $rating;
            }
        }

        return (int) ceil($required / 100) * 100;
    }

    private function percent(float $value): float
    {
        return $this->clamp($value / 100, 0.0, 1.0);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return min(max($value, $min), $max);
    }
}
