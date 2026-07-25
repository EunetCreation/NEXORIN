# APP67J.2.4 · Drafting State Separation and Overlay Cleanup

## Contract

- Select is the only mode that exposes precision resize grips.
- Drafting and Wire commands clear active selection before accepting points.
- Passive OSNAP candidate clouds are never rendered.
- Only the single best resolved OSNAP marker is visible.
- First-point guidance remains in the command line.
- Cursor dynamic input appears only after an anchor exists or a numeric value is typed.
- Escape returns a clean Select state.

## Why

Selection, resize, drafting, and OSNAP overlays previously remained visible at the same time. That made the canvas look like a debug workspace and created ambiguous targets. APP67J.2.4 enforces one active interaction intent at a time.
