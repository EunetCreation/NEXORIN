<?php

namespace App\Support;

use App\Models\Material;

class MaterialCatalogStandards
{
    public const PARSER_VERSION = '10I-B-text-v1';

    /**
     * Return required technical specification keys for a material category.
     *
     * These keys intentionally match engineering field names used in the
     * BOM/BOQ master document so the catalog can become the single source of
     * technical data for BOQ, validation, and client-safe reports.
     *
     * @return array<int, string>
     */
    public static function requiredSpecKeysFor(Material $material): array
    {
        return match (self::categoryCode($material)) {
            'PV_MODULE' => [
                'module_power_wp',
                'voc',
                'vmp',
                'isc',
                'imp',
                'temp_coeff_voc',
                'length_mm',
                'width_mm',
                'weight_kg',
                'max_system_voltage',
                'product_warranty_years',
                'performance_warranty_years',
            ],
            'POWER_CONVERSION' => [
                'inverter_type',
                'rated_ac_power_kw',
                'max_dc_voltage',
                'startup_voltage',
                'mppt_count',
                'mppt_voltage_min',
                'mppt_voltage_max',
                'max_input_current_per_mppt',
                'phase_type',
                'anti_islanding',
                'ip_rating',
                'warranty_years',
            ],
            'BATTERY_ESS' => [
                'battery_chemistry',
                'capacity_kwh',
                'usable_capacity_kwh',
                'dod_percent',
                'cycle_life',
                'max_charge_current_a',
                'max_discharge_current_a',
                'communication_protocol',
                'operating_temperature',
                'fire_safety_required',
            ],
            'DC_ELECTRICAL' => [
                'device_or_cable_type',
                'rated_voltage',
                'rated_current',
                'standard',
                'ip_rating',
            ],
            'AC_ELECTRICAL' => [
                'device_type',
                'rated_voltage',
                'rated_current',
                'breaking_capacity_ka',
                'poles',
                'standard',
            ],
            'MOUNTING_STRUCTURE' => [
                'mounting_type',
                'material_grade',
                'wind_speed_rating',
                'corrosion_class',
                'roof_penetration',
            ],
            'MONITORING' => [
                'protocol',
                'communication_port',
                'measured_parameter',
                'accuracy_class',
            ],
            'GROUNDING_SAFETY' => [
                'device_or_material_type',
                'rated_voltage',
                'standard',
                'safety_scope',
            ],
            default => [],
        };
    }

    /**
     * Return certificate types expected before a material can be approved.
     *
     * @return array<int, string>
     */
    public static function requiredCertificateTypesFor(Material $material): array
    {
        return match (self::categoryCode($material)) {
            'PV_MODULE' => ['IEC 61215', 'IEC 61730'],
            'POWER_CONVERSION' => ['IEC 62109'],
            'BATTERY_ESS' => ['UN38.3', 'IEC 62619'],
            'DC_ELECTRICAL', 'AC_ELECTRICAL' => ['IEC'],
            default => [],
        };
    }

    /**
     * Human readable labels for standards and parser output.
     */
    public static function labels(): array
    {
        return [
            'module_power_wp' => 'Module Power',
            'module_efficiency_percent' => 'Module Efficiency',
            'voc' => 'Open Circuit Voltage',
            'vmp' => 'Maximum Power Voltage',
            'isc' => 'Short Circuit Current',
            'imp' => 'Maximum Power Current',
            'temp_coeff_voc' => 'Temperature Coefficient Voc',
            'temp_coeff_pmax' => 'Temperature Coefficient Pmax',
            'length_mm' => 'Module Length',
            'width_mm' => 'Module Width',
            'weight_kg' => 'Module Weight',
            'max_system_voltage' => 'Maximum System Voltage',
            'product_warranty_years' => 'Product Warranty',
            'performance_warranty_years' => 'Performance Warranty',
            'inverter_type' => 'Inverter Type',
            'rated_ac_power_kw' => 'Rated AC Power',
            'max_dc_voltage' => 'Maximum DC Voltage',
            'startup_voltage' => 'Startup Voltage',
            'mppt_count' => 'MPPT Count',
            'mppt_voltage_min' => 'MPPT Voltage Minimum',
            'mppt_voltage_max' => 'MPPT Voltage Maximum',
            'max_input_current_per_mppt' => 'Maximum Input Current per MPPT',
            'phase_type' => 'Phase Type',
            'anti_islanding' => 'Anti-islanding',
            'ip_rating' => 'IP Rating',
            'warranty_years' => 'Warranty',
            'battery_chemistry' => 'Battery Chemistry',
            'capacity_kwh' => 'Nominal Capacity',
            'usable_capacity_kwh' => 'Usable Capacity',
            'dod_percent' => 'Depth of Discharge',
            'cycle_life' => 'Cycle Life',
            'max_charge_current_a' => 'Maximum Charge Current',
            'max_discharge_current_a' => 'Maximum Discharge Current',
            'communication_protocol' => 'Communication Protocol',
            'operating_temperature' => 'Operating Temperature',
            'fire_safety_required' => 'Fire Safety Required',
            'device_or_cable_type' => 'Device or Cable Type',
            'rated_voltage' => 'Rated Voltage',
            'rated_current' => 'Rated Current',
            'standard' => 'Standard',
            'device_type' => 'Device Type',
            'breaking_capacity_ka' => 'Breaking Capacity',
            'poles' => 'Poles',
            'mounting_type' => 'Mounting Type',
            'material_grade' => 'Material Grade',
            'wind_speed_rating' => 'Wind Speed Rating',
            'corrosion_class' => 'Corrosion Class',
            'roof_penetration' => 'Roof Penetration',
            'protocol' => 'Protocol',
            'communication_port' => 'Communication Port',
            'measured_parameter' => 'Measured Parameter',
            'accuracy_class' => 'Accuracy Class',
            'device_or_material_type' => 'Device or Material Type',
            'safety_scope' => 'Safety Scope',
        ];
    }

    public static function labelFor(string $key): string
    {
        return self::labels()[$key] ?? str($key)->replace('_', ' ')->title()->toString();
    }

    public static function categoryCode(Material $material): string
    {
        $category = strtoupper((string) $material->category?->code);

        if ($category !== '') {
            return $category;
        }

        $type = strtoupper((string) $material->material_type);

        return match (true) {
            str_contains($type, 'PV') || str_contains($type, 'MODULE') => 'PV_MODULE',
            str_contains($type, 'INVERTER') || str_contains($type, 'PCS') => 'POWER_CONVERSION',
            str_contains($type, 'BATTERY') || str_contains($type, 'ESS') => 'BATTERY_ESS',
            str_contains($type, 'DC') || str_contains($type, 'CABLE') => 'DC_ELECTRICAL',
            str_contains($type, 'AC') || str_contains($type, 'PANEL') => 'AC_ELECTRICAL',
            default => '',
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function parserPatterns(): array
    {
        return [
            'module_power_wp' => [
                '/(?:maximum\s+power|rated\s+power|nominal\s+power|pmax|module\s+power)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*(?:w|wp)\b/i',
            ],
            'module_efficiency_percent' => [
                '/(?:module\s+efficiency|efficiency)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*%/i',
            ],
            'voc' => [
                '/(?:voc|open\s+circuit\s+voltage)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*v\b/i',
            ],
            'vmp' => [
                '/(?:vmp|v\s*mp|maximum\s+power\s+voltage)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*v\b/i',
            ],
            'isc' => [
                '/(?:isc|short\s+circuit\s+current)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*a\b/i',
            ],
            'imp' => [
                '/(?:imp|i\s*mp|maximum\s+power\s+current)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*a\b/i',
            ],
            'temp_coeff_voc' => [
                '/(?:temp(?:erature)?\s+coeff(?:icient)?\s+voc|voc\s+temp(?:erature)?\s+coeff(?:icient)?)\s*[:=]?\s*([-+]?\d+(?:[\.,]\d+)?)\s*%?\/?(?:°c|c|k)?/i',
            ],
            'temp_coeff_pmax' => [
                '/(?:temp(?:erature)?\s+coeff(?:icient)?\s+pmax|pmax\s+temp(?:erature)?\s+coeff(?:icient)?)\s*[:=]?\s*([-+]?\d+(?:[\.,]\d+)?)\s*%?\/?(?:°c|c|k)?/i',
            ],
            'length_mm' => [
                '/(?:length|module\s+length)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*mm\b/i',
            ],
            'width_mm' => [
                '/(?:width|module\s+width)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*mm\b/i',
            ],
            'weight_kg' => [
                '/(?:weight|module\s+weight)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*kg\b/i',
            ],
            'max_system_voltage' => [
                '/(?:maximum\s+system\s+voltage|max\s+system\s+voltage)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*v\b/i',
            ],
            'product_warranty_years' => [
                '/(?:product\s+warranty)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*(?:years|year|yr|tahun)\b/i',
            ],
            'performance_warranty_years' => [
                '/(?:performance\s+warranty)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*(?:years|year|yr|tahun)\b/i',
            ],
            'rated_ac_power_kw' => [
                '/(?:rated\s+ac\s+power|ac\s+rated\s+power|nominal\s+ac\s+power)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*kw\b/i',
            ],
            'max_dc_voltage' => [
                '/(?:max(?:imum)?\s+dc\s+voltage|max(?:imum)?\s+input\s+voltage)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*v\b/i',
            ],
            'startup_voltage' => [
                '/(?:startup\s+voltage|start\s+voltage)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*v\b/i',
            ],
            'mppt_count' => [
                '/(?:mppt\s+count|number\s+of\s+mppt|mppt)\s*[:=]?\s*(\d+)\b/i',
            ],
            'battery_chemistry' => [
                '/(?:battery\s+chemistry|chemistry)\s*[:=]?\s*([a-z0-9\- ]{3,30})/i',
            ],
            'capacity_kwh' => [
                '/(?:nominal\s+capacity|capacity)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*kwh\b/i',
            ],
            'usable_capacity_kwh' => [
                '/(?:usable\s+capacity)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*kwh\b/i',
            ],
            'dod_percent' => [
                '/(?:dod|depth\s+of\s+discharge)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*%/i',
            ],
            'cycle_life' => [
                '/(?:cycle\s+life|cycles)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\b/i',
            ],
            'ip_rating' => [
                '/(?:ip\s+rating|protection\s+class)\s*[:=]?\s*(IP\s*\d{2})\b/i',
            ],
            'warranty_years' => [
                '/(?:warranty)\s*[:=]?\s*(\d+(?:[\.,]\d+)?)\s*(?:years|year|yr|tahun)\b/i',
            ],
        ];
    }

    public static function normalizeNumber(string $value): string
    {
        return str_replace(',', '.', trim($value));
    }

    public static function unitFor(string $key): ?string
    {
        return match (true) {
            str_ends_with($key, '_wp') => 'Wp',
            str_ends_with($key, '_kw') => 'kW',
            str_ends_with($key, '_kwh') => 'kWh',
            str_ends_with($key, '_percent') => '%',
            str_ends_with($key, '_mm') => 'mm',
            str_ends_with($key, '_kg') => 'kg',
            str_contains($key, 'voltage') || in_array($key, ['voc', 'vmp'], true) => 'V',
            str_contains($key, 'current') || in_array($key, ['isc', 'imp'], true) => 'A',
            str_contains($key, 'warranty') => 'years',
            $key === 'cycle_life' => 'cycles',
            default => null,
        };
    }
}
