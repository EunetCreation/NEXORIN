<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAnalysis extends Model
{
    protected $fillable = [
        'company_id',
        'project_id',
        'technical_calculation_id',
        'bill_of_material_id',
        'analysis_number',
        'status',
        'currency',
        'electricity_tariff_idr_per_kwh',
        'export_credit_percent',
        'self_consumption_percent',
        'tariff_escalation_percent',
        'annual_om_percent',
        'discount_rate_percent',
        'energy_degradation_percent',
        'project_lifetime_years',
        'replacement_year',
        'replacement_cost_idr',
        'project_capex_idr',
        'annual_energy_kwh',
        'first_year_saving_idr',
        'annual_om_cost_idr',
        'simple_payback_years',
        'lifetime_roi_percent',
        'npv_idr',
        'irr_percent',
        'lcoe_idr_per_kwh',
        'summary',
        'cashflows',
        'warnings',
        'source_snapshot',
        'notes',
        'calculated_by',
        'calculated_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'IDR',
    ];

    protected function casts(): array
    {
        return [
            'electricity_tariff_idr_per_kwh' => 'decimal:2',
            'export_credit_percent' => 'decimal:2',
            'self_consumption_percent' => 'decimal:2',
            'tariff_escalation_percent' => 'decimal:2',
            'annual_om_percent' => 'decimal:2',
            'discount_rate_percent' => 'decimal:2',
            'energy_degradation_percent' => 'decimal:2',
            'project_capex_idr' => 'decimal:2',
            'replacement_cost_idr' => 'decimal:2',
            'annual_energy_kwh' => 'decimal:2',
            'first_year_saving_idr' => 'decimal:2',
            'annual_om_cost_idr' => 'decimal:2',
            'simple_payback_years' => 'decimal:2',
            'lifetime_roi_percent' => 'decimal:2',
            'npv_idr' => 'decimal:2',
            'irr_percent' => 'decimal:2',
            'lcoe_idr_per_kwh' => 'decimal:2',
            'summary' => 'array',
            'cashflows' => 'array',
            'warnings' => 'array',
            'source_snapshot' => 'array',
            'calculated_at' => 'datetime',
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

    public function technicalCalculation(): BelongsTo
    {
        return $this->belongsTo(TechnicalCalculation::class);
    }

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'calculated' => 'Calculated',
            'locked' => 'Locked',
            default => 'Draft',
        };
    }
}
