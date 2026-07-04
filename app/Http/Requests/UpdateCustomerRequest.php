<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $company = $this->route('company');
        $customer = $this->route('customer');
        $companyId = $company instanceof Company
            ? $company->getKey()
            : 0;
        $customerId = $customer instanceof Customer
            ? $customer->getKey()
            : 0;

        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => [
                'required',
                Rule::in(['residential', 'commercial']),
            ],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'portal_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('status', 'active')
                ),
                Rule::unique('customers', 'portal_user_id')
                    ->where(
                        fn ($query) => $query->where('company_id', $companyId)
                    )
                    ->ignore($customerId),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $portalUserId = $this->integer('portal_user_id');
            $company = $this->route('company');

            if (! $portalUserId || ! $company instanceof Company) {
                return;
            }

            $hasCustomerRole = CompanyMembership::query()
                ->where('company_id', $company->getKey())
                ->where('user_id', $portalUserId)
                ->where('status', 'active')
                ->whereHas(
                    'roles',
                    fn ($query) => $query
                        ->where('roles.code', 'customer')
                        ->where('roles.scope', 'company')
                        ->where('roles.is_active', true)
                )
                ->exists();

            if (! $hasCustomerRole) {
                $validator->errors()->add(
                    'portal_user_id',
                    'Select an active company member with the Customer role.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => $this->filled('email')
                ? strtolower(trim((string) $this->input('email')))
                : null,
            'phone' => $this->filled('phone')
                ? trim((string) $this->input('phone'))
                : null,
            'city' => $this->filled('city')
                ? trim((string) $this->input('city'))
                : null,
            'province' => $this->filled('province')
                ? trim((string) $this->input('province'))
                : null,
            'postal_code' => $this->filled('postal_code')
                ? trim((string) $this->input('postal_code'))
                : null,
        ]);
    }
}
