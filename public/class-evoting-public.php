<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Public {

    public function enqueue_styles(): void {
        if ( ! has_block( 'evoting/poll' ) && ! has_block( 'evoting/survey-responses' ) ) {
            return;
        }

        wp_enqueue_style(
            'evoting-public',
            EVOTING_PLUGIN_URL . 'public/css/evoting-public.css',
            [],
            EVOTING_VERSION
        );
    }

    public function enqueue_scripts(): void {
        if ( ! has_block( 'evoting/poll' ) ) {
            return;
        }

        wp_enqueue_script(
            'evoting-public',
            EVOTING_PLUGIN_URL . 'public/js/evoting-public.js',
            [],
            EVOTING_VERSION,
            true
        );

        wp_localize_script( 'evoting-public', 'evotingPublic', [
            'restUrl' => esc_url_raw( rest_url( 'evoting/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'voteSuccess'      => __( 'Twój głos został zapisany. Dziękujemy!', 'evoting' ),
                'voteError'        => __( 'Wystąpił błąd. Spróbuj ponownie.', 'evoting' ),
                'answerAll'        => __( 'Odpowiedz na wszystkie pytania.', 'evoting' ),
                'chooseVisibility' => __( 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'evoting' ),
                'participation' => __( 'Frekwencja', 'evoting' ),
                'totalEligible' => __( 'Uprawnionych do głosowania', 'evoting' ),
                'totalVoters'   => __( 'Uczestniczyło w głosowaniu', 'evoting' ),
                'totalAbsent'   => __( 'Nie uczestniczyło', 'evoting' ),
                'inclAbsent'    => __( 'inc. brak głosu', 'evoting' ),
                'voterList'     => __( 'Głosujący (anonimowo):', 'evoting' ),
                'showVoters'    => __( 'Pokaż głosujących', 'evoting' ),
                'hideVoters'    => __( 'Ukryj głosujących', 'evoting' ),
                'nonVoterList'  => __( 'Nieobecni (nie głosowali):', 'evoting' ),
                'showNonVoters' => __( 'Pokaż nieobecnych', 'evoting' ),
                'hideNonVoters' => __( 'Ukryj nieobecnych', 'evoting' ),
                'days'          => __( 'd', 'evoting' ),
                'hours'         => __( 'godz.', 'evoting' ),
                'minutes'       => __( 'min.', 'evoting' ),
                'seconds'       => __( 'sek.', 'evoting' ),
                'ended'         => __( 'Głosowanie zakończone', 'evoting' ),
            ],
        ] );
    }
}
