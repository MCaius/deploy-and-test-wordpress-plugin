<?php
/**
 * Security tests for escaped admin output.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Output_Security_Test extends Deploy_And_Test_Test_Case {
	private const XSS_PAYLOAD = '<script>window.__deployAndTestXss = true;</script>';

	public function test_audit_log_escapes_untrusted_details() {
		deploy_and_test_add_audit_log( 'security_output', 'failed', self::XSS_PAYLOAD );

		ob_start();
		deploy_and_test_render_audit_log_tab();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( self::XSS_PAYLOAD, $html );
		$this->assertStringContainsString( esc_html( self::XSS_PAYLOAD ), $html );
	}

	public function test_summary_renderer_escapes_all_untrusted_text_fields() {
		$summary = array(
			'suite'      => self::XSS_PAYLOAD,
			'target_env' => self::XSS_PAYLOAD,
			'browser'    => self::XSS_PAYLOAD,
			'stats'      => array(
				'total'    => self::XSS_PAYLOAD,
				'passed'   => 0,
				'failed'   => 1,
				'skipped'  => 0,
				'timedOut' => 0,
			),
			'tests'      => array(
				array(
					'status'     => self::XSS_PAYLOAD,
					'project'    => self::XSS_PAYLOAD,
					'title'      => self::XSS_PAYLOAD,
					'file'       => self::XSS_PAYLOAD,
					'line'       => 1,
					'durationMs' => 1,
					'error'      => self::XSS_PAYLOAD,
				),
			),
		);

		$html = deploy_and_test_render_test_summary_html( $summary );

		$this->assertStringNotContainsString( self::XSS_PAYLOAD, $html );
		$this->assertGreaterThanOrEqual( 8, substr_count( $html, esc_html( self::XSS_PAYLOAD ) ) );
	}

	public function test_connection_page_escapes_stored_values_and_hides_private_key() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->configure_plugin(
			array(
				'preview_target' => self::XSS_PAYLOAD,
			)
		);

		ob_start();
		deploy_and_test_render_connection_tab();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( self::XSS_PAYLOAD, $html );
		$this->assertStringContainsString( esc_attr( self::XSS_PAYLOAD ), $html );
		$this->assertStringNotContainsString( 'BEGIN PRIVATE KEY', $html );
		$this->assertStringContainsString( 'Configured:', $html );
	}

	public function test_environment_status_link_is_safely_rendered() {
		$url = 'https://preview.example.com/?first=1&second=2';

		ob_start();
		deploy_and_test_render_environment_status_card( 'preview', 'Preview', 'Preview environment', $url, true, array() );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'href="' . esc_url( $url, array( 'http', 'https' ) ) . '"', $html );
		$this->assertStringContainsString( esc_html( 'Open: ' . $url ), $html );
		$this->assertStringContainsString( 'target="_blank" rel="noopener noreferrer"', $html );
	}

	public function test_environment_status_link_is_hidden_when_url_is_empty() {
		ob_start();
		deploy_and_test_render_environment_status_card( 'preview', 'Preview', 'Preview environment', '', true, array() );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'deploy-and-test-environment-link', $html );
		$this->assertStringNotContainsString( '<a ', $html );
	}

	public function test_deploy_status_only_renders_configured_environments() {
		$this->configure_plugin( array( 'production_workflow' => '' ) );

		ob_start();
		deploy_and_test_render_status_panel( array(), true );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'data-testid="deploy-status-preview"', $html );
		$this->assertStringNotContainsString( 'data-testid="deploy-status-production"', $html );
	}
}
