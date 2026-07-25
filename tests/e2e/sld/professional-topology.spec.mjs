import { test, expect } from 'playwright/test';
import { e2eSnapshot, openEditor, selectNode } from './helpers/editor.mjs';

const professionalPath = '/sld-editor-professional';

async function payload(page) {
    const raw = await page.locator('script[data-sld-editor-payload]').textContent();
    return JSON.parse(raw || '{}');
}

test.describe('APP67L.4-FINAL · production drawing workflow on professional topology', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('publishes the corrected physical topology contract from the backend builder', async ({ page }) => {
        const { browserErrors } = await openEditor(page, professionalPath);
        const data = await payload(page);
        const graph = data.graph;
        const ids = graph.nodes.map((node) => node.id);

        expect(graph.schema_version).toBe('nexorin-sld-v1.9.0');
        expect(graph.meta.topology_profile).toBe('professional_physical');
        expect(graph.meta.topology_version).toBe('67L.2-R4');
        expect(graph.meta.physical_equipment_count).toBe(25);
        expect(graph.meta.physical_connection_count).toBe(45);
        expect(graph.meta.grounding_generated).toBe(true);
        expect(graph.meta.monitoring_enabled).toBe(true);
        expect(graph.meta.plant_control_enabled).toBe(true);
        expect(graph.meta.document_control.version).toBe('67L.4-FINAL');
        expect(graph.meta.document_control.project.number).toBe('NX-E2E-2026-001');
        expect(graph.meta.document_control.project.client).toBe('PT Production Client');
        expect(graph.meta.document_control.project.site_location).toBe('Jakarta, DKI Jakarta');
        expect(graph.meta.document_control.drawing.issue_code).toBe('DRAFT');
        expect(graph.meta.document_control.drawing.sheet_count).toBe(6);
        expect(graph.meta.document_control.drawing.register).toHaveLength(6);
        expect(graph.meta.document_control.drawing.register[0].drawing_number).toBe('NX-E2E-2026-001-E-SLD-001');
        expect(graph.meta.document_control.drawing.register[5].drawing_number).toBe('NX-E2E-2026-001-E-SLD-006');
        expect(graph.meta.document_control.release.not_for_construction).toBe(true);
        expect(ids).toEqual(expect.arrayContaining([
            'inverter_01', 'inverter_02', 'pcc_breaker_01', 'ct_01',
            'pcc_disconnect_01', 'meter_01', 'export_relay_01',
            'ac_spd_backup_01', 'ac_spd_01', 'pe_bus_01', 'ground_rod_01',
        ]));
        expect(ids).not.toContain('dc_bus_01');
        expect(ids).not.toContain('dc_fuse_01');
        expect(ids).not.toContain('if_dc_01');
        expect(browserErrors).toEqual([]);
    });

    test('boots all physical devices and corrected circuits in maxGraph', async ({ page }) => {
        const { browserErrors } = await openEditor(page, professionalPath);

        await expect(page.locator('[data-editor-node-count]')).toHaveText('25');
        await expect(page.locator('[data-editor-wire-count]')).toHaveText('45');
        await expect(page.locator('[data-nxsld-node-id]')).toHaveCount(25);
        await expect(page.locator('[data-nxsld-node-id="pcc_breaker_01"]')).toBeVisible();
        await expect(page.locator('[data-nxsld-node-id="pcc_disconnect_01"]')).toBeVisible();
        await expect(page.locator('[data-nxsld-node-id="export_relay_01"]')).toBeVisible();

        const snapshot = await e2eSnapshot(page);
        expect(snapshot.ready).toBe(true);
        expect(Object.keys(snapshot.editor.positions)).toHaveLength(25);
        expect(snapshot.editor.positions).toHaveProperty('pcc_breaker_01');
        expect(snapshot.editor.positions).toHaveProperty('pcc_disconnect_01');
        expect(snapshot.editor.positions).toHaveProperty('export_relay_01');
        expect(browserErrors).toEqual([]);
    });

    test('resolves canonical ports for PCC power, metering, hardwired trip, SPD, PE and control', async ({ page }) => {
        const { browserErrors } = await openEditor(page, professionalPath);
        const required = [
            ['pv_group_01', 'DC_OUT', 'dc_power'],
            ['dc_isolator_01', 'DC_OUT', 'dc_power'],
            ['dc_spd_01', 'DC_TAP', 'dc_power'],
            ['inverter_02', 'CTRL', 'control'],
            ['ac_combiner_01', 'AC_OUT', 'ac_power'],
            ['pcc_breaker_01', 'TRIP', 'control'],
            ['ct_01', 'S1', 'measurement'],
            ['ct_01', 'S1P', 'measurement'],
            ['pcc_disconnect_01', 'VREF_L', 'measurement'],
            ['export_relay_01', 'TRIP', 'control'],
            ['ac_spd_01', 'PE', 'protective_earth'],
            ['data_logger_01', 'ETH', 'communication'],
            ['plant_controller_01', 'CTRL', 'control'],
        ];

        const ports = await page.evaluate((items) => items.map(([nodeId, portId, network]) => ({
            nodeId,
            portId,
            network,
            point: window.__NEXORIN_SLD_E2E__?.portClientPoint(nodeId, portId) || null,
        })), required);

        for (const item of ports) {
            expect(item.point, `${item.nodeId}.${item.portId}`).not.toBeNull();
            expect(item.point.node_id).toBe(item.nodeId);
            expect(item.point.port_id).toBe(item.portId);
            expect(item.point.network).toBe(item.network);
            expect(Number.isFinite(item.point.x)).toBe(true);
            expect(Number.isFinite(item.point.y)).toBe(true);
        }
        expect(browserErrors).toEqual([]);
    });

    test('keeps the meter outside the primary feeder and exposes the independent failsafe trip', async ({ page }) => {
        const { browserErrors } = await openEditor(page, professionalPath);
        const data = await payload(page);
        const connections = data.graph.connections;

        expect(connections).toEqual(expect.arrayContaining([
            expect.objectContaining({ id: 'C-AC-MAIN-01', from: 'ac_combiner_01', to: 'pcc_breaker_01', network: 'ac_power' }),
            expect.objectContaining({ id: 'C-AC-CT-01', from: 'pcc_breaker_01', to: 'ct_01', network: 'ac_power' }),
            expect.objectContaining({ id: 'C-AC-DISC-01', from: 'ct_01', to: 'pcc_disconnect_01', network: 'ac_power' }),
            expect.objectContaining({ id: 'C-AC-PCC-01', from: 'pcc_disconnect_01', to: 'grid_01', network: 'ac_power' }),
            expect.objectContaining({ id: 'C-CTRL-RPR-TRIP-01', from: 'export_relay_01', to: 'pcc_breaker_01', network: 'control' }),
        ]));
        expect(connections.some((wire) => wire.network === 'ac_power' && (wire.from === 'meter_01' || wire.to === 'meter_01'))).toBe(false);
        expect(browserErrors).toEqual([]);
    });

    test('shows zero deterministic electrical findings', async ({ page }) => {
        const { browserErrors } = await openEditor(page, professionalPath);

        await expect(page.locator('[data-editor-check-status-label]')).toHaveText('PASS');
        await expect(page.locator('[data-editor-check-status-count]')).toHaveText('0');
        await page.locator('[data-editor-check-status]').click();
        await expect(page.locator('[data-editor-check-count="error"]')).toHaveText('0');
        await expect(page.locator('[data-editor-check-count="warning"]')).toHaveText('0');
        await expect(page.locator('[data-editor-check-count="total"]')).toHaveText('0');
        await expect(page.locator('[data-editor-check-list]')).toContainText('No electrical-rule findings');
        expect(browserErrors).toEqual([]);
    });

    test('publishes traceable cable and protection schedules through the Controller UI', async ({ page }) => {
        const { browserErrors } = await openEditor(page, professionalPath);
        await page.locator('[data-editor-controller-status]').click();

        await expect(page.locator('[data-editor-controller-cables] article')).toHaveCount(53);
        await expect(page.locator('[data-editor-controller-protection] article')).toHaveCount(13);
        await expect(page.locator('[data-editor-controller-cables]')).toContainText('C-DC-01-STR-01-A');
        await expect(page.locator('[data-editor-controller-cables]')).toContainText('INV-01:MPPT-01');
        await expect(page.locator('[data-editor-controller-cables]')).toContainText('Ethernet Cat6');
        await expect(page.locator('[data-editor-controller-protection]')).toContainText('PCC main circuit breaker');
        await expect(page.locator('[data-editor-controller-protection]')).toContainText('Reverse-power');

        const data = await payload(page);
        const inverter = data.graph.nodes.find((node) => node.id === 'inverter_02');
        expect(inverter.assigned_module_count).toBe(48);
        expect(inverter.string_assignments).toHaveLength(3);
        expect(inverter.ports.map((port) => port.id)).toEqual(['DC_IN', 'AC_OUT', 'PE', 'COM', 'CTRL']);
        expect(data.graph.engineering_controller.cable_schedule.some((row) => JSON.stringify(row).includes('IF-DC'))).toBe(false);
        expect(browserErrors).toEqual([]);
    });
});
