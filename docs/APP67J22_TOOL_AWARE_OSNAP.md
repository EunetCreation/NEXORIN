# APP67J.2.2 · Tool-Aware OSNAP

## Purpose

APP67J.2.2 separates engineering connection intent from geometric drafting intent.

- **Wire** uses canonical engineering `PORT` snap.
- **Line, Polyline, Rectangle, Circle, Arc, Leader, Text, and Symbol placement** use geometric OSNAP only.
- Geometric drafting cannot imply or create an electrical connection.

## Tool matrix

| Tool | Engineering PORT | END | MID | NEAR | CEN | GEO | QUAD | INT |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Wire | Yes | No | No | No | No | No | No | No |
| Line / Polyline | No | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Rectangle | No | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Circle / Arc | No | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Leader | No | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Text / Insert | No | Yes | Yes | Yes | Yes | Yes | Yes | Yes |

Wire deliberately rejects geometric pseudo-junctions. Electrical connectivity requires a canonical terminal defined by APP67H and accepted by APP67I.

## UI refinement

- PORT marker and engineering card appear only while Wire is active.
- Wire cursor dynamic input is suppressed. The command line carries the current instruction.
- PORT card is compact: 220 px wide and approximately 42 px high in the test fixture.
- Geometric OSNAP uses only the small marker token such as `END`, `MID`, `NEAR`, or `INT`.
- OSNAP settings explicitly mark Engineering Port as `WIRE ONLY`.
- Dynamic input for geometric drafting uses a compact dark engineering style.
- Asset version is `67j5`.

## Black-box verification

Static Chromium verification uses the real Blade fixture, final CSS, maxGraph, and final production bundle.

1. PORT exclusive to Wire: PASS.
2. Compact Wire engineering card: PASS.
3. Line removes engineering marker and card: PASS.
4. END and MID remain active for drafting: PASS.
5. APP67J.2.1 resize and OSNAP legacy suite: 5/5 PASS.

Supporting validators remain green:

- APP67J JavaScript router: PASS.
- APP67J PHP router and SVG parity: PASS.
- APP67H registry: PASS.
- APP67I rule registry and JS/PHP smoke: PASS.

## Windows Playwright target

```powershell
$env:NEXORIN_PHP_BINARY = "C:\Users\USER\.config\herd\bin\php84\php.exe"
$env:NEXORIN_SLD_E2E_ONLY = "tool-aware-osnap"
npm run test:sld
Remove-Item Env:NEXORIN_SLD_E2E_ONLY
```

Focused target: `4/4`.

Full target after the patch: `55/55 tests · 16/16 suites`.
