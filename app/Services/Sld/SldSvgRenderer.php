<?php

namespace App\Services\Sld;

class SldSvgRenderer
{
    private const WIDTH = 1680;
    private const HEIGHT = 1188;

    /** @var array<int, array{left:float,top:float,right:float,bottom:float}> */
    private array $wireLabelRects = [];

    /** @var array<int, array{left:float,top:float,right:float,bottom:float}> */
    private array $deviceLabelObstacles = [];

    /**
     * Render an A3-landscape engineering sheet from the auditable SLD graph.
     * Technical data remains sourced from the graph. This class only controls
     * paper-space layout, linework, labels, and document identity.
     */
    private readonly SldSmartRouter $smartRouter;
    private readonly SldLabelPlacement $labelPlacement;
    private readonly SldVisualRegression $visualRegression;

    public function __construct(
        private readonly SldPaperComposer $paperComposer,
        ?SldSmartRouter $smartRouter = null,
        ?SldLabelPlacement $labelPlacement = null,
        ?SldVisualRegression $visualRegression = null,
    ) {
        $this->smartRouter = $smartRouter ?? new SldSmartRouter();
        $this->labelPlacement = $labelPlacement ?? new SldLabelPlacement();
        $this->visualRegression = $visualRegression ?? new SldVisualRegression();
    }

    public function render(array $graph): string
    {
        $set = $this->renderSet($graph);

        return $set['pages'][0] ?? '';
    }

    public function renderCombined(array $graph): string
    {
        $set = $this->renderSet($graph);
        $pages = $set['pages'];
        if (count($pages) <= 1) return $pages[0] ?? '';
        $gap = 36;
        $height = (self::HEIGHT * count($pages)) + ($gap * (count($pages) - 1));
        $visualRegression = $this->visualRegression->normalizeMetadata(is_array($graph['editor'] ?? null) ? $graph['editor'] : []);
        $parts = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.self::WIDTH.' '.$height.'" role="img" aria-label="NEXORIN multi-sheet SLD set" data-visual-regression-version="'.SldVisualRegression::VERSION.'" data-visual-regression-contract="'.SldVisualRegression::CONTRACT.'" data-visual-regression-fingerprint="'.$this->escape((string) ($visualRegression['fingerprint'] ?? '')).'" data-visual-regression-status="'.$this->escape((string) ($visualRegression['status'] ?? 'pass')).'">'];
        foreach ($pages as $index => $page) {
            $inner = preg_replace('/^.*?<svg[^>]*>/s', '', $page, 1) ?? $page;
            $inner = preg_replace('/<\/svg>\s*$/s', '', $inner, 1) ?? $inner;
            $y = $index * (self::HEIGHT + $gap);
            $parts[] = '<g transform="translate(0 '.$y.')">'.$inner.'</g>';
        }
        $parts[] = '</svg>';
        return implode('', $parts);
    }

    /**
     * Render every intelligent paper-space sheet.
     *
     * @return array{pages: array<int, string>, summary: array<string, mixed>, sheets: array<int, array<string, mixed>>}
     */
    public function renderSet(array $graph): array
    {
        $data = $this->drawingData($graph);
        $composition = $this->paperComposer->compose($graph);
        $pages = [];

        foreach ($composition['sheets'] as $sheet) {
            $pages[] = $this->renderSheet($graph, $data, $sheet, $composition['summary']);
        }

        return [
            'pages' => $pages,
            'summary' => $composition['summary'],
            'sheets' => array_map(static fn (array $sheet): array => [
                'number' => (int) ($sheet['number'] ?? 1),
                'count' => (int) ($sheet['count'] ?? 1),
                'type' => (string) ($sheet['type'] ?? 'single'),
                'title' => (string) ($sheet['title'] ?? 'Single-line diagram'),
                'node_count' => count($sheet['node_ids'] ?? []),
            ], $composition['sheets']),
        ];
    }

    /** @return array<string, mixed> */
    private function drawingData(array $graph): array
    {
        $editor = is_array($graph['editor'] ?? null) ? $graph['editor'] : [];
        $excluded = array_fill_keys(array_map('strval', is_array($editor['excluded_nodes'] ?? null) ? $editor['excluded_nodes'] : []), true);

        $nodes = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) {
                continue;
            }
            $nodes[(string) $node['id']] = $node;
        }
        foreach (is_array($editor['manual_nodes'] ?? null) ? $editor['manual_nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) {
                continue;
            }
            $node['_manual'] = true;
            $nodes[(string) $node['id']] = $node;
        }
        $nodes = array_filter($nodes, static fn (array $node, string $id): bool => ! isset($excluded[$id]), ARRAY_FILTER_USE_BOTH);

        $connections = [];
        foreach (is_array($graph['connections'] ?? null) ? $graph['connections'] : [] as $index => $connection) {
            if (! is_array($connection)) {
                continue;
            }
            $connection['_id'] = $this->connectionId($connection, $index);
            $connections[] = $connection;
        }
        foreach (is_array($editor['manual_connections'] ?? null) ? $editor['manual_connections'] : [] as $index => $connection) {
            if (! is_array($connection)) {
                continue;
            }
            $connection['_manual'] = true;
            $connection['_id'] = filled($connection['id'] ?? null) ? (string) $connection['id'] : 'manual_'.$this->connectionId($connection, $index);
            $connections[] = $connection;
        }
        $connections = array_values(array_filter($connections, static function (array $connection) use ($nodes): bool {
            return isset($nodes[(string) ($connection['from'] ?? '')], $nodes[(string) ($connection['to'] ?? '')]);
        }));

        $layerState = is_array($editor['layers'] ?? null) ? $editor['layers'] : [];
        $prints = static function (string $layer) use ($layerState): bool {
            return (bool) data_get($layerState, $layer.'.print', true);
        };
        $manualLines = array_values(array_filter(
            is_array($editor['manual_lines'] ?? null) ? $editor['manual_lines'] : [],
            static function (mixed $line) use ($prints): bool {
                if (! is_array($line)) return false;
                $layer = (string) ($line['layer'] ?? 'SLD-LINEWORK');
                $resolved = $layer === 'SLD-ANNOTATION' ? 'E-ANNOTATION' : (str_starts_with($layer, 'E-') ? $layer : 'E-BOUNDARY');
                return $prints($resolved);
            },
        ));
        $manualShapes = array_values(array_filter(
            is_array($editor['manual_shapes'] ?? null) ? $editor['manual_shapes'] : [],
            static function (mixed $shape) use ($prints): bool {
                if (! is_array($shape)) return false;
                $layer = (string) ($shape['layer'] ?? 'SLD-LINEWORK');
                $resolved = str_starts_with($layer, 'E-') ? $layer : 'E-BOUNDARY';
                return $prints($resolved);
            },
        ));
        $annotations = $prints('E-ANNOTATION')
            ? (is_array($editor['annotations'] ?? null) ? $editor['annotations'] : [])
            : [];

        return [
            'meta' => is_array($graph['meta'] ?? null) ? $graph['meta'] : [],
            'editor' => $editor,
            'nodes' => $nodes,
            'connections' => $connections,
            'manual_lines' => $manualLines,
            'manual_shapes' => $manualShapes,
            'routes' => is_array($editor['routes'] ?? null) ? $editor['routes'] : [],
            'annotations' => $annotations,
        ];
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $sheet @param array<string, mixed> $paperSummary */
    private function renderSheet(array $graph, array $data, array $sheet, array $paperSummary): string
    {
        $paperType = (string) ($sheet['type'] ?? 'single');
        if (in_array($paperType, ['professional_sld', 'control_schematic', 'string_schedule', 'cable_schedule', 'protection_schedule', 'engineering_schedule'], true)) {
            return $this->renderProfessionalSheet($graph, $data, $sheet, $paperSummary);
        }

        $meta = $data['meta'];
        $editor = $data['editor'];
        $allNodes = $data['nodes'];
        $nodeIds = array_fill_keys(array_map('strval', is_array($sheet['node_ids'] ?? null) ? $sheet['node_ids'] : []), true);
        $nodes = array_filter($allNodes, static fn (array $node, string $id): bool => isset($nodeIds[$id]), ARRAY_FILTER_USE_BOTH);
        $positions = is_array($sheet['positions'] ?? null) ? $sheet['positions'] : [];
        $transform = is_array($sheet['transform'] ?? null) ? $sheet['transform'] : ['scale' => 1.0, 'offset_x' => 0.0, 'offset_y' => 0.0];

        $this->wireLabelRects = [];
        $this->deviceLabelObstacles = [];
        $routingObstacles = [];
        foreach ($positions as $positionId => $position) {
            if (! is_array($position)) continue;
            $cx = (float) ($position['x'] ?? 0);
            $cy = (float) ($position['y'] ?? 0);
            $w = max(72.0, (float) ($position['w'] ?? 144.0));
            $h = max(66.0, (float) ($position['h'] ?? 120.0));
            $rect = [
                'id' => (string) $positionId,
                'left' => $cx - ($w / 2),
                'top' => $cy - ($h / 2),
                'right' => $cx + ($w / 2),
                'bottom' => $cy + ($h / 2),
                'kind' => 'equipment',
            ];
            $routingObstacles[] = $rect;
            $this->deviceLabelObstacles[] = [
                'left' => $rect['left'] - 12,
                'top' => $rect['top'] - 12,
                'right' => $rect['right'] + 12,
                'bottom' => $rect['bottom'] + 12,
            ];
        }
        // These zones remain outside the topology viewport in normal sheets,
        // but explicit keepouts make the export contract deterministic even
        // when an imported/manual route contains extreme coordinates.
        array_push($routingObstacles,
            ['id' => '__sheet_header__', 'left' => 66.0, 'top' => 46.0, 'right' => 1614.0, 'bottom' => 250.0, 'kind' => 'paper-reserved'],
            ['id' => '__engineering_notes__', 'left' => 66.0, 'top' => 988.0, 'right' => 1004.0, 'bottom' => 1046.0, 'kind' => 'paper-reserved'],
            ['id' => '__symbol_legend__', 'left' => 1018.0, 'top' => 988.0, 'right' => 1614.0, 'bottom' => 1046.0, 'kind' => 'paper-reserved'],
            ['id' => '__title_block__', 'left' => 66.0, 'top' => 1058.0, 'right' => 1614.0, 'bottom' => 1154.0, 'kind' => 'paper-reserved'],
        );

        $connections = array_values(array_filter($data['connections'], static fn (array $connection): bool => isset(
            $nodeIds[(string) ($connection['from'] ?? '')],
            $nodeIds[(string) ($connection['to'] ?? '')],
        )));
        [$manualLines, $manualShapes] = $this->geometryForSheet(
            $data['manual_lines'],
            $data['manual_shapes'],
            is_array($sheet['bounds'] ?? null) ? $sheet['bounds'] : null,
            (string) ($sheet['type'] ?? 'single'),
        );

        $titleCounts = [];
        foreach ($nodes as $node) {
            $titleKey = mb_strtolower(trim((string) ($node['title'] ?? 'equipment')));
            $titleCounts[$titleKey] = ($titleCounts[$titleKey] ?? 0) + 1;
        }

        $title = $this->escape((string) ($meta['title'] ?? 'Single-line diagram'));
        $projectNumber = $this->escape((string) ($meta['project_number'] ?? 'PROJECT'));
        $projectName = $this->escape((string) ($meta['project_name'] ?? 'Untitled project'));
        $systemLabel = $this->escape((string) ($meta['system_label'] ?? 'PV system'));
        $verified = ($meta['calculation_verified'] ?? false) === true;
        $summary = is_array($meta['summary'] ?? null) ? $meta['summary'] : [];
        $rawSheetTitle = (string) ($sheet['title'] ?? ($meta['title'] ?? 'Single-line diagram'));
        $sheetTitle = $this->escape($rawSheetTitle);
        $sheetType = (string) ($sheet['type'] ?? 'single');
        $visualRegression = $this->visualRegression->normalizeMetadata($editor);

        $svg = [];
        $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="420mm" height="297mm" viewBox="0 0 '.self::WIDTH.' '.self::HEIGHT.'" preserveAspectRatio="xMidYMid meet" role="img" aria-labelledby="sld-title sld-desc" class="nexorin-sld-svg" data-paper-sheet="'.(int) ($sheet['number'] ?? 1).'" data-paper-type="'.$this->escape($sheetType).'" data-visual-regression-version="'.SldVisualRegression::VERSION.'" data-visual-regression-contract="'.SldVisualRegression::CONTRACT.'" data-visual-regression-fingerprint="'.$this->escape((string) ($visualRegression['fingerprint'] ?? '')).'" data-visual-regression-status="'.$this->escape((string) ($visualRegression['status'] ?? 'pass')).'">';
        $svg[] = '<title id="sld-title">'.$sheetTitle.' — '.$projectNumber.'</title>';
        $svg[] = '<desc id="sld-desc">'.$systemLabel.' engineering sheet '.(int) ($sheet['number'] ?? 1).' of '.(int) ($sheet['count'] ?? 1).'.</desc>';
        $svg[] = $this->definitions();
        $svg[] = '<rect width="1680" height="1188" fill="#eef3f3"/>';
        $svg[] = '<rect x="18" y="18" width="1644" height="1152" fill="#ffffff" stroke="#102f43" stroke-width="2"/>';
        $svg[] = '<rect x="30" y="30" width="1620" height="1128" fill="url(#sld-grid)" stroke="#93a9b0" stroke-width="1"/>';
        $svg[] = $this->header($projectName, $projectNumber, $systemLabel, $verified, $sheet);
        $svg[] = $this->designData($summary);
        $svg[] = '<g aria-label="Primary electrical drawing">';
        $svg[] = '<rect x="56" y="202" width="1568" height="774" fill="#fbfdfd" stroke="#8ea6ad" stroke-width="1.2"/>';
        $sectionLabel = match ($sheetType) {
            'catalog' => 'ELECTRICAL SYMBOL REGISTER · CONTROLLED ENGINEERING FORMS',
            'overview' => 'SYSTEM OVERVIEW · FUNCTIONAL GROUPS AND DETAIL REFERENCES',
            'as_drawn' => 'PRIMARY POWER TOPOLOGY · AS DRAWN',
            default => strtoupper($rawSheetTitle),
        };
        $svg[] = '<text x="76" y="224" class="sld-section-label">'.$this->escape($sectionLabel).'</text>';

        if ($sheetType === 'overview') {
            $svg[] = $this->overviewDrawing($sheet);
        } else {
            foreach ($manualShapes as $shape) {
                if (is_array($shape)) {
                    $svg[] = $this->draftingShape($shape, $transform);
                }
            }
            foreach ($manualLines as $line) {
                if (is_array($line)) {
                    $svg[] = $this->draftingLine($line, $transform);
                }
            }
            $routedSegments = [];
            foreach ($connections as $connection) {
                $from = (string) ($connection['from'] ?? '');
                $to = (string) ($connection['to'] ?? '');
                if (! isset($positions[$from], $positions[$to])) {
                    continue;
                }
                $route = $data['routes'][(string) ($connection['_id'] ?? '')] ?? null;
                $sourceState = is_array(data_get($editor, 'positions.'.$from)) ? (array) data_get($editor, 'positions.'.$from) : [];
                $targetState = is_array(data_get($editor, 'positions.'.$to)) ? (array) data_get($editor, 'positions.'.$to) : [];
                $svg[] = $this->connection(
                    $connection,
                    $positions[$from],
                    $positions[$to],
                    is_array($route) ? $route : null,
                    $transform,
                    array_merge($nodes[$from] ?? [], $sourceState),
                    array_merge($nodes[$to] ?? [], $targetState),
                    $routingObstacles,
                    $routedSegments,
                );
            }
            $labelItems = [];
            foreach ($nodes as $id => $node) {
                if (! isset($positions[$id]) || ! is_array($positions[$id])) continue;
                $position = $positions[$id];
                $w = max(72.0, (float) ($position['w'] ?? 144.0));
                $h = max(66.0, (float) ($position['h'] ?? 120.0));
                $positionState = is_array(data_get($editor, 'positions.'.$id)) ? (array) data_get($editor, 'positions.'.$id) : [];
                $displayNode = array_merge($node, $positionState);
                $labelItems[] = array_merge($displayNode, [
                    'id' => (string) $id,
                    'x' => (float) ($position['x'] ?? 0) - ($w / 2),
                    'y' => (float) ($position['y'] ?? 0) - ($h / 2),
                    'w' => $w,
                    'h' => $h,
                ]);
            }
            $labelPlacement = $this->labelPlacement->place($labelItems, $routedSegments, [
                'canvas' => ['x' => 56.0, 'y' => 202.0, 'w' => 1568.0, 'h' => 774.0],
            ]);
            $labelPlacementById = collect($labelPlacement['placements'] ?? [])->keyBy('id')->all();

            foreach ($data['annotations'] as $annotation) {
                if (is_array($annotation)) $svg[] = $this->draftingAnnotation($annotation, $transform);
            }
            foreach ($nodes as $id => $node) {
                if (! isset($positions[$id])) {
                    continue;
                }
                $titleKey = mb_strtolower(trim((string) ($node['title'] ?? 'equipment')));
                $positionState = is_array(data_get($editor, 'positions.'.$id)) ? (array) data_get($editor, 'positions.'.$id) : [];
                $displayNode = array_merge($node, $positionState);
                if (array_key_exists('tag_override', $positionState) && $positionState['tag_override'] !== null) {
                    $displayNode['tag'] = (string) $positionState['tag_override'];
                }
                $showTag = ($displayNode['show_tag'] ?? true) !== false && trim((string) ($displayNode['tag'] ?? '')) !== '';
                $svg[] = $this->device($id, $positions[$id], $displayNode, $showTag, is_array($labelPlacementById[$id] ?? null) ? $labelPlacementById[$id] : null);
            }
            if ($sheetType !== 'catalog') {
                $svg[] = $this->crossReferences($sheet, $positions, $nodes);
                if ($sheetType !== 'as_drawn') $svg[] = $this->earthingReference($nodes, $positions);
            }
        }
        $svg[] = '</g>';

        $svg[] = $this->notesAndLegend($nodes, $data['annotations'], $sheet, $paperSummary);
        $svg[] = $this->titleBlock($graph, $projectName, $projectNumber, $systemLabel, $verified, $sheet);
        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $sheet @param array<string, mixed> $paperSummary */
    private function renderProfessionalSheet(array $graph, array $data, array $sheet, array $paperSummary): string
    {
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $type = (string) ($sheet['type'] ?? 'professional_sld');
        $projectNumber = (string) ($meta['project_number'] ?? 'PROJECT');
        $projectName = (string) ($meta['project_name'] ?? 'Untitled project');
        $systemLabel = (string) ($meta['system_label'] ?? 'PV system');
        $sheetTitle = (string) ($sheet['title'] ?? 'Single-line diagram');
        $sheetNumber = (int) ($sheet['number'] ?? 1);
        $sheetCount = (int) ($sheet['count'] ?? 1);
        $visualRegression = $this->visualRegression->normalizeMetadata(is_array($data['editor'] ?? null) ? $data['editor'] : []);

        $svg = [];
        $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="420mm" height="297mm" viewBox="0 0 '.self::WIDTH.' '.self::HEIGHT.'" preserveAspectRatio="xMidYMid meet" role="img" aria-label="APP67L.4-FINAL production-controlled A3 engineering drawing" class="nexorin-sld-svg" data-paper-sheet="'.$sheetNumber.'" data-paper-type="'.$this->escape($type).'" data-visual-regression-version="'.SldVisualRegression::VERSION.'" data-visual-regression-contract="'.SldVisualRegression::CONTRACT.'" data-visual-regression-fingerprint="'.$this->escape((string) ($visualRegression['fingerprint'] ?? '')).'" data-visual-regression-status="'.$this->escape((string) ($visualRegression['status'] ?? 'pass')).'">';
        $svg[] = '<title>'.$this->escape($sheetTitle).' - '.$this->escape($projectNumber).'</title>';
        $svg[] = '<desc>'.$this->escape($systemLabel).' professional engineering drawing, sheet '.$sheetNumber.' of '.$sheetCount.'.</desc>';
        $svg[] = $this->definitions();
        $svg[] = $this->professionalFrame($graph, $sheetTitle, $sheetNumber, $sheetCount, $type);

        if ($type === 'professional_sld') {
            $svg[] = $this->professionalMainDrawing($graph, $data, $sheet, $paperSummary);
        } elseif ($type === 'control_schematic') {
            $svg[] = $this->professionalControlSchematic($graph);
        } elseif ($type === 'string_schedule') {
            $svg[] = $this->professionalStringSchedule($graph);
        } elseif ($type === 'cable_schedule') {
            $svg[] = $this->professionalCableSchedule($graph, $sheet);
        } else {
            $svg[] = $this->professionalProtectionSchedule($graph);
        }

        $svg[] = $this->professionalTitleBlock(
            $graph,
            $projectName,
            $projectNumber,
            $systemLabel,
            $sheet,
        );
        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /** @param array<string, mixed> $sheet */
    private function professionalFrame(array $graph, string $title, int $sheetNumber, int $sheetCount, string $type): string
    {
        $parts = ['<g aria-label="A3 landscape drawing frame">'];
        $parts[] = '<rect width="1680" height="1188" fill="#fff"/>';
        $parts[] = '<rect x="18" y="18" width="1644" height="1152" fill="#fff" stroke="#0a1d26" stroke-width="2.2"/>';
        $parts[] = '<rect x="34" y="34" width="1612" height="1120" fill="none" stroke="#0a1d26" stroke-width="1.15"/>';
        $parts[] = '<path d="M34 58H1646M34 985H1646" stroke="#0a1d26" stroke-width="1"/>';

        $zoneWidth = 1612 / 8;
        for ($index = 0; $index < 8; $index++) {
            $x = 34 + ($index * $zoneWidth);
            if ($index > 0) $parts[] = '<path d="M'.$this->f($x).' 34V58M'.$this->f($x).' 1130V1154" stroke="#0a1d26" stroke-width=".8"/>';
            $parts[] = '<text x="'.$this->f($x + ($zoneWidth / 2)).'" y="51" text-anchor="middle" class="sld-foot-mono">'.($index + 1).'</text>';
            $parts[] = '<text x="'.$this->f($x + ($zoneWidth / 2)).'" y="1148" text-anchor="middle" class="sld-foot-mono">'.($index + 1).'</text>';
        }
        $drawingTop = 58.0;
        $drawingBottom = 985.0;
        $zoneHeight = ($drawingBottom - $drawingTop) / 6;
        foreach (range('A', 'F') as $index => $letter) {
            $y = $drawingTop + ($index * $zoneHeight);
            if ($index > 0) $parts[] = '<path d="M34 '.$this->f($y).'H54M1626 '.$this->f($y).'H1646" stroke="#0a1d26" stroke-width=".8"/>';
            $mid = $y + ($zoneHeight / 2);
            $parts[] = '<text x="44" y="'.$this->f($mid + 4).'" text-anchor="middle" class="sld-foot-mono">'.$letter.'</text>';
            $parts[] = '<text x="1636" y="'.$this->f($mid + 4).'" text-anchor="middle" class="sld-foot-mono">'.$letter.'</text>';
        }
        $drawingRight = $type === 'professional_sld' ? 1273.0 : 1625.0;
        $parts[] = '<rect x="55" y="66" width="'.$this->f($drawingRight - 55.0).'" height="903" fill="#fff" stroke="#a3afb4" stroke-width=".8"/>';
        $controlVersion = (string) data_get($graph, 'meta.document_control.version', '67L.4-FINAL');
        $issueLabel = strtoupper((string) data_get($graph, 'meta.document_control.drawing.issue_label', 'DRAFT'));
        $parts[] = '<text x="72" y="88" class="sld-section-label">'.$this->escape($controlVersion.' - '.$issueLabel.' ENGINEERING DRAWING').'</text>';
        $parts[] = '<text x="72" y="112" class="sld-device-title">'.$this->escape(strtoupper($title)).'</text>';
        $parts[] = '<text x="'.$this->f($drawingRight - 19.0).'" y="88" text-anchor="end" class="sld-foot-mono">SHEET '.$sheetNumber.' / '.$sheetCount.'</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $sheet @param array<string, mixed> $paperSummary */
    private function professionalMainDrawing(array $graph, array $data, array $sheet, array $paperSummary): string
    {
        $allNodes = is_array($data['nodes'] ?? null) ? $data['nodes'] : [];
        $nodeIds = array_fill_keys(array_map('strval', is_array($sheet['node_ids'] ?? null) ? $sheet['node_ids'] : []), true);
        $nodes = array_filter($allNodes, static fn (array $node, string $id): bool => isset($nodeIds[$id]), ARRAY_FILTER_USE_BOTH);
        $summary = is_array(data_get($graph, 'meta.summary')) ? data_get($graph, 'meta.summary') : [];
        $stringGroups = is_array($summary['string_groups'] ?? null) ? $summary['string_groups'] : [];
        $parts = ['<g aria-label="Professional single-line diagram">'];

        $parts[] = $this->professionalMpptSummaryTables($stringGroups);
        foreach ([1 => 325, 2 => 565] as $branch => $y) {
            $suffix = sprintf('%02d', $branch);
            if (! isset($nodes['inverter_'.$suffix])) continue;
            $parts[] = '<rect x="72" y="'.($y - 88).'" width="790" height="214" fill="none" stroke="#6f7d84" stroke-width=".85" stroke-dasharray="7 5"/>';
            $parts[] = '<rect x="72" y="'.($y - 88).'" width="360" height="22" fill="#fff"/>';
            $parts[] = '<text x="82" y="'.($y - 73).'" class="sld-foot-mono">PVA-'.$suffix.' · PV STRING / MPPT BLOCK · INV-'.$suffix.'</text>';
        }
        $parts[] = '<rect x="875" y="237" width="380" height="475" fill="none" stroke="#6f7d84" stroke-width=".85" stroke-dasharray="7 5"/>';
        $parts[] = '<rect x="875" y="237" width="350" height="22" fill="#fff"/>';
        $parts[] = '<text x="885" y="252" class="sld-foot-mono">AC COLLECTION / PCC INTERFACE</text>';
        $parts[] = '<rect x="395" y="825" width="430" height="143" fill="none" stroke="#6f7d84" stroke-width=".85" stroke-dasharray="7 5"/>';
        $parts[] = '<rect x="395" y="825" width="430" height="25" fill="#fff"/>';
        $parts[] = '<path d="M395 850H825" stroke="#d2d9dc" stroke-width=".7"/>';
        $parts[] = '<text x="405" y="843" class="sld-foot-mono">PROTECTIVE EARTHING SYSTEM</text>';

        $parts[] = $this->professionalStringInputBranches($graph, $nodes);
        $parts[] = $this->professionalAcCollectionAndControl($graph, $nodes);
        $parts[] = $this->professionalProtectiveEarthNetwork($nodes);
        $parts[] = $this->professionalMainLegend();
        $parts[] = $this->professionalMainEquipmentSummary($graph);
        $parts[] = $this->professionalMainNotes($paperSummary);
        $parts[] = '</g>';
        return implode('', $parts);
    }


    /**
     * Render independent PV strings with fixed label clearances and dedicated
     * paper-space lanes. No label is placed on a conductor or symbol envelope.
     *
     * @param array<string, array<string, mixed>> $nodes
     */
    private function professionalStringInputBranches(array $graph, array $nodes): string
    {
        $summary = is_array(data_get($graph, 'meta.summary')) ? data_get($graph, 'meta.summary') : [];
        $groups = is_array($summary['string_groups'] ?? null) ? $summary['string_groups'] : [];
        $parts = ['<g aria-label="Independent PV string and MPPT circuits">'];
        foreach (array_slice($groups, 0, 2) as $index => $group) {
            $instance = $index + 1;
            $suffix = sprintf('%02d', $instance);
            $centerY = $instance === 1 ? 325.0 : 565.0;
            $rowYs = [$centerY - 43.0, $centerY, $centerY + 43.0];
            $strings = array_values(array_filter(is_array($group['strings'] ?? null) ? $group['strings'] : [], 'is_array'));
            $inverterId = 'inverter_'.$suffix;
            if (! isset($nodes[$inverterId])) continue;

            // SPD shunt rail and its trunk connection use a lane outside the caption.
            $spdRailY = $centerY + 84.0;
            $parts[] = '<path d="M490 '.$spdRailY.'H548" class="sld-connection protective-earth"/>';

            foreach ($rowYs as $rowIndex => $rowY) {
                $string = $strings[$rowIndex] ?? [];
                $tag = (string) ($string['tag'] ?? ('STR-'.sprintf('%02d', (($instance - 1) * 3) + $rowIndex + 1)));
                $mppt = (string) ($string['mppt_label'] ?? ('MPPT-'.sprintf('%02d', $rowIndex + 1)));
                $modules = (int) ($string['modules'] ?? 0);
                $switchTag = 'QS-DC-'.$suffix.'/'.($rowIndex + 1);

                $parts[] = '<g transform="translate(94 '.$rowY.') scale(.30) translate(-94 -'.$rowY.')">'.$this->symbol('pv_array', 94.0, $rowY).'</g>';
                $parts[] = '<text x="122" y="'.($rowY - 9).'" class="sld-string-tag sld-label-halo">'.$this->escape($tag).'</text>';
                $parts[] = '<text x="122" y="'.($rowY + 14).'" class="sld-string-data sld-label-halo">'.$modules.' MODULES</text>';
                $parts[] = '<path d="M205 '.$rowY.'H247" class="sld-connection dc-power"/>';

                $parts[] = '<g transform="translate(282 '.$rowY.') scale(.36) translate(-282 -'.$rowY.')">'.$this->symbol('dc_isolator', 282.0, $rowY).'</g>';
                $parts[] = '<text x="282" y="'.($rowY + 21).'" text-anchor="middle" class="sld-terminal-label sld-label-halo">'.$this->escape($switchTag).'</text>';
                $parts[] = '<path d="M318 '.$rowY.'H556" class="sld-connection dc-power" marker-end="url(#sld-power-arrow)"/>';
                $parts[] = '<text x="408" y="'.($rowY - 14).'" text-anchor="middle" class="sld-terminal-label sld-label-halo">'.$this->escape($mppt).'</text>';
                $parts[] = '<circle cx="556" cy="'.$rowY.'" r="4" class="sld-junction"/>';

                $tapX = 500.0 + ($rowIndex * 16.0);
                $boxY = $rowY + 13.0;
                $parts[] = '<circle cx="'.$tapX.'" cy="'.$rowY.'" r="3.5" class="sld-junction"/>';
                $parts[] = '<path d="M'.$tapX.' '.$rowY.'V'.$boxY.'" class="sld-connection dc-power"/>';
                $parts[] = '<rect x="'.($tapX - 8).'" y="'.$boxY.'" width="16" height="18" rx="1" fill="#fff" stroke="#12374b" stroke-width="1.5"/>';
                $parts[] = '<path d="M'.($tapX - 5).' '.($boxY + 15).'L'.($tapX + 5).' '.($boxY + 3).'" class="sld-symbol-thin"/>';
                $parts[] = '<path d="M'.$tapX.' '.($boxY + 18).'V'.$spdRailY.'" class="sld-connection protective-earth"/>';
                $parts[] = '<circle cx="'.$tapX.'" cy="'.$spdRailY.'" r="3.2" class="sld-junction"/>';
            }

            // Opaque annotation band masks the three PE drops. The short caption
            // stays inside the band and never shares a lane with a conductor.
            $parts[] = '<rect x="300" y="'.($centerY + 70).'" width="185" height="22" rx="2" class="sld-label-box"/>';
            $parts[] = '<text x="392.5" y="'.($centerY + 86).'" text-anchor="middle" class="sld-string-data">SPD-DC-'.$suffix.' · SHUNT TO PE</text>';

            $inverter = $nodes[$inverterId];
            [$invTitle, $invRating] = $this->professionalCompactDeviceText($inverterId, $inverter);
            $inverter['paper_title'] = $invTitle;
            $inverter['paper_full_title'] = (string) ($inverter['title'] ?? $invTitle);
            $inverter['paper_rating'] = $invRating;
            $inverter['paper_show_subtitle'] = false;
            $parts[] = $this->device($inverterId, ['x' => 610.0, 'y' => $centerY, 'w' => 100.0, 'h' => 154.0], $inverter, true);

            foreach ([
                ['id' => 'ac_breaker_'.$suffix, 'x' => 724.0, 'w' => 92.0],
                ['id' => 'ac_isolator_'.$suffix, 'x' => 834.0, 'w' => 92.0],
            ] as $deviceSpec) {
                if (! isset($nodes[$deviceSpec['id']])) continue;
                $node = $nodes[$deviceSpec['id']];
                [$paperTitle, $paperRating] = $this->professionalCompactDeviceText($deviceSpec['id'], $node);
                $node['paper_title'] = $paperTitle;
                $node['paper_full_title'] = (string) ($node['title'] ?? $paperTitle);
                $node['paper_rating'] = $paperRating;
                $parts[] = $this->device($deviceSpec['id'], ['x' => $deviceSpec['x'], 'y' => $centerY, 'w' => $deviceSpec['w'], 'h' => 142.0], $node, true);
            }

            $parts[] = '<path d="M660 '.$centerY.'H675M770 '.$centerY.'H788M880 '.$centerY.'H876" class="sld-connection ac-power"/>';
            // Dedicated white annotation band keeps the cross-reference clear of the panel boundary.
            $xrefBandY = $centerY + 92.0;
            $parts[] = '<rect x="82" y="'.$xrefBandY.'" width="310" height="24" rx="2" fill="#fff"/>';
            $parts[] = '<text x="92" y="'.($xrefBandY + 17).'" class="sld-note">CABLE XREF: SHEET 4 · C-DC-'.$suffix.'-STR-*-A/B</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }


    /** @param array<string, array<string, mixed>> $nodes */
    private function professionalAcCollectionAndControl(array $graph, array $nodes): string
    {
        $parts = ['<g aria-label="AC collection on a fixed PCC drafting grid" data-layout-grid="pcc-fixed-r58">'];
        $busX = 876.0;
        $mainY = 338.0;

        foreach ([325.0, 565.0] as $feederY) {
            $parts[] = $this->professionalRouteSvg([[890.0, $feederY], [$busX, $feederY]], 'ac-power', false, 'inverter-feeder');
        }
        $parts[] = $this->professionalRouteSvg([[$busX, 325.0], [$busX, 565.0]], 'ac-power', false, 'combiner-bus');
        $parts[] = '<circle cx="'.$busX.'" cy="'.$mainY.'" r="3.1" class="sld-junction"/>';

        // The five PCC devices occupy a fixed equal-pitch grid. Conductors end
        // at symbol envelopes; terminal circles are reserved for real branches.
        $inline = [
            ['ac_combiner_01', 'ac_combiner', 'ACCB-01', 914.0, 'AC COMB.', .32, 14.5],
            ['pcc_breaker_01', 'ac_breaker', 'QF-PCC-01', 992.0, 'MAIN CB', .32, 14.5],
            ['ct_01', 'ct', 'CT-01', 1070.0, 'CT SET', .32, 13.6],
            ['pcc_disconnect_01', 'dc_isolator', 'QS-PCC-01', 1148.0, 'PCC ISO.', .32, 14.5],
            ['grid_01', 'grid', 'GRID-01', 1227.0, 'GRID', .30, 10.2],
        ];
        $terminals = [];
        foreach ($inline as [$id, $symbol, $tag, $x, $shortLabel, $scale, $halfSpan]) {
            if (! isset($nodes[$id])) continue;
            $terminals[$id] = ['left' => $x - $halfSpan, 'right' => $x + $halfSpan];
            $parts[] = '<g transform="translate('.$x.' '.$mainY.') scale('.$scale.') translate('.(-$x).' '.(-$mainY).')">'.$this->symbol($symbol, $x, $mainY).'</g>';
            $parts[] = '<text x="'.$x.'" y="293" text-anchor="middle" class="sld-terminal-label sld-label-halo">'.$shortLabel.'</text>';
            $parts[] = '<text x="'.$x.'" y="385" text-anchor="middle" class="sld-device-tag sld-label-halo">'.$tag.'</text>';
            $ratingText = match ($id) {
                'ac_combiner_01' => $this->number($nodes[$id]['busbar_rating_a'] ?? null, 0).' A BUS',
                'pcc_breaker_01' => $this->number($nodes[$id]['rated_current'] ?? null, 0).' A',
                'ct_01' => 'CT DATA TBC',
                'pcc_disconnect_01' => $this->number($nodes[$id]['rated_current'] ?? null, 0).' A',
                default => 'UTILITY PCC',
            };
            $parts[] = '<text x="'.$x.'" y="405" text-anchor="middle" class="sld-terminal-label sld-label-halo">'.$this->escape($ratingText).'</text>';
        }

        $segments = [
            [[$busX, $mainY], [$terminals['ac_combiner_01']['left'] ?? 899.5, $mainY], 'pcc-main-01'],
            [[$terminals['ac_combiner_01']['right'] ?? 928.5, $mainY], [$terminals['pcc_breaker_01']['left'] ?? 977.5, $mainY], 'pcc-main-02'],
            [[$terminals['pcc_breaker_01']['right'] ?? 1006.5, $mainY], [$terminals['ct_01']['left'] ?? 1056.4, $mainY], 'pcc-main-03'],
            [[$terminals['ct_01']['right'] ?? 1083.6, $mainY], [$terminals['pcc_disconnect_01']['left'] ?? 1133.5, $mainY], 'pcc-main-04'],
            [[$terminals['pcc_disconnect_01']['right'] ?? 1162.5, $mainY], [$terminals['grid_01']['left'] ?? 1216.8, $mainY], 'pcc-main-05'],
            [[$terminals['grid_01']['right'] ?? 1237.2, $mainY], [1254.0, $mainY], 'pcc-main-out'],
        ];
        foreach ($segments as $index => [$a, $b, $routeId]) {
            $parts[] = $this->professionalRouteSvg([$a, $b], 'ac-power', $index === count($segments) - 1, $routeId);
        }
        $parts[] = '<text x="1070" y="268" text-anchor="middle" class="sld-flow-label sld-label-halo">MAIN POWER PATH · METER OUTSIDE PRIMARY FEEDER</text>';

        // A single branch junction identifies the parallel SPD tap. No terminal
        // ornaments are placed on the uninterrupted primary feeder.
        $tapX = 953.0;
        $parts[] = '<circle cx="'.$tapX.'" cy="'.$mainY.'" r="3.4" class="sld-junction"/>';
        $parts[] = $this->professionalRouteSvg([[$tapX, $mainY], [$tapX, 451.0]], 'ac-power', false, 'ac-spd-tap');
        $parts[] = '<g transform="translate('.$tapX.' 474) scale(.31) translate('.(-$tapX).' -474)">'.$this->symbol('ac_breaker', $tapX, 474.0).'</g>';
        $parts[] = '<text x="985" y="468" class="sld-device-tag sld-label-halo">QF-SPD-01</text>';
        $parts[] = '<text x="985" y="488" class="sld-terminal-label sld-label-halo">BACKUP TBC</text>';
        $parts[] = $this->professionalRouteSvg([[$tapX, 489.0], [$tapX, 518.0]], 'ac-power', false, 'ac-spd-protected');
        $parts[] = '<rect x="945" y="518" width="16" height="22" rx="1" fill="#fff" stroke="#12374b" stroke-width="1.5"/>';
        $parts[] = '<path d="M948 536L958 522" class="sld-symbol-thin"/>';
        $parts[] = '<text x="982" y="532" class="sld-device-tag sld-label-halo">SPD-AC-01</text>';
        $parts[] = '<text x="982" y="552" class="sld-terminal-label sld-label-halo">TYPE 2 / 3P+N</text>';
        $parts[] = $this->professionalRouteSvg([[$tapX, 540.0], [$tapX, 810.0]], 'protective-earth', false, 'ac-spd-pe');
        $parts[] = '<circle cx="'.$tapX.'" cy="810" r="3.1" class="sld-junction"/>';

        $parts[] = '<rect x="1088" y="508" width="158" height="76" rx="3" fill="#fff" stroke="#6f7d84" stroke-width=".9" stroke-dasharray="6 4"/>';
        $parts[] = '<text x="1167" y="533" text-anchor="middle" class="sld-device-tag">METERING / EXPORT TRIP</text>';
        $parts[] = '<text x="1167" y="556" text-anchor="middle" class="sld-terminal-label">SCHEMATIC: SHEET 2</text>';
        $parts[] = '<text x="1167" y="578" text-anchor="middle" class="sld-note">RELEASE GATES: SHEET 6</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }


    private function professionalControlSchematic(array $graph): string
    {
        $nodes = [];
        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            if (is_array($node) && filled($node['id'] ?? null)) $nodes[(string) $node['id']] = $node;
        }

        $parts = ['<g aria-label="Zero-export metering and control on a fixed drafting grid" data-layout-grid="control-fixed-r58">'];
        $parts[] = '<rect x="65" y="145" width="700" height="630" fill="#fff" stroke="#6f7d84" stroke-width="1" stroke-dasharray="7 5"/>';
        $parts[] = '<text x="85" y="174" class="sld-foot-mono">PCC MEASUREMENT / PROTECTION INTERFACE</text>';
        $parts[] = '<rect x="785" y="145" width="810" height="630" fill="#fff" stroke="#6f7d84" stroke-width="1" stroke-dasharray="7 5"/>';
        $parts[] = '<text x="805" y="174" class="sld-foot-mono">MONITORING / ACTIVE-POWER CONTROL</text>';

        // LEFT PANEL: fixed three-column primary row, relay directly below the
        // breaker, and meter directly below the CT. This keeps every secondary
        // route short and eliminates the former perimeter loop.
        $mainY = 238.0;
        $qfX = 175.0; $ctX = 405.0; $qsX = 650.0;
        foreach ([
            ['ac_breaker', $qfX, 'QF-PCC-01', 'MAIN CB'],
            ['ct', $ctX, 'CT-01 SET', '3 × DUAL-CORE CT'],
            ['dc_isolator', $qsX, 'QS-PCC-01', 'PCC ISOLATOR'],
        ] as [$symbol, $x, $tag, $label]) {
            $parts[] = '<g transform="translate('.$x.' '.$mainY.') scale(.50) translate('.(-$x).' '.(-$mainY).')">'.$this->symbol($symbol, $x, $mainY).'</g>';
            $parts[] = '<text x="'.$x.'" y="196" text-anchor="middle" class="sld-device-tag sld-label-halo">'.$tag.'</text>';
            $parts[] = '<text x="'.$x.'" y="214" text-anchor="middle" class="sld-terminal-label sld-label-halo">'.$label.'</text>';
        }
        $parts[] = $this->professionalRouteSvg([[92.0, $mainY], [133.0, $mainY]], 'ac-power', false, 'control-primary-in');
        $parts[] = $this->professionalRouteSvg([[217.0, $mainY], [361.0, $mainY]], 'ac-power', false, 'control-primary-01');
        $parts[] = $this->professionalRouteSvg([[449.0, $mainY], [606.0, $mainY]], 'ac-power', false, 'control-primary-02');
        $parts[] = $this->professionalRouteSvg([[694.0, $mainY], [748.0, $mainY]], 'ac-power', true, 'control-primary-out');
        $parts[] = '<text x="405" y="273" text-anchor="middle" class="sld-flow-label sld-label-halo">PRIMARY FEEDER REFERENCE · METER NOT IN SERIES</text>';

        foreach ([
            ['export_relay_01', $qfX, 'REVERSE-POWER RELAY', 'PRELIMINARY EXPORT TRIP', 'relay_paper'],
            ['meter_01', $ctX, 'POWER METER', 'CT / VOLTAGE / MODBUS', 'meter_paper'],
        ] as [$id, $x, $title, $rating, $paperSymbol]) {
            if (! isset($nodes[$id])) continue;
            $node = $nodes[$id];
            $node['paper_title'] = $title;
            $node['paper_rating'] = $rating;
            $node['paper_symbol'] = $paperSymbol;
            $node['paper_show_subtitle'] = false;
            $parts[] = $this->device($id, ['x' => $x, 'y' => 530.0, 'w' => 230.0, 'h' => 170.0], $node, true);
        }

        // Explicit top terminals on the field devices.
        $rprCtX = 150.0; $rprTripX = 175.0; $rprVrefX = 200.0;
        $mtrCtX = 392.0; $mtrVrefX = 418.0;
        $inputY = 440.0;
        foreach ([[$rprCtX, 'measurement'], [$rprTripX, 'control'], [$rprVrefX, 'measurement'], [$mtrCtX, 'measurement'], [$mtrVrefX, 'measurement']] as [$x, $network]) {
            $parts[] = $this->professionalPortSvg($x, $inputY, $network);
        }

        // CT metering and protection cores use separate lanes and terminate at
        // their dedicated device inputs.
        $parts[] = $this->professionalRouteSvg([[$ctX - 10.0, 255.0], [$ctX - 10.0, 340.0], [$mtrCtX, 340.0], [$mtrCtX, $inputY]], 'measurement', false, 'ct-metering-core');
        $parts[] = '<text x="465" y="334" text-anchor="middle" class="sld-terminal-label sld-label-halo">METERING CORE · S1/S2 · TEST BLOCK TBC</text>';
        $parts[] = $this->professionalRouteSvg([[$ctX + 10.0, 255.0], [$ctX + 10.0, 318.0], [$rprCtX, 318.0], [$rprCtX, $inputY]], 'measurement', false, 'ct-protection-core');
        $parts[] = '<text x="340" y="300" text-anchor="middle" class="sld-terminal-label sld-label-halo">PROTECTION CT · S1/S2 · TEST BLOCK TBC</text>';

        // One voltage-reference trunk branches only at the two device terminals.
        $vrefY = 410.0;
        $parts[] = $this->professionalRouteSvg([[$qsX, 255.0], [$qsX, $vrefY], [$rprVrefX, $vrefY], [$rprVrefX, $inputY]], 'measurement', false, 'voltage-reference-trunk');
        $parts[] = $this->professionalRouteSvg([[$mtrVrefX, $vrefY], [$mtrVrefX, $inputY]], 'measurement', false, 'voltage-reference-meter');
        $parts[] = '<circle cx="'.$mtrVrefX.'" cy="'.$vrefY.'" r="3" class="sld-junction"/>';
        $parts[] = '<text x="520" y="400" text-anchor="middle" class="sld-terminal-label sld-label-halo">FUSED 3-PHASE VOLTAGE REFERENCE · TERMINALS TBC</text>';

        // The export-trip path is now a short vertical connection from the relay
        // to a real breaker trip terminal. It does not circle the panel.
        $tripTerminalY = 264.0;
        $parts[] = $this->professionalPortSvg($qfX, $tripTerminalY, 'control');
        $parts[] = $this->professionalRouteSvg([[$rprTripX, $inputY], [$rprTripX, $tripTerminalY]], 'control', false, 'hardwired-export-trip-short');
        $parts[] = '<text x="82" y="350" class="sld-terminal-label sld-label-halo">HARDWIRED TRIP</text>';
        $parts[] = '<text x="82" y="370" class="sld-note sld-label-halo">QF-PCC-01:T · AUXILIARY SUPPLY TBC</text>';

        $parts[] = '<text x="85" y="704" class="sld-note">PRELIMINARY INDEPENDENT EXPORT-TRIP PATH. FAIL-SAFE PHILOSOPHY IS NOT YET VERIFIED.</text>';
        $parts[] = '<text x="85" y="730" class="sld-note">SETPOINT, DELAY, CT DATA, TEST BLOCK, VOLTAGE FUSING, TRIP COIL, SUPPLY AND SUPERVISION: TBC.</text>';

        // RIGHT PANEL: devices remain on a two-column grid. A terminal strip is
        // physically attached to PPC-01, so MTR/RPR inputs no longer float.
        foreach ([
            ['data_logger_01', 1010.0, 285.0, 'DATA LOGGER', 'RS485 / ETHERNET'],
            ['plant_controller_01', 1430.0, 285.0, 'PLANT CONTROLLER', 'ZERO-EXPORT COMMAND'],
        ] as [$id, $x, $yy, $title, $rating]) {
            if (! isset($nodes[$id])) continue;
            $node = $nodes[$id];
            $node['paper_title'] = $title;
            $node['paper_rating'] = $rating;
            $node['paper_symbol'] = 'controller_paper';
            $node['paper_show_subtitle'] = false;
            $parts[] = $this->device($id, ['x' => $x, 'y' => $yy, 'w' => 210.0, 'h' => 150.0], $node, true);
        }

        $dlEthX = 1065.0; $ppcEthX = 1375.0; $ethY = 285.0;
        $parts[] = $this->professionalPortSvg($dlEthX, $ethY, 'communication');
        $parts[] = $this->professionalPortSvg($ppcEthX, $ethY, 'communication');
        $parts[] = $this->professionalRouteSvg([[$dlEthX, $ethY], [$ppcEthX, $ethY]], 'communication', false, 'dl-ppc-ethernet-direct');
        $parts[] = '<text x="1220" y="268" text-anchor="middle" class="sld-terminal-label sld-label-halo">CAT6 ETHERNET</text>';

        // Attached PPC terminal strip.
        $stripX = 1320.0; $stripY = 374.0; $stripW = 64.0; $rowH = 27.0;
        $parts[] = '<g data-paper-terminal-strip="PPC-01"><text x="'.($stripX + 32).'" y="'.($stripY - 10).'" text-anchor="middle" class="sld-port-caption">PPC-01 I/O</text><rect x="'.$stripX.'" y="'.$stripY.'" width="'.$stripW.'" height="108" rx="3" fill="#f8fbfb" stroke="#12374b" stroke-width="1.2"/>';
        foreach ([['MTR', 0], ['RPR', 1], ['CTRL', 2], ['COM', 3]] as [$label, $row]) {
            $rowY = $stripY + ($row * $rowH);
            if ($row > 0) $parts[] = '<path d="M'.$stripX.' '.$rowY.'H'.($stripX + $stripW).'" stroke="#aebbc0" stroke-width=".7"/>';
            $cy = $rowY + ($rowH / 2);
            $parts[] = '<text x="'.($stripX + 29).'" y="'.($cy + 4).'" text-anchor="middle" class="sld-port-caption">'.$label.'</text>';
            $parts[] = $this->professionalPortSvg($stripX, $cy, $label === 'CTRL' ? 'control' : 'communication');
            $parts[] = '<path d="M'.($stripX + $stripW).' '.$cy.'H1402" class="sld-connection '.($label === 'CTRL' ? 'control' : 'communication').'"/>';
        }
        $parts[] = '</g>';

        // MTR and RPR data enter the attached terminal strip through two direct
        // horizontal lanes. Vertical transitions remain in the inter-panel gap.
        $mtrCommX = 520.0; $rprCommX = 290.0;
        $parts[] = $this->professionalPortSvg($mtrCommX, 530.0, 'communication');
        $parts[] = $this->professionalPortSvg($rprCommX, 530.0, 'communication');
        $mtrStripY = $stripY + 13.5; $rprStripY = $stripY + $rowH + 13.5;
        $parts[] = $this->professionalRouteSvg([[$mtrCommX, 530.0], [760.0, 530.0], [760.0, $mtrStripY], [$stripX, $mtrStripY]], 'communication', false, 'meter-to-ppc-strip');
        $parts[] = $this->professionalRouteSvg([[$rprCommX, 530.0], [305.0, 530.0], [305.0, 640.0], [740.0, 640.0], [740.0, $rprStripY], [$stripX, $rprStripY]], 'communication', false, 'relay-to-ppc-strip');
        $parts[] = '<text x="1030" y="'.($mtrStripY - 9).'" text-anchor="middle" class="sld-terminal-label sld-label-halo">MODBUS RTU · IMPORT / EXPORT VALUE</text>';
        $parts[] = '<text x="1030" y="'.($rprStripY - 9).'" text-anchor="middle" class="sld-terminal-label sld-label-halo">DIGITAL STATUS · NO TRIP COMMAND</text>';

        foreach ([['inverter_01', 1030.0, 'INV-01'], ['inverter_02', 1450.0, 'INV-02']] as [$id, $x, $tag]) {
            if (! isset($nodes[$id])) continue;
            $node = $nodes[$id];
            $node['paper_title'] = 'INVERTER';
            $node['paper_rating'] = $tag.' · ACTIVE-POWER LIMIT INPUT';
            $node['paper_show_subtitle'] = false;
            $parts[] = $this->device($id, ['x' => $x, 'y' => 690.0, 'w' => 220.0, 'h' => 166.0], $node, true);
        }

        $inv1CtrlX = 1010.0; $inv1ComX = 1050.0; $inv2CtrlX = 1430.0; $inv2ComX = 1470.0;
        $inverterPortY = 605.0;
        foreach ([[$inv1CtrlX, 'control', 'CTRL'], [$inv1ComX, 'communication', 'COM'], [$inv2CtrlX, 'control', 'CTRL'], [$inv2ComX, 'communication', 'COM']] as [$x, $network, $label]) {
            $parts[] = $this->professionalPortSvg($x, $inverterPortY, $network);
            $parts[] = '<text x="'.$x.'" y="594" text-anchor="middle" class="sld-port-caption">'.$label.'</text>';
        }

        $ctrlStripY = $stripY + (2 * $rowH) + 13.5;
        $comStripY = $stripY + (3 * $rowH) + 13.5;
        $commandBusY = 545.0; $monitorBusY = 580.0;
        $parts[] = $this->professionalRouteSvg([[$stripX, $ctrlStripY], [1285.0, $ctrlStripY], [1285.0, $commandBusY], [$inv1CtrlX, $commandBusY], [$inv1CtrlX, $inverterPortY]], 'control', false, 'ppc-command-grid-inv1');
        $parts[] = $this->professionalRouteSvg([[1285.0, $commandBusY], [$inv2CtrlX, $commandBusY], [$inv2CtrlX, $inverterPortY]], 'control', false, 'ppc-command-grid-inv2');
        $parts[] = '<circle cx="1285" cy="'.$commandBusY.'" r="3" class="sld-junction"/>';
        $parts[] = '<text x="1225" y="534" text-anchor="middle" class="sld-device-tag sld-label-halo">DIRECT ACTIVE-POWER LIMIT COMMAND</text>';

        $dlDevicePortX = 1010.0; $dlMonitorX = 960.0; $dlMonitorY = 365.0;
        $parts[] = $this->professionalPortSvg($dlDevicePortX, $dlMonitorY, 'communication');
        $parts[] = $this->professionalRouteSvg([[$dlDevicePortX, $dlMonitorY], [$dlMonitorX, $dlMonitorY], [$dlMonitorX, $monitorBusY], [$inv2ComX, $monitorBusY]], 'communication', false, 'dl-monitor-grid');
        $parts[] = $this->professionalRouteSvg([[$inv1ComX, $monitorBusY], [$inv1ComX, $inverterPortY]], 'communication', false, 'dl-monitor-grid-inv1');
        $parts[] = $this->professionalRouteSvg([[$inv2ComX, $monitorBusY], [$inv2ComX, $inverterPortY]], 'communication', false, 'dl-monitor-grid-inv2');
        $parts[] = '<circle cx="'.$dlMonitorX.'" cy="'.$monitorBusY.'" r="3" class="sld-junction"/>';
        $parts[] = '<text x="1225" y="570" text-anchor="middle" class="sld-terminal-label sld-label-halo">RS485 INVERTER MONITORING NETWORK</text>';

        $parts[] = '<rect x="65" y="800" width="1530" height="130" fill="#eef4f4" stroke="#6f7d84" stroke-width=".8"/>';
        $notes = [
            '1. Loss of PPC or data-logger communication must not defeat the independent hardwired export-trip path.',
            '2. PPC setpoint, response time, command timeout and inverter fail-state require commissioning tests.',
            '3. Utility export limit, relay threshold, delay, reset and trip-supply philosophy require controlled approval.',
            '4. Switching devices are shown open and de-energized unless stated otherwise. Internal review only.',
        ];
        foreach ($notes as $i => $note) {
            $parts[] = '<text x="87" y="'.(830 + ($i * 24)).'" class="sld-note">'.$this->escape($note).'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }


    /** @param array<string, array<string, mixed>> $nodes */
    private function professionalProtectiveEarthNetwork(array $nodes): string
    {
        $parts = ['<g aria-label="Protective earth busbar and direct electrode connection">'];
        $trunkY = 810.0;
        $parts[] = '<path d="M64 '.$trunkY.'H1215" class="sld-connection protective-earth"/>';
        $parts[] = '<text x="78" y="'.($trunkY - 13).'" class="sld-flow-label sld-label-halo">PE-BUS-01 · PROTECTIVE-EARTH BONDING TRUNK</text>';

        foreach ([[94.0, 391.0], [94.0, 631.0]] as [$sourceX, $sourceY]) {
            $parts[] = '<path d="M'.$sourceX.' '.$sourceY.'H64V'.$trunkY.'" class="sld-connection protective-earth"/>';
            $parts[] = '<circle cx="64" cy="'.$trunkY.'" r="3.1" class="sld-junction"/>';
        }
        foreach ([[548.0, 409.0], [548.0, 649.0]] as [$x, $sourceY]) {
            $parts[] = '<path d="M'.$x.' '.$sourceY.'V'.$trunkY.'" class="sld-connection protective-earth"/>';
            $parts[] = '<circle cx="'.$x.'" cy="'.$trunkY.'" r="3.1" class="sld-junction"/>';
        }
        foreach ([[660.0, 345.0], [660.0, 585.0]] as [$sourceX, $sourceY]) {
            $branchX = 672.0;
            $parts[] = '<path d="M'.$sourceX.' '.$sourceY.'H'.$branchX.'V'.$trunkY.'" class="sld-connection protective-earth"/>';
            $parts[] = '<circle cx="'.$branchX.'" cy="'.$trunkY.'" r="3.1" class="sld-junction"/>';
        }
        $parts[] = '<path d="M922 540V'.$trunkY.'" class="sld-connection protective-earth"/>';
        $parts[] = '<circle cx="922" cy="'.$trunkY.'" r="3.1" class="sld-junction"/>';

        // Enter through the left wall below the title band. The header remains
        // a strict keep-out zone. Conductors terminate directly on device ports.
        $entryX = 370.0;
        $deviceY = 878.0;
        $busX = 555.0;
        $rodX = 720.0;
        $busLeft = $busX - 22.5;
        $busRight = $busX + 22.5;
        $rodLeft = $rodX - 14.0;
        $parts[] = '<path d="M'.$entryX.' '.$trunkY.'V'.$deviceY.'H'.$busLeft.'" class="sld-connection protective-earth" data-paper-route="pe-enclosure-entry"/>';
        $parts[] = '<circle cx="'.$entryX.'" cy="'.$trunkY.'" r="3.3" class="sld-junction"/>';
        $parts[] = $this->professionalPortSvg($busLeft, $deviceY, 'earth');
        $parts[] = '<path d="M'.$busRight.' '.$deviceY.'H'.$rodLeft.'" class="sld-connection protective-earth" data-paper-route="pe-bus-to-electrode"/>';
        $parts[] = $this->professionalPortSvg($busRight, $deviceY, 'earth');
        $parts[] = $this->professionalPortSvg($rodLeft, $deviceY, 'earth');

        $parts[] = '<g transform="translate('.$busX.' 862) scale(.58) translate('.(-$busX).' -862)">'.$this->symbol('pe_bus_paper', $busX, 862.0).'</g>';
        $parts[] = '<rect x="493" y="892" width="124" height="43" rx="2" class="sld-label-box"/>';
        $parts[] = '<text x="555" y="904" text-anchor="middle" class="sld-device-tag">PE-BUS-01</text>';
        $parts[] = '<text x="555" y="918" text-anchor="middle" class="sld-device-title">PE BUS</text>';
        $parts[] = '<text x="555" y="932" text-anchor="middle" class="sld-device-rating">COMMON PE</text>';

        $parts[] = '<g transform="translate('.$rodX.' 862) scale(.58) translate('.(-$rodX).' -862)">'.$this->symbol('ground_rod', $rodX, 862.0).'</g>';
        $parts[] = '<rect x="650" y="892" width="140" height="43" rx="2" class="sld-label-box"/>';
        $parts[] = '<text x="720" y="904" text-anchor="middle" class="sld-device-tag">GND-01</text>';
        $parts[] = '<text x="720" y="918" text-anchor="middle" class="sld-device-title">EARTH ELECTRODE</text>';
        $parts[] = '<text x="720" y="932" text-anchor="middle" class="sld-device-rating">R BY SITE TEST</text>';

        // Notes sit in a padded footer band, safely above the enclosure border.
        $parts[] = '<rect x="410" y="940" width="400" height="24" rx="2" class="sld-label-box"/>';
        $parts[] = '<text x="420" y="950" class="sld-note">EARTHING SYSTEM, PE SIZES AND FAULT LOOP: TBC</text>';
        $parts[] = '<text x="420" y="962" class="sld-note">EARTH RESISTANCE: CONFIRM BY SITE TEST</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }


    /** @param array<string, mixed> $node @return array{0:string,1:string} */
    private function professionalCompactDeviceText(string $id, array $node): array
    {
        $rating = trim((string) ($node['rating'] ?? ''));
        $compact = match (true) {
            str_starts_with($id, 'pv_group_') => ['PV ARRAY', preg_replace('/\s*·\s*\d+\/\d+\s+MPPT.*/i', '', $rating) ?: $rating],
            str_starts_with($id, 'dc_isolator_') => ['DC ISOLATOR', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'dc_fuse_') => ['gPV FUSE IF REQUIRED', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'dc_bus_') => ['DC BUS', preg_replace('/\s*·\s*\d+\s*A.*/i', '', $rating) ?: $rating],
            str_starts_with($id, 'dc_spd_') => ['DC SPD', str_ireplace(['Type 2 DC SPD ', 'Type II DC SPD '], ['TYPE 2 / ', 'TYPE 2 / '], $rating)],
            str_starts_with($id, 'inverter_') => ['INVERTER', preg_match('/([0-9.]+)\s*kW\s*AC/i', $rating, $m) ? $m[1].' kW AC' : (preg_replace('/\s*\/\s*ratio\s+[0-9.]+/i', '', str_replace(' · ', ' / ', $rating)) ?: $rating)],
            str_starts_with($id, 'ac_breaker_') => ['AC CB', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'ac_isolator_') => ['AC ISOLATOR', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'pcc_breaker_') => ['PCC MAIN CB', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'pcc_disconnect_') => ['PCC DISCONNECT', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'ac_spd_backup_') => ['SPD BACKUP', 'RATING / POLES TBC'],
            str_starts_with($id, 'export_relay_') => ['REVERSE POWER RELAY', 'PRELIMINARY EXPORT TRIP'],
            str_starts_with($id, 'ac_combiner_') => ['AC COMBINER', str_replace(' aggregate · ', ' / ', $rating)],
            str_starts_with($id, 'ac_spd_') => ['AC SPD', str_ireplace(['Type 2 AC SPD ', 'Type II AC SPD '], ['TYPE 2 / ', 'TYPE 2 / '], $rating)],
            str_starts_with($id, 'ct_') => ['PCC CT', 'DESIGN DATA TBC'],
            str_starts_with($id, 'meter_') => ['POWER METER', 'CT-OPERATED / MODBUS'],
            str_starts_with($id, 'grid_') => ['PCC / GRID', 'UTILITY INTERFACE'],
            str_starts_with($id, 'data_logger_') => ['DATA LOGGER', 'RS485 / ETHERNET'],
            str_starts_with($id, 'plant_controller_') => ['PLANT CTRL', 'ZERO-EXPORT LOOP'],
            str_starts_with($id, 'pe_bus_') => ['PE BUS', 'COMMON PE'],
            str_starts_with($id, 'ground_rod_') => ['EARTH ELECTRODE', 'R BY SITE TEST'],
            default => [(string) ($node['title'] ?? $id), $rating],
        };

        return [trim((string) $compact[0]), trim((string) $compact[1])];
    }

    /** @param array<int, array<string, mixed>> $groups */
    private function professionalMpptSummaryTables(array $groups): string
    {
        $parts = ['<g aria-label="MPPT summary tables">'];
        foreach (array_slice($groups, 0, 2) as $index => $group) {
            $x = $index === 0 ? 82.0 : 622.0;
            $y = 124.0;
            $w = 500.0;
            $strings = is_array($group['strings'] ?? null) ? $group['strings'] : [];
            $modulesPerString = $strings !== [] ? (int) ($strings[0]['modules'] ?? 0) : 0;
            $stringQty = count($strings);
            $moduleQty = (int) ($group['assigned_module_count'] ?? array_sum(array_map(static fn (array $row): int => (int) ($row['modules'] ?? 0), $strings)));
            $capacity = (float) ($group['assigned_dc_kw'] ?? array_sum(array_map(static fn (array $row): float => (float) ($row['dc_kw'] ?? 0), $strings)));
            $inverter = (string) ($group['inverter_tag'] ?? 'INV-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT));
            $ratio = (float) data_get($group, 'dc_ac_ratio', 0);
            if ($ratio <= 0) $ratio = (float) data_get($group, 'strings.0.dc_ac_ratio', 0);
            $parts[] = '<rect x="'.$this->f($x).'" y="'.$this->f($y).'" width="'.$this->f($w).'" height="105" fill="#fff" stroke="#0a1d26" stroke-width="1"/>';
            $parts[] = '<rect x="'.$this->f($x).'" y="'.$this->f($y).'" width="'.$this->f($w).'" height="22" fill="#eef4f4"/>';
            $parts[] = '<text x="'.$this->f($x + 10).'" y="'.$this->f($y + 15).'" class="sld-foot-mono">'.$this->escape($inverter).' · MPPT / STRING SUMMARY</text>';
            $columns = [0, 88, 190, 280, 370, 500];
            foreach (array_slice($columns, 1, -1) as $offset) $parts[] = '<path d="M'.$this->f($x + $offset).' '.$this->f($y + 22).'V'.$this->f($y + 68).'" stroke="#56656d" stroke-width=".7"/>';
            $parts[] = '<path d="M'.$this->f($x).' '.$this->f($y + 44).'H'.$this->f($x + $w).'M'.$this->f($x).' '.$this->f($y + 68).'H'.$this->f($x + $w).'M'.$this->f($x).' '.$this->f($y + 87).'H'.$this->f($x + $w).'" stroke="#56656d" stroke-width=".7"/>';
            $headers = ['MPPT', 'MODULE/STRING', 'STRING QTY', 'MODULE QTY', 'CAPACITY'];
            $centers = [44, 139, 235, 325, 435];
            foreach ($headers as $h => $header) $parts[] = '<text x="'.$this->f($x + $centers[$h]).'" y="'.$this->f($y + 37).'" text-anchor="middle" class="sld-foot-label">'.$header.'</text>';
            $values = ['1-'.$this->number($group['used_mppt_count'] ?? $stringQty, 0), (string) $modulesPerString, (string) $stringQty, (string) $moduleQty, $this->number($capacity, 2).' kWp'];
            foreach ($values as $v => $value) $parts[] = '<text x="'.$this->f($x + $centers[$v]).'" y="'.$this->f($y + 61).'" text-anchor="middle" class="sld-foot-mono">'.$this->escape($value).'</text>';
            $parts[] = '<text x="'.$this->f($x + 10).'" y="'.$this->f($y + 82).'" class="sld-foot-label">TOTAL CAPACITY</text>';
            $parts[] = '<text x="'.$this->f($x + $w - 10).'" y="'.$this->f($y + 82).'" text-anchor="end" class="sld-foot-mono">'.$this->number($capacity, 2).' kWp</text>';
            $parts[] = '<text x="'.$this->f($x + 10).'" y="'.$this->f($y + 101).'" class="sld-foot-label">DC/AC RATIO</text>';
            $parts[] = '<text x="'.$this->f($x + $w - 10).'" y="'.$this->f($y + 101).'" text-anchor="end" class="sld-foot-mono">'.($ratio > 0 ? $this->number($ratio, 3) : 'SEE DESIGN BASIS').'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalShowsWireLabel(array $connection): bool
    {
        return false;
    }

    /** @param array<string, array<string, mixed>> $positions @return array<int, array{x:float,y:float}>|null */
    private function professionalRoute(array $connection, array $positions): ?array
    {
        $id = (string) ($connection['id'] ?? $connection['_id'] ?? '');
        $from = (string) ($connection['from'] ?? '');
        $to = (string) ($connection['to'] ?? '');
        if (! isset($positions[$from], $positions[$to])) return null;
        $a = $positions[$from];
        $b = $positions[$to];

        if (preg_match('/^C-AC-(\d+)-03$/', $id)) {
            $channelX = 1078.0 + ((int) substr($id, 5, 2) * 9.0);
            return [['x' => $channelX, 'y' => (float) $a['y']], ['x' => $channelX, 'y' => (float) $b['y']]];
        }
        if ($id === 'C-AC-MAIN-01' || $id === 'C-AC-PCC-01') {
            $channelX = 1242.0;
            return [['x' => $channelX, 'y' => (float) $a['y']], ['x' => $channelX, 'y' => (float) $b['y']]];
        }
        if ($id === 'C-AC-SPD-01') {
            return [['x' => 1085.0, 'y' => (float) $a['y']], ['x' => 1085.0, 'y' => (float) $b['y']]];
        }
        if (str_starts_with($id, 'C-COM-INV-')) {
            $channelY = 636.0 + (((int) substr($id, -2)) * 8.0);
            return [['x' => (float) $a['x'], 'y' => $channelY], ['x' => (float) $b['x'], 'y' => $channelY]];
        }
        if ($id === 'C-COM-MTR-01') {
            return [['x' => 1000.0, 'y' => (float) $a['y']], ['x' => 1000.0, 'y' => 704.0], ['x' => (float) $b['x'], 'y' => 704.0]];
        }
        if ($id === 'C-COM-PPC-01' || $id === 'C-CTRL-PPC-01') {
            return [['x' => ((float) $a['x'] + (float) $b['x']) / 2, 'y' => (float) $a['y']]];
        }
        if (str_starts_with($id, 'C-PE-') && $id !== 'C-PE-GND-01') {
            $busY = 822.0;
            return [['x' => (float) $a['x'], 'y' => $busY], ['x' => 500.0, 'y' => $busY]];
        }
        if ($id === 'C-PE-GND-01') {
            return [['x' => 595.0, 'y' => (float) $a['y']], ['x' => 595.0, 'y' => 842.0], ['x' => (float) $b['x'], 'y' => 842.0]];
        }

        return null;
    }

    private function professionalMainLegend(): string
    {
        $x = 1290.0; $y = 68.0; $w = 326.0; $h = 438.0;
        $parts = ['<g aria-label="Drawing legend">'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26" stroke-width="1"/>';
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="28" fill="#eef4f4"/>';
        $parts[] = '<text x="'.($x + 12).'" y="'.($y + 19).'" class="sld-foot-mono">LEGEND · FUNCTIONAL SYMBOLS</text>';

        $lineItems = [
            ['DC / PV', '#b94141', '18 3 3 3'], ['AC POWER', '#a66b2a', ''],
            ['PROTECTIVE EARTH', '#31825a', '7 5'], ['RS485 / COM', '#7258a8', '7 5'],
            ['CONTROL / MEAS.', '#5579b8', '9 4 2 4'],
        ];
        foreach ($lineItems as $index => [$label, $color, $dash]) {
            $col = $index % 2;
            $row = intdiv($index, 2);
            $lineX = $x + 18 + ($col * 155);
            $lineY = $y + 52 + ($row * 34);
            $dashAttr = $dash !== '' ? ' stroke-dasharray="'.$dash.'"' : '';
            $parts[] = '<path d="M'.$lineX.' '.$lineY.'H'.($lineX + 52).'" stroke="'.$color.'" stroke-width="2.5"'.$dashAttr.'/>';
            $parts[] = '<text x="'.($lineX + 62).'" y="'.($lineY + 4).'" class="sld-legend-text">'.$label.'</text>';
        }
        $dividerY = $y + 164;
        $parts[] = '<path d="M'.($x + 12).' '.$dividerY.'H'.($x + $w - 12).'" stroke="#a3afb4"/>';

        $symbols = [
            ['pv_array', 'PV STRING'], ['inverter', 'INVERTER'],
            ['dc_isolator', 'ISOLATOR'], ['ac_breaker', 'BREAKER'],
            ['dc_fuse', 'FUSE (IF REQ.)'], ['dc_spd_paper', 'SPD'],
            ['ac_combiner', 'AC COMBINER'], ['ct', 'CT SET'],
            ['meter_paper', 'POWER METER'], ['ground_rod', 'EARTH ELECTRODE'],
        ];
        foreach ($symbols as $index => [$kind, $label]) {
            $column = $index % 2;
            $row = intdiv($index, 2);
            $sx = $x + 34 + ($column * 160);
            $sy = $y + 195 + ($row * 42);
            $parts[] = '<g transform="translate('.$sx.' '.$sy.') scale(.40) translate('.(-$sx).' '.(-$sy).')">'.$this->symbol($kind, $sx, $sy).'</g>';
            $parts[] = '<text x="'.($sx + 32).'" y="'.($sy + 4).'" class="sld-legend-text">'.$label.'</text>';
        }

        $dotY = $y + 405;
        $parts[] = '<circle cx="'.($x + 22).'" cy="'.$dotY.'" r="4" class="sld-junction"/>';
        $parts[] = '<text x="'.($x + 38).'" y="'.($dotY + 4).'" class="sld-legend-text">FILLED DOT: CONNECTED JUNCTION</text>';
        $parts[] = '<text x="'.($x + 12).'" y="'.($y + $h - 12).'" class="sld-legend-note">IEC-aligned functional reference. Project verification required.</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }


    private function professionalMainEquipmentSummary(array $graph): string
    {
        $summary = is_array(data_get($graph, 'meta.summary')) ? data_get($graph, 'meta.summary') : [];
        $x = 1290.0; $y = 520.0; $w = 326.0; $h = 198.0;
        $parts = ['<g aria-label="Equipment summary">'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>';
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="28" fill="#eef4f4"/>';
        $parts[] = '<text x="'.($x + 12).'" y="'.($y + 19).'" class="sld-foot-mono">DESIGN / EQUIPMENT SUMMARY</text>';
        $rows = [
            ['PV MODULES', $this->number($summary['module_count'] ?? null, 0).' units'],
            ['PV CAPACITY', $this->number($summary['installed_pv_kw'] ?? null, 2).' kWp'],
            ['ARRAY LAYOUT', (string) ($summary['string_layout'] ?? 'Pending')],
            ['INVERTERS', $this->number($summary['inverter_count'] ?? null, 0).' x '.$this->number($summary['inverter_unit_ac_kw'] ?? null, 2).' kW'],
            ['TOTAL AC', $this->number($summary['inverter_ac_kw'] ?? null, 2).' kW'],
            ['DC / AC RATIO', $this->number($summary['dc_ac_ratio'] ?? null, 3)],
            ['SUPPLY', strtoupper(str_replace('_', ' ', (string) ($summary['supply_phase'] ?? 'utility')))],
        ];
        foreach ($rows as $index => [$label, $value]) {
            $rowY = $y + 49 + ($index * 20);
            if ($index > 0) $parts[] = '<path d="M'.($x + 8).' '.($rowY - 14).'H'.($x + $w - 8).'" stroke="#d2d9dc" stroke-width=".6"/>';
            $parts[] = '<text x="'.($x + 12).'" y="'.$rowY.'" class="sld-foot-label">'.$label.'</text>';
            $parts[] = '<text x="'.($x + $w - 12).'" y="'.$rowY.'" text-anchor="end" class="sld-foot-mono">'.$this->escape($this->shorten($value, 31)).'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalMainNotes(array $paperSummary): string
    {
        $x = 1290.0; $y = 732.0; $w = 326.0; $h = 212.0;
        $parts = ['<g aria-label="Engineering notes">'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>';
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="28" fill="#eef4f4"/>';
        $parts[] = '<text x="'.($x + 12).'" y="'.($y + 19).'" class="sld-foot-mono">GENERAL NOTES</text>';
        $notes = [
            '1. Verify cable ampacity, voltage drop, routing, derating and short-circuit duty before construction.',
            '2. Confirm protection coordination, SPD selection and disconnection ratings against final data.',
            '3. Bond exposed conductive parts to project PE. Confirm earth resistance by final site test.',
            '4. Export control requires verified metering, communication and an approved trip philosophy.',
            '5. Internal review only. Not for construction. Approval and conformity assessment pending.',
        ];
        foreach ($notes as $index => $note) {
            foreach ($this->wrapWords($note, 53, 2) as $lineIndex => $line) {
                $parts[] = '<text x="'.($x + 12).'" y="'.($y + 50 + ($index * 29) + ($lineIndex * 11)).'" class="sld-note">'.$this->escape($line).'</text>';
            }
        }
        $parts[] = '<text x="'.($x + 12).'" y="'.($y + $h - 12).'" class="sld-device-tag">COMPOSITION '.$this->escape((string) ($paperSummary['composition_version'] ?? '67L.3')).' · NTS</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalStringSchedule(array $graph): string
    {
        $summary = is_array(data_get($graph, 'meta.summary')) ? data_get($graph, 'meta.summary') : [];
        $groups = is_array($summary['string_groups'] ?? null) ? $summary['string_groups'] : [];
        $isc = $this->number($summary['module_isc_a'] ?? null, 2);
        $imp = $this->number($summary['module_imp_a'] ?? null, 2);
        $maxDc = (float) ($summary['inverter_max_dc_voltage_v'] ?? 0);
        $mpptMin = (float) ($summary['inverter_mppt_min_voltage_v'] ?? 0);
        $mpptMax = (float) ($summary['inverter_mppt_max_voltage_v'] ?? 0);
        $maxInputA = (float) ($summary['inverter_max_input_current_a'] ?? 0);
        $rows = [];
        foreach ($groups as $group) {
            foreach (is_array($group['strings'] ?? null) ? $group['strings'] : [] as $string) {
                $voc = (float) ($string['voc_cold_v'] ?? 0);
                $vmp = (float) ($string['vmp_hot_v'] ?? 0);
                $moduleIsc = (float) ($summary['module_isc_a'] ?? 0);
                $pass = $voc > 0 && ($maxDc <= 0 || $voc <= $maxDc)
                    && $vmp > 0 && ($mpptMin <= 0 || $vmp >= $mpptMin)
                    && ($mpptMax <= 0 || $vmp <= $mpptMax)
                    && ($maxInputA <= 0 || $moduleIsc <= $maxInputA);
                $rows[] = [
                    (string) ($group['inverter_tag'] ?? 'INV'),
                    (string) ($string['mppt_label'] ?? 'MPPT'),
                    (string) ($string['tag'] ?? 'STR'),
                    (string) ((int) ($string['modules'] ?? 0)),
                    $this->number($string['dc_kw'] ?? null, 2),
                    $this->number($voc, 1),
                    $this->number($vmp, 1),
                    $isc,
                    $imp,
                    $pass ? 'PRELIM CALC PASS' : 'REVIEW',
                ];
            }
        }

        $parts = ['<g aria-label="MPPT and string schedule">'];
        $parts[] = '<text x="72" y="143" class="sld-foot-mono">MPPT AND STRING ELECTRICAL CHECK SCHEDULE</text>';
        $x = 72.0; $y = 158.0; $w = 1536.0; $headerH = 34.0; $rowH = 36.0;
        $widths = [120, 120, 120, 160, 150, 170, 170, 130, 130, 266];
        $headers = ['INVERTER', 'MPPT', 'STRING', 'MODULES / STRING', 'DC kWp', 'VOC COLD (V)', 'VMP HOT (V)', 'ISC (A)', 'IMP (A)', 'CHECK STATUS'];
        $height = $headerH + (max(1, count($rows)) * $rowH);
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$height.'" fill="#fff" stroke="#0a1d26"/>';
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$headerH.'" fill="#e9f0f0"/>';
        $cursor = $x;
        foreach ($widths as $index => $cellW) {
            if ($index > 0) $parts[] = '<path d="M'.$cursor.' '.$y.'V'.($y + $height).'" stroke="#56656d" stroke-width=".7"/>';
            $parts[] = '<text x="'.($cursor + ($cellW / 2)).'" y="'.($y + 22).'" text-anchor="middle" class="sld-foot-label">'.$headers[$index].'</text>';
            $cursor += $cellW;
        }
        if ($rows === []) $rows[] = ['-', '-', '-', '-', '-', '-', '-', '-', '-', 'TBC'];
        foreach ($rows as $r => $row) {
            $rowY = $y + $headerH + ($r * $rowH);
            $parts[] = '<path d="M'.$x.' '.$rowY.'H'.($x + $w).'" stroke="#56656d" stroke-width=".7"/>';
            $cursor = $x;
            foreach ($row as $c => $value) {
                $class = $c === 9 ? 'sld-device-tag' : 'sld-foot-mono';
                $parts[] = '<text x="'.($cursor + ($widths[$c] / 2)).'" y="'.($rowY + 23).'" text-anchor="middle" class="'.$class.'">'.$this->escape($value).'</text>';
                $cursor += $widths[$c];
            }
        }

        $parts[] = '<text x="72" y="420" class="sld-note">PRELIMINARY CHECK ONLY: VOC, VMP AND ISC ARE CHECKED AGAINST LINKED DESIGN INPUTS. MODULE AND INVERTER PRODUCT DATASHEET VERIFICATION REMAINS PENDING.</text>';
        $summaryY = 440.0;
        $parts[] = $this->professionalSummaryTable($summary, 72.0, $summaryY, 480.0, 'SYSTEM DESIGN BASIS');
        $parts[] = $this->professionalMpptUtilizationTable($summary, 568.0, $summaryY, 500.0);
        $parts[] = $this->professionalEquipmentTable($graph, 1084.0, $summaryY, 524.0);
        $parts[] = $this->professionalExpandedLegend(72.0, 690.0, 1536.0, 265.0);
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalSummaryTable(array $summary, float $x, float $y, float $w, string $title): string
    {
        $rows = [
            ['Installed PV', $this->number($summary['installed_pv_kw'] ?? null, 2).' kWp'],
            ['Module quantity', $this->number($summary['module_count'] ?? null, 0).' units'],
            ['String layout', (string) ($summary['string_layout'] ?? 'Pending')],
            ['Inverter AC', $this->number($summary['inverter_ac_kw'] ?? null, 2).' kW'],
            ['DC/AC ratio', $this->number($summary['dc_ac_ratio'] ?? null, 3)],
            ['Module Voc / Vmp', $this->number($summary['module_voc_v'] ?? null, 1).' / '.$this->number($summary['module_vmp_v'] ?? null, 1).' V'],
            ['Module Isc / Imp', $this->number($summary['module_isc_a'] ?? null, 2).' / '.$this->number($summary['module_imp_a'] ?? null, 2).' A'],
            ['MPPT range', $this->number($summary['inverter_mppt_min_voltage_v'] ?? null, 0).' - '.$this->number($summary['inverter_mppt_max_voltage_v'] ?? null, 0).' V'],
            ['Max DC / input', $this->number($summary['inverter_max_dc_voltage_v'] ?? null, 0).' V / '.$this->number($summary['inverter_max_input_current_a'] ?? null, 1).' A'],
            ['Minimum ambient temp.', $this->number($summary['minimum_site_temperature_c'] ?? null, 0).' °C - SITE BASIS TBC'],
            ['Maximum cell temp.', $this->number($summary['maximum_cell_temperature_c'] ?? null, 0).' °C - MODEL BASIS TBC'],
            ['AC earthing system', (string) ($summary['ac_earthing_system'] ?? 'TBC')],
            ['PCC fault level', is_numeric($summary['pcc_fault_level_ka'] ?? null) ? $this->number($summary['pcc_fault_level_ka'], 1).' kA' : 'TBC'],
        ];
        $h = 245.0;
        $parts = ['<g><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="28" fill="#e9f0f0"/><text x="'.($x + 12).'" y="'.($y + 19).'" class="sld-foot-mono">'.$this->escape($title).'</text>';
        foreach ($rows as $index => [$label, $value]) {
            $rowY = $y + 47 + ($index * 15.5);
            $parts[] = '<text x="'.($x + 12).'" y="'.$rowY.'" class="sld-foot-label">'.$this->escape($label).'</text>';
            $parts[] = '<text x="'.($x + $w - 12).'" y="'.$rowY.'" text-anchor="end" class="sld-foot-mono">'.$this->escape($this->shorten($value, 40)).'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalMpptUtilizationTable(array $summary, float $x, float $y, float $w): string
    {
        $groups = is_array($summary['string_groups'] ?? null) ? $summary['string_groups'] : [];
        $assignments = is_array($summary['inverter_string_assignments'] ?? null) ? $summary['inverter_string_assignments'] : [];
        $first = $groups[0] ?? [];
        $second = $groups[1] ?? [];
        $firstAvailable = max(1, (int) data_get($assignments, '0.available_mppt_count', 1));
        $secondAvailable = max(1, (int) data_get($assignments, '1.available_mppt_count', $firstAvailable));
        $firstUsed = (int) ($first['used_mppt_count'] ?? 0);
        $secondUsed = (int) ($second['used_mppt_count'] ?? 0);
        $rows = [
            ['INVERTER', (string) ($first['inverter_tag'] ?? 'INV-01'), (string) ($second['inverter_tag'] ?? 'INV-02')],
            ['ASSIGNED DC', $this->number($first['assigned_dc_kw'] ?? null, 2).' kWp', $this->number($second['assigned_dc_kw'] ?? null, 2).' kWp'],
            ['RATED AC', $this->number($summary['inverter_unit_ac_kw'] ?? null, 2).' kW', $this->number($summary['inverter_unit_ac_kw'] ?? null, 2).' kW'],
            ['STRINGS', $this->number(count(is_array($first['strings'] ?? null) ? $first['strings'] : []), 0), $this->number(count(is_array($second['strings'] ?? null) ? $second['strings'] : []), 0)],
            ['MPPT USED', $this->number($firstUsed, 0).' / '.$firstAvailable, $this->number($secondUsed, 0).' / '.$secondAvailable],
            ['SPARE MPPT', $this->number(max(0, $firstAvailable - $firstUsed), 0), $this->number(max(0, $secondAvailable - $secondUsed), 0)],
            ['DC / AC RATIO', $this->number($first['dc_ac_ratio'] ?? $summary['dc_ac_ratio'] ?? null, 3), $this->number($second['dc_ac_ratio'] ?? $summary['dc_ac_ratio'] ?? null, 3)],
            ['STATUS', 'PRELIM / PRODUCT TBC', 'PRELIM / PRODUCT TBC'],
        ];
        $h = 245.0;
        $labelW = 170.0;
        $valueW = ($w - $labelW) / 2;
        $parts = ['<g aria-label="Inverter MPPT utilization"><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="28" fill="#e9f0f0"/><text x="'.($x + 12).'" y="'.($y + 19).'" class="sld-foot-mono">INVERTER / MPPT UTILIZATION</text>';
        foreach ($rows as $index => $row) {
            $rowTop = $y + 28 + ($index * 25.2);
            $parts[] = '<path d="M'.$x.' '.$rowTop.'H'.($x + $w).'" stroke="#56656d" stroke-width=".65"/>';
            $parts[] = '<path d="M'.($x + $labelW).' '.$rowTop.'V'.($y + $h).'M'.($x + $labelW + $valueW).' '.$rowTop.'V'.($y + $h).'" stroke="#56656d" stroke-width=".65"/>';
            $parts[] = '<text x="'.($x + 10).'" y="'.($rowTop + 18).'" class="sld-foot-label">'.$this->escape($row[0]).'</text>';
            $parts[] = '<text x="'.($x + $labelW + ($valueW / 2)).'" y="'.($rowTop + 18).'" text-anchor="middle" class="sld-foot-mono">'.$this->escape($this->shorten($row[1], 24)).'</text>';
            $parts[] = '<text x="'.($x + $labelW + $valueW + ($valueW / 2)).'" y="'.($rowTop + 18).'" text-anchor="middle" class="sld-foot-mono">'.$this->escape($this->shorten($row[2], 24)).'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /** @param array<string, mixed> $summary */
    private function professionalModulePowerWp(array $summary): ?float
    {
        $moduleCount = (float) ($summary['module_count'] ?? 0);
        $installedKw = (float) ($summary['installed_pv_kw'] ?? 0);
        if ($moduleCount <= 0 || $installedKw <= 0) return null;

        return ($installedKw * 1000) / $moduleCount;
    }

    private function professionalEquipmentTable(array $graph, float $x, float $y, float $w): string
    {
        $summary = is_array(data_get($graph, 'meta.summary')) ? data_get($graph, 'meta.summary') : [];
        $rows = [
            ['COMPONENT', 'PV MODULE / ARRAY', 'STRING INVERTER'],
            ['TAG RANGE', 'PVA-01..02', 'INV-01..02'],
            ['PRODUCT MODEL', 'BOM / DATASHEET TBC', 'BOM / DATASHEET TBC'],
            ['UNIT RATING', $this->number($summary['module_power_wp'] ?? $this->professionalModulePowerWp($summary), 0).' Wp', $this->number($summary['inverter_unit_ac_kw'] ?? null, 2).' kW'],
            ['QUANTITY', $this->number($summary['module_count'] ?? null, 0).' modules', $this->number($summary['inverter_count'] ?? null, 0).' units'],
            ['TOTAL CAPACITY', $this->number($summary['installed_pv_kw'] ?? null, 2).' kWp', $this->number($summary['inverter_ac_kw'] ?? null, 2).' kW'],
            ['ELECTRICAL BASIS', '550 Wp CLASS / CALC.', 'MAX DC '.$this->number($summary['inverter_max_dc_voltage_v'] ?? null, 0).' V'],
            ['RELEASE STATUS', 'PRODUCT SELECTION TBC', 'PRODUCT SELECTION TBC'],
        ];
        $h = 245.0;
        $col1 = 150.0; $col2 = ($w - $col1) / 2;
        $rowH = ($h - 28.0) / count($rows);
        $parts = ['<g><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="28" fill="#e9f0f0"/><text x="'.($x + 12).'" y="'.($y + 19).'" class="sld-foot-mono">EQUIPMENT SCHEDULE</text>';
        foreach ($rows as $index => $row) {
            $rowTop = $y + 28 + ($index * $rowH);
            $parts[] = '<path d="M'.$x.' '.$rowTop.'H'.($x + $w).'" stroke="#56656d" stroke-width=".7"/>';
            $parts[] = '<path d="M'.($x + $col1).' '.$rowTop.'V'.($y + $h).'M'.($x + $col1 + $col2).' '.$rowTop.'V'.($y + $h).'" stroke="#56656d" stroke-width=".7"/>';
            $parts[] = '<text x="'.($x + 10).'" y="'.($rowTop + 18).'" class="sld-foot-label">'.$this->escape($row[0]).'</text>';
            $parts[] = '<text x="'.($x + $col1 + ($col2 / 2)).'" y="'.($rowTop + 18).'" text-anchor="middle" class="sld-foot-mono">'.$this->escape($this->shorten($row[1], 25)).'</text>';
            $parts[] = '<text x="'.($x + $col1 + $col2 + ($col2 / 2)).'" y="'.($rowTop + 18).'" text-anchor="middle" class="sld-foot-mono">'.$this->escape($this->shorten($row[2], 25)).'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalExpandedLegend(float $x, float $y, float $w, float $h): string
    {
        $parts = ['<g aria-label="Expanded drawing legend"><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="28" fill="#e9f0f0"/><text x="'.($x + 12).'" y="'.($y + 19).'" class="sld-foot-mono">DRAWING LEGEND / ABBREVIATIONS / SYMBOL REFERENCE</text>';
        $lines = [
            ['DC POWER', '#b94141', '18 3 3 3'], ['AC POWER', '#765022', ''], ['PE / GROUND', '#31825a', '7 5'],
            ['RS485 / COM', '#7258a8', '7 5'], ['CONTROL', '#5579b8', '9 4 2 4'], ['MEASUREMENT', '#438b90', '3 3'],
        ];
        foreach ($lines as $index => [$label, $color, $dash]) {
            $col = $index % 3; $row = intdiv($index, 3);
            $lx = $x + 38 + ($col * 505); $ly = $y + 58 + ($row * 36);
            $dashAttr = $dash !== '' ? ' stroke-dasharray="'.$dash.'"' : '';
            $parts[] = '<path d="M'.$lx.' '.$ly.'H'.($lx + 88).'" stroke="'.$color.'" stroke-width="2.6"'.$dashAttr.'/><text x="'.($lx + 105).'" y="'.($ly + 4).'" class="sld-foot-value">'.$label.'</text>';
        }
        $symbols = [
            ['pv_array', 'PV ARRAY'], ['inverter', 'INVERTER'], ['dc_isolator', 'ISOLATOR'], ['ac_breaker', 'BREAKER'], ['dc_fuse', 'FUSE IF REQ.'], ['dc_spd_paper', 'SPD'],
            ['ac_combiner', 'AC COMBINER'], ['meter_paper', 'POWER METER'], ['ct', 'CT SET'], ['controller_paper', 'PLANT CTRL'], ['pe_bus_paper', 'PE BUS'], ['ground_rod', 'EARTH ELECTRODE'],
        ];
        foreach ($symbols as $index => [$kind, $label]) {
            $col = $index % 6; $row = intdiv($index, 6);
            $sx = $x + 45 + ($col * 250); $sy = $y + 155 + ($row * 54);
            $parts[] = '<g transform="translate('.$sx.' '.$sy.') scale(.41) translate('.(-$sx).' '.(-$sy).')">'.$this->symbol($kind, $sx, $sy).'</g>';
            $parts[] = '<text x="'.($sx + 37).'" y="'.($sy + 4).'" class="sld-foot-value">'.$label.'</text>';
        }
        $footerY = $y + $h - 34;
        $parts[] = '<rect x="'.($x + 1).'" y="'.$footerY.'" width="'.($w - 2).'" height="33" fill="#fff"/>';
        $parts[] = '<path d="M'.($x + 12).' '.$footerY.'H'.($x + $w - 12).'" stroke="#d2d9dc"/>';
        $parts[] = '<text x="'.($x + 18).'" y="'.($footerY + 22).'" class="sld-note">IEC 60617 functional reference. Certification target remains pending independent conformity assessment.</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }


    private function professionalCableSchedule(array $graph, array $sheet): string
    {
        $allCables = is_array(data_get($graph, 'engineering_controller.cable_schedule')) ? data_get($graph, 'engineering_controller.cable_schedule') : [];
        $start = max(0, (int) ($sheet['schedule_start'] ?? 0));
        $limit = max(1, (int) ($sheet['schedule_limit'] ?? count($allCables)));
        $cables = array_slice($allCables, $start, $limit);
        $nodeTags = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) continue;
            $nodeTags[(string) $node['id']] = (string) ($node['tag'] ?? strtoupper((string) $node['id']));
        }
        $parts = ['<g aria-label="Cable and interconnection schedule">'];
        $parts[] = '<text x="72" y="132" class="sld-foot-mono">CABLE SCHEDULE - MODEL DATA LINKED - INDIVIDUAL CIRCUIT AND PORT CROSS-REFERENCES</text>';
        $parts[] = $this->professionalCableTable($cables, 72.0, 158.0, 1536.0, $start + 1, $nodeTags);
        $parts[] = '<text x="72" y="938" class="sld-note">TBC VALUES REQUIRE CONTROLLED ROUTE, BOM, DATASHEET OR SITE INPUT.</text>';
        $parts[] = '<text x="72" y="957" class="sld-note">CONFIRM L1/L2/L3/N/PE, INSTALLATION METHOD AND PROJECT EARTHING SYSTEM BEFORE RELEASE.</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalProtectionSchedule(array $graph): string
    {
        $protection = is_array(data_get($graph, 'engineering_controller.protection_schedule')) ? data_get($graph, 'engineering_controller.protection_schedule') : [];
        $nodeTags = [];
        $nodeMap = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! filled($node['id'] ?? null)) continue;
            $id = (string) $node['id'];
            $nodeTags[$id] = (string) ($node['tag'] ?? strtoupper($id));
            $nodeMap[$id] = $node;
        }
        $parts = ['<g aria-label="Protection, metering and open engineering data schedule">'];
        $parts[] = $this->professionalProtectionTable($protection, 72.0, 158.0, 1536.0, $nodeTags, $nodeMap);
        $parts[] = $this->professionalEngineeringGapsTable($graph, 72.0, 520.0, 980.0);
        $parts[] = $this->professionalScheduleLegend(1070.0, 520.0, 538.0);
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalEngineeringGapsTable(array $graph, float $x, float $y, float $w): string
    {
        $rows = [
            ['PCC fault level / Ik', 'TBC', 'Required for QF-PCC and feeder Icu/Ics selection'],
            ['AC earthing system', 'TBC', 'Confirm TN-S, TN-C-S, TT or IT; coordinate N, PE, RCD and fault loop'],
            ['PCC main breaker duty', 'TBC', 'Trip unit, settings, discrimination and utility interface approval'],
            ['PCC disconnect', 'PRELIMINARY', 'Lockable, accessible and utility requirements to be confirmed'],
            ['Reverse-power relay', 'PRELIMINARY', 'Independent CT/VT core, setpoint, delay, test block and hardwired trip TBC'],
            ['SPD duty', 'TBC', 'Uc/Ucpv, Up, In, Imax, backup protection and lead length'],
            ['Module / inverter model', 'TBC', 'All MPPT results are preliminary until product datasheets are controlled'],
            ['Cable routes / installation', 'PARTIAL', 'Local lengths, ampacity, grouping, ambient and installation method TBC'],
            ['Voltage drop / energy yield', 'PRELIMINARY', 'Rounded indicators only; calculation report references TBC'],
            ['Checker / approver', 'PENDING', 'Not for construction and not approved for utility interconnection'],
        ];
        $h = 390.0;
        $parts = ['<g><text x="'.$x.'" y="'.($y - 12).'" class="sld-foot-mono">OPEN ENGINEERING DATA / RELEASE GATES</text><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="32" fill="#e9f0f0"/>';
        $parts[] = '<path d="M'.($x + 250).' '.$y.'V'.($y + $h).'M'.($x + 390).' '.$y.'V'.($y + $h).'" stroke="#56656d" stroke-width=".7"/>';
        foreach ([['ITEM',$x+125],['STATUS',$x+320],['RELEASE REQUIREMENT',$x+685]] as [$label,$cx]) $parts[] = '<text x="'.$cx.'" y="'.($y+21).'" text-anchor="middle" class="sld-foot-label">'.$label.'</text>';
        foreach ($rows as $i => [$item,$status,$requirement]) {
            $ry=$y+32+($i*35.8);
            $parts[]='<path d="M'.$x.' '.$ry.'H'.($x+$w).'" stroke="#56656d" stroke-width=".55"/>';
            $parts[]='<text x="'.($x+12).'" y="'.($ry+21).'" class="sld-wire-detail">'.$this->escape($item).'</text>';
            $parts[]='<text x="'.($x+320).'" y="'.($ry+21).'" text-anchor="middle" class="sld-device-tag">'.$this->escape($status).'</text>';
            $parts[]='<text x="'.($x+405).'" y="'.($ry+21).'" class="sld-wire-detail">'.$this->escape($this->shorten($requirement,75)).'</text>';
        }
        $parts[]='</g>';
        return implode('', $parts);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function professionalCableTable(array $rows, float $x, float $y, float $w, int $start, array $nodeTags = []): string
    {
        $headerH = 30.0; $rowH = 25.0; $rowCount = max(1, count($rows));
        $widths = [$w * .15, $w * .28, $w * .21, $w * .17, $w * .11, $w * .08];
        $headers = ['CIRCUIT', 'FROM - TO', 'CABLE TYPE', 'SIZE / PAIR', 'V CLASS', 'LENGTH'];
        $parts = ['<g><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.($headerH + ($rowCount * $rowH)).'" fill="#fff" stroke="#0a1d26"/>'];
        $parts[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$headerH.'" fill="#e9f0f0"/>';
        $cursor = $x;
        foreach ($headers as $index => $header) {
            if ($index > 0) $parts[] = '<path d="M'.$cursor.' '.$y.'V'.($y + $headerH + ($rowCount * $rowH)).'" stroke="#56656d" stroke-width=".65"/>';
            $parts[] = '<text x="'.($cursor + ($widths[$index] / 2)).'" y="'.($y + 20).'" text-anchor="middle" class="sld-foot-label">'.$header.'</text>';
            $cursor += $widths[$index];
        }
        if ($rows === []) $rows = [['wire_id' => '-', 'from' => '-', 'to' => '-', 'cable_type' => '-', 'cores' => '-', 'cable_size' => '-', 'voltage_class' => '-', 'length_m' => '-']];
        foreach ($rows as $index => $row) {
            $rowY = $y + $headerH + ($index * $rowH);
            $parts[] = '<path d="M'.$x.' '.$rowY.'H'.($x + $w).'" stroke="#56656d" stroke-width=".65"/>';
            $from = (string) ($row['from'] ?? '-');
            $to = (string) ($row['to'] ?? '-');
            $fromDisplay = (string) ($row['from_display'] ?? ($nodeTags[$from] ?? $from));
            $toDisplay = (string) ($row['to_display'] ?? ($nodeTags[$to] ?? $to));
            $values = [
                (string) ($row['wire_id'] ?? $row['cable_tag'] ?? '-'),
                $fromDisplay.' > '.$toDisplay,
                (string) ($row['cable_type'] ?? '-'),
                $this->professionalCableCoreSize($row),
                $this->meaningfulEngineeringText((string) ($row['voltage_class'] ?? '')) ? (string) $row['voltage_class'] : 'TBC',
                $this->professionalCableLength($row['length_m'] ?? null),
            ];
            $cursor = $x;
            $limits = [26, 48, 36, 28, 22, 22];
            foreach ($values as $col => $value) {
                $parts[] = '<text x="'.($cursor + ($widths[$col] / 2)).'" y="'.($rowY + 18).'" text-anchor="middle" class="sld-wire-detail">'.$this->escape($this->shorten($value, $limits[$col] ?? 20)).'</text>';
                $cursor += $widths[$col];
            }
        }
        $parts[] = '<text x="'.($x + 8).'" y="'.($y - 8).'" class="sld-device-tag">ROWS '.$start.' - '.($start + max(0, count($rows) - 1)).'</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /** @param array<string, mixed> $row */
    private function professionalCableCoreSize(array $row): string
    {
        $explicit = trim((string) ($row['size_display'] ?? ''));
        if ($explicit !== '') return $explicit;

        $cores = (int) ($row['cores'] ?? 0);
        $size = trim((string) ($row['cable_size'] ?? ''));
        $isNumericSize = $size !== '' && preg_match('/^[0-9.,x×+\-\s]+$/u', $size) === 1;

        $runs = max(1, (int) ($row['parallel_runs'] ?? 1));
        $prefix = $runs > 1 ? $runs.' runs · ' : '';
        if (! $isNumericSize) {
            return $prefix.($cores > 0 ? $cores.'C x ' : '').'TBC';
        }

        return $prefix.($cores > 0 ? $cores.'C x ' : '').$this->formatCableSize($size);
    }

    private function professionalCableLength(mixed $value): string
    {
        if (! is_numeric($value) || (float) $value <= 0) return 'TBC';

        $decimals = floor((float) $value) === (float) $value ? 0 : 1;
        return $this->number((float) $value, $decimals).' m route';
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $node @return array{0:string,1:string,2:string,3:string} */
    private function professionalProtectionCells(array $row, array $node): array
    {
        $label = mb_strtolower(trim((string) ($row['label'] ?? '')));
        $configuration = trim((string) ($row['configuration'] ?? ''));
        $rawSystemVoltage = $row['system_voltage'] ?? null;
        $finalDuty = trim((string) ($row['final_duty'] ?? ''));
        $rating = $this->number($row['rating'] ?? null, is_numeric($row['rating'] ?? null) && floor((float) $row['rating']) !== (float) $row['rating'] ? 2 : 0);
        $unit = strtoupper(trim((string) ($row['unit'] ?? '')));
        $explicitRating = trim((string) ($row['rating_display'] ?? ''));
        $hasPositiveRating = is_numeric($row['rating'] ?? null) && (float) $row['rating'] > 0;
        $primaryRating = $explicitRating !== ''
            ? $explicitRating
            : ($hasPositiveRating && $rating !== '—' ? trim($rating.' '.$unit) : 'TBC');
        if (str_contains($label, 'dc spd')) {
            $designation = (string) ($row['designation'] ?? '');
            if (preg_match('/([0-9]{3,4})\s*Vdc/i', $designation, $match) === 1) {
                $primaryRating = 'Ucpv '.$match[1].' VDC';
            }
        }
        $isDc = str_contains($label, 'dc') || str_contains($unit, 'VDC');
        $systemVoltage = is_numeric($rawSystemVoltage)
            ? $this->number((float) $rawSystemVoltage, 0).' '.($isDc ? 'VDC' : 'VAC')
            : trim((string) $rawSystemVoltage);

        return [
            $configuration !== '' ? $configuration : 'CONFIGURATION TBC',
            $primaryRating,
            $systemVoltage !== '' ? $systemVoltage : 'SYSTEM V TBC',
            $finalDuty !== '' ? $finalDuty : 'FINAL DUTY TBC',
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function professionalProtectionTable(array $rows, float $x, float $y, float $w, array $nodeTags = [], array $nodeMap = []): string
    {
        $headerH = 32.0; $rowH = 22.5; $h = $headerH + (max(1, count($rows)) * $rowH);
        $widths = [$w * .13, $w * .18, $w * .19, $w * .10, $w * .10, $w * .30];
        $headers = ['DEVICE TAG', 'DEVICE', 'CONFIG.', 'RATING', 'SYSTEM V', 'FINAL DUTY'];
        $parts = ['<g><text x="'.$x.'" y="'.($y - 10).'" class="sld-foot-mono">PROTECTION SCHEDULE</text><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$headerH.'" fill="#e9f0f0"/>'];
        $cursor = $x;
        foreach ($headers as $index => $header) {
            if ($index > 0) $parts[] = '<path d="M'.$cursor.' '.$y.'V'.($y + $h).'" stroke="#56656d" stroke-width=".65"/>';
            $parts[] = '<text x="'.($cursor + ($widths[$index] / 2)).'" y="'.($y + 21).'" text-anchor="middle" class="sld-foot-label">'.$header.'</text>';
            $cursor += $widths[$index];
        }
        if ($rows === []) $rows = [['node_id' => '-', 'label' => '-', 'designation' => '-', 'rating' => '-', 'unit' => '']];
        foreach ($rows as $index => $row) {
            $rowY = $y + $headerH + ($index * $rowH);
            $parts[] = '<path d="M'.$x.' '.$rowY.'H'.($x + $w).'" stroke="#56656d" stroke-width=".55"/>';
            $nodeId = (string) ($row['node_id'] ?? '');
            $details = $this->professionalProtectionCells($row, is_array($nodeMap[$nodeId] ?? null) ? $nodeMap[$nodeId] : []);
            $values = array_merge([
                (string) ($row['display_tag'] ?? $nodeTags[$nodeId] ?? strtoupper($nodeId !== '' ? $nodeId : '-')),
                (string) ($row['label'] ?? '-'),
            ], $details);
            $cursor = $x;
            foreach ($values as $col => $value) {
                $limits = [18, 28, 32, 16, 18, 54];
                $parts[] = '<text x="'.($cursor + ($widths[$col] / 2)).'" y="'.($rowY + 15).'" text-anchor="middle" class="sld-wire-detail">'.$this->escape($this->shorten($value, $limits[$col] ?? 20)).'</text>';
                $cursor += $widths[$col];
            }
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    private function professionalScheduleLegend(float $x, float $y, float $w): string
    {
        $h = 390.0;
        $parts = ['<g><text x="'.$x.'" y="'.($y - 10).'" class="sld-foot-mono">DRAWING NOTES / LINE LEGEND</text><rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#fff" stroke="#0a1d26"/>'];
        $items = [
            ['DC / PV cable', '#b94141', '18 3 3 3'], ['AC cable', '#765022', ''], ['Protective earth', '#31825a', '7 5'],
            ['RS485 communication', '#7258a8', '7 5'], ['Control / measurement', '#5579b8', '9 4 2 4'],
        ];
        foreach ($items as $index => [$label, $color, $dash]) {
            $iy = $y + 34 + ($index * 34);
            $dashAttr = $dash !== '' ? ' stroke-dasharray="'.$dash.'"' : '';
            $parts[] = '<path d="M'.($x + 18).' '.$iy.'H'.($x + 78).'" stroke="'.$color.'" stroke-width="2.5"'.$dashAttr.'/><text x="'.($x + 90).'" y="'.($iy + 4).'" class="sld-foot-value">'.$label.'</text>';
        }
        $parts[] = '<path d="M'.($x + 212).' '.($y + 14).'V'.($y + $h - 14).'" stroke="#d2d9dc" stroke-width=".7"/>';
        $notes = [
            'Schedules use the linked calculation snapshot and controlled topology.',
            'TBC means final route, BOM, datasheet, protection duty, or site confirmation is pending.',
            'Automated rule checks do not replace engineering coordination or approval.',
            'No IEC certification claim is made. Independent conformity assessment and engineering approval remain pending.',
        ];
        $noteX = $x + 232;
        foreach ($notes as $index => $note) {
            foreach ($this->wrapWords(($index + 1).'. '.$note, 38, 4) as $lineIndex => $line) {
                $parts[] = '<text x="'.$noteX.'" y="'.($y + 36 + ($index * 68) + ($lineIndex * 13)).'" class="sld-note">'.$this->escape($line).'</text>';
            }
        }
        $parts[] = '<text x="'.$noteX.'" y="'.($y + $h - 22).'" class="sld-device-tag">67L.4-FINAL - A3 - NTS</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /** @param array<string, mixed> $sheet */
    private function professionalTitleBlock(array $graph, string $projectName, string $projectNumber, string $systemLabel, array $sheet): string
    {
        $sheetNumber = (int) ($sheet['number'] ?? 1);
        $sheetCount = (int) ($sheet['count'] ?? 1);
        $drawingTitle = strtoupper((string) ($sheet['title'] ?? 'Single-line diagram'));
        $document = is_array(data_get($graph, 'meta.document_control')) ? data_get($graph, 'meta.document_control') : [];
        $date = $this->date((string) data_get($document, 'generated.at', data_get($graph, 'meta.generated_at', '')));
        $controlDate = mb_substr($date, 0, 11);
        $revision = (string) data_get($document, 'drawing.revision_code', data_get($graph, 'meta.drawing_revision', 'R1'));
        $issueCode = (string) data_get($document, 'drawing.issue_code', data_get($graph, 'meta.issue_code', 'DRAFT'));
        $issueLabel = strtoupper((string) data_get($document, 'drawing.issue_label', 'DRAFT'));
        $prefix = (string) data_get($document, 'drawing.number_prefix', data_get($graph, 'meta.drawing_number_prefix', $projectNumber.'-E-SLD'));
        $registerNumber = (string) data_get($document, 'drawing.register.'.($sheetNumber - 1).'.drawing_number', '');
        $drawingNumber = $registerNumber !== '' ? $registerNumber : $prefix.'-'.str_pad((string) $sheetNumber, 3, '0', STR_PAD_LEFT);
        $client = (string) data_get($document, 'project.client', 'Client TBC');
        $site = (string) data_get($document, 'project.site_location', 'Site TBC');
        $organization = (string) data_get($document, 'organization.name', 'NEXORIN Engineering Platform');
        $preparedBy = (string) data_get($document, 'people.prepared_by', 'NX');
        $checkedBy = (string) data_get($document, 'people.checked_by', '-');
        $approvedBy = (string) data_get($document, 'people.approved_by', '-');
        $notForConstruction = (bool) data_get($document, 'release.not_for_construction', true);
        $check = strtoupper((string) data_get($graph, 'electrical_validation.summary.status', 'pending'));
        $errors = (int) data_get($graph, 'electrical_validation.summary.error', 0);
        $warnings = (int) data_get($graph, 'electrical_validation.summary.warning', 0);
        $top = 985.0;
        $bottom = 1154.0;

        $parts = ['<g aria-label="Production controlled title block">'];
        $parts[] = '<rect x="34" y="'.$top.'" width="1612" height="'.($bottom - $top).'" fill="#fff" stroke="#0a1d26" stroke-width="1.2"/>';
        $parts[] = '<path d="M430 '.$top.'V'.$bottom.'M670 '.$top.'V'.$bottom.'M1150 '.$top.'V'.$bottom.'M1460 '.$top.'V'.$bottom.'" stroke="#0a1d26" stroke-width=".85"/>';

        $parts[] = '<text x="48" y="1004" class="sld-tb-label">REVISION / RELEASE</text>';
        $parts[] = '<path d="M34 1014H430M34 1044H430M34 1074H430M78 1014V1074M166 1014V1074M348 1014V1074M394 1014V1074" stroke="#56656d" stroke-width=".65"/>';
        foreach ([['NO',56],['DATE',122],['DESCRIPTION',257],['BY',371],['STATUS',412]] as [$label,$x]) {
            $parts[] = '<text x="'.$x.'" y="1033" text-anchor="middle" class="sld-tb-label">'.$label.'</text>';
        }
        $parts[] = '<text x="56" y="1064" text-anchor="middle" class="sld-tb-value">'.$this->escape((string) data_get($document, 'drawing.revision_number', 1)).'</text>';
        $parts[] = '<text x="122" y="1064" text-anchor="middle" class="sld-tb-value">'.$this->escape($controlDate).'</text>';
        $parts[] = '<text x="257" y="1064" text-anchor="middle" class="sld-tb-value">'.$this->escape($this->shorten($revision.' '.$issueLabel, 24)).'</text>';
        $parts[] = '<text x="371" y="1064" text-anchor="middle" class="sld-tb-value">'.$this->escape($this->shorten($preparedBy ?: 'NX', 10)).'</text>';
        $parts[] = '<text x="412" y="1064" text-anchor="middle" class="sld-tb-value">'.$this->escape($issueCode).'</text>';
        $parts[] = '<text x="48" y="1097" class="sld-tb-label">CONFORMITY / RELEASE</text>';
        $parts[] = '<text x="48" y="1119" class="sld-tb-value">CERTIFICATION TARGET · ASSESSMENT PENDING</text>';
        $parts[] = '<text x="48" y="1141" class="sld-tb-alert">'.($notForConstruction ? 'NOT FOR CONSTRUCTION' : $this->escape($issueLabel)).'</text>';

        $parts[] = '<text x="444" y="1004" class="sld-tb-label">DRAWING CONTROL</text>';
        $parts[] = '<path d="M430 1014H670M430 1060H670M430 1106H670M510 1014V1154M590 1014V1154" stroke="#56656d" stroke-width=".65"/>';
        foreach ([['DRAWN',$preparedBy ?: 'NX',$controlDate],['CHECKED',$checkedBy ?: '-',$checkedBy ? 'RECORDED' : 'PENDING'],['APPROVED',$approvedBy ?: '-',$approvedBy ? 'RECORDED' : 'PENDING']] as $index => [$role,$by,$when]) {
            $rowY = 1044 + ($index * 46);
            $parts[] = '<text x="470" y="'.$rowY.'" text-anchor="middle" class="sld-tb-label">'.$role.'</text>';
            $parts[] = '<text x="550" y="'.$rowY.'" text-anchor="middle" class="sld-tb-value">'.$this->escape($this->shorten($by, 12)).'</text>';
            $parts[] = '<text x="630" y="'.$rowY.'" text-anchor="middle" class="sld-tb-value">'.$this->escape($this->shorten($when, 12)).'</text>';
        }

        $parts[] = '<text x="686" y="1004" class="sld-tb-label">ENGINEERING ORGANIZATION</text>';
        $parts[] = '<text x="686" y="1034" class="sld-brand" style="fill:#0d2c3e;font-size:19px">'.$this->escape(strtoupper($this->shorten($organization, 32))).'</text>';
        $parts[] = '<text x="686" y="1056" class="sld-brand-sub" style="fill:#0b817b;font-size:9px">SMART PV ENGINEERING · CONTROLLED DRAWING</text>';
        $parts[] = '<path d="M670 1069H1150" stroke="#d2d9dc" stroke-width=".7"/>';
        $parts[] = '<text x="686" y="1091" class="sld-tb-label">CLIENT / SITE</text>';
        $parts[] = '<text x="686" y="1113" class="sld-tb-value">'.$this->escape($this->shorten($client.' · '.$site, 68)).'</text>';
        $parts[] = '<text x="686" y="1138" class="sld-tb-label">RULE CHECK</text>';
        $parts[] = '<text x="785" y="1138" class="sld-tb-value">'.$this->escape($check).' · '.$errors.'E / '.$warnings.'W · GAPS OPEN</text>';

        $parts[] = '<text x="1165" y="1004" class="sld-tb-label">PROJECT</text>';
        foreach ($this->wrapWords($projectName, 35, 2) as $index => $line) {
            $parts[] = '<text x="1165" y="'.(1025 + ($index * 15)).'" class="sld-tb-value">'.$this->escape($line).'</text>';
        }
        $parts[] = '<text x="1165" y="1065" class="sld-tb-label">DRAWING</text>';
        foreach ($this->wrapWords($drawingTitle, 35, 2) as $index => $line) {
            $parts[] = '<text x="1165" y="'.(1086 + ($index * 15)).'" class="sld-tb-value">'.$this->escape($line).'</text>';
        }
        $parts[] = '<text x="1165" y="1127" class="sld-tb-label">NUMBER</text>';
        $parts[] = '<text x="1165" y="1147" class="sld-tb-value">'.$this->escape($drawingNumber).'</text>';

        $parts[] = '<text x="1475" y="1004" class="sld-tb-label">SYSTEM</text>';
        foreach ($this->wrapWords(strtoupper($systemLabel), 18, 2) as $index => $line) {
            $parts[] = '<text x="1475" y="'.(1025 + ($index * 15)).'" class="sld-tb-value">'.$this->escape($line).'</text>';
        }
        foreach ([
            ['REVISION', $revision, 1070],
            ['ISSUE', $issueCode, 1097],
            ['SCALE / SIZE', 'NTS / A3', 1124],
            ['SHEET', $sheetNumber.' / '.$sheetCount, 1148],
        ] as [$label, $value, $rowY]) {
            $parts[] = '<text x="1475" y="'.$rowY.'" class="sld-tb-label">'.$label.'</text>';
            $parts[] = '<text x="1630" y="'.$rowY.'" text-anchor="end" class="sld-tb-value">'.$this->escape((string) $value).'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<int, array<string, mixed>> $shapes
     * @param array<string, float>|null $bounds
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function geometryForSheet(array $lines, array $shapes, ?array $bounds, string $sheetType): array
    {
        if ($sheetType === 'catalog' || $sheetType === 'overview') {
            return [[], []];
        }
        if (! $bounds) {
            return [$lines, $shapes];
        }
        $inside = static fn (float $x, float $y): bool => $x >= $bounds['left'] && $x <= $bounds['right'] && $y >= $bounds['top'] && $y <= $bounds['bottom'];
        $lines = array_values(array_filter($lines, static function (mixed $line) use ($inside): bool {
            return is_array($line)
                && ($inside((float) ($line['x1'] ?? 0), (float) ($line['y1'] ?? 0))
                    || $inside((float) ($line['x2'] ?? 0), (float) ($line['y2'] ?? 0)));
        }));
        $shapes = array_values(array_filter($shapes, static function (mixed $shape) use ($inside): bool {
            if (! is_array($shape)) return false;
            $x = (float) ($shape['x'] ?? 0);
            $y = (float) ($shape['y'] ?? 0);
            $w = (float) ($shape['w'] ?? 0);
            $h = (float) ($shape['h'] ?? 0);
            return $inside($x, $y) || $inside($x + $w, $y + $h);
        }));
        return [$lines, $shapes];
    }

    private function definitions(): string
    {
        return <<<'SVG'
<defs>
    <pattern id="sld-grid" width="20" height="20" patternUnits="userSpaceOnUse">
        <path d="M20 0H0V20" fill="none" stroke="#edf2f2" stroke-width="1"/>
    </pattern>
    <marker id="sld-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
        <path d="M0 0 10 5 0 10z" fill="#14384d"/>
    </marker>
    <marker id="sld-leader-arrow" viewBox="0 0 10 10" refX="1" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
        <path d="M10 0 0 5 10 10z" fill="#294b5d"/>
    </marker>
    <marker id="sld-power-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto">
        <path d="M0 0 10 5 0 10z" fill="#14384d"/>
    </marker>
    <style>
        .sld-brand{font:800 23px Inter,Arial,sans-serif;letter-spacing:.18em;fill:#fff}
        .sld-brand-sub{font:700 8px Inter,Arial,sans-serif;letter-spacing:.13em;fill:#9fd9d4}
        .sld-sheet-title{font:800 22px Inter,Arial,sans-serif;letter-spacing:.055em;fill:#fff}
        .sld-sheet-sub{font:500 11px Inter,Arial,sans-serif;fill:#bfd0d7}
        .sld-status{font:800 9px Inter,Arial,sans-serif;letter-spacing:.08em;fill:#fff}
        .sld-section-label,.sld-table-label,.sld-foot-label{font:800 9.4px Inter,Arial,sans-serif;letter-spacing:.11em;fill:#4f6875}
        .sld-table-value{font:700 15px 'JetBrains Mono',Consolas,monospace;fill:#102f43}
        .sld-table-unit{font:700 8px Inter,Arial,sans-serif;fill:#6e8490}
        .sld-device-title{font:800 13px Inter,Arial,sans-serif;letter-spacing:.005em;fill:#102f43}
        .sld-device-tag{font:800 10.4px 'JetBrains Mono',Consolas,monospace;letter-spacing:.06em;fill:#0b817b}
        .sld-device-rating{font:700 9.6px 'JetBrains Mono',Consolas,monospace;fill:#2b4e60}
        .sld-device-sub{font:600 9.2px Inter,Arial,sans-serif;fill:#667d88}
        .sld-symbol{fill:none;stroke:#12374b;stroke-width:2.35;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}
        .sld-symbol-thin{fill:none;stroke:#12374b;stroke-width:1.55;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}
        .sld-symbol-accent{fill:none;stroke:#12374b;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}
        .sld-connection{fill:none;stroke:#14384d;stroke-width:2.65;stroke-linecap:square;stroke-linejoin:miter;vector-effect:non-scaling-stroke}
        .sld-connection.dc-power,.sld-connection.dc-positive,.sld-connection.battery-dc,.sld-connection.battery-positive{stroke:#b94141;stroke-width:2.65;stroke-dasharray:18 3 3 3}
        .sld-connection.dc-negative,.sld-connection.battery-negative{stroke:#4c5960}
        .sld-connection.ac-power{stroke:#765022;stroke-width:3.55}
        .sld-connection.neutral{stroke:#377ead}
        .sld-connection.communication{stroke:#7258a8;stroke-width:1.65;stroke-dasharray:8 5}
        .sld-connection.protective-earth{stroke:#31825a;stroke-width:1.85;stroke-dasharray:5 4}
        .sld-connection.control{stroke:#5579b8;stroke-width:1.35;stroke-dasharray:10 4 2 4}
        .sld-connection.measurement{stroke:#438b90;stroke-width:1.25;stroke-dasharray:3 3}
        .sld-connection.unclassified.manual{stroke:#9b6f3b;stroke-dasharray:9 6}
        .sld-drafting-line{fill:none;stroke:#526f7d;stroke-width:1.5;stroke-linecap:square;stroke-linejoin:miter}
        .sld-drafting-line.leader{stroke:#294b5d}
        .sld-drafting-line.hidden{stroke-dasharray:6 4}
        .sld-drafting-line.center{stroke-dasharray:10 4 2 4}
        .sld-drafting-shape{fill:none;stroke:#526f7d;stroke-width:1.5;stroke-linecap:square;stroke-linejoin:miter}
        .sld-drafting-shape.hidden{stroke-dasharray:6 4}
        .sld-drafting-shape.center{stroke-dasharray:10 4 2 4}
        .sld-line-label{font:700 7px 'JetBrains Mono',Consolas,monospace;fill:#526f7d}
        .sld-wire-label{font:800 8.5px Inter,Arial,sans-serif;letter-spacing:.04em;fill:#0b817b}
        .sld-wire-detail{font:650 10.6px 'JetBrains Mono',Consolas,monospace;fill:#506a76}
        .sld-note{font:600 10.8px Inter,Arial,sans-serif;fill:#294b5d}
        .sld-annotation rect{fill:#fff;fill-opacity:.96;stroke:#6c8994;stroke-width:1}
        .sld-annotation path{fill:none;stroke:#119d95;stroke-width:2}
        .sld-foot-value{font:650 10.6px Inter,Arial,sans-serif;fill:#294b5d}
        .sld-foot-mono{font:700 10.0px 'JetBrains Mono',Consolas,monospace;fill:#102f43}
        .sld-string-tag{font:800 10.8px 'JetBrains Mono',Consolas,monospace;fill:#102f43}
        .sld-string-data{font:650 10.0px 'JetBrains Mono',Consolas,monospace;fill:#3e5864}
        .sld-terminal-label{font:800 10.4px 'JetBrains Mono',Consolas,monospace;fill:#0b817b}
        .sld-flow-label{font:800 9.3px Inter,Arial,sans-serif;letter-spacing:.08em;fill:#4a5d66}
        .sld-legend-text{font:700 9.1px Inter,Arial,sans-serif;fill:#17384a}
        .sld-legend-note{font:600 8.6px Inter,Arial,sans-serif;fill:#526a76}
        .sld-junction{fill:#102f43;stroke:#fff;stroke-width:1.2}
        .sld-label-halo{paint-order:stroke;stroke:#fff;stroke-width:4.2;stroke-linejoin:round}
        .sld-label-box{fill:#fff;fill-opacity:.98;stroke:none}
        .sld-label-box-border{stroke:#d2d9dc;stroke-width:.8}
        .sld-auto-label-box{fill:#fff;fill-opacity:.985;stroke:#9fb1b8;stroke-width:.85}
        .sld-auto-device-label.is-fallback .sld-auto-label-box{stroke:#c5802f;stroke-dasharray:4 3}
        .sld-auto-label-leader{fill:none;stroke:#526f7d;stroke-width:1;stroke-linecap:square;vector-effect:non-scaling-stroke}
        .sld-paper-port{fill:#fff;stroke:#12374b;stroke-width:1.7;vector-effect:non-scaling-stroke}
        .sld-paper-port.power{stroke:#765022}
        .sld-paper-port.earth{stroke:#31825a}
        .sld-paper-port.communication{stroke:#7258a8}
        .sld-paper-port.control{stroke:#5579b8}
        .sld-paper-port.measurement{stroke:#438b90}
        .sld-tb-label{font:800 9.8px Inter,Arial,sans-serif;letter-spacing:.08em;fill:#4f6875}
        .sld-tb-value{font:700 10.7px 'JetBrains Mono',Consolas,monospace;fill:#102f43}
        .sld-tb-alert{font:800 11px Inter,Arial,sans-serif;letter-spacing:.08em;fill:#9c3d35}
        .sld-device{cursor:pointer}
        .sld-device .sld-focus-ring{opacity:0}
        .sld-device.is-selected .sld-focus-ring{opacity:1}
        .sld-hit{fill:#fff;fill-opacity:.001}
    </style>
</defs>
SVG;
    }

    /** @param array<string, mixed> $sheet */
    private function header(string $projectName, string $projectNumber, string $systemLabel, bool $verified, array $sheet): string
    {
        $status = $verified ? 'VERIFIED CALCULATION SOURCE' : 'ENGINEERING DRAFT';
        $tone = $verified ? '#119d95' : '#d58b32';
        $sheetTitle = $this->escape((string) ($sheet['title'] ?? 'Single-line diagram'));
        $sheetNumber = (int) ($sheet['number'] ?? 1);
        $sheetCount = (int) ($sheet['count'] ?? 1);

        return '<g aria-label="Drawing header">'
            .'<rect x="30" y="30" width="1620" height="82" fill="#0d2c3e"/>'
            .'<rect x="30" y="106" width="1620" height="6" fill="#119d95"/>'
            .'<text x="58" y="64" class="sld-brand">NEXORIN</text>'
            .'<text x="58" y="84" class="sld-brand-sub">SOLAR ENGINEERING · CONTROLLED DRAWING</text>'
            .'<path d="M274 45V96" stroke="#426274"/>'
            .'<text x="304" y="63" class="sld-sheet-title">'.$sheetTitle.'</text>'
            .'<text x="304" y="87" class="sld-sheet-sub">'.$projectName.' · '.$systemLabel.' · '.$projectNumber.' · SHEET '.$sheetNumber.' / '.$sheetCount.'</text>'
            .'<rect x="1372" y="49" width="236" height="42" rx="5" fill="'.$tone.'"/>'
            .'<circle cx="1393" cy="70" r="5" fill="#fff"/>'
            .'<text x="1409" y="74" class="sld-status">'.$status.'</text>'
            .'</g>';
    }

    private function designData(array $summary): string
    {
        $items = [
            ['PV CAPACITY', $this->number($summary['installed_pv_kw'] ?? null, 2), 'kWp'],
            ['ARRAY LAYOUT', $this->shorten((string) ($summary['string_layout'] ?? 'Pending'), 24), ''],
            ['INVERTER AC', $this->number($summary['inverter_ac_kw'] ?? null, 2), 'kW'],
            ['DC / AC', $this->number($summary['dc_ac_ratio'] ?? null, 3), 'ratio'],
            ['SUPPLY', strtoupper(str_replace('_', ' ', (string) ($summary['supply_phase'] ?? 'Utility'))), ''],
        ];
        $x = 66;
        $width = 309.6;
        $parts = ['<g aria-label="Design basis summary"><rect x="66" y="132" width="1548" height="62" fill="#f8fbfb" stroke="#c7d5d8"/>'];
        foreach ($items as $index => [$label, $value, $unit]) {
            $left = $x + ($index * $width);
            if ($index > 0) {
                $parts[] = '<path d="M'.$left.' 132V194" stroke="#d6e0e2"/>';
            }
            $parts[] = '<rect x="'.$left.'" y="132" width="4" height="62" fill="'.($index === 4 ? '#d58b32' : '#119d95').'"/>';
            $parts[] = '<text x="'.($left + 18).'" y="153" class="sld-table-label">'.$this->escape($label).'</text>';
            $parts[] = '<text x="'.($left + 18).'" y="179" class="sld-table-value">'.$this->escape((string) $value).'</text>';
            if ($unit !== '') {
                $parts[] = '<text x="'.($left + 235).'" y="179" class="sld-table-unit">'.$this->escape($unit).'</text>';
            }
        }
        $parts[] = '</g>';

        return implode('', $parts);
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @param array<string, array<string, mixed>> $stored
     * @param array<int, array<string, mixed>> $manualLines
     * @param array<int, array<string, mixed>> $manualShapes
     * @return array{positions: array<string, array{x: float, y: float}>, transform: array{scale: float, offset_x: float, offset_y: float}}
     */
    private function paperLayout(array $nodes, array $stored, array $manualLines, array $manualShapes): array
    {
        // These defaults mirror Model Space. Stored x/y are top-left geometry,
        // not paper-space centres. This keeps saved composition predictable.
        $defaults = [
            'pv_array' => ['x' => 110.0, 'y' => 320.0, 'w' => 144.0, 'h' => 120.0],
            'dc_protection' => ['x' => 355.0, 'y' => 320.0, 'w' => 144.0, 'h' => 120.0],
            'inverter' => ['x' => 600.0, 'y' => 320.0, 'w' => 144.0, 'h' => 120.0],
            'ac_protection' => ['x' => 845.0, 'y' => 320.0, 'w' => 144.0, 'h' => 120.0],
            'meter' => ['x' => 1090.0, 'y' => 320.0, 'w' => 144.0, 'h' => 120.0],
            'grid' => ['x' => 1335.0, 'y' => 320.0, 'w' => 144.0, 'h' => 120.0],
            'battery' => ['x' => 600.0, 'y' => 610.0, 'w' => 144.0, 'h' => 120.0],
            'backup_panel' => ['x' => 900.0, 'y' => 610.0, 'w' => 144.0, 'h' => 120.0],
        ];

        $rects = [];
        $manualIndex = 0;
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
            if (isset($defaults[$id])) {
                $rects[$id] = $defaults[$id];
                continue;
            }
            $rects[$id] = [
                'x' => 120.0 + (($manualIndex % 5) * 245.0),
                'y' => 600.0 + (intdiv($manualIndex, 5) * 190.0),
                'w' => 144.0,
                'h' => 120.0,
            ];
            $manualIndex++;
        }

        $left = INF;
        $top = INF;
        $right = -INF;
        $bottom = -INF;
        foreach ($rects as $rect) {
            $left = min($left, $rect['x']);
            $top = min($top, $rect['y']);
            $right = max($right, $rect['x'] + $rect['w']);
            $bottom = max($bottom, $rect['y'] + $rect['h']);
        }
        foreach ($manualLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            foreach ([['x1', 'y1'], ['x2', 'y2']] as [$xKey, $yKey]) {
                if (! is_numeric($line[$xKey] ?? null) || ! is_numeric($line[$yKey] ?? null)) {
                    continue;
                }
                $x = (float) $line[$xKey];
                $y = (float) $line[$yKey];
                $left = min($left, $x);
                $top = min($top, $y);
                $right = max($right, $x);
                $bottom = max($bottom, $y);
            }
        }
        foreach ($manualShapes as $shape) {
            if (! is_array($shape) || ! is_numeric($shape['x'] ?? null) || ! is_numeric($shape['y'] ?? null)) {
                continue;
            }
            $x = (float) $shape['x'];
            $y = (float) $shape['y'];
            $w = max(4.0, (float) ($shape['w'] ?? 4));
            $h = max(4.0, (float) ($shape['h'] ?? $w));
            $left = min($left, $x);
            $top = min($top, $y);
            $right = max($right, $x + $w);
            $bottom = max($bottom, $y + $h);
        }

        if (! is_finite($left) || ! is_finite($top) || ! is_finite($right) || ! is_finite($bottom)) {
            $left = 0.0;
            $top = 0.0;
            $right = 144.0;
            $bottom = 120.0;
        }

        // Paper topology safe area. Composition is only reduced when needed.
        // It is never stretched to the sheet edges, which previously destroyed
        // the spacing users created in Model Space.
        $paperLeft = 120.0;
        $paperTop = 280.0;
        $paperRight = 1560.0;
        $paperBottom = 742.0;
        $contentWidth = max($right - $left, 1.0);
        $contentHeight = max($bottom - $top, 1.0);
        $scale = min(
            ($paperRight - $paperLeft) / $contentWidth,
            ($paperBottom - $paperTop) / $contentHeight,
            1.0,
        );
        $contentCenterX = ($left + $right) / 2;
        $contentCenterY = ($top + $bottom) / 2;
        $paperCenterX = ($paperLeft + $paperRight) / 2;
        $paperCenterY = ($paperTop + $paperBottom) / 2;
        $transform = [
            'scale' => $scale,
            'offset_x' => $paperCenterX - ($contentCenterX * $scale),
            'offset_y' => $paperCenterY - ($contentCenterY * $scale),
        ];

        $positions = [];
        foreach ($rects as $id => $rect) {
            $center = $this->mapPaperPoint(
                $rect['x'] + ($rect['w'] / 2),
                $rect['y'] + ($rect['h'] / 2),
                $transform,
            );
            $positions[$id] = $center;
        }

        return ['positions' => $positions, 'transform' => $transform];
    }

    /** @param array{scale: float, offset_x: float, offset_y: float} $transform */
    private function mapPaperPoint(float $x, float $y, array $transform): array
    {
        return [
            'x' => ($x * $transform['scale']) + $transform['offset_x'],
            'y' => ($y * $transform['scale']) + $transform['offset_y'],
        ];
    }

    /** @return array{x:float,y:float} */
    private function paperPort(array $position, array $node, string $portId, bool $source): array
    {
        $x = (float) ($position['x'] ?? 0);
        $y = (float) ($position['y'] ?? 0);
        $w = max(44.0, (float) ($position['w'] ?? 144.0));
        $h = max(40.0, (float) ($position['h'] ?? 120.0));
        $ports = is_array($node['ports'] ?? null) ? $node['ports'] : [];
        $selected = null;
        foreach ($ports as $port) {
            if (is_array($port) && (string) ($port['id'] ?? '') === $portId) {
                $selected = $port;
                break;
            }
        }
        $side = (string) ($selected['side'] ?? '');
        $anchor = is_array($selected['anchor'] ?? null) ? $selected['anchor'] : null;
        if ($anchor !== null && is_numeric($anchor['x'] ?? null) && is_numeric($anchor['y'] ?? null)) {
            $nx = max(0.0, min(1.0, (float) $anchor['x']));
            $ny = max(0.0, min(1.0, (float) $anchor['y']));
        } else {
            if ($side === '') {
                $upper = strtoupper($portId);
                if (str_contains($upper, 'PE') || str_contains($upper, 'COM') || str_contains($upper, 'CTRL') || str_contains($upper, 'SECONDARY')) $side = 'bottom';
                elseif ($source) $side = 'right';
                else $side = 'left';
            }
            $sameSide = array_values(array_filter($ports, static fn (mixed $port): bool => is_array($port) && (string) ($port['side'] ?? '') === $side));
            $index = 0;
            foreach ($sameSide as $portIndex => $port) {
                if ((string) ($port['id'] ?? '') === $portId) { $index = $portIndex; break; }
            }
            $fraction = count($sameSide) > 0 ? ($index + 1) / (count($sameSide) + 1) : 0.5;
            $nx = $side === 'left' ? 0.245 : ($side === 'right' ? 0.755 : $fraction);
            $ny = $side === 'top' ? 0.105 : ($side === 'bottom' ? 0.665 : $fraction);
        }
        if ($side === '') $side = $source ? 'right' : 'left';
        if (($node['flip_h'] ?? false) === true) { $nx = 1.0 - $nx; $side = match ($side) { 'left' => 'right', 'right' => 'left', default => $side }; }
        if (($node['flip_v'] ?? false) === true) { $ny = 1.0 - $ny; $side = match ($side) { 'top' => 'bottom', 'bottom' => 'top', default => $side }; }
        $rotation = $this->quarterTurn($node['rotation'] ?? 0);
        [$nx, $ny] = match ($rotation) {
            90 => [1.0 - $ny, $nx],
            180 => [1.0 - $nx, 1.0 - $ny],
            270 => [$ny, 1.0 - $nx],
            default => [$nx, $ny],
        };
        $side = match ($rotation) {
            90 => match ($side) { 'left' => 'top', 'top' => 'right', 'right' => 'bottom', 'bottom' => 'left', default => $side },
            180 => match ($side) { 'left' => 'right', 'right' => 'left', 'top' => 'bottom', 'bottom' => 'top', default => $side },
            270 => match ($side) { 'left' => 'bottom', 'bottom' => 'right', 'right' => 'top', 'top' => 'left', default => $side },
            default => $side,
        };
        return ['x' => $x - ($w / 2) + ($nx * $w), 'y' => $y - ($h / 2) + ($ny * $h), 'side' => $side];
    }

    /** @param array<int,array{x:float|int,y:float|int}> $points */
    private function routeIsOrthogonal(array $points): bool
    {
        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index];
            $b = $points[$index + 1];
            if (abs((float) $a['x'] - (float) $b['x']) > 0.001 && abs((float) $a['y'] - (float) $b['y']) > 0.001) {
                return false;
            }
        }

        return count($points) >= 2;
    }

    private function connection(
        array $connection,
        array $source,
        array $target,
        ?array $storedRoute,
        array $transform,
        array $sourceNode = [],
        array $targetNode = [],
        array $routingObstacles = [],
        array &$routedSegments = [],
    ): string {
        $sourcePort = $this->paperPort($source, $sourceNode, (string) ($connection['from_port'] ?? ''), true);
        $targetPort = $this->paperPort($target, $targetNode, (string) ($connection['to_port'] ?? ''), false);
        $startX = $sourcePort['x'];
        $sy = $sourcePort['y'];
        $endX = $targetPort['x'];
        $ty = $targetPort['y'];
        $manual = ($connection['_manual'] ?? false) === true;
        $routingMode = (string) ($connection['routing_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
        $clearanceProfile = in_array((string) ($connection['routing_clearance'] ?? ''), ['compact', 'standard', 'generous'], true)
            ? (string) $connection['routing_clearance']
            : 'standard';
        [$clearance, $channelSpacing] = match ($clearanceProfile) {
            'compact' => [12.0, 9.0],
            'generous' => [26.0, 16.0],
            default => [18.0, 12.0],
        };
        $scale = max(.1, (float) ($transform['scale'] ?? 1.0));
        $paperClearance = max(8.0, $clearance * $scale);
        $paperSpacing = max(7.0, $channelSpacing * $scale);
        $network = strtolower((string) ($connection['network'] ?? 'unclassified'));
        $points = $storedRoute ? $this->paperRoute($storedRoute, $transform) : [];
        $pathPoints = $this->normalizePaperRoute(array_merge(
            [['x' => $startX, 'y' => $sy]],
            $points,
            [['x' => $endX, 'y' => $ty]],
        ));
        $sourceId = (string) ($connection['from'] ?? '');
        $targetId = (string) ($connection['to'] ?? '');
        $autoRouteRequired = $routingMode !== 'manual' && (
            $points === []
            || (string) ($connection['router_version'] ?? '') !== SldSmartRouter::VERSION
            || (string) ($connection['route_contract'] ?? '') !== SldSmartRouter::CONTRACT
            || $this->routeIsStale($pathPoints)
            || $this->paperRouteBlocked($pathPoints, $routingObstacles, [$sourceId, $targetId], $paperClearance)
        );
        $routeStrategy = $routingMode === 'manual' ? 'manual-preserved' : 'saved-obstacle-free';
        $routeMetrics = [
            'contract' => (string) ($connection['route_contract'] ?? SldSmartRouter::CONTRACT),
            'signature' => (string) ($connection['route_signature'] ?? ''),
            'network_class' => (string) ($connection['route_network_class'] ?? SldSmartRouter::networkClass($network)),
            'orthogonal' => (bool) ($connection['route_orthogonal'] ?? $this->routeIsOrthogonal($pathPoints)),
            'terminal_direction_ok' => (bool) ($connection['route_terminal_direction_ok'] ?? ($routingMode === 'manual')),
            'crossings' => (int) ($connection['route_crossings'] ?? 0),
            'clearance_conflicts' => (int) ($connection['route_clearance_conflicts'] ?? 0),
            'separation_conflicts' => (int) ($connection['route_separation_conflicts'] ?? 0),
            'bends' => (int) ($connection['route_bends'] ?? max(0, count($pathPoints) - 2)),
            'length' => (float) ($connection['route_length'] ?? 0),
        ];
        if ($autoRouteRequired) {
            $result = $this->smartRouter->route(
                ['x' => $startX, 'y' => $sy, 'side' => (string) ($sourcePort['side'] ?? 'right'), 'id' => $sourceId],
                ['x' => $endX, 'y' => $ty, 'side' => (string) ($targetPort['side'] ?? 'left'), 'id' => $targetId],
                $routingObstacles,
                $routedSegments,
                $network,
                $paperClearance,
                $paperSpacing,
                max(4.0, 10.0 * $scale),
            );
            $pathPoints = $this->normalizePaperRoute($result['points']);
            $routeStrategy = (string) ($result['strategy'] ?? SldSmartRouter::ENGINE);
            $routeMetrics = $result;
        } elseif ($pathPoints === []) {
            $pathPoints = [['x' => $startX, 'y' => $sy], ['x' => $endX, 'y' => $ty]];
        }
        foreach ($this->paperSegments($pathPoints) as $segment) {
            $routedSegments[] = [
                'a' => $segment['a'], 'b' => $segment['b'],
                'network' => $network,
                'wireId' => (string) ($connection['_id'] ?? $connection['id'] ?? ''),
            ];
        }
        $path = 'M'.$this->f($pathPoints[0]['x']).' '.$this->f($pathPoints[0]['y']);
        foreach (array_slice($pathPoints, 1) as $point) $path .= 'L'.$this->f($point['x']).' '.$this->f($point['y']);

        $networkClassValue = str_replace('_', '-', $network);
        $allowedNetworks = ['dc-power', 'dc-positive', 'dc-negative', 'battery-dc', 'battery-positive', 'battery-negative', 'ac-power', 'neutral', 'communication', 'protective-earth', 'control', 'measurement'];
        $networkClass = in_array($networkClassValue, $allowedNetworks, true) ? $networkClassValue : 'unclassified';
        $class = 'sld-connection '.$networkClass.($manual ? ' manual' : '');
        $wire = '<g class="sld-wire"'
            .' data-router="'.SldSmartRouter::VERSION.'"'
            .' data-route-contract="'.$this->escape((string) ($routeMetrics['contract'] ?? SldSmartRouter::CONTRACT)).'"'
            .' data-route-strategy="'.$this->escape($routeStrategy).'"'
            .' data-route-signature="'.$this->escape((string) ($routeMetrics['signature'] ?? '')).'"'
            .' data-route-network-class="'.$this->escape((string) ($routeMetrics['network_class'] ?? SldSmartRouter::networkClass($network))).'"'
            .' data-route-orthogonal="'.(($routeMetrics['orthogonal'] ?? false) ? 'true' : 'false').'"'
            .' data-route-terminal-direction-ok="'.(($routeMetrics['terminal_direction_ok'] ?? false) ? 'true' : 'false').'"'
            .' data-route-crossings="'.(int) ($routeMetrics['crossings'] ?? 0).'"'
            .' data-route-clearance-conflicts="'.(int) ($routeMetrics['clearance_conflicts'] ?? 0).'"'
            .' data-route-separation-conflicts="'.(int) ($routeMetrics['separation_conflicts'] ?? 0).'"'
            .'><path d="'.$path.'" class="'.$class.'"/>';

        $cableTag = trim((string) ($connection['cable_tag'] ?? ''));
        $customLabel = trim((string) ($connection['label'] ?? ''));
        $wireId = trim((string) ($connection['wire_id'] ?? ''));
        $cableType = trim((string) ($connection['cable_type'] ?? ''));
        $cableSize = trim((string) ($connection['cable_size'] ?? ''));
        $voltageClass = trim((string) ($connection['voltage_class'] ?? ''));
        $installation = trim((string) ($connection['installation_method'] ?? ''));
        $cores = filled($connection['cores'] ?? null) ? (int) $connection['cores'] : null;

        $detailParts = [];
        if ($this->meaningfulEngineeringText($cableType)) $detailParts[] = $cableType;
        if ($this->meaningfulEngineeringText($cableSize)) {
            $detailParts[] = $this->formatCableSize($cableSize);
        }
        if ($cores !== null && $cores > 0) $detailParts[] = $cores.'C';
        if ($this->meaningfulEngineeringText($voltageClass)) $detailParts[] = $voltageClass;
        if ($this->meaningfulEngineeringText($installation) && mb_strtolower($installation) !== 'not specified') $detailParts[] = $installation;

        $hasCableData = $detailParts !== [] || $this->meaningfulEngineeringText($cableTag);
        $labelValue = $this->meaningfulEngineeringText($cableTag)
            ? $cableTag
            : ($this->meaningfulEngineeringText($customLabel)
                ? $customLabel
                : ($hasCableData && $this->meaningfulEngineeringText($wireId) ? $wireId : ''));
        $detailValue = implode(' · ', $detailParts);
        $showLabel = ($connection['show_label'] ?? true) !== false && ($labelValue !== '' || $detailValue !== '');

        if ($showLabel) {
            $label = $this->shorten($labelValue, 30);
            $detail = $this->shorten($detailValue, 46);
            $contentWidth = max(
                $label !== '' ? mb_strlen($label) * 5.7 : 0,
                $detail !== '' ? mb_strlen($detail) * 4.45 : 0,
            );
            $labelWidth = max(76.0, min(250.0, $contentWidth + 12.0));
            $labelHeight = $detail !== '' ? 27.0 : 15.0;
            $placement = $this->placeWireLabel($pathPoints, $labelWidth, $labelHeight);
            $labelX = $placement['x'] + ($labelWidth / 2);
            $labelY = $placement['y'] + 10.0;
            $wire .= '<g class="sld-wire-callout" pointer-events="none">';
            if ($label !== '') {
                $wire .= '<text x="'.$this->f($labelX).'" y="'.$this->f($labelY).'" text-anchor="middle" class="sld-wire-label" style="paint-order:stroke;stroke:#fff;stroke-width:4;stroke-linejoin:round">'.$this->escape($label).'</text>';
            }
            if ($detail !== '') {
                $wire .= '<text x="'.$this->f($labelX).'" y="'.$this->f($labelY + ($label !== '' ? 12 : 0)).'" text-anchor="middle" class="sld-wire-detail" style="paint-order:stroke;stroke:#fff;stroke-width:3.5;stroke-linejoin:round">'.$this->escape($detail).'</text>';
            }
            $wire .= '</g>';
        }

        return $wire.'</g>';
    }

    /** @param array<int,array{x:float,y:float}> $points @return array<int,array{a:array{x:float,y:float},b:array{x:float,y:float}}> */
    private function paperSegments(array $points): array
    {
        $segments = [];
        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index]; $b = $points[$index + 1];
            if ((abs($a['x'] - $b['x']) < .001 || abs($a['y'] - $b['y']) < .001)
                && (abs($a['x'] - $b['x']) >= .001 || abs($a['y'] - $b['y']) >= .001)) {
                $segments[] = ['a' => $a, 'b' => $b];
            }
        }
        return $segments;
    }

    /** @param array<int,array{x:float,y:float}> $points @param array<int,array<string,mixed>> $obstacles @param array<int,string> $excludedIds */
    private function paperRouteBlocked(array $points, array $obstacles, array $excludedIds, float $clearance): bool
    {
        $excluded = array_fill_keys($excludedIds, true);
        $expanded = [];
        foreach ($obstacles as $rect) {
            if (! is_array($rect) || isset($excluded[(string) ($rect['id'] ?? '')])) continue;
            $expanded[] = [
                'left' => (float) ($rect['left'] ?? 0) - $clearance,
                'top' => (float) ($rect['top'] ?? 0) - $clearance,
                'right' => (float) ($rect['right'] ?? 0) + $clearance,
                'bottom' => (float) ($rect['bottom'] ?? 0) + $clearance,
            ];
        }
        foreach ($this->paperSegments($points) as $segment) {
            foreach ($expanded as $rect) {
                $a = $segment['a']; $b = $segment['b'];
                if (abs($a['x'] - $b['x']) < .001) {
                    if ($a['x'] <= $rect['left'] + .001 || $a['x'] >= $rect['right'] - .001) continue;
                    if (max(min($a['y'], $b['y']), $rect['top']) < min(max($a['y'], $b['y']), $rect['bottom']) - .001) return true;
                } elseif (abs($a['y'] - $b['y']) < .001) {
                    if ($a['y'] <= $rect['top'] + .001 || $a['y'] >= $rect['bottom'] - .001) continue;
                    if (max(min($a['x'], $b['x']), $rect['left']) < min(max($a['x'], $b['x']), $rect['right']) - .001) return true;
                } else return true;
            }
        }
        return false;
    }

    /**
     * Normalize a saved route into continuous orthogonal paper-space linework.
     * Legacy route points may describe former symbol terminals. This method
     * removes near-terminal artifacts, inserts deterministic elbows, and
     * guarantees that the first and last segment meet the current ports.
     *
     * @param array<int, array{x:float,y:float}> $points
     * @return array<int, array{x:float,y:float}>
     */
    private function normalizePaperRoute(array $points): array
    {
        $finite = [];
        foreach ($points as $point) {
            if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) continue;
            $normalized = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
            $last = $finite[count($finite) - 1] ?? null;
            if ($last && hypot($last['x'] - $normalized['x'], $last['y'] - $normalized['y']) < .35) continue;
            $finite[] = $normalized;
        }
        if (count($finite) < 2) return $finite;

        $start = $finite[0];
        $end = $finite[count($finite) - 1];
        while (count($finite) > 2 && hypot($finite[1]['x'] - $start['x'], $finite[1]['y'] - $start['y']) < 9.0) array_splice($finite, 1, 1);
        while (count($finite) > 2) {
            $beforeEnd = $finite[count($finite) - 2];
            if (hypot($beforeEnd['x'] - $end['x'], $beforeEnd['y'] - $end['y']) >= 9.0) break;
            array_splice($finite, count($finite) - 2, 1);
        }

        $orthogonal = [$finite[0]];
        for ($index = 1; $index < count($finite); $index++) {
            $previous = $orthogonal[count($orthogonal) - 1];
            $next = $finite[$index];
            $sameX = abs($previous['x'] - $next['x']) < .05;
            $sameY = abs($previous['y'] - $next['y']) < .05;
            if (! $sameX && ! $sameY) {
                $useHorizontalFirst = $index === 1
                    || abs($next['x'] - $previous['x']) >= abs($next['y'] - $previous['y']);
                $orthogonal[] = $useHorizontalFirst
                    ? ['x' => $next['x'], 'y' => $previous['y']]
                    : ['x' => $previous['x'], 'y' => $next['y']];
            }
            $orthogonal[] = $next;
        }

        $clean = [];
        foreach ($orthogonal as $point) {
            $last = $clean[count($clean) - 1] ?? null;
            if ($last && abs($last['x'] - $point['x']) < .05 && abs($last['y'] - $point['y']) < .05) continue;
            $clean[] = $point;
        }
        $index = 1;
        while ($index < count($clean) - 1) {
            $a = $clean[$index - 1]; $b = $clean[$index]; $c = $clean[$index + 1];
            $sameX = abs($a['x'] - $b['x']) < .05 && abs($b['x'] - $c['x']) < .05;
            $sameY = abs($a['y'] - $b['y']) < .05 && abs($b['y'] - $c['y']) < .05;
            if ($sameX || $sameY) { array_splice($clean, $index, 1); continue; }
            $index++;
        }
        return $clean;
    }

    /** @param array<int, array{x:float,y:float}> $points */
    private function routeIsStale(array $points): bool
    {
        if (count($points) < 3) return false;
        $start = $points[0];
        $end = $points[count($points) - 1];
        $minimum = abs($end['x'] - $start['x']) + abs($end['y'] - $start['y']);
        if ($minimum < 1.0) return true;

        $length = 0.0;
        for ($index = 0; $index < count($points) - 1; $index++) {
            $length += abs($points[$index + 1]['x'] - $points[$index]['x'])
                + abs($points[$index + 1]['y'] - $points[$index]['y']);
        }
        $aligned = min(abs($end['x'] - $start['x']), abs($end['y'] - $start['y'])) <= 12.0;
        if ($aligned && $length > max(90.0, $minimum * 2.35)) return true;

        $left = min($start['x'], $end['x']) - 24.0;
        $right = max($start['x'], $end['x']) + 24.0;
        $top = min($start['y'], $end['y']) - 24.0;
        $bottom = max($start['y'], $end['y']) + 24.0;
        $outside = 0;
        foreach (array_slice($points, 1, -1) as $point) {
            if ($point['x'] < $left || $point['x'] > $right || $point['y'] < $top || $point['y'] > $bottom) $outside++;
        }
        return $outside > 0 && $length > $minimum * 2.15;
    }

    /** @param array<int, array{x:float,y:float}> $points @return array{x:float,y:float} */
    private function placeWireLabel(array $points, float $width, float $height): array
    {
        $segments = [];
        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index]; $b = $points[$index + 1];
            $dx = abs($b['x'] - $a['x']); $dy = abs($b['y'] - $a['y']);
            $segments[] = [
                'a' => $a, 'b' => $b,
                'horizontal' => $dy < 1.5,
                'length' => hypot($dx, $dy),
            ];
        }
        usort($segments, static fn (array $a, array $b): int => (($b['horizontal'] ? 1 : 0) <=> ($a['horizontal'] ? 1 : 0)) ?: ($b['length'] <=> $a['length']));
        $candidates = [];
        foreach ($segments as $segment) {
            $mx = ($segment['a']['x'] + $segment['b']['x']) / 2;
            $my = ($segment['a']['y'] + $segment['b']['y']) / 2;
            if ($segment['horizontal']) {
                $candidates[] = ['x' => $mx - ($width / 2), 'y' => $my - $height - 10];
                $candidates[] = ['x' => $mx - ($width / 2), 'y' => $my + 10];
            } else {
                $candidates[] = ['x' => $mx + 10, 'y' => $my - ($height / 2)];
                $candidates[] = ['x' => $mx - $width - 10, 'y' => $my - ($height / 2)];
            }
        }
        foreach ($candidates as $candidate) {
            $rect = [
                'left' => $candidate['x'], 'top' => $candidate['y'],
                'right' => $candidate['x'] + $width, 'bottom' => $candidate['y'] + $height,
            ];
            if ($rect['left'] < 72 || $rect['right'] > 1608 || $rect['top'] < 236 || $rect['bottom'] > 966) continue;
            $collision = false;
            foreach (array_merge($this->deviceLabelObstacles, $this->wireLabelRects) as $other) {
                if ($this->rectanglesIntersect($rect, $other, 5.0)) { $collision = true; break; }
            }
            if (! $collision) {
                $this->wireLabelRects[] = $rect;
                return ['x' => $rect['left'], 'y' => $rect['top']];
            }
        }
        $anchor = $this->wireLabelAnchor($points);
        $x = max(72.0, min(1608.0 - $width, $anchor['x'] - ($width / 2)));
        $y = max(236.0, min(966.0 - $height, $anchor['y'] - $height - 8));
        $rect = ['left'=>$x,'top'=>$y,'right'=>$x+$width,'bottom'=>$y+$height];
        $this->wireLabelRects[] = $rect;
        return ['x' => $x, 'y' => $y];
    }

    /** @param array{left:float,top:float,right:float,bottom:float} $a @param array{left:float,top:float,right:float,bottom:float} $b */
    private function rectanglesIntersect(array $a, array $b, float $gap = 0.0): bool
    {
        return ! ($a['right'] + $gap <= $b['left'] || $b['right'] + $gap <= $a['left'] || $a['bottom'] + $gap <= $b['top'] || $b['bottom'] + $gap <= $a['top']);
    }

    /** @param array<int, array{x:float,y:float}> $points @return array{x:float,y:float} */
    private function wireLabelAnchor(array $points): array
    {
        $best = null;
        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index]; $b = $points[$index + 1];
            $dx = abs($b['x'] - $a['x']); $dy = abs($b['y'] - $a['y']);
            $length = hypot($dx, $dy);
            $horizontalBonus = $dy < 1.5 ? 10000.0 : 0.0;
            $score = $horizontalBonus + $length;
            if ($best === null || $score > $best['score']) {
                $best = ['score'=>$score,'x'=>($a['x']+$b['x'])/2,'y'=>($a['y']+$b['y'])/2];
            }
        }
        return $best ? ['x'=>$best['x'],'y'=>$best['y']] : ($points[0] ?? ['x'=>0.0,'y'=>0.0]);
    }

    /** @param array{scale: float, offset_x: float, offset_y: float} $transform */
    private function paperRoute(array $storedRoute, array $transform): array
    {
        $points = [];
        foreach (array_slice($storedRoute, 0, 30) as $point) {
            if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
                continue;
            }
            $points[] = $this->mapPaperPoint((float) $point['x'], (float) $point['y'], $transform);
        }

        return $points;
    }

    /** @param array{scale: float, offset_x: float, offset_y: float} $transform */
    /** @param array{scale: float, offset_x: float, offset_y: float} $transform */
    private function draftingAnnotation(array $annotation, array $transform): string
    {
        if (!is_numeric($annotation['x'] ?? null) || !is_numeric($annotation['y'] ?? null)) return '';
        $x=(float)$annotation['x']; $y=(float)$annotation['y']; $w=max(80.0,(float)($annotation['w']??240)); $h=max(32.0,(float)($annotation['h']??54));
        $topLeft=$this->mapPaperPoint($x,$y,$transform); $bottomRight=$this->mapPaperPoint($x+$w,$y+$h,$transform);
        $paperW=max(48.0,$bottomRight['x']-$topLeft['x']); $paperH=max(26.0,$bottomRight['y']-$topLeft['y']);
        $text=$this->escape($this->shorten(trim((string)($annotation['text']??'Drawing note')),110));
        return '<g class="sld-annotation"><rect x="'.$this->f($topLeft['x']).'" y="'.$this->f($topLeft['y']).'" width="'.$this->f($paperW).'" height="'.$this->f($paperH).'" rx="3"/>'
            .'<path d="M'.$this->f($topLeft['x']).' '.$this->f($topLeft['y']).'H'.$this->f($topLeft['x']+20).'"/>'
            .'<text x="'.$this->f($topLeft['x']+9).'" y="'.$this->f($topLeft['y']+17).'" class="sld-note">'.$text.'</text></g>';
    }

    /** @param array{scale: float, offset_x: float, offset_y: float} $transform */
    private function draftingShape(array $shape, array $transform): string
    {
        if (! is_numeric($shape['x'] ?? null) || ! is_numeric($shape['y'] ?? null)) {
            return '';
        }

        $type = strtolower((string) ($shape['shape_type'] ?? 'rectangle'));
        if (! in_array($type, ['rectangle', 'circle', 'arc'], true)) {
            return '';
        }
        $x = (float) $shape['x'];
        $y = (float) $shape['y'];
        $w = max(4.0, (float) ($shape['w'] ?? 4));
        $h = max(4.0, (float) ($shape['h'] ?? $w));
        $topLeft = $this->mapPaperPoint($x, $y, $transform);
        $bottomRight = $this->mapPaperPoint($x + $w, $y + $h, $transform);
        $paperW = max(1.0, $bottomRight['x'] - $topLeft['x']);
        $paperH = max(1.0, $bottomRight['y'] - $topLeft['y']);
        $lineType = strtolower((string) ($shape['line_type'] ?? 'continuous'));
        $lineType = in_array($lineType, ['continuous', 'hidden', 'center'], true) ? $lineType : 'continuous';
        $lineWeight = max(0.6, min(5.0, (float) ($shape['lineweight'] ?? 1.4)));
        $class = 'sld-drafting-shape'.($lineType === 'continuous' ? '' : ' '.$lineType);
        $style = 'stroke-width:'.$this->f($lineWeight);

        if ($type === 'circle') {
            $body = '<ellipse cx="'.$this->f($topLeft['x'] + ($paperW / 2)).'" cy="'.$this->f($topLeft['y'] + ($paperH / 2)).'" rx="'.$this->f($paperW / 2).'" ry="'.$this->f($paperH / 2).'" class="'.$class.'" style="'.$style.'"/>';
        } elseif ($type === 'arc') {
            $cx = $topLeft['x'] + ($paperW / 2);
            $cy = $topLeft['y'] + ($paperH / 2);
            $rx = $paperW / 2;
            $ry = $paperH / 2;
            $start = deg2rad((float) ($shape['start_angle'] ?? 0));
            $end = deg2rad((float) ($shape['end_angle'] ?? 90));
            $screenBasis = ($shape['angle_basis'] ?? null) === 'screen';
            $syFactor = $screenBasis ? 1 : -1;
            $sx = $cx + ($rx * cos($start));
            $sy = $cy + ($syFactor * $ry * sin($start));
            $ex = $cx + ($rx * cos($end));
            $ey = $cy + ($syFactor * $ry * sin($end));
            $sweep = ((int) ($shape['sweep_flag'] ?? 1)) === 0 ? 0 : 1;
            $delta = $sweep === 1
                ? fmod(rad2deg($end - $start) + 3600.0, 360.0)
                : fmod(rad2deg($start - $end) + 3600.0, 360.0);
            $large = array_key_exists('large_arc', $shape) ? (((int) $shape['large_arc']) ? 1 : 0) : ($delta > 180.0 ? 1 : 0);
            $body = '<path d="M'.$this->f($sx).' '.$this->f($sy).'A'.$this->f($rx).' '.$this->f($ry).' 0 '.$large.' '.$sweep.' '.$this->f($ex).' '.$this->f($ey).'" class="'.$class.'" style="'.$style.'"/>';
        } else {
            $body = '<rect x="'.$this->f($topLeft['x']).'" y="'.$this->f($topLeft['y']).'" width="'.$this->f($paperW).'" height="'.$this->f($paperH).'" class="'.$class.'" style="'.$style.'"/>';
        }

        $label = trim((string) ($shape['label'] ?? ''));
        if ($label !== '') {
            $body .= '<text x="'.$this->f($topLeft['x'] + ($paperW / 2)).'" y="'.$this->f($topLeft['y'] - 8).'" text-anchor="middle" class="sld-line-label">'.$this->escape($this->shorten($label, 36)).'</text>';
        }

        return '<g class="sld-shape-work">'.$body.'</g>';
    }

    private function draftingLine(array $line, array $transform): string
    {
        if (! is_numeric($line['x1'] ?? null) || ! is_numeric($line['y1'] ?? null) || ! is_numeric($line['x2'] ?? null) || ! is_numeric($line['y2'] ?? null)) {
            return '';
        }
        $start = $this->mapPaperPoint((float) $line['x1'], (float) $line['y1'], $transform);
        $end = $this->mapPaperPoint((float) $line['x2'], (float) $line['y2'], $transform);
        $lineType = strtolower((string) ($line['line_type'] ?? 'continuous'));
        $lineType = in_array($lineType, ['continuous', 'hidden', 'center'], true) ? $lineType : 'continuous';
        $lineWeight = max(0.6, min(5.0, (float) ($line['lineweight'] ?? 1.4)));
        $label = trim((string) ($line['label'] ?? ''));
        $leader = ($line['entity'] ?? null) === 'leader';
        $class = 'sld-drafting-line'.($lineType === 'continuous' ? '' : ' '.$lineType).($leader ? ' leader' : '');
        $marker = $leader ? ' marker-start="url(#sld-leader-arrow)"' : '';
        $svg = '<g class="sld-linework"><path d="M'.$this->f($start['x']).' '.$this->f($start['y']).'L'.$this->f($end['x']).' '.$this->f($end['y']).'" class="'.$class.'" style="stroke-width:'.$this->f($lineWeight).'"'.$marker.'/>';
        if ($label !== '') {
            $svg .= '<text x="'.$this->f(($start['x'] + $end['x']) / 2).'" y="'.$this->f((($start['y'] + $end['y']) / 2) - 8).'" text-anchor="middle" class="sld-line-label">'.$this->escape($this->shorten($label, 36)).'</text>';
        }

        return $svg.'</g>';
    }

    private function device(string $id, array $position, array $node, bool $showTag, ?array $labelPlacement = null): string
    {
        $x = (float) $position['x'];
        $y = (float) $position['y'];
        $rawTitle = trim((string) ($node['paper_title'] ?? $node['title'] ?? $id));
        $semanticTitle = trim((string) ($node['paper_full_title'] ?? $node['title'] ?? $rawTitle));
        $rawSubtitle = trim((string) ($node['subtitle'] ?? ''));
        $rawRating = trim((string) ($node['paper_rating'] ?? $node['rating'] ?? ''));
        $rawTag = trim((string) ($node['tag'] ?? ''));
        $title = $this->escape($rawTitle);
        $semanticTitleEscaped = $this->escape($semanticTitle);
        $subtitle = $this->escape($this->shorten($rawSubtitle, 38));
        $rating = $this->escape($this->shorten($rawRating, 38));
        $tag = $this->escape($rawTag);
        $kind = (string) ($node['paper_symbol'] ?? $node['symbol'] ?? $node['kind'] ?? $id);
        $state = (string) ($node['state'] ?? 'review');
        $manual = ($node['_manual'] ?? false) === true || $state === 'manual';
        $accent = $manual ? '#d58b32' : '#119d95';
        $cellW = max(82.0, (float) ($position['w'] ?? 144.0));
        $cellH = max(72.0, (float) ($position['h'] ?? 120.0));
        $contentScale = max(0.60, min(2.7, min($cellW / 144.0, $cellH / 120.0)));
        $rotation = $this->quarterTurn($node['rotation'] ?? 0);
        $flipH = ($node['flip_h'] ?? false) ? -1 : 1;
        $flipV = ($node['flip_v'] ?? false) ? -1 : 1;
        $showRating = ($node['show_rating'] ?? true) !== false && $this->meaningfulEngineeringText($rawRating);
        $showSubtitle = ($node['paper_show_subtitle'] ?? false) === true
            && ($node['show_subtitle'] ?? true) !== false
            && $this->meaningfulEngineeringText($rawSubtitle);
        $showTag = $showTag && $rawTag !== '';

        $automaticLabel = is_array($labelPlacement);
        $labelWidth = $automaticLabel ? max(96.0, (float) ($labelPlacement['w'] ?? 120.0)) : $cellW;
        $titleLimit = max(12, min(30, (int) floor($labelWidth / 7.0)));
        $titleLines = $this->wrapWords($rawTitle, $titleLimit, 2);
        $labelLineCount = count($titleLines) + ($showTag ? 1 : 0) + ($showRating ? 1 : 0) + ($showSubtitle ? 1 : 0);
        $lineHeight = 13.0;
        if ($automaticLabel) {
            $symbolY = $y;
            $contentScale = max(0.55, min($contentScale, min($cellW / 120.0, $cellH / 86.0)));
            $labelTop = (float) ($labelPlacement['y'] ?? ($y + $cellH / 2 + 14));
        } else {
            $labelBottom = $y + ($cellH / 2) - 8.0;
            $labelTop = $labelBottom - (($labelLineCount - 1) * $lineHeight);
            $symbolAreaBottom = $labelTop - 8.0;
            $symbolAreaTop = $y - ($cellH / 2) + 14.0;
            $symbolY = ($symbolAreaTop + $symbolAreaBottom) / 2;
            $maxSymbolScaleByArea = max(0.55, min(2.7, ($symbolAreaBottom - $symbolAreaTop) / 78.0, $cellW / 120.0));
            $contentScale = min($contentScale, $maxSymbolScaleByArea);
        }

        $parts = [];
        $parts[] = '<g class="sld-device" data-sld-node="'.$this->escape($id).'" data-sld-title="'.$semanticTitleEscaped.'" data-sld-subtitle="'.$subtitle.'" data-sld-rating="'.$rating.'" data-sld-kind="'.$this->escape((string) ($node['kind'] ?? $kind)).'" data-sld-state="'.$this->escape($state).'" role="button" tabindex="0" aria-label="'.$semanticTitleEscaped.'">';
        $parts[] = '<title>'.$semanticTitleEscaped.'</title>';
        $parts[] = '<rect class="sld-focus-ring" x="'.$this->f($x - ($cellW / 2)).'" y="'.$this->f($y - ($cellH / 2)).'" width="'.$this->f($cellW).'" height="'.$this->f($cellH).'" rx="5" fill="none" stroke="#4f8faf" stroke-width="2" stroke-dasharray="6 4"/>';
        $parts[] = '<rect class="sld-hit" x="'.$this->f($x - ($cellW / 2)).'" y="'.$this->f($y - ($cellH / 2)).'" width="'.$this->f($cellW).'" height="'.$this->f($cellH).'"/>';
        $parts[] = '<g transform="translate('.$this->f($x).' '.$this->f($symbolY).') rotate('.$rotation.') scale('.$this->f($contentScale * $flipH).' '.$this->f($contentScale * $flipV).') translate('.$this->f(-$x).' '.$this->f(-$symbolY).')">'.$this->symbol($kind, $x, $symbolY).'</g>';

        if ($automaticLabel) {
            $labelX = (float) ($labelPlacement['x'] ?? ($x - $labelWidth / 2));
            $labelY = (float) ($labelPlacement['y'] ?? ($y + $cellH / 2 + 14));
            $labelHeight = max(34.0, (float) ($labelPlacement['h'] ?? ($labelLineCount * $lineHeight + 14)));
            $labelCenterX = $labelX + ($labelWidth / 2);
            $leader = (bool) ($labelPlacement['leader'] ?? false);
            if ($leader && is_array($labelPlacement['anchor'] ?? null) && is_array($labelPlacement['label_anchor'] ?? null)) {
                $parts[] = '<path d="M'.$this->f((float) $labelPlacement['anchor']['x']).' '.$this->f((float) $labelPlacement['anchor']['y']).'L'.$this->f((float) $labelPlacement['label_anchor']['x']).' '.$this->f((float) $labelPlacement['label_anchor']['y']).'" class="sld-auto-label-leader"/>';
            }
            $parts[] = '<g class="sld-auto-device-label'.(($labelPlacement['fallback'] ?? false) ? ' is-fallback' : '').'" data-label-placement-version="'.SldLabelPlacement::VERSION.'" data-label-placement-contract="'.SldLabelPlacement::CONTRACT.'" data-label-placement-side="'.$this->escape((string) ($labelPlacement['side'] ?? 'bottom')).'" data-label-placement-signature="'.$this->escape((string) ($labelPlacement['signature'] ?? '')).'" data-label-placement-leader="'.($leader ? 'true' : 'false').'">';
            $parts[] = '<rect x="'.$this->f($labelX).'" y="'.$this->f($labelY).'" width="'.$this->f($labelWidth).'" height="'.$this->f($labelHeight).'" rx="3" class="sld-auto-label-box"/>';
            $cursorY = $labelY + 14.0;
            if ($showTag) {
                $parts[] = '<text x="'.$this->f($labelCenterX).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-tag">'.$tag.'</text>';
                $cursorY += $lineHeight;
            }
            foreach ($titleLines as $line) {
                $parts[] = '<text x="'.$this->f($labelCenterX).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-title">'.$this->escape($line).'</text>';
                $cursorY += $lineHeight;
            }
            if ($showRating) {
                $parts[] = '<text x="'.$this->f($labelCenterX).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-rating">'.$rating.'</text>';
                $cursorY += $lineHeight;
            }
            if ($showSubtitle) {
                $parts[] = '<text x="'.$this->f($labelCenterX).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-sub">'.$subtitle.'</text>';
            }
            $parts[] = '</g>';
        } else {
            $cursorY = $labelTop;
            if ($showTag) {
                $parts[] = '<text x="'.$this->f($x).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-tag sld-label-halo">'.$tag.'</text>';
                $cursorY += $lineHeight;
            }
            foreach ($titleLines as $line) {
                $parts[] = '<text x="'.$this->f($x).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-title sld-label-halo">'.$this->escape($line).'</text>';
                $cursorY += $lineHeight;
            }
            if ($showRating) {
                $parts[] = '<text x="'.$this->f($x).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-rating sld-label-halo">'.$rating.'</text>';
                $cursorY += $lineHeight;
            }
            if ($showSubtitle) {
                $parts[] = '<text x="'.$this->f($x).'" y="'.$this->f($cursorY).'" text-anchor="middle" class="sld-device-sub">'.$subtitle.'</text>';
            }
        }
        $parts[] = '<rect x="'.$this->f($x - min(32.0, $cellW * .18)).'" y="'.$this->f($y - ($cellH / 2) + 7).'" width="'.$this->f(min(64.0, $cellW * .36)).'" height="3" rx="1.5" fill="'.$accent.'"/>';
        $parts[] = '</g>';

        return implode('', $parts);
    }

    /** @param array<int, array{0:float,1:float}> $points */
    private function professionalRouteSvg(array $points, string $network, bool $markerEnd = false, string $routeId = ''): string
    {
        if (count($points) < 2) return '';
        $d = '';
        foreach ($points as $index => $point) {
            $d .= ($index === 0 ? 'M' : 'L').$this->f((float) $point[0]).' '.$this->f((float) $point[1]);
        }
        $marker = $markerEnd ? ' marker-end="url(#sld-power-arrow)"' : '';
        $data = $routeId !== '' ? ' data-paper-route="'.$this->escape($routeId).'"' : '';
        return '<path d="'.$d.'" class="sld-connection '.$this->escape($network).'"'.$marker.$data.'/>';
    }

    private function professionalPortSvg(float $x, float $y, string $network = 'power'): string
    {
        $radius = $network === 'power' ? 2.15 : 2.75;
        return '<circle cx="'.$this->f($x).'" cy="'.$this->f($y).'" r="'.$this->f($radius).'" class="sld-paper-port '.$this->escape($network).'" data-paper-port="'.$this->escape($network).'"/>';
    }

    private function formatCableSize(string $value): string
    {
        $size = trim($value);
        if ($size === '') return '';

        if (preg_match('/(?:mm\s*(?:²|2)|sq\.?\s*mm)/iu', $size)) {
            return preg_replace('/mm\s*2/iu', 'mm²', $size) ?? $size;
        }

        // Append an area unit only to numeric conductor-size expressions.
        // Protocol and medium descriptions such as RS485, Ethernet, fibre, or CAT6 remain literal.
        if (preg_match('/^[0-9.,x×+\-\s]+$/u', $size)) return $size.' mm²';

        return $size;
    }

    private function meaningfulEngineeringText(string $value): bool
    {
        $normal = mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
        if ($normal === '' || in_array($normal, ['—', '-', 'n/a', 'na', 'none', 'pending', 'not specified'], true)) return false;
        return ! str_contains($normal, 'not specified')
            && ! str_contains($normal, 'data unavailable')
            && ! str_contains($normal, 'to be confirmed');
    }

    /** @return array<int, string> */
    private function wrapWords(string $value, int $limit, int $maxLines = 2): array
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($value === '') return [''];
        $words = preg_split('/\s+/', $value) ?: [$value];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && mb_strlen($candidate) > $limit) {
                $lines[] = $line;
                $line = $word;
                if (count($lines) >= $maxLines - 1) break;
            } else {
                $line = $candidate;
            }
        }
        $consumed = implode(' ', $lines);
        $remaining = trim($value);
        if ($consumed !== '' && str_starts_with($remaining, $consumed)) $remaining = trim(mb_substr($remaining, mb_strlen($consumed)));
        if ($line !== '') {
            if ($remaining !== '' && count($lines) >= $maxLines - 1) $line = $remaining;
            $lines[] = $this->shorten($line, $limit);
        }
        return array_slice($lines, 0, $maxLines);
    }

    private function quarterTurn(mixed $value): int
    {
        $numeric = is_numeric($value) ? (float) $value : 0.0;
        $rotation = ((int) round($numeric / 90)) * 90;

        return (($rotation % 360) + 360) % 360;
    }

    private function symbol(string $kind, float $cx, float $cy): string
    {
        $x = fn (float $offset): string => $this->f($cx + $offset);
        $y = fn (float $offset): string => $this->f($cy + $offset);

        return match ($kind) {
            'pv_module' => '<g transform="translate('.$x(-38).' '.$y(-29).')"><rect class="sld-symbol" x="5" y="5" width="66" height="42"/><path class="sld-symbol" d="M27 5v42M49 5v42M5 26h66"/><path class="sld-symbol-accent" d="M0 26h5M71 26h5"/></g>',
            'pv_string', 'sub_array' => '<g transform="translate('.$x(-43).' '.$y(-27).')"><rect class="sld-symbol" x="1" y="7" width="24" height="32"/><rect class="sld-symbol" x="31" y="7" width="24" height="32"/><rect class="sld-symbol" x="61" y="7" width="24" height="32"/><path class="sld-symbol" d="M9 7v32M17 7v32M39 7v32M47 7v32M69 7v32M77 7v32M1 23h24M31 23h24M61 23h24"/><path class="sld-symbol-accent" d="M25 23h6M55 23h6M0 23h1M85 23h1M27 48h32"/></g>',
            'pv_array' => '<g transform="translate('.$x(-37).' '.$y(-35).')"><path class="sld-symbol" d="M5 7h58l8 40H13zM23 7l-6 40M44 7l2 40M9 20h58M11 34h58"/><path class="sld-symbol-accent" d="M5 52h70M29 52l-10 13M51 52l10 13"/></g>',
            'junction_box' => '<g transform="translate('.$x(-39).' '.$y(-31).')"><rect class="sld-symbol" x="12" y="5" width="54" height="52" rx="3"/><path class="sld-symbol" d="M0 16h12M0 30h12M0 44h12M66 30h12M24 16h15M24 30h15M24 44h15M39 16v28M39 30h15"/></g>',
            'combiner', 'ac_combiner' => '<g transform="translate('.$x(-45).' '.$y(-31).')"><rect class="sld-symbol" x="15" y="3" width="58" height="56" rx="3"/><path class="sld-symbol" d="M0 15h15M0 30h15M0 45h15M73 30h17M28 15h16M28 30h16M28 45h16M44 15v30M44 30h16"/><path class="sld-symbol-accent" d="M60 43v12M52 55h16"/></g>',
            'power_optimizer', 'dc_dc_converter', 'mppt' => '<g transform="translate('.$x(-39).' '.$y(-29).')"><rect class="sld-symbol" x="8" y="4" width="62" height="50" rx="3"/><path class="sld-symbol-accent" d="M0 29h8M70 29h8M18 39l12-20 10 15 13-20"/><text x="39" y="49" text-anchor="middle" class="sld-device-tag">'.($kind === 'mppt' ? 'MPPT' : 'DC/DC').'</text></g>',
            'rsd', 'epo' => '<g transform="translate('.$x(-34).' '.$y(-34).')"><circle class="sld-symbol" cx="34" cy="34" r="29"/><circle class="sld-symbol-accent" cx="34" cy="34" r="13"/><path class="sld-symbol" d="M34 0v12M34 56v12M0 34h12M56 34h12"/><text x="34" y="38" text-anchor="middle" class="sld-device-title">'.($kind === 'epo' ? 'E' : 'R').'</text></g>',
            'microinverter', 'inverter', 'hybrid_inverter', 'offgrid_inverter', 'central_inverter', 'pcs' => '<g transform="translate('.$x(-36).' '.$y(-36).')"><rect class="sld-symbol" x="2" y="2" width="68" height="68" rx="4"/><path class="sld-symbol" d="M6 66 66 6"/><path class="sld-symbol-accent" d="M14 18h16m-16 8h16M42 51c5-10 10-10 15 0s10 10 15 0"/>'.($kind === 'pcs' ? '<text x="36" y="64" text-anchor="middle" class="sld-device-tag">BIDI</text>' : '').'</g>',
            'battery_cell', 'battery_module', 'battery_rack', 'battery' => '<g transform="translate('.$x(-43).' '.$y(-27).')"><rect class="sld-symbol" x="3" y="8" width="78" height="43" rx="3"/><path class="sld-symbol" d="M81 21h7v18h-7M18 18v23M29 22v15M42 18v23M53 22v15"/><path class="sld-symbol-accent" d="M66 18v23M10 29h16M58 29h16"/></g>',
            'controller_paper', 'relay_paper' => '<g transform="translate('.$x(-39).' '.$y(-31).')"><rect class="sld-symbol" x="3" y="3" width="72" height="56" rx="4"/><path class="sld-symbol" d="M15 17h48M15 30h34M15 43h44"/><path class="sld-symbol-accent" d="M59 13h8v8h-8zM59 27h8v8h-8z"/></g>',
            'bms', 'relay', 'scada', 'data_logger', 'plant_controller' => '<g transform="translate('.$x(-39).' '.$y(-31).')"><rect class="sld-symbol" x="3" y="3" width="72" height="56" rx="4"/><path class="sld-symbol" d="M15 17h48M15 30h34M15 43h44"/><path class="sld-symbol-accent" d="M59 13h8v8h-8zM59 27h8v8h-8z"/><text x="39" y="55" text-anchor="middle" class="sld-device-tag">'.strtoupper(match ($kind) { 'scada' => 'RTU', 'data_logger' => 'DL', 'plant_controller' => 'PPC', default => $kind }).'</text></g>',
            'battery_disconnect', 'dc_isolator', 'ac_isolator' => '<g transform="translate('.$x(-45).' '.$y(-22).')"><path class="sld-symbol" d="M0 22h28M58 22h32M28 22 58 4"/><circle cx="28" cy="22" r="2.8" fill="#12374b"/><circle class="sld-symbol" cx="58" cy="22" r="2.8"/><path class="sld-symbol-thin" d="M41 5v-8M37-3h8"/></g>',
            'dc_fuse' => '<g transform="translate('.$x(-45).' '.$y(-22).')"><path class="sld-symbol" d="M0 22h31M59 22h31"/><rect class="sld-symbol" x="31" y="12" width="28" height="20"/><path class="sld-symbol-thin" d="M35 28 55 16"/></g>',
            'dc_breaker', 'ac_breaker', 'dc_protection', 'ac_protection' => '<g transform="translate('.$x(-45).' '.$y(-23).')"><path class="sld-symbol" d="M0 23h28M62 23h28M28 23 58 4"/><path class="sld-symbol-thin" d="M58 4h4v19"/><circle cx="28" cy="23" r="2.8" fill="#12374b"/><circle class="sld-symbol" cx="62" cy="23" r="2.8"/>'.($kind === 'ac_protection' ? '<rect class="sld-symbol-thin" x="18" y="-4" width="54" height="40"/>' : '').'</g>',
            'dc_spd_paper', 'ac_spd_paper' => '<g transform="translate('.$x(-40).' '.$y(-33).')"><path class="sld-symbol-accent" d="M0 23h29"/><rect class="sld-symbol" x="29" y="8" width="22" height="32" rx="2"/><path class="sld-symbol-thin" d="M36 35 46 13M35 14h10"/><path class="sld-symbol" d="M40 40v14M26 54h28M31 62h18M36 70h8"/></g>',
            'dc_spd', 'ac_spd' => '<g transform="translate('.$x(-40).' '.$y(-37).')"><path class="sld-symbol-accent" d="M0 23h29"/><rect class="sld-symbol" x="29" y="8" width="22" height="32" rx="2"/><path class="sld-symbol-thin" d="M36 35 46 13M35 14h10"/><path class="sld-symbol" d="M40 40v14M26 54h28M31 62h18M36 70h8"/><text x="40" y="4" text-anchor="middle" class="sld-device-tag">'.strtoupper($kind === 'dc_spd' ? 'DC SPD' : 'AC SPD').'</text></g>',
            'pe_bus_paper' => '<g transform="translate('.$x(-44).' '.$y(-27).')"><path class="sld-symbol" stroke-width="4" d="M5 24h78"/><path class="sld-symbol-thin" d="M16 8v16M31 8v16M46 8v16M61 8v16M76 8v16M44 24v18M28 42h32M34 50h20M40 58h8"/></g>',
            'pe_bus' => '<g transform="translate('.$x(-44).' '.$y(-27).')"><path class="sld-symbol" stroke-width="4" d="M5 24h78"/><path class="sld-symbol-thin" d="M16 8v16M31 8v16M46 8v16M61 8v16M76 8v16M44 24v18M28 42h32M34 50h20M40 58h8"/><text x="44" y="4" text-anchor="middle" class="sld-device-tag">PE BUS</text></g>',
            'gfpd', 'afci' => '<g transform="translate('.$x(-45).' '.$y(-24).')"><rect class="sld-symbol" x="18" y="-2" width="54" height="48"/><path class="sld-symbol" d="M0 22h28M62 22h28M28 22 56 5"/><circle cx="28" cy="22" r="2.6" fill="#12374b"/><circle class="sld-symbol" cx="62" cy="22" r="2.6"/><text x="45" y="40" text-anchor="middle" class="sld-device-tag">'.strtoupper($kind).'</text></g>',
            'dc_busbar' => '<g transform="translate('.$x(-42).' '.$y(-25).')"><path class="sld-symbol" stroke-width="5" d="M2 15h80M2 35h80"/><path class="sld-symbol-accent" d="M16 0v15M34 0v15M52 35v15M70 35v15"/></g>',
            'meter_paper' => '<g transform="translate('.$x(-37).' '.$y(-35).')"><rect class="sld-symbol" x="9" y="4" width="56" height="62"/><path class="sld-symbol-thin" d="M16 15h42M18 25h38M18 39h38M18 53h38"/></g>',
            'meter', 'app_pln' => '<g transform="translate('.$x(-37).' '.$y(-35).')"><rect class="sld-symbol" x="9" y="4" width="56" height="62"/><path class="sld-symbol-thin" d="M16 15h42M18 25h38"/><text x="37" y="49" text-anchor="middle" class="sld-device-title">Wh</text><path class="sld-symbol-thin" d="M18 57h38M18 57l7-4m-7 4 7 4M56 57l-7-4m7 4-7 4"/></g>',
            'pcc' => '<g><circle class="sld-symbol" cx="'.$x(0).'" cy="'.$y(0).'" r="28"/><path class="sld-symbol-accent" d="M'.$x(-44).' '.$y(0).'h16M'.$x(28).' '.$y(0).'h16"/><text x="'.$x(0).'" y="'.$y(4).'" text-anchor="middle" class="sld-device-title">PCC</text></g>',
            'grid' => '<g transform="translate('.$x(-34).' '.$y(-39).')"><path class="sld-symbol" d="M34 2 10 76M34 2l24 74M18 21h32M13 39h42M6 76h56M23 21l22 18M45 21 23 39M18 39l32 37M50 39 18 37"/><path class="sld-symbol-accent" d="M0 13h68"/></g>',
            'transformer' => '<g transform="translate('.$x(-42).' '.$y(-29).')"><path class="sld-symbol" d="M0 29h18M66 29h18"/><circle class="sld-symbol" cx="32" cy="29" r="18"/><circle class="sld-symbol" cx="52" cy="29" r="18"/></g>',
            'ct' => '<g transform="translate('.$x(-42).' '.$y(-29).')"><path class="sld-symbol" d="M0 29h84"/><circle class="sld-symbol" cx="42" cy="29" r="20"/><path class="sld-symbol-thin" d="M46 50v13M54 50v13"/></g>',
            'pt' => '<g transform="translate('.$x(-42).' '.$y(-31).')"><path class="sld-symbol" d="M42 0v11M42 51v11"/><circle class="sld-symbol" cx="42" cy="25" r="15"/><circle class="sld-symbol" cx="42" cy="39" r="15"/></g>',
            'generator' => '<g><circle class="sld-symbol" cx="'.$x(0).'" cy="'.$y(0).'" r="31"/><text x="'.$x(0).'" y="'.$y(7).'" text-anchor="middle" class="sld-sheet-title" style="fill:#12374b">G</text><path class="sld-symbol-accent" d="M'.$x(31).' '.$y(0).'h16"/></g>',
            'panel', 'main_panel', 'critical_panel', 'backup_panel', 'load' => '<g transform="translate('.$x(-38).' '.$y(-33).')"><rect class="sld-symbol" x="3" y="3" width="70" height="60" rx="3"/><path class="sld-symbol" d="M15 17h46M15 32h46M15 47h46"/><circle cx="22" cy="17" r="3" fill="#12a59c"/><circle cx="22" cy="32" r="3" fill="#12a59c"/><circle cx="22" cy="47" r="3" fill="#12a59c"/></g>',
            'transfer_switch' => '<g transform="translate('.$x(-41).' '.$y(-31).')"><rect class="sld-symbol" x="2" y="2" width="78" height="58" rx="3"/><path class="sld-symbol" d="M14 18h19m20 0h15M14 44h19m20 0h15M33 18l20 26"/><circle cx="33" cy="18" r="3" fill="#fff" stroke="#12374b" stroke-width="2"/><circle cx="53" cy="18" r="3" fill="#fff" stroke="#12374b" stroke-width="2"/><circle cx="53" cy="44" r="3" fill="#fff" stroke="#12374b" stroke-width="2"/></g>',
            'ground', 'ground_rod' => '<g transform="translate('.$x(-32).' '.$y(-36).')"><path class="sld-symbol" d="M32 2v39M8 41h48M16 51h32M24 61h16"/>'.($kind === 'ground_rod' ? '<path class="sld-symbol-thin" d="M32 61v15M24 70l16-8M24 78l16-8"/>' : '').'</g>',
            'lightning_arrester' => '<g transform="translate('.$x(-31).' '.$y(-37).')"><path class="sld-symbol" d="M31 0v15M31 49v13M7 62h48M14 70h34"/><rect class="sld-symbol" x="22" y="15" width="18" height="34"/><path class="sld-symbol-thin" d="M27 39 36 24M27 24h9"/></g>',
            'weather_station' => '<g transform="translate('.$x(-37).' '.$y(-37).')"><path class="sld-symbol" d="M37 3v66M15 69h44M19 21h36M25 21l12 18 12-18"/><circle class="sld-symbol-accent" cx="37" cy="3" r="4"/><path class="sld-symbol-accent" d="M7 12h12M55 12h12M3 38h14M57 38h14"/></g>',
            'irradiance_sensor' => '<g transform="translate('.$x(-37).' '.$y(-31).')"><path class="sld-symbol" d="M8 54h58M15 54a22 22 0 0 1 44 0"/><path class="sld-symbol-accent" d="M37 0v17M12 8l12 12M62 8 50 20"/></g>',
            'monitoring' => '<g transform="translate('.$x(-38).' '.$y(-31).')"><rect class="sld-symbol" x="3" y="3" width="70" height="52" rx="4"/><path class="sld-symbol-accent" d="M15 42h46M20 34l10-10 9 6 14-15M31 66h14"/></g>',
            default => '<g><circle class="sld-symbol" cx="'.$x(0).'" cy="'.$y(0).'" r="31"/><path class="sld-symbol-accent" d="M'.$x(-16).' '.$y(0).'h32M'.$x(0).' '.$y(-16).'v32"/></g>',
        };
    }

    /** @param array<string, mixed> $sheet */
    private function overviewDrawing(array $sheet): string
    {
        $groups = is_array($sheet['overview_groups'] ?? null) ? $sheet['overview_groups'] : [];
        if ($groups === []) {
            return '<text x="840" y="500" text-anchor="middle" class="sld-device-sub">No overview groups are available.</text>';
        }
        $parts = ['<g aria-label="Functional system overview">'];
        foreach ($groups as $index => $group) {
            $x = (float) ($group['x'] ?? 300);
            $y = (float) ($group['y'] ?? 360);
            $title = $this->escape((string) ($group['title'] ?? 'System group'));
            $count = (int) ($group['count'] ?? 0);
            $sheets = array_map('intval', is_array($group['detail_sheets'] ?? null) ? $group['detail_sheets'] : []);
            $reference = $sheets === [] ? 'MODEL SPACE' : 'SEE SHEET '.implode(', ', $sheets);
            $parts[] = '<g transform="translate('.$this->f($x - 185).' '.$this->f($y - 72).')">'
                .'<rect width="370" height="144" rx="8" fill="#f9fcfc" stroke="#9db4b9" stroke-width="1.4"/>'
                .'<rect width="8" height="144" rx="4" fill="#119d95"/>'
                .'<circle cx="52" cy="52" r="26" fill="#e7f7f5" stroke="#119d95"/>'
                .'<text x="52" y="58" text-anchor="middle" class="sld-table-value">'.($index + 1).'</text>'
                .'<text x="98" y="45" class="sld-device-title">'.$title.'</text>'
                .'<text x="98" y="70" class="sld-device-rating">'.$count.' equipment object'.($count === 1 ? '' : 's').'</text>'
                .'<text x="98" y="101" class="sld-device-tag">'.$this->escape($reference).'</text>'
                .'</g>';
            if (($index % 3) !== 2 && isset($groups[$index + 1])) {
                $nextX = (float) ($groups[$index + 1]['x'] ?? $x + 520);
                $parts[] = '<path d="M'.$this->f($x + 185).' '.$this->f($y).'H'.$this->f($nextX - 185).'" class="sld-connection" marker-end="url(#sld-arrow)"/>';
            }
        }
        $parts[] = '<text x="840" y="770" text-anchor="middle" class="sld-note">Functional overview only. Detailed equipment, terminal data, and conductor routing are shown on the referenced sheets.</text>';
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /** @param array<string, mixed> $sheet @param array<string, array{x:float,y:float}> $positions @param array<string, array<string, mixed>> $nodes */
    private function crossReferences(array $sheet, array $positions, array $nodes): string
    {
        $references = is_array($sheet['cross_references'] ?? null) ? $sheet['cross_references'] : [];
        if ($references === []) return '';
        $parts = ['<g aria-label="Off-page connection references">'];
        foreach ($references as $reference) {
            $id = (string) ($reference['node_id'] ?? '');
            if (! isset($positions[$id])) continue;
            $position = $positions[$id];
            $direction = (string) ($reference['direction'] ?? 'to');
            $otherSheet = (int) ($reference['other_sheet'] ?? 0);
            $connection = is_array($reference['connection'] ?? null) ? $reference['connection'] : [];
            $label = $this->escape($this->shorten((string) ($connection['label'] ?? 'Power circuit'), 22));
            $x = (float) $position['x'] + ($direction === 'to' ? 92 : -92);
            $y = (float) $position['y'] - 18;
            $points = $direction === 'to'
                ? ($this->f($x - 18).','.$this->f($y - 13).' '.$this->f($x + 18).','.$this->f($y).' '.$this->f($x - 18).','.$this->f($y + 13))
                : ($this->f($x + 18).','.$this->f($y - 13).' '.$this->f($x - 18).','.$this->f($y).' '.$this->f($x + 18).','.$this->f($y + 13));
            $parts[] = '<polygon points="'.$points.'" fill="#fff" stroke="#0b817b" stroke-width="1.8"/>'
                .'<text x="'.$this->f($x).'" y="'.$this->f($y - 23).'" text-anchor="middle" class="sld-device-tag">'.strtoupper($direction).' SLD-'.str_pad((string) $otherSheet, 2, '0', STR_PAD_LEFT).'</text>'
                .'<text x="'.$this->f($x).'" y="'.$this->f($y + 29).'" text-anchor="middle" class="sld-wire-detail">'.$label.'</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /** @param array<string, array<string, mixed>> $nodes */
    private function earthingReference(array $nodes, array $positions): string
    {
        $earthCandidates = ['dc_protection', 'inverter', 'ac_protection', 'meter'];
        $active = array_values(array_filter($earthCandidates, static fn (string $id): bool => isset($nodes[$id], $positions[$id])));
        if ($active === []) {
            return '';
        }
        $parts = ['<g aria-label="Protective earth reference"><path d="M260 786H1415" stroke="#596f7c" stroke-width="2.2"/><text x="1430" y="790" class="sld-wire-label">PE REFERENCE</text>'];
        foreach ($active as $id) {
            $x = $positions[$id]['x'];
            $parts[] = '<path d="M'.$this->f($x).' '.$this->f($positions[$id]['y'] + 18).'V786" stroke="#718892" stroke-width="1.4" stroke-dasharray="5 4"/>';
        }
        $parts[] = '<path class="sld-symbol" d="M220 760v26M196 786h48M204 796h32M212 806h16"/></g>';

        return implode('', $parts);
    }

    /** @param array<string, array<string, mixed>> $nodes @param array<string, mixed> $sheet @param array<string, mixed> $paperSummary */
    private function notesAndLegend(array $nodes, array $annotations, array $sheet, array $paperSummary): string
    {
        $kinds = [];
        foreach ($nodes as $id => $node) {
            $kind = str_starts_with($id, 'manual_') ? (string) ($node['kind'] ?? 'equipment') : (string) ($node['symbol'] ?? $node['kind'] ?? $id);
            $label = (string) ($node['title'] ?? ucfirst(str_replace('_', ' ', $kind)));
            $kinds[$kind] = $label;
        }
        $legend = array_slice($kinds, 0, 6, true);
        $parts = ['<g aria-label="Drawing notes and legend">'];
        $parts[] = '<rect x="66" y="988" width="938" height="58" fill="#fff" stroke="#9fb2b8"/>';
        $parts[] = '<text x="84" y="1007" class="sld-foot-label">ENGINEERING NOTES</text>';
        $notes = [
            '1. Preliminary single-line diagram. Verify protection coordination, conductor sizing, routing, and earthing before construction.',
            '2. Model Space composition is preserved. Drawing is not to scale; equipment data remains controlled by the approved calculation source.',
        ];
        foreach ($notes as $index => $note) {
            $parts[] = '<text x="84" y="'.(1024 + ($index * 13)).'" class="sld-note">'.$this->escape($this->shorten($note, 152)).'</text>';
        }

        $parts[] = '<rect x="1018" y="988" width="596" height="58" fill="#fff" stroke="#9fb2b8"/>';
        $parts[] = '<text x="1036" y="1007" class="sld-foot-label">SYMBOLS USED</text>';
        $index = 0;
        foreach ($legend as $kind => $label) {
            $column = $index % 2;
            $row = intdiv($index, 2);
            $x = 1038 + ($column * 282);
            $y = 1019 + ($row * 15);
            $parts[] = '<g transform="translate('.$x.' '.$y.') scale(.23)">'.$this->symbol($kind, 0, 0).'</g>';
            $parts[] = '<text x="'.($x + 25).'" y="'.($y + 4).'" class="sld-foot-value">'.$this->escape($this->shorten($label, 36)).'</text>';
            $index++;
        }
        if (count($kinds) > count($legend)) {
            $parts[] = '<text x="1596" y="1040" text-anchor="end" class="sld-device-tag">+'.(count($kinds) - count($legend)).' TYPES</text>';
        }
        if ($legend === []) {
            $parts[] = '<text x="1036" y="1030" class="sld-foot-value">No equipment symbols on this sheet.</text>';
        }
        $parts[] = '</g>';
        return implode('', $parts);
    }

    /** @param array<string, mixed> $sheet */
    private function titleBlock(array $graph, string $projectName, string $projectNumber, string $systemLabel, bool $verified, array $sheet): string
    {
        $schema = $this->escape((string) ($graph['schema_version'] ?? SldGraphBuilder::SCHEMA_VERSION));
        $generated = $this->escape($this->date((string) data_get($graph, 'meta.generated_at', '')));
        $status = $verified ? 'FOR REVIEW · VERIFIED SOURCE' : 'PRELIMINARY · ENGINEERING DRAFT';
        $sheetNumber = (int) ($sheet['number'] ?? 1);
        $sheetCount = (int) ($sheet['count'] ?? 1);
        $sheetTitle = $this->escape((string) ($sheet['title'] ?? 'Single-line diagram'));
        $drawingNumber = $projectNumber.'-E-SLD-'.str_pad((string) $sheetNumber, 3, '0', STR_PAD_LEFT);
        $revision = $this->escape((string) data_get($graph, 'meta.revision', 'R1'));
        $checkStatus = strtoupper((string) data_get($graph, 'electrical_validation.status', 'PENDING'));
        $checkValue = sprintf(
            '%s %dE/%dW',
            substr($checkStatus, 0, 1),
            (int) data_get($graph, 'electrical_validation.summary.error', 0),
            (int) data_get($graph, 'electrical_validation.summary.warning', 0)
        );

        return '<g aria-label="Drawing title block">'
            .'<rect x="66" y="1058" width="1548" height="96" fill="#fff" stroke="#102f43" stroke-width="1.5"/>'
            .'<path d="M66 1101H1614M760 1058V1154M1092 1058V1154M1308 1058V1154M1432 1058V1154M1522 1058V1154" stroke="#aebdc1"/>'
            .'<text x="84" y="1077" class="sld-foot-label">PROJECT</text>'
            .'<text x="84" y="1094" class="sld-foot-mono">'.$projectNumber.' · '.$projectName.'</text>'
            .'<text x="84" y="1120" class="sld-foot-label">DOCUMENT STATUS</text>'
            .'<text x="84" y="1143" class="sld-foot-mono">'.$status.'</text>'
            .'<text x="778" y="1077" class="sld-foot-label">DRAWING TITLE</text>'
            .'<text x="778" y="1094" class="sld-foot-mono">'.$sheetTitle.'</text>'
            .'<text x="778" y="1120" class="sld-foot-label">SYSTEM / GENERATED</text>'
            .'<text x="778" y="1143" class="sld-foot-value">'.$systemLabel.' · '.$generated.'</text>'
            .'<text x="1110" y="1077" class="sld-foot-label">DRAWING NUMBER</text>'
            .'<text x="1110" y="1094" class="sld-foot-mono">'.$drawingNumber.'</text>'
            .'<text x="1110" y="1120" class="sld-foot-label">SCHEMA</text>'
            .'<text x="1110" y="1143" class="sld-foot-mono">'.$schema.'</text>'
            .'<text x="1326" y="1077" class="sld-foot-label">REV</text>'
            .'<text x="1326" y="1095" class="sld-table-value">'.$revision.'</text>'
            .'<text x="1326" y="1120" class="sld-foot-label">SCALE</text>'
            .'<text x="1326" y="1143" class="sld-foot-mono">NTS</text>'
            .'<text x="1450" y="1077" class="sld-foot-label">SHEET</text>'
            .'<text x="1450" y="1095" class="sld-table-value">'.$sheetNumber.' / '.$sheetCount.'</text>'
            .'<text x="1450" y="1120" class="sld-foot-label">CHECK</text>'
            .'<text x="1450" y="1143" class="sld-foot-mono">'.$this->escape($checkValue).'</text>'
            .'<text x="1540" y="1077" class="sld-foot-label">SIZE</text>'
            .'<text x="1540" y="1095" class="sld-table-value">A3</text>'
            .'<text x="1540" y="1120" class="sld-foot-label">DOC</text>'
            .'<text x="1540" y="1143" class="sld-foot-mono">SLD</text>'
            .'</g>';
    }

    private function connectionId(array $connection, int $index): string
    {
        if (filled($connection['id'] ?? null)) {
            return (string) $connection['id'];
        }

        return 'source_'.($connection['from'] ?? 'from').'__'.($connection['to'] ?? 'to').'__'.$index;
    }

    private function number(mixed $value, int $decimals): string
    {
        if (! is_numeric($value)) {
            return '—';
        }
        $formatted = number_format((float) $value, $decimals, '.', ',');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    private function date(string $value): string
    {
        if ($value === '') {
            return '—';
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function shorten(string $value, int $limit): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(1, $limit - 1))).'…';
    }

    private function f(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
