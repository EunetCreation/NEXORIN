import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const rulesPath = resolve(root, 'resources/data/sld-electrical-rules.json');
const registryPath = resolve(root, 'resources/data/sld-symbol-registry.json');
const rules = JSON.parse(readFileSync(rulesPath, 'utf8'));
const registry = JSON.parse(readFileSync(registryPath, 'utf8'));
const errors = [];
const severities = new Set(['error', 'warning', 'notice', 'info']);
const directions = new Set(['input', 'output', 'bidirectional']);
const symbols = registry.symbols || {};
const networkFamilies = rules.network_families || {};
const ruleCodes = rules.rule_codes || {};

if (rules.schema !== 'nexorin.sld.electrical-rules.v1') errors.push('Unsupported electrical-rule schema.');
if (rules.checkpoint !== 'APP67I') errors.push(`Unexpected checkpoint ${rules.checkpoint || '—'}.`);
if (rules.version !== '67I.1') errors.push(`Unexpected rule version ${rules.version || '—'}.`);
if (!String(rules.claim || '').includes('do not replace')) errors.push('Engineering limitation statement is missing.');

for (const severity of severities) {
    const definition = rules.severity?.[severity];
    if (!definition || !Number.isFinite(Number(definition.rank)) || typeof definition.blocking !== 'boolean') {
        errors.push(`Severity ${severity} is incomplete.`);
    }
}
if (rules.severity?.error?.blocking !== true) errors.push('Error severity must be blocking.');
for (const severity of ['warning', 'notice', 'info']) {
    if (rules.severity?.[severity]?.blocking !== false) errors.push(`${severity} severity must remain non-blocking.`);
}

for (const [network, family] of Object.entries(networkFamilies)) {
    if (!String(family || '').trim()) errors.push(`Network ${network} has no family.`);
    const compatible = rules.compatible_networks?.[network];
    if (!Array.isArray(compatible)) errors.push(`Network ${network} has no compatibility list.`);
    for (const candidate of compatible || []) {
        if (!networkFamilies[candidate]) errors.push(`${network}: unknown compatible network ${candidate}.`);
        if (!rules.compatible_networks?.[candidate]?.includes(network)) errors.push(`${network} ↔ ${candidate}: compatibility is not symmetric.`);
    }
}
for (const network of rules.power_networks || []) {
    if (!networkFamilies[network]) errors.push(`Power network ${network} is unknown.`);
}

for (const [symbolId, profile] of Object.entries(rules.symbol_profiles || {})) {
    const symbol = symbols[symbolId];
    if (!symbol) {
        errors.push(`Rule profile references unknown symbol ${symbolId}.`);
        continue;
    }
    const availablePorts = new Set([...(symbol.ports || []), ...(symbol.source_ports || [])].map((port) => String(port.id)));
    for (const requirement of profile.requirements || []) {
        if (!requirement.id || !requirement.label) errors.push(`${symbolId}: requirement metadata is incomplete.`);
        if (!severities.has(requirement.severity || 'error')) errors.push(`${symbolId}.${requirement.id}: invalid severity.`);
        if (requirement.mode && !['any', 'all'].includes(requirement.mode)) errors.push(`${symbolId}.${requirement.id}: invalid mode.`);
        if (!Array.isArray(requirement.ports) || requirement.ports.length === 0) errors.push(`${symbolId}.${requirement.id}: no candidate ports.`);
        for (const portId of requirement.ports || []) {
            if (!availablePorts.has(String(portId))) errors.push(`${symbolId}.${requirement.id}: unknown port ${portId}.`);
        }
    }
    for (const property of profile.manual_properties || []) {
        if (!property.field || !property.label || !severities.has(property.severity || 'warning')) {
            errors.push(`${symbolId}: manual property requirement is incomplete.`);
        }
    }
    if (profile.grounding_expected === true) {
        const groundPorts = [...(symbol.ports || []), ...(symbol.source_ports || [])]
            .filter((port) => ['protective_earth', 'functional_earth'].includes(port.network));
        if (groundPorts.length === 0) errors.push(`${symbolId}: grounding is expected but no PE/FE terminal exists.`);
    }
}

const expectedCodes = ['E001','E002','E003','E004','E005','E006','E007','E008','E009','E010','E011','E012','W001','W002','W003','W004','W005','W006','N001'];
for (const code of expectedCodes) {
    if (!ruleCodes[code]) errors.push(`Rule code ${code} is missing.`);
}
for (const code of Object.keys(ruleCodes)) {
    if (!/^[EWNI]\d{3}$/.test(code)) errors.push(`Rule code ${code} has an invalid format.`);
}

for (const path of [
    'app/Services/Sld/SldElectricalRuleChecker.php',
    'resources/js/sld-electrical-rule-checker.js',
    'resources/views/slds/editor.blade.php',
]) {
    if (!existsSync(resolve(root, path))) errors.push(`Required APP67I implementation file is missing: ${path}`);
}

for (const symbol of Object.values(symbols)) {
    for (const port of [...(symbol.ports || []), ...(symbol.source_ports || [])]) {
        if (!networkFamilies[port.network]) errors.push(`${symbol.id}.${port.id}: network ${port.network} is not covered by APP67I.`);
        if (!directions.has(port.direction)) errors.push(`${symbol.id}.${port.id}: unsupported direction ${port.direction}.`);
    }
}

if (errors.length > 0) {
    console.error('[APP67I] Electrical-rule registry validation failed:');
    errors.forEach((error) => console.error(`  - ${error}`));
    process.exit(1);
}

console.log(`[APP67I] PASS · ${Object.keys(ruleCodes).length} rules · ${Object.keys(rules.symbol_profiles || {}).length} symbol profiles · ${Object.keys(networkFamilies).length} networks · ${Object.keys(symbols).length} registered symbols covered`);
