import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SYMBOL_REGISTRY = JSON.parse(readFileSync(resolve(root, 'resources/data/sld-symbol-registry.json'), 'utf8'));
const ELECTRICAL_RULES = JSON.parse(readFileSync(resolve(root, 'resources/data/sld-electrical-rules.json'), 'utf8'));
let checkerSource = readFileSync(resolve(root, 'resources/js/sld-electrical-rule-checker.js'), 'utf8');
checkerSource = checkerSource
    .replace("import SYMBOL_REGISTRY from '../data/sld-symbol-registry.json';", `const SYMBOL_REGISTRY = ${JSON.stringify(SYMBOL_REGISTRY)};`)
    .replace("import ELECTRICAL_RULES from '../data/sld-electrical-rules.json';", `const ELECTRICAL_RULES = ${JSON.stringify(ELECTRICAL_RULES)};`);
const checkerUrl = `data:text/javascript;base64,${Buffer.from(checkerSource).toString('base64')}`;
const { evaluateElectricalDiagram } = await import(checkerUrl);

const symbols = SYMBOL_REGISTRY.symbols;
const sourceNode = (id, symbol, tag) => ({
    id,
    symbol,
    title: symbol,
    tag,
    ports: (symbols[symbol].source_ports?.length ? symbols[symbol].source_ports : symbols[symbol].ports),
    _manual: false,
});
const manualNode = (id, symbol, tag) => ({ id, symbol, kind: symbol, title: symbol, tag, ports: symbols[symbol].ports, _manual: true });
const baseline = () => ({
    nodes: [
        sourceNode('pv_array', 'pv_array', 'PVA-01'),
        sourceNode('dc_protection', 'dc_protection', 'QSDC-01'),
        sourceNode('inverter', 'inverter', 'INV-01'),
        sourceNode('ac_protection', 'ac_protection', 'QFAC-01'),
        sourceNode('meter', 'meter', 'MTR-01'),
        sourceNode('grid', 'grid', 'GRID-01'),
    ],
    connections: [
        { id: 'c1', from: 'pv_array', to: 'dc_protection', from_port: 'DC_OUT', to_port: 'DC_IN', network: 'dc_power', _manual: false },
        { id: 'c2', from: 'dc_protection', to: 'inverter', from_port: 'DC_OUT', to_port: 'DC_IN', network: 'dc_power', _manual: false },
        { id: 'c3', from: 'inverter', to: 'ac_protection', from_port: 'AC_OUT', to_port: 'AC_IN', network: 'ac_power', _manual: false },
        { id: 'c4', from: 'ac_protection', to: 'meter', from_port: 'AC_OUT', to_port: 'AC_IN', network: 'ac_power', _manual: false },
        { id: 'c5', from: 'meter', to: 'grid', from_port: 'AC_OUT', to_port: 'AC_PCC', network: 'ac_power', _manual: false },
    ],
});
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const codes = (validation) => validation.issues.map((issue) => issue.code);

const valid = evaluateElectricalDiagram(baseline());
assert(valid.blocking === false, 'Baseline topology must not be blocking.');
assert(valid.summary.error === 0, 'Baseline topology must contain zero errors.');
assert(valid.summary.warning === 4, 'Baseline topology must expose four grounding warnings.');

const duplicate = baseline();
duplicate.nodes.push(manualNode('monitoring-1', 'monitoring', 'INV-01'));
const duplicateResult = evaluateElectricalDiagram(duplicate);
assert(duplicateResult.blocking === true, 'Duplicate tags must be blocking.');
assert(duplicateResult.issues.filter((issue) => issue.code === 'E007').length === 2, 'Duplicate tag must identify both objects.');

const incompatible = baseline();
incompatible.connections.push({ id: 'bad', from: 'pv_array', to: 'grid', from_port: 'DC_OUT', to_port: 'AC_PCC', network: 'dc_power', _manual: true });
assert(codes(evaluateElectricalDiagram(incompatible)).includes('E003'), 'DC-to-AC connection must produce E003.');

const cable = baseline();
cable.nodes.push(manualNode('panel-1', 'panel', 'DB-01'), manualNode('load-1', 'load', 'LOAD-01'));
cable.connections.push({ id: 'manual-power', from: 'panel-1', to: 'load-1', from_port: 'AC_OUT', to_port: 'AC_IN', network: 'ac_power', _manual: true });
const cableResult = evaluateElectricalDiagram(cable);
assert(codes(cableResult).includes('W002'), 'Incomplete manual power cable must produce W002.');
assert(codes(cableResult).includes('N001'), 'Missing voltage class must produce N001.');

const cableWithCanonicalManualFlag = baseline();
cableWithCanonicalManualFlag.nodes.push(manualNode('panel-2', 'panel', 'DB-02'), manualNode('load-2', 'load', 'LOAD-02'));
cableWithCanonicalManualFlag.connections.push({ id: 'manual-power-canonical', from: 'panel-2', to: 'load-2', from_port: 'AC_OUT', to_port: 'AC_IN', network: 'ac_power', manual: true });
const canonicalManualResult = evaluateElectricalDiagram(cableWithCanonicalManualFlag);
assert(codes(canonicalManualResult).includes('W002'), 'Canonical manual flag must produce W002.');
assert(codes(canonicalManualResult).includes('N001'), 'Canonical manual flag must produce N001.');

const unratedProtection = baseline();
unratedProtection.nodes.push(manualNode('dc-breaker-1', 'dc_breaker', 'QFDC-01'));
assert(codes(evaluateElectricalDiagram(unratedProtection)).includes('W006'), 'Manual protection without manufacturer-backed ratings must produce W006.');

const acBypass = {
    nodes: [sourceNode('inverter-only', 'inverter', 'INV-02'), sourceNode('grid-only', 'grid', 'GRID-02')],
    connections: [{ id: 'direct-ac', from: 'inverter-only', to: 'grid-only', from_port: 'AC_OUT', to_port: 'AC_PCC', network: 'ac_power', _manual: false }],
};
assert(codes(evaluateElectricalDiagram(acBypass)).includes('E012'), 'Direct inverter-to-utility connection must produce E012.');

console.log('[APP67I] JS PASS · baseline, duplicate tags, incompatible networks, cable metadata, protection ratings, and AC bypass');
