import { expect } from 'playwright/test';

export async function openEditor(page, path = '/sld-editor') {
    const browserErrors = [];
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') browserErrors.push(message.text());
    });

    const response = await page.goto(path, { waitUntil: 'networkidle' });
    if (!response) throw new Error('SLD fixture navigation returned no HTTP response.');

    const status = response.status();
    const contentType = response.headers()['content-type'] || '';
    if (status !== 200 || !contentType.includes('text/html')) {
        const body = (await response.text()).replace(/\s+/g, ' ').slice(0, 1600);
        throw new Error(`SLD fixture returned HTTP ${status} (${contentType || 'unknown content type'}). Body: ${body}`);
    }

    const root = page.locator('[data-sld-editor]');
    if ((await root.count()) === 0) {
        const body = (await page.locator('body').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 1600);
        throw new Error(`SLD fixture HTML did not contain [data-sld-editor]. Body: ${body}`);
    }

    await expect(root).toHaveAttribute('data-editor-ready', 'true', { timeout: 15_000 });
    await expect(page.locator('[data-editor-graph]')).toBeVisible();

    return { root, browserErrors };
}

export async function graphBox(page) {
    const box = await page.locator('[data-editor-graph]').boundingBox();
    if (!box) throw new Error('SLD graph container has no layout box.');
    return box;
}

export async function clickGraph(page, xRatio, yRatio, options = {}) {
    const box = await graphBox(page);
    await page.mouse.click(
        box.x + box.width * xRatio,
        box.y + box.height * yRatio,
        options,
    );
}

export async function dragGraph(page, from, to, options = {}) {
    const box = await graphBox(page);
    await page.mouse.move(box.x + box.width * from[0], box.y + box.height * from[1]);
    await page.mouse.down(options);
    await page.mouse.move(box.x + box.width * to[0], box.y + box.height * to[1], { steps: 10 });
    await page.mouse.up(options);
}

export async function e2eSnapshot(page) {
    return page.evaluate(() => window.__NEXORIN_SLD_E2E__?.snapshot());
}

export async function selectNode(page, id) {
    const selected = await page.evaluate((nodeId) => window.__NEXORIN_SLD_E2E__?.selectNode(nodeId), id);
    expect(selected).toBe(true);
}

export async function addSymbol(page, kind, xRatio = 0.52, yRatio = 0.55) {
    await page.locator(`[data-editor-add-kind="${kind}"]`).click();
    await clickGraph(page, xRatio, yRatio);
    await expect(page.locator('[data-sld-editor]')).toHaveAttribute('data-active-tool', 'select');
}


export async function clickPort(page, nodeId, portId) {
    const point = await page.evaluate(({ nodeId: targetNodeId, portId: targetPortId }) => (
        window.__NEXORIN_SLD_E2E__?.portClientPoint(targetNodeId, targetPortId) || null
    ), { nodeId, portId });
    if (!point || !Number.isFinite(point.x) || !Number.isFinite(point.y)) {
        throw new Error(`SLD port ${nodeId}.${portId} has no canonical client point.`);
    }
    const graph = await page.locator('[data-editor-graph]').boundingBox();
    if (!graph || point.x < graph.x || point.x > graph.x + graph.width || point.y < graph.y || point.y > graph.y + graph.height) {
        throw new Error(`SLD port ${nodeId}.${portId} resolved outside the graph viewport.`);
    }
    await page.mouse.move(point.x, point.y);
    await page.mouse.click(point.x, point.y);
}

export async function serverState(page) {
    return page.evaluate(async () => (await fetch('/__e2e/state')).json());
}
