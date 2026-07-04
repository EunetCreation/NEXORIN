<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Controllers\Concerns\ManagesRoofSiteSurvey;
use App\Http\Requests\StoreRoofSiteSurveyPhotoRequest;
use App\Models\Company;
use App\Models\Project;
use App\Models\RoofSiteSurveyPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoofSiteSurveyPhotoController extends Controller
{
    use AuthorizesProjectResource;
    use ManagesRoofSiteSurvey;

    public function store(
        StoreRoofSiteSurveyPhotoRequest $request,
        Company $company,
        Project $project
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        $file = $request->file('photo');
        $path = $file->store(
            "roof-site-surveys/{$company->getKey()}/{$project->getKey()}",
            'public'
        );

        try {
            DB::transaction(function () use ($request, $company, $project, $file, $path): void {
                $survey = $this->getOrCreateRoofSiteSurvey($request, $company, $project);

                $survey->photos()->create([
                    'company_id' => $company->getKey(),
                    'project_id' => $project->getKey(),
                    'category' => $request->validated('category'),
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'caption' => $request->validated('caption'),
                    'uploaded_by' => $request->user()->getKey(),
                ]);

                $this->recordRoofSiteSurveyHistory(
                    $request,
                    $company,
                    $project,
                    $survey,
                    'photo_added',
                    $survey->quality_status,
                    $survey->quality_status,
                    'A survey evidence photo was uploaded.'
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return redirect()
            ->route('companies.roof-site.show', [$company, $project])
            ->with('success', 'Survey photo uploaded successfully.');
    }

    public function destroy(
        Request $request,
        Company $company,
        Project $project,
        RoofSiteSurveyPhoto $surveyPhoto
    ): RedirectResponse {
        $this->authorizeManage($request, $company, $project);
        $this->ensurePhotoBelongsToProject($company, $project, $surveyPhoto);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot be updated.');
        }

        DB::transaction(function () use ($request, $company, $project, $surveyPhoto): void {
            $survey = $project->roofSiteSurvey()->firstOrFail();
            $path = $surveyPhoto->path;

            $surveyPhoto->delete();
            Storage::disk('public')->delete($path);

            $this->recordRoofSiteSurveyHistory(
                $request,
                $company,
                $project,
                $survey,
                'photo_deleted',
                $survey->quality_status,
                $survey->quality_status,
                'A survey evidence photo was removed.'
            );
        });

        return redirect()
            ->route('companies.roof-site.show', [$company, $project])
            ->with('success', 'Survey photo removed successfully.');
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

    private function ensurePhotoBelongsToProject(
        Company $company,
        Project $project,
        RoofSiteSurveyPhoto $photo
    ): void {
        if (
            (int) $photo->company_id !== (int) $company->getKey()
            || (int) $photo->project_id !== (int) $project->getKey()
        ) {
            abort(404);
        }
    }
}
