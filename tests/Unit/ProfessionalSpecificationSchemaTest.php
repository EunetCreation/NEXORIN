<?php

namespace Tests\Unit;

use App\Support\ProfessionalCatalogProfile;
use App\Support\ProfessionalSpecificationSchema;
use Tests\TestCase;

class ProfessionalSpecificationSchemaTest extends TestCase
{
    public function test_every_professional_domain_and_subcategory_has_an_exact_schema(): void
    {
        $expectedCodes = collect(ProfessionalCatalogProfile::profiles())
            ->flatMap(function (array $profile, string $rootCode): array {
                return array_merge([$rootCode], array_keys($profile['subcategories'] ?? []));
            })
            ->values()
            ->unique()
            ->all();

        $payload = ProfessionalSpecificationSchema::payload();

        foreach ($expectedCodes as $code) {
            $this->assertArrayHasKey($code, $payload, "Missing professional technical schema for {$code}.");
            $this->assertNotEmpty($payload[$code]['groups'] ?? [], "Schema {$code} has no grouped fields.");
            $this->assertNotEmpty(
                array_merge($payload[$code]['required_specs'] ?? [], $payload[$code]['optional_specs'] ?? []),
                "Schema {$code} has no technical fields."
            );
        }
    }

    public function test_representative_component_schemas_expose_component_specific_fields(): void
    {
        $expectations = [
            'PV_BIFACIAL' => ['voc', 'vmp', 'bifaciality_percent', 'rear_side_power_gain_percent'],
            'INV_MICRO' => ['input_channel_count', 'max_units_per_branch', 'rapid_shutdown_support'],
            'INV_HYBRID' => ['battery_voltage_range', 'bms_protocol', 'backup_output_kw'],
            'BAT_BMS' => ['supported_cell_count', 'balancing_current_a', 'can_profile'],
            'DC_CABLE_CONNECTOR' => ['conductor_size_mm2', 'uv_resistance', 'minimum_bending_radius_mm'],
            'DC_SPD' => ['uc_voltage', 'up_voltage_kv', 'imax_current'],
            'AC_CABLE' => ['core_count', 'armour_type', 'installation_method'],
            'AC_PROTECTION' => ['breaking_capacity_ka', 'trip_unit_type', 'earth_leakage_protection'],
            'AC_TRANSFER_SYNC' => ['source_count', 'transfer_time_ms', 'sync_check_relay'],
            'AC_TRANSFORMER_MV' => ['rated_power_kva', 'vector_group', 'impedance_percent'],
            'MOUNT_ROOF' => ['roof_compatibility', 'clamp_range_mm', 'module_frame_compatibility'],
            'MON_METER' => ['ct_ratio', 'accuracy_class', 'export_control_support'],
            'GROUNDING_SYSTEM' => ['target_resistance_ohm', 'electrode_diameter_mm', 'inspection_pit_required'],
            'PANEL_ACDB' => ['short_circuit_rating_ka', 'incoming_breaker', 'earth_leakage_protection'],
            'CIVIL_SURVEY' => ['survey_instrument', 'accuracy_requirement', 'deliverable'],
            'FIRE_DETECTION' => ['detection_type', 'alarm_output', 'response_time_s'],
            'LEGAL_UTILITY' => ['application_type', 'connection_capacity_kva', 'meter_change_required'],
            'OM_SERVICE' => ['response_time_hours', 'spare_parts_included', 'remote_monitoring_included'],
            'QUOTE_MATERIAL' => ['unit_cost', 'minimum_order_quantity', 'valid_until'],
        ];

        foreach ($expectations as $code => $expectedKeys) {
            $keys = array_merge(
                ProfessionalSpecificationSchema::requiredKeysFor($code),
                ProfessionalSpecificationSchema::optionalKeysFor($code)
            );

            foreach ($expectedKeys as $key) {
                $this->assertContains($key, $keys, "Schema {$code} is missing {$key}.");
            }
        }
    }


    public function test_subcategory_string_resolves_parent_evidence_and_legacy_core_mapping(): void
    {
        $pv = ProfessionalSpecificationSchema::for('PV_MONO_PERC');

        $this->assertSame('PV_MODULE', $pv['root_code']);
        $this->assertContains('IEC 61215', $pv['required_certificates']);
        $this->assertContains('IEC 61730', $pv['required_certificates']);
        $this->assertContains('warranty', $pv['required_documents']);

        $core = ProfessionalSpecificationSchema::coreColumnValues([
            'module_power_wp' => '550',
            'vmp' => '41.8',
            'imp' => '13.16',
            'product_warranty_years' => '12',
        ], 'PV_MONO_PERC');

        $this->assertSame('550', $core['nominal_power_wp']);
        $this->assertSame('41.8', $core['voltage_v']);
        $this->assertSame('13.16', $core['current_a']);
        $this->assertSame('12', $core['warranty_years']);
    }

    public function test_critical_engineering_fields_use_precise_input_types_and_units(): void
    {
        $expectations = [
            'temp_coeff_voc' => ['number', '%/°C'],
            'temp_coeff_pmax' => ['number', '%/°C'],
            'conductor_size_mm2' => ['number', 'mm²'],
            'in_current' => ['number', 'kA'],
            'imax_current' => ['number', 'kA'],
            'ballast_weight_kg_per_module' => ['number', 'kg/module'],
            'lifeline_rating_kn' => ['number', 'kN'],
            'wind_speed_rating' => ['number', 'm/s'],
            'capacity' => ['number', 'kg'],
        ];

        foreach ($expectations as $key => [$type, $unit]) {
            $definition = ProfessionalSpecificationSchema::definition($key);
            $this->assertSame($type, $definition['input_type'], "Unexpected input type for {$key}.");
            $this->assertSame($unit, $definition['unit'], "Unexpected unit for {$key}.");
        }
    }

    public function test_schema_field_keys_are_unique_and_select_fields_have_options(): void
    {
        foreach (ProfessionalSpecificationSchema::payload() as $code => $schema) {
            $fields = collect($schema['groups'] ?? [])->flatMap(fn (array $group): array => $group['fields'] ?? []);
            $keys = $fields->pluck('key')->all();

            $this->assertSame(count($keys), count(array_unique($keys)), "Schema {$code} contains duplicate field keys.");

            foreach ($fields as $field) {
                if (($field['input_type'] ?? null) === 'select') {
                    $this->assertNotEmpty($field['options'] ?? [], "Select field {$field['key']} in {$code} has no options.");
                }
            }
        }
    }

    public function test_all_exact_subcategories_use_known_semantic_groups_without_uncategorized_fields(): void
    {
        $knownGroups = [
            'architecture', 'construction', 'electrical', 'battery', 'performance', 'thermal',
            'mechanical', 'measurement', 'monitoring', 'control', 'communication', 'environmental',
            'installation', 'protection', 'safety', 'compatibility', 'application', 'service',
            'execution', 'maintenance', 'compliance', 'warranty', 'commercial',
        ];

        $subcategoryCodes = collect(ProfessionalCatalogProfile::profiles())
            ->flatMap(fn (array $profile): array => array_keys($profile['subcategories'] ?? []))
            ->values()
            ->all();

        $this->assertCount(70, $subcategoryCodes);

        foreach ($subcategoryCodes as $code) {
            $schema = ProfessionalSpecificationSchema::for($code);
            $fields = collect($schema['groups'])->flatMap(fn (array $group): array => $group['fields']);

            foreach ($fields as $field) {
                $this->assertContains($field['group'], $knownGroups, "Unknown semantic group for {$code}.{$field['key']}.");
                $this->assertNotSame('general', $field['group'], "Uncategorized field remains in {$code}: {$field['key']}.");
            }
        }
    }

    public function test_schema_metadata_counts_and_group_containers_remain_consistent(): void
    {
        foreach (ProfessionalSpecificationSchema::payload() as $code => $schema) {
            $fields = collect($schema['groups'] ?? [])->flatMap(function (array $group) use ($code): array {
                foreach ($group['fields'] ?? [] as $field) {
                    $this->assertSame($group['key'], $field['group'], "Field {$code}.{$field['key']} is rendered in the wrong group container.");
                    $this->assertNotEmpty($field['label'] ?? null, "Field {$code}.{$field['key']} is missing a label.");
                    $this->assertNotEmpty($field['description'] ?? null, "Field {$code}.{$field['key']} is missing a description.");
                }

                return $group['fields'] ?? [];
            });

            $required = array_values($schema['required_specs'] ?? []);
            $optional = array_values($schema['optional_specs'] ?? []);
            $conditional = array_values($schema['conditional_specs'] ?? []);

            $this->assertSame([], array_values(array_intersect($required, $conditional)), "Schema {$code} overlaps required and conditional fields.");
            $this->assertSame([], array_values(array_diff($conditional, $optional)), "Schema {$code} does not expose every conditional field through the optional payload.");
            $this->assertSame(count($required), $schema['required_count'], "Schema {$code} required count is stale.");
            $this->assertSame(count($conditional), $schema['conditional_count'], "Schema {$code} conditional count is stale.");
            $this->assertSame(count(array_diff($optional, $conditional)), $schema['optional_count'], "Schema {$code} optional count is stale.");
            $this->assertSame($fields->count(), $schema['field_count'], "Schema {$code} field count is stale.");

            if ($code !== 'DEFAULT' && $code !== ($schema['root_code'] ?? null)) {
                $labels = $fields->pluck('label')->all();
                $this->assertSame(count($labels), count(array_unique($labels)), "Exact schema {$code} contains duplicate visible labels.");
            }
        }
    }

    public function test_precision_group_ownership_matches_engineering_context(): void
    {
        $expectations = [
            'PV_MONO_PERC' => [
                'cell_type' => 'architecture',
                'cell_count' => 'architecture',
                'module_efficiency_percent' => 'performance',
                'temp_coeff_voc' => 'thermal',
                'temp_coeff_pmax' => 'thermal',
            ],
            'BAT_RACK' => [
                'battery_chemistry' => 'battery',
                'capacity_ah' => 'battery',
                'capacity_kwh' => 'battery',
                'round_trip_efficiency_percent' => 'performance',
                'operating_temperature' => 'thermal',
            ],
            'DC_SPD' => [
                'spd_type' => 'protection',
                'response_time_ns' => 'protection',
                'short_circuit_withstand_ka' => 'protection',
                'visual_indicator' => 'monitoring',
            ],
            'MOUNT_GROUND' => [
                'pile_type' => 'construction',
                'corrosion_class' => 'environmental',
            ],
            'CIVIL_FOUNDATION' => [
                'bearing_capacity_kpa' => 'mechanical',
            ],
            'LIGHTNING_PROTECTION' => [
                'down_conductor_material' => 'construction',
                'corrosion_resistance' => 'environmental',
                'lps_class' => 'safety',
            ],
            'FIRE_EXTINGUISHER' => [
                'extinguishing_agent' => 'safety',
                'capacity' => 'safety',
                'discharge_time_s' => 'performance',
            ],
            'FIRE_DETECTION' => [
                'response_time_s' => 'performance',
                'alarm_output' => 'monitoring',
            ],
            'CIVIL_SURVEY' => [
                'survey_instrument' => 'measurement',
                'accuracy_requirement' => 'measurement',
            ],
            'HSE_SERVICE' => [
                'assessment_checklist' => 'compliance',
                'manpower' => 'execution',
            ],
            'LEGAL_UTILITY' => [
                'utility_name' => 'service',
                'application_type' => 'service',
                'connection_capacity_kva' => 'electrical',
                'export_limit_kw' => 'control',
            ],
            'OM_TEST_EQUIPMENT' => [
                'safety_category' => 'safety',
                'measurement_range' => 'measurement',
            ],
            'LOG_STORAGE' => [
                'storage_area_m2' => 'service',
                'coverage_area' => 'service',
            ],
            'MON_SENSOR' => [
                'response_time_s' => 'measurement',
                'cable_length_m' => 'installation',
            ],
        ];

        foreach ($expectations as $code => $fieldGroups) {
            $fields = collect(ProfessionalSpecificationSchema::for($code)['groups'])
                ->flatMap(fn (array $group): array => $group['fields'])
                ->keyBy('key');

            foreach ($fieldGroups as $key => $expectedGroup) {
                $this->assertTrue($fields->has($key), "Schema {$code} is missing {$key}.");
                $this->assertSame($expectedGroup, $fields[$key]['group'], "Wrong group for {$code}.{$key}.");
            }
        }
    }

    public function test_combined_component_schemas_separate_common_and_subtype_dependent_fields(): void
    {
        $cases = [
            'DC_CABLE_CONNECTOR' => ['required' => 'rated_voltage', 'conditional' => 'conductor_size_mm2'],
            'DC_COMBINER' => ['required' => 'string_input_count', 'conditional' => 'fuse_rating_a'],
            'DC_PROTECTION' => ['required' => 'rated_current', 'conditional' => 'trip_curve'],
            'AC_PROTECTION' => ['required' => 'rated_current', 'conditional' => 'trip_unit_type'],
            'AC_TRANSFER_SYNC' => ['required' => 'source_count', 'conditional' => 'transfer_time_ms'],
            'AC_METERING_CONTROL' => ['required' => 'measured_parameter', 'conditional' => 'ct_ratio'],
            'MOUNT_ACCESS' => ['required' => 'device_or_material_type', 'conditional' => 'lifeline_rating_kn'],
        ];

        foreach ($cases as $code => $keys) {
            $schema = ProfessionalSpecificationSchema::for($code);
            $fields = collect($schema['groups'])->flatMap(fn (array $group): array => $group['fields'])->keyBy('key');

            $this->assertContains($keys['required'], $schema['required_specs']);
            $this->assertContains($keys['conditional'], $schema['conditional_specs']);
            $this->assertTrue($fields[$keys['required']]['required']);
            $this->assertTrue($fields[$keys['conditional']]['conditional']);
            $this->assertFalse($fields[$keys['conditional']]['required']);
        }
    }

    public function test_spd_schema_has_one_classification_source_and_precise_labels(): void
    {
        $schema = ProfessionalSpecificationSchema::for('DC_SPD');
        $keys = collect($schema['groups'])->flatMap(fn (array $group): array => $group['fields'])->pluck('key');

        $this->assertContains('spd_type', $keys);
        $this->assertNotContains('device_type', $keys);
        $this->assertSame('SPD Classification', ProfessionalSpecificationSchema::definition('spd_type')['label']);
        $this->assertStringContainsString('Iscpv', ProfessionalSpecificationSchema::definition('short_circuit_withstand_ka')['label']);
    }

    public function test_energy_capacity_labels_and_schema_counts_are_unambiguous(): void
    {
        $this->assertSame('Nominal Capacity', ProfessionalSpecificationSchema::definition('capacity_ah')['label']);
        $this->assertSame('Ah', ProfessionalSpecificationSchema::definition('capacity_ah')['unit']);
        $this->assertSame('Nominal Energy', ProfessionalSpecificationSchema::definition('capacity_kwh')['label']);
        $this->assertSame('kWh', ProfessionalSpecificationSchema::definition('capacity_kwh')['unit']);

        $schema = ProfessionalSpecificationSchema::for('DC_CABLE_CONNECTOR');
        $this->assertSame(count($schema['conditional_specs']), $schema['conditional_count']);
        $this->assertSame(
            count(array_diff($schema['optional_specs'], $schema['conditional_specs'])),
            $schema['optional_count']
        );
    }


    public function test_retired_duplicate_keys_are_explicitly_scoped_to_exact_components(): void
    {
        $this->assertSame(['module_technology'], ProfessionalSpecificationSchema::deprecatedKeysFor('PV_MONO_PERC'));
        $this->assertSame(['module_technology'], ProfessionalSpecificationSchema::deprecatedKeysFor('PV_TOPCON'));
        $this->assertSame(['module_technology'], ProfessionalSpecificationSchema::deprecatedKeysFor('PV_HJT'));
        $this->assertSame(['bifacial'], ProfessionalSpecificationSchema::deprecatedKeysFor('PV_BIFACIAL'));
        $this->assertSame(['device_type'], ProfessionalSpecificationSchema::deprecatedKeysFor('DC_SPD'));
        $this->assertSame([], ProfessionalSpecificationSchema::deprecatedKeysFor('BAT_RACK'));
    }

    public function test_exact_phase_subtypes_cannot_submit_contradictory_phase_values(): void
    {
        $cases = [
            'INV_STRING_1P' => ['single_phase'],
            'INV_STRING_3P' => ['three_phase'],
            'INV_CENTRAL' => ['three_phase'],
        ];

        foreach ($cases as $code => $expectedOptions) {
            $field = collect(ProfessionalSpecificationSchema::for($code)['groups'])
                ->flatMap(fn (array $group): array => $group['fields'])
                ->firstWhere('key', 'phase_type');

            $this->assertNotNull($field, "Schema {$code} must expose phase_type.");
            $this->assertSame($expectedOptions, array_keys($field['options']));
        }
    }

}
