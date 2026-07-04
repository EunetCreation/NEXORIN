<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'system_recommendation_id',
    'event_type',
    'previous_system',
    'new_system',
    'previous_status',
    'new_status',
    'summary',
    'snapshot',
    'changed_by',
    'changed_at',
])]
class SystemRecommendationHistory extends Model
{
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(SystemRecommendation::class, 'system_recommendation_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'changed_at' => 'datetime',
        ];
    }
}
