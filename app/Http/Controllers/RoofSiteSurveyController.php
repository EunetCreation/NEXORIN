<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Controllers\Concerns\ManagesRoofSiteSurvey;
use App\Http\Requests\UpdateRoofSiteSurveyRequest;
use App\Models\Company;
use App\Models\Project;
use App\Models\RoofSiteSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoofSiteSurveyController extends Controller
{
    use AuthorizesProjectResource;
    use ManagesRoofSiteSurvey;

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        if (! in_array($status, ['all', 'not_started', 'incomplete', 'ready', 'verified'], true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number',
                'roofSiteSurvey:id,project_id,quality_status,survey_date,updated_at,verified_at',
            ]);

        if (! $user->hasCompanyPermission($company, 'roof_site.view')) {
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
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'not_started', fn (Builder $builder) => $builder->whereDoesntHave('roofSiteSurvey'))
            ->when($status === 'incomplete', fn (Builder $builder) => $builder->whereHas('roofSiteSurvey', fn (Builder $survey) => $survey->where('quality_status', 'incomplete')))
            ->when($status === 'ready', fn (Builder $builder) => $builder->whereHas('roofSiteSurvey', fn (Builder $survey) => $survey->where('quality_status', 'ready')))
            ->when($status === 'verified', fn (Builder $builder) => $builder->whereHas('roofSiteSurvey', fn (Builder $survey) => $survey->where('quality_status', 'verified')))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasCompanyPermission($company, 'roof_site.view')) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'started' => (clone $statsQuery)->whereHas('roofSiteSurvey')->count(),
            'ready' => (clone $statsQuery)->whereHas('roofSiteSurvey', fn (Builder $survey) => $survey->whereIn('quality_status', ['ready', 'verified']))->count(),
            'verified' => (clone $statsQuery)->whereHas('roofSiteSurvey', fn (Builder $survey) => $survey->where('quality_status', 'verified'))->count(),
        ];

        return view('roof_site.index', [
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
            ['roof_site.view', 'roof_site.manage'],
            ['roof_site.view_own', 'roof_site.manage_own']
        );

        $canManage = $this->canManageProjectResource(
            $request,
            $company,
            $project,
            ['roof_site.manage'],
            ['roof_site.manage_own']
        ) && ! $project->isArchived();

        $canVerify = $request->user()->hasCompanyPermission($company, 'roof_site.verify')
            && ! $project->isArchived();

        $canAssignSurveyor = $request->user()->hasCompanyPermission($company, 'roof_site.manage');
        $survey = $project->roofSiteSurvey()->first();

        if (! $survey && $canManage) {
            $survey = $this->getOrCreateRoofSiteSurvey($request, $company, $project);
        }

        $survey ??= new RoofSiteSurvey([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'access_condition' => 'unknown',
            'mounting_preference' => 'undecided',
            'structural_review_status' => 'not_assessed',
            'quality_status' => 'incomplete',
        ]);

        if ($survey->exists) {
            $survey->load([
                'surfaces',
                'photos.uploader:id,name',
                'histories.changedBy:id,name',
                'surveyor:id,name',
                'verifier:id,name',
            ]);
        } else {
            $survey->setRelation('surfaces', collect());
            $survey->setRelation('photos', collect());
            $survey->setRelation('histories', collect());
        }

        $summary = $survey->calculateSummary();
        $project->loadMissing('customer:id,name,customer_number');

        $companyMembers = collect();

        if ($canAssignSurveyor) {
            $companyMembers = User::query()
                ->where('status', 'active')
                ->whereHas('companyMemberships', fn (Builder $membership) => $membership
                    ->where('company_id', $company->getKey())
                    ->where('status', 'active'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return view('roof_site.show', compact(
            'company',
            'project',
            'survey',
            'summary',
            'canManage',
            'canVerify',
            'canAssignSurveyor',
            'companyMembers'
        ));
    }

    public function update(
        UpdateRoofSiteSurveyRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['roof_site.manage'],
            ['roof_site.manage_own']
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $validated = $request->validated();
        $canAssignSurveyor = $request->user()->hasCompanyPermission($company, 'roof_site.manage');

        if ($canAssignSurveyor && filled($validated['surveyed_by_user_id'] ?? null)) {
            $isCompanyMember = $company->activeMemberships()
                ->where('user_id', $validated['surveyed_by_user_id'])
                ->exists();

            if (! $isCompanyMember) {
                throw ValidationException::withMessages([
                    'surveyed_by_user_id' => 'The selected surveyor is not an active member of this company.',
                ]);
            }
        }

        DB::transaction(function () use (
            $request,
            $company,
            $project,
            $validated,
            $canAssignSurveyor
        ): void {
            $survey = $this->getOrCreateRoofSiteSurvey($request, $company, $project);
            $previousStatus = $survey->quality_status;

            $survey->update([
                'survey_date' => $validated['survey_date'] ?? null,
                'surveyed_by_user_id' => $canAssignSurveyor
                    ? ($validated['surveyed_by_user_id'] ?? null)
                    : $request->user()->getKey(),
                'access_method' => $validated['access_method'] ?? null,
                'access_condition' => $validated['access_condition'],
                'mounting_preference' => $validated['mounting_preference'],
                'structural_review_status' => $validated['structural_review_status'],
                'structural_notes' => $validated['structural_notes'] ?? null,
                'fall_protection_available' => $validated['fall_protection_available'] ?? null,
                'lightning_protection_present' => $validated['lightning_protection_present'] ?? null,
                'electrical_room_distance_m' => $validated['electrical_room_distance_m'] ?? null,
                'estimated_cable_route_m' => $validated['estimated_cable_route_m'] ?? null,
                'weather_condition' => $validated['weather_condition'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'verified_by' => null,
                'verified_at' => null,
                'updated_by' => $request->user()->getKey(),
            ]);

            $this->refreshRoofSiteSurveyStatus(
                $request,
                $company,
                $project,
                $survey,
                'updated',
                $previousStatus,
                'Roof access, safety, mounting, and structural survey details were updated.'
            );
        });

        return redirect()
            ->route('companies.roof-site.show', [$company, $project])
            ->with('success', 'Roof and site survey updated successfully.');
    }

    public function verify(Request $request, Company $company, Project $project): RedirectResponse
    {
        if ((int) $project->company_id !== (int) $company->getKey()) {
            abort(404);
        }

        if (! $request->user()->hasCompanyPermission($company, 'roof_site.verify')) {
            abort(403);
        }

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be verified.');
        }

        $survey = $project->roofSiteSurvey()->with('surfaces')->firstOrFail();
        $summary = $survey->calculateSummary();

        if (! $summary['is_ready_for_verification']) {
            throw ValidationException::withMessages([
                'verification' => 'Complete the survey date, access method, structural assessment, and every roof surface before verification.',
            ]);
        }

        DB::transaction(function () use ($request, $company, $project, $survey): void {
            $previousStatus = $survey->quality_status;

            $survey->update([
                'quality_status' => 'verified',
                'verified_by' => $request->user()->getKey(),
                'verified_at' => now(),
                'updated_by' => $request->user()->getKey(),
            ]);

            $this->recordRoofSiteSurveyHistory(
                $request,
                $company,
                $project,
                $survey,
                'verified',
                $previousStatus,
                'verified',
                'An authorized engineer or company administrator verified the roof and site survey dataset.'
            );
        });

        return redirect()
            ->route('companies.roof-site.show', [$company, $project])
            ->with('success', 'Roof and site survey verified successfully.');
    }
}
