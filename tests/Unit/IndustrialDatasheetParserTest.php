<?php

namespace Tests\Unit;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialSpec;
use App\Services\Catalog\Datasheet\DatasheetCategoryDetector;
use App\Services\Catalog\DatasheetParser;
use App\Support\ProfessionalCatalogProfile;
use Illuminate\Support\Collection;
use Tests\TestCase;

class IndustrialDatasheetParserTest extends TestCase
{
    public function test_pv_parser_is_schema_aware_and_preserves_negative_temperature_coefficients(): void
    {
        $material = $this->material('PV_MODULE', 'PV_MONO_PERC');
        $text = <<<'TEXT'
PV Module Datasheet
Maximum Power Pmax: 550 W
Module Efficiency: 21.3%
Open Circuit Voltage Voc: 49.8 V
Maximum Power Voltage Vmp: 41.9 V
Short Circuit Current Isc: 14.0 A
Maximum Power Current Imp: 13.13 A
Temp coefficient Voc: -0.28 %/C
Temperature Coefficient Pmax: -0.35 %/C
Length: 2279 mm
Width: 1134 mm
Weight: 28.5 kg
Cell Count: 144 cells
Cell Technology: Mono PERC
Maximum system voltage: 1500 V
Product warranty: 12 years
Performance warranty: 25 years
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material);

        $this->assertSame('parsed', $result['status']);
        $this->assertSame('matched', data_get($result, 'extraction_meta.category.match'));
        $this->assertSame('-0.28', $result['fields']['temp_coeff_voc']['value']);
        $this->assertSame('-0.35', $result['fields']['temp_coeff_pmax']['value']);
        $this->assertSame('550', $result['fields']['module_power_wp']['value']);
        $this->assertSame('Wp', $result['fields']['module_power_wp']['unit']);
        $this->assertArrayNotHasKey('battery_chemistry', $result['fields']);
    }

    public function test_battery_parser_normalizes_wh_to_kwh_and_controlled_select_values(): void
    {
        $material = $this->material('BATTERY_ESS', 'BAT_RACK');
        $text = <<<'TEXT'
LFP Rack Battery Module
Battery Chemistry: Lithium Iron Phosphate (LFP)
Nominal Voltage: 51.2 V
Nominal Capacity: 100 Ah
Nominal Energy: 5120 Wh
Usable Energy: 4.6 kWh
Depth of Discharge: 90%
Round Trip Efficiency: 95%
Cycle Life: 6000 cycles
Maximum Charge Current: 100 A
Maximum Discharge Current: 100 A
C-rate: 1 C
Maximum Parallel Units: 16
Communication Protocol: CAN / RS485
Operating Temperature Range: -20 to 55 C
IP Rating: IP20
Warranty: 10 years
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material);

        $this->assertSame('5.12', $result['fields']['capacity_kwh']['value']);
        $this->assertSame('kWh', $result['fields']['capacity_kwh']['unit']);
        $this->assertSame('lfp', $result['fields']['battery_chemistry']['value']);
        $this->assertSame('-20 to 55', $result['fields']['operating_temperature']['value']);
        $this->assertSame('°C', $result['fields']['operating_temperature']['unit']);
    }

    public function test_spd_parser_maps_exact_protection_fields_without_duplicates(): void
    {
        $material = $this->material('DC_ELECTRICAL', 'DC_SPD');
        $text = <<<'TEXT'
DC Surge Protective Device Datasheet IEC 61643-31
SPD Classification: Type II
Maximum Continuous Operating Voltage Uc: 1000 VDC
Voltage Protection Level Up: 3.8 kV
Nominal Discharge Current In: 20 kA
Maximum Discharge Current Imax: 40 kA
Pole Count: 2 poles
Surge Response Time: 25 ns
PV Short-circuit Current Rating Iscpv: 10 kA
Remote Status Contact: No
Visual Status Indicator: Yes
Replaceable Cartridge: Yes
IP Rating: IP20
Applicable Standard: IEC 61643-31
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material);

        $this->assertSame('Type II', $result['fields']['spd_type']['value']);
        $this->assertSame('1000', $result['fields']['uc_voltage']['value']);
        $this->assertSame('3.8', $result['fields']['up_voltage_kv']['value']);
        $this->assertSame('20', $result['fields']['in_current']['value']);
        $this->assertSame('40', $result['fields']['imax_current']['value']);
        $this->assertSame('0', $result['fields']['remote_contact']['value']);
        $this->assertArrayNotHasKey('device_type', $result['fields']);
    }

    public function test_category_mismatch_blocks_unrelated_document_and_short_aliases_do_not_match_inside_words(): void
    {
        $material = $this->material('BATTERY_ESS', 'BAT_RACK');
        $text = <<<'TEXT'
Grid-tied String Inverter
Rated AC Output Power: 10 kW
Maximum DC Input Voltage: 1100 V
MPPT Voltage Range: 200-1000 V
Anti-islanding protection
Grid code profile IEC 61727
Number of MPPT: 2
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material);

        $this->assertSame('category_mismatch', $result['status']);
        $this->assertTrue((bool) data_get($result, 'extraction_meta.category.blocked'));
        $this->assertSame('INVERTER_ON_GRID', data_get($result, 'extraction_meta.category.detected_root'));
        $this->assertArrayNotHasKey('round_trip_efficiency_percent', $result['fields']);
    }

    public function test_multi_model_value_row_is_flagged_low_confidence(): void
    {
        $material = $this->material('PV_MODULE', 'PV_MONO_PERC');

        $result = app(DatasheetParser::class)->parseText(
            "PV Module\nOpen Circuit Voltage: 49.8 V 50.2 V",
            $material
        );

        $this->assertSame('low_confidence', $result['fields']['voc']['status']);
        $this->assertSame('keep', $result['fields']['voc']['suggested_action']);
        $this->assertNotEmpty($result['fields']['voc']['validation_warnings']);
    }




    public function test_ambiguous_grouped_dot_is_normalized_conservatively_and_kept_low_confidence(): void
    {
        $material = $this->material('BATTERY_ESS', 'BAT_RACK');

        $result = app(DatasheetParser::class)->parseText(
            "LFP Rack Battery\nNominal Energy: 5.120 Wh",
            $material
        );

        $this->assertSame('5.12', $result['fields']['capacity_kwh']['value']);
        $this->assertSame('kWh', $result['fields']['capacity_kwh']['unit']);
        $this->assertSame('low_confidence', $result['fields']['capacity_kwh']['status']);
        $this->assertSame('keep', $result['fields']['capacity_kwh']['suggested_action']);
    }

    public function test_mppt_range_line_is_split_into_governed_minimum_and_maximum_fields(): void
    {
        $material = $this->material('INVERTER_ON_GRID', 'INV_STRING_3P');

        $result = app(DatasheetParser::class)->parseText(
            "Grid-tied String Inverter\nMPPT Voltage Range: 200 - 1000 V\nRated AC Output Power: 10 kW\nAnti-islanding protection",
            $material
        );

        $this->assertSame('200', $result['fields']['mppt_voltage_min']['value']);
        $this->assertSame('1000', $result['fields']['mppt_voltage_max']['value']);
        $this->assertSame('V', $result['fields']['mppt_voltage_min']['unit']);
        $this->assertSame('V', $result['fields']['mppt_voltage_max']['unit']);
    }

    public function test_decimal_comma_with_zero_integer_part_is_not_misread_as_thousands(): void
    {
        $material = $this->material('PV_MODULE', 'PV_MONO_PERC');

        $result = app(DatasheetParser::class)->parseText(
            "PV Module\nTemperature Coefficient Pmax: -0,350 %/C",
            $material
        );

        $this->assertSame('-0.35', $result['fields']['temp_coeff_pmax']['value']);
    }

    public function test_existing_value_conflict_is_not_suggested_for_automatic_acceptance(): void
    {
        $material = $this->material('PV_MODULE', 'PV_MONO_PERC', collect([
            $this->spec('voc', '49.8', 'V'),
        ]));

        $result = app(DatasheetParser::class)->parseText("PV Module\nOpen Circuit Voltage Voc: 50.4 V", $material);

        $this->assertSame('conflict', $result['fields']['voc']['status']);
        $this->assertSame('keep', $result['fields']['voc']['suggested_action']);
        $this->assertSame('49.8', $result['fields']['voc']['existing_value']);
    }


    public function test_multi_variant_pv_table_selects_exact_stc_column_from_material_power(): void
    {
        $material = $this->material('PV_MODULE', 'PV_TOPCON');
        $material->nominal_power_wp = '470';
        $material->name = 'Vertex S+ TSM-NED9R.28 470W';
        $text = <<<'TEXT'
PV Module N-type i-TOPCon
ELECTRICAL DATA (STC)
Peak Power Watts-PMAX(Wp)* 445 450 455 460 465 470
Maximum Power Voltage-VMPP (V) 44.30 44.60 45.00 45.40 45.80 46.10
Maximum Power Current-IMPP (A) 10.05 10.09 10.11 10.14 10.16 10.20
Open Circuit Voltage-VOC (V) 52.60 52.90 53.40 53.80 54.20 54.60
Short Circuit Current-ISC (A) 10.71 10.74 10.77 10.81 10.85 10.89
Module Efficiency η m (%) 22.3 22.5 22.8 23.0 23.3 23.5
ELECTRICAL DATA (NOCT)
Peak Power Watts-PMAX(Wp) 341 344 348 352 355 359
Temperature Coefficient of PMAX - 0.29% /°C
Temperature Coefficient of VOC - 0.24% /°C
Module Dimensions 1762×1134×30 mm
Weight 27 kg
Solar Cells N-type i-TOPCon Monocrystalline
No. of cells 144 cells
Maximum System Voltage 1500V DC
15-year Product Warranty
30-year Power Guarantee
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material);

        $this->assertSame('parsed', $result['status']);
        $this->assertSame(470.0, (float) data_get($result, 'extraction_meta.variant.selected_variant'));
        $this->assertSame('material_summary', data_get($result, 'extraction_meta.variant.selection_source'));
        $this->assertSame('470', $result['fields']['module_power_wp']['value']);
        $this->assertSame('46.1', $result['fields']['vmp']['value']);
        $this->assertSame('10.2', $result['fields']['imp']['value']);
        $this->assertSame('54.6', $result['fields']['voc']['value']);
        $this->assertSame('10.89', $result['fields']['isc']['value']);
        $this->assertSame('23.5', $result['fields']['module_efficiency_percent']['value']);
        $this->assertSame('-0.29', $result['fields']['temp_coeff_pmax']['value']);
        $this->assertSame('-0.24', $result['fields']['temp_coeff_voc']['value']);
        $this->assertSame('1762', $result['fields']['length_mm']['value']);
        $this->assertSame('1134', $result['fields']['width_mm']['value']);
        $this->assertSame('27', $result['fields']['weight_kg']['value']);
        $this->assertSame('144', $result['fields']['cell_count']['value']);
        $this->assertSame('N-type i-TOPCon Monocrystalline', $result['fields']['cell_type']['value']);
        $this->assertSame('15', $result['fields']['product_warranty_years']['value']);
        $this->assertSame('30', $result['fields']['performance_warranty_years']['value']);
    }

    public function test_multi_variant_pv_table_requires_explicit_selection_when_material_power_is_unknown(): void
    {
        $material = $this->material('PV_MODULE', 'PV_TOPCON');
        $text = <<<'TEXT'
PV Module
ELECTRICAL DATA (STC)
Peak Power Watts-PMAX(Wp)* 445 450 455 460 465 470
Maximum Power Voltage-VMPP (V) 44.30 44.60 45.00 45.40 45.80 46.10
Maximum Power Current-IMPP (A) 10.05 10.09 10.11 10.14 10.16 10.20
Open Circuit Voltage-VOC (V) 52.60 52.90 53.40 53.80 54.20 54.60
Short Circuit Current-ISC (A) 10.71 10.74 10.77 10.81 10.85 10.89
Module Efficiency (%) 22.3 22.5 22.8 23.0 23.3 23.5
ELECTRICAL DATA (NOCT)
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material);

        $this->assertSame('variant_selection_required', $result['status']);
        $this->assertTrue((bool) data_get($result, 'extraction_meta.variant.blocked'));
        $this->assertSame([445.0, 450.0, 455.0, 460.0, 465.0, 470.0], data_get($result, 'extraction_meta.variant.available_variants'));
        $this->assertArrayNotHasKey('module_power_wp', $result['fields']);
        $this->assertArrayNotHasKey('voc', $result['fields']);
    }

    public function test_multi_variant_pv_table_can_use_explicit_review_selection(): void
    {
        $material = $this->material('PV_MODULE', 'PV_TOPCON');
        $text = <<<'TEXT'
PV Module
ELECTRICAL DATA (STC)
Peak Power Watts-PMAX(Wp)* 445 450 455 460 465 470
Maximum Power Voltage-VMPP (V) 44.30 44.60 45.00 45.40 45.80 46.10
Maximum Power Current-IMPP (A) 10.05 10.09 10.11 10.14 10.16 10.20
Open Circuit Voltage-VOC (V) 52.60 52.90 53.40 53.80 54.20 54.60
Short Circuit Current-ISC (A) 10.71 10.74 10.77 10.81 10.85 10.89
Module Efficiency (%) 22.3 22.5 22.8 23.0 23.3 23.5
ELECTRICAL DATA (NOCT)
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material, ['variant_power_wp' => 465]);

        $this->assertSame('parsed', $result['status']);
        $this->assertSame(465.0, (float) data_get($result, 'extraction_meta.variant.selected_variant'));
        $this->assertSame('user_selected', data_get($result, 'extraction_meta.variant.selection_source'));
        $this->assertSame('465', $result['fields']['module_power_wp']['value']);
        $this->assertSame('45.8', $result['fields']['vmp']['value']);
        $this->assertSame('54.2', $result['fields']['voc']['value']);
    }

    public function test_exact_category_detector_registry_covers_all_professional_subcategories(): void
    {
        $detector = app(DatasheetCategoryDetector::class);
        $reflection = new \ReflectionClass($detector);
        $method = $reflection->getMethod('exactSignatures');
        $method->setAccessible(true);
        $signatures = $method->invoke($detector);

        $expectedCount = 0;
        foreach (ProfessionalCatalogProfile::profiles() as $rootCode => $profile) {
            foreach (($profile['subcategories'] ?? []) as $subcategoryCode => $label) {
                $expectedCount++;
                $this->assertArrayHasKey($rootCode, $signatures, "Missing detector root {$rootCode}.");
                $this->assertArrayHasKey($subcategoryCode, $signatures[$rootCode], "Missing exact detector signature for {$rootCode}.{$subcategoryCode} ({$label}).");
                $this->assertNotEmpty($signatures[$rootCode][$subcategoryCode], "Empty exact detector signature for {$rootCode}.{$subcategoryCode}.");
            }
        }

        $this->assertSame(70, $expectedCount);
    }

    public function test_exact_pv_subtype_mismatch_blocks_topcon_document_from_mono_perc_material(): void
    {
        $material = $this->material('PV_MODULE', 'PV_MONO_PERC');
        $material->name = 'PV Module Mono PERC 550 Wp';
        $material->nominal_power_wp = '550';

        $text = <<<'TEXT'
N-type i-TOPCon Ultra Dual Glass Monocrystalline PV Module
Maximum Power Pmax: 470 W
Open Circuit Voltage Voc: 54.60 V
Maximum Power Voltage Vmp: 46.10 V
Short Circuit Current Isc: 10.89 A
Maximum Power Current Imp: 10.20 A
Module Efficiency: 23.5%
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material, [
            'document_name' => 'Vertex S+ TSM-NED9R.28 445-470W.pdf',
        ]);

        $this->assertSame('category_mismatch', $result['status']);
        $this->assertTrue((bool) data_get($result, 'extraction_meta.category.blocked'));
        $this->assertFalse((bool) data_get($result, 'extraction_meta.category.root_blocked'));
        $this->assertTrue((bool) data_get($result, 'extraction_meta.category.exact_blocked'));
        $this->assertSame('exact_component_mismatch', data_get($result, 'extraction_meta.category.review_reason'));
        $this->assertSame('PV_MONO_PERC', data_get($result, 'extraction_meta.category.expected_exact'));
        $this->assertSame('PV_TOPCON', data_get($result, 'extraction_meta.category.detected_exact'));
        $this->assertSame('Mono PERC Module', data_get($result, 'extraction_meta.category.expected_exact_label'));
        $this->assertSame('TOPCon Module', data_get($result, 'extraction_meta.category.detected_exact_label'));
    }

    public function test_exact_mono_perc_document_confirms_mono_perc_material(): void
    {
        $material = $this->material('PV_MODULE', 'PV_MONO_PERC');
        $text = <<<'TEXT'
P-type Monocrystalline PERC PV Module Datasheet
Maximum Power Pmax: 550 W
Open Circuit Voltage Voc: 49.8 V
Maximum Power Voltage Vmp: 41.9 V
Short Circuit Current Isc: 14.0 A
Maximum Power Current Imp: 13.13 A
Module Efficiency: 21.3%
TEXT;

        $result = app(DatasheetParser::class)->parseText($text, $material);

        $this->assertSame('parsed', $result['status']);
        $this->assertFalse((bool) data_get($result, 'extraction_meta.category.blocked'));
        $this->assertSame('matched', data_get($result, 'extraction_meta.category.exact_match'));
        $this->assertSame('confirmed', data_get($result, 'extraction_meta.category.review_reason'));
        $this->assertSame('PV_MONO_PERC', data_get($result, 'extraction_meta.category.detected_exact'));
    }

    private function material(string $rootCode, string $childCode, ?Collection $specs = null): Material
    {
        $root = new MaterialCategory(['code' => $rootCode, 'name' => $rootCode]);
        $root->id = 1;
        $root->parent_id = null;

        $child = new MaterialCategory(['code' => $childCode, 'name' => $childCode]);
        $child->id = 2;
        $child->parent_id = 1;
        $child->setRelation('parent', $root);

        $material = new Material(['name' => 'Parser Test Material']);
        $material->id = 99;
        $material->material_category_id = 2;
        $material->setRelation('category', $child);
        $material->setRelation('specs', $specs ?? collect());

        return $material;
    }

    private function spec(string $key, string $value, ?string $unit): MaterialSpec
    {
        $spec = new MaterialSpec([
            'spec_key' => $key,
            'spec_label' => $key,
            'spec_value' => $value,
            'spec_unit' => $unit,
        ]);
        $spec->id = random_int(1, 100000);

        return $spec;
    }
}
