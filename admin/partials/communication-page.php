<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';
$all_groups   = $wpdb->get_results( "SELECT id, name, member_count, is_test_group FROM {$groups_table} ORDER BY is_test_group DESC, name ASC" );
if ( openvote_is_coordinator_restricted_to_own_groups() ) {
    $my_group_ids = array_flip( Openvote_Role_Manager::get_user_groups( get_current_user_id() ) );
    if ( openvote_create_test_group_enabled() ) {
        $test_gid = openvote_get_test_group_id();
        if ( $test_gid ) {
            $my_group_ids[ $test_gid ] = 0;
        }
    }
    $all_groups = array_filter( $all_groups, function ( $g ) use ( $my_group_ids ) {
        return isset( $my_group_ids[ (int) $g->id ] );
    } );
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Komunikacja', 'openvote' ); ?></h1>

    <?php if ( isset( $_GET['bulk_deleted'] ) ) : ?>
        <?php $count = absint( $_GET['bulk_deleted'] ); ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php echo esc_html( sprintf( _n( 'Usunięto %d wiadomość.', 'Usunięto %d wiadomości.', $count, 'openvote' ), $count ) ); ?>
        </p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php esc_html_e( 'Wiadomość została usunięta.', 'openvote' ); ?>
        </p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php esc_html_e( 'Wiadomość została zapisana.', 'openvote' ); ?>
        </p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php esc_html_e( 'Utworzono kopię wiadomości. Możesz ją edytować poniżej.', 'openvote' ); ?>
        </p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['duplicate_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p>
            <?php esc_html_e( 'Nie udało się zduplikować wiadomości.', 'openvote' ); ?>
        </p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['save_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p>
            <?php esc_html_e( 'Zapis nie powiódł się. Sprawdź tytuł i czy wiadomość nie została już wysłana.', 'openvote' ); ?>
        </p></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="page" value="openvote-communication">
        <?php
        $list_table->search_box( __( 'Szukaj wiadomości', 'openvote' ), 'openvote-message' );
        $list_table->display();
        ?>
    </form>

    <hr class="wp-header-end">

    <div id="openvote-communication-form" style="scroll-margin-top: 32px;">
    <h2><?php
        if ( ! empty( $is_preview ) ) {
            esc_html_e( 'Podgląd wysyłki', 'openvote' );
        } else {
            echo $message ? esc_html__( 'Edytuj wysyłkę', 'openvote' ) : esc_html__( 'Nowa wysyłka', 'openvote' );
        }
    ?></h2>
    <?php include OPENVOTE_PLUGIN_DIR . 'admin/partials/communication-form.php'; ?>
    </div>

    <?php
    $messages_audit_all   = function_exists( 'openvote_messages_audit_log_get' ) ? openvote_messages_audit_log_get() : [];
    $audit_per            = 20;
    $audit_total          = count( $messages_audit_all );
    $audit_pages          = $audit_total > 0 ? (int) ceil( $audit_total / $audit_per ) : 1;
    $audit_page           = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
    $audit_page           = min( $audit_page, $audit_pages );
    $audit_offset         = ( $audit_page - 1 ) * $audit_per;
    $audit_entries        = array_slice( $messages_audit_all, $audit_offset, $audit_per );
    $audit_base_url       = add_query_arg( [ 'page' => 'openvote-communication' ], admin_url( 'admin.php' ) );
    ?>
    <section class="openvote-messages-audit-log" style="margin-top:32px; max-width:900px;">
        <h2 class="openvote-section-title" style="margin:0 0 8px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Log wysyłki', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Kto i kiedy uruchomił wysyłkę lub wykonał inne działania na wysyłkach. Lista niekasowalna, w celach bezpieczeństwa.', 'openvote' ); ?></p>
        <div class="openvote-audit-log-box" style="background:#1d2327; color:#f0f0f1; padding:12px 16px; border-radius:4px; max-height:220px; overflow-y:auto; font-family:Consolas, Monaco, monospace; font-size:12px; line-height:1.5;">
            <?php
            if ( empty( $audit_entries ) ) {
                echo '<p style="margin:0; color:#a7aaad;">' . esc_html__( 'Brak wpisów.', 'openvote' ) . '</p>';
            } else {
                foreach ( $audit_entries as $e ) {
                    $t     = isset( $e['t'] ) ? $e['t'] : '';
                    $actor = isset( $e['actor'] ) ? $e['actor'] : '—';
                    $line  = isset( $e['line'] ) ? $e['line'] : '';
                    echo '<div style="margin:2px 0;">' . esc_html( $t . ' ' . $actor . ' ' . $line ) . '</div>';
                }
            }
            ?>
        </div>
        <?php if ( $audit_pages > 1 ) : ?>
        <p class="openvote-audit-log-nav" style="margin:8px 0 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span class="displaying-num" style="color:#646970; font-size:13px;">
                <?php echo esc_html( sprintf( __( 'Strona %1$d z %2$d (%3$d wpisów)', 'openvote' ), $audit_page, $audit_pages, $audit_total ) ); ?>
            </span>
            <?php if ( $audit_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $audit_page - 1, $audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Poprzedni', 'openvote' ); ?></a>
            <?php endif; ?>
            <?php if ( $audit_page < $audit_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $audit_page + 1, $audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Następny', 'openvote' ); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </section>
</div>
