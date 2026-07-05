<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\CalculationFormula;
use App\Support\CalculationWarning;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'project_id',
    'system_type',
    'energy_offset_target_percent',
    'performance_ratio',
    'module_power_wp',
    'module_area_m2',
    'module_voc_v',
    'module_vmp_v',
    'module_isc_a',
    'module_imp_a',
    'module_voc_temp_coeff_percent_c',
    'module_vmp_temp_coeff_percent_c',
    'minimum_site_temperature_c',
    'maximum_cell_temperature_c',
    'inverter_ac_power_kw',
    'inverter_max_dc_voltage_v',
    'inverter_mppt_min_voltage_v',
    'inverter_mppt_max_voltage_v',
    'inverter_max_input_current_a',
    'inverter_mppt_count',
    'desired_dc_ac_ratio',
    'battery_backup_hours',
    'battery_dod_percent',
    'battery_efficiency_percent',
    'inverter_efficiency_percent',
    'dc_cable_length_m',
    'dc_cable_area_mm2',
    'ac_cable_length_m',
    'ac_cable_area_mm2',
    'cable_material',
    'maximum_voltage_drop_percent',
    'result_status',
    'results',
    'engine_results',
    'override_summary',
    'warnings',
    'source_snapshot',
    'engineering_notes',
    'calculated_by',
    'calculated_at',
    'verified_by',
    'verified_at',
    'created_by',
    'updated_by',
])]
class TechnicalCalculation extends Model
{
    /**
     * Default engineering inputs used before the first calculation is stored.
     * These values mirror the database defaults so the first form render is complete.
     *
     * @var array<string, int|float|string>
     */
    protected $attributes = [
        'system_type' => 'on_grid',
        'energy_offset_target_percent' => 80,
        'performance_ratio' => 0.78,
        'module_power_wp' => 550,
        'module_area_m2' => 2.58,
        'module_voc_v' => 49.90,
        'module_vmp_v' => 41.80,
        'module_isc_a' => 13.90,
        'module_imp_a' => 13.16,
        'module_voc_temp_coeff_percent_c' => -0.25,
        'module_vmp_temp_coeff_percent_c' => -0.29,
        'minimum_site_temperature_c' => 20,
        'maximum_cell_temperature_c' => 70,
        'inverter_ac_power_kw' => 5,
        'inverter_max_dc_voltage_v' => 600,
        'inverter_mppt_min_voltage_v' => 120,
        'inverter_mppt_max_voltage_v' => 550,
        'inverter_max_input_current_a' => 32,
        'inverter_mppt_count' => 2,
        'desired_dc_ac_ratio' => 1.15,
        'battery_backup_hours' => 0,
        'battery_dod_percent' => 90,
        'battery_efficiency_percent' => 95,
        'inverter_efficiency_percent' => 96,
        'dc_cable_length_m' => 20,
        'dc_cable_area_mm2' => 6,
        'ac_cable_length_m' => 15,
        'ac_cable_area_mm2' => 6,
        'cable_material' => 'copper',
        'maximum_voltage_drop_percent' => 2,
        'result_status' => 'preliminary',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TechnicalCalculationHistory::class)
            ->latest('changed_at')
            ->latest('id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(TechnicalCalculationOverride::class)
            ->latest('created_at')
            ->latest('id');
    }

    public function activeOverrides(): HasMany
    {
        return $this->hasMany(TechnicalCalculationOverride::class)
            ->where('is_active', true)
            ->latest('created_at')
            ->latest('id');
    }

    public function hasActiveOverrides(): bool
    {
        $summary = $this->override_summary ?? [];

        return is_array($summary) && $summary !== [];
    }



    /**
     * @return array<int, array<string, string>>
     */
    public function warningRecords(): array
    {
        return CalculationWarning::normalizeList($this->warnings ?? [], 'technical_calculation');
    }

    /**
     * @return array<string, int>
     */
    public function warningSeverityCounts(): array
    {
        $counts = [
            'critical' => 0,
            'warning' => 0,
            'caution' => 0,
            'info' => 0,
        ];

        foreach ($this->warningRecords() as $warning) {
            $severity = $warning['severity'] ?? 'info';
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function assumptionSnapshot(): array
    {
        $results = $this->results ?? [];
        $snapshot = $results['assumption_snapshot'] ?? [];

        return is_array($snapshot) ? $snapshot : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function formulaSnapshot(): array
    {
        $results = $this->results ?? [];
        $snapshot = $results['formula_snapshot'] ?? [];

        return is_array($snapshot) ? $snapshot : [];
    }

    public function formulaText(string $field): string
    {
        $formula = $this->formulaSnapshot()[$field] ?? null;

        return is_array($formula) ? CalculationFormula::text($formula) : '-';
    }

    public function getResultStatusLabelAttribute(): string
    {
        return match ($this->result_status) {
            'detailed' => 'Detailed Engineering Calculation',
            'verified' => 'Engineer Verified',
            default => 'Preliminary Engineering Estimate',
        };
    }

    public function isStale(Project $project): bool
    {
        if (! $this->calculated_at) {
            return false;
        }

        $latestSourceUpdate = collect([
            $project->loadAnalysis?->updated_at,
            $project->solarProfile?->updated_at,
            $project->roofSiteSurvey?->updated_at,
            $project->systemRecommendation?->updated_at,
        ])->filter()->max();

        return $latestSourceUpdate
            && $latestSourceUpdate->isAfter($this->calculated_at);
    }

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'engine_results' => 'array',
            'override_summary' => 'array',
            'warnings' => 'array',
            'source_snapshot' => 'array',
            'calculated_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
