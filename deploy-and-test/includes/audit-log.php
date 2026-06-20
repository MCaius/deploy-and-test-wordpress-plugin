<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_get_audit_log() {
	$logs = get_option( DEPLOY_AND_TEST_AUDIT_OPTION, array() );

	return is_array( $logs ) ? $logs : array();
}

function deploy_and_test_add_audit_log( $action, $status, $details ) {
	$user = wp_get_current_user();
	$logs = deploy_and_test_get_audit_log();

	array_unshift(
		$logs,
		array(
			'time'    => current_time( 'mysql' ),
			'user'    => $user && $user->exists() ? $user->user_login : 'unknown',
			'action'  => $action,
			'status'  => $status,
			'details' => $details,
		)
	);

	$logs = array_slice( $logs, 0, DEPLOY_AND_TEST_AUDIT_LIMIT );
	update_option( DEPLOY_AND_TEST_AUDIT_OPTION, $logs, false );
}
