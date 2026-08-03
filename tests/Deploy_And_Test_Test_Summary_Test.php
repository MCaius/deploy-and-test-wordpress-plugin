<?php
/**
 * Test summary artifact failure tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Test_Summary_Test extends Deploy_And_Test_Test_Case {
	public function test_cached_summary_is_returned_without_a_github_request() {
		$expected      = array( 'total' => 2, 'passed' => 2 );
		$github_called = false;
		set_transient( deploy_and_test_test_summary_cache_key( 77 ), $expected, 600 );
		$this->mock_github(
			function () use ( &$github_called ) {
				$github_called = true;
				return $this->http_response( 500, array() );
			}
		);

		$result = deploy_and_test_get_cached_test_summary( 77 );

		$this->assertSame( $expected, $result );
		$this->assertFalse( $github_called );
	}

	public function test_artifact_content_length_over_limit_is_rejected() {
		$response = $this->raw_http_response( 200, 'small body' );
		$response['headers']['content-length'] = DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT + 1;

		$result = deploy_and_test_get_limited_artifact_response_body( $response );

		$this->assertWPError( $result );
		$this->assertSame( 'github_artifact_archive_too_large', $result->get_error_code() );
	}

	public function test_artifact_body_over_limit_is_rejected() {
		$response = $this->raw_http_response( 200, str_repeat( 'x', DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT + 1 ) );

		$result = deploy_and_test_get_limited_artifact_response_body( $response );

		$this->assertWPError( $result );
		$this->assertSame( 'github_artifact_archive_too_large', $result->get_error_code() );
	}

	public function test_archive_with_too_many_files_is_rejected() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required for artifact extraction.' );
		}

		$archive_path = wp_tempnam( 'too-many-files.zip' );
		$zip          = new ZipArchive();
		$zip->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		for ( $index = 0; $index <= DEPLOY_AND_TEST_ARTIFACT_FILE_LIMIT; ++$index ) {
			$zip->addFromString( 'result-' . $index . '.json', '{}' );
		}

		$zip->close();
		$archive = file_get_contents( $archive_path );
		unlink( $archive_path );

		$result = deploy_and_test_extract_test_summary_artifact( $archive );

		$this->assertWPError( $result );
		$this->assertSame( 'zip_too_many_files', $result->get_error_code() );
	}

	public function test_summary_json_over_limit_is_rejected() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required for artifact extraction.' );
		}

		$archive_path = wp_tempnam( 'large-summary.zip' );
		$zip          = new ZipArchive();
		$zip->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString(
			'deploy-update-summary.json',
			wp_json_encode( array( 'output' => str_repeat( 'x', DEPLOY_AND_TEST_TEST_SUMMARY_LIMIT ) ) )
		);
		$zip->close();
		$archive = file_get_contents( $archive_path );
		unlink( $archive_path );

		$result = deploy_and_test_extract_test_summary_artifact( $archive );

		$this->assertWPError( $result );
		$this->assertSame( 'summary_json_too_large', $result->get_error_code() );
	}

	public function test_exact_summary_artifact_is_selected_for_the_run_title() {
		$this->configure_plugin();
		$this->mock_github(
			function ( $url ) {
				if ( strpos( $url, '/artifacts' ) !== false ) {
					return $this->http_response(
						200,
						array(
							'artifacts' => array(
								array(
									'id'   => 10,
									'name' => 'deploy-update-summary-failing-preview',
								),
								array(
									'id'   => 20,
									'name' => 'deploy-update-summary-smoke-preview',
								),
							),
						)
					);
				}

				return $this->http_response( 200, array() );
			}
		);

		$result = deploy_and_test_find_test_summary_artifact(
			123,
			array( 'display_title' => 'Run smoke tests on preview' )
		);

		$this->assertIsArray( $result );
		$this->assertSame( 20, $result['id'] );
		$this->assertSame( 'deploy-update-summary-smoke-preview', $result['name'] );
	}

	public function test_valid_summary_json_is_parsed() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required for artifact extraction.' );
		}

		$expected = array(
			'total'   => 3,
			'passed'  => 3,
			'failed'  => 0,
			'skipped' => 0,
			'tests'   => array(
				array(
					'status' => 'passed',
					'name'   => 'workflow dispatch was accepted',
				),
			),
		);
		$archive_path = wp_tempnam( 'valid-summary.zip' );
		$zip          = new ZipArchive();
		$zip->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( 'deploy-update-summary.json', wp_json_encode( $expected ) );
		$zip->close();

		$archive = file_get_contents( $archive_path );
		unlink( $archive_path );
		$result = deploy_and_test_extract_test_summary_artifact( $archive );

		$this->assertSame( $expected, $result );
	}

	public function test_missing_summary_artifact_returns_a_clear_error() {
		$this->configure_plugin();
		$this->mock_github(
			function ( $url ) {
				if ( strpos( $url, '/artifacts' ) !== false ) {
					return $this->http_response( 200, array( 'artifacts' => array() ) );
				}

				return $this->http_response( 200, array() );
			}
		);

		$result = deploy_and_test_find_test_summary_artifact( 123 );

		$this->assertWPError( $result );
		$this->assertSame( 'summary_artifact_missing', $result->get_error_code() );
		$this->assertStringContainsString( 'No deploy-update-summary artifact found', $result->get_error_message() );
	}

	public function test_malformed_summary_json_is_rejected() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required for artifact extraction.' );
		}

		$archive_path = wp_tempnam( 'malformed-summary.zip' );
		$zip          = new ZipArchive();
		$zip->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( 'deploy-update-summary.json', '{not-valid-json' );
		$zip->close();

		$archive = file_get_contents( $archive_path );
		unlink( $archive_path );
		$result = deploy_and_test_extract_test_summary_artifact( $archive );

		$this->assertWPError( $result );
		$this->assertSame( 'summary_json_invalid', $result->get_error_code() );
	}

	public function test_archive_without_summary_json_is_rejected() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive is required for artifact extraction.' );
		}

		$archive_path = wp_tempnam( 'missing-summary.zip' );
		$zip          = new ZipArchive();
		$zip->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( 'other-file.json', '{}' );
		$zip->close();

		$archive = file_get_contents( $archive_path );
		unlink( $archive_path );
		$result = deploy_and_test_extract_test_summary_artifact( $archive );

		$this->assertWPError( $result );
		$this->assertSame( 'summary_json_missing', $result->get_error_code() );
	}
}
