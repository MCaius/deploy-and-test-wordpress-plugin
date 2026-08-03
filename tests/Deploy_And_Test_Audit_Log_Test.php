<?php
/**
 * Audit retention tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Audit_Log_Test extends Deploy_And_Test_Test_Case {
	public function test_audit_log_retains_only_the_newest_entries() {
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'qa-audit-admin',
			)
		);
		wp_set_current_user( $user_id );

		for ( $index = 0; $index < DEPLOY_AND_TEST_AUDIT_LIMIT + 5; ++$index ) {
			deploy_and_test_add_audit_log( 'retention_' . $index, 'success', 'Entry ' . $index );
		}

		$logs = deploy_and_test_get_audit_log();

		$this->assertCount( DEPLOY_AND_TEST_AUDIT_LIMIT, $logs );
		$this->assertSame( 'retention_104', $logs[0]['action'] );
		$this->assertSame( 'retention_5', $logs[ DEPLOY_AND_TEST_AUDIT_LIMIT - 1 ]['action'] );
		$this->assertSame( 'qa-audit-admin', $logs[0]['user'] );
	}
}
