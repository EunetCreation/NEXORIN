<?php

namespace App\Services\Catalog\Datasheet;

class DatasheetFieldAliasRegistry
{
    /** @param array<string,mixed> $definition */
    public function aliases(string $key, array $definition): array
    {
        $label = (string) ($definition['label'] ?? str($key)->replace('_', ' ')->title());
        $aliases = [
            $label,
            str_replace('_', ' ', $key),
        ];

        $known = [
            'module_power_wp' => ['peak power watts pmax', 'peak power', 'maximum power pmax', 'pmax', 'rated power', 'nominal maximum power', 'module power'],
            'module_efficiency_percent' => ['module efficiency', 'efficiency at stc', 'module efficiency stc'],
            'voc' => ['voc', 'open circuit voltage', 'open-circuit voltage', 'v oc'],
            'vmp' => ['vmp', 'vmpp', 'maximum power voltage', 'voltage at maximum power', 'v mpp'],
            'isc' => ['isc', 'short circuit current', 'short-circuit current', 'i sc'],
            'imp' => ['imp', 'impp', 'maximum power current', 'current at maximum power', 'i mpp'],
            'temp_coeff_voc' => ['temperature coefficient voc', 'temperature coefficient of voc', 'voc temperature coefficient', 'temp coefficient voc', 'temp coeff voc'],
            'temp_coeff_pmax' => ['temperature coefficient pmax', 'temperature coefficient of pmax', 'pmax temperature coefficient', 'temp coefficient pmax', 'temp coeff pmax'],
            'max_system_voltage' => ['maximum system voltage', 'max system voltage', 'maximum permissible system voltage'],
            'max_dc_voltage' => ['maximum dc voltage', 'max dc input voltage', 'maximum input voltage', 'max input voltage'],
            'rated_ac_power_kw' => ['rated ac power', 'nominal ac power', 'ac output power', 'rated output power'],
            'max_dc_power_kwp' => ['maximum pv input power', 'max dc power', 'maximum dc input power'],
            'startup_voltage' => ['startup voltage', 'start-up voltage', 'starting voltage'],
            'mppt_voltage_min' => ['minimum mppt voltage', 'mppt voltage minimum', 'mppt range min'],
            'mppt_voltage_max' => ['maximum mppt voltage', 'mppt voltage maximum', 'mppt range max'],
            'mppt_count' => ['number of mppt', 'mppt trackers', 'mppt count'],
            'strings_per_mppt' => ['strings per mppt', 'inputs per mppt', 'string inputs per mppt'],
            'rated_ac_voltage' => ['rated ac voltage', 'nominal output voltage', 'ac nominal voltage'],
            'rated_ac_current_a' => ['rated ac current', 'nominal output current', 'ac rated current'],
            'efficiency_percent' => ['maximum efficiency', 'max efficiency', 'efficiency'],
            'battery_chemistry' => ['battery chemistry', 'cell chemistry', 'chemistry'],
            'nominal_voltage_v' => ['nominal voltage', 'rated battery voltage'],
            'capacity_ah' => ['nominal capacity', 'rated capacity', 'battery capacity ah'],
            'capacity_kwh' => ['nominal energy', 'rated energy', 'energy capacity', 'battery energy'],
            'gross_capacity_kwh' => ['gross energy', 'gross capacity', 'total energy'],
            'usable_capacity_kwh' => ['usable energy', 'usable capacity', 'available energy'],
            'dod_percent' => ['depth of discharge', 'dod', 'usable dod'],
            'round_trip_efficiency_percent' => ['round trip efficiency', 'round-trip efficiency', 'rte'],
            'cycle_life' => ['cycle life', 'cycles', 'cycle durability'],
            'max_charge_current_a' => ['maximum charge current', 'max charge current', 'charging current max'],
            'max_discharge_current_a' => ['maximum discharge current', 'max discharge current', 'discharging current max'],
            'c_rate' => ['c-rate', 'c rate', 'charge discharge rate'],
            'parallel_limit' => ['maximum parallel units', 'parallel limit', 'max parallel modules'],
            'communication_protocol' => ['communication protocol', 'communication interface', 'communications'],
            'operating_temperature' => ['operating temperature range', 'operating temperature', 'working temperature'],
            'ip_rating' => ['ip rating', 'ingress protection', 'protection degree', 'degree of protection'],
            'warranty_years' => ['warranty', 'product warranty', 'standard warranty'],
            'product_warranty_years' => ['product warranty', 'material warranty'],
            'performance_warranty_years' => ['performance warranty', 'linear performance warranty', 'power warranty'],
            'spd_type' => ['spd classification', 'spd type', 'surge protection class', 'type of spd'],
            'rated_voltage' => ['rated voltage', 'nominal voltage'],
            'rated_current' => ['rated current', 'nominal current'],
            'continuous_operating_voltage' => ['maximum continuous operating voltage', 'continuous operating voltage', 'uc', 'mcov'],
            'uc_voltage' => ['maximum continuous operating voltage', 'continuous operating voltage', 'uc', 'mcov'],
            'voltage_protection_level' => ['voltage protection level', 'up', 'protection level'],
            'up_voltage_kv' => ['voltage protection level', 'up', 'protection level'],
            'nominal_discharge_current_ka' => ['nominal discharge current', 'in', '8/20 current'],
            'in_current' => ['nominal discharge current', 'in', '8/20 current'],
            'max_discharge_current_ka' => ['maximum discharge current', 'imax', 'max discharge current'],
            'imax_current' => ['maximum discharge current', 'imax', 'max discharge current'],
            'short_circuit_withstand_ka' => ['iscpv', 'pv short circuit current rating', 'short circuit current rating'],
            'response_time_ns' => ['response time', 'surge response time'],
            'pole_count' => ['pole count', 'number of poles', 'poles'],
            'applicable_standard' => ['applicable standard', 'compliance standard', 'standard'],
            'standard' => ['applicable standard', 'standard', 'compliance'],
            'length_mm' => ['module length', 'length'],
            'width_mm' => ['module width', 'width'],
            'weight_kg' => ['module weight', 'net weight', 'weight'],
            'cell_count' => ['cell count', 'number of cells', 'cells'],
            'cell_type' => ['cell technology', 'cell type', 'cell material'],
        ];

        $aliases = array_merge($aliases, $known[$key] ?? []);

        return collect($aliases)
            ->map(fn (string $alias): string => $this->normalise($alias))
            ->filter(fn (string $alias): bool => strlen($alias) >= 2)
            ->unique()
            ->sortByDesc('strlen')
            ->values()
            ->all();
    }

    public function normalise(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['–', '—', '−', '_'], ['-', '-', '-', ' '], $value);
        $value = preg_replace('/[^a-z0-9%°+\/\.\- ]+/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
}
