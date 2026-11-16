<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Public {

    public function enqueue_styles(): void {
        if ( ! has_block( 'openvote/poll' ) && ! has_block( 'openvote/survey-responses' ) ) {
            return;
        }

        wp_enqueue_style(
            'openvote-public',
            OPENVOTE_PLUGIN_URL . 'public/css/openvote-public.css',
            [],
            OPENVOTE_VERSION
        );
    }

    public function enqueue_scripts(): void {
        if ( ! has_block( 'openvote/poll' ) ) {
            return;
        }

        wp_enqueue_script(
            'openvote-public',
            OPENVOTE_PLUGIN_URL . 'public/js/openvote-public.js',
            [ 'openvote-identity' ],
            OPENVOTE_VERSION,
            true
        );

        wp_localize_script( 'openvote-public', 'openvotePublic', [
            'restUrl' => esc_url_raw( rest_url( 'openvote/v1' ) ),
            'nonce'   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
            'i18n'    => [
                'voteSuccess'      => __( 'Twój głos został zapisany. Dziękujemy!', 'openvote' ),
                'voteError'        => __( 'Wystąpił błąd. Spróbuj ponownie.', 'openvote' ),
                'answerAll'        => __( 'Odpowiedz na wszystkie pytania.', 'openvote' ),
                'chooseVisibility' => __( 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'openvote' ),
                'participation' => __( 'Frekwencja', 'openvote' ),
                'totalEligible' => __( 'Uprawnionych do głosowania', 'openvote' ),
                'totalVoters'   => __( 'Uczestniczyło w głosowaniu', 'openvote' ),
                'totalAbsent'   => __( 'Nie głosowało', 'openvote' ),
                'inclAbsent'     => __( 'wliczając osoby, które nie głosowały', 'openvote' ),
                'abstainedLabel' => __( 'Wstrzymało się', 'openvote' ),
                'voterList'     => __( 'Głosujący (anonimowo):', 'openvote' ),
                'showVoters'    => __( 'Pokaż głosujących', 'openvote' ),
                'hideVoters'    => __( 'Ukryj głosujących', 'openvote' ),
                'nonVoterList'  => __( 'Osoby, które nie głosowały:', 'openvote' ),
                'showNonVoters' => __( 'Pokaż osoby, które nie głosowały', 'openvote' ),
                'hideNonVoters' => __( 'Ukryj osoby, które nie głosowały', 'openvote' ),
                'days'          => __( 'd', 'openvote' ),
                'hours'         => __( 'godz.', 'openvote' ),
                'minutes'       => __( 'min.', 'openvote' ),
                'seconds'       => __( 'sek.', 'openvote' ),
                'ended'         => __( 'Głosowanie zakończone', 'openvote' ),
        'hintGuest'        => __( 'Aby oddać głos, najpierw zaloguj się lub potwierdź adres e-mail (ramka wyżej).', 'openvote' ),
        'hintQ1'           => __( 'Zaznacz odpowiedź w pytaniu %s.', 'openvote' ),
        'hintQn'           => __( 'Zaznacz odpowiedzi w pytaniach: %s.', 'openvote' ),
        'hintReady'        => __( 'Wszystko gotowe. Naciśnij „Oddaj głos”.', 'openvote' ),
        'saving'           => __( 'Zapisuję głos…', 'openvote' ),
        'networkError'     => __( 'Brak połączenia. Sprawdź internet i spróbuj ponownie. Jeśli Twój głos został już zapisany, po odświeżeniu strony zobaczysz informację o tym.', 'openvote' ),
        'sessionExpired'   => __( 'Sesja wygasła. Odśwież stronę (klawisz F5) i spróbuj ponownie.', 'openvote' ),
        'badResponse'      => __( 'Serwer nie odpowiedział poprawnie. Odśwież stronę (klawisz F5). Jeśli Twój głos został zapisany, zobaczysz tam odpowiednią informację.', 'openvote' ),
        'voteSuccessTitle' => __( 'Dziękujemy! Twój głos został zapisany.', 'openvote' ),
        'voteSuccessNote'  => __( 'Możesz zamknąć tę stronę.', 'openvote' ),
        'alreadyVoted'     => __( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'openvote' ),
        'endedReload'      => __( 'Głosowanie zakończyło się. Odśwież stronę, aby zobaczyć wyniki.', 'openvote' ),
        'mostVotes'        => __( 'najwięcej głosów', 'openvote' ),
        'resultsFailed'    => __( 'Nie udało się pobrać wyników.', 'openvote' ),
        'retry'            => __( 'Spróbuj ponownie', 'openvote' ),
            ],
        ] );
    }
}
