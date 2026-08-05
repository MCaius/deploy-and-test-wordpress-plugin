# Changelog

## Unreleased

### Added

- Security hardening tests for privileged handler authorization, nonce enforcement, malicious configuration boundaries, output escaping, and credential leakage.
- A Playwright admin security journey confirming that stored untrusted content is escaped in settings and audit output without executing JavaScript.

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
