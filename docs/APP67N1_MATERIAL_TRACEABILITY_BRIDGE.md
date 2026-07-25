# APP67N.1 — Material Traceability Bridge

## Baseline

This patch is calculated against the exact working-tree snapshot contained in `NEXORIN-APP.zip` received on 25 July 2026. The embedded Git `HEAD` remains commit `3252207` dated 10 July 2026, but it does not contain the complete APP67M.4 working tree. The 10 July commit must therefore remain historical; the ZIP snapshot is the patch baseline.

## Audit finding

The pricing and SLD document-control contracts were not reading the same metadata path:

- `PricingEngine` stores the selected catalog record under `BillOfMaterialItem.metadata.pricing_source_trace`.
- `SldDocumentControl` previously looked only for top-level `metadata.material_id`, `brand`, `model`, and `datasheet_id`.

A BOM line could therefore be resolved to a real Material Catalog product while the SLD equipment register still displayed incomplete product traceability.

## Change

APP67N.1 introduces a versioned traceability bridge:

1. `PriceResolver` freezes the selected material identity in the pricing trace:
   - material ID, SKU, name, brand, and model;
   - catalog and compliance status;
   - active datasheet ID, revision, and SHA-256.
2. `SldEquipmentTraceResolver` maps BOM evidence into the controlled SLD equipment-register payload.
3. `SldDocumentControl` reads both:
   - legacy top-level BOM metadata; and
   - the current nested pricing-source trace.
4. Existing priced BOM rows that predate APP67N.1 fall back to the current visible Material Catalog record when the older trace lacks brand, model, or datasheet fields.

## Precedence

Controlled equipment identity is resolved in this order:

1. explicit legacy/top-level BOM metadata;
2. immutable pricing-source snapshot;
3. current visible Material Catalog fallback.

This keeps historical overrides backward compatible while preventing later catalog edits from silently rewriting a complete pricing snapshot.

## Compatibility

- No database migration.
- No route or permission change.
- No topology, electrical-rule, renderer, routing, geometry, label, or visual-fingerprint change.
- Existing APP67L.4 document-control version remains locked.
- Existing BOM rows remain readable.

## Validation

The patch adds:

- deterministic contract test: `npm run test:sld-app67n1:php`;
- feature coverage for Price Resolver material snapshots;
- feature coverage for SLD equipment-register resolution and legacy fallback.

The deterministic APP67K, APP67L, and APP67M contracts must remain green.

## Explicitly deferred

The following audited issues are real but are intentionally not mixed into APP67N.1:

- engineering fingerprint currently includes BOM timestamps and may stale an SLD after a pricing-only change;
- the fingerprint is timestamp-oriented rather than a canonical engineering-content hash;
- Material Catalog electrical specifications are not yet reconciled against Technical Calculation inputs;
- equipment traceability warnings are not yet promoted to stricter issue-status gates;
- Technical Calculation uses a mutable project row and does not yet provide a complete immutable calculation revision.

Those items require separate patches with independent regression tests.
