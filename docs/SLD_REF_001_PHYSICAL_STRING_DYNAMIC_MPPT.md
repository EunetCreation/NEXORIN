# SLD-REF-001 · Physical String and Dynamic MPPT Contract

## Purpose

SLD-REF-001 replaces aggregate PV-array graph objects with calculation-controlled physical string circuits. The drawing remains editable, but string quantity, module allocation, inverter assignment, MPPT assignment, polarity, and controlled cable metadata remain authoritative outputs of Technical Calculation.

This patch is the first runtime step toward the approved NEXORIN production-SLD reference. It does not attempt to redesign the complete paper-space composition, AC combiner internals, monitoring cabinet, or title block.

## Source authority

The runtime contract is generated from:

- `technical_calculations.results.inverter_string_assignments`;
- module and inverter electrical inputs from Technical Calculation;
- calculated DC cable area and route length;
- calculated protection outputs;
- selected system type.

No string or MPPT allocation is invented by the SLD editor.

## Deterministic identities

| Object | Contract |
|---|---|
| Physical PV string | `pv_string_###` |
| DC isolator assembly | `dc_isolator_##` |
| Inverter | `inverter_##` |
| String-to-isolator pair | `C-DC-##-STR-##-A` |
| Isolator-to-MPPT pair | `C-DC-##-STR-##-B` |
| String protective earth | `C-PE-STR-##` |

The positive and negative conductors are separate graph connections. A shared `paired_connection_id` and `cable_tag` keep both conductors under one controlled cable-set row.

## Dynamic ports

Every physical string exposes:

- `DC_POS`;
- `DC_NEG`;
- `PE`.

Every isolator channel exposes independent positive and negative input/output terminals. The first channel retains canonical registry IDs; subsequent channels use deterministic `CH##_...` IDs.

Every inverter exposes one positive/negative pair for each allocated MPPT input. Available but unused MPPT positions remain visible. AC, PE, communication, and control ports remain available. Hybrid-only battery and backup ports are filtered for non-hybrid systems.

## Cable schedule behavior

Each string generates:

1. one paired cable-set row from the PV string to the inverter-side isolator assembly;
2. one paired local interconnection row from the isolator channel to the assigned inverter MPPT input;
3. one protective-earth row for the string frame.

The local interconnection is not assigned the array route length. This prevents double-counting the Technical Calculation DC route length.

## Editor reconciliation

Existing deterministic node positions remain authoritative during synchronization. For revisions created before SLD-REF-001, a legacy `pv_group_##` position is migrated by translating the new physical-string cluster around the former aggregate node centre. This preserves the user's former PV-zone placement while introducing individually editable strings.

Manual nodes, lines, shapes, annotations, and other existing editor content remain preserved by the APP67K reconciliation contract.

Routes and automatic label placements that reference removed sourced objects are pruned during synchronization. Routes belonging to retained sourced connections or manual connections remain preserved. This prevents obsolete aggregate PV routes and labels from contaminating visual-regression metadata after the topology migration.

## Compatibility

- Graph schema remains `nexorin-sld-v1.9.0`.
- Existing six-sheet APP67L.4 production composition remains active.
- Legacy aggregate DC source ports remain in the registry as source-only compatibility ports.
- Runtime editor objects use explicit polarity ports.
- Existing issued revision payloads are not rewritten automatically.

## Explicit non-scope

- Product/datasheet-to-calculation locking;
- expanded AC combiner child equipment;
- RS485 versus Ethernet network subtypes;
- final single-sheet production renderer;
- trim, extend, offset, dimension, or user-defined CAD blocks;
- commercial fingerprint separation.

## Validation gate

The patch is accepted only when:

- each calculation string has exactly one physical node;
- every string has positive, negative, and PE circuits;
- every string terminates at its calculated inverter/MPPT/input;
- paired conductors bind to one controlled cable-set row;
- no aggregate `pv_group_*` node remains in the fresh topology;
- electrical-rule checks remain free of deterministic errors and warnings for the standard fixture;
- six-sheet production rendering and document-control tests remain green.
