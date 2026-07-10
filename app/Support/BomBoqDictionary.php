<?php

namespace App\Support;

class BomBoqDictionary
{
    public static function categoryLabel(?string $category): string
    {
        return match ((string) $category) {
            'pv_module' => 'PV Module',
            'mounting' => 'Mounting & Structure',
            'dc_electrical' => 'DC Electrical',
            'power_conversion', 'inverter' => 'Power Conversion',
            'ac_electrical' => 'AC Electrical',
            'battery', 'battery_ess' => 'Battery / ESS',
            'monitoring' => 'Monitoring',
            'grounding_safety' => 'Grounding & Safety',
            'civil_site' => 'Civil & Site Work',
            'engineering_tc' => 'Engineering, Testing & Commissioning',
            'legal_logistics_om' => 'Legal, Logistics & O&M',
            'commercial_cost' => 'Commercial Cost',
            'connector' => 'DC Electrical',
            'protection' => 'Protection',
            'accessory' => 'Accessory',
            default => self::humanize($category ?: 'Other'),
        };
    }

    public static function costGroupLabel(?string $group): string
    {
        return match ((string) $group) {
            'material' => 'Material',
            'electrical_bos' => 'Electrical BOS',
            'structural_bos' => 'Structural BOS',
            'civil' => 'Civil Work',
            'labor' => 'Labor',
            'engineering' => 'Engineering',
            'testing' => 'Testing & Commissioning',
            'legal_permit' => 'Legal & Permit',
            'logistics' => 'Logistics',
            'om' => 'O&M',
            'commercial' => 'Commercial Cost',
            default => self::humanize($group ?: 'General'),
        };
    }

    public static function scopeLabel(?string $status): string
    {
        return match ((string) $status) {
            'required' => 'Required',
            'conditional' => 'Conditional',
            'optional' => 'Optional',
            'utility' => 'Utility Responsibility',
            'service' => 'Service',
            'owner' => 'Owner Responsibility',
            'not_applicable' => 'Not Applicable',
            'catalog_candidate' => 'Catalog Candidate',
            default => 'Needs Review',
        };
    }

    public static function scopeTone(?string $status): string
    {
        return match ((string) $status) {
            'required' => 'required',
            'conditional' => 'conditional',
            'optional' => 'optional',
            'utility', 'owner' => 'excluded',
            'service' => 'service',
            'catalog_candidate' => 'catalog',
            default => 'review',
        };
    }

    public static function pricingStateLabel(?string $state): string
    {
        return match ((string) $state) {
            'priced' => 'Priced',
            'expired_price_warning' => 'Expired Price Warning',
            'supplier_unavailable_warning' => 'Supplier Unavailable',
            'margin_review_required' => 'Margin Review Required',
            'discount_review_required' => 'Discount Review Required',
            'tax_review_required' => 'Tax Review Required',
            'commercial_total_mismatch' => 'Commercial Total Mismatch',
            'manual_fallback' => 'Manual Fallback Price',
            'missing_price' => 'Missing Price',
            'excluded_scope' => 'Excluded from Total',
            'needs_pricing' => 'Price Required',
            'pending' => 'Pending Pricing',
            'summary_cost' => 'Included in Summary Cost',
            'catalog_candidate' => 'Requires Selection',
            'not_applicable' => 'Not Applicable',
            default => 'Pending Review',
        };
    }

    public static function scopeModeLabel(?string $mode): string
    {
        return match ((string) $mode) {
            'base_boq' => 'Base BOQ',
            'selected_addon' => 'Selected Add-on',
            'optional_addon' => 'Optional Add-on',
            'utility_responsibility' => 'Utility Responsibility',
            'owner_responsibility' => 'Owner Responsibility',
            'internal_catalog_candidate' => 'Catalog Candidate',
            'not_applicable' => 'Not Applicable',
            'summary_cost' => 'Included in Summary Cost',
            default => 'Needs Review',
        };
    }

    public static function specLabel(?string $key): string
    {
        return match ((string) $key) {
            'module_power_wp' => 'Module power',
            'module_area_m2' => 'Module area',
            'array_capacity_kwp' => 'Array capacity',
            'inverter_ac_power_kw' => 'Inverter power',
            'total_inverter_ac_kw' => 'Total inverter power',
            'dc_ac_ratio' => 'DC/AC ratio',
            'mppt_count' => 'MPPT count',
            'material' => 'Material',
            'cross_section_mm2' => 'Cable size',
            'string_count' => 'String count',
            'one_way_length_m' => 'One-way route length',
            'voltage_drop_percent' => 'Voltage drop',
            'rating_a' => 'Current rating',
            'type' => 'Type',
            'minimum_power_kw' => 'Minimum battery power',
            'backup_hours' => 'Backup duration',
            'dod_percent' => 'Depth of discharge',
            'efficiency_percent' => 'Efficiency',
            'scope' => 'Scope',
            'included' => 'Included',
            'basis' => 'Basis',
            'waste_factor_percent' => 'Material allowance',
            'module_count' => 'Module count',
            'row_count' => 'Estimated row count',
            'roof_type' => 'Roof type',
            'building_type' => 'Building type',
            'system_type' => 'System type',
            'phase_type' => 'Phase type',
            'backup_load' => 'Backup load',
            default => self::humanize($key ?: 'Detail'),
        };
    }

    public static function value(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)->flatten()->map(fn ($item) => (string) $item)->implode(', ');
        }

        if ($value === null || $value === '') {
            return 'Not recorded';
        }

        return (string) $value;
    }

    public static function humanize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Not recorded';
        }

        return str($value)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();
    }
}
