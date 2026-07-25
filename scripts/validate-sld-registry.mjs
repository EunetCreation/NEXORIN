import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const path = resolve(root, 'resources/data/sld-symbol-registry.json');
const registry = JSON.parse(readFileSync(path, 'utf8'));
const errors = [];
const allowedSides = new Set(['left', 'right', 'top', 'bottom']);
const allowedDirections = new Set(['input', 'output', 'bidirectional']);
const allowedStatuses = new Set(['iec_reference', 'iec_simplified', 'nexorin_assembly']);
const allowedRotations = new Set([0, 90, 180, 270]);
const networkClasses = new Set(Object.keys(registry.network_classes || {}));
const symbols = registry.symbols || {};
const groups = registry.groups || {};
const expectedFrame = { width: 144, height: 120 };

if (registry.schema !== 'nexorin.sld.symbol-registry.v1') errors.push('Unsupported registry schema.');
if (registry.checkpoint !== 'APP67L.1') errors.push(`Unexpected checkpoint ${registry.checkpoint || '—'}.`);
if (registry.version !== '67L.1') errors.push(`Unexpected registry version ${registry.version || '—'}.`);
if (Number(registry.nominal_frame?.width) !== expectedFrame.width || Number(registry.nominal_frame?.height) !== expectedFrame.height) {
    errors.push('Registry nominal frame must be 144 × 120.');
}
if (Object.keys(symbols).length !== Number(registry.symbol_count || 0)) errors.push(`Registry metadata expects ${registry.symbol_count || 0} symbols, found ${Object.keys(symbols).length}.`);

const groupedSymbols = new Map();
for (const [groupId, group] of Object.entries(groups)) {
    if (!Array.isArray(group.symbols) || group.symbols.length === 0) errors.push(`${groupId}: group contains no symbols.`);
    for (const symbolId of group.symbols || []) {
        if (!symbols[symbolId]) errors.push(`${groupId}: unknown symbol ${symbolId}.`);
        const memberships = groupedSymbols.get(symbolId) || [];
        memberships.push(groupId);
        groupedSymbols.set(symbolId, memberships);
    }
}

for (const [id, symbol] of Object.entries(symbols)) {
    if (symbol.id !== id) errors.push(`${id}: id mismatch.`);
    if (!groups[symbol.group]) errors.push(`${id}: unknown group ${symbol.group || '—'}.`);
    if (Number(symbol.nominal_size?.width) !== expectedFrame.width || Number(symbol.nominal_size?.height) !== expectedFrame.height) {
        errors.push(`${id}: nominal frame must be 144 × 120.`);
    }

    const glyph = symbol.glyph_box || {};
    const gx = Number(glyph.x);
    const gy = Number(glyph.y);
    const gw = Number(glyph.width);
    const gh = Number(glyph.height);
    if (![gx, gy, gw, gh].every(Number.isFinite) || gx < 0 || gy < 0 || gw <= 0 || gh <= 0 || gx + gw > 144 || gy + gh > 120) {
        errors.push(`${id}: glyph_box must remain inside the 144 × 120 frame.`);
    }
    for (const centerName of ['optical_center', 'electrical_center']) {
        const x = Number(symbol[centerName]?.x);
        const y = Number(symbol[centerName]?.y);
        if (!Number.isFinite(x) || !Number.isFinite(y) || x < 0 || x > 1 || y < 0 || y > 1) {
            errors.push(`${id}: ${centerName} must use normalized coordinates.`);
        }
    }

    const memberships = groupedSymbols.get(id) || [];
    if (memberships.length !== 1 || memberships[0] !== symbol.group) {
        errors.push(`${id}: expected exactly one group membership matching ${symbol.group || '—'}, found ${memberships.join(', ') || 'none'}.`);
    }
    if (symbol.palette === true && symbol.group === 'internal') errors.push(`${id}: internal symbol cannot appear in the palette.`);
    if (symbol.standard?.certified !== false) errors.push(`${id}: certified must explicitly remain false.`);
    if (!allowedStatuses.has(symbol.standard?.status)) errors.push(`${id}: invalid IEC traceability status ${symbol.standard?.status || '—'}.`);
    if (!symbol.standard?.status_label || !symbol.standard?.family || !symbol.standard?.reference || !symbol.standard?.source_pages || !symbol.standard?.note) {
        errors.push(`${id}: IEC traceability metadata is incomplete.`);
    }
    if (!Array.isArray(symbol.ports) || symbol.ports.length === 0) errors.push(`${id}: no engineering ports.`);

    const rotations = symbol.transforms?.rotations;
    if (!Array.isArray(rotations) || rotations.length === 0 || rotations.some((rotation) => !allowedRotations.has(Number(rotation)))) {
        errors.push(`${id}: transform rotation policy is invalid.`);
    }
    if (typeof symbol.transforms?.mirror_horizontal !== 'boolean' || typeof symbol.transforms?.mirror_vertical !== 'boolean') {
        errors.push(`${id}: mirror policy must be explicit booleans.`);
    }

    for (const profileName of ['ports', 'source_ports']) {
        const profile = Array.isArray(symbol[profileName]) ? symbol[profileName] : [];
        const ids = new Set();
        for (const port of profile) {
            if (!port.id || ids.has(port.id)) errors.push(`${id}.${profileName}: duplicate or missing port id ${port.id || '—'}.`);
            ids.add(port.id);
            if (!networkClasses.has(port.network)) errors.push(`${id}.${port.id}: unknown network ${port.network}.`);
            if (!allowedSides.has(port.side)) errors.push(`${id}.${port.id}: invalid side ${port.side}.`);
            if (!allowedDirections.has(port.direction)) errors.push(`${id}.${port.id}: invalid direction ${port.direction}.`);
            const x = Number(port.anchor?.x);
            const y = Number(port.anchor?.y);
            if (!Number.isFinite(x) || !Number.isFinite(y) || x < 0 || x > 1 || y < 0 || y > 1) errors.push(`${id}.${port.id}: invalid normalized anchor.`);
            if (port.side === 'left' && x > 0.35) errors.push(`${id}.${port.id}: left terminal anchor is outside the glyph-side envelope.`);
            if (port.side === 'right' && x < 0.65) errors.push(`${id}.${port.id}: right terminal anchor is outside the glyph-side envelope.`);
            if (port.side === 'top' && y > 0.22) errors.push(`${id}.${port.id}: top terminal anchor is outside the glyph-side envelope.`);
            if (port.side === 'bottom' && y < 0.55) errors.push(`${id}.${port.id}: bottom terminal anchor is outside the glyph-side envelope.`);
            if (!port.terminal_class) errors.push(`${id}.${port.id}: terminal_class is missing.`);
            if (typeof port.required !== 'boolean') errors.push(`${id}.${port.id}: required must be an explicit boolean.`);
            if (!Number.isInteger(port.max_connections) || port.max_connections < 1) errors.push(`${id}.${port.id}: max_connections must be a positive integer.`);
        }
    }
}

const paletteSymbols = Object.values(symbols).filter((symbol) => symbol.palette === true);
if (paletteSymbols.length !== Number(registry.palette_count || 0)) errors.push(`Registry metadata expects ${registry.palette_count || 0} palette symbols, found ${paletteSymbols.length}.`);

if (errors.length > 0) {
    console.error('[APP67L.1] SLD registry validation failed:');
    errors.forEach((error) => console.error(`  - ${error}`));
    process.exit(1);
}

const portCount = Object.values(symbols).reduce((total, symbol) => total + symbol.ports.length, 0);
const sourcePortCount = Object.values(symbols).reduce((total, symbol) => total + (symbol.source_ports?.length || 0), 0);
console.log(`[APP67L.1] PASS · ${Object.keys(symbols).length} symbols · ${paletteSymbols.length} palette symbols · ${portCount} canonical ports · ${sourcePortCount} source ports`);
