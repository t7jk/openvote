<?php
defined( 'ABSPATH' ) || exit;

/**
 * Zarządzanie rolami wtyczki e-głosowania.
 *
 * Role są przechowywane w wp_usermeta:
 *   openvote_role   → 'poll_admin' | 'poll_editor'
 *   openvote_groups → JSON array ID grup (tylko dla poll_editor)
 *
 * Limity:
 *   Administrator WordPress : min 1, maks. 2  (rola WP 'administrator')
 *   Koordynator             : maks. 3          (openvote_role = poll_admin)
 *   Lokalny Koordynator Grup: maks. 3 na grupę (openvote_role = poll_editor)
 *
 * Administrator WordPress: tylko użytkownicy z grupy "Administratorzy" (wp_openvote_groups).
 */
class Openvote_Role_Manager {

    const ROLE_POLL_ADMIN  = 'poll_admin';
    const ROLE_POLL_EDITOR = 'poll_editor';

    const META_ROLE   = 'openvote_role';
    const META_GROUPS = 'openvote_groups';

    /** Nazwa grupy, której członkowie mogą być administratorami WordPress. */
    const WP_ADMIN_GROUP_NAME = 'Administratorzy';

    const LIMIT_WP_ADMINS   = 2;
    const LIMIT_POLL_ADMINS = 3;
    const LIMIT_EDITORS_PER_GROUP = 3;

    // ─── Odczyt ─────────────────────────────────────────────────────────────

    /**
     * Pobierz rolę openvote danego użytkownika.
     * Zwraca 'poll_admin', 'poll_editor' lub '' gdy brak roli.
     */
    public static function get_user_role( int $user_id ): string {
        return (string) get_user_meta( $user_id, self::META_ROLE, true );
    }

    /**
     * Pobierz listę ID grup danego lokalnego koordynatora grup.
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
     * ID grupy "Administratorzy" (tylko jej członkowie mogą być administratorami WP).
     *
     * @return int|null
     */
    public static function get_administrators_group_id(): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'openvote_groups';
        $name  = self::WP_ADMIN_GROUP_NAME;
        $id    = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE name = %s LIMIT 1",
            $name
        ) );
        return $id ? (int) $id : null;
    }

    /**
     * Czy użytkownik należy do grupy "Administratorzy"?
     */
    public static function is_user_in_administrators_group( int $user_id ): bool {
        $group_id = self::get_administrators_group_id();
        if ( ! $group_id ) {
            return false;
        }
        global $wpdb;
        $gm_table = $wpdb->prefix . 'openvote_group_members';
        $exists   = $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM {$gm_table} WHERE group_id = %d AND user_id = %d LIMIT 1",
            $group_id,
            $user_id
        ) );
        return (bool) $exists;
    }

    /**
     * Pobierz użytkowników należących do grupy "Administratorzy".
     *
     * @return \WP_User[]
     */
    public static function get_users_in_administrators_group(): array {
        $group_id = self::get_administrators_group_id();
        if ( ! $group_id ) {
            return [];
        }
        global $wpdb;
        $gm_table = $wpdb->prefix . 'openvote_group_members';
        $ids      = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$gm_table} WHERE group_id = %d ORDER BY user_id ASC",
            $group_id
        ) );
        if ( empty( $ids ) ) {
            return [];
        }
        return get_users( [
            'include' => array_map( 'absint', $ids ),
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ] );
    }

    /**
     * Pobierz wszystkich administratorów WordPress.
     *
     * @return \WP_User[]
     */
    public static function get_wp_admins(): array {
        return get_users( [ 'role' => 'administrator' ] );
    }

    /**
     * Dodaj użytkownikowi rolę WordPress "administrator".
     * Tylko użytkownicy z grupy "Administratorzy", limit 2.
     *
     * @return true|\WP_Error
     */
    public static function add_wp_admin( int $user_id ): true|\WP_Error {
        $group_id = self::get_administrators_group_id();
        if ( ! $group_id ) {
            return new \WP_Error( 'no_group', __( 'Sejmik „Administratorzy” nie istnieje. Utwórz go w sekcji Sejmiki.', 'openvote' ) );
        }
        if ( ! self::is_user_in_administrators_group( $user_id ) ) {
            return new \WP_Error( 'not_in_group', __( 'Tylko użytkownicy z sejmiku „Administratorzy” mogą zostać administratorami WordPress.', 'openvote' ) );
        }
        $lock = 'openvote_wp_admin_lock';
        if ( get_transient( $lock ) ) {
            return new \WP_Error( 'concurrent_request', __( 'Operacja w toku. Spróbuj za chwilę.', 'openvote' ) );
        }
        set_transient( $lock, true, 30 );

        $current = self::get_wp_admins();
        if ( count( $current ) >= self::LIMIT_WP_ADMINS ) {
            delete_transient( $lock );
            $names = implode( ', ', array_map( fn( $u ) => $u->display_name, $current ) );
            return new \WP_Error(
                'limit_reached',
                sprintf(
                    /* translators: 1: limit, 2: names */
                    __( 'Limit Administratorów WordPress (%1$d) osiągnięty. Zajmują: %2$s.', 'openvote' ),
                    self::LIMIT_WP_ADMINS,
                    $names
                )
            );
        }
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            delete_transient( $lock );
            return new \WP_Error( 'invalid_user', __( 'Nieprawidłowy użytkownik.', 'openvote' ) );
        }
        $user->set_role( 'administrator' );
        delete_transient( $lock );
        return true;
    }

    /**
     * Usuń użytkownikowi rolę WordPress "administrator".
     * Nie można usunąć ostatniego administratora.
     *
     * @return true|\WP_Error
     */
    public static function remove_wp_admin( int $user_id, int $remover_id ): true|\WP_Error {
        $admins = self::get_wp_admins();
        if ( count( $admins ) <= 1 ) {
            return new \WP_Error( 'last_admin', __( 'Nie można usunąć ostatniego Administratora WordPress.', 'openvote' ) );
        }
        $target = get_userdata( $user_id );
        if ( ! $target || ! in_array( 'administrator', (array) $target->roles, true ) ) {
            return new \WP_Error( 'not_admin', __( 'Ten użytkownik nie jest administratorem WordPress.', 'openvote' ) );
        }
        $remover = get_userdata( $remover_id );
        if ( ! $remover || ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'cannot_remove', __( 'Nie masz uprawnień do usunięcia tej roli.', 'openvote' ) );
        }
        $target->set_role( get_option( 'default_role', 'subscriber' ) );
        return true;
    }

    /**
     * Egzekwowanie: tylko użytkownicy z grupy "Administratorzy" mogą mieć rolę administrator.
     * Wywołane po profile_update — jeśli użytkownik ma rolę administrator a nie jest w grupie, rola jest cofana.
     *
     * @param int      $user_id       ID użytkownika.
     * @param \WP_User $old_user_data Dane przed aktualizacją.
     */
    public static function enforce_wp_admin_group( int $user_id, $old_user_data ): void {
        if ( ! self::get_administrators_group_id() ) {
            return;
        }
        $user = get_userdata( $user_id );
        if ( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) {
            return;
        }
        if ( self::is_user_in_administrators_group( $user_id ) ) {
            return;
        }
        $previous_role = ! empty( $old_user_data->roles ) && is_array( $old_user_data->roles )
            ? (string) reset( $old_user_data->roles )
            : get_option( 'default_role', 'subscriber' );
        if ( 'administrator' === $previous_role ) {
            $previous_role = 'subscriber';
        }
        $user->set_role( $previous_role );
        set_transient( 'openvote_roles_error', __( 'Administratorem WordPress może zostać tylko użytkownik z sejmiku „Administratorzy”. Rola została cofnięta.', 'openvote' ), 30 );
    }

    /**
     * Egzekwowanie przy rejestracji: jeśli nowy użytkownik dostał rolę administrator, a nie jest w grupie — ustaw rolę domyślną.
     *
     * @param int $user_id ID nowego użytkownika.
     */
    public static function enforce_wp_admin_group_on_register( int $user_id ): void {
        if ( ! self::get_administrators_group_id() ) {
            return;
        }
        $user = get_userdata( $user_id );
        if ( ! $user || ! in_array( 'administrator', (array) $user->roles, true ) ) {
            return;
        }
        if ( self::is_user_in_administrators_group( $user_id ) ) {
            return;
        }
        $user->set_role( get_option( 'default_role', 'subscriber' ) );
    }

    /**
     * Pobierz wszystkich Koordynatorów.
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
     * Pobierz wszystkich Lokalnych Koordynatorów Grup.
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
     * Ile lokalnych koordynatorów jest przypisanych do danej grupy?
     * Jedno zapytanie SQL zamiast ładowania wszystkich edytorów (wydajność przy dużej liczbie użytkowników).
     */
    public static function count_editors_in_group( int $group_id ): int {
        global $wpdb;
        $group_id = absint( $group_id );
        if ( $group_id <= 0 ) {
            return 0;
        }
        // openvote_groups jest zapisane jako JSON np. [1,2,3]. Sprawdzamy zawieranie group_id w tablicy.
        $sql = "SELECT COUNT(*) FROM {$wpdb->usermeta} um1
                INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
                  AND um2.meta_key = %s AND ( um2.meta_value LIKE %s OR um2.meta_value LIKE %s OR um2.meta_value LIKE %s OR um2.meta_value = %s )
                WHERE um1.meta_key = %s AND um1.meta_value = %s";
        $like1 = '[' . $group_id . ',%';
        $like2 = '%,' . $group_id . ',%';
        $like3 = '%,' . $group_id . ']';
        $exact = '[' . $group_id . ']';
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, self::META_GROUPS, $like1, $like2, $like3, $exact, self::META_ROLE, self::ROLE_POLL_EDITOR ) );
    }

    // ─── Nadawanie ról ───────────────────────────────────────────────────────

    /**
     * Nadaj rolę Koordynatora.
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
                    __( 'Limit Koordynatorów (%1$d) osiągnięty. Zajmują: %2$s.', 'openvote' ),
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
     * Nadaj rolę Koordynatora z przypisaniem do grup.
     *
     * @param int[] $group_ids
     * @return true|\WP_Error
     */
    public static function add_poll_editor( int $user_id, array $group_ids ): true|\WP_Error {
        if ( empty( $group_ids ) ) {
            return new \WP_Error( 'no_groups', __( 'Koordynator musi mieć przypisany co najmniej jeden sejmik.', 'openvote' ) );
        }

        update_user_meta( $user_id, self::META_ROLE, self::ROLE_POLL_EDITOR );
        update_user_meta( $user_id, self::META_GROUPS, wp_json_encode( array_values( array_map( 'absint', $group_ids ) ) ) );

        return true;
    }

    // ─── Usuwanie ról ────────────────────────────────────────────────────────

    /**
     * Usuń rolę openvote od użytkownika.
     *
     * @param int $user_id    ID użytkownika tracącego rolę.
     * @param int $remover_id ID użytkownika wykonującego operację.
     * @return true|\WP_Error
     */
    public static function remove_role( int $user_id, int $remover_id ): true|\WP_Error {
        if ( ! self::can_remove( $remover_id, $user_id ) ) {
            return new \WP_Error(
                'cannot_remove',
                __( 'Nie masz uprawnień do usunięcia tej roli.', 'openvote' )
            );
        }

        delete_user_meta( $user_id, self::META_ROLE );
        delete_user_meta( $user_id, self::META_GROUPS );

        return true;
    }

    /**
     * Sprawdź czy remover może usunąć rolę od target.
     * - Admin WP może usunąć każdego.
     * - Koordynator może usunąć Lokalnego Koordynatora Grup i innego Koordynatora.
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

        $target_is_wp_admin = in_array( 'administrator', (array) $target_user->roles, true );
        if ( $target_is_wp_admin ) {
            if ( '' === $target_role ) {
                return false;
            }
        }

        $remover_is_wp_admin    = in_array( 'administrator', (array) $remover->roles, true );
        $remover_openvote_role   = self::get_user_role( $remover_id );
        $remover_is_poll_admin  = ( self::ROLE_POLL_ADMIN === $remover_openvote_role );

        if ( $remover_is_wp_admin ) {
            return true;
        }

        if ( $remover_is_poll_admin ) {
            return in_array( $target_role, [ self::ROLE_POLL_ADMIN, self::ROLE_POLL_EDITOR ], true );
        }

        return false;
    }

    /**
     * Odłącz koordynatora od jednej grupy (usuwa grupę z listy przypisań).
     * Jeśli to była ostatnia grupa, usuwa rolę koordynatora.
     *
     * @param int $user_id    ID koordynatora.
     * @param int $group_id   ID grupy do odłączenia.
     * @param int $remover_id ID użytkownika wykonującego operację.
     * @return true|\WP_Error
     */
    public static function remove_group_from_editor( int $user_id, int $group_id, int $remover_id ): true|\WP_Error {
        if ( ! self::can_remove( $remover_id, $user_id ) ) {
            return new \WP_Error( 'cannot_remove', __( 'Nie masz uprawnień do tej operacji.', 'openvote' ) );
        }
        if ( self::ROLE_POLL_EDITOR !== self::get_user_role( $user_id ) ) {
            return new \WP_Error( 'not_editor', __( 'Ten użytkownik nie jest koordynatorem.', 'openvote' ) );
        }

        $current = self::get_user_groups( $user_id );
        $new_ids = array_values( array_diff( $current, [ $group_id ] ) );

        if ( empty( $new_ids ) ) {
            delete_user_meta( $user_id, self::META_ROLE );
            delete_user_meta( $user_id, self::META_GROUPS );
        } else {
            update_user_meta( $user_id, self::META_GROUPS, wp_json_encode( $new_ids ) );
        }

        return true;
    }

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
                    __( 'Maks. %1$d Administratorów WordPress. Zajmują: %2$s.', 'openvote' ),
                    self::LIMIT_WP_ADMINS,
                    $names
                )
            );
        }

        return true;
    }
}
