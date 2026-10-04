# NEXORIN Page 010 — Customer Directory / Customer Intelligence Patch Manifest

Baseline source: `NEXORIN(20260812-132443).zip`
Visual target: V6 Golden `010_customers_index.png`
Scope: Page 010 only. Page 011 was not started.

## Identification

- GET: `/companies/{company}/customers` → `CustomerController@index` → `customers.index`.
- Purpose: company-scoped authoritative Customer Directory / Customer Intelligence surface.
- Authoritative records reused: `Customer`, `CustomerProfile`, `CustomerContact`, `CustomerOpportunity`, `CustomerDocument`, `Project`, portal `User`, company memberships and RBAC.
- Relation to Page 008/009: Page 010 reads the same customer truth created on Page 008 and edited on Page 009. No duplicate CRM domain was introduced.
- Database migration added by Page 010: none.

## Truthful V6 adaptation

Implemented only from data already authoritative in the current source:
- total / active / archived customers;
- customer type;
- relationship-stage distribution;
- profile / owner / primary-contact / portal / evidence coverage;
- linked open-project exposure and project-status context;
- open opportunity records and per-currency estimated-value totals;
- exact linked-project coordinate map where authoritative coordinates exist;
- region/province aggregation as recorded context;
- recent customer record updates, explicitly labeled as record updates rather than CRM activity.

Intentionally not implemented because no authoritative domain/methodology exists:
- Won YTD;
- conversion rate;
- relationship score or score distribution;
- CRM activity heatmap;
- contracts / expiring contracts;
- follow-up tasks;
- fake V6 sample customer or KPI values;
- invented customer map coordinates.

Mixed-currency opportunity values are never silently summed across currencies.

## Query / performance changes

- Directory remains server-side paginated at 12 rows.
- Added server-side search/filter/sort over company-scoped Customer data and available CRM relations.
- Uses eager loading, `withCount`, grouped aggregates, and bounded latest-record queries.
- No per-row opportunity/document/project queries were introduced.
- Map points are bounded to the 500 most recently updated projects with exact recorded coordinates.
- No new database indexes were added; Page 008/009 already established the relevant CRM relationship/index foundation and no runtime query plan was available in this sandbox to justify speculative indexing.

## Security / tenancy

- Route middleware remains `web`, `auth`, `user.active`, `company.access`, `company.permission:customer.view`.
- All directory and aggregate reads remain company-scoped.
- Edit/archive controls remain permission-aware in the UI, while authorization remains server-side.
- Archive uses the existing server workflow and preserves the deterministic active-project blocker.

## Changed files

- `app/Http/Controllers/CustomerController.php`
- `resources/views/customers/index.blade.php`
- `public/css/nexorin-customers-index-v6.css` (new)
- `public/js/nexorin-customers-index-v6.js` (new)
- `tests/Feature/Page010CustomerIndexTest.php` (new)
- `scripts/validate-page010-customer-index.php` (new)
- `docs/PAGE010_PATCH_MANIFEST.md` (new)
- `docs/checkpoints/PAGE010_CUSTOMER_INDEX_CHECKPOINT.md` (new)

## Executed verification in this sandbox

PASS:
- PHP syntax: `CustomerController.php`.
- PHP/Blade source syntax: `customers/index.blade.php`.
- Blade compiler `compileString` output syntax.
- Node syntax: `nexorin-customers-index-v6.js`.
- PHP syntax: Page 010 feature test and static validator.
- Page 010 static contract validator: 14/14 checks.
- Laravel route-list inspection confirms Page 010 route/action/middleware.
- Supplemental source-render visual QA at 1440×900, 2048×900, 1024×900, and 390×844: no page-level horizontal overflow; wide directory uses controlled horizontal scrolling; charts and exact-coordinate map remain contained; right rail uses content height; lower geography section spans full content width.

NOT VERIFIED in this sandbox:
- Genuine authenticated Laravel Page 010 BEFORE/AFTER screenshots.
- Database-backed HTTP Page 010 route.
- Page 010 PHPUnit feature execution.
- Page 008/009 PHPUnit regression execution.
- Database query plans / runtime N+1 profiler.
- Browser console/network against an authenticated Laravel runtime.

Environment blockers:
- system PHP exposes PDO core but no SQLite/PostgreSQL PDO driver, so Laravel database/session access returns `could not find driver`;
- system PHP also lacks DOM/mbstring/XML/XMLWriter required by the normal Artisan terminal/PHPUnit path;
- `npm run build` cannot execute the uploaded Vite shim (`Permission denied`), while direct Vite invocation fails because the uploaded `node_modules` snapshot is missing the Rolldown native optional binding. Production build is therefore NOT VERIFIED in this sandbox.

Supplemental source-render screenshots are deliberately watermarked `NOT RUNTIME DATA` and must not be treated as genuine Laravel runtime evidence.

## Locked-page regression scope

No shared CSS/JS component, database schema, or Customer create/edit/update implementation was changed. Only `CustomerController@index` was modified in the shared controller. The Page 009 static validator still reports the same two stale failures that are present in the uploaded baseline; this is recorded in the visual-review evidence and is not a Page 010 regression.
