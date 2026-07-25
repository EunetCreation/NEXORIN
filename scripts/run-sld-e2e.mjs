import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import process from 'node:process';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const playwrightCli = resolve(root, 'node_modules', 'playwright', 'cli.js');
const config = resolve(root, 'playwright.sld.config.mjs');
const fixtureServerScript = resolve(root, 'scripts', 'sld-e2e-server.mjs');
const passthrough = process.argv.slice(2);
const port = Number(process.env.NEXORIN_SLD_E2E_PORT || 4177);
const baseURL = process.env.NEXORIN_SLD_E2E_BASE_URL || `http://127.0.0.1:${port}`;
const suiteTimeoutMs = Number(process.env.NEXORIN_SLD_E2E_SUITE_TIMEOUT_MS || 120_000);
const fixtureStartupTimeoutMs = Number(process.env.NEXORIN_SLD_E2E_STARTUP_TIMEOUT_MS || 90_000);
const fixturePollIntervalMs = Number(process.env.NEXORIN_SLD_E2E_STARTUP_POLL_MS || 250);
const fixtureHealthRequestTimeoutMs = Number(process.env.NEXORIN_SLD_E2E_HEALTH_TIMEOUT_MS || 2_000);
const suiteCooldownMs = Number(process.env.NEXORIN_SLD_E2E_COOLDOWN_MS || 750);
const suites = [
    ['boot', 'tests/e2e/sld/boot.spec.mjs', [], 2],
    ['drafting-core', 'tests/e2e/sld/drafting.spec.mjs', ['--grep', 'creates a line|cancels an unfinished'], 2],
    ['drafting-circles', 'tests/e2e/sld/drafting.spec.mjs', ['--grep', 'valid circle'], 4],
    ['drafting-arcs', 'tests/e2e/sld/drafting.spec.mjs', ['--grep', 'valid arc'], 4],
    ['persistence', 'tests/e2e/sld/persistence.spec.mjs', [], 3],
    ['shell-view', 'tests/e2e/sld/shell-and-view.spec.mjs', [], 6],
    ['symbols-inspector', 'tests/e2e/sld/symbols-and-inspector.spec.mjs', [], 7],
    ['iec-registry', 'tests/e2e/sld/iec-registry.spec.mjs', [], 7],
    ['electrical-rules', 'tests/e2e/sld/electrical-rules.spec.mjs', [], 4],
    ['smart-routing', 'tests/e2e/sld/smart-routing.spec.mjs', [], 5],
    ['orthogonal-routing', 'tests/e2e/sld/orthogonal-routing.spec.mjs', [], 4],
    ['automatic-label-placement', 'tests/e2e/sld/automatic-label-placement.spec.mjs', [], 4],
    ['visual-regression', 'tests/e2e/sld/visual-regression.spec.mjs', [], 4],
    ['precision-resize-osnap', 'tests/e2e/sld/precision-resize-osnap.spec.mjs', [], 5],
    ['tool-aware-osnap', 'tests/e2e/sld/tool-aware-osnap.spec.mjs', [], 4],
    ['interaction-state', 'tests/e2e/sld/interaction-state.spec.mjs', [], 4],
    ['engineering-controller', 'tests/e2e/sld/engineering-controller.spec.mjs', [], 5],
    ['professional-topology', 'tests/e2e/sld/professional-topology.spec.mjs', [], 6],
    ['wire', 'tests/e2e/sld/wire.spec.mjs', [], 3],
    ['geometry-foundation', 'tests/e2e/sld/geometry-foundation.spec.mjs', [], 4],
    ['manage-align', 'tests/e2e/sld/manage.spec.mjs', ['--grep', 'aligns and distributes'], 1],
    ['manage-transform', 'tests/e2e/sld/manage.spec.mjs', ['--grep', 'rotates, mirrors'], 1],
    ['manage-clipboard', 'tests/e2e/sld/manage.spec.mjs', ['--grep', 'cuts and restores'], 1],
];

const requestedSuites = String(process.env.NEXORIN_SLD_E2E_ONLY || '')
    .split(',')
    .map((name) => name.trim())
    .filter(Boolean);
const selectedSuites = requestedSuites.length > 0
    ? suites.filter(([name]) => requestedSuites.includes(name))
    : suites;
if (selectedSuites.length === 0) {
    throw new Error(`No APP67M.4 suite matches NEXORIN_SLD_E2E_ONLY=${requestedSuites.join(',')}`);
}
if (!existsSync(playwrightCli)) {
    throw new Error(
        `Playwright CLI was not found at ${playwrightCli}. Run npm install before executing npm run test:sld.`,
    );
}

const selectedSuiteNames = new Set(selectedSuites.map(([name]) => name));
const requiresProfessionalFixture = selectedSuiteNames.has('professional-topology');
const requiresBaseFixture = selectedSuites.some(([name]) => name !== 'professional-topology');
const fixtureMode = requiresBaseFixture && requiresProfessionalFixture
    ? 'all'
    : requiresProfessionalFixture
        ? 'professional'
        : 'base';

const startedAt = new Date();
const results = [];
let exitCode = 0;
let fixtureServer = null;
let fixtureSpawnError = null;
let fixtureStdoutTail = '';
let fixtureStderrTail = '';

function sleep(ms) {
    return new Promise((resolvePromise) => setTimeout(resolvePromise, ms));
}

function appendTail(current, chunk, limit = 12_000) {
    const next = `${current}${String(chunk)}`;
    return next.length > limit ? next.slice(-limit) : next;
}

function fixtureDiagnostics() {
    const output = [fixtureStdoutTail.trim(), fixtureStderrTail.trim()].filter(Boolean).join('\n');
    return output ? `\nFixture server output:\n${output}` : '';
}

async function waitForFixtureServer() {
    let lastError = null;
    const deadline = Date.now() + fixtureStartupTimeoutMs;

    while (Date.now() < deadline) {
        if (fixtureSpawnError) {
            throw new Error(`Unable to start the SLD E2E fixture server: ${fixtureSpawnError.message}.${fixtureDiagnostics()}`);
        }
        if (fixtureServer && fixtureServer.exitCode !== null) {
            throw new Error(`SLD E2E fixture server exited during startup with code ${fixtureServer.exitCode}.${fixtureDiagnostics()}`);
        }

        try {
            const response = await fetch(`${baseURL}/__e2e/health`, {
                cache: 'no-store',
                signal: AbortSignal.timeout(fixtureHealthRequestTimeoutMs),
            });
            if (response.ok) {
                const health = await response.json().catch(() => null);
                if (health?.ready === true) return health;
                lastError = new Error(`Fixture health endpoint is not ready: ${JSON.stringify(health)}`);
            } else {
                lastError = new Error(`Fixture health endpoint returned HTTP ${response.status}`);
            }
        } catch (error) {
            lastError = error;
        }
        await sleep(fixturePollIntervalMs);
    }

    const elapsedSeconds = (fixtureStartupTimeoutMs / 1000).toFixed(1);
    throw new Error(
        `SLD E2E fixture server did not become ready within ${elapsedSeconds}s. ${lastError?.message || ''}`.trim()
        + fixtureDiagnostics(),
    );
}

async function startFixtureServer() {
    if (process.env.NEXORIN_SLD_E2E_BASE_URL) return;

    console.log(`[APP67M.4] Starting ${fixtureMode} fixture server with a ${(fixtureStartupTimeoutMs / 1000).toFixed(0)}s startup limit...`);
    fixtureServer = spawn(process.execPath, [fixtureServerScript], {
        cwd: root,
        env: {
            ...process.env,
            NEXORIN_SLD_E2E_PORT: String(port),
            NEXORIN_SLD_E2E_FIXTURE_MODE: fixtureMode,
        },
        stdio: ['ignore', 'pipe', 'pipe'],
        windowsHide: true,
    });
    fixtureServer.on('error', (error) => {
        fixtureSpawnError = error;
    });
    fixtureServer.stdout.on('data', (chunk) => {
        fixtureStdoutTail = appendTail(fixtureStdoutTail, chunk);
        process.stdout.write(chunk);
    });
    fixtureServer.stderr.on('data', (chunk) => {
        fixtureStderrTail = appendTail(fixtureStderrTail, chunk);
        process.stderr.write(chunk);
    });

    const health = await waitForFixtureServer();
    console.log(`[APP67M.4] Fixture server ready · mode=${health.fixtureMode || fixtureMode} · PHP=${health.phpBinary || 'unknown'}`);
}

async function stopFixtureServer() {
    if (!fixtureServer || fixtureServer.exitCode !== null) return;
    const closed = new Promise((resolvePromise) => fixtureServer.once('exit', resolvePromise));
    fixtureServer.kill('SIGTERM');
    await Promise.race([closed, sleep(3000)]);
    if (fixtureServer.exitCode === null) {
        if (process.platform === 'win32' && fixtureServer.pid) {
            spawnSync('taskkill', ['/pid', String(fixtureServer.pid), '/T', '/F'], { stdio: 'ignore' });
        } else {
            fixtureServer.kill('SIGKILL');
        }
    }
}

try {
    await startFixtureServer();

    for (const [name, spec, suiteArgs, expectedTests] of selectedSuites) {
        const suiteStarted = Date.now();
        console.log(`\n[APP67M.4] Running ${name} regression suite...`);
        const result = spawnSync(process.execPath, [
            playwrightCli,
            'test',
            '-c',
            config,
            spec,
            ...suiteArgs,
            '--reporter=line',
            ...passthrough,
        ], {
            cwd: root,
            env: {
                ...process.env,
                NEXORIN_SLD_E2E_BASE_URL: baseURL,
            },
            stdio: 'inherit',
            timeout: suiteTimeoutMs,
            killSignal: 'SIGTERM',
            windowsHide: true,
        });

        const timedOut = result.error?.code === 'ETIMEDOUT';
        const status = Number.isInteger(result.status) ? result.status : 1;
        if (result.error) {
            console.error(`[APP67M.4] ${name} ${timedOut ? `exceeded ${suiteTimeoutMs} ms` : result.error.message}`);
        }
        results.push({
            name,
            spec,
            status,
            passed: status === 0,
            timed_out: timedOut,
            tests: expectedTests,
            duration_ms: Date.now() - suiteStarted,
        });

        if (status !== 0) {
            exitCode = status;
            break;
        }
        if (suiteCooldownMs > 0) await sleep(suiteCooldownMs);
    }
} catch (error) {
    console.error(`[APP67M.4] ${error instanceof Error ? error.message : String(error)}`);
    exitCode = 1;
} finally {
    await stopFixtureServer();
}

const finishedAt = new Date();
const summaryDir = resolve(root, 'test-results');
mkdirSync(summaryDir, { recursive: true });
const testsTotal = selectedSuites.reduce((total, suite) => total + suite[3], 0);
const testsCompleted = results.reduce((total, result) => total + (result.passed ? result.tests : 0), 0);
const summary = {
    app: 'NEXORIN SLD',
    checkpoint: 'APP67M.4 · Visual Regression and CAD Renderer Closure',
    started_at: startedAt.toISOString(),
    finished_at: finishedAt.toISOString(),
    passed: exitCode === 0 && results.length === selectedSuites.length,
    fixture_mode: fixtureMode,
    fixture_startup_timeout_ms: fixtureStartupTimeoutMs,
    suites_total: selectedSuites.length,
    suites_completed: results.length,
    tests_total: testsTotal,
    tests_passed: testsCompleted,
    duration_ms: finishedAt.getTime() - startedAt.getTime(),
    results,
};
writeFileSync(resolve(summaryDir, 'sld-e2e-summary.json'), `${JSON.stringify(summary, null, 2)}\n`);

const lines = [
    '# APP67M.4 Playwright Regression Summary',
    '',
    `- Result: **${summary.passed ? 'PASS' : 'FAIL'}**`,
    `- Fixture mode: **${summary.fixture_mode}**`,
    `- Fixture startup limit: **${(summary.fixture_startup_timeout_ms / 1000).toFixed(0)} seconds**`,
    `- Completed: **${summary.suites_completed}/${summary.suites_total} suites**`,
    `- Tests passed: **${summary.tests_passed}/${summary.tests_total}**`,
    `- Duration: **${(summary.duration_ms / 1000).toFixed(1)} seconds**`,
    '',
    '| Suite | Tests | Result | Duration |',
    '|---|---:|---:|---:|',
    ...results.map((item) => `| ${item.name} | ${item.tests} | ${item.passed ? 'PASS' : 'FAIL'} | ${(item.duration_ms / 1000).toFixed(1)} s |`),
    '',
];
writeFileSync(resolve(summaryDir, 'sld-e2e-summary.md'), `${lines.join('\n')}\n`);

console.log(`\n[APP67M.4] ${summary.passed ? 'PASS' : 'FAIL'} · ${summary.tests_passed}/${summary.tests_total} tests · ${summary.suites_completed}/${summary.suites_total} suites · ${(summary.duration_ms / 1000).toFixed(1)}s`);
process.exit(exitCode);
