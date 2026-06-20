<?php
/**
 * Deploy & Test module.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function deploy_and_test_render_audit_log_tab() {
	$logs = deploy_and_test_get_audit_log();

	?>
	<section class="deploy-and-test-card">
		<h2><?php echo esc_html__( 'Audit log', 'deploy-and-test' ); ?></h2>

		<?php if ( ! $logs ) : ?>
			<p class="deploy-and-test-muted"><?php echo esc_html__( 'No deploy actions logged yet.', 'deploy-and-test' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<table class="widefat striped deploy-and-test-audit-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Time', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'User', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Action', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'deploy-and-test' ); ?></th>
					<th><?php echo esc_html__( 'Details', 'deploy-and-test' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['user'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['action'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['status'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['details'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<?php
}
