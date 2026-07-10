# HOTFIX 10E - BOQ Margin, Tax, Discount, and Client Quotation Integration

## Status
Implemented as the commercial pricing layer after 10D Price Resolver and BOQ Pricing Integration.

## Objective
Turn resolved BOQ prices into client quotation-ready commercial totals while keeping internal cost, supplier trace, and margin data out of customer-facing quotation output.

## Scope

### Commercial pricing layer
- Calculates material cost subtotal.
- Calculates material selling subtotal.
- Calculates service subtotal from labor, logistics, and other cost.
- Calculates contingency.
- Calculates taxable subtotal.
- Supports tax exclusive and tax inclusive mode.
- Supports item discount and global discount separation.
- Supports IDR rounding unit.
- Stores net revenue before tax, gross margin amount, and gross margin percent.

### Margin rules
- Keeps price resolver selling price as the default line selling price.
- Supports manual fallback markup.
- Supports item-level margin override through pricing parameters.
- Adds minimum margin guard.
- Marks BOQ as `margin_review_required` when a chargeable item falls below the minimum margin.

### Discount rules
- Supports item-level discount override through pricing parameters.
- Supports global quotation discount.
- Adds maximum discount guard.
- Marks BOQ as `discount_review_required` when item or global discount exceeds the commercial limit.

### Tax rules
- Adds `tax_mode` with `exclusive` and `inclusive` options.
- Adds tax exempt item code support.
- Adds tax exempt category support for engine-level use.
- Adds optional service and contingency tax exemption flags.
- Stores tax trace in BOQ summary.

### Client quotation safety
- Quotation generation continues to use final selling totals from priced BOQ.
- Customer-facing quotation items do not expose unit cost, gross margin, supplier cost, supplier SKU, or price source trace.
- Commercial summary includes client-safe subtotal, tax, discount, rounding, and grand total.
- Internal pricing trace remains in BOM item metadata and internal reports.

### Release gate
- Client release gate now blocks or warns when:
  - BOQ margin is below minimum guard.
  - Discount exceeds the configured limit.
  - Tax settings require review.
  - Commercial total does not reconcile.
  - Existing 10D price resolver warnings remain open.

## Changed files
- `app/Services/Pricing/PricingEngine.php`
- `app/Http/Requests/ApplyBoqPricingRequest.php`
- `app/Services/Quotation/QuotationEngine.php`
- `app/Services/Report/ClientReleaseGate.php`
- `app/Support/BomBoqDictionary.php`
- `resources/views/boms/show.blade.php`
- `tests/Unit/CommercialPricingEngineTest.php`
- `tests/Unit/ClientReleaseGateTest.php`
- `docs/HOTFIX_10E_BOQ_MARGIN_TAX_DISCOUNT_AND_CLIENT_QUOTATION_INTEGRATION.md`

## Migration
No database migration is required. Commercial trace is stored in existing BOQ summary, pricing parameters, and item metadata fields.

## Suggested test commands

```bash
composer dump-autoload
php artisan optimize:clear
php artisan test tests/Unit/CommercialPricingEngineTest.php
php artisan test tests/Unit/PricingEngineTest.php
php artisan test tests/Unit/ClientReleaseGateTest.php
php artisan test tests/Unit/QuotationEngineTest.php
php artisan test
```
