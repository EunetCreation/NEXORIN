<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectSldRevision;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Services\Sld\SldDocumentControl;
use App\Services\Sld\SldExportPackageBuilder;
use App\Services\Sld\SldPackageArchive;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, "[APP67L.4-FINAL] FAIL · {$message}\n");
        exit(1);
    }
};

$project = new Project([
    'project_number' => 'NX-2026-0099',
    'name' => 'Production Drawing Workflow Test',
    'proposed_system' => 'on_grid',
    'site_address' => 'Jl. Test Engineering 99',
    'city' => 'Jakarta',
    'province' => 'DKI Jakarta',
]);
$project->id = 9901;
$project->exists = true;
$project->setRelation('company', null);
$project->setRelation('customer', null);
$project->setRelation('assignedEngineer', null);
$project->setRelation('assignedSales', null);
$project->setRelation('billOfMaterial', null);
$project->setRelation('technicalCalculation', new TechnicalCalculation(['result_status' => 'verified']));

$preparer = new User(['name' => 'Drawing Preparer', 'status' => 'active']);
$preparer->id = 9910;
$preparer->exists = true;
$checker = new User(['name' => 'Independent Checker', 'status' => 'active']);
$checker->id = 9911;
$checker->exists = true;
$approver = new User(['name' => 'Independent Approver', 'status' => 'active']);
$approver->id = 9912;
$approver->exists = true;

/** @var SldDocumentControl $service */
$service = $app->make(SldDocumentControl::class);
$metadata = $service->build($project, 2, $preparer, [
    'drawing_number_prefix' => 'NX-2026-0099-E-SLD',
    'drawing_title' => 'Controlled PV drawing package',
    'client_name' => 'PT Client Test',
    'site_location' => 'Jakarta, DKI Jakarta',
], 'draft');

$assert(data_get($metadata, 'version') === '67L.4-FINAL', 'Document-control version must be APP67L.4-FINAL.');
$assert(data_get($metadata, 'drawing.issue_code') === 'DRAFT', 'Draft issue code must be DRAFT.');
$assert(data_get($metadata, 'drawing.sheet_count') === 6, 'Production register must contain six sheets.');
$assert(data_get($metadata, 'drawing.register.0.drawing_number') === 'NX-2026-0099-E-SLD-001', 'First drawing number must be deterministic.');
$assert(data_get($metadata, 'drawing.register.5.drawing_number') === 'NX-2026-0099-E-SLD-006', 'Last drawing number must be deterministic.');
$assert(data_get($metadata, 'project.client') === 'PT Client Test', 'Client metadata must be project-specific.');
$assert(data_get($metadata, 'release.not_for_construction') === true, 'Draft output must remain not for construction.');

$graph = $service->stampGraph([
    'schema_version' => 'nexorin-sld-v1.9.0',
    'meta' => ['project_number' => 'OLD', 'project_name' => 'OLD'],
    'nodes' => [['id' => 'pv_01'], ['id' => 'inv_01']],
    'connections' => [['id' => 'C-01']],
    'electrical_validation' => ['blocking' => false, 'summary' => ['error' => 0, 'warning' => 0]],
    'engineering_controller' => [
        'blocking' => false,
        'summary' => ['error' => 0],
        'cable_schedule' => [['wire_id' => 'C-01', 'size_display' => '2 x 1C x 6 mm2']],
        'protection_schedule' => [['node_id' => 'qf_01', 'rating' => 100]],
    ],
], $metadata);
$assert(data_get($graph, 'meta.project_number') === 'NX-2026-0099', 'Stamped graph must use production metadata.');
$assert(data_get($graph, 'meta.issue_code') === 'DRAFT', 'Stamped graph must expose issue code.');

$revision = new ProjectSldRevision([
    'company_id' => 9900,
    'project_id' => $project->id,
    'revision' => 2,
    'status' => 'draft',
    'issue_status' => 'draft',
    'document_metadata' => $metadata,
    'issue_history' => [],
    'audit_trail' => [],
    'export_manifest' => [],
    'export_failures' => [],
    'source_snapshot' => ['fingerprint' => 'abc123'],
    'graph' => $graph,
    'warnings' => [],
    'generated_by' => $preparer->id,
]);
$revision->id = 9920;
$revision->exists = true;
$revision->generated_at = Carbon::parse('2026-07-23T10:00:00+07:00');

$assessment = $service->assess($project, $revision, $graph, false);
$assert(data_get($assessment, 'version') === SldDocumentControl::ASSESSMENT_VERSION, 'Release assessment must use the R3 readiness contract.');
$assert(data_get($assessment, 'next_target') === 'internal_review', 'Draft next target must be Internal Review.');
$assert(data_get($assessment, 'target_assessment.blocked') === 0, 'Complete draft metadata and clear checks must be ready for review.');

$audit = $service->appendAudit($revision, 'metadata_updated', $preparer, [
    ['field' => 'drawing.title', 'before' => 'A', 'after' => 'B'],
]);
$assert(count($audit) === 1 && data_get($audit, '0.event') === 'metadata_updated', 'Audit trail must store a normalized event.');
$assert(filled(data_get($audit, '0.event_id')), 'Audit event must have a durable event ID.');
$revision->audit_trail = $audit;

$history = $service->transition($revision, 'internal_review', $preparer, 'Submitted to checker.');
$assert(count($history) === 1 && data_get($history, '0.code') === 'IFR', 'Draft must transition to IFR.');
$revision->issue_status = 'internal_review';
$revision->issue_history = $history;
$revision->status = 'needs_review';

$checkerBlocked = false;
try {
    $service->assertIndependentChecker($revision, $preparer);
} catch (InvalidArgumentException) {
    $checkerBlocked = true;
}
$assert($checkerBlocked, 'Drawing preparer must be blocked from independent checking.');
$service->assertIndependentChecker($revision, $checker);

$history = $service->transition($revision, 'checked', $checker, 'Independent check completed.');
$revision->issue_status = 'checked';
$revision->issue_history = $history;
$revision->status = 'checked';
$revision->reviewed_by = $checker->id;
$assert(data_get($history, '1.code') === 'CHK', 'Internal review must transition to checked.');

$approverBlocked = false;
try {
    $service->assertIndependentApprover($revision, $checker);
} catch (InvalidArgumentException) {
    $approverBlocked = true;
}
$assert($approverBlocked, 'Checker must be blocked from approving the same drawing.');
$service->assertIndependentApprover($revision, $approver);
$history = $service->transition($revision, 'approved', $approver, 'Independent approval recorded.');
$assert(data_get($history, '2.code') === 'APP', 'Checked drawing must transition to approved.');
$assert(! $service->canTransition('internal_review', 'approved'), 'The checked state must not be bypassed.');
$revision->issue_status = 'approved';
$revision->issue_history = $history;
$revision->status = 'approved';
$revision->approved_by = $approver->id;
$revision->approved_at = now();

$assert(! $service->releaseGateAllows('issued_for_construction', $metadata), 'IFC must remain blocked while controlled gaps are open.');
$assert($service->releaseGateAllows('issued_for_tender', $metadata), 'IFT may remain not for construction.');
$history = $service->transition($revision, 'issued_for_tender', $approver, 'Tender issue authority.');
$assert(data_get($history, '3.code') === 'IFT', 'Approved drawing must transition to IFT.');
$revision->issue_status = 'issued_for_tender';
$revision->locked_at = now();
$revision->locked_by = $approver->id;
$assert($revision->isLocked() && $revision->isReadOnly(), 'Issued revision must be locked and read-only.');

$previousMetadata = array_replace_recursive($metadata, [
    'drawing' => ['title' => 'Previous package title'],
    'equipment_register' => [['group' => 'inverter', 'status' => 'TBC', 'items' => []]],
]);
$previous = new ProjectSldRevision([
    'revision' => 1,
    'issue_status' => 'draft',
    'document_metadata' => $previousMetadata,
    'source_snapshot' => ['fingerprint' => 'old-source'],
    'graph' => [
        'nodes' => [['id' => 'pv_01']],
        'connections' => [],
        'engineering_controller' => [
            'cable_schedule' => [['wire_id' => 'C-01', 'size_display' => '2 x 1C x 4 mm2']],
            'protection_schedule' => [['node_id' => 'qf_01', 'rating' => 80]],
        ],
    ],
]);
$comparison = $service->compare($revision, $previous);
$assert(data_get($comparison, 'version') === 'nexorin-sld-revision-comparison-v2', 'Revision comparison must use the R3 detail contract.');
$assert(data_get($comparison, 'source_changed') === true, 'Comparison must report source fingerprint changes.');
$assert(data_get($comparison, 'graph.nodes_added.0') === 'inv_01', 'Comparison must identify added node IDs.');
$assert(data_get($comparison, 'cable_schedule.changed_count') === 1, 'Comparison must identify changed cable rows.');
$assert(data_get($comparison, 'protection_schedule.changed_count') === 1, 'Comparison must identify changed protection rows.');
$assert(data_get($comparison, 'summary.total_changes') > 0, 'Comparison must expose a consolidated change count.');

$revision->document_metadata = $metadata;
$paperSet = [
    'sheets' => collect(data_get($metadata, 'drawing.register'))->map(fn (array $row): array => [
        'number' => $row['sheet'],
        'count' => 6,
        'title' => $row['title'],
        'type' => 'drawing',
        'paper' => 'A3',
        'orientation' => 'landscape',
    ])->all(),
];
$graph['meta']['release_assessment'] = $assessment;
$bytes = '%PDF-1.4 controlled-test';
$manifest = $service->manifest($project, $revision, $graph, $paperSet, 'pdf', $bytes, 'test-renderer', $approver);
$assert(data_get($manifest, 'manifest_version') === 'nexorin-sld-export-v3', 'Manifest must use the R3 integrity contract.');
$assert(data_get($manifest, 'sha256') === hash('sha256', $bytes), 'Manifest must store exact output checksum.');
$assert(data_get($manifest, 'sheet_count') === 6, 'Manifest must retain six-sheet register.');
$assert(str_ends_with((string) data_get($manifest, 'filename'), '-R2-IFT.pdf'), 'IFT filename must contain revision and issue code.');
$exportHistory = $service->appendExportManifest($revision, $manifest);
$assert(data_get($exportHistory, 'version') === 'nexorin-sld-export-history-v3', 'Export history must use the R3 contract.');
$revision->export_manifest = $exportHistory;

$preview = $service->exportPreview($project, $revision);
$assert(data_get($preview, 'locked') === true && data_get($preview, 'read_only') === true, 'Export preview must expose immutable issued state.');
$assert(str_ends_with((string) data_get($preview, 'filenames.package'), '-R2-IFT.package.zip'), 'Export preview must show the exact package filename.');

/** @var SldExportPackageBuilder $packageBuilder */
$packageBuilder = $app->make(SldExportPackageBuilder::class);
$packageFiles = [
    'drawing.pdf' => $bytes,
    'manifest.json' => json_encode($manifest, JSON_PRETTY_PRINT) ?: '{}',
    'drawing-register.csv' => $packageBuilder->registerCsv(data_get($metadata, 'drawing.register', [])),
];
$packageFiles['checksums.sha256'] = $packageBuilder->checksums($packageFiles);
$zip = $packageBuilder->build($packageFiles);
$assert(str_starts_with($zip, "PK\x03\x04"), 'Dependency-free package builder must emit a valid ZIP header.');

Storage::fake('local');
/** @var SldPackageArchive $archive */
$archive = $app->make(SldPackageArchive::class);
$archiveRecord = $archive->store($revision, (string) data_get($manifest, 'event_id'), 'controlled-package.zip', $zip);
$assert($archive->exists($archiveRecord), 'Controlled package must be persisted in private storage.');
$verification = $archive->verify($archiveRecord, hash('sha256', $zip));
$assert(data_get($verification, 'status') === 'verified', 'Stored package checksum must verify.');
Storage::disk('local')->put((string) data_get($archiveRecord, 'path'), 'tampered');
$tampered = $archive->verify($archiveRecord, hash('sha256', $zip));
$assert(data_get($tampered, 'status') === 'mismatch', 'Tampered package must be detected.');
$invalidLocation = $archive->verify(['disk' => 'public', 'path' => '../outside.zip'], hash('sha256', $zip));
$assert(data_get($invalidLocation, 'status') === 'missing', 'Archive verification must reject non-private or traversal paths.');

$controllerSource = file_get_contents($root.'/app/Http/Controllers/ProjectSldController.php') ?: '';
$assert(str_contains($controllerSource, 'Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();'), 'Revision generation must serialize against the project row.');
$assert(str_contains($controllerSource, 'This issued revision is immutable.'), 'A locked issued revision must not be re-issued in place.');

$manifest['format'] = 'package.zip';
$manifest['archive'] = $archiveRecord;
$manifest['verification'] = ['status' => 'not_verified'];
$revision->export_manifest = $service->appendExportManifest($revision, $manifest);
$eventId = (string) data_get($manifest, 'event_id');
$updatedHistory = $service->updateExportEvent($revision, $eventId, ['verification' => ['status' => 'verified']]);
$revision->export_manifest = $updatedHistory;
$assert(data_get($service->exportEvent($revision, $eventId), 'verification.status') === 'verified', 'Package verification must update the matching export event.');

$failureHistory = $service->appendExportFailure($revision, 'package.zip', new RuntimeException('Synthetic export failure'), $approver);
$assert(data_get($failureHistory, '0.format') === 'package.zip', 'Failed export must be recorded without changing issue status.');
$assert($revision->issue_status === 'issued_for_tender', 'Failed export history must not alter the drawing issue state.');

$assert(Route::has('companies.projects.sld.packages.download'), 'Archived package download route must be registered.');
$assert(Route::has('companies.projects.sld.packages.verify'), 'Archived package verification route must be registered.');

fwrite(STDOUT, "[APP67L.4-FINAL] PASS · immutable issued revisions, supersession-ready model, detailed revision diff, audit trail, role separation, v3 manifests, private package archive, checksum verification, tamper detection, re-download routes, and export-failure recovery\n");
