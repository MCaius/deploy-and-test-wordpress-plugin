<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_render_connection_tab() {
	$can_manage              = current_user_can( deploy_and_test_settings_capability() );
	$private_key_configured  = deploy_and_test_github_app_private_key_is_configured();
	$settings                = deploy_and_test_get_settings();
	$test_connection_missing = array();

	if ( ! deploy_and_test_github_app_is_configured() ) {
		$test_connection_missing[] = __( 'GitHub App constants', 'deploy-and-test' );
	}

	if ( ! $settings['owner'] ) {
		$test_connection_missing[] = __( 'owner', 'deploy-and-test' );
	}

	if ( ! $settings['repo'] ) {
		$test_connection_missing[] = __( 'repository', 'deploy-and-test' );
	}

	$test_testing_connection_missing = array();

	if ( ! deploy_and_test_github_app_is_configured() ) {
		$test_testing_connection_missing[] = __( 'GitHub App constants', 'deploy-and-test' );
	}

	if ( ! $settings['owner'] ) {
		$test_testing_connection_missing[] = __( 'owner', 'deploy-and-test' );
	}

	if ( ! $settings['test_repo'] ) {
		$test_testing_connection_missing[] = __( 'testing repository', 'deploy-and-test' );
	}

	?>
	<section class="deploy-and-test-card">
		<h2><?php echo esc_html__( 'GitHub connection', 'deploy-and-test' ); ?></h2>
		<p class="deploy-and-test-muted">
			<?php echo esc_html__( 'Deploy & Test uses a GitHub App. WordPress generates a short-lived installation token server-side when an action runs.', 'deploy-and-test' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'GitHub App ID', 'deploy-and-test' ); ?></th>
				<td>
					<?php if ( defined( 'DEPLOY_AND_TEST_GITHUB_APP_ID' ) && DEPLOY_AND_TEST_GITHUB_APP_ID ) : ?>
						<code><?php echo esc_html( (string) DEPLOY_AND_TEST_GITHUB_APP_ID ); ?></code>
					<?php else : ?>
						<span class="deploy-and-test-muted"><?php echo esc_html__( 'Missing DEPLOY_AND_TEST_GITHUB_APP_ID.', 'deploy-and-test' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Installation ID', 'deploy-and-test' ); ?></th>
				<td>
					<?php if ( defined( 'DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID' ) && DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID ) : ?>
						<code><?php echo esc_html( (string) DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID ); ?></code>
					<?php else : ?>
						<span class="deploy-and-test-muted"><?php echo esc_html__( 'Missing DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID.', 'deploy-and-test' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Private key', 'deploy-and-test' ); ?></th>
				<td>
					<?php if ( $private_key_configured ) : ?>
						<span class="deploy-and-test-secret-status"><?php echo esc_html__( 'Configured:', 'deploy-and-test' ); ?> &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>
					<?php else : ?>
						<span class="deploy-and-test-muted"><?php echo esc_html__( 'Missing DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH or DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY.', 'deploy-and-test' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php if ( ! $can_manage ) : ?>
			<p><?php echo esc_html__( 'Only administrators can update connection settings.', 'deploy-and-test' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="deploy-and-test-settings-form">
				<input type="hidden" name="action" value="deploy_and_test_save_settings">
				<?php wp_nonce_field( 'deploy_and_test_save_settings', 'deploy_and_test_nonce' ); ?>

				<h3><?php echo esc_html__( 'GitHub owner', 'deploy-and-test' ); ?></h3>
				<p class="description">
					<?php echo wp_kses_post( __( 'Use one GitHub owner or organization for the deploy and testing repositories. Repository fields below should contain only the repo name, not <code>owner/repo</code>.', 'deploy-and-test' ) ); ?>
				</p>

				<div class="deploy-and-test-settings-grid">
					<label>
						<?php echo esc_html__( 'Owner or organization', 'deploy-and-test' ); ?>
						<input type="text" name="owner" value="<?php echo esc_attr( $settings['owner'] ); ?>" placeholder="example-org">
					</label>
				</div>

				<div class="deploy-and-test-subtabs" data-deploy-and-test-subtabs>
					<div class="deploy-and-test-subtab-list" role="tablist" aria-label="<?php echo esc_attr__( 'Repository settings', 'deploy-and-test' ); ?>">
						<button type="button" class="deploy-and-test-subtab is-active" id="deploy-and-test-deploy-repo-tab" role="tab" aria-selected="true" aria-controls="deploy-and-test-deploy-repo-panel" data-deploy-and-test-subtab="deploy">
							<?php echo esc_html__( 'Deploy repository', 'deploy-and-test' ); ?>
						</button>
						<button type="button" class="deploy-and-test-subtab" id="deploy-and-test-test-repo-tab" role="tab" aria-selected="false" aria-controls="deploy-and-test-test-repo-panel" data-deploy-and-test-subtab="test">
							<?php echo esc_html__( 'Test repository', 'deploy-and-test' ); ?>
						</button>
					</div>

					<div class="deploy-and-test-subtab-panel is-active" id="deploy-and-test-deploy-repo-panel" role="tabpanel" aria-labelledby="deploy-and-test-deploy-repo-tab" data-deploy-and-test-subtab-panel="deploy">
						<h3><?php echo esc_html__( 'Deploy repository', 'deploy-and-test' ); ?></h3>
						<p class="description">
							<?php echo wp_kses_post( __( 'Add the deploy repository, source ref, target labels, and workflow filenames used by the deploy buttons. Example workflow filenames: <code>deploy-preview.yml</code> and <code>deploy-production.yml</code>.', 'deploy-and-test' ) ); ?>
						</p>

						<div class="deploy-and-test-settings-grid">
							<label>
								<?php echo esc_html__( 'Repository', 'deploy-and-test' ); ?>
								<input type="text" name="repo" value="<?php echo esc_attr( $settings['repo'] ); ?>" placeholder="example-website">
							</label>

							<label>
								<?php echo esc_html__( 'Source ref', 'deploy-and-test' ); ?>
								<input type="text" name="ref" value="<?php echo esc_attr( $settings['ref'] ); ?>" placeholder="main">
							</label>
						</div>

						<h3><?php echo esc_html__( 'Workflow buttons', 'deploy-and-test' ); ?></h3>
						<div class="deploy-and-test-settings-grid">
							<label>
								<?php echo esc_html__( 'Preview workflow file', 'deploy-and-test' ); ?>
								<input type="text" name="preview_workflow" value="<?php echo esc_attr( $settings['preview_workflow'] ); ?>" placeholder="deploy-preview.yml">
							</label>

							<label>
								<?php echo esc_html__( 'Production workflow file', 'deploy-and-test' ); ?>
								<input type="text" name="production_workflow" value="<?php echo esc_attr( $settings['production_workflow'] ); ?>" placeholder="deploy-production.yml">
							</label>

							<label>
								<?php echo esc_html__( 'Preview target label', 'deploy-and-test' ); ?>
								<input type="text" name="preview_target" value="<?php echo esc_attr( $settings['preview_target'] ); ?>" placeholder="preview.example.com">
							</label>

							<label>
								<?php echo esc_html__( 'Production target label', 'deploy-and-test' ); ?>
								<input type="text" name="production_target" value="<?php echo esc_attr( $settings['production_target'] ); ?>" placeholder="example.com">
							</label>
						</div>
					</div>

					<div class="deploy-and-test-subtab-panel" id="deploy-and-test-test-repo-panel" role="tabpanel" aria-labelledby="deploy-and-test-test-repo-tab" data-deploy-and-test-subtab-panel="test" hidden>
						<h3><?php echo esc_html__( 'Test repository', 'deploy-and-test' ); ?></h3>
						<p class="description">
							<?php echo esc_html__( 'Configure the separate repository that contains automation tests, then add the buttons that should appear in the General tab.', 'deploy-and-test' ); ?>
						</p>

						<div class="deploy-and-test-settings-grid">
							<label>
								<?php echo esc_html__( 'Repository', 'deploy-and-test' ); ?>
								<input type="text" name="test_repo" value="<?php echo esc_attr( $settings['test_repo'] ); ?>" placeholder="example-tests">
							</label>

							<label>
								<?php echo esc_html__( 'Source ref', 'deploy-and-test' ); ?>
								<input type="text" name="test_ref" value="<?php echo esc_attr( $settings['test_ref'] ); ?>" placeholder="main">
							</label>
						</div>

						<?php deploy_and_test_render_test_environments_settings( $settings['test_environments'], $settings['test_environment_input'] ); ?>
						<?php deploy_and_test_render_test_actions_settings( $settings['test_actions'] ); ?>
					</div>
				</div>

				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save settings', 'deploy-and-test' ); ?></button>
			</form>

			<div class="deploy-and-test-connection-tests">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="deploy-and-test-test-connection-form">
					<input type="hidden" name="action" value="deploy_and_test_test_connection">
					<?php wp_nonce_field( 'deploy_and_test_test_connection', 'deploy_and_test_nonce' ); ?>

					<button type="submit" class="button button-secondary" <?php disabled( (bool) $test_connection_missing ); ?>>
						<?php echo esc_html__( 'Test deploy repository', 'deploy-and-test' ); ?>
					</button>

					<?php if ( $test_connection_missing ) : ?>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: comma-separated missing deploy connection fields. */
									__( 'Save the missing connection details first: %s.', 'deploy-and-test' ),
									implode( ', ', $test_connection_missing )
								)
							);
							?>
						</p>
					<?php endif; ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="deploy-and-test-test-connection-form">
					<input type="hidden" name="action" value="deploy_and_test_test_testing_connection">
					<?php wp_nonce_field( 'deploy_and_test_test_testing_connection', 'deploy_and_test_nonce' ); ?>

					<button type="submit" class="button button-secondary" <?php disabled( (bool) $test_testing_connection_missing ); ?>>
						<?php echo esc_html__( 'Test testing repository', 'deploy-and-test' ); ?>
					</button>

					<?php if ( $test_testing_connection_missing ) : ?>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: comma-separated missing testing repository connection fields. */
									__( 'Save the missing testing details first: %s.', 'deploy-and-test' ),
									implode( ', ', $test_testing_connection_missing )
								)
							);
							?>
						</p>
					<?php endif; ?>
				</form>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

function deploy_and_test_render_test_environments_settings( $test_environments, $test_environment_input ) {
	if ( ! is_array( $test_environments ) ) {
		$test_environments = array();
	}

	?>
	<div class="deploy-and-test-test-environments-settings">
		<div class="deploy-and-test-test-actions-heading">
			<h3><?php echo esc_html__( 'Test environments', 'deploy-and-test' ); ?></h3>
			<button type="button" class="button" id="deploy-and-test-add-test-environment"><?php echo esc_html__( 'Add test environment', 'deploy-and-test' ); ?></button>
		</div>

		<p class="description">
			<?php echo esc_html__( 'These options appear in the General tab next to Tests. The selected value is sent to GitHub Actions using the input name below.', 'deploy-and-test' ); ?>
		</p>

		<div class="deploy-and-test-settings-grid deploy-and-test-test-environment-input">
			<label>
				<?php echo esc_html__( 'GitHub Actions input name', 'deploy-and-test' ); ?>
				<input type="text" name="test_environment_input" value="<?php echo esc_attr( $test_environment_input ); ?>" placeholder="target_env">
			</label>
		</div>

		<table class="widefat deploy-and-test-test-environments-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Label name', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Env variable', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Remove', 'deploy-and-test' ); ?></th>
				</tr>
			</thead>
			<tbody id="deploy-and-test-test-environments-body">
				<?php foreach ( $test_environments as $index => $test_environment ) : ?>
					<?php deploy_and_test_render_test_environment_row( (int) $index, $test_environment ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<script type="text/html" id="deploy-and-test-test-environment-template">
			<?php
			deploy_and_test_render_test_environment_row(
				'__index__',
				array(
					'label' => '',
					'value' => '',
				)
			);
			?>
		</script>
	</div>
	<?php
}

function deploy_and_test_render_test_environment_row( $index, $test_environment ) {
	$test_environment = array_merge(
		array(
			'label' => '',
			'value' => '',
		),
		is_array( $test_environment ) ? $test_environment : array()
	);

	?>
	<tr class="deploy-and-test-test-environment-row">
		<td>
			<input type="text" name="test_environments[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $test_environment['label'] ); ?>" placeholder="<?php echo esc_attr__( 'Preview', 'deploy-and-test' ); ?>">
		</td>
		<td>
			<input type="text" name="test_environments[<?php echo esc_attr( (string) $index ); ?>][value]" value="<?php echo esc_attr( $test_environment['value'] ); ?>" placeholder="preview">
		</td>
		<td>
			<button type="button" class="button deploy-and-test-remove-test-environment"><?php echo esc_html__( 'Remove', 'deploy-and-test' ); ?></button>
		</td>
	</tr>
	<?php
}

function deploy_and_test_render_test_actions_settings( $test_actions ) {
	if ( ! is_array( $test_actions ) ) {
		$test_actions = array();
	}

	?>
	<div class="deploy-and-test-test-actions-settings">
		<div class="deploy-and-test-test-actions-heading">
			<h3><?php echo esc_html__( 'Test actions', 'deploy-and-test' ); ?></h3>
			<button type="button" class="button" id="deploy-and-test-add-test-action"><?php echo esc_html__( 'Add test action', 'deploy-and-test' ); ?></button>
		</div>

		<p class="description">
			<?php echo esc_html__( 'Each enabled row becomes a button in General. Input name and value are optional; use them for workflows that dispatch one file with different suites.', 'deploy-and-test' ); ?>
		</p>

		<table class="widefat deploy-and-test-test-actions-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Enabled', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Button label', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Workflow file', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Input name', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Input value', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Order', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Remove', 'deploy-and-test' ); ?></th>
				</tr>
			</thead>
			<tbody id="deploy-and-test-test-actions-body">
				<?php foreach ( $test_actions as $index => $test_action ) : ?>
					<?php deploy_and_test_render_test_action_row( (int) $index, $test_action ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<script type="text/html" id="deploy-and-test-test-action-template">
			<?php
			deploy_and_test_render_test_action_row(
				'__index__',
				array(
					'enabled'     => true,
					'label'       => '',
					'workflow'    => 'tests.yml',
					'input_name'  => 'suite',
					'input_value' => '',
					'order'       => 10,
				)
			);
			?>
		</script>
	</div>
	<?php
}

function deploy_and_test_render_test_action_row( $index, $test_action ) {
	$test_action = array_merge(
		array(
			'enabled'     => true,
			'label'       => '',
			'workflow'    => '',
			'input_name'  => 'suite',
			'input_value' => '',
			'order'       => 10,
		),
		is_array( $test_action ) ? $test_action : array()
	);

	?>
	<tr class="deploy-and-test-test-action-row">
		<td>
			<input type="hidden" name="test_actions[<?php echo esc_attr( (string) $index ); ?>][enabled]" value="0">
			<input type="checkbox" name="test_actions[<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( (bool) $test_action['enabled'] ); ?>>
		</td>
		<td>
			<input type="text" name="test_actions[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $test_action['label'] ); ?>" placeholder="<?php echo esc_attr__( 'Run smoke tests', 'deploy-and-test' ); ?>">
		</td>
		<td>
			<input type="text" name="test_actions[<?php echo esc_attr( (string) $index ); ?>][workflow]" value="<?php echo esc_attr( $test_action['workflow'] ); ?>" placeholder="tests.yml">
		</td>
		<td>
			<input type="text" name="test_actions[<?php echo esc_attr( (string) $index ); ?>][input_name]" value="<?php echo esc_attr( $test_action['input_name'] ); ?>" placeholder="suite">
		</td>
		<td>
			<input type="text" name="test_actions[<?php echo esc_attr( (string) $index ); ?>][input_value]" value="<?php echo esc_attr( $test_action['input_value'] ); ?>" placeholder="smoke">
		</td>
		<td>
			<input type="number" name="test_actions[<?php echo esc_attr( (string) $index ); ?>][order]" value="<?php echo esc_attr( (string) $test_action['order'] ); ?>" step="1">
		</td>
		<td>
			<button type="button" class="button deploy-and-test-remove-test-action"><?php echo esc_html__( 'Remove', 'deploy-and-test' ); ?></button>
		</td>
	</tr>
	<?php
}
