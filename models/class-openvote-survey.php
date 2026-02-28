<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Survey {

    private const ALLOWED_STATUSES = [ 'draft', 'open', 'closed' ];
    private const ALLOWED_FIELD_TYPES = [ 'text_short', 'text_long', 'url' ];
    const MAX_QUESTIONS = 20;

    // ── Table helpers ───────────────────────────────────────────────────────

    private static function surveys_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_surveys';
    }

    private static function questions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_survey_questions';
    }

    private static function responses_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_survey_responses';
    }

    private static function answers_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_survey_answers';
    }

    // ── CRUD — Surveys ──────────────────────────────────────────────────────

    /**
     * Pobierz ankietę po ID (z pytaniami).
     */
    public static function get( int $survey_id ): ?object {
        global $wpdb;

        $survey = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::surveys_table(), $survey_id )
        );

        if ( ! $survey ) {
            return null;
        }

        $survey->questions = self::get_questions( $survey_id );

        return $survey;
    }

    /**
     * Pobierz listę ankiet.
     *
     * @param array{status?: string, orderby?: string, order?: string} $args
     * @return object[]
     */
    public static function get_all( array $args = [] ): array {
        global $wpdb;

        $table = self::surveys_table();
        $where = '1=1';
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $order   = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $orderby = in_array( $args['orderby'] ?? '', [ 'title', 'date_start', 'date_end', 'status' ], true )
            ? sanitize_key( $args['orderby'] )
            : 'created_at';

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}";

        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (array) $wpdb->get_results( $sql );
    }

    /**
     * Utwórz nową ankietę.
     *
     * @param array{title: string, description?: string, status?: string, date_start: string, date_end: string, questions?: array} $data
     * @return int|false ID lub false przy błędzie.
     */
    public static function create( array $data ): int|false {
        global $wpdb;

        $result = $wpdb->insert(
            self::surveys_table(),
            [
                'title'       => sanitize_text_field( $data['title'] ),
                'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
                'status'      => in_array( $data['status'] ?? 'draft', self::ALLOWED_STATUSES, true ) ? $data['status'] : 'draft',
                'date_start'  => $data['date_start'],
                'date_end'    => $data['date_end'],
                'created_by'  => get_current_user_id(),
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( false === $result ) {
            return false;
        }

        $survey_id = (int) $wpdb->insert_id;

        if ( ! empty( $data['questions'] ) ) {
            self::save_questions( $survey_id, $data['questions'] );
        }

        return $survey_id;
    }

    /**
     * Zaktualizuj ankietę.
     *
     * @param array{title?: string, description?: string, status?: string, date_start?: string, date_end?: string, questions?: array} $data
     */
    public static function update( int $survey_id, array $data ): bool {
        global $wpdb;

        $update = [];
        $format = [];

        if ( isset( $data['title'] ) ) {
            $update['title'] = sanitize_text_field( $data['title'] );
            $format[]        = '%s';
        }
        if ( array_key_exists( 'description', $data ) ) {
            $update['description'] = $data['description'] ? sanitize_textarea_field( $data['description'] ) : null;
            $format[]              = '%s';
        }
        if ( isset( $data['status'] ) && in_array( $data['status'], self::ALLOWED_STATUSES, true ) ) {
            $update['status'] = $data['status'];
            $format[]         = '%s';
        }
        if ( isset( $data['date_start'] ) ) {
            $update['date_start'] = $data['date_start'];
            $format[]             = '%s';
        }
        if ( isset( $data['date_end'] ) ) {
            $update['date_end'] = $data['date_end'];
            $format[]           = '%s';
        }

        if ( ! empty( $update ) ) {
            $result = $wpdb->update(
                self::surveys_table(),
                $update,
                [ 'id' => $survey_id ],
                $format,
                [ '%d' ]
            );
            if ( false === $result ) {
                return false;
            }
        }

        if ( isset( $data['questions'] ) ) {
            self::save_questions( $survey_id, $data['questions'] );
        }

        return true;
    }

    /**
     * Duplikuj ankietę (kopiuje tytuł i pytania, bez odpowiedzi uczestników).
     *
     * @return int|false ID nowej ankiety lub false przy błędzie.
     */
    public static function duplicate( int $survey_id ): int|false {
        $original = self::get( $survey_id );
        if ( ! $original ) {
            return false;
        }

        $new_id = self::create( [
            'title'       => $original->title . ' ' . __( '(kopia)', 'openvote' ),
            'description' => $original->description,
            'status'      => 'draft',
            'date_start'  => current_time( 'Y-m-d H:i:s' ),
            'date_end'    => wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ),
        ] );

        if ( ! $new_id ) {
            return false;
        }

        // Skopiuj pytania.
        $questions = self::get_questions( $survey_id );
        if ( $questions ) {
            $q_data = array_map( fn( $q ) => [
                'body'       => $q->body,
                'field_type' => $q->field_type,
                'max_chars'  => (int) $q->max_chars,
            ], $questions );
            self::save_questions( $new_id, $q_data );
        }

        return $new_id;
    }

    /**
     * Usuń ankietę wraz z pytaniami i odpowiedziami uczestników.
     */
    public static function delete( int $survey_id ): bool {
        global $wpdb;

        $qt  = self::questions_table();
        $rt  = self::responses_table();
        $at  = self::answers_table();

        // Usuń odpowiedzi uczestników powiązane z pytaniami tej ankiety.
        $question_ids = $wpdb->get_col(
            $wpdb->prepare( 'SELECT id FROM %i WHERE survey_id = %d', $qt, $survey_id )
        );
        if ( $question_ids ) {
            $response_ids = $wpdb->get_col(
                $wpdb->prepare( 'SELECT id FROM %i WHERE survey_id = %d', $rt, $survey_id )
            );
            if ( $response_ids ) {
                $in = implode( ',', array_map( 'intval', $response_ids ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query( "DELETE FROM {$at} WHERE response_id IN ({$in})" );
            }
        }

        $wpdb->delete( $rt, [ 'survey_id' => $survey_id ], [ '%d' ] );
        $wpdb->delete( $qt, [ 'survey_id' => $survey_id ], [ '%d' ] );
        $wpdb->delete( self::surveys_table(), [ 'id' => $survey_id ], [ '%d' ] );

        return true;
    }

    // ── Questions ───────────────────────────────────────────────────────────

    /**
     * Pobierz pytania ankiety.
     *
     * @return object[]
     */
    public static function get_questions( int $survey_id ): array {
        global $wpdb;

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE survey_id = %d ORDER BY sort_order ASC, id ASC',
                self::questions_table(),
                $survey_id
            )
        );
    }

    /**
     * Zapisz pytania (zastępuje poprzednie).
     *
     * @param array<array{body: string, field_type: string, max_chars: int}> $questions
     */
    public static function save_questions( int $survey_id, array $questions ): void {
        global $wpdb;

        $qt = self::questions_table();
        $at = self::answers_table();
        $rt = self::responses_table();

        // Usuń wszystkie zgłoszenia ankiety (responses + survey_answers), aby uniknąć niespójności po zmianie pytań.
        $response_ids = $wpdb->get_col(
            $wpdb->prepare( 'SELECT id FROM %i WHERE survey_id = %d', $rt, $survey_id )
        );
        if ( $response_ids ) {
            $in = implode( ',', array_map( 'intval', $response_ids ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DELETE FROM {$at} WHERE response_id IN ({$in})" );
        }
        $wpdb->delete( $rt, [ 'survey_id' => $survey_id ], [ '%d' ] );

        // Usuń stare odpowiedzi uczestników powiązane ze starymi pytaniami, potem stare pytania.
        $old_ids = $wpdb->get_col(
            $wpdb->prepare( 'SELECT id FROM %i WHERE survey_id = %d', $qt, $survey_id )
        );
        if ( $old_ids ) {
            $in = implode( ',', array_map( 'intval', $old_ids ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DELETE FROM {$at} WHERE question_id IN ({$in})" );
        }

        $wpdb->delete( $qt, [ 'survey_id' => $survey_id ], [ '%d' ] );

        $allowed_profile = array_keys( Openvote_Field_Map::DEFAULTS );
        foreach ( array_values( $questions ) as $i => $q ) {
            $field_type = in_array( $q['field_type'] ?? 'text_short', self::ALLOWED_FIELD_TYPES, true )
                ? $q['field_type']
                : 'text_short';
            $max_chars  = max( 1, min( 2000, (int) ( $q['max_chars'] ?? 100 ) ) );
            $raw_pf     = isset( $q['profile_field'] ) ? (string) $q['profile_field'] : '';
            $profile_field = ( $raw_pf === '1' )
                ? '1'
                : ( in_array( $raw_pf, $allowed_profile, true ) ? $raw_pf : '' );

            $wpdb->insert(
                $qt,
                [
                    'survey_id'     => $survey_id,
                    'body'          => sanitize_text_field( $q['body'] ),
                    'field_type'    => $field_type,
                    'max_chars'     => $max_chars,
                    'sort_order'    => $i,
                    'profile_field' => $profile_field,
                ],
                [ '%d', '%s', '%s', '%d', '%d', '%s' ]
            );
        }
    }

    // ── Responses ───────────────────────────────────────────────────────────

    /**
     * Sprawdź czy użytkownik ma już jakąkolwiek odpowiedź (szkic lub gotową).
     */
    public static function has_response( int $survey_id, int $user_id ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE survey_id = %d AND user_id = %d',
                self::responses_table(),
                $survey_id,
                $user_id
            )
        );
    }

    /**
     * Pobierz odpowiedź użytkownika (jeśli istnieje).
     */
    public static function get_user_response( int $survey_id, int $user_id ): ?object {
        global $wpdb;

        $response = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE survey_id = %d AND user_id = %d',
                self::responses_table(),
                $survey_id,
                $user_id
            )
        );

        if ( ! $response ) {
            return null;
        }

        // Dołącz odpowiedzi na pytania jako tablicę [question_id => answer_text].
        $answers_raw = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT question_id, answer_text FROM %i WHERE response_id = %d',
                self::answers_table(),
                (int) $response->id
            )
        );
        $response->answers = [];
        foreach ( $answers_raw as $a ) {
            $response->answers[ (int) $a->question_id ] = $a->answer_text;
        }

        return $response;
    }

    /**
     * Zapisz lub zaktualizuj odpowiedź uczestnika (upsert).
     *
     * @param int    $survey_id
     * @param int    $user_id
     * @param array  $answers        [question_id => answer_text]
     * @param string $response_status 'draft' lub 'ready'
     * @param array  $user_data      [first_name, last_name, nickname, phone, email]
     * @return bool
     */
    public static function save_response(
        int $survey_id,
        int $user_id,
        array $answers,
        string $response_status,
        array $user_data
    ): bool {
        global $wpdb;

        $rt = self::responses_table();
        $at = self::answers_table();

        $status = in_array( $response_status, [ 'draft', 'ready' ], true ) ? $response_status : 'draft';
        $now    = current_time( 'mysql' );

        $existing_id = $wpdb->get_var(
            $wpdb->prepare( 'SELECT id FROM %i WHERE survey_id = %d AND user_id = %d', $rt, $survey_id, $user_id )
        );

        if ( $existing_id ) {
            // Zaktualizuj rekord odpowiedzi.
            $wpdb->update(
                $rt,
                [
                    'response_status' => $status,
                    'user_first_name' => sanitize_text_field( $user_data['first_name'] ?? '' ),
                    'user_last_name'  => sanitize_text_field( $user_data['last_name'] ?? '' ),
                    'user_nickname'   => sanitize_text_field( $user_data['nickname'] ?? '' ),
                    'user_phone'      => sanitize_text_field( $user_data['phone'] ?? '' ),
                    'user_email'      => sanitize_email( $user_data['email'] ?? '' ),
                    'updated_at'      => $now,
                ],
                [ 'id' => (int) $existing_id ],
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );

            // Usuń stare odpowiedzi na pytania i wstaw nowe.
            $wpdb->delete( $at, [ 'response_id' => (int) $existing_id ], [ '%d' ] );
            $response_id = (int) $existing_id;
        } else {
            // Wstaw nowy rekord.
            $result = $wpdb->insert(
                $rt,
                [
                    'survey_id'       => $survey_id,
                    'user_id'         => $user_id,
                    'response_status' => $status,
                    'user_first_name' => sanitize_text_field( $user_data['first_name'] ?? '' ),
                    'user_last_name'  => sanitize_text_field( $user_data['last_name'] ?? '' ),
                    'user_nickname'   => sanitize_text_field( $user_data['nickname'] ?? '' ),
                    'user_phone'      => sanitize_text_field( $user_data['phone'] ?? '' ),
                    'user_email'      => sanitize_email( $user_data['email'] ?? '' ),
                    'submitted_at'    => $now,
                    'updated_at'      => $now,
                ],
                [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
            );

            if ( false === $result ) {
                return false;
            }

            $response_id = (int) $wpdb->insert_id;
        }

        // Wstaw odpowiedzi na pytania.
        foreach ( $answers as $question_id => $answer_text ) {
            $result = $wpdb->insert(
                $at,
                [
                    'response_id' => $response_id,
                    'question_id' => (int) $question_id,
                    'answer_text' => sanitize_textarea_field( (string) $answer_text ),
                ],
                [ '%d', '%d', '%s' ]
            );
            if ( false === $result ) {
                error_log( 'openvote: save_response INSERT error (response_id=' . $response_id . '): ' . $wpdb->last_error );
                return false;
            }
        }

        return true;
    }

    /**
     * Pobierz listę odpowiedzi dla ankiety (tylko status 'ready').
     *
     * @return object[]
     */
    public static function get_responses( int $survey_id, int $page = 1, int $per_page = 15 ): array {
        global $wpdb;

        $rt     = self::responses_table();
        $offset = max( 0, ( $page - 1 ) * $per_page );

        $responses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$rt} WHERE survey_id = %d AND response_status = 'ready'
                 ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $survey_id,
                $per_page,
                $offset
            )
        );

        if ( ! $responses ) {
            return [];
        }

        $at = self::answers_table();

        foreach ( $responses as $response ) {
            $answers_raw = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT question_id, answer_text FROM %i WHERE response_id = %d',
                    $at,
                    (int) $response->id
                )
            );
            $response->answers = [];
            foreach ( $answers_raw as $a ) {
                $response->answers[ (int) $a->question_id ] = $a->answer_text;
            }
        }

        return $responses;
    }

    /**
     * Pobierz zgłoszenia ze statusem „nie spam” (ready + spam_status = 'not_spam').
     * Opcjonalnie tylko dla jednej ankiety. Do użycia w bloku publicznym.
     *
     * @param int $survey_id 0 = wszystkie ankiety
     * @param int $page
     * @param int $per_page
     * @return object[] Każdy element: id, survey_id, survey_title, user_*, updated_at, answers
     */
    public static function get_responses_not_spam( int $survey_id = 0, int $page = 1, int $per_page = 20 ): array {
        global $wpdb;

        $rt   = self::responses_table();
        $st   = self::surveys_table();
        $offset = max( 0, ( $page - 1 ) * $per_page );

        $where  = "r.response_status = 'ready' AND r.spam_status = 'not_spam'";
        $params = [];
        if ( $survey_id > 0 ) {
            $where  .= ' AND r.survey_id = %d';
            $params[] = $survey_id;
        }
        $params[] = $per_page;
        $params[] = $offset;

        $responses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.survey_id, r.user_id, r.user_first_name, r.user_last_name, r.user_nickname, r.user_phone, r.user_email, r.updated_at, s.title AS survey_title
                 FROM {$rt} r
                 INNER JOIN {$st} s ON s.id = r.survey_id
                 WHERE {$where}
                 ORDER BY r.updated_at DESC
                 LIMIT %d OFFSET %d",
                ...$params
            )
        );

        if ( ! $responses ) {
            return [];
        }

        $at = self::answers_table();
        foreach ( $responses as $response ) {
            $answers_raw = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT question_id, answer_text FROM %i WHERE response_id = %d',
                    $at,
                    (int) $response->id
                )
            );
            $response->answers = [];
            foreach ( $answers_raw as $a ) {
                $response->answers[ (int) $a->question_id ] = $a->answer_text;
            }
        }

        return $responses;
    }

    /**
     * Policz odpowiedzi (tylko 'ready') dla ankiety.
     */
    public static function count_responses( int $survey_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE survey_id = %d AND response_status = 'ready'",
                self::responses_table(),
                $survey_id
            )
        );
    }

    /**
     * Liczniki zgłoszeń (gotowych odpowiedzi) globalnie: nie spam / wszystkie.
     *
     * @return array{not_spam: int, total: int}
     */
    public static function get_response_counts_global(): array {
        global $wpdb;
        $rt    = self::responses_table();
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$rt} WHERE response_status = 'ready'"
        );
        $not_spam = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$rt} WHERE response_status = 'ready' AND spam_status = %s",
                'not_spam'
            )
        );
        return [ 'not_spam' => $not_spam, 'total' => $total ];
    }

    /**
     * Liczniki zgłoszeń (gotowych odpowiedzi) dla jednej ankiety: nie spam / wszystkie.
     *
     * @return array{not_spam: int, total: int}
     */
    public static function get_response_counts_for_survey( int $survey_id ): array {
        global $wpdb;
        $rt = self::responses_table();
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$rt} WHERE survey_id = %d AND response_status = 'ready'",
                $survey_id
            )
        );
        $not_spam = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$rt} WHERE survey_id = %d AND response_status = 'ready' AND spam_status = %s",
                $survey_id,
                'not_spam'
            )
        );
        return [ 'not_spam' => $not_spam, 'total' => $total ];
    }

    /**
     * Ustaw status spam dla odpowiedzi na ankietę.
     *
     * @param int    $response_id ID rekordu w wp_openvote_survey_responses.
     * @param string $status      'pending' | 'not_spam' | 'spam'
     * @return bool
     */
    public static function set_response_spam_status( int $response_id, string $status ): bool {
        $allowed = [ 'pending', 'not_spam', 'spam' ];
        if ( ! in_array( $status, $allowed, true ) ) {
            return false;
        }
        global $wpdb;
        $rt = self::responses_table();
        $n  = $wpdb->update(
            $rt,
            [ 'spam_status' => $status ],
            [ 'id' => $response_id ],
            [ '%s' ],
            [ '%d' ]
        );
        return $n !== false;
    }

    /**
     * Sprawdź czy ankieta jest aktualnie aktywna (status open i w przedziale dat).
     */
    public static function is_active( object $survey ): bool {
        if ( $survey->status !== 'open' ) {
            return false;
        }
        $now   = current_time( 'mysql' );
        return $now >= $survey->date_start && $now <= $survey->date_end;
    }

    /**
     * Pobierz dane profilowe użytkownika potrzebne do ankiety.
     *
     * @return array{first_name: string, last_name: string, nickname: string, phone: string, email: string}
     */
    public static function get_user_profile_data( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [ 'first_name' => '', 'last_name' => '', 'nickname' => '', 'phone' => '', 'email' => '' ];
        }

        // Numer telefonu — szukamy przez Field Map.
        $phone = '';
        if ( class_exists( 'Openvote_Field_Map' ) ) {
            $map = Openvote_Field_Map::get();
            if ( ! empty( $map['phone'] ) && $map['phone'] !== Openvote_Field_Map::NOT_SET_KEY ) {
                $phone = (string) get_user_meta( $user_id, $map['phone'], true );
            }
        }

        return [
            'first_name' => (string) $user->first_name,
            'last_name'  => (string) $user->last_name,
            'nickname'   => (string) $user->nickname,
            'phone'      => $phone,
            'email'      => (string) $user->user_email,
        ];
    }

    /**
     * Sprawdź czy profil użytkownika jest kompletny (wymagane pola).
     *
     * @return array{ok: bool, missing: string[]}
     */
    public static function check_user_profile( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [ 'ok' => false, 'missing' => [] ];
        }

        $required = Openvote_Field_Map::get_survey_required_fields();
        $missing  = [];

        foreach ( $required as $logical => $label ) {
            $value = Openvote_Field_Map::get_user_value( $user, $logical );
            if ( '' === trim( $value ) ) {
                $missing[ $logical ] = $label;
            }
        }

        return [ 'ok' => empty( $missing ), 'missing' => $missing ];
    }
}
