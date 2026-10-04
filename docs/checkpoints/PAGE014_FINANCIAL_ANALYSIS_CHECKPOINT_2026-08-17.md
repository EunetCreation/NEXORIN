# NEXORIN Page 014 — Financial Analyses Show / Financial Engineering Workspace Checkpoint

Date: 2026-08-17
Scope: Page 014 only
Baseline: `NEXORIN(20260817-025151).zip`
Baseline SHA-256: `3fa1f0e4f9ff62ae35a80cc02b9fdb8780b56de0b5d5b88d8ef7cdf6562154c5`
Exact V6 target: `014_financial_analyses_show.png`

## Status

Page 014 has completed source/domain audit, shared financial-basis consolidation, static validation, Blade rendering, visual self-review, responsive/stress rendering, and Page 013 regression checks available in this sandbox.

Authenticated database-backed Laravel route execution remains **NOT VERIFIED** because the sandbox PHP runtime has no PDO database driver. PHPUnit remains **NOT VERIFIED** because DOM, mbstring, XML and XMLWriter extensions are unavailable. Actual Laravel Blade/layout/CSS/JavaScript were rendered with database-independent in-memory fixture models for the documented Page 014 states; those renders are valid visual/runtime evidence for the view layer but are not a substitute for authenticated production-route verification.

Page 015 has not been started.

## Route and authorization mapping verified

- `GET /companies/{company}/projects/{project}/financial-analysis`
  - route: `companies.financial-analyses.show`
  - action: `FinancialAnalysisController@show`
  - middleware includes company access plus the existing calculation/selling-price/quotation view/create/update permission set.
- `POST /companies/{company}/projects/{project}/financial-analysis/calculate`
  - route: `companies.financial-analyses.calculate`
  - action: `FinancialAnalysisController@calculate`
  - middleware includes company access plus `price.selling.update,quotation.create`.
- Controller-level project authorization remains server-side through the existing project resource authorization contract, including company matching and own-project restrictions.

## Financial domain truth retained

Current implementation remains a single saved `FinancialAnalysis` record per project. Recalculation updates that current record; the page does not claim immutable run history, scenario history, or revision history.

Current finance remains IDR-based and consumes a Technical Calculation annual-energy scalar plus explicit financial assumptions. The page does not pretend to implement future time-step self-consumption/export, debt, WACC, tax, P50/P90, sensitivity, Monte Carlo, or bankability workflows.

Outputs are descriptive stored model outputs only: Analysis CAPEX, modeled first-year benefit, NPV, IRR, Simple Payback, LCOE, lifetime ROI where available, annual/cumulative cashflows, and stored warnings. No investment verdict is inferred.

## Shared financial-basis authority

A new `FinancialBasisService` is the shared per-project financial prerequisite authority used by Page 013 row semantics and Page 014 show/calculate flows.

The shared basis verifies:

- project is current/non-archived;
- Technical Calculation exists and belongs to the project/company;
- Technical Calculation has stored results;
- Technical Calculation is not stale;
- annual energy from Technical Calculation is positive;
- BOQ exists for the project/company;
- BOQ is linked to the intended Technical Calculation where the current schema supports that relation;
- BOQ is priced;
- BOQ commercial value is positive;
- priced timestamp exists;
- current finance path is IDR.

Human-readable blocker states include Technical Calculation Required, Technical Basis Stale, Annual Energy Required, BOQ Required, BOQ Pricing Required, BOQ Source Review Required, BOQ Pricing Review Required, Project Archived, and Financial Basis Ready.

Page 013 aggregate statistics retain a bounded SQL aggregate rather than instantiating the service per registry row; its SQL predicates were aligned to the shared authority to avoid an N+1 regression. Page 013 row truth uses the shared service.

## Source freshness

`FinancialAnalysis::sourceFreshness()` now treats incomplete/legacy source snapshots as `Source Review Required`, not confidently current.

Deterministic comparison covers:

- project identity;
- Technical Calculation ID, project/company scope, stored status and calculated timestamp;
- current Technical Calculation stale state;
- BOQ ID, project/company scope, pricing status and priced timestamp;
- BOQ linkage to the Technical Calculation;
- BOQ grand price;
- IDR finance currency boundary.

Displayed states are Current Sources, Source Changed, Source Review Required, or Not Calculated. Stored stale results may remain visible but are explicitly qualified and are not styled as current approval.

## CAPEX semantics

The page distinguishes:

- Priced BOQ reference;
- Analysis CAPEX assumption;
- Uses priced BOQ;
- Custom analysis CAPEX.

A custom Analysis CAPEX changes only the financial model input. It does not mutate or imply a change to the priced BOQ and is not called approved because no controlled commercial override workflow exists for this field.

## Assumptions and defaults

Saved assumptions are always restored when a saved analysis exists; restoration no longer depends on a non-empty summary.

Default/saved/source badges distinguish assumptions from authoritative source values. Discount rate is described only as a discounted-cashflow assumption, not WACC or hurdle rate. Self-consumption is an annual model assumption, not measured time-step energy flow. Export credit is a commercial assumption. Energy degradation is not described as product warranty degradation. Replacement year/cost is not described as predicted failure or scheduled maintenance.

Current engine behavior retained and disclosed: annual O&M is derived from Analysis CAPEX and its yearly escalation uses the existing tariff-escalation assumption because no independent O&M-escalation input exists in the current model.

## Annual energy and engine behavior

Financial calculation now consumes annual energy only from the current Technical Calculation result. The former BOQ-summary annual-energy fallback was removed to prevent a second finance energy truth.

The old unsupported warning that described a Technical Calculation as not “engineer verified” was removed. Stored engine warnings are presented in human-readable form without creating an engineering approval gate.

No final finance metric is calculated in JavaScript or Blade. POST calculation reauthorizes, rechecks financial basis, validates assumptions through the existing request, runs the server-side engine, stores warnings/source snapshot/cashflows, and redirects.

## Visual composition

The old light “investment signal” cockpit was replaced with a Page 014-scoped dark V6 financial engineering workspace that inherits `nexorin-v6-token-contract.css` and the existing luxury interaction grammar.

Implemented hierarchy:

1. Project / analysis command header.
2. Continuous financial-output band when stored analysis exists.
3. Stored cashflow analytical surface.
4. Explicit financial assumptions with source badges.
5. Independent Financial Basis / source freshness / warnings / boundaries rail.
6. Full-width stored cashflow schedule.
7. Output interpretation / model-boundary surface.

Removed/rejected concepts include Investment Signal, Favorable/Unfavorable Economics, hurdle score, 2/3 or 3/3 hurdles, LCOE-vs-tariff investment pass/fail, customer-release readiness, forecast confidence, scenario tabs, budget/actual/committed/EAC/approval-queue content, and AI finance advice.

## Cashflow chart

Apache ECharts renders only stored `cashflows` from the saved financial analysis:

- Annual Net Cashflow as bars;
- Cumulative Cashflow as a non-smoothed line;
- optional replacement-year marker when the saved assumptions define one.

The chart does not synthesize missing years, forecast additional series, calculate metrics client-side, or create scenarios. Tooltip uses dark enterprise styling and explicit IDR values. Duplicate initialization is guarded and resize handling uses `ResizeObserver`.

## Query / payload audit

Page 014 no longer eager-loads BOM items, Technical Calculation calculator/verifier users, or other relations not required by the workspace. No query exists in Blade loops and GET does not run the finance engine or mutate records.

Cashflow chart serialization is limited to year, annual net cashflow, and cumulative cashflow from the stored model. The current model horizon is bounded by request validation to 40 years.

## Responsive / visual evidence

Final main-state measurements:

- 1440×900: document `clientWidth = 1440`, `scrollWidth = 1440`; no page-level horizontal overflow; no browser/console errors captured.
- 2048×900: document `clientWidth = 2048`, `scrollWidth = 2048`; no page-level horizontal overflow; no browser/console errors captured.
- 768 px: document width remains 768/768; cashflow table uses controlled internal scrolling where necessary.
- 390 px: document width remains 390/390; cashflow table uses controlled internal scrolling; command actions and metric band reflow vertically.

State rendering covered:

- no Technical Calculation;
- zero/missing annual energy;
- no BOQ;
- incorrectly linked BOQ;
- unpriced BOQ;
- priced BOQ without priced timestamp;
- valid basis/no analysis;
- current analysis;
- stale technical source;
- stale BOQ source;
- negative NPV;
- IRR unavailable;
- payback not reached;
- custom CAPEX;
- export credit assumption;
- read-only user;
- long values;
- archived project;
- legacy locked analysis;
- validation error.

## Page 013 regression

Page 013 view/CSS/JavaScript and shared V6/luxury assets remain byte-identical to the latest ZIP baseline. Hash checks passed after Page 014 changes. A Page 013 Blade fixture/browser regression at 1440×900 reported `clientWidth = 1440`, `scrollWidth = 1440` and no captured browser errors.

No Page 013 visual file was modified. The only shared finance change is backend financial-basis authority needed to prevent Page 013/Page 014 semantic disagreement.

## Verification

PASS:

- latest full-source baseline SHA-256 rechecked;
- route listing (`php artisan route:list --json`), 198 routes;
- PHP syntax for changed PHP files;
- JavaScript syntax (`node --check`);
- Page 014 CSS brace balance;
- Page 014 deterministic validation script: 27/27;
- 20 database-independent Laravel Blade fixture states;
- final 1440/2048/full-page browser renders;
- cashflow tooltip and row-hover render;
- tablet/mobile overflow measurements;
- Page 013 locked-file hash regression;
- Page 013 Blade/browser regression.

NOT VERIFIED due sandbox runtime dependencies:

- authenticated database-backed GET/POST route execution;
- migration status (no PDO database driver);
- PHPUnit execution (missing DOM, mbstring, XML and XMLWriter extensions).

## Database / migration changes

None.

## Remaining truthful finance limitations

- one mutable saved financial analysis per project; recalculation overwrites the current saved model;
- no immutable finance run/revision history;
- no FinancialScenario implementation;
- annual-energy scalar and annual self-consumption assumption rather than time-step energy-flow finance;
- no independent O&M escalation parameter; current engine uses tariff escalation for O&M escalation;
- no controlled provenance/approval object for custom Analysis CAPEX;
- no financing/debt/WACC/tax/depreciation/FX/PPA/lease/grant modules;
- no P50/P90 finance or uncertainty model;
- no sensitivity/Monte Carlo/Pareto analysis;
- no bankability certification or customer-release gate derived from finance.

## Checkpoint

Page 014 is the current cumulative Page 014 implementation baseline pending user review and eventual authenticated database-backed verification in a runtime with the required PHP/database extensions.

Preserve Pages 001–013. Do not start Page 015 until the user says `lanjut`.
