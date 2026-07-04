<?php

namespace App\Http\Requests;

use App\Models\TechnicalCalculationOverride;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicalCalculationOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(array_keys(TechnicalCalculationOverride::fieldOptions()))],
            'override_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'override_value' => 'override value',
            'reason' => 'engineering reason',
        ];
    }
}
