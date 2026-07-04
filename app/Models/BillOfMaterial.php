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
    'generated_by',
    'generated_at',
    'created_by',
    'updated_by',
])]
class BillOfMaterial extends Model
{
    protected $attributes = [
        'status' => 'draft',
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
            'generated_at' => 'datetime',
        ];
    }
}
