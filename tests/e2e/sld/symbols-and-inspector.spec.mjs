import { test, expect } from 'playwright/test';
import { addSymbol, e2eSnapshot, openEditor, selectNode } from './helpers/editor.mjs';

test.describe('APP67G.3 · electrical library, selection, and Inspector', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('searches and filters the electrical library without losing category structure', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const search = page.locator('[data-editor-symbol-search]');
        await search.fill('transformer');
        await expect(page.locator('[data-editor-add-kind="transformer"]')).toBeVisible();
        await expect(page.locator('[data-editor-add-kind="pv_array"]')).toBeHidden();

        await search.fill('');
        await page.locator('[data-editor-symbol-filter="monitoring"]').click();
        await expect(page.locator('[data-editor-add-kind="monitoring"]')).toBeVisible();
        await expect(page.locator('[data-editor-add-kind="inverter"]')).toBeHidden();
        await page.locator('[data-editor-symbol-filter="all"]').click();
        await expect(page.locator('[data-editor-add-kind="inverter"]')).toBeVisible();
        expect(browserErrors).toEqual([]);
    });

    test('places a manual symbol and edits its authoritative manual properties', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'monitoring', 0.53, 0.76);
        await expect(page.locator('[data-editor-node-count]')).toHaveText('7');
        await expect(page.locator('[data-editor-inspector-form]')).toBeVisible();
        await expect(page.locator('[data-editor-inspector-authority]')).toContainText('Manual drawing layer');

        await page.locator('[data-editor-field="title"]').fill('PV monitoring gateway');
        await page.locator('[data-editor-field="title"]').blur();
        await page.locator('[data-editor-field="tag"]').fill('GW-PLTS-01');
        await page.locator('[data-editor-field="tag"]').blur();
        await page.locator('[data-editor-field="rated_voltage"]').fill('24');
        await page.locator('[data-editor-field="rated_voltage"]').blur();
        await page.locator('[data-editor-field="manufacturer"]').fill('NEXORIN Controls');
        await page.locator('[data-editor-field="manufacturer"]').blur();

        const snapshot = await e2eSnapshot(page);
        const manual = snapshot.editor.manual_nodes.at(-1);
        expect(manual.title).toBe('PV monitoring gateway');
        expect(manual.tag).toBe('GW-PLTS-01');
        expect(manual.rated_voltage).toBe(24);
        expect(manual.manufacturer).toBe('NEXORIN Controls');
        expect(manual.rating).toContain('24 V');
        expect(browserErrors).toEqual([]);
    });

    test('keeps calculated ratings locked while allowing drafting position and tag override', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await selectNode(page, 'inverter');

        await expect(page.locator('[data-editor-inspector-authority]')).toContainText('Calculation authority');
        await expect(page.locator('[data-editor-field="rating"]')).toBeDisabled();
        await expect(page.locator('[data-editor-field="title"]')).toBeDisabled();
        await expect(page.locator('[data-editor-field="tag"]')).toBeEnabled();
        await expect(page.locator('[data-editor-field="x"]')).toBeEnabled();

        await page.locator('[data-editor-field="tag"]').fill('INV-MAIN-01');
        await page.locator('[data-editor-field="tag"]').blur();
        await page.locator('[data-editor-field="x"]').fill('720');
        await page.locator('[data-editor-field="x"]').blur();

        const snapshot = await e2eSnapshot(page);
        expect(snapshot.editor.positions.inverter.tag_override).toBe('INV-MAIN-01');
        expect(snapshot.editor.positions.inverter.x).toBe(720);
        expect(browserErrors).toEqual([]);
    });

    test('switches Inspector Properties, Engineering, and Layers without losing selection', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await selectNode(page, 'inverter');
        const before = await e2eSnapshot(page);

        for (const tab of ['data', 'layers', 'properties']) {
            await page.locator(`[data-editor-inspector-tab="${tab}"]`).click();
            await expect(page.locator(`[data-editor-inspector-tab="${tab}"]`)).toHaveClass(/is-active/);
            await expect(page.locator(`[data-editor-inspector-panel="${tab}"]`)).toHaveClass(/is-active/);
        }

        const after = await e2eSnapshot(page);
        expect(after.selection).toEqual(before.selection);
        expect(browserErrors).toEqual([]);
    });

    test('supports copy, paste, duplicate, undo, redo, lock, and delete for manual objects', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'weather_station', 0.55, 0.76);
        const base = Number(await page.locator('[data-editor-node-count]').textContent());

        await page.locator('[data-editor-action="copy"]').first().click();
        await page.locator('[data-editor-action="paste"]').first().click();
        await expect(page.locator('[data-editor-node-count]')).toHaveText(String(base + 1));

        await page.locator('[data-editor-action="undo"]').first().click();
        await expect(page.locator('[data-editor-node-count]')).toHaveText(String(base));
        await page.locator('[data-editor-action="redo"]').first().click();
        await expect(page.locator('[data-editor-node-count]')).toHaveText(String(base + 1));
        let snapshot = await e2eSnapshot(page);
        const restoredId = snapshot.editor.manual_nodes.at(-1)?.id;
        expect(restoredId).toBeTruthy();
        await page.evaluate((id) => window.__NEXORIN_SLD_E2E__.selectObject(id), restoredId);

        await page.locator('[data-editor-action="duplicate"]').first().click();
        await expect(page.locator('[data-editor-node-count]')).toHaveText(String(base + 2));

        await page.locator('[data-editor-action="lock"]:visible').first().click();
        snapshot = await e2eSnapshot(page);
        expect(snapshot.editor.manual_nodes.some((node) => node.locked === true)).toBe(true);
        await page.locator('[data-editor-action="unlock"]:visible').first().click();

        await page.locator('[data-editor-delete]:visible').click();
        await expect(page.locator('[data-editor-node-count]')).toHaveText(String(base + 1));
        snapshot = await e2eSnapshot(page);
        expect(snapshot.editor.manual_nodes).toHaveLength(2);
        expect(browserErrors).toEqual([]);
    });

    test('selects multiple equipment objects with a left-to-right block window', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const selectionWindow = await page.evaluate(() => {
            const ids = ['pv_array', 'dc_protection', 'inverter'];
            const rects = ids.map((id) => document.querySelector(`[data-nxsld-node-id="${id}"]`)?.getBoundingClientRect()).filter(Boolean);
            if (rects.length !== ids.length) return null;
            const graphRect = document.querySelector('[data-editor-graph]')?.getBoundingClientRect();
            if (!graphRect) return null;
            return {
                x1: graphRect.left + 20,
                y1: Math.max(graphRect.top + 20, Math.min(...rects.map((rect) => rect.top)) - 80),
                x2: Math.max(...rects.map((rect) => rect.right)) + 48,
                y2: Math.max(...rects.map((rect) => rect.bottom)) + 48,
            };
        });
        expect(selectionWindow).toBeTruthy();
        await page.mouse.move(selectionWindow.x1, selectionWindow.y1);
        await page.mouse.down();
        await page.mouse.move(selectionWindow.x2, selectionWindow.y2, { steps: 16 });
        await page.mouse.up();

        const snapshot = await e2eSnapshot(page);
        const selectedIds = new Set(snapshot.selection.map((item) => item.id));
        expect(selectedIds.has('pv_array')).toBe(true);
        expect(selectedIds.has('dc_protection')).toBe(true);
        expect(selectedIds.has('inverter')).toBe(true);
        expect(browserErrors).toEqual([]);
    });

    test('selects the complete drawing with Ctrl+A and keeps multi-selection explicit', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-graph]').focus();
        await page.keyboard.press('Control+A');
        const snapshot = await e2eSnapshot(page);
        expect(snapshot.selection.length).toBeGreaterThanOrEqual(11);
        await expect(page.locator('[data-editor-selection-count]')).not.toHaveText('0');
        await expect(page.locator('[data-editor-inspector-heading]')).toContainText('objects');
        expect(browserErrors).toEqual([]);
    });
});
