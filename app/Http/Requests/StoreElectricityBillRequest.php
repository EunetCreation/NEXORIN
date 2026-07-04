<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreElectricityBillRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billing_month' => ['required', 'date_format:Y-m'],
            'energy_kwh' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'bill_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'peak_demand_kw' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }
}
