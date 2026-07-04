<?php

namespace App\Models;

use App\Support\PhoneFormatter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'customer_number',
    'name',
    'type',
    'email',
    'phone',
    'address',
    'city',
    'province',
    'postal_code',
    'notes',
    'portal_user_id',
    'status',
    'archived_at',
    'created_by',
    'updated_by',
])]
class Customer extends Model
{
    /**
     * Get the company that owns the customer.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the optional portal user linked to the customer.
     */
    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_user_id');
    }

    /**
     * Get the user who created the customer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the customer.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all projects owned by the customer.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Determine whether the customer is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get a human-readable customer type.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'commercial' => 'Commercial',
            default => 'Residential',
        };
    }

    /**
     * Format the customer phone number for display.
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        return PhoneFormatter::indonesia($this->phone);
    }

    /**
     * Scope the query to active customers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope the query to a specific company.
     */
    public function scopeForCompany(
        Builder $query,
        Company|int $company
    ): Builder {
        $companyId = $company instanceof Company
            ? $company->getKey()
            : $company;

        return $query->where('company_id', $companyId);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
