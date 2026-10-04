<?php

namespace App\Support;

use App\Models\Material;

class MaterialCatalogStandards
{
    public const PARSER_VERSION = '64C-exact-category-ux-v1';

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
        return ProfessionalSpecificationSchema::requiredKeysFor($material);
    }

    /**
     * Return certificate types expected before a material can be approved.
     *
     * @return array<int, string>
     */
    public static function requiredCertificateTypesFor(Material $material): array
    {
        return array_values(ProfessionalCatalogProfile::for($material)['certificates'] ?? []);
    }

    /** @return array<int, string> */
    public static function requiredDocumentTypesFor(Material $material): array
    {
        return array_values(ProfessionalCatalogProfile::for($material)['documents'] ?? []);
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
            'max_dc_power_kwp' => 'Maximum DC Power',
            'strings_per_mppt' => 'Strings per MPPT',
            'rated_ac_current_a' => 'Rated AC Current',
            'max_ac_apparent_power_kva' => 'Maximum AC Apparent Power',
            'dc_input_count' => 'DC Input Count',
            'bms_protocol' => 'BMS Protocol',
            'max_pv_power_kwp' => 'Maximum PV Power',
            'can_profile' => 'CAN Profile',
            'rs485_protocol' => 'RS485 Protocol',
            'dc_isolator_rating_a' => 'DC Isolator Rating',
            'ct_ratio' => 'CT Ratio',
            'ppe_category' => 'PPE Category',
            'supplier_sku' => 'Supplier SKU',
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
            'rated_kw' => 'Rated Active Power',
            'rated_kva' => 'Rated Apparent Power',
            'rated_va_w' => 'Rated Apparent / Active Power',
            'declared_certifications' => 'Declared Certifications',
            'declared_certification' => 'Declared Certification',
            'declared_fire_transport_certificates' => 'Declared Fire / Transport Certificates',
            'icu_ka' => 'Ultimate Breaking Capacity',
            'ics_ka_or_percent' => 'Service Breaking Capacity',
            'icn_ka' => 'Rated Short-circuit Capacity',
            'icw_ka_s' => 'Short-time Withstand',
            'ipk_ka' => 'Peak Withstand',
            'i_delta_n_ma' => 'Rated Residual Operating Current',
            'temperature_measurement' => 'Temperature Measurement',
            'icw_ipk' => 'Short-time / Peak Withstand',
            'joint_resistance' => 'Joint Resistance',
            'length_modules' => 'Length / Module System',
            'tapoff' => 'Tap-off Interface',

            // Professional engineering presentation: preserve standard acronyms and avoid machine-like title casing.
            'cpu_type' => 'Controller CPU',
            'fiber_sfp_support' => 'Fiber / SFP Support',
            'vlan_support' => 'VLAN Support',
            'stp_rstp_support' => 'STP / RSTP Support',
            'wan_support' => 'WAN Support',
            'dual_sim_support' => 'Dual-SIM Support',
            'emc_support' => 'EMC Support',
            'cable_od_range_mm' => 'Cable Outside Diameter Range',
            'ansi_functions' => 'Protection Functions (ANSI)',
            'ith_ka_s' => 'Short-time Thermal Current (Ith)',
            'idyn_ka' => 'Dynamic Peak Current (Idyn)',
            'dlms_optional' => 'DLMS Support',
            'trip_unit_lsig' => 'LSIG Trip Unit',
            'lsi_g_settings' => 'LSI / Ground-fault Settings',
            'dcir_mohm' => 'DC Internal Resistance (DCIR)',
            'cop' => 'Coefficient of Performance (COP)',
            'ucpv_voltage_v' => 'Maximum Continuous PV Voltage (Ucpv)',
            'iimp_current_ka_optional' => 'Impulse Current (Iimp)',
            'imax_or_iimp' => 'Maximum / Impulse Discharge Current (Imax / Iimp)',
            'imax_current_ka' => 'Maximum Discharge Current (Imax)',
            'in_current_ka' => 'Nominal Discharge Current (In)',
            'ue_v' => 'Rated Operational Voltage (Ue)',
            'ue' => 'Rated Operational Voltage (Ue)',
            'pf_q_capability' => 'Power Factor / Reactive Power Capability',
            'contact_configuration' => 'Contact Arrangement',
            'program_revision' => 'Control Program Revision',
            'poe_support' => 'PoE Support',
            'sccr' => 'Short-circuit Current Rating (SCCR)',
            'tov' => 'Temporary Overvoltage (TOV)',
            'material' => 'Construction Material',
            'length_m' => 'Overall Length',
            'mounting' => 'Mounting Method',
            'skid_container_data' => 'Skid / Container Configuration',
            'foundation_reactions' => 'Foundation Reaction Loads',
            'aerodynamic_coefficient_data' => 'Aerodynamic Coefficient Reference',
            'alternator_fault_data' => 'Alternator Fault-current Data',
            'range' => 'Measurement Range',
            'signal_output' => 'Signal Output',
            'input' => 'Input Supply',
            'output' => 'Output Supply',
            'ct_vt_compartments' => 'CT / VT Compartments',
            'enclosure_ip_ik' => 'Enclosure Protection (IP / IK)',
            'ip_ik' => 'Protection Ratings (IP / IK)',
            'hv_voltage_kv' => 'HV Voltage',
            'lv_voltage_kv' => 'LV Voltage',
            'mv_interface' => 'MV Interface',
            'icn' => 'Rated Short-circuit Capacity (Icn)',
            'icu_ics_icw' => 'Breaking / Withstand Ratings (Icu / Ics / Icw)',
            'icu_or_icn_ka' => 'Breaking Capacity (Icu / Icn)',
            'direct_or_ct' => 'Meter Connection (Direct / CT)',
            'max_isc_a' => 'Maximum Short-circuit Current (Isc)',
            'max_isc_per_mppt_a' => 'Maximum Short-circuit Current per MPPT (Isc)',
            'soh_algorithm' => 'State-of-Health (SOH) Algorithm',
            'temp_coeff_isc' => 'Isc Temperature Coefficient',
            'temp_coeff_voc' => 'Voc Temperature Coefficient',
            'type_ac_a_f_b' => 'RCD Type (AC / A / F / B)',
            'protocol_api' => 'Protocol / API Interface',
            'recommended_max_pv_power_kwp' => 'Recommended Maximum PV Power',
            'alloy_or_grade' => 'Alloy / Grade',
            'branch_max_units' => 'Maximum Units per Branch',
            'curve_b_c_d_or_other' => 'Trip Curve (B / C / D / Other)',
            'mass_per_m_kg_m' => 'Mass per Metre',
            'max_input_current_per_mppt_a' => 'Maximum Input Current per MPPT',
            'max_units_per_string' => 'Maximum Units per String',
            'peak_current_a_and_duration' => 'Peak Current and Duration',
            'remote_trip_optional' => 'Remote Trip Provision',
            'safety_rating_optional' => 'Safety Rating / Classification',
            'transparent_backsheet_or_glass' => 'Rear Construction (Transparent Backsheet / Glass)',
            'import_export' => 'Import / Export Measurement',
        ];
    }

    public static function labelFor(string $key): string
    {
        if (isset(self::labels()[$key])) {
            return self::labels()[$key];
        }

        $displayKey = preg_replace(
            '/_(kg_m2|kg_m|mm2|kwp|kva|kwh|wh|mwh|wp|kw|ah|kv|ka_s|ka|kn|hz|m2|m3_h|m3|kg|kpa|pa|bar|mm|ms|ns|mbps|nm|db|mohm|ohm_km|ohm|uf_km|percent|days|hours|months|years|minutes|seconds|c|s|m|v|a|w)$/',
            '',
            $key
        ) ?: $key;

        $label = str($displayKey)->replace('_', ' ')->title()->toString();
        $acronyms = [
            'Ac' => 'AC', 'Dc' => 'DC', 'Pv' => 'PV', 'Mppt' => 'MPPT', 'Bms' => 'BMS',
            'Can' => 'CAN', 'Rs485' => 'RS485', 'Mqtt' => 'MQTT', 'Ip' => 'IP', 'Iec' => 'IEC',
            'Ct' => 'CT', 'Ppe' => 'PPE', 'Hse' => 'HSE', 'Slo' => 'SLO', 'Sku' => 'SKU',
            'Moq' => 'MOQ', 'Mlpe' => 'MLPE', 'Lvmdp' => 'LVMDP', 'Acdb' => 'ACDB',
            'Dcdb' => 'DCDB', 'Ats' => 'ATS', 'Sts' => 'STS', 'Amf' => 'AMF',
            'Lps' => 'LPS', 'Vpn' => 'VPN', 'Wifi' => 'Wi-Fi',
            'Pf' => 'PF', 'Thd' => 'THD', 'Afci' => 'AFCI', 'Spd' => 'SPD',
            'Soc' => 'SOC', 'Dod' => 'DoD', 'Ems' => 'EMS', 'Pcs' => 'PCS',
            'Hvac' => 'HVAC', 'Rte' => 'RTE', 'Mcb' => 'MCB', 'Mccb' => 'MCCB',
            'Acb' => 'ACB', 'Vcb' => 'VCB', 'Rmu' => 'RMU', 'Vt' => 'VT',
            'Pqm' => 'PQM', 'Ups' => 'UPS', 'Api' => 'API', 'Nmot' => 'NMOT',
            'Noct' => 'NOCT', 'Isc' => 'Isc', 'Imp' => 'Imp', 'Voc' => 'Voc',
            'Vmp' => 'Vmp', 'Pmax' => 'Pmax', 'Icu' => 'Icu', 'Ics' => 'Ics',
            'Icn' => 'Icn', 'Icw' => 'Icw', 'Ipk' => 'Ipk',
        ];

        return collect(explode(' ', $label))
            ->map(fn (string $word): string => $acronyms[$word] ?? $word)
            ->implode(' ');
    }

    public static function categoryCode(Material $material): string
    {
        $code = ProfessionalCatalogProfile::rootCodeFor($material);

        if ($code !== '') {
            return $code;
        }

        $type = strtoupper((string) $material->material_type);

        return match (true) {
            str_contains($type, 'PV') || str_contains($type, 'MODULE') => 'PV_MODULE',
            str_contains($type, 'HYBRID') || str_contains($type, 'OFF-GRID') || str_contains($type, 'OFFGRID') => 'INVERTER_HYBRID_OFFGRID',
            str_contains($type, 'INVERTER') || str_contains($type, 'PCS') => 'INVERTER_ON_GRID',
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
            str_ends_with($key, '_ohm_km') => 'Ω/km',
            str_ends_with($key, '_mohm') => 'mΩ',
            str_ends_with($key, '_uf_km') => 'µF/km',
            str_ends_with($key, '_m3_h') => 'm³/h',
            str_ends_with($key, '_ka_s') => 'kA·s',
            str_ends_with($key, '_wh') => 'Wh',
            str_ends_with($key, '_mwh') => 'MWh',
            str_ends_with($key, '_c') => '°C',
            str_ends_with($key, '_kg_m2') => 'kg/m²',
            str_ends_with($key, '_kg_m') => 'kg/m',
            str_ends_with($key, '_mm2') => 'mm²',
            str_ends_with($key, '_kwp') => 'kWp',
            str_ends_with($key, '_kva') => 'kVA',
            str_ends_with($key, '_kwh') => 'kWh',
            str_ends_with($key, '_wp') => 'Wp',
            str_ends_with($key, '_kw') => 'kW',
            str_ends_with($key, '_ah') => 'Ah',
            str_ends_with($key, '_kv') => 'kV',
            str_ends_with($key, '_ka') => 'kA',
            str_ends_with($key, '_kn') => 'kN',
            str_ends_with($key, '_hz') => 'Hz',
            str_ends_with($key, '_m2') => 'm²',
            str_ends_with($key, '_m3') => 'm³',
            str_ends_with($key, '_kg') => 'kg',
            str_ends_with($key, '_kpa') => 'kPa',
            str_ends_with($key, '_pa') => 'Pa',
            str_ends_with($key, '_bar') => 'bar',
            str_ends_with($key, '_mm') => 'mm',
            str_ends_with($key, '_ms') => 'ms',
            str_ends_with($key, '_ns') => 'ns',
            str_ends_with($key, '_mbps') => 'Mbps',
            str_ends_with($key, '_nm') => 'nm',
            str_ends_with($key, '_db') => 'dB',
            str_ends_with($key, '_ohm') => 'Ω',
            str_ends_with($key, '_percent') => '%',
            str_ends_with($key, '_days') => 'days',
            str_ends_with($key, '_hours') => 'hours',
            str_ends_with($key, '_months') => 'months',
            str_ends_with($key, '_years') => 'years',
            str_ends_with($key, '_minutes') => 'minutes',
            str_ends_with($key, '_seconds') => 'seconds',
            str_ends_with($key, '_s') => 's',
            str_ends_with($key, '_m') => 'm',
            str_ends_with($key, '_v') => 'V',
            str_ends_with($key, '_a') => 'A',
            str_ends_with($key, '_w') => 'W',
            str_contains($key, 'voltage') || in_array($key, ['voc', 'vmp'], true) => 'V',
            str_contains($key, 'current') || in_array($key, ['isc', 'imp'], true) => 'A',
            str_contains($key, 'warranty') => 'years',
            $key === 'cycle_life' => 'cycles',
            $key === 'c_rate' => 'C',
            default => null,
        };
    }

}
