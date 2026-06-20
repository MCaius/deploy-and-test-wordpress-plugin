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

	$deploy_action    = isset( $_POST['deploy_action'] ) ? sanitize_key( wp_unslash( $_POST['deploy_action'] ) ) : '';
	$result           = new WP_Error( 'invalid_action', __( 'Unknown deploy action.', 'deploy-and-test' ) );
	$environment      = deploy_and_test_environment_from_action( $deploy_action );
	$lock_environment = $environment ? $environment : 'global';
	$active_check     = deploy_and_test_prevent_any_parallel_action( $environment );

	if ( is_wp_error( $active_check ) ) {
		deploy_and_test_add_audit_log( $deploy_action, 'blocked', $active_check->get_error_message() );
		deploy_and_test_redirect( 'error', $active_check->get_error_message() );
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
		deploy_and_test_redirect( 'error', $result->get_error_message() );
	}

	deploy_and_test_add_audit_log( $deploy_action, 'success', $result );
	deploy_and_test_redirect( 'success', $result, 'general', strpos( $deploy_action, 'test_' ) === 0 ? 'test' : 'deploy' );
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

function deploy_and_test_prevent_any_parallel_action( $environment = '' ) {
	$lock_environment = $environment ? $environment : 'global';

	if ( ! deploy_and_test_acquire_deploy_lock( $lock_environment ) ) {
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

	return true;
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

function deploy_and_test_acquire_deploy_lock( $environment ) {
	$lock_key      = deploy_and_test_deploy_lock_key( $environment );
	$now           = time();
	$existing_lock = (int) get_option( $lock_key, 0 );

	if ( $existing_lock && ( $now - $existing_lock ) < DEPLOY_AND_TEST_DEPLOY_LOCK_TTL ) {
		return false;
	}

	if ( $existing_lock ) {
		delete_option( $lock_key );
	}

	return add_option( $lock_key, $now, '', false );
}

function deploy_and_test_release_deploy_lock( $environment ) {
	delete_option( deploy_and_test_deploy_lock_key( $environment ) );
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
