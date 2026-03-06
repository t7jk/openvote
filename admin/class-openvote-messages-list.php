<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lista wysyłek masowych (Komunikacja) w panelu admina — oparta na WP_List_Table.
 */
class Openvote_Messages_List extends WP_List_Table {

    private string $current_status = '';

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'wiadomość', 'openvote' ),
            'plural'   => __( 'wiadomości', 'openvote' ),
            'ajax'     => false,
        ] );

        $this->current_status = sanitize_text_field( $_GET['message_status'] ?? '' );
    }

    public function get_columns(): array {
        return [
            'cb'         => '<input type="checkbox">',
            'title'      => __( 'Tytuł', 'openvote' ),
            'status'     => __( 'Status', 'openvote' ),
            'groups'     => __( 'Grupy', 'openvote' ),
            'author'     => __( 'Autor', 'openvote' ),
            'created_at' => __( 'Utworzono', 'openvote' ),
            'sent_at'    => __( 'Wysłano', 'openvote' ),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'title'      => [ 'title', false ],
            'created_at' => [ 'created_at', true ],
            'sent_at'    => [ 'sent_at', false ],
        ];
    }

    protected function get_bulk_actions(): array {
        return [
            'delete' => __( 'Usuń', 'openvote' ),
        ];
    }

    protected function get_views(): array {
        $counts = [
            ''     => Openvote_Message::count(),
            'draft' => Openvote_Message::count( 'draft' ),
            'sent'  => Openvote_Message::count( 'sent' ),
        ];

        $labels = [
            ''     => __( 'Wszystkie', 'openvote' ),
            'draft' => __( 'Szkic', 'openvote' ),
            'sent'  => __( 'Wysłane', 'openvote' ),
        ];

        $base_url = admin_url( 'admin.php?page=openvote-communication' );
        $views    = [];

        foreach ( $labels as $key => $label ) {
            $url     = $key ? add_query_arg( 'message_status', $key, $base_url ) : $base_url;
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

    public function prepare_items(): void {
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $search       = sanitize_text_field( $_REQUEST['s'] ?? '' );

        $orderby = sanitize_text_field( $_REQUEST['orderby'] ?? 'created_at' );
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

        $this->items = Openvote_Message::get_all( $args );
        $total_items = Openvote_Message::count_with_filters( $args );

        $this->items = Openvote_Message::attach_group_names_to_items( $this->items );

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

    protected function column_cb( $item ): string {
        return sprintf(
            '<input type="checkbox" name="message_ids[]" value="%d">',
            $item->id
        );
    }

    protected function column_title( $item ): string {
        $form_anchor = '#openvote-communication-form';
        $edit_url    = admin_url( 'admin.php?page=openvote-communication&action=edit&message_id=' . $item->id ) . $form_anchor;
        $preview_url = admin_url( 'admin.php?page=openvote-communication&action=preview&message_id=' . $item->id ) . $form_anchor;
        $send_url    = admin_url( 'admin.php?page=openvote-communication&action=send&message_id=' . $item->id );

        $actions = [];

        if ( ! empty( $item->sent_at ) ) {
            $actions['preview'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $preview_url ),
                esc_html__( 'Podgląd', 'openvote' )
            );
        } else {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                esc_html__( 'Edytuj', 'openvote' )
            );
        }

        $actions['duplicate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=openvote-communication&action=duplicate&message_id=' . $item->id ),
                'openvote_duplicate_message_' . $item->id
            ) ),
            esc_html__( 'Duplikuj', 'openvote' )
        );

        $actions['delete'] = sprintf(
            '<a href="%s" onclick="return confirm(\'%s\')" class="submitdelete">%s</a>',
            esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=openvote-communication&action=delete&message_id=' . $item->id ),
                'openvote_delete_message_' . $item->id
            ) ),
            esc_js( __( 'Czy na pewno chcesz usunąć tę wiadomość?', 'openvote' ) ),
            esc_html__( 'Kasuj', 'openvote' )
        );

        if ( empty( $item->sent_at ) ) {
            $actions['send'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $send_url ),
                esc_html__( 'Wyślij', 'openvote' )
            );
        }

        $title_link = ! empty( $item->sent_at ) ? $preview_url : $edit_url;

        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong>%s',
            esc_url( $title_link ),
            esc_html( $item->title ),
            $this->row_actions_with_separator( $actions )
        );
    }

    /**
     * Akcje wiersza z jawnym separatorem " | " między każdą parą linków (kompatybilność z różnymi wersjami WP/motywów).
     */
    private function row_actions_with_separator( array $actions ): string {
        if ( empty( $actions ) ) {
            return '';
        }
        return '<div class="row-actions">' . implode( ' | ', array_values( $actions ) ) . '</div>';
    }

    protected function column_status( $item ): string {
        $is_sent = ! empty( $item->sent_at );
        $status  = $is_sent ? 'sent' : 'draft';
        $labels  = [
            'draft' => __( 'Szkic', 'openvote' ),
            'sent'  => __( 'Wysłano', 'openvote' ),
        ];
        $label = $labels[ $status ];
        return sprintf(
            '<span class="openvote-status openvote-status--%s">%s</span>',
            esc_attr( $status ),
            esc_html( $label )
        );
    }

    protected function column_groups( $item ): string {
        $names = $item->group_names ?? [];
        $max_display = 9;
        if ( count( $names ) > $max_display ) {
            $display = array_slice( $names, 0, $max_display );
            return esc_html( implode( ', ', $display ) . ' …' );
        }
        return esc_html( implode( ', ', $names ) );
    }

    protected function column_author( $item ): string {
        $author_id = (int) ( $item->created_by ?? 0 );
        return esc_html( openvote_get_user_nickname( $author_id ) );
    }

    protected function column_created_at( $item ): string {
        $ts        = strtotime( $item->created_at );
        $formatted = $ts ? date_i18n( 'Y-m-d H:i', $ts ) : $item->created_at;
        return esc_html( $formatted );
    }

    protected function column_sent_at( $item ): string {
        if ( empty( $item->sent_at ) ) {
            return '—';
        }
        $ts        = strtotime( $item->sent_at );
        $formatted = $ts ? date_i18n( 'Y-m-d H:i', $ts ) : $item->sent_at;
        return esc_html( $formatted );
    }

    protected function column_default( $item, $column_name ): string {
        return esc_html( $item->{$column_name} ?? '' );
    }

    /**
     * Tablenav z menu masowym zawsze widocznym (jak lista głosowań).
     */
    protected function display_tablenav( $which ): void {
        if ( 'top' === $which ) {
            wp_nonce_field( 'openvote_bulk_communication', 'openvote_bulk_communication_nonce' );
        }
        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
            <div class="alignleft actions bulkactions">
                <?php $this->bulk_actions( $which ); ?>
            </div>
            <?php $this->extra_tablenav( $which ); ?>
            <?php $this->pagination( $which ); ?>
            <br class="clear" />
        </div>
        <?php
    }

    public function process_bulk_action(): void {
        $action = $this->current_action();
        if ( ! $action || 'delete' !== $action ) {
            return;
        }

        if ( ! isset( $_POST['openvote_bulk_communication_nonce'] ) ||
             ! check_admin_referer( 'openvote_bulk_communication', 'openvote_bulk_communication_nonce' ) ) {
            return;
        }

        $ids = array_map( 'absint', (array) ( $_POST['message_ids'] ?? [] ) );
        if ( empty( $ids ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_others_posts' ) && ! Openvote_Admin::user_can_access_coordinators() ) {
            return;
        }

        foreach ( $ids as $id ) {
            Openvote_Message::delete( $id );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'openvote-communication', 'bulk_deleted' => count( $ids ) ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function display(): void {
        parent::display();
    }
}
