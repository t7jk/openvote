<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';

$poll_admins = Openvote_Role_Manager::get_poll_admins();
$editors     = Openvote_Role_Manager::get_poll_editors();
$current_uid = get_current_user_id();

// Lista do wyboru koordynatora — limit 300 ze względu na wydajność przy dużej bazie (np. 10k+).
$openvote_roles_list_limit = 300;
$all_users_for_role = get_users( [
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'number'  => $openvote_roles_list_limit,
    'exclude' => array_map( fn( $u ) => $u->ID, $poll_admins ),
] );

$groups = $wpdb->get_results( "SELECT * FROM {$groups_table} ORDER BY name ASC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Koordynatorzy', 'openvote' ); ?></h1>
    <p class="description" style="max-width:720px; margin:8px 0 16px;">
        <?php esc_html_e( 'Dodanie użytkownika do grupy powoduje, że staje się koordynatorem tej grupy (jednej lub wielu) i może uruchamiać dla tych grup głosowania dla członków grupy. Jeden koordynator może być przypisany do jednej lub wielu grup.', 'openvote' ); ?>
    </p>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Zmiany zostały zapisane.', 'openvote' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $role_error = get_transient( 'openvote_roles_error' );
    if ( $role_error ) {
        delete_transient( 'openvote_roles_error' );
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $role_error ); ?></p>
        </div>
    <?php } ?>

    <table class="widefat striped" style="max-width: 800px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Koordynator', 'openvote' ); ?></th>
                <th><?php esc_html_e( 'Grupy', 'openvote' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( 'Akcja', 'openvote' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $editors ) ) : ?>
                <tr><td colspan="3"><?php esc_html_e( 'Brak Koordynatorów.', 'openvote' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $editors as $u ) :
                    $nickname = Openvote_Field_Map::get_user_value( $u, 'nickname' );
                    if ( $nickname === '' ) {
                        $nickname = $u->user_login;
                    }
                    $city = Openvote_Field_Map::get_user_value( $u, 'city' );
                    $city_label = ( $city !== '' ) ? $city : __( 'brak', 'openvote' );
                    $group_ids = Openvote_Role_Manager::get_user_groups( $u->ID );
                    $user_groups = [];
                    foreach ( $groups as $g ) {
                        if ( in_array( (int) $g->id, $group_ids, true ) ) {
                            $user_groups[] = $g;
                        }
                    }
                    $can_remove = Openvote_Role_Manager::can_remove( $current_uid, $u->ID );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $nickname . ' (' . $city_label . ')' ); ?></td>
                        <td>
                            <?php
                            if ( empty( $user_groups ) ) {
                                echo '—';
                            } else {
                                $parts = [];
                                foreach ( $user_groups as $g ) {
                                    if ( $can_remove ) {
                                        $parts[] = '<form method="post" action="" style="display:inline;">' . wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce', false, false ) . '<input type="hidden" name="openvote_roles_action" value="remove_group"><input type="hidden" name="user_id" value="' . esc_attr( $u->ID ) . '"><input type="hidden" name="group_id" value="' . esc_attr( $g->id ) . '"><button type="submit" class="button-link">' . esc_html( $g->name ) . '</button></form>';
                                    } else {
                                        $parts[] = esc_html( $g->name );
                                    }
                                }
                                echo implode( ', ', $parts );
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ( $can_remove ) : ?>
                                <form method="post" action="" style="display:inline;">
                                    <?php wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce' ); ?>
                                    <input type="hidden" name="openvote_roles_action" value="remove_role">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>">
                                    <button type="submit" class="button button-small"><?php esc_html_e( 'Odłącz wszystko', 'openvote' ); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( ! empty( $groups ) ) : ?>
        <form method="post" action="" style="margin-top:12px;">
            <?php wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce' ); ?>
            <input type="hidden" name="openvote_roles_action" value="add_poll_editor">
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; gap:16px;">
                <div>
                    <label for="openvote_add_editor_user_id_by_input"><?php esc_html_e( 'ID użytkownika (szybkie):', 'openvote' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php esc_html_e( 'Wpisz ID użytkownika, jeśli znasz.', 'openvote' ); ?></p>
                    <input type="number" name="openvote_add_editor_user_id_by_input" id="openvote_add_editor_user_id_by_input" min="1" placeholder="<?php esc_attr_e( 'opcjonalnie', 'openvote' ); ?>" style="width:120px;">
                </div>
                <div>
                    <label for="openvote_add_editor_user"><?php esc_html_e( 'Koordynator (z listy):', 'openvote' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php printf( esc_html__( 'Pokazano max %d użytkowników. Przewiń listę lub wpisz ID powyżej.', 'openvote' ), (int) $openvote_roles_list_limit ); ?></p>
                    <select name="user_id" id="openvote_add_editor_user" size="15" style="min-width:280px; display:block;">
                        <option value="">— <?php esc_html_e( 'Wybierz', 'openvote' ); ?> —</option>
                        <?php foreach ( $all_users_for_role as $u ) :
                            $nickname = Openvote_Field_Map::get_user_value( $u, 'nickname' );
                            if ( $nickname === '' ) {
                                $nickname = $u->user_login;
                            }
                            $city = Openvote_Field_Map::get_user_value( $u, 'city' );
                            $city_label = ( $city !== '' ) ? $city : __( 'brak', 'openvote' );
                            ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $nickname . ' (' . $city_label . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="align-self:center;">
                    <button type="submit" class="button" style="margin-top:24px;"><?php esc_html_e( 'Dodaj', 'openvote' ); ?></button>
                </div>
                <div>
                    <label for="openvote_editor_groups"><?php esc_html_e( 'Grupy:', 'openvote' ); ?></label>
                    <p class="description" style="margin:4px 0 6px;"><?php esc_html_e( 'Ctrl+klik: wiele grup. Przewiń listę.', 'openvote' ); ?></p>
                    <select name="openvote_editor_groups[]" id="openvote_editor_groups" multiple size="15" style="min-width:200px; display:block;">
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    <?php else : ?>
        <p class="description"><?php esc_html_e( 'Najpierw dodaj grupy w sekcji Grupy, aby móc przypisywać Koordynatorów.', 'openvote' ); ?></p>
    <?php endif; ?>
</div>
