<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'category',
    'title',
    'file_name',
    'file_size_kb',
    'issued_at',
    'expires_at',
    'status',
    'sort_order',
])]
class CompanyDocument extends Model
{
    protected function casts(): array
    {
        return [
            'file_size_kb' => 'integer',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
