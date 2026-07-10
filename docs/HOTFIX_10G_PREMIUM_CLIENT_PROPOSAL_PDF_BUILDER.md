# HOTFIX 10G - Premium Client Proposal PDF Builder

## Status
Implemented as a changed-files patch after sprint 10F and 10F-A.

## Purpose
10G promotes quotation governance into a client-facing proposal workflow. The client proposal PDF is generated from the approved quotation snapshot, not from live BOQ rows. This keeps the customer document stable after the quotation has been approved.

## Main changes

### 1. Snapshot-controlled client proposal PDF
- The quotation PDF route now requires an approved client quotation.
- The renderer reads `client_snapshot` when available.
- The PDF remains based on approved snapshot data even if live quotation items change later.
- The generated file uses a revision-aware client proposal filename.

### 2. Internal data protection
The client PDF intentionally excludes internal commercial fields such as:
- unit cost
- line cost
- gross margin
- supplier trace
- price resolver trace
- supplier cost metadata

### 3. PDF generation audit fields
A new migration adds audit fields to `quotations`:
- `client_proposal_pdf_generated_at`
- `client_proposal_pdf_snapshot_hash`
- `client_proposal_pdf_source_fingerprint`
- `client_proposal_pdf_filename`
- `client_proposal_pdf_generated_by`

### 4. Release gate integration
For snapshot-controlled quotations, the Client Release Gate now blocks release approval until a current client proposal PDF has been generated. It also detects stale PDFs when the quotation snapshot hash or source fingerprint no longer matches.

### 5. Test coverage
Added `ClientProposalPdfBuilderTest` to verify:
- draft quotation cannot generate client proposal PDF
- approved quotation can generate PDF
- PDF uses snapshot data, not live edited item data
- internal pricing data does not leak into the PDF
- release gate requires a current client proposal PDF

Updated `QuotationSnapshotGovernanceTest` so approved quotations generate the client proposal PDF before release gate approval checks.

## Notes
This sprint does not replace the internal premium engineering report. It only strengthens the client quotation proposal PDF path so the commercial document is snapshot-safe and client-safe.
