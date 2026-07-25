# APP67K · PLTS Engineering Controller Integration

## Purpose

APP67K connects the controlled SLD graph to the current engineering source chain. It prevents Technical Calculation, SLD cable/protection data, BOM/BOQ, Financial Analysis, and Quotation from silently diverging.

## Source authority

| Domain | Authority |
|---|---|
| System topology, equipment ratings, cable sizes, protection ratings | Technical Calculation and System Recommendation |
| Equipment placement, routing, annotations, visibility, paper-space composition | SLD draft editor |
| Quantities and commercial scope | BOM/BOQ snapshot |
| CAPEX and investment metrics | Financial Analysis snapshot |
| Customer-facing commercial document | Quotation snapshot |

The SLD may preserve drafting layout and manual objects, but it may not overwrite calculation-controlled ratings on sourced equipment and circuits.

## Controlled source chain

1. Project source, Load Analysis, Solar Profile, Roof/Site Survey, and System Recommendation feed Technical Calculation.
2. Technical Calculation controls SLD topology, canonical ports, cable schedule, and protection schedule.
3. BOM/BOQ must reference the current Technical Calculation and contain the required equipment, cable, and protection item codes.
4. Financial Analysis must reference the current BOQ total.
5. Quotation must reference the current BOM/BOQ and Financial Analysis.

## Controller statuses

- `synchronized`: no controller errors or unresolved warnings.
- `review`: non-blocking warning or notice requires engineering review.
- `blocked`: one or more source, topology, cable, or stale-BOM errors block submit, approve, SVG, and PDF.

Saving a draft remains allowed when blocked. This protects user work while preventing controlled release.

## Main conflict codes

| Code | Meaning | Blocking |
|---|---|---:|
| K001 | Current source fingerprint differs from drawing source | Yes |
| K002 | Technical Calculation unavailable | Yes |
| K003 | Calculation not engineer verified | No |
| K010 | Required sourced equipment missing | Yes |
| K011 | Unexpected sourced topology object | No |
| K020 | Controlled circuit missing | Yes |
| K021 | Cable metadata differs from calculation | Yes |
| K030 | Protection schedule incomplete | No |
| K040 | BOM/BOQ not generated | No |
| K041 | BOM/BOQ snapshot stale | Yes |
| K042 | BOM scope coverage incomplete | No |
| K050 | Financial Analysis downstream-stale | No |
| K051 | Quotation downstream-stale | No |

## Synchronization behavior

`Synchronize source` rebuilds the sourced graph and applies current cable and protection data while preserving:

- sourced equipment positions and dimensions;
- routing and viewport state where still valid;
- manual equipment;
- manual wires;
- line, polyline, circle, arc, rectangle, and leader geometry;
- annotations and layer state;
- audit history and acknowledgements.

## Controlled output

Submit, approve, SVG, and PDF run the controller again on the server. Controlled export responses include:

- `X-NEXORIN-SLD-Validation`
- `X-NEXORIN-SLD-Source`

## Verification

Run:

```powershell
php artisan optimize:clear
npm run validate:sld-registry
npm run validate:sld-rules
npm run test:sld-rules:js
php scripts/test-sld-rules.php
npm run test:sld-router
npm run test:sld-controller:php
```

Focused browser test:

```powershell
$env:NEXORIN_PHP_BINARY = "C:\Users\USER\.config\herd\bin\php84\php.exe"
$env:NEXORIN_SLD_E2E_ONLY = "engineering-controller"
npm run test:sld
Remove-Item Env:NEXORIN_SLD_E2E_ONLY
```

Target: `5/5` focused and `64/64` full regression.
