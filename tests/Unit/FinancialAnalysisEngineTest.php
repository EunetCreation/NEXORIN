<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\Project;
use App\Models\TechnicalCalculation;
use App\Services\Finance\FinancialAnalysisEngine;
use Tests\TestCase;

class FinancialAnalysisEngineTest extends TestCase
{
    public function test_it_calculates_core_financial_metrics(): void
    {
        $payload = (new FinancialAnalysisEngine())->calculate(
            $this->project(),
            $this->calculation('on_grid'),
            $this->pricedBom(),
            [
                'electricity_tariff_idr_per_kwh' => 1600,
                'self_consumption_percent' => 85,
                'project_lifetime_years' => 25,
                'discount_rate_percent' => 8,
            ]
        );

        $this->assertSame('calculated', $payload['summary']['status']);
        $this->assertSame(8500.0, $payload['summary']['first_year_self_consumed_kwh']);
        $this->assertSame(1500.0, $payload['summary']['first_year_export_kwh']);
        $this->assertGreaterThan(0, $payload['summary']['first_year_saving_idr']);
        $this->assertGreaterThan(0, $payload['summary']['project_capex_idr']);
        $this->assertGreaterThan(0, $payload['summary']['lcoe_idr_per_kwh']);
        $this->assertCount(25, $payload['cashflows']);
    }

    public function test_custom_tariff_and_capex_change_result(): void
    {
        $engine = new FinancialAnalysisEngine();

        $base = $engine->calculate($this->project(), $this->calculation('on_grid'), $this->pricedBom());
        $custom = $engine->calculate(
            $this->project(),
            $this->calculation('on_grid'),
            $this->pricedBom(),
            [
                'electricity_tariff_idr_per_kwh' => 2200,
                'project_capex_idr' => 50000000,
                'export_credit_percent' => 20,
            ]
        );

        $this->assertNotSame(
            $base['summary']['first_year_saving_idr'],
            $custom['summary']['first_year_saving_idr']
        );
        $this->assertSame(50000000.0, $custom['summary']['project_capex_idr']);
        $this->assertContains(
            'Export credit is treated as a configurable commercial assumption. Verify it against the active regulation and utility approval.',
            $custom['warnings']
        );
    }

    public function test_it_warns_when_boq_is_not_priced_or_calculation_not_verified(): void
    {
        $calculation = $this->calculation('hybrid');
        $calculation->result_status = 'detailed';

        $bom = $this->pricedBom();
        $bom->pricing_status = 'not_priced';

        $payload = (new FinancialAnalysisEngine())->calculate(
            $this->project(),
            $calculation,
            $bom
        );

        $this->assertContains(
            'BOQ pricing is not marked as priced. Financial analysis uses the available draft value.',
            $payload['warnings']
        );
        $this->assertContains(
            'The technical calculation is not engineer verified. Treat financial analysis as a preliminary estimate.',
            $payload['warnings']
        );
    }

    private function project(): Project
    {
        return new Project([
            'project_number' => 'NXR-TEST-001',
            'name' => 'Financial Test Project',
            'building_type' => 'commercial',
        ]);
    }

    private function calculation(string $systemType): TechnicalCalculation
    {
        $calculation = new TechnicalCalculation([
            'system_type' => $systemType,
            'result_status' => 'verified',
            'results' => [
                'installed_pv_kw' => 8.25,
                'annual_energy_kwh' => 10000,
            ],
        ]);

        $calculation->calculated_at = now();

        return $calculation;
    }

    private function pricedBom(): BillOfMaterial
    {
        return new BillOfMaterial([
            'pricing_status' => 'priced',
            'grand_price_idr' => 75000000,
            'summary' => [
                'grand_price_idr' => 75000000,
                'installed_pv_kw' => 8.25,
                'annual_energy_kwh' => 10000,
            ],
        ]);
    }
}
