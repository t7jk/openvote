<?php
defined( 'ABSPATH' ) || exit;

$current_map    = Openvote_Field_Map::get();
$available_keys = Openvote_Field_Map::available_keys();
$labels         = Openvote_Field_Map::LABELS;

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
    <h1><?php esc_html_e( 'Konfiguracja', 'openvote' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Konfiguracja została zapisana.', 'openvote' ); ?></p>
        </div>
    <?php endif; ?>
    <?php if ( isset( $_GET['page_created'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Strona głosowania została utworzona. Możesz ją edytować w Strony lub przejść pod skonfigurowany adres.', 'openvote' ); ?></p>
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

    <form method="post" action="" id="openvote-settings-form">
        <?php wp_nonce_field( 'openvote_save_settings', 'openvote_settings_nonce' ); ?>

        <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Nazwa systemu', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Skrót nazwy wyświetlany w menu panelu. Pełna nazwa i logo pobierane są z ustawień WordPress (Ustawienia → Ogólne).', 'openvote' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="openvote_brand_short_name"><?php esc_html_e( 'Skrót nazwy', 'openvote' ); ?></label></th>
                <td>
                    <input type="text" name="openvote_brand_short_name" id="openvote_brand_short_name" value="<?php echo esc_attr( openvote_get_brand_short_name() ); ?>" class="regular-text" style="width:120px;" maxlength="6" placeholder="Open Vote" />
                    <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Do 6 znaków. Domyślnie: Open Vote.', 'openvote' ); ?></p>
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
            <?php
            $mail_method    = openvote_get_mail_method();
            $smtp           = openvote_get_smtp_config();
            $sg_api_key     = openvote_get_sendgrid_api_key();
            $email_batch    = (int) get_option( 'openvote_email_batch_size', 0 );
            $email_delay    = (int) get_option( 'openvote_email_batch_delay', 0 );
            $admin_email    = wp_get_current_user()->user_email;
            ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Metoda wysyłki', 'openvote' ); ?></th>
                <td>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="wordpress"
                               id="openvote_mail_wp"
                               <?php checked( $mail_method, 'wordpress' ); ?> />
                        <strong><?php esc_html_e( 'WordPress (php mail)', 'openvote' ); ?></strong>
                        &nbsp;<span class="description"><?php esc_html_e( 'Bez konfiguracji, zalecane do ~50 odbiorców.', 'openvote' ); ?></span>
                    </label>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="openvote_mail_method" value="smtp"
                               id="openvote_mail_smtp"
                               <?php checked( $mail_method, 'smtp' ); ?> />
                        <strong><?php esc_html_e( 'Zewnętrzny serwer SMTP', 'openvote' ); ?></strong>
                        &nbsp;<span class="description"><?php esc_html_e( 'Gmail, Outlook itp. — zalecane do ~500 odbiorców.', 'openvote' ); ?></span>
                    </label>
                    <label style="display:block;">
                        <input type="radio" name="openvote_mail_method" value="sendgrid"
                               id="openvote_mail_sendgrid"
                               <?php checked( $mail_method, 'sendgrid' ); ?> />
                        <strong><?php esc_html_e( 'SendGrid API', 'openvote' ); ?></strong>
                        &nbsp;<span class="description"><?php esc_html_e( 'HTTP API, port 443 — zalecane dla 500–10 000+ odbiorców.', 'openvote' ); ?></span>
                    </label>
                </td>
            </tr>
        </table>

        <?php /* ── SMTP ── */ ?>
        <div id="openvote-smtp-fields" style="<?php echo $mail_method === 'smtp' ? '' : 'display:none;'; ?>margin-top:0;">
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
                <tr>
                    <th scope="row"><?php esc_html_e( 'Test połączenia', 'openvote' ); ?></th>
                    <td>
                        <button type="button" class="button" id="openvote-smtp-test">
                            <?php esc_html_e( 'Wyślij testowy e-mail', 'openvote' ); ?>
                        </button>
                        <span id="openvote-smtp-test-result" style="margin-left:12px;font-weight:500;"></span>
                        <p class="description" style="margin-top:4px;">
                            <?php printf( esc_html__( 'Testowy e-mail zostanie wysłany na: %s', 'openvote' ), '<strong>' . esc_html( $admin_email ) . '</strong>' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── SendGrid ── */ ?>
        <div id="openvote-sendgrid-fields" style="<?php echo $mail_method === 'sendgrid' ? '' : 'display:none;'; ?>margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja SendGrid API', 'openvote' ); ?></h3>
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
                <tr>
                    <th scope="row"><?php esc_html_e( 'Test połączenia', 'openvote' ); ?></th>
                    <td>
                        <button type="button" class="button" id="openvote-sendgrid-test">
                            <?php esc_html_e( 'Wyślij testowy e-mail', 'openvote' ); ?>
                        </button>
                        <span id="openvote-sendgrid-test-result" style="margin-left:12px;font-weight:500;"></span>
                        <p class="description" style="margin-top:4px;">
                            <?php printf( esc_html__( 'Testowy e-mail zostanie wysłany na: %s', 'openvote' ), '<strong>' . esc_html( $admin_email ) . '</strong>' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── Parametry wysyłki wsadowej (widoczne zawsze) ── */ ?>
        <h3 style="margin:24px 0 8px;padding-left:2px;"><?php esc_html_e( 'Parametry wysyłki wsadowej', 'openvote' ); ?></h3>
        <p class="description" style="max-width:680px;margin:0 0 8px;">
            <?php esc_html_e( 'Kontrola tempa wysyłki e-maili przy dużych grupach odbiorców. Puste pola = wartości domyślne (20 e-maili / 3 s dla WP/SMTP; 100 e-maili / 2 s dla SendGrid).', 'openvote' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="openvote_email_batch_size"><?php esc_html_e( 'Liczba e-maili na partię', 'openvote' ); ?></label></th>
                <td>
                    <input type="number" name="openvote_email_batch_size" id="openvote_email_batch_size"
                           value="<?php echo esc_attr( $email_batch > 0 ? $email_batch : '' ); ?>"
                           class="small-text" min="1" max="1000" placeholder="<?php echo esc_attr( $mail_method === 'sendgrid' ? '100' : '20' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Dla SMTP/WP zalecane 10–50. Dla SendGrid do 1000.', 'openvote' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="openvote_email_batch_delay"><?php esc_html_e( 'Pauza między partiami (sekundy)', 'openvote' ); ?></label></th>
                <td>
                    <input type="number" name="openvote_email_batch_delay" id="openvote_email_batch_delay"
                           value="<?php echo esc_attr( $email_delay > 0 ? $email_delay : '' ); ?>"
                           class="small-text" min="1" max="60" placeholder="<?php echo esc_attr( $mail_method === 'sendgrid' ? '2' : '3' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Czas oczekiwania między partiami w przeglądarce admina.', 'openvote' ); ?></p>
                </td>
            </tr>
        </table>

        <script>
        (function(){
            var radioWp       = document.getElementById('openvote_mail_wp');
            var radioSmtp     = document.getElementById('openvote_mail_smtp');
            var radioSg       = document.getElementById('openvote_mail_sendgrid');
            var smtpBox       = document.getElementById('openvote-smtp-fields');
            var sgBox         = document.getElementById('openvote-sendgrid-fields');
            var batchSizeIn   = document.getElementById('openvote_email_batch_size');
            var batchDelayIn  = document.getElementById('openvote_email_batch_delay');

            function toggleMethod(){
                var isSg   = radioSg   && radioSg.checked;
                var isSmtp = radioSmtp && radioSmtp.checked;
                if(smtpBox) smtpBox.style.display = isSmtp ? '' : 'none';
                if(sgBox)   sgBox.style.display   = isSg   ? '' : 'none';
                if(batchSizeIn)  batchSizeIn.placeholder  = isSg ? '100' : '20';
                if(batchDelayIn) batchDelayIn.placeholder = isSg ? '2'   : '3';
            }
            [radioWp, radioSmtp, radioSg].forEach(function(r){ if(r) r.addEventListener('change', toggleMethod); });

            /* SMTP test */
            var smtpTestBtn = document.getElementById('openvote-smtp-test');
            var smtpTestRes = document.getElementById('openvote-smtp-test-result');
            if(smtpTestBtn){
                smtpTestBtn.addEventListener('click', function(){
                    smtpTestBtn.disabled = true;
                    smtpTestRes.textContent = '<?php echo esc_js( __( 'Wysyłanie…', 'openvote' ) ); ?>';
                    smtpTestRes.style.color = '#666';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action:  'openvote_test_smtp',
                            nonce:   '<?php echo esc_js( wp_create_nonce( 'openvote_test_smtp' ) ); ?>',
                            host:     document.getElementById('openvote_smtp_host').value,
                            port:     document.getElementById('openvote_smtp_port').value,
                            enc:      document.getElementById('openvote_smtp_encryption').value,
                            user:     document.getElementById('openvote_smtp_username').value,
                            pass:     document.getElementById('openvote_smtp_password').value,
                            from:     document.getElementById('openvote_from_email').value,
                        })
                    }).then(r=>r.json()).then(function(d){
                        smtpTestRes.textContent = d.data || d.message || '?';
                        smtpTestRes.style.color = d.success ? 'green' : '#c00';
                    }).catch(function(){
                        smtpTestRes.textContent = '<?php echo esc_js( __( 'Błąd połączenia.', 'openvote' ) ); ?>';
                        smtpTestRes.style.color = '#c00';
                    }).finally(function(){ smtpTestBtn.disabled = false; });
                });
            }

            /* SendGrid test */
            var sgTestBtn = document.getElementById('openvote-sendgrid-test');
            var sgTestRes = document.getElementById('openvote-sendgrid-test-result');
            if(sgTestBtn){
                sgTestBtn.addEventListener('click', function(){
                    sgTestBtn.disabled = true;
                    sgTestRes.textContent = '<?php echo esc_js( __( 'Wysyłanie…', 'openvote' ) ); ?>';
                    sgTestRes.style.color = '#666';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action:   'openvote_test_sendgrid',
                            nonce:    '<?php echo esc_js( wp_create_nonce( 'openvote_test_sendgrid' ) ); ?>',
                            api_key:   document.getElementById('openvote_sendgrid_api_key').value,
                        })
                    }).then(r=>r.json()).then(function(d){
                        sgTestRes.textContent = d.data || d.message || '?';
                        sgTestRes.style.color = d.success ? 'green' : '#c00';
                    }).catch(function(){
                        sgTestRes.textContent = '<?php echo esc_js( __( 'Błąd połączenia.', 'openvote' ) ); ?>';
                        sgTestRes.style.color = '#c00';
                    }).finally(function(){ sgTestBtn.disabled = false; });
                });
            }
        })();
        </script>

        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'URL strony głosowania', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Adres ma postać: adres instalacji + ? + parametr (np. glosuj). Użytkownicy wchodzą pod link pokazany poniżej.', 'openvote' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="openvote_vote_page_slug"><?php esc_html_e( 'Nazwa parametru', 'openvote' ); ?></label></th>
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
                </td>
            </tr>
        </table>

        <!-- ── URL strony ankiet ──────────────────────────────────────────── -->
        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'URL strony ankiet', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Adres publicznej strony z ankietami. Najlepiej stwórz stronę WordPress i wstaw blok "Ankiety (Open Vote)", aby korzystać z edytora Gutenberg.', 'openvote' ); ?>
        </p>
        <?php
        if ( isset( $_GET['survey_page_created'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona ankiet została utworzona.', 'openvote' ); ?></p></div>
        <?php elseif ( isset( $_GET['survey_page_updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona ankiet została zaktualizowana.', 'openvote' ); ?></p></div>
        <?php endif; ?>

        <table class="form-table" role="presentation" style="max-width:780px;">
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
                        <button type="submit" name="openvote_create_survey_page" value="1" class="button">
                            <?php esc_html_e( 'Utwórz stronę ankiet', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php elseif ( class_exists( 'Openvote_Survey_Page' ) && ! Openvote_Survey_Page::page_has_survey_block() ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_update_survey_page" value="1" class="button button-primary">
                            <?php esc_html_e( 'Zaktualizuj stronę ankiet (dodaj blok)', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- ── URL strony zgłoszeń ─────────────────────────────────────────── -->
        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'URL strony zgłoszeń', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Adres publicznej strony ze zgłoszeniami oznaczonymi jako „Nie spam”. Stwórz stronę i wstaw blok „Zgłoszenia (Open Vote)” lub użyj przycisków poniżej.', 'openvote' ); ?>
        </p>
        <?php
        if ( isset( $_GET['submissions_page_created'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona zgłoszeń została utworzona.', 'openvote' ); ?></p></div>
        <?php elseif ( isset( $_GET['submissions_page_updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona zgłoszeń została zaktualizowana.', 'openvote' ); ?></p></div>
        <?php endif; ?>

        <table class="form-table" role="presentation" style="max-width:780px;">
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
                        <button type="submit" name="openvote_create_submissions_page" value="1" class="button">
                            <?php esc_html_e( 'Utwórz stronę zgłoszeń', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php elseif ( ! $has_block ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="openvote_update_submissions_page" value="1" class="button button-primary">
                            <?php esc_html_e( 'Zaktualizuj stronę zgłoszeń (dodaj blok)', 'openvote' ); ?>
                        </button>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'Ustawienie czasu (strefa głosowań)', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Jeśli serwer ma inną strefę czasową niż Twoja, ustaw przesunięcie. Czas po przesunięciu jest używany do sprawdzania okresu głosowania i wyświetlania liczników.', 'openvote' ); ?>
        </p>
        <?php
        $current_offset = openvote_get_time_offset_hours();
        $server_time    = current_time( 'Y-m-d H:i:s' );
        $voting_time    = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
        ?>
        <table class="form-table" role="presentation" style="max-width:780px;">
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

        <!-- ── Szablon e-maila zapraszającego ─────────────────────────────── -->

        <h2 class="title" style="margin-top:36px;"><?php esc_html_e( 'Szablon e-maila zapraszającego', 'openvote' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 16px;">
            <?php esc_html_e(
                'Treść e-maila wysyłanego do uczestników przy starcie głosowania. '
                . 'Pola oznaczone {zmienną} są automatycznie zastępowane danymi głosowania.',
                'openvote'
            ); ?>
        </p>

        <div style="background:#f0f6fc;border:1px solid #c3d9f0;border-radius:4px;padding:12px 16px;max-width:700px;margin-bottom:18px;font-size:12px;color:#3c434a;line-height:1.8;">
            <strong><?php esc_html_e( 'Dostępne zmienne:', 'openvote' ); ?></strong><br>
            <code>{poll_title}</code> — <?php esc_html_e( 'tytuł głosowania', 'openvote' ); ?><br>
            <code>{brand_short}</code> — <?php esc_html_e( 'skrót nazwy systemu (z WP Site Title)', 'openvote' ); ?><br>
            <code>{from_email}</code> — <?php esc_html_e( 'adres e-mail nadawcy (z pola powyżej)', 'openvote' ); ?><br>
            <code>{vote_url}</code> — <?php esc_html_e( 'pełny adres strony głosowania', 'openvote' ); ?><br>
            <code>{date_end}</code> — <?php esc_html_e( 'data i godzina zakończenia głosowania', 'openvote' ); ?><br>
            <code>{questions}</code> — <?php esc_html_e( 'lista pytań z odpowiedziami (automatycznie formatowana)', 'openvote' ); ?>
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
                    <label for="openvote_email_body"><?php esc_html_e( 'Treść', 'openvote' ); ?></label>
                </th>
                <td>
                    <textarea id="openvote_email_body"
                              name="openvote_email_body"
                              rows="14"
                              class="large-text"
                              style="font-family:monospace;font-size:13px;line-height:1.6;"><?php echo esc_textarea( openvote_get_email_body_template() ); ?></textarea>
                    <p class="description" style="margin-top:4px;">
                        <?php esc_html_e( 'Treść wiadomości w formacie tekstowym. Używaj {zmiennych} z listy powyżej.', 'openvote' ); ?>
                    </p>
                    <p style="margin-top:8px;">
                        <button type="button" class="button" id="openvote-email-reset-btn">
                            <?php esc_html_e( 'Przywróć domyślną treść', 'openvote' ); ?>
                        </button>
                    </p>
                </td>
            </tr>
        </table>

        <script>
        document.getElementById('openvote-email-reset-btn').addEventListener('click', function() {
            if (!confirm('<?php echo esc_js( __( 'Przywrócić domyślną treść e-maila? Obecna treść zostanie nadpisana.', 'openvote' ) ); ?>')) return;
            document.getElementById('openvote_email_subject').value = 'Zaproszenie do g\u0142osowania: {poll_title}';
            document.getElementById('openvote_email_from_template').value = '{brand_short} ({from_email})';
            document.getElementById('openvote_email_body').value =
                'Zapraszamy do wzi\u0119cia udzia\u0142u w g\u0142osowaniu pod tytu\u0142em: {poll_title}.\n\n' +
                'G\u0142osowanie jest przeprowadzane na stronie: {vote_url}\n' +
                'i potrwa do: {date_end}.\n\n' +
                'Oto lista pyta\u0144 w g\u0142osowaniu:\n{questions}\n\n' +
                'Zapraszamy do g\u0142osowania!\n' +
                'Zesp\u00f3\u0142 {brand_short}';
        });
        </script>

        <p style="margin-top:16px;">
            <?php submit_button( __( 'Zapisz konfigurację', 'openvote' ), 'primary', 'submit', false ); ?>
        </p>
    </form>

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
        $wpdb->prefix . 'openvote_groups'           => __( 'Grupy', 'openvote' ),
        $wpdb->prefix . 'openvote_group_members'    => __( 'Członkowie grup', 'openvote' ),
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

    <script>
    ( function () {
        var form = document.getElementById( 'openvote-settings-form' );
        if ( form ) {
            form.addEventListener( 'submit', function () {
                var btns = form.querySelectorAll( 'button[type="submit"], input[type="submit"]' );
                for ( var i = 0; i < btns.length; i++ ) {
                    btns[ i ].disabled = true;
                }
            } );
        }
    } )();
    </script>
</div>
