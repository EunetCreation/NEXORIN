<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Role;
use App\Models\TechnicalCalculation;
use App\Models\TechnicalCalculationOverride;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicalCalculationOverrideTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private Customer $customer;

    private Project $project;

    private TechnicalCalculation $calculation;

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
            'name' => 'Override Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $this->customer->getKey(),
            'project_number' => 'NXR-2026-00001',
            'name' => 'Commercial Override Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'status' => 'engineering',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->calculation = TechnicalCalculation::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'system_type' => 'on_grid',
            'result_status' => 'detailed',
            'results' => $this->resultPayload(),
            'engine_results' => $this->resultPayload(),
            'warnings' => [],
            'source_snapshot' => [],
            'calculated_by' => $this->superAdmin->getKey(),
            'calculated_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
    }

    public function test_engineer_can_override_a_technical_result_with_audit_trail(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->post(route('companies.technical-calculations.overrides.store', [$this->company, $this->project]), [
                'field' => 'installed_pv_kw',
                'override_value' => 7.2,
                'reason' => 'Adjusted after verified roof layout and final module arrangement.',
            ])
            ->assertRedirect(route('companies.technical-calculations.show', [$this->company, $this->project]))
            ->assertSessionHas('success');

        $this->calculation->refresh();

        $this->assertSame(7.2, $this->calculation->results['installed_pv_kw']);
        $this->assertSame(6.6, $this->calculation->engine_results['installed_pv_kw']);
        $this->assertNotEmpty($this->calculation->override_summary);

        $this->assertDatabaseHas('technical_calculation_overrides', [
            'technical_calculation_id' => $this->calculation->getKey(),
            'field' => 'installed_pv_kw',
            'engine_value' => '6.6',
            'previous_value' => '6.6',
            'override_value' => '7.2',
            'is_active' => true,
            'created_by' => $engineer->getKey(),
        ]);

        $this->assertDatabaseHas('technical_calculation_histories', [
            'technical_calculation_id' => $this->calculation->getKey(),
            'event' => 'overridden',
            'user_id' => $engineer->getKey(),
        ]);
    }

    public function test_engineer_can_reset_an_active_override_to_engine_value(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->post(route('companies.technical-calculations.overrides.store', [$this->company, $this->project]), [
                'field' => 'module_count',
                'override_value' => 14,
                'reason' => 'Adjusted after confirmed module placement on the usable roof area.',
            ]);

        $override = TechnicalCalculationOverride::query()
            ->where('technical_calculation_id', $this->calculation->getKey())
            ->where('field', 'module_count')
            ->firstOrFail();

        $this->actingAs($engineer)
            ->delete(route('companies.technical-calculations.overrides.reset', [$this->company, $this->project, $override]))
            ->assertRedirect(route('companies.technical-calculations.show', [$this->company, $this->project]))
            ->assertSessionHas('success');

        $this->calculation->refresh();
        $override->refresh();

        $this->assertSame(12, $this->calculation->results['module_count']);
        $this->assertFalse($override->is_active);
        $this->assertEmpty($this->calculation->override_summary);

        $this->assertDatabaseHas('technical_calculation_histories', [
            'technical_calculation_id' => $this->calculation->getKey(),
            'event' => 'override_reset',
            'user_id' => $engineer->getKey(),
        ]);
    }

    public function test_verified_calculation_blocks_new_override(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->calculation->update([
            'result_status' => 'verified',
            'verified_by' => $engineer->getKey(),
            'verified_at' => now(),
        ]);

        $this->actingAs($engineer)
            ->from(route('companies.technical-calculations.show', [$this->company, $this->project]))
            ->post(route('companies.technical-calculations.overrides.store', [$this->company, $this->project]), [
                'field' => 'dc_ac_ratio',
                'override_value' => 1.18,
                'reason' => 'Attempted correction after verification should be blocked.',
            ])
            ->assertRedirect(route('companies.technical-calculations.show', [$this->company, $this->project]))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('technical_calculation_overrides', [
            'technical_calculation_id' => $this->calculation->getKey(),
            'field' => 'dc_ac_ratio',
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function resultPayload(): array
    {
        return [
            'required_pv_kw' => 6.5,
            'installed_pv_kw' => 6.6,
            'module_count' => 12,
            'area_limited_module_count' => 30,
            'inverter_count' => 1,
            'total_inverter_ac_kw' => 5,
            'dc_ac_ratio' => 1.32,
            'battery_nominal_kwh' => 0,
            'battery_minimum_power_kw' => 0,
            'estimated_daily_generation_kwh' => 25.1,
            'annual_energy_kwh' => 9161,
            'minimum_modules_per_string' => 3,
            'maximum_modules_per_string' => 11,
            'modules_per_string' => 6,
            'string_count' => 2,
            'string_voc_cold_v' => 310.2,
            'string_vmp_hot_v' => 221.8,
            'dc_input_current_a' => 26.32,
            'dc_voltage_drop_percent' => 1.1,
            'ac_voltage_drop_percent' => 0.8,
            'protection' => [
                'dc_isolator_a' => 40,
                'dc_fuse_a' => 20,
                'ac_breaker_a' => 25,
                'dc_spd' => 'Type II DC SPD',
                'ac_spd' => 'Type II AC SPD',
            ],
        ];
    }
}
