<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin_Uninstall {

    private const CAP   = 'manage_options';
    private const NONCE = 'evoting_uninstall';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['evoting_uninstall_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( self::NONCE, 'evoting_uninstall_nonce' ) ) {
            wp_die( esc_html__( 'Nieprawidłowy token zabezpieczający.', 'evoting' ) );
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        // Require explicit confirmation checkbox.
        if ( empty( $_POST['evoting_confirm_uninstall'] ) ) {
            set_transient( 'evoting_uninstall_error', __( 'Zaznacz pole potwierdzenia przed usunięciem.', 'evoting' ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=evoting-settings' ) );
            exit;
        }

        self::run_cleanup();

        // Deactivate the plugin.
        deactivate_plugins( EVOTING_PLUGIN_BASENAME );

        // Redirect to the plugins page so the user can delete the plugin files.
        wp_safe_redirect( admin_url( 'plugins.php?evoting_uninstalled=1' ) );
        exit;
    }

    /**
     * Drop all plugin tables and delete all plugin options.
     * Called both from the admin UI handler and can be reused by uninstall.php.
     */
    public static function run_cleanup(): void {
        global $wpdb;

        // Drop tables in dependency order (votes → answers → questions → polls; group_members → groups).
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_votes" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_answers" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_questions" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_polls" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_group_members" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_groups" );

        // Delete plugin options.
        delete_option( 'evoting_version' );
        delete_option( 'evoting_db_version' );
        delete_option( 'evoting_field_map' );
        delete_option( 'evoting_vote_page_slug' );
        delete_option( 'evoting_time_offset_hours' );
        delete_option( 'evoting_logo_attachment_id' );
        delete_option( 'evoting_banner_attachment_id' );
        delete_option( 'evoting_brand_short_name' );
        delete_option( 'evoting_brand_full_name' );
    }
}
