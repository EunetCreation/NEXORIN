<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAccessGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $superAdminRole = Role::query()->create([
            'code' => 'super_admin',
            'name' => 'Super Admin',
            'scope' => 'system',
            'description' => 'Full platform administration access.',
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'status' => 'active',
        ]);

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

    public function test_active_member_can_access_active_company(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->companyMemberships()->create([
            'company_id' => $this->company->getKey(),
            'status' => 'active',
            'invited_by' => $this->superAdmin->getKey(),
            'joined_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('companies.access-test', $this->company))
            ->assertOk()
            ->assertJsonPath('message', 'Company access granted.');
    }

    public function test_inactive_company_blocks_an_active_member(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->companyMemberships()->create([
            'company_id' => $this->company->getKey(),
            'status' => 'active',
            'invited_by' => $this->superAdmin->getKey(),
            'joined_at' => now(),
        ]);

        $this->company->update([
            'status' => 'inactive',
        ]);

        $this
            ->actingAs($user)
            ->get(route('companies.access-test', $this->company))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(
                'error',
                'This company is inactive. Please contact a NEXORIN administrator.'
            );
    }

    public function test_inactive_membership_blocks_company_access(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->companyMemberships()->create([
            'company_id' => $this->company->getKey(),
            'status' => 'inactive',
            'invited_by' => $this->superAdmin->getKey(),
            'joined_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('companies.access-test', $this->company))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(
                'error',
                'Your access to this company is inactive. Please contact your company administrator.'
            );
    }

    public function test_user_without_membership_cannot_access_company(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->get(route('companies.access-test', $this->company))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(
                'error',
                'You do not have access to this company.'
            );
    }

    public function test_json_request_receives_forbidden_response_for_inactive_company(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->companyMemberships()->create([
            'company_id' => $this->company->getKey(),
            'status' => 'active',
            'invited_by' => $this->superAdmin->getKey(),
            'joined_at' => now(),
        ]);

        $this->company->update([
            'status' => 'inactive',
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('companies.access-test', $this->company))
            ->assertForbidden()
            ->assertJson([
                'message' => 'This company is inactive. Please contact a NEXORIN administrator.',
            ]);
    }

    public function test_super_admin_can_manage_an_inactive_company(): void
    {
        $this->company->update([
            'status' => 'inactive',
        ]);

        $this
            ->actingAs($this->superAdmin)
            ->get(route('companies.show', $this->company))
            ->assertOk()
            ->assertSee($this->company->name);
    }
}
