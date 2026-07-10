<?php

namespace Tests\Feature;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Services\Report\ClientReleaseGate;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProposalPdfBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Company $company;

    private Project $project;

    private BillOfMaterial $bom;

    private FinancialAnalysis $analysis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->superAdmin = User::factory()->create(['status' => 'active', 'name' => 'Proposal Admin']);
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $this->superAdmin->systemRoles()->attach($superAdminRole, [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);

        $this->company = Company::query()->create([
            'name' => 'NEXORIN Client Proposal Energy',
            'slug' => 'nexorin-client-proposal',
            'code' => 'NCP',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $customer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NCP-CUS-00001',
            'name' => 'Client Proposal Customer',
            'type' => 'commercial',
            'email' => 'client.proposal@example.test',
            'phone' => '+62 812-1111-2222',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => 'NCP-2026-00001',
            'name' => 'Snapshot Locked Client Proposal Project',
            'building_type' => 'commercial',
            'proposed_system' => 'on_grid',
            'site_address' => 'Pekanbaru, Riau',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'status' => 'quotation',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->attachReadyStack();
    }

    public function test_client_proposal_pdf_is_blocked_until_quotation_is_approved(): void
    {
        $this->generateQuotation();

        $this
            ->actingAs($this->superAdmin)
            ->from(route('companies.quotations.show', [$this->company, $this->project]))
            ->get(route('companies.quotations.pdf', [$this->company, $this->project]))
            ->assertRedirect(route('companies.quotations.show', [$this->company, $this->project]))
            ->assertSessionHasErrors('quotation');

        $this->assertNull(Quotation::query()->firstOrFail()->client_proposal_pdf_generated_at);
    }

    public function test_approved_client_proposal_pdf_uses_snapshot_and_hides_internal_data(): void
    {
        $this->generateQuotation();
        $quotation = $this->approveQuotation();

        $quotation->items()->firstOrFail()->update([
            'item_name' => 'LEAKED LIVE ITEM NAME',
            'description' => 'This changed live item must not be used by the approved proposal PDF.',
            'unit_price_idr' => 999_999_999,
            'line_price_idr' => 999_999_999,
        ]);

        $response = $this
            ->actingAs($this->superAdmin)
            ->get(route('companies.quotations.pdf', [$this->company, $this->project]));

        $response
            ->assertOk()
            ->assertHeader('X-NEXORIN-Client-Proposal-Snapshot', (string) $quotation->snapshot_hash);

        $pdf = $response->baseResponse->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Premium Client Proposal', $pdf);
        $this->assertStringContainsString('QTN-NCP-'.now()->format('Ymd').'-001 Rev.0', $pdf);
        $this->assertStringContainsString('PV Module 550 Wp', $pdf);
        $this->assertStringNotContainsString('LEAKED LIVE ITEM NAME', $pdf);
        $this->assertStringNotContainsString('unit_cost_idr', $pdf);
        $this->assertStringNotContainsString('gross_margin_idr', $pdf);
        $this->assertStringNotContainsString('Internal Supplier', $pdf);

        $quotation = $quotation->fresh();
        $this->assertNotNull($quotation->client_proposal_pdf_generated_at);
        $this->assertSame($quotation->snapshot_hash, $quotation->client_proposal_pdf_snapshot_hash);
        $this->assertSame($quotation->source_fingerprint, $quotation->client_proposal_pdf_source_fingerprint);
        $this->assertStringEndsWith('-client-proposal.pdf', (string) $quotation->client_proposal_pdf_filename);
    }

    public function test_release_gate_requires_current_client_proposal_pdf_for_snapshot_controlled_quotation(): void
    {
        $this->generateQuotation();
        $this->approveQuotation();

        $gate = app(ClientReleaseGate::class)->evaluate($this->project->fresh());

        $this->assertSame('Needs Commercial Review', $gate['status']);
        $this->assertFalse($gate['approval_allowed']);
        $this->assertTrue(collect($gate['issues'])->contains(fn (array $issue): bool => $issue['title'] === 'Client proposal PDF has not been generated.'));

        $this
            ->actingAs($this->superAdmin)
            ->get(route('companies.quotations.pdf', [$this->company, $this->project]))
            ->assertOk();

        $gate = app(ClientReleaseGate::class)->evaluate($this->project->fresh());

        $this->assertSame('Pending Client Release Approval', $gate['status']);
        $this->assertTrue($gate['approval_allowed']);
        $this->assertSame([], $gate['issues']);
    }

    private function generateQuotation(): void
    {
        $this
            ->actingAs($this->superAdmin)
            ->post(route('companies.quotations.generate', [$this->company, $this->project]), [
                'quotation_title' => 'Premium Client Proposal Snapshot',
                'validity_days' => 30,
                'include_item_detail' => 1,
                'payment_terms' => '50% DP and 50% before commissioning.',
                'delivery_terms' => 'Installation after site readiness confirmation.',
                'warranty_terms' => 'Manufacturer and workmanship warranty apply.',
                'exclusions' => 'Utility approval is excluded unless stated otherwise.',
                'quotation_note' => 'Client proposal PDF must use approved snapshot data.',
            ])
            ->assertRedirect(route('companies.quotations.show', [$this->company, $this->project]));
    }

    private function approveQuotation(): Quotation
    {
        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.approve', [$this->company, $this->project]), [
                'approval_notes' => 'Approved for client proposal PDF generation.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        return Quotation::query()->firstOrFail()->fresh();
    }

    private function attachReadyStack(): void
    {
        $technical = TechnicalCalculation::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'system_type' => 'on_grid',
            'result_status' => 'verified',
            'results' => [
                'installed_pv_kw' => 20,
                'annual_energy_kwh' => 28_000,
                'module_count' => 40,
                'inverter_count' => 2,
            ],
            'engine_results' => [],
            'warnings' => [],
            'source_snapshot' => [],
            'calculated_by' => $this->superAdmin->getKey(),
            'calculated_at' => now(),
            'verified_by' => $this->superAdmin->getKey(),
            'verified_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->bom = BillOfMaterial::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'technical_calculation_id' => $technical->getKey(),
            'bom_number' => 'BOM-NCP-00001',
            'status' => 'generated',
            'pricing_status' => 'priced',
            'summary' => [
                'commercial_total_consistent' => true,
                'missing_price_item_count' => 0,
                'expired_price_item_count' => 0,
                'unavailable_price_item_count' => 0,
                'low_margin_item_count' => 0,
                'discount_limit_exceeded_count' => 0,
                'tax_review_issue_count' => 0,
            ],
            'material_cost_subtotal_idr' => 100_000_000,
            'material_price_subtotal_idr' => 125_000_000,
            'labor_cost_idr' => 10_000_000,
            'logistics_cost_idr' => 2_000_000,
            'tax_idr' => 13_000_000,
            'discount_idr' => 0,
            'grand_cost_idr' => 112_000_000,
            'grand_price_idr' => 150_000_000,
            'gross_margin_idr' => 38_000_000,
            'gross_margin_percent' => 25.33,
            'warnings' => [],
            'source_snapshot' => [],
            'generated_by' => $this->superAdmin->getKey(),
            'generated_at' => now(),
            'priced_by' => $this->superAdmin->getKey(),
            'priced_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        BillOfMaterialItem::query()->create([
            'bill_of_material_id' => $this->bom->getKey(),
            'category' => 'pv_module',
            'item_code' => 'PV-MODULE-550',
            'item_name' => 'PV Module 550 Wp',
            'description' => 'Monocrystalline PV module',
            'quantity' => 40,
            'unit' => 'pcs',
            'unit_cost_idr' => 1_900_000,
            'unit_price_idr' => 2_450_000,
            'line_cost_idr' => 76_000_000,
            'line_price_idr' => 98_000_000,
            'source_key' => 'module_count',
            'sort_order' => 1,
            'metadata' => [
                'commercial_breakdown' => [
                    'margin_percent' => 22.45,
                    'tax_exempt' => false,
                ],
                'pricing_source_trace' => [
                    'supplier' => 'Internal Supplier',
                    'supplier_sku' => 'PV-550-SUP',
                ],
            ],
        ]);

        $this->analysis = FinancialAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'technical_calculation_id' => $technical->getKey(),
            'bill_of_material_id' => $this->bom->getKey(),
            'analysis_number' => 'FIN-NCP-00001',
            'status' => 'calculated',
            'project_capex_idr' => 150_000_000,
            'annual_energy_kwh' => 28_000,
            'first_year_saving_idr' => 22_000_000,
            'simple_payback_years' => 6.82,
            'lifetime_roi_percent' => 210,
            'warnings' => [],
            'source_snapshot' => [],
            'calculated_by' => $this->superAdmin->getKey(),
            'calculated_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
    }
}
