<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('companies', 'code'),
            ],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'profile_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_profile_image' => ['nullable', 'boolean'],

            'legal_name' => ['nullable', 'string', 'max:190'],
            'company_type' => ['nullable', 'string', 'max:100'],
            'year_established' => ['nullable', 'integer', 'min:1900', 'max:' . (int) now()->year],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'hq_country' => ['nullable', 'string', 'max:100'],
            'hq_city' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'data_confidence' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'categories_served' => ['nullable', 'array', 'max:12'],
            'categories_served.*' => ['string', 'max:60'],
            'focus_markets' => ['nullable', 'array', 'max:12'],
            'focus_markets.*' => ['string', 'max:60'],
            'team_size' => ['nullable', 'integer', 'min:1', 'max:200000'],
            'parent_company_name' => ['nullable', 'string', 'max:190'],
            'parent_company_country' => ['nullable', 'string', 'max:100'],
            'annual_revenue_usd' => ['nullable', 'numeric', 'min:0'],
            'ebitda_margin' => ['nullable', 'numeric', 'between:-999.99,999.99'],
            'current_ratio' => ['nullable', 'numeric', 'min:0'],
            'total_assets_usd' => ['nullable', 'numeric', 'min:0'],
            'net_profit_usd' => ['nullable', 'numeric', 'between:-999999999999.99,999999999999.99'],
            'debt_to_equity' => ['nullable', 'numeric', 'between:-99999.99,99999.99'],
            'performance_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'last_reviewed_at' => ['nullable', 'date'],
            'financial_period_end' => ['nullable', 'date'],

            'contacts' => ['nullable', 'array', 'max:12'],
            'contacts.*.full_name' => ['nullable', 'string', 'max:150'],
            'contacts.*.job_title' => ['nullable', 'string', 'max:150'],
            'contacts.*.email' => ['nullable', 'email:rfc', 'max:190'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],

            'regions' => ['nullable', 'array', 'max:12'],
            'regions.*.country_name' => ['nullable', 'string', 'max:100'],
            'regions.*.country_code' => ['nullable', 'string', 'size:3'],
            'regions.*.region_name' => ['nullable', 'string', 'max:120'],
            'regions.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'regions.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'regions.*.project_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'regions.*.is_headquarters' => ['nullable', 'boolean'],

            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*.category' => ['nullable', Rule::in(['general', 'financial', 'legal', 'technical'])],
            'documents.*.title' => ['nullable', 'string', 'max:190'],
            'documents.*.file_name' => ['nullable', 'string', 'max:190'],
            'documents.*.file_size_kb' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'documents.*.issued_at' => ['nullable', 'date'],
            'documents.*.expires_at' => ['nullable', 'date'],
            'documents.*.status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],

            'registrations' => ['nullable', 'array', 'max:20'],
            'registrations.*.title' => ['nullable', 'string', 'max:190'],
            'registrations.*.reference_number' => ['nullable', 'string', 'max:120'],
            'registrations.*.issuing_authority' => ['nullable', 'string', 'max:190'],
            'registrations.*.issued_at' => ['nullable', 'date'],
            'registrations.*.expires_at' => ['nullable', 'date'],
            'registrations.*.status' => ['nullable', Rule::in(['verified', 'active', 'pending', 'expired'])],

            'certifications' => ['nullable', 'array', 'max:20'],
            'certifications.*.title' => ['nullable', 'string', 'max:190'],
            'certifications.*.reference_number' => ['nullable', 'string', 'max:120'],
            'certifications.*.issuing_authority' => ['nullable', 'string', 'max:190'],
            'certifications.*.issued_at' => ['nullable', 'date'],
            'certifications.*.expires_at' => ['nullable', 'date'],
            'certifications.*.status' => ['nullable', Rule::in(['verified', 'active', 'pending', 'expired'])],

            'insurances' => ['nullable', 'array', 'max:20'],
            'insurances.*.title' => ['nullable', 'string', 'max:190'],
            'insurances.*.reference_number' => ['nullable', 'string', 'max:120'],
            'insurances.*.policy_provider' => ['nullable', 'string', 'max:190'],
            'insurances.*.coverage_amount_usd' => ['nullable', 'numeric', 'min:0'],
            'insurances.*.issued_at' => ['nullable', 'date'],
            'insurances.*.expires_at' => ['nullable', 'date'],
            'insurances.*.status' => ['nullable', Rule::in(['verified', 'active', 'pending', 'expired'])],
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
            'remove_profile_image' => filter_var($this->input('remove_profile_image', false), FILTER_VALIDATE_BOOLEAN),
            'legal_name' => $this->cleanString($this->input('legal_name')),
            'company_type' => $this->cleanString($this->input('company_type')),
            'website' => $this->cleanString($this->input('website')),
            'hq_country' => $this->cleanString($this->input('hq_country')),
            'hq_city' => $this->cleanString($this->input('hq_city')),
            'description' => $this->cleanString($this->input('description')),
            'data_confidence' => $this->input('data_confidence', 'medium'),
            'categories_served' => $this->normalizeCsvArray($this->input('categories_served')),
            'focus_markets' => $this->normalizeCsvArray($this->input('focus_markets')),
            'parent_company_name' => $this->cleanString($this->input('parent_company_name')),
            'parent_company_country' => $this->cleanString($this->input('parent_company_country')),
            'last_reviewed_at' => $this->normalizeDate($this->input('last_reviewed_at')),
            'financial_period_end' => $this->normalizeDate($this->input('financial_period_end')),
            'contacts' => $this->normalizeContacts($this->input('contacts', [])),
            'regions' => $this->normalizeRegions($this->input('regions', [])),
            'documents' => $this->normalizeDocuments($this->input('documents', [])),
            'registrations' => $this->normalizeCredentialRows($this->input('registrations', []), false),
            'certifications' => $this->normalizeCredentialRows($this->input('certifications', []), false),
            'insurances' => $this->normalizeCredentialRows($this->input('insurances', []), true),
        ]);
    }

    private function normalizeContacts(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])->map(function ($row): array {
            return [
                'full_name' => $this->cleanString(data_get($row, 'full_name')),
                'job_title' => $this->cleanString(data_get($row, 'job_title')),
                'email' => $this->cleanString(data_get($row, 'email')),
                'phone' => $this->cleanString(data_get($row, 'phone')),
                'is_primary' => filter_var(data_get($row, 'is_primary'), FILTER_VALIDATE_BOOLEAN),
            ];
        })->values()->all();
    }

    private function normalizeRegions(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])->map(function ($row): array {
            return [
                'country_name' => $this->cleanString(data_get($row, 'country_name')),
                'country_code' => strtoupper((string) $this->cleanString(data_get($row, 'country_code'))),
                'region_name' => $this->cleanString(data_get($row, 'region_name')),
                'latitude' => $this->normalizeDecimal(data_get($row, 'latitude')),
                'longitude' => $this->normalizeDecimal(data_get($row, 'longitude')),
                'project_count' => $this->normalizeInteger(data_get($row, 'project_count')),
                'is_headquarters' => filter_var(data_get($row, 'is_headquarters'), FILTER_VALIDATE_BOOLEAN),
            ];
        })->values()->all();
    }

    private function normalizeDocuments(mixed $rows): array
    {
        return collect(is_array($rows) ? $rows : [])->map(function ($row): array {
            return [
                'category' => $this->cleanString(data_get($row, 'category')) ?? 'general',
                'title' => $this->cleanString(data_get($row, 'title')),
                'file_name' => $this->cleanString(data_get($row, 'file_name')),
                'file_size_kb' => $this->normalizeInteger(data_get($row, 'file_size_kb')),
                'issued_at' => $this->normalizeDate(data_get($row, 'issued_at')),
                'expires_at' => $this->normalizeDate(data_get($row, 'expires_at')),
                'status' => $this->cleanString(data_get($row, 'status')) ?? 'draft',
            ];
        })->values()->all();
    }

    private function normalizeCredentialRows(mixed $rows, bool $withCoverage): array
    {
        return collect(is_array($rows) ? $rows : [])->map(function ($row) use ($withCoverage): array {
            return [
                'title' => $this->cleanString(data_get($row, 'title')),
                'reference_number' => $this->cleanString(data_get($row, 'reference_number')),
                'issuing_authority' => $this->cleanString(data_get($row, 'issuing_authority')),
                'policy_provider' => $this->cleanString(data_get($row, 'policy_provider')),
                'coverage_amount_usd' => $withCoverage ? $this->normalizeDecimal(data_get($row, 'coverage_amount_usd')) : null,
                'issued_at' => $this->normalizeDate(data_get($row, 'issued_at')),
                'expires_at' => $this->normalizeDate(data_get($row, 'expires_at')),
                'status' => $this->cleanString(data_get($row, 'status')) ?? 'pending',
            ];
        })->values()->all();
    }

    private function normalizeCsvArray(mixed $value): ?array
    {
        if (is_array($value)) {
            $values = collect($value);
        } elseif (is_string($value)) {
            $values = collect(preg_split('/[,\n]+/', $value) ?: []);
        } else {
            return null;
        }

        $items = $values
            ->map(fn ($item) => $this->cleanString($item))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeDecimal(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
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
