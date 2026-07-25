import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
    routeOrthogonalBatch,
    SLD_ORTHOGONAL_ROUTING_CONTRACT,
    SLD_SMART_ROUTER_VERSION,
} from '../resources/js/sld-smart-router.js';

let passed = 0;
function check(name, callback) {
    callback();
    passed += 1;
    console.log(`[APP67M.2] PASS ${passed}/6 · ${name}`);
}

const editor = readFileSync(new URL('../resources/js/nexorin-sld-editor.js', import.meta.url), 'utf8');
const view = readFileSync(new URL('../resources/views/slds/editor.blade.php', import.meta.url), 'utf8');
const controller = readFileSync(new URL('../app/Http/Controllers/ProjectSldController.php', import.meta.url), 'utf8');
const renderer = readFileSync(new URL('../app/Services/Sld/SldSvgRenderer.php', import.meta.url), 'utf8');
const runner = readFileSync(new URL('./run-sld-e2e.mjs', import.meta.url), 'utf8');

check('published router contract is APP67M.2', () => {
    assert.equal(SLD_SMART_ROUTER_VERSION, '67M.2');
    assert.equal(SLD_ORTHOGONAL_ROUTING_CONTRACT.name, 'deterministic-orthogonal-channel-v2');
    assert.equal(SLD_ORTHOGONAL_ROUTING_CONTRACT.engine, 'orthogonal-channel-a-star');
});

check('network-priority batch is deterministic', () => {
    const requests = [
        { id: 'SIG', source: { id: 's3', x: 20, y: 180, side: 'right' }, target: { id: 't3', x: 260, y: 180, side: 'left' }, network: 'communication' },
        { id: 'AC', source: { id: 's2', x: 20, y: 120, side: 'right' }, target: { id: 't2', x: 260, y: 120, side: 'left' }, network: 'ac_power' },
        { id: 'DC', source: { id: 's1', x: 20, y: 60, side: 'right' }, target: { id: 't1', x: 260, y: 60, side: 'left' }, network: 'dc_power' },
    ];
    const forward = routeOrthogonalBatch(requests);
    const reverse = routeOrthogonalBatch([...requests].reverse());
    assert.deepEqual(forward, reverse);
    assert.deepEqual(forward.routes.map(({ id }) => id), ['DC', 'AC', 'SIG']);
    assert.equal(forward.summary.terminal_direction_violations, 0);
    assert.equal(forward.summary.orthogonal_wires, 3);
});

check('editor uses batch routing and persists evidence', () => {
    assert.match(editor, /routeOrthogonalBatch\(requests/);
    assert.match(editor, /route_contract: result\.contract/);
    assert.match(editor, /route_signature: result\.signature/);
    assert.match(editor, /terminal_direction_violations:/);
    assert.match(editor, /separation_conflicts:/);
    assert.match(editor, /graph\.batchUpdate\(\(\) => syncConnectedWires\(null, true\)\)/);
});

check('server normalization uses canonical constants', () => {
    assert.match(controller, /SldSmartRouter::VERSION/);
    assert.match(controller, /SldSmartRouter::CONTRACT/);
    assert.match(controller, /SldSmartRouter::ENGINE/);
    assert.match(controller, /route_terminal_direction_ok/);
    assert.match(controller, /route_separation_conflicts/);
});

check('SVG export publishes route integrity metadata', () => {
    assert.match(renderer, /data-route-contract/);
    assert.match(renderer, /data-route-signature/);
    assert.match(renderer, /data-route-orthogonal/);
    assert.match(renderer, /data-route-terminal-direction-ok/);
    assert.match(renderer, /data-route-separation-conflicts/);
});

check('UI and browser gate are versioned for APP67M.2', () => {
    assert.match(view, /\?v=67m[23]/);
    assert.match(view, /data-editor-routing-version>ROUTE 67M\.2/);
    assert.match(runner, /orthogonal-routing\.spec\.mjs', \[\], 4/);
    const suiteTests = [...runner.matchAll(/\['[^']+',\s*'[^']+',\s*\[[^\]]*\],\s*(\d+)\]/g)]
        .reduce((total, match) => total + Number(match[1]), 0);
    assert.ok(suiteTests >= 82);
});

console.log(`[APP67M.2] Orthogonal Routing JS contract · ${passed}/6 passed`);
