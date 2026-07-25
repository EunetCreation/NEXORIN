import { test, expect } from 'playwright/test';
import { addSymbol, e2eSnapshot, openEditor } from './helpers/editor.mjs';

async function selectObjects(page, ids) {
    const count = await page.evaluate((objectIds) => window.__NEXORIN_SLD_E2E__?.selectObjects(objectIds), ids);
    expect(count).toBe(ids.length);
}

async function addThreeManualSymbols(page) {
    await addSymbol(page, 'weather_station', 0.48, 0.72);
    await addSymbol(page, 'monitoring', 0.60, 0.78);
    await addSymbol(page, 'transformer', 0.72, 0.68);
    const snapshot = await e2eSnapshot(page);
    return snapshot.editor.manual_nodes.slice(-3).map((node) => node.id);
}

test.describe('APP67G.3 · Manage panel transformations', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('aligns and distributes selected manual equipment deterministically', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const ids = await addThreeManualSymbols(page);
        await page.locator('[data-editor-ribbon-tab="manage"]').click();

        await selectObjects(page, ids.slice(0, 2));
        await page.locator('[data-editor-action="align-top"]:visible').click();
        let snapshot = await e2eSnapshot(page);
        const firstPair = ids.slice(0, 2).map((id) => snapshot.editor.positions[id]);
        expect(firstPair[0].y).toBe(firstPair[1].y);

        await selectObjects(page, ids);
        await page.locator('[data-editor-action="distribute-horizontal"]:visible').click();
        snapshot = await e2eSnapshot(page);
        const ordered = ids
            .map((id) => snapshot.editor.positions[id])
            .sort((a, b) => a.x - b.x);
        const gapA = ordered[1].x - (ordered[0].x + ordered[0].w);
        const gapB = ordered[2].x - (ordered[1].x + ordered[1].w);
        expect(Math.abs(gapA - gapB)).toBeLessThanOrEqual(20);
        expect(browserErrors).toEqual([]);
    });

    test('rotates, mirrors, resets, groups, ungroups, and changes stacking order', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const ids = await addThreeManualSymbols(page);
        await page.locator('[data-editor-ribbon-tab="manage"]').click();

        await selectObjects(page, [ids[0]]);
        await page.locator('[data-editor-action="rotate-right"]:visible').first().click();
        await page.locator('[data-editor-action="mirror-horizontal"]:visible').first().click();
        let snapshot = await e2eSnapshot(page);
        let transformed = snapshot.editor.manual_nodes.find((node) => node.id === ids[0]);
        expect(transformed.rotation).toBe(90);
        expect(transformed.flip_h).toBe(true);

        await page.locator('[data-editor-action="reset-transform"]:visible').first().click();
        snapshot = await e2eSnapshot(page);
        transformed = snapshot.editor.manual_nodes.find((node) => node.id === ids[0]);
        expect(transformed.rotation).toBe(0);
        expect(transformed.flip_h).toBe(false);
        expect(transformed.flip_v).toBe(false);

        await selectObjects(page, ids.slice(0, 2));
        await page.locator('[data-editor-action="group"]:visible').click();
        snapshot = await e2eSnapshot(page);
        const grouped = ids.slice(0, 2).map((id) => snapshot.editor.manual_nodes.find((node) => node.id === id));
        expect(grouped[0].group_id).toBeTruthy();
        expect(grouped[1].group_id).toBe(grouped[0].group_id);

        await page.locator('[data-editor-action="bring-front"]:visible').click();
        await page.locator('[data-editor-action="send-back"]:visible').click();
        await page.locator('[data-editor-action="ungroup"]:visible').click();
        snapshot = await e2eSnapshot(page);
        const ungrouped = ids.slice(0, 2).map((id) => snapshot.editor.manual_nodes.find((node) => node.id === id));
        expect(ungrouped.every((node) => !node.group_id)).toBe(true);
        expect(browserErrors).toEqual([]);
    });

    test('cuts and restores a manual object through the Home clipboard commands', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'weather_station', 0.56, 0.76);
        const before = Number(await page.locator('[data-editor-node-count]').textContent());

        await page.locator('[data-editor-ribbon-tab="home"]').click();
        await page.locator('[data-editor-action="cut"]:visible').click();
        await expect(page.locator('[data-editor-node-count]')).toHaveText(String(before - 1));
        await page.locator('[data-editor-action="paste"]:visible').click();
        await expect(page.locator('[data-editor-node-count]')).toHaveText(String(before));
        expect(browserErrors).toEqual([]);
    });
});
