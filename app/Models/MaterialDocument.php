<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'material_id',
    'document_type',
    'title',
    'original_filename',
    'file_path',
    'external_url',
    'mime_type',
    'file_size_bytes',
    'revision',
    'issued_at',
    'expires_at',
    'is_active',
    'verification_status',
    'verified_at',
    'verified_by',
    'verification_notes',
    'uploaded_by',
    'notes',
])]
class MaterialDocument extends Model
{
    protected $attributes = [
        'is_active' => true,
        'verification_status' => 'pending',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'file_size_bytes' => 'integer',
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
