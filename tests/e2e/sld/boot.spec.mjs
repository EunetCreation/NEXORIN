import { test, expect } from 'playwright/test';
import { e2eSnapshot, openEditor } from './helpers/editor.mjs';

test.describe('APP67G.3 · editor boot contract', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('boots the real Blade workspace and maxGraph document without browser errors', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);

        await expect(page).toHaveTitle(/SLD Drawing Editor/);
        await expect(root).toHaveAttribute('data-active-tool', 'select');
        await expect(page.locator('[data-editor-node-count]')).toHaveText('6');
        await expect(page.locator('[data-editor-wire-count]')).toHaveText('5');
        await expect(page.locator('[data-editor-line-count]')).toHaveText('0');
        await expect(page.locator('[data-nxsld-node-id]')).toHaveCount(6);
        await expect(page.locator('[data-editor-save-state]')).toHaveText('All changes saved');

        const snapshot = await e2eSnapshot(page);
        expect(snapshot.ready).toBe(true);
        expect(snapshot.editor.manual_nodes).toHaveLength(0);
        expect(snapshot.editor.manual_connections).toHaveLength(0);
        expect(snapshot.editor.manual_lines).toHaveLength(0);
        expect(snapshot.editor.manual_shapes).toHaveLength(0);
        expect(snapshot.editor.annotations).toHaveLength(0);
        expect(snapshot.editor.positions).toHaveProperty('pv_array');
        expect(browserErrors).toEqual([]);
    });

    test('exposes stable engineering ports for every fixture source node', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const requiredPorts = [
            ['pv_array', 'DC_OUT'],
            ['dc_protection', 'DC_IN'],
            ['dc_protection', 'DC_OUT'],
            ['inverter', 'DC_IN'],
            ['inverter', 'AC_OUT'],
            ['inverter', 'COM'],
            ['ac_protection', 'AC_IN'],
            ['meter', 'AC_IN'],
            ['meter', 'AC_OUT'],
            ['grid', 'AC_PCC'],
        ];

        const ports = await page.evaluate((required) => required.map(([nodeId, portId]) => {
            const point = window.__NEXORIN_SLD_E2E__?.portClientPoint(nodeId, portId) || null;
            return { nodeId, portId, point };
        }), requiredPorts);

        for (const { nodeId, portId, point } of ports) {
            expect(point, `${nodeId}.${portId} should resolve through the canonical APP67H registry`).not.toBeNull();
            expect(Number.isFinite(point?.x), `${nodeId}.${portId} x coordinate`).toBe(true);
            expect(Number.isFinite(point?.y), `${nodeId}.${portId} y coordinate`).toBe(true);
            expect(point?.node_id, `${nodeId}.${portId} node identity`).toBe(nodeId);
            expect(point?.port_id, `${nodeId}.${portId} port identity`).toBe(portId);
            expect(String(point?.network || '').length, `${nodeId}.${portId} network classification`).toBeGreaterThan(0);
        }

        const retiredDetailedAliases = await page.evaluate(() => ([
            window.__NEXORIN_SLD_E2E__?.portClientPoint('pv_array', 'DC_POS') || null,
            window.__NEXORIN_SLD_E2E__?.portClientPoint('inverter', 'DC_MPPT1_POS') || null,
        ]));
        expect(retiredDetailedAliases).toEqual([null, null]);
        expect(browserErrors).toEqual([]);
    });
});
