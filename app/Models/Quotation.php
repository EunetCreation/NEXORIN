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
        'version_label',
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
        'snapshot_hash',
        'source_fingerprint',
        'client_snapshot',
        'internal_snapshot',
        'notes',
        'prepared_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'issued_by',
        'issued_at',
        'delivery_method',
        'sent_to',
        'delivery_notes',
        'accepted_by',
        'accepted_at',
        'acceptance_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'expired_at',
        'expiry_reason',
        'client_proposal_pdf_generated_at',
        'client_proposal_pdf_snapshot_hash',
        'client_proposal_pdf_source_fingerprint',
        'client_proposal_pdf_filename',
        'client_proposal_pdf_generated_by',
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
            'client_snapshot' => 'array',
            'internal_snapshot' => 'array',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'client_proposal_pdf_generated_at' => 'datetime',
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

    public function clientProposalPdfGenerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_proposal_pdf_generated_by');
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuotationVersion::class)
            ->orderByDesc('revision_number')
            ->orderByDesc('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuotationEvent::class)
            ->orderByDesc('event_at')
            ->orderByDesc('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'internal_review' => 'Internal Review',
            'approved' => 'Approved',
            'sent' => 'Sent',
            'issued' => 'Issued',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
            'locked' => 'Locked',
            default => 'Draft',
        };
    }

    public function getVersionDisplayAttribute(): string
    {
        return $this->version_label ?: trim(($this->quote_number ?: 'Quotation').' Rev.'.((int) $this->revision_number));
    }

    public function isApprovedForClient(): bool
    {
        return in_array($this->status, ['approved', 'sent', 'issued', 'accepted'], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['accepted', 'rejected', 'cancelled', 'expired'], true);
    }

    public function shouldBeConsideredExpired(): bool
    {
        if (! $this->valid_until || $this->isTerminal()) {
            return false;
        }

        return $this->valid_until->endOfDay()->isPast();
    }

    public function canBeSentToClient(?string $sourceFingerprint = null): bool
    {
        return $this->status === 'approved'
            && ! $this->shouldBeConsideredExpired()
            && $this->hasCurrentClientProposalPdf($sourceFingerprint);
    }

    public function canBeAcceptedByClient(): bool
    {
        return in_array($this->status, ['sent', 'issued'], true)
            && ! $this->shouldBeConsideredExpired();
    }

    public function canBeRejectedByClient(): bool
    {
        return in_array($this->status, ['sent', 'issued'], true)
            && ! $this->shouldBeConsideredExpired();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'internal_review', 'approved', 'sent', 'issued'], true);
    }

    public function isSourceCurrent(?string $sourceFingerprint = null): bool
    {
        if (! $this->source_fingerprint || ! $sourceFingerprint) {
            return true;
        }

        return hash_equals((string) $this->source_fingerprint, $sourceFingerprint);
    }

    public function hasSnapshotControlledClientProposal(): bool
    {
        return filled($this->snapshot_hash) && is_array($this->client_snapshot);
    }

    public function hasCurrentClientProposalPdf(?string $sourceFingerprint = null): bool
    {
        if (! $this->client_proposal_pdf_generated_at || ! $this->snapshot_hash || ! $this->client_proposal_pdf_snapshot_hash) {
            return false;
        }

        if (! hash_equals((string) $this->snapshot_hash, (string) $this->client_proposal_pdf_snapshot_hash)) {
            return false;
        }

        if ($sourceFingerprint && $this->client_proposal_pdf_source_fingerprint) {
            return hash_equals((string) $sourceFingerprint, (string) $this->client_proposal_pdf_source_fingerprint);
        }

        return true;
    }
}

