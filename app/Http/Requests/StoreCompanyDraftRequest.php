<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * Drafts are intentionally permissive: authoritative validation happens
     * only on Save Changes. This lets autosave preserve incomplete edits.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'max:20'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'company_type' => ['nullable', 'string', 'max:100'],
            'year_established' => ['nullable'],
            'website' => ['nullable', 'string', 'max:255'],
            'hq_country' => ['nullable', 'string', 'max:100'],
            'hq_city' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'data_confidence' => ['nullable', 'string', 'max:20'],
            'categories_served' => ['nullable'],
            'focus_markets' => ['nullable'],
            'team_size' => ['nullable'],
            'parent_company_name' => ['nullable', 'string', 'max:190'],
            'parent_company_country' => ['nullable', 'string', 'max:100'],
            'annual_revenue_usd' => ['nullable'],
            'ebitda_margin' => ['nullable'],
            'current_ratio' => ['nullable'],
            'total_assets_usd' => ['nullable'],
            'net_profit_usd' => ['nullable'],
            'debt_to_equity' => ['nullable'],
            'performance_score' => ['nullable'],
            'last_reviewed_at' => ['nullable'],
            'financial_period_end' => ['nullable'],
            'contacts' => ['nullable', 'array', 'max:12'],
            'contacts.*' => ['array'],
            'contacts.*.*' => ['nullable'],
            'regions' => ['nullable', 'array', 'max:12'],
            'regions.*' => ['array'],
            'regions.*.*' => ['nullable'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['array'],
            'documents.*.*' => ['nullable'],
            'registrations' => ['nullable', 'array', 'max:20'],
            'registrations.*' => ['array'],
            'registrations.*.*' => ['nullable'],
            'certifications' => ['nullable', 'array', 'max:20'],
            'certifications.*' => ['array'],
            'certifications.*.*' => ['nullable'],
            'insurances' => ['nullable', 'array', 'max:20'],
            'insurances.*' => ['array'],
            'insurances.*.*' => ['nullable'],
        ];
    }
}
