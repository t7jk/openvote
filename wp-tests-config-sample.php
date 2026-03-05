<?php
/**
 * Sample WordPress test configuration.
 *
 * Copy this file to wp-tests-config.php and fill in your database credentials.
 * wp-tests-config.php is in .gitignore and must never be committed.
 *
 * Usage:
 *   cp wp-tests-config-sample.php wp-tests-config.php
 *   # edit wp-tests-config.php with your local DB credentials
 *   bash bin/install-wp-tests.sh openvote_test root password localhost latest
 *   WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
 */

define( 'DB_NAME',     'openvote_test' );
define( 'DB_USER',     'root' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST',     'localhost' );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN',         'example.org' );
define( 'WP_TESTS_EMAIL',          'admin@example.org' );
define( 'WP_TESTS_TITLE',          'Test Blog' );
define( 'WP_PHP_BINARY',           'php' );
define( 'WPLANG',                  '' );
define( 'WP_DEBUG',                true );
