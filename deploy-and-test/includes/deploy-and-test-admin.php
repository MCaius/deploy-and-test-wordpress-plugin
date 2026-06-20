<?php
/**
 * Deploy & Test admin bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/constants.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/settings.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/audit-log.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/github.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/actions.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/admin/assets.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/admin/admin-page.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/admin/general-tab.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/admin/connection-tab.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/admin/audit-log-tab.php';
require_once DEPLOY_AND_TEST_PLUGIN_DIR . 'includes/hooks.php';
