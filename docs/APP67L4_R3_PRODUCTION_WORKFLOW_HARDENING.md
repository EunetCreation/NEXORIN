# APP67L.4-R3 Production Workflow Hardening

## Scope

APP67L.4-R3 hardens the controlled drawing workflow introduced in R1 and R2. It does not change the six-sheet electrical topology. The release focuses on immutable issued records, safe revision lineage, signed audit evidence, package retention, checksum verification, and controlled recovery when export fails.

## Immutable issued revisions

A revision becomes read-only after formal issue. The lock records:

- lock timestamp;
- locking user;
- issue status and issue timestamp;
- immutable manifest and audit evidence.

Issued or superseded revisions cannot be edited through the SLD editor or document-control form. Engineering changes require creation of a new draft revision.

## Revision supersession

Generating a new revision is executed inside a database transaction that locks the project row and the current revision row. The active revision is marked `superseded`, linked to the replacement revision, and preserved as a read-only historical record.

The revision register exposes:

- revision number and status;
- issue status;
- generated, checked, approved, issued, locked, and superseded timestamps;
- replacement revision;
- package history.

Only the latest non-superseded revision is treated as current.

## Audit trail

R3 records structured events for important controlled actions, including:

- revision creation and supersession;
- document metadata changes with before-and-after values;
- workflow transitions;
- independent check and approval;
- formal issue and revision locking;
- PDF, SVG, and package export;
- package archival and checksum verification;
- package export failure.

Each event records an event ID, action, actor, timestamp, changes, and supporting context. The export manifest includes a hash of the audit trail so the package can be traced to the controlled record state used during export.

## Independent authority enforcement

Checker and approver actions require an active account with `calculation.verify` authority in the company, unless the actor is a super administrator.

Separation rules are enforced:

- the preparer cannot check the same revision;
- the preparer cannot approve the same revision;
- the checker cannot approve the same revision;
- approval requires a recorded independent check.

These controls supplement Laravel authorization. They do not replace the organization’s formal competency and delegation policy.

## Detailed revision comparison

The R3 comparison engine reports changes in:

- controlled metadata;
- source fingerprint;
- nodes and connections;
- cable schedule;
- protection schedule;
- equipment and Material Catalog traceability;
- engineering summary counts.

The comparison is a structured engineering delta, not a graphical redline.

## Private controlled-package archive

Generated ZIP packages are stored on Laravel’s private `local` disk under a company, project, revision, and export-event path. The archive record stores:

- disk and private path;
- filename;
- byte size;
- SHA-256;
- storage timestamp.

Archived packages can be re-downloaded through an authorized controller route. Storage paths are not exposed as public URLs, and archive reads reject alternate disks and traversal paths.

## Package verification

An authorized verifier can recompute the SHA-256 of an archived package and compare it with the export manifest. Verification returns one of:

- `verified`: checksum matches;
- `mismatch`: file exists but checksum differs;
- `missing`: archived file cannot be found.

The result, verifier, timestamp, expected checksum, and actual checksum are added to the export event and audit trail.

## Export-failure recovery

A package-generation exception does not advance the issue status and does not unlock or rewrite the controlled revision. The failure is recorded separately with:

- output format;
- exception class;
- safe error message;
- actor;
- timestamp.

The exception is rethrown so the request fails visibly, while the revision and prior package history remain intact. A later export can be retried from the same controlled revision.

## Database migration

Run the R3 migration after applying the patch:

```powershell
php artisan migrate
```

The migration adds:

- `audit_trail`;
- `export_failures`;
- `locked_at`;
- `locked_by`;
- `superseded_by_revision_id`.

## Validation

Deterministic validation:

```powershell
npm run test:sld-app67l4:php
npm run test:sld-app67l3:php
```

Professional-topology browser regression:

```powershell
$env:NEXORIN_PHP_BINARY = "C:\Users\USER\.config\herd\bin\php84\php.exe"
$env:NEXORIN_SLD_E2E_ONLY = "professional-topology"
npm run test:sld
Remove-Item Env:NEXORIN_SLD_E2E_ONLY
Remove-Item Env:NEXORIN_PHP_BINARY
```

Expected local browser result: `6 passed`.

When Playwright Chromium is not installed, install it once with:

```powershell
npx playwright install chromium
```

## Release status

APP67L.4-R3 protects the integrity of controlled internal-review and tender outputs. It does not itself close open engineering gaps, approve utility interconnection, or establish IEC certification. Construction issue and conformity status remain governed by the controlled release gates and independent assessment.
