# APP67M.4 Visual Regression and CAD Renderer Closure

## Baseline

Input baseline: APP67M.3 Automatic Label Placement, validated on Windows at 86/86 browser tests across 22/22 suites.

Milestone target: APP67M.4 Visual Regression and final closure of the APP67M Constraint-Based CAD Renderer programme.

## Objective

APP67M.4 establishes one deterministic visual-integrity contract across model-space geometry, orthogonal routes, automatic labels, persisted editor state, and exported SVG sheets. The contract produces a canonical scene fingerprint, evaluates clipping and collision risks, and locks browser regression evidence to one repeatable rendering identity.

This milestone does not replace engineering calculations, electrical rule checking, or controlled release gates. It verifies that the approved engineering scene is rendered consistently.

## Canonical visual contract

- Version: `67M.4`
- Contract: `deterministic-scene-fingerprint-v1`
- Engine: `canonical-scene-auditor`
- Coordinate comparison tolerance: `0.5` drawing units
- Clipping tolerance: `0.5` drawing units
- Route orthogonality tolerance: `0.01` drawing units
- Identity: deterministic `vr-xxxxxxxx` FNV-1a signature generated consistently by JavaScript and PHP

## Scene fingerprint

The fingerprint uses canonical, ID-sorted records for:

1. model-space equipment rectangles and transform state;
2. persisted orthogonal route points;
3. automatic label rectangles, side, leader state, and APP67M.3 signature;
4. canonical drawing canvas.

Input order does not influence the fingerprint. A real geometry, routing, or label change produces a new identity. Changes within the configured coordinate tolerance can still be classified as visually equivalent by the comparison function.

## Visual-integrity audit

The browser auditor checks:

- equipment overlap;
- equipment outside the canonical canvas;
- label clipping;
- label-to-equipment overlap;
- label-to-label overlap;
- wire-to-label intersections;
- non-orthogonal route segments;
- route points outside the canonical canvas.

The editor publishes a status of `pass`, `warning`, or `error`, together with counts for overlap, clipping, non-orthogonal routes, and wire-label intersections.

## Editor integration

The maxGraph editor now:

- displays a `VISUAL 67M.4` status indicator;
- publishes the current fingerprint through root data attributes;
- refreshes visual evidence after geometry, route, or label recomputation;
- avoids running the visual audit inside the APP67M.1-HF2 live drag-preview loop;
- exposes visual reports, snapshots, and comparisons through the E2E contract;
- persists `editor.visual_regression` with the saved editor layer.

The persisted metadata includes canonical constants, current fingerprint, locked baseline fingerprint, status, and visual-integrity summary.

## Server control

`SldVisualRegression` independently rebuilds the canonical snapshot from normalized editor positions, routes, labels, and canvas.

`ProjectSldController` does not trust browser-provided contract names, fingerprints, status, or issue counts. PHP independently recomputes overlap, clipping, orthogonality, wire-label intersections, status, summary, and the APP67M.4 signature from normalized persisted geometry.

`SldEngineeringController` upgrades preserved editor layers to APP67M.4 whenever engineering sources are synchronized.

`SldGraphBuilder` initializes visual-regression metadata for new revisions.

No database migration is required.

## SVG and PDF evidence

Generic, professional, and combined SVG outputs publish:

- `data-visual-regression-version`;
- `data-visual-regression-contract`;
- `data-visual-regression-fingerprint`;
- `data-visual-regression-status`.

Primary PDF output inherits the same controlled SVG scene through the existing rendering pipeline. The explicitly enabled emergency vector fallback also embeds APP67M.4 version, contract, fingerprint, and integrity status in deterministic PDF metadata. APP67M.4 does not change paper size, sheet composition, title blocks, or document-control gates.

## Browser regression

APP67M.4 adds one browser suite with four tests:

1. canonical contract, fingerprint, counts, and UI evidence;
2. stable model-space fingerprint and SVG DOM after deterministic route and label rebuilds;
3. detection of a real scene change beyond tolerance;
4. persistence of fingerprint, baseline identity, tolerances, and summary.

The expected browser total is:

```text
90/90 tests passed
23/23 suites passed
```

## Validation

Run deterministic contracts:

```powershell
npm run test:sld-app67m4
```

Expected result:

```text
[APP67M.4] Visual Regression JS contract · 6/6 passed
[APP67M.4] Visual Regression PHP contract · 6/6 passed
```

Build and validate the deployed browser bundle:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/validate-app67m4.ps1
```

Run full browser regression:

```powershell
npm run test:sld
```

## APP67M programme closure

The completed renderer stack is:

- APP67M.1: canonical geometry foundation;
- APP67M.1-HF1: editor and controlled-export runtime recovery;
- APP67M.1-HF2: lightweight drag performance;
- APP67M.2: deterministic orthogonal routing;
- APP67M.3: automatic label placement;
- APP67M.4: deterministic visual regression and renderer closure.

After Windows build and browser validation at 90/90, the stable baseline becomes `APP67M.4`. Further renderer changes must begin a new milestone or an explicitly scoped critical hotfix.

## Status

Deterministic JavaScript and PHP contracts: passed.

SVG and emergency fallback PDF visual evidence: passed.

Fixture rendering and source syntax validation: passed.

Regression contracts APP67M.1 through APP67M.3 and APP67L.4 Final Closure: passed.

Windows production-bundle build and 90-test browser regression: pending user execution.
