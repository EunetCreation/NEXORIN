<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
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

        $this->superAdmin->systemRoles()->attach($role, [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);
    }

    public function test_super_admin_can_open_company_directory(): void
    {
        Company::query()->create([
            'name' => 'NEXORIN Energy',
            'slug' => 'nexorin-energy',
            'code' => 'NXR',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $response = $this
            ->actingAs($this->superAdmin)
            ->get(route('companies.index'));

        $response
            ->assertOk()
            ->assertSee('Companies')
            ->assertSee('NEXORIN Energy');
    }

    public function test_regular_user_cannot_open_company_directory(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->get(route('companies.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_update_and_archive_company(): void
    {
        $createResponse = $this
            ->actingAs($this->superAdmin)
            ->post(route('companies.store'), [
                'name' => 'Solar Lab Indonesia',
                'code' => 'sli',
                'registration_number' => 'REG-2026-001',
                'email' => 'hello@solarlab.test',
                'phone' => '+62 761 555 0101',
                'address' => 'Pekanbaru, Riau',
                'status' => 'active',
            ]);

        $company = Company::query()
            ->where('code', 'SLI')
            ->firstOrFail();

        $createResponse
            ->assertRedirect(route('companies.show', $company))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', [
            'id' => $company->getKey(),
            'name' => 'Solar Lab Indonesia',
            'slug' => 'solar-lab-indonesia',
            'code' => 'SLI',
            'status' => 'active',
        ]);

        $updateResponse = $this
            ->actingAs($this->superAdmin)
            ->put(route('companies.update', $company), [
                'name' => 'Solar Lab Nusantara',
                'code' => 'sln',
                'registration_number' => 'REG-2026-001',
                'email' => 'hello@solarlab.test',
                'phone' => '+62 761 555 0101',
                'address' => 'Pekanbaru, Riau',
                'status' => 'inactive',
            ]);

        $updateResponse
            ->assertRedirect(route('companies.show', $company))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', [
            'id' => $company->getKey(),
            'name' => 'Solar Lab Nusantara',
            'slug' => 'solar-lab-nusantara',
            'code' => 'SLN',
            'status' => 'inactive',
        ]);

        $archiveResponse = $this
            ->actingAs($this->superAdmin)
            ->delete(route('companies.destroy', $company));

        $archiveResponse
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('companies', [
            'id' => $company->getKey(),
        ]);
    }

    public function test_active_company_must_be_deactivated_before_archiving(): void
    {
        $company = Company::query()->create([
            'name' => 'Active Solar Company',
            'slug' => 'active-solar-company',
            'code' => 'ASC',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $response = $this
            ->actingAs($this->superAdmin)
            ->from(route('companies.show', $company))
            ->delete(route('companies.destroy', $company));

        $response
            ->assertRedirect(route('companies.show', $company))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('companies', [
            'id' => $company->getKey(),
            'deleted_at' => null,
        ]);
    }
}