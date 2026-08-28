# Changelog

## 1.1.0 - 2026-08-28

### Added

- Configurable Preview and Production deploy button labels.
- Optional Preview and Production environment links in the deploy status area.
- Regression coverage for deploy labels, environment URL validation, settings persistence, and safe link rendering.
- Independent Preview and Production workflow configuration, including per-environment controls and status cards.
- Native WordPress update discovery for stable GitHub Releases containing the verified `deploy-and-test.zip` asset, with strict repository validation and cached failure handling.
- Packaged-plugin update verification and release-version synchronization checks.
- Administrator-only Settings tab with independent Deploy and Tests feature controls.
- Regression coverage for feature defaults, operating modes, preserved configuration, disabled-action rejection, and cross-session settings behavior.

### Changed

- Disabled features no longer render controls or status UI, poll status, perform unnecessary GitHub requests, or accept manually constructed workflow actions.
- Moved uninstall cleanup from How to use to Settings and changed its unsaved default to disabled while preserving explicitly saved preferences.

## 1.0.4 - 2026-08-10

### Fixed

- Release the duplicate-dispatch startup lock once GitHub exposes the dispatched run, allowing another action immediately after success, failure, cancellation, or timeout while preserving active-run and cross-session protection.

### Changed

- Updated audited development dependencies to resolve published PHP_CodeSniffer and js-yaml security advisories.

## 1.0.3 - 2026-08-05

### Added

- Security hardening tests for privileged handler authorization, nonce enforcement, malicious configuration boundaries, output escaping, and credential leakage.
- A Playwright admin security journey confirming that stored untrusted content is escaped in settings and audit output without executing JavaScript.

### Changed

- Test workflow completion now restores the server-rendered action state automatically while preserving the Test status tab, with updated locking notices and Romanian translations.

## 1.0.2 - 2026-08-04

### Added

- Separate `wp-env` configurations for local development, PHPUnit integration tests, Playwright admin E2E tests, and packaged-plugin verification.
- PHPUnit integration coverage for permissions, validation, actions, GitHub authentication and API behavior, status handling, audit logs, and test summaries.
- Five Playwright journeys covering Administrator navigation and settings, validation feedback, Editor access, and Subscriber denial.
- Release gates for the supported WordPress/PHP matrix, Composer audit, WordPress Coding Standards, WordPress Plugin Check, packaged ZIP installation and activation, and WordPress admin E2E tests.
- QA strategy, manual scenarios, baseline evidence, and developer testing documentation.

### Changed

- Updated development dependencies to resolve published security advisories.

## 1.0.1 - 2026-06-22

### Changed

- Improved admin notices shown while deploy or test workflows are running, including refresh guidance and Romanian translation updates.

## 1.0.0 - 2026-06-20

### Added

- Initial public release of Deploy & Test.
- WordPress admin deploy controls for GitHub Actions workflows.
- GitHub App authentication with short-lived server-side installation tokens.
- Configurable preview and production deploy workflow dispatch.
- Configurable test repository, test environments, and test action buttons.
- Recent deploy and test status panels with GitHub run links.
- Test summary artifact reader for compact JSON reports.
- Audit log for deploy, test, connection, and settings events.
- Optional uninstall cleanup for settings, audit logs, locks, and cached summaries.
- Zip build script with manifest and SHA256 output.
- WordPress Coding Standards tooling and CI workflow.
