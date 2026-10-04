# NEXORIN Page 002 Performance Patch Manifest

Patch baseline: exact contents of `NEXORIN(3).zip` uploaded on 2026-08-02.

## Apply

1. Stop the development server.
2. Back up the current project directory or commit current work.
3. Extract this ZIP into the NEXORIN project root and allow replacement of matching files.
4. Run:

```powershell
php scripts/validate-page001-patch.php
php scripts/validate-page002-performance-patch.php
php scripts/test-page002-authorization-cache.php
php artisan optimize:clear
```

5. Start the application using the same database configuration already used by the current project. This patch adds no migration and does not replace `.env` or the database.

## Files changed

- `app/Http/Controllers/BillOfMaterialController.php`
- `app/Models/CompanyMembership.php`
- `app/Models/User.php`
- `public/js/nexorin-engineering-action.js`
- `public/js/nexorin-shell.js`
- `resources/views/boms/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/partials/topbar.blade.php`

## Files added

- `public/css/nexorin-bom-library-v6.css`
- `public/js/nexorin-bom-library-v6.js`
- `scripts/validate-page002-performance-patch.php`
- `scripts/test-page002-authorization-cache.php`
- `docs/PAGE002_PERFORMANCE_AUDIT.md`
- `docs/PAGE002_PATCH_MANIFEST.md`
- `docs/checkpoints/PAGE002_CHECKPOINT_2026-08-02.md`
- `docs/checkpoints/PAGE002_VALIDATION_LOG.txt`

## Files deleted

None.

## Database impact

None. Existing migrations, company membership, role, permission, project, calculation, BOM, material, and pricing records are preserved.

## Runtime review gate

Page 002 must still be rendered in the target Windows environment and compared with the V6 Golden screenshot before it can be marked final or 1:1.
