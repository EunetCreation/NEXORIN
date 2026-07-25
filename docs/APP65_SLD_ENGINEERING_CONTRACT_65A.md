# APP65 — SLD Engineering Contract 65A

## Purpose

This patch introduces the first controlled Single-Line Diagram (SLD) vertical slice on top of the current APP64C material/parser baseline. The SLD is a revisioned engineering drawing snapshot. It is not a second technical-calculation engine and it does not replace material specifications, the technical calculation, or BOM/BOQ.

## Supported MVP topology

- On-grid rooftop PV
- Hybrid rooftop PV with a battery and backup-load panel

The generator intentionally does not claim to support off-grid topology, detailed protection coordination, utility approval settings, short-circuit studies, or field installation as-built drawings.

## Source of truth

The graph is built from the current project records:

1. `TechnicalCalculation` and its `results`/`warnings`
2. `SystemRecommendation`
3. Project source freshness (load, solar, roof/site survey)
4. BOM/BOQ identity and update timestamp

The generated `source_snapshot.fingerprint` is used to detect whether a revision is stale before approval.

## Graph contract

Every graph contains:

- `schema_version`
- `meta` — project, system type, calculation state, and generation timestamp
- `nodes` — stable component IDs, labels, displayed ratings, category, and review state
- `connections` — directed `from`/`to` links with readable labels and details
- `warnings` — normalized calculation/SLD notices
- `source_snapshot` — source IDs, timestamps, and fingerprint

The first stable node IDs are:

`pv_array`, `dc_protection`, `inverter`, `ac_protection`, `meter`, `grid`, and, for hybrid systems, `battery` and `backup_panel`.

## Revision workflow

`draft → needs_review → approved`

When a new revision is generated, the previous non-superseded revision becomes `superseded`. An approval is blocked when:

- the source fingerprint is stale;
- the linked technical calculation is not engineer verified; or
- the SLD graph contains a critical warning.

## Output

- Inline SVG preview with zoom controls
- SVG download for controlled editing/archival
- PDF export through the existing NEXORIN PDF renderer

## Deliberate next boundary

The next SLD increment may add controlled label/position editing and a release-gate check. It must continue to reference the existing calculation/material/BOM records and must not introduce a parallel electrical truth model.
