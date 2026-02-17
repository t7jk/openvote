<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin_Polls {

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['evoting_poll_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'evoting_save_poll', 'evoting_poll_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $poll_id   = isset( $_POST['poll_id'] ) ? absint( $_POST['poll_id'] ) : 0;
        $action    = sanitize_text_field( $_POST['evoting_action'] ?? 'create' );

        if ( 'delete' === $action && $poll_id ) {
            Evoting_Poll::delete( $poll_id );
            wp_safe_redirect( admin_url( 'admin.php?page=evoting&deleted=1' ) );
            exit;
        }

        $data = $this->sanitize_form_data();

        if ( is_wp_error( $data ) ) {
            set_transient( 'evoting_admin_error', $data->get_error_message(), 30 );
            wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=evoting' ) );
            exit;
        }

        if ( $poll_id ) {
            Evoting_Poll::update( $poll_id, $data );
            $redirect = admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $poll_id . '&updated=1' );
        } else {
            $new_id = Evoting_Poll::create( $data );
            if ( false === $new_id ) {
                set_transient( 'evoting_admin_error', __( 'Błąd zapisu głosowania.', 'evoting' ), 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=evoting-new' ) );
                exit;
            }

            // Send email notifications if requested.
            if ( ! empty( $data['notify_users'] ) ) {
                $this->send_notifications( $new_id, $data['title'] );
            }

            $redirect = admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $new_id . '&created=1' );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * @return array|\WP_Error
     */
    private function sanitize_form_data(): array|\WP_Error {
        $title = sanitize_text_field( $_POST['poll_title'] ?? '' );
        if ( '' === $title ) {
            return new \WP_Error( 'missing_title', __( 'Tytuł jest wymagany.', 'evoting' ) );
        }

        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );

        if ( '' === $start_date || '' === $end_date ) {
            return new \WP_Error( 'missing_dates', __( 'Daty rozpoczęcia i zakończenia są wymagane.', 'evoting' ) );
        }

        if ( $end_date <= $start_date ) {
            return new \WP_Error( 'invalid_dates', __( 'Data zakończenia musi być późniejsza niż data rozpoczęcia.', 'evoting' ) );
        }

        $questions = array_filter(
            array_map( 'sanitize_text_field', (array) ( $_POST['questions'] ?? [] ) ),
            fn( string $q ) => '' !== trim( $q )
        );

        if ( count( $questions ) < 1 ) {
            return new \WP_Error( 'missing_questions', __( 'Dodaj przynajmniej jedno pytanie.', 'evoting' ) );
        }

        if ( count( $questions ) > 12 ) {
            return new \WP_Error( 'too_many_questions', __( 'Maksymalnie 12 pytań.', 'evoting' ) );
        }

        return [
            'title'        => $title,
            'description'  => sanitize_textarea_field( $_POST['poll_description'] ?? '' ),
            'status'       => sanitize_text_field( $_POST['poll_status'] ?? 'draft' ),
            'start_date'   => $start_date,
            'end_date'     => $end_date,
            'notify_users' => ! empty( $_POST['notify_users'] ),
            'questions'    => array_values( $questions ),
        ];
    }

    /**
     * Send email notifications to all users about a new poll.
     */
    private function send_notifications( int $poll_id, string $title ): void {
        $users = get_users( [ 'fields' => [ 'user_email' ] ] );

        if ( empty( $users ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: poll title */
            __( 'Nowe głosowanie: %s', 'evoting' ),
            $title
        );

        $message = sprintf(
            /* translators: %s: poll title */
            __( "Zostało utworzone nowe głosowanie: %s\n\nZaloguj się, aby oddać swój głos.", 'evoting' ),
            $title
        );

        $emails = array_column( $users, 'user_email' );

        // Use BCC to protect privacy.
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        foreach ( $emails as $email ) {
            wp_mail( $email, $subject, $message, $headers );
        }
    }
}
