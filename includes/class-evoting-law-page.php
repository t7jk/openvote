<?php
/**
 * Publiczna strona przepisów prawnych wtyczki.
 *
 * Rejestruje wirtualną stronę dostępną dla wszystkich odwiedzających
 * pod adresem /{slug}/ (domyślnie /przepisy/).
 * Treść strony jest zapisana w opcji evoting_law_content i edytowana
 * z poziomu panelu admina (E-głosowania → Przepisy).
 */
defined( 'ABSPATH' ) || exit;

class Evoting_Law_Page {

    /** Domyślny slug publicznej strony przepisów. */
    const DEFAULT_SLUG = 'przepisy';

    /** Klucz WP Rewrite Query Var. */
    const QUERY_VAR = 'evoting_law';

    // ── Public helpers ──────────────────────────────────────────────────────

    /**
     * Zwraca slug strony przepisów (domyślnie "przepisy").
     */
    public static function get_slug(): string {
        $s = get_option( 'evoting_law_slug', self::DEFAULT_SLUG );
        return sanitize_title( $s ?: self::DEFAULT_SLUG );
    }

    /**
     * Zwraca pełny URL publicznej strony przepisów.
     */
    public static function get_url(): string {
        return home_url( '/' . self::get_slug() . '/' );
    }

    // ── WordPress hooks ─────────────────────────────────────────────────────

    /**
     * Rejestruje query var "evoting_law" w WP.
     *
     * @param string[] $vars
     * @return string[]
     */
    public static function register_query_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * Dodaje regułę przepisywania URL dla publicznej strony przepisów.
     */
    public static function add_rewrite_rule(): void {
        $slug = preg_quote( self::get_slug(), '/' );
        add_rewrite_rule(
            '^' . $slug . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    /**
     * Obsługuje żądanie wirtualnej strony przepisów.
     * Podpięte pod filter "template_include".
     *
     * @param string $template
     * @return string
     */
    public static function filter_template( string $template ): string {
        global $wp_query;

        if ( ! get_query_var( self::QUERY_VAR ) ) {
            return $template;
        }

        // Zablokuj 404.
        $wp_query->is_404  = false;
        $wp_query->is_page = true;
        status_header( 200 );

        // Szukaj szablonu wtyczki i renderuj go.
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_document_title' ] );

        return EVOTING_PLUGIN_DIR . 'public/views/law-page.php';
    }

    /**
     * Dodaje klasę CSS do body na stronie przepisów.
     *
     * @param string[] $classes
     * @return string[]
     */
    public static function add_body_class( array $classes ): array {
        if ( get_query_var( self::QUERY_VAR ) ) {
            $classes[] = 'evoting-law-page';
        }
        return $classes;
    }

    /**
     * Ustawia tytuł zakładki przeglądarki.
     *
     * @param string $title
     * @return string
     */
    public static function filter_document_title( string $title ): string {
        return __( 'Przepisy prawne', 'evoting' ) . ' — ' . get_bloginfo( 'name' );
    }

    /**
     * Rejestruje style.
     */
    public static function enqueue_assets(): void {
        wp_enqueue_style(
            'evoting-public',
            EVOTING_PLUGIN_URL . 'public/css/evoting-public.css',
            [],
            EVOTING_VERSION
        );
    }
}
