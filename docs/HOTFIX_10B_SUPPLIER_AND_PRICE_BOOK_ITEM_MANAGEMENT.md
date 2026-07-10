# HOTFIX 10B — Supplier and Price Book Item Management

## Purpose

This hotfix extends the 10A material catalog foundation with database-backed price book item management. A company can now connect active catalog materials to a price book, assign a supplier reference, store cost data, define selling references, track availability, and manage quote validity.

## Scope delivered

- Added `PriceBookItemController` for item store, edit, update, and remove actions.
- Added nested company routes under `price-books/{priceBook}/items`.
- Expanded the price book detail screen with:
  - price item summary cards,
  - add item form,
  - item table with supplier, cost, selling reference, MOQ, lead time, availability, validity, and actions,
  - expired and missing-reference indicators.
- Added price book item edit screen and reusable form partial.
- Added model accessors on `PriceBookItem` for availability label, expiry state, selling-reference state, and effective margin.
- Added feature test coverage for create, update, remove, cross-company material protection, and permission blocking.

## Validation rules

Each price book item must use:

- one active material visible to the company,
- one unique material per price book,
- optional active supplier from the same company,
- non-negative unit cost,
- optional selling price or margin reference,
- optional MOQ and lead time,
- controlled availability status,
- quote validity where `valid_until` cannot be earlier than `quoted_at`.

## Availability statuses

- `available`
- `limited`
- `backorder`
- `unavailable`
- `discontinued`

## Security and tenancy rules

The sprint keeps the 10A security model.

- Viewing price books requires `price.cost.view`.
- Managing price book items requires `price.cost.update`.
- Materials from another company cannot be attached.
- Suppliers from another company cannot be attached.
- Inactive materials cannot be attached to new price items.

## Current boundary

10B manages supplier and price book item data only. It does not yet replace BOQ pricing. Integration with the live `PricingEngine` and project BOQ selection remains planned for later catalog sprints.

## Suggested next sprint

10C Datasheet and Certificate Uploads should add upload, metadata, validation, expiry checks, and document visibility for material datasheets and certificates.
