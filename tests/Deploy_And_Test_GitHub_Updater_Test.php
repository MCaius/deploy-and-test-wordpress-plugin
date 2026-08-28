<?php
/**
 * GitHub Releases updater integration tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_GitHub_Updater_Test extends Deploy_And_Test_Test_Case {
	private $request_count = 0;
	private $response_body = '';
	private $response_code = 200;

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_release_request' ), 20 );
		parent::tear_down();
	}

	public function test_plugin_header_and_version_constant_are_synchronized() {
		$headers = get_file_data(
			DEPLOY_AND_TEST_PLUGIN_FILE,
			array(
				'version'    => 'Version',
				'update_uri' => 'Update URI',
			),
			'plugin'
		);

		$this->assertSame( DEPLOY_AND_TEST_VERSION, $headers['version'] );
		$this->assertSame( DEPLOY_AND_TEST_UPDATE_URI, $headers['update_uri'] );
	}

	public function test_valid_stable_release_is_parsed() {
		$this->assertSame(
			array(
				'version' => '1.1.0',
				'url'     => 'https://github.com/MCaius/deploy-and-test-wordpress-plugin/releases/tag/v1.1.0',
				'package' => 'https://github.com/MCaius/deploy-and-test-wordpress-plugin/releases/download/v1.1.0/deploy-and-test.zip',
			),
			deploy_and_test_parse_github_release( $this->valid_release() )
		);
	}

	/**
	 * @dataProvider invalid_release_provider
	 */
	public function test_invalid_release_is_rejected( $payload ) {
		$this->assertFalse( deploy_and_test_parse_github_release( $payload ) );
	}

	public function invalid_release_provider() {
		$draft                         = $this->valid_release();
		$draft['draft']                = true;
		$prerelease                    = $this->valid_release();
		$prerelease['prerelease'] = true;
		$invalid_tag                   = $this->valid_release();
		$invalid_tag['tag_name']       = 'latest';
		$missing_asset                 = $this->valid_release();
		$missing_asset['assets']       = array();
		$wrong_repository              = $this->valid_release();
		$wrong_repository['assets'][0]['browser_download_url'] = 'https://github.com/example/other/releases/download/v1.1.0/deploy-and-test.zip';
		$wrong_scheme                  = $this->valid_release();
		$wrong_scheme['assets'][0]['browser_download_url'] = 'http://github.com/MCaius/deploy-and-test-wordpress-plugin/releases/download/v1.1.0/deploy-and-test.zip';
		$wrong_tag_path                = $this->valid_release();
		$wrong_tag_path['assets'][0]['browser_download_url'] = 'https://github.com/MCaius/deploy-and-test-wordpress-plugin/releases/download/v9.9.9/deploy-and-test.zip';
		$asset_not_uploaded            = $this->valid_release();
		$asset_not_uploaded['assets'][0]['state'] = 'new';

		return array(
			'malformed payload'       => array( 'malformed' ),
			'draft'                   => array( $draft ),
			'prerelease'              => array( $prerelease ),
			'invalid tag'             => array( $invalid_tag ),
			'missing asset'           => array( $missing_asset ),
			'wrong repository'        => array( $wrong_repository ),
			'non-HTTPS package'       => array( $wrong_scheme ),
			'mismatched package tag'  => array( $wrong_tag_path ),
			'incomplete upload'       => array( $asset_not_uploaded ),
		);
	}

	public function test_untrusted_release_page_falls_back_to_the_repository() {
		$release             = $this->valid_release();
		$release['html_url'] = 'https://example.test/untrusted';

		$parsed = deploy_and_test_parse_github_release( $release );

		$this->assertSame( DEPLOY_AND_TEST_UPDATE_URI, $parsed['url'] );
	}

	public function test_newer_release_produces_wordpress_update_metadata() {
		set_site_transient(
			DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY,
			array( 'release' => deploy_and_test_parse_github_release( $this->valid_release() ) ),
			HOUR_IN_SECONDS
		);

		$update = deploy_and_test_filter_github_plugin_update(
			false,
			array(
				'Version'   => '1.0.4',
				'UpdateURI' => DEPLOY_AND_TEST_UPDATE_URI,
			),
			plugin_basename( DEPLOY_AND_TEST_PLUGIN_FILE ),
			array( 'en_US' )
		);

		$this->assertSame( '1.1.0', $update['version'] );
		$this->assertSame( 'deploy-and-test', $update['slug'] );
		$this->assertSame( 'https://github.com/MCaius/deploy-and-test-wordpress-plugin/releases/download/v1.1.0/deploy-and-test.zip', $update['package'] );
		$this->assertSame( '7.4', $update['requires_php'] );
		$this->assertFalse( $update['autoupdate'] );
	}

	public function test_current_or_older_release_does_not_produce_an_update() {
		foreach ( array( DEPLOY_AND_TEST_VERSION, '0.9.0' ) as $release_version ) {
			$release            = deploy_and_test_parse_github_release( $this->valid_release() );
			$release['version'] = $release_version;
			set_site_transient( DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY, array( 'release' => $release ), HOUR_IN_SECONDS );

			$this->assertFalse(
				deploy_and_test_filter_github_plugin_update(
					false,
					array(
						'Version'   => DEPLOY_AND_TEST_VERSION,
						'UpdateURI' => DEPLOY_AND_TEST_UPDATE_URI,
					),
					plugin_basename( DEPLOY_AND_TEST_PLUGIN_FILE ),
					array( 'en_US' )
				)
			);
		}
	}

	public function test_update_filter_does_not_modify_other_plugins() {
		$existing = array( 'version' => '9.9.9' );

		$this->assertSame(
			$existing,
			deploy_and_test_filter_github_plugin_update(
				$existing,
				array(
					'Version'   => '1.0.0',
					'UpdateURI' => 'https://github.com/example/other-plugin',
				),
				'other-plugin/other-plugin.php',
				array( 'en_US' )
			)
		);
	}

	public function test_public_release_request_is_cached_and_contains_no_credentials() {
		$this->response_body = wp_json_encode( $this->valid_release() );
		add_filter( 'pre_http_request', array( $this, 'mock_release_request' ), 20, 3 );

		$first  = deploy_and_test_get_latest_github_release();
		$second = deploy_and_test_get_latest_github_release();

		$this->assertSame( '1.1.0', $first['version'] );
		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->request_count );
	}

	public function test_unavailable_or_malformed_response_fails_safely_and_is_cached() {
		$this->response_code = 503;
		add_filter( 'pre_http_request', array( $this, 'mock_release_request' ), 20, 3 );

		$this->assertFalse( deploy_and_test_get_latest_github_release() );
		$this->assertFalse( deploy_and_test_get_latest_github_release() );
		$this->assertSame( 1, $this->request_count );
	}

	public function test_malformed_success_response_fails_safely_and_is_cached() {
		$this->response_body = '{malformed';
		add_filter( 'pre_http_request', array( $this, 'mock_release_request' ), 20, 3 );

		$this->assertFalse( deploy_and_test_get_latest_github_release() );
		$this->assertFalse( deploy_and_test_get_latest_github_release() );
		$this->assertSame( 1, $this->request_count );
	}

	public function test_forced_update_check_clears_the_release_cache() {
		set_site_transient( DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY, array( 'release' => array( 'version' => '1.1.0' ) ), HOUR_IN_SECONDS );

		deploy_and_test_clear_github_release_cache();

		$this->assertFalse( get_site_transient( DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY ) );
	}

	public function mock_release_request( $preempt, $args, $url ) {
		unset( $preempt );
		$this->request_count++;
		$this->assertSame( DEPLOY_AND_TEST_GITHUB_RELEASES_API, $url );
		$this->assertSame( 'Deploy-and-Test-WordPress-Plugin/' . DEPLOY_AND_TEST_VERSION, $args['headers']['User-Agent'] );
		$this->assertArrayNotHasKey( 'Authorization', $args['headers'] );
		$this->assertSame( 0, $args['redirection'] );
		$this->assertSame( DEPLOY_AND_TEST_GITHUB_RESPONSE_SIZE_LIMIT, $args['limit_response_size'] );
		$this->assertStringNotContainsString( home_url(), wp_json_encode( $args ) );
		$this->assertStringNotContainsString( 'test-installation-token', wp_json_encode( $args ) );

		return $this->raw_http_response( $this->response_code, $this->response_body );
	}

	private function valid_release() {
		return array(
			'tag_name'   => 'v1.1.0',
			'html_url'   => 'https://github.com/MCaius/deploy-and-test-wordpress-plugin/releases/tag/v1.1.0',
			'draft'      => false,
			'prerelease' => false,
			'assets'     => array(
				array(
					'name'                 => DEPLOY_AND_TEST_GITHUB_RELEASE_ASSET,
					'state'                => 'uploaded',
					'browser_download_url' => 'https://github.com/MCaius/deploy-and-test-wordpress-plugin/releases/download/v1.1.0/deploy-and-test.zip',
				),
			),
		);
	}
}
