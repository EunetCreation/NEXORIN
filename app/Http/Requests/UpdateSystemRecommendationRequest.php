<?php

namespace App\Http\Requests;

use App\Models\SystemRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grid_connection_status' => ['required', Rule::in(SystemRecommendation::GRID_CONNECTION_STATUSES)],
            'outage_frequency_per_month' => ['required', 'integer', 'between:0,100'],
            'average_outage_duration_hours' => ['required', 'numeric', 'between:0,72'],
            'backup_target_hours' => ['required', 'numeric', 'between:0,72'],
            'target_self_sufficiency_percent' => ['required', 'numeric', 'between:0,100'],
            'budget_flexibility' => ['required', Rule::in(SystemRecommendation::BUDGET_FLEXIBILITIES)],
            'export_policy' => ['required', Rule::in(SystemRecommendation::EXPORT_POLICIES)],
            'primary_objective' => ['required', Rule::in(SystemRecommendation::PRIMARY_OBJECTIVES)],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
