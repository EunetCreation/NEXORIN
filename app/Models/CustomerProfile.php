<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'customer_id', 'preferred_name', 'website', 'tax_id', 'registration_number',
    'currency_code', 'timezone', 'year_established', 'employee_count', 'annual_revenue_amount',
    'revenue_currency_code', 'description', 'relationship_stage', 'primary_industry', 'sub_industry',
    'business_model', 'industry_tags', 'hq_country', 'operating_countries', 'parent_company_assessment', 'parent_customer_id',
    'external_parent_name', 'parent_company_country', 'ownership_pct', 'opportunity_assessment', 'account_owner_user_id',
    'sales_team', 'region', 'business_unit', 'preferred_contract_type', 'engagement_model',
    'payment_terms', 'contract_duration', 'key_considerations', 'review_acknowledged_at', 'review_acknowledged_by', 'created_by', 'updated_by',
])]
class CustomerProfile extends Model
{
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function parentCustomer(): BelongsTo { return $this->belongsTo(Customer::class, 'parent_customer_id'); }
    public function accountOwner(): BelongsTo { return $this->belongsTo(User::class, 'account_owner_user_id'); }
    public function reviewAcknowledgedBy(): BelongsTo { return $this->belongsTo(User::class, 'review_acknowledged_by'); }

    protected function casts(): array
    {
        return [
            'industry_tags' => 'array',
            'operating_countries' => 'array',
            'year_established' => 'integer',
            'employee_count' => 'integer',
            'annual_revenue_amount' => 'decimal:2',
            'ownership_pct' => 'decimal:2',
            'review_acknowledged_at' => 'datetime',
        ];
    }
}
