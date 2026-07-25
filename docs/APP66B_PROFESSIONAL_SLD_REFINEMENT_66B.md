# APP66B Professional SLD Engineering Refinement

## Scope

APP66B converts the APP66A maxGraph foundation into a controlled engineering drafting workspace while preserving the NEXORIN 42A visual baseline. The technical calculation remains authoritative. The browser editor stores drafting layout, display preferences, manual additions, connection routes, and object exclusions.

## Delivered changes

### Dedicated drafting viewport

- The editor uses a dedicated `100dvh` layout.
- The normal application header, sidebar, footer, and page scroll do not enter the drafting surface.
- Library, canvas, and Inspector manage their own available height.
- The status bar remains visible at the bottom of the viewport.
- Browser Fullscreen API support is available through the toolbar or `F`.
- `Esc` exits fullscreen. It does not close the editor.

### Discoverable panel management

- Library and Inspector collapse into persistent side rails instead of disappearing.
- The rails remain clickable and clearly labelled.
- `L` toggles the Library.
- `P` toggles the Inspector.
- The current panel state is saved in the editor layer.

### Clean engineering symbols

- Card-style dashboard nodes were removed from Model Space.
- Devices use custom technical SVG line symbols.
- The default Clean mode shows only the symbol, device name, and primary rating.
- Equipment tags remain available as controlled metadata but stay hidden by default.
- Engineering and Inspection modes can expose additional information.
- Internal object IDs such as `manual_inverter_*` never appear on the canvas.
- Manual equipment receives a readable automatic tag, for example `INV-02`, inside the Inspector.

### Calculated-object governance

Calculated equipment keeps its technical authority while allowing drafting changes.

Editable:

- position;
- routing relationship;
- sheet participation;
- exclusion and restoration;
- viewport and display arrangement.

Read-only:

- calculated rating;
- calculated capacity;
- calculated equipment description;
- calculated dimensions of the technical symbol;
- source authority.

Generated equipment uses **Exclude from drawing** rather than destructive deletion. Excluded equipment can be restored from the Engineering tab.

### Selection and interaction

- Selection geometry now matches the maxGraph cell and its rendered technical object.
- Oversized neon handles were replaced with compact blue engineering grips.
- Calculated devices can move but cannot be resized.
- Manual devices can move and resize.
- Selection, ports, and editor interaction states do not enter exported output.

### Typed port foundation

Graph schema: `nexorin-sld-v1.2.0`.

Nodes now define explicit logical ports. Connections preserve:

- `from_port`;
- `to_port`;
- `network`.

The browser checks network and direction compatibility before creating a manual wire. Initial profiles cover DC power, AC power, battery DC, protective earth, and communication. This is the foundation for deeper engineering validation in a later revision.

### Model Space

- Dark controlled drafting canvas.
- Major and minor engineering grid.
- Snap, orthogonal routing, zoom, pan, fit, coordinate readout, undo, and redo.
- Source and manual layers remain visually distinguishable without changing symbol meaning.
- Clean, Engineering, and Inspection display modes.

### A3 Paper Space

- A3 Paper Space uses the professional server-rendered drawing composition.
- It does not capture Model Space as a screenshot.
- The sheet includes drawing header, compact design basis, primary topology, conductor labels, PE reference, notes, used-symbol legend, and title block.
- The A3 preview refreshes after saving the draft.
- SVG and PDF outputs remain free from grid dominance, selection handles, source badges, and editor controls.
- A deterministic vector PDF fallback remains available when the premium browser PDF renderer is unavailable.

## Keyboard controls

| Action | Shortcut |
|---|---|
| Select | `V` |
| Wire | `W` |
| Note | `T` |
| Library | `L` |
| Inspector | `P` |
| Fullscreen | `F` |
| Exit fullscreen | `Esc` |
| Fit drawing | `0` |
| Search symbol | `/` |
| Save | `Ctrl/Cmd + S` |
| Undo | `Ctrl/Cmd + Z` |
| Redo | `Ctrl/Cmd + Y` |
| Copy | `Ctrl/Cmd + C` |
| Paste | `Ctrl/Cmd + V` |
| Duplicate | `Ctrl/Cmd + D` |
| Delete or exclude | `Delete` |

## Deployment

The production maxGraph bundle is already included in `public/js/nexorin-sld-editor.js`.

Do not run `npm install` or `npm run build` for normal patch deployment.

After copying the patch into the project root, run:

```bash
php artisan optimize:clear
```

Then perform a hard refresh in the browser.

## Verification completed

- JavaScript syntax check passed.
- PHP syntax checks passed for the controller and SLD services.
- Production SLD bundle built successfully.
- Model Space rendered without browser console errors.
- Selection geometry was visually and numerically checked.
- Library and Inspector rails reopen by click and keyboard shortcut.
- Fullscreen entry and exit were browser-tested.
- Viewport height, page overflow, and fixed status bar were browser-tested.
- Manual inverter creation produced `INV-02` and no raw internal ID on the canvas.
- A3 Paper Space and standalone SVG previews were inspected.
- Vector fallback PDF was generated and raster-checked.
- Direct SLD builder, SVG, port schema, and PDF smoke checks passed.

The PHPUnit runner could not execute in the build environment because the installed PHP runtime lacks DOM, mbstring, XML, and XMLWriter extensions. This is an environment limitation, not a test assertion failure.
