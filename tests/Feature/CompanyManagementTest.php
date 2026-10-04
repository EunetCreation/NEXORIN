<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDirectorySavedView;
use App\Models\CompanyProfile;
use App\Models\CompanyRegion;
use App\Models\CompanyRelationship;
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


    public function test_company_directory_uses_truthful_empty_intelligence_states(): void
    {
        Company::query()->create([
            'name' => 'Evidence Pending Energy',
            'slug' => 'evidence-pending-energy',
            'code' => 'EPE',
            'email' => 'private@example.test',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Evidence Pending Energy')
            ->assertSee('Not assessed')
            ->assertSee('Not available')
            ->assertSee('No accepted quotation');
    }

    public function test_company_directory_filters_use_persisted_profile_region_and_relationship_data(): void
    {
        $strategic = Company::query()->create([
            'name' => 'Strategic EPC',
            'slug' => 'strategic-epc',
            'code' => 'SEP',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);
        CompanyProfile::query()->create([
            'company_id' => $strategic->id,
            'company_type' => 'EPC Contractor',
            'categories_served' => ['Commercial & Industrial'],
        ]);
        CompanyRegion::query()->create([
            'company_id' => $strategic->id,
            'country_name' => 'Indonesia',
            'country_code' => 'IDN',
            'region_name' => 'West Java',
            'latitude' => -6.9,
            'longitude' => 107.6,
            'is_headquarters' => true,
        ]);
        CompanyRelationship::query()->create([
            'company_id' => $strategic->id,
            'tier' => 'strategic',
            'qualification_state' => 'qualified',
            'reviewed_by' => $this->superAdmin->id,
            'reviewed_at' => now(),
        ]);

        Company::query()->create([
            'name' => 'Unclassified Supplier',
            'slug' => 'unclassified-supplier',
            'code' => 'UNS',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('companies.index', [
                'type' => 'EPC Contractor',
                'region' => 'West Java',
                'tier' => 'strategic',
                'specialization' => 'Commercial & Industrial',
            ]))
            ->assertOk()
            ->assertSee('Strategic EPC')
            ->assertDontSee('Unclassified Supplier');
    }

    public function test_super_admin_can_export_only_the_filtered_directory_columns(): void
    {
        Company::query()->create([
            'name' => 'Export Target',
            'slug' => 'export-target',
            'code' => 'EXT',
            'email' => 'sensitive-contact@example.test',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);
        Company::query()->create([
            'name' => 'Excluded Company',
            'slug' => 'excluded-company',
            'code' => 'EXC',
            'status' => 'inactive',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('companies.export', ['status' => 'active']));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Export Target', $content);
        $this->assertStringNotContainsString('Excluded Company', $content);
        $this->assertStringNotContainsString('sensitive-contact@example.test', $content);
    }

    public function test_saved_company_directory_views_are_owned_by_the_current_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('companies.directory-views.store'), [
                'name' => 'Strategic review',
                'filters' => [
                    'tier' => 'strategic',
                    'status' => 'active',
                    'sort' => 'name',
                    'direction' => 'asc',
                    'per_page' => 25,
                ],
                'is_default' => true,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('company_directory_saved_views', [
            'user_id' => $this->superAdmin->id,
            'name' => 'Strategic review',
            'is_default' => true,
        ]);

        $saved = CompanyDirectorySavedView::query()->firstOrFail();
        $this->assertSame('strategic', $saved->filters['tier']);
        $this->assertSame('active', $saved->filters['status']);
    }

    public function test_super_admin_can_create_update_and_archive_company(): void
    {
        $createResponse = $this
            ->actingAs($this->superAdmin)
            ->post(route('companies.store'), [
                'name' => 'Solar Lab Indonesia',
                'code' => 'SLI',
                'registration_number' => 'REG-2026-001',
                'email' => 'hello@solarlab.test',
                'phone' => '+62 761 555 0101',
                'address' => 'Pekanbaru, Riau',
                'status' => 'active',
            ]);

        $createResponse
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $company = Company::query()
            ->where('registration_number', 'REG-2026-001')
            ->firstOrFail();

        $createResponse->assertRedirect(route('companies.show', $company));

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
                'code' => 'SLN',
                'registration_number' => 'REG-2026-001',
                'email' => 'hello@solarlab.test',
                'phone' => '+62 761 555 0101',
                'address' => 'Pekanbaru, Riau',
                'status' => 'inactive',
            ]);

        $updateResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.edit', $company))
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