import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
    SLD_VISUAL_REGRESSION_CONTRACT,
    SLD_VISUAL_REGRESSION_VERSION,
    auditVisualScene,
    compareVisualSnapshots,
    createVisualSnapshot,
} from '../resources/js/sld-visual-regression.js';

let passed = 0;
function check(name, callback) {
    callback();
    passed += 1;
    console.log(`[APP67M.4] PASS ${passed}/6 · ${name}`);
}

const editor = readFileSync(new URL('../resources/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const controller = readFileSync(new URL('../app/Http/Controllers/ProjectSldController.php', import.meta.url), 'utf8');
const renderer = readFileSync(new URL('../app/Services/Sld/SldSvgRenderer.php', import.meta.url), 'utf8');
const view = readFileSync(new URL('../resources/views/slds/editor.blade.php', import.meta.url), 'utf8');
const runner = readFileSync(new URL('./run-sld-e2e.mjs', import.meta.url), 'utf8');

const positions = {
    A: { x: 80, y: 80, w: 120, h: 80, rotation: 0, flip_h: false, flip_v: false },
    B: { x: 360, y: 80, w: 120, h: 80, rotation: 0, flip_h: false, flip_v: false },
};
const routes = {
    W1: [{ x: 200, y: 120 }, { x: 280, y: 120 }, { x: 280, y: 120 }, { x: 360, y: 120 }],
};
const labels = {
    positions: {
        A: { x: 80, y: 174, w: 120, h: 48, side: 'bottom', leader: true, signature: 'lp-11111111' },
        B: { x: 360, y: 174, w: 120, h: 48, side: 'bottom', leader: true, signature: 'lp-22222222' },
    },
};

check('published visual-regression contract is APP67M.4', () => {
    assert.equal(SLD_VISUAL_REGRESSION_VERSION, '67M.4');
    assert.equal(SLD_VISUAL_REGRESSION_CONTRACT.name, 'deterministic-scene-fingerprint-v1');
    assert.equal(SLD_VISUAL_REGRESSION_CONTRACT.engine, 'canonical-scene-auditor');
});

check('canonical scene fingerprint is deterministic and order-independent', () => {
    const forward = createVisualSnapshot({ positions, routes, labelPlacement: labels, canvas: { x: 0, y: 0, w: 900, h: 500 } });
    const reverse = createVisualSnapshot({
        positions: Object.fromEntries(Object.entries(positions).reverse()),
        routes: Object.fromEntries(Object.entries(routes).reverse()),
        labelPlacement: { positions: Object.fromEntries(Object.entries(labels.positions).reverse()) },
        canvas: { x: 0, y: 0, w: 900, h: 500 },
    });
    assert.match(forward.fingerprint, /^vr-[0-9a-f]{8}$/);
    assert.equal(forward.fingerprint, reverse.fingerprint);
    const unicode = createVisualSnapshot({
        positions: { 'Beban-α': { x: 80, y: 80, w: 120, h: 80 } },
        routes: {}, labelPlacement: { positions: {} }, canvas: { x: 0, y: 0, w: 900, h: 500 },
    });
    assert.equal(unicode.fingerprint, 'vr-869817e5');
});

check('visual audit detects overlap, clipping, and non-orthogonal route findings', () => {
    const report = auditVisualScene({
        equipment: [
            { id: 'A', x: 80, y: 80, w: 120, h: 80 },
            { id: 'B', x: 160, y: 80, w: 120, h: 80 },
        ],
        labels: [{ id: 'A', x: 100, y: 100, w: 100, h: 40, signature: 'lp-11111111' }],
        routes: [{ id: 'W1', points: [{ x: 10, y: 10 }, { x: 60, y: 40 }], signature: 'rt-11111111' }],
        canvas: { x: 0, y: 0, w: 240, h: 180 },
    });
    assert.equal(report.status, 'error');
    assert.ok(report.summary.overlaps > 0);
    assert.ok(report.summary.non_orthogonal_routes > 0);
    assert.ok(report.summary.issues >= 3);
});

check('snapshot comparison honors coordinate tolerance and reports real changes', () => {
    const baseline = createVisualSnapshot({ positions, routes, labelPlacement: labels, canvas: { x: 0, y: 0, w: 900, h: 500 } });
    const within = createVisualSnapshot({ positions: { ...positions, A: { ...positions.A, x: 80.4 } }, routes, labelPlacement: labels, canvas: { x: 0, y: 0, w: 900, h: 500 } });
    const routeWithin = createVisualSnapshot({ positions, routes: { W1: routes.W1.map((point, index) => index === 1 ? { ...point, x: point.x + 0.4 } : point) }, labelPlacement: labels, canvas: { x: 0, y: 0, w: 900, h: 500 } });
    const changed = createVisualSnapshot({ positions: { ...positions, A: { ...positions.A, x: 100 } }, routes, labelPlacement: labels, canvas: { x: 0, y: 0, w: 900, h: 500 } });
    const transformed = createVisualSnapshot({ positions: { ...positions, A: { ...positions.A, rotation: 90 } }, routes, labelPlacement: labels, canvas: { x: 0, y: 0, w: 900, h: 500 } });
    assert.equal(compareVisualSnapshots(baseline, within, 0.5).match, true);
    assert.equal(compareVisualSnapshots(baseline, routeWithin, 0.5).match, true);
    assert.equal(compareVisualSnapshots(baseline, changed, 0.5).match, false);
    assert.equal(compareVisualSnapshots(baseline, transformed, 0.5).match, false);
});

check('editor, persistence, and SVG export publish visual evidence', () => {
    assert.match(editor, /auditVisualScene\(visualRegressionScene\(\)\)/);
    assert.match(editor, /visual_regression: visualRegressionMetadata/);
    assert.match(controller, /visualRegression->normalizeMetadata/);
    assert.match(renderer, /data-visual-regression-fingerprint/);
    assert.match(renderer, /SldVisualRegression::VERSION/);
});

check('UI and browser gate are versioned for APP67M.4', () => {
    assert.match(view, /67m3-67m4/);
    assert.match(view, /data-editor-visual-regression-version>VISUAL 67M\.4/);
    assert.match(runner, /visual-regression\.spec\.mjs', \[\], 4/);
    const suiteTests = [...runner.matchAll(/\['[^']+',\s*'[^']+',\s*\[[^\]]*\],\s*(\d+)\]/g)]
        .reduce((total, match) => total + Number(match[1]), 0);
    assert.equal(suiteTests, 90);
});

console.log(`[APP67M.4] Visual Regression JS contract · ${passed}/6 passed`);
