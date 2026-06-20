<?php
/**
 * Admin menus and assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page(
			__( 'Deploy & Test', 'deploy-and-test' ),
			__( 'Deploy & Test', 'deploy-and-test' ),
			deploy_and_test_capability(),
			'deploy-and-test',
			'deploy_and_test_render_admin_page',
			'dashicons-update',
			99999
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		$css_path            = DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/deploy-and-test-admin.css';
		$js_path             = DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/deploy-and-test-admin.js';
		$how_to_use_css_path = DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/how-to-use-page/how-to-use-page.css';

		wp_enqueue_style(
			'deploy-and-test-admin',
			DEPLOY_AND_TEST_PLUGIN_URL . 'includes/deploy-and-test-admin.css',
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : null
		);

		wp_enqueue_style(
			'deploy-and-test-how-to-use',
			DEPLOY_AND_TEST_PLUGIN_URL . 'includes/how-to-use-page/how-to-use-page.css',
			array( 'deploy-and-test-admin' ),
			file_exists( $how_to_use_css_path ) ? filemtime( $how_to_use_css_path ) : null
		);

		wp_enqueue_script(
			'deploy-and-test-admin',
			DEPLOY_AND_TEST_PLUGIN_URL . 'includes/deploy-and-test-admin.js',
			array(),
			file_exists( $js_path ) ? filemtime( $js_path ) : null,
			true
		);

		wp_localize_script(
			'deploy-and-test-admin',
			'deployAndTest',
			array(
				'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
				'nonce'                  => wp_create_nonce( 'deploy_and_test_status' ),
				'pollInterval'           => 5000,
				'actionStartingText'     => __( 'Starting...', 'deploy-and-test' ),
				'loadSummaryText'        => __( 'Load test summary', 'deploy-and-test' ),
				'loadingSummaryText'     => __( 'Loading summary...', 'deploy-and-test' ),
				'loadingSummaryHint'     => __( 'Downloading test summary from GitHub. This can take a minute.', 'deploy-and-test' ),
				'summaryUnavailableText' => __( 'Test summary is available after the GitHub Actions run finishes.', 'deploy-and-test' ),
			)
		);
	}
);
