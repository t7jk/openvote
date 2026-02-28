<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Admin_Surveys {

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['openvote_survey_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'openvote_save_survey', 'openvote_survey_nonce' ) ) {
            return;
        }

        if ( ! Openvote_Admin::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $survey_id = isset( $_POST['survey_id'] ) ? absint( $_POST['survey_id'] ) : 0;
        $action    = sanitize_text_field( $_POST['openvote_action'] ?? 'create' );

        if ( 'delete' === $action && $survey_id ) {
            Openvote_Survey::delete( $survey_id );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&deleted=1' ) );
            exit;
        }

        $data = $this->sanitize_form_data();

        if ( is_wp_error( $data ) ) {
            set_transient( 'openvote_survey_admin_error', $data->get_error_message(), 30 );
            wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=openvote-surveys' ) );
            exit;
        }

        // Przycisk submit określa status.
        $submit_action = sanitize_text_field( $_POST['openvote_submit_action'] ?? 'draft' );
        $data['status'] = ( 'start_now' === $submit_action ) ? 'open' : 'draft';

        if ( $survey_id ) {
            Openvote_Survey::update( $survey_id, $data );
            $redirect = add_query_arg( 'updated', 1, admin_url( 'admin.php?page=openvote-surveys' ) );
        } else {
            $new_id = Openvote_Survey::create( $data );
            if ( false === $new_id ) {
                set_transient( 'openvote_survey_admin_error', __( 'Błąd zapisu ankiety.', 'openvote' ), 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote-surveys&action=new' ) );
                exit;
            }
            $redirect = admin_url( 'admin.php?page=openvote-surveys&created=1' );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * @return array|\WP_Error
     */
    private function sanitize_form_data(): array|\WP_Error {
        $title = sanitize_text_field( wp_unslash( $_POST['survey_title'] ?? '' ) );
        if ( '' === $title ) {
            return new \WP_Error( 'missing_title', __( 'Tytuł ankiety jest wymagany.', 'openvote' ) );
        }
        if ( mb_strlen( $title ) > 512 ) {
            return new \WP_Error( 'title_too_long', __( 'Tytuł może zawierać maksymalnie 512 znaków.', 'openvote' ) );
        }

        $description = sanitize_textarea_field( wp_unslash( $_POST['survey_description'] ?? '' ) );
        if ( mb_strlen( $description ) > 5000 ) {
            return new \WP_Error( 'desc_too_long', __( 'Opis może zawierać maksymalnie 5000 znaków.', 'openvote' ) );
        }

        $duration_seconds = [
            '1h'  => 3600,
            '1d'  => DAY_IN_SECONDS,
            '2d'  => 2 * DAY_IN_SECONDS,
            '3d'  => 3 * DAY_IN_SECONDS,
            '7d'  => 7 * DAY_IN_SECONDS,
            '14d' => 14 * DAY_IN_SECONDS,
            '21d' => 21 * DAY_IN_SECONDS,
            '30d' => 30 * DAY_IN_SECONDS,
        ];

        $duration_key = sanitize_text_field( $_POST['survey_duration'] ?? '7d' );
        if ( ! isset( $duration_seconds[ $duration_key ] ) ) {
            return new \WP_Error( 'invalid_duration', __( 'Wybierz poprawny czas trwania ankiety.', 'openvote' ) );
        }

        $date_start = current_time( 'Y-m-d H:i:s' );
        $date_end   = gmdate( 'Y-m-d H:i:s', strtotime( $date_start ) + $duration_seconds[ $duration_key ] );

        // Parse questions.
        $raw_questions = (array) ( $_POST['survey_questions'] ?? [] );
        $questions     = [];

        foreach ( array_values( $raw_questions ) as $q ) {
            if ( ! is_array( $q ) ) {
                continue;
            }
            $body = trim( sanitize_text_field( wp_unslash( $q['body'] ?? '' ) ) );
            if ( '' === $body ) {
                continue;
            }
            if ( mb_strlen( $body ) > 512 ) {
                return new \WP_Error( 'question_too_long', __( 'Etykieta pola może zawierać maksymalnie 512 znaków.', 'openvote' ) );
            }

            $field_type = sanitize_key( wp_unslash( $q['field_type'] ?? 'text_short' ) );
            if ( ! in_array( $field_type, [ 'text_short', 'text_long', 'numeric', 'url', 'email' ], true ) ) {
                $field_type = 'text_short';
            }

            $max_chars = match ( $field_type ) {
                'text_short' => 100,
                'text_long'  => 2000,
                'numeric'    => 30,
                'url'        => 255,
                'email'      => 255,
                default      => 100,
            };

            $allowed_profile = array_keys( Openvote_Field_Map::DEFAULTS );
            $profile_field   = isset( $q['profile_field'] ) && in_array( $q['profile_field'], $allowed_profile, true )
                ? $q['profile_field']
                : '';

            $questions[] = [
                'body'          => $body,
                'field_type'    => $field_type,
                'max_chars'     => $max_chars,
                'profile_field' => $profile_field,
            ];
        }

        if ( count( $questions ) < 1 ) {
            return new \WP_Error( 'no_questions', __( 'Dodaj przynajmniej jedno pole ankiety.', 'openvote' ) );
        }
        if ( count( $questions ) > Openvote_Survey::MAX_QUESTIONS ) {
            return new \WP_Error(
                'too_many_questions',
                sprintf( __( 'Maksymalnie %d pól ankiety.', 'openvote' ), Openvote_Survey::MAX_QUESTIONS )
            );
        }

        return [
            'title'       => $title,
            'description' => $description,
            'date_start'  => $date_start,
            'date_end'    => $date_end,
            'questions'   => $questions,
        ];
    }

}
