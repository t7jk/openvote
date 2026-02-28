<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Admin_Settings {

    private const CAP            = 'manage_options';
    private const NONCE         = 'openvote_save_settings';
    private const CREATE_NONCE  = 'openvote_create_page';

    public function handle_form_submission(): void {
        // Route do czyszczenia bazy — osobny nonce, osobna akcja.
        if ( isset( $_POST['openvote_clean_nonce'] ) ) {
            $this->handle_clean_database();
            return;
        }

        // Osobna ścieżka: utworzenie strony (przycisk „Utwórz stronę…”). Akceptujemy nonce create LUB settings (po rename evoting→openvote oba mogą być w formularzu).
        $has_create_button = ! empty( $_POST['openvote_create_vote_page'] )
            || ! empty( $_POST['openvote_create_survey_page'] )
            || ! empty( $_POST['openvote_create_submissions_page'] );
        if ( $has_create_button && current_user_can( self::CAP ) ) {
            $create_nonce_ok = isset( $_POST['openvote_create_page_nonce'] )
                && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openvote_create_page_nonce'] ) ), self::CREATE_NONCE );
            $settings_nonce_ok = isset( $_POST['openvote_settings_nonce'] )
                && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openvote_settings_nonce'] ) ), self::NONCE );
            if ( $create_nonce_ok || $settings_nonce_ok ) {
                $redirect = $this->handle_create_page_only();
                if ( $redirect ) {
                    wp_safe_redirect( $redirect );
                    exit;
                }
            }
        }

        if ( ! isset( $_POST['openvote_settings_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( self::NONCE, 'openvote_settings_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $raw_map = (array) ( $_POST['openvote_field_map'] ?? [] );
        Openvote_Field_Map::save( $raw_map );

        $raw_required = array_map( 'sanitize_key', (array) ( $_POST['openvote_required_fields'] ?? [] ) );
        Openvote_Field_Map::save_required_fields( $raw_required );

        $raw_survey_required = array_map( 'sanitize_key', (array) ( $_POST['openvote_survey_required_fields'] ?? [] ) );
        Openvote_Field_Map::save_survey_required_fields( $raw_survey_required );

        if ( Openvote_Field_Map::is_city_disabled() ) {
            self::ensure_wszyscy_group_exists();
        }

        $slug = isset( $_POST['openvote_vote_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['openvote_vote_page_slug'] ) ) : '';
        $slug = $slug !== '' ? $slug : 'glosuj';
        update_option( 'openvote_vote_page_slug', $slug, false );

        // Slug strony ankiet.
        $survey_slug = isset( $_POST['openvote_survey_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['openvote_survey_page_slug'] ) ) : '';
        $survey_slug = $survey_slug !== '' ? $survey_slug : 'ankieta';
        update_option( 'openvote_survey_page_slug', $survey_slug, false );

        // Slug strony zgłoszeń (nie spam).
        $submissions_slug = isset( $_POST['openvote_submissions_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['openvote_submissions_page_slug'] ) ) : '';
        $submissions_slug = $submissions_slug !== '' ? $submissions_slug : 'zgloszenia';
        update_option( 'openvote_submissions_page_slug', $submissions_slug, false );

        $offset = isset( $_POST['openvote_time_offset_hours'] ) ? (int) $_POST['openvote_time_offset_hours'] : 0;
        $offset = max( -12, min( 12, $offset ) );
        update_option( 'openvote_time_offset_hours', $offset, false );

        // Logo i banner usunięte z konfiguracji — używane są Site Icon i Site Title z WordPress.

        $from_email = isset( $_POST['openvote_from_email'] ) ? sanitize_email( wp_unslash( $_POST['openvote_from_email'] ) ) : '';
        if ( $from_email === '' || ! is_email( $from_email ) ) {
            $domain     = wp_parse_url( home_url(), PHP_URL_HOST );
            $from_email = 'noreply@' . ( $domain ?: 'example.com' );
        }
        update_option( 'openvote_from_email', $from_email, false );

        $raw_method  = sanitize_key( wp_unslash( $_POST['openvote_mail_method'] ?? 'wordpress' ) );
        $mail_method = in_array( $raw_method, [ 'wordpress', 'smtp', 'sendgrid' ], true ) ? $raw_method : 'wordpress';
        update_option( 'openvote_mail_method', $mail_method, false );

        update_option( 'openvote_smtp_host',       sanitize_text_field( wp_unslash( $_POST['openvote_smtp_host']       ?? '' ) ), false );
        $smtp_port = (int) ( $_POST['openvote_smtp_port'] ?? 587 );
        $smtp_port = max( 1, min( 65535, $smtp_port ) );
        update_option( 'openvote_smtp_port', $smtp_port, false );
        $enc = in_array( $_POST['openvote_smtp_encryption'] ?? '', [ 'tls', 'ssl', 'none' ], true )
            ? sanitize_key( $_POST['openvote_smtp_encryption'] )
            : 'tls';
        update_option( 'openvote_smtp_encryption',  $enc, false );
        update_option( 'openvote_smtp_username',    sanitize_text_field( wp_unslash( $_POST['openvote_smtp_username']  ?? '' ) ), false );
        if ( isset( $_POST['openvote_smtp_password'] ) && '' !== $_POST['openvote_smtp_password'] ) {
            update_option( 'openvote_smtp_password', sanitize_text_field( wp_unslash( $_POST['openvote_smtp_password'] ) ), false );
        }

        // SendGrid API key — zapisuj tylko gdy niepuste (zachowaj stary jeśli pole puste).
        if ( isset( $_POST['openvote_sendgrid_api_key'] ) && '' !== trim( $_POST['openvote_sendgrid_api_key'] ) ) {
            update_option( 'openvote_sendgrid_api_key', sanitize_text_field( wp_unslash( $_POST['openvote_sendgrid_api_key'] ) ), false );
        }

        // Parametry wysyłki wsadowej.
        $batch_size = isset( $_POST['openvote_email_batch_size'] ) ? absint( $_POST['openvote_email_batch_size'] ) : 0;
        $batch_size = min( 1000, $batch_size ); // max 1000
        update_option( 'openvote_email_batch_size', $batch_size, false );

        $batch_delay = isset( $_POST['openvote_email_batch_delay'] ) ? absint( $_POST['openvote_email_batch_delay'] ) : 0;
        $batch_delay = min( 60, $batch_delay ); // max 60 s
        update_option( 'openvote_email_batch_delay', $batch_delay, false );

        $short_name = isset( $_POST['openvote_brand_short_name'] ) ? sanitize_text_field( wp_unslash( $_POST['openvote_brand_short_name'] ) ) : '';
        $short_name = mb_substr( trim( $short_name ), 0, 12 );
        if ( $short_name === '' ) {
            $short_name = 'OpenVote';
        }
        update_option( 'openvote_brand_short_name', $short_name, false );

        // Pełna nazwa pobierana z WordPress Site Title — nie zapisujemy w opcjach wtyczki.

        // ── Szablon e-maila zapraszającego ──────────────────────────────────
        update_option(
            'openvote_email_subject',
            sanitize_text_field( wp_unslash( $_POST['openvote_email_subject'] ?? '' ) ),
            false
        );
        update_option(
            'openvote_email_from_template',
            sanitize_text_field( wp_unslash( $_POST['openvote_email_from_template'] ?? '' ) ),
            false
        );
        // Treść może zawierać znaki nowej linii — używamy wp_kses_post aby nie kasował \n.
        $email_body = wp_unslash( $_POST['openvote_email_body'] ?? '' );
        $email_body = wp_strip_all_tags( $email_body );
        update_option( 'openvote_email_body', $email_body, false );

        flush_rewrite_rules();

        $query_args = [ 'page' => 'openvote-settings', 'saved' => '1' ];

        if ( ! empty( $_POST['openvote_create_vote_page'] ) && ! openvote_vote_page_exists() ) {
            $page_id = self::create_vote_page( $slug );
            if ( $page_id && ! is_wp_error( $page_id ) ) {
                $query_args['page_created'] = '1';
            }
        }

        // Zaktualizuj istniejącą stronę do nowego bloku zakładek.
        if ( ! empty( $_POST['openvote_update_vote_page'] ) ) {
            $updated = self::update_vote_page_block( $slug );
            if ( $updated ) {
                $query_args['page_updated'] = '1';
            }
        }

        // Strona ankiet: utwórz jeśli nie istnieje.
        if ( ! empty( $_POST['openvote_create_survey_page'] ) ) {
            $surv_page = get_page_by_path( $survey_slug, OBJECT, 'page' );
            if ( ! $surv_page ) {
                $page_id = self::create_survey_page( $survey_slug );
                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    $query_args['survey_page_created'] = '1';
                }
            }
        }

        // Strona ankiet: zaktualizuj blok jeśli brakuje.
        if ( ! empty( $_POST['openvote_update_survey_page'] ) ) {
            $updated = self::update_survey_page_block( $survey_slug );
            if ( $updated ) {
                $query_args['survey_page_updated'] = '1';
            } else {
                $query_args['survey_page_update_error'] = '1';
            }
        }

        // Strona zgłoszeń: utwórz jeśli nie istnieje.
        if ( ! empty( $_POST['openvote_create_submissions_page'] ) ) {
            $submissions_slug = get_option( 'openvote_submissions_page_slug', 'zgloszenia' );
            $subm_page = get_page_by_path( $submissions_slug, OBJECT, 'page' );
            if ( ! $subm_page ) {
                $page_id = self::create_submissions_page( $submissions_slug );
                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    $query_args['submissions_page_created'] = '1';
                }
            }
        }

        // Strona zgłoszeń: zaktualizuj blok jeśli brakuje.
        if ( ! empty( $_POST['openvote_update_submissions_page'] ) ) {
            $submissions_slug = get_option( 'openvote_submissions_page_slug', 'zgloszenia' );
            $updated = self::update_submissions_page_block( $submissions_slug );
            if ( $updated ) {
                $query_args['submissions_page_updated'] = '1';
            } else {
                $query_args['submissions_page_update_error'] = '1';
            }
        }

        wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Obsługa żądania „tylko utwórz stronę” (osobny nonce, minimalny POST).
     * Zwraca URL przekierowania lub null, gdy żaden przycisk tworzenia nie został wysłany.
     */
    private function handle_create_page_only(): ?string {
        $base = add_query_arg( [ 'page' => 'openvote-settings', 'saved' => '1' ], admin_url( 'admin.php' ) );

        if ( ! empty( $_POST['openvote_create_vote_page'] ) ) {
            $slug = isset( $_POST['openvote_vote_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['openvote_vote_page_slug'] ) ) : '';
            $slug = $slug !== '' ? $slug : 'glosuj';
            update_option( 'openvote_vote_page_slug', $slug, false );
            if ( ! openvote_vote_page_exists() ) {
                $page_id = self::create_vote_page( $slug );
                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    flush_rewrite_rules();
                    return add_query_arg( 'page_created', '1', $base );
                }
            }
            return $base;
        }

        if ( ! empty( $_POST['openvote_create_survey_page'] ) ) {
            $slug = isset( $_POST['openvote_survey_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['openvote_survey_page_slug'] ) ) : '';
            $slug = $slug !== '' ? $slug : 'ankieta';
            update_option( 'openvote_survey_page_slug', $slug, false );
            $surv_page = get_page_by_path( $slug, OBJECT, 'page' );
            if ( ! $surv_page ) {
                $page_id = self::create_survey_page( $slug );
                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    flush_rewrite_rules();
                    return add_query_arg( 'survey_page_created', '1', $base );
                }
            }
            return $base;
        }

        if ( ! empty( $_POST['openvote_create_submissions_page'] ) ) {
            $slug = isset( $_POST['openvote_submissions_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['openvote_submissions_page_slug'] ) ) : '';
            $slug = $slug !== '' ? $slug : 'zgloszenia';
            update_option( 'openvote_submissions_page_slug', $slug, false );
            $subm_page = get_page_by_path( $slug, OBJECT, 'page' );
            if ( ! $subm_page ) {
                $page_id = self::create_submissions_page( $slug );
                if ( $page_id && ! is_wp_error( $page_id ) ) {
                    flush_rewrite_rules();
                    return add_query_arg( 'submissions_page_created', '1', $base );
                }
            }
            return $base;
        }

        return null;
    }

    /**
     * Aktualizuje tresc istniejącej strony głosowania do bloku openvote/voting-tabs.
     */
    public static function update_vote_page_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        $result = wp_update_post( [
            'ID'           => $page->ID,
            'post_content' => '<!-- wp:openvote/voting-tabs /-->',
        ] );
        return $result && ! is_wp_error( $result );
    }

    /**
     * Tworzy grupę "Wszyscy" jeśli nie istnieje (tryb "Nie używaj miast").
     */
    public static function ensure_wszyscy_group_exists(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'openvote_groups';
        $name  = Openvote_Field_Map::WSZYSCY_NAME;
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
        $block_content = '<!-- wp:openvote/voting-tabs /-->';
        return wp_insert_post(
            [
                'post_type'    => 'page',
                'post_title'   => _x( 'Głosowanie', 'vote page title', 'openvote' ),
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
        $block_content = '<!-- wp:openvote/survey-form /-->';
        return wp_insert_post(
            [
                'post_type'    => 'page',
                'post_title'   => _x( 'Ankiety', 'survey page title', 'openvote' ),
                'post_name'    => $slug,
                'post_content' => $block_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ],
            true
        );
    }

    /**
     * Aktualizuje treść strony ankiet do bloku openvote/survey-form.
     */
    public static function update_survey_page_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        $result = wp_update_post( [
            'ID'           => $page->ID,
            'post_content' => '<!-- wp:openvote/survey-form /-->',
        ] );
        return $result && ! is_wp_error( $result );
    }

    /**
     * Tworzy stronę z blokiem zgłoszeń (nie spam) pod podanym slugiem.
     */
    public static function create_submissions_page( string $slug ) {
        $block_content = '<!-- wp:openvote/survey-responses /-->';
        return wp_insert_post(
            [
                'post_type'    => 'page',
                'post_title'   => _x( 'Zgłoszenia', 'submissions page title', 'openvote' ),
                'post_name'    => $slug,
                'post_content' => $block_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ],
            true
        );
    }

    /**
     * Aktualizuje treść strony zgłoszeń do bloku openvote/survey-responses.
     */
    public static function update_submissions_page_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        $result = wp_update_post( [
            'ID'           => $page->ID,
            'post_content' => '<!-- wp:openvote/survey-responses /-->',
        ] );
        return $result && ! is_wp_error( $result );
    }

    /**
     * Wyczyść bazę danych wtyczki i przywróć ustawienia fabryczne.
     * TRUNCATE wszystkich tabel danych, usuwa role koordynatorów z usermeta,
     * kasuje opcje wtyczki (poza wersją DB).
     */
    private function handle_clean_database(): void {
        if ( ! check_admin_referer( 'openvote_clean_database', 'openvote_clean_nonce' ) ) {
            wp_die( esc_html__( 'Nieprawidłowy token zabezpieczający.', 'openvote' ) );
        }
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }
        if ( empty( $_POST['openvote_confirm_clean'] ) ) {
            set_transient( 'openvote_clean_error',
                __( 'Zaznacz pole potwierdzenia przed wyczyszczeniem.', 'openvote' ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-settings' ) );
            exit;
        }

        global $wpdb;

        // 1. TRUNCATE — usuwa dane, zachowuje strukturę tabel.
        $tables = [
            'openvote_votes', 'openvote_answers', 'openvote_questions', 'openvote_polls',
            'openvote_group_members', 'openvote_groups', 'openvote_email_queue',
            'openvote_survey_answers', 'openvote_survey_responses',
            'openvote_survey_questions', 'openvote_surveys',
        ];
        foreach ( $tables as $t ) {
            $wpdb->query( "TRUNCATE TABLE `{$wpdb->prefix}{$t}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- nazwa tabeli z prefiksu WP i stałej
        }

        // 2. Usuń role koordynatorów ze wszystkich użytkowników.
        $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'openvote_role' ],   [ '%s' ] );
        $wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'openvote_groups' ], [ '%s' ] );

        // 3. Resetuj opcje wtyczki do wartości fabrycznych.
        //    Zachowujemy openvote_version i openvote_db_version — bez nich migracje DB uruchomią się od nowa.
        $options_to_reset = array_diff(
            Openvote_Admin_Uninstall::get_option_keys(),
            [ 'openvote_version', 'openvote_db_version' ]
        );
        foreach ( $options_to_reset as $opt ) {
            delete_option( $opt );
        }

        set_transient( 'openvote_clean_success',
            __( 'Baza danych i ustawienia zostały przywrócone do stanu fabrycznego.', 'openvote' ), 30 );
        wp_safe_redirect( admin_url( 'admin.php?page=openvote-settings' ) );
        exit;
    }

    /**
     * Czy strona o podanym slugu ma blok openvote/survey-responses.
     */
    public static function page_has_submissions_block( string $slug ): bool {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            return false;
        }
        return has_block( 'openvote/survey-responses', $page );
    }
}
