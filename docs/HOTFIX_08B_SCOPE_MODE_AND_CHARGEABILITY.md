# Hotfix 08B: Scope Mode and Chargeability Control

## Purpose

Hotfix 08A expanded the EPC master material catalog to 100+ BOM/BOQ rows. That improved scope completeness, but it also made the base customer price too high because several catalog candidates were charged automatically.

Hotfix 08B separates catalog visibility from base-price chargeability.

## Core behavior

NEXORIN now treats each BOM/BOQ row through two independent controls:

1. **Scope visibility**: whether the item appears in internal reports, client reports, or only as a catalog candidate.
2. **Chargeability**: whether the item is included in the current base BOQ price.

This keeps the master material catalog complete while preventing optional, utility, not-applicable, and engineer-selection items from inflating the base quotation.

## Scope modes

| Scope mode | Meaning | Included in base price |
|---|---|---|
| Base BOQ | Confirmed project scope and priced in the current BOQ | Yes |
| Included in Summary Cost | Shown as a service or logistics line but costed through summary parameters | No line price |
| Optional Add-on | Available for selection but not included by default | No |
| Utility Responsibility | Listed for boundary clarity but handled by utility unless re-scoped | No |
| Owner Responsibility | Listed for boundary clarity but handled by owner unless re-scoped | No |
| Catalog Candidate | Engineering candidate from the master catalog that requires selection or confirmation | No |
| Not Applicable | Not applicable to the current system, scale, or mounting type | No |

## New metadata fields

Each generated item can now carry:

- `scope_mode`
- `included_in_base_price`
- `include_in_client_pdf`
- `include_in_internal_report`
- `requires_engineer_selection`
- `report_visibility`
- `catalog_layer`
- `catalog_version`

## Expected result for residential hybrid projects

A typical hybrid residential project may still show more than 100 internal scope rows. However, only base BOQ rows should enter the customer-facing price. Commercial, MV, SCADA, PCS, weather station, advanced testing, and large BESS candidates remain visible as internal catalog candidates or optional add-ons until selected.

## Validation notes

After applying this patch, regenerate in this order:

1. Technical Calculation
2. BOM/BOQ
3. Apply Pricing
4. Financial Analysis
5. Quotation
6. Premium PDF

The premium report should show scope counters, such as Base BOQ, Catalog Candidates, and Optional Add-ons.
