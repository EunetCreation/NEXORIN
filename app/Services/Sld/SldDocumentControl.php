<?php

namespace App\Services\Sld;

use App\Models\BillOfMaterialItem;
use App\Models\Company;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SldDocumentControl
{
    public function __construct(
        private readonly SldEquipmentTraceResolver $equipmentTraceResolver,
    ) {
    }

    public const VERSION = '67L.4-FINAL';

    public const ASSESSMENT_VERSION = 'nexorin-sld-release-readiness-v3';

    public const EQUIPMENT_TRACEABILITY_VERSION = SldEquipmentTraceResolver::VERSION;

    public const ISSUE_STATUSES = [
        'draft',
        'internal_review',
        'checked',
        'approved',
        'issued_for_tender',
        'issued_for_construction',
        'as_built',
    ];

    public const ISSUE_CODES = [
        'draft' => 'DRAFT',
        'internal_review' => 'IFR',
        'checked' => 'CHK',
        'approved' => 'APP',
        'issued_for_tender' => 'IFT',
        'issued_for_construction' => 'IFC',
        'as_built' => 'AB',
    ];

    public const ISSUE_LABELS = [
        'draft' => 'Draft',
        'internal_review' => 'Internal Review',
        'checked' => 'Checked',
        'approved' => 'Approved',
        'issued_for_tender' => 'Issued for Tender',
        'issued_for_construction' => 'Issued for Construction',
        'as_built' => 'As-Built',
    ];

    /** @return array<string, mixed> */
    public function build(
        Project $project,
        int $revisionNumber,
        ?User $actor = null,
        array $overrides = [],
        string $issueStatus = 'draft'
    ): array {
        $project->loadMissing([
            'company',
            'customer',
            'assignedEngineer',
            'assignedSales',
            'billOfMaterial.items',
        ]);

        $issueStatus = $this->normalizeIssueStatus($issueStatus);
        $prefix = $this->sanitizePrefix((string) ($overrides['drawing_number_prefix'] ?? $this->defaultPrefix($project)));
        $drawingTitle = $this->cleanText(
            $overrides['drawing_title'] ?? 'PV single-line diagram and engineering schedules',
            180
        );
        $clientName = $this->cleanText(
            $overrides['client_name'] ?? $project->customer?->name ?? 'Client TBC',
            180
        );
        $siteLocation = $this->cleanText(
            $overrides['site_location'] ?? $this->siteLocation($project),
            240
        );
        $companyName = $this->cleanText(
            $overrides['company_name'] ?? $project->company?->name ?? 'NEXORIN Engineering Platform',
            180
        );

        $sheetRegister = $this->sheetRegister($prefix, $revisionNumber);
        $equipmentRegister = $this->equipmentRegister($project);
        $equipmentItems = collect($equipmentRegister)->flatMap(fn (array $group): array => is_array($group['items'] ?? null) ? $group['items'] : []);
        $linkedGroups = collect($equipmentRegister)->where('status', 'BOM linked')->count();

        return [
            'version' => self::VERSION,
            'project' => [
                'id' => $project->getKey(),
                'number' => (string) ($project->project_number ?: 'PROJECT-'.$project->getKey()),
                'name' => (string) $project->name,
                'client' => $clientName,
                'site_location' => $siteLocation,
                'system' => $this->systemLabel((string) $project->proposed_system),
            ],
            'organization' => [
                'name' => $companyName,
                'code' => (string) ($project->company?->code ?: 'NX'),
                'registration_number' => $project->company?->registration_number,
                'address' => $project->company?->address,
            ],
            'drawing' => [
                'number_prefix' => $prefix,
                'title' => $drawingTitle,
                'revision_number' => $revisionNumber,
                'revision_code' => 'R'.$revisionNumber,
                'issue_status' => $issueStatus,
                'issue_code' => self::ISSUE_CODES[$issueStatus],
                'issue_label' => self::ISSUE_LABELS[$issueStatus],
                'paper' => 'A3',
                'orientation' => 'landscape',
                'scale' => 'NTS',
                'sheet_count' => count($sheetRegister),
                'register' => $sheetRegister,
            ],
            'people' => [
                'prepared_by' => $actor?->name,
                'assigned_engineer' => $project->assignedEngineer?->name,
                'assigned_sales' => $project->assignedSales?->name,
                'checked_by' => null,
                'approved_by' => null,
                'issued_by' => null,
            ],
            'equipment_register' => $equipmentRegister,
            'source_links' => [
                'equipment_traceability_version' => self::EQUIPMENT_TRACEABILITY_VERSION,
                'bom_id' => $project->billOfMaterial?->getKey(),
                'bom_status' => $project->billOfMaterial?->pricing_status,
                'material_catalog_linked' => $equipmentItems->contains(fn (array $item): bool => filled($item['material_id'] ?? null)),
                'equipment_groups_total' => count($equipmentRegister),
                'equipment_groups_linked' => $linkedGroups,
                'product_models_linked' => $equipmentItems->filter(fn (array $item): bool => filled($item['model'] ?? null))->count(),
                'datasheets_linked' => $equipmentItems->filter(fn (array $item): bool => filled($item['datasheet_id'] ?? null))->count(),
            ],
            'release' => [
                'not_for_construction' => ! in_array($issueStatus, ['issued_for_construction', 'as_built'], true),
                'certification_status' => 'certification_target',
                'conformity_assessment' => 'pending_independent_assessment',
                'engineering_gaps_open' => true,
            ],
            'generated' => [
                'at' => now()->toISOString(),
                'by_user_id' => $actor?->getKey(),
                'by_name' => $actor?->name,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function merge(Project $project, ProjectSldRevision $revision, array $input, ?User $actor = null): array
    {
        $current = is_array($revision->document_metadata) ? $revision->document_metadata : [];
        $overrides = [
            'drawing_number_prefix' => $input['drawing_number_prefix'] ?? data_get($current, 'drawing.number_prefix'),
            'drawing_title' => $input['drawing_title'] ?? data_get($current, 'drawing.title'),
            'client_name' => $input['client_name'] ?? data_get($current, 'project.client'),
            'site_location' => $input['site_location'] ?? data_get($current, 'project.site_location'),
            'company_name' => data_get($current, 'organization.name'),
        ];

        $fresh = $this->build(
            $project,
            $revision->revision,
            $actor,
            $overrides,
            (string) ($revision->issue_status ?: data_get($current, 'drawing.issue_status', 'draft'))
        );

        $fresh['people'] = array_replace($fresh['people'], is_array($current['people'] ?? null) ? $current['people'] : []);
        $fresh['release'] = array_replace($fresh['release'], is_array($current['release'] ?? null) ? $current['release'] : []);
        $fresh['generated'] = is_array($current['generated'] ?? null) ? $current['generated'] : $fresh['generated'];
        $fresh['updated'] = [
            'at' => now()->toISOString(),
            'by_user_id' => $actor?->getKey(),
            'by_name' => $actor?->name,
        ];

        return $fresh;
    }

    /** @return array<string, mixed> */
    public function stampGraph(array $graph, array $documentControl): array
    {
        $graph['meta'] = is_array($graph['meta'] ?? null) ? $graph['meta'] : [];
        $graph['meta']['document_control'] = $documentControl;
        $graph['meta']['project_number'] = (string) data_get($documentControl, 'project.number', data_get($graph, 'meta.project_number', 'PROJECT'));
        $graph['meta']['project_name'] = (string) data_get($documentControl, 'project.name', data_get($graph, 'meta.project_name', 'Untitled project'));
        $graph['meta']['drawing_number_prefix'] = (string) data_get($documentControl, 'drawing.number_prefix', 'PROJECT-E-SLD');
        $graph['meta']['drawing_revision'] = (string) data_get($documentControl, 'drawing.revision_code', 'R1');
        $graph['meta']['issue_status'] = (string) data_get($documentControl, 'drawing.issue_status', 'draft');
        $graph['meta']['issue_code'] = (string) data_get($documentControl, 'drawing.issue_code', 'DRAFT');

        return $graph;
    }

    /** @return array<string, mixed> */
    public function assess(Project $project, ProjectSldRevision $revision, array $graph, bool $isStale = false): array
    {
        $project->loadMissing(['technicalCalculation', 'billOfMaterial.items']);
        $metadata = is_array($revision->document_metadata) ? $revision->document_metadata : [];
        $controller = is_array($graph['engineering_controller'] ?? null) ? $graph['engineering_controller'] : [];
        $electrical = is_array($graph['electrical_validation'] ?? null) ? $graph['electrical_validation'] : [];
        $warnings = collect(is_array($revision->warnings) ? $revision->warnings : []);
        $criticalWarningCount = $warnings->filter(fn (array $warning): bool => ($warning['severity'] ?? null) === 'critical')->count();
        $metadataPaths = [
            'drawing.number_prefix',
            'drawing.title',
            'project.client',
            'project.site_location',
            'organization.name',
        ];
        $missingMetadata = collect($metadataPaths)->filter(fn (string $path): bool => blank(data_get($metadata, $path)))->values()->all();
        $equipmentGroups = collect(is_array($metadata['equipment_register'] ?? null) ? $metadata['equipment_register'] : []);
        $linkedGroups = $equipmentGroups->where('status', 'BOM linked')->count();
        $totalGroups = max(1, $equipmentGroups->count());
        $checkerIndependent = $revision->reviewed_by !== null
            && ($revision->generated_by === null || (int) $revision->reviewed_by !== (int) $revision->generated_by);
        $approverIndependent = $revision->approved_by !== null
            && ($revision->reviewed_by === null || (int) $revision->approved_by !== (int) $revision->reviewed_by)
            && ($revision->generated_by === null || (int) $revision->approved_by !== (int) $revision->generated_by);

        $gates = [
            $this->gate('source_current', 'Current engineering source', $isStale ? 'blocked' : 'pass',
                $isStale ? 'Project or calculation data changed after this revision was generated.' : 'Revision fingerprint matches the current project source.',
                ['internal_review', 'checked', 'approved', 'issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('metadata_complete', 'Controlled metadata complete', $missingMetadata === [] ? 'pass' : 'blocked',
                $missingMetadata === [] ? 'Project, client, site, organization, title, and drawing prefix are controlled.' : 'Missing metadata: '.implode(', ', $missingMetadata).'.',
                ['internal_review', 'checked', 'approved', 'issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('electrical_rules', 'Electrical-rule check', ($electrical['blocking'] ?? false) ? 'blocked' : 'pass',
                ($electrical['blocking'] ?? false)
                    ? sprintf('%d blocking electrical-rule error(s) remain.', (int) data_get($electrical, 'summary.error', 0))
                    : sprintf('%d error(s), %d warning(s).', (int) data_get($electrical, 'summary.error', 0), (int) data_get($electrical, 'summary.warning', 0)),
                ['internal_review', 'checked', 'approved', 'issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('engineering_controller', 'Engineering source controller', ($controller['blocking'] ?? false) ? 'blocked' : 'pass',
                ($controller['blocking'] ?? false)
                    ? sprintf('%d source-controller error(s) remain.', (int) data_get($controller, 'summary.error', 0))
                    : 'Model sources and drawing topology are synchronized.',
                ['internal_review', 'checked', 'approved', 'issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('technical_calculation', 'Verified technical calculation', $project->technicalCalculation?->result_status === 'verified' ? 'pass' : 'blocked',
                $project->technicalCalculation?->result_status === 'verified' ? 'Linked technical calculation is engineer verified.' : 'Technical calculation verification is required.',
                ['checked', 'approved', 'issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('critical_notices', 'Critical notices resolved', $criticalWarningCount === 0 ? 'pass' : 'blocked',
                $criticalWarningCount === 0 ? 'No critical SLD notice remains.' : "{$criticalWarningCount} critical notice(s) remain open.",
                ['checked', 'approved', 'issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('equipment_traceability', 'Material and datasheet traceability', $linkedGroups === $totalGroups ? 'pass' : 'warning',
                "{$linkedGroups} of {$totalGroups} equipment groups are linked to BOM or Material Catalog records.",
                ['issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('independent_checker', 'Independent checker recorded', $checkerIndependent ? 'pass' : 'blocked',
                $checkerIndependent ? 'Checker is recorded and differs from the drawing preparer.' : 'A checker independent from the drawing preparer is required.',
                ['approved', 'issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('independent_approver', 'Independent approver recorded', $approverIndependent ? 'pass' : 'blocked',
                $approverIndependent ? 'Approver is recorded and differs from both preparer and checker.' : 'An approver independent from preparer and checker is required.',
                ['issued_for_tender', 'issued_for_construction', 'as_built']),
            $this->gate('engineering_gaps', 'Engineering gaps closed', data_get($metadata, 'release.engineering_gaps_open', true) === false ? 'pass' : 'blocked',
                data_get($metadata, 'release.engineering_gaps_open', true) === false ? 'Controlled engineering gaps are closed.' : 'Engineering gaps remain open; IFC and As-Built are blocked.',
                ['issued_for_construction', 'as_built']),
            $this->gate('conformity_assessment', 'Independent conformity assessment', data_get($metadata, 'release.conformity_assessment') !== 'pending_independent_assessment' ? 'pass' : 'blocked',
                data_get($metadata, 'release.conformity_assessment') !== 'pending_independent_assessment' ? 'Independent conformity assessment is recorded.' : 'Independent conformity assessment remains pending.',
                ['issued_for_construction', 'as_built']),
        ];

        $nextTarget = $this->nextTarget($revision);
        $targetAssessment = $this->targetAssessment($nextTarget, $gates);
        $counts = collect($gates)->countBy('status');

        return [
            'version' => self::ASSESSMENT_VERSION,
            'current_issue_status' => $this->normalizeIssueStatus((string) ($revision->issue_status ?: 'draft')),
            'next_target' => $nextTarget,
            'next_target_label' => $nextTarget ? $this->issueLabel($nextTarget) : null,
            'status' => $targetAssessment['blocked'] > 0 ? 'blocked' : ($targetAssessment['warning'] > 0 ? 'conditional' : 'ready'),
            'summary' => [
                'pass' => (int) ($counts['pass'] ?? 0),
                'warning' => (int) ($counts['warning'] ?? 0),
                'blocked' => (int) ($counts['blocked'] ?? 0),
                'next_blockers' => $targetAssessment['blocked'],
                'next_warnings' => $targetAssessment['warning'],
            ],
            'gates' => $gates,
            'target_assessment' => $targetAssessment,
            'allowed_targets' => collect(self::ISSUE_STATUSES)->mapWithKeys(function (string $target) use ($revision, $gates): array {
                $stateAllowed = ! $revision->isReadOnly()
                    && $this->canTransition((string) ($revision->issue_status ?: 'draft'), $target);
                $assessment = $this->targetAssessment($target, $gates);

                return [$target => [
                    'state_allowed' => $stateAllowed,
                    'ready' => $stateAllowed && $assessment['blocked'] === 0,
                    'blocked' => $assessment['blocked'],
                    'warning' => $assessment['warning'],
                    'reasons' => $assessment['reasons'],
                ]];
            })->all(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    public function compare(ProjectSldRevision $current, ?ProjectSldRevision $previous): array
    {
        if (! $previous) {
            return [
                'version' => 'nexorin-sld-revision-comparison-v2',
                'available' => false,
                'current_revision' => $current->revision,
                'message' => 'No earlier SLD revision is available for comparison.',
                'changes' => [],
            ];
        }

        $fields = [
            'Drawing prefix' => 'drawing.number_prefix',
            'Drawing title' => 'drawing.title',
            'Client' => 'project.client',
            'Site location' => 'project.site_location',
            'Organization' => 'organization.name',
            'Issue status' => 'drawing.issue_status',
            'Engineering gaps open' => 'release.engineering_gaps_open',
            'Conformity assessment' => 'release.conformity_assessment',
        ];
        $changes = collect($fields)->map(function (string $path, string $label) use ($current, $previous): array {
            $before = data_get($previous->document_metadata, $path);
            $after = data_get($current->document_metadata, $path);

            return [
                'label' => $label,
                'path' => $path,
                'before' => $before,
                'after' => $after,
                'changed' => $before !== $after,
            ];
        })->filter(fn (array $change): bool => (bool) ($change['changed'] ?? false))->values()->all();

        $currentNodes = collect(data_get($current->graph, 'nodes', []))->pluck('id')->filter()->values();
        $previousNodes = collect(data_get($previous->graph, 'nodes', []))->pluck('id')->filter()->values();
        $currentConnections = collect(data_get($current->graph, 'connections', []))->pluck('id')->filter()->values();
        $previousConnections = collect(data_get($previous->graph, 'connections', []))->pluck('id')->filter()->values();
        $cableDiff = $this->scheduleDiff(
            data_get($previous->graph, 'engineering_controller.cable_schedule', []),
            data_get($current->graph, 'engineering_controller.cable_schedule', []),
            'wire_id'
        );
        $protectionDiff = $this->scheduleDiff(
            data_get($previous->graph, 'engineering_controller.protection_schedule', []),
            data_get($current->graph, 'engineering_controller.protection_schedule', []),
            'node_id'
        );
        $equipmentDiff = $this->equipmentDiff(
            data_get($previous->document_metadata, 'equipment_register', []),
            data_get($current->document_metadata, 'equipment_register', [])
        );
        $sourceChanged = data_get($current->source_snapshot, 'fingerprint') !== data_get($previous->source_snapshot, 'fingerprint');
        $graphChanges = count($currentNodes->diff($previousNodes))
            + count($previousNodes->diff($currentNodes))
            + count($currentConnections->diff($previousConnections))
            + count($previousConnections->diff($currentConnections));

        return [
            'version' => 'nexorin-sld-revision-comparison-v2',
            'available' => true,
            'current_revision' => $current->revision,
            'previous_revision' => $previous->revision,
            'source_changed' => $sourceChanged,
            'changes' => $changes,
            'summary' => [
                'metadata_changes' => count($changes),
                'graph_changes' => $graphChanges,
                'cable_changes' => $cableDiff['changed_count'],
                'protection_changes' => $protectionDiff['changed_count'],
                'equipment_changes' => $equipmentDiff['changed_count'],
                'total_changes' => count($changes) + $graphChanges + $cableDiff['changed_count'] + $protectionDiff['changed_count'] + $equipmentDiff['changed_count'],
            ],
            'graph' => [
                'nodes_before' => $previousNodes->count(),
                'nodes_after' => $currentNodes->count(),
                'nodes_added' => $currentNodes->diff($previousNodes)->values()->all(),
                'nodes_removed' => $previousNodes->diff($currentNodes)->values()->all(),
                'connections_before' => $previousConnections->count(),
                'connections_after' => $currentConnections->count(),
                'connections_added' => $currentConnections->diff($previousConnections)->values()->all(),
                'connections_removed' => $previousConnections->diff($currentConnections)->values()->all(),
            ],
            'cable_schedule' => $cableDiff,
            'protection_schedule' => $protectionDiff,
            'equipment_register' => $equipmentDiff,
        ];
    }

    /** @return array<string, mixed> */
    public function exportPreview(Project $project, ProjectSldRevision $revision): array
    {
        $issueStatus = $this->normalizeIssueStatus((string) ($revision->issue_status ?: 'draft'));
        $notForConstruction = (bool) data_get($revision->document_metadata, 'release.not_for_construction', true);

        return [
            'issue_status' => $issueStatus,
            'issue_code' => $this->issueCode($issueStatus),
            'issue_label' => $this->issueLabel($issueStatus),
            'disposition' => $notForConstruction ? 'NOT FOR CONSTRUCTION' : 'CONTROLLED CONSTRUCTION ISSUE',
            'filenames' => [
                'pdf' => $this->exportFilename($project, $revision, 'pdf'),
                'svg' => $this->exportFilename($project, $revision, 'svg'),
                'manifest' => $this->exportFilename($project, $revision, 'manifest.json'),
                'package' => $this->exportFilename($project, $revision, 'package.zip'),
            ],
            'sheet_count' => (int) data_get($revision->document_metadata, 'drawing.sheet_count', 0),
            'drawing_register' => data_get($revision->document_metadata, 'drawing.register', []),
            'locked' => $revision->isLocked(),
            'locked_at' => $revision->locked_at?->toISOString(),
            'read_only' => $revision->isReadOnly(),
            'package_history_count' => collect(data_get($revision->export_manifest, 'events', []))
                ->filter(fn (mixed $event): bool => is_array($event) && (string) ($event['format'] ?? '') === 'package.zip')
                ->count(),
        ];
    }

    public function assertIndependentChecker(ProjectSldRevision $revision, ?User $actor, ?Company $company = null): void
    {
        $this->assertVerificationAuthority($actor, $company, 'checker');

        if ($revision->generated_by !== null && (int) $revision->generated_by === (int) $actor?->getKey()) {
            throw new \InvalidArgumentException('The drawing preparer cannot record the independent engineering check. Assign a different authorized checker.');
        }
    }

    public function assertIndependentApprover(ProjectSldRevision $revision, ?User $actor, ?Company $company = null): void
    {
        $this->assertVerificationAuthority($actor, $company, 'approver');

        if ($revision->reviewed_by === null) {
            throw new \InvalidArgumentException('A recorded independent checker is required before approval.');
        }

        if ((int) $revision->reviewed_by === (int) $actor?->getKey()) {
            throw new \InvalidArgumentException('The checker cannot approve the same drawing revision. Assign a different authorized approver.');
        }

        if ($revision->generated_by !== null && (int) $revision->generated_by === (int) $actor?->getKey()) {
            throw new \InvalidArgumentException('The drawing preparer cannot approve the same drawing revision.');
        }
    }

    /** @return array<string, mixed> */
    public function transition(
        ProjectSldRevision $revision,
        string $toStatus,
        ?User $actor,
        ?string $note = null
    ): array {
        $toStatus = $this->normalizeIssueStatus($toStatus);
        $fromStatus = $this->normalizeIssueStatus((string) ($revision->issue_status ?: 'draft'));

        if (! $this->canTransition($fromStatus, $toStatus)) {
            throw new \InvalidArgumentException("Drawing issue status cannot move from {$fromStatus} to {$toStatus}.");
        }

        $history = is_array($revision->issue_history) ? $revision->issue_history : [];
        $history[] = [
            'event_id' => (string) Str::uuid(),
            'from' => $fromStatus,
            'to' => $toStatus,
            'code' => self::ISSUE_CODES[$toStatus],
            'label' => self::ISSUE_LABELS[$toStatus],
            'note' => $this->cleanText($note, 1000),
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name,
            'at' => now()->toISOString(),
        ];

        return array_slice($history, -50);
    }

    public function releaseGateAllows(string $toStatus, array $documentMetadata): bool
    {
        $toStatus = $this->normalizeIssueStatus($toStatus);

        if (! in_array($toStatus, ['issued_for_construction', 'as_built'], true)) {
            return true;
        }

        return data_get($documentMetadata, 'release.engineering_gaps_open', true) === false
            && data_get($documentMetadata, 'release.conformity_assessment') !== 'pending_independent_assessment';
    }

    public function releaseGateMessage(string $toStatus): string
    {
        $label = $this->issueLabel($toStatus);

        return "{$label} is blocked while engineering gaps or independent conformity assessment remain open. Use Issued for Tender, or close the controlled release gates first.";
    }

    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, match ($fromStatus) {
            'draft' => ['internal_review'],
            'internal_review' => ['draft', 'checked'],
            'checked' => ['draft', 'approved'],
            'approved' => ['issued_for_tender', 'issued_for_construction', 'as_built'],
            'issued_for_tender' => ['issued_for_construction', 'as_built'],
            'issued_for_construction' => ['as_built'],
            default => [],
        }, true);
    }

    /** @return array<string, mixed> */
    public function manifest(
        Project $project,
        ProjectSldRevision $revision,
        array $graph,
        array $paperSet,
        string $format,
        string $bytes,
        string $renderer,
        ?User $actor = null
    ): array {
        $documentControl = is_array($revision->document_metadata)
            ? $revision->document_metadata
            : (is_array(data_get($graph, 'meta.document_control')) ? data_get($graph, 'meta.document_control') : []);
        $sheets = collect(is_array($paperSet['sheets'] ?? null) ? $paperSet['sheets'] : [])
            ->values()
            ->map(function (array $sheet, int $index) use ($documentControl): array {
                $register = data_get($documentControl, 'drawing.register.'.($index), []);

                return [
                    'sheet' => (int) ($sheet['number'] ?? $index + 1),
                    'count' => (int) ($sheet['count'] ?? data_get($documentControl, 'drawing.sheet_count', 1)),
                    'drawing_number' => (string) ($register['drawing_number'] ?? $this->sheetNumber(
                        (string) data_get($documentControl, 'drawing.number_prefix', 'PROJECT-E-SLD'),
                        $index + 1
                    )),
                    'title' => (string) ($sheet['title'] ?? $register['title'] ?? 'Drawing sheet'),
                    'type' => (string) ($sheet['type'] ?? 'drawing'),
                    'paper' => (string) ($sheet['paper'] ?? 'A3'),
                    'orientation' => (string) ($sheet['orientation'] ?? 'landscape'),
                ];
            })
            ->all();
        $issueStatus = $this->normalizeIssueStatus((string) $revision->issue_status);
        $metadataJson = json_encode($documentControl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        $registerJson = json_encode($sheets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        $historyJson = json_encode($revision->issue_history ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        $auditJson = json_encode($revision->audit_trail ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        $sourceFingerprint = (string) data_get($revision->source_snapshot, 'fingerprint', '');
        $packageId = Str::upper(substr(hash('sha256', implode('|', [
            $project->getKey(),
            $revision->getKey(),
            $revision->revision,
            $issueStatus,
            $sourceFingerprint,
            data_get($documentControl, 'drawing.number_prefix'),
        ])), 0, 20));

        return [
            'manifest_version' => 'nexorin-sld-export-v3',
            'document_control_version' => self::VERSION,
            'package_id' => $packageId,
            'event_id' => (string) Str::uuid(),
            'project_id' => $project->getKey(),
            'revision_id' => $revision->getKey(),
            'revision' => $revision->revision,
            'issue_status' => $issueStatus,
            'issue_code' => self::ISSUE_CODES[$issueStatus],
            'disposition' => data_get($documentControl, 'release.not_for_construction', true) ? 'NOT FOR CONSTRUCTION' : 'CONTROLLED CONSTRUCTION ISSUE',
            'format' => strtolower($format),
            'renderer' => $renderer,
            'filename' => $this->exportFilename($project, $revision, strtolower($format)),
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'graph_sha256' => hash('sha256', json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
            'metadata_sha256' => hash('sha256', $metadataJson),
            'register_sha256' => hash('sha256', $registerJson),
            'issue_history_sha256' => hash('sha256', $historyJson),
            'audit_trail_sha256' => hash('sha256', $auditJson),
            'source_fingerprint' => $sourceFingerprint,
            'record_status' => $revision->status,
            'immutable' => $revision->isLocked(),
            'locked_at' => $revision->locked_at?->toISOString(),
            'locked_by' => $revision->locked_by,
            'superseded_by_revision_id' => $revision->superseded_by_revision_id,
            'source_current' => (function () use ($graph): bool {
                $gate = collect(data_get($graph, 'meta.release_assessment.gates', []))->firstWhere('id', 'source_current');

                return is_array($gate) && ($gate['status'] ?? null) === 'pass';
            })(),
            'release_assessment' => data_get($graph, 'meta.release_assessment'),
            'sheet_count' => count($sheets),
            'sheets' => $sheets,
            'exported_at' => now()->toISOString(),
            'exported_by' => [
                'user_id' => $actor?->getKey(),
                'name' => $actor?->name,
            ],
            'document_control' => $documentControl,
        ];
    }

    /** @return array<string, mixed> */
    public function appendExportManifest(ProjectSldRevision $revision, array $manifest): array
    {
        $existing = is_array($revision->export_manifest) ? $revision->export_manifest : [];
        $events = is_array($existing['events'] ?? null) ? $existing['events'] : [];
        $events[] = Arr::except($manifest, ['document_control', 'release_assessment']);
        $format = strtolower((string) ($manifest['format'] ?? 'export'));

        return [
            'version' => 'nexorin-sld-export-history-v3',
            'latest' => $manifest,
            'latest_by_format' => array_replace(
                is_array($existing['latest_by_format'] ?? null) ? $existing['latest_by_format'] : [],
                [$format => $manifest]
            ),
            'events' => array_slice($events, -50),
        ];
    }

    public function exportFilename(Project $project, ProjectSldRevision $revision, string $extension): string
    {
        $prefix = (string) data_get($revision->document_metadata, 'drawing.number_prefix', $this->defaultPrefix($project));
        $issueCode = self::ISSUE_CODES[$this->normalizeIssueStatus((string) ($revision->issue_status ?: 'draft'))];
        $base = trim($prefix, '-').'-R'.$revision->revision.'-'.$issueCode;

        return Str::upper(Str::slug($base, '-')).'.'.ltrim(strtolower($extension), '.');
    }

    public function normalizeIssueStatus(string $status): string
    {
        return in_array($status, self::ISSUE_STATUSES, true) ? $status : 'draft';
    }

    public function issueLabel(string $status): string
    {
        return self::ISSUE_LABELS[$this->normalizeIssueStatus($status)];
    }

    public function issueCode(string $status): string
    {
        return self::ISSUE_CODES[$this->normalizeIssueStatus($status)];
    }

    /** @return array<int, array<string, mixed>> */
    public function appendAudit(
        ProjectSldRevision $revision,
        string $event,
        ?User $actor = null,
        array $changes = [],
        array $context = []
    ): array {
        $trail = is_array($revision->audit_trail) ? $revision->audit_trail : [];
        $trail[] = [
            'event_id' => (string) Str::uuid(),
            'event' => Str::snake($event),
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name,
            'changes' => $changes,
            'context' => $context,
            'at' => now()->toISOString(),
        ];

        return array_slice($trail, -200);
    }

    /** @return array<int, array<string, mixed>> */
    public function metadataChangeSet(array $before, array $after, array $paths): array
    {
        return collect($paths)->map(function (string $path) use ($before, $after): array {
            return [
                'field' => $path,
                'before' => data_get($before, $path),
                'after' => data_get($after, $path),
            ];
        })->filter(fn (array $change): bool => $change['before'] !== $change['after'])->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function exportEvent(ProjectSldRevision $revision, string $eventId): ?array
    {
        return collect(data_get($revision->export_manifest, 'events', []))
            ->first(fn (mixed $event): bool => is_array($event) && (string) ($event['event_id'] ?? '') === $eventId);
    }

    /** @return array<string, mixed> */
    public function updateExportEvent(ProjectSldRevision $revision, string $eventId, array $updates): array
    {
        $history = is_array($revision->export_manifest) ? $revision->export_manifest : [];
        $events = collect(is_array($history['events'] ?? null) ? $history['events'] : [])
            ->map(function (mixed $event) use ($eventId, $updates): mixed {
                if (! is_array($event) || (string) ($event['event_id'] ?? '') !== $eventId) {
                    return $event;
                }

                return array_replace_recursive($event, $updates);
            })->values()->all();
        $history['events'] = $events;
        if ((string) data_get($history, 'latest.event_id') === $eventId) {
            $history['latest'] = array_replace_recursive((array) $history['latest'], $updates);
        }
        foreach ((array) ($history['latest_by_format'] ?? []) as $format => $event) {
            if (is_array($event) && (string) ($event['event_id'] ?? '') === $eventId) {
                $history['latest_by_format'][$format] = array_replace_recursive($event, $updates);
            }
        }

        return $history;
    }

    /** @return array<string, mixed> */
    public function appendExportFailure(ProjectSldRevision $revision, string $format, \Throwable $exception, ?User $actor = null): array
    {
        $failures = is_array($revision->export_failures) ? $revision->export_failures : [];
        $failures[] = [
            'event_id' => (string) Str::uuid(),
            'format' => strtolower($format),
            'message' => $this->cleanText($exception->getMessage(), 1000),
            'exception' => $exception::class,
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name,
            'at' => now()->toISOString(),
        ];

        return array_slice($failures, -50);
    }

    /** @return array<int, array<string, mixed>> */
    public function disclosureSafeAuditTrail(ProjectSldRevision $revision): array
    {
        return collect(is_array($revision->audit_trail) ? $revision->audit_trail : [])
            ->filter(fn (mixed $event): bool => is_array($event))
            ->map(function (array $event): array {
                $context = is_array($event['context'] ?? null) ? $event['context'] : [];
                $event['context'] = Arr::except($context, [
                    'archive_path',
                    'disk',
                    'path',
                    'message',
                    'exception',
                ]);

                return $event;
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function disclosureSafeExportHistory(ProjectSldRevision $revision): array
    {
        $history = is_array($revision->export_manifest) ? $revision->export_manifest : [];
        $safe = ['version' => $history['version'] ?? null];

        if (is_array($history['latest'] ?? null)) {
            $safe['latest'] = $this->disclosureSafeExportEvent($history['latest']);
        }

        $safe['latest_by_format'] = collect(is_array($history['latest_by_format'] ?? null) ? $history['latest_by_format'] : [])
            ->map(fn (mixed $event): mixed => is_array($event) ? $this->disclosureSafeExportEvent($event) : $event)
            ->all();
        $safe['events'] = collect(is_array($history['events'] ?? null) ? $history['events'] : [])
            ->filter(fn (mixed $event): bool => is_array($event))
            ->map(fn (array $event): array => $this->disclosureSafeExportEvent($event))
            ->values()
            ->all();

        return $safe;
    }

    /** @return array<int, array<string, mixed>> */
    public function disclosureSafeExportFailures(ProjectSldRevision $revision): array
    {
        return collect(is_array($revision->export_failures) ? $revision->export_failures : [])
            ->filter(fn (mixed $failure): bool => is_array($failure))
            ->map(fn (array $failure): array => Arr::only($failure, [
                'event_id',
                'format',
                'user_id',
                'user_name',
                'at',
            ]) + ['status' => 'failed'])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function disclosureSafeExportEvent(array $event): array
    {
        $safe = Arr::except($event, ['document_control', 'release_assessment']);
        if (is_array($safe['archive'] ?? null)) {
            $safe['archive'] = Arr::only($safe['archive'], [
                'filename',
                'byte_size',
                'sha256',
                'stored_at',
            ]);
        }

        return $safe;
    }

    private function assertVerificationAuthority(?User $actor, ?Company $company, string $authority): void
    {
        if (! $actor) {
            throw new \InvalidArgumentException('A signed-in '.$authority.' is required.');
        }
        if (! $actor->isActive()) {
            throw new \InvalidArgumentException('The selected '.$authority.' account is not active.');
        }
        if ($company && ! $actor->isSuperAdmin() && ! $actor->hasCompanyPermission($company, 'calculation.verify')) {
            throw new \InvalidArgumentException('The selected '.$authority.' does not have calculation.verify authority in this company.');
        }
    }

    /** @return array<string, mixed> */
    private function scheduleDiff(mixed $beforeRows, mixed $afterRows, string $key): array
    {
        $before = collect(is_array($beforeRows) ? $beforeRows : [])->filter(fn (mixed $row): bool => is_array($row) && filled($row[$key] ?? null))->keyBy($key);
        $after = collect(is_array($afterRows) ? $afterRows : [])->filter(fn (mixed $row): bool => is_array($row) && filled($row[$key] ?? null))->keyBy($key);
        $added = $after->keys()->diff($before->keys())->values()->all();
        $removed = $before->keys()->diff($after->keys())->values()->all();
        $changed = $before->keys()->intersect($after->keys())->filter(function (mixed $id) use ($before, $after): bool {
            return $this->stableHash((array) $before->get($id)) !== $this->stableHash((array) $after->get($id));
        })->values()->all();

        return [
            'before' => $before->count(),
            'after' => $after->count(),
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'changed_count' => count($added) + count($removed) + count($changed),
        ];
    }

    /** @return array<string, mixed> */
    private function equipmentDiff(mixed $beforeGroups, mixed $afterGroups): array
    {
        $normalise = function (mixed $groups): \Illuminate\Support\Collection {
            return collect(is_array($groups) ? $groups : [])->filter(fn (mixed $group): bool => is_array($group))
                ->mapWithKeys(function (array $group): array {
                    $key = (string) ($group['group'] ?? $group['label'] ?? Str::uuid());

                    return [$key => $group];
                });
        };
        $before = $normalise($beforeGroups);
        $after = $normalise($afterGroups);
        $added = $after->keys()->diff($before->keys())->values()->all();
        $removed = $before->keys()->diff($after->keys())->values()->all();
        $changed = $before->keys()->intersect($after->keys())->filter(function (mixed $id) use ($before, $after): bool {
            return $this->stableHash((array) $before->get($id)) !== $this->stableHash((array) $after->get($id));
        })->values()->all();

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'changed_count' => count($added) + count($removed) + count($changed),
        ];
    }

    private function stableHash(array $value): string
    {
        $sort = function (&$item) use (&$sort): void {
            if (! is_array($item)) {
                return;
            }
            if (array_is_list($item)) {
                foreach ($item as &$child) $sort($child);
                return;
            }
            ksort($item);
            foreach ($item as &$child) $sort($child);
        };
        $sort($value);

        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /** @return array<int, array<string, mixed>> */
    private function sheetRegister(string $prefix, int $revision): array
    {
        $titles = [
            'Single-line diagram - On-grid PV system',
            'Zero-export metering, control and export-trip schematic',
            'MPPT, string and equipment schedule',
            'Cable and interconnection schedule - 1 of 2',
            'Cable and interconnection schedule - 2 of 2',
            'Protection, metering and engineering data gaps',
        ];

        return collect($titles)
            ->map(fn (string $title, int $index): array => [
                'sheet' => $index + 1,
                'drawing_number' => $this->sheetNumber($prefix, $index + 1),
                'title' => $title,
                'revision' => 'R'.$revision,
            ])
            ->all();
    }

    private function sheetNumber(string $prefix, int $sheet): string
    {
        return $prefix.'-'.str_pad((string) $sheet, 3, '0', STR_PAD_LEFT);
    }

    private function defaultPrefix(Project $project): string
    {
        $base = (string) ($project->project_number ?: 'NX-'.$project->getKey());

        return $this->sanitizePrefix($base.'-E-SLD');
    }

    private function sanitizePrefix(string $value): string
    {
        $value = Str::upper(Str::slug($value, '-'));
        $value = preg_replace('/-+/', '-', $value) ?: 'PROJECT-E-SLD';

        return substr(trim($value, '-'), 0, 80);
    }

    private function siteLocation(Project $project): string
    {
        return collect([$project->site_address, $project->city, $project->province, $project->postal_code])
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->implode(', ');
    }

    private function systemLabel(string $system): string
    {
        return match ($system) {
            'hybrid' => 'Hybrid PV System',
            'off_grid' => 'Off-grid PV System',
            default => 'On-grid PV System',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function equipmentRegister(Project $project): array
    {
        $items = collect($project->billOfMaterial?->items ?? []);
        $materialIds = $items
            ->map(fn (BillOfMaterialItem $item): ?int => $this->equipmentTraceResolver->materialId($item))
            ->filter()
            ->unique()
            ->values();

        $materials = $materialIds->isEmpty()
            ? collect()
            : Material::query()
                ->with(['datasheets' => fn ($query) => $query->where('is_active', true)])
                ->when($project->company_id, fn ($query) => $query->visibleForCompany((int) $project->company_id))
                ->whereIn('id', $materialIds)
                ->get()
                ->keyBy(fn (Material $material): int => (int) $material->getKey());

        $wanted = [
            'pv_module' => ['module', 'panel', 'pv array'],
            'inverter' => ['inverter'],
            'dc_protection' => ['dc isolator', 'dc spd', 'fuse'],
            'ac_protection' => ['breaker', 'isolator', 'ac spd', 'combiner'],
            'metering_control' => ['meter', 'ct', 'relay', 'controller', 'logger'],
        ];

        return collect($wanted)->map(function (array $terms, string $group) use ($items, $materials): array {
            $matches = $items->filter(function (BillOfMaterialItem $item) use ($terms): bool {
                $haystack = Str::lower($this->equipmentTraceResolver->searchText($item));

                return collect($terms)->contains(fn (string $term): bool => str_contains($haystack, $term));
            });

            return [
                'group' => $group,
                'label' => str($group)->replace('_', ' ')->title()->toString(),
                'status' => $matches->isEmpty() ? 'TBC' : 'BOM linked',
                'items' => $matches->take(8)->map(function (BillOfMaterialItem $item) use ($materials): array {
                    $materialId = $this->equipmentTraceResolver->materialId($item);
                    /** @var Material|null $material */
                    $material = $materialId ? $materials->get($materialId) : null;

                    return $this->equipmentTraceResolver->resolve($item, $material);
                })->values()->all(),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function gate(string $id, string $label, string $status, string $detail, array $requiredFor): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'status' => in_array($status, ['pass', 'warning', 'blocked'], true) ? $status : 'warning',
            'detail' => $detail,
            'required_for' => $requiredFor,
        ];
    }

    /** @return array<string, mixed> */
    private function targetAssessment(?string $target, array $gates): array
    {
        if (! $target) {
            return ['target' => null, 'blocked' => 0, 'warning' => 0, 'reasons' => []];
        }

        $required = collect($gates)->filter(fn (array $gate): bool => in_array($target, $gate['required_for'] ?? [], true));
        $blockers = $required->where('status', 'blocked');
        $warnings = $required->where('status', 'warning');

        return [
            'target' => $target,
            'target_label' => $this->issueLabel($target),
            'blocked' => $blockers->count(),
            'warning' => $warnings->count(),
            'reasons' => $blockers->pluck('detail')->values()->all(),
            'warning_reasons' => $warnings->pluck('detail')->values()->all(),
        ];
    }

    private function nextTarget(ProjectSldRevision $revision): ?string
    {
        if ($revision->isReadOnly()) {
            return null;
        }

        if ($revision->issue_status === 'issued_for_tender') {
            return 'issued_for_construction';
        }
        if ($revision->issue_status === 'issued_for_construction') {
            return 'as_built';
        }
        if ($revision->issue_status === 'as_built') {
            return null;
        }

        return match ($revision->status) {
            'draft' => 'internal_review',
            'needs_review' => 'checked',
            'checked' => 'approved',
            'approved' => 'issued_for_tender',
            default => null,
        };
    }

    private function cleanText(mixed $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = preg_replace('/\s+/u', ' ', trim((string) $value)) ?: '';

        return $text === '' ? null : mb_substr($text, 0, $limit);
    }
}
