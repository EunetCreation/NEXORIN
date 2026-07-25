# APP67L.2 · Professional Physical PLTS Topology Generator

## Objective

APP67L.2 replaces the six-node aggregate SLD baseline with a deterministic physical engineering topology while preserving the APP67K authority model, smart routing, drafting tools, persistence, and controlled release behavior.

The topology is generated from the latest verified Technical Calculation and System Recommendation. Drawing geometry remains drawing-controlled; ratings, ports, string allocation, cable metadata, and protection metadata remain engineering-controlled.

## Generated topology profile

- Profile: `professional_physical`
- Version: `67L.2`
- Graph schema: `nexorin-sld-v1.8.0`
- Protection mode: `individual_components`

For the APP67L controlled 52.8 kWp baseline, the generator creates:

- 2 physical PV string groups
- 2 DC isolators
- 2 DC fuses
- 2 protected DC buses
- 2 parallel DC SPDs
- 2 physical string inverters
- 2 AC circuit breakers
- 2 AC isolators
- 1 AC combiner panel
- 1 parallel AC SPD
- 1 energy meter
- 1 utility grid connection
- 1 PE busbar
- 1 grounding electrode
- 1 data logger
- 1 plant controller for zero-export control

Total baseline contract:

- 24 physical devices
- 35 controlled circuits
- 11 protection schedule rows
- 0 deterministic electrical-rule findings

## Power topology

```text
PV STRING GROUP 01
  → DC ISOLATOR 01
  → DC FUSE 01
  → PROTECTED DC BUS 01
  → INVERTER 01
  → AC BREAKER 01
  → AC ISOLATOR 01
  ┐
  ├→ AC COMBINER → ENERGY METER → UTILITY GRID
  ┘
PV STRING GROUP 02
  → DC ISOLATOR 02
  → DC FUSE 02
  → PROTECTED DC BUS 02
  → INVERTER 02
  → AC BREAKER 02
  → AC ISOLATOR 02
```

## SPD topology

SPDs are generated as parallel protection branches, not as series equipment.

```text
PROTECTED DC BUS 01 → DC SPD 01 → PE BUS
PROTECTED DC BUS 02 → DC SPD 02 → PE BUS
AC COMBINER          → AC SPD 01 → PE BUS
```

## Protective earth topology

Mandatory PE connections are generated automatically for PV groups, protection devices, inverters, AC collection, metering, monitoring, and plant control.

```text
PHYSICAL EQUIPMENT → PE BUS → GROUNDING ELECTRODE
```

The generated baseline produces no missing-PE warning.

## Monitoring and zero-export control

```text
INVERTER 01 COM ─┐
INVERTER 02 COM ─┼→ DATA LOGGER → PLANT CONTROLLER
ENERGY METER COM ┘                    │
                                     └→ closed-loop control link
```

Communication and control networks use separate canonical port and cable definitions.

## String and inverter authority

For the 96-module baseline:

- 6 strings × 16 modules = 96 modules
- INV-01: 48 modules, 3 strings, 3 of 4 MPPT used
- INV-02: 48 modules, 3 strings, 3 of 4 MPPT used
- Unit inverter rating: 25 kW
- Aggregate inverter rating: 50 kW
- AC voltage: 400 V
- DC/AC ratio: 1.056

Each inverter is an independent physical node and exposes only the on-grid port profile:

- `DC_IN`
- `AC_OUT`
- `PE`
- `COM`

## Frontend and backend contract

The backend publishes an explicit `default_position` for every physical node. The production editor now honors that layout on first render, Reset Layout, synchronization, SVG rendering, and PDF rendering.

The frontend retains all source engineering metadata on the maxGraph node value so the Inspector can display:

- unit rated power
- rated voltage
- rated current
- aggregate power
- inverter/string assignment
- MPPT utilization
- engineering binding and source authority

## Compatibility

APP67L.2 preserves support for existing aggregate revisions. Existing APP67K source profiles and legacy fixture IDs remain available for previously saved drawings and regression coverage.

The physical topology is used for new graphs generated from the latest source calculation.

## Validation coverage

- symbol registry validation
- electrical rule registry validation
- JavaScript and PHP electrical rule parity
- JavaScript and PHP smart-router parity
- APP67K Controller reconciliation
- APP67L.1 symbol/data regression
- APP67L.2 physical topology integration
- real Laravel/Blade/production-bundle browser black-box
- Playwright professional-topology suite
- full SLD regression suite

## Deliberate boundary

APP67L.2 completes the professional physical Model Space topology. Paper-space composition remains outside the APP67L.2 topology contract. The professional A3 composition, MPPT and equipment schedules, cable and protection schedules, IEC-aligned legend, controlled title block, print-safe SVG, and three-page PDF fallback are implemented separately in APP67L.3.

## APP67L.3-R3 zero-export enhancement

The original APP67L.2 baseline above records the historical 24-node, 35-circuit topology. APP67L.3-R3 extends the current zero-export professional profile without changing the profile identifier or authority model.

The current zero-export baseline now generates:

- 25 physical nodes
- 38 port-aware controlled circuits
- 1 conditional current transformer
- 2 separate CT secondary measurement circuits
- independent string-fuse and DC switch-channel metadata
- route-specific cable-length semantics
- 0 deterministic electrical-rule findings

The current AC and zero-export path is:

```text
INVERTER FEEDERS
  → AC COMBINER
  → CURRENT TRANSFORMER
  → POWER METER
  → PCC / UTILITY GRID

CT S1 / S2
  → POWER METER MEASUREMENT INPUTS
  → PLANT CONTROLLER
  → ACTIVE-POWER LIMIT COMMAND
  → INVERTER NETWORK
```

The CT node is generated only when zero-export or export-limitation control is required. Non-zero-export professional projects continue to connect the AC combiner directly to the meter and preserve their synchronized controller contract.

## APP67L.3-R5 current paper-space extension

The earlier APP67L.2 through APP67L.3-R4 sections remain historical records. APP67L.3-R5 retains the corrected R4 PCC, metering, export-trip, schedule, and document-control behavior while replacing paper-space layout with collision-controlled lanes and rebuilt legends.

The current deterministic fixture generates:

- 25 physical nodes
- 45 graph connections
- 53 traceable cable and wiring rows
- 13 protection-schedule rows
- 0 deterministic electrical-rule errors
- 0 deterministic electrical-rule warnings

The controlled main AC path is:

```text
INVERTER FEEDERS
  → AC COMBINER
  → PCC MAIN CIRCUIT BREAKER
  → CURRENT TRANSFORMER PRIMARY
  → LOCKABLE PCC ISOLATOR
  → PCC / UTILITY GRID
```

The CT-operated meter is outside the primary power path. The zero-export system has two distinct layers:

```text
Meter measurement → Plant controller → Inverter active-power commands
Protection CT core → Reverse-power relay → Hardwired PCC-breaker trip
```

The current one-string-per-MPPT fixture does not assert installed string fuses without a final product- or code-specific requirement. DC and AC SPDs remain parallel protection branches. `IF-DC-01` is removed, and cable-schedule tags now reconcile with the drawing tags.

The APP67L.3-R5 paper-space package uses six A3 sheets and remains `FOR INTERNAL REVIEW` and `NOT FOR CONSTRUCTION`.
