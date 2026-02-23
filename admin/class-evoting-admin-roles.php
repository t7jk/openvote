<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin_Roles {

    private const CAP   = 'manage_options';
    private const NONCE = 'evoting_roles_action';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['evoting_roles_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( self::NONCE, 'evoting_roles_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $action  = sanitize_text_field( $_POST['evoting_roles_action'] ?? '' );
        $user_id = absint( $_POST['target_user_id'] ?? 0 );

        if ( ! $user_id ) {
            $this->redirect_with_error( __( 'Nieprawidłowy użytkownik.', 'evoting' ) );
            return;
        }

        $current_user_id = get_current_user_id();

        switch ( $action ) {
            case 'add_wp_admin':
                $result = Evoting_Role_Manager::add_wp_admin( $user_id );
                break;

            case 'remove_wp_admin':
                $result = Evoting_Role_Manager::remove_wp_admin( $user_id, $current_user_id );
                break;

            case 'add_poll_admin':
                $result = Evoting_Role_Manager::add_poll_admin( $user_id );
                break;

            case 'add_poll_editor':
                $group_ids = array_map( 'absint', (array) ( $_POST['editor_groups'] ?? [] ) );
                $result    = Evoting_Role_Manager::add_poll_editor( $user_id, $group_ids );
                break;

            case 'remove_role':
                $result = Evoting_Role_Manager::remove_role( $user_id, $current_user_id );
                break;

            default:
                $result = new \WP_Error( 'unknown_action', __( 'Nieznana akcja.', 'evoting' ) );
        }

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_error( $result->get_error_message() );
        } else {
            wp_safe_redirect( add_query_arg( [ 'page' => 'evoting-roles', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    private function redirect_with_error( string $message ): void {
        set_transient( 'evoting_roles_error', $message, 30 );
        wp_safe_redirect( add_query_arg( [ 'page' => 'evoting-roles' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
