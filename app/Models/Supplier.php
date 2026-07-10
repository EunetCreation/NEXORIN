<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'supplier_code',
    'name',
    'contact_person',
    'email',
    'phone',
    'address',
    'website',
    'tax_number',
    'payment_terms',
    'lead_time_days',
    'status',
    'notes',
    'created_by',
    'updated_by',
])]
class Supplier extends Model
{
    protected $attributes = [
        'status' => 'active',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function priceBooks(): HasMany
    {
        return $this->hasMany(PriceBook::class)
            ->latest('effective_from')
            ->latest('id');
    }

    public function priceBookItems(): HasMany
    {
        return $this->hasMany(PriceBookItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'inactive' => 'Inactive',
            'blacklisted' => 'Blacklisted',
            default => 'Active',
        };
    }

    protected function casts(): array
    {
        return [
            'lead_time_days' => 'integer',
        ];
    }
}
