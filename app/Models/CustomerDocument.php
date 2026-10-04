<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id','customer_id','category','title','original_name','storage_disk','storage_path','mime_type','size_bytes','sha256','status','created_by'])]
class CustomerDocument extends Model
{
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    protected function casts(): array { return ['size_bytes' => 'integer']; }
}
