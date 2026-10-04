<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'provider',
    'provider_user_id',
    'provider_email',
    'provider_name',
    'avatar_url',
    'linked_at',
    'last_login_at',
])]
class ExternalIdentity extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
