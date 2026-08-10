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
		$this->assertFalse( get_option( deploy_and_test_deploy_lock_key( 'global' ), false ) );
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

	public function test_successful_deploy_uses_a_global_metadata_lock() {
		$this->configure_plugin();
		$this->mock_github(
			function ( $url ) {
				if ( strpos( $url, '/actions/runs?' ) !== false ) {
					return $this->http_response( 200, array( 'workflow_runs' => array() ) );
				}

				return $this->http_response( 204 );
			}
		);

		$result = deploy_and_test_execute_action( 'deploy_preview' );
		$lock   = deploy_and_test_get_deploy_lock( 'global' );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $lock );
		$this->assertSame( 'deploy', $lock['type'] );
		$this->assertSame( 'preview', $lock['environment'] );
		$this->assertSame( 'deploy-preview.yml', $lock['workflow'] );
		$this->assertSame( '', $lock['baseline_run_id'] );
		$this->assertFalse( get_option( deploy_and_test_deploy_lock_key( 'preview' ), false ) );
	}

	/**
	 * @dataProvider repeated_action_provider
	 */
	public function test_same_action_can_run_again_after_github_observes_completion( $action, $type, $workflow ) {
		$this->configure_plugin();
		$runs           = array();
		$dispatch_count = 0;
		$this->mock_github(
			function ( $url ) use ( &$runs, &$dispatch_count ) {
				if ( strpos( $url, '/actions/runs?' ) !== false ) {
					return $this->http_response( 200, array( 'workflow_runs' => $runs ) );
				}

				$dispatch_count++;
				return $this->http_response( 204 );
			}
		);

		$first_result = deploy_and_test_execute_action( $action );

		$this->assertNotWPError( $first_result );
		$first_lock = deploy_and_test_get_deploy_lock( 'global' );
		$this->assertIsArray( $first_lock );
		$runs         = array(
			array(
				'id'         => 301,
				'path'       => '.github/workflows/' . $workflow,
				'created_at' => gmdate( 'c', $first_lock['started_at'] ),
				'status'     => 'completed',
				'conclusion' => 'success',
			),
		);

		$this->assertTrue( deploy_and_test_reconcile_startup_lock( $runs, $type ) );

		$second_result = deploy_and_test_execute_action( $action );

		$this->assertNotWPError( $second_result );
		$this->assertSame( 2, $dispatch_count );
	}

	public function repeated_action_provider() {
		return array(
			'deploy after deploy' => array( 'deploy_preview', 'deploy', 'deploy-preview.yml' ),
			'test after test'     => array( 'test_smoke', 'test', 'smoke-tests.yml' ),
		);
	}

	/**
	 * @dataProvider terminal_conclusion_provider
	 */
	public function test_observed_terminal_run_releases_the_startup_lock( $conclusion ) {
		$this->configure_plugin();
		$started_at = time();
		$lock       = array(
			'lock_id'         => 'deploy-lock',
			'started_at'      => $started_at,
			'type'            => 'deploy',
			'environment'     => 'preview',
			'workflow'        => 'deploy-preview.yml',
			'baseline_run_id' => '100',
		);
		add_option( deploy_and_test_deploy_lock_key( 'global' ), $lock, '', false );

		$released = deploy_and_test_reconcile_startup_lock(
			array(
				array(
					'id'         => 101,
					'path'       => '.github/workflows/deploy-preview.yml',
					'created_at' => gmdate( 'c', $started_at ),
					'status'     => 'completed',
					'conclusion' => $conclusion,
				),
			),
			'deploy'
		);

		$this->assertTrue( $released );
		$this->assertFalse( get_option( deploy_and_test_deploy_lock_key( 'global' ), false ) );
	}

	public function terminal_conclusion_provider() {
		return array(
			'success'   => array( 'success' ),
			'failure'   => array( 'failure' ),
			'cancelled' => array( 'cancelled' ),
			'timed out' => array( 'timed_out' ),
		);
	}

	public function test_existing_baseline_run_does_not_release_the_startup_lock() {
		$this->configure_plugin();
		$started_at = time();
		$lock       = array(
			'lock_id'         => 'test-lock',
			'started_at'      => $started_at,
			'type'            => 'test',
			'workflow'        => 'smoke-tests.yml',
			'baseline_run_id' => '200',
		);
		add_option( deploy_and_test_deploy_lock_key( 'global' ), $lock, '', false );

		$released = deploy_and_test_reconcile_startup_lock(
			array(
				array(
					'id'         => 200,
					'path'       => '.github/workflows/smoke-tests.yml',
					'created_at' => gmdate( 'c', $started_at - 60 ),
					'status'     => 'completed',
					'conclusion' => 'success',
				),
			),
			'test'
		);

		$this->assertFalse( $released );
		$this->assertSame( $lock, deploy_and_test_get_deploy_lock( 'global' ) );
	}

	public function test_stale_poll_cannot_release_a_newer_startup_lock() {
		$old_lock = array(
			'lock_id'    => 'old-lock',
			'started_at' => time() - 10,
		);
		$new_lock = array(
			'lock_id'    => 'new-lock',
			'started_at' => time(),
		);
		add_option( deploy_and_test_deploy_lock_key( 'global' ), $old_lock, '', false );
		update_option( deploy_and_test_deploy_lock_key( 'global' ), $new_lock, false );

		$this->assertFalse( deploy_and_test_release_deploy_lock( 'global', $old_lock ) );
		$this->assertSame( $new_lock, deploy_and_test_get_deploy_lock( 'global' ) );
	}

	public function test_observed_active_run_releases_only_the_startup_lock() {
		$this->configure_plugin();
		$started_at = time();
		$lock       = array(
			'lock_id'         => 'test-lock',
			'started_at'      => $started_at,
			'type'            => 'test',
			'workflow'        => 'smoke-tests.yml',
			'baseline_run_id' => '200',
		);
		$runs       = array(
			array(
				'id'         => 201,
				'path'       => '.github/workflows/smoke-tests.yml',
				'created_at' => gmdate( 'c', $started_at ),
				'status'     => 'in_progress',
				'conclusion' => null,
			),
		);
		add_option( deploy_and_test_deploy_lock_key( 'global' ), $lock, '', false );

		$this->assertTrue( deploy_and_test_reconcile_startup_lock( $runs, 'test' ) );
		$this->assertTrue( deploy_and_test_test_status_has_active_run( deploy_and_test_get_test_status( $runs ) ) );
		$this->assertFalse( get_option( deploy_and_test_deploy_lock_key( 'global' ), false ) );
	}
}
