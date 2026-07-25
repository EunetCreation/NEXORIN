<?php

namespace App\Services\Sld;

use App\Models\Project;
use App\Models\ProjectSldRevision;

/**
 * Deterministic, dependency-free A3 PDF fallback for controlled SLDs.
 *
 * The primary export path prints the professional SVG renderer through
 * Playwright. This fallback keeps the same engineering hierarchy when a Node
 * runtime is unavailable. It deliberately renders drafting symbols, conductors,
 * notes, legend, and a title block rather than dashboard-style cards.
 */
class SldPdfRenderer
{
    private const WIDTH = 1190.55;
    private const HEIGHT = 841.89;

    /** @var array<int, string> */
    private array $commands = [];

    /** @var array<int, array<int, string>> */
    private array $pages = [];

    /** @var array<string, array{x:float,y:float}> */
    private array $deviceCenters = [];

    private string $visualFingerprint = '';

    private string $visualStatus = 'pass';

    public function __construct(
        private readonly SldPaperComposer $paperComposer,
        private readonly SldVisualRegression $visualRegression,
    ) {
    }

    public function render(Project $project, ProjectSldRevision $revision): string
    {
        $this->pages = [];
        $graph = is_array($revision->graph) ? $revision->graph : [];
        $meta = is_array($graph['meta'] ?? null) ? $graph['meta'] : [];
        $summary = is_array($meta['summary'] ?? null) ? $meta['summary'] : [];
        $editor = is_array($graph['editor'] ?? null) ? $graph['editor'] : [];
        $visualMetadata = $this->visualRegression->normalizeMetadata($editor);
        $this->visualFingerprint = (string) ($visualMetadata['fingerprint'] ?? '');
        $this->visualStatus = (string) ($visualMetadata['status'] ?? 'pass');
        $excluded = array_fill_keys(array_values(array_filter($editor['excluded_nodes'] ?? [], 'is_string')), true);

        $nodes = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! isset($node['id']) || isset($excluded[(string) $node['id']])) continue;
            $nodes[(string) $node['id']] = $node;
        }
        foreach (is_array($editor['manual_nodes'] ?? null) ? $editor['manual_nodes'] : [] as $node) {
            if (! is_array($node) || ! isset($node['id']) || isset($excluded[(string) $node['id']])) continue;
            $node['_manual'] = true;
            $nodes[(string) $node['id']] = $node;
        }

        $connections = [];
        foreach (is_array($graph['connections'] ?? null) ? $graph['connections'] : [] as $index => $connection) {
            if (! is_array($connection)) continue;
            $connection['_id'] = $this->connectionId($connection, $index);
            $connections[] = $connection;
        }
        foreach (is_array($editor['manual_connections'] ?? null) ? $editor['manual_connections'] : [] as $index => $connection) {
            if (! is_array($connection)) continue;
            $connection['_manual'] = true;
            $connection['_id'] = isset($connection['id']) ? (string) $connection['id'] : 'manual_'.$this->connectionId($connection, $index);
            $connections[] = $connection;
        }
        $connections = array_values(array_filter($connections, static fn (mixed $connection): bool => is_array($connection)
            && isset($nodes[(string) ($connection['from'] ?? '')], $nodes[(string) ($connection['to'] ?? '')])));

        $composition = $this->paperComposer->compose($graph);
        foreach ($composition['sheets'] as $sheet) {
            $this->commands = [];
            $this->deviceCenters = [];
            $nodeIds = array_fill_keys(array_map('strval', is_array($sheet['node_ids'] ?? null) ? $sheet['node_ids'] : []), true);
            $sheetNodes = array_filter($nodes, static fn (array $node, string $id): bool => isset($nodeIds[$id]), ARRAY_FILTER_USE_BOTH);
            $sheetConnections = array_values(array_filter($connections, static fn (array $connection): bool => isset(
                $nodeIds[(string) ($connection['from'] ?? '')],
                $nodeIds[(string) ($connection['to'] ?? '')],
            )));

            $sheetType = (string) ($sheet['type'] ?? 'single');
            $this->pageFrame();
            $this->header($project, $revision, $meta, $sheet);
            if ($sheetType === 'professional_sld') {
                $this->designData($summary, $meta);
                $this->professionalPdfMainSld($graph);
            } elseif ($sheetType === 'control_schematic') {
                $this->professionalPdfControlSchematic($graph);
            } elseif ($sheetType === 'string_schedule') {
                $this->professionalPdfStringSchedule($graph);
            } elseif ($sheetType === 'cable_schedule') {
                $this->professionalPdfCableSchedule($graph, $sheet);
            } elseif ($sheetType === 'protection_schedule') {
                $this->professionalPdfProtectionSchedule($graph);
            } else {
                $this->designData($summary, $meta);
                if ($sheetType === 'overview') {
                    $this->overviewSchematic($sheet);
                } else {
                    $sheetEditor = $this->editorForSheet($editor, $sheet);
                    $this->schematic($sheetNodes, $sheetConnections, $sheetEditor);
                }
                $this->notesAndLegend($sheetNodes);
            }
            $this->titleBlock($project, $revision, $graph, $sheet);
            $this->pages[] = $this->commands;
        }

        if ($this->pages === []) {
            $this->commands = [];
            $this->pageFrame();
            $this->header($project, $revision, $meta, ['number' => 1, 'count' => 1, 'title' => 'Single-line diagram']);
            $this->designData($summary, $meta);
            $this->schematic([], [], $editor);
            $this->notesAndLegend([]);
            $this->titleBlock($project, $revision, $graph, ['number' => 1, 'count' => 1, 'title' => 'Single-line diagram']);
            $this->pages[] = $this->commands;
        }

        return $this->buildPdf($this->pages);
    }

    /** @param array<string, mixed> $editor @param array<string, mixed> $sheet @return array<string, mixed> */
    private function editorForSheet(array $editor, array $sheet): array
    {
        $type = (string) ($sheet['type'] ?? 'single');
        $editor['_paper_type'] = $type;
        $editor['_paper_bounds'] = is_array($sheet['bounds'] ?? null) ? $sheet['bounds'] : null;
        $editor['_paper_transform'] = is_array($sheet['transform'] ?? null) ? $sheet['transform'] : null;
        $nodeIds = array_fill_keys(array_map('strval', is_array($sheet['node_ids'] ?? null) ? $sheet['node_ids'] : []), true);
        $positions = [];
        if ($type === 'professional_sld') {
            foreach (is_array($sheet['positions'] ?? null) ? $sheet['positions'] : [] as $id => $center) {
                if (! is_array($center)) continue;
                $w = max(72.0, (float) ($center['w'] ?? 120.0));
                $h = max(66.0, (float) ($center['h'] ?? 92.0));
                $positions[(string) $id] = [
                    'x' => (float) ($center['x'] ?? 0) - ($w / 2),
                    'y' => (float) ($center['y'] ?? 0) - ($h / 2),
                    'w' => $w,
                    'h' => $h,
                ];
            }
            $editor['manual_lines'] = [];
            $editor['manual_shapes'] = [];
            $editor['annotations'] = [];
            $editor['routes'] = [];
        } elseif ($type === 'catalog') {
            foreach (is_array($sheet['positions'] ?? null) ? $sheet['positions'] : [] as $id => $center) {
                if (! is_array($center)) continue;
                $positions[(string) $id] = [
                    'x' => (float) ($center['x'] ?? 0) - 68.0,
                    'y' => (float) ($center['y'] ?? 0) - 63.0,
                    'w' => 144.0,
                    'h' => 120.0,
                ];
            }
            $editor['manual_lines'] = [];
            $editor['manual_shapes'] = [];
        } else {
            foreach (is_array($editor['positions'] ?? null) ? $editor['positions'] : [] as $id => $position) {
                if (isset($nodeIds[(string) $id]) && is_array($position)) $positions[(string) $id] = $position;
            }
            if (is_array($sheet['bounds'] ?? null)) {
                $editor['manual_lines'] = $this->filterLinesToBounds(is_array($editor['manual_lines'] ?? null) ? $editor['manual_lines'] : [], $sheet['bounds']);
                $editor['manual_shapes'] = $this->filterShapesToBounds(is_array($editor['manual_shapes'] ?? null) ? $editor['manual_shapes'] : [], $sheet['bounds']);
            }
        }
        $editor['positions'] = $positions;
        return $editor;
    }

    /** @param array<int, mixed> $lines @param array<string, float> $bounds @return array<int, mixed> */
    private function filterLinesToBounds(array $lines, array $bounds): array
    {
        $inside = static fn (float $x, float $y): bool => $x >= $bounds['left'] && $x <= $bounds['right'] && $y >= $bounds['top'] && $y <= $bounds['bottom'];
        return array_values(array_filter($lines, static fn (mixed $line): bool => is_array($line) && (
            $inside((float) ($line['x1'] ?? 0), (float) ($line['y1'] ?? 0))
            || $inside((float) ($line['x2'] ?? 0), (float) ($line['y2'] ?? 0))
        )));
    }

    /** @param array<int, mixed> $shapes @param array<string, float> $bounds @return array<int, mixed> */
    private function filterShapesToBounds(array $shapes, array $bounds): array
    {
        return array_values(array_filter($shapes, static function (mixed $shape) use ($bounds): bool {
            if (! is_array($shape)) return false;
            $x = (float) ($shape['x'] ?? 0); $y = (float) ($shape['y'] ?? 0);
            $w = (float) ($shape['w'] ?? 0); $h = (float) ($shape['h'] ?? 0);
            return ! (($x + $w) < $bounds['left'] || $x > $bounds['right'] || ($y + $h) < $bounds['top'] || $y > $bounds['bottom']);
        }));
    }

    /** @param array<string, mixed> $graph */
    private function professionalPdfMainSld(array $graph): void
    {
        $summary = is_array(data_get($graph, 'meta.summary')) ? data_get($graph, 'meta.summary') : [];
        $nodes = collect(is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [])->keyBy('id');
        $navy = [0.05, 0.18, 0.24];
        $dc = [0.73, 0.22, 0.22];
        $ac = [0.66, 0.42, 0.15];
        $pe = [0.18, 0.50, 0.34];
        $measure = [0.25, 0.55, 0.58];
        $control = [0.42, 0.32, 0.66];

        $this->text(46, 620, 'PROFESSIONAL POWER TOPOLOGY - METER OUTSIDE PRIMARY FEEDER', 6.2, 'F2', [0.25, 0.42, 0.48]);
        $this->pdfSectionBox(46, 270, 720, 330, 'PV STRING, MPPT AND INVERTER FEEDERS');
        $this->pdfSectionBox(780, 270, self::WIDTH - 826, 330, 'AC COLLECTION, PCC ISOLATION AND PARALLEL SPD');

        $branchYs = [505.0, 365.0];
        foreach ($branchYs as $index => $y) {
            $suffix = sprintf('%02d', $index + 1);
            $this->rect(70, $y - 38, 110, 76, [0.98, 0.99, 0.99], true);
            $this->rect(70, $y - 38, 110, 76, $navy, false, 0.7);
            foreach ([-18, 0, 18] as $offset) $this->line(82, $y + $offset, 165, $y + $offset, $dc, 1.0);
            $this->text(125, $y + 48, 'STRINGS '.(($index * 3) + 1).'-'.(($index * 3) + 3), 5.5, 'F3', $navy, 'center');
            $this->text(125, $y - 54, '3 x 16 MODULES', 5.1, 'F2', $navy, 'center');

            $this->line(180, $y, 220, $y, $dc, 1.8);
            $this->rect(220, $y - 25, 86, 50, [1, 1, 1], true);
            $this->rect(220, $y - 25, 86, 50, $navy, false, 0.7);
            $this->text(263, $y + 5, 'QS-DC-'.$suffix, 5.4, 'F3', $navy, 'center');
            $this->text(263, $y - 12, '3 x 2P CH.', 4.6, 'F2', $navy, 'center');
            $this->line(306, $y, 350, $y, $dc, 1.8);

            $this->rect(350, $y - 34, 105, 68, [0.97, 0.99, 0.99], true);
            $this->rect(350, $y - 34, 105, 68, $navy, false, 0.9);
            $this->text(402.5, $y + 8, 'INV-'.$suffix, 7.0, 'F3', $navy, 'center');
            $this->text(402.5, $y - 13, '25 kW / 26.4 kWp', 4.8, 'F2', $navy, 'center');
            $this->line(455, $y, 500, $y, $ac, 2.0);

            foreach ([[500, 'QF-AC-'.$suffix, '50 A'], [590, 'QS-AC-'.$suffix, '50 A']] as [$x, $tag, $rating]) {
                $this->rect($x, $y - 22, 72, 44, [1, 1, 1], true);
                $this->rect($x, $y - 22, 72, 44, $navy, false, 0.7);
                $this->text($x + 36, $y + 5, $tag, 5.0, 'F3', $navy, 'center');
                $this->text($x + 36, $y - 11, $rating, 4.5, 'F2', $navy, 'center');
            }
            $this->line(572, $y, 590, $y, $ac, 2.0);
            $this->line(662, $y, 720, $y, $ac, 2.0);
            $this->line(720, $y, 720, 435, $ac, 2.0);
            $this->circle(720, $y, 3.4, $ac, $ac, 0.5);

            // Three-channel DC SPD is a parallel shunt, never a series device.
            $this->line(330, $y, 330, $y - 55, $dc, 1.0);
            $this->rect(315, $y - 82, 30, 24, [1, 1, 1], true);
            $this->rect(315, $y - 82, 30, 24, $navy, false, 0.6);
            $this->text(330, $y - 98, 'SPD-DC-'.$suffix, 4.6, 'F3', $navy, 'center');
            $this->line(330, $y - 82, 330, 292, $pe, 1.0);
        }

        // Main plant feeder. The meter and relay are secondary instruments below.
        $mainY = 435.0;
        $this->circle(720, $mainY, 4.0, $ac, $ac, 0.5);
        $this->line(720, $mainY, 805, $mainY, $ac, 2.2);
        $chain = [
            [805, 'ACCB-01', '125 A BUS'],
            [875, 'QF-PCC-01', '100 A MAIN CB'],
            [945, 'CT-01', '3 CT / 2 CORE'],
            [1015, 'QS-PCC-01', '100 A LOCKABLE'],
            [1090, 'GRID-01', 'UTILITY PCC'],
        ];
        foreach ($chain as [$x, $tag, $label]) {
            $this->text($x, $mainY + 19, $tag, 5.2, 'F3', $navy, 'center');
            $this->text($x, $mainY - 19, $label, 4.5, 'F2', $navy, 'center');
        }
        $this->line(805, $mainY, 1115, $mainY, $ac, 2.2);
        $this->leaderArrow(1115, $mainY, 1090, $mainY, $ac);

        // Protected parallel AC SPD branch.
        $this->line(805, $mainY, 805, 348, $ac, 1.5);
        $this->rect(780, 360, 50, 28, [1, 1, 1], true);
        $this->rect(780, 360, 50, 28, $navy, false, 0.6);
        $this->text(805, 371, 'QF-SPD', 4.6, 'F3', $navy, 'center');
        $this->rect(790, 320, 30, 24, [1, 1, 1], true);
        $this->rect(790, 320, 30, 24, $navy, false, 0.6);
        $this->text(826, 327, 'SPD-AC-01', 4.7, 'F3', $navy);
        $this->line(805, 320, 805, 292, $pe, 1.1);

        // CT-operated meter and independent hardwired reverse-power relay.
        foreach ([[875, 'MTR-01', 'POWER METER'], [1000, 'RPR-01', 'REVERSE-POWER RELAY']] as [$x, $tag, $label]) {
            $this->rect($x - 48, 320, 96, 48, [0.98, 0.99, 0.99], true);
            $this->rect($x - 48, 320, 96, 48, $navy, false, 0.7);
            $this->text($x, 349, $tag, 5.8, 'F3', $navy, 'center');
            $this->text($x, 333, $label, 4.3, 'F2', $navy, 'center');
        }
        $this->dashedLine(945, $mainY - 6, 945, 382, $measure, 1.0, [4, 3]);
        $this->dashedLine(945, 382, 875, 368, $measure, 1.0, [4, 3]);
        $this->dashedLine(945, 382, 1000, 368, $measure, 1.0, [4, 3]);
        $this->line(1000, 320, 875, 304, [0.75, 0.20, 0.20], 1.5);
        $this->leaderArrow(875, 304, 1000, 320, [0.75, 0.20, 0.20]);
        $this->text(936, 298, 'HARDWIRED TRIP TO QF-PCC', 4.4, 'F2', [0.75, 0.20, 0.20], 'center');

        // Common PE reference.
        $this->dashedLine(70, 292, 1115, 292, $pe, 1.2, [5, 3]);
        $this->text(72, 280, 'PE-BUS-01 - CONDUCTOR SIZES, EARTHING SYSTEM AND FAULT LOOP TBC', 4.8, 'F2', $pe);

        $this->pdfSectionBox(46, 125, self::WIDTH - 92, 120, 'DRAWING BASIS / RELEASE LIMITS');
        $notes = [
            '1. Main feeder: ACCB-01 to QF-PCC-01, CT-01, lockable QS-PCC-01 and GRID-01. MTR-01 is not in series.',
            '2. DC and AC SPDs are parallel branches to PE. Final Uc/Ucpv, Up, In, Imax, backup protection and lead lengths remain TBC.',
            '3. One string is assigned to each MPPT. External gPV fuses are not asserted until product reverse-current data justify them.',
            '4. RPR-01 provides a preliminary independent hardwired export-trip path. Fail-safe philosophy, CT/VT design, trip supervision and utility approval remain TBC.',
            '5. Internal review only. Product models, fault level, AC earthing system, cable installation, checker and approver remain open.',
        ];
        foreach ($notes as $i => $note) $this->text(62, 216 - ($i * 18), $this->shorten($note, 164), 5.1, 'F1', [0.18, 0.31, 0.37]);
    }

    /** @param array<string, mixed> $graph */
    private function professionalPdfStringSchedule(array $graph): void
    {
        $summary = is_array(data_get($graph, 'meta.summary')) ? data_get($graph, 'meta.summary') : [];
        $groups = is_array($summary['string_groups'] ?? null) ? $summary['string_groups'] : [];
        $rows = [];
        foreach ($groups as $group) {
            foreach (is_array($group['strings'] ?? null) ? $group['strings'] : [] as $string) {
                $rows[] = [
                    (string) ($group['inverter_tag'] ?? '-'),
                    (string) ($string['mppt_label'] ?? '-'),
                    (string) ($string['tag'] ?? '-'),
                    (string) ((int) ($string['modules'] ?? 0)),
                    $this->number($string['dc_kw'] ?? null, 2),
                    $this->number($string['voc_cold_v'] ?? null, 1),
                    $this->number($string['vmp_hot_v'] ?? null, 1),
                    'PRELIM CALC PASS',
                ];
            }
        }
        $this->text(46, 687, 'MPPT AND STRING SCHEDULE', 6.2, 'F2', [0.25, 0.42, 0.48]);
        $this->pdfTable(
            46, 675, self::WIDTH - 92,
            ['INVERTER', 'MPPT', 'STRING', 'MODULES / STRING', 'DC CAP. kWp', 'VOC COLD V', 'VMP HOT V', 'DATA STATUS'],
            [0.10, 0.10, 0.10, 0.14, 0.12, 0.13, 0.12, 0.19],
            $rows,
            23.0,
            19.0,
        );

        $boxY = 350.0;
        $boxH = 165.0;
        $basisX = 46.0;
        $basisW = 340.0;
        $mpptX = 398.0;
        $mpptW = 380.0;
        $equipmentX = 790.0;
        $equipmentW = self::WIDTH - 46 - $equipmentX;

        $this->pdfSectionBox($basisX, $boxY, $basisW, $boxH, 'SYSTEM DESIGN BASIS');
        $basis = [
            ['Installed PV', $this->number($summary['installed_pv_kw'] ?? null, 2).' kWp'],
            ['Module quantity', $this->number($summary['module_count'] ?? null, 0).' units'],
            ['String layout', (string) ($summary['string_layout'] ?? 'Pending')],
            ['Inverter AC', $this->number($summary['inverter_ac_kw'] ?? null, 2).' kW'],
            ['DC / AC ratio', $this->number($summary['dc_ac_ratio'] ?? null, 3)],
            ['Min ambient', $this->number($summary['minimum_site_temperature_c'] ?? null, 0).' C'],
            ['Max cell', $this->number($summary['maximum_cell_temperature_c'] ?? null, 0).' C'],
            ['Open data', 'EARTHING / FAULT LEVEL TBC'],
        ];
        foreach ($basis as $index => [$label, $value]) {
            $y = $boxY + $boxH - 39 - ($index * 15.3);
            $this->text($basisX + 12, $y, $label, 4.8, 'F2', [0.34, 0.47, 0.51]);
            $this->text($basisX + $basisW - 12, $y, $this->shorten($value, 34), 5.0, 'F3', [0.06, 0.18, 0.24], 'right');
        }

        $this->pdfSectionBox($mpptX, $boxY, $mpptW, $boxH, 'INVERTER / MPPT UTILIZATION');
        $assignments = is_array($summary['inverter_string_assignments'] ?? null) ? $summary['inverter_string_assignments'] : [];
        $first = $groups[0] ?? [];
        $second = $groups[1] ?? [];
        $firstAvailable = max(1, (int) data_get($assignments, '0.available_mppt_count', 1));
        $secondAvailable = max(1, (int) data_get($assignments, '1.available_mppt_count', $firstAvailable));
        $firstUsed = (int) ($first['used_mppt_count'] ?? 0);
        $secondUsed = (int) ($second['used_mppt_count'] ?? 0);
        $utilizationRows = [
            ['INVERTER', (string) ($first['inverter_tag'] ?? 'INV-01'), (string) ($second['inverter_tag'] ?? 'INV-02')],
            ['ASSIGNED DC', $this->number($first['assigned_dc_kw'] ?? null, 2).' kWp', $this->number($second['assigned_dc_kw'] ?? null, 2).' kWp'],
            ['RATED AC', $this->number($summary['inverter_unit_ac_kw'] ?? null, 2).' kW', $this->number($summary['inverter_unit_ac_kw'] ?? null, 2).' kW'],
            ['STRINGS', (string) count(is_array($first['strings'] ?? null) ? $first['strings'] : []), (string) count(is_array($second['strings'] ?? null) ? $second['strings'] : [])],
            ['MPPT USED', $firstUsed.' / '.$firstAvailable, $secondUsed.' / '.$secondAvailable],
            ['SPARE MPPT', (string) max(0, $firstAvailable - $firstUsed), (string) max(0, $secondAvailable - $secondUsed)],
            ['DC / AC RATIO', $this->number($first['dc_ac_ratio'] ?? $summary['dc_ac_ratio'] ?? null, 3), $this->number($second['dc_ac_ratio'] ?? $summary['dc_ac_ratio'] ?? null, 3)],
            ['STATUS', 'PRELIM / PRODUCT TBC', 'PRELIM / PRODUCT TBC'],
        ];
        $labelW = 132.0;
        $valueW = ($mpptW - $labelW) / 2;
        foreach ($utilizationRows as $index => $row) {
            $top = $boxY + $boxH - 25 - ($index * 17.25);
            $this->line($mpptX, $top, $mpptX + $mpptW, $top, [0.52, 0.61, 0.64], 0.4);
            $this->line($mpptX + $labelW, $boxY, $mpptX + $labelW, $boxY + $boxH - 25, [0.52, 0.61, 0.64], 0.4);
            $this->line($mpptX + $labelW + $valueW, $boxY, $mpptX + $labelW + $valueW, $boxY + $boxH - 25, [0.52, 0.61, 0.64], 0.4);
            $textY = $top - 11.5;
            $this->text($mpptX + 8, $textY, $row[0], 4.3, 'F2', [0.34, 0.47, 0.51]);
            $this->text($mpptX + $labelW + ($valueW / 2), $textY, $this->shorten($row[1], 21), 4.5, 'F3', [0.06, 0.18, 0.24], 'center');
            $this->text($mpptX + $labelW + $valueW + ($valueW / 2), $textY, $this->shorten($row[2], 21), 4.5, 'F3', [0.06, 0.18, 0.24], 'center');
        }

        $this->pdfSectionBox($equipmentX, $boxY, $equipmentW, $boxH, 'EQUIPMENT SCHEDULE');
        $moduleCount = (float) ($summary['module_count'] ?? 0);
        $moduleWp = $moduleCount > 0 ? (((float) ($summary['installed_pv_kw'] ?? 0) * 1000) / $moduleCount) : 0;
        $equipmentRows = [
            ['COMPONENT', 'PV ARRAY', 'INVERTER'],
            ['UNIT RATING', $this->number($moduleWp, 0).' Wp', $this->number($summary['inverter_unit_ac_kw'] ?? null, 2).' kW'],
            ['QUANTITY', $this->number($summary['module_count'] ?? null, 0).' units', $this->number($summary['inverter_count'] ?? null, 0).' units'],
            ['TOTAL CAP.', $this->number($summary['installed_pv_kw'] ?? null, 2).' kWp', $this->number($summary['inverter_ac_kw'] ?? null, 2).' kW'],
            ['DESIGN', 'PRELIM / PRODUCT TBC', 'PRELIM / PRODUCT TBC'],
            ['SELECTION', 'PRODUCT TBC', 'PRODUCT TBC'],
        ];
        $rowH = 21.5;
        $equipmentLabelW = 98.0;
        $equipmentValueW = ($equipmentW - $equipmentLabelW) / 2;
        foreach ($equipmentRows as $index => $row) {
            $top = $boxY + $boxH - 25 - ($index * $rowH);
            $this->line($equipmentX, $top, $equipmentX + $equipmentW, $top, [0.52, 0.61, 0.64], 0.45);
            $this->line($equipmentX + $equipmentLabelW, $boxY, $equipmentX + $equipmentLabelW, $boxY + $boxH - 25, [0.52, 0.61, 0.64], 0.45);
            $this->line($equipmentX + $equipmentLabelW + $equipmentValueW, $boxY, $equipmentX + $equipmentLabelW + $equipmentValueW, $boxY + $boxH - 25, [0.52, 0.61, 0.64], 0.45);
            $textY = $top - 14;
            $this->text($equipmentX + 7, $textY, $row[0], 4.3, 'F2', [0.34, 0.47, 0.51]);
            $this->text($equipmentX + $equipmentLabelW + ($equipmentValueW / 2), $textY, $this->shorten($row[1], 20), 4.4, 'F3', [0.06, 0.18, 0.24], 'center');
            $this->text($equipmentX + $equipmentLabelW + $equipmentValueW + ($equipmentValueW / 2), $textY, $this->shorten($row[2], 20), 4.4, 'F3', [0.06, 0.18, 0.24], 'center');
        }

        $this->pdfLineLegend(46, 126, self::WIDTH - 92, 204);
    }

    /** @param array<string, mixed> $graph */
    private function professionalPdfControlSchematic(array $graph): void
    {
        $navy = [0.05, 0.18, 0.24];
        $teal = [0.04, 0.56, 0.53];
        $orange = [0.68, 0.42, 0.14];
        $purple = [0.43, 0.32, 0.66];
        $red = [0.72, 0.22, 0.22];
        $this->text(46, 687, 'ZERO-EXPORT METERING, CONTROL AND PRELIMINARY EXPORT TRIP', 6.4, 'F2', [0.25, 0.42, 0.48]);
        $this->rect(46, 395, self::WIDTH - 92, 278, [1, 1, 1], true);
        $this->rect(46, 395, self::WIDTH - 92, 278, $navy, false, 0.7);

        // Main feeder reference. The meter is deliberately outside this path.
        $y = 590.0;
        $this->text(64, 644, 'MAIN AC POWER PATH', 6.0, 'F2', $navy);
        $this->line(86, $y, 1100, $y, $orange, 2.2);
        $devices = [
            [160, 'ACCB-01', 'AC COMBINER'],
            [350, 'QF-PCC-01', 'MAIN PCC CB'],
            [540, 'CT-01', '3-PHASE CT SET'],
            [730, 'QS-PCC-01', 'LOCKABLE PCC DISCONNECT'],
            [960, 'GRID-01', 'PCC / UTILITY'],
        ];
        foreach ($devices as [$x, $tag, $label]) {
            $this->circle($x, $y, 6, [1, 1, 1], $navy, 1.0);
            $this->text($x, $y + 20, $tag, 6.0, 'F3', $navy, 'center');
            $this->text($x, $y - 23, $label, 5.2, 'F2', $navy, 'center');
        }
        $this->text(805, $y + 34, 'UTILITY-ACCESSIBLE / LOCKABLE', 5.0, 'F2', $orange, 'center');

        // Secondary metering and hardwired protection.
        $this->pdfSectionBox(72, 420, 500, 132, 'INDEPENDENT PROTECTION CHANNEL');
        $this->pdfSectionBox(592, 420, 526, 132, 'MONITORING AND ACTIVE-POWER CONTROL');
        $this->line(540, $y - 6, 540, 526, $purple, 1.4);
        $this->line(540, 526, 320, 526, $purple, 1.4);
        $this->line(540, 526, 820, 526, $purple, 1.4);
        $this->text(430, 534, 'PROTECTION CT CORES', 4.8, 'F2', $purple, 'center');
        $this->text(680, 534, 'METERING CT CORES', 4.8, 'F2', $purple, 'center');

        $this->rect(220, 458, 170, 48, [0.97, 0.99, 0.99], true);
        $this->rect(220, 458, 170, 48, $navy, false, 0.8);
        $this->text(305, 487, 'RPR-01', 7.0, 'F3', $navy, 'center');
        $this->text(305, 471, 'REVERSE-POWER RELAY', 5.3, 'F2', $navy, 'center');
        $this->line(390, 482, 462, 482, $red, 1.8);
        $this->leaderArrow(462, 482, 390, 482, $red);
        $this->text(426, 492, 'HARDWIRED TRIP', 4.8, 'F2', $red, 'center');
        $this->rect(462, 458, 92, 48, [0.97, 0.99, 0.99], true);
        $this->rect(462, 458, 92, 48, $navy, false, 0.8);
        $this->text(508, 487, 'QF-PCC-01', 6.0, 'F3', $navy, 'center');
        $this->text(508, 471, 'TRIP COIL · QF-PCC-01:T', 4.5, 'F2', $navy, 'center');

        $this->rect(640, 458, 142, 48, [0.97, 0.99, 0.99], true);
        $this->rect(640, 458, 142, 48, $navy, false, 0.8);
        $this->text(711, 487, 'MTR-01', 7.0, 'F3', $navy, 'center');
        $this->text(711, 471, 'CT-OPERATED METER', 5.0, 'F2', $navy, 'center');
        $this->rect(820, 458, 126, 48, [0.97, 0.99, 0.99], true);
        $this->rect(820, 458, 126, 48, $navy, false, 0.8);
        $this->text(883, 487, 'PPC-01', 7.0, 'F3', $navy, 'center');
        $this->text(883, 471, 'PLANT CONTROLLER', 5.0, 'F2', $navy, 'center');
        $this->line(782, 482, 820, 482, $purple, 1.5);
        $this->leaderArrow(820, 482, 782, 482, $purple);
        $this->text(801, 492, 'MODBUS', 4.6, 'F2', $purple, 'center');
        $this->rect(974, 458, 126, 48, [0.97, 0.99, 0.99], true);
        $this->rect(974, 458, 126, 48, $navy, false, 0.8);
        $this->text(1037, 487, 'INV-01 / 02', 6.2, 'F3', $navy, 'center');
        $this->text(1037, 471, 'POWER LIMIT INPUT', 4.9, 'F2', $navy, 'center');
        $this->line(946, 482, 974, 482, $teal, 1.6);
        $this->leaderArrow(974, 482, 946, 482, $teal);

        $this->pdfSectionBox(46, 135, self::WIDTH - 92, 230, 'OPERATING PHILOSOPHY AND OPEN ENGINEERING DATA');
        $notes = [
            '1. Main feeder passes through CT-01 and QF/QS PCC devices. MTR-01 is not in the primary power path.',
            '2. RPR-01 uses independent CT and voltage-reference inputs and trips QF-PCC-01 by hardwired contact.',
            '3. Loss of Modbus or inverter-control communication cannot defeat the independent reverse-power trip channel.',
            '4. Final CT ratio, class, burden, test/shorting terminals, trip logic, delay and fail-safe state remain TBC.',
            '5. PCC fault level, AC earthing arrangement, utility interconnection requirements and approved product models remain TBC.',
            '6. This sheet is for internal review only and is not approved for construction or energisation.',
        ];
        foreach ($notes as $i => $note) {
            $this->text(64, 327 - ($i * 29), $this->shorten($note, 166), 5.5, 'F1', [0.18, 0.31, 0.37]);
        }
    }

    /** @param array<string, mixed> $graph */
    private function professionalPdfCableSchedule(array $graph, array $sheet): void
    {
        $allCables = is_array(data_get($graph, 'engineering_controller.cable_schedule')) ? data_get($graph, 'engineering_controller.cable_schedule') : [];
        $start = max(0, (int) ($sheet['schedule_start'] ?? 0));
        $limit = max(1, (int) ($sheet['schedule_limit'] ?? count($allCables)));
        $cables = array_slice($allCables, $start, $limit);
        $tags = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! isset($node['id'])) continue;
            $tags[(string) $node['id']] = (string) ($node['tag'] ?? strtoupper((string) $node['id']));
        }
        $rows = array_map(function (array $row) use ($tags): array {
            $from = (string) ($row['from'] ?? '-');
            $to = (string) ($row['to'] ?? '-');
            $fromLabel = (string) ($row['from_display'] ?? ($tags[$from] ?? $from));
            $toLabel = (string) ($row['to_display'] ?? ($tags[$to] ?? $to));
            return [
                (string) ($row['wire_id'] ?? '-'),
                $fromLabel.' > '.$toLabel,
                (string) ($row['cable_type'] ?? '-'),
                $this->professionalPdfCableCoreSize($row),
                $this->meaningfulEngineeringText((string) ($row['voltage_class'] ?? '')) ? (string) $row['voltage_class'] : 'TBC',
                $this->professionalPdfCableLength($row['length_m'] ?? null),
            ];
        }, $cables);
        $this->text(46, 687, 'CABLE AND INTERCONNECTION SCHEDULE - INDIVIDUAL CIRCUIT CROSS-REFERENCE', 6.2, 'F2', [0.25, 0.42, 0.48]);
        $this->pdfTable(46, 675, self::WIDTH - 92, ['CIRCUIT', 'FROM - TO', 'CABLE TYPE', 'SIZE / PAIR', 'V CLASS', 'LENGTH'], [0.15, 0.28, 0.21, 0.17, 0.11, 0.08], $rows, 22.0, 17.0);
        $this->pdfSectionBox(46, 130, self::WIDTH - 92, 120, 'CABLE-SCHEDULE CONTROL NOTES');
        $notes = [
            '1. Each PV string remains individually traceable from string tag to isolator channel and inverter MPPT input.',
            '2. TBC marks unapproved route, cable construction, size, terminal or installation method. Do not infer missing values.',
            '3. Ethernet uses 4 twisted pairs. RS485 uses a shielded twisted pair. Generic 2C Ethernet notation is not used.',
            '4. Confirm the AC L1/L2/L3/N/PE arrangement after selecting the project AC earthing system.',
        ];
        foreach ($notes as $i => $note) $this->text(62, 222 - ($i * 24), $this->shorten($note, 168), 5.3, 'F1', [0.18, 0.31, 0.37]);
    }

    /** @param array<string, mixed> $graph */
    private function professionalPdfProtectionSchedule(array $graph): void
    {
        $protection = is_array(data_get($graph, 'engineering_controller.protection_schedule')) ? data_get($graph, 'engineering_controller.protection_schedule') : [];
        $tags = [];
        $nodeMap = [];
        foreach (is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [] as $node) {
            if (! is_array($node) || ! isset($node['id'])) continue;
            $nodeId = (string) $node['id'];
            $tags[$nodeId] = (string) ($node['tag'] ?? strtoupper($nodeId));
            $nodeMap[$nodeId] = $node;
        }
        $rows = [];
        foreach ($protection as $row) {
            $nodeId = (string) ($row['node_id'] ?? '-');
            $rows[] = array_merge([$tags[$nodeId] ?? strtoupper($nodeId), (string) ($row['label'] ?? '-')], $this->professionalPdfProtectionCells($row, $nodeMap[$nodeId] ?? []));
        }
        $this->text(46, 687, 'PROTECTION, METERING AND ENGINEERING DATA-GAP SCHEDULE', 6.2, 'F2', [0.25, 0.42, 0.48]);
        $this->pdfTable(46, 675, self::WIDTH - 92, ['DEVICE TAG', 'DEVICE', 'CONFIGURATION', 'RATING', 'SYSTEM V', 'FINAL DUTY / DATA GAP'], [0.13, 0.16, 0.20, 0.12, 0.12, 0.27], $rows, 23.0, 18.5);
        $this->pdfSectionBox(46, 130, self::WIDTH - 92, 250, 'ENGINEERING DATA GAPS - RELEASE BLOCKERS');
        $gaps = [
            ['PCC FAULT LEVEL', 'TBC', 'Required before final QF-PCC and branch-breaker Icu/Ics selection.'],
            ['AC EARTHING SYSTEM', 'TBC', 'Select TN-S, TN-C-S, TT or other project arrangement before neutral/PE and RCD decisions.'],
            ['PCC MAIN DISCONNECT', 'PROVISIONAL', 'QF-PCC-01 and lockable QS-PCC-01 shown; utility approval and ratings remain pending.'],
            ['EXPORT-TRIP PATH', 'PROVISIONAL', 'Independent hardwired export-trip path shown; CT, voltage reference, settings, supply, supervision and fail-safe philosophy remain pending.'],
            ['PV STRING FUSES', 'NOT SHOWN', 'One string per MPPT. External gPV fuses require final product/manual and reverse-current justification.'],
            ['PRODUCT MODELS', 'TBC', 'Module, inverter, CT, meter, relay, breaker, isolator and SPD datasheets are not approved.'],
            ['CABLE ROUTES / VD', 'TBC', 'Do not release precise voltage-drop values until final route lengths and installation methods are confirmed.'],
            ['ENERGY YIELD', 'REPORT TBC', 'Annual energy belongs to a referenced yield study with site, weather, orientation, shading and loss inputs.'],
        ];
        foreach ($gaps as $i => [$item, $status, $note]) {
            $yy = 345 - ($i * 26);
            $this->text(62, $yy, $item, 5.3, 'F2', [0.18, 0.31, 0.37]);
            $this->text(275, $yy, $status, 5.3, 'F3', $status === 'TBC' ? [0.78, 0.36, 0.18] : [0.04, 0.50, 0.47]);
            $this->text(390, $yy, $this->shorten($note, 118), 5.2, 'F1', [0.18, 0.31, 0.37]);
        }
    }

    /** @param array<string, mixed> $row */
    private function professionalPdfCableCoreSize(array $row): string
    {
        $display = trim((string) ($row['size_display'] ?? ''));
        if ($display !== '') return str_replace('²', '2', $display);
        $cores = (int) ($row['cores'] ?? 0);
        $size = trim((string) ($row['cable_size'] ?? ''));
        $runs = max(1, (int) ($row['parallel_runs'] ?? 1));
        $prefix = $runs > 1 ? $runs.' runs - ' : '';
        $isNumericSize = $size !== '' && preg_match('/^[0-9.,x×+\-\s]+$/u', $size) === 1;
        if (! $isNumericSize) return $prefix.($cores > 0 ? $cores.'C x ' : '').'TBC';

        $size = str_replace(['×', ','], ['x', '.'], $size);
        if (! str_contains(strtolower($size), 'mm')) $size .= ' mm2';
        return $prefix.($cores > 0 ? $cores.'C x ' : '').$size;
    }

    private function professionalPdfCableLength(mixed $value): string
    {
        if (! is_numeric($value) || (float) $value <= 0) return 'TBC';
        $decimals = floor((float) $value) === (float) $value ? 0 : 1;
        return $this->number((float) $value, $decimals).' m';
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $node @return array{0:string,1:string,2:string,3:string} */
    private function professionalPdfProtectionCells(array $row, array $node): array
    {
        $label = mb_strtolower(trim((string) ($row['label'] ?? '')));
        $designation = trim((string) ($row['designation'] ?? ''));
        $rawRating = $row['rating'] ?? null;
        $rating = is_numeric($rawRating) && (float) $rawRating > 0
            ? $this->number($rawRating, floor((float) $rawRating) !== (float) $rawRating ? 2 : 0)
            : '—';
        $unit = strtoupper(trim((string) ($row['unit'] ?? '')));
        $explicitRating = trim((string) ($row['rating_display'] ?? ''));
        $nodeVoltage = is_numeric($node['rated_voltage'] ?? null) ? (float) $node['rated_voltage'] : null;
        $isDc = str_contains($label, 'dc') || str_contains($unit, 'VDC');
        $voltage = $nodeVoltage !== null && $nodeVoltage > 0
            ? $this->number($nodeVoltage, 0).' '.($isDc ? 'VDC' : 'VAC')
            : (str_contains($unit, 'V') && $rating !== '—' ? $rating.' '.$unit : 'TBC');
        $primaryRating = $explicitRating !== '' ? $explicitRating : ($rating !== '—' ? trim($rating.' '.$unit) : 'TBC');

        if (str_contains($label, 'dc spd')) {
            $ucpv = preg_match('/([0-9]{3,4})\s*Vdc/i', $designation, $match) === 1 ? 'Ucpv '.$match[1].' VDC' : 'Ucpv TBC';
            return ['TYPE 2 / PARALLEL', $ucpv, $voltage, 'In / Imax TBC'];
        }
        if (str_contains($label, 'ac spd')) {
            $configuration = str_contains(strtoupper($designation), '3P+N') ? 'TYPE 2 / 3P+N' : 'TYPE 2 / CONFIG TBC';
            return [$configuration, $primaryRating, $voltage, 'Uc / In / Imax TBC'];
        }
        if (str_contains($label, 'pcc circuit breaker') || str_contains($label, 'main circuit breaker')) return ['3P MAIN / HARDWIRED TRIP', $primaryRating, $voltage, 'Icu / Ics / trip settings TBC'];
        if (str_contains($label, 'circuit breaker')) return ['3P BRANCH', $primaryRating, $voltage, 'Icu / Ics / trip TBC'];
        if (str_contains($label, 'pcc disconnect')) return ['LOCKABLE / UTILITY ACCESS', $primaryRating, $voltage, 'Poles / util. category TBC'];
        if (str_contains($label, 'isolator')) return [$isDc ? '3 x 2P DC CHANNELS' : 'AC LOAD-BREAK', $primaryRating, $voltage, 'Poles / util. category TBC'];
        if (str_contains($label, 'reverse-power') || str_contains($label, 'export relay')) return ['ANSI 32 / HARDWIRED TRIP', $primaryRating, $voltage, 'CT/VT / setting / delay TBC'];
        if (str_contains($label, 'fuse')) return ['FUSE IF REQUIRED', $primaryRating, $voltage, 'Justification / breaking cap. TBC'];

        return [$this->shorten($designation !== '' ? $designation : 'TBC', 22), $primaryRating, $voltage, 'COORDINATION TBC'];
    }

    /** @param array<int, string> $headers @param array<int, float> $fractions @param array<int, array<int, string>> $rows */
    private function pdfTable(float $x, float $topY, float $w, array $headers, array $fractions, array $rows, float $headerH, float $rowH): void
    {
        if ($rows === []) $rows = [array_fill(0, count($headers), '-')];
        $h = $headerH + (count($rows) * $rowH);
        $bottom = $topY - $h;
        $this->rect($x, $bottom, $w, $h, [1, 1, 1], true);
        $this->rect($x, $bottom, $w, $h, [0.08, 0.23, 0.30], false, 0.65);
        $this->rect($x, $topY - $headerH, $w, $headerH, [0.91, 0.95, 0.95], true);
        $widths = array_map(static fn (float $fraction): float => $w * $fraction, $fractions);
        $cursor = $x;
        foreach ($headers as $index => $header) {
            if ($index > 0) $this->line($cursor, $bottom, $cursor, $topY, [0.52, 0.61, 0.64], 0.4);
            $this->text($cursor + ($widths[$index] / 2), $topY - ($headerH * 0.67), $header, 4.35, 'F2', [0.31, 0.45, 0.49], 'center');
            $cursor += $widths[$index];
        }
        foreach ($rows as $rowIndex => $row) {
            $rowTop = $topY - $headerH - ($rowIndex * $rowH);
            $rowBottom = $rowTop - $rowH;
            $this->line($x, $rowBottom, $x + $w, $rowBottom, [0.52, 0.61, 0.64], 0.35);
            $cursor = $x;
            foreach ($headers as $col => $_header) {
                $limit = max(8, (int) floor($widths[$col] / 4.1));
                $value = $this->shorten((string) ($row[$col] ?? '-'), $limit);
                $this->text($cursor + ($widths[$col] / 2), $rowBottom + ($rowH * 0.36), $value, 4.05, 'F3', [0.09, 0.21, 0.27], 'center');
                $cursor += $widths[$col];
            }
        }
    }

    private function pdfSectionBox(float $x, float $y, float $w, float $h, string $title): void
    {
        $this->rect($x, $y, $w, $h, [1, 1, 1], true);
        $this->rect($x, $y, $w, $h, [0.08, 0.23, 0.30], false, 0.6);
        $this->rect($x, $y + $h - 25, $w, 25, [0.91, 0.95, 0.95], true);
        $this->text($x + 10, $y + $h - 16, $title, 5.5, 'F2', [0.31, 0.45, 0.49]);
    }

    private function pdfLineLegend(float $x, float $y, float $w, float $h): void
    {
        $this->pdfSectionBox($x, $y, $w, $h, 'DRAWING LEGEND, ABBREVIATIONS AND SYMBOL REFERENCE');
        $items = [
            ['DC POWER', [0.78, 0.30, 0.30], null],
            ['AC POWER', [0.65, 0.42, 0.16], null],
            ['PE / GROUND', [0.19, 0.51, 0.35], [5, 3]],
            ['RS485 / COM', [0.45, 0.35, 0.66], [5, 3]],
            ['CONTROL', [0.33, 0.47, 0.72], [8, 3]],
        ];
        foreach ($items as $index => [$label, $color, $dash]) {
            $col = $index % 3;
            $row = intdiv($index, 3);
            $lx = $x + 28 + ($col * 350);
            $ly = $y + $h - 58 - ($row * 30);
            if ($dash !== null) $this->dashedLine($lx, $ly, $lx + 74, $ly, $color, 1.5, $dash);
            else $this->line($lx, $ly, $lx + 74, $ly, $color, 1.5);
            $this->text($lx + 88, $ly - 2, $label, 5.0, 'F2', [0.18, 0.31, 0.37]);
        }
        $symbols = [
            ['pv_array', 'PV ARRAY'], ['inverter', 'INVERTER'], ['dc_isolator', 'ISOLATOR'], ['ac_breaker', 'CIRCUIT BREAKER'],
            ['dc_fuse', 'FUSE IF REQUIRED'], ['dc_spd', 'SPD'], ['meter', 'ENERGY METER'], ['ground_rod', 'EARTH ELECTRODE'],
        ];
        foreach ($symbols as $index => [$kind, $label]) {
            $col = $index % 4;
            $row = intdiv($index, 4);
            $cx = $x + 40 + ($col * 265);
            $cy = $y + 78 - ($row * 38);
            $this->miniSymbol($kind, $cx, $cy);
            $this->text($cx + 26, $cy - 2, $label, 4.8, 'F2', [0.18, 0.31, 0.37]);
        }
        $this->text($x + 12, $y + 10, 'IEC 60617 functional symbol reference. Certification candidate; independent conformity assessment pending.', 4.7, 'F1', [0.25, 0.39, 0.44]);
    }

    private function pdfEngineeringNotes(float $x, float $y, float $w, float $h): void
    {
        $this->pdfSectionBox($x, $y, $w, $h, 'DRAWING NOTES / LINE LEGEND');
        $notes = [
            '1. Schedules use the linked calculation snapshot and controlled topology.',
            '2. TBC marks missing cable size, length, coordination, or duty data. Do not infer values.',
            '3. Verify cable routes, ampacity, voltage drop, protection coordination, and breaking capacity.',
            '4. No IEC certification claim is made. Independent conformity assessment is pending.',
        ];
        foreach ($notes as $index => $note) {
            $this->text($x + 14, $y + $h - 47 - ($index * 24), $this->shorten($note, 104), 5.0, 'F1', [0.18, 0.31, 0.37]);
        }
        $legend = [
            ['DC / PV cable', [0.78, 0.30, 0.30], null],
            ['AC cable', [0.65, 0.42, 0.16], null],
            ['Protective earth', [0.19, 0.51, 0.35], [5, 3]],
            ['RS485 communication', [0.45, 0.35, 0.66], [5, 3]],
        ];
        foreach ($legend as $index => [$label, $color, $dash]) {
            $ly = $y + 60 - ($index * 13);
            if ($dash !== null) $this->dashedLine($x + 14, $ly, $x + 75, $ly, $color, 1.3, $dash);
            else $this->line($x + 14, $ly, $x + 75, $ly, $color, 1.3);
            $this->text($x + 87, $ly - 2, $label, 4.6, 'F2', [0.18, 0.31, 0.37]);
        }
    }

    /** @param array<string, mixed> $sheet */
    private function overviewSchematic(array $sheet): void
    {
        $this->rect(46, 248, self::WIDTH - 92, 435, [0.75, 0.82, 0.84], false, 0.5);
        $this->text(58, 667, 'SYSTEM OVERVIEW - FUNCTIONAL GROUPS AND DETAIL REFERENCES', 5.7, 'F2', [0.31, 0.46, 0.52]);
        $groups = array_values(is_array($sheet['overview_groups'] ?? null) ? $sheet['overview_groups'] : []);
        if ($groups === []) {
            $this->text(self::WIDTH / 2, 470, 'No functional groups are available.', 10, 'F1', [0.42, 0.52, 0.55], 'center');
            return;
        }
        $count = count($groups);
        $gap = 22.0;
        $blockW = min(165.0, (self::WIDTH - 150 - (($count - 1) * $gap)) / max(1, $count));
        $startX = (self::WIDTH - (($blockW * $count) + ($gap * ($count - 1)))) / 2;
        $y = 430.0;
        foreach ($groups as $index => $group) {
            $x = $startX + ($index * ($blockW + $gap));
            $this->rect($x, $y, $blockW, 112, [0.985, 0.995, 0.995], true);
            $this->rect($x, $y, $blockW, 112, [0.06, 0.48, 0.46], false, 1.0);
            $this->rect($x, $y + 106, $blockW, 6, [0.04, 0.62, 0.58], true);
            $this->text($x + ($blockW / 2), $y + 72, strtoupper($this->shorten((string) ($group['title'] ?? 'System group'), 28)), 6.3, 'F2', [0.06, 0.18, 0.24], 'center');
            $this->text($x + ($blockW / 2), $y + 48, (string) ((int) ($group['count'] ?? 0)).' EQUIPMENT OBJECTS', 5.0, 'F3', [0.35, 0.48, 0.52], 'center');
            $sheets = implode(', ', array_map(static fn ($number): string => 'SLD-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), $group['detail_sheets'] ?? []));
            $this->text($x + ($blockW / 2), $y + 25, 'SEE '.($sheets !== '' ? $sheets : 'DETAIL SHEET'), 5.2, 'F2', [0.76, 0.42, 0.12], 'center');
            if ($index < $count - 1) {
                $x1 = $x + $blockW; $x2 = $x1 + $gap;
                $this->line($x1, $y + 56, $x2, $y + 56, [0.04, 0.55, 0.52], 1.2);
                $this->line($x2 - 6, $y + 60, $x2, $y + 56, [0.04, 0.55, 0.52], 1.2);
                $this->line($x2 - 6, $y + 52, $x2, $y + 56, [0.04, 0.55, 0.52], 1.2);
            }
        }
    }

    private function pageFrame(): void
    {
        $this->rect(0, 0, self::WIDTH, self::HEIGHT, [0.965, 0.98, 0.98], true);
        $this->rect(12, 12, self::WIDTH - 24, self::HEIGHT - 24, [0.08, 0.19, 0.25], false, 1.0);
        $this->rect(20, 20, self::WIDTH - 40, self::HEIGHT - 40, [0.78, 0.84, 0.85], false, 0.55);
    }

    /** @param array<string, mixed> $meta */
    private function header(Project $project, ProjectSldRevision $revision, array $meta, array $sheet = []): void
    {
        $y = 760.0;
        $this->rect(28, $y, self::WIDTH - 56, 58, [0.045, 0.15, 0.21], true);
        $this->rect(28, $y, self::WIDTH - 56, 4, [0.035, 0.63, 0.59], true);
        $this->line(195, $y + 13, 195, $y + 46, [0.28, 0.48, 0.53], 0.65);

        $this->text(48, $y + 37, 'NEXORIN', 16, 'F2', [1, 1, 1]);
        $controlVersion = (string) data_get($meta, 'document_control.version', '67L.4-FINAL');
        $issueLabel = strtoupper((string) data_get($meta, 'document_control.drawing.issue_label', 'DRAFT'));
        $this->text(48, $y + 20, 'SMART PV ENGINEERING - '.$controlVersion.' - '.$issueLabel, 5.9, 'F2', [0.55, 0.82, 0.8]);
        $this->text(218, $y + 35, $this->shorten(strtoupper((string) ($sheet['title'] ?? 'SINGLE-LINE DIAGRAM')), 68), 14, 'F2', [1, 1, 1]);

        $projectLine = trim(implode(' - ', array_filter([
            (string) ($project->name ?: 'Untitled project'),
            (string) ($meta['system_type'] ?? 'PV system'),
            (string) ($project->project_number ?: null),
        ])));
        $this->text(218, $y + 19, $this->shorten($projectLine, 95), 7.2, 'F1', [0.72, 0.82, 0.84]);

        $verified = (bool) ($meta['calculation_verified'] ?? false);
        $badgeColor = $verified ? [0.035, 0.59, 0.56] : [0.84, 0.52, 0.2];
        $badge = $verified ? 'MODEL DATA LINKED' : 'ENGINEERING DRAFT';
        $this->rect(965, $y + 17, 171, 27, $badgeColor, true);
        $this->circle(982, $y + 30.5, 3.2, [0.92, 1, 0.99], [0.92, 1, 0.99], 0.4);
        $this->text(997, $y + 27.8, $badge, 6.0, 'F2', [1, 1, 1]);
    }

    /** @param array<string, mixed> $summary @param array<string, mixed> $meta */
    private function designData(array $summary, array $meta): void
    {
        $items = [
            ['PV CAPACITY', $this->number($summary['installed_pv_kw'] ?? null, 2), 'kWp'],
            ['ARRAY LAYOUT', (string) ($summary['string_layout'] ?? 'Pending'), ''],
            ['INVERTER AC', $this->number($summary['inverter_ac_kw'] ?? null, 2), 'kW'],
            ['DC / AC', $this->number($summary['dc_ac_ratio'] ?? null, 3), 'ratio'],
            ['SUPPLY', strtoupper((string) ($summary['supply'] ?? $summary['phase'] ?? $meta['supply'] ?? 'Single phase')), ''],
        ];

        $x = 46.0;
        $y = 704.0;
        $total = self::WIDTH - 92;
        $cell = $total / count($items);

        foreach ($items as $index => [$label, $value, $unit]) {
            $this->rect($x, $y, $cell, 38, [0.985, 0.99, 0.99], true);
            $this->rect($x, $y, $cell, 38, [0.68, 0.76, 0.78], false, 0.45);
            $this->rect($x, $y, 3, 38, $index === 4 ? [0.9, 0.57, 0.22] : [0.035, 0.63, 0.59], true);
            $this->text($x + 12, $y + 25, $label, 5.4, 'F2', [0.35, 0.47, 0.52]);
            $this->text($x + 12, $y + 10, $this->shorten($value, 28), 9.2, 'F3', [0.04, 0.15, 0.22]);
            if ($unit !== '') {
                $this->text($x + $cell - 36, $y + 10, $unit, 4.8, 'F2', [0.38, 0.5, 0.54]);
            }
            $x += $cell;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @param array<int, mixed> $connections
     * @param array<string, mixed> $editor
     */
    private function schematic(array $nodes, array $connections, array $editor): void
    {
        $this->rect(38, 220, self::WIDTH - 76, 476, [0.68, 0.77, 0.80], false, 0.65);
        $this->text(50, 681, 'PRIMARY POWER TOPOLOGY · AS DRAWN', 6.1, 'F2', [0.26, 0.42, 0.49]);

        $manualLines = is_array($editor['manual_lines'] ?? null) ? $editor['manual_lines'] : [];
        $manualShapes = is_array($editor['manual_shapes'] ?? null) ? $editor['manual_shapes'] : [];
        $annotations = is_array($editor['annotations'] ?? null) ? $editor['annotations'] : [];
        $allRoutes = is_array($editor['routes'] ?? null) ? $editor['routes'] : [];
        $routes = [];
        foreach ($connections as $index => $connection) {
            if (! is_array($connection)) continue;
            foreach ([$connection['_id'] ?? null, $connection['id'] ?? null, $this->connectionId($connection, $index)] as $routeKey) {
                if (filled($routeKey) && isset($allRoutes[(string) $routeKey]) && is_array($allRoutes[(string) $routeKey])) {
                    $routes[(string) $routeKey] = $allRoutes[(string) $routeKey];
                    break;
                }
            }
        }
        if ($nodes === [] && $manualLines === [] && $manualShapes === [] && $annotations === []) {
            $this->text(self::WIDTH / 2, 470, 'No SLD equipment or drafting geometry is available.', 10, 'F1', [0.42, 0.52, 0.55], 'center');
            return;
        }

        $layout = $this->paperLayout(
            $nodes,
            is_array($editor['positions'] ?? null) ? $editor['positions'] : [],
            $manualLines,
            $manualShapes,
            $routes,
            $annotations,
            (string) ($editor['_paper_type'] ?? 'single'),
        );
        $this->deviceCenters = $layout['positions'];
        $transform = $layout['transform'];

        // Drafting geometry is intentionally rendered behind electrical objects.
        foreach ($manualShapes as $shape) {
            if (is_array($shape)) {
                $this->draftingShape($shape, $transform);
            }
        }
        foreach ($manualLines as $line) {
            if (is_array($line)) {
                $this->draftingLine($line, $transform);
            }
        }

        foreach ($connections as $connection) {
            if (! is_array($connection)) {
                continue;
            }
            $from = (string) ($connection['from'] ?? '');
            $to = (string) ($connection['to'] ?? '');
            if (! isset($this->deviceCenters[$from], $this->deviceCenters[$to])) {
                continue;
            }
            $route = $routes[(string) ($connection['_id'] ?? '')] ?? null;
            $sourceState = is_array(data_get($editor, 'positions.'.$from)) ? (array) data_get($editor, 'positions.'.$from) : [];
            $targetState = is_array(data_get($editor, 'positions.'.$to)) ? (array) data_get($editor, 'positions.'.$to) : [];
            $this->connection(
                $connection,
                $this->deviceCenters[$from],
                $this->deviceCenters[$to],
                is_array($route) ? $route : null,
                $transform,
                array_merge($nodes[$from] ?? [], $sourceState),
                array_merge($nodes[$to] ?? [], $targetState),
            );
        }

        foreach ($annotations as $annotation) {
            if (is_array($annotation)) {
                $this->draftingAnnotation($annotation, $transform);
            }
        }

        foreach ($nodes as $id => $node) {
            if (isset($this->deviceCenters[$id])) {
                $state = is_array(data_get($editor, 'positions.'.$id)) ? (array) data_get($editor, 'positions.'.$id) : [];
                $this->device($id, $this->deviceCenters[$id], array_merge($node, $state));
            }
        }

        $earthCandidates = array_values(array_filter(
            ['dc_protection', 'inverter', 'ac_protection', 'meter'],
            fn (string $id): bool => isset($nodes[$id], $this->deviceCenters[$id])
        ));
        if (($editor['_paper_type'] ?? 'single') !== 'as_drawn' && $earthCandidates !== []) {
            $busY = 300.0;
            $this->groundSymbol(148, $busY);
            $this->line(166, $busY, self::WIDTH - 170, $busY, [0.08, 0.23, 0.3], 1.15);
            $this->text(self::WIDTH - 160, $busY - 2, 'PE REFERENCE', 5.1, 'F2', [0.02, 0.49, 0.47]);
            foreach ($earthCandidates as $id) {
                $center = $this->deviceCenters[$id];
                $this->dashedLine($center['x'], max($busY + 4, $center['y'] - 52), $center['x'], $busY, [0.38, 0.55, 0.6], 0.7, [3, 3]);
            }
        }
    }

    /**
     * Model-space geometry uses a top-left origin and positive Y downward.
     * PDF coordinates use positive Y upward. One uniform transform preserves
     * the user's relative spacing and only scales the composition down when it
     * exceeds the printable topology area. It never stretches a compact layout.
     *
     * @param array<string, array<string, mixed>> $nodes
     * @param array<string, array<string, mixed>> $stored
     * @param array<int, array<string, mixed>> $manualLines
     * @param array<int, array<string, mixed>> $manualShapes
     * @param array<string, array<int, array<string, mixed>>> $routes
     * @param array<int, array<string, mixed>> $annotations
     * @return array{positions:array<string,array{x:float,y:float,w:float,h:float}>,transform:array{scale:float,center_x:float,center_y:float,paper_x:float,paper_y:float}}
     */
    private function paperLayout(array $nodes, array $stored, array $manualLines, array $manualShapes, array $routes = [], array $annotations = [], string $paperType = 'single'): array
    {
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
            if ($position !== null) {
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
        foreach ($routes as $route) {
            if (! is_array($route)) continue;
            foreach ($route as $point) {
                if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) continue;
                $x = (float) $point['x'];
                $y = (float) $point['y'];
                $left = min($left, $x);
                $top = min($top, $y);
                $right = max($right, $x);
                $bottom = max($bottom, $y);
            }
        }
        foreach ($annotations as $annotation) {
            if (! is_array($annotation) || ! is_numeric($annotation['x'] ?? null) || ! is_numeric($annotation['y'] ?? null)) continue;
            $x = (float) $annotation['x'];
            $y = (float) $annotation['y'];
            $w = max(80.0, (float) ($annotation['w'] ?? 240));
            $h = max(32.0, (float) ($annotation['h'] ?? 54));
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

        $paperLeft = 50.0;
        $paperRight = self::WIDTH - 50.0;
        $paperBottom = 238.0;
        $paperTop = 676.0;
        $contentWidth = max($right - $left, 1.0);
        $contentHeight = max($bottom - $top, 1.0);
        $maximumScale = $paperType === 'as_drawn' ? 1.65 : 1.0;
        $scale = min(
            ($paperRight - $paperLeft) / $contentWidth,
            ($paperTop - $paperBottom) / $contentHeight,
            $maximumScale,
        );
        $transform = [
            'scale' => $scale,
            'center_x' => ($left + $right) / 2,
            'center_y' => ($top + $bottom) / 2,
            'paper_x' => ($paperLeft + $paperRight) / 2,
            'paper_y' => ($paperBottom + $paperTop) / 2,
        ];

        $positions = [];
        foreach ($rects as $id => $rect) {
            $positions[$id] = array_merge($this->mapPdfPoint(
                $rect['x'] + ($rect['w'] / 2),
                $rect['y'] + ($rect['h'] / 2),
                $transform,
            ), [
                'w' => $rect['w'] * $scale,
                'h' => $rect['h'] * $scale,
            ]);
        }

        return ['positions' => $positions, 'transform' => $transform];
    }

    /** @param array{scale:float,center_x:float,center_y:float,paper_x:float,paper_y:float} $transform */
    private function mapPdfPoint(float $x, float $y, array $transform): array
    {
        return [
            'x' => (($x - $transform['center_x']) * $transform['scale']) + $transform['paper_x'],
            'y' => $transform['paper_y'] - (($y - $transform['center_y']) * $transform['scale']),
        ];
    }

    /** @param array{scale:float,center_x:float,center_y:float,paper_x:float,paper_y:float} $transform */
    private function draftingShape(array $shape, array $transform): void
    {
        $type = strtolower((string) ($shape['shape_type'] ?? 'rectangle'));
        if (! in_array($type, ['rectangle', 'circle', 'arc'], true)) {
            return;
        }
        $x = (float) ($shape['x'] ?? 0);
        $y = (float) ($shape['y'] ?? 0);
        $w = max(4.0, (float) ($shape['w'] ?? 4));
        $h = max(4.0, (float) ($shape['h'] ?? $w));
        $topLeft = $this->mapPdfPoint($x, $y, $transform);
        $bottomRight = $this->mapPdfPoint($x + $w, $y + $h, $transform);
        $paperX = min($topLeft['x'], $bottomRight['x']);
        $paperY = min($topLeft['y'], $bottomRight['y']);
        $paperW = abs($bottomRight['x'] - $topLeft['x']);
        $paperH = abs($bottomRight['y'] - $topLeft['y']);
        $weight = max(0.45, min(3.0, (float) ($shape['lineweight'] ?? 1.4) * 0.72));
        $lineType = strtolower((string) ($shape['line_type'] ?? 'continuous'));
        $color = [0.32, 0.44, 0.49];
        $dash = $lineType === 'hidden' ? [5, 3] : ($lineType === 'center' ? [9, 3] : null);

        if ($type === 'rectangle') {
            if ($dash !== null) {
                $this->dashedLine($paperX, $paperY, $paperX + $paperW, $paperY, $color, $weight, $dash);
                $this->dashedLine($paperX + $paperW, $paperY, $paperX + $paperW, $paperY + $paperH, $color, $weight, $dash);
                $this->dashedLine($paperX + $paperW, $paperY + $paperH, $paperX, $paperY + $paperH, $color, $weight, $dash);
                $this->dashedLine($paperX, $paperY + $paperH, $paperX, $paperY, $color, $weight, $dash);
            } else {
                $this->rect($paperX, $paperY, $paperW, $paperH, $color, false, $weight);
            }
        } elseif ($type === 'circle') {
            $this->ellipse($paperX + ($paperW / 2), $paperY + ($paperH / 2), $paperW / 2, $paperH / 2, $color, $weight, $dash);
        } else {
            $cx = $paperX + ($paperW / 2);
            $cy = $paperY + ($paperH / 2);
            $rx = $paperW / 2;
            $ry = $paperH / 2;
            $start = (float) ($shape['start_angle'] ?? 0);
            $end = (float) ($shape['end_angle'] ?? 90);
            $sweep = ((int) ($shape['sweep_flag'] ?? 1)) === 0 ? 0 : 1;
            $delta = $sweep === 1
                ? fmod(($end - $start) + 3600.0, 360.0)
                : fmod(($start - $end) + 3600.0, 360.0);
            if ($delta < 0.01) {
                $delta = 360.0;
            }
            $steps = max(8, (int) ceil(abs($delta) / 7.5));
            $points = [];
            $screenBasis = ($shape['angle_basis'] ?? null) === 'screen';
            for ($i = 0; $i <= $steps; $i++) {
                $signedDelta = $sweep === 1 ? $delta : -$delta;
                $angle = deg2rad($start + (($signedDelta * $i) / $steps));
                // PDF space has positive Y upward. New editor arcs use screen-space angles, legacy arcs use mathematical angles.
                $points[] = ['x' => $cx + ($rx * cos($angle)), 'y' => $cy + (($screenBasis ? -1 : 1) * $ry * sin($angle))];
            }
            for ($i = 0; $i < count($points) - 1; $i++) {
                $a = $points[$i];
                $b = $points[$i + 1];
                if ($dash !== null) {
                    $this->dashedLine($a['x'], $a['y'], $b['x'], $b['y'], $color, $weight, $dash);
                } else {
                    $this->line($a['x'], $a['y'], $b['x'], $b['y'], $color, $weight);
                }
            }
        }

        $label = trim((string) ($shape['label'] ?? ''));
        if ($label !== '') {
            $this->text($paperX + ($paperW / 2), $paperY + $paperH + 7, $this->shorten($label, 36), 4.8, 'F3', $color, 'center');
        }
    }

    /** @param array{scale:float,center_x:float,center_y:float,paper_x:float,paper_y:float} $transform */
    private function draftingLine(array $line, array $transform): void
    {
        $start = $this->mapPdfPoint((float) ($line['x1'] ?? 0), (float) ($line['y1'] ?? 0), $transform);
        $end = $this->mapPdfPoint((float) ($line['x2'] ?? 0), (float) ($line['y2'] ?? 0), $transform);
        $weight = max(0.45, min(3.0, (float) ($line['lineweight'] ?? 1.4) * 0.72));
        $type = strtolower((string) ($line['line_type'] ?? 'continuous'));
        $color = [0.32, 0.44, 0.49];
        if ($type === 'hidden') {
            $this->dashedLine($start['x'], $start['y'], $end['x'], $end['y'], $color, $weight, [5, 3]);
        } elseif ($type === 'center') {
            $this->dashedLine($start['x'], $start['y'], $end['x'], $end['y'], $color, $weight, [9, 3]);
        } else {
            $this->line($start['x'], $start['y'], $end['x'], $end['y'], $color, $weight);
        }
        if (strtolower((string) ($line['entity'] ?? 'line')) === 'leader') {
            $this->leaderArrow($start['x'], $start['y'], $end['x'], $end['y'], $color);
        }
        $label = trim((string) ($line['label'] ?? ''));
        if ($label !== '') {
            $this->text(
                ($start['x'] + $end['x']) / 2,
                (($start['y'] + $end['y']) / 2) + 7,
                $this->shorten($label, 36),
                4.8,
                'F3',
                $color,
                'center',
            );
        }
    }

    /** @param array{scale:float,center_x:float,center_y:float,paper_x:float,paper_y:float} $transform */
    private function draftingAnnotation(array $annotation, array $transform): void
    {
        if (! is_numeric($annotation['x'] ?? null) || ! is_numeric($annotation['y'] ?? null)) return;
        $x = (float) $annotation['x'];
        $y = (float) $annotation['y'];
        $w = max(80.0, (float) ($annotation['w'] ?? 240));
        $h = max(32.0, (float) ($annotation['h'] ?? 54));
        $topLeft = $this->mapPdfPoint($x, $y, $transform);
        $bottomRight = $this->mapPdfPoint($x + $w, $y + $h, $transform);
        $paperX = min($topLeft['x'], $bottomRight['x']);
        $paperY = min($topLeft['y'], $bottomRight['y']);
        $paperW = max(48.0, abs($bottomRight['x'] - $topLeft['x']));
        $paperH = max(24.0, abs($bottomRight['y'] - $topLeft['y']));
        $this->rect($paperX, $paperY, $paperW, $paperH, [1, 1, 1], true);
        $this->rect($paperX, $paperY, $paperW, $paperH, [0.43, 0.55, 0.59], false, 0.7);
        $this->line($paperX, $paperY + $paperH, $paperX + min(22.0, $paperW), $paperY + $paperH, [0.035, 0.63, 0.59], 1.6);
        $this->text($paperX + 8, $paperY + $paperH - 14, $this->shorten(trim((string) ($annotation['text'] ?? 'Drawing note')), 92), 5.2, 'F2', [0.16, 0.29, 0.35]);
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
        $anchor = is_array($selected['anchor'] ?? null) ? $selected['anchor'] : null;
        if ($anchor !== null && is_numeric($anchor['x'] ?? null) && is_numeric($anchor['y'] ?? null)) {
            $nx = max(0.0, min(1.0, (float) $anchor['x']));
            $ny = max(0.0, min(1.0, (float) $anchor['y']));
        } else {
            $side = (string) ($selected['side'] ?? '');
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
        if (($node['flip_h'] ?? false) === true) $nx = 1.0 - $nx;
        if (($node['flip_v'] ?? false) === true) $ny = 1.0 - $ny;
        [$nx, $ny] = match ($this->quarterTurn($node['rotation'] ?? 0)) {
            90 => [1.0 - $ny, $nx],
            180 => [1.0 - $nx, 1.0 - $ny],
            270 => [$ny, 1.0 - $nx],
            default => [$nx, $ny],
        };

        // Model-space Y grows downward; PDF Y grows upward.
        return [
            'x' => $x - ($w / 2) + ($nx * $w),
            'y' => $y + ($h / 2) - ($ny * $h),
        ];
    }

    private function quarterTurn(mixed $value): int
    {
        $numeric = is_numeric($value) ? (float) $value : 0.0;
        $rotation = ((int) round($numeric / 90)) * 90;
        return (($rotation % 360) + 360) % 360;
    }

    /** @param array<int, array{x:float,y:float}> $points @return array{x:float,y:float} */
    private function wireLabelAnchor(array $points): array
    {
        $best = null;
        $bestLength = -1.0;
        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index];
            $b = $points[$index + 1];
            $dx = abs($b['x'] - $a['x']);
            $dy = abs($b['y'] - $a['y']);
            $score = $dx >= $dy ? $dx + 10000.0 : $dy;
            if ($score > $bestLength) {
                $bestLength = $score;
                $best = ['x' => ($a['x'] + $b['x']) / 2, 'y' => ($a['y'] + $b['y']) / 2];
            }
        }
        return $best ?? ($points[0] ?? ['x' => 0.0, 'y' => 0.0]);
    }

    /** @param array<int, array{x:float,y:float}> $points @return array<int, array{x:float,y:float}> */
    private function cleanRoutePoints(array $points): array
    {
        $clean = [];
        foreach ($points as $point) {
            $last = $clean[count($clean) - 1] ?? null;
            if ($last && abs($last['x'] - $point['x']) < 0.01 && abs($last['y'] - $point['y']) < 0.01) continue;
            $clean[] = $point;
        }
        $index = 1;
        while ($index < count($clean) - 1) {
            $a = $clean[$index - 1]; $b = $clean[$index]; $c = $clean[$index + 1];
            $sameX = abs($a['x'] - $b['x']) < 0.01 && abs($b['x'] - $c['x']) < 0.01;
            $sameY = abs($a['y'] - $b['y']) < 0.01 && abs($b['y'] - $c['y']) < 0.01;
            if ($sameX || $sameY) { array_splice($clean, $index, 1); continue; }
            $index++;
        }
        return $clean;
    }

    /** @param array<int, array{x:float,y:float}> $points @return array<int, array{x:float,y:float}> */
    private function orthogonalRoutePoints(array $points): array
    {
        if (count($points) < 2) return $points;
        $orthogonal = [$points[0]];
        foreach (array_slice($points, 1) as $point) {
            $previous = $orthogonal[count($orthogonal) - 1];
            if (abs($previous['x'] - $point['x']) > .05 && abs($previous['y'] - $point['y']) > .05) {
                $orthogonal[] = ['x' => $point['x'], 'y' => $previous['y']];
            }
            $orthogonal[] = $point;
        }
        return $this->cleanRoutePoints($orthogonal);
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
            $length += abs($points[$index + 1]['x'] - $points[$index]['x']) + abs($points[$index + 1]['y'] - $points[$index]['y']);
        }
        $aligned = min(abs($end['x'] - $start['x']), abs($end['y'] - $start['y'])) <= 7.0;
        return $aligned && $length > max(64.0, $minimum * 2.35);
    }

    /** @param array{x:float,y:float,w?:float,h?:float} $source @param array{x:float,y:float,w?:float,h?:float} $target */
    private function connection(array $connection, array $source, array $target, ?array $route, array $transform, array $sourceNode = [], array $targetNode = []): void
    {
        $network = strtolower((string) ($connection['network'] ?? ''));
        $manual = ($connection['_manual'] ?? false) === true;
        $color = match ($network) {
            'communication' => [0.45, 0.35, 0.66],
            'protective_earth' => [0.31, 0.46, 0.49],
            'control' => [0.33, 0.47, 0.72],
            'measurement' => [0.26, 0.55, 0.57],
            'dc_positive', 'battery_positive' => [0.79, 0.30, 0.30],
            'dc_negative', 'battery_negative' => [0.29, 0.35, 0.38],
            'dc_power', 'battery_dc' => [0.72, 0.36, 0.28],
            'ac_power' => [0.65, 0.42, 0.16],
            'neutral' => [0.22, 0.49, 0.68],
            default => $manual ? [0.84, 0.52, 0.20] : [0.08, 0.23, 0.30],
        };
        $width = $network === 'communication' ? 0.95 : 1.35;

        $start = $this->paperPort($source, $sourceNode, (string) ($connection['from_port'] ?? ''), true);
        $end = $this->paperPort($target, $targetNode, (string) ($connection['to_port'] ?? ''), false);
        $points = [$start];
        if (is_array($route) && $route !== []) {
            foreach ($route as $point) {
                if (is_array($point) && is_numeric($point['x'] ?? null) && is_numeric($point['y'] ?? null)) {
                    $points[] = $this->mapPdfPoint((float) $point['x'], (float) $point['y'], $transform);
                }
            }
        } elseif (abs($start['y'] - $end['y']) > .05) {
            $midX = ($start['x'] + $end['x']) / 2;
            $points[] = ['x' => $midX, 'y' => $start['y']];
            $points[] = ['x' => $midX, 'y' => $end['y']];
        }
        $points[] = $end;
        $points = $this->orthogonalRoutePoints($this->cleanRoutePoints($points));
        if (is_array($route) && $route !== [] && $this->routeIsStale($points)) {
            $points = [$start];
            if (abs($start['y'] - $end['y']) > .05) {
                $midX = ($start['x'] + $end['x']) / 2;
                $points[] = ['x' => $midX, 'y' => $start['y']];
                $points[] = ['x' => $midX, 'y' => $end['y']];
            }
            $points[] = $end;
        }

        for ($index = 0; $index < count($points) - 1; $index++) {
            $a = $points[$index];
            $b = $points[$index + 1];
            if ($manual || in_array($network, ['communication', 'protective_earth', 'control', 'measurement'], true)) {
                $dash = match ($network) {
                    'communication' => [6, 4],
                    'protective_earth' => [3, 3],
                    'control' => [9, 4, 2, 4],
                    'measurement' => [3, 3],
                    default => [7, 4],
                };
                $this->dashedLine($a['x'], $a['y'], $b['x'], $b['y'], $color, $width, $dash);
            } else {
                $this->line($a['x'], $a['y'], $b['x'], $b['y'], $color, $width);
            }
        }

        $middle = $this->wireLabelAnchor($points);
        $cableTag = trim((string) ($connection['cable_tag'] ?? ''));
        $customLabel = trim((string) ($connection['label'] ?? ''));
        $wireId = trim((string) ($connection['wire_id'] ?? ''));
        $detailParts = [];
        $cableType = trim((string) ($connection['cable_type'] ?? ''));
        $cableSize = trim((string) ($connection['cable_size'] ?? ''));
        $voltageClass = trim((string) ($connection['voltage_class'] ?? ''));
        if ($this->meaningfulEngineeringText($cableType)) $detailParts[] = $cableType;
        if ($this->meaningfulEngineeringText($cableSize)) {
            $detailParts[] = $this->formatCableSize($cableSize);
        }
        if (filled($connection['cores'] ?? null) && (int) $connection['cores'] > 0) $detailParts[] = ((int) $connection['cores']).'C';
        if ($this->meaningfulEngineeringText($voltageClass)) $detailParts[] = $voltageClass;
        $hasCableData = $detailParts !== [] || $this->meaningfulEngineeringText($cableTag);
        $label = $this->meaningfulEngineeringText($cableTag)
            ? $cableTag
            : ($this->meaningfulEngineeringText($customLabel) ? $customLabel : ($hasCableData && $this->meaningfulEngineeringText($wireId) ? $wireId : ''));
        $detail = implode(' · ', $detailParts);
        if (($connection['show_label'] ?? true) !== false && $label !== '') {
            $this->text($middle['x'], $middle['y'] + 13, $this->shorten($label, 30), 5.4, 'F2', $manual ? [0.68, 0.38, 0.09] : [0.02, 0.49, 0.47], 'center');
        }
        if (($connection['show_label'] ?? true) !== false && $detail !== '') {
            $this->text($middle['x'], $middle['y'] + 4, $this->shorten($detail, 44), 4.8, 'F3', [0.30, 0.43, 0.49], 'center');
        }
    }

    /** @param array{x:float,y:float,w?:float,h?:float} $center @param array<string, mixed> $node */
    /** @param array<string, mixed> $node @return array{0:string,1:string} */
    private function professionalCompactDeviceText(string $id, array $node): array
    {
        $rating = trim((string) ($node['rating'] ?? ''));
        $compact = match (true) {
            str_starts_with($id, 'pv_group_') => ['PV ARRAY', preg_replace('/\s*·\s*\d+\/\d+\s+MPPT.*/i', '', $rating) ?: $rating],
            str_starts_with($id, 'dc_isolator_') => ['DC ISOLATOR', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'dc_fuse_') => ['gPV FUSE', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'dc_bus_') => ['DC BUS', preg_replace('/\s*·\s*\d+\s*A.*/i', '', $rating) ?: $rating],
            str_starts_with($id, 'dc_spd_') => ['DC SPD', str_ireplace(['Type 2 DC SPD ', 'Type II DC SPD '], ['TYPE 2 / ', 'TYPE 2 / '], $rating)],
            str_starts_with($id, 'inverter_') => ['INVERTER', preg_replace('/\s*\/\s*ratio\s+[0-9.]+/i', '', str_replace(' · ', ' / ', $rating)) ?: $rating],
            str_starts_with($id, 'ac_breaker_') => ['AC CB', str_replace(' · ', ' / ', $rating)],
            str_starts_with($id, 'ac_isolator_') => ['AC ISOLATOR', str_replace(' · ', ' / ', $rating)],
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

    private function device(string $id, array $center, array $node): void
    {
        $x = (float) ($center['x'] ?? 0);
        $y = (float) ($center['y'] ?? 0);
        $cellW = max(52.0, (float) ($center['w'] ?? 144.0));
        $cellH = max(48.0, (float) ($center['h'] ?? 120.0));
        $manual = ($node['_manual'] ?? false) === true || (string) ($node['state'] ?? '') === 'manual';
        $accent = $manual ? [0.84, 0.52, 0.20] : [0.035, 0.63, 0.59];
        $kind = (string) ($node['symbol'] ?? $node['kind'] ?? $id);
        $contentScale = max(0.52, min(1.85, min($cellW / 144.0, $cellH / 120.0)));
        $rotation = $this->quarterTurn($node['rotation'] ?? 0);
        $flipH = ($node['flip_h'] ?? false) ? -1.0 : 1.0;
        $flipV = ($node['flip_v'] ?? false) ? -1.0 : 1.0;
        $symbolY = $y + min($cellH * 0.12, 18.0);

        // Preserve the user's symbol transform while keeping all text horizontal.
        $angle = deg2rad(-$rotation);
        $cos = cos($angle);
        $sin = sin($angle);
        $sx = $contentScale * $flipH;
        $sy = $contentScale * $flipV;
        $this->commands[] = 'q';
        $this->commands[] = sprintf('1 0 0 1 %.2F %.2F cm', $x, $symbolY);
        $this->commands[] = sprintf('%.5F %.5F %.5F %.5F 0 0 cm', $cos, $sin, -$sin, $cos);
        $this->commands[] = sprintf('%.5F 0 0 %.5F 0 0 cm', $sx, $sy);
        $this->symbol($kind, 0, 0);
        $this->commands[] = 'Q';

        $barHalf = min(22.0, $cellW * 0.18);
        $this->line($x - $barHalf, $y + ($cellH / 2) - 5, $x + $barHalf, $y + ($cellH / 2) - 5, $accent, 1.7);

        $title = (string) ($node['paper_title'] ?? $node['title'] ?? $this->humanize($kind));
        $rating = (string) ($node['paper_rating'] ?? $node['rating'] ?? 'Rating not specified');
        $subtitle = (string) ($node['subtitle'] ?? '');
        $tag = trim((string) (($node['tag_override'] ?? null) ?: ($node['tag'] ?? '')));
        $showTag = ($node['show_tag'] ?? true) !== false && $tag !== '';
        $showRating = ($node['show_rating'] ?? true) !== false && $this->meaningfulEngineeringText($rating);
        $showSubtitle = ($node['show_subtitle'] ?? true) !== false && $this->meaningfulEngineeringText($subtitle);

        $titleY = $y - ($cellH / 2) + 27;
        if ($showTag) {
            $this->text($x, $titleY + 12, $this->shorten($tag, 24), 4.8, 'F3', [0.02, 0.49, 0.47], 'center');
        }
        $this->text($x, $titleY, $this->shorten($title, 26), 7.3, 'F2', [0.04, 0.15, 0.22], 'center');
        $nextY = $titleY - 11;
        if ($showRating) {
            $this->text($x, $nextY, $this->shorten($rating, 36), 4.8, 'F3', [0.12, 0.28, 0.35], 'center');
            $nextY -= 10;
        }
        if ($showSubtitle) {
            $this->text($x, $nextY, $this->shorten($subtitle, 36), 4.5, 'F1', [0.38, 0.49, 0.53], 'center');
        }
    }

    private function symbol(string $id, float $cx, float $cy): void
    {
        $navy = [0.04, 0.16, 0.23];
        $teal = [0.035, 0.63, 0.59];
        $orange = [0.9, 0.57, 0.22];
        $white = [1, 1, 1];

        switch ($id) {
            case 'pv_module':
                $this->rect($cx - 30, $cy - 19, 60, 38, $navy, false, 1.25);
                $this->line($cx - 10, $cy - 19, $cx - 10, $cy + 19, $navy, 0.65);
                $this->line($cx + 10, $cy - 19, $cx + 10, $cy + 19, $navy, 0.65);
                $this->line($cx - 30, $cy, $cx + 30, $cy, $navy, 0.65);
                $this->line($cx - 38, $cy, $cx - 30, $cy, $teal, 1.1);
                $this->line($cx + 30, $cy, $cx + 38, $cy, $teal, 1.1);
                break;

            case 'pv_string':
            case 'sub_array':
                foreach ([-28, 0, 28] as $offset) {
                    $this->rect($cx + $offset - 11, $cy - 17, 22, 34, $navy, false, 1.05);
                    $this->line($cx + $offset, $cy - 17, $cx + $offset, $cy + 17, $navy, 0.55);
                    $this->line($cx + $offset - 11, $cy, $cx + $offset + 11, $cy, $navy, 0.55);
                }
                $this->line($cx - 17, $cy, $cx - 11, $cy, $teal, 1.0);
                $this->line($cx + 11, $cy, $cx + 17, $cy, $teal, 1.0);
                $this->line($cx - 42, $cy, $cx - 39, $cy, $teal, 1.0);
                $this->line($cx + 39, $cy, $cx + 42, $cy, $teal, 1.0);
                break;

            case 'pv_array':
                $this->polygon([
                    [$cx - 30, $cy + 21], [$cx + 25, $cy + 21], [$cx + 31, $cy - 17], [$cx - 24, $cy - 17],
                ], $navy, 1.35);
                $this->line($cx - 11, $cy + 21, $cx - 7, $cy - 17, $navy, 0.65);
                $this->line($cx + 8, $cy + 21, $cx + 12, $cy - 17, $navy, 0.65);
                $this->line($cx - 27, $cy + 8, $cx + 28, $cy + 8, $navy, 0.65);
                $this->line($cx - 25, $cy - 5, $cx + 30, $cy - 5, $navy, 0.65);
                $this->line($cx - 34, $cy - 23, $cx + 34, $cy - 23, $teal, 1.2);
                $this->line($cx - 8, $cy - 23, $cx - 17, $cy - 34, $navy, 1.0);
                $this->line($cx + 9, $cy - 23, $cx + 18, $cy - 34, $navy, 1.0);
                break;

            case 'junction_box':
                $this->rect($cx - 27, $cy - 25, 54, 50, $navy, false, 1.2);
                foreach ([-15, 0, 15] as $offset) {
                    $this->line($cx - 39, $cy + $offset, $cx - 27, $cy + $offset, $navy, 1.0);
                    $this->line($cx - 16, $cy + $offset, $cx, $cy + $offset, $navy, 0.8);
                }
                $this->line($cx, $cy - 15, $cx, $cy + 15, $navy, 0.8);
                $this->line($cx, $cy, $cx + 18, $cy, $navy, 0.8);
                $this->line($cx + 27, $cy, $cx + 39, $cy, $navy, 1.0);
                break;

            case 'combiner':
            case 'ac_combiner':
                $this->rect($cx - 29, $cy - 27, 58, 54, $navy, false, 1.25);
                foreach ([-15, 0, 15] as $offset) {
                    $this->line($cx - 44, $cy + $offset, $cx - 29, $cy + $offset, $navy, 1.0);
                    $this->line($cx - 16, $cy + $offset, $cx, $cy + $offset, $navy, 0.8);
                }
                $this->line($cx, $cy - 15, $cx, $cy + 15, $navy, 0.8);
                $this->line($cx, $cy, $cx + 16, $cy, $navy, 0.8);
                $this->line($cx + 29, $cy, $cx + 44, $cy, $navy, 1.0);
                $this->line($cx + 16, $cy - 15, $cx + 16, $cy - 25, $teal, 1.0);
                $this->groundSymbol($cx + 16, $cy - 25);
                break;

            case 'power_optimizer':
            case 'dc_dc_converter':
            case 'mppt':
                $this->rect($cx - 31, $cy - 25, 62, 50, $navy, false, 1.25);
                $this->line($cx - 39, $cy, $cx - 31, $cy, $teal, 1.2);
                $this->line($cx + 31, $cy, $cx + 39, $cy, $teal, 1.2);
                $this->line($cx - 20, $cy - 12, $cx - 8, $cy + 10, $orange, 1.1);
                $this->line($cx - 8, $cy + 10, $cx + 3, $cy - 5, $orange, 1.1);
                $this->line($cx + 3, $cy - 5, $cx + 18, $cy + 13, $orange, 1.1);
                $this->text($cx, $cy - 19, $id === 'mppt' ? 'MPPT' : 'DC/DC', 4.8, 'F3', $navy, 'center');
                break;

            case 'rsd':
            case 'epo':
                $this->circle($cx, $cy, 29, $white, $navy, 1.3);
                $this->circle($cx, $cy, 13, $white, $orange, 1.2);
                $this->line($cx, $cy + 29, $cx, $cy + 39, $navy, 1.0);
                $this->line($cx, $cy - 29, $cx, $cy - 39, $navy, 1.0);
                $this->line($cx - 29, $cy, $cx - 39, $cy, $navy, 1.0);
                $this->line($cx + 29, $cy, $cx + 39, $cy, $navy, 1.0);
                $this->text($cx, $cy - 3, $id === 'epo' ? 'E' : 'R', 8.0, 'F2', $navy, 'center');
                break;

            case 'microinverter':
            case 'inverter':
            case 'hybrid_inverter':
            case 'offgrid_inverter':
            case 'central_inverter':
            case 'pcs':
                $this->rect($cx - 34, $cy - 34, 68, 68, $navy, false, 1.35);
                $this->line($cx - 30, $cy - 30, $cx + 30, $cy + 30, $navy, 1.15);
                $this->line($cx - 22, $cy + 18, $cx - 7, $cy + 18, $teal, 1.3);
                $this->line($cx - 22, $cy + 10, $cx - 7, $cy + 10, $teal, 1.3);
                $this->wave($cx + 5, $cy - 15, 24, 9, $teal, 1.2);
                if ($id === 'pcs') {
                    $this->text($cx, $cy - 28, 'BIDI', 4.2, 'F3', $navy, 'center');
                }
                break;

            case 'battery_cell':
            case 'battery_module':
            case 'battery_rack':
            case 'battery':
                $this->rect($cx - 38, $cy - 21, 76, 42, $navy, false, 1.25);
                $this->rect($cx + 38, $cy - 9, 7, 18, $navy, false, 1.0);
                foreach ([-24, -8, 8, 24] as $offset) {
                    $height = ($offset === -24 || $offset === 8) ? 23 : 15;
                    $this->line($cx + $offset, $cy - ($height / 2), $cx + $offset, $cy + ($height / 2), $navy, $height > 20 ? 2.3 : 1.2);
                }
                $this->line($cx - 31, $cy, $cx - 17, $cy, $teal, 1.1);
                $this->line($cx + 17, $cy, $cx + 31, $cy, $teal, 1.1);
                break;

            case 'bms':
            case 'relay':
            case 'scada':
            case 'monitoring':
            case 'data_logger':
            case 'plant_controller':
                $this->rect($cx - 36, $cy - 27, 72, 54, $navy, false, 1.2);
                $this->line($cx - 24, $cy + 12, $cx + 24, $cy + 12, $teal, 1.1);
                $this->line($cx - 24, $cy, $cx + 13, $cy, $navy, 0.9);
                $this->line($cx - 24, $cy - 12, $cx + 20, $cy - 12, $navy, 0.9);
                break;

            case 'battery_disconnect':
            case 'dc_isolator':
            case 'ac_isolator':
                $this->line($cx - 43, $cy, $cx - 18, $cy, $navy, 1.25);
                $this->circle($cx - 18, $cy, 2.3, $white, $navy, 0.9);
                $this->circle($cx + 18, $cy, 2.3, $white, $navy, 0.9);
                $this->line($cx - 18, $cy, $cx + 14, $cy + 21, $navy, 1.35);
                $this->line($cx + 18, $cy, $cx + 43, $cy, $navy, 1.25);
                break;

            case 'dc_fuse':
                $this->line($cx - 43, $cy, $cx - 14, $cy, $navy, 1.25);
                $this->rect($cx - 14, $cy - 10, 28, 20, $navy, false, 1.15);
                $this->line($cx - 9, $cy - 7, $cx + 9, $cy + 7, $navy, 1.0);
                $this->line($cx + 14, $cy, $cx + 43, $cy, $navy, 1.25);
                break;

            case 'dc_breaker':
            case 'ac_breaker':
                $this->line($cx - 43, $cy, $cx - 18, $cy, $navy, 1.25);
                $this->circle($cx - 18, $cy, 2.3, $white, $navy, 0.9);
                $this->circle($cx + 18, $cy, 2.3, $white, $navy, 0.9);
                $this->line($cx - 18, $cy, $cx + 13, $cy + 23, $navy, 1.35);
                $this->line($cx + 13, $cy + 23, $cx + 18, $cy + 23, $navy, 1.0);
                $this->line($cx + 18, $cy + 23, $cx + 18, $cy, $navy, 1.0);
                $this->line($cx + 18, $cy, $cx + 43, $cy, $navy, 1.25);
                break;

            case 'dc_spd':
            case 'ac_spd':
                $this->line($cx - 43, $cy - 10, $cx - 12, $cy - 10, $teal, 1.2);
                $this->rect($cx - 12, $cy - 26, 24, 32, $navy, false, 1.15);
                $this->line($cx - 7, $cy + 1, $cx + 7, $cy - 21, $navy, 1.0);
                $this->line($cx - 6, $cy - 20, $cx + 7, $cy - 20, $navy, 1.0);
                $this->line($cx, $cy + 6, $cx, $cy + 21, $navy, 1.15);
                $this->groundSymbol($cx, $cy + 21);
                break;

            case 'pe_bus':
                $this->line($cx - 39, $cy - 3, $cx + 39, $cy - 3, $navy, 3.0);
                foreach ([-28, -14, 0, 14, 28] as $offset) {
                    $this->line($cx + $offset, $cy - 3, $cx + $offset, $cy - 19, $navy, 0.95);
                }
                $this->line($cx, $cy - 3, $cx, $cy + 15, $navy, 1.1);
                $this->groundSymbol($cx, $cy + 15);
                break;

            case 'dc_protection':
            case 'ac_protection':
                $this->rect($cx - 42, $cy - 28, 84, 56, $navy, false, 1.1);
                $this->line($cx - 42, $cy, $cx - 18, $cy, $navy, 1.2);
                $this->circle($cx - 18, $cy, 2.2, $white, $navy, 0.8);
                $this->circle($cx + 8, $cy, 2.2, $white, $navy, 0.8);
                $this->line($cx - 18, $cy, $cx + 5, $cy + 17, $navy, 1.2);
                $this->rect($cx + 14, $cy - 9, 17, 18, $navy, false, 1.0);
                $this->line($cx + 31, $cy, $cx + 42, $cy, $navy, 1.2);
                $this->line($cx + 23, $cy - 9, $cx + 23, $cy - 19, $teal, 0.9);
                $this->groundSymbol($cx + 23, $cy - 19);
                break;

            case 'gfpd':
            case 'afci':
                $this->rect($cx - 38, $cy - 27, 76, 54, $navy, false, 1.1);
                $this->line($cx - 43, $cy, $cx - 17, $cy, $navy, 1.2);
                $this->circle($cx - 17, $cy, 2.2, $white, $navy, 0.8);
                $this->circle($cx + 17, $cy, 2.2, $white, $navy, 0.8);
                $this->line($cx - 17, $cy, $cx + 13, $cy + 21, $navy, 1.2);
                $this->line($cx + 17, $cy, $cx + 43, $cy, $navy, 1.2);
                $this->text($cx, $cy - 18, strtoupper($id), 4.7, 'F3', $navy, 'center');
                break;

            case 'dc_busbar':
                $this->line($cx - 40, $cy + 10, $cx + 40, $cy + 10, $navy, 3.0);
                $this->line($cx - 40, $cy - 10, $cx + 40, $cy - 10, $navy, 3.0);
                foreach ([-24, -7] as $offset) {
                    $this->line($cx + $offset, $cy + 10, $cx + $offset, $cy + 29, $teal, 1.0);
                }
                foreach ([12, 29] as $offset) {
                    $this->line($cx + $offset, $cy - 10, $cx + $offset, $cy - 29, $teal, 1.0);
                }
                break;

            case 'meter':
            case 'app_pln':
                $this->rect($cx - 28, $cy - 34, 56, 68, $navy, false, 1.25);
                $this->line($cx - 20, $cy + 19, $cx + 20, $cy + 19, $navy, 0.8);
                $this->text($cx, $cy - 2, 'Wh', 8.0, 'F2', $navy, 'center');
                $this->line($cx - 18, $cy - 20, $cx + 18, $cy - 20, $orange, 1.0);
                $this->line($cx - 18, $cy - 20, $cx - 11, $cy - 16, $orange, 1.0);
                $this->line($cx - 18, $cy - 20, $cx - 11, $cy - 24, $orange, 1.0);
                $this->line($cx + 18, $cy - 20, $cx + 11, $cy - 16, $orange, 1.0);
                $this->line($cx + 18, $cy - 20, $cx + 11, $cy - 24, $orange, 1.0);
                break;

            case 'pcc':
                $this->circle($cx, $cy, 27, $white, $navy, 1.35);
                $this->line($cx - 43, $cy, $cx - 27, $cy, $teal, 1.2);
                $this->line($cx + 27, $cy, $cx + 43, $cy, $teal, 1.2);
                $this->text($cx, $cy - 3, 'PCC', 7.0, 'F2', $navy, 'center');
                break;

            case 'grid':
                $this->line($cx, $cy + 37, $cx - 24, $cy - 36, $navy, 1.3);
                $this->line($cx, $cy + 37, $cx + 24, $cy - 36, $navy, 1.3);
                $this->line($cx - 16, $cy + 17, $cx + 16, $cy + 17, $navy, 1.0);
                $this->line($cx - 21, $cy, $cx + 21, $cy, $navy, 1.0);
                $this->line($cx - 28, $cy - 36, $cx + 28, $cy - 36, $navy, 1.1);
                $this->line($cx - 34, $cy + 28, $cx + 34, $cy + 28, $orange, 1.2);
                break;

            case 'transformer':
                $this->line($cx - 42, $cy, $cx - 28, $cy, $navy, 1.1);
                $this->line($cx + 28, $cy, $cx + 42, $cy, $navy, 1.1);
                $this->circle($cx - 10, $cy, 20, $white, $navy, 1.2);
                $this->circle($cx + 12, $cy, 20, $white, $navy, 1.2);
                break;

            case 'ct':
                $this->line($cx - 43, $cy, $cx + 43, $cy, $navy, 1.2);
                $this->circle($cx, $cy, 22, $white, $navy, 1.2);
                $this->line($cx + 8, $cy - 22, $cx + 8, $cy - 37, $navy, 0.9);
                $this->line($cx + 17, $cy - 20, $cx + 17, $cy - 37, $navy, 0.9);
                break;

            case 'pt':
                $this->line($cx, $cy + 43, $cx, $cy + 27, $navy, 1.1);
                $this->line($cx, $cy - 27, $cx, $cy - 43, $navy, 1.1);
                $this->circle($cx, $cy + 10, 19, $white, $navy, 1.2);
                $this->circle($cx, $cy - 10, 19, $white, $navy, 1.2);
                break;

            case 'generator':
                $this->circle($cx, $cy, 31, $white, $navy, 1.35);
                $this->text($cx, $cy - 4, 'G', 15, 'F2', $navy, 'center');
                $this->line($cx + 31, $cy, $cx + 45, $cy, $teal, 1.2);
                break;

            case 'panel':
            case 'main_panel':
            case 'critical_panel':
            case 'backup_panel':
            case 'load':
                $this->rect($cx - 35, $cy - 30, 70, 60, $navy, false, 1.25);
                foreach ([15, 0, -15] as $offset) {
                    $this->line($cx - 23, $cy + $offset, $cx + 23, $cy + $offset, $navy, 0.9);
                    $this->circle($cx - 16, $cy + $offset, 2.2, $teal, $teal, 0.4);
                }
                break;

            case 'transfer_switch':
                $this->rect($cx - 39, $cy - 29, 78, 58, $navy, false, 1.2);
                $this->line($cx - 27, $cy + 13, $cx - 8, $cy + 13, $navy, 1.0);
                $this->line($cx + 12, $cy + 13, $cx + 27, $cy + 13, $navy, 1.0);
                $this->line($cx - 27, $cy - 13, $cx - 8, $cy - 13, $navy, 1.0);
                $this->line($cx + 12, $cy - 13, $cx + 27, $cy - 13, $navy, 1.0);
                $this->line($cx - 8, $cy + 13, $cx + 12, $cy - 13, $navy, 1.3);
                foreach ([[$cx - 8, $cy + 13], [$cx + 12, $cy + 13], [$cx + 12, $cy - 13]] as [$x, $y]) {
                    $this->circle($x, $y, 2.4, $white, $navy, 0.8);
                }
                break;

            case 'ground':
            case 'ground_rod':
                $this->line($cx, $cy + 34, $cx, $cy - 5, $navy, 1.3);
                $this->line($cx - 24, $cy - 5, $cx + 24, $cy - 5, $navy, 1.3);
                $this->line($cx - 16, $cy - 15, $cx + 16, $cy - 15, $navy, 1.0);
                $this->line($cx - 8, $cy - 25, $cx + 8, $cy - 25, $navy, 0.8);
                if ($id === 'ground_rod') {
                    $this->line($cx, $cy - 25, $cx, $cy - 39, $orange, 1.1);
                }
                break;

            case 'lightning_arrester':
                $this->line($cx, $cy + 40, $cx, $cy + 24, $navy, 1.1);
                $this->rect($cx - 11, $cy - 16, 22, 40, $navy, false, 1.15);
                $this->line($cx - 6, $cy - 10, $cx + 6, $cy + 17, $orange, 1.0);
                $this->line($cx, $cy - 16, $cx, $cy - 28, $navy, 1.1);
                $this->line($cx - 24, $cy - 28, $cx + 24, $cy - 28, $navy, 1.0);
                $this->line($cx - 16, $cy - 36, $cx + 16, $cy - 36, $navy, 0.8);
                break;

            case 'weather_station':
                $this->line($cx, $cy + 34, $cx, $cy - 32, $navy, 1.2);
                $this->line($cx - 22, $cy - 32, $cx + 22, $cy - 32, $navy, 1.0);
                $this->line($cx - 18, $cy + 16, $cx + 18, $cy + 16, $navy, 1.0);
                $this->line($cx - 12, $cy + 16, $cx, $cy - 2, $navy, 1.0);
                $this->line($cx, $cy - 2, $cx + 12, $cy + 16, $navy, 1.0);
                $this->circle($cx, $cy + 34, 3.5, $orange, $orange, 0.4);
                break;

            case 'irradiance_sensor':
                $this->line($cx - 29, $cy - 24, $cx + 29, $cy - 24, $navy, 1.1);
                // A compact pyranometer dome.
                $steps = 12;
                for ($i = 0; $i < $steps; $i++) {
                    $a1 = M_PI - (($i / $steps) * M_PI);
                    $a2 = M_PI - ((($i + 1) / $steps) * M_PI);
                    $this->line(
                        $cx + (22 * cos($a1)), $cy - 24 + (22 * sin($a1)),
                        $cx + (22 * cos($a2)), $cy - 24 + (22 * sin($a2)),
                        $navy, 1.0,
                    );
                }
                $this->line($cx, $cy + 33, $cx, $cy + 18, $orange, 1.0);
                $this->line($cx - 25, $cy + 25, $cx - 15, $cy + 15, $orange, 1.0);
                $this->line($cx + 25, $cy + 25, $cx + 15, $cy + 15, $orange, 1.0);
                break;

            default:
                $this->circle($cx, $cy, 30, $white, $navy, 1.25);
                $this->line($cx - 15, $cy, $cx + 15, $cy, $teal, 1.1);
                $this->line($cx, $cy - 15, $cx, $cy + 15, $teal, 1.1);
                break;
        }
    }

    /** @param array<string, array<string, mixed>> $nodes */
    private function notesAndLegend(array $nodes): void
    {
        $y = 126.0;
        $h = 84.0;
        $leftX = 46.0;
        $leftW = 664.0;
        $rightX = 720.0;
        $rightW = self::WIDTH - 46 - $rightX;

        $this->rect($leftX, $y, $leftW, $h, [1, 1, 1], true);
        $this->rect($leftX, $y, $leftW, $h, [0.55, 0.66, 0.69], false, 0.55);
        $this->text($leftX + 12, $y + $h - 16, 'DRAWING NOTES', 5.7, 'F2', [0.31, 0.46, 0.52]);
        $notes = [
            '1. Preliminary SLD. Verify protection coordination, conductor sizing, routing, and earthing before construction.',
            '2. Model Space composition is preserved. Drawing is NTS; equipment data remains controlled by the approved calculation source.',
        ];
        foreach ($notes as $index => $note) {
            $this->text($leftX + 12, $y + $h - 34 - ($index * 17), $this->shorten($note, 118), 5.4, 'F1', [0.19, 0.32, 0.38]);
        }

        $this->rect($rightX, $y, $rightW, $h, [1, 1, 1], true);
        $this->rect($rightX, $y, $rightW, $h, [0.55, 0.66, 0.69], false, 0.55);
        $this->text($rightX + 12, $y + $h - 16, 'DRAWING LEGEND - SYMBOLS USED', 5.7, 'F2', [0.31, 0.46, 0.52]);

        $legend = [];
        foreach ($nodes as $id => $node) {
            $manual = ($node['_manual'] ?? false) === true;
            $kind = (string) ($node['symbol'] ?? $node['kind'] ?? $id);
            if (! isset($legend[$kind])) {
                $legend[$kind] = (string) ($node['title'] ?? $this->humanize($kind));
            }
        }
        $legendIndex = 0;
        foreach (array_slice($legend, 0, 6, true) as $kind => $label) {
            $column = $legendIndex % 2;
            $row = intdiv($legendIndex, 2);
            $cx = $rightX + 31 + ($column * 212);
            $cy = $y + 64 - ($row * 22);
            $this->miniSymbol((string) $kind, $cx, $cy);
            $this->text($cx + 24, $cy - 2, $this->shorten($label, 31), 5.1, 'F2', [0.19, 0.32, 0.38]);
            $legendIndex++;
        }
    }

    /** @param array<string, mixed> $graph */
    private function titleBlock(Project $project, ProjectSldRevision $revision, array $graph, array $sheet = []): void
    {
        $x = 46.0;
        $y = 32.0;
        $w = self::WIDTH - 92;
        $h = 82.0;
        $this->rect($x, $y, $w, $h, [1, 1, 1], true);
        $this->rect($x, $y, $w, $h, [0.08, 0.23, 0.3], false, 0.85);

        $columns = [0, 520, 750, 890, 995, $w];
        foreach (array_slice($columns, 1, -1) as $offset) {
            $this->line($x + $offset, $y, $x + $offset, $y + $h, [0.55, 0.66, 0.69], 0.55);
        }
        $this->line($x, $y + 40, $x + $w, $y + 40, [0.55, 0.66, 0.69], 0.55);

        $document = is_array(data_get($graph, 'meta.document_control')) ? data_get($graph, 'meta.document_control') : [];
        $sheetNumber = (int) ($sheet['number'] ?? 1);
        $prefix = (string) data_get($document, 'drawing.number_prefix', (string) ($project->project_number ?: 'PROJECT').'-E-SLD');
        $drawingNumber = (string) data_get($document, 'drawing.register.'.($sheetNumber - 1).'.drawing_number', $prefix.'-'.str_pad((string) $sheetNumber, 3, '0', STR_PAD_LEFT));
        $paperRevision = (string) data_get($document, 'drawing.revision_code', 'R'.$revision->revision);
        $issueCode = (string) data_get($document, 'drawing.issue_code', 'DRAFT');
        $issueLabel = strtoupper((string) data_get($document, 'drawing.issue_label', 'DRAFT'));
        $client = (string) data_get($document, 'project.client', 'Client TBC');
        $site = (string) data_get($document, 'project.site_location', 'Site TBC');
        $notForConstruction = (bool) data_get($document, 'release.not_for_construction', true);

        $this->titleField($x + 12, $y + 54, 'PROJECT', $this->ascii((string) data_get($document, 'project.number', $project->project_number ?: 'PROJECT')));
        $this->text($x + 12, $y + 46, $this->shorten((string) data_get($document, 'project.name', $project->name ?: 'Untitled project'), 75), 5.2, 'F1', [0.2, 0.34, 0.4]);
        $this->titleField($x + 532, $y + 54, 'DRAWING TITLE', strtoupper($this->shorten((string) ($sheet['title'] ?? data_get($document, 'drawing.title', 'SINGLE-LINE DIAGRAM')), 34)));
        $this->text($x + 532, $y + 46, $this->shorten($client.' / '.$site, 34), 5.2, 'F1', [0.2, 0.34, 0.4]);
        $this->titleField($x + 762, $y + 54, 'DRAWING NUMBER', $drawingNumber);
        $this->titleField($x + 902, $y + 54, 'REVISION', $paperRevision.' / '.$issueCode);
        $this->titleField($x + 1007, $y + 54, 'SHEET', (string) $sheetNumber.' / '.(string) ((int) ($sheet['count'] ?? 1)));

        $this->titleField($x + 12, $y + 14, 'DOCUMENT STATUS', $notForConstruction ? $issueLabel.' / NOT FOR CONSTRUCTION' : $issueLabel);
        $this->titleField($x + 532, $y + 14, 'GENERATED', $this->ascii(optional($revision->generated_at)->format('d M Y H:i') ?? now()->format('d M Y H:i')));
        $this->titleField($x + 762, $y + 14, 'SCHEMA', $this->ascii((string) ($graph['schema_version'] ?? 'nexorin-sld-v1')));
        $checkStatus = strtoupper((string) data_get($graph, 'electrical_validation.status', 'PENDING'));
        $checkValue = sprintf(
            '%s %dE/%dW',
            substr($checkStatus, 0, 1),
            (int) data_get($graph, 'electrical_validation.summary.error', 0),
            (int) data_get($graph, 'electrical_validation.summary.warning', 0)
        );
        $this->titleField($x + 902, $y + 14, 'AUTO CHECK', $checkValue.' / ENG PENDING');
        $this->titleField($x + 1007, $y + 14, 'SIZE', 'A3');
    }

    private function titleField(float $x, float $y, string $label, string $value): void
    {
        $this->text($x, $y + 13, $label, 4.8, 'F2', [0.35, 0.47, 0.52]);
        $this->text($x, $y, $this->shorten($value, 54), 5.8, 'F3', [0.04, 0.15, 0.22]);
    }

    private function miniSymbol(string $id, float $cx, float $cy): void
    {
        $scale = 0.45;
        $this->commands[] = sprintf('q %.3F 0 0 %.3F %.2F %.2F cm', $scale, $scale, $cx * (1 - $scale), $cy * (1 - $scale));
        $this->symbol($id, $cx, $cy);
        $this->commands[] = 'Q';
    }

    private function groundSymbol(float $x, float $y): void
    {
        $color = [0.04, 0.16, 0.23];
        $this->line($x, $y + 16, $x, $y, $color, 1.25);
        $this->line($x - 14, $y, $x + 14, $y, $color, 1.25);
        $this->line($x - 10, $y - 6, $x + 10, $y - 6, $color, 1.0);
        $this->line($x - 6, $y - 12, $x + 6, $y - 12, $color, 0.8);
    }

    /** @param array<int, array{0:float,1:float}> $points */
    private function polygon(array $points, array $color, float $width): void
    {
        if ($points === []) {
            return;
        }
        $first = array_shift($points);
        $command = sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F m', $color[0], $color[1], $color[2], $width, $first[0], $first[1]);
        foreach ($points as $point) {
            $command .= sprintf(' %.2F %.2F l', $point[0], $point[1]);
        }
        $this->commands[] = $command.' h S';
    }

    private function wave(float $x, float $y, float $width, float $height, array $color, float $stroke): void
    {
        $x2 = $x + $width;
        $mid = $x + ($width / 2);
        $this->commands[] = sprintf(
            '%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c S',
            $color[0], $color[1], $color[2], $stroke,
            $x, $y,
            $x + ($width * .2), $y + $height,
            $mid - ($width * .1), $y + $height,
            $mid, $y,
            $mid + ($width * .1), $y - $height,
            $x2 - ($width * .2), $y - $height,
            $x2, $y
        );
    }

    private function leaderArrow(float $tipX, float $tipY, float $tailX, float $tailY, array $color): void
    {
        $dx = $tailX - $tipX;
        $dy = $tailY - $tipY;
        $length = hypot($dx, $dy);
        if ($length < 0.01) return;

        $ux = $dx / $length;
        $uy = $dy / $length;
        $baseX = $tipX + ($ux * 9.0);
        $baseY = $tipY + ($uy * 9.0);
        $px = -$uy * 4.2;
        $py = $ux * 4.2;
        $this->commands[] = sprintf(
            '%.3F %.3F %.3F rg %.2F %.2F m %.2F %.2F l %.2F %.2F l h f',
            $color[0], $color[1], $color[2],
            $tipX, $tipY,
            $baseX + $px, $baseY + $py,
            $baseX - $px, $baseY - $py
        );
    }

    private function arrow(float $x, float $y, array $color): void
    {
        $this->commands[] = sprintf(
            '%.3F %.3F %.3F rg %.2F %.2F m %.2F %.2F l %.2F %.2F l h f',
            $color[0], $color[1], $color[2],
            $x, $y,
            $x - 9, $y + 4.5,
            $x - 9, $y - 4.5
        );
    }

    private function dashedLine(float $x1, float $y1, float $x2, float $y2, array $color, float $width, array $dash): void
    {
        $this->commands[] = sprintf(
            'q [%.2F %.2F] 0 d %.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S Q',
            $dash[0], $dash[1], $color[0], $color[1], $color[2], $width, $x1, $y1, $x2, $y2
        );
    }

    private function text(float $x, float $y, string $value, float $size, string $font, array $color, string $align = 'left'): void
    {
        $value = $this->pdfEscape($this->ascii($value));
        if ($align === 'center') {
            $width = strlen(str_replace('\\', '', $value)) * ($size * .48);
            $x -= $width / 2;
        }
        $this->commands[] = sprintf(
            'BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET',
            $font,
            $size,
            $color[0],
            $color[1],
            $color[2],
            $x,
            $y,
            $value
        );
    }

    private function rect(float $x, float $y, float $w, float $h, array $color, bool $fill, float $lineWidth = 1, ?array $stroke = null): void
    {
        if ($fill) {
            $this->commands[] = sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f', $color[0], $color[1], $color[2], $x, $y, $w, $h);
            return;
        }

        $stroke ??= $color;
        $this->commands[] = sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re S', $stroke[0], $stroke[1], $stroke[2], $lineWidth, $x, $y, $w, $h);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $color, float $width = 1): void
    {
        $this->commands[] = sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S', $color[0], $color[1], $color[2], $width, $x1, $y1, $x2, $y2);
    }

    private function ellipse(float $x, float $y, float $rx, float $ry, array $stroke, float $width, ?array $dash = null): void
    {
        $kx = .5522848 * $rx;
        $ky = .5522848 * $ry;
        $dashCommand = $dash !== null ? sprintf('[%.2F %.2F] 0 d ', $dash[0], $dash[1]) : '';
        $this->commands[] = sprintf(
            'q %s%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c S Q',
            $dashCommand,
            $stroke[0], $stroke[1], $stroke[2], $width,
            $x + $rx, $y,
            $x + $rx, $y + $ky, $x + $kx, $y + $ry, $x, $y + $ry,
            $x - $kx, $y + $ry, $x - $rx, $y + $ky, $x - $rx, $y,
            $x - $rx, $y - $ky, $x - $kx, $y - $ry, $x, $y - $ry,
            $x + $kx, $y - $ry, $x + $rx, $y - $ky, $x + $rx, $y
        );
    }

    private function circle(float $x, float $y, float $r, array $fill, array $stroke, float $width): void
    {
        $k = .5522848 * $r;
        $this->commands[] = sprintf(
            'q %.3F %.3F %.3F rg %.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c B Q',
            $fill[0], $fill[1], $fill[2],
            $stroke[0], $stroke[1], $stroke[2],
            $width,
            $x + $r, $y,
            $x + $r, $y + $k, $x + $k, $y + $r, $x, $y + $r,
            $x - $k, $y + $r, $x - $r, $y + $k, $x - $r, $y,
            $x - $r, $y - $k, $x - $k, $y - $r, $x, $y - $r,
            $x + $k, $y - $r, $x + $r, $y - $k, $x + $r, $y
        );
    }

    /** @param array<int, array<int, string>> $pages */
    private function buildPdf(array $pages): string
    {
        $pageCount = max(1, count($pages));
        $pageStart = 3;
        $contentStart = $pageStart + $pageCount;
        $fontStart = $contentStart + $pageCount;
        $font1 = $fontStart;
        $font2 = $fontStart + 1;
        $font3 = $fontStart + 2;
        $infoObject = $fontStart + 3;

        $kids = [];
        for ($index = 0; $index < $pageCount; $index++) $kids[] = ($pageStart + $index).' 0 R';

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        for ($index = 0; $index < $pageCount; $index++) {
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::WIDTH.' '.self::HEIGHT.'] /Resources << /Font << /F1 '.$font1.' 0 R /F2 '.$font2.' 0 R /F3 '.$font3.' 0 R >> >> /Contents '.($contentStart + $index).' 0 R >>';
        }
        foreach ($pages as $commands) {
            $stream = implode("
", $commands)."
";
            $objects[] = "<< /Length ".strlen($stream)." >>
stream
".$stream."endstream";
        }
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
        $objects[] = sprintf(
            '<< /Producer (%s) /Subject (%s) /Keywords (%s) >>',
            $this->pdfEscape('NEXORIN Smart PV APP'.SldVisualRegression::VERSION),
            $this->pdfEscape('Controlled SLD visual regression evidence'),
            $this->pdfEscape(implode(' ', array_filter([
                'APP'.SldVisualRegression::VERSION,
                SldVisualRegression::CONTRACT,
                $this->visualFingerprint,
                'status:'.$this->visualStatus,
            ]))),
        );

        $pdf = "%PDF-1.4
%âãÏÓ
% NEXORIN APP".SldVisualRegression::VERSION." visual ".$this->visualFingerprint." ".$this->visualStatus."
";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj
".$object."
endobj
";
        }
        $xref = strlen($pdf);
        $pdf .= "xref
0 ".(count($objects) + 1)."
0000000000 65535 f 
";
        for ($index = 1; $index <= count($objects); $index++) $pdf .= sprintf("%010d 00000 n 
", $offsets[$index]);
        $pdf .= "trailer
<< /Size ".(count($objects) + 1)." /Root 1 0 R /Info ".$infoObject." 0 R >>
startxref
".$xref."
%%EOF";
        return $pdf;
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

    private function connectionId(array $connection, int $index): string
    {
        if (isset($connection['id']) && trim((string) $connection['id']) !== '') {
            return (string) $connection['id'];
        }

        return 'source_'.($connection['from'] ?? 'from').'__'.($connection['to'] ?? 'to').'__'.$index;
    }

    private function drawingNumber(Project $project): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', (string) ($project->project_number ?: 'PROJECT')) ?? 'PROJECT');
        return trim($base, '-').'-E-SLD-001';
    }

    private function humanize(string $id): string
    {
        return match ($id) {
            'pv_array' => 'PV array',
            'dc_protection' => 'DC protection',
            'inverter' => 'Inverter',
            'ac_protection' => 'AC protection',
            'meter' => 'Energy meter',
            'grid' => 'Utility grid',
            default => ucfirst(str_replace('_', ' ', $id)),
        };
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }

    private function ascii(string $value): string
    {
        return strtr($value, [
            '×' => 'x', '·' => '.', '–' => '-', '—' => '-', '…' => '...', '°' => ' deg ',
            'Φ' => 'ph', 'φ' => 'ph', '≥' => '>=', '≤' => '<=',
        ]);
    }

    private function number(mixed $value, int $decimals): string
    {
        if (! is_numeric($value)) {
            return '-';
        }
        $formatted = number_format((float) $value, $decimals, '.', ',');
        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    private function shorten(string $value, int $limit): string
    {
        $value = trim($this->ascii($value));
        return strlen($value) <= $limit ? $value : rtrim(substr($value, 0, max($limit - 3, 1))).'...';
    }
}
