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

        $this->assertContains('Solar PV Module', $names);
        $this->assertContains('On-Grid String Inverter', $names);
        $this->assertContains('PV DC Cable Red / Black', $names);
        $this->assertContains('AC Circuit Breaker', $names);
        $this->assertContains('Smart Meter / Energy Meter Set', $names);
        $this->assertContains('Shading Analysis Service', $names);
        $this->assertNotContains('Battery Energy Storage Pack', $names);
        $this->assertSame('08B-scope-mode-chargeability-control', $payload['summary']['master_catalog_version']);
        $this->assertGreaterThan(0, $payload['summary']['catalog_candidate_item_count']);

        $shading = collect($payload['items'])->firstWhere('item_code', 'SHADING-ANALYSIS-SERVICE');
        $this->assertFalse($shading['metadata']['included_in_base_price']);
        $this->assertSame('internal_catalog_candidate', $shading['metadata']['scope_mode']);
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
        $this->assertContains('Battery Cabinet and BMS Interface Set', $names);
        $this->assertContains('ATS / AMF / STS Transfer Set', $names);
        $this->assertContains('Battery Commissioning Report', $names);

        $ats = collect($payload['items'])->firstWhere('item_code', 'ATS-AMF-STS');
        $this->assertFalse($ats['metadata']['included_in_base_price']);
        $this->assertSame('internal_catalog_candidate', $ats['metadata']['scope_mode']);
    }

    public function test_it_warns_when_calculation_is_not_verified(): void
    {
        $calculation = $this->calculation('on_grid');
        $calculation->result_status = 'detailed';

        $payload = (new QuantityEngine())->generate($this->project(), $calculation);

        $this->assertContains(
            'The technical calculation has not been engineer verified. Keep this BOM/BOQ as a draft until review is complete.',
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
