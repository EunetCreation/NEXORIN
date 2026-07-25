# APP66A MaxGraph SLD Drafting Workspace

## Objective

APP66A replaces the previous card-based SLD editor with a full-screen engineering drafting workspace. The interface follows the locked NEXORIN 42A visual system while adopting the working structure of professional CAD software.

This patch does not replace calculation-controlled engineering data. It separates the drawing into three controlled layers:

1. `SLD-CALC-SOURCE` for generated engineering components and ratings.
2. `SLD-MANUAL` for user-added symbols and wires.
3. `SLD-ANNOTATION` for drawing notes and callouts.

## Diagram Engine

The browser editor now uses `@maxgraph/core` version `0.24.0`. The production bundle is generated from:

- `resources/js/nexorin-sld-editor.js`
- `vite.sld.config.js`
- `public/js/nexorin-sld-editor.js`

Install and build:

```bash
npm install
npm run build
```

The main build command now runs the Laravel Vite build and the dedicated maxGraph SLD bundle.

## Workspace Structure

The editor contains:

- Compact controlled-document header.
- CAD-style Draw, Modify, and View ribbon.
- Electrical symbol tool palette.
- Dark model space with minor and major grids.
- Crosshair coordinate readout.
- Properties, engineering, and layers inspector.
- Model and A3 sheet display modes.
- Engineering status bar with grid, snap, ortho, and zoom controls.

## Editing Functions

APP66A supports:

- Selecting and moving all symbols.
- Resizing drawing symbols.
- Rubber-band and multi-object selection.
- Adding electrical symbols by click or drag and drop.
- Creating manual wires by clicking a source and destination.
- Native maxGraph connection handling for drag-based connections.
- Orthogonal wire routing and editable bend points.
- Adding and editing drawing notes.
- Copy, paste, duplicate, erase, align, and bring-to-front commands.
- Undo and redo history.
- Mouse-wheel zoom and middle-mouse, right-mouse, or Space pan.
- Fit drawing, fullscreen, grid, snap, and sheet-space controls.
- Keyboard commands: `V`, `W`, `T`, `0`, `Ctrl+S`, `Ctrl+Z`, `Ctrl+Y`, `Ctrl+C`, `Ctrl+V`, `Ctrl+D`, and `Delete`.

## Persistence Contract

The editor saves version 3 data under `graph.editor`:

```text
engine
canvas
viewport
positions
routes
manual_nodes
manual_connections
annotations
```

The controller normalizes coordinate limits, viewport values, routes, manual components, wires, and annotation dimensions before saving. Browser input cannot replace the calculation snapshot or generated engineering values.

## Controlled Review and Export

`SldSvgRenderer` now reads:

- User-adjusted symbol positions.
- maxGraph routing points.
- Manual components and wires.
- Drawing annotations.

Compact browser nodes receive safe minimum dimensions in controlled SVG output so title, rating, and status data remain legible.

## Validation Completed

- `npm run build`: passed.
- Dedicated maxGraph bundle: passed.
- Browser rendering at 1728 × 1000: passed with six source devices and five source connections.
- Manual component insertion and undo: passed.
- Click-to-connect manual wire creation: passed.
- Drawing note insertion and inspector editing: passed.
- Model and A3 sheet switching: passed.
- Grid toggle: passed.
- Save payload serialization and mocked PATCH response: passed.
- SVG route and annotation rendering check: passed.
- PHP syntax checks for changed PHP files: passed.

The local PHPUnit and Blade-cache commands could not run because the execution environment lacks the PHP DOM, XML, XMLWriter, and mbstring extensions. The source-level PHP checks and isolated renderer check passed.

## Scope Limits

APP66A does not add DWG or DXF export. The existing controlled SVG and PDF workflow remains the formal document output. DWG or DXF interoperability should be implemented as a separate engineering exchange module rather than mixed into the browser editor.
