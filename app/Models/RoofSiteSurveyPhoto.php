<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'roof_site_survey_id',
    'category',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'caption',
    'uploaded_by',
])]
class RoofSiteSurveyPhoto extends Model
{
    public const CATEGORIES = [
        'roof',
        'site',
        'obstruction',
        'electrical',
        'access',
        'other',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return ucfirst($this->category);
    }
}
