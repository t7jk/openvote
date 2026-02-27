<?php
/**
 * Wirtualna strona głosowania pod adresem z konfiguracji (np. /?glosuj).
 * Używa aktualnie zainstalowanego szablonu WordPress — get_header/get_footer.
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

    /**
     * Sprawdza, czy bieżące żądanie dotyczy strony głosowania.
     */
    public static function is_vote_page(): bool {
        $slug = evoting_get_vote_page_slug();
        if ( $slug === '' ) {
            return false;
        }

        if ( (int) get_query_var( self::QUERY_VAR ) === 1 ) {
            return true;
        }

        $get_val = isset( $_GET[ $slug ] ) ? sanitize_text_field( wp_unslash( $_GET[ $slug ] ) ) : null;
        if ( $get_val !== null && ( $get_val === '' || $get_val === '1' ) ) {
            return true;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $request_uri = preg_replace( '#\?.*$#', '', $request_uri );
        $request_uri = trim( $request_uri, '/' );
        $path_parts  = explode( '/', $request_uri );
        return end( $path_parts ) === $slug;
    }

    /**
     * Dodaje klasę CSS do <body> na stronie głosowania.
     */
    public static function add_body_class( array $classes ): array {
        if ( self::is_vote_page() ) {
            $classes[] = 'evoting-vote-page';
        }
        return $classes;
    }

    /**
     * Zwraca rozmiar czcionki h2 z motywu (Global Styles lub fallback).
     * Priorytet: wp_get_global_styles (motywy blokowe) → theme_mod → fallback.
     *
     * @return string Wartość CSS, np. "1.75rem".
     */
    private static function get_h2_font_size(): string {
        if ( function_exists( 'wp_get_global_styles' ) ) {
            $h2 = wp_get_global_styles( [ 'elements', 'h2', 'typography' ] );
            if ( ! empty( $h2['fontSize'] ) && is_string( $h2['fontSize'] ) ) {
                return $h2['fontSize'];
            }
        }
        $theme_h2 = get_theme_mod( 'h2_font_size', '' );
        if ( $theme_h2 !== '' ) {
            return esc_attr( $theme_h2 );
        }
        return 'clamp(1.25rem, 2.5vw + 0.25rem, 1.75rem)';
    }

    /**
     * Enqueue CSS i JS na stronie głosowania (przez wp_enqueue_scripts).
     */
    public static function enqueue_assets(): void {
        if ( ! self::is_vote_page() ) {
            return;
        }

        wp_enqueue_style(
            'evoting-public',
            EVOTING_PLUGIN_URL . 'public/css/evoting-public.css',
            [],
            EVOTING_VERSION
        );

        // Dopasuj rozmiar zakładek do h2 aktywnego motywu.
        $h2_size = self::get_h2_font_size();
        wp_add_inline_style( 'evoting-public', sprintf(
            '.evoting-tab { font-size: %s; line-height: 1.2; }',
            $h2_size
        ) );
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
                'participation'    => __( 'Frekwencja', 'evoting' ),
                'totalEligible'    => __( 'Uprawnionych do głosowania', 'evoting' ),
                'totalVoters'      => __( 'Uczestniczyło w głosowaniu', 'evoting' ),
                'totalAbsent'      => __( 'Nie uczestniczyło', 'evoting' ),
                'inclAbsent'       => __( 'inc. brak głosu', 'evoting' ),
                'voterList'        => __( 'Głosujący (anonimowo):', 'evoting' ),
                'showVoters'       => __( 'Pokaż głosujących', 'evoting' ),
                'hideVoters'       => __( 'Ukryj głosujących', 'evoting' ),
                'nonVoterList'     => __( 'Nieobecni (nie głosowali):', 'evoting' ),
                'showNonVoters'    => __( 'Pokaż nieobecnych', 'evoting' ),
                'hideNonVoters'    => __( 'Ukryj nieobecnych', 'evoting' ),
                'days'             => __( 'd', 'evoting' ),
                'hours'            => __( 'godz.', 'evoting' ),
                'minutes'          => __( 'min.', 'evoting' ),
                'seconds'          => __( 'sek.', 'evoting' ),
                'ended'            => __( 'Głosowanie zakończone', 'evoting' ),
            ],
        ] );
    }

    /**
     * Ustawia tytuł zakładki przeglądarki dla strony głosowania.
     */
    public static function filter_document_title( string $title ): string {
        if ( self::is_vote_page() ) {
            return __( 'Trwające głosowania', 'evoting' ) . ' &#8212; ' . get_bloginfo( 'name' );
        }
        return $title;
    }

    /**
     * Ukrywa tytuł strony głosowania renderowany przez motyw (np. <h1>Głosowanie</h1>).
     * Działa niezależnie od nazw klas CSS użytych przez aktywny motyw.
     *
     * @param string $title   Tytuł posta.
     * @param int    $post_id ID posta.
     * @return string Pusty string dla strony głosowania, oryginał dla pozostałych.
     */
    public static function suppress_page_title( string $title, int $post_id ): string {
        $slug    = evoting_get_vote_page_slug();
        $wp_page = $slug !== '' ? get_page_by_path( $slug ) : null;
        if ( $wp_page && $post_id === (int) $wp_page->ID ) {
            return '';
        }
        return $title;
    }

    /**
     * Podpina się pod template_include i podmienia szablon WordPressa
     * na wtyczkowy (który wywołuje get_header/get_footer aktywnego motywu).
     *
     * Gdy istnieje prawdziwa strona WP z tym slugiem:
     *  - Jeśli odwiedzamy ją już pod jej właściwym URL → obsługuj normalnie przez WP.
     *  - Jeśli odwiedzamy przez stary URL query-param (?glosuj) → redirect 301.
     */
    public static function filter_template( string $template ): string {
        if ( ! self::is_vote_page() ) {
            return $template;
        }

        $slug    = evoting_get_vote_page_slug();
        $wp_page = get_page_by_path( $slug );

        if ( $wp_page && 'publish' === $wp_page->post_status ) {
            $page_permalink = untrailingslashit( get_permalink( $wp_page ) );
            $request_path   = isset( $_SERVER['REQUEST_URI'] )
                ? strtok( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' )
                : '/';
            $request_path = untrailingslashit( $request_path );

            // Sprawdź, czy jesteśmy już na stronie WP (np. /wordpress/glosuj).
            $page_path = untrailingslashit( (string) wp_parse_url( $page_permalink, PHP_URL_PATH ) );

            if ( $request_path === $page_path ) {
                // Już jesteśmy na właściwym URL — ustaw query i oddaj szablon motywu.
                global $wp_query, $post;
                $wp_query->queried_object    = $wp_page;
                $wp_query->queried_object_id = $wp_page->ID;
                $wp_query->posts             = [ $wp_page ];
                $wp_query->post              = $wp_page;
                $wp_query->found_posts       = 1;
                $wp_query->post_count        = 1;
                $wp_query->is_404            = false;
                $wp_query->is_page           = true;
                $wp_query->is_singular       = true;
                $wp_query->is_home           = false;
                $post = $wp_page;
                setup_postdata( $wp_page );

                // Zwróć szablon strony z motywu.
                $theme_tpl = locate_template( [ 'page.php', 'singular.php', 'index.php' ] );
                return $theme_tpl ?: $template;
            }

            // Inny URL (query-param, stary link) → redirect do kanonicznego URL strony.
            $redirect_url = add_query_arg(
                isset( $_GET['tab'] ) ? [ 'tab' => sanitize_key( $_GET['tab'] ) ] : [],
                $page_permalink
            );
            wp_redirect( $redirect_url, 301 );
            exit;
        }

        // Brak prawdziwej strony WP — użyj wirtualnego szablonu wtyczki.
        require_once EVOTING_PLUGIN_DIR . 'includes/evoting-render-poll.php';

        global $wp_query;
        $wp_query->is_home     = false;
        $wp_query->is_404      = false;
        $wp_query->is_archive  = false;
        $wp_query->is_singular = true;
        $wp_query->is_page     = true;

        return EVOTING_PLUGIN_DIR . 'public/views/vote-page-theme.php';
    }
}
