import { test, expect } from 'playwright/test';
import { addSymbol, clickPort, e2eSnapshot, openEditor } from './helpers/editor.mjs';


async function runCheck(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.runElectricalCheck());
}

test.describe('APP67I · electrical rule checking', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('reports the source topology as reviewable with no blocking errors', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        await expect(root).toHaveAttribute('data-electrical-check-status', 'review');
        await expect(root).toHaveAttribute('data-electrical-check-errors', '0');
        await expect(root).toHaveAttribute('data-electrical-check-warnings', '4');
        await page.locator('[data-editor-check-status]').click();
        await expect(page.locator('[data-editor-inspector-tab="checks"]')).toHaveClass(/is-active/);
        await expect(page.locator('[data-editor-check-count="error"]')).toHaveText('0');
        await expect(page.locator('[data-editor-check-count="warning"]')).toHaveText('4');
        await expect(page.locator('[data-editor-check-list] [data-editor-issue-id]')).toHaveCount(4);
        expect(browserErrors).toEqual([]);
    });

    test('detects an isolated manual object and locates it from the Checks panel', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'monitoring', 0.60, 0.76);
        const snapshot = await e2eSnapshot(page);
        const nodeId = snapshot.editor.manual_nodes.at(-1).id;
        const validation = await runCheck(page);
        const issue = validation.issues.find((candidate) => candidate.code === 'W005' && candidate.node_ids.includes(nodeId));
        expect(issue).toBeTruthy();
        expect(await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.locateIssue(id), issue.id)).toBe(true);
        const selected = await e2eSnapshot(page);
        expect(selected.selection.some((item) => item.id === nodeId)).toBe(true);
        await expect(page.locator('[data-sld-editor]')).toHaveClass(/is-display-inspection/);
        expect(browserErrors).toEqual([]);
    });

    test('blocks duplicate equipment tags and clears the error after correction', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        await addSymbol(page, 'monitoring', 0.61, 0.76);
        await page.locator('[data-editor-field="tag"]').fill('INV-01');
        await page.locator('[data-editor-field="tag"]').blur();
        let validation = await runCheck(page);
        expect(validation.blocking).toBe(true);
        expect(validation.issues.filter((issue) => issue.code === 'E007')).toHaveLength(2);
        await expect(root).toHaveAttribute('data-electrical-check-status', 'fail');

        await page.locator('[data-editor-field="tag"]').fill('MON-01');
        await page.locator('[data-editor-field="tag"]').blur();
        validation = await runCheck(page);
        expect(validation.issues.some((issue) => issue.code === 'E007')).toBe(false);
        expect(validation.blocking).toBe(false);
        await expect(root).toHaveAttribute('data-electrical-check-status', 'review');
        expect(browserErrors).toEqual([]);
    });

    test('requires power-cable metadata and clears cable findings after completion', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'panel', 0.55, 0.77);
        let snapshot = await e2eSnapshot(page);
        const panelId = snapshot.editor.manual_nodes.at(-1).id;
        await addSymbol(page, 'load', 0.76, 0.77);
        snapshot = await e2eSnapshot(page);
        const loadId = snapshot.editor.manual_nodes.at(-1).id;

        const beforeConnectionCount = snapshot.editor.manual_connections.length;
        await page.locator('[data-editor-tool="wire"]').click();
        await clickPort(page, panelId, 'AC_OUT');
        await clickPort(page, loadId, 'AC_IN');
        await expect.poll(async () => {
            const current = await e2eSnapshot(page);
            return current.editor.manual_connections.length;
        }).toBe(beforeConnectionCount + 1);
        snapshot = await e2eSnapshot(page);
        const createdWire = snapshot.editor.manual_connections.at(-1);
        expect(createdWire.from).toBe(panelId);
        expect(createdWire.to).toBe(loadId);
        expect(createdWire.network).toBe('ac_power');
        expect(createdWire.cable_type).toBe('');
        expect(createdWire.cable_size).toBe('');
        let validation = await runCheck(page);
        expect(validation.issues.some((issue) => issue.code === 'W002')).toBe(true);
        expect(validation.issues.some((issue) => issue.code === 'N001')).toBe(true);

        await page.locator('[data-editor-wire-field="cable_type"]').fill('N2XY');
        await page.locator('[data-editor-wire-field="cable_type"]').blur();
        await page.locator('[data-editor-wire-field="cable_size"]').fill('6');
        await page.locator('[data-editor-wire-field="cable_size"]').blur();
        await page.locator('[data-editor-wire-field="voltage_class"]').fill('0.6/1 kV');
        await page.locator('[data-editor-wire-field="voltage_class"]').blur();
        validation = await runCheck(page);
        expect(validation.issues.some((issue) => issue.code === 'W002')).toBe(false);
        expect(validation.issues.some((issue) => issue.code === 'N001')).toBe(false);
        expect(browserErrors).toEqual([]);
    });
});
