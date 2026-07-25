import { test, expect } from 'playwright/test';
import { clickGraph, e2eSnapshot, openEditor, selectNode } from './helpers/editor.mjs';

async function dragHandle(page, name, dx, dy) {
    const point = await page.evaluate((handleName) => {
        const svg = document.querySelector('[data-editor-drafting-overlay]');
        const handle = document.querySelector(`[data-editor-resize-handle="${handleName}"]`);
        if (!svg || !handle) return null;
        const match = String(handle.getAttribute('transform') || '').match(/translate\(([-.0-9]+)[ ,]+([-.0-9]+)\)/);
        const rect = svg.getBoundingClientRect();
        return match ? { x: rect.left + Number(match[1]), y: rect.top + Number(match[2]) } : null;
    }, name);
    if (!point) throw new Error(`Precision resize handle ${name} has no client point.`);
    await page.mouse.move(point.x, point.y);
    await page.mouse.down();
    await page.mouse.move(point.x + dx, point.y + dy, { steps: 12 });
    await page.mouse.up();
    await page.waitForTimeout(100);
}


async function clientPoint(page, point) {
    const value = await page.evaluate((raw) => window.__NEXORIN_SLD_E2E__?.graphClientPoint(raw) || null, point);
    if (!value || !Number.isFinite(value.x) || !Number.isFinite(value.y)) throw new Error('Unable to resolve graph point to browser client coordinates.');
    return value;
}

async function moveToGraphPoint(page, point) {
    const client = await clientPoint(page, point);
    await page.mouse.move(client.x, client.y);
}

async function longestWireSegment(page) {
    return page.evaluate(() => {
        const wires = window.__NEXORIN_SLD_E2E__?.routeDiagnostics()?.wires || [];
        let best = null;
        wires.forEach((wire) => {
            (wire.points || []).slice(1).forEach((point, index) => {
                const a = wire.points[index];
                const b = point;
                const length = Math.hypot(b.x - a.x, b.y - a.y);
                if (!best || length > best.length) best = { a, b, length, wireId: wire.id };
            });
        });
        return best;
    });
}

test.describe('APP67J.2 · precision resize and visible engineering OSNAP', () => {
    test.beforeEach(async ({ request }) => {
        await request.post('/__e2e/reset');
    });

    test('resizes an IEC equipment envelope independently without distorting its glyph', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await selectNode(page, 'inverter');

        await expect(page.locator('[data-editor-resize-handle]')).toHaveCount(8);
        expect(await page.locator('[data-editor-resize-cell="inverter"]').textContent()).toContain('GLYPH PROPORTION LOCKED');
        await expect(page.locator('[data-editor-field="w"]')).toBeEnabled();
        await expect(page.locator('[data-editor-field="h"]')).toBeEnabled();

        const glyph = page.locator('[data-nxsld-node-id="inverter"] [data-nxsld-symbol-glyph]');
        const glyphBefore = await glyph.innerHTML();
        const before = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('inverter'));

        await dragHandle(page, 'e', 92, 0);
        const wider = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('inverter'));
        expect(wider.w).toBeGreaterThan(before.w);
        expect(wider.h).toBe(before.h);

        await dragHandle(page, 's', 0, 72);
        const taller = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.cellBounds('inverter'));
        expect(taller.w).toBe(wider.w);
        expect(taller.h).toBeGreaterThan(wider.h);

        const glyphAfter = await glyph.innerHTML();
        expect(glyphAfter).toBe(glyphBefore);
        expect(await page.locator('[data-editor-field="w"]').inputValue()).toBe(String(Math.round(taller.w)));
        expect(await page.locator('[data-editor-field="h"]').inputValue()).toBe(String(Math.round(taller.h)));
        expect(browserErrors).toEqual([]);
    });

    test('resizes drafting geometry on one axis with visible side handles', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        await page.locator('[data-editor-tool="rectangle"]').click();
        await clickGraph(page, 0.12, 0.58);
        await clickGraph(page, 0.25, 0.70);

        const snapshot = await e2eSnapshot(page);
        const rectangle = snapshot.editor.manual_shapes.at(-1);
        expect(rectangle?.shape_type).toBe('rectangle');
        await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.selectObject(id), rectangle.id);
        await expect(page.locator('[data-editor-resize-handle]')).toHaveCount(8);

        const before = await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.cellBounds(id), rectangle.id);
        await dragHandle(page, 'e', 84, 0);
        const wider = await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.cellBounds(id), rectangle.id);
        expect(wider.w).toBeGreaterThan(before.w);
        expect(wider.h).toBe(before.h);

        await dragHandle(page, 'n', 0, -64);
        const taller = await page.evaluate((id) => window.__NEXORIN_SLD_E2E__?.cellBounds(id), rectangle.id);
        expect(taller.w).toBe(wider.w);
        expect(taller.h).toBeGreaterThan(wider.h);
        expect(browserErrors).toEqual([]);
    });

    test('shows END, MID, and NEAR markers on an electrical wire', async ({ page }) => {
        const { browserErrors } = await openEditor(page);
        const segment = await longestWireSegment(page);
        expect(segment?.length).toBeGreaterThan(100);

        // Electrical wire ends terminate at canonical PORTs. A drafting line gives
        // the explicit END marker while the electrical wire provides MID and NEAR.
        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.34, 0.72);
        await clickGraph(page, 0.48, 0.72);
        await page.keyboard.press('Enter');
        const snapshot = await e2eSnapshot(page);
        const draftingLine = snapshot.editor.manual_lines.at(-1);
        await page.locator('[data-editor-tool="line"]').click();
        await moveToGraphPoint(page, { x: draftingLine.x1, y: draftingLine.y1 });
        await expect(page.locator('[data-editor-snap-marker] .is-endpoint.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('END');

        const midpoint = { x: (segment.a.x + segment.b.x) / 2, y: (segment.a.y + segment.b.y) / 2 };
        await moveToGraphPoint(page, midpoint);
        await expect(page.locator('[data-editor-snap-marker] .is-midpoint.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('MID');

        const t = 0.28;
        const dx = segment.b.x - segment.a.x;
        const dy = segment.b.y - segment.a.y;
        const norm = Math.max(1, Math.hypot(dx, dy));
        const nearestProbe = {
            x: segment.a.x + dx * t - dy / norm * 12,
            y: segment.a.y + dy * t + dx / norm * 12,
        };
        await moveToGraphPoint(page, nearestProbe);
        await expect(page.locator('[data-editor-snap-marker] .is-nearest.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('NEAR');
        expect(browserErrors).toEqual([]);
    });

    test('shows CENTER and INTERSECTION markers for drafting geometry', async ({ page }) => {
        const { browserErrors } = await openEditor(page);

        await page.locator('[data-editor-tool="circle"]').click();
        await clickGraph(page, 0.18, 0.78);
        await clickGraph(page, 0.24, 0.78);
        let snapshot = await e2eSnapshot(page);
        const circle = snapshot.editor.manual_shapes.at(-1);
        expect(circle?.shape_type).toBe('circle');

        await page.locator('[data-editor-tool="line"]').click();
        await moveToGraphPoint(page, { x: circle.x + circle.w / 2, y: circle.y + circle.h / 2 });
        await expect(page.locator('[data-editor-snap-marker] .is-center.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('CEN');

        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.43, 0.72);
        await clickGraph(page, 0.58, 0.86);
        await page.keyboard.press('Enter');
        await page.locator('[data-editor-tool="line"]').click();
        await clickGraph(page, 0.43, 0.86);
        await clickGraph(page, 0.58, 0.72);
        await page.keyboard.press('Enter');
        snapshot = await e2eSnapshot(page);
        const [lineA, lineB] = snapshot.editor.manual_lines.slice(-2);
        const denominator = (lineA.x1 - lineA.x2) * (lineB.y1 - lineB.y2) - (lineA.y1 - lineA.y2) * (lineB.x1 - lineB.x2);
        const detA = lineA.x1 * lineA.y2 - lineA.y1 * lineA.x2;
        const detB = lineB.x1 * lineB.y2 - lineB.y1 * lineB.x2;
        const intersection = {
            x: (detA * (lineB.x1 - lineB.x2) - (lineA.x1 - lineA.x2) * detB) / denominator,
            y: (detA * (lineB.y1 - lineB.y2) - (lineA.y1 - lineA.y2) * detB) / denominator,
        };
        await page.locator('[data-editor-tool="line"]').click();
        await moveToGraphPoint(page, intersection);
        await expect(page.locator('[data-editor-snap-marker] .is-intersection.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('INT');
        expect(browserErrors).toEqual([]);
    });

    test('shows the canonical PORT target for Wire and rejects geometric pseudo-connections', async ({ page }) => {
        const { root, browserErrors } = await openEditor(page);
        await page.locator('[data-editor-tool="wire"]').click();
        await expect(root).toHaveAttribute('data-active-tool', 'wire');

        const port = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.portClientPoint('inverter', 'AC_OUT') || null);
        expect(port).not.toBeNull();
        await page.mouse.move(port.x, port.y);
        await expect(page.locator('[data-editor-snap-marker] .is-port.is-active')).toBeVisible();
        await expect(page.locator('[data-editor-snap-marker]')).toContainText('PORT');
        await expect(page.locator('[data-editor-snap-tooltip]')).toContainText('PORT · AC_OUT');

        await page.mouse.click(port.x, port.y);
        await expect(root).toHaveAttribute('data-wire-phase', 'target');
        const targetPort = await page.evaluate(() => window.__NEXORIN_SLD_E2E__?.portClientPoint('ac_protection', 'AC_IN') || null);
        expect(targetPort).not.toBeNull();
        await page.mouse.move(targetPort.x + 60, targetPort.y + 60);
        await page.mouse.move(targetPort.x, targetPort.y);
        await expect(page.locator('[data-editor-snap-tooltip]')).toContainText('PORT · AC_IN');

        const segment = await longestWireSegment(page);
        const midpoint = { x: (segment.a.x + segment.b.x) / 2, y: (segment.a.y + segment.b.y) / 2 };
        const wireSnap = await page.evaluate((point) => window.__NEXORIN_SLD_E2E__?.snapDiagnostics(point, 'wire') || null, midpoint);
        expect(wireSnap).toBeNull();
        expect(browserErrors).toEqual([]);
    });
});
