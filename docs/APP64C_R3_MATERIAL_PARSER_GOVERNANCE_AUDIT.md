# NEXORIN APP64C-R3 Material Catalog and Datasheet Parser Governance Audit

## Audit target

- Source audited: `NEXORIN-APP.zip`, supplied as the latest APP64 baseline.
- Requested continuation baseline: `APP64C-R2`.
- Visual baseline: `42A`.
- Locked pages: preserved.
- Main scope: Professional Specification Schema and Industrial Datasheet Parser.

## Audit conclusion

The APP64C-R2 foundation is structurally sound. The professional schema, parser extraction pipeline, temporary upload workspace, application history, rollback, and parser governance migration are present and connected.

Three governance gaps remained in operational routes that were not covered by the previous suite:

1. The additional specification endpoint could write or delete a key owned by the Professional Specification Schema.
2. A datasheet with an active applied revision could be applied again or archived before rollback.
3. Changes to technical evidence or material active state could leave old approval metadata attached to a record that now required review.

APP64C-R3 closes these gaps without changing the main application flow, routes, database schema, or 42A visual system.

## Implemented corrections

### 1. Professional schema ownership guard

The additional specification endpoint now accepts only genuinely custom keys. Any key listed in `ProfessionalSpecificationSchema::managedKeys()` is rejected for both update and deletion.

Schema-owned fields must continue through one of the governed paths:

- Edit technical profile.
- Review and apply an Industrial Datasheet Parser proposal.

This prevents an auxiliary form from bypassing field validation, unit rules, summary synchronization, and schema completeness logic.

### 2. Single active parser application rule

`DatasheetApplicationService` now rejects a second application while the same datasheet still has an application with status `applied`.

The operator must use the existing rollback flow before applying a new reviewed revision. This preserves one clear active revision and one reversible change set.

### 3. Datasheet archive guard

A datasheet cannot be archived while an applied parser revision is active. The backend returns a controlled error and requires rollback first.

The parser card follows the same rule:

- Archive is hidden while an active application exists.
- Candidate value, unit, and action controls are disabled.
- The apply button is disabled and displays `Rollback before reapplying`.

This is a state-control refinement only. No layout, typography, color, spacing, or locked page structure was redesigned.

### 4. Central review invalidation

`Material::markForCatalogReview()` now provides one governance transition for technical or evidence changes.

It performs the following actions:

- sets compliance status to `needs_review`;
- records the latest reviewer and review time;
- clears `approved_at` and `approved_by`;
- moves an approved catalog item back to `needs_review`.

The method is used after:

- custom specification changes;
- certificate changes;
- supporting document changes;
- datasheet registration;
- datasheet reparse;
- datasheet archive;
- material archive;
- material reactivation.

### 5. Reliable test bootstrap

`phpunit.xml` now includes a fixed test-only `APP_KEY`. This makes encryption-dependent tests deterministic and prevents them from inheriting a developer or deployment key.

The key is used only in the PHPUnit testing environment and does not replace the application key used by deployment environments. The supplied source archive intentionally excludes `.env`; clean-overlay verification therefore used a temporary copy of `.env.example` only inside the test workspace. No environment file is included in this patch.

## Flow impact

The patch does not add or remove routes. It does not alter controller ownership, migration structure, database tables, parser extraction algorithms, or approval authority.

The existing operational flow remains:

1. Register datasheet evidence.
2. Parse and review candidates.
3. Apply one reviewed revision.
4. Roll back that revision when correction is required.
5. Reparse or apply a new revision.
6. Complete catalog review and approval.

The patch only prevents invalid shortcuts around this flow.

## Files changed

- `app/Models/Material.php`
- `app/Http/Controllers/MaterialCatalogController.php`
- `app/Http/Controllers/MaterialCertificateController.php`
- `app/Http/Controllers/MaterialDatasheetController.php`
- `app/Http/Controllers/MaterialDocumentController.php`
- `app/Http/Controllers/MaterialSpecController.php`
- `app/Services/Catalog/DatasheetApplicationService.php`
- `resources/views/materials/partials/datasheet-parser-card.blade.php`
- `phpunit.xml`
- `tests/Feature/CatalogStandardizationDatasheetParserTest.php`
- `tests/Feature/MaterialCatalogManagementTest.php`
- `tests/Feature/ProfessionalMaterialCatalogTest.php`

## Test coverage added

- Schema-managed specifications cannot be overwritten through the custom specification endpoint.
- Schema-managed specifications cannot be deleted through the custom specification endpoint.
- A second parser application is rejected until rollback.
- A datasheet with an active parser application cannot be archived.
- Datasheet registration invalidates existing approval.
- Datasheet reparse invalidates existing approval.
- Datasheet archive invalidates existing approval.
- Evidence changes clear old approval metadata.
- Material archive and reactivation clear old approval metadata.

## Final verification

The patch was verified on a clean copy of the supplied ZIP after overlaying only the patch files.

| Check | Result |
|---|---:|
| Full Laravel test suite | 172 passed |
| Assertions | 8,075 |
| Focused catalog and parser tests | 22 passed, 181 assertions |
| PHP syntax scan | 228 application, route, migration, and test files passed |
| JavaScript syntax scan | 19 files passed |
| Clean SQLite migration | Passed |
| Registered routes | 175 total |
| Material Catalog routes | 25 |
| Blade view compilation | Passed |
| Vite production build | Passed |
| npm audit from clean install | 0 vulnerabilities |

## Known boundary

This patch strengthens state governance around the existing parser. It does not claim universal extraction support for scanned, encrypted, image-only, or structurally unknown vendor datasheets. Those documents continue to rely on the existing confidence, mismatch, variant selection, and manual review controls.
