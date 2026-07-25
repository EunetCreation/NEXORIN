# APP67L.3-R5 · Collision-Controlled Professional A3 Drawing Package

## Objective

APP67L.3-R5 retains the validated R4 electrical topology and converts the controlled professional PLTS topology into a six-sheet A3 landscape drawing package for internal engineering review. R5 focuses on paper-space collision control, symbol envelopes, dedicated routing lanes, shorter annotations, rebuilt legends, and print-safe clearances while retaining R4 topology correctness and traceability.

The package is not a construction release. It remains `FOR INTERNAL REVIEW`, `NOT FOR CONSTRUCTION`, and `pending independent conformity assessment` until final product, utility, site, protection, earthing, cable-routing, checker, and approver data are available.

## Reference use

The supplied industrial PV SLD was used as a composition reference for the coordinate frame, string and MPPT grouping, AC collection, metering, monitoring, schedules, revision block, and title block.

The supplied IEC 60617 document was used only as a functional symbol reference for conductors, junctions, earthing, isolators, circuit breakers, surge protection, converters, current transformers, meters, and control functions. No IEC certification claim is made for NEXORIN, its symbol library, or its generated drawings.

## R4 engineering corrections

R4 closes the following review findings:

- Adds `QF-PCC-01`, the main PCC circuit breaker.
- Adds `QS-PCC-01`, the visible, lockable PCC isolator.
- Places `CT-01` on the main AC feeder while keeping `MTR-01` outside the primary power path.
- Adds separate CT metering and protection secondary circuits.
- Adds `RPR-01`, an independent reverse-power relay with a hardwired trip path to the PCC breaker.
- Keeps software zero-export control through the meter, plant controller, data logger, and inverter control ports.
- Removes the undefined `IF-DC-01` tag.
- Aligns Sheet 1 device tags with all cable- and protection-schedule cross-references.
- Expands the cable schedule into individually traceable string, power, protection, communication, control, measurement, and PE rows.
- Draws AC and DC SPDs as protected parallel branches rather than series devices.
- Treats string fuses as optional for the one-string-per-MPPT architecture and does not assert installed fuse devices without a final product-specific requirement.
- Separates Ethernet Cat6, RS485 shielded pair, hardwired trip, CT secondary, and power-cable specifications.
- Removes unsupported precision for annual energy and voltage-drop results from the controlled drawing set.
- Changes MPPT status from unconditional `PASS` to `PRELIM CALC PASS / PRODUCT TBC`.
- Exposes the AC earthing system, PCC fault level, breaker duty, CT specification, SPD duty, and final cable arrangement as open engineering inputs.
- Changes the release description from engineering finalization to internal engineering review.

## Drawing-set contract

Professional physical topologies compose into six deterministic sheets:

| Sheet | Type | Drawing title | Primary purpose |
|---|---|---|---|
| 1 | `professional_sld` | Single-line diagram · On-grid PV system | DC strings, inverter feeders, AC collection, PCC breaker, CT, lockable isolator, grid, SPD branches, and PE topology |
| 2 | `control_schematic` | Zero-export metering, control and failsafe | CT secondary circuits, meter, PPC, data logger, inverter commands, reverse-power relay, and hardwired trip |
| 3 | `string_schedule` | MPPT, string and equipment schedule | Preliminary string checks, design inputs, product status, equipment summary, and symbol legend |
| 4 | `cable_schedule` | Cable schedule · Rows 1-27 | First half of individually traceable cable and wiring rows |
| 5 | `cable_schedule` | Cable schedule · Rows 28-53 | Second half of individually traceable cable and wiring rows |
| 6 | `protection_schedule` | Protection schedule and open engineering data | Protection devices, provisional ratings, final-duty fields, open inputs, notes, and release limitations |

Every sheet uses:

- A3 landscape, 420 mm × 297 mm
- NTS scale
- Composition version `67L.3-R4`
- Revision `R4`
- Controlled sheet numbering
- `FOR INTERNAL REVIEW`
- `NOT FOR CONSTRUCTION`
- `MODEL DATA LINKED`
- `ENGINEERING GAPS OPEN`

## Sheet 1 · Main single-line diagram

The main SLD provides:

- Six independent PV strings and six MPPT assignments
- One switching channel per string
- Product-specific fuse requirement marked as pending rather than assumed
- Parallel DC SPD channels bonded to PE
- Two independent 25 kW inverter feeders
- Preliminary 50 A branch breakers and isolators
- 125 A provisional AC combiner bus
- 100 A main PCC circuit breaker
- CT on the primary feeder
- 100 A visible, lockable PCC isolator
- Direct main feeder continuity to the utility interface
- Protected parallel AC SPD branch
- Protective-earth bonding trunk and earth electrode
- Cross-references to the string, cable, control, and protection sheets

The power meter is not part of the primary feeder. Metering and zero-export wiring are shown on Sheet 2.

## Sheet 2 · Zero-export control and failsafe

The control schematic separates software control from independent hardware protection:

1. The metering CT core supplies the CT-operated power meter.
2. The meter supplies import/export measurements to the plant controller.
3. The plant controller sends active-power-limit commands to the inverter control ports.
4. The data logger supports the monitored inverter communication network.
5. The independent protection CT core supplies the reverse-power relay.
6. The reverse-power relay sends a hardwired trip command to `QF-PCC-01`.

The schematic shows source, destination, signal direction, protocol class, and cross-reference. Final CT ratio, accuracy class, burden, relay setting, delay, trip philosophy, auxiliary supply, terminal block, and fail-safe acceptance remain TBC.

## Sheet 3 · String and equipment schedule

The string schedule presents:

- Inverter and MPPT assignment
- String tags
- Modules per string
- Preliminary DC capacity
- Preliminary cold Voc and hot Vmp
- Preliminary Isc and Imp
- `PRELIM CALC PASS / PRODUCT TBC` status

The drawing does not present a generic calculation as a product-approved result. Final module and inverter models, datasheet limits, temperature coefficients, connector limits, and product selection remain TBC.

Unsupported annual-energy precision and route-dependent voltage-drop precision are omitted from the controlled schedule. Their status is displayed as pending the relevant energy model or confirmed cable route.

## Sheets 4 and 5 · Cable schedule

The current zero-export fixture contains 53 traceable rows. The schedule uses the same equipment and channel tags shown in the SLD and control schematic.

The schedule distinguishes:

- Individual string circuits
- DC switching and MPPT input circuits
- DC SPD branches
- Inverter AC feeders
- Main PCC feeder
- AC SPD branch and backup protection
- CT metering and protection secondaries
- Voltage-reference wiring
- RS485 links
- Ethernet Cat6 links
- Active-power-control links
- Hardwired trip wiring
- Protective-earth bonding

Unknown route lengths, conductor sizes, installation methods, or product-specific requirements are displayed as `TBC`. The renderer does not repeat a known route length across unrelated local interconnections.

## Sheet 6 · Protection schedule and engineering gaps

The protection schedule contains 13 controlled devices. It distinguishes:

- DC switching channels
- DC SPD devices
- Inverter branch circuit breakers
- Inverter branch isolators
- AC combiner
- PCC main circuit breaker
- Lockable PCC isolator
- AC SPD backup protection
- AC SPD
- Reverse-power relay

Provisional values are clearly separated from final engineering duty. The schedule does not invent:

- PCC fault level
- Icu or Ics
- trip settings
- discrimination or selectivity
- utilization category
- CT ratio, class, and burden
- reverse-power threshold and time delay
- SPD Uc, Up, In, Imax, backup protection, or lead arrangement
- AC earthing system
- RCD requirements
- final neutral and PE conductor arrangement

## Preliminary sizing status

The fixture uses the following calculation-controlled preliminary values:

- Inverter feeder breaker: 50 A
- Inverter feeder isolator: 50 A
- Main PCC breaker: 100 A
- PCC isolator: 100 A
- AC combiner bus: 125 A

These values are not final procurement selections. They require final inverter output-current data, cable ampacity, installation method, ambient and grouping derating, voltage drop, fault level, protection coordination, utility requirements, and authorized engineering review.

## Automated validation status

The deterministic R4 fixture produces:

- 25 physical nodes
- 45 graph connections
- 53 traceable cable and wiring rows
- 13 protection-schedule rows
- 0 deterministic electrical-rule errors
- 0 deterministic electrical-rule warnings

A zero-finding result only confirms that the model satisfies the implemented rule set with the available data. It does not replace utility review, protection studies, professional checking, site verification, or construction approval.

## SVG and PDF output

The production renderer generates six standalone vector SVG sheets. The PDF print path combines them into a six-page A3 landscape package.

Print controls include:

- Vector conductors, symbols, tables, and text
- Separate power, PE, communication, control, measurement, and hardwired-trip line styles
- Filled junction dots where conductors are connected
- Grayscale-readable line patterns
- Controlled title blocks and revision metadata
- No construction-release language

The dependency-free PDF fallback also preserves the six-page contract and the corrected topology. It is a continuity mechanism, not a substitute for visual review of the primary SVG print path.

## Validation command

```bash
npm run test:sld-app67l3:php
```

The professional browser regression can be run with:

```bash
NEXORIN_SLD_E2E_ONLY=professional-topology npm run test:sld
```

## Deliberate boundary

APP67L.3-R4 is suitable for internal engineering review of the generated topology and document structure. It is not ready for construction, utility submission, certification, procurement, or final approval until all open engineering inputs are resolved and the drawing has been checked and approved by authorized parties.


## APP67L.3-R5 visual refinement

The R5 renderer removes redundant string-column headings, separates SPD captions from cable cross-references, routes PV and inverter PE conductors outside symbol envelopes, staggers PCC rating annotations, rebuilds the main and expanded legends, and reserves independent communication/control lanes on the zero-export schematic. PV string cable notation is now `2 x 1C`, and the hardwired trip voltage class remains `AUX SUPPLY TBC` until the auxiliary system is selected.
