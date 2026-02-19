<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin {

    private const CAP     = 'edit_others_posts';
    private const CAP_MGR = 'manage_options';

    public function add_menu_pages(): void {
        add_menu_page(
            __( 'EP-RWL', 'evoting' ),
            __( 'EP-RWL', 'evoting' ),
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
            __( 'Grupy użytkowników', 'evoting' ),
            __( 'Grupy', 'evoting' ),
            self::CAP,
            'evoting-groups',
            [ $this, 'render_groups_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Role i uprawnienia', 'evoting' ),
            __( 'Role', 'evoting' ),
            self::CAP_MGR,
            'evoting-roles',
            [ $this, 'render_roles_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Konfiguracja bazy danych', 'evoting' ),
            __( 'Konfiguracja', 'evoting' ),
            self::CAP_MGR,
            'evoting-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Odinstaluj wtyczkę', 'evoting' ),
            __( 'Odinstaluj', 'evoting' ),
            self::CAP_MGR,
            'evoting-uninstall',
            [ $this, 'render_uninstall_page' ]
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

        // Single-poll delete via GET (nonce from wp_nonce_url in row actions).
        if ( 'delete' === $action && $poll_id && current_user_can( 'manage_options' ) ) {
            check_admin_referer( 'evoting_delete_poll_' . $poll_id );
            Evoting_Poll::delete( $poll_id );
            wp_safe_redirect( admin_url( 'admin.php?page=evoting&deleted=1' ) );
            exit;
        }

        // WP_List_Table — polls listing.
        $list_table = new Evoting_Polls_List();
        $list_table->process_bulk_action();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Głosowania', 'evoting' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting-new' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Dodaj nowe', 'evoting' ); ?>
            </a>

            <?php if ( isset( $_GET['bulk_deleted'] ) ) : ?>
                <?php $count = absint( $_GET['bulk_deleted'] ); ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( sprintf( _n( 'Usunięto %d głosowanie.', 'Usunięto %d głosowań.', $count, 'evoting' ), $count ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało usunięte.', 'evoting' ); ?>
                </p></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="page" value="evoting">
                <?php
                $list_table->search_box( __( 'Szukaj głosowania', 'evoting' ), 'evoting-poll' );
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_poll_form_page(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $poll = null;
        include EVOTING_PLUGIN_DIR . 'admin/partials/poll-form.php';
    }

    public function render_groups_page(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        include EVOTING_PLUGIN_DIR . 'admin/partials/groups.php';
    }

    public function render_roles_page(): void {
        if ( ! current_user_can( self::CAP_MGR ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        include EVOTING_PLUGIN_DIR . 'admin/partials/roles.php';
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( self::CAP_MGR ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        include EVOTING_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    public function render_uninstall_page(): void {
        if ( ! current_user_can( self::CAP_MGR ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        include EVOTING_PLUGIN_DIR . 'admin/partials/uninstall.php';
    }

    public function render_brand_header(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! str_contains( $screen->id, 'evoting' ) ) {
            return;
        }
        ?>
        <div class="evoting-brand-header">
            <span class="evoting-brand-header__icon dashicons dashicons-groups"></span>
            <span class="evoting-brand-header__title">EP-RWL</span>
            <span class="evoting-brand-header__sep">·</span>
            <span class="evoting-brand-header__subtitle">E-Parlament Ruch Wolnych Ludzi</span>
        </div>
        <?php
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

        // Batch progress — tylko na stronie Grup.
        if ( str_contains( $hook, 'evoting-groups' ) ) {
            wp_enqueue_script(
                'evoting-batch-progress',
                EVOTING_PLUGIN_URL . 'assets/js/batch-progress.js',
                [],
                EVOTING_VERSION,
                true
            );
            wp_localize_script( 'evoting-batch-progress', 'evotingBatch', [
                'apiRoot' => esc_url_raw( rest_url( 'evoting/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ] );
        }
    }

    private function is_plugin_page( string $hook ): bool {
        return str_contains( $hook, 'evoting' );
    }
}
