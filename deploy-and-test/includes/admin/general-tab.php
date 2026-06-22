<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_render_general_tab( $configured ) {
	$can_run_actions   = $configured;
	$runs              = $can_run_actions ? deploy_and_test_github_get_recent_runs() : new WP_Error( 'missing_config', 'Deploy & Test is not fully configured.' );
	$deploy_status     = deploy_and_test_get_deploy_status( $runs );
	$test_runs         = deploy_and_test_tests_are_configured() ? deploy_and_test_github_get_recent_test_runs() : new WP_Error( 'missing_config', __( 'Testing repository is not fully configured.', 'deploy-and-test' ) );
	$test_status       = deploy_and_test_get_test_status( $test_runs );
	$has_active_action = deploy_and_test_status_has_active_run( $deploy_status ) || deploy_and_test_test_status_has_active_run( $test_status );
	$active_status_tab = isset( $_GET['deploy_and_test_status_tab'] ) ? sanitize_key( $_GET['deploy_and_test_status_tab'] ) : 'deploy';

	if ( ! in_array( $active_status_tab, array( 'deploy', 'test' ), true ) ) {
		$active_status_tab = 'deploy';
	}

	?>
	<div class="deploy-and-test-grid" id="deploy-and-test-controls" data-has-active-action="<?php echo esc_attr( $has_active_action ? '1' : '0' ); ?>">
		<section class="deploy-and-test-card">
			<h2><?php echo esc_html__( 'Deploy', 'deploy-and-test' ); ?></h2>
			<p class="deploy-and-test-muted"><?php echo esc_html__( 'Trigger configured GitHub Actions deploy workflows without pushing code.', 'deploy-and-test' ); ?></p>

			<div class="deploy-and-test-actions">
				<?php deploy_and_test_action_form( 'deploy_preview', __( 'Deploy Preview', 'deploy-and-test' ), 'button button-primary button-hero', ! $can_run_actions || $has_active_action, '', 'preview' ); ?>
				<?php deploy_and_test_action_form( 'deploy_production', __( 'Deploy Production', 'deploy-and-test' ), 'button button-secondary button-hero', ! $can_run_actions || $has_active_action, __( 'Are you sure you want to deploy production?', 'deploy-and-test' ), 'production' ); ?>
			</div>
			<?php if ( $has_active_action ) : ?>
				<p class="deploy-and-test-lock-notice"><?php echo esc_html__( 'Actions are locked while a deploy or test workflow is running. Refresh this page after it finishes to re-enable the buttons.', 'deploy-and-test' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="deploy-and-test-card">
			<div class="deploy-and-test-tests-heading">
				<h2><?php echo esc_html__( 'Tests', 'deploy-and-test' ); ?></h2>
				<?php deploy_and_test_render_test_environment_select(); ?>
			</div>
			<p class="deploy-and-test-muted"><?php echo esc_html__( 'Run configured test workflows from the testing repository.', 'deploy-and-test' ); ?></p>

			<div class="deploy-and-test-test-actions">
				<?php
				$test_actions = deploy_and_test_get_enabled_test_actions();

				if ( $test_actions ) :
					foreach ( $test_actions as $test_action ) :
						deploy_and_test_action_form(
							'test_' . $test_action['id'],
							$test_action['label'],
							'button',
							! deploy_and_test_tests_are_configured() || $has_active_action,
							'',
							'',
							array(
								'test_environment' => deploy_and_test_default_test_environment_value(),
							)
						);
					endforeach;
				else :
					?>
					<p class="deploy-and-test-muted"><?php echo esc_html__( 'No test actions configured yet.', 'deploy-and-test' ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $has_active_action ) : ?>
				<p class="deploy-and-test-lock-notice"><?php echo esc_html__( 'Test buttons are locked until the active workflow finishes. Refresh this page after it finishes to re-enable the buttons.', 'deploy-and-test' ); ?></p>
			<?php endif; ?>
		</section>
	</div>

	<div
		id="deploy-and-test-status-tabs"
		class="deploy-and-test-status-tabs"
		data-active-status-tab="<?php echo esc_attr( $active_status_tab ); ?>"
		data-has-active-deploy-run="<?php echo esc_attr( deploy_and_test_status_has_active_run( $deploy_status ) ? '1' : '0' ); ?>"
		data-has-active-test-run="<?php echo esc_attr( deploy_and_test_test_status_has_active_run( $test_status ) ? '1' : '0' ); ?>"
		data-has-active-action="<?php echo esc_attr( $has_active_action ? '1' : '0' ); ?>"
	>
		<div class="deploy-and-test-subtab-list" role="tablist" aria-label="<?php echo esc_attr__( 'Status panels', 'deploy-and-test' ); ?>">
			<button type="button" class="deploy-and-test-subtab <?php echo esc_attr( $active_status_tab === 'deploy' ? 'is-active' : '' ); ?>" role="tab" aria-selected="<?php echo esc_attr( $active_status_tab === 'deploy' ? 'true' : 'false' ); ?>" data-deploy-and-test-status-tab="deploy">
				<?php echo esc_html__( 'Deploy status', 'deploy-and-test' ); ?>
			</button>
			<button type="button" class="deploy-and-test-subtab <?php echo esc_attr( $active_status_tab === 'test' ? 'is-active' : '' ); ?>" role="tab" aria-selected="<?php echo esc_attr( $active_status_tab === 'test' ? 'true' : 'false' ); ?>" data-deploy-and-test-status-tab="test">
				<?php echo esc_html__( 'Test status', 'deploy-and-test' ); ?>
			</button>
		</div>

		<div id="deploy-and-test-deploy-status" class="deploy-and-test-status-panel <?php echo esc_attr( $active_status_tab === 'deploy' ? 'is-active' : '' ); ?>" data-deploy-and-test-status-panel="deploy" <?php echo $active_status_tab === 'deploy' ? '' : 'hidden'; ?>>
			<?php deploy_and_test_render_status_panel( $runs, $can_run_actions ); ?>
		</div>

		<div id="deploy-and-test-test-status" class="deploy-and-test-status-panel <?php echo esc_attr( $active_status_tab === 'test' ? 'is-active' : '' ); ?>" data-deploy-and-test-status-panel="test" <?php echo $active_status_tab === 'test' ? '' : 'hidden'; ?>>
			<?php deploy_and_test_render_test_status_panel( $test_runs, deploy_and_test_tests_are_configured() ); ?>
		</div>
	</div>
	<?php
}

function deploy_and_test_render_test_environment_select() {
	$test_environments = array_filter(
		deploy_and_test_get_test_environments(),
		function ( $test_environment ) {
			return ( $test_environment['label'] ?? '' ) && ( $test_environment['value'] ?? '' ) !== '';
		}
	);

	if ( ! $test_environments ) {
		return;
	}

	?>
	<label class="deploy-and-test-tests-on">
		<span><?php echo esc_html__( 'Tests on:', 'deploy-and-test' ); ?></span>
		<select id="deploy-and-test-test-environment-select">
			<?php foreach ( $test_environments as $test_environment ) : ?>
				<option value="<?php echo esc_attr( $test_environment['value'] ); ?>"><?php echo esc_html( $test_environment['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}

function deploy_and_test_default_test_environment_value() {
	foreach ( deploy_and_test_get_test_environments() as $test_environment ) {
		if ( ( $test_environment['label'] ?? '' ) && ( $test_environment['value'] ?? '' ) !== '' ) {
			return $test_environment['value'];
		}
	}

	return '';
}

function deploy_and_test_action_form( $action_type, $label, $class, $disabled = false, $confirm = '', $environment = '', $hidden_fields = array() ) {
	$confirm_attribute = $confirm ? 'return confirm(' . wp_json_encode( $confirm ) . ');' : '';

	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="deploy-and-test-action-form" data-environment="<?php echo esc_attr( $environment ); ?>">
		<input type="hidden" name="action" value="deploy_and_test_action">
		<input type="hidden" name="deploy_action" value="<?php echo esc_attr( $action_type ); ?>">
		<?php foreach ( $hidden_fields as $field_name => $field_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $field_value ); ?>">
		<?php endforeach; ?>
		<?php wp_nonce_field( 'deploy_and_test_action', 'deploy_and_test_nonce' ); ?>
		<button
			type="submit"
			class="<?php echo esc_attr( $class ); ?>"
			<?php disabled( $disabled ); ?>
			<?php echo $confirm_attribute ? 'onclick="' . esc_attr( $confirm_attribute ) . '"' : ''; ?>
		>
			<?php echo esc_html( $label ); ?>
		</button>
	</form>
	<?php
}

function deploy_and_test_render_status_panel( $runs, $can_run_actions ) {
	$deploy_status = deploy_and_test_get_deploy_status( $runs );

	?>
	<section class="deploy-and-test-card">
		<p class="deploy-and-test-status-notice"><?php echo esc_html__( 'If deploy fails, wait 2-3 minutes and re-try.', 'deploy-and-test' ); ?></p>

		<div class="deploy-and-test-status-heading">
			<h2><?php echo esc_html__( 'Deploy status', 'deploy-and-test' ); ?></h2>
			<?php if ( deploy_and_test_status_has_active_run( $deploy_status ) ) : ?>
				<span class="deploy-and-test-auto-refresh">
					<span class="spinner is-active"></span>
					<?php echo esc_html__( 'Auto-refreshing every 5 seconds', 'deploy-and-test' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( is_wp_error( $runs ) ) : ?>
			<p class="deploy-and-test-muted"><?php echo esc_html( $runs->get_error_message() ); ?></p>
		<?php else : ?>
			<div class="deploy-and-test-status-grid">
				<?php deploy_and_test_render_environment_status_card( 'preview', 'Preview', deploy_and_test_get_setting( 'preview_target' ), $deploy_status ); ?>
				<?php deploy_and_test_render_environment_status_card( 'production', 'Production', deploy_and_test_get_setting( 'production_target' ), $deploy_status ); ?>
			</div>
		<?php endif; ?>
	</section>

	<section class="deploy-and-test-card">
		<h2><?php echo esc_html__( 'Recent GitHub runs', 'deploy-and-test' ); ?></h2>
		<?php deploy_and_test_render_runs_table( $runs ); ?>
	</section>
	<?php
}

function deploy_and_test_render_environment_status_card( $environment, $label, $target, $deploy_status ) {
	$run     = $deploy_status[ $environment ]['latest'] ?? null;
	$state   = $run ? deploy_and_test_get_run_state( $run ) : 'idle';
	$message = $run ? deploy_and_test_get_run_message( $run ) : __( 'No recent deploy found.', 'deploy-and-test' );

	?>
	<article class="deploy-and-test-status-card deploy-and-test-status-card-<?php echo esc_attr( $state ); ?>">
		<div class="deploy-and-test-status-card-header">
			<h3><?php echo esc_html( $label ); ?></h3>
			<span class="deploy-and-test-status-badge deploy-and-test-status-badge-<?php echo esc_attr( $state ); ?>">
				<?php echo esc_html( deploy_and_test_get_run_state_label( $state ) ); ?>
			</span>
		</div>

		<p><?php echo esc_html( $message ); ?></p>

		<dl>
			<div>
				<dt><?php echo esc_html__( 'Source ref', 'deploy-and-test' ); ?></dt>
				<dd><?php echo esc_html( $run['head_branch'] ?? deploy_and_test_get_setting( 'ref' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Target', 'deploy-and-test' ); ?></dt>
				<dd><?php echo esc_html( $target ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Updated', 'deploy-and-test' ); ?></dt>
				<dd><?php echo esc_html( deploy_and_test_format_github_datetime( $run['updated_at'] ?? '' ) ); ?></dd>
			</div>
		</dl>

		<?php if ( ! empty( $run['html_url'] ) ) : ?>
			<div class="deploy-and-test-status-card-actions">
				<a href="<?php echo esc_url( $run['html_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open GitHub run', 'deploy-and-test' ); ?></a>
			</div>
		<?php endif; ?>
	</article>
	<?php
}

function deploy_and_test_render_runs_table( $runs ) {
	if ( is_wp_error( $runs ) ) {
		echo '<p class="deploy-and-test-muted">' . esc_html( $runs->get_error_message() ) . '</p>';
		return;
	}

	if ( empty( $runs ) ) {
		echo '<p class="deploy-and-test-muted">' . esc_html__( 'No workflow runs found.', 'deploy-and-test' ) . '</p>';
		return;
	}

	?>
	<table class="widefat striped deploy-and-test-runs-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Workflow', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Conclusion', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Source ref', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Updated', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'GitHub', 'deploy-and-test' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $runs as $run ) : ?>
				<tr>
					<td><?php echo esc_html( $run['name'] ?? '' ); ?></td>
					<td><?php echo esc_html( $run['status'] ?? '' ); ?></td>
					<td><?php echo esc_html( $run['conclusion'] ?? '-' ); ?></td>
					<td><?php echo esc_html( $run['head_branch'] ?? '' ); ?></td>
					<td><?php echo esc_html( deploy_and_test_format_github_datetime( $run['updated_at'] ?? '' ) ); ?></td>
					<td>
						<?php if ( ! empty( $run['html_url'] ) ) : ?>
							<a href="<?php echo esc_url( $run['html_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open', 'deploy-and-test' ); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function deploy_and_test_render_test_status_panel( $runs, $configured ) {
	$test_status = deploy_and_test_get_test_status( $runs );
	$latest_run  = $test_status['latest'];

	?>
	<section class="deploy-and-test-card">
		<p class="deploy-and-test-status-notice"><?php echo esc_html__( 'Test results can take a few minutes to appear after a workflow starts. The summary is loaded from the deploy-update-summary artifact and cached temporarily.', 'deploy-and-test' ); ?></p>

		<div class="deploy-and-test-status-heading">
			<h2><?php echo esc_html__( 'Test status', 'deploy-and-test' ); ?></h2>
			<?php if ( deploy_and_test_test_status_has_active_run( $test_status ) ) : ?>
				<span class="deploy-and-test-auto-refresh">
					<span class="spinner is-active"></span>
					<?php echo esc_html__( 'Auto-refreshing every 5 seconds', 'deploy-and-test' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( is_wp_error( $runs ) ) : ?>
			<p class="deploy-and-test-muted"><?php echo esc_html( $runs->get_error_message() ); ?></p>
		<?php elseif ( ! $latest_run ) : ?>
			<p class="deploy-and-test-muted"><?php echo esc_html__( 'No recent test run found.', 'deploy-and-test' ); ?></p>
		<?php else : ?>
			<?php deploy_and_test_render_test_run_card( $latest_run ); ?>
		<?php endif; ?>
	</section>

	<section class="deploy-and-test-card">
		<h2><?php echo esc_html__( 'Recent test runs', 'deploy-and-test' ); ?></h2>
		<?php deploy_and_test_render_runs_table( $runs ); ?>
	</section>
	<?php
}

function deploy_and_test_render_test_run_card( $run ) {
	$state = deploy_and_test_get_run_state( $run );
	$jobs  = deploy_and_test_github_get_run_jobs( deploy_and_test_get_setting( 'test_repo' ), $run['id'] ?? 0 );

	?>
	<article class="deploy-and-test-status-card deploy-and-test-status-card-<?php echo esc_attr( $state ); ?>">
		<div class="deploy-and-test-status-card-header">
			<h3><?php echo esc_html( $run['display_title'] ?? $run['name'] ?? __( 'Latest test run', 'deploy-and-test' ) ); ?></h3>
			<span class="deploy-and-test-status-badge deploy-and-test-status-badge-<?php echo esc_attr( $state ); ?>">
				<?php echo esc_html( deploy_and_test_get_run_state_label( $state ) ); ?>
			</span>
		</div>

		<dl>
			<div>
				<dt><?php echo esc_html__( 'Workflow', 'deploy-and-test' ); ?></dt>
				<dd><?php echo esc_html( $run['name'] ?? '-' ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Source ref', 'deploy-and-test' ); ?></dt>
				<dd><?php echo esc_html( $run['head_branch'] ?? deploy_and_test_get_setting( 'test_ref' ) ); ?></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Updated', 'deploy-and-test' ); ?></dt>
				<dd><?php echo esc_html( deploy_and_test_format_github_datetime( $run['updated_at'] ?? '' ) ); ?></dd>
			</div>
		</dl>

		<div class="deploy-and-test-status-card-actions">
			<?php if ( ! empty( $run['html_url'] ) ) : ?>
				<a href="<?php echo esc_url( $run['html_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open GitHub run', 'deploy-and-test' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $run['id'] ) ) : ?>
				<button
					type="button"
					class="button deploy-and-test-load-test-summary"
					data-run-id="<?php echo esc_attr( (string) $run['id'] ); ?>"
					<?php disabled( deploy_and_test_run_is_active( $run ) ); ?>
				>
					<?php echo esc_html__( 'Load test summary', 'deploy-and-test' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<?php deploy_and_test_render_test_jobs( $jobs ); ?>

		<div class="deploy-and-test-test-summary-output" data-test-summary-for="<?php echo esc_attr( (string) ( $run['id'] ?? '' ) ); ?>"></div>
	</article>
	<?php
}

function deploy_and_test_render_test_jobs( $jobs ) {
	if ( is_wp_error( $jobs ) ) {
		echo '<p class="deploy-and-test-muted">' . esc_html( $jobs->get_error_message() ) . '</p>';
		return;
	}

	if ( ! $jobs ) {
		return;
	}

	?>
	<div class="deploy-and-test-test-jobs">
		<?php foreach ( $jobs as $job ) : ?>
			<details class="deploy-and-test-test-job">
				<summary>
					<span><?php echo esc_html( $job['name'] ?? __( 'Test job', 'deploy-and-test' ) ); ?></span>
					<span class="deploy-and-test-status-badge deploy-and-test-status-badge-<?php echo esc_attr( deploy_and_test_get_run_state( $job ) ); ?>">
						<?php echo esc_html( deploy_and_test_get_run_state_label( deploy_and_test_get_run_state( $job ) ) ); ?>
					</span>
				</summary>

				<?php if ( ! empty( $job['steps'] ) && is_array( $job['steps'] ) ) : ?>
					<ol class="deploy-and-test-test-steps">
						<?php foreach ( $job['steps'] as $step ) : ?>
							<li>
								<span><?php echo esc_html( $step['name'] ?? '' ); ?></span>
								<span><?php echo esc_html( $step['conclusion'] ?? $step['status'] ?? '' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
}

function deploy_and_test_get_deploy_status( $runs ) {
	$status = array(
		'preview'    => array(
			'workflow' => __( 'Deploy Preview', 'deploy-and-test' ),
			'latest'   => null,
			'active'   => null,
		),
		'production' => array(
			'workflow' => __( 'Deploy Production', 'deploy-and-test' ),
			'latest'   => null,
			'active'   => null,
		),
	);

	if ( is_wp_error( $runs ) || ! is_array( $runs ) ) {
		return $status;
	}

	foreach ( $runs as $run ) {
		$environment = deploy_and_test_get_run_environment( $run );

		if ( ! $environment ) {
			continue;
		}

		if ( ! $status[ $environment ]['latest'] ) {
			$status[ $environment ]['latest'] = $run;
		}

		if ( ! $status[ $environment ]['active'] && deploy_and_test_run_is_active( $run ) ) {
			$status[ $environment ]['active'] = $run;
		}
	}

	return $status;
}

function deploy_and_test_get_test_status( $runs ) {
	$status = array(
		'latest' => null,
		'active' => null,
	);

	if ( is_wp_error( $runs ) || ! is_array( $runs ) ) {
		return $status;
	}

	foreach ( $runs as $run ) {
		if ( ! $status['latest'] ) {
			$status['latest'] = $run;
		}

		if ( ! $status['active'] && deploy_and_test_run_is_active( $run ) ) {
			$status['active'] = $run;
		}
	}

	return $status;
}

function deploy_and_test_test_status_has_active_run( $test_status ) {
	return ! empty( $test_status['active'] );
}

function deploy_and_test_get_run_environment( $run ) {
	$name                     = $run['name'] ?? '';
	$preview_workflow_name    = deploy_and_test_workflow_label_from_file( deploy_and_test_get_setting( 'preview_workflow' ) );
	$production_workflow_name = deploy_and_test_workflow_label_from_file( deploy_and_test_get_setting( 'production_workflow' ) );

	if ( $name === $preview_workflow_name || stripos( $name, 'preview' ) !== false ) {
		return 'preview';
	}

	if ( $name === $production_workflow_name || stripos( $name, 'production' ) !== false ) {
		return 'production';
	}

	return '';
}

function deploy_and_test_workflow_label_from_file( $workflow_file ) {
	$basename = basename( (string) $workflow_file, '.yml' );
	$basename = basename( $basename, '.yaml' );

	return ucwords( str_replace( array( '-', '_' ), ' ', $basename ) );
}

function deploy_and_test_run_is_active( $run ) {
	return in_array( $run['status'] ?? '', array( 'queued', 'in_progress', 'waiting', 'pending' ), true );
}

function deploy_and_test_environment_has_active_run( $deploy_status, $environment ) {
	return ! empty( $deploy_status[ $environment ]['active'] );
}

function deploy_and_test_status_has_active_run( $deploy_status ) {
	return deploy_and_test_environment_has_active_run( $deploy_status, 'preview' ) || deploy_and_test_environment_has_active_run( $deploy_status, 'production' );
}

function deploy_and_test_get_run_state( $run ) {
	if ( deploy_and_test_run_is_active( $run ) ) {
		return $run['status'] === 'queued' ? 'queued' : 'running';
	}

	$conclusion = $run['conclusion'] ?? '';

	if ( $conclusion === 'success' ) {
		return 'success';
	}

	if ( in_array( $conclusion, array( 'failure', 'timed_out', 'cancelled' ), true ) ) {
		return 'failed';
	}

	return 'idle';
}

function deploy_and_test_get_run_state_label( $state ) {
	$labels = array(
		'queued'  => __( 'Queued', 'deploy-and-test' ),
		'running' => __( 'Running', 'deploy-and-test' ),
		'success' => __( 'Success', 'deploy-and-test' ),
		'failed'  => __( 'Failed', 'deploy-and-test' ),
		'idle'    => __( 'Idle', 'deploy-and-test' ),
	);

	return $labels[ $state ] ?? __( 'Unknown', 'deploy-and-test' );
}

function deploy_and_test_format_github_datetime( $datetime ) {
	if ( ! $datetime ) {
		return '-';
	}

	$timestamp = strtotime( (string) $datetime );

	if ( ! $timestamp ) {
		return (string) $datetime;
	}

	return wp_date( 'd-m-Y H:i:s', $timestamp );
}

function deploy_and_test_get_run_message( $run ) {
	$state = deploy_and_test_get_run_state( $run );

	if ( $state === 'queued' ) {
		return __( 'GitHub accepted the deploy and is waiting for a runner.', 'deploy-and-test' );
	}

	if ( $state === 'running' ) {
		return __( 'Build and deploy are currently running.', 'deploy-and-test' );
	}

	if ( $state === 'success' ) {
		return __( 'Deploy completed successfully.', 'deploy-and-test' );
	}

	if ( $state === 'failed' ) {
		return __( 'Deploy failed. Open GitHub logs, wait 2-3 minutes, then run deploy again.', 'deploy-and-test' );
	}

	return __( 'No active deploy is running.', 'deploy-and-test' );
}

function deploy_and_test_get_cached_test_summary( $run_id ) {
	$cache_key = deploy_and_test_test_summary_cache_key( $run_id );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) && $cached ) {
		return $cached;
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'ziparchive_missing', __( 'Server cannot read GitHub artifact archives because ZipArchive is not available. Open the GitHub run to view results.', 'deploy-and-test' ) );
	}

	$run = deploy_and_test_get_test_run_by_id( $run_id );

	if ( is_wp_error( $run ) ) {
		return $run;
	}

	if ( ! $run ) {
		return new WP_Error( 'test_run_not_found', __( 'The requested test run was not found in the configured testing repository.', 'deploy-and-test' ) );
	}

	if ( ! deploy_and_test_test_run_uses_configured_workflow( $run ) ) {
		return new WP_Error( 'test_run_not_configured', __( 'The requested test run does not belong to one of the configured test workflows.', 'deploy-and-test' ) );
	}

	$artifact = deploy_and_test_find_test_summary_artifact( $run_id, $run );

	if ( is_wp_error( $artifact ) ) {
		return $artifact;
	}

	$archive = deploy_and_test_github_download_artifact_archive( $artifact );

	if ( is_wp_error( $archive ) ) {
		return $archive;
	}

	$summary = deploy_and_test_extract_test_summary_artifact( $archive );

	if ( is_wp_error( $summary ) ) {
		return $summary;
	}

	set_transient( $cache_key, $summary, DEPLOY_AND_TEST_TEST_SUMMARY_CACHE_TTL );

	return $summary;
}

function deploy_and_test_test_summary_cache_key( $run_id ) {
	return 'deploy_and_test_test_summary_' . absint( $run_id );
}

function deploy_and_test_get_test_run_by_id( $run_id ) {
	$runs = deploy_and_test_github_get_recent_test_runs();

	if ( is_wp_error( $runs ) ) {
		return $runs;
	}

	foreach ( $runs as $run ) {
		if ( (int) ( $run['id'] ?? 0 ) === (int) $run_id ) {
			return ! empty( $run['path'] ) ? $run : deploy_and_test_github_get_run( deploy_and_test_get_setting( 'test_repo' ), $run_id );
		}
	}

	return deploy_and_test_github_get_run( deploy_and_test_get_setting( 'test_repo' ), $run_id );
}

function deploy_and_test_test_run_uses_configured_workflow( $run ) {
	$configured_workflows = deploy_and_test_get_configured_test_workflow_files();
	$run_workflow_file    = deploy_and_test_get_run_workflow_file( $run );

	return $run_workflow_file && in_array( $run_workflow_file, $configured_workflows, true );
}

function deploy_and_test_get_configured_test_workflow_files() {
	$workflow_files = array();

	foreach ( deploy_and_test_get_enabled_test_actions() as $test_action ) {
		if ( ! empty( $test_action['workflow'] ) ) {
			$workflow_files[] = basename( (string) $test_action['workflow'] );
		}
	}

	return array_values( array_unique( array_filter( $workflow_files ) ) );
}

function deploy_and_test_get_run_workflow_file( $run ) {
	$path = $run['path'] ?? '';

	if ( ! $path ) {
		return '';
	}

	return basename( (string) $path );
}

function deploy_and_test_find_test_summary_artifact( $run_id, $run = array() ) {
	$artifacts = deploy_and_test_github_get_run_artifacts( deploy_and_test_get_setting( 'test_repo' ), $run_id );

	if ( is_wp_error( $artifacts ) ) {
		return $artifacts;
	}

	$expected_name = deploy_and_test_expected_test_summary_artifact_name( $run );
	$fallback      = null;

	foreach ( $artifacts as $artifact ) {
		$name = $artifact['name'] ?? '';

		if ( strpos( $name, 'deploy-update-summary-' ) !== 0 || ( empty( $artifact['id'] ) && empty( $artifact['archive_download_url'] ) ) ) {
			continue;
		}

		if ( $expected_name && $name === $expected_name ) {
			return $artifact;
		}

		if ( ! $fallback ) {
			$fallback = $artifact;
		}
	}

	if ( $fallback ) {
		return $fallback;
	}

	return new WP_Error( 'summary_artifact_missing', __( 'No deploy-update-summary artifact found for this test run yet.', 'deploy-and-test' ) );
}

function deploy_and_test_expected_test_summary_artifact_name( $run ) {
	$title = $run['display_title'] ?? '';

	if ( ! $title ) {
		return '';
	}

	if ( ! preg_match( '/^Run\s+(.+?)\s+tests\s+on\s+(.+)$/i', $title, $matches ) ) {
		return '';
	}

	$suite      = sanitize_key( $matches[1] );
	$target_env = sanitize_key( $matches[2] );

	if ( ! $suite || ! $target_env ) {
		return '';
	}

	return 'deploy-update-summary-' . $suite . '-' . $target_env;
}

function deploy_and_test_extract_test_summary_artifact( $archive ) {
	$tmp = wp_tempnam( 'deploy-update-summary.zip' );

	if ( ! $tmp ) {
		return new WP_Error( 'temp_file_failed', __( 'Could not create a temporary file for the test summary artifact.', 'deploy-and-test' ) );
	}

	file_put_contents( $tmp, $archive );

	$zip = new ZipArchive();

	if ( $zip->open( $tmp ) !== true ) {
		@unlink( $tmp );
		return new WP_Error( 'zip_open_failed', __( 'Could not open the test summary artifact.', 'deploy-and-test' ) );
	}

	$summary_json = '';

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive exposes the public numFiles property.
	if ( $zip->numFiles > DEPLOY_AND_TEST_ARTIFACT_FILE_LIMIT ) {
		$zip->close();
		@unlink( $tmp );
		return new WP_Error( 'zip_too_many_files', __( 'The test summary artifact contains too many files to load in WordPress. Open the GitHub run to view the full report.', 'deploy-and-test' ) );
	}

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive exposes the public numFiles property.
	for ( $index = 0; $index < $zip->numFiles; ++$index ) {
		$name = $zip->getNameIndex( $index );

		if ( $name && basename( $name ) === 'deploy-update-summary.json' ) {
			$file_stat = $zip->statIndex( $index );

			if ( is_array( $file_stat ) && isset( $file_stat['size'] ) && (int) $file_stat['size'] > DEPLOY_AND_TEST_TEST_SUMMARY_LIMIT ) {
				$zip->close();
				@unlink( $tmp );
				return new WP_Error( 'summary_json_too_large', __( 'The deploy-update-summary.json file is too large to load in WordPress. Open the GitHub run to view the full report.', 'deploy-and-test' ) );
			}

			$summary_json = $zip->getFromIndex( $index );

			if ( is_string( $summary_json ) && strlen( $summary_json ) > DEPLOY_AND_TEST_TEST_SUMMARY_LIMIT ) {
				$zip->close();
				@unlink( $tmp );
				return new WP_Error( 'summary_json_too_large', __( 'The deploy-update-summary.json file is too large to load in WordPress. Open the GitHub run to view the full report.', 'deploy-and-test' ) );
			}

			break;
		}
	}

	$zip->close();
	@unlink( $tmp );

	if ( ! $summary_json ) {
		return new WP_Error( 'summary_json_missing', __( 'The deploy-update-summary artifact does not contain deploy-update-summary.json.', 'deploy-and-test' ) );
	}

	$summary = json_decode( $summary_json, true );

	if ( ! is_array( $summary ) ) {
		return new WP_Error( 'summary_json_invalid', __( 'The deploy-update-summary.json file is not valid JSON.', 'deploy-and-test' ) );
	}

	return $summary;
}

function deploy_and_test_render_test_summary_html( $summary ) {
	ob_start();
	?>
	<div class="deploy-and-test-test-summary">
		<div class="deploy-and-test-test-summary-stats">
			<?php deploy_and_test_render_test_summary_stat( __( 'Total', 'deploy-and-test' ), $summary['stats']['total'] ?? 0 ); ?>
			<?php deploy_and_test_render_test_summary_stat( __( 'Passed', 'deploy-and-test' ), $summary['stats']['passed'] ?? 0, 'success' ); ?>
			<?php deploy_and_test_render_test_summary_stat( __( 'Failed', 'deploy-and-test' ), $summary['stats']['failed'] ?? 0, 'failed' ); ?>
			<?php deploy_and_test_render_test_summary_stat( __( 'Skipped', 'deploy-and-test' ), $summary['stats']['skipped'] ?? 0 ); ?>
			<?php deploy_and_test_render_test_summary_stat( __( 'Timed out', 'deploy-and-test' ), $summary['stats']['timedOut'] ?? 0, 'failed' ); ?>
		</div>

		<p class="deploy-and-test-muted">
			<?php
			echo esc_html(
				sprintf(
				/* translators: 1: suite, 2: target environment, 3: browser. */
					__( 'Suite %1$s on %2$s using %3$s.', 'deploy-and-test' ),
					$summary['suite'] ?? '-',
					$summary['target_env'] ?? '-',
					$summary['browser'] ?? '-'
				)
			);
			?>
		</p>

		<?php deploy_and_test_render_test_summary_table( $summary['tests'] ?? array() ); ?>
	</div>
	<?php
	return ob_get_clean();
}

function deploy_and_test_render_test_summary_stat( $label, $value, $state = '' ) {
	$class = $state ? ' deploy-and-test-test-summary-stat-' . sanitize_html_class( $state ) : '';

	?>
	<div class="deploy-and-test-test-summary-stat<?php echo esc_attr( $class ); ?>">
		<strong><?php echo esc_html( (string) $value ); ?></strong>
		<span><?php echo esc_html( $label ); ?></span>
	</div>
	<?php
}

function deploy_and_test_render_test_summary_table( $tests ) {
	if ( ! $tests || ! is_array( $tests ) ) {
		echo '<p class="deploy-and-test-muted">' . esc_html__( 'No individual test results were included in the summary.', 'deploy-and-test' ) . '</p>';
		return;
	}

	?>
	<table class="widefat striped deploy-and-test-test-summary-table">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Status', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Project', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Test', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Duration', 'deploy-and-test' ); ?></th>
				<th><?php echo esc_html__( 'Error', 'deploy-and-test' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $tests as $test ) : ?>
				<tr>
					<td><?php echo esc_html( $test['status'] ?? '' ); ?></td>
					<td><?php echo esc_html( $test['project'] ?? '' ); ?></td>
					<td>
						<strong><?php echo esc_html( $test['title'] ?? '' ); ?></strong>
						<?php if ( ! empty( $test['file'] ) ) : ?>
							<br><span class="deploy-and-test-muted"><?php echo esc_html( $test['file'] . ':' . (string) ( $test['line'] ?? 0 ) ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( deploy_and_test_format_duration_ms( $test['durationMs'] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( $test['error'] ?? '' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

function deploy_and_test_format_duration_ms( $duration_ms ) {
	$duration_ms = (float) $duration_ms;

	if ( $duration_ms >= 1000 ) {
		return round( $duration_ms / 1000, 2 ) . 's';
	}

	return round( $duration_ms ) . 'ms';
}
