<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Support\ProjectCockpit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCockpitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_charts_and_insights_for_project_cockpit(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $company = Company::query()->create([
            'name' => 'NEXORIN Energy',
            'slug' => 'nexorin-energy',
            'code' => 'NXR',
            'status' => 'active',
            'created_by' => $user->getKey(),
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_number' => 'NXR-CUS-00001',
            'name' => 'Budi Santoso',
            'type' => 'residential',
            'status' => 'active',
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $project = Project::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => 'NXR-2026-00001',
            'name' => 'Demo PLTS Atap Hybrid Pekanbaru',
            'building_type' => 'residential',
            'proposed_system' => 'hybrid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'quotation',
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
            'status_updated_by' => $user->getKey(),
            'status_changed_at' => now(),
        ]);

        $calculation = TechnicalCalculation::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'system_type' => 'hybrid',
            'result_status' => 'detailed',
            'results' => [
                'required_pv_kw' => 7.58,
                'installed_pv_kw' => 7.20,
                'module_count' => 14,
                'inverter_count' => 2,
                'dc_ac_ratio' => 0.77,
                'annual_energy_kwh' => 9580,
                'dc_voltage_drop_percent' => 1.1,
                'ac_voltage_drop_percent' => 0.8,
            ],
            'override_summary' => [
                'installed_pv_kw' => [
                    'override_value' => 7.20,
                    'reason' => 'Adjusted after roof review.',
                ],
            ],
            'warnings' => [
                [
                    'code' => 'DC_AC_RATIO_LOW',
                    'severity' => 'warning',
                    'title' => 'Low DC/AC Ratio',
                    'message' => 'The inverter capacity may be oversized.',
                    'recommendation' => 'Review inverter capacity.',
                    'source' => 'technical_calculation',
                ],
            ],
            'calculated_at' => now(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $bom = BillOfMaterial::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'technical_calculation_id' => $calculation->getKey(),
            'bom_number' => 'BOM-001',
            'status' => 'generated',
            'pricing_status' => 'priced',
            'grand_price_idr' => 117253990,
            'material_price_subtotal_idr' => 90000000,
            'labor_cost_idr' => 8000000,
            'tax_idr' => 11900000,
            'discount_idr' => 0,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        FinancialAnalysis::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'technical_calculation_id' => $calculation->getKey(),
            'bill_of_material_id' => $bom->getKey(),
            'analysis_number' => 'FIN-001',
            'status' => 'calculated',
            'project_capex_idr' => 117253990,
            'annual_energy_kwh' => 9580,
            'first_year_saving_idr' => 11764032,
            'annual_om_cost_idr' => 1172539,
            'simple_payback_years' => 9.92,
            'lifetime_roi_percent' => 204.30,
            'npv_idr' => 21644894,
            'irr_percent' => 9.86,
            'lcoe_idr_per_kwh' => 1366,
            'cashflows' => [
                [
                    'year' => 1,
                    'gross_saving_idr' => 11764032,
                    'om_cost_idr' => 1172539,
                    'net_cashflow_idr' => 10591493,
                ],
                [
                    'year' => 2,
                    'gross_saving_idr' => 12000000,
                    'om_cost_idr' => 1190000,
                    'net_cashflow_idr' => 10810000,
                ],
            ],
            'calculated_by' => $user->getKey(),
            'calculated_at' => now(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $project->refresh()->load([
            'customer',
            'technicalCalculation',
            'billOfMaterial',
            'financialAnalysis',
            'quotation',
            'loadAnalysis',
            'solarProfile',
            'roofSiteSurvey',
            'systemRecommendation',
        ]);

        $cockpit = ProjectCockpit::build($project);

        $this->assertArrayHasKey('charts', $cockpit);
        $this->assertArrayHasKey('insights', $cockpit);
        $this->assertTrue($cockpit['charts']['cashflow_available']);
        $this->assertSame('Y1', 'Y'.$cockpit['charts']['cashflow'][0]['year']);
        $this->assertNotEmpty($cockpit['insights']);
        $this->assertSame('warning', $cockpit['warning_counts']['warning'] > 0 ? 'warning' : 'none');
        $this->assertSame('IDR 117.253.990', $cockpit['commercial_summary']['grand_price']);
    }
}
