# APP67L.4-FINAL Release Notes

## Release identity

APP67L.4-FINAL closes the production drawing workflow built through APP67L.4-R1, R2, R3, and R3-HF1. It preserves the validated six-sheet professional topology and adds final controls around immutability, atomic issue, evidence disclosure, route integrity, and release verification.

This release remains a certification candidate. It does not claim IEC certification, utility approval, construction approval, or independent conformity assessment.

## Consolidated delivery

### APP67L.4-R1

- Project-specific production drawing metadata.
- Deterministic six-sheet drawing register.
- Draft, review, approval, and issue workflow foundation.
- Project, client, site, organization, revision, and issue identity.

### APP67L.4-R2

- Required sequence: Draft, Internal Review, Checked, Approved, Issued for Tender.
- Independent checker and approver separation.
- Release-readiness gates.
- Material Catalog and BOM traceability.
- Revision comparison.
- Manifest v2 and controlled ZIP package.

### APP67L.4-R3 and R3-HF1

- Immutable issued revisions.
- Transactional revision supersession.
- Structured audit trail.
- Detailed cable, protection, equipment, source, and topology comparison.
- Private package archive.
- SHA-256 verification and tamper detection.
- Export-failure evidence and safe retry behavior.
- Controller and cable-trace hardening retained from the validated R3-HF1 baseline.

### APP67L.4-FINAL closure

- Product and drawing-control identity locked to `67L.4-FINAL`.
- Eloquent model guard blocks changes to issued or superseded engineering content.
- Audit and export-failure histories allow only one new event per save. Export history allows only a new export event or a checksum-verification update. Supersession lineage cannot be rewritten after it is recorded.
- Formal issue now executes inside a database transaction with row locking.
- Issued and superseded historical records no longer receive automatic metadata rewrites after application upgrades.
- Release assessment no longer advertises in-place transitions for immutable issued records.
- Downloadable manifest evidence removes private storage paths, disk identifiers, exception classes, and raw server error messages.
- Route and permission contracts receive deterministic closure checks.
- A safe Windows validator uses an isolated SQLite database for fresh migration and seeding.

## Compatibility

No new database migration is introduced by APP67L.4-FINAL. Apply the existing migrations through:

- `2026_07_17_010000_create_project_sld_revisions_table.php`
- `2026_07_23_170000_add_production_document_control_to_project_sld_revisions.php`
- `2026_07_23_190000_harden_project_sld_revision_control.php`

Unlocked draft records upgrade to the FINAL document-control contract when opened. Issued and superseded records remain frozen under their original stored metadata and hashes.

## Release boundary

Issued for Tender remains the highest generally available issue state while engineering gaps or independent conformity assessment remain open. Issued for Construction and As-Built remain blocked by the controlled gates.
