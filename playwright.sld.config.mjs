import { defineConfig, devices } from 'playwright/test';

const port = Number(process.env.NEXORIN_SLD_E2E_PORT || 4177);
const baseURL = process.env.NEXORIN_SLD_E2E_BASE_URL || `http://127.0.0.1:${port}`;
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE
    || process.env.NEXORIN_CHROMIUM_PATH
    || undefined;
const launchOptions = executablePath ? {
    executablePath,
    args: ['--disable-dev-shm-usage', '--disable-gpu', '--no-sandbox'],
} : undefined;

export default defineConfig({
    testDir: './tests/e2e/sld',
    testMatch: '**/*.spec.mjs',
    outputDir: 'test-results/sld',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 45_000,
    expect: { timeout: 8_000 },
    reporter: [
        ['list'],
        ['html', { outputFolder: 'test-results/sld-report', open: 'never' }],
        ['json', { outputFile: 'test-results/sld-results.json' }],
    ],
    use: {
        ...devices['Desktop Chrome'],
        baseURL,
        viewport: { width: 1920, height: 1080 },
        deviceScaleFactor: 1,
        colorScheme: 'dark',
        locale: 'en-US',
        timezoneId: 'Asia/Jakarta',
        actionTimeout: 8_000,
        navigationTimeout: 15_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: process.env.NEXORIN_SLD_E2E_VIDEO === '1' ? 'retain-on-failure' : 'off',
        launchOptions,
    },
    webServer: process.env.NEXORIN_SLD_E2E_BASE_URL ? undefined : {
        command: 'node scripts/sld-e2e-server.mjs',
        url: `${baseURL}/__e2e/health`,
        timeout: 30_000,
        reuseExistingServer: !process.env.CI,
        env: {
            NEXORIN_SLD_E2E_PORT: String(port),
        },
    },
});
