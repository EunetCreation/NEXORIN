import { test, expect } from 'playwright/test';
import { clickGraph, e2eSnapshot, openEditor } from './helpers/editor.mjs';

async function portGraphPoint(page, nodeId = 'inverter', portId = 'AC_OUT') {
    const point = await page.evaluate(({ nodeId, portId }) => window.__NEXORIN_SLD_E2E__?.portGraphPoint(nodeId, portId) || null, { nodeId, portId });
    if (!point) throw new Error(`Unable to resolve ${nodeId}.${portId} graph point.`);
    return point;
}

async function portClientPoint(page, nodeId = 'inverter', portId = 'AC_OUT') {
    const point = await page.evaluate(({ nodeId, portId }) => window.__NEXORIN_SLD_E2E__?.portClientPoint(nodeId, portId) || null, { nodeId, portId });
    if (!point) throw new Error(`Unable to resolve ${nodeId}.${portId} client point.`);
    return point;
}

test.describe('APP67J.2.2 · tool-aware OSNAP scope', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('keeps engineering PORT snap exclusive to Wire for every drafting command', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const point = await portGraphPoint(page);
        const draftingTools = ['line', 'polyline', 'rectangle', 'circle', 'arc', 'leader', 'text', 'place_symbol'];

        for (const tool of draftingTools) {
            const candidate = await page.evaluate(({ point, tool }) => window.__NEXORIN_SLD_E2E__?.snapDiagnostics(point, tool) || null, { point, tool });
            expect(candidate?.type || null, `${tool} must not expose an engineering PORT snap`).not.toBe('Port');
        }

        const wireCandidate = await page.evaluate((raw) => window.__NEXORIN_SLD_E2E__?.snapDiagnostics(raw, 'wire') || null, point);
        expect(wireCandidate).toMatchObject({ type: 'Port', port_id: 'AC_OUT', node_id: 'inverter' });
        expect(browserErrors).toEqual([]);
    });

    test('shows the compact engineering card in Wire and removes it immediately in Line', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const point = await portClientPoint(page);

        await page.locator('[data-editor-tool="wire"]').click();
        await page.mouse.move(point.x, point.y);
        await expect(root).toHaveAttribute('data-osnap-scope', 'engineering');
        await expect(page.locator('[data-editor-snap-marker] .is-port.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-tooltip]')).toContainText('PORT · AC_OUT');
        await expect(page.locator('[data-editor-dynamic-input]')).toBeHidden();
        const tooltipBox = await page.locator('[data-editor-snap-tooltip]').boundingBox();
        expect(tooltipBox?.width || 999).toBeLessThanOrEqual(224);
        expect(tooltipBox?.height || 999).toBeLessThanOrEqual(54);

        await page.locator('[data-editor-tool="line"]').click();
        await page.mouse.move(point.x + 1, point.y + 1);
        await expect(root).toHaveAttribute('data-osnap-scope', 'geometric');
        await expect(page.locator('[data-editor-snap-marker] .is-port.is-active')).toHaveCount(0);
        await expect(page.locator('[data-editor-snap-tooltip]')).toBeHidden();
        expect(browserErrors).toEqual([]);
    });

    test('preserves END and MID geometric snaps after PORT isolation', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.18, 0.76);
        await clickGraph(page, 0.42, 0.76);
        await page.keyboard.press('Enter');
        const snapshot = await e2eSnapshot(page);
        const line = snapshot.editor.manual_lines.at(-1);
        expect(line).toBeTruthy();

        const startClient = await page.evaluate((point) => window.__NEXORIN_SLD_E2E__?.graphClientPoint(point), { x: line.x1, y: line.y1 });
        await page.locator('[data-editor-tool="line"]').click();
        await page.mouse.move(startClient.x, startClient.y);
        await expect(page.locator('[data-editor-snap-marker] .is-endpoint.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('END');
        await expect(page.locator('[data-editor-snap-tooltip]')).toBeHidden();

        const midpoint = { x: (line.x1 + line.x2) / 2, y: (line.y1 + line.y2) / 2 };
        const midpointClient = await page.evaluate((point) => window.__NEXORIN_SLD_E2E__?.graphClientPoint(point), midpoint);
        await page.mouse.move(midpointClient.x, midpointClient.y);
        await expect(page.locator('[data-editor-snap-marker] .is-midpoint.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('MID');
        await expect(page.locator('[data-editor-snap-tooltip]')).toBeHidden();
        expect(browserErrors).toEqual([]);
    });

    test('communicates Wire-only PORT scope in OSNAP settings', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const row = page.locator('[data-editor-osnap-wire-only]');

        await page.locator('[data-editor-tool="line"]').click();
        await expect(root).toHaveAttribute('data-osnap-scope', 'geometric');
        await expect(row).toContainText('WIRE ONLY');
        await expect(row).not.toHaveClass(/is-context-active/);

        await page.locator('[data-editor-tool="wire"]').click();
        await expect(root).toHaveAttribute('data-osnap-scope', 'engineering');
        await expect(row).toHaveClass(/is-context-active/);
        expect(browserErrors).toEqual([]);
    });
});
