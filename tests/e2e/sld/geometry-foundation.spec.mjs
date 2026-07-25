import { test, expect } from 'playwright/test';
import { e2eSnapshot, openEditor, selectNode, serverState } from './helpers/editor.mjs';

test.describe('APP67M.1 · Constraint-based geometry foundation', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('publishes the canonical model-space geometry contract', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const contract = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.geometryContract());
        const snapshot = await e2eSnapshot(page);

        expect(contract.version).toBe('67M.1');
        expect(contract.contract).toBe('canonical-model-space-v1');
        expect(contract.gridSize).toBe(20);
        expect(contract.canvas).toEqual({ width: 1640, height: 940 });
        expect(snapshot.editor.version).toBe(16);
        expect(snapshot.editor.geometry.version).toBe('67M.1');
        await expect(root).toHaveAttribute('data-geometry-version', '67M.1');
        await expect(page.locator('[data-editor-geometry-version]')).toHaveText('GEOM 67M.1');
        expect(browserErrors).toEqual([]);
    });

    test('keeps transformed port anchors canonical after a quarter turn', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const before = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.portGraphPoint('inverter', 'AC_OUT'));
        expect(before.side).toBe('right');

        await selectNode(page, 'inverter');
        await page.locator('[data-editor-ribbon-tab="manage"]').click();
        await page.locator('[data-editor-action="rotate-right"]:visible').first().click();

        const after = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.portGraphPoint('inverter', 'AC_OUT'));
        expect(after.side).toBe('bottom');
        expect(after.x).not.toBe(before.x);
        expect(after.y).not.toBe(before.y);
        expect(after.relative_x).toBeGreaterThanOrEqual(0);
        expect(after.relative_x).toBeLessThanOrEqual(1);
        expect(after.relative_y).toBeGreaterThanOrEqual(0);
        expect(after.relative_y).toBeLessThanOrEqual(1);
        expect(browserErrors).toEqual([]);
    });

    test('detects equipment overlap and updates live geometry health', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const sourceBounds = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('pv_array'));
        const contract = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.geometryContract());
        const moved = await page.evaluate(({ x, y }) => window.__NEXORIN_SLD_E2E__?.moveObject('dc_protection', x, y), sourceBounds);
        const expectedX = Math.round(sourceBounds.x / contract.gridSize) * contract.gridSize;
        const expectedY = Math.round(sourceBounds.y / contract.gridSize) * contract.gridSize;
        expect(moved.x).toBe(expectedX);
        expect(moved.y).toBe(expectedY);

        const report = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.geometryDiagnostics());
        expect(report.summary.overlaps).toBeGreaterThan(0);
        expect(report.summary.errors).toBeGreaterThan(0);
        await expect(root).toHaveAttribute('data-geometry-health', 'error');
        await expect(page.locator('[data-editor-geometry-issue-count]')).not.toHaveText('0');
        expect(browserErrors).toEqual([]);
    });

    test('persists geometry metadata with the saved editor layer', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const sourceBounds = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('dc_protection'));
        const moved = await page.evaluate(({ x, y }) => (
            window.__NEXORIN_SLD_E2E__?.moveObject('dc_protection', x + 20, y)
        ), sourceBounds);
        expect(moved.x).not.toBe(sourceBounds.x);
        await expect(page.locator('[data-editor-save-state]')).toHaveText('Unsaved changes');

        const saveResponsePromise = page.waitForResponse((response) => (
            response.url().endsWith('/__e2e/save')
            && response.request().method() === 'PATCH'
        ));
        await page.locator('[data-editor-save]').last().click();
        const saveResponse = await saveResponsePromise;
        expect(saveResponse.ok()).toBe(true);
        await expect(page.locator('[data-editor-save-state]')).toContainText(/saved/i);
        await expect.poll(async () => (await serverState(page)).saveCount).toBe(1);

        const state = await serverState(page);
        expect(state.lastSave).not.toBeNull();
        expect(state.lastSave.editor.version).toBe(16);
        expect(state.lastSave.editor.geometry.version).toBe('67M.1');
        expect(state.lastSave.editor.geometry.contract).toBe('canonical-model-space-v1');
        expect(state.lastSave.editor.geometry.diagnostics).toHaveProperty('issues');
        expect(state.lastSave.editor.geometry.diagnostics).toHaveProperty('overlaps');
        expect(browserErrors).toEqual([]);
    });
});
