<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\LoadAnalysis;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;

class UpdateLoadAnalysisRequest extends FormRequest
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
            'source_mode' => [
                'required',
                Rule::in(LoadAnalysis::SOURCE_MODES),
            ],
            'connected_power_va' => [
                'nullable',
                'integer',
                'min:1',
                'max:1000000000',
            ],
            'supply_phase' => [
                'required',
                Rule::in(LoadAnalysis::SUPPLY_PHASES),
            ],
            'tariff_group' => ['nullable', 'string', 'max:50'],
            'electricity_rate_per_kwh' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1000000000',
            ],
            'billing_days' => [
                'required',
                'integer',
                'between:1,366',
            ],
            'daytime_start_hour' => [
                'required',
                'integer',
                'between:0,23',
            ],
            'daytime_end_hour' => [
                'required',
                'integer',
                'between:0,23',
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->integer('daytime_start_hour')
                === $this->integer('daytime_end_hour')
            ) {
                $validator->errors()->add(
                    'daytime_end_hour',
                    'The daytime end hour must differ from the start hour.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tariff_group' => $this->filled('tariff_group')
                ? trim((string) $this->input('tariff_group'))
                : null,
            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }
}
