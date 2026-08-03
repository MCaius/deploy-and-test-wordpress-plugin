<?php
/**
 * Test action and locking tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Actions_Test extends Deploy_And_Test_Test_Case {
	public function test_unknown_test_environment_is_rejected_before_dispatch() {
		$this->configure_plugin();
		$_POST['test_environment'] = 'not-configured';
		$github_called             = false;
		$this->mock_github(
			function () use ( &$github_called ) {
				$github_called = true;
				return $this->http_response( 204 );
			}
		);

		$result = deploy_and_test_dispatch_test_action( 'smoke' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_test_environment', $result->get_error_code() );
		$this->assertFalse( $github_called );
	}

	public function test_configured_environment_is_sent_to_github() {
		$this->configure_plugin();
		$_POST['test_environment'] = 'preview';
		$dispatched_body           = array();
		$this->mock_github(
			function ( $url, $args ) use ( &$dispatched_body ) {
				if ( strpos( $url, '/dispatches' ) !== false ) {
					$dispatched_body = json_decode( $args['body'], true );
				}

				return $this->http_response( 204 );
			}
		);

		$result = deploy_and_test_dispatch_test_action( 'smoke' );

		$this->assertNotWPError( $result );
		$this->assertSame( 'main', $dispatched_body['ref'] );
		$this->assertSame( 'smoke', $dispatched_body['inputs']['suite'] );
		$this->assertSame( 'preview', $dispatched_body['inputs']['target_env'] );
	}

	public function test_recent_lock_blocks_a_duplicate_action_before_github_checks() {
		$this->configure_plugin();
		add_option( deploy_and_test_deploy_lock_key( 'global' ), time(), '', false );
		$github_called = false;
		$this->mock_github(
			function () use ( &$github_called ) {
				$github_called = true;
				return $this->http_response( 200, array() );
			}
		);

		$result = deploy_and_test_prevent_any_parallel_action();

		$this->assertWPError( $result );
		$this->assertSame( 'action_already_starting', $result->get_error_code() );
		$this->assertFalse( $github_called );
	}
}
