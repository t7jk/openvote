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

    /**
     * Przekierowanie ze starego adresu evoting-new na page=evoting&action=new.
     */
    public function redirect_evoting_new(): void {
        if ( isset( $_GET['page'] ) && 'evoting-new' === sanitize_text_field( $_GET['page'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=evoting&action=new' ) );
            exit;
        }
    }

    /**
     * Akcje GET (duplikat, zakończ, uruchom, usuń) — wykonywane w admin_init,
     * zanim cokolwiek zostanie wysłane, żeby przekierowanie działało.
     */
    public function handle_evoting_get_actions(): void {
        if ( ! isset( $_GET['page'] ) || 'evoting' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }

        $action  = sanitize_text_field( $_GET['action'] ?? '' );
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;

        if ( ! $poll_id || ! in_array( $action, [ 'delete', 'end', 'start', 'duplicate' ], true ) ) {
            return;
        }

        if ( 'delete' === $action ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            check_admin_referer( 'evoting_delete_poll_' . $poll_id );
            Evoting_Poll::delete( $poll_id );
            wp_safe_redirect( admin_url( 'admin.php?page=evoting&deleted=1' ) );
            exit;
        }

        if ( 'end' === $action && current_user_can( self::CAP ) ) {
            check_admin_referer( 'evoting_end_poll_' . $poll_id );
            $poll = Evoting_Poll::get( $poll_id );
            if ( $poll && 'open' === $poll->status ) {
                Evoting_Poll::update( $poll_id, [
                    'status'   => 'closed',
                    'date_end' => current_time( 'Y-m-d H:i:s' ),
                ] );
                wp_safe_redirect( admin_url( 'admin.php?page=evoting&poll_ended=1' ) );
                exit;
            }
        }

        if ( 'start' === $action && current_user_can( self::CAP ) ) {
            check_admin_referer( 'evoting_start_poll_' . $poll_id );
            $poll = Evoting_Poll::get( $poll_id );
            if ( $poll && 'draft' === $poll->status ) {
                $now      = current_time( 'Y-m-d H:i:s' );
                $date_end = ( $poll->date_end && $poll->date_end >= $now ) ? $poll->date_end : $now;
                Evoting_Poll::update( $poll_id, [
                    'status'     => 'open',
                    'date_start' => $now,
                    'date_end'   => $date_end,
                ] );
                wp_safe_redirect( add_query_arg( 'started', 1, admin_url( 'admin.php?page=evoting' ) ) );
                exit;
            }
        }

        if ( 'duplicate' === $action && current_user_can( self::CAP ) ) {
            check_admin_referer( 'evoting_duplicate_poll_' . $poll_id );
            $new_id = Evoting_Poll::duplicate( $poll_id );
            if ( false !== $new_id ) {
                wp_safe_redirect( add_query_arg( [ 'duplicated' => 1, 'edit_poll_id' => $new_id ], admin_url( 'admin.php?page=evoting' ) ) );
                exit;
            }
            wp_safe_redirect( add_query_arg( 'duplicate_error', 1, admin_url( 'admin.php?page=evoting' ) ) );
            exit;
        }
    }

    public function render_polls_page(): void {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $action  = sanitize_text_field( $_GET['action'] ?? '' );
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;

        if ( 'new' === $action ) {
            $poll = null;
            include EVOTING_PLUGIN_DIR . 'admin/partials/poll-form.php';
            return;
        }

        if ( 'edit' === $action && $poll_id ) {
            $poll = Evoting_Poll::get( $poll_id );
            if ( $poll ) {
                if ( 'draft' !== $poll->status ) {
                    wp_safe_redirect( add_query_arg( 'edit_locked', 1, admin_url( 'admin.php?page=evoting' ) ) );
                    exit;
                }
                include EVOTING_PLUGIN_DIR . 'admin/partials/poll-form.php';
                return;
            }
        }

        if ( 'view' === $action && $poll_id ) {
            $poll = Evoting_Poll::get( $poll_id );
            if ( $poll && in_array( $poll->status, [ 'open', 'closed' ], true ) ) {
                $is_read_only = true;
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

        // Akcje GET (delete, end, start, duplicate) są obsługiwane w admin_init — handle_evoting_get_actions().

        // WP_List_Table — polls listing.
        $list_table = new Evoting_Polls_List();
        $list_table->process_bulk_action();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Głosowania', 'evoting' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=new' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Dodaj nowe', 'evoting' ); ?>
            </a>

            <?php if ( isset( $_GET['bulk_deleted'] ) ) : ?>
                <?php $count = absint( $_GET['bulk_deleted'] ); ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( sprintf( _n( 'Usunięto %d głosowanie.', 'Usunięto %d głosowań.', $count, 'evoting' ), $count ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['started'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało uruchomione.', 'evoting' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['bulk_started'] ) ) : ?>
                <?php $count = absint( $_GET['bulk_started'] ); ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( sprintf( _n( 'Rozpoczęto %d głosowanie.', 'Rozpoczęto %d głosowań.', $count, 'evoting' ), $count ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['bulk_ended'] ) ) : ?>
                <?php $count = absint( $_GET['bulk_ended'] ); ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( sprintf( _n( 'Zakończono %d głosowanie.', 'Zakończono %d głosowań.', $count, 'evoting' ), $count ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało usunięte.', 'evoting' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Zmiany zostały zapisane.', 'evoting' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['poll_ended'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało zakończone.', 'evoting' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['edit_locked'] ) ) : ?>
                <div class="notice notice-warning is-dismissible"><p>
                    <?php esc_html_e( 'Nie można edytować głosowania, które zostało rozpoczęte lub zakończone. Tylko szkice są edytowalne.', 'evoting' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['duplicated'] ) ) : ?>
                <?php
                $edit_poll_id = isset( $_GET['edit_poll_id'] ) ? absint( $_GET['edit_poll_id'] ) : 0;
                $edit_url     = $edit_poll_id ? admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $edit_poll_id . '&duplicated=1' ) : '';
                ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Utworzono kopię głosowania. Znajdziesz ją na liście jako szkic.', 'evoting' ); ?>
                    <?php if ( $edit_url ) : ?>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary" style="margin-left:10px;">
                            <?php esc_html_e( 'Edytuj skopiowane głosowanie', 'evoting' ); ?>
                        </a>
                    <?php endif; ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['duplicate_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible"><p>
                    <?php esc_html_e( 'Nie udało się skopiować głosowania. Upewnij się, że głosowanie ma pytania.', 'evoting' ); ?>
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
