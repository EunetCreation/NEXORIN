<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesProjectResource;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Services\Pdf\HtmlToPdfRenderer;
use App\Services\Pdf\PdfRenderingException;
use App\Services\Sld\SldDocumentControl;
use App\Services\Sld\SldElectricalRuleChecker;
use App\Services\Sld\SldEngineeringController;
use App\Services\Sld\SldExportPackageBuilder;
use App\Services\Sld\SldGraphBuilder;
use App\Services\Sld\SldGeometryFoundation;
use App\Services\Sld\SldLabelPlacement;
use App\Services\Sld\SldPackageArchive;
use App\Services\Sld\SldSmartRouter;
use App\Services\Sld\SldPdfRenderer;
use App\Services\Sld\SldSvgRenderer;
use App\Services\Sld\SldVisualRegression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View as ViewResponse;
use Symfony\Component\HttpFoundation\Response;

class ProjectSldController extends Controller
{
    use AuthorizesProjectResource;

    public function __construct(
        private readonly SldGraphBuilder $graphBuilder,
        private readonly SldGeometryFoundation $geometryFoundation,
        private readonly SldLabelPlacement $labelPlacement,
        private readonly SldVisualRegression $visualRegression,
        private readonly SldSvgRenderer $svgRenderer,
        private readonly SldElectricalRuleChecker $electricalRuleChecker,
        private readonly SldEngineeringController $engineeringController,
        private readonly SldDocumentControl $documentControl,
        private readonly SldExportPackageBuilder $exportPackageBuilder,
        private readonly SldPackageArchive $packageArchive,
    ) {
    }

    public function show(Request $request, Company $company, Project $project): ViewResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.view', 'calculation.run', 'calculation.revise', 'calculation.verify'],
            ['calculation.view_own']
        );

        $project->load([
            'customer:id,name,customer_number',
            'technicalCalculation',
            'systemRecommendation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation',
            'sldRevisions.generator:id,name',
            'sldRevisions.submitter:id,name',
            'sldRevisions.reviewer:id,name',
            'sldRevisions.approver:id,name',
            'sldRevisions.issuer:id,name',
            'sldRevisions.locker:id,name',
            'sldRevisions.supersededBy:id,revision,issue_status',
            'assignedEngineer:id,name',
            'assignedSales:id,name',
            'company:id,name,code,registration_number,address',
        ]);

        $revision = $project->sldRevisions->first(fn (ProjectSldRevision $sld): bool => $sld->status !== 'superseded');
        if ($revision) {
            $this->ensureDocumentControl($project, $revision, $request);
        }
        $graph = is_array($revision?->graph) ? $revision->graph : null;
        $isStale = $revision
            && (($revision->source_snapshot['fingerprint'] ?? null) !== $this->graphBuilder->sourceFingerprint($project));
        if ($graph !== null) {
            $graph['schema_version'] = SldGraphBuilder::SCHEMA_VERSION;
            $graph['electrical_validation'] = $this->electricalRuleChecker->check($graph);
            $graph['meta']['electrical_validation'] = $graph['electrical_validation']['summary'] ?? [];
            $graph['warnings'] = $this->electricalRuleChecker->mergeRevisionWarnings(
                is_array($revision?->warnings) ? $revision->warnings : [],
                $graph['electrical_validation']
            );
            $graph['engineering_controller'] = $this->engineeringController->inspect($project, $graph);
            $graph['meta']['engineering_controller'] = $graph['engineering_controller']['summary'] ?? [];
            $revision?->setAttribute('warnings', $graph['warnings']);
        }
        $previousRevision = $revision
            ? $project->sldRevisions->sortByDesc('revision')->first(fn (ProjectSldRevision $candidate): bool => $candidate->revision < $revision->revision)
            : null;
        $releaseAssessment = $revision
            ? $this->documentControl->assess($project, $revision, $graph ?? [], (bool) $isStale)
            : [];
        if ($graph !== null) {
            $graph['meta']['release_assessment'] = $releaseAssessment;
        }
        $revisionComparison = $revision ? $this->documentControl->compare($revision, $previousRevision) : [];
        $exportPreview = $revision ? $this->documentControl->exportPreview($project, $revision) : [];
        $paperSet = $graph ? $this->svgRenderer->renderSet($graph) : ['pages' => [], 'summary' => [], 'sheets' => []];
        $svgs = $paperSet['pages'];
        $svg = $svgs[0] ?? null;
        $canGenerate = $this->canManageProjectResource($request, $company, $project, ['calculation.run', 'calculation.revise'], []);
        $canSubmit = $this->canManageProjectResource($request, $company, $project, ['calculation.revise'], []);
        $canApprove = $this->canManageProjectResource($request, $company, $project, ['calculation.verify'], []);
        $canCheck = $canApprove;
        $canIssue = $canApprove;
        $revisionHistory = $project->sldRevisions->sortByDesc('revision')->values();
        $packageHistory = $revisionHistory->flatMap(function (ProjectSldRevision $record): array {
            return collect(data_get($record->export_manifest, 'events', []))
                ->filter(fn (mixed $event): bool => is_array($event) && (string) ($event['format'] ?? '') === 'package.zip')
                ->map(function (array $event) use ($record): array {
                    $event['revision'] = $record->revision;
                    $event['revision_status'] = $record->status;

                    return $event;
                })->values()->all();
        })->sortByDesc('exported_at')->values();
        $auditTrail = collect($revision?->audit_trail ?? [])->sortByDesc('at')->take(30)->values();

        return view('slds.show', compact(
            'company',
            'project',
            'revision',
            'graph',
            'svg',
            'svgs',
            'paperSet',
            'canGenerate',
            'canSubmit',
            'canApprove',
            'canCheck',
            'canIssue',
            'releaseAssessment',
            'revisionComparison',
            'exportPreview',
            'revisionHistory',
            'packageHistory',
            'auditTrail'
        ));
    }


    /**
     * Open the purpose-built SLD drawing editor. The calculation graph remains
     * authoritative; the editor stores only layout and manual drawing layers.
     */
    public function edit(Request $request, Company $company, Project $project): ViewResponse
    {
        $this->authorizeProjectResource(
            $request,
            $company,
            $project,
            ['calculation.view', 'calculation.run', 'calculation.revise', 'calculation.verify'],
            ['calculation.view_own']
        );

        $project->load([
            'technicalCalculation',
            'systemRecommendation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation',
            'sldRevisions.generator:id,name',
            'company',
            'customer',
            'assignedEngineer',
            'assignedSales',
        ]);

        $revision = $project->sldRevisions()
            ->where('status', '!=', 'superseded')
            ->latest('revision')
            ->first();

        if ($revision) {
            $this->ensureDocumentControl($project, $revision, $request);
        }

        $canEdit = $revision !== null
            && $revision->status === 'draft'
            && ! $revision->isReadOnly()
            && $this->canManageProjectResource($request, $company, $project, ['calculation.revise'], []);

        $graph = is_array($revision?->graph) ? $revision->graph : [];
        if ($graph !== []) {
            $graph['schema_version'] = SldGraphBuilder::SCHEMA_VERSION;
            $graph['engineering_controller'] = $this->engineeringController->inspect($project, $graph);
            $graph['meta']['engineering_controller'] = $graph['engineering_controller']['summary'] ?? [];
        }
        $paperSet = $graph !== []
            ? $this->svgRenderer->renderSet($graph)
            : ['pages' => [], 'summary' => [], 'sheets' => []];

        return view('slds.editor', [
            'company' => $company,
            'project' => $project,
            'revision' => $revision,
            'graph' => $graph,
            'a3Svgs' => $paperSet['pages'],
            'paperSummary' => $paperSet['summary'],
            'paperSheets' => $paperSet['sheets'],
            'canEdit' => $canEdit,
        ]);
    }

    /**
     * Persist only the editor layer of a draft. Sourced nodes and the
     * calculation snapshot are intentionally not replaced by browser input.
     */
    public function saveEditor(Request $request, Company $company, Project $project): JsonResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.revise'], []);

        $revision = $this->currentRevision($project);
        if (! $revision) {
            return response()->json(['message' => 'Generate an SLD revision before editing it.'], 422);
        }

        if ($revision->status !== 'draft' || $revision->isReadOnly()) {
            return response()->json(['message' => 'Only an unlocked draft SLD can be edited. Issued and superseded revisions are read-only.'], 422);
        }

        $request->validate([
            'editor' => ['required', 'array'],
            'editor.version' => ['nullable', 'integer', 'min:1', 'max:16'],
            'editor.engine' => ['nullable', 'string', 'max:40'],
            'editor.canvas' => ['nullable', 'array'],
            'editor.canvas.width' => ['nullable', 'integer', 'min:800', 'max:5000'],
            'editor.canvas.height' => ['nullable', 'integer', 'min:500', 'max:5000'],
            'editor.display_mode' => ['nullable', 'in:clean,engineering,inspection'],
            'editor.space' => ['nullable', 'in:model,sheet'],
            'editor.panels' => ['nullable', 'array'],
            'editor.paper' => ['nullable', 'array'],
            'editor.paper.mode' => ['nullable', 'in:as_drawn,auto,single,multi,catalog'],
            'editor.geometry' => ['nullable', 'array'],
            'editor.geometry.version' => ['nullable', 'string', 'max:20'],
            'editor.geometry.contract' => ['nullable', 'string', 'max:80'],
            'editor.geometry.units' => ['nullable', 'string', 'max:30'],
            'editor.geometry.coordinate_precision' => ['nullable', 'integer', 'min:0', 'max:6'],
            'editor.geometry.grid_size' => ['nullable', 'integer', 'min:5', 'max:100'],
            'editor.geometry.minimum_equipment_gap' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'editor.geometry.diagnostics' => ['nullable', 'array'],
            'editor.routing' => ['nullable', 'array'],
            'editor.routing.version' => ['nullable', 'string', 'max:20'],
            'editor.routing.contract' => ['nullable', 'string', 'max:100'],
            'editor.routing.engine' => ['nullable', 'string', 'max:80'],
            'editor.routing.grid_size' => ['nullable', 'integer', 'min:5', 'max:100'],
            'editor.routing.wires' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'editor.routing.orthogonal_wires' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'editor.routing.crossings' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'editor.routing.clearance_conflicts' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'editor.routing.separation_conflicts' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'editor.routing.terminal_direction_violations' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'editor.routing.fallbacks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'editor.routing.bends' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'editor.routing.length' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'editor.label_placement' => ['nullable', 'array'],
            'editor.label_placement.version' => ['nullable', 'string', 'max:20'],
            'editor.label_placement.contract' => ['nullable', 'string', 'max:100'],
            'editor.label_placement.engine' => ['nullable', 'string', 'max:80'],
            'editor.label_placement.clearance' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'editor.label_placement.wire_clearance' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'editor.label_placement.positions' => ['nullable', 'array', 'max:500'],
            'editor.label_placement.summary' => ['nullable', 'array'],
            'editor.visual_regression' => ['nullable', 'array'],
            'editor.visual_regression.version' => ['nullable', 'string', 'max:20'],
            'editor.visual_regression.contract' => ['nullable', 'string', 'max:100'],
            'editor.visual_regression.engine' => ['nullable', 'string', 'max:80'],
            'editor.visual_regression.coordinate_tolerance' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'editor.visual_regression.clipping_tolerance' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'editor.visual_regression.route_tolerance' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'editor.visual_regression.fingerprint' => ['nullable', 'string', 'max:24'],
            'editor.visual_regression.baseline_fingerprint' => ['nullable', 'string', 'max:24'],
            'editor.visual_regression.status' => ['nullable', 'in:pass,warning,error'],
            'editor.visual_regression.summary' => ['nullable', 'array'],
            'editor.layers' => ['nullable', 'array'],
            'editor.tools' => ['nullable', 'array'],
            'editor.tools.circle_mode' => ['nullable', 'in:center-radius,center-diameter,two-point,three-point'],
            'editor.tools.arc_mode' => ['nullable', 'in:three-point,center-start-end,start-center-end,start-end-radius'],
            'editor.excluded_nodes' => ['nullable', 'array', 'max:100'],
            'editor.viewport' => ['nullable', 'array'],
            'editor.positions' => ['nullable', 'array'],
            'editor.routes' => ['nullable', 'array', 'max:300'],
            'editor.manual_nodes' => ['nullable', 'array', 'max:100'],
            'editor.manual_connections' => ['nullable', 'array', 'max:200'],
            'editor.manual_lines' => ['nullable', 'array', 'max:300'],
            'editor.manual_shapes' => ['nullable', 'array', 'max:200'],
            'editor.annotations' => ['nullable', 'array', 'max:100'],
        ]);

        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'systemRecommendation', 'billOfMaterial.items', 'financialAnalysis', 'quotation']);
        $editor = $this->normalizeEditorState((array) $request->input('editor'));
        $graph = is_array($revision->graph) ? $revision->graph : [];
        $graph['editor'] = $editor;
        $graph['schema_version'] = SldGraphBuilder::SCHEMA_VERSION;
        $graph['electrical_validation'] = $this->electricalRuleChecker->check($graph);
        $graph['meta']['electrical_validation'] = $graph['electrical_validation']['summary'] ?? [];
        $warnings = $this->electricalRuleChecker->mergeRevisionWarnings(
            is_array($revision->warnings) ? $revision->warnings : [],
            $graph['electrical_validation']
        );
        $graph['warnings'] = $warnings;
        $graph['engineering_controller'] = $this->engineeringController->inspect($project, $graph);
        $graph['meta']['engineering_controller'] = $graph['engineering_controller']['summary'] ?? [];
        $revision->update([
            'graph' => $graph,
            'warnings' => $warnings,
        ]);

        $paperSet = $this->svgRenderer->renderSet($graph);

        return response()->json([
            'saved' => true,
            'revision' => $revision->revision,
            'saved_at' => now()->toISOString(),
            'paper_svg' => $paperSet['pages'][0] ?? '',
            'paper_svgs' => $paperSet['pages'],
            'paper_summary' => $paperSet['summary'],
            'paper_sheets' => $paperSet['sheets'],
            'electrical_validation' => $graph['electrical_validation'],
            'engineering_controller' => $graph['engineering_controller'],
            'message' => ($graph['engineering_controller']['blocking'] ?? false)
                ? 'Drawing saved. Engineering source synchronization is blocked; open Controller and resolve the source-chain errors before review or controlled export.'
                : (($graph['electrical_validation']['blocking'] ?? false)
                    ? 'Drawing saved with blocking electrical-rule errors. Resolve them before review or controlled export.'
                    : 'Drawing layout, controlled engineering source, electrical-rule result, and intelligent paper-space sheets saved to the current draft revision.'),
        ]);
    }

    public function generate(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.run', 'calculation.revise'], []);

        if ($project->isArchived()) {
            return back()->with('error', 'Archived projects cannot generate a new SLD revision.');
        }

        $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $project->load([
            'company',
            'customer',
            'assignedEngineer',
            'assignedSales',
            'technicalCalculation',
            'systemRecommendation',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation',
        ]);
        $baseGraph = $this->graphBuilder->build($project);

        $revision = DB::transaction(function () use ($request, $company, $project, $baseGraph): ProjectSldRevision {
            Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $current = $project->sldRevisions()
                ->where('status', '!=', 'superseded')
                ->latest('revision')
                ->lockForUpdate()
                ->first();
            $nextRevision = ((int) $project->sldRevisions()->max('revision')) + 1;
            $documentMetadata = $this->documentControl->build(
                $project,
                $nextRevision,
                $request->user(),
                [],
                'draft'
            );
            $graph = $this->documentControl->stampGraph($baseGraph, $documentMetadata);
            $created = $project->sldRevisions()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'revision' => $nextRevision,
                'status' => 'draft',
                'issue_status' => 'draft',
                'system_type' => $graph['meta']['system_type'] ?? null,
                'drawing_number_prefix' => data_get($documentMetadata, 'drawing.number_prefix'),
                'drawing_title' => data_get($documentMetadata, 'drawing.title'),
                'client_name' => data_get($documentMetadata, 'project.client'),
                'site_location' => data_get($documentMetadata, 'project.site_location'),
                'document_metadata' => $documentMetadata,
                'issue_history' => [[
                    'event_id' => (string) \Illuminate\Support\Str::uuid(),
                    'from' => null,
                    'to' => 'draft',
                    'code' => 'DRAFT',
                    'label' => 'Draft',
                    'note' => 'Production drawing record created from the current engineering source.',
                    'user_id' => $request->user()->getKey(),
                    'user_name' => $request->user()->name,
                    'at' => now()->toISOString(),
                ]],
                'graph' => $graph,
                'source_snapshot' => $graph['source_snapshot'] ?? [],
                'warnings' => $graph['warnings'] ?? [],
                'notes' => $request->input('notes'),
                'generated_by' => $request->user()->getKey(),
                'generated_at' => now(),
            ]);
            $created->forceFill([
                'audit_trail' => $this->documentControl->appendAudit(
                    $created,
                    'revision_created',
                    $request->user(),
                    [['field' => 'revision', 'before' => $current?->revision, 'after' => $nextRevision]],
                    ['previous_revision_id' => $current?->getKey()]
                ),
            ])->save();

            if ($current) {
                $current->forceFill([
                    'status' => 'superseded',
                    'superseded_at' => now(),
                    'superseded_by_revision_id' => $created->getKey(),
                    'audit_trail' => $this->documentControl->appendAudit(
                        $current,
                        'revision_superseded',
                        $request->user(),
                        [['field' => 'status', 'before' => $current->status, 'after' => 'superseded']],
                        ['superseded_by_revision_id' => $created->getKey(), 'superseded_by_revision' => $nextRevision]
                    ),
                ])->save();
            }

            return $created;
        });

        $warningCount = count($revision->warnings ?? []);
        $message = $warningCount > 0
            ? "SLD revision {$revision->revision} generated with {$warningCount} review notice(s). Submit it only after resolving the engineering basis."
            : "SLD revision {$revision->revision} generated from the current engineering calculation.";

        return redirect()
            ->route('companies.projects.sld.show', [$company, $project])
            ->with('success', $message);
    }

    public function updateDocumentControl(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.revise'], []);
        $revision = $this->currentRevision($project);

        if (! $revision) {
            throw ValidationException::withMessages(['sld' => 'Generate an SLD revision before editing production document control.']);
        }
        if ($revision->status !== 'draft' || $revision->isReadOnly()) {
            throw ValidationException::withMessages(['sld' => 'Production document metadata can only be edited on an unlocked draft revision.']);
        }

        $validated = $request->validate([
            'drawing_number_prefix' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-_.]*$/'],
            'drawing_title' => ['required', 'string', 'max:180'],
            'client_name' => ['required', 'string', 'max:180'],
            'site_location' => ['required', 'string', 'max:240'],
        ]);

        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'billOfMaterial.items']);
        $beforeMetadata = is_array($revision->document_metadata) ? $revision->document_metadata : [];
        $metadata = $this->documentControl->merge($project, $revision, $validated, $request->user());
        $graph = $this->documentControl->stampGraph(is_array($revision->graph) ? $revision->graph : [], $metadata);
        $changes = $this->documentControl->metadataChangeSet($beforeMetadata, $metadata, [
            'drawing.number_prefix',
            'drawing.title',
            'project.client',
            'project.site_location',
        ]);

        $revision->update([
            'drawing_number_prefix' => data_get($metadata, 'drawing.number_prefix'),
            'drawing_title' => data_get($metadata, 'drawing.title'),
            'client_name' => data_get($metadata, 'project.client'),
            'site_location' => data_get($metadata, 'project.site_location'),
            'document_metadata' => $metadata,
            'graph' => $graph,
            'audit_trail' => $this->documentControl->appendAudit($revision, 'metadata_updated', $request->user(), $changes),
        ]);

        return back()->with('success', 'Production drawing metadata and six-sheet drawing register updated.');
    }

    public function check(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.verify'], []);
        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'billOfMaterial.items']);
        $revision = $this->currentRevision($project);

        if (! $revision || $revision->status !== 'needs_review') {
            throw ValidationException::withMessages(['sld' => 'Only an SLD in engineer review can be checked.']);
        }

        $request->validate([
            'review_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'independent_check_confirm' => ['accepted'],
        ]);
        try {
            $this->documentControl->assertIndependentChecker($revision, $request->user(), $company);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['sld' => $exception->getMessage()]);
        }
        $this->validateRevisionForEngineeringRelease($project, $revision, 'checking');
        $this->validateDocumentControlTarget($project, $revision, 'checked');
        $this->applyIssueTransition($project, $revision, 'checked', $request, $request->input('review_notes'));
        $revision->update([
            'status' => 'checked',
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return back()->with('success', "SLD revision {$revision->revision} checked. Independent approval is still required.");
    }

    public function issue(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.verify'], []);
        $validated = $request->validate([
            'issue_status' => ['required', 'in:issued_for_tender,issued_for_construction,as_built'],
            'issue_note' => ['required', 'string', 'min:10', 'max:1000'],
            'issue_confirm' => ['accepted'],
        ]);
        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'billOfMaterial.items']);

        $revisionNumber = DB::transaction(function () use ($request, $project, $validated): int {
            $revision = $project->sldRevisions()
                ->where('status', '!=', 'superseded')
                ->latest('revision')
                ->lockForUpdate()
                ->first();

            if (! $revision || ! $revision->isApproved()) {
                throw ValidationException::withMessages(['sld' => 'Only an approved SLD revision can be formally issued.']);
            }
            if ($revision->isLocked()) {
                throw ValidationException::withMessages([
                    'sld' => 'This issued revision is immutable. Generate, check, and approve a new revision before assigning a later issue status.',
                ]);
            }

            $target = (string) $validated['issue_status'];
            $this->validateRevisionForEngineeringRelease($project, $revision, 'issue');
            $this->validateDocumentControlTarget($project, $revision, $target);
            if (! $this->documentControl->releaseGateAllows($target, is_array($revision->document_metadata) ? $revision->document_metadata : [])) {
                throw ValidationException::withMessages([
                    'issue_status' => $this->documentControl->releaseGateMessage($target),
                ]);
            }

            $this->applyIssueTransition(
                $project,
                $revision,
                $target,
                $request,
                (string) $validated['issue_note']
            );

            $lockedAt = now();
            $revision->update([
                'issued_by' => $request->user()->getKey(),
                'issued_at' => $lockedAt,
                'locked_by' => $request->user()->getKey(),
                'locked_at' => $lockedAt,
                'audit_trail' => $this->documentControl->appendAudit(
                    $revision,
                    'revision_locked',
                    $request->user(),
                    [['field' => 'locked_at', 'before' => null, 'after' => $lockedAt->toISOString()]],
                    ['reason' => 'Revision formally issued and made read-only.', 'issue_status' => $target]
                ),
            ]);

            return (int) $revision->revision;
        });

        return back()->with('success', sprintf(
            'SLD revision R%d issued as %s.',
            $revisionNumber,
            $this->documentControl->issueLabel((string) $validated['issue_status'])
        ));
    }

    public function manifest(Request $request, Company $company, Project $project): JsonResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.view', 'calculation.verify'], ['calculation.view_own']);
        $revision = $this->currentRevision($project);

        if (! $revision) {
            return response()->json(['message' => 'Generate an SLD revision before requesting its export manifest.'], 422);
        }

        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'billOfMaterial.items']);
        $revision->loadMissing('locker');
        $this->ensureDocumentControl($project, $revision, $request);
        $graph = is_array($revision->graph) ? $revision->graph : [];
        $graph['electrical_validation'] = $this->electricalRuleChecker->check($graph);
        $graph['engineering_controller'] = $this->engineeringController->inspect($project, $graph);
        $isStale = (($revision->source_snapshot['fingerprint'] ?? null) !== $this->graphBuilder->sourceFingerprint($project));

        return response()->json([
            'manifest_version' => 'nexorin-sld-register-manifest-v2',
            'revision' => $revision->revision,
            'issue_status' => $revision->issue_status,
            'issue_label' => $revision->issue_status_label,
            'drawing_number_prefix' => $revision->drawing_number_prefix,
            'document_metadata' => $revision->document_metadata,
            'release_assessment' => $this->documentControl->assess($project, $revision, $graph, $isStale),
            'export_preview' => $this->documentControl->exportPreview($project, $revision),
            'issue_history' => $revision->issue_history ?? [],
            'audit_trail' => $this->documentControl->disclosureSafeAuditTrail($revision),
            'export_manifest' => $this->documentControl->disclosureSafeExportHistory($revision),
            'export_failures' => $this->documentControl->disclosureSafeExportFailures($revision),
            'revision_lock' => [
                'locked' => $revision->isLocked(),
                'locked_at' => $revision->locked_at?->toISOString(),
                'locked_by' => $revision->locker?->name,
                'superseded_by_revision_id' => $revision->superseded_by_revision_id,
            ],
        ], 200, [
            'Content-Disposition' => 'attachment; filename="'.$this->documentControl->exportFilename($project, $revision, 'manifest.json').'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function submit(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.revise'], []);
        $revision = $this->currentRevision($project);

        if (! $revision) {
            throw ValidationException::withMessages(['sld' => 'Generate an SLD revision before submitting it for review.']);
        }

        if ($revision->status !== 'draft') {
            throw ValidationException::withMessages(['sld' => 'Only a draft SLD revision can be submitted for review.']);
        }

        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'systemRecommendation', 'billOfMaterial.items', 'financialAnalysis', 'quotation']);
        $revisionGraph = is_array($revision->graph) ? $revision->graph : [];
        $controller = $this->engineeringController->inspect($project, $revisionGraph);
        if (($controller['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Resolve %d APP67K engineering-controller error(s) before submitting this SLD for review.',
                    (int) data_get($controller, 'summary.error', 0)
                ),
            ]);
        }

        $validation = $this->electricalRuleChecker->check($revisionGraph);
        if (($validation['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Resolve %d blocking electrical-rule error(s) before submitting this SLD for engineer review.',
                    (int) data_get($validation, 'summary.error', 0)
                ),
            ]);
        }

        $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'register_confirm' => ['accepted'],
        ]);
        $this->validateDocumentControlTarget($project, $revision, 'internal_review');

        $this->applyIssueTransition($project, $revision, 'internal_review', $request, $request->input('review_notes'));
        $revision->update([
            'status' => 'needs_review',
            'submitted_by' => $request->user()->getKey(),
            'submitted_at' => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return back()->with('success', "SLD revision {$revision->revision} submitted for engineer review.");
    }

    public function approve(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.verify'], []);
        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'systemRecommendation', 'billOfMaterial.items', 'financialAnalysis', 'quotation']);
        $revision = $this->currentRevision($project);

        if (! $revision) {
            throw ValidationException::withMessages(['sld' => 'Generate and submit an SLD before approval.']);
        }

        if ($revision->status !== 'checked') {
            throw ValidationException::withMessages(['sld' => 'A recorded independent engineering check is required before approval.']);
        }

        if (($revision->source_snapshot['fingerprint'] ?? null) !== $this->graphBuilder->sourceFingerprint($project)) {
            throw ValidationException::withMessages(['sld' => 'This SLD is stale because project or calculation data changed. Generate a new revision before approval.']);
        }

        if ($project->technicalCalculation?->result_status !== 'verified') {
            throw ValidationException::withMessages(['sld' => 'The linked technical calculation must be engineer verified before approving the SLD.']);
        }

        $revisionGraph = is_array($revision->graph) ? $revision->graph : [];
        $controller = $this->engineeringController->inspect($project, $revisionGraph);
        if (($controller['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Resolve %d APP67K engineering-controller error(s) before approving this SLD.',
                    (int) data_get($controller, 'summary.error', 0)
                ),
            ]);
        }

        $validation = $this->electricalRuleChecker->check($revisionGraph);
        if (($validation['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Resolve %d blocking electrical-rule error(s) before approving this SLD.',
                    (int) data_get($validation, 'summary.error', 0)
                ),
            ]);
        }

        if ($revision->hasCriticalWarnings()) {
            throw ValidationException::withMessages(['sld' => 'Critical SLD notices must be resolved before approval.']);
        }

        $request->validate([
            'review_notes' => ['required', 'string', 'min:10', 'max:2000'],
            'independent_approval_confirm' => ['accepted'],
        ]);
        try {
            $this->documentControl->assertIndependentApprover($revision, $request->user(), $company);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['sld' => $exception->getMessage()]);
        }
        $this->validateDocumentControlTarget($project, $revision, 'approved');
        $this->applyIssueTransition($project, $revision, 'approved', $request, $request->input('review_notes'));
        $revision->update([
            'status' => 'approved',
            'reviewed_by' => $revision->reviewed_by ?: $request->user()->getKey(),
            'reviewed_at' => $revision->reviewed_at ?: now(),
            'approved_by' => $request->user()->getKey(),
            'approved_at' => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return back()->with('success', "SLD revision {$revision->revision} approved as the current engineering drawing and is awaiting formal issue.");
    }

    public function reject(Request $request, Company $company, Project $project): RedirectResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.verify'], []);
        $revision = $this->currentRevision($project);

        if (! $revision) {
            throw ValidationException::withMessages(['sld' => 'There is no current SLD revision to return for revision.']);
        }

        if (! in_array($revision->status, ['needs_review', 'checked'], true)) {
            throw ValidationException::withMessages(['sld' => 'Only an SLD revision in engineer review or checked state can be returned for revision.']);
        }

        $request->validate(['review_notes' => ['required', 'string', 'max:2000']]);
        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'billOfMaterial.items']);
        $this->applyIssueTransition($project, $revision, 'draft', $request, $request->input('review_notes'));
        $revision->update([
            'status' => 'draft',
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
            'review_notes' => $request->input('review_notes'),
            'approved_by' => null,
            'approved_at' => null,
            'issued_by' => null,
            'issued_at' => null,
        ]);

        return back()->with('success', "SLD revision {$revision->revision} returned for revision.");
    }

    public function syncController(Request $request, Company $company, Project $project): JsonResponse
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.revise'], []);
        $revision = $this->currentRevision($project);

        if (! $revision) {
            return response()->json(['message' => 'Generate an SLD revision before synchronizing engineering sources.'], 422);
        }
        if ($revision->status !== 'draft') {
            return response()->json(['message' => 'Only a draft SLD can accept engineering-controller updates.'], 422);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:accept_source,acknowledge_review'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'systemRecommendation', 'billOfMaterial.items', 'financialAnalysis', 'quotation']);
        $currentGraph = is_array($revision->graph) ? $revision->graph : [];

        if ($validated['action'] === 'accept_source') {
            $freshGraph = $this->graphBuilder->build($project);
            $graph = $this->engineeringController->reconcile($currentGraph, $freshGraph, $request->user()?->getKey());
            $message = 'Current Technical Calculation, cable schedule, protection schedule, and BOM linkage were applied while preserving the draft layout and manual drawing layer.';
        } else {
            if (! filled($validated['reason'] ?? null)) {
                return response()->json(['message' => 'A review reason is required before acknowledging non-blocking engineering items.'], 422);
            }
            $graph = $this->engineeringController->acknowledge($currentGraph, (string) $validated['reason'], $request->user()?->getKey());
            $message = 'Non-blocking engineering review items were acknowledged in the revision audit trail.';
        }

        $graph = $this->documentControl->stampGraph(
            $graph,
            is_array($revision->document_metadata) ? $revision->document_metadata : []
        );
        $graph['schema_version'] = SldGraphBuilder::SCHEMA_VERSION;
        $graph['electrical_validation'] = $this->electricalRuleChecker->check($graph);
        $graph['meta']['electrical_validation'] = $graph['electrical_validation']['summary'] ?? [];
        $graph['engineering_controller'] = $this->engineeringController->inspect($project, $graph);
        $graph['meta']['engineering_controller'] = $graph['engineering_controller']['summary'] ?? [];
        $graph['warnings'] = $this->electricalRuleChecker->mergeRevisionWarnings(
            is_array($revision->warnings) ? $revision->warnings : [],
            $graph['electrical_validation']
        );

        $revision->update([
            'graph' => $graph,
            'source_snapshot' => $graph['source_snapshot'] ?? [],
            'warnings' => $graph['warnings'],
        ]);

        return response()->json([
            'synchronized' => true,
            'action' => $validated['action'],
            'engineering_controller' => $graph['engineering_controller'],
            'electrical_validation' => $graph['electrical_validation'],
            'message' => $message,
            'reload' => $validated['action'] === 'accept_source',
        ]);
    }

    public function svg(Request $request, Company $company, Project $project): Response
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.view', 'calculation.verify'], ['calculation.view_own']);
        $revision = $this->currentRevision($project);

        if (! $revision?->graph) {
            throw ValidationException::withMessages(['sld' => 'Generate an SLD revision before downloading the SVG.']);
        }

        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'systemRecommendation', 'billOfMaterial.items', 'financialAnalysis', 'quotation']);
        $graph = $this->validatedGraphForControlledOutput($project, $revision, 'SVG');
        $paperSet = $this->svgRenderer->renderSet($graph);
        $svg = $this->svgRenderer->renderCombined($graph);
        $manifest = $this->documentControl->manifest(
            $project,
            $revision,
            $graph,
            $paperSet,
            'svg',
            $svg,
            'nexorin-svg',
            $request->user()
        );
        $revision->update([
            'export_manifest' => $this->documentControl->appendExportManifest($revision, $manifest),
            'last_exported_at' => now(),
            'audit_trail' => $this->documentControl->appendAudit(
                $revision,
                'controlled_output_exported',
                $request->user(),
                [],
                ['format' => 'svg', 'event_id' => data_get($manifest, 'event_id'), 'sha256' => data_get($manifest, 'sha256')]
            ),
        ]);
        $filename = $this->documentControl->exportFilename($project, $revision, 'svg');

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-NEXORIN-SLD-Validation' => strtoupper((string) data_get($graph, 'electrical_validation.status', 'unknown')),
            'X-NEXORIN-SLD-Source' => strtoupper((string) data_get($graph, 'engineering_controller.status', 'unknown')),
        ]);
    }

    public function pdf(
        Request $request,
        Company $company,
        Project $project,
        HtmlToPdfRenderer $pdfRenderer,
        SldPdfRenderer $fallbackRenderer
    ): Response
    {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.view', 'calculation.verify'], ['calculation.view_own']);
        $revision = $this->currentRevision($project);

        if (! $revision?->graph) {
            throw ValidationException::withMessages(['sld' => 'Generate an SLD revision before downloading the PDF.']);
        }

        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'systemRecommendation', 'billOfMaterial.items', 'financialAnalysis', 'quotation']);
        $graph = $this->validatedGraphForControlledOutput($project, $revision, 'PDF');
        [$pdf, $rendererHeader, $paperSet] = $this->renderPdfBytes(
            $project,
            $revision,
            $graph,
            $pdfRenderer,
            $fallbackRenderer
        );

        $manifest = $this->documentControl->manifest(
            $project,
            $revision,
            $graph,
            $paperSet,
            'pdf',
            $pdf,
            $rendererHeader,
            $request->user()
        );
        $revision->update([
            'export_manifest' => $this->documentControl->appendExportManifest($revision, $manifest),
            'last_exported_at' => now(),
            'audit_trail' => $this->documentControl->appendAudit(
                $revision,
                'controlled_output_exported',
                $request->user(),
                [],
                ['format' => 'pdf', 'event_id' => data_get($manifest, 'event_id'), 'sha256' => data_get($manifest, 'sha256')]
            ),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documentControl->exportFilename($project, $revision, 'pdf').'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-NEXORIN-SLD-Renderer' => $rendererHeader,
            'X-NEXORIN-SLD-Validation' => strtoupper((string) data_get($graph, 'electrical_validation.status', 'unknown')),
            'X-NEXORIN-SLD-Source' => strtoupper((string) data_get($graph, 'engineering_controller.status', 'unknown')),
        ]);
    }

    public function package(
        Request $request,
        Company $company,
        Project $project,
        HtmlToPdfRenderer $pdfRenderer,
        SldPdfRenderer $fallbackRenderer
    ): Response {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.view', 'calculation.verify'], ['calculation.view_own']);
        $revision = $this->currentRevision($project);

        if (! $revision?->graph) {
            throw ValidationException::withMessages(['sld' => 'Generate an SLD revision before downloading a controlled export package.']);
        }

        $project->load(['company', 'customer', 'assignedEngineer', 'assignedSales', 'technicalCalculation', 'systemRecommendation', 'billOfMaterial.items', 'financialAnalysis', 'quotation']);

        try {
            $graph = $this->validatedGraphForControlledOutput($project, $revision, 'package');
            $paperSet = $this->svgRenderer->renderSet($graph);
            $svg = $this->svgRenderer->renderCombined($graph);
            [$pdf, $pdfRendererName] = $this->renderPdfBytes($project, $revision, $graph, $pdfRenderer, $fallbackRenderer, $paperSet);

            $pdfManifest = $this->documentControl->manifest($project, $revision, $graph, $paperSet, 'pdf', $pdf, $pdfRendererName, $request->user());
            $svgManifest = $this->documentControl->manifest($project, $revision, $graph, $paperSet, 'svg', $svg, 'nexorin-svg', $request->user());
            $register = is_array(data_get($revision->document_metadata, 'drawing.register'))
                ? data_get($revision->document_metadata, 'drawing.register')
                : [];
            $registerCsv = $this->exportPackageBuilder->registerCsv($register);
            $packageIndex = [
                'package_version' => 'nexorin-sld-controlled-package-v1',
                'package_id' => data_get($pdfManifest, 'package_id'),
                'revision' => $revision->revision,
                'issue_status' => $revision->issue_status,
                'issue_code' => $revision->issue_code,
                'issue_label' => $revision->issue_status_label,
                'disposition' => data_get($pdfManifest, 'disposition'),
                'generated_at' => now()->toISOString(),
                'generated_by' => ['user_id' => $request->user()?->getKey(), 'name' => $request->user()?->name],
                'release_assessment' => data_get($graph, 'meta.release_assessment'),
                'outputs' => [
                    'pdf' => Arr::except($pdfManifest, ['document_control', 'release_assessment']),
                    'svg' => Arr::except($svgManifest, ['document_control', 'release_assessment']),
                ],
                'drawing_register' => $register,
            ];
            $manifestJson = json_encode($packageIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            $pdfName = basename((string) data_get($pdfManifest, 'filename'));
            $svgName = basename((string) data_get($svgManifest, 'filename'));
            $files = [
                $pdfName => $pdf,
                $svgName => $svg,
                'manifest.json' => $manifestJson,
                'drawing-register.csv' => $registerCsv,
                'README.txt' => $this->exportPackageBuilder->readme($packageIndex),
            ];
            $files['checksums.sha256'] = $this->exportPackageBuilder->checksums($files);
            $zip = $this->exportPackageBuilder->build($files);
            $packageManifest = $this->documentControl->manifest(
                $project,
                $revision,
                $graph,
                $paperSet,
                'package.zip',
                $zip,
                'nexorin-controlled-package',
                $request->user()
            );
            $packageManifest['contents'] = array_keys($files);
            $packageManifest['contained_outputs'] = [
                'pdf_sha256' => data_get($pdfManifest, 'sha256'),
                'svg_sha256' => data_get($svgManifest, 'sha256'),
            ];
            $packageManifest['archive'] = $this->packageArchive->store(
                $revision,
                (string) data_get($packageManifest, 'event_id'),
                $this->documentControl->exportFilename($project, $revision, 'package.zip'),
                $zip
            );
            $packageManifest['verification'] = [
                'status' => 'not_verified',
                'verified_at' => null,
                'verified_by' => null,
            ];
            $revision->update([
                'export_manifest' => $this->documentControl->appendExportManifest($revision, $packageManifest),
                'last_exported_at' => now(),
                'audit_trail' => $this->documentControl->appendAudit(
                    $revision,
                    'controlled_package_archived',
                    $request->user(),
                    [],
                    [
                        'event_id' => data_get($packageManifest, 'event_id'),
                        'package_id' => data_get($packageManifest, 'package_id'),
                        'sha256' => data_get($packageManifest, 'sha256'),
                        'archive_path' => data_get($packageManifest, 'archive.path'),
                    ]
                ),
            ]);

            return response($zip, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$this->documentControl->exportFilename($project, $revision, 'package.zip').'"',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'X-NEXORIN-SLD-Package' => (string) data_get($packageManifest, 'package_id'),
                'X-NEXORIN-SLD-Validation' => strtoupper((string) data_get($graph, 'electrical_validation.status', 'unknown')),
            ]);
        } catch (\Throwable $exception) {
            $revision->forceFill([
                'export_failures' => $this->documentControl->appendExportFailure($revision, 'package.zip', $exception, $request->user()),
                'audit_trail' => $this->documentControl->appendAudit(
                    $revision,
                    'controlled_package_export_failed',
                    $request->user(),
                    [],
                    ['message' => $exception->getMessage(), 'exception' => $exception::class]
                ),
            ])->save();

            throw $exception;
        }
    }

    public function downloadArchivedPackage(
        Request $request,
        Company $company,
        Project $project,
        int $revision,
        string $event
    ): Response {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.view', 'calculation.verify'], ['calculation.view_own']);
        $record = $project->sldRevisions()->where('revision', $revision)->firstOrFail();
        $manifest = $this->documentControl->exportEvent($record, $event);
        if (! is_array($manifest) || (string) ($manifest['format'] ?? '') !== 'package.zip') {
            abort(404, 'Controlled package event not found.');
        }

        try {
            $bytes = $this->packageArchive->read((array) ($manifest['archive'] ?? []));
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['sld' => $exception->getMessage()]);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.basename((string) ($manifest['filename'] ?? 'controlled-sld-package.zip')).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-NEXORIN-SLD-Package' => (string) ($manifest['package_id'] ?? ''),
            'X-NEXORIN-SLD-Archive-Event' => $event,
        ]);
    }

    public function verifyArchivedPackage(
        Request $request,
        Company $company,
        Project $project,
        int $revision,
        string $event
    ): RedirectResponse {
        $this->authorizeProjectResource($request, $company, $project, ['calculation.verify'], []);
        $record = $project->sldRevisions()->where('revision', $revision)->firstOrFail();
        $manifest = $this->documentControl->exportEvent($record, $event);
        if (! is_array($manifest) || (string) ($manifest['format'] ?? '') !== 'package.zip') {
            abort(404, 'Controlled package event not found.');
        }

        $verification = $this->packageArchive->verify(
            (array) ($manifest['archive'] ?? []),
            (string) ($manifest['sha256'] ?? '')
        );
        $verification['verified_by'] = [
            'user_id' => $request->user()?->getKey(),
            'name' => $request->user()?->name,
        ];
        $record->forceFill([
            'export_manifest' => $this->documentControl->updateExportEvent($record, $event, ['verification' => $verification]),
            'audit_trail' => $this->documentControl->appendAudit(
                $record,
                'package_integrity_verified',
                $request->user(),
                [],
                [
                    'event_id' => $event,
                    'status' => $verification['status'],
                    'expected_sha256' => $verification['expected_sha256'],
                    'actual_sha256' => $verification['actual_sha256'],
                ]
            ),
        ])->save();

        $message = match ($verification['status']) {
            'verified' => 'Controlled package integrity verified. The archived SHA-256 matches the export manifest.',
            'mismatch' => 'Package verification failed. The archived file checksum does not match the export manifest.',
            default => 'Package verification failed because the archived file is missing.',
        };

        return back()->with($verification['status'] === 'verified' ? 'success' : 'error', $message);
    }

    /** @return array{0:string,1:string,2:array<string,mixed>} */
    private function renderPdfBytes(
        Project $project,
        ProjectSldRevision $revision,
        array $graph,
        HtmlToPdfRenderer $pdfRenderer,
        SldPdfRenderer $fallbackRenderer,
        ?array $paperSet = null
    ): array {
        $paperSet ??= $this->svgRenderer->renderSet($graph);
        $html = View::make('slds.pdf', [
            'project' => $project,
            'revision' => $revision,
            'svgs' => $paperSet['pages'],
            'paperSummary' => $paperSet['summary'],
        ])->render();

        try {
            $pdf = $pdfRenderer->render($html, ['viewportWidth' => 1680, 'viewportHeight' => 900]);
            $rendererName = 'playwright';
        } catch (PdfRenderingException $exception) {
            if (! config('nexorin_pdf.sld_allow_vector_fallback', false)) {
                Log::error('Canonical SLD PDF renderer failed. Vector fallback is disabled to protect SVG/PDF parity.', [
                    'project_id' => $project->getKey(),
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            Log::warning('Canonical SLD PDF renderer unavailable; using explicitly enabled emergency vector fallback.', [
                'project_id' => $project->getKey(),
                'message' => $exception->getMessage(),
            ]);
            $revision->setAttribute('graph', $graph);
            $pdf = $fallbackRenderer->render($project, $revision);
            $rendererName = 'vector-fallback-emergency';
        }

        return [$pdf, $rendererName, $paperSet];
    }

    /** @return array<string, mixed> */
    private function validatedGraphForControlledOutput(Project $project, ProjectSldRevision $revision, string $format): array
    {
        $this->ensureDocumentControl($project, $revision, request());
        $graph = is_array($revision->graph) ? $revision->graph : [];
        $graph = $this->documentControl->stampGraph($graph, is_array($revision->document_metadata) ? $revision->document_metadata : []);
        $validation = $this->electricalRuleChecker->check($graph);
        $graph['schema_version'] = SldGraphBuilder::SCHEMA_VERSION;
        $graph['electrical_validation'] = $validation;
        $graph['meta']['electrical_validation'] = $validation['summary'] ?? [];
        $controller = $this->engineeringController->inspect($project, $graph);
        $graph['engineering_controller'] = $controller;
        $graph['meta']['engineering_controller'] = $controller['summary'] ?? [];
        $isStale = (($revision->source_snapshot['fingerprint'] ?? null) !== $this->graphBuilder->sourceFingerprint($project));
        $graph['meta']['release_assessment'] = $this->documentControl->assess($project, $revision, $graph, $isStale);

        if (($controller['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Controlled %s export is blocked by %d APP67K engineering-controller error(s). Synchronize the drawing source before export.',
                    $format,
                    (int) data_get($controller, 'summary.error', 0)
                ),
            ]);
        }

        if (($validation['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Controlled %s export is blocked by %d electrical-rule error(s). Save the drawing, open Checks, and resolve every error before export.',
                    $format,
                    (int) data_get($validation, 'summary.error', 0)
                ),
            ]);
        }

        return $graph;
    }

    private function ensureDocumentControl(Project $project, ProjectSldRevision $revision, Request $request): void
    {
        if (
            is_array($revision->document_metadata)
            && $revision->document_metadata !== []
            && data_get($revision->document_metadata, 'version') === SldDocumentControl::VERSION
        ) {
            return;
        }

        // Historical issued and superseded records are evidence. Never rewrite
        // their metadata or graph merely because the application contract was
        // upgraded after issue. New work must start as a new draft revision.
        if ($revision->isReadOnly()) {
            return;
        }

        $project->loadMissing(['company', 'customer', 'assignedEngineer', 'assignedSales', 'billOfMaterial.items']);
        $storedIssueStatus = $this->documentControl->normalizeIssueStatus((string) ($revision->issue_status ?: 'draft'));
        $issueStatus = $storedIssueStatus !== 'draft' || $revision->status === 'draft'
            ? $storedIssueStatus
            : match ($revision->status) {
                'needs_review' => 'internal_review',
                'checked' => 'checked',
                'approved' => 'approved',
                default => 'draft',
            };
        $metadata = is_array($revision->document_metadata) && $revision->document_metadata !== []
            ? $this->documentControl->merge($project, $revision, [
                'drawing_number_prefix' => $revision->drawing_number_prefix,
                'drawing_title' => $revision->drawing_title,
                'client_name' => $revision->client_name,
                'site_location' => $revision->site_location,
            ], $request->user())
            : $this->documentControl->build(
                $project,
                $revision->revision,
                $request->user(),
                [
                    'drawing_number_prefix' => $revision->drawing_number_prefix,
                    'drawing_title' => $revision->drawing_title,
                    'client_name' => $revision->client_name,
                    'site_location' => $revision->site_location,
                ],
                $issueStatus
            );
        $metadata['drawing']['issue_status'] = $issueStatus;
        $metadata['drawing']['issue_code'] = $this->documentControl->issueCode($issueStatus);
        $metadata['drawing']['issue_label'] = $this->documentControl->issueLabel($issueStatus);
        if ($revision->reviewer) {
            $metadata['people']['checked_by'] = $revision->reviewer->name;
        }
        if ($revision->approver) {
            $metadata['people']['approved_by'] = $revision->approver->name;
        }
        if ($revision->issuer) {
            $metadata['people']['issued_by'] = $revision->issuer->name;
        }
        $graph = $this->documentControl->stampGraph(is_array($revision->graph) ? $revision->graph : [], $metadata);

        $revision->forceFill([
            'issue_status' => $issueStatus,
            'drawing_number_prefix' => data_get($metadata, 'drawing.number_prefix'),
            'drawing_title' => data_get($metadata, 'drawing.title'),
            'client_name' => data_get($metadata, 'project.client'),
            'site_location' => data_get($metadata, 'project.site_location'),
            'document_metadata' => $metadata,
            'graph' => $graph,
        ])->save();
    }

    private function validateDocumentControlTarget(
        Project $project,
        ProjectSldRevision $revision,
        string $target
    ): void {
        $graph = is_array($revision->graph) ? $revision->graph : [];
        $graph['electrical_validation'] = $this->electricalRuleChecker->check($graph);
        $graph['engineering_controller'] = $this->engineeringController->inspect($project, $graph);
        $isStale = (($revision->source_snapshot['fingerprint'] ?? null) !== $this->graphBuilder->sourceFingerprint($project));
        $assessment = $this->documentControl->assess($project, $revision, $graph, $isStale);
        $targetState = data_get($assessment, 'allowed_targets.'.$target, []);

        if (! (bool) data_get($targetState, 'state_allowed', false)) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'The controlled drawing cannot transition from %s to %s.',
                    $revision->issue_status_label,
                    $this->documentControl->issueLabel($target)
                ),
            ]);
        }

        if ((int) data_get($targetState, 'blocked', 0) > 0) {
            $reasons = collect(data_get($targetState, 'reasons', []))->filter()->implode(' ');
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    '%s is blocked by %d controlled release gate(s). %s',
                    $this->documentControl->issueLabel($target),
                    (int) data_get($targetState, 'blocked', 0),
                    $reasons
                ),
            ]);
        }
    }

    private function validateRevisionForEngineeringRelease(
        Project $project,
        ProjectSldRevision $revision,
        string $action
    ): void {
        if (($revision->source_snapshot['fingerprint'] ?? null) !== $this->graphBuilder->sourceFingerprint($project)) {
            throw ValidationException::withMessages([
                'sld' => "This SLD is stale and cannot continue to {$action}. Generate a new revision from the current project data.",
            ]);
        }

        $graph = is_array($revision->graph) ? $revision->graph : [];
        $controller = $this->engineeringController->inspect($project, $graph);
        if (($controller['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Resolve %d APP67K engineering-controller error(s) before %s this SLD.',
                    (int) data_get($controller, 'summary.error', 0),
                    $action
                ),
            ]);
        }

        $validation = $this->electricalRuleChecker->check($graph);
        if (($validation['blocking'] ?? false) === true) {
            throw ValidationException::withMessages([
                'sld' => sprintf(
                    'Resolve %d blocking electrical-rule error(s) before %s this SLD.',
                    (int) data_get($validation, 'summary.error', 0),
                    $action
                ),
            ]);
        }

        if ($revision->hasCriticalWarnings()) {
            throw ValidationException::withMessages([
                'sld' => 'Critical SLD notices must be resolved before controlled checking, approval, or issue.',
            ]);
        }
    }

    private function applyIssueTransition(
        Project $project,
        ProjectSldRevision $revision,
        string $toStatus,
        Request $request,
        ?string $note = null
    ): void {
        try {
            $history = $this->documentControl->transition($revision, $toStatus, $request->user(), $note);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['sld' => $exception->getMessage()]);
        }

        $metadata = $this->documentControl->merge($project, $revision, [], $request->user());
        $metadata['drawing']['issue_status'] = $toStatus;
        $metadata['drawing']['issue_code'] = $this->documentControl->issueCode($toStatus);
        $metadata['drawing']['issue_label'] = $this->documentControl->issueLabel($toStatus);
        $metadata['release']['not_for_construction'] = ! in_array($toStatus, ['issued_for_construction', 'as_built'], true);

        if ($toStatus === 'checked') {
            $metadata['people']['checked_by'] = $request->user()->name;
        }
        if ($toStatus === 'approved') {
            $metadata['people']['checked_by'] = $metadata['people']['checked_by'] ?: ($revision->reviewer?->name ?: $request->user()->name);
            $metadata['people']['approved_by'] = $request->user()->name;
        }
        if (in_array($toStatus, ['issued_for_tender', 'issued_for_construction', 'as_built'], true)) {
            $metadata['people']['issued_by'] = $request->user()->name;
        }
        if ($toStatus === 'draft') {
            $metadata['people']['checked_by'] = null;
            $metadata['people']['approved_by'] = null;
            $metadata['people']['issued_by'] = null;
        }

        $graph = $this->documentControl->stampGraph(is_array($revision->graph) ? $revision->graph : [], $metadata);
        $revision->update([
            'issue_status' => $toStatus,
            'document_metadata' => $metadata,
            'issue_history' => $history,
            'graph' => $graph,
            'audit_trail' => $this->documentControl->appendAudit(
                $revision,
                'issue_status_changed',
                $request->user(),
                [['field' => 'issue_status', 'before' => $revision->issue_status, 'after' => $toStatus]],
                ['note' => $note, 'issue_code' => $this->documentControl->issueCode($toStatus)]
            ),
        ]);
    }

    private function currentRevision(Project $project): ?ProjectSldRevision
    {
        return $project->sldRevisions()
            ->where('status', '!=', 'superseded')
            ->latest('revision')
            ->first();
    }


    /** @return array<string, mixed> */
    private function normalizeEditorState(array $editor): array
    {
        $canvas = is_array($editor['canvas'] ?? null) ? $editor['canvas'] : [];
        $viewport = is_array($editor['viewport'] ?? null) ? $editor['viewport'] : [];
        $positions = is_array($editor['positions'] ?? null) ? $editor['positions'] : [];
        $routes = is_array($editor['routes'] ?? null) ? $editor['routes'] : [];
        $manualNodes = is_array($editor['manual_nodes'] ?? null) ? $editor['manual_nodes'] : [];
        $manualConnections = is_array($editor['manual_connections'] ?? null) ? $editor['manual_connections'] : [];
        $manualLines = is_array($editor['manual_lines'] ?? null) ? $editor['manual_lines'] : [];
        $manualShapes = is_array($editor['manual_shapes'] ?? null) ? $editor['manual_shapes'] : [];
        $annotations = is_array($editor['annotations'] ?? null) ? $editor['annotations'] : [];
        $panels = is_array($editor['panels'] ?? null) ? $editor['panels'] : [];
        $paper = is_array($editor['paper'] ?? null) ? $editor['paper'] : [];
        $routing = is_array($editor['routing'] ?? null) ? $editor['routing'] : [];
        $layers = is_array($editor['layers'] ?? null) ? $editor['layers'] : [];
        $tools = is_array($editor['tools'] ?? null) ? $editor['tools'] : [];
        $displayMode = in_array($editor['display_mode'] ?? null, ['clean', 'engineering', 'inspection'], true)
            ? (string) $editor['display_mode']
            : 'clean';
        $space = ($editor['space'] ?? null) === 'sheet' ? 'sheet' : 'model';
        $excludedNodes = collect(is_array($editor['excluded_nodes'] ?? null) ? $editor['excluded_nodes'] : [])
            ->filter(fn (mixed $id): bool => is_string($id) && trim($id) !== '')
            ->map(fn (string $id): string => substr($id, 0, 80))
            ->unique()
            ->take(100)
            ->values()
            ->all();

        $cleanPositions = [];
        foreach ($positions as $id => $position) {
            if (! is_string($id) || ! is_array($position)) {
                continue;
            }

            $cleanPositions[substr($id, 0, 80)] = [
                'x' => $this->boundedNumber($position['x'] ?? 0, -10000, 10000),
                'y' => $this->boundedNumber($position['y'] ?? 0, -10000, 10000),
                'w' => $this->boundedNumber($position['w'] ?? 144, 80, 800),
                'h' => $this->boundedNumber($position['h'] ?? 120, 40, 600),
                'locked' => (bool) ($position['locked'] ?? false),
                'rotation' => $this->quarterTurn($position['rotation'] ?? 0),
                'flip_h' => (bool) ($position['flip_h'] ?? false),
                'flip_v' => (bool) ($position['flip_v'] ?? false),
                'group_id' => $this->groupId($position['group_id'] ?? null),
                'show_tag' => (bool) ($position['show_tag'] ?? true),
                'show_rating' => (bool) ($position['show_rating'] ?? true),
                'show_subtitle' => (bool) ($position['show_subtitle'] ?? true),
                'show_ports' => (bool) ($position['show_ports'] ?? true),
                'tag_override' => array_key_exists('tag_override', $position)
                    ? substr(trim((string) ($position['tag_override'] ?? '')), 0, 40)
                    : null,
            ];
        }

        $cleanRoutes = [];
        foreach (array_slice($routes, 0, 300, true) as $id => $points) {
            if (! is_string($id) || ! is_array($points)) {
                continue;
            }

            $cleanPoints = [];
            foreach (array_slice($points, 0, 30) as $point) {
                if (! is_array($point)) {
                    continue;
                }

                $cleanPoints[] = [
                    'x' => $this->boundedNumber($point['x'] ?? 0, -10000, 10000),
                    'y' => $this->boundedNumber($point['y'] ?? 0, -10000, 10000),
                ];
            }

            if ($cleanPoints !== []) {
                $cleanRoutes[substr($id, 0, 180)] = $cleanPoints;
            }
        }

        $cleanNodes = [];
        foreach (array_slice($manualNodes, 0, 100) as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) {
                continue;
            }

            $cleanNodes[] = [
                'id' => substr((string) $node['id'], 0, 80),
                'tag' => substr((string) ($node['tag'] ?? ''), 0, 40),
                'title' => substr((string) ($node['title'] ?? 'Manual component'), 0, 120),
                'subtitle' => substr((string) ($node['subtitle'] ?? 'Manual drawing component'), 0, 180),
                'rating' => substr((string) ($node['rating'] ?? 'Review required'), 0, 180),
                'kind' => substr((string) ($node['kind'] ?? $node['symbol'] ?? 'manual'), 0, 50),
                'symbol' => substr((string) ($node['symbol'] ?? $node['kind'] ?? 'manual'), 0, 50),
                'symbol_standard' => substr((string) ($node['symbol_standard'] ?? ''), 0, 80),
                'symbol_registry_version' => substr((string) ($node['symbol_registry_version'] ?? '67L.1'), 0, 20),
                'state' => 'manual',
                'rated_power' => is_numeric($node['rated_power'] ?? null) ? $this->boundedNumber($node['rated_power'], 0, 1000000) : null,
                'rated_voltage' => is_numeric($node['rated_voltage'] ?? null) ? $this->boundedNumber($node['rated_voltage'], 0, 1000000) : null,
                'rated_current' => is_numeric($node['rated_current'] ?? null) ? $this->boundedNumber($node['rated_current'], 0, 1000000) : null,
                'manufacturer' => substr((string) ($node['manufacturer'] ?? ''), 0, 80),
                'locked' => (bool) ($node['locked'] ?? false),
                'rotation' => $this->quarterTurn($node['rotation'] ?? 0),
                'flip_h' => (bool) ($node['flip_h'] ?? false),
                'flip_v' => (bool) ($node['flip_v'] ?? false),
                'group_id' => $this->groupId($node['group_id'] ?? null),
                'show_tag' => (bool) ($node['show_tag'] ?? true),
                'show_rating' => (bool) ($node['show_rating'] ?? true),
                'show_subtitle' => (bool) ($node['show_subtitle'] ?? true),
                'show_ports' => (bool) ($node['show_ports'] ?? true),
                'ports' => collect(is_array($node['ports'] ?? null) ? $node['ports'] : [])
                    ->filter(fn (mixed $port): bool => is_array($port) && filled($port['id'] ?? null))
                    ->take(24)
                    ->map(fn (array $port): array => [
                        'id' => substr((string) $port['id'], 0, 50),
                        'network' => substr((string) ($port['network'] ?? 'unclassified'), 0, 40),
                        'direction' => in_array($port['direction'] ?? null, ['input', 'output', 'bidirectional'], true) ? (string) $port['direction'] : 'bidirectional',
                        'side' => in_array($port['side'] ?? null, ['left', 'right', 'top', 'bottom'], true) ? (string) $port['side'] : 'right',
                        'label' => substr((string) ($port['label'] ?? $port['id']), 0, 100),
                        'terminal_class' => substr((string) ($port['terminal_class'] ?? 'general'), 0, 60),
                        'required' => (bool) ($port['required'] ?? false),
                        'max_connections' => max(1, min(12, (int) ($port['max_connections'] ?? 1))),
                        'anchor' => [
                            'x' => $this->boundedNumber(data_get($port, 'anchor.x', 0.5), 0, 1),
                            'y' => $this->boundedNumber(data_get($port, 'anchor.y', 0.5), 0, 1),
                        ],
                    ])->values()->all(),
            ];
        }

        $cleanConnections = [];
        foreach (array_slice($manualConnections, 0, 200) as $connection) {
            if (! is_array($connection) || ! filled($connection['from'] ?? null) || ! filled($connection['to'] ?? null)) {
                continue;
            }

            $network = strtolower(str_replace(['-', ' '], '_', (string) ($connection['network'] ?? 'unclassified')));
            $allowedNetworks = [
                'dc_positive', 'dc_negative', 'dc_power',
                'battery_positive', 'battery_negative', 'battery_dc',
                'ac_power', 'neutral', 'protective_earth',
                'communication', 'control', 'measurement', 'functional_earth', 'power', 'unclassified',
            ];
            if (! in_array($network, $allowedNetworks, true)) {
                $network = 'unclassified';
            }

            $cleanConnections[] = [
                'id' => substr((string) ($connection['id'] ?? uniqid('edge_', false)), 0, 80),
                'wire_id' => substr((string) ($connection['wire_id'] ?? ''), 0, 40),
                'from' => substr((string) $connection['from'], 0, 80),
                'to' => substr((string) $connection['to'], 0, 80),
                'label' => substr((string) ($connection['label'] ?? 'Manual wire'), 0, 120),
                'detail' => substr((string) ($connection['detail'] ?? 'Review routing'), 0, 180),
                'cable_tag' => substr((string) ($connection['cable_tag'] ?? ''), 0, 40),
                'cable_type' => substr((string) ($connection['cable_type'] ?? ''), 0, 60),
                'cable_size' => substr((string) ($connection['cable_size'] ?? ''), 0, 30),
                'cores' => filled($connection['cores'] ?? null) ? max(1, min(99, (int) $connection['cores'])) : '',
                'voltage_class' => substr((string) ($connection['voltage_class'] ?? ''), 0, 40),
                'installation_method' => substr((string) ($connection['installation_method'] ?? ''), 0, 60),
                'notes' => substr((string) ($connection['notes'] ?? ''), 0, 240),
                'show_label' => (bool) ($connection['show_label'] ?? true),
                'routing_mode' => ($connection['routing_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto',
                'routing_clearance' => in_array((string) ($connection['routing_clearance'] ?? ''), ['compact', 'standard', 'generous'], true)
                    ? (string) $connection['routing_clearance']
                    : 'standard',
                'router_version' => SldSmartRouter::VERSION,
                'route_contract' => SldSmartRouter::CONTRACT,
                'route_strategy' => in_array((string) ($connection['route_strategy'] ?? ''), [SldSmartRouter::ENGINE, 'perimeter-fallback', 'manual-preserved', 'saved-obstacle-free'], true)
                    ? (string) $connection['route_strategy']
                    : (($connection['routing_mode'] ?? 'auto') === 'manual' ? 'manual-preserved' : SldSmartRouter::ENGINE),
                'route_signature' => preg_match('/^rt-[0-9a-f]{8}$/', (string) ($connection['route_signature'] ?? '')) ? (string) $connection['route_signature'] : '',
                'route_network_class' => SldSmartRouter::networkClass($network),
                'route_preferred_axis' => in_array((string) ($connection['route_preferred_axis'] ?? ''), ['horizontal', 'vertical'], true) ? (string) $connection['route_preferred_axis'] : '',
                'route_preferred_channel' => $this->boundedNumber($connection['route_preferred_channel'] ?? 0, -10000, 10000),
                'route_length' => $this->boundedNumber($connection['route_length'] ?? 0, 0, 10000000),
                'route_bends' => (int) $this->boundedNumber($connection['route_bends'] ?? 0, 0, 100000),
                'route_crossings' => (int) $this->boundedNumber($connection['route_crossings'] ?? 0, 0, 10000),
                'route_clearance_conflicts' => (int) $this->boundedNumber($connection['route_clearance_conflicts'] ?? 0, 0, 10000),
                'route_separation_conflicts' => (int) $this->boundedNumber($connection['route_separation_conflicts'] ?? 0, 0, 10000),
                'route_terminal_direction_ok' => (bool) ($connection['route_terminal_direction_ok'] ?? false),
                'route_orthogonal' => (bool) ($connection['route_orthogonal'] ?? false),
                'from_port' => substr((string) ($connection['from_port'] ?? ''), 0, 50),
                'to_port' => substr((string) ($connection['to_port'] ?? ''), 0, 50),
                'network' => $network,
            ];
        }


        $cleanLines = [];
        foreach (array_slice($manualLines, 0, 300) as $line) {
            if (! is_array($line)) {
                continue;
            }

            $x1 = $this->boundedNumber($line['x1'] ?? 0, -10000, 10000);
            $y1 = $this->boundedNumber($line['y1'] ?? 0, -10000, 10000);
            $x2 = $this->boundedNumber($line['x2'] ?? 0, -10000, 10000);
            $y2 = $this->boundedNumber($line['y2'] ?? 0, -10000, 10000);
            if (abs($x2 - $x1) < 0.01 && abs($y2 - $y1) < 0.01) {
                continue;
            }

            $lineType = strtolower((string) ($line['line_type'] ?? 'continuous'));
            if (! in_array($lineType, ['continuous', 'hidden', 'center'], true)) {
                $lineType = 'continuous';
            }

            $entity = ($line['entity'] ?? null) === 'leader' ? 'leader' : 'line';
            $cleanLines[] = [
                'id' => substr((string) ($line['id'] ?? uniqid($entity.'_', false)), 0, 80),
                'entity' => $entity,
                'label' => substr((string) ($line['label'] ?? ''), 0, 120),
                'layer' => substr((string) ($line['layer'] ?? 'SLD-LINEWORK'), 0, 50),
                'line_type' => $lineType,
                'lineweight' => $this->boundedNumber($line['lineweight'] ?? 1.4, 0.6, 5),
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
                'locked' => (bool) ($line['locked'] ?? false),
                'group_id' => $this->groupId($line['group_id'] ?? null),
            ];
        }

        $cleanShapes = [];
        foreach (array_slice($manualShapes, 0, 200) as $shape) {
            if (! is_array($shape)) {
                continue;
            }

            $shapeType = strtolower((string) ($shape['shape_type'] ?? 'rectangle'));
            if (! in_array($shapeType, ['rectangle', 'circle', 'arc'], true)) {
                continue;
            }
            $width = $this->boundedNumber($shape['w'] ?? 100, 4, 5000);
            $height = $this->boundedNumber($shape['h'] ?? $width, 4, 5000);
            $lineType = strtolower((string) ($shape['line_type'] ?? 'continuous'));
            if (! in_array($lineType, ['continuous', 'hidden', 'center'], true)) {
                $lineType = 'continuous';
            }

            $cleanShapes[] = [
                'id' => substr((string) ($shape['id'] ?? uniqid($shapeType.'_', false)), 0, 80),
                'shape_type' => $shapeType,
                'label' => substr((string) ($shape['label'] ?? ''), 0, 120),
                'layer' => substr((string) ($shape['layer'] ?? 'SLD-LINEWORK'), 0, 50),
                'line_type' => $lineType,
                'lineweight' => $this->boundedNumber($shape['lineweight'] ?? 1.4, 0.6, 5),
                'x' => $this->boundedNumber($shape['x'] ?? 0, -10000, 10000),
                'y' => $this->boundedNumber($shape['y'] ?? 0, -10000, 10000),
                'w' => $width,
                'h' => $height,
                'start_angle' => $this->boundedNumber($shape['start_angle'] ?? 0, -3600, 3600),
                'end_angle' => $this->boundedNumber($shape['end_angle'] ?? 90, -3600, 3600),
                'sweep_flag' => (int) (($shape['sweep_flag'] ?? 1) ? 1 : 0),
                'large_arc' => (int) (($shape['large_arc'] ?? 0) ? 1 : 0),
                'angle_basis' => ($shape['angle_basis'] ?? null) === 'screen' ? 'screen' : 'legacy',
                'locked' => (bool) ($shape['locked'] ?? false),
                'rotation' => $this->quarterTurn($shape['rotation'] ?? 0),
                'flip_h' => (bool) ($shape['flip_h'] ?? false),
                'flip_v' => (bool) ($shape['flip_v'] ?? false),
                'group_id' => $this->groupId($shape['group_id'] ?? null),
            ];
        }

        $cleanAnnotations = [];
        foreach (array_slice($annotations, 0, 100) as $annotation) {
            if (! is_array($annotation) || ! filled($annotation['text'] ?? null)) {
                continue;
            }

            $cleanAnnotations[] = [
                'id' => substr((string) ($annotation['id'] ?? uniqid('note_', false)), 0, 80),
                'text' => substr((string) $annotation['text'], 0, 300),
                'x' => $this->boundedNumber($annotation['x'] ?? 0, -10000, 10000),
                'y' => $this->boundedNumber($annotation['y'] ?? 0, -10000, 10000),
                'w' => $this->boundedNumber($annotation['w'] ?? 280, 120, 800),
                'h' => $this->boundedNumber($annotation['h'] ?? 58, 40, 400),
                'locked' => (bool) ($annotation['locked'] ?? false),
                'rotation' => $this->quarterTurn($annotation['rotation'] ?? 0),
                'flip_h' => (bool) ($annotation['flip_h'] ?? false),
                'flip_v' => (bool) ($annotation['flip_v'] ?? false),
                'group_id' => $this->groupId($annotation['group_id'] ?? null),
            ];
        }

        $layerDefaults = [
            'E-EQUIPMENT', 'E-DC-POWER', 'E-AC-POWER', 'E-NEUTRAL', 'E-PE', 'E-COMM', 'E-CONTROL',
            'E-PROTECTION', 'E-METERING', 'E-ANNOTATION', 'E-BOUNDARY', 'E-EXISTING', 'E-FUTURE',
        ];
        $cleanLayers = [];
        foreach ($layerDefaults as $layerName) {
            $state = is_array($layers[$layerName] ?? null) ? $layers[$layerName] : [];
            $cleanLayers[$layerName] = [
                'visible' => (bool) ($state['visible'] ?? true),
                'locked' => (bool) ($state['locked'] ?? false),
                'print' => (bool) ($state['print'] ?? true),
            ];
        }
        $paperMode = in_array($paper['mode'] ?? null, ['as_drawn', 'auto', 'single', 'multi', 'catalog'], true)
            ? (string) $paper['mode']
            : 'as_drawn';
        if ($paperMode === 'auto') {
            $paperMode = 'as_drawn';
        }
        $osnapDefaults = [
            'endpoint' => true,
            'midpoint' => true,
            'center' => true,
            'geometric_center' => true,
            'quadrant' => true,
            'intersection' => true,
            'nearest' => true,
            'port' => true,
        ];
        $storedOsnapModes = is_array($viewport['osnap_modes'] ?? null) ? $viewport['osnap_modes'] : [];
        $cleanOsnapModes = [];
        foreach ($osnapDefaults as $mode => $default) {
            $cleanOsnapModes[$mode] = (bool) ($storedOsnapModes[$mode] ?? $default);
        }

        $normalized = [
            'version' => max(9, min(16, (int) ($editor['version'] ?? 16))),
            'engine' => substr((string) ($editor['engine'] ?? 'maxgraph-0.24'), 0, 40),
            'display_mode' => $displayMode,
            'space' => $space,
            'panels' => [
                'palette_collapsed' => (bool) ($panels['palette_collapsed'] ?? false),
                'inspector_collapsed' => (bool) ($panels['inspector_collapsed'] ?? false),
                'ribbon_collapsed' => (bool) ($panels['ribbon_collapsed'] ?? false),
            ],
            'paper' => [
                'mode' => $paperMode,
            ],
            'geometry' => $this->geometryFoundation->metadata($cleanPositions, $excludedNodes),
            'routing' => [
                'version' => SldSmartRouter::VERSION,
                'contract' => SldSmartRouter::CONTRACT,
                'engine' => SldSmartRouter::ENGINE,
                'grid_size' => (int) $this->boundedNumber($routing['grid_size'] ?? 10, 5, 100),
                'wires' => (int) $this->boundedNumber($routing['wires'] ?? 0, 0, 1000),
                'orthogonal_wires' => (int) $this->boundedNumber($routing['orthogonal_wires'] ?? 0, 0, 1000),
                'crossings' => (int) $this->boundedNumber($routing['crossings'] ?? 0, 0, 10000),
                'clearance_conflicts' => (int) $this->boundedNumber($routing['clearance_conflicts'] ?? 0, 0, 10000),
                'separation_conflicts' => (int) $this->boundedNumber($routing['separation_conflicts'] ?? 0, 0, 10000),
                'terminal_direction_violations' => (int) $this->boundedNumber($routing['terminal_direction_violations'] ?? 0, 0, 1000),
                'fallbacks' => (int) $this->boundedNumber($routing['fallbacks'] ?? 0, 0, 1000),
                'bends' => (int) $this->boundedNumber($routing['bends'] ?? 0, 0, 100000),
                'length' => $this->boundedNumber($routing['length'] ?? 0, 0, 10000000),
            ],
            'label_placement' => $this->labelPlacement->normalizeMetadata($editor),
            'layers' => $cleanLayers,
            'tools' => [
                'circle_mode' => in_array($tools['circle_mode'] ?? null, ['center-radius', 'center-diameter', 'two-point', 'three-point'], true) ? (string) $tools['circle_mode'] : 'center-radius',
                'arc_mode' => in_array($tools['arc_mode'] ?? null, ['three-point', 'center-start-end', 'start-center-end', 'start-end-radius'], true) ? (string) $tools['arc_mode'] : 'three-point',
            ],
            'excluded_nodes' => $excludedNodes,
            'canvas' => [
                'width' => (int) $this->boundedNumber($canvas['width'] ?? 1600, 800, 5000),
                'height' => (int) $this->boundedNumber($canvas['height'] ?? 920, 500, 5000),
            ],
            'viewport' => [
                'scale' => $this->boundedNumber($viewport['scale'] ?? 1, 0.2, 3),
                'translate_x' => $this->boundedNumber($viewport['translate_x'] ?? 0, -5000, 5000),
                'translate_y' => $this->boundedNumber($viewport['translate_y'] ?? 0, -5000, 5000),
                'grid_size' => (int) $this->boundedNumber($viewport['grid_size'] ?? 20, 5, 100),
                'grid_enabled' => (bool) ($viewport['grid_enabled'] ?? true),
                'snap_enabled' => (bool) ($viewport['snap_enabled'] ?? true),
                'ortho_enabled' => (bool) ($viewport['ortho_enabled'] ?? false),
                'polar_enabled' => (bool) ($viewport['polar_enabled'] ?? true),
                'osnap_enabled' => (bool) ($viewport['osnap_enabled'] ?? true),
                'osnap_modes' => $cleanOsnapModes,
                'otrack_enabled' => (bool) ($viewport['otrack_enabled'] ?? true),
                'dynamic_enabled' => (bool) ($viewport['dynamic_enabled'] ?? true),
            ],
            'positions' => $cleanPositions,
            'routes' => $cleanRoutes,
            'manual_nodes' => $cleanNodes,
            'manual_connections' => $cleanConnections,
            'manual_lines' => $cleanLines,
            'manual_shapes' => $cleanShapes,
            'annotations' => $cleanAnnotations,
            'updated_at' => now()->toISOString(),
        ];
        $normalized['visual_regression'] = $this->visualRegression->normalizeMetadata(
            array_replace($normalized, ['visual_regression' => is_array($editor['visual_regression'] ?? null) ? $editor['visual_regression'] : []]),
        );

        return $normalized;
    }

    private function quarterTurn(mixed $value): int
    {
        $numeric = is_numeric($value) ? (float) $value : 0.0;
        $rotation = ((int) round($numeric / 90)) * 90;

        return (($rotation % 360) + 360) % 360;
    }

    private function groupId(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $clean = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($value)) ?: null;

        return $clean ? substr($clean, 0, 80) : null;
    }

    private function boundedNumber(mixed $value, float $min, float $max): float
    {
        return max($min, min($max, is_numeric($value) ? (float) $value : $min));
    }

    private function filename(Project $project, ProjectSldRevision $revision, string $extension): string
    {
        $number = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) ($project->project_number ?: 'project')) ?: 'project';

        return strtoupper(trim($number, '-')).'-SLD-R'.$revision->revision.'.'.$extension;
    }
}
