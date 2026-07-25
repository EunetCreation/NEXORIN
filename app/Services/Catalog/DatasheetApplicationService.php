<?php

namespace App\Services\Catalog;

use App\Models\Material;
use App\Models\MaterialDatasheet;
use App\Models\MaterialDatasheetApplication;
use App\Models\MaterialSpec;
use App\Models\User;
use App\Services\Catalog\Datasheet\DatasheetUnitNormalizer;
use App\Support\ProfessionalSpecificationSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DatasheetApplicationService
{
    public function __construct(private readonly DatasheetUnitNormalizer $normalizer)
    {
    }

    /**
     * @param array<string,array<string,mixed>> $decisions
     */
    public function apply(Material $material, MaterialDatasheet $datasheet, array $decisions, User $actor): MaterialDatasheetApplication
    {
        if (! $datasheet->is_active) {
            throw ValidationException::withMessages(['datasheet' => 'Archived datasheet evidence cannot be applied to the material.']);
        }
        if ($datasheet->applications()->where('status', 'applied')->exists()) {
            throw ValidationException::withMessages([
                'datasheet' => 'This datasheet already has an active parser application. Roll it back before applying another reviewed revision.',
            ]);
        }

        $this->assertCategoryIsSafe($datasheet);
        $this->assertVariantIsSafe($datasheet);
        $schema = ProfessionalSpecificationSchema::for($material);
        $fieldMap = collect($schema['groups'])->flatMap(fn (array $group): array => $group['fields'])->keyBy('key');
        $parsed = (array) ($datasheet->parsed_fields ?? []);

        if ($decisions === []) {
            throw ValidationException::withMessages(['decisions' => 'Review at least one parsed field and choose an explicit action before applying.']);
        }

        $reviewed = [];
        foreach ($decisions as $key => $decision) {
            if (! $fieldMap->has($key) || ! isset($parsed[$key])) {
                throw ValidationException::withMessages(["decisions.{$key}" => 'This field is not part of the selected component schema.']);
            }

            $action = (string) ($decision['action'] ?? 'keep');
            if (! in_array($action, ['accept', 'keep', 'reject'], true)) {
                throw ValidationException::withMessages(["decisions.{$key}.action" => 'Invalid review action.']);
            }

            $entry = $parsed[$key];
            $parserValue = (string) ($entry['value'] ?? '');
            $parserUnit = $entry['unit'] ?? null;
            $submittedValue = (string) ($decision['value'] ?? $parserValue);
            $submittedUnit = $decision['unit'] ?? $parserUnit;
            $value = $submittedValue;
            $unit = $submittedUnit;
            if ($action === 'accept') {
                $normalized = $this->normalizer->normalize($value, is_string($unit) ? $unit : null, (array) $fieldMap->get($key));
                if (! $normalized['valid'] || $normalized['value'] === '') {
                    throw ValidationException::withMessages(["decisions.{$key}.value" => implode(' ', $normalized['warnings']) ?: 'The reviewed value is invalid.']);
                }
                $value = $normalized['value'];
                $unit = $normalized['unit'];
            }

            $reviewed[$key] = [
                'action' => $action,
                'label' => $entry['label'] ?? ($fieldMap->get($key)['label'] ?? str($key)->replace('_', ' ')->title()->toString()),
                'value' => $value,
                'unit' => $unit,
                'parser_value' => $parserValue,
                'parser_unit' => $parserUnit,
                'parser_status' => $entry['status'] ?? null,
                'confidence' => $entry['confidence'] ?? null,
                'source_page' => $entry['source_page'] ?? null,
                'source_excerpt' => $entry['source_excerpt'] ?? null,
                'matched_alias' => $entry['matched_alias'] ?? null,
                'existing_value' => $entry['existing_value'] ?? null,
                'existing_unit' => $entry['existing_unit'] ?? null,
                'manually_corrected' => $action === 'accept' && (
                    trim($submittedValue) !== trim($parserValue)
                    || strtolower(trim((string) $submittedUnit)) !== strtolower(trim((string) $parserUnit))
                ),
            ];
        }

        if (collect($reviewed)->where('action', 'accept')->isEmpty()) {
            throw ValidationException::withMessages(['decisions' => 'Select at least one safe parsed field to apply.']);
        }

        return DB::transaction(function () use ($material, $datasheet, $actor, $reviewed, $fieldMap, $parsed): MaterialDatasheetApplication {
            $keys = collect($reviewed)->where('action', 'accept')->keys()->all();
            $beforeSpecs = $this->snapshotSpecs($material, $keys);
            $materialSnapshot = $this->materialSnapshot($material);
            $updatedParsed = $parsed;

            foreach ($reviewed as $key => $decision) {
                $updatedParsed[$key]['review_action'] = $decision['action'];
                $updatedParsed[$key]['reviewed_value'] = $decision['value'];
                $updatedParsed[$key]['reviewed_unit'] = $decision['unit'];
                $updatedParsed[$key]['reviewed_by'] = $actor->getKey();
                $updatedParsed[$key]['reviewed_at'] = now()->toIso8601String();

                if ($decision['action'] !== 'accept') {
                    continue;
                }

                $definition = (array) $fieldMap->get($key);
                MaterialSpec::query()->updateOrCreate(
                    ['material_id' => $material->getKey(), 'spec_key' => $key],
                    [
                        'spec_label' => (string) $definition['label'],
                        'spec_value' => (string) $decision['value'],
                        'spec_unit' => $decision['unit'],
                        'sort_order' => $this->sortOrder($fieldMap->keys()->all(), $key),
                        'is_filterable' => (bool) ($definition['filterable'] ?? false),
                    ]
                );
            }

            $material->refresh();
            $this->syncCoreColumnsAndGovernance($material, $actor);
            $afterSpecs = $this->snapshotSpecs($material, $keys);

            $application = MaterialDatasheetApplication::query()->create([
                'material_datasheet_id' => $datasheet->getKey(),
                'material_id' => $material->getKey(),
                'status' => 'applied',
                'parser_version' => $datasheet->parser_version,
                'field_decisions' => $reviewed,
                'before_specs' => $beforeSpecs,
                'after_specs' => $afterSpecs,
                'material_snapshot' => $materialSnapshot,
                'applied_by' => $actor->getKey(),
                'applied_at' => now(),
            ]);

            $datasheet->update([
                'parsed_fields' => $updatedParsed,
                'extraction_status' => 'applied',
                'reviewed_at' => now(),
                'reviewed_by' => $actor->getKey(),
                'applied_at' => now(),
                'applied_by' => $actor->getKey(),
            ]);

            return $application;
        });
    }

    public function rollback(Material $material, MaterialDatasheet $datasheet, MaterialDatasheetApplication $application, User $actor, ?string $note = null): void
    {
        if ((int) $application->material_id !== (int) $material->getKey()
            || (int) $application->material_datasheet_id !== (int) $datasheet->getKey()) {
            abort(404);
        }
        if ($application->status !== 'applied') {
            throw ValidationException::withMessages(['rollback' => 'This parser application has already been rolled back.']);
        }

        $newer = MaterialDatasheetApplication::query()
            ->where('material_id', $material->getKey())
            ->where('status', 'applied')
            ->where(function ($query) use ($application): void {
                $query->where('applied_at', '>', $application->applied_at)
                    ->orWhere(function ($sameTimestamp) use ($application): void {
                        $sameTimestamp
                            ->where('applied_at', $application->applied_at)
                            ->where('id', '>', $application->getKey());
                    });
            })
            ->exists();
        if ($newer) {
            throw ValidationException::withMessages(['rollback' => 'A newer parser application exists. Roll it back first to preserve revision order.']);
        }

        $after = (array) $application->after_specs;
        $current = $this->snapshotSpecs($material, array_keys($after));
        if ($this->snapshotComparable($current) !== $this->snapshotComparable($after)) {
            throw ValidationException::withMessages(['rollback' => 'Technical values changed after this parser application. Rollback is blocked to avoid overwriting newer manual work.']);
        }

        DB::transaction(function () use ($material, $datasheet, $application, $actor, $note): void {
            foreach ((array) $application->before_specs as $key => $snapshot) {
                if ($snapshot === null) {
                    MaterialSpec::query()->where('material_id', $material->getKey())->where('spec_key', $key)->delete();
                    continue;
                }

                MaterialSpec::query()->updateOrCreate(
                    ['material_id' => $material->getKey(), 'spec_key' => $key],
                    [
                        'spec_label' => $snapshot['label'],
                        'spec_value' => $snapshot['value'],
                        'spec_unit' => $snapshot['unit'],
                        'sort_order' => $snapshot['sort_order'],
                        'is_filterable' => $snapshot['is_filterable'],
                    ]
                );
            }

            $material->refresh();
            $this->syncCoreColumnsAndGovernance($material, $actor);
            $application->update([
                'status' => 'rolled_back',
                'rolled_back_by' => $actor->getKey(),
                'rolled_back_at' => now(),
                'rollback_note' => $note,
            ]);

            $latestRemaining = MaterialDatasheetApplication::query()
                ->where('material_datasheet_id', $datasheet->getKey())
                ->where('status', 'applied')
                ->latest('applied_at')
                ->latest('id')
                ->first();
            $datasheet->update([
                'extraction_status' => $latestRemaining ? 'applied' : 'reviewed',
                'applied_at' => $latestRemaining?->applied_at,
                'applied_by' => $latestRemaining?->applied_by,
            ]);
        });
    }

    private function assertCategoryIsSafe(MaterialDatasheet $datasheet): void
    {
        if ((bool) data_get($datasheet->extraction_meta, 'category.blocked', false)) {
            $message = data_get($datasheet->extraction_meta, 'category.review_reason') === 'exact_component_mismatch'
                ? 'Exact component mismatch protection blocked this document. Use a datasheet that matches the material subtype, or register the source against the correct material record.'
                : 'Category mismatch protection blocked this document from being applied.';

            throw ValidationException::withMessages(['datasheet' => $message]);
        }
    }

    private function assertVariantIsSafe(MaterialDatasheet $datasheet): void
    {
        if ((bool) data_get($datasheet->extraction_meta, 'variant.blocked', false)) {
            throw ValidationException::withMessages([
                'datasheet' => 'This multi-variant datasheet requires an exact product power selection before parsed values can be applied.',
            ]);
        }
    }

    /** @param array<int,string> $keys @return array<string,array<string,mixed>|null> */
    private function snapshotSpecs(Material $material, array $keys): array
    {
        $specs = $material->specs()->whereIn('spec_key', $keys)->get()->keyBy('spec_key');
        $snapshot = [];
        foreach ($keys as $key) {
            $spec = $specs->get($key);
            $snapshot[$key] = $spec ? [
                'label' => $spec->spec_label,
                'value' => $spec->spec_value,
                'unit' => $spec->spec_unit,
                'sort_order' => $spec->sort_order,
                'is_filterable' => (bool) $spec->is_filterable,
            ] : null;
        }

        return $snapshot;
    }

    /** @return array<string,mixed> */
    private function materialSnapshot(Material $material): array
    {
        return $material->only([
            'nominal_power_wp', 'nominal_capacity_kwh', 'rated_power_kw', 'voltage_v', 'current_a',
            'electrical_phase', 'ip_rating', 'warranty_years', 'catalog_status', 'compliance_status',
            'approved_at', 'approved_by',
        ]);
    }

    private function syncCoreColumnsAndGovernance(Material $material, User $actor): void
    {
        $values = $material->specs()->pluck('spec_value', 'spec_key')->all();
        $core = ProfessionalSpecificationSchema::coreColumnValues($values, $material);
        $payload = array_merge($core, [
            'last_reviewed_at' => now(),
            'last_reviewed_by' => $actor->getKey(),
            'compliance_status' => 'needs_review',
        ]);
        if ($material->catalog_status === 'approved') {
            $payload += ['catalog_status' => 'needs_review', 'approved_at' => null, 'approved_by' => null];
        }
        $material->forceFill($payload)->save();
    }

    /** @param array<int,string> $keys */
    private function sortOrder(array $keys, string $key): int
    {
        $index = array_search($key, $keys, true);

        return $index === false ? 900 : ((int) $index + 1) * 10;
    }

    private function snapshotComparable(array $snapshot): array
    {
        return collect($snapshot)->map(fn ($row) => $row === null ? null : [
            'value' => (string) $row['value'],
            'unit' => (string) ($row['unit'] ?? ''),
        ])->all();
    }
}
