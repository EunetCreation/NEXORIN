<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Services\Calculation\TechnicalCalculationEngine;
use App\Support\CalculationWarning;
use Tests\TestCase;

class TechnicalCalculationEngineTest extends TestCase
{
    private TechnicalCalculationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new TechnicalCalculationEngine();
    }

    public function test_it_calculates_normal_on_grid_design(): void
    {
        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput([
                'system_type' => 'on_grid',
            ]),
            $this->baseSources()
        );

        $this->assertSame('detailed', $result['result_status']);
        $this->assertSame('on_grid', $result['assumptions']['system_type']);
        $this->assertArrayHasKey('assumption_snapshot', $result);
        $this->assertSame(TechnicalCalculationEngine::VERSION, $result['assumption_snapshot']['engine_version']['value']);
        $this->assertSame('load_analysis', $result['assumption_snapshot']['daily_load_kwh']['source']);

        $this->assertGreaterThan(6.0, $result['required_pv_kw']);
        $this->assertGreaterThan(6.0, $result['installed_pv_kw']);
        $this->assertGreaterThan(0, $result['module_count']);
        $this->assertGreaterThan(0, $result['inverter_count']);

        $this->assertGreaterThanOrEqual(0.75, $result['dc_ac_ratio']);
        $this->assertLessThanOrEqual(1.50, $result['dc_ac_ratio']);

        $this->assertGreaterThan(0, $result['minimum_modules_per_string']);
        $this->assertGreaterThan(0, $result['maximum_modules_per_string']);
        $this->assertGreaterThan(0, $result['modules_per_string']);
        $this->assertGreaterThan(0, $result['string_count']);

        $this->assertArrayHasKey('dc_isolator_a', $result['protection']);
        $this->assertArrayHasKey('dc_fuse_a', $result['protection']);
        $this->assertArrayHasKey('ac_breaker_a', $result['protection']);
    }

    public function test_it_calculates_hybrid_battery_capacity(): void
    {
        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput([
                'system_type' => 'hybrid',
                'battery_backup_hours' => 4,
                'battery_dod_percent' => 90,
                'battery_efficiency_percent' => 95,
            ]),
            $this->baseSources()
        );

        $this->assertSame('hybrid', $result['assumptions']['system_type']);
        $this->assertGreaterThan(0, $result['battery_nominal_kwh']);
        $this->assertGreaterThan(0, $result['battery_minimum_power_kw']);
    }

    public function test_it_warns_when_roof_area_limits_pv_capacity(): void
    {
        $sources = $this->baseSources([
            'roof' => [
                'usable_area_m2' => 10,
                'weighted_shading_percent' => 5,
                'warnings' => [],
            ],
        ]);

        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput(),
            $sources
        );

        $this->assertLessThan(
            $result['required_module_count'],
            $result['module_count']
        );
    }

    public function test_it_detects_incompatible_string_and_mppt_window(): void
    {
        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput([
                'inverter_max_dc_voltage_v' => 200,
                'inverter_mppt_min_voltage_v' => 500,
                'inverter_mppt_max_voltage_v' => 180,
            ]),
            $this->baseSources()
        );

        $this->assertSame('preliminary', $result['result_status']);

        $this->assertWarningCode('STRING_MPPT_INCOMPATIBLE', $result['warnings']);
        $this->assertWarningSeverity('critical', $result['warnings']);
        $this->assertWarningMessageContains(
            'Selected module and inverter are not compatible within the MPPT and maximum DC voltage window.',
            $result['warnings']
        );
    }

    public function test_it_warns_when_voltage_drop_exceeds_limit(): void
    {
        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput([
                'dc_cable_length_m' => 200,
                'dc_cable_area_mm2' => 1.5,
                'ac_cable_length_m' => 150,
                'ac_cable_area_mm2' => 1.5,
                'maximum_voltage_drop_percent' => 1,
            ]),
            $this->baseSources()
        );

        $this->assertGreaterThan(1, $result['dc_voltage_drop_percent']);
        $this->assertGreaterThan(1, $result['ac_voltage_drop_percent']);

        $this->assertWarningCode('DC_VOLTAGE_DROP_EXCEEDS_LIMIT', $result['warnings']);
        $this->assertWarningCode('AC_VOLTAGE_DROP_EXCEEDS_LIMIT', $result['warnings']);
    }

    public function test_it_warns_when_dc_ac_ratio_is_low(): void
    {
        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput([
                'inverter_ac_power_kw' => 20,
            ]),
            $this->baseSources()
        );

        $this->assertLessThan(0.95, $result['dc_ac_ratio']);
        $this->assertWarningCode('DC_AC_RATIO_LOW', $result['warnings']);
        $this->assertWarningSeverity('caution', $result['warnings']);
        $this->assertWarningMessageContains(
            'DC/AC ratio is low. The inverter capacity may be oversized relative to the installed PV array, reducing utilization and financial performance.',
            $result['warnings']
        );
    }

    public function test_it_returns_preliminary_status_when_sources_are_missing(): void
    {
        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput(),
            []
        );

        $this->assertSame('preliminary', $result['result_status']);
        $this->assertSame(0, $result['module_count']);
        $this->assertWarningCode('LOAD_SOURCE_MISSING', $result['warnings']);
        $this->assertWarningCode('SOLAR_PROFILE_FALLBACK', $result['warnings']);
        $this->assertWarningCode('ROOF_AREA_MISSING', $result['warnings']);
    }


    public function test_it_returns_structured_warning_records_and_assumption_snapshot(): void
    {
        $result = $this->engine->calculate(
            new Project(),
            $this->baseInput([
                'inverter_ac_power_kw' => 20,
            ]),
            $this->baseSources()
        );

        $this->assertIsArray($result['warnings'][0]);
        $this->assertArrayHasKey('code', $result['warnings'][0]);
        $this->assertArrayHasKey('severity', $result['warnings'][0]);
        $this->assertArrayHasKey('recommendation', $result['warnings'][0]);
        $this->assertWarningCode('DC_AC_RATIO_LOW', $result['warnings']);
        $this->assertSame('calculation_input', $result['assumption_snapshot']['desired_dc_ac_ratio']['source']);
        $this->assertSame('Maximum voltage drop', $result['assumption_snapshot']['maximum_voltage_drop_percent']['label']);
    }


    /** @param array<int, mixed> $warnings */
    private function assertWarningCode(string $code, array $warnings): void
    {
        $codes = array_map(fn ($warning) => CalculationWarning::code($warning), $warnings);
        $this->assertContains($code, $codes);
    }

    /** @param array<int, mixed> $warnings */
    private function assertWarningSeverity(string $severity, array $warnings): void
    {
        $severities = array_map(fn ($warning) => CalculationWarning::severity($warning), $warnings);
        $this->assertContains($severity, $severities);
    }

    /** @param array<int, mixed> $warnings */
    private function assertWarningMessageContains(string $message, array $warnings): void
    {
        $messages = array_map(fn ($warning) => CalculationWarning::message($warning), $warnings);
        $this->assertTrue(
            collect($messages)->contains(fn (string $item) => str_contains($item, $message)),
            'Expected warning message was not found.'
        );
    }

    private function baseInput(array $override = []): array
    {
        return array_merge([
            'system_type' => 'on_grid',
            'energy_offset_target_percent' => 80,
            'performance_ratio' => 0.78,

            'module_power_wp' => 550,
            'module_area_m2' => 2.58,
            'module_voc_v' => 49.90,
            'module_vmp_v' => 41.80,
            'module_isc_a' => 13.90,
            'module_imp_a' => 13.16,
            'module_voc_temp_coeff_percent_c' => -0.25,
            'module_vmp_temp_coeff_percent_c' => -0.29,
            'minimum_site_temperature_c' => 20,
            'maximum_cell_temperature_c' => 70,

            'inverter_ac_power_kw' => 5,
            'inverter_max_dc_voltage_v' => 600,
            'inverter_mppt_min_voltage_v' => 120,
            'inverter_mppt_max_voltage_v' => 550,
            'inverter_max_input_current_a' => 32,
            'inverter_mppt_count' => 2,
            'desired_dc_ac_ratio' => 1.15,
            'inverter_efficiency_percent' => 96,

            'battery_backup_hours' => 0,
            'battery_dod_percent' => 90,
            'battery_efficiency_percent' => 95,

            'dc_cable_length_m' => 20,
            'dc_cable_area_mm2' => 6,
            'ac_cable_length_m' => 15,
            'ac_cable_area_mm2' => 6,
            'cable_material' => 'copper',
            'maximum_voltage_drop_percent' => 2,
        ], $override);
    }

    private function baseSources(array $override = []): array
    {
        return array_replace_recursive([
            'load' => [
                'daily_energy_kwh' => 30,
                'critical_daily_energy_kwh' => 12,
                'peak_demand_kw' => 4,
                'supply_phase' => 'single_phase',
            ],
            'solar' => [
                'average_psh' => 4.7,
            ],
            'roof' => [
                'usable_area_m2' => 80,
                'weighted_shading_percent' => 5,
                'warnings' => [],
            ],
            'recommendation' => [
                'recommended_system' => 'on_grid',
            ],
        ], $override);
    }
}