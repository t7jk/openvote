<?php
/**
 * Frontend: głosowanie / ankieta bez logowania (weryfikacja adresu e-mail kodem).
 * Rejestracja wspólnego modułu JS panelu, link do bieżącej strony, nagłówki no-cache.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Bieżący adres strony (do powrotu po zalogowaniu).
 */
function openvote_identity_current_url(): string {
    $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( $host === '' || $uri === '' ) {
        return home_url( '/' );
    }
    return esc_url_raw( set_url_scheme( '//' . $host . $uri ) );
}

/**
 * Rejestruje (nie kolejkuje) wspólny skrypt panelu + dane lokalizacyjne.
 * Skrypt ładuje się tylko, gdy zależy od niego inny skrypt lub został zakolejkowany.
 */
function openvote_identity_register_assets(): void {
    $logged = is_user_logged_in();
    wp_register_script(
        'openvote-identity',
        OPENVOTE_PLUGIN_URL . 'public/js/openvote-identity.js',
        [],
        OPENVOTE_VERSION,
        true
    );
    $here = openvote_identity_current_url();
    wp_localize_script( 'openvote-identity', 'openvoteIdentity', [
        'restUrl'     => esc_url_raw( rest_url( 'openvote/v1' ) ),
        'loggedIn'    => $logged ? 1 : 0,
        // Nonce wyłącznie dla zalogowanych — anonimowy nonce powoduje 403 rest_cookie_invalid_nonce.
        'nonce'       => $logged ? wp_create_nonce( 'wp_rest' ) : '',
        'loginUrl'    => wp_login_url( $here ),
        'lostUrl'     => wp_lostpassword_url(),
        'i18n'        => [
            'titlePoll'     => __( 'Jak chcesz oddać głos?', 'openvote' ),
            'titleSurvey'   => __( 'Jak chcesz wypełnić ankietę?', 'openvote' ),
            'login'         => __( 'Zaloguj się', 'openvote' ),
            'lost'          => __( 'Nie pamiętam hasła', 'openvote' ),
            'orWithout'     => __( 'Głosuj bez logowania — potwierdź adres e-mail', 'openvote' ),
            'orWithoutSurvey' => __( 'Wypełnij bez logowania — potwierdź adres e-mail', 'openvote' ),
            'emailLabel'    => __( 'Adres e-mail', 'openvote' ),
            'emailRequired' => __( 'Podaj poprawny adres e-mail.', 'openvote' ),
            'next'          => __( 'Wyślij kod', 'openvote' ),
            'sending'       => __( 'Wysyłanie…', 'openvote' ),
            'checking'      => __( 'Sprawdzanie…', 'openvote' ),
            'notFound'      => __( 'Nie znaleźliśmy konta z tym adresem. Sprawdź pisownię.', 'openvote' ),
            'confirmAbove'  => __( 'Potwierdź e-mail powyżej, aby głosować.', 'openvote' ),
            'confirmAboveSurvey' => __( 'Potwierdź e-mail powyżej, aby wypełnić ankietę.', 'openvote' ),
            'goToPanel'     => __( 'Przejdź do potwierdzenia e-maila', 'openvote' ),
            'codeValid'     => __( 'Kod jest ważny 15 minut.', 'openvote' ),
            'emailHint'     => __( 'np. jan@poczta.pl', 'openvote' ),
            'explain'       => __( 'Kod potwierdza, że to Twój adres e-mail. Nie zakładamy nowego konta.', 'openvote' ),
            'networkError'  => __( 'Brak połączenia z internetem. Sprawdź połączenie i spróbuj ponownie.', 'openvote' ),
            'wrongCodeKeep' => __( 'Popraw kod i naciśnij „Potwierdź”.', 'openvote' ),
            'attempts'      => __( 'Pozostało prób: %1$d z %2$d', 'openvote' ),
            'codeSent'      => __( 'Wysłaliśmy 4-cyfrowy kod na podany adres. Wpisz go poniżej. Nie widzisz wiadomości? Sprawdź folder SPAM.', 'openvote' ),
            'codeLabel'     => __( 'Kod z e-maila', 'openvote' ),
            'codeRequired'  => __( 'Wpisz 4-cyfrowy kod.', 'openvote' ),
            'confirm'       => __( 'Potwierdź', 'openvote' ),
            'resendIn'      => __( 'Wyślij ponownie e-mail za %d s', 'openvote' ),
            'resend'        => __( 'Wyślij ponownie e-mail', 'openvote' ),
            'changeEmail'   => __( 'Zmień adres e-mail', 'openvote' ),
            'lockedPrefix'  => __( 'Wykorzystano %1$d z %2$d prób.', 'openvote' ),
            'or'            => __( 'lub', 'openvote' ),
            'lockedText'    => __( 'Twój głos nie zostanie policzony do wyników. W każdej chwili możesz się zalogować lub skorzystać z opcji »Nie pamiętam hasła«, odzyskać dostęp do konta i oddać głos, dopóki głosowanie jest aktywne.', 'openvote' ),
            'lockedSurvey'  => __( 'Twoja odpowiedź nie zostanie zapisana. W każdej chwili możesz się zalogować lub skorzystać z opcji »Nie pamiętam hasła«, odzyskać dostęp do konta i wypełnić ankietę, dopóki jest aktywna.', 'openvote' ),
            'votingAs'      => __( 'Głosujesz jako %s', 'openvote' ),
            'answeringAs'   => __( 'Wypełniasz ankietę jako %s', 'openvote' ),
            'hasVoted'      => __( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'openvote' ),
            'error'         => __( 'Wystąpił błąd. Spróbuj ponownie.', 'openvote' ),
            'expired'       => __( 'Weryfikacja wygasła — potwierdź adres e-mail ponownie.', 'openvote' ),
            'loginHint'     => __( 'Zaloguj się, aby kontynuować.', 'openvote' ),
        ],
    ] );
}

/**
 * Oznacza bieżące żądanie jako niecache'owalne (LiteSpeed / proxy) dla gości.
 */
function openvote_identity_send_nocache(): void {
    if ( is_user_logged_in() || headers_sent() ) {
        return;
    }
    do_action( 'litespeed_control_set_nocache', 'openvote poll' );
    nocache_headers();
}

/**
 * Hook template_redirect: strony z głosowaniami/ankietami nie mogą być cache'owane dla gości
 * (w HTML jest m.in. stan formularzy, a REST wymaga braku przeterminowanego nonce).
 */
function openvote_identity_maybe_nocache(): void {
    if ( is_user_logged_in() ) {
        return;
    }
    $apply = false;
    if ( class_exists( 'Openvote_Vote_Page' ) && Openvote_Vote_Page::is_vote_page() ) {
        $apply = true;
    } elseif ( class_exists( 'Openvote_Survey_Page' ) && get_query_var( Openvote_Survey_Page::QUERY_VAR ) ) {
        $apply = true;
    } elseif ( is_singular() ) {
        $post = get_post();
        if ( $post && ( has_block( 'openvote/poll', $post ) || has_block( 'openvote/voting-tabs', $post ) || has_block( 'openvote/survey-form', $post ) ) ) {
            $apply = true;
        }
    }
    if ( $apply ) {
        openvote_identity_send_nocache();
    }
}

/**
 * Kolejkuje wspólny skrypt panelu (np. gdy renderujemy głosowanie dla gościa).
 */
function openvote_identity_enqueue(): void {
    wp_enqueue_script( 'openvote-identity' );
    wp_enqueue_style(
        'openvote-public',
        OPENVOTE_PLUGIN_URL . 'public/css/openvote-public.css',
        [],
        OPENVOTE_VERSION
    );
}

/**
 * Miejsce na panel weryfikacji (wypełniane przez openvote-identity.js).
 */
function openvote_identity_render_slot( string $type, int $id ): void {
    openvote_identity_enqueue();
    ?>
    <div class="openvote-identity" data-target-type="<?php echo esc_attr( $type ); ?>" data-target-id="<?php echo esc_attr( $id ); ?>">
        <noscript>
            <p class="openvote-identity__noscript">
                <a class="openvote-identity__btn" href="<?php echo esc_url( wp_login_url( openvote_identity_current_url() ) ); ?>"><?php esc_html_e( 'Zaloguj się', 'openvote' ); ?></a>
            </p>
        </noscript>
    </div>
    <?php
}
