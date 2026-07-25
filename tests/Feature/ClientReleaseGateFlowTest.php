<?php

namespace Tests\Feature;

use App\Models\BillOfMaterial;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Customer;
use App\Models\FinancialAnalysis;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\TechnicalCalculation;
use App\Models\User;
use App\Services\Report\PremiumProjectReportPdfRenderer;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientReleaseGateFlowTest extends TestCase
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

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
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
            'name' => 'Release Flow Customer',
            'type' => 'residential',
            'status' => 'active',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $this->project = Project::query()->create([
            'company_id' => $this->company->getKey(),
            'customer_id' => $this->customer->getKey(),
            'project_number' => 'NXR-2026-00009',
            'name' => 'Client Release Flow Project',
            'building_type' => 'residential',
            'proposed_system' => 'hybrid',
            'site_address' => 'Pekanbaru, Riau',
            'city' => 'Pekanbaru',
            'province' => 'Riau',
            'status' => 'quotation',
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
            'status_updated_by' => $this->superAdmin->getKey(),
            'status_changed_at' => now(),
        ]);

        $this->attachReadyCommercialStack();
    }

    public function test_company_admin_can_view_release_gate_and_locked_final_action(): void
    {
        $admin = $this->createCompanyUser('company_admin');

        $this->actingAs($admin)
            ->get(route('companies.projects.show', [$this->company, $this->project]))
            ->assertOk()
            ->assertSee('Client Release Gate')
            ->assertSee('Approval Status')
            ->assertSee('Pending Client Release Approval')
            ->assertSee('Final Client Proposal PDF')
            ->assertSee('Locked');
    }

    public function test_engineer_can_approve_when_gate_passes(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->from(route('companies.projects.show', [$this->company, $this->project]))
            ->post(route('companies.projects.report.client-release.approve', [$this->company, $this->project]), [
                'notes' => 'Engineering and commercial checks completed.',
            ])
            ->assertRedirect(route('companies.projects.show', [$this->company, $this->project]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('project_release_approvals', [
            'project_id' => $this->project->getKey(),
            'status' => 'active',
            'release_status' => 'Ready for Client Release',
            'approved_by' => $engineer->getKey(),
        ]);
    }

    public function test_sales_cannot_approve_client_release_even_with_export_permissions(): void
    {
        $sales = $this->createCompanyUser('sales');

        $this->actingAs($sales)
            ->from(route('companies.projects.show', [$this->company, $this->project]))
            ->post(route('companies.projects.report.client-release.approve', [$this->company, $this->project]), [
                'notes' => 'Sales attempt.',
            ])
            ->assertRedirect(route('companies.projects.show', [$this->company, $this->project]))
            ->assertSessionHas('error', 'Only company admins and engineers may approve client release.');

        $this->assertDatabaseMissing('project_release_approvals', [
            'project_id' => $this->project->getKey(),
            'status' => 'active',
        ]);
    }

    public function test_customer_cannot_approve_client_release(): void
    {
        $customerUser = $this->createCompanyUser('customer');

        $this->actingAs($customerUser)
            ->from(route('companies.projects.show', [$this->company, $this->project]))
            ->post(route('companies.projects.report.client-release.approve', [$this->company, $this->project]), [
                'notes' => 'Customer attempt.',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('project_release_approvals', [
            'project_id' => $this->project->getKey(),
            'status' => 'active',
        ]);
    }

    public function test_stale_approval_is_visible_in_project_cockpit(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->actingAs($engineer)
            ->post(route('companies.projects.report.client-release.approve', [$this->company, $this->project]), [
                'notes' => 'Initial approval.',
            ]);

        $this->project->quotation->update([
            'notes' => 'Changed after release approval.',
            'updated_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($engineer)
            ->get(route('companies.projects.show', [$this->company, $this->project]))
            ->assertOk()
            ->assertSee('Release Approval Stale')
            ->assertSee('Stale Approval');
    }

    public function test_final_client_pdf_is_blocked_when_not_approved(): void
    {
        $admin = $this->createCompanyUser('company_admin');

        $this->actingAs($admin)
            ->get(route('companies.projects.report.client-proposal.final-pdf', [$this->company, $this->project]))
            ->assertRedirect(route('companies.projects.report.client-proposal.preview', [$this->company, $this->project]))
            ->assertSessionHas('error');
    }

    public function test_draft_client_preview_is_available_with_watermark(): void
    {
        $admin = $this->createCompanyUser('company_admin');

        $this->actingAs($admin)
            ->get(route('companies.projects.report.client-proposal.preview', [$this->company, $this->project]))
            ->assertOk()
            ->assertSee('ENGINEERING REVIEW DRAFT');
    }

    public function test_final_client_pdf_downloads_after_current_approval(): void
    {
        $engineer = $this->createCompanyUser('engineer');

        $this->app->instance(PremiumProjectReportPdfRenderer::class, new class extends PremiumProjectReportPdfRenderer {
            public function __construct() {}

            public function render(Project $project, string $reportMode = 'internal'): string
            {
                return '%PDF-1.4 NEXORIN final proposal test';
            }

            public function filename(Project $project, string $reportMode = 'internal'): string
            {
                return 'NXR-2026-00009-client-proposal.pdf';
            }
        });

        $this->actingAs($engineer)
            ->post(route('companies.projects.report.client-release.approve', [$this->company, $this->project]), [
                'notes' => 'Approved for final PDF.',
            ]);

        $this->actingAs($engineer)
            ->get(route('companies.projects.report.client-proposal.final-pdf', [$this->company, $this->project]))
            ->assertOk()
            ->assertHeader('X-NEXORIN-Premium-Renderer', 'playwright-client-proposal-final')
            ->assertHeader('Content-Disposition', 'attachment; filename="NXR-2026-00009-final-client-proposal.pdf"');
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

        $membership->roles()->attach($role, [
            'assigned_by' => $this->superAdmin->getKey(),
        ]);

        return $user;
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
            'calculated_by' => $this->superAdmin->getKey(),
            'calculated_at' => now(),
            'verified_by' => $this->superAdmin->getKey(),
            'verified_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $bom = BillOfMaterial::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'technical_calculation_id' => $technical->getKey(),
            'bom_number' => 'BOM-NXR-00009',
            'status' => 'generated',
            'pricing_status' => 'priced',
            'material_price_subtotal_idr' => 70_000_000,
            'labor_cost_idr' => 5_000_000,
            'logistics_cost_idr' => 1_500_000,
            'tax_idr' => 10_500_000,
            'grand_price_idr' => 100_000_000,
            'warnings' => [],
            'source_snapshot' => [],
            'generated_by' => $this->superAdmin->getKey(),
            'generated_at' => now(),
            'priced_by' => $this->superAdmin->getKey(),
            'priced_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        $financial = FinancialAnalysis::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'technical_calculation_id' => $technical->getKey(),
            'bill_of_material_id' => $bom->getKey(),
            'analysis_number' => 'FIN-NXR-00009',
            'status' => 'calculated',
            'project_capex_idr' => 100_000_000,
            'annual_energy_kwh' => 9580,
            'first_year_saving_idr' => 12_000_000,
            'warnings' => [],
            'source_snapshot' => [],
            'calculated_by' => $this->superAdmin->getKey(),
            'calculated_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);

        Quotation::query()->create([
            'company_id' => $this->company->getKey(),
            'project_id' => $this->project->getKey(),
            'financial_analysis_id' => $financial->getKey(),
            'bill_of_material_id' => $bom->getKey(),
            'quote_number' => 'QTN-NXR-00009',
            'revision_number' => 1,
            'title' => 'Quotation for Residential Hybrid Rooftop PV System',
            'status' => 'issued',
            'commercial_summary' => [
                'grand_total_idr' => 100_000_000,
            ],
            'terms' => [
                'payment_terms' => '50% down payment and 50% before commissioning.',
            ],
            'warnings' => [],
            'source_snapshot' => [],
            'prepared_by' => $this->superAdmin->getKey(),
            'issued_by' => $this->superAdmin->getKey(),
            'issued_at' => now(),
            'created_by' => $this->superAdmin->getKey(),
            'updated_by' => $this->superAdmin->getKey(),
        ]);
    }
}
