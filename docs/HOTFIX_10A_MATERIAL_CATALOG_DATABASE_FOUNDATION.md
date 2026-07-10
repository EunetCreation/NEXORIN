# HOTFIX 10A — Material Catalog Database Foundation

## Purpose

This hotfix adds the first database-backed catalog layer for NEXORIN. The existing BOM/BOQ engine remains intact, but the application now has a structured foundation for real materials, suppliers, price books, datasheets, and certificates.

## Added database tables

- `material_categories`
- `materials`
- `material_specs`
- `suppliers`
- `price_books`
- `price_book_items`
- `material_datasheets`
- `material_certificates`

## Added models

- `MaterialCategory`
- `Material`
- `MaterialSpec`
- `Supplier`
- `PriceBook`
- `PriceBookItem`
- `MaterialDatasheet`
- `MaterialCertificate`

## Added management screens

Company-scoped routes are available for:

- Material Catalog
- Suppliers
- Price Books

The material catalog supports global reference items and company-specific items. Global catalog items are read-only from the company workspace; company-specific items can be created, updated, archived, and activated based on existing RBAC permissions.

## RBAC

This hotfix reuses the existing permission model:

- `equipment.view`, `equipment.create`, `equipment.update`, `equipment.delete`
- `supplier.view`, `supplier.create`, `supplier.update`, `supplier.delete`
- `price.cost.view`, `price.cost.update`

No new permission records are required.

## Seeder

`MaterialCatalogSeeder` adds a local starter catalog for the existing NEXORIN development company. It creates starter categories, sample PV materials, a demo supplier, and a demo price book.

The seeder is local-only and is called from `DatabaseSeeder` after development company data is created.

## Current boundary

10A does not yet replace the `QuantityEngine` or `PricingEngine`. That integration should happen in later sprints:

- 10B Supplier and Price Book Item Management
- 10C Datasheet and Certificate Uploads
- 10D BOQ Catalog Selection and Substitution
- 10E Catalog-Based Pricing Engine
