<?php
/**
 * Publiczna wirtualna strona ankiet.
 * Dostępna pod adresem /{slug}/ (domyślnie /ankieta/).
 *
 * Jeśli istnieje WordPress Page z zawartością bloku evoting/survey-form,
 * WordPress renderuje ją normalnie przez motyw.
 * Klasa pełni rolę fallback + dostarcza helper get_url().
 */
defined( 'ABSPATH' ) || exit;

class Evoting_Survey_Page {

    const DEFAULT_SLUG = 'ankieta';
    const QUERY_VAR    = 'evoting_survey_page';
    const BLOCK_NAME   = 'evoting/survey-form';

    // ── Helpers ─────────────────────────────────────────────────────────────

    public static function get_slug(): string {
        $s = get_option( 'evoting_survey_page_slug', self::DEFAULT_SLUG );
        return sanitize_title( $s ?: self::DEFAULT_SLUG );
    }

    public static function get_url(): string {
        // Najpierw spróbuj URL WordPress Page.
        $slug = self::get_slug();
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $page && 'publish' === $page->post_status ) {
            return get_permalink( $page->ID );
        }
        return home_url( '/' . $slug . '/' );
    }

    // ── WordPress hooks ─────────────────────────────────────────────────────

    public static function register_query_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function add_rewrite_rule(): void {
        $slug = preg_quote( self::get_slug(), '/' );
        add_rewrite_rule(
            '^' . $slug . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    public static function filter_template( string $template ): string {
        global $wp_query;

        if ( ! get_query_var( self::QUERY_VAR ) ) {
            return $template;
        }

        // Jeśli istnieje WordPress Page o tym slugu i zawiera blok — pozwól WP ją renderować.
        $slug = self::get_slug();
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $page && has_block( self::BLOCK_NAME, $page ) ) {
            $wp_query->is_404     = false;
            $wp_query->is_page    = true;
            $wp_query->post       = $page;
            $wp_query->posts      = [ $page ];
            $wp_query->queried_object    = $page;
            $wp_query->queried_object_id = $page->ID;
            status_header( 200 );
            // Zezwól na domyślny routing WP (found_template).
            return locate_template( [ 'page.php', 'singular.php', 'index.php' ] ) ?: $template;
        }

        // Fallback: własny szablon.
        $wp_query->is_404  = false;
        $wp_query->is_page = true;
        status_header( 200 );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_document_title' ] );

        return EVOTING_PLUGIN_DIR . 'public/views/survey-page.php';
    }

    public static function add_body_class( array $classes ): array {
        if ( get_query_var( self::QUERY_VAR ) ) {
            $classes[] = 'evoting-survey-page';
        }
        return $classes;
    }

    public static function filter_document_title( string $title ): string {
        return __( 'Ankiety', 'evoting' ) . ' — ' . get_bloginfo( 'name' );
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style(
            'evoting-public',
            EVOTING_PLUGIN_URL . 'public/css/evoting-public.css',
            [],
            EVOTING_VERSION
        );
        wp_enqueue_script(
            'evoting-survey-public',
            EVOTING_PLUGIN_URL . 'public/js/survey-public.js',
            [],
            EVOTING_VERSION,
            true
        );
        wp_localize_script( 'evoting-survey-public', 'evotingSurveyPublic', [
            'restUrl' => esc_url_raw( rest_url( 'evoting/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'loggedIn' => is_user_logged_in() ? 1 : 0,
            'i18n'    => [
                'saving'     => __( 'Zapisywanie…', 'evoting' ),
                'savedDraft' => __( 'Szkic zapisany.', 'evoting' ),
                'savedReady' => __( 'Odpowiedź gotowa! Dziękujemy.', 'evoting' ),
                'error'      => __( 'Wystąpił błąd. Spróbuj ponownie.', 'evoting' ),
            ],
        ] );
    }

    /**
     * Sprawdź czy istnieje WordPress Page z blokiem ankiet.
     */
    public static function page_exists(): bool {
        $slug = self::get_slug();
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        return $page && 'publish' === $page->post_status;
    }

    public static function page_has_survey_block(): bool {
        $slug = self::get_slug();
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        return $page && has_block( self::BLOCK_NAME, $page );
    }
}
