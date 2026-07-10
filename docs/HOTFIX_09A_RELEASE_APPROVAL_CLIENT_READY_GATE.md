# Hotfix 09A: Release Approval and Client-Ready Gate

## Purpose

Hotfix 09A turns the Client Proposal PDF from a simple draft output into an approval-controlled customer release document.

The system now distinguishes between:

- Internal Engineering Report
- Client Proposal Preview Draft
- Ready for Client Release

## Main Changes

### 1. Client release approval table

A new table stores explicit client release approvals:

- `project_release_approvals`

Each approval stores:

- company and project
- approval status
- approval hash
- gate snapshot
- gate result
- approval notes
- approver and timestamp
- revocation user, timestamp, and reason

### 2. Release gate service

New service:

- `app/Services/Report/ClientReleaseGate.php`

It checks whether a project can be released to the client.

The gate blocks release when:

- critical or warning notices are open
- technical calculation is not engineer verified
- BOQ pricing is not ready
- BOQ grand price is zero or below material price
- financial analysis is missing or stale
- quotation is missing, draft, or stale
- approval is missing or stale

### 3. Explicit approval workflow

New routes:

- `companies.projects.report.client-release.approve`
- `companies.projects.report.client-release.revoke`

Approval requires existing permissions:

- `quotation.send` or `report.export`

### 4. Project cockpit UI

The project cockpit now shows a Client Release Gate card with:

- current release status
- gate issues
- approval status
- approve/revoke actions

### 5. Client Proposal behavior

The Client Proposal PDF keeps the draft watermark unless the gate status is:

- `Ready for Client Release`

If project data changes after approval, the approval becomes stale because the stored gate hash no longer matches the current release snapshot.

## Required command after installation

This hotfix adds a migration. Run:

```bash
php artisan migrate
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
composer dump-autoload
```

## Test Flow

1. Regenerate technical calculation.
2. Verify technical calculation.
3. Regenerate BOM/BOQ.
4. Apply pricing.
5. Regenerate financial analysis.
6. Regenerate quotation.
7. Issue quotation.
8. Open project cockpit.
9. Review Client Release Gate.
10. Approve Client Release if all gates pass.
11. Generate Client Proposal PDF.

Expected result:

- Before approval: Client Proposal has `ENGINEERING REVIEW DRAFT`.
- After valid approval: Client Proposal removes the draft watermark and shows `Ready for Client Release`.
- If data changes after approval: gate becomes stale and draft watermark returns.
