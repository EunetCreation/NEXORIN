import { test, expect } from 'playwright/test';
import { addSymbol, clickPort, e2eSnapshot, openEditor, serverState } from './helpers/editor.mjs';

const EPSILON = 0.01;
const CONTRACT = 'deterministic-orthogonal-channel-v2';
const ENGINE = 'orthogonal-channel-a-star';

function orthogonal(points = []) {
    return points.length >= 2 && points.slice(0, -1).every((point, index) => {
        const next = points[index + 1];
        return Math.abs(point.x - next.x) < EPSILON || Math.abs(point.y - next.y) < EPSILON;
    });
}

function intersectsExpandedRect(points, rect, clearance = 0) {
    const left = rect.left - clearance;
    const right = rect.right + clearance;
    const top = rect.top - clearance;
    const bottom = rect.bottom + clearance;
    for (let index = 0; index < points.length - 1; index += 1) {
        const a = points[index];
        const b = points[index + 1];
        if (Math.abs(a.x - b.x) < EPSILON) {
            if (a.x > left + EPSILON && a.x < right - EPSILON
                && Math.max(Math.min(a.y, b.y), top) < Math.min(Math.max(a.y, b.y), bottom) - EPSILON) return true;
        } else if (Math.abs(a.y - b.y) < EPSILON) {
            if (a.y > top + EPSILON && a.y < bottom - EPSILON
                && Math.max(Math.min(a.x, b.x), left) < Math.min(Math.max(a.x, b.x), right) - EPSILON) return true;
        } else return true;
    }
    return false;
}

async function diagnostics(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.routeDiagnostics());
}

async function positionNode(page, id, x, y) {
    return page.evaluate(({ id: nodeId, x: nextX, y: nextY }) => window.__NEXORIN_SLD_E2E__?.moveObject(nodeId, nextX, nextY), { id, x, y });
}

async function addPositionedSymbol(page, kind, x, y, clickX = 0.55) {
    await addSymbol(page, kind, clickX, 0.76);
    const snapshot = await e2eSnapshot(page);
    const id = snapshot.editor.manual_nodes.at(-1).id;
    const moved = await positionNode(page, id, x, y);
    expect(moved).not.toBeNull();
    return id;
}

async function createWire(page, fromId, fromPort, toId, toPort) {
    const before = await e2eSnapshot(page);
    await page.locator('[data-editor-tool="wire"]').click();
    await clickPort(page, fromId, fromPort);
    await clickPort(page, toId, toPort);
    await expect.poll(async () => (await e2eSnapshot(page)).editor.manual_connections.length)
        .toBe(before.editor.manual_connections.length + 1);
    return (await e2eSnapshot(page)).editor.manual_connections.at(-1).id;
}

test.describe('APP67M.2 · Orthogonal Routing contract', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('boots every automatic wire on the canonical routing contract', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const report = await diagnostics(page);

        expect(report.version).toBe('67M.2');
        expect(report.contract).toBe(CONTRACT);
        expect(report.engine).toBe(ENGINE);
        expect(report.summary.wires).toBe(report.wires.length);
        expect(report.summary.orthogonalWires).toBe(report.wires.length);
        expect(report.summary.terminalDirectionViolations).toBe(0);
        expect(report.wires.every((wire) => orthogonal(wire.points))).toBe(true);
        expect(report.wires.every((wire) => wire.metrics.contract === CONTRACT)).toBe(true);
        expect(report.wires.every((wire) => wire.metrics.strategy === ENGINE)).toBe(true);
        expect(report.wires.every((wire) => /^rt-[0-9a-f]{8}$/.test(wire.metrics.signature))).toBe(true);
        expect(report.wires.every((wire) => wire.metrics.orthogonal && wire.metrics.terminal_direction_ok)).toBe(true);
        await expect(root).toHaveAttribute('data-routing-version', '67M.2');
        await expect(root).toHaveAttribute('data-routing-contract', CONTRACT);
        await expect(page.locator('[data-editor-routing-version]')).toHaveText('ROUTE 67M.2');
        expect(browserErrors).toEqual([]);
    });

    test('rebuilds every automatic route with stable signatures', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const before = await diagnostics(page);
        const beforeSignatures = Object.fromEntries(before.wires.map((wire) => [wire.id, wire.metrics.signature]));

        expect(await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.rebuildSmartRoutes())).toBe(true);
        await expect.poll(async () => (await diagnostics(page)).summary.terminalDirectionViolations).toBe(0);
        const after = await diagnostics(page);
        const afterSignatures = Object.fromEntries(after.wires.map((wire) => [wire.id, wire.metrics.signature]));

        expect(afterSignatures).toEqual(beforeSignatures);
        expect(after.wires.every((wire) => wire.metrics.strategy === ENGINE)).toBe(true);
        expect(after.wires.every((wire) => wire.metrics.orthogonal && wire.metrics.terminal_direction_ok)).toBe(true);
        expect(browserErrors).toEqual([]);
    });

    test('reroutes a non-connected wire when an obstacle enters its channel', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const panelId = await addPositionedSymbol(page, 'panel', 420, 650, 0.42);
        const loadId = await addPositionedSymbol(page, 'load', 900, 650, 0.80);
        const wireId = await createWire(page, panelId, 'AC_OUT', loadId, 'AC_IN');
        const before = (await diagnostics(page)).wires.find((wire) => wire.id === wireId);
        expect(before).toBeTruthy();

        const obstacleId = await addPositionedSymbol(page, 'monitoring', 660, 650, 0.62);
        await expect.poll(async () => (await diagnostics(page)).wires.find((wire) => wire.id === wireId)?.metrics.signature || '')
            .not.toBe(before.metrics.signature);

        const report = await diagnostics(page);
        const wire = report.wires.find((candidate) => candidate.id === wireId);
        const obstacle = report.obstacles.find((candidate) => candidate.id === obstacleId);
        expect(wire.metrics.contract).toBe(CONTRACT);
        expect(wire.metrics.orthogonal).toBe(true);
        expect(wire.metrics.terminal_direction_ok).toBe(true);
        expect(intersectsExpandedRect(wire.points, obstacle, 18)).toBe(false);
        expect(browserErrors).toEqual([]);
    });

    test('persists routing contract, summary, and manual-wire evidence', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const panelId = await addPositionedSymbol(page, 'panel', 420, 650, 0.42);
        const loadId = await addPositionedSymbol(page, 'load', 900, 650, 0.80);
        const wireId = await createWire(page, panelId, 'AC_OUT', loadId, 'AC_IN');
        await expect(page.locator('[data-editor-save-state]')).toContainText('Unsaved changes');

        const responsePromise = page.waitForResponse((response) => response.url().includes('/__e2e/save') && response.request().method() === 'PATCH');
        await page.evaluate(async () => window.__NEXORIN_SLD_E2E__?.save());
        const response = await responsePromise;
        expect(response.ok()).toBe(true);
        await expect.poll(async () => (await serverState(page)).saveCount).toBe(1);

        const state = await serverState(page);
        const routing = state.lastSave.editor.routing;
        const wire = state.lastSave.editor.manual_connections.find((connection) => connection.id === wireId);
        expect(routing.version).toBe('67M.2');
        expect(routing.contract).toBe(CONTRACT);
        expect(routing.engine).toBe(ENGINE);
        expect(routing.orthogonal_wires).toBe(routing.wires);
        expect(routing.terminal_direction_violations).toBe(0);
        expect(wire.router_version).toBe('67M.2');
        expect(wire.route_contract).toBe(CONTRACT);
        expect(wire.route_strategy).toBe(ENGINE);
        expect(wire.route_orthogonal).toBe(true);
        expect(wire.route_terminal_direction_ok).toBe(true);
        expect(wire.route_signature).toMatch(/^rt-[0-9a-f]{8}$/);
        expect(browserErrors).toEqual([]);
    });
});
