export const SLD_VISUAL_REGRESSION_VERSION = '67M.4';

export const SLD_VISUAL_REGRESSION_CONTRACT = Object.freeze({
    name: 'deterministic-scene-fingerprint-v1',
    engine: 'canonical-scene-auditor',
    coordinateTolerance: 0.5,
    clippingTolerance: 0.5,
    routeTolerance: 0.01,
});

function finite(value, fallback = 0) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
}

function rounded(value, precision = 3) {
    const multiplier = 10 ** precision;
    return Math.round(finite(value, 0) * multiplier) / multiplier;
}

function stableHash(value) {
    let hash = 2166136261;
    const bytes = new TextEncoder().encode(String(value ?? ''));
    for (const byte of bytes) {
        hash ^= byte;
        hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0).toString(16).padStart(8, '0');
}

function normalizeRect(value = {}) {
    const x = finite(value.x ?? value.left, 0);
    const y = finite(value.y ?? value.top, 0);
    const w = Math.max(0, finite(value.w ?? value.width ?? ((value.right ?? x) - x), 0));
    const h = Math.max(0, finite(value.h ?? value.height ?? ((value.bottom ?? y) - y), 0));
    return {
        x: rounded(x),
        y: rounded(y),
        w: rounded(w),
        h: rounded(h),
        left: rounded(x),
        top: rounded(y),
        right: rounded(x + w),
        bottom: rounded(y + h),
    };
}

function normalizePoint(point = {}) {
    return { x: rounded(point.x), y: rounded(point.y) };
}

function pointsForRoute(route = {}) {
    const points = Array.isArray(route.points) ? route.points : (Array.isArray(route) ? route : []);
    return points.map(normalizePoint).filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y));
}

function rectanglesOverlap(a, b, tolerance = 0) {
    const aa = normalizeRect(a);
    const bb = normalizeRect(b);
    return aa.left < bb.right - tolerance
        && aa.right > bb.left + tolerance
        && aa.top < bb.bottom - tolerance
        && aa.bottom > bb.top + tolerance;
}

function rectOutsideCanvas(rect, canvas, tolerance = 0) {
    const r = normalizeRect(rect);
    const c = normalizeRect(canvas);
    return r.left < c.left - tolerance
        || r.top < c.top - tolerance
        || r.right > c.right + tolerance
        || r.bottom > c.bottom + tolerance;
}

function pointInRect(point, rect, tolerance = 0) {
    const r = normalizeRect(rect);
    return point.x >= r.left - tolerance && point.x <= r.right + tolerance
        && point.y >= r.top - tolerance && point.y <= r.bottom + tolerance;
}

function orientation(a, b, c) {
    const value = (b.y - a.y) * (c.x - b.x) - (b.x - a.x) * (c.y - b.y);
    if (Math.abs(value) < 0.00001) return 0;
    return value > 0 ? 1 : 2;
}

function onSegment(a, b, c) {
    return b.x <= Math.max(a.x, c.x) + 0.00001
        && b.x + 0.00001 >= Math.min(a.x, c.x)
        && b.y <= Math.max(a.y, c.y) + 0.00001
        && b.y + 0.00001 >= Math.min(a.y, c.y);
}

function segmentsIntersect(a, b, c, d) {
    const o1 = orientation(a, b, c);
    const o2 = orientation(a, b, d);
    const o3 = orientation(c, d, a);
    const o4 = orientation(c, d, b);
    if (o1 !== o2 && o3 !== o4) return true;
    if (o1 === 0 && onSegment(a, c, b)) return true;
    if (o2 === 0 && onSegment(a, d, b)) return true;
    if (o3 === 0 && onSegment(c, a, d)) return true;
    if (o4 === 0 && onSegment(c, b, d)) return true;
    return false;
}

function segmentIntersectsRect(a, b, rect, tolerance = 0) {
    const r = normalizeRect(rect);
    if (pointInRect(a, r, tolerance) || pointInRect(b, r, tolerance)) return true;
    const corners = [
        { x: r.left, y: r.top },
        { x: r.right, y: r.top },
        { x: r.right, y: r.bottom },
        { x: r.left, y: r.bottom },
    ];
    return corners.some((corner, index) => segmentsIntersect(a, b, corner, corners[(index + 1) % corners.length]));
}

function routeIsOrthogonal(points, tolerance = SLD_VISUAL_REGRESSION_CONTRACT.routeTolerance) {
    return points.length < 2 || points.slice(0, -1).every((point, index) => {
        const next = points[index + 1];
        return Math.abs(point.x - next.x) <= tolerance || Math.abs(point.y - next.y) <= tolerance;
    });
}

function canonicalPositionEntries(positions = {}) {
    return Object.entries(positions || {})
        .filter(([id, position]) => String(id) !== '' && position && typeof position === 'object')
        .map(([id, position]) => {
            const rect = normalizeRect(position);
            return {
                id: String(id),
                x: rect.x,
                y: rect.y,
                w: rect.w,
                h: rect.h,
                rotation: ((Math.round(finite(position.rotation, 0) / 90) * 90) % 360 + 360) % 360,
                flip_h: position.flip_h === true,
                flip_v: position.flip_v === true,
                visible: position.visible !== false,
            };
        })
        .sort((a, b) => a.id.localeCompare(b.id));
}

function canonicalRouteEntries(routes = {}) {
    return Object.entries(routes || {})
        .filter(([id, points]) => String(id) !== '' && Array.isArray(points))
        .map(([id, points]) => ({ id: String(id), points: points.map(normalizePoint) }))
        .sort((a, b) => a.id.localeCompare(b.id));
}

function canonicalLabelEntries(labelPlacement = {}) {
    const positions = labelPlacement?.positions || labelPlacement || {};
    return Object.entries(positions)
        .filter(([id, placement]) => String(id) !== '' && placement && typeof placement === 'object')
        .map(([id, placement]) => {
            const rect = normalizeRect(placement);
            return {
                id: String(id),
                x: rect.x,
                y: rect.y,
                w: rect.w,
                h: rect.h,
                side: ['top', 'right', 'bottom', 'left'].includes(placement.side) ? placement.side : 'bottom',
                leader: placement.leader === true,
                signature: String(placement.signature || ''),
            };
        })
        .sort((a, b) => a.id.localeCompare(b.id));
}

export function createVisualSnapshot({ positions = {}, routes = {}, labelPlacement = {}, canvas = {} } = {}) {
    const canonical = {
        canvas: normalizeRect(canvas),
        equipment: canonicalPositionEntries(positions),
        routes: canonicalRouteEntries(routes),
        labels: canonicalLabelEntries(labelPlacement),
    };
    const payload = JSON.stringify(canonical);
    return {
        version: SLD_VISUAL_REGRESSION_VERSION,
        contract: SLD_VISUAL_REGRESSION_CONTRACT.name,
        engine: SLD_VISUAL_REGRESSION_CONTRACT.engine,
        fingerprint: `vr-${stableHash(payload)}`,
        canonical,
    };
}

export function auditVisualScene({ equipment = [], routes = [], labels = [], canvas = {} } = {}, options = {}) {
    const clippingTolerance = finite(options.clippingTolerance, SLD_VISUAL_REGRESSION_CONTRACT.clippingTolerance);
    const routeTolerance = finite(options.routeTolerance, SLD_VISUAL_REGRESSION_CONTRACT.routeTolerance);
    const normalizedEquipment = (Array.isArray(equipment) ? equipment : [])
        .map((item) => ({ id: String(item.id || ''), ...normalizeRect(item) }))
        .filter((item) => item.id !== '')
        .sort((a, b) => a.id.localeCompare(b.id));
    const normalizedLabels = (Array.isArray(labels) ? labels : [])
        .map((item) => ({ id: String(item.id || ''), ...normalizeRect(item), signature: String(item.signature || '') }))
        .filter((item) => item.id !== '')
        .sort((a, b) => a.id.localeCompare(b.id));
    const normalizedRoutes = (Array.isArray(routes) ? routes : [])
        .map((route) => ({ id: String(route.id || ''), points: pointsForRoute(route), signature: String(route.signature || '') }))
        .filter((route) => route.id !== '')
        .sort((a, b) => a.id.localeCompare(b.id));
    const normalizedCanvas = normalizeRect(canvas);
    const issues = [];

    for (let index = 0; index < normalizedEquipment.length; index += 1) {
        const item = normalizedEquipment[index];
        if (rectOutsideCanvas(item, normalizedCanvas, clippingTolerance)) {
            issues.push({ code: 'V401', severity: 'error', category: 'clipping', objects: [item.id] });
        }
        for (let otherIndex = index + 1; otherIndex < normalizedEquipment.length; otherIndex += 1) {
            const other = normalizedEquipment[otherIndex];
            if (rectanglesOverlap(item, other, clippingTolerance)) {
                issues.push({ code: 'V402', severity: 'error', category: 'equipment_overlap', objects: [item.id, other.id] });
            }
        }
    }

    for (let index = 0; index < normalizedLabels.length; index += 1) {
        const label = normalizedLabels[index];
        if (rectOutsideCanvas(label, normalizedCanvas, clippingTolerance)) {
            issues.push({ code: 'V403', severity: 'error', category: 'label_clipping', objects: [label.id] });
        }
        normalizedEquipment.forEach((item) => {
            if (rectanglesOverlap(label, item, clippingTolerance)) {
                issues.push({ code: 'V404', severity: 'error', category: 'label_equipment_overlap', objects: [label.id, item.id] });
            }
        });
        for (let otherIndex = index + 1; otherIndex < normalizedLabels.length; otherIndex += 1) {
            const other = normalizedLabels[otherIndex];
            if (rectanglesOverlap(label, other, clippingTolerance)) {
                issues.push({ code: 'V405', severity: 'error', category: 'label_overlap', objects: [label.id, other.id] });
            }
        }
    }

    normalizedRoutes.forEach((route) => {
        if (!routeIsOrthogonal(route.points, routeTolerance)) {
            issues.push({ code: 'V406', severity: 'error', category: 'non_orthogonal_route', objects: [route.id] });
        }
        if (route.points.some((point) => !pointInRect(point, normalizedCanvas, clippingTolerance))) {
            issues.push({ code: 'V407', severity: 'error', category: 'route_clipping', objects: [route.id] });
        }
        route.points.slice(0, -1).forEach((point, pointIndex) => {
            const next = route.points[pointIndex + 1];
            normalizedLabels.forEach((label) => {
                if (segmentIntersectsRect(point, next, label, clippingTolerance)) {
                    issues.push({ code: 'V408', severity: 'warning', category: 'wire_label_intersection', objects: [route.id, label.id] });
                }
            });
        });
    });

    const uniqueIssues = [...new Map(issues.map((issue) => [`${issue.code}|${issue.objects.join('|')}`, issue])).values()];
    const errors = uniqueIssues.filter((issue) => issue.severity === 'error').length;
    const warnings = uniqueIssues.filter((issue) => issue.severity === 'warning').length;
    const byCategory = uniqueIssues.reduce((summary, issue) => {
        summary[issue.category] = (summary[issue.category] || 0) + 1;
        return summary;
    }, {});
    const canonical = {
        canvas: normalizedCanvas,
        equipment: normalizedEquipment.map(({ id, x, y, w, h }) => ({ id, x, y, w, h })),
        routes: normalizedRoutes.map(({ id, points, signature }) => ({ id, points, signature })),
        labels: normalizedLabels.map(({ id, x, y, w, h, signature }) => ({ id, x, y, w, h, signature })),
    };
    const fingerprint = `vr-${stableHash(JSON.stringify(canonical))}`;

    return {
        version: SLD_VISUAL_REGRESSION_VERSION,
        contract: SLD_VISUAL_REGRESSION_CONTRACT.name,
        engine: SLD_VISUAL_REGRESSION_CONTRACT.engine,
        fingerprint,
        status: errors > 0 ? 'error' : (warnings > 0 ? 'warning' : 'pass'),
        summary: {
            equipment: normalizedEquipment.length,
            routes: normalizedRoutes.length,
            labels: normalizedLabels.length,
            issues: uniqueIssues.length,
            errors,
            warnings,
            clipping: Number(byCategory.clipping || 0) + Number(byCategory.label_clipping || 0) + Number(byCategory.route_clipping || 0),
            overlaps: Number(byCategory.equipment_overlap || 0) + Number(byCategory.label_equipment_overlap || 0) + Number(byCategory.label_overlap || 0),
            non_orthogonal_routes: Number(byCategory.non_orthogonal_route || 0),
            wire_label_intersections: Number(byCategory.wire_label_intersection || 0),
        },
        issues: uniqueIssues,
        canonical,
    };
}

export function compareVisualSnapshots(baseline = {}, current = {}, tolerance = SLD_VISUAL_REGRESSION_CONTRACT.coordinateTolerance) {
    const before = baseline.canonical || baseline;
    const after = current.canonical || current;
    const changes = [];
    const changedNumbers = (left, right, dimensions) => dimensions.some(
        (dimension) => Math.abs(finite(left?.[dimension], 0) - finite(right?.[dimension], 0)) > tolerance,
    );
    const changedValues = (left, right, properties) => properties.some(
        (property) => left?.[property] !== right?.[property],
    );
    const routePointsChanged = (left, right) => {
        const a = Array.isArray(left?.points) ? left.points : [];
        const b = Array.isArray(right?.points) ? right.points : [];
        return a.length !== b.length || a.some((point, index) => (
            Math.abs(finite(point?.x, 0) - finite(b[index]?.x, 0)) > tolerance
            || Math.abs(finite(point?.y, 0) - finite(b[index]?.y, 0)) > tolerance
        ));
    };
    const compareCollection = (key, isChanged) => {
        const a = new Map((before[key] || []).map((item) => [String(item.id), item]));
        const b = new Map((after[key] || []).map((item) => [String(item.id), item]));
        const ids = [...new Set([...a.keys(), ...b.keys()])].sort();
        ids.forEach((id) => {
            if (!a.has(id) || !b.has(id)) {
                changes.push({ category: key, id, kind: a.has(id) ? 'removed' : 'added' });
                return;
            }
            if (isChanged(a.get(id), b.get(id))) {
                changes.push({ category: key, id, kind: 'changed' });
            }
        });
    };
    compareCollection('equipment', (left, right) => (
        changedNumbers(left, right, ['x', 'y', 'w', 'h'])
        || changedValues(left, right, ['rotation', 'flip_h', 'flip_v', 'visible'])
    ));
    compareCollection('routes', routePointsChanged);
    compareCollection('labels', (left, right) => (
        changedNumbers(left, right, ['x', 'y', 'w', 'h'])
        || changedValues(left, right, ['side', 'leader', 'signature'])
    ));
    return {
        match: changes.length === 0,
        tolerance,
        changes,
        changed_count: changes.length,
    };
}
