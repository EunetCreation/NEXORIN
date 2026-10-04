<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\StoreLoadApplianceRequest;
use App\Http\Requests\UpdateLoadApplianceRequest;
use App\Models\Company;
use App\Models\LoadAnalysis;
use App\Models\LoadAppliance;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LoadApplianceController extends Controller
{
    use AuthorizesProjectResource;

    /**
     * Show the appliance creation form.
     */
    public function create(
        Request $request,
        Company $company,
        Project $project
    ): View|RedirectResponse {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            return redirect()
                ->route('companies.load-analyses.show', [$company, $project])
                ->with('error', 'Archived projects cannot be updated.');
        }

        $analysis = LoadAnalysis::query()
            ->where('company_id', $company->getKey())
            ->where('project_id', $project->getKey())
            ->first();

        if (! $analysis) {
            return redirect()
                ->route('companies.projects.show', [$company, $project])
                ->with('info', 'Open Load Analysis from the project and confirm its project assumptions before adding equipment.');
        }

        $project->loadMissing('customer:id,name,customer_number');

        return view('load_analyses.appliances.create', [
            'company' => $company,
            'project' => $project,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Store a new appliance load record.
     */
    public function store(
        StoreLoadApplianceRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $analysis = LoadAnalysis::query()
            ->where('company_id', $company->getKey())
            ->where('project_id', $project->getKey())
            ->first();

        if (! $analysis) {
            return redirect()
                ->route('companies.projects.show', [$company, $project])
                ->with('error', 'The current Load Analysis is not available. Open Load Analysis from the project before adding equipment.');
        }

        LoadAppliance::query()->create([
            ...$request->validated(),
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'load_analysis_id' => $analysis->getKey(),
            'created_by' => $request->user()->getKey(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Appliance load added successfully.');
    }

    /**
     * Show the appliance edit form.
     */
    public function edit(
        Request $request,
        Company $company,
        Project $project,
        LoadAppliance $loadAppliance
    ): View|RedirectResponse {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            return redirect()
                ->route('companies.load-analyses.show', [$company, $project])
                ->with('error', 'Archived projects cannot be updated.');
        }

        $project->loadMissing([
            'customer:id,name,customer_number',
            'loadAnalysis:id,company_id,project_id,source_mode,supply_phase',
        ]);

        $analysis = $this->requireScopedAppliance(
            $company,
            $project,
            $loadAppliance
        );

        return view('load_analyses.appliances.edit', [
            'company' => $company,
            'project' => $project,
            'analysis' => $analysis,
            'loadAppliance' => $loadAppliance,
        ]);
    }

    /**
     * Update an appliance load record.
     */
    public function update(
        UpdateLoadApplianceRequest $request,
        Company $company,
        Project $project,
        LoadAppliance $loadAppliance
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $project->loadMissing('loadAnalysis:id,company_id,project_id');
        $this->requireScopedAppliance($company, $project, $loadAppliance);

        $loadAppliance->update([
            ...$request->validated(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Appliance load updated successfully.');
    }

    /**
     * Delete an appliance load record.
     */
    public function destroy(
        Request $request,
        Company $company,
        Project $project,
        LoadAppliance $loadAppliance
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $project->loadMissing('loadAnalysis:id,company_id,project_id');
        $this->requireScopedAppliance($company, $project, $loadAppliance);

        $loadAppliance->delete();

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Appliance load removed.');
    }

    /**
     * Authorize load analysis management.
     */
    private function authorizeManage(
        Request $request,
        Company $company,
        Project $project
    ): void {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.manage'],
            ['load_analysis.manage_own']
        );
    }

    /**
     * Confirm that the appliance belongs to the exact routed project and its
     * current Load Analysis. Nested URLs alone are not treated as authority.
     */
    private function requireScopedAppliance(
        Company $company,
        Project $project,
        LoadAppliance $loadAppliance
    ): LoadAnalysis {
        $analysis = $project->loadAnalysis;

        abort_unless(
            $analysis instanceof LoadAnalysis
                && (int) $analysis->company_id === (int) $company->getKey()
                && (int) $analysis->project_id === (int) $project->getKey()
                && (int) $loadAppliance->company_id === (int) $company->getKey()
                && (int) $loadAppliance->project_id === (int) $project->getKey()
                && (int) $loadAppliance->load_analysis_id === (int) $analysis->getKey(),
            Response::HTTP_NOT_FOUND
        );

        return $analysis;
    }
}
