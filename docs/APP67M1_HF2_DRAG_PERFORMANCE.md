# APP67M.1-HF2 Drag Performance

## Status

Implementation complete and validated on Windows.

## Problem

The APP67M.1 object-move controller updated model geometry, rebuilt connected smart routes, and refreshed the complete maxGraph canvas for every pointer event. Select-mode pointer tracking also executed the drafting object-snap pipeline even when no drafting command was active. Large professional drawings therefore produced visible drag latency.

## Correction

- Object movement now uses a lightweight SVG bounding-box preview while the pointer remains down.
- Pointer events are coalesced through `requestAnimationFrame`.
- Model geometry is committed once when the pointer is released.
- Connected smart routes rebuild once after the move commit.
- Electrical validation and geometry diagnostics run after the committed change, not during each pointer event.
- Select-mode pointer tracking bypasses object-snap candidate generation and drafting overlays.
- A dedicated E2E diagnostic contract reports pointer events, rendered frames, commits, and route synchronizations.

## Safety properties

- No database or migration change.
- No change to controlled-output gates.
- No change to electrical topology authority.
- One drag remains one undoable model edit.
- Cancelled pointer interactions do not mutate model geometry.
- Existing smart routing remains authoritative after the deferred post-drop rebuild.

## Validation

Run:

```powershell
npm run test:sld-app67m1-hf2
npm run test:sld
```

Expected deterministic contract:

```text
[APP67M.1-HF2] Drag performance contract · 8/8 passed
```

The stable APP67M.1-HF2 browser baseline was validated at 78/78 tests across 20 suites. The dedicated HF2 deterministic contract was validated at 8/8 passed.
