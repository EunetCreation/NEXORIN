import { test, expect } from 'playwright/test';
import { dragGraph, e2eSnapshot, openEditor } from './helpers/editor.mjs';

test.describe('APP67G.3 · ribbon, panels, viewport, and display modes', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('switches every task-specific ribbon panel without duplicate command ambiguity', async ({ page }) => {
        const { browserErrors } = await openEditor(page);

        for (const tab of ['insert', 'annotate', 'view', 'manage', 'home']) {
            await page.locator(`[data-editor-ribbon-tab="${tab}"]`).click();
            await expect(page.locator(`[data-editor-ribbon-tab="${tab}"]`)).toHaveClass(/is-active/);
            await expect(page.locator(`[data-editor-ribbon-panel="${tab}"]`)).toHaveClass(/is-active/);
        }

        await page.locator('[data-editor-ribbon-tab="insert"]').click();
        await expect(page.locator('[data-editor-ribbon-panel="insert"] [data-editor-action="open-library"]')).toHaveCount(1);
        await expect(page.locator('[data-editor-ribbon-panel="insert"] [data-editor-tool="wire"]')).toHaveCount(0);
        await expect(page.locator('[data-editor-ribbon-panel="insert"] [data-editor-tool="rectangle"]')).toHaveCount(0);
        expect(browserErrors).toEqual([]);
    });

    test('collapses and restores ribbon, library, and inspector with persistent rails', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);

        await page.locator('[data-editor-action="toggle-ribbon"]').click();
        await expect(root).toHaveClass(/is-ribbon-collapsed/);
        await page.locator('[data-editor-action="toggle-ribbon"]').click();
        await expect(root).not.toHaveClass(/is-ribbon-collapsed/);

        await page.locator('[data-editor-toggle-panel="palette"]').click();
        await expect(root).toHaveClass(/is-palette-collapsed/);
        await expect(page.locator('[data-editor-open-panel="palette"]')).toBeVisible();
        await page.locator('[data-editor-open-panel="palette"]').click();
        await expect(root).not.toHaveClass(/is-palette-collapsed/);

        await page.locator('[data-editor-toggle-panel="inspector"]').click();
        await expect(root).toHaveClass(/is-inspector-collapsed/);
        await expect(page.locator('[data-editor-open-panel="inspector"]')).toBeVisible();
        await page.locator('[data-editor-open-panel="inspector"]').click();
        await expect(root).not.toHaveClass(/is-inspector-collapsed/);
        expect(browserErrors).toEqual([]);
    });

    test('switches clean, engineering, and inspection density without changing the model', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const baseline = await e2eSnapshot(page);

        await page.locator('[data-editor-ribbon-tab="view"]').click();
        for (const mode of ['engineering', 'inspection', 'clean']) {
            await page.locator(`[data-editor-display="${mode}"]`).click();
            await expect(root).toHaveClass(new RegExp(`is-display-${mode}`));
            const snapshot = await e2eSnapshot(page);
            expect(snapshot.displayMode).toBe(mode);
            expect(snapshot.editor.manual_nodes).toHaveLength(baseline.editor.manual_nodes.length);
            expect(snapshot.editor.manual_connections).toHaveLength(baseline.editor.manual_connections.length);
        }
        expect(browserErrors).toEqual([]);
    });

    test('opens A3 Paper Space and returns to the same canonical Model Space', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const before = await e2eSnapshot(page);

        await page.locator('[data-editor-space="sheet"]').click();
        await expect(root).toHaveAttribute('data-active-space', 'sheet');
        await expect(page.locator('[data-editor-paper-page="0"]')).toBeVisible();
        await expect(page.locator('[data-editor-space-description]')).toContainText('A3');

        await page.locator('[data-editor-space="model"]').click();
        await expect(root).toHaveAttribute('data-active-space', 'model');
        const after = await e2eSnapshot(page);
        expect(after.editor.positions).toEqual(before.editor.positions);
        expect(after.editor.manual_nodes).toEqual(before.editor.manual_nodes);
        expect(browserErrors).toEqual([]);
    });

    test('enters and exits true fullscreen drafting mode', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        await page.locator('[data-editor-action="fullscreen"]:visible').first().click();
        await expect.poll(() => page.evaluate(() => Boolean(document.fullscreenElement))).toBe(true);
        await expect(root).toHaveClass(/is-true-fullscreen/);
        await page.keyboard.press('Escape');
        await expect.poll(() => page.evaluate(() => Boolean(document.fullscreenElement))).toBe(false);
        await expect(root).not.toHaveClass(/is-true-fullscreen/);
        expect(browserErrors).toEqual([]);
    });

    test('zooms, fits, and pans without moving drawing geometry', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const before = await e2eSnapshot(page);

        await page.locator('[data-editor-action="zoom-in"]:visible').first().click();
        const zoomed = await e2eSnapshot(page);
        expect(zoomed.viewport.scale).toBeGreaterThan(before.viewport.scale);

        await page.locator('[data-editor-action="zoom-out"]:visible').first().click();
        await page.locator('[data-editor-action="fit"]:visible').first().click();
        const fitted = await e2eSnapshot(page);
        expect(fitted.viewport.scale).toBeGreaterThan(0.1);

        await page.locator('[data-editor-tool="pan"]:visible').first().click();
        await dragGraph(page, [0.55, 0.55], [0.67, 0.64]);
        const panned = await e2eSnapshot(page);
        expect(panned.viewport.translateX).not.toBe(fitted.viewport.translateX);
        expect(panned.viewport.translateY).not.toBe(fitted.viewport.translateY);
        expect(panned.editor.positions).toEqual(before.editor.positions);
        expect(browserErrors).toEqual([]);
    });
});
