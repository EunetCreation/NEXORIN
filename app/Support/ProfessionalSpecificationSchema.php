<?php

namespace App\Support;

use App\Models\Material;
use App\Models\MaterialCategory;

class ProfessionalSpecificationSchema
{
    /**
     * Return a category-specific technical specification schema.
     *
     * @return array<string, mixed>
     */
    public static function for(Material|MaterialCategory|string|null $subject): array
    {
        $categoryCode = self::categoryCode($subject);
        $rootCode = ProfessionalCatalogProfile::rootCodeFor($subject);
        $profile = ProfessionalCatalogProfile::for($subject);
        $specific = self::subcategorySchemas()[$categoryCode] ?? null;

        $required = array_values(array_unique($specific['required'] ?? ($profile['specs'] ?? [])));
        $conditional = array_values(array_diff(
            array_unique($specific['conditional'] ?? []),
            $required
        ));
        $optional = array_values(array_diff(
            array_unique(array_merge($specific['optional'] ?? [], $specific['recommended'] ?? [], $conditional)),
            $required
        ));

        $fields = collect(array_merge($required, $optional))
            ->map(function (string $key) use ($required, $conditional, $categoryCode): array {
                $categoryGroup = self::categoryFieldGroupOverrides()[$categoryCode][$key] ?? null;
                $definition = self::definition($key, $categoryGroup);
                $categoryDefinition = self::categoryFieldDefinitionOverrides()[$categoryCode][$key] ?? [];
                if ($categoryDefinition !== []) {
                    $definition = array_merge($definition, $categoryDefinition);
                }
                $definition['required'] = in_array($key, $required, true);
                $definition['conditional'] = in_array($key, $conditional, true);

                return $definition;
            })
            ->groupBy('group')
            ->map(function ($groupFields, string $group): array {
                return [
                    'key' => $group,
                    'label' => self::groupLabels()[$group] ?? str($group)->replace('_', ' ')->title()->toString(),
                    'description' => self::groupDescriptions()[$group] ?? 'Category-specific technical data for governed catalog use.',
                    'fields' => $groupFields->values()->all(),
                ];
            })
            ->sortBy(function (array $group): int {
                $index = array_search($group['key'], self::groupOrder(), true);

                return $index === false ? 999 : (int) $index;
            })
            ->values()
            ->all();

        return [
            'code' => $categoryCode !== '' ? $categoryCode : ($rootCode !== '' ? $rootCode : 'DEFAULT'),
            'root_code' => $rootCode !== '' ? $rootCode : 'DEFAULT',
            'title' => $specific['title'] ?? ($profile['name'] ?? 'General Material'),
            'description' => $specific['description'] ?? ($profile['description'] ?? 'Governed technical profile for a professional material record.'),
            'required_specs' => $required,
            'optional_specs' => $optional,
            'conditional_specs' => $conditional,
            'required_certificates' => array_values($profile['certificates'] ?? []),
            'required_documents' => array_values($profile['documents'] ?? []),
            'groups' => $fields,
            'field_count' => count($required) + count($optional),
            'required_count' => count($required),
            'optional_count' => count(array_diff($optional, $conditional)),
            'conditional_count' => count($conditional),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function payload(): array
    {
        $codes = collect(ProfessionalCatalogProfile::profiles())
            ->flatMap(function (array $profile, string $root): array {
                return [$root => $root] + collect($profile['subcategories'] ?? [])->mapWithKeys(fn ($name, $code): array => [$code => $code])->all();
            })
            ->values()
            ->unique()
            ->all();

        $payload = [];
        foreach ($codes as $code) {
            $payload[$code] = self::for($code);
        }

        $payload['DEFAULT'] = self::defaultSchema();

        return $payload;
    }

    /** @return array<int, string> */
    public static function requiredKeysFor(Material|MaterialCategory|string|null $subject): array
    {
        return self::for($subject)['required_specs'];
    }

    /** @return array<int, string> */
    public static function optionalKeysFor(Material|MaterialCategory|string|null $subject): array
    {
        return self::for($subject)['optional_specs'];
    }

    /**
     * Specifications retired from an exact component schema.
     *
     * These keys are removed only when the material is saved through the governed
     * editor. Custom/parser-only specifications that are not listed here remain
     * untouched by the synchronizer.
     *
     * @return array<int, string>
     */
    public static function deprecatedKeysFor(Material|MaterialCategory|string|null $subject): array
    {
        return match (self::categoryCode($subject)) {
            'PV_MONO_PERC', 'PV_TOPCON', 'PV_HJT' => ['module_technology'],
            'PV_BIFACIAL' => ['bifacial'],
            'DC_SPD' => ['device_type'],
            default => [],
        };
    }

    /** @return array<int, string> */
    public static function managedKeys(): array
    {
        return collect(self::payload())
            ->flatMap(fn (array $schema): array => array_merge($schema['required_specs'] ?? [], $schema['optional_specs'] ?? []))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Map category-specific normalized specs back to legacy material summary columns.
     *
     * @param array<string, mixed> $specs
     * @return array<string, mixed>
     */
    public static function coreColumnValues(array $specs, Material|MaterialCategory|string|null $subject): array
    {
        $rootCode = ProfessionalCatalogProfile::rootCodeFor($subject);
        $value = fn (string $key) => isset($specs[$key]) && $specs[$key] !== '' ? $specs[$key] : null;

        return match ($rootCode) {
            'PV_MODULE' => [
                'nominal_power_wp' => $value('module_power_wp'),
                'voltage_v' => $value('vmp'),
                'current_a' => $value('imp'),
                'warranty_years' => $value('product_warranty_years'),
            ],
            'INVERTER_ON_GRID' => [
                'rated_power_kw' => $value('rated_ac_power_kw'),
                'voltage_v' => $value('rated_ac_voltage'),
                'current_a' => $value('rated_ac_current_a'),
                'electrical_phase' => $value('phase_type'),
                'ip_rating' => $value('ip_rating'),
                'warranty_years' => $value('warranty_years'),
            ],
            'INVERTER_HYBRID_OFFGRID' => [
                'rated_power_kw' => $value('rated_ac_power_kw'),
                'voltage_v' => $value('rated_ac_voltage') ?? $value('nominal_battery_voltage_v'),
                'current_a' => $value('max_discharge_current_a'),
                'electrical_phase' => $value('phase_type'),
                'ip_rating' => $value('ip_rating'),
                'warranty_years' => $value('warranty_years'),
            ],
            'BATTERY_ESS' => [
                'nominal_capacity_kwh' => $value('capacity_kwh'),
                'voltage_v' => $value('nominal_voltage_v'),
                'current_a' => $value('max_discharge_current_a'),
                'ip_rating' => $value('ip_rating'),
                'warranty_years' => $value('warranty_years'),
            ],
            'DC_ELECTRICAL', 'AC_ELECTRICAL', 'PANEL_DISTRIBUTION', 'GROUNDING_LIGHTNING_SAFETY' => [
                'voltage_v' => $value('rated_voltage'),
                'current_a' => $value('rated_current'),
                'electrical_phase' => $value('phase_type'),
                'ip_rating' => $value('ip_rating'),
                'warranty_years' => $value('warranty_years'),
            ],
            'MONITORING_METERING' => [
                'voltage_v' => $value('rated_voltage'),
                'current_a' => $value('rated_current'),
                'ip_rating' => $value('ip_rating'),
                'warranty_years' => $value('warranty_years'),
            ],
            default => [
                'nominal_power_wp' => $value('module_power_wp'),
                'nominal_capacity_kwh' => $value('capacity_kwh'),
                'rated_power_kw' => $value('rated_ac_power_kw') ?? $value('rated_power_kw'),
                'voltage_v' => $value('rated_voltage') ?? $value('nominal_voltage_v'),
                'current_a' => $value('rated_current'),
                'electrical_phase' => $value('phase_type'),
                'ip_rating' => $value('ip_rating'),
                'warranty_years' => $value('warranty_years') ?? $value('product_warranty_years'),
            ],
        };
    }

    /** @return array<string, mixed> */
    public static function legacySpecValues(Material $material): array
    {
        $rootCode = ProfessionalCatalogProfile::rootCodeFor($material);
        $values = [];
        $put = function (string $key, mixed $value) use (&$values): void {
            if ($value !== null && $value !== '') {
                $values[$key] = (string) $value;
            }
        };

        match ($rootCode) {
            'PV_MODULE' => (function () use ($material, $put): void {
                $put('module_power_wp', $material->nominal_power_wp);
                $put('vmp', $material->voltage_v);
                $put('imp', $material->current_a);
                $put('product_warranty_years', $material->warranty_years);
            })(),
            'INVERTER_ON_GRID' => (function () use ($material, $put): void {
                $put('rated_ac_power_kw', $material->rated_power_kw);
                $put('rated_ac_voltage', $material->voltage_v);
                $put('rated_ac_current_a', $material->current_a);
                $put('phase_type', $material->electrical_phase);
                $put('ip_rating', $material->ip_rating);
                $put('warranty_years', $material->warranty_years);
            })(),
            'INVERTER_HYBRID_OFFGRID' => (function () use ($material, $put): void {
                $put('rated_ac_power_kw', $material->rated_power_kw);
                $put('rated_ac_voltage', $material->voltage_v);
                $put('max_discharge_current_a', $material->current_a);
                $put('phase_type', $material->electrical_phase);
                $put('ip_rating', $material->ip_rating);
                $put('warranty_years', $material->warranty_years);
            })(),
            'BATTERY_ESS' => (function () use ($material, $put): void {
                $put('capacity_kwh', $material->nominal_capacity_kwh);
                $put('nominal_voltage_v', $material->voltage_v);
                $put('max_discharge_current_a', $material->current_a);
                $put('ip_rating', $material->ip_rating);
                $put('warranty_years', $material->warranty_years);
            })(),
            default => (function () use ($material, $put): void {
                $put('rated_power_kw', $material->rated_power_kw);
                $put('rated_voltage', $material->voltage_v);
                $put('rated_current', $material->current_a);
                $put('phase_type', $material->electrical_phase);
                $put('ip_rating', $material->ip_rating);
                $put('warranty_years', $material->warranty_years);
            })(),
        };

        return $values;
    }

    /** @return array<string, mixed> */
    public static function definition(string $key, ?string $categoryGroupOverride = null): array
    {
        $overrides = self::fieldOverrides()[$key] ?? [];
        $label = $overrides['label'] ?? MaterialCatalogStandards::labelFor($key);
        $inputType = $overrides['input_type'] ?? self::inferInputType($key);
        $unit = array_key_exists('unit', $overrides) ? $overrides['unit'] : MaterialCatalogStandards::unitFor($key);
        $group = $categoryGroupOverride ?? $overrides['group'] ?? self::fieldGroupOverrides()[$key] ?? self::inferGroup($key);
        $options = $overrides['options'] ?? [];
        if ($inputType === 'select' && $options === []) {
            $options = ['1' => 'Yes', '0' => 'No'];
        }

        $definition = array_merge([
            'key' => $key,
            'label' => $label,
            'short_label' => null,
            'description' => self::descriptionFor($key, $group, $label),
            'group' => $group,
            'input_type' => $inputType,
            'unit' => $unit,
            'step' => $inputType === 'number' ? self::inferStep($key) : null,
            'min' => $inputType === 'number' ? self::inferMin($key) : null,
            'max' => $inputType === 'number' ? self::inferMax($key) : null,
            'options' => $options,
            'placeholder' => self::placeholderFor($key, $unit),
            'filterable' => self::isFilterable($key),
        ], $overrides);

        // A category-specific ownership decision must override the reusable field default.
        $definition['group'] = $group;
        $definition['description'] = self::descriptionFor($key, $group, (string) $definition['label']);

        return $definition;
    }

    /** @return array<string, array<string, mixed>> */
    private static function subcategorySchemas(): array
    {
        $pvBase = ['module_power_wp', 'voc', 'vmp', 'isc', 'imp', 'module_efficiency_percent', 'temp_coeff_voc', 'temp_coeff_pmax', 'length_mm', 'width_mm', 'weight_kg', 'cell_type', 'cell_count', 'max_system_voltage', 'product_warranty_years', 'performance_warranty_years'];
        $stringInv = ['inverter_type', 'rated_ac_power_kw', 'max_dc_power_kwp', 'max_dc_voltage', 'startup_voltage', 'mppt_count', 'strings_per_mppt', 'mppt_voltage_min', 'mppt_voltage_max', 'max_input_current_per_mppt', 'max_short_circuit_current_per_mppt', 'rated_ac_voltage', 'rated_ac_current_a', 'phase_type', 'efficiency_percent', 'anti_islanding', 'grid_code_profile', 'communication_protocol', 'ip_rating', 'warranty_years'];
        $hybridInv = ['inverter_type', 'rated_ac_power_kw', 'max_dc_power_kwp', 'max_dc_voltage', 'mppt_count', 'mppt_voltage_min', 'mppt_voltage_max', 'battery_voltage_range', 'max_charge_current_a', 'max_discharge_current_a', 'backup_output_kw', 'surge_power_kw', 'bms_protocol', 'genset_support', 'phase_type', 'efficiency_percent', 'communication_protocol', 'ip_rating', 'warranty_years'];
        $batteryModule = ['battery_chemistry', 'nominal_voltage_v', 'capacity_ah', 'capacity_kwh', 'usable_capacity_kwh', 'dod_percent', 'round_trip_efficiency_percent', 'cycle_life', 'max_charge_current_a', 'max_discharge_current_a', 'c_rate', 'parallel_limit', 'communication_protocol', 'operating_temperature', 'ip_rating', 'fire_safety_required', 'warranty_years'];

        return [
            'PV_MONO_PERC' => self::schema('Mono PERC Module', 'Electrical, thermal, mechanical, and warranty values for monocrystalline PERC modules.', $pvBase, ['connector_type', 'frame_material']),
            'PV_TOPCON' => self::schema('TOPCon Module', 'High-efficiency TOPCon module profile with electrical, thermal, and degradation evidence.', $pvBase, ['first_year_degradation_percent', 'annual_degradation_percent', 'connector_type']),
            'PV_HJT' => self::schema('HJT Module', 'Heterojunction module profile with low-temperature coefficient and glass architecture evidence.', $pvBase, ['glass_configuration', 'first_year_degradation_percent', 'annual_degradation_percent']),
            'PV_BIFACIAL' => self::schema('Bifacial Module', 'Bifacial module profile with rear-side performance and glass construction evidence.', array_merge($pvBase, ['bifaciality_percent']), ['rear_side_power_gain_percent', 'glass_configuration', 'first_year_degradation_percent', 'annual_degradation_percent']),
            'PV_HIGH_POWER' => self::schema('High-power Commercial Module', 'Large-format commercial module profile with current, size, load, and connector constraints.', $pvBase, ['module_technology', 'max_series_fuse_rating_a', 'front_static_load_pa', 'rear_static_load_pa', 'connector_type']),

            'INV_STRING_1P' => self::schema('String Inverter · Single Phase', 'Single-phase string inverter profile for residential and light-commercial grid connection.', $stringInv, ['max_ac_apparent_power_kva', 'power_factor_range', 'noise_level_db', 'cooling_method']),
            'INV_STRING_3P' => self::schema('String Inverter · Three Phase', 'Three-phase string inverter profile with higher current, MPPT, and grid integration requirements.', $stringInv, ['max_ac_apparent_power_kva', 'power_factor_range', 'rated_frequency_hz', 'cooling_method']),
            'INV_MICRO' => self::schema('Microinverter', 'Module-level inverter profile with input channels, branch limits, and rapid-shutdown capability.', ['inverter_type', 'rated_ac_power_kw', 'rated_input_power_wp', 'input_channel_count', 'max_input_voltage', 'max_input_current_a', 'mppt_voltage_min', 'mppt_voltage_max', 'rated_ac_voltage', 'phase_type', 'efficiency_percent', 'anti_islanding', 'rapid_shutdown_support', 'max_units_per_branch', 'communication_protocol', 'ip_rating', 'warranty_years'], ['trunk_cable_type', 'connector_type']),
            'INV_CENTRAL' => self::schema('Central Inverter', 'Central inverter profile for utility or large-commercial systems with high-current and cooling requirements.', array_merge($stringInv, ['dc_input_count', 'rated_ac_current_a', 'cooling_method']), ['max_ac_apparent_power_kva', 'transformer_required', 'noise_level_db', 'operating_temperature']),
            'INV_OPTIMIZER' => self::schema('Module Optimizer / MLPE', 'Module-level power electronics profile for optimizer and rapid-shutdown coordination.', ['device_type', 'rated_input_power_wp', 'max_input_voltage', 'max_input_current_a', 'output_voltage_range', 'efficiency_percent', 'rapid_shutdown_support', 'communication_protocol', 'ip_rating', 'warranty_years'], ['connector_type', 'compatible_inverter_series']),

            'INV_HYBRID' => self::schema('Hybrid Inverter', 'Hybrid inverter profile for grid, battery, backup, and optional genset coordination.', $hybridInv, ['rated_ac_voltage', 'rated_ac_current_a', 'transfer_time_ms', 'parallel_limit', 'grid_code_profile']),
            'INV_OFFGRID' => self::schema('Off-grid Inverter', 'Off-grid inverter profile with surge, charger, generator, and battery-operating requirements.', $hybridInv, ['nominal_battery_voltage_v', 'charger_power_kw', 'transfer_time_ms', 'parallel_limit']),
            'INV_MPPT_CONTROLLER' => self::schema('MPPT Charge Controller', 'Dedicated charge-controller profile for PV input, battery voltage, current, and protocol limits.', ['device_type', 'max_pv_power_kwp', 'max_dc_voltage', 'mppt_voltage_min', 'mppt_voltage_max', 'max_charge_current_a', 'battery_voltage_range', 'battery_chemistry_support', 'efficiency_percent', 'communication_protocol', 'ip_rating', 'warranty_years'], ['parallel_limit', 'temperature_compensation']),
            'INV_PCS' => self::schema('Power Conversion System', 'Commercial or utility PCS profile for bidirectional battery integration and grid support.', ['device_type', 'rated_ac_power_kw', 'max_ac_apparent_power_kva', 'dc_voltage_range', 'rated_ac_voltage', 'rated_ac_current_a', 'phase_type', 'max_charge_current_a', 'max_discharge_current_a', 'efficiency_percent', 'grid_support_function', 'communication_protocol', 'cooling_method', 'ip_rating', 'warranty_years'], ['parallel_limit', 'transformer_required', 'operating_temperature']),

            'BAT_WALL' => self::schema('Wall-mounted Battery', 'Compact wall battery profile with energy, current, protocol, environmental, and warranty evidence.', $batteryModule, ['mounting_type', 'dimensions_mm', 'weight_kg']),
            'BAT_RACK' => self::schema('Rack Battery Module', 'Rack battery module profile with parallel, BMS, current, and rack integration constraints.', $batteryModule, ['rack_unit_height', 'compatible_rack_type', 'weight_kg']),
            'BAT_CABINET' => self::schema('Battery Cabinet', 'Cabinet-level battery assembly profile with energy, lifecycle, module count, thermal, communication, protection, and fire requirements.', ['device_type', 'battery_chemistry', 'nominal_voltage_v', 'gross_capacity_kwh', 'usable_capacity_kwh', 'dod_percent', 'round_trip_efficiency_percent', 'cycle_life', 'module_count', 'max_charge_current_a', 'max_discharge_current_a', 'bms_protocol', 'communication_protocol', 'thermal_management', 'fire_suppression_interface', 'parallel_limit', 'ip_rating', 'operating_temperature', 'warranty_years'], ['dimensions_mm', 'weight_kg']),
            'BAT_BMS' => self::schema('Battery Management System', 'BMS profile with supported voltage, current, cell count, communication, and balancing requirements.', ['device_type', 'supported_cell_count', 'battery_voltage_range', 'continuous_current_a', 'peak_current_a', 'balancing_current_a', 'can_profile', 'rs485_protocol', 'communication_protocol', 'compatible_inverter_series', 'operating_temperature', 'ip_rating', 'warranty_years'], ['parallel_limit', 'firmware_update_method']),
            'BAT_ESS_PACKAGE' => self::schema('ESS Rack / Cabinet Package', 'Integrated ESS package profile with power, energy, PCS, BMS, thermal, and safety requirements.', ['device_type', 'rated_ac_power_kw', 'gross_capacity_kwh', 'usable_capacity_kwh', 'nominal_voltage_v', 'dod_percent', 'round_trip_efficiency_percent', 'cycle_life', 'pcs_included', 'bms_protocol', 'thermal_management', 'fire_suppression_interface', 'communication_protocol', 'ip_rating', 'warranty_years'], ['grid_support_function', 'parallel_limit', 'operating_temperature']),

            'DC_CABLE_CONNECTOR' => self::schema('PV Cable & Connector', 'Combined cable-and-connector profile. Common ratings are mandatory; cable or connector construction fields apply only to the selected subtype.', ['device_or_cable_type', 'rated_voltage', 'rated_current', 'temperature_rating', 'ip_rating', 'standard'], [], ['conductor_size_mm2', 'conductor_material', 'insulation', 'uv_resistance', 'outer_diameter_mm', 'flame_rating', 'connector_type', 'cable_color', 'minimum_bending_radius_mm', 'drum_length_m']),
            'DC_COMBINER' => self::schema('DC Combiner & Junction', 'Combiner and junction profile. Enclosure and electrical ratings are mandatory; fuse, SPD, isolator, and monitoring fields depend on the assembled variant.', ['device_type', 'string_input_count', 'rated_voltage', 'rated_current', 'busbar_material', 'ip_rating', 'standard'], ['enclosure_material', 'cable_entry_count'], ['fuse_rating_a', 'spd_type', 'dc_isolator_rating_a', 'monitoring_support', 'communication_protocol']),
            'DC_PROTECTION' => self::schema('DC Fuse, Breaker & Isolator', 'Combined DC protection profile. Common ratings are mandatory; fuse, breaker, and isolator characteristics are subtype-dependent.', ['device_type', 'rated_voltage', 'rated_current', 'pole_count', 'mounting_type', 'ip_rating', 'standard'], [], ['breaking_capacity_ka', 'trip_curve', 'isolation_suitability', 'fuse_class', 'utilization_category', 'remote_trip_support']),
            'DC_SPD' => self::schema('DC Surge Protection', 'DC SPD profile for classification, maximum continuous operating voltage, protection level, discharge capability, Iscpv rating, and enclosure evidence.', ['spd_type', 'uc_voltage', 'up_voltage_kv', 'in_current', 'imax_current', 'pole_count', 'response_time_ns', 'short_circuit_withstand_ka', 'ip_rating', 'standard'], ['remote_contact', 'replaceable_cartridge', 'visual_indicator']),
            'DC_CONTAINMENT' => self::schema('Conduit, Tray & Termination', 'Combined containment profile. Material, size, load, UV, corrosion, flame, temperature, and standard are common; enclosure IP and cover/support details depend on the selected subtype.', ['device_or_material_type', 'material', 'dimensions_mm', 'load_capacity_kg_m', 'uv_resistance', 'corrosion_class', 'flame_rating', 'operating_temperature', 'standard'], [], ['ip_rating', 'cover_type', 'support_spacing_m']),

            'AC_CABLE' => self::schema('AC Power Cable', 'AC cable profile for conductor, cores, insulation, ampacity, voltage, temperature, and installation.', ['device_or_cable_type', 'conductor_size_mm2', 'core_count', 'conductor_material', 'rated_voltage', 'rated_current', 'insulation', 'temperature_rating', 'outer_diameter_mm', 'flame_rating', 'standard'], ['armour_type', 'minimum_bending_radius_mm', 'installation_method']),
            'AC_PROTECTION' => self::schema('MCB, MCCB, ACB & Protection', 'Combined AC protection profile. Common ratings are mandatory; curve, trip-unit, adjustability, and accessories depend on the selected breaker or protective device.', ['device_type', 'rated_voltage', 'rated_current', 'poles', 'mounting_type', 'standard'], [], ['breaking_capacity_ka', 'short_circuit_rating_ka', 'curve_type', 'trip_unit_type', 'adjustable_trip_range', 'auxiliary_contact', 'shunt_trip_support', 'earth_leakage_protection']),
            'AC_TRANSFER_SYNC' => self::schema('ATS, STS, AMF & Synchronization', 'Combined transfer and synchronization profile. Common source, rating, controller, enclosure, and standard fields are mandatory; transfer and synchronization functions are subtype-dependent.', ['device_type', 'rated_voltage', 'rated_current', 'poles', 'source_count', 'controller_type', 'communication_protocol', 'ip_rating', 'standard'], ['mechanical_interlock'], ['transfer_type', 'transfer_time_ms', 'genset_support', 'bypass_support', 'sync_check_relay']),
            'AC_METERING_CONTROL' => self::schema('CT, Metering & Export Control', 'Combined CT, meter, and export-control profile. Common measurement and communication fields are mandatory; CT ratio, accuracy, export control, and response fields depend on the selected device subtype.', ['device_type', 'rated_voltage', 'rated_current', 'measured_parameter', 'communication_protocol', 'communication_port', 'ip_rating', 'standard'], [], ['ct_ratio', 'accuracy_class', 'export_control_support', 'response_time_ms', 'pulse_output', 'data_logging_support']),
            'AC_TRANSFORMER_MV' => self::schema('Transformer & MV Integration', 'Transformer and MV equipment profile for power, ratio, vector group, insulation, loss, and protection.', ['device_type', 'rated_power_kva', 'primary_voltage_kv', 'secondary_voltage_v', 'vector_group', 'frequency_hz', 'impedance_percent', 'cooling_method', 'insulation_class', 'short_circuit_rating_ka', 'ip_rating', 'standard'], ['no_load_loss_w', 'load_loss_w', 'tap_range_percent']),

            'MOUNT_ROOF' => self::schema('Rooftop Rail & Clamp', 'Rooftop mounting profile for roof compatibility, rail, clamp, fastener, corrosion, wind loading, and design standard.', ['mounting_type', 'roof_compatibility', 'rail_profile', 'rail_length', 'material_grade', 'clamp_range_mm', 'fastener_type', 'corrosion_class', 'wind_speed_rating', 'roof_penetration', 'unit_weight_kg', 'standard'], ['orientation', 'tilt_range', 'module_frame_compatibility']),
            'MOUNT_BALLAST' => self::schema('Ballasted Mounting', 'Ballasted mounting profile for tilt, ballast, roof load, wind, material, drainage, and design standard.', ['mounting_type', 'roof_compatibility', 'tilt_range', 'orientation', 'ballast_weight_kg_per_module', 'roof_load_kg_m2', 'material_grade', 'corrosion_class', 'wind_speed_rating', 'roof_penetration', 'unit_weight_kg', 'standard'], ['drainage_clearance_mm', 'module_frame_compatibility']),
            'MOUNT_GROUND' => self::schema('Ground-mounted Structure', 'Ground-mount profile for foundation, pile, steel, terrain, tilt, wind, corrosion, and design standard.', ['mounting_type', 'foundation_type', 'pile_type', 'material_grade', 'corrosion_class', 'tilt_range', 'orientation', 'terrain_compatibility', 'wind_speed_rating', 'snow_load_rating_pa', 'unit_weight_kg', 'standard'], ['row_spacing_m', 'ground_clearance_mm']),
            'MOUNT_CARPORT' => self::schema('Solar Carport Structure', 'Carport structure profile for span, clearance, drainage, steel, foundation, design loads, and governing standard.', ['mounting_type', 'foundation_type', 'material_grade', 'corrosion_class', 'bay_span_m', 'clear_height_m', 'tilt_range', 'drainage_system', 'wind_speed_rating', 'snow_load_rating_pa', 'unit_weight_kg', 'standard'], ['vehicle_clearance_m', 'lighting_provision']),
            'MOUNT_ACCESS' => self::schema('Walkway, Lifeline & Access', 'Combined access-and-safety profile. Material, corrosion, roof suitability, and standard are mandatory; walkway and lifeline ratings depend on the selected subtype.', ['device_or_material_type', 'material_grade', 'corrosion_class', 'roof_compatibility', 'standard'], ['surface_finish', 'support_spacing_m'], ['walkway_width_mm', 'load_capacity_kg_m2', 'anchor_type', 'lifeline_rating_kn']),

            'MON_DATA_LOGGER' => self::schema('Data Logger & Gateway', 'Data logging profile for interfaces, protocols, storage, cloud, interval, and environment.', ['device_type', 'protocol', 'communication_port', 'modbus_support', 'can_support', 'mqtt_support', 'logging_interval', 'local_storage_days', 'device_capacity', 'cloud_platform', 'subscription_years', 'ip_rating'], ['remote_update_support', 'alarm_notification_support']),
            'MON_METER' => self::schema('Smart Meter & Energy Meter', 'Meter profile for phases, voltage, current, accuracy, CT, measured parameters, and protocol.', ['device_type', 'phase_type', 'rated_voltage', 'rated_current', 'ct_ratio', 'accuracy_class', 'measured_parameter', 'communication_protocol', 'communication_port', 'logging_interval', 'ip_rating', 'standard'], ['pulse_output', 'export_control_support']),
            'MON_NETWORK' => self::schema('Router, Switch & Communication', 'Network equipment profile for ports, media, throughput, power, protocol, and environment.', ['device_type', 'ethernet_port_count', 'communication_port', 'network_speed_mbps', 'sim_support', 'wifi_support', 'vpn_support', 'power_supply_voltage', 'operating_temperature', 'ip_rating', 'warranty_years'], ['poe_support', 'redundant_power_support']),
            'MON_WEATHER' => self::schema('Weather Station & Pyranometer', 'Weather monitoring profile for sensor type, range, accuracy, output, logging, and calibration.', ['device_type', 'measured_parameter', 'measurement_range', 'accuracy_class', 'sensor_output', 'communication_protocol', 'logging_interval', 'calibration_interval_months', 'operating_temperature', 'ip_rating', 'standard'], ['spectral_range_nm', 'response_time_s']),
            'MON_SENSOR' => self::schema('Temperature & Environmental Sensor', 'Environmental sensor profile for parameter, range, accuracy, output, mounting, and enclosure.', ['device_type', 'measured_parameter', 'measurement_range', 'accuracy_class', 'sensor_output', 'communication_protocol', 'mounting_type', 'response_time_s', 'operating_temperature', 'ip_rating'], ['cable_length_m', 'calibration_interval_months']),

            'GROUNDING_SYSTEM' => self::schema('Grounding Electrode & Conductor', 'Grounding profile for material, conductor size, electrode geometry, resistance target, and corrosion.', ['device_or_material_type', 'material', 'conductor_size_mm2', 'length_m', 'electrode_diameter_mm', 'target_resistance_ohm', 'corrosion_resistance', 'application_area', 'standard'], ['connection_method', 'inspection_pit_required']),
            'LIGHTNING_PROTECTION' => self::schema('Lightning Protection System', 'Lightning protection profile for class, air terminal, down conductor, separation, and coverage.', ['device_type', 'lps_class', 'air_terminal_type', 'air_terminal_height_m', 'down_conductor_material', 'conductor_size_mm2', 'separation_distance_m', 'coverage_radius_m', 'corrosion_resistance', 'standard'], ['lightning_counter_support', 'test_joint_required']),
            'BONDING' => self::schema('Equipotential Bonding', 'Bonding profile for conductor, bar, connection, current, location, and corrosion.', ['device_or_material_type', 'material', 'conductor_size_mm2', 'bonding_bar_size_mm', 'connection_method', 'rated_current', 'application_area', 'corrosion_resistance', 'standard'], ['terminal_count', 'enclosure_required']),
            'SAFETY_LABEL_LOTO' => self::schema('Warning Label & LOTO', 'Safety identification profile for material, message, durability, application, and lockout scope.', ['device_or_material_type', 'safety_scope', 'label_material', 'label_size_mm', 'uv_resistance', 'chemical_resistance', 'application_area', 'language', 'standard'], ['lock_count', 'keying_method']),

            'PANEL_DCDB' => self::schema('DC Distribution Board', 'DC panel profile for voltage, current, busbar, inputs, protection, metering, enclosure, location, and assembly standard.', ['panel_type', 'rated_voltage', 'rated_current', 'short_circuit_rating_ka', 'busbar_material', 'incoming_breaker', 'outgoing_breaker_count', 'spd_type', 'metering', 'installation_location', 'ip_rating', 'standard'], ['form_separation', 'enclosure_material']),
            'PANEL_ACDB' => self::schema('AC Distribution Board', 'AC panel profile for phase, voltage, current, protection, busbar, metering, enclosure, and assembly standard.', ['panel_type', 'phase_type', 'rated_voltage', 'rated_current', 'short_circuit_rating_ka', 'busbar_material', 'incoming_breaker', 'outgoing_breaker_count', 'metering', 'installation_location', 'ip_rating', 'standard'], ['form_separation', 'earth_leakage_protection']),
            'PANEL_BACKUP' => self::schema('Critical Load / Backup Panel', 'Backup panel profile for source, essential circuits, transfer, load capacity, segregation, and assembly standard.', ['panel_type', 'phase_type', 'rated_voltage', 'rated_current', 'source_count', 'critical_circuit_count', 'transfer_type', 'short_circuit_rating_ka', 'incoming_breaker', 'outgoing_breaker_count', 'ip_rating', 'standard'], ['bypass_support', 'load_shedding_support']),
            'PANEL_LVMDP' => self::schema('LVMDP Integration', 'LVMDP integration profile for busbar, breaker, short-circuit, metering, form, spare capacity, and assembly standard.', ['panel_type', 'phase_type', 'rated_voltage', 'rated_current', 'short_circuit_rating_ka', 'busbar_material', 'form_separation', 'incoming_breaker', 'outgoing_breaker_count', 'metering', 'spare_capacity_percent', 'ip_rating', 'standard'], ['communication_protocol', 'arc_flash_label_required']),
            'PANEL_SYNC' => self::schema('Synchronization Panel', 'Synchronization panel profile for source count, controller, relay, breaker, metering, communication, and assembly standard.', ['panel_type', 'phase_type', 'rated_voltage', 'rated_current', 'source_count', 'sync_controller_type', 'sync_check_relay', 'short_circuit_rating_ka', 'incoming_breaker', 'metering', 'communication_protocol', 'ip_rating', 'standard'], ['genset_support', 'load_sharing_support']),

            'CIVIL_ANCHOR_SEALANT' => self::schema('Anchor, Sealant & Waterproofing', 'Civil consumable profile for material, application, substrate, cure, coverage, and weather resistance.', ['work_type', 'material', 'application_area', 'substrate_compatibility', 'coverage_rate', 'curing_time_hours', 'weather_resistance', 'deliverable', 'responsible_party'], ['volume', 'unit']),
            'CIVIL_FOUNDATION' => self::schema('Foundation & Structural Civil Work', 'Foundation work profile for type, concrete, reinforcement, volume, equipment, duration, and deliverable.', ['work_type', 'foundation_type', 'concrete_grade', 'reinforcement_grade', 'volume', 'unit', 'equipment_required', 'working_days', 'deliverable', 'responsible_party'], ['excavation_depth_m', 'bearing_capacity_kpa']),
            'CIVIL_TRENCH_DUCT' => self::schema('Trenching & Duct Bank', 'Trench and duct profile for route, dimensions, duct, backfill, warning tape, and duration.', ['work_type', 'route_length_m', 'trench_depth_m', 'trench_width_m', 'duct_count', 'duct_size_mm', 'backfill_material', 'warning_tape_required', 'working_days', 'deliverable'], ['manpower', 'equipment_required']),
            'CIVIL_SURVEY' => self::schema('Survey & Site Assessment', 'Survey service profile for scope, instruments, area, deliverable, duration, and responsible party.', ['work_type', 'service_scope', 'survey_instrument', 'coverage_area', 'working_days', 'manpower', 'equipment_required', 'deliverable', 'responsible_party'], ['processing_days', 'accuracy_requirement']),
            'CIVIL_BUILDING_WORK' => self::schema('Equipment Shelter & Site Facilities', 'Building work profile for type, area, structure, environmental control, utilities, and schedule.', ['work_type', 'building_area_m2', 'structure_type', 'wall_material', 'roof_material', 'ventilation_required', 'fire_rating_minutes', 'working_days', 'deliverable', 'responsible_party'], ['electrical_service_required', 'drainage_required']),

            'FIRE_EXTINGUISHER' => self::schema('Portable Fire Extinguisher', 'Portable extinguisher profile for agent, capacity, rating, pressure, maintenance, and standard.', ['device_or_service_type', 'extinguishing_agent', 'capacity', 'fire_rating', 'operating_pressure_bar', 'discharge_time_s', 'maintenance_interval', 'application_area', 'standard'], ['mounting_bracket_included', 'warranty_years']),
            'FIRE_DETECTION' => self::schema('Smoke, Heat & Gas Detection', 'Detection profile for sensor, coverage, alarm, power, protocol, environment, and maintenance.', ['device_or_service_type', 'detection_type', 'coverage_area', 'alarm_output', 'power_supply_voltage', 'communication_protocol', 'response_time_s', 'operating_temperature', 'ip_rating', 'standard'], ['maintenance_interval', 'relay_output']),
            'FIRE_SUPPRESSION' => self::schema('Fixed Fire Suppression', 'Suppression profile for agent, enclosure volume, discharge, activation, monitoring, and service.', ['device_or_service_type', 'extinguishing_agent', 'protected_volume_m3', 'agent_quantity_kg', 'discharge_time_s', 'activation_method', 'detection_interface', 'maintenance_interval', 'safety_scope', 'standard'], ['pressure_relief_required', 'remote_alarm_support']),
            'HSE_PPE' => self::schema('PPE & Site Safety Equipment', 'PPE profile for safety scope, protection class, material, size, inspection, and standard.', ['device_or_service_type', 'safety_scope', 'ppe_category', 'protection_class', 'material', 'size_range', 'inspection_interval', 'application_area', 'standard'], ['service_life_years', 'storage_condition']),
            'HSE_SERVICE' => self::schema('HSE Procedure & Service', 'HSE service profile for scope, manpower, duration, deliverables, authority, and review interval.', ['device_or_service_type', 'service_scope', 'manpower', 'working_days', 'deliverable', 'responsible_party', 'assessment_checklist', 'maintenance_interval'], ['applicable_standard', 'training_required']),

            'LEGAL_SLO_INSPECTION' => self::schema('SLO & Technical Inspection', 'Inspection service profile for authority, scope, standard, documents, lead time, and validity.', ['service_type', 'authority_or_issuer', 'service_scope', 'applicable_standard', 'required_document_pack', 'processing_days', 'validity_period', 'deliverable', 'responsible_party'], ['site_visit_required', 'renewal_required']),
            'LEGAL_UTILITY' => self::schema('Utility Application & Interconnection', 'Utility service profile for application, capacity, utility, document pack, process, and responsibility.', ['service_type', 'utility_name', 'application_type', 'connection_capacity_kva', 'applicable_standard', 'required_document_pack', 'processing_days', 'deliverable', 'responsible_party'], ['export_limit_kw', 'meter_change_required']),
            'LEGAL_CERTIFICATE' => self::schema('Product / System Certification', 'Certification service profile for issuer, type, standard, evidence, process, and validity.', ['service_type', 'authority_or_issuer', 'certificate_type', 'applicable_standard', 'required_document_pack', 'processing_days', 'validity_period', 'deliverable', 'responsible_party'], ['renewal_required', 'sample_test_required']),
            'LEGAL_PERMIT' => self::schema('Permit & Registration', 'Permit service profile for authority, permit type, documents, processing, validity, and responsibility.', ['service_type', 'authority_or_issuer', 'permit_type', 'required_document_pack', 'processing_days', 'validity_period', 'deliverable', 'responsible_party'], ['renewal_required', 'fee_basis']),

            'LOG_FREIGHT' => self::schema('Freight & Delivery', 'Freight service profile for mode, origin, destination, weight, Incoterm, lead time, and insurance.', ['service_or_equipment_type', 'transport_mode', 'origin', 'destination', 'shipment_weight_kg', 'shipment_volume_m3', 'delivery_terms', 'lead_time_days', 'insurance_included', 'deliverable'], ['tracking_support', 'special_handling_required']),
            'LOG_STORAGE' => self::schema('Warehousing & Site Storage', 'Storage profile for location, area, duration, environment, security, and handling.', ['service_or_equipment_type', 'storage_location', 'storage_area_m2', 'storage_duration_days', 'environmental_control', 'security_level', 'handling_equipment', 'coverage_area', 'deliverable'], ['insurance_included', 'inventory_reporting_interval']),
            'OM_TEST_EQUIPMENT' => self::schema('O&M Test Equipment', 'Test equipment profile for measurement, range, accuracy, calibration, safety, and warranty.', ['service_or_equipment_type', 'measured_parameter', 'measurement_range', 'accuracy_class', 'calibration_interval_months', 'safety_category', 'communication_protocol', 'warranty_years'], ['ip_rating', 'data_logging_support']),
            'OM_SERVICE' => self::schema('Annual O&M Service', 'O&M service profile for scope, frequency, response, coverage, reports, and responsible party.', ['service_or_equipment_type', 'service_scope', 'maintenance_interval', 'response_time_hours', 'coverage_area', 'working_days', 'deliverable', 'responsible_party'], ['spare_parts_included', 'remote_monitoring_included']),
            'OM_SPARE_WARRANTY' => self::schema('Spare Parts & Warranty Support', 'Support profile for spare scope, stock, response, warranty process, and coverage.', ['service_or_equipment_type', 'service_scope', 'spare_part_category', 'minimum_stock_quantity', 'lead_time_days', 'coverage_area', 'warranty_claim_process', 'deliverable'], ['response_time_hours', 'replacement_unit_available']),

            'QUOTE_MATERIAL' => self::schema('Material Supplier Quote', 'Commercial material quote profile for supplier, SKU, currency, price, MOQ, stock, lead, and validity.', ['supplier_name', 'supplier_sku', 'currency', 'unit_cost', 'minimum_order_quantity', 'availability_status', 'lead_time_days', 'valid_until', 'payment_terms', 'delivery_condition'], ['selling_price', 'tax_included', 'warranty_years']),
            'QUOTE_SERVICE' => self::schema('Service Provider Quote', 'Service quote profile for provider, scope, currency, price basis, duration, validity, and terms.', ['supplier_name', 'service_scope', 'currency', 'unit_cost', 'price_basis', 'working_days', 'valid_until', 'payment_terms', 'delivery_condition', 'deliverable'], ['minimum_order_quantity', 'tax_included']),
            'QUOTE_PRICELIST' => self::schema('Supplier Pricelist', 'Pricelist profile for supplier, currency, effective window, discount, stock, and commercial terms.', ['supplier_name', 'currency', 'effective_from', 'valid_until', 'price_basis', 'discount_percent', 'availability_status', 'lead_time_days', 'payment_terms', 'delivery_condition'], ['supplier_sku', 'minimum_order_quantity', 'tax_included']),
        ];
    }

    /** @return array<string, mixed> */
    private static function schema(string $title, string $description, array $required, array $optional = [], array $conditional = []): array
    {
        return compact('title', 'description', 'required', 'optional', 'conditional');
    }

    /**
     * Explicit semantic group ownership for ambiguous technical fields.
     *
     * @return array<string, string>
     */
    private static function fieldGroupOverrides(): array
    {
        return [
            // Product architecture and construction.
            'module_technology' => 'architecture',
            'cell_type' => 'architecture',
            'cell_count' => 'architecture',
            'bifacial' => 'architecture',
            'inverter_type' => 'architecture',
            'battery_chemistry' => 'battery',
            'pcs_included' => 'architecture',
            'module_count' => 'architecture',
            'supported_cell_count' => 'architecture',
            'device_type' => 'architecture',
            'device_or_cable_type' => 'architecture',
            'device_or_material_type' => 'architecture',
            'device_or_service_type' => 'architecture',
            'service_or_equipment_type' => 'architecture',
            'panel_type' => 'architecture',
            'input_channel_count' => 'architecture',
            'dc_input_count' => 'architecture',
            'source_count' => 'architecture',
            'string_input_count' => 'architecture',
            'pile_type' => 'construction',
            'trunk_cable_type' => 'construction',
            'glass_configuration' => 'construction',
            'frame_material' => 'construction',
            'connector_type' => 'construction',
            'conductor_material' => 'construction',
            'insulation' => 'construction',
            'busbar_material' => 'construction',
            'enclosure_material' => 'construction',
            'material' => 'construction',
            'material_grade' => 'construction',
            'label_material' => 'construction',
            'wall_material' => 'construction',
            'roof_material' => 'construction',
            'structure_type' => 'construction',
            'rail_profile' => 'construction',
            'surface_finish' => 'construction',
            'armour_type' => 'construction',
            'concrete_grade' => 'construction',
            'reinforcement_grade' => 'construction',
            'backfill_material' => 'construction',
            'extinguishing_agent' => 'safety',
            'down_conductor_material' => 'construction',

            // Electrical and energy-storage ratings.
            'backup_output_kw' => 'electrical',
            'surge_power_kw' => 'electrical',
            'charger_power_kw' => 'electrical',
            'connection_capacity_kva' => 'electrical',
            'export_limit_kw' => 'control',
            'spare_capacity_percent' => 'electrical',
            'balancing_current_a' => 'battery',
            'bearing_capacity_kpa' => 'mechanical',
            'capacity_ah' => 'battery',
            'capacity_kwh' => 'battery',
            'gross_capacity_kwh' => 'battery',
            'usable_capacity_kwh' => 'battery',
            'battery_voltage_range' => 'battery',
            'nominal_battery_voltage_v' => 'battery',
            'dod_percent' => 'battery',
            'c_rate' => 'battery',
            'parallel_limit' => 'battery',
            'max_charge_current_a' => 'battery',
            'max_discharge_current_a' => 'battery',

            // Performance and thermal behavior.
            'module_efficiency_percent' => 'performance',
            'efficiency_percent' => 'performance',
            'round_trip_efficiency_percent' => 'performance',
            'bifaciality_percent' => 'performance',
            'rear_side_power_gain_percent' => 'performance',
            'first_year_degradation_percent' => 'performance',
            'annual_degradation_percent' => 'performance',
            'cycle_life' => 'performance',
            'discharge_time_s' => 'performance',
            'temp_coeff_voc' => 'thermal',
            'temp_coeff_pmax' => 'thermal',
            'operating_temperature' => 'thermal',
            'temperature_rating' => 'thermal',
            'temperature_compensation' => 'thermal',
            'cooling_method' => 'thermal',
            'thermal_management' => 'thermal',
            'ventilation_required' => 'thermal',

            // Measurement, monitoring, automation, and communication.
            'ct_ratio' => 'measurement',
            'accuracy_class' => 'measurement',
            'accuracy_requirement' => 'measurement',
            'measured_parameter' => 'measurement',
            'measurement_range' => 'measurement',
            'sensor_output' => 'measurement',
            'response_time_s' => 'measurement',
            'calibration_interval_months' => 'measurement',
            'spectral_range_nm' => 'measurement',
            'survey_instrument' => 'measurement',
            'metering' => 'monitoring',
            'monitoring_support' => 'monitoring',
            'visual_indicator' => 'monitoring',
            'alarm_output' => 'monitoring',
            'relay_output' => 'monitoring',
            'remote_contact' => 'monitoring',
            'data_logging_support' => 'monitoring',
            'local_storage_days' => 'monitoring',
            'cloud_platform' => 'monitoring',
            'logging_interval' => 'monitoring',
            'transfer_type' => 'control',
            'transfer_time_ms' => 'control',
            'controller_type' => 'control',
            'sync_controller_type' => 'control',
            'sync_check_relay' => 'control',
            'mechanical_interlock' => 'control',
            'bypass_support' => 'control',
            'load_sharing_support' => 'control',
            'load_shedding_support' => 'control',
            'grid_support_function' => 'control',
            'export_control_support' => 'control',
            'remote_trip_support' => 'control',
            'shunt_trip_support' => 'control',
            'communication_protocol' => 'communication',
            'communication_port' => 'communication',
            'bms_protocol' => 'communication',
            'can_profile' => 'communication',
            'rs485_protocol' => 'communication',
            'protocol' => 'communication',
            'transport_mode' => 'service',

            // Protection, electrical safety, and compatibility.
            'anti_islanding' => 'protection',
            'rapid_shutdown_support' => 'protection',
            'breaking_capacity_ka' => 'protection',
            'short_circuit_rating_ka' => 'protection',
            'short_circuit_withstand_ka' => 'protection',
            'fuse_rating_a' => 'protection',
            'fuse_class' => 'protection',
            'spd_type' => 'protection',
            'uc_voltage' => 'protection',
            'up_voltage_kv' => 'protection',
            'in_current' => 'protection',
            'imax_current' => 'protection',
            'response_time_ns' => 'protection',
            'trip_curve' => 'protection',
            'curve_type' => 'protection',
            'trip_unit_type' => 'protection',
            'adjustable_trip_range' => 'protection',
            'isolation_suitability' => 'protection',
            'utilization_category' => 'protection',
            'earth_leakage_protection' => 'protection',
            'dc_isolator_rating_a' => 'protection',
            'incoming_breaker' => 'protection',
            'outgoing_breaker_count' => 'protection',
            'pole_count' => 'protection',
            'poles' => 'protection',
            'fire_safety_required' => 'safety',
            'fire_suppression_interface' => 'safety',
            'fire_rating' => 'safety',
            'fire_rating_minutes' => 'safety',
            'safety_scope' => 'safety',
            'protection_class' => 'safety',
            'lifeline_rating_kn' => 'safety',
            'warning_tape_required' => 'safety',
            'arc_flash_label_required' => 'safety',
            'lps_class' => 'safety',
            'safety_category' => 'safety',
            'ppe_category' => 'safety',
            'battery_chemistry_support' => 'compatibility',
            'compatible_inverter_series' => 'compatibility',
            'module_frame_compatibility' => 'compatibility',
            'roof_compatibility' => 'compatibility',
            'terrain_compatibility' => 'compatibility',
            'substrate_compatibility' => 'compatibility',
            'genset_support' => 'compatibility',
            'transformer_required' => 'compatibility',

            // Installation, service, maintenance, and governance.
            'mounting_type' => 'installation',
            'installation_method' => 'installation',
            'installation_location' => 'installation',
            'roof_penetration' => 'installation',
            'support_spacing_m' => 'installation',
            'cable_entry_count' => 'installation',
            'electrical_service_required' => 'installation',
            'route_length_m' => 'installation',
            'application_area' => 'application',
            'coverage_area' => 'application',
            'service_scope' => 'service',
            'deliverable' => 'service',
            'working_days' => 'service',
            'processing_days' => 'service',
            'responsible_party' => 'service',
            'response_time_hours' => 'service',
            'warranty_claim_process' => 'service',
            'manpower' => 'execution',
            'equipment_required' => 'execution',
            'maintenance_interval' => 'maintenance',
            'inspection_interval' => 'maintenance',
            'service_life_years' => 'maintenance',
            'replaceable_cartridge' => 'maintenance',
            'firmware_update_method' => 'maintenance',
            'spare_parts_included' => 'maintenance',
            'replacement_unit_available' => 'maintenance',
            'applicable_standard' => 'compliance',
            'standard' => 'compliance',
            'grid_code_profile' => 'compliance',
            'authority_or_issuer' => 'compliance',
            'certificate_type' => 'compliance',
            'permit_type' => 'compliance',
            'required_document_pack' => 'compliance',
            'assessment_checklist' => 'compliance',
            'product_warranty_years' => 'warranty',
            'performance_warranty_years' => 'warranty',
            'warranty_years' => 'warranty',
            'validity_period' => 'compliance',

            // Environmental, logistics, and remaining precision overrides.
            'ip_rating' => 'environmental',
            'uv_resistance' => 'environmental',
            'weather_resistance' => 'environmental',
            'environmental_control' => 'environmental',
            'storage_condition' => 'environmental',
            'noise_level_db' => 'environmental',
            'flame_rating' => 'safety',
            'chemical_resistance' => 'environmental',
            'operating_pressure_bar' => 'protection',
            'pressure_relief_required' => 'safety',
            'protected_volume_m3' => 'application',
            'activation_method' => 'control',
            'detection_interface' => 'control',
            'remote_alarm_support' => 'monitoring',
            'alarm_notification_support' => 'monitoring',
            'remote_update_support' => 'maintenance',
            'tracking_support' => 'monitoring',
            'inventory_reporting_interval' => 'monitoring',
            'remote_monitoring_included' => 'monitoring',
            'pulse_output' => 'monitoring',
            'lightning_counter_support' => 'monitoring',
            'minimum_bending_radius_mm' => 'mechanical',
            'clamp_range_mm' => 'mechanical',
            'row_spacing_m' => 'mechanical',
            'separation_distance_m' => 'safety',
            'target_resistance_ohm' => 'electrical',
            'vector_group' => 'electrical',
            'tap_range_percent' => 'electrical',
            'core_count' => 'electrical',
            'critical_circuit_count' => 'electrical',
            'terminal_count' => 'electrical',
            'max_units_per_branch' => 'electrical',
            'form_separation' => 'architecture',
            'cover_type' => 'construction',
            'fastener_type' => 'installation',
            'anchor_type' => 'installation',
            'connection_method' => 'installation',
            'test_joint_required' => 'installation',
            'inspection_pit_required' => 'installation',
            'curing_time_hours' => 'installation',
            'drainage_required' => 'installation',
            'drainage_system' => 'installation',
            'excavation_depth_m' => 'installation',
            'lighting_provision' => 'installation',
            'network_speed_mbps' => 'communication',
            'lead_time_days' => 'commercial',
            'subscription_years' => 'commercial',
            'special_handling_required' => 'service',
            'site_visit_required' => 'service',
            'meter_change_required' => 'service',
            'training_required' => 'service',
            'renewal_required' => 'compliance',
            'sample_test_required' => 'compliance',
            'fee_basis' => 'commercial',
            'shipment_volume_m3' => 'service',
            'storage_area_m2' => 'service',
            'storage_duration_days' => 'service',
            'storage_location' => 'service',
            'security_level' => 'service',
            'handling_equipment' => 'execution',
            'origin' => 'service',
            'destination' => 'service',
            'response_time_ms' => 'control',
            'auxiliary_contact' => 'monitoring',
            'building_area_m2' => 'mechanical',
            'cable_color' => 'construction',
            'keying_method' => 'safety',
            'lock_count' => 'safety',
            'volume' => 'execution',
            'unit' => 'execution',
            'language' => 'compliance',
            'utility_name' => 'service',
            'application_type' => 'service',
            'device_capacity' => 'monitoring',
            'redundant_power_support' => 'architecture',
            'shipment_weight_kg' => 'service',
            'capacity' => 'safety',
            'agent_quantity_kg' => 'safety',
        ];
    }

    /**
     * Context-sensitive group ownership for field keys reused by different component families.
     *
     * @return array<string, array<string, string>>
     */
    private static function categoryFieldGroupOverrides(): array
    {
        return [
            'FIRE_DETECTION' => ['response_time_s' => 'performance'],
            'MON_SENSOR' => ['cable_length_m' => 'installation'],
            'LOG_STORAGE' => ['coverage_area' => 'service'],
            'OM_SERVICE' => ['coverage_area' => 'service'],
            'OM_SPARE_WARRANTY' => ['coverage_area' => 'service'],
            'HSE_PPE' => ['service_life_years' => 'warranty'],
        ];
    }

    /**
     * Category constraints for reusable fields whose valid values are fixed by an exact subtype.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function categoryFieldDefinitionOverrides(): array
    {
        return [
            'INV_STRING_1P' => [
                'phase_type' => ['options' => ['single_phase' => 'Single Phase']],
            ],
            'INV_STRING_3P' => [
                'phase_type' => ['options' => ['three_phase' => 'Three Phase']],
            ],
            'INV_CENTRAL' => [
                'phase_type' => ['options' => ['three_phase' => 'Three Phase']],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function fieldOverrides(): array
    {
        $yesNo = ['1' => 'Yes', '0' => 'No'];

        return [
            'temp_coeff_voc' => ['label' => 'Temperature Coefficient Voc', 'input_type' => 'number', 'group' => 'thermal', 'unit' => '%/°C', 'step' => '0.001', 'min' => -10, 'max' => 10],
            'temp_coeff_pmax' => ['label' => 'Temperature Coefficient Pmax', 'input_type' => 'number', 'group' => 'thermal', 'unit' => '%/°C', 'step' => '0.001', 'min' => -10, 'max' => 10],
            'module_technology' => ['label' => 'Module Technology Family', 'group' => 'architecture', 'placeholder' => 'Example: N-type TOPCon, HJT, PERC'],
            'cell_type' => ['label' => 'Cell Technology / Format', 'group' => 'architecture', 'placeholder' => 'Example: N-type monocrystalline half-cut'],
            'cell_count' => ['label' => 'Cell Count', 'input_type' => 'number', 'group' => 'architecture', 'unit' => 'cells', 'step' => '1', 'min' => 1],
            'bifaciality_percent' => ['label' => 'Bifaciality Factor', 'input_type' => 'number', 'group' => 'performance', 'unit' => '%', 'step' => '0.01', 'min' => 0, 'max' => 100],
            'rear_side_power_gain_percent' => ['label' => 'Expected Rear-side Power Gain', 'input_type' => 'number', 'group' => 'performance', 'unit' => '%', 'step' => '0.01', 'min' => 0, 'max' => 100],
            'capacity_ah' => ['label' => 'Nominal Capacity', 'input_type' => 'number', 'group' => 'battery', 'unit' => 'Ah', 'step' => '0.01', 'min' => 0],
            'capacity_kwh' => ['label' => 'Nominal Energy', 'input_type' => 'number', 'group' => 'battery', 'unit' => 'kWh', 'step' => '0.01', 'min' => 0],
            'gross_capacity_kwh' => ['label' => 'Gross Energy', 'input_type' => 'number', 'group' => 'battery', 'unit' => 'kWh', 'step' => '0.01', 'min' => 0],
            'usable_capacity_kwh' => ['label' => 'Usable Energy', 'input_type' => 'number', 'group' => 'battery', 'unit' => 'kWh', 'step' => '0.01', 'min' => 0],
            'round_trip_efficiency_percent' => ['label' => 'Round-trip Efficiency', 'input_type' => 'number', 'group' => 'performance', 'unit' => '%', 'step' => '0.01', 'min' => 0, 'max' => 100],
            'cycle_life' => ['label' => 'Cycle Life', 'input_type' => 'number', 'group' => 'performance', 'unit' => 'cycles', 'step' => '1', 'min' => 0],
            'spd_type' => ['label' => 'SPD Classification', 'group' => 'protection', 'placeholder' => 'Example: Type 2 / Class II'],
            'response_time_ns' => ['label' => 'Surge Response Time', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'ns', 'step' => '0.01', 'min' => 0],
            'short_circuit_withstand_ka' => ['label' => 'PV Short-circuit Current Rating (Iscpv)', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'kA', 'step' => '0.01', 'min' => 0],
            'discharge_time_s' => ['label' => 'Discharge Time', 'input_type' => 'number', 'group' => 'performance', 'unit' => 's', 'step' => '0.01', 'min' => 0],
            'bearing_capacity_kpa' => ['label' => 'Allowable Soil Bearing Capacity', 'input_type' => 'number', 'group' => 'mechanical', 'unit' => 'kPa', 'step' => '0.01', 'min' => 0],
            'connection_capacity_kva' => ['label' => 'Utility Connection Capacity', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'kVA', 'step' => '0.01', 'min' => 0],
            'spare_capacity_percent' => ['label' => 'Electrical Spare Capacity', 'input_type' => 'number', 'group' => 'electrical', 'unit' => '%', 'step' => '0.01', 'min' => 0, 'max' => 100],
            'response_time_hours' => ['label' => 'Service Response Time', 'input_type' => 'number', 'group' => 'service', 'unit' => 'hours', 'step' => '0.01', 'min' => 0],
            'export_limit_kw' => ['label' => 'Export Limit', 'input_type' => 'number', 'group' => 'control', 'unit' => 'kW', 'step' => '0.01', 'min' => 0],
            'grid_support_function' => ['label' => 'Grid Support Functions', 'input_type' => 'text', 'group' => 'control', 'unit' => null],
            'battery_chemistry_support' => ['label' => 'Supported Battery Chemistries', 'input_type' => 'text', 'group' => 'compatibility', 'unit' => null],
            'max_system_voltage' => ['label' => 'Maximum System Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '1'],
            'max_dc_voltage' => ['label' => 'Maximum DC Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '1'],
            'startup_voltage' => ['label' => 'Startup Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '0.01'],
            'mppt_voltage_min' => ['label' => 'MPPT Voltage Minimum', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '0.01'],
            'mppt_voltage_max' => ['label' => 'MPPT Voltage Maximum', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '0.01'],
            'max_input_current_per_mppt' => ['label' => 'Maximum Input Current per MPPT', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'A', 'step' => '0.01'],
            'max_short_circuit_current_per_mppt' => ['label' => 'Maximum Short-circuit Current per MPPT', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'A', 'step' => '0.01'],
            'rated_ac_voltage' => ['label' => 'Rated AC Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '0.01'],
            'max_input_voltage' => ['label' => 'Maximum Input Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '0.01'],
            'rated_voltage' => ['label' => 'Rated Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '0.01'],
            'rated_current' => ['label' => 'Rated Current', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'A', 'step' => '0.01'],
            'uc_voltage' => ['label' => 'Maximum Continuous Operating Voltage (Uc)', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'V', 'step' => '0.01'],
            'up_voltage_kv' => ['label' => 'Voltage Protection Level (Up)', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'kV', 'step' => '0.01'],
            'in_current' => ['label' => 'Nominal Discharge Current (In)', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'kA', 'step' => '0.01'],
            'imax_current' => ['label' => 'Maximum Discharge Current (Imax)', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'kA', 'step' => '0.01'],
            'primary_voltage_kv' => ['label' => 'Primary Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'kV', 'step' => '0.01'],
            'power_supply_voltage' => ['label' => 'Power Supply Voltage', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'V', 'step' => '0.01'],
            'warranty_claim_process' => ['label' => 'Warranty Claim Process', 'input_type' => 'textarea', 'group' => 'service', 'unit' => null],
            'conductor_size_mm2' => ['label' => 'Conductor Cross-section', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'mm²', 'step' => '0.01', 'min' => 0],
            'ballast_weight_kg_per_module' => ['label' => 'Ballast Weight per Module', 'input_type' => 'number', 'group' => 'mechanical', 'unit' => 'kg/module', 'step' => '0.01', 'min' => 0],
            'lifeline_rating_kn' => ['label' => 'Lifeline Rating', 'input_type' => 'number', 'group' => 'safety', 'unit' => 'kN', 'step' => '0.01', 'min' => 0],
            'load_loss_w' => ['label' => 'Load Loss', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'W', 'step' => '0.01', 'min' => 0],
            'no_load_loss_w' => ['label' => 'No-load Loss', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'W', 'step' => '0.01', 'min' => 0],
            'minimum_stock_quantity' => ['label' => 'Minimum Stock Quantity', 'input_type' => 'number', 'group' => 'commercial', 'unit' => 'units', 'step' => '1', 'min' => 0],
            'max_units_per_branch' => ['label' => 'Maximum Units per Branch', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'units/branch', 'step' => '1', 'min' => 1],
            'mppt_count' => ['label' => 'MPPT Count', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'MPPTs', 'step' => '1', 'min' => 1],
            'strings_per_mppt' => ['label' => 'Strings per MPPT', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'strings/MPPT', 'step' => '1', 'min' => 1],
            'input_channel_count' => ['label' => 'Input Channel Count', 'input_type' => 'number', 'group' => 'architecture', 'unit' => 'channels', 'step' => '1', 'min' => 1],
            'dc_input_count' => ['label' => 'DC Input Count', 'input_type' => 'number', 'group' => 'architecture', 'unit' => 'inputs', 'step' => '1', 'min' => 1],
            'source_count' => ['label' => 'Source Count', 'input_type' => 'number', 'group' => 'architecture', 'unit' => 'sources', 'step' => '1', 'min' => 1],
            'string_input_count' => ['label' => 'String Input Count', 'input_type' => 'number', 'group' => 'architecture', 'unit' => 'strings', 'step' => '1', 'min' => 1],
            'module_count' => ['label' => 'Module Count', 'input_type' => 'number', 'group' => 'architecture', 'unit' => 'modules', 'step' => '1', 'min' => 1],
            'supported_cell_count' => ['label' => 'Supported Cell Count', 'input_type' => 'number', 'group' => 'architecture', 'unit' => 'cells', 'step' => '1', 'min' => 1],
            'core_count' => ['label' => 'Core Count', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'cores', 'step' => '1', 'min' => 1],
            'pole_count' => ['label' => 'Pole Count', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'poles', 'step' => '1', 'min' => 1],
            'poles' => ['label' => 'Pole Count', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'poles', 'step' => '1', 'min' => 1],
            'outgoing_breaker_count' => ['label' => 'Outgoing Breaker Count', 'input_type' => 'number', 'group' => 'protection', 'unit' => 'circuits', 'step' => '1', 'min' => 0],
            'critical_circuit_count' => ['label' => 'Critical Circuit Count', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'circuits', 'step' => '1', 'min' => 1],
            'terminal_count' => ['label' => 'Terminal Count', 'input_type' => 'number', 'group' => 'electrical', 'unit' => 'terminals', 'step' => '1', 'min' => 1],
            'cable_entry_count' => ['label' => 'Cable Entry Count', 'input_type' => 'number', 'group' => 'installation', 'unit' => 'entries', 'step' => '1', 'min' => 0],
            'duct_count' => ['label' => 'Duct Count', 'input_type' => 'number', 'group' => 'installation', 'unit' => 'ducts', 'step' => '1', 'min' => 1],
            'ethernet_port_count' => ['label' => 'Ethernet Port Count', 'input_type' => 'number', 'group' => 'communication', 'unit' => 'ports', 'step' => '1', 'min' => 0],
            'lock_count' => ['label' => 'Lock Count', 'input_type' => 'number', 'group' => 'safety', 'unit' => 'locks', 'step' => '1', 'min' => 1],
            'parallel_limit' => ['label' => 'Maximum Parallel Units', 'input_type' => 'number', 'group' => 'battery', 'unit' => 'units', 'step' => '1', 'min' => 1],
            'volume' => ['label' => 'Quantity / Volume', 'input_type' => 'number', 'group' => 'execution', 'unit' => null, 'step' => '0.01', 'min' => 0],
            'replacement_unit_available' => ['label' => 'Replacement Unit Available', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'maintenance', 'unit' => null],
            'mechanical_interlock' => ['label' => 'Mechanical Interlock', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'control', 'unit' => null],
            'auxiliary_contact' => ['label' => 'Auxiliary Contact Available', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'monitoring', 'unit' => null],
            'sync_check_relay' => ['label' => 'Sync-check Relay Included', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'control', 'unit' => null],
            'uv_resistance' => ['label' => 'UV Resistance', 'input_type' => 'text', 'group' => 'environmental', 'unit' => null],
            'rs485_protocol' => ['label' => 'RS-485 Protocol', 'input_type' => 'text', 'group' => 'communication', 'unit' => null],
            'can_profile' => ['label' => 'CAN Profile', 'input_type' => 'text', 'group' => 'communication', 'unit' => null],
            'ppe_category' => ['label' => 'PPE Category', 'input_type' => 'text', 'group' => 'safety', 'unit' => null],
            'rack_unit_height' => ['label' => 'Rack Unit Height', 'input_type' => 'number', 'group' => 'mechanical', 'unit' => 'U', 'step' => '1', 'min' => 0],
            'rail_length' => ['label' => 'Rail Length', 'input_type' => 'number', 'group' => 'mechanical', 'unit' => 'mm', 'step' => '1', 'min' => 0],
            'wind_speed_rating' => ['label' => 'Wind Speed Rating', 'input_type' => 'number', 'group' => 'mechanical', 'unit' => 'm/s', 'step' => '0.01', 'min' => 0],
            'manpower' => ['label' => 'Manpower', 'input_type' => 'number', 'group' => 'execution', 'unit' => 'persons', 'step' => '1', 'min' => 0],
            'logging_interval' => ['label' => 'Logging Interval', 'input_type' => 'number', 'group' => 'monitoring', 'unit' => 'minutes', 'step' => '1', 'min' => 1],
            'bifacial' => ['label' => 'Bifacial Module', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'architecture', 'unit' => null],
            'anti_islanding' => ['label' => 'Anti-islanding Protection', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'protection', 'unit' => null],
            'genset_support' => ['label' => 'Genset Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'compatibility', 'unit' => null],
            'fire_safety_required' => ['label' => 'Dedicated Fire Safety Required', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'safety', 'unit' => null],
            'roof_penetration' => ['label' => 'Roof Penetration Required', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'installation', 'unit' => null],
            'modbus_support' => ['label' => 'Modbus Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'communication', 'unit' => null],
            'can_support' => ['label' => 'CAN Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'communication', 'unit' => null],
            'mqtt_support' => ['label' => 'MQTT Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'communication', 'unit' => null],
            'rapid_shutdown_support' => ['label' => 'Rapid Shutdown Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'protection', 'unit' => null],
            'transformer_required' => ['label' => 'Transformer Required', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'compatibility', 'unit' => null],
            'pcs_included' => ['label' => 'PCS Included', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'architecture', 'unit' => null],
            'monitoring_support' => ['label' => 'String Monitoring Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'monitoring', 'unit' => null],
            'remote_contact' => ['label' => 'Remote Status Contact', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'monitoring', 'unit' => null],
            'replaceable_cartridge' => ['label' => 'Replaceable Cartridge', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'maintenance', 'unit' => null],
            'visual_indicator' => ['label' => 'Visual Status Indicator', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'monitoring', 'unit' => null],
            'metering' => ['label' => 'Integrated Metering', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'monitoring', 'unit' => null],
            'export_control_support' => ['label' => 'Export Control Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'control', 'unit' => null],
            'wifi_support' => ['label' => 'Wi-Fi Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'communication', 'unit' => null],
            'sim_support' => ['label' => 'SIM / Cellular Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'communication', 'unit' => null],
            'vpn_support' => ['label' => 'VPN Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'communication', 'unit' => null],
            'poe_support' => ['label' => 'PoE Support', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'communication', 'unit' => null],
            'ventilation_required' => ['label' => 'Ventilation Required', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'thermal', 'unit' => null],
            'equipment_required' => ['label' => 'Equipment Required', 'input_type' => 'textarea', 'group' => 'execution', 'unit' => null],
            'warning_tape_required' => ['label' => 'Warning Tape Required', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'safety', 'unit' => null],
            'insurance_included' => ['label' => 'Insurance Included', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'commercial', 'unit' => null],
            'tax_included' => ['label' => 'Tax Included', 'input_type' => 'select', 'options' => $yesNo, 'group' => 'commercial', 'unit' => null],
            'phase_type' => ['label' => 'Electrical Phase', 'input_type' => 'select', 'options' => ['single_phase' => 'Single Phase', 'three_phase' => 'Three Phase', 'split_phase' => 'Split Phase', 'dc' => 'DC'], 'group' => 'electrical', 'unit' => null],
            'battery_chemistry' => ['label' => 'Battery Chemistry', 'input_type' => 'select', 'options' => ['lfp' => 'Lithium Iron Phosphate (LFP)', 'nmc' => 'Lithium NMC', 'lead_acid' => 'Lead Acid', 'gel' => 'Gel', 'agm' => 'AGM', 'other' => 'Other'], 'group' => 'battery', 'unit' => null],
            'availability_status' => ['label' => 'Availability Status', 'input_type' => 'select', 'options' => ['available' => 'Available', 'limited' => 'Limited', 'backorder' => 'Backorder', 'unavailable' => 'Unavailable', 'discontinued' => 'Discontinued'], 'group' => 'commercial', 'unit' => null],
            'valid_until' => ['label' => 'Valid Until', 'input_type' => 'date', 'group' => 'commercial', 'unit' => null],
            'effective_from' => ['label' => 'Effective From', 'input_type' => 'date', 'group' => 'commercial', 'unit' => null],
            'service_scope' => ['label' => 'Service Scope', 'input_type' => 'textarea', 'group' => 'service', 'unit' => null],
            'deliverable' => ['label' => 'Deliverable', 'input_type' => 'textarea', 'group' => 'service', 'unit' => null],
            'required_document_pack' => ['label' => 'Required Document Pack', 'input_type' => 'textarea', 'group' => 'compliance', 'unit' => null],
            'compatible_inverter_series' => ['label' => 'Compatible Inverter Series', 'input_type' => 'textarea', 'group' => 'compatibility', 'unit' => null],
            'application_area' => ['label' => 'Application Area', 'input_type' => 'textarea', 'group' => 'application', 'unit' => null],
            'coverage_area' => ['label' => 'Coverage Area', 'input_type' => 'textarea', 'group' => 'application', 'unit' => null],
            'response_time_s' => ['label' => 'Response Time', 'input_type' => 'number', 'unit' => 's', 'step' => '0.01', 'min' => 0],
            'application_type' => ['label' => 'Utility Application Type', 'input_type' => 'text', 'group' => 'service', 'unit' => null],
            'utility_name' => ['label' => 'Utility / Grid Operator', 'input_type' => 'text', 'group' => 'service', 'unit' => null],
            'language' => ['label' => 'Label Language', 'input_type' => 'text', 'group' => 'compliance', 'unit' => null],
            'unit' => ['label' => 'Measurement Unit', 'input_type' => 'text', 'group' => 'execution', 'unit' => null],
            'device_capacity' => ['label' => 'Supported Device Capacity', 'input_type' => 'number', 'group' => 'monitoring', 'unit' => 'devices', 'step' => '1', 'min' => 1],
            'shipment_weight_kg' => ['label' => 'Shipment Weight', 'input_type' => 'number', 'group' => 'service', 'unit' => 'kg', 'step' => '0.01', 'min' => 0],
            'corrosion_class' => ['label' => 'Corrosion Class', 'input_type' => 'text', 'group' => 'environmental', 'unit' => null],
            'corrosion_resistance' => ['label' => 'Corrosion Resistance', 'input_type' => 'text', 'group' => 'environmental', 'unit' => null],
            'extinguishing_agent' => ['label' => 'Extinguishing Agent', 'input_type' => 'text', 'group' => 'safety', 'unit' => null],
            'capacity' => ['label' => 'Agent Capacity', 'input_type' => 'number', 'group' => 'safety', 'unit' => 'kg', 'step' => '0.01', 'min' => 0],
            'agent_quantity_kg' => ['label' => 'Agent Quantity', 'input_type' => 'number', 'group' => 'safety', 'unit' => 'kg', 'step' => '0.01', 'min' => 0],
            'validity_period' => ['label' => 'Certificate / Permit Validity', 'input_type' => 'text', 'group' => 'compliance', 'unit' => null],
            'operating_temperature' => ['label' => 'Operating Temperature Range', 'input_type' => 'text', 'group' => 'thermal', 'unit' => '°C', 'placeholder' => 'Example: -20 to 55'],
            'temperature_rating' => ['label' => 'Temperature Rating Range', 'input_type' => 'text', 'group' => 'thermal', 'unit' => '°C', 'placeholder' => 'Example: -40 to 90'],
            'measurement_range' => ['label' => 'Measurement Range', 'input_type' => 'text', 'group' => 'measurement', 'unit' => null],
            'dimensions_mm' => ['label' => 'Overall Dimensions', 'input_type' => 'text', 'group' => 'mechanical', 'unit' => 'mm'],
            'battery_voltage_range' => ['label' => 'Battery Voltage Range', 'input_type' => 'text', 'group' => 'battery', 'unit' => 'V'],
            'dc_voltage_range' => ['label' => 'DC Voltage Range', 'input_type' => 'text', 'group' => 'electrical', 'unit' => 'V'],
            'output_voltage_range' => ['label' => 'Output Voltage Range', 'input_type' => 'text', 'group' => 'electrical', 'unit' => 'V'],
            'adjustable_trip_range' => ['label' => 'Adjustable Trip Range', 'input_type' => 'text', 'group' => 'protection', 'unit' => 'A'],
            'tilt_range' => ['label' => 'Tilt Range', 'input_type' => 'text', 'group' => 'mechanical', 'unit' => '°'],
            'power_factor_range' => ['label' => 'Power Factor Range', 'input_type' => 'text', 'group' => 'electrical', 'unit' => null],
            'grid_code_profile' => ['label' => 'Grid Code Profile', 'input_type' => 'text', 'group' => 'compliance', 'unit' => null],
            'applicable_standard' => ['label' => 'Applicable Standard', 'input_type' => 'text', 'group' => 'compliance', 'unit' => null],
            'standard' => ['label' => 'Applicable Standard', 'input_type' => 'text', 'group' => 'compliance', 'unit' => null],
        ];
    }

    private static function categoryCode(Material|MaterialCategory|string|null $subject): string
    {
        if ($subject instanceof Material) {
            $subject->loadMissing('category');
            $subject = $subject->category;
        }

        if ($subject instanceof MaterialCategory) {
            return strtoupper((string) $subject->code);
        }

        return strtoupper((string) $subject);
    }

    private static function inferInputType(string $key): string
    {
        if (str_ends_with($key, '_required') || str_ends_with($key, '_support') || str_ends_with($key, '_included')) {
            return 'select';
        }

        if (preg_match('/(_days|_count|_months|_years|_hours|_minutes|_ms|_ns)$/', $key)) {
            return 'number';
        }

        if (preg_match('/(_kw|_kwp|_kva|_kwh|_wp|_v|_a|_ka|_mm|_m|_m2|_m3|_kg|_kg_m|_kg_m2|_pa|_kpa|_bar|_hz|_db|_ohm|_percent|_mbps|_nm|_s)$/', $key)) {
            return 'number';
        }

        if (in_array($key, ['voc', 'vmp', 'isc', 'imp', 'mppt_count', 'strings_per_mppt', 'cycle_life', 'parallel_limit', 'pole_count', 'poles', 'capacity_ah', 'c_rate', 'unit_cost', 'selling_price', 'minimum_order_quantity', 'lead_time_days'], true)) {
            return 'number';
        }

        return str_contains($key, 'scope') || str_contains($key, 'deliverable') || str_contains($key, 'condition') ? 'textarea' : 'text';
    }

    private static function inferGroup(string $key): string
    {
        return match (true) {
            preg_match('/warranty|degradation|cycle_life|validity/', $key) === 1 => 'warranty',
            preg_match('/certificate|standard|grid_code|authority|permit|compliance/', $key) === 1 => 'compliance',
            preg_match('/price|cost|currency|payment|delivery|moq|minimum_order|availability|valid_until|effective|discount|tax|supplier/', $key) === 1 => 'commercial',
            preg_match('/service|work_|manpower|working_days|processing|responsible|survey|inspection|assessment|deliverable/', $key) === 1 => 'service',
            preg_match('/accuracy|measurement|measured|sensor|calibration|ct_ratio|spectral_range/', $key) === 1 => 'measurement',
            preg_match('/communication|protocol|modbus|can_|mqtt|ethernet|wifi|sim_|vpn|cloud|logging|data_|_port|port_count|network|rs485/', $key) === 1 => 'communication',
            preg_match('/transfer|controller|synchron|interlock|load_sharing|load_shedding|export_control|grid_support/', $key) === 1 => 'control',
            preg_match('/fire|safety|anti_islanding|rapid_shutdown|short_circuit|breaking|protection|trip|spd|fuse|loto|ppe|earth_leakage/', $key) === 1 => 'protection',
            preg_match('/temperature|temp_coeff|thermal|cooling|ventilation/', $key) === 1 => 'thermal',
            preg_match('/efficiency|bifaciality|degradation|performance|cycle_life|discharge_time/', $key) === 1 => 'performance',
            preg_match('/battery_|bms|capacity_ah|capacity_kwh|gross_capacity|usable_capacity|dod|charge_current|discharge_current|c_rate|parallel_limit|nominal_battery/', $key) === 1 => 'battery',
            preg_match('/length|width|weight|dimension|rail|foundation|roof|tilt|orientation|corrosion|load_|span|height|clearance|diameter|size|wind|snow|ballast|walkway/', $key) === 1 => 'mechanical',
            preg_match('/mounting|installation|route|trench|duct|penetration|support_spacing|cable_entry/', $key) === 1 => 'installation',
            preg_match('/material|conductor|insulation|enclosure|frame|glass|busbar|concrete|reinforcement|armour|finish/', $key) === 1 => 'construction',
            preg_match('/compatible|compatibility|genset|transformer_required|terrain|substrate/', $key) === 1 => 'compatibility',
            preg_match('/coverage|application_area/', $key) === 1 => 'application',
            preg_match('/voltage|current|power|mppt|phase|frequency|voc|vmp|isc|imp|transformer|impedance/', $key) === 1 => 'electrical',
            preg_match('/maintenance|inspection_interval|replaceable|service_life|firmware|spare_part/', $key) === 1 => 'maintenance',
            preg_match('/type|technology|cell_count|module_count|source_count|input_count/', $key) === 1 => 'architecture',
            default => 'general',
        };
    }

    private static function inferStep(string $key): string
    {
        return preg_match('/(_count|_days|_months|_years|_hours|mppt_count|cycle_life|parallel_limit|poles|pole_count)$/', $key) ? '1' : '0.01';
    }

    private static function inferMin(string $key): int|float|null
    {
        if (str_contains($key, 'temp_coeff')) {
            return -10;
        }

        return 0;
    }

    private static function inferMax(string $key): int|float|null
    {
        return match (true) {
            str_ends_with($key, '_percent') => 100,
            str_contains($key, 'warranty') => 100,
            str_contains($key, 'efficiency') => 100,
            default => null,
        };
    }

    private static function isFilterable(string $key): bool
    {
        return preg_match('/power|capacity|voltage|current|phase|chemistry|protocol|ip_rating|mounting|material|standard|availability|efficiency|warranty/', $key) === 1;
    }

    private static function descriptionFor(string $key, ?string $resolvedGroup = null, ?string $resolvedLabel = null): string
    {
        $label = $resolvedLabel ?? MaterialCatalogStandards::labelFor($key);
        $group = $resolvedGroup ?? self::fieldGroupOverrides()[$key] ?? self::inferGroup($key);

        return match ($group) {
            'architecture' => $label.' defines the component technology, construction, or functional architecture.',
            'construction' => $label.' records the material, enclosure, conductor, or construction configuration.',
            'electrical' => $label.' used for electrical compatibility, sizing, and project design checks.',
            'battery' => $label.' used for storage sizing, BMS integration, and operating-limit checks.',
            'performance' => $label.' records conversion, energy, response, degradation, or lifecycle performance.',
            'thermal' => $label.' records temperature behavior, cooling, and thermal operating limits.',
            'mechanical' => $label.' used for layout, structure, installation, and physical compatibility.',
            'environmental' => $label.' used for site suitability, ingress protection, weather, and operating-condition review.',
            'measurement' => $label.' records measurement range, accuracy, calibration, or sensor response.',
            'monitoring' => $label.' records telemetry, status, metering, logging, and operational visibility.',
            'control' => $label.' records transfer, synchronization, automation, export, or load-control behavior.',
            'communication' => $label.' used for protocol, networking, remote integration, and data exchange.',
            'installation' => $label.' records installation method, routing, location, access, or termination needs.',
            'protection' => $label.' used for protective-device duty, surge, trip, isolation, and coordination checks.',
            'safety' => $label.' used for fire, personnel, site, and hazard-control governance.',
            'compatibility' => $label.' records approved equipment, site, voltage, protocol, or architecture compatibility.',
            'application' => $label.' defines the intended site, coverage, or use-case boundary.',
            'commercial' => $label.' used for supplier, pricing, validity, and procurement governance.',
            'service' => $label.' used for scope, service level, responsibility, and deliverable planning.',
            'execution' => $label.' records manpower, equipment, and execution resource requirements.',
            'maintenance' => $label.' records inspection, replacement, firmware, and maintainability requirements.',
            'compliance' => $label.' used for applicable standard, authority, and evidence traceability.',
            'warranty' => $label.' records warranty, validity, and lifecycle commitments.',
            default => $label.' recorded as a governed category-specific technical attribute.',
        };
    }

    private static function placeholderFor(string $key, ?string $unit): string
    {
        return $unit ? 'Enter value in '.$unit : 'Enter '.strtolower(MaterialCatalogStandards::labelFor($key));
    }

    /** @return array<int, string> */
    private static function groupOrder(): array
    {
        return ['general', 'architecture', 'construction', 'electrical', 'battery', 'performance', 'thermal', 'mechanical', 'measurement', 'monitoring', 'control', 'communication', 'environmental', 'installation', 'protection', 'safety', 'compatibility', 'application', 'service', 'execution', 'maintenance', 'compliance', 'warranty', 'commercial'];
    }

    /** @return array<string, string> */
    private static function groupLabels(): array
    {
        return [
            'general' => 'General Technical Identity',
            'architecture' => 'Product Architecture',
            'construction' => 'Materials & Construction',
            'electrical' => 'Electrical Ratings',
            'battery' => 'Battery & Energy Storage',
            'performance' => 'Performance & Lifecycle',
            'thermal' => 'Thermal Characteristics',
            'mechanical' => 'Mechanical & Structural',
            'measurement' => 'Measurement & Accuracy',
            'communication' => 'Communication & Data',
            'monitoring' => 'Monitoring & Instrumentation',
            'control' => 'Control & Automation',
            'environmental' => 'Environmental Limits',
            'installation' => 'Installation Requirements',
            'protection' => 'Protection Ratings',
            'safety' => 'Safety & Hazard Controls',
            'compatibility' => 'Compatibility',
            'application' => 'Application Scope',
            'service' => 'Service & Deliverables',
            'execution' => 'Execution Resources',
            'maintenance' => 'Maintenance',
            'compliance' => 'Compliance & Standards',
            'warranty' => 'Warranty & Lifecycle',
            'commercial' => 'Commercial Reference',
        ];
    }

    /** @return array<string, string> */
    private static function groupDescriptions(): array
    {
        return [
            'general' => 'Core technical identity used to distinguish this component from neighboring catalog types.',
            'architecture' => 'Technology family, internal architecture, topology, and component configuration.',
            'construction' => 'Material, conductor, enclosure, insulation, coating, and construction details.',
            'electrical' => 'Ratings used for sizing, compatibility, cable, and design decisions.',
            'battery' => 'Energy, current, chemistry, BMS, and operating limits for storage integration.',
            'performance' => 'Efficiency, degradation, response, lifecycle, and functional performance values.',
            'thermal' => 'Temperature coefficients, cooling method, and thermal operating limits.',
            'mechanical' => 'Physical dimensions, loads, and structural or mounting compatibility.',
            'measurement' => 'Range, accuracy, response, and calibration data for monitoring or instruments.',
            'communication' => 'Protocols, ports, data logging, networking, and remote integration.',
            'monitoring' => 'Metering, sensing, status, telemetry, and operational visibility.',
            'control' => 'Automation, export control, synchronization, and control-system behavior.',
            'environmental' => 'Ingress protection, temperature, weather, corrosion, and site conditions.',
            'installation' => 'Installation method, routing, penetration, foundation, and commissioning needs.',
            'protection' => 'Protection class, discharge capability, breaking duty, and coordination limits.',
            'safety' => 'Fire protection, personnel safety, hazard controls, and emergency-response requirements.',
            'compatibility' => 'Approved equipment, protocol, voltage, or architecture compatibility.',
            'application' => 'Intended project, site, coverage, or use-case boundaries.',
            'service' => 'Service scope, execution duration, deliverables, and responsible party.',
            'execution' => 'Resources, manpower, equipment, and site-execution requirements.',
            'maintenance' => 'Inspection, replacement, service interval, and maintainability information.',
            'compliance' => 'Standards, authorities, permits, certificates, and technical evidence.',
            'warranty' => 'Warranty, degradation, validity, service life, and lifecycle commitments.',
            'commercial' => 'Supplier, price, validity, delivery, payment, and purchasing conditions.',
        ];
    }

    /** @return array<string, mixed> */
    private static function defaultSchema(): array
    {
        $keys = ['rated_power_kw', 'rated_voltage', 'rated_current', 'ip_rating', 'warranty_years'];
        $fields = collect($keys)->map(function (string $key): array {
            $field = self::definition($key);
            $field['required'] = false;

            return $field;
        })->groupBy('group')->map(fn ($items, $group): array => [
            'key' => $group,
            'label' => self::groupLabels()[$group] ?? 'General',
            'description' => self::groupDescriptions()[$group] ?? 'General technical attributes.',
            'fields' => $items->values()->all(),
        ])->values()->all();

        return [
            'code' => 'DEFAULT',
            'root_code' => 'DEFAULT',
            'title' => 'General Material',
            'description' => 'General governed technical profile. Select a professional subcategory for a complete specific schema.',
            'required_specs' => [],
            'optional_specs' => $keys,
            'required_certificates' => [],
            'required_documents' => ['datasheet'],
            'groups' => $fields,
            'field_count' => count($keys),
            'required_count' => 0,
            'optional_count' => count($keys),
            'conditional_count' => 0,
        ];
    }
}
