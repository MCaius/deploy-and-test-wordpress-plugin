<?php
/**
 * Shared Deploy & Test constants.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DEPLOY_AND_TEST_SETTINGS_OPTION        = 'deploy_and_test_settings';
const DEPLOY_AND_TEST_AUDIT_OPTION           = 'deploy_and_test_audit_log';
const DEPLOY_AND_TEST_AUDIT_LIMIT            = 100;
const DEPLOY_AND_TEST_DEPLOY_LOCK_TTL        = 120;
const DEPLOY_AND_TEST_TEST_SUMMARY_CACHE_TTL = 600;
