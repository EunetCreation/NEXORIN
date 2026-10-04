<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $company = $this->route('company');
        $companyId = $company instanceof Company ? $company->getKey() : 0;
        $currentYear = (int) now()->year;

        return [
            'name' => ['required', 'string', 'max:150'],
            'preferred_name' => ['nullable', 'string', 'max:150'],
            'type' => ['required', Rule::in(['residential', 'commercial'])],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'timezone'],
            'year_established' => ['nullable', 'integer', 'min:1800', 'max:'.$currentYear],
            'employee_count' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'annual_revenue_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'revenue_currency_code' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:4000'],
            'relationship_stage' => ['nullable', Rule::in(['lead', 'prospect', 'active', 'on_hold', 'closed'])],

            'primary_industry' => ['nullable', 'string', 'max:120'],
            'sub_industry' => ['nullable', 'string', 'max:120'],
            'business_model' => ['nullable', 'string', 'max:120'],
            'industry_tags' => ['nullable', 'array', 'max:12'],
            'industry_tags.*' => ['string', 'max:60'],

            'parent_company_assessment' => ['nullable', Rule::in(['not_assessed', 'no_parent', 'has_parent'])],
            'parent_customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active')),
            ],
            'external_parent_name' => ['nullable', 'string', 'max:190'],
            'parent_company_country' => ['nullable', 'string', 'max:100'],
            'ownership_pct' => ['nullable', 'numeric', 'between:0,100'],

            'hq_country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:2000'],
            'operating_countries' => ['nullable', 'array', 'max:30'],
            'operating_countries.*' => ['string', 'max:100'],

            'account_owner_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'sales_team' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'business_unit' => ['nullable', 'string', 'max:120'],

            'contacts' => ['nullable', 'array', 'max:8'],
            'contacts.*.full_name' => ['nullable', 'string', 'max:150'],
            'contacts.*.job_title' => ['nullable', 'string', 'max:150'],
            'contacts.*.email' => ['nullable', 'email:rfc', 'max:190'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],

            'portal_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active')),
                Rule::unique('customers', 'portal_user_id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],

            'opportunity_assessment' => ['nullable', Rule::in(['not_assessed', 'no_opportunity', 'has_opportunity'])],
            'create_opportunity' => ['nullable', 'boolean'],
            'opportunity_name' => ['nullable', 'required_if:create_opportunity,1', 'string', 'max:190'],
            'opportunity_type' => ['nullable', 'string', 'max:80'],
            'opportunity_estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'opportunity_currency_code' => ['nullable', 'string', 'size:3'],
            'opportunity_probability_pct' => ['nullable', 'integer', 'between:0,100'],
            'opportunity_expected_close_date' => ['nullable', 'date'],

            'preferred_contract_type' => ['nullable', 'string', 'max:100'],
            'engagement_model' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'contract_duration' => ['nullable', 'string', 'max:100'],
            'key_considerations' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'documents' => ['nullable', 'array', 'max:8'],
            'documents.incorporation' => ['nullable', 'file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:25600'],
            'documents.tax_registration' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:25600'],
            'documents.financial_statements' => ['nullable', 'file', 'mimes:pdf,xls,xlsx', 'max:25600'],
            'documents.company_profile' => ['nullable', 'file', 'mimes:pdf,ppt,pptx', 'max:25600'],
            'review_acknowledged' => ['nullable', 'required_if:submit_intent,create', 'accepted'],
            'submit_intent' => ['nullable', Rule::in(['create', 'save_exit'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $company = $this->route('company');
            if (! $company instanceof Company) {
                return;
            }

            $portalUserId = $this->integer('portal_user_id');
            if ($portalUserId && ! $this->hasCompanyRole($company, $portalUserId, 'customer')) {
                $validator->errors()->add(
                    'portal_user_id',
                    'Select an active company member with the Customer role.'
                );
            }

            $accountOwnerId = $this->integer('account_owner_user_id');
            if ($accountOwnerId && ! $this->hasActiveCompanyMembership($company, $accountOwnerId)) {
                $validator->errors()->add(
                    'account_owner_user_id',
                    'Select an active member of this company as the account owner.'
                );
            }

            $parentCustomerId = $this->integer('parent_customer_id');
            if ($parentCustomerId) {
                $validParent = Customer::query()
                    ->whereKey($parentCustomerId)
                    ->where('company_id', $company->getKey())
                    ->where('status', 'active')
                    ->exists();

                if (! $validParent) {
                    $validator->errors()->add(
                        'parent_customer_id',
                        'Select an active parent customer from this company workspace.'
                    );
                }
            }

            $parentAssessment = $this->input('parent_company_assessment', 'not_assessed');
            if ($parentAssessment === 'has_parent'
                && ! $this->integer('parent_customer_id')
                && blank($this->input('external_parent_name'))) {
                $validator->errors()->add(
                    'parent_company_assessment',
                    'Select an existing parent customer or enter the external parent company name.'
                );
            }

            $opportunityAssessment = $this->input('opportunity_assessment', 'not_assessed');
            if ($opportunityAssessment === 'has_opportunity' && blank($this->input('opportunity_name'))) {
                $validator->errors()->add(
                    'opportunity_name',
                    'Enter the opportunity name when an opportunity is confirmed.'
                );
            }

            $contacts = collect($this->input('contacts', []))
                ->filter(fn ($row) => is_array($row) && collect($row)->except('is_primary')->filter(fn ($value) => filled($value))->isNotEmpty());

            if ($contacts->where('is_primary', true)->count() > 1) {
                $validator->errors()->add('contacts', 'Only one customer contact can be marked as primary.');
            }

            foreach ($contacts as $index => $row) {
                if (blank(data_get($row, 'full_name'))) {
                    $validator->errors()->add("contacts.$index.full_name", 'Enter a contact name or remove the incomplete contact row.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $parentAssessment = $this->cleanString($this->input('parent_company_assessment'));
        if ($parentAssessment === null) {
            $parentAssessment = $this->filled('parent_customer_id') || $this->filled('external_parent_name')
                ? 'has_parent'
                : 'not_assessed';
        }

        $opportunityAssessment = $this->cleanString($this->input('opportunity_assessment'));
        if ($opportunityAssessment === null) {
            $opportunityAssessment = filter_var($this->input('create_opportunity', false), FILTER_VALIDATE_BOOLEAN)
                ? 'has_opportunity'
                : 'not_assessed';
        }

        $parentHasRelationship = $parentAssessment === 'has_parent';
        $hasOpportunity = $opportunityAssessment === 'has_opportunity';

        $this->merge([
            'name' => $this->cleanString($this->input('name')),
            'preferred_name' => $this->cleanString($this->input('preferred_name')),
            'email' => $this->lowerEmail($this->input('email')),
            'phone' => $this->cleanString($this->input('phone')),
            'website' => $this->cleanString($this->input('website')),
            'tax_id' => $this->cleanString($this->input('tax_id')),
            'registration_number' => $this->cleanString($this->input('registration_number')),
            'currency_code' => $this->upperCode($this->input('currency_code')),
            'timezone' => $this->cleanString($this->input('timezone')),
            'revenue_currency_code' => $this->upperCode($this->input('revenue_currency_code')),
            'description' => $this->cleanString($this->input('description')),
            'relationship_stage' => $this->input('relationship_stage', 'lead'),
            'primary_industry' => $this->cleanString($this->input('primary_industry')),
            'sub_industry' => $this->cleanString($this->input('sub_industry')),
            'business_model' => $this->cleanString($this->input('business_model')),
            'industry_tags' => $this->normalizeList($this->input('industry_tags')),
            'parent_company_assessment' => $parentAssessment,
            'parent_customer_id' => $parentHasRelationship ? $this->input('parent_customer_id') : null,
            'external_parent_name' => $parentHasRelationship ? $this->cleanString($this->input('external_parent_name')) : null,
            'parent_company_country' => $parentHasRelationship ? $this->cleanString($this->input('parent_company_country')) : null,
            'ownership_pct' => $parentHasRelationship ? $this->input('ownership_pct') : null,
            'hq_country' => $this->cleanString($this->input('hq_country')),
            'city' => $this->cleanString($this->input('city')),
            'province' => $this->cleanString($this->input('province')),
            'postal_code' => $this->cleanString($this->input('postal_code')),
            'address' => $this->cleanString($this->input('address')),
            'operating_countries' => $this->normalizeList($this->input('operating_countries')),
            'sales_team' => $this->cleanString($this->input('sales_team')),
            'region' => $this->cleanString($this->input('region')),
            'business_unit' => $this->cleanString($this->input('business_unit')),
            'contacts' => $this->normalizeContacts($this->input('contacts', [])),
            'opportunity_assessment' => $opportunityAssessment,
            'create_opportunity' => $hasOpportunity,
            'opportunity_name' => $hasOpportunity ? $this->cleanString($this->input('opportunity_name')) : null,
            'opportunity_type' => $hasOpportunity ? $this->cleanString($this->input('opportunity_type')) : null,
            'opportunity_estimated_value' => $hasOpportunity ? $this->input('opportunity_estimated_value') : null,
            'opportunity_currency_code' => $hasOpportunity ? $this->upperCode($this->input('opportunity_currency_code')) : null,
            'opportunity_probability_pct' => $hasOpportunity ? $this->input('opportunity_probability_pct') : null,
            'opportunity_expected_close_date' => $hasOpportunity ? $this->input('opportunity_expected_close_date') : null,
            'preferred_contract_type' => $this->cleanString($this->input('preferred_contract_type')),
            'engagement_model' => $this->cleanString($this->input('engagement_model')),
            'payment_terms' => $this->cleanString($this->input('payment_terms')),
            'contract_duration' => $this->cleanString($this->input('contract_duration')),
            'key_considerations' => $this->cleanString($this->input('key_considerations')),
            'notes' => $this->cleanString($this->input('notes')),
            'submit_intent' => $this->input('submit_intent', 'create'),
        ]);
    }

    private function hasActiveCompanyMembership(Company $company, int $userId): bool
    {
        return CompanyMembership::query()
            ->where('company_id', $company->getKey())
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->exists();
    }

    private function hasCompanyRole(Company $company, int $userId, string $roleCode): bool
    {
        return CompanyMembership::query()
            ->where('company_id', $company->getKey())
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.code', $roleCode)
                ->where('roles.scope', 'company')
                ->where('roles.is_active', true))
            ->exists();
    }

    private function normalizeContacts(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->map(fn ($row): array => [
                'full_name' => $this->cleanString(data_get($row, 'full_name')),
                'job_title' => $this->cleanString(data_get($row, 'job_title')),
                'email' => $this->lowerEmail(data_get($row, 'email')),
                'phone' => $this->cleanString(data_get($row, 'phone')),
                'is_primary' => filter_var(data_get($row, 'is_primary', false), FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();
    }

    private function normalizeList(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = preg_split('/[,\n]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return null;
        }

        $items = collect($value)
            ->map(fn ($item) => $this->cleanString($item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function lowerEmail(mixed $value): ?string
    {
        $value = $this->cleanString($value);
        return $value === null ? null : strtolower($value);
    }

    private function upperCode(mixed $value): ?string
    {
        $value = $this->cleanString($value);
        return $value === null ? null : strtoupper($value);
    }
}
