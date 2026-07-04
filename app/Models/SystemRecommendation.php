<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'project_id',
    'grid_connection_status',
    'outage_frequency_per_month',
    'average_outage_duration_hours',
    'backup_target_hours',
    'target_self_sufficiency_percent',
    'budget_flexibility',
    'export_policy',
    'primary_objective',
    'notes',
    'engine_recommended_system',
    'recommended_system',
    'on_grid_score',
    'hybrid_score',
    'decisiveness_index',
    'result_status',
    'engine_version',
    'hard_constraints',
    'warnings',
    'reasons',
    'source_snapshot',
    'generated_by',
    'generated_at',
    'override_system',
    'override_reason',
    'overridden_by',
    'overridden_at',
    'created_by',
    'updated_by',
])]
class SystemRecommendation extends Model
{
    public const GRID_CONNECTION_STATUSES = [
        'stable',
        'occasional_outages',
        'frequent_outages',
        'unavailable',
    ];

    public const BUDGET_FLEXIBILITIES = [
        'strict',
        'balanced',
        'flexible',
    ];

    public const EXPORT_POLICIES = [
        'allowed',
        'limited',
        'zero_export',
        'unknown',
    ];

    public const PRIMARY_OBJECTIVES = [
        'savings',
        'backup',
        'balanced',
        'self_sufficiency',
        'maximize_roof',
    ];

    public const SYSTEMS = [
        'on_grid',
        'hybrid',
    ];

    public const RESULT_STATUSES = [
        'incomplete',
        'generated',
        'review_required',
        'overridden',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SystemRecommendationScore::class)
            ->orderBy('sort_order')
            ->orderBy('alternative');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SystemRecommendationHistory::class)
            ->latest('changed_at')
            ->latest('id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    public function getGridConnectionStatusLabelAttribute(): string
    {
        return match ($this->grid_connection_status) {
            'occasional_outages' => 'Available with Occasional Outages',
            'frequent_outages' => 'Available with Frequent Outages',
            'unavailable' => 'Grid Unavailable',
            default => 'Stable Grid',
        };
    }

    public function getBudgetFlexibilityLabelAttribute(): string
    {
        return match ($this->budget_flexibility) {
            'strict' => 'Strict Budget',
            'flexible' => 'Flexible Budget',
            default => 'Balanced Budget',
        };
    }

    public function getExportPolicyLabelAttribute(): string
    {
        return match ($this->export_policy) {
            'allowed' => 'Export Allowed',
            'limited' => 'Export Limited',
            'zero_export' => 'Zero Export Required',
            default => 'Export Policy Unknown',
        };
    }

    public function getPrimaryObjectiveLabelAttribute(): string
    {
        return match ($this->primary_objective) {
            'savings' => 'Maximum Savings',
            'backup' => 'Backup Reliability',
            'self_sufficiency' => 'Energy Self-sufficiency',
            'maximize_roof' => 'Maximum Roof Utilization',
            default => 'Balanced Performance',
        };
    }

    public function getRecommendedSystemLabelAttribute(): string
    {
        return $this->systemLabel($this->recommended_system);
    }

    public function getEngineRecommendedSystemLabelAttribute(): string
    {
        return $this->systemLabel($this->engine_recommended_system);
    }

    public function getResultStatusLabelAttribute(): string
    {
        return match ($this->result_status) {
            'generated' => 'Recommendation Generated',
            'review_required' => 'Engineer Review Required',
            'overridden' => 'Engineer Override Applied',
            default => 'Source Data Incomplete',
        };
    }

    public function hasGeneratedResult(): bool
    {
        return $this->engine_recommended_system !== null
            && $this->on_grid_score !== null
            && $this->hybrid_score !== null;
    }

    public function isStale(Project $project): bool
    {
        if ($this->generated_at === null) {
            return false;
        }

        $latestSourceUpdate = collect([
            $project->loadAnalysis?->updated_at,
            $project->solarProfile?->updated_at,
            $project->roofSiteSurvey?->updated_at,
            $project->roofSurfaces?->max('updated_at'),
        ])->filter()->max();

        return $latestSourceUpdate !== null
            && $latestSourceUpdate->isAfter($this->generated_at);
    }

    private function systemLabel(?string $system): string
    {
        return match ($system) {
            'on_grid' => 'On-grid',
            'hybrid' => 'Hybrid',
            default => 'Not Available',
        };
    }

    protected function casts(): array
    {
        return [
            'outage_frequency_per_month' => 'integer',
            'average_outage_duration_hours' => 'decimal:2',
            'backup_target_hours' => 'decimal:2',
            'target_self_sufficiency_percent' => 'decimal:2',
            'on_grid_score' => 'decimal:2',
            'hybrid_score' => 'decimal:2',
            'decisiveness_index' => 'decimal:2',
            'hard_constraints' => 'array',
            'warnings' => 'array',
            'reasons' => 'array',
            'source_snapshot' => 'array',
            'generated_at' => 'datetime',
            'overridden_at' => 'datetime',
        ];
    }
}
