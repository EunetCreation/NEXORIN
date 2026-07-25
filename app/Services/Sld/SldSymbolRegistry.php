<?php

namespace App\Services\Sld;

use RuntimeException;

final class SldSymbolRegistry
{
    /** @var array<string, mixed>|null */
    private ?array $registry = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->registry !== null) {
            return $this->registry;
        }

        $path = resource_path('data/sld-symbol-registry.json');
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("SLD symbol registry is missing or unreadable: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ($decoded['schema'] ?? null) !== 'nexorin.sld.symbol-registry.v1') {
            throw new RuntimeException('SLD symbol registry has an unsupported schema.');
        }

        $this->validateRegistry($decoded);

        return $this->registry = $decoded;
    }

    /** @param array<string, mixed> $registry */
    private function validateRegistry(array $registry): void
    {
        $symbols = $registry['symbols'] ?? null;
        $groups = $registry['groups'] ?? null;
        $networkClasses = $registry['network_classes'] ?? null;
        if (! is_array($symbols) || count($symbols) !== (int) ($registry['symbol_count'] ?? 0)) {
            throw new RuntimeException('SLD symbol registry count does not match its controlled metadata.');
        }
        if (! is_array($groups) || $groups === [] || ! is_array($networkClasses) || $networkClasses === []) {
            throw new RuntimeException('SLD symbol registry groups or network classes are missing.');
        }
        if (($registry['checkpoint'] ?? null) !== 'APP67L.1' || ($registry['version'] ?? null) !== '67L.1') {
            throw new RuntimeException('SLD symbol registry checkpoint or version is unsupported.');
        }

        $paletteCount = 0;
        $allowedSides = ['left', 'right', 'top', 'bottom'];
        $allowedDirections = ['input', 'output', 'bidirectional'];
        $allowedStatuses = ['iec_reference', 'iec_simplified', 'nexorin_assembly'];
        foreach ($symbols as $id => $symbol) {
            if (! is_array($symbol) || ($symbol['id'] ?? null) !== $id) {
                throw new RuntimeException("SLD symbol registry contains an invalid definition for {$id}.");
            }
            if (($symbol['palette'] ?? false) === true) {
                $paletteCount++;
            }
            if ((int) data_get($symbol, 'nominal_size.width') !== 144 || (int) data_get($symbol, 'nominal_size.height') !== 120) {
                throw new RuntimeException("SLD symbol {$id} does not use the 144 × 120 nominal frame.");
            }
            if (data_get($symbol, 'standard.certified') !== false || ! in_array(data_get($symbol, 'standard.status'), $allowedStatuses, true)) {
                throw new RuntimeException("SLD symbol {$id} contains invalid IEC traceability metadata.");
            }
            foreach (['ports', 'source_ports'] as $profileName) {
                $ports = $symbol[$profileName] ?? [];
                if ($profileName === 'ports' && (! is_array($ports) || $ports === [])) {
                    throw new RuntimeException("SLD symbol {$id} has no engineering ports.");
                }
                if (! is_array($ports)) {
                    throw new RuntimeException("SLD symbol {$id} has an invalid {$profileName} profile.");
                }
                $portIds = [];
                foreach ($ports as $port) {
                    $portId = is_array($port) ? (string) ($port['id'] ?? '') : '';
                    $x = is_array($port) ? data_get($port, 'anchor.x') : null;
                    $y = is_array($port) ? data_get($port, 'anchor.y') : null;
                    if ($portId === '' || isset($portIds[$portId])
                        || ! isset($networkClasses[(string) ($port['network'] ?? '')])
                        || ! in_array($port['side'] ?? null, $allowedSides, true)
                        || ! in_array($port['direction'] ?? null, $allowedDirections, true)
                        || ! is_numeric($x) || ! is_numeric($y)
                        || (float) $x < 0.0 || (float) $x > 1.0
                        || (float) $y < 0.0 || (float) $y > 1.0
                        || ! filled($port['terminal_class'] ?? null)) {
                        throw new RuntimeException("SLD symbol {$id} has an invalid port definition for {$portId}.");
                    }
                    $portIds[$portId] = true;
                }
            }
        }

        if ($paletteCount !== (int) ($registry['palette_count'] ?? 0)) {
            throw new RuntimeException('SLD symbol palette count does not match its controlled metadata.');
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function symbols(): array
    {
        return $this->all()['symbols'];
    }

    /** @return array<string, mixed>|null */
    public function symbol(string $id): ?array
    {
        $symbol = $this->symbols()[$id] ?? null;

        return is_array($symbol) ? $symbol : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function ports(string $id, bool $source = false): array
    {
        $symbol = $this->symbol($id);
        if ($symbol === null) {
            return [];
        }

        $ports = $source && is_array($symbol['source_ports'] ?? null)
            ? $symbol['source_ports']
            : ($symbol['ports'] ?? []);

        return array_values(array_filter(is_array($ports) ? $ports : [], 'is_array'));
    }

    /** @return array<string, array<string, mixed>> */
    public function paletteGroups(): array
    {
        $registry = $this->all();
        $groups = is_array($registry['groups'] ?? null) ? $registry['groups'] : [];
        $symbols = $this->symbols();
        $result = [];

        uasort($groups, static fn (array $a, array $b): int => ((int) ($a['order'] ?? 999)) <=> ((int) ($b['order'] ?? 999)));

        foreach ($groups as $groupId => $group) {
            if (($group['category'] ?? '') === 'internal') {
                continue;
            }

            $items = [];
            foreach (is_array($group['symbols'] ?? null) ? $group['symbols'] : [] as $symbolId) {
                $symbol = $symbols[$symbolId] ?? null;
                if (! is_array($symbol) || ($symbol['palette'] ?? false) !== true) {
                    continue;
                }
                $items[] = $symbol;
            }

            if ($items === []) {
                continue;
            }

            $copy = $group;
            $copy['id'] = (string) $groupId;
            $copy['symbols'] = $items;
            $result[(string) $groupId] = $copy;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function standardBasis(): array
    {
        $basis = $this->all()['standard_basis'] ?? [];

        return is_array($basis) ? $basis : [];
    }
}
