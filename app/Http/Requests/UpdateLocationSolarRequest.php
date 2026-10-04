<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UpdateLocationSolarRequest extends FormRequest
{
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

        if ($project->isArchived()) {
            return false;
        }

        if ($user->hasAnyCompanyPermission($company, ['location_solar.manage'])) {
            return true;
        }

        if ($user->hasAnyCompanyPermission($company, ['location_solar.manage_own'])) {
            if ($project->isOwnedBy($user)) {
                return true;
            }

            abort(Response::HTTP_NOT_FOUND);
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'site_address' => ['required', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
            'timezone' => ['nullable', 'timezone', 'max:64'],
            'data_source' => ['required', Rule::in(ProjectSolarProfile::DATA_SOURCES)],
            'source_name' => ['nullable', 'string', 'max:150'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'source_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'monthly' => ['required', 'array'],
            'monthly.*.ghi_daily_kwh_m2' => ['nullable', 'numeric', 'between:0,20'],
            'monthly.*.poa_daily_kwh_m2' => ['nullable', 'numeric', 'between:0,20'],
            'monthly.*.peak_sun_hours' => ['nullable', 'numeric', 'between:0,24'],
            'monthly.*.ambient_temperature_c' => ['nullable', 'numeric', 'between:-60,80'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
            'monthly.*.ghi_daily_kwh_m2.between' => 'Daily GHI must be between 0 and 20 kWh/m²/day.',
            'monthly.*.peak_sun_hours.between' => 'Peak Sun Hours must be between 0 and 24 hours/day.',
        ];
    }
}
