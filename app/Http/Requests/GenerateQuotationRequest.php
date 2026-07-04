<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quotation_title' => ['required', 'string', 'max:180'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:90'],
            'include_item_detail' => ['nullable', 'boolean'],
            'payment_terms' => ['required', 'string', 'max:1000'],
            'delivery_terms' => ['required', 'string', 'max:1000'],
            'warranty_terms' => ['required', 'string', 'max:1000'],
            'exclusions' => ['nullable', 'string', 'max:1000'],
            'quotation_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_item_detail' => $this->boolean('include_item_detail'),
        ]);
    }
}
