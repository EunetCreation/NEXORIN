# NEXORIN APP64 Industrial Datasheet Parser 64A

## Baseline

This milestone is built on:

- `NEXORIN-APP(63)`
- `NEXORIN-APP63-CATALOG-SCHEMA-PRECISION-63A-PATCH`

It preserves the APP63 professional specification schema and uses the same normalized `material_specs` records consumed by catalog readiness, price books, project-aware BOM pricing, and client release governance.

## Purpose

APP64 replaces the previous broad regex parser and one-click application flow with a controlled engineering evidence workflow:

1. secure datasheet intake;
2. page-aware text extraction;
3. exact-subcategory schema parsing;
4. normalized values and units;
5. field-level confidence and source trace;
6. category mismatch protection;
7. explicit Accept, Keep, or Reject review;
8. revision snapshots and guarded rollback;
9. automatic return to Needs Review after technical changes.

The parser never approves a material automatically.

## Implemented flow

```text
PDF / TXT / CSV
  -> file signature and MIME validation
  -> SHA-256 duplicate detection
  -> document extraction
  -> expected component schema
  -> category detection
  -> alias and range-aware parsing
  -> unit normalization and range validation
  -> conflict comparison with existing catalog values
  -> field-by-field review
  -> transactional selective application
  -> material_specs and legacy summary synchronization
  -> Needs Review governance
  -> auditable application revision
  -> guarded rollback
```

## Document extraction

### Preferred PDF extraction

The parser uses `pdftotext` when that executable is available. It preserves page breaks and creates page-level field traces.

Configuration:

```env
NEXORIN_PDFTOTEXT_BINARY=pdftotext
```

The value may also be an absolute executable path.

### Native fallback

A conservative pure-PHP fallback extracts readable text from common PDF content streams. It does not claim OCR, encrypted PDF support, or complete embedded-font decoding.

Image-only or scanned PDFs are flagged for manual review. OCR is intentionally outside APP64 64A.

### Text formats

TXT and CSV are accepted as controlled testing or structured import evidence. Binary content disguised as TXT or CSV is rejected.

## Parser controls

- Exact component schema only. Fields outside the selected material subcategory are not generated or accepted.
- Technical aliases for PV modules, inverters, batteries, SPD devices, and other catalog fields.
- Generic aliases derived from every governed schema label and key.
- Short aliases use word boundaries to prevent false matches inside unrelated words.
- MPPT range lines are split into governed minimum and maximum values.
- Multi-model rows with several candidate values are marked low confidence.
- Required fields not found are listed in warnings.
- Category mismatch can block application.

## Unit normalization

Implemented conversions include:

- W, kW, MW;
- Wh, kWh, MWh;
- mV, V, kV;
- mA, A, kA;
- mm, cm, m;
- g, kg;
- ns, ms, s;
- Pa, kPa, MPa.

The parser preserves the original raw value and raw unit while storing a schema-normalized candidate. Decimal comma handling includes values such as `-0,350` without misreading them as thousands.

## Review and application governance

Every parsed field exposes:

- existing catalog value;
- proposed normalized value;
- raw source value;
- source page;
- source excerpt;
- matched alias;
- field confidence;
- new, match, conflict, or low-confidence status;
- Accept, Keep Existing, or Reject action.

Only accepted values are saved. Server-side validation rebuilds the exact schema and does not trust browser-submitted keys.

Each application stores:

- parser version;
- all review decisions;
- parser value and reviewed value;
- manual-correction indicator;
- source page and excerpt;
- confidence and parser status;
- before and after specification snapshots;
- applying user and time.

## Rollback governance

Rollback is blocked when:

- the application is not the latest active material parser revision;
- a later application exists;
- current technical values differ from the application snapshot because of later manual work;
- the application belongs to another material or datasheet.

Rollback restores only the fields changed by that parser application. Datasheet evidence remains registered, and the material remains in Needs Review.

## Security and tenancy

- Existing company permission middleware remains in force.
- Material ownership is checked for every mutation.
- Datasheet, material, and application relationships are validated.
- Global catalog records remain read-only inside a company context.
- External URLs are stored as references and are not fetched server-side.
- Local files use controlled storage paths and secure evidence routes.
- Duplicate active evidence is prevented with SHA-256 fingerprints.
- Archived datasheets cannot apply technical values.

## Project integration

APP64 writes to the same normalized specifications introduced in APP62 and corrected in APP63. It does not create a competing technical data structure.

```text
Datasheet parser
  -> material_specs
  -> material core summary columns
  -> catalog completeness and approval
  -> price-book material linkage
  -> project system, scale, phase, and roof compatibility
  -> BOM pricing trace
  -> catalog_review_required
  -> client release gate
```

Approved materials edited through parser application or rollback are returned to Needs Review and their approval actor and timestamp are cleared.

## Database changes

Migration:

```text
database/migrations/2026_07_16_010000_add_industrial_datasheet_parser_governance.php
```

Adds extraction metadata to `material_datasheets` and creates `material_datasheet_applications` for revision history and rollback.

## Environment configuration

```env
NEXORIN_DATASHEET_PARSER_VERSION=64A-schema-v1
NEXORIN_DATASHEET_MAX_UPLOAD_KB=10240
NEXORIN_PDFTOTEXT_BINARY=pdftotext
NEXORIN_DATASHEET_PROCESS_TIMEOUT=20
NEXORIN_DATASHEET_MAX_TEXT_LENGTH=200000
```

## Installation

```powershell
php artisan optimize:clear
php artisan migrate
npm ci
npm run build
php artisan test
php artisan optimize
```

No taxonomy seeding is required for this patch when APP63 63A is already installed.

## Verification performed in the build environment

- PHP syntax check on all changed and new PHP files: passed.
- Laravel route boot and route enumeration: passed.
- Total application routes: 175.
- Datasheet routes: 6.
- Blade direct compilation for material detail and parser workspace: passed.
- CSS structural brace audit: passed.
- Vite production build: passed.
- NPM audit: 0 vulnerabilities.
- APP63 schema inventory: 15 roots, 70 subcategories, 86 schemas, 398 unique keys.
- Schema-wide synthetic parser audit: 85 component schemas and 1,199 field appearances, 0 missing parser fields.
- PV parsing smoke test: passed.
- Battery Wh to kWh conversion smoke test: passed.
- DC SPD exact-field smoke test: passed.
- Category mismatch blocking smoke test: passed.
- Multi-model ambiguity smoke test: passed.
- Decimal comma smoke test: passed.
- Ambiguous grouped-dot normalization is surfaced as low confidence: passed.
- MPPT minimum and maximum range split smoke test: passed.
- Real PDF extraction using `BOM_BOQ_PROJECT_PLTS_LENGKAP.pdf`: `pdftotext`, 32 pages, readable text recovered.

## Test environment limitation

The build container cannot execute PHPUnit because PHP extensions `dom`, `mbstring`, `xml`, and `xmlwriter` are unavailable. The test command stops before executing the suite. New unit and feature regression tests are included and must be run in the normal NEXORIN development environment.

Composer CLI is also unavailable in the build container, although the existing Composer autoloader successfully loads all APP64 classes during route, Blade, and parser smoke tests.

The build container has no PDO database drivers, so the new migration could not be executed against a disposable database here. Its PHP syntax and Laravel route/model boot path passed, and the migration must be exercised by `php artisan migrate` in the normal NEXORIN environment. Browser-based visual regression was not claimed because an authenticated browser runtime was not available in this container.

## Deliberate limitations

APP64 64A does not claim:

- OCR for scanned or image-only PDF files;
- automatic recognition of the correct product column in every multi-model table;
- fully automatic material approval;
- automatic smart component selection;
- module-inverter or battery-inverter engineering compatibility calculations beyond the existing project pricing compatibility flow.

These limitations are surfaced as warnings or low-confidence review states instead of silently guessing.
