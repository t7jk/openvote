<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin_Settings {

    private const CAP   = 'manage_options';
    private const NONCE = 'evoting_save_settings';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['evoting_settings_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( self::NONCE, 'evoting_settings_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $raw_map = (array) ( $_POST['evoting_field_map'] ?? [] );
        Evoting_Field_Map::save( $raw_map );

        $slug = isset( $_POST['evoting_vote_page_slug'] ) ? sanitize_title( wp_unslash( $_POST['evoting_vote_page_slug'] ) ) : '';
        $slug = $slug !== '' ? $slug : 'glosuj';
        update_option( 'evoting_vote_page_slug', $slug, false );
        flush_rewrite_rules();

        $query_args = [ 'page' => 'evoting-settings', 'saved' => '1' ];

        if ( ! empty( $_POST['evoting_create_vote_page'] ) && ! evoting_vote_page_exists() ) {
            $page_id = self::create_vote_page( $slug );
            if ( $page_id && ! is_wp_error( $page_id ) ) {
                $query_args['page_created'] = '1';
            }
        }

        wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Tworzy stronę z blokiem głosowań pod podanym slugiem.
     *
     * @param string $slug Slug strony (np. „glosuj”).
     * @return int|WP_Error ID utworzonej strony lub błąd.
     */
    public static function create_vote_page( string $slug ) {
        $block_content = '<!-- wp:evoting/poll {"pollId":0} /-->';
        return wp_insert_post(
            [
                'post_type'    => 'page',
                'post_title'   => _x( 'Głosowanie', 'vote page title', 'evoting' ),
                'post_name'    => $slug,
                'post_content' => $block_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ],
            true
        );
    }
}
