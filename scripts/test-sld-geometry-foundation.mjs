import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
    SLD_GEOMETRY_CONTRACT,
    SLD_GEOMETRY_FOUNDATION_VERSION,
    alignGeometryRectangles,
    canonicalPortPoint,
    diagnoseGeometry,
    distributeGeometryRectangles,
    normalizeGeometryRect,
} from '../resources/js/sld-geometry-foundation.js';

let passed = 0;
function test(name, callback) {
    callback();
    passed += 1;
    console.log(`[PASS] ${name}`);
}

test('normalizes equipment geometry against the canonical envelope', () => {
    const rect = normalizeGeometryRect({ x: 13.26, y: 27.84, w: 40, h: 20 });
    assert.deepEqual(rect, { id: null, x: 13.3, y: 27.8, w: 80, h: 50 });
});

test('aligns and distributes geometry deterministically', () => {
    const source = [
        { id: 'a', x: 0, y: 0, w: 100, h: 80 },
        { id: 'b', x: 180, y: 40, w: 100, h: 80 },
        { id: 'c', x: 400, y: 80, w: 100, h: 80 },
    ];
    const aligned = alignGeometryRectangles(source, 'middle', { snap: false });
    assert.deepEqual(aligned.map((item) => item.y), [40, 40, 40]);
    const distributed = distributeGeometryRectangles(source, 'horizontal', { snap: false });
    assert.equal(distributed.gap, 100);
    assert.deepEqual(distributed.items.map((item) => item.x), [0, 200, 400]);
});

test('maps a transformed engineering port from normalized anchors', () => {
    const point = canonicalPortPoint(
        { x: 100, y: 200, w: 200, h: 100 },
        { side: 'right', anchor: { x: 1, y: 0.5 } },
        { rotation: 90 },
    );
    assert.equal(point.x, 200);
    assert.equal(point.y, 350);
    assert.equal(point.side, 'bottom');
});

test('ships the APP67M.1 contract in the production browser bundle', () => {
    const bundle = readFileSync(new URL('../public/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
    assert.match(bundle, /67M\.1/);
    assert.match(bundle, /version:16/);
    assert.match(bundle, /geometryContract/);
    assert.match(bundle, /geometryDiagnostics/);
});

test('reports overlap, canvas, grid, and clearance diagnostics', () => {
    const report = diagnoseGeometry([
        { id: 'a', x: 0, y: 0, w: 100, h: 100 },
        { id: 'b', x: 80, y: 0, w: 100, h: 100 },
        { id: 'c', x: 1630, y: 920, w: 100, h: 100 },
        { id: 'd', x: 195, y: 0, w: 100, h: 100 },
    ]);
    assert.equal(report.version, SLD_GEOMETRY_FOUNDATION_VERSION);
    assert.equal(report.contract, SLD_GEOMETRY_CONTRACT.name);
    assert.equal(report.summary.overlaps, 1);
    assert.equal(report.summary.outside_canvas, 1);
    assert.ok(report.summary.off_grid >= 1);
    assert.ok(report.summary.clearance >= 1);
});

console.log(`[APP67M.1] Geometry Foundation JS · ${passed}/5 passed`);
