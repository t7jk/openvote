<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Evoting_Surveys_List extends WP_List_Table {

    private string $current_status = '';

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'ankieta', 'evoting' ),
            'plural'   => __( 'ankiety', 'evoting' ),
            'ajax'     => false,
        ] );

        $this->current_status = sanitize_text_field( $_GET['survey_status'] ?? '' );
    }

    // ─── Kolumny ───────────────────────────────────────────────────────────

    public function get_columns(): array {
        return [
            'cb'         => '<input type="checkbox">',
            'title'      => __( 'Tytuł', 'evoting' ),
            'status'     => __( 'Status', 'evoting' ),
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
            'close'  => __( 'Zakończ', 'evoting' ),
            'delete' => __( 'Usuń', 'evoting' ),
        ];
    }

    // ─── Filtry statusów ──────────────────────────────────────────────────

    protected function get_views(): array {
        $counts = [
            ''       => self::count_surveys(),
            'draft'  => self::count_surveys( 'draft' ),
            'open'   => self::count_surveys( 'open' ),
            'closed' => self::count_surveys( 'closed' ),
        ];

        $labels = [
            ''       => __( 'Wszystkie', 'evoting' ),
            'draft'  => __( 'Szkic', 'evoting' ),
            'open'   => __( 'Otwarta', 'evoting' ),
            'closed' => __( 'Zamknięta', 'evoting' ),
        ];

        $base_url = admin_url( 'admin.php?page=evoting-surveys' );
        $views    = [];

        foreach ( $labels as $key => $label ) {
            $url     = $key ? add_query_arg( 'survey_status', $key, $base_url ) : $base_url;
            $current = ( $this->current_status === $key ) ? ' class="current" aria-current="page"' : '';
            $views[ $key ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $url ),
                $current,
                esc_html( $label ),
                (int) $counts[ $key ]
            );
        }

        return $views;
    }

    // ─── Dane ─────────────────────────────────────────────────────────────

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

        $this->items = self::get_surveys_with_search( $args );
        $total_items = self::count_surveys_with_search( $args );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total_items / $per_page ),
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
            '<input type="checkbox" name="survey_ids[]" value="%d">',
            (int) $item->id
        );
    }

    protected function column_title( $item ): string {
        $edit_url  = admin_url( 'admin.php?page=evoting-surveys&action=edit&survey_id=' . $item->id );
        $view_url  = admin_url( 'admin.php?page=evoting-surveys&action=view&survey_id=' . $item->id );
        $resp_url  = admin_url( 'admin.php?page=evoting-surveys&action=responses&survey_id=' . $item->id );
        $del_url   = wp_nonce_url(
            admin_url( 'admin.php?page=evoting-surveys&action=delete&survey_id=' . $item->id ),
            'evoting_delete_survey_' . $item->id
        );
        $close_url = wp_nonce_url(
            admin_url( 'admin.php?page=evoting-surveys&action=close&survey_id=' . $item->id ),
            'evoting_close_survey_' . $item->id
        );
        $dup_url   = wp_nonce_url(
            admin_url( 'admin.php?page=evoting-surveys&action=duplicate&survey_id=' . $item->id ),
            'evoting_duplicate_survey_' . $item->id
        );

        $actions = [];

        // ── Otwarta ────────────────────────────────────────────────────────
        if ( 'open' === $item->status ) {
            $actions['view'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $view_url ),
                esc_html__( 'Podgląd', 'evoting' )
            );
            $actions['responses'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $resp_url ),
                esc_html__( 'Zgłoszenia', 'evoting' )
            );
            $actions['close'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                esc_url( $close_url ),
                esc_js( __( 'Zakończyć ankietę? Operacja nieodwracalna.', 'evoting' ) ),
                esc_html__( 'Zakończ', 'evoting' )
            );
            $actions['duplicate'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $dup_url ),
                esc_html__( 'Duplikuj', 'evoting' )
            );
        }

        // ── Zamknięta ──────────────────────────────────────────────────────
        if ( 'closed' === $item->status ) {
            $actions['responses'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $resp_url ),
                esc_html__( 'Zgłoszenia', 'evoting' )
            );
            $actions['duplicate'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $dup_url ),
                esc_html__( 'Duplikuj', 'evoting' )
            );
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')" class="submitdelete">%s</a>',
                esc_url( $del_url ),
                esc_js( __( 'Usunąć tę ankietę wraz z odpowiedziami?', 'evoting' ) ),
                esc_html__( 'Usuń', 'evoting' )
            );
        }

        // ── Szkic ──────────────────────────────────────────────────────────
        if ( 'draft' === $item->status ) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                esc_html__( 'Edytuj', 'evoting' )
            );
            $actions['duplicate'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $dup_url ),
                esc_html__( 'Duplikuj', 'evoting' )
            );
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')" class="submitdelete">%s</a>',
                esc_url( $del_url ),
                esc_js( __( 'Usunąć tę ankietę wraz z odpowiedziami?', 'evoting' ) ),
                esc_html__( 'Usuń', 'evoting' )
            );
        }

        $title_link = ( 'draft' === $item->status ) ? $edit_url : $view_url;

        $counts = Evoting_Survey::get_response_counts_for_survey( (int) $item->id );
        $n      = $counts['not_spam'];
        $y      = $counts['total'];
        $counts_html = ( $n !== 0 || $y !== 0 )
            ? ' <span class="evoting-survey-row-counts">(' . (int) $n . '/' . (int) $y . ')</span>'
            : '';

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
            'draft'  => __( 'Szkic', 'evoting' ),
            'open'   => __( 'Otwarta', 'evoting' ),
            'closed' => __( 'Zamknięta', 'evoting' ),
        ];
        return sprintf(
            '<span class="evoting-status evoting-status--%s">%s</span>',
            esc_attr( $item->status ),
            esc_html( $labels[ $item->status ] ?? $item->status )
        );
    }

    protected function column_date_start( $item ): string {
        $ts = strtotime( $item->date_start );
        return esc_html( $ts ? date_i18n( 'Y-m-d H:i', $ts ) : $item->date_start );
    }

    protected function column_date_end( $item ): string {
        $ts  = strtotime( $item->date_end );
        $now = current_time( 'mysql' );
        $style = ( $item->date_end < $now && 'open' === $item->status )
            ? ' style="color:#d63638;font-weight:600;"'
            : '';
        return sprintf(
            '<span%s>%s</span>',
            $style,
            esc_html( $ts ? date_i18n( 'Y-m-d H:i', $ts ) : $item->date_end )
        );
    }

    protected function column_default( $item, $column_name ): string {
        return esc_html( $item->{$column_name} ?? '' );
    }

    // ─── Bulk actions ─────────────────────────────────────────────────────

    public function process_bulk_action(): void {
        $action = $this->current_action();
        if ( ! $action ) {
            return;
        }

        if ( ! isset( $_POST['evoting_survey_bulk_nonce'] ) ||
             ! check_admin_referer( 'evoting_survey_bulk_action', 'evoting_survey_bulk_nonce' ) ) {
            return;
        }

        $ids = array_map( 'absint', (array) ( $_POST['survey_ids'] ?? [] ) );
        if ( empty( $ids ) ) {
            return;
        }

        if ( 'close' === $action ) {
            $now = current_time( 'Y-m-d H:i:s' );
            foreach ( $ids as $id ) {
                $s = Evoting_Survey::get( $id );
                if ( $s && 'open' === $s->status ) {
                    Evoting_Survey::update( $id, [ 'status' => 'closed', 'date_end' => $now ] );
                }
            }
            wp_safe_redirect( add_query_arg( [ 'page' => 'evoting-surveys', 'bulk_closed' => count( $ids ) ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( 'delete' === $action ) {
            foreach ( $ids as $id ) {
                Evoting_Survey::delete( $id );
            }
            wp_safe_redirect( add_query_arg( [ 'page' => 'evoting-surveys', 'bulk_deleted' => count( $ids ) ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    // ─── Display z nonce ──────────────────────────────────────────────────

    public function display(): void {
        wp_nonce_field( 'evoting_survey_bulk_action', 'evoting_survey_bulk_nonce' );
        parent::display();
    }

    // ─── Szukaj ───────────────────────────────────────────────────────────

    private static function get_surveys_with_search( array $args ): array {
        global $wpdb;

        $table  = $wpdb->prefix . 'evoting_surveys';
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

        $allowed_orderby = [ 'title', 'status', 'date_start', 'date_end', 'created_at' ];
        $orderby = in_array( $args['orderby'] ?? '', $allowed_orderby, true ) ? $args['orderby'] : 'date_start';
        $order   = 'ASC' === ( $args['order'] ?? 'DESC' ) ? 'ASC' : 'DESC';
        $limit   = absint( $args['limit'] ?? 20 );
        $offset  = absint( $args['offset'] ?? 0 );

        $sql    = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params = array_merge( $params, [ $limit, $offset ] );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
    }

    private static function count_surveys_with_search( array $args ): int {
        global $wpdb;

        $table  = $wpdb->prefix . 'evoting_surveys';
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

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return empty( $params )
            ? (int) $wpdb->get_var( $sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
    }

    private static function count_surveys( string $status = '' ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'evoting_surveys';
        if ( $status ) {
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }
}
