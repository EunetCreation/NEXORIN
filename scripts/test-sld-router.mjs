import assert from 'node:assert/strict';
import {
    routeCrossingCount,
    routeIntersectsObstacles,
    routeIsOrthogonal,
    routeOrthogonal,
    routeOrthogonalBatch,
    routingNetworkClass,
    routingNetworkPriority,
    segmentsFromRoute,
    SLD_ORTHOGONAL_ROUTING_CONTRACT,
    SLD_SMART_ROUTER_VERSION,
} from '../resources/js/sld-smart-router.js';

const obstacle = { id: 'equipment-middle', left: 90, top: 20, right: 150, bottom: 100 };
const base = {
    source: { id: 'source', x: 20, y: 60, side: 'right' },
    target: { id: 'target', x: 220, y: 60, side: 'left' },
    network: 'ac_power',
    gridSize: 10,
    clearance: 12,
    channelSpacing: 12,
};

const routed = routeOrthogonal({ ...base, obstacles: [obstacle] });
assert.equal(routed.version, SLD_SMART_ROUTER_VERSION);
assert.equal(routed.contract, SLD_ORTHOGONAL_ROUTING_CONTRACT.name);
assert.equal(routed.strategy, SLD_ORTHOGONAL_ROUTING_CONTRACT.engine);
assert.equal(routed.network_class, 'ac');
assert.match(routed.signature, /^rt-[0-9a-f]{8}$/);
assert.equal(routeIsOrthogonal(routed.fullPoints), true);
assert.equal(routed.orthogonal, true);
assert.equal(routed.terminal_direction_ok, true);
assert.equal(routed.source_direction_ok, true);
assert.equal(routed.target_direction_ok, true);
assert.equal(routeIntersectsObstacles(routed.fullPoints, [obstacle], 12), false);
assert.equal(routed.clearance_conflicts, 0);
assert.equal(routed.crossings, 0);
assert.ok(routed.bends >= 2);

const existing = [{ a: { x: 110, y: 20 }, b: { x: 110, y: 100 }, network: 'communication', wireId: 'COM-01' }];
const crossingAware = routeOrthogonal({ ...base, existingSegments: existing });
assert.equal(routeIsOrthogonal(crossingAware.fullPoints), true);
assert.equal(routeCrossingCount(crossingAware.fullPoints, existing), 0);
assert.equal(crossingAware.crossings, 0);
assert.ok(crossingAware.length > 200, 'The route should intentionally detour around the existing wire.');

const deterministicA = routeOrthogonal({ ...base, obstacles: [obstacle], existingSegments: existing });
const deterministicB = routeOrthogonal({ ...base, obstacles: [obstacle], existingSegments: existing });
assert.deepEqual(deterministicA, deterministicB);
assert.equal(deterministicA.signature, deterministicB.signature);

const impossibleBand = { id: 'wall', left: 40, top: -500, right: 200, bottom: 500 };
const fallback = routeOrthogonal({ ...base, obstacles: [impossibleBand], clearance: 12 });
assert.equal(routeIsOrthogonal(fallback.fullPoints), true);
assert.equal(fallback.strategy, 'perimeter-fallback');
assert.ok(fallback.clearance_conflicts > 0, 'An impossible corridor must be reported, not silently marked clear.');

const sharedTerminalRoute = [{ x: 0, y: 0 }, { x: 50, y: 0 }];
const sharedTerminalWire = [{ a: { x: 0, y: 0 }, b: { x: 0, y: 50 }, network: 'protective_earth', wireId: 'PE-01' }];
assert.equal(routeCrossingCount(sharedTerminalRoute, sharedTerminalWire), 0, 'A true shared terminal must not be reported as a crossing.');
const internalTeeRoute = [{ x: -50, y: 0 }, { x: 50, y: 0 }];
assert.equal(routeCrossingCount(internalTeeRoute, sharedTerminalWire), 1, 'An unmodelled T-intersection inside a route must remain a crossing.');

const segments = segmentsFromRoute(routed.fullPoints, 'ac_power', 'AC-01');
assert.equal(segments.length, routed.fullPoints.length - 1);
assert.ok(segments.every((segment) => segment.network === 'ac_power' && segment.wireId === 'AC-01'));
assert.equal(routingNetworkClass('protective-earth'), 'earth');
assert.ok(routingNetworkPriority('dc_power') < routingNetworkPriority('communication'));

const batchRequests = [
    { id: 'SIG-02', ...base, network: 'communication', source: { id: 'sig-source', x: 20, y: 180, side: 'right' }, target: { id: 'sig-target', x: 260, y: 180, side: 'left' }, obstacles: [] },
    { id: 'AC-02', ...base, network: 'ac_power', source: { id: 'ac-source', x: 20, y: 120, side: 'right' }, target: { id: 'ac-target', x: 260, y: 120, side: 'left' }, obstacles: [] },
    { id: 'DC-02', ...base, network: 'dc_power', source: { id: 'dc-source', x: 20, y: 60, side: 'right' }, target: { id: 'dc-target', x: 260, y: 60, side: 'left' }, obstacles: [] },
];
const batchA = routeOrthogonalBatch(batchRequests);
const batchB = routeOrthogonalBatch([...batchRequests].reverse());
assert.equal(batchA.version, SLD_SMART_ROUTER_VERSION);
assert.equal(batchA.contract, SLD_ORTHOGONAL_ROUTING_CONTRACT.name);
assert.equal(batchA.engine, SLD_ORTHOGONAL_ROUTING_CONTRACT.engine);
assert.deepEqual(batchA.routes.map(({ id }) => id), ['DC-02', 'AC-02', 'SIG-02']);
assert.deepEqual(batchA, batchB, 'Batch routing must be independent of request input order.');
assert.equal(batchA.summary.wires, 3);
assert.equal(batchA.summary.orthogonal_wires, 3);
assert.equal(batchA.summary.terminal_direction_violations, 0);
assert.ok(batchA.routes.every((route) => route.signature && route.terminal_direction_ok && route.orthogonal));

let stressSeed = 0x67_0a_0b;
const random = () => {
    stressSeed = (1664525 * stressSeed + 1013904223) >>> 0;
    return stressSeed / 0x1_0000_0000;
};
for (let caseIndex = 0; caseIndex < 80; caseIndex += 1) {
    const stressObstacles = Array.from({ length: 1 + Math.floor(random() * 4) }, (_item, obstacleIndex) => {
        const left = 110 + Math.floor(random() * 360);
        const top = 20 + Math.floor(random() * 240);
        const width = 40 + Math.floor(random() * 70);
        const height = 40 + Math.floor(random() * 90);
        return { id: `stress-${caseIndex}-${obstacleIndex}`, left, top, right: left + width, bottom: top + height };
    });
    const stressBase = {
        source: { id: `source-${caseIndex}`, x: 20, y: 40 + Math.floor(random() * 220), side: 'right' },
        target: { id: `target-${caseIndex}`, x: 620, y: 40 + Math.floor(random() * 220), side: 'left' },
        obstacles: stressObstacles,
        network: ['dc_power', 'ac_power', 'protective_earth', 'communication'][caseIndex % 4],
        gridSize: 10,
        clearance: [12, 18, 26][caseIndex % 3],
        channelSpacing: [9, 12, 16][caseIndex % 3],
    };
    const stressRoute = routeOrthogonal(stressBase);
    assert.deepEqual(stressRoute, routeOrthogonal(stressBase), `Stress case ${caseIndex} is not deterministic.`);
    assert.equal(routeIsOrthogonal(stressRoute.fullPoints), true, `Stress case ${caseIndex} contains a diagonal segment.`);
    assert.equal(stressRoute.terminal_direction_ok, true, `Stress case ${caseIndex} violates terminal lead direction.`);
    assert.match(stressRoute.signature, /^rt-[0-9a-f]{8}$/, `Stress case ${caseIndex} has no stable signature.`);
    assert.ok(stressRoute.fullPoints.every((point) => Number.isFinite(point.x) && Number.isFinite(point.y)), `Stress case ${caseIndex} contains a non-finite point.`);
    if (stressRoute.clearance_conflicts === 0) {
        assert.equal(routeIntersectsObstacles(stressRoute.fullPoints, stressObstacles, stressBase.clearance), false, `Stress case ${caseIndex} silently violates clearance.`);
    }
}

console.log(`[APP67M.2] JS PASS · ${SLD_SMART_ROUTER_VERSION} · deterministic orthogonal channel routing, terminal leads, network priority, route signatures, batch stability, and 80 stress cases`);
