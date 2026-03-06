<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Admin_Settings {

    private const CAP            = 'manage_options';
    private const NONCE         = 'openvote_save_settings';
    private const CREATE_NONCE  = 'openvote_create_page';

    public function handle_form_submission(): void {
        // Jednorazowa akcja GET: dodaj pole user_gsm do bazy i zmapuj Telefon.
        if ( current_user_can( self::CAP ) && isset( $_GET['openvote_add_phone_field'] ) && $_GET['openvote_add_phone_field'] === '1' ) {
            if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'openvote_add_phone_field' ) ) {
                $user_id = get_current_user_id();
                update_user_meta( $user_id, 'user_gsm', '' );
                $map = (array) get_option( Openvote_Field_Map::OPTION_KEY, [] );
                $map['phone'] = 'user_gsm';
                update_option( Openvote_Field_Map::OPTION_KEY, $map, false );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-settings&openvote_phone_field_added=1' ) );
                exit;
            }
        }

        // Jednorazowa akcja GET: dodaj pole user_group do bazy, zmapuj Grupa i ustaw jako wymagane do głosowania.
        if ( current_user_can( self::CAP ) && isset( $_GET['openvote_add_city_field'] ) && $_GET['openvote_add_city_field'] === '1' ) {
            if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'openvote_add_city_field' ) ) {
                $user_id = get_current_user_id();
                update_user_meta( $user_id, 'user_group', '' );
                $map = (array) get_option( Openvote_Field_Map::OPTION_KEY, [] );
                $map['city'] = 'user_group';
                update_option( Openvote_Field_Map::OPTION_KEY, $map, false );
                $req = (array) get_option( Openvote_Field_Map::REQUIRED_FIELDS_OPTION, [] );
                if ( ! in_array( 'city', $req, true ) ) {
                    $req[] = 'city';
                    update_option( Openvote_Field_Map::REQUIRED_FIELDS_OPTION, $req, false );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-settings&openvote_city_field_added=1' ) );
                exit;
            }
        }

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

        $can_manage = current_user_can( self::CAP );
        $can_settings_screen = openvote_user_can_access_screen( get_current_user_id(), 'openvote-settings' );
        if ( ! $can_manage && ! $can_settings_screen ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $raw_map = (array) ( $_POST['openvote_field_map'] ?? [] );
        Openvote_Field_Map::save( $raw_map );

        $raw_required = array_map( 'sanitize_key', (array) ( $_POST['openvote_required_fields'] ?? [] ) );
        Openvote_Field_Map::save_required_fields( $raw_required );

        $raw_survey_required = array_map( 'sanitize_key', (array) ( $_POST['openvote_survey_required_fields'] ?? [] ) );
        Openvote_Field_Map::save_survey_required_fields( $raw_survey_required );

        // Mapowanie roli → ekrany.
        $allowed_roles  = Openvote_Role_Map::ROLES;
        $allowed_screens = Openvote_Role_Map::SCREENS;
        $raw_role_screen = (array) ( $_POST['openvote_role_screen'] ?? [] );
        $role_screen_map = [];
        foreach ( $allowed_roles as $role_slug ) {
            $role_screen_map[ $role_slug ] = [];
            foreach ( $allowed_screens as $screen_slug ) {
                $role_screen_map[ $role_slug ][ $screen_slug ] = 0;
            }
            if ( isset( $raw_role_screen[ $role_slug ] ) && is_array( $raw_role_screen[ $role_slug ] ) ) {
                foreach ( $allowed_screens as $screen_slug ) {
                    if ( isset( $raw_role_screen[ $role_slug ][ $screen_slug ] ) && ( $raw_role_screen[ $role_slug ][ $screen_slug ] === '1' || $raw_role_screen[ $role_slug ][ $screen_slug ] === 1 ) ) {
                        $role_screen_map[ $role_slug ][ $screen_slug ] = 1;
                    }
                }
            }
        }
        update_option( Openvote_Role_Map::OPTION_KEY, $role_screen_map, false );

        $coordinator_access = sanitize_key( (string) ( $_POST['openvote_coordinator_poll_access'] ?? 'all' ) );
        $coordinator_access = ( $coordinator_access === 'own' ) ? 'own' : 'all';
        update_option( 'openvote_coordinator_poll_access', $coordinator_access, false );

        // Grupa Test: checkbox włączony = utrzymaj/utwórz grupę; wyłączony = usuń grupę.
        $create_test_group = isset( $_POST['openvote_create_test_group'] ) && ( $_POST['openvote_create_test_group'] === '1' || $_POST['openvote_create_test_group'] === 1 );
        if ( $create_test_group ) {
            update_option( 'openvote_create_test_group', 1, false );
            Openvote_Activator::ensure_test_group_exists();
        } else {
            $test_id = openvote_get_test_group_id();
            if ( $test_id ) {
                global $wpdb;
                $gm_table      = $wpdb->prefix . 'openvote_group_members';
                $groups_table  = $wpdb->prefix . 'openvote_groups';
                $wpdb->delete( $gm_table, [ 'group_id' => $test_id ], [ '%d' ] );
                $wpdb->delete( $groups_table, [ 'id' => $test_id ], [ '%d' ] );
                Openvote_Poll::remove_group_from_all_polls( $test_id );
            }
            update_option( 'openvote_create_test_group', 0, false );
        }

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

        $raw_auto_sync = sanitize_key( (string) ( $_POST['openvote_auto_sync_schedule'] ?? 'first_sunday' ) );
        $allowed_schedules = [ 'manual', 'first_sunday', 'second_sunday', 'weekly', 'daily' ];
        $auto_sync_schedule = in_array( $raw_auto_sync, $allowed_schedules, true ) ? $raw_auto_sync : 'first_sunday';
        update_option( 'openvote_auto_sync_schedule', $auto_sync_schedule, false );

        // Logo i banner usunięte z konfiguracji — używane są Site Icon i Site Title z WordPress.

        $from_email = isset( $_POST['openvote_from_email'] ) ? sanitize_email( wp_unslash( $_POST['openvote_from_email'] ) ) : '';
        if ( $from_email === '' || ! is_email( $from_email ) ) {
            $domain     = wp_parse_url( home_url(), PHP_URL_HOST );
            $from_email = 'noreply@' . ( $domain ?: 'example.com' );
        }
        update_option( 'openvote_from_email', $from_email, false );

        $raw_method  = sanitize_key( wp_unslash( $_POST['openvote_mail_method'] ?? 'wordpress' ) );
        $allowed     = [ 'wordpress', 'smtp', 'sendgrid', 'brevo', 'brevo_paid', 'freshmail', 'getresponse' ];
        $mail_method = in_array( $raw_method, $allowed, true ) ? $raw_method : 'wordpress';

        // WordPress (PHP-mail) niedostępne gdy w systemie > 250 adresów e-mail.
        if ( $mail_method === 'wordpress' ) {
            global $wpdb;
            $email_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email != '' AND user_email IS NOT NULL" );
            if ( $email_count > 250 ) {
                $mail_method = get_option( 'openvote_mail_method', 'smtp' );
                if ( ! in_array( $mail_method, $allowed, true ) ) {
                    $mail_method = 'smtp';
                }
                set_transient( 'openvote_settings_error', __( 'WordPress (PHP-mail) jest niedostępne przy ponad 250 adresach e-mail w systemie. Zachowano poprzednią metodę.', 'openvote' ), 30 );
            }
        }
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
        if ( isset( $_POST['openvote_sendgrid_api_key'] ) && '' !== trim( (string) $_POST['openvote_sendgrid_api_key'] ) ) {
            update_option( 'openvote_sendgrid_api_key', sanitize_text_field( wp_unslash( $_POST['openvote_sendgrid_api_key'] ) ), false );
        }

        // Brevo API key — zapisuj tylko gdy niepuste (wspólny dla brevo i brevo_paid).
        if ( isset( $_POST['openvote_brevo_api_key'] ) && '' !== trim( (string) $_POST['openvote_brevo_api_key'] ) ) {
            update_option( 'openvote_brevo_api_key', sanitize_text_field( wp_unslash( $_POST['openvote_brevo_api_key'] ) ), false );
        }

        // Freshmail: klucz API i sekret.
        if ( isset( $_POST['openvote_freshmail_api_key'] ) ) {
            update_option( 'openvote_freshmail_api_key', sanitize_text_field( wp_unslash( $_POST['openvote_freshmail_api_key'] ) ), false );
        }
        if ( isset( $_POST['openvote_freshmail_api_secret'] ) ) {
            update_option( 'openvote_freshmail_api_secret', sanitize_text_field( wp_unslash( $_POST['openvote_freshmail_api_secret'] ) ), false );
        }

        // GetResponse: klucz API i From Field ID.
        if ( isset( $_POST['openvote_getresponse_api_key'] ) ) {
            update_option( 'openvote_getresponse_api_key', sanitize_text_field( wp_unslash( $_POST['openvote_getresponse_api_key'] ) ), false );
        }
        if ( isset( $_POST['openvote_getresponse_from_field_id'] ) ) {
            update_option( 'openvote_getresponse_from_field_id', sanitize_text_field( wp_unslash( $_POST['openvote_getresponse_from_field_id'] ) ), false );
        }

        // Parametry wysyłki masowej z tabeli „Warunki wysyłki e-maili” — per metoda (bezpłatne). 0 = domyślna wartość.
        $brevo_size  = isset( $_POST['openvote_batch_brevo_free_size'] ) ? absint( $_POST['openvote_batch_brevo_free_size'] ) : 0;
        $brevo_delay = isset( $_POST['openvote_batch_brevo_free_delay'] ) ? absint( $_POST['openvote_batch_brevo_free_delay'] ) : 0;
        $wp_size     = isset( $_POST['openvote_batch_wp_size'] ) ? absint( $_POST['openvote_batch_wp_size'] ) : 0;
        $wp_delay    = isset( $_POST['openvote_batch_wp_delay'] ) ? absint( $_POST['openvote_batch_wp_delay'] ) : 0;
        $smtp_size   = isset( $_POST['openvote_batch_smtp_size'] ) ? absint( $_POST['openvote_batch_smtp_size'] ) : 0;
        $smtp_delay  = isset( $_POST['openvote_batch_smtp_delay'] ) ? absint( $_POST['openvote_batch_smtp_delay'] ) : 0;
        $brevo_size  = $brevo_size > 0 ? min( OPENVOTE_EMAIL_BATCH_BREVO_FREE_MAX, $brevo_size ) : 0;
        $brevo_delay = $brevo_delay > 0 ? max( OPENVOTE_EMAIL_BATCH_BREVO_FREE_DELAY_MIN, $brevo_delay ) : 0;
        $wp_size     = $wp_size > 0 ? min( OPENVOTE_EMAIL_BATCH_WP_MAX, $wp_size ) : 0;
        $wp_delay    = $wp_delay > 0 ? max( OPENVOTE_EMAIL_BATCH_WP_DELAY_MIN, $wp_delay ) : 0;
        $smtp_size   = $smtp_size > 0 ? min( OPENVOTE_EMAIL_BATCH_WP_SMTP_MAX, $smtp_size ) : 0;
        $smtp_delay  = $smtp_delay > 0 ? max( OPENVOTE_EMAIL_BATCH_WP_SMTP_DELAY_MIN, $smtp_delay ) : 0;
        update_option( 'openvote_batch_brevo_free_size', $brevo_size, false );
        update_option( 'openvote_batch_brevo_free_delay', $brevo_delay, false );
        update_option( 'openvote_batch_wp_size', $wp_size, false );
        update_option( 'openvote_batch_wp_delay', $wp_delay, false );
        update_option( 'openvote_batch_smtp_size', $smtp_size, false );
        update_option( 'openvote_batch_smtp_delay', $smtp_delay, false );

        $brevo_per_day = isset( $_POST['openvote_batch_brevo_free_per_day'] ) ? absint( $_POST['openvote_batch_brevo_free_per_day'] ) : 0;
        $wp_per_day    = isset( $_POST['openvote_batch_wp_per_day'] ) ? absint( $_POST['openvote_batch_wp_per_day'] ) : 0;
        $smtp_per_day  = isset( $_POST['openvote_batch_smtp_per_day'] ) ? absint( $_POST['openvote_batch_smtp_per_day'] ) : 0;
        update_option( 'openvote_batch_brevo_free_per_day', $brevo_per_day > 0 ? min( 10000, $brevo_per_day ) : 0, false );
        update_option( 'openvote_batch_wp_per_day', $wp_per_day > 0 ? min( 100000, $wp_per_day ) : 0, false );
        update_option( 'openvote_batch_smtp_per_day', $smtp_per_day > 0 ? min( 100000, $smtp_per_day ) : 0, false );

        $brevo_per_15   = isset( $_POST['openvote_batch_brevo_free_per_15min'] ) ? absint( $_POST['openvote_batch_brevo_free_per_15min'] ) : 0;
        $brevo_per_hour = isset( $_POST['openvote_batch_brevo_free_per_hour'] ) ? absint( $_POST['openvote_batch_brevo_free_per_hour'] ) : 0;
        $wp_per_15      = isset( $_POST['openvote_batch_wp_per_15min'] ) ? absint( $_POST['openvote_batch_wp_per_15min'] ) : 0;
        $wp_per_hour    = isset( $_POST['openvote_batch_wp_per_hour'] ) ? absint( $_POST['openvote_batch_wp_per_hour'] ) : 0;
        $smtp_per_15    = isset( $_POST['openvote_batch_smtp_per_15min'] ) ? absint( $_POST['openvote_batch_smtp_per_15min'] ) : 0;
        $smtp_per_hour  = isset( $_POST['openvote_batch_smtp_per_hour'] ) ? absint( $_POST['openvote_batch_smtp_per_hour'] ) : 0;
        update_option( 'openvote_batch_brevo_free_per_15min', $brevo_per_15 > 0 ? min( 1000, $brevo_per_15 ) : 0, false );
        update_option( 'openvote_batch_brevo_free_per_hour', $brevo_per_hour > 0 ? min( 10000, $brevo_per_hour ) : 0, false );
        update_option( 'openvote_batch_wp_per_15min', $wp_per_15 > 0 ? min( 1000, $wp_per_15 ) : 0, false );
        update_option( 'openvote_batch_wp_per_hour', $wp_per_hour > 0 ? min( 10000, $wp_per_hour ) : 0, false );
        update_option( 'openvote_batch_smtp_per_15min', $smtp_per_15 > 0 ? min( 1000, $smtp_per_15 ) : 0, false );
        update_option( 'openvote_batch_smtp_per_hour', $smtp_per_hour > 0 ? min( 10000, $smtp_per_hour ) : 0, false );

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
        $email_body_plain = wp_unslash( $_POST['openvote_email_body_plain'] ?? '' );
        $email_body_plain = wp_strip_all_tags( $email_body_plain );
        update_option( 'openvote_email_body_plain', $email_body_plain, false );

        $missed = isset( $_POST['openvote_stat_missed_votes'] ) ? absint( $_POST['openvote_stat_missed_votes'] ) : 0;
        if ( $missed >= 1 && $missed <= 24 ) {
            update_option( 'openvote_stat_missed_votes', $missed, false );
        }
        $months = isset( $_POST['openvote_stat_months_inactive'] ) ? absint( $_POST['openvote_stat_months_inactive'] ) : 0;
        if ( $months >= 1 && $months <= 24 ) {
            update_option( 'openvote_stat_months_inactive', $months, false );
        }
        $do_not_skip = isset( $_POST['openvote_communication_do_not_skip_inactive'] ) && $_POST['openvote_communication_do_not_skip_inactive'] === '1' ? 1 : 0;
        update_option( 'openvote_communication_do_not_skip_inactive', $do_not_skip, false );

        Openvote_Cron_Sync::reschedule();

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

    /**
     * AJAX: zapisz domyślną treść e-maila (plain) do opcji. Wywoływane z przycisku „Przywróć domyślną”.
     */
    public function ajax_reset_email_body_plain(): void {
        check_ajax_referer( 'openvote_reset_email_body_plain', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( [ 'message' => __( 'Brak uprawnień.', 'openvote' ) ] );
        }
        $default = openvote_get_email_body_plain_default();
        update_option( 'openvote_email_body_plain', $default, false );
        wp_send_json_success( [ 'message' => __( 'Zapisano domyślną treść.', 'openvote' ) ] );
    }
}
