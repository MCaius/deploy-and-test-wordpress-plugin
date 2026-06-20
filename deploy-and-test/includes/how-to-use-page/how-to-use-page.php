<?php
/**
 * How to use content for Deploy & Test.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$deploy_and_test_can_manage_settings = current_user_can( deploy_and_test_settings_capability() );
?>

<div class="deploy-and-test-how-to-use-page">
	<details class="deploy-and-test-how-to-use-toggle">
		<summary><?php echo esc_html__( 'General usage', 'deploy-and-test' ); ?></summary>

		<div class="deploy-and-test-how-to-use-content">
			<p>
				<?php echo wp_kses_post( __( 'Use <strong>Deploy Preview</strong> and <strong>Deploy Production</strong> to trigger the configured deploy workflows from WordPress. Use the <strong>Tests</strong> box to run the configured test actions against the selected test environment.', 'deploy-and-test' ) ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'The selected Tests on value is sent to GitHub Actions using the input configured in Connection > Test repository. Test buttons can point to the same workflow file with different input values, such as suite=api, suite=ui, or suite=seo.', 'deploy-and-test' ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'Deploy and test buttons are locked while any workflow is queued or running, so only one action can run at a time. After starting a test, the page opens Test status automatically and refreshes while the run is active.', 'deploy-and-test' ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'Use Deploy status for preview and production deploy results. Use Test status for recent test runs, summary artifacts, and individual passed or failed tests. For full logs and large reports, open the GitHub run.', 'deploy-and-test' ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'WordPress Editors can trigger the configured deploy and test actions, but only Administrators can change plugin configuration, workflow settings, cleanup settings, or audit-log access. This is intended for teams that want trusted non-technical WordPress users to run approved workflows without granting administrator or GitHub access.', 'deploy-and-test' ); ?>
			</p>
		</div>
	</details>

	<?php if ( $deploy_and_test_can_manage_settings ) : ?>
	<details class="deploy-and-test-how-to-use-toggle">
		<summary><?php echo esc_html__( 'Creating and Configuring the GitHub App', 'deploy-and-test' ); ?></summary>

		<div class="deploy-and-test-how-to-use-content">
			<ol>
				<li><?php echo esc_html__( 'Log in to GitHub with an account that owns or can administer the organization/repository used for deploys.', 'deploy-and-test' ); ?></li>
				<li><?php echo wp_kses_post( __( 'Open GitHub, then go to <strong>Settings</strong>.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Go to <strong>Developer settings</strong>, then <strong>GitHub Apps</strong>.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Click <strong>New GitHub App</strong>.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo esc_html__( 'Use a clear app name that explains the deploy purpose.', 'deploy-and-test' ); ?></li>
				<li><?php echo wp_kses_post( __( 'Set the <strong>Homepage URL</strong> to the WordPress site URL.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Leave <strong>Callback URL</strong> empty. This deploy flow does not use GitHub OAuth login.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Leave <strong>Request user authorization (OAuth) during installation</strong> unchecked.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Leave <strong>Enable Device Flow</strong> unchecked.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Leave <strong>Setup URL</strong> empty and leave <strong>Redirect on update</strong> unchecked.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Leave <strong>Webhook Active</strong> unchecked. Webhooks are not needed for the current deploy status flow.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Under <strong>Repository permissions</strong>, set <strong>Actions</strong> to <strong>Read and write</strong>.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo esc_html__( 'Leave all other permissions disabled unless they are explicitly required later.', 'deploy-and-test' ); ?></li>
				<li>
					<?php echo wp_kses_post( __( 'Under <strong>Where can this GitHub App be installed?</strong>, choose <strong>Any account</strong> if the app is created under a personal account but needs to be installed on an organization.', 'deploy-and-test' ) ); ?>
				</li>
				<li><?php echo esc_html__( 'Create the GitHub App.', 'deploy-and-test' ); ?></li>
				<li><?php echo wp_kses_post( __( 'After the app is created, copy the <strong>App ID</strong>.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Generate a private key and download the <code>.pem</code> file.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo esc_html__( 'Install the app on the organization or account that owns the repository.', 'deploy-and-test' ); ?></li>
				<li><?php echo wp_kses_post( __( 'Choose <strong>Only select repositories</strong> and select only the repository or repositories connected to this deploy flow.', 'deploy-and-test' ) ); ?></li>
				<li><?php echo esc_html__( 'If the repository belongs to an organization, an organization owner may need to approve or complete the installation.', 'deploy-and-test' ); ?></li>
				<li><?php echo esc_html__( 'After installation, open the installation page URL and copy the numeric installation ID from the URL.', 'deploy-and-test' ); ?></li>
			</ol>

			<p>
				<?php echo esc_html__( 'Deploy & Test reads deploy status by calling the GitHub API when the admin page loads or refreshes. Webhooks can be added later for real-time status updates, but they require a secure WordPress REST endpoint and webhook secret validation.', 'deploy-and-test' ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'The installation URL usually looks similar to this:', 'deploy-and-test' ); ?>
			</p>

			<pre><code>https://github.com/settings/installations/12345678</code></pre>

			<p>
				<?php echo wp_kses_post( __( 'In this example, <code>12345678</code> is the installation ID.', 'deploy-and-test' ) ); ?>
			</p>

			<p class="deploy-and-test-how-to-use-notice">
				<?php echo wp_kses_post( __( '<strong>Security notice:</strong> The GitHub App private key must never be committed to a repository, shown in browser JavaScript, or saved in a public folder.', 'deploy-and-test' ) ); ?>
			</p>
		</div>
	</details>

	<details class="deploy-and-test-how-to-use-toggle">
		<summary><?php echo esc_html__( 'Adding GitHub App Settings to wp-config.php', 'deploy-and-test' ); ?></summary>

		<div class="deploy-and-test-how-to-use-content">
			<p>
				<?php echo wp_kses_post( __( 'Connect to the server where WordPress is installed and open the <code>wp-config.php</code> file, typically located in the root directory of the WordPress installation.', 'deploy-and-test' ) ); ?>
			</p>

			<p><?php echo esc_html__( 'Add the GitHub App constants before the comment:', 'deploy-and-test' ); ?></p>

			<pre><code>/* That's all, stop editing! */</code></pre>

			<p>
				<?php echo wp_kses_post( __( '<strong>Recommended option:</strong> store the private key in a separate <code>.pem</code> file and keep only the file path in <code>wp-config.php</code>.', 'deploy-and-test' ) ); ?>
			</p>

			<pre><code>/* GitHub deploy &amp; test plugin */
define('DEPLOY_AND_TEST_GITHUB_APP_ID', 'your-app-id');
define('DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID', 'your-installation-id');
define('DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH', '/secure/path/github-app-private-key.pem');

/* That's all, stop editing! */</code></pre>

			<p>
				<?php echo wp_kses_post( __( 'The <code>.pem</code> file should ideally live outside the public WordPress web root. If that is not possible on the hosting environment, keep it in a protected server folder and make sure it is not committed to Git.', 'deploy-and-test' ) ); ?>
			</p>

			<p><?php echo esc_html__( 'Recommended permissions for the private key folder and file:', 'deploy-and-test' ); ?></p>

			<pre><code>Folder: 700
File:   600</code></pre>

			<p><?php echo esc_html__( 'If WordPress cannot read the private key with those permissions, use this fallback:', 'deploy-and-test' ); ?></p>

			<pre><code>Folder: 750
File:   640</code></pre>

			<p>
				<?php echo esc_html__( 'Avoid using public read permissions for the private key file unless the hosting environment gives no other option.', 'deploy-and-test' ); ?>
			</p>

			<p>
				<?php echo wp_kses_post( __( '<strong>Fallback option:</strong> place the private key directly in <code>wp-config.php</code>. This works, but it is easier to break because the newline characters must stay correct.', 'deploy-and-test' ) ); ?>
			</p>

			<pre><code>/* GitHub deploy &amp; test plugin */
define('DEPLOY_AND_TEST_GITHUB_APP_ID', 'your-app-id');
define('DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID', 'your-installation-id');
define('DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY', "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----");

/* That's all, stop editing! */</code></pre>

			<p class="deploy-and-test-how-to-use-notice">
				<?php echo wp_kses_post( __( '<strong>Important:</strong> The private key is a production secret. Anyone who has this key and the app IDs can generate temporary GitHub tokens for the repositories where the app is installed.', 'deploy-and-test' ) ); ?>
			</p>
		</div>
	</details>

	<details class="deploy-and-test-how-to-use-toggle">
		<summary><?php echo esc_html__( 'Configuring Workflows', 'deploy-and-test' ); ?></summary>

		<div class="deploy-and-test-how-to-use-content">
			<p>
				<?php echo wp_kses_post( __( 'In the <strong>Connection</strong> tab, set the GitHub owner, deploy repository, testing repository, source refs, target labels, and workflow filenames used by the buttons.', 'deploy-and-test' ) ); ?>
			</p>

			<pre><code>Deploy repository: example-website
Preview workflow file: deploy-preview.yml
Production workflow file: deploy-production.yml
Testing repository: example-tests
Test action: Run smoke tests -> tests.yml with suite=smoke
Source ref: main</code></pre>

			<p>
				<?php echo wp_kses_post( __( 'The workflow filenames must match files in each repository\'s <code>.github/workflows</code> directory. Enabled test actions automatically appear as buttons in the General tab.', 'deploy-and-test' ) ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'For test actions, each enabled row becomes one button. Use the label for the button text, the workflow file for the GitHub Actions file, and the input name/value pair for the suite or mode you want that button to run.', 'deploy-and-test' ); ?>
			</p>

			<pre><code>Label: Smoke tests
Workflow file: tests.yml
Input name: suite
Input value: smoke
Enabled: yes</code></pre>
		</div>
	</details>

	<details class="deploy-and-test-how-to-use-toggle">
		<summary><?php echo esc_html__( 'Structuring the Deploy Workflow to Work with the Plugin', 'deploy-and-test' ); ?></summary>

		<div class="deploy-and-test-how-to-use-content">
			<p>
				<?php echo wp_kses_post( __( 'The deploy repository should contain one GitHub Actions workflow for preview and one for production, or equivalent workflow files configured in <strong>Connection > Deploy repository</strong>. The plugin triggers these workflows with <code>workflow_dispatch</code>.', 'deploy-and-test' ) ); ?>
			</p>

			<pre><code>.github/workflows/deploy-preview.yml
.github/workflows/deploy-production.yml</code></pre>

			<p>
				<?php echo esc_html__( 'Each deploy workflow should support manual dispatch. Deploy & Test sends the source ref configured in the Connection tab when it dispatches the workflow.', 'deploy-and-test' ); ?>
			</p>

			<pre><code>name: Deploy Preview

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
		run: npm run deploy:preview</code></pre>

			<p>
				<?php echo esc_html__( 'For production, use a separate workflow file and a clear workflow name:', 'deploy-and-test' ); ?>
			</p>

			<pre><code>name: Deploy Production

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
		run: npm run deploy:production</code></pre>

			<p>
				<?php echo wp_kses_post( __( 'Recommended plugin settings: <code>Preview workflow file</code> should point to <code>deploy-preview.yml</code>, and <code>Production workflow file</code> should point to <code>deploy-production.yml</code>. Use names like <code>Deploy Preview</code> and <code>Deploy Production</code> so the status cards can classify recent runs correctly.', 'deploy-and-test' ) ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'Keep deploy workflows idempotent and safe to re-run. The plugin locks deploy and test buttons while any workflow is queued or running, but the workflow itself should still handle retries safely.', 'deploy-and-test' ); ?>
			</p>
		</div>
	</details>

	<details class="deploy-and-test-how-to-use-toggle">
		<summary><?php echo esc_html__( 'Structuring the Test Framework to Work with the Plugin', 'deploy-and-test' ); ?></summary>

		<div class="deploy-and-test-how-to-use-content">
			<p>
				<?php echo wp_kses_post( __( 'The testing repository should expose a manually dispatched GitHub Actions workflow with inputs that match the buttons configured in <strong>Connection > Test repository</strong>.', 'deploy-and-test' ) ); ?>
			</p>

			<p>
				<?php echo wp_kses_post( __( 'Use a stable <code>run-name</code> pattern so Deploy & Test can match a run to the expected summary artifact. The recommended pattern is <code>Run ${{ inputs.suite }} tests on ${{ inputs.target_env }}</code>.', 'deploy-and-test' ) ); ?>
			</p>

			<pre><code>name: Tests
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
		options: [chromium, firefox, webkit]</code></pre>

			<p>
				<?php echo esc_html__( 'Recommended Deploy & Test settings for that workflow:', 'deploy-and-test' ); ?>
			</p>

			<pre><code>Testing workflow file: tests.yml
Test environment input name: target_env
Test action input name: suite
Test action input values: all, api, ui, seo, performance</code></pre>

			<p>
				<?php echo esc_html__( 'A minimal workflow should run the selected suite, write a compact JSON summary, and upload that summary even when tests fail.', 'deploy-and-test' ); ?>
			</p>

			<pre><code>jobs:
	test:
	runs-on: ubuntu-latest
	steps:
		- name: Checkout repository
		uses: actions/checkout@v4

		- name: Install dependencies
		run: npm ci

		- name: Run selected tests
		run: npm run test -- --suite="${{ inputs.suite }}" --target="${{ inputs.target_env }}" --browser="${{ inputs.browser }}"

		- name: Write Deploy &amp; Test summary
		if: always()
		run: |
			mkdir -p test-results
			node scripts/write-deploy-summary.js \
			--suite="${{ inputs.suite }}" \
			--target-env="${{ inputs.target_env }}" \
			--browser="${{ inputs.browser }}" \
			--output="test-results/deploy-update-summary.json"

		- name: Upload Deploy &amp; Test summary
		if: always()
		uses: actions/upload-artifact@v4
		with:
			name: deploy-update-summary-${{ inputs.suite }}-${{ inputs.target_env }}
			path: test-results/deploy-update-summary.json</code></pre>

			<p>
				<?php echo esc_html__( 'Every test run should upload a small JSON summary artifact with this predictable name:', 'deploy-and-test' ); ?>
			</p>

			<pre><code>deploy-update-summary-${{ inputs.suite }}-${{ inputs.target_env }}</code></pre>

			<p>
				<?php echo esc_html__( 'The recommended file path inside the artifact is:', 'deploy-and-test' ); ?>
			</p>

			<pre><code>test-results/deploy-update-summary.json</code></pre>

			<p>
				<?php echo esc_html__( 'Deploy & Test looks for a file named deploy-update-summary.json inside the artifact archive, so the parent folder can be different if your test framework needs another layout.', 'deploy-and-test' ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'Deploy & Test reads that JSON and displays the test stats and individual test results in WordPress. Keep the JSON stable and small; use GitHub for full logs and large reports.', 'deploy-and-test' ); ?>
			</p>

			<pre><code>{
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
}</code></pre>

			<p>
				<?php echo esc_html__( 'The workflow should generate and upload this summary with if: always(), so WordPress can show results even when tests fail.', 'deploy-and-test' ); ?>
			</p>
		</div>
	</details>

	<details class="deploy-and-test-how-to-use-toggle">
		<summary><?php echo esc_html__( 'Audit Log', 'deploy-and-test' ); ?></summary>

		<div class="deploy-and-test-how-to-use-content">
			<p>
				<?php echo wp_kses_post( __( 'The audit log is stored in the WordPress database within the <code>wp_options</code> table under:', 'deploy-and-test' ) ); ?>
			</p>

			<pre><code>deploy_and_test_audit_log</code></pre>

			<p><?php echo esc_html__( 'The data is stored as a WordPress serialized array containing:', 'deploy-and-test' ); ?></p>

			<ul>
				<li><?php echo esc_html__( 'Timestamp', 'deploy-and-test' ); ?></li>
				<li><?php echo esc_html__( 'User', 'deploy-and-test' ); ?></li>
				<li><?php echo esc_html__( 'Action', 'deploy-and-test' ); ?></li>
				<li><?php echo esc_html__( 'Status', 'deploy-and-test' ); ?></li>
				<li><?php echo esc_html__( 'Details', 'deploy-and-test' ); ?></li>
			</ul>

			<p>
				<?php echo esc_html__( 'The system keeps the most recent 100 entries. When a new entry exceeds this limit, the oldest record is automatically removed.', 'deploy-and-test' ); ?>
			</p>

			<p>
				<?php echo esc_html__( 'If Delete plugin data on uninstall is enabled, uninstalling the plugin also removes the stored settings, audit log, temporary locks, and cached test summaries.', 'deploy-and-test' ); ?>
			</p>
		</div>
	</details>
	<?php endif; ?>
</div>
