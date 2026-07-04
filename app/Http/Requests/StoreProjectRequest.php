<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
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
        $companyId = $company instanceof Company
            ? $company->getKey()
            : 0;
        $canCreateCompanyProject = $company instanceof Company
            && $this->user()?->hasCompanyPermission(
                $company,
                'project.create'
            );

        return [
            'customer_id' => [
                $canCreateCompanyProject ? 'required' : 'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->where('status', 'active')
                ),
            ],
            'name' => ['required', 'string', 'max:180'],
            'building_type' => [
                'required',
                Rule::in(Project::BUILDING_TYPES),
            ],
            'proposed_system' => [
                'required',
                Rule::in(Project::PROPOSED_SYSTEMS),
            ],
            'site_address' => ['required', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_engineer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('status', 'active')
                ),
            ],
            'assigned_sales_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('status', 'active')
                ),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $company = $this->route('company');

            if (! $company instanceof Company) {
                return;
            }

            $this->validateAssignee(
                $validator,
                $company,
                'assigned_engineer_id',
                'engineer',
                'The selected engineer is not an active Engineer member of this company.'
            );

            $this->validateAssignee(
                $validator,
                $company,
                'assigned_sales_id',
                'sales',
                'The selected sales user is not an active Sales member of this company.'
            );
        });
    }

    /**
     * Validate an assigned company member and role.
     */
    private function validateAssignee(
        Validator $validator,
        Company $company,
        string $field,
        string $roleCode,
        string $message
    ): void {
        $userId = $this->integer($field);

        if (! $userId) {
            return;
        }

        $isValid = CompanyMembership::query()
            ->where('company_id', $company->getKey())
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas(
                'roles',
                fn ($query) => $query
                    ->where('roles.code', $roleCode)
                    ->where('roles.scope', 'company')
                    ->where('roles.is_active', true)
            )
            ->exists();

        if (! $isValid) {
            $validator->errors()->add($field, $message);
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'site_address' => trim((string) $this->input('site_address')),
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
