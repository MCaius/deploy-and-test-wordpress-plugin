# Deploy & Test

Deploy & Test is a WordPress admin plugin for developers and small teams that deploy sites with GitHub Actions. It lets trusted WordPress users trigger configured deploy and test workflows without giving them GitHub access or personal access tokens.

This is a public portfolio/reference project. It is not currently managed as an open contribution project, but forks are welcome under the license.

## Use Case

- Give editors or site managers a controlled deploy button in WordPress.
- Trigger preview and production GitHub Actions workflows from the admin.
- Run configured test workflows from a separate testing repository.
- Keep GitHub credentials server-side through a GitHub App.
- Show recent deploy/test status and a small audit trail in WordPress.

## Features

- WordPress admin page with Deploy, Tests, Connection, and Audit log tabs.
- GitHub App authentication with short-lived installation tokens.
- Configurable repository owner, deploy repository, test repository, refs, workflow filenames, and target labels.
- Preview and production deploy actions.
- Configurable test action buttons and test environments.
- Recent workflow run status cards with polling while actions are active.
- Test summary artifact display for compact JSON reports.
- Audit log stored in WordPress options and limited to the latest 100 entries.
- Optional uninstall cleanup for settings, audit logs, locks, and cached test summaries.

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- A GitHub App installed on the repositories used by the deploy/test flow.
- GitHub Actions workflows that support `workflow_dispatch`.
- `ZipArchive` on the WordPress server if you want WordPress to read test summary artifacts.

## Install

1. Upload the `deploy-and-test` folder to `wp-content/plugins/`.
2. Activate `Deploy & Test` in WordPress.
3. Create and install a GitHub App with `Actions: Read and write`.
4. Add the GitHub App constants to `wp-config.php`.
5. Configure repository and workflow settings in `Deploy & Test -> Connection`.

## Build Upload Zip

Run:

```bash
npm run build:zip
```

This creates:

```text
dist/deploy-and-test.zip
```

Upload that zip manually in WordPress:

```text
Plugins -> Add New Plugin -> Upload Plugin
```

The zip contains only the `deploy-and-test/` plugin folder and runtime plugin files. Repository docs, scripts, generated manifests, `vendor/`, and local config are not included.

## Development Checks

Install the PHP tooling:

```bash
composer install
```

Run WordPress Coding Standards checks:

```bash
composer lint:php
```

Build the upload zip:

```bash
npm run build:zip
```

The CI workflow runs the same lint/build steps. PHPCS issues may still need cleanup before the public workflow is expected to pass.

## wp-config.php Constants

Recommended private key file path setup:

```php
define('DEPLOY_AND_TEST_GITHUB_APP_ID', 'your-app-id');
define('DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID', 'your-installation-id');
define('DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH', '/secure/path/github-app-private-key.pem');
```

Fallback direct private key setup:

```php
define('DEPLOY_AND_TEST_GITHUB_APP_ID', 'your-app-id');
define('DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID', 'your-installation-id');
define('DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY', "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----");
```

Private key file permissions:

```text
Recommended:
Folder: 700
File:   600

Fallback:
Folder: 750
File:   640
```

## GitHub App Permissions

Use repository permissions:

```text
Metadata: Read-only
Actions: Read and write
```

Leave OAuth, device flow, setup URL, and webhooks disabled unless you intentionally extend the plugin later.

## Workflow Expectations

The deploy repository should include manually dispatched workflow files, for example:

```text
.github/workflows/deploy-preview.yml
.github/workflows/deploy-production.yml
```

The testing repository can expose separate manually dispatched workflows, or one workflow with inputs used by the configured test buttons.

## Security Model

- The plugin does not push code.
- The plugin triggers GitHub Actions workflow dispatches on configured refs.
- GitHub App private keys are read from `wp-config.php` constants, not stored in the database.
- WordPress generates short-lived GitHub installation tokens server-side when actions run.
- Admin POST/AJAX handlers use capability checks and nonces.
- Audit logs are stored in `wp_options` under `deploy_and_test_audit_log`.

## Limitations

- This plugin assumes a GitHub Actions based deploy process already exists.
- It is aimed at developer-managed WordPress sites, not general-purpose WordPress users.
- It does not create GitHub workflow files for you.
- It does not manage GitHub App installation or repository permissions automatically.
- It is not currently maintained as an open contribution project.

## License

GPL-2.0-or-later. See `LICENSE`.
