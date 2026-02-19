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
 *   sync_group          — synchronizuj jedną grupę (city lub custom) z usermeta
 *   sync_all_city_groups — odkryj wszystkie miasta i stwórz/synchronizuj grupy
 *   send_start_emails   — wyślij e-mail o otwarciu głosowania
 *   send_reminder_emails — wyślij e-mail przypomnienie (24h przed końcem)
 *   build_snapshot      — buduj snapshot uprawnionych (join_mode=closed)
 */
class Evoting_Batch_Processor {

    const BATCH_SIZE = 100;

    // ─── Publiczne API ───────────────────────────────────────────────────────

    /**
     * Uruchom nowe zadanie.
     *
     * @param string $type   Typ zadania (np. 'sync_group').
     * @param array  $params Parametry zadania.
     * @return string Job ID.
     */
    public static function start_job( string $type, array $params ): string {
        $job_id = uniqid( 'evoting_job_', true );

        $total = self::count_total( $type, $params );

        set_transient(
            $job_id,
            [
                'type'      => $type,
                'params'    => $params,
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
            case 'send_reminder_emails':
                $poll_id = absint( $params['poll_id'] ?? 0 );
                if ( ! $poll_id ) {
                    return 0;
                }
                // Policz uprawnionych użytkowników z adresem e-mail.
                return (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email != ''"
                );

            case 'build_snapshot':
                // Policz uprawnionych dla głosowania.
                $poll_id = absint( $params['poll_id'] ?? 0 );
                if ( ! $poll_id ) {
                    return 0;
                }
                $poll = Evoting_Poll::get( $poll_id );
                return $poll ? Evoting_Vote::get_eligible_count( $poll ) : 0;

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
            case 'send_reminder_emails':
                $items = self::batch_send_emails( $params, $offset, $type );
                break;

            case 'build_snapshot':
                $items = self::batch_build_snapshot( $params, $offset );
                break;
        }

        $count            = count( $items );
        $job['offset']   += $count > 0 ? self::BATCH_SIZE : $job['total']; // przesuń do przodu
        $job['processed'] += $count;
        $job['results']   = array_merge( $job['results'], $items );

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
            $users = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} WHERE {$city_key} = %s ORDER BY ID ASC LIMIT %d OFFSET %d",
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
     * Jeden batch = jedno miasto.
     */
    private static function batch_sync_all_city_groups( array $params, int $offset ): array {
        global $wpdb;

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
     * Wyślij e-mail do partii użytkowników.
     */
    private static function batch_send_emails( array $params, int $offset, string $type ): array {
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

        if ( 'send_start_emails' === $type ) {
            $subject = sprintf( __( 'Nowe głosowanie: %s', 'evoting' ), $poll->title );
            $message = sprintf(
                __( "Zostało otwarte nowe głosowanie: %s\n\nZaloguj się, aby wziąć udział.", 'evoting' ),
                $poll->title
            );
        } else {
            $subject = sprintf( __( 'Przypomnienie: głosowanie kończy się wkrótce — %s', 'evoting' ), $poll->title );
            $message = sprintf(
                __( "Przypominamy, że głosowanie \"%s\" kończy się %s.\n\nZaloguj się i oddaj głos.", 'evoting' ),
                $poll->title,
                $poll->date_end
            );
        }

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
        $sent    = [];

        foreach ( $users as $user ) {
            if ( is_email( $user->user_email ) ) {
                wp_mail( $user->user_email, $subject, $message, $headers );
                $sent[] = $user->user_email;
            }
        }

        return $sent;
    }

    /**
     * Buduj snapshot uprawnionych do głosowania (join_mode = closed).
     * Snapshot zapisuje user_id w wp_evoting_group_members z source='snapshot_{poll_id}'.
     */
    private static function batch_build_snapshot( array $params, int $offset ): array {
        global $wpdb;

        $poll_id = absint( $params['poll_id'] ?? 0 );
        $poll    = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return [];
        }

        $snapshot_table = $wpdb->prefix . 'evoting_poll_snapshots';

        // Użyj tymczasowej meta-opcji jako storage snapshotów (prostsze niż nowa tabela).
        $map    = Evoting_Field_Map::get();
        $params_with_map = array_merge( $params, [ 'map' => $map ] );

        // Pobierz uprawnionych użytkowników w tej partii.
        $city_key = Evoting_Field_Map::get_field( 'city' );
        $group_ids = Evoting_Poll::get_target_group_ids( $poll );

        if ( ! empty( $group_ids ) ) {
            $gm_table     = $wpdb->prefix . 'evoting_group_members';
            $g_holders    = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
            $users = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT u.ID FROM {$wpdb->users} u
                     INNER JOIN {$gm_table} gm ON u.ID = gm.user_id AND gm.group_id IN ({$g_holders})
                     ORDER BY u.ID ASC LIMIT %d OFFSET %d",
                    array_merge( $group_ids, [ self::BATCH_SIZE, $offset ] )
                )
            );
        } else {
            $users = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} ORDER BY ID ASC LIMIT %d OFFSET %d",
                    self::BATCH_SIZE,
                    $offset
                )
            );
        }

        // Zapisz snapshot jako JSON w opcji.
        $option_key    = "evoting_snapshot_{$poll_id}";
        $existing      = (array) get_option( $option_key, [] );
        $new_user_ids  = array_column( $users, 'ID' );
        $merged        = array_unique( array_merge( $existing, array_map( 'absint', $new_user_ids ) ) );
        update_option( $option_key, $merged, false );

        return $new_user_ids;
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

    /**
     * Sprawdź czy użytkownik jest na snapshocie dla głosowania.
     */
    public static function is_in_snapshot( int $poll_id, int $user_id ): bool {
        $option_key = "evoting_snapshot_{$poll_id}";
        $snapshot   = (array) get_option( $option_key, [] );
        return in_array( $user_id, $snapshot, false );
    }

    /**
     * Usuń snapshot dla głosowania.
     */
    public static function delete_snapshot( int $poll_id ): void {
        delete_option( "evoting_snapshot_{$poll_id}" );
    }
}
