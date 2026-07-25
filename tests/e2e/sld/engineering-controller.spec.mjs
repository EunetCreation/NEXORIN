import { test, expect } from 'playwright/test';
import { e2eSnapshot, openEditor, serverState } from './helpers/editor.mjs';

test.describe('APP67K · PLTS engineering controller integration', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('surfaces a blocked source chain and opens the dedicated Controller workspace', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        await expect(root).toHaveAttribute('data-engineering-controller-status', 'blocked');
        await expect(page.locator('[data-editor-controller-status-label]')).toHaveText('BLOCKED');
        await expect(page.locator('[data-editor-controller-status-count]')).toHaveText('3');

        await page.locator('[data-editor-controller-status]').click();
        await expect(page.locator('[data-editor-inspector-tab="controller"]')).toHaveClass(/is-active/);
        await expect(page.locator('[data-editor-inspector-panel="controller"]')).toBeVisible();
        await expect(page.locator('[data-editor-controller-sources] article')).toHaveCount(6);
        await expect(page.locator('[data-editor-controller-conflicts]')).toContainText('K001 · Engineering source changed');
        expect(browserErrors).toEqual([]);
    });

    test('publishes controlled cable and protection schedules from the engineering payload', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-controller-status]').click();
        await page.locator('.nxsld-controller-schedule').first().locator('summary').click();
        await expect(page.locator('[data-editor-controller-cables] article')).toHaveCount(5);
        await expect(page.locator('[data-editor-controller-cables]')).toContainText('C-DC-01');
        await expect(page.locator('[data-editor-controller-cables]')).toContainText('PV1-F');
        await expect(page.locator('[data-editor-controller-cables]')).toContainText('C-AC-03');

        const payload = await page.evaluate(() => JSON.parse(document.querySelector('[data-sld-editor-payload]').textContent));
        const inverterFeeder = payload.graph.connections.find((connection) => connection.wire_id === 'C-AC-01');
        expect(inverterFeeder.cable_type).toBe('CU XLPE/PVC');
        expect(inverterFeeder.cable_size).toBe('6');
        expect(inverterFeeder.engineering_binding.authority).toBe('technical_calculation');
        expect(payload.graph.schema_version).toBe('nexorin-sld-v1.6.0');
        expect(browserErrors).toEqual([]);
    });

    test('locates the SLD object referenced by an engineering-controller finding', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-controller-status]').click();
        await page.locator('[data-editor-controller-conflicts] button').first().click();
        const snapshot = await e2eSnapshot(page);
        expect(snapshot.selection).toEqual([{ id: 'inverter', entity: 'node' }]);
        await expect(page.locator('[data-editor-inspector-tab="controller"]')).toHaveClass(/is-active/);
        expect(browserErrors).toEqual([]);
    });

    test('synchronizes current engineering sources while preserving the edited drafting layout', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        const resized = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.resizeObject('inverter', 220, 160));
        expect(resized).toMatchObject({ w: 220, h: 160 });
        let snapshot = await e2eSnapshot(page);
        expect(snapshot.dirty).toBe(true);

        await page.locator('[data-editor-controller-status]').click();
        await page.locator('[data-editor-controller-sync]').click();
        await expect(root).toHaveAttribute('data-engineering-controller-status', 'synchronized');
        await expect(page.locator('[data-editor-controller-status-label]')).toHaveText('SYNCHRONIZED');
        await expect(page.locator('[data-editor-controller-conflicts]')).toContainText('No controller conflicts');

        snapshot = await e2eSnapshot(page);
        expect(snapshot.editor.positions.inverter.w).toBe(220);
        expect(snapshot.editor.positions.inverter.h).toBe(160);
        const state = await serverState(page);
        expect(state.saveCount).toBe(1);
        expect(state.controllerSyncCount).toBe(1);
        expect(state.lastController.status).toBe('synchronized');
        expect(browserErrors).toEqual([]);
    });

    test('records acknowledgement reasons for non-blocking downstream review items', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-controller-status]').click();
        await page.locator('[data-editor-controller-reason]').fill('Reviewed with the design engineer before commercial regeneration.');
        await page.locator('[data-editor-controller-ack]').click();
        await expect(page.locator('[data-editor-controller-status-label]')).toHaveText('REVIEW');
        await expect(page.locator('[data-editor-controller-conflicts] .is-acknowledged')).toHaveCount(2);
        const state = await serverState(page);
        expect(state.controllerSyncCount).toBe(1);
        expect(state.lastController.acknowledgements).toHaveLength(2);
        expect(state.lastController.acknowledgements[0].reason).toContain('Reviewed with the design engineer');
        expect(browserErrors).toEqual([]);
    });
});
