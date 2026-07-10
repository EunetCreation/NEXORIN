import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

function help() {
    console.error('Usage: node scripts/render-pdf.mjs <input-html-path-or-url> <output-pdf-path> [options-json]');
}

const [, , input, output, optionsJson = '{}'] = process.argv;

if (input === '--help' || !input || !output) {
    help();
    process.exit(input === '--help' ? 0 : 2);
}

let chromium;
try {
    ({ chromium } = await import('playwright'));
} catch (error) {
    console.error('Playwright is not installed. Run: npm install && npx playwright install chromium');
    console.error(error?.message || String(error));
    process.exit(1);
}

let options = {};
try {
    options = JSON.parse(optionsJson || '{}');
} catch (error) {
    console.error(`Invalid options JSON: ${error.message}`);
    process.exit(2);
}

const inputUrl = /^(https?:|file:)/i.test(input)
    ? input
    : pathToFileURL(path.resolve(input)).href;

fs.mkdirSync(path.dirname(path.resolve(output)), { recursive: true });

if (!/^(https?:|file:)/i.test(input) && !fs.existsSync(input)) {
    console.error(`Input HTML file does not exist: ${input}`);
    process.exit(2);
}

let browser;
try {
    browser = await chromium.launch({
        headless: true,
        executablePath: process.env.NEXORIN_CHROMIUM_PATH || undefined,
        args: [
            '--allow-file-access-from-files',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--no-sandbox',
        ],
    });

    const page = await browser.newPage({
        viewport: {
            width: Number(options.viewportWidth || 1240),
            height: Number(options.viewportHeight || 1754),
        },
        deviceScaleFactor: 1,
    });

    page.on('pageerror', error => {
        console.error(`Page error: ${error?.message || String(error)}`);
    });

    page.on('requestfailed', request => {
        console.error(`Request failed: ${request.url()} - ${request.failure()?.errorText || 'unknown error'}`);
    });

    await page.emulateMedia({ media: 'print' });
    await page.goto(inputUrl, {
        waitUntil: 'load',
        timeout: Number(options.navigationTimeout || 60000),
    });

    await page.waitForLoadState('networkidle', {
        timeout: Math.min(Number(options.navigationTimeout || 60000), 15000),
    }).catch(() => null);

    await page.pdf({
        path: output,
        format: 'A4',
        printBackground: true,
        preferCSSPageSize: true,
        displayHeaderFooter: false,
        margin: {
            top: '0',
            right: '0',
            bottom: '0',
            left: '0',
        },
    });
} catch (error) {
    console.error(error?.stack || error?.message || String(error));
    process.exit(1);
} finally {
    if (browser) {
        await browser.close();
    }
}
