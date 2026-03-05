<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';
$gm_table     = $wpdb->prefix . 'openvote_group_members';

// ─── Obsługa formularzy (PHP, bez AJAX) ───────────────────────────────────

$message = '';
$error   = '';

if ( isset( $_GET['updated'] ) && sanitize_key( $_GET['updated'] ?? '' ) === '1' ) {
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

// Obsługa formularza (usuń grupę, dodaj, członkowie) jest w Openvote_Admin::handle_groups_form_early() (admin_init, priorytet 1),
// żeby przekierowanie odbywało się przed jakimkolwiek outputem (unika błędu "headers already sent" z motywem Blocksy).

// Lista użytkowników do ręcznego dodawania do grup — limit 1000 ze względu na wydajność przy dużej bazie (np. 10k+).
$openvote_users_list_limit = 1000;
$all_users_for_groups = get_users( [
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'number'  => $openvote_users_list_limit,
] );

// Aktualizuj liczniki i pobierz grupy. Test pierwsza (is_test_group DESC), potem alfabetycznie.
$groups = $wpdb->get_results( "SELECT * FROM {$groups_table} ORDER BY is_test_group DESC, name ASC" );
foreach ( $groups as $group ) {
    $count = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*) FROM {$gm_table} WHERE group_id = %d", $group->id )
    );
    if ( (int) $group->member_count !== $count ) {
        $wpdb->update( $groups_table, [ 'member_count' => $count ], [ 'id' => $group->id ], [ '%d' ], [ '%d' ] );
        $group->member_count = $count;
    }
}
if ( openvote_is_coordinator_restricted_to_own_groups() ) {
    $my_group_ids = array_flip( Openvote_Role_Manager::get_user_groups( get_current_user_id() ) );
    if ( openvote_create_test_group_enabled() ) {
        $test_gid = openvote_get_test_group_id();
        if ( $test_gid ) {
            $my_group_ids[ $test_gid ] = 0;
        }
    }
    $groups = array_filter( $groups, function ( $g ) use ( $my_group_ids ) {
        return isset( $my_group_ids[ (int) $g->id ] );
    } );
}
// Grupa Test zawsze pierwsza (w listach i selectach). Identyfikacja po is_test_group lub po nazwie "Test".
$test_group_name = defined( 'OPENVOTE_PLUGIN_DIR' ) && class_exists( 'Openvote_Activator' ) ? Openvote_Activator::TEST_GROUP_NAME : 'Test';
usort( $groups, function ( $a, $b ) use ( $test_group_name ) {
    $a_test = ( ! empty( $a->is_test_group ) ) || ( isset( $a->name ) && $a->name === $test_group_name );
    $b_test = ( ! empty( $b->is_test_group ) ) || ( isset( $b->name ) && $b->name === $test_group_name );
    if ( $a_test && ! $b_test ) {
        return -1;
    }
    if ( ! $a_test && $b_test ) {
        return 1;
    }
    return strcasecmp( $a->name ?? '', $b->name ?? '' );
} );

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
    <h1><?php esc_html_e( 'Członkowie i grupy', 'openvote' ); ?></h1>

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
                <th style="width:250px;"><?php esc_html_e( 'Nazwa grupy', 'openvote' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Członkowie', 'openvote' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Akcja', 'openvote' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $groups ) ) : ?>
                <tr><td colspan="3"><?php esc_html_e( 'Brak grup.', 'openvote' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $groups as $group ) : ?>
                    <?php $is_test = ! empty( $group->is_test_group ); ?>
                    <tr<?php echo $is_test ? ' class="openvote-group-test"' : ''; ?>>
                        <td><strong><?php echo esc_html( $group->name ); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-groups&action=members&group_id=' . $group->id ) ); ?>">
                                <?php echo esc_html( $group->member_count ); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ( ! $is_test ) : ?>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
                                <input type="hidden" name="openvote_groups_action" value="delete">
                                <input type="hidden" name="group_id" value="<?php echo esc_attr( $group->id ); ?>">
                                <button type="submit" class="button button-small button-link-delete"
                                        onclick="return confirm('<?php echo esc_js( __( 'Usunąć tę grupę? Zostaną usunięci wszyscy jej członkowie (przypisania).', 'openvote' ) ); ?>');">
                                    <?php esc_html_e( 'Usuń grupę', 'openvote' ); ?>
                                </button>
                            </form>
                            <?php endif; ?>
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
        <h2><?php printf( esc_html__( 'Członkowie grupy: %s', 'openvote' ), esc_html( $group->name ) ); ?>
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
                    <th><?php esc_html_e( 'E-mail (zanonimizowany)', 'openvote' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Źródło', 'openvote' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Dodano', 'openvote' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Akcja', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $members ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'Brak członków.', 'openvote' ); ?></td></tr>
                <?php else : ?>
                    <?php
                    // Panel admina: e-mail zanonimizowany (np. jan.kowalski@gmail.com → ja..ko......@gm....co).
                    foreach ( $members as $m ) :
                        $email_display = Openvote_Vote::anonymize_email( $m->user_email ?? '' );
                    ?>
                        <tr>
                            <td><?php echo esc_html( $m->display_name ); ?></td>
                            <td><code><?php echo esc_html( $email_display ); ?></code></td>
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
               class="button"><?php esc_html_e( 'Następny »', 'openvote' ); ?></a>
        <?php endif; ?>

        <?php endif; // if $group ?>
    <?php endif; // if 'members' === $view_action ?>

    <?php if ( 'members' !== $view_action && ! empty( $groups ) ) : ?>
        <?php // ─── Dodaj użytkownika do grup (ręcznie, np. do testów) ───────── ?>
        <hr>
        <h2><?php esc_html_e( 'Dodaj członków do grup', 'openvote' ); ?></h2>
        <p class="description" style="margin:4px 0 12px;"><?php esc_html_e( 'Ręczne przypisanie użytkownika do jednej lub wielu grup (niezależnie od automatycznego przyporządkowania). Przydatne przy testach.', 'openvote' ); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
            <input type="hidden" name="openvote_groups_action" value="add_user_to_groups">
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:16px;">
                <div>
                    <label for="openvote_add_user_id"><?php esc_html_e( 'Użytkownicy', 'openvote' ); ?></label>
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
                    <label for="openvote_user_groups"><?php esc_html_e( 'Grupy:', 'openvote' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php esc_html_e( 'Ctrl+klik: wiele grup. Przewiń listę.', 'openvote' ); ?></p>
                    <select name="openvote_user_groups[]" id="openvote_user_groups" multiple size="15" style="min-width:200px; display:block;">
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php // ─── Dodaj członka po adresie e-mail (nad „Dodaj grupę”) ───────── ?>
    <?php if ( ! empty( $groups ) ) : ?>
    <hr>
    <section class="openvote-groups-add-by-email" style="max-width:800px; margin-top:24px; border:1px solid #c3c4c7; border-radius:4px; padding:20px 24px; background:#fff; box-shadow:0 1px 1px rgba(0,0,0,.04);">
        <h2 class="openvote-section-title" style="margin:0 0 16px; font-size:1.1em; font-weight:600; padding-bottom:8px; border-bottom:1px solid #eee;"><?php esc_html_e( 'Dodaj członka po adresie e-mail', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Wpisz fragment adresu e-mail (min. 2 znaki). Po opóźnieniu wyniki wyszukiwania pojawią się poniżej. Wybierz użytkownika i grupy, następnie kliknij Dodaj >>.', 'openvote' ); ?></p>
        <form method="post" action="" id="openvote-add-member-by-email-form">
            <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
            <input type="hidden" name="openvote_groups_action" value="add_user_to_groups">
            <input type="hidden" name="openvote_add_user_id" id="openvote_member_email_search_user_id" value="">

            <div style="display:flex; align-items:stretch; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <label for="openvote_member_email_search_input" class="screen-reader-text"><?php esc_html_e( 'Szukaj po e-mailu', 'openvote' ); ?></label>
                    <input type="text"
                           id="openvote_member_email_search_input"
                           autocomplete="off"
                           placeholder="<?php esc_attr_e( 'Wpisz adres e-mail lub fragment...', 'openvote' ); ?>"
                           style="width:100%; padding:8px 12px; margin-bottom:6px;">
                    <div id="openvote_member_email_search_status" aria-live="polite" style="min-height:20px; font-size:12px; color:#646970;"></div>
                    <div id="openvote_member_email_search_results" style="min-height:80px; border:1px solid #c3c4c7; border-radius:4px; background:#f6f7f7; max-height:200px; overflow-y:auto;"></div>
                    <div id="openvote_member_email_selected_user" style="margin-top:8px; font-weight:600; color:#1d2327;"></div>
                </div>

                <div style="display:flex; align-items:center; flex-shrink:0;">
                    <button type="submit" class="button button-primary" id="openvote_add_member_by_email_btn" disabled><?php esc_html_e( 'Dodaj >>', 'openvote' ); ?></button>
                </div>

                <div style="flex:1; min-width:200px;">
                    <h3 style="margin:0 0 6px; font-size:13px; font-weight:600; color:#1d2327;"><?php esc_html_e( 'Grupy', 'openvote' ); ?></h3>
                    <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Ctrl+klik: wiele grup.', 'openvote' ); ?></p>
                    <select name="openvote_user_groups[]" id="openvote_member_email_user_groups" multiple size="12" style="min-width:100%; display:block;">
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <?php // ─── Dodaj grupę ─────────────────────────────────────────────────── ?>
    <hr>
    <h2><?php esc_html_e( 'Dodaj grupę', 'openvote' ); ?></h2>
    <form method="post" action="" style="max-width:500px;">
        <?php wp_nonce_field( 'openvote_groups_action', 'openvote_groups_nonce' ); ?>
        <input type="hidden" name="openvote_groups_action" value="add">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="group_name"><?php esc_html_e( 'Nazwa', 'openvote' ); ?> *</label></th>
                <td><input type="text" id="group_name" name="group_name" class="regular-text" required maxlength="255"></td>
            </tr>
        </table>
        <?php submit_button( __( 'Dodaj grupę', 'openvote' ), 'primary', 'submit', false ); ?>
    </form>

    <?php // ─── Synchronizacja grup-miast (na dole strony) ───────────────────── ?>
    <hr>
    <div style="margin-top:24px;margin-bottom:20px;padding:12px 16px;background:#fff;border:1px solid #ddd;border-left:4px solid #2271b1;max-width:800px;">
        <strong><?php esc_html_e( 'Synchronizacja grup-miast (ver.2.14)', 'openvote' ); ?></strong>
        <p class="description" style="margin:4px 0 8px;">
            <?php esc_html_e( 'Odkrywa unikalne wartości pola "miasto" w bazie użytkowników, tworzy brakujące grupy i przypisuje do nich użytkowników automatycznie. Proces może trwać bardzo długo. Synchronizacja uruchamiana jest automatycznie, według ustawień administratora.', 'openvote' ); ?>
        </p>
        <p style="margin:0 0 8px;">
            <button type="button" id="openvote-sync-all-btn" class="button button-primary">
                <?php esc_html_e( 'Synchronizuj wszystkie grupy-miasta', 'openvote' ); ?>
            </button>
            <button type="button" id="openvote-sync-all-reset-btn" class="button openvote-btn-sync-reset" style="margin-left:8px;background:#b32d2e;border-color:#b32d2e;color:#fff;">
                <?php esc_html_e( 'Synchronizuj od początku wszystkie grupy-miasta (Nie zalecane)', 'openvote' ); ?>
            </button>
        </p>
        <button type="button" id="openvote-sync-all-stop-btn" class="button" style="display:none;">
            <?php esc_html_e( 'Zatrzymaj synchronizację grup-miast', 'openvote' ); ?>
        </button>
        <div id="openvote-sync-all-progress" style="margin-top:10px;"></div>
    </div>

    <?php
    $groups_audit_all   = openvote_groups_audit_log_get();
    $groups_audit_per   = 20;
    $groups_audit_total = count( $groups_audit_all );
    $groups_audit_pages = $groups_audit_total > 0 ? (int) ceil( $groups_audit_total / $groups_audit_per ) : 1;
    $groups_audit_page  = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
    $groups_audit_page  = min( $groups_audit_page, $groups_audit_pages );
    $groups_audit_offset = ( $groups_audit_page - 1 ) * $groups_audit_per;
    $groups_audit_entries = array_slice( $groups_audit_all, $groups_audit_offset, $groups_audit_per );
    $groups_audit_base_url = add_query_arg( [ 'page' => 'openvote-groups' ], admin_url( 'admin.php' ) );
    ?>
    <section class="openvote-groups-audit-log" style="margin-top:32px; max-width:900px;">
        <h2 class="openvote-section-title" style="margin:0 0 8px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Log zmian grup i członków', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Kto i kiedy utworzył lub usunął grupę, dodał lub usunął członka, uruchomił synchronizację. Lista niekasowalna, w celach bezpieczeństwa.', 'openvote' ); ?></p>
        <div class="openvote-audit-log-box" style="background:#1d2327; color:#f0f0f1; padding:12px 16px; border-radius:4px; max-height:220px; overflow-y:auto; font-family:Consolas, Monaco, monospace; font-size:12px; line-height:1.5;">
            <?php
            if ( empty( $groups_audit_entries ) ) {
                echo '<p style="margin:0; color:#a7aaad;">' . esc_html__( 'Brak wpisów.', 'openvote' ) . '</p>';
            } else {
                foreach ( $groups_audit_entries as $e ) {
                    $t     = isset( $e['t'] ) ? $e['t'] : '';
                    $actor = isset( $e['actor'] ) ? $e['actor'] : '—';
                    $line  = isset( $e['line'] ) ? $e['line'] : '';
                    echo '<div style="margin:2px 0;">' . esc_html( $t . ' ' . $actor . ' ' . $line ) . '</div>';
                }
            }
            ?>
        </div>
        <?php if ( $groups_audit_pages > 1 ) : ?>
        <p class="openvote-audit-log-nav" style="margin:8px 0 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span class="displaying-num" style="color:#646970; font-size:13px;">
                <?php
                echo esc_html( sprintf( __( 'Strona %1$d z %2$d (%3$d wpisów)', 'openvote' ), $groups_audit_page, $groups_audit_pages, $groups_audit_total ) );
                ?>
            </span>
            <?php if ( $groups_audit_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $groups_audit_page - 1, $groups_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Poprzedni', 'openvote' ); ?></a>
            <?php endif; ?>
            <?php if ( $groups_audit_page < $groups_audit_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $groups_audit_page + 1, $groups_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Następny', 'openvote' ); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </section>
</div>

<?php if ( ! empty( $groups ) ) : ?>
<script>
(function() {
    var DEBOUNCE_MS = 400;
    var MIN_CHARS = 2;
    var searchInput = document.getElementById('openvote_member_email_search_input');
    var searchStatus = document.getElementById('openvote_member_email_search_status');
    var searchResults = document.getElementById('openvote_member_email_search_results');
    var selectedDisplay = document.getElementById('openvote_member_email_selected_user');
    var userIdInput = document.getElementById('openvote_member_email_search_user_id');
    var addBtn = document.getElementById('openvote_add_member_by_email_btn');
    var timer = null;
    var lastQuery = '';

    function setStatus(text) {
        if (searchStatus) searchStatus.textContent = text || '';
    }

    function clearSelection() {
        if (userIdInput) userIdInput.value = '';
        if (selectedDisplay) selectedDisplay.textContent = '';
        if (addBtn) addBtn.disabled = true;
        updateAddButtonState();
    }

    function updateAddButtonState() {
        var groupsSelect = document.getElementById('openvote_member_email_user_groups');
        var hasUser = userIdInput && userIdInput.value && parseInt(userIdInput.value, 10) > 0;
        var hasGroup = groupsSelect && groupsSelect.selectedOptions && groupsSelect.selectedOptions.length > 0;
        if (addBtn) addBtn.disabled = !hasUser || !hasGroup;
    }

    function selectUser(id, label) {
        if (userIdInput) userIdInput.value = id;
        if (selectedDisplay) selectedDisplay.textContent = label;
        if (searchResults) searchResults.innerHTML = '';
        setStatus('');
        updateAddButtonState();
    }

    function runSearch() {
        var q = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
        if (q.length < MIN_CHARS) {
            setStatus('');
            if (searchResults) searchResults.innerHTML = '';
            clearSelection();
            return;
        }
        lastQuery = q;
        setStatus('<?php echo esc_js( __( 'Szukam…', 'openvote' ) ); ?>');
        if (searchResults) searchResults.innerHTML = '';

        var apiRoot = <?php echo json_encode( esc_url_raw( rest_url( 'openvote/v1' ) ) ); ?>;
        var nonce = <?php echo json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

        fetch( apiRoot + '/users/search-by-email?email=' + encodeURIComponent(q), {
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce },
            credentials: 'same-origin'
        } )
            .then(function(r) {
                if (!r.ok) {
                    return r.json().then(function(err) {
                        throw new Error(err && err.message ? err.message : r.statusText || 'Błąd ' + r.status);
                    }).catch(function() {
                        throw new Error(r.statusText || 'Błąd ' + r.status);
                    });
                }
                return r.json();
            })
            .then(function(data) {
                if (lastQuery !== (searchInput && searchInput.value ? searchInput.value.trim() : '')) return;
                setStatus('');
                if (!searchResults) return;
                if (!Array.isArray(data) || data.length === 0) {
                    searchResults.innerHTML = '<p style="margin:8px 12px; color:#646970;"><?php echo esc_js( __( 'Brak wyników.', 'openvote' ) ); ?></p>';
                    return;
                }
                searchResults.innerHTML = '';
                data.forEach(function(item) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'button button-small';
                    btn.style.cssText = 'display:block; width:100%; text-align:left; margin:4px 8px; padding:6px 10px;';
                    btn.textContent = item.label + ' (' + item.email + ')';
                    btn.addEventListener('click', function() { selectUser(item.id, item.label); });
                    searchResults.appendChild(btn);
                });
            })
            .catch(function(err) {
                if (lastQuery !== (searchInput && searchInput.value ? searchInput.value.trim() : '')) return;
                setStatus(err && err.message ? err.message : '<?php echo esc_js( __( 'Błąd wyszukiwania.', 'openvote' ) ); ?>');
            });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearSelection();
            if (timer) clearTimeout(timer);
            var q = searchInput.value.trim();
            if (q.length < MIN_CHARS) {
                setStatus('');
                if (searchResults) searchResults.innerHTML = '';
                return;
            }
            timer = setTimeout(runSearch, DEBOUNCE_MS);
        });
        searchInput.addEventListener('focus', function() {
            if (searchInput.value.trim().length >= MIN_CHARS && searchResults && !searchResults.innerHTML) runSearch();
        });
    }
    var groupsSelect = document.getElementById('openvote_member_email_user_groups');
    if (groupsSelect) groupsSelect.addEventListener('change', updateAddButtonState);
})();
</script>
<?php endif; ?>

<style>
.openvote-progress-wrap { display:flex; align-items:center; gap:12px; margin:4px 0; }
.openvote-progress-bar-outer { width:160px; height:12px; background:#ddd; border-radius:6px; overflow:hidden; }
.openvote-progress-bar-inner { height:100%; background:#2271b1; transition:width .3s; }
.openvote-progress-label { margin:0; font-size:12px; color:#555; }
.openvote-progress-done  { color:#0a730a; font-weight:600; }
.openvote-progress-error { color:#d63638; font-weight:600; }
</style>
