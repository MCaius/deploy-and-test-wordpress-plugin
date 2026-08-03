<?php
/**
 * Role and capability tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Permissions_Test extends Deploy_And_Test_Test_Case {
	public function test_administrator_can_operate_and_configure_the_plugin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( current_user_can( deploy_and_test_capability() ) );
		$this->assertTrue( current_user_can( deploy_and_test_settings_capability() ) );
	}

	public function test_editor_can_operate_but_cannot_configure_the_plugin() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( current_user_can( deploy_and_test_capability() ) );
		$this->assertFalse( current_user_can( deploy_and_test_settings_capability() ) );
	}

	public function test_subscriber_cannot_access_plugin_operations_or_settings() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( deploy_and_test_capability() ) );
		$this->assertFalse( current_user_can( deploy_and_test_settings_capability() ) );
	}
}
