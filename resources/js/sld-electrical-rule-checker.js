import SYMBOL_REGISTRY from '../data/sld-symbol-registry.json';
import ELECTRICAL_RULES from '../data/sld-electrical-rules.json';

const symbols = Object.freeze(SYMBOL_REGISTRY.symbols || {});
const rules = Object.freeze(ELECTRICAL_RULES);

export function normalizeElectricalNetwork(network = '') {
    const key = String(network || '').trim().toLowerCase().replaceAll('-', '_').replaceAll(' ', '_');
    if (key === 'dc+' || key === 'positive') return 'dc_positive';
    if (key === 'dc-' || key === 'negative') return 'dc_negative';
    if (key === 'ac') return 'ac_power';
    if (key === 'pe' || key === 'earth') return 'protective_earth';
    if (key === 'fe') return 'functional_earth';
    if (key === 'comm') return 'communication';
    return rules.network_families?.[key] ? key : 'unclassified';
}

function networksCompatible(a, b) {
    if (a === b) return a !== 'unclassified';
    return Array.isArray(rules.compatible_networks?.[a]) && rules.compatible_networks[a].includes(b);
}

function canSend(direction) {
    return direction === 'output' || direction === 'bidirectional';
}

function canReceive(direction) {
    return direction === 'input' || direction === 'bidirectional';
}

function isManualConnection(connection = {}) {
    return connection._manual === true
        || connection.manual === true
        || String(connection.state || '').toLowerCase() === 'manual'
        || String(connection.authority || '').toLowerCase() === 'manual';
}

function nodeLabel(node, fallback) {
    const tag = String(node?.tag_override || node?.tag || '').trim();
    const title = String(node?.title || '').trim();
    if (tag && title) return `${tag} · ${title}`;
    return tag || title || fallback;
}

function issueId(code, subjectType, subjectId, portId = '') {
    const raw = `${code}|${subjectType}|${subjectId}|${portId}`;
    let hash = 2166136261;
    for (let index = 0; index < raw.length; index += 1) {
        hash ^= raw.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }
    return `${code.toLowerCase()}-${(hash >>> 0).toString(16).padStart(8, '0')}`;
}

function canonicalPorts(node) {
    const symbol = String(node?.symbol || node?.kind || '');
    const definition = symbols[symbol];
    if (!definition) return [];
    if (Array.isArray(node?.ports) && node.ports.length > 0) {
        const defaults = [...(definition.ports || []), ...(definition.source_ports || [])];
        const byId = new Map(defaults.map((port) => [String(port.id), port]));
        return node.ports.map((port) => ({ ...(byId.get(String(port.id)) || {}), ...port }));
    }
    return Array.isArray(definition.ports) ? definition.ports : [];
}

export function evaluateElectricalDiagram(input = {}) {
    const nodes = new Map();
    for (const node of Array.isArray(input.nodes) ? input.nodes : []) {
        if (!node || !node.id) continue;
        nodes.set(String(node.id), { ...node });
    }
    const connections = (Array.isArray(input.connections) ? input.connections : []).filter(Boolean);
    const issues = [];
    const issueKeys = new Set();
    const addIssue = ({ code, severity, title, message, recommendation, subjectType, subjectId, nodeIds = [], connectionIds = [], portId = null }) => {
        const key = `${code}|${subjectType}|${subjectId}|${portId || ''}|${nodeIds.join(',')}|${connectionIds.join(',')}`;
        if (issueKeys.has(key)) return;
        issueKeys.add(key);
        issues.push({
            id: issueId(code, subjectType, subjectId, portId || ''),
            code,
            severity: rules.severity?.[severity] ? severity : 'notice',
            blocking: Boolean(rules.severity?.[severity]?.blocking),
            title,
            message,
            recommendation,
            subject_type: subjectType,
            subject_id: subjectId,
            node_ids: [...new Set(nodeIds.filter(Boolean).map(String))],
            connection_ids: [...new Set(connectionIds.filter(Boolean).map(String))],
            port_id: portId,
            source: 'sld_electrical_rule_check',
        });
    };

    const portsByNode = new Map();
    const symbolByNode = new Map();
    const tags = new Map();
    for (const [nodeId, node] of nodes) {
        const symbol = String(node.symbol || node.kind || '');
        symbolByNode.set(nodeId, symbol);
        if (!symbols[symbol]) {
            addIssue({
                code: 'E011', severity: 'error', title: 'Unknown engineering symbol',
                message: `Object ${nodeId} references symbol '${symbol}', which is not present in the APP67H registry.`,
                recommendation: 'Replace the object with an approved NEXORIN engineering symbol before review or export.',
                subjectType: 'node', subjectId: nodeId, nodeIds: [nodeId],
            });
            portsByNode.set(nodeId, new Map());
        } else {
            portsByNode.set(nodeId, new Map(canonicalPorts(node).filter((port) => port?.id).map((port) => [String(port.id), port])));
        }

        const tag = String(node.tag_override || node.tag || '').trim().toUpperCase();
        if (!tag) {
            addIssue({
                code: 'W004', severity: 'warning', title: 'Equipment tag is missing',
                message: `${nodeLabel(node, nodeId)} has no equipment tag.`,
                recommendation: 'Assign a unique tag before engineering review so schedules and drawing references remain traceable.',
                subjectType: 'node', subjectId: nodeId, nodeIds: [nodeId],
            });
        } else {
            if (!tags.has(tag)) tags.set(tag, []);
            tags.get(tag).push(nodeId);
        }
    }

    for (const [tag, nodeIds] of tags) {
        if (nodeIds.length < 2) continue;
        for (const nodeId of nodeIds) {
            addIssue({
                code: 'E007', severity: 'error', title: 'Duplicate equipment tag',
                message: `Equipment tag ${tag} is assigned to more than one object.`,
                recommendation: 'Assign a unique equipment tag to every object before review or export.',
                subjectType: 'node', subjectId: nodeId, nodeIds,
            });
        }
    }

    const portUsage = new Map();
    const validPortUsage = new Map();
    const validNodeDegree = new Map([...nodes.keys()].map((id) => [id, 0]));
    const validConnections = [];
    const signatures = new Map();
    const increment = (map, nodeId, portId) => {
        if (!map.has(nodeId)) map.set(nodeId, new Map());
        const ports = map.get(nodeId);
        ports.set(portId, (ports.get(portId) || 0) + 1);
    };
    const usage = (map, nodeId, portId) => map.get(nodeId)?.get(portId) || 0;

    connections.forEach((connection, index) => {
        const connectionId = String(connection.id || connection.wire_id || `connection_${index + 1}`);
        const from = String(connection.from || '');
        const to = String(connection.to || '');
        const fromPortId = String(connection.from_port || '');
        const toPortId = String(connection.to_port || '');
        const network = normalizeElectricalNetwork(connection.network);

        if (!from || !to || !nodes.has(from) || !nodes.has(to)) {
            addIssue({
                code: 'E001', severity: 'error', title: 'Connection endpoint is missing',
                message: `Connection ${connectionId} does not terminate at two available engineering objects.`,
                recommendation: 'Reconnect both ends to registered equipment ports or remove the invalid wire.',
                subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [connectionId],
            });
            return;
        }
        if (from === to) {
            addIssue({
                code: 'E009', severity: 'error', title: 'Invalid electrical self-loop',
                message: `Connection ${connectionId} starts and ends on ${nodeLabel(nodes.get(from), from)}.`,
                recommendation: 'Connect the circuit to a distinct destination object.',
                subjectType: 'connection', subjectId: connectionId, nodeIds: [from], connectionIds: [connectionId],
            });
            return;
        }
        if (network === 'unclassified') {
            addIssue({
                code: 'E008', severity: 'error', title: 'Electrical network is unclassified',
                message: `Connection ${connectionId} has no authoritative electrical network type.`,
                recommendation: 'Reconnect the wire through compatible APP67H ports so its network is derived automatically.',
                subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [connectionId],
            });
        }

        const fromPort = portsByNode.get(from)?.get(fromPortId);
        const toPort = portsByNode.get(to)?.get(toPortId);
        if (!fromPort || !toPort) {
            const missing = !fromPort ? `${from}.${fromPortId}` : `${to}.${toPortId}`;
            addIssue({
                code: 'E002', severity: 'error', title: 'Unknown terminal reference',
                message: `Connection ${connectionId} references terminal ${missing}, which is not available on the selected symbol profile.`,
                recommendation: 'Reconnect the wire to a visible APP67H terminal.',
                subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [connectionId], portId: !fromPort ? fromPortId : toPortId,
            });
            return;
        }

        const fromNetwork = normalizeElectricalNetwork(fromPort.network);
        const toNetwork = normalizeElectricalNetwork(toPort.network);
        increment(portUsage, from, fromPortId);
        increment(portUsage, to, toPortId);
        const networkClassified = network !== 'unclassified';
        const networkValid = networkClassified
            && networksCompatible(fromNetwork, toNetwork)
            && networksCompatible(fromNetwork, network)
            && networksCompatible(toNetwork, network);
        if (networkClassified && !networkValid) {
            addIssue({
                code: 'E003', severity: 'error', title: 'Incompatible electrical networks',
                message: `Connection ${connectionId} attempts to join ${fromNetwork} to ${toNetwork} as ${network}.`,
                recommendation: 'Use ports from the same compatible electrical network. DC polarity, AC, neutral, PE, control, communication, and measurement circuits must remain separated.',
                subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [connectionId],
            });
        }
        const directionValid = canSend(String(fromPort.direction || 'bidirectional')) && canReceive(String(toPort.direction || 'bidirectional'));
        if (!directionValid) {
            addIssue({
                code: 'E004', severity: 'error', title: 'Terminal direction is invalid',
                message: `Connection ${connectionId} does not follow the declared source and destination terminal directions.`,
                recommendation: 'Reverse the connection or choose an output-to-input terminal pair.',
                subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [connectionId],
            });
        }

        const signature = `${from}|${fromPortId}|${to}|${toPortId}|${network}`;
        if (signatures.has(signature)) {
            addIssue({
                code: 'W003', severity: 'warning', title: 'Duplicate terminal connection',
                message: `Connection ${connectionId} duplicates ${signatures.get(signature)} between the same terminals.`,
                recommendation: 'Remove the duplicate wire or document a distinct parallel circuit with separate terminals and cable tags.',
                subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [signatures.get(signature), connectionId],
            });
        } else {
            signatures.set(signature, connectionId);
        }

        if (isManualConnection(connection) && rules.power_networks.includes(network)) {
            const cableType = String(connection.cable_type || '').trim();
            const cableSize = String(connection.cable_size || '').trim();
            if (!cableType || !cableSize) {
                const missing = !cableType && !cableSize ? 'cable type and conductor size' : (!cableType ? 'cable type' : 'conductor size');
                addIssue({
                    code: 'W002', severity: 'warning', title: 'Cable specification is incomplete',
                    message: `Power connection ${connectionId} is missing ${missing}.`,
                    recommendation: 'Record the cable family and conductor size before engineering review.',
                    subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [connectionId],
                });
            }
            if (!String(connection.voltage_class || '').trim()) {
                addIssue({
                    code: 'N001', severity: 'notice', title: 'Cable voltage class is not recorded',
                    message: `Power connection ${connectionId} has no voltage-class metadata.`,
                    recommendation: 'Record the applicable cable voltage class to improve schedule and procurement traceability.',
                    subjectType: 'connection', subjectId: connectionId, nodeIds: [from, to], connectionIds: [connectionId],
                });
            }
        }

        if (networkValid && directionValid) {
            increment(validPortUsage, from, fromPortId);
            increment(validPortUsage, to, toPortId);
            validNodeDegree.set(from, (validNodeDegree.get(from) || 0) + 1);
            validNodeDegree.set(to, (validNodeDegree.get(to) || 0) + 1);
            validConnections.push({ id: connectionId, from, to, from_port: fromPortId, to_port: toPortId, network });
        }
    });

    for (const [nodeId, ports] of portUsage) {
        for (const [portId, count] of ports) {
            const port = portsByNode.get(nodeId)?.get(portId) || {};
            const limit = Math.max(1, Number(port.max_connections || 1));
            if (count <= limit) continue;
            addIssue({
                code: 'E005', severity: 'error', title: 'Terminal connection limit exceeded',
                message: `Terminal ${nodeId}.${portId} has ${count} connections but allows ${limit}.`,
                recommendation: 'Use a busbar, junction, combiner, or separate terminal that represents the intended branching topology.',
                subjectType: 'node', subjectId: nodeId, nodeIds: [nodeId], portId,
            });
        }
    }

    for (const [nodeId, node] of nodes) {
        const symbol = symbolByNode.get(nodeId) || '';
        const profile = rules.symbol_profiles?.[symbol] || {};
        const requirements = Array.isArray(profile.requirements) ? profile.requirements : [];
        for (const requirement of requirements) {
            const candidatePorts = Array.isArray(requirement.ports) ? requirement.ports.map(String).filter(Boolean) : [];
            if (!candidatePorts.length) continue;
            const connected = candidatePorts.map((portId) => usage(validPortUsage, nodeId, portId) > 0);
            const satisfied = requirement.mode === 'all' ? connected.every(Boolean) : connected.some(Boolean);
            if (satisfied) continue;
            addIssue({
                code: 'E006', severity: requirement.severity || 'error', title: 'Required electrical connection is missing',
                message: `${nodeLabel(node, nodeId)} has no valid connection for ${requirement.label || candidatePorts.join(' / ')}.`,
                recommendation: 'Connect the required terminal set using compatible APP67H ports before review or controlled export.',
                subjectType: 'node', subjectId: nodeId, nodeIds: [nodeId], portId: candidatePorts[0],
            });
        }

        if (node._manual === true) {
            for (const property of Array.isArray(profile.manual_properties) ? profile.manual_properties : []) {
                const field = String(property.field || '');
                if (!field) continue;
                const value = node[field];
                const missing = value === null || value === undefined || value === '' || (Number.isFinite(Number(value)) && Number(value) <= 0);
                if (!missing) continue;
                addIssue({
                    code: 'W006', severity: property.severity || 'warning', title: 'Manual protection rating is incomplete',
                    message: `${nodeLabel(node, nodeId)} has no valid ${property.label || field.replaceAll('_', ' ')}.`,
                    recommendation: 'Record the manufacturer-backed protection rating before engineering review or procurement release.',
                    subjectType: 'node', subjectId: nodeId, nodeIds: [nodeId],
                });
            }
        }

        if (profile.grounding_expected === true) {
            const pePorts = [...(portsByNode.get(nodeId)?.entries() || [])]
                .filter(([, port]) => ['protective_earth', 'functional_earth'].includes(normalizeElectricalNetwork(port.network)))
                .map(([id]) => id);
            const grounded = pePorts.some((portId) => usage(validPortUsage, nodeId, portId) > 0);
            if (pePorts.length && !grounded) {
                addIssue({
                    code: 'W001', severity: 'warning', title: 'Protective-earth connection is missing',
                    message: `${nodeLabel(node, nodeId)} exposes an earthing terminal but no valid PE/FE connection is shown.`,
                    recommendation: 'Connect the equipment earthing terminal to the protective or functional earthing network and verify the project earthing design.',
                    subjectType: 'node', subjectId: nodeId, nodeIds: [nodeId], portId: pePorts[0],
                });
            }
        }

        if (node._manual === true && (validNodeDegree.get(nodeId) || 0) === 0 && !requirements.length) {
            addIssue({
                code: 'W005', severity: 'warning', title: 'Engineering object is isolated',
                message: `${nodeLabel(node, nodeId)} is not connected to the drawing network.`,
                recommendation: 'Connect the object, mark it as future/existing with a clear note, or remove it from the active SLD.',
                subjectType: 'node', subjectId: nodeId, nodeIds: [nodeId],
            });
        }
    }

    for (const connection of validConnections) {
        const fromSymbol = symbolByNode.get(connection.from) || '';
        const toSymbol = symbolByNode.get(connection.to) || '';
        if (['pv_module', 'pv_string', 'pv_array', 'sub_array'].includes(fromSymbol)
            && ['inverter', 'hybrid_inverter', 'offgrid_inverter', 'central_inverter'].includes(toSymbol)) {
            addIssue({
                code: 'E010', severity: 'error', title: 'PV source bypasses DC protection',
                message: `Connection ${connection.id} routes a PV source directly to an inverter without a represented DC protection boundary.`,
                recommendation: 'Insert the applicable DC isolator, fuse, breaker, SPD, combiner, or documented protection assembly between the PV source and inverter.',
                subjectType: 'connection', subjectId: connection.id, nodeIds: [connection.from, connection.to], connectionIds: [connection.id],
            });
        }
        if (['inverter', 'hybrid_inverter', 'offgrid_inverter', 'central_inverter', 'microinverter'].includes(fromSymbol)
            && ['meter', 'app_pln', 'pcc', 'grid'].includes(toSymbol)) {
            addIssue({
                code: 'E012', severity: 'error', title: 'Inverter AC output bypasses AC protection',
                message: `Connection ${connection.id} routes inverter AC output directly to the metering or utility boundary.`,
                recommendation: 'Insert and document the applicable AC isolator, breaker, RCD where required, and surge-protection boundary before the metering or utility connection.',
                subjectType: 'connection', subjectId: connection.id, nodeIds: [connection.from, connection.to], connectionIds: [connection.id],
            });
        }
    }

    issues.sort((a, b) => {
        const rank = (issue) => Number(rules.severity?.[issue.severity]?.rank || 0);
        return rank(b) - rank(a) || a.code.localeCompare(b.code) || a.subject_id.localeCompare(b.subject_id);
    });
    const summary = { status: 'pass', blocking: false, total: issues.length, error: 0, warning: 0, notice: 0, info: 0, nodes_checked: nodes.size, connections_checked: connections.length };
    for (const issue of issues) summary[issue.severity] = Number(summary[issue.severity] || 0) + 1;
    summary.status = summary.error > 0 ? 'fail' : (summary.warning > 0 ? 'review' : 'pass');
    summary.blocking = summary.error > 0;

    return {
        schema: 'nexorin.sld.electrical-validation.v1',
        checkpoint: 'APP67I',
        version: rules.version,
        status: summary.status,
        blocking: summary.blocking,
        checked_at: new Date().toISOString(),
        summary,
        issues,
    };
}

export { ELECTRICAL_RULES };
