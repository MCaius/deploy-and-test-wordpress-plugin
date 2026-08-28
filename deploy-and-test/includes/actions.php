<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_handle_deploy_action() {
	if ( ! current_user_can( deploy_and_test_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to run deploy actions.', 'deploy-and-test' ) );
	}

	check_admin_referer( 'deploy_and_test_action', 'deploy_and_test_nonce' );

	$deploy_action = isset( $_POST['deploy_action'] ) ? sanitize_key( wp_unslash( $_POST['deploy_action'] ) ) : '';
	$result        = deploy_and_test_execute_action( $deploy_action );

	if ( is_wp_error( $result ) ) {
		deploy_and_test_redirect( 'error', $result->get_error_message() );
	}

	deploy_and_test_redirect( 'success', deploy_and_test_action_success_message( $deploy_action ), 'general', strpos( $deploy_action, 'test_' ) === 0 ? 'test' : 'deploy', true );
}

function deploy_and_test_execute_action( $deploy_action ) {
	$result           = new WP_Error( 'invalid_action', __( 'Unknown deploy action.', 'deploy-and-test' ) );
	$lock_environment = 'global';
	$lock_context     = deploy_and_test_action_lock_context( $deploy_action );
	$environment      = deploy_and_test_environment_from_action( $deploy_action );

	if ( $environment && ! deploy_and_test_deploy_environment_is_configured( $environment ) ) {
		$result = new WP_Error( 'missing_deploy_config', __( 'The requested deployment workflow is not configured.', 'deploy-and-test' ) );
		deploy_and_test_add_audit_log( $deploy_action, 'failed', $result->get_error_message() );
		return $result;
	}

	$active_check = deploy_and_test_prevent_any_parallel_action( $lock_context );

	if ( is_wp_error( $active_check ) ) {
		deploy_and_test_add_audit_log( $deploy_action, 'blocked', $active_check->get_error_message() );
		return $active_check;
	}

	if ( $deploy_action === 'deploy_preview' ) {
		$result = deploy_and_test_github_dispatch_workflow( deploy_and_test_get_setting( 'preview_workflow' ) );
	} elseif ( $deploy_action === 'deploy_production' ) {
		$result = deploy_and_test_github_dispatch_workflow( deploy_and_test_get_setting( 'production_workflow' ) );
	} elseif ( strpos( $deploy_action, 'test_' ) === 0 ) {
		$result = deploy_and_test_dispatch_test_action( substr( $deploy_action, 5 ) );
	}

	if ( is_wp_error( $result ) ) {
		deploy_and_test_release_deploy_lock( $lock_environment );

		deploy_and_test_add_audit_log( $deploy_action, 'failed', $result->get_error_message() );
		return $result;
	}

	deploy_and_test_add_audit_log( $deploy_action, 'success', $result );

	return $result;
}

function deploy_and_test_action_success_message( $deploy_action ) {
	if ( $deploy_action === 'deploy_preview' ) {
		return __( 'Preview deploy started.', 'deploy-and-test' );
	}

	if ( $deploy_action === 'deploy_production' ) {
		return __( 'Production deploy started.', 'deploy-and-test' );
	}

	if ( strpos( $deploy_action, 'test_' ) === 0 ) {
		$test_action = deploy_and_test_get_test_action( substr( $deploy_action, 5 ) );

		if ( $test_action && ! empty( $test_action['label'] ) ) {
			return sprintf(
				/* translators: %s: configured test action label. */
				__( 'Test started: %s.', 'deploy-and-test' ),
				$test_action['label']
			);
		}

		return __( 'Test workflow started.', 'deploy-and-test' );
	}

	return __( 'Workflow started.', 'deploy-and-test' );
}

function deploy_and_test_dispatch_test_action( $test_action_id ) {
	if ( ! deploy_and_test_tests_are_configured() ) {
		return new WP_Error( 'missing_test_config', __( 'Testing repository and test action settings are not fully configured.', 'deploy-and-test' ) );
	}

	$test_action = deploy_and_test_get_test_action( $test_action_id );

	if ( ! $test_action ) {
		return new WP_Error( 'unknown_test_action', __( 'Unknown test action.', 'deploy-and-test' ) );
	}

	$inputs = array();

	if ( ! empty( $test_action['input_name'] ) && $test_action['input_value'] !== '' ) {
		$inputs[ $test_action['input_name'] ] = $test_action['input_value'];
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The deploy_and_test_action nonce is verified by deploy_and_test_handle_deploy_action().
	$test_environment_value = isset( $_POST['test_environment'] ) ? sanitize_text_field( wp_unslash( $_POST['test_environment'] ) ) : '';
	$test_environment       = $test_environment_value !== '' ? deploy_and_test_get_test_environment( $test_environment_value ) : null;
	$test_environment_input = deploy_and_test_get_setting( 'test_environment_input' ) ? deploy_and_test_get_setting( 'test_environment_input' ) : 'target_env';

	if ( $test_environment_value !== '' && ! $test_environment ) {
		return new WP_Error( 'invalid_test_environment', __( 'Unknown test environment.', 'deploy-and-test' ) );
	}

	if ( $test_environment && $test_environment_input ) {
		$inputs[ $test_environment_input ] = $test_environment['value'];
	}

	return deploy_and_test_github_dispatch_workflow(
		$test_action['workflow'],
		deploy_and_test_get_setting( 'test_repo' ),
		deploy_and_test_get_setting( 'test_ref' ),
		$inputs
	);
}

function deploy_and_test_environment_from_action( $deploy_action ) {
	if ( $deploy_action === 'deploy_preview' ) {
		return 'preview';
	}

	if ( $deploy_action === 'deploy_production' ) {
		return 'production';
	}

	return '';
}

function deploy_and_test_action_lock_context( $deploy_action ) {
	$environment = deploy_and_test_environment_from_action( $deploy_action );

	if ( $environment ) {
		return array(
			'type'        => 'deploy',
			'environment' => $environment,
			'workflow'    => deploy_and_test_get_setting( $environment . '_workflow' ),
		);
	}

	if ( strpos( $deploy_action, 'test_' ) === 0 ) {
		$test_action = deploy_and_test_get_test_action( substr( $deploy_action, 5 ) );

		if ( $test_action && ! empty( $test_action['workflow'] ) ) {
			return array(
				'type'     => 'test',
				'workflow' => $test_action['workflow'],
			);
		}
	}

	return array();
}

function deploy_and_test_prevent_any_parallel_action( $lock_context = array() ) {
	$lock_environment = 'global';

	if ( ! deploy_and_test_acquire_deploy_lock( $lock_environment, $lock_context ) ) {
		return new WP_Error( 'action_already_starting', __( 'A workflow was started recently. Wait a moment, then try again.', 'deploy-and-test' ) );
	}

	$deploy_runs = deploy_and_test_is_configured() ? deploy_and_test_github_get_recent_runs() : array();

	if ( is_wp_error( $deploy_runs ) ) {
		deploy_and_test_release_deploy_lock( $lock_environment );
		return $deploy_runs;
	}

	$deploy_status = deploy_and_test_get_deploy_status( $deploy_runs );

	if ( deploy_and_test_status_has_active_run( $deploy_status ) ) {
		deploy_and_test_release_deploy_lock( $lock_environment );
		return new WP_Error( 'workflow_already_running', __( 'A deploy workflow is already queued or running. Wait for it to finish before starting another action.', 'deploy-and-test' ) );
	}

	if ( deploy_and_test_tests_are_configured() ) {
		$test_runs = deploy_and_test_github_get_recent_test_runs();

		if ( is_wp_error( $test_runs ) ) {
			deploy_and_test_release_deploy_lock( $lock_environment );
			return $test_runs;
		}

		$test_status = deploy_and_test_get_test_status( $test_runs );

		if ( deploy_and_test_test_status_has_active_run( $test_status ) ) {
			deploy_and_test_release_deploy_lock( $lock_environment );
			return new WP_Error( 'workflow_already_running', __( 'A test workflow is already queued or running. Wait for it to finish before starting another action.', 'deploy-and-test' ) );
		}
	}

	deploy_and_test_update_startup_lock_baseline( $lock_context, $deploy_runs, isset( $test_runs ) ? $test_runs : array() );

	return true;
}

function deploy_and_test_update_startup_lock_baseline( $lock_context, $deploy_runs, $test_runs ) {
	if ( empty( $lock_context['type'] ) || empty( $lock_context['workflow'] ) ) {
		return;
	}

	$lock = deploy_and_test_get_deploy_lock( 'global' );

	if ( ! is_array( $lock ) || ( $lock['lock_id'] ?? '' ) === '' ) {
		return;
	}

	$runs       = $lock_context['type'] === 'test' ? $test_runs : $deploy_runs;
	$latest_run = deploy_and_test_get_latest_startup_lock_run( $runs, $lock_context );

	$lock['baseline_run_id'] = (string) ( $latest_run['id'] ?? '' );
	update_option( deploy_and_test_deploy_lock_key( 'global' ), $lock, false );
}

function deploy_and_test_reconcile_startup_lock( $runs, $type ) {
	$lock = deploy_and_test_get_deploy_lock( 'global' );

	if ( ! is_array( $lock ) || ( $lock['type'] ?? '' ) !== $type || ! array_key_exists( 'baseline_run_id', $lock ) ) {
		return false;
	}

	$latest_run = deploy_and_test_get_latest_startup_lock_run( $runs, $lock );

	if ( ! $latest_run || (string) ( $latest_run['id'] ?? '' ) === (string) $lock['baseline_run_id'] ) {
		return false;
	}

	$created_at = ! empty( $latest_run['created_at'] ) ? strtotime( $latest_run['created_at'] ) : false;
	$started_at = isset( $lock['started_at'] ) ? (int) $lock['started_at'] : 0;

	if ( ! $created_at || ! $started_at || $created_at < ( $started_at - 10 ) ) {
		return false;
	}

	return deploy_and_test_release_deploy_lock( 'global', $lock );
}

function deploy_and_test_get_latest_startup_lock_run( $runs, $lock ) {
	if ( ! is_array( $runs ) ) {
		return null;
	}

	foreach ( $runs as $run ) {
		if ( deploy_and_test_run_matches_startup_lock( $run, $lock ) ) {
			return $run;
		}
	}

	return null;
}

function deploy_and_test_run_matches_startup_lock( $run, $lock ) {
	$workflow = (string) ( $lock['workflow'] ?? '' );

	if ( $workflow === '' ) {
		return false;
	}

	if ( ctype_digit( $workflow ) && (string) ( $run['workflow_id'] ?? '' ) === $workflow ) {
		return true;
	}

	$run_path_parts = explode( '@', (string) ( $run['path'] ?? '' ), 2 );
	$workflow_parts = explode( '@', $workflow, 2 );

	if ( $run_path_parts[0] !== '' && basename( $run_path_parts[0] ) === basename( $workflow_parts[0] ) ) {
		return true;
	}

	return ( $lock['type'] ?? '' ) === 'deploy'
		&& ! empty( $lock['environment'] )
		&& deploy_and_test_get_run_environment( $run ) === $lock['environment'];
}

function deploy_and_test_prevent_duplicate_deploy( $environment ) {
	if ( ! deploy_and_test_acquire_deploy_lock( $environment ) ) {
		return new WP_Error( 'deploy_already_starting', __( 'A deploy for this environment was started recently. Wait 2-3 minutes, then try again.', 'deploy-and-test' ) );
	}

	$runs = deploy_and_test_github_get_recent_runs();

	if ( is_wp_error( $runs ) ) {
		deploy_and_test_release_deploy_lock( $environment );
		return $runs;
	}

	$deploy_status = deploy_and_test_get_deploy_status( $runs );

	if ( deploy_and_test_environment_has_active_run( $deploy_status, $environment ) ) {
		deploy_and_test_release_deploy_lock( $environment );
		return new WP_Error( 'deploy_already_running', __( 'A deploy for this environment is already queued or running.', 'deploy-and-test' ) );
	}

	return true;
}

function deploy_and_test_acquire_deploy_lock( $environment, $context = array() ) {
	$lock_key      = deploy_and_test_deploy_lock_key( $environment );
	$now           = time();
	$existing_lock = deploy_and_test_get_deploy_lock( $environment );
	$lock_time     = is_array( $existing_lock ) ? (int) ( $existing_lock['started_at'] ?? 0 ) : (int) $existing_lock;

	if ( $lock_time && ( $now - $lock_time ) < DEPLOY_AND_TEST_DEPLOY_LOCK_TTL ) {
		return false;
	}

	if ( $existing_lock ) {
		deploy_and_test_release_deploy_lock( $environment, $existing_lock );
	}

	$lock = array_merge(
		array(
			'lock_id'    => wp_generate_uuid4(),
			'started_at' => $now,
		),
		$context
	);

	return add_option( $lock_key, $lock, '', false );
}

function deploy_and_test_get_deploy_lock( $environment ) {
	return get_option( deploy_and_test_deploy_lock_key( $environment ), 0 );
}

function deploy_and_test_release_deploy_lock( $environment, $expected_lock = null ) {
	$lock_key = deploy_and_test_deploy_lock_key( $environment );

	if ( $expected_lock === null ) {
		return delete_option( $lock_key );
	}

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The exact option value is required for an atomic compare-and-delete lock release; the option cache is cleared below.
	$deleted = $wpdb->delete(
		$wpdb->options,
		array(
			'option_name'  => $lock_key,
			'option_value' => maybe_serialize( $expected_lock ),
		),
		array( '%s', '%s' )
	);

	if ( $deleted === 1 ) {
		wp_cache_delete( $lock_key, 'options' );
		return true;
	}

	return false;
}

function deploy_and_test_deploy_lock_key( $environment ) {
	return 'deploy_and_test_deploy_lock_' . sanitize_key( $environment );
}

function deploy_and_test_handle_test_connection() {
	if ( ! current_user_can( deploy_and_test_settings_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to test the GitHub connection.', 'deploy-and-test' ) );
	}

	check_admin_referer( 'deploy_and_test_test_connection', 'deploy_and_test_nonce' );

	$result = deploy_and_test_github_test_connection();

	if ( is_wp_error( $result ) ) {
		deploy_and_test_add_audit_log( 'test_connection', 'failed', $result->get_error_message() );
		deploy_and_test_redirect( 'error', $result->get_error_message(), 'connection' );
	}

	deploy_and_test_add_audit_log( 'test_connection', 'success', $result );
	deploy_and_test_redirect( 'success', $result, 'connection' );
}

function deploy_and_test_handle_test_testing_connection() {
	if ( ! current_user_can( deploy_and_test_settings_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to test the GitHub connection.', 'deploy-and-test' ) );
	}

	check_admin_referer( 'deploy_and_test_test_testing_connection', 'deploy_and_test_nonce' );

	$result = deploy_and_test_github_test_testing_connection();

	if ( is_wp_error( $result ) ) {
		deploy_and_test_add_audit_log( 'test_testing_connection', 'failed', $result->get_error_message() );
		deploy_and_test_redirect( 'error', $result->get_error_message(), 'connection' );
	}

	deploy_and_test_add_audit_log( 'test_testing_connection', 'success', $result );
	deploy_and_test_redirect( 'success', $result, 'connection' );
}

function deploy_and_test_handle_status_ajax() {
	if ( ! current_user_can( deploy_and_test_capability() ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to view deploy status.', 'deploy-and-test' ),
			),
			403
		);
	}

	check_ajax_referer( 'deploy_and_test_status', 'nonce' );

	$configured    = deploy_and_test_is_configured();
	$runs          = $configured ? deploy_and_test_github_get_recent_runs() : new WP_Error( 'missing_config', __( 'Deploy & Test is not fully configured.', 'deploy-and-test' ) );
	deploy_and_test_reconcile_startup_lock( $runs, 'deploy' );
	$deploy_status = deploy_and_test_get_deploy_status( $runs );

	ob_start();
	deploy_and_test_render_status_panel( $runs, $configured );
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'html'         => $html,
			'hasActiveRun' => deploy_and_test_status_has_active_run( $deploy_status ),
		)
	);
}

function deploy_and_test_handle_test_status_ajax() {
	if ( ! current_user_can( deploy_and_test_capability() ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to view test status.', 'deploy-and-test' ),
			),
			403
		);
	}

	check_ajax_referer( 'deploy_and_test_status', 'nonce' );

	$configured  = deploy_and_test_tests_are_configured();
	$runs        = $configured ? deploy_and_test_github_get_recent_test_runs() : new WP_Error( 'missing_config', __( 'Testing repository is not fully configured.', 'deploy-and-test' ) );
	deploy_and_test_reconcile_startup_lock( $runs, 'test' );
	$test_status = deploy_and_test_get_test_status( $runs );

	ob_start();
	deploy_and_test_render_test_status_panel( $runs, $configured );
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'html'         => $html,
			'hasActiveRun' => deploy_and_test_test_status_has_active_run( $test_status ),
		)
	);
}

function deploy_and_test_handle_test_summary_ajax() {
	if ( ! current_user_can( deploy_and_test_capability() ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to view test summary.', 'deploy-and-test' ),
			),
			403
		);
	}

	check_ajax_referer( 'deploy_and_test_status', 'nonce' );

	$run_id = isset( $_POST['run_id'] ) ? absint( $_POST['run_id'] ) : 0;

	if ( ! $run_id ) {
		wp_send_json_error(
			array(
				'message' => __( 'Missing test run ID.', 'deploy-and-test' ),
			),
			400
		);
	}

	$summary = deploy_and_test_get_cached_test_summary( $run_id );

	if ( is_wp_error( $summary ) ) {
		wp_send_json_error(
			array(
				'message' => $summary->get_error_message(),
			),
			500
		);
	}

	wp_send_json_success(
		array(
			'html' => deploy_and_test_render_test_summary_html( $summary ),
		)
	);
}
