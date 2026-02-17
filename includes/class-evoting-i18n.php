<?php
defined( 'ABSPATH' ) || exit;

class Evoting_I18n {

    public function load_plugin_textdomain(): void {
        load_plugin_textdomain( 'evoting', false, dirname( EVOTING_PLUGIN_BASENAME ) . '/languages' );
    }
}
