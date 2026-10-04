<?php

namespace App\Http\Requests;

use App\Models\CompanyRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyDirectoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'tier' => ['nullable', Rule::in([...CompanyRelationship::TIERS, 'unassigned'])],
            'region' => ['nullable', 'string', 'max:120'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'certification' => ['nullable', 'string', 'max:190'],
            'governance_state' => ['nullable', Rule::in(['ready', 'attention', 'expiring', 'incomplete', 'not_assessed'])],
            'onboarding_state' => ['nullable', Rule::in(['complete', 'in_progress', 'not_started', 'not_configured'])],
            'sort' => ['nullable', Rule::in(['name', 'code', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'type' => $validated['type'] ?? null,
            'status' => $validated['status'] ?? null,
            'tier' => $validated['tier'] ?? null,
            'region' => $validated['region'] ?? null,
            'specialization' => $validated['specialization'] ?? null,
            'certification' => $validated['certification'] ?? null,
            'governance_state' => $validated['governance_state'] ?? null,
            'onboarding_state' => $validated['onboarding_state'] ?? null,
            'sort' => $validated['sort'] ?? 'name',
            'direction' => $validated['direction'] ?? 'asc',
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }
}
