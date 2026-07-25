<?php

namespace App\Services\Catalog;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialSpec;
use App\Support\ProfessionalSpecificationSchema;
use Illuminate\Support\Arr;

class MaterialSpecificationSynchronizer
{
    /**
     * Synchronize category-managed specifications without deleting custom or parser-only fields.
     *
     * @param array<string, mixed> $values
     * @param array<int, string> $previousManagedKeys
     * @return array{changed:bool,normalized:array<string,string|null>,core:array<string,mixed>}
     */
    public function sync(Material $material, MaterialCategory|string|null $category, array $values, array $previousManagedKeys = []): array
    {
        $schema = ProfessionalSpecificationSchema::for($category);
        $fieldMap = collect($schema['groups'])
            ->flatMap(fn (array $group): array => $group['fields'])
            ->keyBy('key');
        $managedKeys = $fieldMap->keys()->all();
        $normalized = [];
        $changed = false;

        foreach ($managedKeys as $key) {
            $definition = $fieldMap->get($key, []);
            $value = $this->normalizeValue(Arr::get($values, $key), $definition);
            $normalized[$key] = $value;

            $existing = $material->specs()->where('spec_key', $key)->first();

            if ($value === null) {
                if ($existing) {
                    $existing->delete();
                    $changed = true;
                }
                continue;
            }

            $payload = [
                'spec_label' => (string) ($definition['label'] ?? str($key)->replace('_', ' ')->title()),
                'spec_value' => $value,
                'spec_unit' => $definition['unit'] ?? null,
                'sort_order' => $this->sortOrder($schema, $key),
                'is_filterable' => (bool) ($definition['filterable'] ?? false),
            ];

            if (! $existing) {
                MaterialSpec::query()->create(array_merge($payload, [
                    'material_id' => $material->getKey(),
                    'spec_key' => $key,
                ]));
                $changed = true;
                continue;
            }

            if ($this->specChanged($existing, $payload)) {
                $existing->update($payload);
                $changed = true;
            }
        }

        $obsoleteKeys = array_values(array_unique(array_merge(
            array_diff($previousManagedKeys, $managedKeys),
            ProfessionalSpecificationSchema::deprecatedKeysFor($category)
        )));
        if ($obsoleteKeys !== []) {
            $deleted = $material->specs()->whereIn('spec_key', $obsoleteKeys)->delete();
            $changed = $changed || $deleted > 0;
        }

        $core = array_filter(
            ProfessionalSpecificationSchema::coreColumnValues($normalized, $category),
            fn ($value): bool => $value !== null
        );

        // Clear previously mapped summary fields when the new schema no longer supplies them.
        foreach (['nominal_power_wp', 'nominal_capacity_kwh', 'rated_power_kw', 'voltage_v', 'current_a', 'electrical_phase', 'ip_rating', 'warranty_years'] as $column) {
            if (! array_key_exists($column, $core)) {
                $core[$column] = null;
            }
        }

        $material->refresh();
        $coreChanged = collect($core)->contains(fn ($value, string $column): bool => (string) ($material->{$column} ?? '') !== (string) ($value ?? ''));
        if ($coreChanged) {
            $material->forceFill($core)->save();
            $changed = true;
        }

        return [
            'changed' => $changed,
            'normalized' => $normalized,
            'core' => $core,
        ];
    }

    /** @param array<string, mixed> $definition */
    private function normalizeValue(mixed $value, array $definition): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $type = (string) ($definition['input_type'] ?? 'text');

        if ($type === 'number') {
            $normalized = str_replace(',', '.', trim((string) $value));
            if (! is_numeric($normalized)) {
                return null;
            }

            $number = (float) $normalized;
            $step = (string) ($definition['step'] ?? '0.01');

            return $step === '1'
                ? (string) (int) round($number)
                : rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
        }

        if ($type === 'select' && in_array((string) $value, ['0', '1'], true)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    /** @param array<string, mixed> $schema */
    private function sortOrder(array $schema, string $key): int
    {
        $keys = collect($schema['groups'])
            ->flatMap(fn (array $group): array => collect($group['fields'])->pluck('key')->all())
            ->values()
            ->all();
        $index = array_search($key, $keys, true);

        return $index === false ? 900 : ((int) $index + 1) * 10;
    }

    /** @param array<string, mixed> $payload */
    private function specChanged(MaterialSpec $existing, array $payload): bool
    {
        return (string) $existing->spec_label !== (string) $payload['spec_label']
            || (string) $existing->spec_value !== (string) $payload['spec_value']
            || (string) ($existing->spec_unit ?? '') !== (string) ($payload['spec_unit'] ?? '')
            || (int) $existing->sort_order !== (int) $payload['sort_order']
            || (bool) $existing->is_filterable !== (bool) $payload['is_filterable'];
    }
}
