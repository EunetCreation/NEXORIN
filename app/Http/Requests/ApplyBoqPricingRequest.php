<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyBoqPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'material_markup_percent' => 'nullable|numeric|min:0|max:200',
            'labor_rate_per_kwp_idr' => 'nullable|numeric|min:0|max:999999999',
            'logistics_cost_idr' => 'nullable|numeric|min:0|max:999999999',
            'other_cost_idr' => 'nullable|numeric|min:0|max:999999999',
            'contingency_percent' => 'nullable|numeric|min:0|max:100',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'pricing_note' => 'nullable|string|max:1000',
        ];
    }
}
