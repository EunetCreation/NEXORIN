# Hotfix 10H - Client Proposal Delivery, Expiry, Acceptance, and Audit Trail

## Status
Implemented.

## Purpose
Sprint 10G made approved quotation snapshots capable of producing premium client proposal PDFs. Sprint 10H adds lifecycle governance after PDF generation, so the proposal can be sent, accepted, rejected, cancelled, expired, and audited without exposing internal pricing data.

## Added capabilities

### 1. Delivery workflow
- Approved quotations can be marked as sent only after the current client proposal PDF has been generated.
- Delivery metadata is stored on the quotation:
  - `delivery_method`
  - `sent_to`
  - `delivery_notes`
  - `issued_by`
  - `issued_at`

Supported delivery methods:
- manual
- email
- whatsapp
- download
- portal
- other

### 2. Acceptance workflow
- Sent quotations can be marked as accepted.
- Acceptance stores:
  - `accepted_by`
  - `accepted_at`
  - `acceptance_notes`

### 3. Rejection workflow
- Sent quotations can be marked as rejected.
- Rejection requires a reason and stores:
  - `rejected_by`
  - `rejected_at`
  - `rejection_reason`

### 4. Cancellation workflow
- Draft, internal review, approved, sent, and legacy issued quotations can be cancelled.
- Cancellation stores:
  - `cancelled_by`
  - `cancelled_at`
  - `cancellation_reason`

### 5. Expiry guard
- A non-terminal quotation is considered expired after `valid_until` has passed.
- Expired quotations cannot be sent, accepted, or rejected.
- Expiry can be persisted through lifecycle checks and stores:
  - `expired_at`
  - `expiry_reason`

### 6. Audit trail
A new `quotation_events` table records lifecycle events:
- generated
- revised
- approved
- client_pdf_generated
- sent
- accepted
- rejected
- cancelled
- expired

Each event can store actor, timestamp, status transition, channel, recipient, notes, and metadata.

## New files
- `app/Models/QuotationEvent.php`
- `app/Services/Quotation/QuotationLifecycleService.php`
- `database/migrations/2026_07_07_004000_add_quotation_lifecycle_delivery_and_events.php`
- `tests/Feature/QuotationLifecycleDeliveryTest.php`

## Changed files
- `app/Models/Quotation.php`
- `app/Http/Controllers/QuotationController.php`
- `app/Services/Report/ClientReleaseGate.php`
- `routes/web.php`
- `resources/views/quotations/show.blade.php`
- `resources/views/quotations/index.blade.php`

## Validation target
Run:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate

php artisan test tests/Feature/QuotationLifecycleDeliveryTest.php
php artisan test tests/Feature/ClientProposalPdfBuilderTest.php
php artisan test tests/Feature/QuotationSnapshotGovernanceTest.php
php artisan test tests/Unit/ClientReleaseGateTest.php
php artisan test
```
