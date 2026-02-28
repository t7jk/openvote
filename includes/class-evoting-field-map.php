<?php
defined( 'ABSPATH' ) || exit;

/**
 * Maps logical field names used by the plugin to actual
 * WordPress user meta keys (or core wp_users columns).
 *
 * Stored as WP option: evoting_field_map
 */
class Evoting_Field_Map {

    const OPTION_KEY   = 'evoting_field_map';
    const NO_CITY_KEY  = '__evoting_no_city__';
    const WSZYSCY_NAME = 'Wszyscy';

    /** Option key for storing which logical fields are required for voting. */
    const REQUIRED_FIELDS_OPTION = 'evoting_required_fields';

    /** Option key for storing which logical fields are required for surveys. */
    const SURVEY_REQUIRED_FIELDS_OPTION = 'evoting_survey_required_fields';

    /** Sentinel value meaning "field not mapped — treat as empty". */
    const NOT_SET_KEY = '__evoting_not_set__';

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
        'phone'      => self::NOT_SET_KEY,
        'pesel'      => self::NOT_SET_KEY,
        'id_card'    => self::NOT_SET_KEY,
        'address'    => self::NOT_SET_KEY,
        'zip_code'   => self::NOT_SET_KEY,
        'town'       => self::NOT_SET_KEY,
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
        'phone'      => 'Numer telefonu',
        'pesel'      => 'Numer PESEL',
        'id_card'    => 'Numer dowodu osobistego',
        'address'    => 'Ulica i numer domu',
        'zip_code'   => 'Kod pocztowy',
        'town'       => 'Miejscowość',
    ];

    /**
     * Klucze pól uznawane za wrażliwe na stronie publicznej zgłoszeń.
     * Na /zgloszenia/ wartości tych pól nie są wyświetlane (tylko "—").
     */
    const SENSITIVE_FOR_PUBLIC = [
        'email',
        'city',
        'phone',
        'pesel',
        'id_card',
        'address',
        'zip_code',
        'town',
    ];

    /**
     * Czy dane pole (logical key) jest wrażliwe i nie powinno być pokazywane publicznie.
     */
    public static function is_sensitive_for_public( string $logical ): bool {
        return in_array( $logical, self::SENSITIVE_FOR_PUBLIC, true );
    }

    /**
     * Fields required by default (always required, regardless of admin config).
     * Admin can add more via checkboxes; these cannot be unchecked.
     */
    const ALWAYS_REQUIRED = [ 'first_name', 'last_name', 'nickname', 'email' ];

    /**
     * Fields always required for surveys (fixed, cannot be unchecked).
     * Miasto (city) jest opcjonalne — admin może je zaznaczyć/odznaczyć w Konfiguracji.
     */
    const SURVEY_ALWAYS_REQUIRED = [ 'first_name', 'last_name', 'nickname', 'email' ];

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
     * Whether city field is disabled (option "Nie używaj miast").
     * Wszyscy użytkownicy są w jednej grupie "Wszyscy".
     */
    public static function is_city_disabled(): bool {
        return self::get_field( 'city' ) === self::NO_CITY_KEY;
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

        if ( 'city' === $logical && $actual === self::NO_CITY_KEY ) {
            return self::WSZYSCY_NAME;
        }

        if ( $actual === self::NOT_SET_KEY ) {
            return '';
        }

        if ( self::is_core_field( $actual ) ) {
            return (string) ( $user->{$actual} ?? '' );
        }

        return (string) get_user_meta( $user->ID, $actual, true );
    }

    /**
     * Returns logical field keys that are marked as required for voting.
     * Returns array: [ 'first_name' => 'Imię', 'email' => 'E-mail', ... ]
     */
    public static function get_required_fields(): array {
        $saved    = (array) get_option( self::REQUIRED_FIELDS_OPTION, [] );
        $required = [];
        foreach ( array_keys( self::DEFAULTS ) as $logical ) {
            // city — skip if disabled
            if ( 'city' === $logical && self::is_city_disabled() ) {
                continue;
            }
            // Always-required fields are included regardless of saved option
            if ( in_array( $logical, self::ALWAYS_REQUIRED, true ) || in_array( $logical, $saved, true ) ) {
                $required[ $logical ] = self::LABELS[ $logical ];
            }
        }
        return $required;
    }

    /**
     * Whether a specific logical field is marked as required.
     */
    public static function is_required( string $logical ): bool {
        if ( in_array( $logical, self::ALWAYS_REQUIRED, true ) ) {
            return true;
        }
        $saved = (array) get_option( self::REQUIRED_FIELDS_OPTION, [] );
        return in_array( $logical, $saved, true );
    }

    /**
     * Persist the required-fields selection from POST data.
     * $checked is an array of logical keys that were checked.
     */
    public static function save_required_fields( array $checked ): void {
        $clean = [];
        foreach ( array_keys( self::DEFAULTS ) as $logical ) {
            // Always-required fields don't need to be stored
            if ( in_array( $logical, self::ALWAYS_REQUIRED, true ) ) {
                continue;
            }
            if ( in_array( $logical, $checked, true ) ) {
                $clean[] = $logical;
            }
        }
        update_option( self::REQUIRED_FIELDS_OPTION, $clean, false );
    }

    // ── Survey required fields ────────────────────────────────────────────────

    /**
     * Returns required fields for surveys as [ 'logical' => 'Label' ].
     * Always-required: first_name, last_name, nickname, email, city.
     * Optional (default: phone is checked): saved in evoting_survey_required_fields.
     */
    public static function get_survey_required_fields(): array {
        $saved    = (array) get_option( self::SURVEY_REQUIRED_FIELDS_OPTION, [ 'phone', 'city' ] );
        $required = [];
        foreach ( array_keys( self::DEFAULTS ) as $logical ) {
            if ( in_array( $logical, self::SURVEY_ALWAYS_REQUIRED, true )
                 || in_array( $logical, $saved, true ) ) {
                $required[ $logical ] = self::LABELS[ $logical ];
            }
        }
        return $required;
    }

    /**
     * Whether a specific logical field is required for surveys.
     */
    public static function is_survey_required( string $logical ): bool {
        if ( in_array( $logical, self::SURVEY_ALWAYS_REQUIRED, true ) ) {
            return true;
        }
        $saved = (array) get_option( self::SURVEY_REQUIRED_FIELDS_OPTION, [ 'phone', 'city' ] );
        return in_array( $logical, $saved, true );
    }

    /**
     * Persist survey required fields from POST data.
     */
    public static function save_survey_required_fields( array $checked ): void {
        $clean = [];
        foreach ( array_keys( self::DEFAULTS ) as $logical ) {
            if ( in_array( $logical, self::SURVEY_ALWAYS_REQUIRED, true ) ) {
                continue;
            }
            if ( in_array( $logical, $checked, true ) ) {
                $clean[] = $logical;
            }
        }
        update_option( self::SURVEY_REQUIRED_FIELDS_OPTION, $clean, false );
    }

    // ── Missing field helpers ─────────────────────────────────────────────────

    /**
     * Return logical fields that are required but missing for a given user.
     * Used to display the inline profile completion form on public pages.
     *
     * @return array<string, string>  [ 'logical_key' => 'Human label', ... ]
     */
    public static function get_missing_fields_for_user( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        $required = self::get_required_fields();
        $missing  = [];

        foreach ( $required as $logical => $label ) {
            $value = self::get_user_value( $user, $logical );
            if ( '' === trim( $value ) ) {
                $missing[ $logical ] = $label;
            }
        }

        return $missing;
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
