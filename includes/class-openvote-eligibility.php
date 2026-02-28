<?php
defined( 'ABSPATH' ) || exit;

/**
 * Weryfikacja uprawnień do głosowania — 6 sprawdzeń w kolejności.
 *
 * Zwracana wartość: array{ eligible: bool, reason: string }
 *   eligible = true  → użytkownik może głosować
 *   eligible = false → reason zawiera komunikat dla użytkownika
 */
class Openvote_Eligibility {

    /**
     * Sprawdź czy dany użytkownik może oddać głos w danym głosowaniu.
     *
 * Kolejność sprawdzeń:
 *   1. Głosowanie istnieje i ma status 'open'
 *   2. Bieżący moment (data i godzina) między date_start a date_end
 *   3. Użytkownik jest zalogowany
 *   4. Profil kompletny: Imię, Nazwisko, Nickname, E-mail, Miasto
 *   5. Użytkownik należy do grupy docelowej (lub target_groups = null)
 *   6. Użytkownik jeszcze nie głosował
 *
 * Każdy zarejestrowany użytkownik przypisany do grupy docelowej może głosować
 * przez cały czas trwania głosowania (bez ograniczeń do momentu zakończenia).
     *
     * @param int $user_id  0 jeśli niezalogowany
     * @param int $poll_id
     * @return array{ eligible: bool, reason: string }
     */
    public static function can_vote( int $user_id, int $poll_id ): array {

        // ── 1. Głosowanie istnieje i status = 'open' ───────────────────────
        $poll = Openvote_Poll::get( $poll_id );

        if ( ! $poll ) {
            return self::no( __( 'Głosowanie nie istnieje.', 'openvote' ) );
        }

        if ( 'open' !== $poll->status ) {
            $labels = [
                'draft'  => __( 'Głosowanie jest jeszcze w przygotowaniu.', 'openvote' ),
                'closed' => __( 'Głosowanie zostało zakończone.', 'openvote' ),
            ];
            return self::no( $labels[ $poll->status ] ?? __( 'Głosowanie nie jest aktywne.', 'openvote' ) );
        }

        // ── 2. Bieżący moment mieści się między date_start a date_end ───────
        $now = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
        $start = self::normalize_poll_datetime( $poll->date_start, true );
        $end   = self::normalize_poll_datetime( $poll->date_end, false );

        if ( $now < $start ) {
            return self::no(
                sprintf(
                    /* translators: %s: data i godzina rozpoczęcia */
                    __( 'Głosowanie rozpocznie się %s.', 'openvote' ),
                    $start
                )
            );
        }

        if ( $now > $end ) {
            return self::no(
                sprintf(
                    /* translators: %s: data i godzina zakończenia */
                    __( 'Głosowanie zakończyło się %s.', 'openvote' ),
                    $end
                )
            );
        }

        // ── 3. Użytkownik zalogowany ────────────────────────────────────────
        if ( ! $user_id || ! is_user_logged_in() ) {
            return self::no( __( 'Musisz być zalogowany, aby wziąć udział w głosowaniu.', 'openvote' ) );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return self::no( __( 'Konto użytkownika nie istnieje.', 'openvote' ) );
        }

        // ── 4. Profil kompletny — weryfikacja pól oznaczonych jako wymagane ──
        $required = Openvote_Field_Map::get_required_fields();

        foreach ( $required as $logical => $label ) {
            if ( '' === Openvote_Field_Map::get_user_value( $user, $logical ) ) {
                return self::no(
                    sprintf(
                        /* translators: %s: nazwa pola */
                        __( 'Twój profil jest niekompletny. Brakuje: %s.', 'openvote' ),
                        $label
                    )
                );
            }
        }

        // ── 5. Przynależność do grupy docelowej ─────────────────────────────
        $group_ids = Openvote_Poll::get_target_group_ids( $poll );

        if ( ! empty( $group_ids ) ) {
            if ( Openvote_Field_Map::is_city_disabled() ) {
                $wszyscy_id = self::get_wszyscy_group_id();
                if ( $wszyscy_id && [ $wszyscy_id ] === array_map( 'intval', $group_ids ) ) {
                    return [ 'eligible' => true, 'reason' => '' ];
                }
            }

            global $wpdb;
            $gm_table     = $wpdb->prefix . 'openvote_group_members';
            $placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

            $is_member = (bool) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$gm_table}
                     WHERE user_id = %d AND group_id IN ({$placeholders})",
                    array_merge( [ $user_id ], $group_ids )
                )
            );

            if ( ! $is_member ) {
                return self::no(
                    __( 'To głosowanie jest dostępne tylko dla wybranych grup. Twoje konto nie należy do żadnej z nich.', 'openvote' )
                );
            }
        }

        // ── 6. Użytkownik jeszcze nie głosował ─────────────────────────────
        if ( Openvote_Vote::has_voted( $poll_id, $user_id ) ) {
            return self::no( __( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'openvote' ) );
        }

        return [ 'eligible' => true, 'reason' => '' ];
    }

    /**
     * ID grupy "Wszyscy" (gdy opcja "Nie używaj miast" jest włączona).
     *
     * @return int|null
     */
    private static function get_wszyscy_group_id(): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'openvote_groups';
        $id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s", Openvote_Field_Map::WSZYSCY_NAME ) );
        return $id ? (int) $id : null;
    }

    /**
     * Skrót — zwróć tablicę z eligible=false.
     */
    private static function no( string $reason ): array {
        return [ 'eligible' => false, 'reason' => $reason ];
    }

    /**
     * Normalizes poll date/datetime from DB to Y-m-d H:i:s for comparison.
     * Legacy DATE-only values: start → 00:00:00, end → 23:59:59.
     *
     * @param string $value date_start or date_end from DB.
     * @param bool   $is_start true for date_start (default time 00:00:00).
     * @return string Y-m-d H:i:s
     */
    private static function normalize_poll_datetime( string $value, bool $is_start ): string {
        $value = trim( $value );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return $value . ( $is_start ? ' 00:00:00' : ' 23:59:59' );
        }
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}(?::\d{2})?$/', $value ) ) {
            return strlen( $value ) === 16 ? $value . ':00' : $value;
        }
        return $value;
    }

    /**
     * Wersja zwracająca WP_Error — dla kompatybilności wstecznej.
     * Używana w class-openvote-vote.php::cast().
     *
     * @return true|\WP_Error
     */
    public static function can_vote_or_error( int $user_id, int $poll_id ): true|\WP_Error {
        $result = self::can_vote( $user_id, $poll_id );
        if ( $result['eligible'] ) {
            return true;
        }
        return new \WP_Error( 'not_eligible', $result['reason'], [ 'status' => 403 ] );
    }
}
