import { test, expect } from 'playwright/test';
import { addSymbol, e2eSnapshot, openEditor } from './helpers/editor.mjs';

test.describe('APP67L.1 · controlled PLTS symbol and port registry', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('publishes one normalized palette with traceable IEC status and dimensions', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const cards = page.locator('[data-editor-symbol-name]');
        await expect(cards).toHaveCount(60);
        await expect(cards.first()).toHaveAttribute('data-editor-symbol-size', '144 × 120');
        const allowedStatusLabels = new Set([
            'IEC reference form',
            'IEC simplified form',
            'Reference drawing form',
            'NEXORIN engineering assembly',
        ]);
        const publishedStatusLabels = await cards.evaluateAll((items) =>
            items.map((item) => item.dataset.editorSymbolStatus || ''),
        );
        expect(publishedStatusLabels.every((status) => allowedStatusLabels.has(status))).toBe(true);
        await expect(cards.first()).toHaveAttribute('data-editor-symbol-port-count', /[1-9][0-9]*/);
        expect(await cards.evaluateAll((items) => new Set(items.map((item) => item.dataset.editorSymbolSize)).size)).toBe(1);
        expect(browserErrors).toEqual([]);
    });

    test('places CT primary and measurement terminals on explicit registry anchors', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'ct', 0.55, 0.68);
        const snapshot = await e2eSnapshot(page);
        const ct = snapshot.editor.manual_nodes.at(-1);
        expect(ct.kind).toBe('ct');
        expect(ct.ports.map((port) => port.id)).toEqual(['P1', 'P2', 'S1', 'S2']);
        expect(ct.ports.every((port) => Number.isFinite(port.anchor?.x) && Number.isFinite(port.anchor?.y))).toBe(true);

        const node = page.locator(`[data-nxsld-node-id="${ct.id}"]`);
        await expect(node.locator('[data-nxsld-port-id]')).toHaveCount(4);
        await expect(node.locator('[data-nxsld-port-id="S1"]')).toHaveAttribute('data-nxsld-port-network', 'measurement');
        await expect(node.locator('[data-nxsld-port-id="P1"]')).toHaveAttribute('data-nxsld-port-side', 'left');
        expect(browserErrors).toEqual([]);
    });

    test('enforces transform policy and exposes registry facts in Inspector', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'ground', 0.64, 0.72);
        let snapshot = await e2eSnapshot(page);
        const ground = snapshot.editor.manual_nodes.at(-1);
        await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.selectObjects([id]), ground.id);
        await page.locator('[data-editor-ribbon-tab="manage"]').click();
        await page.locator('[data-editor-action="rotate-right"]:visible').first().click();
        await page.locator('[data-editor-action="mirror-horizontal"]:visible').first().click();
        snapshot = await e2eSnapshot(page);
        const transformed = snapshot.editor.manual_nodes.find((node) => node.id === ground.id);
        expect(transformed.rotation).toBe(0);
        expect(transformed.flip_h).toBe(false);
        await expect(page.locator('[data-editor-inspector-standard]')).toContainText('IEC reference form');
        await expect(page.locator('[data-editor-inspector-ports]')).toContainText('1');
        expect(browserErrors).toEqual([]);
    });

    test('finds individual DC and AC isolators without hiding their full engineering names', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-symbol-search]').fill('isolator');

        const dcIsolator = page.locator('[data-editor-add-kind="dc_isolator"]');
        const acIsolator = page.locator('[data-editor-add-kind="ac_isolator"]');
        await expect(dcIsolator).toBeVisible();
        await expect(acIsolator).toBeVisible();
        await expect(dcIsolator.locator('strong')).toHaveText('DC isolator');
        await expect(acIsolator.locator('strong')).toHaveText('AC isolator');
        await expect(dcIsolator).toHaveAttribute('data-editor-symbol-scope', 'INDIVIDUAL');
        await expect(acIsolator).toHaveAttribute('data-editor-symbol-scope', 'INDIVIDUAL');
        expect(browserErrors).toEqual([]);
    });

    test('previews the individual DC SPD with reference scope and engineered ports', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const card = page.locator('[data-editor-add-kind="dc_spd"]');
        await card.hover();

        const tooltip = page.locator('[data-editor-symbol-tooltip]');
        await expect(tooltip).toBeVisible();
        await expect(tooltip).toContainText('DC SPD');
        await expect(tooltip).toContainText('INDIVIDUAL');
        await expect(tooltip).toContainText('2 engineered port(s)');
        await expect(tooltip.locator('.nxsld-symbol-tooltip-preview svg')).toBeVisible();

        await addSymbol(page, 'dc_spd', 0.55, 0.58);
        const snapshot = await e2eSnapshot(page);
        const spd = snapshot.editor.manual_nodes.at(-1);
        expect(spd.kind).toBe('dc_spd');
        expect(spd.ports.map((port) => port.id)).toEqual(['DC_TAP', 'PE']);
        expect(spd.ports.map((port) => port.network)).toEqual(['dc_power', 'protective_earth']);
        expect(browserErrors).toEqual([]);
    });

    test('adds a PE bus with multi-connection protective-earth terminals', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await addSymbol(page, 'pe_bus', 0.57, 0.68);
        const snapshot = await e2eSnapshot(page);
        const bus = snapshot.editor.manual_nodes.at(-1);
        expect(bus.kind).toBe('pe_bus');
        expect(bus.ports.map((port) => port.id)).toEqual(['PE_IN', 'PE_OUT']);
        expect(bus.ports.every((port) => port.network === 'protective_earth')).toBe(true);
        expect(Math.max(...bus.ports.map((port) => Number(port.max_connections || 0)))).toBeGreaterThanOrEqual(8);
        expect(browserErrors).toEqual([]);
    });

    test('separates project control blocks from electrical protection assemblies', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const logger = page.locator('[data-editor-add-kind="data_logger"]');
        const controller = page.locator('[data-editor-add-kind="plant_controller"]');
        const dcAssembly = page.locator('[data-editor-add-kind="dc_protection"]');

        await expect(logger).toHaveAttribute('data-editor-symbol-scope', 'INDIVIDUAL');
        await expect(controller).toHaveAttribute('data-editor-symbol-scope', 'INDIVIDUAL');
        await expect(dcAssembly).toHaveAttribute('data-editor-symbol-scope', 'ASSEMBLY');
        await expect(logger.locator('strong')).toHaveText('Data logger');
        await expect(controller.locator('strong')).toHaveText('Plant controller');
        expect(browserErrors).toEqual([]);
    });

});
