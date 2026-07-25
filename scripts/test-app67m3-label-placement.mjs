import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
    labelRectanglesOverlap,
    placeEngineeringLabels,
    SLD_LABEL_PLACEMENT_CONTRACT,
    SLD_LABEL_PLACEMENT_VERSION,
} from '../resources/js/sld-label-placement.js';

let passed = 0;
function check(name, callback) {
    callback();
    passed += 1;
    console.log(`[APP67M.3] PASS ${passed}/6 · ${name}`);
}

const editor = readFileSync(new URL('../resources/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const controller = readFileSync(new URL('../app/Http/Controllers/ProjectSldController.php', import.meta.url), 'utf8');
const renderer = readFileSync(new URL('../app/Services/Sld/SldSvgRenderer.php', import.meta.url), 'utf8');
const view = readFileSync(new URL('../resources/views/slds/editor.blade.php', import.meta.url), 'utf8');
const runner = readFileSync(new URL('./run-sld-e2e.mjs', import.meta.url), 'utf8');

const items = [
    { id: 'A', x: 80, y: 80, w: 120, h: 80, tag: 'PV-01', title: 'PV array', rating: '12.5 kWp', ports: [{ side: 'right' }] },
    { id: 'B', x: 360, y: 80, w: 120, h: 80, tag: 'INV-01', title: 'Inverter', rating: '10 kW', ports: [{ side: 'left' }, { side: 'right' }] },
    { id: 'C', x: 640, y: 80, w: 120, h: 80, tag: 'MDB-01', title: 'Distribution board', rating: '400 V', ports: [{ side: 'left' }] },
];
const wires = [
    { a: { x: 200, y: 120 }, b: { x: 360, y: 120 }, network: 'dc_power' },
    { a: { x: 480, y: 120 }, b: { x: 640, y: 120 }, network: 'ac_power' },
];

check('published label placement contract is APP67M.3', () => {
    assert.equal(SLD_LABEL_PLACEMENT_VERSION, '67M.3');
    assert.equal(SLD_LABEL_PLACEMENT_CONTRACT.name, 'deterministic-clearance-label-v1');
    assert.equal(SLD_LABEL_PLACEMENT_CONTRACT.engine, 'candidate-score-label-placer');
});

check('clear layout produces one deterministic label per equipment', () => {
    const result = placeEngineeringLabels(items, wires, { canvas: { x: 0, y: 0, w: 900, h: 500 } });
    assert.equal(result.summary.labels, items.length);
    assert.equal(result.placements.length, items.length);
    assert.equal(new Set(result.placements.map((placement) => placement.id)).size, items.length);
    assert.ok(result.placements.every((placement) => /^lp-[0-9a-f]{8}$/.test(placement.signature)));
    assert.deepEqual(
        Object.fromEntries(result.placements.map((placement) => [placement.id, placement.signature])),
        { A: 'lp-3c4eac4f', B: 'lp-c0978401', C: 'lp-ac961b44' },
        'shared APP67M.3 label signatures changed',
    );
    for (let i = 0; i < result.placements.length; i += 1) {
        for (let j = i + 1; j < result.placements.length; j += 1) {
            assert.equal(labelRectanglesOverlap(result.placements[i].rect, result.placements[j].rect), false);
        }
    }
});

check('placement output is independent of input order', () => {
    const forward = placeEngineeringLabels(items, wires, { canvas: { x: 0, y: 0, w: 900, h: 500 } });
    const reverse = placeEngineeringLabels([...items].reverse(), [...wires].reverse(), { canvas: { x: 0, y: 0, w: 900, h: 500 } });
    assert.deepEqual(forward, reverse);
});

check('rotation and port density influence preferred label side', () => {
    const normal = placeEngineeringLabels([{ id: 'N', x: 200, y: 200, w: 120, h: 80, title: 'Normal', ports: [{ side: 'bottom' }] }]);
    const rotated = placeEngineeringLabels([{ id: 'R', x: 200, y: 200, w: 120, h: 80, title: 'Rotated', rotation: 90, ports: [{ side: 'right' }] }]);
    assert.notEqual(normal.placements[0].side, 'bottom');
    assert.equal(rotated.placements[0].side, 'right');
});

check('editor, persistence, and SVG export publish label evidence', () => {
    assert.match(editor, /placeEngineeringLabels\(items, existingWireSegments\(\)/);
    assert.match(editor, /label_placement:/);
    assert.match(editor, /dataLabelPlacementVersion|labelPlacementVersion/);
    assert.match(controller, /SldLabelPlacement::VERSION|labelPlacement->normalizeMetadata/);
    assert.match(renderer, /data-label-placement-contract/);
    assert.match(renderer, /sld-auto-label-leader/);
});

check('UI and browser gate are versioned for APP67M.3', () => {
    assert.match(view, /\?v=67m3/);
    assert.match(view, /data-editor-label-placement-version>LABEL 67M\.3/);
    assert.match(runner, /automatic-label-placement\.spec\.mjs', \[\], 4/);
    const suiteTests = [...runner.matchAll(/\['[^']+',\s*'[^']+',\s*\[[^\]]*\],\s*(\d+)\]/g)]
        .reduce((total, match) => total + Number(match[1]), 0);
    assert.ok(suiteTests >= 86);
});

console.log(`[APP67M.3] Automatic Label Placement JS contract · ${passed}/6 passed`);
