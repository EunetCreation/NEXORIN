# APP67G.3 Playwright Full Interaction Regression

## Purpose

APP67G.3 adds a repeatable browser regression gate for the NEXORIN SLD drafting workspace. The suite exercises the real Blade editor, the production maxGraph bundle, the production CSS, and the editor serialization contract.

The fixture uses deterministic engineering data and an in-memory save endpoint. It does not require PostgreSQL, Redis, queue workers, or access to confidential project data.

## Test command

```bash
npm run test:sld
```

Additional commands:

```bash
npm run test:sld:headed
npm run test:sld:ui
npm run test:sld:report
```

Run one or more named shards during diagnosis:

```bash
NEXORIN_SLD_E2E_ONLY=boot npm run test:sld
NEXORIN_SLD_E2E_ONLY=wire,manage-transform npm run test:sld
```

On Windows Command Prompt, use `set NEXORIN_SLD_E2E_ONLY=boot` before the test command. In PowerShell, use `$env:NEXORIN_SLD_E2E_ONLY='boot'`.

Install the browser once on a new workstation:

```bash
npm install
npx playwright install chromium
```

## Regression matrix

The release gate contains 34 browser tests.

| Area | Coverage |
|---|---|
| Boot contract | Real Blade workspace, maxGraph initialization, browser error capture, engineering port identity |
| Selection | Single selection, multi-selection, left-to-right block selection, Ctrl+A, explicit multi-selection state |
| Drafting | Line, rectangle, note, associative leader, four circle methods, four arc methods, Escape cancellation |
| View | Fit drawing, zoom, pan, fullscreen, Model Space, A3 Paper Space |
| Display modes | Clean, Engineering, Inspection |
| Ribbon and panels | All ribbon tabs, ribbon collapse, library collapse, Inspector collapse, persistent panel rails |
| Electrical library | Search, category filtering, symbol placement |
| Inspector | Properties, Engineering, Layers, manual fields, controlled fields, tag override, layer state |
| Clipboard and history | Cut, copy, paste, duplicate, undo, redo, delete |
| Manage | Align, distribute, rotate, mirror, reset transform, group, ungroup, bring front, send back |
| Wire | Compatible port connection, incompatible network rejection, no dangling edge, metadata editing, route rebuild |
| Persistence | Save, reload, editor serialization, layers, paper mode, panel state, dirty-close flow |

## Bugs identified and corrected

### 1. A3 Paper Space button had no command binding

The Blade interface rendered Model Space and A3 Paper Space controls, but the JavaScript did not attach a click listener to `data-editor-space`. APP67G.3 binds both controls to `setSpace()` and exposes the current space through `data-active-space` for deterministic UI verification.

### 2. Associative Leader could not be edited reliably

Selecting a Leader also selected its linked annotation. The Inspector treated the pair as an unsupported multi-selection and hid the editable form. APP67G.3 resolves an associative Leader group to its annotation cell for Inspector editing while preserving the grouped drafting behavior.

### 3. Inspector could overwrite text while the user was typing

Selection and model refreshes called `updateInspector()` and reassigned every field value. An active field could revert before its change handler completed. APP67G.3 does not overwrite the currently focused field.

### 4. Regression readiness was not observable

The editor now publishes `data-editor-ready="false|true"` and dispatches `nexorin:sld-ready` after initialization. This removes timing guesses from automated tests.

### 5. Windows path handling in the test runner

The test runner and fixture server now resolve ESM file paths with `fileURLToPath()`. This avoids malformed `/C:/...` or `/D:/...` paths on Windows.

### 6. A failed browser process could leave the regression command hanging

Each regression shard has a configurable timeout. The default is 120 seconds:

```text
NEXORIN_SLD_E2E_SUITE_TIMEOUT_MS=120000
```

The runner also terminates the fixture server process tree on Windows when normal shutdown does not complete.

## E2E-only diagnostic contract

The production editor exposes the diagnostic API only when the root contains:

```html
 data-editor-e2e="true"
```

Normal application pages do not expose it. The fixture uses the API to read deterministic snapshots, select known cells, trigger save, and inspect cell geometry. User-facing editing still occurs through the actual interface controls and browser events.

## Result

All 34 scenarios passed in isolated Playwright shards using Chromium. The production SLD bundle also passed JavaScript syntax checks, PHP fixture linting, the dedicated SLD build, and the full frontend build.

The maxGraph build emits its existing direct `eval` warning from `@maxgraph/core/internal/utils.js`. The warning originates in the dependency. It does not fail the build.

## Generated reports

After `npm run test:sld`, the runner writes:

```text
test-results/sld-e2e-summary.json
test-results/sld-e2e-summary.md
```

Failure evidence can include Playwright traces and screenshots under `test-results/sld/`.

## Next checkpoint

APP67H should establish a formal IEC symbol and port registry. APP67G.3 must remain the regression gate for every symbol, terminal, routing, Inspector, and serialization change introduced after this checkpoint.
