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

        if ( ! current_user_can( self::CAP ) && ! Evoting_Admin::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'evoting' ) );
        }

        $poll_id = isset( $_POST['poll_id'] ) ? absint( $_POST['poll_id'] ) : 0;
        $action  = sanitize_text_field( $_POST['evoting_action'] ?? 'create' );

        if ( 'delete' === $action && $poll_id ) {
            Evoting_Poll::delete( $poll_id );
            wp_safe_redirect( admin_url( 'admin.php?page=evoting&deleted=1' ) );
            exit;
        }

        if ( $poll_id ) {
            $existing = Evoting_Poll::get( $poll_id );
            if ( $existing && 'draft' !== $existing->status ) {
                set_transient( 'evoting_admin_error', __( 'Nie można edytować głosowania, które zostało rozpoczęte lub zakończone.', 'evoting' ), 30 );
                wp_safe_redirect( add_query_arg( 'edit_locked', 1, admin_url( 'admin.php?page=evoting' ) ) );
                exit;
            }
        }

        $data = $this->sanitize_form_data();

        if ( is_wp_error( $data ) ) {
            set_transient( 'evoting_admin_error', $data->get_error_message(), 30 );
            wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=evoting' ) );
            exit;
        }

        // Status i data_start wg przycisku: Zapisz jako szkic / Wystartuj / Zaplanuj.
        $submit_action = sanitize_text_field( $_POST['evoting_submit_action'] ?? '' );
        if ( $poll_id ) {
            $existing   = Evoting_Poll::get( $poll_id );
            $was_open   = $existing && 'open' === $existing->status;
            if ( 'start_now' === $submit_action ) {
                $data['status']     = 'open';
                $data['date_start'] = current_time( 'Y-m-d H:i:s' );
                if ( $data['date_end'] < $data['date_start'] ) {
                    $data['date_end'] = $data['date_start'];
                }
            } else {
                $data['status'] = 'draft';
            }
        } else {
            $data['status'] = ( 'start_now' === $submit_action ) ? 'open' : 'draft';
            $was_open       = false;
        }

        if ( $poll_id ) {
            Evoting_Poll::update( $poll_id, $data );
            $redirect = add_query_arg( 'updated', 1, admin_url( 'admin.php?page=evoting' ) );
            if ( 'start_now' === $submit_action ) {
                $redirect = add_query_arg( 'started', 1, $redirect );
            }
        } else {
            $new_id = Evoting_Poll::create( $data );
            if ( false === $new_id ) {
                set_transient( 'evoting_admin_error', __( 'Błąd zapisu głosowania.', 'evoting' ), 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=evoting&action=new' ) );
                exit;
            }
            $poll_id  = $new_id;
            $redirect = ( 'start_now' === $submit_action )
                ? add_query_arg( 'started', 1, admin_url( 'admin.php?page=evoting' ) )
                : admin_url( 'admin.php?page=evoting&created=1' );
        }

        // Automatyczna wysyłka zaproszeń przez system kolejki batch.
        if ( ! empty( $data['notify_start'] ) && 'open' === $data['status'] && ! $was_open ) {
            $redirect = add_query_arg( 'autostart', '1', admin_url( 'admin.php?page=evoting&action=invitations&poll_id=' . $poll_id ) );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * @return array|\WP_Error
     */
    private function sanitize_form_data(): array|\WP_Error {
        $title = sanitize_text_field( wp_unslash( $_POST['poll_title'] ?? '' ) );
        if ( '' === $title ) {
            return new \WP_Error( 'missing_title', __( 'Tytuł jest wymagany.', 'evoting' ) );
        }
        if ( mb_strlen( $title ) > 512 ) {
            return new \WP_Error( 'title_too_long', __( 'Tytuł może zawierać maksymalnie 512 znaków.', 'evoting' ) );
        }

        $duration_key = sanitize_text_field( wp_unslash( $_POST['poll_duration'] ?? '7d' ) );
        $duration_seconds = [
            '1h'  => 3600,
            '1d'  => DAY_IN_SECONDS,
            '2d'  => 2 * DAY_IN_SECONDS,
            '3d'  => 3 * DAY_IN_SECONDS,
            '7d'  => 7 * DAY_IN_SECONDS,
            '14d' => 14 * DAY_IN_SECONDS,
            '21d' => 21 * DAY_IN_SECONDS,
        ];
        if ( ! isset( $duration_seconds[ $duration_key ] ) ) {
            return new \WP_Error( 'invalid_duration', __( 'Wybierz poprawny czas trwania głosowania.', 'evoting' ) );
        }
        $date_start = current_time( 'Y-m-d H:i:s' );
        $date_end   = gmdate( 'Y-m-d H:i:s', strtotime( $date_start ) + $duration_seconds[ $duration_key ] );

        // Parse questions with nested answers.
        $raw_questions = (array) ( $_POST['questions'] ?? [] );
        $questions     = [];

        foreach ( array_values( $raw_questions ) as $q ) {
            if ( ! is_array( $q ) ) {
                continue;
            }

            $q_text = trim( sanitize_text_field( wp_unslash( $q['text'] ?? '' ) ) );
            if ( '' === $q_text ) {
                continue;
            }
            if ( mb_strlen( $q_text ) > 512 ) {
                return new \WP_Error( 'question_too_long', __( 'Każde pytanie może zawierać maksymalnie 512 znaków.', 'evoting' ) );
            }

            $raw_answers = array_values( (array) ( $q['answers'] ?? [] ) );
            $answers     = [];

            foreach ( $raw_answers as $a_text ) {
                $a_text = trim( sanitize_text_field( wp_unslash( $a_text ) ) );
                if ( '' !== $a_text ) {
                    $answers[] = $a_text;
                }
            }

            if ( count( $answers ) < 3 ) {
                return new \WP_Error( 'too_few_answers', __( 'Każde pytanie musi mieć co najmniej 3 odpowiedzi (w tym obowiązkową abstencję).', 'evoting' ) );
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
        if ( count( $questions ) > 24 ) {
            return new \WP_Error( 'too_many_questions', __( 'Maksymalnie 24 pytania.', 'evoting' ) );
        }

        // target_groups: multi-select of group IDs → store as JSON.
        $raw_groups    = array_map( 'absint', (array) ( $_POST['target_groups'] ?? [] ) );
        $target_groups = ! empty( $raw_groups ) ? wp_json_encode( array_values( $raw_groups ) ) : '';

        return [
            'title'         => $title,
            'description'   => sanitize_textarea_field( wp_unslash( $_POST['poll_description'] ?? '' ) ),
            'date_start'    => $date_start,
            'date_end'      => $date_end,
            'target_groups' => $target_groups,
            'notify_start'  => ! empty( $_POST['notify_start'] ),
            'questions'     => $questions,
        ];
    }

    /**
     * Normalizes datetime from form (YYYY-MM-DD, YYYY-MM-DDTHH:mm, or Y-m-d H:i:s) to DB format Y-m-d H:i:s.
     *
     * @param string $raw Sanitized POST value.
     * @return string|false DB datetime 'Y-m-d H:i:s' or false if invalid.
     */
    private static function normalize_datetime_for_db( string $raw ): string|false {
        $raw = trim( $raw );
        if ( '' === $raw ) {
            return false;
        }
        // datetime-local: YYYY-MM-DDTHH:mm or YYYY-MM-DDTHH:mm:ss
        if ( preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m ) ) {
            $d = $m[1];
            $h = (int) $m[2];
            $i = (int) $m[3];
            $s = isset( $m[4] ) ? (int) $m[4] : 0;
            if ( $h < 0 || $h > 23 || $i < 0 || $i > 59 || $s < 0 || $s > 59 ) {
                return false;
            }
            return sprintf( '%s %02d:%02d:%02d', $d, $h, $i, $s );
        }
        // date only: YYYY-MM-DD
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            return $raw . ' 00:00:00';
        }
        // already Y-m-d H:i or Y-m-d H:i:s
        if ( preg_match( '/^(\d{4}-\d{2}-\d{2})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m ) ) {
            $d = $m[1];
            $h = (int) $m[2];
            $i = (int) $m[3];
            $s = isset( $m[4] ) ? (int) $m[4] : 0;
            if ( $h < 0 || $h > 23 || $i < 0 || $i > 59 || $s < 0 || $s > 59 ) {
                return false;
            }
            return sprintf( '%s %02d:%02d:%02d', $d, $h, $i, $s );
        }
        return false;
    }

    /**
     * Legacy: wysyłka powiadomień e-mail do użytkowników (max 500 w jednym żądaniu).
     * Dla dużych baz (np. 10k+) należy używać systemu zaproszeń (batch) z ekranu Wyniki / Zaproszenia.
     */
    private function send_notifications( int $poll_id, string $title ): void {
        $max_recipients = 500;
        $users = get_users( [
            'fields'  => [ 'user_email' ],
            'number'  => $max_recipients,
            'orderby' => 'ID',
        ] );

        if ( empty( $users ) ) {
            return;
        }

        $subject = sprintf( __( 'Nowe głosowanie: %s', 'evoting' ), $title );
        $message = sprintf(
            __( "Zostało otwarte nowe głosowanie: %s\n\nZaloguj się, aby oddać swój głos.", 'evoting' ),
            $title
        );
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        foreach ( array_column( $users, 'user_email' ) as $email ) {
            if ( ! is_email( $email ) ) {
                continue;
            }
            wp_mail( $email, $subject, $message, $headers );
        }
    }
}
