> **Superseded for paper-space output:** APP67L.3-R5 retains the R4 electrical topology and replaces the current visual drawing composition.

# APP67L.3-R4 · Electrical Topology Correction and Cross-Reference Integrity

## Disposition

Status: `FOR INTERNAL REVIEW`

Construction status: `NOT FOR CONSTRUCTION`

Conformity status: `certification candidate`, pending independent conformity assessment.

## Review findings accepted

The R4 revision accepts the substantive findings raised during detailed R3 review:

1. The PCC lacked a dedicated main breaker and lockable disconnect.
2. `IF-DC-01` was an undefined schedule-only tag.
3. The cable schedule did not match the individual-string topology on the SLD.
4. Zero-export control relied only on software communication.
5. Annual-energy and voltage-drop values showed unsupported precision.
6. `ENGINEERING FINALIZATION` conflicted with pending checking and approval.
7. MPPT rows displayed `PASS` before final product selection.
8. The AC earthing system was unspecified.
9. PCC fault level and breaker duty were unavailable.
10. String fuses were asserted despite one string per MPPT and no documented need.
11. Inverter branch protection was oversized without a visible preliminary sizing basis.
12. The AC combiner bus had no stated current margin.
13. Design temperatures lacked project and product authority.

## Implemented electrical changes

### PCC protection and isolation

The controlled main path is now:

```text
INV-01 FEEDER ─┐
               ├→ ACCB-01 → QF-PCC-01 → CT-01 → QS-PCC-01 → GRID-01
INV-02 FEEDER ─┘
```

- `QF-PCC-01` provides the main PCC breaker function.
- `QS-PCC-01` provides visible, lockable isolation for utility and maintenance access.
- Final duty, poles, neutral treatment, utilization category, interlocking, and utility acceptance remain TBC.

### CT-operated meter topology

`MTR-01` is outside the primary feeder. It receives CT secondary and voltage-reference wiring. The primary feeder passes through `CT-01` and continues to the PCC.

### Independent zero-export failsafe

The software loop remains:

```text
MTR-01 → PPC-01 → INV-01 / INV-02
```

The independent hardware path is:

```text
CT-01 protection core → RPR-01 → hardwired trip → QF-PCC-01
```

The hardwired path does not rely on RS485 or Ethernet. Final protection philosophy, relay threshold, delay, fail-safe state, auxiliary supply, test block, and utility requirements remain TBC.

### SPD topology

- DC SPDs are parallel branches from the MPPT input channels to PE.
- The AC SPD is a parallel branch protected by `QF-SPD-01`.
- SPD selection data and lead requirements remain TBC.

### String-circuit policy

The fixture has one string per MPPT. R4 does not assert installed string fuses by default. The drawing and schedule mark fuse application as product- and code-dependent.

If final module, inverter, conductor, or local-code data requires string overcurrent protection, the controlled topology must instantiate the required fuse devices and update the schedule before release.

## Schedule integrity

- `IF-DC-01` has been removed.
- Cable rows use the same string, switch-channel, MPPT, breaker, CT, relay, meter, PPC, and PCC tags shown in the drawings.
- Individual string circuits remain individually traceable through the schedule.
- Ethernet and RS485 are no longer represented as one generic two-core fieldbus cable.
- CT secondary, voltage reference, hardwired trip, control, communication, AC power, DC power, and PE conductors use distinct circuit classes.

## Conservative data handling

R4 removes or qualifies values that cannot be supported by the available data:

- Annual energy is not displayed as a precise controlled value.
- Voltage drop is not displayed as a precise controlled value while routes remain TBC.
- MPPT rows use `PRELIM CALC PASS / PRODUCT TBC`.
- AC earthing arrangement is TBC.
- PCC fault level is TBC.
- Icu, Ics, trip settings, selectivity, and protection duty are TBC.
- Final neutral and PE conductor arrangement is TBC.
- Product model and datasheet authority are TBC.

## Preliminary calculation values

The fixture now distinguishes branch and main equipment:

| Item | Preliminary value | Release status |
|---|---:|---|
| Inverter feeder breaker | 50 A | Preliminary, coordination pending |
| Inverter feeder isolator | 50 A | Preliminary, utilization category pending |
| PCC main breaker | 100 A | Preliminary, fault duty and trip pending |
| PCC isolator | 100 A | Preliminary, utility acceptance pending |
| AC combiner bus | 125 A | Preliminary, thermal and fault withstand pending |

## Six-sheet review package

R4 separates the crowded R3 package into six sheets:

1. Main SLD
2. Zero-export control and independent failsafe
3. MPPT, string, and equipment schedule
4. Cable schedule rows 1-27
5. Cable schedule rows 28-53
6. Protection schedule and open engineering inputs

This separation improves legibility and prevents control wiring, schedule data, and detailed checks from obscuring the main power path.

## Acceptance gates passed

The deterministic R4 regression verifies:

- PCC main breaker and lockable isolator exist.
- Meter is outside the primary power path.
- CT metering and protection secondaries are separate.
- Reverse-power relay hardwired trip exists.
- AC and DC SPD branches are parallel.
- No undefined `IF-DC` tag exists.
- No asserted fuse-per-string device exists for the one-string-per-MPPT fixture.
- Sheet and schedule tags reconcile.
- 25 nodes and 45 graph connections are generated.
- 53 cable rows and 13 protection rows are generated.
- Six A3 sheets and a six-page fallback PDF are produced.
- Automated electrical findings are 0 errors and 0 warnings.

## Open gates before construction release

R4 must not be released for construction until the project resolves:

- Utility interconnection and accessible-disconnect requirements
- AC earthing system and neutral treatment
- PCC prospective short-circuit current
- Breaker Icu, Ics, trip settings, and coordination
- Cable material, core arrangement, ampacity, route, installation method, and derating
- RCD or residual-current requirements
- Final module and inverter models
- String-fuse requirement
- SPD type, Uc/Ucpv, Up, In, Imax, backup protection, and connection leads
- CT ratio, class, burden, polarity, terminal/test block, and secondary earthing
- Reverse-power relay setting, delay, trip circuit, auxiliary supply, and fail-safe test
- Grounding conductor sizes and measured earth resistance
- Checker, approver, utility, and independent conformity-assessment actions
