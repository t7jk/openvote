<?php
/**
 * PHPUnit bootstrap for integration tests (real MySQL via WP test suite).
 *
 * Prerequisites:
 *   bash bin/install-wp-tests.sh openvote_test root password localhost latest
 *   cp wp-tests-config-sample.php wp-tests-config.php  # and fill credentials
 *
 * Run:
 *   WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
 */

$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (!is_dir($_tests_dir . '/includes')) {
    echo 'ERROR: WP test suite not found at ' . $_tests_dir . "\n";
    echo "Run: bash bin/install-wp-tests.sh openvote_test root password localhost latest\n";
    exit(1);
}

// Config file (DB credentials for test DB).
$_config_file = dirname(__DIR__) . '/wp-tests-config.php';
if (!is_file($_config_file)) {
    $_config_file = $_tests_dir . '/wp-tests-config.php';
}
if (!is_file($_config_file)) {
    echo "ERROR: wp-tests-config.php not found.\n";
    echo "Copy wp-tests-config-sample.php to wp-tests-config.php and edit it.\n";
    exit(1);
}
define('WP_TESTS_CONFIG_FILE_PATH', $_config_file);

// Load WP test suite functions (must happen before wp-tests-config.php defines constants).
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin and create schema in the test DB.
 */
function _openvote_load_plugin(): void {
    define('OPENVOTE_VERSION', '1.0.20');
    require_once dirname(__DIR__) . '/openvote.php';
}
tests_add_filter('muplugins_loaded', '_openvote_load_plugin');

/**
 * After WP is set up, activate plugin schema.
 */
function _openvote_activate(): void {
    Openvote_Activator::activate();
}
tests_add_filter('setup_theme', '_openvote_activate');

// Boot WP.
require_once $_tests_dir . '/includes/bootstrap.php';
