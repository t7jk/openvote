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
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_email_queue" );

        // Survey tables (answers → responses → questions → surveys).
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_survey_answers" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_survey_responses" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_survey_questions" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}evoting_surveys" );

        // Delete all plugin options (single source of list).
        foreach ( self::get_option_keys() as $key ) {
            delete_option( $key );
        }
    }

    /**
     * List of all evoting option keys to remove on uninstall.
     *
     * @return string[]
     */
    public static function get_option_keys(): array {
        return [
            'evoting_version',
            'evoting_db_version',
            'evoting_field_map',
            'evoting_vote_page_slug',
            'evoting_survey_page_slug',
            'evoting_submissions_page_slug',
            'evoting_time_offset_hours',
            'evoting_logo_attachment_id',
            'evoting_banner_attachment_id',
            'evoting_brand_short_name',
            'evoting_brand_full_name',
            'evoting_from_email',
            'evoting_mail_method',
            'evoting_smtp_host',
            'evoting_smtp_port',
            'evoting_smtp_encryption',
            'evoting_smtp_username',
            'evoting_smtp_password',
            'evoting_sendgrid_api_key',
            'evoting_email_batch_size',
            'evoting_email_batch_delay',
            'evoting_email_subject',
            'evoting_email_from_template',
            'evoting_email_body',
            'evoting_required_fields',
            'evoting_survey_required_fields',
            'evoting_law_slug',
        ];
    }
}
