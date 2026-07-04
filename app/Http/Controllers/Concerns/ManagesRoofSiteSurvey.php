<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Models\Project;
use App\Models\RoofSiteSurvey;
use App\Models\RoofSiteSurveyHistory;
use Illuminate\Http\Request;

trait ManagesRoofSiteSurvey
{
    protected function getOrCreateRoofSiteSurvey(
        Request $request,
        Company $company,
        Project $project
    ): RoofSiteSurvey {
        return RoofSiteSurvey::query()->firstOrCreate(
            ['project_id' => $project->getKey()],
            [
                'company_id' => $company->getKey(),
                'access_condition' => 'unknown',
                'mounting_preference' => 'undecided',
                'structural_review_status' => 'not_assessed',
                'quality_status' => 'incomplete',
                'created_by' => $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]
        );
    }

    protected function refreshRoofSiteSurveyStatus(
        Request $request,
        Company $company,
        Project $project,
        RoofSiteSurvey $survey,
        string $eventType,
        string $previousStatus,
        string $summary
    ): void {
        $survey->load('surfaces');
        $newStatus = $survey->derivedQualityStatus();

        $survey->forceFill([
            'quality_status' => $newStatus,
            'verified_by' => null,
            'verified_at' => null,
            'updated_by' => $request->user()->getKey(),
        ])->save();

        $this->recordRoofSiteSurveyHistory(
            $request,
            $company,
            $project,
            $survey,
            $eventType,
            $previousStatus,
            $newStatus,
            $summary
        );
    }

    protected function recordRoofSiteSurveyHistory(
        Request $request,
        Company $company,
        Project $project,
        RoofSiteSurvey $survey,
        string $eventType,
        ?string $previousStatus,
        ?string $newStatus,
        string $summary
    ): RoofSiteSurveyHistory {
        return RoofSiteSurveyHistory::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'roof_site_survey_id' => $survey->getKey(),
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'summary' => $summary,
            'changed_by' => $request->user()->getKey(),
            'changed_at' => now(),
        ]);
    }

    protected function ensureRoofSiteSurveyBelongsToProject(
        Company $company,
        Project $project,
        RoofSiteSurvey $survey
    ): void {
        if (
            (int) $survey->company_id !== (int) $company->getKey()
            || (int) $survey->project_id !== (int) $project->getKey()
        ) {
            abort(404);
        }
    }
}
