<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';
$gm_table     = $wpdb->prefix . 'openvote_group_members';

// ─── Obsługa formularzy (PHP, bez AJAX) ───────────────────────────────────

$message = '';
$error   = '';

if ( isset( $_GET['updated'] ) && sanitize_key( $_GET['updated'] ?? '' ) === '1' && ! isset( $_POST['openvote_groups_nonce'] ) ) {
    $msg = get_transient( 'openvote_groups_message' );
    $err = get_transient( 'openvote_groups_error' );
    if ( $msg !== false ) {
        $message = $msg;
        delete_transient( 'openvote_groups_message' );
    }
    if ( $err !== false ) {
        $error = $err;
        delete_transient( 'openvote_groups_error' );
    }
}

if ( isset( $_POST['openvote_groups_nonce'] ) && check_admin_referer( 'openvote_groups_action', 'openvote_groups_nonce' ) ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
    }

    $action = sanitize_text_field( $_POST['openvote_groups_action'] ?? '' );

    if ( 'add' === $action ) {
        $name = sanitize_text_field( $_POST['group_name'] ?? '' );
        $type = 'custom'; // Grupy dodawane ręcznie są zawsze typu „własna”.

        if ( '' === $name ) {
            $error = __( 'Nazwa sejmiku jest wymagana.', 'openvote' );
        } else {
            $inserted = $wpdb->insert(
                $groups_table,
                [ 'name' => $name, 'type' => $type, 'description' => null ],
                [ '%s', '%s', '%s' ]
            );
            if ( $inserted ) {
                $message = __( 'Sejmik został dodany.', 'openvote' );
            } else {
                $error = __( 'Błąd zapisu — nazwa sejmiku może być już zajęta.', 'openvote' );
            }
        }
    } elseif ( 'delete' === $action ) {
        $group_id = absint( $_POST['group_id'] ?? 0 );
        if ( ! $group_id ) {
            $error = __( 'Nie wybrano sejmiku do usunięcia.', 'openvote' );
        } else {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$groups_table} WHERE id = %d", $group_id ) );
            if ( ! $exists ) {
                $error = __( 'Wybrany sejmik nie istnieje.', 'openvote' );
            } else {
                $wpdb->delete( $gm_table, [ 'group_id' => $group_id ], [ '%d' ] );
                $wpdb->delete( $groups_table, [ 'id' => $group_id ], [ '%d' ] );
                Openvote_Poll::remove_group_from_all_polls( $group_id );
                $message = __( 'Sejmik został usunięty.', 'openvote' );
            }
        }
    } elseif ( 'add_member' === $action ) {
        $group_id = absint( $_POST['group_id'] ?? 0 );
        $user_id  = absint( $_POST['member_user_id'] ?? 0 );
        if ( ! $group_id || ! $user_id ) {
            $error = __( 'Wybierz sejmik i użytkownika.', 'openvote' );
        } else {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$gm_table} (group_id, user_id, source, added_at) VALUES (%d, %d, 'manual', %s)",
                    $group_id,
                    $user_id,
                    current_time( 'mysql' )
                )
            );
            $message = __( 'Członek dodany.', 'openvote' );
        }
    } elseif ( 'remove_member' === $action ) {
        $group_id = absint( $_POST['group_id'] ?? 0 );
        $user_id  = absint( $_POST['member_user_id'] ?? 0 );
        if ( ! $group_id || ! $user_id ) {
            $error = __( 'Wybierz sejmik i użytkownika.', 'openvote' );
        } else {
            $wpdb->delete( $gm_table, [ 'group_id' => $group_id, 'user_id' => $user_id ], [ '%d', '%d' ] );
            $message = __( 'Członek usunięty.', 'openvote' );
        }
    } elseif ( 'add_user_to_groups' === $action ) {
        $user_id = absint( $_POST['openvote_add_user_id'] ?? 0 );
        $group_ids = array_map( 'absint', (array) ( $_POST['openvote_user_groups'] ?? [] ) );
        $group_ids = array_filter( $group_ids );
        if ( ! $user_id ) {
            $error = __( 'Wybierz użytkownika z listy.', 'openvote' );
        } elseif ( empty( $group_ids ) ) {
            $error = __( 'Wybierz co najmniej jeden sejmik.', 'openvote' );
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
            }
            // Gdy użytkownik ma brak miasta w profilu i wybrano dokładnie jedną grupę — nadpisz pole „miasto” nazwą grupy.
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
    }

    if ( $message || $error ) {
        if ( $message ) {
            set_transient( 'openvote_groups_message', $message, 30 );
        }
        if ( $error ) {
            set_transient( 'openvote_groups_error', $error, 30 );
        }
        wp_safe_redirect( add_query_arg( [ 'page' => 'openvote-groups', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}

// Lista użytkowników do ręcznego dodawania do grup — limit 300 ze względu na wydajność przy dużej bazie (np. 10k+).
$openvote_users_list_limit = 300;
$all_users_for_groups = get_users( [
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'number'  => $openvote_users_list_limit,
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
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Członkowie i Sejmiki', 'openvote' ); ?></h1>

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
                <th style="width:250px;"><?php esc_html_e( 'Nazwa sejmiku', 'openvote' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Członkowie', 'openvote' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Akcja', 'openvote' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $groups ) ) : ?>
                <tr><td colspan="3"><?php esc_html_e( 'Brak sejmików.', 'openvote' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $groups as $group ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $group->name ); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-groups&action=members&group_id=' . $group->id ) ); ?>">
                                <?php echo esc_html( $group->member_count ); ?>
                            </a>
                        </td>
                        <td>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
                                <input type="hidden" name="openvote_groups_action" value="delete">
                                <input type="hidden" name="group_id" value="<?php echo esc_attr( $group->id ); ?>">
                                <button type="submit" class="button button-small button-link-delete"
                                        onclick="return confirm('<?php echo esc_js( __( 'Usunąć ten sejmik? Zostaną usunięci wszyscy jego członkowie (przypisania).', 'openvote' ) ); ?>');">
                                    <?php esc_html_e( 'Usuń sejmik', 'openvote' ); ?>
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
        <h2><?php printf( esc_html__( 'Członkowie sejmiku: %s', 'openvote' ), esc_html( $group->name ) ); ?>
            <span class="openvote-badge openvote-badge--meta"><?php echo esc_html( $total_members ); ?></span>
        </h2>

        <?php // Dodaj członka ręcznie ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=openvote-groups&action=members&group_id=' . $view_gid ) ); ?>" style="margin-bottom:16px;">
            <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
            <input type="hidden" name="openvote_groups_action" value="add_member">
            <input type="hidden" name="group_id" value="<?php echo esc_attr( $view_gid ); ?>">
            <input type="number" name="member_user_id" placeholder="<?php esc_attr_e( 'ID użytkownika', 'openvote' ); ?>"
                   min="1" style="width:160px;" required>
            <button type="submit" class="button"><?php esc_html_e( 'Dodaj ręcznie', 'openvote' ); ?></button>
        </form>

        <table class="wp-list-table widefat fixed striped" style="max-width:800px;margin-bottom:16px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Użytkownik', 'openvote' ); ?></th>
                    <th><?php esc_html_e( 'E-mail', 'openvote' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Źródło', 'openvote' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Dodano', 'openvote' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Akcja', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $members ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'Brak członków.', 'openvote' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $members as $m ) : ?>
                        <tr>
                            <td><?php echo esc_html( $m->display_name ); ?></td>
                            <td><?php echo esc_html( $m->user_email ); ?></td>
                            <td><?php echo esc_html( $m->source ); ?></td>
                            <td><?php echo esc_html( $m->added_at ); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=openvote-groups&action=members&group_id=' . $view_gid ) ); ?>">
                                    <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
                                    <input type="hidden" name="openvote_groups_action" value="remove_member">
                                    <input type="hidden" name="group_id" value="<?php echo esc_attr( $view_gid ); ?>">
                                    <input type="hidden" name="member_user_id" value="<?php echo esc_attr( $m->user_id ); ?>">
                                    <button type="submit" class="button button-small button-link-delete"
                                            onclick="return confirm('<?php esc_attr_e( 'Usunąć z grupy?', 'openvote' ); ?>');">
                                        <?php esc_html_e( 'Usuń', 'openvote' ); ?>
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
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-groups&action=members&group_id=' . $view_gid . '&member_offset=' . max( 0, $page_offset - 100 ) ) ); ?>"
               class="button">&laquo; <?php esc_html_e( 'Poprzednie', 'openvote' ); ?></a>
        <?php endif; ?>
        <?php if ( ( $page_offset + 100 ) < $total_members ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-groups&action=members&group_id=' . $view_gid . '&member_offset=' . ( $page_offset + 100 ) ) ); ?>"
               class="button"><?php esc_html_e( 'Następne', 'openvote' ); ?> &raquo;</a>
        <?php endif; ?>

        <?php endif; // if $group ?>
    <?php endif; // if 'members' === $view_action ?>

    <?php if ( 'members' !== $view_action && ! empty( $groups ) ) : ?>
        <?php // ─── Dodaj użytkownika do grup (ręcznie, np. do testów) ───────── ?>
        <hr>
        <h2><?php esc_html_e( 'Dodaj członków do Sejmików', 'openvote' ); ?></h2>
        <p class="description" style="margin:4px 0 12px;"><?php esc_html_e( 'Ręczne przypisanie użytkownika do jednego lub wielu sejmików (niezależnie od automatycznego przyporządkowania). Przydatne przy testach.', 'openvote' ); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
            <input type="hidden" name="openvote_groups_action" value="add_user_to_groups">
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:16px;">
                <div>
                    <label for="openvote_add_user_id"><?php esc_html_e( 'Użytkownik', 'openvote' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php printf( esc_html__( 'Wybierz użytkownika z listy (max %d).', 'openvote' ), (int) $openvote_users_list_limit ); ?></p>
                    <select name="openvote_add_user_id" id="openvote_add_user_id" size="15" style="min-width:280px; display:block;">
                        <option value="">— <?php esc_html_e( 'Wybierz', 'openvote' ); ?> —</option>
                        <?php foreach ( $all_users_for_groups as $u ) :
                            $city = Openvote_Field_Map::get_user_value( $u, 'city' );
                            $city_label = ( $city !== '' ) ? $city : __( 'brak', 'openvote' );
                            $nick = Openvote_Field_Map::get_user_value( $u, 'nickname' );
                            if ( $nick === '' ) { $nick = $u->user_login; }
                        ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $nick . ' (' . $city_label . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="align-self:center;">
                    <button type="submit" class="button" style="margin-top:24px;"><?php esc_html_e( 'Dodaj', 'openvote' ); ?></button>
                </div>
                <div>
                    <label for="openvote_user_groups"><?php esc_html_e( 'Sejmiki:', 'openvote' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php esc_html_e( 'Ctrl+klik: wiele sejmików. Przewiń listę.', 'openvote' ); ?></p>
                    <select name="openvote_user_groups[]" id="openvote_user_groups" multiple size="15" style="min-width:200px; display:block;">
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
    <h2><?php esc_html_e( 'Dodaj sejmik', 'openvote' ); ?></h2>
    <form method="post" action="" style="max-width:500px;">
        <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
        <input type="hidden" name="openvote_groups_action" value="add">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="group_name"><?php esc_html_e( 'Nazwa', 'openvote' ); ?> *</label></th>
                <td><input type="text" id="group_name" name="group_name" class="regular-text" required maxlength="255"></td>
            </tr>
        </table>
        <?php submit_button( __( 'Dodaj sejmik', 'openvote' ), 'primary', 'submit', false ); ?>
    </form>

    <?php // ─── Synchronizacja grup-miast (na dole strony) ───────────────────── ?>
    <hr>
    <div style="margin-top:24px;margin-bottom:20px;padding:12px 16px;background:#fff;border:1px solid #ddd;border-left:4px solid #2271b1;max-width:800px;">
        <strong><?php esc_html_e( 'Synchronizacja sejmików-miast', 'openvote' ); ?></strong>
        <p class="description" style="margin:4px 0 8px;">
            <?php esc_html_e( 'Odkrywa unikalne wartości pola "miasto" w bazie użytkowników, tworzy brakujące sejmiki i przypisuje do nich użytkowników automatycznie (partiami po 100).', 'openvote' ); ?>
        </p>
        <button type="button" id="openvote-sync-all-btn" class="button button-primary">
            <?php esc_html_e( 'Synchronizuj wszystkie sejmiki-miasta', 'openvote' ); ?>
        </button>
        <div id="openvote-sync-all-progress" style="margin-top:10px;"></div>
    </div>
</div>

<style>
.openvote-progress-wrap { display:flex; align-items:center; gap:12px; margin:4px 0; }
.openvote-progress-bar-outer { width:160px; height:12px; background:#ddd; border-radius:6px; overflow:hidden; }
.openvote-progress-bar-inner { height:100%; background:#2271b1; transition:width .3s; }
.openvote-progress-label { margin:0; font-size:12px; color:#555; }
.openvote-progress-done  { color:#0a730a; font-weight:600; }
.openvote-progress-error { color:#d63638; font-weight:600; }
</style>
