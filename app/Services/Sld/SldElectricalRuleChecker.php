<?php

namespace App\Services\Sld;

use App\Support\CalculationWarning;
use RuntimeException;

final class SldElectricalRuleChecker
{
    /** @var array<string, mixed>|null */
    private ?array $rules = null;

    public function __construct(private readonly SldSymbolRegistry $symbolRegistry)
    {
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $path = resource_path('data/sld-electrical-rules.json');
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("SLD electrical rule registry is missing or unreadable: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)
            || ($decoded['schema'] ?? null) !== 'nexorin.sld.electrical-rules.v1'
            || ($decoded['checkpoint'] ?? null) !== 'APP67I'
            || ($decoded['version'] ?? null) !== '67I.1') {
            throw new RuntimeException('SLD electrical rule registry has an unsupported schema or version.');
        }

        foreach (['severity', 'network_families', 'compatible_networks', 'power_networks', 'symbol_profiles', 'rule_codes'] as $key) {
            if (! is_array($decoded[$key] ?? null) || $decoded[$key] === []) {
                throw new RuntimeException("SLD electrical rule registry is missing {$key}.");
            }
        }

        foreach ($decoded['symbol_profiles'] as $symbolId => $profile) {
            if ($this->symbolRegistry->symbol((string) $symbolId) === null || ! is_array($profile)) {
                throw new RuntimeException("SLD electrical rule profile references an unknown symbol: {$symbolId}.");
            }
        }

        return $this->rules = $decoded;
    }

    /**
     * @param array<string, mixed> $graph
     * @return array{schema:string,checkpoint:string,version:string,status:string,blocking:bool,checked_at:string,summary:array<string,int|string|bool>,issues:array<int,array<string,mixed>>}
     */
    public function check(array $graph): array
    {
        $rules = $this->rules();
        $editor = is_array($graph['editor'] ?? null) ? $graph['editor'] : [];
        $excluded = array_fill_keys(array_map('strval', is_array($editor['excluded_nodes'] ?? null) ? $editor['excluded_nodes'] : []), true);

        $nodes = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null) || isset($excluded[(string) $node['id']])) {
                continue;
            }
            $node['_manual'] = false;
            $nodes[(string) $node['id']] = $node;
        }
        foreach (is_array($editor['manual_nodes'] ?? null) ? $editor['manual_nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) {
                continue;
            }
            $node['_manual'] = true;
            $nodes[(string) $node['id']] = $node;
        }

        $connections = [];
        foreach (is_array($graph['connections'] ?? null) ? $graph['connections'] : [] as $connection) {
            if (! is_array($connection)) {
                continue;
            }
            if (isset($excluded[(string) ($connection['from'] ?? '')]) || isset($excluded[(string) ($connection['to'] ?? '')])) {
                continue;
            }
            $connection['_manual'] = false;
            $connections[] = $connection;
        }
        foreach (is_array($editor['manual_connections'] ?? null) ? $editor['manual_connections'] : [] as $connection) {
            if (! is_array($connection)) {
                continue;
            }
            $connection['_manual'] = true;
            $connections[] = $connection;
        }

        $issues = [];
        $issueKeys = [];
        $addIssue = function (
            string $code,
            string $severity,
            string $title,
            string $message,
            string $recommendation,
            string $subjectType,
            string $subjectId,
            array $nodeIds = [],
            array $connectionIds = [],
            ?string $portId = null
        ) use (&$issues, &$issueKeys, $rules): void {
            $severity = isset($rules['severity'][$severity]) ? $severity : 'notice';
            $key = implode('|', [$code, $subjectType, $subjectId, $portId ?? '', implode(',', $nodeIds), implode(',', $connectionIds)]);
            if (isset($issueKeys[$key])) {
                return;
            }
            $issueKeys[$key] = true;
            $issues[] = [
                'id' => strtolower($code).'-'.substr(hash('sha256', $key), 0, 12),
                'code' => $code,
                'severity' => $severity,
                'blocking' => (bool) data_get($rules, "severity.{$severity}.blocking", false),
                'title' => $title,
                'message' => $message,
                'recommendation' => $recommendation,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'node_ids' => array_values(array_unique(array_filter(array_map('strval', $nodeIds)))),
                'connection_ids' => array_values(array_unique(array_filter(array_map('strval', $connectionIds)))),
                'port_id' => $portId,
                'source' => 'sld_electrical_rule_check',
            ];
        };

        $portsByNode = [];
        $symbolByNode = [];
        $tags = [];
        foreach ($nodes as $nodeId => $node) {
            $symbol = (string) ($node['symbol'] ?? $node['kind'] ?? '');
            $symbolByNode[$nodeId] = $symbol;
            $registry = $this->symbolRegistry->symbol($symbol);
            if ($registry === null) {
                $addIssue(
                    'E011', 'error', 'Unknown engineering symbol',
                    "Object {$nodeId} references symbol '{$symbol}', which is not present in the APP67H registry.",
                    'Replace the object with an approved NEXORIN engineering symbol before review or export.',
                    'node', $nodeId, [$nodeId]
                );
                $portsByNode[$nodeId] = [];
            } else {
                $explicit = is_array($node['ports'] ?? null) ? array_values(array_filter($node['ports'], 'is_array')) : [];
                $ports = $explicit !== [] ? $explicit : $this->symbolRegistry->ports($symbol);
                $portsByNode[$nodeId] = collect($ports)
                    ->filter(fn (mixed $port): bool => is_array($port) && filled($port['id'] ?? null))
                    ->mapWithKeys(fn (array $port): array => [(string) $port['id'] => $port])
                    ->all();
            }

            $tag = strtoupper(trim((string) ($node['tag_override'] ?? $node['tag'] ?? '')));
            if ($tag === '') {
                $addIssue(
                    'W004', 'warning', 'Equipment tag is missing',
                    "{$this->nodeLabel($node, $nodeId)} has no equipment tag.",
                    'Assign a unique tag before engineering review so schedules and drawing references remain traceable.',
                    'node', $nodeId, [$nodeId]
                );
            } else {
                $tags[$tag][] = $nodeId;
            }
        }

        foreach ($tags as $tag => $nodeIds) {
            if (count($nodeIds) < 2) {
                continue;
            }
            foreach ($nodeIds as $nodeId) {
                $addIssue(
                    'E007', 'error', 'Duplicate equipment tag',
                    "Equipment tag {$tag} is assigned to more than one object.",
                    'Assign a unique equipment tag to every object before review or export.',
                    'node', $nodeId, $nodeIds
                );
            }
        }

        $portUsage = [];
        $validPortUsage = [];
        $validNodeDegree = array_fill_keys(array_keys($nodes), 0);
        $validConnections = [];
        $connectionSignatures = [];

        foreach ($connections as $index => $connection) {
            $connectionId = (string) ($connection['id'] ?? $connection['wire_id'] ?? 'connection_'.($index + 1));
            $from = (string) ($connection['from'] ?? '');
            $to = (string) ($connection['to'] ?? '');
            $fromPortId = (string) ($connection['from_port'] ?? '');
            $toPortId = (string) ($connection['to_port'] ?? '');
            $network = $this->normalizeNetwork((string) ($connection['network'] ?? 'unclassified'));

            if ($from === '' || $to === '' || ! isset($nodes[$from]) || ! isset($nodes[$to])) {
                $addIssue(
                    'E001', 'error', 'Connection endpoint is missing',
                    "Connection {$connectionId} does not terminate at two available engineering objects.",
                    'Reconnect both ends to registered equipment ports or remove the invalid wire.',
                    'connection', $connectionId, array_filter([$from, $to]), [$connectionId]
                );
                continue;
            }

            if ($from === $to) {
                $addIssue(
                    'E009', 'error', 'Invalid electrical self-loop',
                    "Connection {$connectionId} starts and ends on {$this->nodeLabel($nodes[$from], $from)}.",
                    'Connect the circuit to a distinct destination object.',
                    'connection', $connectionId, [$from], [$connectionId]
                );
                continue;
            }

            if ($network === 'unclassified') {
                $addIssue(
                    'E008', 'error', 'Electrical network is unclassified',
                    "Connection {$connectionId} has no authoritative electrical network type.",
                    'Reconnect the wire through compatible APP67H ports so its network is derived automatically.',
                    'connection', $connectionId, [$from, $to], [$connectionId]
                );
            }

            $fromPort = $portsByNode[$from][$fromPortId] ?? null;
            $toPort = $portsByNode[$to][$toPortId] ?? null;
            if (! is_array($fromPort) || ! is_array($toPort)) {
                $missing = ! is_array($fromPort) ? "{$from}.{$fromPortId}" : "{$to}.{$toPortId}";
                $addIssue(
                    'E002', 'error', 'Unknown terminal reference',
                    "Connection {$connectionId} references terminal {$missing}, which is not available on the selected symbol profile.",
                    'Reconnect the wire to a visible APP67H terminal.',
                    'connection', $connectionId, [$from, $to], [$connectionId], ! is_array($fromPort) ? $fromPortId : $toPortId
                );
                continue;
            }

            $fromNetwork = $this->normalizeNetwork((string) ($fromPort['network'] ?? 'unclassified'));
            $toNetwork = $this->normalizeNetwork((string) ($toPort['network'] ?? 'unclassified'));
            $portUsage[$from][$fromPortId] = ($portUsage[$from][$fromPortId] ?? 0) + 1;
            $portUsage[$to][$toPortId] = ($portUsage[$to][$toPortId] ?? 0) + 1;

            $networkClassified = $network !== 'unclassified';
            $networkValid = $networkClassified
                && $this->networksCompatible($fromNetwork, $toNetwork)
                && $this->networksCompatible($fromNetwork, $network)
                && $this->networksCompatible($toNetwork, $network);
            if ($networkClassified && ! $networkValid) {
                $addIssue(
                    'E003', 'error', 'Incompatible electrical networks',
                    "Connection {$connectionId} attempts to join {$fromNetwork} to {$toNetwork} as {$network}.",
                    'Use ports from the same compatible electrical network. DC polarity, AC, neutral, PE, control, communication, and measurement circuits must remain separated.',
                    'connection', $connectionId, [$from, $to], [$connectionId]
                );
            }

            $directionValid = $this->canSend((string) ($fromPort['direction'] ?? 'bidirectional'))
                && $this->canReceive((string) ($toPort['direction'] ?? 'bidirectional'));
            if (! $directionValid) {
                $addIssue(
                    'E004', 'error', 'Terminal direction is invalid',
                    "Connection {$connectionId} does not follow the declared source and destination terminal directions.",
                    'Reverse the connection or choose an output-to-input terminal pair.',
                    'connection', $connectionId, [$from, $to], [$connectionId]
                );
            }

            $signature = implode('|', [$from, $fromPortId, $to, $toPortId, $network]);
            if (isset($connectionSignatures[$signature])) {
                $addIssue(
                    'W003', 'warning', 'Duplicate terminal connection',
                    "Connection {$connectionId} duplicates {$connectionSignatures[$signature]} between the same terminals.",
                    'Remove the duplicate wire or document a distinct parallel circuit with separate terminals and cable tags.',
                    'connection', $connectionId, [$from, $to], [$connectionSignatures[$signature], $connectionId]
                );
            } else {
                $connectionSignatures[$signature] = $connectionId;
            }

            if (($connection['_manual'] ?? false) === true && in_array($network, $rules['power_networks'], true)) {
                $cableType = trim((string) ($connection['cable_type'] ?? ''));
                $cableSize = trim((string) ($connection['cable_size'] ?? ''));
                if ($cableType === '' || $cableSize === '') {
                    $addIssue(
                        'W002', 'warning', 'Cable specification is incomplete',
                        "Power connection {$connectionId} is missing ".($cableType === '' && $cableSize === '' ? 'cable type and conductor size' : ($cableType === '' ? 'cable type' : 'conductor size')).'.',
                        'Record the cable family and conductor size before engineering review.',
                        'connection', $connectionId, [$from, $to], [$connectionId]
                    );
                }
                if (trim((string) ($connection['voltage_class'] ?? '')) === '') {
                    $addIssue(
                        'N001', 'notice', 'Cable voltage class is not recorded',
                        "Power connection {$connectionId} has no voltage-class metadata.",
                        'Record the applicable cable voltage class to improve schedule and procurement traceability.',
                        'connection', $connectionId, [$from, $to], [$connectionId]
                    );
                }
            }

            if ($networkValid && $directionValid) {
                $validPortUsage[$from][$fromPortId] = ($validPortUsage[$from][$fromPortId] ?? 0) + 1;
                $validPortUsage[$to][$toPortId] = ($validPortUsage[$to][$toPortId] ?? 0) + 1;
                $validNodeDegree[$from] = ($validNodeDegree[$from] ?? 0) + 1;
                $validNodeDegree[$to] = ($validNodeDegree[$to] ?? 0) + 1;
                $validConnections[] = [
                    'id' => $connectionId,
                    'from' => $from,
                    'to' => $to,
                    'from_port' => $fromPortId,
                    'to_port' => $toPortId,
                    'network' => $network,
                ];
            }
        }

        foreach ($portUsage as $nodeId => $ports) {
            foreach ($ports as $portId => $count) {
                $port = $portsByNode[$nodeId][$portId] ?? [];
                $limit = max(1, (int) ($port['max_connections'] ?? 1));
                if ($count <= $limit) {
                    continue;
                }
                $addIssue(
                    'E005', 'error', 'Terminal connection limit exceeded',
                    "Terminal {$nodeId}.{$portId} has {$count} connections but allows {$limit}.",
                    'Use a busbar, junction, combiner, or separate terminal that represents the intended branching topology.',
                    'node', $nodeId, [$nodeId], [], $portId
                );
            }
        }

        $profiles = is_array($rules['symbol_profiles'] ?? null) ? $rules['symbol_profiles'] : [];
        foreach ($nodes as $nodeId => $node) {
            $symbol = $symbolByNode[$nodeId] ?? '';
            $profile = is_array($profiles[$symbol] ?? null) ? $profiles[$symbol] : [];
            $requirements = is_array($profile['requirements'] ?? null) ? $profile['requirements'] : [];
            foreach ($requirements as $requirement) {
                if (! is_array($requirement)) {
                    continue;
                }
                $candidatePorts = array_values(array_filter(array_map('strval', is_array($requirement['ports'] ?? null) ? $requirement['ports'] : [])));
                if ($candidatePorts === []) {
                    continue;
                }
                $connected = array_map(fn (string $portId): bool => ($validPortUsage[$nodeId][$portId] ?? 0) > 0, $candidatePorts);
                $satisfied = ($requirement['mode'] ?? 'any') === 'all'
                    ? ! in_array(false, $connected, true)
                    : in_array(true, $connected, true);
                if ($satisfied) {
                    continue;
                }
                $label = (string) ($requirement['label'] ?? implode(' / ', $candidatePorts));
                $severity = (string) ($requirement['severity'] ?? 'error');
                $addIssue(
                    'E006', $severity, 'Required electrical connection is missing',
                    "{$this->nodeLabel($node, $nodeId)} has no valid connection for {$label}.",
                    'Connect the required terminal set using compatible APP67H ports before review or controlled export.',
                    'node', $nodeId, [$nodeId], [], $candidatePorts[0]
                );
            }

            if (($node['_manual'] ?? false) === true) {
                foreach (is_array($profile['manual_properties'] ?? null) ? $profile['manual_properties'] : [] as $property) {
                    if (! is_array($property) || ! filled($property['field'] ?? null)) {
                        continue;
                    }
                    $field = (string) $property['field'];
                    $value = $node[$field] ?? null;
                    $missing = $value === null || $value === '' || (is_numeric($value) && (float) $value <= 0);
                    if (! $missing) {
                        continue;
                    }
                    $label = (string) ($property['label'] ?? str_replace('_', ' ', $field));
                    $addIssue(
                        'W006', (string) ($property['severity'] ?? 'warning'), 'Manual protection rating is incomplete',
                        "{$this->nodeLabel($node, $nodeId)} has no valid {$label}.",
                        'Record the manufacturer-backed protection rating before engineering review or procurement release.',
                        'node', $nodeId, [$nodeId]
                    );
                }
            }

            if (($profile['grounding_expected'] ?? false) === true) {
                $pePorts = collect($portsByNode[$nodeId] ?? [])
                    ->filter(fn (array $port): bool => in_array($this->normalizeNetwork((string) ($port['network'] ?? '')), ['protective_earth', 'functional_earth'], true))
                    ->keys()
                    ->map(fn (mixed $id): string => (string) $id)
                    ->all();
                $grounded = collect($pePorts)->contains(fn (string $portId): bool => ($validPortUsage[$nodeId][$portId] ?? 0) > 0);
                if ($pePorts !== [] && ! $grounded) {
                    $addIssue(
                        'W001', 'warning', 'Protective-earth connection is missing',
                        "{$this->nodeLabel($node, $nodeId)} exposes an earthing terminal but no valid PE/FE connection is shown.",
                        'Connect the equipment earthing terminal to the protective or functional earthing network and verify the project earthing design.',
                        'node', $nodeId, [$nodeId], [], $pePorts[0]
                    );
                }
            }

            if (($node['_manual'] ?? false) === true && ($validNodeDegree[$nodeId] ?? 0) === 0 && $requirements === []) {
                $addIssue(
                    'W005', 'warning', 'Engineering object is isolated',
                    "{$this->nodeLabel($node, $nodeId)} is not connected to the drawing network.",
                    'Connect the object, mark it as future/existing with a clear note, or remove it from the active SLD.',
                    'node', $nodeId, [$nodeId]
                );
            }
        }

        foreach ($validConnections as $connection) {
            $fromSymbol = $symbolByNode[$connection['from']] ?? '';
            $toSymbol = $symbolByNode[$connection['to']] ?? '';
            if (in_array($fromSymbol, ['pv_module', 'pv_string', 'pv_array', 'sub_array'], true)
                && in_array($toSymbol, ['inverter', 'hybrid_inverter', 'offgrid_inverter', 'central_inverter'], true)) {
                $addIssue(
                    'E010', 'error', 'PV source bypasses DC protection',
                    "Connection {$connection['id']} routes a PV source directly to an inverter without a represented DC protection boundary.",
                    'Insert the applicable DC isolator, fuse, breaker, SPD, combiner, or documented protection assembly between the PV source and inverter.',
                    'connection', $connection['id'], [$connection['from'], $connection['to']], [$connection['id']]
                );
            }
            if (in_array($fromSymbol, ['inverter', 'hybrid_inverter', 'offgrid_inverter', 'central_inverter', 'microinverter'], true)
                && in_array($toSymbol, ['meter', 'app_pln', 'pcc', 'grid'], true)) {
                $addIssue(
                    'E012', 'error', 'Inverter AC output bypasses AC protection',
                    "Connection {$connection['id']} routes inverter AC output directly to the metering or utility boundary.",
                    'Insert and document the applicable AC isolator, breaker, RCD where required, and surge-protection boundary before the metering or utility connection.',
                    'connection', $connection['id'], [$connection['from'], $connection['to']], [$connection['id']]
                );
            }
        }

        usort($issues, function (array $a, array $b) use ($rules): int {
            $rankA = (int) data_get($rules, 'severity.'.($a['severity'] ?? 'info').'.rank', 0);
            $rankB = (int) data_get($rules, 'severity.'.($b['severity'] ?? 'info').'.rank', 0);
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            return [
                (string) ($a['code'] ?? ''),
                (string) ($a['subject_id'] ?? ''),
            ] <=> [
                (string) ($b['code'] ?? ''),
                (string) ($b['subject_id'] ?? ''),
            ];
        });

        $counts = ['error' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0];
        foreach ($issues as $issue) {
            $severity = (string) ($issue['severity'] ?? 'info');
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }
        $status = $counts['error'] > 0 ? 'fail' : ($counts['warning'] > 0 ? 'review' : 'pass');

        return [
            'schema' => 'nexorin.sld.electrical-validation.v1',
            'checkpoint' => 'APP67I',
            'version' => (string) ($rules['version'] ?? '67I.1'),
            'status' => $status,
            'blocking' => $counts['error'] > 0,
            'checked_at' => now()->toISOString(),
            'summary' => [
                'status' => $status,
                'blocking' => $counts['error'] > 0,
                'total' => count($issues),
                'error' => $counts['error'],
                'warning' => $counts['warning'],
                'notice' => $counts['notice'],
                'info' => $counts['info'],
                'nodes_checked' => count($nodes),
                'connections_checked' => count($connections),
            ],
            'issues' => $issues,
        ];
    }

    /** @param array<string, mixed> $validation @return array<int, array<string, string>> */
    public function toRevisionWarnings(array $validation): array
    {
        $warnings = [];
        foreach (is_array($validation['issues'] ?? null) ? $validation['issues'] : [] as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $severity = match ($issue['severity'] ?? 'info') {
                'error' => 'critical',
                'warning' => 'warning',
                'notice' => 'caution',
                default => 'info',
            };
            $warnings[] = CalculationWarning::make(
                'SLD_'.(string) ($issue['code'] ?? 'RULE'),
                $severity,
                (string) ($issue['title'] ?? 'SLD electrical rule notice'),
                (string) ($issue['message'] ?? ''),
                (string) ($issue['recommendation'] ?? ''),
                'sld_electrical_rule_check'
            );
        }

        return CalculationWarning::unique($warnings);
    }

    /** @param array<int, mixed> $existing @param array<string, mixed> $validation @return array<int, array<string, string>> */
    public function mergeRevisionWarnings(array $existing, array $validation): array
    {
        $preserved = collect(CalculationWarning::normalizeList($existing, 'sld'))
            ->reject(fn (array $warning): bool => ($warning['source'] ?? null) === 'sld_electrical_rule_check')
            ->values()
            ->all();

        return CalculationWarning::unique([...$preserved, ...$this->toRevisionWarnings($validation)]);
    }

    private function normalizeNetwork(string $network): string
    {
        $key = strtolower(trim(str_replace(['-', ' '], '_', $network)));
        return match ($key) {
            'dc+', 'positive' => 'dc_positive',
            'dc-', 'negative' => 'dc_negative',
            'ac' => 'ac_power',
            'pe', 'earth' => 'protective_earth',
            'fe' => 'functional_earth',
            'comm' => 'communication',
            default => isset($this->rules()['network_families'][$key]) ? $key : 'unclassified',
        };
    }

    private function networksCompatible(string $a, string $b): bool
    {
        if ($a === $b) {
            return $a !== 'unclassified';
        }
        $compatible = is_array($this->rules()['compatible_networks'][$a] ?? null)
            ? $this->rules()['compatible_networks'][$a]
            : [];
        return in_array($b, $compatible, true);
    }

    private function canSend(string $direction): bool
    {
        return in_array($direction, ['output', 'bidirectional'], true);
    }

    private function canReceive(string $direction): bool
    {
        return in_array($direction, ['input', 'bidirectional'], true);
    }

    /** @param array<string, mixed> $node */
    private function nodeLabel(array $node, string $fallback): string
    {
        $tag = trim((string) ($node['tag_override'] ?? $node['tag'] ?? ''));
        $title = trim((string) ($node['title'] ?? ''));
        if ($tag !== '' && $title !== '') {
            return "{$tag} · {$title}";
        }
        return $tag !== '' ? $tag : ($title !== '' ? $title : $fallback);
    }
}
