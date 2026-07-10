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

    public function test_company_member_can_open_the_redesigned_dashboard(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create([
            'name' => 'Amanda Putri',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'name' => 'NEXORIN Energy',
            'slug' => 'nexorin-energy',
            'code' => 'NXR',
            'status' => 'active',
            'created_by' => $user->getKey(),
        ]);

        $membership = CompanyMembership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
            'invited_by' => $user->getKey(),
            'joined_at' => now(),
        ]);

        $companyAdminRole = Role::query()
            ->where('code', 'company_admin')
            ->where('scope', 'company')
            ->firstOrFail();

        $membership->roles()->attach($companyAdminRole, [
            'assigned_by' => $user->getKey(),
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_number' => 'NXR-CUS-00001',
            'name' => 'Solar Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        Project::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => 'NXR-2026-00001',
            'name' => 'Commercial Rooftop Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'engineering',
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
            'status_updated_by' => $user->getKey(),
            'status_changed_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Good')
            ->assertSee('Project Pipeline')
            ->assertSee('Project Work Queue')
            ->assertSee('Catalog Readiness')
            ->assertSee('Commercial Rooftop Project');
    }
}
