<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\TechnicalCalculation;
use App\Services\Quantity\QuantityEngine;
use Tests\TestCase;

class QuantityEngineTest extends TestCase
{
    public function test_it_generates_bom_items_from_on_grid_technical_calculation(): void
    {
        $payload = (new QuantityEngine())->generate(
            $this->project(),
            $this->calculation('on_grid')
        );

        $this->assertSame('on_grid', $payload['summary']['system_type']);
        $this->assertSame(12, $payload['summary']['module_count']);
        $this->assertSame(1, $payload['summary']['inverter_count']);
        $this->assertSame('not_priced', $payload['summary']['pricing_status']);
        $this->assertNotEmpty($payload['items']);

        $names = array_column($payload['items'], 'item_name');

        $this->assertContains('PV Module', $names);
        $this->assertContains('Grid-Tied Inverter', $names);
        $this->assertContains('DC Solar Cable', $names);
        $this->assertContains('AC Circuit Breaker', $names);
        $this->assertNotContains('Battery Energy Storage Pack', $names);
    }

    public function test_it_generates_battery_items_for_hybrid_system(): void
    {
        $payload = (new QuantityEngine())->generate(
            $this->project(),
            $this->calculation('hybrid')
        );

        $names = array_column($payload['items'], 'item_name');

        $this->assertSame('hybrid', $payload['summary']['system_type']);
        $this->assertGreaterThan(0, $payload['summary']['battery_nominal_kwh']);
        $this->assertContains('Hybrid Inverter', $names);
        $this->assertContains('Battery Energy Storage Pack', $names);
        $this->assertContains('Battery Protection and BMS Interface Set', $names);
    }

    public function test_it_warns_when_calculation_is_not_verified(): void
    {
        $calculation = $this->calculation('on_grid');
        $calculation->result_status = 'detailed';

        $payload = (new QuantityEngine())->generate($this->project(), $calculation);

        $this->assertContains(
            'The technical calculation has not been engineer verified. This BOM should remain a draft.',
            $payload['warnings']
        );
    }

    private function project(): Project
    {
        return new Project([
            'project_number' => 'NXR-TEST-001',
            'name' => 'Test Project',
            'building_type' => 'residential',
        ]);
    }

    private function calculation(string $systemType): TechnicalCalculation
    {
        $batteryKwh = $systemType === 'hybrid' ? 12.5 : 0;

        $calculation = new TechnicalCalculation([
            'system_type' => $systemType,
            'result_status' => 'verified',
            'module_power_wp' => 550,
            'module_area_m2' => 2.58,
            'inverter_ac_power_kw' => 5,
            'inverter_mppt_count' => 2,
            'battery_backup_hours' => $systemType === 'hybrid' ? 4 : 0,
            'battery_dod_percent' => 90,
            'battery_efficiency_percent' => 95,
            'dc_cable_length_m' => 20,
            'dc_cable_area_mm2' => 6,
            'ac_cable_length_m' => 15,
            'ac_cable_area_mm2' => 6,
            'cable_material' => 'copper',
            'results' => [
                'installed_pv_kw' => 6.6,
                'module_count' => 12,
                'inverter_count' => 1,
                'total_inverter_ac_kw' => 5,
                'dc_ac_ratio' => 1.32,
                'string_count' => 2,
                'battery_nominal_kwh' => $batteryKwh,
                'battery_minimum_power_kw' => $systemType === 'hybrid' ? 3.2 : 0,
                'dc_voltage_drop_percent' => 1.1,
                'ac_voltage_drop_percent' => 0.8,
                'protection' => [
                    'dc_isolator_a' => 40,
                    'dc_fuse_a' => 20,
                    'ac_breaker_a' => 32,
                    'dc_spd' => 'Type II DC SPD',
                    'ac_spd' => 'Type II AC SPD',
                ],
            ],
        ]);

        $calculation->calculated_at = now();

        return $calculation;
    }
}
