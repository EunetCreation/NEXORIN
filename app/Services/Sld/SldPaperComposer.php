<?php

namespace App\Services\Sld;

/**
 * Density-aware A3 paper-space composer.
 *
 * Model Space remains the canonical drawing. This service never rewrites model
 * coordinates. It selects readable viewports, creates overview/detail sheets,
 * and generates catalogue sheets when the canvas is clearly a symbol board.
 */
class SldPaperComposer
{
    // Print-safe topology viewport. The area is intentionally generous: the
    // drawing is the primary document content, while notes and the title block
    // remain compact supporting information.
    public const PAPER_LEFT = 70.0;
    public const PAPER_TOP = 232.0;
    public const PAPER_RIGHT = 1610.0;
    public const PAPER_BOTTOM = 970.0;
    public const MIN_READABLE_SCALE = 0.62;
    public const MAX_DETAIL_NODES = 14;
    public const CATALOG_PAGE_SIZE = 12;

    /** @return array<string, mixed> */
    public function compose(array $graph): array
    {
        $editor = is_array($graph['editor'] ?? null) ? $graph['editor'] : [];
        $excluded = array_fill_keys(array_map('strval', is_array($editor['excluded_nodes'] ?? null) ? $editor['excluded_nodes'] : []), true);
        $layers = $this->layers($editor);

        $nodes = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) {
                continue;
            }
            $id = (string) $node['id'];
            if (isset($excluded[$id]) || ! $this->layerPrintable($layers, $this->nodeLayer($id, $node))) {
                continue;
            }
            $nodes[$id] = $node;
        }
        foreach (is_array($editor['manual_nodes'] ?? null) ? $editor['manual_nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) {
                continue;
            }
            $id = (string) $node['id'];
            $node['_manual'] = true;
            if (isset($excluded[$id]) || ! $this->layerPrintable($layers, $this->nodeLayer($id, $node))) {
                continue;
            }
            $nodes[$id] = $node;
        }

        $connections = [];
        foreach (is_array($graph['connections'] ?? null) ? $graph['connections'] : [] as $index => $connection) {
            if (! is_array($connection)) {
                continue;
            }
            $connection['_id'] = $this->connectionId($connection, $index);
            if ($this->layerPrintable($layers, $this->connectionLayer($connection))) {
                $connections[] = $connection;
            }
        }
        foreach (is_array($editor['manual_connections'] ?? null) ? $editor['manual_connections'] : [] as $index => $connection) {
            if (! is_array($connection)) {
                continue;
            }
            $connection['_manual'] = true;
            $connection['_id'] = filled($connection['id'] ?? null) ? (string) $connection['id'] : 'manual_'.$this->connectionId($connection, $index);
            if ($this->layerPrintable($layers, $this->connectionLayer($connection))) {
                $connections[] = $connection;
            }
        }
        $connections = array_values(array_filter($connections, static fn (array $connection): bool => isset(
            $nodes[(string) ($connection['from'] ?? '')],
            $nodes[(string) ($connection['to'] ?? '')],
        )));

        $positions = is_array($editor['positions'] ?? null) ? $editor['positions'] : [];
        $rects = $this->rectangles($nodes, $positions);
        $analysis = $this->analyse($rects, $connections);
        $paperMode = (string) data_get($editor, 'paper.mode', 'as_drawn');
        if ($paperMode === 'auto') $paperMode = 'as_drawn';

        if ($nodes === []) {
            return [
                'mode' => 'empty',
                'sheets' => [$this->emptySheet()],
                'summary' => [
                    'mode' => 'empty',
                    'sheet_count' => 1,
                    'readability' => 'empty',
                    'message' => 'No printable SLD objects are available.',
                    'minimum_scale' => 1.0,
                ],
            ];
        }

        $topologyProfile = (string) data_get($graph, 'meta.topology_profile', '');
        $professionalPaper = (bool) data_get($editor, 'paper.professional', true);
        if ($topologyProfile === 'professional_physical'
            && $professionalPaper
            && in_array($paperMode, ['as_drawn', 'single', 'multi'], true)) {
            return $this->professionalSet($graph, $nodes, $connections);
        }

        $catalog = $paperMode === 'catalog'
            || ($paperMode === 'auto' && count($nodes) >= 18 && count($connections) <= max(1, (int) floor(count($nodes) * 0.05)));
        if ($catalog) {
            $sheets = $this->catalogSheets($nodes);
            return $this->result('catalog', $sheets, 1.0, 'Symbol board detected. Symbols were paginated into readable catalogue sheets.');
        }

        if ($paperMode === 'as_drawn' || $paperMode === 'single') {
            $drawingBounds = $this->drawingBounds(array_keys($nodes), $rects, $editor, $connections, $layers);
            $sheet = $this->detailSheet(array_keys($nodes), $rects, 1, 1, 'Single-line diagram · As drawn', 'as_drawn', $drawingBounds, 2.05);
            $message = $paperMode === 'as_drawn'
                ? 'WYSIWYG export preserves the saved Model Space composition, symbol transforms, and wire routes on one A3 sheet.'
                : 'The complete saved drawing is fitted to one A3 sheet without changing relative object positions.';
            return $this->result('as_drawn', [$sheet], (float) data_get($sheet, 'transform.scale', 1.0), $message);
        }

        $singleReadable = $analysis['scale'] >= self::MIN_READABLE_SCALE
            && $analysis['node_count'] <= 18
            && $analysis['overlaps'] === 0;

        if ($paperMode === 'single' || ($paperMode === 'auto' && $singleReadable)) {
            $sheet = $this->detailSheet(array_keys($nodes), $rects, 1, 1, 'System single-line diagram', 'single');
            $message = $singleReadable
                ? 'The complete drawing fits one A3 sheet at a readable scale.'
                : 'Single-sheet mode is active. Readability warnings are shown because the drawing exceeds the recommended A3 density.';
            return $this->result('single', [$sheet], (float) $analysis['scale'], $message, $singleReadable ? 'pass' : 'warning');
        }

        $clusters = $this->detailClusters($nodes, $connections, $rects);
        if (count($clusters) === 1 && $analysis['scale'] >= self::MIN_READABLE_SCALE) {
            $sheet = $this->detailSheet($clusters[0], $rects, 1, 1, 'System single-line diagram', 'single');
            return $this->result('single', [$sheet], (float) $analysis['scale'], 'The complete drawing fits one A3 sheet at a readable scale.');
        }

        $detailCount = max(1, count($clusters));
        $sheetCount = $detailCount + 1;
        $sheets = [];
        $sheets[] = $this->overviewSheet($nodes, $clusters, 1, $sheetCount);
        foreach ($clusters as $index => $ids) {
            $number = $index + 2;
            $sheets[] = $this->detailSheet(
                $ids,
                $rects,
                $number,
                $sheetCount,
                $this->sheetTitle($ids, $nodes, $number),
                'detail',
            );
        }
        $sheets = $this->attachCrossReferences($sheets, $connections);

        $detailScales = array_map(
            static fn (array $sheet): float => (float) data_get($sheet, 'transform.scale', 1.0),
            array_values(array_filter($sheets, static fn (array $sheet): bool => ($sheet['type'] ?? '') === 'detail')),
        );
        $result = $this->result(
            'multi',
            $sheets,
            $detailScales !== [] ? min($detailScales) : 1.0,
            'The drawing exceeds readable single-sheet capacity. NEXORIN created one overview sheet and '.count($clusters).' readable detail sheet(s).',
        );
        $result['summary']['single_sheet_scale'] = round((float) $analysis['scale'], 3);

        return $result;
    }

    /** @param array<int, array<string, mixed>> $sheets @return array<string, mixed> */
    private function result(string $mode, array $sheets, float $minimumScale, string $message, string $readability = 'pass'): array
    {
        return [
            'mode' => $mode,
            'sheets' => array_values($sheets),
            'summary' => [
                'mode' => $mode,
                'sheet_count' => count($sheets),
                'readability' => $readability,
                'message' => $message,
                'minimum_scale' => round($minimumScale, 3),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function emptySheet(): array
    {
        return [
            'number' => 1,
            'count' => 1,
            'type' => 'empty',
            'title' => 'Single-line diagram',
            'node_ids' => [],
            'positions' => [],
            'transform' => $this->transformForBounds(['left' => 0, 'top' => 0, 'right' => 144, 'bottom' => 120]),
            'cross_references' => [],
        ];
    }

    /**
     * Compose the APP67L.3 reference-grade A3 drawing set.
     *
     * Model Space remains untouched. The positions below are paper-space
     * centres used only by the controlled A3 export. This gives the physical
     * topology a stable engineering composition while preserving the saved
     * drafting geometry in Model Space.
     *
     * @param array<string, array<string, mixed>> $nodes
     * @param array<int, array<string, mixed>> $connections
     * @return array<string, mixed>
     */
    private function professionalSet(array $graph, array $nodes, array $connections): array
    {
        $positions = $this->professionalPositions($nodes);
        $allNodeIds = array_values(array_keys($nodes));
        $sheetCount = 6;
        $sheets = [
            [
                'number' => 1,
                'count' => $sheetCount,
                'type' => 'professional_sld',
                'paper' => 'A3',
                'orientation' => 'landscape',
                'title' => 'Single-line diagram · On-grid PV system',
                'node_ids' => $allNodeIds,
                'positions' => $positions,
                'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
                'bounds' => ['left' => 50.0, 'top' => 50.0, 'right' => 1274.0, 'bottom' => 974.0],
                'cross_references' => [],
            ],
            [
                'number' => 2,
                'count' => $sheetCount,
                'type' => 'control_schematic',
                'paper' => 'A3',
                'orientation' => 'landscape',
                'title' => 'Zero-export metering, control and export-trip schematic',
                'node_ids' => $allNodeIds,
                'positions' => $positions,
                'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
                'cross_references' => [],
            ],
            [
                'number' => 3,
                'count' => $sheetCount,
                'type' => 'string_schedule',
                'paper' => 'A3',
                'orientation' => 'landscape',
                'title' => 'MPPT, string and equipment schedule',
                'node_ids' => [],
                'positions' => [],
                'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
                'cross_references' => [],
            ],
            [
                'number' => 4,
                'count' => $sheetCount,
                'type' => 'cable_schedule',
                'paper' => 'A3',
                'orientation' => 'landscape',
                'title' => 'Cable and interconnection schedule · 1 of 2',
                'schedule_start' => 0,
                'schedule_limit' => 27,
                'node_ids' => [],
                'positions' => [],
                'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
                'cross_references' => [],
            ],
            [
                'number' => 5,
                'count' => $sheetCount,
                'type' => 'cable_schedule',
                'paper' => 'A3',
                'orientation' => 'landscape',
                'title' => 'Cable and interconnection schedule · 2 of 2',
                'schedule_start' => 27,
                'schedule_limit' => 26,
                'node_ids' => [],
                'positions' => [],
                'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
                'cross_references' => [],
            ],
            [
                'number' => 6,
                'count' => $sheetCount,
                'type' => 'protection_schedule',
                'paper' => 'A3',
                'orientation' => 'landscape',
                'title' => 'Protection, metering and engineering data gaps',
                'node_ids' => [],
                'positions' => [],
                'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
                'cross_references' => [],
            ],
        ];

        return [
            'mode' => 'professional',
            'sheets' => $sheets,
            'summary' => [
                'mode' => 'professional',
                'composition_version' => '67L.4-FINAL',
                'sheet_count' => $sheetCount,
                'readability' => 'pass',
                'message' => 'APP67L.4-FINAL preserves the stable six-sheet engineering composition while adding release-readiness gates, independent check and approval integrity, revision comparison, exact issue preview, Material Catalog traceability, and checksum-backed controlled export packages.',
                'minimum_scale' => 1.0,
                'topology_profile' => (string) data_get($graph, 'meta.topology_profile', ''),
                'connection_count' => count($connections),
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @return array<string, array{x:float,y:float,w:float,h:float}>
     */
    private function professionalPositions(array $nodes): array
    {
        $positions = [];
        $branchRows = [1 => 318.0, 2 => 535.0, 3 => 710.0];
        $columns = [
            'pv_group' => [122.0, 126.0, 106.0],
            'dc_isolator' => [350.0, 108.0, 92.0],
            'inverter' => [690.0, 132.0, 112.0],
            'ac_breaker' => [830.0, 104.0, 90.0],
            'ac_isolator' => [955.0, 110.0, 90.0],
        ];

        foreach ($nodes as $id => $node) {
            if (preg_match('/^(pv_group|dc_isolator|inverter|ac_breaker|ac_isolator)_(\d+)$/', $id, $match)) {
                $branch = max(1, min(3, (int) $match[2]));
                [$x, $w, $h] = $columns[$match[1]];
                $positions[$id] = ['x' => $x, 'y' => $branchRows[$branch], 'w' => $w, 'h' => $h];
                continue;
            }
            if (preg_match('/^dc_spd_(\d+)$/', $id, $match)) {
                $branch = max(1, min(3, (int) $match[1]));
                $positions[$id] = ['x' => 555.0, 'y' => $branchRows[$branch] + 94.0, 'w' => 92.0, 'h' => 76.0];
                continue;
            }
        }

        $shared = [
            'ac_combiner_01' => ['x' => 1015.0, 'y' => 390.0, 'w' => 110.0, 'h' => 98.0],
            'pcc_breaker_01' => ['x' => 1110.0, 'y' => 390.0, 'w' => 96.0, 'h' => 92.0],
            'ct_01' => ['x' => 1190.0, 'y' => 390.0, 'w' => 84.0, 'h' => 82.0],
            'pcc_disconnect_01' => ['x' => 1260.0, 'y' => 390.0, 'w' => 96.0, 'h' => 92.0],
            'grid_01' => ['x' => 1245.0, 'y' => 525.0, 'w' => 110.0, 'h' => 100.0],
            'meter_01' => ['x' => 1100.0, 'y' => 545.0, 'w' => 110.0, 'h' => 98.0],
            'export_relay_01' => ['x' => 1100.0, 'y' => 665.0, 'w' => 110.0, 'h' => 98.0],
            'ac_spd_backup_01' => ['x' => 1015.0, 'y' => 510.0, 'w' => 90.0, 'h' => 78.0],
            'ac_spd_01' => ['x' => 1015.0, 'y' => 620.0, 'w' => 92.0, 'h' => 80.0],
            'data_logger_01' => ['x' => 710.0, 'y' => 748.0, 'w' => 126.0, 'h' => 96.0],
            'plant_controller_01' => ['x' => 895.0, 'y' => 748.0, 'w' => 130.0, 'h' => 96.0],
            'pe_bus_01' => ['x' => 510.0, 'y' => 890.0, 'w' => 132.0, 'h' => 84.0],
            'ground_rod_01' => ['x' => 710.0, 'y' => 890.0, 'w' => 112.0, 'h' => 84.0],
            'battery_01' => ['x' => 110.0, 'y' => 705.0, 'w' => 132.0, 'h' => 104.0],
            'backup_panel_01' => ['x' => 1100.0, 'y' => 820.0, 'w' => 132.0, 'h' => 104.0],
        ];
        foreach ($shared as $id => $position) {
            if (isset($nodes[$id])) $positions[$id] = $position;
        }

        $unplaced = array_values(array_diff(array_keys($nodes), array_keys($positions)));
        foreach ($unplaced as $index => $id) {
            $positions[$id] = [
                'x' => 120.0 + (($index % 6) * 180.0),
                'y' => 820.0 + (intdiv($index, 6) * 95.0),
                'w' => 128.0,
                'h' => 88.0,
            ];
        }

        return $positions;
    }

    /** @param array<string, array<string, mixed>> $nodes @return array<int, array<string, mixed>> */
    private function catalogSheets(array $nodes): array
    {
        uasort($nodes, fn (array $a, array $b): int => [$this->groupOrder($this->nodeGroup('', $a)), (string) ($a['title'] ?? '')]
            <=> [$this->groupOrder($this->nodeGroup('', $b)), (string) ($b['title'] ?? '')]);
        $chunks = array_chunk(array_keys($nodes), self::CATALOG_PAGE_SIZE);
        $count = count($chunks);
        $sheets = [];
        foreach ($chunks as $pageIndex => $ids) {
            $positions = [];
            foreach ($ids as $index => $id) {
                $column = $index % 4;
                $row = intdiv($index, 4);
                $positions[$id] = [
                    'x' => 260.0 + ($column * 385.0),
                    'y' => 330.0 + ($row * 155.0),
                ];
            }
            $sheets[] = [
                'number' => $pageIndex + 1,
                'count' => $count,
                'type' => 'catalog',
                'title' => 'Electrical symbol register · '.($pageIndex + 1),
                'node_ids' => $ids,
                'positions' => $positions,
                'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
                'cross_references' => [],
            ];
        }
        return $sheets;
    }

    /** @param array<string, array<string, mixed>> $nodes @param array<int, array<int, string>> $clusters @return array<string, mixed> */
    private function overviewSheet(array $nodes, array $clusters, int $number, int $count): array
    {
        $nodeSheet = [];
        foreach ($clusters as $index => $ids) {
            foreach ($ids as $id) {
                $nodeSheet[$id] = $index + 2;
            }
        }
        $grouped = [];
        foreach ($nodes as $id => $node) {
            $group = $this->nodeGroup($id, $node);
            $grouped[$group]['ids'][] = $id;
            $grouped[$group]['sheets'][$nodeSheet[$id] ?? 2] = true;
        }
        uksort($grouped, fn (string $a, string $b): int => $this->groupOrder($a) <=> $this->groupOrder($b));
        $groups = [];
        foreach ($grouped as $group => $data) {
            $groups[] = [
                'key' => $group,
                'title' => $this->groupTitle($group),
                'count' => count($data['ids'] ?? []),
                'node_ids' => array_values($data['ids'] ?? []),
                'detail_sheets' => array_map('intval', array_keys($data['sheets'] ?? [])),
            ];
        }
        foreach ($groups as $index => &$group) {
            $column = $index % 3;
            $row = intdiv($index, 3);
            $group['x'] = 305.0 + ($column * 520.0);
            $group['y'] = 360.0 + ($row * 230.0);
        }
        unset($group);

        return [
            'number' => $number,
            'count' => $count,
            'type' => 'overview',
            'title' => 'System overview',
            'node_ids' => array_keys($nodes),
            'positions' => [],
            'transform' => ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0],
            'overview_groups' => $groups,
            'cross_references' => [],
        ];
    }

    /** @param array<int, string> $ids @param array<string, array<string, float>> $rects @return array<string, mixed> */
    private function detailSheet(array $ids, array $rects, int $number, int $count, string $title, string $type, ?array $boundsOverride = null, float $maxScale = 1.0): array
    {
        $bounds = $boundsOverride ?? $this->boundsForIds($ids, $rects);
        $transform = $this->transformForBounds($bounds, $maxScale);
        $positions = [];
        foreach ($ids as $id) {
            if (! isset($rects[$id])) {
                continue;
            }
            $rect = $rects[$id];
            $positions[$id] = array_merge(
                $this->mapPoint($rect['x'] + ($rect['w'] / 2), $rect['y'] + ($rect['h'] / 2), $transform),
                [
                    'w' => $rect['w'] * $transform['scale'],
                    'h' => $rect['h'] * $transform['scale'],
                    'model_x' => $rect['x'],
                    'model_y' => $rect['y'],
                    'model_w' => $rect['w'],
                    'model_h' => $rect['h'],
                ],
            );
        }
        return [
            'number' => $number,
            'count' => $count,
            'type' => $type,
            'title' => $title,
            'node_ids' => array_values($ids),
            'bounds' => $bounds,
            'positions' => $positions,
            'transform' => $transform,
            'cross_references' => [],
        ];
    }

    /** @param array<int, array<string, mixed>> $sheets @param array<int, array<string, mixed>> $connections @return array<int, array<string, mixed>> */
    private function attachCrossReferences(array $sheets, array $connections): array
    {
        $nodeSheet = [];
        foreach ($sheets as $sheet) {
            if (($sheet['type'] ?? '') === 'overview') {
                continue;
            }
            foreach ($sheet['node_ids'] ?? [] as $id) {
                $nodeSheet[(string) $id] = (int) $sheet['number'];
            }
        }
        foreach ($connections as $connection) {
            $from = (string) ($connection['from'] ?? '');
            $to = (string) ($connection['to'] ?? '');
            $fromSheet = $nodeSheet[$from] ?? null;
            $toSheet = $nodeSheet[$to] ?? null;
            if (! $fromSheet || ! $toSheet || $fromSheet === $toSheet) {
                continue;
            }
            foreach ($sheets as &$sheet) {
                $number = (int) ($sheet['number'] ?? 0);
                if ($number === $fromSheet) {
                    $sheet['cross_references'][] = [
                        'direction' => 'to',
                        'node_id' => $from,
                        'other_node_id' => $to,
                        'other_sheet' => $toSheet,
                        'connection' => $connection,
                    ];
                } elseif ($number === $toSheet) {
                    $sheet['cross_references'][] = [
                        'direction' => 'from',
                        'node_id' => $to,
                        'other_node_id' => $from,
                        'other_sheet' => $fromSheet,
                        'connection' => $connection,
                    ];
                }
            }
            unset($sheet);
        }
        return $sheets;
    }

    /** @param array<string, array<string, mixed>> $nodes @param array<int, array<string, mixed>> $connections @param array<string, array<string, float>> $rects @return array<int, array<int, string>> */
    private function detailClusters(array $nodes, array $connections, array $rects): array
    {
        $adjacency = array_fill_keys(array_keys($nodes), []);
        foreach ($connections as $connection) {
            $from = (string) ($connection['from'] ?? '');
            $to = (string) ($connection['to'] ?? '');
            if (isset($adjacency[$from], $adjacency[$to])) {
                $adjacency[$from][] = $to;
                $adjacency[$to][] = $from;
            }
        }

        $seen = [];
        $components = [];
        foreach (array_keys($nodes) as $start) {
            if (isset($seen[$start])) {
                continue;
            }
            $stack = [$start];
            $component = [];
            while ($stack !== []) {
                $id = array_pop($stack);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $component[] = $id;
                foreach ($adjacency[$id] ?? [] as $next) {
                    if (! isset($seen[$next])) {
                        $stack[] = $next;
                    }
                }
            }
            $components[] = $component;
        }

        $clusters = [];
        foreach ($components as $component) {
            foreach ($this->splitReadable($component, $rects) as $chunk) {
                $clusters[] = $chunk;
            }
        }

        // Pack small disconnected components when the combined viewport remains readable.
        usort($clusters, fn (array $a, array $b): int => $this->boundsForIds($a, $rects)['left'] <=> $this->boundsForIds($b, $rects)['left']);
        $packed = [];
        foreach ($clusters as $cluster) {
            $placed = false;
            foreach ($packed as $index => $existing) {
                $candidate = array_values(array_unique(array_merge($existing, $cluster)));
                if (count($candidate) <= self::MAX_DETAIL_NODES && $this->scaleForBounds($this->boundsForIds($candidate, $rects)) >= self::MIN_READABLE_SCALE) {
                    $packed[$index] = $candidate;
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $packed[] = $cluster;
            }
        }
        return array_values($packed);
    }

    /** @param array<int, string> $ids @param array<string, array<string, float>> $rects @return array<int, array<int, string>> */
    private function splitReadable(array $ids, array $rects): array
    {
        if (count($ids) <= self::MAX_DETAIL_NODES && $this->scaleForBounds($this->boundsForIds($ids, $rects)) >= self::MIN_READABLE_SCALE) {
            return [$ids];
        }
        if (count($ids) <= 1) {
            return [$ids];
        }
        $bounds = $this->boundsForIds($ids, $rects);
        $horizontal = ($bounds['right'] - $bounds['left']) >= ($bounds['bottom'] - $bounds['top']);
        usort($ids, function (string $a, string $b) use ($rects, $horizontal): int {
            $ra = $rects[$a];
            $rb = $rects[$b];
            $ca = $horizontal ? $ra['x'] + ($ra['w'] / 2) : $ra['y'] + ($ra['h'] / 2);
            $cb = $horizontal ? $rb['x'] + ($rb['w'] / 2) : $rb['y'] + ($rb['h'] / 2);
            return $ca <=> $cb;
        });
        $mid = max(1, intdiv(count($ids), 2));
        return array_merge(
            $this->splitReadable(array_slice($ids, 0, $mid), $rects),
            $this->splitReadable(array_slice($ids, $mid), $rects),
        );
    }

    /** @param array<int, string> $ids @param array<string, array<string, mixed>> $nodes */
    private function sheetTitle(array $ids, array $nodes, int $number): string
    {
        $counts = [];
        foreach ($ids as $id) {
            $group = $this->nodeGroup($id, $nodes[$id] ?? []);
            $counts[$group] = ($counts[$group] ?? 0) + 1;
        }
        arsort($counts);
        $groups = array_slice(array_keys($counts), 0, 2);
        $labels = array_map(fn (string $group): string => $this->groupTitle($group), $groups);
        return ($labels !== [] ? implode(' & ', $labels) : 'SLD detail').' · Sheet '.$number;
    }

    /** @param array<string, array<string, float>> $rects @param array<int, array<string, mixed>> $connections @return array<string, mixed> */
    private function analyse(array $rects, array $connections): array
    {
        $bounds = $this->boundsForIds(array_keys($rects), $rects);
        $overlaps = 0;
        $values = array_values($rects);
        for ($i = 0; $i < count($values); $i++) {
            for ($j = $i + 1; $j < count($values); $j++) {
                if ($this->rectanglesOverlap($values[$i], $values[$j], 8.0)) {
                    $overlaps++;
                }
            }
        }
        return [
            'node_count' => count($rects),
            'connection_count' => count($connections),
            'bounds' => $bounds,
            'scale' => $this->scaleForBounds($bounds),
            'overlaps' => $overlaps,
        ];
    }

    /** @param array<string, array<string, mixed>> $nodes @param array<string, array<string, mixed>> $stored @return array<string, array<string, float>> */
    private function rectangles(array $nodes, array $stored): array
    {
        $defaults = [
            'pv_array' => [110.0, 320.0], 'dc_protection' => [355.0, 320.0], 'inverter' => [600.0, 320.0],
            'ac_protection' => [845.0, 320.0], 'meter' => [1090.0, 320.0], 'grid' => [1335.0, 320.0],
            'battery' => [600.0, 610.0], 'backup_panel' => [900.0, 610.0],
        ];
        $rects = [];
        $fallbackIndex = 0;
        foreach ($nodes as $id => $node) {
            $position = is_array($stored[$id] ?? null) ? $stored[$id] : null;
            if ($position) {
                $rects[$id] = [
                    'x' => (float) ($position['x'] ?? 0),
                    'y' => (float) ($position['y'] ?? 0),
                    'w' => max(80.0, (float) ($position['w'] ?? 144)),
                    'h' => max(50.0, (float) ($position['h'] ?? 120)),
                ];
                continue;
            }
            [$x, $y] = $defaults[$id] ?? [120.0 + (($fallbackIndex % 5) * 245.0), 600.0 + (intdiv($fallbackIndex, 5) * 190.0)];
            $rects[$id] = ['x' => $x, 'y' => $y, 'w' => 144.0, 'h' => 120.0];
            $fallbackIndex++;
        }
        return $rects;
    }

    /** @param array<int, string> $ids @param array<string, array<string, float>> $rects @return array{left:float,top:float,right:float,bottom:float} */
    private function drawingBounds(array $ids, array $rects, array $editor, array $connections = [], array $layers = []): array
    {
        $bounds = $this->boundsForIds($ids, $rects);
        // Remove the node-only padding before extending with routes and drafting geometry.
        $left = $bounds['left'] + 70.0;
        $top = $bounds['top'] + 70.0;
        $right = $bounds['right'] - 70.0;
        $bottom = $bounds['bottom'] - 70.0;
        $include = static function (float $x, float $y) use (&$left, &$top, &$right, &$bottom): void {
            $left = min($left, $x); $top = min($top, $y); $right = max($right, $x); $bottom = max($bottom, $y);
        };
        // Only active printable connections may influence the paper bounds.
        // Deleted/rebuilt wires can leave stale route entries in saved editor
        // state. Including those invisible points made the real drawing tiny
        // and shifted it away from the paper centre.
        $activeRouteKeys = [];
        foreach ($connections as $index => $connection) {
            if (! is_array($connection)) continue;
            foreach ([$connection['_id'] ?? null, $connection['id'] ?? null, $this->connectionId($connection, $index)] as $key) {
                if (filled($key)) $activeRouteKeys[(string) $key] = true;
            }
        }
        foreach (is_array($editor['routes'] ?? null) ? $editor['routes'] : [] as $routeKey => $route) {
            if (! isset($activeRouteKeys[(string) $routeKey])) continue;
            foreach (is_array($route) ? $route : [] as $point) {
                if (is_array($point) && is_numeric($point['x'] ?? null) && is_numeric($point['y'] ?? null)) {
                    $include((float) $point['x'], (float) $point['y']);
                }
            }
        }
        foreach (is_array($editor['manual_lines'] ?? null) ? $editor['manual_lines'] : [] as $line) {
            if (!is_array($line)) continue;
            $layer = (string) ($line['layer'] ?? 'SLD-LINEWORK');
            $resolvedLayer = $layer === 'SLD-ANNOTATION' ? 'E-ANNOTATION' : (str_starts_with($layer, 'E-') ? $layer : 'E-BOUNDARY');
            if (! $this->layerPrintable($layers, $resolvedLayer)) continue;
            if (is_numeric($line['x1'] ?? null) && is_numeric($line['y1'] ?? null)) $include((float)$line['x1'], (float)$line['y1']);
            if (is_numeric($line['x2'] ?? null) && is_numeric($line['y2'] ?? null)) $include((float)$line['x2'], (float)$line['y2']);
        }
        foreach (is_array($editor['manual_shapes'] ?? null) ? $editor['manual_shapes'] : [] as $shape) {
            if (!is_array($shape) || !is_numeric($shape['x'] ?? null) || !is_numeric($shape['y'] ?? null)) continue;
            $layer = (string) ($shape['layer'] ?? 'SLD-LINEWORK');
            $resolvedLayer = str_starts_with($layer, 'E-') ? $layer : 'E-BOUNDARY';
            if (! $this->layerPrintable($layers, $resolvedLayer)) continue;
            $x=(float)$shape['x']; $y=(float)$shape['y']; $w=max(0.0,(float)($shape['w']??0)); $h=max(0.0,(float)($shape['h']??0));
            $include($x,$y); $include($x+$w,$y+$h);
        }
        if ($this->layerPrintable($layers, 'E-ANNOTATION')) {
            foreach (is_array($editor['annotations'] ?? null) ? $editor['annotations'] : [] as $note) {
                if (!is_array($note) || !is_numeric($note['x'] ?? null) || !is_numeric($note['y'] ?? null)) continue;
                $x=(float)$note['x']; $y=(float)$note['y']; $w=max(80.0,(float)($note['w']??240)); $h=max(32.0,(float)($note['h']??54));
                $include($x,$y); $include($x+$w,$y+$h);
            }
        }
        // Reserve enough breathing room for labels without sacrificing the
        // majority of the A3 topology viewport.
        $span = max($right - $left, $bottom - $top, 1.0);
        $padding = max(20.0, min(32.0, $span * 0.028));
        return ['left'=>$left-$padding,'top'=>$top-$padding,'right'=>$right+$padding,'bottom'=>$bottom+$padding];
    }

    /** @param array<int, string> $ids @param array<string, array<string, float>> $rects @return array{left:float,top:float,right:float,bottom:float} */
    private function boundsForIds(array $ids, array $rects): array
    {
        $left = INF; $top = INF; $right = -INF; $bottom = -INF;
        foreach ($ids as $id) {
            if (! isset($rects[$id])) {
                continue;
            }
            $rect = $rects[$id];
            $left = min($left, $rect['x']);
            $top = min($top, $rect['y']);
            $right = max($right, $rect['x'] + $rect['w']);
            $bottom = max($bottom, $rect['y'] + $rect['h']);
        }
        if (! is_finite($left)) {
            return ['left' => 0.0, 'top' => 0.0, 'right' => 144.0, 'bottom' => 120.0];
        }
        $padding = 70.0;
        return ['left' => $left - $padding, 'top' => $top - $padding, 'right' => $right + $padding, 'bottom' => $bottom + $padding];
    }

    /** @param array{left:float,top:float,right:float,bottom:float} $bounds @return array{scale:float,offset_x:float,offset_y:float} */
    private function transformForBounds(array $bounds, float $maxScale = 1.0): array
    {
        $width = max(1.0, $bounds['right'] - $bounds['left']);
        $height = max(1.0, $bounds['bottom'] - $bounds['top']);
        $scale = min(
            (self::PAPER_RIGHT - self::PAPER_LEFT) / $width,
            (self::PAPER_BOTTOM - self::PAPER_TOP) / $height,
            max(0.1, $maxScale),
        );
        $contentCenterX = ($bounds['left'] + $bounds['right']) / 2;
        $contentCenterY = ($bounds['top'] + $bounds['bottom']) / 2;
        $paperCenterX = (self::PAPER_LEFT + self::PAPER_RIGHT) / 2;
        $paperCenterY = (self::PAPER_TOP + self::PAPER_BOTTOM) / 2;
        return [
            'scale' => $scale,
            'offset_x' => $paperCenterX - ($contentCenterX * $scale),
            'offset_y' => $paperCenterY - ($contentCenterY * $scale),
        ];
    }

    /** @param array{left:float,top:float,right:float,bottom:float} $bounds */
    private function scaleForBounds(array $bounds): float
    {
        return (float) $this->transformForBounds($bounds)['scale'];
    }

    /** @param array{scale:float,offset_x:float,offset_y:float} $transform @return array{x:float,y:float} */
    private function mapPoint(float $x, float $y, array $transform): array
    {
        return ['x' => ($x * $transform['scale']) + $transform['offset_x'], 'y' => ($y * $transform['scale']) + $transform['offset_y']];
    }

    /** @param array<string, float> $a @param array<string, float> $b */
    private function rectanglesOverlap(array $a, array $b, float $gap): bool
    {
        return ! (
            ($a['x'] + $a['w'] + $gap) <= $b['x']
            || ($b['x'] + $b['w'] + $gap) <= $a['x']
            || ($a['y'] + $a['h'] + $gap) <= $b['y']
            || ($b['y'] + $b['h'] + $gap) <= $a['y']
        );
    }

    /** @return array<string, array<string, bool>> */
    private function layers(array $editor): array
    {
        $defaults = [
            'E-EQUIPMENT' => ['visible' => true, 'print' => true],
            'E-DC-POWER' => ['visible' => true, 'print' => true],
            'E-AC-POWER' => ['visible' => true, 'print' => true],
            'E-NEUTRAL' => ['visible' => true, 'print' => true],
            'E-PE' => ['visible' => true, 'print' => true],
            'E-COMM' => ['visible' => true, 'print' => true],
            'E-CONTROL' => ['visible' => true, 'print' => true],
            'E-PROTECTION' => ['visible' => true, 'print' => true],
            'E-METERING' => ['visible' => true, 'print' => true],
            'E-ANNOTATION' => ['visible' => true, 'print' => true],
            'E-BOUNDARY' => ['visible' => true, 'print' => true],
            'E-EXISTING' => ['visible' => true, 'print' => true],
            'E-FUTURE' => ['visible' => true, 'print' => true],
        ];
        foreach (is_array($editor['layers'] ?? null) ? $editor['layers'] : [] as $name => $state) {
            if (! isset($defaults[$name]) || ! is_array($state)) {
                continue;
            }
            $defaults[$name] = [
                'visible' => (bool) ($state['visible'] ?? true),
                'print' => (bool) ($state['print'] ?? true),
            ];
        }
        return $defaults;
    }

    /** @param array<string, array<string, bool>> $layers */
    private function layerPrintable(array $layers, string $name): bool
    {
        return (bool) ($layers[$name]['print'] ?? true);
    }

    /** @param array<string, mixed> $node */
    public function nodeLayer(string $id, array $node): string
    {
        $kind = (string) ($node['kind'] ?? $id);
        $symbol = (string) ($node['symbol'] ?? $kind);
        if (in_array($symbol, ['dc_protection', 'dc_breaker', 'dc_isolator', 'dc_fuse', 'dc_spd', 'gfpd', 'afci', 'ac_protection', 'ac_breaker', 'ac_isolator', 'ac_spd', 'lightning_arrester', 'battery_disconnect'], true)) return 'E-PROTECTION';
        if (in_array($symbol, ['meter', 'app_pln', 'ct', 'pt', 'relay', 'pcc'], true)) return 'E-METERING';
        if (in_array($symbol, ['ground', 'ground_rod', 'pe_bus'], true)) return 'E-PE';
        if (in_array($symbol, ['monitoring', 'data_logger', 'plant_controller', 'scada', 'weather_station', 'irradiance_sensor', 'bms'], true)) return 'E-CONTROL';
        return 'E-EQUIPMENT';
    }

    /** @param array<string, mixed> $connection */
    public function connectionLayer(array $connection): string
    {
        $network = strtolower((string) ($connection['network'] ?? ''));
        return match (true) {
            str_contains($network, 'communication') => 'E-COMM',
            str_contains($network, 'protective') || $network === 'pe' => 'E-PE',
            str_contains($network, 'neutral') => 'E-NEUTRAL',
            str_contains($network, 'control') => 'E-CONTROL',
            str_contains($network, 'measurement') => 'E-METERING',
            str_contains($network, 'dc') || str_contains($network, 'battery') => 'E-DC-POWER',
            default => 'E-AC-POWER',
        };
    }

    /** @param array<string, mixed> $node */
    private function nodeGroup(string $id, array $node): string
    {
        $symbol = (string) ($node['symbol'] ?? $node['kind'] ?? $id);
        if (in_array($symbol, ['pv_module', 'pv_string', 'pv_array', 'sub_array', 'junction_box', 'combiner', 'power_optimizer', 'rsd', 'dc_isolator', 'dc_fuse', 'dc_breaker', 'dc_spd', 'dc_protection', 'dc_busbar', 'gfpd', 'afci'], true)) return 'pv_dc';
        if (in_array($symbol, ['mppt', 'dc_dc_converter', 'inverter', 'hybrid_inverter', 'offgrid_inverter', 'central_inverter', 'pcs', 'microinverter'], true)) return 'conversion';
        if (in_array($symbol, ['battery_cell', 'battery_module', 'battery_rack', 'battery', 'bms', 'battery_disconnect'], true)) return 'storage';
        if (in_array($symbol, ['ac_protection', 'ac_breaker', 'ac_isolator', 'ac_spd', 'ac_combiner', 'panel', 'main_panel', 'critical_panel', 'backup_panel', 'load', 'transfer_switch', 'epo'], true)) return 'ac_distribution';
        if (in_array($symbol, ['meter', 'app_pln', 'pcc', 'grid', 'transformer', 'generator', 'ct', 'pt', 'relay'], true)) return 'grid_metering';
        return 'monitoring_safety';
    }

    private function groupTitle(string $group): string
    {
        return match ($group) {
            'pv_dc' => 'PV generation & DC protection',
            'conversion' => 'Power conversion',
            'storage' => 'Battery & BESS',
            'ac_distribution' => 'AC distribution & loads',
            'grid_metering' => 'Metering, transformer & grid',
            default => 'Monitoring, control & safety',
        };
    }

    private function groupOrder(string $group): int
    {
        return array_search($group, ['pv_dc', 'conversion', 'storage', 'ac_distribution', 'grid_metering', 'monitoring_safety'], true) ?: 0;
    }

    private function connectionId(array $connection, int $index): string
    {
        if (filled($connection['id'] ?? null)) return (string) $connection['id'];
        return 'source_'.($connection['from'] ?? 'from').'__'.($connection['to'] ?? 'to').'__'.$index;
    }
}
