<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'system_recommendation_id',
    'alternative',
    'criterion_code',
    'criterion_name',
    'weight',
    'rating',
    'weighted_score',
    'rationale',
    'sort_order',
])]
class SystemRecommendationScore extends Model
{
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(SystemRecommendation::class, 'system_recommendation_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getAlternativeLabelAttribute(): string
    {
        return $this->alternative === 'hybrid' ? 'Hybrid' : 'On-grid';
    }

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'rating' => 'decimal:2',
            'weighted_score' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }
}
