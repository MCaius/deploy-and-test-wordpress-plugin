=== Deploy & Test ===
Contributors: mcaius
Tags: github-actions, deployment, testing, developer-tools
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Trigger controlled GitHub Actions deploy and test workflows from the WordPress admin using a GitHub App.

== Description ==

Deploy & Test gives trusted WordPress users controlled buttons for triggering configured GitHub Actions deploy and test workflows.

GitHub App credentials remain server-side. The plugin generates short-lived installation tokens when an action runs, displays recent workflow status, loads compact test-summary artifacts, and records an audit trail in WordPress.

Administrators configure the GitHub owner, repositories, refs, workflow files, deploy button labels, target labels, optional environment URLs, test actions, and optional test environments. Editors can run the approved actions without receiving GitHub access or personal access tokens.

== Installation ==

1. Upload and activate the Deploy & Test plugin.
2. Create a GitHub App with Actions read and write access and install it on the required repositories.
3. Add the GitHub App ID, installation ID, and private key path to wp-config.php.
4. Open Deploy & Test in WordPress and configure the deploy and testing repositories and workflows.

== Frequently Asked Questions ==

= Does this plugin deploy files directly? =

No. It dispatches GitHub Actions workflows that are configured in your repositories.

= Are GitHub credentials stored in the WordPress database? =

No. GitHub App credentials are read from constants configured in wp-config.php.

== Changelog ==

= 1.0.4 =

* Released the duplicate-dispatch startup lock as soon as GitHub exposes the dispatched run.
* Allowed the next action immediately after workflow success, failure, cancellation, or timeout while preserving active-run and cross-session protection.

= 1.0.3 =

* Restored actions automatically after test workflow completion while preserving Test status.
* Updated running-workflow notices and Romanian translations.

= 1.0.2 =

* Added PHPUnit integration, WordPress admin E2E, compatibility, Plugin Check, and packaged-plugin release verification.
* Updated development dependencies and developer/QA documentation.

= 1.0.1 =

* Improved notices shown while deploy or test workflows are running.

= 1.0.0 =

* Initial public release.
