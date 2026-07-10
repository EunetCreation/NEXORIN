<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialSpec;
use App\Models\PriceBook;
use App\Models\PriceBookItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialCatalogSeeder extends Seeder
{
    /**
     * Seed a starter material catalog for local development and demos.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('MaterialCatalogSeeder can only run in the local environment.');

            return;
        }

        $company = Company::query()
            ->where('slug', 'nexorin-energy')
            ->first();

        if (! $company) {
            $this->command?->warn('The NEXORIN development company is unavailable. Run DevelopmentCompanySeeder first.');

            return;
        }

        $admin = User::query()
            ->where('email', 'admin@nexorin.local')
            ->first();

        DB::transaction(function () use ($company, $admin): void {
            $categories = [
                ['code' => 'PV_MODULE', 'name' => 'PV Module', 'sort_order' => 10],
                ['code' => 'POWER_CONVERSION', 'name' => 'Power Conversion', 'sort_order' => 20],
                ['code' => 'BATTERY_ESS', 'name' => 'Battery / ESS', 'sort_order' => 30],
                ['code' => 'MOUNTING_STRUCTURE', 'name' => 'Mounting & Structure', 'sort_order' => 40],
                ['code' => 'DC_ELECTRICAL', 'name' => 'DC Electrical', 'sort_order' => 50],
                ['code' => 'AC_ELECTRICAL', 'name' => 'AC Electrical', 'sort_order' => 60],
                ['code' => 'GROUNDING_SAFETY', 'name' => 'Grounding & Safety', 'sort_order' => 70],
                ['code' => 'MONITORING', 'name' => 'Monitoring', 'sort_order' => 80],
                ['code' => 'ENGINEERING_SERVICE', 'name' => 'Engineering Service', 'sort_order' => 90],
            ];

            foreach ($categories as $categoryData) {
                MaterialCategory::query()->updateOrCreate(
                    ['code' => $categoryData['code']],
                    [
                        'company_id' => null,
                        'parent_id' => null,
                        'name' => $categoryData['name'],
                        'description' => 'Default NEXORIN catalog category for '.$categoryData['name'].'.',
                        'sort_order' => $categoryData['sort_order'],
                        'is_active' => true,
                    ]
                );
            }

            $categoryIds = MaterialCategory::query()
                ->whereIn('code', array_column($categories, 'code'))
                ->pluck('id', 'code');

            $materials = [
                [
                    'sku' => 'PV-MOD-550-MONO',
                    'category' => 'PV_MODULE',
                    'name' => 'Solar PV Module 550 Wp Mono PERC',
                    'brand' => 'Reference Tier-1',
                    'model' => '550Wp Mono',
                    'material_type' => 'PV module',
                    'cost_group' => 'Material',
                    'uom' => 'pcs',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'nominal_power_wp' => 550,
                    'voltage_v' => 41.9,
                    'current_a' => 13.13,
                    'warranty_years' => 12,
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference tier-1 PV module used as a starter catalog item for rooftop PV sizing.',
                    'technical_notes' => 'Replace with actual approved brand, model, datasheet, and certificate before procurement.',
                    'specs' => [
                        ['module_efficiency', 'Module Efficiency', '21.3', '%'],
                        ['voc', 'Open Circuit Voltage', '49.8', 'V'],
                        ['vmp', 'Maximum Power Voltage', '41.9', 'V'],
                        ['imp', 'Maximum Power Current', '13.13', 'A'],
                    ],
                ],
                [
                    'sku' => 'INV-HYB-5KW-1P',
                    'category' => 'POWER_CONVERSION',
                    'name' => 'Hybrid Inverter 5 kW Single Phase',
                    'brand' => 'Reference Hybrid',
                    'model' => 'HYB-5K-1P',
                    'material_type' => 'Hybrid inverter',
                    'cost_group' => 'Material',
                    'uom' => 'pcs',
                    'system_types' => ['hybrid'],
                    'project_scales' => ['residential', 'commercial'],
                    'rated_power_kw' => 5,
                    'voltage_v' => 220,
                    'electrical_phase' => 'Single Phase',
                    'ip_rating' => 'IP65',
                    'warranty_years' => 5,
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference single-phase hybrid inverter for residential and light-commercial backup planning.',
                    'technical_notes' => 'Final model must be checked for MPPT voltage range, battery compatibility, and grid code settings.',
                    'specs' => [
                        ['ac_power', 'AC Rated Power', '5', 'kW'],
                        ['mppt_qty', 'MPPT Quantity', '2', null],
                        ['battery_voltage', 'Battery Voltage Range', '48', 'V nominal'],
                    ],
                ],
                [
                    'sku' => 'BAT-LFP-5KWH',
                    'category' => 'BATTERY_ESS',
                    'name' => 'LFP Battery Module 5.12 kWh',
                    'brand' => 'Reference ESS',
                    'model' => 'LFP-5.12',
                    'material_type' => 'Battery module',
                    'cost_group' => 'Material',
                    'uom' => 'kWh',
                    'system_types' => ['hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'nominal_capacity_kwh' => 5.12,
                    'voltage_v' => 51.2,
                    'warranty_years' => 5,
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference LFP battery module for hybrid and off-grid energy storage planning.',
                    'technical_notes' => 'Final battery selection must match inverter battery protocol, BMS limits, and installation environment.',
                    'specs' => [
                        ['chemistry', 'Chemistry', 'LFP', null],
                        ['nominal_capacity', 'Nominal Capacity', '5.12', 'kWh'],
                        ['dod', 'Recommended Depth of Discharge', '80', '%'],
                    ],
                ],
                [
                    'sku' => 'CABLE-PV1F-4MM',
                    'category' => 'DC_ELECTRICAL',
                    'name' => 'PV1-F DC Cable 4 mm² Red / Black',
                    'brand' => 'Reference Cable',
                    'model' => 'PV1-F 4mm²',
                    'material_type' => 'DC cable',
                    'cost_group' => 'Electrical BOS',
                    'uom' => 'm',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'voltage_v' => 1500,
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference PV cable for DC string routing.',
                    'technical_notes' => 'Cable size must be checked against current, voltage drop, routing condition, and local standard.',
                    'specs' => [
                        ['conductor_size', 'Conductor Size', '4', 'mm²'],
                        ['insulation', 'Insulation', 'PV1-F', null],
                    ],
                ],
                [
                    'sku' => 'SPD-DC-T2-1000V',
                    'category' => 'DC_ELECTRICAL',
                    'name' => 'DC Surge Protection Device Type II 1000V',
                    'brand' => 'Reference Protection',
                    'model' => 'DC-SPD-T2-1000',
                    'material_type' => 'DC protection',
                    'cost_group' => 'Electrical BOS',
                    'uom' => 'pcs',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'voltage_v' => 1000,
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference DC SPD for PV array protection.',
                    'technical_notes' => 'Final SPD class, pole configuration, and voltage rating must follow array voltage and protection design.',
                    'specs' => [
                        ['type', 'SPD Type', 'Type II', null],
                        ['rated_voltage', 'Rated DC Voltage', '1000', 'Vdc'],
                    ],
                ],
                [
                    'sku' => 'FIRE-EXT-BASIC-SET',
                    'category' => 'GROUNDING_SAFETY',
                    'name' => 'Portable Fire Extinguisher and Basic Safety Equipment Allowance',
                    'brand' => 'Reference Safety',
                    'model' => 'Basic Safety Set',
                    'material_type' => 'Safety equipment',
                    'cost_group' => 'Electrical BOS',
                    'uom' => 'lot',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Basic safety allowance for electrical panel, inverter, and battery area readiness.',
                    'technical_notes' => 'Final fire safety scope must follow site risk assessment and applicable regulation.',
                    'specs' => [
                        ['scope', 'Scope', 'Portable extinguisher, labels, and basic safety accessories', null],
                    ],
                ],
            ];

            foreach ($materials as $materialData) {
                $specs = $materialData['specs'];
                unset($materialData['specs']);

                $categoryCode = $materialData['category'];
                unset($materialData['category']);

                $material = Material::query()->updateOrCreate(
                    [
                        'company_id' => $company->getKey(),
                        'sku' => $materialData['sku'],
                    ],
                    array_merge($materialData, [
                        'material_category_id' => $categoryIds[$categoryCode] ?? null,
                        'is_active' => true,
                        'created_by' => $admin?->getKey(),
                        'updated_by' => $admin?->getKey(),
                    ])
                );

                foreach ($specs as $index => $spec) {
                    MaterialSpec::query()->updateOrCreate(
                        [
                            'material_id' => $material->getKey(),
                            'spec_key' => $spec[0],
                        ],
                        [
                            'spec_label' => $spec[1],
                            'spec_value' => $spec[2],
                            'spec_unit' => $spec[3],
                            'sort_order' => ($index + 1) * 10,
                            'is_filterable' => true,
                        ]
                    );
                }
            }

            $supplier = Supplier::query()->updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'supplier_code' => 'NXR-DEMO-SUP',
                ],
                [
                    'name' => 'NEXORIN Demo Supplier',
                    'contact_person' => 'Procurement Desk',
                    'email' => 'supplier@nexorin.local',
                    'phone' => '+62 761 000 2026',
                    'address' => 'Pekanbaru, Riau',
                    'website' => 'https://nexorin.local',
                    'payment_terms' => '50% down payment, 50% before delivery',
                    'lead_time_days' => 14,
                    'status' => 'active',
                    'notes' => 'Demo supplier for local material catalog testing.',
                    'created_by' => $admin?->getKey(),
                    'updated_by' => $admin?->getKey(),
                ]
            );

            $priceBook = PriceBook::query()->updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'code' => 'NXR-PB-DEMO-2026',
                ],
                [
                    'supplier_id' => $supplier->getKey(),
                    'name' => 'NEXORIN Demo Price Book 2026',
                    'currency' => 'IDR',
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'effective_until' => now()->endOfYear()->toDateString(),
                    'pricing_basis' => 'internal_estimate',
                    'status' => 'active',
                    'notes' => 'Starter price book for local catalog testing. Replace values with approved supplier quotes.',
                    'created_by' => $admin?->getKey(),
                    'updated_by' => $admin?->getKey(),
                ]
            );

            $priceMap = [
                'PV-MOD-550-MONO' => [1650000, 1897500, 'available'],
                'INV-HYB-5KW-1P' => [28500000, 32775000, 'available'],
                'BAT-LFP-5KWH' => [4750000, 5200000, 'available'],
                'CABLE-PV1F-4MM' => [35000, 40250, 'available'],
                'SPD-DC-T2-1000V' => [850000, 977500, 'available'],
                'FIRE-EXT-BASIC-SET' => [950000, 1092500, 'available'],
            ];

            foreach ($priceMap as $sku => [$unitCost, $sellingPrice, $availability]) {
                $material = Material::query()
                    ->where('company_id', $company->getKey())
                    ->where('sku', $sku)
                    ->first();

                if (! $material) {
                    continue;
                }

                PriceBookItem::query()->updateOrCreate(
                    [
                        'price_book_id' => $priceBook->getKey(),
                        'material_id' => $material->getKey(),
                    ],
                    [
                        'supplier_id' => $supplier->getKey(),
                        'supplier_sku' => $sku,
                        'unit_cost' => $unitCost,
                        'selling_price' => $sellingPrice,
                        'margin_percent' => null,
                        'minimum_order_quantity' => 1,
                        'lead_time_days' => 14,
                        'availability_status' => $availability,
                        'warranty_note' => 'Demo warranty reference. Confirm with supplier before release.',
                        'quoted_at' => now()->toDateString(),
                        'valid_until' => now()->endOfYear()->toDateString(),
                    ]
                );
            }
        });

        $this->command?->info('The NEXORIN starter material catalog is ready.');
    }
}
