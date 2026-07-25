<?php

declare(strict_types=1);

use App\Models\BillOfMaterialItem;
use App\Models\Material;
use App\Models\MaterialDatasheet;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Supplier;
use App\Services\Pricing\PriceResolver;
use App\Services\Sld\SldEquipmentTraceResolver;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, "[APP67N.1] FAIL · {$message}\n");
        exit(1);
    }
};

$datasheet = new MaterialDatasheet([
    'title' => 'Catalog module datasheet',
    'revision' => 'Catalog Rev. 4',
    'sha256' => str_repeat('a', 64),
    'is_active' => true,
]);
$datasheet->id = 701;
$datasheet->exists = true;

$material = new Material([
    'sku' => 'CAT-PV-550',
    'name' => 'Catalog PV Module',
    'brand' => 'Catalog Solar',
    'model' => 'CAT-550',
    'catalog_status' => 'approved',
    'compliance_status' => 'compliant',
]);
$material->id = 501;
$material->exists = true;
$material->setRelation('datasheets', collect([$datasheet]));

$item = new BillOfMaterialItem([
    'category' => 'pv_module',
    'item_code' => 'PV-MODULE',
    'item_name' => 'Solar PV Module',
    'quantity' => 20,
    'unit' => 'pcs',
    'metadata' => [
        'pricing_source_trace' => [
            'material_id' => 501,
            'material_sku' => 'SNAP-PV-550',
            'material_name' => 'Snapshot PV Module',
            'material_brand' => 'Snapshot Solar',
            'material_model' => 'SNAP-550',
            'material_catalog_status' => 'approved',
            'material_compliance_status' => 'compliant',
            'material_datasheet_id' => 700,
            'material_datasheet_revision' => 'Snapshot Rev. 2',
            'material_datasheet_sha256' => str_repeat('b', 64),
        ],
    ],
]);
$item->id = 301;
$item->exists = true;

/** @var SldEquipmentTraceResolver $traceResolver */
$traceResolver = $app->make(SldEquipmentTraceResolver::class);
$resolved = $traceResolver->resolve($item, $material);

$assert($traceResolver->materialId($item) === 501, 'nested pricing trace must expose the selected material id');
$assert(str_contains(strtolower($traceResolver->searchText($item)), 'snapshot solar'), 'equipment grouping must search the pricing trace identity');
$assert($resolved['material_sku'] === 'SNAP-PV-550', 'pricing snapshot SKU must override the mutable catalog fallback');
$assert($resolved['brand'] === 'Snapshot Solar', 'pricing snapshot brand must override the mutable catalog fallback');
$assert($resolved['model'] === 'SNAP-550', 'pricing snapshot model must override the mutable catalog fallback');
$assert($resolved['datasheet_id'] === 700, 'pricing snapshot datasheet id must be retained');
$assert($resolved['datasheet_revision'] === 'Snapshot Rev. 2', 'pricing snapshot datasheet revision must be retained');
$assert($resolved['datasheet_sha256'] === str_repeat('b', 64), 'pricing snapshot datasheet checksum must be retained');
$assert($resolved['traceability_source'] === 'pricing_source_trace', 'nested pricing evidence must declare its traceability source');
$assert($resolved['traceability_version'] === SldEquipmentTraceResolver::VERSION, 'equipment trace must publish the APP67N.1 contract version');

$legacyItem = new BillOfMaterialItem([
    'item_code' => 'PV-MODULE',
    'item_name' => 'Legacy module row',
    'quantity' => 10,
    'unit' => 'pcs',
    'metadata' => [
        'material_id' => 501,
        'brand' => 'Locked Legacy Brand',
        'model' => 'LOCK-550',
        'pricing_source_trace' => [
            'material_id' => 999,
            'material_brand' => 'Ignored Trace Brand',
            'material_model' => 'IGNORED-550',
        ],
    ],
]);
$legacyResolved = $traceResolver->resolve($legacyItem, $material);
$assert($legacyResolved['material_id'] === 501, 'legacy top-level material id must remain backward compatible');
$assert($legacyResolved['brand'] === 'Locked Legacy Brand', 'legacy locked brand must retain precedence');
$assert($legacyResolved['model'] === 'LOCK-550', 'legacy locked model must retain precedence');
$assert($legacyResolved['traceability_source'] === 'bom_metadata', 'legacy evidence source must remain explicit');

$fallbackItem = new BillOfMaterialItem([
    'item_code' => 'PV-MODULE',
    'item_name' => 'Existing priced module',
    'quantity' => 5,
    'unit' => 'pcs',
    'metadata' => [
        'pricing_source_trace' => [
            'material_id' => 501,
            'material_name' => 'Older pricing trace',
        ],
    ],
]);
$fallbackResolved = $traceResolver->resolve($fallbackItem, $material);
$assert($fallbackResolved['brand'] === 'Catalog Solar', 'older trace must fall back to the current catalog brand');
$assert($fallbackResolved['model'] === 'CAT-550', 'older trace must fall back to the current catalog model');
$assert($fallbackResolved['datasheet_id'] === 701, 'older trace must fall back to an active catalog datasheet');

$priceBook = new PriceBook(['code' => 'PB-TRACE-2026', 'name' => 'Trace Price Book']);
$priceBook->id = 11;
$priceBook->exists = true;
$supplier = new Supplier(['name' => 'Trace Supplier', 'status' => 'active']);
$supplier->id = 21;
$supplier->exists = true;
$priceItem = new PriceBookItem([
    'price_book_id' => 11,
    'material_id' => 501,
    'supplier_id' => 21,
    'supplier_sku' => 'SUP-PV-550',
    'availability_status' => 'available',
    'quote_status' => 'active',
]);
$priceItem->id = 31;
$priceItem->exists = true;
$priceItem->setRelation('priceBook', $priceBook);
$priceItem->setRelation('material', $material);
$priceItem->setRelation('supplier', $supplier);

$traceMethod = new ReflectionMethod(PriceResolver::class, 'trace');
$pricingTrace = $traceMethod->invoke(
    new PriceResolver(),
    $priceItem,
    ['compatibility' => ['compatible' => true], 'matched_by' => 'item_code', 'match_score' => 100, 'quality_score' => 100],
    'priced'
);
$assert($pricingTrace['material_trace_version'] === PriceResolver::MATERIAL_TRACE_VERSION, 'pricing trace must publish the v2 material contract');
$assert($pricingTrace['material_brand'] === 'Catalog Solar', 'pricing trace must freeze the selected material brand');
$assert($pricingTrace['material_model'] === 'CAT-550', 'pricing trace must freeze the selected material model');
$assert($pricingTrace['material_datasheet_id'] === 701, 'pricing trace must freeze the selected datasheet id');
$assert($pricingTrace['material_datasheet_sha256'] === str_repeat('a', 64), 'pricing trace must freeze the datasheet checksum');

fwrite(STDOUT, "[APP67N.1] PASS · price-book material snapshot, legacy compatibility, catalog fallback, and SLD equipment traceability\n");
