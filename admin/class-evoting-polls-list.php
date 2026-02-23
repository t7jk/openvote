<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lista głosowań w panelu admina — oparty na WP_List_Table.
 */
class Evoting_Polls_List extends WP_List_Table {

    private string $current_status = '';

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'głosowanie', 'evoting' ),
            'plural'   => __( 'głosowania', 'evoting' ),
            'ajax'     => false,
        ] );

        $this->current_status = sanitize_text_field( $_GET['poll_status'] ?? '' );
    }

    // ─── Konfiguracja kolumn ──────────────────────────────────────────────

    public function get_columns(): array {
        return [
            'cb'         => '<input type="checkbox">',
            'title'      => __( 'Tytuł', 'evoting' ),
            'status'     => __( 'Status', 'evoting' ),
            'questions'  => __( 'Pytania', 'evoting' ),
            'date_start' => __( 'Rozpoczęcie', 'evoting' ),
            'date_end'   => __( 'Zakończenie', 'evoting' ),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'title'      => [ 'title', false ],
            'status'     => [ 'status', false ],
            'date_start' => [ 'date_start', true ],
            'date_end'   => [ 'date_end', false ],
        ];
    }

    protected function get_bulk_actions(): array {
        return [
            'start'  => __( 'Rozpocznij', 'evoting' ),
            'end'    => __( 'Zakończ', 'evoting' ),
            'delete' => __( 'Usuń', 'evoting' ),
        ];
    }

    // ─── Filtry statusów (widoki) ─────────────────────────────────────────

    protected function get_views(): array {
        $counts = [
            ''       => Evoting_Poll::count(),
            'draft'  => Evoting_Poll::count( 'draft' ),
            'open'   => Evoting_Poll::count( 'open' ),
            'closed' => Evoting_Poll::count( 'closed' ),
        ];

        $labels = [
            ''       => __( 'Wszystkie', 'evoting' ),
            'draft'  => __( 'Szkic', 'evoting' ),
            'open'   => __( 'Rozpoczęte', 'evoting' ),
            'closed' => __( 'Zakończone', 'evoting' ),
        ];

        $base_url = admin_url( 'admin.php?page=evoting' );
        $views    = [];

        foreach ( $labels as $key => $label ) {
            $url     = $key ? add_query_arg( 'poll_status', $key, $base_url ) : $base_url;
            $current = ( $this->current_status === $key ) ? ' class="current" aria-current="page"' : '';
            $views[ $key ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $url ),
                $current,
                esc_html( $label ),
                $counts[ $key ]
            );
        }

        return $views;
    }

    // ─── Przygotowanie danych ─────────────────────────────────────────────

    public function prepare_items(): void {
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $search       = sanitize_text_field( $_REQUEST['s'] ?? '' );

        $orderby = sanitize_text_field( $_REQUEST['orderby'] ?? 'date_start' );
        $order   = strtoupper( sanitize_text_field( $_REQUEST['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

        $args = [
            'orderby' => $orderby,
            'order'   => $order,
            'limit'   => $per_page,
            'offset'  => ( $current_page - 1 ) * $per_page,
        ];

        if ( $this->current_status ) {
            $args['status'] = $this->current_status;
        }

        if ( $search ) {
            $args['search'] = $search;
        }

        $this->items = self::get_polls_with_search( $args );
        $total_items = self::count_polls_with_search( $args );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
            'title',
        ];
    }

    // ─── Renderowanie kolumn ──────────────────────────────────────────────

    protected function column_cb( $item ): string {
        return sprintf(
            '<input type="checkbox" name="poll_ids[]" value="%d">',
            $item->id
        );
    }

    protected function column_title( $item ): string {
        $edit_url    = admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $item->id );
        $results_url = admin_url( 'admin.php?page=evoting&action=results&poll_id=' . $item->id );
        $view_url    = admin_url( 'admin.php?page=evoting&action=view&poll_id=' . $item->id );

        $actions = [];

        // Wyniki — tylko dla głosowania zakończonego.
        if ( 'closed' === $item->status ) {
            $actions['results'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $results_url ),
                esc_html__( 'Wyniki', 'evoting' )
            );
        }

        if ( 'draft' === $item->status ) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                esc_html__( 'Edytuj', 'evoting' )
            );
        }

        if ( in_array( $item->status, [ 'open', 'closed' ], true ) ) {
            $actions['view'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $view_url ),
                esc_html__( 'Podgląd', 'evoting' )
            );
        }

        if ( 'open' === $item->status ) {
            $actions['end'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url( wp_nonce_url(
                    admin_url( 'admin.php?page=evoting&action=end&poll_id=' . $item->id ),
                    'evoting_end_poll_' . $item->id
                ) ),
                esc_js( __( 'Zakończyć głosowanie? Przyjmowanie głosów zostanie zatrzymane, data zakończenia ustawiona na dziś. Operacja nieodwracalna.', 'evoting' ) ),
                esc_html__( 'Zakończ', 'evoting' )
            );
        }

        if ( 'draft' === $item->status ) {
            $actions['start'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url( wp_nonce_url(
                    admin_url( 'admin.php?page=evoting&action=start&poll_id=' . $item->id ),
                    'evoting_start_poll_' . $item->id
                ) ),
                esc_js( __( 'Uruchomić głosowanie? Data rozpoczęcia zostanie ustawiona na dziś i rozpocznie się zbieranie głosów.', 'evoting' ) ),
                esc_html__( 'Uruchom', 'evoting' )
            );
        }

        $actions['duplicate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=evoting&action=duplicate&poll_id=' . $item->id ),
                'evoting_duplicate_poll_' . $item->id
            ) ),
            esc_html__( 'Duplikuj', 'evoting' )
        );

        // Usuń — niedostępne dla głosowania rozpoczętego.
        if ( 'open' !== $item->status ) {
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')" class="submitdelete">%s</a>',
                esc_url( wp_nonce_url(
                    admin_url( 'admin.php?page=evoting&action=delete&poll_id=' . $item->id ),
                    'evoting_delete_poll_' . $item->id
                ) ),
                esc_js( __( 'Czy na pewno chcesz usunąć to głosowanie?', 'evoting' ) ),
                esc_html__( 'Usuń', 'evoting' )
            );
        }

        $title_link = ( 'draft' === $item->status ) ? $edit_url : $view_url;
        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong>%s',
            esc_url( $title_link ),
            esc_html( $item->title ),
            $this->row_actions( $actions )
        );
    }

    protected function column_status( $item ): string {
        $labels = [
            'draft'  => __( 'Szkic', 'evoting' ),
            'open'   => __( 'Rozpoczęte', 'evoting' ),
            'closed' => __( 'Zakończone', 'evoting' ),
        ];
        $label = $labels[ $item->status ] ?? $item->status;
        return sprintf(
            '<span class="evoting-status evoting-status--%s">%s</span>',
            esc_attr( $item->status ),
            esc_html( $label )
        );
    }

    protected function column_questions( $item ): string {
        return esc_html( (string) $item->question_count );
    }

    protected function column_date_start( $item ): string {
        return esc_html( $item->date_start );
    }

    protected function column_date_end( $item ): string {
        $now = current_time( 'Y-m-d H:i:s' );
        $end = $item->date_end;
        if ( strlen( $end ) === 10 ) {
            $end .= ' 23:59:59';
        }
        $style = ( $end < $now && 'open' === $item->status )
            ? ' style="color:#d63638;font-weight:600;"'
            : '';
        return sprintf( '<span%s>%s</span>', $style, esc_html( $item->date_end ) );
    }

    protected function column_default( $item, $column_name ): string {
        return esc_html( $item->{$column_name} ?? '' );
    }

    // ─── Obsługa bulk actions ─────────────────────────────────────────────

    public function process_bulk_action(): void {
        $action = $this->current_action();
        if ( ! $action ) {
            return;
        }

        if ( ! isset( $_POST['evoting_bulk_nonce'] ) ||
             ! check_admin_referer( 'evoting_bulk_action', 'evoting_bulk_nonce' ) ) {
            return;
        }

        $ids = array_map( 'absint', (array) ( $_POST['poll_ids'] ?? [] ) );
        if ( empty( $ids ) ) {
            return;
        }

        if ( 'start' === $action ) {
            if ( ! current_user_can( 'edit_others_posts' ) ) {
                return;
            }
            $now = current_time( 'Y-m-d H:i:s' );
            $count = 0;
            foreach ( $ids as $id ) {
                $poll = Evoting_Poll::get( $id );
                if ( $poll && 'draft' === $poll->status ) {
                    $date_end = ( $poll->date_end && $poll->date_end >= $now ) ? $poll->date_end : $now;
                    Evoting_Poll::update( $id, [
                        'status'     => 'open',
                        'date_start' => $now,
                        'date_end'   => $date_end,
                    ] );
                    ++$count;
                }
            }
            wp_safe_redirect( add_query_arg( [ 'page' => 'evoting', 'bulk_started' => $count ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( 'end' === $action ) {
            if ( ! current_user_can( 'edit_others_posts' ) ) {
                return;
            }
            $now = current_time( 'Y-m-d H:i:s' );
            $count = 0;
            foreach ( $ids as $id ) {
                $poll = Evoting_Poll::get( $id );
                if ( $poll && 'open' === $poll->status ) {
                    Evoting_Poll::update( $id, [
                        'status'   => 'closed',
                        'date_end' => $now,
                    ] );
                    ++$count;
                }
            }
            wp_safe_redirect( add_query_arg( [ 'page' => 'evoting', 'bulk_ended' => $count ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( 'delete' === $action ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            foreach ( $ids as $id ) {
                Evoting_Poll::delete( $id );
            }
            wp_safe_redirect( add_query_arg( [ 'page' => 'evoting', 'bulk_deleted' => count( $ids ) ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    // ─── Wyświetlenie formularza z nonce ──────────────────────────────────

    public function display(): void {
        wp_nonce_field( 'evoting_bulk_action', 'evoting_bulk_nonce' );
        parent::display();
    }

    // ─── Wyszukiwanie ─────────────────────────────────────────────────────

    private static function get_polls_with_search( array $args ): array {
        global $wpdb;

        $table   = $wpdb->prefix . 'evoting_polls';
        $where   = '1=1';
        $params  = [];

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND p.status = %s';
            $params[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where   .= ' AND p.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $allowed_orderby = [ 'title', 'status', 'date_start', 'date_end', 'created_at' ];
        $orderby = in_array( $args['orderby'] ?? '', $allowed_orderby, true ) ? $args['orderby'] : 'date_start';
        $order   = 'ASC' === ( $args['order'] ?? 'DESC' ) ? 'ASC' : 'DESC';

        $limit  = absint( $args['limit'] ?? 20 );
        $offset = absint( $args['offset'] ?? 0 );

        $sql    = "SELECT p.*,
                          (SELECT COUNT(*) FROM {$wpdb->prefix}evoting_questions q WHERE q.poll_id = p.id) AS question_count
                   FROM {$table} p
                   WHERE {$where}
                   ORDER BY p.{$orderby} {$order}
                   LIMIT %d OFFSET %d";

        $params = array_merge( $params, [ $limit, $offset ] );

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
    }

    private static function count_polls_with_search( array $args ): int {
        global $wpdb;

        $table  = $wpdb->prefix . 'evoting_polls';
        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where   .= ' AND title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        return empty( $params )
            ? (int) $wpdb->get_var( $sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
    }
}
