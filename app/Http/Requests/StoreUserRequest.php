<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $memberships = collect($this->input('memberships', []))
            ->filter(
                fn (mixed $membership): bool => is_array($membership)
                    && (
                        filled($membership['company_id'] ?? null)
                        || filled($membership['role_id'] ?? null)
                    )
            )
            ->map(fn (array $membership): array => [
                'company_id' => $membership['company_id'] ?? null,
                'role_id' => $membership['role_id'] ?? null,
                'status' => $membership['status'] ?? 'active',
            ])
            ->values()
            ->all();

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'is_super_admin' => $this->boolean('is_super_admin'),
            'memberships' => $memberships,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
            'is_super_admin' => ['required', 'boolean'],
            'memberships' => ['array', 'max:50'],
            'memberships.*.company_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('companies', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'memberships.*.role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where(fn ($query) => $query
                        ->where('scope', 'company')
                        ->where('is_active', true)),
            ],
            'memberships.*.status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'memberships.*.company_id.distinct' => 'Each company can only be assigned once.',
            'memberships.*.company_id.exists' => 'The selected company is unavailable.',
            'memberships.*.role_id.exists' => 'The selected company role is unavailable.',
        ];
    }
}
