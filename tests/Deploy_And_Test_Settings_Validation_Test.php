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
}
