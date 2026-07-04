<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPermissionGuardTest extends TestCase
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

    public function test_company_admin_can_view_confidential_cost_prices(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');

        $this
            ->actingAs($companyAdmin)
            ->get(route(
                'companies.permission-tests.cost-price',
                $this->company
            ))
            ->assertOk()
            ->assertJsonPath(
                'permission',
                'price.cost.view'
            );
    }

    public function test_engineer_can_create_projects_and_verify_calculations(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->get(route(
                'companies.permission-tests.project-create',
                $this->company
            ))
            ->assertOk()
            ->assertJsonPath('permission', 'project.create');

        $this
            ->actingAs($engineer)
            ->get(route(
                'companies.permission-tests.calculation-verify',
                $this->company
            ))
            ->assertOk()
            ->assertJsonPath('permission', 'calculation.verify');
    }

    public function test_engineer_cannot_view_confidential_cost_prices(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->get(route(
                'companies.permission-tests.cost-price',
                $this->company
            ))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(
                'error',
                'You do not have permission to access this feature.'
            );
    }

    public function test_sales_can_create_quotations_but_cannot_view_cost_prices(): void
    {
        $sales = $this->createCompanyUser('sales');

        $this
            ->actingAs($sales)
            ->get(route(
                'companies.permission-tests.quotation-create',
                $this->company
            ))
            ->assertOk()
            ->assertJsonPath('permission', 'quotation.create');

        $this
            ->actingAs($sales)
            ->getJson(route(
                'companies.permission-tests.cost-price',
                $this->company
            ))
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have permission to access this feature.',
            ]);
    }

    public function test_customer_can_view_owned_projects_but_cannot_create_company_projects(): void
    {
        $customer = $this->createCompanyUser('customer');

        $this
            ->actingAs($customer)
            ->get(route(
                'companies.permission-tests.owned-project',
                $this->company
            ))
            ->assertOk()
            ->assertJsonPath('permission', 'project.view_own');

        $this
            ->actingAs($customer)
            ->getJson(route(
                'companies.permission-tests.project-create',
                $this->company
            ))
            ->assertForbidden();
    }

    public function test_reviewer_can_approve_calculations(): void
    {
        $reviewer = $this->createCompanyUser('reviewer');

        $this
            ->actingAs($reviewer)
            ->get(route(
                'companies.permission-tests.calculation-approval',
                $this->company
            ))
            ->assertOk()
            ->assertJsonPath('permission', 'calculation.approve');
    }

    public function test_super_admin_bypasses_company_permission_assignments(): void
    {
        $this
            ->actingAs($this->superAdmin)
            ->get(route(
                'companies.permission-tests.cost-price',
                $this->company
            ))
            ->assertOk();
    }

    public function test_membership_in_another_company_does_not_grant_permission(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Other Solar Company',
            'slug' => 'other-solar-company',
            'code' => 'OSC',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $sales = $this->createCompanyUser('sales', $otherCompany);

        $this
            ->actingAs($sales)
            ->getJson(route(
                'companies.permission-tests.quotation-create',
                $this->company
            ))
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have access to this company.',
            ]);
    }

    private function createCompanyUser(
        string $roleCode,
        ?Company $company = null
    ): User {
        $company ??= $this->company;

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $membership = CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
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
