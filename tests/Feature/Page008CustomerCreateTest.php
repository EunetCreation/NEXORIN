<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerCreateDraft;
use App\Models\CustomerOpportunity;
use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Page008CustomerCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Company $company;

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
            'slug' => 'nexorin-energy-page-008',
            'code' => 'NX8',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);
    }

    public function test_authorized_user_can_open_page_008_customer_create_surface(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');

        $this->actingAs($companyAdmin)
            ->get(route('companies.customers.create', $this->company))
            ->assertOk()
            ->assertSee('Create Customer')
            ->assertSee('Customer Readiness')
            ->assertSee('Relationship Score')
            ->assertSee('Not Assessed')
            ->assertSee('Opportunity Summary')
            ->assertSee('Data Source Hints');
    }

    public function test_customer_crm_handoff_persists_profile_contact_and_optional_opportunity(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');
        $accountOwner = $this->createCompanyUser('sales');

        $response = $this->actingAs($companyAdmin)
            ->post(route('companies.customers.store', $this->company), [
                'name' => 'PT Example Solar Industries',
                'preferred_name' => 'Example Solar',
                'type' => 'commercial',
                'email' => 'engineering@example.test',
                'website' => 'https://example.test',
                'currency_code' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'relationship_stage' => 'prospect',
                'primary_industry' => 'Manufacturing',
                'business_model' => 'Industrial manufacturer',
                'industry_tags' => 'manufacturing, industrial',
                'hq_country' => 'Indonesia',
                'city' => 'Jakarta',
                'operating_countries' => 'Indonesia, Singapore',
                'account_owner_user_id' => $accountOwner->getKey(),
                'contacts' => [[
                    'full_name' => 'Ayu Engineering',
                    'job_title' => 'Engineering Manager',
                    'email' => 'ayu@example.test',
                    'phone' => '+62 812 0000 0000',
                    'is_primary' => '1',
                ]],
                'parent_company_assessment' => 'no_parent',
                'opportunity_assessment' => 'has_opportunity',
                'opportunity_name' => 'Rooftop PV Assessment',
                'opportunity_type' => 'Solar PV',
                'opportunity_estimated_value' => '1250000000',
                'opportunity_currency_code' => 'IDR',
                'opportunity_probability_pct' => '35',
                'preferred_contract_type' => 'EPC',
                'engagement_model' => 'Direct engagement',
                'submit_intent' => 'create',
            ]);

        $customer = Customer::query()->where('name', 'PT Example Solar Industries')->firstOrFail();
        $response->assertRedirect(route('companies.customers.show', [$this->company, $customer]));

        $profile = CustomerProfile::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('Example Solar', $profile->preferred_name);
        $this->assertSame('prospect', $profile->relationship_stage);
        $this->assertSame($accountOwner->getKey(), $profile->account_owner_user_id);
        $this->assertSame(['manufacturing', 'industrial'], $profile->industry_tags);
        $this->assertSame(['Indonesia', 'Singapore'], $profile->operating_countries);
        $this->assertSame('no_parent', $profile->parent_company_assessment);
        $this->assertSame('has_opportunity', $profile->opportunity_assessment);

        $contact = CustomerContact::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('Ayu Engineering', $contact->full_name);
        $this->assertTrue($contact->is_primary);

        $opportunity = CustomerOpportunity::query()->where('customer_id', $customer->getKey())->firstOrFail();
        $this->assertSame('Rooftop PV Assessment', $opportunity->name);
        $this->assertSame(35, $opportunity->probability_pct);
    }

    public function test_account_owner_must_be_an_active_member_of_the_same_company(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');
        $outsider = User::factory()->create(['status' => 'active']);

        $this->actingAs($companyAdmin)
            ->from(route('companies.customers.create', $this->company))
            ->post(route('companies.customers.store', $this->company), [
                'name' => 'Tenant Scope Test Customer',
                'type' => 'commercial',
                'account_owner_user_id' => $outsider->getKey(),
            ])
            ->assertRedirect(route('companies.customers.create', $this->company))
            ->assertSessionHasErrors('account_owner_user_id');

        $this->assertDatabaseMissing('customers', ['name' => 'Tenant Scope Test Customer']);
    }

    public function test_explicit_negative_assessments_and_review_acknowledgement_are_persisted_truthfully(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');

        $this->actingAs($companyAdmin)
            ->post(route('companies.customers.store', $this->company), [
                'name' => 'PT Assessment Truth Customer',
                'type' => 'commercial',
                'parent_company_assessment' => 'no_parent',
                'opportunity_assessment' => 'no_opportunity',
                'review_acknowledged' => '1',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()->where('name', 'PT Assessment Truth Customer')->firstOrFail();
        $profile = CustomerProfile::query()->where('customer_id', $customer->getKey())->firstOrFail();

        $this->assertSame('no_parent', $profile->parent_company_assessment);
        $this->assertSame('no_opportunity', $profile->opportunity_assessment);
        $this->assertNotNull($profile->review_acknowledged_at);
        $this->assertSame($companyAdmin->getKey(), $profile->review_acknowledged_by);
        $this->assertDatabaseMissing('customer_opportunities', ['customer_id' => $customer->getKey()]);
    }

    public function test_legacy_create_opportunity_flag_remains_backward_compatible(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');

        $this->actingAs($companyAdmin)
            ->post(route('companies.customers.store', $this->company), [
                'name' => 'Legacy Opportunity Customer',
                'type' => 'commercial',
                'create_opportunity' => '1',
                'opportunity_name' => 'Legacy Commercial Intake',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()->where('name', 'Legacy Opportunity Customer')->firstOrFail();
        $profile = CustomerProfile::query()->where('customer_id', $customer->getKey())->firstOrFail();

        $this->assertSame('has_opportunity', $profile->opportunity_assessment);
        $this->assertDatabaseHas('customer_opportunities', [
            'customer_id' => $customer->getKey(),
            'name' => 'Legacy Commercial Intake',
        ]);
    }

    public function test_customer_create_draft_is_scoped_to_company_and_user(): void
    {
        $companyAdmin = $this->createCompanyUser('company_admin');

        $this->actingAs($companyAdmin)
            ->postJson(route('companies.customers.create-draft.store', $this->company), [
                'payload' => [
                    'name' => 'Draft Customer',
                    'type' => 'commercial',
                    'primary_industry' => 'Food Processing',
                    'contacts' => [[
                        'full_name' => 'Draft Contact',
                        'email' => 'draft@example.test',
                    ]],
                    'ignored_internal_key' => 'must not persist',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'saved');

        $draft = CustomerCreateDraft::query()->firstOrFail();
        $this->assertSame($this->company->getKey(), $draft->company_id);
        $this->assertSame($companyAdmin->getKey(), $draft->user_id);
        $this->assertSame('Draft Customer', $draft->payload['name']);
        $this->assertArrayNotHasKey('ignored_internal_key', $draft->payload);
    }

    public function test_customer_document_is_stored_privately_with_hash_metadata(): void
    {
        Storage::fake('local');
        $companyAdmin = $this->createCompanyUser('company_admin');
        $document = UploadedFile::fake()->create('company-profile.pdf', 96, 'application/pdf');

        $this->actingAs($companyAdmin)
            ->post(route('companies.customers.store', $this->company), [
                'name' => 'Document Intake Customer',
                'type' => 'commercial',
                'documents' => [
                    'company_profile' => $document,
                ],
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()->where('name', 'Document Intake Customer')->firstOrFail();
        $record = $customer->documents()->firstOrFail();

        $this->assertSame('local', $record->storage_disk);
        $this->assertSame('recorded', $record->status);
        $this->assertNotEmpty($record->sha256);
        Storage::disk('local')->assertExists($record->storage_path);
    }

    public function test_final_create_intent_requires_review_acknowledgement(): void
    {
        Storage::fake('local');
        [$company, $superAdmin] = $this->seedCompanyContext();

        $response = $this->actingAs($superAdmin)->post(route('companies.customers.store', $company), [
            'name' => 'PT Review Gate Customer',
            'type' => 'commercial',
            'submit_intent' => 'create',
        ]);

        $response->assertSessionHasErrors('review_acknowledged');
        $this->assertDatabaseMissing('customers', [
            'company_id' => $company->getKey(),
            'name' => 'PT Review Gate Customer',
        ]);
    }

    public function test_financial_statement_rejects_presentation_file_type(): void
    {
        Storage::fake('local');
        $companyAdmin = $this->createCompanyUser('company_admin');
        $presentation = UploadedFile::fake()->create(
            'financial-statements.pptx',
            64,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        );

        $this->actingAs($companyAdmin)
            ->from(route('companies.customers.create', $this->company))
            ->post(route('companies.customers.store', $this->company), [
                'name' => 'Invalid Document Category Customer',
                'type' => 'commercial',
                'documents' => [
                    'financial_statements' => $presentation,
                ],
            ])
            ->assertRedirect(route('companies.customers.create', $this->company))
            ->assertSessionHasErrors('documents.financial_statements');

        $this->assertDatabaseMissing('customers', ['name' => 'Invalid Document Category Customer']);
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
        $membership->roles()->attach($role, ['assigned_by' => $this->superAdmin->getKey()]);

        return $user;
    }
}
