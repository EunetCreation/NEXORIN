<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyCredential;
use App\Models\CompanyDocument;
use App\Models\CompanyFinancialSnapshot;
use App\Models\CompanyProfile;
use App\Models\CompanyRegion;
use App\Models\CompanyRelationship;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectStatusHistory;
use App\Models\Quotation;
use App\Models\QuotationEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProfile\CompanyProfileWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
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

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->systemRoles()->attach($role, ['assigned_by' => $this->superAdmin->id]);
    }

    public function test_super_admin_can_view_persisted_company_360_profile_without_synthetic_score(): void
    {
        $company = $this->company('Profile Evidence Energy', 'PEE');
        CompanyProfile::query()->create([
            'company_id' => $company->id,
            'legal_name' => 'PT Profile Evidence Energy',
            'company_type' => 'EPC Contractor',
            'year_established' => 2018,
            'hq_country' => 'Indonesia',
            'hq_city' => 'Bandung',
            'categories_served' => ['Commercial & Industrial'],
            'focus_markets' => ['Rooftop Solar'],
            'team_size' => 48,
            'last_reviewed_at' => now(),
        ]);
        CompanyRelationship::query()->create([
            'company_id' => $company->id,
            'tier' => 'strategic',
            'qualification_state' => 'qualified',
            'source_type' => 'manual_review',
            'source_reference' => 'REL-2026-014',
            'reviewed_by' => $this->superAdmin->id,
            'reviewed_at' => now(),
        ]);
        CompanyContact::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Rani Utama',
            'job_title' => 'Commercial Director',
            'email' => 'rani@example.test',
            'is_primary' => true,
        ]);
        CompanyRegion::query()->create([
            'company_id' => $company->id,
            'country_name' => 'Indonesia',
            'country_code' => 'IDN',
            'region_name' => 'West Java',
            'latitude' => -6.914744,
            'longitude' => 107.60981,
            'is_headquarters' => true,
        ]);
        CompanyCredential::query()->create([
            'company_id' => $company->id,
            'kind' => 'certification',
            'title' => 'Quality Management Certificate',
            'issuing_authority' => 'Independent Certification Body',
            'issued_at' => now()->subYear(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);
        CompanyFinancialSnapshot::query()->create([
            'company_id' => $company->id,
            'period_end' => '2025-12-31',
            'currency' => 'IDR',
            'annual_revenue' => 12500000000,
            'net_profit' => 1100000000,
            'ebitda_margin' => 14.2,
            'recorded_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('companies.show', $company));

        $response->assertOk()
            ->assertSee('Company Profile')
            ->assertSee('PT Profile Evidence Energy')
            ->assertSee('EPC Contractor')
            ->assertSee('Commercial &amp; Industrial', false)
            ->assertSee('Strategic')
            ->assertSee('West Java')
            ->assertSee('Rani Utama')
            ->assertSee('Quality Management Certificate')
            ->assertSee('Not Scored')
            ->assertDontSee('83/100')
            ->assertDontSee('Compliance Score');
    }

    public function test_regular_user_cannot_open_company_profile(): void
    {
        $company = $this->company('Protected Company', 'PCO');
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get(route('companies.show', $company))
            ->assertForbidden();
    }

    public function test_active_projects_and_portfolio_reuse_directory_semantics(): void
    {
        $company = $this->company('Portfolio Company', 'PFC');
        $customer = $this->customer($company, 'Portfolio Customer');
        $draft = $this->project($company, $customer, 'P-001', 'Draft Rooftop', 'draft');
        $engineering = $this->project($company, $customer, 'P-002', 'Engineering Rooftop', 'engineering');
        $completed = $this->project($company, $customer, 'P-003', 'Completed Rooftop', 'completed');

        Quotation::query()->create([
            'company_id' => $company->id,
            'project_id' => $draft->id,
            'quote_number' => 'Q-001',
            'status' => 'accepted',
            'currency' => 'IDR',
            'commercial_summary' => ['grand_total_idr' => 2500000000],
            'accepted_at' => now()->subDay(),
        ]);
        Quotation::query()->create([
            'company_id' => $company->id,
            'project_id' => $engineering->id,
            'quote_number' => 'Q-002',
            'status' => 'accepted',
            'currency' => 'USD',
            'commercial_summary' => ['grand_total_idr' => 9000000000],
            'accepted_at' => now(),
        ]);

        $workspace = app(CompanyProfileWorkspaceService::class)->build($company)['workspace'];

        $this->assertSame(2, $workspace['activeProjectCount']);
        $this->assertSame(2500000000.0, $workspace['acceptedPortfolio']['company_value']);
        $this->assertSame(1, $workspace['acceptedPortfolio']['accepted_quotation_count']);
        $this->assertSame(1, $workspace['acceptedPortfolio']['excluded_or_unavailable_count']);
        $this->assertSame(['draft', 'engineering'], collect($workspace['activeProjectLifecycle'])->pluck('key')->all());
        $this->assertFalse(collect($workspace['activeProjectLifecycle'])->pluck('key')->contains($completed->status));
    }

    public function test_company_profile_uses_truthful_empty_states_when_evidence_is_missing(): void
    {
        $company = $this->company('Evidence Pending Company', 'EPC');

        $this->actingAs($this->superAdmin)
            ->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('Unassigned')
            ->assertSee('Not Scored')
            ->assertSee('Not assessed')
            ->assertSee('No financial snapshot recorded')
            ->assertSee('No service region recorded')
            ->assertSee('No primary contact recorded')
            ->assertSee('No credentials recorded')
            ->assertSee('No governed documents recorded')
            ->assertSee('No auditable recent activity recorded.');
    }

    public function test_company_profile_does_not_leak_other_company_records(): void
    {
        $company = $this->company('Scoped Company', 'SCP');
        $other = $this->company('Other Company', 'OTH');

        CompanyContact::query()->create([
            'company_id' => $other->id,
            'full_name' => 'Other Company Secret Contact',
            'job_title' => 'Secret Role',
            'email' => 'secret@example.test',
            'is_primary' => true,
        ]);
        CompanyDocument::query()->create([
            'company_id' => $other->id,
            'category' => 'financial',
            'title' => 'Other Company Confidential Financial Record',
            'status' => 'active',
        ]);
        CompanyCredential::query()->create([
            'company_id' => $other->id,
            'kind' => 'certification',
            'title' => 'Other Company Credential',
            'status' => 'active',
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('companies.show', $company))
            ->assertOk()
            ->assertDontSee('Other Company Secret Contact')
            ->assertDontSee('Other Company Confidential Financial Record')
            ->assertDontSee('Other Company Credential');
    }

    public function test_recent_activity_only_uses_persisted_audit_events(): void
    {
        $company = $this->company('Auditable Company', 'AUD');
        $customer = $this->customer($company, 'Audit Customer');
        $project = $this->project($company, $customer, 'AUD-001', 'Audit Project', 'engineering');

        ProjectStatusHistory::query()->create([
            'project_id' => $project->id,
            'from_status' => 'survey',
            'to_status' => 'engineering',
            'note' => 'Engineering scope opened after survey review.',
            'changed_by' => $this->superAdmin->id,
            'changed_at' => now()->subHour(),
        ]);
        $quotation = Quotation::query()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'quote_number' => 'AUD-Q-01',
            'status' => 'approved',
            'currency' => 'IDR',
        ]);
        QuotationEvent::query()->create([
            'quotation_id' => $quotation->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'event_type' => 'approved',
            'status_from' => 'internal_review',
            'status_to' => 'approved',
            'actor_id' => $this->superAdmin->id,
            'event_at' => now(),
            'channel' => 'internal',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('companies.show', $company));

        $response->assertOk()
            ->assertSee('AUD-001 moved to Engineering')
            ->assertSee('Engineering scope opened after survey review.')
            ->assertSee('Approved · AUD-001')
            ->assertDontSee('Company updated 2 hours ago');
    }

    public function test_inactive_company_profile_remains_manageable_but_hides_operational_links(): void
    {
        $company = $this->company('Inactive Company', 'INA', 'inactive');

        $this->actingAs($this->superAdmin)
            ->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('Inactive')
            ->assertSee('Activate Company')
            ->assertSee('Archive Company')
            ->assertDontSee('View Projects')
            ->assertDontSee('View Customers');
    }

    private function company(string $name, string $code, string $status = 'active'): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => strtolower($code).'-'.str()->random(6),
            'code' => $code,
            'status' => $status,
            'created_by' => $this->superAdmin->id,
        ]);
    }

    private function customer(Company $company, string $name): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_number' => 'C-'.str()->random(6),
            'name' => $name,
            'type' => 'commercial',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    private function project(Company $company, Customer $customer, string $number, string $name, string $status): Project
    {
        return Project::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'project_number' => $number,
            'name' => $name,
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Bandung, West Java',
            'status' => $status,
            'created_by' => $this->superAdmin->id,
        ]);
    }
}
