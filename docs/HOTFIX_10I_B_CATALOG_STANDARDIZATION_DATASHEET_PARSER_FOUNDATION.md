# 10I-B Catalog Standardization and Datasheet Parser Foundation

## Status
Implemented as a focused foundation sprint after legacy engine pruning.

## Purpose
This sprint strengthens the material catalog so it can become a reliable technical source for BOM, BOQ, price book, quotation, and client-safe PDF workflows. The goal is not to replace engineer review, but to make every catalog item easier to validate before it is used in a project.

## Main changes

### 1. Catalog governance fields
Added governance fields to `materials`:

- `catalog_status`
- `compliance_status`
- `approved_at`
- `approved_by`
- `last_reviewed_at`
- `last_reviewed_by`

These fields separate an active item from an approved item. An item can be active for editing and still not approved for BOQ and quotation use.

### 2. Datasheet parser metadata
Added parser and review fields to `material_datasheets`:

- `original_filename`
- `mime_type`
- `file_size_bytes`
- `extraction_status`
- `parser_version`
- `parse_confidence`
- `parsed_text`
- `parsed_fields`
- `parse_warnings`
- `parsed_at`
- `reviewed_at`
- `reviewed_by`
- `applied_at`
- `applied_by`

The parser is text-based and deterministic. It does not call paid AI services. Scanned PDFs remain manual-review items.

### 3. Certificate verification fields
Added verification fields to `material_certificates`:

- `verification_status`
- `verified_at`
- `verified_by`
- `verification_notes`

This supports standard compliance checks such as IEC 61215, IEC 61730, IEC 62109, UN38.3, and IEC 62619.

### 4. Catalog standards registry
Added:

- `app/Support/MaterialCatalogStandards.php`

This file defines required specification keys and required certificate types per category. It currently covers PV module, power conversion, battery/ESS, DC electrical, AC electrical, mounting, monitoring, and grounding/safety categories.

### 5. Completeness evaluator
Added:

- `app/Services/Catalog/MaterialCatalogCompletenessEvaluator.php`

This service calculates:

- completeness score
- missing required specs
- missing required certificates
- datasheet readiness
- price reference availability
- approval readiness

Approval is blocked when required specs, parsed datasheet evidence, or required certificates are missing.

### 6. Datasheet parser service
Added:

- `app/Services/Catalog/DatasheetParser.php`

The parser extracts common PV, inverter, and battery fields from readable datasheet text. Parsed values can be reviewed and applied to `material_specs`.

### 7. Material datasheet workflow
Added:

- `app/Http/Controllers/MaterialDatasheetController.php`

New routes:

- `companies.materials.datasheets.store`
- `companies.materials.datasheets.apply`
- `companies.materials.datasheets.archive`

### 8. Material approval route
Added:

- `companies.materials.approve`

Company materials can be approved only after catalog readiness checks pass. Global catalog items remain read-only from company workspaces.

### 9. UI update
Updated material detail view to display:

- catalog status
- completeness score
- readiness notes
- approval button
- normalized technical specs
- datasheet upload form
- parser confidence and warnings
- apply parsed specs action

### 10. Test coverage
Added:

- `tests/Feature/CatalogStandardizationDatasheetParserTest.php`

Coverage:

- company admin can upload, parse, and apply datasheet specs
- incomplete material cannot be approved
- complete material can be approved

## Commands

After applying the patch:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate

php artisan test tests/Feature/CatalogStandardizationDatasheetParserTest.php
php artisan test tests/Feature/MaterialCatalogManagementTest.php
php artisan test
```

## Notes

This is still a foundation parser. It works best with text-based PDF or text content. OCR and advanced table extraction should be handled in a later sprint.
