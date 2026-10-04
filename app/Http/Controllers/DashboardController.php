<?php

namespace App\Http\Controllers;

use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Material;
use App\Models\PriceBookItem;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Services\Company\ActiveCompanyResolver;
use App\Support\ProjectCockpit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the current user's company-scoped command center.
     */
    public function __invoke(
        Request $request,
        ActiveCompanyResolver $companies,
    ): View|RedirectResponse {
        $user = $request->user();

        if ($companies->requiresSelection($user, $request)) {
            return redirect()->route('workspace.select');
        }

        $company = $companies->resolve($user, $request);
        $permissions = $this->permissionSummary($user, $company);
        $overview = $company
            ? $this->buildOverview($company, $user, $permissions)
            : $this->emptyOverview();

        return view('dashboard', [
            'currentUser' => $user,
            'currentCompany' => $company,
            'roleLabel' => $this->roleLabel($user, $company),
            'permissions' => $permissions,
            ...$overview,
        ]);
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    private function buildOverview(Company $company, User $user, array $permissions): array
    {
        $companyId = (int) $company->getKey();
        $projectStatusCounts = $this->emptyProjectStatusCounts();
        $projectSystemCounts = $this->emptyProjectSystemCounts();
        $portfolioProjectCount = 0;
        $currentProjectsCount = 0;
        $workQueue = collect();
        $projectMapPoints = collect();
        $exactCoordinateCount = 0;

        $visibleProjects = $this->visibleProjectQuery($company, $user, $permissions);

        if ($permissions['projects']) {
            $rawProjectStatusCounts = (clone $visibleProjects)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $projectStatusCounts = collect(Project::STATUSES)
                ->mapWithKeys(fn (string $status): array => [
                    $status => (int) ($rawProjectStatusCounts[$status] ?? 0),
                ]);

            $rawProjectSystemCounts = (clone $visibleProjects)
                ->selectRaw('proposed_system, COUNT(*) as total')
                ->groupBy('proposed_system')
                ->pluck('total', 'proposed_system');

            $projectSystemCounts = collect(Project::PROPOSED_SYSTEMS)
                ->mapWithKeys(fn (string $system): array => [
                    $system => (int) ($rawProjectSystemCounts[$system] ?? 0),
                ]);

            $portfolioProjectCount = (int) $projectStatusCounts->sum();
            $currentProjectsCount = (int) $projectStatusCounts
                ->except(['completed', 'cancelled'])
                ->sum();

            $recentProjects = (clone $visibleProjects)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with([
                    'customer',
                    'loadAnalysis.electricityBills',
                    'loadAnalysis.appliances',
                    'solarProfile',
                    'roofSiteSurvey.surfaces',
                    'systemRecommendation',
                    'technicalCalculation',
                    'billOfMaterial',
                    'financialAnalysis',
                    'quotation',
                ])
                ->latest('updated_at')
                ->take(6)
                ->get();

            $workQueue = $recentProjects->map(function (Project $project): array {
                $cockpit = $this->dashboardCockpit($project);

                return [
                    'project' => $project,
                    // Existing ProjectCockpit completion is treated only as stored module coverage here,
                    // not physical project completion or an engineering verification score.
                    'module_coverage_percent' => (int) $cockpit['completion_percent'],
                    'next_step' => $cockpit['next_step'],
                    'warning_count' => (int) collect($cockpit['warning_counts'])->sum(),
                ];
            });

            $coordinateQuery = (clone $visibleProjects)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');

            $exactCoordinateCount = (clone $coordinateQuery)->count();
            $projectMapPoints = $coordinateQuery
                ->select(['id', 'name', 'project_number', 'status', 'latitude', 'longitude', 'updated_at'])
                ->latest('updated_at')
                ->take(24)
                ->get()
                ->map(fn (Project $project): array => [
                    'name' => $project->name,
                    'project_number' => $project->project_number ?: 'Project record',
                    'status' => $project->status,
                    'latitude' => (float) $project->latitude,
                    'longitude' => (float) $project->longitude,
                ])
                ->values();
        }

        $engineeringStatusCounts = collect([
            'stale' => 0,
            'preliminary' => 0,
            'detailed' => 0,
            'verified' => 0,
        ]);
        $engineeringRecordCount = 0;

        if ($permissions['engineering']) {
            // Mirror TechnicalCalculation::isStale() at aggregate-query level so a stored
            // "verified" status is never presented as current after one of its existing
            // upstream workflow records has changed. This is still the legacy timestamp
            // stale model; the canonical dependency graph remains a future architecture gap.
            $dashboardStatusExpression = $this->engineeringDashboardStatusExpression();
            $rawEngineeringCounts = TechnicalCalculation::query()
                ->where('company_id', $companyId)
                ->whereNotNull('calculated_at')
                ->whereIn('project_id', (clone $visibleProjects)->select('projects.id'))
                ->selectRaw($dashboardStatusExpression.' as dashboard_status, COUNT(*) as total')
                ->groupByRaw($dashboardStatusExpression)
                ->pluck('total', 'dashboard_status');

            $engineeringStatusCounts = $engineeringStatusCounts
                ->mapWithKeys(fn (int $count, string $status): array => [
                    $status => (int) ($rawEngineeringCounts[$status] ?? 0),
                ]);
            $engineeringRecordCount = (int) $engineeringStatusCounts->sum();
        }

        $engineeringRecordCoveragePercent = $portfolioProjectCount > 0
            ? min(100, (int) round(($engineeringRecordCount / $portfolioProjectCount) * 100))
            : 0;

        $catalogTotal = 0;
        $catalogApproved = 0;
        $catalogApprovalPercent = 0;
        $priceReferenceCount = 0;

        if ($permissions['catalog']) {
            $catalogAggregate = Material::query()
                ->visibleForCompany($company)
                ->active()
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN catalog_status = 'approved' THEN 1 ELSE 0 END) as approved")
                ->first();

            $catalogTotal = (int) ($catalogAggregate?->total ?? 0);
            $catalogApproved = (int) ($catalogAggregate?->approved ?? 0);
            $catalogApprovalPercent = $catalogTotal > 0
                ? min(100, (int) round(($catalogApproved / $catalogTotal) * 100))
                : 0;

            if ($permissions['price_references']) {
                $priceReferenceCount = PriceBookItem::query()
                    ->where('quote_status', 'active')
                    ->where(function (Builder $query): void {
                        $query->whereNull('valid_until')
                            ->orWhereDate('valid_until', '>=', today());
                    })
                    ->whereHas('priceBook', function (Builder $query) use ($companyId): void {
                        $query->where('company_id', $companyId)
                            ->where('status', 'active')
                            ->where(function (Builder $dateQuery): void {
                                $dateQuery->whereNull('effective_from')
                                    ->orWhereDate('effective_from', '<=', today());
                            })
                            ->where(function (Builder $dateQuery): void {
                                $dateQuery->whereNull('effective_until')
                                    ->orWhereDate('effective_until', '>=', today());
                            });
                    })
                    ->count();
            }
        }

        $pricedBoqCount = 0;
        $pricedBoqValueIdr = 0.0;

        if ($permissions['bom']) {
            $pricedBoqAggregate = BillOfMaterial::query()
                ->where('company_id', $companyId)
                ->where('pricing_status', 'priced')
                ->whereIn('project_id', (clone $visibleProjects)->select('projects.id'))
                ->selectRaw('COUNT(*) as record_count, COALESCE(SUM(grand_price_idr), 0) as total_value')
                ->first();

            $pricedBoqCount = (int) ($pricedBoqAggregate?->record_count ?? 0);
            $pricedBoqValueIdr = (float) ($pricedBoqAggregate?->total_value ?? 0);
        }

        $quotationStatusCounts = collect();
        $quotationOpenCount = 0;
        $recentQuotations = collect();

        if ($permissions['quotations']) {
            $quotationProjects = Project::query()
                ->forCompany($company)
                ->current();

            if (! $permissions['quotations_all']) {
                $quotationProjects->ownedBy($user);
            }

            $quotationQuery = Quotation::query()
                ->where('company_id', $companyId)
                ->whereIn('project_id', $quotationProjects->select('projects.id'));

            $quotationStatusCounts = (clone $quotationQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $quotationOpenCount = (int) $quotationStatusCounts
                ->except(['accepted', 'rejected', 'cancelled', 'expired'])
                ->sum();

            $recentQuotations = (clone $quotationQuery)
                ->with('project.customer')
                ->latest('updated_at')
                ->take(5)
                ->get();
        }

        return [
            'portfolioProjectCount' => $portfolioProjectCount,
            'currentProjectsCount' => $currentProjectsCount,
            'projectStatusCounts' => $projectStatusCounts,
            'projectSystemCounts' => $projectSystemCounts,
            'workQueue' => $workQueue,
            'projectMapPoints' => $projectMapPoints,
            'exactCoordinateCount' => $exactCoordinateCount,
            'engineeringRecordCount' => $engineeringRecordCount,
            'engineeringRecordCoveragePercent' => $engineeringRecordCoveragePercent,
            'engineeringStatusCounts' => $engineeringStatusCounts,
            'catalogTotal' => $catalogTotal,
            'catalogApproved' => $catalogApproved,
            'catalogApprovalPercent' => $catalogApprovalPercent,
            'priceReferenceCount' => $priceReferenceCount,
            'pricedBoqCount' => $pricedBoqCount,
            'pricedBoqValueIdr' => $pricedBoqValueIdr,
            'quotationStatusCounts' => $quotationStatusCounts,
            'quotationOpenCount' => $quotationOpenCount,
            'recentQuotations' => $recentQuotations,
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    private function visibleProjectQuery(Company $company, User $user, array $permissions): Builder
    {
        $query = Project::query()
            ->forCompany($company)
            ->current();

        if (! $permissions['projects']) {
            return $query->whereRaw('1 = 0');
        }

        if (! $permissions['projects_all']) {
            $query->ownedBy($user);
        }

        return $query;
    }

    /**
     * Keep the existing cockpit workflow vocabulary while correcting the Page 012 view of a
     * stored technical record that has become stale. This stays page-local so locked pages
     * using ProjectCockpit are not silently changed by the dashboard refinement.
     *
     * @return array<string, mixed>
     */
    private function dashboardCockpit(Project $project): array
    {
        $cockpit = ProjectCockpit::build($project);
        $calculation = $project->technicalCalculation;

        if (! $calculation || ! $calculation->isStale($project)) {
            return $cockpit;
        }

        $steps = collect($cockpit['steps'] ?? [])->map(function (array $step): array {
            if (($step['key'] ?? null) !== 'technical') {
                return $step;
            }

            return [
                ...$step,
                'state' => 'warning',
                'status' => 'Stale',
                'metric' => 'Recalculation required',
                'caption' => 'Recalculate after upstream changes',
            ];
        })->values();

        $completedSteps = $steps->whereIn('state', ['complete', 'locked'])->count();
        $cockpit['steps'] = $steps->all();
        $cockpit['completed_steps'] = $completedSteps;
        $cockpit['completion_percent'] = $steps->isNotEmpty()
            ? (int) round(($completedSteps / $steps->count()) * 100)
            : 0;
        $cockpit['next_step'] = $steps->first(
            fn (array $step): bool => ! in_array($step['state'] ?? null, ['complete', 'locked'], true),
        );

        return $cockpit;
    }

    /**
     * @return array<string, bool>
     */
    private function permissionSummary(User $user, ?Company $company): array
    {
        if (! $company) {
            return [
                'projects' => false,
                'projects_all' => false,
                'create_project' => false,
                'engineering' => false,
                'catalog' => false,
                'price_references' => false,
                'bom' => false,
                'quotations' => false,
                'quotations_all' => false,
            ];
        }

        $projectsAll = $user->hasCompanyPermission($company, 'project.view');
        $projectsOwn = $user->hasCompanyPermission($company, 'project.view_own');
        $quotationsAll = $user->hasCompanyPermission($company, 'quotation.view');
        $quotationsOwn = $user->hasCompanyPermission($company, 'quotation.view_own');

        return [
            'projects' => $projectsAll || $projectsOwn,
            'projects_all' => $projectsAll,
            'create_project' => $user->hasAnyCompanyPermission($company, ['project.create', 'project.create_own']),
            'engineering' => ($projectsAll || $projectsOwn)
                && $user->hasAnyCompanyPermission($company, ['calculation.view', 'calculation.view_own']),
            'catalog' => $user->hasCompanyPermission($company, 'equipment.view'),
            'price_references' => $user->hasAnyCompanyPermission($company, [
                'price.cost.view',
                'price.selling.view',
                'supplier.view',
            ]),
            // Portfolio selling-value aggregation follows the same customer-facing price visibility
            // boundary used by BOM/quotation workflows. Calculation permission alone is insufficient.
            'bom' => ($projectsAll || $projectsOwn)
                && $user->hasAnyCompanyPermission($company, ['price.selling.view', 'quotation.view']),
            'quotations' => $quotationsAll || $quotationsOwn,
            'quotations_all' => $quotationsAll,
        ];
    }

    /**
     * SQL representation of the current legacy TechnicalCalculation::isStale() rule.
     *
     * A future CalculationDependency graph should replace this timestamp comparison,
     * but the dashboard must not silently ignore stale state while that gap remains.
     */
    private function engineeringDashboardStatusExpression(): string
    {
        return "CASE WHEN "
            ."EXISTS (SELECT 1 FROM load_analyses WHERE load_analyses.project_id = technical_calculations.project_id AND load_analyses.updated_at > technical_calculations.calculated_at) OR "
            ."EXISTS (SELECT 1 FROM project_solar_profiles WHERE project_solar_profiles.project_id = technical_calculations.project_id AND project_solar_profiles.updated_at > technical_calculations.calculated_at) OR "
            ."EXISTS (SELECT 1 FROM roof_site_surveys WHERE roof_site_surveys.project_id = technical_calculations.project_id AND roof_site_surveys.updated_at > technical_calculations.calculated_at) OR "
            ."EXISTS (SELECT 1 FROM system_recommendations WHERE system_recommendations.project_id = technical_calculations.project_id AND system_recommendations.updated_at > technical_calculations.calculated_at) "
            ."THEN 'stale' ELSE result_status END";
    }

    private function roleLabel(User $user, ?Company $company): string
    {
        if ($user->isSuperAdmin()) {
            return 'Super Admin';
        }

        if (! $company) {
            return 'System User';
        }

        return $user->membershipForCompany($company)?->roles?->first()?->name
            ?? 'Company Member';
    }

    /**
     * @return Collection<string, int>
     */
    private function emptyProjectStatusCounts(): Collection
    {
        return collect(Project::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => 0]);
    }

    /**
     * @return Collection<string, int>
     */
    private function emptyProjectSystemCounts(): Collection
    {
        return collect(Project::PROPOSED_SYSTEMS)
            ->mapWithKeys(fn (string $system): array => [$system => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOverview(): array
    {
        return [
            'portfolioProjectCount' => 0,
            'currentProjectsCount' => 0,
            'projectStatusCounts' => $this->emptyProjectStatusCounts(),
            'projectSystemCounts' => $this->emptyProjectSystemCounts(),
            'workQueue' => collect(),
            'projectMapPoints' => collect(),
            'exactCoordinateCount' => 0,
            'engineeringRecordCount' => 0,
            'engineeringRecordCoveragePercent' => 0,
            'engineeringStatusCounts' => collect(['stale' => 0, 'preliminary' => 0, 'detailed' => 0, 'verified' => 0]),
            'catalogTotal' => 0,
            'catalogApproved' => 0,
            'catalogApprovalPercent' => 0,
            'priceReferenceCount' => 0,
            'pricedBoqCount' => 0,
            'pricedBoqValueIdr' => 0.0,
            'quotationStatusCounts' => collect(),
            'quotationOpenCount' => 0,
            'recentQuotations' => collect(),
        ];
    }
}
