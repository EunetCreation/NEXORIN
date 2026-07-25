import { test, expect } from 'playwright/test';
import { addSymbol, clickGraph, e2eSnapshot, openEditor, serverState } from './helpers/editor.mjs';

test.describe('APP67G.3 · save, reload, layer, and close regression', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('serializes the full editor layer and restores it after reload', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'monitoring', 0.58, 0.76);

        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.12, 0.80);
        await clickGraph(page, 0.25, 0.80);
        await page.keyboard.press('Enter');

        await page.locator('[data-editor-ribbon-tab="annotate"]').click();
        await page.locator('[data-editor-tool="text"]').click();
        await clickGraph(page, 0.48, 0.78);
        await page.locator('[data-editor-field="title"]').fill('Persisted drawing note');
        await page.locator('[data-editor-field="title"]').blur();

        await page.locator('[data-editor-save]').last().click();
        await expect(page.locator('[data-editor-save-state]')).toContainText('Saved');
        const state = await serverState(page);
        expect(state.saveCount).toBe(1);
        expect(state.lastSave.editor.manual_nodes).toHaveLength(1);
        expect(state.lastSave.editor.manual_lines).toHaveLength(1);
        expect(state.lastSave.editor.annotations).toHaveLength(1);

        await page.reload({ waitUntil: 'networkidle' });
        await expect(page.locator('[data-sld-editor]')).toHaveAttribute('data-editor-ready', 'true');
        const restored = await e2eSnapshot(page);
        expect(restored.editor.manual_nodes).toHaveLength(1);
        expect(restored.editor.manual_lines).toHaveLength(1);
        expect(restored.editor.annotations[0].text).toBe('Persisted drawing note');
        expect(browserErrors).toEqual([]);
    });

    test('persists layer visibility, print state, paper mode, and panel state', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-inspector-tab="layers"]').click();
        const commRow = page.locator('[data-editor-layer="E-COMM"]');
        await commRow.locator('[data-editor-layer-action="visible"]').click();
        await commRow.locator('[data-editor-layer-action="print"]').click();

        await page.locator('[data-editor-space="sheet"]').click();
        await page.locator('[data-editor-paper-mode]').selectOption('multi');
        await page.locator('[data-editor-space="model"]').click();
        await page.locator('[data-editor-toggle-panel="palette"]').click();
        await page.locator('[data-editor-action="toggle-ribbon"]').click();

        await page.locator('[data-editor-save]').last().click();
        const state = await serverState(page);
        expect(state.lastSave.editor.layers['E-COMM'].visible).toBe(false);
        expect(state.lastSave.editor.layers['E-COMM'].print).toBe(false);
        expect(state.lastSave.editor.paper.mode).toBe('multi');
        expect(state.lastSave.editor.panels.palette_collapsed).toBe(true);
        expect(state.lastSave.editor.panels.ribbon_collapsed).toBe(true);
        expect(browserErrors).toEqual([]);
    });

    test('warns before closing a dirty drawing and supports cancel, save, and close', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'weather_station', 0.56, 0.76);
        await expect(page.locator('[data-editor-save-state]')).toHaveText('Unsaved changes');

        await page.locator('[data-editor-close]').last().click();
        await expect(page.locator('[data-editor-close-dialog]')).toBeVisible();
        await page.locator('[data-editor-close-cancel]').click();
        await expect(page.locator('[data-editor-close-dialog]')).toBeHidden();

        await page.locator('[data-editor-close]').last().click();
        await page.locator('[data-editor-close-save]').click();
        await expect(page.locator('[data-e2e-closed]')).toHaveText('Editor closed');
        expect(browserErrors).toEqual([]);
    });
});
