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
            __( 'Ankiety', 'openvote' ),
            __( 'Ankiety', 'openvote' ),
            'read',
            'openvote-surveys',
            [ $this, 'render_surveys_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Wysyłka', 'openvote' ),
            __( 'Wysyłka', 'openvote' ),
            'read',
            'openvote-communication',
            [ $this, 'render_communication_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Grupy', 'openvote' ),
            __( 'Grupy', 'openvote' ),
            'read',
            'openvote-groups',
            [ $this, 'render_groups_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Koordynatorzy', 'openvote' ),
            __( 'Koordynatorzy', 'openvote' ),
            'read',
            'openvote-roles',
            [ $this, 'render_roles_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Statystyka', 'openvote' ),
            __( 'Statystyka', 'openvote' ),
            'read',
            'openvote-statistics',
            [ $this, 'render_statistics_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Konfiguracja', 'openvote' ),
            __( 'Konfiguracja', 'openvote' ),
            'read',
            'openvote-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'openvote',
            __( 'Podręcznik użytkownika', 'openvote' ),
            __( 'Podręcznik', 'openvote' ),
            'read',
            'openvote-manual',
            [ $this, 'render_manual_page' ]
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
     * Czy użytkownik ma dostęp do zakładki Koordynatorzy (Administrator lub Koordynator).
     */
    public static function user_can_access_coordinators(): bool {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return false;
        }
        $allowed_wp_roles = [ 'administrator' ];
        if ( array_intersect( $allowed_wp_roles, (array) $user->roles ) ) {
            return true;
        }
        return Openvote_Role_Manager::get_user_role( $user->ID ) === Openvote_Role_Manager::ROLE_POLL_ADMIN;
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
     * Menu Open Vote widzą tylko Administrator (wszystko) i Koordynator (wszystko oprócz Konfiguracji).
     * Subscriber, Contributor, Autor, Edytor — brak dostępu do menu (nie ma znaczenia, którą z tych ról mają).
     */
    public function style_menu_for_restricted_roles(): void {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) {
            return;
        }
        $is_administrator = in_array( 'administrator', (array) $user->roles, true );
        $is_coordinator  = self::user_can_access_coordinators();

        if ( $is_administrator || $is_coordinator ) {
            return;
        }
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

    /** Slugi ekranów objętych mapowaniem roli (menu + dostęp). */
    private const SCREEN_SLUGS = [ 'openvote', 'openvote-surveys', 'openvote-groups', 'openvote-roles', 'openvote-settings', 'openvote-manual', 'openvote-statistics', 'openvote-communication' ];

    /**
     * Slugi podstron, które mają być wyłączone (kursywa, brak kliku) według mapy roli.
     *
     * @return string[] Slugi do wyłączenia lub pustą tablicę.
     */
    public static function get_disabled_submenu_slugs_for_coordinator_only(): array {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return [];
        }
        $disabled = [];
        foreach ( self::SCREEN_SLUGS as $slug ) {
            if ( ! openvote_user_can_access_screen( $user_id, $slug ) ) {
                $disabled[] = $slug;
            }
        }
        return $disabled;
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
     * Obsługa formularza grup (dodaj, usuń, członkowie) — przed jakimkolwiek outputem,
     * żeby przekierowanie nie powodowało "headers already sent" (np. z motywem Blocksy).
     */
    public function handle_groups_form_early(): void {
        if ( ! isset( $_POST['openvote_groups_nonce'] ) ) {
            return;
        }
        if ( ! check_admin_referer( 'openvote_groups_action', 'openvote_groups_nonce' ) ) {
            return;
        }
        if ( ! current_user_can( self::CAP_MGR ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ), 403 );
        }

        global $wpdb;
        $groups_table = $wpdb->prefix . 'openvote_groups';
        $gm_table     = $wpdb->prefix . 'openvote_group_members';

        $action = sanitize_text_field( $_POST['openvote_groups_action'] ?? '' );
        $message = '';
        $error   = '';

        // Grupy systemowe (Wszyscy): członkostwo zarządzane automatycznie — bez ręcznych zmian.
        $sys_locked_msg = __( 'To grupa systemowa — członkostwo jest zarządzane automatycznie (wypełnione pole Grupa, bez administratorów) i nie można go zmieniać ręcznie.', 'openvote' );
        $post_group_id  = absint( $_POST['group_id'] ?? 0 );
        $is_sys_group   = $post_group_id && openvote_is_system_group( $post_group_id );

        if ( 'system_sync' === $action ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ), 403 );
            }
            $res     = Openvote_System_Groups::run_manual();
            $message = $res['done']
                ? __( 'Grupa systemowa Wszyscy zsynchronizowana z profilami użytkowników.', 'openvote' )
                : __( 'Synchronizacja grup systemowych rozpoczęta — pozostała część zostanie dokończona przez cron (heartbeat) lub kolejnym kliknięciem.', 'openvote' );
            if ( '' !== $res['summary'] ) {
                $message .= ' (' . $res['summary'] . ')';
            }
        } elseif ( 'add' === $action ) {
            $name = sanitize_text_field( $_POST['group_name'] ?? '' );
            if ( '' === $name ) {
                $error = __( 'Nazwa grupy jest wymagana.', 'openvote' );
            } elseif ( Openvote_System_Groups::is_reserved_name( $name ) ) {
                $error = __( 'Nazwa „Wszyscy” i nazwy zaczynające się od „system.” są zarezerwowane dla grup systemowych.', 'openvote' );
            } else {
                $inserted = $wpdb->insert(
                    $groups_table,
                    [ 'name' => $name, 'type' => 'custom', 'description' => null ],
                    [ '%s', '%s', '%s' ]
                );
                if ( $inserted ) {
                    $message = __( 'Grupa została dodana.', 'openvote' );
                    openvote_groups_audit_log_append( get_current_user_id(), sprintf( __( 'utworzył grupę %s', 'openvote' ), $name ) );
                } else {
                    $error = __( 'Błąd zapisu — nazwa grupy może być już zajęta.', 'openvote' );
                }
            }
        } elseif ( 'delete' === $action ) {
            $group_id = absint( $_POST['group_id'] ?? 0 );
            if ( ! $group_id ) {
                $error = __( 'Nie wybrano grupy do usunięcia.', 'openvote' );
            } elseif ( openvote_is_test_group( $group_id ) ) {
                $error = __( 'Grupy Test nie można usunąć. Wyłącz ją w Konfiguracja → Utwórz grupę do testowania.', 'openvote' );
            } elseif ( $is_sys_group ) {
                $error = __( 'Grupy systemowej nie można usunąć.', 'openvote' );
            } else {
                $group_row = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM {$groups_table} WHERE id = %d", $group_id ) );
                if ( ! $group_row ) {
                    $error = __( 'Wybrana grupa nie istnieje.', 'openvote' );
                } else {
                    $group_name = $group_row->name;
                    $wpdb->delete( $gm_table, [ 'group_id' => $group_id ], [ '%d' ] );
                    $wpdb->delete( $groups_table, [ 'id' => $group_id ], [ '%d' ] );
                    Openvote_Poll::remove_group_from_all_polls( $group_id );
                    $message = __( 'Grupa została usunięta.', 'openvote' );
                    openvote_groups_audit_log_append( get_current_user_id(), sprintf( __( 'usunął grupę %s', 'openvote' ), $group_name ) );
                }
            }
        } elseif ( 'add_member' === $action ) {
            $group_id = absint( $_POST['group_id'] ?? 0 );
            $user_id  = absint( $_POST['member_user_id'] ?? 0 );
            if ( ! $group_id || ! $user_id ) {
                $error = __( 'Wybierz grupę i użytkownika.', 'openvote' );
            } elseif ( $is_sys_group ) {
                $error = $sys_locked_msg;
            } else {
                $group_row = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$groups_table} WHERE id = %d", $group_id ) );
                if ( ! $group_row ) {
                    $error = __( 'Nie znaleziono grupy.', 'openvote' );
                } elseif ( ! get_userdata( $user_id ) ) {
                    $error = __( 'Nie znaleziono użytkownika o podanym ID.', 'openvote' );
                } else {
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT IGNORE INTO {$gm_table} (group_id, user_id, source, added_at) VALUES (%d, %d, 'manual', %s)",
                            $group_id,
                            $user_id,
                            current_time( 'mysql' )
                        )
                    );
                    if ( $wpdb->rows_affected ) {
                        $message = __( 'Członek dodany.', 'openvote' );
                    } else {
                        $error = __( 'Ten użytkownik już jest w grupie.', 'openvote' );
                    }
                }
                if ( $message && $group_row ) {
                    openvote_groups_audit_log_append( get_current_user_id(), sprintf( __( 'dodał %s do %s', 'openvote' ), openvote_get_user_nickname( $user_id ), $group_row->name ) );
                }
            }
        } elseif ( 'remove_member' === $action ) {
            $group_id = absint( $_POST['group_id'] ?? 0 );
            $user_id  = absint( $_POST['member_user_id'] ?? 0 );
            if ( ! $group_id || ! $user_id ) {
                $error = __( 'Wybierz grupę i użytkownika.', 'openvote' );
            } elseif ( $is_sys_group ) {
                $error = $sys_locked_msg;
            } else {
                $group_row = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$groups_table} WHERE id = %d", $group_id ) );
                $member_nick = openvote_get_user_nickname( $user_id );
                $wpdb->delete( $gm_table, [ 'group_id' => $group_id, 'user_id' => $user_id ], [ '%d', '%d' ] );
                $message = __( 'Członek usunięty.', 'openvote' );
                if ( $group_row ) {
                    openvote_groups_audit_log_append( get_current_user_id(), sprintf( __( 'usunął %s z %s', 'openvote' ), $member_nick, $group_row->name ) );
                }
            }
        } elseif ( 'add_user_to_groups' === $action ) {
            $user_id   = absint( $_POST['openvote_add_user_id'] ?? 0 );
            $group_ids = array_map( 'absint', (array) ( $_POST['openvote_user_groups'] ?? [] ) );
            $group_ids = array_filter( $group_ids );
            // Grupy systemowe odfiltrowane (członkostwo automatyczne; nazwa „system.X” nie może trafić do pola miasta w profilu).
            $had_groups = ! empty( $group_ids );
            $group_ids  = array_values( array_filter( $group_ids, static fn( $gid ) => ! openvote_is_system_group( (int) $gid ) ) );
            if ( ! $user_id ) {
                $error = __( 'Wybierz użytkownika z listy.', 'openvote' );
            } elseif ( empty( $group_ids ) ) {
                $error = $had_groups ? $sys_locked_msg : __( 'Wybierz co najmniej jedną grupę.', 'openvote' );
            }
            if ( $user_id && ! empty( $group_ids ) ) {
                $added = 0;
                foreach ( $group_ids as $gid ) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT IGNORE INTO {$gm_table} (group_id, user_id, source, added_at) VALUES (%d, %d, 'manual', %s)",
                            $gid,
                            $user_id,
                            current_time( 'mysql' )
                        )
                    );
                    if ( $wpdb->rows_affected ) {
                        ++$added;
                    }
                }
                if ( $added > 0 ) {
                    $message = sprintf(
                        /* translators: %d: number of groups */
                        _n( 'Użytkownik dodany do %d grupy.', 'Użytkownik dodany do %d grup.', $added, 'openvote' ),
                        $added
                    );
                    $placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
                    $group_names = $wpdb->get_col( $wpdb->prepare( "SELECT name FROM {$groups_table} WHERE id IN ($placeholders) ORDER BY name ASC", ...$group_ids ) );
                    $group_names_str = is_array( $group_names ) ? implode( ', ', array_map( 'trim', $group_names ) ) : '';
                    if ( $group_names_str !== '' ) {
                        openvote_groups_audit_log_append( get_current_user_id(), sprintf( __( 'dodał %s do %s', 'openvote' ), openvote_get_user_nickname( $user_id ), $group_names_str ) );
                    }
                }
                if ( 0 === $added ) {
                    $message = __( 'Użytkownik już należy do wybranych grup.', 'openvote' );
                }
                if ( count( $group_ids ) === 1 ) {
                    $user = get_userdata( $user_id );
                    if ( $user instanceof WP_User ) {
                        $current_city = Openvote_Field_Map::get_user_value( $user, 'city' );
                        $current_city = trim( (string) $current_city );
                        if ( '' === $current_city ) {
                            $group_row = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$groups_table} WHERE id = %d", $group_ids[0] ) );
                            if ( $group_row && $group_row->name !== '' ) {
                                $city_key = Openvote_Field_Map::get_field( 'city' );
                                if ( ! Openvote_Field_Map::is_core_field( $city_key ) ) {
                                    update_user_meta( $user_id, $city_key, $group_row->name );
                                } else {
                                    wp_update_user( [ 'ID' => $user_id, $city_key => $group_row->name ] );
                                }
                                $message = ( $message ? $message . ' ' : '' ) . __( 'Profil użytkownika zaktualizowany: wpisano miejsce zamieszkania.', 'openvote' );
                            }
                        }
                    }
                }
            }
        } elseif ( 'import_csv' === $action ) {
            $group_id  = absint( $_POST['group_id'] ?? 0 );
            $group_row = $group_id ? $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM {$groups_table} WHERE id = %d", $group_id ) ) : null;
            $file      = $_FILES['openvote_csv_file'] ?? null;
            if ( ! $group_row ) {
                $error = __( 'Wybierz grupę.', 'openvote' );
            } elseif ( $is_sys_group ) {
                $error = $sys_locked_msg;
            } elseif ( ! is_array( $file ) || ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || ! is_uploaded_file( $file['tmp_name'] ) ) {
                $error = __( 'Nie udało się wgrać pliku.', 'openvote' );
            } elseif ( (int) $file['size'] > 2 * MB_IN_BYTES ) {
                $error = __( 'Plik jest za duży (maksymalnie 2 MB).', 'openvote' );
            } elseif ( ! in_array( strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ), [ 'csv', 'txt' ], true ) ) {
                $error = __( 'Dozwolone są tylko pliki .csv i .txt.', 'openvote' );
            } else {
                $content = (string) file_get_contents( $file['tmp_name'] );
                $report  = self::import_group_members_from_csv( $content, (int) $group_row->id, (string) $group_row->name );
                set_transient( 'openvote_groups_csv_report_' . get_current_user_id(), $report, 300 );
                $message = __( 'Zakończono import członków z pliku CSV.', 'openvote' );
                openvote_groups_audit_log_append( get_current_user_id(), sprintf( __( 'zaimportował z CSV do %1$s: dodano %2$d', 'openvote' ), $group_row->name, $report['added'] ) );
            }
        }

        if ( $message || $error ) {
            if ( $message ) {
                set_transient( 'openvote_groups_message', $message, 30 );
            }
            if ( $error ) {
                set_transient( 'openvote_groups_error', $error, 30 );
            }
            // Zakładka docelowa po akcji (fragment #tab-… obsługuje openvote-settings-tabs.js).
            $groups_tab_map = [
                'system_sync'        => 'sync',
                'add'                => 'add-group',
                'delete'             => 'list',
                'add_member'         => 'list',
                'remove_member'      => 'list',
                'add_user_to_groups' => 'members',
                'import_csv'         => 'csv',
            ];
            $groups_tab = $groups_tab_map[ $action ] ?? 'list';
            $redirect_args = [ 'page' => 'openvote-groups', 'updated' => '1' ];
            if ( in_array( $action, [ 'add_member', 'remove_member' ], true ) && ! empty( $_POST['group_id'] ) ) {
                // Po dodaniu/usunięciu członka wracamy do widoku członków tej grupy.
                $redirect_args['action']   = 'members';
                $redirect_args['group_id'] = absint( $_POST['group_id'] );
            }
            wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) . '#tab-' . $groups_tab );
            exit;
        }
    }


    /**
     * Parsuje CSV (adresy e-mail) i dodaje istniejących użytkowników do grupy. Nie tworzy kont.
     *
     * @return array{total:int,added:int,rejected:string[],existing:int,invalid:int}
     */
    private static function import_group_members_from_csv( string $content, int $group_id, string $group_name ): array {
        global $wpdb;
        $gm_table = $wpdb->prefix . 'openvote_group_members';
        $content  = preg_replace( '/^\xEF\xBB\xBF/', '', $content );
        if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
            $conv    = function_exists( 'mb_convert_encoding' ) ? @mb_convert_encoding( $content, 'UTF-8', 'Windows-1250' ) : false;
            $content = is_string( $conv ) ? $conv : mb_convert_encoding( $content, 'UTF-8', 'ISO-8859-1' );
        }
        $content = str_replace( [ "\r\n", "\r" ], "\n", $content );
        $lines   = array_values( array_filter( explode( "\n", $content ), static fn( $l ) => trim( $l ) !== '' ) );

        // Wykryj separator po pierwszych liniach.
        $delim = ',';
        $best  = -1;
        foreach ( [ ',', ';', "\t" ] as $d ) {
            $cnt = 0;
            foreach ( array_slice( $lines, 0, 5 ) as $l ) {
                $cnt += substr_count( $l, $d );
            }
            if ( $cnt > $best ) {
                $best  = $cnt;
                $delim = $d;
            }
        }
        $rows = [];
        foreach ( $lines as $l ) {
            $rows[] = str_getcsv( $l, $delim, '"', '' );
        }

        // Kolumna e-mail: nagłówek lub pierwsza kolumna z poprawnym adresem.
        $col      = null;
        $skip_hdr = false;
        if ( $rows ) {
            foreach ( $rows[0] as $i => $cell ) {
                $h = strtolower( trim( (string) $cell ) );
                if ( in_array( $h, [ 'email', 'e-mail', 'mail', 'adres e-mail', 'adres email', 'adres e-mail:' ], true ) ) {
                    $col      = $i;
                    $skip_hdr = true;
                    break;
                }
            }
        }
        $seen    = [];
        $order   = [];
        $invalid = 0;
        foreach ( array_slice( $rows, $skip_hdr ? 1 : 0 ) as $row ) {
            $cand = null;
            if ( null !== $col ) {
                $cand = trim( (string) ( $row[ $col ] ?? '' ) );
                if ( '' === $cand ) {
                    continue;
                }
            } else {
                foreach ( $row as $cell ) {
                    if ( is_email( trim( (string) $cell ) ) ) {
                        $cand = trim( (string) $cell );
                        break;
                    }
                }
                if ( null === $cand ) {
                    // Wiersz bez poprawnego e-maila: licz jako nieprawidłowy, jeśli zawiera "@".
                    if ( strpos( implode( ' ', $row ), '@' ) !== false ) {
                        $key = 'bad:' . strtolower( trim( implode( ' ', $row ) ) );
                        if ( ! isset( $seen[ $key ] ) ) {
                            $seen[ $key ] = true;
                            ++$invalid;
                        }
                    }
                    continue;
                }
            }
            $key = strtolower( $cand );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            if ( ! is_email( $cand ) ) {
                ++$invalid;
                continue;
            }
            $order[] = $key;
        }

        // Mapowanie e-mail → ID użytkownika (zgodnie z mapą pól, w paczkach).
        $actual = Openvote_Field_Map::get_field( 'email' );
        if ( Openvote_Field_Map::NOT_SET_KEY === $actual ) {
            $actual = 'user_email';
        }
        $map = [];
        foreach ( array_chunk( $order, 500 ) as $chunk ) {
            $ph = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
            if ( 'user_email' === $actual ) {
                $res = $wpdb->get_results( $wpdb->prepare( "SELECT ID AS uid, LOWER(user_email) AS em FROM {$wpdb->users} WHERE LOWER(user_email) IN ($ph)", ...$chunk ) );
            } elseif ( Openvote_Field_Map::is_core_field( $actual ) ) {
                $col_sql = esc_sql( $actual );
                $res     = $wpdb->get_results( $wpdb->prepare( "SELECT ID AS uid, LOWER(`{$col_sql}`) AS em FROM {$wpdb->users} WHERE LOWER(`{$col_sql}`) IN ($ph)", ...$chunk ) );
            } else {
                $res = $wpdb->get_results( $wpdb->prepare( "SELECT user_id AS uid, LOWER(meta_value) AS em FROM {$wpdb->usermeta} WHERE meta_key = %s AND LOWER(meta_value) IN ($ph)", $actual, ...$chunk ) );
            }
            foreach ( (array) $res as $r ) {
                $map[ $r->em ] = (int) $r->uid;
            }
        }

        $existing_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$gm_table} WHERE group_id = %d", $group_id ) ) );
        $existing_ids = array_flip( $existing_ids );
        $added        = 0;
        $already      = 0;
        $rejected     = [];
        $now          = current_time( 'mysql' );
        foreach ( $order as $em ) {
            if ( ! isset( $map[ $em ] ) ) {
                $rejected[] = $em;
                continue;
            }
            $uid = $map[ $em ];
            if ( isset( $existing_ids[ $uid ] ) ) {
                ++$already;
                continue;
            }
            $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$gm_table} (group_id, user_id, source, added_at) VALUES (%d, %d, 'manual', %s)", $group_id, $uid, $now ) );
            if ( $wpdb->rows_affected ) {
                ++$added;
                $existing_ids[ $uid ] = true;
                // Jak przy ręcznym dodaniu do jednej grupy: uzupełnij puste miasto nazwą grupy.
                $user = get_userdata( $uid );
                if ( $user instanceof WP_User && '' === trim( Openvote_Field_Map::get_user_value( $user, 'city' ) ) && '' !== $group_name ) {
                    $city_key = Openvote_Field_Map::get_field( 'city' );
                    if ( ! Openvote_Field_Map::is_core_field( $city_key ) ) {
                        update_user_meta( $uid, $city_key, $group_name );
                    } else {
                        wp_update_user( [ 'ID' => $uid, $city_key => $group_name ] );
                    }
                }
            } else {
                ++$already;
            }
        }
        return [
            'total'    => count( $order ) + $invalid,
            'added'    => $added,
            'rejected' => $rejected,
            'existing' => $already,
            'invalid'  => $invalid,
            'group'    => $group_name,
        ];
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
        if ( ! Openvote_Poll::current_user_can_access( $poll ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego głosowania.', 'openvote' ), '', [ 'response' => 403 ] );
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
     * Akcje GET (duplikat, zakończ, usuń) — wykonywane w admin_init,
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
            if ( $poll && ! Openvote_Poll::current_user_can_access( $poll ) ) {
                wp_die( esc_html__( 'Brak uprawnień do tego głosowania.', 'openvote' ), '', [ 'response' => 403 ] );
            }
            if ( $poll && 'draft' !== $poll->status ) {
                wp_safe_redirect( add_query_arg( 'edit_locked', 1, admin_url( 'admin.php?page=openvote' ) ) );
                exit;
            }
            return;
        }

        if ( ! $poll_id || ! in_array( $action, [ 'delete', 'end', 'duplicate' ], true ) ) {
            return;
        }

        if ( 'delete' === $action ) {
            check_admin_referer( 'openvote_delete_poll_' . $poll_id );
            $res = Openvote_Admin_Polls::delete_checked( $poll_id );
            if ( is_wp_error( $res ) ) {
                set_transient( 'openvote_admin_error', $res->get_error_message(), 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote' ) );
                exit;
            }
            wp_safe_redirect( admin_url( 'admin.php?page=openvote&deleted=1' ) );
            exit;
        }

        // Pozostałe akcje (zakończ / start / duplikuj): koordynator ograniczony do własnych grup nie rusza cudzych głosowań.
        $guard_poll = Openvote_Poll::get( $poll_id );
        if ( $guard_poll && ! Openvote_Poll::current_user_can_access( $guard_poll ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego głosowania.', 'openvote' ), '', [ 'response' => 403 ] );
        }

        if ( 'end' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
            check_admin_referer( 'openvote_end_poll_' . $poll_id );
            $poll = Openvote_Poll::get( $poll_id );
            if ( $poll && 'open' === $poll->status ) {
                // Termin minął (zegar głosowań z przesunięciem) → zachowaj date_end; inaczej ustaw na „teraz”.
                $date_end_was_past = Openvote_Poll::is_time_expired( $poll );
                // Atomowo (WHERE status='open'): przy wyścigu z auto-zamknięciem w heartbeacie naliczy tylko jeden z nich.
                $closed_now = Openvote_Poll::close_if_open(
                    $poll_id,
                    $date_end_was_past ? null : openvote_current_time_for_voting( 'Y-m-d H:i:s' )
                );
                if ( $closed_now && $date_end_was_past && function_exists( 'openvote_increment_missed_for_poll_non_voters' ) ) {
                    openvote_increment_missed_for_poll_non_voters( $poll_id );
                }
                $title = isset( $poll->title ) ? $poll->title : '';
                if ( $closed_now && $title !== '' ) {
                    openvote_polls_audit_log_append( get_current_user_id(), sprintf( __( 'zakończył głosowanie %s', 'openvote' ), $title ) );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=openvote&poll_ended=1' ) );
                exit;
            }
        }

        if ( 'duplicate' === $action && ( current_user_can( self::CAP ) || self::user_can_access_coordinators() ) ) {
            check_admin_referer( 'openvote_duplicate_poll_' . $poll_id );
            $poll = Openvote_Poll::get( $poll_id );
            // E: kopia głosowania bez grup (= dla wszystkich) przy ograniczeniu koordynatorów — tylko manage_options.
            if ( $poll && empty( Openvote_Poll::get_target_group_ids( $poll ) ) && ! openvote_current_user_can_target_everyone() ) {
                set_transient( 'openvote_admin_error', __( 'Głosowanie bez grup docelowych (dostępne dla wszystkich) może zapisać tylko administrator WordPress, gdy koordynatorzy są ograniczeni do własnych grup. Wybierz co najmniej jedną grupę docelową.', 'openvote' ), 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote' ) );
                exit;
            }
            $title = $poll && isset( $poll->title ) ? $poll->title : '';
            $new_id = Openvote_Poll::duplicate( $poll_id );
            if ( false !== $new_id ) {
                if ( $title !== '' ) {
                    openvote_polls_audit_log_append( get_current_user_id(), sprintf( __( 'skopiował głosowanie %s', 'openvote' ), $title ) );
                }
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
                // Ankieta wygasła czasowo: zachowaj jej rzeczywistą date_end (jak heartbeat). Zamknięcie atomowe.
                $closed_now = Openvote_Survey::close_if_open(
                    $survey_id,
                    Openvote_Survey::is_time_expired( $s ) ? null : openvote_current_time_for_voting( 'Y-m-d H:i:s' )
                );
                $title = isset( $s->title ) ? $s->title : '';
                if ( $closed_now && $title !== '' ) {
                    openvote_surveys_audit_log_append( get_current_user_id(), sprintf( __( 'zakończył ankietę %s', 'openvote' ), $title ) );
                }
            }
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&closed=1' ) );
            exit;
        }

        if ( 'delete' === $action && $survey_id ) {
            if ( ! self::user_can_access_coordinators() ) {
                return;
            }
            check_admin_referer( 'openvote_delete_survey_' . $survey_id );
            $s = Openvote_Survey::get( $survey_id );
            $title = $s && isset( $s->title ) ? $s->title : '';
            Openvote_Survey::delete( $survey_id );
            if ( $title !== '' ) {
                openvote_surveys_audit_log_append( get_current_user_id(), sprintf( __( 'usunął ankietę %s', 'openvote' ), $title ) );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&deleted=1' ) );
            exit;
        }

        if ( 'duplicate' === $action && $survey_id ) {
            if ( ! self::user_can_access_coordinators() ) {
                return;
            }
            check_admin_referer( 'openvote_duplicate_survey_' . $survey_id );
            $s = Openvote_Survey::get( $survey_id );
            $title = $s && isset( $s->title ) ? $s->title : '';
            $new_id = Openvote_Survey::duplicate( $survey_id );
            if ( $new_id ) {
                if ( $title !== '' ) {
                    openvote_surveys_audit_log_append( get_current_user_id(), sprintf( __( 'zduplikował ankietę %s', 'openvote' ), $title ) );
                }
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
        if ( 'revert_spam_status' === $action && $survey_id && $response_id ) {
            if ( ! self::user_can_access_coordinators() ) {
                return;
            }
            check_admin_referer( 'openvote_revert_spam_' . $response_id );
            Openvote_Survey::set_response_spam_status( $response_id, 'pending' );
            wp_safe_redirect( add_query_arg( [ 'page' => 'openvote-surveys', 'action' => 'responses', 'survey_id' => $survey_id, 'reverted_spam' => 1 ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    /**
     * Bulk actions (POST) na liście Komunikacja — w admin_init.
     */
    public function handle_bulk_communication_action(): void {
        if ( ! isset( $_GET['page'] ) || 'openvote-communication' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }
        $list_table = new Openvote_Messages_List();
        $list_table->process_bulk_action();
    }

    /**
     * Akcje GET na stronie Komunikacja (delete, duplicate, edit→preview) — w admin_init, przed jakimkolwiek outputem.
     */
    public function handle_communication_get_actions(): void {
        if ( ! isset( $_GET['page'] ) || 'openvote-communication' !== sanitize_text_field( $_GET['page'] ) ) {
            return;
        }

        $action     = sanitize_text_field( $_GET['action'] ?? '' );
        $message_id = isset( $_GET['message_id'] ) ? absint( $_GET['message_id'] ) : 0;

        // Przekieruj edycję wysłanej wiadomości na podgląd — zanim cokolwiek się wyświetli (unika "headers already sent").
        if ( $message_id && 'edit' === $action && openvote_user_can_access_screen( get_current_user_id(), 'openvote-communication' ) ) {
            $message = Openvote_Message::get( $message_id );
            if ( $message && ! empty( $message->sent_at ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&action=preview&message_id=' . $message_id ) . '#tab-form' );
                exit;
            }
        }

        if ( ! $message_id || ! in_array( $action, [ 'delete', 'duplicate' ], true ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
            return;
        }

        // F: koordynator ograniczony do własnych grup nie usuwa / nie duplikuje cudzych wiadomości.
        $guard_msg = Openvote_Message::get( $message_id );
        if ( $guard_msg && ! Openvote_Message::current_user_can_access( $guard_msg ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tej wiadomości.', 'openvote' ), '', [ 'response' => 403 ] );
        }

        if ( 'delete' === $action ) {
            check_admin_referer( 'openvote_delete_message_' . $message_id );
            $message = Openvote_Message::get( $message_id );
            $title   = $message && isset( $message->title ) ? $message->title : '';
            // Nie usuwamy wiadomości w trakcie wysyłki (są jeszcze oczekujące wiadomości w kolejce).
            global $wpdb;
            $pending_q = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}openvote_message_queue WHERE message_id = %d AND status = 'pending'",
                $message_id
            ) );
            if ( $message && empty( $message->sent_at ) && $pending_q > 0 ) {
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&delete_blocked=1' ) . '#tab-list' );
                exit;
            }
            Openvote_Message::delete( $message_id );
            if ( function_exists( 'openvote_messages_audit_log_append' ) && $title !== '' ) {
                openvote_messages_audit_log_append( get_current_user_id(), sprintf( __( 'usunął wysyłkę %s', 'openvote' ), $title ) );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&deleted=1' ) . '#tab-list' );
            exit;
        }

        if ( 'duplicate' === $action ) {
            check_admin_referer( 'openvote_duplicate_message_' . $message_id );
            $message = Openvote_Message::get( $message_id );
            $title   = $message && isset( $message->title ) ? $message->title : '';
            $new_id  = Openvote_Message::duplicate( $message_id );
            if ( false !== $new_id ) {
                if ( function_exists( 'openvote_messages_audit_log_append' ) && $title !== '' ) {
                    openvote_messages_audit_log_append( get_current_user_id(), sprintf( __( 'zduplikował wysyłkę %s', 'openvote' ), $title ) );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&action=edit&message_id=' . $new_id . '&duplicated=1' ) . '#tab-form' );
                exit;
            }
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&duplicate_error=1' ) . '#tab-list' );
            exit;
        }
    }

    /**
     * Obsługa zapisu formularza Komunikacja (POST) — w admin_init.
     */
    public function handle_communication_form_submission(): void {
        if ( ! isset( $_POST['openvote_communication_action'] ) || 'save' !== sanitize_text_field( $_POST['openvote_communication_action'] ) ) {
            return;
        }

        if ( ! isset( $_POST['openvote_save_message_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( $_POST['openvote_save_message_nonce'] ), 'openvote_save_message' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) && ! self::user_can_access_coordinators() ) {
            return;
        }

        $message_id = isset( $_POST['openvote_message_id'] ) ? absint( $_POST['openvote_message_id'] ) : 0;
        $title      = isset( $_POST['openvote_message_title'] ) ? sanitize_text_field( $_POST['openvote_message_title'] ) : '';
        $body       = isset( $_POST['openvote_message_body'] ) ? wp_kses_post( $_POST['openvote_message_body'] ) : '';
        $raw_groups = array_map( 'absint', (array) ( $_POST['target_groups'] ?? [] ) );
        $target_groups = array_values( array_filter( $raw_groups ) );

        $save_fallback = [
            'message_id'    => $message_id,
            'title'         => $title,
            'body'          => $body,
            'target_groups' => $target_groups,
        ];
        $transient_key = 'openvote_communication_save_fallback_' . get_current_user_id();

        if ( '' === $title ) {
            set_transient( $transient_key, $save_fallback, 120 );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&save_error=1' ) . '#tab-form' );
            exit;
        }

        // F: edycja istniejącej wiadomości — koordynator ograniczony do własnych grup tylko swoje.
        $existing_msg   = $message_id ? Openvote_Message::get( $message_id ) : null;
        if ( $existing_msg && ! Openvote_Message::current_user_can_access( $existing_msg ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tej wiadomości.', 'openvote' ), '', [ 'response' => 403 ] );
        }
        // Koordynator ograniczony do własnych grup: odrzucamy cudze grupy (nie tylko w UI). Własne = przypisane
        // w openvote_groups (także grupa systemowa Wszyscy, jeśli przypisana) + grupa Test.
        if ( function_exists( 'openvote_is_coordinator_restricted_to_own_groups' ) && openvote_is_coordinator_restricted_to_own_groups() ) {
            $target_groups = array_values( array_intersect( $target_groups, openvote_coordinator_own_group_ids() ) );
            $save_fallback['target_groups'] = $target_groups;
        }
        // Grupy systemowe: przy włączonym ograniczeniu koordynatorów wskazuje je administrator WP (manage_options) albo
        // koordynator, któremu są przypisane; zapisane już wcześniej w wiadomości (przez administratora) są zachowywane.
        $existing_tg    = $existing_msg ? Openvote_Message::get_target_group_ids( $existing_msg ) : [];
        if ( ! empty( Openvote_System_Groups::apply_target_policy( $raw_groups, $existing_tg )['denied'] ) ) {
            set_transient( $transient_key, $save_fallback, 120 );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&save_error=3' ) . '#tab-form' );
            exit;
        }
        $target_groups = Openvote_System_Groups::apply_target_policy( $target_groups, $existing_tg )['ids'];
        $save_fallback['target_groups'] = $target_groups;
        if ( empty( $target_groups ) ) {
            set_transient( $transient_key, $save_fallback, 120 );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&save_error=2' ) . '#tab-form' );
            exit;
        }

        if ( $message_id ) {
            $message = Openvote_Message::get( $message_id );
            if ( ! $message || $message->sent_at ) {
                set_transient( $transient_key, $save_fallback, 120 );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&save_error=1' ) . '#tab-form' );
                exit;
            }
            $ok = Openvote_Message::update( $message_id, [
                'title'         => $title,
                'body'          => $body,
                'target_groups' => $target_groups,
            ] );
            if ( $ok ) {
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&action=edit&message_id=' . $message_id . '&saved=1' ) . '#tab-form' );
            } else {
                set_transient( $transient_key, $save_fallback, 120 );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&save_error=1' ) . '#tab-form' );
            }
            exit;
        }

        $new_id = Openvote_Message::create( [
            'title'         => $title,
            'body'          => $body,
            'target_groups' => $target_groups,
        ] );

        if ( false !== $new_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&action=edit&message_id=' . $new_id . '&saved=1' ) . '#tab-form' );
        } else {
            set_transient( $transient_key, $save_fallback, 120 );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-communication&save_error=1' ) . '#tab-form' );
        }
        exit;
    }

    public function render_surveys_page(): void {
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote-surveys' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
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
            if ( $survey && 'draft' !== $survey->status ) {
                // Ankieta rozpoczęta/zakończona: pola są zablokowane — pokazujemy podgląd (tylko do odczytu).
                $is_read_only = true;
                $edit_locked  = true;
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/survey-form.php';
                return;
            }
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
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
        }

        $action  = sanitize_text_field( $_GET['action'] ?? '' );
        $poll_id = isset( $_GET['poll_id'] ) ? absint( $_GET['poll_id'] ) : 0;

        if ( 'new' === $action ) {
            $poll = null;
            include OPENVOTE_PLUGIN_DIR . 'admin/partials/poll-form.php';
            return;
        }

        if ( $poll_id && in_array( $action, [ 'edit', 'view', 'results', 'invitations' ], true ) ) {
            $guard_poll = Openvote_Poll::get( $poll_id );
            if ( $guard_poll && ! Openvote_Poll::current_user_can_access( $guard_poll ) ) {
                wp_die( esc_html__( 'Brak uprawnień do tego głosowania.', 'openvote' ), '', [ 'response' => 403 ] );
            }
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

            <?php if ( ! empty( $_GET['bulk_skipped'] ) ) : ?>
                <?php $skipped_n = absint( $_GET['bulk_skipped'] ); ?>
                <div class="notice notice-warning is-dismissible"><p>
                    <?php echo esc_html( sprintf( _n( 'Pominięto %d pozycję (trwające głosowanie lub brak uprawnień). Trwające głosowanie trzeba najpierw zakończyć.', 'Pominięto %d pozycji (trwające głosowania lub brak uprawnień). Trwające głosowania trzeba najpierw zakończyć.', $skipped_n, 'openvote' ), $skipped_n ) ); ?>
                </p></div>
            <?php endif; ?>

            <?php
            $list_err = get_transient( 'openvote_admin_error' );
            if ( $list_err ) {
                delete_transient( 'openvote_admin_error' );
                echo '<div class="notice notice-error is-dismissible" role="alert"><p>' . esc_html( $list_err ) . '</p></div>';
            }
            ?>

            <?php if ( isset( $_GET['started'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e( 'Głosowanie wystartowało.', 'openvote' ); ?>
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

            <?php if ( isset( $_GET['draft_incomplete'] ) ) : ?>
                <div class="notice notice-warning is-dismissible"><p>
                    <?php esc_html_e( 'Szkic zapisany, ale niektóre pytania mają mniej niż 3 odpowiedzi. Uzupełnij je przed startem (min. 2 własne odpowiedzi + „Wstrzymuję się”).', 'openvote' ); ?>
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

            <?php if ( isset( $_GET['duplicate_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible"><p>
                    <?php esc_html_e( 'Nie udało się skopiować głosowania. Upewnij się, że głosowanie ma pytania.', 'openvote' ); ?>
                </p></div>
            <?php endif; ?>

        <?php $poll_vote_url = function_exists( 'openvote_get_vote_page_url' ) ? openvote_get_vote_page_url() : home_url( '/glosuj/' ); ?>
        <div class="notice notice-info inline openvote-poll-list-info" id="openvote-poll-list-info" style="max-width:560px;margin-bottom:16px;padding:8px 32px 8px 12px;position:relative;overflow-wrap:anywhere;">
            <button type="button" class="openvote-poll-list-info-dismiss" aria-label="<?php esc_attr_e( 'Zamknij', 'openvote' ); ?>" style="position:absolute;top:6px;right:8px;background:none;border:none;padding:0;cursor:pointer;font-size:18px;line-height:1;color:#2271b1;opacity:.8;">&times;</button>
            <p style="margin:0;font-size:13px;">
                <?php esc_html_e( 'Głosowania:', 'openvote' ); ?>
                <a href="<?php echo esc_url( $poll_vote_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $poll_vote_url ); ?></a>
            </p>
        </div>
        <script>
        (function(){
            var box = document.getElementById('openvote-poll-list-info');
            var btn = box && box.querySelector('.openvote-poll-list-info-dismiss');
            var key = 'openvote_poll_list_info_closed';
            if ( box && btn ) {
                if ( sessionStorage.getItem(key) === '1' ) { box.style.display = 'none'; }
                btn.addEventListener('click', function() {
                    box.style.display = 'none';
                    try { sessionStorage.setItem(key, '1'); } catch (e) {}
                });
            }
        })();
        </script>

            <?php /* Wyszukiwarka jako GET: zachowuje filtr statusu, a linki sortowania/paginacji zawierają frazę. */ ?>
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="openvote">
                <?php if ( ! empty( $_GET['poll_status'] ) ) : ?>
                    <input type="hidden" name="poll_status" value="<?php echo esc_attr( sanitize_text_field( $_GET['poll_status'] ) ); ?>">
                <?php endif; ?>
                <?php $list_table->search_box( __( 'Szukaj głosowania', 'openvote' ), 'openvote-poll' ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( add_query_arg( null, null ) ); ?>">
                <input type="hidden" name="page" value="openvote">
                <?php $list_table->display(); ?>
            </form>

            <?php
            $polls_audit_all    = openvote_polls_audit_log_get();
            $polls_audit_per    = 20;
            $polls_audit_total  = count( $polls_audit_all );
            $polls_audit_pages  = $polls_audit_total > 0 ? (int) ceil( $polls_audit_total / $polls_audit_per ) : 1;
            $polls_audit_page   = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
            $polls_audit_page   = min( $polls_audit_page, $polls_audit_pages );
            $polls_audit_offset = ( $polls_audit_page - 1 ) * $polls_audit_per;
            $polls_audit_entries = array_slice( $polls_audit_all, $polls_audit_offset, $polls_audit_per );
            $polls_audit_base_url = add_query_arg( [ 'page' => 'openvote' ], admin_url( 'admin.php' ) );
            ?>
            <section class="openvote-polls-audit-log" style="margin-top:32px; max-width:900px;">
                <h2 class="openvote-section-title" style="margin:0 0 8px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Log czynności głosowań', 'openvote' ); ?></h2>
                <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Kto i kiedy utworzył, edytował, wystartował, zakończył, skopiował lub usunął głosowanie albo wysłał zaproszenia. Lista niekasowalna, w celach bezpieczeństwa.', 'openvote' ); ?></p>
                <div class="openvote-audit-log-box" style="background:#1d2327; color:#f0f0f1; padding:12px 16px; border-radius:4px; max-height:220px; overflow-y:auto; font-family:Consolas, Monaco, monospace; font-size:12px; line-height:1.5;">
                    <?php
                    if ( empty( $polls_audit_entries ) ) {
                        echo '<p style="margin:0; color:#a7aaad;">' . esc_html__( 'Brak wpisów.', 'openvote' ) . '</p>';
                    } else {
                        foreach ( $polls_audit_entries as $e ) {
                            $t     = isset( $e['t'] ) ? $e['t'] : '';
                            $actor = isset( $e['actor'] ) ? $e['actor'] : '—';
                            $line  = isset( $e['line'] ) ? $e['line'] : '';
                            echo '<div style="margin:2px 0;">' . esc_html( $t . ' ' . $actor . ' ' . $line ) . '</div>';
                        }
                    }
                    ?>
                </div>
                <?php if ( $polls_audit_pages > 1 ) : ?>
                <p class="openvote-audit-log-nav" style="margin:8px 0 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <span class="displaying-num" style="color:#646970; font-size:13px;">
                        <?php
                        echo esc_html( sprintf( __( 'Strona %1$d z %2$d (%3$d wpisów)', 'openvote' ), $polls_audit_page, $polls_audit_pages, $polls_audit_total ) );
                        ?>
                    </span>
                    <?php if ( $polls_audit_page > 1 ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'audit_page', $polls_audit_page - 1, $polls_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Poprzedni', 'openvote' ); ?></a>
                    <?php endif; ?>
                    <?php if ( $polls_audit_page < $polls_audit_pages ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'audit_page', $polls_audit_page + 1, $polls_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Następny', 'openvote' ); ?></a>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </section>
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

    public function render_communication_page(): void {
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote-communication' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
        }

        $action     = sanitize_text_field( $_GET['action'] ?? '' );
        $message_id = isset( $_GET['message_id'] ) ? absint( $_GET['message_id'] ) : 0;

        // F: podgląd / edycja / wysyłka cudzej wiadomości — odmowa dla koordynatora ograniczonego do własnych grup.
        if ( $message_id && in_array( $action, [ 'send', 'preview', 'edit' ], true ) ) {
            $guard_msg = Openvote_Message::get( $message_id );
            if ( $guard_msg && ! Openvote_Message::current_user_can_access( $guard_msg ) ) {
                wp_die( esc_html__( 'Brak uprawnień do tej wiadomości.', 'openvote' ), '', [ 'response' => 403 ] );
            }
        }

        if ( 'send' === $action && $message_id ) {
            $message = Openvote_Message::get( $message_id );
            if ( $message ) {
                include OPENVOTE_PLUGIN_DIR . 'admin/partials/message-send.php';
                return;
            }
        }

        $list_table = new Openvote_Messages_List();
        $list_table->prepare_items();

        $message    = null;
        $is_preview = false;
        if ( isset( $_GET['save_error'] ) ) {
            $transient_key = 'openvote_communication_save_fallback_' . get_current_user_id();
            $fallback      = get_transient( $transient_key );
            if ( is_array( $fallback ) && ! empty( $fallback ) ) {
                delete_transient( $transient_key );
                $message = (object) [
                    'id'            => isset( $fallback['message_id'] ) ? (int) $fallback['message_id'] : 0,
                    'title'         => isset( $fallback['title'] ) ? $fallback['title'] : '',
                    'body'          => isset( $fallback['body'] ) ? $fallback['body'] : '',
                    'target_groups' => isset( $fallback['target_groups'] ) && is_array( $fallback['target_groups'] )
                        ? wp_json_encode( array_values( $fallback['target_groups'] ) )
                        : '[]',
                ];
            }
        }
        if ( null === $message && $message_id ) {
            if ( 'preview' === $action ) {
                $message    = Openvote_Message::get( $message_id );
                $is_preview = $message && ! empty( $message->sent_at );
            } elseif ( 'edit' === $action ) {
                $message = Openvote_Message::get( $message_id );
            }
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/communication-page.php';
    }

    public function render_groups_page(): void {
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote-groups' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/groups.php';
    }

    public function render_roles_page(): void {
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote-roles' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/roles.php';
    }

    public function render_statistics_page(): void {
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote-statistics' ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
        }
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/statistics.php';
    }

    public function render_settings_page(): void {
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote-settings' ) && ! current_user_can( self::CAP_MGR ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
        }

        include OPENVOTE_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    public function render_manual_page(): void {
        if ( ! openvote_user_can_access_screen( get_current_user_id(), 'openvote-manual' ) && ! current_user_can( self::CAP_MGR ) ) {
            wp_die( esc_html__( 'Brak uprawnień do tego ekranu.', 'openvote' ) );
        }
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/manual.php';
    }

    public function render_about_page(): void {
        include OPENVOTE_PLUGIN_DIR . 'admin/partials/about.php';
    }

    public function render_brand_header(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! str_contains( $screen->id, 'openvote' ) ) {
            return;
        }
        $logo_url   = openvote_get_logo_url();
        $site_name  = get_bloginfo( 'name' );
        $site_desc  = get_bloginfo( 'description' );
        $site_name  = is_string( $site_name ) && trim( $site_name ) !== '' ? trim( $site_name ) : '';
        $site_desc  = is_string( $site_desc ) && trim( $site_desc ) !== '' ? trim( $site_desc ) : '';
        ?>
        <div class="openvote-brand-header">
            <div class="openvote-brand-header__row">
                <?php if ( $logo_url !== '' ) : ?>
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="openvote-brand-header__icon openvote-brand-header__icon--img" width="32" height="32" />
                <?php else : ?>
                    <span class="openvote-brand-header__icon dashicons dashicons-groups"></span>
                <?php endif; ?>
                <span class="openvote-brand-header__title"><?php echo esc_html( openvote_get_brand_short_name() ); ?></span>
                <?php if ( $site_name !== '' ) : ?>
                    <span class="openvote-brand-header__sep">·</span>
                    <span class="openvote-brand-header__subtitle"><?php echo esc_html( $site_name ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $site_desc !== '' ) : ?>
                <p class="openvote-brand-header__tagline"><?php echo esc_html( $site_desc ); ?></p>
            <?php endif; ?>
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
        wp_localize_script( 'openvote-admin', 'openvoteAdminI18n', [
            'maxQuestions'   => __( 'Maksymalnie %d pytań.', 'openvote' ),
            'maxAnswers'     => __( 'Maksymalnie %d odpowiedzi na pytanie.', 'openvote' ),
            'minAnswers'     => __( 'Pytanie musi mieć co najmniej dwie własne odpowiedzi plus „Wstrzymuję się”.', 'openvote' ),
            'questionText'   => __( 'Treść pytania', 'openvote' ),
            'answerText'     => __( 'Treść odpowiedzi', 'openvote' ),
            'addAnswer'      => __( 'Dodaj odpowiedź', 'openvote' ),
            'removeQuestion' => __( 'Usuń pytanie', 'openvote' ),
            'removeAnswer'   => __( 'Usuń odpowiedź', 'openvote' ),
            'abstainAuto'    => __( '(wstrzymanie – auto)', 'openvote' ),
            'loadMore'       => __( 'Załaduj więcej (pokazano %1$d z %2$d)', 'openvote' ),
            'missPrefix'     => __( 'Aby wystartować, uzupełnij: ', 'openvote' ),
            'missTitle'      => __( 'tytuł', 'openvote' ),
            'missDuration'   => __( 'czas trwania', 'openvote' ),
            'missGroup'      => __( 'grupę docelową', 'openvote' ),
            'missQuestion'   => __( 'treść pytania', 'openvote' ),
            'missAnswers'    => __( 'min. 3 odpowiedzi w pytaniu %d', 'openvote' ),
            'missAnswerText' => __( 'treść %d. odpowiedzi w pytaniu %d', 'openvote' ),
            'missDuplicate'  => __( 'powtórzona odpowiedź w pytaniu %d', 'openvote' ),
        ] );

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

        // Edytor treści (wp_editor) na stronie Komunikacja.
        if ( str_contains( $hook, 'openvote-communication' ) ) {
            wp_enqueue_editor();
            wp_enqueue_media();
            wp_enqueue_script(
                'openvote-communication-editor',
                OPENVOTE_PLUGIN_URL . 'assets/js/communication-editor.js',
                [ 'jquery' ],
                OPENVOTE_VERSION,
                true
            );
            wp_localize_script( 'openvote-communication-editor', 'openvoteCommunicationEditor', [
                'defaultBody' => openvote_get_default_message_body(),
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
                    'placeholder'           => __( 'Treść pola (np. Imię i nazwisko kandydata)', 'openvote' ),
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

        // Zakładki (client-side) na ekranach: Konfiguracja, Grupy, Koordynatorzy, Wysyłka.
        foreach ( [ 'openvote-settings', 'openvote-groups', 'openvote-roles', 'openvote-communication' ] as $tabs_screen ) {
            if ( str_contains( $hook, $tabs_screen ) ) {
                wp_enqueue_script(
                    'openvote-settings-tabs',
                    OPENVOTE_PLUGIN_URL . 'admin/js/openvote-settings-tabs.js',
                    [],
                    OPENVOTE_VERSION,
                    true
                );
                break;
            }
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
