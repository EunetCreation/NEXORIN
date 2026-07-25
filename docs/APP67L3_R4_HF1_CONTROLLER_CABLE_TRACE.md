# APP67L.3-R4-HF1 · Controller Cable Traceability

## Defect

The APP67L.3-R4 professional-topology browser suite expected the Controller cable list to expose the destination endpoint `INV-01:MPPT-01`. The controller payload already contained `from` and `to`, and the A3 cable schedule rendered those fields, but the browser Controller card rendered only `wire_id`, `label`, and `detail`.

## Correction

Each Controller cable card now displays:

1. circuit or wire ID;
2. traceable `from → to` route;
3. schedule label and cable detail.

No topology, calculation, protection, or electrical-rule logic changed.
