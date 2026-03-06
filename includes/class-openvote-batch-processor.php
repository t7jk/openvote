<?php
defined( 'ABSPATH' ) || exit;

/**
 * Silnik przetwarzania operacji masowych partiami po 100 rekordów.
 *
 * Każde zadanie (job) przechowywane jest jako transient WordPress.
 * Frontend odpytuje /jobs/{id}/progress i wywołuje /jobs/{id}/next
 * do momentu, aż status = 'done'.
 *
 * Typy zadań:
 *   sync_group           — synchronizuj jedną grupę (city lub custom) z usermeta
 *   sync_all_city_groups — odkryj wszystkie miasta i stwórz/synchronizuj grupy
 *   send_start_emails    — wyślij e-mail o otwarciu głosowania (bez kolejki)
 *   send_invitations     — wyślij zaproszenia z kolejki wp_openvote_email_queue
 */
class Openvote_Batch_Processor {

    const BATCH_SIZE                   = 100;
    const SYNC_BATCH_SIZE              = 25;   // synchronizacja jednej grupy — użytkownicy po 25
    const SYNC_CITIES_BATCH_SIZE       = 1;   // synchronizacja wszystkich — po jednym mieście (log po każdym)
    const RECALC_INACTIVE_BATCH_SIZE   = 50;   // przelicz statystyki nieaktywnych — po 50 użytkowników (ograniczenie zapytań/firewall)
    const SYNC_LOG_EVERY_USERS   = 100; // co tylu użytkowników dopisać linię do logu (częstszy postęp)
    const EMAIL_BATCH_SIZE_DEFAULT = 20;  // WP/SMTP
    const EMAIL_SENDGRID_BATCH_DEFAULT = 100; // SendGrid

    const OPTION_SYNC_ALL_CHECKPOINT = 'openvote_sync_all_checkpoint';

    // ─── Publiczne API ───────────────────────────────────────────────────────

    /**
     * Uruchom nowe zadanie.
     *
     * Dla `send_invitations` wypełnia tabelę wp_openvote_email_queue przed startem.
     *
     * @param string $type   Typ zadania (np. 'sync_group').
     * @param array  $params Parametry zadania.
     * @return string Job ID.
     */
    public static function start_job( string $type, array $params ): string {
        $job_id = uniqid( 'openvote_job_', true );

        // Dla wysyłki zaproszeń: najpierw wypełnij kolejkę w bazie.
        if ( 'send_invitations' === $type ) {
            self::fill_email_queue( $job_id, $params );
        }

        $total = self::count_total( $type, array_merge( $params, [ 'job_id' => $job_id ] ) );

        $job_data = [
            'type'      => $type,
            'params'    => array_merge( $params, [ 'job_id' => $job_id ] ),
            'offset'    => 0,
            'total'     => $total,
            'processed' => 0,
            'status'    => 'running',
            'results'   => [],
            'logs'      => [],
            'started_at' => time(),
        ];

        if ( 'sync_all_city_groups' === $type ) {
            $job_data['total_users'] = 0;
            $checkpoint              = get_option( self::OPTION_SYNC_ALL_CHECKPOINT, [] );
            $city_disabled           = Openvote_Field_Map::is_city_disabled();
            $resumed                 = false;
            // Wznowienie tylko gdy checkpoint jest niepusty (po resecie jest [] — wtedy start od zera, bez komunikatu "Wznowiono").
            if ( is_array( $checkpoint ) && ! empty( $checkpoint ) && $total > 0 ) {
                if ( $city_disabled ) {
                    $user_off = isset( $checkpoint['user_offset'] ) ? (int) $checkpoint['user_offset'] : 0;
                    if ( $user_off >= 0 && $user_off <= $total ) {
                        $job_data['offset'] = $user_off;
                        $resumed             = true;
                    }
                } else {
                    $city_off   = isset( $checkpoint['city_offset'] ) ? (int) $checkpoint['city_offset'] : 0;
                    $sync_off   = isset( $checkpoint['sync_user_offset'] ) ? (int) $checkpoint['sync_user_offset'] : 0;
                    if ( $city_off >= 0 && $city_off <= $total ) {
                        $job_data['offset'] = $city_off;
                        $job_data['params']['sync_user_offset'] = $sync_off;
                        $resumed = true;
                    }
                }
            }
            $job_data['logs'] = [
                gmdate( 'Y-m-d H:i:s' ) . ' ' . (
                    $resumed
                    ? sprintf(
                        /* translators: %d: number of cities or users */
                        __( 'Wznowiono od ostatniego stanu. Łącznie do przetworzenia: %d.', 'openvote' ),
                        $total
                    )
                    : sprintf(
                        /* translators: %d: number of cities */
                        __( 'Łącznie do przetworzenia: %d miast. Liczenie użytkowników w pierwszej partii…', 'openvote' ),
                        $total
                    )
                ),
            ];
        } elseif ( 'send_invitations' === $type ) {
            $job_data['logs'] = [
                gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                    /* translators: %d: total to process */
                    __( 'Zadanie uruchomione. Łącznie do przetworzenia: %d', 'openvote' ),
                    $total
                ),
            ];
            if ( class_exists( 'Openvote_Mailer', false ) ) {
                $job_data['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                    /* translators: %s: method label e.g. Brevo API, SendGrid API */
                    __( 'Metoda wysyłki: %s.', 'openvote' ),
                    Openvote_Mailer::get_method_label()
                );
                $job_data['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Sprawdzanie połączenia z dostawcą e-mail…', 'openvote' );
                $test = Openvote_Mailer::test_connection();
                $job_data['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . (
                    $test['ok']
                    ? $test['message']
                    : sprintf(
                        /* translators: %s: error message from provider */
                        __( 'Błąd połączenia z dostawcą: %s', 'openvote' ),
                        $test['message']
                    )
                );
            }
        } else {
            $job_data['logs'] = [
                gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                    /* translators: %d: total to process */
                    __( 'Zadanie uruchomione. Łącznie do przetworzenia: %d', 'openvote' ),
                    $total
                ),
            ];
        }
        set_transient( $job_id, $job_data, HOUR_IN_SECONDS );

        return $job_id;
    }

    /**
     * Pobierz stan zadania.
     *
     * @return array|false Dane zadania lub false jeśli wygasło.
     */
    public static function get_job( string $job_id ): array|false {
        return get_transient( $job_id );
    }

    /**
     * Zatrzymaj zadanie (ustaw status 'cancelled').
     *
     * @param string $job_id ID zadania.
     * @return bool True jeśli zadanie było running i zostało zatrzymane.
     */
    public static function cancel_job( string $job_id ): bool {
        $job = get_transient( $job_id );
        if ( false === $job || 'running' !== $job['status'] ) {
            return false;
        }
        if ( ! isset( $job['logs'] ) || ! is_array( $job['logs'] ) ) {
            $job['logs'] = [];
        }
        $job['status'] = 'cancelled';
        $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Zatrzymane przez użytkownika.', 'openvote' );
        set_transient( $job_id, $job, HOUR_IN_SECONDS );
        return true;
    }

    /**
     * Przetwórz następną partię dla danego zadania.
     *
     * @return array|false Zaktualizowany stan zadania lub false jeśli wygasło.
     */
    public static function process_batch( string $job_id ): array|false {
        $job = get_transient( $job_id );

        if ( false === $job ) {
            return false;
        }

        if ( 'done' === $job['status'] || 'cancelled' === $job['status'] ) {
            return $job;
        }

        if ( ! isset( $job['logs'] ) || ! is_array( $job['logs'] ) ) {
            $job['logs'] = [];
        }
        $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
            /* translators: 1: offset, 2: total */
            __( 'Partia offset %1$d / %2$d', 'openvote' ),
            $job['offset'],
            $job['total']
        );
        set_transient( $job_id, $job, HOUR_IN_SECONDS );

        $result = self::run_batch( $job );
        $job    = $result['job'];

        if ( ( $job['status'] ?? '' ) === 'limit_exceeded' ) {
            set_transient( $job_id, $job, HOUR_IN_SECONDS );
            return $job;
        }

        $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
            /* translators: %d: number of processed items */
            __( 'Przetworzono: %d rekordów', 'openvote' ),
            count( $result['items'] )
        );
        if ( 'send_invitations' === $job['type'] && ! empty( $result['items'] ) ) {
            $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                /* translators: %d: number of emails sent in this batch */
                __( 'Wysłano %d e-maili (partia).', 'openvote' ),
                count( $result['items'] )
            );
        }
        // Dla sync_all_city_groups — lista przetworzonych miast (widoczny postęp).
        if ( 'sync_all_city_groups' === $job['type'] && ! empty( $result['items'] ) ) {
            $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Miasta:', 'openvote' ) . ' ' . implode( ', ', array_map( 'esc_html', $result['items'] ) );
        }

        // Sprawdź czy zadanie zakończone.
        if ( $job['offset'] >= $job['total'] || empty( $result['items'] ) ) {
            $job['status'] = 'done';
            if ( 'send_invitations' === $job['type'] ) {
                $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Wysyłka zakończona.', 'openvote' );
            }
            if ( 'recalc_inactive' === $job['type'] ) {
                $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Przeliczanie statystyk nieaktywnych zakończone.', 'openvote' );
            }
            if ( 'sync_all_city_groups' === $job['type'] && function_exists( 'openvote_current_time_for_voting' ) ) {
                update_option( 'openvote_last_cron_sync_date', openvote_current_time_for_voting( 'Y-m-d' ), false );
            }
        }

        // Zapis checkpointu dla sync_all_city_groups (wznowienie od ostatniego stanu).
        if ( 'sync_all_city_groups' === $job['type'] ) {
            $city_disabled = Openvote_Field_Map::is_city_disabled();
            if ( $job['status'] === 'done' ) {
                if ( $city_disabled ) {
                    update_option( self::OPTION_SYNC_ALL_CHECKPOINT, [ 'user_offset' => $job['total'] ], false );
                } else {
                    update_option( self::OPTION_SYNC_ALL_CHECKPOINT, [ 'city_offset' => $job['total'], 'sync_user_offset' => 0 ], false );
                }
            } else {
                if ( $city_disabled ) {
                    update_option( self::OPTION_SYNC_ALL_CHECKPOINT, [ 'user_offset' => $job['offset'] ], false );
                } else {
                    update_option( self::OPTION_SYNC_ALL_CHECKPOINT, [
                        'city_offset'      => $job['offset'],
                        'sync_user_offset' => (int) ( $job['params']['sync_user_offset'] ?? 0 ),
                    ], false );
                }
            }
        }

        set_transient( $job_id, $job, HOUR_IN_SECONDS );

        return $job;
    }

    // ─── Wewnętrzna logika ───────────────────────────────────────────────────

    /**
     * Policz łączną liczbę rekordów do przetworzenia.
     */
    private static function count_total( string $type, array $params ): int {
        global $wpdb;

        switch ( $type ) {
            case 'sync_group':
                return self::count_users_for_group( $params );

            case 'sync_all_city_groups':
                if ( Openvote_Field_Map::is_city_disabled() ) {
                    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
                }
                // Policz unikalne wartości pola city.
                $city_key = Openvote_Field_Map::get_field( 'city' );
                if ( Openvote_Field_Map::is_core_field( $city_key ) ) {
                    return (int) $wpdb->get_var(
                        "SELECT COUNT(DISTINCT {$city_key}) FROM {$wpdb->users} WHERE {$city_key} != ''"
                    );
                }
                return (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
                        sanitize_key( $city_key )
                    )
                );

            case 'send_start_emails':
                $poll_id = absint( $params['poll_id'] ?? 0 );
                if ( ! $poll_id ) {
                    return 0;
                }
                $poll = Openvote_Poll::get( $poll_id );
                if ( ! $poll ) {
                    return 0;
                }
                $target_groups = $poll->target_groups ? json_decode( $poll->target_groups, true ) : [];
                if ( ! empty( $target_groups ) ) {
                    $gm_table   = $wpdb->prefix . 'openvote_group_members';
                    $ids_clean  = array_map( 'absint', (array) $target_groups );
                    $placeholders = implode( ',', array_fill( 0, count( $ids_clean ), '%d' ) );
                    return (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                             INNER JOIN {$gm_table} gm ON u.ID = gm.user_id
                             WHERE gm.group_id IN ({$placeholders}) AND u.user_email != ''",
                            ...$ids_clean
                        )
                    );
                }
                return (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email != ''"
                );

            case 'send_invitations':
                $poll_id_ci = absint( $params['poll_id'] ?? 0 );
                if ( ! $poll_id_ci ) {
                    return 0;
                }
                $eq = $wpdb->prefix . 'openvote_email_queue';
                return (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$eq} WHERE poll_id = %d AND status = 'pending'",
                        $poll_id_ci
                    )
                );

            case 'recalc_inactive':
                $gm_table = $wpdb->prefix . 'openvote_group_members';
                return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$gm_table}" );

            default:
                return 0;
        }
    }

    /**
     * Dla sync_all_city_groups: łączna liczba użytkowników do zsynchronizowania (z polem miasto).
     */
    private static function count_total_users_for_sync_all(): int {
        global $wpdb;
        if ( Openvote_Field_Map::is_city_disabled() ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
        }
        $city_key = Openvote_Field_Map::get_field( 'city' );
        if ( Openvote_Field_Map::is_core_field( $city_key ) ) {
            $safe_col = '`' . esc_sql( $city_key ) . '`';
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE {$safe_col} != ''" );
        }
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
                sanitize_key( $city_key )
            )
        );
    }

    /**
     * Wykonaj jedną partię rekordów.
     *
     * @param array $job Job (przekazywany przez referencję przy sync_all_city_groups — do logu co 250 użytk.).
     * @return array{ job: array, items: array }
     */
    private static function run_batch( array &$job ): array {
        $type   = $job['type'];
        $params = $job['params'];
        $offset = $job['offset'];
        $items  = [];

        switch ( $type ) {
            case 'sync_group':
                $items = self::batch_sync_group( $params, $offset );
                break;

            case 'sync_all_city_groups':
                $items = self::batch_sync_all_city_groups( $params, $offset, $job );
                break;

            case 'send_start_emails':
                $items = self::batch_send_emails( $params, $offset );
                if ( ! empty( $items ) ) {
                    if ( class_exists( 'Openvote_Email_Rate_Limits', false ) ) {
                        Openvote_Email_Rate_Limits::increment( count( $items ) );
                    }
                    openvote_increment_emails_sent( count( $items ) );
                }
                break;

            case 'send_invitations': {
                $batch_size = openvote_get_email_batch_size();
                if ( class_exists( 'Openvote_Email_Rate_Limits', false ) ) {
                    // Use the actual number of pending emails (capped at batch_size) so the
                    // check does not trigger when the queue is nearly empty but batch_size is large.
                    $eq = $wpdb->prefix . 'openvote_email_queue';
                    $job_id_for_check = sanitize_text_field( $params['job_id'] ?? '' );
                    $pending_count = $job_id_for_check
                        ? (int) $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$eq} WHERE job_id = %s AND status = 'pending'",
                            $job_id_for_check
                          ) )
                        : $batch_size;
                    $check_n = min( $batch_size, max( 1, $pending_count ) );
                    $limit_check = Openvote_Email_Rate_Limits::would_exceed_limits( $check_n );
                    if ( ! empty( $limit_check['exceeded'] ) ) {
                        $job['status']         = 'limit_exceeded';
                        $job['limit_type']     = $limit_check['limit_type'];
                        $job['wait_seconds']   = $limit_check['wait_seconds'];
                        $job['limit_message']  = $limit_check['message'];
                        $job['limit_max']      = $limit_check['limit_max'];
                        return [ 'job' => $job, 'items' => [] ];
                    }
                }
                $inv_result = self::batch_send_invitations( $params );
                $items      = isset( $inv_result['items'] ) ? $inv_result['items'] : $inv_result;
                if ( ! empty( $inv_result['extra_logs'] ) && is_array( $inv_result['extra_logs'] ) ) {
                    foreach ( $inv_result['extra_logs'] as $line ) {
                        $job['logs'][] = $line;
                    }
                }
                if ( ! empty( $items ) ) {
                    if ( class_exists( 'Openvote_Email_Rate_Limits', false ) ) {
                        Openvote_Email_Rate_Limits::increment( count( $items ) );
                    }
                    openvote_increment_emails_sent( count( $items ) );
                }
                break;
            }

            case 'recalc_inactive':
                $items = self::batch_recalc_inactive( $offset );
                break;
        }

        $count = count( $items );
        if ( 'sync_all_city_groups' !== $job['type'] ) {
            $job['processed'] += $count;
        }

        if ( 'send_invitations' === $job['type'] ) {
            // Dla kolejki DB: offset = liczba wierszy, które już nie są pending (dynamicznie z DB).
            global $wpdb;
            $eq      = $wpdb->prefix . 'openvote_email_queue';
            $pid_run = absint( $job['params']['poll_id'] ?? 0 );
            $job['offset'] = $pid_run
                ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$eq} WHERE poll_id = %d AND status != 'pending'", $pid_run ) )
                : $job['total'];
        } elseif ( 'sync_all_city_groups' === $job['type'] && Openvote_Field_Map::is_city_disabled() ) {
            // Tryb „Wszyscy”: offset = następna partia użytkowników.
            $job['offset'] += $count > 0 ? self::SYNC_BATCH_SIZE : $job['total'];
        } elseif ( 'sync_all_city_groups' !== $job['type'] ) {
            $batch_size = self::batch_size_for_type( $job['type'] );
            $job['offset'] += $count > 0 ? $batch_size : $job['total'];
        }

        $job['results'] = array_merge( $job['results'], $items );

        return [ 'job' => $job, 'items' => $items ];
    }

    /**
     * Rozmiar partii dla danego typu zadania (sync = 25, pozostałe = 100).
     */
    private static function batch_size_for_type( string $type ): int {
        if ( 'sync_all_city_groups' === $type ) {
            return self::SYNC_CITIES_BATCH_SIZE;
        }
        if ( 'sync_group' === $type ) {
            return self::SYNC_BATCH_SIZE;
        }
        if ( 'recalc_inactive' === $type ) {
            return self::RECALC_INACTIVE_BATCH_SIZE;
        }
        return self::BATCH_SIZE;
    }

    // ─── Implementacje typów zadań ───────────────────────────────────────────

    /**
     * Przelicz statystyki nieaktywnych (openvote_missed_polls_count) dla partii członków grup.
     *
     * @param int $offset Liczba już przetworzonych użytkowników.
     * @return array Lista user_id przetworzonych w tej partii.
     */
    private static function batch_recalc_inactive( int $offset ): array {
        global $wpdb;
        $gm_table = $wpdb->prefix . 'openvote_group_members';
        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$gm_table} ORDER BY user_id ASC LIMIT %d OFFSET %d",
                self::RECALC_INACTIVE_BATCH_SIZE,
                $offset
            )
        );
        $items = [];
        foreach ( (array) $user_ids as $uid ) {
            openvote_recalculate_missed_polls_count( (int) $uid );
            $items[] = (int) $uid;
        }
        return $items;
    }

    /**
     * Synchronizuj jedną grupę — dodaj użytkowników z pasującym polem city.
     *
     * @param array $params ['group_id' => int, 'city_value' => string]
     */
    private static function batch_sync_group( array $params, int $offset ): array {
        global $wpdb;

        $group_id   = absint( $params['group_id'] ?? 0 );
        $city_value = sanitize_text_field( $params['city_value'] ?? '' );

        if ( ! $group_id || '' === $city_value ) {
            return [];
        }

        $city_key = Openvote_Field_Map::get_field( 'city' );
        $gm_table = $wpdb->prefix . 'openvote_group_members';

        // Pobierz partię użytkowników z pasującym miastem.
        if ( Openvote_Field_Map::is_core_field( $city_key ) ) {
            $safe_col = '`' . esc_sql( $city_key ) . '`';
            $users    = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} WHERE {$safe_col} = %s ORDER BY ID ASC LIMIT %d OFFSET %d",
                    $city_value,
                    self::SYNC_BATCH_SIZE,
                    $offset
                )
            );
        } else {
            $users = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT u.ID FROM {$wpdb->users} u
                     INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                       AND um.meta_key = %s AND um.meta_value = %s
                     ORDER BY u.ID ASC LIMIT %d OFFSET %d",
                    sanitize_key( $city_key ),
                    $city_value,
                    self::SYNC_BATCH_SIZE,
                    $offset
                )
            );
        }

        $added = 0;
        foreach ( $users as $user ) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$gm_table} (group_id, user_id, source, added_at) VALUES (%d, %d, 'auto', %s)",
                    $group_id,
                    $user->ID,
                    current_time( 'mysql' )
                )
            );
            if ( $wpdb->rows_affected ) {
                ++$added;
            }
        }

        // Zaktualizuj licznik grupy.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$gm_table} WHERE group_id = %d", $group_id )
        );
        $wpdb->update(
            $wpdb->prefix . 'openvote_groups',
            [ 'member_count' => $count ],
            [ 'id' => $group_id ],
            [ '%d' ],
            [ '%d' ]
        );

        return array_column( $users, 'ID' );
    }

    /**
     * Synchronizuj wszystkie grupy-miasta — jedna partia = max 25 użytkowników z bieżącego miasta (unikamy timeoutu).
     * Gdy "Nie używaj miast": jedna grupa "Wszyscy", batch = SYNC_BATCH_SIZE użytkowników.
     *
     * @param array $params Zawiera job_id, sync_user_offset (offset użytk. w bieżącym mieście).
     * @param int   $offset Indeks bieżącego miasta (0 .. total-1).
     * @param array $job   Job (referencja) — offset, processed, logs aktualizowane tutaj.
     */
    private static function batch_sync_all_city_groups( array $params, int $offset, array &$job ): array {
        global $wpdb;

        if ( Openvote_Field_Map::is_city_disabled() ) {
            return self::batch_sync_wszyscy( $offset );
        }

        $job_id           = $params['job_id'] ?? null;
        $sync_user_offset = (int) ( $params['sync_user_offset'] ?? 0 );
        $city_key         = Openvote_Field_Map::get_field( 'city' );
        $groups_table     = $wpdb->prefix . 'openvote_groups';

        if ( ! isset( $job['logs'] ) || ! is_array( $job['logs'] ) ) {
            $job['logs'] = [];
        }
        $job['users_synced'] = isset( $job['users_synced'] ) ? (int) $job['users_synced'] : 0;

        $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
            /* translators: %d: city offset index */
            __( 'Pobieranie miasta (pozycja %d)...', 'openvote' ),
            $offset
        );
        if ( $job_id ) {
            set_transient( $job_id, $job, HOUR_IN_SECONDS );
        }

        // Pobierz jedno miasto na pozycji offset.
        if ( Openvote_Field_Map::is_core_field( $city_key ) ) {
            $cities = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT {$city_key} FROM {$wpdb->users}
                     WHERE {$city_key} != '' ORDER BY {$city_key} ASC LIMIT 1 OFFSET %d",
                    $offset
                )
            );
        } else {
            $cities = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
                     WHERE meta_key = %s AND meta_value != ''
                     ORDER BY meta_value ASC LIMIT 1 OFFSET %d",
                    sanitize_key( $city_key ),
                    $offset
                )
            );
        }

        if ( empty( $cities ) ) {
            $job['offset'] = $job['total'];
            if ( $job_id ) {
                set_transient( $job_id, $job, HOUR_IN_SECONDS );
            }
            return [];
        }

        $city_value = sanitize_text_field( $cities[0] );

        $existing_id = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$groups_table} WHERE name = %s", $city_value )
        );
        if ( ! $existing_id ) {
            $wpdb->insert(
                $groups_table,
                [ 'name' => $city_value, 'type' => 'city', 'member_count' => 0 ],
                [ '%s', '%s', '%d' ]
            );
            $existing_id = $wpdb->insert_id;
        }
        $group_id = (int) $existing_id;

        $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
            /* translators: %s: city name */
            __( 'Miasto: %s. Liczenie użytkowników...', 'openvote' ),
            $city_value
        );
        if ( $job_id ) {
            set_transient( $job_id, $job, HOUR_IN_SECONDS );
        }

        $total_in_city = self::count_users_for_group( [
            'group_id'   => $group_id,
            'city_value' => $city_value,
        ] );

        // Pierwsza partia w tym mieście — log „start”.
        if ( 0 === $sync_user_offset ) {
            $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                /* translators: 1: city name, 2: number of users */
                __( 'Miasto %1$s: %2$d użytkowników do przetworzenia', 'openvote' ),
                $city_value,
                $total_in_city
            );
            if ( $job_id ) {
                set_transient( $job_id, $job, HOUR_IN_SECONDS );
            }
        }

        $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Synchronizacja partii 25 użytkowników...', 'openvote' );
        if ( $job_id ) {
            set_transient( $job_id, $job, HOUR_IN_SECONDS );
        }

        // Jedna mikropartia: max 25 użytkowników z tego miasta.
        $items = self::batch_sync_group(
            [ 'group_id' => $group_id, 'city_value' => $city_value ],
            $sync_user_offset
        );
        $count = count( $items );

        $sync_user_offset += $count;
        $job['params']['sync_user_offset'] = $sync_user_offset;
        $job['users_synced']              += $count;

        // Log dla każdej mikropartii w tym mieście.
        if ( $count > 0 ) {
            $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                /* translators: 1: city name, 2: processed in city, 3: total in city, 4: total users synced in job */
                __( 'Miasto %1$s: przetworzono %2$d / %3$d użytkowników (łącznie w zadaniu: %4$d)', 'openvote' ),
                $city_value,
                min( $sync_user_offset, $total_in_city ),
                $total_in_city,
                $job['users_synced']
            );
        }

        // Log co SYNC_LOG_EVERY_USERS użytkowników (łącznie).
        $prev = (int) floor( ( $job['users_synced'] - $count ) / self::SYNC_LOG_EVERY_USERS );
        $curr = (int) floor( $job['users_synced'] / self::SYNC_LOG_EVERY_USERS );
        if ( $curr > $prev ) {
            $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                /* translators: 1: number (e.g. 100), 2: total users synced so far */
                __( 'Przetworzono %1$d użytkowników (łącznie: %2$d)', 'openvote' ),
                self::SYNC_LOG_EVERY_USERS,
                $job['users_synced']
            );
        }

        if ( $sync_user_offset >= $total_in_city ) {
            $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                /* translators: %s: city name */
                __( 'Miasto %s zakończone.', 'openvote' ),
                $city_value
            );
            $job['offset']++;
            $job['processed']++;
            $job['params']['sync_user_offset'] = 0;
            if ( $job['processed'] > 0 && $job['total'] > 0 ) {
                $job['total_users'] = max(
                    $job['users_synced'],
                    (int) round( $job['users_synced'] / $job['processed'] * $job['total'] )
                );
            }
        }

        if ( $job_id ) {
            set_transient( $job_id, $job, HOUR_IN_SECONDS );
        }

        return [ $city_value ];
    }

    /**
     * Jedna partia: dodaj SYNC_BATCH_SIZE użytkowników do grupy "Wszyscy" (tryb bez miast).
     */
    private static function batch_sync_wszyscy( int $offset ): array {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'openvote_groups';
        $gm_table     = $wpdb->prefix . 'openvote_group_members';
        $name         = Openvote_Field_Map::WSZYSCY_NAME;

        $wszyscy_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$groups_table} WHERE name = %s", $name ) );
        if ( ! $wszyscy_id ) {
            $wpdb->insert(
                $groups_table,
                [ 'name' => $name, 'type' => 'custom', 'description' => null, 'member_count' => 0 ],
                [ '%s', '%s', '%s', '%d' ]
            );
            $wszyscy_id = $wpdb->insert_id;
        }
        $wszyscy_id = (int) $wszyscy_id;

        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} ORDER BY ID ASC LIMIT %d OFFSET %d",
                self::SYNC_BATCH_SIZE,
                $offset
            )
        );

        $now = current_time( 'mysql' );
        foreach ( $user_ids as $uid ) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$gm_table} (group_id, user_id, source, added_at) VALUES (%d, %d, 'auto', %s)",
                    $wszyscy_id,
                    (int) $uid,
                    $now
                )
            );
        }

        if ( ! empty( $user_ids ) ) {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$gm_table} WHERE group_id = %d", $wszyscy_id )
            );
            $wpdb->update( $groups_table, [ 'member_count' => $count ], [ 'id' => $wszyscy_id ], [ '%d' ], [ '%d' ] );
        }

        return array_map( 'intval', $user_ids );
    }

    /**
     * Wykonaj pełen sync jednej grupy (wszystkie partie synchronicznie).
     * Używany przy batch_sync_all_city_groups. Gdy podano $job, co SYNC_LOG_EVERY_USERS użytk. dopisuje linię do logu.
     *
     * @param int         $group_id
     * @param string      $city_value
     * @param array $job Referencja do job — do logu co SYNC_LOG_EVERY_USERS użytkowników (tylko przy sync_all).
     */
    public static function run_full_sync( int $group_id, string $city_value, array &$job ): int {
        global $wpdb;

        $total  = self::count_users_for_group( [ 'group_id' => $group_id, 'city_value' => $city_value ] );
        $offset = 0;
        $added  = 0;

        $job['users_synced'] = isset( $job['users_synced'] ) ? (int) $job['users_synced'] : 0;

        if ( ! isset( $job['logs'] ) || ! is_array( $job['logs'] ) ) {
            $job['logs'] = [];
        }
        $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
            /* translators: 1: city name, 2: number of users */
            __( 'Miasto %1$s: %2$d użytkowników do przetworzenia', 'openvote' ),
            $city_value,
            $total
        );
        $job_id = $job['params']['job_id'] ?? null;
        if ( $job_id ) {
            set_transient( $job_id, $job, HOUR_IN_SECONDS );
        }

        while ( $offset < $total ) {
            $items  = self::batch_sync_group( [ 'group_id' => $group_id, 'city_value' => $city_value ], $offset );
            $count  = count( $items );
            $added += $count;
            $offset += self::SYNC_BATCH_SIZE;

            if ( $count > 0 ) {
                $job['users_synced'] += $count;
                $prev_250 = (int) floor( ( $job['users_synced'] - $count ) / self::SYNC_LOG_EVERY_USERS );
                $curr_250 = (int) floor( $job['users_synced'] / self::SYNC_LOG_EVERY_USERS );
                if ( $curr_250 > $prev_250 ) {
                    $job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                        /* translators: 1: number (e.g. 100), 2: total users synced so far */
                        __( 'Przetworzono %1$d użytkowników (łącznie: %2$d)', 'openvote' ),
                        self::SYNC_LOG_EVERY_USERS,
                        $job['users_synced']
                    );
                    if ( $job_id ) {
                        set_transient( $job_id, $job, HOUR_IN_SECONDS );
                    }
                }
            }
        }

        return $added;
    }

    /**
     * Wyślij e-mail o otwarciu głosowania do partii użytkowników.
     */
    private static function batch_send_emails( array $params, int $offset ): array {
        global $wpdb;

        $poll_id = absint( $params['poll_id'] ?? 0 );
        $poll    = Openvote_Poll::get( $poll_id );

        if ( ! $poll ) {
            return [];
        }

        $target_groups = $poll->target_groups ? json_decode( $poll->target_groups, true ) : [];
        if ( ! empty( $target_groups ) ) {
            $gm_table     = $wpdb->prefix . 'openvote_group_members';
            $ids_clean    = array_map( 'absint', (array) $target_groups );
            $placeholders = implode( ',', array_fill( 0, count( $ids_clean ), '%d' ) );
            $users = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT u.ID, u.user_email, u.display_name FROM {$wpdb->users} u
                     INNER JOIN {$gm_table} gm ON u.ID = gm.user_id
                     WHERE gm.group_id IN ({$placeholders}) AND u.user_email != ''
                     GROUP BY u.ID ORDER BY u.ID ASC LIMIT %d OFFSET %d",
                    array_merge( $ids_clean, [ self::BATCH_SIZE, $offset ] )
                )
            );
        } else {
            $users = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, user_email, display_name FROM {$wpdb->users}
                     WHERE user_email != '' ORDER BY ID ASC LIMIT %d OFFSET %d",
                    self::BATCH_SIZE,
                    $offset
                )
            );
        }

        if ( empty( $users ) ) {
            return [];
        }

        $from_email   = openvote_get_from_email();
        $from_name    = openvote_render_email_template( openvote_get_email_from_template(), $poll );
        $subject      = openvote_render_email_template( openvote_get_email_subject_template(), $poll );
        $email_type   = openvote_get_email_template_type();
        $message      = openvote_render_email_template( openvote_get_email_body_template(), $poll, $email_type );
        $message_is_html = str_starts_with( trim( $message ), '<' );
        $content_type = ( $email_type === 'html' || $message_is_html ) ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
        $headers      = [
            'Content-Type: ' . $content_type,
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
        $sent         = [];

        foreach ( $users as $user ) {
            if ( function_exists( 'openvote_is_user_inactive' ) && openvote_is_user_inactive( (int) $user->ID ) ) {
                continue;
            }
            if ( is_email( $user->user_email ) && wp_mail( $user->user_email, $subject, $message, $headers ) ) {
                $sent[] = $user->user_email;
            }
        }

        return $sent;
    }

    // ─── Wysyłka zaproszeń z kolejki DB ─────────────────────────────────────

    /**
     * Wypełnij kolejkę e-maili dla danego głosowania.
     * Wstawiamy każdego uprawnionego użytkownika z danej grupy.
     *
     * @param string $job_id  ID zadania.
     * @param array  $params  ['poll_id' => int]
     */
    private static function fill_email_queue( string $job_id, array $params ): void {
        global $wpdb;

        $poll_id = absint( $params['poll_id'] ?? 0 );
        if ( ! $poll_id ) {
            return;
        }

        $poll = Openvote_Poll::get( $poll_id );
        if ( ! $poll ) {
            return;
        }

        $eq = $wpdb->prefix . 'openvote_email_queue';

        // Pobierz uprawnionych — użytkownicy z grup docelowych (lub wszyscy jeśli brak grupy).
        $target_groups = $poll->target_groups ? json_decode( $poll->target_groups, true ) : [];

        if ( ! empty( $target_groups ) ) {
            $gm_table     = $wpdb->prefix . 'openvote_group_members';
            $ids_clean    = array_map( 'absint', (array) $target_groups );
            $placeholders = implode( ',', array_fill( 0, count( $ids_clean ), '%d' ) );
            $users        = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT u.ID, u.user_email, u.display_name
                     FROM {$wpdb->users} u
                     INNER JOIN {$gm_table} gm ON u.ID = gm.user_id
                     WHERE gm.group_id IN ({$placeholders}) AND u.user_email != ''
                     GROUP BY u.ID",
                    ...$ids_clean
                )
            );
        } else {
            $users = $wpdb->get_results(
                "SELECT ID, user_email, display_name FROM {$wpdb->users} WHERE user_email != '' ORDER BY ID ASC"
            );
        }

        if ( empty( $users ) ) {
            return;
        }

        $now    = current_time( 'mysql' );
        $values = [];
        $fmt    = [];

        foreach ( $users as $u ) {
            if ( ! is_email( $u->user_email ) ) {
                continue;
            }
            if ( function_exists( 'openvote_is_user_inactive' ) && openvote_is_user_inactive( (int) $u->ID ) ) {
                continue;
            }
            $values[] = $job_id;
            $values[] = $poll_id;
            $values[] = (int) $u->ID;
            $values[] = sanitize_email( $u->user_email );
            $values[] = sanitize_text_field( $u->display_name );
            $values[] = $now;
            $fmt[]    = "(%s, %d, %d, %s, %s, %s)";
        }

        if ( empty( $fmt ) ) {
            return;
        }

        $suppress = $wpdb->suppress_errors( true );

        // Wstawiamy partiami po 500 — INSERT IGNORE pomija duplikaty (UNIQUE KEY poll_id+user_id).
        $chunk_size = 500;
        $chunk_fmt  = array_chunk( $fmt, $chunk_size );
        $chunk_val  = array_chunk( $values, $chunk_size * 6 );

        foreach ( $chunk_fmt as $idx => $chunk ) {
            // $chunk zawiera wyłącznie stałe stringi formatu (%s/%d) — nie dane użytkownika.
            $sql = "INSERT IGNORE INTO {$eq} (job_id, poll_id, user_id, email, name, created_at) VALUES "
                   . implode( ',', $chunk );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql zawiera tylko nazwę tabeli z $wpdb->prefix i stałe placeholdery formatu
            $wpdb->query( $wpdb->prepare( $sql, ...$chunk_val[ $idx ] ) );
            if ( $wpdb->last_error ) {
                error_log( 'openvote: fill_email_queue INSERT error: ' . $wpdb->last_error );
            }
        }

        // Reset wierszy 'failed' → 'pending' (ponowna próba wysyłki).
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$eq} SET status = 'pending', job_id = %s, error_msg = NULL WHERE poll_id = %d AND status = 'failed'",
                $job_id,
                $poll_id
            )
        );

        // Przypisz nowy job_id do wszystkich oczekujących wierszy.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$eq} SET job_id = %s WHERE poll_id = %d AND status = 'pending'",
                $job_id,
                $poll_id
            )
        );

        $wpdb->suppress_errors( $suppress );
    }

    /**
     * Wyślij jedną partię z kolejki dla zadania `send_invitations`.
     *
     * @param array $params ['job_id' => string, 'poll_id' => int]
     * @return array Lista wysłanych adresów e-mail.
     */
    private static function batch_send_invitations( array $params ): array {
        global $wpdb;

        $job_id  = sanitize_text_field( $params['job_id'] ?? '' );
        $poll_id = absint( $params['poll_id'] ?? 0 );

        if ( $job_id === '' || ! $poll_id ) {
            return [];
        }

        $poll = Openvote_Poll::get( $poll_id );
        if ( ! $poll ) {
            return [];
        }

        $method     = openvote_get_mail_method();
        $batch_size = openvote_get_email_batch_size();
        $eq         = $wpdb->prefix . 'openvote_email_queue';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email, name FROM {$eq} WHERE poll_id = %d AND status = 'pending' LIMIT %d",
                $poll_id,
                $batch_size
            )
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $subject    = openvote_render_email_template( openvote_get_email_subject_template(), $poll );
        $email_type = openvote_get_email_template_type();
        $message    = openvote_render_email_template( openvote_get_email_body_template(), $poll, $email_type );

        // Gdy treść wygląda na HTML, wysyłaj zawsze jako text/html (np. gdy w polu plain wklejono HTML).
        $message_is_html = str_starts_with( trim( $message ), '<' );
        $content_type    = ( $email_type === 'html' || $message_is_html ) ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
        $content_type_short = ( $email_type === 'html' || $message_is_html ) ? 'text/html' : 'text/plain';

        $sent      = [];
        $failed    = [];
        $extra_logs = [];

        if ( 'sendgrid' === $method ) {
            $recipients = [];
            foreach ( $rows as $row ) {
                $recipients[] = [ 'email' => $row->email, 'name' => $row->name ];
            }
            $content_type_sendgrid = $content_type_short;
            $result = Openvote_Mailer::send_via_sendgrid( $recipients, $subject, $message, '', $content_type_sendgrid );
            $all_sent = ( $result['sent'] === count( $rows ) );
            foreach ( $rows as $row ) {
                if ( $all_sent ) {
                    $sent[] = $row->email;
                } else {
                    $failed[ $row->id ] = $result['error'];
                }
            }
            if ( ! empty( $result['error'] ) ) {
                $extra_logs[] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                    /* translators: %s: error message from email provider */
                    __( 'Błąd dostawcy e-mail: %s', 'openvote' ),
                    $result['error']
                );
            }
        } elseif ( 'brevo' === $method || 'brevo_paid' === $method ) {
            $recipients = [];
            foreach ( $rows as $row ) {
                $recipients[] = [ 'email' => $row->email, 'name' => $row->name ];
            }
            $content_type_brevo = $content_type_short;
            $result = Openvote_Mailer::send_via_brevo( $recipients, $subject, $message, '', $content_type_brevo );
            $all_sent = ( $result['sent'] === count( $rows ) );
            foreach ( $rows as $row ) {
                if ( $all_sent ) {
                    $sent[] = $row->email;
                } else {
                    $failed[ $row->id ] = $result['error'];
                }
            }
            if ( ! empty( $result['error'] ) ) {
                $extra_logs[] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                    /* translators: %s: error message from email provider */
                    __( 'Błąd dostawcy e-mail: %s', 'openvote' ),
                    $result['error']
                );
            }
        } elseif ( 'freshmail' === $method ) {
            $recipients = [];
            foreach ( $rows as $row ) {
                $recipients[] = [ 'email' => $row->email, 'name' => $row->name ];
            }
            $content_type_fm = $content_type_short;
            $result = Openvote_Mailer::send_via_freshmail( $recipients, $subject, $message, '', '', $content_type_fm );
            $all_sent = ( $result['sent'] === count( $rows ) );
            foreach ( $rows as $row ) {
                if ( $all_sent ) {
                    $sent[] = $row->email;
                } else {
                    $failed[ $row->id ] = $result['error'];
                }
            }
            if ( ! empty( $result['error'] ) ) {
                $extra_logs[] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                    /* translators: %s: error message from email provider */
                    __( 'Błąd dostawcy e-mail: %s', 'openvote' ),
                    $result['error']
                );
            }
        } elseif ( 'getresponse' === $method ) {
            $recipients = [];
            foreach ( $rows as $row ) {
                $recipients[] = [ 'email' => $row->email, 'name' => $row->name ];
            }
            $content_type_gr = $content_type_short;
            $result = Openvote_Mailer::send_via_getresponse( $recipients, $subject, $message, '', '', $content_type_gr );
            $all_sent = ( $result['sent'] === count( $rows ) );
            foreach ( $rows as $row ) {
                if ( $all_sent ) {
                    $sent[] = $row->email;
                } else {
                    $failed[ $row->id ] = $result['error'];
                }
            }
            if ( ! empty( $result['error'] ) ) {
                $extra_logs[] = gmdate( 'Y-m-d H:i:s' ) . ' ' . sprintf(
                    /* translators: %s: error message from email provider */
                    __( 'Błąd dostawcy e-mail: %s', 'openvote' ),
                    $result['error']
                );
            }
        } else {
            $from_email   = openvote_get_from_email();
            $from_name    = openvote_render_email_template( openvote_get_email_from_template(), $poll );
            $headers      = [
                'Content-Type: ' . $content_type,
                'From: ' . $from_name . ' <' . $from_email . '>',
            ];
            foreach ( $rows as $row ) {
                $ok = wp_mail( $row->email, $subject, $message, $headers );
                if ( $ok ) {
                    $sent[] = $row->email;
                } else {
                    $failed[ $row->id ] = 'wp_mail error';
                }
            }
            if ( ! empty( $failed ) ) {
                $extra_logs[] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Błąd dostawcy e-mail przy wysyłce partii — sprawdź konfigurację.', 'openvote' );
            }
        }

        $now = current_time( 'mysql' );

        // Oznacz wysłane.
        if ( ! empty( $sent ) ) {
            $sent_ids = array_column(
                array_filter( $rows, fn( $r ) => in_array( $r->email, $sent, true ) ),
                'id'
            );
            if ( $sent_ids ) {
                $placeholders = implode( ',', array_fill( 0, count( $sent_ids ), '%d' ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$eq} SET status = 'sent', sent_at = %s WHERE id IN ({$placeholders})",
                    array_merge( [ $now ], $sent_ids )
                ) );
            }
        }

        // Oznacz nieudane.
        foreach ( $failed as $row_id => $error_msg ) {
            $wpdb->update(
                $eq,
                [ 'status' => 'failed', 'error_msg' => mb_substr( $error_msg, 0, 512 ) ],
                [ 'id' => $row_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        return [
            'items'      => $sent,
            'extra_logs' => $extra_logs,
        ];
    }

    // ─── Pomocnicze ─────────────────────────────────────────────────────────

    /**
     * Policz użytkowników pasujących do grupy.
     */
    private static function count_users_for_group( array $params ): int {
        global $wpdb;

        $city_value = sanitize_text_field( $params['city_value'] ?? '' );
        if ( '' === $city_value ) {
            return 0;
        }

        $city_key = Openvote_Field_Map::get_field( 'city' );

        if ( Openvote_Field_Map::is_core_field( $city_key ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->users} WHERE {$city_key} = %s",
                    $city_value
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
                 WHERE meta_key = %s AND meta_value = %s",
                sanitize_key( $city_key ),
                $city_value
            )
        );
    }

}
