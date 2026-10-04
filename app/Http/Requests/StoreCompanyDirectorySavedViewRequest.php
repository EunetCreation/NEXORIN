<?php

namespace App\Http\Requests;

use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyDirectorySavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['required', 'array'],
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.type' => ['nullable', 'string', 'max:100'],
            'filters.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'filters.tier' => ['nullable', Rule::in([...CompanyRelationship::TIERS, 'unassigned'])],
            'filters.region' => ['nullable', 'string', 'max:120'],
            'filters.specialization' => ['nullable', 'string', 'max:120'],
            'filters.certification' => ['nullable', 'string', 'max:190'],
            'filters.governance_state' => ['nullable', Rule::in(['ready', 'attention', 'expiring', 'incomplete', 'not_assessed'])],
            'filters.onboarding_state' => ['nullable', Rule::in(['complete', 'in_progress'])],
            'filters.sort' => ['nullable', Rule::in(['name', 'code', 'status', 'created_at'])],
            'filters.direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'filters.per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
