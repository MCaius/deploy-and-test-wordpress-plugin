<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_capability() {
	return 'edit_pages';
}

function deploy_and_test_settings_capability() {
	return 'manage_options';
}

function deploy_and_test_default_settings() {
	return array(
		'owner'                            => '',
		'repo'                             => '',
		'ref'                              => 'main',
		'preview_workflow'                 => 'deploy-preview.yml',
		'production_workflow'              => 'deploy-production.yml',
		'preview_target'                   => 'Preview environment',
		'production_target'                => 'Production environment',
		'test_repo'                        => '',
		'test_ref'                         => 'main',
		'test_environment_input'           => 'target_env',
		'test_environments'                => array(),
		'test_actions'                     => array(),
		'delete_data_on_uninstall'         => true,
		'delete_data_on_uninstall_touched' => false,
	);
}

function deploy_and_test_get_settings() {
	$settings = get_option( DEPLOY_AND_TEST_SETTINGS_OPTION, array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$settings = array_merge( deploy_and_test_default_settings(), $settings );

	if ( empty( $settings['delete_data_on_uninstall_touched'] ) ) {
		$settings['delete_data_on_uninstall'] = true;
	}

	return $settings;
}

function deploy_and_test_get_setting( $key ) {
	$settings = deploy_and_test_get_settings();

	return $settings[ $key ] ?? '';
}

function deploy_and_test_get_test_actions() {
	$settings = deploy_and_test_get_settings();
	$actions  = $settings['test_actions'] ?? array();

	return is_array( $actions ) ? $actions : array();
}

function deploy_and_test_get_test_environments() {
	$settings     = deploy_and_test_get_settings();
	$environments = $settings['test_environments'] ?? array();

	return is_array( $environments ) ? $environments : array();
}

function deploy_and_test_get_enabled_test_actions() {
	$actions = array_filter(
		deploy_and_test_get_test_actions(),
		function ( $action ) {
			return ! empty( $action['enabled'] ) && ! empty( $action['id'] ) && ! empty( $action['label'] ) && ! empty( $action['workflow'] );
		}
	);

	usort(
		$actions,
		function ( $a, $b ) {
			return (int) ( $a['order'] ?? 10 ) <=> (int) ( $b['order'] ?? 10 );
		}
	);

	return $actions;
}

function deploy_and_test_get_test_action( $test_action_id ) {
	foreach ( deploy_and_test_get_enabled_test_actions() as $test_action ) {
		if ( $test_action['id'] === $test_action_id ) {
			return $test_action;
		}
	}

	return null;
}

function deploy_and_test_tests_are_configured() {
	return deploy_and_test_github_app_is_configured()
		&& deploy_and_test_get_setting( 'owner' )
		&& deploy_and_test_get_setting( 'test_repo' )
		&& deploy_and_test_get_setting( 'test_ref' )
		&& deploy_and_test_get_enabled_test_actions();
}

function deploy_and_test_handle_save_settings() {
	if ( ! current_user_can( deploy_and_test_settings_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to update deploy settings.', 'deploy-and-test' ) );
	}

	check_admin_referer( 'deploy_and_test_save_settings', 'deploy_and_test_nonce' );

	$current_settings = deploy_and_test_get_settings();
	$settings         = array(
		'owner'                    => isset( $_POST['owner'] ) ? sanitize_text_field( wp_unslash( $_POST['owner'] ) ) : '',
		'repo'                     => isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '',
		'ref'                      => isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : 'main',
		'preview_workflow'         => isset( $_POST['preview_workflow'] ) ? sanitize_file_name( wp_unslash( $_POST['preview_workflow'] ) ) : 'deploy-preview.yml',
		'production_workflow'      => isset( $_POST['production_workflow'] ) ? sanitize_file_name( wp_unslash( $_POST['production_workflow'] ) ) : 'deploy-production.yml',
		'preview_target'           => isset( $_POST['preview_target'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_target'] ) ) : 'Preview environment',
		'production_target'        => isset( $_POST['production_target'] ) ? sanitize_text_field( wp_unslash( $_POST['production_target'] ) ) : 'Production environment',
		'test_repo'                => isset( $_POST['test_repo'] ) ? sanitize_text_field( wp_unslash( $_POST['test_repo'] ) ) : '',
		'test_ref'                 => isset( $_POST['test_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['test_ref'] ) ) : 'main',
		'test_environment_input'   => isset( $_POST['test_environment_input'] ) ? sanitize_key( wp_unslash( $_POST['test_environment_input'] ) ) : 'target_env',
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized recursively by deploy_and_test_sanitize_test_environments().
		'test_environments'        => isset( $_POST['test_environments'] ) ? deploy_and_test_sanitize_test_environments( wp_unslash( $_POST['test_environments'] ) ) : array(),
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized recursively by deploy_and_test_sanitize_test_actions().
		'test_actions'             => isset( $_POST['test_actions'] ) ? deploy_and_test_sanitize_test_actions( wp_unslash( $_POST['test_actions'] ) ) : array(),
		'delete_data_on_uninstall' => ! empty( $current_settings['delete_data_on_uninstall'] ),
	);

	$settings = deploy_and_test_normalize_settings( $settings );

	update_option( DEPLOY_AND_TEST_SETTINGS_OPTION, $settings, false );
	deploy_and_test_add_audit_log( 'save_settings', 'success', __( 'Deploy & Test settings were updated.', 'deploy-and-test' ) );
	deploy_and_test_redirect( 'success', __( 'Deploy & Test settings saved.', 'deploy-and-test' ), 'connection' );
}

function deploy_and_test_handle_save_cleanup_settings() {
	if ( ! current_user_can( deploy_and_test_settings_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to update deploy settings.', 'deploy-and-test' ) );
	}

	check_admin_referer( 'deploy_and_test_save_cleanup_settings', 'deploy_and_test_nonce' );

	$settings                                     = deploy_and_test_get_settings();
	$settings['delete_data_on_uninstall']         = ! empty( $_POST['delete_data_on_uninstall'] );
	$settings['delete_data_on_uninstall_touched'] = true;

	update_option( DEPLOY_AND_TEST_SETTINGS_OPTION, $settings, false );
	deploy_and_test_add_audit_log( 'save_cleanup_settings', 'success', __( 'Uninstall cleanup setting was updated.', 'deploy-and-test' ) );
	deploy_and_test_redirect( 'success', __( 'Uninstall cleanup setting saved.', 'deploy-and-test' ), 'general' );
}

function deploy_and_test_normalize_settings( $settings ) {
	$settings['owner']                  = trim( $settings['owner'] );
	$settings['repo']                   = trim( $settings['repo'] );
	$settings['test_repo']              = trim( $settings['test_repo'] );
	$settings['test_environment_input'] = $settings['test_environment_input'] ? $settings['test_environment_input'] : 'target_env';

	if ( strpos( $settings['repo'], '/' ) !== false ) {
		$parts = array_values( array_filter( explode( '/', $settings['repo'] ) ) );

		if ( count( $parts ) === 2 ) {
			$settings['owner'] = $parts[0];
			$settings['repo']  = $parts[1];
		}
	}

	if ( strpos( $settings['test_repo'], '/' ) !== false ) {
		$parts = array_values( array_filter( explode( '/', $settings['test_repo'] ) ) );

		if ( count( $parts ) === 2 ) {
			if ( ! $settings['owner'] ) {
				$settings['owner'] = $parts[0];
			}

			$settings['test_repo'] = $parts[1];
		}
	}

	return $settings;
}

function deploy_and_test_sanitize_test_actions( $actions ) {
	if ( ! is_array( $actions ) ) {
		return array();
	}

	$sanitized = array();
	$position  = 0;

	foreach ( $actions as $action ) {
		if ( ! is_array( $action ) ) {
			continue;
		}

		$label       = isset( $action['label'] ) ? sanitize_text_field( $action['label'] ) : '';
		$workflow    = isset( $action['workflow'] ) ? sanitize_file_name( $action['workflow'] ) : '';
		$input_name  = isset( $action['input_name'] ) ? sanitize_key( $action['input_name'] ) : '';
		$input_value = isset( $action['input_value'] ) ? sanitize_text_field( $action['input_value'] ) : '';

		if ( ! $label && ! $workflow ) {
			continue;
		}

		++$position;

		$sanitized[] = array(
			'id'          => sanitize_key( $label . '-' . $workflow . '-' . $input_value . '-' . $position ),
			'enabled'     => ! empty( $action['enabled'] ),
			'label'       => $label,
			'workflow'    => $workflow,
			'input_name'  => $input_name,
			'input_value' => $input_value,
			'order'       => isset( $action['order'] ) ? (int) $action['order'] : 10,
		);
	}

	usort(
		$sanitized,
		function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		}
	);

	return $sanitized;
}

function deploy_and_test_sanitize_test_environments( $environments ) {
	if ( ! is_array( $environments ) ) {
		return array();
	}

	$sanitized = array();

	foreach ( $environments as $environment ) {
		if ( ! is_array( $environment ) ) {
			continue;
		}

		$label = isset( $environment['label'] ) ? sanitize_text_field( $environment['label'] ) : '';
		$value = isset( $environment['value'] ) ? sanitize_text_field( $environment['value'] ) : '';

		if ( ! $label && ! $value ) {
			continue;
		}

		$sanitized[] = array(
			'label' => $label,
			'value' => $value,
		);
	}

	return $sanitized;
}

function deploy_and_test_get_test_environment( $environment_value ) {
	foreach ( deploy_and_test_get_test_environments() as $environment ) {
		if ( ( $environment['value'] ?? '' ) === $environment_value ) {
			return $environment;
		}
	}

	return null;
}

function deploy_and_test_is_configured() {
	return deploy_and_test_github_app_is_configured()
		&& deploy_and_test_get_setting( 'owner' )
		&& deploy_and_test_get_setting( 'repo' )
		&& deploy_and_test_get_setting( 'ref' )
		&& deploy_and_test_get_setting( 'preview_workflow' )
		&& deploy_and_test_get_setting( 'production_workflow' );
}

function deploy_and_test_redirect( $status, $message, $tab = 'general', $status_tab = '' ) {
	$args = array(
		'page'                    => 'deploy-and-test',
		'tab'                     => $tab,
		'deploy_and_test_status'  => $status,
		'deploy_and_test_message' => $message,
	);

	if ( $status_tab ) {
		$args['deploy_and_test_status_tab'] = $status_tab;
	}

	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}
