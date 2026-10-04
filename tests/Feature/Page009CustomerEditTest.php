<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerDocument;
use App\Models\CustomerOpportunity;
use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Page009CustomerEditTest extends TestCase
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
            'name' => 'NEXORIN Customer Edit Test', 'slug' => 'nexorin-page-009', 'code' => 'NX9',
            'status' => 'active', 'created_by' => $this->superAdmin->getKey(),
        ]);
        $this->companyAdmin = $this->createCompanyUser($this->company, 'company_admin');
    }

    public function test_page_009_preloads_authoritative_page_008_customer_context(): void
    {
        [$customer, $profile, $contact, $opportunity, $document] = $this->seedCustomerContext();

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.edit', [$this->company, $customer]))
            ->assertOk()
            ->assertSee('Edit Customer')
            ->assertSee($customer->name)
            ->assertSee($profile->preferred_name)
            ->assertSee($contact->full_name)
            ->assertSee($opportunity->name)
            ->assertSee($document->original_name)
            ->assertSee('Not Assessed')
            ->assertDontSee('Relationship Score')
            ->assertDontSee('Enterprise placeholder');
    }

    public function test_update_reuses_profile_contact_and_opportunity_identity(): void
    {
        [$customer, $profile, $contact, $opportunity] = $this->seedCustomerContext();
        $owner = $this->createCompanyUser($this->company, 'sales');

        $payload = array_replace($this->basePayload($customer, $profile, $contact, $opportunity), [
            'name' => 'PT NEXORIN Customer Revised',
            'preferred_name' => 'NEXORIN Revised',
            'account_owner_user_id' => $owner->getKey(),
            'parent_company_assessment' => 'no_parent',
            'opportunity_assessment' => 'has_opportunity',
        ]);
        $payload['contacts'][0]['job_title'] = 'Director of Engineering and Energy Transition';
        $payload['opportunities'][0]['probability_pct'] = 65;

        $this->actingAs($this->companyAdmin)
            ->put(route('companies.customers.update', [$this->company, $customer]), $payload)
            ->assertRedirect(route('companies.customers.show', [$this->company, $customer]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'PT NEXORIN Customer Revised']);
        $this->assertDatabaseHas('customer_profiles', ['id' => $profile->id, 'customer_id' => $customer->id, 'preferred_name' => 'NEXORIN Revised', 'account_owner_user_id' => $owner->id]);
        $this->assertDatabaseHas('customer_contacts', ['id' => $contact->id, 'job_title' => 'Director of Engineering and Energy Transition']);
        $this->assertDatabaseHas('customer_opportunities', ['id' => $opportunity->id, 'probability_pct' => 65, 'status' => 'open']);
        $this->assertSame(1, CustomerProfile::query()->where('customer_id', $customer->id)->count());
    }

    public function test_contact_removal_is_explicit_and_does_not_delete_other_stable_contacts(): void
    {
        [$customer, $profile, $contact, $opportunity] = $this->seedCustomerContext();
        $second = CustomerContact::query()->create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'full_name' => 'Second Stable Contact',
            'email' => 'second@example.test', 'is_primary' => false, 'sort_order' => 1,
            'created_by' => $this->companyAdmin->id, 'updated_by' => $this->companyAdmin->id,
        ]);
        $payload = $this->basePayload($customer, $profile, $contact, $opportunity);
        $payload['contacts'][] = ['id' => $second->id, 'full_name' => $second->full_name, 'email' => $second->email, 'is_primary' => '0', '_remove' => '0'];
        $payload['contacts'][0]['_remove'] = '1';
        $payload['contacts'][0]['is_primary'] = '0';

        $this->actingAs($this->companyAdmin)
            ->put(route('companies.customers.update', [$this->company, $customer]), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('customer_contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('customer_contacts', ['id' => $second->id, 'customer_id' => $customer->id]);
    }

    public function test_cross_company_account_owner_is_rejected_server_side(): void
    {
        [$customer, $profile, $contact, $opportunity] = $this->seedCustomerContext();
        $otherCompany = Company::query()->create(['name' => 'Other Tenant', 'slug' => 'other-tenant-009', 'code' => 'O9', 'status' => 'active', 'created_by' => $this->superAdmin->id]);
        $outsider = $this->createCompanyUser($otherCompany, 'sales');
        $payload = $this->basePayload($customer, $profile, $contact, $opportunity);
        $payload['account_owner_user_id'] = $outsider->id;

        $this->actingAs($this->companyAdmin)
            ->from(route('companies.customers.edit', [$this->company, $customer]))
            ->put(route('companies.customers.update', [$this->company, $customer]), $payload)
            ->assertRedirect(route('companies.customers.edit', [$this->company, $customer]))
            ->assertSessionHasErrors('account_owner_user_id');
    }

    public function test_existing_document_is_preserved_and_new_upload_is_additive_recorded_evidence(): void
    {
        Storage::fake('local');
        [$customer, $profile, $contact, $opportunity, $document] = $this->seedCustomerContext();
        $payload = $this->basePayload($customer, $profile, $contact, $opportunity);
        $payload['documents'] = ['company_profile' => UploadedFile::fake()->create('revised-company-profile.pdf', 64, 'application/pdf')];

        $this->actingAs($this->companyAdmin)
            ->put(route('companies.customers.update', [$this->company, $customer]), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_documents', ['id' => $document->id, 'customer_id' => $customer->id]);
        $new = CustomerDocument::query()->where('customer_id', $customer->id)->where('original_name', 'revised-company-profile.pdf')->firstOrFail();
        $this->assertSame('recorded', $new->status);
        $this->assertNotEmpty($new->sha256);
        Storage::disk('local')->assertExists($new->storage_path);
        $this->assertSame(2, CustomerDocument::query()->where('customer_id', $customer->id)->count());
    }

    public function test_stale_edit_baseline_is_rejected_without_overwriting_newer_customer_data(): void
    {
        [$customer, $profile, $contact, $opportunity] = $this->seedCustomerContext();
        $staleBaseline = $customer->updated_at->copy()->subMinute()->toIso8601String();
        $payload = $this->basePayload($customer, $profile, $contact, $opportunity);
        $payload['baseline_updated_at'] = $staleBaseline;
        $payload['name'] = 'Stale Overwrite Attempt';

        $this->actingAs($this->companyAdmin)
            ->from(route('companies.customers.edit', [$this->company, $customer]))
            ->put(route('companies.customers.update', [$this->company, $customer]), $payload)
            ->assertSessionHasErrors('baseline_updated_at');

        $this->assertNotSame('Stale Overwrite Attempt', $customer->fresh()->name);
    }

    public function test_archived_customer_edit_is_not_available(): void
    {
        [$customer] = $this->seedCustomerContext();
        $customer->update(['status' => 'archived', 'archived_at' => now()]);

        $this->actingAs($this->companyAdmin)
            ->get(route('companies.customers.edit', [$this->company, $customer]))
            ->assertNotFound();
    }

    private function seedCustomerContext(): array
    {
        $customer = Customer::query()->create([
            'company_id' => $this->company->id, 'customer_number' => 'NX9-CUS-00001',
            'name' => 'PT Surya Energi Nusantara International Manufacturing and Industrial Solutions',
            'type' => 'commercial', 'email' => 'energy.transition.procurement@example-enterprise.test',
            'phone' => '+62 812 3456 7890', 'address' => 'Industrial Estate Building 12, Very Long Customer Address, Jakarta',
            'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '12950', 'notes' => 'Authoritative customer relationship note.',
            'status' => 'active', 'created_by' => $this->companyAdmin->id, 'updated_by' => $this->companyAdmin->id,
        ]);
        $profile = CustomerProfile::query()->create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'preferred_name' => 'Surya Energi Nusantara',
            'website' => 'https://www.example-enterprise.test/energy-transition/customer-relationship', 'tax_id' => '01.234.567.8-999.000',
            'registration_number' => 'AHU-1234567890.AH.01.01.TAHUN-2026', 'currency_code' => 'IDR', 'timezone' => 'Asia/Jakarta',
            'relationship_stage' => 'prospect', 'primary_industry' => 'Industrial Manufacturing', 'hq_country' => 'Indonesia',
            'parent_company_assessment' => 'not_assessed', 'opportunity_assessment' => 'has_opportunity',
            'review_acknowledged_at' => now(), 'review_acknowledged_by' => $this->companyAdmin->id,
            'created_by' => $this->companyAdmin->id, 'updated_by' => $this->companyAdmin->id,
        ]);
        $contact = CustomerContact::query()->create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'full_name' => 'Ayu Rahmawati Kusumawardhani',
            'job_title' => 'Head of Engineering and Sustainable Energy Procurement', 'email' => 'ayu.rahmawati@example-enterprise.test',
            'phone' => '+62 811 2222 3333', 'is_primary' => true, 'sort_order' => 0,
            'created_by' => $this->companyAdmin->id, 'updated_by' => $this->companyAdmin->id,
        ]);
        $opportunity = CustomerOpportunity::query()->create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'name' => 'Multi-site Rooftop Solar Engineering Assessment',
            'opportunity_type' => 'Solar PV', 'estimated_value' => 3250000000, 'currency_code' => 'IDR', 'probability_pct' => 40,
            'expected_close_date' => now()->addMonths(3)->toDateString(), 'status' => 'open',
            'created_by' => $this->companyAdmin->id, 'updated_by' => $this->companyAdmin->id,
        ]);
        $document = CustomerDocument::query()->create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'category' => 'company_profile', 'title' => 'Company Profile',
            'original_name' => 'customer-company-profile-governance-evidence-2026.pdf', 'storage_disk' => 'local',
            'storage_path' => 'customer-documents/test/existing.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 98304,
            'sha256' => hash('sha256', 'existing-document'), 'status' => 'recorded', 'created_by' => $this->companyAdmin->id,
        ]);
        return [$customer, $profile, $contact, $opportunity, $document];
    }

    private function basePayload(Customer $customer, CustomerProfile $profile, CustomerContact $contact, CustomerOpportunity $opportunity): array
    {
        return [
            'baseline_updated_at' => $customer->updated_at->toIso8601String(), 'contacts_sync' => '1',
            'name' => $customer->name, 'preferred_name' => $profile->preferred_name, 'type' => $customer->type,
            'email' => $customer->email, 'phone' => $customer->phone, 'website' => $profile->website,
            'tax_id' => $profile->tax_id, 'registration_number' => $profile->registration_number, 'currency_code' => 'IDR',
            'timezone' => 'Asia/Jakarta', 'relationship_stage' => 'prospect', 'primary_industry' => 'Industrial Manufacturing',
            'parent_company_assessment' => 'not_assessed', 'hq_country' => 'Indonesia', 'city' => $customer->city,
            'province' => $customer->province, 'postal_code' => $customer->postal_code, 'address' => $customer->address,
            'opportunity_assessment' => 'has_opportunity', 'notes' => $customer->notes,
            'contacts' => [[
                'id' => $contact->id, 'full_name' => $contact->full_name, 'job_title' => $contact->job_title,
                'email' => $contact->email, 'phone' => $contact->phone, 'is_primary' => '1', '_remove' => '0',
            ]],
            'opportunities' => [[
                'id' => $opportunity->id, 'name' => $opportunity->name, 'opportunity_type' => $opportunity->opportunity_type,
                'estimated_value' => $opportunity->estimated_value, 'currency_code' => $opportunity->currency_code,
                'probability_pct' => $opportunity->probability_pct, 'expected_close_date' => $opportunity->expected_close_date?->format('Y-m-d'),
            ]],
            'review_acknowledged' => '1',
        ];
    }

    private function createCompanyUser(Company $company, string $roleCode): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $membership = CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active',
            'invited_by' => $this->superAdmin->id, 'joined_at' => now(),
        ]);
        $role = Role::query()->where('code', $roleCode)->where('scope', 'company')->firstOrFail();
        $membership->roles()->attach($role, ['assigned_by' => $this->superAdmin->id]);
        return $user;
    }
}
