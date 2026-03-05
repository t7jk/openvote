<?php
/**
 * Plugin Name: Fix Headers Already Sent (Blocksy / redirects)
 * Description: Starts output buffering so WordPress can send redirect headers even when
 *              the theme outputs CSS early (e.g. Blocksy dynamic CSS in admin).
 *              Copy this file to wp-content/mu-plugins/ob-headers-fix.php
 * Version:     1.0
 * Author:      (your name)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	ob_start();
}, 0 );

add_action( 'shutdown', function () {
	if ( ob_get_level() ) {
		ob_end_flush();
	}
}, 0 );
