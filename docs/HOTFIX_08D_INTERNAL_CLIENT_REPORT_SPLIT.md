# Hotfix 08D - Internal Report and Client Proposal Split

## Purpose

Hotfix 08D separates NEXORIN PDF output into two report modes:

1. **Internal Engineering Report**
   - Full technical and commercial review document.
   - Includes recommendation trace, open review matrix, controlled scope, catalog candidates, optional add-ons, utility responsibility, and detailed BOM/BOQ scope.

2. **Client Proposal PDF**
   - Customer-facing proposal document.
   - Includes selected system, customer-facing commercial value, base BOQ scope, commercial terms, exclusions, and release gate note.
   - Shows an `ENGINEERING REVIEW DRAFT` watermark when the report is not ready for final client release.

## New routes

- `companies.projects.report.client-proposal.preview`
- `companies.projects.report.client-proposal.pdf`

## Renderer behavior

`PremiumProjectReportPdfRenderer` now accepts report mode:

- `internal`
- `client`

Internal mode uses:

```php
pdf.premium.project-report
```

Client mode uses:

```php
pdf.premium.client-proposal
```

## Release gate

The client proposal does not hide release status. If the project is not ready for release, the proposal includes:

```text
ENGINEERING REVIEW DRAFT
```

This protects customer-facing output while still allowing controlled preview by sales or management.

## Client proposal scope

The client proposal includes only customer-facing base scope:

- Base BOQ items
- Included summary-cost items
- Selected add-ons if they are explicitly scoped later

It excludes:

- Catalog candidates
- Optional add-ons not selected
- Utility responsibility
- Owner responsibility
- Not applicable rows

## UI changes

Project cockpit and quotation page now expose both:

- Internal Engineering Report
- Client Proposal PDF
