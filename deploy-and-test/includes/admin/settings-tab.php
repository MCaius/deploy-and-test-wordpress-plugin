<?php
/**
 * Plugin behavior settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_render_settings_tab() {
	$settings = deploy_and_test_get_settings();

	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="deploy-and-test-feature-settings-form" data-testid="feature-settings-form">
		<input type="hidden" name="action" value="deploy_and_test_save_feature_settings">
		<?php wp_nonce_field( 'deploy_and_test_save_feature_settings', 'deploy_and_test_nonce' ); ?>

		<section class="deploy-and-test-card deploy-and-test-settings-section">
			<h2><?php echo esc_html__( 'Features', 'deploy-and-test' ); ?></h2>
			<p class="deploy-and-test-muted"><?php echo esc_html__( 'Choose which workflow features are available. At least one feature must remain enabled.', 'deploy-and-test' ); ?></p>

			<div class="deploy-and-test-settings-toggles">
				<?php
				deploy_and_test_render_settings_toggle(
					'enable_deploy_features',
					__( 'Enable deploy features', 'deploy-and-test' ),
					__( 'Show deploy actions and status, and allow deploy polling and GitHub requests.', 'deploy-and-test' ),
					! empty( $settings['enable_deploy_features'] ),
					'feature-deploy-toggle',
					true
				);
				deploy_and_test_render_settings_toggle(
					'enable_test_features',
					__( 'Enable test features', 'deploy-and-test' ),
					__( 'Show test actions, environments, status, and summaries, and allow test polling and GitHub requests.', 'deploy-and-test' ),
					! empty( $settings['enable_test_features'] ),
					'feature-test-toggle',
					true
				);
				?>
			</div>

			<p class="deploy-and-test-settings-error" data-testid="feature-settings-validation" aria-live="polite" hidden>
				<?php echo esc_html__( 'Enable at least one feature: Deploy or Tests.', 'deploy-and-test' ); ?>
			</p>
		</section>

		<section class="deploy-and-test-card deploy-and-test-settings-section">
			<h2><?php echo esc_html__( 'Data and cleanup', 'deploy-and-test' ); ?></h2>
			<?php
			deploy_and_test_render_settings_toggle(
				'delete_data_on_uninstall',
				__( 'Delete plugin data on uninstall', 'deploy-and-test' ),
				__( 'When enabled, uninstalling the plugin removes Deploy & Test settings, audit logs, temporary locks, and cached test summaries from the database.', 'deploy-and-test' ),
				! empty( $settings['delete_data_on_uninstall'] ),
				'delete-data-on-uninstall-toggle'
			);
			?>
		</section>

		<?php submit_button( __( 'Save settings', 'deploy-and-test' ), 'primary', 'submit', true, array( 'data-testid' => 'save-feature-settings' ) ); ?>
	</form>
	<?php
}

function deploy_and_test_render_settings_toggle( $name, $label, $description, $checked, $test_id, $feature_toggle = false ) {
	?>
	<label class="deploy-and-test-toggle">
		<input
			type="checkbox"
			name="<?php echo esc_attr( $name ); ?>"
			value="1"
			data-testid="<?php echo esc_attr( $test_id ); ?>"
			<?php if ( $feature_toggle ) : ?>
				data-deploy-and-test-feature-toggle="1"
			<?php endif; ?>
			<?php checked( $checked ); ?>
		>
		<span class="deploy-and-test-toggle-control" aria-hidden="true"></span>
		<span>
			<strong><?php echo esc_html( $label ); ?></strong>
			<span class="deploy-and-test-toggle-description"><?php echo esc_html( $description ); ?></span>
		</span>
	</label>
	<?php
}
