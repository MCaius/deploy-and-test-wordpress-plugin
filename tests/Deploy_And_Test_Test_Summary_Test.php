<?php
/**
 * Test summary artifact failure tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Test_Summary_Test extends Deploy_And_Test_Test_Case {
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
