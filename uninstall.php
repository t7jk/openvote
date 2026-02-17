<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_votes" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_questions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_polls" );

delete_option( 'evoting_version' );
