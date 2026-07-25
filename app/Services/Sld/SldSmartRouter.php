<?php

namespace App\Services\Sld;

final class SldSmartRouter
{
    public const VERSION = '67M.2';

    public const CONTRACT = 'deterministic-orthogonal-channel-v2';

    public const ENGINE = 'orthogonal-channel-a-star';

    private const EPSILON = 0.001;

    /** @var array<string,array{class:string,lane:int,priority:int}> */
    private const NETWORK_PROFILE = [
        'dc_positive' => ['class' => 'dc', 'lane' => -3, 'priority' => 10],
        'dc_negative' => ['class' => 'dc', 'lane' => -2, 'priority' => 11],
        'dc_power' => ['class' => 'dc', 'lane' => -2, 'priority' => 12],
        'battery_positive' => ['class' => 'dc', 'lane' => -3, 'priority' => 13],
        'battery_negative' => ['class' => 'dc', 'lane' => -2, 'priority' => 14],
        'battery_dc' => ['class' => 'dc', 'lane' => -2, 'priority' => 15],
        'ac_power' => ['class' => 'ac', 'lane' => 0, 'priority' => 20],
        'neutral' => ['class' => 'ac', 'lane' => 1, 'priority' => 21],
        'protective_earth' => ['class' => 'earth', 'lane' => 2, 'priority' => 30],
        'functional_earth' => ['class' => 'earth', 'lane' => 2, 'priority' => 31],
        'communication' => ['class' => 'signal', 'lane' => 4, 'priority' => 40],
        'control' => ['class' => 'signal', 'lane' => 5, 'priority' => 41],
        'measurement' => ['class' => 'signal', 'lane' => 6, 'priority' => 42],
        'unclassified' => ['class' => 'unclassified', 'lane' => 0, 'priority' => 90],
    ];

    /**
     * Deterministic obstacle-aware orthogonal router for paper-space export.
     *
     * @param array{x:float|int,y:float|int,side?:string,id?:string} $source
     * @param array{x:float|int,y:float|int,side?:string,id?:string} $target
     * @param array<int,array<string,mixed>> $obstacles
     * @param array<int,array<string,mixed>> $existingSegments
     * @return array<string,mixed>
     */
    public function route(
        array $source,
        array $target,
        array $obstacles = [],
        array $existingSegments = [],
        string $network = 'unclassified',
        float $clearance = 18.0,
        float $channelSpacing = 12.0,
        float $gridSize = 10.0,
    ): array {
        $gridSize = max(2.0, $gridSize);
        $clearance = max(4.0, $clearance);
        $channelSpacing = max(6.0, $channelSpacing);
        $network = $this->normalizeNetwork($network);
        $sourcePoint = $this->point($source);
        $targetPoint = $this->point($target);
        $sourceSide = $this->side((string) ($source['side'] ?? 'right'));
        $targetSide = $this->side((string) ($target['side'] ?? 'left'));
        $leadDistance = max(24.0, $clearance + 16.0);
        $sourceLead = $this->leadPoint($sourcePoint, $sourceSide, $leadDistance, $gridSize);
        $targetLead = $this->leadPoint($targetPoint, $targetSide, $leadDistance, $gridSize);
        $sourceId = (string) ($source['id'] ?? '');
        $targetId = (string) ($target['id'] ?? '');

        $expanded = [];
        foreach ($obstacles as $index => $raw) {
            if (! is_array($raw)) continue;
            $rect = $this->rect($raw, 'obstacle-'.$index);
            if ($rect['id'] === $sourceId || $rect['id'] === $targetId) continue;
            $expanded[] = $this->expand($rect, $clearance);
        }

        $segments = [];
        foreach ($existingSegments as $segment) {
            if (! is_array($segment) || ! is_array($segment['a'] ?? null) || ! is_array($segment['b'] ?? null)) continue;
            $a = $this->point($segment['a']);
            $b = $this->point($segment['b']);
            if (! $this->orthogonal($a, $b)) continue;
            $segments[] = [
                'a' => $a,
                'b' => $b,
                'network' => $this->normalizeNetwork((string) ($segment['network'] ?? 'unclassified')),
                'wireId' => (string) ($segment['wireId'] ?? ''),
            ];
        }

        $laneOffset = $this->networkLane($network) * $channelSpacing;
        $preferred = $this->preferredChannel($sourceLead, $targetLead, $sourceSide, $targetSide, $laneOffset, $gridSize);
        $xs = [$sourceLead['x'], $targetLead['x'], $sourcePoint['x'], $targetPoint['x'], $sourceLead['x'] + $laneOffset, $targetLead['x'] + $laneOffset];
        $ys = [$sourceLead['y'], $targetLead['y'], $sourcePoint['y'], $targetPoint['y'], $sourceLead['y'] + $laneOffset, $targetLead['y'] + $laneOffset];
        if ($preferred['axis'] === 'horizontal') $ys[] = $preferred['value']; else $xs[] = $preferred['value'];

        foreach ($expanded as $rect) {
            array_push($xs,
                $this->snap($rect['left'] - $channelSpacing, $gridSize),
                $this->snap($rect['left'], $gridSize),
                $this->snap($rect['right'], $gridSize),
                $this->snap($rect['right'] + $channelSpacing, $gridSize),
            );
            array_push($ys,
                $this->snap($rect['top'] - $channelSpacing, $gridSize),
                $this->snap($rect['top'], $gridSize),
                $this->snap($rect['bottom'], $gridSize),
                $this->snap($rect['bottom'] + $channelSpacing, $gridSize),
            );
        }
        foreach ($segments as $segment) {
            foreach ([$segment['a']['x'], $segment['b']['x']] as $x) {
                $xs[] = $this->snap($x, $gridSize);
                $xs[] = $this->snap($x - $channelSpacing, $gridSize);
                $xs[] = $this->snap($x + $channelSpacing, $gridSize);
            }
            foreach ([$segment['a']['y'], $segment['b']['y']] as $y) {
                $ys[] = $this->snap($y, $gridSize);
                $ys[] = $this->snap($y - $channelSpacing, $gridSize);
                $ys[] = $this->snap($y + $channelSpacing, $gridSize);
            }
        }

        $xAnchors = [$sourceLead['x'], $targetLead['x'], $preferred['axis'] === 'vertical' ? $preferred['value'] : $sourceLead['x']];
        $yAnchors = [$sourceLead['y'], $targetLead['y'], $preferred['axis'] === 'horizontal' ? $preferred['value'] : $sourceLead['y']];
        $xs = $this->reduceChannels($this->uniqueSorted($xs), $xAnchors, 76);
        $ys = $this->reduceChannels($this->uniqueSorted($ys), $yAnchors, 76);

        $nodes = [];
        $indexByKey = [];
        foreach ($ys as $y) {
            foreach ($xs as $x) {
                $point = ['x' => $x, 'y' => $y];
                $inside = false;
                foreach ($expanded as $rect) {
                    if ($this->inside($point, $rect)) { $inside = true; break; }
                }
                if ($inside) continue;
                $index = count($nodes);
                $nodes[] = $point;
                $indexByKey[$this->key($point)] = $index;
            }
        }

        $sourceIndex = $indexByKey[$this->key($sourceLead)] ?? null;
        $targetIndex = $indexByKey[$this->key($targetLead)] ?? null;
        if ($sourceIndex === null || $targetIndex === null) {
            return $this->finish(
                $sourcePoint,
                $targetPoint,
                $this->fallback($sourceLead, $targetLead, $expanded, $gridSize),
                'perimeter-fallback',
                $expanded,
                $segments,
                $network,
                $channelSpacing,
                $sourceSide,
                $targetSide,
                $preferred,
                $clearance,
            );
        }

        $adjacency = array_fill(0, count($nodes), []);
        $connect = function (?int $aIndex, ?int $bIndex) use (&$adjacency, $nodes, $expanded): void {
            if ($aIndex === null || $bIndex === null || $aIndex === $bIndex) return;
            if ($this->blocked($nodes[$aIndex], $nodes[$bIndex], $expanded)) return;
            $adjacency[$aIndex][] = $bIndex;
            $adjacency[$bIndex][] = $aIndex;
        };
        foreach ($ys as $y) {
            $previous = null;
            foreach ($xs as $x) {
                $index = $indexByKey[$this->key(['x' => $x, 'y' => $y])] ?? null;
                if ($index === null) continue;
                $connect($previous, $index);
                $previous = $index;
            }
        }
        foreach ($xs as $x) {
            $previous = null;
            foreach ($ys as $y) {
                $index = $indexByKey[$this->key(['x' => $x, 'y' => $y])] ?? null;
                if ($index === null) continue;
                $connect($previous, $index);
                $previous = $index;
            }
        }

        $open = [];
        $best = [];
        $parent = [];
        $push = static function (int $node, string $direction, float $g, float $f, ?string $previousKey = null) use (&$open, &$best, &$parent): void {
            $key = $node.'|'.$direction;
            if ($g >= ($best[$key] ?? INF) - self::EPSILON) return;
            $best[$key] = $g;
            if ($previousKey !== null) $parent[$key] = $previousKey;
            $open[] = ['node' => $node, 'direction' => $direction, 'g' => $g, 'f' => $f, 'key' => $key];
        };
        $heuristic = static fn (array $point): float => abs($point['x'] - $targetLead['x']) + abs($point['y'] - $targetLead['y']);
        $push($sourceIndex, 'none', 0.0, $heuristic($sourceLead));
        $final = null;

        while ($open !== []) {
            usort($open, static fn (array $a, array $b): int => [$a['f'], $a['g'], $a['node'], $a['direction']] <=> [$b['f'], $b['g'], $b['node'], $b['direction']]);
            $current = array_shift($open);
            if ($current['g'] > ($best[$current['key']] ?? INF) + self::EPSILON) continue;
            if ($current['node'] === $targetIndex) { $final = $current; break; }
            $point = $nodes[$current['node']];
            foreach ($adjacency[$current['node']] as $nextNode) {
                $next = $nodes[$nextNode];
                $direction = abs($point['x'] - $next['x']) < self::EPSILON ? 'vertical' : 'horizontal';
                $extra = abs($point['x'] - $next['x']) + abs($point['y'] - $next['y']);
                if ($current['direction'] !== 'none' && $current['direction'] !== $direction) $extra += 34.0;
                foreach ($segments as $segment) {
                    if ($this->crosses($point, $next, $segment['a'], $segment['b'])) {
                        $extra += 360.0 * $this->crossingWeight($network, $segment['network']);
                    }
                    $proximity = $this->parallelProximity($point, $next, $segment['a'], $segment['b'], $channelSpacing);
                    if ($proximity > self::EPSILON) {
                        $extra += $proximity * $this->proximityWeight($network, $segment['network']);
                    }
                }
                $coordinate = $direction === 'horizontal' ? $point['y'] : $point['x'];
                $extra += abs($coordinate - $preferred['value']) * ($direction === $preferred['axis'] ? .032 : .006);
                $nextG = $current['g'] + $extra;
                $push($nextNode, $direction, $nextG, $nextG + $heuristic($next), $current['key']);
            }
        }

        if ($final === null) {
            return $this->finish(
                $sourcePoint,
                $targetPoint,
                $this->fallback($sourceLead, $targetLead, $expanded, $gridSize),
                'perimeter-fallback',
                $expanded,
                $segments,
                $network,
                $channelSpacing,
                $sourceSide,
                $targetSide,
                $preferred,
                $clearance,
            );
        }

        $reversed = [];
        $key = $final['key'];
        while ($key !== null) {
            $nodeIndex = (int) explode('|', $key, 2)[0];
            $reversed[] = $nodes[$nodeIndex];
            $key = $parent[$key] ?? null;
        }

        return $this->finish(
            $sourcePoint,
            $targetPoint,
            $this->compress(array_reverse($reversed)),
            'orthogonal-channel-a-star',
            $expanded,
            $segments,
            $network,
            $channelSpacing,
            $sourceSide,
            $targetSide,
            $preferred,
            $clearance,
        );
    }

    /**
     * @param array<int,array<string,mixed>> $requests
     * @param array<int,array<string,mixed>> $existingSegments
     * @return array<string,mixed>
     */
    public function routeBatch(array $requests, array $existingSegments = []): array
    {
        usort($requests, function (array $a, array $b): int {
            $priority = self::networkPriority((string) ($a['network'] ?? 'unclassified')) <=> self::networkPriority((string) ($b['network'] ?? 'unclassified'));
            return $priority ?: strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        $segments = [];
        foreach ($existingSegments as $segment) {
            if (! is_array($segment) || ! is_array($segment['a'] ?? null) || ! is_array($segment['b'] ?? null)) continue;
            $segments[] = [
                'a' => $this->point($segment['a']),
                'b' => $this->point($segment['b']),
                'network' => $this->normalizeNetwork((string) ($segment['network'] ?? 'unclassified')),
                'wireId' => (string) ($segment['wireId'] ?? ''),
            ];
        }

        $routes = [];
        foreach ($requests as $request) {
            $result = $this->route(
                is_array($request['source'] ?? null) ? $request['source'] : [],
                is_array($request['target'] ?? null) ? $request['target'] : [],
                is_array($request['obstacles'] ?? null) ? $request['obstacles'] : [],
                $segments,
                (string) ($request['network'] ?? 'unclassified'),
                (float) ($request['clearance'] ?? 18.0),
                (float) ($request['channelSpacing'] ?? 12.0),
                (float) ($request['gridSize'] ?? 10.0),
            );
            $id = (string) ($request['id'] ?? 'route-'.(count($routes) + 1));
            $network = $this->normalizeNetwork((string) ($request['network'] ?? 'unclassified'));
            $routes[] = ['id' => $id, 'network' => $network, ...$result];
            foreach ($this->segments($result['points'] ?? []) as $segment) {
                $segments[] = ['a' => $segment['a'], 'b' => $segment['b'], 'network' => $network, 'wireId' => $id];
            }
        }

        $summary = [
            'wires' => 0,
            'crossings' => 0,
            'clearance_conflicts' => 0,
            'separation_conflicts' => 0,
            'fallbacks' => 0,
            'terminal_direction_violations' => 0,
            'orthogonal_wires' => 0,
            'length' => 0,
        ];
        foreach ($routes as $route) {
            $summary['wires']++;
            $summary['crossings'] += (int) ($route['crossings'] ?? 0);
            $summary['clearance_conflicts'] += (int) ($route['clearance_conflicts'] ?? 0);
            $summary['separation_conflicts'] += (int) ($route['separation_conflicts'] ?? 0);
            $summary['fallbacks'] += ($route['strategy'] ?? '') === 'perimeter-fallback' ? 1 : 0;
            $summary['terminal_direction_violations'] += ($route['terminal_direction_ok'] ?? false) ? 0 : 1;
            $summary['orthogonal_wires'] += ($route['orthogonal'] ?? false) ? 1 : 0;
            $summary['length'] += (int) ($route['length'] ?? 0);
        }

        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'engine' => self::ENGINE,
            'routes' => $routes,
            'segments' => $segments,
            'summary' => $summary,
        ];
    }

    public static function networkClass(string $network): string
    {
        $key = strtolower(str_replace(['-', ' '], '_', trim($network)));
        if (! array_key_exists($key, self::NETWORK_PROFILE)) $key = 'unclassified';

        return self::NETWORK_PROFILE[$key]['class'];
    }

    public static function networkPriority(string $network): int
    {
        $key = strtolower(str_replace(['-', ' '], '_', trim($network)));
        if (! array_key_exists($key, self::NETWORK_PROFILE)) $key = 'unclassified';

        return self::NETWORK_PROFILE[$key]['priority'];
    }

    /** @return array<string,mixed> */
    private function finish(
        array $source,
        array $target,
        array $interior,
        string $strategy,
        array $obstacles,
        array $existing,
        string $network,
        float $spacing,
        string $sourceSide,
        string $targetSide,
        array $preferred,
        float $clearance,
    ): array {
        $full = $this->compress(array_merge([$source], $interior, [$target]));
        $allowedJunctions = [$source, $target];
        $length = 0.0;
        $crossings = 0;
        $obstacleConflicts = 0;
        $channelConflicts = 0;
        $separationConflicts = 0;

        foreach ($this->segments($full) as $segment) {
            $length += abs($segment['a']['x'] - $segment['b']['x']) + abs($segment['a']['y'] - $segment['b']['y']);
            if ($this->blocked($segment['a'], $segment['b'], $obstacles)) $obstacleConflicts++;
            foreach ($existing as $other) {
                if ($this->crosses($segment['a'], $segment['b'], $other['a'], $other['b'])
                    && ! $this->sharedEndpointJunction($segment['a'], $segment['b'], $other['a'], $other['b'], $allowedJunctions)) {
                    $crossings++;
                }
                if ($this->parallelProximity($segment['a'], $segment['b'], $other['a'], $other['b'], $spacing) > self::EPSILON) {
                    if (self::networkClass((string) ($other['network'] ?? 'unclassified')) === self::networkClass($network)) $channelConflicts++;
                    else $separationConflicts++;
                }
            }
        }

        $segments = $this->segments($full);
        $orthogonal = count($segments) === max(0, count($full) - 1);
        $sourceDirectionOk = count($full) >= 2 && $this->terminalLeadMatches($full[0], $full[1], $sourceSide);
        $targetDirectionOk = count($full) >= 2 && $this->terminalLeadMatches($full[count($full) - 1], $full[count($full) - 2], $targetSide);
        $clearanceConflicts = $obstacleConflicts + $channelConflicts + $separationConflicts;

        return [
            'points' => $full,
            'fullPoints' => $full,
            'strategy' => $strategy,
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'preferred_axis' => (string) ($preferred['axis'] ?? 'horizontal'),
            'preferred_channel' => (float) ($preferred['value'] ?? 0),
            'clearance' => $clearance,
            'channel_spacing' => $spacing,
            'length' => (int) round($length),
            'bends' => max(0, count($full) - 2),
            'crossings' => $crossings,
            'obstacle_conflicts' => $obstacleConflicts,
            'channel_conflicts' => $channelConflicts,
            'separation_conflicts' => $separationConflicts,
            'clearance_conflicts' => $clearanceConflicts,
            'obstacle_free' => $obstacleConflicts === 0,
            'orthogonal' => $orthogonal,
            'terminal_direction_ok' => $sourceDirectionOk && $targetDirectionOk,
            'source_direction_ok' => $sourceDirectionOk,
            'target_direction_ok' => $targetDirectionOk,
            'network_class' => self::networkClass($network),
            'signature' => $this->signature($full, $network),
        ];
    }

    /** @return array<int,array{x:float,y:float}> */
    private function fallback(array $source, array $target, array $obstacles, float $grid): array
    {
        $offset = 60.0;
        $left = min($source['x'], $target['x']);
        $right = max($source['x'], $target['x']);
        $top = min($source['y'], $target['y']);
        $bottom = max($source['y'], $target['y']);
        foreach ($obstacles as $rect) {
            $left = min($left, $rect['left']);
            $right = max($right, $rect['right']);
            $top = min($top, $rect['top']);
            $bottom = max($bottom, $rect['bottom']);
        }
        $candidates = [
            [$source, ['x' => $target['x'], 'y' => $source['y']], $target],
            [$source, ['x' => $source['x'], 'y' => $target['y']], $target],
            [$source, ['x' => $left - $offset, 'y' => $source['y']], ['x' => $left - $offset, 'y' => $target['y']], $target],
            [$source, ['x' => $right + $offset, 'y' => $source['y']], ['x' => $right + $offset, 'y' => $target['y']], $target],
            [$source, ['x' => $source['x'], 'y' => $top - $offset], ['x' => $target['x'], 'y' => $top - $offset], $target],
            [$source, ['x' => $source['x'], 'y' => $bottom + $offset], ['x' => $target['x'], 'y' => $bottom + $offset], $target],
        ];
        $valid = [];
        foreach ($candidates as $candidate) {
            $candidate = $this->compress($candidate);
            $blocked = false;
            foreach ($this->segments($candidate) as $segment) {
                if ($this->blocked($segment['a'], $segment['b'], $obstacles)) { $blocked = true; break; }
            }
            if (! $blocked) $valid[] = $candidate;
        }
        usort($valid, function (array $a, array $b): int {
            return $this->length($a) <=> $this->length($b)
                ?: count($a) <=> count($b)
                ?: strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR));
        });
        return $valid[0] ?? $this->compress([$source, ['x' => $target['x'], 'y' => $source['y']], $target]);
    }

    private function normalizeNetwork(string $network): string
    {
        $key = strtolower(str_replace(['-', ' '], '_', trim($network)));
        return array_key_exists($key, self::NETWORK_PROFILE) ? $key : 'unclassified';
    }

    private function networkLane(string $network): int
    {
        return self::NETWORK_PROFILE[$this->normalizeNetwork($network)]['lane'];
    }

    /** @return array{axis:string,value:float} */
    private function preferredChannel(array $source, array $target, string $sourceSide, string $targetSide, float $laneOffset, float $grid): array
    {
        $horizontal = in_array($sourceSide, ['left', 'right'], true) && in_array($targetSide, ['left', 'right'], true);
        $vertical = in_array($sourceSide, ['top', 'bottom'], true) && in_array($targetSide, ['top', 'bottom'], true);
        $axis = $horizontal ? 'horizontal' : ($vertical ? 'vertical' : (abs($source['x'] - $target['x']) >= abs($source['y'] - $target['y']) ? 'horizontal' : 'vertical'));
        $value = $axis === 'horizontal'
            ? $this->snap((($source['y'] + $target['y']) / 2) + $laneOffset, $grid)
            : $this->snap((($source['x'] + $target['x']) / 2) + $laneOffset, $grid);
        return ['axis' => $axis, 'value' => $value];
    }

    private function crossingWeight(string $network, string $existingNetwork): float
    {
        $currentClass = self::networkClass($network);
        $existingClass = self::networkClass($existingNetwork);
        if ($currentClass === $existingClass) return .78;
        $powerSignal = (in_array($currentClass, ['dc', 'ac'], true) && $existingClass === 'signal')
            || (in_array($existingClass, ['dc', 'ac'], true) && $currentClass === 'signal');
        return $powerSignal ? 1.55 : 1.12;
    }

    private function proximityWeight(string $network, string $existingNetwork): float
    {
        if ($this->normalizeNetwork($network) === $this->normalizeNetwork($existingNetwork)) return 2.2;
        if (self::networkClass($network) === self::networkClass($existingNetwork)) return 5.4;
        return 10.8;
    }

    private function signature(array $points, string $network): string
    {
        $encoded = array_map(static fn (array $point): string => number_format((float) ($point['x'] ?? 0), 2, '.', '').','.number_format((float) ($point['y'] ?? 0), 2, '.', ''), $points);
        $text = self::VERSION.'|'.$this->normalizeNetwork($network).'|'.implode(';', $encoded);
        $hash = 0;
        $length = strlen($text);
        for ($index = 0; $index < $length; $index++) $hash = (($hash * 31) + ord($text[$index])) % 2147483647;
        return 'rt-'.str_pad(dechex($hash), 8, '0', STR_PAD_LEFT);
    }

    private function terminalLeadMatches(array $origin, array $next, string $side): bool
    {
        [$vx, $vy] = match ($side) {
            'left' => [-1, 0],
            'top' => [0, -1],
            'bottom' => [0, 1],
            default => [1, 0],
        };
        $dx = $next['x'] - $origin['x'];
        $dy = $next['y'] - $origin['y'];
        if ($vx !== 0) return abs($dy) < self::EPSILON && abs($dx) > self::EPSILON && ($dx > 0 ? 1 : -1) === $vx;
        return abs($dx) < self::EPSILON && abs($dy) > self::EPSILON && ($dy > 0 ? 1 : -1) === $vy;
    }

    private function side(string $side): string
    {
        return in_array($side, ['left', 'right', 'top', 'bottom'], true) ? $side : 'right';
    }

    /** @return array{x:float,y:float} */
    private function point(array $point): array
    {
        return ['x' => (float) ($point['x'] ?? 0), 'y' => (float) ($point['y'] ?? 0)];
    }

    private function snap(float $value, float $grid): float
    {
        return round($value / $grid) * $grid;
    }

    private function key(array $point): string
    {
        return number_format((float) $point['x'], 4, '.', '').'|'.number_format((float) $point['y'], 4, '.', '');
    }

    /** @return array{x:float,y:float} */
    private function offset(array $point, string $side, float $distance): array
    {
        [$x, $y] = match ($side) {
            'left' => [-1, 0],
            'top' => [0, -1],
            'bottom' => [0, 1],
            default => [1, 0],
        };
        return ['x' => $point['x'] + ($x * $distance), 'y' => $point['y'] + ($y * $distance)];
    }

    /** @return array{x:float,y:float} */
    private function leadPoint(array $point, string $side, float $distance, float $grid): array
    {
        $raw = $this->offset($point, $side, $distance);
        return in_array($side, ['left', 'right'], true)
            ? ['x' => $this->snap($raw['x'], $grid), 'y' => round($point['y'], 4)]
            : ['x' => round($point['x'], 4), 'y' => $this->snap($raw['y'], $grid)];
    }

    /** @return array{id:string,left:float,top:float,right:float,bottom:float} */
    private function rect(array $rect, string $id): array
    {
        $left = (float) ($rect['left'] ?? $rect['x'] ?? 0);
        $top = (float) ($rect['top'] ?? $rect['y'] ?? 0);
        $right = isset($rect['right']) ? (float) $rect['right'] : $left + max(0.0, (float) ($rect['width'] ?? $rect['w'] ?? 0));
        $bottom = isset($rect['bottom']) ? (float) $rect['bottom'] : $top + max(0.0, (float) ($rect['height'] ?? $rect['h'] ?? 0));
        return ['id' => (string) ($rect['id'] ?? $id), 'left' => min($left, $right), 'top' => min($top, $bottom), 'right' => max($left, $right), 'bottom' => max($top, $bottom)];
    }

    private function expand(array $rect, float $amount): array
    {
        return [...$rect, 'left' => $rect['left'] - $amount, 'top' => $rect['top'] - $amount, 'right' => $rect['right'] + $amount, 'bottom' => $rect['bottom'] + $amount];
    }

    private function inside(array $point, array $rect): bool
    {
        return $point['x'] > $rect['left'] + self::EPSILON && $point['x'] < $rect['right'] - self::EPSILON
            && $point['y'] > $rect['top'] + self::EPSILON && $point['y'] < $rect['bottom'] - self::EPSILON;
    }

    private function overlap(float $a1, float $a2, float $b1, float $b2, bool $inclusive = true): bool
    {
        $left = max(min($a1, $a2), min($b1, $b2));
        $right = min(max($a1, $a2), max($b1, $b2));
        return $inclusive ? $left <= $right + self::EPSILON : $left < $right - self::EPSILON;
    }

    private function orthogonal(array $a, array $b): bool
    {
        return abs($a['x'] - $b['x']) < self::EPSILON || abs($a['y'] - $b['y']) < self::EPSILON;
    }

    private function intersectsRect(array $a, array $b, array $rect): bool
    {
        if (abs($a['x'] - $b['x']) < self::EPSILON) {
            if ($a['x'] <= $rect['left'] + self::EPSILON || $a['x'] >= $rect['right'] - self::EPSILON) return false;
            return $this->overlap($a['y'], $b['y'], $rect['top'], $rect['bottom'], false);
        }
        if (abs($a['y'] - $b['y']) < self::EPSILON) {
            if ($a['y'] <= $rect['top'] + self::EPSILON || $a['y'] >= $rect['bottom'] - self::EPSILON) return false;
            return $this->overlap($a['x'], $b['x'], $rect['left'], $rect['right'], false);
        }
        return true;
    }

    private function blocked(array $a, array $b, array $obstacles): bool
    {
        foreach ($obstacles as $rect) if ($this->intersectsRect($a, $b, $rect)) return true;
        return false;
    }

    private function crosses(array $a, array $b, array $c, array $d): bool
    {
        $abVertical = abs($a['x'] - $b['x']) < self::EPSILON;
        $cdVertical = abs($c['x'] - $d['x']) < self::EPSILON;
        if ($abVertical === $cdVertical) return false;
        $vertical = $abVertical ? [$a, $b] : [$c, $d];
        $horizontal = $abVertical ? [$c, $d] : [$a, $b];
        $x = $vertical[0]['x'];
        $y = $horizontal[0]['y'];
        return $this->overlap($vertical[0]['y'], $vertical[1]['y'], $y, $y)
            && $this->overlap($horizontal[0]['x'], $horizontal[1]['x'], $x, $x);
    }

    private function sharedEndpointJunction(array $a, array $b, array $c, array $d, array $allowedJunctions): bool
    {
        $abVertical = abs($a['x'] - $b['x']) < self::EPSILON;
        $cdVertical = abs($c['x'] - $d['x']) < self::EPSILON;
        if ($abVertical === $cdVertical) return false;
        $vertical = $abVertical ? [$a, $b] : [$c, $d];
        $horizontal = $abVertical ? [$c, $d] : [$a, $b];
        $intersection = ['x' => $vertical[0]['x'], 'y' => $horizontal[0]['y']];
        $matches = static fn (array $point): bool => abs($point['x'] - $intersection['x']) < self::EPSILON && abs($point['y'] - $intersection['y']) < self::EPSILON;
        $allowed = false;
        foreach ($allowedJunctions as $junction) if ($matches($junction)) { $allowed = true; break; }
        if (! $allowed) return false;
        return ($matches($a) || $matches($b)) && ($matches($c) || $matches($d));
    }

    private function parallelProximity(array $a, array $b, array $c, array $d, float $spacing): float
    {
        $abVertical = abs($a['x'] - $b['x']) < self::EPSILON;
        $cdVertical = abs($c['x'] - $d['x']) < self::EPSILON;
        if ($abVertical !== $cdVertical) return 0.0;
        if ($abVertical) {
            if (! $this->overlap($a['y'], $b['y'], $c['y'], $d['y'], false)) return 0.0;
            return max(0.0, $spacing - abs($a['x'] - $c['x']));
        }
        if (! $this->overlap($a['x'], $b['x'], $c['x'], $d['x'], false)) return 0.0;
        return max(0.0, $spacing - abs($a['y'] - $c['y']));
    }

    /** @return array<int,array{a:array{x:float,y:float},b:array{x:float,y:float}}> */
    private function segments(array $points): array
    {
        $result = [];
        for ($index = 0; $index < count($points) - 1; $index++) {
            if ($this->orthogonal($points[$index], $points[$index + 1]) && $points[$index] !== $points[$index + 1]) {
                $result[] = ['a' => $points[$index], 'b' => $points[$index + 1]];
            }
        }
        return $result;
    }

    private function length(array $points): int
    {
        $sum = 0.0;
        foreach ($this->segments($points) as $segment) {
            $sum += abs($segment['a']['x'] - $segment['b']['x']) + abs($segment['a']['y'] - $segment['b']['y']);
        }
        return (int) round($sum);
    }

    /** @return array<int,array{x:float,y:float}> */
    private function compress(array $points): array
    {
        $clean = [];
        foreach ($points as $point) {
            $point = $this->point($point);
            $point = ['x' => round($point['x'], 4), 'y' => round($point['y'], 4)];
            $previous = end($clean);
            if (is_array($previous) && abs($previous['x'] - $point['x']) < self::EPSILON && abs($previous['y'] - $point['y']) < self::EPSILON) continue;
            $clean[] = $point;
        }
        $index = 1;
        while ($index < count($clean) - 1) {
            $a = $clean[$index - 1];
            $b = $clean[$index];
            $c = $clean[$index + 1];
            $sameX = abs($a['x'] - $b['x']) < self::EPSILON && abs($b['x'] - $c['x']) < self::EPSILON;
            $sameY = abs($a['y'] - $b['y']) < self::EPSILON && abs($b['y'] - $c['y']) < self::EPSILON;
            if ($sameX || $sameY) array_splice($clean, $index, 1); else $index++;
        }
        return $clean;
    }

    /** @return array<int,float> */
    private function uniqueSorted(array $values): array
    {
        $map = [];
        foreach ($values as $value) if (is_numeric($value)) $map[number_format((float) $value, 4, '.', '')] = (float) $value;
        $values = array_values($map);
        sort($values, SORT_NUMERIC);
        return $values;
    }

    /** @return array<int,float> */
    private function reduceChannels(array $values, array $anchors, int $max = 72): array
    {
        if (count($values) <= $max) return $values;
        $selected = [];
        foreach ($anchors as $anchor) {
            $closest = $values[0];
            foreach ($values as $value) if (abs($value - $anchor) < abs($closest - $anchor)) $closest = $value;
            $selected[(string) $closest] = $closest;
        }
        usort($values, static fn (float $a, float $b): int => min(...array_map(static fn (float $anchor): float => abs($a - $anchor), $anchors)) <=> min(...array_map(static fn (float $anchor): float => abs($b - $anchor), $anchors)) ?: ($a <=> $b));
        foreach ($values as $value) {
            if (count($selected) >= $max) break;
            $selected[(string) $value] = $value;
        }
        $result = array_values($selected);
        sort($result, SORT_NUMERIC);
        return $result;
    }
}
