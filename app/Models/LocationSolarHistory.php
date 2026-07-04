<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'project_solar_profile_id',
    'event_type',
    'previous_source',
    'new_source',
    'previous_status',
    'new_status',
    'summary',
    'changed_by',
    'changed_at',
])]
class LocationSolarHistory extends Model
{
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProjectSolarProfile::class, 'project_solar_profile_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
