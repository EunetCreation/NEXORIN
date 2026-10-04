<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'period_end',
    'currency',
    'annual_revenue',
    'ebitda_margin',
    'current_ratio',
    'total_assets',
    'net_profit',
    'debt_to_equity',
    'source_document_id',
    'recorded_by',
])]
class CompanyFinancialSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'period_end' => 'date',
            'annual_revenue' => 'decimal:2',
            'ebitda_margin' => 'decimal:2',
            'current_ratio' => 'decimal:2',
            'total_assets' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'debt_to_equity' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(CompanyDocument::class, 'source_document_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
