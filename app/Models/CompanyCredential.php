<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'kind',
    'title',
    'reference_number',
    'issuing_authority',
    'policy_provider',
    'coverage_amount_usd',
    'issued_at',
    'expires_at',
    'status',
    'sort_order',
])]
class CompanyCredential extends Model
{
    protected function casts(): array
    {
        return [
            'coverage_amount_usd' => 'decimal:2',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
