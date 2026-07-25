<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Http\Requests\GenerateQuotationRequest;
use App\Models\Company;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Services\Quotation\QuotationEngine;
use App\Services\Quotation\QuotationLifecycleService;
use App\Services\Quotation\QuotationPdfRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly QuotationEngine $quotationEngine,
        private readonly QuotationLifecycleService $quotationLifecycle,
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = ['all', 'not_generated', 'draft', 'internal_review', 'approved', 'sent', 'issued', 'accepted', 'rejected', 'cancelled', 'expired', 'locked'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = $company->projects()
            ->current()
            ->with([
                'customer:id,name,customer_number,email,phone',
                'billOfMaterial:id,project_id,pricing_status,grand_price_idr,priced_at',
                'financialAnalysis:id,project_id,status,project_capex_idr,first_year_saving_idr,simple_payback_years,calculated_at',
                'quotation:id,project_id,quote_number,revision_number,version_label,status,commercial_summary,valid_until,prepared_by,approved_by,approved_at,issued_at,snapshot_hash,source_fingerprint,client_proposal_pdf_generated_at,client_proposal_pdf_snapshot_hash,created_at,updated_at',
            ]);

        if (! $user->hasAnyCompanyPermission($company, ['project.view', 'quotation.view'])) {
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
            ->when($status === 'not_generated', fn (Builder $builder) => $builder->whereDoesntHave('quotation'))
            ->when(
                $status !== 'all' && $status !== 'not_generated',
                fn (Builder $builder) => $builder->whereHas(
                    'quotation',
                    fn (Builder $quotation) => $quotation->where('status', $status)
                )
            )
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statsQuery = $company->projects()->current();

        if (! $user->hasAnyCompanyPermission($company, ['project.view', 'quotation.view'])) {
            $statsQuery->ownedBy($user);
        }

        $stats = [
            'projects' => (clone $statsQuery)->count(),
            'ready' => (clone $statsQuery)
                ->whereHas('billOfMaterial', fn (Builder $bom) => $bom
                    ->where('pricing_status', 'priced')
                    ->where('grand_price_idr', '>', 0))
                ->whereHas('financialAnalysis', fn (Builder $analysis) => $analysis
                    ->where('status', 'calculated'))
                ->count(),
            'draft' => (clone $statsQuery)->whereHas('quotation', fn (Builder $quotation) => $quotation->where('status', 'draft'))->count(),
            'issued' => (clone $statsQuery)->whereHas('quotation', fn (Builder $quotation) => $quotation->whereIn('status', ['sent', 'issued']))->count(),
        ];

        return view('quotations.index', [
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
            ['quotation.view', 'quotation.create', 'quotation.update', 'quotation.send'],
            ['quotation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number,email,phone,address,city,province',
            'technicalCalculation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
            'quotation.versions',
            'quotation.preparer:id,name',
            'quotation.approver:id,name',
            'quotation.issuer:id,name',
            'quotation.clientProposalPdfGenerator:id,name',
            'quotation.accepter:id,name',
            'quotation.rejecter:id,name',
            'quotation.canceller:id,name',
            'quotation.events.actor:id,name',
        ]);

        $quotation = $project->quotation;
        $bom = $project->billOfMaterial;
        $analysis = $project->financialAnalysis;
        $defaults = $this->quotationEngine->defaultParameters();
        $terms = $quotation?->terms ?? [];
        $summary = $quotation?->summary ?? [];

        $parameters = array_merge($defaults, [
            'quotation_title' => $quotation?->title ?: ($summary['title'] ?? $defaults['quotation_title']),
            'validity_days' => $summary['validity_days'] ?? $defaults['validity_days'],
            'include_item_detail' => true,
            'payment_terms' => $terms['payment_terms'] ?? $defaults['payment_terms'],
            'delivery_terms' => $terms['delivery_terms'] ?? $defaults['delivery_terms'],
            'warranty_terms' => $terms['warranty_terms'] ?? $defaults['warranty_terms'],
            'exclusions' => $terms['exclusions'] ?? $defaults['exclusions'],
            'quotation_note' => $terms['quotation_note'] ?? $quotation?->notes,
        ]);

        $canGenerate = $request->user()->hasAnyCompanyPermission($company, ['quotation.create', 'quotation.update'])
            && ! $project->isArchived();
        $canApprove = $request->user()->hasCompanyPermission($company, 'quotation.send')
            && $quotation
            && in_array($quotation->status, ['draft', 'internal_review'], true)
            && ! $project->isArchived();

        $canIssue = $request->user()->hasCompanyPermission($company, 'quotation.send')
            && $quotation
            && $quotation->status === 'approved'
            && ! $project->isArchived();

        $canAccept = $request->user()->hasCompanyPermission($company, 'quotation.send')
            && $quotation
            && $quotation->canBeAcceptedByClient()
            && ! $project->isArchived();

        $canReject = $request->user()->hasCompanyPermission($company, 'quotation.send')
            && $quotation
            && $quotation->canBeRejectedByClient()
            && ! $project->isArchived();

        $canCancel = $request->user()->hasCompanyPermission($company, 'quotation.send')
            && $quotation
            && $quotation->canBeCancelled()
            && ! $project->isArchived();

        return view('quotations.show', compact(
            'company',
            'project',
            'quotation',
            'bom',
            'analysis',
            'parameters',
            'canGenerate',
            'canApprove',
            'canIssue',
            'canAccept',
            'canReject',
            'canCancel'
        ));
    }

    public function generate(GenerateQuotationRequest $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.create', 'quotation.update'],
            []
        );

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot generate quotations.');
        }

        $project->load([
            'customer',
            'technicalCalculation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation',
        ]);

        $bom = $project->billOfMaterial;
        $analysis = $project->financialAnalysis;

        if (! $bom || $bom->pricing_status !== 'priced' || (float) $bom->grand_price_idr <= 0) {
            throw ValidationException::withMessages([
                'bom' => 'Calculate BOQ pricing before generating a quotation.',
            ]);
        }

        if (! $analysis || $analysis->status !== 'calculated') {
            throw ValidationException::withMessages([
                'financial_analysis' => 'Calculate financial analysis before generating a quotation.',
            ]);
        }

        $payload = $this->quotationEngine->generate(
            $project,
            $bom,
            $analysis,
            $request->validated()
        );

        DB::transaction(function () use ($request, $company, $project, $bom, $analysis, $payload): void {
            $quotation = $project->quotation()->firstOrNew();
            $revision = $quotation->exists ? ((int) $quotation->revision_number + 1) : 0;
            $quoteNumber = $quotation->quote_number ?: $this->nextQuoteNumber($company);
            $summary = $payload['summary'];

            $quotation->fill([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'financial_analysis_id' => $analysis->getKey(),
                'bill_of_material_id' => $bom->getKey(),
                'quote_number' => $quoteNumber,
                'revision_number' => $revision,
                'version_label' => $this->versionLabel($quoteNumber, $revision),
                'status' => 'draft',
                'currency' => 'IDR',
                'title' => $summary['title'],
                'valid_until' => $summary['valid_until'],
                'customer_snapshot' => $payload['customer_snapshot'],
                'project_snapshot' => $payload['project_snapshot'],
                'technical_summary' => $payload['technical_summary'],
                'financial_summary' => $payload['financial_summary'],
                'commercial_summary' => $payload['commercial_summary'],
                'terms' => $payload['terms'],
                'warnings' => $payload['warnings'],
                'source_snapshot' => $payload['source_snapshot'],
                'snapshot_hash' => $payload['snapshot_hash'],
                'source_fingerprint' => $payload['source_fingerprint'],
                'client_snapshot' => $payload['client_snapshot'],
                'internal_snapshot' => $payload['internal_snapshot'],
                'notes' => $payload['terms']['quotation_note'],
                'prepared_by' => $request->user()->getKey(),
                'approved_by' => null,
                'approved_at' => null,
                'approval_notes' => null,
                'issued_by' => null,
                'issued_at' => null,
                'delivery_method' => null,
                'sent_to' => null,
                'delivery_notes' => null,
                'accepted_by' => null,
                'accepted_at' => null,
                'acceptance_notes' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'expired_at' => null,
                'expiry_reason' => null,
                'client_proposal_pdf_generated_at' => null,
                'client_proposal_pdf_snapshot_hash' => null,
                'client_proposal_pdf_source_fingerprint' => null,
                'client_proposal_pdf_filename' => null,
                'client_proposal_pdf_generated_by' => null,
                'created_by' => $quotation->exists ? $quotation->created_by : $request->user()->getKey(),
                'updated_by' => $request->user()->getKey(),
            ]);

            $quotation->save();
            $quotation->items()->delete();

            foreach ($payload['items'] as $index => $item) {
                $quotation->items()->create([
                    'category' => $item['category'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price_idr' => $item['unit_price_idr'],
                    'line_price_idr' => $item['line_price_idr'],
                    'source_key' => $item['source_key'],
                    'sort_order' => $index + 1,
                    'metadata' => $item['metadata'],
                ]);
            }

            $this->storeVersion($quotation, $payload, $request->user()->getKey());
            $this->quotationLifecycle->recordGenerated($quotation, $request->user(), [
                'version_label' => $quotation->version_display,
                'snapshot_hash' => $quotation->snapshot_hash,
                'source_fingerprint' => $quotation->source_fingerprint,
                'grand_total_idr' => $quotation->commercial_summary['grand_total_idr'] ?? null,
                'notes' => 'Quotation snapshot generated.',
            ]);
        });

        return redirect()
            ->route('companies.quotations.show', [$company, $project])
            ->with('success', 'Quotation draft has been generated and snapshotted as a new revision.');
    }

    public function approve(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.send'],
            []
        );

        $project->load(['quotation.versions', 'technicalCalculation', 'billOfMaterial.items', 'financialAnalysis']);
        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before approving it.',
            ]);
        }

        if (! in_array($quotation->status, ['draft', 'internal_review'], true)) {
            return back()->with('error', 'Only draft or internal review quotations can be approved.');
        }

        if (! empty($quotation->warnings)) {
            throw ValidationException::withMessages([
                'quotation' => 'Resolve quotation warnings before approval.',
            ]);
        }

        $bom = $project->billOfMaterial;
        $analysis = $project->financialAnalysis;

        if ($bom && $analysis) {
            $currentFingerprint = $this->quotationEngine->sourceFingerprint($project, $bom, $analysis);

            if (! $quotation->isSourceCurrent($currentFingerprint)) {
                throw ValidationException::withMessages([
                    'quotation' => 'Quotation source data changed. Regenerate quotation before approval.',
                ]);
            }
        }

        $quotation->forceFill([
            'status' => 'approved',
            'approved_by' => $request->user()->getKey(),
            'approved_at' => now(),
            'approval_notes' => $this->approvalNotes($request),
            'updated_by' => $request->user()->getKey(),
        ])->save();

        $quotation->versions()
            ->where('revision_number', $quotation->revision_number)
            ->update([
                'status' => 'approved',
                'approved_by' => $request->user()->getKey(),
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        $this->quotationLifecycle->recordEvent(
            $quotation,
            'approved',
            $request->user(),
            'draft',
            'approved',
            $quotation->approval_notes,
            null,
            null,
            [
                'version_label' => $quotation->version_display,
                'snapshot_hash' => $quotation->snapshot_hash,
            ]
        );

        return back()->with('success', 'Quotation revision has been approved for client use.');
    }


    public function pdf(Request $request, Company $company, Project $project, QuotationPdfRenderer $pdfRenderer): Response
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.view', 'quotation.create', 'quotation.update', 'quotation.send'],
            ['quotation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number,email,phone,address,city,province',
            'technicalCalculation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
            'quotation.project.customer',
            'quotation.preparer:id,name',
            'quotation.approver:id,name',
            'quotation.issuer:id,name',
            'quotation.clientProposalPdfGenerator:id,name',
            'quotation.accepter:id,name',
            'quotation.rejecter:id,name',
            'quotation.canceller:id,name',
            'quotation.events.actor:id,name',
        ]);

        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before downloading the PDF.',
            ]);
        }

        if (! $quotation->isApprovedForClient()) {
            throw ValidationException::withMessages([
                'quotation' => 'Approve the quotation snapshot before generating the client proposal PDF.',
            ]);
        }

        if ($quotation->valid_until && $quotation->valid_until->isPast()) {
            throw ValidationException::withMessages([
                'quotation' => 'Expired quotations cannot generate client proposal PDF. Generate a new revision.',
            ]);
        }

        $bom = $project->billOfMaterial;
        $analysis = $project->financialAnalysis;

        if ($bom && $analysis) {
            $currentFingerprint = $this->quotationEngine->sourceFingerprint($project, $bom, $analysis);

            if (! $quotation->isSourceCurrent($currentFingerprint)) {
                throw ValidationException::withMessages([
                    'quotation' => 'Quotation source data changed. Regenerate and approve the quotation before generating the PDF.',
                ]);
            }
        }

        if ($quotation->hasSnapshotControlledClientProposal() === false) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate a snapshot-controlled quotation before downloading the client proposal PDF.',
            ]);
        }

        $filename = $pdfRenderer->clientFilename($quotation);

        $quotation->forceFill([
            'client_proposal_pdf_generated_at' => now(),
            'client_proposal_pdf_snapshot_hash' => $quotation->snapshot_hash,
            'client_proposal_pdf_source_fingerprint' => $quotation->source_fingerprint,
            'client_proposal_pdf_filename' => $filename,
            'client_proposal_pdf_generated_by' => $request->user()->getKey(),
            'updated_by' => $request->user()->getKey(),
        ])->save();

        $this->quotationLifecycle->recordEvent(
            $quotation,
            'client_pdf_generated',
            $request->user(),
            $quotation->status,
            $quotation->status,
            'Snapshot-safe client proposal PDF generated.',
            'download',
            null,
            [
                'filename' => $filename,
                'snapshot_hash' => $quotation->snapshot_hash,
                'source_fingerprint' => $quotation->source_fingerprint,
            ]
        );

        $quotation->refresh()->loadMissing(['items', 'project.customer', 'preparer', 'approver', 'issuer', 'clientProposalPdfGenerator']);

        $pdf = $pdfRenderer->render($quotation);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-NEXORIN-Client-Proposal-Snapshot' => (string) $quotation->snapshot_hash,
        ]);
    }

    public function issue(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.send'],
            []
        );

        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before issuing it.',
            ]);
        }

        $this->quotationLifecycle->expireIfNeeded($quotation, $request->user());
        $quotation->refresh();

        if ($quotation->status !== 'approved') {
            return back()->with('error', 'Only approved quotations can be sent to customer.');
        }

        if ($quotation->shouldBeConsideredExpired()) {
            $this->quotationLifecycle->expireIfNeeded($quotation, $request->user());

            return back()->with('error', 'Expired quotations cannot be sent. Generate a new revision.');
        }

        $project->loadMissing(['billOfMaterial.items', 'financialAnalysis']);
        $currentFingerprint = null;

        if ($project->billOfMaterial && $project->financialAnalysis) {
            $currentFingerprint = $this->quotationEngine->sourceFingerprint($project, $project->billOfMaterial, $project->financialAnalysis);
        }

        if (! $quotation->hasCurrentClientProposalPdf($currentFingerprint)) {
            return back()->with('error', 'Generate the current client proposal PDF before sending the quotation.');
        }

        $delivery = $request->validate([
            'delivery_method' => ['nullable', 'string', 'in:manual,email,whatsapp,download,portal,other'],
            'sent_to' => ['nullable', 'string', 'max:190'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($quotation, $request, $delivery, $project): void {
            $this->quotationLifecycle->markSent($quotation, $request->user(), $delivery);
            $this->transitionProjectStatus(
                $project,
                'quotation',
                $request->user()->getKey(),
                'Quotation issued to customer.'
            );
        });

        return back()->with('success', 'Quotation has been sent.');
    }

    public function accept(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.send'],
            []
        );

        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before recording acceptance.',
            ]);
        }

        $this->quotationLifecycle->expireIfNeeded($quotation, $request->user());
        $quotation->refresh();

        if (! $quotation->canBeAcceptedByClient()) {
            return back()->with('error', 'Only sent and non-expired quotations can be accepted.');
        }

        $payload = $request->validate([
            'acceptance_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($quotation, $request, $payload, $project): void {
            $this->quotationLifecycle->markAccepted($quotation, $request->user(), $payload['acceptance_notes'] ?? null);
            $this->transitionProjectStatus(
                $project,
                'approved',
                $request->user()->getKey(),
                'Customer accepted quotation'.(! empty($payload['acceptance_notes']) ? ': '.$payload['acceptance_notes'] : '.')
            );
        });

        return back()->with('success', 'Client acceptance has been recorded and the project is now approved.');
    }

    public function reject(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.send'],
            []
        );

        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before recording rejection.',
            ]);
        }

        $this->quotationLifecycle->expireIfNeeded($quotation, $request->user());
        $quotation->refresh();

        if (! $quotation->canBeRejectedByClient()) {
            return back()->with('error', 'Only sent and non-expired quotations can be rejected.');
        }

        $payload = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->quotationLifecycle->markRejected($quotation, $request->user(), $payload['rejection_reason']);

        return back()->with('success', 'Client rejection has been recorded.');
    }

    public function cancel(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['quotation.send'],
            []
        );

        $quotation = $project->quotation;

        if (! $quotation) {
            throw ValidationException::withMessages([
                'quotation' => 'Generate quotation before cancellation.',
            ]);
        }

        if (! $quotation->canBeCancelled()) {
            return back()->with('error', 'This quotation cannot be cancelled.');
        }

        $payload = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->quotationLifecycle->markCancelled($quotation, $request->user(), $payload['cancellation_reason'] ?? null);

        return back()->with('success', 'Quotation has been cancelled.');
    }

    private function transitionProjectStatus(Project $project, string $targetStatus, int $actorId, ?string $note = null): void
    {
        $project->refresh();

        if ($project->status === $targetStatus) {
            return;
        }

        if (! $project->canTransitionTo($targetStatus)) {
            throw ValidationException::withMessages([
                'project_status' => 'Project cannot transition from '.$project->status.' to '.$targetStatus.'.',
            ]);
        }

        $previousStatus = (string) $project->status;
        $changedAt = now();

        $project->forceFill([
            'status' => $targetStatus,
            'status_updated_by' => $actorId,
            'status_changed_at' => $changedAt,
            'updated_by' => $actorId,
        ])->save();

        $project->statusHistories()->create([
            'from_status' => $previousStatus,
            'to_status' => $targetStatus,
            'note' => $note,
            'changed_by' => $actorId,
            'changed_at' => $changedAt,
        ]);
    }

    private function approvalNotes(Request $request): ?string
    {
        $notes = substr(trim((string) $request->input('approval_notes', '')), 0, 1000);

        return $notes !== '' ? $notes : null;
    }

    private function storeVersion(Quotation $quotation, array $payload, int $userId): QuotationVersion
    {
        return QuotationVersion::query()->updateOrCreate(
            [
                'quotation_id' => $quotation->getKey(),
                'revision_number' => $quotation->revision_number,
            ],
            [
                'company_id' => $quotation->company_id,
                'project_id' => $quotation->project_id,
                'quote_number' => $quotation->quote_number,
                'version_label' => $quotation->version_label,
                'status' => $quotation->status,
                'currency' => $quotation->currency,
                'title' => $quotation->title,
                'valid_until' => $quotation->valid_until,
                'snapshot_hash' => $payload['snapshot_hash'],
                'source_fingerprint' => $payload['source_fingerprint'],
                'summary' => $payload['summary'],
                'customer_snapshot' => $payload['customer_snapshot'],
                'project_snapshot' => $payload['project_snapshot'],
                'technical_summary' => $payload['technical_summary'],
                'financial_summary' => $payload['financial_summary'],
                'commercial_summary' => $payload['commercial_summary'],
                'terms' => $payload['terms'],
                'warnings' => $payload['warnings'],
                'source_snapshot' => $payload['source_snapshot'],
                'client_snapshot' => $payload['client_snapshot'],
                'internal_snapshot' => $payload['internal_snapshot'],
                'items_snapshot' => $payload['items'],
                'notes' => $quotation->notes,
                'created_by' => $userId,
            ]
        );
    }

    private function versionLabel(string $quoteNumber, int $revision): string
    {
        return sprintf('%s Rev.%d', $quoteNumber, $revision);
    }

    private function nextQuoteNumber(Company $company): string
    {
        $prefix = sprintf('QTN-%s-%s', strtoupper((string) $company->code), now()->format('Ymd'));
        $count = Quotation::query()
            ->where('company_id', $company->getKey())
            ->where('quote_number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $count);
    }
}
