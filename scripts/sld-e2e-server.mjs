import { createServer } from 'node:http';
import { createReadStream, existsSync, statSync } from 'node:fs';
import { dirname, extname, join, normalize, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicRoot = join(projectRoot, 'public');
const fixtureRenderer = join(projectRoot, 'tests', 'e2e', 'sld', 'render-fixture.php');
const professionalFixtureRenderer = join(projectRoot, 'tests', 'e2e', 'sld', 'render-professional-topology-fixture.php');
const host = process.env.NEXORIN_SLD_E2E_HOST || '127.0.0.1';
const port = Number(process.env.NEXORIN_SLD_E2E_PORT || 4177);
const requestedPhpBinary = process.env.NEXORIN_PHP_BINARY || process.env.PHP_BINARY || 'php';
const configuredPhpBinary = resolvePhpBinary(requestedPhpBinary);
const fixtureMode = String(process.env.NEXORIN_SLD_E2E_FIXTURE_MODE || 'all').toLowerCase();
const phpRenderTimeoutMs = Number(process.env.NEXORIN_SLD_PHP_RENDER_TIMEOUT_MS || 120_000);
const validFixtureModes = new Set(['base', 'professional', 'all']);

if (!validFixtureModes.has(fixtureMode)) {
    throw new Error(`Invalid NEXORIN_SLD_E2E_FIXTURE_MODE=${fixtureMode}. Expected base, professional, or all.`);
}
if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error(`Invalid NEXORIN_SLD_E2E_PORT=${process.env.NEXORIN_SLD_E2E_PORT || port}.`);
}

const requiresBaseFixture = fixtureMode === 'base' || fixtureMode === 'all';
const requiresProfessionalFixture = fixtureMode === 'professional' || fixtureMode === 'all';

let fixtureHtml = null;
let professionalFixtureHtml = null;
let lastSave = null;
let saveCount = 0;
let controllerSyncCount = 0;
let lastController = null;
let activePhpCommand = configuredPhpBinary;

const contentTypes = {
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml; charset=utf-8',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.woff2': 'font/woff2',
};

function stripWrappingQuotes(value) {
    const normalized = String(value || '').trim();
    if (normalized.length >= 2) {
        const first = normalized[0];
        const last = normalized.at(-1);
        if ((first === '"' && last === '"') || (first === "'" && last === "'")) {
            return normalized.slice(1, -1).trim();
        }
    }
    return normalized;
}

function resolvePhpBinary(value) {
    const normalized = stripWrappingQuotes(value) || 'php';
    if (process.platform !== 'win32' || normalized.toLowerCase() !== 'php') {
        return normalized;
    }

    const lookup = spawnSync('where.exe', ['php'], {
        encoding: 'utf8',
        windowsHide: true,
        timeout: 10_000,
    });
    if (!lookup.error && lookup.status === 0 && lookup.stdout) {
        const matches = lookup.stdout
            .split(/\r?\n/)
            .map((entry) => stripWrappingQuotes(entry))
            .filter(Boolean);
        const executable = matches.find((entry) => /\.exe$/i.test(entry));
        if (executable) return executable;
    }

    const userProfile = process.env.USERPROFILE || process.env.HOME || '';
    const herdCandidates = ['php84', 'php83', 'php82', 'php81']
        .map((version) => join(userProfile, '.config', 'herd', 'bin', version, 'php.exe'));
    const herdExecutable = herdCandidates.find((candidate) => existsSync(candidate));
    if (herdExecutable) return herdExecutable;

    if (!lookup.error && lookup.status === 0 && lookup.stdout) {
        const shim = lookup.stdout
            .split(/\r?\n/)
            .map((entry) => stripWrappingQuotes(entry))
            .find(Boolean);
        if (shim) return shim;
    }

    return normalized;
}

function quoteWindowsCommandArg(value) {
    return `"${String(value).replaceAll('"', '""')}"`;
}

function windowsCommandToken(value) {
    const normalized = stripWrappingQuotes(value);
    return /^[A-Za-z0-9_.:-]+$/.test(normalized)
        ? normalized
        : quoteWindowsCommandArg(normalized);
}

function runPhpFixture(rendererPath = fixtureRenderer) {
    const childEnv = {
        ...process.env,
        APP_ENV: 'e2e',
        NEXORIN_SLD_E2E_PORT: String(port),
    };

    // Laravel Herd and several Windows PHP managers expose `php` through a
    // .bat/.cmd shim. Node cannot execute those shims directly without cmd.exe.
    if (process.platform === 'win32' && !/\.exe$/i.test(configuredPhpBinary)) {
        const commandInterpreter = process.env.ComSpec || 'C:\\Windows\\System32\\cmd.exe';
        const commandLine = `call ${windowsCommandToken(configuredPhpBinary)} ${quoteWindowsCommandArg(rendererPath)}`;
        activePhpCommand = `${commandInterpreter} /d /c ${commandLine}`;

        return spawnSync(commandInterpreter, ['/d', '/c', commandLine], {
            cwd: projectRoot,
            encoding: 'utf8',
            maxBuffer: 4 * 1024 * 1024,
            windowsHide: true,
            windowsVerbatimArguments: true,
            env: childEnv,
            timeout: phpRenderTimeoutMs,
            killSignal: 'SIGTERM',
        });
    }

    activePhpCommand = configuredPhpBinary;
    return spawnSync(configuredPhpBinary, [rendererPath], {
        cwd: projectRoot,
        encoding: 'utf8',
        maxBuffer: 4 * 1024 * 1024,
        windowsHide: true,
        env: childEnv,
        timeout: phpRenderTimeoutMs,
        killSignal: 'SIGTERM',
    });
}

function renderFixtureFile(rendererPath, label) {
    const startedAt = Date.now();
    console.log(`[APP67L.2] Rendering ${label} fixture with ${configuredPhpBinary}...`);
    const result = runPhpFixture(rendererPath);

    if (result.error) {
        const timeoutHint = result.error.code === 'ETIMEDOUT'
            ? ` The renderer exceeded ${(phpRenderTimeoutMs / 1000).toFixed(0)} seconds. Increase NEXORIN_SLD_PHP_RENDER_TIMEOUT_MS if the machine is unusually slow.`
            : '';
        throw new Error(
            `Unable to start PHP fixture renderer with ${activePhpCommand}: ${result.error.message}.${timeoutHint}\n`
            + 'Set NEXORIN_PHP_BINARY to the absolute path of php.exe when PHP is not available through the Windows PATH.',
        );
    }

    if (result.status !== 0 || !result.stdout) {
        const details = [result.stderr, result.stdout].filter(Boolean).join('\n').trim();
        throw new Error(
            `Unable to render the ${label} fixture with ${activePhpCommand} (PHP exit ${result.status ?? 'unknown'}).\n${details}`,
        );
    }

    if (!result.stdout.includes('data-sld-editor')) {
        const preview = result.stdout.replace(/\s+/g, ' ').slice(0, 1200);
        throw new Error(`${label} fixture did not contain [data-sld-editor]. Output preview:\n${preview}`);
    }

    console.log(`[APP67L.2] Rendered ${label} fixture in ${((Date.now() - startedAt) / 1000).toFixed(1)}s.`);
    return result.stdout;
}

function renderFixture() {
    fixtureHtml = renderFixtureFile(fixtureRenderer, 'SLD E2E');
    return fixtureHtml;
}

function renderProfessionalFixture() {
    professionalFixtureHtml = renderFixtureFile(professionalFixtureRenderer, 'APP67L.2 professional-topology');
    return professionalFixtureHtml;
}

function fixtureWithSavedState() {
    const base = fixtureHtml || renderFixture();
    const editor = lastSave?.editor;
    if (!editor && !lastController) return base;

    return base.replace(
        /(<script type="application\/json" data-sld-editor-payload>)([\s\S]*?)(<\/script>)/,
        (match, open, raw, close) => {
            try {
                const payload = JSON.parse(raw);
                payload.graph ||= {};
                if (editor) {
                    payload.editor = editor;
                    payload.graph.editor = editor;
                }
                if (lastController) {
                    payload.graph.engineering_controller = lastController;
                    payload.graph.meta ||= {};
                    payload.graph.meta.engineering_controller = lastController.summary || {};
                }
                const encoded = JSON.stringify(payload)
                    .replaceAll('<', '\u003C')
                    .replaceAll('>', '\u003E')
                    .replaceAll('&', '\u0026');
                return `${open}${encoded}${close}`;
            } catch (_error) {
                return match;
            }
        },
    );
}

function synchronizedController(previous = null) {
    const previousEvents = Array.isArray(previous?.events) ? previous.events : [];
    return {
        version: '67K.1',
        status: 'synchronized',
        blocking: false,
        source_current: true,
        summary: { error: 0, warning: 0, notice: 0, total: 0 },
        message: 'Technical Calculation, SLD, BOM/BOQ, and downstream status are synchronized.',
        sources: [
            { key: 'calculation', label: 'Technical Calculation', status: 'ready', detail: 'Engineer Verified', authority: true },
            { key: 'recommendation', label: 'System Recommendation', status: 'ready', detail: 'On Grid', authority: true },
            { key: 'sld', label: 'SLD Controlled Graph', status: 'ready', detail: 'Current source fingerprint', authority: false },
            { key: 'bom', label: 'BOM / BOQ', status: 'ready', detail: 'Generated Draft · Priced', authority: false },
            { key: 'financial', label: 'Financial Analysis', status: 'ready', detail: 'Calculated', authority: false },
            { key: 'quotation', label: 'Quotation', status: 'ready', detail: 'Current snapshot', authority: false },
        ],
        impacts: [
            { key: 'topology', label: 'Topology', count: 0, status: 'ready' },
            { key: 'cable', label: 'Cable schedule', count: 0, status: 'ready' },
            { key: 'protection', label: 'Protection schedule', count: 0, status: 'ready' },
            { key: 'bom', label: 'BOM / BOQ', count: 0, status: 'ready' },
            { key: 'quotation', label: 'Quotation', count: 0, status: 'ready' },
        ],
        cable_schedule: previous?.cable_schedule || [],
        protection_schedule: previous?.protection_schedule || [],
        conflicts: [],
        acknowledgements: previous?.acknowledgements || [],
        events: [...previousEvents, { action: 'accept_engineering_source', actor_id: 9000, at: new Date().toISOString() }],
        checked_at: new Date().toISOString(),
    };
}

function json(response, status, payload) {
    response.statusCode = status;
    response.setHeader('Content-Type', 'application/json; charset=utf-8');
    response.setHeader('Cache-Control', 'no-store');
    response.end(JSON.stringify(payload));
}

async function readBody(request) {
    let body = '';
    for await (const chunk of request) body += chunk;
    return body;
}

function safePublicPath(pathname) {
    const candidate = normalize(join(publicRoot, pathname.replace(/^\/+/, '')));
    return candidate.startsWith(publicRoot) ? candidate : null;
}

try {
    console.log(`[APP67L.2] Bootstrapping fixture mode=${fixtureMode} · PHP render limit=${(phpRenderTimeoutMs / 1000).toFixed(0)}s.`);
    if (requiresBaseFixture) renderFixture();
    if (requiresProfessionalFixture) renderProfessionalFixture();
} catch (error) {
    console.error(`[APP67L.2] Fixture bootstrap failed.\n${error instanceof Error ? error.stack || error.message : String(error)}`);
    process.exit(1);
}

const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url || '/', `http://${host}:${port}`);

        if (url.pathname === '/__e2e/health') {
            const baseReady = Boolean(fixtureHtml?.includes('data-sld-editor'));
            const professionalReady = Boolean(
                professionalFixtureHtml?.includes('data-sld-editor')
                && professionalFixtureHtml?.includes('professional_physical'),
            );
            return json(response, 200, {
                ready: (!requiresBaseFixture || baseReady) && (!requiresProfessionalFixture || professionalReady),
                fixtureMode,
                baseReady,
                professionalReady,
                saveCount,
                phpBinary: configuredPhpBinary,
                phpCommand: activePhpCommand,
                phpRenderTimeoutMs,
            });
        }

        if (url.pathname === '/__e2e/state') {
            return json(response, 200, { saveCount, lastSave, controllerSyncCount, lastController });
        }

        if (url.pathname === '/__e2e/reset' && request.method === 'POST') {
            lastSave = null;
            saveCount = 0;
            controllerSyncCount = 0;
            lastController = null;
            return json(response, 200, { reset: true });
        }

        if (url.pathname === '/__e2e/save' && request.method === 'PATCH') {
            const raw = await readBody(request);
            lastSave = raw ? JSON.parse(raw) : null;
            saveCount += 1;
            return json(response, 200, {
                saved: true,
                revision: 7,
                saved_at: new Date().toISOString(),
                paper_svg: '<svg viewBox="0 0 1680 1188"><rect width="1680" height="1188" fill="#fff"/><text x="80" y="100">Saved E2E sheet</text></svg>',
                paper_svgs: ['<svg viewBox="0 0 1680 1188"><rect width="1680" height="1188" fill="#fff"/><text x="80" y="100">Saved E2E sheet</text></svg>'],
                paper_summary: { mode: 'as_drawn', sheet_count: 1, message: 'Saved by APP67J fixture server.' },
                paper_sheets: [{ number: 1, title: 'SLD Overview', type: 'overview', node_count: Number(lastSave?.editor?.manual_nodes?.length || 0) + 6 }],
                engineering_controller: lastController,
                message: 'Drawing saved by APP67J regression fixture.',
            });
        }

        if (url.pathname === '/__e2e/controller' && request.method === 'PATCH') {
            const raw = await readBody(request);
            const input = raw ? JSON.parse(raw) : {};
            controllerSyncCount += 1;
            if (input.action === 'acknowledge_review') {
                const current = lastController || synchronizedController();
                lastController = {
                    ...current,
                    status: 'review',
                    summary: { error: 0, warning: 1, notice: 1, total: 2 },
                    conflicts: [
                        { code: 'K003', severity: 'warning', category: 'calculation', title: 'Calculation review item', message: 'Acknowledged fixture warning.', action: 'Review before approval.', acknowledged: true },
                        { code: 'K051', severity: 'notice', category: 'quotation', title: 'Quotation is downstream-stale', message: 'Acknowledged downstream notice.', action: 'Regenerate quotation.', acknowledged: true },
                    ],
                    acknowledgements: [
                        { code: 'K003', reason: input.reason || 'Reviewed', actor_id: 9000, at: new Date().toISOString() },
                        { code: 'K051', reason: input.reason || 'Reviewed', actor_id: 9000, at: new Date().toISOString() },
                    ],
                };
            } else {
                const baseController = lastController || (() => {
                    const sourceHtml = fixtureHtml || professionalFixtureHtml || '';
                    const match = sourceHtml.match(/<script type="application\/json" data-sld-editor-payload>([\s\S]*?)<\/script>/);
                    if (!match) return null;
                    try { return JSON.parse(match[1])?.graph?.engineering_controller || null; } catch { return null; }
                })();
                lastController = synchronizedController(baseController);
            }
            return json(response, 200, {
                synchronized: true,
                action: input.action || 'accept_source',
                engineering_controller: lastController,
                electrical_validation: null,
                reload: false,
                message: input.action === 'acknowledge_review'
                    ? 'Non-blocking engineering review items were acknowledged in the revision audit trail.'
                    : 'Current Technical Calculation, cable schedule, protection schedule, and BOM linkage were applied while preserving the draft layout and manual drawing layer.',
            });
        }

        if (url.pathname === '/__e2e/closed') {
            response.statusCode = 200;
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            return response.end('<!doctype html><title>Editor closed</title><h1 data-e2e-closed>Editor closed</h1>');
        }

        if (url.pathname === '/sld-editor-professional') {
            response.statusCode = 200;
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.setHeader('Cache-Control', 'no-store');
            return response.end(professionalFixtureHtml || renderProfessionalFixture());
        }

        if (url.pathname === '/' || url.pathname === '/sld-editor') {
            response.statusCode = 200;
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.setHeader('Cache-Control', 'no-store');
            return response.end(fixtureWithSavedState());
        }

        const filePath = safePublicPath(url.pathname);
        if (filePath && existsSync(filePath) && statSync(filePath).isFile()) {
            response.statusCode = 200;
            response.setHeader('Content-Type', contentTypes[extname(filePath).toLowerCase()] || 'application/octet-stream');
            response.setHeader('Cache-Control', 'no-store');
            if (request.method === 'HEAD') return response.end();
            return createReadStream(filePath).pipe(response);
        }

        response.statusCode = 404;
        response.setHeader('Content-Type', 'text/plain; charset=utf-8');
        response.end('Not found');
    } catch (error) {
        const message = error instanceof Error ? error.stack || error.message : String(error);
        console.error(`[APP67K] Fixture request failed: ${message}`);
        json(response, 500, { error: message });
    }
});

server.on('error', (error) => {
    console.error(`[APP67L.2] Fixture server failed to listen on ${host}:${port}. ${error instanceof Error ? error.message : String(error)}`);
    process.exit(1);
});

server.listen(port, host, () => {
    const routes = [
        requiresBaseFixture ? '/sld-editor' : null,
        requiresProfessionalFixture ? '/sld-editor-professional' : null,
    ].filter(Boolean).join(' and ');
    console.log(`[APP67L.2] SLD fixture server ready at http://${host}:${port} (${routes}).`);
});

function shutdown() {
    server.close(() => process.exit(0));
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
