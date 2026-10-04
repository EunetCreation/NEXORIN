<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id','customer_id','name','opportunity_type','estimated_value','currency_code','probability_pct','expected_close_date','status','created_by','updated_by'])]
class CustomerOpportunity extends Model
{
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2', 'probability_pct' => 'integer', 'expected_close_date' => 'date'];
    }
}
