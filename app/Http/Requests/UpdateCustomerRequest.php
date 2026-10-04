<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = $this->route('company');
        $customer = $this->route('customer');

        return $user !== null
            && $company instanceof Company
            && $customer instanceof Customer
            && (int) $customer->company_id === (int) $company->getKey()
            && $customer->isActive()
            && $user->canAccessCompany($company)
            && $user->hasCompanyPermission($company, 'customer.update');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $company = $this->route('company');
        $customer = $this->route('customer');
        $companyId = $company instanceof Company ? $company->getKey() : 0;
        $customerId = $customer instanceof Customer ? $customer->getKey() : 0;
        $currentYear = (int) now()->year;

        return [
            'baseline_updated_at' => ['required', 'date'],
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

            'parent_company_assessment' => ['required', Rule::in(['not_assessed', 'no_parent', 'has_parent'])],
            'parent_customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->where('id', '<>', $customerId)),
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

            'contacts_sync' => ['required', 'accepted'],
            'contacts' => ['nullable', 'array', 'max:12'],
            'contacts.*.id' => [
                'nullable',
                'integer',
                Rule::exists('customer_contacts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('customer_id', $customerId)),
            ],
            'contacts.*.full_name' => ['nullable', 'string', 'max:150'],
            'contacts.*.job_title' => ['nullable', 'string', 'max:150'],
            'contacts.*.email' => ['nullable', 'email:rfc', 'max:190'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'contacts.*._remove' => ['nullable', 'boolean'],

            'portal_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active')),
                Rule::unique('customers', 'portal_user_id')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($customerId),
            ],

            'opportunity_assessment' => ['required', Rule::in(['not_assessed', 'no_opportunity', 'has_opportunity'])],
            'opportunities' => ['nullable', 'array', 'max:12'],
            'opportunities.*.id' => [
                'nullable',
                'integer',
                Rule::exists('customer_opportunities', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('customer_id', $customerId)),
            ],
            'opportunities.*.name' => ['nullable', 'string', 'max:190'],
            'opportunities.*.opportunity_type' => ['nullable', 'string', 'max:80'],
            'opportunities.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'opportunities.*.currency_code' => ['nullable', 'string', 'size:3'],
            'opportunities.*.probability_pct' => ['nullable', 'integer', 'between:0,100'],
            'opportunities.*.expected_close_date' => ['nullable', 'date'],

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
            'review_acknowledged' => ['required', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $company = $this->route('company');
            $customer = $this->route('customer');
            if (! $company instanceof Company || ! $customer instanceof Customer) {
                return;
            }

            $baseline = $this->cleanString($this->input('baseline_updated_at'));
            if ($baseline !== null && $customer->updated_at !== null) {
                try {
                    $baselineNormalized = Carbon::parse($baseline)->toIso8601String();
                } catch (\Throwable) {
                    $baselineNormalized = null;
                }

                if ($baselineNormalized !== $customer->updated_at->toIso8601String()) {
                    $validator->errors()->add(
                        'baseline_updated_at',
                        'This customer changed after the edit form was opened. Reload the page before saving to avoid overwriting a newer revision.'
                    );
                }
            }

            $portalUserId = $this->integer('portal_user_id');
            if ($portalUserId && ! $this->hasCompanyRole($company, $portalUserId, 'customer')) {
                $validator->errors()->add('portal_user_id', 'Select an active company member with the Customer role.');
            }

            $accountOwnerId = $this->integer('account_owner_user_id');
            if ($accountOwnerId && ! $this->hasActiveCompanyMembership($company, $accountOwnerId)) {
                $validator->errors()->add('account_owner_user_id', 'Select an active member of this company as the account owner.');
            }

            $parentAssessment = $this->input('parent_company_assessment', 'not_assessed');
            $parentCustomerId = $this->integer('parent_customer_id');
            $externalParentName = $this->cleanString($this->input('external_parent_name'));

            if ($parentAssessment === 'has_parent' && ! $parentCustomerId && $externalParentName === null) {
                $validator->errors()->add('parent_company_assessment', 'Select an existing parent customer or enter the external parent company name.');
            }

            if ($parentCustomerId && $externalParentName !== null) {
                $validator->errors()->add('external_parent_name', 'Use either an existing parent customer or an external parent company name, not both.');
            }

            if ($parentCustomerId === (int) $customer->getKey()) {
                $validator->errors()->add('parent_customer_id', 'A customer cannot be its own parent company.');
            }

            $submittedContacts = collect($this->input('contacts', []));
            $submittedExistingIds = $submittedContacts
                ->pluck('id')
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();
            $currentContactIds = $customer->contacts()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            if ($submittedExistingIds->all() !== $currentContactIds->all()) {
                $validator->errors()->add('contacts', 'The saved contact set changed or the form is incomplete. Reload the page before saving contact changes.');
            }

            $activeContacts = $submittedContacts->filter(function ($row): bool {
                if (! is_array($row) || filter_var(data_get($row, '_remove', false), FILTER_VALIDATE_BOOLEAN)) {
                    return false;
                }

                return filled(data_get($row, 'id'))
                    || collect($row)->only(['full_name', 'job_title', 'email', 'phone'])->filter(fn ($value) => filled($value))->isNotEmpty();
            });

            if ($activeContacts->filter(fn ($row) => filter_var(data_get($row, 'is_primary', false), FILTER_VALIDATE_BOOLEAN))->count() > 1) {
                $validator->errors()->add('contacts', 'Only one customer contact can be marked as primary.');
            }

            foreach ($activeContacts as $index => $row) {
                if (blank(data_get($row, 'full_name'))) {
                    $validator->errors()->add("contacts.$index.full_name", 'Enter a contact name or remove the incomplete contact row.');
                }
            }

            $submittedOpportunities = collect($this->input('opportunities', []))->filter(function ($row): bool {
                if (! is_array($row)) {
                    return false;
                }

                return filled(data_get($row, 'id'))
                    || collect($row)->except('id')->filter(fn ($value) => filled($value))->isNotEmpty();
            });

            foreach ($submittedOpportunities as $index => $row) {
                if (blank(data_get($row, 'name'))) {
                    $validator->errors()->add("opportunities.$index.name", 'Enter an opportunity name or remove the incomplete opportunity row.');
                }

                $opportunityId = data_get($row, 'id');
                if (filled($opportunityId) && ! $customer->opportunities()->whereKey((int) $opportunityId)->where('status', 'open')->exists()) {
                    $validator->errors()->add("opportunities.$index.id", 'Only open opportunities can be edited here. Reload the customer to review the current opportunity state.');
                }
            }

            $openOpportunityCount = $customer->opportunities()->where('status', 'open')->count();
            $newOpportunityCount = $submittedOpportunities->filter(fn ($row) => blank(data_get($row, 'id')))->count();
            $opportunityAssessment = $this->input('opportunity_assessment', 'not_assessed');

            if ($opportunityAssessment === 'has_opportunity' && ($openOpportunityCount + $newOpportunityCount) < 1) {
                $validator->errors()->add('opportunity_assessment', 'Record at least one open opportunity when the relationship is assessed as having an opportunity.');
            }

            if ($opportunityAssessment !== 'has_opportunity' && $openOpportunityCount > 0) {
                $validator->errors()->add('opportunity_assessment', 'Open opportunity records already exist. Keep the assessment as Yes until those records are closed through their controlled workflow.');
            }

            if ($opportunityAssessment !== 'has_opportunity' && $newOpportunityCount > 0) {
                $validator->errors()->add('opportunity_assessment', 'Set the opportunity assessment to Yes before adding a new opportunity record.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $parentAssessment = $this->cleanString($this->input('parent_company_assessment')) ?? 'not_assessed';
        $opportunityAssessment = $this->cleanString($this->input('opportunity_assessment')) ?? 'not_assessed';
        $parentHasRelationship = $parentAssessment === 'has_parent';

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
            'relationship_stage' => $this->cleanString($this->input('relationship_stage')) ?? 'lead',
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
            'opportunities' => $this->normalizeOpportunities($this->input('opportunities', [])),
            'preferred_contract_type' => $this->cleanString($this->input('preferred_contract_type')),
            'engagement_model' => $this->cleanString($this->input('engagement_model')),
            'payment_terms' => $this->cleanString($this->input('payment_terms')),
            'contract_duration' => $this->cleanString($this->input('contract_duration')),
            'key_considerations' => $this->cleanString($this->input('key_considerations')),
            'notes' => $this->cleanString($this->input('notes')),
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
                'id' => filled(data_get($row, 'id')) ? (int) data_get($row, 'id') : null,
                'full_name' => $this->cleanString(data_get($row, 'full_name')),
                'job_title' => $this->cleanString(data_get($row, 'job_title')),
                'email' => $this->lowerEmail(data_get($row, 'email')),
                'phone' => $this->cleanString(data_get($row, 'phone')),
                'is_primary' => filter_var(data_get($row, 'is_primary', false), FILTER_VALIDATE_BOOLEAN),
                '_remove' => filter_var(data_get($row, '_remove', false), FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();
    }

    private function normalizeOpportunities(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])
            ->map(fn ($row): array => [
                'id' => filled(data_get($row, 'id')) ? (int) data_get($row, 'id') : null,
                'name' => $this->cleanString(data_get($row, 'name')),
                'opportunity_type' => $this->cleanString(data_get($row, 'opportunity_type')),
                'estimated_value' => data_get($row, 'estimated_value'),
                'currency_code' => $this->upperCode(data_get($row, 'currency_code')),
                'probability_pct' => data_get($row, 'probability_pct'),
                'expected_close_date' => data_get($row, 'expected_close_date'),
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
