<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Activator {

    const DB_VERSION = '4.9.0';

    /** Nazwa grupy testowej (nie można jej usunąć z UI, tylko wyłączyć w Ustawieniach). */
    const TEST_GROUP_NAME = 'Test';

    /** Ustawiane, gdy migracja 4.9.0 nie zdołała dodać kolumny system_key — wersja schematu nie jest wtedy podnoszona. */
    private static bool $schema_failed = false;

    /** Wersja do zapisania w openvote_db_version po migracjach. */
    private static function version_to_store(): string {
        return self::$schema_failed ? '4.8.0' : self::DB_VERSION;
    }

    public static function activate(): void {
        self::create_tables();
        self::run_migrations();
        update_option( 'openvote_version', OPENVOTE_VERSION );
        update_option( 'openvote_db_version', self::version_to_store() );
        if ( get_option( 'openvote_create_test_group', null ) === null ) {
            update_option( 'openvote_create_test_group', 1, false );
        }
        self::ensure_test_group_exists();
        require_once __DIR__ . '/class-openvote-system-groups.php';
        Openvote_System_Groups::flush_cache();
        Openvote_System_Groups::ensure_groups( true );
        Openvote_System_Groups::cleanup_legacy_role_groups();
        self::ensure_city_required_default();
        require_once __DIR__ . '/class-openvote-vote-page.php';
        Openvote_Vote_Page::add_rewrite_rule();

        require_once __DIR__ . '/class-openvote-survey-page.php';
        Openvote_Survey_Page::add_rewrite_rule();

        require_once __DIR__ . '/class-openvote-cron-sync.php';
        Openvote_Cron_Sync::reschedule();

        flush_rewrite_rules();
    }

    /**
     * Automatyczna aktualizacja schematu DB gdy wersja w opcjach jest starsza niż DB_VERSION.
     * Wywoływana z admin_init żeby brakujące tabele były tworzone bez konieczności
     * ręcznego deaktywowania i aktywowania wtyczki.
     */
    public static function maybe_upgrade(): void {
        global $wpdb;

        $installed    = get_option( 'openvote_db_version', '1.0.0' );
        $version_old  = version_compare( $installed, self::DB_VERSION, '<' );

        // Sprawdź czy tabele fizycznie istnieją.
        $eq_table      = $wpdb->prefix . 'openvote_email_queue';
        $surv_table    = $wpdb->prefix . 'openvote_surveys';
        $messages_table = $wpdb->prefix . 'openvote_messages';
        $table_missing = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $eq_table ) ) !== $eq_table
                      || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $surv_table ) ) !== $surv_table
                      || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $messages_table ) ) !== $messages_table;

        if ( $version_old || $table_missing ) {
            self::create_tables();
            self::run_migrations();
            update_option( 'openvote_db_version', self::version_to_store() );
            if ( class_exists( 'Openvote_System_Groups' ) ) {
                Openvote_System_Groups::flush_cache();
                Openvote_System_Groups::ensure_groups( true );
            }
        }
        // Jednorazowe zmiany danych 4.9.0 niezależne od numeru wersji (dev, gdzie 4.9.0 przeszło wcześniej).
        if ( class_exists( 'Openvote_System_Groups' ) && Openvote_System_Groups::schema_ready() ) {
            Openvote_System_Groups::cleanup_legacy_role_groups();
        }
        self::ensure_city_required_default();
    }

    /** Znacznik jednorazowej zmiany B (4.9.0): pole „Grupa” domyślnie wymagane do głosowania. */
    const OPT_CITY_REQUIRED_DONE = 'openvote_city_required_default_done';

    /**
     * Jednorazowo (4.9.0): gdy pole „Grupa” jest zmapowane, dodaj 'city' do openvote_required_fields (jeśli brak)
     * z wpisem w dzienniku głosowań. Odróżnia członków od kont technicznych obsługi. Administrator może potem odznaczyć.
     * Gdy pole jest wyłączone („Nie używaj miast”) / niezmapowane — tylko znacznik (domyślna wartość opcji i tak zawiera city).
     *
     * @return bool Czy dodano 'city'.
     */
    public static function ensure_city_required_default(): bool {
        if ( get_option( self::OPT_CITY_REQUIRED_DONE ) ) {
            return false;
        }
        $added = false;
        if ( class_exists( 'Openvote_Field_Map' ) && null !== Openvote_Field_Map::grouping_field_key() ) {
            $raw = get_option( Openvote_Field_Map::REQUIRED_FIELDS_OPTION, null );
            $req = is_array( $raw ) ? $raw : [];
            if ( ! in_array( 'city', $req, true ) ) {
                $req[] = 'city';
                update_option( Openvote_Field_Map::REQUIRED_FIELDS_OPTION, array_values( $req ), false );
                $added = true;
                if ( function_exists( 'openvote_polls_audit_log_append' ) ) {
                    openvote_polls_audit_log_append( 0, __( 'aktualizacja 4.9.0: pole „Grupa” ustawiono jako wymagane do głosowania (można odznaczyć w Konfiguracji)', 'openvote' ) );
                }
            }
        }
        update_option( self::OPT_CITY_REQUIRED_DONE, 1, true );
        return $added;
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $polls         = $wpdb->prefix . 'openvote_polls';
        $questions     = $wpdb->prefix . 'openvote_questions';
        $answers       = $wpdb->prefix . 'openvote_answers';
        $votes         = $wpdb->prefix . 'openvote_votes';
        $groups        = $wpdb->prefix . 'openvote_groups';
        $group_members = $wpdb->prefix . 'openvote_group_members';
        $email_queue      = $wpdb->prefix . 'openvote_email_queue';
        $surveys          = $wpdb->prefix . 'openvote_surveys';
        $survey_questions = $wpdb->prefix . 'openvote_survey_questions';
        $survey_responses = $wpdb->prefix . 'openvote_survey_responses';
        $survey_answers   = $wpdb->prefix . 'openvote_survey_answers';
        $messages        = $wpdb->prefix . 'openvote_messages';
        $message_queue   = $wpdb->prefix . 'openvote_message_queue';

        $sql = "CREATE TABLE {$polls} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title         VARCHAR(512) NOT NULL,
            description   TEXT,
            status        ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
            join_mode     ENUM('open','closed') NOT NULL DEFAULT 'open',
            vote_mode     ENUM('public','anonymous') NOT NULL DEFAULT 'public',
            target_groups TEXT,
            notify_start  TINYINT(1) NOT NULL DEFAULT 0,
            notify_end    TINYINT(1) NOT NULL DEFAULT 0,
            date_start    DATETIME NOT NULL,
            date_end      DATETIME NOT NULL,
            created_by    BIGINT UNSIGNED NOT NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY date_start (date_start),
            KEY date_end (date_end)
        ) {$charset_collate};

        CREATE TABLE {$questions} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id    BIGINT UNSIGNED NOT NULL,
            body       VARCHAR(512) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY poll_id (poll_id)
        ) {$charset_collate};

        CREATE TABLE {$answers} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT UNSIGNED NOT NULL,
            body        VARCHAR(512) NOT NULL,
            is_abstain  TINYINT(1) NOT NULL DEFAULT 0,
            sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY question_id (question_id)
        ) {$charset_collate};

        CREATE TABLE {$votes} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id      BIGINT UNSIGNED NOT NULL,
            question_id  BIGINT UNSIGNED NOT NULL,
            user_id      BIGINT UNSIGNED NOT NULL,
            answer_id    BIGINT UNSIGNED NOT NULL,
            is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
            voted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_vote (poll_id,question_id,user_id),
            KEY poll_id (poll_id),
            KEY user_id (user_id)
        ) {$charset_collate};

        CREATE TABLE {$groups} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name          VARCHAR(255) NOT NULL,
            type          ENUM('city','custom','system') NOT NULL DEFAULT 'city',
            description   TEXT,
            member_count  INT UNSIGNED NOT NULL DEFAULT 0,
            is_test_group TINYINT(1) NOT NULL DEFAULT 0,
            system_key    VARCHAR(64) NULL DEFAULT NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name),
            UNIQUE KEY system_key (system_key),
            KEY type (type),
            KEY is_test_group (is_test_group)
        ) {$charset_collate};

        CREATE TABLE {$group_members} (
            id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            user_id  BIGINT UNSIGNED NOT NULL,
            source   ENUM('auto','manual') NOT NULL DEFAULT 'auto',
            added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_member (group_id,user_id),
            KEY group_id (group_id),
            KEY user_id (user_id)
        ) {$charset_collate};

        CREATE TABLE {$email_queue} (
            id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id    VARCHAR(64) NOT NULL,
            poll_id   BIGINT UNSIGNED NOT NULL,
            user_id   BIGINT UNSIGNED NOT NULL,
            email     VARCHAR(255) NOT NULL,
            name      VARCHAR(255) NOT NULL DEFAULT '',
            status    ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            error_msg VARCHAR(512) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at   DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_poll_user (poll_id,user_id),
            KEY job_status (job_id,status),
            KEY poll_status (poll_id,status)
        ) {$charset_collate};";

        $sql .= "
        CREATE TABLE {$surveys} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title       VARCHAR(512) NOT NULL,
            description TEXT,
            status      ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
            date_start  DATETIME NOT NULL,
            date_end    DATETIME NOT NULL,
            created_by  BIGINT UNSIGNED NOT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY date_start (date_start),
            KEY date_end (date_end)
        ) {$charset_collate};

        CREATE TABLE {$survey_questions} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            survey_id     BIGINT UNSIGNED NOT NULL,
            body          VARCHAR(512) NOT NULL,
            field_type   ENUM('text_short','text_long','numeric','url','email') NOT NULL DEFAULT 'text_short',
            max_chars     SMALLINT UNSIGNED NOT NULL DEFAULT 100,
            sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
            profile_field VARCHAR(64) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY survey_id (survey_id)
        ) {$charset_collate};

        CREATE TABLE {$survey_responses} (
            id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            survey_id         BIGINT UNSIGNED NOT NULL,
            user_id           BIGINT UNSIGNED NOT NULL,
            response_status   ENUM('draft','ready') NOT NULL DEFAULT 'draft',
            spam_status       VARCHAR(20) NOT NULL DEFAULT 'pending',
            user_first_name   VARCHAR(255) NOT NULL DEFAULT '',
            user_last_name    VARCHAR(255) NOT NULL DEFAULT '',
            user_nickname     VARCHAR(255) NOT NULL DEFAULT '',
            user_phone        VARCHAR(50)  NOT NULL DEFAULT '',
            user_email        VARCHAR(255) NOT NULL DEFAULT '',
            submitted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_response (survey_id, user_id),
            KEY survey_status (survey_id, response_status)
        ) {$charset_collate};

        CREATE TABLE {$survey_answers} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            response_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            answer_text TEXT NOT NULL,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY question_id (question_id)
        ) {$charset_collate};

        CREATE TABLE {$messages} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title         VARCHAR(512) NOT NULL,
            body          LONGTEXT NOT NULL,
            target_groups TEXT,
            created_by    BIGINT UNSIGNED NOT NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at       DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY sent_at (sent_at)
        ) {$charset_collate};

        CREATE TABLE {$message_queue} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED NOT NULL,
            email      VARCHAR(255) NOT NULL,
            name       VARCHAR(255) NOT NULL DEFAULT '',
            status     ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            error_msg  VARCHAR(512) NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at    DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_message_user (message_id,user_id),
            KEY message_status (message_id,status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * ALTER TABLE migrations from older schemas.
     */
    private static function run_migrations(): void {
        global $wpdb;

        $installed = get_option( 'openvote_db_version', '1.0.0' );

        // ── Migracja z evoting_* na openvote_* (zmiana nazwy wtyczki) ─────────
        $old_polls = $wpdb->prefix . 'evoting_polls';
        $new_polls = $wpdb->prefix . 'openvote_polls';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_polls ) ) === $old_polls
             && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_polls ) ) !== $new_polls ) {
            $old_tables = [
                'evoting_polls'         => 'openvote_polls',
                'evoting_questions'     => 'openvote_questions',
                'evoting_answers'       => 'openvote_answers',
                'evoting_votes'        => 'openvote_votes',
                'evoting_groups'       => 'openvote_groups',
                'evoting_group_members' => 'openvote_group_members',
                'evoting_email_queue'  => 'openvote_email_queue',
                'evoting_surveys'      => 'openvote_surveys',
                'evoting_survey_questions' => 'openvote_survey_questions',
                'evoting_survey_responses' => 'openvote_survey_responses',
                'evoting_survey_answers'   => 'openvote_survey_answers',
            ];
            foreach ( $old_tables as $old => $new ) {
                $t_old = $wpdb->prefix . $old;
                $t_new = $wpdb->prefix . $new;
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t_old ) ) === $t_old ) {
                    $wpdb->query( "RENAME TABLE `{$t_old}` TO `{$t_new}`" ); // phpcs:ignore
                }
            }
            $opts = [ 'evoting_version', 'evoting_db_version', 'evoting_vote_page_slug', 'evoting_survey_page_slug', 'evoting_brand_short_name', 'evoting_brand_full_name', 'evoting_field_map', 'evoting_required_fields', 'evoting_survey_required_fields' ];
            foreach ( $opts as $key ) {
                $value = get_option( $key, null );
                if ( $value !== null ) {
                    $new_key = str_replace( 'evoting_', 'openvote_', $key );
                    update_option( $new_key, $value );
                    delete_option( $key );
                }
            }
        }

        // ── 2.0.0 → 3.0.0 : rename old polls columns to spec names ──────────
        if ( version_compare( $installed, '3.0.0', '<' ) ) {
            $polls     = $wpdb->prefix . 'openvote_polls';
            $questions = $wpdb->prefix . 'openvote_questions';
            $answers   = $wpdb->prefix . 'openvote_answers';
            $votes     = $wpdb->prefix . 'openvote_votes';

            // Polls: rename start_date → date_start, end_date → date_end
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$polls}" );

            if ( in_array( 'start_date', $cols, true ) && ! in_array( 'date_start', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} CHANGE start_date date_start DATE NOT NULL" );
            }
            if ( in_array( 'end_date', $cols, true ) && ! in_array( 'date_end', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} CHANGE end_date date_end DATE NOT NULL" );
            }

            // Polls: rename notify_users → notify_start, add notify_end
            if ( in_array( 'notify_users', $cols, true ) && ! in_array( 'notify_start', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} CHANGE notify_users notify_start TINYINT(1) NOT NULL DEFAULT 0" );
            }
            // Re-fetch cols after possible renames
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$polls}" );
            if ( ! in_array( 'notify_end', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} ADD COLUMN notify_end TINYINT(1) NOT NULL DEFAULT 0" );
            }

            // Polls: add join_mode, vote_mode, target_groups; drop target_type/target_group
            if ( ! in_array( 'join_mode', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} ADD COLUMN join_mode ENUM('open','closed') NOT NULL DEFAULT 'open'" );
            }
            if ( ! in_array( 'vote_mode', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} ADD COLUMN vote_mode ENUM('public','anonymous') NOT NULL DEFAULT 'public'" );
            }
            if ( ! in_array( 'target_groups', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} ADD COLUMN target_groups TEXT" );
            }
            if ( in_array( 'target_type', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} DROP COLUMN target_type" );
            }
            if ( in_array( 'target_group', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} DROP COLUMN target_group" );
            }
            if ( in_array( 'updated_at', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} DROP COLUMN updated_at" );
            }

            // Questions: rename question_text → body
            $q_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$questions}" );
            if ( in_array( 'question_text', $q_cols, true ) && ! in_array( 'body', $q_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$questions} CHANGE question_text body VARCHAR(512) NOT NULL" );
            }
            // Sort_order: change INT to TINYINT if needed (safe, dbDelta handles)

            // Answers: rename answer_text → body
            $a_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$answers}" );
            if ( in_array( 'answer_text', $a_cols, true ) && ! in_array( 'body', $a_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$answers} CHANGE answer_text body VARCHAR(512) NOT NULL" );
            }

            // Votes: add is_anonymous if missing
            $v_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$votes}" );
            if ( ! in_array( 'is_anonymous', $v_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$votes} ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER answer_id" );
            }
            // Remove extra indexes that may be left from old schema
            $indexes = $wpdb->get_results( "SHOW INDEX FROM {$votes}", ARRAY_A );
            $idx_names = array_column( $indexes, 'Key_name' );
            if ( in_array( 'question_id', $idx_names, true ) ) {
                $wpdb->query( "ALTER TABLE {$votes} DROP INDEX question_id" );
            }
            if ( in_array( 'answer_id', $idx_names, true ) ) {
                $wpdb->query( "ALTER TABLE {$votes} DROP INDEX answer_id" );
            }
        }

        // ── 3.0.0 → 3.1.0 : date_start / date_end DATE → DATETIME (add time) ─
        if ( version_compare( $installed, '3.1.0', '<' ) ) {
            $polls = $wpdb->prefix . 'openvote_polls';
            $cols  = $wpdb->get_col( "SHOW COLUMNS FROM {$polls}" );
            if ( in_array( 'date_start', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} MODIFY COLUMN date_start DATETIME NOT NULL" );
            }
            if ( in_array( 'date_end', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls} MODIFY COLUMN date_end DATETIME NOT NULL" );
            }
        }

        // ── 3.1.0 → 3.2.0 : status ENUM + 'scheduled' (zaplanowane) ─────────
        if ( version_compare( $installed, '3.2.0', '<' ) ) {
            $polls = $wpdb->prefix . 'openvote_polls';
            $wpdb->query( "ALTER TABLE {$polls} MODIFY COLUMN status ENUM('draft','scheduled','open','closed') NOT NULL DEFAULT 'draft'" );
        }

        // ── 3.2.0 → 3.3.0 : usunięcie statusu 'scheduled' ───────────────────
        if ( version_compare( $installed, '3.3.0', '<' ) ) {
            $polls = $wpdb->prefix . 'openvote_polls';
            $wpdb->query( "UPDATE {$polls} SET status = 'draft' WHERE status = 'scheduled'" );
            $wpdb->query( "ALTER TABLE {$polls} MODIFY COLUMN status ENUM('draft','open','closed') NOT NULL DEFAULT 'draft'" );
        }

        // ── 3.3.0 → 3.4.0 : tabela kolejki e-maili ───────────────────────────
        // Tworzona przez dbDelta (powyżej) — migracja nie wymaga ALTER.

        // ── 3.4.0 → 3.5.0 : UNIQUE KEY (poll_id, user_id) w tabeli kolejki ──
        if ( version_compare( $installed, '3.5.0', '<' ) ) {
            $eq      = $wpdb->prefix . 'openvote_email_queue';
            $indexes = $wpdb->get_col( "SHOW INDEX FROM {$eq}", 2 );  // phpcs:ignore — kolumna 2 = Key_name
            if ( ! is_array( $indexes ) ) {
                $indexes = (array) $indexes;
            }
            if ( ! in_array( 'unique_poll_user', $indexes, true ) ) {
                // Usuń duplikaty przed dodaniem klucza (zostaw najnowszy wpis).
                $wpdb->query(
                    "DELETE e1 FROM {$eq} e1
                     INNER JOIN {$eq} e2
                     ON e1.poll_id = e2.poll_id AND e1.user_id = e2.user_id AND e1.id < e2.id"
                ); // phpcs:ignore
                $wpdb->query( "ALTER TABLE {$eq} ADD UNIQUE KEY unique_poll_user (poll_id, user_id)" ); // phpcs:ignore
            }
        }

        // ── 4.0.0 → 4.1.0 : spam_status w odpowiedziach na ankiety ───────────
        if ( version_compare( $installed, '4.1.0', '<' ) ) {
            $sr = $wpdb->prefix . 'openvote_survey_responses';
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$sr}" );
            if ( ! empty( $cols ) && ! in_array( 'spam_status', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$sr} ADD COLUMN spam_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER response_status" );
            }
        }

        // ── 4.1.0 → 4.2.0 : profile_field w pytaniach ankiet (dane wrażliwe na /zgloszenia) ─
        if ( version_compare( $installed, '4.2.0', '<' ) ) {
            $sq = $wpdb->prefix . 'openvote_survey_questions';
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$sq}" );
            if ( ! empty( $cols ) && ! in_array( 'profile_field', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$sq} ADD COLUMN profile_field VARCHAR(64) NOT NULL DEFAULT '' AFTER sort_order" );
            }
        }

        // ── 4.2.0 → 4.3.0 : modified_by, modified_at (twórca / ostatni modyfikator) ─────
        if ( version_compare( $installed, '4.3.0', '<' ) ) {
            $polls_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}openvote_polls" );
            if ( is_array( $polls_cols ) && ! in_array( 'modified_by', $polls_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}openvote_polls ADD COLUMN modified_by BIGINT UNSIGNED NULL AFTER created_at, ADD COLUMN modified_at DATETIME NULL AFTER modified_by" );
            }
            $surveys_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}openvote_surveys" );
            if ( is_array( $surveys_cols ) && ! in_array( 'modified_by', $surveys_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}openvote_surveys ADD COLUMN modified_by BIGINT UNSIGNED NULL AFTER created_at, ADD COLUMN modified_at DATETIME NULL AFTER modified_by" );
            }
        }

        // ── 4.4.0 → 4.5.0 : ponowne dodanie modified_by, modified_at (autor = ostatni modyfikator) ─────
        if ( version_compare( $installed, '4.5.0', '<' ) ) {
            $polls_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}openvote_polls" );
            if ( is_array( $polls_cols ) && ! in_array( 'modified_by', $polls_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}openvote_polls ADD COLUMN modified_by BIGINT UNSIGNED NULL AFTER created_at, ADD COLUMN modified_at DATETIME NULL AFTER modified_by" );
            }
            $surveys_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}openvote_surveys" );
            if ( is_array( $surveys_cols ) && ! in_array( 'modified_by', $surveys_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}openvote_surveys ADD COLUMN modified_by BIGINT UNSIGNED NULL AFTER created_at, ADD COLUMN modified_at DATETIME NULL AFTER modified_by" );
            }
        }

        // ── 4.5.0 → 4.6.0 : scalenie ról poll_editor i poll_admin w jedną rolę poll_admin (Koordynator) ─
        if ( version_compare( $installed, '4.6.0', '<' ) ) {
            $wpdb->update(
                $wpdb->usermeta,
                [ 'meta_value' => 'poll_admin' ],
                [ 'meta_key' => 'openvote_role', 'meta_value' => 'poll_editor' ],
                [ '%s' ],
                [ '%s', '%s' ]
            );
        }

        // ── 4.6.0 → 4.7.0 : grupa Test (is_test_group), opcja openvote_create_test_group ─
        if ( version_compare( $installed, '4.7.0', '<' ) ) {
            $groups_table = $wpdb->prefix . 'openvote_groups';
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$groups_table}" );
            if ( is_array( $cols ) && ! in_array( 'is_test_group', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$groups_table} ADD COLUMN is_test_group TINYINT(1) NOT NULL DEFAULT 0 AFTER member_count" );
            }
            if ( get_option( 'openvote_create_test_group', null ) === null ) {
                update_option( 'openvote_create_test_group', 1, false );
            }
        }

        // ── 4.8.0 → 4.9.0 : grupa systemowa system.Wszyscy (type 'system', system_key 'all'), „Wszyscy” → system.Wszyscy ─
        if ( version_compare( $installed, '4.9.0', '<' ) ) {
            self::migrate_system_groups();
        }
    }

    /**
     * Migracja 4.9.0 (idempotentna): kolumna system_key + UNIQUE, typ 'system', utworzenie grup systemowych,
     * zastąpienie dawnej grupy „Wszyscy” przez system.Wszyscy (najpierw członkowie i przepięcie odwołań, potem usunięcie).
     */
    private static function migrate_system_groups(): void {
        global $wpdb;
        $groups_table = $wpdb->prefix . 'openvote_groups';

        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$groups_table}" );
        if ( ! is_array( $cols ) || empty( $cols ) ) {
            return;
        }
        $type_def = (string) $wpdb->get_var( "SHOW COLUMNS FROM {$groups_table} LIKE 'type'", 1 );
        if ( stripos( $type_def, "'system'" ) === false ) {
            $wpdb->query( "ALTER TABLE {$groups_table} MODIFY COLUMN type ENUM('city','custom','system') NOT NULL DEFAULT 'city'" );
        }
        if ( ! in_array( 'system_key', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$groups_table} ADD COLUMN system_key VARCHAR(64) NULL DEFAULT NULL AFTER is_test_group" );
        }
        $indexes = array_column( (array) $wpdb->get_results( "SHOW INDEX FROM {$groups_table}", ARRAY_A ), 'Key_name' );
        if ( ! in_array( 'system_key', $indexes, true ) ) {
            $wpdb->query( "ALTER TABLE {$groups_table} ADD UNIQUE KEY system_key (system_key)" );
        }
        if ( ! in_array( 'system_key', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$groups_table}" ), true ) ) {
            self::$schema_failed = true; // ALTER się nie powiódł — wersja zostaje 4.8.0, ponowna próba przy następnym maybe_upgrade().
            return;
        }

        require_once __DIR__ . '/class-openvote-system-groups.php';
        Openvote_System_Groups::mark_schema_ready();
        Openvote_System_Groups::ensure_groups( true );
        self::replace_legacy_wszyscy_group();
        // Pierwsze uzgodnienie (podbudżet; resztę dokończy heartbeat / przycisk w panelu).
        Openvote_System_Groups::reconcile( time() + Openvote_System_Groups::BUDGET );
    }

    /**
     * Dawna grupa „Wszyscy” (tryb „Nie używaj miast”) → system.Wszyscy.
     * Kolejność bezpieczna dla trwających głosowań: (1) pełne wypełnienie system.Wszyscy, (2) przepięcie target_groups
     * głosowań i wiadomości oraz grup koordynatorów, (3) usunięcie starej grupy BEZ remove_group_from_all_polls()
     * (które zerowałoby target_groups = „głosować mogą wszyscy”). Ponowne uruchomienie po przerwaniu jest bezpieczne.
     */
    private static function replace_legacy_wszyscy_group(): void {
        global $wpdb;
        $groups_table = $wpdb->prefix . 'openvote_groups';
        $gm_table     = $wpdb->prefix . 'openvote_group_members';

        $new_id = Openvote_System_Groups::get_all_group_id();
        if ( ! $new_id ) {
            return;
        }
        $old = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, member_count FROM {$groups_table} WHERE name = %s AND system_key IS NULL AND is_test_group = 0 LIMIT 1",
            Openvote_System_Groups::ALL_NAME
        ) );
        if ( ! $old || (int) $old->id === $new_id ) {
            return;
        }
        $old_id = (int) $old->id;

        // (1) system.Wszyscy kompletna, zanim cokolwiek na nią wskaże.
        $max_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->users}" );
        foreach ( Openvote_System_Groups::chunk_ranges( max( 1, $max_id ), Openvote_System_Groups::CHUNK ) as $range ) {
            Openvote_System_Groups::reconcile_range( $new_id, Openvote_System_Groups::KEY_ALL, $range[0], $range[1] >= $max_id ? null : $range[1] );
        }
        Openvote_System_Groups::recount( $new_id );

        // (2) Przepięcie odwołań.
        $replace = static function ( array $ids ) use ( $old_id, $new_id ): array {
            $out = [];
            foreach ( $ids as $id ) {
                $id    = ( (int) $id === $old_id ) ? $new_id : (int) $id;
                $out[] = $id;
            }
            return array_values( array_unique( $out ) );
        };
        $polls_moved = 0;
        foreach ( [ 'openvote_polls', 'openvote_messages' ] as $t ) {
            $table = $wpdb->prefix . $t;
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
                continue;
            }
            $rows = (array) $wpdb->get_results( "SELECT id, target_groups FROM {$table} WHERE target_groups IS NOT NULL AND target_groups <> ''" );
            foreach ( $rows as $row ) {
                $ids = json_decode( (string) $row->target_groups, true );
                if ( ! is_array( $ids ) || ! in_array( $old_id, array_map( 'intval', $ids ), true ) ) {
                    continue;
                }
                $wpdb->update( $table, [ 'target_groups' => wp_json_encode( $replace( $ids ) ) ], [ 'id' => (int) $row->id ], [ '%s' ], [ '%d' ] );
                ++$polls_moved;
            }
        }
        $metas = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            'openvote_groups'
        ) );
        foreach ( $metas as $m ) {
            $raw = maybe_unserialize( $m->meta_value );
            $ids = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
            if ( ! is_array( $ids ) || ! in_array( $old_id, array_map( 'intval', $ids ), true ) ) {
                continue;
            }
            $wpdb->update( $wpdb->usermeta, [ 'meta_value' => wp_json_encode( $replace( $ids ) ) ], [ 'umeta_id' => (int) $m->umeta_id ], [ '%s' ], [ '%d' ] );
        }
        if ( ! empty( $metas ) && function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'user_meta' );
        }

        // (3) Usunięcie starej grupy (bez remove_group_from_all_polls).
        $wpdb->delete( $gm_table, [ 'group_id' => $old_id ], [ '%d' ] );
        $wpdb->delete( $groups_table, [ 'id' => $old_id ], [ '%d' ] );

        if ( function_exists( 'openvote_groups_audit_log_append' ) ) {
            openvote_groups_audit_log_append( 0, sprintf(
                /* translators: 1: old member count, 2: number of polls/messages re-pointed */
                __( 'migracja 4.9.0: grupę Wszyscy (%1$d członków) zastąpiono grupą system.Wszyscy; przepięto głosowania/wiadomości: %2$d', 'openvote' ),
                (int) $old->member_count,
                $polls_moved
            ) );
        }
    }

    /**
     * Tworzy grupę Test (is_test_group=1) jeśli opcja jest włączona i grupa nie istnieje.
     * Wywoływane przy aktywacji i przy zapisie ustawień (checkbox włączony).
     */
    public static function ensure_test_group_exists(): void {
        if ( (int) get_option( 'openvote_create_test_group', 1 ) !== 1 ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'openvote_groups';
        $name  = self::TEST_GROUP_NAME;
        $id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s", $name ) );
        if ( ! $id ) {
            $wpdb->insert(
                $table,
                [ 'name' => $name, 'type' => 'custom', 'description' => null, 'member_count' => 0, 'is_test_group' => 1 ],
                [ '%s', '%s', '%s', '%d', '%d' ]
            );
        } else {
            $wpdb->update( $table, [ 'is_test_group' => 1 ], [ 'id' => (int) $id ], [ '%d' ], [ '%d' ] );
        }
    }
}
