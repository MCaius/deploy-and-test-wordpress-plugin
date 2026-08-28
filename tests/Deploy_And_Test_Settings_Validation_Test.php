<?php
/**
 * Configuration validation tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Settings_Validation_Test extends Deploy_And_Test_Test_Case {
	public function test_malformed_owner_is_rejected() {
		$settings          = deploy_and_test_default_settings();
		$settings['owner'] = '-invalid-owner';

		$result = deploy_and_test_validate_settings( $settings );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_owner', $result->get_error_code() );
	}

	public function test_repository_with_spaces_is_rejected() {
		$settings         = deploy_and_test_default_settings();
		$settings['repo'] = 'repository with spaces';

		$result = deploy_and_test_validate_settings( $settings );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_repo', $result->get_error_code() );
	}

	public function test_invalid_ref_is_rejected() {
		$settings        = deploy_and_test_default_settings();
		$settings['ref'] = 'invalid ref';

		$result = deploy_and_test_validate_settings( $settings );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_ref', $result->get_error_code() );
	}

	public function test_non_yaml_workflow_is_rejected() {
		$settings                     = deploy_and_test_default_settings();
		$settings['preview_workflow'] = 'deploy-preview.txt';

		$result = deploy_and_test_validate_settings( $settings );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_workflow_file', $result->get_error_code() );
	}

	public function test_http_and_https_environment_urls_are_accepted() {
		$settings                               = deploy_and_test_default_settings();
		$settings['preview_environment_url']    = 'https://preview.example.com/path';
		$settings['production_environment_url'] = 'http://example.com';

		$this->assertTrue( deploy_and_test_validate_settings( $settings ) );
	}

	public function test_deploy_button_labels_use_defaults_and_saved_values() {
		$this->assertSame( 'Deploy Preview', deploy_and_test_get_deploy_button_label( 'preview' ) );
		$this->assertSame( 'Deploy Production', deploy_and_test_get_deploy_button_label( 'production' ) );

		$this->configure_plugin(
			array(
				'preview_button_label'    => 'Deploy Staging',
				'production_button_label' => 'Publish Live',
			)
		);

		$this->assertSame( 'Deploy Staging', deploy_and_test_get_deploy_button_label( 'preview' ) );
		$this->assertSame( 'Publish Live', deploy_and_test_get_deploy_button_label( 'production' ) );
	}

	/**
	 * @dataProvider deploy_workflow_configuration_provider
	 */
	public function test_deploy_workflows_are_configured_independently( $preview_workflow, $production_workflow, $preview_configured, $production_configured, $any_configured ) {
		$this->configure_plugin(
			array(
				'preview_workflow'    => $preview_workflow,
				'production_workflow' => $production_workflow,
			)
		);

		$this->assertSame( $preview_configured, deploy_and_test_deploy_environment_is_configured( 'preview' ) );
		$this->assertSame( $production_configured, deploy_and_test_deploy_environment_is_configured( 'production' ) );
		$this->assertSame( $any_configured, deploy_and_test_is_configured() );
	}

	public function deploy_workflow_configuration_provider() {
		return array(
			'both configured'    => array( 'deploy-preview.yml', 'deploy-production.yml', true, true, true ),
			'preview only'       => array( 'deploy-preview.yml', '', true, false, true ),
			'production only'    => array( '', 'deploy-production.yml', false, true, true ),
			'neither configured' => array( '', '', false, false, false ),
		);
	}

	public function test_test_actions_remain_configured_without_deploy_workflows() {
		$this->configure_plugin(
			array(
				'preview_workflow'    => '',
				'production_workflow' => '',
			)
		);

		$this->assertFalse( deploy_and_test_is_configured() );
		$this->assertTrue( (bool) deploy_and_test_tests_are_configured() );
	}

	/**
	 * @dataProvider invalid_environment_url_provider
	 */
	public function test_invalid_environment_url_is_rejected( $url ) {
		$settings                            = deploy_and_test_default_settings();
		$settings['preview_environment_url'] = $url;

		$result = deploy_and_test_validate_settings( $settings );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_environment_url', $result->get_error_code() );
	}

	public function invalid_environment_url_provider() {
		return array(
			'malformed URL' => array( 'not-a-url' ),
			'FTP URL'       => array( 'ftp://example.com' ),
			'credentials'   => array( 'https://user:password@example.com' ),
		);
	}

	/**
	 * @dataProvider malicious_setting_provider
	 */
	public function test_request_boundary_values_are_rejected( $field, $value, $expected_error ) {
		$settings           = deploy_and_test_default_settings();
		$settings[ $field ] = $value;

		$result = deploy_and_test_validate_settings( $settings );

		$this->assertWPError( $result );
		$this->assertSame( $expected_error, $result->get_error_code() );
	}

	public function malicious_setting_provider() {
		return array(
			'owner URL'              => array( 'owner', 'https://github.com/example', 'invalid_owner' ),
			'repository traversal'   => array( 'repo', '../private-repository', 'invalid_repo' ),
			'repository URL'         => array( 'test_repo', 'https://example.test/repository', 'invalid_repo' ),
			'ref traversal'          => array( 'ref', 'refs/heads/main..secret', 'invalid_ref' ),
			'ref reflog expression'  => array( 'test_ref', 'main@{1}', 'invalid_ref' ),
			'workflow traversal'     => array( 'preview_workflow', '../deploy.yml', 'invalid_workflow_file' ),
			'workflow URL'           => array( 'production_workflow', 'https://example.test/deploy.yml', 'invalid_workflow_file' ),
		);
	}
}
