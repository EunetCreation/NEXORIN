import { test, expect } from 'playwright/test';
import { addSymbol, clickPort, e2eSnapshot, openEditor } from './helpers/editor.mjs';

const EPSILON = 0.01;

function orthogonal(points = []) {
    if (points.length < 2) return false;
    return points.slice(0, -1).every((point, index) => {
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
            if (a.x <= left + EPSILON || a.x >= right - EPSILON) continue;
            if (Math.max(Math.min(a.y, b.y), top) < Math.min(Math.max(a.y, b.y), bottom) - EPSILON) return true;
        } else if (Math.abs(a.y - b.y) < EPSILON) {
            if (a.y <= top + EPSILON || a.y >= bottom - EPSILON) continue;
            if (Math.max(Math.min(a.x, b.x), left) < Math.min(Math.max(a.x, b.x), right) - EPSILON) return true;
        } else return true;
    }
    return false;
}

async function diagnostics(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.routeDiagnostics());
}

async function setNodePosition(page, id, x, y) {
    expect(await page.evaluate((nodeId) => window.__NEXORIN_SLD_E2E__?.selectNode(nodeId), id)).toBe(true);

    const snapshot = await e2eSnapshot(page);
    const viewport = snapshot?.editor?.viewport || {};
    const gridSize = Math.max(1, Number(viewport.grid_size || 20));
    const snapEnabled = viewport.snap_enabled !== false;
    const expectedX = snapEnabled ? Math.round(Number(x) / gridSize) * gridSize : Number(x);
    const expectedY = snapEnabled ? Math.round(Number(y) / gridSize) * gridSize : Number(y);

    await page.locator('[data-editor-field="x"]').fill(String(x));
    await page.locator('[data-editor-field="x"]').blur();
    await page.locator('[data-editor-field="y"]').fill(String(y));
    await page.locator('[data-editor-field="y"]').blur();
    await expect.poll(async () => {
        const bounds = await page.evaluate((nodeId) => window.__NEXORIN_SLD_E2E__?.cellBounds(nodeId), id);
        return bounds ? `${Math.round(bounds.x)}|${Math.round(bounds.y)}` : '';
    }).toBe(`${expectedX}|${expectedY}`);
}

async function addPositionedSymbol(page, kind, x, y, clickX = 0.55) {
    await addSymbol(page, kind, clickX, 0.75);
    const snapshot = await e2eSnapshot(page);
    const id = snapshot.editor.manual_nodes.at(-1).id;
    await setNodePosition(page, id, x, y);
    return id;
}

async function createWire(page, fromId, fromPort, toId, toPort) {
    const before = await e2eSnapshot(page);
    await page.locator('[data-editor-tool="wire"]').click();
    await clickPort(page, fromId, fromPort);
    await clickPort(page, toId, toPort);
    await expect.poll(async () => (await e2eSnapshot(page)).editor.manual_connections.length).toBe(before.editor.manual_connections.length + 1);
    return (await e2eSnapshot(page)).editor.manual_connections.at(-1).id;
}

test.describe('APP67M.2 · deterministic orthogonal smart routing', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('boots every source wire on the deterministic orthogonal router', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const report = await diagnostics(page);
        expect(report.version).toBe('67M.2');
        expect(report.contract).toBe('deterministic-orthogonal-channel-v2');
        expect(report.engine).toBe('orthogonal-channel-a-star');
        expect(report.wires).toHaveLength(5);
        expect(report.summary.wires).toBe(5);
        expect(report.wires.every((wire) => wire.mode === 'auto')).toBe(true);
        expect(report.wires.every((wire) => orthogonal(wire.points))).toBe(true);
        expect(report.wires.every((wire) => wire.metrics.strategy.length > 0)).toBe(true);
        expect(report.wires.every((wire) => wire.metrics.contract === report.contract)).toBe(true);
        expect(report.wires.every((wire) => /^rt-[0-9a-f]{8}$/.test(wire.metrics.signature))).toBe(true);
        expect(report.wires.every((wire) => wire.metrics.orthogonal === true)).toBe(true);
        expect(report.wires.every((wire) => wire.metrics.terminal_direction_ok === true)).toBe(true);
        await expect(root).toHaveAttribute('data-routing-crossings', String(report.summary.crossings));
        await expect(root).toHaveAttribute('data-routing-conflicts', String(report.summary.conflicts));
        expect(browserErrors).toEqual([]);
    });

    test('routes a new AC wire around intervening equipment with standard clearance', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const panelId = await addPositionedSymbol(page, 'panel', 420, 650, 0.42);
        const obstacleId = await addPositionedSymbol(page, 'monitoring', 650, 650, 0.62);
        const loadId = await addPositionedSymbol(page, 'load', 880, 650, 0.80);
        const wireId = await createWire(page, panelId, 'AC_OUT', loadId, 'AC_IN');

        await expect.poll(async () => (await diagnostics(page)).wires.find((wire) => wire.id === wireId)?.metrics.strategy || '').toContain('orthogonal-channel');
        const report = await diagnostics(page);
        const wire = report.wires.find((candidate) => candidate.id === wireId);
        const obstacle = report.obstacles.find((candidate) => candidate.id === obstacleId);
        expect(wire).toBeTruthy();
        expect(obstacle).toBeTruthy();
        expect(wire.clearance).toBe('standard');
        expect(orthogonal(wire.points)).toBe(true);
        expect(intersectsExpandedRect(wire.points, obstacle, 18)).toBe(false);
        expect(wire.metrics.clearance_conflicts).toBe(0);
        expect(browserErrors).toEqual([]);
    });

    test('reroutes automatically when a new obstacle enters an existing route', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const panelId = await addPositionedSymbol(page, 'panel', 420, 650, 0.42);
        const loadId = await addPositionedSymbol(page, 'load', 880, 650, 0.80);
        const wireId = await createWire(page, panelId, 'AC_OUT', loadId, 'AC_IN');
        const before = (await diagnostics(page)).wires.find((wire) => wire.id === wireId).points;

        const obstacleId = await addPositionedSymbol(page, 'monitoring', 650, 650, 0.62);
        await expect.poll(async () => JSON.stringify((await diagnostics(page)).wires.find((wire) => wire.id === wireId)?.points || [])).not.toBe(JSON.stringify(before));

        const report = await diagnostics(page);
        const wire = report.wires.find((candidate) => candidate.id === wireId);
        const obstacle = report.obstacles.find((candidate) => candidate.id === obstacleId);
        expect(orthogonal(wire.points)).toBe(true);
        expect(intersectsExpandedRect(wire.points, obstacle, 18)).toBe(false);
        expect(browserErrors).toEqual([]);
    });

    test('preserves manual routes when surrounding equipment changes', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const panelId = await addPositionedSymbol(page, 'panel', 420, 650, 0.42);
        const loadId = await addPositionedSymbol(page, 'load', 880, 650, 0.80);
        const wireId = await createWire(page, panelId, 'AC_OUT', loadId, 'AC_IN');
        expect(await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.selectObject(id), wireId)).toBe(true);
        await page.locator('[data-editor-wire-field="routing_mode"]').selectOption('manual');
        await expect.poll(async () => (await diagnostics(page)).wires.find((wire) => wire.id === wireId)?.mode).toBe('manual');
        const before = (await diagnostics(page)).wires.find((wire) => wire.id === wireId).points;

        await addPositionedSymbol(page, 'monitoring', 650, 650, 0.62);
        await page.waitForTimeout(250);
        const after = (await diagnostics(page)).wires.find((wire) => wire.id === wireId);
        expect(after.mode).toBe('manual');
        expect(after.points).toEqual(before);
        expect(browserErrors).toEqual([]);
    });

    test('rebuilds all automatic routes with a selected clearance profile and reports health', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const panelId = await addPositionedSymbol(page, 'panel', 420, 650, 0.42);
        const obstacleId = await addPositionedSymbol(page, 'monitoring', 650, 650, 0.62);
        const loadId = await addPositionedSymbol(page, 'load', 880, 650, 0.80);
        const wireId = await createWire(page, panelId, 'AC_OUT', loadId, 'AC_IN');
        expect(await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.selectObject(id), wireId)).toBe(true);
        await page.locator('[data-editor-wire-field="routing_clearance"]').selectOption('generous');
        await expect.poll(async () => (await diagnostics(page)).wires.find((wire) => wire.id === wireId)?.clearance).toBe('generous');
        await page.locator('[data-editor-ribbon-tab="manage"]').click();
        await page.locator('[data-editor-action="rebuild-all-routes"]').click();
        await expect.poll(async () => {
            const current = await diagnostics(page);
            const currentWire = current.wires.find((candidate) => candidate.id === wireId);
            const currentObstacle = current.obstacles.find((candidate) => candidate.id === obstacleId);
            return Boolean(currentWire && currentObstacle && !intersectsExpandedRect(currentWire.points, currentObstacle, 26));
        }).toBe(true);

        const report = await diagnostics(page);
        const wire = report.wires.find((candidate) => candidate.id === wireId);
        const obstacle = report.obstacles.find((candidate) => candidate.id === obstacleId);
        expect(wire.clearance).toBe('generous');
        expect(intersectsExpandedRect(wire.points, obstacle, 26)).toBe(false);
        expect(report.summary.wires).toBe(6);
        await expect(root).toHaveAttribute('data-routing-status', /clear|crossing|conflict/);
        await expect(page.locator('[data-editor-routing-ribbon-count]')).not.toHaveText('—');
        expect(browserErrors).toEqual([]);
    });
});
