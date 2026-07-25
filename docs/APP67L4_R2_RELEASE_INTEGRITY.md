# APP67L.4-R2 Production Workflow UI and Release Integrity

## Scope

APP67L.4-R2 hardens the production drawing workflow without changing the six-sheet engineering topology. It focuses on controlled release decisions, identity separation, revision traceability, and export-package integrity.

## Release-readiness assessment

Every current SLD revision receives a structured release assessment covering:

- source fingerprint currency;
- controlled metadata completeness;
- APP67I electrical-rule status;
- APP67K engineering-controller status;
- technical-calculation verification;
- critical notice closure;
- Material Catalog and BOM traceability;
- independent checker identity;
- independent approver identity;
- engineering-gap closure;
- independent conformity assessment.

The UI shows pass, advisory, and blocked counts for the next controlled transition. IFC and As-Built remain blocked while engineering gaps or conformity assessment remain open.

## Independent authorities

R2 removes the direct `Internal Review -> Approved` transition.

The required sequence is:

`Draft -> Internal Review -> Checked -> Approved -> Issued for Tender`

The drawing preparer cannot record the independent check. The checker cannot approve the same revision. The drawing preparer also cannot approve the same revision. Checker and approval notes are mandatory controlled records.

## Production UI

The document-control workspace now includes:

- title-block source editing;
- exact issue and disposition preview;
- exact PDF, SVG, and package filenames;
- release-gate list;
- six-sheet drawing register;
- Material Catalog and BOM traceability by equipment group;
- comparison against the previous revision;
- latest export checksum;
- controlled ZIP package download.

## Revision comparison

R2 compares the current revision with the immediately preceding revision. It reports:

- controlled metadata changes;
- source fingerprint change;
- node additions and removals;
- circuit additions and removals;
- node and connection counts before and after.

This is a concise engineering delta, not a graphical redline.

## Export manifest v2

The export manifest now records:

- package ID and event ID;
- exact output SHA-256;
- graph SHA-256;
- metadata SHA-256;
- drawing-register SHA-256;
- issue-history SHA-256;
- source fingerprint and source-current flag;
- release-assessment snapshot;
- issue disposition;
- renderer, byte size, actor, timestamp, and six-sheet register.

## Controlled ZIP package

The dependency-free package builder produces a ZIP containing:

- controlled multi-sheet PDF;
- combined editable SVG;
- manifest JSON;
- six-sheet drawing register CSV;
- checksum list;
- package README.

The ZIP writer uses the standard uncompressed ZIP store method and does not require the PHP Zip extension.

## Compatibility

Existing APP67L.4-R1 metadata is upgraded automatically when the revision is opened or exported. Existing people, release state, issue status, and revision lineage are preserved.

No additional database migration is required beyond the APP67L.4-R1 migration.

## Validation

```powershell
npm run test:sld-app67l4:php
npm run test:sld-app67l3:php

$env:NEXORIN_PHP_BINARY = "C:\Users\USER\.config\herd\bin\php84\php.exe"
$env:NEXORIN_SLD_E2E_ONLY = "professional-topology"
npm run test:sld
Remove-Item Env:NEXORIN_SLD_E2E_ONLY
Remove-Item Env:NEXORIN_PHP_BINARY
```

Expected browser result: `6 passed`.
