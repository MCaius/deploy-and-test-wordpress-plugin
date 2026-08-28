<?php
/**
 * GitHub workflow status mapping tests.
 */

require_once __DIR__ . '/test-case.php';

class Deploy_And_Test_Status_Test extends Deploy_And_Test_Test_Case {
	/**
	 * @dataProvider run_state_provider
	 */
	public function test_run_state_mapping( $status, $conclusion, $expected ) {
		$run = array(
			'status'     => $status,
			'conclusion' => $conclusion,
		);

		$this->assertSame( $expected, deploy_and_test_get_run_state( $run ) );
	}

	public function run_state_provider() {
		return array(
			'queued'             => array( 'queued', '', 'queued' ),
			'in progress'        => array( 'in_progress', '', 'running' ),
			'waiting'            => array( 'waiting', '', 'running' ),
			'pending'            => array( 'pending', '', 'running' ),
			'success'            => array( 'completed', 'success', 'success' ),
			'failure'            => array( 'completed', 'failure', 'failed' ),
			'timed out'          => array( 'completed', 'timed_out', 'failed' ),
			'cancelled'          => array( 'completed', 'cancelled', 'failed' ),
			'unknown conclusion' => array( 'completed', 'neutral', 'idle' ),
		);
	}

	public function test_deploy_status_ignores_unrelated_runs_and_tracks_latest_and_active_runs() {
		$this->configure_plugin();
		$preview_latest = array(
			'id'         => 20,
			'name'       => 'Preview sandbox deployment',
			'status'     => 'completed',
			'conclusion' => 'success',
		);
		$preview_active = array(
			'id'     => 19,
			'name'   => 'Preview sandbox deployment',
			'status' => 'in_progress',
		);
		$production_active = array(
			'id'     => 18,
			'name'   => 'Production sandbox deployment',
			'status' => 'queued',
		);
		$runs = array(
			array( 'id' => 21, 'name' => 'Unrelated workflow', 'status' => 'completed' ),
			$preview_latest,
			$preview_active,
			$production_active,
		);

		$status = deploy_and_test_get_deploy_status( $runs );

		$this->assertSame( $preview_latest, $status['preview']['latest'] );
		$this->assertSame( $preview_active, $status['preview']['active'] );
		$this->assertSame( $production_active, $status['production']['latest'] );
		$this->assertSame( $production_active, $status['production']['active'] );
		$this->assertTrue( deploy_and_test_status_has_active_run( $status ) );
	}

	public function test_deploy_status_ignores_an_unconfigured_environment() {
		$this->configure_plugin( array( 'production_workflow' => '' ) );
		$runs = array(
			array( 'id' => 20, 'name' => 'Deploy Preview', 'status' => 'completed', 'conclusion' => 'success' ),
			array( 'id' => 19, 'name' => 'Deploy Production', 'status' => 'in_progress' ),
		);

		$status = deploy_and_test_get_deploy_status( $runs );

		$this->assertSame( 20, $status['preview']['latest']['id'] );
		$this->assertNull( $status['production']['latest'] );
		$this->assertNull( $status['production']['active'] );
		$this->assertFalse( deploy_and_test_status_has_active_run( $status ) );
	}

	public function test_test_status_tracks_the_first_latest_and_active_runs() {
		$latest = array( 'id' => 30, 'status' => 'completed', 'conclusion' => 'success' );
		$active = array( 'id' => 29, 'status' => 'in_progress' );

		$status = deploy_and_test_get_test_status( array( $latest, $active ) );

		$this->assertSame( $latest, $status['latest'] );
		$this->assertSame( $active, $status['active'] );
		$this->assertTrue( deploy_and_test_test_status_has_active_run( $status ) );
	}

	public function test_only_configured_test_workflow_paths_are_accepted() {
		$this->configure_plugin();

		$this->assertTrue(
			deploy_and_test_test_run_uses_configured_workflow(
				array( 'path' => '.github/workflows/smoke-tests.yml' )
			)
		);
		$this->assertFalse(
			deploy_and_test_test_run_uses_configured_workflow(
				array( 'path' => '.github/workflows/unrelated.yml' )
			)
		);
		$this->assertFalse( deploy_and_test_test_run_uses_configured_workflow( array() ) );
	}
}
