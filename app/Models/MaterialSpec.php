<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'material_id',
    'spec_key',
    'spec_label',
    'spec_value',
    'spec_unit',
    'sort_order',
    'is_filterable',
])]
class MaterialSpec extends Model
{
    protected $attributes = [
        'sort_order' => 0,
        'is_filterable' => false,
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getFormattedValueAttribute(): string
    {
        return trim(collect([$this->spec_value, $this->spec_unit])->filter()->implode(' '));
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_filterable' => 'boolean',
        ];
    }
}
