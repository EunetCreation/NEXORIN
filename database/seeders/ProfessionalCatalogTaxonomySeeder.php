<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Support\ProfessionalCatalogProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfessionalCatalogTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (ProfessionalCatalogProfile::profiles() as $code => $profile) {
                $root = MaterialCategory::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'company_id' => null,
                        'parent_id' => null,
                        'name' => $profile['name'],
                        'description' => $profile['description'],
                        'sort_order' => $this->rootSortOrder($code),
                        'is_active' => true,
                    ]
                );

                $childOrder = 10;
                foreach ($profile['subcategories'] as $childCode => $childName) {
                    MaterialCategory::query()->updateOrCreate(
                        ['code' => $childCode],
                        [
                            'company_id' => null,
                            'parent_id' => $root->getKey(),
                            'name' => $childName,
                            'description' => $childName.' under the '.$profile['name'].' professional catalog profile.',
                            'sort_order' => $childOrder,
                            'is_active' => true,
                        ]
                    );
                    $childOrder += 10;
                }
            }

            $this->migrateLegacyTaxonomy();
        });

        $this->command?->info('Professional NEXORIN material taxonomy is ready: 15 master catalogs with governed subcategories.');
    }


    private function migrateLegacyTaxonomy(): void
    {
        foreach ([
            'MONITORING' => 'MONITORING_METERING',
            'GROUNDING_SAFETY' => 'GROUNDING_LIGHTNING_SAFETY',
            'ENGINEERING_SERVICE' => 'CIVIL_SITE_WORK',
        ] as $legacyCode => $targetCode) {
            $legacy = MaterialCategory::query()->where('code', $legacyCode)->first();
            $target = MaterialCategory::query()->where('code', $targetCode)->first();

            if (! $legacy || ! $target || $legacy->is($target)) {
                continue;
            }

            Material::query()->where('material_category_id', $legacy->getKey())
                ->update(['material_category_id' => $target->getKey()]);
            MaterialCategory::query()->where('parent_id', $legacy->getKey())
                ->update(['parent_id' => $target->getKey()]);

            $legacy->update([
                'is_active' => false,
                'description' => 'Legacy taxonomy retained for audit. Active records were migrated to '.$target->name.'.',
            ]);
        }

        $legacyPower = MaterialCategory::query()->where('code', 'POWER_CONVERSION')->first();
        $onGrid = MaterialCategory::query()->where('code', 'INVERTER_ON_GRID')->first();
        $hybrid = MaterialCategory::query()->where('code', 'INVERTER_HYBRID_OFFGRID')->first();

        if (! $legacyPower || ! $onGrid || ! $hybrid) {
            return;
        }

        MaterialCategory::query()
            ->where('parent_id', $legacyPower->getKey())
            ->get()
            ->each(function (MaterialCategory $category) use ($onGrid, $hybrid): void {
                $search = strtolower($category->code.' '.$category->name);
                $target = preg_match('/hybrid|off.?grid|mppt|charge|pcs/', $search) ? $hybrid : $onGrid;
                $category->update(['parent_id' => $target->getKey()]);
            });

        Material::query()
            ->where('material_category_id', $legacyPower->getKey())
            ->get()
            ->each(function (Material $material) use ($onGrid, $hybrid): void {
                $systems = collect($material->system_types ?? [])->map(fn ($value): string => strtolower((string) $value));
                $search = strtolower(implode(' ', array_filter([$material->name, $material->material_type, $material->model])));
                $hybridFit = $systems->intersect(['hybrid', 'off_grid'])->isNotEmpty()
                    || preg_match('/hybrid|off.?grid|mppt|charge|pcs/', $search);

                $material->update(['material_category_id' => ($hybridFit ? $hybrid : $onGrid)->getKey()]);
            });

        $legacyPower->update([
            'is_active' => false,
            'description' => 'Legacy taxonomy retained for audit. Materials were migrated to the professional on-grid or hybrid/off-grid inverter domains.',
        ]);
    }

    private function rootSortOrder(string $code): int
    {
        $codes = array_keys(ProfessionalCatalogProfile::profiles());
        $index = array_search($code, $codes, true);

        return $index === false ? 900 : ((int) $index + 1) * 10;
    }
}
