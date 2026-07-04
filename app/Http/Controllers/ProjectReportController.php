<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Models\Company;
use App\Models\Project;
use App\Services\Report\ProjectReportPdfRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectReportController extends Controller
{
    use AuthorizesProjectResource;

    public function pdf(
        Request $request,
        Company $company,
        Project $project,
        ProjectReportPdfRenderer $renderer
    ): Response {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            [
                'project.view',
                'calculation.view',
                'price.selling.view',
                'quotation.view',
            ],
            [
                'project.view_own',
                'calculation.view_own',
                'quotation.view_own',
            ]
        );

        $project->load([
            'company',
            'customer',
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'systemRecommendation.scores',
            'technicalCalculation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
        ]);

        $pdf = $renderer->render($project);
        $filename = $renderer->filename($project);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
