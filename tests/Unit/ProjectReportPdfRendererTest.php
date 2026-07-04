<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\TechnicalCalculation;
use App\Services\Report\ProjectReportPdfRenderer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class ProjectReportPdfRendererTest extends TestCase
{
    public function test_it_renders_project_report_pdf(): void
    {
        $project = new Project([
            'project_number' => 'PRJ-TEST-001',
            'name' => 'Pekanbaru Rooftop PV Demo',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Jl. Sudirman, Pekanbaru, Kompleks Gedung Energi Surya Lantai Dua Blok Timur dengan akses servis belakang',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'latitude' => 0.5071,
            'longitude' => 101.4478,
            'status' => 'quotation',
        ]);

        $project->setRelation('company', new Company([
            'name' => 'PT Krida Taruna Nugraha',
            'code' => 'KTN',
        ]));

        $project->setRelation('customer', new Customer([
            'name' => 'Demo Customer',
            'customer_number' => 'CUS-001',
            'email' => 'customer@example.test',
            'phone' => '08123456789',
        ]));

        $project->setRelation('technicalCalculation', new TechnicalCalculation([
            'system_type' => 'on_grid',
            'result_status' => 'detailed',
            'results' => [
                'required_pv_kw' => 6.5,
                'installed_pv_kw' => 6.6,
                'module_count' => 12,
                'inverter_count' => 1,
                'dc_ac_ratio' => 1.2,
                'annual_energy_kwh' => 9200,
                'modules_per_string' => 6,
                'string_count' => 2,
                'dc_voltage_drop_percent' => 1.1,
                'ac_voltage_drop_percent' => 0.8,
                'assumption_snapshot' => [
                    'performance_ratio' => [
                        'label' => 'Base performance ratio',
                        'value' => 0.78,
                        'unit' => '',
                        'source' => 'calculation_input',
                        'description' => 'Base performance ratio before shading adjustment.',
                    ],
                    'average_psh' => [
                        'label' => 'Average peak sun hour',
                        'value' => 4.7,
                        'unit' => 'h/day',
                        'source' => 'solar_profile',
                        'description' => 'Average daily solar resource used in preliminary energy production estimation.',
                    ],
                ],
            ],
            'warnings' => [
                [
                    'code' => 'DC_AC_RATIO_LOW',
                    'severity' => 'caution',
                    'title' => 'Low DC/AC Ratio',
                    'message' => 'DC/AC ratio is low. The inverter capacity may be oversized relative to the installed PV array.',
                    'recommendation' => 'Review inverter capacity or increase PV array size.',
                    'source' => 'technical_calculation',
                ],
            ],
        ]));

        $bom = new BillOfMaterial([
            'bom_number' => 'BOM-001',
            'status' => 'generated',
            'pricing_status' => 'priced',
            'material_cost_subtotal_idr' => 45000000,
            'material_price_subtotal_idr' => 55000000,
            'labor_cost_idr' => 5000000,
            'logistics_cost_idr' => 2000000,
            'tax_idr' => 6500000,
            'discount_idr' => 0,
            'grand_cost_idr' => 52000000,
            'grand_price_idr' => 68500000,
            'warnings' => [],
        ]);

        $bom->setRelation('items', new EloquentCollection([
            new BillOfMaterialItem([
                'item_name' => 'PV Module 550 Wp',
                'quantity' => 12,
                'unit' => 'pcs',
                'line_price_idr' => 36000000,
            ]),
            new BillOfMaterialItem([
                'item_name' => 'Grid-Tied Inverter',
                'quantity' => 1,
                'unit' => 'unit',
                'line_price_idr' => 12000000,
            ]),
        ]));

        $project->setRelation('billOfMaterial', $bom);

        $project->setRelation('financialAnalysis', new FinancialAnalysis([
            'status' => 'calculated',
            'project_capex_idr' => 68500000,
            'annual_energy_kwh' => 9200,
            'first_year_saving_idr' => 10600000,
            'annual_om_cost_idr' => 685000,
            'simple_payback_years' => 6.46,
            'lifetime_roi_percent' => 210,
            'npv_idr' => 35000000,
            'irr_percent' => 14.2,
            'lcoe_idr_per_kwh' => 620,
            'project_lifetime_years' => 25,
            'cashflows' => [
                [
                    'year' => 1,
                    'gross_saving_idr' => 10600000,
                    'om_cost_idr' => 685000,
                    'net_cashflow_idr' => 9915000,
                ],
            ],
            'warnings' => [],
        ]));

        $quotation = new Quotation([
            'quote_number' => 'QTN-KTN-20260704-001',
            'revision_number' => 0,
            'status' => 'issued',
            'commercial_summary' => [
                'grand_total_idr' => 68500000,
            ],
            'terms' => [
                'payment_terms' => '50% down payment, 40% before delivery, 10% after commissioning.',
                'delivery_terms' => '30 to 45 working days after approval.',
                'warranty_terms' => 'Manufacturer warranty and 1 year workmanship warranty.',
                'exclusions' => 'Structural reinforcement and utility approval fees are excluded.',
            ],
            'warnings' => [],
        ]);

        $quotation->setRelation('items', new EloquentCollection([
            new QuotationItem([
                'item_name' => 'PV System Package',
                'quantity' => 1,
                'unit' => 'lot',
                'line_price_idr' => 68500000,
            ]),
        ]));

        $project->setRelation('quotation', $quotation);

        $renderer = new ProjectReportPdfRenderer();
        $pdf = $renderer->render($project);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('NEXORIN', $pdf);
        $this->assertStringContainsString('+62 812-3456-789', $pdf);

        // Raw PDF output can split long text internally.
        // Checking key words separately makes the test stable.
        $this->assertStringContainsString('Gedung', $pdf);
        $this->assertStringContainsString('Energi', $pdf);
        $this->assertStringContainsString('Surya', $pdf);

        $this->assertStringContainsString('IDR 10,600,000', $pdf);
        $this->assertStringContainsString('Engineering assumptions', $pdf);
        $this->assertStringContainsString('Base performance ratio', $pdf);
        $this->assertStringContainsString('Low DC/AC Ratio', $pdf);
        $this->assertStringContainsString('Recommendation:', $pdf);
        $this->assertStringNotContainsString('...', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertSame('PRJ-TEST-001-project-report.pdf', $renderer->filename($project));
    }
}