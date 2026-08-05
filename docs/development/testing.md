# Development and Testing

This guide describes the local WordPress environments, automated suites, live
sandbox boundary, and release checks used by Deploy & Test.

## Prerequisites

- Docker Desktop or another Docker-compatible runtime.
- Node.js 20 or newer and npm.
- PHP 7.4 or newer and Composer.
- Chromium for headed or headless Playwright runs.

Install the project dependencies:

```bash
composer install
npm ci
npx playwright install chromium
```

## Local WordPress environments

The environments use separate configuration files and ports so each purpose is
explicit.

| Purpose | Configuration | URL |
| --- | --- | --- |
| Manual development | `.wp-env.json` | `http://localhost:8888` |
| PHPUnit integration | `.wp-env.test.json` | `http://localhost:8892` |
| Playwright admin E2E | `.wp-env.e2e.json` | `http://localhost:8893` |
| Packaged ZIP smoke test | `.wp-env.package.json` | `http://localhost:8890` |

All configurations enable `WP_DEBUG` and `WP_DEBUG_LOG` and disable
`WP_DEBUG_DISPLAY`.

### Manual development

Start and stop the main development site:

```bash
npm run env:start -- --update
npm run env:stop
```

Use the ignored `.wp-env.override.json` and `local-secrets/` directory for local
GitHub App IDs and private keys. Never commit those files or copy credentials,
tokens, JWTs, authorization headers, or private-key content into test evidence.

## Static checks and dependency audits

Run the local dependency and PHP style checks:

```bash
npm audit
composer audit
composer run lint:php
```

## PHPUnit integration suite

The PHPUnit suite uses WordPress test bootstrapping and mocked HTTP responses.
It does not require a GitHub App and must not dispatch live workflows.

```bash
npm run env:test:start -- --update
npm run test:php
npm run env:test:stop
```

Coverage includes roles and permissions, nonce enforcement for privileged POST
and AJAX handlers, settings and request-boundary validation, actions and locks,
GitHub authentication and controlled API responses, credential-leakage checks,
escaped admin output, status handling, audit logs, summary artifacts, and
uninstall behavior.

## Playwright admin E2E suite

The Playwright suite runs seven isolated WordPress admin journeys for
Administrator, Editor, and Subscriber behavior. Test setup resets plugin state
and creates the required local users. A controlled polling journey verifies that
test completion restores the page with Test status selected. The security journey
injects a controlled stored-content payload and confirms that settings and audit
output escape it without executing JavaScript. The suite does not require GitHub
credentials.

Start the environment and run the suite:

```bash
npm run env:e2e:start -- --update
npm run test:e2e
npm run env:e2e:stop
```

Run with a visible browser:

```bash
npm run test:e2e:headed
```

Remove previous artifacts before a clean rerun:

```bash
rm -rf test-results playwright-report
npm run test:e2e
```

Open the HTML report after a failure:

```bash
npx playwright show-report
```

The generated `test-results/` and `playwright-report/` directories are ignored
by Git.

## Packaged-plugin smoke test

Build the distributable ZIP:

```bash
npm run build:zip
```

Unpack it for the isolated package environment:

```bash
mkdir -p dist/package
unzip -q dist/deploy-and-test.zip -d dist/package
```

Start WordPress, confirm that the packaged plugin is active and loaded, and
then stop the environment:

```bash
npx wp-env --config=.wp-env.package.json start --update
npx wp-env --config=.wp-env.package.json run cli wp plugin status deploy-and-test
npx wp-env --config=.wp-env.package.json run cli wp eval 'if ( ! defined( "DEPLOY_AND_TEST_PLUGIN_FILE" ) ) { exit( 1 ); } echo "Packaged plugin loaded successfully.\n";'
npx wp-env --config=.wp-env.package.json stop
```

## Live GitHub sandbox

The live layer uses only the dedicated QA organization, GitHub App, deploy
repository, and test repository. Use it to verify real authentication,
repository connections, workflow dispatch, polling, cross-action locking, and
summary-artifact downloads.

Live sandbox workflows must never deploy a real website or access production
repositories or secrets. Run this layer before a release and after changes to
GitHub authentication, dispatch, polling, or artifact handling.

## Release gates

The reusable GitHub Actions release-gate workflow runs:

- Composer audit, WordPress Coding Standards, and ZIP construction.
- PHPUnit on WordPress 6.0 with PHP 7.4 and 8.0.
- PHPUnit on latest WordPress with PHP 7.4, 8.0, 8.2, and 8.3.
- WordPress Plugin Check against the packaged plugin.
- Clean installation and activation of the packaged ZIP.
- The seven Playwright WordPress admin journeys, including workflow-completion restoration and the stored-content security boundary.

The release workflow depends on the complete gate workflow. A tag must not
publish a release when verification fails.

## Recommended pre-merge sequence

```bash
npm audit
composer audit
composer run lint:php
npm run env:test:start -- --update
npm run test:php
npm run env:test:stop
npm run env:e2e:start -- --update
npm run test:e2e
npm run env:e2e:stop
npm run build:zip
git diff --check
```

Run the live sandbox subset separately when the change touches the GitHub
integration contract.
