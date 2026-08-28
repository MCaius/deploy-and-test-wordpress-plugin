import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = dirname(dirname(fileURLToPath(import.meta.url)));
const pluginSource = await readFile(join(rootDir, 'deploy-and-test', 'deploy-and-test.php'), 'utf8');
const readme = await readFile(join(rootDir, 'deploy-and-test', 'readme.txt'), 'utf8');
const changelog = await readFile(join(rootDir, 'CHANGELOG.md'), 'utf8');
const packageData = JSON.parse(await readFile(join(rootDir, 'package.json'), 'utf8'));
const packageLockData = JSON.parse(await readFile(join(rootDir, 'package-lock.json'), 'utf8'));

const headerVersion = pluginSource.match(/^\s*\* Version:\s*([^\s]+)\s*$/m)?.[1];
const constantVersion = pluginSource.match(/define\( 'DEPLOY_AND_TEST_VERSION', '([^']+)' \);/)?.[1];
const stableTag = readme.match(/^Stable tag:\s*([^\s]+)\s*$/m)?.[1];
const versions = new Map([
    ['plugin header', headerVersion],
    ['DEPLOY_AND_TEST_VERSION', constantVersion],
    ['readme stable tag', stableTag],
    ['package.json', packageData.version],
    ['package-lock.json', packageLockData.version],
    ['package-lock root package', packageLockData.packages?.['']?.version],
]);

for (const [source, version] of versions) {
    if (version !== headerVersion) {
        throw new Error(`${source} version ${version || '(missing)'} does not match plugin header ${headerVersion || '(missing)'}.`);
    }
}

if (!changelog.includes(`## ${headerVersion} -`)) {
    throw new Error(`CHANGELOG.md does not contain a dated ${headerVersion} release entry.`);
}

if (process.env.GITHUB_REF_TYPE === 'tag' && process.env.GITHUB_REF_NAME !== `v${headerVersion}`) {
    throw new Error(`Git tag ${process.env.GITHUB_REF_NAME} does not match plugin version v${headerVersion}.`);
}

console.log(`Release version ${headerVersion} is synchronized.`);
