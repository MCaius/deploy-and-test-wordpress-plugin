export const users = {
    administrator: {
        username: 'admin',
        password: 'password',
    },
    editor: {
        username: 'qa-editor',
        password: 'qa-editor-password',
    },
    subscriber: {
        username: 'qa-subscriber',
        password: 'qa-subscriber-password',
    },
};

export async function loginAs(page, user) {
    await page.goto('/wp-login.php');
    await page.getByLabel('Username or Email Address').fill(user.username);
    await page.getByLabel('Password', { exact: true }).fill(user.password);
    await page.getByRole('button', { name: 'Log In' }).click();
    await page.waitForURL(/\/wp-admin\//);
}
