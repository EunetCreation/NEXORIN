<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectCockpit;
use App\Services\Report\ClientReleaseGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Display projects available to the current user.
     */
    public function index(
        Request $request,
        Company $company
    ): View {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Project::STATUSES)],
            'system' => ['nullable', Rule::in(Project::PROPOSED_SYSTEMS)],
            'archive' => ['nullable', Rule::in(['current', 'archived', 'all'])],
            'sort' => [
                'nullable',
                Rule::in(['name', 'project_number', 'status', 'created_at', 'status_changed_at']),
            ],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? null;
        $system = $validated['system'] ?? null;
        $archive = $validated['archive'] ?? 'current';
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        $baseQuery = $this->visibleProjectsQuery($request, $company);

        $projects = (clone $baseQuery)
            ->with([
                'customer:id,name,customer_number,portal_user_id',
                'assignedEngineer:id,name',
                'assignedSales:id,name',
            ])
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $projectQuery) use ($search): void {
                        $projectQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('project_number', 'like', "%{$search}%")
                            ->orWhere('site_address', 'like', "%{$search}%")
                            ->orWhereHas(
                                'customer',
                                fn (Builder $customerQuery) => $customerQuery
                                    ->where('name', 'like', "%{$search}%")
                            );
                    });
                }
            )
            ->when(
                $status,
                fn (Builder $query) => $query->where('status', $status)
            )
            ->when(
                $system,
                fn (Builder $query) => $query->where('proposed_system', $system)
            )
            ->when(
                $archive === 'current',
                fn (Builder $query) => $query->whereNull('archived_at')
            )
            ->when(
                $archive === 'archived',
                fn (Builder $query) => $query->whereNotNull('archived_at')
            )
            ->orderBy($sort, $direction)
            ->paginate(12)
            ->withQueryString();

        $recentProject = (clone $baseQuery)
            ->whereNull('archived_at')
            ->with([
                'customer:id,name,customer_number,portal_user_id',
                'assignedEngineer:id,name',
                'assignedSales:id,name',
                'sldRevisions' => fn ($query) => $query
                    ->where('status', '!=', 'superseded')
                    ->latest('revision')
                    ->limit(1),
            ])
            ->latest('updated_at')
            ->first();

        $stats = [
            'total' => (clone $baseQuery)->whereNull('archived_at')->count(),
            'draft' => (clone $baseQuery)
                ->whereNull('archived_at')
                ->where('status', 'draft')
                ->count(),
            'active' => (clone $baseQuery)
                ->whereNull('archived_at')
                ->whereIn('status', ['survey', 'engineering', 'quotation', 'approved'])
                ->count(),
            'completed' => (clone $baseQuery)
                ->whereNull('archived_at')
                ->where('status', 'completed')
                ->count(),
        ];

        return view('projects.index', [
            'company' => $company,
            'projects' => $projects,
            'recentProject' => $recentProject,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'system' => $system,
                'archive' => $archive,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'canCreateProject' => $request->user()->hasAnyCompanyPermission(
                $company,
                ['project.create', 'project.create_own']
            ),
            'canViewSld' => $request->user()->hasAnyCompanyPermission(
                $company,
                ['calculation.view', 'calculation.run', 'calculation.revise', 'calculation.verify', 'calculation.view_own']
            ),
        ]);
    }

    /**
     * Show the project creation form.
     */
    public function create(
        Request $request,
        Company $company
    ): View|RedirectResponse {
        $user = $request->user();
        $canCreateCompanyProject = $user->hasCompanyPermission(
            $company,
            'project.create'
        );

        $ownedCustomer = null;

        if (! $canCreateCompanyProject) {
            $ownedCustomer = $company->customers()
                ->active()
                ->where('portal_user_id', $user->getKey())
                ->first();

            if (! $ownedCustomer) {
                return redirect()
                    ->route('companies.projects.index', $company)
                    ->with(
                        'error',
                        'Your customer portal account is not linked to an active customer profile.'
                    );
            }
        }

        return view('projects.create', [
            'company' => $company,
            'customers' => $canCreateCompanyProject
                ? $company->customers()->active()->orderBy('name')->get()
                : collect([$ownedCustomer]),
            'ownedCustomer' => $ownedCustomer,
            'engineers' => $canCreateCompanyProject
                ? $this->companyRoleUsers($company, 'engineer')
                : collect(),
            'salesUsers' => $canCreateCompanyProject
                ? $this->companyRoleUsers($company, 'sales')
                : collect(),
            'canCreateCompanyProject' => $canCreateCompanyProject,
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(
        StoreProjectRequest $request,
        Company $company
    ): RedirectResponse {
        $user = $request->user();
        $canCreateCompanyProject = $user->hasCompanyPermission(
            $company,
            'project.create'
        );

        $customer = $canCreateCompanyProject
            ? $company->customers()
                ->active()
                ->findOrFail($request->integer('customer_id'))
            : $company->customers()
                ->active()
                ->where('portal_user_id', $user->getKey())
                ->first();

        if (! $customer) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Your customer portal account is not linked to an active customer profile.'
                );
        }

        $project = DB::transaction(function () use (
            $request,
            $company,
            $customer,
            $canCreateCompanyProject
        ): Project {
            $data = $request->validated();
            unset($data['customer_id']);

            $data['company_id'] = $company->getKey();
            $data['customer_id'] = $customer->getKey();
            $data['status'] = 'draft';
            $data['created_by'] = $request->user()->getKey();
            $data['updated_by'] = $request->user()->getKey();
            $data['status_updated_by'] = $request->user()->getKey();
            $data['status_changed_at'] = now();

            if (! $canCreateCompanyProject) {
                $data['assigned_engineer_id'] = null;
                $data['assigned_sales_id'] = null;
            }

            $project = Project::query()->create($data);

            $project->update([
                'project_number' => sprintf(
                    '%s-%s-%05d',
                    strtoupper($company->code),
                    now()->format('Y'),
                    $project->getKey()
                ),
            ]);

            $project->statusHistories()->create([
                'from_status' => null,
                'to_status' => 'draft',
                'note' => 'Project created.',
                'changed_by' => $request->user()->getKey(),
                'changed_at' => now(),
            ]);

            return $project;
        });

        return redirect()
            ->route('companies.projects.show', [$company, $project])
            ->with('success', 'Project created successfully.');
    }

    /**
     * Display a project.
     */
    public function show(
        Request $request,
        Company $company,
        Project $project,
        ClientReleaseGate $releaseGateService
    ): View {
        $this->ensureVisibleProject($request, $company, $project);

        $project->load([
            'customer.portalUser:id,name,email,status',
            'assignedEngineer:id,name,email',
            'assignedSales:id,name,email',
            'creator:id,name,email',
            'updater:id,name,email',
            'statusUpdater:id,name,email',
            'statusHistories.changedBy:id,name,email',
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'systemRecommendation.scores',
            'technicalCalculation.activeOverrides.creator:id,name',
            'technicalCalculation.calculator:id,name',
            'technicalCalculation.verifier:id,name',
            'sldRevisions.generator:id,name',
            'sldRevisions.approver:id,name',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
            'activeReleaseApproval.approver:id,name',
            'releaseApprovals.approver:id,name',
        ]);

        $user = $request->user();
        $cockpit = ProjectCockpit::build($project);
        $releaseGate = $releaseGateService->evaluate($project);

        return view('projects.show', [
            'company' => $company,
            'project' => $project,
            'cockpit' => $cockpit,
            'canUpdateProject' => $user->hasCompanyPermission(
                $company,
                'project.update'
            ),
            'canArchiveProject' => $user->hasCompanyPermission(
                $company,
                'project.archive'
            ),
            'canViewLoadAnalysis' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'load_analysis.view',
                    'load_analysis.manage',
                    'load_analysis.view_own',
                    'load_analysis.manage_own',
                ]
            ),
            'canViewLocationSolar' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'location_solar.view',
                    'location_solar.manage',
                    'location_solar.view_own',
                    'location_solar.manage_own',
                ]
            ),
            'canViewRoofSite' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'roof_site.view',
                    'roof_site.manage',
                    'roof_site.view_own',
                    'roof_site.manage_own',
                ]
            ),
            'canViewRecommendation' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'recommendation.view',
                    'recommendation.run',
                    'recommendation.override',
                    'recommendation.view_own',
                ]
            ),
            'canViewTechnicalCalculation' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'calculation.view',
                    'calculation.run',
                    'calculation.revise',
                    'calculation.verify',
                    'calculation.view_own',
                ]
            ),
            'canViewBom' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'calculation.view',
                    'calculation.run',
                    'calculation.revise',
                    'calculation.view_own',
                ]
            ),
            'canViewFinancialAnalysis' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'calculation.view',
                    'price.selling.view',
                    'quotation.view',
                    'calculation.view_own',
                    'quotation.view_own',
                ]
            ),
            'canViewQuotation' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'quotation.view',
                    'quotation.create',
                    'quotation.update',
                    'quotation.send',
                    'quotation.view_own',
                ]
            ),
            'canViewReports' => $user->hasAnyCompanyPermission(
                $company,
                [
                    'project.view',
                    'project.view_own',
                    'calculation.view',
                    'calculation.view_own',
                    'quotation.view',
                    'quotation.view_own',
                ]
            ),
            'releaseGate' => $releaseGate,
            'canApproveClientRelease' => $releaseGateService->userCanManageApproval($user, $company)
                && ! $project->isArchived(),
        ]);
    }

    /**
     * Show the project edit form.
     */
    public function edit(
        Company $company,
        Project $project
    ): View|RedirectResponse {
        if ($project->isArchived()) {
            return redirect()
                ->route('companies.projects.show', [$company, $project])
                ->with('error', 'Archived projects cannot be updated.');
        }

        if (! $project->customer()->where('status', 'active')->exists()) {
            return redirect()
                ->route('companies.projects.show', [$company, $project])
                ->with(
                    'error',
                    'This project cannot be updated because its customer profile is archived.'
                );
        }

        return view('projects.edit', [
            'company' => $company,
            'project' => $project,
            'customers' => $company->customers()->active()->orderBy('name')->get(),
            'engineers' => $this->companyRoleUsers($company, 'engineer'),
            'salesUsers' => $this->companyRoleUsers($company, 'sales'),
            'canCreateCompanyProject' => true,
            'allowedStatuses' => array_values(array_unique([
                $project->status,
                ...$project->allowedNextStatuses(),
            ])),
        ]);
    }

    /**
     * Update a project.
     */
    public function update(
        UpdateProjectRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        if ($project->isArchived()) {
            return back()->with(
                'error',
                'Archived projects cannot be updated.'
            );
        }

        if (! $project->customer()->where('status', 'active')->exists()) {
            return back()->with(
                'error',
                'This project cannot be updated because its customer profile is archived.'
            );
        }

        DB::transaction(function () use ($request, $project): void {
            $data = $request->validated();
            $statusNote = $data['status_note'] ?? null;
            unset($data['status_note']);

            $previousStatus = $project->status;
            $statusChanged = $previousStatus !== $data['status'];

            $data['updated_by'] = $request->user()->getKey();

            if ($statusChanged) {
                $data['status_updated_by'] = $request->user()->getKey();
                $data['status_changed_at'] = now();
            }

            $project->update($data);

            if ($statusChanged) {
                $project->statusHistories()->create([
                    'from_status' => $previousStatus,
                    'to_status' => $project->status,
                    'note' => $statusNote,
                    'changed_by' => $request->user()->getKey(),
                    'changed_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('companies.projects.show', [$company, $project])
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Archive a project.
     */
    public function archive(
        Request $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        if ($project->isArchived()) {
            return back()->with('error', 'Project is already archived.');
        }

        if (! in_array($project->status, ['completed', 'cancelled'], true)) {
            return back()->with(
                'error',
                'Only completed or cancelled projects can be archived.'
            );
        }

        $project->update([
            'archived_at' => now(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('companies.projects.index', $company)
            ->with('success', 'Project archived successfully.');
    }

    /**
     * Build a project query scoped to the current user's visibility.
     */
    private function visibleProjectsQuery(
        Request $request,
        Company $company
    ): Builder {
        $query = Project::query()
            ->where('company_id', $company->getKey());
        $user = $request->user();

        if ($user->hasCompanyPermission($company, 'project.view')) {
            return $query;
        }

        return $query->ownedBy($user);
    }

    /**
     * Ensure the project is visible to the current user.
     */
    private function ensureVisibleProject(
        Request $request,
        Company $company,
        Project $project
    ): void {
        $user = $request->user();

        if ($user->hasCompanyPermission($company, 'project.view')) {
            return;
        }

        if (
            $user->hasCompanyPermission($company, 'project.view_own')
            && $project->isOwnedBy($user)
        ) {
            return;
        }

        abort(404);
    }

    /**
     * Get active users with a specific role in the company.
     *
     * @return Collection<int, User>
     */
    private function companyRoleUsers(
        Company $company,
        string $roleCode
    ): Collection {
        return User::query()
            ->select(['users.id', 'users.name', 'users.email'])
            ->where('users.status', 'active')
            ->whereHas(
                'companyMemberships',
                fn (Builder $query) => $query
                    ->where('company_id', $company->getKey())
                    ->where('status', 'active')
                    ->whereHas(
                        'roles',
                        fn (Builder $roleQuery) => $roleQuery
                            ->where('roles.code', $roleCode)
                            ->where('roles.scope', 'company')
                            ->where('roles.is_active', true)
                    )
            )
            ->orderBy('name')
            ->get();
    }
}
