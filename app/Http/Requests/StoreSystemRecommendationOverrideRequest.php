<?php

namespace App\Http\Requests;

use App\Models\SystemRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemRecommendationOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'override_system' => ['required', Rule::in(SystemRecommendation::SYSTEMS)],
            'override_reason' => ['required', 'string', 'min:20', 'max:3000'],
        ];
    }
}
