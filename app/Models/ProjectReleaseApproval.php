<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReleaseApproval extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'status',
        'release_status',
        'gate_hash',
        'gate_snapshot',
        'gate_result',
        'notes',
        'approved_by',
        'approved_at',
        'revoked_by',
        'revoked_at',
        'revoke_reason',
    ];

    protected $attributes = [
        'status' => 'active',
        'release_status' => 'Ready for Client Release',
    ];

    protected function casts(): array
    {
        return [
            'gate_snapshot' => 'array',
            'gate_result' => 'array',
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active Approval',
            'revoked' => 'Revoked',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
