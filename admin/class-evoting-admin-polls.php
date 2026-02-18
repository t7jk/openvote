<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Admin_Polls {

    private const CAP = 'edit_others_posts';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['evoting_poll_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'evoting_save_poll', 'evoting_poll_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $poll_id = isset( $_POST['poll_id'] ) ? absint( $_POST['poll_id'] ) : 0;
        $action  = sanitize_text_field( $_POST['evoting_action'] ?? 'create' );

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
        if ( mb_strlen( $title ) > 512 ) {
            return new \WP_Error( 'title_too_long', __( 'Tytuł może zawierać maksymalnie 512 znaków.', 'evoting' ) );
        }

        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );

        if ( '' === $start_date || '' === $end_date ) {
            return new \WP_Error( 'missing_dates', __( 'Daty rozpoczęcia i zakończenia są wymagane.', 'evoting' ) );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
            return new \WP_Error( 'invalid_date_format', __( 'Format daty: RRRR-MM-DD.', 'evoting' ) );
        }

        if ( $end_date < $start_date ) {
            return new \WP_Error( 'invalid_dates', __( 'Data zakończenia musi być taka sama lub późniejsza niż data rozpoczęcia.', 'evoting' ) );
        }

        // Parse questions with nested answers.
        $raw_questions = (array) ( $_POST['questions'] ?? [] );
        $questions     = [];

        foreach ( array_values( $raw_questions ) as $q ) {
            if ( ! is_array( $q ) ) {
                continue;
            }

            $q_text = trim( sanitize_text_field( $q['text'] ?? '' ) );
            if ( '' === $q_text ) {
                continue;
            }
            if ( mb_strlen( $q_text ) > 512 ) {
                return new \WP_Error( 'question_too_long', __( 'Każde pytanie może zawierać maksymalnie 512 znaków.', 'evoting' ) );
            }

            $raw_answers = array_values( (array) ( $q['answers'] ?? [] ) );
            $answers     = [];

            foreach ( $raw_answers as $a_text ) {
                $a_text = trim( sanitize_text_field( $a_text ) );
                if ( '' !== $a_text ) {
                    $answers[] = $a_text;
                }
            }

            if ( count( $answers ) < 2 ) {
                return new \WP_Error( 'too_few_answers', __( 'Każde pytanie musi mieć co najmniej 2 odpowiedzi.', 'evoting' ) );
            }
            if ( count( $answers ) > 12 ) {
                return new \WP_Error( 'too_many_answers', __( 'Maksymalnie 12 odpowiedzi per pytanie.', 'evoting' ) );
            }

            $questions[] = [
                'text'    => $q_text,
                'answers' => $answers,
            ];
        }

        if ( count( $questions ) < 1 ) {
            return new \WP_Error( 'missing_questions', __( 'Dodaj przynajmniej jedno pytanie.', 'evoting' ) );
        }
        if ( count( $questions ) > 12 ) {
            return new \WP_Error( 'too_many_questions', __( 'Maksymalnie 12 pytań.', 'evoting' ) );
        }

        $target_type  = in_array( $_POST['target_type'] ?? 'all', [ 'all', 'group' ], true )
            ? sanitize_text_field( $_POST['target_type'] )
            : 'all';
        $target_group = ( 'group' === $target_type )
            ? sanitize_text_field( $_POST['target_group'] ?? '' )
            : '';

        if ( 'group' === $target_type && '' === $target_group ) {
            return new \WP_Error( 'missing_group', __( 'Wybierz grupę docelową.', 'evoting' ) );
        }

        return [
            'title'        => $title,
            'description'  => sanitize_textarea_field( $_POST['poll_description'] ?? '' ),
            'status'       => sanitize_text_field( $_POST['poll_status'] ?? 'draft' ),
            'start_date'   => $start_date,
            'end_date'     => $end_date,
            'target_type'  => $target_type,
            'target_group' => $target_group,
            'notify_users' => ! empty( $_POST['notify_users'] ),
            'questions'    => $questions,
        ];
    }

    private function send_notifications( int $poll_id, string $title ): void {
        $users = get_users( [ 'fields' => [ 'user_email' ] ] );

        if ( empty( $users ) ) {
            return;
        }

        $subject = sprintf( __( 'Nowe głosowanie: %s', 'evoting' ), $title );
        $message = sprintf(
            __( "Zostało utworzone nowe głosowanie: %s\n\nZaloguj się, aby oddać swój głos.", 'evoting' ),
            $title
        );
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        foreach ( array_column( $users, 'user_email' ) as $email ) {
            wp_mail( $email, $subject, $message, $headers );
        }
    }
}
