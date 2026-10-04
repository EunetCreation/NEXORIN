<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'load_analysis_id',
    'billing_month',
    'energy_kwh',
    'bill_amount',
    'peak_demand_kw',
    'created_by',
    'updated_by',
])]
class ElectricityBill extends Model
{
    /**
     * Bill mutations invalidate the parent Load Analysis freshness timestamp.
     * Existing Recommendation / Technical Calculation stale checks consume it.
     *
     * @var array<int, string>
     */
    protected $touches = ['loadAnalysis'];

    /**
     * Get the company that owns the bill record.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the project associated with the bill record.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent load analysis.
     */
    public function loadAnalysis(): BelongsTo
    {
        return $this->belongsTo(LoadAnalysis::class);
    }

    /**
     * Get the effective rate represented by this bill.
     */
    public function getEffectiveRateAttribute(): ?float
    {
        $energy = (float) $this->energy_kwh;
        $amount = $this->bill_amount === null
            ? null
            : (float) $this->bill_amount;

        if ($energy <= 0 || $amount === null) {
            return null;
        }

        return round($amount / $energy, 2);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'energy_kwh' => 'decimal:3',
            'bill_amount' => 'decimal:2',
            'peak_demand_kw' => 'decimal:3',
        ];
    }
}
