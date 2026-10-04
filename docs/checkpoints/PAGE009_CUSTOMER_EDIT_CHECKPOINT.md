# Page 009 — Edit Customer Checkpoint

Date: 2026-08-12  
Baseline: `NEXORIN(20260812-094343).zip`  
V6 target: `009_customers_edit.png`

## Current checkpoint status

**IMPLEMENTED / REVIEW READY — NOT FINAL, NOT RUNTIME VERIFIED IN THIS SANDBOX.**

Page 009 now edits the same authoritative Customer domain established by Page 008. No duplicate Customer store, no Page 009 migration, and no fake CRM KPI/activity domain were introduced.

The update path uses server-side validation and company scope, optimistic concurrency, stable contacts/opportunities, explicit parent/opportunity assessment semantics, account-owner/portal membership constraints, additive private document evidence, and review acknowledgement.

Frontend work is page-local and does not change the locked Page 001–008 CSS/JS surfaces. The shared `CustomerController` was extended only for Page 009 edit/update behavior; Page 008 create/store logic was not rewritten.

## Visual checkpoint

- Dark V6-aligned Page 009 hierarchy and proportions.
- Functional top actions + deterministic section tabs.
- Customer profile and actual record counts, not V6 samples.
- Long customer names, URLs, email addresses, contact names/titles and opportunity names reflow without card/page overflow.
- Compact truthful right rail: record readiness, ownership/access, commercial handoff, documents, saved change context.
- No giant equal-height forced card architecture or nested-scroll trap.
- Existing documents are shown only from backend data; upload adds recorded evidence.
- Tablet/mobile reflow is intentional; desktop primary remains 1440×900 and 2048×900.

## Required local confirmation before lock/final

1. Run migrations in the actual NEXORIN local PostgreSQL environment if not already applied.
2. Run `php artisan test --filter=Page009CustomerEditTest`.
3. Re-run `php artisan test --filter=Page008CustomerCreateTest` plus the existing customer management regression suite.
4. Log in as an authorized company user, open a real existing customer edit route, and verify prefilled data.
5. Save a controlled real/customer test change and verify profile/contact/opportunity identity, dirty/saving/saved states and additive document behavior.
6. Capture genuine authenticated runtime screenshots at 1440×900, 2048×900 and full-page.
7. Confirm browser console/network are clean in the real route.

Do not call Page 009 FINAL/VERIFIED until those runtime checks complete successfully.

STOP after Page 009. Do not start Page 010 until the user explicitly says “lanjut” or equivalent.
