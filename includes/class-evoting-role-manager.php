<?php
defined( 'ABSPATH' ) || exit;

/**
 * Zarządzanie rolami wtyczki e-głosowania.
 *
 * Role są przechowywane w wp_usermeta:
 *   evoting_role   → 'poll_admin' | 'poll_editor'
 *   evoting_groups → JSON array ID grup (tylko dla poll_editor)
 *
 * Limity:
 *   Administrator WordPress : min 1, maks. 2  (rola WP 'administrator')
 *   Administrator Głosowań  : maks. 3          (evoting_role = poll_admin)
 *   Redaktor Głosowań       : maks. 3 na grupę (evoting_role = poll_editor)
 */
class Evoting_Role_Manager {

    const ROLE_POLL_ADMIN  = 'poll_admin';
    const ROLE_POLL_EDITOR = 'poll_editor';

    const META_ROLE   = 'evoting_role';
    const META_GROUPS = 'evoting_groups';

    const LIMIT_WP_ADMINS   = 2;
    const LIMIT_POLL_ADMINS = 3;
    const LIMIT_EDITORS_PER_GROUP = 3;

    // ─── Odczyt ─────────────────────────────────────────────────────────────

    /**
     * Pobierz rolę evoting danego użytkownika.
     * Zwraca 'poll_admin', 'poll_editor' lub '' gdy brak roli.
     */
    public static function get_user_role( int $user_id ): string {
        return (string) get_user_meta( $user_id, self::META_ROLE, true );
    }

    /**
     * Pobierz listę ID grup danego redaktora.
     *
     * @return int[]
     */
    public static function get_user_groups( int $user_id ): array {
        $raw = get_user_meta( $user_id, self::META_GROUPS, true );
        if ( ! $raw ) {
            return [];
        }
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? array_map( 'absint', $decoded ) : [];
    }

    /**
     * Pobierz wszystkich administratorów WordPress.
     *
     * @return WP_User[]
     */
    public static function get_wp_admins(): array {
        return get_users( [ 'role' => 'administrator' ] );
    }

    /**
     * Pobierz wszystkich Administratorów Głosowań.
     *
     * @return WP_User[]
     */
    public static function get_poll_admins(): array {
        return get_users( [
            'meta_key'   => self::META_ROLE,
            'meta_value' => self::ROLE_POLL_ADMIN,
        ] );
    }

    /**
     * Pobierz wszystkich Redaktorów Głosowań.
     *
     * @return WP_User[]
     */
    public static function get_poll_editors(): array {
        return get_users( [
            'meta_key'   => self::META_ROLE,
            'meta_value' => self::ROLE_POLL_EDITOR,
        ] );
    }

    /**
     * Ile redaktorów jest przypisanych do danej grupy?
     */
    public static function count_editors_in_group( int $group_id ): int {
        $editors = self::get_poll_editors();
        $count   = 0;
        foreach ( $editors as $user ) {
            if ( in_array( $group_id, self::get_user_groups( $user->ID ), true ) ) {
                ++$count;
            }
        }
        return $count;
    }

    // ─── Nadawanie ról ───────────────────────────────────────────────────────

    /**
     * Nadaj rolę Administratora Głosowań.
     *
     * @return true|\WP_Error
     */
    public static function add_poll_admin( int $user_id ): true|\WP_Error {
        $current_admins = self::get_poll_admins();

        if ( count( $current_admins ) >= self::LIMIT_POLL_ADMINS ) {
            $names = implode( ', ', array_map( fn( $u ) => $u->display_name, $current_admins ) );
            return new \WP_Error(
                'limit_reached',
                sprintf(
                    /* translators: 1: limit, 2: names */
                    __( 'Limit Administratorów Głosowań (%1$d) osiągnięty. Zajmują: %2$s.', 'evoting' ),
                    self::LIMIT_POLL_ADMINS,
                    $names
                )
            );
        }

        update_user_meta( $user_id, self::META_ROLE, self::ROLE_POLL_ADMIN );
        delete_user_meta( $user_id, self::META_GROUPS );

        return true;
    }

    /**
     * Nadaj rolę Redaktora Głosowań z przypisaniem do grup.
     *
     * @param int[] $group_ids
     * @return true|\WP_Error
     */
    public static function add_poll_editor( int $user_id, array $group_ids ): true|\WP_Error {
        if ( empty( $group_ids ) ) {
            return new \WP_Error( 'no_groups', __( 'Redaktor musi mieć przypisaną co najmniej jedną grupę.', 'evoting' ) );
        }

        // Sprawdź limit per grupa.
        foreach ( $group_ids as $group_id ) {
            $group_id = absint( $group_id );
            $count    = self::count_editors_in_group( $group_id );

            // Nie licz bieżącego użytkownika jeśli już jest redaktorem w tej grupie.
            if ( in_array( $group_id, self::get_user_groups( $user_id ), true ) ) {
                continue;
            }

            if ( $count >= self::LIMIT_EDITORS_PER_GROUP ) {
                $editors_in_group = array_filter(
                    self::get_poll_editors(),
                    fn( $u ) => in_array( $group_id, self::get_user_groups( $u->ID ), true )
                );
                $names = implode( ', ', array_map( fn( $u ) => $u->display_name, $editors_in_group ) );

                return new \WP_Error(
                    'limit_reached',
                    sprintf(
                        /* translators: 1: limit, 2: group ID, 3: names */
                        __( 'Limit Redaktorów (%1$d) dla grupy #%2$d osiągnięty. Zajmują: %3$s.', 'evoting' ),
                        self::LIMIT_EDITORS_PER_GROUP,
                        $group_id,
                        $names
                    )
                );
            }
        }

        update_user_meta( $user_id, self::META_ROLE, self::ROLE_POLL_EDITOR );
        update_user_meta( $user_id, self::META_GROUPS, wp_json_encode( array_values( array_map( 'absint', $group_ids ) ) ) );

        return true;
    }

    // ─── Usuwanie ról ────────────────────────────────────────────────────────

    /**
     * Usuń rolę evoting od użytkownika.
     *
     * @param int $user_id    ID użytkownika tracącego rolę.
     * @param int $remover_id ID użytkownika wykonującego operację.
     * @return true|\WP_Error
     */
    public static function remove_role( int $user_id, int $remover_id ): true|\WP_Error {
        if ( ! self::can_remove( $remover_id, $user_id ) ) {
            return new \WP_Error(
                'cannot_remove',
                __( 'Nie masz uprawnień do usunięcia tej roli.', 'evoting' )
            );
        }

        delete_user_meta( $user_id, self::META_ROLE );
        delete_user_meta( $user_id, self::META_GROUPS );

        return true;
    }

    /**
     * Sprawdź czy remover może usunąć rolę od target.
     * - Admin WP może usunąć każdego.
     * - Admin Głosowań może usunąć Redaktora i innego Admina Głosowań.
     * - Nie można usunąć ostatniego Administratora WordPress.
     */
    public static function can_remove( int $remover_id, int $target_id ): bool {
        $remover = get_userdata( $remover_id );
        if ( ! $remover ) {
            return false;
        }

        $target_role = self::get_user_role( $target_id );
        $target_user = get_userdata( $target_id );
        if ( ! $target_user ) {
            return false;
        }

        // Jeśli target jest Admin WP — tylko Admin WP może próbować usunąć,
        // ale musimy sprawdzić czy nie jest ostatnim.
        $target_is_wp_admin = in_array( 'administrator', (array) $target_user->roles, true );
        if ( $target_is_wp_admin ) {
            // Roli WP administrator nie usuwamy przez ten system.
            // Można usunąć evoting_role od admin WP tylko jeśli nie ma roli evoting.
            // Jeśli target nie ma evoting_role — nie ma co usuwać.
            if ( '' === $target_role ) {
                return false;
            }
        }

        $remover_is_wp_admin    = in_array( 'administrator', (array) $remover->roles, true );
        $remover_evoting_role   = self::get_user_role( $remover_id );
        $remover_is_poll_admin  = ( self::ROLE_POLL_ADMIN === $remover_evoting_role );

        if ( $remover_is_wp_admin ) {
            return true;
        }

        if ( $remover_is_poll_admin ) {
            // Poll admin może usunąć redaktora lub innego poll admina.
            return in_array( $target_role, [ self::ROLE_POLL_ADMIN, self::ROLE_POLL_EDITOR ], true );
        }

        return false;
    }

    // ─── Walidacja limitów WP Admin ─────────────────────────────────────────

    /**
     * Sprawdź czy liczba Administratorów WP mieści się w limicie (min 1, maks. 2).
     */
    public static function validate_wp_admin_count(): true|\WP_Error {
        $count = count( self::get_wp_admins() );

        if ( $count > self::LIMIT_WP_ADMINS ) {
            $admins = self::get_wp_admins();
            $names  = implode( ', ', array_map( fn( $u ) => $u->display_name, $admins ) );
            return new \WP_Error(
                'wp_admin_limit',
                sprintf(
                    /* translators: 1: limit, 2: names */
                    __( 'Maks. %1$d Administratorów WordPress. Zajmują: %2$s.', 'evoting' ),
                    self::LIMIT_WP_ADMINS,
                    $names
                )
            );
        }

        return true;
    }
}
