<?php
/**
 * Privileged handler security tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Handler_Security_WP_Die_Exception extends RuntimeException {}

class Deploy_And_Test_Handler_Security_Test extends Deploy_And_Test_Test_Case {
	private $die_handler_filter;

	public function set_up() {
		parent::set_up();

		$this->die_handler_filter = function () {
			return function ( $message ) {
				$message = is_scalar( $message ) ? wp_strip_all_tags( (string) $message ) : '';

				throw new Deploy_And_Test_Handler_Security_WP_Die_Exception( $message );
			};
		};

		add_filter( 'wp_die_handler', $this->die_handler_filter );
		add_filter( 'wp_die_ajax_handler', $this->die_handler_filter );
	}

	public function tear_down() {
		remove_filter( 'wp_die_handler', $this->die_handler_filter );
		remove_filter( 'wp_die_ajax_handler', $this->die_handler_filter );

		unset( $_POST['action'], $_POST['nonce'], $_POST['run_id'] );
		unset( $_REQUEST['nonce'] );

		parent::tear_down();
	}

	/**
	 * @dataProvider unauthorized_post_handler_provider
	 */
	public function test_post_handlers_reject_unauthorized_users( $handler, $role, $nonce_action, $expected_message ) {
		$user_id = $role ? self::factory()->user->create( array( 'role' => $role ) ) : 0;
		wp_set_current_user( $user_id );

		$_POST['deploy_action']           = 'deploy_preview';
		$_POST['deploy_and_test_nonce']   = wp_create_nonce( $nonce_action );
		$_REQUEST['deploy_and_test_nonce'] = $_POST['deploy_and_test_nonce'];

		$exception = $this->capture_wp_die( $handler );

		$this->assertStringContainsString( $expected_message, $exception->getMessage() );
	}

	public function unauthorized_post_handler_provider() {
		return array(
			'unauthenticated deploy action'          => array( 'deploy_and_test_handle_deploy_action', '', 'deploy_and_test_action', 'permission to run deploy actions' ),
			'subscriber deploy action'               => array( 'deploy_and_test_handle_deploy_action', 'subscriber', 'deploy_and_test_action', 'permission to run deploy actions' ),
			'unauthenticated settings save'          => array( 'deploy_and_test_handle_save_settings', '', 'deploy_and_test_save_settings', 'permission to update deploy settings' ),
			'editor settings save'                   => array( 'deploy_and_test_handle_save_settings', 'editor', 'deploy_and_test_save_settings', 'permission to update deploy settings' ),
			'unauthenticated cleanup save'           => array( 'deploy_and_test_handle_save_cleanup_settings', '', 'deploy_and_test_save_cleanup_settings', 'permission to update deploy settings' ),
			'editor cleanup save'                    => array( 'deploy_and_test_handle_save_cleanup_settings', 'editor', 'deploy_and_test_save_cleanup_settings', 'permission to update deploy settings' ),
			'unauthenticated deploy connection test' => array( 'deploy_and_test_handle_test_connection', '', 'deploy_and_test_test_connection', 'permission to test the GitHub connection' ),
			'editor deploy connection test'          => array( 'deploy_and_test_handle_test_connection', 'editor', 'deploy_and_test_test_connection', 'permission to test the GitHub connection' ),
			'unauthenticated test connection test'   => array( 'deploy_and_test_handle_test_testing_connection', '', 'deploy_and_test_test_testing_connection', 'permission to test the GitHub connection' ),
			'editor test connection test'            => array( 'deploy_and_test_handle_test_testing_connection', 'editor', 'deploy_and_test_test_testing_connection', 'permission to test the GitHub connection' ),
		);
	}

	/**
	 * @dataProvider protected_post_handler_provider
	 */
	public function test_post_handlers_reject_invalid_nonces( $handler, $role ) {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		$_POST['deploy_action']           = 'deploy_preview';
		$_POST['deploy_and_test_nonce']   = 'invalid-security-test-nonce';
		$_REQUEST['deploy_and_test_nonce'] = 'invalid-security-test-nonce';

		$exception = $this->capture_wp_die( $handler );

		$this->assertStringContainsString( 'link you followed has expired', strtolower( $exception->getMessage() ) );
	}

	public function protected_post_handler_provider() {
		return array(
			'deploy action'           => array( 'deploy_and_test_handle_deploy_action', 'editor' ),
			'settings save'           => array( 'deploy_and_test_handle_save_settings', 'administrator' ),
			'cleanup save'            => array( 'deploy_and_test_handle_save_cleanup_settings', 'administrator' ),
			'deploy connection test'  => array( 'deploy_and_test_handle_test_connection', 'administrator' ),
			'testing connection test' => array( 'deploy_and_test_handle_test_testing_connection', 'administrator' ),
		);
	}

	/**
	 * @dataProvider ajax_handler_provider
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_handlers_reject_unauthorized_users( $handler ) {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$output = '';
		$this->capture_wp_die( $handler, $output );
		$response = json_decode( $output, true );

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'permission', strtolower( $response['data']['message'] ) );
	}

	/**
	 * @dataProvider ajax_handler_provider
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_handlers_reject_invalid_nonces( $handler ) {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$_POST['nonce']    = 'invalid-security-test-nonce';
		$_REQUEST['nonce'] = 'invalid-security-test-nonce';

		$exception = $this->capture_wp_die( $handler );

		$this->assertSame( '-1', $exception->getMessage() );
	}

	public function ajax_handler_provider() {
		return array(
			'deploy status' => array( 'deploy_and_test_handle_status_ajax' ),
			'test status'   => array( 'deploy_and_test_handle_test_status_ajax' ),
			'test summary'  => array( 'deploy_and_test_handle_test_summary_ajax' ),
		);
	}

	/**
	 * @dataProvider authorized_status_ajax_provider
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_authorized_status_ajax_returns_only_rendered_status_data( $handler ) {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$_POST['nonce']    = wp_create_nonce( 'deploy_and_test_status' );
		$_REQUEST['nonce'] = $_POST['nonce'];

		$output = '';
		$this->capture_wp_die( $handler, $output );
		$response = json_decode( $output, true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'html', $response['data'] );
		$this->assertFalse( $response['data']['hasActiveRun'] );
	}

	public function authorized_status_ajax_provider() {
		return array(
			'deploy status' => array( 'deploy_and_test_handle_status_ajax' ),
			'test status'   => array( 'deploy_and_test_handle_test_status_ajax' ),
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_authorized_summary_ajax_rejects_a_missing_run_id() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$_POST['nonce']    = wp_create_nonce( 'deploy_and_test_status' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['run_id']   = 0;

		$output = '';
		$this->capture_wp_die( 'deploy_and_test_handle_test_summary_ajax', $output );
		$response = json_decode( $output, true );

		$this->assertIsArray( $response );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Missing test run ID.', $response['data']['message'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_authorized_summary_ajax_returns_only_rendered_summary_html() {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		set_transient(
			deploy_and_test_test_summary_cache_key( 123 ),
			array(
				'stats' => array(
					'total'    => 1,
					'passed'   => 1,
					'failed'   => 0,
					'skipped'  => 0,
					'timedOut' => 0,
				),
			),
			MINUTE_IN_SECONDS
		);

		$_POST['nonce']    = wp_create_nonce( 'deploy_and_test_status' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['run_id']   = 123;

		$output = '';
		$this->capture_wp_die( 'deploy_and_test_handle_test_summary_ajax', $output );
		$response = json_decode( $output, true );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'html', $response['data'] );
		$this->assertStringContainsString( 'deploy-and-test-test-summary', $response['data']['html'] );
	}

	private function capture_wp_die( $callback, &$output = null ) {
		$exception = null;
		ob_start();

		try {
			call_user_func( $callback );
		} catch ( Deploy_And_Test_Handler_Security_WP_Die_Exception $caught ) {
			$exception = $caught;
		} finally {
			$output = ob_get_clean();
		}

		$this->assertInstanceOf( Deploy_And_Test_Handler_Security_WP_Die_Exception::class, $exception, 'The protected handler returned without terminating the request.' );

		return $exception;
	}
}
