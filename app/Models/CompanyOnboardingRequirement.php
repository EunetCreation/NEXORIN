<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'label', 'description', 'definition_version', 'evaluation_strategy',
    'required_by_default', 'applicable_company_types', 'is_active', 'sort_order',
])]
class CompanyOnboardingRequirement extends Model
{
    protected function casts(): array
    {
        return [
            'required_by_default' => 'boolean',
            'applicable_company_types' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
