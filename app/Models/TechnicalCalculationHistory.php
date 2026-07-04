<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['technical_calculation_id','user_id','event','from_status','to_status','notes','snapshot','changed_at'])]
class TechnicalCalculationHistory extends Model
{
    public function calculation(): BelongsTo { return $this->belongsTo(TechnicalCalculation::class, 'technical_calculation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    protected function casts(): array { return ['snapshot'=>'array','changed_at'=>'datetime']; }
}
