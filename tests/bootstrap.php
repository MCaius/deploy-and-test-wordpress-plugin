<?php
/**
 * PHPUnit bootstrap for the WordPress integration test environment.
 */

$deploy_and_test_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $deploy_and_test_tests_dir ) {
	$deploy_and_test_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $deploy_and_test_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test functions were not found. Run the suite through wp-env.\n" );
	exit( 1 );
}

require_once $deploy_and_test_tests_dir . '/includes/functions.php';

$deploy_and_test_polyfills_autoload = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

if ( ! file_exists( $deploy_and_test_polyfills_autoload ) ) {
	fwrite( STDERR, "Composer dependencies were not found. Run composer install before the PHPUnit suite.\n" );
	exit( 1 );
}

require_once $deploy_and_test_polyfills_autoload;

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

function deploy_and_test_tests_load_plugin() {
	if ( ! defined( 'DEPLOY_AND_TEST_GITHUB_APP_ID' ) ) {
		define( 'DEPLOY_AND_TEST_GITHUB_APP_ID', 'test-app-id' );
	}

	if ( ! defined( 'DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID' ) ) {
		define( 'DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID', 'test-installation-id' );
	}

	if ( ! defined( 'DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH' ) && ! defined( 'DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY' ) ) {
		$key_resource = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		$private_key  = '';

		if ( $key_resource ) {
			openssl_pkey_export( $key_resource, $private_key );
		}

		define( 'DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY', $private_key );
	}

	$plugin_file = dirname( __DIR__ ) . '/deploy-and-test.php';

	if ( ! file_exists( $plugin_file ) ) {
		$plugin_file = dirname( __DIR__ ) . '/deploy-and-test/deploy-and-test.php';
	}

	require $plugin_file;
}

tests_add_filter( 'muplugins_loaded', 'deploy_and_test_tests_load_plugin' );

require $deploy_and_test_tests_dir . '/includes/bootstrap.php';
