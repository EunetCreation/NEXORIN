<?php

namespace App\Services\Sld;

use App\Models\BillOfMaterialItem;
use App\Models\Material;

class SldEquipmentTraceResolver
{
    public const VERSION = 'APP67N.1';

    public function materialId(BillOfMaterialItem $item): ?int
    {
        $value = $this->firstFilled(
            data_get($item->metadata, 'material_id'),
            data_get($item->metadata, 'pricing_source_trace.material_id')
        );

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function searchText(BillOfMaterialItem $item): string
    {
        return collect([
            $item->category,
            $item->item_name,
            $item->description,
            data_get($item->metadata, 'material_name'),
            data_get($item->metadata, 'brand'),
            data_get($item->metadata, 'model'),
            data_get($item->metadata, 'pricing_source_trace.material_name'),
            data_get($item->metadata, 'pricing_source_trace.material_brand'),
            data_get($item->metadata, 'pricing_source_trace.material_model'),
        ])->filter()->implode(' ');
    }

    /** @return array<string, mixed> */
    public function resolve(BillOfMaterialItem $item, ?Material $material = null): array
    {
        $metadata = is_array($item->metadata) ? $item->metadata : [];
        $trace = is_array(data_get($metadata, 'pricing_source_trace'))
            ? data_get($metadata, 'pricing_source_trace')
            : [];
        $datasheet = $material?->datasheets?->first();
        $materialId = $this->materialId($item);

        return [
            'bom_item_id' => $item->getKey(),
            'item_code' => $item->item_code,
            'name' => $item->item_name,
            'material_name' => $this->firstFilled(
                data_get($metadata, 'material_name'),
                data_get($trace, 'material_name'),
                $material?->name
            ),
            'material_sku' => $this->firstFilled(
                data_get($metadata, 'material_sku'),
                data_get($trace, 'material_sku'),
                $material?->sku
            ),
            'brand' => $this->firstFilled(
                data_get($metadata, 'brand'),
                data_get($trace, 'material_brand'),
                $material?->brand
            ),
            'model' => $this->firstFilled(
                data_get($metadata, 'model'),
                data_get($trace, 'material_model'),
                $material?->model
            ),
            'material_id' => $materialId,
            'datasheet_id' => $this->firstFilled(
                data_get($metadata, 'datasheet_id'),
                data_get($trace, 'material_datasheet_id'),
                $datasheet?->getKey()
            ),
            'datasheet_revision' => $this->firstFilled(
                data_get($metadata, 'datasheet_revision'),
                data_get($trace, 'material_datasheet_revision'),
                $datasheet?->revision
            ),
            'datasheet_sha256' => $this->firstFilled(
                data_get($metadata, 'datasheet_sha256'),
                data_get($trace, 'material_datasheet_sha256'),
                $datasheet?->sha256
            ),
            'material_catalog_status' => $this->firstFilled(
                data_get($metadata, 'material_catalog_status'),
                data_get($trace, 'material_catalog_status'),
                $material?->catalog_status
            ),
            'material_compliance_status' => $this->firstFilled(
                data_get($metadata, 'material_compliance_status'),
                data_get($trace, 'material_compliance_status'),
                $material?->compliance_status
            ),
            'traceability_version' => self::VERSION,
            'traceability_source' => filled(data_get($metadata, 'material_id'))
                ? 'bom_metadata'
                : (filled(data_get($trace, 'material_id')) ? 'pricing_source_trace' : null),
            'quantity' => $item->quantity,
            'unit' => $item->unit,
        ];
    }

    private function firstFilled(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
