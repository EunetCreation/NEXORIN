<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\StoreElectricityBillRequest;
use App\Models\Company;
use App\Models\ElectricityBill;
use App\Models\LoadAnalysis;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ElectricityBillController extends Controller
{
    use AuthorizesProjectResource;

    /**
     * Store or replace an electricity bill month.
     */
    public function store(
        StoreElectricityBillRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.manage'],
            ['load_analysis.manage_own']
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $analysis = $this->analysisFor($request, $company, $project);
        $month = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $request->validated('billing_month').'-01'
        )->startOfMonth();

        $bill = ElectricityBill::query()->firstOrNew([
            'load_analysis_id' => $analysis->getKey(),
            'billing_month' => $month->toDateString(),
        ]);

        $bill->fill([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'energy_kwh' => $request->validated('energy_kwh'),
            'bill_amount' => $request->validated('bill_amount'),
            'peak_demand_kw' => $request->validated('peak_demand_kw'),
            'updated_by' => $request->user()->getKey(),
        ]);

        if (! $bill->exists) {
            $bill->created_by = $request->user()->getKey();
        }

        $bill->save();

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Electricity bill record saved successfully.');
    }

    /**
     * Delete an electricity bill record.
     */
    public function destroy(
        Request $request,
        Company $company,
        Project $project,
        ElectricityBill $electricityBill
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['load_analysis.manage'],
            ['load_analysis.manage_own']
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $electricityBill->delete();

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Electricity bill record removed.');
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
