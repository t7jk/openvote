<?php
defined( 'ABSPATH' ) || exit;

/**
 * Maps logical field names used by the plugin to actual
 * WordPress user meta keys (or core wp_users columns).
 *
 * Stored as WP option: evoting_field_map
 */
class Evoting_Field_Map {

    const OPTION_KEY = 'evoting_field_map';

    /**
     * Logical field identifiers and their factory defaults.
     * Keys are internal identifiers used throughout the codebase.
     */
    const DEFAULTS = [
        'first_name' => 'first_name',
        'last_name'  => 'last_name',
        'nickname'   => 'nickname',
        'email'      => 'user_email',
        'city'       => 'user_registration_miejsce_spotkania',
    ];

    /**
     * Human-readable labels for the admin settings form.
     */
    const LABELS = [
        'first_name' => 'Imię',
        'last_name'  => 'Nazwisko',
        'nickname'   => 'Nickname',
        'email'      => 'E-mail',
        'city'       => 'Nazwa miasta / miejsce spotkania',
    ];

    /**
     * WordPress core wp_users columns (not usermeta).
     * Values from these fields must be read via $user->{field},
     * not get_user_meta().
     */
    const CORE_USER_FIELDS = [
        'user_email',
        'user_login',
        'user_nicename',
        'display_name',
        'user_url',
        'user_registered',
    ];

    /**
     * Return the full mapping: logical_key => actual_key.
     */
    public static function get(): array {
        return wp_parse_args(
            (array) get_option( self::OPTION_KEY, [] ),
            self::DEFAULTS
        );
    }

    /**
     * Return the actual DB key for a given logical field.
     */
    public static function get_field( string $logical ): string {
        $map = self::get();
        return $map[ $logical ] ?? self::DEFAULTS[ $logical ] ?? $logical;
    }

    /**
     * Return whether a given actual key is a core wp_users column.
     */
    public static function is_core_field( string $actual_key ): bool {
        return in_array( $actual_key, self::CORE_USER_FIELDS, true );
    }

    /**
     * Read a user field value regardless of whether it is a core
     * column or a usermeta entry.
     *
     * @param WP_User $user
     * @param string  $logical   One of the DEFAULTS keys.
     */
    public static function get_user_value( \WP_User $user, string $logical ): string {
        $actual = self::get_field( $logical );

        if ( self::is_core_field( $actual ) ) {
            return (string) ( $user->{$actual} ?? '' );
        }

        return (string) get_user_meta( $user->ID, $actual, true );
    }

    /**
     * Validate and persist a new field map from POST data.
     */
    public static function save( array $raw ): void {
        $clean = [];
        foreach ( array_keys( self::DEFAULTS ) as $key ) {
            $val = sanitize_text_field( $raw[ $key ] ?? '' );
            if ( '' !== $val ) {
                $clean[ $key ] = $val;
            }
        }
        update_option( self::OPTION_KEY, $clean, false );
    }

    /**
     * Return all available field keys for the dropdown:
     * core wp_users fields first, then all distinct usermeta keys.
     *
     * @return array{ core: string[], meta: string[] }
     */
    public static function available_keys(): array {
        global $wpdb;

        $meta_keys = (array) $wpdb->get_col(
            "SELECT DISTINCT meta_key
             FROM {$wpdb->usermeta}
             ORDER BY meta_key ASC"
        );

        return [
            'core' => self::CORE_USER_FIELDS,
            'meta' => $meta_keys,
        ];
    }
}
