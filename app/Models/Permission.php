<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code',
    'name',
    'module',
    'action',
    'description',
    'is_active',
])]
class Permission extends Model
{
    /**
     * Get the roles assigned to the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        )
            ->withPivot('id')
            ->withTimestamps();
    }

    /**
     * Determine whether the permission is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Scope the query to active permissions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope the query to a specific module.
     */
    public function scopeForModule(
        Builder $query,
        string $module
    ): Builder {
        return $query->where('module', $module);
    }

    /**
     * Scope the query to a specific action.
     */
    public function scopeForAction(
        Builder $query,
        string $action
    ): Builder {
        return $query->where('action', $action);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}