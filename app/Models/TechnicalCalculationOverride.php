<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'project_id',
    'technical_calculation_id',
    'field',
    'label',
    'unit',
    'value_type',
    'engine_value',
    'previous_value',
    'override_value',
    'reason',
    'is_active',
    'reset_reason',
    'created_by',
    'reset_by',
    'reset_at',
])]
class TechnicalCalculationOverride extends Model
{
    /**
     * Fields that may be overridden by an engineer.
     *
     * @return array<string, array{label:string, unit:string, type:string}>
     */
    public static function fieldOptions(): array
    {
        return [
            'required_pv_kw' => ['label' => 'Required PV', 'unit' => 'kWp', 'type' => 'numeric'],
            'installed_pv_kw' => ['label' => 'Installed PV', 'unit' => 'kWp', 'type' => 'numeric'],
            'module_count' => ['label' => 'Module count', 'unit' => 'pcs', 'type' => 'integer'],
            'inverter_count' => ['label' => 'Inverter count', 'unit' => 'unit', 'type' => 'integer'],
            'total_inverter_ac_kw' => ['label' => 'Total inverter AC power', 'unit' => 'kW', 'type' => 'numeric'],
            'modules_per_string' => ['label' => 'Modules per string', 'unit' => 'pcs/string', 'type' => 'integer'],
            'string_count' => ['label' => 'String count', 'unit' => 'strings', 'type' => 'integer'],
            'dc_ac_ratio' => ['label' => 'DC/AC ratio', 'unit' => 'ratio', 'type' => 'numeric'],
            'battery_nominal_kwh' => ['label' => 'Battery nominal capacity', 'unit' => 'kWh', 'type' => 'numeric'],
            'battery_minimum_power_kw' => ['label' => 'Battery minimum power', 'unit' => 'kW', 'type' => 'numeric'],
            'estimated_daily_generation_kwh' => ['label' => 'Estimated daily generation', 'unit' => 'kWh/day', 'type' => 'numeric'],
            'annual_energy_kwh' => ['label' => 'Annual energy', 'unit' => 'kWh/year', 'type' => 'numeric'],
            'dc_voltage_drop_percent' => ['label' => 'DC voltage drop', 'unit' => '%', 'type' => 'numeric'],
            'ac_voltage_drop_percent' => ['label' => 'AC voltage drop', 'unit' => '%', 'type' => 'numeric'],
        ];
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(TechnicalCalculation::class, 'technical_calculation_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resetter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reset_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function formattedEngineValue(): string
    {
        return $this->formatValue($this->engine_value);
    }

    public function formattedPreviousValue(): string
    {
        return $this->formatValue($this->previous_value);
    }

    public function formattedOverrideValue(): string
    {
        return $this->formatValue($this->override_value);
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_numeric($value)) {
            $decimals = $this->value_type === 'integer' ? 0 : 3;
            $formatted = number_format((float) $value, $decimals);
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        } else {
            $formatted = (string) $value;
        }

        return trim($formatted.' '.($this->unit ?: ''));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reset_at' => 'datetime',
        ];
    }
}
