import { expect, test } from 'playwright/test';
import { openEditor } from './helpers/editor.mjs';

async function nodeCenter(page, id) {
    return page.evaluate((nodeId) => {
        const api = window.__NEXORIN_SLD_E2E__;
        const bounds = api?.cellBounds(nodeId);
        if (!bounds) return null;
        return {
            bounds,
            client: api.graphClientPoint({
                x: bounds.x + bounds.w / 2,
                y: bounds.y + bounds.h / 2,
            }),
        };
    }, id);
}

test.describe('APP67M.1-HF2 drag performance', () => {
    test('coalesces pointer updates and commits geometry once', async ({ page }) => {
        const { browserErrors } = await openEditor(page, '/sld-editor-professional');
        await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.fit());

        const before = await nodeCenter(page, 'dc_protection');
        expect(before).not.toBeNull();
        const diagnosticsBefore = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.dragPerformanceDiagnostics());

        await page.mouse.move(before.client.x, before.client.y);
        await page.mouse.down();
        await page.mouse.move(before.client.x + 180, before.client.y + 100, { steps: 48 });
        await page.mouse.up();
        await page.waitForTimeout(250);

        const after = await nodeCenter(page, 'dc_protection');
        const diagnostics = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.dragPerformanceDiagnostics());
        expect(after.bounds.x).not.toBe(before.bounds.x);
        expect(after.bounds.y).not.toBe(before.bounds.y);
        expect(diagnostics.commits).toBe((diagnosticsBefore?.commits || 0) + 1);
        expect(diagnostics.route_syncs).toBe((diagnosticsBefore?.route_syncs || 0) + 1);
        expect(diagnostics.rendered_frames).toBeLessThanOrEqual(diagnostics.pointer_events);
        expect(diagnostics.preview_active).toBe(false);
        expect(diagnostics.mode).toBe('idle');
        expect(browserErrors).toEqual([]);
    });

    test('keeps select-mode pointer tracking outside the drafting snap pipeline', async ({ page }) => {
        const { browserErrors } = await openEditor(page, '/sld-editor-professional');
        const graph = await page.locator('[data-editor-graph]').boundingBox();
        expect(graph).not.toBeNull();

        await page.mouse.move(graph.x + graph.width * 0.25, graph.y + graph.height * 0.3);
        await page.mouse.move(graph.x + graph.width * 0.75, graph.y + graph.height * 0.7, { steps: 30 });
        await page.waitForTimeout(50);

        const state = await page.evaluate(() => ({
            tool: window.__NEXORIN_SLD_E2E__?.snapshot()?.activeTool,
            dynamic: window.__NEXORIN_SLD_E2E__?.interactionDiagnostics()?.dynamicInputVisible,
            performance: window.__NEXORIN_SLD_E2E__?.dragPerformanceDiagnostics(),
        }));
        expect(state.tool).toBe('select');
        expect(state.dynamic).toBe(false);
        expect(state.performance.mode).toBe('idle');
        expect(browserErrors).toEqual([]);
    });
});
