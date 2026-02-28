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

    // Optional fields: first option is "not assigned"
    $optional_fields = [ 'phone', 'pesel', 'id_card', 'address', 'zip_code', 'town' ];
    if ( in_array( $logical, $optional_fields, true ) ) {
        echo '<option value="' . esc_attr( Evoting_Field_Map::NOT_SET_KEY ) . '"' . selected( $current, Evoting_Field_Map::NOT_SET_KEY, false ) . '>';
        echo esc_html__( '— nie określone —', 'evoting' );
        echo '</option>';
    }

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
    <?php if ( isset( $_GET['page_updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Strona głosowania została zaktualizowana — blok zakładek jest aktywny.', 'evoting' ); ?></p>
        </div>
    <?php endif; ?>
    <?php if ( evoting_vote_page_exists() && ! evoting_vote_page_has_tabs_block() ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Strona głosowania wymaga aktualizacji.', 'evoting' ); ?></strong>
                <?php esc_html_e( 'Strona istnieje, ale nie zawiera bloku zakładek (Trwające / Zakończone głosowania).', 'evoting' ); ?>
            </p>
            <form method="post" action="" style="margin-bottom:8px;">
                <?php wp_nonce_field( 'evoting_save_settings', 'evoting_settings_nonce' ); ?>
                <input type="hidden" name="evoting_update_vote_page" value="1" />
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Zaktualizuj stronę głosowania', 'evoting' ); ?>
                </button>
                <span style="margin-left:10px;color:#555;font-size:12px;">
                    <?php esc_html_e( 'Zastąpi treść strony blokiem zakładek. Inne bloki które dodałeś/aś zostaną usunięte.', 'evoting' ); ?>
                </span>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" action="" id="evoting-settings-form">
        <?php wp_nonce_field( 'evoting_save_settings', 'evoting_settings_nonce' ); ?>

        <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Nazwa systemu', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Skrót nazwy wyświetlany w menu panelu. Pełna nazwa i logo pobierane są z ustawień WordPress (Ustawienia → Ogólne).', 'evoting' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="evoting_brand_short_name"><?php esc_html_e( 'Skrót nazwy', 'evoting' ); ?></label></th>
                <td>
                    <input type="text" name="evoting_brand_short_name" id="evoting_brand_short_name" value="<?php echo esc_attr( evoting_get_brand_short_name() ); ?>" class="regular-text" style="width:120px;" maxlength="6" placeholder="EP-RWL" />
                    <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Do 6 znaków. Domyślnie: EP-RWL.', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="evoting_from_email"><?php esc_html_e( 'E-mail nadawcy', 'evoting' ); ?></label></th>
                <td>
                    <input type="email" name="evoting_from_email" id="evoting_from_email"
                           value="<?php echo esc_attr( evoting_get_from_email() ); ?>"
                           class="regular-text" style="max-width:360px;"
                           placeholder="<?php echo esc_attr( 'noreply@' . ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'example.com' ) ); ?>" />
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Adres e-mail, z którego wysyłane są zaproszenia i powiadomienia. Np. noreply@twojadomena.pl', 'evoting' ); ?>
                    </p>
                </td>
            </tr>
            <?php
            $mail_method    = evoting_get_mail_method();
            $smtp           = evoting_get_smtp_config();
            $sg_api_key     = evoting_get_sendgrid_api_key();
            $email_batch    = (int) get_option( 'evoting_email_batch_size', 0 );
            $email_delay    = (int) get_option( 'evoting_email_batch_delay', 0 );
            $admin_email    = wp_get_current_user()->user_email;
            ?>
            <tr>
                <th scope="row"><?php esc_html_e( 'Metoda wysyłki', 'evoting' ); ?></th>
                <td>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="evoting_mail_method" value="wordpress"
                               id="evoting_mail_wp"
                               <?php checked( $mail_method, 'wordpress' ); ?> />
                        <strong><?php esc_html_e( 'WordPress (php mail)', 'evoting' ); ?></strong>
                        &nbsp;<span class="description"><?php esc_html_e( 'Bez konfiguracji, zalecane do ~50 odbiorców.', 'evoting' ); ?></span>
                    </label>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="radio" name="evoting_mail_method" value="smtp"
                               id="evoting_mail_smtp"
                               <?php checked( $mail_method, 'smtp' ); ?> />
                        <strong><?php esc_html_e( 'Zewnętrzny serwer SMTP', 'evoting' ); ?></strong>
                        &nbsp;<span class="description"><?php esc_html_e( 'Gmail, Outlook itp. — zalecane do ~500 odbiorców.', 'evoting' ); ?></span>
                    </label>
                    <label style="display:block;">
                        <input type="radio" name="evoting_mail_method" value="sendgrid"
                               id="evoting_mail_sendgrid"
                               <?php checked( $mail_method, 'sendgrid' ); ?> />
                        <strong><?php esc_html_e( 'SendGrid API', 'evoting' ); ?></strong>
                        &nbsp;<span class="description"><?php esc_html_e( 'HTTP API, port 443 — zalecane dla 500–10 000+ odbiorców.', 'evoting' ); ?></span>
                    </label>
                </td>
            </tr>
        </table>

        <?php /* ── SMTP ── */ ?>
        <div id="evoting-smtp-fields" style="<?php echo $mail_method === 'smtp' ? '' : 'display:none;'; ?>margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja serwera SMTP', 'evoting' ); ?></h3>
            <table class="form-table" role="presentation" style="max-width:780px;">
                <tr>
                    <th scope="row"><label for="evoting_smtp_host"><?php esc_html_e( 'Serwer SMTP (host)', 'evoting' ); ?></label></th>
                    <td>
                        <input type="text" name="evoting_smtp_host" id="evoting_smtp_host"
                               value="<?php echo esc_attr( $smtp['host'] ); ?>"
                               class="regular-text" placeholder="smtp.gmail.com" />
                        <p class="description"><?php esc_html_e( 'Np. smtp.gmail.com, smtp.office365.com', 'evoting' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="evoting_smtp_port"><?php esc_html_e( 'Port', 'evoting' ); ?></label></th>
                    <td>
                        <input type="number" name="evoting_smtp_port" id="evoting_smtp_port"
                               value="<?php echo esc_attr( $smtp['port'] ); ?>"
                               class="small-text" min="1" max="65535" />
                        <p class="description"><?php esc_html_e( 'Najczęstsze: 587 (TLS/STARTTLS), 465 (SSL), 25 (bez szyfrowania)', 'evoting' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Szyfrowanie', 'evoting' ); ?></th>
                    <td>
                        <select name="evoting_smtp_encryption" id="evoting_smtp_encryption">
                            <option value="tls"  <?php selected( $smtp['encryption'], 'tls' ); ?>><?php esc_html_e( 'TLS / STARTTLS (zalecane, port 587)', 'evoting' ); ?></option>
                            <option value="ssl"  <?php selected( $smtp['encryption'], 'ssl' ); ?>><?php esc_html_e( 'SSL (port 465)', 'evoting' ); ?></option>
                            <option value="none" <?php selected( $smtp['encryption'], 'none' ); ?>><?php esc_html_e( 'Brak szyfrowania (niezalecane)', 'evoting' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="evoting_smtp_username"><?php esc_html_e( 'Użytkownik (login)', 'evoting' ); ?></label></th>
                    <td>
                        <input type="text" name="evoting_smtp_username" id="evoting_smtp_username"
                               value="<?php echo esc_attr( $smtp['username'] ); ?>"
                               class="regular-text" autocomplete="username"
                               placeholder="<?php esc_attr_e( 'np. noreply@twojadomena.pl', 'evoting' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="evoting_smtp_password"><?php esc_html_e( 'Hasło', 'evoting' ); ?></label></th>
                    <td>
                        <input type="password" name="evoting_smtp_password" id="evoting_smtp_password"
                               value="<?php echo esc_attr( $smtp['password'] ); ?>"
                               class="regular-text" autocomplete="current-password" />
                        <p class="description"><?php esc_html_e( 'Użyj dedykowanego hasła aplikacji (np. dla Gmail/Outlook).', 'evoting' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Test połączenia', 'evoting' ); ?></th>
                    <td>
                        <button type="button" class="button" id="evoting-smtp-test">
                            <?php esc_html_e( 'Wyślij testowy e-mail', 'evoting' ); ?>
                        </button>
                        <span id="evoting-smtp-test-result" style="margin-left:12px;font-weight:500;"></span>
                        <p class="description" style="margin-top:4px;">
                            <?php printf( esc_html__( 'Testowy e-mail zostanie wysłany na: %s', 'evoting' ), '<strong>' . esc_html( $admin_email ) . '</strong>' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── SendGrid ── */ ?>
        <div id="evoting-sendgrid-fields" style="<?php echo $mail_method === 'sendgrid' ? '' : 'display:none;'; ?>margin-top:0;">
            <h3 style="margin:16px 0 8px;padding-left:2px;"><?php esc_html_e( 'Konfiguracja SendGrid API', 'evoting' ); ?></h3>
            <table class="form-table" role="presentation" style="max-width:780px;">
                <tr>
                    <th scope="row"><label for="evoting_sendgrid_api_key"><?php esc_html_e( 'Klucz API SendGrid', 'evoting' ); ?></label></th>
                    <td>
                        <input type="password" name="evoting_sendgrid_api_key" id="evoting_sendgrid_api_key"
                               value="<?php echo esc_attr( $sg_api_key ); ?>"
                               class="regular-text" placeholder="SG.xxxxxxxxxxxxxxxx" autocomplete="off" />
                        <p class="description">
                            <?php esc_html_e( 'Utwórz klucz w panelu SendGrid → Settings → API Keys → Full Access lub Mail Send.', 'evoting' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Test połączenia', 'evoting' ); ?></th>
                    <td>
                        <button type="button" class="button" id="evoting-sendgrid-test">
                            <?php esc_html_e( 'Wyślij testowy e-mail', 'evoting' ); ?>
                        </button>
                        <span id="evoting-sendgrid-test-result" style="margin-left:12px;font-weight:500;"></span>
                        <p class="description" style="margin-top:4px;">
                            <?php printf( esc_html__( 'Testowy e-mail zostanie wysłany na: %s', 'evoting' ), '<strong>' . esc_html( $admin_email ) . '</strong>' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php /* ── Parametry wysyłki wsadowej (widoczne zawsze) ── */ ?>
        <h3 style="margin:24px 0 8px;padding-left:2px;"><?php esc_html_e( 'Parametry wysyłki wsadowej', 'evoting' ); ?></h3>
        <p class="description" style="max-width:680px;margin:0 0 8px;">
            <?php esc_html_e( 'Kontrola tempa wysyłki e-maili przy dużych grupach odbiorców. Puste pola = wartości domyślne (20 e-maili / 3 s dla WP/SMTP; 100 e-maili / 2 s dla SendGrid).', 'evoting' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="evoting_email_batch_size"><?php esc_html_e( 'Liczba e-maili na partię', 'evoting' ); ?></label></th>
                <td>
                    <input type="number" name="evoting_email_batch_size" id="evoting_email_batch_size"
                           value="<?php echo esc_attr( $email_batch > 0 ? $email_batch : '' ); ?>"
                           class="small-text" min="1" max="1000" placeholder="<?php echo esc_attr( $mail_method === 'sendgrid' ? '100' : '20' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Dla SMTP/WP zalecane 10–50. Dla SendGrid do 1000.', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="evoting_email_batch_delay"><?php esc_html_e( 'Pauza między partiami (sekundy)', 'evoting' ); ?></label></th>
                <td>
                    <input type="number" name="evoting_email_batch_delay" id="evoting_email_batch_delay"
                           value="<?php echo esc_attr( $email_delay > 0 ? $email_delay : '' ); ?>"
                           class="small-text" min="1" max="60" placeholder="<?php echo esc_attr( $mail_method === 'sendgrid' ? '2' : '3' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Czas oczekiwania między partiami w przeglądarce admina.', 'evoting' ); ?></p>
                </td>
            </tr>
        </table>

        <script>
        (function(){
            var radioWp       = document.getElementById('evoting_mail_wp');
            var radioSmtp     = document.getElementById('evoting_mail_smtp');
            var radioSg       = document.getElementById('evoting_mail_sendgrid');
            var smtpBox       = document.getElementById('evoting-smtp-fields');
            var sgBox         = document.getElementById('evoting-sendgrid-fields');
            var batchSizeIn   = document.getElementById('evoting_email_batch_size');
            var batchDelayIn  = document.getElementById('evoting_email_batch_delay');

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
            var smtpTestBtn = document.getElementById('evoting-smtp-test');
            var smtpTestRes = document.getElementById('evoting-smtp-test-result');
            if(smtpTestBtn){
                smtpTestBtn.addEventListener('click', function(){
                    smtpTestBtn.disabled = true;
                    smtpTestRes.textContent = '<?php echo esc_js( __( 'Wysyłanie…', 'evoting' ) ); ?>';
                    smtpTestRes.style.color = '#666';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action:  'evoting_test_smtp',
                            nonce:   '<?php echo esc_js( wp_create_nonce( 'evoting_test_smtp' ) ); ?>',
                            host:     document.getElementById('evoting_smtp_host').value,
                            port:     document.getElementById('evoting_smtp_port').value,
                            enc:      document.getElementById('evoting_smtp_encryption').value,
                            user:     document.getElementById('evoting_smtp_username').value,
                            pass:     document.getElementById('evoting_smtp_password').value,
                            from:     document.getElementById('evoting_from_email').value,
                        })
                    }).then(r=>r.json()).then(function(d){
                        smtpTestRes.textContent = d.data || d.message || '?';
                        smtpTestRes.style.color = d.success ? 'green' : '#c00';
                    }).catch(function(){
                        smtpTestRes.textContent = '<?php echo esc_js( __( 'Błąd połączenia.', 'evoting' ) ); ?>';
                        smtpTestRes.style.color = '#c00';
                    }).finally(function(){ smtpTestBtn.disabled = false; });
                });
            }

            /* SendGrid test */
            var sgTestBtn = document.getElementById('evoting-sendgrid-test');
            var sgTestRes = document.getElementById('evoting-sendgrid-test-result');
            if(sgTestBtn){
                sgTestBtn.addEventListener('click', function(){
                    sgTestBtn.disabled = true;
                    sgTestRes.textContent = '<?php echo esc_js( __( 'Wysyłanie…', 'evoting' ) ); ?>';
                    sgTestRes.style.color = '#666';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action:   'evoting_test_sendgrid',
                            nonce:    '<?php echo esc_js( wp_create_nonce( 'evoting_test_sendgrid' ) ); ?>',
                            api_key:   document.getElementById('evoting_sendgrid_api_key').value,
                        })
                    }).then(r=>r.json()).then(function(d){
                        sgTestRes.textContent = d.data || d.message || '?';
                        sgTestRes.style.color = d.success ? 'green' : '#c00';
                    }).catch(function(){
                        sgTestRes.textContent = '<?php echo esc_js( __( 'Błąd połączenia.', 'evoting' ) ); ?>';
                        sgTestRes.style.color = '#c00';
                    }).finally(function(){ sgTestBtn.disabled = false; });
                });
            }
        })();
        </script>

        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'URL strony głosowania', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Adres ma postać: adres instalacji + ? + parametr (np. glosuj). Użytkownicy wchodzą pod link pokazany poniżej.', 'evoting' ); ?>
        </p>
        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="evoting_vote_page_slug"><?php esc_html_e( 'Nazwa parametru', 'evoting' ); ?></label></th>
                <td>
                    <input type="text" name="evoting_vote_page_slug" id="evoting_vote_page_slug" value="<?php echo esc_attr( evoting_get_vote_page_slug() ); ?>" class="regular-text" style="width:140px;" placeholder="glosuj" />
                    <?php
                    $slug_param = evoting_get_vote_page_slug();
                    $wp_vote_page = get_page_by_path( $slug_param, OBJECT, 'page' );
                    if ( $wp_vote_page && $wp_vote_page->post_status === 'publish' ) {
                        $vote_page_url = get_permalink( $wp_vote_page );
                        $url_note      = __( 'Strona WordPress (możesz ją edytować w Gutenberg):', 'evoting' );
                    } else {
                        $vote_page_url = evoting_get_vote_page_url();
                        $url_note      = __( 'Adres strony głosowania:', 'evoting' );
                    }
                    ?>
                    <p class="description" style="margin-top:6px;">
                        <?php echo esc_html( $url_note ); ?>
                        <strong><a href="<?php echo esc_url( $vote_page_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $vote_page_url ); ?></a></strong>
                        <?php if ( $wp_vote_page ) : ?>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( get_edit_post_link( $wp_vote_page->ID ) ); ?>" target="_blank">
                            <?php esc_html_e( 'Edytuj w Gutenberg', 'evoting' ); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>

        <!-- ── URL strony ankiet ──────────────────────────────────────────── -->
        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'URL strony ankiet', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Adres publicznej strony z ankietami. Najlepiej stwórz stronę WordPress i wstaw blok "Ankiety (E-głosowania)", aby korzystać z edytora Gutenberg.', 'evoting' ); ?>
        </p>
        <?php
        if ( isset( $_GET['survey_page_created'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona ankiet została utworzona.', 'evoting' ); ?></p></div>
        <?php elseif ( isset( $_GET['survey_page_updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona ankiet została zaktualizowana.', 'evoting' ); ?></p></div>
        <?php endif; ?>

        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="evoting_survey_page_slug"><?php esc_html_e( 'Slug strony', 'evoting' ); ?></label></th>
                <td>
                    <input type="text" name="evoting_survey_page_slug" id="evoting_survey_page_slug"
                           value="<?php echo esc_attr( class_exists( 'Evoting_Survey_Page' ) ? Evoting_Survey_Page::get_slug() : get_option( 'evoting_survey_page_slug', 'ankieta' ) ); ?>"
                           class="regular-text" style="width:140px;" placeholder="ankieta" />
                    <?php
                    $surv_slug = class_exists( 'Evoting_Survey_Page' ) ? Evoting_Survey_Page::get_slug() : 'ankieta';
                    $surv_page = get_page_by_path( $surv_slug, OBJECT, 'page' );
                    if ( $surv_page && 'publish' === $surv_page->post_status ) {
                        $surv_url = get_permalink( $surv_page->ID );
                    } else {
                        $surv_url = class_exists( 'Evoting_Survey_Page' ) ? Evoting_Survey_Page::get_url() : home_url( '/' . $surv_slug . '/' );
                    }
                    ?>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Adres strony ankiet:', 'evoting' ); ?>
                        <strong><a href="<?php echo esc_url( $surv_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $surv_url ); ?></a></strong>
                        <?php if ( $surv_page ) : ?>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( get_edit_post_link( $surv_page->ID ) ); ?>" target="_blank">
                            <?php esc_html_e( 'Edytuj w Gutenberg', 'evoting' ); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                    <?php if ( ! $surv_page ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="evoting_create_survey_page" value="1" class="button">
                            <?php esc_html_e( 'Utwórz stronę ankiet', 'evoting' ); ?>
                        </button>
                    </p>
                    <?php elseif ( class_exists( 'Evoting_Survey_Page' ) && ! Evoting_Survey_Page::page_has_survey_block() ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="evoting_update_survey_page" value="1" class="button button-primary">
                            <?php esc_html_e( 'Zaktualizuj stronę ankiet (dodaj blok)', 'evoting' ); ?>
                        </button>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- ── URL strony zgłoszeń ─────────────────────────────────────────── -->
        <h2 class="title" style="margin-top:28px;"><?php esc_html_e( 'URL strony zgłoszeń', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 12px;">
            <?php esc_html_e( 'Adres publicznej strony ze zgłoszeniami oznaczonymi jako „Nie spam”. Stwórz stronę i wstaw blok „Zgłoszenia (E-głosowania)” lub użyj przycisków poniżej.', 'evoting' ); ?>
        </p>
        <?php
        if ( isset( $_GET['submissions_page_created'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona zgłoszeń została utworzona.', 'evoting' ); ?></p></div>
        <?php elseif ( isset( $_GET['submissions_page_updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Strona zgłoszeń została zaktualizowana.', 'evoting' ); ?></p></div>
        <?php endif; ?>

        <table class="form-table" role="presentation" style="max-width:780px;">
            <tr>
                <th scope="row"><label for="evoting_submissions_page_slug"><?php esc_html_e( 'Slug strony', 'evoting' ); ?></label></th>
                <td>
                    <?php $subm_slug = get_option( 'evoting_submissions_page_slug', 'zgloszenia' ); ?>
                    <input type="text" name="evoting_submissions_page_slug" id="evoting_submissions_page_slug"
                           value="<?php echo esc_attr( $subm_slug ); ?>"
                           class="regular-text" style="width:140px;" placeholder="zgloszenia" />
                    <?php
                    $subm_page = get_page_by_path( $subm_slug, OBJECT, 'page' );
                    if ( $subm_page && 'publish' === $subm_page->post_status ) {
                        $subm_url = get_permalink( $subm_page->ID );
                    } else {
                        $subm_url = home_url( '/' . $subm_slug . '/' );
                    }
                    $has_block = class_exists( 'Evoting_Admin_Settings' ) && Evoting_Admin_Settings::page_has_submissions_block( $subm_slug );
                    ?>
                    <p class="description" style="margin-top:6px;">
                        <?php esc_html_e( 'Adres strony zgłoszeń:', 'evoting' ); ?>
                        <strong><a href="<?php echo esc_url( $subm_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $subm_url ); ?></a></strong>
                        <?php if ( $subm_page ) : ?>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url( get_edit_post_link( $subm_page->ID ) ); ?>" target="_blank">
                            <?php esc_html_e( 'Edytuj w Gutenberg', 'evoting' ); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                    <?php if ( ! $subm_page ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="evoting_create_submissions_page" value="1" class="button">
                            <?php esc_html_e( 'Utwórz stronę zgłoszeń', 'evoting' ); ?>
                        </button>
                    </p>
                    <?php elseif ( ! $has_block ) : ?>
                    <p style="margin-top:8px;">
                        <button type="submit" name="evoting_update_submissions_page" value="1" class="button button-primary">
                            <?php esc_html_e( 'Zaktualizuj stronę zgłoszeń (dodaj blok)', 'evoting' ); ?>
                        </button>
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

        <div class="notice notice-warning inline" style="max-width:860px;margin:0 0 16px;padding:12px 16px;">
            <p style="margin:0 0 6px;">
                <strong>⚠ <?php esc_html_e( 'Wymagana wtyczka do rozszerzonych pól profilu', 'evoting' ); ?></strong>
            </p>
            <p style="margin:0 0 8px;color:#3c3c3c;">
                <?php esc_html_e(
                    'Pola takie jak Telefon, PESEL czy Numer dowodu osobistego nie istnieją domyślnie w WordPress. '
                    . 'Aby użytkownicy mogli je uzupełniać, potrzebujesz wtyczki, która dodaje własne pola rejestracji i profilu — np.:',
                    'evoting'
                ); ?>
            </p>
            <ul style="margin:0 0 8px;padding-left:20px;list-style:disc;color:#3c3c3c;">
                <li>
                    <a href="https://wordpress.org/plugins/user-registration/" target="_blank" rel="noopener noreferrer">
                        <strong>User Registration &amp; Membership</strong>
                    </a>
                    <?php esc_html_e( '— darmowa, 60 000+ aktywnych instalacji, obsługuje własne pola i powiązuje je z usermeta.', 'evoting' ); ?>
                </li>
                <li>
                    <?php esc_html_e( 'Lub każda inna wtyczka zapisująca dane w', 'evoting' ); ?>
                    <code>wp_usermeta</code>
                    <?php esc_html_e( 'pod dowolnym kluczem (meta_key) — wpisz ten klucz w kolumnie poniżej.', 'evoting' ); ?>
                </li>
            </ul>
            <p style="margin:0;font-size:12px;color:#777;">
                <?php esc_html_e(
                    'Jeśli dana wtyczka zapisuje numer telefonu jako „user_gsm", wpisz „user_gsm" jako klucz dla pola Telefon.',
                    'evoting'
                ); ?>
            </p>
        </div>

        <table class="widefat fixed evoting-settings-table" style="max-width:960px;">
            <thead>
                <tr>
                    <th style="width:220px;"><?php esc_html_e( 'Pole logiczne', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Klucz w bazie danych WordPress', 'evoting' ); ?></th>
                    <th style="width:110px;text-align:center;"><?php esc_html_e( 'Wymagane', 'evoting' ); ?><br><span style="font-weight:400;font-size:11px;color:#888;"><?php esc_html_e( 'do głosowania', 'evoting' ); ?></span></th>
                    <th style="width:110px;text-align:center;"><?php esc_html_e( 'Wymagane', 'evoting' ); ?><br><span style="font-weight:400;font-size:11px;color:#888;"><?php esc_html_e( 'do ankiety', 'evoting' ); ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $labels as $logical => $label ) :
                    $current_val      = $current_map[ $logical ] ?? Evoting_Field_Map::DEFAULTS[ $logical ];
                    $is_core          = Evoting_Field_Map::is_core_field( $current_val );
                    $always_req       = in_array( $logical, Evoting_Field_Map::ALWAYS_REQUIRED, true );
                    $is_req           = Evoting_Field_Map::is_required( $logical );
                    $survey_always    = in_array( $logical, Evoting_Field_Map::SURVEY_ALWAYS_REQUIRED, true );
                    $is_survey_req    = Evoting_Field_Map::is_survey_required( $logical );
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $label ); ?></strong>
                        <?php if ( 'email' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Zazwyczaj user_email (pole wbudowane)', 'evoting' ); ?>
                            </span>
                        <?php elseif ( 'phone' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_gsm', 'evoting' ); ?>
                            </span>
                        <?php elseif ( 'pesel' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_pesel', 'evoting' ); ?>
                            </span>
                        <?php elseif ( 'id_card' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_id', 'evoting' ); ?>
                            </span>
                        <?php elseif ( 'address' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_address', 'evoting' ); ?>
                            </span>
                        <?php elseif ( 'zip_code' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_zip', 'evoting' ); ?>
                            </span>
                        <?php elseif ( 'town' === $logical ) : ?>
                            <br><span class="description" style="font-size:11px;">
                                <?php esc_html_e( 'Domyślnie: user_city', 'evoting' ); ?>
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
                        <br>
                        <?php if ( $current_val === Evoting_Field_Map::NOT_SET_KEY ) : ?>
                            <span class="evoting-badge" style="background:#f0f0f1;color:#787c82;"><?php esc_html_e( 'nie określone', 'evoting' ); ?></span>
                        <?php elseif ( 'city' === $logical && $current_val === Evoting_Field_Map::NO_CITY_KEY ) : ?>
                            <code style="margin-top:4px;display:inline-block;"><?php esc_html_e( 'Nie używaj miast', 'evoting' ); ?></code>
                            <span class="evoting-badge evoting-badge--meta"><?php esc_html_e( 'grupa Wszyscy', 'evoting' ); ?></span>
                        <?php else : ?>
                            <code style="margin-top:4px;display:inline-block;"><?php echo esc_html( $current_val ); ?></code>
                            <?php if ( $is_core ) : ?>
                                <span class="evoting-badge evoting-badge--core"><?php esc_html_e( 'wbudowane', 'evoting' ); ?></span>
                            <?php else : ?>
                                <span class="evoting-badge evoting-badge--meta"><?php esc_html_e( 'usermeta', 'evoting' ); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;vertical-align:middle;">
                        <?php if ( $always_req ) : ?>
                            <span class="evoting-always-required-box" title="<?php esc_attr_e( 'To pole jest zawsze wymagane', 'evoting' ); ?>" aria-label="<?php esc_attr_e( 'zawsze wymagane', 'evoting' ); ?>">
                                <span class="evoting-always-required-box__check" aria-hidden="true">✓</span>
                            </span>
                            <br><span style="font-size:11px;color:#888;"><?php esc_html_e( 'zawsze', 'evoting' ); ?></span>
                        <?php else : ?>
                            <input type="checkbox"
                                   name="evoting_required_fields[]"
                                   value="<?php echo esc_attr( $logical ); ?>"
                                   <?php checked( $is_req ); ?>>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;vertical-align:middle;">
                        <?php if ( $survey_always ) : ?>
                            <span class="evoting-always-required-box" title="<?php esc_attr_e( 'To pole jest zawsze wymagane dla ankiet', 'evoting' ); ?>" aria-label="<?php esc_attr_e( 'zawsze wymagane', 'evoting' ); ?>">
                                <span class="evoting-always-required-box__check" aria-hidden="true">✓</span>
                            </span>
                            <br><span style="font-size:11px;color:#888;"><?php esc_html_e( 'zawsze', 'evoting' ); ?></span>
                        <?php else : ?>
                            <input type="checkbox"
                                   name="evoting_survey_required_fields[]"
                                   value="<?php echo esc_attr( $logical ); ?>"
                                   <?php checked( $is_survey_req ); ?>>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ── Szablon e-maila zapraszającego ─────────────────────────────── -->

        <h2 class="title" style="margin-top:36px;"><?php esc_html_e( 'Szablon e-maila zapraszającego', 'evoting' ); ?></h2>
        <p class="description" style="max-width:700px;margin:8px 0 16px;">
            <?php esc_html_e(
                'Treść e-maila wysyłanego do uczestników przy starcie głosowania. '
                . 'Pola oznaczone {zmienną} są automatycznie zastępowane danymi głosowania.',
                'evoting'
            ); ?>
        </p>

        <div style="background:#f0f6fc;border:1px solid #c3d9f0;border-radius:4px;padding:12px 16px;max-width:700px;margin-bottom:18px;font-size:12px;color:#3c434a;line-height:1.8;">
            <strong><?php esc_html_e( 'Dostępne zmienne:', 'evoting' ); ?></strong><br>
            <code>{poll_title}</code> — <?php esc_html_e( 'tytuł głosowania', 'evoting' ); ?><br>
            <code>{brand_short}</code> — <?php esc_html_e( 'skrót nazwy systemu (z WP Site Title)', 'evoting' ); ?><br>
            <code>{from_email}</code> — <?php esc_html_e( 'adres e-mail nadawcy (z pola powyżej)', 'evoting' ); ?><br>
            <code>{vote_url}</code> — <?php esc_html_e( 'pełny adres strony głosowania', 'evoting' ); ?><br>
            <code>{date_end}</code> — <?php esc_html_e( 'data i godzina zakończenia głosowania', 'evoting' ); ?><br>
            <code>{questions}</code> — <?php esc_html_e( 'lista pytań z odpowiedziami (automatycznie formatowana)', 'evoting' ); ?>
        </div>

        <table class="form-table" style="max-width:760px;">
            <tr>
                <th scope="row" style="width:160px;">
                    <label for="evoting_email_subject"><?php esc_html_e( 'Temat', 'evoting' ); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="evoting_email_subject"
                           name="evoting_email_subject"
                           value="<?php echo esc_attr( evoting_get_email_subject_template() ); ?>"
                           class="large-text"
                           placeholder="<?php esc_attr_e( 'Zaproszenie do głosowania: {poll_title}', 'evoting' ); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="evoting_email_from_template"><?php esc_html_e( 'Od (nazwa nadawcy)', 'evoting' ); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="evoting_email_from_template"
                           name="evoting_email_from_template"
                           value="<?php echo esc_attr( evoting_get_email_from_template() ); ?>"
                           class="large-text"
                           placeholder="<?php esc_attr_e( '{brand_short} ({from_email})', 'evoting' ); ?>" />
                    <p class="description" style="margin-top:4px;">
                        <?php esc_html_e( 'Nazwa wyświetlana jako nadawca w kliencie pocztowym. Adres e-mail pochodzi z pola "E-mail nadawcy" powyżej.', 'evoting' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row" style="vertical-align:top;padding-top:12px;">
                    <label for="evoting_email_body"><?php esc_html_e( 'Treść', 'evoting' ); ?></label>
                </th>
                <td>
                    <textarea id="evoting_email_body"
                              name="evoting_email_body"
                              rows="14"
                              class="large-text"
                              style="font-family:monospace;font-size:13px;line-height:1.6;"><?php echo esc_textarea( evoting_get_email_body_template() ); ?></textarea>
                    <p class="description" style="margin-top:4px;">
                        <?php esc_html_e( 'Treść wiadomości w formacie tekstowym. Używaj {zmiennych} z listy powyżej.', 'evoting' ); ?>
                    </p>
                    <p style="margin-top:8px;">
                        <button type="button" class="button" id="evoting-email-reset-btn">
                            <?php esc_html_e( 'Przywróć domyślną treść', 'evoting' ); ?>
                        </button>
                    </p>
                </td>
            </tr>
        </table>

        <script>
        document.getElementById('evoting-email-reset-btn').addEventListener('click', function() {
            if (!confirm('<?php echo esc_js( __( 'Przywrócić domyślną treść e-maila? Obecna treść zostanie nadpisana.', 'evoting' ) ); ?>')) return;
            document.getElementById('evoting_email_subject').value = 'Zaproszenie do g\u0142osowania: {poll_title}';
            document.getElementById('evoting_email_from_template').value = '{brand_short} ({from_email})';
            document.getElementById('evoting_email_body').value =
                'Zapraszamy do wzi\u0119cia udzia\u0142u w g\u0142osowaniu pod tytu\u0142em: {poll_title}.\n\n' +
                'G\u0142osowanie jest przeprowadzane na stronie: {vote_url}\n' +
                'i potrwa do: {date_end}.\n\n' +
                'Oto lista pyta\u0144 w g\u0142osowaniu:\n{questions}\n\n' +
                'Zapraszamy do g\u0142osowania!\n' +
                'Zesp\u00f3\u0142 {brand_short}';
        });
        </script>

        <p style="margin-top:16px;">
            <?php submit_button( __( 'Zapisz konfigurację', 'evoting' ), 'primary', 'submit', false ); ?>
        </p>
    </form>

    <hr>
    <h2 style="font-size:14px;"><?php esc_html_e( 'Jak to działa?', 'evoting' ); ?></h2>
    <ul style="list-style:disc;padding-left:20px;max-width:700px;color:#555;font-size:13px;">
        <li><?php esc_html_e( 'Użytkownik jest uprawniony do głosowania tylko jeśli wszystkie pola oznaczone jako "Wymagane" są wypełnione.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Pole "Nazwa miasta" służy do definiowania grup docelowych głosowania.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Pola wbudowane (np. user_email) są odczytywane z tabeli wp_users.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Pozostałe pola są odczytywane z tabeli wp_usermeta.', 'evoting' ); ?></li>
        <li><?php esc_html_e( 'Po zmianie konfiguracji wszystkie nowe i istniejące głosowania używają nowych kluczy.', 'evoting' ); ?></li>
    </ul>

    <hr style="margin-top:32px;margin-bottom:20px;">
    <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Odinstaluj wtyczkę', 'evoting' ); ?></h2>
    <?php include EVOTING_PLUGIN_DIR . 'admin/partials/uninstall.php'; ?>

    <script>
    ( function () {
        var form = document.getElementById( 'evoting-settings-form' );
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
