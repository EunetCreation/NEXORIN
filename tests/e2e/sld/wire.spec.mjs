import { test, expect } from 'playwright/test';
import { addSymbol, clickPort, e2eSnapshot, openEditor } from './helpers/editor.mjs';


test.describe('APP67G.3 · typed engineering Wire', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('creates a communication wire from exact compatible ports and edits cable metadata', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'monitoring', 0.60, 0.76);
        const before = await e2eSnapshot(page);
        const monitoringId = before.editor.manual_nodes.at(-1).id;

        await page.locator('[data-editor-tool="wire"]').click();
        await clickPort(page, 'inverter', 'COM');
        await clickPort(page, monitoringId, 'COM');

        await expect(page.locator('[data-editor-wire-count]')).toHaveText('6');
        await expect(page.locator('[data-editor-wire-panel]')).toBeVisible();
        await expect(page.locator('[data-editor-wire-network-badge]')).toHaveText('COM');
        await expect(page.locator('[data-editor-wire-field="network"]')).toHaveValue('communication');
        await expect(page.locator('[data-editor-wire-field="network"]')).toBeDisabled();

        await page.locator('[data-editor-wire-field="cable_type"]').fill('RS485');
        await page.locator('[data-editor-wire-field="cable_type"]').blur();
        await page.locator('[data-editor-wire-field="cable_tag"]').fill('COM-01');
        await page.locator('[data-editor-wire-field="cable_tag"]').blur();

        const snapshot = await e2eSnapshot(page);
        const wire = snapshot.editor.manual_connections.at(-1);
        expect(wire.from).toBe('inverter');
        expect(wire.to).toBe(monitoringId);
        expect(wire.from_port).toBe('COM');
        expect(wire.to_port).toBe('COM');
        expect(wire.network).toBe('communication');
        expect(wire.cable_type).toBe('RS485');
        expect(wire.detail).toBe('RS485');
        expect(wire.detail).not.toContain('mm²');
        expect(browserErrors).toEqual([]);
    });

    test('rejects an incompatible DC-to-AC destination without creating a dangling edge', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const before = await e2eSnapshot(page);
        await page.locator('[data-editor-tool="wire"]').click();
        await clickPort(page, 'pv_array', 'DC_OUT');
        const incompatibleTarget = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.portClientPoint('grid', 'AC_PCC') || null);
        expect(incompatibleTarget).not.toBeNull();
        await page.mouse.move(incompatibleTarget.x, incompatibleTarget.y);
        await expect(page.locator('[data-editor-snap-marker] .is-port.is-invalid')).toBeVisible();
        await expect(page.locator('[data-editor-snap-tooltip]')).toHaveClass(/is-port-invalid/);
        await expect(page.locator('[data-editor-snap-tooltip]')).toContainText('DC is not compatible with AC');
        await page.mouse.click(incompatibleTarget.x, incompatibleTarget.y);

        await expect(page.locator('[data-editor-wire-toast]')).toContainText('CONNECTION REJECTED');
        await expect(page.locator('[data-editor-wire-count]')).toHaveText('5');
        const after = await e2eSnapshot(page);
        expect(after.editor.manual_connections).toHaveLength(before.editor.manual_connections.length);
        expect(browserErrors).toEqual([]);
    });

    test('keeps wire terminals and network identity after equipment movement and route rebuild', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'monitoring', 0.60, 0.76);
        let snapshot = await e2eSnapshot(page);
        const monitoringId = snapshot.editor.manual_nodes.at(-1).id;

        await page.locator('[data-editor-tool="wire"]').click();
        await clickPort(page, 'inverter', 'COM');
        await clickPort(page, monitoringId, 'COM');
        snapshot = await e2eSnapshot(page);
        const wireId = snapshot.editor.manual_connections.at(-1).id;

        await page.evaluate((id) => window.__NEXORIN_SLD_E2E__.selectNode(id), monitoringId);
        await page.locator('[data-editor-field="x"]').fill('1080');
        await page.locator('[data-editor-field="x"]').blur();
        await page.evaluate((id) => window.__NEXORIN_SLD_E2E__.selectObject(id), wireId);
        await page.locator('[data-editor-action="wire-reset-route"]').click();

        const after = await e2eSnapshot(page);
        const wire = after.editor.manual_connections.find((candidate) => candidate.id === wireId);
        expect(wire.from).toBe('inverter');
        expect(wire.to).toBe(monitoringId);
        expect(wire.network).toBe('communication');
        expect(Array.isArray(after.editor.routes[wireId])).toBe(true);
        expect(browserErrors).toEqual([]);
    });
});
