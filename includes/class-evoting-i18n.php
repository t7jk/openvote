<?php
defined( 'ABSPATH' ) || exit;

/**
 * Internationalization: Polish when WordPress locale is pl_PL (or pl_*), English otherwise.
 * Loads evoting-en_US.mo for any non-Polish locale so that the UI displays in English.
 */
class Evoting_I18n {

    public function load_plugin_textdomain(): void {
        $rel_path = dirname( EVOTING_PLUGIN_BASENAME ) . '/languages';
        load_plugin_textdomain( 'evoting', false, $rel_path );

        // Gdy język WordPress to nie polski — załaduj tłumaczenie angielskie, aby interfejs był po angielsku.
        $locale = get_locale();
        $is_polish = ( $locale === 'pl_PL' || $locale === 'pl' || strpos( $locale, 'pl_' ) === 0 );
        if ( ! $is_polish ) {
            $mopath = EVOTING_PLUGIN_DIR . 'languages/evoting-en_US.mo';
            if ( file_exists( $mopath ) ) {
                load_textdomain( 'evoting', $mopath );
            }
        }
    }
}
