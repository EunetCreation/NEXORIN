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

class Page011CustomerShowTest extends TestCase
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
            'name' => 'NEXORIN Page 011 Company',
            'slug' => 'nexorin-page-011',
            'code' => 'NX11',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);
        $this->companyAdmin = $this->createCompanyUser($this->company, 'company_admin');
    }

    public function test_page_011_renders_authoritative_customer_360_without_fabricated_v6_intelligence(): void
    {
        [$customer, $profile, $contact, $opportunity, $document, $project] = $this->seedCustomerContext();

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.show', [$this->company, $customer]))
            ->assertOk()
            ->assertSee('Customer 360')
            ->assertSee($customer->name)
            ->assertSee($profile->preferred_name)
            ->assertSee($contact->full_name)
            ->assertSee($opportunity->name)
            ->assertSee('IDR 3.3B')
            ->assertSee($document->title)
            ->assertSee($project->name)
            ->assertSee('Relationship Overview')
            ->assertSee('Information Coverage')
            ->assertDontSee('synthetic relationship score')
            ->assertDontSee('current schema')
            ->assertDontSee('activity-event domain')
            ->assertDontSee('Credit Rating')
            ->assertDontSee('Lifetime Value')
            ->assertDontSee('Contract Health')
            ->assertDontSee('Recent Activities')
            ->assertDontSee('AI Recommendation')
            ->assertDontSee('Regional Footprint')
            ->assertDontSee($document->storage_path);
    }

    public function test_page_011_keeps_open_opportunity_currency_totals_separate(): void
    {
        [$customer] = $this->seedCustomerContext();

        CustomerOpportunity::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'name' => 'USD Long-Term Energy Advisory Opportunity',
            'opportunity_type' => 'Engineering Advisory',
            'estimated_value' => 125000,
            'currency_code' => 'USD',
            'probability_pct' => null,
            'expected_close_date' => now()->addMonths(5)->toDateString(),
            'status' => 'open',
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.show', [$this->company, $customer]))
            ->assertOk()
            ->assertSee('Mixed Currencies')
            ->assertSee('IDR 3.3B')
            ->assertSee('USD 125.0K')
            ->assertSee('Probability Not Assessed');
    }

    public function test_page_011_uses_real_project_blockers_and_exact_project_coordinate_context(): void
    {
        [$customer, , , , , $project] = $this->seedCustomerContext();

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.show', [$this->company, $customer]))
            ->assertOk()
            ->assertSee('Archive Customer · Blocked')
            ->assertSee('Archive blocked by open project')
            ->assertSee('Exact Coordinate')
            ->assertSee($project->project_number)
            ->assertSee('-6.2000000')
            ->assertSee('106.8166667');
    }

    public function test_page_011_viewer_receives_profile_data_but_not_mutating_actions(): void
    {
        [$customer] = $this->seedCustomerContext();
        $viewer = $this->createCompanyUser($this->company, 'viewer');

        $this->actingAs($viewer)
            ->get(route('companies.customers.show', [$this->company, $customer]))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertDontSee('Edit Customer')
            ->assertDontSee('Archive Customer')
            ->assertDontSee('New Project');
    }

    public function test_page_011_scope_binding_blocks_cross_tenant_customer_access(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'Other Page 011 Tenant',
            'slug' => 'other-page-011-tenant',
            'code' => 'O11',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);
        $otherCustomer = $this->createCustomer($otherCompany, 'O11-CUS-00001', 'Cross Tenant Customer Must Not Render', 'commercial');

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.show', [$this->company, $otherCustomer]))
            ->assertNotFound();
    }

    public function test_page_011_empty_crm_state_remains_truthful_without_sample_values(): void
    {
        $customer = $this->createCustomer(
            $this->company,
            'NX11-CUS-EMPTY',
            'Customer Without CRM Handoff Evidence',
            'residential',
            ['email' => null, 'phone' => null]
        );

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.show', [$this->company, $customer]))
            ->assertOk()
            ->assertSee('Customer profile has not been recorded.')
            ->assertSee('Not Recorded')
            ->assertSee('No open opportunities recorded')
            ->assertSee('No customer documents recorded')
            ->assertSee('No portal identity linked')
            ->assertDontSee('87%')
            ->assertDontSee('$2.4M');
    }

    private function seedCustomerContext(): array
    {
        $customer = $this->createCustomer(
            $this->company,
            'NX11-CUS-00001',
            'PT Surya Energi Nusantara International Manufacturing and Industrial Solutions with an Intentionally Long Enterprise Account Name',
            'commercial',
            [
                'email' => 'energy.transition.procurement.and.infrastructure@example-enterprise.test',
                'phone' => '+62 812 3456 7890',
                'address' => 'Industrial Estate Building 18, Sustainable Manufacturing and Energy Transition Complex',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'postal_code' => '12950',
                'notes' => 'Customer requires controlled technical evidence before commercial commitment. This intentionally long note validates right-rail wrapping without inventing relationship activity.',
            ]
        );

        $profile = CustomerProfile::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'preferred_name' => 'Surya Energi Nusantara',
            'website' => 'https://example-enterprise.test/customer-profile',
            'currency_code' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'year_established' => 1998,
            'employee_count' => 3200,
            'annual_revenue_amount' => 875000000000,
            'revenue_currency_code' => 'IDR',
            'description' => 'Industrial manufacturing group evaluating controlled rooftop solar deployment across multiple facilities.',
            'relationship_stage' => 'prospect',
            'primary_industry' => 'Industrial Manufacturing',
            'sub_industry' => 'Energy Intensive Manufacturing and Industrial Processing',
            'business_model' => 'Multi-site industrial operator',
            'industry_tags' => ['manufacturing', 'solar', 'multi-site'],
            'hq_country' => 'Indonesia',
            'operating_countries' => ['Indonesia'],
            'parent_company_assessment' => 'no_parent',
            'opportunity_assessment' => 'has_opportunity',
            'account_owner_user_id' => $this->companyAdmin->id,
            'sales_team' => 'Enterprise Energy',
            'region' => 'Jakarta Metropolitan and Western Indonesia Industrial Corridor',
            'business_unit' => 'Industrial Energy Transition',
            'preferred_contract_type' => 'Engineering and EPC',
            'engagement_model' => 'Technical qualification before commercial release',
            'payment_terms' => 'Recorded in customer commercial review',
            'contract_duration' => 'Not committed',
            'key_considerations' => 'Preserve technical evidence, exact project provenance, and company-scoped access through every handoff.',
            'review_acknowledged_at' => now(),
            'review_acknowledged_by' => $this->companyAdmin->id,
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'full_name' => 'Ayu Rahmawati Kusumawardhani',
            'job_title' => 'Head of Engineering and Sustainable Energy Procurement with Enterprise Program Governance Responsibility',
            'email' => 'ayu.rahmawati.long.enterprise.contact@example-enterprise.test',
            'phone' => '+62 811 2222 3333',
            'is_primary' => true,
            'sort_order' => 0,
            'created_by' => $this->companyAdmin->id,
            'updated_by' => $this->companyAdmin->id,
        ]);

        $opportunity = CustomerOpportunity::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'name' => 'Multi-site Rooftop Solar Engineering Assessment and Controlled EPC Qualification Program',
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
            'title' => 'Enterprise Company Profile and Governance Evidence Package with Long Controlled Document Title',
            'original_name' => 'customer-company-profile-governance-evidence-package-2026-controlled-revision.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'customer-documents/test/page011-sensitive-internal-path.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 98304,
            'sha256' => hash('sha256', 'page011-existing-document'),
            'status' => 'recorded',
            'created_by' => $this->companyAdmin->id,
        ]);

        $project = Project::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'project_number' => 'NX11-PRJ-00001',
            'name' => 'Jakarta Manufacturing Rooftop Solar Project with Extended Facility Qualification Scope',
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
