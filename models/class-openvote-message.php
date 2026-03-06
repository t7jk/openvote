<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Message {

    private static function messages_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'openvote_messages';
    }

    /**
     * Create a new message.
     *
     * @param array{title: string, body: string, target_groups: string, created_by?: int} $data
     * @return int|false Message ID or false on failure.
     */
    public static function create( array $data ): int|false {
        global $wpdb;

        $user_id = isset( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id();
        $target_groups = isset( $data['target_groups'] ) ? $data['target_groups'] : '';
        if ( is_array( $target_groups ) ) {
            $target_groups = wp_json_encode( array_values( array_map( 'absint', $target_groups ) ) );
        }

        $result = $wpdb->insert(
            self::messages_table(),
            [
                'title'         => sanitize_text_field( $data['title'] ?? '' ),
                'body'          => wp_kses_post( $data['body'] ?? '' ),
                'target_groups' => $target_groups,
                'created_by'    => $user_id,
                'created_at'    => current_time( 'mysql' ),
                'sent_at'       => null,
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        if ( false === $result ) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing message (only draft / not yet sent).
     */
    public static function update( int $message_id, array $data ): bool {
        global $wpdb;

        $message = self::get( $message_id );
        if ( ! $message || $message->sent_at ) {
            return false;
        }

        $update = [];
        $format = [];

        if ( isset( $data['title'] ) ) {
            $update['title'] = sanitize_text_field( $data['title'] );
            $format[]        = '%s';
        }
        if ( array_key_exists( 'body', $data ) ) {
            $update['body'] = wp_kses_post( $data['body'] );
            $format[]       = '%s';
        }
        if ( array_key_exists( 'target_groups', $data ) ) {
            $tg = $data['target_groups'];
            $update['target_groups'] = is_array( $tg ) ? wp_json_encode( array_values( array_map( 'absint', $tg ) ) ) : $tg;
            $format[] = '%s';
        }

        if ( empty( $update ) ) {
            return true;
        }

        return false !== $wpdb->update(
            self::messages_table(),
            $update,
            [ 'id' => $message_id ],
            $format,
            [ '%d' ]
        );
    }

    /**
     * Get a single message by ID.
     */
    public static function get( int $message_id ): ?object {
        global $wpdb;

        $table = self::messages_table();
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $message_id ) );

        return $row ?: null;
    }

    /**
     * Get all messages with optional filters and pagination.
     *
     * @param array{status?: string, orderby?: string, order?: string, limit?: int, offset?: int, search?: string} $args
     * @return object[]
     */
    public static function get_all( array $args = [] ): array {
        global $wpdb;

        $table  = self::messages_table();
        $where  = '1=1';
        $params = [];

        $status = $args['status'] ?? null;
        if ( 'draft' === $status ) {
            $where   .= ' AND sent_at IS NULL';
        } elseif ( 'sent' === $status ) {
            $where   .= ' AND sent_at IS NOT NULL';
        }

        if ( ! empty( $args['search'] ) ) {
            $where   .= ' AND title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $orderby = in_array( $args['orderby'] ?? '', [ 'title', 'created_at', 'sent_at' ], true )
            ? $args['orderby']
            : 'created_at';
        $order   = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params = array_merge( $params, [ $limit, $offset ] );

        if ( empty( $params ) ) {
            $params = [ $limit, $offset ];
        }

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
    }

    /**
     * Count messages, optionally by status (draft = sent_at IS NULL, sent = sent_at IS NOT NULL).
     */
    public static function count( ?string $status = null ): int {
        global $wpdb;

        $table = self::messages_table();

        if ( 'draft' === $status ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE sent_at IS NULL" );
        }
        if ( 'sent' === $status ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE sent_at IS NOT NULL" );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Count with same filters as get_all (for pagination).
     */
    public static function count_with_filters( array $args = [] ): int {
        global $wpdb;

        $table  = self::messages_table();
        $where  = '1=1';
        $params = [];

        $status = $args['status'] ?? null;
        if ( 'draft' === $status ) {
            $where   .= ' AND sent_at IS NULL';
        } elseif ( 'sent' === $status ) {
            $where   .= ' AND sent_at IS NOT NULL';
        }

        if ( ! empty( $args['search'] ) ) {
            $where   .= ' AND title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        if ( empty( $params ) ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
    }

    /**
     * Delete a message and its queue rows.
     */
    public static function delete( int $message_id ): bool {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'openvote_message_queue';
        $wpdb->delete( $queue_table, [ 'message_id' => $message_id ], [ '%d' ] );

        return (bool) $wpdb->delete( self::messages_table(), [ 'id' => $message_id ], [ '%d' ] );
    }

    /**
     * Duplicate a message (new record, sent_at = null).
     * Tytuł kopii: bazowy tytuł (bez ewentualnego " (kopia N)") + " (kopia M)", gdzie M = następny wolny numer.
     *
     * @return int|false New message ID or false on failure.
     */
    public static function duplicate( int $message_id ): int|false {
        global $wpdb;

        $message = self::get( $message_id );
        if ( ! $message ) {
            return false;
        }

        $group_ids = [];
        if ( ! empty( $message->target_groups ) ) {
            $decoded = json_decode( $message->target_groups, true );
            if ( is_array( $decoded ) ) {
                $group_ids = array_map( 'absint', $decoded );
            }
        }

        $title_raw = $message->title ?? '';
        $base_title = trim( (string) preg_replace( '/\s*\(kopia\s*\d+\)\s*$/iu', '', $title_raw ) );
        if ( $base_title === '' ) {
            $base_title = $title_raw;
        }

        $table = self::messages_table();
        $like_pattern = $wpdb->esc_like( $base_title . ' (kopia ' ) . '%';
        $rows  = $wpdb->get_col( $wpdb->prepare(
            "SELECT title FROM {$table} WHERE title = %s OR title LIKE %s",
            $base_title,
            $like_pattern
        ) );

        $max_num = 0;
        foreach ( (array) $rows as $t ) {
            if ( preg_match( '/\s*\(kopia\s*(\d+)\)\s*$/u', (string) $t, $m ) ) {
                $max_num = max( $max_num, (int) $m[1] );
            }
        }
        $next_num = $max_num + 1;
        $copy_title = $base_title . ' (kopia ' . $next_num . ')';

        return self::create( [
            'title'         => $copy_title,
            'body'          => $message->body,
            'target_groups' => $group_ids,
            'created_by'    => get_current_user_id(),
        ] );
    }

    /**
     * Attach group names to a list of message items (for list table display).
     *
     * @param object[] $items
     * @return object[]
     */
    public static function attach_group_names_to_items( array $items ): array {
        global $wpdb;

        if ( empty( $items ) ) {
            return $items;
        }

        $groups_table = $wpdb->prefix . 'openvote_groups';
        $group_names_by_id = [];
        $all_group_ids = [];

        foreach ( $items as $item ) {
            if ( ! empty( $item->target_groups ) ) {
                $decoded = json_decode( $item->target_groups, true );
                if ( is_array( $decoded ) ) {
                    $all_group_ids = array_merge( $all_group_ids, $decoded );
                }
            }
        }

        $all_group_ids = array_unique( array_map( 'absint', $all_group_ids ) );
        if ( empty( $all_group_ids ) ) {
            foreach ( $items as $item ) {
                $item->group_names = [];
            }
            return $items;
        }

        $placeholders = implode( ',', array_fill( 0, count( $all_group_ids ), '%d' ) );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM {$groups_table} WHERE id IN ({$placeholders})",
                ...$all_group_ids
            )
        );

        foreach ( $rows as $row ) {
            $group_names_by_id[ (int) $row->id ] = $row->name;
        }

        foreach ( $items as $item ) {
            $item->group_names = [];
            if ( ! empty( $item->target_groups ) ) {
                $decoded = json_decode( $item->target_groups, true );
                if ( is_array( $decoded ) ) {
                    foreach ( $decoded as $gid ) {
                        $gid = (int) $gid;
                        if ( isset( $group_names_by_id[ $gid ] ) ) {
                            $item->group_names[] = $group_names_by_id[ $gid ];
                        }
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Parse target_groups JSON into array of group IDs.
     *
     * @return int[]
     */
    public static function get_target_group_ids( object $message ): array {
        if ( empty( $message->target_groups ) ) {
            return [];
        }
        $decoded = json_decode( $message->target_groups, true );
        return is_array( $decoded ) ? array_map( 'absint', $decoded ) : [];
    }
}
