export const SLD_GEOMETRY_FOUNDATION_VERSION = '67M.1';

export const SLD_GEOMETRY_CONTRACT = Object.freeze({
    name: 'canonical-model-space-v1',
    units: 'drawing_unit',
    coordinatePrecision: 1,
    gridSize: 20,
    canvas: Object.freeze({ width: 1640, height: 940 }),
    equipment: Object.freeze({
        minWidth: 80,
        minHeight: 50,
        maxWidth: 800,
        maxHeight: 600,
        nominalWidth: 144,
        nominalHeight: 120,
        minimumGap: 20,
    }),
});

function finite(value, fallback = 0) {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : fallback;
}

function clamp(value, minimum, maximum) {
    return Math.max(minimum, Math.min(maximum, value));
}

function precisionFactor(precision = SLD_GEOMETRY_CONTRACT.coordinatePrecision) {
    const normalized = clamp(Math.floor(finite(precision, 1)), 0, 6);
    return 10 ** normalized;
}

export function roundCoordinate(value, precision = SLD_GEOMETRY_CONTRACT.coordinatePrecision) {
    const factor = precisionFactor(precision);
    return Math.round(finite(value, 0) * factor) / factor;
}

export function snapCoordinate(value, gridSize = SLD_GEOMETRY_CONTRACT.gridSize) {
    const grid = Math.max(1, finite(gridSize, SLD_GEOMETRY_CONTRACT.gridSize));
    return roundCoordinate(Math.round(finite(value, 0) / grid) * grid);
}

export function isGridAligned(value, gridSize = SLD_GEOMETRY_CONTRACT.gridSize, tolerance = 0.01) {
    const grid = Math.max(1, finite(gridSize, SLD_GEOMETRY_CONTRACT.gridSize));
    const numeric = finite(value, Number.NaN);
    if (!Number.isFinite(numeric)) return false;
    return Math.abs(numeric - Math.round(numeric / grid) * grid) <= Math.max(0, finite(tolerance, 0.01));
}

export function normalizeQuarterTurn(value = 0) {
    const numeric = finite(value, 0);
    return ((Math.round(numeric / 90) * 90) % 360 + 360) % 360;
}

export function normalizeGeometryRect(rect = {}, options = {}) {
    const equipment = SLD_GEOMETRY_CONTRACT.equipment;
    const minimumWidth = Math.max(1, finite(options.minWidth, equipment.minWidth));
    const minimumHeight = Math.max(1, finite(options.minHeight, equipment.minHeight));
    const maximumWidth = Math.max(minimumWidth, finite(options.maxWidth, equipment.maxWidth));
    const maximumHeight = Math.max(minimumHeight, finite(options.maxHeight, equipment.maxHeight));
    const fallbackWidth = clamp(finite(options.defaultWidth, equipment.nominalWidth), minimumWidth, maximumWidth);
    const fallbackHeight = clamp(finite(options.defaultHeight, equipment.nominalHeight), minimumHeight, maximumHeight);
    const width = clamp(finite(rect.w ?? rect.width, fallbackWidth), minimumWidth, maximumWidth);
    const height = clamp(finite(rect.h ?? rect.height, fallbackHeight), minimumHeight, maximumHeight);
    const shouldSnap = options.snap === true;
    const gridSize = finite(options.gridSize, SLD_GEOMETRY_CONTRACT.gridSize);
    const normalize = shouldSnap ? (value) => snapCoordinate(value, gridSize) : roundCoordinate;

    return {
        id: rect.id == null ? null : String(rect.id),
        x: normalize(finite(rect.x, 0)),
        y: normalize(finite(rect.y, 0)),
        w: roundCoordinate(width),
        h: roundCoordinate(height),
    };
}

export function geometryBounds(rectangles = []) {
    const items = rectangles
        .map((rect) => normalizeGeometryRect(rect, {
            minWidth: 0.001,
            minHeight: 0.001,
            maxWidth: 100000,
            maxHeight: 100000,
            defaultWidth: 0.001,
            defaultHeight: 0.001,
        }))
        .filter((rect) => Number.isFinite(rect.x) && Number.isFinite(rect.y) && rect.w > 0 && rect.h > 0);

    if (items.length === 0) return null;

    const left = Math.min(...items.map((item) => item.x));
    const right = Math.max(...items.map((item) => item.x + item.w));
    const top = Math.min(...items.map((item) => item.y));
    const bottom = Math.max(...items.map((item) => item.y + item.h));

    return {
        items,
        left,
        right,
        top,
        bottom,
        width: roundCoordinate(right - left),
        height: roundCoordinate(bottom - top),
        centerX: roundCoordinate((left + right) / 2),
        centerY: roundCoordinate((top + bottom) / 2),
    };
}

function quantize(value, gridSize, snap) {
    return snap ? snapCoordinate(value, gridSize) : roundCoordinate(value);
}

export function alignGeometryRectangles(rectangles = [], mode = 'left', options = {}) {
    const accepted = new Set(['left', 'center', 'right', 'top', 'middle', 'bottom']);
    if (!accepted.has(mode)) return rectangles.map((rect) => ({ ...rect }));
    const bounds = geometryBounds(rectangles);
    if (!bounds) return [];
    const snap = options.snap !== false;
    const gridSize = finite(options.gridSize, SLD_GEOMETRY_CONTRACT.gridSize);

    return bounds.items.map((item) => {
        let x = item.x;
        let y = item.y;
        if (mode === 'left') x = bounds.left;
        if (mode === 'center') x = bounds.centerX - item.w / 2;
        if (mode === 'right') x = bounds.right - item.w;
        if (mode === 'top') y = bounds.top;
        if (mode === 'middle') y = bounds.centerY - item.h / 2;
        if (mode === 'bottom') y = bounds.bottom - item.h;
        return {
            ...item,
            x: quantize(x, gridSize, snap),
            y: quantize(y, gridSize, snap),
        };
    });
}

export function distributeGeometryRectangles(rectangles = [], axis = 'horizontal', options = {}) {
    if (!['horizontal', 'vertical'].includes(axis)) return { items: rectangles.map((rect) => ({ ...rect })), gap: 0 };
    const bounds = geometryBounds(rectangles);
    if (!bounds || bounds.items.length < 3) return { items: bounds?.items || [], gap: 0 };
    const snap = options.snap !== false;
    const gridSize = finite(options.gridSize, SLD_GEOMETRY_CONTRACT.gridSize);
    const ordered = [...bounds.items].sort((a, b) => {
        const delta = axis === 'horizontal' ? a.x - b.x : a.y - b.y;
        return delta || String(a.id || '').localeCompare(String(b.id || ''));
    });
    const start = axis === 'horizontal' ? ordered[0].x : ordered[0].y;
    const end = axis === 'horizontal'
        ? ordered.at(-1).x + ordered.at(-1).w
        : ordered.at(-1).y + ordered.at(-1).h;
    const totalSize = ordered.reduce((sum, item) => sum + (axis === 'horizontal' ? item.w : item.h), 0);
    const gap = (end - start - totalSize) / (ordered.length - 1);
    let cursor = start;

    const items = ordered.map((item, index) => {
        const next = { ...item };
        if (index > 0 && index < ordered.length - 1) {
            if (axis === 'horizontal') next.x = quantize(cursor, gridSize, snap);
            else next.y = quantize(cursor, gridSize, snap);
        }
        cursor += (axis === 'horizontal' ? item.w : item.h) + gap;
        return next;
    });

    return { items, gap: roundCoordinate(gap) };
}

export function transformGeometryPoint(point, center, transform = {}) {
    let dx = finite(point?.x, 0) - finite(center?.x, 0);
    let dy = finite(point?.y, 0) - finite(center?.y, 0);
    if (transform.flip_h === true) dx *= -1;
    if (transform.flip_v === true) dy *= -1;
    const radians = normalizeQuarterTurn(transform.rotation) * Math.PI / 180;
    return {
        x: roundCoordinate(finite(center?.x, 0) + dx * Math.cos(radians) - dy * Math.sin(radians), 5),
        y: roundCoordinate(finite(center?.y, 0) + dx * Math.sin(radians) + dy * Math.cos(radians), 5),
    };
}

export function transformedGeometrySide(side = 'right', transform = {}) {
    const vectors = { left: [-1, 0], right: [1, 0], top: [0, -1], bottom: [0, 1] };
    let [x, y] = vectors[side] || vectors.right;
    if (transform.flip_h === true) x *= -1;
    if (transform.flip_v === true) y *= -1;
    const radians = normalizeQuarterTurn(transform.rotation) * Math.PI / 180;
    const rx = Math.round(x * Math.cos(radians) - y * Math.sin(radians));
    const ry = Math.round(x * Math.sin(radians) + y * Math.cos(radians));
    if (Math.abs(rx) >= Math.abs(ry)) return rx < 0 ? 'left' : 'right';
    return ry < 0 ? 'top' : 'bottom';
}

export function canonicalPortPoint(rect = {}, port = {}, transform = {}) {
    const normalized = normalizeGeometryRect(rect, {
        minWidth: 0.001,
        minHeight: 0.001,
        maxWidth: 100000,
        maxHeight: 100000,
        defaultWidth: SLD_GEOMETRY_CONTRACT.equipment.nominalWidth,
        defaultHeight: SLD_GEOMETRY_CONTRACT.equipment.nominalHeight,
    });
    const anchor = port.anchor || {};
    const raw = {
        x: normalized.x + normalized.w * clamp(finite(anchor.x, 0.5), 0, 1),
        y: normalized.y + normalized.h * clamp(finite(anchor.y, 0.5), 0, 1),
    };
    const center = {
        x: normalized.x + normalized.w / 2,
        y: normalized.y + normalized.h / 2,
    };
    const transformed = transformGeometryPoint(raw, center, transform);
    return {
        x: transformed.x,
        y: transformed.y,
        side: transformedGeometrySide(port.side || 'right', transform),
        relativeX: roundCoordinate((transformed.x - normalized.x) / Math.max(normalized.w, 0.001), 5),
        relativeY: roundCoordinate((transformed.y - normalized.y) / Math.max(normalized.h, 0.001), 5),
    };
}

function overlapMetrics(a, b) {
    const overlapX = Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x);
    const overlapY = Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y);
    return {
        overlapX,
        overlapY,
        area: overlapX > 0 && overlapY > 0 ? overlapX * overlapY : 0,
    };
}

function edgeGap(a, b) {
    const horizontal = Math.max(0, Math.max(a.x, b.x) - Math.min(a.x + a.w, b.x + b.w));
    const vertical = Math.max(0, Math.max(a.y, b.y) - Math.min(a.y + a.h, b.y + b.h));
    if (horizontal > 0 && vertical > 0) return Math.hypot(horizontal, vertical);
    return Math.max(horizontal, vertical);
}

export function diagnoseGeometry(rectangles = [], options = {}) {
    const canvas = {
        width: Math.max(1, finite(options.canvas?.width, SLD_GEOMETRY_CONTRACT.canvas.width)),
        height: Math.max(1, finite(options.canvas?.height, SLD_GEOMETRY_CONTRACT.canvas.height)),
    };
    const gridSize = Math.max(1, finite(options.gridSize, SLD_GEOMETRY_CONTRACT.gridSize));
    const minimumGap = Math.max(0, finite(options.minimumGap, SLD_GEOMETRY_CONTRACT.equipment.minimumGap));
    const items = rectangles.map((rect, index) => {
        const normalized = normalizeGeometryRect(rect, {
            minWidth: 0.001,
            minHeight: 0.001,
            maxWidth: 100000,
            maxHeight: 100000,
            defaultWidth: 0.001,
            defaultHeight: 0.001,
        });
        return { ...normalized, id: normalized.id || String(index), entity: rect.entity || 'object' };
    });
    const issues = [];
    const advisories = [];

    items.forEach((item) => {
        if (![item.x, item.y, item.w, item.h].every(Number.isFinite) || item.w <= 0 || item.h <= 0) {
            issues.push({ code: 'GEOMETRY_INVALID_RECT', severity: 'error', objects: [item.id] });
            return;
        }
        if (item.x < 0 || item.y < 0 || item.x + item.w > canvas.width || item.y + item.h > canvas.height) {
            issues.push({ code: 'GEOMETRY_OUTSIDE_CANVAS', severity: 'warning', objects: [item.id] });
        }
        if (!isGridAligned(item.x, gridSize) || !isGridAligned(item.y, gridSize)) {
            advisories.push({ code: 'GEOMETRY_OFF_GRID', severity: 'notice', objects: [item.id] });
        }
    });

    for (let first = 0; first < items.length; first += 1) {
        for (let second = first + 1; second < items.length; second += 1) {
            const a = items[first];
            const b = items[second];
            const overlap = overlapMetrics(a, b);
            if (overlap.area > 0.01) {
                issues.push({
                    code: 'GEOMETRY_OVERLAP',
                    severity: 'error',
                    objects: [a.id, b.id],
                    overlap_area: roundCoordinate(overlap.area),
                });
                continue;
            }
            const gap = edgeGap(a, b);
            if (gap < minimumGap) {
                advisories.push({
                    code: 'GEOMETRY_CLEARANCE',
                    severity: 'notice',
                    objects: [a.id, b.id],
                    gap: roundCoordinate(gap),
                    required_gap: minimumGap,
                });
            }
        }
    }

    const bounds = geometryBounds(items);
    return {
        version: SLD_GEOMETRY_FOUNDATION_VERSION,
        contract: SLD_GEOMETRY_CONTRACT.name,
        units: SLD_GEOMETRY_CONTRACT.units,
        objects: items.length,
        issues,
        advisories,
        summary: {
            issues: issues.length,
            errors: issues.filter((issue) => issue.severity === 'error').length,
            warnings: issues.filter((issue) => issue.severity === 'warning').length,
            advisories: advisories.length,
            overlaps: issues.filter((issue) => issue.code === 'GEOMETRY_OVERLAP').length,
            outside_canvas: issues.filter((issue) => issue.code === 'GEOMETRY_OUTSIDE_CANVAS').length,
            off_grid: advisories.filter((issue) => issue.code === 'GEOMETRY_OFF_GRID').length,
            clearance: advisories.filter((issue) => issue.code === 'GEOMETRY_CLEARANCE').length,
        },
        bounds: bounds ? {
            left: bounds.left,
            right: bounds.right,
            top: bounds.top,
            bottom: bounds.bottom,
            width: bounds.width,
            height: bounds.height,
        } : null,
    };
}
