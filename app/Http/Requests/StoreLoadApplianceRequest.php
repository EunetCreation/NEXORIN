<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoadApplianceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'between:1,100000'],
            'rated_power_w' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'operating_hours_per_day' => ['required', 'numeric', 'gt:0', 'max:24'],
            'usage_factor' => ['required', 'numeric', 'gt:0', 'max:1'],
            'start_hour' => ['required', 'integer', 'between:0,23'],
            'starting_multiplier' => ['required', 'numeric', 'min:1', 'max:20'],
            'is_critical' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'category' => $this->filled('category')
                ? trim((string) $this->input('category'))
                : null,
            'is_critical' => $this->boolean('is_critical'),
            'sort_order' => $this->filled('sort_order')
                ? $this->integer('sort_order')
                : 0,
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }
}
