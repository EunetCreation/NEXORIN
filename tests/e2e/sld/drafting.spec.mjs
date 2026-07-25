import { test, expect } from 'playwright/test';
import { clickGraph, e2eSnapshot, openEditor } from './helpers/editor.mjs';

async function geometryCount(page) {
    const snapshot = await e2eSnapshot(page);
    return snapshot.editor.manual_lines.length + snapshot.editor.manual_shapes.length;
}

test.describe('APP67G.3 · precision drafting commands', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('creates a line, rectangle, note, and associative leader', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const initial = await e2eSnapshot(page);

        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.10, 0.78);
        await clickGraph(page, 0.24, 0.78);
        await page.keyboard.press('Enter');

        await page.locator('[data-editor-tool="rectangle"]').click();
        await clickGraph(page, 0.30, 0.70);
        await clickGraph(page, 0.42, 0.82);

        await page.locator('[data-editor-ribbon-tab="annotate"]').click();
        await page.locator('[data-editor-tool="text"]').click();
        await clickGraph(page, 0.48, 0.78);
        await page.locator('[data-editor-field="title"]').fill('Coordination note');
        await page.locator('[data-editor-field="title"]').blur();

        await page.locator('[data-editor-tool="leader"]').click();
        await clickGraph(page, 0.68, 0.76);
        await clickGraph(page, 0.78, 0.70);
        await page.locator('[data-editor-field="title"]').fill('Check utility interface');
        await page.locator('[data-editor-field="title"]').blur();

        const snapshot = await e2eSnapshot(page);
        expect(snapshot.editor.manual_lines.length).toBe(initial.editor.manual_lines.length + 2);
        expect(snapshot.editor.manual_shapes.length).toBe(initial.editor.manual_shapes.length + 1);
        expect(snapshot.editor.annotations.length).toBe(initial.editor.annotations.length + 2);
        const leader = snapshot.editor.manual_lines.find((line) => line.entity === 'leader');
        const leaderNote = snapshot.editor.annotations.find((note) => note.group_id === leader?.group_id);
        expect(leader?.group_id).toBeTruthy();
        expect(leaderNote?.text).toBe('Check utility interface');
        expect(browserErrors).toEqual([]);
    });

    for (const mode of ['center-radius', 'center-diameter', 'two-point', 'three-point']) {
        test(`creates a valid circle using ${mode}`, async ({ page }) => {
            const { root, browserErrors } = await openEditor(page);
            const before = await geometryCount(page);

            await page.locator('[data-editor-geometry-menu-toggle]').first().click();
            await page.locator(`[data-editor-circle-mode="${mode}"]`).click();
            await expect(root).toHaveAttribute('data-circle-mode', mode);
            await page.locator('[data-editor-tool="circle"]').click();
            await clickGraph(page, 0.20, 0.72);
            await clickGraph(page, 0.27, 0.72);
            if (mode === 'three-point') await clickGraph(page, 0.235, 0.65);

            const snapshot = await e2eSnapshot(page);
            expect(await geometryCount(page)).toBe(before + 1);
            expect(snapshot.editor.manual_shapes.at(-1)?.shape_type).toBe('circle');
            expect(snapshot.editor.manual_shapes.at(-1)?.w).toBeGreaterThan(1);
            expect(snapshot.editor.manual_shapes.at(-1)?.h).toBeGreaterThan(1);
            expect(browserErrors).toEqual([]);
        });
    }

    for (const mode of ['three-point', 'center-start-end', 'start-center-end', 'start-end-radius']) {
        test(`creates a valid arc using ${mode}`, async ({ page }) => {
            const { root, browserErrors } = await openEditor(page);
            const before = await geometryCount(page);

            await page.locator('[data-editor-geometry-menu-toggle]').nth(1).click();
            await page.locator(`[data-editor-arc-mode="${mode}"]`).click();
            await expect(root).toHaveAttribute('data-arc-mode', mode);
            await page.locator('[data-editor-tool="arc"]').click();

            if (mode === 'center-start-end') {
                await clickGraph(page, 0.52, 0.72);
                await clickGraph(page, 0.58, 0.72);
                await clickGraph(page, 0.52, 0.64);
            } else if (mode === 'start-center-end') {
                await clickGraph(page, 0.58, 0.72);
                await clickGraph(page, 0.52, 0.72);
                await clickGraph(page, 0.52, 0.64);
            } else {
                await clickGraph(page, 0.48, 0.76);
                await clickGraph(page, 0.54, 0.66);
                await clickGraph(page, 0.62, 0.76);
            }

            const snapshot = await e2eSnapshot(page);
            expect(await geometryCount(page)).toBe(before + 1);
            expect(snapshot.editor.manual_shapes.at(-1)?.shape_type).toBe('arc');
            expect(Number.isFinite(snapshot.editor.manual_shapes.at(-1)?.start_angle)).toBe(true);
            expect(Number.isFinite(snapshot.editor.manual_shapes.at(-1)?.end_angle)).toBe(true);
            expect(browserErrors).toEqual([]);
        });
    }

    test('cancels an unfinished command with Escape without creating invalid geometry', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const before = await geometryCount(page);
        await page.locator('[data-editor-tool="rectangle"]').click();
        await clickGraph(page, 0.20, 0.72);
        await page.keyboard.press('Escape');
        await expect(root).toHaveAttribute('data-active-tool', 'select');
        expect(await geometryCount(page)).toBe(before);
        expect(browserErrors).toEqual([]);
    });
});
