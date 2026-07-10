<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'parent_id',
    'code',
    'name',
    'description',
    'sort_order',
    'is_active',
])]
class MaterialCategory extends Model
{
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function scopeVisibleForCompany(Builder $query, Company|int $company): Builder
    {
        $companyId = $company instanceof Company
            ? $company->getKey()
            : $company;

        return $query->where(function (Builder $scopeQuery) use ($companyId): void {
            $scopeQuery
                ->whereNull('company_id')
                ->orWhere('company_id', $companyId);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getScopeLabelAttribute(): string
    {
        return $this->company_id ? 'Company Catalog' : 'Global Catalog';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
