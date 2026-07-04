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

class CustomerProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

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
    }

    public function test_company_admin_can_create_a_customer(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');

        $response = $this
            ->actingAs($companyAdmin)
            ->post(route('companies.customers.store', $this->company), [
                'name' => 'PT Solar Customer',
                'type' => 'commercial',
                'email' => 'customer@example.com',
                'phone' => '+62 812 3456 7890',
                'address' => 'Pekanbaru, Riau',
                'city' => 'Pekanbaru',
                'province' => 'Riau',
            ]);

        $customer = Customer::query()->firstOrFail();

        $response->assertRedirect(route(
            'companies.customers.show',
            [$this->company, $customer]
        ));

        $this->assertSame($this->company->id, $customer->company_id);
        $this->assertSame('NXR-CUS-00001', $customer->customer_number);
        $this->assertSame($companyAdmin->id, $customer->created_by);
    }

    public function test_engineer_can_create_a_project_with_company_scoped_assignments(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $sales = $this->createCompanyUser('sales');

        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($engineer)
            ->post(route('companies.projects.store', $this->company), [
                'customer_id' => $customer->id,
                'name' => 'Rooftop PV Project',
                'building_type' => 'commercial',
                'proposed_system' => 'on_grid',
                'site_address' => 'Pekanbaru, Riau',
                'city' => 'Pekanbaru',
                'province' => 'Riau',
                'latitude' => 0.507067,
                'longitude' => 101.447779,
                'assigned_engineer_id' => $engineer->id,
                'assigned_sales_id' => $sales->id,
            ]);

        $project = Project::query()->firstOrFail();

        $response->assertRedirect(route(
            'companies.projects.show',
            [$this->company, $project]
        ));

        $this->assertSame('NXR-'.now()->format('Y').'-00001', $project->project_number);
        $this->assertSame('draft', $project->status);
        $this->assertSame($engineer->id, $project->assigned_engineer_id);
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'from_status' => null,
            'to_status' => 'draft',
        ]);
    }

    public function test_customer_can_create_a_project_only_for_their_linked_profile(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $ownedCustomer = $this->createCustomer($customerUser);

        $response = $this
            ->actingAs($customerUser)
            ->post(route('companies.projects.store', $this->company), [
                'name' => 'Customer Requested Project',
                'building_type' => 'residential',
                'proposed_system' => 'undecided',
                'site_address' => 'Pekanbaru, Riau',
            ]);

        $project = Project::query()->firstOrFail();

        $response->assertRedirect(route(
            'companies.projects.show',
            [$this->company, $project]
        ));

        $this->assertSame($ownedCustomer->id, $project->customer_id);
        $this->assertNull($project->assigned_engineer_id);
        $this->assertNull($project->assigned_sales_id);
    }

    public function test_customer_only_sees_projects_linked_to_their_portal_profile(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');

        $ownedCustomer = $this->createCustomer($customerUser);
        $otherCustomer = $this->createCustomer($otherCustomerUser);

        $ownedProject = $this->createProject($ownedCustomer, 'Owned Project');
        $otherProject = $this->createProject($otherCustomer, 'Other Project');

        $this
            ->actingAs($customerUser)
            ->get(route('companies.projects.index', $this->company))
            ->assertOk()
            ->assertSee('Owned Project')
            ->assertDontSee('Other Project');

        $this
            ->actingAs($customerUser)
            ->get(route(
                'companies.projects.show',
                [$this->company, $ownedProject]
            ))
            ->assertOk();

        $this
            ->actingAs($customerUser)
            ->get(route(
                'companies.projects.show',
                [$this->company, $otherProject]
            ))
            ->assertNotFound();
    }

    public function test_project_status_transition_creates_an_audit_record(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $customer = $this->createCustomer();
        $project = $this->createProject($customer, 'Status Project');

        $this
            ->actingAs($engineer)
            ->put(route(
                'companies.projects.update',
                [$this->company, $project]
            ), [
                'customer_id' => $customer->id,
                'name' => $project->name,
                'building_type' => $project->building_type,
                'proposed_system' => $project->proposed_system,
                'site_address' => $project->site_address,
                'status' => 'survey',
                'status_note' => 'Initial survey scheduled.',
            ])
            ->assertRedirect(route(
                'companies.projects.show',
                [$this->company, $project]
            ));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'survey',
            'status_updated_by' => $engineer->id,
        ]);

        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'from_status' => 'draft',
            'to_status' => 'survey',
            'note' => 'Initial survey scheduled.',
            'changed_by' => $engineer->id,
        ]);
    }

    public function test_nested_binding_prevents_cross_company_customer_access(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');

        $otherCompany = Company::query()->create([
            'name' => 'Other Solar Company',
            'slug' => 'other-solar-company',
            'code' => 'OSC',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $otherCustomer = Customer::query()->create([
            'company_id' => $otherCompany->id,
            'customer_number' => 'OSC-CUS-00001',
            'name' => 'Other Customer',
            'type' => 'residential',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);

        $this
            ->actingAs($companyAdmin)
            ->get(route(
                'companies.customers.show',
                [$this->company, $otherCustomer]
            ))
            ->assertNotFound();
    }

    private function createCompanyUser(string $roleCode): User
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $membership = CompanyMembership::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by' => $this->superAdmin->id,
            'joined_at' => now(),
        ]);

        $role = Role::query()
            ->where('code', $roleCode)
            ->where('scope', 'company')
            ->firstOrFail();

        $membership->roles()->attach($role, [
            'assigned_by' => $this->superAdmin->id,
        ]);

        return $user;
    }

    private function createCustomer(?User $portalUser = null): Customer
    {
        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Customer '.fake()->unique()->word(),
            'type' => 'residential',
            'email' => fake()->unique()->safeEmail(),
            'portal_user_id' => $portalUser?->id,
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);

        $customer->update([
            'customer_number' => sprintf(
                'NXR-CUS-%05d',
                $customer->id
            ),
        ]);

        return $customer;
    }

    private function createProject(
        Customer $customer,
        string $name
    ): Project {
        $project = Project::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'name' => $name,
            'building_type' => 'residential',
            'proposed_system' => 'undecided',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
            'status_updated_by' => $this->superAdmin->id,
            'status_changed_at' => now(),
        ]);

        $project->update([
            'project_number' => sprintf(
                'NXR-%s-%05d',
                now()->format('Y'),
                $project->id
            ),
        ]);

        return $project;
    }
}
