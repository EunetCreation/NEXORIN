<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Role $superAdminRole;

    private Role $engineerRole;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::query()->create([
            'code' => 'super_admin',
            'name' => 'Super Admin',
            'scope' => 'system',
            'description' => 'Full platform administration access.',
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->engineerRole = Role::query()->create([
            'code' => 'engineer',
            'name' => 'Engineer',
            'scope' => 'company',
            'description' => 'Engineering access.',
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'status' => 'active',
        ]);

        $this->superAdmin->systemRoles()->attach($this->superAdminRole, [
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

    public function test_super_admin_can_open_user_directory(): void
    {
        $this
            ->actingAs($this->superAdmin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Users')
            ->assertSee($this->superAdmin->email);
    }

    public function test_regular_user_cannot_open_user_directory(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_company_role(): void
    {
        $response = $this
            ->actingAs($this->superAdmin)
            ->post(route('users.store'), [
                'name' => 'Solar Engineer',
                'email' => 'solar.engineer@example.test',
                'password' => 'Engineer@2026!',
                'password_confirmation' => 'Engineer@2026!',
                'status' => 'active',
                'is_super_admin' => '0',
                'memberships' => [
                    [
                        'company_id' => $this->company->getKey(),
                        'role_id' => $this->engineerRole->getKey(),
                        'status' => 'active',
                    ],
                ],
            ]);

        $user = User::query()
            ->where('email', 'solar.engineer@example.test')
            ->firstOrFail();

        $response
            ->assertRedirect(route('users.show', $user))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'status' => 'active',
        ]);

        $membership = $user->companyMemberships()
            ->where('company_id', $this->company->getKey())
            ->firstOrFail();

        $this->assertTrue($membership->hasRole('engineer'));
    }

    public function test_super_admin_can_update_and_deactivate_another_user(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $updateResponse = $this
            ->actingAs($this->superAdmin)
            ->put(route('users.update', $user), [
                'name' => 'Updated User',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
                'status' => 'active',
                'is_super_admin' => '0',
                'memberships' => [],
            ]);

        $updateResponse
            ->assertRedirect(route('users.show', $user))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Updated User',
        ]);

        $statusResponse = $this
            ->actingAs($this->superAdmin)
            ->patch(route('users.status', $user), [
                'status' => 'inactive',
            ]);

        $statusResponse->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'status' => 'inactive',
        ]);
    }

    public function test_super_admin_cannot_deactivate_own_account(): void
    {
        $response = $this
            ->actingAs($this->superAdmin)
            ->from(route('users.show', $this->superAdmin))
            ->patch(route('users.status', $this->superAdmin), [
                'status' => 'inactive',
            ]);

        $response
            ->assertRedirect(route('users.show', $this->superAdmin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->getKey(),
            'status' => 'active',
        ]);
    }

    public function test_inactive_user_is_blocked_by_active_account_middleware(): void
    {
        $user = User::factory()->create([
            'status' => 'inactive',
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }
}
