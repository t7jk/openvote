<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Admin_Uninstall {

    private const CAP   = 'manage_options';
    private const NONCE = 'openvote_uninstall';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['openvote_uninstall_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( self::NONCE, 'openvote_uninstall_nonce' ) ) {
            wp_die( esc_html__( 'Nieprawidłowy token zabezpieczający.', 'openvote' ) );
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        // Require explicit confirmation checkbox.
        if ( empty( $_POST['openvote_confirm_uninstall'] ) ) {
            set_transient( 'openvote_uninstall_error', __( 'Zaznacz pole potwierdzenia przed usunięciem.', 'openvote' ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-settings' ) );
            exit;
        }

        self::run_cleanup();

        // Deactivate the plugin.
        deactivate_plugins( OPENVOTE_PLUGIN_BASENAME );

        // Redirect to the plugins page so the user can delete the plugin files.
        wp_safe_redirect( admin_url( 'plugins.php?openvote_uninstalled=1' ) );
        exit;
    }

    /**
     * Drop all plugin tables and delete all plugin options.
     * Called both from the admin UI handler and can be reused by uninstall.php.
     */
    public static function run_cleanup(): void {
        global $wpdb;

        // Drop tables in dependency order (votes → answers → questions → polls; group_members → groups).
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_votes" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_answers" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_questions" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_polls" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_group_members" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_groups" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_email_queue" );

        // Survey tables (answers → responses → questions → surveys).
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_survey_answers" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_survey_responses" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_survey_questions" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}openvote_surveys" );

        // Delete all plugin options (single source of list).
        foreach ( self::get_option_keys() as $key ) {
            delete_option( $key );
        }
    }

    /**
     * List of all openvote option keys to remove on uninstall.
     *
     * @return string[]
     */
    public static function get_option_keys(): array {
        return [
            'openvote_version',
            'openvote_db_version',
            'openvote_field_map',
            'openvote_vote_page_slug',
            'openvote_survey_page_slug',
            'openvote_submissions_page_slug',
            'openvote_time_offset_hours',
            'openvote_logo_attachment_id',
            'openvote_banner_attachment_id',
            'openvote_brand_short_name',
            'openvote_brand_full_name',
            'openvote_from_email',
            'openvote_mail_method',
            'openvote_smtp_host',
            'openvote_smtp_port',
            'openvote_smtp_encryption',
            'openvote_smtp_username',
            'openvote_smtp_password',
            'openvote_sendgrid_api_key',
            'openvote_email_batch_size',
            'openvote_email_batch_delay',
            'openvote_email_subject',
            'openvote_email_from_template',
            'openvote_email_body',
            'openvote_required_fields',
            'openvote_survey_required_fields',
            'openvote_law_slug',
        ];
    }
}
