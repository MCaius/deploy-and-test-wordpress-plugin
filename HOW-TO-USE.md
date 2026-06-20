# How to Use Deploy & Test

This guide explains how to connect Deploy & Test to GitHub Actions using a GitHub App, configure deploy and test workflows, and expose automated test results inside WordPress.

## 1. Create a GitHub App

Create the GitHub App from the account or organization that owns the repositories used by the deploy flow.

Recommended settings:

- Homepage URL: the WordPress site URL.
- Callback URL: empty.
- Request user authorization during installation: disabled.
- Device Flow: disabled.
- Setup URL: empty.
- Webhook Active: disabled for the current polling-based status flow.
- Repository permissions:
  - Metadata: Read-only.
  - Actions: Read and write.

After creating the app:

1. Copy the App ID.
2. Generate and download a private key.
3. Install the app on only the repositories connected to this deploy/test flow.
4. Copy the numeric installation ID from the installation URL.

Example installation URL:

```text
https://github.com/settings/installations/12345678
```

In this example, `12345678` is the installation ID.

## 2. Add GitHub App Constants to wp-config.php

Add the constants before:

```php
/* That's all, stop editing! */
```

Recommended private key file path setup:

```php
/* GitHub deploy & test plugin */
define('DEPLOY_AND_TEST_GITHUB_APP_ID', 'your-app-id');
define('DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID', 'your-installation-id');
define('DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH', '/secure/path/github-app-private-key.pem');
```

Recommended permissions:

```text
Folder: 700
File:   600
```

Fallback permissions if WordPress cannot read the key:

```text
Folder: 750
File:   640
```

Fallback direct private key setup:

```php
/* GitHub deploy & test plugin */
define('DEPLOY_AND_TEST_GITHUB_APP_ID', 'your-app-id');
define('DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID', 'your-installation-id');
define('DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY', "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----");
```

The private key is a production secret. Do not commit it, expose it in JavaScript, or store it in a public web folder.

## 3. Configure the WordPress Connection Tab

In `Deploy & Test -> Connection`, configure:

```text
Owner or organization: example-org
Deploy repository: example-website
Deploy source ref: main
Preview workflow file: deploy-preview.yml
Production workflow file: deploy-production.yml
Preview target label: preview.example.com
Production target label: example.com
Testing repository: example-tests
Testing source ref: main
```

Repository fields should contain only the repository name, not `owner/repo`.

Only WordPress Administrators can change connection settings, workflow filenames, test action definitions, cleanup settings, and audit-log access. WordPress Editors can trigger the configured deploy and test actions from the General tab, but they cannot change the plugin configuration. This permission split is intentional for teams that want trusted, non-technical WordPress users to run approved workflows without granting administrator or GitHub access.

## 4. Configure Test Environments

Test environments appear next to the Tests panel in the General tab. The selected value is sent to GitHub Actions using the configured input name.

Example:

```text
GitHub Actions input name: target_env

Label name: Preview
Env variable: preview

Label name: Production
Env variable: prod
```

## 5. Configure Test Action Buttons

Each enabled test action row becomes one button in WordPress.

Example:

```text
Label: Smoke tests
Workflow file: tests.yml
Input name: suite
Input value: smoke
Order: 10
Enabled: yes
```

This dispatches `tests.yml` with:

```json
{
  "suite": "smoke",
  "target_env": "preview"
}
```

The `target_env` value comes from the selected test environment.

## 6. Deploy Workflow Example

Deploy workflows must support `workflow_dispatch`. Deploy & Test sends the configured source ref when it dispatches the workflow.

```yaml
name: Deploy Preview

on:
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Install dependencies
        run: npm ci

      - name: Build
        run: npm run build

      - name: Deploy preview
        run: npm run deploy:preview
```

For production, use a separate workflow file and a clear workflow name:

```yaml
name: Deploy Production

on:
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Install dependencies
        run: npm ci

      - name: Build
        run: npm run build

      - name: Deploy production
        run: npm run deploy:production
```

Recommended plugin settings:

```text
Preview workflow file: deploy-preview.yml
Production workflow file: deploy-production.yml
```

Names like `Deploy Preview` and `Deploy Production` help the status cards classify recent runs correctly.

## 7. Test Workflow Example

Use a stable `run-name` pattern so Deploy & Test can match a run to the expected summary artifact.

```yaml
name: Tests
run-name: Run ${{ inputs.suite }} tests on ${{ inputs.target_env }}

on:
  workflow_dispatch:
    inputs:
      suite:
        type: choice
        required: true
        options: [all, api, ui, seo, performance]
      target_env:
        type: choice
        required: true
        options: [preview, prod]
      browser:
        type: choice
        required: true
        options: [chromium, firefox, webkit]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Install dependencies
        run: npm ci

      - name: Run selected tests
        run: npm run test -- --suite="${{ inputs.suite }}" --target="${{ inputs.target_env }}" --browser="${{ inputs.browser }}"

      - name: Write Deploy & Test summary
        if: always()
        run: |
          mkdir -p test-results
          node scripts/write-deploy-summary.js \
            --suite="${{ inputs.suite }}" \
            --target-env="${{ inputs.target_env }}" \
            --browser="${{ inputs.browser }}" \
            --output="test-results/deploy-update-summary.json"

      - name: Upload Deploy & Test summary
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: deploy-update-summary-${{ inputs.suite }}-${{ inputs.target_env }}
          path: test-results/deploy-update-summary.json
```

## 8. Test Summary Artifact Format

Every test run should upload a small JSON summary artifact with this predictable name:

```text
deploy-update-summary-${{ inputs.suite }}-${{ inputs.target_env }}
```

The recommended file path inside the artifact is:

```text
test-results/deploy-update-summary.json
```

Deploy & Test looks for a file named `deploy-update-summary.json` inside the artifact archive, so the parent folder can be different if your test framework needs another layout.

Example JSON:

```json
{
  "suite": "api",
  "target_env": "preview",
  "browser": "chromium",
  "status": "passed",
  "stats": {
    "total": 7,
    "passed": 7,
    "failed": 0,
    "skipped": 0,
    "timedOut": 0,
    "durationMs": 4100
  },
  "tests": [
    {
      "status": "passed",
      "project": "api",
      "file": "api/smoke/example.spec.ts",
      "line": 5,
      "title": "example public API contract",
      "durationMs": 300,
      "error": ""
    }
  ],
  "generatedAt": "2026-06-18T07:00:00.000Z"
}
```

Upload the summary with `if: always()` so WordPress can show results even when tests fail.

## 9. Audit Log and Uninstall Cleanup

The audit log is stored in the WordPress database in `wp_options` under:

```text
deploy_and_test_audit_log
```

Each record stores timestamp, user, action, status, and details. The plugin keeps the latest 100 entries.

If `Delete plugin data on uninstall` is enabled, uninstalling the plugin removes stored settings, audit logs, temporary locks, and cached test summaries.
