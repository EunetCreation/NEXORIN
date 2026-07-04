<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\LoadAnalysis;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadAnalysisManagementTest extends TestCase
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

        $this->superAdmin = User::factory()->create([
            'status' => 'active',
        ]);

        $superAdminRole = Role::query()
            ->where('code', 'super_admin')
            ->firstOrFail();

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
            'name' => 'Load Analysis Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $this->customer->getKey(),
            'project_number' => 'NXR-2026-00001',
            'name' => 'Commercial Rooftop Project',
            'building_type' => 'commercial',
            'proposed_system' => 'hybrid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);
    }

    public function test_engineer_can_create_load_inputs_and_view_calculated_summary(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->put(route('companies.load-analyses.update', [
                $this->company,
                $this->project,
            ]), [
                'source_mode' => 'combined',
                'connected_power_va' => 23000,
                'supply_phase' => 'three_phase',
                'tariff_group' => 'B-2/TR',
                'electricity_rate_per_kwh' => 1444.70,
                'billing_days' => 30,
                'daytime_start_hour' => 6,
                'daytime_end_hour' => 18,
                'notes' => 'Validated during the initial survey.',
            ])
            ->assertRedirect(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]));

        $this
            ->actingAs($engineer)
            ->post(route('companies.load-analyses.appliances.store', [
                $this->company,
                $this->project,
            ]), [
                'name' => 'Air Conditioner',
                'category' => 'Cooling',
                'quantity' => 2,
                'rated_power_w' => 1000,
                'operating_hours_per_day' => 8,
                'usage_factor' => 0.75,
                'start_hour' => 8,
                'starting_multiplier' => 3,
                'is_critical' => 1,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]));

        $this
            ->actingAs($engineer)
            ->post(route('companies.load-analyses.bills.store', [
                $this->company,
                $this->project,
            ]), [
                'billing_month' => '2026-06',
                'energy_kwh' => 360,
                'bill_amount' => 520092,
                'peak_demand_kw' => 1.5,
            ])
            ->assertRedirect(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]));

        $analysis = LoadAnalysis::query()
            ->with(['appliances', 'electricityBills'])
            ->where('project_id', $this->project->getKey())
            ->firstOrFail();

        $summary = $analysis->calculateSummary();

        $this->assertSame(12.0, $summary['daily_energy_kwh']);
        $this->assertSame(360.0, $summary['monthly_energy_kwh']);
        $this->assertSame(12.0, $summary['critical_daily_energy_kwh']);
        $this->assertSame(360.0, $summary['average_bill_kwh']);

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]))
            ->assertOk()
            ->assertSee('Air Conditioner')
            ->assertSee('24-Hour Load Profile');
    }

    public function test_customer_can_manage_only_their_owned_project_load_analysis(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');

        $this->customer->update([
            'portal_user_id' => $customerUser->getKey(),
        ]);

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
            'name' => 'Other Project',
            'building_type' => 'residential',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'draft',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this
            ->actingAs($customerUser)
            ->get(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]))
            ->assertOk();

        $this
            ->actingAs($customerUser)
            ->get(route('companies.load-analyses.show', [
                $this->company,
                $otherProject,
            ]))
            ->assertNotFound();
    }

    public function test_viewer_cannot_modify_load_analysis(): void
    {
        $viewer = $this->createCompanyUser('viewer');

        $this
            ->actingAs($viewer)
            ->put(route('companies.load-analyses.update', [
                $this->company,
                $this->project,
            ]), [
                'source_mode' => 'combined',
                'supply_phase' => 'single_phase',
                'billing_days' => 30,
                'daytime_start_hour' => 6,
                'daytime_end_hour' => 18,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    private function createCompanyUser(string $roleCode): User
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

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
