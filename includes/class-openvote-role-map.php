<?php
defined( 'ABSPATH' ) || exit;

/**
 * Mapowanie roli → ekrany: które role mogą wyświetlać dane menu i mieć dostęp do ekranów.
 * Opcja: openvote_role_screen_map.
 */
class Openvote_Role_Map {

    const OPTION_KEY = 'openvote_role_screen_map';

    /** Dozwolone slugi ról (wiersze tabeli). */
    const ROLES = [ 'subscriber', 'administrator', 'openvote_coordinator' ];

    /** Dozwolone slugi ekranów (kolumny tabeli). Konfiguracja jako ostatnia. */
    const SCREENS = [ 'openvote', 'openvote-surveys', 'openvote-groups', 'openvote-roles', 'openvote-manual', 'openvote-statistics', 'openvote-communication', 'openvote-settings' ];

    /** Domyślna mapa: tylko Administrator (wszystko) i Koordynator (wszystko oprócz Konfiguracji). Subscriber — brak dostępu. */
    const DEFAULT_MAP = [
        'administrator'       => [
            'openvote'           => 1,
            'openvote-surveys'  => 1,
            'openvote-groups'   => 1,
            'openvote-roles'    => 1,
            'openvote-manual'   => 1,
            'openvote-statistics' => 1,
            'openvote-communication' => 1,
            'openvote-settings' => 1,
        ],
        'subscriber'          => [
            'openvote'           => 0,
            'openvote-surveys'  => 0,
            'openvote-groups'   => 0,
            'openvote-roles'    => 0,
            'openvote-manual'   => 0,
            'openvote-statistics' => 0,
            'openvote-communication' => 0,
            'openvote-settings' => 0,
        ],
        'openvote_coordinator' => [
            'openvote'           => 1,
            'openvote-surveys'  => 1,
            'openvote-groups'   => 1,
            'openvote-roles'    => 1,
            'openvote-manual'   => 1,
            'openvote-statistics' => 1,
            'openvote-communication' => 1,
            'openvote-settings' => 0,
        ],
    ];

    /**
     * Zwraca mapę rola → ekrany z opcji, zmerge'owaną z domyślnymi.
     *
     * @return array<string, array<string, int>>
     */
    public static function get_map(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        $map = [];
        foreach ( self::ROLES as $role ) {
            $map[ $role ] = self::DEFAULT_MAP[ $role ] ?? array_fill_keys( self::SCREENS, 0 );
            if ( isset( $saved[ $role ] ) && is_array( $saved[ $role ] ) ) {
                foreach ( self::SCREENS as $screen ) {
                    if ( array_key_exists( $screen, $saved[ $role ] ) ) {
                        $map[ $role ][ $screen ] = (int) $saved[ $role ][ $screen ];
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Czy użytkownik ma rolę Koordynatora (poll_admin, wyświetlana jako Koordynator).
     */
    public static function user_is_coordinator( int $user_id ): bool {
        return Openvote_Role_Manager::get_user_role( $user_id ) === Openvote_Role_Manager::ROLE_POLL_ADMIN;
    }

    /**
     * Lista „aktywnych” ról użytkownika: role WordPress + openvote_coordinator jeśli jest koordynatorem.
     *
     * @return string[]
     */
    public static function get_user_effective_roles( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user || ! $user->exists() ) {
            return [];
        }
        $roles = array_intersect( (array) $user->roles, self::ROLES );
        $roles = array_values( $roles );
        if ( self::user_is_coordinator( $user_id ) ) {
            $roles[] = 'openvote_coordinator';
        }
        return array_unique( $roles );
    }

    /**
     * Czy użytkownik ma dostęp do danego ekranu (zgodnie z mapą roli).
     */
    public static function user_can_access_screen( int $user_id, string $screen_slug ): bool {
        if ( ! in_array( $screen_slug, self::SCREENS, true ) ) {
            return false;
        }
        $map   = self::get_map();
        $roles = self::get_user_effective_roles( $user_id );
        foreach ( $roles as $role ) {
            if ( isset( $map[ $role ][ $screen_slug ] ) && (int) $map[ $role ][ $screen_slug ] === 1 ) {
                return true;
            }
        }
        return false;
    }
}
