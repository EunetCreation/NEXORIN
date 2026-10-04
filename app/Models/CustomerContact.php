<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id','customer_id','full_name','job_title','email','phone','is_primary','sort_order','created_by','updated_by'])]
class CustomerContact extends Model
{
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    protected function casts(): array { return ['is_primary' => 'boolean', 'sort_order' => 'integer']; }
}
