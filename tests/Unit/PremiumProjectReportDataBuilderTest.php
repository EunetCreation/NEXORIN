<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialAnalysis;
use App\Models\LoadAnalysis;
use App\Models\MonthlySolarDatum;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use App\Models\Quotation;
use App\Models\RoofSiteSurvey;
use App\Models\RoofSurface;
use App\Models\SystemRecommendation;
use App\Models\SystemRecommendationScore;
use App\Models\TechnicalCalculation;
use App\Services\Report\PremiumProjectReportPdfRenderer;
use App\Services\Report\ProjectReportDataBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class PremiumProjectReportDataBuilderTest extends TestCase
{
    public function test_it_builds_premium_report_data_without_database_persistence(): void
    {
        $project = $this->makeProjectFixture();

        $data = (new ProjectReportDataBuilder())->build($project);

        $this->assertSame('Pekanbaru Executive Solar HQ', $data['project']['name']);
        $this->assertSame('Hybrid', $data['headline']['system_type']);
        $this->assertSame('8,80 kWp', $data['technical']['installed_pv_kw']);
        $this->assertSame('Rp 92.500.000', $data['commercial']['grand_price']);
        $this->assertCount(12, $data['charts']['monthly_energy']);
        $this->assertGreaterThanOrEqual(1, $data['warnings']['total']);
    }

    public function test_it_renders_premium_html_preview(): void
    {
        $project = $this->makeProjectFixture();
        $renderer = app(PremiumProjectReportPdfRenderer::class);

        $html = $renderer->html($project, 'web');

        $this->assertStringContainsString('Premium Engineering Report', $html);
        $this->assertStringContainsString('Executive Dashboard', $html);
        $this->assertStringContainsString('Financial and Commercial Summary', $html);
        $this->assertStringContainsString('Pekanbaru Executive Solar HQ', $html);
    }

    private function makeProjectFixture(): Project
    {
        $project = new Project([
            'project_number' => 'NXR-2026-PREMIUM-001',
            'name' => 'Pekanbaru Executive Solar HQ',
            'building_type' => 'commercial',
            'proposed_system' => 'hybrid',
            'site_address' => 'Jl. Sudirman, Pekanbaru, Riau',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'latitude' => 0.507067,
            'longitude' => 101.447779,
            'status' => 'quotation',
        ]);

        $project->setRelation('company', new Company(['name' => 'NEXORIN Energy']));
        $project->setRelation('customer', new Customer([
            'name' => 'PT Surya Pelanggan Utama',
            'email' => 'customer@example.test',
            'phone' => '08123456789',
            'address' => 'Pekanbaru, Riau',
        ]));

        $load = new LoadAnalysis([
            'source_mode' => 'combined',
            'connected_power_va' => 23000,
            'supply_phase' => 'three_phase',
            'tariff_group' => 'B2',
            'billing_days' => 30,
        ]);
        $load->setRelation('appliances', new EloquentCollection());
        $load->setRelation('electricityBills', new EloquentCollection());
        $project->setRelation('loadAnalysis', $load);

        $solar = new ProjectSolarProfile([
            'data_source' => 'manual',
            'source_name' => 'Pekanbaru Reference Solar Dataset',
            'quality_status' => 'verified',
        ]);
        $solar->setRelation('monthlyData', new EloquentCollection(array_map(
            fn (int $month) => new MonthlySolarDatum([
                'month' => $month,
                'ghi_daily_kwh_m2' => 4.6 + (($month % 4) * 0.15),
                'poa_daily_kwh_m2' => 4.8 + (($month % 4) * 0.15),
                'peak_sun_hours' => 4.5 + (($month % 4) * 0.2),
                'ambient_temperature_c' => 29.5,
            ]),
            range(1, 12)
        )));
        $project->setRelation('solarProfile', $solar);

        $roof = new RoofSiteSurvey([
            'quality_status' => 'ready',
            'survey_date' => now(),
            'access_method' => 'fixed_ladder',
            'structural_review_status' => 'required',
        ]);
        $roof->setRelation('surfaces', new EloquentCollection([
            new RoofSurface([
                'name' => 'Main Roof',
                'roof_type' => 'flat',
                'roof_material' => 'concrete',
                'roof_condition' => 'good',
                'gross_area_m2' => 110,
                'setback_area_m2' => 10,
                'obstruction_area_m2' => 8,
                'walkway_area_m2' => 6,
                'tilt_deg' => 10,
                'azimuth_deg' => 180,
                'shading_percent' => 5,
            ]),
        ]));
        $project->setRelation('roofSiteSurvey', $roof);

        $recommendation = new SystemRecommendation([
            'recommended_system' => 'hybrid',
            'engine_recommended_system' => 'hybrid',
            'result_status' => 'generated',
            'grid_connection_status' => 'occasional_outages',
            'primary_objective' => 'balanced',
            'budget_flexibility' => 'balanced',
            'export_policy' => 'limited',
            'on_grid_score' => 73.5,
            'hybrid_score' => 88.0,
            'decisiveness_index' => 16.48,
            'reasons' => [
                'Hybrid is preferred because backup reliability is required.',
                'Daytime load still supports self-consumption.',
            ],
        ]);
        $recommendation->setRelation('scores', new EloquentCollection([
            new SystemRecommendationScore([
                'alternative' => 'hybrid',
                'criterion_name' => 'Backup requirement',
                'weight' => .15,
                'rating' => 95,
                'weighted_score' => 14.25,
                'rationale' => 'Critical load backup target is present.',
            ]),
        ]));
        $project->setRelation('systemRecommendation', $recommendation);

        $project->setRelation('technicalCalculation', new TechnicalCalculation([
            'system_type' => 'hybrid',
            'result_status' => 'detailed',
            'module_power_wp' => 550,
            'inverter_ac_power_kw' => 8,
            'performance_ratio' => .78,
            'results' => [
                'installed_pv_kw' => 8.8,
                'module_count' => 16,
                'annual_energy_kwh' => 12600,
                'dc_ac_ratio' => 1.1,
                'string_count' => 2,
                'modules_per_string' => 8,
                'mppt_used' => 2,
                'usable_battery_kwh' => 10,
                'dc_voltage_drop_percent' => 1.2,
                'ac_voltage_drop_percent' => .9,
            ],
            'warnings' => [[
                'severity' => 'warning',
                'title' => 'Engineering Verification Required',
                'message' => 'The current result is not engineer verified.',
                'recommendation' => 'Review before issue.',
            ]],
        ]));

        $bom = new BillOfMaterial([
            'status' => 'generated',
            'pricing_status' => 'priced',
            'material_price_subtotal_idr' => 72000000,
            'labor_cost_idr' => 8000000,
            'logistics_cost_idr' => 3500000,
            'tax_idr' => 8500000,
            'discount_idr' => 0,
            'grand_price_idr' => 92500000,
            'gross_margin_percent' => 21.5,
            'warnings' => [],
        ]);
        $bom->setRelation('items', new EloquentCollection([
            new BillOfMaterialItem(['category' => 'module', 'item_name' => 'PV Module 550 Wp', 'quantity' => 16, 'unit' => 'pcs', 'line_price_idr' => 48000000]),
            new BillOfMaterialItem(['category' => 'inverter', 'item_name' => 'Hybrid Inverter 8 kW', 'quantity' => 1, 'unit' => 'unit', 'line_price_idr' => 18500000]),
        ]));
        $project->setRelation('billOfMaterial', $bom);

        $project->setRelation('financialAnalysis', new FinancialAnalysis([
            'status' => 'calculated',
            'project_capex_idr' => 92500000,
            'annual_energy_kwh' => 12600,
            'first_year_saving_idr' => 18500000,
            'annual_om_cost_idr' => 925000,
            'simple_payback_years' => 5,
            'lifetime_roi_percent' => 220,
            'npv_idr' => 61500000,
            'irr_percent' => 18.2,
            'lcoe_idr_per_kwh' => 710,
            'electricity_tariff_idr_per_kwh' => 1444.7,
            'cashflows' => [],
            'warnings' => [],
        ]));

        $project->setRelation('quotation', new Quotation([
            'quote_number' => 'QTN-NXR-2026-001',
            'revision_number' => 1,
            'status' => 'issued',
            'commercial_summary' => ['grand_total_idr' => 92500000],
            'warnings' => [],
        ]));

        return $project;
    }
}
