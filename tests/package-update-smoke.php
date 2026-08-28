<?php
/**
 * Disposable packaged-plugin update smoke test.
 */

$plugin_file          = 'deploy-and-test/deploy-and-test.php';
$fixture_directory    = WP_CONTENT_DIR . '/deploy-and-test-update-fixtures';
$expected_version     = trim( (string) file_get_contents( $fixture_directory . '/expected-version.txt' ) );
$current_package_path = $fixture_directory . '/deploy-and-test.zip';
$settings_marker      = 'packaged-update-preserved';

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

$before = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );

if ( ( $before['Version'] ?? '' ) !== '0.0.1' || ! is_plugin_active( $plugin_file ) ) {
	fwrite( STDERR, "The synthetic older plugin is not active.\n" );
	exit( 1 );
}

update_option(
	DEPLOY_AND_TEST_SETTINGS_OPTION,
	array(
		'owner' => $settings_marker,
	),
	false
);

$updates               = new stdClass();
$updates->last_checked = time();
$updates->checked      = array( $plugin_file => '0.0.1' );
$updates->response     = array(
	$plugin_file => (object) array(
		'id'          => DEPLOY_AND_TEST_UPDATE_URI,
		'slug'        => 'deploy-and-test',
		'plugin'      => $plugin_file,
		'new_version' => $expected_version,
		'url'         => DEPLOY_AND_TEST_UPDATE_URI,
		'package'     => $current_package_path,
	),
);
$updates->no_update    = array();
$updates->translations = array();

set_site_transient( 'update_plugins', $updates );

$skin     = new WP_Ajax_Upgrader_Skin();
$upgrader = new Plugin_Upgrader( $skin );
$results  = $upgrader->bulk_upgrade( array( $plugin_file ) );
$result   = is_array( $results ) ? ( $results[ $plugin_file ] ?? false ) : $results;

if ( is_wp_error( $result ) || ! $result || is_wp_error( $skin->result ) || $skin->get_errors()->has_errors() ) {
	$message = is_wp_error( $result ) ? $result->get_error_message() : $skin->get_error_messages();
	$message = $message ? $message : 'WordPress did not complete the plugin update.';
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

wp_clean_plugins_cache( true );
$after    = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
$settings = get_option( DEPLOY_AND_TEST_SETTINGS_OPTION, array() );

if ( ( $after['Version'] ?? '' ) !== $expected_version ) {
	fwrite( STDERR, "The installed plugin version does not match the current package.\n" );
	exit( 1 );
}

if ( ! is_plugin_active( $plugin_file ) ) {
	fwrite( STDERR, "The plugin is not active after the update.\n" );
	exit( 1 );
}

if ( ( $settings['owner'] ?? '' ) !== $settings_marker ) {
	fwrite( STDERR, "Plugin settings were not preserved during the update.\n" );
	exit( 1 );
}

echo "Packaged plugin update completed and preserved settings.\n";
