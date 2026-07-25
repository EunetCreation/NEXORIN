# NEXORIN APP63 Catalog Schema Precision 63A Audit Report

## Audit baseline

- Source inspected: `NEXORIN-APP(63).zip`.
- Scope: professional material technical schemas, field ownership, dynamic intake UI, backend validation, normalized persistence, readiness, approval invalidation, and existing Price Book/BOM/project handoff.
- No route, permission, engineering formula, database migration, BOM quantity formula, pricing formula, or quotation formula was redesigned in this refinement.

## Result

| Metric | Result |
|---|---:|
| Professional domains | 15 |
| Exact component subcategories | 70 |
| Schema payloads including roots and fallback | 86 |
| Unique normalized specification keys | 398 |
| Semantic field groups | 23 |
| Duplicate keys in a schema | 0 |
| Duplicate labels in exact schemas | 0 |
| Uncategorized `general` fields in exact schemas | 0 |
| Empty select option sets | 0 |
| Required/conditional overlaps | 0 |
| Static schema audit errors | 0 |
| Clean APP63 extraction + patch application | Pass |

## What was corrected

### Semantic ownership

Every exact component field is now assigned to an engineering group that matches its meaning: Architecture, Construction, Electrical, Battery, Performance, Thermal, Mechanical, Measurement, Monitoring, Control, Communication, Environmental, Installation, Protection, Safety, Compatibility, Application, Service, Execution, Maintenance, Compliance, Warranty, or Commercial.

Load-bearing corrections include:

- PV cell type and cell count moved to Product Architecture, efficiency to Performance, and temperature coefficients to Thermal.
- Battery chemistry, Ah/kWh energy values, DoD, current, and parallel limits remain in Battery; round-trip efficiency and cycle life are Performance; operating temperature is Thermal.
- DC SPD response time and Iscpv are Protection values; indicators and remote contacts are Monitoring values.
- Mounting pile/material construction, structural loads, environmental corrosion, and installation details are separated.
- Metering accuracy remains Measurement; alarms/status are Monitoring; export, transfer, and synchronization functions are Control.
- Fire agent and PPE hazard values are Safety; detector response and discharge duration are Performance; maintenance intervals are Maintenance.
- Civil and service records distinguish technical measurement, execution resources, service scope/deliverables, compliance, and commercial terms.

### Duplicate and ambiguous concepts

- Retired `module_technology` from exact Mono PERC, TOPCon, and HJT schemas because the exact subcategory already declares the technology.
- Retired redundant `bifacial` boolean from the exact Bifacial Module schema. Bifaciality percentage remains the meaningful performance value.
- Retired duplicate `device_type` from DC SPD. `spd_type` is the single authoritative classification field.
- Renamed battery capacity semantics: Ah is Nominal Capacity, kWh is Nominal Energy, and usable/gross energy are explicit.
- Renamed DC SPD short-circuit field to expose the Iscpv engineering meaning.
- Exact single-phase and three-phase inverter categories constrain phase options so contradictory values cannot be submitted.

### Combined taxonomy categories

Seven combined subcategories now distinguish common required fields from subtype-dependent fields instead of falsely requiring every field for every contained device:

- PV Cable & Connector
- DC Combiner & Junction
- DC Fuse, Breaker & Isolator
- MCB, MCCB, ACB & Protection
- ATS, STS, AMF & Synchronization
- CT, Metering & Export Control
- Walkway, Lifeline & Access

Subtype-dependent fields are visible and accepted by the backend, but they are not counted as universal approval requirements for every device in the combined category.

### Existing record cleanup

The synchronizer now explicitly removes only retired duplicate keys when an affected material is saved:

| Exact category | Retired key |
|---|---|
| PV_MONO_PERC | `module_technology` |
| PV_TOPCON | `module_technology` |
| PV_HJT | `module_technology` |
| PV_BIFACIAL | `bifacial` |
| DC_SPD | `device_type` |

Custom specifications and parser-only fields are preserved. Cleanup runs when the user re-saves the affected material through the governed editor.

## UI and backend alignment

The implemented flow is:

```text
Exact component subcategory
→ server-owned schema payload
→ grouped dynamic form
→ server reconstructs allowed fields
→ unknown fields rejected
→ normalized material_specs synchronization
→ legacy summary columns synchronized
→ completeness evaluator reads the same required keys
→ approval allowed or blocked
→ Price Book → project-compatible BOM pricing trace → Client Release gate
```

Verified source behavior:

- Drafts can remain incomplete. Required technical fields are approval requirements, not create-time blockers.
- The backend derives the schema from the selected category and rejects submitted keys outside that schema.
- Material identity and normalized technical fields are persisted inside the existing database transaction.
- A technical or identity change on an Approved material resets it to Needs Review and clears the stale approval actor/time.
- Completeness uses the exact schema required keys, parsed active datasheet, verified certificates/documents, and active state.
- Price Book resolution continues to check project system type, project scale, supply phase, and surveyed roof type.
- A compatible but unapproved catalog material can remain an internal pricing trace, while `catalog_review_required` reaches the BOM summary and blocks Client Release until approval and repricing.

## UI precision changes

- Form score renamed to Form Completion so it is not confused with final approval readiness.
- Required, subtype-dependent, optional, and completed counts are separated.
- Governance notes show Optional when no optional notes are entered, rather than falsely showing Complete.
- Singular/plural field counters are corrected.
- One/two-field groups use compact layout.
- Sticky action bar is smaller and page bottom spacing prevents it from covering inputs.
- Exact phase subtype with one valid value is auto-selected safely.
- Accessibility text distinguishes approval-required, subtype-dependent, and optional fields.

## Professional domain coverage

| Domain | Exact subcategories | Required appearances | Conditional appearances | Optional appearances |
|---|---:|---:|---:|---:|
| PV Module | 5 | 81 | 0 | 17 |
| On-grid Inverter | 5 | 89 | 0 | 16 |
| Hybrid & Off-grid Inverter | 4 | 65 | 0 | 14 |
| Battery & ESS | 5 | 81 | 0 | 13 |
| DC Electrical | 5 | 39 | 24 | 5 |
| AC Electrical | 5 | 46 | 19 | 7 |
| Mounting & Structure | 5 | 53 | 4 | 11 |
| Monitoring, Metering & Communication | 5 | 56 | 0 | 10 |
| Grounding, Lightning & Safety | 4 | 37 | 0 | 8 |
| Panel & Distribution | 5 | 62 | 0 | 10 |
| Civil & Site Work | 5 | 48 | 0 | 10 |
| Fire & HSE | 5 | 46 | 0 | 10 |
| Legal, Certification & Compliance | 4 | 35 | 0 | 8 |
| Logistics & O&M | 5 | 43 | 0 | 10 |
| Supplier Quote & Pricelist | 3 | 30 | 0 | 8 |

## Exact schema matrix

Legend: Required is universal for the exact category; Conditional is subtype-dependent inside a combined category; Optional is additional engineering context.

| Root domain | Code | Exact component | Required | Conditional | Optional | Semantic groups used |
|---|---|---|---:|---:|---:|---|
| PV Module | `PV_MONO_PERC` | Mono PERC Module | 16 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Warranty & Lifecycle |
| PV Module | `PV_TOPCON` | TOPCon Module | 16 | 0 | 3 | Product Architecture, Materials & Construction, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Warranty & Lifecycle |
| PV Module | `PV_HJT` | HJT Module | 16 | 0 | 3 | Product Architecture, Materials & Construction, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Warranty & Lifecycle |
| PV Module | `PV_BIFACIAL` | Bifacial Module | 17 | 0 | 4 | Product Architecture, Materials & Construction, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Warranty & Lifecycle |
| PV Module | `PV_HIGH_POWER` | High-power Commercial Module | 16 | 0 | 5 | Product Architecture, Materials & Construction, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Protection Ratings, Warranty & Lifecycle |
| On-grid Inverter | `INV_STRING_1P` | String Inverter · Single Phase | 20 | 0 | 4 | Product Architecture, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Communication & Data, Environmental Limits, Protection Ratings, Compliance & Standards, Warranty & Lifecycle |
| On-grid Inverter | `INV_STRING_3P` | String Inverter · Three Phase | 20 | 0 | 4 | Product Architecture, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Communication & Data, Environmental Limits, Protection Ratings, Compliance & Standards, Warranty & Lifecycle |
| On-grid Inverter | `INV_MICRO` | Microinverter | 17 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Performance & Lifecycle, Communication & Data, Environmental Limits, Protection Ratings, Warranty & Lifecycle |
| On-grid Inverter | `INV_CENTRAL` | Central Inverter | 22 | 0 | 4 | Product Architecture, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Communication & Data, Environmental Limits, Protection Ratings, Compatibility, Compliance & Standards, Warranty & Lifecycle |
| On-grid Inverter | `INV_OPTIMIZER` | Module Optimizer / MLPE | 10 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Performance & Lifecycle, Communication & Data, Environmental Limits, Protection Ratings, Compatibility, Warranty & Lifecycle |
| Hybrid & Off-grid Inverter | `INV_HYBRID` | Hybrid Inverter | 19 | 0 | 5 | Product Architecture, Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Control & Automation, Communication & Data, Environmental Limits, Compatibility, Compliance & Standards, Warranty & Lifecycle |
| Hybrid & Off-grid Inverter | `INV_OFFGRID` | Off-grid Inverter | 19 | 0 | 4 | Product Architecture, Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Control & Automation, Communication & Data, Environmental Limits, Compatibility, Warranty & Lifecycle |
| Hybrid & Off-grid Inverter | `INV_MPPT_CONTROLLER` | MPPT Charge Controller | 12 | 0 | 2 | Product Architecture, Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Thermal Characteristics, Communication & Data, Environmental Limits, Compatibility, Warranty & Lifecycle |
| Hybrid & Off-grid Inverter | `INV_PCS` | Power Conversion System | 15 | 0 | 3 | Product Architecture, Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Thermal Characteristics, Control & Automation, Communication & Data, Environmental Limits, Compatibility, Warranty & Lifecycle |
| Battery & ESS | `BAT_WALL` | Wall-mounted Battery | 17 | 0 | 3 | Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Communication & Data, Environmental Limits, Installation Requirements, Safety & Hazard Controls, Warranty & Lifecycle |
| Battery & ESS | `BAT_RACK` | Rack Battery Module | 17 | 0 | 3 | Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Communication & Data, Environmental Limits, Safety & Hazard Controls, Compatibility, Warranty & Lifecycle |
| Battery & ESS | `BAT_CABINET` | Battery Cabinet | 19 | 0 | 2 | Product Architecture, Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Thermal Characteristics, Mechanical & Structural, Communication & Data, Environmental Limits, Safety & Hazard Controls, Warranty & Lifecycle |
| Battery & ESS | `BAT_BMS` | Battery Management System | 13 | 0 | 2 | Product Architecture, Electrical Ratings, Battery & Energy Storage, Thermal Characteristics, Communication & Data, Environmental Limits, Compatibility, Maintenance, Warranty & Lifecycle |
| Battery & ESS | `BAT_ESS_PACKAGE` | ESS Rack / Cabinet Package | 15 | 0 | 3 | Product Architecture, Electrical Ratings, Battery & Energy Storage, Performance & Lifecycle, Thermal Characteristics, Control & Automation, Communication & Data, Environmental Limits, Safety & Hazard Controls, Warranty & Lifecycle |
| DC Electrical | `DC_CABLE_CONNECTOR` | PV Cable & Connector | 6 | 10 | 0 | Product Architecture, Materials & Construction, Electrical Ratings, Thermal Characteristics, Mechanical & Structural, Environmental Limits, Safety & Hazard Controls, Compliance & Standards |
| DC Electrical | `DC_COMBINER` | DC Combiner & Junction | 7 | 5 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Monitoring & Instrumentation, Communication & Data, Environmental Limits, Installation Requirements, Protection Ratings, Compliance & Standards |
| DC Electrical | `DC_PROTECTION` | DC Fuse, Breaker & Isolator | 7 | 6 | 0 | Product Architecture, Electrical Ratings, Control & Automation, Environmental Limits, Installation Requirements, Protection Ratings, Compliance & Standards |
| DC Electrical | `DC_SPD` | DC Surge Protection | 10 | 0 | 3 | Monitoring & Instrumentation, Environmental Limits, Protection Ratings, Maintenance, Compliance & Standards |
| DC Electrical | `DC_CONTAINMENT` | Conduit, Tray & Termination | 9 | 3 | 0 | Product Architecture, Materials & Construction, Thermal Characteristics, Mechanical & Structural, Environmental Limits, Installation Requirements, Safety & Hazard Controls, Compliance & Standards |
| AC Electrical | `AC_CABLE` | AC Power Cable | 11 | 0 | 3 | Product Architecture, Materials & Construction, Electrical Ratings, Thermal Characteristics, Mechanical & Structural, Installation Requirements, Safety & Hazard Controls, Compliance & Standards |
| AC Electrical | `AC_PROTECTION` | MCB, MCCB, ACB & Protection | 6 | 8 | 0 | Product Architecture, Electrical Ratings, Monitoring & Instrumentation, Control & Automation, Installation Requirements, Protection Ratings, Compliance & Standards |
| AC Electrical | `AC_TRANSFER_SYNC` | ATS, STS, AMF & Synchronization | 9 | 5 | 1 | Product Architecture, Electrical Ratings, Control & Automation, Communication & Data, Environmental Limits, Protection Ratings, Compatibility, Compliance & Standards |
| AC Electrical | `AC_METERING_CONTROL` | CT, Metering & Export Control | 8 | 6 | 0 | Product Architecture, Electrical Ratings, Measurement & Accuracy, Monitoring & Instrumentation, Control & Automation, Communication & Data, Environmental Limits, Compliance & Standards |
| AC Electrical | `AC_TRANSFORMER_MV` | Transformer & MV Integration | 12 | 0 | 3 | Product Architecture, Materials & Construction, Electrical Ratings, Thermal Characteristics, Environmental Limits, Protection Ratings, Compliance & Standards |
| Mounting & Structure | `MOUNT_ROOF` | Rooftop Rail & Clamp | 12 | 0 | 3 | Materials & Construction, Mechanical & Structural, Environmental Limits, Installation Requirements, Compatibility, Compliance & Standards |
| Mounting & Structure | `MOUNT_BALLAST` | Ballasted Mounting | 12 | 0 | 2 | Materials & Construction, Mechanical & Structural, Environmental Limits, Installation Requirements, Compatibility, Compliance & Standards |
| Mounting & Structure | `MOUNT_GROUND` | Ground-mounted Structure | 12 | 0 | 2 | Materials & Construction, Mechanical & Structural, Environmental Limits, Installation Requirements, Compatibility, Compliance & Standards |
| Mounting & Structure | `MOUNT_CARPORT` | Solar Carport Structure | 12 | 0 | 2 | Materials & Construction, Mechanical & Structural, Environmental Limits, Installation Requirements, Compliance & Standards |
| Mounting & Structure | `MOUNT_ACCESS` | Walkway, Lifeline & Access | 5 | 4 | 2 | Product Architecture, Materials & Construction, Mechanical & Structural, Environmental Limits, Installation Requirements, Safety & Hazard Controls, Compatibility, Compliance & Standards |
| Monitoring, Metering & Communication | `MON_DATA_LOGGER` | Data Logger & Gateway | 12 | 0 | 2 | Product Architecture, Monitoring & Instrumentation, Communication & Data, Environmental Limits, Maintenance, Commercial Reference |
| Monitoring, Metering & Communication | `MON_METER` | Smart Meter & Energy Meter | 12 | 0 | 2 | Product Architecture, Electrical Ratings, Measurement & Accuracy, Monitoring & Instrumentation, Control & Automation, Communication & Data, Environmental Limits, Compliance & Standards |
| Monitoring, Metering & Communication | `MON_NETWORK` | Router, Switch & Communication | 11 | 0 | 2 | Product Architecture, Electrical Ratings, Thermal Characteristics, Communication & Data, Environmental Limits, Warranty & Lifecycle |
| Monitoring, Metering & Communication | `MON_WEATHER` | Weather Station & Pyranometer | 11 | 0 | 2 | Product Architecture, Thermal Characteristics, Measurement & Accuracy, Monitoring & Instrumentation, Communication & Data, Environmental Limits, Compliance & Standards |
| Monitoring, Metering & Communication | `MON_SENSOR` | Temperature & Environmental Sensor | 10 | 0 | 2 | Product Architecture, Thermal Characteristics, Measurement & Accuracy, Communication & Data, Environmental Limits, Installation Requirements |
| Grounding, Lightning & Safety | `GROUNDING_SYSTEM` | Grounding Electrode & Conductor | 9 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Mechanical & Structural, Environmental Limits, Installation Requirements, Application Scope, Compliance & Standards |
| Grounding, Lightning & Safety | `LIGHTNING_PROTECTION` | Lightning Protection System | 10 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Mechanical & Structural, Monitoring & Instrumentation, Environmental Limits, Installation Requirements, Safety & Hazard Controls, Application Scope, Compliance & Standards |
| Grounding, Lightning & Safety | `BONDING` | Equipotential Bonding | 9 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Mechanical & Structural, Environmental Limits, Installation Requirements, Application Scope, Compliance & Standards |
| Grounding, Lightning & Safety | `SAFETY_LABEL_LOTO` | Warning Label & LOTO | 9 | 0 | 2 | Product Architecture, Materials & Construction, Mechanical & Structural, Environmental Limits, Safety & Hazard Controls, Application Scope, Compliance & Standards |
| Panel & Distribution | `PANEL_DCDB` | DC Distribution Board | 12 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Monitoring & Instrumentation, Environmental Limits, Installation Requirements, Protection Ratings, Compliance & Standards |
| Panel & Distribution | `PANEL_ACDB` | AC Distribution Board | 12 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Monitoring & Instrumentation, Environmental Limits, Installation Requirements, Protection Ratings, Compliance & Standards |
| Panel & Distribution | `PANEL_BACKUP` | Critical Load / Backup Panel | 12 | 0 | 2 | Product Architecture, Electrical Ratings, Control & Automation, Environmental Limits, Protection Ratings, Compliance & Standards |
| Panel & Distribution | `PANEL_LVMDP` | LVMDP Integration | 13 | 0 | 2 | Product Architecture, Materials & Construction, Electrical Ratings, Monitoring & Instrumentation, Communication & Data, Environmental Limits, Protection Ratings, Safety & Hazard Controls, Compliance & Standards |
| Panel & Distribution | `PANEL_SYNC` | Synchronization Panel | 13 | 0 | 2 | Product Architecture, Electrical Ratings, Monitoring & Instrumentation, Control & Automation, Communication & Data, Environmental Limits, Protection Ratings, Compatibility, Compliance & Standards |
| Civil & Site Work | `CIVIL_ANCHOR_SEALANT` | Anchor, Sealant & Waterproofing | 9 | 0 | 2 | Materials & Construction, Environmental Limits, Installation Requirements, Compatibility, Application Scope, Service & Deliverables, Execution Resources |
| Civil & Site Work | `CIVIL_FOUNDATION` | Foundation & Structural Civil Work | 10 | 0 | 2 | Materials & Construction, Mechanical & Structural, Installation Requirements, Service & Deliverables, Execution Resources |
| Civil & Site Work | `CIVIL_TRENCH_DUCT` | Trenching & Duct Bank | 10 | 0 | 2 | Materials & Construction, Mechanical & Structural, Installation Requirements, Safety & Hazard Controls, Service & Deliverables, Execution Resources |
| Civil & Site Work | `CIVIL_SURVEY` | Survey & Site Assessment | 9 | 0 | 2 | Measurement & Accuracy, Application Scope, Service & Deliverables, Execution Resources |
| Civil & Site Work | `CIVIL_BUILDING_WORK` | Equipment Shelter & Site Facilities | 10 | 0 | 2 | Materials & Construction, Thermal Characteristics, Mechanical & Structural, Installation Requirements, Safety & Hazard Controls, Service & Deliverables |
| Fire & HSE | `FIRE_EXTINGUISHER` | Portable Fire Extinguisher | 9 | 0 | 2 | Product Architecture, Performance & Lifecycle, Installation Requirements, Protection Ratings, Safety & Hazard Controls, Application Scope, Maintenance, Compliance & Standards, Warranty & Lifecycle |
| Fire & HSE | `FIRE_DETECTION` | Smoke, Heat & Gas Detection | 10 | 0 | 2 | Product Architecture, Electrical Ratings, Performance & Lifecycle, Thermal Characteristics, Monitoring & Instrumentation, Communication & Data, Environmental Limits, Application Scope, Maintenance, Compliance & Standards |
| Fire & HSE | `FIRE_SUPPRESSION` | Fixed Fire Suppression | 10 | 0 | 2 | Product Architecture, Performance & Lifecycle, Monitoring & Instrumentation, Control & Automation, Safety & Hazard Controls, Application Scope, Maintenance, Compliance & Standards |
| Fire & HSE | `HSE_PPE` | PPE & Site Safety Equipment | 9 | 0 | 2 | Product Architecture, Materials & Construction, Mechanical & Structural, Environmental Limits, Safety & Hazard Controls, Application Scope, Maintenance, Compliance & Standards, Warranty & Lifecycle |
| Fire & HSE | `HSE_SERVICE` | HSE Procedure & Service | 8 | 0 | 2 | Product Architecture, Service & Deliverables, Execution Resources, Maintenance, Compliance & Standards |
| Legal, Certification & Compliance | `LEGAL_SLO_INSPECTION` | SLO & Technical Inspection | 9 | 0 | 2 | Service & Deliverables, Compliance & Standards |
| Legal, Certification & Compliance | `LEGAL_UTILITY` | Utility Application & Interconnection | 9 | 0 | 2 | Electrical Ratings, Control & Automation, Service & Deliverables, Compliance & Standards |
| Legal, Certification & Compliance | `LEGAL_CERTIFICATE` | Product / System Certification | 9 | 0 | 2 | Service & Deliverables, Compliance & Standards |
| Legal, Certification & Compliance | `LEGAL_PERMIT` | Permit & Registration | 8 | 0 | 2 | Service & Deliverables, Compliance & Standards, Commercial Reference |
| Logistics & O&M | `LOG_FREIGHT` | Freight & Delivery | 10 | 0 | 2 | Product Architecture, Monitoring & Instrumentation, Service & Deliverables, Commercial Reference |
| Logistics & O&M | `LOG_STORAGE` | Warehousing & Site Storage | 9 | 0 | 2 | Product Architecture, Monitoring & Instrumentation, Environmental Limits, Service & Deliverables, Execution Resources, Commercial Reference |
| Logistics & O&M | `OM_TEST_EQUIPMENT` | O&M Test Equipment | 8 | 0 | 2 | Product Architecture, Measurement & Accuracy, Monitoring & Instrumentation, Communication & Data, Environmental Limits, Safety & Hazard Controls, Warranty & Lifecycle |
| Logistics & O&M | `OM_SERVICE` | Annual O&M Service | 8 | 0 | 2 | Product Architecture, Monitoring & Instrumentation, Service & Deliverables, Maintenance |
| Logistics & O&M | `OM_SPARE_WARRANTY` | Spare Parts & Warranty Support | 8 | 0 | 2 | Product Architecture, Service & Deliverables, Maintenance, Commercial Reference |
| Supplier Quote & Pricelist | `QUOTE_MATERIAL` | Material Supplier Quote | 10 | 0 | 3 | Warranty & Lifecycle, Commercial Reference |
| Supplier Quote & Pricelist | `QUOTE_SERVICE` | Service Provider Quote | 10 | 0 | 2 | Service & Deliverables, Commercial Reference |
| Supplier Quote & Pricelist | `QUOTE_PRICELIST` | Supplier Pricelist | 10 | 0 | 3 | Commercial Reference |

## Verification performed in the build environment

| Check | Result |
|---|---|
| PHP syntax: schema | Pass |
| PHP syntax: synchronizer | Pass |
| PHP syntax: schema tests | Pass |
| JavaScript syntax | Pass |
| Schema structural and semantic audit | Pass, 0 errors |
| Laravel application boot / route enumeration | Pass |
| Total routes | 173 |
| Material Catalog routes | 23 |
| Direct Blade compilation: create/edit/form | Pass |
| `npm ci` | Pass, 0 vulnerabilities |
| `npm run build` | Pass, Vite 8.1.3, 3 modules |
| Clean-room check on fresh APP63 extraction | Pass: patch paths, syntax, 173 routes, 23 material routes, schema audit, Blade compile, and Vite build |
| PHPUnit in this container | Not runnable because `dom`, `mbstring`, `xml`, and `xmlwriter` are absent |

The PHPUnit limitation is environmental. The updated unit tests are included and must be executed in the user environment where the existing suite already runs.

## Required installation and regression run

```powershell
php artisan optimize:clear
npm ci
npm run build
php artisan test
php artisan optimize
```

No migration or taxonomy reseed is required by this precision patch.

## Focused UAT after installation

1. Re-open and re-save the existing PV Module, Rack Battery, and DC SPD test records.
2. Confirm PV cell fields are under Product Architecture, efficiency under Performance, and temperature coefficients under Thermal.
3. Confirm battery Ah/kWh labels are unambiguous, round-trip efficiency is under Performance, and operating temperature is under Thermal.
4. Confirm DC SPD has one classification field only, while response time and Iscpv are under Protection and visual/remote indicators are under Monitoring.
5. Confirm combined categories show blue Subtype dependent badges and do not count those fields as universal required fields.
6. Confirm edit/save preserves current values, cleans only retired duplicates, and does not remove custom specifications.
7. Run the full test suite and then perform Price Book → BOM repricing → Client Release gate regression with one Approved and one Needs Review material.
