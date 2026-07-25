# APP67J.2 · Precision Resize and Visible Engineering OSNAP

## Objective

APP67J.2 closes two P0 drafting gaps found during manual web testing:

1. selected equipment and drafting objects did not provide a reliable, repeatable resize interaction;
2. Wire and drafting OSNAP targets were too small or invisible, especially END, MID, NEAR, CENTER, INTERSECTION, and PORT.

## Precision resize contract

- A single unlocked vertex in Model Space exposes eight resize grips.
- East and west grips modify width only.
- North and south grips modify height only.
- Corner grips modify both dimensions.
- Holding Shift on a corner grip preserves the envelope ratio.
- Grid snap remains authoritative when Snap is enabled.
- Width and Height remain editable in Inspector, including calculation-controlled equipment.
- Electrical ratings and source descriptions remain locked to Technical Calculation.
- IEC glyph markup is never stretched or rewritten during envelope resize.
- Canonical port anchors remain proportional to the resized envelope.
- Connected automatic wires are rebuilt after resize completion.

## Engineering OSNAP contract

Visible markers and tokens:

- END: drafting endpoints;
- MID: segment midpoint;
- NEAR: nearest point on drafting or electrical wire segment;
- CEN: circle or arc center;
- GEO: geometric or insertion center;
- QUAD: circle quadrant;
- INT: geometric intersection;
- PORT: canonical engineering terminal.

Electrical Wire accepts PORT only. END, MID, NEAR, CENTER, and INTERSECTION remain drafting references and cannot silently create an electrical pseudo-junction.

## IEC symbol protection

Representative IEC-aligned symbols were visually audited at production scale: String inverter, Power transformer, DC circuit breaker, DC fuse, Distribution board, Energy meter, CT, VT, Protective earth, Grounding electrode, Lightning arrester, and Battery bank.

The component envelope may change, but the symbol glyph preserves its own geometry and aspect. This prevents the stretched-transformer problem observed during APP67J manual testing.

## Verification

- APP67J.2 production-bundle browser black-box: 5/5 PASS.
- Existing APP67H registry validator: PASS.
- Existing APP67I rule validators: PASS.
- Existing APP67J JS/PHP router stress suites: PASS.
- Vite production build: PASS, 270 modules.
- Playwright discovery: 51 tests in 11 files, 15 isolated runner suites.

The normal HTTP Playwright run must still be executed on the user Windows environment because the build sandbox blocks Chromium access to local HTTP pages by administrator policy.
