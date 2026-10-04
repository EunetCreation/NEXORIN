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
use Symfony\Component\HttpFoundation\Response;

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

        $analysis = $this->requireAnalysis($company, $project);
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

        $analysis = $this->requireAnalysis($company, $project);

        abort_unless(
            (int) $electricityBill->company_id === (int) $company->getKey()
                && (int) $electricityBill->project_id === (int) $project->getKey()
                && (int) $electricityBill->load_analysis_id === (int) $analysis->getKey(),
            Response::HTTP_NOT_FOUND
        );

        $electricityBill->delete();

        return redirect()
            ->route('companies.load-analyses.show', [$company, $project])
            ->with('success', 'Electricity bill record removed.');
    }

    /**
     * Resolve the exact persisted Load Analysis for this company/project.
     * Bill mutations are explicit child actions and must not create the parent
     * analysis implicitly.
     */
    private function requireAnalysis(Company $company, Project $project): LoadAnalysis
    {
        $analysis = LoadAnalysis::query()
            ->where('company_id', $company->getKey())
            ->where('project_id', $project->getKey())
            ->first();

        abort_unless($analysis instanceof LoadAnalysis, Response::HTTP_NOT_FOUND);

        return $analysis;
    }
}
