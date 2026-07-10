<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationVersion extends Model
{
    protected $fillable = [
        'quotation_id',
        'company_id',
        'project_id',
        'quote_number',
        'revision_number',
        'version_label',
        'status',
        'currency',
        'title',
        'valid_until',
        'snapshot_hash',
        'source_fingerprint',
        'summary',
        'customer_snapshot',
        'project_snapshot',
        'technical_summary',
        'financial_summary',
        'commercial_summary',
        'terms',
        'warnings',
        'source_snapshot',
        'client_snapshot',
        'internal_snapshot',
        'items_snapshot',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'IDR',
        'revision_number' => 0,
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'valid_until' => 'date',
            'summary' => 'array',
            'customer_snapshot' => 'array',
            'project_snapshot' => 'array',
            'technical_summary' => 'array',
            'financial_summary' => 'array',
            'commercial_summary' => 'array',
            'terms' => 'array',
            'warnings' => 'array',
            'source_snapshot' => 'array',
            'client_snapshot' => 'array',
            'internal_snapshot' => 'array',
            'items_snapshot' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
