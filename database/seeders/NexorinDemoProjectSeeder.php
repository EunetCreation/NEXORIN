<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ElectricityBill;
use App\Models\FinancialAnalysis;
use App\Models\LoadAnalysis;
use App\Models\LoadAppliance;
use App\Models\MonthlySolarDatum;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use App\Models\Quotation;
use App\Models\RoofSiteSurvey;
use App\Models\RoofSurface;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
use App\Models\TechnicalCalculationHistory;
use App\Models\User;
use App\Services\Calculation\TechnicalCalculationEngine;
use App\Services\Finance\FinancialAnalysisEngine;
use App\Services\Pricing\PricingEngine;
use App\Services\Quantity\QuantityEngine;
use App\Services\Quotation\QuotationEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NexorinDemoProjectSeeder extends Seeder
{
    /**
     * Create one complete Pekanbaru demo project from source data to quotation.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('NexorinDemoProjectSeeder is disabled in production.');

            return;
        }

        $this->call([
            RbacSeeder::class,
            SuperAdminSeeder::class,
            DevelopmentCompanySeeder::class,
            DevelopmentRbacUsersSeeder::class,
        ]);

        DB::transaction(function (): void {
            $company = Company::query()
                ->where('slug', 'nexorin-energy')
                ->first();

            if (! $company) {
                throw new RuntimeException('The NEXORIN demo company is unavailable.');
            }

            $admin = User::query()->where('email', 'company.admin@nexorin.local')->first()
                ?? User::query()->where('email', 'admin@nexorin.local')->first();
            $engineer = User::query()->where('email', 'engineer@nexorin.local')->first() ?? $admin;
            $sales = User::query()->where('email', 'sales@nexorin.local')->first() ?? $admin;

            if (! $admin) {
                throw new RuntimeException('The NEXORIN demo users are unavailable.');
            }

            $customer = Customer::query()->updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'customer_number' => 'NXR-CUS-DEMO-001',
                ],
                [
                    'name' => 'PT Demo Pekanbaru Energi',
                    'type' => 'commercial',
                    'email' => 'customer.demo@nexorin.local',
                    'phone' => '+62 812-2026-0001',
                    'address' => 'Jl. HR Soebrantas, Tampan',
                    'city' => 'Pekanbaru',
                    'province' => 'Riau',
                    'postal_code' => '28293',
                    'notes' => 'Demo customer for the complete NEXORIN workflow.',
                    'status' => 'active',
                    'archived_at' => null,
                    'created_by' => $admin->getKey(),
                    'updated_by' => $admin->getKey(),
                ]
            );

            $project = Project::query()->updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'project_number' => 'NXR-PRJ-DEMO-001',
                ],
                [
                    'customer_id' => $customer->getKey(),
                    'name' => 'Demo PLTS Atap Hybrid Pekanbaru',
                    'building_type' => 'commercial',
                    'proposed_system' => 'hybrid',
                    'site_address' => 'Jl. HR Soebrantas, Tampan, Pekanbaru, Riau',
                    'city' => 'Pekanbaru',
                    'province' => 'Riau',
                    'postal_code' => '28293',
                    'latitude' => 0.507067,
                    'longitude' => 101.447779,
                    'description' => 'End-to-end demo project for load analysis, solar profile, roof survey, technical calculation, BOM/BOQ, financial analysis, quotation, and PDF reports.',
                    'status' => 'quotation',
                    'assigned_engineer_id' => $engineer?->getKey(),
                    'assigned_sales_id' => $sales?->getKey(),
                    'created_by' => $admin->getKey(),
                    'updated_by' => $admin->getKey(),
                    'status_updated_by' => $admin->getKey(),
                    'status_changed_at' => now(),
                    'archived_at' => null,
                ]
            );

            $this->resetDemoProjectOutputs($project);

            $loadAnalysis = $this->seedLoadAnalysis($company, $project, $admin);
            $solarProfile = $this->seedSolarProfile($company, $project, $engineer ?? $admin);
            $roofSurvey = $this->seedRoofSurvey($company, $project, $engineer ?? $admin);
            $recommendation = $this->seedSystemRecommendation($company, $project, $engineer ?? $admin);

            $project->load([
                'loadAnalysis.appliances',
                'loadAnalysis.electricityBills',
                'solarProfile.monthlyData',
                'roofSiteSurvey.surfaces',
                'systemRecommendation',
            ]);

            $sources = $this->sourceSnapshot($project, $loadAnalysis, $solarProfile, $roofSurvey, $recommendation);
            $technicalInput = $this->technicalInput();
            $technicalPayload = app(TechnicalCalculationEngine::class)->calculate(
                $project,
                $technicalInput,
                $sources
            );

            $technicalResult = $technicalPayload;
            unset($technicalResult['warnings'], $technicalResult['source_snapshot']);

            $calculation = TechnicalCalculation::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                ...$technicalInput,
                'result_status' => 'verified',
                'results' => $technicalResult,
                'warnings' => $technicalPayload['warnings'],
                'source_snapshot' => $sources,
                'engineering_notes' => 'Demo calculation generated by NexorinDemoProjectSeeder.',
                'calculated_by' => $engineer?->getKey(),
                'calculated_at' => now(),
                'verified_by' => $engineer?->getKey(),
                'verified_at' => now(),
                'created_by' => $admin->getKey(),
                'updated_by' => $admin->getKey(),
            ]);

            TechnicalCalculationHistory::query()->create([
                'technical_calculation_id' => $calculation->getKey(),
                'user_id' => $engineer?->getKey(),
                'event' => 'verified',
                'from_status' => $technicalPayload['result_status'] ?? 'detailed',
                'to_status' => 'verified',
                'notes' => 'Demo calculation was generated and engineer verified by the demo seeder.',
                'snapshot' => [
                    'installed_pv_kw' => $technicalResult['installed_pv_kw'] ?? null,
                    'module_count' => $technicalResult['module_count'] ?? null,
                    'inverter_count' => $technicalResult['inverter_count'] ?? null,
                    'annual_energy_kwh' => $technicalResult['annual_energy_kwh'] ?? null,
                ],
                'changed_at' => now(),
            ]);

            $quantityPayload = app(QuantityEngine::class)->generate($project->fresh(), $calculation);
            $bom = BillOfMaterial::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'technical_calculation_id' => $calculation->getKey(),
                'bom_number' => 'BOM-'.$company->code.'-DEMO-001',
                'status' => 'generated',
                'summary' => $quantityPayload['summary'],
                'warnings' => $quantityPayload['warnings'],
                'source_snapshot' => $quantityPayload['source_snapshot'],
                'generated_by' => $engineer?->getKey(),
                'generated_at' => now(),
                'created_by' => $admin->getKey(),
                'updated_by' => $admin->getKey(),
            ]);
            $bom->items()->createMany($quantityPayload['items']);
            $bom->load('items');

            $pricingPayload = app(PricingEngine::class)->price($bom, [
                'material_markup_percent' => 15,
                'labor_rate_per_kwp_idr' => 850000,
                'logistics_cost_idr' => 2500000,
                'other_cost_idr' => 0,
                'contingency_percent' => 3,
                'tax_percent' => 11,
                'discount_percent' => 2,
                'pricing_note' => 'Demo BOQ pricing using MVP default price book.',
            ]);

            foreach ($pricingPayload['items'] as $pricedItem) {
                $bom->items()->whereKey($pricedItem['id'])->update([
                    'unit_cost_idr' => $pricedItem['unit_cost_idr'],
                    'unit_price_idr' => $pricedItem['unit_price_idr'],
                    'line_cost_idr' => $pricedItem['line_cost_idr'],
                    'line_price_idr' => $pricedItem['line_price_idr'],
                    'metadata' => $pricedItem['metadata'],
                ]);
            }

            $bom->update([
                'pricing_status' => 'priced',
                'summary' => $pricingPayload['summary'],
                'warnings' => array_values(array_unique(array_merge($bom->warnings ?? [], $pricingPayload['warnings']))),
                'material_cost_subtotal_idr' => $pricingPayload['summary']['material_cost_subtotal_idr'],
                'material_price_subtotal_idr' => $pricingPayload['summary']['material_price_subtotal_idr'],
                'labor_cost_idr' => $pricingPayload['summary']['labor_cost_idr'],
                'logistics_cost_idr' => $pricingPayload['summary']['logistics_cost_idr'],
                'other_cost_idr' => $pricingPayload['summary']['other_cost_idr'],
                'contingency_cost_idr' => $pricingPayload['summary']['contingency_cost_idr'],
                'tax_idr' => $pricingPayload['summary']['tax_idr'],
                'discount_idr' => $pricingPayload['summary']['discount_idr'],
                'grand_cost_idr' => $pricingPayload['summary']['grand_cost_idr'],
                'grand_price_idr' => $pricingPayload['summary']['grand_price_idr'],
                'gross_margin_idr' => $pricingPayload['summary']['gross_margin_idr'],
                'gross_margin_percent' => $pricingPayload['summary']['gross_margin_percent'],
                'pricing_parameters' => $pricingPayload['parameters'],
                'priced_by' => $admin->getKey(),
                'priced_at' => now(),
                'updated_by' => $admin->getKey(),
            ]);

            $bom = $bom->fresh(['items']);
            $financialPayload = app(FinancialAnalysisEngine::class)->calculate($project->fresh(), $calculation, $bom, [
                'electricity_tariff_idr_per_kwh' => 1444.70,
                'export_credit_percent' => 0,
                'self_consumption_percent' => 85,
                'tariff_escalation_percent' => 3,
                'annual_om_percent' => 1,
                'discount_rate_percent' => 8,
                'energy_degradation_percent' => 0.5,
                'project_lifetime_years' => 25,
                'replacement_year' => 10,
                'replacement_cost_idr' => 45000000,
                'project_capex_idr' => null,
                'analysis_note' => 'Demo financial scenario for Pekanbaru hybrid rooftop PV.',
            ]);
            $financialSummary = $financialPayload['summary'];

            $analysis = FinancialAnalysis::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'technical_calculation_id' => $calculation->getKey(),
                'bill_of_material_id' => $bom->getKey(),
                'analysis_number' => 'FIN-'.$company->code.'-DEMO-001',
                'status' => 'calculated',
                'currency' => 'IDR',
                'electricity_tariff_idr_per_kwh' => $financialSummary['electricity_tariff_idr_per_kwh'],
                'export_credit_percent' => $financialSummary['export_credit_percent'] ?? 0,
                'self_consumption_percent' => $financialSummary['self_consumption_percent'],
                'tariff_escalation_percent' => $financialSummary['tariff_escalation_percent'],
                'annual_om_percent' => $financialPayload['parameters']['annual_om_percent'],
                'discount_rate_percent' => $financialSummary['discount_rate_percent'],
                'energy_degradation_percent' => $financialSummary['energy_degradation_percent'],
                'project_lifetime_years' => $financialSummary['project_lifetime_years'],
                'replacement_year' => $financialSummary['replacement_year'],
                'replacement_cost_idr' => $financialSummary['replacement_cost_idr'],
                'project_capex_idr' => $financialSummary['project_capex_idr'],
                'annual_energy_kwh' => $financialSummary['annual_energy_kwh'],
                'first_year_saving_idr' => $financialSummary['first_year_saving_idr'],
                'annual_om_cost_idr' => $financialSummary['annual_om_cost_idr'],
                'simple_payback_years' => $financialSummary['simple_payback_years'],
                'lifetime_roi_percent' => $financialSummary['lifetime_roi_percent'],
                'npv_idr' => $financialSummary['npv_idr'],
                'irr_percent' => $financialSummary['irr_percent'],
                'lcoe_idr_per_kwh' => $financialSummary['lcoe_idr_per_kwh'],
                'summary' => $financialSummary,
                'cashflows' => $financialPayload['cashflows'],
                'warnings' => $financialPayload['warnings'],
                'source_snapshot' => $financialPayload['source_snapshot'],
                'notes' => 'Demo financial scenario for Pekanbaru hybrid rooftop PV.',
                'calculated_by' => $admin->getKey(),
                'calculated_at' => now(),
                'created_by' => $admin->getKey(),
                'updated_by' => $admin->getKey(),
            ]);

            $quotationPayload = app(QuotationEngine::class)->generate($project->fresh(['customer', 'technicalCalculation']), $bom, $analysis, [
                'quotation_title' => 'Solar PV Hybrid System Proposal',
                'validity_days' => 14,
                'include_item_detail' => true,
                'payment_terms' => '50% down payment, 40% before delivery, and 10% after commissioning.',
                'delivery_terms' => 'Estimated delivery and installation period is 30 to 45 working days after down payment and final technical approval.',
                'warranty_terms' => 'PV module and inverter warranty follow manufacturer terms. Installation workmanship warranty is 1 year.',
                'exclusions' => 'Structural reinforcement, utility approval fees, civil works outside the agreed scope, and additional work caused by site changes are excluded.',
                'quotation_note' => 'Generated from the complete NEXORIN demo workflow.',
            ]);

            $quotation = Quotation::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'financial_analysis_id' => $analysis->getKey(),
                'bill_of_material_id' => $bom->getKey(),
                'quote_number' => 'QTN-'.$company->code.'-DEMO-001',
                'revision_number' => 0,
                'status' => 'issued',
                'currency' => 'IDR',
                'title' => $quotationPayload['summary']['title'],
                'valid_until' => $quotationPayload['summary']['valid_until'],
                'customer_snapshot' => $quotationPayload['customer_snapshot'],
                'project_snapshot' => $quotationPayload['project_snapshot'],
                'technical_summary' => $quotationPayload['technical_summary'],
                'financial_summary' => $quotationPayload['financial_summary'],
                'commercial_summary' => $quotationPayload['commercial_summary'],
                'terms' => $quotationPayload['terms'],
                'warnings' => $quotationPayload['warnings'],
                'source_snapshot' => $quotationPayload['source_snapshot'],
                'notes' => $quotationPayload['terms']['quotation_note'],
                'prepared_by' => $sales?->getKey(),
                'issued_by' => $sales?->getKey(),
                'issued_at' => now(),
                'created_by' => $sales?->getKey(),
                'updated_by' => $sales?->getKey(),
            ]);
            $quotation->items()->createMany($quotationPayload['items']);
        });

        $this->command?->info('NEXORIN demo project is ready: NXR-PRJ-DEMO-001.');
        $this->command?->info('Login with company.admin@nexorin.local / CompanyAdmin@2026! and open /companies/1/projects/1 or the project list.');
    }

    private function resetDemoProjectOutputs(Project $project): void
    {
        $project->quotation?->items()->delete();
        $project->quotation?->delete();
        $project->financialAnalysis?->delete();
        $project->billOfMaterial?->items()->delete();
        $project->billOfMaterial?->delete();
        $project->technicalCalculation?->histories()->delete();
        $project->technicalCalculation?->delete();
        $project->systemRecommendation?->scores()->delete();
        $project->systemRecommendation?->histories()->delete();
        $project->systemRecommendation?->delete();
        $project->roofSiteSurvey?->surfaces()->delete();
        $project->roofSiteSurvey?->histories()->delete();
        $project->roofSiteSurvey?->photos()->delete();
        $project->roofSiteSurvey?->delete();
        $project->solarProfile?->monthlyData()->delete();
        $project->solarProfile?->histories()->delete();
        $project->solarProfile?->delete();
        $project->loadAnalysis?->appliances()->delete();
        $project->loadAnalysis?->electricityBills()->delete();
        $project->loadAnalysis?->delete();
    }

    private function seedLoadAnalysis(Company $company, Project $project, User $user): LoadAnalysis
    {
        $analysis = LoadAnalysis::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'source_mode' => 'combined',
            'connected_power_va' => 23000,
            'supply_phase' => 'three_phase',
            'tariff_group' => 'B-2/TR',
            'electricity_rate_per_kwh' => 1444.70,
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'notes' => 'Demo commercial load profile for Pekanbaru.',
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $appliances = [
            ['Air Conditioner', 'HVAC', 6, 900, 8, 0.75, 8, 3.0, false, 10],
            ['Office Lighting', 'Lighting', 36, 12, 10, 1.00, 7, 1.0, false, 20],
            ['Computer Workstation', 'Office Equipment', 18, 120, 8, 0.70, 8, 1.2, false, 30],
            ['Refrigerator and Pantry Load', 'Appliance', 2, 180, 24, 0.45, 0, 1.5, true, 40],
            ['Network and CCTV Critical Load', 'Critical Load', 1, 600, 24, 1.00, 0, 1.0, true, 50],
        ];

        foreach ($appliances as [$name, $category, $quantity, $power, $hours, $usage, $start, $startMultiplier, $critical, $sort]) {
            LoadAppliance::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'load_analysis_id' => $analysis->getKey(),
                'name' => $name,
                'category' => $category,
                'quantity' => $quantity,
                'rated_power_w' => $power,
                'operating_hours_per_day' => $hours,
                'usage_factor' => $usage,
                'start_hour' => $start,
                'starting_multiplier' => $startMultiplier,
                'is_critical' => $critical,
                'sort_order' => $sort,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
        }

        foreach ([860, 910, 895, 925, 940, 970] as $index => $energyKwh) {
            ElectricityBill::query()->create([
                'company_id' => $company->getKey(),
                'project_id' => $project->getKey(),
                'load_analysis_id' => $analysis->getKey(),
                'billing_month' => now()->subMonths(5 - $index)->startOfMonth()->toDateString(),
                'energy_kwh' => $energyKwh,
                'bill_amount' => round($energyKwh * 1444.70, 2),
                'peak_demand_kw' => 9.5 + ($index * 0.2),
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
        }

        return $analysis;
    }

    private function seedSolarProfile(Company $company, Project $project, User $user): ProjectSolarProfile
    {
        $profile = ProjectSolarProfile::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'data_source' => 'reference',
            'source_name' => 'Pekanbaru manual reference dataset',
            'source_reference' => 'Demo internal reference',
            'source_date' => now()->toDateString(),
            'elevation_m' => 12.0,
            'timezone' => 'Asia/Jakarta',
            'quality_status' => 'verified',
            'notes' => 'Monthly PSH values prepared for NEXORIN demonstration.',
            'verified_by' => $user->getKey(),
            'verified_at' => now(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $psh = [4.4, 4.5, 4.7, 4.8, 4.9, 4.8, 4.7, 4.9, 4.8, 4.7, 4.5, 4.4];
        foreach ($psh as $monthIndex => $value) {
            MonthlySolarDatum::query()->create([
                'company_id' => $company->getKey(),
                'project_solar_profile_id' => $profile->getKey(),
                'month' => $monthIndex + 1,
                'ghi_daily_kwh_m2' => $value,
                'poa_daily_kwh_m2' => round($value * 0.98, 3),
                'peak_sun_hours' => $value,
                'ambient_temperature_c' => 28.5 + (($monthIndex % 4) * 0.3),
            ]);
        }

        return $profile->fresh('monthlyData');
    }

    private function seedRoofSurvey(Company $company, Project $project, User $user): RoofSiteSurvey
    {
        $survey = RoofSiteSurvey::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'survey_date' => now()->toDateString(),
            'surveyed_by_user_id' => $user->getKey(),
            'access_method' => 'fixed_ladder',
            'access_condition' => 'good',
            'mounting_preference' => 'roof_parallel',
            'structural_review_status' => 'completed',
            'structural_notes' => 'Demo survey assumes roof structure is acceptable for preliminary planning.',
            'fall_protection_available' => true,
            'lightning_protection_present' => true,
            'electrical_room_distance_m' => 18,
            'estimated_cable_route_m' => 28,
            'weather_condition' => 'Clear',
            'quality_status' => 'verified',
            'notes' => 'Demo rooftop survey with one usable metal sheet roof plane.',
            'verified_by' => $user->getKey(),
            'verified_at' => now(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        RoofSurface::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'roof_site_survey_id' => $survey->getKey(),
            'name' => 'Main Office Roof',
            'roof_type' => 'pitched',
            'roof_material' => 'metal_sheet',
            'roof_condition' => 'good',
            'gross_area_m2' => 125,
            'setback_area_m2' => 14,
            'obstruction_area_m2' => 6,
            'walkway_area_m2' => 10,
            'tilt_deg' => 12,
            'azimuth_deg' => 0,
            'shading_percent' => 5,
            'max_additional_load_kg_m2' => 18,
            'sort_order' => 1,
            'notes' => 'Suitable for a compact rooftop PV array in the demo scenario.',
        ]);

        return $survey->fresh('surfaces');
    }

    private function seedSystemRecommendation(Company $company, Project $project, User $user): SystemRecommendation
    {
        return SystemRecommendation::query()->create([
            'company_id' => $company->getKey(),
            'project_id' => $project->getKey(),
            'grid_connection_status' => 'occasional_outages',
            'outage_frequency_per_month' => 3,
            'average_outage_duration_hours' => 1.5,
            'backup_target_hours' => 3,
            'target_self_sufficiency_percent' => 40,
            'budget_flexibility' => 'balanced',
            'export_policy' => 'limited',
            'primary_objective' => 'balanced',
            'notes' => 'Hybrid is selected because the demo customer has critical loads and occasional outages.',
            'engine_recommended_system' => 'hybrid',
            'recommended_system' => 'hybrid',
            'on_grid_score' => 72.5,
            'hybrid_score' => 86.0,
            'decisiveness_index' => 13.5,
            'result_status' => 'generated',
            'engine_version' => 'demo-rule-v1',
            'hard_constraints' => [],
            'warnings' => [],
            'reasons' => [
                'Grid is available but outage frequency supports backup capability.',
                'Critical load requires several hours of autonomy.',
                'Commercial daytime load supports good self-consumption.',
            ],
            'source_snapshot' => ['source' => 'NexorinDemoProjectSeeder'],
            'generated_by' => $user->getKey(),
            'generated_at' => now(),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(
        Project $project,
        LoadAnalysis $loadAnalysis,
        ProjectSolarProfile $solarProfile,
        RoofSiteSurvey $roofSurvey,
        SystemRecommendation $recommendation
    ): array {
        $loadSummary = $loadAnalysis->fresh(['appliances', 'electricityBills'])->calculateSummary();
        $loadSummary['supply_phase'] = $loadAnalysis->supply_phase;
        $loadSummary['tariff_group'] = $loadAnalysis->tariff_group;
        $loadSummary['electricity_rate_per_kwh'] = $loadAnalysis->electricity_rate_per_kwh;

        return [
            'load' => $loadSummary,
            'solar' => $solarProfile->fresh('monthlyData')->calculateSummary($project),
            'roof' => $roofSurvey->fresh('surfaces')->calculateSummary(),
            'recommendation' => [
                'recommended_system' => $recommendation->recommended_system,
                'engine_recommended_system' => $recommendation->engine_recommended_system,
                'result_status' => $recommendation->result_status,
                'on_grid_score' => $recommendation->on_grid_score,
                'hybrid_score' => $recommendation->hybrid_score,
                'decisiveness_index' => $recommendation->decisiveness_index,
                'generated_at' => optional($recommendation->generated_at)->toISOString(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function technicalInput(): array
    {
        return [
            'system_type' => 'hybrid',
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
            'inverter_ac_power_kw' => 8,
            'inverter_max_dc_voltage_v' => 600,
            'inverter_mppt_min_voltage_v' => 120,
            'inverter_mppt_max_voltage_v' => 550,
            'inverter_max_input_current_a' => 32,
            'inverter_mppt_count' => 2,
            'desired_dc_ac_ratio' => 1.15,
            'battery_backup_hours' => 3,
            'battery_dod_percent' => 90,
            'battery_efficiency_percent' => 95,
            'inverter_efficiency_percent' => 96,
            'dc_cable_length_m' => 28,
            'dc_cable_area_mm2' => 6,
            'ac_cable_length_m' => 20,
            'ac_cable_area_mm2' => 6,
            'cable_material' => 'copper',
            'maximum_voltage_drop_percent' => 2,
        ];
    }
}
