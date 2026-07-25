# APP67L.4-R2 Production Drawing Workflow

## Purpose

APP67L.4 converts the stable APP67L.3 six-sheet internal-review package into a controlled project document workflow. Drawing content remains model-linked. Project identity, client, site, company, drawing numbers, revision, issue state, authorities, material references, release gates, and export lineage become project-specific and auditable.

This milestone does not claim construction readiness or IEC certification. IFC and As-Built remain blocked while engineering gaps or independent conformity assessment are open.

## Controlled sequence

The required workflow is:

`Draft -> Internal Review -> Checked -> Approved -> Issued for Tender`

R2 removes direct approval from Internal Review. The drawing preparer cannot act as checker. The checker cannot approve the same revision. The drawing preparer also cannot approve it. Checker, approver, and issue authority records require explicit confirmation and controlled notes.

## Release-readiness gates

The current revision is assessed against:

- source fingerprint currency;
- metadata completeness;
- APP67I electrical-rule status;
- APP67K engineering-controller status;
- technical-calculation verification;
- critical notice closure;
- Material Catalog and BOM traceability;
- independent checker and approver identity;
- engineering-gap closure;
- independent conformity assessment.

The UI shows pass, advisory, and blocked counts for the next transition. Server-side transition validation uses the same gates.

## Production workspace

The SLD page now provides:

- controlled metadata editor;
- title-block and disposition preview;
- exact PDF, SVG, manifest, and package filenames;
- release-gate status list;
- deterministic six-sheet drawing register;
- Material Catalog and BOM traceability summary;
- previous-revision metadata and topology comparison;
- latest checksum-backed export record;
- controlled ZIP package download.

## Project-specific drawing register

The six-sheet register is deterministic:

1. Single-line diagram, on-grid PV system
2. Zero-export metering, control, and export-trip schematic
3. MPPT, string, and equipment schedule
4. Cable and interconnection schedule, 1 of 2
5. Cable and interconnection schedule, 2 of 2
6. Protection, metering and engineering data gaps

Example prefix `NX-2026-0042-E-SLD` produces drawing numbers `NX-2026-0042-E-SLD-001` through `NX-2026-0042-E-SLD-006`.

## Export manifest v2

Every PDF, SVG, or controlled package export records:

- package and event identifiers;
- issue status, issue code, and disposition;
- exact output SHA-256;
- graph SHA-256;
- document metadata SHA-256;
- drawing-register SHA-256;
- issue-history SHA-256;
- source fingerprint and source-current status;
- release-assessment snapshot;
- renderer, byte size, actor, timestamp, and sheet register.

## Controlled ZIP package

The package download contains:

- controlled multi-sheet PDF;
- combined editable SVG;
- manifest JSON;
- drawing-register CSV;
- SHA-256 checksum list;
- package README.

The ZIP builder uses the standard uncompressed ZIP store method. It does not require the PHP Zip extension.

## Material traceability

The document-control service groups linked BOM items into:

- PV module;
- inverter;
- DC protection;
- AC protection;
- metering and control.

Material, brand, model, datasheet, quantity, and unit identifiers are retained when available. Missing selections remain `TBC`. No product data is invented.

## Revision comparison

R2 compares the current revision with the immediately preceding revision. It reports controlled metadata changes, source fingerprint changes, node additions or removals, and circuit additions or removals. This is an engineering delta summary, not a graphical redline.

## Compatibility and migration

Existing APP67L.4-R1 metadata is upgraded automatically when a revision is opened or exported. Existing people, release state, issue status, and lineage are preserved.

No new database migration is required beyond:

`2026_07_23_170000_add_production_document_control_to_project_sld_revisions.php`

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
