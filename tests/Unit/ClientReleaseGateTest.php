<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Services\Report\ClientReleaseGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientReleaseGateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => 'active']);
        $this->company = Company::query()->create([
            'name' => 'NEXORIN Energy',
            'slug' => 'nexorin-energy',
            'code' => 'NXR',
            'status' => 'active',
            'created_by' => $this->user->getKey(),
        ]);

        $customer = Customer::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_number' => 'NXR-CUS-00001',
            'name' => 'Release Gate Customer',
            'type' => 'residential',
            'status' => 'active',
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $customer->getKey(),
            'project_number' => 'NXR-2026-00001',
            'name' => 'Client Release Gate Project',
            'building_type' => 'residential',
            'proposed_system' => 'hybrid',
            'site_address' => 'Pekanbaru, Riau',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'status' => 'quotation',
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
            'status_updated_by' => $this->user->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->attachReadyCommercialStack();
    }

    public function test_gate_requires_explicit_approval_before_client_release_is_ready(): void
    {
        $gate = app(ClientReleaseGate::class);

        $beforeApproval = $gate->evaluate($this->project->fresh());

        $this->assertSame('Pending Client Release Approval', $beforeApproval['status']);
        $this->assertTrue($beforeApproval['approval_allowed']);
        $this->assertFalse($beforeApproval['ready']);
        $this->assertSame([], $beforeApproval['issues']);

        $approval = $gate->approve($this->project->fresh(), $this->user, 'Approved after engineering and commercial review.');

        $this->assertSame('active', $approval->status);
        $this->assertSame('Ready for Client Release', $approval->release_status);
        $this->assertNotEmpty($approval->gate_hash);

        $afterApproval = $gate->evaluate($this->project->fresh());

        $this->assertSame('Ready for Client Release', $afterApproval['status']);
        $this->assertTrue($afterApproval['ready']);
        $this->assertTrue($afterApproval['approval_current']);
        $this->assertFalse($afterApproval['approval_stale']);
    }

    public function test_gate_blocks_approval_when_engineering_warning_is_open(): void
    {
        $this->project->technicalCalculation->update([
            'warnings' => [
                [
                    'severity' => 'warning',
                    'code' => 'roof_constraint_review',
                    'message' => 'Roof and site survey data still require engineer review.',
                ],
            ],
        ]);

        $gate = app(ClientReleaseGate::class);
        $result = $gate->evaluate($this->project->fresh());

        $this->assertSame('Needs Engineering Review', $result['status']);
        $this->assertFalse($result['approval_allowed']);
        $this->assertFalse($result['ready']);
        $this->assertGreaterThan(0, $result['issue_count']);

        $this->expectException(ValidationException::class);
        $gate->approve($this->project->fresh(), $this->user);
    }

    public function test_gate_marks_approval_as_stale_when_project_data_changes_after_approval(): void
    {
        $gate = app(ClientReleaseGate::class);
        $gate->approve($this->project->fresh(), $this->user, 'Initial release approval.');

        $this->project->quotation->update([
            'notes' => 'Commercial note changed after approval.',
            'updated_at' => now()->addMinutes(5),
        ]);

        $result = $gate->evaluate($this->project->fresh());

        $this->assertSame('Release Approval Stale', $result['status']);
        $this->assertFalse($result['ready']);
        $this->assertFalse($result['approval_current']);
        $this->assertTrue($result['approval_stale']);
        $this->assertTrue($result['approval_allowed']);
    }

    public function test_gate_blocks_when_boq_grand_price_is_below_material_price(): void
    {
        $this->project->billOfMaterial->update([
            'material_price_subtotal_idr' => 120_000_000,
            'grand_price_idr' => 100_000_000,
        ]);

        $gate = app(ClientReleaseGate::class);
        $result = $gate->evaluate($this->project->fresh());

        $this->assertSame('Blocked by Review Gate', $result['status']);
        $this->assertFalse($result['approval_allowed']);
        $this->assertFalse($result['ready']);
        $this->assertTrue(collect($result['issues'])->contains(fn (array $issue): bool => $issue['title'] === 'BOQ grand price is below material price.'));
    }


    public function test_gate_blocks_when_margin_guard_fails(): void
    {
        $this->project->billOfMaterial->update([
            'pricing_status' => 'margin_review_required',
            'summary' => [
                'low_margin_item_count' => 1,
                'discount_limit_exceeded_count' => 0,
                'tax_review_issue_count' => 0,
                'commercial_total_consistent' => true,
            ],
        ]);

        $gate = app(ClientReleaseGate::class);
        $result = $gate->evaluate($this->project->fresh());

        $this->assertSame('Blocked by Review Gate', $result['status']);
        $this->assertFalse($result['approval_allowed']);
        $this->assertTrue(collect($result['issues'])->contains(fn (array $issue): bool => $issue['title'] === 'BOQ margin is below the minimum guard.'));
    }

    public function test_gate_blocks_client_release_when_boq_uses_catalog_materials_pending_review(): void
    {
        $this->project->billOfMaterial->update([
            'pricing_status' => 'priced',
            'summary' => [
                'catalog_review_item_count' => 2,
                'missing_price_item_count' => 0,
                'expired_price_item_count' => 0,
                'unavailable_price_item_count' => 0,
                'low_margin_item_count' => 0,
                'discount_limit_exceeded_count' => 0,
                'tax_review_issue_count' => 0,
                'commercial_total_consistent' => true,
            ],
        ]);

        $gate = app(ClientReleaseGate::class);
        $result = $gate->evaluate($this->project->fresh());

        $this->assertSame('Needs Commercial Review', $result['status']);
        $this->assertFalse($result['approval_allowed']);
        $this->assertFalse($result['ready']);
        $this->assertTrue(collect($result['issues'])->contains(
            fn (array $issue): bool => $issue['title'] === 'BOQ uses material records that still require catalog approval.'
        ));
        $this->assertSame(2, $result['snapshot']['bom']['catalog_review_item_count']);
    }

    public function test_gate_blocks_when_discount_limit_is_exceeded(): void
    {
        $this->project->billOfMaterial->update([
            'pricing_status' => 'discount_review_required',
            'summary' => [
                'low_margin_item_count' => 0,
                'discount_limit_exceeded_count' => 1,
                'tax_review_issue_count' => 0,
                'commercial_total_consistent' => true,
            ],
        ]);

        $gate = app(ClientReleaseGate::class);
        $result = $gate->evaluate($this->project->fresh());

        $this->assertSame('Needs Commercial Review', $result['status']);
        $this->assertFalse($result['approval_allowed']);
        $this->assertTrue(collect($result['issues'])->contains(fn (array $issue): bool => $issue['title'] === 'BOQ discount exceeds the allowed limit.'));
    }

    private function attachReadyCommercialStack(): void
    {
        $technical = TechnicalCalculation::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'system_type' => 'hybrid',
            'result_status' => 'verified',
            'results' => [
                'installed_pv_kw' => 7.7,
                'annual_energy_kwh' => 9580,
                'module_count' => 14,
                'total_inverter_ac_kw' => 10,
                'battery_nominal_kwh' => 1.07,
            ],
            'engine_results' => [],
            'warnings' => [],
            'source_snapshot' => [],
            'calculated_by' => $this->user->getKey(),
            'calculated_at' => now(),
            'verified_by' => $this->user->getKey(),
            'verified_at' => now(),
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);

        $bom = BillOfMaterial::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'technical_calculation_id' => $technical->getKey(),
            'bom_number' => 'BOM-NXR-00001',
            'status' => 'generated',
            'pricing_status' => 'priced',
            'material_price_subtotal_idr' => 70_000_000,
            'labor_cost_idr' => 5_000_000,
            'logistics_cost_idr' => 1_500_000,
            'tax_idr' => 10_500_000,
            'grand_price_idr' => 100_000_000,
            'warnings' => [],
            'source_snapshot' => [],
            'generated_by' => $this->user->getKey(),
            'generated_at' => now(),
            'priced_by' => $this->user->getKey(),
            'priced_at' => now(),
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);

        $financial = FinancialAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'technical_calculation_id' => $technical->getKey(),
            'bill_of_material_id' => $bom->getKey(),
            'analysis_number' => 'FIN-NXR-00001',
            'status' => 'calculated',
            'project_capex_idr' => 100_000_000,
            'annual_energy_kwh' => 9580,
            'first_year_saving_idr' => 12_000_000,
            'warnings' => [],
            'source_snapshot' => [],
            'calculated_by' => $this->user->getKey(),
            'calculated_at' => now(),
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);

        Quotation::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'financial_analysis_id' => $financial->getKey(),
            'bill_of_material_id' => $bom->getKey(),
            'quote_number' => 'QTN-NXR-00001',
            'revision_number' => 1,
            'status' => 'issued',
            'commercial_summary' => [
                'grand_total_idr' => 100_000_000,
            ],
            'warnings' => [],
            'source_snapshot' => [],
            'prepared_by' => $this->user->getKey(),
            'issued_by' => $this->user->getKey(),
            'issued_at' => now(),
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);
    }
}
