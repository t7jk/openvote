<?php
defined( 'ABSPATH' ) || exit;

$wp_admins   = Evoting_Role_Manager::get_wp_admins();
$poll_admins = Evoting_Role_Manager::get_poll_admins();
$editors     = Evoting_Role_Manager::get_poll_editors();
$all_users   = get_users( [ 'fields' => [ 'ID', 'display_name', 'user_email' ] ] );
$current_uid = get_current_user_id();

// Pobierz grupy do selecta redaktora.
global $wpdb;
$groups_table = $wpdb->prefix . 'evoting_groups';
$all_groups   = $wpdb->get_results( "SELECT id, name FROM {$groups_table} ORDER BY name ASC" );

// Usuń z listy użytkowników tych, którzy już mają jakąś rolę evoting (dla uniknięcia duplikatów).
$assigned_ids = array_merge(
    array_map( fn( $u ) => $u->ID, $poll_admins ),
    array_map( fn( $u ) => $u->ID, $editors )
);

$available_users = array_filter( $all_users, fn( $u ) => ! in_array( (int) $u->ID, $assigned_ids, true ) );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Role i uprawnienia', 'evoting' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Zmiana ról została zapisana.', 'evoting' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $role_error = get_transient( 'evoting_roles_error' );
    if ( $role_error ) :
        delete_transient( 'evoting_roles_error' );
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $role_error ); ?></p>
        </div>
    <?php endif; ?>

    <?php // ─── Administratorzy WordPress ──────────────────────────────────── ?>
    <h2><?php esc_html_e( 'Administratorzy WordPress', 'evoting' ); ?>
        <span class="evoting-badge evoting-badge--core"><?php echo esc_html( count( $wp_admins ) . ' / ' . Evoting_Role_Manager::LIMIT_WP_ADMINS ); ?></span>
    </h2>
    <p class="description"><?php esc_html_e( 'Użytkownicy z rolą WordPress "administrator". Limit: min. 1, maks. 2. Zarządzanie przez panel WordPress.', 'evoting' ); ?></p>

    <table class="wp-list-table widefat fixed striped" style="max-width:700px;margin-bottom:30px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Użytkownik', 'evoting' ); ?></th>
                <th><?php esc_html_e( 'E-mail', 'evoting' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $wp_admins ) ) : ?>
                <tr><td colspan="2"><?php esc_html_e( 'Brak.', 'evoting' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $wp_admins as $u ) : ?>
                    <tr>
                        <td><?php echo esc_html( $u->display_name ); ?></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php // ─── Administratorzy Głosowań ───────────────────────────────────── ?>
    <h2><?php esc_html_e( 'Administratorzy Głosowań', 'evoting' ); ?>
        <span class="evoting-badge evoting-badge--meta"><?php echo esc_html( count( $poll_admins ) . ' / ' . Evoting_Role_Manager::LIMIT_POLL_ADMINS ); ?></span>
    </h2>

    <table class="wp-list-table widefat fixed striped" style="max-width:700px;margin-bottom:16px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Użytkownik', 'evoting' ); ?></th>
                <th><?php esc_html_e( 'E-mail', 'evoting' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Akcja', 'evoting' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $poll_admins ) ) : ?>
                <tr><td colspan="3"><?php esc_html_e( 'Brak.', 'evoting' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $poll_admins as $u ) : ?>
                    <tr>
                        <td><?php echo esc_html( $u->display_name ); ?></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                        <td>
                            <?php if ( Evoting_Role_Manager::can_remove( $current_uid, $u->ID ) ) : ?>
                                <form method="post" action="">
                                    <?php wp_nonce_field( 'evoting_roles_action', 'evoting_roles_nonce' ); ?>
                                    <input type="hidden" name="evoting_roles_action" value="remove_role">
                                    <input type="hidden" name="target_user_id" value="<?php echo esc_attr( $u->ID ); ?>">
                                    <button type="submit" class="button button-small button-link-delete"
                                            onclick="return confirm('<?php esc_attr_e( 'Usunąć tę rolę?', 'evoting' ); ?>');">
                                        <?php esc_html_e( 'Usuń', 'evoting' ); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( count( $poll_admins ) < Evoting_Role_Manager::LIMIT_POLL_ADMINS ) : ?>
        <form method="post" action="" style="margin-bottom:30px;">
            <?php wp_nonce_field( 'evoting_roles_action', 'evoting_roles_nonce' ); ?>
            <input type="hidden" name="evoting_roles_action" value="add_poll_admin">
            <select name="target_user_id" required style="min-width:280px;">
                <option value=""><?php esc_html_e( '— Wybierz użytkownika —', 'evoting' ); ?></option>
                <?php foreach ( $available_users as $u ) : ?>
                    <option value="<?php echo esc_attr( $u->ID ); ?>">
                        <?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary" style="margin-left:8px;">
                <?php esc_html_e( 'Dodaj Administratora Głosowań', 'evoting' ); ?>
            </button>
        </form>
    <?php else : ?>
        <p class="description" style="margin-bottom:30px;">
            <?php esc_html_e( 'Osiągnięto limit Administratorów Głosowań.', 'evoting' ); ?>
        </p>
    <?php endif; ?>

    <?php // ─── Redaktorzy Głosowań ────────────────────────────────────────── ?>
    <h2><?php esc_html_e( 'Redaktorzy Głosowań', 'evoting' ); ?>
        <span class="evoting-badge evoting-badge--meta"><?php echo esc_html( count( $editors ) ); ?></span>
    </h2>
    <p class="description"><?php printf( esc_html__( 'Maks. %d redaktorów na grupę.', 'evoting' ), Evoting_Role_Manager::LIMIT_EDITORS_PER_GROUP ); ?></p>

    <table class="wp-list-table widefat fixed striped" style="max-width:700px;margin-bottom:16px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Użytkownik', 'evoting' ); ?></th>
                <th><?php esc_html_e( 'E-mail', 'evoting' ); ?></th>
                <th><?php esc_html_e( 'Grupy', 'evoting' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Akcja', 'evoting' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $editors ) ) : ?>
                <tr><td colspan="4"><?php esc_html_e( 'Brak.', 'evoting' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $editors as $u ) :
                    $group_ids = Evoting_Role_Manager::get_user_groups( $u->ID );
                    $group_names = [];
                    foreach ( $all_groups as $g ) {
                        if ( in_array( (int) $g->id, $group_ids, true ) ) {
                            $group_names[] = $g->name;
                        }
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html( $u->display_name ); ?></td>
                        <td><?php echo esc_html( $u->user_email ); ?></td>
                        <td><?php echo esc_html( ! empty( $group_names ) ? implode( ', ', $group_names ) : '—' ); ?></td>
                        <td>
                            <?php if ( Evoting_Role_Manager::can_remove( $current_uid, $u->ID ) ) : ?>
                                <form method="post" action="">
                                    <?php wp_nonce_field( 'evoting_roles_action', 'evoting_roles_nonce' ); ?>
                                    <input type="hidden" name="evoting_roles_action" value="remove_role">
                                    <input type="hidden" name="target_user_id" value="<?php echo esc_attr( $u->ID ); ?>">
                                    <button type="submit" class="button button-small button-link-delete"
                                            onclick="return confirm('<?php esc_attr_e( 'Usunąć tę rolę?', 'evoting' ); ?>');">
                                        <?php esc_html_e( 'Usuń', 'evoting' ); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( ! empty( $all_groups ) ) : ?>
        <form method="post" action="" style="max-width:700px;">
            <?php wp_nonce_field( 'evoting_roles_action', 'evoting_roles_nonce' ); ?>
            <input type="hidden" name="evoting_roles_action" value="add_poll_editor">
            <table class="form-table" style="max-width:700px;">
                <tr>
                    <th scope="row"><label for="editor_user"><?php esc_html_e( 'Użytkownik', 'evoting' ); ?></label></th>
                    <td>
                        <select name="target_user_id" id="editor_user" required style="min-width:280px;">
                            <option value=""><?php esc_html_e( '— Wybierz użytkownika —', 'evoting' ); ?></option>
                            <?php foreach ( $available_users as $u ) : ?>
                                <option value="<?php echo esc_attr( $u->ID ); ?>">
                                    <?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Grupy', 'evoting' ); ?></th>
                    <td>
                        <select name="editor_groups[]" multiple size="6" style="min-width:280px;">
                            <?php foreach ( $all_groups as $g ) :
                                $current_count = Evoting_Role_Manager::count_editors_in_group( (int) $g->id );
                                $is_full       = $current_count >= Evoting_Role_Manager::LIMIT_EDITORS_PER_GROUP;
                                ?>
                                <option value="<?php echo esc_attr( $g->id ); ?>"
                                        <?php echo $is_full ? 'disabled' : ''; ?>>
                                    <?php echo esc_html( $g->name ); ?>
                                    <?php echo $is_full ? esc_html( ' (' . __( 'pełna', 'evoting' ) . ')' ) : ''; ?>
                                    (<?php echo esc_html( $current_count . '/' . Evoting_Role_Manager::LIMIT_EDITORS_PER_GROUP ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Ctrl+klik aby wybrać wiele grup.', 'evoting' ); ?></p>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Dodaj Redaktora Głosowań', 'evoting' ); ?>
                </button>
            </p>
        </form>
    <?php else : ?>
        <p class="description"><?php esc_html_e( 'Najpierw dodaj grupy w sekcji Grupy, aby móc przypisywać Redaktorów.', 'evoting' ); ?></p>
    <?php endif; ?>
</div>
