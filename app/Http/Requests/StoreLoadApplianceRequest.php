<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreLoadApplianceRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $company = $this->route('company');
        $project = $this->route('project');

        if (! $user || ! $company instanceof Company || ! $project instanceof Project) {
            return false;
        }

        if ((int) $project->company_id !== (int) $company->getKey()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($user->hasAnyCompanyPermission($company, ['load_analysis.manage'])) {
            return true;
        }

        if ($user->hasAnyCompanyPermission($company, ['load_analysis.manage_own'])) {
            if ($project->isOwnedBy($user)) {
                return true;
            }

            abort(Response::HTTP_NOT_FOUND);
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'between:1,100000'],
            'rated_power_w' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'operating_hours_per_day' => ['required', 'numeric', 'gt:0', 'max:24'],
            'usage_factor' => ['required', 'numeric', 'gt:0', 'max:1'],
            'start_hour' => ['required', 'integer', 'between:0,23'],
            'starting_multiplier' => ['required', 'numeric', 'min:1', 'max:20'],
            'is_critical' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /**
     * Human-readable field names for validation feedback.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'equipment name',
            'category' => 'category',
            'quantity' => 'quantity',
            'rated_power_w' => 'rated power per unit',
            'operating_hours_per_day' => 'operating hours per day',
            'usage_factor' => 'usage factor',
            'start_hour' => 'daily start time',
            'starting_multiplier' => 'starting multiplier',
            'is_critical' => 'critical load classification',
            'sort_order' => 'display order',
            'notes' => 'engineering notes',
        ];
    }

    /**
     * Prepare the data for validation without silently choosing engineering inputs.
     */
    protected function prepareForValidation(): void
    {
        $prepared = [
            'name' => trim((string) $this->input('name')),
            'category' => $this->filled('category')
                ? trim((string) $this->input('category'))
                : null,
            'sort_order' => $this->filled('sort_order')
                ? $this->integer('sort_order')
                : 0,
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ];

        // Preserve an unselected state so the create form cannot silently turn
        // an omitted criticality choice into a standard-load classification.
        if ($this->has('is_critical') && $this->input('is_critical') !== '') {
            $prepared['is_critical'] = $this->boolean('is_critical');
        }

        $this->merge($prepared);
    }
}
