<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Role;
use App\Models\RoofSiteSurvey;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoofSiteSurveyManagementTest extends TestCase
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
            'name' => 'Roof Survey Customer',
            'type' => 'residential',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $this->customer->getKey(),
            'project_number' => 'NXR-2026-00001',
            'name' => 'Residential Roof Survey',
            'building_type' => 'residential',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);
    }

    public function test_engineer_can_record_surface_and_verify_complete_survey(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->put(route('companies.roof-site.update', [$this->company, $this->project]), [
                'survey_date' => '2026-07-03',
                'surveyed_by_user_id' => $engineer->getKey(),
                'access_method' => 'fixed_ladder',
                'access_condition' => 'good',
                'mounting_preference' => 'roof_parallel',
                'structural_review_status' => 'not_required',
                'fall_protection_available' => 1,
                'lightning_protection_present' => 1,
                'electrical_room_distance_m' => 12,
                'estimated_cable_route_m' => 20,
            ])
            ->assertRedirect(route('companies.roof-site.show', [$this->company, $this->project]));

        $this->actingAs($engineer)
            ->post(route('companies.roof-site.surfaces.store', [$this->company, $this->project]), [
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
            ])
            ->assertRedirect(route('companies.roof-site.show', [$this->company, $this->project]));

        $survey = RoofSiteSurvey::query()->with('surfaces')->where('project_id', $this->project->getKey())->firstOrFail();
        $summary = $survey->calculateSummary();

        $this->assertSame(100.0, $summary['gross_area_m2']);
        $this->assertSame(80.0, $summary['usable_area_m2']);
        $this->assertTrue($summary['is_ready_for_verification']);
        $this->assertSame('ready', $survey->fresh()->quality_status);

        $this->actingAs($engineer)
            ->patch(route('companies.roof-site.verify', [$this->company, $this->project]))
            ->assertRedirect(route('companies.roof-site.show', [$this->company, $this->project]));

        $this->assertDatabaseHas('roof_site_surveys', [
            'project_id' => $this->project->getKey(),
            'quality_status' => 'verified',
            'verified_by' => $engineer->getKey(),
        ]);
    }

    public function test_customer_can_open_only_owned_project_survey(): void
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
            'name' => 'Other Roof Survey',
            'building_type' => 'residential',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->actingAs($customerUser)
            ->get(route('companies.roof-site.show', [$this->company, $this->project]))
            ->assertOk();

        $this->actingAs($customerUser)
            ->get(route('companies.roof-site.show', [$this->company, $otherProject]))
            ->assertNotFound();
    }

    public function test_viewer_cannot_modify_survey(): void
    {
        $viewer = $this->createCompanyUser('viewer');

        $this->actingAs($viewer)
            ->put(route('companies.roof-site.update', [$this->company, $this->project]), [
                'access_condition' => 'good',
                'mounting_preference' => 'undecided',
                'structural_review_status' => 'not_assessed',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
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
