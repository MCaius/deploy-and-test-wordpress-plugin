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
- Future PHPUnit integration and browser tests.

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

Mocks are the default for automated tests because they are repeatable, do not
consume GitHub rate limits, and cannot trigger real workflows accidentally.

### 3. Live GitHub sandbox

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

## Supported roles

| Role | Expected access |
| --- | --- |
| Administrator | View the plugin, configure connections and cleanup, test connections, and run actions. |
| Editor | View the plugin and run configured deploy/test actions, but not change connection or cleanup settings. |
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

- On every pull request once the PHPUnit harness exists.

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

