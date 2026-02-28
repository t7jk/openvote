<?php
defined( 'ABSPATH' ) || exit;

$polls = Openvote_Poll::get_all();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Głosowania', 'openvote' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Dodaj nowe', 'openvote' ); ?>
    </a>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-law' ) ); ?>"
       class="page-title-action"
       style="margin-left:4px;"
       title="<?php esc_attr_e( 'Przepisy prawne obowiązujące w głosowaniach', 'openvote' ); ?>">
        ⚖️ <?php esc_html_e( 'Przepisy', 'openvote' ); ?>
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
</div>
