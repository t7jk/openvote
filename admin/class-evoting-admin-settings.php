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

        $raw_required = array_map( 'sanitize_key', (array) ( $_POST['evoting_required_fields'] ?? [] ) );
        Evoting_Field_Map::save_required_fields( $raw_required );

        $raw_survey_required = array_map( 'sanitize_key', (array) ( $_POST['evoting_survey_required_fields'] ?? [] ) );
        Evoting_Field_Map::save_survey_required_fields( $raw_survey_required );

        if ( Evoting_Field_Map::is_city_disabled() ) {
            self::ensure_wszyscy_group_exists();
        }

        $slug = isset( $_POST['evoting_vote_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['evoting_vote_page_slug'] ) ) : '';
        $slug = $slug !== '' ? $slug : 'glosuj';
        update_option( 'evoting_vote_page_slug', $slug, false );

        // Slug strony ankiet.
        $survey_slug = isset( $_POST['evoting_survey_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['evoting_survey_page_slug'] ) ) : '';
        $survey_slug = $survey_slug !== '' ? $survey_slug : 'ankieta';
        update_option( 'evoting_survey_page_slug', $survey_slug, false );

        // Slug strony zgłoszeń (nie spam).
        $submissions_slug = isset( $_POST['evoting_submissions_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['evoting_submissions_page_slug'] ) ) : '';
        $submissions_slug = $submissions_slug !== '' ? $submissions_slug : 'zgloszenia';
        update_option( 'evoting_submissions_page_slug', $submissions_slug, false );

        $offset = isset( $_POST['evoting_time_offset_hours'] ) ? (int) $_POST['evoting_time_offset_hours'] : 0;
        $offset = max( -12, min( 12, $offset ) );
        update_option( 'evoting_time_offset_hours', $offset, false );

        // Logo i banner usunięte z konfiguracji — używane są Site Icon i Site Title z WordPress.

        $from_email = isset( $_POST['evoting_from_email'] ) ? sanitize_email( wp_unslash( $_POST['evoting_from_email'] ) ) : '';
        if ( $from_email === '' || ! is_email( $from_email ) ) {
            $domain     = wp_parse_url( home_url(), PHP_URL_HOST );
            $from_email = 'noreply@' . ( $domain ?: 'example.com' );
        }
        update_option( 'evoting_from_email', $from_email, false );

        $raw_method  = sanitize_key( wp_unslash( $_POST['evoting_mail_method'] ?? 'wordpress' ) );
        $mail_method = in_array( $raw_method, [ 'wordpress', 'smtp', 'sendgrid' ], true ) ? $raw_method : 'wordpress';
        update_option( 'evoting_mail_method', $mail_method, false );

        update_option( 'evoting_smtp_host',       sanitize_text_field( wp_unslash( $_POST['evoting_smtp_host']       ?? '' ) ), false );
        update_option( 'evoting_smtp_port',        (int) ( $_POST['evoting_smtp_port'] ?? 587 ), false );
        $enc = in_array( $_POST['evoting_smtp_encryption'] ?? '', [ 'tls', 'ssl', 'none' ], true )
            ? sanitize_key( $_POST['evoting_smtp_encryption'] )
            : 'tls';
        update_option( 'evoting_smtp_encryption',  $enc, false );
        update_option( 'evoting_smtp_username',    sanitize_text_field( wp_unslash( $_POST['evoting_smtp_username']  ?? '' ) ), false );
        if ( isset( $_POST['evoting_smtp_password'] ) && '' !== $_POST['evoting_smtp_password'] ) {
            update_option( 'evoting_smtp_password', sanitize_text_field( wp_unslash( $_POST['evoting_smtp_password'] ) ), false );
        }

        // SendGrid API key — zapisuj tylko gdy niepuste (zachowaj stary jeśli pole puste).
        if ( isset( $_POST['evoting_sendgrid_api_key'] ) && '' !== trim( $_POST['evoting_sendgrid_api_key'] ) ) {
            update_option( 'evoting_sendgrid_api_key', sanitize_text_field( wp_unslash( $_POST['evoting_sendgrid_api_key'] ) ), false );
        }

        // Parametry wysyłki wsadowej.
        $batch_size = isset( $_POST['evoting_email_batch_size'] ) ? absint( $_POST['evoting_email_batch_size'] ) : 0;
        $batch_size = min( 1000, $batch_size ); // max 1000
        update_option( 'evoting_email_batch_size', $batch_size, false );

        $batch_delay = isset( $_POST['evoting_email_batch_delay'] ) ? absint( $_POST['evoting_email_batch_delay'] ) : 0;
        $batch_delay = min( 60, $batch_delay ); // max 60 s
        update_option( 'evoting_email_batch_delay', $batch_delay, false );

        $short_name = isset( $_POST['evoting_brand_short_name'] ) ? sanitize_text_field( wp_unslash( $_POST['evoting_brand_short_name'] ) ) : '';
        $short_name = mb_substr( trim( $short_name ), 0, 6 );
        if ( $short_name === '' ) {
            $short_name = 'EP-RWL';
        }
        update_option( 'evoting_brand_short_name', $short_name, false );

        // Pełna nazwa pobierana z WordPress Site Title — nie zapisujemy w opcjach wtyczki.

        // ── Szablon e-maila zapraszającego ──────────────────────────────────
        update_option(
            'evoting_email_subject',
            sanitize_text_field( wp_unslash( $_POST['evoting_email_subject'] ?? '' ) ),
            false
        );
        update_option(
            'evoting_email_from_template',
            sanitize_text_field( wp_unslash( $_POST['evoting_email_from_template'] ?? '' ) ),
            false
        );
        // Treść może zawierać znaki nowej linii — używamy wp_kses_post aby nie kasował \n.
        $email_body = wp_unslash( $_POST['evoting_email_body'] ?? '' );
        $email_body = wp_strip_all_tags( $email_body );
        update_option( 'evoting_email_body', $email_body, false );

        flush_rewrite_rules();

        $query_args = [ 'page' => 'evoting-settings', 'saved' => '1' ];

        if ( ! empty( $_POST['evoting_create_vote_page'] ) && ! evoting_vote_page_exists() ) {
            $page_id = self::create_vote_page( $slug );
            if ( $page_id && ! is_wp_error( $page_id ) ) {
                $query_args['page_created'] = '1';
            }
        }

        // Zaktualizuj istniejącą stronę do nowego bloku zakładek.
        if ( ! empty( $_POST['evoting_update_vote_page'] ) ) {
            $updated = self::update_vote_page_block( $slug );
            if ( $updated ) {
                $query_args['page_updated'] = '1';
            }
        }

        // Strona ankiet: utwórz jeśli nie istnieje.
        if ( ! empty( $_POST['evoting_create_survey_page'] ) ) {
            $surv_page = get_page_by_path( $survey_slug, OBJECT, 'page' );
            if ( ! $surv_page ) {
                $page_id = self::create_survey_page( $survey_slug );
                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    $query_args['survey_page_created'] = '1';
                }
            }
        }

        // Strona ankiet: zaktualizuj blok jeśli brakuje.
        if ( ! empty( $_POST['evoting_update_survey_page'] ) ) {
            $updated = self::update_survey_page_block( $survey_slug );
            if ( $updated ) {
                $query_args['survey_page_updated'] = '1';
            }
        }

        // Strona zgłoszeń: utwórz jeśli nie istnieje.
        if ( ! empty( $_POST['evoting_create_submissions_page'] ) ) {
            $submissions_slug = get_option( 'evoting_submissions_page_slug', 'zgloszenia' );
            $subm_page = get_page_by_path( $submissions_slug, OBJECT, 'page' );
            if ( ! $subm_page ) {
                $page_id = self::create_submissions_page( $submissions_slug );
                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    $query_args['submissions_page_created'] = '1';
                }
            }
        }

        // Strona zgłoszeń: zaktualizuj blok jeśli brakuje.
        if ( ! empty( $_POST['evoting_update_submissions_page'] ) ) {
            $submissions_slug = get_option( 'evoting_submissions_page_slug', 'zgloszenia' );
            $updated = self::update_submissions_page_block( $submissions_slug );
            if ( $updated ) {
                $query_args['submissions_page_updated'] = '1';
            }
        }

        wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
        exit;
    }


    /**
     * Aktualizuje tresc istniejącej strony głosowania do bloku evoting/voting-tabs.
     */
    public static function update_vote_page_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        $result = wp_update_post( [
            'ID'           => $page->ID,
            'post_content' => '<!-- wp:evoting/voting-tabs /-->',
        ] );
        return $result && ! is_wp_error( $result );
    }

    /**
     * Tworzy grupę "Wszyscy" jeśli nie istnieje (tryb "Nie używaj miast").
     */
    public static function ensure_wszyscy_group_exists(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'evoting_groups';
        $name  = Evoting_Field_Map::WSZYSCY_NAME;
        $id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s", $name ) );
        if ( ! $id ) {
            $wpdb->insert(
                $table,
                [ 'name' => $name, 'type' => 'custom', 'description' => null, 'member_count' => 0 ],
                [ '%s', '%s', '%s', '%d' ]
            );
        }
    }

    /**
     * Tworzy stronę z blokiem głosowań pod podanym slugiem.
     *
     * @param string $slug Slug strony (np. „glosuj”).
     * @return int|WP_Error ID utworzonej strony lub błąd.
     */
    public static function create_vote_page( string $slug ) {
        // Blok zakładek głosowań — dynamiczny, renderowany server-side.
        $block_content = '<!-- wp:evoting/voting-tabs /-->';
        return wp_insert_post(
            [
                'post_type'    => 'page',
                'post_title'   => _x( 'Głosowanie', 'vote page title', 'evoting' ),
                'post_name'    => $slug,
                'post_content' => $block_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ],
            true
        );
    }

    /**
     * Tworzy stronę z blokiem ankiet pod podanym slugiem.
     */
    public static function create_survey_page( string $slug ) {
        $block_content = '<!-- wp:evoting/survey-form /-->';
        return wp_insert_post(
            [
                'post_type'    => 'page',
                'post_title'   => _x( 'Ankiety', 'survey page title', 'evoting' ),
                'post_name'    => $slug,
                'post_content' => $block_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ],
            true
        );
    }

    /**
     * Aktualizuje treść strony ankiet do bloku evoting/survey-form.
     */
    public static function update_survey_page_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        $result = wp_update_post( [
            'ID'           => $page->ID,
            'post_content' => '<!-- wp:evoting/survey-form /-->',
        ] );
        return $result && ! is_wp_error( $result );
    }

    /**
     * Tworzy stronę z blokiem zgłoszeń (nie spam) pod podanym slugiem.
     */
    public static function create_submissions_page( string $slug ) {
        $block_content = '<!-- wp:evoting/survey-responses /-->';
        return wp_insert_post(
            [
                'post_type'    => 'page',
                'post_title'   => _x( 'Zgłoszenia', 'submissions page title', 'evoting' ),
                'post_name'    => $slug,
                'post_content' => $block_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ],
            true
        );
    }

    /**
     * Aktualizuje treść strony zgłoszeń do bloku evoting/survey-responses.
     */
    public static function update_submissions_page_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        $result = wp_update_post( [
            'ID'           => $page->ID,
            'post_content' => '<!-- wp:evoting/survey-responses /-->',
        ] );
        return $result && ! is_wp_error( $result );
    }

    /**
     * Czy strona o podanym slugu ma blok evoting/survey-responses.
     */
    public static function page_has_submissions_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        return has_block( 'evoting/survey-responses', $page );
    }
}
