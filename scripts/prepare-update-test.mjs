import { execFile } from 'node:child_process';
import { copyFile, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const rootDir = dirname(dirname(fileURLToPath(import.meta.url)));
const pluginSlug = 'deploy-and-test';
const currentZip = join(rootDir, 'dist', `${pluginSlug}.zip`);
const fixtureDir = join(rootDir, 'dist', 'update-test');
const oldSourceDir = join(fixtureDir, 'old-source');
const oldPluginFile = join(oldSourceDir, pluginSlug, `${pluginSlug}.php`);
const sourcePluginFile = join(rootDir, pluginSlug, `${pluginSlug}.php`);
const smokeTestFile = join(rootDir, 'tests', 'package-update-smoke.php');

const source = await readFile(sourcePluginFile, 'utf8');
const versionMatch = source.match(/^\s*\* Version:\s*([^\s]+)\s*$/m);

if (!versionMatch) {
    throw new Error('Could not read the current plugin version.');
}

const currentVersion = versionMatch[1];

await rm(fixtureDir, { recursive: true, force: true });
await mkdir(oldSourceDir, { recursive: true });
await execFileAsync('unzip', ['-q', currentZip, '-d', oldSourceDir]);

const oldSource = (await readFile(oldPluginFile, 'utf8'))
    .replace(/^\s*\* Version:\s*[^\s]+\s*$/m, ' * Version: 0.0.1')
    .replace("define( 'DEPLOY_AND_TEST_VERSION', '" + currentVersion + "' );", "define( 'DEPLOY_AND_TEST_VERSION', '0.0.1' );");

if (!oldSource.includes(' * Version: 0.0.1') || !oldSource.includes("define( 'DEPLOY_AND_TEST_VERSION', '0.0.1' );")) {
    throw new Error('Could not prepare the synthetic older plugin version.');
}

await writeFile(oldPluginFile, oldSource);
await execFileAsync('zip', ['-q', '-r', join(fixtureDir, `${pluginSlug}-old.zip`), pluginSlug], { cwd: oldSourceDir });
await copyFile(currentZip, join(fixtureDir, `${pluginSlug}.zip`));
await copyFile(smokeTestFile, join(fixtureDir, 'package-update-smoke.php'));
await writeFile(join(fixtureDir, 'expected-version.txt'), `${currentVersion}\n`);

console.log(`Prepared packaged update test from 0.0.1 to ${currentVersion}.`);
