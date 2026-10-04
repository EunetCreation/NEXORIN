<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_member_sees_truthful_company_scoped_command_center(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'name' => 'Amanda Putri',
            'status' => 'active',
        ]);

        $company = $this->createCompanyFor($user, 'NEXORIN Energy', 'nexorin-energy', 'NXR');
        $this->attachRole($user, $company, 'company_admin');

        $customer = $this->createCustomer($company, $user, 'NXR-CUS-00001', 'Solar Customer');
        $this->createProject($company, $customer, $user, 'NXR-2026-00001', 'Commercial Rooftop Project');

        $foreignCompany = $this->createCompanyFor($user, 'Foreign Tenant', 'foreign-tenant', 'FRN');
        // The authenticated user deliberately receives no membership in the foreign tenant.
        $foreignCustomer = $this->createCustomer($foreignCompany, $user, 'FRN-CUS-00001', 'Foreign Customer');
        $this->createProject($foreignCompany, $foreignCustomer, $user, 'FRN-2026-00001', 'Foreign Tenant Project');

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Command Center')
            ->assertSee('Current Projects')
            ->assertSee('Engineering Records')
            ->assertSee('Catalog Approval')
            ->assertSee('Recorded Selling Value')
            ->assertSee('Project Pipeline')
            ->assertSee('System Mix')
            ->assertSee('Project Work Queue')
            ->assertSee('Continue Work')
            ->assertSee('Exact Project Coordinates')
            ->assertSee('Commercial Rooftop Project')
            ->assertDontSee('Foreign Tenant Project')
            ->assertDontSee('Needs Attention')
            ->assertDontSee('Designed Capacity')
            ->assertDontSee('Commercial Value')
            ->assertDontSee('>Live<', false)
            ->assertDontSee('No telemetry or synthetic portfolio intelligence is implied.');
    }

    public function test_customer_role_only_sees_projects_owned_through_its_customer_record(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'name' => 'Customer Portal User',
            'status' => 'active',
        ]);
        $staff = User::factory()->create(['status' => 'active']);

        $company = $this->createCompanyFor($staff, 'Customer Scoped Solar', 'customer-scoped-solar', 'CSS');
        $this->attachRole($user, $company, 'customer');

        $ownedCustomer = $this->createCustomer($company, $staff, 'CSS-CUS-00001', 'Owned Customer', $user);
        $otherCustomer = $this->createCustomer($company, $staff, 'CSS-CUS-00002', 'Other Customer');

        $this->createProject($company, $ownedCustomer, $staff, 'CSS-2026-00001', 'Owned Portal Project');
        $this->createProject($company, $otherCustomer, $staff, 'CSS-2026-00002', 'Other Customer Project');

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Owned Portal Project')
            ->assertDontSee('Other Customer Project')
            ->assertSee('Engineering Records')
            ->assertSee('Open Quotations')
            ->assertDontSee('Catalog Approval')
            ->assertDontSee('Recorded Selling Value');
    }

    private function createCompanyFor(User $creator, string $name, string $slug, string $code): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => $slug,
            'code' => $code,
            'status' => 'active',
            'created_by' => $creator->getKey(),
        ]);
    }

    private function attachRole(User $user, Company $company, string $roleCode): void
    {
        $membership = CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
            'invited_by' => $user->getKey(),
            'joined_at' => now(),
        ]);

        $role = Role::query()
            ->where('code', $roleCode)
            ->where('scope', 'company')
            ->firstOrFail();

        $membership->roles()->attach($role, [
            'assigned_by' => $user->getKey(),
        ]);
    }

    private function createCustomer(
        Company $company,
        User $actor,
        string $number,
        string $name,
        ?User $portalUser = null,
    ): Customer {
        return Customer::query()->create([
            'company_id' => $company->getKey(),
            'portal_user_id' => $portalUser?->getKey(),
            'customer_number' => $number,
            'name' => $name,
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ]);
    }

    private function createProject(
        Company $company,
        Customer $customer,
        User $actor,
        string $number,
        string $name,
    ): Project {
        return Project::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => $number,
            'name' => $name,
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'engineering',
            'created_by' => $actor->getKey(),
            'updated_by' => $actor->getKey(),
            'status_updated_by' => $actor->getKey(),
            'status_changed_at' => now(),
        ]);
    }
}
