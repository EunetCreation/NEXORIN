<?php

namespace App\Services\Sld;

class SldGeometryFoundation
{
    public const VERSION = '67M.1';

    public const CONTRACT = 'canonical-model-space-v1';

    public const UNITS = 'drawing_unit';

    public const GRID_SIZE = 20;

    public const COORDINATE_PRECISION = 1;

    public const CANVAS_WIDTH = 1640;

    public const CANVAS_HEIGHT = 940;

    public const MINIMUM_EQUIPMENT_GAP = 20;

    /**
     * @param  array<string, array<string, mixed>>  $positions
     * @param  array<int, string>  $excludedNodeIds
     * @return array<string, mixed>
     */
    public function metadata(array $positions, array $excludedNodeIds = []): array
    {
        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'units' => self::UNITS,
            'coordinate_precision' => self::COORDINATE_PRECISION,
            'grid_size' => self::GRID_SIZE,
            'minimum_equipment_gap' => self::MINIMUM_EQUIPMENT_GAP,
            'diagnostics' => $this->diagnose($positions, $excludedNodeIds)['summary'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $positions
     * @param  array<int, string>  $excludedNodeIds
     * @return array{version:string,contract:string,units:string,objects:int,issues:array<int,array<string,mixed>>,advisories:array<int,array<string,mixed>>,summary:array<string,int>,bounds:?array<string,float>}
     */
    public function diagnose(array $positions, array $excludedNodeIds = []): array
    {
        $excluded = array_fill_keys(array_map('strval', $excludedNodeIds), true);
        $items = [];

        foreach ($positions as $id => $position) {
            if (! is_string($id) || isset($excluded[$id]) || ! is_array($position)) {
                continue;
            }

            $rect = $this->rect($id, $position);
            if ($rect !== null) {
                $items[] = $rect;
            }
        }

        $issues = [];
        $advisories = [];

        foreach ($items as $item) {
            if ($item['x'] < 0
                || $item['y'] < 0
                || $item['x'] + $item['w'] > self::CANVAS_WIDTH
                || $item['y'] + $item['h'] > self::CANVAS_HEIGHT) {
                $issues[] = [
                    'code' => 'GEOMETRY_OUTSIDE_CANVAS',
                    'severity' => 'warning',
                    'objects' => [$item['id']],
                ];
            }

            if (! $this->gridAligned($item['x']) || ! $this->gridAligned($item['y'])) {
                $advisories[] = [
                    'code' => 'GEOMETRY_OFF_GRID',
                    'severity' => 'notice',
                    'objects' => [$item['id']],
                ];
            }
        }

        $count = count($items);
        for ($first = 0; $first < $count; $first++) {
            for ($second = $first + 1; $second < $count; $second++) {
                $a = $items[$first];
                $b = $items[$second];
                $overlapX = min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']);
                $overlapY = min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']);
                $overlapArea = $overlapX > 0 && $overlapY > 0 ? $overlapX * $overlapY : 0.0;

                if ($overlapArea > 0.01) {
                    $issues[] = [
                        'code' => 'GEOMETRY_OVERLAP',
                        'severity' => 'error',
                        'objects' => [$a['id'], $b['id']],
                        'overlap_area' => $this->round($overlapArea),
                    ];

                    continue;
                }

                $gap = $this->edgeGap($a, $b);
                if ($gap < self::MINIMUM_EQUIPMENT_GAP) {
                    $advisories[] = [
                        'code' => 'GEOMETRY_CLEARANCE',
                        'severity' => 'notice',
                        'objects' => [$a['id'], $b['id']],
                        'gap' => $this->round($gap),
                        'required_gap' => self::MINIMUM_EQUIPMENT_GAP,
                    ];
                }
            }
        }

        $errors = count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'error'));
        $warnings = count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'warning'));

        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'units' => self::UNITS,
            'objects' => $count,
            'issues' => $issues,
            'advisories' => $advisories,
            'summary' => [
                'issues' => count($issues),
                'errors' => $errors,
                'warnings' => $warnings,
                'advisories' => count($advisories),
                'overlaps' => count(array_filter($issues, static fn (array $issue): bool => $issue['code'] === 'GEOMETRY_OVERLAP')),
                'outside_canvas' => count(array_filter($issues, static fn (array $issue): bool => $issue['code'] === 'GEOMETRY_OUTSIDE_CANVAS')),
                'off_grid' => count(array_filter($advisories, static fn (array $issue): bool => $issue['code'] === 'GEOMETRY_OFF_GRID')),
                'clearance' => count(array_filter($advisories, static fn (array $issue): bool => $issue['code'] === 'GEOMETRY_CLEARANCE')),
            ],
            'bounds' => $this->bounds($items),
        ];
    }

    /** @param array<string, mixed> $position @return array{id:string,x:float,y:float,w:float,h:float}|null */
    private function rect(string $id, array $position): ?array
    {
        $values = [
            'x' => $position['x'] ?? null,
            'y' => $position['y'] ?? null,
            'w' => $position['w'] ?? null,
            'h' => $position['h'] ?? null,
        ];

        foreach ($values as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                return null;
            }
        }

        $width = (float) $values['w'];
        $height = (float) $values['h'];
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'id' => $id,
            'x' => $this->round((float) $values['x']),
            'y' => $this->round((float) $values['y']),
            'w' => $this->round($width),
            'h' => $this->round($height),
        ];
    }

    private function gridAligned(float $value): bool
    {
        return abs($value - round($value / self::GRID_SIZE) * self::GRID_SIZE) <= 0.01;
    }

    /** @param array{x:float,y:float,w:float,h:float} $a @param array{x:float,y:float,w:float,h:float} $b */
    private function edgeGap(array $a, array $b): float
    {
        $horizontal = max(0.0, max($a['x'], $b['x']) - min($a['x'] + $a['w'], $b['x'] + $b['w']));
        $vertical = max(0.0, max($a['y'], $b['y']) - min($a['y'] + $a['h'], $b['y'] + $b['h']));

        if ($horizontal > 0 && $vertical > 0) {
            return hypot($horizontal, $vertical);
        }

        return max($horizontal, $vertical);
    }

    /** @param array<int, array{id:string,x:float,y:float,w:float,h:float}> $items @return array<string,float>|null */
    private function bounds(array $items): ?array
    {
        if ($items === []) {
            return null;
        }

        $left = min(array_column($items, 'x'));
        $top = min(array_column($items, 'y'));
        $right = max(array_map(static fn (array $item): float => $item['x'] + $item['w'], $items));
        $bottom = max(array_map(static fn (array $item): float => $item['y'] + $item['h'], $items));

        return [
            'left' => $this->round($left),
            'right' => $this->round($right),
            'top' => $this->round($top),
            'bottom' => $this->round($bottom),
            'width' => $this->round($right - $left),
            'height' => $this->round($bottom - $top),
        ];
    }

    private function round(float $value): float
    {
        return round($value, self::COORDINATE_PRECISION);
    }
}
