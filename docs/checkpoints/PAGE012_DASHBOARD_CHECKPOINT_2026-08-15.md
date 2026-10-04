# NEXORIN Page 012 — Dashboard / Command Center Checkpoint

Date: 2026-08-15
Scope: Page 012 only
Route: `GET /dashboard`
Controller: `App\Http\Controllers\DashboardController::__invoke`
View: `resources/views/dashboard.blade.php`
Exact V6 target: `012_dashboard.png`
Implementation-truth baseline: `NEXORIN(20260815-100618).zip`
Baseline SHA-256: `63a83a58a9ee705b7b3d35916a81c821a4c90f10170d4c511e41d8d503ce5c56`

## Checkpoint status

Page 012 has completed the source, semantic, visual, responsive, sparse/rich/restricted-state, and browser-console review available in this sandbox. It is ready for user review as the current Page 012 cumulative patch.

Authenticated database-backed `/dashboard` execution and PHPUnit are **NOT VERIFIED** in this sandbox because the installed PHP runtime has no PDO database drivers and lacks DOM/mbstring/XML/XMLWriter extensions. The actual Laravel Blade/layout was rendered successfully using a DB-independent Page 012 fixture for rich, sparse, restricted-permission, and long-value states. Those fixture renders were used for the genuine Blade/CSS/JS visual review, but they must not be represented as authenticated production-route verification.

Page 013 has not been started.

## Truth corrections

- Removed the misleading `Designed Capacity` aggregate that summed stored inverter AC power across technical records.
- Removed arbitrary `Needs Attention` semantics based on lifecycle stages.
- Reframed BOM/BOQ aggregate as `Recorded Selling Value`; it is not called quotation/sales pipeline value.
- Removed `Live` language; database aggregates are not telemetry.
- Engineering aggregation is company scoped, project-visibility scoped, and separates legacy `Stale`, `Preliminary`, `Detailed`, and `Verified` stored technical records.
- The Page 012 work queue locally treats a stale technical record as requiring recalculation instead of counting it as a completed workflow module. Shared `ProjectCockpit` was not changed.
- Catalog approval is explicitly catalog governance, not overall engineering readiness.
- Current price-reference count is only available when the user has relevant cost/selling/supplier permission.
- Selling-value aggregation follows the existing BOM/quotation customer-facing selling-price visibility boundary.
- Map markers use only stored `latitude` and `longitude`. City/province text is never converted into invented coordinates.
- `Module Coverage` is explicitly stored workflow-module coverage, not construction progress or engineering verification.
- Sparse companies do not receive a large wall of zero KPI cards or empty fake charts.

## Page 012 architecture and query behavior

- Active company resolved through `ActiveCompanyResolver`.
- Projects use `Project::forCompany(...)->current()` and `ownedBy(...)` when the user only has own-project visibility.
- Lifecycle counts are grouped in one aggregate query.
- Work queue is bounded to six projects and eagerly loads the ProjectCockpit relation set to avoid per-row relation queries.
- Map collection is bounded to 24 exact-coordinate projects.
- Technical calculation counts use one aggregate with legacy timestamp-based stale detection; this is not a substitute for the future canonical CalculationDependency graph.
- Catalog approval uses one aggregate over active visible materials.
- Price references are company/effective-date/status scoped.
- Priced BOM/BOQ value is company and visible-project scoped and only includes `pricing_status = priced` records.
- Quotation counts use grouped lifecycle status and company/project visibility scope.

No database migration or new domain table was introduced for Page 012.

## Visual implementation

Page 012 inherits the existing shared V6 token contract and luxury interaction layer. New styling and behavior are page-scoped:

- `public/css/nexorin-dashboard-v6.css`
- `public/js/nexorin-dashboard-v6.js`

The V6 composition is interpreted as a command-center hierarchy rather than copied literally. V6 surfaces that require nonexistent authoritative domains—capacity trend, live energy, regional capacity totals, issues/risk intelligence, approvals, workload, operational health, telemetry, synthetic trends—are intentionally omitted rather than fabricated.

The implemented hierarchy is:

1. Company-context command header.
2. Permission- and data-adaptive authoritative metrics.
3. Exact-coordinate Indonesia project map + project lifecycle distribution.
4. Project-stage and calculation-record analytics + explicit Data Basis boundary.
5. Deterministic project work queue.
6. Actual quotation lifecycle activity when available.
7. Permission-gated authoritative next-workspace actions.

## Responsive / stress review

Browser fixture review covered:

- 1440 × 900 rich state
- 2048 × 900 rich state
- full-page rich state
- sparse company state
- restricted-permission state
- long company/project/customer values
- 1024 px long-value state
- 760 px long-value state
- 390 px long-value/mobile state

Measured document overflow:

- 1440: `clientWidth = 1440`, `scrollWidth = 1440`
- 2048: `clientWidth = 2048`, `scrollWidth = 2048`
- 1024: `clientWidth = 1024`, `scrollWidth = 1024`
- 760: `clientWidth = 760`, `scrollWidth = 760`
- 390: `clientWidth = 390`, `scrollWidth = 390`

At 390 px the work-queue table intentionally has internal horizontal scrolling (`320px` client width / `784px` scroll width); the document itself does not horizontally overflow.

Browser fixture page errors: none.
Browser fixture console errors: none.
ECharts canvases in rich state: four, including the exact-coordinate Indonesia map.

## Validation

Passed:

- PHP syntax: DashboardController
- PHP syntax: DashboardUiTest
- PHP syntax: Page 012 validator
- JavaScript syntax: dashboard JS
- Page 012 static contract: 28/28
- Laravel route discovery: `/dashboard` -> DashboardController, middleware `web`, `auth`, `user.active`
- Actual Laravel Blade rendering in DB-independent fixtures: rich / sparse / restricted / long
- Browser visual/console review of fixture-rendered actual Blade/CSS/JS

Not verified in this sandbox:

- Authenticated DB-backed `/dashboard` request: PHP has no PDO drivers.
- PHPUnit `DashboardUiTest`: runtime lacks DOM, mbstring, XML, XMLWriter.
- Vite production build: uploaded `node_modules` snapshot has unusable Vite executable permissions and missing Rolldown native optional binding. Page 012 CSS/JS are direct public assets and were executed in browser review, but full Vite build remains NOT VERIFIED.

## Locked-page regression boundary

No shared CSS, shared JS, Page 001–011 view, controller, service, route, migration, or model was changed by this patch. Therefore there is no intentional shared-asset regression surface introduced by Page 012. The stale-workflow correction was deliberately kept inside DashboardController instead of altering shared ProjectCockpit behavior.

## Current limitation retained truthfully

The dashboard can only expose the authority implemented in the current source. NEXORIN still lacks the canonical immutable `ProjectRevision / Scenario / CalculationRun / RulePack / ProductRevision / CalculationDependency` authority described by the master architecture. The current stale logic is legacy timestamp based. Therefore Page 012 does not promote stored calculation rows into a new system-level engineering verification score, fleet designed capacity, AI recommendation, or operational-health truth.

## Checkpoint

Page 012 — Dashboard / Command Center: **CURRENT CUMULATIVE BASELINE, VISUALLY SELF-REVIEWED; AUTHENTICATED DB RUNTIME NOT VERIFIED IN SANDBOX**.

Preserve Page 001–011. Do not begin Page 013 until the user explicitly says `lanjut`.
