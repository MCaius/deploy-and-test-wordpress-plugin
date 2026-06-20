<?php
/**
 * Plugin Name: Deploy & Test
 * Description: Trigger GitHub Actions deploy workflows from the WordPress admin using a GitHub App.
 * Version: 1.0.0
 * Author: Deploy & Test
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: deploy-and-test
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DEPLOY_AND_TEST_PLUGIN_FILE', __FILE__ );
define( 'DEPLOY_AND_TEST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEPLOY_AND_TEST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'deploy-and-test', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

register_activation_hook(
	__FILE__,
	function () {
		$settings = get_option( 'deploy_and_test_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! array_key_exists( 'delete_data_on_uninstall', $settings ) ) {
			$settings['delete_data_on_uninstall'] = true;
		}

		if ( ! array_key_exists( 'delete_data_on_uninstall_touched', $settings ) ) {
			$settings['delete_data_on_uninstall_touched'] = false;
		}

		update_option( 'deploy_and_test_settings', $settings, false );
	}
);

require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/deploy-and-test-admin.php';
