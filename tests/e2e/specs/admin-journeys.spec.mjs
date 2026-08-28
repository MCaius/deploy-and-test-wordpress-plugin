import { expect, test } from '@playwright/test';
import { loginAs, users } from '../helpers/auth.mjs';
import { resetPluginState } from '../helpers/wp-cli.mjs';

const pluginPage = '/wp-admin/admin.php?page=deploy-and-test';

test.beforeEach(() => {
    resetPluginState();
});

test('Administrator can navigate plugin tabs and status panels', async ({ page }) => {
    await loginAs(page, users.administrator);
    await page.goto(pluginPage);

    await expect(page.getByRole('heading', { name: 'Deploy & Test', level: 1 })).toBeVisible();
    await expect(page.getByTestId('tab-general')).toBeVisible();
    await expect(page.getByTestId('tab-connection')).toBeVisible();
    await expect(page.getByTestId('tab-audit-log')).toBeVisible();

    await page.getByTestId('status-tab-test').click();
    await expect(page.getByTestId('status-panel-test')).toBeVisible();
    await expect(page.getByTestId('status-tab-test')).toHaveAttribute('aria-selected', 'true');

    await page.getByTestId('tab-connection').click();
    await expect(page).toHaveURL(/tab=connection/);
    await expect(page.getByRole('heading', { name: 'GitHub connection' })).toBeVisible();

    await page.getByTestId('tab-audit-log').click();
    await expect(page).toHaveURL(/tab=audit-log/);
    await expect(page.getByRole('heading', { name: 'Audit log' })).toBeVisible();
});

test('Completed test workflow restores the page with Test status selected', async ({ page }) => {
    let testStatusRequests = 0;

    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
        const requestBody = route.request().postData() || '';

        if (!requestBody.includes('action=deploy_and_test_test_status')) {
            await route.continue();
            return;
        }

        testStatusRequests += 1;

        if (testStatusRequests >= 3) {
            await new Promise((resolve) => setTimeout(resolve, 1000));
        }

        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                success: true,
                data: {
                    html: '<section><p>Mock test status</p></section>',
                    hasActiveRun: testStatusRequests === 1,
                },
            }),
        });
    });

    await loginAs(page, users.administrator);
    await page.goto(`${pluginPage}&deploy_and_test_status_tab=test&deploy_and_test_workflow_started=1`);

    await expect(page.getByTestId('status-tab-test')).toHaveAttribute('aria-selected', 'true');
    await expect.poll(() => testStatusRequests, { timeout: 12000 }).toBeGreaterThanOrEqual(3);
    await expect(page).toHaveURL(/deploy_and_test_status_tab=test/);
    await expect(page.getByTestId('status-panel-test')).toBeVisible();
});

test('Administrator can save valid repository settings', async ({ page }) => {
    await loginAs(page, users.administrator);
    await page.goto(`${pluginPage}&tab=connection`);

    await page.locator('input[name="owner"]').fill('e2e-sandbox');
    await page.locator('input[name="repo"]').fill('deploy-target-sandbox');
    await page.locator('input[name="ref"]').fill('main');
    await page.getByRole('button', { name: 'Save settings' }).click();

    await expect(page.locator('.notice-success')).toContainText('Settings saved.');
    await expect(page.locator('input[name="owner"]')).toHaveValue('e2e-sandbox');
    await expect(page.locator('input[name="repo"]')).toHaveValue('deploy-target-sandbox');

    await page.reload();
    await expect(page.locator('input[name="owner"]')).toHaveValue('e2e-sandbox');
    await expect(page.locator('input[name="repo"]')).toHaveValue('deploy-target-sandbox');
});

test('Administrator can save and clear deploy environment URLs', async ({ page }) => {
    await loginAs(page, users.administrator);
    await page.goto(`${pluginPage}&tab=connection`);

    await page.getByTestId('preview-environment-url').fill('https://preview.example.test');
    await page.getByTestId('production-environment-url').fill('https://www.example.test');
    await page.getByTestId('save-connection-settings').click();

    await expect(page.getByTestId('admin-feedback-notice')).toContainText('Settings saved.');
    await expect(page.getByTestId('preview-environment-url')).toHaveValue('https://preview.example.test');
    await expect(page.getByTestId('production-environment-url')).toHaveValue('https://www.example.test');

    await page.getByTestId('preview-environment-url').fill('');
    await page.getByTestId('production-environment-url').fill('');
    await page.getByTestId('save-connection-settings').click();

    await expect(page.getByTestId('admin-feedback-notice')).toContainText('Settings saved.');
    await expect(page.getByTestId('preview-environment-url')).toHaveValue('');
    await expect(page.getByTestId('production-environment-url')).toHaveValue('');
});

test('Administrator can enable deploy workflows independently', async ({ page }) => {
    await loginAs(page, users.administrator);
    await page.goto(`${pluginPage}&tab=connection`);

    await page.getByTestId('production-workflow-file').fill('');
    await page.getByTestId('save-connection-settings').click();
    await page.getByTestId('tab-general').click();

    await expect(page.getByTestId('deploy-action-preview')).toBeVisible();
    await expect(page.getByTestId('deploy-action-production')).toHaveCount(0);

    await page.getByTestId('tab-connection').click();
    await page.getByTestId('preview-workflow-file').fill('');
    await page.getByTestId('production-workflow-file').fill('deploy-production.yml');
    await page.getByTestId('save-connection-settings').click();
    await page.getByTestId('tab-general').click();

    await expect(page.getByTestId('deploy-action-preview')).toHaveCount(0);
    await expect(page.getByTestId('deploy-action-production')).toBeVisible();

    await page.getByTestId('tab-connection').click();
    await page.getByTestId('production-workflow-file').fill('');
    await page.getByTestId('save-connection-settings').click();
    await page.getByTestId('tab-general').click();

    await expect(page.getByTestId('deploy-action-preview')).toHaveCount(0);
    await expect(page.getByTestId('deploy-action-production')).toHaveCount(0);
    await expect(page.getByTestId('deploy-workflows-empty-state')).toContainText('No deployment workflows are configured.');
});

test('Administrator sees validation feedback and invalid settings are not stored', async ({ page }) => {
    await loginAs(page, users.administrator);
    await page.goto(`${pluginPage}&tab=connection`);

    await page.locator('input[name="owner"]').fill('-invalid-owner');
    await page.locator('input[name="repo"]').fill('deploy-target-sandbox');
    await page.getByRole('button', { name: 'Save settings' }).click();

    await expect(page.getByTestId('admin-feedback-notice')).toContainText(
        'GitHub owner must contain only letters, numbers, and single hyphens, and cannot start or end with a hyphen.'
    );
    await expect(page.locator('input[name="owner"]')).toHaveValue('');
});

test('Editor can open approved actions but cannot access settings or audit tabs', async ({ page }) => {
    await loginAs(page, users.editor);
    await page.goto(pluginPage);

    await expect(page.getByRole('heading', { name: 'Deploy & Test', level: 1 })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Deploy Preview' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Deploy Production' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Connection', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Audit log', exact: true })).toHaveCount(0);

    await page.goto(`${pluginPage}&tab=connection`);
    await expect(page.getByRole('heading', { name: 'GitHub connection' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Deploy Preview' })).toBeVisible();
});

test('Subscriber cannot access the plugin page', async ({ page }) => {
    await loginAs(page, users.subscriber);
    await page.goto(pluginPage);

    await expect(page.getByText('Sorry, you are not allowed to access this page.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Deploy & Test', level: 1 })).toHaveCount(0);
});
