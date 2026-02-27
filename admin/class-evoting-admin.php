<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin {

    private const CAP     = 'edit_others_posts';
    private const CAP_MGR = 'manage_options';

    public function add_menu_pages(): void {
        $menu_icon = evoting_get_logo_url();
        if ( $menu_icon === '' ) {
            $menu_icon = 'dashicons-yes-alt';
        }
        add_menu_page(
            evoting_get_brand_short_name(),
            evoting_get_brand_short_name(),
            'read',
            'evoting',
            [ $this, 'render_polls_page' ],
            $menu_icon,
            30
        );

        add_submenu_page(
            'evoting',
            __( 'Głosowania', 'evoting' ),
            __( 'Głosowania', 'evoting' ),
            'read',
            'evoting',
            [ $this, 'render_polls_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Grupy użytkowników', 'evoting' ),
            __( 'Grupy', 'evoting' ),
            'read',
            'evoting-groups',
            [ $this, 'render_groups_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Koordynatorzy', 'evoting' ),
            __( 'Koordynatorzy', 'evoting' ),
            'read',
            'evoting-roles',
            [ $this, 'render_roles_page' ]
        );

        add_submenu_page(
            'evoting',
            __( 'Konfiguracja', 'evoting' ),
            __( 'Konfiguracja', 'evoting' ),
            'read',
            'evoting-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Czy użytkownik ma dostęp do zakładki Koordynatorzy (Administrator / Editor / Author lub Koordynator dowolnej grupy).
     */
    public static function user_can_access_coordinators(): bool {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return false;
        }
        $allowed_wp_roles = [ 'administrator', 'editor', 'author' ];
        if ( array_intersect( $allowed_wp_roles, (array) $user->roles ) ) {
            return true;
        }
        $evoting_role = Evoting_Role_Manager::get_user_role( $user->ID );
        if ( Evoting_Role_Manager::ROLE_POLL_ADMIN === $evoting_role ) {
            return true;
        }
        if ( Evoting_Role_Manager::ROLE_POLL_EDITOR === $evoting_role && ! empty( Evoting_Role_Manager::get_user_groups( $user->ID ) ) ) {
            return true;
        }
        return false;
    }

    /**
     * W pasku admina (niebieski pasek): logo obok nazwy strony, gdy jest ustawione w Konfiguracji.
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function customize_admin_bar_logo( WP_Admin_Bar $wp_admin_bar ): void {
        $logo_url = evoting_get_logo_url();
        if ( $logo_url === '' ) {
            return;
        }
        $node = $wp_admin_bar->get_node( 'site-name' );
        if ( ! $node || empty( $node->title ) ) {
            return;
        }
        $img = '<img src="' . esc_url( $logo_url ) . '" alt="" class="evoting-admin-bar-logo" style="width:20px;height:20px;vertical-align:middle;margin-right:6px;border-radius:2px;" />';
        $wp_admin_bar->add_node( [
            'id'    => 'site-name',
            'title' => $img . $node->title,
        ] );
    }

    /**
     * Dla użytkowników bez dostępu: menu EP-RWL w kursywie i nieaktywne.
     * Dla samych Koordynatorów (bez Admin/Editor/Author): tylko zakładka Koordynatorzy jest klikalna.
     */
    public function style_menu_for_restricted_roles(): void {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return;
        }
        $has_wp_role = (bool) array_intersect( [ 'administrator', 'editor', 'author' ], (array) $user->roles );
        $can_access_coordinators = self::user_can_access_coordinators();

        if ( $has_wp_role ) {
            return;
        }
        if ( ! $can_access_coordinators ) {
            global $menu;
            if ( is_array( $menu ) ) {
                foreach ( $menu as $key => $item ) {
                    if ( isset( $item[2] ) && 'evoting' === $item[2] ) {
                        $menu[ $key ][4] = ( isset( $item[4] ) ? $item[4] . ' ' : '' ) . 'evoting-menu-disabled';
                        break;
                    }
                }
            }
        }
    }

    /**
     * Slugi podstron, które mają być wyłączone (kursywa, brak kliku) dla użytkownika będącego tylko Koordynatorem.
     *
     * @return string[] Slugi do wyłączenia lub pustą tablicę.
     */
    public static function get_disabled_submenu_slugs_for_coordinator_only(): array {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return [];
        }
        $has_wp_role = (bool) array_intersect( [ 'administrator', 'editor', 'author' ], (array) $user->roles );
        if ( $has_wp_role ) {
            return [];
        }
        if ( self::user_can_access_coordinators() ) {
            return [ 'evoting-settings' ];
        }
        return [];
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
     * Pobieranie wyników głosowania jako PDF (admin_init, priorytet 1).
     */
    public function handle_results_pdf_download(): void {
        if ( ! isset( $_GET['page'] ) || 'evoting' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }
        if ( ! isset( $_GET['action'] ) || 'results' !== sanitize_text_field( $_GET['action'] ) ) {
            return;
        }
        if ( empty( $_GET['evoting_pdf'] ) || ! current_user_can( self::CAP ) ) {
            return;
        }
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;
        if ( ! $poll_id ) {
            return;
        }
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'evoting_results_pdf_' . $poll_id ) ) {
            wp_die( esc_html__( 'Link wygasł lub jest nieprawidłowy.', 'evoting' ) );
        }
        $poll = Evoting_Poll::get( $poll_id );
        if ( ! $poll ) {
            wp_die( esc_html__( 'Głosowanie nie istnieje.', 'evoting' ) );
        }
        $results         = Evoting_Vote::get_results( $poll_id );
        $voters          = Evoting_Vote::get_voters_admin( $poll_id );
        $non_voters_list = Evoting_Vote::get_non_voters( $poll_id );
        Evoting_Results_Pdf::output_download( $poll, $results, $voters, $non_voters_list );
        exit;
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

        if ( 'end' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
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

        if ( 'start' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
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

        if ( 'duplicate' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
            check_admin_referer( 'evoting_duplicate_poll_' . $poll_id );
            $new_id = Evoting_Poll::duplicate( $poll_id );
            if ( false !== $new_id ) {
                wp_safe_redirect( add_query_arg( [ 'action' => 'edit', 'poll_id' => $new_id, 'duplicated' => 1 ], admin_url( 'admin.php?page=evoting' ) ) );
                exit;
            }
            wp_safe_redirect( add_query_arg( 'duplicate_error', 1, admin_url( 'admin.php?page=evoting' ) ) );
            exit;
        }
    }

    public function render_polls_page(): void {
        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
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
                $results          = Evoting_Vote::get_results( $poll_id );
                $voters           = Evoting_Vote::get_voters_admin( $poll_id );
                $non_voters_list  = Evoting_Vote::get_non_voters( $poll_id );
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

            <?php if ( isset( $_GET['created'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało utworzone.', 'evoting' ); ?>
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
        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $poll = null;
        include EVOTING_PLUGIN_DIR . 'admin/partials/poll-form.php';
    }

    public function render_groups_page(): void {
        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        include EVOTING_PLUGIN_DIR . 'admin/partials/groups.php';
    }

    public function render_roles_page(): void {
        if ( ! current_user_can( self::CAP_MGR ) && ! self::user_can_access_coordinators() ) {
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

    public function render_brand_header(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! str_contains( $screen->id, 'evoting' ) ) {
            return;
        }
        $logo_url = evoting_get_logo_url();
        ?>
        <div class="evoting-brand-header">
            <?php if ( $logo_url !== '' ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="evoting-brand-header__icon evoting-brand-header__icon--img" width="32" height="32" />
            <?php else : ?>
                <span class="evoting-brand-header__icon dashicons dashicons-groups"></span>
            <?php endif; ?>
            <span class="evoting-brand-header__title"><?php echo esc_html( evoting_get_brand_short_name() ); ?></span>
            <span class="evoting-brand-header__sep">·</span>
            <span class="evoting-brand-header__subtitle"><?php echo esc_html( evoting_get_brand_full_name() ); ?></span>
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

    /**
     * Skrypt i style ograniczające menu — na każdej stronie admina (żeby podmenu EP-RWL było poprawnie stylowane).
     */
    public function enqueue_menu_restrict_script( string $hook ): void {
        wp_enqueue_style(
            'evoting-admin',
            EVOTING_PLUGIN_URL . 'admin/css/evoting-admin.css',
            [],
            EVOTING_VERSION
        );
        wp_enqueue_script(
            'evoting-menu',
            EVOTING_PLUGIN_URL . 'admin/js/evoting-menu.js',
            [],
            EVOTING_VERSION,
            true
        );
        wp_localize_script( 'evoting-menu', 'evotingMenu', [
            'disableSubmenuSlugs' => self::get_disabled_submenu_slugs_for_coordinator_only(),
        ] );
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

        // Media picker (logo, banner) — tylko na stronie Konfiguracji.
        if ( str_contains( $hook, 'evoting-settings' ) ) {
            wp_enqueue_media();
            wp_register_script(
                'evoting-settings-media',
                '',
                [ 'jquery', 'media-editor', 'media-views' ],
                EVOTING_VERSION,
                true
            );
            wp_enqueue_script( 'evoting-settings-media' );
            wp_add_inline_script( 'evoting-settings-media', self::get_media_picker_script() );
        }
    }

    /**
     * Skrypt do wyboru pliku z biblioteki mediów (logo, banner).
     *
     * @return string
     */
    private static function get_media_picker_script(): string {
        return <<<'JS'
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.evoting-media-picker').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = this.getAttribute('data-target');
                var previewId = this.getAttribute('data-preview');
                var sizeAttr = (this.getAttribute('data-size') || '32,32').split(',');
                var width = parseInt(sizeAttr[0], 10) || 32;
                var height = parseInt(sizeAttr[1], 10) || 32;
                var input = document.getElementById(targetId);
                var preview = document.getElementById(previewId);
                var currentId = input && input.value ? parseInt(input.value, 10) : 0;
                var frame = wp.media({
                    library: { type: 'image' },
                    multiple: false,
                    frame: 'select'
                });
                if (currentId) frame.state('library').selection.reset().add(wp.media.attachment(currentId));
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    if (input) input.value = att.id;
                    if (preview) {
                        var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                        if (width <= 32 && att.sizes && att.sizes.thumbnail) url = att.sizes.thumbnail.url;
                        preview.innerHTML = '<img src="' + url + '" alt="" style="max-width:' + Math.min(width, 400) + 'px;max-height:' + Math.min(height, 130) + 'px;display:block;" />';
                    }
                    document.querySelectorAll('.evoting-media-remove[data-target="' + targetId + '"]').forEach(function(b) { b.classList.remove('hidden'); });
                });
                frame.open();
            });
        });
        document.querySelectorAll('.evoting-media-remove').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = this.getAttribute('data-target');
                var previewId = this.getAttribute('data-preview');
                var input = document.getElementById(targetId);
                var preview = document.getElementById(previewId);
                if (input) input.value = '';
                if (preview) preview.innerHTML = '';
                this.classList.add('hidden');
            });
        });
    });
})();
JS;
    }

    private function is_plugin_page( string $hook ): bool {
        return str_contains( $hook, 'evoting' );
    }
}
