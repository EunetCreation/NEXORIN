# NEXORIN Companies Module

This update adds the first operational administration module without requiring a frontend build.

## Included

- Super Admin middleware
- Company CRUD controller
- Store and update validation requests
- Company directory with search, status filter, sorting, statistics, and pagination
- Create, view, edit, activate, deactivate, and archive workflows
- Shared UI foundation for buttons, forms, tables, badges, alerts, cards, and pagination
- Active Companies navigation item
- Dynamic topbar heading
- Feature tests for company administration

## Run

```powershell
php artisan optimize:clear
php artisan migrate
php artisan serve
```

Open:

```text
http://127.0.0.1:8000/companies
```

No `npm install` or Vite build is required for this update because the shared module stylesheet is served directly from `public/css/nexorin-ui.css`.
