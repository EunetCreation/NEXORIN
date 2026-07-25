# APP67H IEC Symbol and Port Registry

## Purpose

APP67H replaces scattered symbol, terminal, and transformation definitions with one canonical engineering registry. The registry drives the Electrical Library, maxGraph node metadata, port markers, Inspector facts, save payloads, SVG output, PDF fallback geometry, and the next electrical-rule checkpoint.

APP67H does not claim IEC certification. It records traceability and identifies whether a NEXORIN symbol is an IEC reference form, an IEC-simplified form, or a NEXORIN engineering assembly based on IEC graphical conventions.

## Canonical source

```text
resources/data/sld-symbol-registry.json
```

Registry identity:

```text
schema: nexorin.sld.symbol-registry.v1
checkpoint: APP67H
version: 67H.1
```

The release contains:

| Registry item | Count |
|---|---:|
| Total symbol definitions | 53 |
| User-facing palette symbols | 52 |
| Internal generated symbols | 1 |
| Canonical engineering ports | 174 |
| Source-topology ports | 25 |
| Library groups | 5 user-facing groups plus 1 internal group |

## Symbol groups

| Group | Symbols | Scope |
|---|---:|---|
| PV generation | 9 | Module, string, array, sub-array, junction, combiner, optimizer, RSD, microinverter |
| Conversion and storage | 12 | MPPT, DC/DC, inverter variants, PCS, battery hierarchy, BMS |
| Protection and switching | 10 | Disconnect, fuse, breakers, DC/AC protection, busbar, GFPD, AFCI, transfer, EPO |
| AC distribution and grid | 11 | Combiner, panels, transformer, ATS, utility grid, generator, AC load, grounding |
| Monitoring, metering, and safety | 10 | Monitoring, logger, gateway, meter, CT, PT, weather, annunciation, safety |
| Internal | 1 | Generated backup panel |

## IEC traceability status

Every symbol stores explicit standard metadata:

```json
{
  "status": "iec_reference | iec_simplified | nexorin_assembly",
  "status_label": "...",
  "family": "...",
  "reference": "...",
  "source_pages": "...",
  "note": "...",
  "certified": false
}
```

The current registry distribution is:

| Status | Count | Meaning |
|---|---:|---|
| `iec_reference` | 13 | Uses a direct IEC graphical family or reference form |
| `iec_simplified` | 16 | Uses a simplified single-line representation based on IEC conventions |
| `nexorin_assembly` | 24 | Represents a PLTS equipment assembly assembled from IEC-aligned primitives |

The `certified` field remains `false` for every symbol. This prevents the interface and export layer from presenting an unsupported certification claim.

## Normalized geometry contract

All equipment uses one nominal editor frame:

```text
144 × 120 editor pixels
```

Each definition also records:

- glyph box;
- optical center;
- electrical center;
- tag prefix;
- default rating;
- rotation policy;
- horizontal and vertical mirror policy;
- explicit terminal anchors.

Legacy untouched cards saved at `136 × 126` or `172 × 126` are normalized to `144 × 120` when loaded. Deliberately resized custom geometry remains unchanged.

The insertion ghost, reset-size command, source topology defaults, manual symbol defaults, paper composer, SVG renderer, and PDF fallback use the same nominal frame.

## Port coordinate system

Every port has a stable normalized anchor in the symbol frame:

```json
{
  "id": "P1",
  "network": "measurement",
  "direction": "input",
  "side": "left",
  "label": "Primary current P1",
  "terminal_class": "ct-primary",
  "required": true,
  "max_connections": 1,
  "anchor": { "x": 0.245, "y": 0.34 }
}
```

Normalized coordinates range from `0.0` to `1.0`. The editor and server renderers use the same anchor data. Rotation and mirror transformations are applied after the canonical anchor is resolved.

This contract removes the previous behavior where terminals were distributed only by side and could appear outside the actual glyph or become visually misaligned with a conductor.

## Network classes

APP67H separates network identity from visual wire style. Supported engineering classes are:

- DC positive;
- DC negative;
- DC power with unspecified polarity;
- battery positive;
- battery negative;
- battery DC with unspecified polarity;
- AC power;
- neutral;
- protective earth;
- functional earth;
- communication;
- control;
- measurement;
- unspecified power.

These identities are the data foundation for APP67I electrical rule checking. APP67H records the terminal contract. It does not yet perform the full design-rule evaluation.

## Explicit engineering cases

APP67H adds explicit definitions for cases that cannot be safely inferred from a generic left/right port layout:

- CT primary terminals `P1`, `P2` and secondary terminals `S1`, `S2`;
- PT primary and secondary measurement terminals;
- neutral terminals on panels, main distribution, and relevant AC equipment;
- protective-earth and functional-earth separation;
- emergency-power-off as a control signal;
- communication and measurement terminals separated from power terminals;
- fixed orientation and restricted mirror behavior for semantically sensitive symbols such as ground, arrester, meter, APP, and EPO.

## Runtime architecture

### PHP registry service

```text
app/Services/Sld/SldSymbolRegistry.php
```

The service validates the registry schema and provides symbol, port, palette-group, and standard-basis access to Laravel services and Blade views.

### Graph builder

`SldGraphBuilder` now emits:

- `symbol`;
- registry-defined source ports;
- `symbol_standard`;
- `symbol_registry_version`;
- graph schema `nexorin-sld-v1.3.0`.

### Editor

The JavaScript editor imports the canonical JSON directly. It no longer maintains a second independent symbol or source-port catalogue. It uses the registry for:

- Electrical Library cards;
- nominal size;
- symbol metadata;
- explicit port anchors;
- network identity;
- transform policy;
- Inspector standard and port facts;
- serialized manual nodes.

### Blade palette

The Electrical Library is rendered from `SldSymbolRegistry::paletteGroups()`. Search metadata and tooltips include the symbol status, standard family, source pages, port count, size, and tag prefix.

### Export

SVG and PDF fallback port placement first reads the serialized anchor. Older graph data without anchors uses a centered engineering fallback aligned with the symbol glyph envelope.

## Transformation policy

Most equipment supports quarter-turn rotation:

```text
0°, 90°, 180°, 270°
```

Symbols whose electrical meaning or drafting convention requires a fixed orientation expose only `0°`. Mirror operations are blocked when the registry marks the requested axis as unsafe. The toolbar and Inspector use the same policy.

## Validation

Validate registry integrity:

```bash
npm run validate:sld-registry
```

Expected result:

```text
[APP67H] PASS · 53 symbols · 52 palette symbols · 174 canonical ports · 25 source ports
```

Build the production SLD bundle:

```bash
npm run build:sld
```

Run only APP67H browser tests in PowerShell:

```powershell
$env:NEXORIN_PHP_BINARY = "C:\Users\USER\.config\herd\bin\php84\php.exe"
$env:NEXORIN_SLD_E2E_ONLY = "iec-registry"
npm run test:sld
Remove-Item Env:NEXORIN_SLD_E2E_ONLY
```

Run the full regression gate:

```bash
npm run test:sld
```

APP67H adds three browser scenarios. The full expected gate is now:

```text
37 tests · 12 suites
```

Relevant PHPUnit coverage:

```bash
php artisan test --filter=SldSymbolRegistryTest
```

## Compatibility

APP67H adds no new NPM or Composer dependency. maxGraph remains the drafting engine. The compiled public JavaScript bundle is included in the patch, so rebuilding is optional during patch installation.

Saved SLD data remains readable. Legacy nodes receive missing registry metadata and anchors at runtime. Custom sizes and user-created content are preserved.

## Engineering boundary

APP67H standardizes symbol identity and terminals. It does not yet calculate cable ampacity, protection coordination, polarity continuity, voltage compatibility, MPPT limits, fault level, or mandatory equipment completeness. Those checks belong to APP67I.

## Next checkpoint

APP67I should consume the registry to implement electrical rule checking with clickable findings, severity, rule evidence, and drawing focus.
