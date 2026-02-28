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
 *   send_invitations     — wyślij zaproszenia z kolejki wp_evoting_email_queue
 */
class Evoting_Batch_Processor {

    const BATCH_SIZE              = 100;
    const EMAIL_BATCH_SIZE_DEFAULT = 20;  // WP/SMTP
    const EMAIL_SENDGRID_BATCH_DEFAULT = 100; // SendGrid

    // ─── Publiczne API ───────────────────────────────────────────────────────

    /**
     * Uruchom nowe zadanie.
     *
     * Dla `send_invitations` wypełnia tabelę wp_evoting_email_queue przed startem.
     *
     * @param string $type   Typ zadania (np. 'sync_group').
     * @param array  $params Parametry zadania.
     * @return string Job ID.
     */
    public static function start_job( string $type, array $params ): string {
        $job_id = uniqid( 'evoting_job_', true );

        // Dla wysyłki zaproszeń: najpierw wypełnij kolejkę w bazie.
        if ( 'send_invitations' === $type ) {
            self::fill_email_queue( $job_id, $params );
        }

        $total = self::count_total( $type, array_merge( $params, [ 'job_id' => $job_id ] ) );

        set_transient(
            $job_id,
            [
                'type'      => $type,
                'params'    => array_merge( $params, [ 'job_id' => $job_id ] ),
                'offset'    => 0,
                'total'     => $total,
                'processed' => 0,
                'status'    => 'running',
                'results'   => [],
            ],
            HOUR_IN_SECONDS
        );

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
     * Przetwórz następną partię dla danego zadania.
     *
     * @return array|false Zaktualizowany stan zadania lub false jeśli wygasło.
     */
    public static function process_batch( string $job_id ): array|false {
        $job = get_transient( $job_id );

        if ( false === $job ) {
            return false;
        }

        if ( 'done' === $job['status'] ) {
            return $job;
        }

        $result = self::run_batch( $job );
        $job    = $result['job'];

        // Sprawdź czy zadanie zakończone.
        if ( $job['offset'] >= $job['total'] || empty( $result['items'] ) ) {
            $job['status'] = 'done';
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
                if ( Evoting_Field_Map::is_city_disabled() ) {
                    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
                }
                // Policz unikalne wartości pola city.
                $city_key = Evoting_Field_Map::get_field( 'city' );
                if ( Evoting_Field_Map::is_core_field( $city_key ) ) {
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
                return (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email != ''"
                );

            case 'send_invitations':
                $poll_id_ci = absint( $params['poll_id'] ?? 0 );
                if ( ! $poll_id_ci ) {
                    return 0;
                }
                $eq = $wpdb->prefix . 'evoting_email_queue';
                return (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$eq} WHERE poll_id = %d AND status = 'pending'",
                        $poll_id_ci
                    )
                );

            default:
                return 0;
        }
    }

    /**
     * Wykonaj jedną partię rekordów.
     *
     * @return array{ job: array, items: array }
     */
    private static function run_batch( array $job ): array {
        $type   = $job['type'];
        $params = $job['params'];
        $offset = $job['offset'];
        $items  = [];

        switch ( $type ) {
            case 'sync_group':
                $items = self::batch_sync_group( $params, $offset );
                break;

            case 'sync_all_city_groups':
                $items = self::batch_sync_all_city_groups( $params, $offset );
                break;

            case 'send_start_emails':
                $items = self::batch_send_emails( $params, $offset );
                break;

            case 'send_invitations':
                $items = self::batch_send_invitations( $params );
                break;
        }

        $count             = count( $items );
        $job['processed'] += $count;

        if ( 'send_invitations' === $job['type'] ) {
            // Dla kolejki DB: offset = liczba wierszy, które już nie są pending (dynamicznie z DB).
            global $wpdb;
            $eq      = $wpdb->prefix . 'evoting_email_queue';
            $pid_run = absint( $job['params']['poll_id'] ?? 0 );
            $job['offset'] = $pid_run
                ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$eq} WHERE poll_id = %d AND status != 'pending'", $pid_run ) )
                : $job['total'];
        } else {
            $job['offset'] += $count > 0 ? self::BATCH_SIZE : $job['total'];
        }

        $job['results'] = array_merge( $job['results'], $items );

        return [ 'job' => $job, 'items' => $items ];
    }

    // ─── Implementacje typów zadań ───────────────────────────────────────────

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

        $city_key = Evoting_Field_Map::get_field( 'city' );
        $gm_table = $wpdb->prefix . 'evoting_group_members';

        // Pobierz partię użytkowników z pasującym miastem.
        if ( Evoting_Field_Map::is_core_field( $city_key ) ) {
            $safe_col = '`' . esc_sql( $city_key ) . '`';
            $users    = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} WHERE {$safe_col} = %s ORDER BY ID ASC LIMIT %d OFFSET %d",
                    $city_value,
                    self::BATCH_SIZE,
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
                    self::BATCH_SIZE,
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
            $wpdb->prefix . 'evoting_groups',
            [ 'member_count' => $count ],
            [ 'id' => $group_id ],
            [ '%d' ],
            [ '%d' ]
        );

        return array_column( $users, 'ID' );
    }

    /**
     * Synchronizuj wszystkie grupy-miasta — odkryj miasta, stwórz grupy, uruchom sync per miasto.
     * Gdy "Nie używaj miast": jedna grupa "Wszyscy", batch = 100 użytkowników dodanych do niej.
     */
    private static function batch_sync_all_city_groups( array $params, int $offset ): array {
        global $wpdb;

        if ( Evoting_Field_Map::is_city_disabled() ) {
            return self::batch_sync_wszyscy( $offset );
        }

        $city_key      = Evoting_Field_Map::get_field( 'city' );
        $groups_table  = $wpdb->prefix . 'evoting_groups';

        if ( Evoting_Field_Map::is_core_field( $city_key ) ) {
            $cities = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT {$city_key} FROM {$wpdb->users}
                     WHERE {$city_key} != '' ORDER BY {$city_key} ASC LIMIT %d OFFSET %d",
                    self::BATCH_SIZE,
                    $offset
                )
            );
        } else {
            $cities = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
                     WHERE meta_key = %s AND meta_value != ''
                     ORDER BY meta_value ASC LIMIT %d OFFSET %d",
                    sanitize_key( $city_key ),
                    self::BATCH_SIZE,
                    $offset
                )
            );
        }

        $processed = [];

        foreach ( $cities as $city ) {
            $city = sanitize_text_field( $city );

            // Stwórz grupę jeśli nie istnieje.
            $existing_id = $wpdb->get_var(
                $wpdb->prepare( "SELECT id FROM {$groups_table} WHERE name = %s", $city )
            );

            if ( ! $existing_id ) {
                $wpdb->insert(
                    $groups_table,
                    [ 'name' => $city, 'type' => 'city', 'member_count' => 0 ],
                    [ '%s', '%s', '%d' ]
                );
                $existing_id = $wpdb->insert_id;
            }

            // Uruchom pełen sync tej grupy (synchronicznie — wszystkie partie).
            if ( $existing_id ) {
                self::run_full_sync( (int) $existing_id, $city );
                $processed[] = $city;
            }
        }

        return $processed;
    }

    /**
     * Jedna partia: dodaj 100 użytkowników do grupy "Wszyscy" (tryb bez miast).
     */
    private static function batch_sync_wszyscy( int $offset ): array {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'evoting_groups';
        $gm_table     = $wpdb->prefix . 'evoting_group_members';
        $name         = Evoting_Field_Map::WSZYSCY_NAME;

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
                self::BATCH_SIZE,
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
     * Używany przy batch_sync_all_city_groups.
     */
    public static function run_full_sync( int $group_id, string $city_value ): int {
        global $wpdb;

        $total    = self::count_users_for_group( [ 'group_id' => $group_id, 'city_value' => $city_value ] );
        $offset   = 0;
        $added    = 0;

        while ( $offset < $total ) {
            $items  = self::batch_sync_group( [ 'group_id' => $group_id, 'city_value' => $city_value ], $offset );
            $added += count( $items );
            $offset += self::BATCH_SIZE;
        }

        return $added;
    }

    /**
     * Wyślij e-mail o otwarciu głosowania do partii użytkowników.
     */
    private static function batch_send_emails( array $params, int $offset ): array {
        global $wpdb;

        $poll_id = absint( $params['poll_id'] ?? 0 );
        $poll    = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return [];
        }

        $users = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_email, display_name FROM {$wpdb->users}
                 WHERE user_email != '' ORDER BY ID ASC LIMIT %d OFFSET %d",
                self::BATCH_SIZE,
                $offset
            )
        );

        if ( empty( $users ) ) {
            return [];
        }

        $from_email = evoting_get_from_email();
        $from_name  = evoting_render_email_template( evoting_get_email_from_template(), $poll );
        $subject    = evoting_render_email_template( evoting_get_email_subject_template(), $poll );
        $message    = evoting_render_email_template( evoting_get_email_body_template(), $poll );
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
        $sent    = [];

        foreach ( $users as $user ) {
            if ( is_email( $user->user_email ) ) {
                wp_mail( $user->user_email, $subject, $message, $headers );
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

        $poll = Evoting_Poll::get( $poll_id );
        if ( ! $poll ) {
            return;
        }

        $eq = $wpdb->prefix . 'evoting_email_queue';

        // Pobierz uprawnionych — użytkownicy z grup docelowych (lub wszyscy jeśli brak grupy).
        $target_groups = $poll->target_groups ? json_decode( $poll->target_groups, true ) : [];

        if ( ! empty( $target_groups ) ) {
            $gm_table     = $wpdb->prefix . 'evoting_group_members';
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
                error_log( 'evoting: fill_email_queue INSERT error: ' . $wpdb->last_error );
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

        $poll = Evoting_Poll::get( $poll_id );
        if ( ! $poll ) {
            return [];
        }

        $method     = evoting_get_mail_method();
        $batch_size = evoting_get_email_batch_size();
        $eq         = $wpdb->prefix . 'evoting_email_queue';

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

        $subject = evoting_render_email_template( evoting_get_email_subject_template(), $poll );
        $message = evoting_render_email_template( evoting_get_email_body_template(), $poll );

        $sent   = [];
        $failed = [];

        if ( 'sendgrid' === $method ) {
            $recipients = [];
            foreach ( $rows as $row ) {
                $recipients[] = [ 'email' => $row->email, 'name' => $row->name ];
            }
            $result = Evoting_Mailer::send_via_sendgrid( $recipients, $subject, $message );
            foreach ( $rows as $row ) {
                if ( $result['sent'] > 0 ) {
                    $sent[] = $row->email;
                } else {
                    $failed[ $row->id ] = $result['error'];
                }
            }
        } else {
            $from_email = evoting_get_from_email();
            $from_name  = evoting_render_email_template( evoting_get_email_from_template(), $poll );
            $headers    = [
                'Content-Type: text/plain; charset=UTF-8',
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

        return $sent;
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

        $city_key = Evoting_Field_Map::get_field( 'city' );

        if ( Evoting_Field_Map::is_core_field( $city_key ) ) {
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
