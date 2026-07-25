# APP67M.2 Deterministic Orthogonal Routing

## Baseline

Input baseline: APP67M.1-HF2 with Windows browser regression validated at 78/78 tests across 20 suites.

Milestone target: APP67M.2 Orthogonal Routing.

## Objective

APP67M.2 replaces the legacy per-wire visibility-grid route contract with one deterministic batch-routing contract. The editor, server-side renderer, persisted editor layer, and exported SVG now use the same version, engine identity, network priorities, terminal-lead rules, and route-integrity evidence.

This milestone does not perform automatic label placement. Label collision resolution remains APP67M.3.

## Canonical routing contract

- Version: `67M.2`
- Contract: `deterministic-orthogonal-channel-v2`
- Engine: `orthogonal-channel-a-star`
- Routing grid: 10 drawing units
- Minimum terminal lead: 24 drawing units
- Default route order: DC, AC, protective earth, signal/control, unclassified
- Route geometry: horizontal and vertical segments only
- Route identity: deterministic `rt-xxxxxxxx` signature

## Implementation

### Shared JavaScript and PHP router

The JavaScript and PHP implementations now provide equivalent routing evidence:

- obstacle and reserved-zone avoidance;
- deterministic orthogonal A* channel selection;
- terminal departure and arrival direction checks;
- crossing penalties;
- same-class channel-spacing penalties;
- power-to-signal separation penalties;
- network-class classification;
- preferred routing axis and channel;
- stable route signatures;
- explicit fallback reporting;
- deterministic network-priority batch routing.

### Editor integration

The maxGraph editor now:

- creates initial automatic routes as one deterministic batch;
- reroutes affected wires in network-priority order;
- keeps unaffected routes as fixed routing segments;
- reroutes a wire when a moved obstacle enters its current corridor;
- preserves manual route control points;
- records route contract, signature, network class, bends, crossings, clearance findings, separation findings, orthogonality, and terminal-direction state;
- displays a `ROUTE 67M.2` status badge;
- reports structural route errors separately from crossings;
- persists the APP67M.2 routing summary in editor schema version 16.

APP67M.1-HF2 drag performance remains intact. Live drag still uses the lightweight preview path and performs routing only after pointer release.

### Server-side integrity

`ProjectSldController` normalizes routing metadata with server-owned APP67M.2 constants. Browser-provided version or engine names do not override the canonical contract.

`SldEngineeringController` upgrades preserved editor layers to APP67M.2 when engineering source synchronization occurs.

`SldGraphBuilder` and `SldProfessionalTopologyPlan` stamp new sourced circuits with the APP67M.2 route contract.

### SVG export integrity

The SVG renderer rebuilds automatic routes when the saved router version or route contract is stale. Each exported wire publishes:

- `data-router`;
- `data-route-contract`;
- `data-route-strategy`;
- `data-route-signature`;
- `data-route-network-class`;
- `data-route-orthogonal`;
- `data-route-terminal-direction-ok`;
- crossing, clearance, and separation findings.

## Persistence contract

The editor routing summary contains:

- wire count;
- orthogonal wire count;
- crossing count;
- clearance-conflict count;
- separation-conflict count;
- terminal-direction violation count;
- fallback count;
- total bends;
- total route length.

Manual connection evidence contains the route contract and route metrics because those connections are browser-authored drawing objects. Sourced connections remain controlled by the engineering graph and are deterministically recomputed from their persisted control points.

## Validation

Run the deterministic milestone contract:

```powershell
npm run test:sld-app67m2
```

Expected result:

```text
[APP67M.2] Orthogonal Routing JS contract · 6/6 passed
[APP67M.2] Orthogonal Routing PHP contract · 6/6 passed
```

Run the shared router regression:

```powershell
npm run test:sld-router
```

The browser runner adds one APP67M.2 suite with four tests. The new expected total is:

```text
82/82 tests passed
21/21 suites passed
```

The four new browser tests verify:

1. canonical routing metadata on every automatic wire;
2. stable signatures after deterministic full-route rebuild;
3. rerouting when a non-connected obstacle enters a route channel;
4. persistence of routing summary and manual-wire integrity evidence.

## Deployment note

The SLD JavaScript source changed. Build the browser bundle on Windows after applying the patch:

```powershell
npm run build:sld
php artisan optimize:clear
```

Then hard-refresh the editor with `Ctrl + F5` before browser validation.

## Status

Deterministic implementation validation: passed.

Windows production-bundle build and browser regression: passed at 82/82 tests across 21/21 suites.
