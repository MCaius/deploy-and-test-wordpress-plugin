import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');

export function runWpCli(...args) {
    try {
        return execFileSync(
            'npx',
            ['wp-env', '--config=.wp-env.e2e.json', 'run', 'cli', 'wp', ...args],
            {
                cwd: projectRoot,
                encoding: 'utf8',
                stdio: ['ignore', 'pipe', 'pipe'],
            }
        );
    } catch (error) {
        const output = `${error.stdout || ''}${error.stderr || ''}`.trim();
        throw new Error(`WP-CLI E2E command failed: ${output}`, { cause: error });
    }
}

export function runWpEval(code) {
    return runWpCli('eval', code);
}

export function resetPluginState() {
    runWpEval(`
        delete_option( 'deploy_and_test_settings' );
        delete_option( 'deploy_and_test_audit_log' );
        delete_option( 'deploy_and_test_deploy_lock_preview' );
        delete_option( 'deploy_and_test_deploy_lock_production' );
        delete_option( 'deploy_and_test_deploy_lock_global' );
    `);
}
