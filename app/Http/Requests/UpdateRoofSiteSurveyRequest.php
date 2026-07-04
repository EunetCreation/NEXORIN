<?php

namespace App\Http\Requests;

use App\Models\RoofSiteSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoofSiteSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'survey_date' => ['nullable', 'date'],
            'surveyed_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'access_method' => ['nullable', Rule::in(RoofSiteSurvey::ACCESS_METHODS)],
            'access_condition' => ['required', Rule::in(RoofSiteSurvey::ACCESS_CONDITIONS)],
            'mounting_preference' => ['required', Rule::in(RoofSiteSurvey::MOUNTING_PREFERENCES)],
            'structural_review_status' => ['required', Rule::in(RoofSiteSurvey::STRUCTURAL_REVIEW_STATUSES)],
            'structural_notes' => ['nullable', 'string', 'max:3000'],
            'fall_protection_available' => ['nullable', 'boolean'],
            'lightning_protection_present' => ['nullable', 'boolean'],
            'electrical_room_distance_m' => ['nullable', 'numeric', 'between:0,10000'],
            'estimated_cable_route_m' => ['nullable', 'numeric', 'between:0,10000'],
            'weather_condition' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
