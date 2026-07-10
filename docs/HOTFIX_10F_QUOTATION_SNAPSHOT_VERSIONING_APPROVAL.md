# HOTFIX 10F - Quotation Snapshot, Versioning, Approval, and Client/Internal Proposal Separation

## Status
Implemented as the next commercial governance layer after 10E.

## Purpose
10E made BOQ pricing client quotation ready. 10F locks the commercial result into a quotation snapshot so future changes to price books, BOQ, tax, margin, or financial analysis do not silently change an already prepared offer.

## Main Changes

### 1. Quotation snapshot fields
The quotation record now stores:

- `version_label`
- `snapshot_hash`
- `source_fingerprint`
- `client_snapshot`
- `internal_snapshot`
- `approved_by`
- `approved_at`
- `approval_notes`

### 2. Quotation version history
A new `quotation_versions` table stores every generated revision.

Each version records:

- quotation number and revision number
- version label
- status
- validity date
- snapshot hash
- source fingerprint
- customer, project, technical, financial, commercial, terms, warning, client, internal, and item snapshots

### 3. Client and internal separation
The client snapshot contains selling information only. It excludes:

- unit cost
- line cost
- gross margin
- supplier trace
- source pricing audit details

The internal snapshot keeps the full audit trail for company users.

### 4. Approval workflow
A quotation can now move through:

- `draft`
- `approved`
- `sent`
- `accepted`
- `rejected`
- `expired`

Legacy `issued` status remains accepted by release checks for backward compatibility.

### 5. Send guard
A quotation cannot be sent while still in draft. It must be approved first.

### 6. Release gate integration
Client release gate now blocks when:

- quotation is missing
- quotation is not approved
- quotation has expired
- quotation source fingerprint is stale
- quotation grand total no longer matches BOQ

### 7. Test coverage
Added `QuotationSnapshotGovernanceTest` for:

- client-safe snapshot creation
- internal snapshot audit data
- revision record creation
- snapshot immutability after BOQ changes
- approval required before sending
- release gate blocking stale quotation snapshots

## Notes
No existing quotation columns were removed. The old `issued` status remains valid for existing tests and historical records.
