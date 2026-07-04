<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'financial_analysis_id',
        'bill_of_material_id',
        'quote_number',
        'revision_number',
        'status',
        'currency',
        'title',
        'valid_until',
        'customer_snapshot',
        'project_snapshot',
        'technical_summary',
        'financial_summary',
        'commercial_summary',
        'terms',
        'warnings',
        'source_snapshot',
        'notes',
        'prepared_by',
        'issued_by',
        'issued_at',
        'created_by',
        'updated_by',
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
            'customer_snapshot' => 'array',
            'project_snapshot' => 'array',
            'technical_summary' => 'array',
            'financial_summary' => 'array',
            'commercial_summary' => 'array',
            'terms' => 'array',
            'warnings' => 'array',
            'source_snapshot' => 'array',
            'issued_at' => 'datetime',
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

    public function financialAnalysis(): BelongsTo
    {
        return $this->belongsTo(FinancialAnalysis::class);
    }

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'issued' => 'Issued',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
            'locked' => 'Locked',
            default => 'Draft',
        };
    }
}
