<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'project_id',
    'data_source',
    'source_name',
    'source_reference',
    'source_date',
    'elevation_m',
    'timezone',
    'quality_status',
    'notes',
    'verified_by',
    'verified_at',
    'created_by',
    'updated_by',
])]
class ProjectSolarProfile extends Model
{
    public const DATA_SOURCES = ['manual', 'reference', 'api'];

    public const QUALITY_STATUSES = ['incomplete', 'estimated', 'imported', 'verified'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function monthlyData(): HasMany
    {
        return $this->hasMany(MonthlySolarDatum::class)
            ->orderBy('month');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LocationSolarHistory::class)
            ->latest('changed_at')
            ->latest('id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function calculateSummary(Project $project): array
    {
        /** @var Collection<int, MonthlySolarDatum> $rows */
        $rows = $this->relationLoaded('monthlyData')
            ? $this->monthlyData
            : $this->monthlyData()->get();

        $completeRows = $rows->filter(fn (MonthlySolarDatum $row): bool => $row->isComplete());
        $completeMonths = $completeRows->count();
        $totalDays = $completeRows->sum(fn (MonthlySolarDatum $row): int => $row->days_in_month);

        $annualIrradiation = $completeRows->sum(
            fn (MonthlySolarDatum $row): float => (float) $row->ghi_daily_kwh_m2 * $row->days_in_month
        );

        $weightedPsh = $totalDays > 0
            ? $completeRows->sum(fn (MonthlySolarDatum $row): float => (float) $row->peak_sun_hours * $row->days_in_month) / $totalDays
            : 0.0;

        $weightedTemperature = $totalDays > 0
            ? $completeRows->sum(fn (MonthlySolarDatum $row): float => (float) $row->ambient_temperature_c * $row->days_in_month) / $totalDays
            : 0.0;

        $readinessPoints = 0;
        $readinessPoints += $project->latitude !== null ? 1 : 0;
        $readinessPoints += $project->longitude !== null ? 1 : 0;
        $readinessPoints += filled($this->source_name) ? 1 : 0;
        $readinessPoints += $completeMonths;
        $readinessPercent = ($readinessPoints / 15) * 100;

        return [
            'complete_months' => $completeMonths,
            'annual_irradiation_kwh_m2' => round($annualIrradiation, 1),
            'average_psh' => round($weightedPsh, 2),
            'average_temperature_c' => round($weightedTemperature, 1),
            'readiness_percent' => round($readinessPercent, 1),
            'is_ready_for_verification' => $completeMonths === 12
                && $project->latitude !== null
                && $project->longitude !== null
                && filled($this->source_name),
            'potential_label' => match (true) {
                $weightedPsh >= 4.5 => 'Strong',
                $weightedPsh >= 3.5 => 'Moderate',
                $weightedPsh > 0 => 'Limited',
                default => 'Not Available',
            },
        ];
    }

    public function derivedQualityStatus(Project $project): string
    {
        $summary = $this->calculateSummary($project);

        if (! $summary['is_ready_for_verification']) {
            return 'incomplete';
        }

        return $this->data_source === 'api'
            ? 'imported'
            : 'estimated';
    }

    public function getDataSourceLabelAttribute(): string
    {
        return match ($this->data_source) {
            'api' => 'API Import',
            'reference' => 'Reference Dataset',
            default => 'Manual Input',
        };
    }

    public function getQualityStatusLabelAttribute(): string
    {
        return ucfirst($this->quality_status);
    }

    protected function casts(): array
    {
        return [
            'source_date' => 'date',
            'elevation_m' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }
}
