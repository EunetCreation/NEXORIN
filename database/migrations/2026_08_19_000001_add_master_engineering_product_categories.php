<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $definitions = [
            ['code' => 'EPM_PV_MOD_STD', 'root' => 'PV_MODULE', 'title' => 'PV Module — monofacial', 'reference' => '55.PV-MOD-STD'],
            ['code' => 'EPM_PV_MOD_BIF', 'root' => 'PV_MODULE', 'title' => 'PV Module — bifacial', 'reference' => '55.PV-MOD-BIF'],
            ['code' => 'EPM_PV_MOD_BIPV', 'root' => 'PV_MODULE', 'title' => 'Building-integrated PV module', 'reference' => '55.PV-MOD-BIPV'],
            ['code' => 'EPM_PV_OPT', 'root' => 'INVERTER_ON_GRID', 'title' => 'DC optimizer / MLPE', 'reference' => '55.PV-OPT'],
            ['code' => 'EPM_PV_RSD', 'root' => 'INVERTER_ON_GRID', 'title' => 'Rapid shutdown device', 'reference' => '55.PV-RSD'],
            ['code' => 'EPM_INV_STRING', 'root' => 'INVERTER_ON_GRID', 'title' => 'String inverter', 'reference' => '55.INV-STRING'],
            ['code' => 'EPM_INV_CENTRAL', 'root' => 'INVERTER_ON_GRID', 'title' => 'Central inverter / inverter station', 'reference' => '55.INV-CENTRAL'],
            ['code' => 'EPM_INV_MICRO', 'root' => 'INVERTER_ON_GRID', 'title' => 'Microinverter', 'reference' => '55.INV-MICRO'],
            ['code' => 'EPM_INV_HYBRID', 'root' => 'INVERTER_HYBRID_OFFGRID', 'title' => 'Hybrid inverter', 'reference' => '55.INV-HYBRID'],
            ['code' => 'EPM_PCS_BESS', 'root' => 'INVERTER_HYBRID_OFFGRID', 'title' => 'Bidirectional PCS', 'reference' => '55.PCS-BESS'],
            ['code' => 'EPM_BAT_CELL', 'root' => 'BATTERY_ESS', 'title' => 'Battery cell', 'reference' => '55.BAT-CELL'],
            ['code' => 'EPM_BAT_MODULE', 'root' => 'BATTERY_ESS', 'title' => 'Battery module/pack', 'reference' => '55.BAT-MODULE'],
            ['code' => 'EPM_BAT_RACK', 'root' => 'BATTERY_ESS', 'title' => 'Battery rack', 'reference' => '55.BAT-RACK'],
            ['code' => 'EPM_BAT_CAB', 'root' => 'BATTERY_ESS', 'title' => 'Battery cabinet', 'reference' => '55.BAT-CAB'],
            ['code' => 'EPM_BAT_CONTAINER', 'root' => 'BATTERY_ESS', 'title' => 'Containerized BESS', 'reference' => '55.BAT-CONTAINER'],
            ['code' => 'EPM_BMS', 'root' => 'BATTERY_ESS', 'title' => 'Battery management system', 'reference' => '55.BMS'],
            ['code' => 'EPM_EMS', 'root' => 'BATTERY_ESS', 'title' => 'Energy management system', 'reference' => '55.EMS'],
            ['code' => 'EPM_DC_CABLE', 'root' => 'DC_ELECTRICAL', 'title' => 'PV DC cable', 'reference' => '55.DC-CABLE'],
            ['code' => 'EPM_DC_CONN', 'root' => 'DC_ELECTRICAL', 'title' => 'PV connector', 'reference' => '55.DC-CONN'],
            ['code' => 'EPM_DC_BRANCH', 'root' => 'DC_ELECTRICAL', 'title' => 'PV branch connector', 'reference' => '55.DC-BRANCH'],
            ['code' => 'EPM_DC_FUSE', 'root' => 'DC_ELECTRICAL', 'title' => 'gPV/string fuse', 'reference' => '55.DC-FUSE'],
            ['code' => 'EPM_DC_FUSE_HOLDER', 'root' => 'DC_ELECTRICAL', 'title' => 'DC fuse holder', 'reference' => '55.DC-FUSE-HOLDER'],
            ['code' => 'EPM_DC_BREAKER', 'root' => 'DC_ELECTRICAL', 'title' => 'DC circuit breaker', 'reference' => '55.DC-BREAKER'],
            ['code' => 'EPM_DC_ISOLATOR', 'root' => 'DC_ELECTRICAL', 'title' => 'PV DC switch-disconnector', 'reference' => '55.DC-ISOLATOR'],
            ['code' => 'EPM_DC_SPD', 'root' => 'DC_ELECTRICAL', 'title' => 'PV DC SPD', 'reference' => '55.DC-SPD'],
            ['code' => 'EPM_DC_COMB', 'root' => 'DC_ELECTRICAL', 'title' => 'DC combiner/string monitoring box', 'reference' => '55.DC-COMB'],
            ['code' => 'EPM_AC_CABLE', 'root' => 'AC_ELECTRICAL', 'title' => 'LV AC power cable', 'reference' => '55.AC-CABLE'],
            ['code' => 'EPM_MCB', 'root' => 'AC_ELECTRICAL', 'title' => 'Miniature circuit breaker', 'reference' => '55.MCB'],
            ['code' => 'EPM_MCCB', 'root' => 'AC_ELECTRICAL', 'title' => 'Moulded case circuit breaker', 'reference' => '55.MCCB'],
            ['code' => 'EPM_ACB', 'root' => 'AC_ELECTRICAL', 'title' => 'Air circuit breaker', 'reference' => '55.ACB'],
            ['code' => 'EPM_RCD', 'root' => 'AC_ELECTRICAL', 'title' => 'Residual-current device', 'reference' => '55.RCD'],
            ['code' => 'EPM_RCBO', 'root' => 'AC_ELECTRICAL', 'title' => 'Residual-current breaker with overcurrent', 'reference' => '55.RCBO'],
            ['code' => 'EPM_AC_SPD', 'root' => 'AC_ELECTRICAL', 'title' => 'AC surge protective device', 'reference' => '55.AC-SPD'],
            ['code' => 'EPM_AC_ISOLATOR', 'root' => 'AC_ELECTRICAL', 'title' => 'AC switch-disconnector', 'reference' => '55.AC-ISOLATOR'],
            ['code' => 'EPM_CONTACTOR', 'root' => 'AC_ELECTRICAL', 'title' => 'Power contactor', 'reference' => '55.CONTACTOR'],
            ['code' => 'EPM_ATS', 'root' => 'AC_ELECTRICAL', 'title' => 'Automatic transfer switch', 'reference' => '55.ATS'],
            ['code' => 'EPM_STS', 'root' => 'AC_ELECTRICAL', 'title' => 'Static transfer switch', 'reference' => '55.STS'],
            ['code' => 'EPM_BUSBAR', 'root' => 'AC_ELECTRICAL', 'title' => 'LV busbar', 'reference' => '55.BUSBAR'],
            ['code' => 'EPM_BUSDUCT', 'root' => 'AC_ELECTRICAL', 'title' => 'Busway / busduct', 'reference' => '55.BUSDuct'],
            ['code' => 'EPM_LV_PANEL', 'root' => 'PANEL_DISTRIBUTION', 'title' => 'LVDB/ACDB/LVMDP assembly', 'reference' => '55.LV-PANEL'],
            ['code' => 'EPM_TRANSFORMER', 'root' => 'AC_ELECTRICAL', 'title' => 'Power/distribution transformer', 'reference' => '55.TRANSFORMER'],
            ['code' => 'EPM_MV_CABLE', 'root' => 'AC_ELECTRICAL', 'title' => 'Medium-voltage cable', 'reference' => '55.MV-CABLE'],
            ['code' => 'EPM_MV_RMU', 'root' => 'AC_ELECTRICAL', 'title' => 'Ring main unit', 'reference' => '55.MV-RMU'],
            ['code' => 'EPM_MV_SWG', 'root' => 'AC_ELECTRICAL', 'title' => 'MV switchgear panel', 'reference' => '55.MV-SWG'],
            ['code' => 'EPM_VCB', 'root' => 'AC_ELECTRICAL', 'title' => 'Vacuum circuit breaker', 'reference' => '55.VCB'],
            ['code' => 'EPM_MV_LBS', 'root' => 'AC_ELECTRICAL', 'title' => 'Load break switch', 'reference' => '55.MV-LBS'],
            ['code' => 'EPM_RELAY', 'root' => 'AC_ELECTRICAL', 'title' => 'Protection relay', 'reference' => '55.RELAY'],
            ['code' => 'EPM_CT_METER', 'root' => 'AC_ELECTRICAL', 'title' => 'Metering CT', 'reference' => '55.CT-METER'],
            ['code' => 'EPM_CT_PROT', 'root' => 'AC_ELECTRICAL', 'title' => 'Protection CT', 'reference' => '55.CT-PROT'],
            ['code' => 'EPM_VT', 'root' => 'AC_ELECTRICAL', 'title' => 'Voltage transformer/PT', 'reference' => '55.VT'],
            ['code' => 'EPM_METER', 'root' => 'MONITORING_METERING', 'title' => 'Bidirectional energy meter', 'reference' => '55.METER'],
            ['code' => 'EPM_PQM', 'root' => 'MONITORING_METERING', 'title' => 'Power quality meter', 'reference' => '55.PQM'],
            ['code' => 'EPM_EXPORT_CTRL', 'root' => 'MONITORING_METERING', 'title' => 'Export/zero-export controller', 'reference' => '55.EXPORT-CTRL'],
            ['code' => 'EPM_PLC', 'root' => 'MONITORING_METERING', 'title' => 'PLC/plant controller', 'reference' => '55.PLC'],
            ['code' => 'EPM_LOGGER', 'root' => 'MONITORING_METERING', 'title' => 'Data logger/gateway', 'reference' => '55.LOGGER'],
            ['code' => 'EPM_NET_SW', 'root' => 'MONITORING_METERING', 'title' => 'Industrial Ethernet switch', 'reference' => '55.NET-SW'],
            ['code' => 'EPM_ROUTER', 'root' => 'MONITORING_METERING', 'title' => 'Industrial router/cellular gateway', 'reference' => '55.ROUTER'],
            ['code' => 'EPM_UPS', 'root' => 'MONITORING_METERING', 'title' => 'Auxiliary UPS', 'reference' => '55.UPS'],
            ['code' => 'EPM_PYR', 'root' => 'MONITORING_METERING', 'title' => 'Pyranometer/reference irradiance sensor', 'reference' => '55.PYR'],
            ['code' => 'EPM_TEMP_SENS', 'root' => 'MONITORING_METERING', 'title' => 'Module/ambient temperature sensor', 'reference' => '55.TEMP-SENS'],
            ['code' => 'EPM_ANEMO', 'root' => 'MONITORING_METERING', 'title' => 'Wind sensor', 'reference' => '55.ANEMO'],
            ['code' => 'EPM_WEATHER_STN', 'root' => 'MONITORING_METERING', 'title' => 'Weather station', 'reference' => '55.WEATHER-STN'],
            ['code' => 'EPM_RAIL', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'PV mounting rail', 'reference' => '55.RAIL'],
            ['code' => 'EPM_MID_CLAMP', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Module mid-clamp', 'reference' => '55.MID-CLAMP'],
            ['code' => 'EPM_END_CLAMP', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Module end-clamp', 'reference' => '55.END-CLAMP'],
            ['code' => 'EPM_ROOF_HOOK', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Tile roof hook', 'reference' => '55.ROOF-HOOK'],
            ['code' => 'EPM_L_FOOT', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Metal roof L-foot', 'reference' => '55.L-FOOT'],
            ['code' => 'EPM_HANGER_BOLT', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Hanger bolt attachment', 'reference' => '55.HANGER-BOLT'],
            ['code' => 'EPM_SEAM_CLAMP', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Standing seam clamp', 'reference' => '55.SEAM-CLAMP'],
            ['code' => 'EPM_BALLAST', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Ballast tray/block system', 'reference' => '55.BALLAST'],
            ['code' => 'EPM_POST', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Ground-mount post', 'reference' => '55.POST'],
            ['code' => 'EPM_PILE', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Pile/screw pile', 'reference' => '55.PILE'],
            ['code' => 'EPM_TRACKER', 'root' => 'MOUNTING_STRUCTURE', 'title' => 'Single-axis tracker', 'reference' => '55.TRACKER'],
            ['code' => 'EPM_FOUND_CONC', 'root' => 'CIVIL_SITE_WORK', 'title' => 'Concrete foundation', 'reference' => '55.FOUND-CONC'],
            ['code' => 'EPM_EARTH_COND', 'root' => 'GROUNDING_LIGHTNING_SAFETY', 'title' => 'Protective/bonding conductor', 'reference' => '55.EARTH-COND'],
            ['code' => 'EPM_EARTH_ROD', 'root' => 'GROUNDING_LIGHTNING_SAFETY', 'title' => 'Earth electrode rod', 'reference' => '55.EARTH-ROD'],
            ['code' => 'EPM_EARTH_BAR', 'root' => 'GROUNDING_LIGHTNING_SAFETY', 'title' => 'Main/local earth bar', 'reference' => '55.EARTH-BAR'],
            ['code' => 'EPM_LPS_AIR', 'root' => 'GROUNDING_LIGHTNING_SAFETY', 'title' => 'Lightning air terminal', 'reference' => '55.LPS-AIR'],
            ['code' => 'EPM_LPS_DOWN', 'root' => 'GROUNDING_LIGHTNING_SAFETY', 'title' => 'Lightning down conductor', 'reference' => '55.LPS-DOWN'],
            ['code' => 'EPM_TRAY', 'root' => 'DC_ELECTRICAL', 'title' => 'Cable tray/ladder', 'reference' => '55.TRAY'],
            ['code' => 'EPM_CONDUIT', 'root' => 'DC_ELECTRICAL', 'title' => 'Conduit/duct', 'reference' => '55.CONDUIT'],
            ['code' => 'EPM_GLAND', 'root' => 'DC_ELECTRICAL', 'title' => 'Cable gland', 'reference' => '55.GLAND'],
            ['code' => 'EPM_LUG', 'root' => 'DC_ELECTRICAL', 'title' => 'Cable lug/terminal', 'reference' => '55.LUG'],
            ['code' => 'EPM_LABEL', 'root' => 'GROUNDING_LIGHTNING_SAFETY', 'title' => 'Electrical safety/identification label', 'reference' => '55.LABEL'],
            ['code' => 'EPM_GENSET', 'root' => 'INVERTER_HYBRID_OFFGRID', 'title' => 'Generator set', 'reference' => '55.GENSET'],
            ['code' => 'EPM_FIRE_DET', 'root' => 'FIRE_HSE', 'title' => 'Fire/smoke/heat/off-gas detector', 'reference' => '55.FIRE-DET'],
            ['code' => 'EPM_FIRE_SUP', 'root' => 'FIRE_HSE', 'title' => 'Fire suppression system', 'reference' => '55.FIRE-SUP'],
            ['code' => 'EPM_HVAC_BESS', 'root' => 'FIRE_HSE', 'title' => 'BESS HVAC/liquid cooling', 'reference' => '55.HVAC-BESS'],
            ['code' => 'EPM_E_STOP', 'root' => 'FIRE_HSE', 'title' => 'Emergency stop', 'reference' => '55.E-STOP'],
        ];

        $rootIds = DB::table('material_categories')
            ->whereIn('code', collect($definitions)->pluck('root')->unique()->values()->all())
            ->pluck('id', 'code');

        foreach ($definitions as $index => $definition) {
            $rootId = $rootIds[$definition['root']] ?? null;
            if (! $rootId) {
                continue;
            }

            DB::table('material_categories')->updateOrInsert(
                ['code' => $definition['code']],
                [
                    'company_id' => null,
                    'parent_id' => $rootId,
                    'name' => $definition['title'],
                    'description' => 'Canonical engineering product class from NEXORIN MASTER ENGINEERING Section 55 ('.$definition['reference'].'). Material/MaterialSpec remains mutable until ProductRevision authority is implemented.',
                    'sort_order' => 1000 + (($index + 1) * 10),
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $codes = [
            'EPM_PV_MOD_STD',
            'EPM_PV_MOD_BIF',
            'EPM_PV_MOD_BIPV',
            'EPM_PV_OPT',
            'EPM_PV_RSD',
            'EPM_INV_STRING',
            'EPM_INV_CENTRAL',
            'EPM_INV_MICRO',
            'EPM_INV_HYBRID',
            'EPM_PCS_BESS',
            'EPM_BAT_CELL',
            'EPM_BAT_MODULE',
            'EPM_BAT_RACK',
            'EPM_BAT_CAB',
            'EPM_BAT_CONTAINER',
            'EPM_BMS',
            'EPM_EMS',
            'EPM_DC_CABLE',
            'EPM_DC_CONN',
            'EPM_DC_BRANCH',
            'EPM_DC_FUSE',
            'EPM_DC_FUSE_HOLDER',
            'EPM_DC_BREAKER',
            'EPM_DC_ISOLATOR',
            'EPM_DC_SPD',
            'EPM_DC_COMB',
            'EPM_AC_CABLE',
            'EPM_MCB',
            'EPM_MCCB',
            'EPM_ACB',
            'EPM_RCD',
            'EPM_RCBO',
            'EPM_AC_SPD',
            'EPM_AC_ISOLATOR',
            'EPM_CONTACTOR',
            'EPM_ATS',
            'EPM_STS',
            'EPM_BUSBAR',
            'EPM_BUSDUCT',
            'EPM_LV_PANEL',
            'EPM_TRANSFORMER',
            'EPM_MV_CABLE',
            'EPM_MV_RMU',
            'EPM_MV_SWG',
            'EPM_VCB',
            'EPM_MV_LBS',
            'EPM_RELAY',
            'EPM_CT_METER',
            'EPM_CT_PROT',
            'EPM_VT',
            'EPM_METER',
            'EPM_PQM',
            'EPM_EXPORT_CTRL',
            'EPM_PLC',
            'EPM_LOGGER',
            'EPM_NET_SW',
            'EPM_ROUTER',
            'EPM_UPS',
            'EPM_PYR',
            'EPM_TEMP_SENS',
            'EPM_ANEMO',
            'EPM_WEATHER_STN',
            'EPM_RAIL',
            'EPM_MID_CLAMP',
            'EPM_END_CLAMP',
            'EPM_ROOF_HOOK',
            'EPM_L_FOOT',
            'EPM_HANGER_BOLT',
            'EPM_SEAM_CLAMP',
            'EPM_BALLAST',
            'EPM_POST',
            'EPM_PILE',
            'EPM_TRACKER',
            'EPM_FOUND_CONC',
            'EPM_EARTH_COND',
            'EPM_EARTH_ROD',
            'EPM_EARTH_BAR',
            'EPM_LPS_AIR',
            'EPM_LPS_DOWN',
            'EPM_TRAY',
            'EPM_CONDUIT',
            'EPM_GLAND',
            'EPM_LUG',
            'EPM_LABEL',
            'EPM_GENSET',
            'EPM_FIRE_DET',
            'EPM_FIRE_SUP',
            'EPM_HVAC_BESS',
            'EPM_E_STOP',
        ];

        $used = DB::table('materials')->whereIn('material_category_id', function ($query) use ($codes): void {
            $query->select('id')->from('material_categories')->whereIn('code', $codes);
        })->pluck('material_category_id')->unique()->all();

        DB::table('material_categories')
            ->whereIn('code', $codes)
            ->when($used !== [], fn ($query) => $query->whereNotIn('id', $used))
            ->delete();

        if ($used !== []) {
            DB::table('material_categories')->whereIn('id', $used)->update(['is_active' => false, 'updated_at' => now()]);
        }
    }
};
