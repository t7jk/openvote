<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Admin_Roles {

    private const CAP   = 'manage_options';
    private const NONCE = 'openvote_roles_action';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['openvote_roles_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( self::NONCE, 'openvote_roles_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $action   = sanitize_text_field( $_POST['openvote_roles_action'] ?? '' );
        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
        $current_user_id = get_current_user_id();

        $result = null;

        switch ( $action ) {
            case 'remove_group':
                if ( $user_id && $group_id ) {
                    $result = Openvote_Role_Manager::remove_group_from_editor( $user_id, $group_id, $current_user_id );
                }
                break;
            case 'add_poll_editor':
                if ( ! $user_id ) {
                    return;
                }
                $group_ids = array_map( 'absint', (array) ( $_POST['openvote_editor_groups'] ?? [] ) );
                $group_ids = array_filter( $group_ids );
                // Jeśli użytkownik jest już koordynatorem, dołącz nowe grupy do istniejących (można go dopisać do kolejnych grup).
                if ( Openvote_Role_Manager::ROLE_POLL_EDITOR === Openvote_Role_Manager::get_user_role( $user_id ) ) {
                    $existing = Openvote_Role_Manager::get_user_groups( $user_id );
                    $group_ids = array_values( array_unique( array_merge( $existing, $group_ids ) ) );
                }
                $result = Openvote_Role_Manager::add_poll_editor( $user_id, $group_ids );
                break;
            case 'remove_role':
                if ( ! $user_id ) {
                    return;
                }
                $result = Openvote_Role_Manager::remove_role( $user_id, $current_user_id );
                break;
        }

        if ( $result === null ) {
            set_transient( 'openvote_roles_error', __( 'Nie wybrano użytkownika lub grupy.', 'openvote' ), 30 );
            wp_safe_redirect( add_query_arg( [ 'page' => 'openvote-roles' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( true === $result ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'openvote-roles', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $message = $result instanceof \WP_Error ? $result->get_error_message() : __( 'Wystąpił błąd.', 'openvote' );
        set_transient( 'openvote_roles_error', $message, 30 );
        wp_safe_redirect( add_query_arg( [ 'page' => 'openvote-roles' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
