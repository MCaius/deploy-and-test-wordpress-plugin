<?php
/**
 * GitHub App authentication tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_GitHub_Authentication_Test extends Deploy_And_Test_Test_Case {
	public function test_generated_jwt_contains_the_expected_header_and_claims() {
		$jwt = deploy_and_test_github_generate_app_jwt();

		$this->assertNotWPError( $jwt );
		$segments = explode( '.', $jwt );
		$this->assertCount( 3, $segments );
		$header  = json_decode( $this->decode_base64url( $segments[0] ), true );
		$payload = json_decode( $this->decode_base64url( $segments[1] ), true );

		$this->assertSame( 'RS256', $header['alg'] );
		$this->assertSame( 'JWT', $header['typ'] );
		$this->assertSame( (string) DEPLOY_AND_TEST_GITHUB_APP_ID, $payload['iss'] );
		$this->assertSame( 9 * 60, $payload['exp'] - $payload['iat'] );
		$this->assertNotSame( '', $segments[2] );
	}

	public function test_installation_token_is_returned_from_a_successful_response() {
		$result = deploy_and_test_github_get_installation_token();

		$this->assertSame( 'test-installation-token', $result );
	}

	public function test_installation_token_http_error_is_reported() {
		$this->mock_installation_token(
			function () {
				return $this->http_response( 401, array( 'message' => 'Bad credentials' ) );
			}
		);

		$result = deploy_and_test_github_get_installation_token();

		$this->assertWPError( $result );
		$this->assertSame( 'github_app_token_error', $result->get_error_code() );
		$this->assertSame( 'Bad credentials HTTP 401.', $result->get_error_message() );
	}

	public function test_installation_token_missing_from_success_response_is_reported() {
		$this->mock_installation_token(
			function () {
				return $this->http_response( 201, array() );
			}
		);

		$result = deploy_and_test_github_get_installation_token();

		$this->assertWPError( $result );
		$this->assertSame( 'github_app_token_missing', $result->get_error_code() );
	}

	public function test_installation_token_network_error_is_preserved() {
		$this->mock_installation_token(
			function () {
				return new WP_Error( 'http_request_failed', 'Token endpoint timed out.' );
			}
		);

		$result = deploy_and_test_github_get_installation_token();

		$this->assertWPError( $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
		$this->assertSame( 'Token endpoint timed out.', $result->get_error_message() );
	}

	private function decode_base64url( $value ) {
		$padding = strlen( $value ) % 4;

		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return base64_decode( strtr( $value, '-_', '+/' ) );
	}
}
