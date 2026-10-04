# SLD-LANDING-001 — SLD Review Workspace Redesign

## Purpose

Replace the legacy long-form SLD landing page with a drawing-first review workspace. The editor, topology engine, Technical Calculation authority, electrical rules, document-control services, and controlled export routes remain unchanged.

## Locked visual direction

- Light professional application shell.
- Midnight navy corporate identity.
- Warm ivory and white surfaces.
- Muted champagne-gold accent used only for active states and premium hierarchy.
- Dark CAD preview with white paper-space content.
- Status colors remain semantic: green, amber, and red.

## Workspace hierarchy

1. Compact project and revision header.
2. Persistent Edit Drawing, Synchronize, Run Checks, New Revision, and Export PDF actions.
3. One consolidated engineering status strip.
4. Large dark CAD preview and sheet selector.
5. Action Required rail and concise drawing summary.
6. Detail tabs: Overview, Issues, Traceability, Revisions, Release.

## Functional preservation

The redesign preserves:

- SLD generation and revision creation.
- Editor access.
- APP67K engineering-source synchronization.
- APP67I electrical-check visibility.
- PDF, SVG, manifest, and package download routes.
- Controlled metadata updates.
- Material and datasheet traceability.
- Revision comparison and package verification.
- Submit, check, approve, reject, and formal issue workflows.

## Intentional removals from the landing viewport

- Oversized hero banner.
- Repeated blocking alerts.
- Selected-component inspector, which belongs in the editor.
- All governance cards displayed simultaneously.
- Long vertical presentation before the drawing preview.

## Validation

Run:

```bash
npm run test:sld-landing001:php
node --check public/js/nexorin-sld.js
```

Then perform a browser review at desktop widths and confirm:

- Drawing preview is visible in the first viewport.
- Tabs switch without a page reload.
- Synchronize calls the existing APP67K controller route.
- Blocked PDF opens an explanatory dialog.
- Sheet navigation, zoom, fit-width, fullscreen, and monochrome controls work.
