# NEXORIN APP64 Industrial Datasheet Parser 64B Audit Report

## Baseline

This cumulative patch targets:

- `NEXORIN-APP(63)`
- `NEXORIN-APP63-CATALOG-SCHEMA-PRECISION-63A-PATCH`

It includes the APP64A parser foundation and the APP64B multi-variant table refinement. It can therefore be installed directly when APP63 + 63A is the current baseline.

## Reason for refinement

The Trina Solar Vertex S+ `TSM-NED9R.28 445-470W` datasheet contains six product powers in one STC table. The 64A row-level alias parser could recover text, but it could not safely map each electrical row to the correct product column. This caused risks such as:

- reading Vmp as module power;
- reading the temperature coefficient of Isc as Isc;
- taking the first 445 W column for a 470 W material;
- mixing product warranty and performance warranty;
- losing separated negative signs;
- failing to split dimensions.

## APP64B implementation

### Governed PV STC matrix parser

A dedicated `PvVariantTableParser` now:

1. detects the `ELECTRICAL DATA (STC)` section;
2. reads the product power row;
3. aligns Vmp, Imp, Voc, Isc, and module efficiency by column index;
4. ignores the NOCT table for core catalog ratings;
5. records all available product powers;
6. selects one exact product variant from:
   - explicit user selection;
   - existing `module_power_wp` specification;
   - material nominal power;
   - material model, SKU, or name;
   - a single detected table variant;
7. blocks application when a multi-variant table has no exact selection.

### Variant governance

For an unresolved multi-variant document:

- extraction status becomes `variant_selection_required`;
- variant-specific electrical fields are withheld;
- application is blocked server-side;
- parser confidence is capped at 55%;
- the UI presents detected power variants and a controlled reparse selector.

A selected variant is written to extraction metadata with:

- available variants;
- selected variant;
- selection source;
- blocking state.

### PV supplemental precision parsing

APP64B adds governed extraction for:

- product warranty;
- performance/power warranty;
- temperature coefficient Pmax with separated signs;
- temperature coefficient Voc with separated signs;
- module dimensions split into length and width;
- module weight;
- cell technology;
- cell count;
- maximum system voltage;
- connector type when present;
- first-year and annual degradation when readable.

### Alias safety

The broad `maximum power` alias was removed from module power matching. Module power now prefers terms such as:

- Peak Power Watts Pmax;
- Maximum Power Pmax;
- Pmax;
- Rated Power;
- Module Power.

This prevents `Maximum Power Voltage` from being interpreted as module power.

## UI flow

Material detail now includes:

- optional target product variant input during upload for PV modules;
- detected variant summary;
- selected variant and selection source;
- a variant selector in the reparse action;
- disabled apply controls until exact variant selection is safe.

Upload location:

`Material Catalog → open material → Datasheets → Register vendor evidence`

## Real-document audit

Source tested:

`Vertex S+ TSM-NED9R.28 445-470W.pdf`

Extraction result for material power 470 Wp:

| Field | Parsed result |
|---|---:|
| Module power | 470 Wp |
| Vmp | 46.10 V |
| Imp | 10.20 A |
| Voc | 54.60 V |
| Isc | 10.89 A |
| Module efficiency | 23.50% |
| Temperature coefficient Pmax | -0.29 %/°C |
| Temperature coefficient Voc | -0.24 %/°C |
| Module length | 1762 mm |
| Module width | 1134 mm |
| Weight | 27 kg |
| Cell technology | N-type i-TOPCon Monocrystalline |
| Cell count | 144 |
| Maximum system voltage | 1500 V |
| Product warranty | 15 years |
| Performance warranty | 30 years |

Audit result:

- extraction method: `pdftotext`;
- pages: 2;
- status: `parsed`;
- selected variant: 470 Wp;
- selection source: material summary;
- parser confidence: 98.16%;
- required TOPCon fields identified: 16/16.

Explicit selection of 465 Wp also produced the correct STC column:

- Pmax 465 Wp;
- Vmp 45.80 V;
- Imp 10.16 A;
- Voc 54.20 V;
- Isc 10.85 A;
- efficiency 23.30%.

When material power was intentionally removed, the parser correctly returned:

- status `variant_selection_required`;
- available variants 445, 450, 455, 460, 465, 470 Wp;
- no Pmax, Vmp, Imp, Voc, Isc, or efficiency candidate;
- server-side application blocked.

## Regression audit

Custom runtime smoke checks passed for:

- simple single-model PV parsing;
- negative temperature coefficients;
- battery Wh to kWh conversion;
- battery operating temperature range;
- DC SPD Uc and Imax;
- category mismatch blocking;
- MPPT range split;
- real two-page Trina Solar PDF;
- automatic variant selection;
- explicit variant selection;
- unresolved variant blocking.

Additional checks:

- PHP syntax: pass for all changed PHP files;
- Laravel route registration: six datasheet routes present;
- Blade compilation: material show and parser card pass;
- `npm ci`: pass;
- npm vulnerabilities: 0;
- Vite production build: pass.

`php artisan test` could not be executed in the audit container because PHP extensions `dom`, `mbstring`, `xml`, and `xmlwriter` are unavailable. New unit and feature tests are included and must be run in the user's Windows environment, where the test suite has previously executed successfully.

## Files changed by 64B refinement

- `app/Services/Catalog/Datasheet/PvVariantTableParser.php`
- `app/Services/Catalog/DatasheetParser.php`
- `app/Services/Catalog/Datasheet/DatasheetFieldAliasRegistry.php`
- `app/Services/Catalog/DatasheetApplicationService.php`
- `app/Http/Controllers/MaterialDatasheetController.php`
- `app/Support/MaterialCatalogStandards.php`
- `config/nexorin_catalog.php`
- `.env.example`
- `resources/views/materials/show.blade.php`
- `resources/views/materials/partials/datasheet-parser-card.blade.php`
- `public/css/nexorin-material-catalog.css`
- `tests/Unit/IndustrialDatasheetParserTest.php`
- `tests/Feature/CatalogStandardizationDatasheetParserTest.php`

The cumulative ZIP also includes the runtime foundation files and migration introduced by APP64A.

## Known boundary

APP64B handles governed row-oriented STC multi-variant PV tables recovered by `pdftotext`. It does not claim universal table understanding for every vendor layout, scanned PDF, encrypted PDF, or image-only table. Those documents remain governed by low-confidence/manual-review controls rather than being applied silently.
