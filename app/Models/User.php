<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'email_verified_at',
    'password',
    'status',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * Get all company memberships associated with the user.
     */
    public function companyMemberships(): HasMany
    {
        return $this->hasMany(
            CompanyMembership::class,
            'user_id'
        );
    }

    /**
     * Get the user's active company memberships.
     */
    public function activeCompanyMemberships(): HasMany
    {
        return $this->companyMemberships()
            ->where('status', 'active');
    }

    /**
     * Get all companies associated with the user.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            Company::class,
            'company_memberships',
            'user_id',
            'company_id'
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
     * Get the user's active companies.
     */
    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()
            ->wherePivot('status', 'active')
            ->where('companies.status', 'active');
    }

    /**
     * Get the user's system-level roles.
     */
    public function systemRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'system_user_roles',
            'user_id',
            'role_id'
        )
            ->withPivot([
                'id',
                'assigned_by',
            ])
            ->withTimestamps();
    }


    /**
     * Get customer profiles linked to the user.
     */
    public function customerProfiles(): HasMany
    {
        return $this->hasMany(Customer::class, 'portal_user_id');
    }

    /**
     * Get projects assigned to the user as engineer.
     */
    public function engineeringProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'assigned_engineer_id');
    }

    /**
     * Get projects assigned to the user as sales.
     */
    public function salesProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'assigned_sales_id');
    }

    /**
     * Get customers created by the user.
     */
    public function createdCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    /**
     * Get projects created by the user.
     */
    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    /**
     * Get the companies created by the user.
     */
    public function createdCompanies(): HasMany
    {
        return $this->hasMany(
            Company::class,
            'created_by'
        );
    }

    /**
     * Get the company memberships invited by the user.
     */
    public function invitedMemberships(): HasMany
    {
        return $this->hasMany(
            CompanyMembership::class,
            'invited_by'
        );
    }

    /**
     * Determine whether the user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether the user has a system-level role.
     */
    public function hasSystemRole(string $roleCode): bool
    {
        return $this->systemRoles()
            ->where('roles.code', $roleCode)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Determine whether the user is a Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasSystemRole('super_admin');
    }

    /**
     * Get the user's active membership for a company.
     */
    public function membershipForCompany(
        Company|int $company
    ): ?CompanyMembership {
        $companyId = $company instanceof Company
            ? $company->getKey()
            : $company;

        return $this->companyMemberships()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Determine whether the user belongs to a company.
     */
    public function belongsToCompany(
        Company|int $company
    ): bool {
        return $this->membershipForCompany($company) !== null;
    }

    /**
     * Determine whether the user can access a company.
     */
    public function canAccessCompany(
        Company|int $company
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->belongsToCompany($company);
    }

    /**
     * Determine whether the user has a role in a company.
     */
    public function hasCompanyRole(
        Company|int $company,
        string $roleCode
    ): bool {
        $membership = $this->membershipForCompany($company);

        if (! $membership) {
            return false;
        }

        return $membership->hasRole($roleCode);
    }

    /**
     * Determine whether the user has a permission in a company.
     */
    public function hasCompanyPermission(
        Company|int $company,
        string $permissionCode
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $membership = $this->membershipForCompany($company);

        if (! $membership) {
            return false;
        }

        return $membership->hasPermission($permissionCode);
    }

    /**
     * Determine whether the user has at least one company permission.
     *
     * @param array<int, string> $permissionCodes
     */
    public function hasAnyCompanyPermission(
        Company|int $company,
        array $permissionCodes
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $membership = $this->membershipForCompany($company);

        if (! $membership) {
            return false;
        }

        return $membership->hasAnyPermission($permissionCodes);
    }

    /**
     * Determine whether the user has every requested company permission.
     *
     * @param array<int, string> $permissionCodes
     */
    public function hasAllCompanyPermissions(
        Company|int $company,
        array $permissionCodes
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $membership = $this->membershipForCompany($company);

        if (! $membership) {
            return false;
        }

        return $membership->hasAllPermissions($permissionCodes);
    }

    /**
     * Scope the query to active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}