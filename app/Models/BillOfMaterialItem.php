<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bill_of_material_id',
    'category',
    'item_code',
    'item_name',
    'description',
    'specification',
    'quantity',
    'unit',
    'unit_cost_idr',
    'unit_price_idr',
    'line_cost_idr',
    'line_price_idr',
    'source_key',
    'sort_order',
    'metadata',
])]
class BillOfMaterialItem extends Model
{
    protected $attributes = [
        'unit_cost_idr' => 0,
        'unit_price_idr' => 0,
        'line_cost_idr' => 0,
        'line_price_idr' => 0,
        'sort_order' => 0,
    ];

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    protected function casts(): array
    {
        return [
            'specification' => 'array',
            'metadata' => 'array',
            'quantity' => 'decimal:3',
            'unit_cost_idr' => 'decimal:2',
            'unit_price_idr' => 'decimal:2',
            'line_cost_idr' => 'decimal:2',
            'line_price_idr' => 'decimal:2',
        ];
    }
}
