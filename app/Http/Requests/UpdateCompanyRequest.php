<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('companies', 'code')->ignore($company),
            ],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->cleanString($this->input('name')),
            'code' => strtoupper((string) $this->cleanString($this->input('code'))),
            'registration_number' => $this->cleanString($this->input('registration_number')),
            'email' => $this->cleanString($this->input('email')),
            'phone' => $this->cleanString($this->input('phone')),
            'address' => $this->cleanString($this->input('address')),
            'status' => $this->input('status', 'active'),
        ]);
    }

    /**
     * Normalize optional string input.
     */
    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
