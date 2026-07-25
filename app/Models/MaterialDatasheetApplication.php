<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'material_datasheet_id',
    'material_id',
    'status',
    'parser_version',
    'field_decisions',
    'before_specs',
    'after_specs',
    'material_snapshot',
    'applied_by',
    'applied_at',
    'rolled_back_by',
    'rolled_back_at',
    'rollback_note',
])]
class MaterialDatasheetApplication extends Model
{
    protected $attributes = ['status' => 'applied'];

    public function datasheet(): BelongsTo
    {
        return $this->belongsTo(MaterialDatasheet::class, 'material_datasheet_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function rollbackActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }

    protected function casts(): array
    {
        return [
            'field_decisions' => 'array',
            'before_specs' => 'array',
            'after_specs' => 'array',
            'material_snapshot' => 'array',
            'applied_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }
}
