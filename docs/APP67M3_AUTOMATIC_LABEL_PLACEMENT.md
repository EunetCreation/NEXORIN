# APP67M.3 Automatic Label Placement

## Baseline

Input baseline: APP67M.2 with Windows browser regression validated at 82/82 tests across 21 suites.

Milestone target: APP67M.3 Automatic Label Placement.

## Objective

APP67M.3 separates equipment identification text from symbol geometry and places each label through one deterministic clearance-aware contract. The editor, persisted editor layer, engineering synchronization, and SVG export now publish the same placement identity and conflict evidence.

This milestone does not introduce free-form manual label dragging. The placement is derived from controlled equipment data and recomputed when geometry or routing changes.

## Canonical label contract

- Version: `67M.3`
- Contract: `deterministic-clearance-label-v1`
- Engine: `candidate-score-label-placer`
- Equipment gap: 14 drawing units
- Label clearance: 12 drawing units
- Wire clearance: 8 drawing units
- Candidate sides: bottom, top, right, left
- Identity: deterministic `lp-xxxxxxxx` signature generated identically by JavaScript and PHP

## Placement behavior

For every visible equipment object, the engine:

1. estimates the engineering label envelope from tag, title, rating, and subtitle;
2. transforms terminal sides through rotation and mirror state, then orders candidate sides from the resulting terminal density;
3. scores equipment overlap, prior-label overlap, wire intersection, and canvas overflow;
4. selects the lowest deterministic score;
5. creates a leader when the selected position is constrained or separated from its equipment anchor;
6. records fallback and conflict evidence without silently changing engineering data.

Input order does not affect the output. Equipment IDs define the stable processing order.

## Editor integration

The maxGraph editor now:

- displays one derived label object for each visible equipment object;
- removes duplicated text from the symbol envelope;
- makes generated labels and leaders non-selectable and non-editable;
- recomputes labels after automatic routing or equipment movement;
- excludes derived labels from engineering object counts, routing obstacles, persistence object lists, and geometry diagnostics;
- displays a `LABEL 67M.3` health indicator;
- records placement bounds, side, leader state, fallback state, conflict counts, and signature;
- retains APP67M.1-HF2 lightweight drag behavior and APP67M.2 deterministic routing.

## Persistence and server control

Editor schema version 16 remains unchanged. The new `editor.label_placement` section contains:

- canonical version, contract, and engine;
- clearance values;
- placement map keyed by equipment ID;
- label, leader, fallback, and conflict summary.

`ProjectSldController` accepts the browser placement evidence but normalizes all constants, dimensions, bounds, counts, and signatures on the server.

`SldEngineeringController` upgrades preserved editor layers to APP67M.3 when engineering source synchronization occurs.

`SldGraphBuilder` initializes the canonical label-placement metadata for new revisions.

No database migration is required.

## SVG export integrity

Generic engineering sheets recompute placement in paper space after routing is complete. Each exported automatic label publishes:

- `data-label-placement-version`;
- `data-label-placement-contract`;
- `data-label-placement-side`;
- `data-label-placement-signature`;
- `data-label-placement-leader`.

The SVG includes a controlled label box and, where required, a leader line. Professional schedule sheets retain their specialized fixed-layout renderer.

## Validation

Run the deterministic milestone contracts:

```powershell
npm run test:sld-app67m3
```

Expected result:

```text
[APP67M.3] Automatic Label Placement JS contract · 6/6 passed
[APP67M.3] Automatic Label Placement PHP contract · 6/6 passed
```

Build and validate the deployed browser bundle:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/validate-app67m3.ps1
```

The browser runner adds one APP67M.3 suite with four tests. The new expected total is:

```text
86/86 tests passed
22/22 suites passed
```

The four new browser tests verify:

1. canonical contract, derived-label count, signatures, and UI evidence;
2. stable signatures after deterministic label rebuild;
3. label relocation after equipment movement;
4. persistence of label placement metadata.

## Deployment note

The SLD JavaScript source changed. After applying the patch on Windows, run:

```powershell
npm run build:sld
php artisan optimize:clear
```

Then hard-refresh the editor with `Ctrl + F5`.

## Status

Deterministic JavaScript and PHP validation: passed.

Windows production-bundle build and browser regression: passed at 86/86 tests across 22/22 suites.
