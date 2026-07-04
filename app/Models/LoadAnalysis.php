<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'project_id',
    'source_mode',
    'connected_power_va',
    'supply_phase',
    'tariff_group',
    'electricity_rate_per_kwh',
    'billing_days',
    'daytime_start_hour',
    'daytime_end_hour',
    'notes',
    'created_by',
    'updated_by',
])]
class LoadAnalysis extends Model
{
    public const SOURCE_MODES = [
        'equipment',
        'bill',
        'combined',
    ];

    public const SUPPLY_PHASES = [
        'single_phase',
        'three_phase',
    ];

    /**
     * Get the company that owns the analysis.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the project associated with the analysis.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get monthly electricity bills.
     */
    public function electricityBills(): HasMany
    {
        return $this->hasMany(ElectricityBill::class)
            ->latest('billing_month')
            ->latest('id');
    }

    /**
     * Get appliance load records.
     */
    public function appliances(): HasMany
    {
        return $this->hasMany(LoadAppliance::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Get the user who created the analysis.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the analysis.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Calculate all load summary metrics.
     *
     * @return array<string, float|int|null>
     */
    public function calculateSummary(): array
    {
        $appliances = $this->relationLoaded('appliances')
            ? $this->appliances
            : $this->appliances()->get();

        $bills = $this->relationLoaded('electricityBills')
            ? $this->electricityBills
            : $this->electricityBills()->get();

        $profile = $this->calculateHourlyProfile($appliances->all());
        $connectedLoadKw = (float) $appliances->sum(
            fn (LoadAppliance $appliance): float => $appliance->connected_load_kw
        );
        $startingLoadKw = (float) $appliances->sum(
            fn (LoadAppliance $appliance): float => $appliance->starting_load_kw
        );
        $equipmentDailyEnergyKwh = array_sum($profile);
        $billingDays = max((int) $this->billing_days, 1);
        $equipmentMonthlyEnergyKwh = $equipmentDailyEnergyKwh * $billingDays;
        $criticalDailyEnergyKwh = (float) $appliances
            ->where('is_critical', true)
            ->sum(fn (LoadAppliance $appliance): float => $appliance->daily_energy_kwh);
        $profilePeakDemandKw = $profile === [] ? 0.0 : max($profile);
        $dayEnergyKwh = 0.0;

        foreach ($profile as $hour => $energy) {
            if ($this->isDaytimeHour((int) $hour)) {
                $dayEnergyKwh += $energy;
            }
        }

        $nightEnergyKwh = max($equipmentDailyEnergyKwh - $dayEnergyKwh, 0);
        $averageBillKwh = $bills->isEmpty()
            ? null
            : (float) $bills->avg('energy_kwh');
        $averageBillAmount = $bills->whereNotNull('bill_amount')->isEmpty()
            ? null
            : (float) $bills->whereNotNull('bill_amount')->avg('bill_amount');
        $averageBillPeakKw = $bills->whereNotNull('peak_demand_kw')->isEmpty()
            ? null
            : (float) $bills->whereNotNull('peak_demand_kw')->avg('peak_demand_kw');
        $billDailyEnergyKwh = $averageBillKwh === null
            ? null
            : $averageBillKwh / $billingDays;

        $dailyEnergyKwh = match ($this->source_mode) {
            'bill' => $billDailyEnergyKwh ?? $equipmentDailyEnergyKwh,
            'equipment' => $equipmentDailyEnergyKwh,
            default => $equipmentDailyEnergyKwh > 0
                ? $equipmentDailyEnergyKwh
                : ($billDailyEnergyKwh ?? 0),
        };

        $monthlyEnergyKwh = $dailyEnergyKwh * $billingDays;
        $peakDemandKw = $profilePeakDemandKw > 0
            ? $profilePeakDemandKw
            : ($averageBillPeakKw ?? 0);
        $effectiveRate = $averageBillKwh && $averageBillAmount
            ? $averageBillAmount / $averageBillKwh
            : null;
        $loadFactorPercent = $peakDemandKw > 0
            ? min(($dailyEnergyKwh / ($peakDemandKw * 24)) * 100, 100)
            : 0;
        $billVariancePercent = $averageBillKwh && $averageBillKwh > 0
            && $equipmentMonthlyEnergyKwh > 0
                ? (($equipmentMonthlyEnergyKwh - $averageBillKwh) / $averageBillKwh) * 100
                : null;

        return [
            'appliance_count' => $appliances->count(),
            'bill_count' => $bills->count(),
            'connected_load_kw' => round($connectedLoadKw, 3),
            'starting_load_kw' => round($startingLoadKw, 3),
            'daily_energy_kwh' => round($dailyEnergyKwh, 3),
            'monthly_energy_kwh' => round($monthlyEnergyKwh, 3),
            'annual_energy_kwh' => round($monthlyEnergyKwh * 12, 3),
            'equipment_daily_energy_kwh' => round($equipmentDailyEnergyKwh, 3),
            'equipment_monthly_energy_kwh' => round($equipmentMonthlyEnergyKwh, 3),
            'critical_daily_energy_kwh' => round($criticalDailyEnergyKwh, 3),
            'peak_demand_kw' => round($peakDemandKw, 3),
            'day_energy_kwh' => round($dayEnergyKwh, 3),
            'night_energy_kwh' => round($nightEnergyKwh, 3),
            'day_share_percent' => $equipmentDailyEnergyKwh > 0
                ? round(($dayEnergyKwh / $equipmentDailyEnergyKwh) * 100, 2)
                : 0,
            'night_share_percent' => $equipmentDailyEnergyKwh > 0
                ? round(($nightEnergyKwh / $equipmentDailyEnergyKwh) * 100, 2)
                : 0,
            'load_factor_percent' => round($loadFactorPercent, 2),
            'average_bill_kwh' => $averageBillKwh === null
                ? null
                : round($averageBillKwh, 3),
            'average_bill_amount' => $averageBillAmount === null
                ? null
                : round($averageBillAmount, 2),
            'average_bill_peak_kw' => $averageBillPeakKw === null
                ? null
                : round($averageBillPeakKw, 3),
            'effective_rate_per_kwh' => $effectiveRate === null
                ? null
                : round($effectiveRate, 2),
            'bill_variance_percent' => $billVariancePercent === null
                ? null
                : round($billVariancePercent, 2),
        ];
    }

    /**
     * Calculate a 24-hour energy profile in kWh per hour.
     *
     * @param  array<int, LoadAppliance>  $appliances
     * @return array<int, float>
     */
    public function calculateHourlyProfile(array $appliances = []): array
    {
        if ($appliances === []) {
            $appliances = $this->appliances()->get()->all();
        }

        $profile = array_fill(0, 24, 0.0);

        foreach ($appliances as $appliance) {
            $powerKw = $appliance->connected_load_kw
                * (float) $appliance->usage_factor;
            $remainingHours = min(
                max((float) $appliance->operating_hours_per_day, 0),
                24
            );
            $position = (float) ((int) $appliance->start_hour % 24);
            $safetyCounter = 0;

            while ($remainingHours > 0.000001 && $safetyCounter < 48) {
                $hourFloor = floor($position);
                $hourIndex = ((int) $hourFloor) % 24;
                $fractionIntoHour = $position - $hourFloor;
                $availableInHour = 1 - $fractionIntoHour;
                $allocatedHours = min($remainingHours, $availableInHour);

                $profile[$hourIndex] += $powerKw * $allocatedHours;

                $remainingHours -= $allocatedHours;
                $position += $allocatedHours;
                $safetyCounter++;
            }
        }

        return array_map(
            fn (float $energy): float => round($energy, 4),
            $profile
        );
    }

    /**
     * Determine whether an hour falls inside the configured daytime window.
     */
    public function isDaytimeHour(int $hour): bool
    {
        $start = (int) $this->daytime_start_hour;
        $end = (int) $this->daytime_end_hour;

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $hour >= $start && $hour < $end;
        }

        return $hour >= $start || $hour < $end;
    }

    /**
     * Get a human-readable source mode label.
     */
    public function getSourceModeLabelAttribute(): string
    {
        return match ($this->source_mode) {
            'equipment' => 'Equipment List',
            'bill' => 'Electricity Bills',
            default => 'Combined Validation',
        };
    }

    /**
     * Get a human-readable supply phase label.
     */
    public function getSupplyPhaseLabelAttribute(): string
    {
        return match ($this->supply_phase) {
            'three_phase' => 'Three-phase',
            default => 'Single-phase',
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connected_power_va' => 'integer',
            'electricity_rate_per_kwh' => 'decimal:2',
            'billing_days' => 'integer',
            'daytime_start_hour' => 'integer',
            'daytime_end_hour' => 'integer',
        ];
    }
}
