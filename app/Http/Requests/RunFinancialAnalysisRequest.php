<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunFinancialAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'electricity_tariff_idr_per_kwh' => 'nullable|numeric|min:0|max:999999999',
            'export_credit_percent' => 'nullable|numeric|min:0|max:100',
            'self_consumption_percent' => 'nullable|numeric|min:0|max:100',
            'tariff_escalation_percent' => 'nullable|numeric|min:0|max:100',
            'annual_om_percent' => 'nullable|numeric|min:0|max:100',
            'discount_rate_percent' => 'nullable|numeric|min:0|max:100',
            'energy_degradation_percent' => 'nullable|numeric|min:0|max:100',
            'project_lifetime_years' => 'nullable|integer|min:1|max:40',
            'replacement_year' => 'nullable|integer|min:0|max:40',
            'replacement_cost_idr' => 'nullable|numeric|min:0|max:999999999999',
            'project_capex_idr' => 'nullable|numeric|min:0|max:999999999999',
            'analysis_note' => 'nullable|string|max:1000',
        ];
    }
}
