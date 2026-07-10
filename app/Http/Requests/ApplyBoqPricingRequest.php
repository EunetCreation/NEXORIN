<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyBoqPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'material_markup_percent' => 'nullable|numeric|min:0|max:200',
            'min_margin_percent' => 'nullable|numeric|min:0|max:100',
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',
            'labor_rate_per_kwp_idr' => 'nullable|numeric|min:0|max:999999999',
            'logistics_cost_idr' => 'nullable|numeric|min:0|max:999999999',
            'other_cost_idr' => 'nullable|numeric|min:0|max:999999999',
            'contingency_percent' => 'nullable|numeric|min:0|max:100',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'tax_mode' => 'nullable|in:exclusive,inclusive',
            'tax_exempt_item_codes' => 'nullable|string|max:1000',
            'tax_exempt_categories' => 'nullable|string|max:1000',
            'services_tax_exempt' => 'nullable|boolean',
            'contingency_tax_exempt' => 'nullable|boolean',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'rounding_unit_idr' => 'nullable|numeric|in:1,100,1000,10000',
            'item_margin_overrides' => 'nullable|array',
            'item_margin_overrides.*' => 'nullable|numeric|min:0|max:1000',
            'item_discount_overrides' => 'nullable|array',
            'item_discount_overrides.*' => 'nullable|numeric|min:0|max:100',
            'pricing_note' => 'nullable|string|max:1000',
        ];
    }
}
