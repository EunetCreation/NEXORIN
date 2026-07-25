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
use App\Services\Catalog\MaterialSpecificationSynchronizer;
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
            $this->call(ProfessionalCatalogTaxonomySeeder::class);

            $categoryIds = MaterialCategory::query()
                ->whereIn('code', [
                    'PV_MONO_PERC',
                    'INV_HYBRID',
                    'BAT_WALL',
                    'DC_CABLE_CONNECTOR',
                    'DC_SPD',
                    'FIRE_EXTINGUISHER',
                ])
                ->pluck('id', 'code');

            $materials = [
                [
                    'sku' => 'PV-MOD-550-MONO',
                    'category' => 'PV_MONO_PERC',
                    'name' => 'Solar PV Module 550 Wp Mono PERC',
                    'brand' => 'Reference Tier-1',
                    'model' => '550Wp Mono',
                    'material_type' => 'PV module',
                    'cost_group' => 'Material',
                    'uom' => 'pcs',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference tier-1 PV module used as a starter catalog item for rooftop PV sizing.',
                    'technical_notes' => 'Replace with actual approved brand, model, datasheet, and certificate before procurement.',
                    'technical_specs' => [
                        'module_technology' => 'Mono PERC',
                        'module_power_wp' => 550,
                        'voc' => 49.8,
                        'vmp' => 41.9,
                        'isc' => 13.9,
                        'imp' => 13.13,
                        'module_efficiency_percent' => 21.3,
                        'temp_coeff_voc' => -0.25,
                        'temp_coeff_pmax' => -0.29,
                        'max_system_voltage' => '1500',
                        'cell_type' => 'Mono PERC half-cut',
                        'cell_count' => 144,
                        'length_mm' => 2278,
                        'width_mm' => 1134,
                        'weight_kg' => 27.5,
                        'connector_type' => 'MC4 compatible',
                        'frame_material' => 'Anodized aluminium',
                        'product_warranty_years' => 12,
                        'performance_warranty_years' => 30,
                    ],
                ],
                [
                    'sku' => 'INV-HYB-5KW-1P',
                    'category' => 'INV_HYBRID',
                    'name' => 'Hybrid Inverter 5 kW Single Phase',
                    'brand' => 'Reference Hybrid',
                    'model' => 'HYB-5K-1P',
                    'material_type' => 'Hybrid inverter',
                    'cost_group' => 'Material',
                    'uom' => 'pcs',
                    'system_types' => ['hybrid'],
                    'project_scales' => ['residential', 'commercial'],
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference single-phase hybrid inverter for residential and light-commercial backup planning.',
                    'technical_notes' => 'Final model must be checked for MPPT voltage range, battery compatibility, and grid code settings.',
                    'technical_specs' => [
                        'inverter_type' => 'Hybrid inverter',
                        'rated_ac_power_kw' => 5,
                        'max_dc_power_kwp' => 7.5,
                        'max_dc_voltage' => '600',
                        'mppt_count' => 2,
                        'mppt_voltage_min' => '120',
                        'mppt_voltage_max' => '550',
                        'rated_ac_voltage' => '230',
                        'rated_ac_current_a' => 21.8,
                        'phase_type' => 'single_phase',
                        'efficiency_percent' => 97.6,
                        'battery_voltage_range' => '40–60',
                        'max_charge_current_a' => 100,
                        'max_discharge_current_a' => 100,
                        'backup_output_kw' => 5,
                        'surge_power_kw' => 10,
                        'transfer_time_ms' => 10,
                        'bms_protocol' => 'CAN / RS485',
                        'communication_protocol' => 'RS485 / Wi-Fi',
                        'parallel_limit' => 6,
                        'genset_support' => '1',
                        'grid_code_profile' => 'Configurable utility profile',
                        'ip_rating' => 'IP65',
                        'warranty_years' => 5,
                    ],
                ],
                [
                    'sku' => 'BAT-LFP-5KWH',
                    'category' => 'BAT_WALL',
                    'name' => 'LFP Battery Module 5.12 kWh',
                    'brand' => 'Reference ESS',
                    'model' => 'LFP-5.12',
                    'material_type' => 'Battery module',
                    'cost_group' => 'Material',
                    'uom' => 'kWh',
                    'system_types' => ['hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference LFP battery module for hybrid and off-grid energy storage planning.',
                    'technical_notes' => 'Final battery selection must match inverter battery protocol, BMS limits, and installation environment.',
                    'technical_specs' => [
                        'battery_chemistry' => 'lfp',
                        'nominal_voltage_v' => 51.2,
                        'capacity_ah' => 100,
                        'capacity_kwh' => 5.12,
                        'usable_capacity_kwh' => 4.61,
                        'dod_percent' => 90,
                        'round_trip_efficiency_percent' => 95,
                        'cycle_life' => 6000,
                        'max_charge_current_a' => 100,
                        'max_discharge_current_a' => 100,
                        'c_rate' => 1,
                        'parallel_limit' => 16,
                        'mounting_type' => 'Wall mounted',
                        'dimensions_mm' => '480 × 460 × 180',
                        'weight_kg' => 48,
                        'communication_protocol' => 'CAN / RS485',
                        'operating_temperature' => '-10 to 50',
                        'ip_rating' => 'IP54',
                        'fire_safety_required' => '1',
                        'warranty_years' => 5,
                    ],
                ],
                [
                    'sku' => 'CABLE-PV1F-4MM',
                    'category' => 'DC_CABLE_CONNECTOR',
                    'name' => 'PV1-F DC Cable 4 mm² Red / Black',
                    'brand' => 'Reference Cable',
                    'model' => 'PV1-F 4mm²',
                    'material_type' => 'DC cable',
                    'cost_group' => 'Electrical BOS',
                    'uom' => 'm',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference PV cable for DC string routing.',
                    'technical_notes' => 'Cable size must be checked against current, voltage drop, routing condition, and local standard.',
                    'technical_specs' => [
                        'device_or_cable_type' => 'PV1-F solar cable',
                        'conductor_material' => 'Tinned copper',
                        'conductor_size_mm2' => '4',
                        'rated_voltage' => '1500',
                        'rated_current' => '55',
                        'insulation' => 'Cross-linked halogen-free compound',
                        'uv_resistance' => 'UV and ozone resistant',
                        'temperature_rating' => '-40 to 90',
                        'flame_rating' => 'IEC 60332-1',
                        'outer_diameter_mm' => 5.8,
                        'minimum_bending_radius_mm' => 23.2,
                        'drum_length_m' => 500,
                        'cable_color' => 'Red / black',
                        'connector_type' => 'MC4 compatible termination',
                        'standard' => 'EN 50618 / IEC 62930',
                    ],
                ],
                [
                    'sku' => 'SPD-DC-T2-1000V',
                    'category' => 'DC_SPD',
                    'name' => 'DC Surge Protection Device Type II 1000V',
                    'brand' => 'Reference Protection',
                    'model' => 'DC-SPD-T2-1000',
                    'material_type' => 'DC protection',
                    'cost_group' => 'Electrical BOS',
                    'uom' => 'pcs',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Reference DC SPD for PV array protection.',
                    'technical_notes' => 'Final SPD class, pole configuration, and voltage rating must follow array voltage and protection design.',
                    'technical_specs' => [
                        'device_type' => 'DC surge protection device',
                        'spd_type' => 'Type II',
                        'pole_count' => 2,
                        'uc_voltage' => '1000',
                        'up_voltage_kv' => '3.5',
                        'in_current' => 20,
                        'imax_current' => 40,
                        'short_circuit_withstand_ka' => 10,
                        'response_time_ns' => 25,
                        'remote_contact' => '0',
                        'replaceable_cartridge' => '1',
                        'visual_indicator' => '1',
                        'ip_rating' => 'IP20',
                        'standard' => 'IEC 61643-31',
                    ],
                ],
                [
                    'sku' => 'FIRE-EXT-BASIC-SET',
                    'category' => 'FIRE_EXTINGUISHER',
                    'name' => 'Portable Fire Extinguisher and Basic Safety Equipment Allowance',
                    'brand' => 'Reference Safety',
                    'model' => 'Basic Safety Set',
                    'material_type' => 'Safety equipment',
                    'cost_group' => 'Safety & HSE',
                    'uom' => 'lot',
                    'system_types' => ['on_grid', 'hybrid', 'off_grid'],
                    'project_scales' => ['residential', 'commercial'],
                    'default_scope_mode' => 'base_boq',
                    'description' => 'Basic safety allowance for electrical panel, inverter, and battery area readiness.',
                    'technical_notes' => 'Final fire safety scope must follow site risk assessment and applicable regulation.',
                    'technical_specs' => [
                        'device_or_service_type' => 'Portable fire extinguisher set',
                        'extinguishing_agent' => 'Dry chemical powder',
                        'capacity' => 6,
                        'fire_rating' => 'ABC',
                        'operating_pressure_bar' => 15,
                        'discharge_time_s' => 15,
                        'mounting_bracket_included' => '1',
                        'maintenance_interval' => '12 months',
                        'application_area' => 'Electrical panel, inverter, and battery support area',
                        'standard' => 'Applicable local fire safety standard',
                        'warranty_years' => 1,
                    ],
                ],
            ];

            $synchronizer = app(MaterialSpecificationSynchronizer::class);

            foreach ($materials as $materialData) {
                $technicalSpecs = $materialData['technical_specs'];
                unset($materialData['technical_specs']);

                $categoryCode = $materialData['category'];
                unset($materialData['category']);

                $material = Material::query()->updateOrCreate(
                    [
                        'company_id' => $company->getKey(),
                        'sku' => $materialData['sku'],
                    ],
                    array_merge($materialData, [
                        'material_category_id' => $categoryIds[$categoryCode] ?? null,
                        'catalog_status' => 'draft',
                        'compliance_status' => 'needs_review',
                        'is_active' => true,
                        'created_by' => $admin?->getKey(),
                        'updated_by' => $admin?->getKey(),
                    ])
                );

                $synchronizer->sync($material, $categoryCode, $technicalSpecs);
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
