# APP66B.2 SLD CAD Line and Composition Fidelity

## Scope

APP66B.2 adds a generic CAD drafting Line command and corrects Model Space to A3 Paper Space composition fidelity.

## CAD Line command

- Shortcut: `L`
- Click a start point, then click consecutive points to draw a continuous chain.
- `Enter`, double-click, or right-click finishes the chain.
- `Ortho` constrains segments to horizontal or vertical directions.
- `Snap` aligns endpoints to the active grid.
- Holding `Shift` forces orthogonal alignment for the current segment.
- Lines are independent drafting geometry. They do not connect to electrical ports and do not participate in electrical validation.
- Line properties include layer, line type, lineweight, label, start coordinates, and end coordinates.
- Supported line types: Continuous, Hidden, and Center.
- Lines support select, endpoint editing, move, copy, paste, duplicate, delete, save, reload, A3 SVG, and PDF fallback rendering.

## Wire versus Line

- Wire is an electrical connection object. It stores source, destination, port identities, network type, and cable metadata.
- Line is generic drafting geometry for boundaries, construction marks, leaders, manual detailing, and other non-electrical linework.

## Composition fidelity fix

The previous paper renderer independently normalized saved node positions across the full A3 topology width. That behavior changed relative spacing and could force objects at the minimum and maximum X positions to opposite sides of the sheet. It could also collapse objects with similar coordinates.

APP66B.2 uses one uniform Model Space to Paper Space transform:

- Saved `x` and `y` values are treated as maxGraph top-left geometry.
- Node width and height are included when calculating content bounds.
- Drafting-line endpoints are included in the same bounds.
- One scale is applied to X and Y.
- Compact compositions are never enlarged to fill the sheet.
- Large compositions are only scaled down enough to fit the printable topology area.
- Node positions, manual lines, wire routes, and annotations retain the same relative geometry.
- Negative Model Space coordinates are preserved by server normalization.
- Saved node dimensions now use the same limits as the editor.

## Files changed

- `app/Http/Controllers/ProjectSldController.php`
- `app/Services/Sld/SldSvgRenderer.php`
- `app/Services/Sld/SldPdfRenderer.php`
- `resources/js/nexorin-sld-editor.js`
- `resources/views/slds/editor.blade.php`
- `public/css/nexorin-sld-editor.css`
- `public/js/nexorin-sld-editor.js`

## Verification

- JavaScript syntax check passed.
- PHP syntax checks passed.
- Vite production SLD build passed.
- Browser line test created two orthogonal segments and serialized two `manual_lines` records.
- Browser console errors: 0.
- Controller normalization retained negative coordinates and edited dimensions.
- SVG Paper Space preserved X and Y distance ratios with one uniform transform.
- PDF fallback preserved X and Y distance ratios with one uniform transform.
- SVG and PDF fallback previews were rendered and inspected visually.

## Installation

Install this patch after APP66B and APP66B.1. Extract it into the project root, then run:

```bash
php artisan optimize:clear
```

Perform a hard browser refresh. No Node installation or build command is required because the production JavaScript bundle is included.
