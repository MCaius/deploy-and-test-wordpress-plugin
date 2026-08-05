<?php
/**
 * Tests that request credentials do not leak into user-visible failures.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Secret_Leakage_Test extends Deploy_And_Test_Test_Case {
	public function test_failed_action_does_not_expose_request_credentials_in_error_or_audit() {
		$this->configure_plugin();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$captured_jwt = '';
		$this->mock_installation_token(
			function ( $url, $args ) use ( &$captured_jwt ) {
				$captured_jwt = str_replace( 'Bearer ', '', $args['headers']['Authorization'] );
				return $this->http_response( 201, array( 'token' => 'qa-secret-installation-token' ) );
			}
		);
		$this->mock_github(
			function () {
				return $this->http_response( 503, array( 'message' => 'Controlled GitHub failure' ) );
			}
		);

		$result = deploy_and_test_execute_action( 'deploy_preview' );
		$logs   = deploy_and_test_get_audit_log();
		$output = $result->get_error_message() . ' ' . wp_json_encode( $logs );

		$this->assertWPError( $result );
		$this->assertNotSame( '', $captured_jwt );
		$this->assertStringNotContainsString( $captured_jwt, $output );
		$this->assertStringNotContainsString( 'qa-secret-installation-token', $output );
		$this->assertStringNotContainsString( 'Authorization', $output );
		$this->assertStringNotContainsString( 'BEGIN PRIVATE KEY', $output );
	}

	public function test_connection_page_displays_key_status_without_key_or_jwt_material() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$jwt         = deploy_and_test_github_generate_app_jwt();
		$private_key = deploy_and_test_github_get_private_key();

		ob_start();
		deploy_and_test_render_connection_tab();
		$html = ob_get_clean();

		$this->assertNotWPError( $jwt );
		$this->assertNotWPError( $private_key );
		$this->assertStringContainsString( 'Configured:', $html );
		$this->assertStringNotContainsString( $jwt, $html );
		$this->assertStringNotContainsString( $private_key, $html );
		$this->assertStringNotContainsString( 'BEGIN PRIVATE KEY', $html );
	}
}
