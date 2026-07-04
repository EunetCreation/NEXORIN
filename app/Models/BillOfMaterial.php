<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'project_id',
    'technical_calculation_id',
    'bom_number',
    'status',
    'summary',
    'warnings',
    'source_snapshot',
    'notes',
    'pricing_status',
    'material_cost_subtotal_idr',
    'material_price_subtotal_idr',
    'labor_cost_idr',
    'logistics_cost_idr',
    'other_cost_idr',
    'contingency_cost_idr',
    'tax_idr',
    'discount_idr',
    'grand_cost_idr',
    'grand_price_idr',
    'gross_margin_idr',
    'gross_margin_percent',
    'pricing_parameters',
    'priced_by',
    'priced_at',
    'generated_by',
    'generated_at',
    'created_by',
    'updated_by',
])]
class BillOfMaterial extends Model
{
    protected $attributes = [
        'status' => 'draft',
        'pricing_status' => 'not_priced',
    ];

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

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function pricer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priced_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillOfMaterialItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'generated' => 'Generated Draft',
            'locked' => 'Locked',
            default => 'Draft',
        };
    }

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'warnings' => 'array',
            'source_snapshot' => 'array',
            'pricing_parameters' => 'array',
            'material_cost_subtotal_idr' => 'decimal:2',
            'material_price_subtotal_idr' => 'decimal:2',
            'labor_cost_idr' => 'decimal:2',
            'logistics_cost_idr' => 'decimal:2',
            'other_cost_idr' => 'decimal:2',
            'contingency_cost_idr' => 'decimal:2',
            'tax_idr' => 'decimal:2',
            'discount_idr' => 'decimal:2',
            'grand_cost_idr' => 'decimal:2',
            'grand_price_idr' => 'decimal:2',
            'gross_margin_idr' => 'decimal:2',
            'gross_margin_percent' => 'decimal:2',
            'generated_at' => 'datetime',
            'priced_at' => 'datetime',
        ];
    }
}
