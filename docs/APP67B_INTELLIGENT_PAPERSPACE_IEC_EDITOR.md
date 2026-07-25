# APP67B Intelligent Paper Space and IEC-Aligned Editor

## Scope

APP67B corrects the failure mode where a dense Model Space drawing was reduced into one unreadable A3 cluster. It also improves the SLD library, layer control, equipment property input, navigation, and the project landing hierarchy.

## Canonical drawing model

Model Space remains the source of truth. Paper Space does not auto-arrange or rewrite device coordinates. The composer reads the same nodes, wires, drafting geometry, annotations, layer states, and equipment metadata that the editor saves.

## Paper-space composition modes

- **Auto · fit readable** selects the safest composition.
- **Single A3** is used only while text, symbols, and spacing remain readable.
- **Overview + details** creates a functional overview followed by spatial detail sheets.
- **Symbol register** paginates unconnected library/test boards instead of shrinking all symbols into one sheet.

The composer records the composition mode, sheet count, readability state, single-sheet scale, minimum detail scale, sheet title, type, assigned nodes, bounds, and cross-sheet references.

## Dense-drawing behaviour

- A 57-symbol unconnected board becomes five readable symbol-register sheets.
- A large connected topology becomes one overview plus readable detail sheets.
- Small project drawings stay on one A3 sheet.
- Paper Space never silently changes the Model Space composition.
- Layer print state is honoured by SVG and PDF output.
- Off-sheet relationships are surfaced as cross-sheet references.

## IEC-aligned symbol direction

Priority SLD forms were redrawn as clean vector geometry for switching devices, fuse/disconnector families, grounding, transformers, CT/VT, inverter/converter, battery, meters, and surge protection. Symbols remain NEXORIN-owned production SVG geometry with explicit editor ports, snap points, states, and export behaviour.

The application describes these forms as **IEC-aligned**, not certified. Formal compliance claims require symbol-by-symbol validation against the official IEC 60617 database.

## Editor improvements

- Searchable and filterable symbol library.
- Engineering tooltip with function, family, tag prefix, and port summary.
- Persistent zoom controls and paper-sheet navigator.
- DC, AC, PE, communication, annotation, boundary, existing, and future layers.
- Layer visibility, lock, print, and solo controls.
- Numeric fields use explicit unit suffixes.
- Line can use arbitrary angles when ORTHO is off.
- Wire remains orthogonal and port-aware.
- ORTHO defaults to off for general drafting.
- Network-aware editor wire colours remain distinguishable in monochrome through labels and line patterns.

## Project landing hierarchy

The project portfolio now separates:

1. A dominant **Continue working** card for the most recently updated project.
2. A direct SLD workspace action when the user has calculation access.
3. A clearly labelled **All projects** portfolio section.
4. A large first-workspace CTA when no current project exists.

## Verification

- PHP syntax checks for controller, services, Blade views, and unit test.
- JavaScript syntax checks for source and production bundles.
- Production maxGraph bundle build.
- Manual paper-composer tests for small, large, and 57-symbol drawings.
- SVG visual inspection for symbol register, overview, and detail sheets.
- Five-page primary PDF render and visual inspection.
- Five-page deterministic fallback PDF render and visual inspection.

Full PHPUnit execution requires DOM, mbstring, XML, and XMLWriter extensions in the PHP runtime.
