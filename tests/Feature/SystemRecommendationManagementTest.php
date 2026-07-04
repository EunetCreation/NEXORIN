<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\LoadAnalysis;
use App\Models\LoadAppliance;
use App\Models\MonthlySolarDatum;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use App\Models\Role;
use App\Models\RoofSiteSurvey;
use App\Models\RoofSurface;
use App\Models\SystemRecommendation;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemRecommendationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $this->superAdmin->systemRoles()->attach($superAdminRole, [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);

        $this->company = Company::query()->create([
            'name' => 'NEXORIN Energy',
            'slug' => 'nexorin-energy',
            'code' => 'NXR',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $this->customer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00001',
            'name' => 'Recommendation Customer',
            'type' => 'residential',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $this->customer->getKey(),
            'project_number' => 'NXR-2026-00001',
            'name' => 'Residential Recommendation',
            'building_type' => 'residential',
            'proposed_system' => 'undecided',
            'site_address' => 'Pekanbaru, Riau',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'latitude' => 0.507067,
            'longitude' => 101.447777,
            'status' => 'engineering',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->createCompleteSourceData();
    }

    public function test_engineer_can_generate_hybrid_recommendation_and_apply_override(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->put(route('companies.recommendations.run', [$this->company, $this->project]), [
                'grid_connection_status' => 'frequent_outages',
                'outage_frequency_per_month' => 8,
                'average_outage_duration_hours' => 2,
                'backup_target_hours' => 4,
                'target_self_sufficiency_percent' => 70,
                'budget_flexibility' => 'flexible',
                'export_policy' => 'zero_export',
                'primary_objective' => 'backup',
                'notes' => 'Frequent outages require reliable backup for critical residential loads.',
            ])
            ->assertRedirect(route('companies.recommendations.show', [$this->company, $this->project]));

        $recommendation = SystemRecommendation::query()
            ->with('scores')
            ->where('project_id', $this->project->getKey())
            ->firstOrFail();

        $this->assertSame('hybrid', $recommendation->engine_recommended_system);
        $this->assertSame('hybrid', $recommendation->recommended_system);
        $this->assertGreaterThan((float) $recommendation->on_grid_score, (float) $recommendation->hybrid_score);
        $this->assertCount(16, $recommendation->scores);
        $this->assertContains($recommendation->result_status, ['generated', 'review_required']);

        $this->actingAs($engineer)
            ->patch(route('companies.recommendations.override', [$this->company, $this->project]), [
                'override_system' => 'on_grid',
                'override_reason' => 'The customer rejected battery storage and accepted the documented outage limitation.',
            ])
            ->assertRedirect(route('companies.recommendations.show', [$this->company, $this->project]));

        $this->assertDatabaseHas('system_recommendations', [
            'project_id' => $this->project->getKey(),
            'engine_recommended_system' => 'hybrid',
            'recommended_system' => 'on_grid',
            'override_system' => 'on_grid',
            'result_status' => 'overridden',
        ]);
    }

    public function test_engine_blocks_recommendation_when_grid_is_unavailable(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->put(route('companies.recommendations.run', [$this->company, $this->project]), [
                'grid_connection_status' => 'unavailable',
                'outage_frequency_per_month' => 0,
                'average_outage_duration_hours' => 0,
                'backup_target_hours' => 4,
                'target_self_sufficiency_percent' => 90,
                'budget_flexibility' => 'flexible',
                'export_policy' => 'unknown',
                'primary_objective' => 'self_sufficiency',
            ])
            ->assertRedirect(route('companies.recommendations.show', [$this->company, $this->project]))
            ->assertSessionHas('error');

        $recommendation = SystemRecommendation::query()
            ->where('project_id', $this->project->getKey())
            ->firstOrFail();

        $this->assertSame('incomplete', $recommendation->result_status);
        $this->assertNull($recommendation->recommended_system);
        $this->assertNotEmpty($recommendation->hard_constraints);
    }

    public function test_customer_can_view_only_owned_project_recommendation(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');
        $this->customer->update(['portal_user_id' => $customerUser->getKey()]);

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00002',
            'name' => 'Other Customer',
            'type' => 'residential',
            'portal_user_id' => $otherCustomerUser->getKey(),
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $otherProject = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $otherCustomer->getKey(),
            'project_number' => 'NXR-2026-00002',
            'name' => 'Other Recommendation',
            'building_type' => 'residential',
            'proposed_system' => 'undecided',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'engineering',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->actingAs($customerUser)
            ->get(route('companies.recommendations.show', [$this->company, $this->project]))
            ->assertOk();

        $this->actingAs($customerUser)
            ->get(route('companies.recommendations.show', [$this->company, $otherProject]))
            ->assertNotFound();
    }

    public function test_sales_can_view_but_cannot_run_recommendation(): void
    {
        $sales = $this->createCompanyUser('sales');

        $this->actingAs($sales)
            ->get(route('companies.recommendations.show', [$this->company, $this->project]))
            ->assertOk();

        $this->actingAs($sales)
            ->put(route('companies.recommendations.run', [$this->company, $this->project]), [
                'grid_connection_status' => 'stable',
                'outage_frequency_per_month' => 0,
                'average_outage_duration_hours' => 0,
                'backup_target_hours' => 0,
                'target_self_sufficiency_percent' => 30,
                'budget_flexibility' => 'strict',
                'export_policy' => 'allowed',
                'primary_objective' => 'savings',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    private function createCompleteSourceData(): void
    {
        $loadAnalysis = LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'source_mode' => 'equipment',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        LoadAppliance::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'load_analysis_id' => $loadAnalysis->getKey(),
            'name' => 'Critical Air Conditioner',
            'category' => 'cooling',
            'quantity' => 2,
            'rated_power_w' => 1000,
            'operating_hours_per_day' => 8,
            'start_hour' => 8,
            'usage_factor' => 0.75,
            'starting_multiplier' => 3,
            'is_critical' => true,
            'sort_order' => 1,
        ]);

        $solarProfile = ProjectSolarProfile::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'data_source' => 'manual',
            'source_name' => 'Test Solar Dataset',
            'timezone' => 'Asia/Jakarta',
            'quality_status' => 'verified',
            'verified_by' => $this->superAdmin->getKey(),
            'verified_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        foreach (range(1, 12) as $month) {
            MonthlySolarDatum::query()->create([
                'company_id' => $this->company->getKey(),
                'project_solar_profile_id' => $solarProfile->getKey(),
                'month' => $month,
                'ghi_daily_kwh_m2' => 4.8,
                'poa_daily_kwh_m2' => 4.9,
                'peak_sun_hours' => 4.5,
                'ambient_temperature_c' => 28,
            ]);
        }

        $roofSurvey = RoofSiteSurvey::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'survey_date' => '2026-07-03',
            'surveyed_by_user_id' => $this->superAdmin->getKey(),
            'access_method' => 'fixed_ladder',
            'access_condition' => 'good',
            'mounting_preference' => 'roof_parallel',
            'structural_review_status' => 'not_required',
            'fall_protection_available' => true,
            'lightning_protection_present' => true,
            'quality_status' => 'verified',
            'verified_by' => $this->superAdmin->getKey(),
            'verified_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        RoofSurface::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'roof_site_survey_id' => $roofSurvey->getKey(),
            'name' => 'Main Roof',
            'roof_type' => 'pitched',
            'roof_material' => 'metal_sheet',
            'roof_condition' => 'good',
            'gross_area_m2' => 100,
            'setback_area_m2' => 10,
            'obstruction_area_m2' => 5,
            'walkway_area_m2' => 5,
            'tilt_deg' => 15,
            'azimuth_deg' => 0,
            'shading_percent' => 10,
            'sort_order' => 1,
        ]);
    }

    private function createCompanyUser(string $roleCode): User
    {
        $user = User::factory()->create(['status' => 'active']);

        $membership = CompanyMembership::query()->create([
            'company_id' => $this->company->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
            'invited_by' => $this->superAdmin->getKey(),
            'joined_at' => now(),
        ]);

        $role = Role::query()
            ->where('code', $roleCode)
            ->where('scope', 'company')
            ->firstOrFail();

        $membership->roles()->attach($role, [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);

        return $user;
    }
}
