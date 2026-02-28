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
            [],
            OPENVOTE_VERSION,
            true
        );

        wp_localize_script( 'openvote-public', 'openvotePublic', [
            'restUrl' => esc_url_raw( rest_url( 'openvote/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'voteSuccess'      => __( 'Twój głos został zapisany. Dziękujemy!', 'openvote' ),
                'voteError'        => __( 'Wystąpił błąd. Spróbuj ponownie.', 'openvote' ),
                'answerAll'        => __( 'Odpowiedz na wszystkie pytania.', 'openvote' ),
                'chooseVisibility' => __( 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'openvote' ),
                'participation' => __( 'Frekwencja', 'openvote' ),
                'totalEligible' => __( 'Uprawnionych do głosowania', 'openvote' ),
                'totalVoters'   => __( 'Uczestniczyło w głosowaniu', 'openvote' ),
                'totalAbsent'   => __( 'Nie uczestniczyło', 'openvote' ),
                'inclAbsent'    => __( 'inc. brak głosu', 'openvote' ),
                'voterList'     => __( 'Głosujący (anonimowo):', 'openvote' ),
                'showVoters'    => __( 'Pokaż głosujących', 'openvote' ),
                'hideVoters'    => __( 'Ukryj głosujących', 'openvote' ),
                'nonVoterList'  => __( 'Nieobecni (nie głosowali):', 'openvote' ),
                'showNonVoters' => __( 'Pokaż nieobecnych', 'openvote' ),
                'hideNonVoters' => __( 'Ukryj nieobecnych', 'openvote' ),
                'days'          => __( 'd', 'openvote' ),
                'hours'         => __( 'godz.', 'openvote' ),
                'minutes'       => __( 'min.', 'openvote' ),
                'seconds'       => __( 'sek.', 'openvote' ),
                'ended'         => __( 'Głosowanie zakończone', 'openvote' ),
            ],
        ] );
    }
}
