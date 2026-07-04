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
    'survey_date',
    'surveyed_by_user_id',
    'access_method',
    'access_condition',
    'mounting_preference',
    'structural_review_status',
    'structural_notes',
    'fall_protection_available',
    'lightning_protection_present',
    'electrical_room_distance_m',
    'estimated_cable_route_m',
    'weather_condition',
    'quality_status',
    'notes',
    'verified_by',
    'verified_at',
    'created_by',
    'updated_by',
])]
class RoofSiteSurvey extends Model
{
    public const ACCESS_METHODS = [
        'internal_stairs',
        'fixed_ladder',
        'portable_ladder',
        'scaffolding',
        'mobile_elevated_platform',
        'other',
    ];

    public const ACCESS_CONDITIONS = [
        'unknown',
        'good',
        'restricted',
        'unsafe',
    ];

    public const MOUNTING_PREFERENCES = [
        'undecided',
        'roof_parallel',
        'tilted_rack',
        'ballasted',
        'standing_seam_clamp',
        'other',
    ];

    public const STRUCTURAL_REVIEW_STATUSES = [
        'not_assessed',
        'not_required',
        'required',
        'completed',
    ];

    public const QUALITY_STATUSES = [
        'incomplete',
        'ready',
        'verified',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyed_by_user_id');
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

    public function surfaces(): HasMany
    {
        return $this->hasMany(RoofSurface::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoofSiteSurveyPhoto::class)
            ->latest('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RoofSiteSurveyHistory::class)
            ->latest('changed_at')
            ->latest('id');
    }

    public function calculateSummary(): array
    {
        /** @var Collection<int, RoofSurface> $surfaces */
        $surfaces = $this->relationLoaded('surfaces')
            ? $this->surfaces
            : $this->surfaces()->get();

        $surfaceCount = $surfaces->count();
        $completeSurfaceCount = $surfaces
            ->filter(fn (RoofSurface $surface): bool => $surface->isComplete())
            ->count();

        $grossArea = $surfaces->sum(
            fn (RoofSurface $surface): float => (float) $surface->gross_area_m2
        );

        $usableArea = $surfaces->sum(
            fn (RoofSurface $surface): float => $surface->usableAreaM2()
        );

        $weightedShading = $usableArea > 0
            ? $surfaces->sum(
                fn (RoofSurface $surface): float => $surface->usableAreaM2()
                    * (float) $surface->shading_percent
            ) / $usableArea
            : 0.0;

        $readinessPoints = 0;
        $readinessPoints += $this->survey_date !== null ? 1 : 0;
        $readinessPoints += filled($this->access_method) ? 1 : 0;
        $readinessPoints += $this->structural_review_status !== 'not_assessed' ? 1 : 0;
        $readinessPoints += $surfaceCount > 0 ? 1 : 0;
        $readinessPoints += $surfaceCount > 0 && $surfaceCount === $completeSurfaceCount ? 1 : 0;

        $warnings = $this->buildWarnings($surfaces, $usableArea);

        return [
            'surface_count' => $surfaceCount,
            'complete_surface_count' => $completeSurfaceCount,
            'gross_area_m2' => round($grossArea, 2),
            'usable_area_m2' => round($usableArea, 2),
            'usable_area_percent' => $grossArea > 0
                ? round(($usableArea / $grossArea) * 100, 1)
                : 0.0,
            'weighted_shading_percent' => round($weightedShading, 1),
            'readiness_percent' => round(($readinessPoints / 5) * 100, 1),
            'is_ready_for_verification' => $readinessPoints === 5,
            'warnings' => $warnings,
            'warning_count' => count($warnings),
            'overall_condition' => $this->overallCondition($surfaces),
        ];
    }

    public function derivedQualityStatus(): string
    {
        return $this->calculateSummary()['is_ready_for_verification']
            ? 'ready'
            : 'incomplete';
    }

    public function resetVerification(?int $updatedBy = null): void
    {
        $this->forceFill([
            'verified_by' => null,
            'verified_at' => null,
            'updated_by' => $updatedBy ?? $this->updated_by,
        ])->save();
    }

    public function getQualityStatusLabelAttribute(): string
    {
        return match ($this->quality_status) {
            'ready' => 'Ready for Verification',
            'verified' => 'Verified',
            default => 'Incomplete',
        };
    }

    public function getAccessMethodLabelAttribute(): string
    {
        return match ($this->access_method) {
            'internal_stairs' => 'Internal Stairs',
            'fixed_ladder' => 'Fixed Ladder',
            'portable_ladder' => 'Portable Ladder',
            'scaffolding' => 'Scaffolding',
            'mobile_elevated_platform' => 'Mobile Elevated Work Platform',
            'other' => 'Other',
            default => 'Not Recorded',
        };
    }

    public function getStructuralReviewStatusLabelAttribute(): string
    {
        return match ($this->structural_review_status) {
            'not_required' => 'Not Required',
            'required' => 'Required',
            'completed' => 'Completed',
            default => 'Not Assessed',
        };
    }

    /**
     * @param  Collection<int, RoofSurface>  $surfaces
     * @return array<int, string>
     */
    private function buildWarnings(Collection $surfaces, float $usableArea): array
    {
        $warnings = [];

        if ($surfaces->isEmpty()) {
            $warnings[] = 'At least one roof surface must be recorded.';
        }

        if ($surfaces->contains(fn (RoofSurface $surface): bool => $surface->roof_condition === 'poor')) {
            $warnings[] = 'One or more roof surfaces are in poor condition and require specialist review.';
        }

        if ($surfaces->contains(fn (RoofSurface $surface): bool => (float) $surface->shading_percent > 20)) {
            $warnings[] = 'One or more roof surfaces have shading above 20 percent.';
        }

        if ($surfaces->isNotEmpty() && $usableArea <= 0) {
            $warnings[] = 'No usable roof area remains after setbacks, obstructions, and walkways.';
        }

        if ($this->structural_review_status === 'required') {
            $warnings[] = 'A structural review is required before final engineering approval.';
        }

        if ($this->access_condition === 'unsafe') {
            $warnings[] = 'Roof access is marked unsafe.';
        }

        if ($this->fall_protection_available === false) {
            $warnings[] = 'Fall protection is not available at the surveyed site.';
        }

        if ((float) $this->estimated_cable_route_m > 50) {
            $warnings[] = 'The estimated cable route exceeds 50 meters and requires voltage-drop review.';
        }

        return $warnings;
    }

    /**
     * @param  Collection<int, RoofSurface>  $surfaces
     */
    private function overallCondition(Collection $surfaces): string
    {
        if ($surfaces->isEmpty()) {
            return 'Not Available';
        }

        if ($surfaces->contains(fn (RoofSurface $surface): bool => $surface->roof_condition === 'poor')) {
            return 'Poor';
        }

        if ($surfaces->contains(fn (RoofSurface $surface): bool => $surface->roof_condition === 'fair')) {
            return 'Fair';
        }

        if ($surfaces->every(fn (RoofSurface $surface): bool => $surface->roof_condition === 'good')) {
            return 'Good';
        }

        return 'Unknown';
    }

    protected function casts(): array
    {
        return [
            'survey_date' => 'date',
            'fall_protection_available' => 'boolean',
            'lightning_protection_present' => 'boolean',
            'electrical_room_distance_m' => 'decimal:2',
            'estimated_cable_route_m' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }
}
