<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$groups_table = $wpdb->prefix . 'evoting_groups';
$gm_table     = $wpdb->prefix . 'evoting_group_members';

// ─── Obsługa formularzy (PHP, bez AJAX) ───────────────────────────────────

$message = '';
$error   = '';

if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' && ! isset( $_POST['evoting_groups_nonce'] ) ) {
    $msg = get_transient( 'evoting_groups_message' );
    $err = get_transient( 'evoting_groups_error' );
    if ( $msg !== false ) {
        $message = $msg;
        delete_transient( 'evoting_groups_message' );
    }
    if ( $err !== false ) {
        $error = $err;
        delete_transient( 'evoting_groups_error' );
    }
}

if ( isset( $_POST['evoting_groups_nonce'] ) && check_admin_referer( 'evoting_groups_action', 'evoting_groups_nonce' ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
    }

    $action = sanitize_text_field( $_POST['evoting_groups_action'] ?? '' );

    if ( 'add' === $action ) {
        $name = sanitize_text_field( $_POST['group_name'] ?? '' );
        $type = 'custom'; // Grupy dodawane ręcznie są zawsze typu „własna”.

        if ( '' === $name ) {
            $error = __( 'Nazwa grupy jest wymagana.', 'evoting' );
        } else {
            $inserted = $wpdb->insert(
                $groups_table,
                [ 'name' => $name, 'type' => $type, 'description' => null ],
                [ '%s', '%s', '%s' ]
            );
            if ( $inserted ) {
                $message = __( 'Grupa została dodana.', 'evoting' );
            } else {
                $error = __( 'Błąd zapisu — nazwa grupy może być już zajęta.', 'evoting' );
            }
        }
    } elseif ( 'delete' === $action ) {
        $group_id = absint( $_POST['group_id'] ?? 0 );
        if ( ! $group_id ) {
            $error = __( 'Nie wybrano grupy do usunięcia.', 'evoting' );
        } else {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$groups_table} WHERE id = %d", $group_id ) );
            if ( ! $exists ) {
                $error = __( 'Wybrana grupa nie istnieje.', 'evoting' );
            } else {
                $wpdb->delete( $gm_table, [ 'group_id' => $group_id ], [ '%d' ] );
                $wpdb->delete( $groups_table, [ 'id' => $group_id ], [ '%d' ] );
                Evoting_Poll::remove_group_from_all_polls( $group_id );
                $message = __( 'Grupa została usunięta.', 'evoting' );
            }
        }
    } elseif ( 'add_member' === $action ) {
        $group_id = absint( $_POST['group_id'] ?? 0 );
        $user_id  = absint( $_POST['member_user_id'] ?? 0 );
        if ( ! $group_id || ! $user_id ) {
            $error = __( 'Wybierz grupę i użytkownika.', 'evoting' );
        } else {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$gm_table} (group_id, user_id, source, added_at) VALUES (%d, %d, 'manual', %s)",
                    $group_id,
                    $user_id,
                    current_time( 'mysql' )
                )
            );
            $message = __( 'Członek dodany.', 'evoting' );
        }
    } elseif ( 'remove_member' === $action ) {
        $group_id = absint( $_POST['group_id'] ?? 0 );
        $user_id  = absint( $_POST['member_user_id'] ?? 0 );
        if ( ! $group_id || ! $user_id ) {
            $error = __( 'Wybierz grupę i użytkownika.', 'evoting' );
        } else {
            $wpdb->delete( $gm_table, [ 'group_id' => $group_id, 'user_id' => $user_id ], [ '%d', '%d' ] );
            $message = __( 'Członek usunięty.', 'evoting' );
        }
    } elseif ( 'add_user_to_groups' === $action ) {
        $user_id = absint( $_POST['evoting_add_user_id_by_input'] ?? 0 );
        if ( ! $user_id ) {
            $user_id = absint( $_POST['evoting_add_user_id'] ?? 0 );
        }
        $group_ids = array_map( 'absint', (array) ( $_POST['evoting_user_groups'] ?? [] ) );
        $group_ids = array_filter( $group_ids );
        if ( ! $user_id ) {
            $error = __( 'Wybierz użytkownika z listy lub wpisz ID użytkownika.', 'evoting' );
        } elseif ( empty( $group_ids ) ) {
            $error = __( 'Wybierz co najmniej jedną grupę.', 'evoting' );
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
                    _n( 'Użytkownik dodany do %d grupy.', 'Użytkownik dodany do %d grup.', $added, 'evoting' ),
                    $added
                );
            }
            // Gdy użytkownik ma brak miasta w profilu i wybrano dokładnie jedną grupę — nadpisz pole „miasto” nazwą grupy.
            if ( count( $group_ids ) === 1 ) {
                $user = get_userdata( $user_id );
                if ( $user instanceof WP_User ) {
                    $current_city = Evoting_Field_Map::get_user_value( $user, 'city' );
                    $current_city = trim( (string) $current_city );
                    if ( '' === $current_city ) {
                        $group_row = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$groups_table} WHERE id = %d", $group_ids[0] ) );
                        if ( $group_row && $group_row->name !== '' ) {
                            $city_key = Evoting_Field_Map::get_field( 'city' );
                            if ( ! Evoting_Field_Map::is_core_field( $city_key ) ) {
                                update_user_meta( $user_id, $city_key, $group_row->name );
                            } else {
                                wp_update_user( [ 'ID' => $user_id, $city_key => $group_row->name ] );
                            }
                            $message = ( $message ? $message . ' ' : '' ) . __( 'Profil użytkownika zaktualizowany: wpisano miejsce zamieszkania.', 'evoting' );
                        }
                    }
                }
            }
        }
    }

    if ( $message || $error ) {
        if ( $message ) {
            set_transient( 'evoting_groups_message', $message, 30 );
        }
        if ( $error ) {
            set_transient( 'evoting_groups_error', $error, 30 );
        }
        wp_safe_redirect( add_query_arg( [ 'page' => 'evoting-groups', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}

// Lista użytkowników do ręcznego dodawania do grup — limit 300 ze względu na wydajność przy dużej bazie (np. 10k+).
$evoting_users_list_limit = 300;
$all_users_for_groups = get_users( [
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'number'  => $evoting_users_list_limit,
] );

// Aktualizuj liczniki i pobierz grupy.
$groups = $wpdb->get_results( "SELECT * FROM {$groups_table} ORDER BY name ASC" );
foreach ( $groups as $group ) {
    $count = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*) FROM {$gm_table} WHERE group_id = %d", $group->id )
    );
    if ( (int) $group->member_count !== $count ) {
        $wpdb->update( $groups_table, [ 'member_count' => $count ], [ 'id' => $group->id ], [ '%d' ], [ '%d' ] );
        $group->member_count = $count;
    }
}

// Parametry AJAX przekazane do JS.
wp_enqueue_script(
    'evoting-batch-progress',
    EVOTING_PLUGIN_URL . 'assets/js/batch-progress.js',
    [],
    EVOTING_VERSION,
    true
);
wp_localize_script( 'evoting-batch-progress', 'evotingBatch', [
    'apiRoot'    => esc_url_raw( rest_url( 'evoting/v1' ) ),
    'nonce'      => wp_create_nonce( 'wp_rest' ),
    'emailDelay' => evoting_get_email_batch_delay(),
] );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Grupy użytkowników', 'evoting' ); ?></h1>

    <?php if ( $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>
    <?php if ( $error ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <?php // ─── Tabela grup ─────────────────────────────────────────────────── ?>
    <table class="wp-list-table widefat fixed striped" style="max-width:900px;margin-bottom:30px;">
        <thead>
            <tr>
                <th style="width:250px;"><?php esc_html_e( 'Nazwa grupy', 'evoting' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Członkowie', 'evoting' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Akcja', 'evoting' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $groups ) ) : ?>
                <tr><td colspan="3"><?php esc_html_e( 'Brak grup.', 'evoting' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $groups as $group ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $group->name ); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting-groups&action=members&group_id=' . $group->id ) ); ?>">
                                <?php echo esc_html( $group->member_count ); ?>
                            </a>
                        </td>
                        <td>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field( 'evoting_groups_action', 'evoting_groups_nonce' ); ?>
                                <input type="hidden" name="evoting_groups_action" value="delete">
                                <input type="hidden" name="group_id" value="<?php echo esc_attr( $group->id ); ?>">
                                <button type="submit" class="button button-small button-link-delete"
                                        onclick="return confirm('<?php echo esc_js( __( 'Usunąć tę grupę? Zostaną usunięci wszyscy jej członkowie (przypisania).', 'evoting' ) ); ?>');">
                                    <?php esc_html_e( 'Usuń grupę', 'evoting' ); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php // ─── Widok członków ──────────────────────────────────────────────── ?>
    <?php
    $view_action = sanitize_text_field( $_GET['action'] ?? '' );
    $view_gid    = absint( $_GET['group_id'] ?? 0 );

    if ( 'members' === $view_action && $view_gid ) :
        $group = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$groups_table} WHERE id = %d", $view_gid ) );
        if ( $group ) :
            $page_offset  = absint( $_GET['member_offset'] ?? 0 );
            $members      = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT gm.user_id, gm.source, gm.added_at, u.display_name, u.user_email
                     FROM {$gm_table} gm
                     INNER JOIN {$wpdb->users} u ON gm.user_id = u.ID
                     WHERE gm.group_id = %d
                     ORDER BY u.display_name ASC
                     LIMIT 100 OFFSET %d",
                    $view_gid,
                    $page_offset
                )
            );
            $total_members = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$gm_table} WHERE group_id = %d", $view_gid )
            );
        ?>
        <hr>
        <h2><?php printf( esc_html__( 'Członkowie grupy: %s', 'evoting' ), esc_html( $group->name ) ); ?>
            <span class="evoting-badge evoting-badge--meta"><?php echo esc_html( $total_members ); ?></span>
        </h2>

        <?php // Dodaj członka ręcznie ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=evoting-groups&action=members&group_id=' . $view_gid ) ); ?>" style="margin-bottom:16px;">
            <?php wp_nonce_field( 'evoting_groups_action', 'evoting_groups_nonce' ); ?>
            <input type="hidden" name="evoting_groups_action" value="add_member">
            <input type="hidden" name="group_id" value="<?php echo esc_attr( $view_gid ); ?>">
            <input type="number" name="member_user_id" placeholder="<?php esc_attr_e( 'ID użytkownika', 'evoting' ); ?>"
                   min="1" style="width:160px;" required>
            <button type="submit" class="button"><?php esc_html_e( 'Dodaj ręcznie', 'evoting' ); ?></button>
        </form>

        <table class="wp-list-table widefat fixed striped" style="max-width:800px;margin-bottom:16px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Użytkownik', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'E-mail', 'evoting' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Źródło', 'evoting' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Dodano', 'evoting' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Akcja', 'evoting' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $members ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'Brak członków.', 'evoting' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $members as $m ) : ?>
                        <tr>
                            <td><?php echo esc_html( $m->display_name ); ?></td>
                            <td><?php echo esc_html( $m->user_email ); ?></td>
                            <td><?php echo esc_html( $m->source ); ?></td>
                            <td><?php echo esc_html( $m->added_at ); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=evoting-groups&action=members&group_id=' . $view_gid ) ); ?>">
                                    <?php wp_nonce_field( 'evoting_groups_action', 'evoting_groups_nonce' ); ?>
                                    <input type="hidden" name="evoting_groups_action" value="remove_member">
                                    <input type="hidden" name="group_id" value="<?php echo esc_attr( $view_gid ); ?>">
                                    <input type="hidden" name="member_user_id" value="<?php echo esc_attr( $m->user_id ); ?>">
                                    <button type="submit" class="button button-small button-link-delete"
                                            onclick="return confirm('<?php esc_attr_e( 'Usunąć z grupy?', 'evoting' ); ?>');">
                                        <?php esc_html_e( 'Usuń', 'evoting' ); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php // Paginacja ?>
        <?php if ( $page_offset > 0 ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting-groups&action=members&group_id=' . $view_gid . '&member_offset=' . max( 0, $page_offset - 100 ) ) ); ?>"
               class="button">&laquo; <?php esc_html_e( 'Poprzednie', 'evoting' ); ?></a>
        <?php endif; ?>
        <?php if ( ( $page_offset + 100 ) < $total_members ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting-groups&action=members&group_id=' . $view_gid . '&member_offset=' . ( $page_offset + 100 ) ) ); ?>"
               class="button"><?php esc_html_e( 'Następne', 'evoting' ); ?> &raquo;</a>
        <?php endif; ?>

        <?php endif; // if $group ?>
    <?php endif; // if 'members' === $view_action ?>

    <?php if ( 'members' !== $view_action && ! empty( $groups ) ) : ?>
        <?php // ─── Dodaj użytkownika do grup (ręcznie, np. do testów) ───────── ?>
        <hr>
        <h2><?php esc_html_e( 'Dodaj użytkownika do grup', 'evoting' ); ?></h2>
        <p class="description" style="margin:4px 0 12px;"><?php esc_html_e( 'Ręczne przypisanie użytkownika do jednej lub wielu grup (niezależnie od automatycznego przyporządkowania). Przydatne przy testach.', 'evoting' ); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field( 'evoting_groups_action', 'evoting_groups_nonce' ); ?>
            <input type="hidden" name="evoting_groups_action" value="add_user_to_groups">
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:16px;">
                <div>
                    <label for="evoting_add_user_id_by_input"><?php esc_html_e( 'ID użytkownika (szybkie):', 'evoting' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php esc_html_e( 'Wpisz ID użytkownika, jeśli znasz (np. z listy członków).', 'evoting' ); ?></p>
                    <input type="number" name="evoting_add_user_id_by_input" id="evoting_add_user_id_by_input" min="1" placeholder="<?php esc_attr_e( 'opcjonalnie', 'evoting' ); ?>" style="width:120px;">
                </div>
                <div>
                    <label for="evoting_add_user_id"><?php esc_html_e( 'Użytkownik (z listy):', 'evoting' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php printf( esc_html__( 'Pokazano max %d użytkowników. Przewiń listę lub wpisz ID powyżej.', 'evoting' ), (int) $evoting_users_list_limit ); ?></p>
                    <select name="evoting_add_user_id" id="evoting_add_user_id" size="15" style="min-width:280px; display:block;">
                        <option value="">— <?php esc_html_e( 'Wybierz', 'evoting' ); ?> —</option>
                        <?php foreach ( $all_users_for_groups as $u ) :
                            $city = Evoting_Field_Map::get_user_value( $u, 'city' );
                            $city_label = ( $city !== '' ) ? $city : __( 'brak', 'evoting' );
                            $nick = Evoting_Field_Map::get_user_value( $u, 'nickname' );
                            if ( $nick === '' ) { $nick = $u->user_login; }
                        ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $nick . ' (' . $city_label . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="align-self:center;">
                    <button type="submit" class="button" style="margin-top:24px;"><?php esc_html_e( 'Dodaj', 'evoting' ); ?></button>
                </div>
                <div>
                    <label for="evoting_user_groups"><?php esc_html_e( 'Grupy:', 'evoting' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php esc_html_e( 'Ctrl+klik: wiele grup. Przewiń listę.', 'evoting' ); ?></p>
                    <select name="evoting_user_groups[]" id="evoting_user_groups" multiple size="15" style="min-width:200px; display:block;">
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php // ─── Dodaj grupę ─────────────────────────────────────────────────── ?>
    <hr>
    <h2><?php esc_html_e( 'Dodaj grupę', 'evoting' ); ?></h2>
    <form method="post" action="" style="max-width:500px;">
        <?php wp_nonce_field( 'evoting_groups_action', 'evoting_groups_nonce' ); ?>
        <input type="hidden" name="evoting_groups_action" value="add">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="group_name"><?php esc_html_e( 'Nazwa', 'evoting' ); ?> *</label></th>
                <td><input type="text" id="group_name" name="group_name" class="regular-text" required maxlength="255"></td>
            </tr>
        </table>
        <?php submit_button( __( 'Dodaj grupę', 'evoting' ), 'primary', 'submit', false ); ?>
    </form>

    <?php // ─── Synchronizacja grup-miast (na dole strony) ───────────────────── ?>
    <hr>
    <div style="margin-top:24px;margin-bottom:20px;padding:12px 16px;background:#fff;border:1px solid #ddd;border-left:4px solid #2271b1;max-width:800px;">
        <strong><?php esc_html_e( 'Synchronizacja grup-miast', 'evoting' ); ?></strong>
        <p class="description" style="margin:4px 0 8px;">
            <?php esc_html_e( 'Odkrywa unikalne wartości pola "miasto" w bazie użytkowników, tworzy brakujące grupy i przypisuje do nich użytkowników automatycznie (partiami po 100).', 'evoting' ); ?>
        </p>
        <button type="button" id="evoting-sync-all-btn" class="button button-primary">
            <?php esc_html_e( 'Synchronizuj wszystkie grupy-miasta', 'evoting' ); ?>
        </button>
        <div id="evoting-sync-all-progress" style="margin-top:10px;"></div>
    </div>
</div>

<style>
.evoting-progress-wrap { display:flex; align-items:center; gap:12px; margin:4px 0; }
.evoting-progress-bar-outer { width:160px; height:12px; background:#ddd; border-radius:6px; overflow:hidden; }
.evoting-progress-bar-inner { height:100%; background:#2271b1; transition:width .3s; }
.evoting-progress-label { margin:0; font-size:12px; color:#555; }
.evoting-progress-done  { color:#0a730a; font-weight:600; }
.evoting-progress-error { color:#d63638; font-weight:600; }
</style>
