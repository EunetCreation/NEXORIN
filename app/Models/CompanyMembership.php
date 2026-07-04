<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'company_id',
    'user_id',
    'status',
    'invited_by',
    'joined_at',
])]
class CompanyMembership extends Model
{
    /**
     * Get the company associated with the membership.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class,
            'company_id'
        );
    }

    /**
     * Get the user associated with the membership.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Get the user who invited the membership owner.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'invited_by'
        );
    }

    /**
     * Get the company-level roles assigned to the membership.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'membership_roles',
            'company_membership_id',
            'role_id'
        )
            ->withPivot([
                'id',
                'assigned_by',
            ])
            ->withTimestamps();
    }

    /**
     * Determine whether the membership is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether the user has joined the company.
     */
    public function hasJoined(): bool
    {
        return $this->joined_at !== null;
    }

    /**
     * Determine whether the membership has a company-level role.
     */
    public function hasRole(string $roleCode): bool
    {
        return $this->roles()
            ->where('roles.code', $roleCode)
            ->where('roles.scope', 'company')
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Determine whether the membership has a specific permission.
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->roles()
            ->where('roles.scope', 'company')
            ->where('roles.is_active', true)
            ->whereHas(
                'permissions',
                function (Builder $query) use ($permissionCode): void {
                    $query
                        ->where('permissions.code', $permissionCode)
                        ->where('permissions.is_active', true);
                }
            )
            ->exists();
    }

    /**
     * Determine whether the membership has at least one permission.
     *
     * @param array<int, string> $permissionCodes
     */
    public function hasAnyPermission(array $permissionCodes): bool
    {
        $permissionCodes = array_values(array_unique(array_filter(
            $permissionCodes,
            fn (mixed $code): bool => is_string($code) && trim($code) !== ''
        )));

        if ($permissionCodes === []) {
            return false;
        }

        return $this->roles()
            ->where('roles.scope', 'company')
            ->where('roles.is_active', true)
            ->whereHas(
                'permissions',
                function (Builder $query) use ($permissionCodes): void {
                    $query
                        ->whereIn('permissions.code', $permissionCodes)
                        ->where('permissions.is_active', true);
                }
            )
            ->exists();
    }

    /**
     * Determine whether the membership has every requested permission.
     *
     * @param array<int, string> $permissionCodes
     */
    public function hasAllPermissions(array $permissionCodes): bool
    {
        $permissionCodes = array_values(array_unique(array_filter(
            $permissionCodes,
            fn (mixed $code): bool => is_string($code) && trim($code) !== ''
        )));

        if ($permissionCodes === []) {
            return false;
        }

        foreach ($permissionCodes as $permissionCode) {
            if (! $this->hasPermission($permissionCode)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope the query to active memberships.
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
     * Scope the query to a specific user.
     */
    public function scopeForUser(
        Builder $query,
        User|int $user
    ): Builder {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $query->where('user_id', $userId);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }
}