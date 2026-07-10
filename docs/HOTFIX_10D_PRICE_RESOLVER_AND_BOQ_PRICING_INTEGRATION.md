# HOTFIX 10D Price Resolver and BOQ Pricing Integration

## Status

Completed patch scope. Run the test suite after applying this patch.

## Purpose

Sprint 10D connects the 10A material catalog and the 10B/10C supplier price book workflow into the BOQ pricing engine. BOQ pricing no longer depends only on the static MVP cost table. The engine first tries to resolve each chargeable BOQ item from the active company price book. If no company price is available, it uses the existing manual fallback table. If no company price or fallback price exists, the BOQ is marked as missing price.

## Main Changes

### 1. Company price resolver

Added `App\Services\Pricing\PriceResolver`.

The resolver checks active company price books and evaluates candidate price book items using:

- BOQ item code
- material SKU
- optional metadata SKU
- known resolver aliases
- supplier SKU
- material name and model
- quote status
- quote validity date
- price book effective date
- availability status
- supplier status
- selling reference or margin reference

Obsolete quotes are not used as the default price source.

### 2. BOQ pricing integration

Updated `App\Services\Pricing\PricingEngine` so every chargeable BOQ item receives a pricing decision:

- `company_price_book`
- `manual_fallback`
- `missing_price`
- `summary_cost`
- excluded scope state

Each item now stores `pricing_source_trace` inside item metadata. The trace includes price book, supplier, supplier SKU, material SKU, quote status, validity date, availability, lead time, match method, and resolver timestamp.

### 3. Pricing health summary

The BOQ summary now records:

- `company_price_book_item_count`
- `manual_fallback_item_count`
- `missing_price_item_count`
- `expired_price_item_count`
- `unavailable_price_item_count`
- `pricing_health`

The final BOQ pricing status can be:

- `priced`
- `missing_price`
- `partially_priced`
- `expired_price_warning`
- `supplier_unavailable_warning`

### 4. Release gate integration

Updated `ClientReleaseGate` so client release is only considered ready when:

- BOQ exists
- BOQ pricing status is `priced`
- BOQ grand price is greater than zero
- no missing price item exists
- no expired supplier price item exists
- no unavailable supplier price item exists

### 5. UI polish

Updated BOQ detail page to show:

- pricing status
- company price book item count
- manual fallback count
- missing or review item count
- price source trace per BOQ line

### 6. Tests

Added `tests/Feature/BoqPriceResolverIntegrationTest.php`.

Covered cases:

- active company price book item is used first
- obsolete quote is rejected as default and manual fallback is used
- expired quote sets warning status
- unavailable supplier item sets warning status
- missing required price blocks priced status

## Expected Commands

```bash
composer dump-autoload
php artisan optimize:clear
php artisan test tests/Feature/BoqPriceResolverIntegrationTest.php
php artisan test tests/Unit/PricingEngineTest.php
php artisan test tests/Unit/ClientReleaseGateTest.php
php artisan test
```

## Notes

This sprint does not add new database columns. Price source trace is stored in existing BOQ item metadata so the patch remains low risk and compatible with the current BOM/BOQ schema.
