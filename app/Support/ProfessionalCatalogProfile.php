<?php

namespace App\Support;

use App\Models\Material;
use App\Models\MaterialCategory;

class ProfessionalCatalogProfile
{
    /**
     * Professional NEXORIN catalog taxonomy.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function profiles(): array
    {
        return [
            'PV_MODULE' => self::profile(
                'PV Module',
                'PV generation assets with electrical, dimensional, thermal, warranty, and certification evidence.',
                ['module_power_wp', 'voc', 'vmp', 'isc', 'imp', 'module_efficiency_percent', 'temp_coeff_voc', 'temp_coeff_pmax', 'length_mm', 'width_mm', 'weight_kg', 'cell_type', 'bifacial', 'max_system_voltage', 'product_warranty_years', 'performance_warranty_years'],
                ['IEC 61215', 'IEC 61730'],
                ['datasheet', 'warranty', 'installation_manual'],
                ['nominal_power_wp', 'voltage_v', 'current_a', 'ip_rating', 'warranty_years'],
                [
                    'PV_MONO_PERC' => 'Mono PERC Module',
                    'PV_TOPCON' => 'TOPCon Module',
                    'PV_HJT' => 'HJT Module',
                    'PV_BIFACIAL' => 'Bifacial Module',
                    'PV_HIGH_POWER' => 'High-power Commercial Module',
                ]
            ),
            'INVERTER_ON_GRID' => self::profile(
                'On-grid Inverter',
                'Grid-connected power conversion equipment with MPPT, anti-islanding, grid-code, and enclosure evidence.',
                ['inverter_type', 'rated_ac_power_kw', 'max_dc_power_kwp', 'max_dc_voltage', 'startup_voltage', 'mppt_count', 'strings_per_mppt', 'mppt_voltage_min', 'mppt_voltage_max', 'max_input_current_per_mppt', 'max_short_circuit_current_per_mppt', 'rated_ac_voltage', 'phase_type', 'efficiency_percent', 'anti_islanding', 'grid_code_profile', 'communication_protocol', 'ip_rating', 'warranty_years'],
                ['IEC 62109', 'GRID CODE'],
                ['datasheet', 'warranty', 'installation_manual', 'grid_code_certificate'],
                ['rated_power_kw', 'voltage_v', 'current_a', 'electrical_phase', 'ip_rating', 'warranty_years'],
                [
                    'INV_STRING_1P' => 'String Inverter · Single Phase',
                    'INV_STRING_3P' => 'String Inverter · Three Phase',
                    'INV_MICRO' => 'Microinverter',
                    'INV_CENTRAL' => 'Central Inverter',
                    'INV_OPTIMIZER' => 'Module Optimizer / MLPE',
                ]
            ),
            'INVERTER_HYBRID_OFFGRID' => self::profile(
                'Hybrid & Off-grid Inverter',
                'Hybrid, off-grid, charge-control, and PCS equipment with battery protocol and backup capability evidence.',
                ['inverter_type', 'rated_ac_power_kw', 'max_dc_power_kwp', 'max_dc_voltage', 'mppt_count', 'mppt_voltage_min', 'mppt_voltage_max', 'battery_voltage_range', 'max_charge_current_a', 'max_discharge_current_a', 'backup_output_kw', 'surge_power_kw', 'bms_protocol', 'genset_support', 'phase_type', 'efficiency_percent', 'communication_protocol', 'ip_rating', 'warranty_years'],
                ['IEC 62109'],
                ['datasheet', 'battery_compatibility_list', 'warranty', 'installation_manual'],
                ['rated_power_kw', 'voltage_v', 'current_a', 'electrical_phase', 'ip_rating', 'warranty_years'],
                [
                    'INV_HYBRID' => 'Hybrid Inverter',
                    'INV_OFFGRID' => 'Off-grid Inverter',
                    'INV_MPPT_CONTROLLER' => 'MPPT Charge Controller',
                    'INV_PCS' => 'Power Conversion System',
                ]
            ),
            'BATTERY_ESS' => self::profile(
                'Battery & ESS',
                'Battery modules, racks, cabinets, BMS, and ESS packages with safety, protocol, thermal, and warranty evidence.',
                ['battery_chemistry', 'nominal_voltage_v', 'capacity_ah', 'capacity_kwh', 'usable_capacity_kwh', 'dod_percent', 'round_trip_efficiency_percent', 'cycle_life', 'max_charge_current_a', 'max_discharge_current_a', 'c_rate', 'parallel_limit', 'communication_protocol', 'operating_temperature', 'fire_safety_required', 'warranty_years'],
                ['UN38.3', 'IEC 62619'],
                ['datasheet', 'safety_manual', 'bms_protocol', 'warranty', 'installation_manual'],
                ['nominal_capacity_kwh', 'voltage_v', 'current_a', 'ip_rating', 'warranty_years'],
                [
                    'BAT_WALL' => 'Wall-mounted Battery',
                    'BAT_RACK' => 'Rack Battery Module',
                    'BAT_CABINET' => 'Battery Cabinet',
                    'BAT_BMS' => 'Battery Management System',
                    'BAT_ESS_PACKAGE' => 'ESS Rack / Cabinet Package',
                ]
            ),
            'DC_ELECTRICAL' => self::profile(
                'DC Electrical',
                'PV cable, connectors, combiner, isolation, fuse, breaker, SPD, conduit, and termination equipment.',
                ['device_or_cable_type', 'conductor_size_mm2', 'rated_voltage', 'rated_current', 'insulation', 'uv_resistance', 'temperature_rating', 'breaking_capacity_ka', 'spd_type', 'uc_voltage', 'in_current', 'imax_current', 'pole_count', 'ip_rating', 'standard'],
                ['IEC'],
                ['datasheet', 'installation_manual'],
                ['voltage_v', 'current_a', 'ip_rating', 'warranty_years'],
                [
                    'DC_CABLE_CONNECTOR' => 'PV Cable & Connector',
                    'DC_COMBINER' => 'DC Combiner & Junction',
                    'DC_PROTECTION' => 'DC Fuse, Breaker & Isolator',
                    'DC_SPD' => 'DC Surge Protection',
                    'DC_CONTAINMENT' => 'Conduit, Tray & Termination',
                ]
            ),
            'AC_ELECTRICAL' => self::profile(
                'AC Electrical',
                'AC cable, switchgear, protection, transfer, metering, transformer, and grid integration equipment.',
                ['device_type', 'rated_voltage', 'rated_current', 'breaking_capacity_ka', 'poles', 'curve_type', 'short_circuit_rating_ka', 'busbar_material', 'ct_ratio', 'accuracy_class', 'communication_protocol', 'ip_rating', 'standard'],
                ['IEC'],
                ['datasheet', 'installation_manual'],
                ['rated_power_kw', 'voltage_v', 'current_a', 'electrical_phase', 'ip_rating', 'warranty_years'],
                [
                    'AC_CABLE' => 'AC Power Cable',
                    'AC_PROTECTION' => 'MCB, MCCB, ACB & Protection',
                    'AC_TRANSFER_SYNC' => 'ATS, STS, AMF & Synchronization',
                    'AC_METERING_CONTROL' => 'CT, Metering & Export Control',
                    'AC_TRANSFORMER_MV' => 'Transformer & MV Integration',
                ]
            ),
            'MOUNTING_STRUCTURE' => self::profile(
                'Mounting & Structure',
                'Rooftop, ballast, carport, and ground-mount components with structural, corrosion, and installation evidence.',
                ['mounting_type', 'roof_compatibility', 'tilt_range', 'orientation', 'material_grade', 'corrosion_class', 'wind_speed_rating', 'rail_length', 'foundation_type', 'roof_penetration', 'unit_weight_kg'],
                [],
                ['datasheet', 'structural_design_guide', 'installation_manual', 'warranty', 'corrosion_document'],
                ['ip_rating', 'warranty_years'],
                [
                    'MOUNT_ROOF' => 'Rooftop Rail & Clamp',
                    'MOUNT_BALLAST' => 'Ballasted Mounting',
                    'MOUNT_GROUND' => 'Ground-mounted Structure',
                    'MOUNT_CARPORT' => 'Solar Carport Structure',
                    'MOUNT_ACCESS' => 'Walkway, Lifeline & Access',
                ]
            ),
            'MONITORING_METERING' => self::profile(
                'Monitoring, Metering & Communication',
                'Data logger, smart meter, gateway, networking, weather, and sensor equipment for operational visibility.',
                ['device_type', 'protocol', 'communication_port', 'modbus_support', 'can_support', 'mqtt_support', 'logging_interval', 'measured_parameter', 'accuracy_class', 'ct_ratio', 'cloud_platform', 'subscription_years', 'ip_rating'],
                [],
                ['datasheet', 'communication_manual', 'modbus_map', 'installation_manual'],
                ['voltage_v', 'current_a', 'ip_rating', 'warranty_years'],
                [
                    'MON_DATA_LOGGER' => 'Data Logger & Gateway',
                    'MON_METER' => 'Smart Meter & Energy Meter',
                    'MON_NETWORK' => 'Router, Switch & Communication',
                    'MON_WEATHER' => 'Weather Station & Pyranometer',
                    'MON_SENSOR' => 'Temperature & Environmental Sensor',
                ]
            ),
            'GROUNDING_LIGHTNING_SAFETY' => self::profile(
                'Grounding, Lightning & Safety',
                'Grounding, bonding, lightning protection, identification, and lockout safety components.',
                ['device_or_material_type', 'material', 'conductor_size_mm2', 'length_m', 'corrosion_resistance', 'rated_voltage', 'application_area', 'safety_scope', 'standard'],
                [],
                ['datasheet', 'installation_manual'],
                ['voltage_v', 'current_a', 'ip_rating', 'warranty_years'],
                [
                    'GROUNDING_SYSTEM' => 'Grounding Electrode & Conductor',
                    'LIGHTNING_PROTECTION' => 'Lightning Protection System',
                    'BONDING' => 'Equipotential Bonding',
                    'SAFETY_LABEL_LOTO' => 'Warning Label & LOTO',
                ]
            ),
            'PANEL_DISTRIBUTION' => self::profile(
                'Panel & Distribution',
                'DCDB, ACDB, LVMDP, backup, synchronization, enclosure, busbar, and distribution assemblies.',
                ['panel_type', 'rated_current', 'rated_voltage', 'short_circuit_rating_ka', 'busbar_material', 'form_separation', 'incoming_breaker', 'outgoing_breaker_count', 'metering', 'installation_location', 'ip_rating'],
                ['IEC'],
                ['panel_specification', 'component_datasheet', 'wiring_diagram', 'type_test'],
                ['rated_power_kw', 'voltage_v', 'current_a', 'electrical_phase', 'ip_rating', 'warranty_years'],
                [
                    'PANEL_DCDB' => 'DC Distribution Board',
                    'PANEL_ACDB' => 'AC Distribution Board',
                    'PANEL_BACKUP' => 'Critical Load / Backup Panel',
                    'PANEL_LVMDP' => 'LVMDP Integration',
                    'PANEL_SYNC' => 'Synchronization Panel',
                ]
            ),
            'CIVIL_SITE_WORK' => self::profile(
                'Civil & Site Work',
                'Civil products, site works, survey tools, method statements, and deliverable-based service records.',
                ['work_type', 'volume', 'unit', 'manpower', 'working_days', 'equipment_required', 'deliverable', 'responsible_party', 'processing_days'],
                [],
                ['product_datasheet', 'method_statement', 'work_specification', 'assessment_checklist'],
                ['warranty_years'],
                [
                    'CIVIL_ANCHOR_SEALANT' => 'Anchor, Sealant & Waterproofing',
                    'CIVIL_FOUNDATION' => 'Foundation & Structural Civil Work',
                    'CIVIL_TRENCH_DUCT' => 'Trenching & Duct Bank',
                    'CIVIL_SURVEY' => 'Survey & Site Assessment',
                    'CIVIL_BUILDING_WORK' => 'Equipment Shelter & Site Facilities',
                ]
            ),
            'FIRE_HSE' => self::profile(
                'Fire & HSE',
                'Fire detection, suppression, PPE, signage, emergency planning, and HSE services.',
                ['device_or_service_type', 'extinguishing_agent', 'capacity', 'detection_type', 'coverage_area', 'maintenance_interval', 'safety_scope', 'standard'],
                [],
                ['datasheet', 'safety_manual', 'maintenance_instruction'],
                ['voltage_v', 'current_a', 'ip_rating', 'warranty_years'],
                [
                    'FIRE_EXTINGUISHER' => 'Portable Fire Extinguisher',
                    'FIRE_DETECTION' => 'Smoke, Heat & Gas Detection',
                    'FIRE_SUPPRESSION' => 'Fixed Fire Suppression',
                    'HSE_PPE' => 'PPE & Site Safety Equipment',
                    'HSE_SERVICE' => 'HSE Procedure & Service',
                ]
            ),
            'LEGAL_CERTIFICATION' => self::profile(
                'Legal, Certification & Compliance',
                'Inspection, certification, utility, permit, registration, and compliance service records.',
                ['service_type', 'authority_or_issuer', 'applicable_standard', 'deliverable', 'processing_days', 'validity_period', 'responsible_party'],
                [],
                ['service_scope', 'compliance_reference', 'deliverable_template'],
                ['warranty_years'],
                [
                    'LEGAL_SLO_INSPECTION' => 'SLO & Technical Inspection',
                    'LEGAL_UTILITY' => 'Utility Application & Interconnection',
                    'LEGAL_CERTIFICATE' => 'Product / System Certification',
                    'LEGAL_PERMIT' => 'Permit & Registration',
                ]
            ),
            'LOGISTICS_OM' => self::profile(
                'Logistics & O&M',
                'Freight, storage, commissioning equipment, maintenance, spare parts, and warranty support services.',
                ['service_or_equipment_type', 'service_scope', 'delivery_terms', 'lead_time_days', 'coverage_area', 'maintenance_interval', 'deliverable', 'warranty_claim_process'],
                [],
                ['service_scope', 'pricelist', 'delivery_terms', 'om_checklist', 'warranty_claim_procedure'],
                ['warranty_years'],
                [
                    'LOG_FREIGHT' => 'Freight & Delivery',
                    'LOG_STORAGE' => 'Warehousing & Site Storage',
                    'OM_TEST_EQUIPMENT' => 'O&M Test Equipment',
                    'OM_SERVICE' => 'Annual O&M Service',
                    'OM_SPARE_WARRANTY' => 'Spare Parts & Warranty Support',
                ]
            ),
            'SUPPLIER_PRICE_QUOTE' => self::profile(
                'Supplier Quote & Pricelist',
                'Supplier commercial references for price, stock, MOQ, lead time, warranty, and purchase conditions.',
                ['supplier_name', 'supplier_sku', 'currency', 'unit_cost', 'minimum_order_quantity', 'availability_status', 'lead_time_days', 'valid_until', 'payment_terms', 'delivery_condition'],
                [],
                ['supplier_quote', 'pricelist', 'purchase_condition'],
                ['warranty_years'],
                [
                    'QUOTE_MATERIAL' => 'Material Supplier Quote',
                    'QUOTE_SERVICE' => 'Service Provider Quote',
                    'QUOTE_PRICELIST' => 'Supplier Pricelist',
                ]
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function profile(string $name, string $description, array $specs, array $certificates, array $documents, array $coreFields, array $subcategories): array
    {
        return compact('name', 'description', 'specs', 'certificates', 'documents', 'coreFields', 'subcategories');
    }

    public static function legacyAliases(): array
    {
        return [
            'POWER_CONVERSION' => 'INVERTER_ON_GRID',
            'MONITORING' => 'MONITORING_METERING',
            'GROUNDING_SAFETY' => 'GROUNDING_LIGHTNING_SAFETY',
            'ENGINEERING_SERVICE' => 'CIVIL_SITE_WORK',
        ];
    }

    public static function rootCodeFor(Material|MaterialCategory|string|null $subject): string
    {
        if ($subject instanceof Material) {
            $subject->loadMissing('category.parent');
            $subject = $subject->category;
        }

        if ($subject instanceof MaterialCategory) {
            $category = $subject;
            $guard = 0;
            while ($category->parent_id && $guard < 8) {
                $category->loadMissing('parent');
                if (! $category->parent) {
                    break;
                }
                $category = $category->parent;
                $guard++;
            }
            $code = strtoupper((string) $category->code);
        } else {
            $code = strtoupper((string) $subject);
        }

        $code = self::legacyAliases()[$code] ?? $code;

        static $subcategoryRoots = null;
        if ($subcategoryRoots === null) {
            $subcategoryRoots = [];
            foreach (self::profiles() as $rootCode => $profile) {
                $subcategoryRoots[$rootCode] = $rootCode;
                foreach (array_keys($profile['subcategories'] ?? []) as $subcategoryCode) {
                    $subcategoryRoots[strtoupper((string) $subcategoryCode)] = $rootCode;
                }
            }
        }

        return $subcategoryRoots[$code] ?? $code;
    }

    /** @return array<string, mixed> */
    public static function for(Material|MaterialCategory|string|null $subject): array
    {
        $code = self::rootCodeFor($subject);
        return self::profiles()[$code] ?? [
            'name' => 'General Material',
            'description' => 'General catalog item with manually governed technical and document evidence.',
            'specs' => [],
            'certificates' => [],
            'documents' => ['datasheet'],
            'coreFields' => ['nominal_power_wp', 'rated_power_kw', 'nominal_capacity_kwh', 'voltage_v', 'current_a', 'electrical_phase', 'ip_rating', 'warranty_years'],
            'subcategories' => [],
        ];
    }

    public static function documentLabels(): array
    {
        return [
            'datasheet' => 'Technical Datasheet',
            'certificate' => 'Product Certificate',
            'warranty' => 'Warranty Statement',
            'installation_manual' => 'Installation Manual',
            'safety_manual' => 'Safety Manual',
            'bms_protocol' => 'BMS Protocol',
            'battery_compatibility_list' => 'Battery Compatibility List',
            'grid_code_certificate' => 'Grid-code Certificate',
            'communication_manual' => 'Communication Manual',
            'modbus_map' => 'Modbus Register Map',
            'structural_design_guide' => 'Structural Design Guide',
            'corrosion_document' => 'Corrosion / Coating Document',
            'panel_specification' => 'Panel Specification',
            'component_datasheet' => 'Component Datasheet Pack',
            'wiring_diagram' => 'Wiring Diagram',
            'type_test' => 'Type-test Document',
            'product_datasheet' => 'Product Datasheet',
            'method_statement' => 'Method Statement',
            'work_specification' => 'Work Specification',
            'assessment_checklist' => 'Assessment Checklist',
            'maintenance_instruction' => 'Maintenance Instruction',
            'service_scope' => 'Service Scope',
            'compliance_reference' => 'Compliance Reference',
            'deliverable_template' => 'Deliverable Template',
            'pricelist' => 'Pricelist',
            'delivery_terms' => 'Delivery Terms',
            'om_checklist' => 'O&M Checklist',
            'warranty_claim_procedure' => 'Warranty Claim Procedure',
            'supplier_quote' => 'Supplier Quotation',
            'purchase_condition' => 'Purchase Condition',
            'technical_drawing' => 'Technical Drawing',
            'other' => 'Other Supporting Document',
        ];
    }

    public static function supportingDocumentLabels(): array
    {
        return array_diff_key(self::documentLabels(), array_flip(['datasheet', 'certificate']));
    }

    public static function profilePayload(): array
    {
        return collect(self::profiles())->map(function (array $profile, string $code): array {
            return [
                'code' => $code,
                'title' => $profile['name'],
                'copy' => $profile['description'],
                'fields' => $profile['coreFields'],
                'required_specs' => $profile['specs'],
                'required_certificates' => $profile['certificates'],
                'required_documents' => $profile['documents'],
            ];
        })->all();
    }
}
