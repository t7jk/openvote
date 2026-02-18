<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin_Settings {

    private const CAP   = 'manage_options';
    private const NONCE = 'evoting_save_settings';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['evoting_settings_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( self::NONCE, 'evoting_settings_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $raw_map = (array) ( $_POST['evoting_field_map'] ?? [] );
        Evoting_Field_Map::save( $raw_map );

        wp_safe_redirect(
            add_query_arg(
                [ 'page' => 'evoting-settings', 'saved' => '1' ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
