import { createHash } from 'node:crypto';
import { test, expect } from 'playwright/test';
import { openEditor, serverState } from './helpers/editor.mjs';

const CONTRACT = 'deterministic-scene-fingerprint-v1';
const ENGINE = 'canonical-scene-auditor';

function digest(value) {
    return createHash('sha256').update(value).digest('hex');
}

async function report(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.visualRegression());
}

async function snapshot(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.visualSnapshot());
}

async function modelSpaceSvg(page) {
    const svg = page.locator('[data-editor-graph]').locator(':scope > svg');
    await expect(svg).toHaveCount(1);
    return svg.evaluate((element) => element.outerHTML);
}

test.describe('APP67M.4 · Visual Regression and CAD renderer closure', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('boots on the canonical visual-regression contract with a locked fingerprint', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const visual = await report(page);

        expect(visual.version).toBe('67M.4');
        expect(visual.contract).toBe(CONTRACT);
        expect(visual.engine).toBe(ENGINE);
        expect(visual.fingerprint).toMatch(/^vr-[0-9a-f]{8}$/);
        expect(visual.summary.equipment).toBeGreaterThan(0);
        expect(visual.summary.routes).toBeGreaterThan(0);
        expect(visual.summary.labels).toBe(visual.summary.equipment);
        await expect(root).toHaveAttribute('data-visual-regression-version', '67M.4');
        await expect(root).toHaveAttribute('data-visual-regression-contract', CONTRACT);
        await expect(root).toHaveAttribute('data-visual-regression-fingerprint', /^vr-[0-9a-f]{8}$/);
        await expect(page.locator('[data-editor-visual-regression-version]')).toHaveText('VISUAL 67M.4');
        expect(browserErrors).toEqual([]);
    });

    test('keeps model-space fingerprint and rendered SVG stable after deterministic rebuilds', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const before = await snapshot(page);
        const beforeSvg = await modelSpaceSvg(page);

        expect(await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.rebuildSmartRoutes())).toBe(true);
        await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.rebuildLabels());
        await expect.poll(async () => (await snapshot(page)).fingerprint).toBe(before.fingerprint);

        const after = await snapshot(page);
        const afterSvg = await modelSpaceSvg(page);
        expect(after.fingerprint).toBe(before.fingerprint);
        expect(digest(afterSvg)).toBe(digest(beforeSvg));
        expect(browserErrors).toEqual([]);
    });

    test('detects a real scene change outside the coordinate tolerance', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const baseline = await snapshot(page);
        const bounds = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('dc_protection'));
        await page.evaluate(({ x, y }) => window.__NEXORIN_SLD_E2E__?.moveObject('dc_protection', x + 20, y), bounds);

        const current = await snapshot(page);
        const comparison = await page.evaluate((locked) => window.__NEXORIN_SLD_E2E__?.compareVisualSnapshot(locked), baseline);
        expect(current.fingerprint).not.toBe(baseline.fingerprint);
        expect(comparison.match).toBe(false);
        expect(comparison.changed_count).toBeGreaterThan(0);
        await expect(page.locator('[data-editor-save-state]')).toHaveText('Unsaved changes');
        expect(browserErrors).toEqual([]);
    });

    test('persists visual fingerprint, tolerance, and integrity summary with the editor layer', async ({ page }) => {
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
        const visual = state.lastSave.editor.visual_regression;
        expect(visual.version).toBe('67M.4');
        expect(visual.contract).toBe(CONTRACT);
        expect(visual.engine).toBe(ENGINE);
        expect(visual.fingerprint).toMatch(/^vr-[0-9a-f]{8}$/);
        expect(visual.baseline_fingerprint).toMatch(/^vr-[0-9a-f]{8}$/);
        expect(visual.coordinate_tolerance).toBe(0.5);
        expect(visual.summary.equipment).toBeGreaterThan(0);
        expect(visual.summary.routes).toBeGreaterThan(0);
        expect(visual.summary.labels).toBe(visual.summary.equipment);
        expect(browserErrors).toEqual([]);
    });
});
