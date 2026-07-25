<?php

declare(strict_types=1);

use App\Services\Sld\SldPhysicalDcTopology;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, "[SLD-REF-001] FAIL · {$message}\n");
        exit(1);
    }
};

$service = new SldPhysicalDcTopology();
$strings = $service->normalizeStrings([
    ['string_index' => 1, 'tag' => 'STR-01', 'modules' => 20, 'mppt_index' => 1, 'mppt_label' => 'MPPT-01'],
    ['string_index' => 2, 'tag' => 'STR-02', 'modules' => 20, 'mppt_index' => 1, 'mppt_label' => 'MPPT-01'],
    ['string_index' => 3, 'tag' => 'STR-03', 'modules' => 20, 'mppt_index' => 2, 'mppt_label' => 'MPPT-02'],
], 1);

$assert(array_column($strings, 'node_id') === ['pv_string_001', 'pv_string_002', 'pv_string_003'], 'physical string node IDs must remain deterministic.');
$assert(array_column($strings, 'channel_index') === [1, 2, 3], 'isolator channels must follow calculation string order.');
$assert(array_column($strings, 'input_index') === [1, 2, 1], 'strings sharing an MPPT must receive deterministic input indices.');
$assert(data_get($strings, '0.inverter_ports.positive') === 'DC_MPPT1_POS', 'first MPPT/input must retain the canonical registry port.');
$assert(data_get($strings, '1.inverter_ports.positive') === 'DC_MPPT01_IN02_POS', 'second input on MPPT-01 must receive an explicit positive terminal.');
$assert(data_get($strings, '2.inverter_ports.negative') === 'DC_MPPT02_IN01_NEG', 'MPPT-02 input must receive an explicit negative terminal.');

$isolatorPorts = collect($service->isolatorPorts($strings));
$assert($isolatorPorts->count() === 13, 'three strings require twelve polarity terminals plus one SPD shunt tap.');
$assert($isolatorPorts->pluck('id')->contains('CH03_DC_OUT_NEG'), 'third string isolator negative output must exist.');
$assert($isolatorPorts->pluck('id')->contains('SPD_TAP'), 'DC isolator assembly must expose the controlled SPD shunt tap.');

$baseSourcePorts = [
    ['id' => 'DC_IN', 'network' => 'dc_power', 'direction' => 'input', 'side' => 'left'],
    ['id' => 'AC_OUT', 'network' => 'ac_power', 'direction' => 'output', 'side' => 'right'],
    ['id' => 'PE', 'network' => 'protective_earth', 'direction' => 'bidirectional', 'side' => 'bottom'],
    ['id' => 'COM', 'network' => 'communication', 'direction' => 'bidirectional', 'side' => 'bottom'],
    ['id' => 'BAT_DC', 'network' => 'battery_dc', 'direction' => 'bidirectional', 'side' => 'left'],
];
$inverterPorts = collect($service->inverterPorts($strings, 4, 'on_grid', $baseSourcePorts));
$inverterPortIds = $inverterPorts->pluck('id');

$assert(! $inverterPortIds->contains('DC_IN'), 'legacy aggregate DC input must not be exposed as an editable runtime terminal.');
$assert(! $inverterPortIds->contains('BAT_DC'), 'non-hybrid inverter must not expose a battery terminal.');
$assert($inverterPortIds->contains('DC_MPPT01_IN02_POS'), 'shared MPPT second input positive terminal must exist.');
$assert($inverterPortIds->contains('DC_MPPT03_IN01_NEG'), 'unused available MPPT must remain visible for engineering allocation.');
$assert($inverterPortIds->contains('DC_MPPT04_IN01_POS'), 'all datasheet/calculation MPPT positions must be represented.');
$assert($inverterPortIds->contains('AC_OUT') && $inverterPortIds->contains('PE') && $inverterPortIds->contains('COM'), 'base AC, PE, and communication ports must be retained.');
$assert($inverterPortIds->contains('CTRL'), 'active-power control port must remain available for zero-export control.');
$assert($service->nodeHeight(7) > $service->nodeHeight(3), 'equipment envelope height must scale with physical terminal count.');

fwrite(STDOUT, "[SLD-REF-001] PASS · deterministic physical strings, paired DC polarity terminals, shared-MPPT input indexing, dynamic inverter ports, and scalable equipment envelopes\n");
