<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Activator {

    const DB_VERSION = '3.3.0';

    public static function activate(): void {
        self::create_tables();
        self::run_migrations();
        update_option( 'evoting_version', EVOTING_VERSION );
        update_option( 'evoting_db_version', self::DB_VERSION );
        require_once __DIR__ . '/class-evoting-vote-page.php';
        Evoting_Vote_Page::add_rewrite_rule();
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $polls         = $wpdb->prefix . 'evoting_polls';
        $questions     = $wpdb->prefix . 'evoting_questions';
        $answers       = $wpdb->prefix . 'evoting_answers';
        $votes         = $wpdb->prefix . 'evoting_votes';
        $groups        = $wpdb->prefix . 'evoting_groups';
        $group_members = $wpdb->prefix . 'evoting_group_members';

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
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name         VARCHAR(255) NOT NULL,
            type         ENUM('city','custom') NOT NULL DEFAULT 'city',
            description  TEXT,
            member_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name),
            KEY type (type)
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
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * ALTER TABLE migrations from older schemas.
     */
    private static function run_migrations(): void {
        global $wpdb;

        $installed = get_option( 'evoting_db_version', '1.0.0' );

        // ── 2.0.0 → 3.0.0 : rename old polls columns to spec names ──────────
        if ( version_compare( $installed, '3.0.0', '<' ) ) {
            $polls     = $wpdb->prefix . 'evoting_polls';
            $questions = $wpdb->prefix . 'evoting_questions';
            $answers   = $wpdb->prefix . 'evoting_answers';
            $votes     = $wpdb->prefix . 'evoting_votes';

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
            $polls = $wpdb->prefix . 'evoting_polls';
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
            $polls = $wpdb->prefix . 'evoting_polls';
            $wpdb->query( "ALTER TABLE {$polls} MODIFY COLUMN status ENUM('draft','scheduled','open','closed') NOT NULL DEFAULT 'draft'" );
        }

        // ── 3.2.0 → 3.3.0 : usunięcie statusu 'scheduled' ───────────────────
        if ( version_compare( $installed, '3.3.0', '<' ) ) {
            $polls = $wpdb->prefix . 'evoting_polls';
            $wpdb->query( "UPDATE {$polls} SET status = 'draft' WHERE status = 'scheduled'" );
            $wpdb->query( "ALTER TABLE {$polls} MODIFY COLUMN status ENUM('draft','open','closed') NOT NULL DEFAULT 'draft'" );
        }
    }
}
