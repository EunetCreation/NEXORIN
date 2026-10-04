<?php

namespace App\Services\Solar;

use App\Models\MonthlySolarDatum;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use Illuminate\Support\Collection;

class SolarDatasetAssessment
{
    public const REQUIRED_MONTHS = 12;

    /**
     * Assess the current persisted/form-backed monthly solar dataset without
     * promoting completeness into engineering verification or bankability.
     *
     * @param  Collection<int, MonthlySolarDatum>|null  $rows
     * @return array<string, mixed>
     */
    public function assess(
        Project $project,
        ?ProjectSolarProfile $profile,
        ?Collection $rows = null,
        ?int $knownCompleteMonths = null,
        ?int $knownPoaMonths = null,
    ): array {
        if (! $profile || ! $profile->exists) {
            return $this->emptyAssessment($project);
        }

        $rows ??= $profile->relationLoaded('monthlyData')
            ? $profile->monthlyData
            : $profile->monthlyData()->get();

        $completeRows = $rows->filter(fn (MonthlySolarDatum $row): bool => $row->isComplete());
        $completeMonths = $knownCompleteMonths ?? $completeRows->count();
        $poaMonths = $knownPoaMonths ?? $rows->filter(
            fn (MonthlySolarDatum $row): bool => $row->poa_daily_kwh_m2 !== null
        )->count();

        $hasLatitude = $project->latitude !== null;
        $hasLongitude = $project->longitude !== null;
        $hasCoordinates = $hasLatitude && $hasLongitude;
        $hasPartialCoordinates = $hasLatitude xor $hasLongitude;
        $hasSourceName = filled($profile->source_name);
        $datasetComplete = $hasCoordinates
            && $hasSourceName
            && $completeMonths === self::REQUIRED_MONTHS;
        $datasetReviewed = $datasetComplete
            && $profile->quality_status === 'verified'
            && $profile->verified_at !== null;

        $state = $datasetReviewed
            ? 'reviewed'
            : ($datasetComplete ? 'complete' : 'input_required');

        $totalDays = $completeRows->sum(fn (MonthlySolarDatum $row): int => $row->days_in_month);
        $recordedGhiTotal = $completeRows->sum(
            fn (MonthlySolarDatum $row): float => (float) $row->ghi_daily_kwh_m2 * $row->days_in_month
        );
        $weightedGhi = $totalDays > 0
            ? $recordedGhiTotal / $totalDays
            : null;
        $weightedPsh = $totalDays > 0
            ? $completeRows->sum(fn (MonthlySolarDatum $row): float => (float) $row->peak_sun_hours * $row->days_in_month) / $totalDays
            : null;
        $weightedTemperature = $totalDays > 0
            ? $completeRows->sum(fn (MonthlySolarDatum $row): float => (float) $row->ambient_temperature_c * $row->days_in_month) / $totalDays
            : null;

        $missingMonths = collect(range(1, self::REQUIRED_MONTHS))
            ->reject(fn (int $month): bool => $completeRows->contains(
                fn (MonthlySolarDatum $row): bool => (int) $row->month === $month
            ))
            ->values();

        $missingInputs = [];
        if (! $hasCoordinates) {
            $missingInputs[] = $hasPartialCoordinates ? 'Complete the coordinate pair' : 'Record the project coordinates';
        }
        if (! $hasSourceName) {
            $missingInputs[] = 'Record the source name';
        }
        if ($completeMonths < self::REQUIRED_MONTHS) {
            $missingInputs[] = sprintf(
                'Complete %d remaining monthly record%s',
                self::REQUIRED_MONTHS - $completeMonths,
                (self::REQUIRED_MONTHS - $completeMonths) === 1 ? '' : 's'
            );
        }

        $requirementCount = ($hasLatitude ? 1 : 0)
            + ($hasLongitude ? 1 : 0)
            + ($hasSourceName ? 1 : 0)
            + $completeMonths;

        return [
            'profile_exists' => (bool) $profile->exists,
            'state' => $state,
            'state_label' => $this->stateLabel($state),
            'source_type_label' => $this->sourceTypeLabel((string) $profile->data_source),
            'has_latitude' => $hasLatitude,
            'has_longitude' => $hasLongitude,
            'has_coordinates' => $hasCoordinates,
            'has_partial_coordinates' => $hasPartialCoordinates,
            'coordinates_label' => $hasCoordinates
                ? 'Recorded'
                : ($hasPartialCoordinates ? 'Incomplete coordinate pair' : 'Not Recorded'),
            'has_source_name' => $hasSourceName,
            'complete_months' => $completeMonths,
            'required_months' => self::REQUIRED_MONTHS,
            'missing_months' => $missingMonths->all(),
            'poa_months' => $poaMonths,
            'is_dataset_complete' => $datasetComplete,
            'is_dataset_reviewed' => $datasetReviewed,
            'can_review' => $datasetComplete && ! $datasetReviewed,
            'missing_inputs' => $missingInputs,
            'recorded_requirement_count' => $requirementCount,
            'required_requirement_count' => 15,
            'recorded_data_completeness_percent' => round(($requirementCount / 15) * 100, 1),
            'derived_annual_ghi_kwh_m2' => $completeMonths === self::REQUIRED_MONTHS ? round($recordedGhiTotal, 1) : null,
            'recorded_period_ghi_kwh_m2' => $completeMonths > 0 ? round($recordedGhiTotal, 1) : null,
            'average_ghi' => $weightedGhi !== null ? round($weightedGhi, 2) : null,
            'average_psh' => $weightedPsh !== null ? round($weightedPsh, 2) : null,
            'average_temperature_c' => $weightedTemperature !== null ? round($weightedTemperature, 1) : null,
            'average_basis_label' => $completeMonths === self::REQUIRED_MONTHS
                ? 'Annual average'
                : ($completeMonths > 0 ? 'Average across complete recorded months' : 'Not Available'),
        ];
    }

    public function state(Project $project, ?ProjectSolarProfile $profile, int $completeMonths): string
    {
        if (! $profile) {
            return 'no_dataset';
        }

        $complete = $project->latitude !== null
            && $project->longitude !== null
            && filled($profile->source_name)
            && $completeMonths === self::REQUIRED_MONTHS;

        if (! $complete) {
            return 'input_required';
        }

        return $profile->quality_status === 'verified' && $profile->verified_at !== null
            ? 'reviewed'
            : 'complete';
    }

    public function stateLabel(string $state): string
    {
        return match ($state) {
            'reviewed' => 'Dataset Reviewed',
            'complete' => 'Dataset Complete',
            'input_required' => 'Input Required',
            default => 'No Dataset',
        };
    }

    public function sourceTypeLabel(string $source): string
    {
        return match ($source) {
            'api' => 'API-designated source',
            'reference' => 'Reference source',
            default => 'Manual entry',
        };
    }

    public function completeMonthsSql(string $profileAlias): string
    {
        return "(
            SELECT COUNT(*)
            FROM monthly_solar_data AS complete_months
            WHERE complete_months.project_solar_profile_id = {$profileAlias}.id
                AND complete_months.ghi_daily_kwh_m2 IS NOT NULL
                AND complete_months.peak_sun_hours IS NOT NULL
                AND complete_months.ambient_temperature_c IS NOT NULL
        )";
    }

    public function requiredDatasetSql(string $profileAlias, string $projectAlias = 'projects'): string
    {
        return "{$projectAlias}.latitude IS NOT NULL
            AND {$projectAlias}.longitude IS NOT NULL
            AND {$profileAlias}.source_name IS NOT NULL
            AND TRIM({$profileAlias}.source_name) <> ''
            AND ".$this->completeMonthsSql($profileAlias).' = '.self::REQUIRED_MONTHS;
    }

    public function reviewedDatasetSql(string $profileAlias, string $projectAlias = 'projects'): string
    {
        return '('.$this->requiredDatasetSql($profileAlias, $projectAlias).")
            AND {$profileAlias}.quality_status = 'verified'
            AND {$profileAlias}.verified_at IS NOT NULL";
    }

    /** @return array<string, mixed> */
    private function emptyAssessment(Project $project): array
    {
        $hasLatitude = $project->latitude !== null;
        $hasLongitude = $project->longitude !== null;
        $hasCoordinates = $hasLatitude && $hasLongitude;
        $hasPartialCoordinates = $hasLatitude xor $hasLongitude;

        return [
            'profile_exists' => false,
            'state' => 'no_dataset',
            'state_label' => 'No Dataset',
            'source_type_label' => 'Not Available',
            'has_latitude' => $hasLatitude,
            'has_longitude' => $hasLongitude,
            'has_coordinates' => $hasCoordinates,
            'has_partial_coordinates' => $hasPartialCoordinates,
            'coordinates_label' => $hasCoordinates
                ? 'Recorded'
                : ($hasPartialCoordinates ? 'Incomplete coordinate pair' : 'Not Recorded'),
            'has_source_name' => false,
            'complete_months' => 0,
            'required_months' => self::REQUIRED_MONTHS,
            'missing_months' => range(1, self::REQUIRED_MONTHS),
            'poa_months' => 0,
            'is_dataset_complete' => false,
            'is_dataset_reviewed' => false,
            'can_review' => false,
            'missing_inputs' => ['Start the solar dataset'],
            'recorded_requirement_count' => 0,
            'required_requirement_count' => 15,
            'recorded_data_completeness_percent' => 0.0,
            'derived_annual_ghi_kwh_m2' => null,
            'recorded_period_ghi_kwh_m2' => null,
            'average_ghi' => null,
            'average_psh' => null,
            'average_temperature_c' => null,
            'average_basis_label' => 'Not Available',
        ];
    }
}
