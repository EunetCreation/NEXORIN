<?php

namespace App\Models;

use App\Support\PhoneFormatter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'slug',
    'code',
    'registration_number',
    'email',
    'phone',
    'address',
    'logo_path',
    'status',
    'created_by',
])]
class Company extends Model
{
    use SoftDeletes;

    /**
     * Get the user who created the company.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Get all memberships associated with the company.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(
            CompanyMembership::class,
            'company_id'
        );
    }

    /**
     * Get active memberships associated with the company.
     */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()
            ->where('status', 'active');
    }

    /**
     * Get all users associated with the company.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'company_memberships',
            'company_id',
            'user_id'
        )
            ->withPivot([
                'id',
                'status',
                'invited_by',
                'joined_at',
            ])
            ->withTimestamps();
    }


    /**
     * Get the customers owned by the company.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get the projects owned by the company.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get load analyses owned by the company.
     */
    public function loadAnalyses(): HasMany
    {
        return $this->hasMany(LoadAnalysis::class);
    }

    /**
     * Format the company phone number for display.
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        return PhoneFormatter::indonesia($this->phone);
    }

    /**
     * Determine whether the company is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether the company contains a specific user.
     */
    public function hasUser(User|int $user): bool
    {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->memberships()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Scope the query to active companies.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}