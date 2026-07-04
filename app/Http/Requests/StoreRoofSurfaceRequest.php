<?php

namespace App\Http\Requests;

use App\Models\RoofSurface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoofSurfaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'roof_type' => ['required', Rule::in(RoofSurface::ROOF_TYPES)],
            'roof_material' => ['required', Rule::in(RoofSurface::ROOF_MATERIALS)],
            'roof_condition' => ['required', Rule::in(RoofSurface::ROOF_CONDITIONS)],
            'gross_area_m2' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'setback_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'obstruction_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'walkway_area_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'tilt_deg' => ['required', 'numeric', 'between:0,90'],
            'azimuth_deg' => ['required', 'numeric', 'between:0,360'],
            'shading_percent' => ['required', 'numeric', 'between:0,100'],
            'max_additional_load_kg_m2' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'azimuth_deg.between' => 'Azimuth must be between 0 and 360 degrees.',
            'tilt_deg.between' => 'Tilt must be between 0 and 90 degrees.',
            'shading_percent.between' => 'Shading must be between 0 and 100 percent.',
        ];
    }
}
