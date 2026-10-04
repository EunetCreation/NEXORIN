<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerDocument;
use App\Models\CustomerOpportunity;
use App\Models\CustomerProfile;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Page010CustomerIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Company $company;
    private User $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $this->superAdmin->systemRoles()->attach($superAdminRole, ['assigned_by' => $this->superAdmin->getKey()]);

        $this->company = Company::query()->create([
            'name' => 'NEXORIN Page 010 Company',
            'slug' => 'nexorin-page-010',
            'code' => 'NX10',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);
        $this->companyAdmin = $this->createCompanyUser($this->company, 'company_admin');
    }

    public function test_page_010_renders_authoritative_customer_intelligence_without_fake_v6_metrics(): void
    {
        [$customer, $profile, $contact, $opportunity, $document] = $this->seedCustomerContext();

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.index', $this->company))
            ->assertOk()
            ->assertSee('Customers Index')
            ->assertSee($customer->name)
            ->assertSee($profile->preferred_name)
            ->assertSee($contact->full_name)
            ->assertSee($opportunity->name)
            ->assertSee('IDR 3.3B')
            ->assertSee('Customer Record Coverage')
            ->assertSee('Customer-Linked Project Geography')
            ->assertDontSee('Won YTD')
            ->assertDontSee('Conversion Rate')
            ->assertDontSee('Relationship Score')
            ->assertDontSee('Activity Heatmap')
            ->assertDontSee('Expiring Contracts')
            ->assertDontSee('Follow-up Tasks')
            ->assertDontSee($document->storage_path);
    }

    public function test_page_010_search_and_crm_filters_are_server_side_and_company_scoped(): void
    {
        [$target, $profile] = $this->seedCustomerContext();
        $other = $this->createCustomer($this->company, 'NX10-CUS-00002', 'Residential Secondary Account', 'residential');
        CustomerProfile::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $other->id,
            'relationship_stage' => 'lead',
            'region' => 'West Java',
            'parent_company_assessment' => 'not_assessed',
            'opportunity_assessment' => 'not_assessed',
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.index', [
                'company' => $this->company,
                'search' => 'Industrial Manufacturing',
                'stage' => $profile->relationship_stage,
                'region' => 'Jakarta Metropolitan',
                'projects' => 'open',
                'evidence' => 'recorded',
            ]))
            ->assertOk()
            ->assertSee($target->name)
            ->assertDontSee($other->name);
    }

    public function test_page_010_keeps_mixed_currency_opportunity_values_separate(): void
    {
        [$customer] = $this->seedCustomerContext();
        CustomerOpportunity::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'name' => 'USD Engineering Advisory Opportunity',
            'estimated_value' => 125000,
            'currency_code' => 'USD',
            'probability_pct' => 25,
            'expected_close_date' => now()->addMonths(2)->toDateString(),
            'status' => 'open',
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.index', $this->company))
            ->assertOk()
            ->assertSee('IDR 3.3B')
            ->assertSee('USD 125.0K')
            ->assertDontSee('Pipeline Value');
    }

    public function test_page_010_does_not_leak_other_company_customer_or_aggregate_context(): void
    {
        $this->seedCustomerContext();
        $otherCompany = Company::query()->create([
            'name' => 'Other Page 010 Tenant',
            'slug' => 'other-page-010-tenant',
            'code' => 'O10',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);
        $outsider = $this->createCustomer($otherCompany, 'O10-CUS-00001', 'Cross Tenant Customer Must Not Appear', 'commercial');
        CustomerOpportunity::query()->create([
            'company_id' => $otherCompany->id,
            'customer_id' => $outsider->id,
            'name' => 'Cross Tenant Opportunity Must Not Appear',
            'estimated_value' => 999999999999,
            'currency_code' => 'USD',
            'status' => 'open',
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.index', $this->company))
            ->assertOk()
            ->assertDontSee($outsider->name)
            ->assertDontSee('Cross Tenant Opportunity Must Not Appear')
            ->assertDontSee('999.0B');
    }

    public function test_page_010_shows_real_open_project_archive_blocker_and_exact_coordinate_context(): void
    {
        [$customer] = $this->seedCustomerContext();
        $project = Project::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.index', $this->company))
            ->assertOk()
            ->assertSee('Archive blocked by open project')
            ->assertSee('Archive Customer · Blocked')
            ->assertSee('Exact Project Coordinate')
            ->assertSee($project->name);
    }

    public function test_page_010_viewer_cannot_receive_update_or_archive_actions(): void
    {
        [$customer] = $this->seedCustomerContext();
        $viewer = $this->createCompanyUser($this->company, 'viewer');

        $this->actingAs($viewer)
            ->get(route('companies.customers.index', $this->company))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertDontSee('Edit Customer')
            ->assertDontSee('Archive Customer');
    }

    public function test_page_010_preserves_created_at_sort_and_supports_new_directory_sorts(): void
    {
        [$customer] = $this->seedCustomerContext();

        foreach (['created_at', 'updated_at', 'name', 'customer_number', 'type', 'status', 'relationship_stage', 'region', 'active_projects'] as $sort) {
            $this->actingAs($this->companyAdmin)
                ->get(route('companies.customers.index', [
                    'company' => $this->company,
                    'sort' => $sort,
                    'direction' => 'asc',
                ]))
                ->assertOk()
                ->assertSee($customer->name);
        }
    }

    public function test_page_010_paginates_directory_instead_of_loading_unbounded_customers(): void
    {
        for ($index = 1; $index <= 14; $index++) {
            $this->createCustomer(
                $this->company,
                sprintf('NX10-P-%05d', $index),
                sprintf('Pagination Customer %02d', $index),
                $index % 2 === 0 ? 'commercial' : 'residential'
            );
        }

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.index', $this->company))
            ->assertOk()
            ->assertSee('of 14')
            ->assertSee('Next');
    }

    private function seedCustomerContext(): array
    {
        $customer = $this->createCustomer(
            $this->company,
            'NX10-CUS-00001',
            'PT Surya Energi Nusantara International Manufacturing and Industrial Solutions',
            'commercial',
            [
                'email' => 'energy.transition.procurement@example-enterprise.test',
                'phone' => '+62 812 3456 7890',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
            ]
        );

        $profile = CustomerProfile::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'preferred_name' => 'Surya Energi Nusantara',
            'relationship_stage' => 'prospect',
            'primary_industry' => 'Industrial Manufacturing',
            'sub_industry' => 'Energy Intensive Manufacturing and Industrial Processing',
            'region' => 'Jakarta Metropolitan',
            'account_owner_user_id' => $this->companyAdmin->id,
            'hq_country' => 'Indonesia',
            'parent_company_assessment' => 'not_assessed',
            'opportunity_assessment' => 'has_opportunity',
            'review_acknowledged_at' => now(),
            'review_acknowledged_by' => $this->companyAdmin->id,
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'full_name' => 'Ayu Rahmawati Kusumawardhani',
            'job_title' => 'Head of Engineering and Sustainable Energy Procurement',
            'email' => 'ayu.rahmawati@example-enterprise.test',
            'phone' => '+62 811 2222 3333',
            'is_primary' => true,
            'sort_order' => 0,
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $opportunity = CustomerOpportunity::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'name' => 'Multi-site Rooftop Solar Engineering Assessment',
            'opportunity_type' => 'Solar PV',
            'estimated_value' => 3250000000,
            'currency_code' => 'IDR',
            'probability_pct' => 40,
            'expected_close_date' => now()->addMonths(3)->toDateString(),
            'status' => 'open',
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $document = CustomerDocument::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'category' => 'company_profile',
            'title' => 'Company Profile',
            'original_name' => 'customer-company-profile-governance-evidence-2026.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'customer-documents/test/existing.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 98304,
            'sha256' => hash('sha256', 'page010-existing-document'),
            'status' => 'recorded',
            'created_by' => $this->companyAdmin->id,
        ]);

        $project = Project::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'project_number' => 'NX10-PRJ-00001',
            'name' => 'Jakarta Manufacturing Rooftop Solar Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Industrial Estate, Jakarta, Indonesia',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'status' => 'engineering',
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        return [$customer, $profile, $contact, $opportunity, $document, $project];
    }

    private function createCustomer(Company $company, string $number, string $name, string $type, array $overrides = []): Customer
    {
        return Customer::query()->create(array_merge([
            'company_id' => $company->id,
            'customer_number' => $number,
            'name' => $name,
            'type' => $type,
            'status' => 'active',
            'created_by' => $this->companyAdmin->id ?? $this->superAdmin->id,
            'updated_by' => $this->companyAdmin->id ?? $this->superAdmin->id,
        ], $overrides));
    }

    private function createCompanyUser(Company $company, string $roleCode): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $membership = CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by' => $this->superAdmin->id,
            'joined_at' => now(),
        ]);
        $role = Role::query()->where('code', $roleCode)->where('scope', 'company')->firstOrFail();
        $membership->roles()->attach($role, ['assigned_by' => $this->superAdmin->id]);

        return $user;
    }
}
