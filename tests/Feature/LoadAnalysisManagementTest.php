<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\ElectricityBill;
use App\Models\LoadAnalysis;
use App\Models\LoadAppliance;
use App\Models\SystemRecommendation;
use App\Models\TechnicalCalculation;
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
            ->assertSee('24-Hour Modeled Load Profile');
    }


    public function test_equipment_create_redirects_when_current_load_analysis_does_not_exist(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.appliances.create', [
                $this->company,
                $this->project,
            ]))
            ->assertRedirect(route('companies.projects.show', [
                $this->company,
                $this->project,
            ]))
            ->assertSessionHas('info');

        $this->assertDatabaseCount('load_analyses', 0);
    }

    public function test_equipment_store_does_not_silently_create_load_analysis_defaults(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->post(route('companies.load-analyses.appliances.store', [
                $this->company,
                $this->project,
            ]), $this->validAppliancePayload())
            ->assertRedirect(route('companies.projects.show', [
                $this->company,
                $this->project,
            ]))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('load_analyses', 0);
        $this->assertDatabaseCount('load_appliances', 0);
    }

    public function test_critical_load_classification_must_be_explicit_on_create(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->createLoadAnalysis();

        $payload = $this->validAppliancePayload();
        $payload['is_critical'] = '';

        $this
            ->actingAs($engineer)
            ->from(route('companies.load-analyses.appliances.create', [
                $this->company,
                $this->project,
            ]))
            ->post(route('companies.load-analyses.appliances.store', [
                $this->company,
                $this->project,
            ]), $payload)
            ->assertSessionHasErrors('is_critical');

        $this->assertDatabaseCount('load_appliances', 0);
    }

    public function test_appliance_mutation_touches_load_analysis_for_existing_stale_checks(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $sourceTime = $analysis->updated_at;
        $generatedAt = $sourceTime->copy()->addSeconds(10);

        $this->travelTo($sourceTime->copy()->addMinute());

        try {
            $this
                ->actingAs($engineer)
                ->post(route('companies.load-analyses.appliances.store', [
                    $this->company,
                    $this->project,
                ]), $this->validAppliancePayload())
                ->assertRedirect(route('companies.load-analyses.show', [
                    $this->company,
                    $this->project,
                ]));
        } finally {
            $this->travelBack();
        }

        $analysis->refresh();
        $this->assertTrue($analysis->updated_at->isAfter($sourceTime));
        $this->assertDatabaseHas('load_appliances', [
            'load_analysis_id' => $analysis->getKey(),
            'name' => 'Air Conditioner',
        ]);

        $this->project->setRelation('loadAnalysis', $analysis);

        $recommendation = new SystemRecommendation([
            'generated_at' => $generatedAt,
        ]);
        $calculation = new TechnicalCalculation([
            'calculated_at' => $generatedAt,
        ]);

        $this->assertTrue($recommendation->isStale($this->project));
        $this->assertTrue($calculation->isStale($this->project));
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

        LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'source_mode' => 'combined',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $invalidPayload = $this->validAppliancePayload();
        unset($invalidPayload['rated_power_w']);

        $this
            ->actingAs($customerUser)
            ->post(route('companies.load-analyses.appliances.store', [
                $this->company,
                $otherProject,
            ]), $invalidPayload)
            ->assertNotFound();
    }

    public function test_load_analysis_show_get_is_read_only_and_never_creates_engineering_state(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]))
            ->assertOk()
            ->assertSee('No Load Analysis')
            ->assertSee('Load Analysis has not been started')
            ->assertSee('Start Load Analysis')
            ->assertDontSee('Engineering readiness')
            ->assertDontSee('Validation fit')
            ->assertDontSee('Coincident peak');

        $this->assertDatabaseCount('load_analyses', 0);
    }

    public function test_load_analysis_start_is_an_explicit_authorized_mutation(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->post(route('companies.load-analyses.start', [
                $this->company,
                $this->project,
            ]))
            ->assertRedirect(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]));

        $this->assertDatabaseHas('load_analyses', [
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'source_mode' => 'combined',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $engineer->getKey(),
        ]);

        $this
            ->actingAs($engineer)
            ->post(route('companies.load-analyses.start', [
                $this->company,
                $this->project,
            ]))
            ->assertRedirect(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]));

        $this->assertDatabaseCount('load_analyses', 1);
    }

    public function test_electricity_bill_store_requires_existing_scoped_load_analysis(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this
            ->actingAs($engineer)
            ->post(route('companies.load-analyses.bills.store', [
                $this->company,
                $this->project,
            ]), [
                'billing_month' => '2026-06',
                'energy_kwh' => 720,
                'bill_amount' => 1030000,
                'peak_demand_kw' => 4.2,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('load_analyses', 0);
        $this->assertDatabaseCount('electricity_bills', 0);
    }

    public function test_bill_mutation_touches_load_analysis_for_existing_downstream_stale_checks(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $sourceTime = $analysis->updated_at;

        $this->travelTo($sourceTime->copy()->addMinute());
        try {
            $this
                ->actingAs($engineer)
                ->post(route('companies.load-analyses.bills.store', [
                    $this->company,
                    $this->project,
                ]), [
                    'billing_month' => '2026-06',
                    'energy_kwh' => 720,
                    'bill_amount' => 1030000,
                    'peak_demand_kw' => 4.2,
                ])
                ->assertRedirect(route('companies.load-analyses.show', [
                    $this->company,
                    $this->project,
                ]));
        } finally {
            $this->travelBack();
        }

        $analysis->refresh();
        $this->assertTrue($analysis->updated_at->isAfter($sourceTime));
    }

    public function test_load_summary_keeps_bill_mode_separate_and_uses_only_paired_bill_amounts_for_effective_rate(): void
    {
        $analysis = $this->createLoadAnalysis();
        $analysis->update(['source_mode' => 'bill']);
        $this->createAppliance($analysis, [
            'operating_hours_per_day' => 10,
            'usage_factor' => 1,
        ]);

        $analysis->load(['appliances', 'electricityBills']);
        $withoutBills = $analysis->calculateSummary();
        $this->assertSame(0.0, $withoutBills['daily_energy_kwh']);
        $this->assertSame(0.0, $withoutBills['monthly_energy_kwh']);
        $this->assertSame(0.0, $withoutBills['peak_demand_kw']);
        $this->assertSame(2.0, $withoutBills['profile_peak_demand_kw']);

        ElectricityBill::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'load_analysis_id' => $analysis->getKey(),
            'billing_month' => '2026-05-01',
            'energy_kwh' => 600,
            'bill_amount' => 900000,
            'peak_demand_kw' => 4,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
        ElectricityBill::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'load_analysis_id' => $analysis->getKey(),
            'billing_month' => '2026-06-01',
            'energy_kwh' => 900,
            'bill_amount' => null,
            'peak_demand_kw' => 6,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $analysis->load(['appliances', 'electricityBills']);
        $summary = $analysis->calculateSummary();

        $this->assertSame(25.0, $summary['daily_energy_kwh']);
        $this->assertSame(750.0, $summary['monthly_energy_kwh']);
        $this->assertSame(5.0, $summary['peak_demand_kw']);
        $this->assertSame(1500.0, $summary['effective_rate_per_kwh']);
        $this->assertSame(2.0, $summary['profile_peak_demand_kw']);
    }

    public function test_load_analysis_show_uses_reconciliation_language_not_validation_or_system_start_claims(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $this->createAppliance($analysis, [
            'starting_multiplier' => 6,
        ]);

        ElectricityBill::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'load_analysis_id' => $analysis->getKey(),
            'billing_month' => '2026-06-01',
            'energy_kwh' => 300,
            'bill_amount' => 500000,
            'peak_demand_kw' => 7.5,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]))
            ->assertOk()
            ->assertSee('Modeled profile peak')
            ->assertSee('Bill comparison')
            ->assertSee('Model-to-bill variance')
            ->assertSee('Preliminary equipment-level fallback')
            ->assertSee('Recorded peak demand')
            ->assertDontSee('Engineering readiness')
            ->assertDontSee('Validation fit')
            ->assertDontSee('Coincident peak')
            ->assertDontSee('System starting peak')
            ->assertDontSee('Validated');
    }

    public function test_load_analysis_index_is_read_only_excludes_archived_projects_and_does_not_create_analysis(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $archivedProject = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $this->customer->getKey(),
            'project_number' => 'NXR-2026-00099',
            'name' => 'Archived Load Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'survey',
            'archived_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.index', $this->company))
            ->assertOk()
            ->assertSee($this->project->name)
            ->assertSee('No Load Analysis')
            ->assertSee('Start analysis')
            ->assertDontSee($archivedProject->name);

        $this->assertDatabaseCount('load_analyses', 0);
    }

    public function test_load_analysis_index_view_own_scope_hides_other_customer_projects(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');

        $this->customer->update([
            'portal_user_id' => $customerUser->getKey(),
        ]);

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00022',
            'name' => 'Restricted Customer',
            'type' => 'commercial',
            'portal_user_id' => $otherCustomerUser->getKey(),
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $otherProject = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $otherCustomer->getKey(),
            'project_number' => 'NXR-2026-00022',
            'name' => 'Restricted Customer Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->createLoadAnalysis();
        LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'source_mode' => 'equipment',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this
            ->actingAs($customerUser)
            ->get(route('companies.load-analyses.index', $this->company))
            ->assertOk()
            ->assertSee($this->project->name)
            ->assertDontSee($otherProject->name)
            ->assertDontSee($otherCustomer->name);
    }

    public function test_load_analysis_index_reuses_authoritative_summary_and_existing_downstream_stale_checks(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $this->createAppliance($analysis);
        $analysis->refresh();

        $olderSourceTime = $analysis->updated_at->copy()->subMinute();

        SystemRecommendation::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'generated_at' => $olderSourceTime,
            'generated_by' => $this->superAdmin->getKey(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        TechnicalCalculation::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'calculated_at' => $olderSourceTime,
            'calculated_by' => $this->superAdmin->getKey(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.index', $this->company))
            ->assertOk()
            ->assertSee('Preliminary')
            ->assertSee('Equipment schedule')
            ->assertSee('1 equipment group')
            ->assertSee('4.3 MWh')
            ->assertSee('1.5 kW')
            ->assertSee('Modeled 24-hour profile')
            ->assertSee('Downstream review required')
            ->assertDontSee('Engineering Ready')
            ->assertDontSee('Validated');
    }

    public function test_load_analysis_index_filters_input_required_server_side(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->createLoadAnalysis();

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00033',
            'name' => 'Equipment Model Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $otherProject = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $otherCustomer->getKey(),
            'project_number' => 'NXR-2026-00033',
            'name' => 'Project With Equipment Model',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $otherAnalysis = LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'source_mode' => 'equipment',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
        LoadAppliance::query()->create([
            ...$this->validAppliancePayload(),
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'load_analysis_id' => $otherAnalysis->getKey(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.index', [
                'company' => $this->company,
                'status' => 'input_required',
            ]))
            ->assertOk()
            ->assertSee($this->project->name)
            ->assertSee('Input Required')
            ->assertDontSee($otherProject->name);
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


    public function test_equipment_edit_loads_stored_values_and_update_preserves_hidden_sort_order(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $appliance = $this->createAppliance($analysis, [
            'name' => 'Production Fan',
            'quantity' => 3,
            'rated_power_w' => 1850,
            'operating_hours_per_day' => 9.5,
            'usage_factor' => 0.72,
            'start_hour' => 23,
            'starting_multiplier' => 5.5,
            'is_critical' => true,
            'sort_order' => 7,
        ]);

        $this
            ->actingAs($engineer)
            ->get(route('companies.load-analyses.appliances.edit', [
                $this->company,
                $this->project,
                $appliance,
            ]))
            ->assertOk()
            ->assertSee('Edit Equipment Load')
            ->assertSee('Production Fan')
            ->assertSee('Stored: 0.72')
            ->assertSee('Stored: 23:00');

        $payload = $this->validAppliancePayload();
        $payload['name'] = 'Production Fan Revised';
        $payload['quantity'] = 4;
        $payload['usage_factor'] = 0.68;
        unset($payload['sort_order']);

        $this
            ->actingAs($engineer)
            ->put(route('companies.load-analyses.appliances.update', [
                $this->company,
                $this->project,
                $appliance,
            ]), $payload)
            ->assertRedirect(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]));

        $appliance->refresh();
        $this->assertSame('Production Fan Revised', $appliance->name);
        $this->assertSame(4, $appliance->quantity);
        $this->assertSame(7, $appliance->sort_order);
    }

    public function test_equipment_update_rejects_appliance_from_another_project_before_validation(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $this->createLoadAnalysis();

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00901',
            'name' => 'Other Load Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $otherProject = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $otherCustomer->getKey(),
            'project_number' => 'NXR-2026-00901',
            'name' => 'Other Load Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $otherAnalysis = LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'source_mode' => 'equipment',
            'supply_phase' => 'three_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $foreignAppliance = LoadAppliance::query()->create([
            ...$this->validAppliancePayload(),
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'load_analysis_id' => $otherAnalysis->getKey(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $invalidPayload = $this->validAppliancePayload();
        unset($invalidPayload['rated_power_w']);

        $this
            ->actingAs($engineer)
            ->put(route('companies.load-analyses.appliances.update', [
                $this->company,
                $this->project,
                $foreignAppliance,
            ]), $invalidPayload)
            ->assertNotFound();
    }

    public function test_equipment_update_rejects_corrupted_company_and_load_analysis_scope(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();

        $otherCompany = Company::query()->create([
            'name' => 'Other Energy Company',
            'slug' => 'other-energy-company',
            'code' => 'OEC',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $wrongCompanyAppliance = $this->createAppliance($analysis, [
            'company_id' => $otherCompany->getKey(),
            'name' => 'Corrupted Company Link',
        ]);

        $this
            ->actingAs($engineer)
            ->put(route('companies.load-analyses.appliances.update', [
                $this->company,
                $this->project,
                $wrongCompanyAppliance,
            ]), $this->validAppliancePayload())
            ->assertNotFound();

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00902',
            'name' => 'Alternate Analysis Customer',
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
        $otherProject = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $otherCustomer->getKey(),
            'project_number' => 'NXR-2026-00902',
            'name' => 'Alternate Analysis Project',
            'building_type' => 'commercial',
            'proposed_system' => 'hybrid',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);
        $otherAnalysis = LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'source_mode' => 'equipment',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
        $wrongAnalysisAppliance = $this->createAppliance($analysis, [
            'load_analysis_id' => $otherAnalysis->getKey(),
            'name' => 'Corrupted Analysis Link',
        ]);

        $this
            ->actingAs($engineer)
            ->put(route('companies.load-analyses.appliances.update', [
                $this->company,
                $this->project,
                $wrongAnalysisAppliance,
            ]), $this->validAppliancePayload())
            ->assertNotFound();

        $this
            ->actingAs($engineer)
            ->delete(route('companies.load-analyses.appliances.destroy', [
                $this->company,
                $this->project,
                $wrongAnalysisAppliance,
            ]))
            ->assertNotFound();
    }

    public function test_equipment_update_ignores_identity_tampering_and_preserves_creator_and_sort_order(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $appliance = $this->createAppliance($analysis, [
            'sort_order' => 11,
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $otherCompany = Company::query()->create([
            'name' => 'Tamper Target Company',
            'slug' => 'tamper-target-company',
            'code' => 'TTC',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $payload = $this->validAppliancePayload();
        $payload['sort_order'] = 0;
        $payload['company_id'] = $otherCompany->getKey();
        $payload['project_id'] = 999999;
        $payload['load_analysis_id'] = 999999;
        $payload['created_by'] = $engineer->getKey();

        $this
            ->actingAs($engineer)
            ->put(route('companies.load-analyses.appliances.update', [
                $this->company,
                $this->project,
                $appliance,
            ]), $payload)
            ->assertRedirect(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]));

        $appliance->refresh();
        $this->assertSame($this->company->getKey(), $appliance->company_id);
        $this->assertSame($this->project->getKey(), $appliance->project_id);
        $this->assertSame($analysis->getKey(), $appliance->load_analysis_id);
        $this->assertSame($this->superAdmin->getKey(), $appliance->created_by);
        $this->assertSame(11, $appliance->sort_order);
        $this->assertSame($engineer->getKey(), $appliance->updated_by);
    }


    public function test_manage_own_user_cannot_update_appliance_through_another_customers_project(): void
    {
        $customerUser = $this->createCompanyUser('customer');
        $otherCustomerUser = $this->createCompanyUser('customer');

        $this->customer->update([
            'portal_user_id' => $customerUser->getKey(),
        ]);

        $analysis = $this->createLoadAnalysis();
        $appliance = $this->createAppliance($analysis);

        $otherCustomer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00903',
            'name' => 'Other Portal Customer',
            'type' => 'commercial',
            'portal_user_id' => $otherCustomerUser->getKey(),
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $otherProject = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $otherCustomer->getKey(),
            'project_number' => 'NXR-2026-00903',
            'name' => 'Other Portal Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'status' => 'survey',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $otherAnalysis = LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'source_mode' => 'equipment',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $otherAppliance = LoadAppliance::query()->create([
            ...$this->validAppliancePayload(),
            'company_id' => $this->company->getKey(),
            'project_id' => $otherProject->getKey(),
            'load_analysis_id' => $otherAnalysis->getKey(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this
            ->actingAs($customerUser)
            ->put(route('companies.load-analyses.appliances.update', [
                $this->company,
                $otherProject,
                $otherAppliance,
            ]), $this->validAppliancePayload())
            ->assertNotFound();

        $this->assertDatabaseHas('load_appliances', [
            'id' => $otherAppliance->getKey(),
            'project_id' => $otherProject->getKey(),
        ]);
        $this->assertDatabaseHas('load_appliances', [
            'id' => $appliance->getKey(),
            'project_id' => $this->project->getKey(),
        ]);
    }

    public function test_archived_project_blocks_equipment_update_server_side(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $appliance = $this->createAppliance($analysis, ['quantity' => 2]);

        $this->project->update([
            'archived_at' => now(),
        ]);

        $payload = $this->validAppliancePayload();
        $payload['quantity'] = 9;

        $this
            ->actingAs($engineer)
            ->from(route('companies.load-analyses.show', [
                $this->company,
                $this->project,
            ]))
            ->put(route('companies.load-analyses.appliances.update', [
                $this->company,
                $this->project,
                $appliance,
            ]), $payload)
            ->assertSessionHas('error');

        $this->assertSame(2, $appliance->fresh()->quantity);
    }

    public function test_equipment_update_touches_load_analysis_for_existing_stale_checks(): void
    {
        $engineer = $this->createCompanyUser('engineer');
        $analysis = $this->createLoadAnalysis();
        $appliance = $this->createAppliance($analysis);
        $sourceTime = $analysis->updated_at;

        $payload = $this->validAppliancePayload();
        $payload['quantity'] = 4;
        unset($payload['sort_order']);

        $this->travelTo($sourceTime->copy()->addMinute());
        try {
            $this
                ->actingAs($engineer)
                ->put(route('companies.load-analyses.appliances.update', [
                    $this->company,
                    $this->project,
                    $appliance,
                ]), $payload)
                ->assertRedirect(route('companies.load-analyses.show', [
                    $this->company,
                    $this->project,
                ]));
        } finally {
            $this->travelBack();
        }

        $analysis->refresh();
        $this->assertTrue($analysis->updated_at->isAfter($sourceTime));
    }


    private function createLoadAnalysis(): LoadAnalysis
    {
        return LoadAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'source_mode' => 'combined',
            'supply_phase' => 'single_phase',
            'billing_days' => 30,
            'daytime_start_hour' => 6,
            'daytime_end_hour' => 18,
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validAppliancePayload(): array
    {
        return [
            'name' => 'Air Conditioner',
            'category' => 'Cooling',
            'quantity' => 2,
            'rated_power_w' => 1000,
            'operating_hours_per_day' => 8,
            'usage_factor' => 0.75,
            'start_hour' => 8,
            'starting_multiplier' => 3,
            'is_critical' => 1,
            'sort_order' => 0,
            'notes' => 'Survey assumption.',
        ];
    }


    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAppliance(LoadAnalysis $analysis, array $overrides = []): LoadAppliance
    {
        return LoadAppliance::query()->create([
            ...$this->validAppliancePayload(),
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'load_analysis_id' => $analysis->getKey(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            ...$overrides,
        ]);
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
