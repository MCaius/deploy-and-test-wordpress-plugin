# Deploy & Test QA Strategy

## Purpose

This strategy defines how Deploy & Test is verified before release. It separates
repeatable local checks from mocked GitHub API tests and a small set of live
GitHub sandbox checks.

The goals are to:

- Detect permission, validation, workflow, audit, locking, and cleanup defects.
- Keep most automated tests fast, deterministic, and safe.
- Verify the real GitHub App contract without targeting production repositories.
- Preserve baseline failures as evidence until they are fixed and retested.

## Test layers

### 1. Local WordPress

The committed `.wp-env.json` is the canonical local environment. It starts a
clean WordPress installation and maps `./deploy-and-test` as the active plugin.

Use this layer for:

- Plugin installation and activation.
- Administrator, Editor, and Subscriber access checks.
- Settings validation and admin-interface behavior.
- Audit-log and uninstall checks.
- Manual reproduction of defects.
- PHPUnit integration tests in the dedicated `.wp-env.test.json` environment.
- Playwright admin journeys in the dedicated `.wp-env.e2e.json` environment.

Local GitHub App values belong only in the ignored `.wp-env.override.json`.
Private keys must remain in the ignored `local-secrets/` directory and must
never appear in reports, screenshots, logs, or commits.

### 2. Mocked GitHub API

Most automated GitHub-related tests will intercept WordPress HTTP requests and
return controlled responses without contacting GitHub.

This layer should cover:

- Workflow dispatch request method, endpoint, ref, and inputs.
- Installation-token and API error handling.
- HTTP 401, 403, 404, 422, and 500 responses.
- Network timeouts and malformed response bodies.
- Run-status selection and concurrent-action blocking.
- Artifact selection, size limits, missing artifacts, and malformed summaries.
- Stable GitHub release parsing, allow-listed package URLs, cached failures, and native update metadata.

Mocks are the default for automated tests because they are repeatable, do not
consume GitHub rate limits, and cannot trigger real workflows accidentally.

The PHPUnit integration suite covers permissions, settings validation, action
locking and dispatch, GitHub authentication and controlled API responses,
status matching, feature modes, disabled-action rejection, audit logs, summary artifacts, uninstall behavior, privileged
handler authorization and nonce enforcement, output escaping, malicious
request boundaries, and credential leakage.

### 3. WordPress admin E2E

Playwright runs ten browser journeys against the isolated E2E WordPress
environment. These journeys cover:

- Administrator navigation across General, Connection, Settings, Audit log, and status panels.
- Deploy-only and Tests-only behavior, preserved Connection values, cleanup defaults, and cross-session settings persistence.
- Automatic server-rendered action restoration with Test status preserved after test workflow completion.
- Successful repository-setting persistence.
- Validation feedback and rejection of malformed settings.
- Editor access to approved actions without configuration or audit access.
- Subscriber denial at the plugin page.
- Stored untrusted content escaped in settings and audit output without JavaScript execution.

The browser suite uses stable plugin-owned `data-testid` attributes where a
role or label could be ambiguous. It does not require GitHub App credentials
and does not contact the live sandbox.

### 4. Security hardening

Security checks span PHPUnit and Playwright and cover:

- Capability enforcement for every privileged POST and AJAX handler.
- Missing or invalid nonce rejection before protected operations run.
- Rejection of URL, traversal, reflog, and workflow-path configuration payloads.
- Escaping of stored settings, audit details, and test-summary content.
- Exclusion of private-key, JWT, installation-token, and authorization-header material from user-visible errors, audit entries, and admin HTML.
- Browser confirmation that a controlled stored-content payload remains inert.

These tests use generated or synthetic credentials and local payloads. They
must never contain a real GitHub App private key or installation token.

### 5. Live GitHub sandbox

A dedicated GitHub organization, private GitHub App, and harmless sandbox
repositories will provide a limited live-contract layer.

Use this layer for:

- Confirming GitHub App authentication and installation permissions.
- Testing repository connections.
- Dispatching preview, production, passing-test, and failing-test workflows.
- Observing polling and cross-action locking against real workflow states.
- Downloading and rendering a real test-summary artifact.

The sandbox workflows must not deploy a real site or access production secrets.
Run the live journey before releases and whenever the GitHub integration changes.

### 6. Release gates

The reusable release-gate workflow verifies:

- Composer audit, WordPress Coding Standards, and ZIP construction.
- WordPress 6.0 on PHP 7.4 and 8.0.
- Latest WordPress on PHP 7.4, 8.0, 8.2, and 8.3.
- WordPress Plugin Check against the packaged plugin.
- Installation and activation of the packaged ZIP in a clean WordPress environment.
- Native update from a synthetic older package, including activation and settings preservation.
- The ten Playwright WordPress admin journeys, including feature-mode persistence, workflow-completion restoration, and the stored-content security boundary.

The release workflow must complete these gates before it can build and publish
a GitHub release.

## Supported roles

| Role | Expected access |
| --- | --- |
| Administrator | View the plugin, configure connections, feature modes, and cleanup, test connections, and run enabled actions. |
| Editor | View the plugin and run enabled deploy/test actions, but not change Connection, Settings, or cleanup preferences. |
| Subscriber | No plugin page or action access. |

## Result states

Use one of these states in QA reports:

- **Pass**: Actual behavior matches the expected result.
- **Fail**: Actual behavior differs from the expected result.
- **Blocked**: A required dependency or environment is unavailable.
- **Not run**: The scenario has not been attempted.

For every failure, record:

- Scenario ID and environment.
- Exact steps and actual behavior.
- Screenshot, log excerpt, or GitHub run URL when safe.
- Whether the behavior is reproducible.
- Follow-up issue or fix branch, once created.

Do not remove a failure from a baseline report after it is fixed. Add the retest
result and reference the fixing commit.

## Test data and safety

- Use only the dedicated QA users and sandbox repositories.
- Never use a production repository for manual dispatch checks.
- Never paste private keys, installation tokens, JWTs, or authorization headers
  into a report.
- Use harmless workflows with controlled pass/fail inputs.
- Reset or destroy the wp-env database only when the loss of local test data is
  intentional.
- Test uninstall on a disposable environment or after recording any evidence
  that must be preserved.

## Execution cadence

Run the local smoke subset:

- After environment or plugin-bootstrap changes.
- Before committing a QA-foundation milestone.

Run the mocked integration suite:

- Before merging changes that affect PHP behavior or WordPress integration.
- In the release-gate workflow across the supported WordPress/PHP matrix.

Run the Playwright admin suite:

- Before merging changes that affect admin markup, navigation, permissions, or settings.
- In the release-gate workflow.

Run the live sandbox subset:

- Before a release.
- After GitHub authentication, workflow dispatch, polling, or artifact changes.

## Entry criteria for the foundation baseline

- `wp-env` starts successfully.
- Deploy & Test is active.
- Administrator, Editor, and Subscriber users exist.
- The manual scenarios are available in `docs/qa/manual-scenarios.md`.

## Exit criteria for the QA foundation

- Every critical scenario has Pass, Fail, or an explained Blocked result.
- No failure is silently omitted.
- Confirmed defects have a reproducible scenario and follow-up branch or issue.
- No secret or production resource appears in committed test evidence.
