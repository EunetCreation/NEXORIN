<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Models\Company;
use App\Models\Project;
use App\Services\Pdf\PdfRenderingException;
use App\Services\Report\PremiumProjectReportPdfRenderer;
use App\Services\Report\ClientReleaseGate;
use App\Services\Report\ProjectReportPdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        $pdf = $renderer->render($project);
        $filename = $renderer->filename($project);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function premiumPreview(
        Request $request,
        Company $company,
        Project $project,
        PremiumProjectReportPdfRenderer $renderer
    ): Response {
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        return response($renderer->html($project, 'web'), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function premiumPdf(
        Request $request,
        Company $company,
        Project $project,
        PremiumProjectReportPdfRenderer $renderer,
        ProjectReportPdfRenderer $fallbackRenderer
    ): Response {
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        try {
            $pdf = $renderer->render($project);
            $filename = $renderer->filename($project);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'X-NEXORIN-Premium-Renderer' => 'playwright',
            ]);
        } catch (PdfRenderingException $exception) {
            Log::error('Premium project report PDF failed.', [
                'project_id' => $project->getKey(),
                'message' => $exception->getMessage(),
            ]);

            if ((bool) config('nexorin_pdf.allow_legacy_fallback', false)) {
                $pdf = $fallbackRenderer->render($project);
                $filename = 'LEGACY-FALLBACK-'.$fallbackRenderer->filename($project);

                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    'Cache-Control' => 'private, max-age=0, must-revalidate',
                    'X-NEXORIN-Premium-Renderer' => 'legacy-fallback',
                ]);
            }

            throw $exception;
        }
    }


    public function clientProposalPreview(
        Request $request,
        Company $company,
        Project $project,
        PremiumProjectReportPdfRenderer $renderer
    ): Response {
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        return response($renderer->html($project, 'web', 'client'), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function clientProposalPdf(
        Request $request,
        Company $company,
        Project $project,
        PremiumProjectReportPdfRenderer $renderer,
        ProjectReportPdfRenderer $fallbackRenderer
    ): Response {
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        return $this->downloadClientProposal(
            $project,
            $renderer,
            $fallbackRenderer,
            'playwright-client-proposal-draft'
        );
    }

    public function clientProposalFinalPdf(
        Request $request,
        Company $company,
        Project $project,
        PremiumProjectReportPdfRenderer $renderer,
        ProjectReportPdfRenderer $fallbackRenderer,
        ClientReleaseGate $gate
    ): Response|RedirectResponse {
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        $releaseGate = $gate->evaluate($project);

        if (! ($releaseGate['ready'] ?? false)) {
            return back()->with('error', 'Final Client Proposal PDF is blocked until the Client Release Gate is Ready for Client Release. Use Draft Proposal PDF or Preview while review is still open.');
        }

        return $this->downloadClientProposal(
            $project,
            $renderer,
            $fallbackRenderer,
            'playwright-client-proposal-final',
            true
        );
    }


    public function approveClientRelease(
        Request $request,
        Company $company,
        Project $project,
        ClientReleaseGate $gate
    ): RedirectResponse {
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        if (! $gate->userCanManageApproval($request->user(), $company)) {
            return back()->with('error', 'Only company admins and engineers may approve client release.');
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $approval = $gate->approve($project, $request->user(), $data['notes'] ?? null);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first() ?: 'Client release approval failed.');
        }

        return back()->with('success', 'Client release approved by '.$approval->approver?->name.'. Generate the Client Proposal PDF again to remove the draft watermark.');
    }

    public function revokeClientRelease(
        Request $request,
        Company $company,
        Project $project,
        ClientReleaseGate $gate
    ): RedirectResponse {
        $this->authorizeReportAccess($request, $company, $project);
        $this->loadReportRelations($project);

        if (! $gate->userCanManageApproval($request->user(), $company)) {
            return back()->with('error', 'Only company admins and engineers may revoke client release.');
        }

        $data = $request->validate([
            'revoke_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $gate->revoke($project, $request->user(), $data['revoke_reason'] ?? null);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first() ?: 'Client release approval could not be revoked.');
        }

        return back()->with('success', 'Client release approval has been revoked.');
    }

    private function downloadClientProposal(
        Project $project,
        PremiumProjectReportPdfRenderer $renderer,
        ProjectReportPdfRenderer $fallbackRenderer,
        string $rendererHeader,
        bool $final = false
    ): Response {
        try {
            $pdf = $renderer->render($project, 'client');
            $filename = $renderer->filename($project, 'client');

            if ($final) {
                $filename = str_replace('-client-proposal.pdf', '-final-client-proposal.pdf', $filename);
            }

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'X-NEXORIN-Premium-Renderer' => $rendererHeader,
            ]);
        } catch (PdfRenderingException $exception) {
            Log::error('Client proposal PDF failed.', [
                'project_id' => $project->getKey(),
                'message' => $exception->getMessage(),
            ]);

            if ((bool) config('nexorin_pdf.allow_legacy_fallback', false)) {
                $pdf = $fallbackRenderer->render($project);
                $filename = 'LEGACY-FALLBACK-'.$fallbackRenderer->filename($project);

                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    'Cache-Control' => 'private, max-age=0, must-revalidate',
                    'X-NEXORIN-Premium-Renderer' => 'legacy-fallback',
                ]);
            }

            throw $exception;
        }
    }

    private function authorizeReportAccess(Request $request, Company $company, Project $project): void
    {
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
    }

    private function loadReportRelations(Project $project): void
    {
        $project->load([
            'company',
            'customer',
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'systemRecommendation.scores',
            'technicalCalculation.activeOverrides.creator',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
            'activeReleaseApproval.approver',
            'releaseApprovals.approver',
        ]);
    }
}
