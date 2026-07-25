<?php

namespace App\Services\Sld;

class SldVisualRegression
{
    public const VERSION = '67M.4';
    public const CONTRACT = 'deterministic-scene-fingerprint-v1';
    public const ENGINE = 'canonical-scene-auditor';
    public const COORDINATE_TOLERANCE = 0.5;
    public const CLIPPING_TOLERANCE = 0.5;
    public const ROUTE_TOLERANCE = 0.01;

    /** @param array<string,mixed> $editor */
    public function normalizeMetadata(array $editor): array
    {
        $input = is_array($editor['visual_regression'] ?? null) ? $editor['visual_regression'] : [];
        $audit = $this->audit($editor);

        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'engine' => self::ENGINE,
            'coordinate_tolerance' => self::COORDINATE_TOLERANCE,
            'clipping_tolerance' => self::CLIPPING_TOLERANCE,
            'route_tolerance' => self::ROUTE_TOLERANCE,
            'fingerprint' => $audit['fingerprint'],
            'baseline_fingerprint' => preg_match('/^vr-[0-9a-f]{8}$/', (string) ($input['baseline_fingerprint'] ?? ''))
                ? (string) $input['baseline_fingerprint']
                : $audit['fingerprint'],
            'status' => $audit['status'],
            'summary' => $audit['summary'],
        ];
    }

    /**
     * Recompute server-owned visual-integrity evidence from persisted editor
     * geometry. Browser-provided issue counts and status are never trusted.
     *
     * @param array<string,mixed> $editor
     * @return array<string,mixed>
     */
    public function audit(array $editor): array
    {
        $snapshot = $this->snapshot($editor);
        $canonical = $snapshot['canonical'];
        $canvas = $this->rect(is_array($canonical['canvas'] ?? null) ? $canonical['canvas'] : []);
        $equipment = is_array($canonical['equipment'] ?? null) ? $canonical['equipment'] : [];
        $routes = is_array($canonical['routes'] ?? null) ? $canonical['routes'] : [];
        $labels = is_array($canonical['labels'] ?? null) ? $canonical['labels'] : [];
        $issues = [];
        $addIssue = static function (string $code, string $severity, string $category, array $objects) use (&$issues): void {
            $key = $code.'|'.implode('|', $objects);
            $issues[$key] = ['code' => $code, 'severity' => $severity, 'category' => $category, 'objects' => $objects];
        };

        foreach ($equipment as $index => $item) {
            if (! is_array($item)) continue;
            $id = (string) ($item['id'] ?? '');
            if ($this->rectOutsideCanvas($item, $canvas, self::CLIPPING_TOLERANCE)) {
                $addIssue('V401', 'error', 'clipping', [$id]);
            }
            for ($otherIndex = $index + 1, $count = count($equipment); $otherIndex < $count; $otherIndex++) {
                $other = $equipment[$otherIndex] ?? null;
                if (! is_array($other)) continue;
                if ($this->rectanglesOverlap($item, $other, self::CLIPPING_TOLERANCE)) {
                    $addIssue('V402', 'error', 'equipment_overlap', [$id, (string) ($other['id'] ?? '')]);
                }
            }
        }

        foreach ($labels as $index => $label) {
            if (! is_array($label)) continue;
            $id = (string) ($label['id'] ?? '');
            if ($this->rectOutsideCanvas($label, $canvas, self::CLIPPING_TOLERANCE)) {
                $addIssue('V403', 'error', 'label_clipping', [$id]);
            }
            foreach ($equipment as $item) {
                if (is_array($item) && $this->rectanglesOverlap($label, $item, self::CLIPPING_TOLERANCE)) {
                    $addIssue('V404', 'error', 'label_equipment_overlap', [$id, (string) ($item['id'] ?? '')]);
                }
            }
            for ($otherIndex = $index + 1, $count = count($labels); $otherIndex < $count; $otherIndex++) {
                $other = $labels[$otherIndex] ?? null;
                if (! is_array($other)) continue;
                if ($this->rectanglesOverlap($label, $other, self::CLIPPING_TOLERANCE)) {
                    $addIssue('V405', 'error', 'label_overlap', [$id, (string) ($other['id'] ?? '')]);
                }
            }
        }

        foreach ($routes as $route) {
            if (! is_array($route)) continue;
            $id = (string) ($route['id'] ?? '');
            $points = array_values(array_filter(is_array($route['points'] ?? null) ? $route['points'] : [], 'is_array'));
            if (! $this->routeIsOrthogonal($points, self::ROUTE_TOLERANCE)) {
                $addIssue('V406', 'error', 'non_orthogonal_route', [$id]);
            }
            foreach ($points as $point) {
                if (! $this->pointInRect($point, $canvas, self::CLIPPING_TOLERANCE)) {
                    $addIssue('V407', 'error', 'route_clipping', [$id]);
                    break;
                }
            }
            for ($pointIndex = 0, $pointCount = count($points) - 1; $pointIndex < $pointCount; $pointIndex++) {
                $from = $points[$pointIndex];
                $to = $points[$pointIndex + 1];
                foreach ($labels as $label) {
                    if (is_array($label) && $this->segmentIntersectsRect($from, $to, $label, self::CLIPPING_TOLERANCE)) {
                        $addIssue('V408', 'warning', 'wire_label_intersection', [$id, (string) ($label['id'] ?? '')]);
                    }
                }
            }
        }

        $issueList = array_values($issues);
        $errors = count(array_filter($issueList, static fn (array $issue): bool => ($issue['severity'] ?? null) === 'error'));
        $warnings = count(array_filter($issueList, static fn (array $issue): bool => ($issue['severity'] ?? null) === 'warning'));
        $categories = [];
        foreach ($issueList as $issue) {
            $category = (string) ($issue['category'] ?? '');
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'engine' => self::ENGINE,
            'fingerprint' => $snapshot['fingerprint'],
            'status' => $errors > 0 ? 'error' : ($warnings > 0 ? 'warning' : 'pass'),
            'summary' => [
                'equipment' => count($equipment),
                'routes' => count($routes),
                'labels' => count($labels),
                'issues' => count($issueList),
                'errors' => $errors,
                'warnings' => $warnings,
                'clipping' => (int) (($categories['clipping'] ?? 0) + ($categories['label_clipping'] ?? 0) + ($categories['route_clipping'] ?? 0)),
                'overlaps' => (int) (($categories['equipment_overlap'] ?? 0) + ($categories['label_equipment_overlap'] ?? 0) + ($categories['label_overlap'] ?? 0)),
                'non_orthogonal_routes' => (int) ($categories['non_orthogonal_route'] ?? 0),
                'wire_label_intersections' => (int) ($categories['wire_label_intersection'] ?? 0),
            ],
            'issues' => $issueList,
            'canonical' => $canonical,
        ];
    }

    /** @param array<string,mixed> $editor */
    public function snapshot(array $editor): array
    {
        $canvas = $this->rect(is_array($editor['canvas'] ?? null) ? $editor['canvas'] : ['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 920]);
        $positions = [];
        foreach (is_array($editor['positions'] ?? null) ? $editor['positions'] : [] as $id => $position) {
            if (! is_string($id) || ! is_array($position) || trim($id) === '') continue;
            $rect = $this->rect($position);
            $positions[] = [
                'id' => $id,
                'x' => $rect['x'], 'y' => $rect['y'], 'w' => $rect['w'], 'h' => $rect['h'],
                'rotation' => (((int) round($this->number($position['rotation'] ?? 0) / 90) * 90) % 360 + 360) % 360,
                'flip_h' => (bool) ($position['flip_h'] ?? false),
                'flip_v' => (bool) ($position['flip_v'] ?? false),
                'visible' => ($position['visible'] ?? true) !== false,
            ];
        }
        usort($positions, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        $routes = [];
        foreach (is_array($editor['routes'] ?? null) ? $editor['routes'] : [] as $id => $points) {
            if (! is_string($id) || ! is_array($points) || trim($id) === '') continue;
            $clean = [];
            foreach ($points as $point) {
                if (! is_array($point)) continue;
                $clean[] = ['x' => $this->round($point['x'] ?? 0), 'y' => $this->round($point['y'] ?? 0)];
            }
            $routes[] = ['id' => $id, 'points' => $clean];
        }
        usort($routes, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        $labels = [];
        $labelMetadata = is_array($editor['label_placement'] ?? null) ? $editor['label_placement'] : [];
        foreach (is_array($labelMetadata['positions'] ?? null) ? $labelMetadata['positions'] : [] as $id => $placement) {
            if (! is_string($id) || ! is_array($placement) || trim($id) === '') continue;
            $rect = $this->rect($placement);
            $labels[] = [
                'id' => $id,
                'x' => $rect['x'], 'y' => $rect['y'], 'w' => $rect['w'], 'h' => $rect['h'],
                'side' => in_array($placement['side'] ?? null, ['top', 'right', 'bottom', 'left'], true) ? $placement['side'] : 'bottom',
                'leader' => (bool) ($placement['leader'] ?? false),
                'signature' => substr((string) ($placement['signature'] ?? ''), 0, 24),
            ];
        }
        usort($labels, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        $canonical = ['canvas' => $canvas, 'equipment' => $positions, 'routes' => $routes, 'labels' => $labels];
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'engine' => self::ENGINE,
            'fingerprint' => 'vr-'.hash('fnv1a32', $json),
            'canonical' => $canonical,
        ];
    }

    /** @param array<string,mixed> $value @return array<string,float> */
    private function rect(array $value): array
    {
        $x = $this->round($value['x'] ?? $value['left'] ?? 0);
        $y = $this->round($value['y'] ?? $value['top'] ?? 0);
        $w = max(0, $this->round($value['w'] ?? $value['width'] ?? (($value['right'] ?? $x) - $x)));
        $h = max(0, $this->round($value['h'] ?? $value['height'] ?? (($value['bottom'] ?? $y) - $y)));
        return [
            'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h,
            'left' => $x, 'top' => $y, 'right' => $this->round($x + $w), 'bottom' => $this->round($y + $h),
        ];
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private function rectanglesOverlap(array $a, array $b, float $tolerance = 0): bool
    {
        $aa = $this->rect($a);
        $bb = $this->rect($b);
        return $aa['left'] < $bb['right'] - $tolerance
            && $aa['right'] > $bb['left'] + $tolerance
            && $aa['top'] < $bb['bottom'] - $tolerance
            && $aa['bottom'] > $bb['top'] + $tolerance;
    }

    /** @param array<string,mixed> $rect @param array<string,mixed> $canvas */
    private function rectOutsideCanvas(array $rect, array $canvas, float $tolerance = 0): bool
    {
        $r = $this->rect($rect);
        $c = $this->rect($canvas);
        return $r['left'] < $c['left'] - $tolerance
            || $r['top'] < $c['top'] - $tolerance
            || $r['right'] > $c['right'] + $tolerance
            || $r['bottom'] > $c['bottom'] + $tolerance;
    }

    /** @param array<string,mixed> $point @param array<string,mixed> $rect */
    private function pointInRect(array $point, array $rect, float $tolerance = 0): bool
    {
        $r = $this->rect($rect);
        $x = $this->number($point['x'] ?? 0);
        $y = $this->number($point['y'] ?? 0);
        return $x >= $r['left'] - $tolerance && $x <= $r['right'] + $tolerance
            && $y >= $r['top'] - $tolerance && $y <= $r['bottom'] + $tolerance;
    }

    /** @param array<int,array<string,mixed>> $points */
    private function routeIsOrthogonal(array $points, float $tolerance): bool
    {
        for ($index = 0, $count = count($points) - 1; $index < $count; $index++) {
            $from = $points[$index];
            $to = $points[$index + 1];
            if (abs($this->number($from['x'] ?? 0) - $this->number($to['x'] ?? 0)) > $tolerance
                && abs($this->number($from['y'] ?? 0) - $this->number($to['y'] ?? 0)) > $tolerance) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b @param array<string,mixed> $c */
    private function orientation(array $a, array $b, array $c): int
    {
        $value = ($this->number($b['y'] ?? 0) - $this->number($a['y'] ?? 0)) * ($this->number($c['x'] ?? 0) - $this->number($b['x'] ?? 0))
            - ($this->number($b['x'] ?? 0) - $this->number($a['x'] ?? 0)) * ($this->number($c['y'] ?? 0) - $this->number($b['y'] ?? 0));
        if (abs($value) < 0.00001) return 0;
        return $value > 0 ? 1 : 2;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b @param array<string,mixed> $c */
    private function onSegment(array $a, array $b, array $c): bool
    {
        $ax = $this->number($a['x'] ?? 0); $ay = $this->number($a['y'] ?? 0);
        $bx = $this->number($b['x'] ?? 0); $by = $this->number($b['y'] ?? 0);
        $cx = $this->number($c['x'] ?? 0); $cy = $this->number($c['y'] ?? 0);
        return $bx <= max($ax, $cx) + 0.00001 && $bx + 0.00001 >= min($ax, $cx)
            && $by <= max($ay, $cy) + 0.00001 && $by + 0.00001 >= min($ay, $cy);
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b @param array<string,mixed> $c @param array<string,mixed> $d */
    private function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $o1 = $this->orientation($a, $b, $c);
        $o2 = $this->orientation($a, $b, $d);
        $o3 = $this->orientation($c, $d, $a);
        $o4 = $this->orientation($c, $d, $b);
        if ($o1 !== $o2 && $o3 !== $o4) return true;
        if ($o1 === 0 && $this->onSegment($a, $c, $b)) return true;
        if ($o2 === 0 && $this->onSegment($a, $d, $b)) return true;
        if ($o3 === 0 && $this->onSegment($c, $a, $d)) return true;
        return $o4 === 0 && $this->onSegment($c, $b, $d);
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b @param array<string,mixed> $rect */
    private function segmentIntersectsRect(array $a, array $b, array $rect, float $tolerance = 0): bool
    {
        $r = $this->rect($rect);
        if ($this->pointInRect($a, $r, $tolerance) || $this->pointInRect($b, $r, $tolerance)) return true;
        $corners = [
            ['x' => $r['left'], 'y' => $r['top']],
            ['x' => $r['right'], 'y' => $r['top']],
            ['x' => $r['right'], 'y' => $r['bottom']],
            ['x' => $r['left'], 'y' => $r['bottom']],
        ];
        foreach ($corners as $index => $corner) {
            if ($this->segmentsIntersect($a, $b, $corner, $corners[($index + 1) % count($corners)])) return true;
        }
        return false;
    }

    private function number(mixed $value, float $fallback = 0): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }

    private function round(mixed $value): float
    {
        return round($this->number($value), 3);
    }

    private function bounded(mixed $value, float $minimum, float $maximum): float
    {
        return max($minimum, min($maximum, $this->number($value)));
    }
}
