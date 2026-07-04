<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'roof_site_survey_id',
    'event_type',
    'previous_status',
    'new_status',
    'summary',
    'changed_by',
    'changed_at',
])]
class RoofSiteSurveyHistory extends Model
{
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

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }
}
