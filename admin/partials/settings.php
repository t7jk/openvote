<?php
defined( 'ABSPATH' ) || exit;

$current_map    = Openvote_Field_Map::get();
$available_keys = Openvote_Field_Map::available_keys();
$labels         = Openvote_Field_Map::LABELS;
asort( $labels, SORT_LOCALE_STRING ); // Tabela mapowania: kolejność wg tytułu pola (alfabetycznie).

/**
 * Render a <select> dropdown for a logical field.
 *
 * @param string   $logical      Logical field key (e.g. 'first_name').
 * @param string   $current      Currently mapped actual key.
 * @param string[] $core_keys    Core wp_users columns.
 * @param string[] $meta_keys    All usermeta keys.
 */
function openvote_settings_select( string $logical, string $current, array $core_keys, array $meta_keys ): void {
    $name = 'openvote_field_map[' . esc_attr( $logical ) . ']';
    echo '<select name="' . $name . '" id="openvote_field_' . esc_attr( $logical ) . '" class="openvote-settings-select">';

    // Optional fields: first option is "not assigned"
    $optional_fields = [ 'phone', 'pesel', 'id_card', 'address', 'zip_code', 'town' ];
    if ( in_array( $logical, $optional_fields, true ) ) {
        echo '<option value="' . esc_attr( Openvote_Field_Map::NOT_SET_KEY ) . '"' . selected( $current, Openvote_Field_Map::NOT_SET_KEY, false ) . '>';
        echo esc_html__( '— nie określone —', 'openvote' );
        echo '</option>';
    }

    if ( 'city' === $logical ) {
        echo '<option value="' . esc_attr( Openvote_Field_Map::NO_CITY_KEY ) . '"' . selected( $current, Openvote_Field_Map::NO_CITY_KEY, false ) . '>';
        echo esc_html__( 'Nie używaj miast (wszyscy w grupie Wszyscy)', 'openvote' );
        echo '</option>';
    }

    echo '<optgroup label="' . esc_attr__( 'Pola wbudowane WordPress (wp_users)', 'openvote' ) . '">';
    foreach ( $core_keys as $key ) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $key ),
            selected( $current, $key, false ),
            esc_html( $key )
        );
    }
    echo '</optgroup>';

    echo '<optgroup label="' . esc_attr__( 'Własne pola użytkownika (usermeta)', 'openvote' ) . '">';
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
    <h1><?php esc_html_e( 'Konfiguracja OpenVote', 'openvote' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Konfiguracja została zapisana.', 'openvote' ); ?></p>
        </div>
    <?php endif; ?>
    <?php
    $openvote_settings_error = get_transient( 'openvote_settings_error' );
    if ( $openvote_settings_error ) {
        delete_transient( 'openvote_settings_error' );
        ?>
        <div class="notice notice-warning is-dismissible"><p><?php echo esc_html( $openvote_settings_error ); ?></p></div>
    <?php } ?>
    <?php if ( isset( $_GET['page_created'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Strona głosowania została utworzona. Możesz ją edytować w Strony lub przejść pod skonfigurowany adres.', 'openvote' ); ?></p>
            <p><?php esc_html_e( 'Jeśli po wejściu na adres strony widzisz „Not Found”: wejdź w Ustawienia → Bezpośrednie odnośniki, wybierz dowolną strukturę inną niż „Zwykły” i kliknij „Zapisz zmiany”.', 'openvote' ); ?></p>
        </div>
    <?php endif; ?>
    <?php if ( isset( $_GET['page_updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Strona głosowania została zaktualizowana — blok zakładek jest aktywny.', 'openvote' ); ?></p>
        </div>
    <?php endif; ?>
    <?php if ( openvote_vote_page_exists() && ! openvote_vote_page_has_tabs_block() ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Strona głosowania wymaga aktualizacji.', 'openvote' ); ?></strong>
                <?php esc_html_e( 'Strona istnieje, ale nie zawiera bloku zakładek (Trwające / Zakończone głosowania).', 'openvote' ); ?>
            </p>
            <form method="post" action="" style="margin-bottom:8px;">
                <?php wp_nonce_field( 'openvote_save_settings', 'openvote_settings_nonce' ); ?>
                <input type="hidden" name="openvote_update_vote_page" value="1" />
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Zaktualizuj stronę głosowania', 'openvote' ); ?>
                </button>
                <span style="margin-left:10px;color:#555;font-size:12px;">
                    <?php esc_html_e( 'Zastąpi treść strony blokiem zakładek. Inne bloki które dodałeś/aś zostaną usunięte.', 'openvote' ); ?>
                </span>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=openvote-settings' ) ); ?>" id="openvote-settings-form">
        <?php wp_nonce_field( 'openvote_save_settings', 'openvote_settings_nonce' ); ?>
        <?php wp_nonce_field( 'openvote_create_page', 'openvote_create_page_nonce' ); ?>

        <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Nazwa systemu', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Skrót nazwy wyświetlany w menu panelu. Pełna nazwa i logo pobierane są z ustawień WordPress (Ustawienia → Ogólne).', 'openvote' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="openvote_brand_short_name"><?php esc_html_e( 'Skrót nazwy', 'openvote' ); ?></label></th>
                <td>
                    <input type="text" name="openvote_brand_short_name" id="openvote_brand_short_name" value="<?php echo esc_attr( openvote_get_brand_short_name() ); ?>" class="regular-text" style="width:120px;" maxlength="12" placeholder="OpenVote" />
                    <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Do 12 znaków. Domyślnie: OpenVote.', 'openvote' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="openvote-settings-save-wrap">
            <?php submit_button( __( 'Zapisz konfigurację', 'openvote' ), 'primary', 'submit', false ); ?>
        </p>

        <div class="openvote-settings-email-section">
        <?php
        global $wpdb;
        $email_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email != '' AND user_email IS NOT NULL" );
        $mail_method    = openvote_get_mail_method();
        $smtp           = openvote_get_smtp_config();
        $sg_api_key     = openvote_get_sendgrid_api_key();
        $brevo_api_key  = openvote_get_brevo_api_key();
        $freshmail_key  = openvote_get_freshmail_api_key();
        $freshmail_sec  = openvote_get_freshmail_api_secret();
        $getresponse_key = openvote_get_getresponse_api_key();
        $getresponse_ff  = openvote_get_getresponse_from_field_id();
        $batch_brevo_size  = (int) get_option( 'openvote_batch_brevo_free_size', 0 );
        $batch_brevo_delay = (int) get_option( 'openvote_batch_brevo_free_delay', 0 );
        $batch_wp_size     = (int) get_option( 'openvote_batch_wp_size', 0 );
        $batch_wp_delay    = (int) get_option( 'openvote_batch_wp_delay', 0 );
        $batch_smtp_size   = (int) get_option( 'openvote_batch_smtp_size', 0 );
        $batch_smtp_delay  = (int) get_option( 'openvote_batch_smtp_delay', 0 );
        $batch_brevo_per_day  = (int) get_option( 'openvote_batch_brevo_free_per_day', 0 );
        $batch_wp_per_day     = (int) get_option( 'openvote_batch_wp_per_day', 0 );
        $batch_smtp_per_day   = (int) get_option( 'openvote_batch_smtp_per_day', 0 );
        $batch_brevo_per_15   = (int) get_option( 'openvote_batch_brevo_free_per_15min', 0 );
        $batch_brevo_per_hour = (int) get_option( 'openvote_batch_brevo_free_per_hour', 0 );
        $batch_wp_per_15      = (int) get_option( 'openvote_batch_wp_per_15min', 0 );
        $batch_wp_per_hour    = (int) get_option( 'openvote_batch_wp_per_hour', 0 );
        $batch_smtp_per_15    = (int) get_option( 'openvote_batch_smtp_per_15min', 0 );
        $batch_smtp_per_hour  = (int) get_option( 'openvote_batch_smtp_per_hour', 0 );
        $admin_email    = wp_get_current_user()->user_email;
        $default_invitation_test_to = 'email@poczta.pl';
        $wp_mail_disabled = $email_count > 250;
        ?>
        <table class="form-table openvote-settings-email-section__table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Liczba adresów e-mail w systemie', 'openvote' ); ?></th>
                <td>
                    <p class="description" style="margin:0;">
                        <?php printf( esc_html__( 'Obecna liczba adresów e-mail w systemie: %s', 'openvote' ), '<strong>' . (int) $email_count . '</strong>' ); ?>
                        <?php if ( $wp_mail_disabled ) : ?>
                            <br><span style="color:#b32d2e;"><?php esc_html_e( 'WordPress (PHP-mail) jest niedostępne przy ponad 250 adresach.', 'openvote' ); ?></span>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="openvote_from_email"><?php esc_html_e( 'E-mail nadawcy', 'openvote' ); ?></label></th>
                <td>
                    <input type="email" name="openvote_from_email" id="openvote_from_email"
                           value="<?php echo esc_attr( openvote_get_from_email() ); ?>"
                           class="regular-text" style="max-width:360px;"
                           placeholder="<?php echo esc_attr( 'noreply@' . ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'example.com' ) ); ?>" />
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Adres e-mail, z którego wysyłane są zaproszenia i powiadomienia. Np. noreply@twojadomena.pl', 'openvote' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Metoda wysyłki', 'openvote' ); ?></th>
                <td>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="wordpress"
                               id="openvote_mail_wp"
                               <?php disabled( $wp_mail_disabled ); ?> <?php checked( $mail_method, 'wordpress' ); ?> />
                        <strong><?php esc_html_e( 'WordPress (PHP-mail)', 'openvote' ); ?></strong>
                    </label>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="smtp"
                               id="openvote_mail_smtp"
                               <?php checked( $mail_method, 'smtp' ); ?> />
                        <strong><?php esc_html_e( 'SMTP zewnętrzny', 'openvote' ); ?></strong>
                    </label>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="sendgrid"
                               id="openvote_mail_sendgrid"
                               <?php checked( $mail_method, 'sendgrid' ); ?> />
                        <strong><?php esc_html_e( 'SendGrid API', 'openvote' ); ?></strong>
                    </label>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="brevo"
                               id="openvote_mail_brevo"
                               <?php checked( $mail_method, 'brevo' ); ?> />
                        <strong><?php esc_html_e( 'BREVO (free)', 'openvote' ); ?></strong>
                    </label>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="brevo_paid"
                               id="openvote_mail_brevo_paid"
                               <?php checked( $mail_method, 'brevo_paid' ); ?> />
                        <strong><?php esc_html_e( 'BREVO (płatne)', 'openvote' ); ?></strong>
                    </label>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="freshmail"
                               id="openvote_mail_freshmail"
                               <?php checked( $mail_method, 'freshmail' ); ?> />
                        <strong><?php esc_html_e( 'Freshmail API', 'openvote' ); ?></strong>
                    </label>
                    <label style="display:block;">
                        <input type="radio" name="openvote_mail_method" value="getresponse"
                               id="openvote_mail_getresponse"
                               <?php checked( $mail_method, 'getresponse' ); ?> />
                        <strong><?php esc_html_e( 'GetResponse API', 'openvote' ); ?></strong>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="openvote_test_invitation_to"><?php esc_html_e( 'Wyślij test e-mail', 'openvote' ); ?></label></th>
                <td>
                    <input type="email" id="openvote_test_invitation_to" class="regular-text" style="max-width:320px;"
                           value="<?php echo esc_attr( $default_invitation_test_to ); ?>"
                           placeholder="<?php echo esc_attr( $default_invitation_test_to ); ?>" />
                    <button type="button" class="button button-primary" id="openvote-test-invitation-btn" style="margin-left:12px;">
                        <?php esc_html_e( 'Wyślij e-mail testowy.', 'openvote' ); ?>
                    </button>
                    <span id="openvote-test-invitation-result" style="margin-left:12px;font-weight:500;"></span>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Wysyła na podany adres z treścią zaproszenia (HTML). Używana jest metoda zaznaczona powyżej.', 'openvote' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php /* ── SMTP (zawsze widoczny) ── */ ?>
        <div id="openvote-smtp-fields" class="openvote-settings-email-section__smtp" style="margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja serwera SMTP', 'openvote' ); ?></h3>
            <table class="form-table" role="presentation" style="max-width:780px;">
                <tr>
                    <th scope="row"><label for="openvote_smtp_host"><?php esc_html_e( 'Serwer SMTP (host)', 'openvote' ); ?></label></th>
                    <td>
                        <input type="text" name="openvote_smtp_host" id="openvote_smtp_host"
                               value="<?php echo esc_attr( $smtp['host'] ); ?>"
                               class="regular-text" placeholder="smtp.gmail.com" />
                        <p class="description"><?php esc_html_e( 'Np. smtp.gmail.com, smtp.office365.com', 'openvote' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openvote_smtp_port"><?php esc_html_e( 'Port', 'openvote' ); ?></label></th>
                    <td>
                        <input type="number" name="openvote_smtp_port" id="openvote_smtp_port"
                               value="<?php echo esc_attr( $smtp['port'] ); ?>"
                               class="small-text" min="1" max="65535" />
                        <p class="description"><?php esc_html_e( 'Najczęstsze: 587 (TLS/STARTTLS), 465 (SSL), 25 (bez szyfrowania)', 'openvote' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Szyfrowanie', 'openvote' ); ?></th>
                    <td>
                        <select name="openvote_smtp_encryption" id="openvote_smtp_encryption">
                            <option value="tls"  <?php selected( $smtp['encryption'], 'tls' ); ?>><?php esc_html_e( 'TLS / STARTTLS (zalecane, port 587)', 'openvote' ); ?></option>
                            <option value="ssl"  <?php selected( $smtp['encryption'], 'ssl' ); ?>><?php esc_html_e( 'SSL (port 465)', 'openvote' ); ?></option>
                            <option value="none" <?php selected( $smtp['encryption'], 'none' ); ?>><?php esc_html_e( 'Brak szyfrowania (niezalecane)', 'openvote' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openvote_smtp_username"><?php esc_html_e( 'Użytkownik (login)', 'openvote' ); ?></label></th>
                    <td>
                        <input type="text" name="openvote_smtp_username" id="openvote_smtp_username"
                               value="<?php echo esc_attr( $smtp['username'] ); ?>"
                               class="regular-text" autocomplete="username"
                               placeholder="<?php esc_attr_e( 'np. noreply@twojadomena.pl', 'openvote' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openvote_smtp_password"><?php esc_html_e( 'Hasło', 'openvote' ); ?></label></th>
                    <td>
                        <input type="password" name="openvote_smtp_password" id="openvote_smtp_password"
                               value="<?php echo esc_attr( $smtp['password'] ); ?>"
                               class="regular-text" autocomplete="current-password" />
                        <p class="description"><?php esc_html_e( 'Użyj dedykowanego hasła aplikacji (np. dla Gmail/Outlook).', 'openvote' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── SendGrid (zawsze widoczny) ── */ ?>
        <div id="openvote-sendgrid-fields" class="openvote-settings-email-section__sendgrid" style="margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja SendGrid API', 'openvote' ); ?></h3>
            <p class="description" style="margin-bottom:12px;max-width:780px;">
                <?php
                printf(
                    /* translators: 1: opening <a> tag with SendGrid login URL, 2: closing </a> */
                    esc_html__( 'Zaloguj się do SendGrid: %1$sapp.sendgrid.com%2$s. Aby wygenerować klucz API: w panelu wybierz Settings → API Keys → Create API Key; nadaj nazwę, wybierz uprawnienia „Full Access” lub „Restricted Access” z włączonym „Mail Send”. Klucz (zaczyna się od SG.) jest wyświetlany tylko raz — skopiuj go i wklej poniżej.', 'openvote' ),
                    '<a href="' . esc_url( 'https://app.sendgrid.com' ) . '" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
            <table class="form-table" role="presentation" style="max-width:780px;">
                <tr>
                    <th scope="row"><label for="openvote_sendgrid_api_key"><?php esc_html_e( 'Klucz API SendGrid', 'openvote' ); ?></label></th>
                    <td>
                        <input type="password" name="openvote_sendgrid_api_key" id="openvote_sendgrid_api_key"
                               value="<?php echo esc_attr( $sg_api_key ); ?>"
                               class="regular-text" placeholder="SG.xxxxxxxxxxxxxxxx" autocomplete="off" />
                        <p class="description">
                            <?php esc_html_e( 'Utwórz klucz w panelu SendGrid → Settings → API Keys → Full Access lub Mail Send.', 'openvote' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── BREVO (zawsze widoczny) ── */ ?>
        <div id="openvote-brevo-fields" class="openvote-settings-email-section__brevo" style="margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja BREVO API', 'openvote' ); ?></h3>
            <p class="description" style="margin-bottom:12px;max-width:780px;">
                <?php
                printf(
                    /* translators: 1: opening <a> tag, 2: closing </a> */
                    esc_html__( 'Brevo (dawniej Sendinblue): %1$sbrevo.com%2$s. Klucz API: Logowanie → SMTP & API → API Keys → Generate a new API key. Wspólny dla BREVO (free) i BREVO (płatne).', 'openvote' ),
                    '<a href="' . esc_url( 'https://www.brevo.com' ) . '" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
            <table class="form-table" role="presentation" style="max-width:780px;">
                <tr>
                    <th scope="row"><label for="openvote_brevo_api_key"><?php esc_html_e( 'Klucz API BREVO', 'openvote' ); ?></label></th>
                    <td>
                        <input type="password" name="openvote_brevo_api_key" id="openvote_brevo_api_key"
                               value="<?php echo esc_attr( $brevo_api_key ); ?>"
                               class="regular-text" placeholder="xkeysib-..." autocomplete="off" />
                        <p class="description">
                            <?php esc_html_e( 'Utwórz klucz w panelu Brevo → SMTP & API → API Keys.', 'openvote' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── Freshmail (zawsze widoczny) ── */ ?>
        <div id="openvote-freshmail-fields" class="openvote-settings-email-section__freshmail" style="margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja Freshmail API', 'openvote' ); ?></h3>
            <p class="description" style="margin-bottom:12px;max-width:780px;">
                <?php
                printf(
                    /* translators: 1: opening <a> tag, 2: closing </a> */
                    esc_html__( 'Freshmail: %1$sfreshmail.com%2$s. Klucz API i sekret: Subskrypcja → Zaawansowane → API. Używane do wysyłki e-maili transakcyjnych.', 'openvote' ),
                    '<a href="' . esc_url( 'https://www.freshmail.com' ) . '" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
            <table class="form-table" role="presentation" style="max-width:780px;">
                <tr>
                    <th scope="row"><label for="openvote_freshmail_api_key"><?php esc_html_e( 'Klucz API Freshmail', 'openvote' ); ?></label></th>
                    <td>
                        <input type="password" name="openvote_freshmail_api_key" id="openvote_freshmail_api_key"
                               value="<?php echo esc_attr( $freshmail_key ); ?>"
                               class="regular-text" autocomplete="off" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openvote_freshmail_api_secret"><?php esc_html_e( 'Sekret API Freshmail', 'openvote' ); ?></label></th>
                    <td>
                        <input type="password" name="openvote_freshmail_api_secret" id="openvote_freshmail_api_secret"
                               value="<?php echo esc_attr( $freshmail_sec ); ?>"
                               class="regular-text" autocomplete="off" />
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── GetResponse (zawsze widoczny) ── */ ?>
        <div id="openvote-getresponse-fields" class="openvote-settings-email-section__getresponse" style="margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja GetResponse API', 'openvote' ); ?></h3>
            <p class="description" style="margin-bottom:12px;max-width:780px;">
                <?php
                printf(
                    /* translators: 1: opening <a> tag, 2: closing </a> */
                    esc_html__( 'GetResponse: %1$sgetresponse.com%2$s. E-maile transakcyjne wymagają dodatku Transactional (np. GetResponse MAX). Klucz API: Integracje → API. From Field ID: pole nadawcy z panelu lub GET /from-fields.', 'openvote' ),
                    '<a href="' . esc_url( 'https://www.getresponse.com' ) . '" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
            <table class="form-table" role="presentation" style="max-width:780px;">
                <tr>
                    <th scope="row"><label for="openvote_getresponse_api_key"><?php esc_html_e( 'Klucz API GetResponse', 'openvote' ); ?></label></th>
                    <td>
                        <input type="password" name="openvote_getresponse_api_key" id="openvote_getresponse_api_key"
                               value="<?php echo esc_attr( $getresponse_key ); ?>"
                               class="regular-text" autocomplete="off" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openvote_getresponse_from_field_id"><?php esc_html_e( 'From Field ID', 'openvote' ); ?></label></th>
                    <td>
                        <input type="text" name="openvote_getresponse_from_field_id" id="openvote_getresponse_from_field_id"
                               value="<?php echo esc_attr( $getresponse_ff ); ?>"
                               class="regular-text" placeholder="np. ufIK" />
                        <p class="description"><?php esc_html_e( 'ID pola nadawcy (z API from-fields lub panelu GetResponse).', 'openvote' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <h3 style="margin:20px 0 8px;padding-left:2px;"><?php esc_html_e( 'Warunki wysyłki e-maili', 'openvote' ); ?></h3>
        <p class="description" style="margin-bottom:10px;max-width:900px;">
            <?php esc_html_e( 'Poniższa tabela opisuje ograniczenia i zalecenia dla każdej metody wysyłki w systemie Open Vote.', 'openvote' ); ?>
        </p>
        <table class="widefat striped" style="max-width:900px;" role="presentation">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Metoda', 'openvote' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Dostępność / limit adresów', 'openvote' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Maks. rozmiar partii', 'openvote' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Min. pauza między partiami', 'openvote' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Maks. na 15 min', 'openvote' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Maks. na godzinę', 'openvote' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Maks. ilość na dobę', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7" style="background:#f0f0f1;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Bezpłatne usługi:', 'openvote' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'BREVO (free)', 'openvote' ); ?></strong></td>
                    <td><?php esc_html_e( 'Zawsze dostępne. Jedyny limit Brevo: 300 e-maili na 24 h.', 'openvote' ); ?></td>
                    <td>
                        <label for="openvote_batch_brevo_free_size" class="screen-reader-text"><?php esc_html_e( 'Maks. rozmiar partii BREVO', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_brevo_free_size" id="openvote_batch_brevo_free_size"
                               value="<?php echo esc_attr( $batch_brevo_size > 0 ? $batch_brevo_size : '' ); ?>"
                               class="small-text" min="1" max="100" placeholder="100" />
                    </td>
                    <td>
                        <label for="openvote_batch_brevo_free_delay" class="screen-reader-text"><?php esc_html_e( 'Min. pauza BREVO (s)', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_brevo_free_delay" id="openvote_batch_brevo_free_delay"
                               value="<?php echo esc_attr( $batch_brevo_delay > 0 ? $batch_brevo_delay : '' ); ?>"
                               class="small-text" min="1" max="86400" placeholder="1200" /> s
                    </td>
                    <td>
                        <label for="openvote_batch_brevo_free_per_15min" class="screen-reader-text"><?php esc_html_e( 'Maks. na 15 min BREVO', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_brevo_free_per_15min" id="openvote_batch_brevo_free_per_15min"
                               value="<?php echo esc_attr( $batch_brevo_per_15 > 0 ? $batch_brevo_per_15 : '' ); ?>"
                               class="small-text" min="0" max="1000" placeholder="100" />
                    </td>
                    <td>
                        <label for="openvote_batch_brevo_free_per_hour" class="screen-reader-text"><?php esc_html_e( 'Maks. na godzinę BREVO', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_brevo_free_per_hour" id="openvote_batch_brevo_free_per_hour"
                               value="<?php echo esc_attr( $batch_brevo_per_hour > 0 ? $batch_brevo_per_hour : '' ); ?>"
                               class="small-text" min="0" max="10000" placeholder="100" />
                    </td>
                    <td>
                        <label for="openvote_batch_brevo_free_per_day" class="screen-reader-text"><?php esc_html_e( 'Maks. ilość na dobę BREVO', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_brevo_free_per_day" id="openvote_batch_brevo_free_per_day"
                               value="<?php echo esc_attr( $batch_brevo_per_day > 0 ? $batch_brevo_per_day : '' ); ?>"
                               class="small-text" min="1" max="10000" placeholder="300" />
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'WordPress (PHP-mail)', 'openvote' ); ?></strong></td>
                    <td><?php esc_html_e( 'Dostępne tylko przy nie więcej niż 250 adresach e-mail w systemie.', 'openvote' ); ?></td>
                    <td>
                        <label for="openvote_batch_wp_size" class="screen-reader-text"><?php esc_html_e( 'Maks. rozmiar partii WordPress', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_wp_size" id="openvote_batch_wp_size"
                               value="<?php echo esc_attr( $batch_wp_size > 0 ? $batch_wp_size : '' ); ?>"
                               class="small-text" min="1" max="80" placeholder="80" />
                    </td>
                    <td>
                        <label for="openvote_batch_wp_delay" class="screen-reader-text"><?php esc_html_e( 'Min. pauza WordPress (s)', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_wp_delay" id="openvote_batch_wp_delay"
                               value="<?php echo esc_attr( $batch_wp_delay > 0 ? $batch_wp_delay : '' ); ?>"
                               class="small-text" min="1" max="86400" placeholder="900" /> s
                    </td>
                    <td>
                        <label for="openvote_batch_wp_per_15min" class="screen-reader-text"><?php esc_html_e( 'Maks. na 15 min WordPress', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_wp_per_15min" id="openvote_batch_wp_per_15min"
                               value="<?php echo esc_attr( $batch_wp_per_15 > 0 ? $batch_wp_per_15 : '' ); ?>"
                               class="small-text" min="0" max="1000" placeholder="80" />
                    </td>
                    <td>
                        <label for="openvote_batch_wp_per_hour" class="screen-reader-text"><?php esc_html_e( 'Maks. na godzinę WordPress', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_wp_per_hour" id="openvote_batch_wp_per_hour"
                               value="<?php echo esc_attr( $batch_wp_per_hour > 0 ? $batch_wp_per_hour : '' ); ?>"
                               class="small-text" min="0" max="10000" placeholder="320" />
                    </td>
                    <td>
                        <label for="openvote_batch_wp_per_day" class="screen-reader-text"><?php esc_html_e( 'Maks. ilość na dobę WordPress', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_wp_per_day" id="openvote_batch_wp_per_day"
                               value="<?php echo esc_attr( $batch_wp_per_day > 0 ? $batch_wp_per_day : '' ); ?>"
                               class="small-text" min="1" max="100000" placeholder="7680" />
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'SMTP zewnętrzny', 'openvote' ); ?></strong></td>
                    <td><?php esc_html_e( 'Zawsze dostępne (wymaga konfiguracji serwera).', 'openvote' ); ?></td>
                    <td>
                        <label for="openvote_batch_smtp_size" class="screen-reader-text"><?php esc_html_e( 'Maks. rozmiar partii SMTP', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_smtp_size" id="openvote_batch_smtp_size"
                               value="<?php echo esc_attr( $batch_smtp_size > 0 ? $batch_smtp_size : '' ); ?>"
                               class="small-text" min="1" max="100" placeholder="100" />
                    </td>
                    <td>
                        <label for="openvote_batch_smtp_delay" class="screen-reader-text"><?php esc_html_e( 'Min. pauza SMTP (s)', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_smtp_delay" id="openvote_batch_smtp_delay"
                               value="<?php echo esc_attr( $batch_smtp_delay > 0 ? $batch_smtp_delay : '' ); ?>"
                               class="small-text" min="1" max="86400" placeholder="900" /> s
                    </td>
                    <td>
                        <label for="openvote_batch_smtp_per_15min" class="screen-reader-text"><?php esc_html_e( 'Maks. na 15 min SMTP', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_smtp_per_15min" id="openvote_batch_smtp_per_15min"
                               value="<?php echo esc_attr( $batch_smtp_per_15 > 0 ? $batch_smtp_per_15 : '' ); ?>"
                               class="small-text" min="0" max="1000" placeholder="100" />
                    </td>
                    <td>
                        <label for="openvote_batch_smtp_per_hour" class="screen-reader-text"><?php esc_html_e( 'Maks. na godzinę SMTP', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_smtp_per_hour" id="openvote_batch_smtp_per_hour"
                               value="<?php echo esc_attr( $batch_smtp_per_hour > 0 ? $batch_smtp_per_hour : '' ); ?>"
                               class="small-text" min="0" max="10000" placeholder="400" />
                    </td>
                    <td>
                        <label for="openvote_batch_smtp_per_day" class="screen-reader-text"><?php esc_html_e( 'Maks. ilość na dobę SMTP', 'openvote' ); ?></label>
                        <input type="number" name="openvote_batch_smtp_per_day" id="openvote_batch_smtp_per_day"
                               value="<?php echo esc_attr( $batch_smtp_per_day > 0 ? $batch_smtp_per_day : '' ); ?>"
                               class="small-text" min="1" max="100000" placeholder="500" />
                    </td>
                </tr>
                <tr>
                    <td colspan="7" style="background:#f0f0f1;font-weight:600;padding:8px 10px;"><?php esc_html_e( 'Abonamentowe usługi:', 'openvote' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'BREVO (płatne)', 'openvote' ); ?></strong></td>
                    <td><?php esc_html_e( 'Zawsze dostępne (limity zależne od planu Brevo).', 'openvote' ); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Freshmail API', 'openvote' ); ?></strong></td>
                    <td><?php esc_html_e( 'Zawsze dostępne (wymaga klucza API i sekretu).', 'openvote' ); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'GetResponse API', 'openvote' ); ?></strong></td>
                    <td><?php esc_html_e( 'Wymaga dodatku Transactional (np. GetResponse MAX).', 'openvote' ); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'SendGrid API', 'openvote' ); ?></strong></td>
                    <td><?php esc_html_e( 'Zawsze dostępne (wymaga klucza API).', 'openvote' ); ?></td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                    <td>—</td>
                </tr>
            </tbody>
        </table>
        </div>

        <p class="openvote-settings-save-wrap">
            <?php submit_button( __( 'Zapisz konfigurację', 'openvote' ), 'primary', 'submit', false ); ?>
        </p>

        <script>
        (function(){
            var radioWp        = document.getElementById('openvote_mail_wp');
            var radioSmtp      = document.getElementById('openvote_mail_smtp');
            var radioSg        = document.getElementById('openvote_mail_sendgrid');
            var radioBrevo     = document.getElementById('openvote_mail_brevo');
            var radioBrevoPaid = document.getElementById('openvote_mail_brevo_paid');
            var radioFreshmail = document.getElementById('openvote_mail_freshmail');
            var radioGetresponse = document.getElementById('openvote_mail_getresponse');
            function getSelectedMethod(){
                if(radioGetresponse && radioGetresponse.checked) return 'getresponse';
                if(radioFreshmail && radioFreshmail.checked) return 'freshmail';
                if(radioBrevoPaid && radioBrevoPaid.checked) return 'brevo_paid';
                if(radioBrevo && radioBrevo.checked) return 'brevo';
                if(radioSg && radioSg.checked) return 'sendgrid';
                if(radioSmtp && radioSmtp.checked) return 'smtp';
                return 'wordpress';
            }

            /* Test invitation (HTML) — jedyne miejsce wysyłki e-maila testowego */
            var invBtn = document.getElementById('openvote-test-invitation-btn');
            var invRes = document.getElementById('openvote-test-invitation-result');
            var invTo = document.getElementById('openvote_test_invitation_to');
            function getSelectedMailMethod(){
                var r = document.querySelector('input[name="openvote_mail_method"]:checked');
                return (r && r.value) ? r.value : 'wordpress';
            }
            if(invBtn){
                invBtn.addEventListener('click', function(){
                    var to = invTo && invTo.value ? invTo.value.trim() : '';
                    if(!to){ if(invRes) invRes.textContent = '<?php echo esc_js( __( 'Podaj adres e-mail.', 'openvote' ) ); ?>'; if(invRes) invRes.style.color = '#c00'; return; }
                    invBtn.disabled = true;
                    if(invRes) invRes.textContent = '<?php echo esc_js( __( 'Wysyłanie…', 'openvote' ) ); ?>';
                    if(invRes) invRes.style.color = '#666';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action:       'openvote_send_test_invitation_email',
                            nonce:        '<?php echo esc_js( wp_create_nonce( 'openvote_send_test_invitation_email' ) ); ?>',
                            to:           to,
                            test_method:  getSelectedMailMethod(),
                        })
                    }).then(r=>r.json()).then(function(d){
                        if(invRes) { invRes.textContent = (d.data && d.data.message) ? d.data.message : (d.message || '?'); invRes.style.color = d.success ? 'green' : '#c00'; }
                    }).catch(function(){
                        if(invRes) { invRes.textContent = '<?php echo esc_js( __( 'Błąd połączenia.', 'openvote' ) ); ?>'; invRes.style.color = '#c00'; }
                    }).finally(function(){ invBtn.disabled = false; });
                });
            }
        })();
        </script>

        <div class="openvote-settings-url-section">
        <h2 class="title openvote-settings-url-section__title"><?php esc_html_e( 'URL strony głosowania', 'openvote' ); ?></h2>
        <p class="description openvote-settings-url-section__desc">
            <?php esc_html_e( 'Adres ma postać: adres instalacji + slug w ścieżce (np. /glosuj/). Użytkownicy wchodzą pod link pokazany poniżej.', 'openvote' ); ?>
        </p>
        <table class="form-table openvote-settings-url-section__table" role="presentation">
            <tr>
                <th scope="row"><label for="openvote_vote_page_slug"><?php esc_html_e( 'Slug strony', 'openvote' ); ?></label></th>
                <td>
                    <input type="text" name="openvote_vote_page_slug" id="openvote_vote_page_slug" value="<?php echo esc_attr( openvote_get_vote_page_slug() ); ?>" class="regular-text" style="width:140px;" placeholder="glosuj" />
                    <?php
                    $slug_param = openvote_get_vote_page_slug();
                    $wp_vote_page = get_page_by_path( $slug_param, OBJECT, 'page' );
                    if ( $wp_vote_page && $wp_vote_page->post_status === 'publish' ) {
                        $vote_page_url = get_permalink( $wp_vote_page );
                        $url_note      = __( 'Strona WordPress (możesz ją edytować w Gutenberg):', 'openvote' );
                    } else {
                        $vote_page_url = openvote_get_vote_page_url();
                        $url_note      = __( 'Adres strony głosowania:', 'openvote' );
                    }
                    ?>
                    <p class="description" style="margin-top:6px;">
                        <?php echo esc_html( $url_note ); ?>
                        <strong><a href="<?php echo esc_url( $vote_page_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vote_page_url ); ?></a></strong>
                        <?php if ( $wp_vote_page ) : ?>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( get_edit_post_link( $wp_vote_page->ID ) ); ?>" target="_blank">
                            <?php esc_html_e( 'Edytuj w Gutenberg', 'openvote' ); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                    <?php if ( ! $wp_vote_page ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_create_vote_page" value="1" class="button button-primary" data-loading="<?php echo esc_attr( __( 'Zapisywanie…', 'openvote' ) ); ?>">
                            <?php esc_html_e( 'Utwórz stronę głosowania', 'openvote' ); ?>
                        </button>
                        <span class="description" style="margin-left:8px;"><?php esc_html_e( 'Stworzy stronę WordPress z blokiem zakładek (Trwające / Zakończone głosowania).', 'openvote' ); ?></span>
                    </p>
                    <?php elseif ( $wp_vote_page && ! openvote_vote_page_has_tabs_block() ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_update_vote_page" value="1" class="button button-primary" data-loading="<?php echo esc_attr( __( 'Zapisywanie…', 'openvote' ) ); ?>">
                            <?php esc_html_e( 'Zaktualizuj stronę głosowania (dodaj blok)', 'openvote' ); ?>
                        </button>
                        <span class="description" style="margin-left:8px;"><?php esc_html_e( 'Zastąpi treść strony blokiem zakładek.', 'openvote' ); ?></span>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        </div>

        <!-- ── URL strony ankiet ──────────────────────────────────────────── -->
        <div class="openvote-settings-url-section">
        <h2 class="title openvote-settings-url-section__title"><?php esc_html_e( 'URL strony ankiet', 'openvote' ); ?></h2>
        <p class="description openvote-settings-url-section__desc">
            <?php esc_html_e( 'Adres publicznej strony z ankietami. Najlepiej stwórz stronę WordPress i wstaw blok "Ankiety (Open Vote)", aby korzystać z edytora Gutenberg.', 'openvote' ); ?>
        </p>
        <?php
        if ( isset( $_GET['survey_page_created'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona ankiet została utworzona.', 'openvote' ); ?></p></div>
        <?php elseif ( isset( $_GET['survey_page_updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona ankiet została zaktualizowana.', 'openvote' ); ?></p></div>
        <?php elseif ( isset( $_GET['survey_page_update_error'] ) ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Nie udało się zaktualizować strony ankiet. Sprawdź, czy strona o podanym slugu istnieje i czy masz uprawnienia do jej edycji. Możesz dodać blok „Ankiety (Open Vote)” ręcznie w edytorze strony.', 'openvote' ); ?></p></div>
        <?php endif; ?>

        <table class="form-table openvote-settings-url-section__table" role="presentation">
            <tr>
                <th scope="row"><label for="openvote_survey_page_slug"><?php esc_html_e( 'Slug strony', 'openvote' ); ?></label></th>
                <td>
                    <input type="text" name="openvote_survey_page_slug" id="openvote_survey_page_slug"
                           value="<?php echo esc_attr( class_exists( 'Openvote_Survey_Page' ) ? Openvote_Survey_Page::get_slug() : get_option( 'openvote_survey_page_slug', 'ankieta' ) ); ?>"
                           class="regular-text" style="width:140px;" placeholder="ankieta" />
                    <?php
                    $surv_slug = class_exists( 'Openvote_Survey_Page' ) ? Openvote_Survey_Page::get_slug() : 'ankieta';
                    $surv_page = get_page_by_path( $surv_slug, OBJECT, 'page' );
                    if ( $surv_page && 'publish' === $surv_page->post_status ) {
                        $surv_url = get_permalink( $surv_page->ID );
                    } else {
                        $surv_url = class_exists( 'Openvote_Survey_Page' ) ? Openvote_Survey_Page::get_url() : home_url( '/' . $surv_slug . '/' );
                    }
                    ?>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Adres strony ankiet:', 'openvote' ); ?>
                        <strong><a href="<?php echo esc_url( $surv_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $surv_url ); ?></a></strong>
                        <?php if ( $surv_page ) : ?>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( get_edit_post_link( $surv_page->ID ) ); ?>" target="_blank">
                            <?php esc_html_e( 'Edytuj w Gutenberg', 'openvote' ); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                    <?php if ( ! $surv_page ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_create_survey_page" value="1" class="button button-primary" data-loading="<?php echo esc_attr( __( 'Zapisywanie…', 'openvote' ) ); ?>">
                            <?php esc_html_e( 'Utwórz stronę ankiet', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php elseif ( $surv_page && class_exists( 'Openvote_Survey_Page' ) && ! Openvote_Survey_Page::page_has_survey_block() ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_update_survey_page" value="1" class="button button-primary" data-loading="<?php echo esc_attr( __( 'Zapisywanie…', 'openvote' ) ); ?>">
                            <?php esc_html_e( 'Zaktualizuj stronę ankiet (dodaj blok)', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        </div>

        <!-- ── URL strony zgłoszeń ─────────────────────────────────────────── -->
        <div class="openvote-settings-url-section">
        <h2 class="title openvote-settings-url-section__title"><?php esc_html_e( 'URL strony zgłoszeń', 'openvote' ); ?></h2>
        <p class="description openvote-settings-url-section__desc">
            <?php esc_html_e( 'Adres publicznej strony ze zgłoszeniami oznaczonymi jako „Nie spam”. Stwórz stronę i wstaw blok „Zgłoszenia (Open Vote)” lub użyj przycisków poniżej.', 'openvote' ); ?>
        </p>
        <?php
        if ( isset( $_GET['submissions_page_created'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona zgłoszeń została utworzona.', 'openvote' ); ?></p></div>
        <?php elseif ( isset( $_GET['submissions_page_updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona zgłoszeń została zaktualizowana.', 'openvote' ); ?></p></div>
        <?php elseif ( isset( $_GET['submissions_page_update_error'] ) ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Nie udało się zaktualizować strony zgłoszeń. Sprawdź, czy strona o podanym slugu istnieje i czy masz uprawnienia do jej edycji. Możesz dodać blok „Zgłoszenia (Open Vote)” ręcznie w edytorze strony.', 'openvote' ); ?></p></div>
        <?php endif; ?>

        <table class="form-table openvote-settings-url-section__table" role="presentation">
            <tr>
                <th scope="row"><label for="openvote_submissions_page_slug"><?php esc_html_e( 'Slug strony', 'openvote' ); ?></label></th>
                <td>
                    <?php $subm_slug = get_option( 'openvote_submissions_page_slug', 'zgloszenia' ); ?>
                    <input type="text" name="openvote_submissions_page_slug" id="openvote_submissions_page_slug"
                           value="<?php echo esc_attr( $subm_slug ); ?>"
                           class="regular-text" style="width:140px;" placeholder="zgloszenia" />
                    <?php
                    $subm_page = get_page_by_path( $subm_slug, OBJECT, 'page' );
                    if ( $subm_page && 'publish' === $subm_page->post_status ) {
                        $subm_url = get_permalink( $subm_page->ID );
                    } else {
                        $subm_url = home_url( '/' . $subm_slug . '/' );
                    }
                    $has_block = class_exists( 'Openvote_Admin_Settings' ) && Openvote_Admin_Settings::page_has_submissions_block( $subm_slug );
                    ?>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Adres strony zgłoszeń:', 'openvote' ); ?>
                        <strong><a href="<?php echo esc_url( $subm_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $subm_url ); ?></a></strong>
                        <?php if ( $subm_page ) : ?>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( get_edit_post_link( $subm_page->ID ) ); ?>" target="_blank">
                            <?php esc_html_e( 'Edytuj w Gutenberg', 'openvote' ); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                    <?php if ( ! $subm_page ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_create_submissions_page" value="1" class="button button-primary" data-loading="<?php echo esc_attr( __( 'Zapisywanie…', 'openvote' ) ); ?>">
                            <?php esc_html_e( 'Utwórz stronę zgłoszeń', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php elseif ( $subm_page && ! $has_block ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_update_submissions_page" value="1" class="button button-primary" data-loading="<?php echo esc_attr( __( 'Zapisywanie…', 'openvote' ) ); ?>">
                            <?php esc_html_e( 'Zaktualizuj stronę zgłoszeń (dodaj blok)', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        </div>

        <p class="openvote-settings-save-wrap">
            <?php submit_button( __( 'Zapisz konfigurację', 'openvote' ), 'primary', 'submit', false ); ?>
        </p>

        <div class="openvote-settings-url-section openvote-settings-time-section">
        <h2 class="title openvote-settings-url-section__title"><?php esc_html_e( 'Ustawienie czasu (strefa głosowań)', 'openvote' ); ?></h2>
        <p class="description openvote-settings-url-section__desc">
            <?php esc_html_e( 'Jeśli serwer ma inną strefę czasową niż Twoja, ustaw przesunięcie. Czas po przesunięciu jest używany do sprawdzania okresu głosowania i wyświetlania liczników.', 'openvote' ); ?>
        </p>
        <?php
        $current_offset = openvote_get_time_offset_hours();
        $server_time    = current_time( 'Y-m-d H:i:s' );
        $voting_time    = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
        ?>
        <table class="form-table openvote-settings-url-section__table" role="presentation">
            <tr>
                <th scope="row"><label for="openvote_time_offset_hours"><?php esc_html_e( 'Przesunięcie czasu', 'openvote' ); ?></label></th>
                <td>
                    <select name="openvote_time_offset_hours" id="openvote_time_offset_hours">
                        <?php for ( $h = -12; $h <= 12; $h++ ) : ?>
                            <option value="<?php echo (int) $h; ?>" <?php selected( $current_offset, $h ); ?>>
                                <?php echo esc_html( $h === 0 ? __( '0 (bez zmiany)', 'openvote' ) : sprintf( __( '%+d h', 'openvote' ), $h ) ); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Czas na serwerze', 'openvote' ); ?></th>
                <td>
                    <code id="openvote-server-time"><?php echo esc_html( $server_time ); ?></code>
                    <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Aktualny czas według ustawień WordPress (strefa z Ustawienia → Ogólne).', 'openvote' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Czas dla głosowań', 'openvote' ); ?></th>
                <td>
                    <strong id="openvote-voting-time"><?php echo esc_html( $voting_time ); ?></strong>
                    <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Czas używany przy sprawdzaniu rozpoczęcia i zakończenia głosowania (serwer + przesunięcie).', 'openvote' ); ?></p>
                </td>
            </tr>
        </table>
        </div>

        <div class="openvote-settings-url-section openvote-settings-cron-sync-section" style="margin-top:28px;">
        <h2 class="title openvote-settings-url-section__title"><?php esc_html_e( 'Automatyczna synchronizacja sejmików-miast (wp-cron)', 'openvote' ); ?></h2>
        <p class="description openvote-settings-url-section__desc">
            <?php esc_html_e( 'Cron uruchamia proces synchronizacji o 00:00 w strefie czasu WordPress (w niedziele według wybranego harmonogramu).', 'openvote' ); ?>
        </p>
        <?php
        $auto_sync_schedule = get_option( 'openvote_auto_sync_schedule', 'manual' );
        $allowed_schedules = [ 'manual', 'first_sunday', 'second_sunday', 'weekly', 'daily' ];
        if ( ! in_array( $auto_sync_schedule, $allowed_schedules, true ) ) {
            $auto_sync_schedule = 'manual';
        }
        $last_sync_date = get_option( 'openvote_last_cron_sync_date', '' );
        ?>
        <table class="form-table openvote-settings-url-section__table" role="presentation">
            <tr>
                <th scope="row"><label for="openvote_auto_sync_schedule"><?php esc_html_e( 'Harmonogram', 'openvote' ); ?></label></th>
                <td>
                    <select name="openvote_auto_sync_schedule" id="openvote_auto_sync_schedule" class="regular-text">
                        <option value="manual" <?php selected( $auto_sync_schedule, 'manual' ); ?>><?php esc_html_e( 'Tylko ręcznie', 'openvote' ); ?></option>
                        <option value="first_sunday" <?php selected( $auto_sync_schedule, 'first_sunday' ); ?>><?php esc_html_e( 'Automatycznie w pierwszą niedzielę miesiąca', 'openvote' ); ?></option>
                        <option value="second_sunday" <?php selected( $auto_sync_schedule, 'second_sunday' ); ?>><?php esc_html_e( 'Automatycznie co drugą niedzielę miesiąca', 'openvote' ); ?></option>
                        <option value="weekly" <?php selected( $auto_sync_schedule, 'weekly' ); ?>><?php esc_html_e( 'Automatycznie co niedzielę', 'openvote' ); ?></option>
                        <option value="daily" <?php selected( $auto_sync_schedule, 'daily' ); ?>><?php esc_html_e( 'Automatycznie codziennie (Nie zalecane przy dużej ilości użytkowników)', 'openvote' ); ?></option>
                    </select>
                    <p class="description" style="margin-top:8px;padding:8px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;max-width:480px;">
                        <?php if ( $last_sync_date !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $last_sync_date ) ) : ?>
                            <?php
                            /* translators: %s: date in Y-m-d format */
                            echo esc_html( sprintf( __( 'Ostatnia synchronizacja zakończona dnia %s.', 'openvote' ), $last_sync_date ) );
                            ?>
                        <?php else : ?>
                            <?php esc_html_e( 'Nie było jeszcze synchronizacji.', 'openvote' ); ?>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>
        </div>

        <p class="openvote-settings-save-wrap">
            <?php submit_button( __( 'Zapisz konfigurację', 'openvote' ), 'primary', 'submit', false ); ?>
        </p>

        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'Mapowanie pól użytkownika', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e(
                'Powiąż logiczne pola wtyczki z rzeczywistymi kluczami w bazie danych WordPress. '
                . 'Dzięki temu wtyczka będzie działać poprawnie niezależnie od użytej wtyczki rejestracji '
                . 'lub własnego schematu metadanych.',
                'openvote'
            ); ?>
        </p>

        <div class="notice notice-warning inline" style="max-width:860px;margin:0 0 16px;padding:12px 16px;">
            <p style="margin:0 0 6px;">
                <strong>⚠ <?php esc_html_e( 'Wymagana wtyczka do rozszerzonych pól profilu', 'openvote' ); ?></strong>
            </p>
            <p style="margin:0 0 8px;color:#3c3c3c;">
                <?php esc_html_e(
                    'Pola takie jak Telefon, PESEL czy Numer dowodu osobistego nie istnieją domyślnie w WordPress. '
                    . 'Aby użytkownicy mogli je uzupełniać, potrzebujesz wtyczki, która dodaje własne pola rejestracji i profilu — np.:',
                    'openvote'
                ); ?>
            </p>
            <ul style="margin:0 0 8px;padding-left:20px;list-style:disc;color:#3c3c3c;">
                <li>
                    <a href="https://wordpress.org/plugins/user-registration/" target="_blank" rel="noopener noreferrer">
                        <strong>User Registration &amp; Membership</strong>
                    </a>
                    <?php esc_html_e( '— darmowa, 60 000+ aktywnych instalacji, obsługuje własne pola i powiązuje je z usermeta.', 'openvote' ); ?>
                </li>
                <li>
                    <?php esc_html_e( 'Lub każda inna wtyczka zapisująca dane w', 'openvote' ); ?>
                    <code>wp_usermeta</code>
                    <?php esc_html_e( 'pod dowolnym kluczem (meta_key) — wpisz ten klucz w kolumnie poniżej.', 'openvote' ); ?>
                </li>
            </ul>
            <p style="margin:0;font-size:12px;color:#777;">
                <?php esc_html_e(
                    'Jeśli dana wtyczka zapisuje numer telefonu jako „user_gsm", wpisz „user_gsm" jako klucz dla pola Telefon.',
                    'openvote'
                ); ?>
            </p>
        </div>

        <table class="widefat fixed openvote-settings-table" style="max-width:960px;">
            <thead>
                <tr>
                    <th style="width:220px;"><?php esc_html_e( 'Pole logiczne', 'openvote' ); ?></th>
                    <th><?php esc_html_e( 'Klucz w bazie danych WordPress', 'openvote' ); ?></th>
                    <th style="width:110px;text-align:center;"><?php esc_html_e( 'Wymagane', 'openvote' ); ?><br><span style="font-weight:400;font-size:11px;color:#888;"><?php esc_html_e( 'do głosowania', 'openvote' ); ?></span></th>
                    <th style="width:110px;text-align:center;"><?php esc_html_e( 'Wymagane', 'openvote' ); ?><br><span style="font-weight:400;font-size:11px;color:#888;"><?php esc_html_e( 'do ankiety', 'openvote' ); ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $labels as $logical => $label ) :
                    $current_val      = $current_map[ $logical ] ?? Openvote_Field_Map::DEFAULTS[ $logical ];
                    $is_core          = Openvote_Field_Map::is_core_field( $current_val );
                    $always_req       = in_array( $logical, Openvote_Field_Map::ALWAYS_REQUIRED, true );
                    $is_req           = Openvote_Field_Map::is_required( $logical );
                    $survey_always    = in_array( $logical, Openvote_Field_Map::SURVEY_ALWAYS_REQUIRED, true );
                    $is_survey_req    = Openvote_Field_Map::is_survey_required( $logical );
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $label ); ?></strong>
                        <?php if ( 'email' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Zazwyczaj user_email (pole wbudowane)', 'openvote' ); ?>
                            </span>
                        <?php elseif ( 'phone' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_gsm', 'openvote' ); ?>
                            </span>
                        <?php elseif ( 'pesel' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_pesel', 'openvote' ); ?>
                            </span>
                        <?php elseif ( 'id_card' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_id', 'openvote' ); ?>
                            </span>
                        <?php elseif ( 'address' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_address', 'openvote' ); ?>
                            </span>
                        <?php elseif ( 'zip_code' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_zip', 'openvote' ); ?>
                            </span>
                        <?php elseif ( 'town' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_city', 'openvote' ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php openvote_settings_select(
                            $logical,
                            $current_val,
                            $available_keys['core'],
                            $available_keys['meta']
                        ); ?>
                        <br>
                        <?php if ( $current_val === Openvote_Field_Map::NOT_SET_KEY ) : ?>
                            <span class="openvote-badge" style="background:#f0f0f1;color:#787c82;"><?php esc_html_e( 'nie określone', 'openvote' ); ?></span>
                        <?php elseif ( 'city' === $logical && $current_val === Openvote_Field_Map::NO_CITY_KEY ) : ?>
                            <code style="margin-top:4px;display:inline-block;"><?php esc_html_e( 'Nie używaj miast', 'openvote' ); ?></code>
                            <span class="openvote-badge openvote-badge--meta"><?php esc_html_e( 'grupa Wszyscy', 'openvote' ); ?></span>
                        <?php else : ?>
                            <code style="margin-top:4px;display:inline-block;"><?php echo esc_html( $current_val ); ?></code>
                            <?php if ( $is_core ) : ?>
                                <span class="openvote-badge openvote-badge--core"><?php esc_html_e( 'wbudowane', 'openvote' ); ?></span>
                            <?php else : ?>
                                <span class="openvote-badge openvote-badge--meta"><?php esc_html_e( 'usermeta', 'openvote' ); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;vertical-align:middle;">
                        <?php if ( $always_req ) : ?>
                            <span class="openvote-always-required-box" title="<?php esc_attr_e( 'To pole jest zawsze wymagane', 'openvote' ); ?>" aria-label="<?php esc_attr_e( 'zawsze wymagane', 'openvote' ); ?>">
                                <span class="openvote-always-required-box__check" aria-hidden="true">✓</span>
                            </span>
                            <br><span style="font-size:11px;color:#888;"><?php esc_html_e( 'zawsze', 'openvote' ); ?></span>
                        <?php else : ?>
                            <input type="checkbox"
                                   name="openvote_required_fields[]"
                                   value="<?php echo esc_attr( $logical ); ?>"
                                   <?php checked( $is_req ); ?>>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;vertical-align:middle;">
                        <?php if ( $survey_always ) : ?>
                            <span class="openvote-always-required-box" title="<?php esc_attr_e( 'To pole jest zawsze wymagane dla ankiet', 'openvote' ); ?>" aria-label="<?php esc_attr_e( 'zawsze wymagane', 'openvote' ); ?>">
                                <span class="openvote-always-required-box__check" aria-hidden="true">✓</span>
                            </span>
                            <br><span style="font-size:11px;color:#888;"><?php esc_html_e( 'zawsze', 'openvote' ); ?></span>
                        <?php else : ?>
                            <input type="checkbox"
                                   name="openvote_survey_required_fields[]"
                                   value="<?php echo esc_attr( $logical ); ?>"
                                   <?php checked( $is_survey_req ); ?>>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $role_screen_map = openvote_get_role_screen_map();
        $role_labels = [
            'subscriber'          => __( 'Subskrybent', 'openvote' ),
            'author'              => __( 'Autor', 'openvote' ),
            'editor'              => __( 'Edytor', 'openvote' ),
            'administrator'       => __( 'Administrator', 'openvote' ),
            'openvote_coordinator' => __( 'Koordynator', 'openvote' ),
        ];
        $screen_labels = [
            'openvote'          => __( 'Głosowania', 'openvote' ),
            'openvote-surveys'  => __( 'Ankiety', 'openvote' ),
            'openvote-groups'   => __( 'Członkowie i Sejmiki', 'openvote' ),
            'openvote-roles'    => __( 'Koordynatorzy i Sejmiki', 'openvote' ),
            'openvote-settings' => __( 'Konfiguracja', 'openvote' ),
        ];
        ?>
        <h2 class="title" style="margin-top:36px;"><?php esc_html_e( 'Mapowanie roli', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 16px;">
            <?php esc_html_e( 'Zaznaczenie decyduje o tym, które role mogą wyświetlać dane menu i mieć dostęp do danego ekranu. Odznaczenie blokuje widoczność pozycji w menu oraz dostęp do strony.', 'openvote' ); ?>
        </p>
        <table class="widefat fixed openvote-settings-table" style="max-width:900px;">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Rola', 'openvote' ); ?></th>
                    <?php foreach ( $screen_labels as $screen_slug => $label ) : ?>
                        <th scope="col"><?php echo esc_html( $label ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( array_keys( $role_labels ) as $role_slug ) :
                    $is_admin = ( $role_slug === 'administrator' );
                ?>
                <tr>
                    <td><?php echo esc_html( $role_labels[ $role_slug ] ); ?></td>
                    <?php foreach ( array_keys( $screen_labels ) as $screen_slug ) : ?>
                        <td>
                            <?php if ( $is_admin ) : ?>
                                <?php // Administrator ma zawsze dostęp; checkbox tylko do odczytu, wartość wysyłana hidden. ?>
                                <input type="hidden" name="openvote_role_screen[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $screen_slug ); ?>]" value="1">
                                <input type="checkbox"
                                       id="openvote_role_screen_<?php echo esc_attr( $role_slug ); ?>_<?php echo esc_attr( $screen_slug ); ?>"
                                       checked
                                       disabled
                                       aria-label="<?php echo esc_attr( $role_labels[ $role_slug ] . ' — ' . $screen_labels[ $screen_slug ] ); ?>">
                            <?php else : ?>
                                <label class="screen-reader-text" for="openvote_role_screen_<?php echo esc_attr( $role_slug ); ?>_<?php echo esc_attr( $screen_slug ); ?>"><?php echo esc_html( $role_labels[ $role_slug ] . ' — ' . $screen_labels[ $screen_slug ] ); ?></label>
                                <input type="checkbox"
                                       id="openvote_role_screen_<?php echo esc_attr( $role_slug ); ?>_<?php echo esc_attr( $screen_slug ); ?>"
                                       name="openvote_role_screen[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $screen_slug ); ?>]"
                                       value="1"
                                       <?php checked( ! empty( $role_screen_map[ $role_slug ][ $screen_slug ] ) ); ?>>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="description" style="margin-top:16px;max-width:700px;">
            <strong><?php esc_html_e( 'Dostęp Koordynatatora do głosowań', 'openvote' ); ?></strong>
        </p>
        <p class="description" style="max-width:700px;margin:4px 0 8px;">
            <?php esc_html_e( 'Gdy wybrano „Do tylko własnych Sejmików”, koordynator widzi na liście tylko głosowania i ankiety dotyczące swoich sejmików oraz na stronie Członkowie i Sejmiki tylko swoje sejmiki. Może organizować głosowania wyłącznie w przypisanych sejmikach. Domyślnie koordynator ma dostęp do wszystkich sejmików.', 'openvote' ); ?>
        </p>
        <?php
        $coordinator_access = get_option( 'openvote_coordinator_poll_access', 'all' );
        $coordinator_access = ( $coordinator_access === 'own' ) ? 'own' : 'all';
        ?>
        <p>
            <label for="openvote_coordinator_poll_access"><?php esc_html_e( 'Zakres dostępu koordynatora:', 'openvote' ); ?></label>
            <select name="openvote_coordinator_poll_access" id="openvote_coordinator_poll_access">
                <option value="all" <?php selected( $coordinator_access, 'all' ); ?>><?php esc_html_e( 'Do wszystkich Sejmików (Grup/Miast)', 'openvote' ); ?></option>
                <option value="own" <?php selected( $coordinator_access, 'own' ); ?>><?php esc_html_e( 'Do tylko własnych Sejmików (Grup/Miast)', 'openvote' ); ?></option>
            </select>
        </p>

        <!-- ── Szablon e-maila zapraszającego ─────────────────────────────── -->

        <h2 class="title" style="margin-top:36px;"><?php esc_html_e( 'Szablon e-maila zapraszającego', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 16px;">
            <?php esc_html_e(
                'Treść e-maila wysyłanego do uczestników przy starcie głosowania. '
                . 'W treści i temacie możesz użyć pól wbudowanych z tabeli poniżej – zostaną one zastąpione danymi głosowania i witryny.',
                'openvote'
            ); ?>
        </p>

        <div class="openvote-email-placeholders-legend" style="max-width:760px;margin-bottom:24px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);overflow:hidden;">
            <h3 style="margin:0;padding:12px 16px;background:#f0f0f1;border-bottom:1px solid #c3c4c7;font-size:14px;">
                <?php esc_html_e( 'Pola wbudowane w treść e-maila', 'openvote' ); ?>
            </h3>
            <table class="widefat striped" style="margin:0;border:none;">
                <thead>
                    <tr>
                        <th style="width:180px;"><?php esc_html_e( 'Pole', 'openvote' ); ?></th>
                        <th><?php esc_html_e( 'Opis', 'openvote' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>{from_email}</code></td><td><?php esc_html_e( 'Adres e-mail nadawcy (pole powyżej).', 'openvote' ); ?></td></tr>
                    <tr><td><code>{site_url}</code></td><td><?php esc_html_e( 'Adres główny witryny.', 'openvote' ); ?></td></tr>
                    <tr><td><code>{vote_url}</code></td><td><?php esc_html_e( 'Adres strony głosowania.', 'openvote' ); ?></td></tr>
                    <tr><td><code>{date_start}</code></td><td><?php esc_html_e( 'Data i godzina rozpoczęcia głosowania.', 'openvote' ); ?></td></tr>
                    <tr><td><code>{date_end}</code></td><td><?php esc_html_e( 'Data i godzina zakończenia głosowania.', 'openvote' ); ?></td></tr>
                    <tr><td><code>{questions}</code></td><td><?php esc_html_e( 'Lista pytań i odpowiedzi (w HTML: lista punktowana, w tekście: numerowana).', 'openvote' ); ?></td></tr>
                    <tr><td><code>{site_name}</code></td><td><?php esc_html_e( 'Nazwa witryny (Ustawienia → Ogólne).', 'openvote' ); ?></td></tr>
                    <tr><td><code>{brand_short}</code></td><td><?php esc_html_e( 'Skrót nazwy systemu (z Konfiguracji powyżej).', 'openvote' ); ?></td></tr>
                    <tr><td><code>{site_tagline}</code></td><td><?php esc_html_e( 'Slogan witryny (Ustawienia → Ogólne).', 'openvote' ); ?></td></tr>
                    <tr><td><code>{poll_title}</code></td><td><?php esc_html_e( 'Tytuł głosowania.', 'openvote' ); ?></td></tr>
                </tbody>
            </table>
        </div>

        <table class="form-table" style="max-width:760px;">
            <tr>
                <th scope="row" style="width:160px;">
                    <label for="openvote_email_subject"><?php esc_html_e( 'Temat', 'openvote' ); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="openvote_email_subject"
                           name="openvote_email_subject"
                           value="<?php echo esc_attr( openvote_get_email_subject_template() ); ?>"
                           class="large-text"
                           placeholder="<?php esc_attr_e( 'Zaproszenie do głosowania: {poll_title}', 'openvote' ); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="openvote_email_from_template"><?php esc_html_e( 'Od (nazwa nadawcy)', 'openvote' ); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="openvote_email_from_template"
                           name="openvote_email_from_template"
                           value="<?php echo esc_attr( openvote_get_email_from_template() ); ?>"
                           class="large-text"
                           placeholder="<?php esc_attr_e( '{brand_short} ({from_email})', 'openvote' ); ?>" />
                    <p class="description" style="margin-top:4px;">
                        <?php esc_html_e( 'Nazwa wyświetlana jako nadawca w kliencie pocztowym. Adres e-mail pochodzi z pola "E-mail nadawcy" powyżej.', 'openvote' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row" style="vertical-align:top;padding-top:12px;">
                    <?php esc_html_e( 'Typ szablonu wysyłanego', 'openvote' ); ?>
                </th>
                <td>
                    <fieldset>
                        <label><input type="radio" name="openvote_email_template_type" value="plain" <?php checked( openvote_get_email_template_type(), 'plain' ); ?> /> <?php esc_html_e( 'Szablon czysty tekst (text/plain)', 'openvote' ); ?></label><br>
                        <label><input type="radio" name="openvote_email_template_type" value="html" <?php checked( openvote_get_email_template_type(), 'html' ); ?> /> <?php esc_html_e( 'Szablon HTML (text/html)', 'openvote' ); ?></label>
                    </fieldset>
                    <p class="description" style="margin-top:4px;">
                        <?php esc_html_e( 'Wybierz, w jakim formacie mają być wysyłane e-maile zaproszenia.', 'openvote' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row" style="vertical-align:top;padding-top:12px;">
                    <label for="openvote_email_body_plain"><?php esc_html_e( 'Treść (wersja czysty tekst)', 'openvote' ); ?></label>
                </th>
                <td>
                    <textarea id="openvote_email_body_plain"
                              name="openvote_email_body_plain"
                              rows="14"
                              class="large-text"
                              style="font-family:monospace;font-size:13px;line-height:1.6;"><?php echo esc_textarea( openvote_get_email_body_plain_template() ); ?></textarea>
                    <p style="margin-top:6px;">
                        <button type="button" class="button" id="openvote-email-reset-plain-btn"><?php esc_html_e( 'Przywróć domyślną (czysty tekst)', 'openvote' ); ?></button>
                        <button type="button" class="button" id="openvote-email-preview-plain-btn"><?php esc_html_e( 'Zobacz podgląd', 'openvote' ); ?></button>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row" style="vertical-align:top;padding-top:12px;">
                    <label for="openvote_email_body_html"><?php esc_html_e( 'Treść (wersja HTML)', 'openvote' ); ?></label>
                </th>
                <td>
                    <textarea id="openvote_email_body_html"
                              name="openvote_email_body_html"
                              rows="24"
                              class="large-text"
                              style="font-family:monospace;font-size:12px;line-height:1.5;"><?php echo esc_textarea( openvote_get_email_body_html_template() ); ?></textarea>
                    <p style="margin-top:6px;">
                        <button type="button" class="button" id="openvote-email-reset-html-btn"><?php esc_html_e( 'Przywróć domyślną (HTML)', 'openvote' ); ?></button>
                        <button type="button" class="button" id="openvote-email-preview-html-btn"><?php esc_html_e( 'Zobacz podgląd', 'openvote' ); ?></button>
                    </p>
                </td>
            </tr>
        </table>

        <script>
        ( function() {
            var plainDefault = <?php echo json_encode( openvote_get_email_body_plain_default() ); ?>;
            var htmlDefault = <?php echo json_encode( openvote_get_email_body_html_default() ); ?>;
            var previewPlaceholders = <?php echo json_encode( [
                'poll_title'     => __( 'Przykładowe głosowanie', 'openvote' ),
                'brand_short'    => openvote_get_brand_short_name(),
                'from_email'     => openvote_get_from_email(),
                'vote_url'       => openvote_get_vote_page_url(),
                'date_start'     => wp_date( 'd.m.Y H:i', strtotime( 'today' ) ),
                'date_end'       => wp_date( 'd.m.Y H:i', strtotime( '+7 days' ) ),
                'questions'      => '<ul><li>' . __( 'Przykładowe pytanie 1', 'openvote' ) . '<ul><li>' . __( 'Opcja A', 'openvote' ) . '</li><li>' . __( 'Opcja B', 'openvote' ) . '</li></ul></li><li>' . __( 'Przykładowe pytanie 2', 'openvote' ) . '</li></ul>',
                'questions_plain' => "1. " . __( 'Przykładowe pytanie 1', 'openvote' ) . "\n   - " . __( 'Opcja A', 'openvote' ) . "\n   - " . __( 'Opcja B', 'openvote' ) . "\n\n2. " . __( 'Przykładowe pytanie 2', 'openvote' ),
                'site_url'       => home_url( '/' ),
                'site_name'      => get_bloginfo( 'name' ),
                'site_tagline'   => get_bloginfo( 'description' ),
                'plugin_author'  => OPENVOTE_PLUGIN_AUTHOR,
                'github_url'     => OPENVOTE_GITHUB_URL,
            ] ); ?>;
            document.getElementById('openvote-email-reset-plain-btn').addEventListener('click', function() {
                if (!confirm('<?php echo esc_js( __( 'Przywrócić domyślną treść (czysty tekst)? Obecna treść zostanie nadpisana.', 'openvote' ) ); ?>')) return;
                document.getElementById('openvote_email_body_plain').value = plainDefault;
            });
            document.getElementById('openvote-email-preview-plain-btn').addEventListener('click', function() {
                var text = document.getElementById('openvote_email_body_plain').value;
                var placeholders = {};
                for (var k in previewPlaceholders) {
                    if (previewPlaceholders.hasOwnProperty(k)) {
                        placeholders[k] = previewPlaceholders[k];
                    }
                }
                placeholders.questions = placeholders.questions_plain || placeholders.questions;
                var key;
                for (key in placeholders) {
                    if (placeholders.hasOwnProperty(key) && key !== 'questions_plain') {
                        text = text.replace(new RegExp('\\{' + key + '\\}', 'g'), placeholders[key]);
                    }
                }
                var w = window.open('', '_blank');
                if (w) {
                    w.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title><?php echo esc_js( __( 'Podgląd wiadomości (czysty tekst)', 'openvote' ) ); ?></title></head><body style="font-family:monospace;font-size:14px;line-height:1.6;white-space:pre-wrap;max-width:720px;margin:24px auto;padding:0 20px;">' + (function() { var d = document.createElement('div'); d.textContent = text; return d.innerHTML; })() + '</body></html>');
                    w.document.close();
                }
            });
            document.getElementById('openvote-email-reset-html-btn').addEventListener('click', function() {
                if (!confirm('<?php echo esc_js( __( 'Przywrócić domyślną treść (HTML)? Obecna treść zostanie nadpisana.', 'openvote' ) ); ?>')) return;
                document.getElementById('openvote_email_body_html').value = htmlDefault;
            });
            document.getElementById('openvote-email-preview-html-btn').addEventListener('click', function() {
                var html = document.getElementById('openvote_email_body_html').value;
                var key;
                for (key in previewPlaceholders) {
                    if (previewPlaceholders.hasOwnProperty(key)) {
                        html = html.replace(new RegExp('\\{' + key + '\\}', 'g'), previewPlaceholders[key]);
                    }
                }
                var w = window.open('', '_blank');
                if (w) {
                    w.document.write(html);
                    w.document.close();
                }
            });
        } )();
        </script>

        <p class="openvote-settings-save-wrap" style="margin-top:16px;">
            <?php submit_button( __( 'Zapisz konfigurację', 'openvote' ), 'primary', 'submit', false ); ?>
        </p>
    </form>

    <script>
    ( function () {
        var mainForm = document.getElementById( 'openvote-settings-form' );
        if ( mainForm ) {
            mainForm.addEventListener( 'submit', function ( e ) {
                var btn = e.submitter;
                if ( btn && btn.getAttribute( 'data-loading' ) ) {
                    // Wyłącz przycisk dopiero w następnym ticku — inaczej przeglądarka
                    // nie dołącza name/value wyłączonego przycisku do POST (np. openvote_create_vote_page).
                    var loadingText = btn.getAttribute( 'data-loading' );
                    setTimeout( function () {
                        btn.disabled = true;
                        btn.textContent = loadingText;
                    }, 0 );
                }
            } );
        }
    } )();
    </script>

    <hr>
    <h2 style="font-size:14px;"><?php esc_html_e( 'Jak to działa?', 'openvote' ); ?></h2>
    <ul style="list-style:disc;padding-left:20px;max-width:700px;color:#555;font-size:13px;">
        <li><?php esc_html_e( 'Użytkownik jest uprawniony do głosowania tylko jeśli wszystkie pola oznaczone jako "Wymagane" są wypełnione.', 'openvote' ); ?></li>
        <li><?php esc_html_e( 'Pole "Nazwa miasta" służy do definiowania grup docelowych głosowania.', 'openvote' ); ?></li>
        <li><?php esc_html_e( 'Pola wbudowane (np. user_email) są odczytywane z tabeli wp_users.', 'openvote' ); ?></li>
        <li><?php esc_html_e( 'Pozostałe pola są odczytywane z tabeli wp_usermeta.', 'openvote' ); ?></li>
        <li><?php esc_html_e( 'Po zmianie konfiguracji wszystkie nowe i istniejące głosowania używają nowych kluczy.', 'openvote' ); ?></li>
    </ul>

    <hr style="margin-top:32px;margin-bottom:20px;">
    <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Wyczyść bazę danych wtyczki i przywróć wartości fabryczne', 'openvote' ); ?></h2>

    <?php
    $openvote_clean_error   = get_transient( 'openvote_clean_error' );
    $openvote_clean_success = get_transient( 'openvote_clean_success' );
    if ( $openvote_clean_error )   delete_transient( 'openvote_clean_error' );
    if ( $openvote_clean_success ) delete_transient( 'openvote_clean_success' );

    global $wpdb;
    $openvote_clean_tables = [
        $wpdb->prefix . 'openvote_polls'            => __( 'Głosowania', 'openvote' ),
        $wpdb->prefix . 'openvote_votes'            => __( 'Oddane głosy', 'openvote' ),
        $wpdb->prefix . 'openvote_groups'           => __( 'Sejmiki', 'openvote' ),
        $wpdb->prefix . 'openvote_group_members'    => __( 'Członkowie sejmików', 'openvote' ),
        $wpdb->prefix . 'openvote_surveys'          => __( 'Ankiety', 'openvote' ),
        $wpdb->prefix . 'openvote_survey_responses' => __( 'Odpowiedzi ankiet', 'openvote' ),
        $wpdb->prefix . 'openvote_email_queue'      => __( 'Kolejka e-mail', 'openvote' ),
    ];
    $openvote_coord_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", 'openvote_role'
    ) );
    ?>

    <?php if ( $openvote_clean_error ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $openvote_clean_error ); ?></p></div>
    <?php endif; ?>
    <?php if ( $openvote_clean_success ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $openvote_clean_success ); ?></p></div>
    <?php endif; ?>

    <div class="openvote-danger-zone">
        <div class="openvote-danger-zone__header">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e( 'Strefa zagrożenia — operacja nieodwracalna', 'openvote' ); ?>
        </div>
        <div class="openvote-danger-zone__body">
            <p><?php esc_html_e( 'Poniższa operacja usuwa wszystkie dane głosowań, ankiet, grup i koordynatorów oraz przywraca ustawienia konfiguracyjne do wartości fabrycznych. Struktura tabel zostaje zachowana. Wtyczka pozostaje aktywna.', 'openvote' ); ?></p>

            <h3><?php esc_html_e( 'Co zostanie wyczyszczone:', 'openvote' ); ?></h3>
            <table class="widefat fixed" style="max-width:560px;margin-bottom:20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Dane', 'openvote' ); ?></th>
                        <th style="width:140px;"><?php esc_html_e( 'Zawartość', 'openvote' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $openvote_clean_tables as $table => $label ) :
                        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
                    ?>
                        <tr>
                            <td><?php echo esc_html( $label ); ?> <code style="font-size:11px;"><?php echo esc_html( $table ); ?></code></td>
                            <td><?php printf( esc_html( _n( '%d rekord', '%d rekordów', $count, 'openvote' ) ), $count ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><?php esc_html_e( 'Koordynatorzy (usermeta)', 'openvote' ); ?></td>
                        <td><?php printf( esc_html( _n( '%d użytkownik', '%d użytkowników', $openvote_coord_count, 'openvote' ) ), $openvote_coord_count ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Opcje konfiguracyjne wtyczki', 'openvote' ); ?></td>
                        <td><?php esc_html_e( 'Reset do domyślnych', 'openvote' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="" id="openvote-clean-form">
                <?php wp_nonce_field( 'openvote_clean_database', 'openvote_clean_nonce' ); ?>

                <label class="openvote-confirm-label">
                    <input type="checkbox" name="openvote_confirm_clean" id="openvote_confirm_clean" value="1">
                    <?php esc_html_e( 'Rozumiem, że operacja jest nieodwracalna i wszystkie dane oraz ustawienia wtyczki zostaną trwale usunięte.', 'openvote' ); ?>
                </label>

                <p style="margin-top:20px;">
                    <button type="submit"
                            id="openvote-clean-btn"
                            class="button openvote-btn-danger"
                            disabled>
                        <span class="dashicons dashicons-trash" style="margin-top:3px;"></span>
                        <?php esc_html_e( 'Wyczyść bazę danych wtyczki i przywróć wartości fabryczne', 'openvote' ); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var cb  = document.getElementById( 'openvote_confirm_clean' );
        var btn = document.getElementById( 'openvote-clean-btn' );
        if ( ! cb || ! btn ) return;
        cb.addEventListener( 'change', function () {
            btn.disabled = ! cb.checked;
        } );
    })();
    </script>

    <hr style="margin-top:32px;margin-bottom:20px;">
    <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Odinstaluj wtyczkę', 'openvote' ); ?></h2>
    <?php include OPENVOTE_PLUGIN_DIR . 'admin/partials/uninstall.php'; ?>

    </div>
