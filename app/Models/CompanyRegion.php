<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'country_name',
    'country_code',
    'region_name',
    'latitude',
    'longitude',
    'project_count',
    'is_headquarters',
    'sort_order',
])]
class CompanyRegion extends Model
{
    protected function casts(): array
    {
        return [
            'project_count' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_headquarters' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
