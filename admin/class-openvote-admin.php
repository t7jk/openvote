<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Admin {

    private const CAP     = 'edit_others_posts';
    private const CAP_MGR = 'manage_options';

    public function add_menu_pages(): void {
        $menu_icon = openvote_get_logo_url();
        if ( $menu_icon === '' ) {
            $menu_icon = 'dashicons-yes-alt';
        }
        add_menu_page(
            openvote_get_brand_short_name(),
            openvote_get_brand_short_name(),
            'read',
            'openvote',
            [ $this, 'render_polls_page' ],
            $menu_icon,
            30
        );

        add_submenu_page(
            'openvote',
            __( 'Głosowania', 'openvote' ),
            __( 'Głosowania', 'openvote' ),
            'read',
            'openvote',
            [ $this, 'render_polls_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Ankiety wyborcze', 'openvote' ),
            __( 'Ankiety wyborcze', 'openvote' ),
            'read',
            'openvote-surveys',
            [ $this, 'render_surveys_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Członkowie i Sejmiki', 'openvote' ),
            __( 'Członkowie i Sejmiki', 'openvote' ),
            'read',
            'openvote-groups',
            [ $this, 'render_groups_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Koordynatorzy i Sejmiki', 'openvote' ),
            __( 'Koordynatorzy i Sejmiki', 'openvote' ),
            'read',
            'openvote-roles',
            [ $this, 'render_roles_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Konfiguracja OpenVote', 'openvote' ),
            __( 'Konfiguracja OpenVote', 'openvote' ),
            'read',
            'openvote-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Podręcznik użytkownika', 'openvote' ),
            __( '📖 Podręcznik', 'openvote' ),
            'read',
            'openvote-manual',
            [ $this, 'render_manual_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Przepisy prawne', 'openvote' ),
            __( '⚖️ Przepisy', 'openvote' ),
            'read',
            'openvote-law',
            [ $this, 'render_law_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'O OpenVote', 'openvote' ),
            __( 'O OpenVote', 'openvote' ),
            'read',
            'openvote-about',
            [ $this, 'render_about_page' ]
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
        $openvote_role = Openvote_Role_Manager::get_user_role( $user->ID );
        if ( Openvote_Role_Manager::ROLE_POLL_ADMIN === $openvote_role ) {
            return true;
        }
        if ( Openvote_Role_Manager::ROLE_POLL_EDITOR === $openvote_role && ! empty( Openvote_Role_Manager::get_user_groups( $user->ID ) ) ) {
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
        $logo_url = openvote_get_logo_url();
        if ( $logo_url === '' ) {
            return;
        }
        $node = $wp_admin_bar->get_node( 'site-name' );
        if ( ! $node || empty( $node->title ) ) {
            return;
        }
        $img = '<img src="' . esc_url( $logo_url ) . '" alt="" class="openvote-admin-bar-logo" style="width:20px;height:20px;vertical-align:middle;margin-right:6px;border-radius:2px;" />';
        $wp_admin_bar->add_node( [
            'id'    => 'site-name',
            'title' => $img . $node->title,
        ] );
    }

    /**
     * Dla użytkowników bez dostępu: menu Open Vote w kursywie i nieaktywne.
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
                    if ( isset( $item[2] ) && 'openvote' === $item[2] ) {
                        $menu[ $key ][4] = ( isset( $item[4] ) ? $item[4] . ' ' : '' ) . 'openvote-menu-disabled';
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
            return [ 'openvote-settings' ];
        }
        return [];
    }

    /**
     * Przekierowanie ze starego adresu openvote-new na page=openvote&action=new.
     */
    public function redirect_openvote_new(): void {
        if ( isset( $_GET['page'] ) && 'openvote-new' === sanitize_text_field( $_GET['page'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=openvote&action=new' ) );
            exit;
        }
    }

    /**
     * Pobieranie wyników głosowania jako PDF (admin_init, priorytet 1).
     */
    public function handle_results_pdf_download(): void {
        if ( ! isset( $_GET['page'] ) || 'openvote' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }
        if ( ! isset( $_GET['action'] ) || 'results' !== sanitize_text_field( $_GET['action'] ) ) {
            return;
        }
        if ( empty( $_GET['openvote_pdf'] ) || ! current_user_can( self::CAP ) ) {
            return;
        }
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;
        if ( ! $poll_id ) {
            return;
        }
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'openvote_results_pdf_' . $poll_id ) ) {
            wp_die( esc_html__( 'Link wygasł lub jest nieprawidłowy.', 'openvote' ) );
        }
        $poll = Openvote_Poll::get( $poll_id );
        if ( ! $poll ) {
            wp_die( esc_html__( 'Głosowanie nie istnieje.', 'openvote' ) );
        }
        $results         = Openvote_Vote::get_results( $poll_id );
        $voters          = Openvote_Vote::get_voters_admin( $poll_id, 0, 0 );
        $non_voters_list = Openvote_Vote::get_non_voters( $poll_id, 0, 0 );
        Openvote_Results_Pdf::output_download( $poll, $results, $voters, $non_voters_list );
        exit;
    }

    /**
     * Bulk actions (POST) na liście głosowań — wykonywane w admin_init,
     * zanim cokolwiek zostanie wysłane, żeby wp_safe_redirect() działał.
     */
    public function handle_bulk_polls_action(): void {
        if ( ! isset( $_GET['page'] ) || 'openvote' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }
        $list_table = new Openvote_Polls_List();
        $list_table->process_bulk_action();
    }

    /**
     * Bulk actions (POST) na liście ankiet — wykonywane w admin_init (priorytet 1),
     * zanim motyw lub inna wtyczka wyśle output, żeby wp_safe_redirect() działał.
     */
    public function handle_bulk_surveys_action(): void {
        if ( ! isset( $_GET['page'] ) || 'openvote-surveys' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }
        $list_table = new Openvote_Surveys_List();
        $list_table->process_bulk_action();
    }

    /**
     * Akcje GET (duplikat, zakończ, uruchom, usuń) — wykonywane w admin_init,
     * zanim cokolwiek zostanie wysłane, żeby przekierowanie działało.
     */
    public function handle_openvote_get_actions(): void {
        if ( ! isset( $_GET['page'] ) || 'openvote' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }

        $action  = sanitize_text_field( $_GET['action'] ?? '' );
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;

        if ( 'edit' === $action && $poll_id ) {
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll && 'draft' !== $poll->status ) {
                wp_safe_redirect( add_query_arg( 'edit_locked', 1, admin_url( 'admin.php?page=openvote' ) ) );
                exit;
            }
            return;
        }

        if ( ! $poll_id || ! in_array( $action, [ 'delete', 'end', 'start', 'duplicate' ], true ) ) {
            return;
        }

        if ( 'delete' === $action ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            check_admin_referer( 'openvote_delete_poll_' . $poll_id );
            Openvote_Poll::delete( $poll_id );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote&deleted=1' ) );
            exit;
        }

        if ( 'end' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
            check_admin_referer( 'openvote_end_poll_' . $poll_id );
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll && 'open' === $poll->status ) {
                Openvote_Poll::update( $poll_id, [
                    'status'   => 'closed',
                    'date_end' => current_time( 'Y-m-d H:i:s' ),
                ] );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote&poll_ended=1' ) );
                exit;
            }
        }

        if ( 'start' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
            check_admin_referer( 'openvote_start_poll_' . $poll_id );
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll && 'draft' === $poll->status ) {
                $now      = current_time( 'Y-m-d H:i:s' );
                $date_end = ( $poll->date_end && $poll->date_end >= $now ) ? $poll->date_end : $now;
                Openvote_Poll::update( $poll_id, [
                    'status'     => 'open',
                    'date_start' => $now,
                    'date_end'   => $date_end,
                ] );
                wp_safe_redirect( add_query_arg( 'started', 1, admin_url( 'admin.php?page=openvote' ) ) );
                exit;
            }
        }

        if ( 'duplicate' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
            check_admin_referer( 'openvote_duplicate_poll_' . $poll_id );
            $new_id = Openvote_Poll::duplicate( $poll_id );
            if ( false !== $new_id ) {
                wp_safe_redirect( add_query_arg( [ 'action' => 'edit', 'poll_id' => $new_id, 'duplicated' => 1 ], admin_url( 'admin.php?page=openvote' ) ) );
                exit;
            }
            wp_safe_redirect( add_query_arg( 'duplicate_error', 1, admin_url( 'admin.php?page=openvote' ) ) );
            exit;
        }
    }

    /**
     * Akcje GET na stronie ankiet (close, delete, duplicate) — w admin_init,
     * zanim cokolwiek zostanie wysłane, żeby wp_safe_redirect() działał.
     */
    public function handle_openvote_surveys_get_actions(): void {
        if ( ! isset( $_GET['page'] ) || 'openvote-surveys' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }

        $action    = sanitize_text_field( $_GET['action'] ?? '' );
        $survey_id = isset( $_GET['survey_id'] ) ? absint( $_GET['survey_id'] ) : 0;

        if ( 'close' === $action && $survey_id ) {
            if ( ! self::user_can_access_coordinators() ) {
                return;
            }
            check_admin_referer( 'openvote_close_survey_' . $survey_id );
            $s = Openvote_Survey::get( $survey_id );
            if ( $s && 'open' === $s->status ) {
                Openvote_Survey::update( $survey_id, [ 'status' => 'closed', 'date_end' => current_time( 'Y-m-d H:i:s' ) ] );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&closed=1' ) );
            exit;
        }

        if ( 'delete' === $action && $survey_id ) {
            if ( ! self::user_can_access_coordinators() ) {
                return;
            }
            check_admin_referer( 'openvote_delete_survey_' . $survey_id );
            Openvote_Survey::delete( $survey_id );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&deleted=1' ) );
            exit;
        }

        if ( 'duplicate' === $action && $survey_id ) {
            if ( ! self::user_can_access_coordinators() ) {
                return;
            }
            check_admin_referer( 'openvote_duplicate_survey_' . $survey_id );
            $new_id = Openvote_Survey::duplicate( $survey_id );
            if ( $new_id ) {
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&action=edit&survey_id=' . $new_id . '&duplicated=1' ) );
            } else {
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&duplicate_error=1' ) );
            }
            exit;
        }

        $response_id = isset( $_GET['response_id'] ) ? absint( $_GET['response_id'] ) : 0;
        if ( 'mark_not_spam' === $action && $survey_id && $response_id ) {
            if ( ! self::user_can_access_coordinators() ) {
                return;
            }
            check_admin_referer( 'openvote_mark_not_spam_' . $response_id );
            Openvote_Survey::set_response_spam_status( $response_id, 'not_spam' );
            wp_safe_redirect( add_query_arg( [ 'page' => 'openvote-surveys', 'action' => 'responses', 'survey_id' => $survey_id, 'marked_not_spam' => 1 ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    public function render_surveys_page(): void {
        if ( ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $action    = sanitize_text_field( $_GET['action'] ?? '' );
        $survey_id = isset( $_GET['survey_id'] ) ? absint( $_GET['survey_id'] ) : 0;

        // Akcje GET (close, delete, duplicate) są obsługiwane w admin_init — handle_openvote_surveys_get_actions().

        if ( 'new' === $action ) {
            $survey = null;
            include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-form.php';
            return;
        }

        if ( 'edit' === $action && $survey_id ) {
            $survey = Openvote_Survey::get( $survey_id );
            if ( $survey ) {
                $is_read_only = false;
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-form.php';
                return;
            }
        }

        if ( 'view' === $action && $survey_id ) {
            $survey = Openvote_Survey::get( $survey_id );
            if ( $survey ) {
                $is_read_only = true;
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-form.php';
                return;
            }
        }

        if ( 'responses' === $action && $survey_id ) {
            $survey = Openvote_Survey::get( $survey_id );
            if ( $survey ) {
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-responses.php';
                return;
            }
        }

        if ( 'all_responses' === $action ) {
            $survey = null;
            include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-responses.php';
            return;
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-list.php';
    }

    public function render_surveys_new_page(): void {
        if ( ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }
        $survey = null;
        $is_read_only = false;
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-form.php';
    }

    public function render_surveys_responses_page(): void {
        if ( ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }
        $survey = null; // widok zbiorczy wszystkich zamkniętych ankiet
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-responses.php';
    }

    public function render_polls_page(): void {
        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $action  = sanitize_text_field( $_GET['action'] ?? '' );
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;

        if ( 'new' === $action ) {
            $poll = null;
            include OPENVOTE_PLUGIN_DIR . 'admin/partials/poll-form.php';
            return;
        }

        if ( 'edit' === $action && $poll_id ) {
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll && 'draft' === $poll->status ) {
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/poll-form.php';
                return;
            }
        }

        if ( 'view' === $action && $poll_id ) {
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll && in_array( $poll->status, [ 'open', 'closed' ], true ) ) {
                $is_read_only = true;
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/poll-form.php';
                return;
            }
        }

        if ( 'results' === $action && $poll_id ) {
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll ) {
                $results          = Openvote_Vote::get_results( $poll_id );
                $voters_page_size = 100;
                $non_voters_page_size = 100;
                $voters_offset    = isset( $_GET['voters_offset'] ) ? max( 0, absint( $_GET['voters_offset'] ) ) : 0;
                $non_voters_offset = isset( $_GET['non_voters_offset'] ) ? max( 0, absint( $_GET['non_voters_offset'] ) ) : 0;
                $voters           = Openvote_Vote::get_voters_admin( $poll_id, $voters_page_size, $voters_offset );
                $non_voters_list  = Openvote_Vote::get_non_voters( $poll_id, $non_voters_page_size, $non_voters_offset );
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/poll-results.php';
                return;
            }
        }

        if ( 'invitations' === $action && $poll_id ) {
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll ) {
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/poll-invitations.php';
                return;
            }
        }

        // Akcje GET (delete, end, start, duplicate) są obsługiwane w admin_init — handle_openvote_get_actions().
        // Bulk POST actions są obsługiwane w admin_init — handle_bulk_polls_action().

        // WP_List_Table — polls listing.
        $list_table = new Openvote_Polls_List();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Głosowania', 'openvote' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=new' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Dodaj nowe', 'openvote' ); ?>
            </a>

            <?php if ( isset( $_GET['bulk_deleted'] ) ) : ?>
                <?php $count = absint( $_GET['bulk_deleted'] ); ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( sprintf( _n( 'Usunięto %d głosowanie.', 'Usunięto %d głosowań.', $count, 'openvote' ), $count ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['started'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało uruchomione.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['bulk_ended'] ) ) : ?>
                <?php $count = absint( $_GET['bulk_ended'] ); ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( sprintf( _n( 'Zakończono %d głosowanie.', 'Zakończono %d głosowań.', $count, 'openvote' ), $count ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało usunięte.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['created'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało utworzone.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Zmiany zostały zapisane.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['poll_ended'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie zostało zakończone.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['edit_locked'] ) ) : ?>
                <div class="notice notice-warning is-dismissible"><p>
                    <?php esc_html_e( 'Nie można edytować głosowania, które zostało rozpoczęte lub zakończone. Tylko szkice są edytowalne.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['duplicated'] ) ) : ?>
                <?php
                $edit_poll_id = isset( $_GET['edit_poll_id'] ) ? absint( $_GET['edit_poll_id'] ) : 0;
                $edit_url     = $edit_poll_id ? admin_url( 'admin.php?page=openvote&action=edit&poll_id=' . $edit_poll_id . '&duplicated=1' ) : '';
                ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Utworzono kopię głosowania. Znajdziesz ją na liście jako szkic.', 'openvote' ); ?>
                    <?php if ( $edit_url ) : ?>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary" style="margin-left:10px;">
                            <?php esc_html_e( 'Edytuj skopiowane głosowanie', 'openvote' ); ?>
                        </a>
                    <?php endif; ?>
                </p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['duplicate_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible"><p>
                    <?php esc_html_e( 'Nie udało się skopiować głosowania. Upewnij się, że głosowanie ma pytania.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="page" value="openvote">
                <?php
                $list_table->search_box( __( 'Szukaj głosowania', 'openvote' ), 'openvote-poll' );
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_poll_form_page(): void {
        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $poll = null;
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/poll-form.php';
    }

    public function render_groups_page(): void {
        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/groups.php';
    }

    public function render_roles_page(): void {
        if ( ! current_user_can( self::CAP_MGR ) && ! self::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/roles.php';
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( self::CAP_MGR ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    public function render_manual_page(): void {
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/manual.php';
    }

    public function render_law_page(): void {
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/law.php';
    }

    public function render_about_page(): void {
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/about.php';
    }

    public function render_brand_header(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! str_contains( $screen->id, 'openvote' ) ) {
            return;
        }
        $logo_url = openvote_get_logo_url();
        ?>
        <div class="openvote-brand-header">
            <?php if ( $logo_url !== '' ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="openvote-brand-header__icon openvote-brand-header__icon--img" width="32" height="32" />
            <?php else : ?>
                <span class="openvote-brand-header__icon dashicons dashicons-groups"></span>
            <?php endif; ?>
            <span class="openvote-brand-header__title"><?php echo esc_html( openvote_get_brand_short_name() ); ?></span>
            <span class="openvote-brand-header__sep">·</span>
            <span class="openvote-brand-header__subtitle"><?php echo esc_html( openvote_get_brand_full_name() ); ?></span>
        </div>
        <?php
    }

    public function enqueue_styles( string $hook ): void {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_style(
            'openvote-admin',
            OPENVOTE_PLUGIN_URL . 'admin/css/openvote-admin.css',
            [],
            OPENVOTE_VERSION
        );
    }

    /**
     * Skrypt i style ograniczające menu — na każdej stronie admina (żeby podmenu Open Vote było poprawnie stylowane).
     */
    public function enqueue_menu_restrict_script( string $hook ): void {
        wp_enqueue_style(
            'openvote-admin',
            OPENVOTE_PLUGIN_URL . 'admin/css/openvote-admin.css',
            [],
            OPENVOTE_VERSION
        );
        wp_enqueue_script(
            'openvote-menu',
            OPENVOTE_PLUGIN_URL . 'admin/js/openvote-menu.js',
            [],
            OPENVOTE_VERSION,
            true
        );
        wp_localize_script( 'openvote-menu', 'openvoteMenu', [
            'disableSubmenuSlugs' => self::get_disabled_submenu_slugs_for_coordinator_only(),
        ] );
    }

    public function enqueue_scripts( string $hook ): void {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_script(
            'openvote-admin',
            OPENVOTE_PLUGIN_URL . 'admin/js/openvote-admin.js',
            [],
            OPENVOTE_VERSION,
            true
        );

        // Batch progress — na stronie Grup oraz na głównej stronie Open Vote (zaproszenia, wyniki).
        if ( str_contains( $hook, 'openvote' ) ) {
            wp_enqueue_script(
                'openvote-batch-progress',
                OPENVOTE_PLUGIN_URL . 'assets/js/batch-progress.js',
                [],
                OPENVOTE_VERSION,
                true
            );
            wp_localize_script( 'openvote-batch-progress', 'openvoteBatch', [
                'apiRoot'    => esc_url_raw( rest_url( 'openvote/v1' ) ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'emailDelay' => openvote_get_email_batch_delay(),
            ] );
        }

        // Edytor pól ankiet — tylko na stronie Ankiet.
        if ( str_contains( $hook, 'openvote-surveys' ) ) {
            wp_enqueue_script(
                'openvote-survey-admin',
                OPENVOTE_PLUGIN_URL . 'assets/js/survey-admin.js',
                [],
                OPENVOTE_VERSION,
                true
            );
            $translated_labels = array_combine(
                array_keys( Openvote_Field_Map::LABELS ),
                array_map( function( $l ) { return __( $l, 'openvote' ); }, Openvote_Field_Map::LABELS )
            );
            $profile_opts = [ '' => __( '— brak (pole dowolne)', 'openvote' ) ] + $translated_labels;
            wp_localize_script( 'openvote-survey-admin', 'openvoteSurveyAdmin', [
                'maxFields'        => Openvote_Survey::MAX_QUESTIONS,
                'profileFieldOpts' => $profile_opts,
                'i18n'             => [
                    'text_short'     => __( 'Krótki tekst do 100 znaków', 'openvote' ),
                    'text_long'      => __( 'Długi tekst do 2000 znaków', 'openvote' ),
                    'url'            => __( 'Adres URL', 'openvote' ),
                    'placeholder'           => __( 'Pytanie', 'openvote' ),
                    'sensitiveCheckboxLabel' => __( 'Informacja wrażliwa - nie pokazuj odpowiedzi na stronie publicznie.', 'openvote' ),
                    'maxChars'              => __( 'Limit znaków:', 'openvote' ),
                    'remove'         => __( 'Usuń pole', 'openvote' ),
                    'minOne'         => __( 'Ankieta musi mieć co najmniej jedno pole.', 'openvote' ),
                    'emptyLabel'     => __( 'Wypełnij etykiety wszystkich pól.', 'openvote' ),
                    'dateOrder'      => __( 'Data zakończenia musi być późniejsza niż data rozpoczęcia.', 'openvote' ),
                ],
            ] );
        }

        // Media picker (logo, banner) — tylko na stronie Konfiguracji.
        if ( str_contains( $hook, 'openvote-settings' ) ) {
            wp_enqueue_media();
            wp_register_script(
                'openvote-settings-media',
                '',
                [ 'jquery', 'media-editor', 'media-views' ],
                OPENVOTE_VERSION,
                true
            );
            wp_enqueue_script( 'openvote-settings-media' );
            wp_add_inline_script( 'openvote-settings-media', self::get_media_picker_script() );
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
        document.querySelectorAll('.openvote-media-picker').forEach(function(btn) {
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
                    document.querySelectorAll('.openvote-media-remove[data-target="' + targetId + '"]').forEach(function(b) { b.classList.remove('hidden'); });
                });
                frame.open();
            });
        });
        document.querySelectorAll('.openvote-media-remove').forEach(function(btn) {
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
        return str_contains( $hook, 'openvote' );
    }
}
