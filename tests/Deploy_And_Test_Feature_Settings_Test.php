<?php
/**
 * Independent feature settings tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Feature_Settings_Test extends Deploy_And_Test_Test_Case {
	public function test_feature_and_cleanup_defaults_are_safe() {
		$settings = deploy_and_test_get_settings();

		$this->assertTrue( $settings['enable_deploy_features'] );
		$this->assertTrue( $settings['enable_test_features'] );
		$this->assertFalse( $settings['delete_data_on_uninstall'] );
	}

	public function test_explicit_cleanup_preference_is_preserved() {
		update_option(
			DEPLOY_AND_TEST_SETTINGS_OPTION,
			array(
				'delete_data_on_uninstall'         => true,
				'delete_data_on_uninstall_touched' => true,
			),
			false
		);

		$this->assertTrue( deploy_and_test_get_setting( 'delete_data_on_uninstall' ) );
	}

	public function test_at_least_one_feature_must_be_enabled() {
		$result = deploy_and_test_validate_feature_settings( false, false );

		$this->assertWPError( $result );
		$this->assertSame( 'no_features_enabled', $result->get_error_code() );
		$this->assertTrue( deploy_and_test_validate_feature_settings( true, false ) );
		$this->assertTrue( deploy_and_test_validate_feature_settings( false, true ) );
	}

	public function test_disabling_a_feature_preserves_its_configuration() {
		$this->configure_plugin(
			array(
				'enable_deploy_features' => false,
				'enable_test_features'   => true,
			)
		);

		$this->assertSame( 'deploy-repo', deploy_and_test_get_setting( 'repo' ) );
		$this->assertSame( 'deploy-preview.yml', deploy_and_test_get_setting( 'preview_workflow' ) );
		$this->assertSame( 'test-repo', deploy_and_test_get_setting( 'test_repo' ) );
		$this->assertFalse( deploy_and_test_deploy_features_enabled() );
		$this->assertTrue( deploy_and_test_test_features_enabled() );
	}

	public function test_deploy_only_and_tests_only_configuration_are_independent() {
		$this->configure_plugin(
			array(
				'enable_test_features' => false,
				'test_repo'            => '',
				'test_actions'         => array(),
			)
		);
		$this->assertTrue( deploy_and_test_enabled_features_are_configured() );

		$this->configure_plugin(
			array(
				'enable_deploy_features' => false,
				'enable_test_features'   => true,
				'repo'                   => '',
				'preview_workflow'       => '',
				'production_workflow'    => '',
			)
		);
		$this->assertTrue( deploy_and_test_enabled_features_are_configured() );
	}

	/**
	 * @dataProvider disabled_action_provider
	 */
	public function test_disabled_actions_are_rejected_without_github_requests( $setting, $action, $error_code ) {
		$this->configure_plugin( array( $setting => false ) );
		$github_called = false;
		$this->mock_github(
			function () use ( &$github_called ) {
				$github_called = true;
				return $this->http_response( 204 );
			}
		);

		$result = deploy_and_test_execute_action( $action );

		$this->assertWPError( $result );
		$this->assertSame( $error_code, $result->get_error_code() );
		$this->assertFalse( $github_called );
	}

	public function disabled_action_provider() {
		return array(
			'deploy disabled' => array( 'enable_deploy_features', 'deploy_preview', 'deploy_features_disabled' ),
			'tests disabled'  => array( 'enable_test_features', 'test_smoke', 'test_features_disabled' ),
		);
	}

	/**
	 * @dataProvider feature_mode_provider
	 */
	public function test_general_only_renders_and_requests_enabled_features( $overrides, $visible_test_ids, $hidden_test_ids, $expected_repository, $unexpected_repository ) {
		$this->configure_plugin( $overrides );
		$requested_urls = array();
		$this->mock_github(
			function ( $url ) use ( &$requested_urls ) {
				$requested_urls[] = $url;
				return $this->http_response( 200, array( 'workflow_runs' => array() ) );
			}
		);

		ob_start();
		deploy_and_test_render_general_tab( true );
		$html = ob_get_clean();

		foreach ( $visible_test_ids as $test_id ) {
			$this->assertStringContainsString( 'data-testid="' . $test_id . '"', $html );
		}

		foreach ( $hidden_test_ids as $test_id ) {
			$this->assertStringNotContainsString( 'data-testid="' . $test_id . '"', $html );
		}

		$this->assertStringContainsString( '/repos/qa-owner/' . $expected_repository . '/', implode( "\n", $requested_urls ) );
		$this->assertStringNotContainsString( '/repos/qa-owner/' . $unexpected_repository . '/', implode( "\n", $requested_urls ) );
	}

	public function feature_mode_provider() {
		return array(
			'deploy only' => array(
				array( 'enable_test_features' => false ),
				array( 'deploy-action-preview', 'status-tab-deploy', 'status-panel-deploy' ),
				array( 'status-tab-test', 'status-panel-test' ),
				'deploy-repo',
				'test-repo',
			),
			'tests only'  => array(
				array( 'enable_deploy_features' => false ),
				array( 'status-tab-test', 'status-panel-test' ),
				array( 'deploy-action-preview', 'status-tab-deploy', 'status-panel-deploy' ),
				'test-repo',
				'deploy-repo',
			),
		);
	}
}
