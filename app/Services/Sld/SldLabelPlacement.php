<?php

namespace App\Services\Sld;

class SldLabelPlacement
{
    public const VERSION = '67M.3';
    public const CONTRACT = 'deterministic-clearance-label-v1';
    public const ENGINE = 'candidate-score-label-placer';
    public const CLEARANCE = 12.0;
    public const EQUIPMENT_GAP = 14.0;
    public const WIRE_CLEARANCE = 8.0;
    public const LEADER_THRESHOLD = 28.0;

    /** @param array<int,array<string,mixed>> $items @param array<int,array<string,mixed>> $segments */
    public function place(array $items, array $segments = [], array $options = []): array
    {
        $equipment = [];
        foreach ($items as $item) {
            if (! is_array($item) || trim((string) ($item['id'] ?? '')) === '') continue;
            $equipment[] = array_merge($item, $this->rect($item), ['id' => (string) $item['id']]);
        }
        usort($equipment, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $canvas = is_array($options['canvas'] ?? null) ? $this->rect($options['canvas']) : null;
        $gap = $this->number($options['equipmentGap'] ?? self::EQUIPMENT_GAP, self::EQUIPMENT_GAP);
        $placements = [];

        foreach ($equipment as $item) {
            $size = $this->estimate($item, $options);
            $candidates = [];
            foreach ($this->candidateOrder($item) as $rank => $side) {
                $candidate = $this->candidate($item, $side, $size, $gap);
                $score = $this->score($candidate, $item, $equipment, $placements, $segments, $canvas, $rank, $options);
                $candidates[] = array_merge($candidate, ['rank' => $rank], $score);
            }
            usort($candidates, static fn (array $a, array $b): int => ($a['score'] <=> $b['score']) ?: ($a['rank'] <=> $b['rank']) ?: strcmp($a['side'], $b['side']));
            $best = $candidates[0];
            $conflicts = $best['equipment_conflicts'] + $best['label_conflicts'] + $best['wire_conflicts'];
            $leaderLength = abs($best['anchor']['x'] - $best['label_anchor']['x']) + abs($best['anchor']['y'] - $best['label_anchor']['y']);
            $leader = $conflicts > 0 || $leaderLength > $this->number($options['leaderThreshold'] ?? self::LEADER_THRESHOLD, self::LEADER_THRESHOLD);
            $signaturePayload = implode('|', [
                (string) $item['id'],
                (string) $best['side'],
                number_format((float) $best['rect']['x'], 3, '.', ''),
                number_format((float) $best['rect']['y'], 3, '.', ''),
                number_format((float) $best['rect']['w'], 3, '.', ''),
                number_format((float) $best['rect']['h'], 3, '.', ''),
                $leader ? '1' : '0',
            ]);
            $signature = 'lp-'.hash('fnv1a32', $signaturePayload);
            $placements[] = [
                'id' => $item['id'], 'side' => $best['side'], 'rect' => $best['rect'],
                'x' => $best['rect']['x'], 'y' => $best['rect']['y'], 'w' => $best['rect']['w'], 'h' => $best['rect']['h'],
                'anchor' => $best['anchor'], 'label_anchor' => $best['label_anchor'], 'leader' => $leader,
                'score' => $best['score'], 'equipment_conflicts' => $best['equipment_conflicts'],
                'label_conflicts' => $best['label_conflicts'], 'wire_conflicts' => $best['wire_conflicts'],
                'fallback' => $conflicts > 0, 'signature' => $signature,
            ];
        }

        $summary = ['labels' => 0, 'leaders' => 0, 'fallbacks' => 0, 'equipment_conflicts' => 0, 'label_conflicts' => 0, 'wire_conflicts' => 0];
        foreach ($placements as $placement) {
            $summary['labels']++;
            $summary['leaders'] += $placement['leader'] ? 1 : 0;
            $summary['fallbacks'] += $placement['fallback'] ? 1 : 0;
            $summary['equipment_conflicts'] += $placement['equipment_conflicts'];
            $summary['label_conflicts'] += $placement['label_conflicts'];
            $summary['wire_conflicts'] += $placement['wire_conflicts'];
        }

        return ['version' => self::VERSION, 'contract' => self::CONTRACT, 'engine' => self::ENGINE, 'placements' => $placements, 'summary' => $summary];
    }

    /** @param array<string,mixed> $editor */
    public function normalizeMetadata(array $editor): array
    {
        $input = is_array($editor['label_placement'] ?? null) ? $editor['label_placement'] : [];
        $summary = is_array($input['summary'] ?? null) ? $input['summary'] : [];
        $positions = [];
        foreach (array_slice(is_array($input['positions'] ?? null) ? $input['positions'] : [], 0, 500, true) as $id => $placement) {
            if (! is_string($id) || ! is_array($placement)) continue;
            $positions[substr($id, 0, 80)] = [
                'side' => in_array($placement['side'] ?? null, ['top', 'right', 'bottom', 'left'], true) ? $placement['side'] : 'bottom',
                'x' => $this->bounded($placement['x'] ?? 0, -10000, 10000),
                'y' => $this->bounded($placement['y'] ?? 0, -10000, 10000),
                'w' => $this->bounded($placement['w'] ?? 120, 40, 400),
                'h' => $this->bounded($placement['h'] ?? 48, 20, 180),
                'leader' => (bool) ($placement['leader'] ?? false),
                'fallback' => (bool) ($placement['fallback'] ?? false),
                'signature' => substr((string) ($placement['signature'] ?? ''), 0, 24),
                'equipment_conflicts' => (int) $this->bounded($placement['equipment_conflicts'] ?? 0, 0, 1000),
                'label_conflicts' => (int) $this->bounded($placement['label_conflicts'] ?? 0, 0, 1000),
                'wire_conflicts' => (int) $this->bounded($placement['wire_conflicts'] ?? 0, 0, 1000),
            ];
        }
        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'engine' => self::ENGINE,
            'clearance' => self::CLEARANCE,
            'wire_clearance' => self::WIRE_CLEARANCE,
            'positions' => $positions,
            'summary' => [
                'labels' => (int) $this->bounded($summary['labels'] ?? count($positions), 0, 1000),
                'leaders' => (int) $this->bounded($summary['leaders'] ?? 0, 0, 1000),
                'fallbacks' => (int) $this->bounded($summary['fallbacks'] ?? 0, 0, 1000),
                'equipment_conflicts' => (int) $this->bounded($summary['equipment_conflicts'] ?? 0, 0, 10000),
                'label_conflicts' => (int) $this->bounded($summary['label_conflicts'] ?? 0, 0, 10000),
                'wire_conflicts' => (int) $this->bounded($summary['wire_conflicts'] ?? 0, 0, 10000),
            ],
        ];
    }

    private function estimate(array $item, array $options): array
    {
        $lines = array_values(array_filter([
            ($item['show_tag'] ?? true) === false ? '' : trim((string) ($item['tag'] ?? '')),
            trim((string) ($item['title'] ?? $item['label'] ?? $item['id'] ?? 'Equipment')),
            ($item['show_rating'] ?? true) === false ? '' : trim((string) ($item['rating'] ?? '')),
            ($item['show_subtitle'] ?? true) === false ? '' : trim((string) ($item['subtitle'] ?? '')),
        ], static fn (string $value): bool => $value !== ''));
        $longest = array_reduce($lines, static fn (int $max, string $line): int => max($max, mb_strlen($line)), 0);
        return [
            'width' => round($this->bounded($options['width'] ?? ($longest * 6.2 + 24), 96, 220)),
            'height' => round($this->bounded($options['height'] ?? (count($lines) * 13 + 14), 34, 82)),
        ];
    }

    private function candidateOrder(array $item): array
    {
        $rotation = (((int) round($this->number($item['rotation'] ?? 0, 0) / 90) * 90) % 360 + 360) % 360;
        $base = in_array($rotation, [90, 270], true) ? ['right', 'left', 'bottom', 'top'] : ['bottom', 'top', 'right', 'left'];
        $density = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];
        foreach (is_array($item['ports'] ?? null) ? $item['ports'] : [] as $port) {
            $rawSide = in_array($port['side'] ?? null, array_keys($density), true) ? $port['side'] : 'right';
            $side = $this->transformedSide($rawSide, $item);
            $density[$side]++;
        }
        $rank = array_flip($base);
        usort($base, static fn (string $a, string $b): int => ($density[$a] <=> $density[$b]) ?: ($rank[$a] <=> $rank[$b]));
        return $base;
    }

    private function transformedSide(string $side, array $transform): string
    {
        [$x, $y] = match ($side) {
            'left' => [-1, 0],
            'top' => [0, -1],
            'bottom' => [0, 1],
            default => [1, 0],
        };
        if (($transform['flip_h'] ?? false) === true) $x *= -1;
        if (($transform['flip_v'] ?? false) === true) $y *= -1;
        $rotation = (((int) round($this->number($transform['rotation'] ?? 0, 0) / 90) * 90) % 360 + 360) % 360;
        [$rx, $ry] = match ($rotation) {
            90 => [-$y, $x],
            180 => [-$x, -$y],
            270 => [$y, -$x],
            default => [$x, $y],
        };
        if (abs($rx) >= abs($ry)) return $rx < 0 ? 'left' : 'right';
        return $ry < 0 ? 'top' : 'bottom';
    }

    private function candidate(array $item, string $side, array $size, float $gap): array
    {
        $equipment = $this->rect($item);
        $cx = $equipment['left'] + $equipment['w'] / 2;
        $cy = $equipment['top'] + $equipment['h'] / 2;
        $x = $cx - $size['width'] / 2;
        $y = $cy - $size['height'] / 2;
        if ($side === 'top') $y = $equipment['top'] - $gap - $size['height'];
        if ($side === 'bottom') $y = $equipment['bottom'] + $gap;
        if ($side === 'left') $x = $equipment['left'] - $gap - $size['width'];
        if ($side === 'right') $x = $equipment['right'] + $gap;
        $rect = $this->rect(['x' => $x, 'y' => $y, 'w' => $size['width'], 'h' => $size['height']]);
        return [
            'side' => $side, 'rect' => $rect,
            'anchor' => ['x' => $side === 'left' ? $equipment['left'] : ($side === 'right' ? $equipment['right'] : $cx), 'y' => $side === 'top' ? $equipment['top'] : ($side === 'bottom' ? $equipment['bottom'] : $cy)],
            'label_anchor' => ['x' => $side === 'left' ? $rect['right'] : ($side === 'right' ? $rect['left'] : $rect['left'] + $rect['w'] / 2), 'y' => $side === 'top' ? $rect['bottom'] : ($side === 'bottom' ? $rect['top'] : $rect['top'] + $rect['h'] / 2)],
        ];
    }

    private function score(array $candidate, array $item, array $equipment, array $labels, array $segments, ?array $canvas, int $rank, array $options): array
    {
        $clearance = $this->number($options['clearance'] ?? self::CLEARANCE, self::CLEARANCE);
        $wireClearance = $this->number($options['wireClearance'] ?? self::WIRE_CLEARANCE, self::WIRE_CLEARANCE);
        $equipmentConflicts = $labelConflicts = $wireConflicts = 0;
        foreach ($equipment as $rect) {
            if ((string) ($rect['id'] ?? '') === (string) ($item['id'] ?? '')) continue;
            if ($this->overlap($candidate['rect'], $rect, $clearance)) $equipmentConflicts++;
        }
        foreach ($labels as $placement) if ($this->overlap($candidate['rect'], $placement['rect'], $clearance)) $labelConflicts++;
        foreach ($segments as $segment) if (is_array($segment) && $this->segmentRect($segment, $candidate['rect'], $wireClearance)) $wireConflicts++;
        $outside = 0.0;
        if ($canvas) {
            $rect = $candidate['rect'];
            $outside = max(0, $canvas['left'] - $rect['left']) + max(0, $rect['right'] - $canvas['right']) + max(0, $canvas['top'] - $rect['top']) + max(0, $rect['bottom'] - $canvas['bottom']);
        }
        return ['score' => $rank * 100 + $equipmentConflicts * 100000 + $labelConflicts * 90000 + $wireConflicts * 50000 + $outside * 5000, 'equipment_conflicts' => $equipmentConflicts, 'label_conflicts' => $labelConflicts, 'wire_conflicts' => $wireConflicts];
    }

    private function overlap(array $a, array $b, float $gap = 0): bool
    {
        $aa = $this->expand($this->rect($a), $gap); $bb = $this->rect($b);
        return $aa['left'] < $bb['right'] && $aa['right'] > $bb['left'] && $aa['top'] < $bb['bottom'] && $aa['bottom'] > $bb['top'];
    }

    private function segmentRect(array $segment, array $rect, float $clearance): bool
    {
        $r = $this->expand($this->rect($rect), $clearance);
        $a = [
            'x' => $this->number($segment['x1'] ?? data_get($segment, 'start.x') ?? data_get($segment, 'a.x'), 0),
            'y' => $this->number($segment['y1'] ?? data_get($segment, 'start.y') ?? data_get($segment, 'a.y'), 0),
        ];
        $b = [
            'x' => $this->number($segment['x2'] ?? data_get($segment, 'end.x') ?? data_get($segment, 'b.x'), 0),
            'y' => $this->number($segment['y2'] ?? data_get($segment, 'end.y') ?? data_get($segment, 'b.y'), 0),
        ];
        if ($this->pointInRect($a, $r) || $this->pointInRect($b, $r)) return true;
        $corners = [['x'=>$r['left'],'y'=>$r['top']],['x'=>$r['right'],'y'=>$r['top']],['x'=>$r['right'],'y'=>$r['bottom']],['x'=>$r['left'],'y'=>$r['bottom']]];
        for ($i=0; $i<4; $i++) if ($this->segmentsIntersect($a,$b,$corners[$i],$corners[($i+1)%4])) return true;
        return false;
    }

    private function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $o1=$this->orientation($a,$b,$c); $o2=$this->orientation($a,$b,$d); $o3=$this->orientation($c,$d,$a); $o4=$this->orientation($c,$d,$b);
        if ($o1!==$o2 && $o3!==$o4) return true;
        if ($o1===0 && $this->onSegment($a,$c,$b)) return true;
        if ($o2===0 && $this->onSegment($a,$d,$b)) return true;
        if ($o3===0 && $this->onSegment($c,$a,$d)) return true;
        return $o4===0 && $this->onSegment($c,$b,$d);
    }
    private function orientation(array $a,array $b,array $c): int { $v=($b['y']-$a['y'])*($c['x']-$b['x'])-($b['x']-$a['x'])*($c['y']-$b['y']); return abs($v)<0.00001?0:($v>0?1:2); }
    private function onSegment(array $a,array $b,array $c): bool { return $b['x']<=max($a['x'],$c['x'])+0.00001&&$b['x']+0.00001>=min($a['x'],$c['x'])&&$b['y']<=max($a['y'],$c['y'])+0.00001&&$b['y']+0.00001>=min($a['y'],$c['y']); }
    private function pointInRect(array $p,array $r): bool { return $p['x'] >= $r['left'] && $p['x'] <= $r['right'] && $p['y'] >= $r['top'] && $p['y'] <= $r['bottom']; }
    private function expand(array $r,float $g): array { return array_merge($r,['x'=>$r['x']-$g,'y'=>$r['y']-$g,'w'=>$r['w']+$g*2,'h'=>$r['h']+$g*2,'left'=>$r['left']-$g,'top'=>$r['top']-$g,'right'=>$r['right']+$g,'bottom'=>$r['bottom']+$g]); }
    private function rect(array $r): array { $x=$this->number($r['x']??$r['left']??0,0);$y=$this->number($r['y']??$r['top']??0,0);$w=max(1,$this->number($r['w']??$r['width']??(($r['right']??$x)-$x),1));$h=max(1,$this->number($r['h']??$r['height']??(($r['bottom']??$y)-$y),1));return ['x'=>$x,'y'=>$y,'w'=>$w,'h'=>$h,'left'=>$x,'top'=>$y,'right'=>$x+$w,'bottom'=>$y+$h]; }
    private function number(mixed $value,float $fallback): float { return is_numeric($value)?(float)$value:$fallback; }
    private function bounded(mixed $value,float $min,float $max): float { return max($min,min($max,$this->number($value,$min))); }
}
