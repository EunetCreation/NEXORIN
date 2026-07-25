<?php

namespace App\Services\Pricing;

use App\Models\BillOfMaterialItem;
use App\Models\Company;
use App\Models\Material;
use App\Models\PriceBookItem;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PriceResolver
{
    public const MATERIAL_TRACE_VERSION = 'nexorin-price-material-trace-v2';

    /**
     * Resolve the best company price book item for one BOQ line.
     *
     * @return array<string, mixed>
     */
    public function resolve(BillOfMaterialItem $item, Company|int|null $company, array $options = []): array
    {
        $companyId = $company instanceof Company ? $company->getKey() : $company;

        if (! $companyId) {
            return $this->missing('company_unavailable', 'Company context is unavailable for price book resolution.');
        }

        $itemCode = trim((string) $item->item_code);

        if ($itemCode === '') {
            return $this->missing('item_code_missing', 'BOQ item code is unavailable for price book resolution.');
        }

        $today = Carbon::today();
        $markupPercent = $this->boundedPercent($options['material_markup_percent'] ?? 0, 0, 200);
        $project = ($options['project'] ?? null) instanceof Project ? $options['project'] : null;
        $identityCandidates = $this->candidateRows((int) $companyId)
            ->map(function (PriceBookItem $priceItem) use ($item, $today, $project): array {
                $match = $this->matchScore($item, $priceItem);
                $compatibility = $this->projectCompatibility($priceItem->material, $project);

                return [
                    'price_item' => $priceItem,
                    'match_score' => $match['score'],
                    'matched_by' => $match['matched_by'],
                    'quality_score' => $this->qualityScore($priceItem, $today),
                    'compatibility' => $compatibility,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['match_score'] > 0)
            ->values();

        if ($identityCandidates->isEmpty()) {
            return $this->missing(
                'no_company_price_match',
                sprintf('No active company price book item matches BOQ item %s.', $itemCode),
                $this->projectTrace($project)
            );
        }

        $candidates = $identityCandidates
            ->filter(fn (array $candidate): bool => ($candidate['compatibility']['compatible'] ?? true) === true)
            ->values();

        if ($candidates->isEmpty()) {
            return $this->missing(
                'no_project_compatible_price_match',
                sprintf(
                    'Company price items match %s, but none are compatible with the project system and scale.',
                    $itemCode
                ),
                array_merge($this->projectTrace($project), [
                    'rejected_materials' => $identityCandidates
                        ->map(fn (array $candidate): array => [
                            'material_id' => $candidate['price_item']->material_id,
                            'material_sku' => $candidate['price_item']->material?->sku,
                            'reasons' => $candidate['compatibility']['reasons'] ?? [],
                        ])
                        ->all(),
                ])
            );
        }

        $usableCandidates = $candidates
            ->reject(fn (array $candidate): bool => $candidate['price_item']->quote_status === 'obsolete')
            ->values();

        if ($usableCandidates->isEmpty()) {
            return $this->missing(
                'only_obsolete_quotes',
                sprintf('Only obsolete supplier quotes are available for %s. Obsolete quotes are not used by default.', $itemCode)
            );
        }

        $selected = $usableCandidates
            ->sortByDesc(fn (array $candidate): string => sprintf(
                '%05d.%05d.%015d.%010d',
                $candidate['match_score'],
                $candidate['quality_score'],
                $this->dateRank($candidate['price_item']->valid_until),
                999999999 - (int) round((float) $candidate['price_item']->effective_selling_price)
            ))
            ->first();

        /** @var PriceBookItem $priceItem */
        $priceItem = $selected['price_item'];
        $unitCost = $this->money($priceItem->unit_cost);

        if ($unitCost <= 0) {
            return $this->missing(
                'company_price_missing_cost',
                sprintf('Matched price book item for %s has no valid unit cost.', $itemCode),
                $this->trace($priceItem, $selected, 'missing_cost')
            );
        }

        $unitPrice = $this->resolveSellingPrice($priceItem, $markupPercent);
        $state = 'priced';
        $warnings = [];
        $catalogReviewRequired = $priceItem->material?->catalog_status !== 'approved'
            || $priceItem->material?->compliance_status !== 'compliant';
        $catalogReviewMessage = $catalogReviewRequired
            ? sprintf(
                'Catalog material %s is selected for project-compatible pricing, but its catalog governance status still requires review.',
                $priceItem->material?->sku ?? $itemCode
            )
            : null;

        $priceBookExpired = $priceItem->priceBook?->effective_until !== null
            && $priceItem->priceBook->effective_until->isBefore($today);
        $quoteExpired = $priceItem->is_expired;

        if ($priceBookExpired || $quoteExpired) {
            $state = 'expired_price_warning';
            $warnings[] = sprintf(
                'Expired supplier price is used for %s. Refresh the quote before client release.',
                $item->item_name ?: $itemCode
            );
        }

        if (in_array($priceItem->availability_status, ['unavailable', 'discontinued'], true)) {
            $state = 'supplier_unavailable_warning';
            $warnings[] = sprintf(
                'Supplier item for %s is marked %s. Confirm availability before client release.',
                $item->item_name ?: $itemCode,
                $priceItem->availability_label
            );
        }

        return [
            'resolved' => true,
            'state' => $state,
            'source' => 'company_price_book',
            'unit_cost_idr' => $unitCost,
            'unit_price_idr' => $unitPrice,
            'margin_percent' => $priceItem->effective_margin_percent !== null
                ? round((float) $priceItem->effective_margin_percent, 2)
                : null,
            'warnings' => $warnings,
            'catalog_review_required' => $catalogReviewRequired,
            'catalog_review_message' => $catalogReviewMessage,
            'trace' => array_merge(
                $this->trace($priceItem, $selected, $state),
                [
                    'catalog_review_required' => $catalogReviewRequired,
                    'catalog_review_message' => $catalogReviewMessage,
                ]
            ),
        ];
    }

    /** @return Collection<int, PriceBookItem> */
    private function candidateRows(int $companyId): Collection
    {
        return PriceBookItem::query()
            ->with([
                'priceBook',
                'material.category',
                'material.datasheets' => fn ($query) => $query->where('is_active', true),
                'supplier',
            ])
            ->whereHas('priceBook', function ($query) use ($companyId): void {
                $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active');
            })
            ->whereHas('material', function ($query) use ($companyId): void {
                $query
                    ->where('is_active', true)
                    ->where(function ($scope) use ($companyId): void {
                        $scope
                            ->whereNull('company_id')
                            ->orWhere('company_id', $companyId);
                    });
            })
            ->get();
    }

    /** @return array{score:int,matched_by:string} */
    private function matchScore(BillOfMaterialItem $item, PriceBookItem $priceItem): array
    {
        $material = $priceItem->material;

        if (! $material) {
            return ['score' => 0, 'matched_by' => 'none'];
        }

        $itemCode = $this->normalizeKey((string) $item->item_code);
        $materialSku = $this->normalizeKey((string) $material->sku);
        $metadata = $item->metadata ?? [];
        $metadataKeys = collect([
            $metadata['material_sku'] ?? null,
            $metadata['catalog_sku'] ?? null,
            $metadata['price_resolver_sku'] ?? null,
            $metadata['supplier_sku'] ?? null,
        ])
            ->filter(fn ($value): bool => $value !== null && trim((string) $value) !== '')
            ->map(fn ($value): string => $this->normalizeKey((string) $value))
            ->values();

        if ($itemCode !== '' && $itemCode === $materialSku) {
            return ['score' => 100, 'matched_by' => 'item_code'];
        }

        if ($metadataKeys->contains($materialSku)) {
            return ['score' => 98, 'matched_by' => 'metadata_sku'];
        }

        $aliases = collect($this->aliasesFor((string) $item->item_code))
            ->map(fn (string $alias): string => $this->normalizeKey($alias));

        if ($aliases->contains($materialSku)) {
            return ['score' => 92, 'matched_by' => 'resolver_alias'];
        }

        $supplierSku = $this->normalizeKey((string) $priceItem->supplier_sku);
        if ($itemCode !== '' && $supplierSku !== '' && $itemCode === $supplierSku) {
            return ['score' => 88, 'matched_by' => 'supplier_sku'];
        }

        $itemName = $this->normalizeText((string) $item->item_name);
        $materialName = $this->normalizeText((string) $material->name);

        if ($itemName !== '' && $materialName !== '' && ($itemName === $materialName || str_contains($materialName, $itemName) || str_contains($itemName, $materialName))) {
            return ['score' => 70, 'matched_by' => 'material_name'];
        }

        $model = $this->normalizeText((string) $material->model);
        if ($model !== '' && $itemName !== '' && str_contains($itemName, $model)) {
            return ['score' => 60, 'matched_by' => 'material_model'];
        }

        return ['score' => 0, 'matched_by' => 'none'];
    }

    private function qualityScore(PriceBookItem $priceItem, Carbon $today): int
    {
        $score = 0;

        if ($priceItem->unit_cost !== null && (float) $priceItem->unit_cost > 0) {
            $score += 30;
        }

        $score += match ($priceItem->availability_status) {
            'available' => 30,
            'limited' => 25,
            'backorder' => 15,
            'unavailable' => 5,
            'discontinued' => 1,
            default => 10,
        };

        if ($priceItem->quote_status === 'active') {
            $score += 25;
        }

        if ($priceItem->valid_until === null || ! $priceItem->valid_until->isBefore($today)) {
            $score += 20;
        }

        if ($priceItem->priceBook?->effective_until === null || ! $priceItem->priceBook->effective_until->isBefore($today)) {
            $score += 10;
        }

        if ($priceItem->supplier?->status === 'active') {
            $score += 5;
        }

        if ($priceItem->has_selling_reference) {
            $score += 5;
        }

        $score += match ($priceItem->material?->catalog_status) {
            'approved' => 20,
            'needs_review' => 6,
            default => 0,
        };

        if ($priceItem->material?->compliance_status === 'compliant') {
            $score += 10;
        }

        return $score;
    }

    /** @return array{compatible:bool,status:string,reasons:array<int,string>,project_system:?string,project_scale:?string,project_supply_phase:?string,project_roof_types:array<int,string>} */
    private function projectCompatibility(?Material $material, ?Project $project): array
    {
        if (! $material || ! $project) {
            return [
                'compatible' => true,
                'status' => 'not_evaluated',
                'reasons' => [],
                'project_system' => $project?->proposed_system,
                'project_scale' => $project?->building_type,
                'project_supply_phase' => null,
                'project_roof_types' => [],
            ];
        }

        $project->loadMissing([
            'loadAnalysis:id,project_id,supply_phase',
            'roofSurfaces:id,project_id,roof_type',
        ]);

        $reasons = [];
        $projectSystem = trim((string) $project->proposed_system);
        $projectScale = trim((string) $project->building_type);
        $projectSupplyPhase = trim((string) ($project->loadAnalysis?->supply_phase ?? ''));
        $projectRoofTypes = $project->roofSurfaces
            ->pluck('roof_type')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        $systemTypes = collect($material->system_types ?? [])->map(fn ($value): string => trim((string) $value))->filter();
        $projectScales = collect($material->project_scales ?? [])->map(fn ($value): string => trim((string) $value))->filter();
        $materialRoofTypes = collect($material->suitable_roof_types ?? [])->map(fn ($value): string => trim((string) $value))->filter();
        $materialPhase = trim((string) ($material->electrical_phase ?? ''));

        if ($projectSystem !== '' && $projectSystem !== 'undecided' && $systemTypes->isNotEmpty() && ! $systemTypes->contains($projectSystem)) {
            $reasons[] = sprintf('Material is not approved for %s systems.', str($projectSystem)->replace('_', ' ')->title());
        }

        if ($projectScale !== '' && $projectScales->isNotEmpty() && ! $projectScales->contains($projectScale)) {
            $reasons[] = sprintf('Material is not approved for %s project scale.', str($projectScale)->replace('_', ' ')->title());
        }

        if (in_array($materialPhase, ['single_phase', 'three_phase'], true)
            && $projectSupplyPhase !== ''
            && $materialPhase !== $projectSupplyPhase) {
            $reasons[] = sprintf(
                'Material electrical phase %s does not match the project supply phase %s.',
                str($materialPhase)->replace('_', ' ')->title(),
                str($projectSupplyPhase)->replace('_', ' ')->title()
            );
        }

        if ($materialRoofTypes->isNotEmpty()
            && $projectRoofTypes->isNotEmpty()
            && $materialRoofTypes->intersect($projectRoofTypes)->isEmpty()) {
            $reasons[] = sprintf(
                'Material roof compatibility (%s) does not match the project roof survey (%s).',
                $materialRoofTypes->map(fn (string $value): string => str($value)->replace('_', ' ')->title()->toString())->implode(', '),
                $projectRoofTypes->map(fn (string $value): string => str($value)->replace('_', ' ')->title()->toString())->implode(', ')
            );
        }

        return [
            'compatible' => $reasons === [],
            'status' => $reasons === [] ? 'compatible' : 'incompatible',
            'reasons' => $reasons,
            'project_system' => $projectSystem !== '' ? $projectSystem : null,
            'project_scale' => $projectScale !== '' ? $projectScale : null,
            'project_supply_phase' => $projectSupplyPhase !== '' ? $projectSupplyPhase : null,
            'project_roof_types' => $projectRoofTypes->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function projectTrace(?Project $project): array
    {
        if ($project) {
            $project->loadMissing([
                'loadAnalysis:id,project_id,supply_phase',
                'roofSurfaces:id,project_id,roof_type',
            ]);
        }

        return [
            'project_id' => $project?->getKey(),
            'project_number' => $project?->project_number,
            'project_system' => $project?->proposed_system,
            'project_scale' => $project?->building_type,
            'project_supply_phase' => $project?->loadAnalysis?->supply_phase,
            'project_roof_types' => $project?->roofSurfaces?->pluck('roof_type')->filter()->unique()->values()->all() ?? [],
        ];
    }

    private function resolveSellingPrice(PriceBookItem $priceItem, float $markupPercent): float
    {
        if ($priceItem->selling_price !== null) {
            return $this->money($priceItem->selling_price);
        }

        if ($priceItem->margin_percent !== null) {
            return $this->money((float) $priceItem->unit_cost * (1 + ((float) $priceItem->margin_percent / 100)));
        }

        return $this->money((float) $priceItem->unit_cost * (1 + ($markupPercent / 100)));
    }

    /** @return array<string, mixed> */
    private function trace(PriceBookItem $priceItem, array $selected, string $state): array
    {
        $material = $priceItem->material;
        $datasheet = $material?->datasheets?->first();

        return [
            'material_trace_version' => self::MATERIAL_TRACE_VERSION,
            'resolver' => 'company_price_book',
            'state' => $state,
            'price_book_item_id' => $priceItem->getKey(),
            'price_book_id' => $priceItem->price_book_id,
            'price_book_code' => $priceItem->priceBook?->code,
            'price_book_name' => $priceItem->priceBook?->name,
            'price_book_effective_from' => $priceItem->priceBook?->effective_from?->toDateString(),
            'price_book_effective_until' => $priceItem->priceBook?->effective_until?->toDateString(),
            'material_id' => $priceItem->material_id,
            'material_sku' => $material?->sku,
            'material_name' => $material?->name,
            'material_brand' => $material?->brand,
            'material_model' => $material?->model,
            'material_category_code' => $material?->category?->code,
            'material_catalog_status' => $material?->catalog_status,
            'material_compliance_status' => $material?->compliance_status,
            'material_system_types' => $material?->system_types ?? [],
            'material_project_scales' => $material?->project_scales ?? [],
            'material_suitable_roof_types' => $material?->suitable_roof_types ?? [],
            'material_electrical_phase' => $material?->electrical_phase,
            'material_datasheet_id' => $datasheet?->getKey(),
            'material_datasheet_revision' => $datasheet?->revision,
            'material_datasheet_sha256' => $datasheet?->sha256,
            'project_compatibility' => $selected['compatibility'] ?? null,
            'supplier_id' => $priceItem->supplier_id,
            'supplier_name' => $priceItem->supplier?->name,
            'supplier_status' => $priceItem->supplier?->status,
            'supplier_sku' => $priceItem->supplier_sku,
            'availability_status' => $priceItem->availability_status,
            'quote_status' => $priceItem->quote_status,
            'derived_quote_status' => $priceItem->derived_quote_status,
            'quoted_at' => $priceItem->quoted_at?->toDateString(),
            'valid_until' => $priceItem->valid_until?->toDateString(),
            'lead_time_days' => $priceItem->lead_time_days,
            'minimum_order_quantity' => $priceItem->minimum_order_quantity !== null ? (float) $priceItem->minimum_order_quantity : null,
            'matched_by' => $selected['matched_by'] ?? 'unknown',
            'match_score' => $selected['match_score'] ?? 0,
            'quality_score' => $selected['quality_score'] ?? 0,
            'resolved_at' => now()->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function missing(string $reason, string $message, array $trace = []): array
    {
        return [
            'resolved' => false,
            'state' => 'missing_price',
            'source' => 'company_price_book',
            'reason' => $reason,
            'message' => $message,
            'warnings' => [],
            'trace' => array_merge([
                'resolver' => 'company_price_book',
                'state' => 'missing_price',
                'reason' => $reason,
                'resolved_at' => now()->toISOString(),
            ], $trace),
        ];
    }

    /** @return array<int, string> */
    private function aliasesFor(string $itemCode): array
    {
        $aliases = [
            'PV-MODULE' => ['PV-MOD-550-MONO'],
            'INV-HYBRID' => ['INV-HYB-5KW-1P'],
            'INV-GRID-HYBRID' => ['INV-HYB-5KW-1P'],
            'BATTERY-PACK' => ['BAT-LFP-5KWH'],
            'DC-CABLE-PV1F' => ['CABLE-PV1F-4MM'],
            'DC-SPD' => ['SPD-DC-T2-1000V'],
            'FIRE-PPE-ALLOWANCE' => ['FIRE-EXT-BASIC-SET'],
        ];

        return $aliases[strtoupper(trim($itemCode))] ?? [];
    }

    private function dateRank(mixed $date): int
    {
        if (! $date instanceof Carbon) {
            return 0;
        }

        return max(0, (int) $date->timestamp);
    }

    private function normalizeKey(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value))) ?: '';
    }

    private function normalizeText(string $value): string
    {
        return str($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function money(mixed $value): float
    {
        return max(0.0, round((float) $value, 2));
    }

    private function boundedPercent(mixed $value, float $min, float $max): float
    {
        return min($max, max($min, round((float) $value, 2)));
    }
}
