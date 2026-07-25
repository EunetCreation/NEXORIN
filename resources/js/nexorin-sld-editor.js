import {
    Cell,
    EdgeHandlerConfig,
    Geometry,
    Graph,
    HandleConfig,
    InternalEvent,
    Point,
    UndoManager,
    VertexHandlerConfig,
    getDefaultPlugins,
} from '@maxgraph/core';
import SYMBOL_REGISTRY from '../data/sld-symbol-registry.json';
import { evaluateElectricalDiagram } from './sld-electrical-rule-checker.js';
import {
    routeIntersectsObstacles,
    routeIsOrthogonal,
    routeOrthogonal,
    routeOrthogonalBatch,
    routingNetworkClass,
    routingNetworkPriority,
    segmentsFromRoute,
    SLD_ORTHOGONAL_ROUTING_CONTRACT,
    SLD_SMART_ROUTER_VERSION,
} from './sld-smart-router.js';
import {
    SLD_GEOMETRY_CONTRACT,
    SLD_GEOMETRY_FOUNDATION_VERSION,
    alignGeometryRectangles,
    canonicalPortPoint,
    diagnoseGeometry,
    distributeGeometryRectangles,
    normalizeGeometryRect,
    normalizeQuarterTurn,
    transformGeometryPoint as transformObjectPoint,
    transformedGeometrySide as transformedSide,
} from './sld-geometry-foundation.js';
import {
    SLD_LABEL_PLACEMENT_CONTRACT,
    SLD_LABEL_PLACEMENT_VERSION,
    placeEngineeringLabels,
} from './sld-label-placement.js';
import {
    SLD_VISUAL_REGRESSION_CONTRACT,
    SLD_VISUAL_REGRESSION_VERSION,
    auditVisualScene,
    compareVisualSnapshots,
    createVisualSnapshot,
} from './sld-visual-regression.js';

const REGISTRY_SYMBOLS = Object.freeze(SYMBOL_REGISTRY.symbols || {});

const ROUTING_CLEARANCE_PROFILES = Object.freeze({
    compact: { label: 'Compact', clearance: 12, spacing: 9 },
    standard: { label: 'Standard', clearance: 18, spacing: 12 },
    generous: { label: 'Generous', clearance: 26, spacing: 16 },
});


const SYMBOL_DEFINITIONS = Object.freeze(Object.fromEntries(
    Object.entries(REGISTRY_SYMBOLS).map(([id, symbol]) => [id, {
        title: symbol.title,
        subtitle: symbol.subtitle,
        rating: symbol.default_rating,
        kind: symbol.kind,
        prefix: symbol.tag_prefix,
        size: [Number(symbol.nominal_size?.width || 144), Number(symbol.nominal_size?.height || 120)],
    }]),
));

const PORT_PROFILES = Object.freeze(Object.fromEntries(
    Object.entries(REGISTRY_SYMBOLS).map(([id, symbol]) => [id, Array.isArray(symbol.ports) ? symbol.ports : []]),
));

function registrySymbol(symbol = '') {
    return REGISTRY_SYMBOLS[String(symbol || '')] || REGISTRY_SYMBOLS.panel || null;
}

function registryTransformPolicy(symbol = '') {
    return registrySymbol(symbol)?.transforms || {
        rotations: [0, 90, 180, 270],
        mirror_horizontal: true,
        mirror_vertical: true,
        preserve_terminal_semantics: true,
    };
}

function registryStandard(symbol = '') {
    return registrySymbol(symbol)?.standard || {
        status: 'engineering_reference',
        status_label: 'Reference drawing form',
        family: 'NEXORIN engineering reference form',
        reference: 'User-provided symbol reference',
        source_pages: '',
        certified: false,
    };
}

const WIRE_NETWORK_DEFINITIONS = {
    dc_positive: { label: 'DC positive', short: 'DC+', layer: 'E-DC-POWER', color: '#ef6f61', polarity: '+' },
    dc_negative: { label: 'DC negative', short: 'DC−', layer: 'E-DC-POWER', color: '#b8c9cf', polarity: '−' },
    dc_power: { label: 'DC power', short: 'DC', layer: 'E-DC-POWER', color: '#db785d', polarity: '' },
    battery_positive: { label: 'Battery positive', short: 'BAT+', layer: 'E-DC-POWER', color: '#e36d58', polarity: '+' },
    battery_negative: { label: 'Battery negative', short: 'BAT−', layer: 'E-DC-POWER', color: '#aebfc6', polarity: '−' },
    battery_dc: { label: 'Battery DC', short: 'BAT DC', layer: 'E-DC-POWER', color: '#cf765e', polarity: '' },
    ac_power: { label: 'AC power', short: 'AC', layer: 'E-AC-POWER', color: '#e0a14c', polarity: '' },
    neutral: { label: 'Neutral', short: 'N', layer: 'E-NEUTRAL', color: '#62aee1', polarity: '' },
    protective_earth: { label: 'Protective earth', short: 'PE', layer: 'E-PE', color: '#4bc477', polarity: '' },
    functional_earth: { label: 'Functional earth', short: 'FE', layer: 'E-PE', color: '#65b982', polarity: '' },
    communication: { label: 'Communication', short: 'COM', layer: 'E-COMM', color: '#ad82dc', polarity: '' },
    control: { label: 'Control', short: 'CTRL', layer: 'E-CONTROL', color: '#7da3dc', polarity: '' },
    measurement: { label: 'Measurement', short: 'MEAS', layer: 'E-METERING', color: '#63b7bb', polarity: '' },
    power: { label: 'Power', short: 'PWR', layer: 'E-AC-POWER', color: '#9bb4b7', polarity: '' },
    unclassified: { label: 'Unclassified circuit', short: 'WIRE', layer: 'E-AC-POWER', color: '#9bb4b7', polarity: '' },
};

function normalizeWireNetwork(network = '') {
    const key = String(network || '').trim().toLowerCase().replaceAll('-', '_').replaceAll(' ', '_');
    if (key === 'dc+' || key === 'positive') return 'dc_positive';
    if (key === 'dc-' || key === 'negative') return 'dc_negative';
    if (key === 'pe' || key === 'earth') return 'protective_earth';
    if (key === 'ac') return 'ac_power';
    if (key === 'comm') return 'communication';
    return WIRE_NETWORK_DEFINITIONS[key] ? key : 'unclassified';
}

function wireNetworkDefinition(network = '') {
    return WIRE_NETWORK_DEFINITIONS[normalizeWireNetwork(network)] || WIRE_NETWORK_DEFINITIONS.unclassified;
}

function wireCableDetail(value = {}) {
    const parts = [];
    if (value.cable_type) parts.push(String(value.cable_type).trim());
    if (value.cable_size) {
        const rawSize = String(value.cable_size).trim();
        const hasUnit = /(?:mm\s*(?:²|2)|sq\.?\s*mm)/i.test(rawSize);
        parts.push(hasUnit ? rawSize.replace(/mm\s*2/gi, 'mm²') : `${rawSize} mm²`);
    }
    if (value.cores) parts.push(`${value.cores}C`);
    if (value.voltage_class) parts.push(String(value.voltage_class).trim());
    return parts.filter(Boolean).length > 0 ? parts.filter(Boolean).join(' · ') : (value.detail || 'Cable data not specified');
}

function distributedPortFraction(index, count, start, end) {
    if (count <= 1) return (start + end) / 2;
    return start + ((end - start) * index / (count - 1));
}

function decoratePortAnchors(ports = []) {
    const grouped = { left: [], right: [], top: [], bottom: [] };
    ports.forEach((port) => (grouped[port.side] || grouped.right).push(port));

    return ports.map((port) => {
        if (Number.isFinite(Number(port.anchor?.x)) && Number.isFinite(Number(port.anchor?.y))) {
            return { ...port, anchor: { x: Number(port.anchor.x), y: Number(port.anchor.y) } };
        }
        const side = grouped[port.side] ? port.side : 'right';
        const list = grouped[side];
        const index = Math.max(0, list.indexOf(port));
        if (side === 'left' || side === 'right') {
            return {
                ...port,
                side,
                anchor: {
                    x: side === 'left' ? 0.245 : 0.755,
                    y: distributedPortFraction(index, list.length, 0.25, 0.57),
                },
            };
        }
        return {
            ...port,
            side,
            anchor: {
                x: distributedPortFraction(index, list.length, 0.32, 0.68),
                y: side === 'top' ? 0.105 : 0.665,
            },
        };
    });
}

function portProfile(symbol, explicit = null) {
    const registry = registrySymbol(symbol);
    const defaults = [
        ...(Array.isArray(registry?.ports) ? registry.ports : []),
        ...(Array.isArray(registry?.source_ports) ? registry.source_ports : []),
    ];
    const fallback = defaults.length > 0 ? defaults : (PORT_PROFILES.panel || []);
    if (!Array.isArray(explicit) || explicit.length === 0) {
        return decoratePortAnchors((PORT_PROFILES[symbol] || fallback).map((port) => ({ ...port })));
    }

    const byId = new Map(defaults.map((port) => [String(port.id), port]));
    return decoratePortAnchors(explicit.map((port) => ({ ...(byId.get(String(port.id)) || {}), ...port })));
}

function compatiblePortPair(sourceValue, targetValue, preferredSourcePortId = null, preferredTargetPortId = null) {
    const sourcePorts = portProfile(sourceValue.symbol || sourceValue.kind, sourceValue.ports);
    const targetPorts = portProfile(targetValue.symbol || targetValue.kind, targetValue.ports);
    const canSend = (direction) => direction === 'output' || direction === 'bidirectional';
    const canReceive = (direction) => direction === 'input' || direction === 'bidirectional';

    const compatibleNetwork = (a, b) => {
        if (a === b) return true;
        const dc = new Set(['dc_power', 'dc_positive', 'dc_negative']);
        const battery = new Set(['battery_dc', 'battery_positive', 'battery_negative']);
        if (dc.has(a) && dc.has(b)) {
            if ((a === 'dc_positive' && b === 'dc_negative') || (a === 'dc_negative' && b === 'dc_positive')) return false;
            return a === 'dc_power' || b === 'dc_power';
        }
        if (battery.has(a) && battery.has(b)) {
            if ((a === 'battery_positive' && b === 'battery_negative') || (a === 'battery_negative' && b === 'battery_positive')) return false;
            return a === 'battery_dc' || b === 'battery_dc';
        }
        return false;
    };

    const orderedSourcePorts = [...sourcePorts].sort((a, b) => (a.id === preferredSourcePortId ? -1 : 0) - (b.id === preferredSourcePortId ? -1 : 0));
    const orderedTargetPorts = [...targetPorts].sort((a, b) => (a.id === preferredTargetPortId ? -1 : 0) - (b.id === preferredTargetPortId ? -1 : 0));

    for (const sourcePort of orderedSourcePorts) {
        if (preferredSourcePortId && sourcePort.id !== preferredSourcePortId) continue;
        if (!canSend(sourcePort.direction)) continue;
        for (const targetPort of orderedTargetPorts) {
            if (preferredTargetPortId && targetPort.id !== preferredTargetPortId) continue;
            if (!canReceive(targetPort.direction) || !compatibleNetwork(sourcePort.network, targetPort.network)) continue;
            const network = sourcePort.network === targetPort.network
                ? sourcePort.network
                : (sourcePort.network.includes('battery') ? 'battery_dc' : 'dc_power');
            return { sourcePort, targetPort, network: normalizeWireNetwork(network) };
        }
    }

    return null;
}

const SOURCE_TAGS = {
    pv_array: 'PVA-01',
    dc_protection: 'QSDC-01',
    inverter: 'INV-01',
    ac_protection: 'QFAC-01',
    meter: 'MTR-01',
    grid: 'GRID-01',
    battery: 'BAT-01',
    backup_panel: 'DB-ESS-01',
};

const DEFAULT_POSITIONS = {
    pv_array: { x: 110, y: 320, w: 144, h: 120 },
    dc_protection: { x: 355, y: 320, w: 144, h: 120 },
    inverter: { x: 600, y: 320, w: 144, h: 120 },
    ac_protection: { x: 845, y: 320, w: 144, h: 120 },
    meter: { x: 1090, y: 320, w: 144, h: 120 },
    grid: { x: 1335, y: 320, w: 144, h: 120 },
    battery: { x: 600, y: 610, w: 144, h: 120 },
    backup_panel: { x: 900, y: 610, w: 144, h: 120 },
};

const CANVAS = { ...SLD_GEOMETRY_CONTRACT.canvas };
const GRID_SIZE = SLD_GEOMETRY_CONTRACT.gridSize;
const MIN_ZOOM = 0.2;
const MAX_ZOOM = 2.5;
const PANEL_STORAGE_KEY = 'nexorin-sld-panels';

const LAYER_DEFAULTS = {
    'E-EQUIPMENT': { visible: true, locked: false, print: true },
    'E-DC-POWER': { visible: true, locked: false, print: true },
    'E-AC-POWER': { visible: true, locked: false, print: true },
    'E-NEUTRAL': { visible: true, locked: false, print: true },
    'E-PE': { visible: true, locked: false, print: true },
    'E-COMM': { visible: true, locked: false, print: true },
    'E-CONTROL': { visible: true, locked: false, print: true },
    'E-PROTECTION': { visible: true, locked: false, print: true },
    'E-METERING': { visible: true, locked: false, print: true },
    'E-ANNOTATION': { visible: true, locked: false, print: true },
    'E-BOUNDARY': { visible: true, locked: false, print: true },
    'E-EXISTING': { visible: true, locked: false, print: true },
    'E-FUTURE': { visible: true, locked: false, print: true },
};


const OSNAP_DEFAULTS = {
    endpoint: true,
    midpoint: true,
    center: true,
    geometric_center: true,
    quadrant: true,
    intersection: true,
    nearest: true,
    port: true,
};

function osnapKeyForType(type = '') {
    const normalized = String(type).replace(/([a-z])([A-Z])/g, '$1_$2').toLowerCase();
    return normalized === 'geometric_center' ? 'geometric_center' : normalized;
}

function equipmentLayer(value = {}) {
    const symbol = String(value.symbol || value.kind || value.id || '');
    if (['dc_protection', 'dc_isolator', 'dc_spd', 'dc_breaker', 'dc_fuse', 'gfpd', 'afci', 'ac_protection', 'ac_breaker', 'ac_isolator', 'ac_spd', 'lightning_arrester', 'battery_disconnect'].includes(symbol)) return 'E-PROTECTION';
    if (['meter', 'app_pln', 'ct', 'pt', 'relay', 'pcc'].includes(symbol)) return 'E-METERING';
    if (['ground', 'ground_rod', 'pe_bus'].includes(symbol)) return 'E-PE';
    if (['monitoring', 'data_logger', 'plant_controller', 'scada', 'weather_station', 'irradiance_sensor', 'bms'].includes(symbol)) return 'E-CONTROL';
    return 'E-EQUIPMENT';
}

function isDraftingLineValue(value = {}) {
    return ['line', 'leader'].includes(String(value?.entity || ''));
}

function objectLayer(value = {}) {
    if (value.layer && String(value.layer).startsWith('E-')) return String(value.layer);
    if (['annotation', 'derived_label', 'label_leader'].includes(value.entity)) return 'E-ANNOTATION';
    if (value.entity === 'shape') return 'E-BOUNDARY';
    if (isDraftingLineValue(value)) return value.layer === 'SLD-ANNOTATION' || value.entity === 'leader' ? 'E-ANNOTATION' : 'E-BOUNDARY';
    if (value.entity === 'edge') return wireNetworkDefinition(value.network).layer;
    return equipmentLayer(value);
}

HandleConfig.fillColor = '#f7fbfb';
HandleConfig.strokeColor = '#2b9fcb';
HandleConfig.size = 9;
HandleConfig.labelFillColor = '#f7fbfb';
HandleConfig.labelSize = 8;
VertexHandlerConfig.selectionColor = '#45aee0';
VertexHandlerConfig.selectionStrokeWidth = 1.4;
VertexHandlerConfig.selectionDashed = false;
EdgeHandlerConfig.selectionColor = '#45aee0';
EdgeHandlerConfig.selectionStrokeWidth = 1.4;
EdgeHandlerConfig.selectionDashed = false;
EdgeHandlerConfig.connectFillColor = '#45d5c9';

function safeJson(node) {
    try {
        return JSON.parse(node?.textContent || '{}');
    } catch (error) {
        console.error('NEXORIN SLD payload could not be parsed.', error);
        return {};
    }
}

function finite(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function slug(value) {
    return String(value || 'object').replace(/[^a-zA-Z0-9_-]+/g, '_').slice(0, 70);
}

function uid(prefix = 'manual') {
    return `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function transformLabel(value = {}) {
    const rotation = normalizeQuarterTurn(value.rotation);
    const mirrors = [value.flip_h === true ? 'MIRROR H' : '', value.flip_v === true ? 'MIRROR V' : ''].filter(Boolean);
    return `${rotation}°${mirrors.length ? ` · ${mirrors.join(' · ')}` : ' · NORMAL'}`;
}

function nodeIcon(kind) {
    const secondary = 'class="nxsld-symbol-secondary"';
    const k = kind || 'panel';

    if (k === 'pv_array') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><path d="M11 9h46l6 31H17zM24 9l-4 31M42 9l2 31M13 20h47M15 30h46"/><path ${secondary} d="M8 47h56M27 47l-9 10M45 47l9 10"/><path d="M57 5v9M52 9.5h10"/></svg>`;
    }
    if (k === 'pv_module') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><path d="M10 12h52v34H10zM27 12v34M45 12v34M10 29h52"/><path ${secondary} d="M4 29h6M62 29h6"/><path d="M58 6v9M54 10.5h8"/></svg>`;
    }
    if (k === 'pv_string') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path d="M4 14h19v24H4zM28 14h19v24H28zM52 14h19v24H52zM10 14v24M17 14v24M34 14v24M41 14v24M58 14v24M65 14v24M4 26h19M28 26h19M52 26h19"/><path ${secondary} d="M23 26h5M47 26h5M0 26h4M71 26h5M24 48h28"/></svg>`;
    }
    if (k === 'sub_array') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><rect ${secondary} x="2" y="8" width="72" height="42" rx="2" stroke-dasharray="4 3"/><path d="M8 16h17v22H8zM29.5 16h17v22h-17zM51 16h17v22H51zM13 16v22M20 16v22M34.5 16v22M41.5 16v22M56 16v22M63 16v22M8 27h17M29.5 27h17M51 27h17"/><path ${secondary} d="M25 27h4.5M46.5 27H51M20 55h36"/></svg>`;
    }
    if (k === 'junction_box') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><rect ${secondary} x="15" y="10" width="42" height="42" rx="3"/><path d="M3 20h12M3 31h12M3 42h12M57 31h12M25 20h12M25 31h12M25 42h12M37 20v22M37 31h12"/></svg>`;
    }
    if (k === 'power_optimizer' || k === 'dc_dc_converter') {
        const label = k === 'power_optimizer' ? 'OPT' : 'DC/DC';
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><rect ${secondary} x="10" y="11" width="52" height="40" rx="3"/><path d="M3 31h7M62 31h7M20 36l9-12 9 12 10-15"/><text x="36" y="47" text-anchor="middle">${label}</text></svg>`;
    }
    if (k === 'rsd' || k === 'epo') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><circle ${secondary} cx="36" cy="31" r="23"/><circle cx="36" cy="31" r="10"/><path d="M36 8v8M36 46v8M13 31h8M51 31h8"/><text x="36" y="35" text-anchor="middle">${k === 'epo' ? 'E' : 'R'}</text></svg>`;
    }
    if (k === 'microinverter') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><rect ${secondary} x="12" y="13" width="48" height="36" rx="3"/><path d="M16 45 56 17M20 25h12M42 38c4-10 8-10 12 0"/><path d="M3 31h9M60 31h9"/></svg>`;
    }
    if (k === 'mppt') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><rect ${secondary} x="10" y="10" width="52" height="42" rx="3"/><path d="M4 22h6M4 40h6M62 22h6M62 40h6M19 40 30 20l9 14 10-18"/><text x="36" y="48" text-anchor="middle">MPPT</text></svg>`;
    }
    if (k === 'combiner') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><rect x="14" y="8" width="44" height="46" rx="2"/><path d="M4 17h10M4 28h10M4 39h10M58 28h10M24 17h12M24 28h12M24 39h12M36 17v22M36 28h12"/><path ${secondary} d="M48 39v10M42 49h12"/></svg>`;
    }
    if (['dc_protection', 'ac_protection'].includes(k)) {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path ${secondary} d="M3 29h18m20 0h13m18 0h1M21 29 41 14M54 20h16v18H54z"/><circle cx="21" cy="29" r="2"/><circle cx="41" cy="29" r="2"/><path d="M62 38v11m-10 0h20M57 55h10"/></svg>`;
    }
    if (k === 'dc_isolator' || k === 'ac_isolator') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path ${secondary} d="M3 31h22m28 0h20"/><circle cx="25" cy="31" r="2.4"/><circle cx="53" cy="31" r="2.4"/><path d="M25 31 51 13M51 13h4"/><text x="38" y="56" text-anchor="middle">${k === 'dc_isolator' ? 'DC' : 'AC'}</text></svg>`;
    }
    if (k === 'dc_spd' || k === 'ac_spd') {
        return `<svg viewBox="0 0 70 64" aria-hidden="true"><path ${secondary} d="M3 20h24M35 7v13M35 50v8M19 58h32M25 63h20"/><rect x="27" y="20" width="16" height="30"/><path d="M31 43 39 27M31 27h8"/><text x="55" y="26" text-anchor="middle">${k === 'dc_spd' ? 'DC' : 'AC'}</text></svg>`;
    }
    if (k === 'ac_breaker') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path ${secondary} d="M3 31h22m28 0h20"/><circle cx="25" cy="31" r="2.4"/><circle cx="53" cy="31" r="2.4"/><path d="M25 31 49 13M49 13h4v18"/><text x="38" y="56" text-anchor="middle">QF AC</text></svg>`;
    }
    if (['inverter', 'hybrid_inverter', 'offgrid_inverter', 'central_inverter', 'pcs'].includes(k)) {
        const mark = k === 'pcs' ? '↔' : (k === 'hybrid_inverter' ? 'H' : (k === 'central_inverter' ? 'C' : ''));
        return `<svg viewBox="0 0 70 64" aria-hidden="true"><rect ${secondary} x="6" y="4" width="58" height="56" rx="3"/><path ${secondary} d="M10 56 60 8"/><path d="M15 18h18M15 25h12M42 43c5-13 10-13 15 0s10 13 13 0"/>${mark ? `<text x="35" y="55" text-anchor="middle">${mark}</text>` : ''}</svg>`;
    }
    if (['battery_cell', 'battery_module', 'battery_rack', 'battery'].includes(k)) {
        const cells = k === 'battery_cell' ? 1 : (k === 'battery_module' ? 3 : 4);
        const bars = Array.from({ length: cells }, (_, index) => `<path d="M${18 + index * 10} 20v22M${22 + index * 10} 24v14"/>`).join('');
        return `<svg viewBox="0 0 74 54" aria-hidden="true"><rect ${secondary} x="5" y="8" width="60" height="38" rx="3"/><rect x="65" y="19" width="5" height="16" rx="1"/>${bars}<path ${secondary} d="M17 5v6M14 8h6M51 8h7"/></svg>`;
    }
    if (k === 'bms' || k === 'relay' || k === 'scada') {
        return `<svg viewBox="0 0 72 60" aria-hidden="true"><rect ${secondary} x="8" y="8" width="56" height="44" rx="3"/><path d="M16 18h40M16 28h28M16 38h36"/><text x="36" y="49" text-anchor="middle">${k === 'bms' ? 'BMS' : (k === 'relay' ? 'RY' : 'RTU')}</text></svg>`;
    }
    if (k === 'battery_disconnect') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path ${secondary} d="M3 31h22m28 0h20"/><circle cx="25" cy="31" r="2.4"/><circle cx="53" cy="31" r="2.4"/><path d="M25 31 51 13"/></svg>`;
    }
    if (k === 'dc_fuse') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path ${secondary} d="M3 31h24m22 0h24"/><rect x="27" y="21" width="22" height="20"/><path d="M31 37 45 25"/></svg>`;
    }
    if (k === 'dc_breaker' || k === 'gfpd' || k === 'afci') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path ${secondary} d="M3 31h22m28 0h20"/><circle cx="25" cy="31" r="2.4"/><circle cx="53" cy="31" r="2.4"/><path d="M25 31 49 13M49 13h4v18"/>${['gfpd','afci'].includes(k) ? `<rect x="16" y="7" width="44" height="42"/><text x="38" y="56" text-anchor="middle">${k === 'gfpd' ? 'GFPD' : 'AFCI'}</text>` : ''}</svg>`;
    }
    if (k === 'dc_busbar') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path d="M8 23h60M8 39h60" stroke-width="4"/><path ${secondary} d="M18 12v11M34 12v11M50 39v11M64 39v11"/></svg>`;
    }
    if (['panel', 'main_panel', 'critical_panel', 'load', 'ac_combiner'].includes(k)) {
        const branchCount = k === 'main_panel' ? 4 : (k === 'critical_panel' ? 2 : 3);
        const label = k === 'ac_combiner' ? 'AC' : (k === 'critical_panel' ? 'CRIT' : (k === 'main_panel' ? 'MDB' : (k === 'load' ? 'LOAD' : 'DB')));
        const branches = Array.from({ length: branchCount }, (_, index) => {
            const y = 18 + index * 9;
            return `<path d="M22 ${y}h31M47 ${y - 3}v6"/>`;
        }).join('');
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><rect ${secondary} x="10" y="7" width="52" height="48" rx="2"/><path d="M20 13v36" stroke-width="3"/>${branches}<text x="41" y="52" text-anchor="middle">${label}</text></svg>`;
    }
    if (k === 'meter' || k === 'app_pln') {
        return `<svg viewBox="0 0 64 64" aria-hidden="true"><rect ${secondary} x="10" y="5" width="44" height="54"/><path d="M16 16h32M17 24h30"/><text x="32" y="43" text-anchor="middle">Wh</text><path d="M17 51h30M17 51l6-4m-6 4 6 4M47 51l-6-4m6 4-6 4"/></svg>`;
    }
    if (k === 'grid') {
        return `<svg viewBox="0 0 70 66" aria-hidden="true"><path ${secondary} d="M35 4 12 61M35 4l23 57M21 23h28M17 37h36M12 61h46M27 15l16 0M25 23l20 14M45 23 25 37M35 4v57"/><path d="M2 12h22M46 12h22"/></svg>`;
    }
    if (k === 'pcc') {
        return `<svg viewBox="0 0 64 64" aria-hidden="true"><circle ${secondary} cx="32" cy="32" r="18"/><path d="M2 32h12M50 32h12"/><text x="32" y="36" text-anchor="middle">PCC</text></svg>`;
    }
    if (k === 'transformer') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><path ${secondary} d="M2 31h13M57 31h13"/><circle cx="29" cy="31" r="15"/><circle cx="43" cy="31" r="15"/></svg>`;
    }
    if (k === 'ct') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><path ${secondary} d="M2 31h68"/><circle cx="36" cy="31" r="17"/><path d="M40 48v11M48 46v13"/></svg>`;
    }
    if (k === 'pt') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><path ${secondary} d="M36 2v11M36 49v11"/><circle cx="36" cy="26" r="13"/><circle cx="36" cy="38" r="13"/></svg>`;
    }
    if (k === 'generator') {
        return `<svg viewBox="0 0 64 64" aria-hidden="true"><circle ${secondary} cx="32" cy="32" r="25"/><text x="32" y="38" text-anchor="middle">G</text><path d="M57 32h6"/></svg>`;
    }
    if (k === 'weather_station') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><path ${secondary} d="M36 8v46M18 54h36M20 20h32M26 20l10 14 10-14"/><circle cx="36" cy="8" r="4"/><path d="M12 12h10M50 12h10M8 31h12M52 31h12"/></svg>`;
    }
    if (k === 'irradiance_sensor') {
        return `<svg viewBox="0 0 72 62" aria-hidden="true"><path ${secondary} d="M16 44h40M22 44a14 14 0 0 1 28 0"/><path d="M36 7v12M18 14l8 9M54 14l-8 9"/></svg>`;
    }
    if (k === 'ground_rod') {
        return `<svg viewBox="0 0 64 58" aria-hidden="true"><path ${secondary} d="M32 4v48M15 36h34M20 44h24M25 52h14"/><path d="M32 52v5"/></svg>`;
    }
    if (k === 'lightning_arrester') {
        return `<svg viewBox="0 0 64 62" aria-hidden="true"><path ${secondary} d="M32 3v13M32 46v10M12 56h40M18 61h28"/><rect x="23" y="16" width="18" height="30"/><path d="M27 38 37 23M27 23h10"/></svg>`;
    }
    if (k === 'ground') {
        return `<svg viewBox="0 0 64 58" aria-hidden="true"><path ${secondary} d="M32 5v30M10 35h44M17 43h30M24 51h16"/><circle cx="32" cy="5" r="2"/></svg>`;
    }
    if (k === 'transfer_switch') {
        return `<svg viewBox="0 0 72 60" aria-hidden="true"><path ${secondary} d="M5 13h20M5 47h20M48 30h19M25 13l23 17M25 47l23-17"/><circle cx="25" cy="13" r="2"/><circle cx="25" cy="47" r="2"/><circle cx="48" cy="30" r="2"/></svg>`;
    }
    if (k === 'monitoring') {
        return `<svg viewBox="0 0 72 60" aria-hidden="true"><rect ${secondary} x="6" y="6" width="60" height="40" rx="3"/><path d="M16 37h40M21 32l9-9 8 5 12-13"/><path ${secondary} d="M28 54h16M36 46v8"/></svg>`;
    }
    if (k === 'data_logger') {
        return `<svg viewBox="0 0 72 60" aria-hidden="true"><rect ${secondary} x="8" y="7" width="56" height="45" rx="3"/><path d="M17 17h38M17 26h24M17 36h38M20 45h5M31 45h5M42 45h5"/><text x="54" y="47" text-anchor="middle">DL</text></svg>`;
    }
    if (k === 'plant_controller') {
        return `<svg viewBox="0 0 72 60" aria-hidden="true"><rect ${secondary} x="7" y="7" width="58" height="46" rx="3"/><path d="M17 18h38M17 29h38M17 40h38M25 14v32M47 14v32"/><text x="36" y="58" text-anchor="middle">PLC</text></svg>`;
    }
    if (k === 'pe_bus') {
        return `<svg viewBox="0 0 76 62" aria-hidden="true"><path d="M8 30h60" stroke-width="4"/><path ${secondary} d="M18 14v16M31 30v16M45 14v16M58 30v16M38 46v9M26 55h24M31 60h14"/><text x="38" y="11" text-anchor="middle">PE</text></svg>`;
    }
    return `<svg viewBox="0 0 70 60" aria-hidden="true"><rect ${secondary} x="7" y="6" width="56" height="48" rx="2"/><path d="M16 18h38M16 30h38M16 42h38"/><circle cx="23" cy="18" r="2"/><circle cx="23" cy="30" r="2"/><circle cx="23" cy="42" r="2"/></svg>`;
}

function nodeMarkup(value) {
    const manual = value.manual === true || String(value.state || '').toLowerCase() === 'manual';
    const attention = ['attention', 'critical', 'blocked', 'error'].includes(String(value.state || '').toLowerCase());
    const locked = value.locked === true;
    const rotation = normalizeQuarterTurn(value.rotation);
    const flipH = value.flip_h === true;
    const flipV = value.flip_v === true;
    const groupClass = value.group_id ? ' is-grouped' : '';
    const toneClass = `${manual ? 'is-manual' : (attention ? 'is-attention' : 'is-source')}${locked ? ' is-object-locked' : ''}${groupClass} is-rotation-${rotation}${flipH ? ' is-flipped-h' : ''}${flipV ? ' is-flipped-v' : ''}`;
    const tag = value.show_tag === false || !value.tag ? '' : `<span class="nxsld-device-tag">${escapeHtml(value.tag)}</span>`;
    const rating = value.show_rating === false ? '' : `<b>${escapeHtml(value.rating || 'Rating not specified')}</b>`;
    const subtitle = value.show_subtitle === false ? '' : `<small>${escapeHtml(value.subtitle || 'Electrical component')}</small>`;
    const ports = value.show_ports === false ? '' : (() => {
        const profile = portProfile(value.symbol || value.kind, value.ports);
        return profile.map((port) => {
            const side = ['left', 'right', 'top', 'bottom'].includes(port.side) ? port.side : 'right';
            const base = {
                x: Number.isFinite(Number(port.anchor?.x)) ? Number(port.anchor.x) : 0.5,
                y: Number.isFinite(Number(port.anchor?.y)) ? Number(port.anchor.y) : 0.5,
            };
            const transformed = transformObjectPoint(base, { x: 0.5, y: 0.5 }, value);
            const network = normalizeWireNetwork(port.network);
            const direction = ['input', 'output', 'bidirectional'].includes(port.direction) ? port.direction : 'bidirectional';
            const definition = wireNetworkDefinition(network);
            return `<span class="nxsld-device-port is-smart-port is-${escapeHtml(direction)} is-side-${escapeHtml(side)} is-network-${escapeHtml(network)}" data-nxsld-port-id="${escapeHtml(port.id)}" data-nxsld-port-side="${escapeHtml(side)}" data-nxsld-port-network="${escapeHtml(network)}" data-nxsld-port-direction="${escapeHtml(direction)}" data-nxsld-port-terminal-class="${escapeHtml(port.terminal_class || network)}" data-nxsld-port-label="${escapeHtml(port.label || port.id)}" style="left:${(transformed.x * 100).toFixed(3)}%;top:${(transformed.y * 100).toFixed(3)}%;--nxsld-port-tone:${definition.color}" aria-hidden="true"><i></i></span>`;
        }).join('');
    })();
    const sourceLabel = manual ? 'Manual draft' : 'Calculation source';
    const lockBadge = locked ? '<span class="nxsld-device-lock" title="Object locked"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>' : '';
    const groupBadge = value.group_id ? '<span class="nxsld-device-group" title="Grouped object"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="7" height="7"/><rect x="13" y="12" width="7" height="7"/><path d="M11 8.5h3M10 12v3"/></svg></span>' : '';
    const transformStyle = `--nxsld-symbol-rotation:${rotation}deg;--nxsld-symbol-flip-x:${flipH ? -1 : 1};--nxsld-symbol-flip-y:${flipV ? -1 : 1}`;
    const registry = registrySymbol(value.symbol || value.id || value.kind);
    const standardStatus = String(registry?.standard?.status || 'nexorin_assembly').replaceAll('_', '-');

    return `<div class="nxsld-device has-auto-label ${toneClass} is-standard-${escapeHtml(standardStatus)}" data-nxsld-node-id="${escapeHtml(value.id || '')}" data-nxsld-symbol-id="${escapeHtml(value.symbol || value.id || '')}" data-transform="${escapeHtml(transformLabel(value))}">${lockBadge}${groupBadge}${ports}<span class="nxsld-device-symbol" data-nxsld-symbol-glyph="${escapeHtml(value.symbol || value.id || '')}" style="${transformStyle}">${nodeIcon(value.symbol || value.id)}</span><span class="nxsld-device-copy">${tag}<strong>${escapeHtml(value.title || 'Electrical component')}</strong>${rating}${subtitle}<em>${sourceLabel}</em></span></div>`;
}

function derivedLabelMarkup(value = {}) {
    const tag = value.show_tag === false || !value.tag ? '' : `<span class="nxsld-auto-label-tag">${escapeHtml(value.tag)}</span>`;
    const rating = value.show_rating === false || !value.rating ? '' : `<b>${escapeHtml(value.rating)}</b>`;
    const subtitle = value.show_subtitle === false || !value.subtitle ? '' : `<small>${escapeHtml(value.subtitle)}</small>`;
    const side = ['top', 'right', 'bottom', 'left'].includes(value.side) ? value.side : 'bottom';
    return `<div class="nxsld-auto-label is-side-${side}${value.fallback ? ' is-fallback' : ''}" data-nxsld-auto-label="${escapeHtml(value.node_id || '')}" data-label-signature="${escapeHtml(value.signature || '')}">${tag}<strong>${escapeHtml(value.title || 'Electrical component')}</strong>${rating}${subtitle}</div>`;
}

function noteMarkup(value) {
    const rotation = normalizeQuarterTurn(value.rotation);
    const flipH = value.flip_h === true;
    const flipV = value.flip_v === true;
    const style = `--nxsld-object-rotation:${rotation}deg;--nxsld-object-flip-x:${flipH ? -1 : 1};--nxsld-object-flip-y:${flipV ? -1 : 1}`;
    return `<div class="nxsld-note-label${value.group_id ? ' is-grouped' : ''}" style="${style}"><span>NOTE</span>${escapeHtml(value.text || value.title || 'Drawing note')}</div>`;
}

function edgeMarkup(value) {
    const network = normalizeWireNetwork(value.network);
    const definition = wireNetworkDefinition(network);
    const label = value.show_label === false ? '' : (value.cable_tag || value.wire_id || value.label || (value.manual ? definition.short : ''));
    if (!label) return '';
    const detail = wireCableDetail(value);
    return `<span class="nxsld-edge-label is-smart-wire is-network-${escapeHtml(network)}${value.manual ? ' is-manual' : ''}" style="--nxsld-wire-tone:${definition.color}"><i></i><strong>${escapeHtml(label)}</strong><small>${escapeHtml(detail)}</small></span>`;
}

function labelVertexStyle() {
    return {
        shape: 'rectangle', html: true, fillColor: 'none', strokeColor: 'none',
        overflow: 'fill', whiteSpace: 'wrap', align: 'left', verticalAlign: 'top',
        spacing: 0, rounded: false, shadow: false, editable: false, resizable: false,
    };
}

function labelLeaderStyle() {
    return {
        noEdgeStyle: true, orthogonalLoop: false, rounded: false,
        strokeWidth: 1.1, strokeColor: '#6e8d96', endArrow: 'none', startArrow: 'none',
        html: false, editable: false, dashed: false,
    };
}

function vertexStyle(value) {
    return {
        shape: 'rectangle',
        html: true,
        fillColor: 'none',
        strokeColor: 'none',
        perimeter: 'rectanglePerimeter',
        overflow: 'fill',
        whiteSpace: 'wrap',
        align: 'left',
        verticalAlign: 'top',
        spacing: 0,
        spacingTop: 0,
        spacingLeft: 0,
        rounded: false,
        shadow: false,
        editable: false,
        // Drafting geometry may be resized for both generated and manual equipment.
        // Technical values remain governed separately in the inspector.
        resizable: ['node', 'annotation'].includes(value.entity) || value.manual === true,
    };
}

function networkVisual(network = '', manual = false, sourceKind = '', targetKind = '') {
    let normalized = normalizeWireNetwork(network);
    const isDcByKind = [sourceKind, targetKind].some((kind) => ['generation', 'storage', 'conversion'].includes(kind));
    if (normalized === 'unclassified' && isDcByKind) normalized = 'dc_power';
    const definition = wireNetworkDefinition(normalized);
    if (normalized === 'communication') return { color: definition.color, dashed: true, dashPattern: '7 4', width: 1.55 };
    if (normalized === 'protective_earth') return { color: definition.color, dashed: true, dashPattern: '4 3', width: 1.7 };
    if (normalized === 'control') return { color: definition.color, dashed: true, dashPattern: '9 4 2 4', width: 1.55 };
    if (normalized === 'measurement') return { color: definition.color, dashed: true, dashPattern: '3 3', width: 1.5 };
    if (normalized === 'neutral') return { color: definition.color, dashed: false, width: 1.75 };
    if (normalized === 'dc_positive' || normalized === 'dc_negative' || normalized.includes('battery')) return { color: definition.color, dashed: false, width: 1.85 };
    if (normalized === 'dc_power') return { color: definition.color, dashed: false, width: 1.85 };
    if (normalized === 'ac_power') return { color: definition.color, dashed: false, width: 1.9 };
    return { color: manual ? '#d19345' : '#a7c8ca', dashed: manual, dashPattern: manual ? '7 4' : undefined, width: manual ? 1.8 : 2.1 };
}

function edgeStyle(manual = false, sourceKind = '', targetKind = '', network = '') {
    const visual = networkVisual(network, manual, sourceKind, targetKind);
    return {
        edgeStyle: 'orthogonalEdgeStyle',
        orthogonalLoop: true,
        jettySize: 18,
        rounded: false,
        strokeWidth: visual.width,
        strokeColor: visual.color,
        endArrow: 'none',
        startArrow: 'none',
        html: true,
        fontColor: visual.color,
        fontSize: 8,
        labelBackgroundColor: '#07151e',
        labelBorderColor: 'none',
        editable: false,
        exitX: 0.74,
        exitY: 0.5,
        exitPerimeter: false,
        entryX: 0.26,
        entryY: 0.5,
        entryPerimeter: false,
        dashed: visual.dashed,
        dashPattern: visual.dashPattern,
    };
}

function manualEdgeStyle(sourceKind = '', targetKind = '', orthoEnabled = true, network = '') {
    const style = edgeStyle(true, sourceKind, targetKind, network);
    // Engineering wires remain orthogonal by default. The flag only controls
    // restored legacy wires that were explicitly saved as free-route objects.
    if (!orthoEnabled && String(network || '').toLowerCase() === 'annotation') {
        delete style.edgeStyle;
        style.noEdgeStyle = true;
        style.orthogonalLoop = false;
    }
    return style;
}

function draftingLineStyle(value = {}) {
    const lineType = String(value.line_type || 'continuous').toLowerCase();
    const lineWeight = clamp(finite(value.lineweight, 1.4), 0.6, 5);
    const leader = value.entity === 'leader';
    return {
        noEdgeStyle: true,
        orthogonalLoop: false,
        rounded: false,
        strokeWidth: lineWeight,
        strokeColor: leader ? '#d7e7e8' : '#bfd4d6',
        endArrow: 'none',
        startArrow: leader ? 'classic' : 'none',
        startFill: leader,
        startSize: leader ? 9 : 0,
        html: false,
        editable: false,
        dashed: lineType !== 'continuous',
        dashPattern: lineType === 'center' ? '10 4 2 4' : (lineType === 'hidden' ? '6 4' : undefined),
    };
}


function normalizeAngle(angle) {
    return ((finite(angle, 0) % 360) + 360) % 360;
}

function angleFromCenter(center, point) {
    return normalizeAngle(Math.atan2(point.y - center.y, point.x - center.x) * 180 / Math.PI);
}

function geometryDistance(a, b) {
    return Math.hypot((b?.x || 0) - (a?.x || 0), (b?.y || 0) - (a?.y || 0));
}

function circleThroughThreePoints(a, b, c) {
    const d = 2 * (a.x * (b.y - c.y) + b.x * (c.y - a.y) + c.x * (a.y - b.y));
    if (Math.abs(d) < 0.00001) return null;
    const aa = a.x * a.x + a.y * a.y;
    const bb = b.x * b.x + b.y * b.y;
    const cc = c.x * c.x + c.y * c.y;
    const center = {
        x: (aa * (b.y - c.y) + bb * (c.y - a.y) + cc * (a.y - b.y)) / d,
        y: (aa * (c.x - b.x) + bb * (a.x - c.x) + cc * (b.x - a.x)) / d,
    };
    const radius = geometryDistance(center, a);
    if (!Number.isFinite(radius) || radius < 0.5 || radius > 100000) return null;
    return { center, radius };
}

function arcFromThreePoints(start, through, end) {
    const circle = circleThroughThreePoints(start, through, end);
    if (!circle) return null;
    const startAngle = angleFromCenter(circle.center, start);
    const throughAngle = angleFromCenter(circle.center, through);
    const endAngle = angleFromCenter(circle.center, end);
    const clockwiseDelta = normalizeAngle(endAngle - startAngle);
    const throughClockwiseDelta = normalizeAngle(throughAngle - startAngle);
    const clockwise = throughClockwiseDelta <= clockwiseDelta + 0.0001;
    const sweepDelta = clockwise ? clockwiseDelta : normalizeAngle(startAngle - endAngle);
    return {
        center: circle.center,
        radius: circle.radius,
        start_angle: startAngle,
        end_angle: endAngle,
        sweep_flag: clockwise ? 1 : 0,
        large_arc: sweepDelta > 180 ? 1 : 0,
        angle_basis: 'screen',
    };
}

function arcFromCenterStartEnd(center, start, end) {
    const radius = geometryDistance(center, start);
    if (radius < 0.5) return null;
    const startAngle = angleFromCenter(center, start);
    const endAngle = angleFromCenter(center, end);
    const clockwiseDelta = normalizeAngle(endAngle - startAngle);
    const counterClockwiseDelta = normalizeAngle(startAngle - endAngle);
    const clockwise = clockwiseDelta <= counterClockwiseDelta;
    return {
        center,
        radius,
        start_angle: startAngle,
        end_angle: endAngle,
        sweep_flag: clockwise ? 1 : 0,
        large_arc: 0,
        angle_basis: 'screen',
    };
}

function arcSvgPath(startAngle, endAngle, sweepFlag = 1, largeArc = null, angleBasis = 'legacy') {
    const start = normalizeAngle(startAngle);
    const end = normalizeAngle(endAngle);
    const radius = 50;
    const cx = 50;
    const cy = 50;
    const screenBasis = angleBasis === 'screen';
    const polar = (angle) => {
        const radians = angle * Math.PI / 180;
        return { x: cx + radius * Math.cos(radians), y: cy + (screenBasis ? 1 : -1) * radius * Math.sin(radians) };
    };
    const a = polar(start);
    const b = polar(end);
    const sweep = Number(sweepFlag) === 0 ? 0 : 1;
    let delta = sweep ? normalizeAngle(end - start) : normalizeAngle(start - end);
    if (delta === 0) delta = 359.999;
    const large = largeArc == null ? (delta > 180 ? 1 : 0) : (Number(largeArc) ? 1 : 0);
    return `M ${a.x.toFixed(3)} ${a.y.toFixed(3)} A ${radius} ${radius} 0 ${large} ${sweep} ${b.x.toFixed(3)} ${b.y.toFixed(3)}`;
}

function geometryMarkup(value) {
    const type = String(value.shape_type || 'rectangle');
    const lineType = ['continuous', 'hidden', 'center'].includes(String(value.line_type || '').toLowerCase()) ? String(value.line_type).toLowerCase() : 'continuous';
    const lineWeight = clamp(finite(value.lineweight, 1.4), 0.6, 5);
    const base = `vector-effect="non-scaling-stroke" style="stroke-width:${lineWeight}px"`;
    const rotation = normalizeQuarterTurn(value.rotation);
    const flipH = value.flip_h === true;
    const flipV = value.flip_v === true;
    const classes = `nxsld-geometry-object is-${type} is-${lineType}${value.group_id ? ' is-grouped' : ''}`;
    const style = `--nxsld-object-rotation:${rotation}deg;--nxsld-object-flip-x:${flipH ? -1 : 1};--nxsld-object-flip-y:${flipV ? -1 : 1}`;
    if (type === 'circle') {
        return `<div class="${classes}" style="${style}"><svg viewBox="0 0 100 100" preserveAspectRatio="none"><ellipse ${base} cx="50" cy="50" rx="50" ry="50"/></svg></div>`;
    }
    if (type === 'arc') {
        return `<div class="${classes}" style="${style}"><svg viewBox="0 0 100 100" preserveAspectRatio="none"><path ${base} d="${arcSvgPath(value.start_angle, value.end_angle, value.sweep_flag, value.large_arc, value.angle_basis)}"/></svg></div>`;
    }
    return `<div class="${classes}" style="${style}"><svg viewBox="0 0 100 100" preserveAspectRatio="none"><rect ${base} x="0" y="0" width="100" height="100"/></svg></div>`;
}

function geometryShapeStyle(value = {}) {
    return {
        shape: 'rectangle',
        html: true,
        fillColor: 'none',
        strokeColor: 'none',
        perimeter: 'rectanglePerimeter',
        overflow: 'fill',
        whiteSpace: 'wrap',
        align: 'left',
        verticalAlign: 'top',
        spacing: 0,
        rounded: false,
        shadow: false,
        editable: false,
        resizable: true,
        rotatable: false,
    };
}

function defaultPosition(id, index, editorPosition, sourceDefault = null) {
    const sourceBase = sourceDefault && Number.isFinite(Number(sourceDefault.x)) && Number.isFinite(Number(sourceDefault.y))
        ? {
            x: Number(sourceDefault.x),
            y: Number(sourceDefault.y),
            w: Math.max(80, finite(sourceDefault.w, 144)),
            h: Math.max(50, finite(sourceDefault.h, 120)),
        }
        : null;
    const base = sourceBase || DEFAULT_POSITIONS[id] || {
        x: 120 + (index % 5) * 245,
        y: 600 + Math.floor(index / 5) * 190,
        w: 144,
        h: 120,
    };
    const storedWidth = finite(editorPosition?.w, base.w);
    const storedHeight = finite(editorPosition?.h, base.h);
    const legacyCardGeometry = (Math.abs(storedWidth - 172) < 1 && Math.abs(storedHeight - 126) < 1)
        || (Math.abs(storedWidth - 136) < 1 && Math.abs(storedHeight - 126) < 1);
    const geometry = normalizeGeometryRect({
        x: finite(editorPosition?.x, base.x),
        y: finite(editorPosition?.y, base.y),
        w: legacyCardGeometry ? base.w : storedWidth,
        h: legacyCardGeometry ? base.h : storedHeight,
    }, {
        minWidth: SLD_GEOMETRY_CONTRACT.equipment.minWidth,
        minHeight: SLD_GEOMETRY_CONTRACT.equipment.minHeight,
        maxWidth: SLD_GEOMETRY_CONTRACT.equipment.maxWidth,
        maxHeight: SLD_GEOMETRY_CONTRACT.equipment.maxHeight,
        defaultWidth: base.w,
        defaultHeight: base.h,
    });
    return {
        ...geometry,
        locked: editorPosition?.locked === true,
        rotation: normalizeQuarterTurn(editorPosition?.rotation),
        flip_h: editorPosition?.flip_h === true,
        flip_v: editorPosition?.flip_v === true,
        group_id: editorPosition?.group_id || null,
        show_tag: editorPosition?.show_tag !== false,
        show_rating: editorPosition?.show_rating !== false,
        show_subtitle: editorPosition?.show_subtitle !== false,
        show_ports: editorPosition?.show_ports !== false,
        tag_override: typeof editorPosition?.tag_override === 'string' ? editorPosition.tag_override : null,
    };
}

function startEditor(root) {
    const payload = safeJson(root.querySelector('[data-sld-editor-payload]'));
    const graphPayload = payload.graph || {};
    const editorPayload = graphPayload.editor || payload.editor || {};
    const sourceNodes = Array.isArray(graphPayload.nodes) ? graphPayload.nodes : [];
    const sourceConnections = Array.isArray(graphPayload.connections) ? graphPayload.connections : [];
    const manualNodes = Array.isArray(editorPayload.manual_nodes) ? editorPayload.manual_nodes : [];
    const manualConnections = Array.isArray(editorPayload.manual_connections) ? editorPayload.manual_connections : [];
    const manualLines = Array.isArray(editorPayload.manual_lines) ? editorPayload.manual_lines : [];
    const manualShapes = Array.isArray(editorPayload.manual_shapes) ? editorPayload.manual_shapes : [];
    const annotations = Array.isArray(editorPayload.annotations) ? editorPayload.annotations : [];
    const storedPositions = editorPayload.positions || {};
    const storedRoutes = editorPayload.routes || {};
    const readOnly = root.dataset.editorReadOnly === 'true';
    const saveUrl = root.dataset.editorSaveUrl || '';
    const controllerUrl = root.dataset.editorControllerUrl || '';
    const closeUrl = root.dataset.editorCloseUrl || '';
    const container = root.querySelector('[data-editor-graph]');
    if (!container) return;
    root.dataset.editorReady = 'false';

    document.body.classList.add('nxsld-editor-mode');
    InternalEvent.disableContextMenu(container);

    const plugins = [...getDefaultPlugins()];
    const graph = new Graph(container, undefined, plugins);
    const model = graph.getDataModel();
    const undoManager = new UndoManager(120);
    const cellsByNodeId = new Map();
    const labelCellsByNodeId = new Map();
    const labelLeaderCellsByNodeId = new Map();
    const excludedNodes = new Set(Array.isArray(editorPayload.excluded_nodes) ? editorPayload.excluded_nodes.map(String) : []);
    let clipboard = [];
    let clipboardPasteSerial = 0;
    let suppressDirty = true;
    let dirty = false;
    let activeTool = 'select';
    let activeSpace = editorPayload.space === 'sheet' ? 'sheet' : 'model';
    let displayMode = ['clean', 'engineering', 'inspection'].includes(editorPayload.display_mode) ? editorPayload.display_mode : 'clean';
    let spaceDown = false;
    let panSession = null;
    let pendingWireSource = null;
    let pendingWireSourcePort = null;
    let smartWireSyncing = false;
    let derivedWireSyncing = false;
    let wireSyncScheduled = false;
    let dragCommitInProgress = false;
    let pointerFrameScheduled = false;
    let pendingPointerSample = null;
    let dragPreviewFrame = null;
    let dragPerformance = {
        version: '67M.1-HF2',
        mode: 'idle',
        pointer_events: 0,
        rendered_frames: 0,
        commits: 0,
        route_syncs: 0,
        selected_count: 0,
        duration_ms: 0,
    };
    let routeSyncSummary = { wires: 0, crossings: 0, conflicts: 0, separationConflicts: 0, bends: 0, length: 0, fallbacks: 0, terminalDirectionViolations: 0, orthogonalWires: 0 };
    let labelPlacementResult = { version: SLD_LABEL_PLACEMENT_VERSION, contract: SLD_LABEL_PLACEMENT_CONTRACT.name, engine: SLD_LABEL_PLACEMENT_CONTRACT.engine, placements: [], summary: { labels: 0, leaders: 0, fallbacks: 0, equipment_conflicts: 0, label_conflicts: 0, wire_conflicts: 0 } };
    let labelPlacementSyncing = false;
    let labelPlacementScheduled = false;
    let visualRegressionReport = { version: SLD_VISUAL_REGRESSION_VERSION, contract: SLD_VISUAL_REGRESSION_CONTRACT.name, engine: SLD_VISUAL_REGRESSION_CONTRACT.engine, fingerprint: '', status: 'pass', summary: { equipment: 0, routes: 0, labels: 0, issues: 0, errors: 0, warnings: 0, clipping: 0, overlaps: 0, non_orthogonal_routes: 0, wire_label_intersections: 0 }, issues: [] };
    let visualRegressionScheduled = false;
    let visualBaselineFingerprint = /^vr-[0-9a-f]{8}$/.test(String(editorPayload.visual_regression?.fingerprint || '')) ? String(editorPayload.visual_regression.fingerprint) : '';
    let serializedVisualFingerprint = visualBaselineFingerprint;
    let geometryDiagnosticReport = diagnoseGeometry([], { canvas: CANVAS, gridSize: GRID_SIZE });
    let draftSession = null;
    let pointerPoint = { x: 0, y: 0 };
    let pointerScreen = { x: 0, y: 0 };
    let activeSnap = null;
    let typedCommand = '';
    let typedCommandTimer = null;
    let dynamicPrimary = '';
    let dynamicSecondary = '';
    let dynamicField = 'primary';
    let placementKind = null;
    let circleMode = ['center-radius', 'center-diameter', 'two-point', 'three-point'].includes(editorPayload.tools?.circle_mode) ? editorPayload.tools.circle_mode : 'center-radius';
    let arcMode = ['three-point', 'center-start-end', 'start-center-end', 'start-end-radius'].includes(editorPayload.tools?.arc_mode) ? editorPayload.tools.arc_mode : 'three-point';
    let gridEnabled = editorPayload.viewport?.grid_enabled !== false;
    let snapEnabled = editorPayload.viewport?.snap_enabled !== false;
    let orthoEnabled = editorPayload.viewport?.ortho_enabled === true;
    let polarEnabled = editorPayload.viewport?.polar_enabled !== false;
    let osnapEnabled = editorPayload.viewport?.osnap_enabled !== false;
    let osnapModes = { ...OSNAP_DEFAULTS, ...(editorPayload.viewport?.osnap_modes || {}) };
    Object.keys(OSNAP_DEFAULTS).forEach((key) => { osnapModes[key] = osnapModes[key] !== false; });
    const osnapMigrationKey = `nexorin.sld.osnap.c3.${slug(payload.project?.number || 'drawing')}`;
    try {
        if (localStorage.getItem(osnapMigrationKey) !== 'ready') {
            osnapEnabled = true;
            localStorage.setItem(osnapMigrationKey, 'ready');
        }
    } catch (_error) { /* localStorage may be unavailable */ }
    let otrackEnabled = editorPayload.viewport?.otrack_enabled !== false;
    let dynamicEnabled = editorPayload.viewport?.dynamic_enabled !== false;
    let paperMode = ['as_drawn', 'auto', 'single', 'multi', 'catalog'].includes(editorPayload.paper?.mode) ? editorPayload.paper.mode : 'as_drawn';
    let directDraftPointer = null;
    let selectionDrag = null;
    let moveSelectionSession = null;
    let expandingGroupSelection = false;
    let suppressGraphClickUntil = 0;
    let activePaperPage = 0;
    let activeSymbolFilter = 'all';
    let electricalValidation = graphPayload.electrical_validation || null;
    let engineeringController = graphPayload.engineering_controller || null;
    let controllerBusy = false;
    let electricalCheckFilter = 'all';
    let electricalCheckTimer = null;
    const layerState = Object.fromEntries(Object.entries(LAYER_DEFAULTS).map(([name, defaults]) => {
        const stored = editorPayload.layers?.[name] || {};
        return [name, { ...defaults, ...stored }];
    }));

    const els = {
        save: root.querySelector('[data-editor-save]'),
        saveState: root.querySelector('[data-editor-save-state]'),
        nodeCount: root.querySelector('[data-editor-node-count]'),
        wireCount: root.querySelector('[data-editor-wire-count]'),
        lineCount: root.querySelector('[data-editor-line-count]'),
        geometryBadge: root.querySelector('[data-editor-geometry-badge]'),
        geometryVersion: root.querySelector('[data-editor-geometry-version]'),
        geometryIssueCount: root.querySelector('[data-editor-geometry-issue-count]'),
        labelPlacementBadge: root.querySelector('[data-editor-label-placement-badge]'),
        labelPlacementVersion: root.querySelector('[data-editor-label-placement-version]'),
        labelPlacementCount: root.querySelector('[data-editor-label-placement-count]'),
        visualRegressionBadge: root.querySelector('[data-editor-visual-regression-badge]'),
        visualRegressionVersion: root.querySelector('[data-editor-visual-regression-version]'),
        visualRegressionCount: root.querySelector('[data-editor-visual-regression-count]'),
        zoomLabel: root.querySelector('[data-editor-zoom-label]'),
        zoomRange: root.querySelector('[data-editor-zoom-range]'),
        floatingZoom: root.querySelector('[data-editor-floating-zoom]'),
        x: root.querySelector('[data-editor-coordinate-x]'),
        y: root.querySelector('[data-editor-coordinate-y]'),
        selectionStatus: root.querySelector('[data-editor-selection-status]'),
        inspectorEmpty: root.querySelector('[data-editor-inspector-empty]'),
        inspectorForm: root.querySelector('[data-editor-inspector-form]'),
        inspectorHeading: root.querySelector('[data-editor-inspector-heading]'),
        inspectorKind: root.querySelector('[data-editor-inspector-kind]'),
        inspectorAuthority: root.querySelector('[data-editor-inspector-authority]'),
        inspectorLayer: root.querySelector('[data-editor-inspector-layer]'),
        inspectorGroup: root.querySelector('[data-editor-inspector-group]'),
        inspectorStandard: root.querySelector('[data-editor-inspector-standard]'),
        inspectorPorts: root.querySelector('[data-editor-inspector-ports]'),
        inspectorId: root.querySelector('[data-editor-inspector-id]'),
        profileEyebrow: root.querySelector('[data-editor-profile-eyebrow]'),
        profileTitle: root.querySelector('[data-editor-profile-title]'),
        profileMeta: root.querySelector('[data-editor-profile-meta]'),
        profileState: root.querySelector('[data-editor-profile-state]'),
        transformStatus: root.querySelector('[data-editor-transform-status]'),
        deleteLabel: root.querySelector('[data-editor-delete-label]'),
        crosshair: root.querySelector('[data-editor-crosshair]'),
        hint: root.querySelector('[data-editor-canvas-hint]'),
        excludedList: root.querySelector('[data-editor-excluded-list]'),
        excludedCount: root.querySelector('[data-editor-excluded-count]'),
        spaceDescription: root.querySelector('[data-editor-space-description]'),
        closeDialog: root.querySelector('[data-editor-close-dialog]'),
        paperPreview: root.querySelector('[data-editor-paper-preview]'),
        paperPreviewNote: root.querySelector('[data-editor-paper-preview-note]'),
        paperPages: root.querySelector('[data-editor-paper-pages]'),
        paperNavigation: root.querySelector('[data-editor-paper-navigation]'),
        paperStatus: root.querySelector('[data-editor-paper-status]'),
        paperMessage: root.querySelector('[data-editor-paper-message]'),
        paperMode: root.querySelector('[data-editor-paper-mode]'),
        symbolTooltip: root.querySelector('[data-editor-symbol-tooltip]'),
        draftingOverlay: root.querySelector('[data-editor-drafting-overlay]'),
        geometryPreview: root.querySelector('[data-editor-geometry-preview]'),
        trackingGuides: root.querySelector('[data-editor-tracking-guides]'),
        dragPreview: root.querySelector('[data-editor-drag-preview]'),
        snapMarker: root.querySelector('[data-editor-snap-marker]'),
        selectedSnapPoints: root.querySelector('[data-editor-selected-snap-points]'),
        selectionWindow: root.querySelector('[data-editor-selection-window]'),
        resizeOverlay: root.querySelector('[data-editor-resize-overlay]'),
        dynamicInput: root.querySelector('[data-editor-dynamic-input]'),
        dynamicPrompt: root.querySelector('[data-editor-dynamic-prompt]'),
        dynamicPrimary: root.querySelector('[data-editor-dynamic-primary]'),
        dynamicSecondary: root.querySelector('[data-editor-dynamic-secondary]'),
        snapTooltip: root.querySelector('[data-editor-snap-tooltip]'),
        wireToast: root.querySelector('[data-editor-wire-toast]'),
        commandName: root.querySelector('[data-editor-command-name]'),
        commandPrompt: root.querySelector('[data-editor-command-prompt]'),
        selectionCount: root.querySelector('[data-editor-selection-count]'),
        osnapMenu: root.querySelector('[data-editor-osnap-menu]'),
        osnapSummary: root.querySelector('[data-editor-osnap-summary]'),
        wirePanel: root.querySelector('[data-editor-wire-panel]'),
        wireSource: root.querySelector('[data-editor-wire-source]'),
        wireTarget: root.querySelector('[data-editor-wire-target]'),
        wireNetworkBadge: root.querySelector('[data-editor-wire-network-badge]'),
        wireRouteState: root.querySelector('[data-editor-wire-route-state]'),
        wireRouteStrategy: root.querySelector('[data-editor-wire-route-strategy]'),
        wireRouteClearance: root.querySelector('[data-editor-wire-route-clearance]'),
        wireRouteBends: root.querySelector('[data-editor-wire-route-bends]'),
        wireRouteCrossings: root.querySelector('[data-editor-wire-route-crossings]'),
        wireRouteLength: root.querySelector('[data-editor-wire-route-length]'),
        routingRibbonCount: root.querySelector('[data-editor-routing-ribbon-count]'),
        routingVersion: root.querySelector('[data-editor-routing-version]'),
        nonWireSections: [...root.querySelectorAll('[data-editor-non-wire-section]')],
        authorityHelp: root.querySelector('[data-editor-authority-help]'),
        checkStatus: root.querySelector('[data-editor-check-status]'),
        checkStatusLabel: root.querySelector('[data-editor-check-status-label]'),
        checkStatusCount: root.querySelector('[data-editor-check-status-count]'),
        checkRibbonCount: root.querySelector('[data-editor-check-ribbon-count]'),
        checkTabCount: root.querySelector('[data-editor-check-tab-count]'),
        checkSummary: root.querySelector('[data-editor-check-summary]'),
        checkHeading: root.querySelector('[data-editor-check-heading]'),
        checkMessage: root.querySelector('[data-editor-check-message]'),
        checkList: root.querySelector('[data-editor-check-list]'),
        controllerStatus: root.querySelector('[data-editor-controller-status]'),
        controllerStatusLabel: root.querySelector('[data-editor-controller-status-label]'),
        controllerStatusCount: root.querySelector('[data-editor-controller-status-count]'),
        controllerTabCount: root.querySelector('[data-editor-controller-tab-count]'),
        controllerHero: root.querySelector('[data-editor-controller-hero]'),
        controllerHeading: root.querySelector('[data-editor-controller-heading]'),
        controllerMessage: root.querySelector('[data-editor-controller-message]'),
        controllerSources: root.querySelector('[data-editor-controller-sources]'),
        controllerImpacts: root.querySelector('[data-editor-controller-impacts]'),
        controllerConflicts: root.querySelector('[data-editor-controller-conflicts]'),
        controllerCables: root.querySelector('[data-editor-controller-cables]'),
        controllerProtection: root.querySelector('[data-editor-controller-protection]'),
        controllerSync: root.querySelector('[data-editor-controller-sync]'),
        controllerAck: root.querySelector('[data-editor-controller-ack]'),
        controllerReason: root.querySelector('[data-editor-controller-reason]'),
    };

    const fields = {};
    const fieldLabels = {};
    root.querySelectorAll('[data-editor-field]').forEach((field) => {
        fields[field.dataset.editorField] = field;
    });
    root.querySelectorAll('[data-editor-field-label]').forEach((label) => {
        fieldLabels[label.dataset.editorFieldLabel] = label;
    });
    const wireFields = {};
    root.querySelectorAll('[data-editor-wire-field]').forEach((field) => {
        wireFields[field.dataset.editorWireField] = field;
    });

    // APP66B.1 uses an explicit CAD-style pan controller.
    // The stock maxGraph panner is disabled to avoid conflicts with rubber-band selection.
    graph.setPanning(false);
    graph.setConnectable(!readOnly);
    graph.setAllowDanglingEdges(false);
    graph.setDisconnectOnMove(false);
    graph.setGridEnabled(snapEnabled);
    graph.setGridSize(GRID_SIZE);
    graph.setCellsResizable(false);
    graph.setCellsMovable(!readOnly);
    graph.setCellsEditable(false);
    graph.setCellsCloneable(!readOnly);
    graph.setDropEnabled(!readOnly);
    graph.setTooltips(true);
    graph.centerZoom = true;
    graph.keepSelectionVisibleOnZoom = true;
    graph.border = 52;

    const panningHandler = graph.getPlugin('PanningHandler');
    if (panningHandler) {
        panningHandler.setPanningEnabled?.(false);
        panningHandler.usePopupTrigger = false;
        panningHandler.useLeftButtonForPanning = false;
    }

    const connectionHandler = graph.getPlugin('ConnectionHandler');
    if (connectionHandler) {
        connectionHandler.createTarget = false;
        connectionHandler.select = true;
        connectionHandler.movePreviewAway = false;
    }

    graph.isHtmlLabel = () => true;
    graph.getLabel = (cell) => {
        const value = cell?.getValue?.();
        if (!value || typeof value !== 'object') return '';
        if (cell.isEdge?.()) return isDraftingLineValue(value) || value.entity === 'label_leader' ? '' : edgeMarkup(value);
        if (value.entity === 'derived_label') return derivedLabelMarkup(value);
        if (value.entity === 'annotation') return noteMarkup(value);
        if (value.entity === 'shape') return geometryMarkup(value);
        return nodeMarkup(value);
    };

    const tooltipHandler = graph.getPlugin('TooltipHandler');
    if (tooltipHandler) {
        tooltipHandler.getTooltipForCell = (cell) => {
            const value = cell?.getValue?.();
            if (!value || typeof value !== 'object') return '';
            if (cell.isEdge?.()) return isDraftingLineValue(value)
                ? `${value.label || (value.entity === 'leader' ? 'Leader' : 'Drafting line')} · ${value.line_type || 'continuous'} · ${value.lineweight || 1.4}`
                : `${value.wire_id || value.cable_tag || value.label || 'Wire'} · ${wireNetworkDefinition(value.network).label}${wireCableDetail(value) ? ` · ${wireCableDetail(value)}` : ''}`;
            return `${value.title || value.text || 'Electrical object'}${value.rating ? ` · ${value.rating}` : ''}`;
        };
    }

    graph.isCellEditable = () => false;
    graph.isCellSelectable = (cell) => !['derived_label', 'label_leader'].includes(cell?.getValue?.()?.entity);
    graph.isCellDeletable = (cell) => !readOnly && cell?.getValue?.()?.locked !== true && !layerState[objectLayer(cell?.getValue?.() || {})]?.locked && (cell?.getValue?.()?.manual === true || ['annotation', 'shape'].includes(cell?.getValue?.()?.entity));
    graph.isCellResizable = (cell) => {
        const value = cell?.getValue?.() || {};
        if (readOnly || !cell?.isVertex?.() || ['derived_label'].includes(value.entity) || value.locked === true || layerState[objectLayer(value)]?.locked) return false;
        // Generated equipment is allowed to change drafting geometry without
        // changing calculation-controlled electrical values.
        return value.entity === 'node' || value.manual === true || ['annotation', 'shape'].includes(value.entity);
    };
    graph.isCellMovable = (cell) => {
        if (readOnly || activeTool === 'pan' || spaceDown) return false;
        const value = cell?.getValue?.();
        if (['derived_label', 'label_leader'].includes(value?.entity)) return false;
        if (value?.locked === true || layerState[objectLayer(value || {})]?.locked) return false;
        if (cell?.isVertex?.()) return true;
        return cell?.isEdge?.() && isDraftingLineValue(value) && activeTool === 'select';
    };
    graph.isCellDisconnectable = (cell) => !readOnly && cell?.getValue?.()?.entity === 'edge' && cell?.getValue?.()?.manual === true;
    graph.isValidConnection = (source, target) => {
        if (readOnly || !source || !target || source === target) return false;
        const sourceValue = source.getValue?.() || {};
        const targetValue = target.getValue?.() || {};
        if (sourceValue.entity !== 'node' || targetValue.entity !== 'node') return false;
        return compatiblePortPair(sourceValue, targetValue) !== null;
    };

    const originalCreateEdge = graph.createEdge.bind(graph);
    graph.createEdge = (parent, id, value, source, target, style) => {
        if (value && typeof value === 'object' && value.entity === 'edge') {
            return originalCreateEdge(parent, id, value, source, target, style);
        }
        const sourceValue = source?.getValue?.() || {};
        const targetValue = target?.getValue?.() || {};
        const pair = compatiblePortPair(sourceValue, targetValue);
        const network = normalizeWireNetwork(pair?.network);
        const definition = wireNetworkDefinition(network);
        const wireId = nextWireId(network);
        const wire = {
            entity: 'edge',
            id: uid('wire'),
            wire_id: wireId,
            manual: true,
            label: definition.label,
            detail: 'Cable data not specified',
            cable_tag: '',
            cable_type: '',
            cable_size: '',
            cores: '',
            voltage_class: '',
            installation_method: '',
            notes: '',
            show_label: true,
            routing_mode: 'auto',
            routing_clearance: 'standard',
            router_version: SLD_SMART_ROUTER_VERSION,
            from_port: pair?.sourcePort?.id || null,
            to_port: pair?.targetPort?.id || null,
            network,
        };
        return originalCreateEdge(parent, wire.id, wire, source, target, style || manualEdgeStyle(sourceValue.kind, targetValue.kind, true, wire.network));
    };

    const undoListener = (_sender, event) => {
        if (suppressDirty || derivedWireSyncing || labelPlacementSyncing) return;
        undoManager.undoableEditHappened(event.getProperty('edit'));
        markDirty();
    };
    model.addListener(InternalEvent.UNDO, undoListener);
    graph.getView().addListener(InternalEvent.UNDO, undoListener);
    undoManager.addListener(InternalEvent.UNDO, () => { markDirty(); scheduleSmartWireSync(); refreshUi(); });
    undoManager.addListener(InternalEvent.REDO, () => { markDirty(); scheduleSmartWireSync(); refreshUi(); });
    model.addListener(InternalEvent.CHANGE, () => {
        if (suppressDirty || smartWireSyncing || labelPlacementSyncing || dragCommitInProgress) return;
        scheduleSmartWireSync();
        scheduleElectricalCheck();
    });

    function markSaveState(type, text) {
        if (!els.saveState) return;
        els.saveState.className = `nxsld-save-state is-${type}`;
        els.saveState.textContent = text;
    }

    function markDirty() {
        if (suppressDirty || readOnly) return;
        dirty = true;
        markSaveState('dirty', 'Unsaved changes');
        if (els.paperPreviewNote) els.paperPreviewNote.textContent = 'Save drawing to refresh A3 composition';
    }

    function sourceTag(node) {
        return node.tag || SOURCE_TAGS[node.id] || '';
    }

    function createNodeCell(node, position, manual = false) {
        const symbolId = node.symbol || (SYMBOL_DEFINITIONS[node.id] ? node.id : (node.kind || node.id));
        const registry = registrySymbol(symbolId);
        const value = {
            ...node,
            entity: 'node',
            id: String(node.id),
            tag: manual ? (node.tag || '') : (position.tag_override ?? sourceTag(node)),
            title: node.title || node.label || node.name || 'Electrical component',
            subtitle: node.subtitle || 'Electrical component',
            rating: node.rating || 'Rating not specified',
            kind: node.kind || SYMBOL_DEFINITIONS[node.symbol]?.kind || 'component',
            state: manual ? 'manual' : (node.state || 'ready'),
            symbol: symbolId,
            ports: portProfile(symbolId, node.ports),
            symbol_standard: node.symbol_standard || registry?.standard || null,
            symbol_registry_version: node.symbol_registry_version || SYMBOL_REGISTRY.version || '67L.1',
            manual,
            locked: Boolean(node.locked ?? position.locked),
            rotation: normalizeQuarterTurn(node.rotation ?? position.rotation),
            flip_h: Boolean(node.flip_h ?? position.flip_h),
            flip_v: Boolean(node.flip_v ?? position.flip_v),
            group_id: node.group_id || position.group_id || null,
            show_tag: (node.show_tag ?? position.show_tag) !== false,
            show_rating: (node.show_rating ?? position.show_rating) !== false,
            show_subtitle: (node.show_subtitle ?? position.show_subtitle) !== false,
            show_ports: (node.show_ports ?? position.show_ports) !== false,
        };
        const cell = graph.insertVertex({
            parent: graph.getDefaultParent(),
            id: `node:${slug(value.id)}`,
            value,
            position: [position.x, position.y],
            size: [position.w, position.h],
            style: vertexStyle(value),
        });
        cellsByNodeId.set(value.id, cell);
        return cell;
    }

    function createAnnotationCell(annotation, index) {
        const value = {
            entity: 'annotation',
            id: annotation.id || uid('note'),
            text: annotation.text || 'Drawing note',
            manual: true,
            locked: annotation.locked === true,
            rotation: normalizeQuarterTurn(annotation.rotation),
            flip_h: annotation.flip_h === true,
            flip_v: annotation.flip_v === true,
            group_id: annotation.group_id || null,
        };
        return graph.insertVertex({
            parent: graph.getDefaultParent(),
            id: `note:${slug(value.id)}`,
            value,
            position: [finite(annotation.x, 150 + index * 30), finite(annotation.y, 810 + index * 30)],
            size: [finite(annotation.w, 300), finite(annotation.h, 64)],
            style: vertexStyle(value),
        });
    }

    function applyRoute(edge, route) {
        if (!edge || !Array.isArray(route) || route.length === 0) return;
        const geometry = edge.getGeometry()?.clone() || new Geometry();
        geometry.relative = true;
        geometry.points = route
            .filter((point) => point && Number.isFinite(Number(point.x)) && Number.isFinite(Number(point.y)))
            .map((point) => new Point(Number(point.x), Number(point.y)));
        model.setGeometry(edge, geometry);
    }

    function wireNetworksCompatible(a, b) {
        const source = normalizeWireNetwork(a);
        const target = normalizeWireNetwork(b);
        if (source === target) return true;
        const dc = new Set(['dc_power', 'dc_positive', 'dc_negative']);
        const battery = new Set(['battery_dc', 'battery_positive', 'battery_negative']);
        if (dc.has(source) && dc.has(target)) {
            if ((source === 'dc_positive' && target === 'dc_negative') || (source === 'dc_negative' && target === 'dc_positive')) return false;
            return source === 'dc_power' || target === 'dc_power';
        }
        if (battery.has(source) && battery.has(target)) {
            if ((source === 'battery_positive' && target === 'battery_negative') || (source === 'battery_negative' && target === 'battery_positive')) return false;
            return source === 'battery_dc' || target === 'battery_dc';
        }
        return false;
    }

    function updateWirePortPresentation() {
        const wireActive = activeTool === 'wire' && activeSpace === 'model';
        root.dataset.wirePhase = wireActive ? (pendingWireSource ? 'target' : 'source') : 'idle';
        const sourceNetwork = normalizeWireNetwork(pendingWireSourcePort?.network || '');
        root.dataset.wireSourceNetwork = pendingWireSource ? sourceNetwork : '';
        root.querySelectorAll('[data-nxsld-port-id]').forEach((portElement) => {
            const network = normalizeWireNetwork(portElement.dataset.nxsldPortNetwork || '');
            const direction = portElement.dataset.nxsldPortDirection || 'bidirectional';
            const nodeElement = portElement.closest('[data-nxsld-node-id]');
            const sourceNode = pendingWireSource ? String(cellValue(pendingWireSource)?.id || '') : '';
            const isSourcePort = Boolean(pendingWireSourcePort)
                && nodeElement?.dataset.nxsldNodeId === sourceNode
                && portElement.dataset.nxsldPortId === pendingWireSourcePort.id;
            const canReceive = direction === 'input' || direction === 'bidirectional';
            const compatible = Boolean(pendingWireSource) && canReceive && wireNetworksCompatible(sourceNetwork, network) && !isSourcePort;
            portElement.classList.toggle('is-wire-source-port', isSourcePort);
            portElement.classList.toggle('is-wire-compatible', compatible);
            portElement.classList.toggle('is-wire-incompatible', Boolean(pendingWireSource) && !compatible && !isSourcePort);
        });
    }

    let wireToastTimer = null;
    function showWireToast(title, message, tone = 'error') {
        if (!els.wireToast) return;
        window.clearTimeout(wireToastTimer);
        els.wireToast.className = `nxsld-wire-toast is-${tone}`;
        els.wireToast.innerHTML = `<i></i><span><strong>${escapeHtml(title)}</strong><small>${escapeHtml(message)}</small></span>`;
        els.wireToast.hidden = false;
        requestAnimationFrame(() => els.wireToast.classList.add('is-visible'));
        wireToastTimer = window.setTimeout(() => {
            els.wireToast.classList.remove('is-visible');
            window.setTimeout(() => { els.wireToast.hidden = true; }, 180);
        }, tone === 'error' ? 3600 : 2300);
    }

    function portDescriptorForCell(cell, preferredPortId = null, toward = null) {
        if (!cell) return null;
        const value = cellValue(cell) || {};
        const geometry = cell.getGeometry?.();
        if (!geometry) return null;
        const candidates = portCoordinates(value, geometry);
        let point = preferredPortId ? candidates.find((candidate) => candidate.port?.id === preferredPortId) : null;
        if (!point && toward && candidates.length > 0) {
            point = [...candidates].sort((a, b) => distance(a, toward) - distance(b, toward))[0];
        }
        point ||= candidates[0] || null;
        if (!point) return null;
        const side = transformedSide(point.port?.side || 'right', value);
        return {
            ...point,
            side,
            relativeX: clamp((point.x - geometry.x) / Math.max(geometry.width, 0.001), 0, 1),
            relativeY: clamp((point.y - geometry.y) / Math.max(geometry.height, 0.001), 0, 1),
            cell,
            value,
            geometry,
        };
    }

    function clickedPortDescriptor(cell, point, capturePixels = 22) {
        if (!cell || !point) return null;
        const descriptor = portDescriptorForCell(cell, null, point);
        if (!descriptor) return null;
        const capture = capturePixels / Math.max(graph.getView().scale, 0.01);
        return distance(descriptor, point) <= capture ? descriptor : null;
    }

    function wireTerminalDescriptor(edge, sourceTerminal = true) {
        const value = cellValue(edge) || {};
        const cell = edge?.getTerminal?.(sourceTerminal);
        const opposite = edge?.getTerminal?.(!sourceTerminal);
        const oppositeGeometry = opposite?.getGeometry?.();
        const toward = oppositeGeometry ? {
            x: oppositeGeometry.x + oppositeGeometry.width / 2,
            y: oppositeGeometry.y + oppositeGeometry.height / 2,
        } : null;
        const portId = sourceTerminal ? value.from_port : value.to_port;
        return portDescriptorForCell(cell, portId, toward);
    }

    function wireStyleForEdge(edge) {
        const value = cellValue(edge) || {};
        const source = edge?.getTerminal?.(true);
        const target = edge?.getTerminal?.(false);
        const sourceValue = cellValue(source) || {};
        const targetValue = cellValue(target) || {};
        const style = value.manual === true
            ? manualEdgeStyle(sourceValue.kind, targetValue.kind, true, value.network)
            : edgeStyle(false, sourceValue.kind, targetValue.kind, value.network);
        const sourcePort = wireTerminalDescriptor(edge, true);
        const targetPort = wireTerminalDescriptor(edge, false);
        if (sourcePort) {
            style.exitX = Number(sourcePort.relativeX.toFixed(5));
            style.exitY = Number(sourcePort.relativeY.toFixed(5));
            style.exitPerimeter = false;
        }
        if (targetPort) {
            style.entryX = Number(targetPort.relativeX.toFixed(5));
            style.entryY = Number(targetPort.relativeY.toFixed(5));
            style.entryPerimeter = false;
        }
        return style;
    }

    function sideVector(side = 'right') {
        return {
            left: { x: -1, y: 0 },
            right: { x: 1, y: 0 },
            top: { x: 0, y: -1 },
            bottom: { x: 0, y: 1 },
        }[side] || { x: 1, y: 0 };
    }

    function routePointOutsidePort(port, distanceValue = 30) {
        const vector = sideVector(port?.side);
        return {
            x: graph.snap(port.x + vector.x * distanceValue),
            y: graph.snap(port.y + vector.y * distanceValue),
        };
    }

    function pointInsideExpandedGeometry(point, descriptor, margin = 12) {
        if (!point || !descriptor?.geometry) return false;
        const geometry = descriptor.geometry;
        return point.x > geometry.x - margin
            && point.x < geometry.x + geometry.width + margin
            && point.y > geometry.y - margin
            && point.y < geometry.y + geometry.height + margin;
    }

    function routeLength(points) {
        let total = 0;
        for (let index = 0; index < points.length - 1; index += 1) total += distance(points[index], points[index + 1]);
        return total;
    }

    function compressOrthogonalRoute(points) {
        const clean = [];
        points.forEach((point) => {
            if (!point || !Number.isFinite(point.x) || !Number.isFinite(point.y)) return;
            const normalized = { x: Number(point.x.toFixed(4)), y: Number(point.y.toFixed(4)) };
            const previous = clean.at(-1);
            if (previous && Math.abs(previous.x - normalized.x) < 0.01 && Math.abs(previous.y - normalized.y) < 0.01) return;
            clean.push(normalized);
        });
        let changed = true;
        while (changed && clean.length > 2) {
            changed = false;
            for (let index = 1; index < clean.length - 1; index += 1) {
                const a = clean[index - 1];
                const b = clean[index];
                const c = clean[index + 1];
                const collinearX = Math.abs(a.x - b.x) < 0.01 && Math.abs(b.x - c.x) < 0.01;
                const collinearY = Math.abs(a.y - b.y) < 0.01 && Math.abs(b.y - c.y) < 0.01;
                if (collinearX || collinearY) {
                    clean.splice(index, 1);
                    changed = true;
                    break;
                }
            }
        }
        return clean;
    }

    function routingProfile(value = {}) {
        const key = Object.hasOwn(ROUTING_CLEARANCE_PROFILES, value.routing_clearance) ? value.routing_clearance : 'standard';
        return { key, ...ROUTING_CLEARANCE_PROFILES[key] };
    }

    function routingObstacleRects(edge = null) {
        const source = edge?.getTerminal?.(true) || null;
        const target = edge?.getTerminal?.(false) || null;
        return (graph.getDefaultParent()?.getChildren?.() || []).flatMap((cell) => {
            if (!cell?.isVertex?.() || cell.isVisible?.() === false) return [];
            const value = cellValue(cell) || {};
            const geometry = cell.getGeometry?.();
            if (!geometry) return [];
            const isEquipment = value.entity === 'node';
            const isAnnotationObstacle = value.entity === 'annotation';
            const isKeepout = value.entity === 'shape' && value.routing_keepout === true;
            if (!isEquipment && !isAnnotationObstacle && !isKeepout) return [];
            return [{
                id: String(value.id || cell.getId?.() || ''),
                left: geometry.x,
                top: geometry.y,
                right: geometry.x + geometry.width,
                bottom: geometry.y + geometry.height,
                kind: isEquipment ? 'equipment' : (isKeepout ? 'keepout' : 'annotation'),
                terminal: cell === source || cell === target,
            }];
        });
    }

    function edgeFullRoutePoints(edge) {
        const source = wireTerminalDescriptor(edge, true);
        const target = wireTerminalDescriptor(edge, false);
        if (!source || !target) return [];
        const geometry = edge.getGeometry?.();
        const points = Array.isArray(geometry?.points) ? geometry.points.map((point) => ({ x: point.x, y: point.y })) : [];
        return compressOrthogonalRoute([{ x: source.x, y: source.y }, ...points, { x: target.x, y: target.y }]);
    }

    function existingWireSegments(excludedEdge = null, excludedEdges = null) {
        const excluded = excludedEdges instanceof Set ? excludedEdges : null;
        return (graph.getDefaultParent()?.getChildren?.() || []).flatMap((cell) => {
            if (cell === excludedEdge || excluded?.has(cell) || !cell?.isEdge?.() || cell.isVisible?.() === false) return [];
            const value = cellValue(cell) || {};
            if (value.entity !== 'edge') return [];
            return segmentsFromRoute(edgeFullRoutePoints(cell), normalizeWireNetwork(value.network), String(value.id || cell.getId?.() || ''));
        });
    }

    function labelPlacementItems() {
        return (graph.getDefaultParent()?.getChildren?.() || []).flatMap((cell) => {
            const value = cellValue(cell) || {};
            const geometry = cell.getGeometry?.();
            if (!cell?.isVertex?.() || cell.isVisible?.() === false || value.entity !== 'node' || !geometry) return [];
            return [{
                id: String(value.id || cell.getId?.() || ''),
                x: geometry.x, y: geometry.y, w: geometry.width, h: geometry.height,
                title: value.title || 'Electrical component', tag: value.tag || '', rating: value.rating || '', subtitle: value.subtitle || '',
                show_tag: value.show_tag !== false, show_rating: value.show_rating !== false, show_subtitle: value.show_subtitle !== false,
                rotation: normalizeQuarterTurn(value.rotation), flip_h: value.flip_h === true, flip_v: value.flip_v === true,
                ports: portProfile(value.symbol || value.kind, value.ports), manual: value.manual === true,
            }];
        });
    }

    function removeDerivedLabelCell(map, id) {
        const cell = map.get(id);
        if (!cell) return;
        model.remove(cell);
        map.delete(id);
    }

    function createOrUpdateDerivedLabel(placement, item) {
        const id = String(placement.id);
        const value = {
            entity: 'derived_label', id: `label_${id}`, node_id: id,
            title: item.title || 'Electrical component', tag: item.tag || '', rating: item.rating || '', subtitle: item.subtitle || '',
            show_tag: item.show_tag !== false, show_rating: item.show_rating !== false, show_subtitle: item.show_subtitle !== false,
            side: placement.side, leader: placement.leader === true, fallback: placement.fallback === true,
            signature: placement.signature || '', equipment_conflicts: placement.equipment_conflicts || 0,
            label_conflicts: placement.label_conflicts || 0, wire_conflicts: placement.wire_conflicts || 0,
            manual: false, locked: true, layer: 'E-ANNOTATION',
        };
        let labelCell = labelCellsByNodeId.get(id);
        const geometry = new Geometry(placement.x, placement.y, placement.w, placement.h);
        if (!labelCell) {
            labelCell = graph.insertVertex({ parent: graph.getDefaultParent(), id: `auto-label:${slug(id)}`, value, position: [placement.x, placement.y], size: [placement.w, placement.h], style: labelVertexStyle() });
            labelCell.setConnectable(false);
            labelCellsByNodeId.set(id, labelCell);
        } else {
            model.setValue(labelCell, value);
            model.setGeometry(labelCell, geometry);
            model.setVisible(labelCell, true);
        }

        if (placement.leader) {
            let leaderCell = labelLeaderCellsByNodeId.get(id);
            const leaderValue = { entity: 'label_leader', id: `label_leader_${id}`, node_id: id, manual: false, locked: true, layer: 'E-ANNOTATION', signature: placement.signature || '' };
            const leaderGeometry = new Geometry();
            leaderGeometry.relative = true;
            leaderGeometry.setTerminalPoint(new Point(placement.anchor.x, placement.anchor.y), true);
            leaderGeometry.setTerminalPoint(new Point(placement.label_anchor.x, placement.label_anchor.y), false);
            if (!leaderCell) {
                leaderCell = new Cell(leaderValue, leaderGeometry, labelLeaderStyle());
                leaderCell.setId(`auto-label-leader:${slug(id)}`);
                leaderCell.setEdge(true);
                leaderCell.setConnectable(false);
                graph.addCell(leaderCell);
                labelLeaderCellsByNodeId.set(id, leaderCell);
            } else {
                model.setValue(leaderCell, leaderValue);
                model.setGeometry(leaderCell, leaderGeometry);
                model.setVisible(leaderCell, true);
            }
        } else {
            removeDerivedLabelCell(labelLeaderCellsByNodeId, id);
        }
    }

    function updateLabelPlacementStatus() {
        const summary = labelPlacementResult.summary || {};
        const conflicts = Number(summary.equipment_conflicts || 0) + Number(summary.label_conflicts || 0) + Number(summary.wire_conflicts || 0);
        root.dataset.labelPlacementVersion = SLD_LABEL_PLACEMENT_VERSION;
        root.dataset.labelPlacementContract = SLD_LABEL_PLACEMENT_CONTRACT.name;
        root.dataset.labelPlacementStatus = conflicts > 0 ? 'conflict' : 'clear';
        root.dataset.labelPlacementConflicts = String(conflicts);
        root.dataset.labelPlacementLeaders = String(summary.leaders || 0);
        if (els.labelPlacementVersion) els.labelPlacementVersion.textContent = `LABEL ${SLD_LABEL_PLACEMENT_VERSION}`;
        if (els.labelPlacementCount) els.labelPlacementCount.textContent = conflicts > 0 ? `${conflicts} CONFLICT${conflicts === 1 ? '' : 'S'}` : 'CLEAR';
        if (els.labelPlacementBadge) {
            els.labelPlacementBadge.classList.toggle('is-label-warning', conflicts > 0);
            els.labelPlacementBadge.title = conflicts > 0
                ? `${conflicts} automatic label conflict${conflicts === 1 ? '' : 's'} · ${summary.leaders || 0} leader${Number(summary.leaders || 0) === 1 ? '' : 's'}`
                : `APP${SLD_LABEL_PLACEMENT_VERSION} automatic label placement · ${summary.labels || 0} labels · ${summary.leaders || 0} leaders`;
        }
    }

    function rebuildAutomaticLabels() {
        if (labelPlacementSyncing) return labelPlacementResult;
        labelPlacementSyncing = true;
        try {
            const items = labelPlacementItems();
            const itemById = new Map(items.map((item) => [String(item.id), item]));
            labelPlacementResult = placeEngineeringLabels(items, existingWireSegments(), {
                canvas: { x: 0, y: 0, w: CANVAS.width, h: CANVAS.height },
                clearance: SLD_LABEL_PLACEMENT_CONTRACT.clearance,
                wireClearance: SLD_LABEL_PLACEMENT_CONTRACT.wireClearance,
                equipmentGap: SLD_LABEL_PLACEMENT_CONTRACT.equipmentGap,
            });
            const active = new Set(labelPlacementResult.placements.map((placement) => String(placement.id)));
            graph.batchUpdate(() => {
                [...labelCellsByNodeId.keys()].filter((id) => !active.has(id)).forEach((id) => removeDerivedLabelCell(labelCellsByNodeId, id));
                [...labelLeaderCellsByNodeId.keys()].filter((id) => !active.has(id)).forEach((id) => removeDerivedLabelCell(labelLeaderCellsByNodeId, id));
                labelPlacementResult.placements.forEach((placement) => createOrUpdateDerivedLabel(placement, itemById.get(String(placement.id)) || {}));
            });
            updateLabelPlacementStatus();
            scheduleVisualRegression();
            return labelPlacementResult;
        } finally {
            labelPlacementSyncing = false;
        }
    }

    function scheduleAutomaticLabelPlacement() {
        if (labelPlacementSyncing || suppressDirty || labelPlacementScheduled || dragCommitInProgress) return;
        labelPlacementScheduled = true;
        window.requestAnimationFrame(() => {
            labelPlacementScheduled = false;
            rebuildAutomaticLabels();
            graph.refresh();
        });
    }

    function visualRegressionScene() {
        const routes = (graph.getDefaultParent()?.getChildren?.() || []).flatMap((cell) => {
            const value = cellValue(cell) || {};
            if (!cell?.isEdge?.() || cell.isVisible?.() === false || value.entity !== 'edge') return [];
            return [{
                id: String(value.id || cell.getId?.() || ''),
                points: edgeFullRoutePoints(cell),
                signature: String(value.route_signature || ''),
            }];
        });
        return {
            equipment: geometryDiagnosticItems(),
            routes,
            labels: labelPlacementResult.placements.map((placement) => ({
                id: String(placement.id), x: placement.x, y: placement.y, w: placement.w, h: placement.h,
                signature: placement.signature || '',
            })),
            canvas: { x: 0, y: 0, w: CANVAS.width, h: CANVAS.height },
        };
    }

    function visualSnapshotInput() {
        const positions = {};
        const routes = {};
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        children.forEach((cell) => {
            const value = cellValue(cell) || {};
            const geometry = cell.getGeometry?.();
            if (!geometry) return;
            if (cell.isVertex?.() && value.entity === 'node' && value.id) {
                positions[String(value.id)] = {
                    x: Math.round(geometry.x),
                    y: Math.round(geometry.y),
                    w: Math.round(geometry.width),
                    h: Math.round(geometry.height),
                    rotation: normalizeQuarterTurn(value.rotation),
                    flip_h: value.flip_h === true,
                    flip_v: value.flip_v === true,
                };
            } else if (cell.isEdge?.() && value.entity === 'edge' && value.id && Array.isArray(geometry.points) && geometry.points.length > 0) {
                routes[String(value.id)] = geometry.points.map((point) => ({ x: Math.round(point.x), y: Math.round(point.y) }));
            }
        });
        const labelPlacement = {
            positions: Object.fromEntries(labelPlacementResult.placements.map((placement) => [String(placement.id), {
                side: placement.side,
                x: Math.round(placement.x),
                y: Math.round(placement.y),
                w: Math.round(placement.w),
                h: Math.round(placement.h),
                leader: placement.leader === true,
                signature: placement.signature || '',
            }])),
        };
        return { positions, routes, labelPlacement, canvas: { x: 0, y: 0, w: CANVAS.width, h: CANVAS.height } };
    }

    function updateVisualRegressionStatus() {
        visualRegressionReport = auditVisualScene(visualRegressionScene());
        // Lock the user-facing fingerprint to the same canonical persisted scene
        // used by PHP and controlled exports. The audit still uses full terminal
        // routes to detect visual-integrity issues.
        visualRegressionReport.fingerprint = createVisualSnapshot(visualSnapshotInput()).fingerprint;
        const summary = visualRegressionReport.summary || {};
        const issues = Number(summary.issues || 0);
        const errors = Number(summary.errors || 0);
        root.dataset.visualRegressionVersion = SLD_VISUAL_REGRESSION_VERSION;
        root.dataset.visualRegressionContract = SLD_VISUAL_REGRESSION_CONTRACT.name;
        root.dataset.visualRegressionStatus = visualRegressionReport.status || 'pass';
        root.dataset.visualRegressionIssues = String(issues);
        root.dataset.visualRegressionFingerprint = visualRegressionReport.fingerprint || '';
        if (els.visualRegressionVersion) els.visualRegressionVersion.textContent = `VISUAL ${SLD_VISUAL_REGRESSION_VERSION}`;
        if (els.visualRegressionCount) els.visualRegressionCount.textContent = issues > 0 ? `${issues} ISSUE${issues === 1 ? '' : 'S'}` : 'LOCKED';
        if (els.visualRegressionBadge) {
            els.visualRegressionBadge.classList.toggle('is-visual-warning', issues > 0 && errors === 0);
            els.visualRegressionBadge.classList.toggle('is-visual-error', errors > 0);
            els.visualRegressionBadge.title = issues > 0
                ? `${issues} visual-integrity issue${issues === 1 ? '' : 's'} · ${summary.overlaps || 0} overlap · ${summary.clipping || 0} clipping · ${summary.non_orthogonal_routes || 0} non-orthogonal route`
                : `APP${SLD_VISUAL_REGRESSION_VERSION} deterministic visual fingerprint · ${visualRegressionReport.fingerprint || 'pending'}`;
        }
        return visualRegressionReport;
    }

    function scheduleVisualRegression() {
        if (visualRegressionScheduled || dragCommitInProgress || labelPlacementSyncing || smartWireSyncing) return;
        visualRegressionScheduled = true;
        window.requestAnimationFrame(() => {
            visualRegressionScheduled = false;
            updateVisualRegressionStatus();
        });
    }

    function smartRouteRequestForEdge(edge, valueOverride = null) {
        const value = valueOverride || cellValue(edge) || {};
        const source = wireTerminalDescriptor(edge, true);
        const target = wireTerminalDescriptor(edge, false);
        if (!source || !target) return null;
        const profile = routingProfile(value);
        return {
            id: String(value.id || edge.getId?.() || ''),
            source: { x: source.x, y: source.y, side: source.side, id: String(source.value?.id || source.cell?.getValue?.()?.id || '') },
            target: { x: target.x, y: target.y, side: target.side, id: String(target.value?.id || target.cell?.getValue?.()?.id || '') },
            obstacles: routingObstacleRects(edge),
            network: normalizeWireNetwork(value.network),
            gridSize: Math.max(5, Math.min(GRID_SIZE, 10)),
            clearance: profile.clearance,
            channelSpacing: profile.spacing,
            leadDistance: profile.clearance + 16,
        };
    }

    function smartRouteForTerminals(source, target, network, excludedEdge = null, value = {}, existingSegmentsOverride = null) {
        if (!source || !target) return { points: [], fullPoints: [], strategy: 'unroutable', crossings: 0, clearance_conflicts: 1, bends: 0, length: 0, orthogonal: false, terminal_direction_ok: false };
        const profile = routingProfile(value);
        return routeOrthogonal({
            source: { x: source.x, y: source.y, side: source.side, id: String(source.value?.id || source.cell?.getValue?.()?.id || '') },
            target: { x: target.x, y: target.y, side: target.side, id: String(target.value?.id || target.cell?.getValue?.()?.id || '') },
            obstacles: routingObstacleRects(excludedEdge),
            existingSegments: Array.isArray(existingSegmentsOverride) ? existingSegmentsOverride : existingWireSegments(excludedEdge),
            network: normalizeWireNetwork(network),
            gridSize: Math.max(5, Math.min(GRID_SIZE, 10)),
            clearance: profile.clearance,
            channelSpacing: profile.spacing,
            leadDistance: profile.clearance + 16,
        });
    }

    function autoRouteResultForEdge(edge, valueOverride = null, existingSegmentsOverride = null) {
        const value = valueOverride || cellValue(edge) || {};
        const source = wireTerminalDescriptor(edge, true);
        const target = wireTerminalDescriptor(edge, false);
        return smartRouteForTerminals(source, target, value.network, edge, value, existingSegmentsOverride);
    }

    function autoRoutePointsForEdge(edge) {
        return autoRouteResultForEdge(edge).points || [];
    }

    function sameRoutePoints(current = [], next = []) {
        if (current.length !== next.length) return false;
        return current.every((point, index) => Math.abs(point.x - next[index].x) < 0.01 && Math.abs(point.y - next[index].y) < 0.01);
    }

    function applySmartRouteResult(edge, value, result) {
        const geometry = edge.getGeometry()?.clone() || new Geometry();
        geometry.relative = true;
        const nextPoints = result.points || [];
        const currentPoints = Array.isArray(geometry.points) ? geometry.points : [];
        if (!sameRoutePoints(currentPoints, nextPoints)) {
            geometry.points = nextPoints.map((point) => new Point(point.x, point.y));
            model.setGeometry(edge, geometry);
        }
        const nextValue = {
            ...value,
            router_version: result.version || SLD_SMART_ROUTER_VERSION,
            route_contract: result.contract || SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
            route_strategy: result.strategy || SLD_ORTHOGONAL_ROUTING_CONTRACT.engine,
            route_signature: result.signature || '',
            route_network_class: result.network_class || 'unclassified',
            route_preferred_axis: result.preferred_axis || '',
            route_preferred_channel: Number(result.preferred_channel || 0),
            route_length: Number(result.length || 0),
            route_bends: Number(result.bends || 0),
            route_crossings: Number(result.crossings || 0),
            route_clearance_conflicts: Number(result.clearance_conflicts || 0),
            route_separation_conflicts: Number(result.separation_conflicts || 0),
            route_terminal_direction_ok: result.terminal_direction_ok === true,
            route_orthogonal: result.orthogonal === true,
        };
        const metricsChanged = [
            'router_version', 'route_contract', 'route_strategy', 'route_signature', 'route_network_class',
            'route_preferred_axis', 'route_preferred_channel', 'route_length', 'route_bends', 'route_crossings',
            'route_clearance_conflicts', 'route_separation_conflicts', 'route_terminal_direction_ok', 'route_orthogonal',
        ].some((key) => value[key] !== nextValue[key]);
        if (metricsChanged) model.setValue(edge, nextValue);
    }

    function syncSmartWire(edge, rebuildRoute = true, valueOverride = null, resultOverride = null, existingSegmentsOverride = null) {
        const value = valueOverride || cellValue(edge) || {};
        if (!edge?.isEdge?.() || value?.entity !== 'edge') return;
        model.setStyle(edge, wireStyleForEdge(edge));
        if (!rebuildRoute || value.routing_mode === 'manual') return;
        const result = resultOverride || autoRouteResultForEdge(edge, value, existingSegmentsOverride);
        applySmartRouteResult(edge, value, result);
    }

    function routeAffectedByCells(edge, changedCells) {
        if (!(changedCells instanceof Set) || changedCells.size === 0) return true;
        if (changedCells.has(edge) || changedCells.has(edge.getTerminal?.(true)) || changedCells.has(edge.getTerminal?.(false))) return true;
        const value = cellValue(edge) || {};
        const profile = routingProfile(value);
        const changedObstacles = [...changedCells].flatMap((cell) => {
            if (!cell?.isVertex?.() || cell.isVisible?.() === false) return [];
            const geometry = cell.getGeometry?.();
            const cellData = cellValue(cell) || {};
            const obstacle = cellData.entity === 'node' || cellData.entity === 'annotation' || (cellData.entity === 'shape' && cellData.routing_keepout === true);
            if (!geometry || !obstacle) return [];
            return [{ id: String(cellData.id || cell.getId?.() || ''), left: geometry.x, top: geometry.y, right: geometry.x + geometry.width, bottom: geometry.y + geometry.height }];
        });
        return changedObstacles.length > 0 && routeIntersectsObstacles(edgeFullRoutePoints(edge), changedObstacles, profile.clearance);
    }

    function syncConnectedWires(cells = null, rebuildRoute = true) {
        if (smartWireSyncing) return;
        const changed = cells ? new Set(cells) : null;
        const allEdges = (graph.getDefaultParent()?.getChildren?.() || []).filter((cell) => cell?.isEdge?.() && cellValue(cell)?.entity === 'edge');
        allEdges.forEach((edge) => model.setStyle(edge, wireStyleForEdge(edge)));
        const candidates = allEdges.filter((edge) => {
            const value = cellValue(edge) || {};
            return value.routing_mode !== 'manual' && (!changed || routeAffectedByCells(edge, changed));
        }).sort((a, b) => {
            const av = cellValue(a) || {};
            const bv = cellValue(b) || {};
            return routingNetworkPriority(normalizeWireNetwork(av.network)) - routingNetworkPriority(normalizeWireNetwork(bv.network))
                || String(av.id || '').localeCompare(String(bv.id || ''));
        });
        if (!rebuildRoute || candidates.length === 0) {
            rebuildAutomaticLabels();
            graph.refresh();
            updateRoutingStatus();
            return;
        }
        const candidateSet = new Set(candidates);
        const fixedSegments = existingWireSegments(null, candidateSet);
        const requests = candidates.map((edge) => smartRouteRequestForEdge(edge)).filter(Boolean);
        const batch = routeOrthogonalBatch(requests, { existingSegments: fixedSegments });
        const routeById = new Map(batch.routes.map((route) => [String(route.id), route]));
        smartWireSyncing = true;
        try {
            candidates.forEach((edge) => {
                const value = cellValue(edge) || {};
                const result = routeById.get(String(value.id || edge.getId?.() || ''));
                if (result) syncSmartWire(edge, true, value, result);
            });
        } finally {
            smartWireSyncing = false;
        }
        rebuildAutomaticLabels();
        graph.refresh();
        updateRoutingStatus();
    }

    function scheduleSmartWireSync(cells = null) {
        if (smartWireSyncing || suppressDirty || wireSyncScheduled) return;
        wireSyncScheduled = true;
        window.requestAnimationFrame(() => {
            wireSyncScheduled = false;
            derivedWireSyncing = true;
            try {
                graph.batchUpdate(() => syncConnectedWires(cells, true));
            } finally {
                derivedWireSyncing = false;
            }
            refreshUi();
        });
    }

    function createLineCell(line) {
        const entity = line.entity === 'leader' ? 'leader' : 'line';
        const value = {
            entity,
            id: String(line.id || uid(entity)),
            manual: true,
            label: line.label || '',
            layer: line.layer || 'SLD-LINEWORK',
            line_type: line.line_type || 'continuous',
            lineweight: clamp(finite(line.lineweight, 1.4), 0.6, 5),
            locked: line.locked === true,
            group_id: line.group_id || null,
        };
        const geometry = new Geometry();
        geometry.relative = true;
        geometry.setTerminalPoint(new Point(finite(line.x1, 0), finite(line.y1, 0)), true);
        geometry.setTerminalPoint(new Point(finite(line.x2, 0), finite(line.y2, 0)), false);
        const cell = new Cell(value, geometry, draftingLineStyle(value));
        cell.setId(`line:${slug(value.id)}`);
        cell.setEdge(true);
        cell.setConnectable(false);
        graph.addCell(cell);
        return cell;
    }


    function createShapeCell(shape) {
        const type = ['rectangle', 'circle', 'arc'].includes(String(shape.shape_type)) ? String(shape.shape_type) : 'rectangle';
        const value = {
            entity: 'shape',
            id: String(shape.id || uid(type)),
            manual: true,
            shape_type: type,
            layer: shape.layer || 'SLD-LINEWORK',
            line_type: shape.line_type || 'continuous',
            lineweight: clamp(finite(shape.lineweight, 1.4), 0.6, 5),
            start_angle: finite(shape.start_angle, 0),
            end_angle: finite(shape.end_angle, 90),
            sweep_flag: Number(shape.sweep_flag) === 0 ? 0 : 1,
            large_arc: Number(shape.large_arc) ? 1 : 0,
            angle_basis: shape.angle_basis === 'screen' ? 'screen' : 'legacy',
            label: shape.label || '',
            locked: shape.locked === true,
        };
        const x = finite(shape.x, 0);
        const y = finite(shape.y, 0);
        const w = Math.max(4, finite(shape.w, 120));
        const h = Math.max(4, finite(shape.h, type === 'circle' ? w : 80));
        const cell = graph.insertVertex({
            parent: graph.getDefaultParent(),
            id: `shape:${slug(value.id)}`,
            value,
            position: [x, y],
            size: [w, h],
            style: geometryShapeStyle(value),
        });
        cell.setConnectable(false);
        return cell;
    }

    graph.batchUpdate(() => {
        sourceNodes.forEach((node, index) => createNodeCell(node, defaultPosition(node.id, index, storedPositions[node.id], node.default_position), false));
        manualNodes.forEach((node, index) => {
            const definition = SYMBOL_DEFINITIONS[node.kind] || SYMBOL_DEFINITIONS.panel;
            const normalized = { ...definition, ...node, id: node.id || uid('manual'), symbol: node.kind || 'panel' };
            createNodeCell(normalized, defaultPosition(normalized.id, sourceNodes.length + index, storedPositions[normalized.id], normalized.default_position), true);
        });
        annotations.forEach(createAnnotationCell);
        manualLines.forEach((line) => createLineCell(line));
        manualShapes.forEach((shape) => createShapeCell(shape));

        sourceConnections.forEach((connection, index) => {
            const source = cellsByNodeId.get(connection.from);
            const target = cellsByNodeId.get(connection.to);
            if (!source || !target) return;
            const value = {
                entity: 'edge',
                id: connection.id || `source_${index}_${slug(connection.from)}_${slug(connection.to)}`,
                manual: false,
                from: connection.from,
                to: connection.to,
                label: connection.label || '',
                detail: connection.detail || '',
                wire_id: connection.wire_id || '',
                cable_tag: connection.cable_tag || '',
                cable_type: connection.cable_type || '',
                cable_size: connection.cable_size || '',
                cores: connection.cores || '',
                voltage_class: connection.voltage_class || '',
                installation_method: connection.installation_method || '',
                notes: connection.notes || '',
                show_label: connection.show_label !== false,
                routing_mode: connection.routing_mode === 'manual' ? 'manual' : 'auto',
                routing_clearance: Object.hasOwn(ROUTING_CLEARANCE_PROFILES, connection.routing_clearance) ? connection.routing_clearance : 'standard',
                router_version: connection.router_version || SLD_SMART_ROUTER_VERSION,
                route_contract: connection.route_contract || SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
                route_strategy: connection.route_strategy || '',
                route_signature: connection.route_signature || '',
                route_network_class: connection.route_network_class || routingNetworkClass(connection.network),
                route_preferred_axis: connection.route_preferred_axis || '',
                route_preferred_channel: finite(connection.route_preferred_channel, 0),
                route_length: finite(connection.route_length, 0),
                route_bends: finite(connection.route_bends, 0),
                route_crossings: finite(connection.route_crossings, 0),
                route_clearance_conflicts: finite(connection.route_clearance_conflicts, 0),
                route_separation_conflicts: finite(connection.route_separation_conflicts, 0),
                route_terminal_direction_ok: connection.route_terminal_direction_ok === true,
                route_orthogonal: connection.route_orthogonal === true,
                from_port: connection.from_port || null,
                to_port: connection.to_port || null,
                network: normalizeWireNetwork(connection.network),
            };
            const edge = graph.insertEdge({
                parent: graph.getDefaultParent(),
                id: `edge:${value.id}`,
                value,
                source,
                target,
                style: edgeStyle(false, source.getValue()?.kind, target.getValue()?.kind, value.network),
            });
            if (value.routing_mode === 'manual') applyRoute(edge, storedRoutes[value.id]);
        });

        manualConnections.forEach((connection, index) => {
            const source = cellsByNodeId.get(connection.from);
            const target = cellsByNodeId.get(connection.to);
            if (!source || !target) return;
            const value = {
                entity: 'edge',
                id: connection.id || uid('wire'),
                manual: true,
                from: connection.from,
                to: connection.to,
                label: connection.label || wireNetworkDefinition(connection.network).label,
                detail: connection.detail || 'Cable data not specified',
                wire_id: connection.wire_id || connection.id || '',
                cable_tag: connection.cable_tag || '',
                cable_type: connection.cable_type || '',
                cable_size: connection.cable_size || '',
                cores: connection.cores || '',
                voltage_class: connection.voltage_class || '',
                installation_method: connection.installation_method || '',
                notes: connection.notes || '',
                show_label: connection.show_label !== false,
                routing_mode: connection.routing_mode === 'manual' ? 'manual' : 'auto',
                routing_clearance: Object.hasOwn(ROUTING_CLEARANCE_PROFILES, connection.routing_clearance) ? connection.routing_clearance : 'standard',
                router_version: connection.router_version || SLD_SMART_ROUTER_VERSION,
                route_contract: connection.route_contract || SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
                route_strategy: connection.route_strategy || '',
                route_signature: connection.route_signature || '',
                route_network_class: connection.route_network_class || routingNetworkClass(connection.network),
                route_preferred_axis: connection.route_preferred_axis || '',
                route_preferred_channel: finite(connection.route_preferred_channel, 0),
                route_length: finite(connection.route_length, 0),
                route_bends: finite(connection.route_bends, 0),
                route_crossings: finite(connection.route_crossings, 0),
                route_clearance_conflicts: finite(connection.route_clearance_conflicts, 0),
                route_separation_conflicts: finite(connection.route_separation_conflicts, 0),
                route_terminal_direction_ok: connection.route_terminal_direction_ok === true,
                route_orthogonal: connection.route_orthogonal === true,
                from_port: connection.from_port || null,
                to_port: connection.to_port || null,
                network: normalizeWireNetwork(connection.network),
            };
            const edge = graph.insertEdge({
                parent: graph.getDefaultParent(),
                id: `edge:${slug(value.id)}:${index}`,
                value,
                source,
                target,
                style: manualEdgeStyle(source.getValue()?.kind, target.getValue()?.kind, true, value.network),
            });
            if (value.routing_mode === 'manual') applyRoute(edge, storedRoutes[value.id]);
        });

        excludedNodes.forEach((id) => {
            const cell = cellsByNodeId.get(id);
            if (cell && cell.getValue?.()?.manual !== true) model.setVisible(cell, false);
        });
    });

    // Build every automatic wire in one deterministic network-priority batch.
    // Manual routes keep their stored control points but still receive the
    // canonical port-entry style contract.
    graph.batchUpdate(() => syncConnectedWires(null, true));

    suppressDirty = false;
    undoManager.clear();

    function selectedCells() {
        return graph.getSelectionCells?.() || [];
    }

    function selectedCell() {
        return graph.getSelectionCell?.() || null;
    }

    function inspectorCell() {
        const cells = selectedCells();
        if (cells.length === 1) return cells[0];
        if (cells.length !== 2) return null;
        const values = cells.map((cell) => cellValue(cell));
        const groupId = values[0]?.group_id;
        if (!groupId || values.some((value) => value?.group_id !== groupId)) return null;
        const leaderIndex = values.findIndex((value) => value?.entity === 'leader');
        const annotationIndex = values.findIndex((value) => value?.entity === 'annotation');
        if (leaderIndex < 0 || annotationIndex < 0) return null;
        return cells[annotationIndex];
    }

    function cellValue(cell) {
        const value = cell?.getValue?.();
        return value && typeof value === 'object' ? value : null;
    }

    function nextTag(kind) {
        const definition = SYMBOL_DEFINITIONS[kind] || SYMBOL_DEFINITIONS.panel;
        const prefix = definition.prefix;
        const used = new Set();
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        children.forEach((cell) => {
            const tag = String(cellValue(cell)?.tag || '');
            const match = tag.match(new RegExp(`^${prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}-(\\d+)$`, 'i'));
            if (match) used.add(Number(match[1]));
        });
        let number = 1;
        while (used.has(number)) number += 1;
        return `${prefix}-${String(number).padStart(2, '0')}`;
    }

    function nextWireId(network = 'unclassified') {
        const definition = wireNetworkDefinition(network);
        const prefix = `W-${definition.short.replace(/[^A-Z0-9]+/gi, '').toUpperCase() || 'WIRE'}`;
        const used = new Set();
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        children.forEach((cell) => {
            const value = cellValue(cell);
            if (!cell.isEdge?.() || value?.entity !== 'edge') return;
            const candidate = String(value.wire_id || value.id || '');
            const match = candidate.match(new RegExp(`^${prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}-(\\d+)$`, 'i'));
            if (match) used.add(Number(match[1]));
        });
        let number = 1;
        while (used.has(number)) number += 1;
        return `${prefix}-${String(number).padStart(3, '0')}`;
    }

    function updateOsnapToolScopeUi() {
        const engineering = toolUsesEngineeringPortSnap();
        root.dataset.osnapScope = engineering ? 'engineering' : 'geometric';
        root.querySelector('[data-editor-osnap-wire-only]')?.classList.toggle('is-context-active', engineering);
        const statusButton = root.querySelector('[data-editor-status-toggle="osnap"]');
        if (statusButton) statusButton.title = engineering
            ? 'Object snap · engineering PORT mode for Wire (F3)'
            : 'Object snap · geometric modes only (F3)';
    }

    const DRAWING_COMMAND_TOOLS = new Set(['line', 'polyline', 'rectangle', 'circle', 'arc', 'leader', 'text', 'place_symbol']);

    function interactionModeForTool(tool = activeTool) {
        if (tool === 'select') return 'select';
        if (tool === 'pan') return 'navigate';
        if (tool === 'wire') return 'wire';
        return DRAWING_COMMAND_TOOLS.has(tool) ? 'draft' : 'select';
    }

    function clearSelectionAndResizeState() {
        precisionResizeSession = null;
        root.classList.remove('is-precision-resizing');
        graph.clearSelection?.();
        if (els.resizeOverlay) els.resizeOverlay.innerHTML = '';
        if (els.selectedSnapPoints) els.selectedSnapPoints.innerHTML = '';
    }

    function setTool(tool, options = {}) {
        const allowed = ['select', 'pan', 'line', 'polyline', 'rectangle', 'circle', 'arc', 'wire', 'text', 'leader', 'place_symbol'];
        const nextTool = allowed.includes(tool) ? tool : 'select';
        if (activeTool !== nextTool) resetDraftSession();
        activeTool = nextTool;
        if (activeTool !== 'select') clearSelectionAndResizeState();
        if (['line', 'polyline', 'rectangle', 'circle', 'arc', 'leader'].includes(nextTool) && orthoEnabled && options.keepOrtho !== true) {
            setOrtho(false, false);
        }
        if (nextTool !== 'wire') {
            pendingWireSource = null;
            pendingWireSourcePort = null;
        }
        if (nextTool !== 'place_symbol' && !options.keepPlacement) placementKind = null;
        root.dataset.activeTool = activeTool;
        root.dataset.interactionMode = interactionModeForTool(activeTool);
        updateOsnapToolScopeUi();
        root.dataset.circleMode = circleMode;
        root.dataset.arcMode = arcMode;
        root.querySelectorAll('[data-editor-tool]').forEach((button) => button.classList.toggle('is-active', button.dataset.editorTool === activeTool));
        container.classList.toggle('is-wire-mode', activeTool === 'wire');
        container.classList.toggle('is-line-mode', DRAWING_COMMAND_TOOLS.has(activeTool));
        window.requestAnimationFrame(updateWirePortPresentation);
        container.classList.toggle('is-select-mode', activeTool === 'select');
        container.classList.toggle('is-pan-tool', activeTool === 'pan');
        container.classList.toggle('is-pan-mode', activeTool === 'pan' || spaceDown || panSession !== null);
        container.classList.toggle('is-insert-mode', activeTool === 'place_symbol');
        graph.setConnectable(!readOnly && activeTool === 'wire');
        if (els.hint) {
            els.hint.classList.remove('is-hidden');
            const hints = {
                select: '<kbd>SPACE</kbd> + drag or middle-mouse drag to pan',
                pan: '<kbd>H</kbd> drag anywhere to pan · <kbd>V</kbd> return to Select',
                line: '<kbd>L</kbd> specify first point · <kbd>ENTER</kbd> finish',
                polyline: '<kbd>PL</kbd> specify first point · continuous precision linework',
                rectangle: '<kbd>REC</kbd> specify first corner',
                circle: `<kbd>C</kbd> ${circleModeLabel()} · choose mode from the arrow`,
                arc: `<kbd>A</kbd> ${arcModeLabel()} · choose mode from the arrow`,
                wire: '<kbd>W</kbd> select source, then compatible destination',
                text: '<kbd>T</kbd> specify insertion point for drawing note',
                leader: '<kbd>LE</kbd> specify arrowhead and landing point',
                place_symbol: '<kbd>INSERT</kbd> specify insertion point',
            };
            els.hint.innerHTML = hints[activeTool] || hints.select;
        }
        updateCommandUi();
        updateDraftOverlay();
        renderPrecisionResizeOverlay();
        renderSelectedSnapPoints();
        graph.refresh();
        container.focus({ preventScroll: true });
    }

    function beginSymbolPlacement(kind) {
        if (readOnly || !SYMBOL_DEFINITIONS[kind]) return;
        placementKind = kind;
        setTool('place_symbol', { keepPlacement: true });
        updateCommandUi(`Specify insertion point for ${SYMBOL_DEFINITIONS[kind].title}.`);
    }

    function addManual(kind, point = null, options = {}) {
        if (readOnly) return;
        const definition = SYMBOL_DEFINITIONS[kind] || SYMBOL_DEFINITIONS.panel;
        const id = uid(`manual_${slug(kind)}`);
        const view = graph.getView();
        const [width, height] = definition.size || [144, 120];
        const location = point || {
            x: Math.max(80, (container.clientWidth / 2) / view.scale - view.translate.x - width / 2),
            y: Math.max(80, (container.clientHeight / 2) / view.scale - view.translate.y - height / 2),
        };
        let cell;
        graph.batchUpdate(() => {
            cell = createNodeCell({ ...definition, id, tag: nextTag(kind), symbol: kind, state: 'manual' }, {
                x: graph.snap(location.x),
                y: graph.snap(location.y),
                w: width,
                h: height,
            }, true);
        });
        graph.setSelectionCell(cell);
        openPanel('inspector');
        markDirty();
        refreshUi();
        if (options.selectToolAfter) setTool('select');
    }

    function addAnnotation(point) {
        if (readOnly) return;
        const value = { entity: 'annotation', id: uid('note'), text: 'Drawing note', manual: true };
        let cell;
        graph.batchUpdate(() => {
            cell = graph.insertVertex({
                parent: graph.getDefaultParent(),
                id: `note:${value.id}`,
                value,
                position: [graph.snap(point.x), graph.snap(point.y)],
                size: [300, 64],
                style: vertexStyle(value),
            });
        });
        graph.setSelectionCell(cell);
        openPanel('inspector');
        markDirty();
        setTool('select');
        window.setTimeout(() => {
            fields.title?.focus();
            fields.title?.select?.();
        }, 60);
    }

    function graphPoint(event) {
        const rect = container.getBoundingClientRect();
        const view = graph.getView();
        return {
            x: (event.clientX - rect.left) / view.scale - view.translate.x,
            y: (event.clientY - rect.top) / view.scale - view.translate.y,
        };
    }

    function graphToScreen(point) {
        const view = graph.getView();
        return {
            x: (point.x + view.translate.x) * view.scale,
            y: (point.y + view.translate.y) * view.scale,
        };
    }

    function graphToFrameScreen(point) {
        const local = graphToScreen(point);
        const frame = root.querySelector('.nxsld-canvas-frame')?.getBoundingClientRect();
        const rect = container.getBoundingClientRect();
        return {
            x: local.x + (frame ? rect.left - frame.left : 0),
            y: local.y + (frame ? rect.top - frame.top : 0),
        };
    }

    let precisionResizeSession = null;

    function precisionResizableCell() {
        if (activeSpace !== 'model' || activeTool !== 'select' || readOnly) return null;
        const cells = selectedCells();
        if (cells.length !== 1) return null;
        const cell = cells[0];
        const value = cellValue(cell) || {};
        if (!cell?.isVertex?.() || value.locked === true || layerState[objectLayer(value)]?.locked) return null;
        if (!['node', 'shape', 'annotation'].includes(value.entity) && value.manual !== true) return null;
        return cell;
    }

    function precisionResizeLimits(value = {}) {
        if (value.entity === 'node') return { minWidth: 96, minHeight: 80, maxWidth: 900, maxHeight: 700 };
        if (value.entity === 'annotation') return { minWidth: 120, minHeight: 40, maxWidth: 1200, maxHeight: 700 };
        return { minWidth: 20, minHeight: 20, maxWidth: 1600, maxHeight: 1200 };
    }

    function renderPrecisionResizeOverlay() {
        if (!els.resizeOverlay) return;
        const cell = precisionResizableCell();
        const geometry = cell?.getGeometry?.();
        if (!cell || !geometry || precisionResizeSession?.cell && precisionResizeSession.cell !== cell) {
            els.resizeOverlay.innerHTML = '';
            return;
        }
        const a = graphToFrameScreen({ x: geometry.x, y: geometry.y });
        const b = graphToFrameScreen({ x: geometry.x + geometry.width, y: geometry.y + geometry.height });
        const left = Math.min(a.x, b.x);
        const top = Math.min(a.y, b.y);
        const width = Math.max(1, Math.abs(b.x - a.x));
        const height = Math.max(1, Math.abs(b.y - a.y));
        const right = left + width;
        const bottom = top + height;
        const cx = left + width / 2;
        const cy = top + height / 2;
        const points = {
            nw: [left, top], n: [cx, top], ne: [right, top],
            e: [right, cy], se: [right, bottom], s: [cx, bottom],
            sw: [left, bottom], w: [left, cy],
        };
        const labels = {
            nw: 'Resize north-west', n: 'Resize height', ne: 'Resize north-east', e: 'Resize width',
            se: 'Resize south-east', s: 'Resize height', sw: 'Resize south-west', w: 'Resize width',
        };
        const handles = Object.entries(points).map(([key, [x, y]]) => `
            <g class="nxsld-precision-resize-handle is-${key}" data-editor-resize-handle="${key}" transform="translate(${x} ${y})">
                <rect x="-7" y="-7" width="14" height="14" rx="2"/>
                <title>${labels[key]}</title>
            </g>`).join('');
        const value = cellValue(cell) || {};
        const standard = value.entity === 'node' ? registrySymbol(value.symbol || value.kind)?.nominal_size : null;
        const status = standard ? `${Math.round(geometry.width)} × ${Math.round(geometry.height)} · GLYPH PROPORTION LOCKED` : `${Math.round(geometry.width)} × ${Math.round(geometry.height)}`;
        const badgeWidth = Math.max(112, status.length * 6.2 + 18);
        const badgeX = clamp(cx - badgeWidth / 2, 8, Math.max(8, (els.canvasFrame?.clientWidth || container.clientWidth) - badgeWidth - 8));
        const badgeY = bottom + 13;
        els.resizeOverlay.innerHTML = `
            <g class="nxsld-precision-resize-frame${precisionResizeSession ? ' is-active' : ''}" data-editor-resize-cell="${escapeHtml(value.id || cell.getId?.() || '')}">
                <rect class="nxsld-precision-resize-bounds" x="${left}" y="${top}" width="${width}" height="${height}"/>
                <path class="nxsld-precision-resize-axis" d="M${cx - 9} ${cy}H${cx + 9}M${cx} ${cy - 9}V${cy + 9}"/>
                ${handles}
                <g class="nxsld-precision-resize-badge" transform="translate(${badgeX} ${badgeY})">
                    <rect width="${badgeWidth}" height="22" rx="4"/>
                    <text x="${badgeWidth / 2}" y="14" text-anchor="middle">${escapeHtml(status)}</text>
                </g>
            </g>`;
    }

    function snappedResizeValue(value, minimum, maximum) {
        const raw = clamp(value, minimum, maximum);
        if (!snapEnabled) return raw;
        return clamp(Math.round(raw / GRID_SIZE) * GRID_SIZE, minimum, maximum);
    }

    function beginPrecisionResize(event) {
        const handle = event.target?.closest?.('[data-editor-resize-handle]');
        if (!handle || precisionResizeSession || event.button !== 0) return;
        const cell = precisionResizableCell();
        const geometry = cell?.getGeometry?.()?.clone?.();
        if (!cell || !geometry) return;
        precisionResizeSession = {
            cell,
            pointerId: event.pointerId,
            handle: String(handle.dataset.editorResizeHandle || 'se'),
            startPoint: graphPoint(event),
            geometry,
            aspect: geometry.width / Math.max(geometry.height, 1),
        };
        root.classList.add('is-precision-resizing');
        handle.setPointerCapture?.(event.pointerId);
        updateCommandUi('Drag the precision handle. Hold Shift to preserve the envelope ratio.');
        event.preventDefault();
        event.stopPropagation();
        renderPrecisionResizeOverlay();
    }

    function movePrecisionResize(event) {
        const session = precisionResizeSession;
        if (!session || event.pointerId !== session.pointerId) return;
        const value = cellValue(session.cell) || {};
        const limits = precisionResizeLimits(value);
        const point = graphPoint(event);
        const dx = point.x - session.startPoint.x;
        const dy = point.y - session.startPoint.y;
        const original = session.geometry;
        const handle = session.handle;
        let left = original.x;
        let top = original.y;
        let right = original.x + original.width;
        let bottom = original.y + original.height;
        if (handle.includes('w')) left += dx;
        if (handle.includes('e')) right += dx;
        if (handle.includes('n')) top += dy;
        if (handle.includes('s')) bottom += dy;

        let width = right - left;
        let height = bottom - top;
        const corner = handle.length === 2;
        if (event.shiftKey && corner) {
            if (Math.abs(dx) >= Math.abs(dy)) height = Math.sign(height || 1) * Math.abs(width) / Math.max(session.aspect, 0.01);
            else width = Math.sign(width || 1) * Math.abs(height) * session.aspect;
            if (handle.includes('w')) left = right - width; else right = left + width;
            if (handle.includes('n')) top = bottom - height; else bottom = top + height;
        }

        width = snappedResizeValue(Math.abs(width), limits.minWidth, limits.maxWidth);
        height = snappedResizeValue(Math.abs(height), limits.minHeight, limits.maxHeight);
        if (handle.includes('w')) left = right - width; else right = left + width;
        if (handle.includes('n')) top = bottom - height; else bottom = top + height;

        const next = original.clone();
        next.x = snapEnabled ? graph.snap(Math.min(left, right)) : Math.min(left, right);
        next.y = snapEnabled ? graph.snap(Math.min(top, bottom)) : Math.min(top, bottom);
        next.width = width;
        next.height = height;
        model.setGeometry(session.cell, next);
        renderPrecisionResizeOverlay();
        if (fields.w && document.activeElement !== fields.w) fields.w.value = String(Math.round(width));
        if (fields.h && document.activeElement !== fields.h) fields.h.value = String(Math.round(height));
        event.preventDefault();
    }

    function endPrecisionResize(event) {
        const session = precisionResizeSession;
        if (!session || event.pointerId !== session.pointerId) return;
        precisionResizeSession = null;
        root.classList.remove('is-precision-resizing');
        syncConnectedWires([session.cell], true);
        markDirty();
        scheduleElectricalCheck();
        refreshUi();
        updateCommandUi('Resize complete. IEC glyph proportions and engineering ports were preserved.');
        event.preventDefault();
    }

    function normalizeRect(a, b) {
        return {
            left: Math.min(a.x, b.x),
            top: Math.min(a.y, b.y),
            right: Math.max(a.x, b.x),
            bottom: Math.max(a.y, b.y),
            width: Math.abs(b.x - a.x),
            height: Math.abs(b.y - a.y),
        };
    }

    function pointInsideRect(point, rect) {
        return point.x >= rect.left && point.x <= rect.right && point.y >= rect.top && point.y <= rect.bottom;
    }

    function segmentHitsRect(a, b, rect) {
        if (pointInsideRect(a, rect) || pointInsideRect(b, rect)) return true;
        const edges = [
            [{ x: rect.left, y: rect.top }, { x: rect.right, y: rect.top }],
            [{ x: rect.right, y: rect.top }, { x: rect.right, y: rect.bottom }],
            [{ x: rect.right, y: rect.bottom }, { x: rect.left, y: rect.bottom }],
            [{ x: rect.left, y: rect.bottom }, { x: rect.left, y: rect.top }],
        ];
        return edges.some(([c, d]) => segmentIntersection(a, b, c, d) !== null);
    }

    function edgeModelPoints(cell) {
        const geometry = cell?.getGeometry?.();
        if (!geometry) return [];
        const points = [];
        const source = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
        const target = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
        if (source) points.push({ x: source.x, y: source.y });
        (geometry.points || []).forEach((point) => points.push({ x: point.x, y: point.y }));
        if (target) points.push({ x: target.x, y: target.y });
        if (points.length < 2) {
            const sourceState = graph.getView().getState(cell.getTerminal?.(true));
            const targetState = graph.getView().getState(cell.getTerminal?.(false));
            if (sourceState) points.unshift({ x: sourceState.getCenterX() / graph.getView().scale - graph.getView().translate.x, y: sourceState.getCenterY() / graph.getView().scale - graph.getView().translate.y });
            if (targetState) points.push({ x: targetState.getCenterX() / graph.getView().scale - graph.getView().translate.x, y: targetState.getCenterY() / graph.getView().scale - graph.getView().translate.y });
        }
        return points;
    }

    function cellsInsideSelection(start, end) {
        const rect = normalizeRect(start, end);
        const crossing = end.x < start.x;
        const matches = [];
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        children.forEach((cell) => {
            if (cell.isVisible?.() === false) return;
            if (cell.isVertex?.()) {
                const geometry = cell.getGeometry?.();
                if (!geometry) return;
                const bounds = {
                    left: geometry.x,
                    top: geometry.y,
                    right: geometry.x + geometry.width,
                    bottom: geometry.y + geometry.height,
                };
                const contained = bounds.left >= rect.left && bounds.right <= rect.right && bounds.top >= rect.top && bounds.bottom <= rect.bottom;
                const intersects = bounds.right >= rect.left && bounds.left <= rect.right && bounds.bottom >= rect.top && bounds.top <= rect.bottom;
                if (crossing ? intersects : contained) matches.push(cell);
                return;
            }
            if (cell.isEdge?.()) {
                const points = edgeModelPoints(cell);
                if (points.length < 2) return;
                const contained = points.every((point) => pointInsideRect(point, rect));
                const intersects = points.some((point) => pointInsideRect(point, rect)) || points.slice(0, -1).some((point, index) => segmentHitsRect(point, points[index + 1], rect));
                if (crossing ? intersects : contained) matches.push(cell);
            }
        });
        return matches;
    }

    function renderSelectionWindow(start, end, crossing = false) {
        if (!els.selectionWindow) return;
        if (!start || !end) {
            els.selectionWindow.setAttribute('hidden', '');
            els.selectionWindow.setAttribute('display', 'none');
            els.selectionWindow.removeAttribute('x');
            els.selectionWindow.removeAttribute('y');
            els.selectionWindow.removeAttribute('width');
            els.selectionWindow.removeAttribute('height');
            els.selectionWindow.classList.remove('is-crossing');
            return;
        }
        const rect = normalizeRect(start, end);
        els.selectionWindow.removeAttribute('hidden');
        els.selectionWindow.removeAttribute('display');
        els.selectionWindow.setAttribute('x', String(rect.left));
        els.selectionWindow.setAttribute('y', String(rect.top));
        els.selectionWindow.setAttribute('width', String(rect.width));
        els.selectionWindow.setAttribute('height', String(rect.height));
        els.selectionWindow.classList.toggle('is-crossing', crossing);
    }

    function gridSnap(point) {
        return snapEnabled ? { x: graph.snap(point.x), y: graph.snap(point.y) } : { x: point.x, y: point.y };
    }

    function distance(a, b) {
        return Math.hypot((b?.x || 0) - (a?.x || 0), (b?.y || 0) - (a?.y || 0));
    }

    function angleDegrees(a, b) {
        // CAD convention: 0° points right and positive angles rotate counter-clockwise.
        // Model-space Y increases downward, so Y is inverted for the angle calculation.
        let angle = Math.atan2((a?.y || 0) - (b?.y || 0), (b?.x || 0) - (a?.x || 0)) * 180 / Math.PI;
        if (angle < 0) angle += 360;
        return angle;
    }

    function pointOnAngle(anchor, length, angle) {
        const radians = angle * Math.PI / 180;
        return { x: anchor.x + Math.cos(radians) * length, y: anchor.y - Math.sin(radians) * length };
    }

    function nearestPointOnSegment(point, a, b) {
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const length2 = dx * dx + dy * dy;
        if (length2 <= 0.000001) return { ...a };
        const t = clamp(((point.x - a.x) * dx + (point.y - a.y) * dy) / length2, 0, 1);
        return { x: a.x + t * dx, y: a.y + t * dy };
    }

    function segmentIntersection(a, b, c, d) {
        const denominator = (a.x - b.x) * (c.y - d.y) - (a.y - b.y) * (c.x - d.x);
        if (Math.abs(denominator) < 0.00001) return null;
        const determinant1 = a.x * b.y - a.y * b.x;
        const determinant2 = c.x * d.y - c.y * d.x;
        const x = (determinant1 * (c.x - d.x) - (a.x - b.x) * determinant2) / denominator;
        const y = (determinant1 * (c.y - d.y) - (a.y - b.y) * determinant2) / denominator;
        const within = (value, start, end) => value >= Math.min(start, end) - 0.01 && value <= Math.max(start, end) + 0.01;
        if (!within(x, a.x, b.x) || !within(y, a.y, b.y) || !within(x, c.x, d.x) || !within(y, c.y, d.y)) return null;
        return { x, y };
    }

    function portCoordinates(value, geometry) {
        const ports = portProfile(value.symbol || value.kind, value.ports);
        const rect = { x: geometry.x, y: geometry.y, w: geometry.width, h: geometry.height };
        return ports.map((port) => {
            const point = canonicalPortPoint(rect, port, value);
            return {
                x: point.x,
                y: point.y,
                side: point.side,
                relativeX: point.relativeX,
                relativeY: point.relativeY,
                type: 'Port',
                priority: 0,
                port,
                label: `${port.id} · ${port.label}`,
            };
        });
    }

    function rectangleSegments(geometry) {
        const x1 = geometry.x;
        const y1 = geometry.y;
        const x2 = geometry.x + geometry.width;
        const y2 = geometry.y + geometry.height;
        return [
            [{ x: x1, y: y1 }, { x: x2, y: y1 }],
            [{ x: x2, y: y1 }, { x: x2, y: y2 }],
            [{ x: x2, y: y2 }, { x: x1, y: y2 }],
            [{ x: x1, y: y2 }, { x: x1, y: y1 }],
        ];
    }

    function lineSegments() {
        const segments = [];
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        children.forEach((cell) => {
            const value = cellValue(cell);
            const geometry = cell.getGeometry?.();
            if (!value || !geometry || cell.isVisible?.() === false) return;
            if (cell.isEdge?.() && isDraftingLineValue(value)) {
                const a = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
                const b = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
                if (a && b) segments.push({ a: { x: a.x, y: a.y }, b: { x: b.x, y: b.y }, cell });
            } else if (cell.isEdge?.() && value.entity === 'edge') {
                const route = edgeFullRoutePoints(cell);
                for (let index = 1; index < route.length; index += 1) {
                    segments.push({ a: route[index - 1], b: route[index], cell });
                }
            }
            if (cell.isVertex?.() && value.entity === 'shape' && value.shape_type === 'rectangle') {
                rectangleSegments(geometry).forEach(([a, b]) => segments.push({ a, b, cell }));
            }
        });
        return segments;
    }

    function osnapModeEnabled(type) {
        const key = osnapKeyForType(type);
        return osnapModes[key] !== false;
    }

    function toolUsesEngineeringPortSnap(tool = activeTool) {
        return tool === 'wire';
    }

    function toolUsesGeometricOsnap(tool = activeTool) {
        return ['line', 'polyline', 'rectangle', 'circle', 'arc', 'leader', 'text', 'place_symbol'].includes(tool);
    }

    function snapCandidates(rawPoint) {
        const candidates = [];
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        const add = (point, type, priority = 4, extra = {}) => {
            if (!osnapModeEnabled(type)) return;
            candidates.push({ x: point.x, y: point.y, type, label: extra.label || type, priority, ...extra });
        };

        children.forEach((cell) => {
            const value = cellValue(cell);
            const geometry = cell.getGeometry?.();
            if (!value || !geometry || cell.isVisible?.() === false) return;
            if (cell.isEdge?.() && isDraftingLineValue(value)) {
                const a = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
                const b = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
                if (!a || !b) return;
                add(a, 'Endpoint', 1, { cell });
                add(b, 'Endpoint', 1, { cell });
                add({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, 'Midpoint', 2, { cell });
                add(nearestPointOnSegment(rawPoint, a, b), 'Nearest', 8, { cell });
                return;
            }
            if (cell.isEdge?.() && value.entity === 'edge') {
                const route = edgeFullRoutePoints(cell);
                if (route.length >= 2) {
                    add(route[0], 'Endpoint', 1, { cell, label: 'Wire endpoint' });
                    add(route.at(-1), 'Endpoint', 1, { cell, label: 'Wire endpoint' });
                    route.slice(1).forEach((point, index) => {
                        const a = route[index];
                        const b = point;
                        add({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, 'Midpoint', 2, { cell, label: 'Wire midpoint' });
                        add(nearestPointOnSegment(rawPoint, a, b), 'Nearest', 8, { cell, label: 'Nearest on wire' });
                    });
                }
                return;
            }
            if (!cell.isVertex?.()) return;
            const x1 = geometry.x;
            const y1 = geometry.y;
            const x2 = geometry.x + geometry.width;
            const y2 = geometry.y + geometry.height;
            const cx = (x1 + x2) / 2;
            const cy = (y1 + y2) / 2;
            if (value.entity === 'shape') {
                if (value.shape_type === 'circle') {
                    const rx = geometry.width / 2;
                    const ry = geometry.height / 2;
                    add({ x: cx, y: cy }, 'Center', 1, { cell, label: 'Center' });
                    add({ x: cx, y: y1 }, 'Quadrant', 2, { cell, label: 'Quadrant' });
                    add({ x: x2, y: cy }, 'Quadrant', 2, { cell, label: 'Quadrant' });
                    add({ x: cx, y: y2 }, 'Quadrant', 2, { cell, label: 'Quadrant' });
                    add({ x: x1, y: cy }, 'Quadrant', 2, { cell, label: 'Quadrant' });
                    const theta = Math.atan2((rawPoint.y - cy) / Math.max(ry, 0.001), (rawPoint.x - cx) / Math.max(rx, 0.001));
                    add({ x: cx + Math.cos(theta) * rx, y: cy + Math.sin(theta) * ry }, 'Nearest', 7, { cell, label: 'Nearest' });
                    return;
                }
                if (value.shape_type === 'arc') {
                    const radiusX = geometry.width / 2;
                    const radiusY = geometry.height / 2;
                    const screenBasis = value.angle_basis === 'screen';
                    const pointAt = (angle) => {
                        const radians = angle * Math.PI / 180;
                        const raw = { x: cx + Math.cos(radians) * radiusX, y: cy + (screenBasis ? 1 : -1) * Math.sin(radians) * radiusY };
                        return transformObjectPoint(raw, { x: cx, y: cy }, value);
                    };
                    add({ x: cx, y: cy }, 'Center', 2, { cell, label: 'Center' });
                    add(pointAt(value.start_angle), 'Endpoint', 1, { cell, label: 'Endpoint' });
                    add(pointAt(value.end_angle), 'Endpoint', 1, { cell, label: 'Endpoint' });
                    const sweep = Number(value.sweep_flag) === 0 ? 0 : 1;
                    const startAngle = normalizeAngle(value.start_angle);
                    const endAngle = normalizeAngle(value.end_angle);
                    const span = sweep ? normalizeAngle(endAngle - startAngle) : normalizeAngle(startAngle - endAngle);
                    const midAngle = sweep ? normalizeAngle(startAngle + span / 2) : normalizeAngle(startAngle - span / 2);
                    add(pointAt(midAngle), 'Midpoint', 3, { cell, label: 'Midpoint' });
                    const rawAngle = normalizeAngle(Math.atan2(screenBasis ? rawPoint.y - cy : cy - rawPoint.y, rawPoint.x - cx) * 180 / Math.PI);
                    const progress = sweep ? normalizeAngle(rawAngle - startAngle) : normalizeAngle(startAngle - rawAngle);
                    if (progress <= span + 0.001) add(pointAt(rawAngle), 'Nearest', 7, { cell, label: 'Nearest' });
                    return;
                }
                const rectanglePoints = [
                    { x: x1, y: y1 },
                    { x: x2, y: y1 },
                    { x: x2, y: y2 },
                    { x: x1, y: y2 },
                ];
                rectanglePoints.forEach((corner) => add(corner, 'Endpoint', 1, { cell, label: 'Endpoint' }));
                rectangleSegments(geometry).forEach(([a, b]) => {
                    add({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, 'Midpoint', 2, { cell, label: 'Midpoint' });
                    add(nearestPointOnSegment(rawPoint, a, b), 'Nearest', 7, { cell, label: 'Nearest' });
                });
                add({ x: cx, y: cy }, 'GeometricCenter', 2, { cell, label: 'Geometric center' });
                return;
            }
            if (value.entity === 'annotation') {
                add({ x: x1, y: y1 }, 'Endpoint', 4, { cell });
                add({ x: x2, y: y1 }, 'Endpoint', 4, { cell });
                add({ x: x2, y: y2 }, 'Endpoint', 4, { cell });
                add({ x: x1, y: y2 }, 'Endpoint', 4, { cell });
                add({ x: cx, y: y1 }, 'Midpoint', 5, { cell });
                add({ x: x2, y: cy }, 'Midpoint', 5, { cell });
                add({ x: cx, y: y2 }, 'Midpoint', 5, { cell });
                add({ x: x1, y: cy }, 'Midpoint', 5, { cell });
                add({ x: cx, y: cy }, 'GeometricCenter', 5, { cell, label: 'Geometric center' });
            }
            if (value.entity === 'node') {
                // Engineering PORT snap is exclusive to Wire. Drafting tools may use
                // the equipment insertion point, but must never imply an electrical connection.
                if (toolUsesGeometricOsnap()) add({ x: cx, y: cy }, 'GeometricCenter', 6, { cell, label: 'Equipment insertion point' });
                if (toolUsesEngineeringPortSnap() && osnapModeEnabled('Port')) {
                    portCoordinates(value, geometry).forEach((point) => candidates.push({ ...point, cell }));
                }
            }
        });

        const segments = lineSegments();
        for (let i = 0; i < segments.length; i += 1) {
            for (let j = i + 1; j < segments.length; j += 1) {
                if (segments[i].cell === segments[j].cell) continue;
                const point = segmentIntersection(segments[i].a, segments[i].b, segments[j].a, segments[j].b);
                if (point) add(point, 'Intersection', 1);
            }
        }
        return candidates;
    }

    function staticSnapPointsForCell(cell) {
        const value = cellValue(cell);
        const geometry = cell?.getGeometry?.();
        if (!value || !geometry || cell?.isVisible?.() === false) return [];
        const points = [];
        const add = (point, type, label = type) => {
            if (!osnapModeEnabled(type)) return;
            points.push({ x: point.x, y: point.y, type, label, cell });
        };

        if (cell.isEdge?.() && isDraftingLineValue(value)) {
            const a = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
            const b = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
            if (a && b) {
                add(a, 'Endpoint');
                add(b, 'Endpoint');
                add({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, 'Midpoint');
            }
            return points;
        }
        if (cell.isEdge?.() && value.entity === 'edge') {
            const route = edgeFullRoutePoints(cell);
            if (route.length >= 2) {
                add(route[0], 'Endpoint', 'Wire endpoint');
                add(route.at(-1), 'Endpoint', 'Wire endpoint');
                route.slice(1).forEach((point, index) => {
                    const a = route[index];
                    add({ x: (a.x + point.x) / 2, y: (a.y + point.y) / 2 }, 'Midpoint', 'Wire midpoint');
                });
            }
            return points;
        }

        if (!cell.isVertex?.()) return points;
        const x1 = geometry.x;
        const y1 = geometry.y;
        const x2 = geometry.x + geometry.width;
        const y2 = geometry.y + geometry.height;
        const cx = (x1 + x2) / 2;
        const cy = (y1 + y2) / 2;

        if (value.entity === 'shape' && value.shape_type === 'rectangle') {
            add({ x: x1, y: y1 }, 'Endpoint');
            add({ x: x2, y: y1 }, 'Endpoint');
            add({ x: x2, y: y2 }, 'Endpoint');
            add({ x: x1, y: y2 }, 'Endpoint');
            add({ x: cx, y: y1 }, 'Midpoint');
            add({ x: x2, y: cy }, 'Midpoint');
            add({ x: cx, y: y2 }, 'Midpoint');
            add({ x: x1, y: cy }, 'Midpoint');
            add({ x: cx, y: cy }, 'GeometricCenter', 'Geometric center');
            return points;
        }

        if (value.entity === 'shape' && value.shape_type === 'circle') {
            add({ x: cx, y: cy }, 'Center');
            add({ x: cx, y: y1 }, 'Quadrant');
            add({ x: x2, y: cy }, 'Quadrant');
            add({ x: cx, y: y2 }, 'Quadrant');
            add({ x: x1, y: cy }, 'Quadrant');
            return points;
        }

        if (value.entity === 'shape' && value.shape_type === 'arc') {
            const rx = geometry.width / 2;
            const ry = geometry.height / 2;
            const screenBasis = value.angle_basis === 'screen';
            const pointAt = (angle) => {
                const radians = finite(angle, 0) * Math.PI / 180;
                return { x: cx + Math.cos(radians) * rx, y: cy + (screenBasis ? 1 : -1) * Math.sin(radians) * ry };
            };
            const sweep = Number(value.sweep_flag) === 0 ? 0 : 1;
            const start = normalizeAngle(value.start_angle);
            const end = normalizeAngle(value.end_angle);
            const span = sweep ? normalizeAngle(end - start) : normalizeAngle(start - end);
            const mid = sweep ? normalizeAngle(start + span / 2) : normalizeAngle(start - span / 2);
            add(pointAt(start), 'Endpoint');
            add(pointAt(end), 'Endpoint');
            add(pointAt(mid), 'Midpoint');
            add({ x: cx, y: cy }, 'Center');
            return points;
        }

        if (value.entity === 'node') {
            if (toolUsesEngineeringPortSnap() && osnapModeEnabled('Port')) {
                portCoordinates(value, geometry).forEach((point) => points.push({ ...point, cell }));
            }
            if (toolUsesGeometricOsnap()) add({ x: cx, y: cy }, 'GeometricCenter', 'Insertion point');
        }
        return points;
    }

    function snapGlyph(point, type, passive = false, state = '') {
        const size = passive ? 3 : (type === 'Port' ? 5 : 4.5);
        const cls = `is-${String(type || 'nearest').replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()}${passive ? ' is-passive' : ' is-active'}${state ? ` is-${state}` : ''}`;
        const halo = passive ? '' : `<circle class="nxsld-osnap-halo" cx="${point.x}" cy="${point.y}" r="${size + 6}"/>`;
        if (type === 'Endpoint') return `<g class="${cls}">${halo}<rect x="${point.x - size}" y="${point.y - size}" width="${size * 2}" height="${size * 2}" rx="1"/></g>`;
        if (type === 'Midpoint') return `<g class="${cls}">${halo}<path d="M${point.x} ${point.y - size} ${point.x + size} ${point.y + size - 1} ${point.x - size} ${point.y + size - 1}Z"/></g>`;
        if (type === 'Center') return `<g class="${cls}">${halo}<circle cx="${point.x}" cy="${point.y}" r="${size}"/><path d="M${point.x - size - 5} ${point.y}H${point.x + size + 5}M${point.x} ${point.y - size - 5}V${point.y + size + 5}"/></g>`;
        if (type === 'GeometricCenter') return `<g class="${cls}">${halo}<circle cx="${point.x}" cy="${point.y}" r="${size}"/><path d="M${point.x - size - 3} ${point.y}H${point.x + size + 3}M${point.x} ${point.y - size - 3}V${point.y + size + 3}M${point.x - size + 1} ${point.y - size + 1}L${point.x + size - 1} ${point.y + size - 1}M${point.x + size - 1} ${point.y - size + 1}L${point.x - size + 1} ${point.y + size - 1}"/></g>`;
        if (type === 'Quadrant') return `<g class="${cls}">${halo}<path d="M${point.x} ${point.y - size} ${point.x + size} ${point.y} ${point.x} ${point.y + size} ${point.x - size} ${point.y}Z"/></g>`;
        if (type === 'Intersection') return `<g class="${cls}">${halo}<path d="M${point.x - size} ${point.y - size}L${point.x + size} ${point.y + size}M${point.x + size} ${point.y - size}L${point.x - size} ${point.y + size}"/></g>`;
        if (type === 'Port') return `<g class="${cls}">${halo}<circle cx="${point.x}" cy="${point.y}" r="${size}"/><circle cx="${point.x}" cy="${point.y}" r="3"/><path d="M${point.x - size - 5} ${point.y}H${point.x + size + 5}M${point.x} ${point.y - size - 5}V${point.y + size + 5}"/></g>`;
        return `<g class="${cls}">${halo}<path d="M${point.x - size} ${point.y + 5}h${size - 1}l5-${size + 3}h${size - 1}"/></g>`;
    }

    function osnapInteractionActive() {
        return ['line', 'polyline', 'rectangle', 'circle', 'arc', 'wire', 'leader', 'text', 'place_symbol'].includes(activeTool);
    }

    function renderSelectedSnapPoints() {
        if (!els.selectedSnapPoints) return;
        // APP67J.2.4: passive candidate clouds compete with selection grips and
        // obscure the drawing. Only the single resolved activeSnap is rendered.
        els.selectedSnapPoints.innerHTML = '';
    }

    function geometryCellAtPoint(point) {
        const direct = graph.getCellAt?.(point.x, point.y);
        if (direct && direct !== graph.getDefaultParent()) return direct;
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        for (let index = children.length - 1; index >= 0; index -= 1) {
            const cell = children[index];
            if (cell?.isVisible?.() === false) continue;
            const geometry = cell?.getGeometry?.();
            if (!geometry) continue;
            if (cell.isVertex?.()) {
                if (point.x >= geometry.x - 3 && point.x <= geometry.x + geometry.width + 3 && point.y >= geometry.y - 3 && point.y <= geometry.y + geometry.height + 3) return cell;
                continue;
            }
            if (cell.isEdge?.()) {
                const a = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
                const b = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
                if (a && b && distance(point, nearestPointOnSegment(point, a, b)) <= 7 / Math.max(graph.getView().scale, 0.01)) return cell;
            }
        }
        return null;
    }

    function wirePortCandidateCapturable(candidate) {
        return Boolean(candidate?.port && candidate?.cell);
    }

    function wirePortCandidateStatus(candidate) {
        if (!wirePortCandidateCapturable(candidate)) {
            return { eligible: false, code: 'invalid-port', label: 'Invalid engineering port' };
        }
        const direction = String(candidate.port.direction || 'bidirectional');
        if (!pendingWireSource) {
            const eligible = direction === 'output' || direction === 'bidirectional';
            return eligible
                ? { eligible: true, code: 'source-valid', label: 'Valid source port' }
                : { eligible: false, code: 'source-direction', label: 'Input-only port cannot start a wire' };
        }
        if (candidate.cell === pendingWireSource) {
            return { eligible: false, code: 'same-equipment', label: 'Choose a different destination equipment' };
        }
        if (direction !== 'input' && direction !== 'bidirectional') {
            return { eligible: false, code: 'target-direction', label: 'Output-only port cannot receive this wire' };
        }
        const sourceNetwork = normalizeWireNetwork(pendingWireSourcePort?.network || '');
        const targetNetwork = normalizeWireNetwork(candidate.port.network || '');
        if (!wireNetworksCompatible(sourceNetwork, targetNetwork)) {
            const sourceDefinition = wireNetworkDefinition(sourceNetwork);
            const targetDefinition = wireNetworkDefinition(targetNetwork);
            return {
                eligible: false,
                code: 'network-mismatch',
                label: `${sourceDefinition.short} is not compatible with ${targetDefinition.short}`,
            };
        }
        return { eligible: true, code: 'target-valid', label: 'Compatible destination port' };
    }

    function wirePortCandidateEligible(candidate) {
        return wirePortCandidateStatus(candidate).eligible;
    }

    function compareWirePortCandidates(a, b) {
        const distanceDelta = Number(a.distance || 0) - Number(b.distance || 0);
        if (Math.abs(distanceDelta) > 0.001) return distanceDelta;
        const eligibilityDelta = Number(!wirePortCandidateEligible(a)) - Number(!wirePortCandidateEligible(b));
        if (eligibilityDelta !== 0) return eligibilityDelta;
        const preferredDirection = pendingWireSource ? 'input' : 'output';
        const directionRank = (candidate) => String(candidate.port?.direction || 'bidirectional') === preferredDirection ? 0 : 1;
        const directionDelta = directionRank(a) - directionRank(b);
        if (directionDelta !== 0) return directionDelta;
        if (pendingWireSource) {
            const sourceNetwork = normalizeWireNetwork(pendingWireSourcePort?.network || '');
            const networkRank = (candidate) => normalizeWireNetwork(candidate.port?.network || '') === sourceNetwork ? 0 : 1;
            const networkDelta = networkRank(a) - networkRank(b);
            if (networkDelta !== 0) return networkDelta;
        }
        const nodeA = String(cellValue(a.cell)?.id || '');
        const nodeB = String(cellValue(b.cell)?.id || '');
        const nodeDelta = nodeA.localeCompare(nodeB);
        if (nodeDelta !== 0) return nodeDelta;
        return String(a.port?.id || '').localeCompare(String(b.port?.id || ''));
    }

    function findObjectSnap(rawPoint) {
        if (!osnapEnabled) return null;
        const allCandidates = snapCandidates(rawPoint)
            .map((candidate) => ({ ...candidate, distance: distance(rawPoint, candidate) }));
        if (activeTool === 'wire' && osnapModeEnabled('Port')) {
            const portCapture = 40 / Math.max(graph.getView().scale, 0.01);
            return allCandidates
                .filter((candidate) => candidate.type === 'Port' && candidate.distance <= portCapture && wirePortCandidateCapturable(candidate))
                .sort(compareWirePortCandidates)[0] || null;
        }
        const scale = Math.max(graph.getView().scale, 0.01);
        const capturePixels = {
            Port: 24,
            Endpoint: 16,
            Midpoint: 16,
            Center: 17,
            GeometricCenter: 17,
            Quadrant: 16,
            Intersection: 18,
            Nearest: 32,
        };
        return allCandidates
            .filter((candidate) => candidate.distance <= (capturePixels[candidate.type] || 18) / scale)
            .sort((a, b) => (a.priority - b.priority) || (a.distance - b.distance))[0] || null;
    }

    function polarConstrain(anchor, point, orthoOverride = null) {
        if (!anchor) return point;
        const dx = point.x - anchor.x;
        const dy = point.y - anchor.y;
        const length = Math.hypot(dx, dy);
        if (length < 0.0001) return point;
        const useOrtho = orthoOverride === null ? orthoEnabled : orthoOverride;
        if (useOrtho) {
            return Math.abs(dx) >= Math.abs(dy) ? { x: point.x, y: anchor.y } : { x: anchor.x, y: point.y };
        }
        if (!polarEnabled) return point;
        const angle = angleDegrees(anchor, point);
        const target = Math.round(angle / 45) * 45;
        const difference = Math.min(Math.abs(angle - target), 360 - Math.abs(angle - target));
        return difference <= 3 ? pointOnAngle(anchor, length, target) : point;
    }

    function resolvedDraftPoint(rawPoint, anchor = null, orthoOverride = null) {
        const snap = findObjectSnap(rawPoint);
        let point = snap ? { x: snap.x, y: snap.y } : gridSnap(rawPoint);
        point = polarConstrain(anchor, point, orthoOverride);
        activeSnap = snap;
        return point;
    }

    function commandLabel(tool = activeTool) {
        const labels = {
            select: 'SELECT', pan: 'PAN', line: 'LINE', polyline: 'PLINE', rectangle: 'RECTANG', circle: 'CIRCLE', arc: 'ARC',
            wire: 'WIRE', text: 'MTEXT', leader: 'MLEADER', place_symbol: 'INSERT',
        };
        return labels[tool] || String(tool || 'SELECT').toUpperCase();
    }

    function circleModeLabel(mode = circleMode) {
        return {
            'center-radius': 'Center, Radius',
            'center-diameter': 'Center, Diameter',
            'two-point': '2-Point Diameter',
            'three-point': '3-Point Circle',
        }[mode] || 'Center, Radius';
    }

    function arcModeLabel(mode = arcMode) {
        return {
            'three-point': '3-Point Arc',
            'center-start-end': 'Center, Start, End',
            'start-center-end': 'Start, Center, End',
            'start-end-radius': 'Start, End, Radius',
        }[mode] || '3-Point Arc';
    }

    function draftPrompt() {
        const count = draftSession?.points?.length || 0;
        if (activeTool === 'select') return 'Ready. Select objects or choose a drafting command.';
        if (activeTool === 'pan') return 'Press and drag to pan. Space or middle mouse provides temporary pan.';
        if (activeTool === 'wire') {
            if (!pendingWireSource) return 'Select an output or bidirectional engineering port.';
            const network = wireNetworkDefinition(pendingWireSourcePort?.network);
            return `Source ${pendingWireSourcePort?.id || 'port'} locked · select compatible ${network.short} destination.`;
        }
        if (activeTool === 'text') return 'Specify insertion point for drawing note.';
        if (activeTool === 'place_symbol') return `Specify insertion point for ${SYMBOL_DEFINITIONS[placementKind]?.title || 'electrical symbol'}.`;
        if (activeTool === 'rectangle') return count === 0 ? 'Specify first corner.' : 'Specify opposite corner.';
        if (activeTool === 'circle') {
            if (circleMode === 'center-radius') return count === 0 ? 'Specify center point.' : 'Specify radius.';
            if (circleMode === 'center-diameter') return count === 0 ? 'Specify center point.' : 'Specify diameter.';
            if (circleMode === 'two-point') return count === 0 ? 'Specify first diameter endpoint.' : 'Specify second diameter endpoint.';
            return count === 0 ? 'Specify first point on circle.' : (count === 1 ? 'Specify second point on circle.' : 'Specify third point on circle.');
        }
        if (activeTool === 'arc') {
            if (arcMode === 'three-point') return count === 0 ? 'Specify arc start point.' : (count === 1 ? 'Specify point on arc.' : 'Specify arc endpoint.');
            if (arcMode === 'center-start-end') return count === 0 ? 'Specify center point.' : (count === 1 ? 'Specify arc start point.' : 'Specify arc endpoint.');
            if (arcMode === 'start-center-end') return count === 0 ? 'Specify arc start point.' : (count === 1 ? 'Specify center point.' : 'Specify arc endpoint.');
            return count === 0 ? 'Specify arc start point.' : (count === 1 ? 'Specify arc endpoint.' : 'Specify radius and side.');
        }
        if (activeTool === 'leader') return draftSession ? 'Specify leader landing point.' : 'Specify arrowhead point.';
        if (activeTool === 'line' || activeTool === 'polyline') return draftSession ? 'Specify next point or [Undo/Close].' : 'Specify first point.';
        return 'Specify point.';
    }

    function updateCommandUi(prompt = null) {
        if (els.commandName) els.commandName.textContent = commandLabel();
        if (els.commandPrompt) els.commandPrompt.textContent = prompt || draftPrompt();
    }

    function compactDynamicPrompt() {
        if (activeTool === 'line' || activeTool === 'polyline') return 'Next point';
        if (activeTool === 'leader') return draftSession ? 'Landing point' : 'Arrow point';
        if (activeTool === 'rectangle') return 'Opposite corner';
        if (activeTool === 'circle') return circleMode === 'three-point' ? `Circle point ${(draftSession?.points?.length || 0) + 1}` : (circleMode === 'center-diameter' ? 'Diameter' : 'Radius');
        if (activeTool === 'arc') return `Arc point ${(draftSession?.points?.length || 0) + 1}`;
        if (activeTool === 'text') return 'Note point';
        if (activeTool === 'place_symbol') return 'Insert point';
        return 'Point';
    }

    function shouldShowDynamicInput() {
        if (!dynamicEnabled || activeTool === 'select' || activeTool === 'pan' || activeTool === 'wire') return false;
        const hasAnchor = Array.isArray(draftSession?.points) && draftSession.points.length > 0;
        const hasTypedValue = dynamicPrimary !== '' || dynamicSecondary !== '';
        return hasAnchor || hasTypedValue;
    }

    function positionDynamicInput(screen) {
        if (!els.dynamicInput || els.dynamicInput.hidden) return;
        const frameWidth = Math.max(280, els.canvasFrame?.clientWidth || container.clientWidth || 800);
        const frameHeight = Math.max(180, els.canvasFrame?.clientHeight || container.clientHeight || 600);
        window.requestAnimationFrame(() => {
            if (!els.dynamicInput || els.dynamicInput.hidden) return;
            const width = Math.min(els.dynamicInput.offsetWidth || 260, frameWidth - 20);
            const height = Math.min(els.dynamicInput.offsetHeight || 28, frameHeight - 20);
            const gapX = activeSnap ? 30 : 18;
            const gapY = activeSnap ? 26 : 16;
            let left = screen.x + gapX;
            let top = screen.y + gapY;
            if (left + width > frameWidth - 10) left = screen.x - width - gapX;
            if (top + height > frameHeight - 10) top = screen.y - height - gapY;
            els.dynamicInput.style.left = `${clamp(left, 10, frameWidth - width - 10)}px`;
            els.dynamicInput.style.top = `${clamp(top, 10, frameHeight - height - 10)}px`;
        });
    }

    function dynamicMetrics(anchor, point) {
        if (!point) return { primary: '0.000', secondary: '' };
        if (!anchor) {
            return {
                primary: dynamicPrimary || `X ${point.x.toFixed(3)}`,
                secondary: dynamicSecondary || `Y ${point.y.toFixed(3)}`,
            };
        }
        const dx = point.x - anchor.x;
        const dy = point.y - anchor.y;
        if (activeTool === 'rectangle') {
            return {
                primary: dynamicPrimary || `W ${Math.abs(dx).toFixed(3)}`,
                secondary: dynamicSecondary || `H ${Math.abs(dy).toFixed(3)}`,
            };
        }
        if (activeTool === 'circle') {
            if (circleMode === 'two-point') {
                const diameter = distance(anchor, point);
                return { primary: dynamicPrimary || `D ${diameter.toFixed(3)}`, secondary: dynamicSecondary || `R ${(diameter / 2).toFixed(3)}` };
            }
            if (circleMode === 'three-point' && draftSession?.points?.length >= 2) {
                const circle = circleThroughThreePoints(draftSession.points[0], draftSession.points[1], point);
                return circle ? { primary: dynamicPrimary || `R ${circle.radius.toFixed(3)}`, secondary: dynamicSecondary || `Ø ${(circle.radius * 2).toFixed(3)}` } : { primary: 'COLLINEAR', secondary: '' };
            }
            const radius = distance(anchor, point);
            if (circleMode === 'center-diameter') return { primary: dynamicPrimary || `D ${(radius * 2).toFixed(3)}`, secondary: dynamicSecondary || `R ${radius.toFixed(3)}` };
            return { primary: dynamicPrimary || `R ${radius.toFixed(3)}`, secondary: dynamicSecondary || `Ø ${(radius * 2).toFixed(3)}` };
        }
        if (activeTool === 'arc' && draftSession?.points?.length >= 2) {
            let spec = null;
            if (arcMode === 'three-point') spec = arcFromThreePoints(draftSession.points[0], draftSession.points[1], point);
            if (arcMode === 'center-start-end') spec = arcFromCenterStartEnd(draftSession.points[0], draftSession.points[1], point);
            if (arcMode === 'start-center-end') spec = arcFromCenterStartEnd(draftSession.points[1], draftSession.points[0], point);
            if (arcMode === 'start-end-radius') spec = arcFromThreePoints(draftSession.points[0], point, draftSession.points[1]);
            if (!spec) return { primary: 'INVALID ARC', secondary: '' };
            const delta = spec.sweep_flag ? normalizeAngle(spec.end_angle - spec.start_angle) : normalizeAngle(spec.start_angle - spec.end_angle);
            return { primary: dynamicPrimary || `R ${spec.radius.toFixed(3)}`, secondary: dynamicSecondary || `${delta.toFixed(2)}°` };
        }
        return {
            primary: dynamicPrimary || distance(anchor, point).toFixed(3),
            secondary: dynamicSecondary || `${angleDegrees(anchor, point).toFixed(2)}°`,
        };
    }

    function setSnapMarker(candidate) {
        if (!els.snapMarker || !els.snapTooltip) return;
        const isPort = candidate?.type === 'Port' && candidate?.port;
        if (!candidate || !osnapEnabled || activeSpace !== 'model' || !osnapInteractionActive() || (isPort && !toolUsesEngineeringPortSnap())) {
            els.snapMarker.innerHTML = '';
            els.snapTooltip.hidden = true;
            return;
        }
        const point = graphToFrameScreen(candidate);
        const shortLabels = {
            Endpoint: 'END', Midpoint: 'MID', Center: 'CEN', GeometricCenter: 'GEO',
            Quadrant: 'QUAD', Intersection: 'INT', Port: 'PORT', Nearest: 'NEAR',
        };
        const portStatus = isPort ? wirePortCandidateStatus(candidate) : null;
        const short = shortLabels[candidate.type] || String(candidate.type || 'SNAP').toUpperCase();
        const tokenWidth = Math.max(26, short.length * 5.4 + 8);
        const markerState = isPort && portStatus && !portStatus.eligible ? 'invalid' : '';
        els.snapMarker.innerHTML = `${snapGlyph(point, candidate.type, false, markerState)}<g class="nxsld-osnap-token${markerState ? ' is-invalid' : ''}" transform="translate(${point.x + 9} ${point.y + 8})"><rect width="${tokenWidth}" height="14" rx="3"/><text x="${tokenWidth / 2}" y="9.5" text-anchor="middle">${short}</text></g>`;
        els.snapTooltip.classList.toggle('is-port-tooltip', Boolean(isPort));
        els.snapTooltip.classList.toggle('is-port-invalid', Boolean(isPort && portStatus && !portStatus.eligible));
        els.snapTooltip.classList.toggle('is-osnap-tooltip', false);
        if (!isPort) {
            // Geometric OSNAP uses only the compact marker token. The engineering
            // information card is exclusive to Wire and canonical PORT targets.
            els.snapTooltip.hidden = true;
            els.snapTooltip.innerHTML = '';
            return;
        }
        const network = wireNetworkDefinition(candidate.port.network);
        const direction = String(candidate.port.direction || 'bidirectional').replaceAll('_', ' ');
        const equipment = cellValue(candidate.cell) || {};
        const statusLabel = portStatus?.eligible ? '' : `<em>${escapeHtml(portStatus?.label || 'Incompatible port')}</em>`;
        els.snapTooltip.hidden = false;
        els.snapTooltip.innerHTML = `<span><strong>PORT · ${escapeHtml(candidate.port.id)}</strong><b style="--nxsld-tooltip-tone:${network.color}">${escapeHtml(network.short)}</b></span><small>${escapeHtml(equipment.tag || equipment.title || 'Equipment')} · ${escapeHtml(direction)} · ${escapeHtml(candidate.port.label || 'engineering terminal')}</small>${statusLabel}`;
        const tooltipWidth = 220;
        const tooltipHeight = 48;
        const frameWidth = Math.max(280, els.canvasFrame?.clientWidth || container.clientWidth || 800);
        const frameHeight = Math.max(180, els.canvasFrame?.clientHeight || container.clientHeight || 600);
        let left = point.x + 16;
        let top = point.y - tooltipHeight - 14;
        if (left + tooltipWidth > frameWidth - 12) left = point.x - tooltipWidth - 16;
        if (top < 10) top = point.y + 16;
        els.snapTooltip.style.left = `${clamp(left, 10, frameWidth - tooltipWidth - 10)}px`;
        els.snapTooltip.style.top = `${clamp(top, 10, frameHeight - tooltipHeight - 10)}px`;
    }

    function portPointForCell(cell, preferredPortId = null, toward = null) {
        if (!cell) return null;
        const value = cellValue(cell) || {};
        const geometry = cell.getGeometry?.();
        if (!geometry) return null;
        const points = portCoordinates(value, geometry);
        if (preferredPortId) {
            const exact = points.find((point) => point.port?.id === preferredPortId);
            if (exact) return exact;
        }
        if (toward && points.length > 0) {
            return [...points].sort((a, b) => distance(a, toward) - distance(b, toward))[0];
        }
        return points[0] || {
            x: geometry.x + geometry.width / 2,
            y: geometry.y + geometry.height / 2,
            type: 'Port',
            label: 'Connection point',
        };
    }

    function wirePreviewGeometry(targetPoint) {
        if (!pendingWireSource || !targetPoint) return null;
        const sourceValue = cellValue(pendingWireSource) || {};
        const targetCell = activeSnap?.cell && activeSnap.cell !== pendingWireSource && cellValue(activeSnap.cell)?.entity === 'node'
            ? activeSnap.cell
            : null;
        const preferredTargetPort = targetCell && activeSnap?.cell === targetCell ? activeSnap?.port?.id || null : null;
        const pair = targetCell
            ? compatiblePortPair(sourceValue, cellValue(targetCell) || {}, pendingWireSourcePort?.id || null, preferredTargetPort)
            : null;
        const source = portDescriptorForCell(pendingWireSource, pair?.sourcePort?.id || pendingWireSourcePort?.id || null, targetPoint);
        const target = targetCell
            ? portDescriptorForCell(targetCell, pair?.targetPort?.id || preferredTargetPort, source)
            : { x: targetPoint.x, y: targetPoint.y, side: Math.abs(targetPoint.x - source.x) >= Math.abs(targetPoint.y - source.y) ? (targetPoint.x >= source.x ? 'left' : 'right') : (targetPoint.y >= source.y ? 'top' : 'bottom') };
        if (!source || !target) return null;
        const routeResult = smartRouteForTerminals(
            source,
            { ...target, value: cellValue(targetCell) || { id: '__wire_preview__' }, cell: targetCell },
            pair?.network || pendingWireSourcePort?.network || 'unclassified',
            null,
            { routing_clearance: 'standard' },
        );
        const route = routeResult.fullPoints?.length ? routeResult.fullPoints : compressOrthogonalRoute([{ x: source.x, y: source.y }, { x: target.x, y: target.y }]);
        return { sourcePoint: source, destination: target, pair, targetCell, route, routeResult };
    }

    function updateDraftOverlay() {
        if (!els.geometryPreview || !els.trackingGuides) return;
        if (activeSpace !== 'model' || !osnapInteractionActive()) {
            els.geometryPreview.setAttribute('d', '');
            els.trackingGuides.innerHTML = '';
            if (els.dynamicInput) els.dynamicInput.hidden = true;
            activeSnap = null;
            setSnapMarker(null);
            return;
        }
        const anchor = draftSession?.points?.[draftSession.points.length - 1] || null;
        const point = resolvedDraftPoint(pointerPoint, anchor, activeTool === 'wire' ? true : null);
        const screen = graphToFrameScreen(point);
        let metricAnchor = anchor;
        let anchorScreen = anchor ? graphToFrameScreen(anchor) : null;
        let path = '';
        const guides = [];

        if (draftSession?.points?.length) {
            const committed = draftSession.points.map((draftPoint, index) => {
                const marker = graphToFrameScreen(draftPoint);
                const label = index === 0 ? '1' : String(index + 1);
                return `<g transform="translate(${marker.x} ${marker.y})"><rect x="-4" y="-4" width="8" height="8"/><text x="8" y="-8">${label}</text></g>`;
            }).join('');
            guides.push(`<g class="nxsld-committed-points">${committed}</g>`);
        }

        if (activeTool === 'wire' && pendingWireSource) {
            const preview = wirePreviewGeometry(point);
            if (preview) {
                metricAnchor = preview.sourcePoint;
                anchorScreen = graphToFrameScreen(preview.sourcePoint);
                const routeScreen = preview.route.map((routePoint) => graphToFrameScreen(routePoint));
                path = routeScreen.map((routePoint, index) => `${index === 0 ? 'M' : 'L'}${routePoint.x} ${routePoint.y}`).join('');
                const destinationScreen = routeScreen.at(-1);
                guides.push(`<path d="M${anchorScreen.x} ${anchorScreen.y}L${destinationScreen.x} ${destinationScreen.y}"/>`);
                const network = wireNetworkDefinition(preview.pair?.network || pendingWireSourcePort?.network);
                guides.push(`<g class="nxsld-wire-preview-badge" transform="translate(${destinationScreen.x + 16} ${destinationScreen.y - 20})"><rect x="0" y="0" width="82" height="20" rx="3"/><circle cx="11" cy="10" r="3" style="fill:${network.color}"/><text x="20" y="13">${escapeHtml(network.short)} SMART ROUTE</text></g>`);
            }
        } else if (anchorScreen) {
            guides.push(`<path d="M${anchorScreen.x} ${anchorScreen.y}L${screen.x} ${screen.y}"/>`);
            if (otrackEnabled) {
                guides.push(`<path d="M0 ${screen.y}H${screen.x}"/>`);
                guides.push(`<path d="M${screen.x} 0V${screen.y}"/>`);
            }
        }

        if (['line', 'polyline', 'leader'].includes(activeTool) && anchorScreen) {
            path = `M${anchorScreen.x} ${anchorScreen.y}L${screen.x} ${screen.y}`;
            guides.push(`<g class="nxsld-draft-grips"><rect x="${anchorScreen.x - 3}" y="${anchorScreen.y - 3}" width="6" height="6"/><rect x="${screen.x - 3}" y="${screen.y - 3}" width="6" height="6"/></g>`);
        }
        if (activeTool === 'rectangle' && anchorScreen) {
            path = `M${anchorScreen.x} ${anchorScreen.y}H${screen.x}V${screen.y}H${anchorScreen.x}Z`;
            guides.push(`<g class="nxsld-draft-grips"><rect x="${anchorScreen.x - 3}" y="${anchorScreen.y - 3}" width="6" height="6"/><rect x="${screen.x - 3}" y="${screen.y - 3}" width="6" height="6"/></g>`);
        }
        if (activeTool === 'circle' && draftSession?.points?.length) {
            let center = null;
            let radius = 0;
            if (circleMode === 'two-point') {
                const a = draftSession.points[0];
                center = { x: (a.x + point.x) / 2, y: (a.y + point.y) / 2 };
                radius = distance(a, point) / 2;
            } else if (circleMode === 'three-point' && draftSession.points.length >= 2) {
                const circle = circleThroughThreePoints(draftSession.points[0], draftSession.points[1], point);
                center = circle?.center || null;
                radius = circle?.radius || 0;
            } else {
                center = draftSession.points[0];
                radius = distance(center, point);
            }
            if (center && radius > 0) {
                const c = graphToFrameScreen(center);
                const scale = Math.max(graph.getView().scale, 0.001);
                const r = radius * scale;
                path = `M${c.x - r} ${c.y}a${r} ${r} 0 1 0 ${r * 2} 0a${r} ${r} 0 1 0 ${-r * 2} 0`;
                guides.push(`<path d="M${c.x} ${c.y}L${screen.x} ${screen.y}"/>`);
                guides.push(`<g class="nxsld-draft-grips"><circle cx="${c.x}" cy="${c.y}" r="3.5"/><rect x="${screen.x - 3}" y="${screen.y - 3}" width="6" height="6"/></g>`);
                metricAnchor = center;
            }
        }
        if (activeTool === 'arc' && draftSession?.points?.length >= 2) {
            let spec = null;
            if (arcMode === 'three-point') spec = arcFromThreePoints(draftSession.points[0], draftSession.points[1], point);
            if (arcMode === 'center-start-end') spec = arcFromCenterStartEnd(draftSession.points[0], draftSession.points[1], point);
            if (arcMode === 'start-center-end') spec = arcFromCenterStartEnd(draftSession.points[1], draftSession.points[0], point);
            if (arcMode === 'start-end-radius') spec = arcFromThreePoints(draftSession.points[0], point, draftSession.points[1]);
            if (!spec) {
                const first = graphToFrameScreen(draftSession.points[0]);
                const second = graphToFrameScreen(draftSession.points[1]);
                guides.push(`<path class="nxsld-arc-construction" d="M${first.x} ${first.y}L${second.x} ${second.y}L${screen.x} ${screen.y}"/>`);
            }
            if (spec) {
                const c = graphToFrameScreen(spec.center);
                const startPoint = {
                    x: spec.center.x + spec.radius * Math.cos(spec.start_angle * Math.PI / 180),
                    y: spec.center.y + spec.radius * Math.sin(spec.start_angle * Math.PI / 180),
                };
                const endPoint = {
                    x: spec.center.x + spec.radius * Math.cos(spec.end_angle * Math.PI / 180),
                    y: spec.center.y + spec.radius * Math.sin(spec.end_angle * Math.PI / 180),
                };
                const st = graphToFrameScreen(startPoint);
                const en = graphToFrameScreen(endPoint);
                const r = spec.radius * Math.max(graph.getView().scale, 0.001);
                path = `M${st.x} ${st.y}A${r} ${r} 0 ${spec.large_arc} ${spec.sweep_flag} ${en.x} ${en.y}`;
                guides.push(`<path d="M${c.x} ${c.y}L${st.x} ${st.y}M${c.x} ${c.y}L${en.x} ${en.y}"/>`);
                guides.push(`<g class="nxsld-draft-grips"><circle cx="${c.x}" cy="${c.y}" r="3.5"/><rect x="${st.x - 3}" y="${st.y - 3}" width="6" height="6"/><rect x="${en.x - 3}" y="${en.y - 3}" width="6" height="6"/></g>`);
                metricAnchor = spec.center;
            }
        }
        if (activeTool === 'place_symbol' && placementKind) {
            const scale = Math.max(graph.getView().scale, 0.2);
            const insertionSize = SYMBOL_DEFINITIONS[placementKind]?.size || [144, 120];
            const width = finite(insertionSize[0], 144) * scale;
            const height = finite(insertionSize[1], 120) * scale;
            guides.push(`<g class="nxsld-insertion-ghost"><rect x="${screen.x - width / 2}" y="${screen.y - height / 2}" width="${width}" height="${height}" rx="4"/><path d="M${screen.x - 12} ${screen.y}H${screen.x + 12}M${screen.x} ${screen.y - 12}V${screen.y + 12}"/><text x="${screen.x}" y="${screen.y + height / 2 + 16}">${SYMBOL_DEFINITIONS[placementKind]?.title || 'Electrical symbol'}</text></g>`);
        }
        if (activeTool === 'text') {
            guides.push(`<g class="nxsld-insertion-ghost"><path d="M${screen.x - 12} ${screen.y}H${screen.x + 12}M${screen.x} ${screen.y - 12}V${screen.y + 12}"/><path d="M${screen.x + 14} ${screen.y + 10}h76"/><text x="${screen.x + 17}" y="${screen.y + 6}">Drawing note</text></g>`);
        }

        els.geometryPreview.setAttribute('d', path);
        els.trackingGuides.innerHTML = guides.join('');
        setSnapMarker(activeSnap);
        renderSelectedSnapPoints();

        if (els.dynamicInput) {
            const show = shouldShowDynamicInput();
            els.dynamicInput.hidden = !show;
            if (show) {
                const metrics = dynamicMetrics(metricAnchor, point);
                if (els.dynamicPrompt) els.dynamicPrompt.textContent = compactDynamicPrompt();
                if (els.dynamicPrimary) {
                    els.dynamicPrimary.textContent = metrics.primary;
                    els.dynamicPrimary.classList.toggle('is-editing', dynamicField === 'primary' && dynamicPrimary !== '');
                }
                if (els.dynamicSecondary) {
                    els.dynamicSecondary.textContent = metrics.secondary;
                    els.dynamicSecondary.hidden = metrics.secondary === '';
                    els.dynamicSecondary.classList.toggle('is-editing', dynamicField === 'secondary' && dynamicSecondary !== '');
                }
                positionDynamicInput(screen);
            }
        }
    }

    function clearDynamicEntry() {
        dynamicPrimary = '';
        dynamicSecondary = '';
        dynamicField = 'primary';
    }

    function resetDraftSession() {
        draftSession = null;
        activeSnap = null;
        clearDynamicEntry();
        if (els.geometryPreview) els.geometryPreview.setAttribute('d', '');
        if (els.trackingGuides) els.trackingGuides.innerHTML = '';
        if (els.selectedSnapPoints) els.selectedSnapPoints.innerHTML = '';
        if (els.dynamicInput) els.dynamicInput.hidden = true;
        setSnapMarker(null);
    }

    function finishDraftCommand(stayActive = true) {
        resetDraftSession();
        pendingWireSource = null;
        pendingWireSourcePort = null;
        if (!stayActive) setTool('select');
        else updateCommandUi();
    }

    function addDraftingLine(start, end, options = {}) {
        if (readOnly || distance(start, end) < 0.5) return null;
        let line;
        graph.batchUpdate(() => {
            line = createLineCell({
                id: uid(options.entity === 'leader' ? 'leader' : 'line'),
                entity: options.entity === 'leader' ? 'leader' : 'line',
                x1: start.x,
                y1: start.y,
                x2: end.x,
                y2: end.y,
                label: options.label || '',
                layer: options.layer || (options.entity === 'leader' ? 'SLD-ANNOTATION' : 'SLD-LINEWORK'),
                line_type: options.line_type || 'continuous',
                lineweight: options.lineweight || 1.4,
                group_id: options.group_id || null,
            });
        });
        if (activeTool === 'select' || options.selectAfter === true) graph.setSelectionCell(line);
        else graph.clearSelection?.();
        markDirty();
        refreshUi();
        return line;
    }

    function insertGeometryShape(shape) {
        if (!shape) return null;
        shape.layer = shape.layer || 'SLD-LINEWORK';
        shape.line_type = shape.line_type || 'continuous';
        shape.lineweight = shape.lineweight || 1.4;
        let cell;
        graph.batchUpdate(() => { cell = createShapeCell(shape); });
        graph.setSelectionCell(cell);
        markDirty();
        refreshUi();
        return cell;
    }

    function rectangleShape(first, second) {
        const x = Math.min(first.x, second.x);
        const y = Math.min(first.y, second.y);
        const w = Math.abs(second.x - first.x);
        const h = Math.abs(second.y - first.y);
        if (w < 1 || h < 1) return null;
        return { id: uid('rectangle'), shape_type: 'rectangle', x, y, w, h };
    }

    function circleShape(points) {
        let center = null;
        let radius = 0;
        if (circleMode === 'center-radius' || circleMode === 'center-diameter') {
            center = points[0];
            radius = distance(points[0], points[1]);
        } else if (circleMode === 'two-point') {
            center = { x: (points[0].x + points[1].x) / 2, y: (points[0].y + points[1].y) / 2 };
            radius = distance(points[0], points[1]) / 2;
        } else {
            const circle = circleThroughThreePoints(points[0], points[1], points[2]);
            center = circle?.center || null;
            radius = circle?.radius || 0;
        }
        if (!center || radius < 1) return null;
        return { id: uid('circle'), shape_type: 'circle', x: center.x - radius, y: center.y - radius, w: radius * 2, h: radius * 2 };
    }

    function arcShape(points) {
        let spec = null;
        if (arcMode === 'three-point') spec = arcFromThreePoints(points[0], points[1], points[2]);
        if (arcMode === 'center-start-end') spec = arcFromCenterStartEnd(points[0], points[1], points[2]);
        if (arcMode === 'start-center-end') spec = arcFromCenterStartEnd(points[1], points[0], points[2]);
        if (arcMode === 'start-end-radius') spec = arcFromThreePoints(points[0], points[2], points[1]);
        if (!spec) return null;
        return {
            id: uid('arc'),
            shape_type: 'arc',
            x: spec.center.x - spec.radius,
            y: spec.center.y - spec.radius,
            w: spec.radius * 2,
            h: spec.radius * 2,
            start_angle: spec.start_angle,
            end_angle: spec.end_angle,
            sweep_flag: spec.sweep_flag,
            large_arc: spec.large_arc,
            angle_basis: spec.angle_basis,
        };
    }

    function exactPointFromDynamic(anchor, fallback) {
        if (!anchor || dynamicPrimary === '') return fallback;
        const numeric = (value) => Number(String(value || '').replace(/[^0-9.+\-]/g, ''));
        const primary = numeric(dynamicPrimary);
        if (!Number.isFinite(primary)) return fallback;
        if (activeTool === 'rectangle') {
            const secondary = numeric(dynamicSecondary);
            if (!Number.isFinite(secondary)) return fallback;
            return { x: anchor.x + Math.sign(fallback.x - anchor.x || 1) * Math.abs(primary), y: anchor.y + Math.sign(fallback.y - anchor.y || 1) * Math.abs(secondary) };
        }
        if (activeTool === 'circle' && circleMode === 'center-diameter') {
            const angle = numeric(dynamicSecondary);
            const effectiveAngle = Number.isFinite(angle) ? angle : angleDegrees(anchor, fallback);
            return pointOnAngle(anchor, Math.abs(primary) / 2, effectiveAngle);
        }
        const angle = numeric(dynamicSecondary);
        const effectiveAngle = Number.isFinite(angle) ? angle : angleDegrees(anchor, fallback);
        return pointOnAngle(anchor, Math.abs(primary), effectiveAngle);
    }

    function commitDraftPoint(nativeEvent = null) {
        const anchor = draftSession?.points?.[draftSession.points.length - 1] || null;
        const orthoOverride = activeTool === 'wire' ? true : (nativeEvent?.shiftKey === true ? !orthoEnabled : null);
        let point = resolvedDraftPoint(pointerPoint, anchor, orthoOverride);
        point = exactPointFromDynamic(anchor, point);
        clearDynamicEntry();

        if (activeTool === 'place_symbol') {
            const kind = placementKind;
            placementKind = null;
            if (kind) addManual(kind, point, { selectToolAfter: true });
            return;
        }
        if (activeTool === 'text') {
            addAnnotation(point);
            return;
        }
        if (['line', 'polyline', 'leader'].includes(activeTool)) {
            if (!draftSession) {
                draftSession = { points: [point] };
                updateCommandUi();
                updateDraftOverlay();
                return;
            }
            const start = draftSession.points[draftSession.points.length - 1];
            const leaderGroupId = activeTool === 'leader' ? uid('leader_group') : null;
            const created = addDraftingLine(start, point, {
                entity: activeTool === 'leader' ? 'leader' : 'line',
                group_id: leaderGroupId,
            });
            if (!created) return;
            if (activeTool === 'leader') {
                const annotation = { entity: 'annotation', id: uid('note'), text: 'Leader note', manual: true, group_id: leaderGroupId };
                let note;
                graph.batchUpdate(() => {
                    note = graph.insertVertex({ parent: graph.getDefaultParent(), id: `note:${annotation.id}`, value: annotation, position: [point.x + 12, point.y - 22], size: [220, 54], style: vertexStyle(annotation) });
                });
                graph.setSelectionCell(note);
                openPanel('inspector');
                finishDraftCommand(false);
                window.setTimeout(() => { fields.title?.focus(); fields.title?.select?.(); }, 50);
                return;
            }
            draftSession.points.push(point);
            updateCommandUi();
            updateDraftOverlay();
            return;
        }
        if (activeTool === 'rectangle') {
            if (!draftSession) { draftSession = { points: [point] }; updateCommandUi(); updateDraftOverlay(); return; }
            const created = insertGeometryShape(rectangleShape(draftSession.points[0], point));
            if (!created) { updateCommandUi('Rectangle requires two distinct corners.'); return; }
            finishDraftCommand(false);
            return;
        }
        if (activeTool === 'circle') {
            const required = circleMode === 'three-point' ? 3 : 2;
            if (!draftSession) { draftSession = { points: [point] }; updateCommandUi(); updateDraftOverlay(); return; }
            draftSession.points.push(point);
            if (draftSession.points.length < required) { updateCommandUi(); updateDraftOverlay(); return; }
            const created = insertGeometryShape(circleShape(draftSession.points));
            if (!created) { draftSession.points.pop(); updateCommandUi('Circle points are collinear or too close. Specify another point.'); updateDraftOverlay(); return; }
            finishDraftCommand(false);
            return;
        }
        if (activeTool === 'arc') {
            if (!draftSession) { draftSession = { points: [point] }; updateCommandUi(); updateDraftOverlay(); return; }
            draftSession.points.push(point);
            if (draftSession.points.length < 3) { updateCommandUi(); updateDraftOverlay(); return; }
            const created = insertGeometryShape(arcShape(draftSession.points));
            if (!created) { draftSession.points.pop(); updateCommandUi('Arc points are collinear or invalid. Specify another point.'); updateDraftOverlay(); return; }
            finishDraftCommand(false);
        }
    }

    function syncGridToViewport() {
        const view = graph.getView();
        const minor = Math.max(4, GRID_SIZE * view.scale);
        const major = minor * 5;
        const offsetX = ((view.translate.x * view.scale) % major + major) % major;
        const offsetY = ((view.translate.y * view.scale) % major + major) % major;
        container.style.setProperty('--nxsld-grid-minor-size', `${minor}px`);
        container.style.setProperty('--nxsld-grid-major-size', `${major}px`);
        container.style.setProperty('--nxsld-grid-offset-x', `${offsetX}px`);
        container.style.setProperty('--nxsld-grid-offset-y', `${offsetY}px`);
        updateDraftOverlay();
        renderSelectedSnapPoints();
    }

    function fitDrawing() {
        const fitPlugin = graph.getPlugin('fit');
        fitPlugin?.fit({ border: activeSpace === 'sheet' ? 54 : 72, margin: 12 });
        if (activeSpace === 'model' && graph.getView().scale < 0.82) {
            graph.zoomTo(0.82, true);
        }
        graph.center(true, true, 0.5, activeSpace === 'sheet' ? 0.5 : 0.43);
        syncGridToViewport();
        updateZoomUi();
    }

    function setZoom(scale) {
        graph.zoomTo(clamp(scale, MIN_ZOOM, MAX_ZOOM), true);
        syncGridToViewport();
        updateZoomUi();
    }

    function updateZoomUi() {
        const percentage = Math.round(graph.getView().scale * 100);
        if (els.zoomLabel) els.zoomLabel.textContent = `${percentage}%`;
        if (els.floatingZoom) els.floatingZoom.textContent = `${percentage}%`;
        if (els.zoomRange) els.zoomRange.value = String(clamp(percentage, 20, 250));
    }

    function excludeCells(cells) {
        const sourceCells = cells.filter((cell) => cell.isVertex?.() && cellValue(cell)?.entity === 'node' && cellValue(cell)?.manual !== true);
        if (sourceCells.length === 0) return false;
        graph.batchUpdate(() => {
            sourceCells.forEach((cell) => {
                const value = cellValue(cell);
                excludedNodes.add(String(value.id));
                model.setVisible(cell, false);
            });
        });
        graph.clearSelection?.();
        markDirty();
        refreshUi();
        return true;
    }

    function restoreExcluded(id) {
        const cell = cellsByNodeId.get(id);
        if (!cell) return;
        graph.batchUpdate(() => model.setVisible(cell, true));
        excludedNodes.delete(id);
        graph.setSelectionCell(cell);
        markDirty();
        refreshUi();
        window.setTimeout(fitDrawing, 40);
    }

    function removeSelected() {
        if (readOnly) return;
        const cells = selectedCells();
        if (excludeCells(cells)) return;
        const deletable = cells.filter((cell) => graph.isCellDeletable(cell));
        if (deletable.length === 0) return;
        graph.removeCells(deletable);
        markDirty();
        refreshUi();
    }

    function selectedWire() {
        const cell = selectedCell();
        return cell?.isEdge?.() && cellValue(cell)?.entity === 'edge' ? cell : null;
    }

    function resetSelectedWireRoute() {
        const edge = selectedWire();
        const value = cellValue(edge);
        if (readOnly || !edge || !value || value.manual !== true || value.locked === true) return;
        const next = { ...value, routing_mode: 'auto' };
        graph.batchUpdate(() => {
            model.setValue(edge, next);
            syncSmartWire(edge, true, next);
        });
        markDirty();
        graph.refresh(edge);
        refreshUi();
        updateCommandUi(`${value.wire_id || 'Wire'} rebuilt using smart auto-route.`);
    }

    function updateRoutingStatus() {
        const edges = (graph.getDefaultParent()?.getChildren?.() || []).filter((cell) => cell?.isEdge?.() && cellValue(cell)?.entity === 'edge');
        routeSyncSummary = edges.reduce((summary, edge) => {
            const value = cellValue(edge) || {};
            summary.wires += 1;
            summary.crossings += Number(value.route_crossings || 0);
            summary.conflicts += Number(value.route_clearance_conflicts || 0);
            summary.separationConflicts += Number(value.route_separation_conflicts || 0);
            summary.bends += Number(value.route_bends || 0);
            summary.length += Number(value.route_length || 0);
            summary.fallbacks += value.route_strategy === 'perimeter-fallback' ? 1 : 0;
            const liveOrthogonal = value.routing_mode === 'manual' ? routeIsOrthogonal(edgeFullRoutePoints(edge)) : value.route_orthogonal === true;
            summary.terminalDirectionViolations += value.routing_mode !== 'manual' && value.route_terminal_direction_ok !== true ? 1 : 0;
            summary.orthogonalWires += liveOrthogonal ? 1 : 0;
            return summary;
        }, { wires: 0, crossings: 0, conflicts: 0, separationConflicts: 0, bends: 0, length: 0, fallbacks: 0, terminalDirectionViolations: 0, orthogonalWires: 0 });
        const structuralErrors = routeSyncSummary.terminalDirectionViolations + Math.max(0, routeSyncSummary.wires - routeSyncSummary.orthogonalWires);
        root.dataset.routingVersion = SLD_SMART_ROUTER_VERSION;
        root.dataset.routingContract = SLD_ORTHOGONAL_ROUTING_CONTRACT.name;
        root.dataset.routingStatus = structuralErrors > 0 || routeSyncSummary.conflicts > 0 ? 'conflict' : (routeSyncSummary.crossings > 0 ? 'crossing' : 'clear');
        root.dataset.routingConflicts = String(routeSyncSummary.conflicts);
        root.dataset.routingCrossings = String(routeSyncSummary.crossings);
        root.dataset.routingSeparationConflicts = String(routeSyncSummary.separationConflicts);
        root.dataset.routingTerminalViolations = String(routeSyncSummary.terminalDirectionViolations);
        root.dataset.routingFallbacks = String(routeSyncSummary.fallbacks);
        if (els.routingVersion) els.routingVersion.textContent = `ROUTE ${SLD_SMART_ROUTER_VERSION}`;
        if (els.routingRibbonCount) {
            els.routingRibbonCount.textContent = structuralErrors > 0
                ? `${structuralErrors} ROUTE ERROR${structuralErrors === 1 ? '' : 'S'}`
                : routeSyncSummary.conflicts > 0
                    ? `${routeSyncSummary.conflicts} CONFLICT${routeSyncSummary.conflicts === 1 ? '' : 'S'}`
                    : (routeSyncSummary.crossings > 0 ? `${routeSyncSummary.crossings} CROSSING${routeSyncSummary.crossings === 1 ? '' : 'S'}` : 'CLEAR');
        }
        scheduleVisualRegression();
    }

    function rebuildAllSmartRoutes() {
        if (readOnly) return false;
        const edges = (graph.getDefaultParent()?.getChildren?.() || []).filter((cell) => cell?.isEdge?.() && cellValue(cell)?.entity === 'edge' && cellValue(cell)?.routing_mode !== 'manual');
        graph.batchUpdate(() => syncConnectedWires(null, true));
        markDirty();
        refreshUi();
        updateCommandUi(`${edges.length} orthogonal route${edges.length === 1 ? '' : 's'} rebuilt · ${routeSyncSummary.crossings} crossing${routeSyncSummary.crossings === 1 ? '' : 's'} · ${routeSyncSummary.conflicts} clearance conflict${routeSyncSummary.conflicts === 1 ? '' : 's'} · ${routeSyncSummary.fallbacks} fallback${routeSyncSummary.fallbacks === 1 ? '' : 's'}.`);
        return true;
    }

    function selectWireTerminal(sourceTerminal = true) {
        const edge = selectedWire();
        const terminal = edge?.getTerminal?.(sourceTerminal);
        if (!edge || !terminal) return;
        graph.setSelectionCell(terminal);
        refreshUi();
        updateCommandUi(`${sourceTerminal ? 'Source' : 'Destination'} equipment selected.`);
    }

    function copySelection(announce = true) {
        const cells = selectedCells().filter((cell) => {
            const value = cellValue(cell);
            if (!value || cell.isVisible?.() === false) return false;
            if (cell.isVertex?.()) return ['node', 'annotation', 'shape'].includes(value.entity);
            return cell.isEdge?.() && isDraftingLineValue(value);
        });
        clipboard = cells.map((cell) => ({ value: structuredClone(cellValue(cell)), geometry: cell.getGeometry()?.clone() }));
        clipboardPasteSerial = 0;
        if (announce) updateCommandUi(clipboard.length > 0 ? `${clipboard.length} object${clipboard.length === 1 ? '' : 's'} copied.` : 'No copyable object selected.');
        refreshUi();
        return clipboard.length > 0;
    }

    function pasteSelection() {
        if (readOnly || clipboard.length === 0) return;
        clipboardPasteSerial += 1;
        const inserted = [];
        const offset = 28 * clipboardPasteSerial;
        const pastedGroups = new Map();
        graph.batchUpdate(() => {
            clipboard.forEach((item) => {
                const value = structuredClone(item.value);
                value.locked = false;
                if (value.group_id) {
                    if (!pastedGroups.has(value.group_id)) pastedGroups.set(value.group_id, uid('group'));
                    value.group_id = pastedGroups.get(value.group_id);
                }
                if (isDraftingLineValue(value)) {
                    value.id = uid(value.entity === 'leader' ? 'leader' : 'line');
                    const geometry = item.geometry?.clone() || new Geometry();
                    geometry.translate(offset, offset);
                    const cell = new Cell(value, geometry, draftingLineStyle(value));
                    cell.setId(`line:${slug(value.id)}`);
                    cell.setEdge(true);
                    cell.setConnectable(false);
                    graph.addCell(cell);
                    inserted.push(cell);
                    return;
                }
                if (value.entity === 'node') {
                    value.id = uid(`manual_${slug(value.symbol || value.kind || 'equipment')}`);
                    value.manual = true;
                    value.state = 'manual';
                    value.tag = nextTag(value.symbol || value.kind || 'panel');
                } else if (value.entity === 'annotation') {
                    value.id = uid('note');
                } else if (value.entity === 'shape') {
                    value.id = uid(value.shape_type || 'shape');
                }
                const geometry = item.geometry?.clone() || new Geometry(200, 200, 144, 120);
                geometry.x += offset;
                geometry.y += offset;
                const style = value.entity === 'shape' ? geometryShapeStyle(value) : vertexStyle(value);
                const cell = new Cell(value, geometry, style);
                cell.setId(`${value.entity === 'annotation' ? 'note' : (value.entity === 'shape' ? 'shape' : 'node')}:${slug(value.id)}`);
                cell.setVertex(true);
                cell.setConnectable(value.entity === 'node');
                graph.addCell(cell);
                if (value.entity === 'node') cellsByNodeId.set(value.id, cell);
                inserted.push(cell);
            });
        });
        graph.setSelectionCells(inserted);
        markDirty();
        refreshUi();
        updateCommandUi(`${inserted.length} object${inserted.length === 1 ? '' : 's'} pasted.`);
    }

    function cutSelection() {
        if (readOnly || !copySelection(false)) return;
        const removable = selectedCells().filter((cell) => graph.isCellDeletable(cell));
        if (removable.length > 0) graph.removeCells(removable);
        markDirty();
        refreshUi();
        updateCommandUi(removable.length === clipboard.length ? `${removable.length} object${removable.length === 1 ? '' : 's'} cut.` : `${removable.length} removable object${removable.length === 1 ? '' : 's'} cut. Calculated sources remain in the drawing.`);
    }

    function duplicateSelection() {
        if (!copySelection(false)) return;
        pasteSelection();
    }

    function selectedEditableVertices(minimum = 1) {
        const cells = selectedCells().filter((cell) => cell.isVertex?.() && graph.isCellMovable(cell) && cellValue(cell)?.locked !== true);
        return cells.length >= minimum ? cells : [];
    }

    function selectionGeometryItems(cells) {
        return cells.map((cell) => {
            const geometry = cell.getGeometry?.();
            if (!geometry) return null;
            return {
                id: String(cell.getId?.() || cellValue(cell)?.id || ''),
                x: geometry.x,
                y: geometry.y,
                w: geometry.width,
                h: geometry.height,
            };
        }).filter(Boolean);
    }

    function alignSelection(mode) {
        const cells = selectedEditableVertices(2);
        if (readOnly || cells.length < 2) return;
        const aligned = alignGeometryRectangles(selectionGeometryItems(cells), mode, { gridSize: GRID_SIZE, snap: snapEnabled });
        const byId = new Map(aligned.map((item) => [item.id, item]));
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const next = byId.get(String(cell.getId?.() || cellValue(cell)?.id || ''));
                const geometry = cell.getGeometry?.()?.clone();
                if (!next || !geometry) return;
                geometry.x = next.x;
                geometry.y = next.y;
                model.setGeometry(cell, geometry);
            });
        });
        markDirty();
        refreshUi();
        updateCommandUi(`${cells.length} objects aligned ${mode} using the ${SLD_GEOMETRY_FOUNDATION_VERSION} geometry contract.`);
    }

    function distributeSelection(axis) {
        const cells = selectedEditableVertices(3);
        if (readOnly || cells.length < 3) return;
        const distributed = distributeGeometryRectangles(selectionGeometryItems(cells), axis, { gridSize: GRID_SIZE, snap: snapEnabled });
        const byId = new Map(distributed.items.map((item) => [item.id, item]));
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const next = byId.get(String(cell.getId?.() || cellValue(cell)?.id || ''));
                const geometry = cell.getGeometry?.()?.clone();
                if (!next || !geometry) return;
                geometry.x = next.x;
                geometry.y = next.y;
                model.setGeometry(cell, geometry);
            });
        });
        markDirty();
        refreshUi();
        updateCommandUi(`${cells.length} objects distributed ${axis} · nominal gap ${distributed.gap}.`);
    }

    function setObjectLock(locked) {
        if (readOnly) return;
        const cells = selectedCells().filter((cell) => cellValue(cell));
        if (cells.length === 0) return;
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const value = cellValue(cell);
                model.setValue(cell, { ...value, locked: Boolean(locked) });
            });
        });
        markDirty();
        graph.refresh();
        refreshUi();
        updateCommandUi(`${cells.length} object${cells.length === 1 ? '' : 's'} ${locked ? 'locked' : 'unlocked'}.`);
    }

    function resetSelectionSize() {
        const cells = selectedEditableVertices(1).filter((cell) => ['node', 'annotation'].includes(cellValue(cell)?.entity));
        if (readOnly || cells.length === 0) return;
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const value = cellValue(cell);
                const geometry = cell.getGeometry()?.clone();
                if (!geometry) return;
                if (value.entity === 'annotation') {
                    geometry.width = 300;
                    geometry.height = 64;
                } else {
                    const definition = SYMBOL_DEFINITIONS[value.symbol || value.kind] || SYMBOL_DEFINITIONS.panel;
                    geometry.width = definition.size?.[0] || 144;
                    geometry.height = definition.size?.[1] || 120;
                }
                model.setGeometry(cell, geometry);
            });
        });
        markDirty();
        refreshUi();
        updateCommandUi(`${cells.length} object${cells.length === 1 ? '' : 's'} restored to standard symbol size.`);
    }

    function selectedTransformCells() {
        return selectedCells().filter((cell) => {
            const value = cellValue(cell);
            if (!value || value.locked === true || layerState[objectLayer(value)]?.locked) return false;
            return (cell.isVertex?.() && ['node', 'shape', 'annotation'].includes(value.entity)) || (cell.isEdge?.() && isDraftingLineValue(value));
        });
    }

    function rotatePoint(point, center, deltaDegrees) {
        const radians = deltaDegrees * Math.PI / 180;
        const dx = point.x - center.x;
        const dy = point.y - center.y;
        return {
            x: center.x + dx * Math.cos(radians) - dy * Math.sin(radians),
            y: center.y + dx * Math.sin(radians) + dy * Math.cos(radians),
        };
    }

    function applyRotation(delta) {
        if (readOnly) return;
        const cells = selectedTransformCells();
        if (cells.length === 0) return;
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const value = cellValue(cell);
                const geometry = cell.getGeometry()?.clone();
                if (!value || !geometry) return;
                if (isDraftingLineValue(value)) {
                    const a = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
                    const b = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
                    if (!a || !b) return;
                    const center = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
                    const ra = rotatePoint(a, center, delta);
                    const rb = rotatePoint(b, center, delta);
                    geometry.setTerminalPoint(new Point(graph.snap(ra.x), graph.snap(ra.y)), true);
                    geometry.setTerminalPoint(new Point(graph.snap(rb.x), graph.snap(rb.y)), false);
                    model.setGeometry(cell, geometry);
                    return;
                }
                const nextRotation = normalizeQuarterTurn(finite(value.rotation, 0) + delta);
                if (value.entity === 'node') {
                    const policy = registryTransformPolicy(value.symbol || value.kind);
                    const allowedRotations = Array.isArray(policy.rotations) ? policy.rotations.map(normalizeQuarterTurn) : [0, 90, 180, 270];
                    if (!allowedRotations.includes(nextRotation)) return;
                }
                if (Math.abs(delta) % 180 === 90) {
                    const cx = geometry.x + geometry.width / 2;
                    const cy = geometry.y + geometry.height / 2;
                    const nextWidth = geometry.height;
                    const nextHeight = geometry.width;
                    geometry.x = graph.snap(cx - nextWidth / 2);
                    geometry.y = graph.snap(cy - nextHeight / 2);
                    geometry.width = nextWidth;
                    geometry.height = nextHeight;
                    model.setGeometry(cell, geometry);
                }
                model.setValue(cell, { ...value, rotation: nextRotation });
            });
        });
        markDirty();
        graph.refresh();
        refreshUi();
        updateCommandUi(`${cells.length} object${cells.length === 1 ? '' : 's'} rotated ${delta > 0 ? 'clockwise' : 'counter-clockwise'} 90°.`);
    }

    function applyMirror(axis) {
        if (readOnly) return;
        const cells = selectedTransformCells();
        if (cells.length === 0) return;
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const value = cellValue(cell);
                const geometry = cell.getGeometry()?.clone();
                if (!value || !geometry) return;
                if (isDraftingLineValue(value)) {
                    const a = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
                    const b = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
                    if (!a || !b) return;
                    if (axis === 'horizontal') {
                        const cx = (a.x + b.x) / 2;
                        geometry.setTerminalPoint(new Point(graph.snap(2 * cx - a.x), a.y), true);
                        geometry.setTerminalPoint(new Point(graph.snap(2 * cx - b.x), b.y), false);
                    } else {
                        const cy = (a.y + b.y) / 2;
                        geometry.setTerminalPoint(new Point(a.x, graph.snap(2 * cy - a.y)), true);
                        geometry.setTerminalPoint(new Point(b.x, graph.snap(2 * cy - b.y)), false);
                    }
                    model.setGeometry(cell, geometry);
                    return;
                }
                if (value.entity === 'node') {
                    const policy = registryTransformPolicy(value.symbol || value.kind);
                    const allowed = axis === 'horizontal' ? policy.mirror_horizontal !== false : policy.mirror_vertical !== false;
                    if (!allowed) return;
                }
                const key = axis === 'horizontal' ? 'flip_h' : 'flip_v';
                model.setValue(cell, { ...value, [key]: value[key] !== true });
            });
        });
        markDirty();
        graph.refresh();
        refreshUi();
        updateCommandUi(`${cells.length} object${cells.length === 1 ? '' : 's'} mirrored ${axis}.`);
    }

    function resetSelectionTransform() {
        if (readOnly) return;
        const cells = selectedTransformCells().filter((cell) => !isDraftingLineValue(cellValue(cell)));
        if (cells.length === 0) {
            updateCommandUi('Reset transform is available for equipment, geometry, and annotation objects.');
            return;
        }
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const value = cellValue(cell);
                const geometry = cell.getGeometry()?.clone();
                if (!value || !geometry) return;
                const rotation = normalizeQuarterTurn(value.rotation);
                if (rotation % 180 === 90) {
                    const cx = geometry.x + geometry.width / 2;
                    const cy = geometry.y + geometry.height / 2;
                    const nextWidth = geometry.height;
                    const nextHeight = geometry.width;
                    geometry.x = graph.snap(cx - nextWidth / 2);
                    geometry.y = graph.snap(cy - nextHeight / 2);
                    geometry.width = nextWidth;
                    geometry.height = nextHeight;
                    model.setGeometry(cell, geometry);
                }
                model.setValue(cell, { ...value, rotation: 0, flip_h: false, flip_v: false });
            });
        });
        markDirty();
        graph.refresh();
        refreshUi();
        updateCommandUi(`${cells.length} object transform${cells.length === 1 ? '' : 's'} reset.`);
    }

    function groupSelection() {
        if (readOnly) return;
        const cells = selectedCells().filter((cell) => {
            const value = cellValue(cell);
            if (!value || value.locked === true || layerState[objectLayer(value)]?.locked) return false;
            return (cell.isVertex?.() && ['node', 'shape', 'annotation'].includes(value.entity))
                || (cell.isEdge?.() && isDraftingLineValue(value));
        });
        if (cells.length < 2) return;
        const groupId = uid('group');
        graph.batchUpdate(() => cells.forEach((cell) => model.setValue(cell, { ...cellValue(cell), group_id: groupId })));
        markDirty();
        graph.refresh();
        refreshUi();
        updateCommandUi(`${cells.length} objects grouped as ${groupId.toUpperCase()}.`);
    }

    function ungroupSelection() {
        if (readOnly) return;
        const groupIds = new Set(selectedCells().map((cell) => cellValue(cell)?.group_id).filter(Boolean));
        if (groupIds.size === 0) return;
        const all = graph.getDefaultParent()?.getChildren?.() || [];
        const affected = all.filter((cell) => groupIds.has(cellValue(cell)?.group_id));
        graph.batchUpdate(() => affected.forEach((cell) => {
            const value = cellValue(cell);
            model.setValue(cell, { ...value, group_id: null });
        }));
        markDirty();
        graph.refresh();
        refreshUi();
        updateCommandUi(`${affected.length} grouped object${affected.length === 1 ? '' : 's'} released.`);
    }

    function expandGroupedSelection() {
        if (expandingGroupSelection) return;
        const selected = selectedCells();
        const groupIds = new Set(selected.map((cell) => cellValue(cell)?.group_id).filter(Boolean));
        if (groupIds.size === 0) return;
        const all = graph.getDefaultParent()?.getChildren?.() || [];
        const expanded = all.filter((cell) => {
            const value = cellValue(cell);
            return value && groupIds.has(value.group_id) && cell.isVisible?.() !== false;
        });
        const merged = [...new Set([...selected, ...expanded])];
        if (merged.length === selected.length) return;
        expandingGroupSelection = true;
        graph.setSelectionCells(merged);
        expandingGroupSelection = false;
    }

    function setSymbolVisibility(key) {
        if (readOnly || !['tag', 'rating', 'subtitle', 'ports'].includes(key)) return;
        const nodes = selectedCells().filter((cell) => cell.isVertex?.() && cellValue(cell)?.entity === 'node' && cellValue(cell)?.locked !== true);
        if (nodes.length === 0) return;
        const property = `show_${key}`;
        const nextVisible = nodes.some((cell) => cellValue(cell)?.[property] === false);
        graph.batchUpdate(() => nodes.forEach((cell) => model.setValue(cell, { ...cellValue(cell), [property]: nextVisible })));
        markDirty();
        graph.refresh();
        refreshUi();
        updateCommandUi(`${key.replace('_', ' ')} ${nextVisible ? 'shown' : 'hidden'} for ${nodes.length} symbol${nodes.length === 1 ? '' : 's'}.`);
    }

    function nudgeSelection(dx, dy) {
        const cells = selectedEditableVertices(1);
        if (readOnly || cells.length === 0) return;
        graph.batchUpdate(() => {
            cells.forEach((cell) => {
                const geometry = cell.getGeometry()?.clone();
                if (!geometry) return;
                geometry.translate(dx, dy);
                model.setGeometry(cell, geometry);
            });
        });
        markDirty();
        refreshUi();
    }

    function bringFront() {
        const cells = selectedCells();
        if (readOnly || cells.length === 0) return;
        graph.orderCells(false, cells);
        markDirty();
        refreshUi();
    }

    function sendBack() {
        const cells = selectedCells();
        if (readOnly || cells.length === 0) return;
        graph.orderCells(true, cells);
        markDirty();
        refreshUi();
    }

    function resetGeneratedLayout() {
        if (readOnly) return;
        graph.batchUpdate(() => {
            sourceNodes.forEach((node, index) => {
                const cell = cellsByNodeId.get(String(node.id));
                if (!cell) return;
                const target = defaultPosition(node.id, index, null, node.default_position);
                const geometry = cell.getGeometry()?.clone();
                if (geometry) {
                    geometry.x = target.x;
                    geometry.y = target.y;
                    geometry.width = target.w;
                    geometry.height = target.h;
                    model.setGeometry(cell, geometry);
                }
                model.setVisible(cell, true);
                excludedNodes.delete(String(node.id));
            });
            const children = graph.getDefaultParent()?.getChildren?.() || [];
            children.forEach((cell) => {
                if (!cell.isEdge?.() || cellValue(cell)?.manual === true) return;
                const geometry = cell.getGeometry()?.clone();
                if (geometry) {
                    geometry.points = null;
                    model.setGeometry(cell, geometry);
                }
            });
        });
        markDirty();
        refreshUi();
        window.setTimeout(fitDrawing, 40);
    }

    function updateExcludedList() {
        if (els.excludedCount) els.excludedCount.textContent = String(excludedNodes.size);
        if (!els.excludedList) return;
        if (excludedNodes.size === 0) {
            els.excludedList.innerHTML = '<p>No calculated objects are excluded.</p>';
            return;
        }
        els.excludedList.innerHTML = [...excludedNodes].map((id) => {
            const value = cellValue(cellsByNodeId.get(id)) || {};
            return `<button type="button" data-editor-restore="${escapeHtml(id)}"><span><strong>${escapeHtml(value.title || 'Calculated object')}</strong><small>${escapeHtml(value.tag || 'Calculation source')}</small></span><b>Restore</b></button>`;
        }).join('');
        els.excludedList.querySelectorAll('[data-editor-restore]').forEach((button) => button.addEventListener('click', () => restoreExcluded(button.dataset.editorRestore)));
    }

    function updateInspector() {
        const cells = selectedCells();
        const cell = inspectorCell();
        const value = cellValue(cell);
        const geometry = cell?.getGeometry?.();

        if (!cell || !value) {
            if (els.wirePanel) els.wirePanel.hidden = true;
            if (els.authorityHelp) els.authorityHelp.hidden = true;
            els.nonWireSections?.forEach((section) => { section.hidden = false; });
            const defaults = { title: 'Equipment name', tag: 'Equipment tag <em>optional</em>', subtitle: 'Description', rating: 'Main rating', x: 'Position X', y: 'Position Y', w: 'Width', h: 'Height', rotation: 'Rotation' };
            Object.entries(defaults).forEach(([key, copy]) => { if (fieldLabels[key]) fieldLabels[key].innerHTML = copy; });
            if (els.inspectorEmpty) els.inspectorEmpty.hidden = false;
            if (els.inspectorForm) els.inspectorForm.hidden = true;
            if (els.inspectorHeading) els.inspectorHeading.textContent = cells.length > 1 ? `${cells.length} objects` : 'No selection';
            if (els.inspectorStandard) els.inspectorStandard.textContent = '—';
            if (els.inspectorPorts) els.inspectorPorts.textContent = '—';
            if (els.selectionStatus) els.selectionStatus.textContent = cells.length > 1 ? `${cells.length} objects selected` : 'No object selected';
            return;
        }

        if (els.inspectorEmpty) els.inspectorEmpty.hidden = true;
        if (els.inspectorForm) els.inspectorForm.hidden = false;
        if (els.inspectorHeading) els.inspectorHeading.textContent = value.title || value.text || value.label || 'Selected object';
        if (els.selectionStatus) {
            const type = isDraftingLineValue(value) ? (value.entity === 'leader' ? 'Leader' : 'Line') : (value.entity === 'shape' ? String(value.shape_type || 'Shape').replaceAll('_', ' ') : (cell.isEdge?.() ? 'Wire' : value.entity === 'annotation' ? 'Annotation' : 'Device'));
            els.selectionStatus.textContent = `${type} selected · ${value.title || value.label || value.text || 'Object'}`;
        }
        if (els.inspectorKind) els.inspectorKind.textContent = isDraftingLineValue(value) ? (value.entity === 'leader' ? 'Associative leader' : 'Drafting line') : (value.entity === 'shape' ? `${String(value.shape_type || 'shape').replaceAll('_', ' ')} geometry` : (cell.isEdge?.() ? 'Orthogonal wire' : (value.kind || value.entity || 'component').replaceAll('_', ' ')));
        if (els.inspectorAuthority) els.inspectorAuthority.textContent = value.manual
            ? `Manual drawing layer${value.locked ? ' · Locked' : ''}`
            : `Calculation authority · Tag and drafting layout editable · Ratings read-only${value.locked ? ' · Locked' : ''}`;
        if (els.inspectorLayer) els.inspectorLayer.textContent = objectLayer(value);
        if (els.inspectorId) els.inspectorId.textContent = value.id || cell.getId();
        if (els.inspectorGroup) els.inspectorGroup.textContent = value.group_id ? `Grouped · ${String(value.group_id).replace('group_', '').slice(-8).toUpperCase()}` : 'Independent object';
        const selectedRegistry = value.entity === 'node' ? registrySymbol(value.symbol || value.id || value.kind) : null;
        const selectedStandard = selectedRegistry?.standard || value.symbol_standard || null;
        const selectedPorts = value.entity === 'node' ? portProfile(value.symbol || value.id || value.kind, value.ports) : [];
        if (els.inspectorStandard) {
            els.inspectorStandard.textContent = selectedStandard
                ? `${selectedStandard.status_label || 'Reference drawing form'}${selectedStandard.source_pages ? ` · p. ${selectedStandard.source_pages}` : ''}`
                : 'Not applicable';
        }
        if (els.inspectorPorts) {
            const networks = [...new Set(selectedPorts.map((port) => wireNetworkDefinition(port.network).short))];
            els.inspectorPorts.textContent = selectedPorts.length > 0 ? `${selectedPorts.length} · ${networks.join(' / ')}` : 'Not applicable';
        }
        const objectTypeLabel = value.entity === 'node' ? 'ELECTRICAL EQUIPMENT' : value.entity === 'shape' ? 'DRAFTING GEOMETRY' : value.entity === 'annotation' ? 'ANNOTATION' : isDraftingLineValue(value) ? (value.entity === 'leader' ? 'ASSOCIATIVE LEADER' : 'DRAFTING LINE') : 'CONNECTED CIRCUIT';
        if (els.profileEyebrow) els.profileEyebrow.textContent = objectTypeLabel;
        if (els.profileTitle) els.profileTitle.textContent = value.title || value.text || value.label || 'Selected object';
        if (els.profileMeta) els.profileMeta.textContent = `${objectLayer(value)} · ${value.manual ? 'Manual authority' : 'Calculation authority'}${value.group_id ? ' · Grouped' : ''}`;
        if (els.profileState) {
            els.profileState.textContent = value.locked ? 'LOCKED' : (value.group_id ? 'GROUPED' : 'EDITABLE');
            els.profileState.className = value.locked ? 'is-locked' : (value.group_id ? 'is-grouped' : '');
        }
        if (els.transformStatus) els.transformStatus.textContent = transformLabel(value);

        const sourceLocked = !value.manual && value.entity === 'node';
        const isEdge = cell.isEdge?.();
        const isLine = isDraftingLineValue(value);
        const isAnnotation = value.entity === 'annotation';
        const isShape = value.entity === 'shape';
        const isWire = cell.isEdge?.() && value.entity === 'edge';
        if (els.authorityHelp) {
            els.authorityHelp.hidden = isWire;
            els.authorityHelp.classList.toggle('is-calculation', sourceLocked);
            const quantityText = Number(value.equipment_count || 0) > 1 ? ` · ${Number(value.equipment_count)} PHYSICAL UNITS` : '';
            const aggregateText = Number(value.aggregate_power || 0) > 0 ? ` Aggregate ${Number(value.aggregate_power).toLocaleString(undefined, { maximumFractionDigits: 2 })} kW.` : '';
            els.authorityHelp.innerHTML = sourceLocked
                ? `<strong>CALCULATION-CONTROLLED EQUIPMENT${escapeHtml(quantityText)}</strong><span>Equipment tag and drafting geometry are editable. Unit ratings, source ports, and engineering descriptions remain controlled by Technical Calculation.${escapeHtml(aggregateText)}</span>`
                : '<strong>DRAWING-CONTROLLED OBJECT</strong><span>Properties below are editable unless the object or its layer is locked.</span>';
        }
        if (els.wirePanel) els.wirePanel.hidden = !isWire;
        els.nonWireSections?.forEach((section) => { section.hidden = isWire; });

        if (isWire) {
            const sourceCell = cell.getTerminal?.(true);
            const targetCell = cell.getTerminal?.(false);
            const sourceValue = cellValue(sourceCell) || {};
            const targetValue = cellValue(targetCell) || {};
            const sourcePort = portProfile(sourceValue.symbol || sourceValue.kind, sourceValue.ports).find((port) => port.id === value.from_port);
            const targetPort = portProfile(targetValue.symbol || targetValue.kind, targetValue.ports).find((port) => port.id === value.to_port);
            const network = normalizeWireNetwork(value.network);
            const definition = wireNetworkDefinition(network);
            if (els.wireSource) els.wireSource.textContent = `${sourceValue.tag || sourceValue.title || 'Source'} · ${value.from_port || 'AUTO'}${sourcePort?.label ? ` · ${sourcePort.label}` : ''}`;
            if (els.wireTarget) els.wireTarget.textContent = `${targetValue.tag || targetValue.title || 'Destination'} · ${value.to_port || 'AUTO'}${targetPort?.label ? ` · ${targetPort.label}` : ''}`;
            if (els.wireNetworkBadge) {
                els.wireNetworkBadge.textContent = definition.short;
                els.wireNetworkBadge.style.setProperty('--nxsld-wire-tone', definition.color);
            }
            if (els.wireRouteState) els.wireRouteState.textContent = value.routing_mode === 'manual' ? 'MANUAL ROUTE' : 'OBSTACLE-AWARE';
            if (els.wireRouteStrategy) els.wireRouteStrategy.textContent = value.route_strategy === 'perimeter-fallback' ? 'PERIMETER FALLBACK' : 'A* VISIBILITY GRID';
            if (els.wireRouteClearance) els.wireRouteClearance.textContent = routingProfile(value).label.toUpperCase();
            if (els.wireRouteBends) els.wireRouteBends.textContent = String(Number(value.route_bends || 0));
            if (els.wireRouteCrossings) els.wireRouteCrossings.textContent = String(Number(value.route_crossings || 0));
            if (els.wireRouteLength) els.wireRouteLength.textContent = `${Math.round(Number(value.route_length || 0))}`;
            const wireValues = {
                wire_id: value.wire_id || value.id || '',
                network,
                cable_tag: value.cable_tag || '',
                cable_type: value.cable_type || '',
                cable_size: value.cable_size || '',
                cores: value.cores || '',
                voltage_class: value.voltage_class || '',
                installation_method: value.installation_method || '',
                routing_mode: value.routing_mode === 'manual' ? 'manual' : 'auto',
                routing_clearance: routingProfile(value).key,
                notes: value.notes || '',
            };
            Object.entries(wireFields).forEach(([key, field]) => {
                if (key === 'show_label') {
                    const active = value.show_label !== false;
                    field.classList.toggle('is-active', active);
                    field.setAttribute('aria-pressed', String(active));
                    field.disabled = readOnly || value.locked === true;
                    return;
                }
                if (document.activeElement !== field) field.value = wireValues[key] ?? '';
                const authorityLocked = key === 'network' || value.manual !== true;
                field.disabled = readOnly || value.locked === true || authorityLocked;
                field.closest('label')?.classList.toggle('is-authority-locked', authorityLocked);
                field.closest('label')?.classList.toggle('is-wire-editable', !authorityLocked && !readOnly && value.locked !== true);
            });
            const routeButton = root.querySelector('[data-editor-action="wire-reset-route"]');
            if (routeButton) routeButton.disabled = readOnly || value.manual !== true || value.locked === true;
            root.querySelectorAll('[data-editor-action="wire-select-source"], [data-editor-action="wire-select-target"]').forEach((button) => { button.disabled = false; });
        }

        const labelCopy = isLine
            ? { title: 'Line label <em>optional</em>', tag: 'Layer', subtitle: 'Linetype', rating: 'Lineweight', x: 'Start X', y: 'Start Y', w: 'End X', h: 'End Y' }
            : isShape
                ? { title: 'Geometry label <em>optional</em>', tag: 'Layer', subtitle: 'Geometry type', rating: 'Lineweight', x: 'Position X', y: 'Position Y', w: 'Width', h: 'Height' }
                : isEdge
                    ? { title: 'Circuit label', tag: 'Equipment tag <em>not applicable</em>', subtitle: 'Cable detail', rating: 'Cable detail', x: 'Position X', y: 'Position Y', w: 'Width', h: 'Height' }
                    : isAnnotation
                        ? { title: 'Drawing note', tag: 'Equipment tag <em>not applicable</em>', subtitle: 'Description', rating: 'Main rating', x: 'Position X', y: 'Position Y', w: 'Width', h: 'Height' }
                        : { title: 'Equipment name', tag: 'Equipment tag <em>optional</em>', subtitle: 'Description', rating: 'Main rating', x: 'Position X', y: 'Position Y', w: 'Width', h: 'Height' };
        Object.entries(labelCopy).forEach(([key, copy]) => {
            if (fieldLabels[key]) fieldLabels[key].innerHTML = copy;
        });
        const lineStart = isLine ? (geometry?.getTerminalPoint?.(true) || geometry?.sourcePoint) : null;
        const lineEnd = isLine ? (geometry?.getTerminalPoint?.(false) || geometry?.targetPoint) : null;
        const fieldValues = {
            title: isAnnotation ? value.text : (isLine || isShape ? value.label : (isEdge ? value.label : value.title)),
            tag: isLine || isShape ? value.layer : (isEdge || isAnnotation ? '' : value.tag),
            subtitle: isLine ? value.line_type : (isShape ? value.shape_type : (isEdge ? value.detail : value.subtitle)),
            rating: isLine || isShape ? value.lineweight : (isEdge ? value.detail : value.rating),
            x: isLine ? lineStart?.x : geometry?.x,
            y: isLine ? lineStart?.y : geometry?.y,
            w: isLine ? lineEnd?.x : geometry?.width,
            h: isLine ? lineEnd?.y : geometry?.height,
            rated_power: value.rated_power ?? '',
            rated_voltage: value.rated_voltage ?? '',
            rated_current: value.rated_current ?? '',
            manufacturer: value.manufacturer ?? '',
            rotation: normalizeQuarterTurn(value.rotation),
        };
        Object.entries(fields).forEach(([key, field]) => {
            if (document.activeElement !== field) field.value = fieldValues[key] ?? '';
            const isContentField = ['title', 'tag', 'subtitle', 'rating', 'rated_power', 'rated_voltage', 'rated_current', 'manufacturer'].includes(key);
            const isSizeField = ['w', 'h'].includes(key);
            const isRotationField = key === 'rotation';
            const rotationPolicyLocked = isRotationField && value.entity === 'node' && (registryTransformPolicy(value.symbol || value.kind).rotations || [0]).length <= 1;
            const authorityLocked = sourceLocked && isContentField && key !== 'tag';
            field.disabled = readOnly || (isEdge && !isLine) || authorityLocked || rotationPolicyLocked || (isLine && isRotationField) || ((isLine || isShape || isAnnotation) && ['rated_power', 'rated_voltage', 'rated_current', 'manufacturer'].includes(key)) || (isAnnotation && key !== 'title' && isContentField);
            field.closest('label')?.classList.toggle('is-authority-locked', authorityLocked);
            field.closest('label')?.classList.toggle('is-field-editable', !field.disabled);
            if (authorityLocked) field.title = 'Controlled by the technical calculation. Edit the source calculation to change this value.';
            else field.removeAttribute('title');
        });

        root.querySelectorAll('[data-editor-visibility]').forEach((button) => {
            const property = `show_${button.dataset.editorVisibility}`;
            const available = value.entity === 'node';
            const active = available && value[property] !== false;
            button.disabled = readOnly || !available || value.locked === true;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });

        const deleteButton = root.querySelector('[data-editor-delete]');
        if (deleteButton) {
            const sourceNode = cell.isVertex?.() && value.entity === 'node' && value.manual !== true;
            deleteButton.disabled = readOnly || (!sourceNode && !graph.isCellDeletable(cell));
            deleteButton.classList.toggle('is-exclude', sourceNode);
            if (els.deleteLabel) els.deleteLabel.textContent = sourceNode ? 'Exclude from drawing' : 'Remove selected object';
        }
    }

    function electricalDiagramSnapshot() {
        const nodes = [];
        const connections = [];
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        children.forEach((cell) => {
            if (cell.isVisible?.() === false) return;
            const value = cellValue(cell);
            if (!value || typeof value !== 'object') return;
            if (cell.isVertex?.() && value.entity === 'node') {
                nodes.push({
                    ...value,
                    id: String(value.id || cell.getId()),
                    symbol: value.symbol || value.kind,
                    ports: portProfile(value.symbol || value.kind, value.ports),
                    _manual: value.manual === true,
                });
                return;
            }
            if (cell.isEdge?.() && value.entity === 'edge') {
                const sourceValue = cellValue(cell.getTerminal?.(true)) || {};
                const targetValue = cellValue(cell.getTerminal?.(false)) || {};
                const manual = value.manual === true || String(value.state || '').toLowerCase() === 'manual';
                connections.push({
                    ...value,
                    id: String(value.id || value.wire_id || cell.getId()),
                    from: String(sourceValue.id || value.from || ''),
                    to: String(targetValue.id || value.to || ''),
                    manual,
                    _manual: manual,
                });
            }
        });
        return { nodes, connections };
    }

    function severityRank(severity) {
        return ({ error: 4, warning: 3, notice: 2, info: 1 })[severity] || 0;
    }

    function applyElectricalHighlights(validation) {
        root.querySelectorAll('[data-nxsld-node-id]').forEach((node) => {
            node.classList.remove('has-electrical-error', 'has-electrical-warning');
            node.removeAttribute('data-electrical-severity');
        });
        const byNode = new Map();
        (validation?.issues || []).forEach((issue) => {
            (issue.node_ids || []).forEach((nodeId) => {
                const current = byNode.get(String(nodeId));
                if (!current || severityRank(issue.severity) > severityRank(current)) byNode.set(String(nodeId), issue.severity);
            });
        });
        const escapeSelector = window.CSS?.escape ? window.CSS.escape : (value) => String(value).replace(/([\"'\\.#:[\]()>+~*=])/g, '\\$1');
        byNode.forEach((severity, nodeId) => {
            const node = root.querySelector(`[data-nxsld-node-id="${escapeSelector(nodeId)}"]`);
            if (!node) return;
            node.dataset.electricalSeverity = severity;
            if (severity === 'error') node.classList.add('has-electrical-error');
            else if (severity === 'warning') node.classList.add('has-electrical-warning');
        });
    }

    function openInspectorTab(tab) {
        openPanel('inspector');
        root.querySelectorAll('[data-editor-inspector-tab]').forEach((button) => button.classList.toggle('is-active', button.dataset.editorInspectorTab === tab));
        root.querySelectorAll('[data-editor-inspector-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.editorInspectorPanel === tab));
    }

    function findCellById(id) {
        const key = String(id ?? '');
        if (!key) return null;
        const nodeCell = cellsByNodeId.get(key);
        if (nodeCell) return nodeCell;
        return (graph.getDefaultParent()?.getChildren?.() || []).find((cell) => {
            const value = cellValue(cell);
            return String(value?.id || value?.wire_id || cell?.getId?.() || '') === key;
        }) || null;
    }

    function locateElectricalIssue(issueId) {
        const issue = electricalValidation?.issues?.find((candidate) => candidate.id === issueId);
        if (!issue) return false;
        const candidates = [...(issue.connection_ids || []), ...(issue.node_ids || []), issue.subject_id].filter(Boolean);
        const cell = candidates.map((id) => findCellById(id)).find(Boolean);
        if (!cell) return false;
        graph.setSelectionCell(cell);
        graph.scrollCellToVisible?.(cell, true);
        if (displayMode !== 'inspection') setDisplayMode('inspection');
        openInspectorTab('checks');
        refreshUi();
        return true;
    }

    function renderElectricalValidation(validation = electricalValidation) {
        if (!validation) return;
        electricalValidation = validation;
        const summary = validation.summary || {};
        const status = validation.status || summary.status || 'pass';
        const total = Number(summary.total || 0);
        root.dataset.electricalCheckStatus = status;
        root.dataset.electricalCheckErrors = String(summary.error || 0);
        root.dataset.electricalCheckWarnings = String(summary.warning || 0);
        [els.checkStatus, els.checkSummary].forEach((element) => {
            if (!element) return;
            element.classList.remove('is-pending', 'is-pass', 'is-review', 'is-fail');
            element.classList.add(`is-${status}`);
        });
        if (els.checkStatusLabel) els.checkStatusLabel.textContent = status === 'fail' ? 'BLOCKED' : (status === 'review' ? 'REVIEW' : 'PASS');
        if (els.checkStatusCount) els.checkStatusCount.textContent = String(total);
        if (els.checkRibbonCount) els.checkRibbonCount.textContent = String(total);
        if (els.checkTabCount) els.checkTabCount.textContent = String(total);
        root.querySelectorAll('[data-editor-check-count]').forEach((counter) => {
            counter.textContent = String(summary[counter.dataset.editorCheckCount] || 0);
        });
        if (els.checkHeading) {
            els.checkHeading.textContent = status === 'fail'
                ? `${summary.error || 0} blocking error${Number(summary.error || 0) === 1 ? '' : 's'}`
                : (status === 'review' ? `${summary.warning || 0} review warning${Number(summary.warning || 0) === 1 ? '' : 's'}` : 'Electrical rule check passed');
        }
        if (els.checkMessage) {
            els.checkMessage.textContent = status === 'fail'
                ? 'Save is allowed, but controlled review and export remain blocked until all errors are resolved.'
                : (status === 'review' ? 'No blocking topology error was found. Review warnings and notices before issue.' : 'No blocking error or warning was found in the current drawing topology.');
        }
        const visibleIssues = (validation.issues || []).filter((issue) => electricalCheckFilter === 'all' || issue.severity === electricalCheckFilter);
        if (els.checkList) {
            const hasAnyIssue = (validation.issues || []).length > 0;
            const emptyMessage = hasAnyIssue
                ? 'No issues match this filter.'
                : 'No electrical-rule findings.';
            els.checkList.innerHTML = visibleIssues.length === 0
                ? `<p>${emptyMessage}</p>`
                : visibleIssues.map((issue) => `<button type="button" class="nxsld-check-item is-${escapeHtml(issue.severity)}" data-editor-issue-id="${escapeHtml(issue.id)}"><span>${escapeHtml(issue.code)}</span><div><strong>${escapeHtml(issue.title)}</strong><p>${escapeHtml(issue.message)}</p><small>${escapeHtml(issue.recommendation)}</small></div></button>`).join('');
            els.checkList.querySelectorAll('[data-editor-issue-id]').forEach((button) => button.addEventListener('click', () => locateElectricalIssue(button.dataset.editorIssueId)));
        }
        applyElectricalHighlights(validation);
    }

    function runElectricalCheck({ open = false } = {}) {
        electricalValidation = evaluateElectricalDiagram(electricalDiagramSnapshot());
        renderElectricalValidation(electricalValidation);
        if (open) openInspectorTab('checks');
        root.dispatchEvent(new CustomEvent('nexorin:sld-electrical-check', { bubbles: true, detail: electricalValidation }));
        return electricalValidation;
    }

    function scheduleElectricalCheck() {
        window.clearTimeout(electricalCheckTimer);
        electricalCheckTimer = window.setTimeout(() => runElectricalCheck(), 180);
    }

    function controllerStatusTone(status) {
        return ['synchronized', 'review', 'blocked', 'stale'].includes(status) ? status : 'pending';
    }

    function locateControllerConflict(conflict) {
        const candidates = [...(conflict?.objects || []), conflict?.connectionKey].filter(Boolean);
        const cell = candidates.map((id) => findCellById(id)).find(Boolean);
        if (!cell) return false;
        graph.setSelectionCell(cell);
        graph.scrollCellToVisible?.(cell, true);
        openInspectorTab('controller');
        refreshUi();
        return true;
    }

    function renderEngineeringController(controller = engineeringController) {
        if (!controller) return;
        engineeringController = controller;
        const status = controllerStatusTone(controller.status);
        const summary = controller.summary || {};
        root.dataset.engineeringControllerStatus = status;
        root.dataset.engineeringControllerErrors = String(summary.error || 0);
        [els.controllerStatus, els.controllerHero].forEach((element) => {
            if (!element) return;
            element.classList.remove('is-pending', 'is-synchronized', 'is-review', 'is-blocked', 'is-stale');
            element.classList.add(`is-${status}`);
        });
        if (els.controllerStatusLabel) els.controllerStatusLabel.textContent = status === 'blocked' ? 'BLOCKED' : status.toUpperCase();
        if (els.controllerStatusCount) els.controllerStatusCount.textContent = String(summary.total || 0);
        if (els.controllerTabCount) els.controllerTabCount.textContent = String(summary.total || 0);
        root.querySelectorAll('[data-editor-controller-count]').forEach((counter) => {
            counter.textContent = String(summary[counter.dataset.editorControllerCount] || 0);
        });
        if (els.controllerHeading) els.controllerHeading.textContent = status === 'synchronized' ? 'Source chain synchronized' : (status === 'blocked' ? 'Engineering synchronization blocked' : 'Engineering review required');
        if (els.controllerMessage) els.controllerMessage.textContent = controller.message || 'Engineering controller result is unavailable.';

        if (els.controllerSources) {
            els.controllerSources.innerHTML = (controller.sources || []).map((source) => `<article class="is-${escapeHtml(source.status || 'pending')}"><i></i><div><strong>${escapeHtml(source.label || 'Source')}</strong><small>${escapeHtml(source.detail || '—')}</small></div><b>${escapeHtml(String(source.status || 'pending').toUpperCase())}</b></article>`).join('');
        }
        if (els.controllerImpacts) {
            els.controllerImpacts.innerHTML = (controller.impacts || []).map((impact) => `<article class="is-${escapeHtml(impact.status || 'ready')}"><span>${escapeHtml(impact.label || 'Impact')}</span><b>${Number(impact.count || 0)}</b></article>`).join('');
        }
        if (els.controllerConflicts) {
            els.controllerConflicts.innerHTML = (controller.conflicts || []).length === 0
                ? '<p class="nxsld-controller-empty">No controller conflicts. Source chain is synchronized.</p>'
                : controller.conflicts.map((conflict, index) => `<button type="button" class="is-${escapeHtml(conflict.severity || 'notice')}${conflict.acknowledged ? ' is-acknowledged' : ''}" data-editor-controller-conflict-index="${index}"><i></i><span><b>${escapeHtml(conflict.code || 'K---')} · ${escapeHtml(conflict.title || 'Engineering finding')}</b><small>${escapeHtml(conflict.message || '')}</small><em>${escapeHtml(conflict.action || '')}</em></span></button>`).join('');
            els.controllerConflicts.querySelectorAll('[data-editor-controller-conflict-index]').forEach((button) => button.addEventListener('click', () => locateControllerConflict(controller.conflicts[Number(button.dataset.editorControllerConflictIndex)])));
        }
        if (els.controllerCables) {
            els.controllerCables.innerHTML = (controller.cable_schedule || []).map((cable) => {
                const route = [cable.from, cable.to].filter(Boolean).join(' → ');
                const description = [cable.label, cable.detail].filter(Boolean).join(' · ');
                return `<article><strong>${escapeHtml(cable.wire_id || 'CABLE')}</strong><span>${escapeHtml(route || cable.label || '')}</span><small>${escapeHtml(description)}</small></article>`;
            }).join('');
        }
        if (els.controllerProtection) {
            els.controllerProtection.innerHTML = (controller.protection_schedule || []).map((item) => `<article><strong>${escapeHtml(item.id || 'PROTECTION')}</strong><span>${escapeHtml(item.label || '')}</span><small>${escapeHtml(`${Number(item.rating || 0)} ${item.unit || ''} · ${item.designation || ''}`)}</small></article>`).join('');
        }
        if (els.controllerSync) els.controllerSync.disabled = readOnly || controllerBusy || !controllerUrl;
        if (els.controllerAck) els.controllerAck.disabled = readOnly || controllerBusy || !controllerUrl || Number(summary.warning || 0) + Number(summary.notice || 0) === 0;
    }

    async function runEngineeringControllerAction(action, reason = '') {
        if (readOnly || !controllerUrl || controllerBusy) return false;
        controllerBusy = true;
        renderEngineeringController();
        markSaveState('saving', action === 'accept_source' ? 'Synchronizing engineering source…' : 'Recording engineering review…');
        try {
            if (dirty && !(await save())) throw new Error('Save the current drawing before synchronizing engineering sources.');
            const response = await fetch(controllerUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ action, reason }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || `Engineering synchronization failed (${response.status})`);
            if (result.engineering_controller) renderEngineeringController(result.engineering_controller);
            if (result.electrical_validation) renderElectricalValidation(result.electrical_validation);
            showWireToast('success', action === 'accept_source' ? 'SOURCE SYNCHRONIZED' : 'REVIEW RECORDED', result.message || 'Engineering controller updated.');
            markSaveState('saved', 'Engineering controller updated');
            if (result.reload === true) {
                window.setTimeout(() => window.location.reload(), 420);
            }
            return true;
        } catch (error) {
            showWireToast('error', 'ENGINEERING CONTROLLER', error.message || 'Engineering synchronization failed.');
            markSaveState('error', error.message || 'Engineering synchronization failed');
            return false;
        } finally {
            controllerBusy = false;
            renderEngineeringController();
        }
    }

    function geometryDiagnosticItems() {
        return (graph.getDefaultParent()?.getChildren?.() || []).flatMap((cell) => {
            const value = cellValue(cell) || {};
            const geometry = cell.getGeometry?.();
            if (!cell?.isVertex?.() || cell.isVisible?.() === false || value.entity !== 'node' || !geometry) return [];
            return [{
                id: String(value.id || cell.getId?.() || ''),
                entity: 'node',
                x: geometry.x,
                y: geometry.y,
                w: geometry.width,
                h: geometry.height,
            }];
        });
    }

    function collectGeometryDiagnostics() {
        return diagnoseGeometry(geometryDiagnosticItems(), {
            canvas: CANVAS,
            gridSize: GRID_SIZE,
            minimumGap: SLD_GEOMETRY_CONTRACT.equipment.minimumGap,
        });
    }

    function updateGeometryStatus() {
        geometryDiagnosticReport = collectGeometryDiagnostics();
        const summary = geometryDiagnosticReport.summary || {};
        const issueCount = Number(summary.issues || 0);
        const errorCount = Number(summary.errors || 0);
        if (els.geometryVersion) els.geometryVersion.textContent = `GEOM ${SLD_GEOMETRY_FOUNDATION_VERSION}`;
        if (els.geometryIssueCount) els.geometryIssueCount.textContent = String(issueCount);
        if (els.geometryBadge) {
            els.geometryBadge.classList.toggle('is-geometry-warning', issueCount > 0 && errorCount === 0);
            els.geometryBadge.classList.toggle('is-geometry-error', errorCount > 0);
            els.geometryBadge.title = issueCount > 0
                ? `${issueCount} geometry issue${issueCount === 1 ? '' : 's'} · ${summary.overlaps || 0} overlap · ${summary.outside_canvas || 0} outside canvas`
                : `APP${SLD_GEOMETRY_FOUNDATION_VERSION} canonical geometry · ${summary.advisories || 0} advisory item${Number(summary.advisories || 0) === 1 ? '' : 's'}`;
        }
        root.dataset.geometryVersion = SLD_GEOMETRY_FOUNDATION_VERSION;
        root.dataset.geometryHealth = errorCount > 0 ? 'error' : (issueCount > 0 ? 'warning' : 'pass');
    }

    function countCells() {
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        const nodes = children.filter((cell) => cell.isVertex?.() && cell.isVisible?.() !== false && cellValue(cell)?.entity === 'node').length;
        const wires = children.filter((cell) => cell.isEdge?.() && cellValue(cell)?.entity === 'edge' && graph.getView().getState(cell)).length;
        const lines = children.filter((cell) => ((cell.isEdge?.() && isDraftingLineValue(cellValue(cell))) || (cell.isVertex?.() && cellValue(cell)?.entity === 'shape')) && graph.getView().getState(cell)).length;
        if (els.nodeCount) els.nodeCount.textContent = String(nodes);
        if (els.wireCount) els.wireCount.textContent = String(wires);
        if (els.lineCount) els.lineCount.textContent = String(lines);
        els.hint?.classList.toggle('is-hidden', graph.getSelectionCount?.() > 0);
    }

    function refreshUi() {
        updateInspector();
        updateExcludedList();
        countCells();
        updateGeometryStatus();
        updateZoomUi();
        renderSelectedSnapPoints();
        renderPrecisionResizeOverlay();
        window.requestAnimationFrame(updateWirePortPresentation);
        const selection = selectedCells();
        const vertexCount = selection.filter((cell) => cell.isVertex?.() && graph.isCellMovable(cell)).length;
        const lockedCount = selection.filter((cell) => cellValue(cell)?.locked === true).length;
        if (els.selectionCount) els.selectionCount.textContent = String(selection.length);
        root.querySelectorAll('[data-editor-action="undo"]').forEach((button) => { button.disabled = !undoManager.canUndo(); });
        root.querySelectorAll('[data-editor-action="redo"]').forEach((button) => { button.disabled = !undoManager.canRedo(); });
        root.querySelectorAll('[data-editor-requires-selection]').forEach((button) => { button.disabled = readOnly || selection.length === 0; });
        root.querySelectorAll('[data-editor-requires-two]').forEach((button) => { button.disabled = readOnly || vertexCount < 2; });
        root.querySelectorAll('[data-editor-requires-three]').forEach((button) => { button.disabled = readOnly || vertexCount < 3; });
        root.querySelectorAll('[data-editor-action="paste"]').forEach((button) => { button.disabled = readOnly || clipboard.length === 0; });
        root.querySelectorAll('[data-editor-action="lock"]').forEach((button) => { button.disabled = readOnly || selection.length === 0 || lockedCount === selection.length; });
        root.querySelectorAll('[data-editor-action="unlock"]').forEach((button) => { button.disabled = readOnly || lockedCount === 0; });
        const transformCount = selection.filter((cell) => {
            const value = cellValue(cell);
            return value && value.locked !== true && ((cell.isVertex?.() && ['node', 'shape', 'annotation'].includes(value.entity)) || (cell.isEdge?.() && isDraftingLineValue(value)));
        }).length;
        const groupedCount = selection.filter((cell) => Boolean(cellValue(cell)?.group_id)).length;
        root.querySelectorAll('[data-editor-requires-transform]').forEach((button) => { button.disabled = readOnly || transformCount === 0; });
        root.querySelectorAll('[data-editor-requires-group]').forEach((button) => { button.disabled = readOnly || groupedCount === 0; });
    }

    function panelState() {
        return {
            palette_collapsed: root.classList.contains('is-palette-collapsed'),
            inspector_collapsed: root.classList.contains('is-inspector-collapsed'),
            ribbon_collapsed: root.classList.contains('is-ribbon-collapsed'),
        };
    }

    function selectPaperPage(index) {
        const pages = [...root.querySelectorAll('[data-editor-paper-page]')];
        if (pages.length === 0) return;
        activePaperPage = clamp(Number(index) || 0, 0, pages.length - 1);
        pages.forEach((page, pageIndex) => page.classList.toggle('is-active', pageIndex === activePaperPage));
        root.querySelectorAll('[data-editor-paper-tab]').forEach((button) => button.classList.toggle('is-active', Number(button.dataset.editorPaperTab) === activePaperPage));
    }

    function renderPaperNavigation(sheets = []) {
        if (!els.paperNavigation) return;
        els.paperNavigation.innerHTML = sheets.map((sheet, index) => `<button type="button" class="${index === activePaperPage ? 'is-active' : ''}" data-editor-paper-tab="${index}"><b>${String(sheet.number || index + 1).padStart(2, '0')}</b><span>${escapeHtml(sheet.title || `Sheet ${index + 1}`)}</span><small>${escapeHtml(String(sheet.type || 'detail').toUpperCase())} · ${Number(sheet.node_count || 0)} OBJECTS</small></button>`).join('');
        els.paperNavigation.querySelectorAll('[data-editor-paper-tab]').forEach((button) => button.addEventListener('click', () => selectPaperPage(Number(button.dataset.editorPaperTab))));
    }

    function updatePaperSummary(summary = {}) {
        const mode = String(summary.mode || paperMode || 'auto').toUpperCase();
        const sheetCount = Number(summary.sheet_count || root.querySelectorAll('[data-editor-paper-page]').length || 1);
        if (els.paperStatus) els.paperStatus.textContent = `${mode} · ${sheetCount} SHEET${sheetCount === 1 ? '' : 'S'}`;
        if (els.paperMessage) els.paperMessage.textContent = summary.message || 'Paper-space composition follows the saved Model Space drawing.';
    }

    function renderLayerControls() {
        root.querySelectorAll('[data-editor-layer]').forEach((row) => {
            const state = layerState[row.dataset.editorLayer] || LAYER_DEFAULTS[row.dataset.editorLayer] || { visible: true, locked: false, print: true };
            row.classList.toggle('is-hidden-layer', !state.visible);
            row.classList.toggle('is-locked-layer', state.locked);
            row.classList.toggle('is-nonprinting-layer', !state.print);
            row.querySelectorAll('[data-editor-layer-action]').forEach((button) => button.classList.toggle('is-active', Boolean(state[button.dataset.editorLayerAction])));
        });
    }

    function applyLayerState(mark = false) {
        graph.batchUpdate(() => {
            const children = graph.getDefaultParent()?.getChildren?.() || [];
            children.forEach((cell) => {
                const value = cellValue(cell);
                if (!value) return;
                const state = layerState[objectLayer(value)] || { visible: true };
                const excluded = value.entity === 'node' && value.manual !== true && excludedNodes.has(String(value.id));
                model.setVisible(cell, state.visible !== false && !excluded);
            });
        });
        renderLayerControls();
        graph.refresh();
        refreshUi();
        if (mark) markDirty();
    }

    function setLayerProperty(layerName, property, value) {
        if (!layerState[layerName] || !['visible', 'locked', 'print'].includes(property)) return;
        layerState[layerName][property] = Boolean(value);
        if (property === 'visible') applyLayerState(true);
        else { renderLayerControls(); markDirty(); refreshUi(); }
    }

    function serializeEditor() {
        const positions = {};
        const routes = {};
        const manual_nodes = [];
        const manual_connections = [];
        const manual_lines = [];
        const manual_shapes = [];
        const serializedAnnotations = [];
        const children = graph.getDefaultParent()?.getChildren?.() || [];

        children.forEach((cell) => {
            const value = cellValue(cell);
            const geometry = cell.getGeometry?.();
            if (!value || !geometry) return;
            if (cell.isVertex?.() && value.entity === 'node') {
                positions[value.id] = {
                    x: Math.round(geometry.x),
                    y: Math.round(geometry.y),
                    w: Math.round(geometry.width),
                    h: Math.round(geometry.height),
                    locked: value.locked === true,
                    rotation: normalizeQuarterTurn(value.rotation),
                    flip_h: value.flip_h === true,
                    flip_v: value.flip_v === true,
                    group_id: value.group_id || null,
                    show_tag: value.show_tag !== false,
                    show_rating: value.show_rating !== false,
                    show_subtitle: value.show_subtitle !== false,
                    show_ports: value.show_ports !== false,
                    tag_override: value.manual ? null : String(value.tag || ''),
                };
                if (value.manual) {
                    manual_nodes.push({
                        id: value.id,
                        tag: value.tag || '',
                        title: value.title,
                        subtitle: value.subtitle,
                        rating: value.rating,
                        kind: value.symbol || value.kind || 'panel',
                        state: 'manual',
                        rated_power: value.rated_power ?? null,
                        rated_voltage: value.rated_voltage ?? null,
                        rated_current: value.rated_current ?? null,
                        manufacturer: value.manufacturer || '',
                        locked: value.locked === true,
                        rotation: normalizeQuarterTurn(value.rotation),
                        flip_h: value.flip_h === true,
                        flip_v: value.flip_v === true,
                        group_id: value.group_id || null,
                        show_tag: value.show_tag !== false,
                        show_rating: value.show_rating !== false,
                        show_subtitle: value.show_subtitle !== false,
                        show_ports: value.show_ports !== false,
                        ports: portProfile(value.symbol || value.kind, value.ports),
                    });
                }
            } else if (cell.isVertex?.() && value.entity === 'shape') {
                manual_shapes.push({
                    id: value.id || uid(value.shape_type || 'shape'),
                    shape_type: value.shape_type || 'rectangle',
                    layer: value.layer || 'SLD-LINEWORK',
                    line_type: value.line_type || 'continuous',
                    lineweight: clamp(finite(value.lineweight, 1.4), 0.6, 5),
                    label: value.label || '',
                    start_angle: finite(value.start_angle, 0),
                    end_angle: finite(value.end_angle, 90),
                    sweep_flag: Number(value.sweep_flag) === 0 ? 0 : 1,
                    large_arc: Number(value.large_arc) ? 1 : 0,
                    angle_basis: value.angle_basis === 'screen' ? 'screen' : 'legacy',
                    x: Math.round(geometry.x),
                    y: Math.round(geometry.y),
                    w: Math.round(geometry.width),
                    h: Math.round(geometry.height),
                    locked: value.locked === true,
                    rotation: normalizeQuarterTurn(value.rotation),
                    flip_h: value.flip_h === true,
                    flip_v: value.flip_v === true,
                    group_id: value.group_id || null,
                });
            } else if (cell.isVertex?.() && value.entity === 'annotation') {
                serializedAnnotations.push({
                    id: value.id,
                    text: value.text || value.title || 'Drawing note',
                    x: Math.round(geometry.x),
                    y: Math.round(geometry.y),
                    w: Math.round(geometry.width),
                    h: Math.round(geometry.height),
                    locked: value.locked === true,
                    rotation: normalizeQuarterTurn(value.rotation),
                    flip_h: value.flip_h === true,
                    flip_v: value.flip_v === true,
                    group_id: value.group_id || null,
                });
            } else if (cell.isEdge?.() && isDraftingLineValue(value)) {
                const sourcePoint = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
                const targetPoint = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
                if (!sourcePoint || !targetPoint) return;
                manual_lines.push({
                    id: value.id || uid(value.entity === 'leader' ? 'leader' : 'line'),
                    entity: value.entity === 'leader' ? 'leader' : 'line',
                    label: value.label || '',
                    layer: value.layer || 'SLD-LINEWORK',
                    line_type: value.line_type || 'continuous',
                    lineweight: clamp(finite(value.lineweight, 1.4), 0.6, 5),
                    x1: Math.round(sourcePoint.x),
                    y1: Math.round(sourcePoint.y),
                    x2: Math.round(targetPoint.x),
                    y2: Math.round(targetPoint.y),
                    locked: value.locked === true,
                    group_id: value.group_id || null,
                });
            } else if (cell.isEdge?.()) {
                const sourceValue = cellValue(cell.getTerminal?.(true));
                const targetValue = cellValue(cell.getTerminal?.(false));
                if (!sourceValue?.id || !targetValue?.id) return;
                value.from = sourceValue.id;
                value.to = targetValue.id;
                if (value.manual) {
                    manual_connections.push({
                        id: value.id || uid('wire'),
                        wire_id: value.wire_id || '',
                        from: sourceValue.id,
                        to: targetValue.id,
                        label: value.label || wireNetworkDefinition(value.network).label,
                        detail: wireCableDetail(value),
                        cable_tag: value.cable_tag || '',
                        cable_type: value.cable_type || '',
                        cable_size: value.cable_size || '',
                        cores: value.cores || '',
                        voltage_class: value.voltage_class || '',
                        installation_method: value.installation_method || '',
                        notes: value.notes || '',
                        show_label: value.show_label !== false,
                        routing_mode: value.routing_mode === 'manual' ? 'manual' : 'auto',
                        routing_clearance: routingProfile(value).key,
                        router_version: value.router_version || SLD_SMART_ROUTER_VERSION,
                        route_contract: value.route_contract || SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
                        route_strategy: value.route_strategy || '',
                        route_signature: value.route_signature || '',
                        route_network_class: value.route_network_class || routingNetworkClass(value.network),
                        route_preferred_axis: value.route_preferred_axis || '',
                        route_preferred_channel: Number(value.route_preferred_channel || 0),
                        route_length: Number(value.route_length || 0),
                        route_bends: Number(value.route_bends || 0),
                        route_crossings: Number(value.route_crossings || 0),
                        route_clearance_conflicts: Number(value.route_clearance_conflicts || 0),
                        route_separation_conflicts: Number(value.route_separation_conflicts || 0),
                        route_terminal_direction_ok: value.routing_mode === 'manual' ? true : value.route_terminal_direction_ok === true,
                        route_orthogonal: value.routing_mode === 'manual' ? routeIsOrthogonal(edgeFullRoutePoints(cell)) : value.route_orthogonal === true,
                        from_port: value.from_port || null,
                        to_port: value.to_port || null,
                        network: normalizeWireNetwork(value.network),
                    });
                }
                if (Array.isArray(geometry.points) && geometry.points.length > 0) {
                    routes[value.id] = geometry.points.map((point) => ({ x: Math.round(point.x), y: Math.round(point.y) }));
                }
            }
        });

        const labelPlacementMetadata = {
            version: SLD_LABEL_PLACEMENT_VERSION,
            contract: SLD_LABEL_PLACEMENT_CONTRACT.name,
            engine: SLD_LABEL_PLACEMENT_CONTRACT.engine,
            clearance: SLD_LABEL_PLACEMENT_CONTRACT.clearance,
            wire_clearance: SLD_LABEL_PLACEMENT_CONTRACT.wireClearance,
            positions: Object.fromEntries(labelPlacementResult.placements.map((placement) => [String(placement.id), {
                side: placement.side, x: Math.round(placement.x), y: Math.round(placement.y),
                w: Math.round(placement.w), h: Math.round(placement.h), leader: placement.leader === true,
                fallback: placement.fallback === true, signature: placement.signature || '',
                equipment_conflicts: Number(placement.equipment_conflicts || 0),
                label_conflicts: Number(placement.label_conflicts || 0), wire_conflicts: Number(placement.wire_conflicts || 0),
            }])),
            summary: { ...(labelPlacementResult.summary || {}) },
        };
        const visualSnapshot = createVisualSnapshot({
            positions,
            routes,
            labelPlacement: labelPlacementMetadata,
            canvas: { x: 0, y: 0, w: CANVAS.width, h: CANVAS.height },
        });
        serializedVisualFingerprint = visualSnapshot.fingerprint;
        const visualReport = updateVisualRegressionStatus();
        const visualRegressionMetadata = {
            version: SLD_VISUAL_REGRESSION_VERSION,
            contract: SLD_VISUAL_REGRESSION_CONTRACT.name,
            engine: SLD_VISUAL_REGRESSION_CONTRACT.engine,
            coordinate_tolerance: SLD_VISUAL_REGRESSION_CONTRACT.coordinateTolerance,
            clipping_tolerance: SLD_VISUAL_REGRESSION_CONTRACT.clippingTolerance,
            route_tolerance: SLD_VISUAL_REGRESSION_CONTRACT.routeTolerance,
            fingerprint: visualSnapshot.fingerprint,
            baseline_fingerprint: visualBaselineFingerprint || visualSnapshot.fingerprint,
            status: visualReport.status,
            summary: { ...(visualReport.summary || {}) },
        };

        return {
            version: 16,
            engine: 'maxgraph-0.24',
            canvas: CANVAS,
            display_mode: displayMode,
            space: activeSpace,
            panels: panelState(),
            paper: { mode: paperMode },
            geometry: {
                version: SLD_GEOMETRY_FOUNDATION_VERSION,
                contract: SLD_GEOMETRY_CONTRACT.name,
                units: SLD_GEOMETRY_CONTRACT.units,
                coordinate_precision: SLD_GEOMETRY_CONTRACT.coordinatePrecision,
                grid_size: GRID_SIZE,
                minimum_equipment_gap: SLD_GEOMETRY_CONTRACT.equipment.minimumGap,
                diagnostics: collectGeometryDiagnostics().summary,
            },
            routing: {
                version: SLD_SMART_ROUTER_VERSION,
                contract: SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
                engine: SLD_ORTHOGONAL_ROUTING_CONTRACT.engine,
                grid_size: SLD_ORTHOGONAL_ROUTING_CONTRACT.gridSize,
                wires: routeSyncSummary.wires,
                orthogonal_wires: routeSyncSummary.orthogonalWires,
                crossings: routeSyncSummary.crossings,
                clearance_conflicts: routeSyncSummary.conflicts,
                separation_conflicts: routeSyncSummary.separationConflicts,
                terminal_direction_violations: routeSyncSummary.terminalDirectionViolations,
                fallbacks: routeSyncSummary.fallbacks,
                bends: routeSyncSummary.bends,
                length: Number(routeSyncSummary.length.toFixed(2)),
            },
            label_placement: labelPlacementMetadata,
            visual_regression: visualRegressionMetadata,
            layers: JSON.parse(JSON.stringify(layerState)),
            tools: { circle_mode: circleMode, arc_mode: arcMode },
            excluded_nodes: [...excludedNodes],
            viewport: {
                scale: Number(graph.getView().scale.toFixed(3)),
                translate_x: Number(graph.getView().translate.x.toFixed(2)),
                translate_y: Number(graph.getView().translate.y.toFixed(2)),
                grid_size: GRID_SIZE,
                grid_enabled: gridEnabled,
                snap_enabled: snapEnabled,
                ortho_enabled: orthoEnabled,
                polar_enabled: polarEnabled,
                osnap_enabled: osnapEnabled,
                osnap_modes: { ...osnapModes },
                otrack_enabled: otrackEnabled,
                dynamic_enabled: dynamicEnabled,
            },
            positions,
            routes,
            manual_nodes,
            manual_connections,
            manual_lines,
            manual_shapes,
            annotations: serializedAnnotations,
        };
    }

    async function save() {
        if (readOnly || !saveUrl || !dirty) return true;
        runElectricalCheck();
        renderEngineeringController();
        markSaveState('saving', 'Saving drawing…');
        root.querySelectorAll('[data-editor-save]').forEach((button) => { button.disabled = true; });
        try {
            const response = await fetch(saveUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ editor: serializeEditor() }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || `Save failed (${response.status})`);
            dirty = false;
            visualBaselineFingerprint = serializedVisualFingerprint || visualBaselineFingerprint;
            markSaveState('saved', 'Saved just now');
            if (Array.isArray(result.paper_svgs) && els.paperPages) {
                els.paperPages.innerHTML = result.paper_svgs.map((svg, index) => `<article class="nxsld-paper-page${index === 0 ? ' is-active' : ''}" data-editor-paper-page="${index}">${svg}</article>`).join('');
                activePaperPage = 0;
                renderPaperNavigation(result.paper_sheets || []);
            } else if (result.paper_svg && els.paperPages) {
                els.paperPages.innerHTML = `<article class="nxsld-paper-page is-active" data-editor-paper-page="0">${result.paper_svg}</article>`;
            }
            updatePaperSummary(result.paper_summary || {});
            if (result.electrical_validation) renderElectricalValidation(result.electrical_validation);
            if (result.engineering_controller) renderEngineeringController(result.engineering_controller);
            if (els.paperPreviewNote) els.paperPreviewNote.textContent = 'Saved intelligent paper-space composition';
            return true;
        } catch (error) {
            markSaveState('error', error.message || 'Drawing could not be saved');
            return false;
        } finally {
            root.querySelectorAll('[data-editor-save]').forEach((button) => { button.disabled = readOnly; });
        }
    }

    function syncPanelAccessibility(panel) {
        const collapsed = root.classList.contains(`is-${panel}-collapsed`);
        const panelElement = root.querySelector(`[data-editor-panel="${panel}"]`);
        const collapseButton = root.querySelector(`[data-editor-toggle-panel="${panel}"]`);
        const openButton = root.querySelector(`[data-editor-open-panel="${panel}"]`);

        if (panelElement) {
            panelElement.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
            if ('inert' in panelElement) panelElement.inert = collapsed;
        }

        if (collapseButton) {
            collapseButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }

        if (openButton) {
            openButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    function syncAllPanels() {
        syncPanelAccessibility('palette');
        syncPanelAccessibility('inspector');
    }

    function openPanel(panel) {
        root.classList.remove(`is-${panel}-collapsed`);
        syncPanelAccessibility(panel);
        persistPanels();
        window.setTimeout(() => { graph.refresh(); fitIfEmptySpace(); }, 210);
    }

    function togglePanel(panel) {
        root.classList.toggle(`is-${panel}-collapsed`);
        syncPanelAccessibility(panel);
        persistPanels();
        window.setTimeout(() => { graph.refresh(); fitIfEmptySpace(); }, 210);
    }

    function persistPanels() {
        try { localStorage.setItem(PANEL_STORAGE_KEY, JSON.stringify(panelState())); } catch (_error) { /* no-op */ }
    }

    function restorePanels() {
        let state = editorPayload.panels || {};
        try {
            const stored = JSON.parse(localStorage.getItem(PANEL_STORAGE_KEY) || '{}');
            state = { ...state, ...stored };
        } catch (_error) { /* no-op */ }
        root.classList.toggle('is-palette-collapsed', state.palette_collapsed === true);
        root.classList.toggle('is-inspector-collapsed', state.inspector_collapsed === true);
        root.classList.toggle('is-ribbon-collapsed', state.ribbon_collapsed === true);
        root.querySelectorAll('[data-editor-action="toggle-ribbon"]').forEach((button) => {
            const collapsed = root.classList.contains('is-ribbon-collapsed');
            button.classList.toggle('is-active', collapsed);
            button.setAttribute('aria-pressed', String(collapsed));
            button.title = collapsed ? 'Expand ribbon' : 'Collapse ribbon';
        });
        syncAllPanels();
    }

    function fitIfEmptySpace() {
        if (container.clientWidth > 0 && container.clientHeight > 0) graph.refresh();
    }

    function setDisplayMode(mode) {
        if (!['clean', 'engineering', 'inspection'].includes(mode)) return;
        displayMode = mode;
        root.classList.remove('is-display-clean', 'is-display-engineering', 'is-display-inspection');
        root.classList.add(`is-display-${mode}`);
        root.querySelectorAll('[data-editor-display]').forEach((button) => button.classList.toggle('is-active', button.dataset.editorDisplay === mode));
        graph.refresh();
        markDirty();
    }

    function setSpace(space, shouldFit = true) {
        if (space === 'sheet') resetDraftSession();
        activeSpace = space === 'sheet' ? 'sheet' : 'model';
        root.dataset.activeSpace = activeSpace;
        root.classList.toggle('is-sheet-space', activeSpace === 'sheet');
        root.querySelectorAll('[data-editor-space]').forEach((button) => button.classList.toggle('is-active', button.dataset.editorSpace === activeSpace));
        if (els.spaceDescription) els.spaceDescription.textContent = activeSpace === 'sheet' ? 'A3 landscape drawing composition' : 'Unlimited drafting canvas';
        window.setTimeout(() => {
            graph.refresh();
            if (shouldFit) fitDrawing();
        }, 90);
    }

    function showCloseDialog() {
        if (!dirty) {
            window.location.assign(closeUrl);
            return;
        }
        if (!els.closeDialog) return;
        els.closeDialog.hidden = false;
        els.closeDialog.setAttribute('aria-hidden', 'false');
    }

    function hideCloseDialog() {
        if (!els.closeDialog) return;
        els.closeDialog.hidden = true;
        els.closeDialog.setAttribute('aria-hidden', 'true');
    }

    function updateFullscreenUi() {
        const active = Boolean(document.fullscreenElement);
        root.classList.toggle('is-true-fullscreen', active);
        root.querySelectorAll('[data-fullscreen-enter]').forEach((icon) => { icon.hidden = active; });
        root.querySelectorAll('[data-fullscreen-exit]').forEach((icon) => { icon.hidden = !active; });
        root.querySelectorAll('[data-editor-action="fullscreen"]').forEach((button) => {
            button.setAttribute('aria-label', active ? 'Exit fullscreen drafting mode' : 'Enter fullscreen drafting mode');
            button.title = active ? 'Exit fullscreen (Esc)' : 'Fullscreen (F)';
        });
        window.setTimeout(() => graph.refresh(), 80);
    }

    graph.getSelectionModel().addListener(InternalEvent.CHANGE, () => {
        expandGroupedSelection();
        refreshUi();
    });
    graph.addListener(InternalEvent.CELLS_ADDED, (_sender, event) => {
        const cells = event.getProperty('cells') || [];
        cells.forEach((cell) => {
            if (!cell.isEdge?.()) return;
            const value = cellValue(cell);
            if (!value?.manual) return;
            if (isDraftingLineValue(value)) {
                model.setStyle(cell, draftingLineStyle(value));
                return;
            }
            const sourceValue = cellValue(cell.getTerminal?.(true));
            const targetValue = cellValue(cell.getTerminal?.(false));
            value.from = sourceValue?.id;
            value.to = targetValue?.id;
            model.setStyle(cell, manualEdgeStyle(sourceValue?.kind, targetValue?.kind, true, value.network));
            syncSmartWire(cell, value.routing_mode !== 'manual', value);
        });
        refreshUi();
    });
    graph.addListener(InternalEvent.CELLS_MOVED, (_sender, event) => {
        syncConnectedWires(event.getProperty('cells') || [], true);
        renderPrecisionResizeOverlay();
    });
    graph.addListener(InternalEvent.CELLS_RESIZED, (_sender, event) => {
        syncConnectedWires(event.getProperty('cells') || [], true);
        renderPrecisionResizeOverlay();
    });

    graph.addListener(InternalEvent.DOUBLE_CLICK, (_sender, event) => {
        const cell = event.getProperty('cell');
        const value = cellValue(cell);
        if (readOnly || value?.entity !== 'annotation') return;
        graph.setSelectionCell(cell);
        openPanel('inspector');
        refreshUi();
        fields.title?.focus();
        fields.title?.select?.();
        event.consume?.();
    });

    els.resizeOverlay?.addEventListener('pointerdown', beginPrecisionResize);
    window.addEventListener('pointermove', movePrecisionResize, { passive: false });
    window.addEventListener('pointerup', endPrecisionResize, { passive: false });
    window.addEventListener('pointercancel', endPrecisionResize, { passive: false });

    function renderPointerFrame() {
        pointerFrameScheduled = false;
        const sample = pendingPointerSample;
        pendingPointerSample = null;
        if (!sample || moveSelectionSession || panSession || selectionDrag) return;
        pointerPoint = graphPoint(sample);
        const frame = root.querySelector('.nxsld-canvas-frame')?.getBoundingClientRect();
        pointerScreen = frame ? { x: sample.clientX - frame.left, y: sample.clientY - frame.top } : { x: sample.offsetX, y: sample.offsetY };
        const interactiveDraft = activeSpace === 'model' && osnapInteractionActive();
        const anchor = interactiveDraft ? (draftSession?.points?.[draftSession.points.length - 1] || null) : null;
        const orthoOverride = interactiveDraft ? (activeTool === 'wire' ? true : (sample.shiftKey ? !orthoEnabled : null)) : null;
        const resolved = interactiveDraft ? resolvedDraftPoint(pointerPoint, anchor, orthoOverride) : gridSnap(pointerPoint);
        if (els.x) els.x.textContent = String(Math.round(resolved.x));
        if (els.y) els.y.textContent = String(Math.round(resolved.y));
        if (els.crosshair && frame) {
            const crosshairPoint = graphToFrameScreen(resolved);
            els.crosshair.style.left = `${crosshairPoint.x}px`;
            els.crosshair.style.top = `${crosshairPoint.y}px`;
        }
        if (interactiveDraft) updateDraftOverlay();
        else if (els.dynamicInput) els.dynamicInput.hidden = true;
    }

    container.addEventListener('pointermove', (event) => {
        pendingPointerSample = {
            clientX: event.clientX,
            clientY: event.clientY,
            offsetX: event.offsetX,
            offsetY: event.offsetY,
            shiftKey: event.shiftKey,
        };
        if (pointerFrameScheduled) return;
        pointerFrameScheduled = true;
        window.requestAnimationFrame(renderPointerFrame);
    });

    function shouldStartPan(event) {
        if (activeSpace !== 'model' || panSession) return false;
        const leftDrag = event.button === 0 && (activeTool === 'pan' || spaceDown);
        const middleDrag = event.button === 1;
        return leftDrag || middleDrag;
    }

    function beginPan(event) {
        if (!shouldStartPan(event)) return;
        const view = graph.getView();
        panSession = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            translateX: view.translate.x,
            translateY: view.translate.y,
            moved: false,
        };
        container.setPointerCapture?.(event.pointerId);
        root.classList.add('is-panning');
        container.classList.add('is-pan-mode', 'is-panning');
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }

    function movePan(event) {
        if (!panSession || event.pointerId !== panSession.pointerId) return;
        const dx = event.clientX - panSession.startX;
        const dy = event.clientY - panSession.startY;
        if (Math.abs(dx) > 1 || Math.abs(dy) > 1) panSession.moved = true;
        const scale = graph.getView().scale || 1;
        graph.getView().setTranslate(
            panSession.translateX + dx / scale,
            panSession.translateY + dy / scale,
        );
        syncGridToViewport();
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }

    function endPan(event) {
        if (!panSession || (event?.pointerId != null && event.pointerId !== panSession.pointerId)) return;
        const completed = panSession;
        panSession = null;
        try { container.releasePointerCapture?.(completed.pointerId); } catch (_error) { /* no-op */ }
        root.classList.remove('is-panning');
        container.classList.remove('is-panning');
        container.classList.toggle('is-pan-mode', activeTool === 'pan' || spaceDown);
        if (completed.moved) {
            markDirty();
            refreshUi();
        }
        event?.preventDefault?.();
        event?.stopPropagation?.();
        event?.stopImmediatePropagation?.();
    }

    container.addEventListener('pointerdown', beginPan, { capture: true });
    container.addEventListener('pointermove', movePan, { capture: true });
    container.addEventListener('pointerup', endPan, { capture: true });
    container.addEventListener('pointercancel', endPan, { capture: true });
    container.addEventListener('lostpointercapture', endPan);

    function cloneMoveGeometry(cell) {
        const geometry = cell?.getGeometry?.();
        return geometry?.clone?.() || null;
    }

    function translatedMoveGeometry(cell, original, dx, dy) {
        if (!original) return null;
        const geometry = original.clone?.() || original;
        if (cell.isVertex?.()) {
            geometry.x = finite(original.x, 0) + dx;
            geometry.y = finite(original.y, 0) + dy;
            return geometry;
        }
        const value = cellValue(cell);
        if (cell.isEdge?.() && isDraftingLineValue(value)) {
            const source = original.getTerminalPoint?.(true) || original.sourcePoint;
            const target = original.getTerminalPoint?.(false) || original.targetPoint;
            if (source) geometry.setTerminalPoint(new Point(source.x + dx, source.y + dy), true);
            if (target) geometry.setTerminalPoint(new Point(target.x + dx, target.y + dy), false);
            if (Array.isArray(original.points)) geometry.points = original.points.map((point) => new Point(point.x + dx, point.y + dy));
            return geometry;
        }
        return null;
    }

    function isResizeHandleEvent(event) {
        const target = event?.target;
        if (!(target instanceof Element)) return false;
        const cursor = String(window.getComputedStyle(target).cursor || target.style?.cursor || '').toLowerCase();
        if (cursor.includes('resize')) return true;
        return Boolean(target.closest?.('[data-nxsld-resize-handle], .mxVertexHandle, .mxHandle'));
    }

    function dragPreviewMarkup(entries) {
        return entries.map(({ cell, geometry }) => {
            const value = cellValue(cell) || {};
            if (cell.isVertex?.()) {
                const topLeft = graphToFrameScreen({ x: geometry.x, y: geometry.y });
                const bottomRight = graphToFrameScreen({ x: geometry.x + geometry.width, y: geometry.y + geometry.height });
                const width = Math.max(1, bottomRight.x - topLeft.x);
                const height = Math.max(1, bottomRight.y - topLeft.y);
                const label = escapeHtml(value.tag || value.title || value.text || value.id || 'OBJECT');
                return `<g class="nxsld-drag-preview-object"><rect x="${topLeft.x}" y="${topLeft.y}" width="${width}" height="${height}" rx="3"/><text x="${topLeft.x + 7}" y="${topLeft.y + 15}">${label}</text></g>`;
            }
            if (cell.isEdge?.() && isDraftingLineValue(value)) {
                const source = geometry.getTerminalPoint?.(true) || geometry.sourcePoint;
                const target = geometry.getTerminalPoint?.(false) || geometry.targetPoint;
                if (!source || !target) return '';
                const a = graphToFrameScreen(source);
                const b = graphToFrameScreen(target);
                return `<path class="nxsld-drag-preview-line" d="M${a.x} ${a.y}L${b.x} ${b.y}"/>`;
            }
            return '';
        }).join('');
    }

    function clearObjectMovePreview() {
        if (dragPreviewFrame !== null) {
            window.cancelAnimationFrame(dragPreviewFrame);
            dragPreviewFrame = null;
        }
        if (els.dragPreview) {
            els.dragPreview.innerHTML = '';
            els.dragPreview.removeAttribute('transform');
        }
        root.dataset.dragPerformanceMode = 'idle';
    }

    function renderObjectMoveFrame() {
        dragPreviewFrame = null;
        const session = moveSelectionSession;
        if (!session || !session.pendingGraph) return;
        const current = session.pendingGraph;
        session.pendingGraph = null;
        const dx = current.x - session.startGraph.x;
        const dy = current.y - session.startGraph.y;
        session.dx = dx;
        session.dy = dy;
        const scale = Math.max(graph.getView().scale, 0.001);
        if (els.dragPreview) els.dragPreview.setAttribute('transform', `translate(${dx * scale} ${dy * scale})`);
        dragPerformance.rendered_frames += 1;
    }

    function beginObjectMove(event) {
        if (activeSpace !== 'model' || activeTool !== 'select' || readOnly || spaceDown || panSession || selectionDrag || event.button !== 0 || isResizeHandleEvent(event)) return;
        const startGraph = graphPoint(event);
        const hit = geometryCellAtPoint(startGraph);
        if (!hit || hit === graph.getDefaultParent() || !graph.isCellMovable(hit)) return;

        if (!graph.isCellSelected?.(hit)) {
            if (event.shiftKey || event.ctrlKey || event.metaKey) graph.addSelectionCell?.(hit);
            else graph.setSelectionCell(hit);
        }
        const movable = selectedCells().filter((cell) => graph.isCellMovable(cell));
        const entries = movable.map((cell) => ({ cell, geometry: cloneMoveGeometry(cell) })).filter((entry) => entry.geometry);
        if (entries.length === 0) return;
        moveSelectionSession = {
            pointerId: event.pointerId,
            startGraph,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startedAt: performance.now(),
            moved: false,
            pendingGraph: null,
            dx: 0,
            dy: 0,
            cells: entries,
        };
        dragPerformance = {
            version: '67M.1-HF2',
            mode: 'preview',
            pointer_events: 0,
            rendered_frames: 0,
            commits: dragPerformance.commits,
            route_syncs: dragPerformance.route_syncs,
            selected_count: entries.length,
            duration_ms: 0,
        };
        if (els.dragPreview) {
            els.dragPreview.innerHTML = dragPreviewMarkup(entries);
            els.dragPreview.setAttribute('transform', 'translate(0 0)');
        }
        root.dataset.dragPerformanceMode = 'preview';
        try { container.setPointerCapture?.(event.pointerId); } catch (_error) { /* no-op */ }
        root.classList.add('is-object-moving');
        renderPrecisionResizeOverlay();
        updateCommandUi(`MOVE · ${entries.length} object${entries.length === 1 ? '' : 's'} · release to commit`);
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }

    function moveSelectedObjects(event) {
        if (!moveSelectionSession || event.pointerId !== moveSelectionSession.pointerId) return;
        dragPerformance.pointer_events += 1;
        if (!moveSelectionSession.moved && Math.hypot(event.clientX - moveSelectionSession.startClientX, event.clientY - moveSelectionSession.startClientY) <= 3) return;
        moveSelectionSession.moved = true;
        moveSelectionSession.pendingGraph = graphPoint(event);
        if (dragPreviewFrame === null) dragPreviewFrame = window.requestAnimationFrame(renderObjectMoveFrame);
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }

    function endObjectMove(event, cancelled = false) {
        if (!moveSelectionSession || (event?.pointerId != null && event.pointerId !== moveSelectionSession.pointerId)) return;
        const completed = moveSelectionSession;
        if (completed.pendingGraph) {
            const current = completed.pendingGraph;
            completed.dx = current.x - completed.startGraph.x;
            completed.dy = current.y - completed.startGraph.y;
            completed.pendingGraph = null;
        }
        moveSelectionSession = null;
        try { container.releasePointerCapture?.(completed.pointerId); } catch (_error) { /* no-op */ }
        root.classList.remove('is-object-moving');
        clearObjectMovePreview();

        if (completed.moved && !cancelled) {
            dragCommitInProgress = true;
            model.beginUpdate();
            try {
                completed.cells.forEach(({ cell, geometry }) => {
                    const translated = translatedMoveGeometry(cell, geometry, completed.dx, completed.dy);
                    if (translated) model.setGeometry(cell, translated);
                });
            } finally {
                try { model.endUpdate(); } finally { dragCommitInProgress = false; }
            }
            markDirty();
            dragPerformance.commits += 1;
            dragPerformance.route_syncs += 1;
            scheduleSmartWireSync(completed.cells.map(({ cell }) => cell));
            scheduleElectricalCheck();
        }
        dragPerformance.mode = 'idle';
        dragPerformance.duration_ms = Math.max(0, Math.round(performance.now() - completed.startedAt));
        renderPrecisionResizeOverlay();
        updateCommandUi(completed.moved && !cancelled ? 'Move committed. Smart routes are updating once.' : 'Move cancelled.');
        event?.preventDefault?.();
        event?.stopPropagation?.();
        event?.stopImmediatePropagation?.();
    }

    container.addEventListener('pointerdown', beginObjectMove, { capture: true });
    container.addEventListener('pointermove', moveSelectedObjects, { capture: true });
    container.addEventListener('pointerup', (event) => endObjectMove(event, false), { capture: true });
    container.addEventListener('pointercancel', (event) => endObjectMove(event, true), { capture: true });
    container.addEventListener('lostpointercapture', (event) => endObjectMove(event, false));

    function beginWindowSelection(event) {
        if (activeSpace !== 'model' || activeTool !== 'select' || readOnly || spaceDown || panSession || moveSelectionSession || event.button !== 0) return;
        const startGraph = graphPoint(event);
        const hit = geometryCellAtPoint(startGraph);
        if (hit && hit !== graph.getDefaultParent()) return;
        const startFrame = graphToFrameScreen(startGraph);
        selectionDrag = {
            pointerId: event.pointerId,
            startGraph,
            endGraph: startGraph,
            startFrame,
            endFrame: startFrame,
            moved: false,
            additive: event.shiftKey || event.ctrlKey || event.metaKey,
            startClientX: event.clientX,
            startClientY: event.clientY,
        };
        renderSelectionWindow(startFrame, startFrame, false);
        container.setPointerCapture?.(event.pointerId);
        root.classList.add('is-window-selecting');
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }

    function moveWindowSelection(event) {
        if (!selectionDrag || event.pointerId !== selectionDrag.pointerId) return;
        selectionDrag.endGraph = graphPoint(event);
        selectionDrag.endFrame = graphToFrameScreen(selectionDrag.endGraph);
        selectionDrag.moved = selectionDrag.moved || Math.hypot(event.clientX - selectionDrag.startClientX, event.clientY - selectionDrag.startClientY) > 3;
        renderSelectionWindow(selectionDrag.startFrame, selectionDrag.endFrame, selectionDrag.endGraph.x < selectionDrag.startGraph.x);
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }

    function endWindowSelection(event) {
        if (!selectionDrag || (event?.pointerId != null && event.pointerId !== selectionDrag.pointerId)) return;
        const completed = selectionDrag;
        selectionDrag = null;
        try { container.releasePointerCapture?.(completed.pointerId); } catch (_error) { /* no-op */ }
        root.classList.remove('is-window-selecting');
        renderSelectionWindow(null, null);
        if (completed.moved && completed.startGraph && completed.endGraph) {
            const selected = cellsInsideSelection(completed.startGraph, completed.endGraph);
            if (completed.additive) {
                const combined = [...new Set([...selectedCells(), ...selected])];
                graph.setSelectionCells(combined);
            } else {
                graph.setSelectionCells(selected);
            }
        } else if (!completed.additive) {
            graph.clearSelection?.();
        }
        refreshUi();
        event?.preventDefault?.();
        event?.stopPropagation?.();
        event?.stopImmediatePropagation?.();
    }

    container.addEventListener('pointerdown', beginWindowSelection, { capture: true });
    container.addEventListener('pointermove', moveWindowSelection, { capture: true });
    container.addEventListener('pointerup', endWindowSelection, { capture: true });
    container.addEventListener('pointercancel', endWindowSelection, { capture: true });

    function commitWireCell(cell, nativeEvent, preferredPortId = null) {
        if (activeTool !== 'wire' || readOnly || !cell || cellValue(cell)?.entity !== 'node') return false;
        const clickedPoint = nativeEvent ? graphPoint(nativeEvent) : pointerPoint;
        const exactDescriptor = preferredPortId
            ? portDescriptorForCell(cell, preferredPortId, clickedPoint)
            : clickedPortDescriptor(cell, clickedPoint, 30);
        const exactClickedPort = exactDescriptor?.port || null;
        const clickedSnapPort = exactClickedPort || (activeSnap?.cell === cell && activeSnap?.type === 'Port' ? activeSnap.port : null);
        const cellValueObject = cellValue(cell) || {};

        if (!pendingWireSource) {
            const allPorts = portProfile(cellValueObject.symbol || cellValueObject.kind, cellValueObject.ports);
            const availableSourcePorts = allPorts.filter((port) => ['output', 'bidirectional'].includes(port.direction));
            if (exactClickedPort && !availableSourcePorts.some((port) => port.id === exactClickedPort.id)) {
                flashWirePort(cell, exactClickedPort.id, 'invalid');
                showWireToast('SOURCE PORT REJECTED', `${exactClickedPort.id} is input-only. Choose an output or bidirectional port.`);
                if (els.hint) els.hint.innerHTML = `<kbd>W</kbd> ${escapeHtml(exactClickedPort.id)} is not a source port`;
                updateCommandUi(`Connection rejected. ${exactClickedPort.id} is an input-only port.`);
                return true;
            }
            const clickedSourcePort = clickedSnapPort && availableSourcePorts.some((port) => port.id === clickedSnapPort.id) ? clickedSnapPort : null;
            const nearestSourcePort = clickedSourcePort || portDescriptorForCell(cell, null, clickedPoint)?.port || availableSourcePorts[0] || null;
            const selectedSourcePort = availableSourcePorts.find((port) => port.id === nearestSourcePort?.id) || availableSourcePorts[0] || null;
            if (!selectedSourcePort) {
                showWireToast('NO OUTPUT PORT', 'The selected equipment has no output or bidirectional engineering port.');
                if (els.hint) els.hint.innerHTML = '<kbd>W</kbd> selected equipment has no output or bidirectional port';
                updateCommandUi('No valid source port is available on this equipment.');
                return true;
            }
            pendingWireSource = cell;
            pendingWireSourcePort = selectedSourcePort;
            flashWirePort(cell, selectedSourcePort.id, 'valid');
            graph.clearSelection?.();
            const network = wireNetworkDefinition(selectedSourcePort.network);
            if (els.hint) els.hint.innerHTML = `<kbd>W</kbd> ${escapeHtml(selectedSourcePort.id)} selected · choose compatible ${escapeHtml(network.short)} destination`;
            updateCommandUi(`Source locked to ${selectedSourcePort.id} · ${network.label}. Select a compatible destination port.`);
            showWireToast('SOURCE LOCKED', `${selectedSourcePort.id} · ${network.label}`, 'success');
            updateWirePortPresentation();
            updateDraftOverlay();
            return true;
        }

        const source = pendingWireSource;
        const target = cell;
        if (source === target) {
            showWireToast('DESTINATION REJECTED', 'Source and destination must be different equipment.');
            if (els.hint) els.hint.innerHTML = '<kbd>W</kbd> choose a different destination equipment';
            updateCommandUi('Source and destination must be different equipment.');
            return true;
        }

        const sourceValue = cellValue(source) || {};
        const targetValue = cellValue(target) || {};
        const preferredTargetId = preferredPortId || exactClickedPort?.id || clickedSnapPort?.id || null;
        const pair = compatiblePortPair(sourceValue, targetValue, pendingWireSourcePort?.id || null, preferredTargetId);
        if (!pair) {
            const targetName = preferredTargetId || targetValue.tag || targetValue.title || 'selected target';
            const sourceName = pendingWireSourcePort?.id || 'Source port';
            flashWirePort(target, preferredTargetId, 'invalid');
            showWireToast('CONNECTION REJECTED', `${sourceName} cannot connect to ${targetName}.`);
            if (els.hint) els.hint.innerHTML = `<kbd>W</kbd> incompatible destination · ${escapeHtml(String(targetName))}`;
            updateCommandUi(`Connection rejected. ${sourceName} is not compatible with ${targetName}.`);
            return true;
        }

        const network = normalizeWireNetwork(pair.network);
        const definition = wireNetworkDefinition(network);
        const wireId = nextWireId(network);
        const value = {
            entity: 'edge', id: uid('wire'), wire_id: wireId, manual: true,
            from: sourceValue.id, to: targetValue.id,
            from_port: pair.sourcePort.id, to_port: pair.targetPort.id,
            network, label: definition.label, detail: 'Cable data not specified',
            cable_tag: '', cable_type: '', cable_size: '', cores: '', voltage_class: '',
            installation_method: '', notes: '', show_label: true, routing_mode: 'auto', routing_clearance: 'standard',
        };
        let edge;
        graph.batchUpdate(() => {
            edge = graph.insertEdge({
                parent: graph.getDefaultParent(), id: `edge:${slug(value.id)}`, value, source, target,
                style: manualEdgeStyle(sourceValue.kind, targetValue.kind, true, value.network),
            });
            syncSmartWire(edge, true, value);
        });
        pendingWireSource = null;
        pendingWireSourcePort = null;
        graph.setSelectionCell(edge);
        markDirty();
        refreshUi();
        openPanel('inspector');
        showWireToast('SMART WIRE CREATED', `${wireId} · ${pair.sourcePort.id} → ${pair.targetPort.id}`, 'success');
        if (els.hint) els.hint.innerHTML = `<kbd>W</kbd> ${escapeHtml(wireId)} connected · select next source`;
        updateCommandUi(`${wireId} created from ${sourceValue.tag || sourceValue.title} · ${pair.sourcePort.id} to ${targetValue.tag || targetValue.title} · ${pair.targetPort.id}.`);
        updateWirePortPresentation();
        updateDraftOverlay();
        return true;
    }

    let directWirePointer = null;
    function wirePointerPortId(event) {
        return event.target?.closest?.('[data-nxsld-port-id]')?.dataset?.nxsldPortId || null;
    }

    function wirePortHitAt(point, capturePixels = 34) {
        const directCell = graph.getCellAt?.(point.x, point.y) || geometryCellAtPoint(point);
        const capture = capturePixels / Math.max(graph.getView().scale, 0.01);
        const nodes = (graph.getDefaultParent()?.getChildren?.() || [])
            .filter((cell) => cell?.isVertex?.() && cellValue(cell)?.entity === 'node' && cell.isVisible?.() !== false);
        const candidates = [];
        nodes.forEach((cell) => {
            const value = cellValue(cell) || {};
            const geometry = cell.getGeometry?.();
            if (!geometry) return;
            portCoordinates(value, geometry).forEach((candidate) => {
                const enriched = { ...candidate, cell, distance: distance(candidate, point), type: 'Port' };
                if (enriched.distance <= capture && wirePortCandidateCapturable(enriched)) candidates.push(enriched);
            });
        });
        const best = candidates.sort(compareWirePortCandidates)[0] || null;
        if (best) return { cell: best.cell, portId: best.port?.id || null, distance: best.distance };
        if (directCell && cellValue(directCell)?.entity === 'node') {
            const value = cellValue(directCell) || {};
            const geometry = directCell.getGeometry?.();
            const fallback = geometry
                ? portCoordinates(value, geometry)
                    .map((candidate) => ({ ...candidate, cell: directCell, distance: distance(candidate, point), type: 'Port' }))
                    .filter(wirePortCandidateCapturable)
                    .sort(compareWirePortCandidates)[0] || null
                : null;
            if (fallback) return { cell: directCell, portId: fallback.port?.id || null, distance: fallback.distance };
        }
        return null;
    }

    function flashWirePort(cell, portId, state = 'invalid') {
        const nodeId = String(cellValue(cell)?.id || '');
        if (!nodeId || !portId) return;
        const escapeSelector = window.CSS?.escape ? window.CSS.escape : (value) => String(value).replace(/([\"'\\.#:[\]()>+~*=])/g, '\\$1');
        const selector = `[data-nxsld-node-id="${escapeSelector(nodeId)}"] [data-nxsld-port-id="${escapeSelector(portId)}"]`;
        const element = root.querySelector(selector);
        if (!element) return;
        const className = state === 'valid' ? 'is-wire-port-confirmed' : 'is-wire-port-rejected';
        element.classList.remove('is-wire-port-confirmed', 'is-wire-port-rejected');
        void element.offsetWidth;
        element.classList.add(className);
        window.setTimeout(() => element.classList.remove(className), 520);
    }

    container.addEventListener('pointerdown', (event) => {
        if (activeTool !== 'wire' || activeSpace !== 'model' || readOnly || event.button !== 0 || panSession || spaceDown) return;
        const point = graphPoint(event);
        const snapHit = activeSnap?.type === 'Port' && wirePortCandidateCapturable(activeSnap)
            && distance(activeSnap, point) <= 40 / Math.max(graph.getView().scale, 0.01)
            ? { cell: activeSnap.cell, portId: activeSnap.port?.id || null, distance: distance(activeSnap, point) }
            : null;
        const hit = snapHit || wirePortHitAt(point, 38);
        if (!hit?.cell) return;
        directWirePointer = {
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY,
            cell: hit.cell,
            portId: snapHit?.portId || wirePointerPortId(event) || hit.portId,
        };
        container.setPointerCapture?.(event.pointerId);
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }, { capture: true });

    container.addEventListener('pointerup', (event) => {
        if (!directWirePointer || event.pointerId !== directWirePointer.pointerId) return;
        const session = directWirePointer;
        directWirePointer = null;
        const click = Math.hypot(event.clientX - session.x, event.clientY - session.y) <= 14;
        try { container.releasePointerCapture?.(event.pointerId); } catch (_error) { /* no-op */ }
        if (click) {
            pointerPoint = graphPoint(event);
            commitWireCell(session.cell, event, session.portId);
            suppressGraphClickUntil = performance.now() + 180;
        }
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }, { capture: true });

    container.addEventListener('pointercancel', () => { directWirePointer = null; }, { capture: true });

    function precisionPointerTool() {
        return activeSpace === 'model'
            && !readOnly
            && ['line', 'polyline', 'rectangle', 'circle', 'arc', 'leader', 'text', 'place_symbol'].includes(activeTool)
            && !spaceDown
            && activeTool !== 'pan';
    }

    container.addEventListener('pointerdown', (event) => {
        if (!precisionPointerTool() || event.button !== 0 || panSession) return;
        directDraftPointer = {
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY,
        };
        container.setPointerCapture?.(event.pointerId);
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }, { capture: true });

    container.addEventListener('pointerup', (event) => {
        if (!directDraftPointer || event.pointerId !== directDraftPointer.pointerId) return;
        const click = Math.hypot(event.clientX - directDraftPointer.x, event.clientY - directDraftPointer.y) <= 7;
        directDraftPointer = null;
        try { container.releasePointerCapture?.(event.pointerId); } catch (_error) { /* no-op */ }
        if (click && precisionPointerTool()) {
            pointerPoint = graphPoint(event);
            commitDraftPoint(event);
            suppressGraphClickUntil = performance.now() + 120;
        }
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    }, { capture: true });

    container.addEventListener('pointercancel', () => { directDraftPointer = null; }, { capture: true });

    graph.addListener(InternalEvent.CLICK, (_sender, event) => {
        const cell = event.getProperty('cell');
        const nativeEvent = event.getProperty('event');
        if (performance.now() < suppressGraphClickUntil && (precisionPointerTool() || activeTool === 'wire')) { event.consume?.(); return; }

        if (!readOnly && nativeEvent && activeSpace === 'model' && ['line', 'polyline', 'rectangle', 'circle', 'arc', 'leader', 'text', 'place_symbol'].includes(activeTool)) {
            pointerPoint = graphPoint(nativeEvent);
            commitDraftPoint(nativeEvent);
            if (nativeEvent.detail >= 2 && ['line', 'polyline'].includes(activeTool)) finishDraftCommand(true);
            event.consume?.();
            return;
        }

        if (activeTool === 'wire' && !readOnly && cell && cellValue(cell)?.entity === 'node') {
            const preferredPortId = nativeEvent ? wirePointerPortId(nativeEvent) : null;
            if (commitWireCell(cell, nativeEvent, preferredPortId)) event.consume?.();
        }
    });

    container.addEventListener('contextmenu', (event) => {
        if (!['line', 'polyline', 'leader', 'rectangle', 'circle', 'arc'].includes(activeTool)) return;
        event.preventDefault();
        finishDraftCommand(true);
    });

    container.addEventListener('wheel', (event) => {
        if (event.ctrlKey || event.metaKey) return;
        event.preventDefault();
        setZoom(graph.getView().scale * (event.deltaY < 0 ? 1.1 : 0.9));
    }, { passive: false });

    root.querySelectorAll('[data-editor-tool]').forEach((button) => button.addEventListener('click', () => setTool(button.dataset.editorTool)));
    root.querySelectorAll('[data-editor-ribbon-tab]').forEach((button) => button.addEventListener('click', () => {
        const tab = button.dataset.editorRibbonTab;
        root.querySelectorAll('[data-editor-ribbon-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
        root.querySelectorAll('[data-editor-ribbon-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.editorRibbonPanel === tab));
    }));
    root.querySelectorAll('[data-editor-inspector-tab]').forEach((button) => button.addEventListener('click', () => {
        const tab = button.dataset.editorInspectorTab;
        root.querySelectorAll('[data-editor-inspector-tab]').forEach((item) => item.classList.toggle('is-active', item === button));
        root.querySelectorAll('[data-editor-inspector-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.editorInspectorPanel === tab));
    }));

    els.checkStatus?.addEventListener('click', () => runElectricalCheck({ open: true }));
    els.controllerStatus?.addEventListener('click', () => openInspectorTab('controller'));
    els.controllerSync?.addEventListener('click', () => runEngineeringControllerAction('accept_source'));
    els.controllerAck?.addEventListener('click', () => runEngineeringControllerAction('acknowledge_review', els.controllerReason?.value?.trim() || ''));
    root.querySelector('[data-editor-run-check]')?.addEventListener('click', () => runElectricalCheck({ open: true }));
    root.querySelectorAll('[data-editor-check-filter]').forEach((button) => button.addEventListener('click', () => {
        electricalCheckFilter = button.dataset.editorCheckFilter || 'all';
        root.querySelectorAll('[data-editor-check-filter]').forEach((item) => item.classList.toggle('is-active', item === button));
        renderElectricalValidation();
    }));

    root.querySelectorAll('[data-editor-toggle-panel]').forEach((button) => button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const panel = button.dataset.editorTogglePanel;
        if (panel === 'palette' || panel === 'inspector') togglePanel(panel);
    }));

    root.querySelectorAll('[data-editor-open-panel]').forEach((button) => button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const panel = button.dataset.editorOpenPanel;
        if (panel === 'palette' || panel === 'inspector') openPanel(panel);
    }));

    root.querySelectorAll('[data-editor-action]').forEach((button) => button.addEventListener('click', async () => {
        const action = button.dataset.editorAction;
        if (action === 'undo') undoManager.undo();
        if (action === 'redo') undoManager.redo();
        if (action === 'cut') cutSelection();
        if (action === 'copy') copySelection();
        if (action === 'paste') pasteSelection();
        if (action === 'delete') removeSelected();
        if (action === 'duplicate') duplicateSelection();
        if (action?.startsWith('align-')) alignSelection(action.replace('align-', ''));
        if (action === 'distribute-horizontal') distributeSelection('horizontal');
        if (action === 'distribute-vertical') distributeSelection('vertical');
        if (action === 'bring-front') bringFront();
        if (action === 'send-back') sendBack();
        if (action === 'lock') setObjectLock(true);
        if (action === 'unlock') setObjectLock(false);
        if (action === 'reset-size') resetSelectionSize();
        if (action === 'rotate-left') applyRotation(-90);
        if (action === 'rotate-right') applyRotation(90);
        if (action === 'mirror-horizontal') applyMirror('horizontal');
        if (action === 'mirror-vertical') applyMirror('vertical');
        if (action === 'reset-transform') resetSelectionTransform();
        if (action === 'wire-reset-route') resetSelectedWireRoute();
        if (action === 'rebuild-all-routes') rebuildAllSmartRoutes();
        if (action === 'wire-select-source') selectWireTerminal(true);
        if (action === 'wire-select-target') selectWireTerminal(false);
        if (action === 'group') groupSelection();
        if (action === 'ungroup') ungroupSelection();
        if (action === 'reset-layout') resetGeneratedLayout();
        if (action === 'run-electrical-check') runElectricalCheck({ open: true });
        if (action === 'fit') fitDrawing();
        if (action === 'zoom-in') setZoom(graph.getView().scale * 1.15);
        if (action === 'zoom-out') setZoom(graph.getView().scale / 1.15);
        if (action === 'fullscreen') {
            if (document.fullscreenElement) await document.exitFullscreen?.();
            else await root.requestFullscreen?.();
        }
        if (action === 'open-library') openPanel('palette');
        if (action === 'toggle-ribbon') {
            root.classList.toggle('is-ribbon-collapsed');
            const collapsed = root.classList.contains('is-ribbon-collapsed');
            button.classList.toggle('is-active', collapsed);
            button.setAttribute('aria-pressed', String(collapsed));
            button.title = collapsed ? 'Expand ribbon' : 'Collapse ribbon';
            persistPanels();
            window.setTimeout(() => { graph.refresh(); fitIfEmptySpace(); }, 160);
        }
        if (action === 'toggle-panels') {
            const bothOpen = !root.classList.contains('is-palette-collapsed') && !root.classList.contains('is-inspector-collapsed');
            root.classList.toggle('is-palette-collapsed', bothOpen);
            root.classList.toggle('is-inspector-collapsed', bothOpen);
            syncAllPanels();
            persistPanels();
            window.setTimeout(() => graph.refresh(), 210);
        }
        refreshUi();
    }));

    root.querySelectorAll('[data-editor-visibility]').forEach((button) => button.addEventListener('click', () => setSymbolVisibility(button.dataset.editorVisibility)));

    root.querySelector('[data-editor-osnap-menu-toggle]')?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (!els.osnapMenu) return;
        const opening = els.osnapMenu.hidden;
        els.osnapMenu.hidden = !opening;
        event.currentTarget.setAttribute('aria-expanded', String(opening));
        syncOsnapSettingsUi();
    });
    root.querySelectorAll('[data-editor-osnap-mode]').forEach((input) => input.addEventListener('change', () => setOsnapMode(input.dataset.editorOsnapMode, input.checked)));
    root.querySelector('[data-editor-osnap-all]')?.addEventListener('click', () => {
        Object.keys(OSNAP_DEFAULTS).forEach((key) => { osnapModes[key] = true; });
        syncOsnapSettingsUi();
        markDirty();
        updateDraftOverlay();
        renderSelectedSnapPoints();
    });
    root.querySelector('[data-editor-osnap-none]')?.addEventListener('click', () => {
        Object.keys(OSNAP_DEFAULTS).forEach((key) => { osnapModes[key] = false; });
        syncOsnapSettingsUi();
        markDirty();
        updateDraftOverlay();
        renderSelectedSnapPoints();
    });
    document.addEventListener('click', (event) => {
        if (!els.osnapMenu || els.osnapMenu.hidden) return;
        if (event.target.closest?.('[data-editor-osnap-menu-shell]')) return;
        els.osnapMenu.hidden = true;
        root.querySelector('[data-editor-osnap-menu-toggle]')?.setAttribute('aria-expanded', 'false');
    });

    els.paperMode?.addEventListener('change', () => {
        paperMode = ['as_drawn', 'auto', 'single', 'multi', 'catalog'].includes(els.paperMode.value) ? els.paperMode.value : 'as_drawn';
        markDirty();
        updatePaperSummary({
            mode: paperMode,
            sheet_count: root.querySelectorAll('[data-editor-paper-page]').length,
            message: 'Save drawing to rebuild Paper Space using the selected composition mode.',
        });
        if (els.paperPreviewNote) els.paperPreviewNote.textContent = 'Save drawing to refresh Paper Space';
    });
    if (els.paperMode) els.paperMode.value = paperMode;

    root.querySelectorAll('[data-editor-layer-action]').forEach((button) => button.addEventListener('click', () => {
        const row = button.closest('[data-editor-layer]');
        if (!row) return;
        const property = button.dataset.editorLayerAction;
        setLayerProperty(row.dataset.editorLayer, property, !layerState[row.dataset.editorLayer]?.[property]);
    }));
    root.querySelector('[data-editor-layer-show-all]')?.addEventListener('click', () => {
        Object.values(layerState).forEach((state) => { state.visible = true; });
        applyLayerState(true);
    });
    root.querySelector('[data-editor-layer-print-all]')?.addEventListener('click', () => {
        Object.values(layerState).forEach((state) => { state.print = true; });
        renderLayerControls();
        markDirty();
    });

    root.querySelectorAll('[data-editor-symbol-glyph]').forEach((glyph) => {
        const kind = glyph.dataset.editorSymbolGlyph || '';
        glyph.innerHTML = nodeIcon(kind);
        glyph.classList.add('is-rendered');
    });

    root.querySelectorAll('[data-editor-add-kind]').forEach((button) => {
        button.addEventListener('click', () => beginSymbolPlacement(button.dataset.editorAddKind));
        button.addEventListener('dragstart', (event) => {
            if (readOnly) return event.preventDefault();
            event.dataTransfer?.setData('application/x-nexorin-sld-symbol', button.dataset.editorAddKind);
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy';
        });
    });

    container.addEventListener('dragover', (event) => {
        const hasType = event.dataTransfer?.types?.includes('application/x-nexorin-sld-symbol');
        if (readOnly || !hasType) return;
        event.preventDefault();
        container.classList.add('is-drop-target');
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
    });
    container.addEventListener('dragleave', (event) => {
        if (!container.contains(event.relatedTarget)) container.classList.remove('is-drop-target');
    });
    container.addEventListener('drop', (event) => {
        container.classList.remove('is-drop-target');
        if (readOnly) return;
        const kind = event.dataTransfer?.getData('application/x-nexorin-sld-symbol');
        if (!kind) return;
        event.preventDefault();
        addManual(kind, graphPoint(event));
    });

    root.querySelectorAll('[data-editor-group-toggle]').forEach((button) => button.addEventListener('click', () => {
        button.setAttribute('aria-expanded', button.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
    }));

    function closeGeometryMenus(except = null) {
        root.querySelectorAll('[data-editor-geometry-menu]').forEach((menu) => {
            if (menu !== except) menu.hidden = true;
        });
        root.querySelectorAll('[data-editor-geometry-menu-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', String(button.nextElementSibling === except && !except?.hidden));
        });
    }

    root.querySelectorAll('[data-editor-geometry-menu-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const menu = button.parentElement?.querySelector('[data-editor-geometry-menu]');
            if (!menu) return;
            const open = menu.hidden;
            closeGeometryMenus(open ? menu : null);
            menu.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
        });
    });

    root.querySelectorAll('[data-editor-circle-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            circleMode = button.dataset.editorCircleMode;
            root.querySelectorAll('[data-editor-circle-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelector('[data-editor-circle-mode-label]')?.replaceChildren(document.createTextNode(circleModeLabel()));
            closeGeometryMenus();
            setTool('circle');
        });
    });

    root.querySelectorAll('[data-editor-arc-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            arcMode = button.dataset.editorArcMode;
            root.querySelectorAll('[data-editor-arc-mode]').forEach((item) => item.classList.toggle('is-active', item === button));
            root.querySelector('[data-editor-arc-mode-label]')?.replaceChildren(document.createTextNode(arcModeLabel()));
            closeGeometryMenus();
            setTool('arc');
        });
    });

    document.addEventListener('pointerdown', (event) => {
        if (!event.target.closest?.('.nxsld-command-menu')) closeGeometryMenus();
    });

    const symbolSearch = root.querySelector('[data-editor-symbol-search]');
    function applySymbolFilter() {
        const query = symbolSearch?.value.trim().toLowerCase() || '';
        root.querySelectorAll('[data-editor-symbol-name]').forEach((button) => {
            const matchesText = !query || button.dataset.editorSymbolName.includes(query);
            const matchesCategory = activeSymbolFilter === 'all' || button.dataset.editorSymbolCategory === activeSymbolFilter;
            button.hidden = !(matchesText && matchesCategory);
        });
        root.querySelectorAll('[data-editor-symbol-group]').forEach((group) => {
            const visible = [...group.querySelectorAll('[data-editor-symbol-name]')].some((button) => !button.hidden);
            group.hidden = !visible;
        });
    }
    symbolSearch?.addEventListener('input', applySymbolFilter);
    root.querySelectorAll('[data-editor-symbol-filter]').forEach((button) => button.addEventListener('click', () => {
        activeSymbolFilter = button.dataset.editorSymbolFilter || 'all';
        root.querySelectorAll('[data-editor-symbol-filter]').forEach((item) => item.classList.toggle('is-active', item === button));
        applySymbolFilter();
    }));
    function positionSymbolTooltip(button, event = null) {
        if (!els.symbolTooltip) return;
        const rect = button.getBoundingClientRect();
        const tooltipWidth = 286;
        const preferredX = event?.clientX ? event.clientX + 14 : rect.right + 10;
        const preferredY = event?.clientY ? event.clientY + 12 : rect.top;
        els.symbolTooltip.style.left = `${clamp(preferredX, 10, window.innerWidth - tooltipWidth - 10)}px`;
        els.symbolTooltip.style.top = `${clamp(preferredY, 10, window.innerHeight - 150)}px`;
    }

    root.querySelectorAll('[data-editor-symbol-name]').forEach((button) => {
        const showTooltip = (event = null) => {
            if (!els.symbolTooltip) return;
            const title = button.querySelector('strong')?.textContent || 'Electrical symbol';
            const pages = button.dataset.editorSymbolPages ? ` · ref. page ${button.dataset.editorSymbolPages}` : '';
            const preview = button.querySelector('[data-editor-symbol-glyph]')?.innerHTML || '';
            els.symbolTooltip.innerHTML = `<div class="nxsld-symbol-tooltip-preview">${preview}</div><div class="nxsld-symbol-tooltip-copy"><strong>${escapeHtml(title)}</strong><span>${escapeHtml(button.dataset.editorSymbolDescription || '')}</span><em>${escapeHtml(button.dataset.editorSymbolScope || 'INDIVIDUAL')}</em><small>${escapeHtml(button.dataset.editorSymbolStatus || 'Reference drawing form')} · ${escapeHtml(button.dataset.editorSymbolStandard || 'Engineering reference')}${escapeHtml(pages)}</small><code>${escapeHtml(button.dataset.editorSymbolPortCount || '0')} engineered port(s) · ${escapeHtml(button.dataset.editorSymbolSize || '144 × 120')} · tag ${escapeHtml(button.dataset.editorSymbolPrefix || '—')}</code></div>`;
            positionSymbolTooltip(button, event);
            els.symbolTooltip.hidden = false;
        };
        button.addEventListener('pointerenter', showTooltip);
        button.addEventListener('pointermove', (event) => positionSymbolTooltip(button, event));
        button.addEventListener('pointerleave', () => { if (els.symbolTooltip) els.symbolTooltip.hidden = true; });
        button.addEventListener('focus', () => showTooltip());
        button.addEventListener('blur', () => { if (els.symbolTooltip) els.symbolTooltip.hidden = true; });
    });

    root.querySelectorAll('[data-editor-display]').forEach((button) => button.addEventListener('click', () => setDisplayMode(button.dataset.editorDisplay)));
    root.querySelectorAll('[data-editor-space]').forEach((button) => button.addEventListener('click', () => setSpace(button.dataset.editorSpace)));

    const gridInputs = root.querySelectorAll('[data-editor-grid]');
    const snapInputs = root.querySelectorAll('[data-editor-snap]');
    const orthoInputs = root.querySelectorAll('[data-editor-ortho]');

    function setGrid(enabled) {
        gridEnabled = Boolean(enabled);
        container.classList.toggle('is-grid-hidden', !gridEnabled);
        root.querySelectorAll('[data-editor-grid]').forEach((input) => { input.checked = gridEnabled; });
        root.querySelector('[data-editor-status-toggle="grid"]')?.classList.toggle('is-active', gridEnabled);
        markDirty();
    }

    function setSnap(enabled) {
        snapEnabled = Boolean(enabled);
        graph.setGridEnabled(snapEnabled);
        root.querySelectorAll('[data-editor-snap]').forEach((input) => { input.checked = snapEnabled; });
        root.querySelector('[data-editor-status-toggle="snap"]')?.classList.toggle('is-active', snapEnabled);
        markDirty();
    }

    function setOrtho(enabled, updateEdges = true) {
        orthoEnabled = enabled;
        root.querySelectorAll('[data-editor-ortho]').forEach((input) => { input.checked = enabled; });
        root.querySelector('[data-editor-status-toggle="ortho"]')?.classList.toggle('is-active', enabled);
        if (!updateEdges) return;
        const children = graph.getDefaultParent()?.getChildren?.() || [];
        graph.batchUpdate(() => {
            children.forEach((cell) => {
                const value = cellValue(cell);
                if (!cell.isEdge?.() || value?.manual !== true || value?.entity !== 'edge') return;
                syncSmartWire(cell, false);
            });
        });
        markDirty();
    }


    function setPolar(enabled, shouldDirty = true) {
        polarEnabled = enabled;
        root.querySelector('[data-editor-status-toggle="polar"]')?.classList.toggle('is-active', enabled);
        if (shouldDirty) markDirty();
        updateDraftOverlay();
    }

    function syncOsnapSettingsUi() {
        root.querySelectorAll('[data-editor-osnap-mode]').forEach((input) => {
            input.checked = osnapModes[input.dataset.editorOsnapMode] !== false;
        });
        const activeCount = Object.values(osnapModes).filter(Boolean).length;
        if (els.osnapSummary) els.osnapSummary.textContent = `${activeCount} of ${Object.keys(OSNAP_DEFAULTS).length} modes enabled`;
        updateOsnapToolScopeUi();
    }

    function setOsnapMode(mode, enabled) {
        if (!(mode in OSNAP_DEFAULTS)) return;
        osnapModes = { ...osnapModes, [mode]: Boolean(enabled) };
        syncOsnapSettingsUi();
        markDirty();
        updateDraftOverlay();
        renderSelectedSnapPoints();
    }

    function setOsnap(enabled, shouldDirty = true) {
        osnapEnabled = enabled;
        root.querySelector('[data-editor-status-toggle="osnap"]')?.classList.toggle('is-active', enabled);
        syncOsnapSettingsUi();
        if (shouldDirty) markDirty();
        updateDraftOverlay();
        renderSelectedSnapPoints();
    }

    function setOtrack(enabled, shouldDirty = true) {
        otrackEnabled = enabled;
        root.querySelector('[data-editor-status-toggle="otrack"]')?.classList.toggle('is-active', enabled);
        if (shouldDirty) markDirty();
        updateDraftOverlay();
    }

    function setDynamic(enabled, shouldDirty = true) {
        dynamicEnabled = enabled;
        root.querySelector('[data-editor-status-toggle="dynamic"]')?.classList.toggle('is-active', enabled);
        if (els.dynamicInput && !enabled) els.dynamicInput.hidden = true;
        if (shouldDirty) markDirty();
        updateDraftOverlay();
    }

    gridInputs.forEach((input) => input.addEventListener('change', () => setGrid(input.checked)));
    snapInputs.forEach((input) => input.addEventListener('change', () => setSnap(input.checked)));
    orthoInputs.forEach((input) => input.addEventListener('change', () => setOrtho(input.checked)));
    root.querySelectorAll('[data-editor-status-toggle]').forEach((button) => button.addEventListener('click', () => {
        const type = button.dataset.editorStatusToggle;
        if (type === 'grid') setGrid(!button.classList.contains('is-active'));
        if (type === 'snap') setSnap(!button.classList.contains('is-active'));
        if (type === 'ortho') setOrtho(!button.classList.contains('is-active'));
        if (type === 'polar') setPolar(!button.classList.contains('is-active'));
        if (type === 'osnap') setOsnap(!button.classList.contains('is-active'));
        if (type === 'otrack') setOtrack(!button.classList.contains('is-active'));
        if (type === 'dynamic') setDynamic(!button.classList.contains('is-active'));
    }));

    function applyWireField(key, field, explicitValue = undefined) {
        const edge = selectedWire();
        const value = cellValue(edge);
        if (!edge || !value || readOnly || value.manual !== true || value.locked === true || key === 'network') return;
        const next = { ...value };
        const rawValue = explicitValue !== undefined ? explicitValue : field.value;
        if (key === 'show_label') next.show_label = Boolean(rawValue);
        else if (key === 'network') {
            next.network = normalizeWireNetwork(rawValue);
            next.label = wireNetworkDefinition(next.network).label;
        } else if (key === 'cores') next.cores = rawValue === '' ? '' : clamp(Math.round(finite(rawValue, 1)), 1, 99);
        else if (key === 'routing_mode') {
            next.routing_mode = rawValue === 'manual' ? 'manual' : 'auto';
            if (next.routing_mode === 'manual') {
                next.router_version = SLD_SMART_ROUTER_VERSION;
                next.route_contract = SLD_ORTHOGONAL_ROUTING_CONTRACT.name;
                next.route_strategy = 'manual-preserved';
                next.route_orthogonal = routeIsOrthogonal(edgeFullRoutePoints(edge));
                next.route_terminal_direction_ok = true;
            }
        }
        else if (key === 'routing_clearance') next.routing_clearance = Object.hasOwn(ROUTING_CLEARANCE_PROFILES, rawValue) ? rawValue : 'standard';
        else next[key] = rawValue;
        next.detail = wireCableDetail(next);
        graph.batchUpdate(() => {
            model.setValue(edge, next);
            if (key === 'routing_mode' || key === 'routing_clearance') syncSmartWire(edge, next.routing_mode !== 'manual', next);
        });
        markDirty();
        graph.refresh(edge);
        refreshUi();
    }

    Object.entries(wireFields).forEach(([key, field]) => {
        if (key === 'show_label') {
            field.addEventListener('click', () => {
                const edge = selectedWire();
                const value = cellValue(edge);
                if (!edge || !value || field.disabled) return;
                applyWireField(key, field, value.show_label === false);
            });
            return;
        }
        const apply = () => {
            if (field.disabled) return;
            applyWireField(key, field);
        };
        if (field.matches('input, textarea')) field.addEventListener('input', apply);
        field.addEventListener('change', apply);
        field.addEventListener('blur', apply);
    });

    Object.entries(fields).forEach(([key, field]) => {
        const apply = () => {
            const cell = inspectorCell();
            const value = cellValue(cell);
            if (!cell || !value || field.disabled || readOnly) return;
            graph.batchUpdate(() => {
                if (key === 'rotation') {
                    const target = normalizeQuarterTurn(field.value);
                    const current = normalizeQuarterTurn(value.rotation);
                    if (value.entity === 'node') {
                        const allowed = registryTransformPolicy(value.symbol || value.kind).rotations || [0, 90, 180, 270];
                        if (!allowed.map(normalizeQuarterTurn).includes(target)) {
                            field.value = String(current);
                            updateCommandUi('This IEC registry symbol has a fixed engineering orientation.');
                            return;
                        }
                    }
                    const delta = ((target - current + 540) % 360) - 180;
                    if (delta !== 0) {
                        const geometry = cell.getGeometry()?.clone();
                        if (geometry && Math.abs(delta) % 180 === 90) {
                            const cx = geometry.x + geometry.width / 2;
                            const cy = geometry.y + geometry.height / 2;
                            const nextWidth = geometry.height;
                            const nextHeight = geometry.width;
                            geometry.x = graph.snap(cx - nextWidth / 2);
                            geometry.y = graph.snap(cy - nextHeight / 2);
                            geometry.width = nextWidth;
                            geometry.height = nextHeight;
                            model.setGeometry(cell, geometry);
                        }
                        model.setValue(cell, { ...value, rotation: target });
                    }
                } else if (['x', 'y', 'w', 'h'].includes(key)) {
                    const geometry = cell.getGeometry()?.clone();
                    if (!geometry) return;
                    if (isDraftingLineValue(value)) {
                        const sourcePoint = geometry.getTerminalPoint?.(true) || geometry.sourcePoint || new Point(0, 0);
                        const targetPoint = geometry.getTerminalPoint?.(false) || geometry.targetPoint || new Point(0, 0);
                        if (key === 'x') sourcePoint.x = graph.snap(finite(field.value, sourcePoint.x));
                        if (key === 'y') sourcePoint.y = graph.snap(finite(field.value, sourcePoint.y));
                        if (key === 'w') targetPoint.x = graph.snap(finite(field.value, targetPoint.x));
                        if (key === 'h') targetPoint.y = graph.snap(finite(field.value, targetPoint.y));
                        geometry.setTerminalPoint(sourcePoint, true);
                        geometry.setTerminalPoint(targetPoint, false);
                    } else {
                        if (key === 'x') geometry.x = graph.snap(finite(field.value, geometry.x));
                        if (key === 'y') geometry.y = graph.snap(finite(field.value, geometry.y));
                        if (key === 'w') geometry.width = clamp(graph.snap(finite(field.value, geometry.width)), 80, 800);
                        if (key === 'h') geometry.height = clamp(graph.snap(finite(field.value, geometry.height)), 40, 600);
                    }
                    model.setGeometry(cell, geometry);
                } else {
                    const next = { ...value };
                    if (value.entity === 'annotation' && key === 'title') next.text = field.value;
                    else if (isDraftingLineValue(value)) {
                        if (key === 'title') next.label = field.value;
                        if (key === 'tag') next.layer = field.value || 'SLD-LINEWORK';
                        if (key === 'subtitle') next.line_type = ['continuous', 'hidden', 'center'].includes(String(field.value).toLowerCase()) ? String(field.value).toLowerCase() : 'continuous';
                        if (key === 'rating') next.lineweight = clamp(finite(field.value, next.lineweight || 1.4), 0.6, 5);
                    } else if (value.entity === 'shape') {
                        if (key === 'title') next.label = field.value;
                        if (key === 'tag') next.layer = field.value || 'SLD-LINEWORK';
                        if (key === 'subtitle' && ['rectangle', 'circle', 'arc'].includes(String(field.value).toLowerCase())) next.shape_type = String(field.value).toLowerCase();
                        if (key === 'rating') next.lineweight = clamp(finite(field.value, next.lineweight || 1.4), 0.6, 5);
                    } else if (cell.isEdge?.() && key === 'title') next.label = field.value;
                    else if (cell.isEdge?.() && ['subtitle', 'rating'].includes(key)) next.detail = field.value;
                    else if (['rated_power', 'rated_voltage', 'rated_current'].includes(key)) {
                        next[key] = field.value === '' ? null : finite(field.value, null);
                        const parts = [];
                        if (next.rated_power !== null && next.rated_power !== '' && Number.isFinite(Number(next.rated_power))) parts.push(`${Number(next.rated_power).toLocaleString(undefined, { maximumFractionDigits: 2 })} kW`);
                        if (next.rated_voltage !== null && next.rated_voltage !== '' && Number.isFinite(Number(next.rated_voltage))) parts.push(`${Number(next.rated_voltage).toLocaleString(undefined, { maximumFractionDigits: 0 })} V`);
                        if (next.rated_current !== null && next.rated_current !== '' && Number.isFinite(Number(next.rated_current))) parts.push(`${Number(next.rated_current).toLocaleString(undefined, { maximumFractionDigits: 1 })} A`);
                        if (parts.length > 0) next.rating = parts.join(' · ');
                    } else next[key] = field.value;
                    model.setValue(cell, next);
                    if (isDraftingLineValue(value)) model.setStyle(cell, draftingLineStyle(next));
                    if (value.entity === 'shape') model.setStyle(cell, geometryShapeStyle(next));
                }
            });
            markDirty();
            graph.refresh(cell);
            refreshUi();
        };
        field.addEventListener('change', apply);
        field.addEventListener('blur', apply);
    });

    root.querySelector('[data-editor-delete]')?.addEventListener('click', removeSelected);
    root.querySelectorAll('[data-editor-save]').forEach((button) => button.addEventListener('click', save));
    els.zoomRange?.addEventListener('input', () => setZoom(Number(els.zoomRange.value) / 100));
    root.querySelectorAll('[data-editor-close]').forEach((button) => button.addEventListener('click', showCloseDialog));
    root.querySelector('[data-editor-close-cancel]')?.addEventListener('click', hideCloseDialog);
    root.querySelector('[data-editor-close-discard]')?.addEventListener('click', () => window.location.assign(closeUrl));
    root.querySelector('[data-editor-close-save]')?.addEventListener('click', async () => {
        const saved = await save();
        if (saved) window.location.assign(closeUrl);
    });
    els.closeDialog?.addEventListener('click', (event) => {
        if (event.target === els.closeDialog) hideCloseDialog();
    });

    function selectAllDrawingObjects() {
        if (activeSpace !== 'model') return;
        if (draftSession || activeTool !== 'select') {
            resetDraftSession();
            setTool('select');
        }
        const cells = (graph.getDefaultParent()?.getChildren?.() || []).filter((cell) => {
            const value = cellValue(cell);
            if (!value || cell.isVisible?.() === false || graph.isCellSelectable?.(cell) === false) return false;
            return !layerState[objectLayer(value)] || layerState[objectLayer(value)].visible !== false;
        });
        graph.setSelectionCells?.(cells);
        renderSelectedSnapPoints();
        refreshUi();
        updateCommandUi(`${cells.length} drawing object${cells.length === 1 ? '' : 's'} selected.`);
    }

    window.addEventListener('keydown', (event) => {
        const target = event.target;
        const editingText = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target?.isContentEditable;
        if (event.code === 'Space' && !editingText) {
            spaceDown = true;
            container.classList.add('is-pan-mode');
            if (!panSession && els.hint) els.hint.innerHTML = '<kbd>SPACE</kbd> drag anywhere to pan';
            event.preventDefault();
        }
        if (editingText) return;
        const key = event.key.toLowerCase();

        if (event.key === 'F3') { event.preventDefault(); setOsnap(!osnapEnabled); return; }
        if (event.key === 'F7') { event.preventDefault(); setGrid(container.classList.contains('is-grid-hidden')); return; }
        if (event.key === 'F8') { event.preventDefault(); setOrtho(!orthoEnabled); return; }
        if (event.key === 'F9') { event.preventDefault(); setSnap(!snapEnabled); return; }
        if (event.key === 'F10') { event.preventDefault(); setPolar(!polarEnabled); return; }
        if (event.key === 'F11') { event.preventDefault(); setOtrack(!otrackEnabled); return; }
        if (event.key === 'F12') { event.preventDefault(); setDynamic(!dynamicEnabled); return; }

        if ((event.ctrlKey || event.metaKey) && key === 'a') {
            event.preventDefault();
            event.stopPropagation();
            selectAllDrawingObjects();
            return;
        }
        if ((event.ctrlKey || event.metaKey) && key === 's') { event.preventDefault(); save(); return; }
        if ((event.ctrlKey || event.metaKey) && key === 'z') { event.preventDefault(); event.shiftKey ? undoManager.redo() : undoManager.undo(); return; }
        if ((event.ctrlKey || event.metaKey) && key === 'y') { event.preventDefault(); undoManager.redo(); return; }
        if ((event.ctrlKey || event.metaKey) && key === 'x') { event.preventDefault(); cutSelection(); return; }
        if ((event.ctrlKey || event.metaKey) && key === 'c') { event.preventDefault(); copySelection(); return; }
        if ((event.ctrlKey || event.metaKey) && key === 'v') { event.preventDefault(); pasteSelection(); return; }
        if ((event.ctrlKey || event.metaKey) && key === 'd') { event.preventDefault(); duplicateSelection(); return; }
        if ((event.ctrlKey || event.metaKey) && !event.shiftKey && key === 'g') { event.preventDefault(); groupSelection(); return; }
        if ((event.ctrlKey || event.metaKey) && event.shiftKey && key === 'g') { event.preventDefault(); ungroupSelection(); return; }
        if (event.altKey && !event.shiftKey && key === 'r') { event.preventDefault(); applyRotation(90); return; }
        if (event.altKey && event.shiftKey && key === 'r') { event.preventDefault(); applyRotation(-90); return; }
        if (event.altKey && !event.shiftKey && key === 'l') { event.preventDefault(); setObjectLock(true); return; }
        if (event.altKey && event.shiftKey && key === 'l') { event.preventDefault(); setObjectLock(false); return; }
        if (activeTool === 'select' && ['arrowleft', 'arrowright', 'arrowup', 'arrowdown'].includes(key)) {
            event.preventDefault();
            const step = event.shiftKey ? 10 : 1;
            if (key === 'arrowleft') nudgeSelection(-step, 0);
            if (key === 'arrowright') nudgeSelection(step, 0);
            if (key === 'arrowup') nudgeSelection(0, -step);
            if (key === 'arrowdown') nudgeSelection(0, step);
            return;
        }

        const precisionCommandActive = ['line', 'polyline', 'rectangle', 'circle', 'arc', 'leader'].includes(activeTool) && activeSpace === 'model';
        if (precisionCommandActive && dynamicEnabled && /^[0-9.\-]$/.test(event.key)) {
            event.preventDefault();
            if (dynamicField === 'primary') dynamicPrimary += event.key;
            else dynamicSecondary += event.key;
            updateDraftOverlay();
            return;
        }
        if (precisionCommandActive && event.key === 'Tab') {
            event.preventDefault();
            dynamicField = dynamicField === 'primary' ? 'secondary' : 'primary';
            updateDraftOverlay();
            return;
        }
        if (precisionCommandActive && key === 'backspace' && (dynamicPrimary !== '' || dynamicSecondary !== '')) {
            event.preventDefault();
            if (dynamicField === 'primary') dynamicPrimary = dynamicPrimary.slice(0, -1);
            else dynamicSecondary = dynamicSecondary.slice(0, -1);
            updateDraftOverlay();
            return;
        }
        if (key === 'enter') {
            if (precisionCommandActive && (dynamicPrimary !== '' || dynamicSecondary !== '')) {
                event.preventDefault();
                commitDraftPoint();
                return;
            }
            if (['line', 'polyline', 'leader'].includes(activeTool) && draftSession) {
                event.preventDefault();
                finishDraftCommand(true);
                return;
            }
            if (typedCommand !== '') {
                event.preventDefault();
                executeTypedCommand(typedCommand);
                typedCommand = '';
                return;
            }
        }

        if (key === 'delete' || key === 'backspace') { event.preventDefault(); removeSelected(); return; }
        if (key === 'escape') {
            if (document.fullscreenElement) {
                event.preventDefault();
                document.exitFullscreen?.();
            } else if (draftSession || activeTool !== 'select') {
                event.preventDefault();
                resetDraftSession();
                setTool('select');
            } else {
                hideCloseDialog();
                graph.clearSelection?.();
            }
            return;
        }

        const directTools = { v: 'select', h: 'pan', l: 'line', c: 'circle', a: 'arc', w: 'wire', t: 'text' };
        if (directTools[key] && typedCommand === '' && !event.altKey && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            setTool(directTools[key]);
            typedCommand = '';
            return;
        }
        if (key === '[') { event.preventDefault(); togglePanel('palette'); return; }
        if (key === ']') { event.preventDefault(); togglePanel('inspector'); return; }
        if (key === 'f') { event.preventDefault(); root.querySelector('[data-editor-action="fullscreen"]')?.click(); return; }
        if (key === '0') { event.preventDefault(); fitDrawing(); return; }
        if (key === '+' || key === '=') { event.preventDefault(); setZoom(graph.getView().scale * 1.15); return; }
        if (key === '-') { event.preventDefault(); setZoom(graph.getView().scale / 1.15); return; }
        if (key === '/') { event.preventDefault(); openPanel('palette'); symbolSearch?.focus(); return; }

        if (/^[a-z]$/.test(key) && !event.altKey && !event.ctrlKey && !event.metaKey) {
            typedCommand += key;
            window.clearTimeout(typedCommandTimer);
            typedCommandTimer = window.setTimeout(() => { typedCommand = ''; updateCommandUi(); }, 900);
            updateCommandUi(`Type command: ${typedCommand.toUpperCase()}`);
            const recognized = ['pl', 'rec', 'ml'];
            if (recognized.includes(typedCommand)) {
                executeTypedCommand(typedCommand);
                typedCommand = '';
            }
        }
    });

    function executeTypedCommand(command) {
        const normalized = String(command || '').trim().toLowerCase();
        const map = {
            pl: 'polyline', pline: 'polyline', polyline: 'polyline',
            rec: 'rectangle', rectang: 'rectangle', rectangle: 'rectangle',
            le: 'leader', ml: 'leader', mleader: 'leader', leader: 'leader',
            line: 'line', circle: 'circle', arc: 'arc', wire: 'wire', pan: 'pan', select: 'select',
        };
        if (map[normalized]) setTool(map[normalized]);
        else updateCommandUi(`Unknown command “${normalized.toUpperCase()}”.`);
    }

    window.addEventListener('keyup', (event) => {
        if (event.code === 'Space') {
            spaceDown = false;
            if (!panSession && activeTool !== 'pan') container.classList.remove('is-pan-mode');
            if (!panSession && activeTool === 'select' && els.hint) els.hint.innerHTML = '<kbd>SPACE</kbd> + drag or middle-mouse drag to pan';
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
    window.addEventListener('resize', () => { graph.refresh(); syncGridToViewport(); updateDraftOverlay(); renderPrecisionResizeOverlay(); });
    window.addEventListener('blur', () => { spaceDown = false; endPan(); });
    document.addEventListener('fullscreenchange', updateFullscreenUi);

    root.querySelectorAll('[data-editor-circle-mode]').forEach((button) => button.classList.toggle('is-active', button.dataset.editorCircleMode === circleMode));
    root.querySelectorAll('[data-editor-arc-mode]').forEach((button) => button.classList.toggle('is-active', button.dataset.editorArcMode === arcMode));
    root.querySelector('[data-editor-circle-mode-label]')?.replaceChildren(document.createTextNode(circleModeLabel()));
    root.querySelector('[data-editor-arc-mode-label]')?.replaceChildren(document.createTextNode(arcModeLabel()));

    restorePanels();
    setTool('select');
    root.classList.remove('is-display-clean', 'is-display-engineering', 'is-display-inspection');
    root.classList.add(`is-display-${displayMode}`);
    root.querySelectorAll('[data-editor-display]').forEach((button) => button.classList.toggle('is-active', button.dataset.editorDisplay === displayMode));
    setSpace(activeSpace, false);
    setGrid(gridEnabled);
    setSnap(snapEnabled);
    setOrtho(orthoEnabled, false);
    setPolar(polarEnabled, false);
    renderPaperNavigation([...root.querySelectorAll('[data-editor-paper-tab]')].map((button, index) => ({ number: index + 1, title: button.querySelector('span')?.textContent || `Sheet ${index + 1}`, type: button.querySelector('small')?.textContent?.split(' · ')[0] || 'detail', node_count: Number(button.querySelector('small')?.textContent?.match(/(\d+) OBJECTS/)?.[1] || 0) })));
    updatePaperSummary({ mode: paperMode, sheet_count: root.querySelectorAll('[data-editor-paper-page]').length, message: els.paperMessage?.textContent || '' });
    renderLayerControls();
    applyLayerState(false);
    setOsnap(osnapEnabled, false);
    syncOsnapSettingsUi();
    setOtrack(otrackEnabled, false);
    setDynamic(dynamicEnabled, false);
    updateFullscreenUi();
    renderEngineeringController();
    refreshUi();
    updateRoutingStatus();
    updateVisualRegressionStatus();

    if (root.dataset.editorE2e === 'true') {
        window.__NEXORIN_SLD_E2E__ = {
            snapshot() {
                const selection = selectedCells().map((cell) => {
                    const value = cellValue(cell) || {};
                    return { id: value.id || value.wire_id || null, entity: value.entity || null };
                });
                return {
                    ready: root.dataset.editorReady === 'true',
                    activeTool,
                    activeSpace,
                    displayMode,
                    dirty,
                    selection,
                    viewport: {
                        scale: graph.getView().scale,
                        translateX: graph.getView().translate.x,
                        translateY: graph.getView().translate.y,
                    },
                    editor: serializeEditor(),
                };
            },
            selectNode(id) {
                const cell = cellsByNodeId.get(String(id));
                if (!cell) return false;
                graph.setSelectionCell(cell);
                refreshUi();
                return true;
            },
            selectObject(id) {
                const cell = findCellById(id);
                if (!cell) return false;
                graph.setSelectionCell(cell);
                refreshUi();
                return true;
            },
            selectObjects(ids) {
                const cells = Array.isArray(ids) ? ids.map((id) => findCellById(id)).filter(Boolean) : [];
                graph.setSelectionCells(cells);
                refreshUi();
                return cells.length;
            },
            clearSelection() {
                graph.clearSelection?.();
                refreshUi();
            },
            setTool(tool) {
                setTool(tool);
                return activeTool;
            },
            fit() {
                fitDrawing();
                return graph.getView().scale;
            },
            setZoom(scale) {
                setZoom(Number(scale));
                return graph.getView().scale;
            },
            async save() {
                await save();
                return !dirty;
            },
            runElectricalCheck() {
                return runElectricalCheck();
            },
            electricalValidation() {
                return electricalValidation;
            },
            engineeringController() {
                return engineeringController;
            },
            async syncEngineeringController(action = 'accept_source', reason = '') {
                return runEngineeringControllerAction(action, reason);
            },
            locateIssue(id) {
                return locateElectricalIssue(id);
            },
            cellBounds(id) {
                const cell = findCellById(id);
                const geometry = cell?.getGeometry?.();
                if (!geometry) return null;
                return { x: geometry.x, y: geometry.y, w: geometry.width, h: geometry.height };
            },
            geometryDiagnostics() {
                return collectGeometryDiagnostics();
            },
            geometryContract() {
                return {
                    version: SLD_GEOMETRY_FOUNDATION_VERSION,
                    contract: SLD_GEOMETRY_CONTRACT.name,
                    gridSize: GRID_SIZE,
                    canvas: { ...CANVAS },
                    minimumEquipmentGap: SLD_GEOMETRY_CONTRACT.equipment.minimumGap,
                };
            },
            labelPlacement() {
                return JSON.parse(JSON.stringify(labelPlacementResult));
            },
            labelPlacementContract() {
                return { version: SLD_LABEL_PLACEMENT_VERSION, ...SLD_LABEL_PLACEMENT_CONTRACT };
            },
            rebuildLabels() {
                return JSON.parse(JSON.stringify(rebuildAutomaticLabels()));
            },
            labelBounds(id) {
                const placement = labelPlacementResult.placements.find((item) => String(item.id) === String(id));
                return placement ? { x: placement.x, y: placement.y, w: placement.w, h: placement.h, side: placement.side, leader: placement.leader, signature: placement.signature } : null;
            },
            visualRegression() {
                return JSON.parse(JSON.stringify(updateVisualRegressionStatus()));
            },
            visualRegressionContract() {
                return { version: SLD_VISUAL_REGRESSION_VERSION, ...SLD_VISUAL_REGRESSION_CONTRACT };
            },
            visualSnapshot() {
                const editor = serializeEditor();
                return createVisualSnapshot({ positions: editor.positions, routes: editor.routes, labelPlacement: editor.label_placement, canvas: editor.canvas });
            },
            compareVisualSnapshot(baseline) {
                const editor = serializeEditor();
                const current = createVisualSnapshot({ positions: editor.positions, routes: editor.routes, labelPlacement: editor.label_placement, canvas: editor.canvas });
                return compareVisualSnapshots(baseline, current);
            },
            moveObject(id, x, y) {
                const cell = findCellById(id);
                const value = cellValue(cell) || {};
                const geometry = cell?.getGeometry?.()?.clone?.();
                if (!cell?.isVertex?.() || !geometry || value.locked === true) return null;
                geometry.x = snapEnabled ? graph.snap(Number(x)) : Number(x);
                geometry.y = snapEnabled ? graph.snap(Number(y)) : Number(y);
                model.setGeometry(cell, geometry);
                syncConnectedWires([cell], true);
                graph.setSelectionCell(cell);
                markDirty();
                refreshUi();
                return { x: geometry.x, y: geometry.y, w: geometry.width, h: geometry.height };
            },
            resizeObject(id, width, height) {
                const cell = findCellById(id);
                const value = cellValue(cell) || {};
                const geometry = cell?.getGeometry?.()?.clone?.();
                if (!cell?.isVertex?.() || !geometry || value.locked === true) return null;
                const limits = precisionResizeLimits(value);
                geometry.width = snappedResizeValue(Number(width), limits.minWidth, limits.maxWidth);
                geometry.height = snappedResizeValue(Number(height), limits.minHeight, limits.maxHeight);
                model.setGeometry(cell, geometry);
                syncConnectedWires([cell], true);
                graph.setSelectionCell(cell);
                refreshUi();
                return { x: geometry.x, y: geometry.y, w: geometry.width, h: geometry.height };
            },
            snapDiagnostics(point, tool = activeTool) {
                const previousTool = activeTool;
                activeTool = tool;
                const raw = { x: Number(point?.x || 0), y: Number(point?.y || 0) };
                const candidate = findObjectSnap(raw);
                activeTool = previousTool;
                return candidate ? { x: candidate.x, y: candidate.y, type: candidate.type, label: candidate.label, port_id: candidate.port?.id || null, node_id: cellValue(candidate.cell)?.id || null } : null;
            },
            interactionDiagnostics() {
                return {
                    activeTool,
                    interactionMode: root.dataset.interactionMode || interactionModeForTool(),
                    selectionCount: graph.getSelectionCount?.() || 0,
                    resizeHandles: els.resizeOverlay?.querySelectorAll?.('[data-editor-resize-handle]')?.length || 0,
                    passiveSnapMarkers: els.selectedSnapPoints?.querySelectorAll?.('.is-passive')?.length || 0,
                    activeSnapMarkers: els.snapMarker?.querySelectorAll?.('.is-active')?.length || 0,
                    dynamicInputVisible: Boolean(els.dynamicInput && !els.dynamicInput.hidden),
                    dynamicPrompt: els.dynamicPrompt?.textContent || '',
                };
            },
            dragPerformanceDiagnostics() {
                return {
                    ...dragPerformance,
                    preview_active: Boolean(moveSelectionSession),
                    frame_pending: dragPreviewFrame !== null,
                    route_frame_pending: wireSyncScheduled,
                    mode: root.dataset.dragPerformanceMode || dragPerformance.mode || 'idle',
                };
            },
            graphClientPoint(point) {
                const raw = { x: Number(point?.x || 0), y: Number(point?.y || 0) };
                const local = graphToScreen(raw);
                const rect = container.getBoundingClientRect();
                return { x: rect.left + local.x, y: rect.top + local.y };
            },
            rebuildSmartRoutes() {
                return rebuildAllSmartRoutes();
            },
            previewSmartRoute(id) {
                const edge = findCellById(id);
                if (!edge?.isEdge?.() || cellValue(edge)?.entity !== 'edge') return null;
                const value = cellValue(edge) || {};
                const profile = routingProfile(value);
                const result = autoRouteResultForEdge(edge, value);
                return { profile, value: { ...value }, result };
            },
            routeDiagnostics() {
                const obstacles = routingObstacleRects();
                const wires = (graph.getDefaultParent()?.getChildren?.() || []).filter((cell) => cell?.isEdge?.() && cellValue(cell)?.entity === 'edge').map((edge) => {
                    const value = cellValue(edge) || {};
                    return {
                        id: value.id || '',
                        from: String(cellValue(edge.getTerminal?.(true))?.id || value.from || ''),
                        to: String(cellValue(edge.getTerminal?.(false))?.id || value.to || ''),
                        network: normalizeWireNetwork(value.network),
                        mode: value.routing_mode === 'manual' ? 'manual' : 'auto',
                        clearance: routingProfile(value).key,
                        points: edgeFullRoutePoints(edge),
                        metrics: {
                            contract: value.route_contract || '',
                            strategy: value.route_strategy || '',
                            signature: value.route_signature || '',
                            network_class: value.route_network_class || routingNetworkClass(value.network),
                            preferred_axis: value.route_preferred_axis || '',
                            preferred_channel: Number(value.route_preferred_channel || 0),
                            bends: Number(value.route_bends || 0),
                            crossings: Number(value.route_crossings || 0),
                            clearance_conflicts: Number(value.route_clearance_conflicts || 0),
                            separation_conflicts: Number(value.route_separation_conflicts || 0),
                            terminal_direction_ok: value.routing_mode === 'manual' ? true : value.route_terminal_direction_ok === true,
                            orthogonal: value.routing_mode === 'manual' ? routeIsOrthogonal(edgeFullRoutePoints(edge)) : value.route_orthogonal === true,
                            length: Number(value.route_length || 0),
                        },
                    };
                });
                return {
                    version: SLD_SMART_ROUTER_VERSION,
                    contract: SLD_ORTHOGONAL_ROUTING_CONTRACT.name,
                    engine: SLD_ORTHOGONAL_ROUTING_CONTRACT.engine,
                    summary: { ...routeSyncSummary },
                    obstacles,
                    wires,
                };
            },
            portGraphPoint(id, portId) {
                const cell = findCellById(id);
                const descriptor = portDescriptorForCell(cell, portId);
                if (!descriptor || (portId && String(descriptor.port?.id || '') !== String(portId))) return null;
                return {
                    x: descriptor.x,
                    y: descriptor.y,
                    node_id: String(cellValue(cell)?.id || id || ''),
                    port_id: String(descriptor.port?.id || portId || ''),
                    network: normalizeWireNetwork(descriptor.port?.network || ''),
                    side: descriptor.side,
                    relative_x: descriptor.relativeX,
                    relative_y: descriptor.relativeY,
                };
            },
            portClientPoint(id, portId) {
                const cell = findCellById(id);
                const descriptor = portDescriptorForCell(cell, portId);
                if (!descriptor || (portId && String(descriptor.port?.id || '') !== String(portId))) return null;
                const local = graphToScreen(descriptor);
                const rect = container.getBoundingClientRect();
                return {
                    x: rect.left + local.x,
                    y: rect.top + local.y,
                    node_id: String(cellValue(cell)?.id || id || ''),
                    port_id: String(descriptor.port?.id || portId || ''),
                    network: normalizeWireNetwork(descriptor.port?.network || ''),
                    side: descriptor.side,
                    relative_x: descriptor.relativeX,
                    relative_y: descriptor.relativeY,
                };
            },
        };
    }

    window.requestAnimationFrame(() => {
        const storedScale = finite(editorPayload.viewport?.scale, 0);
        if (activeSpace === 'model' && storedScale >= 0.82 && storedScale <= MAX_ZOOM) {
            graph.getView().scaleAndTranslate(
                clamp(storedScale, 0.82, MAX_ZOOM),
                finite(editorPayload.viewport.translate_x, 0),
                finite(editorPayload.viewport.translate_y, 0),
            );
        } else {
            fitDrawing();
        }
        syncGridToViewport();
        updateZoomUi();
        suppressDirty = false;
        dirty = false;
        runElectricalCheck();
        markSaveState('saved', readOnly ? 'Read-only drawing' : 'All changes saved');
        root.dataset.editorReady = 'true';
        root.dispatchEvent(new CustomEvent('nexorin:sld-ready', { bubbles: true }));
    });
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sld-editor]').forEach(startEditor);
});
