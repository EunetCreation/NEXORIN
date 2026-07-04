<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code',
    'name',
    'scope',
    'description',
    'is_system',
    'is_active',
])]
class Role extends Model
{
    /**
     * Get the permissions assigned to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        )
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * Get the company memberships assigned to the role.
     */
    public function companyMemberships(): BelongsToMany
    {
        return $this->belongsToMany(
            CompanyMembership::class,
            'membership_roles',
            'role_id',
            'company_membership_id'
        )
            ->withPivot([
                'id',
                'assigned_by',
            ])
            ->withTimestamps();
    }

    /**
     * Get the system-level users assigned to the role.
     */
    public function systemUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'system_user_roles',
            'role_id',
            'user_id'
        )
            ->withPivot([
                'id',
                'assigned_by',
            ])
            ->withTimestamps();
    }

    /**
     * Determine whether the role contains a specific active permission.
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->permissions()
            ->where('permissions.code', $permissionCode)
            ->where('permissions.is_active', true)
            ->exists();
    }

    /**
     * Determine whether the role belongs to the system scope.
     */
    public function isSystemScoped(): bool
    {
        return $this->scope === 'system';
    }

    /**
     * Determine whether the role belongs to the company scope.
     */
    public function isCompanyScoped(): bool
    {
        return $this->scope === 'company';
    }

    /**
     * Scope the query to active roles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}