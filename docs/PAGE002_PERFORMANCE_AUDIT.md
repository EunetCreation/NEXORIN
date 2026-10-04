# NEXORIN Page 002 and Runtime Performance Audit

Date: 2026-08-02  
Baseline: `NEXORIN(3).zip` plus the SPV Golden Visual, runtime render, audit matrix, backend findings, and SLD reference archives.

## Baseline status

The official 001–077 audit marks Page 002 (`companies.boms.index`) as **KOREKSI MAYOR** with SSIM diagnostic `0.0662`. Backend status is **SESUAI INTI**: company scoping, permission, BOM directory data, and pagination already existed. The required action was direct reconstruction without inventing routes.

## Root causes of the application feeling heavy

### 1. Artificial navigation blocking — critical

`NexorinEngineeringAction.navigate()` intercepted routine GET links and waited for a decorative orchestration sequence before changing pages. Call sites supplied durations between roughly 760 ms and 1,300 ms. This made every navigation feel slow even when the server response was ready.

Resolution: routine GET navigation now remains native and immediate. Blocking is opt-in only through `blockingNavigation: true` for a genuinely controlled handoff.

### 2. Repeated authorization queries in the global shell — critical

The sidebar and topbar repeatedly called `isSuperAdmin()`, `membershipForCompany()`, `hasCompanyPermission()`, and `hasAnyCompanyPermission()`. Before this patch, every permission check could repeat system-role, membership, role, and permission queries. A normal authenticated shell contains eleven sidebar permission groups plus topbar permission checks, so the source path could exceed fifty authorization-related and shell queries before the Page 002 controller completed.

Resolution:

- `User` memoizes super-admin and company-membership resolution on the authenticated model instance.
- `CompanyMembership` loads active company roles and permission codes once, then evaluates permission checks in memory.
- sidebar and topbar reuse `membershipForCompany()` instead of issuing separate membership queries.
- cache-reset methods remain available for same-request role assignment changes.

This is request-local memoization; it does not change the persisted role/permission model or grant additional access.

### 3. Page 002 asset weight and render work — high

Original Page 002 loaded the general BOM/BOQ stylesheet and script:

- CSS: 97,722 bytes
- JS: 13,608 bytes
- ECharts: 1,121,883 bytes when charts were used

Reconstructed Page 002 uses page-scoped assets:

- CSS: approximately 31.6 KB, a reduction of about 67%
- JS: approximately 9.8 KB, a reduction of about 28%
- ECharts is loaded lazily when analytics is near the viewport or the browser becomes idle
- no continuous page animation was added
- only one scoped backdrop blur remains

### 4. Render-blocking remote font stylesheet — medium

The global layout synchronously loaded Google Fonts. During the previous 77-page runtime audit, external font loading contributed to console/network noise and could delay first paint.

Resolution: font CSS is preloaded and activated asynchronously, with a `noscript` fallback. Local system fonts remain valid fallbacks when the network is unavailable.

### 5. Login-to-workspace overlay dwell — medium

The authenticated workspace transition held the interface for 1,180 ms. It is now capped at 420 ms and becomes effectively immediate under reduced-motion preference.

### 6. Development process overhead — operational

`composer dev` starts the PHP server, queue listener, Pail log stream, and Vite concurrently. That mode is appropriate for active development but consumes more CPU and memory than `php artisan serve` alone. It is not the primary Page 002 latency cause, but it can make a lower-spec development machine feel heavier.

## Page 002 reconstruction

Page 002 now provides real, backend-aligned components:

- five portfolio KPI cards from project, calculation, BOM, and pricing aggregates;
- filters for search, proposed system, project status, and BOM state;
- dense project/package directory with engineering basis, scope composition, line count, value, readiness, lifecycle state, and update time;
- permission-safe links to material catalog and existing engineering/BOM routes;
- category and lifecycle ECharts using actual aggregated BOM data;
- commercial coverage, engineering queue, cost exposure, recent activity, and a calculated health score;
- responsive desktop, tablet, and mobile layouts;
- empty, filtered, pagination, restricted-permission, and chart-fallback states.

No screenshot, static background, fake map, invented route, or decorative placeholder is used.

## Validation completed

- PHP syntax lint for changed PHP and Blade source files: pass.
- Blade compilation plus PHP syntax lint of compiled output: pass.
- JavaScript syntax checks for all changed scripts: pass.
- authorization behavior contract using in-memory Eloquent relations: pass.
- Page 002 static contract validator: pass.
- differential patch generated against the exact uploaded ZIP baseline.

## Runtime validation limitation

The current execution container cannot complete an authenticated Laravel render because PHP lacks `pdo_sqlite`, `pdo_pgsql`, `DOM`, `mbstring`, and XML extensions. The bundled `node_modules` also contains the Windows Rolldown binding but not the Linux native binding required by Vite. Network access is unavailable, so those packages/extensions cannot be installed here.

Therefore Page 002 is **implemented and statically validated, but not yet eligible for a “final/1:1” claim**. The next release-gate step on the user's Windows environment is to run the supplied validator, start Laravel with the existing database, render Page 002 at the standard audit viewport, compare it with the V6 Golden Reference, and perform one visual correction pass.
