<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Load the cleanup helper so we don't duplicate the DROP TABLE logic.
require_once plugin_dir_path( __FILE__ ) . 'admin/class-openvote-admin-uninstall.php';

Openvote_Admin_Uninstall::run_cleanup();
