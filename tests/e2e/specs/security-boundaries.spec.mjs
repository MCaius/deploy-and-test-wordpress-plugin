import { Buffer } from 'node:buffer';
import { expect, test } from '@playwright/test';
import { loginAs, users } from '../helpers/auth.mjs';
import { resetPluginState, runWpEval } from '../helpers/wp-cli.mjs';

const pluginPage = '/wp-admin/admin.php?page=deploy-and-test';
const xssPayload = '<script>window.__deployAndTestXss = true;</script>';

test.beforeEach(() => {
    resetPluginState();
});

test('Stored untrusted content is escaped in settings and audit output', async ({ page }) => {
    const encodedPayload = Buffer.from(xssPayload).toString('base64');

    runWpEval(`
        $payload = base64_decode( '${encodedPayload}' );
        $settings = deploy_and_test_default_settings();
        $settings['preview_target'] = $payload;
        update_option( DEPLOY_AND_TEST_SETTINGS_OPTION, $settings, false );
        deploy_and_test_add_audit_log( 'security_output', 'failed', $payload );
    `);

    await loginAs(page, users.administrator);
    await page.goto(`${pluginPage}&tab=connection`);

    await expect(page.locator('input[name="preview_target"]')).toHaveValue(xssPayload);
    await expect(page.locator('script').filter({ hasText: '__deployAndTestXss' })).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => window.__deployAndTestXss)).toBeUndefined();

    await page.getByTestId('tab-audit-log').click();
    await expect(page.getByRole('cell', { name: xssPayload, exact: true })).toBeVisible();
    await expect(page.locator('script').filter({ hasText: '__deployAndTestXss' })).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => window.__deployAndTestXss)).toBeUndefined();
});
