<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'tier', 'qualification_state', 'source_type', 'source_reference',
    'effective_from', 'effective_to', 'reviewed_by', 'reviewed_at', 'notes',
])]
class CompanyRelationship extends Model
{
    public const TIERS = ['strategic', 'preferred', 'approved', 'standard', 'not_qualified'];

    public const QUALIFICATION_STATES = ['not_assessed', 'qualified', 'review_required', 'disqualified'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
