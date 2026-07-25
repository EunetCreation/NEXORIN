export const SLD_SMART_ROUTER_VERSION = '67M.2';
export const SLD_ORTHOGONAL_ROUTING_CONTRACT = Object.freeze({
    version: SLD_SMART_ROUTER_VERSION,
    name: 'deterministic-orthogonal-channel-v2',
    engine: 'orthogonal-channel-a-star',
    gridSize: 10,
    minimumLead: 24,
    maximumBends: 12,
});

const EPSILON = 0.001;
const DEFAULT_GRID = 10;
const DEFAULT_CLEARANCE = 18;
const DEFAULT_CHANNEL_SPACING = 12;
const DEFAULT_BEND_PENALTY = 34;
const DEFAULT_CROSSING_PENALTY = 360;

const NETWORK_PROFILE = Object.freeze({
    dc_positive: { className: 'dc', lane: -3, priority: 10 },
    dc_negative: { className: 'dc', lane: -2, priority: 11 },
    dc_power: { className: 'dc', lane: -2, priority: 12 },
    battery_positive: { className: 'dc', lane: -3, priority: 13 },
    battery_negative: { className: 'dc', lane: -2, priority: 14 },
    battery_dc: { className: 'dc', lane: -2, priority: 15 },
    ac_power: { className: 'ac', lane: 0, priority: 20 },
    neutral: { className: 'ac', lane: 1, priority: 21 },
    protective_earth: { className: 'earth', lane: 2, priority: 30 },
    functional_earth: { className: 'earth', lane: 2, priority: 31 },
    communication: { className: 'signal', lane: 4, priority: 40 },
    control: { className: 'signal', lane: 5, priority: 41 },
    measurement: { className: 'signal', lane: 6, priority: 42 },
    unclassified: { className: 'unclassified', lane: 0, priority: 90 },
});

function finite(value, fallback = 0) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
}

function snap(value, grid = DEFAULT_GRID) {
    const size = Math.max(1, finite(grid, DEFAULT_GRID));
    return Math.round(finite(value) / size) * size;
}

function normalizePoint(point) {
    return { x: finite(point?.x), y: finite(point?.y) };
}

function normalizeNetwork(network = 'unclassified') {
    const key = String(network || 'unclassified').toLowerCase().replace(/[\s-]+/g, '_');
    return Object.hasOwn(NETWORK_PROFILE, key) ? key : 'unclassified';
}

export function routingNetworkClass(network = 'unclassified') {
    return NETWORK_PROFILE[normalizeNetwork(network)].className;
}

export function routingNetworkPriority(network = 'unclassified') {
    return NETWORK_PROFILE[normalizeNetwork(network)].priority;
}

function routingLane(network = 'unclassified') {
    return NETWORK_PROFILE[normalizeNetwork(network)].lane;
}

function normalizeRect(rect, fallbackId = '') {
    const left = finite(rect?.left ?? rect?.x);
    const top = finite(rect?.top ?? rect?.y);
    const width = finite(rect?.width ?? rect?.w, finite(rect?.right) - left);
    const height = finite(rect?.height ?? rect?.h, finite(rect?.bottom) - top);
    const right = Number.isFinite(Number(rect?.right)) ? finite(rect.right) : left + Math.max(0, width);
    const bottom = Number.isFinite(Number(rect?.bottom)) ? finite(rect.bottom) : top + Math.max(0, height);
    return {
        id: String(rect?.id || fallbackId || ''),
        left: Math.min(left, right),
        top: Math.min(top, bottom),
        right: Math.max(left, right),
        bottom: Math.max(top, bottom),
        kind: String(rect?.kind || 'obstacle'),
    };
}

function expandRect(rect, amount) {
    return {
        ...rect,
        left: rect.left - amount,
        top: rect.top - amount,
        right: rect.right + amount,
        bottom: rect.bottom + amount,
    };
}

function pointInsideRect(point, rect, inclusive = false) {
    if (inclusive) {
        return point.x >= rect.left - EPSILON && point.x <= rect.right + EPSILON
            && point.y >= rect.top - EPSILON && point.y <= rect.bottom + EPSILON;
    }
    return point.x > rect.left + EPSILON && point.x < rect.right - EPSILON
        && point.y > rect.top + EPSILON && point.y < rect.bottom - EPSILON;
}

function rangesOverlap(a1, a2, b1, b2, inclusive = true) {
    const left = Math.max(Math.min(a1, a2), Math.min(b1, b2));
    const right = Math.min(Math.max(a1, a2), Math.max(b1, b2));
    return inclusive ? left <= right + EPSILON : left < right - EPSILON;
}

function segmentIntersectsRect(a, b, rect) {
    if (Math.abs(a.x - b.x) < EPSILON) {
        const x = a.x;
        if (x <= rect.left + EPSILON || x >= rect.right - EPSILON) return false;
        return rangesOverlap(a.y, b.y, rect.top, rect.bottom, false);
    }
    if (Math.abs(a.y - b.y) < EPSILON) {
        const y = a.y;
        if (y <= rect.top + EPSILON || y >= rect.bottom - EPSILON) return false;
        return rangesOverlap(a.x, b.x, rect.left, rect.right, false);
    }
    return true;
}

function segmentBlocked(a, b, obstacles) {
    return obstacles.some((rect) => segmentIntersectsRect(a, b, rect));
}

function orthogonalSegments(points = [], network = 'unclassified', wireId = '') {
    const result = [];
    for (let index = 0; index < points.length - 1; index += 1) {
        const a = normalizePoint(points[index]);
        const b = normalizePoint(points[index + 1]);
        if (Math.abs(a.x - b.x) > EPSILON && Math.abs(a.y - b.y) > EPSILON) continue;
        if (Math.abs(a.x - b.x) < EPSILON && Math.abs(a.y - b.y) < EPSILON) continue;
        result.push({ a, b, network: normalizeNetwork(network), wireId: String(wireId || '') });
    }
    return result;
}

function perpendicularCrossing(a, b, c, d) {
    const abVertical = Math.abs(a.x - b.x) < EPSILON;
    const cdVertical = Math.abs(c.x - d.x) < EPSILON;
    if (abVertical === cdVertical) return false;
    const vertical = abVertical ? [a, b] : [c, d];
    const horizontal = abVertical ? [c, d] : [a, b];
    const x = vertical[0].x;
    const y = horizontal[0].y;
    if (!rangesOverlap(vertical[0].y, vertical[1].y, y, y, true)) return false;
    if (!rangesOverlap(horizontal[0].x, horizontal[1].x, x, x, true)) return false;
    return true;
}

function sharedEndpointJunction(a, b, c, d, allowedJunctions = []) {
    const abVertical = Math.abs(a.x - b.x) < EPSILON;
    const cdVertical = Math.abs(c.x - d.x) < EPSILON;
    if (abVertical === cdVertical) return false;
    const vertical = abVertical ? [a, b] : [c, d];
    const horizontal = abVertical ? [c, d] : [a, b];
    const intersection = { x: vertical[0].x, y: horizontal[0].y };
    const isAllowed = allowedJunctions.some((point) => Math.abs(point.x - intersection.x) < EPSILON && Math.abs(point.y - intersection.y) < EPSILON);
    if (!isAllowed) return false;
    const onFirstEndpoint = [a, b].some((point) => Math.abs(point.x - intersection.x) < EPSILON && Math.abs(point.y - intersection.y) < EPSILON);
    const onSecondEndpoint = [c, d].some((point) => Math.abs(point.x - intersection.x) < EPSILON && Math.abs(point.y - intersection.y) < EPSILON);
    return onFirstEndpoint && onSecondEndpoint;
}

function parallelProximity(a, b, c, d, spacing) {
    const abVertical = Math.abs(a.x - b.x) < EPSILON;
    const cdVertical = Math.abs(c.x - d.x) < EPSILON;
    if (abVertical !== cdVertical) return 0;
    if (abVertical) {
        if (!rangesOverlap(a.y, b.y, c.y, d.y, false)) return 0;
        return Math.max(0, spacing - Math.abs(a.x - c.x));
    }
    if (!rangesOverlap(a.x, b.x, c.x, d.x, false)) return 0;
    return Math.max(0, spacing - Math.abs(a.y - c.y));
}

function sideVector(side = 'right') {
    return {
        left: { x: -1, y: 0 },
        right: { x: 1, y: 0 },
        top: { x: 0, y: -1 },
        bottom: { x: 0, y: 1 },
    }[String(side || 'right')] || { x: 1, y: 0 };
}

function leadPoint(point, side, distance, grid) {
    const vector = sideVector(side);
    const raw = { x: point.x + vector.x * distance, y: point.y + vector.y * distance };
    return Math.abs(vector.x) > EPSILON
        ? { x: snap(raw.x, grid), y: Number(point.y.toFixed(4)) }
        : { x: Number(point.x.toFixed(4)), y: snap(raw.y, grid) };
}

function terminalLeadMatches(origin, next, side) {
    if (!origin || !next) return false;
    const vector = sideVector(side);
    const dx = next.x - origin.x;
    const dy = next.y - origin.y;
    if (Math.abs(vector.x) > EPSILON) return Math.abs(dy) < EPSILON && Math.sign(dx || vector.x) === Math.sign(vector.x) && Math.abs(dx) > EPSILON;
    return Math.abs(dx) < EPSILON && Math.sign(dy || vector.y) === Math.sign(vector.y) && Math.abs(dy) > EPSILON;
}

function routeSignature(points = [], network = 'unclassified') {
    const text = `${SLD_SMART_ROUTER_VERSION}|${normalizeNetwork(network)}|${points.map((point) => `${finite(point.x).toFixed(2)},${finite(point.y).toFixed(2)}`).join(';')}`;
    let hash = 0;
    for (let index = 0; index < text.length; index += 1) hash = ((hash * 31) + text.charCodeAt(index)) % 2147483647;
    return `rt-${Math.trunc(hash).toString(16).padStart(8, '0')}`;
}

function uniqueSorted(values) {
    return [...new Set(values.filter(Number.isFinite).map((value) => Number(value.toFixed(4))))].sort((a, b) => a - b);
}

function reduceChannels(values, anchors, maxCount = 72) {
    if (values.length <= maxCount) return values;
    const preserved = new Set();
    anchors.forEach((anchor) => {
        let closest = values[0];
        values.forEach((value) => {
            if (Math.abs(value - anchor) < Math.abs(closest - anchor)) closest = value;
        });
        preserved.add(closest);
    });
    const scored = values.map((value) => ({
        value,
        score: Math.min(...anchors.map((anchor) => Math.abs(value - anchor))),
    })).sort((a, b) => a.score - b.score || a.value - b.value);
    scored.slice(0, Math.max(0, maxCount - preserved.size)).forEach(({ value }) => preserved.add(value));
    return [...preserved].sort((a, b) => a - b);
}

function compressRoute(points) {
    const clean = [];
    points.forEach((rawPoint) => {
        if (!rawPoint || !Number.isFinite(Number(rawPoint.x)) || !Number.isFinite(Number(rawPoint.y))) return;
        const point = { x: Number(finite(rawPoint.x).toFixed(4)), y: Number(finite(rawPoint.y).toFixed(4)) };
        const previous = clean.at(-1);
        if (previous && Math.abs(previous.x - point.x) < EPSILON && Math.abs(previous.y - point.y) < EPSILON) return;
        clean.push(point);
    });
    let index = 1;
    while (index < clean.length - 1) {
        const a = clean[index - 1];
        const b = clean[index];
        const c = clean[index + 1];
        const sameX = Math.abs(a.x - b.x) < EPSILON && Math.abs(b.x - c.x) < EPSILON;
        const sameY = Math.abs(a.y - b.y) < EPSILON && Math.abs(b.y - c.y) < EPSILON;
        if (sameX || sameY) clean.splice(index, 1);
        else index += 1;
    }
    return clean;
}

function crossingWeight(network, existingNetwork) {
    const currentClass = routingNetworkClass(network);
    const existingClass = routingNetworkClass(existingNetwork);
    if (currentClass === existingClass) return 0.78;
    const powerSignal = ['dc', 'ac'].includes(currentClass) && existingClass === 'signal'
        || ['dc', 'ac'].includes(existingClass) && currentClass === 'signal';
    return powerSignal ? 1.55 : 1.12;
}

function proximityWeight(network, existingNetwork) {
    if (normalizeNetwork(network) === normalizeNetwork(existingNetwork)) return 2.2;
    if (routingNetworkClass(network) === routingNetworkClass(existingNetwork)) return 5.4;
    return 10.8;
}

function routeMetrics(points, obstacles, existingSegments, network, spacing, sourceSide, targetSide) {
    const segments = orthogonalSegments(points, network);
    const allowedJunctions = points.length > 0 ? [points[0], points.at(-1)] : [];
    let length = 0;
    let crossings = 0;
    let obstacleConflicts = 0;
    let channelConflicts = 0;
    let separationConflicts = 0;
    segments.forEach((segment) => {
        length += Math.abs(segment.a.x - segment.b.x) + Math.abs(segment.a.y - segment.b.y);
        if (obstacles.some((rect) => segmentIntersectsRect(segment.a, segment.b, rect))) obstacleConflicts += 1;
        existingSegments.forEach((existing) => {
            if (perpendicularCrossing(segment.a, segment.b, existing.a, existing.b)
                && !sharedEndpointJunction(segment.a, segment.b, existing.a, existing.b, allowedJunctions)) crossings += 1;
            if (parallelProximity(segment.a, segment.b, existing.a, existing.b, spacing) > EPSILON) {
                if (routingNetworkClass(existing.network) === routingNetworkClass(network)) channelConflicts += 1;
                else separationConflicts += 1;
            }
        });
    });
    const orthogonal = segments.length === Math.max(0, points.length - 1);
    const sourceDirectionOk = points.length >= 2 && terminalLeadMatches(points[0], points[1], sourceSide);
    const targetDirectionOk = points.length >= 2 && terminalLeadMatches(points.at(-1), points.at(-2), targetSide);
    const clearanceConflicts = obstacleConflicts + channelConflicts + separationConflicts;
    return {
        length: Math.round(length),
        bends: Math.max(0, points.length - 2),
        crossings,
        obstacle_conflicts: obstacleConflicts,
        channel_conflicts: channelConflicts,
        separation_conflicts: separationConflicts,
        clearance_conflicts: clearanceConflicts,
        obstacle_free: obstacleConflicts === 0,
        orthogonal,
        terminal_direction_ok: sourceDirectionOk && targetDirectionOk,
        source_direction_ok: sourceDirectionOk,
        target_direction_ok: targetDirectionOk,
        network_class: routingNetworkClass(network),
        signature: routeSignature(points, network),
    };
}

function fallbackRoute(sourceLead, targetLead, obstacles, grid) {
    const candidates = [
        [sourceLead, { x: targetLead.x, y: sourceLead.y }, targetLead],
        [sourceLead, { x: sourceLead.x, y: targetLead.y }, targetLead],
    ];
    const offset = 60;
    const envelope = {
        left: Math.min(sourceLead.x, targetLead.x, ...obstacles.map((rect) => rect.left)) - offset,
        right: Math.max(sourceLead.x, targetLead.x, ...obstacles.map((rect) => rect.right)) + offset,
        top: Math.min(sourceLead.y, targetLead.y, ...obstacles.map((rect) => rect.top)) - offset,
        bottom: Math.max(sourceLead.y, targetLead.y, ...obstacles.map((rect) => rect.bottom)) + offset,
    };
    candidates.push(
        [sourceLead, { x: envelope.left, y: sourceLead.y }, { x: envelope.left, y: targetLead.y }, targetLead],
        [sourceLead, { x: envelope.right, y: sourceLead.y }, { x: envelope.right, y: targetLead.y }, targetLead],
        [sourceLead, { x: sourceLead.x, y: envelope.top }, { x: targetLead.x, y: envelope.top }, targetLead],
        [sourceLead, { x: sourceLead.x, y: envelope.bottom }, { x: targetLead.x, y: envelope.bottom }, targetLead],
    );
    const valid = candidates
        .map((candidate) => compressRoute(candidate))
        .filter((candidate) => orthogonalSegments(candidate).every((segment) => !segmentBlocked(segment.a, segment.b, obstacles)))
        .sort((a, b) => {
            const length = (route) => orthogonalSegments(route).reduce((sum, segment) => sum + Math.abs(segment.a.x - segment.b.x) + Math.abs(segment.a.y - segment.b.y), 0);
            return length(a) - length(b) || a.length - b.length || JSON.stringify(a).localeCompare(JSON.stringify(b));
        });
    return valid[0] || compressRoute([sourceLead, { x: targetLead.x, y: sourceLead.y }, targetLead]);
}

function preferredChannel(sourceLead, targetLead, sourceSide, targetSide, laneOffset, grid) {
    const horizontalPorts = ['left', 'right'].includes(sourceSide) && ['left', 'right'].includes(targetSide);
    const verticalPorts = ['top', 'bottom'].includes(sourceSide) && ['top', 'bottom'].includes(targetSide);
    const axis = horizontalPorts ? 'horizontal' : verticalPorts ? 'vertical'
        : Math.abs(sourceLead.x - targetLead.x) >= Math.abs(sourceLead.y - targetLead.y) ? 'horizontal' : 'vertical';
    const value = axis === 'horizontal'
        ? snap(((sourceLead.y + targetLead.y) / 2) + laneOffset, grid)
        : snap(((sourceLead.x + targetLead.x) / 2) + laneOffset, grid);
    return { axis, value };
}

export function routeOrthogonal(options = {}) {
    const grid = Math.max(2, finite(options.gridSize, DEFAULT_GRID));
    const clearance = Math.max(4, finite(options.clearance, DEFAULT_CLEARANCE));
    const channelSpacing = Math.max(6, finite(options.channelSpacing, DEFAULT_CHANNEL_SPACING));
    const bendPenalty = Math.max(0, finite(options.bendPenalty, DEFAULT_BEND_PENALTY));
    const crossingPenalty = Math.max(0, finite(options.crossingPenalty, DEFAULT_CROSSING_PENALTY));
    const source = { ...normalizePoint(options.source), side: String(options.source?.side || 'right'), id: String(options.source?.id || '') };
    const target = { ...normalizePoint(options.target), side: String(options.target?.side || 'left'), id: String(options.target?.id || '') };
    const network = normalizeNetwork(options.network);
    const leadDistance = Math.max(clearance + 8, finite(options.leadDistance, clearance + 16), SLD_ORTHOGONAL_ROUTING_CONTRACT.minimumLead);
    const sourceLead = leadPoint(source, source.side, leadDistance, grid);
    const targetLead = leadPoint(target, target.side, leadDistance, grid);

    const obstacles = [...(Array.isArray(options.obstacles) ? options.obstacles : []), ...(Array.isArray(options.reservedZones) ? options.reservedZones : [])]
        .map((rect, index) => normalizeRect(rect, `obstacle-${index}`))
        .filter((rect) => rect.id !== source.id && rect.id !== target.id)
        .map((rect) => expandRect(rect, clearance));
    const existingSegments = (Array.isArray(options.existingSegments) ? options.existingSegments : []).map((segment) => ({
        a: normalizePoint(segment.a),
        b: normalizePoint(segment.b),
        network: normalizeNetwork(segment.network),
        wireId: String(segment.wireId || ''),
    })).filter((segment) => (Math.abs(segment.a.x - segment.b.x) < EPSILON || Math.abs(segment.a.y - segment.b.y) < EPSILON));

    const laneOffset = routingLane(network) * channelSpacing;
    const preferred = preferredChannel(sourceLead, targetLead, source.side, target.side, laneOffset, grid);
    const xs = [sourceLead.x, targetLead.x, source.x, target.x, sourceLead.x + laneOffset, targetLead.x + laneOffset];
    const ys = [sourceLead.y, targetLead.y, source.y, target.y, sourceLead.y + laneOffset, targetLead.y + laneOffset];
    if (preferred.axis === 'horizontal') ys.push(preferred.value);
    else xs.push(preferred.value);
    obstacles.forEach((rect) => {
        xs.push(snap(rect.left - channelSpacing, grid), snap(rect.left, grid), snap(rect.right, grid), snap(rect.right + channelSpacing, grid));
        ys.push(snap(rect.top - channelSpacing, grid), snap(rect.top, grid), snap(rect.bottom, grid), snap(rect.bottom + channelSpacing, grid));
    });
    existingSegments.forEach((segment) => {
        xs.push(
            snap(segment.a.x, grid), snap(segment.b.x, grid),
            snap(segment.a.x - channelSpacing, grid), snap(segment.a.x + channelSpacing, grid),
            snap(segment.b.x - channelSpacing, grid), snap(segment.b.x + channelSpacing, grid),
        );
        ys.push(
            snap(segment.a.y, grid), snap(segment.b.y, grid),
            snap(segment.a.y - channelSpacing, grid), snap(segment.a.y + channelSpacing, grid),
            snap(segment.b.y - channelSpacing, grid), snap(segment.b.y + channelSpacing, grid),
        );
    });

    const xValues = reduceChannels(uniqueSorted(xs), [sourceLead.x, targetLead.x, preferred.axis === 'vertical' ? preferred.value : sourceLead.x], 76);
    const yValues = reduceChannels(uniqueSorted(ys), [sourceLead.y, targetLead.y, preferred.axis === 'horizontal' ? preferred.value : sourceLead.y], 76);
    const nodes = [];
    const indexByKey = new Map();
    yValues.forEach((y) => {
        xValues.forEach((x) => {
            const point = { x, y };
            if (obstacles.some((rect) => pointInsideRect(point, rect, false))) return;
            const index = nodes.length;
            nodes.push(point);
            indexByKey.set(`${x}|${y}`, index);
        });
    });
    const sourceIndex = indexByKey.get(`${sourceLead.x}|${sourceLead.y}`);
    const targetIndex = indexByKey.get(`${targetLead.x}|${targetLead.y}`);
    if (sourceIndex == null || targetIndex == null) {
        const fallback = fallbackRoute(sourceLead, targetLead, obstacles, grid);
        const full = compressRoute([source, ...fallback, target]);
        return {
            points: fallback,
            fullPoints: full,
            strategy: 'perimeter-fallback',
            version: SLD_SMART_ROUTER_VERSION,
            contract: SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
            preferred_axis: preferred.axis,
            preferred_channel: preferred.value,
            clearance,
            channel_spacing: channelSpacing,
            ...routeMetrics(full, obstacles, existingSegments, network, channelSpacing, source.side, target.side),
        };
    }

    const adjacency = Array.from({ length: nodes.length }, () => []);
    const connect = (aIndex, bIndex) => {
        if (aIndex == null || bIndex == null || aIndex === bIndex) return;
        const a = nodes[aIndex];
        const b = nodes[bIndex];
        if (segmentBlocked(a, b, obstacles)) return;
        adjacency[aIndex].push(bIndex);
        adjacency[bIndex].push(aIndex);
    };
    yValues.forEach((y) => {
        let previous = null;
        xValues.forEach((x) => {
            const index = indexByKey.get(`${x}|${y}`);
            if (index == null) return;
            if (previous != null) connect(previous, index);
            previous = index;
        });
    });
    xValues.forEach((x) => {
        let previous = null;
        yValues.forEach((y) => {
            const index = indexByKey.get(`${x}|${y}`);
            if (index == null) return;
            if (previous != null) connect(previous, index);
            previous = index;
        });
    });

    const open = [];
    const best = new Map();
    const parent = new Map();
    const pushState = (node, direction, g, f, previousKey = null) => {
        const key = `${node}|${direction}`;
        if (g >= (best.get(key) ?? Infinity) - EPSILON) return;
        best.set(key, g);
        if (previousKey) parent.set(key, previousKey);
        open.push({ node, direction, g, f, key });
    };
    const heuristic = (point) => Math.abs(point.x - targetLead.x) + Math.abs(point.y - targetLead.y);
    pushState(sourceIndex, 'none', 0, heuristic(sourceLead));
    let finalState = null;

    while (open.length > 0) {
        open.sort((a, b) => a.f - b.f || a.g - b.g || a.node - b.node || a.direction.localeCompare(b.direction));
        const current = open.shift();
        if (current.g > (best.get(current.key) ?? Infinity) + EPSILON) continue;
        if (current.node === targetIndex) { finalState = current; break; }
        const point = nodes[current.node];
        adjacency[current.node].forEach((nextNode) => {
            const next = nodes[nextNode];
            const direction = Math.abs(point.x - next.x) < EPSILON ? 'vertical' : 'horizontal';
            const length = Math.abs(point.x - next.x) + Math.abs(point.y - next.y);
            let extra = length;
            if (current.direction !== 'none' && current.direction !== direction) extra += bendPenalty;
            existingSegments.forEach((segment) => {
                if (perpendicularCrossing(point, next, segment.a, segment.b)) extra += crossingPenalty * crossingWeight(network, segment.network);
                const proximity = parallelProximity(point, next, segment.a, segment.b, channelSpacing);
                if (proximity > EPSILON) extra += proximity * proximityWeight(network, segment.network);
            });
            if (direction === preferred.axis) {
                const coordinate = direction === 'horizontal' ? point.y : point.x;
                extra += Math.abs(coordinate - preferred.value) * 0.032;
            } else {
                const coordinate = direction === 'horizontal' ? point.y : point.x;
                extra += Math.abs(coordinate - preferred.value) * 0.006;
            }
            const nextG = current.g + extra;
            pushState(nextNode, direction, nextG, nextG + heuristic(next), current.key);
        });
    }

    let interior;
    let strategy = 'orthogonal-channel-a-star';
    if (!finalState) {
        interior = fallbackRoute(sourceLead, targetLead, obstacles, grid);
        strategy = 'perimeter-fallback';
    } else {
        const reversed = [];
        let key = finalState.key;
        while (key) {
            const nodeIndex = Number(key.split('|')[0]);
            reversed.push(nodes[nodeIndex]);
            key = parent.get(key) || null;
        }
        interior = compressRoute(reversed.reverse());
    }
    const fullPoints = compressRoute([source, ...interior, target]);
    return {
        points: interior,
        fullPoints,
        strategy,
        version: SLD_SMART_ROUTER_VERSION,
        contract: SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
        preferred_axis: preferred.axis,
        preferred_channel: preferred.value,
        clearance,
        channel_spacing: channelSpacing,
        ...routeMetrics(fullPoints, obstacles, existingSegments, network, channelSpacing, source.side, target.side),
    };
}

export function routeOrthogonalBatch(requests = [], options = {}) {
    const fixedSegments = (Array.isArray(options.existingSegments) ? options.existingSegments : []).map((segment) => ({
        a: normalizePoint(segment.a),
        b: normalizePoint(segment.b),
        network: normalizeNetwork(segment.network),
        wireId: String(segment.wireId || ''),
    }));
    const ordered = [...(Array.isArray(requests) ? requests : [])].sort((a, b) => {
        const priority = routingNetworkPriority(a?.network) - routingNetworkPriority(b?.network);
        return priority || String(a?.id || '').localeCompare(String(b?.id || ''));
    });
    const routes = [];
    const routedSegments = [...fixedSegments];
    ordered.forEach((request) => {
        const result = routeOrthogonal({ ...request, existingSegments: routedSegments });
        const id = String(request?.id || `route-${routes.length + 1}`);
        routes.push({ id, network: normalizeNetwork(request?.network), ...result });
        routedSegments.push(...segmentsFromRoute(result.fullPoints, request?.network, id));
    });
    const summary = routes.reduce((aggregate, route) => {
        aggregate.wires += 1;
        aggregate.crossings += Number(route.crossings || 0);
        aggregate.clearance_conflicts += Number(route.clearance_conflicts || 0);
        aggregate.separation_conflicts += Number(route.separation_conflicts || 0);
        aggregate.fallbacks += route.strategy === 'perimeter-fallback' ? 1 : 0;
        aggregate.terminal_direction_violations += route.terminal_direction_ok === false ? 1 : 0;
        aggregate.orthogonal_wires += route.orthogonal === true ? 1 : 0;
        aggregate.length += Number(route.length || 0);
        return aggregate;
    }, { wires: 0, crossings: 0, clearance_conflicts: 0, separation_conflicts: 0, fallbacks: 0, terminal_direction_violations: 0, orthogonal_wires: 0, length: 0 });
    return {
        version: SLD_SMART_ROUTER_VERSION,
        contract: SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
        engine: SLD_ORTHOGONAL_ROUTING_CONTRACT.engine,
        routes,
        segments: routedSegments,
        summary,
    };
}

export function routeIsOrthogonal(points = []) {
    return orthogonalSegments(points).length === Math.max(0, points.length - 1);
}

export function routeIntersectsObstacles(points = [], obstacles = [], clearance = 0) {
    const expanded = obstacles.map((rect, index) => expandRect(normalizeRect(rect, `obstacle-${index}`), clearance));
    return orthogonalSegments(points).some((segment) => segmentBlocked(segment.a, segment.b, expanded));
}

export function routeCrossingCount(points = [], existingSegments = []) {
    const allowedJunctions = points.length > 0 ? [normalizePoint(points[0]), normalizePoint(points.at(-1))] : [];
    return orthogonalSegments(points).reduce((sum, segment) => sum + existingSegments.filter((existing) => perpendicularCrossing(segment.a, segment.b, existing.a, existing.b)
        && !sharedEndpointJunction(segment.a, segment.b, existing.a, existing.b, allowedJunctions)).length, 0);
}

export function segmentsFromRoute(points = [], network = 'unclassified', wireId = '') {
    return orthogonalSegments(points, network, wireId);
}
