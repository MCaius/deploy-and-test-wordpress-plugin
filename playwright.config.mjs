import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e/specs',
    outputDir: './test-results',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    globalSetup: './tests/e2e/global-setup.mjs',
    use: {
        baseURL: process.env.WP_BASE_URL || 'http://localhost:8893',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { browserName: 'chromium' },
        },
    ],
});
