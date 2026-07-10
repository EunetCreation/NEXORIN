# HOTFIX 09C — Release Approval UX and Route Guard

## Purpose

Hotfix 09C hardens the client release workflow after the release gate engine and stale approval checks were added.

The goal is to make the approval state obvious in the Project Cockpit and to prevent the final client proposal PDF from being downloaded before the Client Release Gate is ready.

## Main Changes

### 1. Approval role guard

Only trusted internal release roles may approve or revoke final client release:

- Super Admin
- Company Admin
- Engineer

Sales and customer users may still view permitted draft/preview content according to their normal permissions, but they cannot approve or revoke client release.

### 2. Draft vs final client proposal routes

The client proposal workflow now separates three actions:

- Client Proposal Preview
- Draft Proposal PDF
- Final Client Proposal PDF

The existing draft PDF route remains available and keeps the draft watermark while the gate is not ready.

The new final route is blocked unless the Client Release Gate evaluates to `Ready for Client Release`.

### 3. Watermark state labels

Client Proposal watermark text now follows the gate status:

- `ENGINEERING REVIEW DRAFT` when the proposal is still under review.
- `STALE APPROVAL DRAFT` when project or quotation data changed after approval.
- `RELEASE REVOKED DRAFT` when a previous approval was revoked.
- No watermark when the approval is current and the gate is ready.

### 4. Project Cockpit UX

The Client Release Gate card now shows:

- Release Status
- Approval Status
- Engineering issue count
- Commercial issue count
- active approval summary
- approval history
- locked approval message when issues remain
- final PDF locked/download action state

### 5. Revoke reason

Revoking approval now requires a reason so the approval audit trail is clearer.

### 6. Feature test coverage

Added browser-level release gate flow tests for:

- viewing the release gate card,
- engineer approval,
- sales approval denial,
- customer approval denial,
- stale approval visibility,
- final PDF route guard,
- draft preview watermark,
- final PDF download after current approval using a mocked renderer.

## Changed Files

- `app/Services/Report/ClientReleaseGate.php`
- `app/Services/Report/ProjectReportDataBuilder.php`
- `app/Http/Controllers/ProjectReportController.php`
- `app/Http/Controllers/ProjectController.php`
- `routes/web.php`
- `resources/views/projects/show.blade.php`
- `tests/Feature/ClientReleaseGateFlowTest.php`

## Test Notes

Run locally:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan test
```

Sandbox validation performed:

- PHP syntax check passed for changed PHP/Blade files.
- Route list shows the new final client proposal route.

The sandbox PHP runtime does not include the DOM/XML extensions required by PHPUnit, so full PHPUnit execution must be performed in the local development environment.
