<?php

namespace App\Services\Recommendation;

use App\Models\Project;
use App\Models\SystemRecommendation;
use App\Services\Solar\SolarDatasetAssessment;
use Illuminate\Support\Collection;

class SystemRecommendationEngine
{
    public const VERSION = '1.0.0';

    /**
     * Criterion weights follow the preliminary model documented in the
     * NEXORIN proposal. They are configuration values, not universal facts.
     *
     * @var array<string, float>
     */
    private const WEIGHTS = [
        'grid_stability' => 0.20,
        'outage_exposure' => 0.15,
        'critical_backup' => 0.15,
        'night_load' => 0.10,
        'day_load' => 0.15,
        'self_sufficiency' => 0.10,
        'budget_alignment' => 0.10,
        'roof_export' => 0.05,
    ];

    /**
     * Evaluate on-grid and hybrid suitability for one project.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function evaluate(Project $project, array $inputs): array
    {
        $project->loadMissing([
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'roofSurfaces',
        ]);

        $sourceAssessment = $this->assessSources($project, $inputs);
        $hardConstraints = $sourceAssessment['hard_constraints'];
        $warnings = $sourceAssessment['warnings'];
        $sourceSnapshot = $sourceAssessment['source_snapshot'];

        if ($hardConstraints !== []) {
            return [
                'engine_version' => self::VERSION,
                'result_status' => 'incomplete',
                'engine_recommended_system' => null,
                'recommended_system' => null,
                'on_grid_score' => null,
                'hybrid_score' => null,
                'decisiveness_index' => null,
                'hard_constraints' => $hardConstraints,
                'warnings' => $warnings,
                'reasons' => [],
                'source_snapshot' => $sourceSnapshot,
                'scores' => [],
            ];
        }

        $load = $sourceSnapshot['load'];
        $roof = $sourceSnapshot['roof'];

        $ratings = $this->buildRatings($inputs, $load, $roof);
        $scoreRows = [];
        $totals = ['on_grid' => 0.0, 'hybrid' => 0.0];

        foreach ($ratings as $sortOrder => $criterion) {
            $weight = self::WEIGHTS[$criterion['code']];

            foreach (SystemRecommendation::SYSTEMS as $alternative) {
                $rating = $this->clamp((float) $criterion[$alternative]['rating']);
                $weightedScore = $rating * $weight;
                $totals[$alternative] += $weightedScore;

                $scoreRows[] = [
                    'alternative' => $alternative,
                    'criterion_code' => $criterion['code'],
                    'criterion_name' => $criterion['name'],
                    'weight' => $weight,
                    'rating' => round($rating, 2),
                    'weighted_score' => round($weightedScore, 4),
                    'rationale' => $criterion[$alternative]['rationale'],
                    'sort_order' => $sortOrder + 1,
                ];
            }
        }

        $onGridScore = round($totals['on_grid'], 2);
        $hybridScore = round($totals['hybrid'], 2);
        $engineRecommendedSystem = $hybridScore > $onGridScore
            ? 'hybrid'
            : 'on_grid';
        $highestScore = max($onGridScore, $hybridScore);
        $secondScore = min($onGridScore, $hybridScore);
        $decisivenessIndex = $highestScore > 0
            ? round((($highestScore - $secondScore) / $highestScore) * 100, 2)
            : 0.0;
        $resultStatus = $decisivenessIndex < 10
            ? 'review_required'
            : 'generated';

        if ($decisivenessIndex < 10) {
            $warnings[] = 'The two alternatives have close scores. An engineer should review the assumptions before a system type is selected.';
        }

        $reasons = $this->buildReasons(
            collect($scoreRows),
            $engineRecommendedSystem,
            $inputs
        );

        return [
            'engine_version' => self::VERSION,
            'result_status' => $resultStatus,
            'engine_recommended_system' => $engineRecommendedSystem,
            'recommended_system' => $engineRecommendedSystem,
            'on_grid_score' => $onGridScore,
            'hybrid_score' => $hybridScore,
            'decisiveness_index' => $decisivenessIndex,
            'hard_constraints' => [],
            'warnings' => array_values(array_unique($warnings)),
            'reasons' => $reasons,
            'source_snapshot' => $sourceSnapshot,
            'scores' => $scoreRows,
        ];
    }

    /**
     * Inspect prerequisite modules without calculating alternative scores.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function assessSources(Project $project, array $inputs = []): array
    {
        $project->loadMissing([
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'roofSurfaces',
        ]);

        $hardConstraints = [];
        $warnings = [];

        $loadAnalysis = $project->loadAnalysis;
        $loadSummary = $loadAnalysis?->calculateSummary();
        $solarProfile = $project->solarProfile;
        $solarSummary = $solarProfile ? app(SolarDatasetAssessment::class)->assess($project, $solarProfile) : null;
        $roofSurvey = $project->roofSiteSurvey;
        $roofSummary = $roofSurvey?->calculateSummary();

        if ($loadAnalysis === null || ($loadSummary['daily_energy_kwh'] ?? 0) <= 0) {
            $hardConstraints[] = 'A load analysis with positive daily energy demand is required.';
        }

        if ($solarProfile === null || ! ($solarSummary['is_dataset_complete'] ?? false)) {
            $hardConstraints[] = 'Complete all twelve months of location and solar data before running the recommendation.';
        }

        if ($solarProfile !== null && ($solarSummary['average_psh'] ?? 0) <= 0) {
            $hardConstraints[] = 'Peak Sun Hours must be greater than zero.';
        }

        if ($roofSurvey === null || ! ($roofSummary['is_ready_for_verification'] ?? false)) {
            $hardConstraints[] = 'Complete the roof and site survey before running the recommendation.';
        }

        if ($roofSurvey !== null && ($roofSummary['usable_area_m2'] ?? 0) <= 0) {
            $hardConstraints[] = 'The project has no usable roof area after setbacks, obstructions, and walkways.';
        }

        if (($inputs['grid_connection_status'] ?? null) === 'unavailable') {
            $hardConstraints[] = 'On-grid and hybrid systems both require an available utility grid in the current prototype. Evaluate an off-grid system in the product roadmap instead.';
        }

        if ($solarProfile !== null && ($solarSummary['is_dataset_complete'] ?? false) && ($solarSummary['state'] ?? null) !== 'reviewed') {
            $warnings[] = 'The location and solar dataset is complete but the current dataset review has not been recorded.';
        }

        if ($roofSurvey !== null && $roofSurvey->quality_status !== 'verified') {
            $warnings[] = 'Roof and site survey data are complete but not engineer verified.';
        }

        if ($roofSurvey?->structural_review_status === 'required') {
            $warnings[] = 'A structural review is required before final engineering approval.';
        }

        foreach (($roofSummary['warnings'] ?? []) as $warning) {
            $warnings[] = $warning;
        }

        if (
            (float) ($inputs['backup_target_hours'] ?? 0) > 0
            && (float) ($loadSummary['critical_daily_energy_kwh'] ?? 0) <= 0
        ) {
            $warnings[] = 'A backup duration was requested, but no appliance has been marked as a critical load.';
        }

        if (($inputs['export_policy'] ?? 'unknown') === 'unknown') {
            $warnings[] = 'The export policy is unknown. Confirm utility and project requirements before final design.';
        }

        if (
            (float) ($inputs['target_self_sufficiency_percent'] ?? 0) >= 70
            && ($inputs['budget_flexibility'] ?? 'balanced') === 'strict'
        ) {
            $warnings[] = 'A high self-sufficiency target may conflict with a strict investment budget.';
        }

        return [
            'hard_constraints' => array_values(array_unique($hardConstraints)),
            'warnings' => array_values(array_unique($warnings)),
            'source_snapshot' => [
                'load' => [
                    'available' => $loadAnalysis !== null,
                    'source_mode' => $loadAnalysis?->source_mode,
                    'daily_energy_kwh' => (float) ($loadSummary['daily_energy_kwh'] ?? 0),
                    'monthly_energy_kwh' => (float) ($loadSummary['monthly_energy_kwh'] ?? 0),
                    'peak_demand_kw' => (float) ($loadSummary['peak_demand_kw'] ?? 0),
                    'critical_daily_energy_kwh' => (float) ($loadSummary['critical_daily_energy_kwh'] ?? 0),
                    'day_share_percent' => (float) ($loadSummary['day_share_percent'] ?? 0),
                    'night_share_percent' => (float) ($loadSummary['night_share_percent'] ?? 0),
                    'updated_at' => $loadAnalysis?->updated_at?->toIso8601String(),
                ],
                'solar' => [
                    'available' => $solarProfile !== null,
                    'quality_status' => $solarProfile?->quality_status,
                    'dataset_state' => $solarSummary['state'] ?? 'no_dataset',
                    'dataset_state_label' => $solarSummary['state_label'] ?? 'No Dataset',
                    'complete_months' => (int) ($solarSummary['complete_months'] ?? 0),
                    'average_psh' => (float) ($solarSummary['average_psh'] ?? 0),
                    'annual_irradiation_kwh_m2' => $solarSummary['derived_annual_ghi_kwh_m2'] ?? null,
                    'updated_at' => $solarProfile?->updated_at?->toIso8601String(),
                ],
                'roof' => [
                    'available' => $roofSurvey !== null,
                    'quality_status' => $roofSurvey?->quality_status,
                    'surface_count' => (int) ($roofSummary['surface_count'] ?? 0),
                    'usable_area_m2' => (float) ($roofSummary['usable_area_m2'] ?? 0),
                    'weighted_shading_percent' => (float) ($roofSummary['weighted_shading_percent'] ?? 0),
                    'overall_condition' => $roofSummary['overall_condition'] ?? 'Not Available',
                    'structural_review_status' => $roofSurvey?->structural_review_status,
                    'updated_at' => $roofSurvey?->updated_at?->toIso8601String(),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $load
     * @param  array<string, mixed>  $roof
     * @return array<int, array<string, mixed>>
     */
    private function buildRatings(array $inputs, array $load, array $roof): array
    {
        $gridStatus = $inputs['grid_connection_status'];
        [$onGridGridRating, $hybridGridRating] = match ($gridStatus) {
            'occasional_outages' => [70.0, 80.0],
            'frequent_outages' => [30.0, 100.0],
            default => [100.0, 45.0],
        };

        $monthlyOutageHours = (float) $inputs['outage_frequency_per_month']
            * (float) $inputs['average_outage_duration_hours'];
        $outagePressure = match (true) {
            $monthlyOutageHours <= 0.5 => 0.0,
            $monthlyOutageHours <= 2 => 25.0,
            $monthlyOutageHours <= 6 => 50.0,
            $monthlyOutageHours <= 15 => 75.0,
            default => 100.0,
        };

        $dailyEnergy = max((float) $load['daily_energy_kwh'], 0.001);
        $criticalShare = $this->clamp(
            ((float) $load['critical_daily_energy_kwh'] / $dailyEnergy) * 100
        );
        $backupHoursPressure = $this->clamp(
            ((float) $inputs['backup_target_hours'] / 8) * 100
        );
        $backupPressure = max($criticalShare, $backupHoursPressure);

        $dayShare = $this->clamp((float) $load['day_share_percent']);
        $nightShare = $this->clamp((float) $load['night_share_percent']);
        $selfSufficiencyTarget = $this->clamp(
            (float) $inputs['target_self_sufficiency_percent']
        );

        [$onGridBudget, $hybridBudget] = match ($inputs['budget_flexibility']) {
            'strict' => [100.0, 30.0],
            'flexible' => [70.0, 100.0],
            default => [80.0, 70.0],
        };

        $usableArea = (float) $roof['usable_area_m2'];
        $areaRating = match (true) {
            $usableArea >= 50 => 100.0,
            $usableArea >= 25 => 85.0,
            $usableArea >= 10 => 65.0,
            default => 40.0,
        };
        $shadingRating = $this->clamp(
            100 - (float) $roof['weighted_shading_percent']
        );
        $roofRating = ($areaRating + $shadingRating) / 2;
        [$onGridExport, $hybridExport] = match ($inputs['export_policy']) {
            'allowed' => [100.0, 70.0],
            'limited' => [70.0, 85.0],
            'zero_export' => [45.0, 100.0],
            default => [65.0, 70.0],
        };
        $onGridRoofExport = ($roofRating * 0.60) + ($onGridExport * 0.40);
        $hybridRoofExport = ($roofRating * 0.60) + ($hybridExport * 0.40);

        $onGridCritical = 100 - $backupPressure;
        $hybridCritical = max(30, $backupPressure);
        $onGridSelfSufficiency = max(20, 100 - ($selfSufficiencyTarget * 0.8));
        $hybridSelfSufficiency = min(100, 25 + ($selfSufficiencyTarget * 0.85));

        if ($inputs['primary_objective'] === 'backup') {
            $onGridCritical -= 10;
            $hybridCritical += 10;
        }

        if ($inputs['primary_objective'] === 'self_sufficiency') {
            $onGridSelfSufficiency -= 10;
            $hybridSelfSufficiency += 10;
        }

        if ($inputs['primary_objective'] === 'savings') {
            $onGridBudget += 10;
            $hybridBudget -= 10;
        }

        if ($inputs['primary_objective'] === 'maximize_roof') {
            $onGridRoofExport += 5;
            $hybridRoofExport += 5;
        }

        return [
            [
                'code' => 'grid_stability',
                'name' => 'Grid Availability and Stability',
                'on_grid' => [
                    'rating' => $onGridGridRating,
                    'rationale' => $gridStatus === 'stable'
                        ? 'A stable utility grid strongly supports a simple on-grid configuration.'
                        : 'Grid interruptions reduce the suitability of an on-grid-only configuration.',
                ],
                'hybrid' => [
                    'rating' => $hybridGridRating,
                    'rationale' => $gridStatus === 'stable'
                        ? 'A stable grid reduces the operational need for battery backup.'
                        : 'Grid interruptions increase the value of a hybrid system with energy storage.',
                ],
            ],
            [
                'code' => 'outage_exposure',
                'name' => 'Outage Frequency and Duration',
                'on_grid' => [
                    'rating' => 100 - $outagePressure,
                    'rationale' => sprintf('Estimated outage exposure is %.1f hours per month; on-grid systems do not provide backup during outages.', $monthlyOutageHours),
                ],
                'hybrid' => [
                    'rating' => max(35, $outagePressure),
                    'rationale' => sprintf('Estimated outage exposure is %.1f hours per month; storage can support selected loads during interruptions.', $monthlyOutageHours),
                ],
            ],
            [
                'code' => 'critical_backup',
                'name' => 'Critical Loads and Backup Target',
                'on_grid' => [
                    'rating' => $onGridCritical,
                    'rationale' => sprintf('Critical loads represent %.1f%% of daily energy and the requested backup duration is %.1f hours.', $criticalShare, (float) $inputs['backup_target_hours']),
                ],
                'hybrid' => [
                    'rating' => $hybridCritical,
                    'rationale' => sprintf('Critical loads represent %.1f%% of daily energy and the requested backup duration is %.1f hours, which supports battery consideration.', $criticalShare, (float) $inputs['backup_target_hours']),
                ],
            ],
            [
                'code' => 'night_load',
                'name' => 'Night Load Proportion',
                'on_grid' => [
                    'rating' => max(20, 100 - $nightShare),
                    'rationale' => sprintf('Night consumption is %.1f%%; energy produced in daylight cannot directly serve this portion without storage.', $nightShare),
                ],
                'hybrid' => [
                    'rating' => min(100, 30 + ($nightShare * 0.7)),
                    'rationale' => sprintf('Night consumption is %.1f%%; storage can shift part of daytime PV energy to later hours.', $nightShare),
                ],
            ],
            [
                'code' => 'day_load',
                'name' => 'Daytime Load Proportion',
                'on_grid' => [
                    'rating' => $dayShare,
                    'rationale' => sprintf('Daytime consumption is %.1f%%, which supports direct PV self-consumption.', $dayShare),
                ],
                'hybrid' => [
                    'rating' => min(100, 45 + ($dayShare * 0.55)),
                    'rationale' => sprintf('Daytime consumption is %.1f%%; hybrid operation can serve direct load and charge storage.', $dayShare),
                ],
            ],
            [
                'code' => 'self_sufficiency',
                'name' => 'Energy Self-sufficiency Target',
                'on_grid' => [
                    'rating' => $onGridSelfSufficiency,
                    'rationale' => sprintf('The user targets %.1f%% self-sufficiency; a higher target is harder to achieve without storage.', $selfSufficiencyTarget),
                ],
                'hybrid' => [
                    'rating' => $hybridSelfSufficiency,
                    'rationale' => sprintf('The user targets %.1f%% self-sufficiency; storage can increase the share of load served by PV.', $selfSufficiencyTarget),
                ],
            ],
            [
                'code' => 'budget_alignment',
                'name' => 'Budget and Objective Alignment',
                'on_grid' => [
                    'rating' => $onGridBudget,
                    'rationale' => 'On-grid configurations generally align better with tighter initial investment constraints and savings-focused objectives.',
                ],
                'hybrid' => [
                    'rating' => $hybridBudget,
                    'rationale' => 'Hybrid configurations require additional storage investment but align with backup and resilience objectives.',
                ],
            ],
            [
                'code' => 'roof_export',
                'name' => 'Roof Suitability and Export Policy',
                'on_grid' => [
                    'rating' => $onGridRoofExport,
                    'rationale' => sprintf('Usable roof area is %.1f m² with %.1f%% weighted shading; the selected export policy affects allowable on-grid operation.', $usableArea, (float) $roof['weighted_shading_percent']),
                ],
                'hybrid' => [
                    'rating' => $hybridRoofExport,
                    'rationale' => sprintf('Usable roof area is %.1f m² with %.1f%% weighted shading; storage can support limited-export or zero-export strategies.', $usableArea, (float) $roof['weighted_shading_percent']),
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $inputs
     * @return array<int, string>
     */
    private function buildReasons(
        Collection $rows,
        string $selectedAlternative,
        array $inputs
    ): array {
        $otherAlternative = $selectedAlternative === 'hybrid' ? 'on_grid' : 'hybrid';
        $selectedRows = $rows->where('alternative', $selectedAlternative)->keyBy('criterion_code');
        $otherRows = $rows->where('alternative', $otherAlternative)->keyBy('criterion_code');

        $reasons = $selectedRows
            ->map(function (array $row, string $criterionCode) use ($otherRows): array {
                $other = $otherRows->get($criterionCode);

                return [
                    'advantage' => (float) $row['weighted_score'] - (float) ($other['weighted_score'] ?? 0),
                    'text' => $row['rationale'],
                ];
            })
            ->sortByDesc('advantage')
            ->filter(fn (array $reason): bool => $reason['advantage'] > 0)
            ->take(4)
            ->pluck('text')
            ->values()
            ->all();

        if ($reasons === []) {
            $reasons[] = 'The selected alternative has the highest total weighted suitability score for the current project inputs.';
        }

        $reasons[] = sprintf(
            'The primary project objective is %s.',
            str_replace('_', ' ', $inputs['primary_objective'])
        );

        return array_values(array_unique($reasons));
    }

    private function clamp(float $value, float $minimum = 0, float $maximum = 100): float
    {
        return min(max($value, $minimum), $maximum);
    }
}
