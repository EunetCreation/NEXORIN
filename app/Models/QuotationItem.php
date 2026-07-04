<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'category',
        'item_code',
        'item_name',
        'description',
        'quantity',
        'unit',
        'unit_price_idr',
        'line_price_idr',
        'source_key',
        'sort_order',
        'metadata',
    ];

    protected $attributes = [
        'quantity' => 0,
        'unit_price_idr' => 0,
        'line_price_idr' => 0,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_idr' => 'decimal:2',
            'line_price_idr' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
