# NEXORIN APP62 Professional Specification Schema 62A

## 1. Purpose

This patch connects the professional Material Catalog taxonomy to a category-specific technical intake system. It removes the previous generic technical form limitation and gives each professional component subcategory its own governed field schema, units, validation, readiness requirements, and project applicability context.

The visual implementation follows the existing NEXORIN 42A design language. It does not redesign the global shell, Dashboard, Project Cockpit, engineering modules, BOM/BOQ hero, Financial Analysis, or Quotation modules.

## 2. Scope delivered

### Professional technical schema

- 15 professional root domains.
- 70 professional component subcategories.
- 85 total root and subcategory schemas, excluding the default fallback.
- 1,191 field appearances across all schemas.
- 397 unique normalized technical keys.
- Minimum 7 and maximum 26 fields per schema.
- Average 14.01 fields per schema.

Every schema defines:

- required and optional specifications;
- field label and description;
- technical group;
- input type;
- unit;
- numeric range and step where relevant;
- selection options;
- filterability;
- certificate and supporting-document expectations.

Representative component coverage includes:

- Mono PERC, TOPCon, HJT, bifacial, and high-power PV modules;
- single-phase, three-phase, micro, central, and optimizer inverter equipment;
- hybrid inverter, off-grid inverter, charge controller, and PCS;
- wall battery, rack battery, battery cabinet, BMS, and ESS packages;
- PV cable and connector, combiner, DC protection, DC SPD, and containment;
- AC cable, MCB/MCCB/ACB protection, ATS/STS/AMF, metering/export control, transformer/MV integration;
- rooftop, ballast, ground mount, carport, walkway, and lifeline components;
- data logger, smart meter, network gateway, weather station, and sensors;
- grounding, lightning protection, bonding, warning label, and LOTO;
- DCDB, ACDB, critical-load panel, LVMDP, and synchronization panel;
- civil work, fire/HSE, certification, logistics/O&M, and supplier quotation records.

## 3. End-to-end data flow

The implemented flow is:

```text
Professional taxonomy
    → Exact component subcategory
    → Category-specific dynamic form
    → Server-side field validation
    → Database transaction
    → material_specs synchronization
    → Legacy material summary-field synchronization
    → Catalog readiness evaluation
    → Evidence and approval governance
    → Price-book material linkage
    → Project-aware price resolution
    → BOM/BOQ pricing health
    → Client release gate
```

### Draft behavior

Required technical fields are required for catalog approval, not for saving an early draft. A Company Admin may save an incomplete material record and complete it later. The form shows progress and the detail page lists missing technical and evidence requirements.

### Approval behavior

Catalog approval remains blocked until the material has:

- an exact professional component subcategory;
- complete identity data;
- all required normalized specifications;
- an active parsed/reviewed/applied datasheet;
- all required verified certificates;
- all required verified supporting documents;
- no expired evidence.

A positive price reference improves commercial readiness but does not replace technical approval requirements.

### Revision behavior

When an approved material changes in its identity, applicability, or normalized technical specifications, the record automatically returns to `needs_review`. Existing evidence and history remain available for audit.

## 4. Project connection

Material Catalog records now participate in project commercial resolution through the existing Price Book and BOM/BOQ flow.

When a BOM item resolves to a company price-book material, NEXORIN evaluates:

- project proposed system versus material `system_types`;
- project building type versus material `project_scales`;
- project Load Analysis supply phase versus material `electrical_phase`;
- project Roof & Site Survey roof types versus material `suitable_roof_types`;
- material catalog approval and compliance status.

An incompatible material is rejected as a company price match. If a compatible material is still Draft or Needs Review, it may remain available for internal staging, but the BOM receives a catalog-review warning and Client Release is blocked until catalog approval is complete and BOM pricing is recalculated.

The price-resolution trace now records:

- material identity and category;
- catalog and compliance state;
- system, scale, phase, and roof applicability;
- project compatibility result and rejection reasons;
- supplier and price-book source;
- availability, quote validity, and pricing state.

## 5. UI and backend alignment

### UI

The Material create/edit workspace now:

- requires selection of an exact component subcategory for new professional records;
- renders grouped technical sections based on the selected component;
- displays correct input controls, units, limits, and select options;
- distinguishes approval-required fields from optional fields;
- preserves values while moving between categories during editing;
- displays required, optional, and completed counts;
- includes system, scale, roof/site, and electrical-phase applicability;
- preserves the existing 42A typography, color, spacing, dark mode, and responsive patterns.

### Backend

The controller independently resolves the selected category and schema. It does not trust client-generated field lists. Unsupported cross-category fields are rejected. All technical values pass server-side type and range validation.

The synchronizer:

- writes normalized values to `material_specs`;
- updates existing rows rather than duplicating them;
- deletes obsolete schema-managed values when category changes;
- preserves custom and parser-only specifications;
- maps important normalized fields to legacy `materials` summary columns;
- keeps create/update operations inside database transactions.

## 6. Important boundaries

This patch provides the technical data foundation and project-aware commercial connection. It does not yet implement:

- automatic engineering product selection for a project;
- module-to-inverter string/MPPT compatibility optimization;
- inverter-to-battery protocol and voltage compatibility selection;
- automated scenario ranking by CAPEX, payback, backup, or roof utilization;
- native PDF table extraction, OCR, or page-level parser provenance;
- roof-map module placement;
- automatic SLD generation.

Those capabilities should consume the normalized schema introduced here. They should not create a second technical-data structure.

## 7. Installation

Install this patch over the latest APP62 source and back up the application and database first.

```powershell
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=ProfessionalCatalogTaxonomySeeder
npm ci
npm run build
php artisan test
php artisan optimize
```

No new migration is added by this patch. `php artisan migrate` is included as a safe deployment step because the existing APP62 schema already contains `material_specs`, applicability JSON fields, and catalog-governance fields.

Do not run `MaterialCatalogSeeder` in production. It is development/demo data and remains environment-restricted.

## 8. Audit results

### Passed in the audit environment

- PHP syntax for all changed and new PHP files.
- Laravel application boot and route enumeration.
- 173 routes registered.
- Material Catalog, evidence, approval, Price Book, item, and import routes present.
- Direct Blade compilation for Material index/create/edit/detail/form and Price Book item/detail views.
- JavaScript syntax validation.
- CSS brace and structural validation.
- Schema registry integrity:
  - 15 roots;
  - 70 subcategories;
  - 85 exact schemas;
  - no duplicate field keys inside a schema;
  - no select field without options;
  - no invalid minimum/maximum range.
- Vite production build:
  - Vite 8.1.3;
  - 3 modules transformed;
  - build completed successfully.

### Automated tests added or extended

- exact schema structure and critical unit/input metadata;
- dynamic PV specification persistence;
- unsupported cross-category field rejection;
- professional root-category approval blocking;
- approval invalidation after technical change;
- roof applicability persistence;
- project system and scale incompatibility rejection;
- project phase and roof incompatibility rejection;
- Draft material governance trace in pricing;
- Client Release blocking for catalog-review BOM items.

### Environment limitation

`php artisan test` could not execute in the audit container because its PHP runtime lacks `dom`, `mbstring`, `xml`, and `xmlwriter`. The exact PHPUnit message is:

```text
PHPUnit requires the "dom", "json", "libxml", "mbstring", "tokenizer", "xml", "xmlwriter" extensions, but the "dom", "mbstring", "xml", "xmlwriter" extensions are not available.
```

The tests are included in the patch and must be run in the user's normal PHP environment before the patch is accepted as the next locked baseline.

## 9. Required UAT sequence

### A. Taxonomy and dynamic form

1. Open Material Catalog as Company Admin.
2. Create one material from each representative group:
   - PV Mono PERC;
   - three-phase string inverter;
   - hybrid inverter;
   - rack battery;
   - BMS;
   - PV cable;
   - DC SPD;
   - AC breaker;
   - rooftop mounting;
   - smart meter;
   - grounding conductor;
   - ACDB panel;
   - civil service;
   - fire extinguisher;
   - supplier quotation.
3. Confirm each selection displays different component-specific fields.
4. Confirm irrelevant fields do not appear.
5. Confirm units and input controls are correct.
6. Save as Draft with partial data.
7. Reopen Edit and confirm all values persist.

### B. Validation and readiness

1. Submit a numeric value outside its allowed range.
2. Confirm the server returns a field-specific validation error.
3. Submit a field from another subcategory by tampering with the request.
4. Confirm the request is rejected.
5. Complete required specifications.
6. Confirm missing-spec counts decrease correctly.
7. Attempt approval without required evidence and confirm it is blocked.
8. Complete datasheet, certificate, and supporting-document requirements.
9. Approve the material.
10. Change one technical value and confirm it returns to Needs Review.

### C. Project applicability and pricing

1. Create two materials mapped to the same BOM alias but with different applicability.
2. Link each to active Price Book items.
3. Use a project with known:
   - proposed system;
   - building type;
   - Load Analysis supply phase;
   - Roof & Site Survey roof type.
4. Run BOM pricing.
5. Confirm incompatible system, scale, phase, or roof materials are rejected.
6. Confirm the compatible approved material is selected.
7. Repeat using a compatible Draft material.
8. Confirm pricing trace identifies `catalog_review_required`.
9. Confirm BOM summary increases `catalog_review_item_count`.
10. Confirm Client Release is blocked.
11. Approve the material and reprice the BOM.
12. Confirm the catalog-review blocker clears.

### D. Role and tenant checks

- Company Admin can create/edit and manage governed catalog data.
- Engineer sees technical data but cannot modify unauthorized commercial fields.
- Sales does not see confidential cost fields without permission.
- Viewer remains read-only.
- Customer cannot open internal Material Catalog routes.
- Another company cannot open material, evidence, Price Book, or related project data.

### E. Responsive and visual checks

Verify Material Catalog index, create, edit, detail, Price Book detail, and Price Book item form at:

- 1440 × 900;
- 1366 × 768;
- 1024 × 768;
- 390 × 844;
- light mode;
- dark mode.

The global shell and locked modules must remain unchanged.
