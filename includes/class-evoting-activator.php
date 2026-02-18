<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Activator {

    const DB_VERSION = '2.0.0';

    public static function activate(): void {
        self::create_tables();
        self::run_migrations();
        update_option( 'evoting_version', EVOTING_VERSION );
        update_option( 'evoting_db_version', self::DB_VERSION );
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $polls_table     = $wpdb->prefix . 'evoting_polls';
        $questions_table = $wpdb->prefix . 'evoting_questions';
        $answers_table   = $wpdb->prefix . 'evoting_answers';
        $votes_table     = $wpdb->prefix . 'evoting_votes';

        $sql = "CREATE TABLE {$polls_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(512) NOT NULL,
            description TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            target_type VARCHAR(10) NOT NULL DEFAULT 'all',
            target_group VARCHAR(255) NULL,
            notify_users TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) {$charset_collate};

        CREATE TABLE {$questions_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id BIGINT(20) UNSIGNED NOT NULL,
            question_text VARCHAR(512) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY poll_id (poll_id)
        ) {$charset_collate};

        CREATE TABLE {$answers_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            answer_text VARCHAR(512) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_abstain TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY question_id (question_id)
        ) {$charset_collate};

        CREATE TABLE {$votes_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id BIGINT(20) UNSIGNED NOT NULL,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            answer_id BIGINT(20) UNSIGNED NOT NULL,
            voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY poll_question_user (poll_id, question_id, user_id),
            KEY poll_id (poll_id),
            KEY question_id (question_id),
            KEY user_id (user_id),
            KEY answer_id (answer_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Run ALTER TABLE migrations for upgrades from older schema.
     */
    private static function run_migrations(): void {
        global $wpdb;

        $installed = get_option( 'evoting_db_version', '1.0.0' );

        if ( version_compare( $installed, '2.0.0', '<' ) ) {
            $polls_table     = $wpdb->prefix . 'evoting_polls';
            $questions_table = $wpdb->prefix . 'evoting_questions';
            $votes_table     = $wpdb->prefix . 'evoting_votes';

            // Extend title length.
            $wpdb->query( "ALTER TABLE {$polls_table} MODIFY title VARCHAR(512) NOT NULL" );

            // Change date columns from DATETIME to DATE.
            $wpdb->query( "ALTER TABLE {$polls_table} MODIFY start_date DATE NOT NULL" );
            $wpdb->query( "ALTER TABLE {$polls_table} MODIFY end_date DATE NOT NULL" );

            // Add target columns (IF NOT EXISTS via try – dbDelta already handles new columns).
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$polls_table}" );
            if ( ! in_array( 'target_type', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls_table} ADD COLUMN target_type VARCHAR(10) NOT NULL DEFAULT 'all' AFTER notify_users" );
            }
            if ( ! in_array( 'target_group', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$polls_table} ADD COLUMN target_group VARCHAR(255) NULL AFTER target_type" );
            }

            // Extend question_text length.
            $wpdb->query( "ALTER TABLE {$questions_table} MODIFY question_text VARCHAR(512) NOT NULL" );

            // Migrate votes: rename answer → answer_id if old column still exists.
            $vote_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$votes_table}" );
            if ( in_array( 'answer', $vote_cols, true ) && ! in_array( 'answer_id', $vote_cols, true ) ) {
                // Drop old data – no valid answer_id references exist yet.
                $wpdb->query( "TRUNCATE TABLE {$votes_table}" );
                $wpdb->query( "ALTER TABLE {$votes_table} DROP COLUMN answer" );
                $wpdb->query( "ALTER TABLE {$votes_table} ADD COLUMN answer_id BIGINT(20) UNSIGNED NOT NULL AFTER user_id" );
                $wpdb->query( "ALTER TABLE {$votes_table} ADD KEY answer_id (answer_id)" );
            }
        }
    }
}
