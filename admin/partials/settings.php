<?php
defined( 'ABSPATH' ) || exit;

$current_map    = Evoting_Field_Map::get();
$available_keys = Evoting_Field_Map::available_keys();
$labels         = Evoting_Field_Map::LABELS;

/**
 * Render a <select> dropdown for a logical field.
 *
 * @param string   $logical      Logical field key (e.g. 'first_name').
 * @param string   $current      Currently mapped actual key.
 * @param string[] $core_keys    Core wp_users columns.
 * @param string[] $meta_keys    All usermeta keys.
 */
function evoting_settings_select( string $logical, string $current, array $core_keys, array $meta_keys ): void {
    $name = 'evoting_field_map[' . esc_attr( $logical ) . ']';
    echo '<select name="' . $name . '" id="evoting_field_' . esc_attr( $logical ) . '" class="evoting-settings-select">';

    if ( 'city' === $logical ) {
        echo '<option value="' . esc_attr( Evoting_Field_Map::NO_CITY_KEY ) . '"' . selected( $current, Evoting_Field_Map::NO_CITY_KEY, false ) . '>';
        echo esc_html__( 'Nie używaj miast (wszyscy w grupie Wszyscy)', 'evoting' );
        echo '</option>';
    }

    echo '<optgroup label="' . esc_attr__( 'Pola wbudowane WordPress (wp_users)', 'evoting' ) . '">';
    foreach ( $core_keys as $key ) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $key ),
            selected( $current, $key, false ),
            esc_html( $key )
        );
    }
    echo '</optgroup>';

    echo '<optgroup label="' . esc_attr__( 'Własne pola użytkownika (usermeta)', 'evoting' ) . '">';
    foreach ( $meta_keys as $key ) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $key ),
            selected( $current, $key, false ),
            esc_html( $key )
        );
    }
    echo '</optgroup>';

    echo '</select>';
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Konfiguracja', 'evoting' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Konfiguracja została zapisana.', 'evoting' ); ?></p>
        </div>
    <?php endif; ?>
    <?php if ( isset( $_GET['page_created'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Strona głosowania została utworzona. Możesz ją edytować w Strony lub przejść pod skonfigurowany adres.', 'evoting' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'evoting_save_settings', 'evoting_settings_nonce' ); ?>

        <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'URL strony głosowania', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Adres, pod którym użytkownicy wchodzą, aby zobaczyć i wypełnić głosowania. Domena jest pobierana automatycznie z adresu instalacji WordPress — podaj tylko końcówkę ścieżki (np. glosuj).', 'evoting' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="evoting_vote_page_slug"><?php esc_html_e( 'Ścieżka (slug)', 'evoting' ); ?></label></th>
                <td>
                    <code style="margin-right:4px;"><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
                    <input type="text" name="evoting_vote_page_slug" id="evoting_vote_page_slug" value="<?php echo esc_attr( evoting_get_vote_page_slug() ); ?>" class="regular-text" style="width:140px;" placeholder="glosuj" />
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Pełny adres:', 'evoting' ); ?>
                        <strong><a href="<?php echo esc_url( evoting_get_vote_page_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( evoting_get_vote_page_url() ); ?></a></strong>
                    </p>
                    <?php if ( ! evoting_vote_page_exists() ) : ?>
                        <p style="margin-top:10px;">
                            <button type="submit" name="evoting_create_vote_page" value="1" class="button button-secondary">
                                <?php esc_html_e( 'Dodaj podstronę', 'evoting' ); ?>
                            </button>
                            <span class="description" style="margin-left:8px;"><?php esc_html_e( 'Utworzy stronę pod powyższym adresem z blokiem głosowań.', 'evoting' ); ?></span>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'Ustawienie czasu (strefa głosowań)', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Jeśli serwer ma inną strefę czasową niż Twoja, ustaw przesunięcie. Czas po przesunięciu jest używany do sprawdzania okresu głosowania i wyświetlania liczników.', 'evoting' ); ?>
        </p>
        <?php
        $current_offset = evoting_get_time_offset_hours();
        $server_time    = current_time( 'Y-m-d H:i:s' );
        $voting_time    = evoting_current_time_for_voting( 'Y-m-d H:i:s' );
        ?>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="evoting_time_offset_hours"><?php esc_html_e( 'Przesunięcie czasu', 'evoting' ); ?></label></th>
                <td>
                    <select name="evoting_time_offset_hours" id="evoting_time_offset_hours">
                        <?php for ( $h = -12; $h <= 12; $h++ ) : ?>
                            <option value="<?php echo (int) $h; ?>" <?php selected( $current_offset, $h ); ?>>
                                <?php echo esc_html( $h === 0 ? __( '0 (bez zmiany)', 'evoting' ) : sprintf( __( '%+d h', 'evoting' ), $h ) ); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Czas na serwerze', 'evoting' ); ?></th>
                <td>
                    <code id="evoting-server-time"><?php echo esc_html( $server_time ); ?></code>
                    <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Aktualny czas według ustawień WordPress (strefa z Ustawienia → Ogólne).', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Czas dla głosowań', 'evoting' ); ?></th>
                <td>
                    <strong id="evoting-voting-time"><?php echo esc_html( $voting_time ); ?></strong>
                    <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Czas używany przy sprawdzaniu rozpoczęcia i zakończenia głosowania (serwer + przesunięcie).', 'evoting' ); ?></p>
                </td>
            </tr>
        </table>

        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'Mapowanie pól użytkownika', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e(
                'Powiąż logiczne pola wtyczki z rzeczywistymi kluczami w bazie danych WordPress. '
                . 'Dzięki temu wtyczka będzie działać poprawnie niezależnie od użytej wtyczki rejestracji '
                . 'lub własnego schematu metadanych.',
                'evoting'
            ); ?>
        </p>
        <table class="widefat fixed evoting-settings-table" style="max-width:780px;">
            <thead>
                <tr>
                    <th style="width:220px;"><?php esc_html_e( 'Pole logiczne', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Klucz w bazie danych WordPress', 'evoting' ); ?></th>
                    <th style="width:180px;"><?php esc_html_e( 'Aktualny klucz', 'evoting' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $labels as $logical => $label ) :
                    $current_val = $current_map[ $logical ] ?? Evoting_Field_Map::DEFAULTS[ $logical ];
                    $is_core     = Evoting_Field_Map::is_core_field( $current_val );
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $label ); ?></strong>
                        <?php if ( 'email' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Zazwyczaj user_email (pole wbudowane)', 'evoting' ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php evoting_settings_select(
                            $logical,
                            $current_val,
                            $available_keys['core'],
                            $available_keys['meta']
                        ); ?>
                    </td>
                    <td>
                        <?php if ( 'city' === $logical && $current_val === Evoting_Field_Map::NO_CITY_KEY ) : ?>
                            <code><?php esc_html_e( 'Nie używaj miast', 'evoting' ); ?></code>
                            <br><span class="evoting-badge evoting-badge--meta"><?php esc_html_e( 'grupa Wszyscy', 'evoting' ); ?></span>
                        <?php else : ?>
                            <code><?php echo esc_html( $current_val ); ?></code>
                            <?php if ( $is_core ) : ?>
                                <br><span class="evoting-badge evoting-badge--core">
                                    <?php esc_html_e( 'wbudowane', 'evoting' ); ?>
                                </span>
                            <?php else : ?>
                                <br><span class="evoting-badge evoting-badge--meta">
                                    <?php esc_html_e( 'usermeta', 'evoting' ); ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:16px;">
            <?php submit_button( __( 'Zapisz konfigurację', 'evoting' ), 'primary', 'submit', false ); ?>
        </p>
    </form>

    <hr>
    <h2 style="font-size:14px;"><?php esc_html_e( 'Jak to działa?', 'evoting' ); ?></h2>
    <ul style="list-style:disc;padding-left:20px;max-width:700px;color:#555;font-size:13px;">
        <li><?php esc_html_e( 'Użytkownik jest uprawniony do głosowania tylko jeśli wszystkie 5 pól jest wypełnionych.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Pole "Nazwa miasta" służy do definiowania grup docelowych głosowania.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Pola wbudowane (np. user_email) są odczytywane z tabeli wp_users.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Pozostałe pola są odczytywane z tabeli wp_usermeta.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Po zmianie konfiguracji wszystkie nowe i istniejące głosowania używają nowych kluczy.', 'evoting' ); ?></li>
    </ul>
</div>
