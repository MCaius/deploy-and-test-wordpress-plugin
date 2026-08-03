# Test Foundation Baseline

## Report status

**Complete for the currently available test layers**

This report preserves the initial behavior of Deploy & Test on the
`qa/test-foundation` branch. Results must remain in this file even when later
commits fix a failure.

## Environment

| Item | Value |
| --- | --- |
| Date started | 2026-07-29 |
| Branch | `qa/test-foundation` |
| Environment | `wp-env` at `http://localhost:8888` |
| WordPress version | 7.0.2 |
| PHP version | 8.3.32 |
| Deploy & Test version | `1.0.1` |
| GitHub sandbox | `mcaius-qa-sandbox` organization with separate deploy and test repositories |
| Automated suite | PHPUnit 9.6.35 through the WordPress `wp-env` tests environment |

## Evidence already observed

- `wp plugin status deploy-and-test` reported the plugin as active.
- The Administrator could open the Deploy & Test page.
- The unconfigured page displayed a GitHub App configuration notice.
- Deploy controls were unavailable while configuration was incomplete.
- Administrator, Editor, and Subscriber QA users were created successfully.

- GitHub App credentials loaded successfully from the ignored local override,
  and the private key was readable inside `wp-env`.
- Both sandbox repository connection checks found the expected two workflows.
- Preview and production deploy workflows completed successfully.
- Passing and intentionally failing test workflows returned their expected
  statuses and summary artifacts.
- The initial WordPress integration suite completed with 16 tests and 42
  assertions on PHP 8.3.33.

These observations establish the local and live-sandbox baseline. Mocked API
failure paths and uninstall behavior still require their dedicated test layers.

## Results

| ID | Result | Evidence or actual behavior | Follow-up |
| --- | --- | --- | --- |
| QA-001 | Pass | Plugin reported Active through WP-CLI and its admin page loaded. | None |
| QA-002 | Pass | Configuration notice was shown; deploy and test actions were unavailable. | None |
| QA-003 | Pass | Administrator accessed General, Connection, and Audit log, saved Connection settings, and managed the uninstall-cleanup option. | None |
| QA-004 | Pass | Editor accessed the plugin and operational deploy/test controls; Connection and uninstall-cleanup settings were hidden. | None |
| QA-005 | Pass | Subscriber saw no Deploy & Test menu; direct access returned “Sorry, you are not allowed to access this page.” | None |
| QA-006 | Pass | Valid sandbox deployment and test settings saved successfully, persisted after refresh, enabled the action buttons, and produced a successful audit entry. | None |
| QA-007 | Pass | Malformed owner, repository, ref, and non-YAML workflow values produced specific validation errors and were not persisted. The same behavior was confirmed for deployment and test settings. | None |
| QA-008 | Pass | Without GitHub App constants, the configuration notice remained visible and deploy/test actions stayed unavailable even though repository settings could be saved. | None |
| QA-009 | Pass | Deployment connection reported “GitHub connection works. Found 2 workflows.” The audit status was success and no credentials were exposed. | None |
| QA-010 | Pass | Testing connection reported “Testing repository connection works. Found 2 workflows.” The audit status was success and no credentials were exposed. | None |
| QA-011 | Pass | Exactly one preview workflow was dispatched. Polling detected success, action buttons unlocked automatically, the audit recorded `deploy_preview` success, and the GitHub link was correct. | None |
| QA-012 | Pass | Cancelling the production confirmation created no workflow, start notice, or production audit event; buttons remained available. | None |
| QA-013 | Pass | Exactly one production workflow was dispatched after confirmation. Polling detected success, buttons unlocked automatically, the audit recorded `deploy_production` success, and the GitHub link was correct. | None |
| QA-014 | Partial | Mocked GitHub HTTP 503 and network-timeout responses returned useful errors without exposing the installation token. The complete admin handler path, failed audit entry, and lock release are not yet exercised end-to-end. | Add a testable action-handler boundary covering redirect, audit, and lock release |
| QA-015 | Pass | The QA Sandbox environment sent `target_env=preview`; one passing workflow completed successfully. Its summary rendered 3 total, 3 passed, 0 failed, 0 skipped, and 0 timed out tests with three individual results and the correct GitHub link. | Fix the post-test unlock failure recorded below |
| QA-016 | Pass | One intentional-failure workflow completed as failure. Its summary rendered 2 total, 1 passed, 1 failed, 0 skipped, and 0 timed out tests, including the expected readable failure, and linked to the correct GitHub run. | Fix the post-test unlock failure recorded below |
| QA-017 | Pass | An unknown test environment returned `invalid_test_environment` before any mocked GitHub request was made. A configured environment sent the expected `suite` and `target_env` inputs. | None |
| QA-018 | Pass | Mocked missing artifacts returned `summary_artifact_missing`; archives with missing or malformed `deploy-update-summary.json` files returned the expected explicit errors. | None |
| QA-019 | Pass | Audit log contained successful and failed operations with time, user, action, status, and details; no App ID, Installation ID, token, JWT, or private-key content was exposed. | None |
| QA-020 | Pass | After 105 generated audit events, only the newest 100 remained in newest-first order with the expected user attribution. | None |
| QA-021 | Partial | A rapid double-click created exactly one live workflow because the UI disabled the button immediately. The automated integration test also confirmed that a recent server-side lock returns `action_already_starting` before any GitHub request. The handler-level `blocked` audit entry remains unverified. | Cover the complete blocked handler response and audit entry |
| QA-022 | Pass | While an Admin test workflow was active, all actions in the pre-opened Editor session were locked, no preview workflow was dispatched, and both sessions required refresh after the test completed. | Fix the post-test unlock failure recorded below |
| QA-023 | Blocked | Requires a disposable WordPress environment with an installed ZIP or copied plugin. | Never delete the mapped source plugin; prepare disposable uninstall environment |
| QA-024 | Blocked | Requires a disposable WordPress environment with an installed ZIP or copied plugin. | Execute after QA-023 in the disposable environment |

## Failures

| Scenario | Summary | Reproduction | Evidence | Follow-up |
| --- | --- | --- | --- | --- |
| QA-015, QA-016, QA-022 | Test completion does not automatically unlock action buttons. | Start a passing or failing test workflow, leave the page open until polling detects completion, and observe the controls without refreshing. | The final success/failure and summary become available, but Admin and Editor action buttons remain disabled until a manual page refresh. Deploy workflows did unlock automatically. | Correct the client-side lock state after test polling reaches a terminal status, then retest both roles. |
| QA-015 | A full refresh loses the selected Test status tab. | Open Test status and refresh the page to unlock the controls. | The page returns to Deploy status instead of preserving Test status. | Consider preserving the lower status tab in the URL or browser state. |

## Retests

| Scenario | Fix commit or branch | Result | Notes |
| --- | --- | --- | --- |
| — | — | — | — |

## Completion note

All scenarios available in the local and live-sandbox layers were attempted.
Remaining Blocked scenarios identify the automated handler coverage or
disposable uninstall environment they require. QA-014 and QA-021 remain Partial
because their lower-level failure and locking behavior is covered, while the
complete redirect/audit/lock-release handler behavior is not yet exercised.
