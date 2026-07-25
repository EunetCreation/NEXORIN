# APP67A NEXORIN Precision Drafting Engine

## Purpose

APP67A upgrades the browser SLD editor from a diagram canvas into a precision drafting workspace. The implementation keeps the NEXORIN 42A identity while adopting proven CAD interaction patterns. Electrical symbols remain part of the workspace and are not replaced by generic drawing tools.

## Architecture

The editor uses three cooperating layers:

1. **maxGraph document model** for cells, electrical connections, selection, undo and redo, copy and paste, and serialization.
2. **NEXORIN precision interaction engine** for command prompts, dynamic input, rubber-band previews, grid snap, polar tracking, object snap, and drawing geometry.
3. **NEXORIN engineering symbol registry** for SVG geometry, equipment metadata, typed electrical ports, automatic labels, and export rules.

Model Space and A3 Paper Space use one canonical saved drawing model. Paper Space applies one uniform viewport transform. It does not rebuild or redistribute the layout.

## Implemented precision commands

| Command | Shortcut | Behaviour |
|---|---|---|
| Select | `V` | Select and edit drawing objects |
| Pan | `H`, `Space + drag`, middle mouse | Move the viewport without moving objects |
| Line | `L` | Multi-segment line command with separate line entities |
| Polyline workflow | `PL` | Connected segment drafting workflow |
| Rectangle | `REC` | First corner and opposite corner |
| Circle | `C` | Center and radius |
| Arc | `A` | Center, start point, and end point |
| Engineering wire | `W` | Typed port-to-port electrical connection |
| Drawing note | `T` | Place an editable note |
| Multileader | `ML` | Place a leader and editable note |
| Fit drawing | `0` | Fit drawing to the available viewport |
| Fullscreen | `F` | Enter or leave application fullscreen |

The current Polyline workflow creates connected line segments. A future dedicated lightweight-polyline object can be added without changing the saved coordinate contract.

## Precision aids

- Dynamic command prompt beside the cursor
- Coordinate, distance, radius, diameter, width, height, and angle readouts
- Rubber-band geometry preview before point confirmation
- Orthogonal tracking
- Polar tracking at common engineering angles
- Object snap tracking guides
- Grid display and independent grid snap
- Snap marker and snap tooltip
- Keyboard numeric entry with `Tab` field switching
- `Enter` to commit or finish
- `Esc` to cancel

## Object snaps

- Endpoint
- Midpoint
- Center
- Quadrant
- Intersection
- Nearest
- Electrical port

Geometric snaps and electrical ports are intentionally separate. Generic linework may snap to symbol geometry. Engineering Wire only connects compatible electrical ports.

## Electrical connection rules

The editor stores source port, destination port, and network type for every manual Wire. The first compatibility layer distinguishes:

- DC positive
- DC negative
- General DC power
- Battery positive
- Battery negative
- General battery DC
- AC power
- Protective earth
- Communication
- Control

The editor rejects connections when port network or direction is incompatible. Calculated equipment ratings remain read-only while layout and routing remain editable.

## Symbol library expansion

APP67A includes the following families:

- PV module, string, array, sub-array, junction box, combiner, optimizer, rapid shutdown, and microinverter
- MPPT controller, DC-DC converter, string inverter, hybrid inverter, off-grid inverter, central inverter, and BESS PCS
- Battery cell, module, rack, bank, BMS, and battery disconnect
- DC fuse, DC breaker, DC protection assembly, DC busbar, GFPD, and AFCI
- AC protection, AC combiner, ATS, distribution panels, critical-load panel, and EPO
- Energy meter, APP PLN or kWh exim, PCC or POI, utility grid, transformer, and generator
- CT, VT, protection relay, monitoring gateway, SCADA or RTU, weather station, and irradiance sensor
- Protective earth, grounding electrode, and lightning arrester

The library uses engineering linework rather than product illustrations or dashboard cards.

## Saved editor contract

APP67A persists:

- Model-space node position and size
- Manual electrical nodes
- Manual electrical wires and typed ports
- Manual lines
- Rectangle, circle, and arc geometry
- Connection routes
- Drawing annotations
- Excluded calculated nodes
- Viewport scale and translation
- Grid, snap, ortho, polar, object snap, tracking, and dynamic-input state
- Panel state and active display mode

Browser input cannot replace the technical-calculation source nodes or calculated ratings.

## A3 output

The A3 SVG renderer and dependency-free PDF fallback render:

- Saved component positions
- Saved line and shape geometry
- Manual and sourced equipment
- Electrical connections and communication lines
- Drawing notes and legend
- Title block and revision data
- A uniform paper-space transform

Interactive selection handles, hover states, snap markers, and dynamic input never enter the exported document.

## Installation

The delivery patch includes the compiled maxGraph production bundle. Installation does not require Node.js on the target server.

```bash
php artisan optimize:clear
```

Then perform a hard browser refresh.

Do not run `npm ci`, `npm install`, or `npm run build` for patch installation. Those commands are only required when modifying the source JavaScript locally.

## Scope boundary

APP67A establishes the precision drafting foundation. Advanced destructive CAD operations such as Trim, Extend, Offset, Fillet, Chamfer, Hatch, and full associative Dimension are not represented as completed commands in this release. They should be added only after their geometry rules, undo behaviour, save contract, and A3 rendering are fully defined and tested.
