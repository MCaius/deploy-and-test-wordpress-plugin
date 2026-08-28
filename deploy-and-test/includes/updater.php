<?php
/**
 * GitHub Releases update integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DEPLOY_AND_TEST_UPDATE_URI               = 'https://github.com/MCaius/deploy-and-test-wordpress-plugin';
const DEPLOY_AND_TEST_GITHUB_RELEASES_API       = 'https://api.github.com/repos/MCaius/deploy-and-test-wordpress-plugin/releases/latest';
const DEPLOY_AND_TEST_GITHUB_RELEASE_ASSET      = 'deploy-and-test.zip';
const DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY  = 'deploy_and_test_github_release_update';
const DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_TTL  = 21600;
const DEPLOY_AND_TEST_GITHUB_FAILURE_CACHE_TTL  = 900;
const DEPLOY_AND_TEST_GITHUB_RESPONSE_SIZE_LIMIT = 1048576;

/**
 * Parse and validate a GitHub release response.
 *
 * @param mixed $payload Decoded GitHub API response.
 * @return array|false Validated release data or false.
 */
function deploy_and_test_parse_github_release( $payload ) {
	if ( ! is_array( $payload ) || ! empty( $payload['draft'] ) || ! empty( $payload['prerelease'] ) ) {
		return false;
	}

	$tag = isset( $payload['tag_name'] ) ? trim( (string) $payload['tag_name'] ) : '';

	if ( ! preg_match( '/^v?([0-9]+(?:\.[0-9]+){1,3})$/', $tag, $matches ) ) {
		return false;
	}

	$version              = $matches[1];
	$expected_package_url = DEPLOY_AND_TEST_UPDATE_URI . '/releases/download/' . rawurlencode( $tag ) . '/' . DEPLOY_AND_TEST_GITHUB_RELEASE_ASSET;
	$package              = '';

	foreach ( (array) ( $payload['assets'] ?? array() ) as $asset ) {
		if (
			! is_array( $asset ) ||
			( $asset['name'] ?? '' ) !== DEPLOY_AND_TEST_GITHUB_RELEASE_ASSET ||
			( isset( $asset['state'] ) && $asset['state'] !== 'uploaded' )
		) {
			continue;
		}

		$asset_url = isset( $asset['browser_download_url'] ) ? esc_url_raw( $asset['browser_download_url'], array( 'https' ) ) : '';

		if ( $asset_url !== $expected_package_url ) {
			continue;
		}

		$package = $asset_url;
		break;
	}

	if ( $package === '' ) {
		return false;
	}

	$expected_release_url = DEPLOY_AND_TEST_UPDATE_URI . '/releases/tag/' . rawurlencode( $tag );
	$release_url          = isset( $payload['html_url'] ) ? esc_url_raw( $payload['html_url'], array( 'https' ) ) : '';

	if ( $release_url !== $expected_release_url ) {
		$release_url = DEPLOY_AND_TEST_UPDATE_URI;
	}

	return array(
		'version' => $version,
		'url'     => $release_url,
		'package' => $package,
	);
}

/**
 * Fetch the latest validated GitHub release, with a site-wide cache.
 *
 * This request intentionally uses the public Releases API directly. It never
 * uses the plugin's GitHub App authentication or installation tokens.
 *
 * @return array|false Validated release data or false.
 */
function deploy_and_test_get_latest_github_release() {
	$cached = get_site_transient( DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY );

	if ( is_array( $cached ) && array_key_exists( 'release', $cached ) ) {
		return is_array( $cached['release'] ) ? $cached['release'] : false;
	}

	$response = wp_safe_remote_get(
		DEPLOY_AND_TEST_GITHUB_RELEASES_API,
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => DEPLOY_AND_TEST_GITHUB_RESPONSE_SIZE_LIMIT,
			'headers'             => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Deploy-and-Test-WordPress-Plugin/' . DEPLOY_AND_TEST_VERSION,
			),
		)
	);

	$release = false;

	if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			$release = deploy_and_test_parse_github_release( $payload );
		}
	}

	set_site_transient(
		DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY,
		array( 'release' => $release ),
		$release ? DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_TTL : DEPLOY_AND_TEST_GITHUB_FAILURE_CACHE_TTL
	);

	return $release;
}

/**
 * Clear the release cache when WordPress forces a new plugin update check.
 */
function deploy_and_test_clear_github_release_cache() {
	delete_site_transient( DEPLOY_AND_TEST_GITHUB_RELEASE_CACHE_KEY );
}

/**
 * Supply update metadata through WordPress's Update URI hook.
 *
 * @param array|false $update      Existing third-party update data.
 * @param array       $plugin_data Plugin header data.
 * @param string      $plugin_file Plugin basename.
 * @param array       $locales     Installed locales.
 * @return array|false Update data or false.
 */
function deploy_and_test_filter_github_plugin_update( $update, $plugin_data, $plugin_file, $locales ) {
	unset( $locales );

	if (
		$plugin_file !== plugin_basename( DEPLOY_AND_TEST_PLUGIN_FILE ) ||
		( $plugin_data['UpdateURI'] ?? '' ) !== DEPLOY_AND_TEST_UPDATE_URI
	) {
		return $update;
	}

	$release         = deploy_and_test_get_latest_github_release();
	$current_version = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : DEPLOY_AND_TEST_VERSION;

	if ( ! $release || ! version_compare( $release['version'], $current_version, '>' ) ) {
		return false;
	}

	return array(
		'id'           => DEPLOY_AND_TEST_UPDATE_URI,
		'slug'         => 'deploy-and-test',
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => '6.0',
		'tested'       => '7.1',
		'requires_php' => '7.4',
		'autoupdate'   => false,
	);
}
