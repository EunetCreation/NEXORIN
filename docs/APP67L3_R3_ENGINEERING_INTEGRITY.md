> **Superseded:** This document records the APP67L.3-R3 implementation. APP67L.3-R4 replaces the current controlled topology and drawing contract.

# APP67L.3-R3 · Engineering Integrity and Drafting Finalization

## Purpose

APP67L.3-R3 addresses the technical and document-control issues found during the detailed R2 review. The revision does not treat visual polish as a substitute for engineering clarity. It aligns the generated model, printed single-line diagram, cable schedule, protection schedule, and title block.

## Critical issues closed

### Independent string and MPPT representation

Each inverter now displays three independent string circuits. Each string has:

- its own string tag
- module quantity and DC capacity
- one gPV fuse
- one DC switching channel
- one MPPT assignment
- Voc cold and Vmp hot annotation

The drawing no longer presents one downstream `per-string` fuse after three strings have been visually combined.

### Explicit zero-export measurement chain

The zero-export profile adds a current-transformer node and two secondary measurement circuits:

- CT S1 to meter measurement input
- CT S2 to meter measurement return

The AC primary path is:

```text
AC combiner → current transformer → power meter → PCC / grid
```

The control path is shown separately:

```text
Power meter → plant controller → inverter network
```

The CT is conditional. A professional project without zero-export or export-limitation control does not receive the CT automatically.

### Port-aware circuit identity

Circuit keys now include source port, destination port, and network. This prevents two valid circuits between the same pair of nodes from being merged accidentally. The change is required for CT secondary S1 and S2 wiring and improves future multi-port device support.

### Route-specific cable-length semantics

Known design-route inputs are assigned only to their intended route:

- PV array to string-fuse route
- inverter feeder to AC collection route

Local equipment, protection, CT secondary, communication, control, PE, and internal panel interconnections render as `TBC` unless the controlled source provides a dedicated value. The generator no longer repeats one route length across every component-to-component segment.

### Clear AC collection, SPD, metering, and PCC path

The main SLD now shows a direct, readable sequence:

```text
Inverter feeders → AC combiner → CT → power meter → PCC / grid
```

The AC SPD is a parallel branch with an explicit connection dot and PE termination. DC SPDs remain parallel protection branches.

### Junction and crossing clarity

The R3 drawing uses filled connection dots for bonded electrical junctions. Power, PE, measurement, communication, and control paths use distinct patterns and direction markers. This reduces ambiguity between a connected junction and a line crossing.

### Consistent document control

The header, revision table, drawing number, title block, scale block, and paper-space label now use R3 consistently. Fixture names from the previous APP67L.2 test package are no longer printed in the demonstration output.

The status wording now distinguishes:

- model data linkage
- automated rule checking
- pending engineering verification
- pending checker and approver action

The drawing does not use `VERIFIED SOURCE` as a substitute for engineering approval.

## Current controlled baseline

For the 52.8 kWp zero-export demonstration profile:

- 96 PV modules
- 6 strings × 16 modules
- 2 string inverters × 25 kW
- 3 MPPT inputs used per inverter
- 25 physical nodes
- 38 controlled circuits
- 15 expanded protection-schedule rows
- 0 deterministic electrical-rule findings

The 15 visible protection rows comprise:

- 2 three-channel DC isolator assemblies
- 6 individual string-fuse rows
- 2 DC SPD rows
- 2 AC breaker rows
- 2 AC isolator rows
- 1 AC SPD row

## Sheet-specific refinement

### Sheet 1

- Independent strings and MPPT channels
- Fuse and switch channel tags
- Electrical voltage annotations
- Stronger DC and AC hierarchy
- CT-operated meter and PCC path
- Directional zero-export communication and control
- Protective-earth bonding trunk with filled junction dots
- Compact but consistent R3 title block

### Sheet 2

- Voc, Vmp, Isc, Imp, and check status per string
- Inverter MPPT-range and input-current basis
- Design temperature basis
- MPPT utilization and spare-input visibility
- Equipment tag ranges and product-selection status

### Sheet 3

- 38 port-aware cable rows
- Known route length versus TBC local length
- Parallel-run notation
- Two CT secondary circuits retained separately
- Expanded 15-row protection schedule
- Explicit TBC duty and coordination items

## Validation result

The deterministic regression set passes:

- APP67L.1 symbol and renderer regression
- APP67L.2 professional physical topology regression
- APP67L.3-R3 A3 integration
- APP67K engineering controller integration
- APP67I PHP and JavaScript electrical rules
- APP67J PHP smart router, 40 stress cases
- APP67J JavaScript smart router, 80 stress cases
- Symbol registry, 61 symbols and 196 canonical ports
- Electrical-rule registry, 19 rules, 35 profiles, and 15 networks
- Three standalone A3 SVG sheets
- Three-page A3 fallback PDF

## Release boundary

APP67L.3-R3 is suitable as a controlled `FOR REVIEW` package. It remains intentionally blocked from a construction-ready claim while TBC data, product selection, site routing, protection coordination, checker approval, approver signature, and independent conformity assessment remain incomplete.
