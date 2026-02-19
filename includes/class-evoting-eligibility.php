<?php
defined( 'ABSPATH' ) || exit;

/**
 * Weryfikacja uprawnień do głosowania — 7 sprawdzeń w kolejności.
 *
 * Zwracana wartość: array{ eligible: bool, reason: string }
 *   eligible = true  → użytkownik może głosować
 *   eligible = false → reason zawiera komunikat dla użytkownika
 */
class Evoting_Eligibility {

    /**
     * Sprawdź czy dany użytkownik może oddać głos w danym głosowaniu.
     *
     * Kolejność sprawdzeń:
     *   1. Głosowanie istnieje i ma status 'open'
     *   2. Dzisiejsza data między date_start a date_end
     *   3. Użytkownik jest zalogowany
     *   4. Profil kompletny: Imię, Nazwisko, Nickname, E-mail, Miasto
     *   5. Użytkownik należy do grupy docelowej (lub target_groups = null)
     *   6. Użytkownik jeszcze nie głosował
     *   7. Jeśli join_mode = 'closed' → użytkownik na liście snapshot
     *
     * @param int $user_id  0 jeśli niezalogowany
     * @param int $poll_id
     * @return array{ eligible: bool, reason: string }
     */
    public static function can_vote( int $user_id, int $poll_id ): array {

        // ── 1. Głosowanie istnieje i status = 'open' ───────────────────────
        $poll = Evoting_Poll::get( $poll_id );

        if ( ! $poll ) {
            return self::no( __( 'Głosowanie nie istnieje.', 'evoting' ) );
        }

        if ( 'open' !== $poll->status ) {
            $labels = [
                'draft'  => __( 'Głosowanie jest jeszcze w przygotowaniu.', 'evoting' ),
                'closed' => __( 'Głosowanie zostało zakończone.', 'evoting' ),
            ];
            return self::no( $labels[ $poll->status ] ?? __( 'Głosowanie nie jest aktywne.', 'evoting' ) );
        }

        // ── 2. Data mieści się między date_start a date_end ────────────────
        $today = current_time( 'Y-m-d' );

        if ( $today < $poll->date_start ) {
            return self::no(
                sprintf(
                    /* translators: %s: data rozpoczęcia */
                    __( 'Głosowanie rozpocznie się %s.', 'evoting' ),
                    $poll->date_start
                )
            );
        }

        if ( $today > $poll->date_end ) {
            return self::no(
                sprintf(
                    /* translators: %s: data zakończenia */
                    __( 'Głosowanie zakończyło się %s.', 'evoting' ),
                    $poll->date_end
                )
            );
        }

        // ── 3. Użytkownik zalogowany ────────────────────────────────────────
        if ( ! $user_id || ! is_user_logged_in() ) {
            return self::no( __( 'Musisz być zalogowany, aby wziąć udział w głosowaniu.', 'evoting' ) );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return self::no( __( 'Konto użytkownika nie istnieje.', 'evoting' ) );
        }

        // ── 4. Profil kompletny ─────────────────────────────────────────────
        $required = [
            'first_name' => Evoting_Field_Map::LABELS['first_name'],
            'last_name'  => Evoting_Field_Map::LABELS['last_name'],
            'nickname'   => Evoting_Field_Map::LABELS['nickname'],
            'email'      => Evoting_Field_Map::LABELS['email'],
            'city'       => Evoting_Field_Map::LABELS['city'],
        ];

        foreach ( $required as $logical => $label ) {
            if ( '' === Evoting_Field_Map::get_user_value( $user, $logical ) ) {
                return self::no(
                    sprintf(
                        /* translators: %s: nazwa pola */
                        __( 'Twój profil jest niekompletny. Brakuje: %s.', 'evoting' ),
                        $label
                    )
                );
            }
        }

        // ── 5. Przynależność do grupy docelowej ─────────────────────────────
        $group_ids = Evoting_Poll::get_target_group_ids( $poll );

        if ( ! empty( $group_ids ) ) {
            global $wpdb;
            $gm_table     = $wpdb->prefix . 'evoting_group_members';
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
                    __( 'To głosowanie jest dostępne tylko dla wybranych grup. Twoje konto nie należy do żadnej z nich.', 'evoting' )
                );
            }
        }

        // ── 6. Użytkownik jeszcze nie głosował ─────────────────────────────
        if ( Evoting_Vote::has_voted( $poll_id, $user_id ) ) {
            return self::no( __( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'evoting' ) );
        }

        // ── 7. Tryb zamknięty → snapshot ───────────────────────────────────
        if ( 'closed' === $poll->join_mode ) {
            if ( ! Evoting_Batch_Processor::is_in_snapshot( $poll_id, $user_id ) ) {
                return self::no(
                    __( 'To głosowanie jest zamknięte. Lista uprawnionych została ustalona przy otwarciu głosowania i nie obejmuje Twojego konta.', 'evoting' )
                );
            }
        }

        return [ 'eligible' => true, 'reason' => '' ];
    }

    /**
     * Skrót — zwróć tablicę z eligible=false.
     */
    private static function no( string $reason ): array {
        return [ 'eligible' => false, 'reason' => $reason ];
    }

    /**
     * Wersja zwracająca WP_Error — dla kompatybilności wstecznej.
     * Używana w class-evoting-vote.php::cast().
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
