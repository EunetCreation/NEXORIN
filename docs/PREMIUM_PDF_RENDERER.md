# NEXORIN Premium PDF Renderer

This package uses the premium Playwright renderer as the primary report pipeline for company-grade project PDF output.

## Active routes

```text
companies.projects.report.premium.preview
companies.projects.report.premium.pdf
```

The legacy/basic renderer is no longer presented in the project UI. Keep legacy report routes only for controlled internal debugging or emergency maintenance.

## Rendering flow

```text
Project data
→ ProjectReportDataBuilder
→ Premium Blade HTML template
→ Playwright renderer
→ Premium A4 PDF response
```

## Configuration

```env
NEXORIN_NODE_BINARY=node
NEXORIN_PDF_RENDER_SCRIPT=/absolute/path/to/scripts/render-pdf.mjs
NEXORIN_PDF_TIMEOUT=120
NEXORIN_PDF_NAVIGATION_TIMEOUT=60000
NEXORIN_PDF_TEMP_DIR=/writable/temp/path
NEXORIN_PREMIUM_PDF_ALLOW_FALLBACK=false
```

`NEXORIN_PREMIUM_PDF_ALLOW_FALLBACK` must remain `false` for normal development and production. Set it to `true` only for emergency recovery when a controlled internal fallback is explicitly required.

## Current premium pages

1. Cover
2. Executive dashboard
3. System recommendation
4. Technical system summary
5. Financial and commercial summary
6. Detailed BOM/BOQ scope

## Quality direction

Premium PDF output must avoid raw internal terms in customer-facing pages. Do not show values such as `null`, `NaN`, raw field names, raw scope codes, or unflagged zero pricing. User-facing text must use business labels such as Required, Conditional, Optional, Utility Responsibility, Service, Price Required, and Pending Pricing.
