<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Controllers\Concerns\ManagesRoofSiteSurvey;
use App\Http\Requests\StoreRoofSurfaceRequest;
use App\Http\Requests\UpdateRoofSurfaceRequest;
use App\Models\Company;
use App\Models\Project;
use App\Models\RoofSurface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoofSurfaceController extends Controller
{
    use AuthorizesProjectResource;
    use ManagesRoofSiteSurvey;

    public function create(Request $request, Company $company, Project $project): View
    {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            abort(403);
        }

        $survey = $this->getOrCreateRoofSiteSurvey($request, $company, $project);
        $surface = new RoofSurface([
            'roof_condition' => 'unknown',
            'setback_area_m2' => 0,
            'obstruction_area_m2' => 0,
            'walkway_area_m2' => 0,
            'shading_percent' => 0,
            'sort_order' => $survey->surfaces()->count() + 1,
        ]);

        return view('roof_site.surfaces.create', compact(
            'company',
            'project',
            'survey',
            'surface'
        ));
    }

    public function store(
        StoreRoofSurfaceRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $validated = $request->validated();
        $this->validateExcludedAreas($validated);

        DB::transaction(function () use ($request, $company, $project, $validated): void {
            $survey = $this->getOrCreateRoofSiteSurvey($request, $company, $project);
            $previousStatus = $survey->quality_status;

            $survey->surfaces()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                ...$this->normalizedSurfaceData($validated),
            ]);

            $this->refreshRoofSiteSurveyStatus(
                $request,
                $company,
                $project,
                $survey,
                'surface_added',
                $previousStatus,
                'A roof surface was added to the survey.'
            );
        });

        return redirect()
            ->route('companies.roof-site.show', [$company, $project])
            ->with('success', 'Roof surface added successfully.');
    }

    public function edit(
        Request $request,
        Company $company,
        Project $project,
        RoofSurface $roofSurface
    ): View {
        $this->authorizeManage($request, $company, $project);
        $this->ensureSurfaceBelongsToProject($company, $project, $roofSurface);

        if ($project->isArchived()) {
            abort(403);
        }

        $survey = $project->roofSiteSurvey()->firstOrFail();

        return view('roof_site.surfaces.edit', [
            'company' => $company,
            'project' => $project,
            'survey' => $survey,
            'surface' => $roofSurface,
        ]);
    }

    public function update(
        UpdateRoofSurfaceRequest $request,
        Company $company,
        Project $project,
        RoofSurface $roofSurface
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);
        $this->ensureSurfaceBelongsToProject($company, $project, $roofSurface);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $validated = $request->validated();
        $this->validateExcludedAreas($validated);

        DB::transaction(function () use ($request, $company, $project, $roofSurface, $validated): void {
            $survey = $project->roofSiteSurvey()->firstOrFail();
            $previousStatus = $survey->quality_status;

            $roofSurface->update($this->normalizedSurfaceData($validated));

            $this->refreshRoofSiteSurveyStatus(
                $request,
                $company,
                $project,
                $survey,
                'surface_updated',
                $previousStatus,
                'A roof surface was updated.'
            );
        });

        return redirect()
            ->route('companies.roof-site.show', [$company, $project])
            ->with('success', 'Roof surface updated successfully.');
    }

    public function destroy(
        Request $request,
        Company $company,
        Project $project,
        RoofSurface $roofSurface
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);
        $this->ensureSurfaceBelongsToProject($company, $project, $roofSurface);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        DB::transaction(function () use ($request, $company, $project, $roofSurface): void {
            $survey = $project->roofSiteSurvey()->firstOrFail();
            $previousStatus = $survey->quality_status;
            $surfaceName = $roofSurface->name;

            $roofSurface->delete();

            $this->refreshRoofSiteSurveyStatus(
                $request,
                $company,
                $project,
                $survey,
                'surface_deleted',
                $previousStatus,
                "Roof surface '{$surfaceName}' was removed from the survey."
            );
        });

        return redirect()
            ->route('companies.roof-site.show', [$company, $project])
            ->with('success', 'Roof surface removed successfully.');
    }

    private function authorizeManage(Request $request, Company $company, Project $project): void
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['roof_site.manage'],
            ['roof_site.manage_own']
        );
    }

    private function ensureSurfaceBelongsToProject(
        Company $company,
        Project $project,
        RoofSurface $surface
    ): void {
        if (
            (int) $surface->company_id !== (int) $company->getKey()
            || (int) $surface->project_id !== (int) $project->getKey()
        ) {
            abort(404);
        }
    }

    private function validateExcludedAreas(array $validated): void
    {
        $excludedArea = (float) ($validated['setback_area_m2'] ?? 0)
            + (float) ($validated['obstruction_area_m2'] ?? 0)
            + (float) ($validated['walkway_area_m2'] ?? 0);

        if ($excludedArea > (float) $validated['gross_area_m2']) {
            throw ValidationException::withMessages([
                'gross_area_m2' => 'Setback, obstruction, and walkway areas cannot exceed the gross roof area.',
            ]);
        }
    }

    private function normalizedSurfaceData(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'roof_type' => $validated['roof_type'],
            'roof_material' => $validated['roof_material'],
            'roof_condition' => $validated['roof_condition'],
            'gross_area_m2' => $validated['gross_area_m2'],
            'setback_area_m2' => $validated['setback_area_m2'] ?? 0,
            'obstruction_area_m2' => $validated['obstruction_area_m2'] ?? 0,
            'walkway_area_m2' => $validated['walkway_area_m2'] ?? 0,
            'tilt_deg' => $validated['tilt_deg'],
            'azimuth_deg' => $validated['azimuth_deg'],
            'shading_percent' => $validated['shading_percent'],
            'max_additional_load_kg_m2' => $validated['max_additional_load_kg_m2'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
