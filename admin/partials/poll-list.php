<?php
defined( 'ABSPATH' ) || exit;

$polls = Openvote_Poll::get_all();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Głosowania', 'openvote' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Dodaj nowe', 'openvote' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Głosowanie zostało usunięte.', 'openvote' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'openvote_admin_error' );
    if ( $error ) :
        delete_transient( 'openvote_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Tytuł', 'openvote' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'openvote' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Pytania', 'openvote' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Data rozpoczęcia', 'openvote' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Data zakończenia', 'openvote' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Akcje', 'openvote' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $polls ) ) : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e( 'Brak głosowań.', 'openvote' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $polls as $poll ) :
                    $questions     = Openvote_Poll::get_questions( (int) $poll->id );
                    $status_labels = [
                        'draft'  => __( 'Szkic', 'openvote' ),
                        'open'   => __( 'Rozpoczęte', 'openvote' ),
                        'closed' => __( 'Zakończone', 'openvote' ),
                    ];
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=edit&poll_id=' . $poll->id ) ); ?>">
                                    <?php echo esc_html( $poll->title ); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <span class="openvote-status openvote-status--<?php echo esc_attr( $poll->status ); ?>">
                                <?php echo esc_html( $status_labels[ $poll->status ] ?? $poll->status ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( count( $questions ) ); ?></td>
                        <td><?php echo esc_html( $poll->date_start ); ?></td>
                        <td><?php echo esc_html( $poll->date_end ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=edit&poll_id=' . $poll->id ) ); ?>">
                                <?php esc_html_e( 'Edytuj', 'openvote' ); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=results&poll_id=' . $poll->id ) ); ?>">
                                <?php esc_html_e( 'Wyniki', 'openvote' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    $polls_audit_all    = openvote_polls_audit_log_get();
    $polls_audit_per    = 20;
    $polls_audit_total  = count( $polls_audit_all );
    $polls_audit_pages  = $polls_audit_total > 0 ? (int) ceil( $polls_audit_total / $polls_audit_per ) : 1;
    $polls_audit_page   = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
    $polls_audit_page   = min( $polls_audit_page, $polls_audit_pages );
    $polls_audit_offset = ( $polls_audit_page - 1 ) * $polls_audit_per;
    $polls_audit_entries = array_slice( $polls_audit_all, $polls_audit_offset, $polls_audit_per );
    $polls_audit_base_url = add_query_arg( [ 'page' => 'openvote' ], admin_url( 'admin.php' ) );
    ?>
    <section class="openvote-polls-audit-log" style="margin-top:32px; max-width:900px;">
        <h2 class="openvote-section-title" style="margin:0 0 8px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Log czynności głosowań', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Kto i kiedy utworzył, edytował, wystartował, zakończył, skopiował lub usunął głosowanie albo wysłał zaproszenia. Lista niekasowalna, w celach bezpieczeństwa.', 'openvote' ); ?></p>
        <div class="openvote-audit-log-box" style="background:#1d2327; color:#f0f0f1; padding:12px 16px; border-radius:4px; max-height:220px; overflow-y:auto; font-family:Consolas, Monaco, monospace; font-size:12px; line-height:1.5;">
            <?php
            if ( empty( $polls_audit_entries ) ) {
                echo '<p style="margin:0; color:#a7aaad;">' . esc_html__( 'Brak wpisów.', 'openvote' ) . '</p>';
            } else {
                foreach ( $polls_audit_entries as $e ) {
                    $t     = isset( $e['t'] ) ? $e['t'] : '';
                    $actor = isset( $e['actor'] ) ? $e['actor'] : '—';
                    $line  = isset( $e['line'] ) ? $e['line'] : '';
                    echo '<div style="margin:2px 0;">' . esc_html( $t . ' ' . $actor . ' ' . $line ) . '</div>';
                }
            }
            ?>
        </div>
        <?php if ( $polls_audit_pages > 1 ) : ?>
        <p class="openvote-audit-log-nav" style="margin:8px 0 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span class="displaying-num" style="color:#646970; font-size:13px;">
                <?php
                echo esc_html( sprintf( __( 'Strona %1$d z %2$d (%3$d wpisów)', 'openvote' ), $polls_audit_page, $polls_audit_pages, $polls_audit_total ) );
                ?>
            </span>
            <?php if ( $polls_audit_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $polls_audit_page - 1, $polls_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Poprzedni', 'openvote' ); ?></a>
            <?php endif; ?>
            <?php if ( $polls_audit_page < $polls_audit_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $polls_audit_page + 1, $polls_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Następny', 'openvote' ); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </section>
</div>
