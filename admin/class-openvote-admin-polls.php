<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Admin_Polls {

    private const CAP = 'edit_others_posts';

    public function handle_form_submission(): void {
        if ( ! isset( $_POST['openvote_poll_nonce'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'openvote_save_poll', 'openvote_poll_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( self::CAP ) && ! Openvote_Admin::user_can_access_coordinators() ) {
            wp_die( esc_html__( 'Brak uprawnień.', 'openvote' ) );
        }

        $poll_id = isset( $_POST['poll_id'] ) ? absint( $_POST['poll_id'] ) : 0;
        $action  = sanitize_text_field( $_POST['openvote_action'] ?? 'create' );
        $extend_duration = ! empty( $_POST['openvote_extend_duration'] );

        if ( 'delete' === $action && $poll_id ) {
            // Te same kontrole co w ścieżce GET (uprawnienia, status != open, dostęp do grup, wpis w logu).
            $res = self::delete_checked( $poll_id );
            if ( is_wp_error( $res ) ) {
                set_transient( 'openvote_admin_error', $res->get_error_message(), 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote' ) );
                exit;
            }
            wp_safe_redirect( admin_url( 'admin.php?page=openvote&deleted=1' ) );
            exit;
        }

        // A5: koordynator ograniczony do własnych grup nie zmienia cudzych głosowań.
        if ( $poll_id ) {
            $guard = Openvote_Poll::get( $poll_id );
            if ( $guard && ! Openvote_Poll::current_user_can_access( $guard ) ) {
                wp_die( esc_html__( 'Brak uprawnień do tego głosowania.', 'openvote' ), '', [ 'response' => 403 ] );
            }
        }

        // Z podglądu: tylko aktualizacja czasu trwania (wydłużenie TRWAJĄCEGO głosowania).
        if ( $extend_duration && $poll_id && 'update' === $action ) {
            $existing = Openvote_Poll::get( $poll_id );
            if ( $existing && 'open' === $existing->status && ! empty( $existing->date_start ) ) {
                $duration_seconds = self::duration_seconds_map();
                $duration_key = sanitize_text_field( wp_unslash( $_POST['poll_duration'] ?? '7d' ) );
                if ( isset( $duration_seconds[ $duration_key ] ) ) {
                    $start_ts = strtotime( $existing->date_start );
                    $date_end = wp_date( 'Y-m-d H:i:s', $start_ts + $duration_seconds[ $duration_key ] );
                    if ( $date_end <= current_time( 'Y-m-d H:i:s' ) ) {
                        set_transient( 'openvote_admin_error', __( 'Nowy termin zakończenia musi być w przyszłości. Wybierz dłuższy czas trwania albo użyj „Zakończ”.', 'openvote' ), 30 );
                        wp_safe_redirect( add_query_arg( [ 'action' => 'view', 'poll_id' => $poll_id ], admin_url( 'admin.php?page=openvote' ) ) );
                        exit;
                    }
                    // notify_start celowo NIE jest ruszane (formularz podglądu go nie zawiera).
                    Openvote_Poll::update( $poll_id, [ 'date_end' => $date_end ] );
                    if ( ! empty( $existing->title ) ) {
                        openvote_polls_audit_log_append( get_current_user_id(), sprintf( __( 'zmienił czas trwania głosowania %s', 'openvote' ), $existing->title ) );
                    }
                    wp_safe_redirect( add_query_arg( [ 'action' => 'view', 'poll_id' => $poll_id, 'updated' => 1 ], admin_url( 'admin.php?page=openvote' ) ) );
                    exit;
                }
            }
            set_transient( 'openvote_admin_error', __( 'Czas trwania można zmienić tylko dla trwającego głosowania.', 'openvote' ), 30 );
            wp_safe_redirect( admin_url( 'admin.php?page=openvote' ) );
            exit;
        }

        if ( $poll_id && ! $extend_duration ) {
            $existing = Openvote_Poll::get( $poll_id );
            if ( $existing && 'draft' !== $existing->status ) {
                set_transient( 'openvote_admin_error', __( 'Nie można edytować głosowania, które zostało rozpoczęte lub zakończone.', 'openvote' ), 30 );
                wp_safe_redirect( add_query_arg( 'edit_locked', 1, admin_url( 'admin.php?page=openvote' ) ) );
                exit;
            }
        }

        // Status i data_start wg przycisku: Zapisz jako szkic / Wystartuj / Zaplanuj.
        $submit_action = sanitize_text_field( $_POST['openvote_submit_action'] ?? '' );
        $is_draft_save = ( 'save_draft' === $submit_action );

        $data = $this->sanitize_form_data( $is_draft_save );

        if ( is_wp_error( $data ) ) {
            set_transient( 'openvote_admin_error', $data->get_error_message(), 30 );
            // Zachowaj wpisane dane — formularz odtworzy je po przekierowaniu.
            set_transient( 'openvote_poll_form_draft_' . get_current_user_id(), $this->collect_form_draft( $poll_id ), 120 );
            $back = $poll_id
                ? admin_url( 'admin.php?page=openvote&action=edit&poll_id=' . $poll_id )
                : admin_url( 'admin.php?page=openvote&action=new' );
            wp_safe_redirect( $back );
            exit;
        }
        if ( $poll_id ) {
            $existing   = Openvote_Poll::get( $poll_id );
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

        $actor_id = get_current_user_id();
        $title_log = isset( $data['title'] ) ? $data['title'] : '';

        if ( $poll_id ) {
            Openvote_Poll::update( $poll_id, $data );
            if ( 'start_now' === $submit_action && $title_log !== '' ) {
                openvote_polls_audit_log_append( $actor_id, sprintf( __( 'wystartował głosowanie %s', 'openvote' ), $title_log ) );
            } elseif ( $title_log !== '' ) {
                openvote_polls_audit_log_append( $actor_id, sprintf( __( 'edytował głosowanie %s', 'openvote' ), $title_log ) );
            }
            $redirect = add_query_arg( 'updated', 1, admin_url( 'admin.php?page=openvote' ) );
            if ( 'start_now' === $submit_action ) {
                $redirect = add_query_arg( 'started', 1, $redirect );
            } elseif ( ! empty( $data['_incomplete'] ) ) {
                $redirect = add_query_arg( 'draft_incomplete', 1, $redirect );
            }
        } else {
            $new_id = Openvote_Poll::create( $data );
            if ( false === $new_id ) {
                set_transient( 'openvote_admin_error', __( 'Błąd zapisu głosowania.', 'openvote' ), 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=openvote&action=new' ) );
                exit;
            }
            $poll_id  = $new_id;
            if ( $title_log !== '' ) {
                openvote_polls_audit_log_append( $actor_id, sprintf( __( 'utworzył głosowanie %s', 'openvote' ), $title_log ) );
            }
            if ( 'start_now' === $submit_action && $title_log !== '' ) {
                openvote_polls_audit_log_append( $actor_id, sprintf( __( 'wystartował głosowanie %s', 'openvote' ), $title_log ) );
            }
            $redirect = ( 'start_now' === $submit_action )
                ? add_query_arg( 'started', 1, admin_url( 'admin.php?page=openvote' ) )
                : admin_url( 'admin.php?page=openvote&created=1' );
            if ( 'start_now' !== $submit_action && ! empty( $data['_incomplete'] ) ) {
                $redirect = add_query_arg( 'draft_incomplete', 1, $redirect );
            }
        }

        // Nie przekierowujemy na ekran wysyłki — użytkownik sam wchodzi w „Wysyłki”, jeśli chce wysłać zaproszenia (np. może rozreklamować głosowanie w mediach społecznościowych).
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * @param bool $is_draft_save True gdy kliknięto "Zapisz jako szkic" — pomijana walidacja wymaganych pól.
     * @return array|\WP_Error
     */
    private function sanitize_form_data( bool $is_draft_save = false ): array|\WP_Error {
        $title = trim( sanitize_text_field( wp_unslash( $_POST['poll_title'] ?? '' ) ) );
        if ( '' === $title ) {
            if ( $is_draft_save ) {
                $title = sprintf( __( 'Szkic %s', 'openvote' ), wp_date( 'd.m.Y H:i' ) );
            } else {
                return new \WP_Error( 'missing_title', __( 'Tytuł jest wymagany.', 'openvote' ) );
            }
        }
        if ( mb_strlen( $title ) > 512 ) {
            return new \WP_Error( 'title_too_long', __( 'Tytuł może zawierać maksymalnie 512 znaków.', 'openvote' ) );
        }

        $duration_key = sanitize_text_field( wp_unslash( $_POST['poll_duration'] ?? '7d' ) );
        $duration_seconds = self::duration_seconds_map();
        if ( ! isset( $duration_seconds[ $duration_key ] ) ) {
            if ( $is_draft_save ) {
                $duration_key = '7d';
            } else {
                return new \WP_Error( 'invalid_duration', __( 'Wybierz poprawny czas trwania głosowania.', 'openvote' ) );
            }
        }
        $date_start = current_time( 'Y-m-d H:i:s' );
        $date_end   = wp_date( 'Y-m-d H:i:s', time() + $duration_seconds[ $duration_key ] );

        // Parse questions with nested answers.
        $raw_questions = (array) ( $_POST['questions'] ?? [] );
        $questions     = [];
        $incomplete    = false;

        foreach ( array_values( $raw_questions ) as $q ) {
            if ( ! is_array( $q ) ) {
                continue;
            }

            $q_text = trim( sanitize_text_field( wp_unslash( $q['text'] ?? '' ) ) );
            if ( '' === $q_text ) {
                continue;
            }
            if ( mb_strlen( $q_text ) > 512 ) {
                return new \WP_Error( 'question_too_long', __( 'Każde pytanie może zawierać maksymalnie 512 znaków.', 'openvote' ) );
            }

            $raw_answers = array_values( (array) ( $q['answers'] ?? [] ) );
            $answers     = [];

            foreach ( $raw_answers as $a_text ) {
                $a_text = is_scalar( $a_text ) ? trim( sanitize_text_field( wp_unslash( (string) $a_text ) ) ) : '';
                if ( '' !== $a_text ) {
                    if ( mb_strlen( $a_text ) > 512 ) {
                        return new \WP_Error( 'answer_too_long', __( 'Każda odpowiedź może zawierać maksymalnie 512 znaków.', 'openvote' ) );
                    }
                    $answers[] = $a_text;
                }
            }

            // Duplikaty odpowiedzi (bez względu na wielkość liter) są niedozwolone.
            $seen = [];
            foreach ( $answers as $a ) {
                $k = mb_strtolower( $a );
                if ( isset( $seen[ $k ] ) ) {
                    return new \WP_Error(
                        'duplicate_answers',
                        sprintf( __( 'Odpowiedzi w pytaniu „%s” nie mogą się powtarzać.', 'openvote' ), $q_text )
                    );
                }
                $seen[ $k ] = true;
            }

            if ( count( $answers ) < 3 ) {
                if ( $is_draft_save ) {
                    // Szkic: zachowujemy pytanie z tym, co wpisano (walidacja dopiero przy starcie).
                    $incomplete = true;
                } else {
                    return new \WP_Error( 'too_few_answers', __( 'Każde pytanie: min. 2 własne odpowiedzi + „Wstrzymuję się” (razem 3–12).', 'openvote' ) );
                }
            }
            if ( count( $answers ) > 12 ) {
                return new \WP_Error( 'too_many_answers', __( 'Maksymalnie 12 odpowiedzi na pytanie.', 'openvote' ) );
            }

            $questions[] = [
                'text'    => $q_text,
                'answers' => $answers,
            ];
        }

        if ( count( $questions ) < 1 && ! $is_draft_save ) {
            return new \WP_Error( 'missing_questions', __( 'Dodaj przynajmniej jedno pytanie.', 'openvote' ) );
        }
        if ( count( $questions ) > 24 ) {
            return new \WP_Error( 'too_many_questions', __( 'Maksymalnie 24 pytania.', 'openvote' ) );
        }

        // target_groups: multi-select of group IDs → store as JSON.
        $raw_groups    = array_map( 'absint', (array) ( $_POST['target_groups'] ?? [] ) );
        // Grupy systemowe (system.Wszyscy): przy włączonym ograniczeniu koordynatorów wskazuje je administrator WP (manage_options)
        // albo koordynator, któremu grupa systemowa jest przypisana (openvote_groups). Nowe nieprzypisane odrzucamy;
        // zapisane wcześniej w edytowanym głosowaniu (przez administratora) zachowujemy.
        $edit_poll_id  = absint( $_POST['poll_id'] ?? 0 );
        $edit_poll     = $edit_poll_id ? Openvote_Poll::get( $edit_poll_id ) : null;
        $existing_tg   = $edit_poll ? Openvote_Poll::get_target_group_ids( $edit_poll ) : [];
        $policy        = Openvote_System_Groups::apply_target_policy( $raw_groups, $existing_tg );
        if ( ! empty( $policy['denied'] ) ) {
            return new \WP_Error( 'system_group_denied', __( 'Grupy systemowe (system.…) może wskazać jako docelowe tylko administrator WordPress lub koordynator, któremu dana grupa systemowa jest przypisana, gdy koordynatorzy są ograniczeni do własnych grup.', 'openvote' ) );
        }
        $sys_existing  = array_values( array_intersect( $existing_tg, Openvote_System_Groups::get_system_group_ids() ) );
        $raw_groups    = $policy['ids'];
        // A5: koordynator ograniczony do własnych grup może wskazać wyłącznie swoje grupy (także przypisane mu grupy
        // systemowe) — egzekwowane po stronie serwera.
        if ( function_exists( 'openvote_is_coordinator_restricted_to_own_groups' ) && openvote_is_coordinator_restricted_to_own_groups() ) {
            $own = openvote_coordinator_own_group_ids();
            if ( ! Openvote_System_Groups::current_user_can_target() ) {
                $own = array_merge( $own, $sys_existing );
            }
            $raw_groups = array_values( array_intersect( $raw_groups, $own ) );
            if ( empty( $raw_groups ) ) {
                return new \WP_Error( 'missing_group', __( 'Wybierz co najmniej jedną ze swoich grup docelowych.', 'openvote' ) );
            }
        }
        // E: głosowanie bez grup docelowych = „głosować mogą wszyscy” — przy ograniczeniu koordynatorów tylko manage_options
        // (dotyczy też edycji głosowania utworzonego przez administratora bez grup oraz zapisu szkicu).
        if ( empty( $raw_groups ) && function_exists( 'openvote_current_user_can_target_everyone' ) && ! openvote_current_user_can_target_everyone() ) {
            return new \WP_Error( 'missing_group', __( 'Głosowanie bez grup docelowych (dostępne dla wszystkich) może zapisać tylko administrator WordPress, gdy koordynatorzy są ograniczeni do własnych grup. Wybierz co najmniej jedną grupę docelową.', 'openvote' ) );
        }
        $target_groups = ! empty( $raw_groups ) ? wp_json_encode( array_values( $raw_groups ) ) : '';

        return [
            'title'         => $title,
            'description'   => sanitize_textarea_field( wp_unslash( $_POST['poll_description'] ?? '' ) ),
            'date_start'    => $date_start,
            'date_end'      => $date_end,
            'target_groups' => $target_groups,
            'notify_start'  => ! empty( $_POST['notify_start'] ),
            'questions'     => $questions,
            '_incomplete'   => $incomplete,
        ];
    }

    /**
     * Mapa kluczy czasu trwania → sekundy (jedno miejsce dla formularza i podglądu).
     *
     * @return array<string,int>
     */
    public static function duration_seconds_map(): array {
        return [
            '1h'  => 3600,
            '12h' => 12 * 3600,
            '1d'  => DAY_IN_SECONDS,
            '7d'  => 7 * DAY_IN_SECONDS,
            '14d' => 14 * DAY_IN_SECONDS,
            '21d' => 21 * DAY_IN_SECONDS,
            '28d' => 28 * DAY_IN_SECONDS,
        ];
    }

    /**
     * Usuń głosowanie z pełnymi kontrolami (wspólne dla GET, POST i akcji zbiorczych):
     * manage_options, status inny niż „trwa”, dostęp do grup, wpis w logu czynności.
     *
     * @return true|\WP_Error
     */
    public static function delete_checked( int $poll_id ): bool|\WP_Error {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'forbidden', __( 'Tylko administrator może usuwać głosowania.', 'openvote' ) );
        }
        $poll = Openvote_Poll::get( $poll_id );
        if ( ! $poll ) {
            return new \WP_Error( 'not_found', __( 'Głosowanie nie istnieje.', 'openvote' ) );
        }
        if ( 'open' === $poll->status ) {
            return new \WP_Error( 'poll_open', __( 'Trwającego głosowania nie można usunąć. Najpierw je zakończ.', 'openvote' ) );
        }
        $title = (string) ( $poll->title ?? '' );
        Openvote_Poll::delete( $poll_id );
        if ( '' !== $title ) {
            openvote_polls_audit_log_append( get_current_user_id(), sprintf( __( 'usunął głosowanie %s', 'openvote' ), $title ) );
        }
        return true;
    }

    /**
     * Zbierz wpisane dane formularza (do odtworzenia po błędzie walidacji).
     */
    private function collect_form_draft( int $poll_id ): array {
        $qs = [];
        foreach ( array_values( (array) ( $_POST['questions'] ?? [] ) ) as $q ) {
            if ( ! is_array( $q ) ) {
                continue;
            }
            $answers = [];
            foreach ( array_values( (array) ( $q['answers'] ?? [] ) ) as $a ) {
                $answers[] = sanitize_text_field( wp_unslash( (string) $a ) );
            }
            $qs[] = [
                'text'    => sanitize_text_field( wp_unslash( $q['text'] ?? '' ) ),
                'answers' => $answers,
            ];
        }
        return [
            'poll_id'       => $poll_id,
            'title'         => sanitize_text_field( wp_unslash( $_POST['poll_title'] ?? '' ) ),
            'description'   => sanitize_textarea_field( wp_unslash( $_POST['poll_description'] ?? '' ) ),
            'duration'      => sanitize_text_field( wp_unslash( $_POST['poll_duration'] ?? '7d' ) ),
            'target_groups' => array_map( 'absint', (array) ( $_POST['target_groups'] ?? [] ) ),
            'notify_start'  => ! empty( $_POST['notify_start'] ),
            'questions'     => $qs,
        ];
    }
}
