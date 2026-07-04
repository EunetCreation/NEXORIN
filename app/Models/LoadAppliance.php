<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'load_analysis_id',
    'name',
    'category',
    'quantity',
    'rated_power_w',
    'operating_hours_per_day',
    'usage_factor',
    'start_hour',
    'starting_multiplier',
    'is_critical',
    'sort_order',
    'notes',
    'created_by',
    'updated_by',
])]
class LoadAppliance extends Model
{
    /**
     * Get the company that owns the appliance record.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the project associated with the appliance record.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the parent load analysis.
     */
    public function loadAnalysis(): BelongsTo
    {
        return $this->belongsTo(LoadAnalysis::class);
    }

    /**
     * Get total connected load in kW before usage factor.
     */
    public function getConnectedLoadKwAttribute(): float
    {
        return round(
            ((int) $this->quantity * (float) $this->rated_power_w) / 1000,
            4
        );
    }

    /**
     * Get estimated daily energy in kWh.
     */
    public function getDailyEnergyKwhAttribute(): float
    {
        return round(
            $this->connected_load_kw
                * (float) $this->operating_hours_per_day
                * (float) $this->usage_factor,
            4
        );
    }

    /**
     * Get estimated starting load in kW.
     */
    public function getStartingLoadKwAttribute(): float
    {
        return round(
            $this->connected_load_kw * (float) $this->starting_multiplier,
            4
        );
    }

    /**
     * Get a human-readable operating schedule.
     */
    public function getScheduleLabelAttribute(): string
    {
        $start = (int) $this->start_hour;
        $duration = (float) $this->operating_hours_per_day;
        $end = fmod($start + $duration, 24);

        return sprintf(
            '%02d:00 for %s h (ends %s)',
            $start,
            rtrim(rtrim(number_format($duration, 2, '.', ''), '0'), '.'),
            $this->formatDecimalHour($end)
        );
    }

    /**
     * Format a decimal hour value as HH:MM.
     */
    private function formatDecimalHour(float $hour): string
    {
        $normalized = fmod($hour + 24, 24);
        $hours = (int) floor($normalized);
        $minutes = (int) round(($normalized - $hours) * 60);

        if ($minutes === 60) {
            $hours = ($hours + 1) % 24;
            $minutes = 0;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'rated_power_w' => 'decimal:3',
            'operating_hours_per_day' => 'decimal:2',
            'usage_factor' => 'decimal:4',
            'start_hour' => 'integer',
            'starting_multiplier' => 'decimal:2',
            'is_critical' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
