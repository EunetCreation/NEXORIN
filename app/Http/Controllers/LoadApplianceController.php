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

        return view('load_analyses.appliances.create', [
            'company' => $company,
            'project' => $project,
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

        $analysis = $this->analysisFor($request, $company, $project);

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

        return view('load_analyses.appliances.edit', [
            'company' => $company,
            'project' => $project,
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
     * Get or create the project load analysis.
     */
    private function analysisFor(
        Request $request,
        Company $company,
        Project $project
    ): LoadAnalysis {
        return LoadAnalysis::query()->firstOrCreate(
            ['project_id' => $project->getKey()],
            [
                'company_id' => $company->getKey(),
                'source_mode' => 'combined',
                'supply_phase' => 'single_phase',
                'billing_days' => 30,
                'daytime_start_hour' => 6,
                'daytime_end_hour' => 18,
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]
        );
    }
}
