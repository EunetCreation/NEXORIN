<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Project;
use App\Services\Quantity\QuantityEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillOfMaterialController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly QuantityEngine $quantityEngine
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = ['all', 'not_generated', 'draft', 'generated', 'locked'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'technicalCalculation:id,project_id,result_status,calculated_at,verified_at',
                'billOfMaterial:id,project_id,bom_number,status,generated_at',
            ]);

        if (! $user->hasCompanyPermission($company, 'calculation.view')) {
            $query->ownedBy($user);
        }

        $projects = $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('project_number', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'not_generated', fn (Builder $builder) => $builder->whereDoesntHave('billOfMaterial'))
            ->when(
                $status !== 'all' && $status !== 'not_generated',
                fn (Builder $builder) => $builder->whereHas(
                    'billOfMaterial',
                    fn (Builder $bom) => $bom->where('status', $status)
                )
            )
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasCompanyPermission($company, 'calculation.view')) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'ready' => (clone $statsQuery)->whereHas('technicalCalculation', fn (Builder $calculation) => $calculation
                ->whereNotNull('calculated_at')
                ->whereNotNull('results'))
                ->count(),
            'generated' => (clone $statsQuery)->whereHas('billOfMaterial')->count(),
            'locked' => (clone $statsQuery)->whereHas('billOfMaterial', fn (Builder $bom) => $bom->where('status', 'locked'))->count(),
        ];

        return view('boms.index', [
            'company' => $company,
            'projects' => $projects,
            'stats' => $stats,
            'filters' => compact('search', 'status'),
        ]);
    }

    public function show(Request $request, Company $company, Project $project): View
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.view', 'calculation.run', 'calculation.revise'],
            ['calculation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number',
            'technicalCalculation.calculator:id,name',
            'technicalCalculation.verifier:id,name',
            'billOfMaterial.items',
            'billOfMaterial.generator:id,name',
        ]);

        $bom = $project->billOfMaterial;
        $calculation = $project->technicalCalculation;
        $canGenerate = $request->user()->hasAnyCompanyPermission($company, ['calculation.run', 'calculation.revise'])
            && ! $project->isArchived();

        return view('boms.show', compact(
            'company',
            'project',
            'bom',
            'calculation',
            'canGenerate'
        ));
    }

    public function generate(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.run', 'calculation.revise'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot generate BOM/BOQ drafts.');
        }

        $project->load([
            'loadAnalysis',
            'solarProfile',
            'roofSiteSurvey',
            'systemRecommendation',
            'technicalCalculation',
        ]);

        $calculation = $project->technicalCalculation;

        if (! $calculation || ! $calculation->calculated_at || ! $calculation->results) {
            throw ValidationException::withMessages([
                'technical_calculation' => 'Run the technical calculation before generating BOM/BOQ.',
            ]);
        }

        if ($calculation->isStale($project)) {
            throw ValidationException::withMessages([
                'technical_calculation' => 'Source data changed after the last technical calculation. Recalculate before generating BOM/BOQ.',
            ]);
        }

        $payload = $this->quantityEngine->generate($project, $calculation);

        DB::transaction(function () use ($request, $company, $project, $calculation, $payload): void {
            $bom = $project->billOfMaterial()->firstOrNew();
            $isNew = ! $bom->exists;

            $bom->fill([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'technical_calculation_id' => $calculation->getKey(),
                'bom_number' => $bom->bom_number ?: $this->nextBomNumber($company),
                'status' => 'generated',
                'summary' => $payload['summary'],
                'warnings' => $payload['warnings'],
                'source_snapshot' => $payload['source_snapshot'],
                'generated_by' => $request->user()->getKey(),
                'generated_at' => now(),
                'created_by' => $isNew
                    ? $request->user()->getKey()
                    : $bom->created_by,
                'updated_by' => $request->user()->getKey(),
            ]);

            $bom->save();
            $bom->items()->delete();
            $bom->items()->createMany($payload['items']);
        });

        return redirect()
            ->route('companies.boms.show', [$company, $project])
            ->with('success', 'BOM/BOQ draft generated from the latest technical calculation.');
    }

    private function nextBomNumber(Company $company): string
    {
        $prefix = 'BOM-'.$company->code.'-'.now()->format('ymd');
        $count = BillOfMaterial::query()
            ->where('company_id', $company->getKey())
            ->where('bom_number', 'like', $prefix.'%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $count);
    }
}
