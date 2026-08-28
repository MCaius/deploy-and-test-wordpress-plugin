# Deploy & Test Critical Manual Scenarios

## How to use this document

Execute scenarios against the wp-env site unless a scenario explicitly requires
the live GitHub sandbox. Record results in
`docs/qa/reports/test-foundation-baseline.md`.

Common local accounts:

| Role | Username |
| --- | --- |
| Administrator | `admin` |
| Editor | `qa-editor` |
| Subscriber | `qa-subscriber` |

Do not record passwords or GitHub credentials in QA reports.

## Local foundation

### QA-001 — Clean activation

**Layer:** Local

1. Start a new or reset wp-env installation.
2. Check the plugin status with WP-CLI.
3. Open the Deploy & Test admin page as Administrator.

**Expected:** Deploy & Test is active, the admin page loads without a PHP error,
and no GitHub action is dispatched.

### QA-002 — Unconfigured state

**Layer:** Local

1. Open the plugin without GitHub App constants or saved repositories.
2. Inspect the deploy and test controls.

**Expected:** A clear configuration notice is visible, deploy controls are
disabled, no test action is available, and status panels explain that
configuration is incomplete.

## Permissions

### QA-003 — Administrator access

**Layer:** Local

1. Sign in as Administrator.
2. Open every plugin tab.

**Expected:** General, Connection, and Audit log are accessible. Configuration,
cleanup, connection-test, and action controls are available as appropriate.

### QA-004 — Editor access

**Layer:** Local

1. Sign in as `qa-editor`.
2. Open Deploy & Test.
3. Attempt to locate connection and cleanup settings.

**Expected:** The Editor can access operational deploy/test controls but cannot
change connection or cleanup settings.

### QA-005 — Subscriber denial

**Layer:** Local

1. Sign in as `qa-subscriber`.
2. Look for Deploy & Test in the admin menu.
3. Attempt to open its known admin URL directly.

**Expected:** No plugin menu is shown and direct access is denied.

## Configuration

### QA-006 — Save valid configuration

**Layer:** Live sandbox

1. As Administrator, enter the sandbox owner, deployment repository, refs, and
   YAML workflow filenames.
2. Add the test repository, environments, and enabled test actions.
3. Save.

**Expected:** A success notice appears, values persist after reload, and an audit
entry records a successful settings save.

### QA-007 — Reject malformed configuration

**Layer:** Local

1. As Administrator, try invalid owner, repository, ref, and workflow values.
2. Include a workflow path or non-YAML filename.
3. Save each invalid case.

**Expected:** Malformed values are rejected or safely normalized. Well-formed values may be saved without contacting GitHub.

### QA-008 — Missing GitHub App constants

**Layer:** Local

1. Save otherwise plausible repository values without GitHub App constants.
2. Return to General.

**Expected:** The plugin remains visibly unconfigured and actions remain locked.
No secret value is requested through the WordPress database-backed settings UI.

### QA-009 — Deployment repository connection

**Layer:** Live sandbox

1. Configure valid GitHub App constants and deployment repository values.
2. Click the deployment connection test.

**Expected:** The plugin reports a successful connection and workflow count. The
attempt is recorded in the audit log without exposing credentials.

### QA-010 — Testing repository connection

**Layer:** Live sandbox

1. Configure the sandbox testing repository.
2. Click its connection test.

**Expected:** The plugin reports a successful connection and workflow count. The
attempt is audited without exposing credentials.

## Deployment

### QA-011 — Preview dispatch

**Layer:** Live sandbox

1. Click Deploy Preview once.
2. Observe WordPress and the sandbox repository.

**Expected:** Exactly one preview workflow is dispatched, a success notice and
audit entry appear, polling finds the run, and the status links to the correct
GitHub run.

### QA-012 — Production confirmation cancellation

**Layer:** Live sandbox

1. Click Deploy Production.
2. Cancel the confirmation.

**Expected:** No workflow is dispatched and no successful production action is
recorded.

### QA-013 — Production dispatch

**Layer:** Live sandbox

1. Click Deploy Production.
2. Accept the confirmation.

**Expected:** Exactly one production workflow is dispatched and is distinguishable
from preview in the notice, status, and audit log.

### QA-014 — Deployment API failure

**Layer:** Mocked API

1. Return a controlled GitHub error or timeout for a deployment dispatch.
2. Attempt the action.

**Expected:** The UI reports a useful failure, no success is claimed, the lock is
released, and the failed attempt is audited without leaking credentials.

## Test workflows and summaries

### QA-015 — Passing test workflow

**Layer:** Live sandbox

1. Select a configured environment.
2. Trigger the passing test action.
3. Wait for completion and load the summary.

**Expected:** The correct workflow inputs are sent, status progresses to success,
the available actions are restored automatically with Test status still selected,
and the expected summary artifact is rendered understandably.

### QA-016 — Failing test workflow

**Layer:** Live sandbox

1. Trigger the intentionally failing test action.
2. Wait for completion and load its result.

**Expected:** WordPress reports failure rather than success, restores the
available actions automatically with Test status still selected, and displays
useful failed job or test information with a link to the correct GitHub run.

### QA-017 — Invalid test environment

**Layer:** Mocked API

1. Submit a test action with an environment value not present in saved settings.

**Expected:** The request is rejected as an unknown test environment and no
GitHub workflow is dispatched.

### QA-018 — Missing or malformed summary artifact

**Layer:** Mocked API

1. Test a completed run with no expected artifact.
2. Repeat with malformed JSON in the expected artifact.

**Expected:** Each condition produces a clear error without breaking the page,
and no fabricated summary is displayed.

## Audit

### QA-019 — Audit success and failure entries

**Layer:** Local plus live sandbox

1. Perform one successful settings/action operation.
2. Perform one controlled failed operation.
3. Open Audit log.

**Expected:** Newest entries appear first and contain time, user, action, status,
and safe details. No token, private key, JWT, or authorization header appears.

### QA-020 — Audit retention limit

**Layer:** Automated or local

1. Create more audit events than the configured retention limit.
2. Inspect the stored log.

**Expected:** Only the newest allowed entries remain, in newest-first order.

## Locking

### QA-021 — Rapid duplicate submission

**Layer:** Live sandbox

1. Submit the same action twice in rapid succession.

**Expected:** Only one workflow is dispatched. The second attempt is blocked with
a useful message and a `blocked` audit entry.

### QA-022 — Cross-action concurrency

**Layer:** Live sandbox

1. Start a deliberately slow workflow in one session.
2. While it is queued or running, try a different deploy or test action in a
   second session.

**Expected:** The second action is blocked until the active workflow finishes.
No unintended second workflow is dispatched.

## Uninstall

### QA-023 — Uninstall with cleanup enabled

**Layer:** Disposable local package

**Safety:** Do not delete the plugin from the canonical wp-env environment,
because that environment maps the repository's source directory. Use a separate
disposable WordPress environment with an installed ZIP or copied plugin.

1. Save recognizable settings and create audit data.
2. Enable deletion of plugin data on uninstall.
3. Delete the plugin from WordPress.
4. Reinstall and activate it.

**Expected:** Settings, audit entries, action locks, and cached test summaries are
removed. The plugin starts with defaults.

### QA-024 — Uninstall with cleanup disabled

**Layer:** Disposable local package

**Safety:** Use the separate disposable environment from QA-023, never the
canonical environment that maps the repository's source directory.

1. Save recognizable settings and audit data.
2. Disable deletion of plugin data on uninstall.
3. Delete, reinstall, and activate the plugin.

**Expected:** The saved settings and audit history remain available after
reinstallation.

## Deploy presentation

### QA-025 — Deploy labels and environment links

**Layer:** Local

1. Save custom Preview and Production deploy button labels.
2. Save valid HTTP or HTTPS URLs for both environments.
3. Open General and verify the configured labels and `Open: <URL>` links.
4. Confirm each link opens the correct URL in a new tab without dispatching a
   workflow.
5. Leave only one environment URL configured and verify that only its link
   appears.
6. Clear both labels and URLs, save again, and return to General.
7. Try to save an invalid or non-HTTP/HTTPS environment URL.

**Expected:** Custom labels and URLs persist. Empty labels restore `Deploy
Preview` and `Deploy Production`. Empty URLs remove the environment links.
Invalid environment URLs are rejected without replacing previously valid
settings.

### QA-026 — Independent deploy workflows

**Layer:** Local plus mocked API

1. Configure both Preview and Production workflow files and confirm that both
   deploy actions appear.
2. Clear only the Production workflow, save, and return to General.
3. Confirm that Preview remains available and Production is absent.
4. Repeat with only Production configured.
5. Clear both workflow fields and return to General.
6. Submit a forged action request for an environment whose workflow is empty.
7. Confirm separately that configured test actions remain available.

**Expected:** Each configured deploy environment works independently. Missing
workflow buttons and status cards are not displayed, the both-empty state is
explained clearly, no GitHub request is made for an unconfigured environment,
and test actions are unaffected.

## Native plugin updates

### QA-027 — GitHub release discovery and WordPress update

**Layer:** Disposable local package plus controlled release

1. Install the previous updater-enabled Deploy & Test version on a disposable
   WordPress site and save recognizable settings.
2. Publish a newer stable GitHub Release whose tag matches the plugin version
   and whose assets contain the verified `deploy-and-test.zip` package.
3. Refresh the WordPress Updates screen and confirm that the new version is
   offered for Deploy & Test.
4. Install it using the standard **Update now** action.
5. Confirm that the plugin remains active, reports the expected version, and
   preserves the saved settings.
6. Repeat discovery with a draft, prerelease, malformed tag, missing expected
   ZIP, and an asset URL outside the allow-listed repository.
7. Simulate an unavailable or malformed GitHub API response.

**Expected:** Only a newer stable release with the exact package from the
allow-listed HTTPS repository is offered. The update succeeds without losing
settings. Unsafe or unavailable releases fail closed without affecting the
installed plugin, and no GitHub App credentials or site data are sent.
