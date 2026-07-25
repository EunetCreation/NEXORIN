import { test, expect } from 'playwright/test';
import { openEditor, serverState } from './helpers/editor.mjs';

const CONTRACT = 'deterministic-clearance-label-v1';
const ENGINE = 'candidate-score-label-placer';

async function report(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.labelPlacement());
}

test.describe('APP67M.3 · Automatic label placement', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('publishes deterministic label contract and derived labels', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const contract = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.labelPlacementContract());
        const placement = await report(page);

        expect(contract.version).toBe('67M.3');
        expect(contract.name).toBe(CONTRACT);
        expect(contract.engine).toBe(ENGINE);
        expect(placement.version).toBe('67M.3');
        expect(placement.contract).toBe(CONTRACT);
        expect(placement.engine).toBe(ENGINE);
        expect(placement.placements.length).toBeGreaterThan(0);
        expect(placement.summary.labels).toBe(placement.placements.length);
        expect(placement.placements.every((item) => /^lp-[0-9a-f]{8}$/.test(item.signature))).toBe(true);
        await expect(root).toHaveAttribute('data-label-placement-version', '67M.3');
        await expect(root).toHaveAttribute('data-label-placement-contract', CONTRACT);
        await expect(page.locator('[data-editor-label-placement-version]')).toHaveText('LABEL 67M.3');
        await expect(page.locator('.nxsld-auto-label')).toHaveCount(placement.placements.length);
        expect(browserErrors).toEqual([]);
    });

    test('rebuilds label positions with stable signatures', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const before = await report(page);
        const beforeSignatures = Object.fromEntries(before.placements.map((item) => [item.id, item.signature]));
        const rebuilt = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.rebuildLabels());
        const afterSignatures = Object.fromEntries(rebuilt.placements.map((item) => [item.id, item.signature]));

        expect(afterSignatures).toEqual(beforeSignatures);
        expect(rebuilt.summary).toEqual(before.summary);
        expect(browserErrors).toEqual([]);
    });

    test('relocates the connected label after equipment moves', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const before = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.labelBounds('dc_protection'));
        const bounds = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('dc_protection'));
        expect(before).toBeTruthy();
        expect(bounds).toBeTruthy();

        const moved = await page.evaluate(({ x, y }) => window.__NEXORIN_SLD_E2E__?.moveObject('dc_protection', x + 40, y + 20), bounds);
        expect(moved.x).not.toBe(bounds.x);
        const after = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.labelBounds('dc_protection'));
        expect(after).toBeTruthy();
        expect(after.signature).not.toBe(before.signature);
        expect(after.x !== before.x || after.y !== before.y).toBe(true);
        await expect(page.locator('[data-editor-save-state]')).toHaveText('Unsaved changes');
        expect(browserErrors).toEqual([]);
    });

    test('persists label placement metadata with the editor layer', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const bounds = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('dc_protection'));
        await page.evaluate(({ x, y }) => window.__NEXORIN_SLD_E2E__?.moveObject('dc_protection', x + 20, y), bounds);
        await expect(page.locator('[data-editor-save-state]')).toHaveText('Unsaved changes');

        const responsePromise = page.waitForResponse((response) => response.url().includes('/__e2e/save') && response.request().method() === 'PATCH');
        await page.evaluate(async () => window.__NEXORIN_SLD_E2E__?.save());
        const response = await responsePromise;
        expect(response.ok()).toBe(true);
        await expect.poll(async () => (await serverState(page)).saveCount).toBe(1);

        const state = await serverState(page);
        const labels = state.lastSave.editor.label_placement;
        expect(labels.version).toBe('67M.3');
        expect(labels.contract).toBe(CONTRACT);
        expect(labels.engine).toBe(ENGINE);
        expect(labels.summary.labels).toBeGreaterThan(0);
        expect(Object.keys(labels.positions).length).toBe(labels.summary.labels);
        expect(labels.positions.dc_protection.signature).toMatch(/^lp-[0-9a-f]{8}$/);
        expect(browserErrors).toEqual([]);
    });
});
