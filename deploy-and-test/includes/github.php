<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_github_test_connection() {
	$workflow_count = deploy_and_test_github_count_workflows( deploy_and_test_get_setting( 'repo' ) );

	if ( is_wp_error( $workflow_count ) ) {
		return $workflow_count;
	}

	return sprintf(
		/* translators: %d: number of GitHub workflows found. */
		_n( 'GitHub connection works. Found %d workflow.', 'GitHub connection works. Found %d workflows.', $workflow_count, 'deploy-and-test' ),
		$workflow_count
	);
}

function deploy_and_test_github_test_testing_connection() {
	$workflow_count = deploy_and_test_github_count_workflows( deploy_and_test_get_setting( 'test_repo' ) );

	if ( is_wp_error( $workflow_count ) ) {
		return $workflow_count;
	}

	return sprintf(
		/* translators: %d: number of GitHub workflows found. */
		_n( 'Testing repository connection works. Found %d workflow.', 'Testing repository connection works. Found %d workflows.', $workflow_count, 'deploy-and-test' ),
		$workflow_count
	);
}

function deploy_and_test_github_count_workflows( $repo ) {
	$response = deploy_and_test_github_request(
		'repos/' . deploy_and_test_get_setting( 'owner' ) . '/' . $repo . '/actions/workflows',
		'GET'
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return isset( $response['total_count'] ) ? (int) $response['total_count'] : 0;
}

function deploy_and_test_github_dispatch_workflow( $workflow_file, $repo = '', $ref = '', $inputs = array() ) {
	$repo = $repo ? $repo : deploy_and_test_get_setting( 'repo' );
	$ref  = $ref ? $ref : deploy_and_test_get_setting( 'ref' );
	$body = array(
		'ref' => $ref,
	);

	if ( $inputs ) {
		$body['inputs'] = $inputs;
	}

	$response = deploy_and_test_github_request(
		'repos/' . deploy_and_test_get_setting( 'owner' ) . '/' . $repo . '/actions/workflows/' . rawurlencode( $workflow_file ) . '/dispatches',
		'POST',
		$body
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return sprintf(
		/* translators: %s: GitHub Actions workflow file. */
		__( 'GitHub workflow dispatch started for %s.', 'deploy-and-test' ),
		$workflow_file
	);
}

function deploy_and_test_github_get_recent_runs() {
	$response = deploy_and_test_github_request(
		'repos/' . deploy_and_test_get_setting( 'owner' ) . '/' . deploy_and_test_get_setting( 'repo' ) . '/actions/runs?per_page=10',
		'GET'
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return $response['workflow_runs'] ?? array();
}

function deploy_and_test_github_get_recent_test_runs() {
	$response = deploy_and_test_github_request(
		'repos/' . deploy_and_test_get_setting( 'owner' ) . '/' . deploy_and_test_get_setting( 'test_repo' ) . '/actions/runs?per_page=10',
		'GET'
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return $response['workflow_runs'] ?? array();
}

function deploy_and_test_github_get_run_jobs( $repo, $run_id ) {
	$response = deploy_and_test_github_request(
		'repos/' . deploy_and_test_get_setting( 'owner' ) . '/' . $repo . '/actions/runs/' . rawurlencode( (string) $run_id ) . '/jobs?per_page=100',
		'GET'
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return $response['jobs'] ?? array();
}

function deploy_and_test_github_get_run( $repo, $run_id ) {
	$response = deploy_and_test_github_request(
		'repos/' . deploy_and_test_get_setting( 'owner' ) . '/' . $repo . '/actions/runs/' . rawurlencode( (string) $run_id ),
		'GET'
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return $response;
}

function deploy_and_test_github_get_run_artifacts( $repo, $run_id ) {
	$response = deploy_and_test_github_request(
		'repos/' . deploy_and_test_get_setting( 'owner' ) . '/' . $repo . '/actions/runs/' . rawurlencode( (string) $run_id ) . '/artifacts?per_page=100',
		'GET'
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return $response['artifacts'] ?? array();
}

function deploy_and_test_github_download_artifact_archive( $artifact ) {
	$token = deploy_and_test_github_get_installation_token();

	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$artifact_id          = $artifact['id'] ?? 0;
	$archive_download_url = $artifact_id
		? 'https://api.github.com/repos/' . rawurlencode( deploy_and_test_get_setting( 'owner' ) ) . '/' . rawurlencode( deploy_and_test_get_setting( 'test_repo' ) ) . '/actions/artifacts/' . rawurlencode( (string) $artifact_id ) . '/zip'
		: ( $artifact['archive_download_url'] ?? '' );

	if ( ! $archive_download_url ) {
		return new WP_Error( 'github_artifact_missing_url', __( 'GitHub artifact archive download URL is missing.', 'deploy-and-test' ) );
	}

	if ( ! empty( $artifact['size_in_bytes'] ) && (int) $artifact['size_in_bytes'] > DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT ) {
		return deploy_and_test_artifact_archive_too_large_error();
	}

	$response = wp_remote_get(
		$archive_download_url,
		array(
			'headers'             => array(
				'Accept'               => 'application/vnd.github+json',
				'Authorization'        => 'Bearer ' . $token,
				'X-GitHub-Api-Version' => '2022-11-28',
			),
			'limit_response_size' => DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT + 1,
			'redirection'         => 0,
			'timeout'             => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );

	if ( $code >= 300 && $code < 400 ) {
		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( ! $location ) {
			return new WP_Error( 'github_artifact_redirect_missing', __( 'GitHub artifact download redirect did not include a Location header.', 'deploy-and-test' ) );
		}

		return deploy_and_test_download_temporary_artifact_url( $location );
	}

	if ( $code < 200 || $code >= 300 ) {
		$body    = wp_remote_retrieve_body( $response );
		$decoded = $body ? json_decode( $body, true ) : array();
		$message = is_array( $decoded ) && ! empty( $decoded['message'] ) ? $decoded['message'] : __( 'Could not download GitHub artifact archive.', 'deploy-and-test' );

		return new WP_Error(
			'github_artifact_error',
			sprintf(
				/* translators: 1: GitHub error message, 2: HTTP status code, 3: artifact name. */
				__( '%1$s HTTP %2$d. Artifact: %3$s.', 'deploy-and-test' ),
				$message,
				$code,
				$artifact['name'] ?? (string) $artifact_id
			)
		);
	}

	return deploy_and_test_get_limited_artifact_response_body( $response );
}

function deploy_and_test_download_temporary_artifact_url( $url ) {
	$response = wp_remote_get(
		$url,
		array(
			'limit_response_size' => DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT + 1,
			'redirection'         => 5,
			'timeout'             => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );

	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'github_artifact_storage_error', __( 'Could not download GitHub artifact archive from the temporary storage URL.', 'deploy-and-test' ) . ' HTTP ' . $code . '.' );
	}

	return deploy_and_test_get_limited_artifact_response_body( $response );
}

function deploy_and_test_get_limited_artifact_response_body( $response ) {
	$content_length = wp_remote_retrieve_header( $response, 'content-length' );

	if ( $content_length && (int) $content_length > DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT ) {
		return deploy_and_test_artifact_archive_too_large_error();
	}

	$body = wp_remote_retrieve_body( $response );

	if ( strlen( $body ) > DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT ) {
		return deploy_and_test_artifact_archive_too_large_error();
	}

	return $body;
}

function deploy_and_test_artifact_archive_too_large_error() {
	return new WP_Error(
		'github_artifact_archive_too_large',
		sprintf(
			/* translators: %s: maximum artifact archive size. */
			__( 'The GitHub artifact archive is too large to load in WordPress. Limit: %s. Open the GitHub run to view the full report.', 'deploy-and-test' ),
			size_format( DEPLOY_AND_TEST_ARTIFACT_ARCHIVE_LIMIT )
		)
	);
}

function deploy_and_test_github_request( $endpoint, $method = 'GET', $body = null ) {
	$token = deploy_and_test_github_get_installation_token();

	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$args = array(
		'method'  => $method,
		'headers' => array(
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'Bearer ' . $token,
			'X-GitHub-Api-Version' => '2022-11-28',
		),
		'timeout' => 20,
	);

	if ( $body !== null ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
	}

	$response = wp_remote_request( 'https://api.github.com/' . ltrim( $endpoint, '/' ), $args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code     = wp_remote_retrieve_response_code( $response );
	$raw_body = wp_remote_retrieve_body( $response );
	$decoded  = $raw_body ? json_decode( $raw_body, true ) : array();

	if ( $code < 200 || $code >= 300 ) {
		$message = is_array( $decoded ) && ! empty( $decoded['message'] ) ? $decoded['message'] : __( 'GitHub API request failed.', 'deploy-and-test' );
		return new WP_Error( 'github_api_error', deploy_and_test_github_error_message( $message, $code, $endpoint ) );
	}

	return is_array( $decoded ) ? $decoded : array();
}

function deploy_and_test_github_error_message( $message, $code, $endpoint ) {
	$details = $message . ' HTTP ' . $code . '.';

	if ( $code === 404 ) {
		$owner          = deploy_and_test_get_setting( 'owner' );
		$repo           = deploy_and_test_get_setting( 'repo' );
		$endpoint_parts = explode( '/', ltrim( $endpoint, '/' ) );

		if ( count( $endpoint_parts ) >= 3 && $endpoint_parts[0] === 'repos' ) {
			$owner = $endpoint_parts[1];
			$repo  = $endpoint_parts[2];
		}

		$details .= ' ' . __( 'Check that the GitHub owner and repository are correct, the GitHub App is installed on that repository, and the app has Actions read/write permission.', 'deploy-and-test' );

		if ( strpos( $repo, '/' ) !== false ) {
			$details .= ' ' . sprintf(
				/* translators: %s: repository field value. */
				__( 'The Repository field should contain only the repo name, for example "my-wordpress-site", not "%s".', 'deploy-and-test' ),
				$repo
			);
		}

		if ( $owner && $repo ) {
			$details .= ' ' . sprintf(
				/* translators: %s: GitHub owner/repository target. */
				__( 'Current repository target: %s.', 'deploy-and-test' ),
				$owner . '/' . $repo
			);
		}

		$details .= ' ' . sprintf(
			/* translators: %s: GitHub API endpoint. */
			__( 'GitHub endpoint: %s.', 'deploy-and-test' ),
			ltrim( $endpoint, '/' )
		);
	}

	return $details;
}

function deploy_and_test_github_app_is_configured() {
	return defined( 'DEPLOY_AND_TEST_GITHUB_APP_ID' )
		&& DEPLOY_AND_TEST_GITHUB_APP_ID
		&& defined( 'DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID' )
		&& DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID
		&& deploy_and_test_github_app_private_key_is_configured();
}

function deploy_and_test_github_app_private_key_is_configured() {
	return ( defined( 'DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH' ) && DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH )
		|| ( defined( 'DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY' ) && DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY );
}

function deploy_and_test_github_get_installation_token() {
	$jwt = deploy_and_test_github_generate_app_jwt();

	if ( is_wp_error( $jwt ) ) {
		return $jwt;
	}

	$response = wp_remote_post(
		'https://api.github.com/app/installations/' . rawurlencode( (string) DEPLOY_AND_TEST_GITHUB_INSTALLATION_ID ) . '/access_tokens',
		array(
			'headers' => array(
				'Accept'               => 'application/vnd.github+json',
				'Authorization'        => 'Bearer ' . $jwt,
				'X-GitHub-Api-Version' => '2022-11-28',
			),
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code     = wp_remote_retrieve_response_code( $response );
	$raw_body = wp_remote_retrieve_body( $response );
	$decoded  = $raw_body ? json_decode( $raw_body, true ) : array();

	if ( $code < 200 || $code >= 300 ) {
		$message = is_array( $decoded ) && ! empty( $decoded['message'] ) ? $decoded['message'] : __( 'Could not create GitHub App installation token.', 'deploy-and-test' );
		return new WP_Error( 'github_app_token_error', $message . ' HTTP ' . $code . '.' );
	}

	if ( empty( $decoded['token'] ) ) {
		return new WP_Error( 'github_app_token_missing', __( 'GitHub did not return an installation token.', 'deploy-and-test' ) );
	}

	return $decoded['token'];
}

function deploy_and_test_github_generate_app_jwt() {
	if ( ! defined( 'DEPLOY_AND_TEST_GITHUB_APP_ID' ) || ! DEPLOY_AND_TEST_GITHUB_APP_ID ) {
		return new WP_Error( 'missing_github_app_id', __( 'Missing DEPLOY_AND_TEST_GITHUB_APP_ID in wp-config.php.', 'deploy-and-test' ) );
	}

	if ( ! function_exists( 'openssl_sign' ) ) {
		return new WP_Error( 'openssl_missing', __( 'OpenSSL is required to sign GitHub App JWTs.', 'deploy-and-test' ) );
	}

	$private_key = deploy_and_test_github_get_private_key();

	if ( is_wp_error( $private_key ) ) {
		return $private_key;
	}

	$issued_at      = time() - 60;
	$expires_at     = $issued_at + ( 9 * 60 );
	$header         = array(
		'alg' => 'RS256',
		'typ' => 'JWT',
	);
	$payload        = array(
		'iat' => $issued_at,
		'exp' => $expires_at,
		'iss' => (string) DEPLOY_AND_TEST_GITHUB_APP_ID,
	);
	$unsigned_token = deploy_and_test_base64url_encode( wp_json_encode( $header ) ) . '.' . deploy_and_test_base64url_encode( wp_json_encode( $payload ) );
	$signature      = '';

	if ( ! openssl_sign( $unsigned_token, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
		return new WP_Error( 'github_app_jwt_sign_failed', __( 'Could not sign the GitHub App JWT.', 'deploy-and-test' ) );
	}

	return $unsigned_token . '.' . deploy_and_test_base64url_encode( $signature );
}

function deploy_and_test_github_get_private_key() {
	if ( defined( 'DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH' ) && DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH ) {
		$path = (string) DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH;

		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'github_app_private_key_unreadable', __( 'GitHub App private key file is not readable.', 'deploy-and-test' ) );
		}

		$key = file_get_contents( $path );

		if ( ! $key ) {
			return new WP_Error( 'github_app_private_key_empty', __( 'GitHub App private key file is empty.', 'deploy-and-test' ) );
		}

		return $key;
	}

	if ( defined( 'DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY' ) && DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY ) {
		return str_replace( '\n', "\n", (string) DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY );
	}

	return new WP_Error( 'missing_github_app_private_key', __( 'Missing DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY_PATH or DEPLOY_AND_TEST_GITHUB_APP_PRIVATE_KEY in wp-config.php.', 'deploy-and-test' ) );
}

function deploy_and_test_base64url_encode( $value ) {
	return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
}
