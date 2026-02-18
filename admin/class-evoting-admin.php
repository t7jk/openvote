<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin {

    private const CAP = 'edit_others_posts';

    public function add_menu_pages(): void {
        add_menu_page(
            __( 'Demokratyczne Głosowanie', 'evoting' ),
            __( 'Dem. Głosowanie', 'evoting' ),
            self::CAP,
            'evoting',
            [ $this, 'render_polls_page' ],
            'dashicons-yes-alt',
            30
        );

        add_submenu_page(
            'evoting',
            __( 'Głosowania', 'evoting' ),
            __( 'Głosowania', 'evoting' ),
            self::CAP,
            'evoting',
            [ $this, 'render_polls_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Dodaj głosowanie', 'evoting' ),
            __( 'Dodaj nowe', 'evoting' ),
            self::CAP,
            'evoting-new',
            [ $this, 'render_poll_form_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Konfiguracja bazy danych', 'evoting' ),
            __( 'Konfiguracja', 'evoting' ),
            'manage_options',
            'evoting-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_polls_page(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $action  = sanitize_text_field( $_GET['action'] ?? '' );
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;

        if ( 'edit' === $action && $poll_id ) {
            $poll = Evoting_Poll::get( $poll_id );
            if ( $poll ) {
                include EVOTING_PLUGIN_DIR . 'admin/partials/poll-form.php';
                return;
            }
        }

        if ( 'results' === $action && $poll_id ) {
            $poll = Evoting_Poll::get( $poll_id );
            if ( $poll ) {
                $results = Evoting_Vote::get_results( $poll_id );
                $voters  = Evoting_Vote::get_voters_admin( $poll_id );
                include EVOTING_PLUGIN_DIR . 'admin/partials/poll-results.php';
                return;
            }
        }

        include EVOTING_PLUGIN_DIR . 'admin/partials/poll-list.php';
    }

    public function render_poll_form_page(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $poll = null;
        include EVOTING_PLUGIN_DIR . 'admin/partials/poll-form.php';
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        include EVOTING_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    public function enqueue_styles( string $hook ): void {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_style(
            'evoting-admin',
            EVOTING_PLUGIN_URL . 'admin/css/evoting-admin.css',
            [],
            EVOTING_VERSION
        );
    }

    public function enqueue_scripts( string $hook ): void {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_script(
            'evoting-admin',
            EVOTING_PLUGIN_URL . 'admin/js/evoting-admin.js',
            [],
            EVOTING_VERSION,
            true
        );
    }

    private function is_plugin_page( string $hook ): bool {
        return str_contains( $hook, 'evoting' );
    }
}
