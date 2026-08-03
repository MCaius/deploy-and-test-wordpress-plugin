<?php
/**
 * Shared integration test helpers.
 */

abstract class Deploy_And_Test_Test_Case extends WP_UnitTestCase {
	protected $github_response_callback;
	protected $installation_token_response_callback;

	public function set_up() {
		parent::set_up();

		delete_option( DEPLOY_AND_TEST_SETTINGS_OPTION );
		delete_option( DEPLOY_AND_TEST_AUDIT_OPTION );
		delete_option( deploy_and_test_deploy_lock_key( 'global' ) );
		delete_option( deploy_and_test_deploy_lock_key( 'preview' ) );
		delete_option( deploy_and_test_deploy_lock_key( 'production' ) );

		$this->github_response_callback = null;
		$this->installation_token_response_callback = null;
		add_filter( 'pre_http_request', array( $this, 'filter_github_http_request' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'filter_github_http_request' ), 10 );
		unset( $_POST['test_environment'] );
		unset( $_POST['deploy_action'], $_POST['deploy_and_test_nonce'] );
		unset( $_REQUEST['deploy_and_test_nonce'] );

		parent::tear_down();
	}

	protected function configure_plugin( $overrides = array() ) {
		$settings = array_merge(
			deploy_and_test_default_settings(),
			array(
				'owner'             => 'qa-owner',
				'repo'              => 'deploy-repo',
				'ref'               => 'main',
				'test_repo'         => 'test-repo',
				'test_ref'          => 'main',
				'test_environments' => array(
					array(
						'label' => 'QA Sandbox',
						'value' => 'preview',
					),
				),
				'test_actions'      => array(
					array(
						'id'          => 'smoke',
						'enabled'     => true,
						'label'       => 'Passing smoke tests',
						'workflow'    => 'smoke-tests.yml',
						'input_name'  => 'suite',
						'input_value' => 'smoke',
						'order'       => 10,
					),
				),
			),
			$overrides
		);

		update_option( DEPLOY_AND_TEST_SETTINGS_OPTION, $settings, false );
	}

	protected function mock_github( $callback ) {
		$this->github_response_callback = $callback;
	}

	protected function mock_installation_token( $callback ) {
		$this->installation_token_response_callback = $callback;
	}

	public function filter_github_http_request( $preempt, $args, $url ) {
		if ( strpos( $url, 'https://api.github.com/app/installations/' ) === 0 ) {
			if ( is_callable( $this->installation_token_response_callback ) ) {
				return call_user_func( $this->installation_token_response_callback, $url, $args );
			}

			return $this->http_response( 201, array( 'token' => 'test-installation-token' ) );
		}

		if ( strpos( $url, 'https://api.github.com/' ) === 0 && is_callable( $this->github_response_callback ) ) {
			return call_user_func( $this->github_response_callback, $url, $args );
		}

		return $preempt;
	}

	protected function http_response( $code, $body = array() ) {
		return $this->raw_http_response( $code, wp_json_encode( $body ) );
	}

	protected function raw_http_response( $code, $body ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
