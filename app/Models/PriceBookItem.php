<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'price_book_id',
    'material_id',
    'supplier_id',
    'supplier_sku',
    'unit_cost',
    'selling_price',
    'margin_percent',
    'minimum_order_quantity',
    'lead_time_days',
    'availability_status',
    'warranty_note',
    'quoted_at',
    'valid_until',
    'quote_status',
    'import_batch_code',
    'notes',
])]
class PriceBookItem extends Model
{
    protected $attributes = [
        'unit_cost' => 0,
        'availability_status' => 'available',
        'quote_status' => 'active',
    ];

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getEffectiveSellingPriceAttribute(): float
    {
        if ($this->selling_price !== null) {
            return (float) $this->selling_price;
        }

        if ($this->margin_percent !== null) {
            return (float) $this->unit_cost * (1 + ((float) $this->margin_percent / 100));
        }

        return (float) $this->unit_cost;
    }

    public function getAvailabilityLabelAttribute(): string
    {
        return match ($this->availability_status) {
            'limited' => 'Limited Stock',
            'backorder' => 'Backorder',
            'unavailable' => 'Unavailable',
            'discontinued' => 'Discontinued',
            default => 'Available',
        };
    }

    public function getQuoteStatusLabelAttribute(): string
    {
        return match ($this->quote_status) {
            'obsolete' => 'Obsolete Quote',
            default => 'Active Quote',
        };
    }

    public function getDerivedQuoteStatusAttribute(): string
    {
        if ($this->quote_status === 'obsolete') {
            return 'obsolete';
        }

        return $this->is_expired ? 'expired' : 'active';
    }

    public function getDerivedQuoteStatusLabelAttribute(): string
    {
        return match ($this->derived_quote_status) {
            'expired' => 'Expired Quote',
            'obsolete' => 'Obsolete Quote',
            default => 'Active Quote',
        };
    }

    public function getPriceHealthStatusAttribute(): string
    {
        if ($this->unit_cost === null || (float) $this->unit_cost <= 0) {
            return 'missing_cost';
        }

        if (in_array($this->availability_status, ['unavailable', 'discontinued'], true)) {
            return 'unavailable';
        }

        if ($this->derived_quote_status !== 'active') {
            return $this->derived_quote_status;
        }

        if ($this->effective_margin_percent !== null && $this->effective_margin_percent < 10) {
            return 'low_margin';
        }

        if (! $this->has_selling_reference) {
            return 'missing_selling_reference';
        }

        return 'healthy';
    }

    public function getPriceHealthLabelAttribute(): string
    {
        return match ($this->price_health_status) {
            'missing_cost' => 'Missing Cost',
            'unavailable' => 'Unavailable',
            'expired' => 'Expired Quote',
            'obsolete' => 'Obsolete Quote',
            'low_margin' => 'Low Margin',
            'missing_selling_reference' => 'No Selling Reference',
            default => 'Healthy',
        };
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->isBefore(today());
    }

    public function getHasSellingReferenceAttribute(): bool
    {
        return $this->selling_price !== null
            || $this->margin_percent !== null;
    }

    public function getEffectiveMarginPercentAttribute(): ?float
    {
        $unitCost = (float) $this->unit_cost;

        if ($unitCost <= 0) {
            return null;
        }

        return (($this->effective_selling_price - $unitCost) / $unitCost) * 100;
    }

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'margin_percent' => 'decimal:2',
            'minimum_order_quantity' => 'decimal:3',
            'lead_time_days' => 'integer',
            'quoted_at' => 'date',
            'valid_until' => 'date',
        ];
    }
}
