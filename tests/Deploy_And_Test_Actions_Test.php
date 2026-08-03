<?php
/**
 * Test action and locking tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_WP_Die_Exception extends RuntimeException {}

class Deploy_And_Test_Actions_Test extends Deploy_And_Test_Test_Case {
	public function test_deploy_handler_rejects_an_invalid_nonce() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$_POST['deploy_action']           = 'deploy_preview';
		$_POST['deploy_and_test_nonce']   = 'invalid-nonce';
		$_REQUEST['deploy_and_test_nonce'] = 'invalid-nonce';
		$die_handler_filter               = function () {
			return function ( $message ) {
				throw new Deploy_And_Test_WP_Die_Exception( wp_strip_all_tags( $message ) );
			};
		};
		add_filter( 'wp_die_handler', $die_handler_filter );

		try {
			deploy_and_test_handle_deploy_action();
			$this->fail( 'The handler accepted an invalid nonce.' );
		} catch ( Deploy_And_Test_WP_Die_Exception $exception ) {
			$this->assertStringContainsString( 'link you followed has expired', strtolower( $exception->getMessage() ) );
		} finally {
			remove_filter( 'wp_die_handler', $die_handler_filter );
		}
	}

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

	public function test_failed_dispatch_is_audited_and_releases_the_lock() {
		$this->configure_plugin();
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'qa-failure-admin',
			)
		);
		wp_set_current_user( $user_id );
		$this->mock_github(
			function ( $url ) {
				if ( strpos( $url, '/actions/runs?' ) !== false ) {
					return $this->http_response( 200, array( 'workflow_runs' => array() ) );
				}

				return $this->http_response( 503, array( 'message' => 'Controlled dispatch failure' ) );
			}
		);

		$result = deploy_and_test_execute_action( 'deploy_preview' );
		$logs   = deploy_and_test_get_audit_log();

		$this->assertWPError( $result );
		$this->assertSame( 'github_api_error', $result->get_error_code() );
		$this->assertFalse( get_option( deploy_and_test_deploy_lock_key( 'preview' ), false ) );
		$this->assertCount( 1, $logs );
		$this->assertSame( 'deploy_preview', $logs[0]['action'] );
		$this->assertSame( 'failed', $logs[0]['status'] );
		$this->assertSame( 'qa-failure-admin', $logs[0]['user'] );
		$this->assertStringContainsString( 'Controlled dispatch failure HTTP 503.', $logs[0]['details'] );
		$this->assertStringNotContainsString( 'test-installation-token', $logs[0]['details'] );
	}

	public function test_duplicate_action_is_blocked_and_audited_without_github_request() {
		$this->configure_plugin();
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'editor',
				'user_login' => 'qa-blocked-editor',
			)
		);
		wp_set_current_user( $user_id );
		add_option( deploy_and_test_deploy_lock_key( 'global' ), time(), '', false );
		$github_called = false;
		$this->mock_github(
			function () use ( &$github_called ) {
				$github_called = true;
				return $this->http_response( 200, array() );
			}
		);

		$result = deploy_and_test_execute_action( 'test_smoke' );
		$logs   = deploy_and_test_get_audit_log();

		$this->assertWPError( $result );
		$this->assertSame( 'action_already_starting', $result->get_error_code() );
		$this->assertStringContainsString( 'A workflow was started recently.', $result->get_error_message() );
		$this->assertFalse( $github_called );
		$this->assertCount( 1, $logs );
		$this->assertSame( 'test_smoke', $logs[0]['action'] );
		$this->assertSame( 'blocked', $logs[0]['status'] );
		$this->assertSame( 'qa-blocked-editor', $logs[0]['user'] );
		$this->assertSame( $result->get_error_message(), $logs[0]['details'] );
	}
}
