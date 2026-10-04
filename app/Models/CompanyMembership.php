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
    /** @var array{roles: array<int, string>, permissions: array<int, string>}|null */
    protected ?array $authorizationContextCache = null;

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
        return in_array($roleCode, $this->authorizationContext()['roles'], true);
    }

    /**
     * Determine whether the membership has a specific permission.
     */
    public function hasPermission(string $permissionCode): bool
    {
        return in_array($permissionCode, $this->authorizationContext()['permissions'], true);
    }

    /**
     * Determine whether the membership has at least one permission.
     *
     * @param array<int, string> $permissionCodes
     */
    public function hasAnyPermission(array $permissionCodes): bool
    {
        $permissionCodes = $this->normalizeAuthorizationCodes($permissionCodes);

        if ($permissionCodes === []) {
            return false;
        }

        return array_intersect($permissionCodes, $this->authorizationContext()['permissions']) !== [];
    }

    /**
     * Determine whether the membership has every requested permission.
     *
     * @param array<int, string> $permissionCodes
     */
    public function hasAllPermissions(array $permissionCodes): bool
    {
        $permissionCodes = $this->normalizeAuthorizationCodes($permissionCodes);

        if ($permissionCodes === []) {
            return false;
        }

        return array_diff($permissionCodes, $this->authorizationContext()['permissions']) === [];
    }

    /**
     * Build the active company-role permission set once for this model instance.
     *
     * @return array{roles: array<int, string>, permissions: array<int, string>}
     */
    protected function authorizationContext(): array
    {
        if ($this->authorizationContextCache !== null) {
            return $this->authorizationContextCache;
        }

        $authorizationRelationsLoaded = $this->relationLoaded('roles')
            && $this->roles->every(fn (Role $role): bool => $role->relationLoaded('permissions'));

        if (! $authorizationRelationsLoaded) {
            $this->loadMissing('roles.permissions');
        }

        $activeRoles = $this->roles
            ->filter(fn (Role $role): bool => $role->scope === 'company' && $role->is_active);

        return $this->authorizationContextCache = [
            'roles' => $activeRoles
                ->pluck('code')
                ->filter(fn (mixed $code): bool => is_string($code) && $code !== '')
                ->unique()
                ->values()
                ->all(),
            'permissions' => $activeRoles
                ->flatMap(fn (Role $role) => $role->permissions
                    ->filter(fn (Permission $permission): bool => $permission->is_active)
                    ->pluck('code'))
                ->filter(fn (mixed $code): bool => is_string($code) && $code !== '')
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, string>
     */
    protected function normalizeAuthorizationCodes(array $codes): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (mixed $code): string => is_string($code) ? trim($code) : '', $codes),
            fn (string $code): bool => $code !== ''
        )));
    }

    /**
     * Clear memoized role and permission values after an in-request assignment.
     */
    public function forgetAuthorizationCache(): static
    {
        $this->authorizationContextCache = null;
        $this->unsetRelation('roles');

        return $this;
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