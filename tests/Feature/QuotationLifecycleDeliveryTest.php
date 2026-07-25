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

class QuotationLifecycleDeliveryTest extends TestCase
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

        $this->superAdmin = User::factory()->create(['status' => 'active', 'name' => 'Lifecycle Admin']);
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $this->superAdmin->systemRoles()->attach($superAdminRole, [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);

        $this->company = Company::query()->create([
            'name' => 'NEXORIN Lifecycle Energy',
            'slug' => 'nexorin-lifecycle',
            'code' => 'NLC',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
        ]);

        $customer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NLC-CUS-00001',
            'name' => 'Lifecycle Customer',
            'type' => 'commercial',
            'email' => 'lifecycle.customer@example.test',
            'phone' => '+62 812-3333-4444',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => 'NLC-2026-00001',
            'name' => 'Quotation Lifecycle Project',
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

    public function test_delivery_requires_current_client_pdf_and_records_audit_event(): void
    {
        $this->generateQuotation();
        $this->approveQuotation();

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.issue', [$this->company, $this->project]), [
                'delivery_method' => 'email',
                'sent_to' => 'lifecycle.customer@example.test',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('approved', Quotation::query()->firstOrFail()->status);

        $this
            ->actingAs($this->superAdmin)
            ->get(route('companies.quotations.pdf', [$this->company, $this->project]))
            ->assertOk();

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.issue', [$this->company, $this->project]), [
                'delivery_method' => 'email',
                'sent_to' => 'lifecycle.customer@example.test',
                'delivery_notes' => 'Sent after commercial approval.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $quotation = Quotation::query()->with('events')->firstOrFail();

        $this->assertSame('sent', $quotation->status);
        $this->assertSame('email', $quotation->delivery_method);
        $this->assertSame('lifecycle.customer@example.test', $quotation->sent_to);
        $this->assertNotNull($quotation->issued_at);

        $eventTypes = $quotation->events->pluck('event_type')->all();
        $this->assertContains('generated', $eventTypes);
        $this->assertContains('approved', $eventTypes);
        $this->assertContains('client_pdf_generated', $eventTypes);
        $this->assertContains('sent', $eventTypes);

        $this->assertDatabaseHas('quotation_events', [
            'quotation_id' => $quotation->getKey(),
            'event_type' => 'sent',
            'channel' => 'email',
            'recipient' => 'lifecycle.customer@example.test',
        ]);

        $this->project->refresh()->load(['billOfMaterial.items', 'financialAnalysis', 'quotation']);
        $currentFingerprint = app(\App\Services\Quotation\QuotationEngine::class)->sourceFingerprint(
            $this->project,
            $this->project->billOfMaterial,
            $this->project->financialAnalysis
        );

        $this->assertTrue($quotation->isSourceCurrent($currentFingerprint));
        $this->assertTrue($quotation->hasCurrentClientProposalPdf($currentFingerprint));
    }

    public function test_sent_quotation_can_be_accepted_with_audit_trail(): void
    {
        $this->generateApprovePdfAndSend();

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.accept', [$this->company, $this->project]), [
                'acceptance_notes' => 'Client confirmed by email.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('accepted', $quotation->status);
        $this->assertNotNull($quotation->accepted_at);
        $this->assertSame($this->superAdmin->getKey(), $quotation->accepted_by);
        $this->assertSame('Client confirmed by email.', $quotation->acceptance_notes);
        $this->assertSame('approved', $this->project->fresh()->status);

        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $this->project->getKey(),
            'from_status' => 'quotation',
            'to_status' => 'approved',
            'changed_by' => $this->superAdmin->getKey(),
        ]);

        $this->assertDatabaseHas('quotation_events', [
            'quotation_id' => $quotation->getKey(),
            'event_type' => 'accepted',
            'status_from' => 'sent',
            'status_to' => 'accepted',
        ]);
    }

    public function test_issue_and_acceptance_do_not_stale_current_client_release_approval(): void
    {
        $this->generateQuotation();
        $this->approveQuotation();

        $this
            ->actingAs($this->superAdmin)
            ->get(route('companies.quotations.pdf', [$this->company, $this->project]))
            ->assertOk();

        $gate = app(ClientReleaseGate::class);
        $approval = $gate->approve($this->project->fresh(), $this->superAdmin, 'Approved before delivery.');
        $this->assertNotNull($approval->getKey());
        $this->assertTrue($gate->evaluate($this->project->fresh())['ready']);

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.issue', [$this->company, $this->project]), [
                'delivery_method' => 'email',
                'sent_to' => 'lifecycle.customer@example.test',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($gate->evaluate($this->project->fresh())['ready']);

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.accept', [$this->company, $this->project]), [
                'acceptance_notes' => 'Customer accepted without content changes.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $result = $gate->evaluate($this->project->fresh());
        $this->assertTrue($result['ready']);
        $this->assertTrue($result['approval_current']);
        $this->assertFalse($result['approval_stale']);
    }

    public function test_rejection_requires_reason_and_records_status(): void
    {
        $this->generateApprovePdfAndSend();

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.reject', [$this->company, $this->project]))
            ->assertSessionHasErrors('rejection_reason');

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.reject', [$this->company, $this->project]), [
                'rejection_reason' => 'Client postponed the project budget.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('rejected', $quotation->status);
        $this->assertSame('Client postponed the project budget.', $quotation->rejection_reason);
        $this->assertNotNull($quotation->rejected_at);

        $this->assertDatabaseHas('quotation_events', [
            'quotation_id' => $quotation->getKey(),
            'event_type' => 'rejected',
            'status_from' => 'sent',
            'status_to' => 'rejected',
        ]);
    }

    public function test_expired_quotation_is_marked_and_cannot_be_sent_or_accepted(): void
    {
        $this->generateQuotation();
        $this->approveQuotation();

        $quotation = Quotation::query()->firstOrFail();
        $quotation->forceFill([
            'valid_until' => now()->subDay()->toDateString(),
        ])->save();

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.issue', [$this->company, $this->project]), [
                'delivery_method' => 'manual',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $quotation = $quotation->fresh();
        $this->assertSame('expired', $quotation->status);
        $this->assertNotNull($quotation->expired_at);

        $this->assertDatabaseHas('quotation_events', [
            'quotation_id' => $quotation->getKey(),
            'event_type' => 'expired',
            'status_from' => 'approved',
            'status_to' => 'expired',
        ]);
    }

    private function generateApprovePdfAndSend(): Quotation
    {
        $this->generateQuotation();
        $this->approveQuotation();

        $this
            ->actingAs($this->superAdmin)
            ->get(route('companies.quotations.pdf', [$this->company, $this->project]))
            ->assertOk();

        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.issue', [$this->company, $this->project]), [
                'delivery_method' => 'whatsapp',
                'sent_to' => '+62 812-3333-4444',
                'delivery_notes' => 'Sent through client WhatsApp.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        return Quotation::query()->firstOrFail()->fresh();
    }

    private function generateQuotation(): void
    {
        $this
            ->actingAs($this->superAdmin)
            ->post(route('companies.quotations.generate', [$this->company, $this->project]), [
                'quotation_title' => 'Lifecycle Quotation',
                'validity_days' => 30,
                'include_item_detail' => 1,
                'payment_terms' => '50% DP and 50% before commissioning.',
                'delivery_terms' => 'Installation after site readiness confirmation.',
                'warranty_terms' => 'Manufacturer and workmanship warranty apply.',
                'exclusions' => 'Utility approval is excluded unless stated otherwise.',
                'quotation_note' => 'Lifecycle controlled quotation snapshot.',
            ])
            ->assertRedirect(route('companies.quotations.show', [$this->company, $this->project]));
    }

    private function approveQuotation(): Quotation
    {
        $this
            ->actingAs($this->superAdmin)
            ->patch(route('companies.quotations.approve', [$this->company, $this->project]), [
                'approval_notes' => 'Approved for lifecycle test.',
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
            'bom_number' => 'BOM-NLC-00001',
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
            'analysis_number' => 'FIN-NLC-00001',
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
