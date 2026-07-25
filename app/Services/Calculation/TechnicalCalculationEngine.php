<?php

namespace App\Services\Calculation;

use App\Models\Project;
use App\Support\CalculationFormula;
use App\Support\CalculationWarning;

class TechnicalCalculationEngine
{
    public const VERSION = 'nexorin-technical-lv-v1.4.0';

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
            $warnings[] = CalculationWarning::make(
                'LOAD_SOURCE_MISSING',
                'critical',
                'Load Source Missing',
                'Load source is missing or has zero daily energy. PV sizing result is therefore limited.',
                'Complete load analysis or enter a valid daily energy value before using the design result.'
            );
        }

        if ($psh <= 0) {
            $psh = 4.5;
            $warnings[] = CalculationWarning::make(
                'SOLAR_PROFILE_FALLBACK',
                'caution',
                'Solar Profile Fallback',
                'Solar profile is missing. The calculation used a fallback peak sun hour value of 4.5 h/day.',
                'Provide a project solar profile from API data, measured data, or a documented manual value.'
            );
        }

        if (! $hasRoofSource) {
            $warnings[] = CalculationWarning::make(
                'ROOF_AREA_MISSING',
                'warning',
                'Usable Roof Area Missing',
                'Usable roof area is missing. Roof limitation was not applied.',
                'Complete roof/site survey before finalizing module count and layout.'
            );
        }

        if ($recommendation === []) {
            $warnings[] = CalculationWarning::make(
                'RECOMMENDATION_SOURCE_MISSING',
                'info',
                'Recommendation Source Missing',
                'System recommendation source is missing. The selected system type only follows the calculation form input.',
                'Generate a system recommendation to document why the selected topology is suitable.'
            );
        }

        foreach (($roof['warnings'] ?? []) as $roofWarning) {
            if (is_string($roofWarning) && $roofWarning !== '') {
                $warnings[] = CalculationWarning::fromLegacy($roofWarning, 'roof_site_survey', 'ROOF_SITE_WARNING');
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
            $warnings[] = CalculationWarning::make(
                'PV_ARRAY_ROOF_LIMITED',
                'warning',
                'PV Array Roof Limited',
                'PV array is roof limited. Installed PV capacity is lower than the target offset requirement.',
                'Review available mounting area, module wattage, target offset, or layout constraints.'
            );
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

        if ($dcAcRatio > 0 && $dcAcRatio < 0.95) {
            $warnings[] = CalculationWarning::make(
                'DC_AC_RATIO_LOW',
                'caution',
                'Low DC/AC Ratio',
                'DC/AC ratio is low. The inverter capacity may be oversized relative to the installed PV array, reducing utilization and financial performance.',
                'Review inverter capacity, increase PV array size, or document the reason for oversizing.'
            );
        }

        if ($dcAcRatio > 0 && ($dcAcRatio < 0.75 || $dcAcRatio > 1.50)) {
            $warnings[] = CalculationWarning::make(
                'DC_AC_RATIO_OUTSIDE_BAND',
                'warning',
                'DC/AC Ratio Outside Design Band',
                'DC/AC ratio is outside the common preliminary design band of 0.75 to 1.50.',
                'Confirm inverter selection and PV array size against project objectives and manufacturer guidance.'
            );
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
        $stringModuleCounts = [];

        if ($moduleCount > 0 && $minimumModulesPerString > 0 && $maximumModulesPerString >= $minimumModulesPerString) {
            $availableStringInputs = max(1, $inverterCount * $inverterMpptCount);
            $maxCandidateStrings = min($moduleCount, $availableStringInputs);

            $validArchitectures = [];

            for ($candidateStrings = 1; $candidateStrings <= $maxCandidateStrings; $candidateStrings++) {
                $candidateDistribution = $this->distributeModules($moduleCount, $candidateStrings);
                $candidateMinimum = min($candidateDistribution);
                $candidateMaximum = max($candidateDistribution);

                if ($candidateMinimum < $minimumModulesPerString || $candidateMaximum > $maximumModulesPerString) {
                    continue;
                }

                /*
                 * Prefer an architecture that can be divided evenly across the
                 * physical inverter units. This prevents a technically valid but
                 * operationally poor result such as five strings across two equal
                 * inverters when six balanced strings are also voltage-compatible.
                 * Among equally balanced choices, fewer strings are preferred to
                 * keep the preliminary BOS simple.
                 */
                $validArchitectures[] = [
                    'string_count' => $candidateStrings,
                    'distribution' => $candidateDistribution,
                    'maximum' => $candidateMaximum,
                    'balance_penalty' => $inverterCount > 1 && $candidateStrings % $inverterCount !== 0 ? 1 : 0,
                ];
            }

            if ($validArchitectures !== []) {
                usort($validArchitectures, static fn (array $left, array $right): int => [
                    $left['balance_penalty'],
                    $left['string_count'],
                ] <=> [
                    $right['balance_penalty'],
                    $right['string_count'],
                ]);

                $selectedArchitecture = $validArchitectures[0];
                $stringCount = (int) $selectedArchitecture['string_count'];
                $stringModuleCounts = $selectedArchitecture['distribution'];
                // Voltage checks use the longest string as the conservative case.
                $modulesPerString = (int) $selectedArchitecture['maximum'];
            }

            if ($stringCount === 0) {
                $modulesPerString = $moduleCount >= $maximumModulesPerString
                    ? $maximumModulesPerString
                    : max($minimumModulesPerString, $moduleCount);
                $modulesPerString = min($modulesPerString, $moduleCount);
                $stringCount = (int) ceil($moduleCount / max($modulesPerString, 1));
                $stringModuleCounts = $this->distributeModules($moduleCount, $stringCount);
                $modulesPerString = $stringModuleCounts !== [] ? max($stringModuleCounts) : $modulesPerString;
            }
        }

        if ($minimumModulesPerString > $maximumModulesPerString || $maximumModulesPerString === 0) {
            $warnings[] = CalculationWarning::make(
                'STRING_MPPT_INCOMPATIBLE',
                'critical',
                'String and MPPT Incompatible',
                'Selected module and inverter are not compatible within the MPPT and maximum DC voltage window.',
                'Change module count per string, select another inverter, or verify datasheet voltage limits.'
            );
        }

        $availableMpptInputs = $inverterCount * $inverterMpptCount;
        if ($stringCount > 0 && $availableMpptInputs > 0 && $stringCount > $availableMpptInputs) {
            $warnings[] = CalculationWarning::make(
                'STRING_COUNT_EXCEEDS_MPPT',
                'critical',
                'String Count Exceeds MPPT Inputs',
                'String count exceeds the available MPPT inputs. Select a larger inverter set or adjust the module layout.',
                'Increase inverter or MPPT capacity, change string layout, or revise module count.'
            );
        }

        $parallelStringsPerMppt = $availableMpptInputs > 0
            ? (int) ceil($stringCount / $availableMpptInputs)
            : 0;
        $dcInputCurrentA = $parallelStringsPerMppt * $moduleIsc * 1.25;

        if ($dcInputCurrentA > 0 && $inverterMaxInputCurrentA > 0 && $dcInputCurrentA > $inverterMaxInputCurrentA) {
            $warnings[] = CalculationWarning::make(
                'DC_INPUT_CURRENT_EXCEEDS_LIMIT',
                'critical',
                'DC Input Current Exceeds Limit',
                'Calculated DC input current exceeds the inverter input-current limit per inverter.',
                'Reduce parallel strings per MPPT or select an inverter with a higher input current rating.'
            );
        }

        $stringVocColdV = $modulesPerString * $moduleVocCold;
        $stringVmpHotV = $modulesPerString * $moduleVmpHot;
        $inverterStringAssignments = $this->inverterStringAssignments(
            $stringModuleCounts,
            $inverterCount,
            $inverterMpptCount,
            $moduleWp,
            $inverterAcPowerKw,
            $moduleVocCold,
            $moduleVmpHot
        );
        $usedMpptInputs = collect($inverterStringAssignments)->sum(
            fn (array $assignment): int => (int) ($assignment['used_mppt_count'] ?? 0)
        );
        $acNominalVoltageV = $supplyPhase === 'three_phase' ? 400.0 : 230.0;
        $inverterUnitRatedCurrentA = $this->acCurrent($inverterAcPowerKw, $supplyPhase, $inverterEfficiency);
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
                $warnings[] = CalculationWarning::make(
                    'HYBRID_BACKUP_DURATION_ZERO',
                    'warning',
                    'Hybrid Backup Duration Zero',
                    'Hybrid system is selected but backup duration is zero.',
                    'Enter required backup duration or switch the design to on-grid if backup is not needed.'
                );
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
            $warnings[] = CalculationWarning::make(
                'DC_VOLTAGE_DROP_EXCEEDS_LIMIT',
                'warning',
                'DC Voltage Drop Exceeds Limit',
                'DC voltage drop exceeds the selected maximum design limit.',
                'Increase cable cross-section, shorten route length, or revise the voltage-drop limit with justification.'
            );
        }

        if ($acVoltageDropPercent > $maximumVoltageDropPercent) {
            $warnings[] = CalculationWarning::make(
                'AC_VOLTAGE_DROP_EXCEEDS_LIMIT',
                'warning',
                'AC Voltage Drop Exceeds Limit',
                'AC voltage drop exceeds the selected maximum design limit.',
                'Increase cable cross-section, shorten route length, or revise AC routing assumptions.'
            );
        }

        $inverterFeederBreakerA = $this->nextStandardRating($inverterUnitRatedCurrentA * 1.25);
        $mainAcBreakerA = $this->nextStandardRating($acCurrentA * 1.25);
        $acCombinerBusA = $this->nextStandardRating($mainAcBreakerA * 1.25);

        $protection = [
            'dc_isolator_a' => $this->nextStandardRating($dcInputCurrentA),
            'dc_fuse_a' => $this->nextStandardRating($moduleIsc * 1.56),
            // Backward-compatible key. APP67L.3-R4 treats this as the inverter
            // feeder rating, not the aggregate plant-main rating.
            'ac_breaker_a' => $inverterFeederBreakerA,
            'inverter_feeder_breaker_a' => $inverterFeederBreakerA,
            'inverter_feeder_isolator_a' => $inverterFeederBreakerA,
            'main_ac_breaker_a' => $mainAcBreakerA,
            'pcc_isolator_a' => $mainAcBreakerA,
            'ac_combiner_bus_a' => $acCombinerBusA,
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
            'string_module_counts' => $stringModuleCounts,
            'string_distribution_label' => $this->stringDistributionLabel($stringModuleCounts),
            'inverter_string_assignments' => $inverterStringAssignments,
            'available_mppt_inputs' => $availableMpptInputs,
            'used_mppt_inputs' => $usedMpptInputs,
            'inverter_unit_ac_kw' => round($inverterAcPowerKw, 3),
            'inverter_unit_rated_current_a' => round($inverterUnitRatedCurrentA, 3),
            'ac_nominal_voltage_v' => round($acNominalVoltageV, 1),
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
            'assumption_snapshot' => $this->assumptionSnapshot([
                'daily_load_kwh' => round($dailyLoadKwh, 3),
                'critical_daily_load_kwh' => round($criticalDailyLoadKwh, 3),
                'peak_demand_kw' => round($peakDemandKw, 3),
                'average_psh' => round($psh, 3),
                'usable_roof_area_m2' => round($usableRoofAreaM2, 3),
                'weighted_shading_percent' => round($weightedShadingPercent, 3),
                'energy_offset_target_percent' => round($offsetTarget * 100, 3),
                'performance_ratio' => round($performanceRatio, 4),
                'effective_performance_ratio' => round($effectivePerformanceRatio, 4),
                'module_power_wp' => round($moduleWp, 3),
                'module_area_m2' => round($moduleAreaM2, 3),
                'module_voc_v' => round($moduleVoc, 3),
                'module_vmp_v' => round($moduleVmp, 3),
                'module_isc_a' => round($moduleIsc, 3),
                'module_imp_a' => round($moduleImp, 3),
                'inverter_ac_power_kw' => round($inverterAcPowerKw, 3),
                'desired_dc_ac_ratio' => round($desiredDcAcRatio, 3),
                'battery_backup_hours' => round($backupHours, 3),
                'battery_dod_percent' => round($batteryDod * 100, 3),
                'battery_efficiency_percent' => round($batteryEfficiency * 100, 3),
                'maximum_voltage_drop_percent' => round($maximumVoltageDropPercent, 3),
                'supply_phase' => $supplyPhase,
                'system_type' => $systemType,
                'cable_material' => $cableMaterial,
            ]),
            'formula_snapshot' => $this->formulaSnapshot([
                'daily_load_kwh' => round($dailyLoadKwh, 3),
                'target_daily_energy_kwh' => round($targetDailyEnergyKwh, 3),
                'critical_daily_load_kwh' => round($criticalDailyLoadKwh, 3),
                'peak_demand_kw' => round($peakDemandKw, 3),
                'average_psh' => round($psh, 3),
                'weighted_shading_percent' => round($weightedShadingPercent, 3),
                'shading_factor' => round($shadingFactor, 4),
                'energy_offset_target_percent' => round($offsetTarget * 100, 3),
                'performance_ratio' => round($performanceRatio, 4),
                'effective_performance_ratio' => round($effectivePerformanceRatio, 4),
                'module_power_wp' => round($moduleWp, 3),
                'module_area_m2' => round($moduleAreaM2, 3),
                'usable_roof_area_m2' => round($usableRoofAreaM2, 3),
                'required_pv_kw' => round($requiredPvKw, 3),
                'required_module_count' => $requiredModuleCount,
                'area_limited_module_count' => $areaLimitedModuleCount,
                'module_count' => $moduleCount,
                'installed_pv_kw' => round($installedPvKw, 3),
                'inverter_count' => $inverterCount,
                'inverter_ac_power_kw' => round($inverterAcPowerKw, 3),
                'desired_dc_ac_ratio' => round($desiredDcAcRatio, 3),
                'total_inverter_ac_kw' => round($totalInverterAcKw, 3),
                'dc_ac_ratio' => round($dcAcRatio, 3),
                'module_voc_cold_v' => round($moduleVocCold, 3),
                'module_vmp_hot_v' => round($moduleVmpHot, 3),
                'minimum_modules_per_string' => $minimumModulesPerString,
                'maximum_modules_per_string' => $maximumModulesPerString,
                'modules_per_string' => $modulesPerString,
                'string_count' => $stringCount,
                'string_voc_cold_v' => round($stringVocColdV, 3),
                'string_vmp_hot_v' => round($stringVmpHotV, 3),
                'estimated_daily_generation_kwh' => round($estimatedDailyGenerationKwh, 3),
                'annual_energy_kwh' => round($annualEnergyKwh, 3),
                'battery_backup_hours' => round($backupHours, 3),
                'battery_dod_percent' => round($batteryDod * 100, 3),
                'battery_efficiency_percent' => round($batteryEfficiency * 100, 3),
                'battery_nominal_kwh' => round($batteryNominalKwh, 3),
                'battery_minimum_power_kw' => round($batteryMinimumPowerKw, 3),
                'dc_current_a' => round($moduleImp * max($parallelStringsPerMppt, 1), 3),
                'dc_cable_length_m' => round($dcCableLengthM, 3),
                'dc_cable_area_mm2' => round($dcCableAreaMm2, 3),
                'dc_voltage_drop_percent' => round($dcVoltageDropPercent, 3),
                'ac_current_a' => round($acCurrentA, 3),
                'ac_cable_length_m' => round($acCableLengthM, 3),
                'ac_cable_area_mm2' => round($acCableAreaMm2, 3),
                'ac_voltage_drop_percent' => round($acVoltageDropPercent, 3),
                'system_type' => $systemType,
                'supply_phase' => $supplyPhase,
                'cable_material' => $cableMaterial,
            ]),
            'warnings' => CalculationWarning::unique($warnings),
            'source_snapshot' => $sources,
        ];
    }

    /**
     * Assign the actual string distribution to physical inverter and MPPT
     * instances. No synthetic modules or aggregate-only tracker rows are
     * introduced. The allocation remains deterministic for audit and SLD use.
     *
     * @param  array<int, int>  $stringModuleCounts
     * @return array<int, array<string, mixed>>
     */
    private function inverterStringAssignments(
        array $stringModuleCounts,
        int $inverterCount,
        int $mpptCount,
        float $moduleWp,
        float $inverterAcPowerKw,
        float $moduleVocCold,
        float $moduleVmpHot
    ): array {
        if ($stringModuleCounts === [] || $inverterCount <= 0 || $mpptCount <= 0) {
            return [];
        }

        $assignments = [];
        for ($inverterIndex = 1; $inverterIndex <= $inverterCount; $inverterIndex++) {
            $assignments[$inverterIndex] = [
                'inverter_index' => $inverterIndex,
                'tag' => sprintf('INV-%02d', $inverterIndex),
                'rated_ac_kw' => round($inverterAcPowerKw, 3),
                'available_mppt_count' => $mpptCount,
                'used_mppt_count' => 0,
                'assigned_module_count' => 0,
                'assigned_dc_kw' => 0.0,
                'dc_ac_ratio' => 0.0,
                'strings' => [],
            ];
        }

        foreach (array_values($stringModuleCounts) as $stringOffset => $modules) {
            $inverterIndex = ($stringOffset % $inverterCount) + 1;
            $currentCount = count($assignments[$inverterIndex]['strings']);
            $mpptIndex = ($currentCount % $mpptCount) + 1;
            $stringIndex = $stringOffset + 1;
            $dcKw = ($modules * $moduleWp) / 1000;

            $assignments[$inverterIndex]['strings'][] = [
                'string_index' => $stringIndex,
                'tag' => sprintf('STR-%02d', $stringIndex),
                'modules' => $modules,
                'mppt_index' => $mpptIndex,
                'mppt_label' => sprintf('MPPT-%02d', $mpptIndex),
                'dc_kw' => round($dcKw, 3),
                'voc_cold_v' => round($modules * $moduleVocCold, 3),
                'vmp_hot_v' => round($modules * $moduleVmpHot, 3),
            ];
            $assignments[$inverterIndex]['assigned_module_count'] += $modules;
            $assignments[$inverterIndex]['assigned_dc_kw'] += $dcKw;
        }

        foreach ($assignments as &$assignment) {
            $assignment['used_mppt_count'] = count(array_unique(array_column($assignment['strings'], 'mppt_index')));
            $assignment['assigned_dc_kw'] = round((float) $assignment['assigned_dc_kw'], 3);
            $assignment['dc_ac_ratio'] = $inverterAcPowerKw > 0
                ? round((float) $assignment['assigned_dc_kw'] / $inverterAcPowerKw, 3)
                : 0.0;
        }
        unset($assignment);

        return array_values($assignments);
    }

    /** @param array<int, int> $counts */
    private function stringDistributionLabel(array $counts): string
    {
        return $counts === [] ? 'String allocation pending' : implode(' + ', $counts).' modules';
    }

    /**
     * Distribute every module across the selected strings without inventing
     * modules. The first strings receive one extra module when the total is
     * not evenly divisible (for example 13 modules becomes [7, 6]).
     *
     * @return array<int, int>
     */
    private function distributeModules(int $moduleCount, int $stringCount): array
    {
        if ($moduleCount <= 0 || $stringCount <= 0) {
            return [];
        }

        $base = intdiv($moduleCount, $stringCount);
        $remainder = $moduleCount % $stringCount;

        return array_map(
            static fn (int $index): int => $base + ($index < $remainder ? 1 : 0),
            range(0, $stringCount - 1)
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array<string, mixed>>
     */
    private function formulaSnapshot(array $values): array
    {
        $formulas = [
            'target_daily_energy_kwh' => CalculationFormula::make(
                'TARGET_DAILY_ENERGY',
                'PV sizing',
                'Target daily energy',
                'daily_load_kwh * energy_offset_target',
                'Daily load × energy offset target',
                [
                    'daily_load_kwh' => ['label' => 'Daily load energy', 'value' => $values['daily_load_kwh'] ?? 0, 'unit' => 'kWh/day'],
                    'energy_offset_target_percent' => ['label' => 'Energy offset target', 'value' => $values['energy_offset_target_percent'] ?? 0, 'unit' => '%'],
                ],
                $values['target_daily_energy_kwh'] ?? 0,
                'kWh/day',
                'Determines how much daily load the PV system should offset in the preliminary design.'
            ),
            'effective_performance_ratio' => CalculationFormula::make(
                'EFFECTIVE_PERFORMANCE_RATIO',
                'PV sizing',
                'Effective performance ratio',
                'performance_ratio * (1 - weighted_shading_percent / 100)',
                'Base PR × shading factor',
                [
                    'performance_ratio' => ['label' => 'Base performance ratio', 'value' => $values['performance_ratio'] ?? 0, 'unit' => ''],
                    'weighted_shading_percent' => ['label' => 'Weighted shading', 'value' => $values['weighted_shading_percent'] ?? 0, 'unit' => '%'],
                    'shading_factor' => ['label' => 'Shading factor', 'value' => $values['shading_factor'] ?? 0, 'unit' => ''],
                ],
                $values['effective_performance_ratio'] ?? 0,
                '',
                'Combines the base performance ratio and roof shading factor used by the energy model.'
            ),
            'required_pv_kw' => CalculationFormula::make(
                'REQUIRED_PV_CAPACITY',
                'PV sizing',
                'Required PV capacity',
                'target_daily_energy_kwh / (average_psh * effective_performance_ratio)',
                'Target daily energy ÷ (PSH × effective PR)',
                [
                    'target_daily_energy_kwh' => ['label' => 'Target daily energy', 'value' => $values['target_daily_energy_kwh'] ?? 0, 'unit' => 'kWh/day'],
                    'average_psh' => ['label' => 'Average peak sun hour', 'value' => $values['average_psh'] ?? 0, 'unit' => 'h/day'],
                    'effective_performance_ratio' => ['label' => 'Effective performance ratio', 'value' => $values['effective_performance_ratio'] ?? 0, 'unit' => ''],
                ],
                $values['required_pv_kw'] ?? 0,
                'kWp',
                'Calculates the preliminary PV array size needed to meet the selected energy offset target.'
            ),
            'required_module_count' => CalculationFormula::make(
                'REQUIRED_MODULE_COUNT',
                'PV sizing',
                'Required module count',
                'ceil(required_pv_kw * 1000 / module_power_wp)',
                'Ceiling(required PV × 1000 ÷ module power)',
                [
                    'required_pv_kw' => ['label' => 'Required PV capacity', 'value' => $values['required_pv_kw'] ?? 0, 'unit' => 'kWp'],
                    'module_power_wp' => ['label' => 'Module power', 'value' => $values['module_power_wp'] ?? 0, 'unit' => 'Wp'],
                ],
                $values['required_module_count'] ?? 0,
                'pcs',
                'Converts required PV capacity into the minimum module quantity before applying roof-area limits.'
            ),
            'area_limited_module_count' => CalculationFormula::make(
                'AREA_LIMITED_MODULE_COUNT',
                'Roof constraint',
                'Area-limited module count',
                'floor(usable_roof_area_m2 / module_area_m2)',
                'Floor(usable roof area ÷ module area)',
                [
                    'usable_roof_area_m2' => ['label' => 'Usable roof area', 'value' => $values['usable_roof_area_m2'] ?? 0, 'unit' => 'm2'],
                    'module_area_m2' => ['label' => 'Module area', 'value' => $values['module_area_m2'] ?? 0, 'unit' => 'm2/module'],
                ],
                $values['area_limited_module_count'] ?? 0,
                'pcs',
                'Limits module quantity based on the preliminary usable roof area recorded in the site survey.'
            ),
            'module_count' => CalculationFormula::make(
                'FINAL_MODULE_COUNT',
                'Roof constraint',
                'Final module count',
                'min(required_module_count, area_limited_module_count)',
                'Minimum(required modules, roof-limited modules)',
                [
                    'required_module_count' => ['label' => 'Required module count', 'value' => $values['required_module_count'] ?? 0, 'unit' => 'pcs'],
                    'area_limited_module_count' => ['label' => 'Area-limited module count', 'value' => $values['area_limited_module_count'] ?? 0, 'unit' => 'pcs'],
                ],
                $values['module_count'] ?? 0,
                'pcs',
                'Selects the effective module quantity used by downstream string design, BOM, and reports.'
            ),
            'installed_pv_kw' => CalculationFormula::make(
                'INSTALLED_PV_CAPACITY',
                'PV sizing',
                'Installed PV capacity',
                'module_count * module_power_wp / 1000',
                'Module count × module power ÷ 1000',
                [
                    'module_count' => ['label' => 'Module count', 'value' => $values['module_count'] ?? 0, 'unit' => 'pcs'],
                    'module_power_wp' => ['label' => 'Module power', 'value' => $values['module_power_wp'] ?? 0, 'unit' => 'Wp'],
                ],
                $values['installed_pv_kw'] ?? 0,
                'kWp',
                'Calculates final installed PV capacity after roof constraints and module rounding.'
            ),
            'inverter_count' => CalculationFormula::make(
                'INVERTER_COUNT_SELECTION',
                'Inverter sizing',
                'Inverter count',
                'nearest count to installed_pv_kw / (inverter_ac_power_kw * desired_dc_ac_ratio)',
                'Nearest integer to installed PV ÷ (inverter AC × desired DC/AC)',
                [
                    'installed_pv_kw' => ['label' => 'Installed PV capacity', 'value' => $values['installed_pv_kw'] ?? 0, 'unit' => 'kWp'],
                    'inverter_ac_power_kw' => ['label' => 'Inverter AC power', 'value' => $values['inverter_ac_power_kw'] ?? 0, 'unit' => 'kW/unit'],
                    'desired_dc_ac_ratio' => ['label' => 'Desired DC/AC ratio', 'value' => $values['desired_dc_ac_ratio'] ?? 0, 'unit' => ''],
                ],
                $values['inverter_count'] ?? 0,
                'unit',
                'Chooses the inverter quantity that keeps DC/AC ratio closest to the selected design target.'
            ),
            'dc_ac_ratio' => CalculationFormula::make(
                'DC_AC_RATIO',
                'Inverter sizing',
                'DC/AC ratio',
                'installed_pv_kw / total_inverter_ac_kw',
                'Installed PV ÷ total inverter AC capacity',
                [
                    'installed_pv_kw' => ['label' => 'Installed PV capacity', 'value' => $values['installed_pv_kw'] ?? 0, 'unit' => 'kWp'],
                    'total_inverter_ac_kw' => ['label' => 'Total inverter AC power', 'value' => $values['total_inverter_ac_kw'] ?? 0, 'unit' => 'kW'],
                ],
                $values['dc_ac_ratio'] ?? 0,
                '',
                'Checks whether inverter capacity is reasonably matched to the installed DC array capacity.'
            ),
            'maximum_modules_per_string' => CalculationFormula::make(
                'MAXIMUM_MODULES_PER_STRING',
                'String design',
                'Maximum modules per string',
                'min(floor(inverter_max_dc_voltage / corrected_voc), floor(mppt_max_voltage / corrected_vmp))',
                'Minimum of Voc limit and MPPT upper voltage limit',
                [
                    'module_voc_cold_v' => ['label' => 'Corrected cold module Voc', 'value' => $values['module_voc_cold_v'] ?? 0, 'unit' => 'V'],
                    'module_vmp_hot_v' => ['label' => 'Corrected hot module Vmp', 'value' => $values['module_vmp_hot_v'] ?? 0, 'unit' => 'V'],
                ],
                $values['maximum_modules_per_string'] ?? 0,
                'pcs/string',
                'Prevents string voltage from exceeding inverter maximum DC voltage and MPPT upper window.'
            ),
            'minimum_modules_per_string' => CalculationFormula::make(
                'MINIMUM_MODULES_PER_STRING',
                'String design',
                'Minimum modules per string',
                'ceil(inverter_mppt_min_voltage / corrected_hot_module_vmp)',
                'Ceiling(MPPT minimum voltage ÷ corrected hot module Vmp)',
                [
                    'module_vmp_hot_v' => ['label' => 'Corrected hot module Vmp', 'value' => $values['module_vmp_hot_v'] ?? 0, 'unit' => 'V'],
                ],
                $values['minimum_modules_per_string'] ?? 0,
                'pcs/string',
                'Ensures string operating voltage stays above the inverter MPPT lower limit under hot conditions.'
            ),
            'annual_energy_kwh' => CalculationFormula::make(
                'ANNUAL_ENERGY_ESTIMATE',
                'Energy estimate',
                'Annual energy estimate',
                'installed_pv_kw * average_psh * effective_performance_ratio * 365',
                'Installed PV × PSH × effective PR × 365',
                [
                    'installed_pv_kw' => ['label' => 'Installed PV capacity', 'value' => $values['installed_pv_kw'] ?? 0, 'unit' => 'kWp'],
                    'average_psh' => ['label' => 'Average peak sun hour', 'value' => $values['average_psh'] ?? 0, 'unit' => 'h/day'],
                    'effective_performance_ratio' => ['label' => 'Effective performance ratio', 'value' => $values['effective_performance_ratio'] ?? 0, 'unit' => ''],
                ],
                $values['annual_energy_kwh'] ?? 0,
                'kWh/year',
                'Estimates annual PV production using a preliminary PSH-based energy model.'
            ),
            'battery_nominal_kwh' => CalculationFormula::make(
                'BATTERY_NOMINAL_CAPACITY',
                'Battery sizing',
                'Battery nominal capacity',
                'usable_backup_energy / (battery_dod * battery_efficiency)',
                'Usable backup energy ÷ (DoD × efficiency)',
                [
                    'critical_daily_load_kwh' => ['label' => 'Critical daily load', 'value' => $values['critical_daily_load_kwh'] ?? 0, 'unit' => 'kWh/day'],
                    'battery_backup_hours' => ['label' => 'Backup duration', 'value' => $values['battery_backup_hours'] ?? 0, 'unit' => 'hours'],
                    'battery_dod_percent' => ['label' => 'Battery DoD', 'value' => $values['battery_dod_percent'] ?? 0, 'unit' => '%'],
                    'battery_efficiency_percent' => ['label' => 'Battery efficiency', 'value' => $values['battery_efficiency_percent'] ?? 0, 'unit' => '%'],
                ],
                $values['battery_nominal_kwh'] ?? 0,
                'kWh',
                'Calculates nominal battery capacity for hybrid backup after depth-of-discharge and efficiency assumptions.'
            ),
            'dc_voltage_drop_percent' => CalculationFormula::make(
                'DC_VOLTAGE_DROP',
                'Cable check',
                'DC voltage drop',
                '(current * 2 * resistivity * cable_length / cable_area) / string_voltage * 100',
                'DC voltage drop ÷ string voltage × 100',
                [
                    'dc_current_a' => ['label' => 'DC design current', 'value' => $values['dc_current_a'] ?? 0, 'unit' => 'A'],
                    'dc_cable_length_m' => ['label' => 'DC cable length', 'value' => $values['dc_cable_length_m'] ?? 0, 'unit' => 'm'],
                    'dc_cable_area_mm2' => ['label' => 'DC cable area', 'value' => $values['dc_cable_area_mm2'] ?? 0, 'unit' => 'mm2'],
                    'string_vmp_hot_v' => ['label' => 'Hot string Vmp', 'value' => $values['string_vmp_hot_v'] ?? 0, 'unit' => 'V'],
                ],
                $values['dc_voltage_drop_percent'] ?? 0,
                '%',
                'Estimates preliminary two-wire DC voltage drop using conductor material, length, area, current, and string voltage.'
            ),
            'ac_voltage_drop_percent' => CalculationFormula::make(
                'AC_VOLTAGE_DROP',
                'Cable check',
                'AC voltage drop',
                'phase multiplier * current * resistance / line_voltage * 100',
                'AC voltage drop ÷ nominal line voltage × 100',
                [
                    'ac_current_a' => ['label' => 'AC design current', 'value' => $values['ac_current_a'] ?? 0, 'unit' => 'A'],
                    'ac_cable_length_m' => ['label' => 'AC cable length', 'value' => $values['ac_cable_length_m'] ?? 0, 'unit' => 'm'],
                    'ac_cable_area_mm2' => ['label' => 'AC cable area', 'value' => $values['ac_cable_area_mm2'] ?? 0, 'unit' => 'mm2'],
                    'supply_phase' => ['label' => 'Supply phase', 'value' => $values['supply_phase'] ?? '-', 'unit' => ''],
                ],
                $values['ac_voltage_drop_percent'] ?? 0,
                '%',
                'Estimates preliminary AC voltage drop using conductor material, cable route, supply phase, and calculated AC current.'
            ),
        ];

        if (($values['system_type'] ?? 'on_grid') !== 'hybrid') {
            unset($formulas['battery_nominal_kwh']);
        }

        return $formulas;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{label:string,value:mixed,unit:string,source:string,description:string}>
     */
    private function assumptionSnapshot(array $values): array
    {
        return [
            'engine_version' => [
                'label' => 'Calculation engine version',
                'value' => self::VERSION,
                'unit' => '',
                'source' => 'system',
                'description' => 'Version of the low-voltage rooftop PV technical calculation engine used for this result.',
            ],
            'system_type' => [
                'label' => 'System type',
                'value' => $values['system_type'] ?? '-',
                'unit' => '',
                'source' => 'calculation_input',
                'description' => 'Selected PLTS configuration used by the sizing engine.',
            ],
            'daily_load_kwh' => [
                'label' => 'Daily load energy',
                'value' => $values['daily_load_kwh'] ?? 0,
                'unit' => 'kWh/day',
                'source' => 'load_analysis',
                'description' => 'Daily energy demand used as the PV sizing basis.',
            ],
            'critical_daily_load_kwh' => [
                'label' => 'Critical daily load',
                'value' => $values['critical_daily_load_kwh'] ?? 0,
                'unit' => 'kWh/day',
                'source' => 'load_analysis',
                'description' => 'Critical load energy used for hybrid backup sizing.',
            ],
            'peak_demand_kw' => [
                'label' => 'Peak demand',
                'value' => $values['peak_demand_kw'] ?? 0,
                'unit' => 'kW',
                'source' => 'load_analysis',
                'description' => 'Peak load basis for inverter, battery power, and AC current checks.',
            ],
            'average_psh' => [
                'label' => 'Average peak sun hour',
                'value' => $values['average_psh'] ?? 0,
                'unit' => 'h/day',
                'source' => 'solar_profile',
                'description' => 'Average daily solar resource used in preliminary energy production estimation.',
            ],
            'usable_roof_area_m2' => [
                'label' => 'Usable roof area',
                'value' => $values['usable_roof_area_m2'] ?? 0,
                'unit' => 'm2',
                'source' => 'roof_site_survey',
                'description' => 'Available roof area after applying basic site limitations.',
            ],
            'weighted_shading_percent' => [
                'label' => 'Weighted shading',
                'value' => $values['weighted_shading_percent'] ?? 0,
                'unit' => '%',
                'source' => 'roof_site_survey',
                'description' => 'Weighted shading percentage used to adjust the effective performance ratio.',
            ],
            'energy_offset_target_percent' => [
                'label' => 'Energy offset target',
                'value' => $values['energy_offset_target_percent'] ?? 0,
                'unit' => '%',
                'source' => 'calculation_input',
                'description' => 'Target share of daily load to be offset by PV generation.',
            ],
            'performance_ratio' => [
                'label' => 'Base performance ratio',
                'value' => $values['performance_ratio'] ?? 0,
                'unit' => '',
                'source' => 'calculation_input',
                'description' => 'Base performance ratio before shading adjustment.',
            ],
            'effective_performance_ratio' => [
                'label' => 'Effective performance ratio',
                'value' => $values['effective_performance_ratio'] ?? 0,
                'unit' => '',
                'source' => 'derived',
                'description' => 'Performance ratio after applying the shading factor.',
            ],
            'module_power_wp' => [
                'label' => 'Module power',
                'value' => $values['module_power_wp'] ?? 0,
                'unit' => 'Wp',
                'source' => 'module_datasheet',
                'description' => 'PV module rated power used to calculate module quantity.',
            ],
            'inverter_ac_power_kw' => [
                'label' => 'Inverter AC power',
                'value' => $values['inverter_ac_power_kw'] ?? 0,
                'unit' => 'kW/unit',
                'source' => 'inverter_datasheet',
                'description' => 'Nominal AC capacity per inverter used for inverter count and DC/AC ratio.',
            ],
            'desired_dc_ac_ratio' => [
                'label' => 'Desired DC/AC ratio',
                'value' => $values['desired_dc_ac_ratio'] ?? 0,
                'unit' => '',
                'source' => 'calculation_input',
                'description' => 'Target ratio used to choose the initial inverter count.',
            ],
            'maximum_voltage_drop_percent' => [
                'label' => 'Maximum voltage drop',
                'value' => $values['maximum_voltage_drop_percent'] ?? 0,
                'unit' => '%',
                'source' => 'calculation_input',
                'description' => 'Maximum preliminary voltage-drop limit used for DC and AC cable checks.',
            ],
            'battery_backup_hours' => [
                'label' => 'Battery backup duration',
                'value' => $values['battery_backup_hours'] ?? 0,
                'unit' => 'hours',
                'source' => 'calculation_input',
                'description' => 'Backup duration target used when the selected system is hybrid.',
            ],
            'battery_dod_percent' => [
                'label' => 'Battery DoD',
                'value' => $values['battery_dod_percent'] ?? 0,
                'unit' => '%',
                'source' => 'battery_datasheet',
                'description' => 'Allowed battery depth of discharge used for nominal capacity sizing.',
            ],
            'battery_efficiency_percent' => [
                'label' => 'Battery efficiency',
                'value' => $values['battery_efficiency_percent'] ?? 0,
                'unit' => '%',
                'source' => 'battery_datasheet',
                'description' => 'Battery round-trip efficiency assumption used for hybrid sizing.',
            ],
            'cable_material' => [
                'label' => 'Cable material',
                'value' => $values['cable_material'] ?? '-',
                'unit' => '',
                'source' => 'calculation_input',
                'description' => 'Cable conductor material used to select resistivity for voltage-drop estimation.',
            ],
            'supply_phase' => [
                'label' => 'Supply phase',
                'value' => $values['supply_phase'] ?? '-',
                'unit' => '',
                'source' => 'load_analysis',
                'description' => 'Electrical phase configuration used for AC current and voltage-drop calculations.',
            ],
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
            if (CalculationWarning::severity($warning) === 'critical') {
                return 'preliminary';
            }

            $lower = strtolower(CalculationWarning::message($warning));

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
