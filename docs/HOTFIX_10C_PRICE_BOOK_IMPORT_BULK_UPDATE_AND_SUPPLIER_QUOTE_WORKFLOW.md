# HOTFIX 10C - Price Book Import, Bulk Update, and Supplier Quote Workflow

## Status
Implemented as the next sprint after 10B Supplier and Price Book Item Management.

## Purpose
10C turns price book item management from single-row entry into a procurement-ready price workflow. The module now supports bulk price maintenance, CSV supplier import with preview validation, supplier quote status control, and price health visibility.

## Scope

### 1. Bulk price update
Company users with `price.cost.update` can update multiple price book items from the price book detail page.

Bulk fields:
- unit cost
- selling price override
- margin percent
- minimum order quantity
- lead time days
- availability status
- quote status
- quoted date
- valid until date

Validation rules:
- unit cost must be non-negative
- selling price must be non-negative when filled
- margin must be between 0 and 1000 percent
- lead time must be between 0 and 3650 days
- validity end date may not be earlier than quoted date
- every updated item must belong to the selected price book

The system allows a selling price below unit cost but returns a visible warning. This supports real procurement review while avoiding silent price risk.

### 2. CSV import with preview before commit
Price books now have an import page. The import process has two steps:

1. Upload CSV and preview validation result.
2. Commit only valid and warning rows.

Accepted material matching headers:
- `material_key`
- `material_sku`
- `sku`
- `item_code`
- `material`

Accepted supplier matching headers:
- `supplier_code`
- `supplier`
- `supplier_name`

Accepted pricing headers:
- `unit_cost`
- `cost`
- `harga_modal`
- `selling_price`
- `harga_jual`
- `margin_percent`
- `margin`

Accepted procurement headers:
- `supplier_sku`
- `vendor_sku`
- `minimum_order_quantity`
- `moq`
- `lead_time_days`
- `lead_time`

Accepted quote workflow headers:
- `quote_status`
- `status_quote`
- `quoted_at`
- `quote_date`
- `valid_until`
- `expired_at`
- `expiry_date`

Accepted notes:
- `warranty_note`
- `warranty`
- `notes`
- `note`

Preview statuses:
- valid: row can be committed without warning
- warning: row can be committed but needs procurement review
- error: row cannot be committed

Error examples:
- material not found
- material inactive or not visible to the company
- inactive supplier
- negative cost
- unsupported availability status
- unsupported quote status
- invalid date
- duplicate material inside the uploaded CSV

Warning examples:
- selling price below unit cost
- quote already expired
- no supplier on the row or price book header

### 3. Update existing item instead of duplicating
The importer uses the pair `price_book_id + material_id` as the identity. If the material already exists in the price book, the import updates the row. If not, the import creates a new row. This preserves the unique price book item rule from 10B.

### 4. Supplier quote workflow
A new migration adds two fields to `price_book_items`:

- `quote_status`: active or obsolete
- `import_batch_code`: import trace code for CSV batches

Computed quote status:
- active quote stays active while it is still valid
- active quote becomes expired when `valid_until` is earlier than today
- obsolete quote stays obsolete regardless of validity date

### 5. Price health dashboard
The price book detail page now shows a procurement health overview:

- healthy items
- expired quotes
- obsolete quotes
- unavailable items
- low margin items
- items without selling reference
- missing cost items

Price health labels are computed in `PriceBookItem`:

- Healthy
- Missing Cost
- Unavailable
- Expired Quote
- Obsolete Quote
- Low Margin
- No Selling Reference

## Files added
- `app/Http/Controllers/PriceBookBulkItemController.php`
- `database/migrations/2026_07_07_001000_add_quote_workflow_fields_to_price_book_items_table.php`
- `resources/views/price_book_items/import.blade.php`
- `resources/views/price_book_items/import_preview.blade.php`
- `tests/Feature/PriceBookBulkItemManagementTest.php`
- `docs/HOTFIX_10C_PRICE_BOOK_IMPORT_BULK_UPDATE_AND_SUPPLIER_QUOTE_WORKFLOW.md`

## Files changed
- `routes/web.php`
- `app/Http/Controllers/PriceBookController.php`
- `app/Http/Controllers/PriceBookItemController.php`
- `app/Models/PriceBookItem.php`
- `resources/views/price_books/show.blade.php`
- `resources/views/price_book_items/partials/form.blade.php`

## Suggested verification

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
php artisan test tests/Feature/PriceBookItemManagementTest.php
php artisan test tests/Feature/PriceBookBulkItemManagementTest.php
php artisan test
```

## Expected result
10A and 10B remain stable. 10C adds procurement speed and quote traceability without changing the existing single-row price item workflow.
