<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\LoadAnalysis;
use App\Models\LoadAppliance;
use App\Models\Project;
use Symfony\Component\HttpFoundation\Response;

class UpdateLoadApplianceRequest extends StoreLoadApplianceRequest
{
    /**
     * Authorize the project action first, then validate the nested appliance
     * relationship before field validation can disclose request behavior.
     */
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $company = $this->route('company');
        $project = $this->route('project');
        $loadAppliance = $this->route('loadAppliance');

        if (! $company instanceof Company
            || ! $project instanceof Project
            || ! $loadAppliance instanceof LoadAppliance) {
            return false;
        }

        $project->loadMissing('loadAnalysis:id,company_id,project_id');
        $analysis = $project->loadAnalysis;

        if (! $analysis instanceof LoadAnalysis
            || (int) $analysis->company_id !== (int) $company->getKey()
            || (int) $analysis->project_id !== (int) $project->getKey()
            || (int) $loadAppliance->company_id !== (int) $company->getKey()
            || (int) $loadAppliance->project_id !== (int) $project->getKey()
            || (int) $loadAppliance->load_analysis_id !== (int) $analysis->getKey()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return true;
    }

    /**
     * Keep create normalization behavior while preserving the appliance's
     * hidden display order during edit. Browser input must not reassign it.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $loadAppliance = $this->route('loadAppliance');

        if ($loadAppliance instanceof LoadAppliance) {
            $this->merge([
                'sort_order' => $loadAppliance->sort_order,
            ]);
        }
    }
}
