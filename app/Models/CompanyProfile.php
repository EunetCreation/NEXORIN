<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'profile_image_path',
    'legal_name',
    'company_type',
    'year_established',
    'website',
    'hq_country',
    'hq_city',
    'description',
    'data_confidence',
    'categories_served',
    'focus_markets',
    'team_size',
    'parent_company_name',
    'parent_company_country',
    'annual_revenue_usd',
    'ebitda_margin',
    'current_ratio',
    'total_assets_usd',
    'net_profit_usd',
    'debt_to_equity',
    'performance_score',
    'last_reviewed_at',
])]
class CompanyProfile extends Model
{
    protected function casts(): array
    {
        return [
            'categories_served' => 'array',
            'focus_markets' => 'array',
            'year_established' => 'integer',
            'team_size' => 'integer',
            'annual_revenue_usd' => 'decimal:2',
            'ebitda_margin' => 'decimal:2',
            'current_ratio' => 'decimal:2',
            'total_assets_usd' => 'decimal:2',
            'net_profit_usd' => 'decimal:2',
            'debt_to_equity' => 'decimal:2',
            'performance_score' => 'integer',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
