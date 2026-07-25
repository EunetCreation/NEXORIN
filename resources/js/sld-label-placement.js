import { transformedGeometrySide } from './sld-geometry-foundation.js';

export const SLD_LABEL_PLACEMENT_VERSION = '67M.3';

export const SLD_LABEL_PLACEMENT_CONTRACT = Object.freeze({
    name: 'deterministic-clearance-label-v1',
    engine: 'candidate-score-label-placer',
    clearance: 12,
    equipmentGap: 14,
    wireClearance: 8,
    leaderThreshold: 28,
    candidates: Object.freeze(['bottom', 'top', 'right', 'left']),
});

function finite(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function clamp(value, minimum, maximum) {
    return Math.max(minimum, Math.min(maximum, value));
}

function normalizeRect(rect = {}) {
    const x = finite(rect.x ?? rect.left, 0);
    const y = finite(rect.y ?? rect.top, 0);
    const w = Math.max(1, finite(rect.w ?? rect.width ?? ((rect.right ?? x) - x), 1));
    const h = Math.max(1, finite(rect.h ?? rect.height ?? ((rect.bottom ?? y) - y), 1));
    return { x, y, w, h, left: x, top: y, right: x + w, bottom: y + h };
}

function expandRect(rect, gap) {
    const normalized = normalizeRect(rect);
    return {
        ...normalized,
        x: normalized.x - gap,
        y: normalized.y - gap,
        w: normalized.w + gap * 2,
        h: normalized.h + gap * 2,
        left: normalized.left - gap,
        top: normalized.top - gap,
        right: normalized.right + gap,
        bottom: normalized.bottom + gap,
    };
}

export function labelRectanglesOverlap(a, b, gap = 0) {
    const aa = expandRect(a, gap);
    const bb = normalizeRect(b);
    return aa.left < bb.right && aa.right > bb.left && aa.top < bb.bottom && aa.bottom > bb.top;
}

function pointInRect(point, rect) {
    const r = normalizeRect(rect);
    return point.x >= r.left && point.x <= r.right && point.y >= r.top && point.y <= r.bottom;
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

export function labelSegmentIntersectsRect(segment = {}, rect = {}, clearance = 0) {
    const r = expandRect(rect, clearance);
    const a = {
        x: finite(segment.x1 ?? segment.start?.x ?? segment.a?.x, 0),
        y: finite(segment.y1 ?? segment.start?.y ?? segment.a?.y, 0),
    };
    const b = {
        x: finite(segment.x2 ?? segment.end?.x ?? segment.b?.x, 0),
        y: finite(segment.y2 ?? segment.end?.y ?? segment.b?.y, 0),
    };
    if (pointInRect(a, r) || pointInRect(b, r)) return true;
    const corners = [
        { x: r.left, y: r.top },
        { x: r.right, y: r.top },
        { x: r.right, y: r.bottom },
        { x: r.left, y: r.bottom },
    ];
    return corners.some((corner, index) => segmentsIntersect(a, b, corner, corners[(index + 1) % corners.length]));
}

function stableHash(value) {
    let hash = 2166136261;
    const text = String(value ?? '');
    for (let index = 0; index < text.length; index += 1) {
        hash ^= text.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0).toString(16).padStart(8, '0');
}

export function estimateEngineeringLabel(item = {}, options = {}) {
    const title = String(item.title || item.label || item.id || 'Equipment').trim();
    const tag = item.show_tag === false ? '' : String(item.tag || '').trim();
    const rating = item.show_rating === false ? '' : String(item.rating || '').trim();
    const subtitle = item.show_subtitle === false ? '' : String(item.subtitle || '').trim();
    const lines = [tag, title, rating, subtitle].filter(Boolean);
    const longest = lines.reduce((maximum, line) => Math.max(maximum, line.length), 0);
    const width = clamp(finite(options.width, 0) || (longest * 6.2 + 24), 96, 220);
    const height = clamp(finite(options.height, 0) || (lines.length * 13 + 14), 34, 82);
    return { width: Math.round(width), height: Math.round(height), lines: lines.length };
}

function portDensity(item = {}) {
    const density = { top: 0, right: 0, bottom: 0, left: 0 };
    (Array.isArray(item.ports) ? item.ports : []).forEach((port) => {
        const rawSide = ['top', 'right', 'bottom', 'left'].includes(port?.side) ? port.side : 'right';
        const side = transformedGeometrySide(rawSide, item);
        density[side] += 1;
    });
    return density;
}

function candidateOrder(item = {}) {
    const rotation = ((Math.round(finite(item.rotation, 0) / 90) * 90) % 360 + 360) % 360;
    const base = rotation === 90 || rotation === 270
        ? ['right', 'left', 'bottom', 'top']
        : ['bottom', 'top', 'right', 'left'];
    const densities = portDensity(item);
    return [...base].sort((a, b) => densities[a] - densities[b] || base.indexOf(a) - base.indexOf(b));
}

function labelCandidate(item, side, size, gap) {
    const equipment = normalizeRect(item);
    const centerX = equipment.left + equipment.w / 2;
    const centerY = equipment.top + equipment.h / 2;
    let x = centerX - size.width / 2;
    let y = centerY - size.height / 2;
    if (side === 'top') y = equipment.top - gap - size.height;
    if (side === 'bottom') y = equipment.bottom + gap;
    if (side === 'left') x = equipment.left - gap - size.width;
    if (side === 'right') x = equipment.right + gap;
    if (side === 'top' || side === 'bottom') x = centerX - size.width / 2;
    if (side === 'left' || side === 'right') y = centerY - size.height / 2;
    const rect = normalizeRect({ x, y, w: size.width, h: size.height });
    const anchor = {
        x: side === 'left' ? equipment.left : (side === 'right' ? equipment.right : centerX),
        y: side === 'top' ? equipment.top : (side === 'bottom' ? equipment.bottom : centerY),
    };
    const labelAnchor = {
        x: side === 'left' ? rect.right : (side === 'right' ? rect.left : rect.left + rect.w / 2),
        y: side === 'top' ? rect.bottom : (side === 'bottom' ? rect.top : rect.top + rect.h / 2),
    };
    return { side, rect, anchor, labelAnchor };
}

function outsidePenalty(rect, canvas) {
    if (!canvas) return 0;
    const r = normalizeRect(rect);
    const c = normalizeRect(canvas);
    const overflow = Math.max(0, c.left - r.left)
        + Math.max(0, r.right - c.right)
        + Math.max(0, c.top - r.top)
        + Math.max(0, r.bottom - c.bottom);
    return overflow * 5000;
}

function scoreCandidate(candidate, item, equipment, labels, wireSegments, canvas, rank, options) {
    const clearance = finite(options.clearance, SLD_LABEL_PLACEMENT_CONTRACT.clearance);
    const wireClearance = finite(options.wireClearance, SLD_LABEL_PLACEMENT_CONTRACT.wireClearance);
    let equipmentConflicts = 0;
    let labelConflicts = 0;
    let wireConflicts = 0;
    equipment.forEach((rect) => {
        if (String(rect.id || '') === String(item.id || '')) return;
        if (labelRectanglesOverlap(candidate.rect, rect, clearance)) equipmentConflicts += 1;
    });
    labels.forEach((placement) => {
        if (labelRectanglesOverlap(candidate.rect, placement.rect, clearance)) labelConflicts += 1;
    });
    wireSegments.forEach((segment) => {
        if (labelSegmentIntersectsRect(segment, candidate.rect, wireClearance)) wireConflicts += 1;
    });
    const score = rank * 100
        + equipmentConflicts * 100000
        + labelConflicts * 90000
        + wireConflicts * 50000
        + outsidePenalty(candidate.rect, canvas);
    return { score, equipmentConflicts, labelConflicts, wireConflicts };
}

export function placeEngineeringLabels(items = [], wireSegments = [], options = {}) {
    const equipment = (Array.isArray(items) ? items : [])
        .map((item) => ({ ...item, ...normalizeRect(item), id: String(item.id || '') }))
        .filter((item) => item.id !== '')
        .sort((a, b) => a.id.localeCompare(b.id));
    const canvas = options.canvas ? normalizeRect(options.canvas) : null;
    const gap = finite(options.equipmentGap, SLD_LABEL_PLACEMENT_CONTRACT.equipmentGap);
    const placements = [];

    equipment.forEach((item) => {
        const size = estimateEngineeringLabel(item, options);
        const candidates = candidateOrder(item).map((side, rank) => {
            const candidate = labelCandidate(item, side, size, gap);
            return { ...candidate, rank, ...scoreCandidate(candidate, item, equipment, placements, wireSegments, canvas, rank, options) };
        }).sort((a, b) => a.score - b.score || a.rank - b.rank || a.side.localeCompare(b.side));
        const best = candidates[0];
        const conflicts = best.equipmentConflicts + best.labelConflicts + best.wireConflicts;
        const leaderLength = Math.abs(best.anchor.x - best.labelAnchor.x) + Math.abs(best.anchor.y - best.labelAnchor.y);
        const leader = conflicts > 0 || leaderLength > finite(options.leaderThreshold, SLD_LABEL_PLACEMENT_CONTRACT.leaderThreshold);
        const signaturePayload = [
            item.id,
            best.side,
            best.rect.x.toFixed(3),
            best.rect.y.toFixed(3),
            best.rect.w.toFixed(3),
            best.rect.h.toFixed(3),
            leader ? '1' : '0',
        ].join('|');
        const signature = `lp-${stableHash(signaturePayload)}`;
        placements.push({
            id: item.id,
            side: best.side,
            rect: best.rect,
            x: best.rect.x,
            y: best.rect.y,
            w: best.rect.w,
            h: best.rect.h,
            anchor: best.anchor,
            label_anchor: best.labelAnchor,
            leader,
            score: best.score,
            equipment_conflicts: best.equipmentConflicts,
            label_conflicts: best.labelConflicts,
            wire_conflicts: best.wireConflicts,
            fallback: conflicts > 0,
            signature,
        });
    });

    const summary = placements.reduce((result, placement) => {
        result.labels += 1;
        result.leaders += placement.leader ? 1 : 0;
        result.fallbacks += placement.fallback ? 1 : 0;
        result.equipment_conflicts += placement.equipment_conflicts;
        result.label_conflicts += placement.label_conflicts;
        result.wire_conflicts += placement.wire_conflicts;
        return result;
    }, { labels: 0, leaders: 0, fallbacks: 0, equipment_conflicts: 0, label_conflicts: 0, wire_conflicts: 0 });

    return {
        version: SLD_LABEL_PLACEMENT_VERSION,
        contract: SLD_LABEL_PLACEMENT_CONTRACT.name,
        engine: SLD_LABEL_PLACEMENT_CONTRACT.engine,
        placements,
        summary,
    };
}
