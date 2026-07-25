import { test, expect } from 'playwright/test';
import { clickGraph, e2eSnapshot, openEditor, selectNode } from './helpers/editor.mjs';

async function diagnostics(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.interactionDiagnostics?.() || null);
}

test.describe('APP67J.2.4 · drafting state separation and overlay cleanup', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('clears selection and resize grips when a drafting command starts', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        await selectNode(page, 'inverter');
        await expect(page.locator('[data-editor-resize-handle]')).toHaveCount(8);

        await page.locator('[data-editor-tool="line"]').click();
        await expect(root).toHaveAttribute('data-active-tool', 'line');
        await expect(root).toHaveAttribute('data-interaction-mode', 'draft');
        await expect(page.locator('[data-editor-resize-handle]')).toHaveCount(0);
        await expect(page.locator('[data-editor-selected-snap-points] > *')).toHaveCount(0);
        await expect(page.locator('[data-editor-dynamic-input]')).toBeHidden();

        const state = await diagnostics(page);
        expect(state.selectionCount).toBe(0);
        expect(state.resizeHandles).toBe(0);
        expect(state.passiveSnapMarkers).toBe(0);
        await expect(page.locator('[data-editor-command-prompt]')).toContainText('Specify first point');
        expect(browserErrors).toEqual([]);
    });

    test('shows only the single resolved OSNAP target without passive marker clouds', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.34, 0.74);
        await clickGraph(page, 0.51, 0.74);
        await page.keyboard.press('Enter');

        const snapshot = await e2eSnapshot(page);
        const line = snapshot.editor.manual_lines.at(-1);
        const endpoint = await page.evaluate((point) => window.__NEXORIN_SLD_E2E__?.graphClientPoint(point), { x: line.x1, y: line.y1 });
        await page.mouse.move(endpoint.x, endpoint.y);

        await expect(page.locator('[data-editor-snap-marker] .is-active')).toHaveCount(1);
        await expect(page.locator('[data-editor-selected-snap-points] > *')).toHaveCount(0);
        const state = await diagnostics(page);
        expect(state.activeSnapMarkers).toBe(1);
        expect(state.passiveSnapMarkers).toBe(0);
        expect(browserErrors).toEqual([]);
    });

    test('keeps first-point input on the command line and shows a compact cursor input only after anchoring', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-tool="line"]').click();
        await expect(page.locator('[data-editor-dynamic-input]')).toBeHidden();
        await clickGraph(page, 0.31, 0.68);
        await page.mouse.move(760, 540);

        const input = page.locator('[data-editor-dynamic-input]');
        await expect(input).toBeVisible();
        await expect(page.locator('[data-editor-dynamic-prompt]')).toHaveText('Next point');
        const box = await input.boundingBox();
        expect(box).not.toBeNull();
        expect(box.width).toBeLessThanOrEqual(280);
        expect(box.height).toBeLessThanOrEqual(34);
        expect(browserErrors).toEqual([]);
    });

    test('Escape returns a clean Select state with no drafting overlays', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.28, 0.72);
        await expect(page.locator('[data-editor-dynamic-input]')).toBeVisible();
        await page.keyboard.press('Escape');

        await expect(root).toHaveAttribute('data-active-tool', 'select');
        await expect(root).toHaveAttribute('data-interaction-mode', 'select');
        await expect(page.locator('[data-editor-dynamic-input]')).toBeHidden();
        await expect(page.locator('[data-editor-snap-marker] > *')).toHaveCount(0);
        await expect(page.locator('[data-editor-resize-handle]')).toHaveCount(0);
        await expect(page.locator('[data-editor-command-name]')).toHaveText('SELECT');
        const state = await diagnostics(page);
        expect(state.selectionCount).toBe(0);
        expect(state.activeSnapMarkers).toBe(0);
        expect(browserErrors).toEqual([]);
    });
});
