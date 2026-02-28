<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lista głosowań w panelu admina — oparty na WP_List_Table.
 */
class Openvote_Polls_List extends WP_List_Table {

    private string $current_status = '';

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'głosowanie', 'openvote' ),
            'plural'   => __( 'głosowania', 'openvote' ),
            'ajax'     => false,
        ] );

        $this->current_status = sanitize_text_field( $_GET['poll_status'] ?? '' );
    }

    // ─── Konfiguracja kolumn ──────────────────────────────────────────────

    public function get_columns(): array {
        return [
            'cb'         => '<input type="checkbox">',
            'title'      => __( 'Tytuł', 'openvote' ),
            'status'     => __( 'Status', 'openvote' ),
            'groups'     => __( 'Sejmiki', 'openvote' ),
            'date_start' => __( 'Rozpoczęcie', 'openvote' ),
            'date_end'   => __( 'Zakończenie', 'openvote' ),
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
            'end'    => __( 'Zakończ', 'openvote' ),
            'delete' => __( 'Usuń', 'openvote' ),
        ];
    }

    // ─── Filtry statusów (widoki) ─────────────────────────────────────────

    protected function get_views(): array {
        $counts = [
            ''       => Openvote_Poll::count(),
            'draft'  => Openvote_Poll::count( 'draft' ),
            'open'   => Openvote_Poll::count( 'open' ),
            'closed' => Openvote_Poll::count( 'closed' ),
        ];

        $labels = [
            ''      => __( 'Wszystkie', 'openvote' ),
            'draft' => __( 'Szkic', 'openvote' ),
            'open'  => __( 'Rozpoczęte', 'openvote' ),
            'closed' => __( 'Zakończone', 'openvote' ),
        ];

        $base_url = admin_url( 'admin.php?page=openvote' );
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

        $this->attach_group_names_to_items();

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

    /**
     * Pobiera nazwy grup dla target_groups każdego głosowania i dopisuje je do item->group_names.
     */
    private function attach_group_names_to_items(): void {
        global $wpdb;

        $group_ids = [];
        foreach ( $this->items as $item ) {
            $ids = Openvote_Poll::get_target_group_ids( $item );
            $group_ids = array_merge( $group_ids, $ids );
        }
        $group_ids = array_unique( array_filter( $group_ids ) );
        $groups_map = [];
        if ( ! empty( $group_ids ) ) {
            $table        = $wpdb->prefix . 'openvote_groups';
            $placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
            $rows         = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, name FROM {$table} WHERE id IN ({$placeholders})",
                    $group_ids
                )
            );
            foreach ( $rows as $r ) {
                $groups_map[ (int) $r->id ] = $r->name;
            }
        }
        foreach ( $this->items as $item ) {
            $ids   = Openvote_Poll::get_target_group_ids( $item );
            $names = array_filter( array_map( function ( $id ) use ( $groups_map ) {
                return $groups_map[ $id ] ?? '';
            }, $ids ) );
            $item->group_names = array_values( $names );
        }
    }

    // ─── Renderowanie kolumn ──────────────────────────────────────────────

    protected function column_cb( $item ): string {
        return sprintf(
            '<input type="checkbox" name="poll_ids[]" value="%d">',
            $item->id
        );
    }

    protected function column_title( $item ): string {
        $edit_url         = admin_url( 'admin.php?page=openvote&action=edit&poll_id=' . $item->id );
        $results_url      = admin_url( 'admin.php?page=openvote&action=results&poll_id=' . $item->id );
        $view_url         = admin_url( 'admin.php?page=openvote&action=view&poll_id=' . $item->id );
        $invitations_url  = admin_url( 'admin.php?page=openvote&action=invitations&poll_id=' . $item->id );

        $actions = [];

        // Wyniki — tylko dla głosowania zakończonego.
        if ( 'closed' === $item->status ) {
            $actions['results'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $results_url ),
                esc_html__( 'Wyniki', 'openvote' )
            );
        }

        if ( 'draft' === $item->status ) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                esc_html__( 'Edytuj', 'openvote' )
            );
        }

        if ( 'open' === $item->status ) {
            $actions['view'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $view_url ),
                esc_html__( 'Podgląd', 'openvote' )
            );
        }

        // Zaproszenia — status wysyłki e-mail, dostępny dla open i closed.
        if ( in_array( $item->status, [ 'open', 'closed' ], true ) ) {
            $actions['invitations'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $invitations_url ),
                esc_html__( 'Zaproszenia', 'openvote' )
            );
        }

        if ( 'open' === $item->status ) {
            $actions['end'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url( wp_nonce_url(
                    admin_url( 'admin.php?page=openvote&action=end&poll_id=' . $item->id ),
                    'openvote_end_poll_' . $item->id
                ) ),
                esc_js( __( 'Zakończyć głosowanie? Przyjmowanie głosów zostanie zatrzymane, data zakończenia ustawiona na dziś. Operacja nieodwracalna.', 'openvote' ) ),
                esc_html__( 'Zakończ', 'openvote' )
            );
        }

        if ( 'draft' === $item->status ) {
            unset( $actions['start'] );
        }

        $actions['duplicate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=openvote&action=duplicate&poll_id=' . $item->id ),
                'openvote_duplicate_poll_' . $item->id
            ) ),
            esc_html__( 'Duplikuj', 'openvote' )
        );

        // Usuń — niedostępne dla głosowania rozpoczętego.
        if ( 'open' !== $item->status ) {
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')" class="submitdelete">%s</a>',
                esc_url( wp_nonce_url(
                    admin_url( 'admin.php?page=openvote&action=delete&poll_id=' . $item->id ),
                    'openvote_delete_poll_' . $item->id
                ) ),
                esc_js( __( 'Czy na pewno chcesz usunąć to głosowanie?', 'openvote' ) ),
                esc_html__( 'Usuń', 'openvote' )
            );
        }

        $title_link = ( 'draft' === $item->status ) ? $edit_url : $view_url;
        $counts     = Openvote_Vote::get_turnout_counts( (int) $item->id );
        $counts_html = sprintf(
            ' <span class="openvote-poll-turnout" title="%s">(%d/%d)</span>',
            esc_attr__( 'Oddane głosy / uprawnieni', 'openvote' ),
            $counts['total_voters'],
            $counts['total_eligible']
        );
        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong>%s%s',
            esc_url( $title_link ),
            esc_html( $item->title ),
            $counts_html,
            $this->row_actions( $actions )
        );
    }

    protected function column_status( $item ): string {
        $labels = [
            'draft'  => __( 'Szkic', 'openvote' ),
            'open'   => __( 'Rozpoczęte', 'openvote' ),
            'closed' => __( 'Zakończone', 'openvote' ),
        ];
        $label = $labels[ $item->status ] ?? $item->status;
        return sprintf(
            '<span class="openvote-status openvote-status--%s">%s</span>',
            esc_attr( $item->status ),
            esc_html( $label )
        );
    }

    protected function column_groups( $item ): string {
        $names = $item->group_names ?? [];
        return esc_html( implode( ', ', $names ) );
    }

    protected function column_date_start( $item ): string {
        $ts = strtotime( $item->date_start );
        $formatted = $ts ? date_i18n( 'Y-m-d H:i', $ts ) : $item->date_start;
        return esc_html( $formatted );
    }

    protected function column_date_end( $item ): string {
        $now = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
        $end = $item->date_end;
        if ( strlen( $end ) === 10 ) {
            $end .= ' 23:59:59';
        }
        $style = ( $end < $now && 'open' === $item->status )
            ? ' style="color:#d63638;font-weight:600;"'
            : '';
        $ts       = strtotime( $item->date_end );
        $formatted = $ts ? date_i18n( 'Y-m-d H:i', $ts ) : $item->date_end;
        return sprintf( '<span%s>%s</span>', $style, esc_html( $formatted ) );
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

        if ( ! isset( $_POST['openvote_bulk_nonce'] ) ||
             ! check_admin_referer( 'openvote_bulk_action', 'openvote_bulk_nonce' ) ) {
            return;
        }

        $ids = array_map( 'absint', (array) ( $_POST['poll_ids'] ?? [] ) );
        if ( empty( $ids ) ) {
            return;
        }

        if ( 'end' === $action ) {
            if ( ! current_user_can( 'edit_others_posts' ) && ! Openvote_Admin::user_can_access_coordinators() ) {
                return;
            }
            $now = current_time( 'Y-m-d H:i:s' );
            $count = 0;
            foreach ( $ids as $id ) {
                $poll = Openvote_Poll::get( $id );
                if ( $poll && 'open' === $poll->status ) {
                    Openvote_Poll::update( $id, [
                        'status'   => 'closed',
                        'date_end' => $now,
                    ] );
                    ++$count;
                }
            }
            wp_safe_redirect( add_query_arg( [ 'page' => 'openvote', 'bulk_ended' => $count ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( 'delete' === $action ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            foreach ( $ids as $id ) {
                Openvote_Poll::delete( $id );
            }
            wp_safe_redirect( add_query_arg( [ 'page' => 'openvote', 'bulk_deleted' => count( $ids ) ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    // ─── Wyświetlenie formularza z nonce ──────────────────────────────────

    public function display(): void {
        wp_nonce_field( 'openvote_bulk_action', 'openvote_bulk_nonce' );
        parent::display();
    }

    // ─── Wyszukiwanie ─────────────────────────────────────────────────────

    private static function get_polls_with_search( array $args ): array {
        global $wpdb;

        $table   = $wpdb->prefix . 'openvote_polls';
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
                          (SELECT COUNT(*) FROM {$wpdb->prefix}openvote_questions q WHERE q.poll_id = p.id) AS question_count
                   FROM {$table} p
                   WHERE {$where}
                   ORDER BY p.{$orderby} {$order}
                   LIMIT %d OFFSET %d";

        $params = array_merge( $params, [ $limit, $offset ] );

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
    }

    private static function count_polls_with_search( array $args ): int {
        global $wpdb;

        $table  = $wpdb->prefix . 'openvote_polls';
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
