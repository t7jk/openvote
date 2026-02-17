<?php
defined( 'ABSPATH' ) || exit;

class Evoting_Activator {

    public static function activate(): void {
        self::create_tables();
        update_option( 'evoting_version', EVOTING_VERSION );
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $polls_table = $wpdb->prefix . 'evoting_polls';
        $questions_table = $wpdb->prefix . 'evoting_questions';
        $votes_table = $wpdb->prefix . 'evoting_votes';

        $sql = "CREATE TABLE {$polls_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
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
            question_text VARCHAR(500) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY poll_id (poll_id)
        ) {$charset_collate};

        CREATE TABLE {$votes_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id BIGINT(20) UNSIGNED NOT NULL,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            answer VARCHAR(20) NOT NULL,
            voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY poll_question_user (poll_id, question_id, user_id),
            KEY poll_id (poll_id),
            KEY question_id (question_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
