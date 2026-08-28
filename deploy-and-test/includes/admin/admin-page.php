<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_render_admin_page() {
	if ( ! current_user_can( deploy_and_test_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'deploy-and-test' ) );
	}

	$tab                 = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
	$can_manage_settings = current_user_can( deploy_and_test_settings_capability() );
	$allowed_tabs        = array( 'general' );

	if ( $can_manage_settings ) {
		$allowed_tabs[] = 'connection';
		$allowed_tabs[] = 'settings';
		$allowed_tabs[] = 'audit-log';
	}

	if ( ! in_array( $tab, $allowed_tabs, true ) ) {
		$tab = 'general';
	}

	$message    = isset( $_GET['deploy_and_test_message'] ) ? sanitize_text_field( wp_unslash( $_GET['deploy_and_test_message'] ) ) : '';
	$status     = isset( $_GET['deploy_and_test_status'] ) ? sanitize_key( $_GET['deploy_and_test_status'] ) : 'info';
	$configured = deploy_and_test_enabled_features_are_configured();
	if ( deploy_and_test_deploy_features_enabled() && deploy_and_test_test_features_enabled() ) {
		$configuration_message = __( 'Configure the enabled deploy and test features in Connection before using workflow actions.', 'deploy-and-test' );
	} elseif ( deploy_and_test_deploy_features_enabled() ) {
		$configuration_message = __( 'Configure the deploy repository and workflows in Connection before using deploy actions.', 'deploy-and-test' );
	} else {
		$configuration_message = __( 'Configure the testing repository and actions in Connection before using test actions.', 'deploy-and-test' );
	}

	?>
	<div class="wrap deploy-and-test-page">
		<h1>Deploy & Test</h1>

		<?php if ( $message ) : ?>
			<div class="notice notice-<?php echo esc_attr( $status === 'success' ? 'success' : ( $status === 'error' ? 'error' : 'info' ) ); ?> is-dismissible" data-testid="admin-feedback-notice">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! $configured ) : ?>
			<div class="notice notice-error" data-testid="configuration-notice">
			<p><?php echo esc_html( $configuration_message ); ?></p>
			</div>
		<?php endif; ?>

		<details class="deploy-and-test-howto">
			<summary><span class="deploy-and-test-howto-title"><?php echo esc_html__( 'How to use', 'deploy-and-test' ); ?></span></summary>
			<?php deploy_and_test_render_how_to_use_page(); ?>
		</details>

		<nav class="nav-tab-wrapper deploy-and-test-tabs" aria-label="<?php echo esc_attr__( 'Deploy tabs', 'deploy-and-test' ); ?>" data-testid="primary-tabs">
			<?php deploy_and_test_tab_link( 'general', __( 'General', 'deploy-and-test' ), $tab ); ?>
			<?php if ( $can_manage_settings ) : ?>
				<?php deploy_and_test_tab_link( 'connection', __( 'Connection', 'deploy-and-test' ), $tab ); ?>
				<?php deploy_and_test_tab_link( 'settings', __( 'Settings', 'deploy-and-test' ), $tab ); ?>
				<?php deploy_and_test_tab_link( 'audit-log', __( 'Audit log', 'deploy-and-test' ), $tab ); ?>
			<?php endif; ?>
		</nav>

		<?php if ( $tab === 'connection' ) : ?>
			<?php deploy_and_test_render_connection_tab(); ?>
		<?php elseif ( $tab === 'settings' ) : ?>
			<?php deploy_and_test_render_settings_tab(); ?>
		<?php elseif ( $tab === 'audit-log' ) : ?>
			<?php deploy_and_test_render_audit_log_tab(); ?>
		<?php else : ?>
			<?php deploy_and_test_render_general_tab( $configured ); ?>
		<?php endif; ?>
	</div>
	<?php
}

function deploy_and_test_render_how_to_use_page() {
	$template = DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/how-to-use-page/how-to-use-page.php';

	if ( file_exists( $template ) ) {
		include $template;
		return;
	}

	echo '<p>' . esc_html__( 'Instructions will be added here.', 'deploy-and-test' ) . '</p>';
}

function deploy_and_test_tab_link( $slug, $label, $active_tab ) {
	$url = add_query_arg(
		array(
			'page' => 'deploy-and-test',
			'tab'  => $slug,
		),
		admin_url( 'admin.php' )
	);

	printf(
		'<a href="%s" class="nav-tab %s" data-testid="tab-%s">%s</a>',
		esc_url( $url ),
		esc_attr( $active_tab === $slug ? 'nav-tab-active' : '' ),
		esc_attr( $slug ),
		esc_html( $label )
	);
}
