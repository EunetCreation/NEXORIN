<?php

namespace App\Services\Quotation;

use App\Models\BillOfMaterial;
use App\Models\FinancialAnalysis;
use App\Models\Project;

class QuotationEngine
{
    /**
     * Build a customer-facing quotation payload from BOQ and financial analysis.
     *
     * @return array{summary: array<string, mixed>, items: array<int, array<string, mixed>>, customer_snapshot: array<string, mixed>, project_snapshot: array<string, mixed>, technical_summary: array<string, mixed>, financial_summary: array<string, mixed>, commercial_summary: array<string, mixed>, terms: array<string, mixed>, warnings: array<int, string>, source_snapshot: array<string, mixed>, client_snapshot: array<string, mixed>, internal_snapshot: array<string, mixed>, snapshot_hash: string, source_fingerprint: string, parameters: array<string, mixed>}
     */
    public function generate(
        Project $project,
        BillOfMaterial $bom,
        FinancialAnalysis $analysis,
        array $parameters = []
    ): array {
        $normalized = $this->normalizeParameters($parameters);
        $warnings = [];
        $technicalResults = $project->technicalCalculation?->results ?? [];
        $bomSummary = $bom->summary ?? [];

        if ($bom->pricing_status !== 'priced') {
            $warnings[] = 'BOQ pricing is not marked as priced. Resolve commercial review gates before issuing a quotation.';
        }

        if (in_array($bom->pricing_status, ['margin_review_required', 'discount_review_required', 'tax_review_required', 'commercial_total_mismatch'], true)) {
            $warnings[] = 'BOQ commercial pricing requires margin, discount, tax, or total consistency review.';
        }

        if ((float) $bom->grand_price_idr <= 0) {
            $warnings[] = 'BOQ grand price is zero. Generate BOQ pricing before issuing a quotation.';
        }

        if ($analysis->status !== 'calculated') {
            $warnings[] = 'Financial analysis is not marked as calculated. Financial summary may be incomplete.';
        }

        if ($project->technicalCalculation && $project->technicalCalculation->result_status !== 'verified') {
            $warnings[] = 'Technical calculation is not engineer verified. Quotation should remain a draft.';
        }

        $customer = $project->customer;
        $grandTotal = $this->money($bom->grand_price_idr ?: ($analysis->project_capex_idr ?? 0));
        $itemRows = $this->customerItems($bom, $normalized['include_item_detail']);
        $serviceRows = $this->serviceRows($bom);
        $items = array_merge($itemRows, $serviceRows);

        $lineTotal = array_sum(array_column($items, 'line_price_idr'));
        $roundingAdjustment = round($grandTotal - $lineTotal, 2);

        if (abs($roundingAdjustment) >= 1) {
            $items[] = [
                'category' => 'commercial_adjustment',
                'item_code' => 'ROUNDING-ADJ',
                'item_name' => 'Commercial adjustment',
                'description' => 'Adjustment to match approved BOQ selling total.',
                'quantity' => 1.0,
                'unit' => 'lot',
                'unit_price_idr' => $roundingAdjustment,
                'line_price_idr' => $roundingAdjustment,
                'source_key' => 'commercial_adjustment',
                'metadata' => ['type' => 'rounding_or_commercial_adjustment', 'customer_safe' => true],
            ];
        }

        $technicalSummary = [
            'system_type' => $project->technicalCalculation?->system_type ?? ($bomSummary['system_type'] ?? null),
            'installed_pv_kw' => $this->number($technicalResults['installed_pv_kw'] ?? $bomSummary['installed_pv_kw'] ?? 0, 3),
            'annual_energy_kwh' => $this->number($analysis->annual_energy_kwh ?: ($technicalResults['annual_energy_kwh'] ?? 0), 2),
            'module_count' => (int) ($technicalResults['module_count'] ?? $bomSummary['module_count'] ?? 0),
            'inverter_count' => (int) ($technicalResults['inverter_count'] ?? $bomSummary['inverter_count'] ?? 0),
            'battery_nominal_kwh' => $this->number($technicalResults['battery_nominal_kwh'] ?? $bomSummary['battery_nominal_kwh'] ?? 0, 2),
            'result_status' => $project->technicalCalculation?->result_status,
        ];

        $financialSummary = [
            'project_capex_idr' => $this->money($analysis->project_capex_idr ?: $grandTotal),
            'first_year_saving_idr' => $this->money($analysis->first_year_saving_idr),
            'simple_payback_years' => $analysis->simple_payback_years === null ? null : $this->number($analysis->simple_payback_years, 2),
            'lifetime_roi_percent' => $this->number($analysis->lifetime_roi_percent, 2),
            'npv_idr' => $this->money($analysis->npv_idr),
            'irr_percent' => $analysis->irr_percent === null ? null : $this->number($analysis->irr_percent, 2),
            'lcoe_idr_per_kwh' => $analysis->lcoe_idr_per_kwh === null ? null : $this->number($analysis->lcoe_idr_per_kwh, 2),
        ];

        $commercialSummary = [
            'currency' => 'IDR',
            'subtotal_idr' => $this->money($bomSummary['commercial_subtotal_idr'] ?? ($bom->material_price_subtotal_idr + $bom->labor_cost_idr + $bom->logistics_cost_idr + $bom->other_cost_idr + $bom->contingency_cost_idr)),
            'material_subtotal_idr' => $this->money($bom->material_price_subtotal_idr),
            'service_subtotal_idr' => $this->money($bomSummary['service_subtotal_idr'] ?? ($bom->labor_cost_idr + $bom->logistics_cost_idr + $bom->other_cost_idr)),
            'contingency_idr' => $this->money($bom->contingency_cost_idr),
            'tax_mode' => (string) ($bomSummary['tax_mode'] ?? 'exclusive'),
            'tax_percent' => $this->number($bomSummary['tax_percent'] ?? 0, 2),
            'tax_idr' => $this->money($bom->tax_idr),
            'tax_note' => ((string) ($bomSummary['tax_mode'] ?? 'exclusive')) === 'inclusive'
                ? 'Tax is included in the displayed selling total.'
                : 'Tax is added to the subtotal.',
            'item_discount_idr' => $this->money($bomSummary['item_discount_idr'] ?? 0),
            'global_discount_idr' => $this->money($bomSummary['global_discount_idr'] ?? 0),
            'discount_idr' => $this->money($bom->discount_idr),
            'rounding_adjustment_idr' => $this->money($bomSummary['rounding_adjustment_idr'] ?? 0),
            'grand_total_idr' => $grandTotal,
            'valid_until' => now()->addDays($normalized['validity_days'])->toDateString(),
        ];

        $terms = [
            'payment_terms' => $normalized['payment_terms'],
            'delivery_terms' => $normalized['delivery_terms'],
            'warranty_terms' => $normalized['warranty_terms'],
            'exclusions' => $normalized['exclusions'],
            'quotation_note' => $normalized['quotation_note'],
        ];

        $summary = [
            'status' => 'draft',
            'currency' => 'IDR',
            'title' => $normalized['quotation_title'],
            'grand_total_idr' => $grandTotal,
            'validity_days' => $normalized['validity_days'],
            'valid_until' => $commercialSummary['valid_until'],
            'generated_at' => now()->toISOString(),
        ];

        $customerSnapshot = [
            'name' => $customer?->name,
            'customer_number' => $customer?->customer_number,
            'email' => $customer?->email,
            'phone' => $customer?->formatted_phone ?? $customer?->phone,
            'address' => $customer?->address,
            'city' => $customer?->city,
            'province' => $customer?->province,
        ];

        $projectSnapshot = [
            'project_number' => $project->project_number,
            'name' => $project->name,
            'building_type' => $project->building_type,
            'proposed_system' => $project->proposed_system,
            'site_address' => $project->site_address,
            'city' => $project->city,
            'province' => $project->province,
        ];

        $sourceFingerprint = $this->sourceFingerprint($project, $bom, $analysis);
        $sourceSnapshot = [
            'project_id' => $project->getKey(),
            'technical_calculation_id' => $project->technicalCalculation?->getKey(),
            'bill_of_material_id' => $bom->getKey(),
            'financial_analysis_id' => $analysis->getKey(),
            'source_fingerprint' => $sourceFingerprint,
            'bom_pricing_status' => $bom->pricing_status,
            'bom_commercial_total_consistent' => (bool) ($bomSummary['commercial_total_consistent'] ?? true),
            'bom_low_margin_item_count' => (int) ($bomSummary['low_margin_item_count'] ?? 0),
            'bom_discount_limit_exceeded_count' => (int) ($bomSummary['discount_limit_exceeded_count'] ?? 0),
            'financial_analysis_status' => $analysis->status,
            'bom_priced_at' => optional($bom->priced_at)->toISOString(),
            'bom_updated_at' => optional($bom->updated_at)->toISOString(),
            'financial_calculated_at' => optional($analysis->calculated_at)->toISOString(),
            'financial_updated_at' => optional($analysis->updated_at)->toISOString(),
        ];

        $clientSnapshot = $this->clientSnapshot($summary, $items, $customerSnapshot, $projectSnapshot, $technicalSummary, $financialSummary, $commercialSummary, $terms, $warnings);
        $internalSnapshot = $this->internalSnapshot($project, $bom, $analysis, $items, $summary, $commercialSummary, $sourceSnapshot, $normalized, $warnings);
        $snapshotHash = $this->stableHash([
            'client_snapshot' => $clientSnapshot,
            'internal_snapshot' => $internalSnapshot,
        ]);

        return [
            'summary' => $summary,
            'items' => array_values($items),
            'customer_snapshot' => $customerSnapshot,
            'project_snapshot' => $projectSnapshot,
            'technical_summary' => $technicalSummary,
            'financial_summary' => $financialSummary,
            'commercial_summary' => $commercialSummary,
            'terms' => $terms,
            'warnings' => array_values(array_unique($warnings)),
            'source_snapshot' => $sourceSnapshot,
            'client_snapshot' => $clientSnapshot,
            'internal_snapshot' => $internalSnapshot,
            'snapshot_hash' => $snapshotHash,
            'source_fingerprint' => $sourceFingerprint,
            'parameters' => $normalized,
        ];
    }

    /** @return array<string, mixed> */
    public function defaultParameters(): array
    {
        return [
            'quotation_title' => 'Solar PV System Proposal',
            'validity_days' => 14,
            'include_item_detail' => true,
            'payment_terms' => '50% down payment and 50% before commissioning.',
            'delivery_terms' => 'Installation schedule is subject to product availability, site readiness, and final survey verification.',
            'warranty_terms' => 'Product warranty follows manufacturer terms. Workmanship warranty follows company policy.',
            'exclusions' => 'Utility approval, structural reinforcement, civil work outside the listed scope, and permit fees are excluded unless stated otherwise.',
            'quotation_note' => null,
        ];
    }

    public function sourceFingerprint(Project $project, BillOfMaterial $bom, FinancialAnalysis $analysis): string
    {
        $bom->loadMissing('items');

        return $this->stableHash([
            'project' => [
                'id' => $project->getKey(),
                'project_number' => $project->project_number,
                'name' => $project->name,
                'updated_at' => optional($project->updated_at)->toISOString(),
            ],
            'technical_calculation' => [
                'id' => $project->technicalCalculation?->getKey(),
                'status' => $project->technicalCalculation?->result_status,
                'updated_at' => optional($project->technicalCalculation?->updated_at)->toISOString(),
            ],
            'bill_of_material' => [
                'id' => $bom->getKey(),
                'pricing_status' => $bom->pricing_status,
                'summary' => $bom->summary,
                'material_price_subtotal_idr' => $this->money($bom->material_price_subtotal_idr),
                'labor_cost_idr' => $this->money($bom->labor_cost_idr),
                'logistics_cost_idr' => $this->money($bom->logistics_cost_idr),
                'other_cost_idr' => $this->money($bom->other_cost_idr),
                'contingency_cost_idr' => $this->money($bom->contingency_cost_idr),
                'tax_idr' => $this->money($bom->tax_idr),
                'discount_idr' => $this->money($bom->discount_idr),
                'grand_price_idr' => $this->money($bom->grand_price_idr),
                'priced_at' => optional($bom->priced_at)->toISOString(),
                'updated_at' => optional($bom->updated_at)->toISOString(),
                'items' => $bom->items->map(fn ($item): array => [
                    'id' => $item->getKey(),
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'quantity' => $this->number($item->quantity, 3),
                    'unit' => $item->unit,
                    'unit_price_idr' => $this->money($item->unit_price_idr),
                    'line_price_idr' => $this->money($item->line_price_idr),
                    'updated_at' => optional($item->updated_at)->toISOString(),
                ])->values()->all(),
            ],
            'financial_analysis' => [
                'id' => $analysis->getKey(),
                'status' => $analysis->status,
                'project_capex_idr' => $this->money($analysis->project_capex_idr),
                'updated_at' => optional($analysis->updated_at)->toISOString(),
                'calculated_at' => optional($analysis->calculated_at)->toISOString(),
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function customerItems(BillOfMaterial $bom, bool $includeItemDetail): array
    {
        $bom->loadMissing('items');

        if (! $includeItemDetail) {
            return [[
                'category' => 'solar_pv_system',
                'item_code' => 'PV-SYSTEM-PACKAGE',
                'item_name' => 'Solar PV system package',
                'description' => 'PV modules, inverter, mounting, cables, protection, accessories, and installation package based on the approved BOQ.',
                'quantity' => 1.0,
                'unit' => 'lot',
                'unit_price_idr' => $this->money($bom->material_price_subtotal_idr),
                'line_price_idr' => $this->money($bom->material_price_subtotal_idr),
                'source_key' => 'boq_package',
                'metadata' => ['source' => 'boq_summary', 'customer_safe' => true],
            ]];
        }

        $rows = [];

        foreach ($bom->items as $item) {
            $metadata = $item->metadata ?? [];

            if (($metadata['include_in_boq'] ?? true) === false) {
                continue;
            }

            if ($this->money($item->line_price_idr) <= 0) {
                continue;
            }

            $rows[] = [
                'category' => (string) $item->category,
                'item_code' => (string) $item->item_code,
                'item_name' => (string) $item->item_name,
                'description' => (string) ($item->description ?: $item->item_name),
                'quantity' => $this->number($item->quantity, 3),
                'unit' => (string) $item->unit,
                'unit_price_idr' => $this->money($item->unit_price_idr),
                'line_price_idr' => $this->money($item->line_price_idr),
                'source_key' => (string) $item->source_key,
                'metadata' => [
                    'source' => 'bill_of_material_item',
                    'bill_of_material_item_id' => $item->getKey(),
                    'tax_exempt' => (bool) (($metadata['commercial_breakdown']['tax_exempt'] ?? false) === true),
                    'customer_safe' => true,
                ],
            ];
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function serviceRows(BillOfMaterial $bom): array
    {
        $rows = [];

        $summary = $bom->summary ?? [];
        $taxMode = (string) ($summary['tax_mode'] ?? 'exclusive');
        $serviceMap = [
            ['labor', 'INSTALL-LABOR', 'Installation labor and commissioning', $bom->labor_cost_idr],
            ['logistics', 'LOGISTICS', 'Logistics and site handling', $bom->logistics_cost_idr],
            ['other', 'OTHER-SCOPE', 'Other project scope', $bom->other_cost_idr],
            ['contingency', 'CONTINGENCY', 'Contingency allowance', $bom->contingency_cost_idr],
        ];

        if ($taxMode !== 'inclusive') {
            $serviceMap[] = ['tax', 'TAX', 'Tax', $bom->tax_idr];
        }

        foreach ($serviceMap as [$category, $code, $name, $amount]) {
            $amount = $this->money($amount);

            if ($amount <= 0) {
                continue;
            }

            $rows[] = [
                'category' => $category,
                'item_code' => $code,
                'item_name' => $name,
                'description' => $name,
                'quantity' => 1.0,
                'unit' => 'lot',
                'unit_price_idr' => $amount,
                'line_price_idr' => $amount,
                'source_key' => $category,
                'metadata' => ['source' => 'boq_pricing_summary', 'customer_safe' => true],
            ];
        }

        $discount = $this->money($bom->discount_idr);

        if ($discount > 0) {
            $rows[] = [
                'category' => 'discount',
                'item_code' => 'DISCOUNT',
                'item_name' => 'Commercial discount',
                'description' => 'Approved commercial discount.',
                'quantity' => 1.0,
                'unit' => 'lot',
                'unit_price_idr' => -$discount,
                'line_price_idr' => -$discount,
                'source_key' => 'discount',
                'metadata' => ['source' => 'boq_pricing_summary', 'customer_safe' => true],
            ];
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $items @param array<int, string> $warnings @return array<string, mixed> */
    private function clientSnapshot(array $summary, array $items, array $customerSnapshot, array $projectSnapshot, array $technicalSummary, array $financialSummary, array $commercialSummary, array $terms, array $warnings): array
    {
        return [
            'summary' => $summary,
            'customer' => $customerSnapshot,
            'project' => $projectSnapshot,
            'technical_summary' => $technicalSummary,
            'financial_summary' => $financialSummary,
            'commercial_summary' => [
                'currency' => $commercialSummary['currency'],
                'subtotal_idr' => $commercialSummary['subtotal_idr'],
                'tax_mode' => $commercialSummary['tax_mode'],
                'tax_percent' => $commercialSummary['tax_percent'],
                'tax_idr' => $commercialSummary['tax_idr'],
                'tax_note' => $commercialSummary['tax_note'],
                'discount_idr' => $commercialSummary['discount_idr'],
                'rounding_adjustment_idr' => $commercialSummary['rounding_adjustment_idr'],
                'grand_total_idr' => $commercialSummary['grand_total_idr'],
                'valid_until' => $commercialSummary['valid_until'],
            ],
            'items' => array_map(fn (array $item): array => [
                'category' => $item['category'],
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price_idr' => $item['unit_price_idr'],
                'line_price_idr' => $item['line_price_idr'],
            ], $items),
            'terms' => $terms,
            'warnings' => array_values(array_unique($warnings)),
            'privacy' => [
                'cost_hidden' => true,
                'margin_hidden' => true,
                'supplier_trace_hidden' => true,
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $items @param array<int, string> $warnings @return array<string, mixed> */
    private function internalSnapshot(Project $project, BillOfMaterial $bom, FinancialAnalysis $analysis, array $items, array $summary, array $commercialSummary, array $sourceSnapshot, array $parameters, array $warnings): array
    {
        $bom->loadMissing('items');

        return [
            'summary' => $summary,
            'commercial_summary' => $commercialSummary,
            'source_snapshot' => $sourceSnapshot,
            'parameters' => $parameters,
            'warnings' => array_values(array_unique($warnings)),
            'audit' => [
                'project_id' => $project->getKey(),
                'bill_of_material_id' => $bom->getKey(),
                'financial_analysis_id' => $analysis->getKey(),
                'created_at' => now()->toISOString(),
            ],
            'boq_internal_totals' => [
                'material_cost_subtotal_idr' => $this->money($bom->material_cost_subtotal_idr),
                'material_price_subtotal_idr' => $this->money($bom->material_price_subtotal_idr),
                'labor_cost_idr' => $this->money($bom->labor_cost_idr),
                'logistics_cost_idr' => $this->money($bom->logistics_cost_idr),
                'other_cost_idr' => $this->money($bom->other_cost_idr),
                'contingency_cost_idr' => $this->money($bom->contingency_cost_idr),
                'tax_idr' => $this->money($bom->tax_idr),
                'discount_idr' => $this->money($bom->discount_idr),
                'grand_cost_idr' => $this->money($bom->grand_cost_idr),
                'grand_price_idr' => $this->money($bom->grand_price_idr),
                'gross_margin_idr' => $this->money($bom->gross_margin_idr),
                'gross_margin_percent' => $this->number($bom->gross_margin_percent, 2),
            ],
            'boq_items' => $bom->items->map(fn ($item): array => [
                'id' => $item->getKey(),
                'category' => $item->category,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'quantity' => $this->number($item->quantity, 3),
                'unit' => $item->unit,
                'unit_cost_idr' => $this->money($item->unit_cost_idr),
                'unit_price_idr' => $this->money($item->unit_price_idr),
                'line_cost_idr' => $this->money($item->line_cost_idr),
                'line_price_idr' => $this->money($item->line_price_idr),
                'source_key' => $item->source_key,
                'metadata' => $item->metadata,
            ])->values()->all(),
            'quotation_items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeParameters(array $parameters): array
    {
        $defaults = $this->defaultParameters();
        $validityDays = (int) ($parameters['validity_days'] ?? $defaults['validity_days']);

        return [
            'quotation_title' => $this->text($parameters['quotation_title'] ?? null, $defaults['quotation_title'], 180),
            'validity_days' => min(90, max(1, $validityDays)),
            'include_item_detail' => (bool) ($parameters['include_item_detail'] ?? $defaults['include_item_detail']),
            'payment_terms' => $this->text($parameters['payment_terms'] ?? null, $defaults['payment_terms'], 1000),
            'delivery_terms' => $this->text($parameters['delivery_terms'] ?? null, $defaults['delivery_terms'], 1000),
            'warranty_terms' => $this->text($parameters['warranty_terms'] ?? null, $defaults['warranty_terms'], 1000),
            'exclusions' => $this->text($parameters['exclusions'] ?? null, $defaults['exclusions'], 1000),
            'quotation_note' => isset($parameters['quotation_note']) && trim((string) $parameters['quotation_note']) !== ''
                ? substr(trim((string) $parameters['quotation_note']), 0, 2000)
                : null,
        ];
    }

    private function text(mixed $value, string $default, int $limit): string
    {
        $text = trim((string) ($value ?: $default));

        return substr($text !== '' ? $text : $default, 0, $limit);
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function number(mixed $value, int $decimals): float
    {
        return round((float) $value, $decimals);
    }

    private function stableHash(array $payload): string
    {
        return hash('sha256', json_encode($this->sortRecursive($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);

        if ($isList) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        return $value;
    }
}
