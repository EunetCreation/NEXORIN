# NEXORIN Page 009 — Edit Customer Patch Manifest

Baseline source: `NEXORIN(20260812-094343).zip`  
Target: V6 Golden `009_customers_edit.png`  
Scope: Page 009 only. Page 010 was not started.

## Identification

- GET: `/companies/{company}/customers/{customer}/edit` → `CustomerController@edit`
- PUT: `/companies/{company}/customers/{customer}` → `CustomerController@update`
- Purpose: edit the same authoritative Customer and Page 008 CRM handoff records created by Create Customer.
- Authoritative records reused: Customer, CustomerProfile, CustomerContact, CustomerOpportunity, CustomerDocument, company-scoped users/memberships/RBAC.
- Database migration added by Page 009: none.

## Implementation

- Replaced the legacy Page 009 surface with a page-local V6 dark enterprise edit experience.
- Prefills Customer and Page 008 CRM data instead of V6 sample values.
- Preserves explicit Parent Company and Opportunity assessments: Not Assessed / No / Yes.
- Contact edits retain stable record IDs; removal is explicit and validated against the complete saved contact set.
- Existing open opportunities retain stable IDs; Page 009 does not silently close/delete them.
- Account owner and portal account remain company-scoped and server-authorized.
- Added optimistic concurrency with `baseline_updated_at` to block stale overwrites.
- Documents are additive private evidence. Existing records are not overwritten; new uploads are stored with SHA-256 metadata and status `recorded`.
- Dirty / saving states are truthful; no “Saved” claim is emitted before successful navigation response.
- Added navigation-away protection, deterministic tab navigation, long-value reflow, keyboard upload interaction, reduced-motion handling, and tablet/mobile reflow.
- V6 Relationship Score, fake pipeline metrics, contracts, milestones, recommendations, and synthetic activities were intentionally not fabricated.

## Changed files

- `app/Http/Controllers/CustomerController.php`
- `app/Http/Requests/UpdateCustomerRequest.php`
- `resources/views/customers/edit.blade.php`
- `public/css/nexorin-customer-edit-v6.css` (new)
- `public/js/nexorin-customer-edit-v6.js` (new)
- `tests/Feature/Page009CustomerEditTest.php` (new)
- `scripts/validate-page009-customer-edit.php` (new)
- `docs/PAGE009_PATCH_MANIFEST.md` (new)
- `docs/checkpoints/PAGE009_CUSTOMER_EDIT_CHECKPOINT.md` (new)

## Verification status in this sandbox

Executed successfully:
- PHP syntax checks for Page 009 PHP/Blade files.
- Node syntax check for Page 009 JavaScript.
- Laravel `route:list` inspection of customer routes/middleware.
- Page 009 static contract validator: 13/13 checks PASS.
- Laravel Blade source rendering using in-memory model relations for visual inspection.
- Browser source-render audit at 1440×900, 2048×900, 1024×900, and 390×844.
- Browser interaction audit: dirty state, contact add, primary-contact exclusivity, parent assessment field gating.
- Browser layout audit: no page horizontal overflow, no nested vertical scroller, no card overflow.
- Reduced-motion computed audit.

Not verified in this sandbox:
- Authenticated HTTP route against a migrated database / a real persisted Customer record.
- PHPUnit Page 009 feature suite: sandbox PHP lacks DOM, mbstring, XML and XMLWriter extensions.
- Page 008 PHPUnit regression suite for the same environment reason.
- Vite production build: uploaded `node_modules` snapshot has a non-executable Vite shim and then a missing Rolldown native optional binding when invoked directly.

These environment limitations must not be interpreted as application PASS or FAIL for the blocked checks.
