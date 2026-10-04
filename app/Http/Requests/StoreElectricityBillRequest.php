<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreElectricityBillRequest extends FormRequest
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
            'billing_month' => ['required', 'date_format:Y-m'],
            'energy_kwh' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'bill_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'peak_demand_kw' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }
}
