<?php

namespace App\Models;

use App\Services\Solar\SolarDatasetAssessment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    /**
     * Backward-compatible summary wrapper around the shared solar dataset
     * assessment authority. Legacy keys remain available for current
     * consumers while their semantics are kept truthful.
     *
     * @return array<string, mixed>
     */
    public function calculateSummary(Project $project): array
    {
        $assessment = app(SolarDatasetAssessment::class)->assess($project, $this);

        return [
            ...$assessment,
            'annual_irradiation_kwh_m2' => $assessment['derived_annual_ghi_kwh_m2'],
            'readiness_percent' => $assessment['recorded_data_completeness_percent'],
            'is_ready_for_verification' => $assessment['is_dataset_complete'],
            'potential_label' => 'Not Assessed',
        ];
    }

    public function derivedQualityStatus(Project $project): string
    {
        $assessment = app(SolarDatasetAssessment::class)->assess($project, $this);

        if (! $assessment['is_dataset_complete']) {
            return 'incomplete';
        }

        // Preserve the legacy persistence enum for compatibility. The UI must
        // not interpret "imported" as proof that NEXORIN performed ingestion.
        return $this->data_source === 'api'
            ? 'imported'
            : 'estimated';
    }

    public function getDataSourceLabelAttribute(): string
    {
        return app(SolarDatasetAssessment::class)->sourceTypeLabel((string) $this->data_source);
    }

    public function getQualityStatusLabelAttribute(): string
    {
        return match ($this->quality_status) {
            'verified' => 'Dataset Reviewed',
            'estimated', 'imported' => 'Dataset Complete',
            default => 'Input Required',
        };
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
