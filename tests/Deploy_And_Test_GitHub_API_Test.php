<?php
/**
 * GitHub API failure tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_GitHub_API_Test extends Deploy_And_Test_Test_Case {
	/**
	 * @dataProvider github_error_status_provider
	 */
	public function test_dispatch_reports_github_error_statuses( $status_code ) {
		$this->configure_plugin();
		$this->mock_github(
			function () use ( $status_code ) {
				return $this->http_response( $status_code, array( 'message' => 'Controlled GitHub error' ) );
			}
		);

		$result = deploy_and_test_github_dispatch_workflow( 'deploy-preview.yml' );

		$this->assertWPError( $result );
		$this->assertSame( 'github_api_error', $result->get_error_code() );
		$this->assertStringContainsString( 'HTTP ' . $status_code . '.', $result->get_error_message() );
		$this->assertStringNotContainsString( 'test-installation-token', $result->get_error_message() );
	}

	public function github_error_status_provider() {
		return array(
			'Unauthorized'          => array( 401 ),
			'Forbidden'             => array( 403 ),
			'Not found'             => array( 404 ),
			'Unprocessable entity'  => array( 422 ),
			'Internal server error' => array( 500 ),
		);
	}

	public function test_dispatch_returns_a_useful_github_http_error() {
		$this->configure_plugin();
		$this->mock_github(
			function () {
				return $this->http_response( 503, array( 'message' => 'Service temporarily unavailable' ) );
			}
		);

		$result = deploy_and_test_github_dispatch_workflow( 'deploy-preview.yml' );

		$this->assertWPError( $result );
		$this->assertSame( 'github_api_error', $result->get_error_code() );
		$this->assertStringContainsString( 'Service temporarily unavailable HTTP 503.', $result->get_error_message() );
		$this->assertStringNotContainsString( 'test-installation-token', $result->get_error_message() );
	}

	public function test_dispatch_preserves_a_network_timeout_error() {
		$this->configure_plugin();
		$this->mock_github(
			function () {
				return new WP_Error( 'http_request_failed', 'Connection timed out.' );
			}
		);

		$result = deploy_and_test_github_dispatch_workflow( 'deploy-preview.yml' );

		$this->assertWPError( $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
		$this->assertSame( 'Connection timed out.', $result->get_error_message() );
	}

	public function test_malformed_success_response_is_rejected() {
		$this->configure_plugin();
		$this->mock_github(
			function () {
				return $this->raw_http_response( 200, '{not-valid-json' );
			}
		);

		$result = deploy_and_test_github_count_workflows( 'deploy-repo' );

		$this->assertWPError( $result );
		$this->assertSame( 'github_invalid_json', $result->get_error_code() );
		$this->assertSame( 'GitHub API returned malformed JSON.', $result->get_error_message() );
	}
}
