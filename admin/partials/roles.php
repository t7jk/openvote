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

/**
 * Format: Imię Nazwisko - login (Miasto). Bez (Miasto) gdy miasto nieużywane lub puste.
 *
 * @param \WP_User $user
 * @return string
 */
function openvote_roles_format_user_display( \WP_User $user ): string {
    $first = trim( (string) Openvote_Field_Map::get_user_value( $user, 'first_name' ) );
    $last  = trim( (string) Openvote_Field_Map::get_user_value( $user, 'last_name' ) );
    $nick  = trim( (string) Openvote_Field_Map::get_user_value( $user, 'nickname' ) );
    if ( $nick === '' ) {
        $nick = $user->user_login;
    }
    $full_name = trim( $first . ' ' . $last );
    $parts     = $full_name !== '' ? [ $full_name, $nick ] : [ $nick ];
    $display   = implode( ' - ', $parts );
    if ( ! Openvote_Field_Map::is_city_disabled() ) {
        $city = trim( (string) Openvote_Field_Map::get_user_value( $user, 'city' ) );
        if ( $city !== '' ) {
            $display .= ' (' . $city . ')';
        }
    }
    return $display;
}
?>
<div class="wrap openvote-roles-page">
    <h1><?php esc_html_e( 'Koordynatorzy i Sejmiki', 'openvote' ); ?></h1>
    <p class="description" style="max-width:720px; margin:8px 0 24px;">
        <?php esc_html_e( 'Dodanie użytkownika do sejmiku powoduje, że staje się koordynatorem tego sejmiku (jednego lub wielu) i może uruchamiać dla tych sejmików głosowania dla członków sejmiku. Jeden koordynator może być przypisany do jednego lub wielu sejmików.', 'openvote' ); ?>
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

    <!-- 1. Lista obecnych koordynatorów -->
    <section class="openvote-roles-list" style="margin-bottom:32px;">
        <h2 class="openvote-section-title" style="margin:0 0 12px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Obecni koordynatorzy', 'openvote' ); ?></h2>
        <table class="widefat striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Koordynator', 'openvote' ); ?></th>
                    <th><?php esc_html_e( 'Sejmiki', 'openvote' ); ?></th>
                    <th style="width:120px;"><?php esc_html_e( 'Akcja', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $editors ) ) : ?>
                    <tr><td colspan="3"><?php esc_html_e( 'Brak Koordynatorów.', 'openvote' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $editors as $u ) :
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
                            <td><?php echo esc_html( openvote_roles_format_user_display( $u ) ); ?></td>
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
    </section>

    <?php if ( ! empty( $groups ) ) : ?>
    <!-- 2. Dodaj koordynatora — jedna sekcja formularza -->
    <section class="openvote-roles-add" style="max-width:800px; border:1px solid #c3c4c7; border-radius:4px; padding:20px 24px; background:#fff; box-shadow:0 1px 1px rgba(0,0,0,.04);">
        <h2 class="openvote-section-title" style="margin:0 0 16px; font-size:1.1em; font-weight:600; padding-bottom:8px; border-bottom:1px solid #eee;"><?php esc_html_e( 'Dodaj koordynatora', 'openvote' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce' ); ?>
            <input type="hidden" name="openvote_roles_action" value="add_poll_editor">

            <div style="display:flex; align-items:stretch; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <h3 style="margin:0 0 6px; font-size:13px; font-weight:600; color:#1d2327;"><?php esc_html_e( 'Użytkownicy', 'openvote' ); ?></h3>
                    <p class="description" style="margin:0 0 8px;"><?php printf( esc_html__( 'Wybierz użytkownika (max %d).', 'openvote' ), (int) $openvote_roles_list_limit ); ?></p>
                    <select name="user_id" id="openvote_add_editor_user" size="12" style="min-width:100%; display:block;">
                        <option value="">— <?php esc_html_e( 'Wybierz', 'openvote' ); ?> —</option>
                        <?php foreach ( $all_users_for_role as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( openvote_roles_format_user_display( $u ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex; align-items:center; flex-shrink:0;">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj >>', 'openvote' ); ?></button>
                </div>

                <div style="flex:1; min-width:200px;">
                    <h3 style="margin:0 0 6px; font-size:13px; font-weight:600; color:#1d2327;"><?php esc_html_e( 'Sejmiki', 'openvote' ); ?></h3>
                    <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Ctrl+klik: wiele sejmików.', 'openvote' ); ?></p>
                    <select name="openvote_editor_groups[]" id="openvote_editor_groups" multiple size="12" style="min-width:100%; display:block;">
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </section>
    <?php else : ?>
        <p class="description"><?php esc_html_e( 'Najpierw dodaj sejmiki w sekcji Sejmiki, aby móc przypisywać Koordynatorów.', 'openvote' ); ?></p>
    <?php endif; ?>
</div>
