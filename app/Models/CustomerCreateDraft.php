<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id','user_id','payload','saved_at'])]
class CustomerCreateDraft extends Model
{
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    protected function casts(): array { return ['payload' => 'array', 'saved_at' => 'datetime']; }
}
