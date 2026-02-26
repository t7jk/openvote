<?php
/**
 * Wirtualna strona głosowania pod adresem z konfiguracji (np. /glosuj).
 * Rejestruje regułę przekierowania i obsługuje żądanie bez szablonu WordPress.
 */
defined( 'ABSPATH' ) || exit;

class Evoting_Vote_Page {

    const QUERY_VAR = 'evoting_vote';

    public static function register_query_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function add_rewrite_rule(): void {
        $slug = evoting_get_vote_page_slug();
        if ( $slug === '' ) {
            return;
        }
        add_rewrite_rule(
            $slug . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    public static function maybe_serve_vote_page(): void {
        if ( ! get_query_var( self::QUERY_VAR ) ) {
            return;
        }

        require_once EVOTING_PLUGIN_DIR . 'includes/evoting-render-poll.php';

        $vote_page_url = evoting_get_vote_page_url();
        $css_url       = EVOTING_PLUGIN_URL . 'public/css/evoting-public.css';
        $js_url        = EVOTING_PLUGIN_URL . 'public/js/evoting-public.js';

        wp_enqueue_style( 'evoting-public', $css_url, [], EVOTING_VERSION );
        wp_enqueue_script( 'evoting-public', $js_url, [], EVOTING_VERSION, true );
        $vote_page_url = evoting_get_vote_page_url();

        wp_localize_script( 'evoting-public', 'evotingPublic', [
            'restUrl' => esc_url_raw( rest_url( 'evoting/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'voteSuccess'      => __( 'Twój głos został zapisany. Dziękujemy!', 'evoting' ),
                'voteError'        => __( 'Wystąpił błąd. Spróbuj ponownie.', 'evoting' ),
                'answerAll'        => __( 'Odpowiedz na wszystkie pytania.', 'evoting' ),
                'chooseVisibility' => __( 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'evoting' ),
                'participation'    => __( 'Frekwencja', 'evoting' ),
                'totalEligible'    => __( 'Uprawnionych do głosowania', 'evoting' ),
                'totalVoters'      => __( 'Uczestniczyło w głosowaniu', 'evoting' ),
                'totalAbsent'      => __( 'Nie uczestniczyło', 'evoting' ),
                'inclAbsent'       => __( 'inc. brak głosu', 'evoting' ),
                'voterList'        => __( 'Głosujący (anonimowo):', 'evoting' ),
                'showVoters'       => __( 'Pokaż głosujących', 'evoting' ),
                'hideVoters'       => __( 'Ukryj głosujących', 'evoting' ),
                'days'             => __( 'd', 'evoting' ),
                'hours'            => __( 'godz.', 'evoting' ),
                'minutes'          => __( 'min.', 'evoting' ),
                'seconds'          => __( 'sek.', 'evoting' ),
                'ended'            => __( 'Głosowanie zakończone', 'evoting' ),
            ],
        ] );

        include EVOTING_PLUGIN_DIR . 'public/views/vote-page.php';
        exit;
    }
}
