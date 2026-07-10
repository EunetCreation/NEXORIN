<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationEvent extends Model
{
    protected $fillable = [
        'quotation_id',
        'company_id',
        'project_id',
        'event_type',
        'status_from',
        'status_to',
        'actor_id',
        'event_at',
        'channel',
        'recipient',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'metadata' => 'array',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event_type) {
            'generated' => 'Generated',
            'approved' => 'Approved',
            'client_pdf_generated' => 'Client PDF Generated',
            'sent' => 'Sent to Client',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
            'revised' => 'Revised',
            default => str($this->event_type)->replace('_', ' ')->title()->toString(),
        };
    }
}
