<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\LocationSolarHistory;
use App\Models\MonthlySolarDatum;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectSolarProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSolarManagementTest extends TestCase
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
            'name' => 'Solar Resource Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = $this->createProject($this->company, $this->customer, [
            'project_number' => 'NXR-2026-00001',
            'name' => 'Commercial Solar Project',
        ]);
    }

    public function test_get_show_is_read_only_and_does_not_create_solar_profile(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->get(route('companies.location-solar.show', [$this->company, $this->project]))
            ->assertOk();

        $this->assertDatabaseCount('project_solar_profiles', 0);
        $this->assertDatabaseCount('location_solar_histories', 0);
    }

    public function test_engineer_can_explicitly_start_solar_dataset(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->post(route('companies.location-solar.start', [$this->company, $this->project]))
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]));

        $profile = ProjectSolarProfile::query()
            ->where('company_id', $this->company->getKey())
            ->where('project_id', $this->project->getKey())
            ->firstOrFail();

        $this->assertSame('incomplete', $profile->quality_status);
        $this->assertSame('manual', $profile->data_source);
        $this->assertSame(1, LocationSolarHistory::query()
            ->where('project_solar_profile_id', $profile->getKey())
            ->where('event_type', 'started')
            ->count());
    }

    public function test_start_is_idempotent_for_existing_dataset(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->createSolarProfile($this->project, ['quality_status' => 'incomplete']);

        $this->actingAs($engineer)
            ->post(route('companies.location-solar.start', [$this->company, $this->project]))
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]));

        $this->assertDatabaseCount('project_solar_profiles', 1);
        $this->assertDatabaseMissing('location_solar_histories', [
            'project_id' => $this->project->getKey(),
            'event_type' => 'started',
        ]);
    }

    public function test_viewer_cannot_start_dataset(): void
    {
        $viewer = $this->createCompanyUser('viewer');

        $this->actingAs($viewer)
            ->post(route('companies.location-solar.start', [$this->company, $this->project]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('project_solar_profiles', 0);
    }

    public function test_manage_own_user_can_start_only_owned_project(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');
        $this->customer->update(['portal_user_id' => $customerUser->getKey()]);

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00002',
            'name' => 'Other Solar Customer',
            'type' => 'commercial',
            'portal_user_id' => $otherCustomerUser->getKey(),
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
        $otherProject = $this->createProject($this->company, $otherCustomer, [
            'project_number' => 'NXR-2026-00002',
            'name' => 'Other Customer Project',
        ]);

        $this->actingAs($customerUser)
            ->post(route('companies.location-solar.start', [$this->company, $this->project]))
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]));

        $this->actingAs($customerUser)
            ->post(route('companies.location-solar.start', [$this->company, $otherProject]))
            ->assertNotFound();

        $this->assertDatabaseHas('project_solar_profiles', ['project_id' => $this->project->getKey()]);
        $this->assertDatabaseMissing('project_solar_profiles', ['project_id' => $otherProject->getKey()]);
    }

    public function test_manage_own_authorization_runs_before_field_validation(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');
        $this->customer->update(['portal_user_id' => $customerUser->getKey()]);

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00003',
            'name' => 'Restricted Solar Customer',
            'type' => 'commercial',
            'portal_user_id' => $otherCustomerUser->getKey(),
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
        $otherProject = $this->createProject($this->company, $otherCustomer, [
            'project_number' => 'NXR-2026-00003',
            'name' => 'Restricted Solar Project',
        ]);

        $this->actingAs($customerUser)
            ->put(route('companies.location-solar.update', [$this->company, $otherProject]), [])
            ->assertNotFound();
    }

    public function test_index_uses_truthful_registry_status_and_api_designated_copy(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->project->update([
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
        ]);
        $profile = $this->createSolarProfile($this->project, [
            'data_source' => 'api',
            'source_name' => 'Recorded external dataset',
            'source_reference' => 'Reference-2026-01',
            'quality_status' => 'imported',
        ]);
        $this->createCompleteMonthlyData($profile);

        $this->actingAs($engineer)
            ->get(route('companies.location-solar.index', $this->company))
            ->assertOk()
            ->assertSee('Dataset Complete')
            ->assertSee('API-designated source')
            ->assertSee('Recorded Project Coordinates')
            ->assertDontSee('Engineering ready')
            ->assertDontSee('Ready to verify')
            ->assertDontSee('Imported · ready')
            ->assertDontSee('Solar-resource engine online');
    }

    public function test_index_search_and_truthful_status_filter_are_server_side(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $completeProject = $this->createProject($this->company, $this->customer, [
            'project_number' => 'NXR-2026-COMPLETE',
            'name' => 'Bandung Complete Solar Dataset',
            'city' => 'Bandung',
            'province' => 'West Java',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ]);
        $completeProfile = $this->createSolarProfile($completeProject, [
            'quality_status' => 'estimated',
            'source_name' => 'Bandung recorded resource study',
        ]);
        $this->createCompleteMonthlyData($completeProfile);

        $reviewedProject = $this->createProject($this->company, $this->customer, [
            'project_number' => 'NXR-2026-REVIEWED',
            'name' => 'Reviewed Solar Dataset',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
        ]);
        $reviewedProfile = $this->createSolarProfile($reviewedProject, [
            'quality_status' => 'verified',
            'verified_by' => $engineer->getKey(),
            'verified_at' => now(),
        ]);
        $this->createCompleteMonthlyData($reviewedProfile);

        $this->actingAs($engineer)
            ->get(route('companies.location-solar.index', [
                'company' => $this->company,
                'status' => 'complete',
                'search' => 'Bandung',
            ]))
            ->assertOk()
            ->assertSee('Bandung Complete Solar Dataset')
            ->assertDontSee('Reviewed Solar Dataset')
            ->assertSee('Dataset Complete');
    }

    public function test_view_own_registry_does_not_leak_other_customer_projects(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');
        $this->customer->update(['portal_user_id' => $customerUser->getKey()]);

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-OWN-SCOPE',
            'name' => 'Other Scoped Customer',
            'type' => 'commercial',
            'portal_user_id' => $otherCustomerUser->getKey(),
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
        $otherProject = $this->createProject($this->company, $otherCustomer, [
            'project_number' => 'NXR-2026-OTHER-SCOPE',
            'name' => 'Other Customer Solar Project',
        ]);

        $this->actingAs($customerUser)
            ->get(route('companies.location-solar.index', $this->company))
            ->assertOk()
            ->assertSee($this->project->name)
            ->assertDontSee($otherProject->name);
    }

    public function test_index_excludes_archived_projects(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $archived = $this->createProject($this->company, $this->customer, [
            'project_number' => 'NXR-2026-ARCHIVED',
            'name' => 'Archived Solar Project',
            'archived_at' => now(),
        ]);
        $this->createSolarProfile($archived);

        $this->actingAs($engineer)
            ->get(route('companies.location-solar.index', $this->company))
            ->assertOk()
            ->assertDontSee('Archived Solar Project');
    }

    public function test_archived_project_cannot_start_or_verify_dataset(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->project->update(['archived_at' => now()]);

        $indexUrl = route('companies.location-solar.index', $this->company);
        $this->actingAs($engineer)
            ->from($indexUrl)
            ->post(route('companies.location-solar.start', [$this->company, $this->project]))
            ->assertRedirect($indexUrl)
            ->assertSessionHas('error');

        $profile = $this->createSolarProfile($this->project, ['quality_status' => 'estimated']);
        $this->createCompleteMonthlyData($profile);

        $this->actingAs($engineer)
            ->from($indexUrl)
            ->patch(route('companies.location-solar.verify', [$this->company, $this->project]))
            ->assertRedirect($indexUrl)
            ->assertSessionHas('error');

        $this->assertSame('estimated', $profile->fresh()->quality_status);
    }

    public function test_cross_company_start_is_not_found(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Other Energy',
            'slug' => 'other-energy',
            'code' => 'OTH',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('companies.location-solar.start', [$otherCompany, $this->project]))
            ->assertNotFound();

        $this->assertDatabaseCount('project_solar_profiles', 0);
    }

    public function test_verify_only_permission_cannot_review_a_project_without_project_resource_access(): void
    {
        $reviewer = User::factory()->create(['status' => 'active']);
        $membership = CompanyMembership::query()->create([
            'company_id' => $this->company->getKey(),
            'user_id' => $reviewer->getKey(),
            'status' => 'active',
            'invited_by' => $this->superAdmin->getKey(),
            'joined_at' => now(),
        ]);
        $role = Role::query()->create([
            'code' => 'solar_review_only',
            'name' => 'Solar Review Only',
            'scope' => 'company',
            'description' => 'Test-only role with dataset review permission but no project resource access.',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach(Permission::query()->where('code', 'location_solar.verify')->firstOrFail());
        $membership->roles()->attach($role, ['assigned_by' => $this->superAdmin->getKey()]);

        $this->project->update(['latitude' => -6.175392, 'longitude' => 106.827153]);
        $profile = $this->createSolarProfile($this->project, ['quality_status' => 'estimated']);
        $this->createCompleteMonthlyData($profile);

        $this->actingAs($reviewer)
            ->patch(route('companies.location-solar.verify', [$this->company, $this->project]))
            ->assertForbidden();

        $this->assertSame('estimated', $profile->fresh()->quality_status);
    }

    public function test_start_dataset_does_not_assign_a_silent_timezone(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->post(route('companies.location-solar.start', [$this->company, $this->project]))
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]));

        $this->assertNull(ProjectSolarProfile::query()->where('project_id', $this->project->getKey())->value('timezone'));
    }

    public function test_update_allows_city_and_province_to_remain_unrecorded(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->createSolarProfile($this->project);

        $payload = $this->validUpdatePayload();
        $payload['site_address'] = str_repeat('Long recorded site address ', 20);
        $payload['city'] = '';
        $payload['province'] = '';

        $this->actingAs($engineer)
            ->put(route('companies.location-solar.update', [$this->company, $this->project]), $payload)
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]));

        $this->project->refresh();
        $this->assertSame($payload['site_address'], $this->project->site_address);
        $this->assertNull($this->project->city);
        $this->assertNull($this->project->province);
    }

    public function test_put_update_cannot_create_a_missing_dataset(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->put(route('companies.location-solar.update', [$this->company, $this->project]), $this->validUpdatePayload())
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('project_solar_profiles', 0);
        $this->assertDatabaseCount('monthly_solar_data', 0);
    }

    public function test_partial_months_do_not_create_an_annual_ghi_total_or_solar_potential_verdict(): void
    {
        $profile = $this->createSolarProfile($this->project);

        foreach (range(1, 6) as $month) {
            MonthlySolarDatum::query()->create([
                'company_id' => $profile->company_id,
                'project_solar_profile_id' => $profile->getKey(),
                'month' => $month,
                'ghi_daily_kwh_m2' => 4.8,
                'peak_sun_hours' => 4.4,
                'ambient_temperature_c' => 28.5,
            ]);
        }

        $profile->load('monthlyData');
        $summary = $profile->calculateSummary($this->project);

        $this->assertSame(6, $summary['complete_months']);
        $this->assertNull($summary['annual_irradiation_kwh_m2']);
        $this->assertSame(4.8, $summary['average_ghi']);
        $this->assertSame('Not Assessed', $summary['potential_label']);
        $this->assertSame('Average across complete recorded months', $summary['average_basis_label']);
    }

    public function test_complete_months_produce_a_derived_annual_ghi_total_without_requiring_optional_poa(): void
    {
        $profile = $this->createSolarProfile($this->project);
        $this->createCompleteMonthlyData($profile);
        $profile->load('monthlyData');

        $summary = $profile->calculateSummary($this->project);

        $this->assertSame(12, $summary['complete_months']);
        $this->assertNotNull($summary['annual_irradiation_kwh_m2']);
        $this->assertNotNull($summary['average_ghi']);
        $this->assertSame(0, $summary['poa_months']);
    }

    public function test_review_records_workflow_evidence_without_invalidating_timestamp_based_downstream_freshness(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->project->update(['latitude' => -6.175392, 'longitude' => 106.827153]);
        $profile = $this->createSolarProfile($this->project, ['quality_status' => 'estimated']);
        $this->createCompleteMonthlyData($profile);
        $profile->refresh();
        $sourceUpdatedAt = $profile->updated_at->copy();

        $this->travel(10)->minutes();

        $this->actingAs($engineer)
            ->patch(route('companies.location-solar.verify', [$this->company, $this->project]))
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]));

        $profile->refresh();
        $this->assertSame('verified', $profile->quality_status);
        $this->assertNotNull($profile->verified_at);
        $this->assertTrue($profile->updated_at->equalTo($sourceUpdatedAt));
        $this->assertDatabaseHas('location_solar_histories', [
            'project_id' => $this->project->getKey(),
            'event_type' => 'reviewed',
        ]);
    }

    public function test_editing_a_reviewed_dataset_invalidates_the_review_and_returns_to_current_complete_state(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->project->update(['latitude' => -6.175392, 'longitude' => 106.827153]);
        $profile = $this->createSolarProfile($this->project, [
            'quality_status' => 'verified',
            'verified_by' => $engineer->getKey(),
            'verified_at' => now(),
        ]);
        $this->createCompleteMonthlyData($profile);

        $payload = $this->validUpdatePayload();
        $payload['source_name'] = 'Updated controlled reference';

        $this->actingAs($engineer)
            ->put(route('companies.location-solar.update', [$this->company, $this->project]), $payload)
            ->assertRedirect(route('companies.location-solar.show', [$this->company, $this->project]));

        $profile->refresh();
        $this->assertSame('estimated', $profile->quality_status);
        $this->assertNull($profile->verified_by);
        $this->assertNull($profile->verified_at);
    }

    public function test_show_uses_page_020_truthful_dataset_language(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->project->update(['latitude' => -6.175392, 'longitude' => 106.827153]);
        $profile = $this->createSolarProfile($this->project, [
            'data_source' => 'api',
            'quality_status' => 'imported',
            'source_name' => 'Recorded external resource dataset',
        ]);
        $this->createCompleteMonthlyData($profile);

        $this->actingAs($engineer)
            ->get(route('companies.location-solar.show', [$this->company, $this->project]))
            ->assertOk()
            ->assertSee('Dataset Complete')
            ->assertSee('API-designated source')
            ->assertSee('Derived Annual GHI')
            ->assertDontSee('API-derived dataset')
            ->assertDontSee('Ready to verify')
            ->assertDontSee('Engineering verification')
            ->assertDontSee('solar potential');
    }

    private function createSolarProfile(Project $project, array $overrides = []): ProjectSolarProfile
    {
        return ProjectSolarProfile::query()->create(array_merge([
            'company_id' => $project->company_id,
            'project_id' => $project->getKey(),
            'data_source' => 'manual',
            'source_name' => 'Recorded project resource study',
            'timezone' => null,
            'quality_status' => 'incomplete',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ], $overrides));
    }

    private function createCompleteMonthlyData(ProjectSolarProfile $profile): void
    {
        foreach (range(1, 12) as $month) {
            MonthlySolarDatum::query()->create([
                'company_id' => $profile->company_id,
                'project_solar_profile_id' => $profile->getKey(),
                'month' => $month,
                'ghi_daily_kwh_m2' => 4.8,
                'peak_sun_hours' => 4.4,
                'ambient_temperature_c' => 28.5,
            ]);
        }
    }

    private function validUpdatePayload(): array
    {
        $monthly = [];
        foreach (range(1, 12) as $month) {
            $monthly[$month] = [
                'ghi_daily_kwh_m2' => 4.8,
                'poa_daily_kwh_m2' => null,
                'peak_sun_hours' => 4.4,
                'ambient_temperature_c' => 28.5,
            ];
        }

        return [
            'site_address' => 'Updated project site',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'postal_code' => '28100',
            'latitude' => -0.507068,
            'longitude' => 101.447779,
            'elevation_m' => null,
            'timezone' => null,
            'data_source' => 'manual',
            'source_name' => 'Recorded project resource study',
            'source_reference' => null,
            'source_date' => null,
            'notes' => null,
            'monthly' => $monthly,
        ];
    }

    private function createProject(Company $company, Customer $customer, array $overrides = []): Project
    {
        return Project::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => 'NXR-'.fake()->unique()->numerify('2026-#####'),
            'name' => 'Solar Resource Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Project site',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ], $overrides));
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
