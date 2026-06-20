<?php
/**
 * Clean up Deploy & Test data when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings_option          = 'deploy_and_test_settings';
$audit_option             = 'deploy_and_test_audit_log';
$settings                 = get_option( $settings_option, array() );
$delete_data_on_uninstall = true;

if ( is_array( $settings ) && array_key_exists( 'delete_data_on_uninstall', $settings ) ) {
	$delete_data_on_uninstall = ! empty( $settings['delete_data_on_uninstall'] );
}

if ( ! $delete_data_on_uninstall ) {
	return;
}

delete_option( $settings_option );
delete_option( $audit_option );

global $wpdb;

$option_names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'deploy_and_test_deploy_lock_' ) . '%',
		$wpdb->esc_like( '_transient_deploy_and_test_test_summary_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_deploy_and_test_test_summary_' ) . '%'
	)
);

foreach ( $option_names as $option_name ) {
	delete_option( $option_name );
}
