<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTechnicalCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('system_type') !== 'on_grid') {
            return;
        }

        $this->merge([
            'battery_backup_hours' => $this->filled('battery_backup_hours')
                ? $this->input('battery_backup_hours')
                : 0,
            'battery_dod_percent' => $this->filled('battery_dod_percent')
                ? $this->input('battery_dod_percent')
                : 90,
            'battery_efficiency_percent' => $this->filled('battery_efficiency_percent')
                ? $this->input('battery_efficiency_percent')
                : 95,
        ]);
    }

    public function rules(): array
    {
        return [
            'system_type' => ['required', Rule::in(['on_grid', 'hybrid'])],
            'energy_offset_target_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'performance_ratio' => ['required', 'numeric', 'min:0.4', 'max:1'],
            'module_power_wp' => ['required', 'numeric', 'min:50', 'max:1000'],
            'module_area_m2' => ['required', 'numeric', 'min:0.2', 'max:10'],
            'module_voc_v' => ['required', 'numeric', 'min:1', 'max:200'],
            'module_vmp_v' => ['required', 'numeric', 'min:1', 'lt:module_voc_v'],
            'module_isc_a' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'module_imp_a' => ['required', 'numeric', 'min:0.1', 'lte:module_isc_a'],
            'module_voc_temp_coeff_percent_c' => ['required', 'numeric', 'min:-2', 'max:0'],
            'module_vmp_temp_coeff_percent_c' => ['required', 'numeric', 'min:-2', 'max:0'],
            'minimum_site_temperature_c' => ['required', 'numeric', 'min:-50', 'max:50'],
            'maximum_cell_temperature_c' => ['required', 'numeric', 'min:25', 'max:100'],
            'inverter_ac_power_kw' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'inverter_max_dc_voltage_v' => ['required', 'numeric', 'min:50', 'max:2000'],
            'inverter_mppt_min_voltage_v' => ['required', 'numeric', 'min:20', 'max:1800'],
            'inverter_mppt_max_voltage_v' => [
                'required',
                'numeric',
                'gt:inverter_mppt_min_voltage_v',
                'lte:inverter_max_dc_voltage_v',
            ],
            'inverter_max_input_current_a' => ['required', 'numeric', 'min:1', 'max:1000'],
            'inverter_mppt_count' => ['required', 'integer', 'min:1', 'max:20'],
            'desired_dc_ac_ratio' => ['required', 'numeric', 'min:0.7', 'max:2'],
            'battery_backup_hours' => [
                'required_if:system_type,hybrid',
                'nullable',
                'numeric',
                'min:0',
                'max:168',
            ],
            'battery_dod_percent' => [
                'required_if:system_type,hybrid',
                'nullable',
                'numeric',
                'min:10',
                'max:100',
            ],
            'battery_efficiency_percent' => [
                'required_if:system_type,hybrid',
                'nullable',
                'numeric',
                'min:50',
                'max:100',
            ],
            'inverter_efficiency_percent' => ['required', 'numeric', 'min:50', 'max:100'],
            'dc_cable_length_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'dc_cable_area_mm2' => ['required', 'numeric', 'min:1', 'max:1000'],
            'ac_cable_length_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'ac_cable_area_mm2' => ['required', 'numeric', 'min:1', 'max:1000'],
            'cable_material' => ['required', Rule::in(['copper', 'aluminium'])],
            'maximum_voltage_drop_percent' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'engineering_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'module_voc_v' => 'module Voc',
            'module_vmp_v' => 'module Vmp',
            'module_isc_a' => 'module Isc',
            'module_imp_a' => 'module Imp',
            'inverter_mppt_min_voltage_v' => 'minimum MPPT voltage',
            'inverter_mppt_max_voltage_v' => 'maximum MPPT voltage',
            'battery_dod_percent' => 'battery depth of discharge',
        ];
    }
}
