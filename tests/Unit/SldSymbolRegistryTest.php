<?php

namespace Tests\Unit;

use App\Services\Sld\SldSymbolRegistry;
use Tests\TestCase;

class SldSymbolRegistryTest extends TestCase
{
    public function test_registry_has_one_canonical_definition_for_every_symbol(): void
    {
        $registry = app(SldSymbolRegistry::class);
        $symbols = $registry->symbols();

        self::assertCount(61, $symbols);
        self::assertSame('67L.1', $registry->all()['version']);
        self::assertCount(60, array_filter($symbols, static fn (array $symbol): bool => ($symbol['palette'] ?? false) === true));

        foreach ($symbols as $id => $symbol) {
            self::assertSame($id, $symbol['id']);
            self::assertSame(['width' => 144, 'height' => 120], $symbol['nominal_size']);
            self::assertFalse((bool) data_get($symbol, 'standard.certified', true));
            self::assertNotEmpty(data_get($symbol, 'standard.status'));
            self::assertNotEmpty($symbol['ports']);
        }
    }

    public function test_all_ports_have_stable_terminal_metadata_and_normalized_anchors(): void
    {
        foreach (app(SldSymbolRegistry::class)->symbols() as $symbol) {
            $profiles = [$symbol['ports'] ?? [], $symbol['source_ports'] ?? []];
            foreach ($profiles as $ports) {
                foreach ($ports as $port) {
                    self::assertNotEmpty($port['id']);
                    self::assertContains($port['direction'], ['input', 'output', 'bidirectional']);
                    self::assertContains($port['side'], ['left', 'right', 'top', 'bottom']);
                    self::assertIsNumeric(data_get($port, 'anchor.x'));
                    self::assertIsNumeric(data_get($port, 'anchor.y'));
                    self::assertGreaterThanOrEqual(0.0, (float) data_get($port, 'anchor.x'));
                    self::assertLessThanOrEqual(1.0, (float) data_get($port, 'anchor.x'));
                    self::assertGreaterThanOrEqual(0.0, (float) data_get($port, 'anchor.y'));
                    self::assertLessThanOrEqual(1.0, (float) data_get($port, 'anchor.y'));
                    self::assertNotEmpty($port['terminal_class']);
                }
            }
        }
    }

    public function test_instrument_transformers_and_distribution_neutral_are_engineered_explicitly(): void
    {
        $registry = app(SldSymbolRegistry::class);

        self::assertSame(['P1', 'P2', 'S1', 'S2'], array_column($registry->ports('ct'), 'id'));
        self::assertSame(['PRIMARY', 'SECONDARY'], array_column($registry->ports('pt'), 'id'));
        self::assertContains('N', array_column($registry->ports('panel'), 'id'));
        self::assertContains('N', array_column($registry->ports('main_panel'), 'id'));
        self::assertSame([0], data_get($registry->symbol('ground'), 'transforms.rotations'));
        self::assertFalse((bool) data_get($registry->symbol('ground'), 'transforms.mirror_horizontal'));
    }
    public function test_professional_plts_individual_components_have_explicit_port_semantics(): void
    {
        $registry = app(SldSymbolRegistry::class);

        self::assertSame(['DC_IN', 'DC_OUT'], array_column($registry->ports('dc_isolator'), 'id'));
        self::assertSame(['DC_TAP', 'PE'], array_column($registry->ports('dc_spd'), 'id'));
        self::assertSame(['AC_IN', 'AC_OUT'], array_column($registry->ports('ac_breaker'), 'id'));
        self::assertSame(['AC_IN', 'AC_OUT'], array_column($registry->ports('ac_isolator'), 'id'));
        self::assertSame(['AC_TAP', 'PE'], array_column($registry->ports('ac_spd'), 'id'));
        self::assertSame(['PE_IN', 'PE_OUT'], array_column($registry->ports('pe_bus'), 'id'));

        self::assertSame('dc_power', data_get($registry->symbol('dc_spd'), 'ports.0.network'));
        self::assertSame('protective_earth', data_get($registry->symbol('dc_spd'), 'ports.1.network'));
        self::assertSame('communication', data_get($registry->symbol('data_logger'), 'ports.0.network'));
        self::assertSame('control', data_get($registry->symbol('plant_controller'), 'ports.2.network'));

        foreach (['dc_isolator', 'dc_spd', 'ac_breaker', 'ac_isolator', 'ac_spd', 'pe_bus', 'data_logger', 'plant_controller'] as $symbolId) {
            self::assertTrue((bool) data_get($registry->symbol($symbolId), 'palette'));
            self::assertNotEmpty(data_get($registry->symbol($symbolId), 'aliases'));
            self::assertSame('Reference drawing form', data_get($registry->symbol($symbolId), 'standard.status_label'));
        }
    }

}
