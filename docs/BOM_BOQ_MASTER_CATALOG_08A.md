# NEXORIN Hotfix 08A - BOM/BOQ Master Material Library Expansion

## Purpose

Hotfix 08A expands the BOM/BOQ engine from a project-specific preliminary list into an EPC-level master scope library. The goal is to prevent missing small materials, conditional commercial items, service lines, legal items, testing work, O&M options, and utility/owner responsibility boundaries.

## Design principle

The master catalog is not meant to charge every item to every project. Each item is assigned a scope status:

- Required: included in the base BOQ.
- Conditional: included only when the rule applies.
- Optional: displayed for scope clarity and excluded from the base total until selected.
- Utility Responsibility: displayed for scope clarity and excluded from the base total.
- Owner Responsibility: displayed for scope clarity and excluded from the base total.
- Not Applicable: shown only when needed for master-scope completeness.
- Service: included when it is a project service line.

## New scope areas added

Hotfix 08A adds expanded coverage for:

- PV module spares, optimizer option, rapid shutdown option, and cleaning kit.
- Standing seam clamp, ballast systems, ground piles, HDG steel structure, tilt supports, anchors, corrosion repair, catwalks, and lifelines.
- DC junction box, DC busbar/enclosure, MC4 branch connectors, and DC cable tray/ladder.
- Micro inverter option, off-grid inverter support, MPPT controller, PCS, inverter thermal management, and inverter spare parts.
- RCCB/ELCB/RCBO, contactor/interlock, protection relay, synchronization panel, ATS/AMF/STS, CT/PT, export limiter, power quality meter, harmonic filter option, LVMDP integration, transformer option, and MV switchgear option.
- Battery rack, combiner, busbar, smoke/heat/gas detector, and battery room ventilation.
- Smart meter, Modbus gateway, Ethernet switch, cellular data option, SCADA/HMI option, weather station option, pyranometer/reference cell, and cloud subscription.
- Equipotential bonding bar, exothermic welding, lightning protection allowance, lightning counter, lockout-tagout kit, and PPE kit.
- Shading analysis, roof structural assessment, soil test, roof repair option, foundation, trenching, drainage, fence/gate, CCTV/security lighting, and equipment shelter/battery room.
- Energy yield simulation, SLD/layout/string/cable routing drawings, structural calculation, scaffolding/lifting equipment, insulation/polarity/Voc/Isc testing, I-V curve/thermography, grounding resistance test, inverter commissioning sheet, battery commissioning report, and HSE work permit package.
- SLO/technical inspection support, utility interconnection study, project insurance option, warehousing/site storage, project management, warranty reserve/spare parts, monthly performance report option, and corrective maintenance option.

## Commercial handling

The pricing engine has been expanded with default unit costs for the new item codes. Items with optional, utility, owner, or not-applicable scope are excluded from the base customer total until selected or re-scoped by the company.

## Important limitation

The 08A catalog is still a deterministic master-scope library, not a vendor catalog. The next step is to create a database-backed product catalog with real brands, models, datasheets, supplier lead times, certificates, warranty data, and price validity.
