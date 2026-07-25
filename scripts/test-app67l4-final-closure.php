<?php

declare(strict_types=1);

use App\Models\ProjectSldRevision;
use App\Services\Sld\SldDocumentControl;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

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

$lockedRecord = static function (array $overrides = []): ProjectSldRevision {
    $record = new ProjectSldRevision();
    $record->setRawAttributes(array_replace([
        'id' => 6704,
        'company_id' => 1,
        'project_id' => 1,
        'revision' => 4,
        'status' => 'approved',
        'issue_status' => 'issued_for_tender',
        'graph' => '{}',
        'document_metadata' => '{}',
        'issue_history' => '[]',
        'audit_trail' => '[]',
        'export_manifest' => '{}',
        'export_failures' => '[]',
        'locked_at' => '2026-07-23 21:00:00',
        'locked_by' => 12,
    ], $overrides), true);
    $record->exists = true;

    return $record;
};

/** @var SldDocumentControl $documentControl */
$documentControl = $app->make(SldDocumentControl::class);
$assert(SldDocumentControl::VERSION === '67L.4-FINAL', 'Document-control baseline must be locked to APP67L.4-FINAL.');

$tampered = $lockedRecord();
$tampered->graph = ['nodes' => [['id' => 'tampered']]];
$blocked = false;
try {
    $tampered->assertImmutableMutationAllowed();
} catch (LogicException) {
    $blocked = true;
}
$assert($blocked, 'Issued revision graph mutation must be blocked at model level.');

$metadataTamper = $lockedRecord();
$metadataTamper->document_metadata = ['version' => 'rewritten'];
$blocked = false;
try {
    $metadataTamper->assertImmutableMutationAllowed();
} catch (LogicException) {
    $blocked = true;
}
$assert($blocked, 'Issued revision metadata mutation must be blocked at model level.');

$auditAppend = $lockedRecord();
$auditAppend->audit_trail = [['event_id' => 'AUD-1', 'event' => 'package_integrity_verified']];
$auditAppend->assertImmutableMutationAllowed();

$auditRewrite = $lockedRecord(['audit_trail' => json_encode([['event_id' => 'AUD-1', 'event' => 'revision_locked']])]);
$auditRewrite->audit_trail = [['event_id' => 'AUD-2', 'event' => 'history_rewritten']];
$blocked = false;
try {
    $auditRewrite->assertImmutableMutationAllowed();
} catch (LogicException) {
    $blocked = true;
}
$assert($blocked, 'Issued revision audit evidence must be append-only.');

$failureBefore = [[
    'event_id' => 'FAIL-1',
    'format' => 'package.zip',
    'message' => 'Renderer unavailable',
    'exception' => RuntimeException::class,
    'at' => '2026-07-23T21:10:00+07:00',
]];
$failureAppend = $lockedRecord(['export_failures' => json_encode($failureBefore)]);
$failureAppend->export_failures = [...$failureBefore, [
    'event_id' => 'FAIL-2',
    'format' => 'package.zip',
    'message' => 'Archive unavailable',
    'exception' => RuntimeException::class,
    'at' => '2026-07-23T21:20:00+07:00',
]];
$failureAppend->assertImmutableMutationAllowed();

$failureRewrite = $lockedRecord(['export_failures' => json_encode($failureBefore)]);
$failureRewrite->export_failures = [[
    'event_id' => 'FAIL-X',
    'format' => 'package.zip',
    'message' => 'Rewritten',
    'exception' => RuntimeException::class,
    'at' => '2026-07-23T21:20:00+07:00',
]];
$blocked = false;
try {
    $failureRewrite->assertImmutableMutationAllowed();
} catch (LogicException) {
    $blocked = true;
}
$assert($blocked, 'Issued revision export-failure evidence must be append-only.');

$exportEvent = [
    'event_id' => 'EXP-1',
    'format' => 'package.zip',
    'filename' => 'NX-R4-IFT.zip',
    'sha256' => str_repeat('1', 64),
    'verification' => ['status' => 'not_verified'],
];
$exportHistory = [
    'version' => 'nexorin-sld-export-history-v3',
    'latest' => $exportEvent,
    'latest_by_format' => ['package.zip' => $exportEvent],
    'events' => [$exportEvent],
];
$exportAppend = $lockedRecord(['export_manifest' => json_encode($exportHistory)]);
$newExportEvent = [
    'event_id' => 'EXP-2',
    'format' => 'pdf',
    'filename' => 'NX-R4-IFT.pdf',
    'sha256' => str_repeat('2', 64),
    'document_control' => ['version' => '67L.4-FINAL'],
    'release_assessment' => ['status' => 'ready'],
];
$exportAppend->export_manifest = $documentControl->appendExportManifest($exportAppend, $newExportEvent);
$exportAppend->last_exported_at = Carbon::parse('2026-07-23T21:30:00+07:00');
$exportAppend->assertImmutableMutationAllowed();

$verificationUpdate = $lockedRecord(['export_manifest' => json_encode($exportHistory)]);
$verificationUpdate->export_manifest = $documentControl->updateExportEvent($verificationUpdate, 'EXP-1', [
    'verification' => [
        'status' => 'verified',
        'verified_at' => '2026-07-23T21:40:00+07:00',
    ],
]);
$verificationUpdate->assertImmutableMutationAllowed();

$exportRewrite = $lockedRecord(['export_manifest' => json_encode($exportHistory)]);
$rewrittenHistory = $exportHistory;
$rewrittenHistory['events'][0]['filename'] = 'REWRITTEN.zip';
$rewrittenHistory['latest']['filename'] = 'REWRITTEN.zip';
$rewrittenHistory['latest_by_format']['package.zip']['filename'] = 'REWRITTEN.zip';
$exportRewrite->export_manifest = $rewrittenHistory;
$blocked = false;
try {
    $exportRewrite->assertImmutableMutationAllowed();
} catch (LogicException) {
    $blocked = true;
}
$assert($blocked, 'Issued revision export history must reject rewritten package evidence.');

$backdatedExport = $lockedRecord(['last_exported_at' => '2026-07-23 21:30:00']);
$backdatedExport->last_exported_at = Carbon::parse('2026-07-23T21:00:00+07:00');
$blocked = false;
try {
    $backdatedExport->assertImmutableMutationAllowed();
} catch (LogicException) {
    $blocked = true;
}
$assert($blocked, 'Issued revision export timestamp must not move backwards.');

$initialLock = $lockedRecord(['locked_at' => null, 'locked_by' => null]);
$initialLock->locked_at = Carbon::parse('2026-07-23T21:00:00+07:00');
$initialLock->locked_by = 12;
$initialLock->issued_at = Carbon::parse('2026-07-23T21:00:00+07:00');
$initialLock->issued_by = 12;
$initialLock->audit_trail = [['event' => 'revision_locked']];
$initialLock->assertImmutableMutationAllowed();

$superseded = $lockedRecord();
$superseded->status = 'superseded';
$superseded->superseded_at = Carbon::parse('2026-07-23T22:00:00+07:00');
$superseded->superseded_by_revision_id = 6705;
$superseded->audit_trail = [['event' => 'revision_superseded']];
$superseded->assertImmutableMutationAllowed();

$supersessionRewrite = $lockedRecord([
    'status' => 'superseded',
    'superseded_at' => '2026-07-23 22:00:00',
    'superseded_by_revision_id' => 6705,
]);
$supersessionRewrite->superseded_by_revision_id = 9999;
$blocked = false;
try {
    $supersessionRewrite->assertImmutableMutationAllowed();
} catch (LogicException) {
    $blocked = true;
}
$assert($blocked, 'Supersession lineage must be immutable after it is recorded.');

$evidence = new ProjectSldRevision([
    'audit_trail' => [[
        'event_id' => 'AUD-1',
        'event' => 'controlled_package_archived',
        'context' => [
            'archive_path' => 'controlled/sld/company-1/project-1/private.zip',
            'exception' => RuntimeException::class,
            'message' => '/private/server/path',
            'package_id' => 'PKG-1',
        ],
        'at' => '2026-07-23T21:00:00+07:00',
    ]],
    'export_manifest' => [
        'version' => 'nexorin-sld-export-history-v3',
        'latest' => [
            'event_id' => 'EXP-1',
            'document_control' => ['internal' => true],
            'release_assessment' => ['internal' => true],
            'archive' => [
                'disk' => 'local',
                'path' => 'controlled/sld/private.zip',
                'filename' => 'package.zip',
                'byte_size' => 120,
                'sha256' => str_repeat('a', 64),
                'stored_at' => '2026-07-23T21:00:00+07:00',
            ],
        ],
        'latest_by_format' => [],
        'events' => [],
    ],
    'export_failures' => [[
        'event_id' => 'FAIL-1',
        'format' => 'package.zip',
        'message' => '/private/server/path failed',
        'exception' => RuntimeException::class,
        'user_id' => 12,
        'user_name' => 'Verifier',
        'at' => '2026-07-23T21:00:00+07:00',
    ]],
]);

$safeAudit = $documentControl->disclosureSafeAuditTrail($evidence);
$assert(data_get($safeAudit, '0.context.archive_path') === null, 'Manifest audit disclosure must remove private archive paths.');
$assert(data_get($safeAudit, '0.context.exception') === null, 'Manifest audit disclosure must remove exception classes.');
$assert(data_get($safeAudit, '0.context.package_id') === 'PKG-1', 'Manifest audit disclosure must retain useful package evidence.');

$safeHistory = $documentControl->disclosureSafeExportHistory($evidence);
$assert(data_get($safeHistory, 'latest.archive.path') === null, 'Manifest export history must remove private storage paths.');
$assert(data_get($safeHistory, 'latest.archive.disk') === null, 'Manifest export history must remove private disk identifiers.');
$assert(data_get($safeHistory, 'latest.archive.filename') === 'package.zip', 'Manifest export history must retain the controlled filename.');
$assert(data_get($safeHistory, 'latest.document_control') === null, 'Manifest export history must avoid duplicating internal document-control snapshots.');

$safeFailures = $documentControl->disclosureSafeExportFailures($evidence);
$assert(data_get($safeFailures, '0.message') === null, 'Manifest export failures must not expose raw exception messages.');
$assert(data_get($safeFailures, '0.exception') === null, 'Manifest export failures must not expose exception classes.');
$assert(data_get($safeFailures, '0.status') === 'failed', 'Manifest export failures must retain a safe failure state.');

$controllerSource = file_get_contents($root.'/app/Http/Controllers/ProjectSldController.php') ?: '';
$assert(str_contains($controllerSource, 'DB::transaction(function () use ($request, $project, $validated): int'), 'Formal issue must run inside a database transaction.');
$assert(str_contains($controllerSource, "->lockForUpdate()\n                ->first();"), 'Formal issue must lock the current revision row.');
$assert(str_contains($controllerSource, 'if ($revision->isReadOnly()) {'), 'Compatibility upgrades must freeze issued and superseded revisions.');
$assert(str_contains($controllerSource, 'disclosureSafeExportHistory($revision)'), 'Downloadable manifests must use disclosure-safe export evidence.');

$documentControlSource = file_get_contents($root.'/app/Services/Sld/SldDocumentControl.php') ?: '';
$assert(str_contains($documentControlSource, '! $revision->isReadOnly()'), 'Release assessment must not advertise transitions for immutable revisions.');
$assert(str_contains($documentControlSource, 'if ($revision->isReadOnly()) {' . "\n            return null;"), 'Immutable revisions must not expose an in-place next issue target.');

$routeExpectations = [
    'companies.projects.sld.show' => 'company.permission:calculation.view,calculation.run,calculation.revise,calculation.verify,calculation.view_own',
    'companies.projects.sld.editor.save' => 'company.permission:calculation.revise',
    'companies.projects.sld.document-control.update' => 'company.permission:calculation.revise',
    'companies.projects.sld.submit' => 'company.permission:calculation.revise',
    'companies.projects.sld.check' => 'company.permission:calculation.verify',
    'companies.projects.sld.approve' => 'company.permission:calculation.verify',
    'companies.projects.sld.issue' => 'company.permission:calculation.verify',
    'companies.projects.sld.packages.verify' => 'company.permission:calculation.verify',
];
foreach ($routeExpectations as $name => $middleware) {
    $route = Route::getRoutes()->getByName($name);
    $assert($route !== null, "Required route {$name} must be registered.");
    $assert(in_array($middleware, $route->gatherMiddleware(), true), "Route {$name} must retain middleware {$middleware}.");
}

$migrationFiles = glob($root.'/database/migrations/*.php') ?: [];
$basenames = array_map('basename', $migrationFiles);
$assert(count($basenames) === count(array_unique($basenames)), 'Migration filenames must be unique.');
foreach ($migrationFiles as $migrationFile) {
    $source = file_get_contents($migrationFile) ?: '';
    $assert(str_contains($source, 'function up('), basename($migrationFile).' must define up().');
    $assert(str_contains($source, 'function down('), basename($migrationFile).' must define down().');
}
$hardeningMigration = file_get_contents($root.'/database/migrations/2026_07_23_190000_harden_project_sld_revision_control.php') ?: '';
foreach (['audit_trail', 'export_failures', 'locked_at', 'locked_by', 'superseded_by_revision_id'] as $column) {
    $assert(str_contains($hardeningMigration, "'{$column}'"), "R3 hardening migration must retain {$column}.");
}

fwrite(STDOUT, "[APP67L.4-FINAL] PASS · version lock, append-only controlled evidence, immutable supersession lineage, atomic issue locking, frozen historical metadata, safe manifest disclosure, route-permission integrity, and migration contract checks\n");
