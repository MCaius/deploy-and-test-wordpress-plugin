<?php
/**
 * Admin action hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_deploy_and_test_action', 'deploy_and_test_handle_deploy_action' );
add_action( 'admin_post_deploy_and_test_save_settings', 'deploy_and_test_handle_save_settings' );
add_action( 'admin_post_deploy_and_test_save_cleanup_settings', 'deploy_and_test_handle_save_cleanup_settings' );
add_action( 'admin_post_deploy_and_test_test_connection', 'deploy_and_test_handle_test_connection' );
add_action( 'admin_post_deploy_and_test_test_testing_connection', 'deploy_and_test_handle_test_testing_connection' );
add_action( 'wp_ajax_deploy_and_test_status', 'deploy_and_test_handle_status_ajax' );
add_action( 'wp_ajax_deploy_and_test_test_status', 'deploy_and_test_handle_test_status_ajax' );
add_action( 'wp_ajax_deploy_and_test_test_summary', 'deploy_and_test_handle_test_summary_ajax' );
