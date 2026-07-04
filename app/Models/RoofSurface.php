<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'roof_site_survey_id',
    'name',
    'roof_type',
    'roof_material',
    'roof_condition',
    'gross_area_m2',
    'setback_area_m2',
    'obstruction_area_m2',
    'walkway_area_m2',
    'tilt_deg',
    'azimuth_deg',
    'shading_percent',
    'max_additional_load_kg_m2',
    'sort_order',
    'notes',
])]
class RoofSurface extends Model
{
    public const ROOF_TYPES = [
        'pitched',
        'flat',
        'canopy',
        'other',
    ];

    public const ROOF_MATERIALS = [
        'metal_sheet',
        'concrete',
        'clay_tile',
        'asphalt_shingle',
        'fiber_cement',
        'other',
    ];

    public const ROOF_CONDITIONS = [
        'unknown',
        'good',
        'fair',
        'poor',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(RoofSiteSurvey::class, 'roof_site_survey_id');
    }

    public function usableAreaM2(): float
    {
        return max(
            0,
            (float) $this->gross_area_m2
            - (float) $this->setback_area_m2
            - (float) $this->obstruction_area_m2
            - (float) $this->walkway_area_m2
        );
    }

    public function isComplete(): bool
    {
        return filled($this->name)
            && filled($this->roof_type)
            && filled($this->roof_material)
            && filled($this->roof_condition)
            && $this->gross_area_m2 !== null
            && (float) $this->gross_area_m2 > 0
            && $this->tilt_deg !== null
            && $this->azimuth_deg !== null
            && $this->shading_percent !== null;
    }

    public function getRoofTypeLabelAttribute(): string
    {
        return match ($this->roof_type) {
            'pitched' => 'Pitched Roof',
            'flat' => 'Flat Roof',
            'canopy' => 'Canopy',
            'other' => 'Other',
            default => 'Not Set',
        };
    }

    public function getRoofMaterialLabelAttribute(): string
    {
        return match ($this->roof_material) {
            'metal_sheet' => 'Metal Sheet',
            'concrete' => 'Concrete',
            'clay_tile' => 'Clay Tile',
            'asphalt_shingle' => 'Asphalt Shingle',
            'fiber_cement' => 'Fiber Cement',
            'other' => 'Other',
            default => 'Not Set',
        };
    }

    public function getRoofConditionLabelAttribute(): string
    {
        return ucfirst($this->roof_condition ?: 'unknown');
    }

    protected function casts(): array
    {
        return [
            'gross_area_m2' => 'decimal:2',
            'setback_area_m2' => 'decimal:2',
            'obstruction_area_m2' => 'decimal:2',
            'walkway_area_m2' => 'decimal:2',
            'tilt_deg' => 'decimal:2',
            'azimuth_deg' => 'decimal:2',
            'shading_percent' => 'decimal:2',
            'max_additional_load_kg_m2' => 'decimal:2',
        ];
    }
}
