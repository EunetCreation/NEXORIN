# APP67M.1 Constraint-Based Geometry Foundation

## Baseline

Input baseline: APP67L.4 operational final baseline with the Windows browser regression validated at 74 of 74 passed.

Milestone target: APP67M.1 Geometry Foundation.

## Objective

APP67M.1 establishes one deterministic geometry contract for the SLD drafting workspace. The contract governs model-space coordinates, equipment envelopes, transformed engineering ports, alignment, distribution, persistence, diagnostics, and server-side verification.

This milestone does not introduce automatic cable rerouting or automatic label placement. Those remain APP67M.2 and APP67M.3.

## Canonical geometry contract

- Version: `67M.1`
- Contract: `canonical-model-space-v1`
- Units: `drawing_unit`
- Coordinate precision: 1 decimal place
- Grid: 20 drawing units
- Canvas: 1640 x 940 drawing units
- Equipment width envelope: 80 to 800 drawing units
- Equipment height envelope: 50 to 600 drawing units
- Nominal equipment size: 144 x 120 drawing units
- Minimum equipment clearance: 20 drawing units

## Implementation

### Browser geometry module

`resources/js/sld-geometry-foundation.js` provides pure deterministic geometry operations:

1. coordinate rounding and grid snapping;
2. quarter-turn normalization;
3. equipment rectangle normalization;
4. bounds calculation;
5. alignment and equal distribution;
6. rotation and mirror transforms;
7. canonical transformed engineering-port coordinates;
8. overlap, canvas, grid, and clearance diagnostics.

### SLD editor integration

`resources/js/nexorin-sld-editor.js` and the production browser bundle now:

- use editor schema version 16;
- persist geometry contract metadata;
- normalize equipment geometry before insertion;
- calculate port positions from normalized anchors;
- use deterministic alignment and distribution;
- update live geometry health after editor changes;
- expose geometry diagnostics through the E2E contract;
- display the `GEOM 67M.1` status badge;
- expose overlap and outside-canvas issue counts.

### Server-side contract enforcement

`app/Services/Sld/SldGeometryFoundation.php` recomputes geometry metadata from saved equipment positions. The browser-supplied diagnostic summary is not trusted as authoritative.

The geometry service is integrated into:

- `SldGraphBuilder` for initial graph construction;
- `ProjectSldController` for save validation and normalization;
- `SldEngineeringController` when preserving the editor layer during controller synchronization.

Issued or superseded document-control protections from APP67L.4 remain unchanged.

## Diagnostics

The milestone records two classes of findings.

### Blocking geometry findings

- invalid rectangle;
- equipment overlap.

### Non-blocking geometry findings

- equipment outside the canonical model-space canvas;
- off-grid insertion point;
- clearance below 20 drawing units.

The current milestone reports these findings. It does not automatically reposition existing equipment.

## Validation completed

- Geometry Foundation JavaScript tests: 5 of 5 passed.
- Geometry Foundation PHP tests: 7 of 7 passed.
- APP67L.1 deterministic regression: passed.
- APP67L.2 deterministic topology regression: passed.
- APP67L.3 deterministic A3 regression: passed.
- APP67L.4 production workflow regression: passed.
- APP67L.4 Final Closure regression: passed.
- Source JavaScript syntax checks: passed.
- Production browser bundle syntax check: passed.
- Changed PHP source syntax checks: passed.
- Fixture rendering and local fixture-server boot: passed.
- The fixture page loads the `67m1` browser asset and renders the geometry status control.

The container PHPUnit command could not run because its PHP installation lacks DOM, mbstring, XML, and XMLWriter. This environment limitation does not replace the deterministic PHP suites listed above.

## Windows browser regression target

The E2E runner now contains 20 suites and expects 78 tests. Four new tests verify:

1. the canonical model-space contract;
2. transformed port anchors after a quarter turn;
3. live overlap detection and geometry health;
4. persisted editor version 16 geometry metadata.

Run:

```powershell
npm run test:sld
```

Expected result:

```text
[APP67M.1] PASS · 78/78 tests · 20/20 suites
```

## Milestone status

Implementation status: complete.

Deterministic validation status: passed.

Browser release evidence status: validated on Windows at `78/78` tests across `20/20` suites.

Stable baseline: APP67M.1-HF2. Next milestone: APP67M.2 Orthogonal Routing.
