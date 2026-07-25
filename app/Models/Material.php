<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'material_category_id',
    'sku',
    'name',
    'brand',
    'model',
    'material_type',
    'cost_group',
    'uom',
    'system_types',
    'project_scales',
    'suitable_roof_types',
    'nominal_power_wp',
    'nominal_capacity_kwh',
    'rated_power_kw',
    'voltage_v',
    'current_a',
    'electrical_phase',
    'ip_rating',
    'warranty_years',
    'default_scope_mode',
    'catalog_status',
    'compliance_status',
    'approved_at',
    'approved_by',
    'last_reviewed_at',
    'last_reviewed_by',
    'is_active',
    'description',
    'technical_notes',
    'created_by',
    'updated_by',
])]
class Material extends Model
{
    protected $attributes = [
        'uom' => 'pcs',
        'default_scope_mode' => 'catalog_candidate',
        'catalog_status' => 'draft',
        'compliance_status' => 'needs_review',
        'is_active' => true,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(MaterialSpec::class)
            ->orderBy('sort_order')
            ->orderBy('spec_label');
    }

    public function priceBookItems(): HasMany
    {
        return $this->hasMany(PriceBookItem::class);
    }

    public function datasheets(): HasMany
    {
        return $this->hasMany(MaterialDatasheet::class)
            ->latest('issued_at')
            ->latest('id');
    }

    public function datasheetApplications(): HasMany
    {
        return $this->hasMany(MaterialDatasheetApplication::class)
            ->latest('applied_at')
            ->latest('id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(MaterialCertificate::class)
            ->latest('issued_at')
            ->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MaterialDocument::class)
            ->latest('issued_at')
            ->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reviewed_by');
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

    public function getDisplayNameAttribute(): string
    {
        $identity = collect([$this->brand, $this->model])
            ->filter()
            ->implode(' ');

        return trim($identity) !== ''
            ? $identity.' · '.$this->name
            : $this->name;
    }

    public function getSystemTypeLabelAttribute(): string
    {
        return collect($this->system_types ?? [])
            ->map(fn (string $value): string => match ($value) {
                'on_grid' => 'On-grid',
                'hybrid' => 'Hybrid',
                'off_grid' => 'Off-grid',
                default => str($value)->replace('_', ' ')->title()->toString(),
            })
            ->implode(', ');
    }

    /**
     * Return the material to governed review after technical or evidence changes.
     */
    public function markForCatalogReview(?int $reviewerId = null): bool
    {
        $payload = [
            'compliance_status' => 'needs_review',
            'last_reviewed_at' => now(),
            'last_reviewed_by' => $reviewerId,
            'approved_at' => null,
            'approved_by' => null,
        ];

        if ($this->catalog_status === 'approved') {
            $payload['catalog_status'] = 'needs_review';
        }

        return $this->forceFill($payload)->save();
    }

    protected function casts(): array
    {
        return [
            'system_types' => 'array',
            'project_scales' => 'array',
            'suitable_roof_types' => 'array',
            'nominal_power_wp' => 'decimal:2',
            'nominal_capacity_kwh' => 'decimal:3',
            'rated_power_kw' => 'decimal:3',
            'voltage_v' => 'decimal:2',
            'current_a' => 'decimal:2',
            'warranty_years' => 'integer',
            'approved_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
