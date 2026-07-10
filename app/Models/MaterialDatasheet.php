<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'material_id',
    'title',
    'original_filename',
    'file_path',
    'external_url',
    'mime_type',
    'file_size_bytes',
    'revision',
    'issued_at',
    'is_active',
    'extraction_status',
    'parser_version',
    'parse_confidence',
    'parsed_text',
    'parsed_fields',
    'parse_warnings',
    'parsed_at',
    'reviewed_at',
    'reviewed_by',
    'applied_at',
    'applied_by',
    'uploaded_by',
    'notes',
])]
class MaterialDatasheet extends Model
{
    protected $attributes = [
        'is_active' => true,
        'extraction_status' => 'pending',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'file_size_bytes' => 'integer',
            'parse_confidence' => 'decimal:2',
            'parsed_fields' => 'array',
            'parse_warnings' => 'array',
            'parsed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
