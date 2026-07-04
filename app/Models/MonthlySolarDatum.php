<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_solar_profile_id',
    'month',
    'ghi_daily_kwh_m2',
    'poa_daily_kwh_m2',
    'peak_sun_hours',
    'ambient_temperature_c',
])]
class MonthlySolarDatum extends Model
{
    protected $table = 'monthly_solar_data';
    public const MONTH_NAMES = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public const MONTH_DAYS = [
        1 => 31,
        2 => 28,
        3 => 31,
        4 => 30,
        5 => 31,
        6 => 30,
        7 => 31,
        8 => 31,
        9 => 30,
        10 => 31,
        11 => 30,
        12 => 31,
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProjectSolarProfile::class, 'project_solar_profile_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getMonthNameAttribute(): string
    {
        return self::MONTH_NAMES[(int) $this->month] ?? 'Unknown';
    }

    public function getDaysInMonthAttribute(): int
    {
        return self::MONTH_DAYS[(int) $this->month] ?? 30;
    }

    public function isComplete(): bool
    {
        return $this->ghi_daily_kwh_m2 !== null
            && $this->peak_sun_hours !== null
            && $this->ambient_temperature_c !== null;
    }

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'ghi_daily_kwh_m2' => 'decimal:3',
            'poa_daily_kwh_m2' => 'decimal:3',
            'peak_sun_hours' => 'decimal:2',
            'ambient_temperature_c' => 'decimal:2',
        ];
    }
}
