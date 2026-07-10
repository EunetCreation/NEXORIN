<?php

namespace App\Services\Report;

use App\Models\MonthlySolarDatum;
use App\Models\Project;
use App\Support\CalculationWarning;
use App\Support\BomBoqDictionary;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectReportDataBuilder
{
    /**
     * Build a presentation-ready ViewModel for premium HTML/PDF reports.
     *
     * The renderer should not query formulas, relations, warnings, or money
     * formatting directly. This class centralizes report data so design can move
     * fast without touching calculation logic.
     *
     * @return array<string, mixed>
     */
    public function build(Project $project, string $reportMode = 'internal'): array
    {
        $this->loadReportRelations($project);
        $normalizedReportMode = $this->normalizeReportMode($reportMode);

        $load = $project->loadAnalysis;
        $solar = $project->solarProfile;
        $roof = $project->roofSiteSurvey;
        $recommendation = $project->systemRecommendation;
        $calculation = $project->technicalCalculation;
        $bom = $project->billOfMaterial;
        $financial = $project->financialAnalysis;
        $quotation = $project->quotation;

        $loadSummary = $load ? $load->calculateSummary() : [];
        $solarSummary = $solar ? $solar->calculateSummary($project) : [];
        $roofSummary = $roof ? $roof->calculateSummary() : [];
        $results = is_array($calculation?->results ?? null) ? $calculation->results : [];
        $commercial = is_array($quotation?->commercial_summary ?? null) ? $quotation->commercial_summary : [];
        $technicalSnapshot = is_array($quotation?->technical_summary ?? null) ? $quotation->technical_summary : [];
        $financialSnapshot = is_array($quotation?->financial_summary ?? null) ? $quotation->financial_summary : [];
        $warnings = $this->warnings($project);
        $displayWarnings = $this->displayWarnings($warnings, $project);
        $severityCounts = $this->severityCounts($displayWarnings);

        $installedPv = $this->firstNumber([
            $results['installed_pv_kw'] ?? null,
            $technicalSnapshot['installed_pv_kw'] ?? null,
            $results['pv_capacity_kwp'] ?? null,
        ]);

        $annualEnergy = $this->firstNumber([
            $financial?->annual_energy_kwh,
            $financialSnapshot['annual_energy_kwh'] ?? null,
            $results['annual_energy_kwh'] ?? null,
            $loadSummary['annual_energy_kwh'] ?? null,
        ]);

        $bomPricingReady = $this->bomPricingReady($bom);
        $grandTotal = $this->reportGrandTotal($bom, $quotation, $financial, $commercial, $bomPricingReady);
        $commercialBreakdown = $this->commercialBreakdown($bom, $quotation, $financial, $grandTotal, $bomPricingReady);
        $scopeStats = $this->scopeStats($bom);

        $systemLabel = $this->systemLabel(
            $recommendation?->recommended_system
            ?: $calculation?->system_type
            ?: $project->proposed_system
        );

        $statusMatrix = $this->statusMatrix($project, $displayWarnings, $bomPricingReady);
        $monthlyEnergyRows = $this->monthlyEnergyRows($project, $installedPv, $calculation?->performance_ratio);
        $releaseGate = app(ClientReleaseGate::class)->evaluate($project, $displayWarnings, $bomPricingReady);
        $releaseStatus = $releaseGate['status'];
        $clientReleaseReady = (bool) $releaseGate['ready'];

        return [
            'meta' => [
                'generated_at' => now()->format('d M Y, H:i'),
                'document_title' => $normalizedReportMode === 'client' ? 'Client Proposal' : 'Premium Engineering Report',
                'document_code' => $project->project_number ?: 'PROJECT-'.$project->getKey(),
                'classification' => $releaseStatus,
                'release_status' => $releaseStatus,
                'report_mode' => $normalizedReportMode === 'client' ? 'Client Proposal PDF' : 'Internal Engineering Report',
                'client_release_ready' => $clientReleaseReady,
                'watermark' => $normalizedReportMode === 'client' && ! $clientReleaseReady ? 'ENGINEERING REVIEW DRAFT' : null,
                'revision' => $quotation ? 'R'.$quotation->revision_number : 'R0',
                'release_gate' => $releaseGate,
            ],
            'company' => [
                'name' => $project->company?->name ?: 'NEXORIN',
                'email' => $project->company?->email ?? null,
                'phone' => $project->company?->phone ?? null,
                'address' => $project->company?->address ?? null,
            ],
            'customer' => [
                'name' => $project->customer?->name ?: 'Customer not recorded',
                'email' => $project->customer?->email,
                'phone' => $project->customer?->phone,
                'address' => $project->customer?->address,
            ],
            'project' => [
                'id' => $project->getKey(),
                'number' => $project->project_number ?: 'PROJECT-'.$project->getKey(),
                'name' => $project->name ?: 'Untitled Project',
                'status' => $this->label($project->status),
                'building_type' => $this->label($project->building_type),
                'site_address' => $project->site_address ?: 'Site address not recorded',
                'city' => $project->city,
                'province' => $project->province,
                'coordinates' => $this->coordinates($project->latitude, $project->longitude),
                'description' => $project->description,
            ],
            'headline' => [
                'system_type' => $systemLabel,
                'status_label' => $this->healthLabel($severityCounts),
                'executive_note' => $this->executiveNote($systemLabel, $installedPv, $annualEnergy, $severityCounts),
                'executive_note_compact' => $this->executiveNoteCompact($systemLabel, $installedPv, $annualEnergy, $severityCounts),
            ],
            'kpis' => [
                [
                    'label' => 'Recommended System',
                    'value' => $systemLabel,
                    'caption' => 'Primary configuration',
                    'tone' => 'teal',
                ],
                [
                    'label' => 'PV Capacity',
                    'value' => $this->number($installedPv, 2, ' kWp'),
                    'caption' => 'Installed DC array',
                    'tone' => 'navy',
                ],
                [
                    'label' => 'Annual Energy',
                    'value' => $this->number($annualEnergy, 0, ' kWh'),
                    'caption' => 'Estimated first-year production',
                    'tone' => 'gold',
                ],
                [
                    'label' => 'Grand Price',
                    'value' => $this->money($grandTotal),
                    'caption' => 'Customer-facing value',
                    'tone' => 'slate',
                ],
            ],
            'summary_metrics' => [
                'payback' => $this->number($financial?->simple_payback_years ?? $financialSnapshot['simple_payback_years'] ?? null, 2, ' years'),
                'roi' => $this->number($financial?->lifetime_roi_percent ?? $financialSnapshot['lifetime_roi_percent'] ?? null, 2, '%'),
                'npv' => $this->money($financial?->npv_idr ?? $financialSnapshot['npv_idr'] ?? null),
                'lcoe' => $this->money($financial?->lcoe_idr_per_kwh ?? $financialSnapshot['lcoe_idr_per_kwh'] ?? null).'/kWh',
                'self_consumption' => $this->number($financial?->self_consumption_percent ?? $financialSnapshot['self_consumption_percent'] ?? null, 2, '%'),
                'warning_health' => $this->healthLabel($severityCounts),
            ],
            'recommendation' => [
                'available' => $recommendation !== null,
                'system' => $systemLabel,
                'engine_system' => $this->systemLabel($recommendation?->engine_recommended_system),
                'status' => $recommendation?->result_status_label ?? 'Recommendation not generated',
                'on_grid_score' => $this->number($recommendation?->on_grid_score, 2),
                'hybrid_score' => $this->number($recommendation?->hybrid_score, 2),
                'decisiveness' => $this->number($recommendation?->decisiveness_index, 2, '%'),
                'grid_status' => $recommendation?->grid_connection_status_label ?? 'Not recorded',
                'objective' => $recommendation?->primary_objective_label ?? 'Not recorded',
                'budget' => $recommendation?->budget_flexibility_label ?? 'Not recorded',
                'export_policy' => $recommendation?->export_policy_label ?? 'Not recorded',
                'reasons' => $this->reasons($recommendation?->reasons),
                'score_rows' => $this->scoreRows($recommendation?->scores),
                'score_rows_note' => $this->scoreRowsNote($recommendation?->scores),
            ],
            'technical' => [
                'status' => $this->statusLabel($calculation?->result_status_label ?? 'Calculation not available'),
                'system_type' => $this->systemLabel($calculation?->system_type),
                'installed_pv_kw' => $this->number($installedPv, 2, ' kWp'),
                'module_count' => $this->number($results['module_count'] ?? null, 0, ' modules'),
                'module_power' => $this->number($calculation?->module_power_wp, 0, ' Wp'),
                'inverter_power' => $this->inverterPower($calculation, $results),
                'battery_capacity' => $this->batteryCapacity($results, $technicalSnapshot, $calculation?->system_type),
                'battery_power' => $this->batteryPower($results),
                'dc_ac_ratio' => $this->number($this->safeDcAcRatio($results, $calculation), 2),
                'string_design' => $this->stringDesign($results),
                'voltage_drop_dc' => $this->number($results['dc_voltage_drop_percent'] ?? null, 2, '%'),
                'voltage_drop_ac' => $this->number($results['ac_voltage_drop_percent'] ?? null, 2, '%'),
                'calculated_at' => $calculation?->calculated_at?->format('d M Y, H:i') ?? 'Not calculated',
                'verified_at' => $calculation?->verified_at?->format('d M Y, H:i') ?? 'Not verified',
            ],
            'load' => [
                'source' => $this->label($load?->source_mode),
                'phase' => $this->label($load?->supply_phase),
                'tariff' => $load?->tariff_group ?: 'Not recorded',
                'daily_energy' => $this->number($loadSummary['daily_energy_kwh'] ?? null, 2, ' kWh/day'),
                'monthly_energy' => $this->number($loadSummary['monthly_energy_kwh'] ?? null, 0, ' kWh/month'),
                'peak_demand' => $this->number($loadSummary['peak_demand_kw'] ?? null, 2, ' kW'),
                'critical_energy' => $this->number($loadSummary['critical_daily_energy_kwh'] ?? null, 2, ' kWh/day'),
                'day_share' => $this->number($loadSummary['day_share_percent'] ?? null, 2, '%'),
                'night_share' => $this->number($loadSummary['night_share_percent'] ?? null, 2, '%'),
            ],
            'site' => [
                'solar_source' => $solar?->source_name ?: $this->label($solar?->data_source),
                'solar_quality' => $this->label($solar?->quality_status),
                'annual_irradiation' => $this->number($solarSummary['annual_irradiation_kwh_m2'] ?? null, 0, ' kWh/m²/year'),
                'average_psh' => $this->number($solarSummary['average_psh'] ?? null, 2, ' h/day'),
                'average_temperature' => $this->number($solarSummary['average_temperature_c'] ?? null, 2, ' °C'),
                'roof_quality' => $roof?->quality_status_label ?? 'Survey not available',
                'usable_area' => $this->number($roofSummary['usable_area_m2'] ?? null, 2, ' m²'),
                'weighted_shading' => $this->number($roofSummary['weighted_shading_percent'] ?? null, 2, '%'),
                'readiness' => $this->number($roofSummary['readiness_percent'] ?? null, 1, '%'),
                'structural_review' => $roof?->structural_review_status_label ?? 'Not assessed',
            ],
            'financial' => [
                'status' => $this->statusLabel($financial?->status_label ?? 'Financial analysis not available'),
                'capex' => $this->money($grandTotal ?? $financial?->project_capex_idr),
                'first_year_saving' => $this->money($financial?->first_year_saving_idr),
                'annual_om' => $this->money($financial?->annual_om_cost_idr),
                'payback' => $this->number($financial?->simple_payback_years, 2, ' years'),
                'roi' => $this->number($financial?->lifetime_roi_percent, 2, '%'),
                'npv' => $this->money($financial?->npv_idr),
                'irr' => $this->number($financial?->irr_percent, 2, '%'),
                'lcoe' => $this->money($financial?->lcoe_idr_per_kwh).'/kWh',
                'tariff' => $this->money($financial?->electricity_tariff_idr_per_kwh).'/kWh',
                'cashflow_rows' => $this->cashflowRows($financial?->cashflows),
            ],
            'commercial' => [
                'bom_status' => $this->statusLabel($bom?->status_label ?? 'BOM not available'),
                'pricing_status' => $this->statusLabel($this->label($bom?->pricing_status)),
                'pricing_ready' => $bomPricingReady,
                'breakdown_ready' => $commercialBreakdown['ready'],
                'breakdown_note' => $commercialBreakdown['note'],
                'value_source' => $commercialBreakdown['value_source'],
                'material_price' => $commercialBreakdown['material_price'],
                'labor' => $commercialBreakdown['labor'],
                'logistics' => $commercialBreakdown['logistics'],
                'tax' => $commercialBreakdown['tax'],
                'discount' => $commercialBreakdown['discount'],
                'grand_price' => $this->money($grandTotal),
                'gross_margin' => $bomPricingReady ? $this->number($bom?->gross_margin_percent, 2, '%') : 'Pending',
                'item_count' => $scopeStats['total_scope_rows'],
                'base_boq_item_count' => $scopeStats['base_boq_item_count'],
                'summary_cost_item_count' => $scopeStats['summary_cost_item_count'],
                'optional_addon_item_count' => $scopeStats['optional_addon_item_count'],
                'catalog_candidate_item_count' => $scopeStats['catalog_candidate_item_count'],
                'utility_responsibility_item_count' => $scopeStats['utility_responsibility_item_count'],
                'owner_responsibility_item_count' => $scopeStats['owner_responsibility_item_count'],
                'not_applicable_item_count' => $scopeStats['not_applicable_item_count'],
                'client_visible_item_count' => $scopeStats['client_visible_item_count'],
                'internal_visible_item_count' => $scopeStats['internal_visible_item_count'],
                'scope_stats' => $scopeStats,
                'top_items' => $this->topBomItems($bom?->items, $bomPricingReady),
                'detailed_items' => $this->detailedBomItems($bom?->items, $bomPricingReady),
                'scope_note' => 'Base BOQ items are included in the current customer-facing total. Optional items, utility responsibilities, not-applicable rows, and catalog candidates remain visible for internal scope control and are excluded until selected or re-scoped by the company.',
                'commercial_note' => $this->commercialNote($systemLabel, $financial, $grandTotal),
            ],
            'client_proposal' => $this->clientProposalData($project, $systemLabel, $installedPv, $annualEnergy, $grandTotal, $bomPricingReady, $releaseStatus, $clientReleaseReady, $releaseGate),
            'charts' => [
                'monthly_energy' => $monthlyEnergyRows,
                'monthly_energy_stats' => $this->monthlyEnergyStats($monthlyEnergyRows, $annualEnergy),
                'score_bars' => [
                    ['label' => 'On-grid', 'value' => (float) ($recommendation?->on_grid_score ?? 0)],
                    ['label' => 'Hybrid', 'value' => (float) ($recommendation?->hybrid_score ?? 0)],
                ],
                'cost_stack' => $this->costStack($bom, $grandTotal, $bomPricingReady),
            ],
            'warnings' => [
                'items' => array_slice($displayWarnings, 0, 6),
                'counts' => $severityCounts,
                'total' => count($displayWarnings),
            ],
            'status_matrix' => $statusMatrix,
            'release_gate' => $releaseGate,
            'disclaimer' => 'This premium report supports engineering, commercial, and financial decision-making. It does not replace detailed site verification, PLN/utility approval, structural assessment, final engineering sign-off, construction supervision, commissioning, or any professional responsibility required by applicable regulation.',
        ];
    }

    private function loadReportRelations(Project $project): void
    {
        if (! $project->exists) {
            return;
        }

        $project->loadMissing([
            'company',
            'customer',
            'loadAnalysis.appliances',
            'loadAnalysis.electricityBills',
            'solarProfile.monthlyData',
            'roofSiteSurvey.surfaces',
            'systemRecommendation.scores',
            'technicalCalculation.activeOverrides.creator',
            'billOfMaterial.items',
            'financialAnalysis',
            'quotation.items',
            'activeReleaseApproval.approver',
            'releaseApprovals.approver',
        ]);
    }


    private function normalizeReportMode(string $reportMode): string
    {
        $mode = Str::of($reportMode)->lower()->replace(['-', ' '], '_')->toString();

        return in_array($mode, ['client', 'client_proposal', 'proposal'], true) ? 'client' : 'internal';
    }

    /**
     * @return array<string, mixed>
     */
    private function clientProposalData(
        Project $project,
        string $systemLabel,
        ?float $installedPv,
        ?float $annualEnergy,
        ?float $grandTotal,
        bool $bomPricingReady,
        string $releaseStatus,
        bool $clientReleaseReady,
        array $releaseGate = [],
    ): array {
        $quotation = $project->quotation;
        $terms = is_array($quotation?->terms ?? null) ? $quotation->terms : [];
        $customer = $project->customer;
        $bom = $project->billOfMaterial;
        $scopeRows = $this->clientScopeItems($bom?->items, $bomPricingReady);

        return [
            'title' => $quotation?->title ?: 'Solar PV System Proposal',
            'quote_number' => $quotation?->quote_number ?: ($project->project_number ?: 'PROJECT-'.$project->getKey()),
            'revision' => $quotation ? 'R'.$quotation->revision_number : 'R0',
            'valid_until' => $quotation?->valid_until?->format('d M Y') ?? 'To be confirmed',
            'prepared_for' => $customer?->name ?: 'Customer not recorded',
            'prepared_by' => $project->company?->name ?: 'NEXORIN Energy',
            'release_ready' => $clientReleaseReady,
            'release_status' => $releaseStatus,
            'watermark' => $clientReleaseReady ? null : ($releaseGate['draft_watermark'] ?? 'ENGINEERING REVIEW DRAFT'),
            'release_note' => $clientReleaseReady
                ? 'This proposal is ready for client release based on the current approval gate.'
                : $this->clientReleaseNote($releaseStatus, $releaseGate),
            'proposal_note' => $this->clientProposalNote($systemLabel, $grandTotal),
            'why_hybrid' => $this->clientProposalWhyHybrid($systemLabel),
            'system_summary' => [
                ['label' => 'Recommended System', 'value' => $systemLabel, 'caption' => 'Selected planning basis'],
                ['label' => 'PV Capacity', 'value' => $this->number($installedPv, 2, ' kWp'), 'caption' => 'Installed DC array'],
                ['label' => 'Annual Energy', 'value' => $this->number($annualEnergy, 0, ' kWh/year'), 'caption' => 'First-year estimate'],
                ['label' => 'Proposal Value', 'value' => $this->money($grandTotal), 'caption' => 'Customer-facing total'],
            ],
            'scope_rows' => $scopeRows,
            'scope_count' => count($scopeRows),
            'terms' => [
                'Payment Terms' => $terms['payment_terms'] ?? '50% down payment and 50% before commissioning.',
                'Delivery Terms' => $terms['delivery_terms'] ?? 'Installation schedule is subject to product availability, site readiness, and final survey verification.',
                'Warranty Terms' => $terms['warranty_terms'] ?? 'Product warranty follows manufacturer terms. Workmanship warranty follows company policy.',
                'Exclusions' => $terms['exclusions'] ?? 'Utility upgrades, major structural repairs, internet subscription, and owner-side electrical works are excluded unless stated otherwise.',
            ],
            'quotation_note' => $terms['quotation_note'] ?? $quotation?->notes,
        ];
    }

    /**
     * @param array<string, mixed> $releaseGate
     */
    private function clientReleaseNote(string $releaseStatus, array $releaseGate): string
    {
        if (($releaseGate['approval_status'] ?? null) === 'stale') {
            return 'This proposal is a stale approval draft. Project data changed after approval and must be re-approved before final release.';
        }

        if (($releaseGate['approval_status'] ?? null) === 'revoked') {
            return 'This proposal is a revoked release draft. A new approval is required before final client release.';
        }

        if ($releaseStatus === 'Pending Client Release Approval') {
            return 'This proposal passed the gate checks but still requires explicit client release approval before final release.';
        }

        return 'This proposal is a client-preview draft. Open engineering or commercial notices must be resolved before final release.';
    }

    private function clientProposalNote(string $systemLabel, ?float $grandTotal): string
    {
        $price = $this->money($grandTotal);

        return "This client proposal summarizes the selected {$systemLabel} configuration, expected energy benefit, included base scope, commercial value, and standard terms. The current proposal value is {$price}.";
    }

    /**
     * @return array{title:string,points:array<int,string>}
     */
    private function clientProposalWhyHybrid(string $systemLabel): array
    {
        if (strcasecmp($systemLabel, 'Hybrid') === 0) {
            return [
                'title' => 'Why Hybrid',
                'points' => [
                    'Designed to improve backup reliability during grid interruptions.',
                    'Uses battery storage to support selected loads when the grid is unstable.',
                    'Balances PV production, direct consumption, storage support, and controlled export policy.',
                ],
            ];
        }

        if (strcasecmp($systemLabel, 'On-grid') === 0) {
            return [
                'title' => 'Why On-grid',
                'points' => [
                    'Designed to reduce daytime electricity import from the grid.',
                    'Prioritizes simple operation, lower system complexity, and customer-facing savings.',
                    'Suitable when backup power is not the primary project requirement.',
                ],
            ];
        }

        return [
            'title' => 'Why This System',
            'points' => [
                'Selected as the current planning basis from the project inputs and recommendation engine.',
                'Balances technical requirements, site conditions, commercial value, and customer objectives.',
                'Final release remains subject to engineering review and company approval.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function clientScopeItems(?Collection $items, bool $pricingReady): array
    {
        if (! $items || $items->isEmpty()) {
            return [];
        }

        return $items
            ->filter(function ($item): bool {
                $metadata = $item->metadata ?? [];
                $mode = (string) ($metadata['scope_mode'] ?? '');
                $include = (bool) ($metadata['included_in_base_price'] ?? $metadata['include_in_boq'] ?? true);

                return $include || in_array($mode, ['base_boq', 'included_in_summary_cost', 'selected_add_on'], true);
            })
            ->sortBy(function ($item): string {
                $metadata = $item->metadata ?? [];
                $rank = $this->clientScopeSortRank((string) ($item->category ?? ''), (string) ($metadata['cost_group'] ?? ''));

                return str_pad((string) $rank, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) (int) ($item->sort_order ?? 0), 6, '0', STR_PAD_LEFT).'-'.str_pad((string) (int) ($item->id ?? 0), 6, '0', STR_PAD_LEFT);
            })
            ->map(function ($item) use ($pricingReady): array {
                $metadata = $item->metadata ?? [];
                $scope = (string) ($metadata['scope_status'] ?? 'required');
                $mode = (string) ($metadata['scope_mode'] ?? null);
                $pricingState = (string) ($metadata['pricing_state'] ?? ($item->line_price_idr > 0 ? 'priced' : 'pending'));

                return [
                    'group' => BomBoqDictionary::costGroupLabel($metadata['cost_group'] ?? $item->category),
                    'category' => BomBoqDictionary::categoryLabel($item->category),
                    'name' => $this->itemDisplayName($item->item_name),
                    'scope' => $this->scopeBadge($scope, $mode),
                    'quantity' => $this->number($item->quantity, 2).' '.$item->unit,
                    'price' => $pricingReady && (float) $item->line_price_idr > 0
                        ? $this->money($item->line_price_idr)
                        : BomBoqDictionary::pricingStateLabel($pricingState),
                ];
            })
            ->values()
            ->all();
    }

    private function itemDisplayName(?string $name): string
    {
        $name = trim((string) $name);

        return match ($name) {
            'APAR and Basic Safety Equipment Allowance' => 'Portable Fire Extinguisher and Basic Safety Equipment Allowance',
            default => $name,
        };
    }

    private function clientScopeSortRank(string $category, string $costGroup): int
    {
        if ($category === 'engineering_tc') {
            return match ($costGroup) {
                'engineering' => 90,
                'testing' => 100,
                'labor' => 105,
                default => 95,
            };
        }

        if ($category === 'legal_logistics_om') {
            return match ($costGroup) {
                'legal_permit' => 110,
                'logistics' => 120,
                'om' => 130,
                default => 115,
            };
        }

        return match ($category) {
            'pv_module' => 10,
            'mounting' => 20,
            'power_conversion', 'inverter' => 30,
            'battery', 'battery_ess' => 40,
            'dc_electrical', 'connector' => 50,
            'ac_electrical', 'protection' => 60,
            'monitoring' => 70,
            'grounding_safety' => 80,
            'civil_site' => 85,
            'commercial_cost' => 140,
            default => 150,
        };
    }

    private function executiveNote(string $system, ?float $installedPv, ?float $annualEnergy, array $counts): string
    {
        $capacity = $installedPv === null ? 'proposed PV capacity' : $this->number($installedPv, 2, ' kWp');
        $energy = $annualEnergy === null ? 'estimated annual production' : $this->number($annualEnergy, 0, ' kWh/year');
        $health = $counts['critical'] > 0
            ? 'Critical issues must be resolved before commercial release.'
            : ($counts['warning'] > 0 ? 'Open notices require review before release.' : 'No critical issues are present in the current report dataset.');

        return "NEXORIN recommends {$system} as the current planning basis. The design consolidates {$capacity}, {$energy}, roof input, technical checks, BOQ pricing, and financial outputs into one controlled company report. {$health}";
    }


    private function executiveNoteCompact(string $system, ?float $installedPv, ?float $annualEnergy, array $counts): string
    {
        $capacity = $installedPv === null ? 'proposed PV capacity' : $this->number($installedPv, 2, ' kWp');
        $energy = $annualEnergy === null ? 'estimated production' : $this->number($annualEnergy, 0, ' kWh/year');
        $health = $counts['critical'] > 0
            ? 'Resolve critical issues before commercial release.'
            : ($counts['warning'] > 0 ? 'Review open notices before commercial release.' : 'No critical issues detected.');

        return "{$system} is the planning basis for {$capacity} and {$energy}. {$health}";
    }

    private function reportStatus(Project $project, array $warnings = [], bool $bomPricingReady = false): string
    {
        $hasCritical = collect($warnings)->contains(fn (array $warning): bool => ($warning['severity'] ?? null) === 'critical');
        $hasWarning = collect($warnings)->contains(fn (array $warning): bool => in_array(($warning['severity'] ?? null), ['warning', 'critical'], true));
        $calculation = $project->technicalCalculation;
        $quotation = $project->quotation;

        if ($hasCritical) {
            return 'Blocked by Review Gate';
        }

        if ($hasWarning) {
            return 'Needs Engineering Review';
        }

        if ($calculation?->result_status === 'verified' && in_array($quotation?->status, ['approved', 'sent', 'issued', 'accepted'], true) && $bomPricingReady) {
            return 'Ready for Client Release';
        }

        if ($calculation?->result_status === 'verified' && $bomPricingReady) {
            return 'Engineer Verified Draft';
        }

        if ($bomPricingReady || $project->financialAnalysis?->status === 'calculated') {
            return 'Commercial Review Draft';
        }

        return 'Preliminary Engineering Estimate';
    }

    private function warnings(Project $project): array
    {
        $warnings = [];

        if ($project->roofSiteSurvey) {
            $summary = $project->roofSiteSurvey->calculateSummary();
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($summary['warnings'] ?? [], 'roof_site_survey'));
        }

        if ($project->systemRecommendation) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->systemRecommendation->warnings ?? [], 'system_recommendation'));
        }

        if ($project->technicalCalculation) {
            $warnings = array_merge($warnings, $project->technicalCalculation->warningRecords());
        }

        if ($project->billOfMaterial) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->billOfMaterial->warnings ?? [], 'bom_boq'));
        }

        if ($project->financialAnalysis) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->financialAnalysis->warnings ?? [], 'financial_analysis'));
        }

        if ($project->quotation) {
            $warnings = array_merge($warnings, CalculationWarning::normalizeList($project->quotation->warnings ?? [], 'quotation'));
        }

        $warnings = array_merge($warnings, $this->reportGateWarnings($project));

        if ($project->technicalCalculation && $project->technicalCalculation->result_status !== 'verified') {
            $warnings[] = CalculationWarning::make(
                'ENGINEERING_VERIFICATION_REQUIRED',
                'warning',
                'Engineering Verification Required',
                'The technical calculation has not reached Engineer Verified status.',
                'Review technical assumptions, site constraints, string compatibility, and commercial impact before issuing the report.',
                'project_report'
            );
        }

        return CalculationWarning::unique($warnings);
    }

    private function severityCounts(array $warnings): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'caution' => 0, 'info' => 0];

        foreach ($warnings as $warning) {
            $severity = $warning['severity'] ?? 'info';
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        return $counts;
    }

    private function healthLabel(array $counts): string
    {
        if (($counts['critical'] ?? 0) > 0) {
            return 'Critical Review Required';
        }

        if (($counts['warning'] ?? 0) > 0) {
            return 'Review Required';
        }

        if (($counts['caution'] ?? 0) > 0) {
            return 'Attention Required';
        }

        return 'Clean Draft';
    }

    private function reasons(mixed $reasons): array
    {
        if (! is_array($reasons)) {
            return ['Recommendation reasons are not available yet.'];
        }

        $flattened = [];
        foreach ($reasons as $reason) {
            if (is_array($reason)) {
                $flattened[] = trim((string) ($reason['message'] ?? $reason['reason'] ?? $reason['text'] ?? ''));
            } else {
                $flattened[] = trim((string) $reason);
            }
        }

        $flattened = array_values(array_filter($flattened));

        return $flattened ?: ['Recommendation reasons are not available yet.'];
    }

    private function scoreRows(?Collection $scores): array
    {
        if (! $scores || $scores->isEmpty()) {
            return [];
        }

        return $scores->take(6)->map(fn ($score): array => [
            'alternative' => $score->alternative_label,
            'criterion' => $score->criterion_name,
            'weight' => $this->number($score->weight, 2),
            'rating' => $this->number($score->rating, 2),
            'weighted_score' => $this->number($score->weighted_score, 2),
            'rationale' => Str::limit($score->rationale ?: 'No rationale recorded.', 118),
        ])->values()->all();
    }

    private function stringDesign(array $results): string
    {
        $moduleCount = (int) ($results['module_count'] ?? 0);
        $strings = (int) ($results['string_count'] ?? $results['strings'] ?? 0);
        $modulesPerString = (int) ($results['modules_per_string'] ?? 0);
        $mppt = (int) ($results['mppt_used'] ?? 0);

        if ($moduleCount > 0 && $strings > 0 && $modulesPerString > 0 && ($strings * $modulesPerString) !== $moduleCount) {
            $evenModules = intdiv($moduleCount, $strings);
            $remainder = $moduleCount % $strings;

            if ($remainder === 0 && $evenModules > 0) {
                $modulesPerString = $evenModules;
            } else {
                $layout = $remainder > 0
                    ? $evenModules.'-'.($evenModules + 1).' modules/string'
                    : $modulesPerString.' modules/string';

                return trim(implode(' · ', array_filter([
                    $this->number($strings, 0, ' strings'),
                    $layout,
                    $mppt > 0 ? $this->number($mppt, 0, ' MPPT') : null,
                ])));
            }
        }

        if ($strings || $modulesPerString || $mppt) {
            return trim(implode(' · ', array_filter([
                $strings ? $this->number($strings, 0, ' strings') : null,
                $modulesPerString ? $this->number($modulesPerString, 0, ' modules/string') : null,
                $mppt ? $this->number($mppt, 0, ' MPPT') : null,
            ])));
        }

        return 'Not calculated';
    }

    private function cashflowRows(mixed $cashflows): array
    {
        if (! is_array($cashflows)) {
            return [];
        }

        return collect($cashflows)
            ->take(6)
            ->map(fn ($row, $index): array => [
                'year' => $row['year'] ?? $index,
                'saving' => $this->money($row['saving_idr'] ?? $row['savings_idr'] ?? null),
                'cost' => $this->money($row['cost_idr'] ?? $row['om_cost_idr'] ?? null),
                'net' => $this->money($row['net_cashflow_idr'] ?? $row['cashflow_idr'] ?? null),
                'cumulative' => $this->money($row['cumulative_cashflow_idr'] ?? null),
            ])
            ->values()
            ->all();
    }

    private function scopeStats(mixed $bom): array
    {
        $summary = is_array($bom?->summary ?? null) ? $bom->summary : [];
        $counters = is_array($summary['scope_counters'] ?? null) ? $summary['scope_counters'] : [];

        $defaults = [
            'total_scope_rows' => $bom?->items?->count() ?? 0,
            'base_boq_item_count' => 0,
            'summary_cost_item_count' => 0,
            'optional_addon_item_count' => 0,
            'utility_responsibility_item_count' => 0,
            'owner_responsibility_item_count' => 0,
            'catalog_candidate_item_count' => 0,
            'not_applicable_item_count' => 0,
            'client_visible_item_count' => 0,
            'internal_visible_item_count' => 0,
        ];

        $stats = array_merge($defaults, array_intersect_key($counters, $defaults));

        if (($stats['base_boq_item_count'] + $stats['optional_addon_item_count'] + $stats['catalog_candidate_item_count']) === 0 && $bom?->items) {
            foreach ($bom->items as $item) {
                $metadata = $item->metadata ?? [];
                $mode = (string) ($metadata['scope_mode'] ?? (($metadata['include_in_boq'] ?? true) ? 'base_boq' : 'optional_addon'));

                match ($mode) {
                    'base_boq' => $stats['base_boq_item_count']++,
                    'summary_cost' => $stats['summary_cost_item_count']++,
                    'optional_addon' => $stats['optional_addon_item_count']++,
                    'utility_responsibility' => $stats['utility_responsibility_item_count']++,
                    'owner_responsibility' => $stats['owner_responsibility_item_count']++,
                    'internal_catalog_candidate' => $stats['catalog_candidate_item_count']++,
                    'not_applicable' => $stats['not_applicable_item_count']++,
                    default => null,
                };

                if (($metadata['include_in_client_pdf'] ?? false) === true) {
                    $stats['client_visible_item_count']++;
                }

                if (($metadata['include_in_internal_report'] ?? true) === true) {
                    $stats['internal_visible_item_count']++;
                }
            }
        }

        return $stats;
    }

    private function topBomItems(?Collection $items, bool $pricingReady): array
    {
        if (! $items || $items->isEmpty()) {
            return [];
        }

        return $items
            ->filter(fn ($item): bool => (bool) (($item->metadata['included_in_base_price'] ?? $item->metadata['include_in_boq'] ?? true))
                && ! in_array((string) ($item->metadata['cost_group'] ?? ''), ['labor', 'logistics'], true))
            ->sortByDesc(fn ($item): float => (float) ($item->line_price_idr ?? 0))
            ->take(6)
            ->map(fn ($item): array => [
                'category' => BomBoqDictionary::categoryLabel($item->category),
                'name' => $this->itemDisplayName($item->item_name),
                'quantity' => $this->number($item->quantity, 2).' '.$item->unit,
                'price' => $pricingReady ? $this->money($item->line_price_idr) : 'Pending pricing',
            ])->values()->all();
    }


    private function detailedBomItems(?Collection $items, bool $pricingReady): array
    {
        if (! $items || $items->isEmpty()) {
            return [];
        }

        return $items
            ->filter(fn ($item): bool => ($item->metadata['include_in_pdf'] ?? true) !== false)
            ->map(function ($item) use ($pricingReady): array {
                $metadata = $item->metadata ?? [];
                $scope = (string) ($metadata['scope_status'] ?? 'required');
                $pricingState = (string) ($metadata['pricing_state'] ?? ($item->line_price_idr > 0 ? 'priced' : 'pending'));

                return [
                    'group' => BomBoqDictionary::costGroupLabel($metadata['cost_group'] ?? $item->category),
                    'category' => BomBoqDictionary::categoryLabel($item->category),
                    'name' => $this->itemDisplayName($item->item_name),
                    'scope' => BomBoqDictionary::scopeLabel($scope),
                    'mode' => BomBoqDictionary::scopeModeLabel($metadata['scope_mode'] ?? null),
                    'scope_badge' => $this->scopeBadge($scope, $metadata['scope_mode'] ?? null),
                    'quantity' => $this->number($item->quantity, 2).' '.$item->unit,
                    'price' => $pricingReady && (float) $item->line_price_idr > 0
                        ? $this->money($item->line_price_idr)
                        : BomBoqDictionary::pricingStateLabel($pricingState),
                    'basis' => (string) ($metadata['calculation_basis'] ?? 'Engineering quantity allowance'),
                ];
            })
            ->values()
            ->all();
    }


    private function scopeBadge(?string $scope, mixed $mode): string
    {
        $scopeLabel = BomBoqDictionary::scopeLabel($scope);
        $modeLabel = BomBoqDictionary::scopeModeLabel(is_string($mode) ? $mode : null);

        if ($scopeLabel === $modeLabel || $modeLabel === 'Needs Review') {
            return $scopeLabel;
        }

        return $scopeLabel.' · '.$modeLabel;
    }

    private function commercialNote(string $systemLabel, mixed $financial, ?float $grandTotal): ?string
    {
        $isHybrid = strcasecmp($systemLabel, 'Hybrid') === 0;
        $payback = is_numeric($financial?->simple_payback_years ?? null) ? (float) $financial->simple_payback_years : null;
        $npv = is_numeric($financial?->npv_idr ?? null) ? (float) $financial->npv_idr : null;
        $lcoe = is_numeric($financial?->lcoe_idr_per_kwh ?? null) ? (float) $financial->lcoe_idr_per_kwh : null;
        $tariff = is_numeric($financial?->electricity_tariff_idr_per_kwh ?? null) ? (float) $financial->electricity_tariff_idr_per_kwh : null;

        $weakReturn = ($payback !== null && $payback > 12)
            || ($npv !== null && $npv < 0)
            || ($lcoe !== null && $tariff !== null && $tariff > 0 && $lcoe > $tariff);

        if ($isHybrid && $weakReturn) {
            return 'This hybrid configuration prioritizes backup reliability. Financial return is weaker than an on-grid-only configuration because battery, protection, control, and backup-related scope increase project CAPEX.';
        }

        if ($grandTotal !== null && $grandTotal > 0 && $weakReturn) {
            return 'The current commercial result requires review. Payback, NPV, ROI, or LCOE indicate that the proposal may need scope optimization before client release.';
        }

        return null;
    }

    private function monthlyEnergyRows(Project $project, ?float $installedPvKw, mixed $performanceRatio): array
    {
        $installed = $installedPvKw ?: 0.0;
        $pr = is_numeric($performanceRatio) ? (float) $performanceRatio : 0.78;
        $rows = $project->solarProfile?->monthlyData;

        if (! $rows || $rows->isEmpty() || $installed <= 0) {
            return collect(range(1, 12))->map(fn (int $month): array => [
                'month' => substr(MonthlySolarDatum::MONTH_NAMES[$month], 0, 3),
                'value' => 0.0,
                'label' => '—',
                'height' => 0,
            ])->all();
        }

        $energyRows = $rows->map(function (MonthlySolarDatum $row) use ($installed, $pr): array {
            $psh = (float) ($row->peak_sun_hours ?: $row->ghi_daily_kwh_m2 ?: 0);
            $energy = $installed * $psh * $row->days_in_month * $pr;

            return [
                'month' => substr($row->month_name, 0, 3),
                'value' => round($energy, 1),
                'label' => $this->number($energy, 0),
            ];
        })->values();

        $max = max($energyRows->max('value') ?: 0, 1);

        return $energyRows->map(fn (array $row): array => $row + [
            'height' => (int) round(((float) $row['value'] / $max) * 100),
        ])->all();
    }

    private function costStack(mixed $bom, ?float $grandTotal, bool $pricingReady): array
    {
        $rows = [
            ['label' => 'Material', 'value' => (float) ($bom?->material_price_subtotal_idr ?? 0)],
            ['label' => 'Labor', 'value' => (float) ($bom?->labor_cost_idr ?? 0)],
            ['label' => 'Logistics', 'value' => (float) ($bom?->logistics_cost_idr ?? 0)],
            ['label' => 'Tax', 'value' => (float) ($bom?->tax_idr ?? 0)],
            ['label' => 'Other', 'value' => (float) ($bom?->other_cost_idr ?? 0) + (float) ($bom?->contingency_cost_idr ?? 0)],
        ];

        $internalTotal = array_sum(array_column($rows, 'value'));
        $ready = $pricingReady && $internalTotal > 0;
        $total = $ready ? max((float) $internalTotal, 1.0) : max((float) ($grandTotal ?: 1), 1.0);

        return array_map(fn (array $row): array => [
            'label' => $row['label'],
            'value' => $ready ? $this->money($row['value']) : 'Pending',
            'percent' => $ready ? round(((float) $row['value'] / $total) * 100, 1) : 0,
            'available' => $ready,
        ], $rows);
    }

    private function displayWarnings(array $warnings, Project $project): array
    {
        $technicalVerified = $project->technicalCalculation?->result_status === 'verified';

        return collect($warnings)->map(function (array $warning) use ($technicalVerified): array {
            $source = (string) ($warning['source'] ?? 'system');
            $code = (string) ($warning['code'] ?? '');
            $message = strtolower((string) ($warning['message'] ?? ''));

            if ($source === 'system_recommendation' && $code === 'ENGINEERING_VERIFICATION_REQUIRED') {
                $warning['title'] = 'Recommendation Review Required';
                $warning['message'] = 'The recommendation result is available, but scoring inputs and constraints still require engineer review before final release.';
            } elseif ($source === 'roof_site_survey' && ($code === 'ENGINEERING_VERIFICATION_REQUIRED' || str_contains($message, 'engineer verified'))) {
                $warning['title'] = 'Roof Survey Review Required';
                $warning['message'] = 'Roof and site data are available, but site readiness, shading, access, and structural notes still require review before procurement.';
            } elseif ($source === 'bom_boq' && $technicalVerified && (str_contains($message, 'technical calculation') || str_contains($message, 'engineer verified'))) {
                $warning['title'] = 'BOM/BOQ Refresh Required';
                $warning['message'] = 'BOM/BOQ remains a draft or is not fully synchronized with the verified technical calculation. Refresh quantity and pricing before customer release.';
            }

            $warning['source_label'] = $this->sourceLabel($source);

            return $warning;
        })->values()->all();
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'technical_calculation' => 'Technical Calculation',
            'system_recommendation' => 'Recommendation Engine',
            'roof_site_survey' => 'Roof Survey',
            'bom_boq' => 'BOM/BOQ',
            'financial_analysis' => 'Financial Analysis',
            'quotation' => 'Quotation',
            'project_report' => 'Report Validation Gate',
            default => $this->label($source),
        };
    }

    private function scoreRowsNote(?Collection $scores): string
    {
        if (! $scores || $scores->isEmpty()) {
            return 'No criteria trace has been stored yet.';
        }

        $total = $scores->count();

        return $total > 6
            ? 'Showing the top 6 trace rows. Full scoring records remain available in the database for audit.'
            : 'Showing all stored scoring trace rows for this recommendation.';
    }

    private function batteryCapacity(array $results, array $technicalSnapshot, ?string $systemType): string
    {
        $capacity = $this->firstNumber([
            $results['battery_nominal_kwh'] ?? null,
            $technicalSnapshot['battery_nominal_kwh'] ?? null,
            $results['usable_battery_kwh'] ?? null,
            $results['battery_capacity_kwh'] ?? null,
        ]);

        if ($capacity !== null && $capacity > 0) {
            return $this->number($capacity, 2, ' kWh');
        }

        return $systemType === 'hybrid' ? 'Pending sizing' : 'Not required';
    }

    private function batteryPower(array $results): string
    {
        return $this->number($results['battery_minimum_power_kw'] ?? null, 2, ' kW min');
    }

    private function bomPricingReady(mixed $bom): bool
    {
        return $bom !== null
            && $bom->pricing_status === 'priced'
            && (float) ($bom->grand_price_idr ?? 0) > 0;
    }

    private function commercialBreakdown(mixed $bom, mixed $quotation, mixed $financial, ?float $grandTotal, bool $pricingReady): array
    {
        $internalTotal = (float) ($bom?->material_price_subtotal_idr ?? 0)
            + (float) ($bom?->labor_cost_idr ?? 0)
            + (float) ($bom?->logistics_cost_idr ?? 0)
            + (float) ($bom?->tax_idr ?? 0)
            + (float) ($bom?->other_cost_idr ?? 0)
            + (float) ($bom?->contingency_cost_idr ?? 0);

        $ready = $pricingReady && $internalTotal > 0;

        if (! $ready) {
            return [
                'ready' => false,
                'note' => 'Internal BOQ pricing is not priced yet. The customer-facing value is shown from quotation or financial CAPEX, while the cost composition is still pending review.',
                'value_source' => $this->grandTotalSource($bom, $quotation, $financial, $grandTotal),
                'material_price' => 'Pending',
                'labor' => 'Pending',
                'logistics' => 'Pending',
                'tax' => 'Pending',
                'discount' => 'Pending',
            ];
        }

        return [
            'ready' => true,
            'note' => 'Internal BOQ pricing is available and can be used for commercial review.',
            'value_source' => 'Latest BOQ',
            'material_price' => $this->money($bom?->material_price_subtotal_idr),
            'labor' => $this->money($bom?->labor_cost_idr),
            'logistics' => $this->money($bom?->logistics_cost_idr),
            'tax' => $this->money($bom?->tax_idr),
            'discount' => $this->money($bom?->discount_idr),
        ];
    }

    private function grandTotalSource(mixed $bom, mixed $quotation, mixed $financial, ?float $grandTotal): string
    {
        if ($quotation && is_array($quotation->commercial_summary ?? null) && is_numeric($quotation->commercial_summary['grand_total_idr'] ?? null)) {
            return 'Issued quotation total';
        }

        if ($bom && is_numeric($bom->grand_price_idr ?? null) && (float) $bom->grand_price_idr > 0) {
            return 'BOM/BOQ grand price';
        }

        if ($financial && is_numeric($financial->project_capex_idr ?? null) && (float) $financial->project_capex_idr > 0) {
            return 'Financial CAPEX';
        }

        return $grandTotal !== null ? 'Derived project total' : 'Not available';
    }

    private function reportGrandTotal(mixed $bom, mixed $quotation, mixed $financial, array $commercial, bool $bomPricingReady): ?float
    {
        if ($bomPricingReady && is_numeric($bom?->grand_price_idr ?? null) && (float) $bom->grand_price_idr > 0) {
            return (float) $bom->grand_price_idr;
        }

        return $this->firstNumber([
            $commercial['grand_total_idr'] ?? null,
            $quotation?->commercial_summary['grand_total_idr'] ?? null,
            $bom?->grand_price_idr,
            $financial?->project_capex_idr,
        ]);
    }

    private function inverterPower(mixed $calculation, array $results): string
    {
        $total = $this->firstNumber([
            $results['total_inverter_ac_kw'] ?? null,
            null,
        ]);
        $unit = $this->firstNumber([
            $calculation?->inverter_ac_power_kw ?? null,
            $results['inverter_ac_power_kw'] ?? null,
        ]);
        $count = (int) ($results['inverter_count'] ?? 0);

        if ($total === null && $unit !== null && $count > 0) {
            $total = $unit * $count;
        }

        if ($total !== null && $total > 0 && $count > 1 && $unit !== null && $unit > 0) {
            return $this->number($total, 2, ' kW AC total').' · '.$count.' × '.$this->number($unit, 2, ' kW');
        }

        return $this->number($total ?? $unit, 2, ' kW AC');
    }

    private function safeDcAcRatio(array $results, mixed $calculation): ?float
    {
        $installedPv = $this->firstNumber([
            $results['installed_pv_kw'] ?? null,
            $results['pv_capacity_kwp'] ?? null,
        ]);
        $totalInverterAc = $this->firstNumber([
            $results['total_inverter_ac_kw'] ?? null,
        ]);
        $unitInverterAc = $this->firstNumber([
            $calculation?->inverter_ac_power_kw ?? null,
            $results['inverter_ac_power_kw'] ?? null,
        ]);
        $count = (int) ($results['inverter_count'] ?? 0);

        if (($totalInverterAc === null || $totalInverterAc <= 0) && $unitInverterAc !== null && $unitInverterAc > 0 && $count > 0) {
            $totalInverterAc = $unitInverterAc * $count;
        }

        if ($installedPv !== null && $installedPv > 0 && $totalInverterAc !== null && $totalInverterAc > 0) {
            return $installedPv / $totalInverterAc;
        }

        return is_numeric($results['dc_ac_ratio'] ?? null) ? (float) $results['dc_ac_ratio'] : null;
    }

    /** @return array<int, array<string, string>> */
    private function reportGateWarnings(Project $project): array
    {
        $warnings = [];
        $calculation = $project->technicalCalculation;
        $results = is_array($calculation?->results ?? null) ? $calculation->results : [];
        $moduleCount = (int) ($results['module_count'] ?? 0);
        $stringCount = (int) ($results['string_count'] ?? 0);
        $modulesPerString = (int) ($results['modules_per_string'] ?? 0);

        if ($moduleCount > 0 && $stringCount > 0 && $modulesPerString > 0 && ($stringCount * $modulesPerString) !== $moduleCount) {
            $warnings[] = CalculationWarning::make(
                'STRING_MODULE_COUNT_MISMATCH',
                'warning',
                'String Design Review Required',
                'The module count in the string design does not match the PV array module count. The report displays an adjusted layout, but the string design still requires review.',
                'Refresh the technical calculation or adjust modules per string so the string layout is consistent with the array module count.',
                'project_report'
            );
        }

        $safeRatio = $this->safeDcAcRatio($results, $calculation);
        $storedRatio = is_numeric($results['dc_ac_ratio'] ?? null) ? (float) $results['dc_ac_ratio'] : null;

        if ($safeRatio !== null && $storedRatio !== null && abs($safeRatio - $storedRatio) > 0.05) {
            $warnings[] = CalculationWarning::make(
                'DC_AC_RATIO_RECALCULATED',
                'caution',
                'DC/AC Ratio Recalculated',
                'The report recalculates the DC/AC ratio from PV DC capacity and total inverter AC capacity to avoid an inconsistent ratio display.',
                'Refresh the technical calculation so the stored value matches the value displayed in the report.',
                'project_report'
            );
        }

        $bom = $project->billOfMaterial;
        $quotation = $project->quotation;
        $bomGrand = is_numeric($bom?->grand_price_idr ?? null) ? (float) $bom->grand_price_idr : 0.0;
        $material = is_numeric($bom?->material_price_subtotal_idr ?? null) ? (float) $bom->material_price_subtotal_idr : 0.0;
        $quotationGrand = is_array($quotation?->commercial_summary ?? null) && is_numeric($quotation->commercial_summary['grand_total_idr'] ?? null)
            ? (float) $quotation->commercial_summary['grand_total_idr']
            : 0.0;

        if ($bom?->pricing_status === 'priced' && $material > 0 && $bomGrand > 0 && $bomGrand < $material) {
            $warnings[] = CalculationWarning::make(
                'BOQ_TOTAL_BELOW_MATERIAL',
                'critical',
                'Commercial Total Below Material Cost',
                'The BOQ grand price is lower than total material cost. This must not be released as a customer proposal without commercial correction.',
                'Review pricing, discount, tax, margin, and the grand price source before creating the final PDF.',
                'project_report'
            );
        }

        if ($bom?->pricing_status === 'priced' && $bomGrand > 0 && $quotationGrand > 0 && abs($quotationGrand - $bomGrand) / max($bomGrand, 1) > 0.01) {
            $warnings[] = CalculationWarning::make(
                'QUOTATION_TOTAL_STALE',
                'warning',
                'Quotation Refresh Required',
                'The stored quotation value differs from the latest BOQ. The report uses the latest BOQ as the primary proposal value source.',
                'Regenerate the quotation after BOQ pricing is updated.',
                'project_report'
            );
        }

        $financial = $project->financialAnalysis;
        $financialCapex = is_numeric($financial?->project_capex_idr ?? null) ? (float) $financial->project_capex_idr : 0.0;

        if ($bom?->pricing_status === 'priced' && $bomGrand > 0 && $financialCapex > 0 && abs($financialCapex - $bomGrand) / max($bomGrand, 1) > 0.01) {
            $warnings[] = CalculationWarning::make(
                'FINANCIAL_CAPEX_STALE',
                'warning',
                'Financial Analysis Refresh Required',
                'The CAPEX value in the financial analysis differs from the latest BOQ. Payback, ROI, NPV, and LCOE should be recalculated after final pricing.',
                'Regenerate financial analysis after BOQ pricing is updated.',
                'project_report'
            );
        }

        return $warnings;
    }

    private function statusMatrix(Project $project, array $warnings, bool $bomPricingReady): array
    {
        $hasRecommendationWarning = $this->hasWarningSource($warnings, 'system_recommendation');
        $hasRoofWarning = $this->hasWarningSource($warnings, 'roof_site_survey');
        $hasBomWarning = $this->hasWarningSource($warnings, 'bom_boq');

        return [
            [
                'label' => 'Technical Calculation',
                'status' => $this->statusLabel($project->technicalCalculation?->result_status_label ?? 'Not available'),
                'tone' => $project->technicalCalculation?->result_status === 'verified' ? 'ok' : 'review',
                'note' => $project->technicalCalculation?->result_status === 'verified' ? 'Formula output has been engineer verified.' : 'The calculation still requires engineer approval.',
            ],
            [
                'label' => 'System Recommendation',
                'status' => $this->statusLabel($project->systemRecommendation?->result_status_label ?? 'Not generated'),
                'tone' => $hasRecommendationWarning ? 'review' : 'ok',
                'note' => $hasRecommendationWarning ? 'Review scoring assumptions before release.' : 'No open recommendation warning.',
            ],
            [
                'label' => 'Roof and Site',
                'status' => $this->statusLabel($project->roofSiteSurvey?->quality_status_label ?? 'Not surveyed'),
                'tone' => $hasRoofWarning ? 'review' : 'ok',
                'note' => $hasRoofWarning ? 'Confirm site dimensions and structural notes.' : 'No roof readiness warning is open.',
            ],
            [
                'label' => 'Pricing BOM/BOQ',
                'status' => $this->statusLabel($project->billOfMaterial?->status_label ?? 'Not generated'),
                'tone' => ($bomPricingReady && ! $hasBomWarning) ? 'ok' : 'review',
                'note' => $bomPricingReady ? 'Priced BOQ is available.' : 'Pricing is still pending or draft.',
            ],
        ];
    }

    private function hasWarningSource(array $warnings, string $source): bool
    {
        foreach ($warnings as $warning) {
            if (($warning['source'] ?? null) === $source) {
                return true;
            }
        }

        return false;
    }

    private function monthlyEnergyStats(array $rows, ?float $annualEnergy): array
    {
        $valid = collect($rows)->filter(fn (array $row): bool => (float) ($row['value'] ?? 0) > 0);

        if ($valid->isEmpty()) {
            return [
                'annual_total' => $this->number($annualEnergy, 0, ' kWh/year'),
                'peak_month' => 'Not available',
                'low_month' => 'Not available',
            ];
        }

        $peak = $valid->sortByDesc('value')->first();
        $low = $valid->sortBy('value')->first();
        $total = $annualEnergy ?? (float) $valid->sum('value');

        return [
            'annual_total' => $this->number($total, 0, ' kWh/year'),
            'peak_month' => ($peak['month'] ?? 'Peak').' · '.$this->number($peak['value'] ?? null, 0, ' kWh'),
            'low_month' => ($low['month'] ?? 'Low').' · '.$this->number($low['value'] ?? null, 0, ' kWh'),
        ];
    }

    private function firstNumber(array $values): ?float
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '—';
        }

        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    private function number(mixed $value, int $decimals = 2, string $suffix = ''): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, $decimals, ',', '.').$suffix;
    }

    private function label(?string $value): string
    {
        if (! filled($value)) {
            return 'Not recorded';
        }

        return Str::of($value)->replace(['_', '-'], ' ')->title()->toString();
    }

    private function statusLabel(?string $value): string
    {
        return match ((string) $value) {
            'Generated Draft', 'Draf Terbentuk' => 'Generated Draft',
            'Draft', 'Draf' => 'Draft',
            'Locked', 'Terkunci' => 'Locked',
            'Priced', 'Sudah Dihargai' => 'Priced',
            'Not Priced', 'Belum Dihargai' => 'Not Priced',
            'Engineer Verified', 'Terverifikasi Engineer' => 'Engineer Verified',
            'Detailed Engineering Calculation', 'Perhitungan Engineering Detail' => 'Detailed Engineering Calculation',
            'Preliminary Engineering Estimate', 'Estimasi Engineering Awal' => 'Preliminary Engineering Estimate',
            'Calculated', 'Terhitung' => 'Calculated',
            'Financial analysis not available', 'Analisis finansial belum tersedia' => 'Financial analysis not available',
            'Calculation not available', 'Perhitungan belum tersedia' => 'Calculation not available',
            'BOM not available', 'BOM belum tersedia' => 'BOM not available',
            default => $value ?: 'Not recorded',
        };
    }

    private function systemLabel(?string $value): string
    {
        return match ($value) {
            'on_grid' => 'On-grid',
            'hybrid' => 'Hybrid',
            'off_grid' => 'Off-grid',
            default => 'Not Available',
        };
    }

    private function coordinates(mixed $latitude, mixed $longitude): string
    {
        if ($latitude === null || $longitude === null) {
            return 'Coordinates not recorded';
        }

        return number_format((float) $latitude, 6, '.', '').', '.number_format((float) $longitude, 6, '.', '');
    }
}
