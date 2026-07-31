# Test Foundation Baseline

## Report status

**In progress**

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
| GitHub sandbox | Not configured yet |

## Evidence already observed

- `wp plugin status deploy-and-test` reported the plugin as active.
- The Administrator could open the Deploy & Test page.
- The unconfigured page displayed a GitHub App configuration notice.
- Deploy controls were unavailable while configuration was incomplete.
- Administrator, Editor, and Subscriber QA users were created successfully.

These observations establish the local foundation only. They do not establish
GitHub integration, role restrictions, locking, or uninstall behavior.

## Results

| ID | Result | Evidence or actual behavior | Follow-up |
| --- | --- | --- | --- |
| QA-001 | Pass | Plugin reported Active through WP-CLI and its admin page loaded. | None |
| QA-002 | Pass | Configuration notice was shown; deploy and test actions were unavailable. | None |
| QA-003 | Pass | Administrator page access was observed, but all tabs and controls were not checked systematically. | Execute locally |
| QA-004 | Pass | Editor account exists. | Execute locally |
| QA-005 | Pass | Subscriber account exists. | Execute locally |
| QA-006 | Blocked | Requires GitHub sandbox configuration. | Run after sandbox setup |
| QA-007 | Pass | — | Execute locally |
| QA-008 | Pass | — | Execute locally |
| QA-009 | Blocked | Requires GitHub sandbox configuration. | Run after sandbox setup |
| QA-010 | Blocked | Requires GitHub sandbox configuration. | Run after sandbox setup |
| QA-011 | Blocked | Requires GitHub sandbox workflows. | Run after sandbox setup |
| QA-012 | Blocked | Requires GitHub sandbox workflows. | Run after sandbox setup |
| QA-013 | Blocked | Requires GitHub sandbox workflows. | Run after sandbox setup |
| QA-014 | Blocked | Requires the mocked HTTP test harness. | Run during PHPUnit milestone |
| QA-015 | Blocked | Requires GitHub sandbox workflows and summary artifact. | Run after sandbox setup |
| QA-016 | Blocked | Requires intentionally failing sandbox workflow. | Run after sandbox setup |
| QA-017 | Blocked | Requires the mocked HTTP test harness. | Run during PHPUnit milestone |
| QA-018 | Blocked | Requires mocked artifact responses. | Run during PHPUnit milestone |
| QA-019 | Pass | — | Execute local portion now; complete after sandbox setup |
| QA-020 | Blocked | Best executed after automated integration harness exists. | Run during PHPUnit milestone |
| QA-021 | Blocked | Requires deliberately slow sandbox workflow. | Run after sandbox setup |
| QA-022 | Blocked | Requires deliberately slow sandbox workflow and two sessions. | Run after sandbox setup |
| QA-023 | Blocked | Requires a disposable WordPress environment with an installed ZIP or copied plugin. | Never delete the mapped source plugin; prepare disposable uninstall environment |
| QA-024 | Blocked | Requires a disposable WordPress environment with an installed ZIP or copied plugin. | Execute after QA-023 in the disposable environment |

## Failures

No failure has been recorded yet. Add every observed failure here; do not replace
it with only a later passing retest.

| Scenario | Summary | Reproduction | Evidence | Follow-up |
| --- | --- | --- | --- | --- |
| — | — | — | — | — |

## Retests

| Scenario | Fix commit or branch | Result | Notes |
| --- | --- | --- | --- |
| — | — | — | — |

## Completion note

The baseline is complete when every scenario has been attempted in its available
layer and all remaining Blocked results identify a concrete missing dependency.
