# NEXORIN Page 022 — Edit Material Checkpoint

Date: 2026-10-23  
Scope: Page 022 only (`A_DIRECT`)  
Routes:
- `GET /companies/{company}/materials/{material}/edit` (`companies.materials.edit`)
- `PUT /companies/{company}/materials/{material}` (`companies.materials.update`)  
Controller:
- `App\Http\Controllers\MaterialCatalogController::edit`
- `App\Http\Controllers\MaterialCatalogController::update`  
Primary Blade:
- `resources/views/materials/edit.blade.php`  
Page-local Assets:
- `public/css/nexorin-material-edit.css`
- `public/js/nexorin-material-edit.js`  
Exact V6 Target:
- `022_materials_edit.png`

---

## 1. Checkpoint Status

**`022 — IMPLEMENTED / REVIEW READY`** (NOT FINAL / NOT LOCKED automatically; awaits explicit user decision).

- **Verified in this sandbox**:
  - Full source & domain audit of `MaterialCatalogController`, `Material`, `MaterialSpec`, `MaterialCategory`, `MaterialSpecificationSynchronizer`, `MaterialCatalogCompletenessEvaluator`, `DatasheetApplicationService`, `ProfessionalSpecificationSchema`, and `EngineeringProductMasterDictionary` (89 canonical component classes).
  - Genuine BEFORE browser renders (`1440×900`, `2048×900`, `full-page`) captured prior to modifying source.
  - Controlled backend mutation contract (`GOVERNANCE_IMPACT_FIELDS` vs `ADMINISTRATIVE_ONLY_FIELDS`, server-side company ownership check, `equipment.update` middleware, unsupported spec key rejection, stale spec key cleanup on category change, approval invalidation via `markForCatalogReview()`, and preservation of linked datasheets/certificates/documents).
  - V6 dark enterprise luxury refinement in `resources/views/materials/edit.blade.php`, `public/css/nexorin-material-edit.css`, and `public/js/nexorin-material-edit.js` with zero modification to locked Page 021 files (`create.blade.php`, `partials/form.blade.php`, `nexorin-material-create.css`, `nexorin-material-catalog.js`, `Page021MaterialCreateTest.php`).
  - JavaScript syntax check (`node --check public/js/nexorin-material-edit.js` -> PASS).
  - Static contract validation (`scripts/validate-page022-material-edit.php` assertions -> PASS).
  - Genuine AFTER browser renders in headless Chromium across `1440×900`, `2048×900`, `full-page`, `1180×900`, `1024×900`, `768×900`, `390×900`, dirty/category-switch warning state, sparse/no-evidence state, dense/long-string stress state, and validation-error state — with **0 severe console/network errors** and **0 horizontal overflow (`overflowX: false`)**.
- **NOT VERIFIED in this sandbox**:
  - `php -l`, `php artisan route:list`, `php artisan test --filter=Page022MaterialEditTest`, and live database-backed Laravel HTTP execution are **NOT VERIFIED** because the container environment does not have a `php` CLI binary installed (`php: command not found`).

---

## 2. Backend & Domain Mutation Contract Implemented

1. **Authoritative Persisted Entity**:
   - `MaterialCatalogController@edit` and `MaterialCatalogController@update` operate strictly on an existing persisted company-owned `Material` via `findMaterial($company, $material)` and verify `(int) $material->company_id === (int) $company->getKey()`. Global catalog rows (`company_id === null`) are blocked from company mutation on both `GET edit` and `PUT update`.
   - Route middleware enforces `auth`, `active`, `company.access`, and `company.permission:equipment.update`.
2. **Governance vs. Administrative Mutation Separation**:
   - `GOVERNANCE_IMPACT_FIELDS`: `material_category_id`, `sku`, `name`, `brand`, `model`, `uom`, `system_types`, `project_scales`, `suitable_roof_types`, `default_scope_mode`, `is_active`, `technical_notes`, plus any `MaterialSpec` value/unit change reported by `MaterialSpecificationSynchronizer::sync()`.
   - `ADMINISTRATIVE_ONLY_FIELDS`: `material_type`, `cost_group`, `description`.
   - When any governance-impact field or technical specification changes on an `approved` / `compliant` Material, `update()` invokes `$material->markForCatalogReview($request->user()->getKey())`, resetting `catalog_status = 'needs_review'`, `compliance_status = 'needs_review'`, `approved_at = null`, and `approved_by = null`.
   - When only administrative fields (`material_type`, `cost_group`, `description`) change, existing `approved` / `compliant` status is preserved while recording `updated_by`.
   - When no fields or specs change (no-op save), existing status and timestamps remain untouched.
3. **Category-Driven Specification Schema & Stale Key Cleanup**:
   - `validatedMaterialPayload()` validates `technical_specs` against the target `MaterialCategory` schema from `ProfessionalSpecificationSchema::for($category)` and rejects any unsupported keys.
   - `MaterialSpecificationSynchronizer::sync()` deletes stale `MaterialSpec` keys from the previous category schema that do not belong to the newly selected category, while preserving custom non-schema specs.
4. **Evidence & Catalog Media Boundaries**:
   - Attached `datasheets`, `certificates`, `documents`, and `priceBookItems` are preserved across edits and never auto-deleted.
   - If a material has an applied datasheet extraction (`MaterialDatasheetApplication` with `status = 'applied'`), manual edits to those applied `MaterialSpec` keys block automatic parser rollback (`DatasheetApplicationService::rollback()`).
   - No shared persisted per-material product photo gallery exists in the current backend; Page 022 displays the canonical component-class illustration (`public/images/material-classes/*.webp`) and explicitly states that class visuals are not compliance evidence.
   - Optimistic concurrency locking is not implemented on `materials`; Page 022 reports `Direct transactional update (no optimistic lock token)` truthfully.

---

## 3. Changed & Added Files

- Modified:
  - `app/Http/Controllers/MaterialCatalogController.php`
  - `resources/views/materials/edit.blade.php`
- Added:
  - `public/css/nexorin-material-edit.css`
  - `public/js/nexorin-material-edit.js`
  - `tests/Feature/Page022MaterialEditTest.php`
  - `scripts/validate-page022-material-edit.php`
  - `docs/checkpoints/PAGE022_MATERIAL_EDIT_CHECKPOINT.md`
- Unchanged (Locked Baseline Preserved 100%):
  - `resources/views/materials/create.blade.php`
  - `resources/views/materials/partials/form.blade.php`
  - `public/css/nexorin-material-create.css`
  - `public/js/nexorin-material-catalog.js`
  - `tests/Feature/Page021MaterialCreateTest.php`

---

## 4. Required Local Verification Steps Before Locking Page 022

1. Extract `NEXORIN-PAGE022-PATCH.zip` over the project root.
2. Run `php scripts/validate-page022-material-edit.php`.
3. Run `php artisan test --filter=Page022MaterialEditTest` and `php artisan test --filter=Page021MaterialCreateTest`.
4. Open `/companies/{company}/materials/{material}/edit` in the local Laravel runtime and verify live persistence and redirect to `/companies/{company}/materials/{material}`.

STOP after Page 022. Do not start Page 023 until explicitly instructed.
