<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Customer;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Models\TechnicalCalculation;
use App\Services\Quotation\QuotationEngine;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class QuotationEngineTest extends TestCase
{
    public function test_it_generates_customer_facing_quotation_from_priced_boq_and_financial_analysis(): void
    {
        $project = $this->project();
        $bom = $this->bom();
        $analysis = $this->analysis();

        $payload = (new QuotationEngine())->generate($project, $bom, $analysis, [
            'quotation_title' => 'PV Rooftop Proposal',
            'validity_days' => 21,
            'include_item_detail' => true,
            'payment_terms' => '50% DP, 50% before commissioning.',
            'delivery_terms' => 'Installation after site readiness confirmation.',
            'warranty_terms' => 'Manufacturer warranty applies.',
            'exclusions' => 'Utility approval is excluded.',
            'quotation_note' => 'Prepared for preliminary approval.',
        ]);

        $this->assertSame('draft', $payload['summary']['status']);
        $this->assertSame('PV Rooftop Proposal', $payload['summary']['title']);
        $this->assertSame(21, $payload['summary']['validity_days']);
        $this->assertSame(60000000.0, $payload['commercial_summary']['grand_total_idr']);
        $this->assertSame('NEXORIN Customer', $payload['customer_snapshot']['name']);
        $this->assertSame(6.6, $payload['technical_summary']['installed_pv_kw']);
        $this->assertSame(12000000.0, $payload['financial_summary']['first_year_saving_idr']);
        $this->assertNotEmpty($payload['items']);
        $this->assertSame([], $payload['warnings']);
    }

    public function test_it_warns_when_boq_or_financial_analysis_is_not_ready(): void
    {
        $project = $this->project(false);
        $bom = $this->bom('draft', 0);
        $analysis = $this->analysis('draft');

        $payload = (new QuotationEngine())->generate($project, $bom, $analysis);

        $this->assertContains('BOQ pricing is not marked as priced. Quotation value may be incomplete.', $payload['warnings']);
        $this->assertContains('BOQ grand price is zero. Generate BOQ pricing before issuing a quotation.', $payload['warnings']);
        $this->assertContains('Financial analysis is not marked as calculated. Financial summary may be incomplete.', $payload['warnings']);
        $this->assertContains('Technical calculation is not engineer verified. Quotation should remain a draft.', $payload['warnings']);
    }

    public function test_it_can_generate_package_only_quotation(): void
    {
        $payload = (new QuotationEngine())->generate(
            $this->project(),
            $this->bom(),
            $this->analysis(),
            ['include_item_detail' => false]
        );

        $this->assertSame('PV-SYSTEM-PACKAGE', $payload['items'][0]['item_code']);
        $this->assertTrue(count($payload['items']) < 10);
    }

    private function project(bool $verified = true): Project
    {
        $project = new Project([
            'project_number' => 'NXR-PRJ-001',
            'name' => 'Residential PV Demo',
            'building_type' => 'residential',
            'proposed_system' => 'on_grid',
            'site_address' => 'Jl. Solar No. 1, Pekanbaru',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
        ]);

        $project->setRelation('customer', new Customer([
            'name' => 'NEXORIN Customer',
            'customer_number' => 'CUST-001',
            'email' => 'customer@nexorin.local',
            'phone' => '08123456789',
            'address' => 'Pekanbaru',
        ]));

        $project->setRelation('technicalCalculation', new TechnicalCalculation([
            'system_type' => 'on_grid',
            'result_status' => $verified ? 'verified' : 'detailed',
            'results' => [
                'installed_pv_kw' => 6.6,
                'annual_energy_kwh' => 9500,
                'module_count' => 12,
                'inverter_count' => 1,
                'battery_nominal_kwh' => 0,
            ],
        ]));

        return $project;
    }

    private function bom(string $pricingStatus = 'priced', float $grandPrice = 60000000): BillOfMaterial
    {
        $bom = new BillOfMaterial([
            'pricing_status' => $pricingStatus,
            'summary' => [
                'system_type' => 'on_grid',
                'installed_pv_kw' => 6.6,
                'module_count' => 12,
                'inverter_count' => 1,
            ],
            'material_price_subtotal_idr' => 42000000,
            'labor_cost_idr' => 6000000,
            'logistics_cost_idr' => 1500000,
            'other_cost_idr' => 0,
            'contingency_cost_idr' => 1500000,
            'tax_idr' => 9000000,
            'discount_idr' => 0,
            'grand_price_idr' => $grandPrice,
        ]);

        $items = new Collection([
            new BillOfMaterialItem([
                'category' => 'pv_module',
                'item_code' => 'PV-MODULE',
                'item_name' => 'PV Module 550 Wp',
                'description' => 'Monocrystalline PV module',
                'quantity' => 12,
                'unit' => 'pcs',
                'unit_price_idr' => 1900000,
                'line_price_idr' => 22800000,
                'source_key' => 'module_count',
            ]),
            new BillOfMaterialItem([
                'category' => 'inverter',
                'item_code' => 'INV-GRID-HYBRID',
                'item_name' => 'Grid inverter',
                'description' => '5 kW inverter',
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price_idr' => 20700000,
                'line_price_idr' => 20700000,
                'source_key' => 'inverter_count',
            ]),
        ]);

        $bom->setRelation('items', $items);

        return $bom;
    }

    private function analysis(string $status = 'calculated'): FinancialAnalysis
    {
        return new FinancialAnalysis([
            'status' => $status,
            'project_capex_idr' => 60000000,
            'annual_energy_kwh' => 9500,
            'first_year_saving_idr' => 12000000,
            'simple_payback_years' => 5,
            'lifetime_roi_percent' => 240,
            'npv_idr' => 85000000,
            'irr_percent' => 18.5,
            'lcoe_idr_per_kwh' => 650,
            'summary' => [
                'project_capex_idr' => 60000000,
                'first_year_saving_idr' => 12000000,
            ],
        ]);
    }
}
