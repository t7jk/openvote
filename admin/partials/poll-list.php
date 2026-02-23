<?php
defined( 'ABSPATH' ) || exit;

$polls = Evoting_Poll::get_all();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Głosowania', 'evoting' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Dodaj nowe', 'evoting' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Głosowanie zostało usunięte.', 'evoting' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'evoting_admin_error' );
    if ( $error ) :
        delete_transient( 'evoting_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Tytuł', 'evoting' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'evoting' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Tryb', 'evoting' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Pytania', 'evoting' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Data rozpoczęcia', 'evoting' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Data zakończenia', 'evoting' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Akcje', 'evoting' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $polls ) ) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'Brak głosowań.', 'evoting' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $polls as $poll ) :
                    $questions     = Evoting_Poll::get_questions( (int) $poll->id );
                    $status_labels = [
                        'draft'  => __( 'Szkic', 'evoting' ),
                        'open'   => __( 'Rozpoczęte', 'evoting' ),
                        'closed' => __( 'Zakończone', 'evoting' ),
                    ];
                    $mode_label = 'anonymous' === ( $poll->vote_mode ?? 'public' )
                        ? __( '🔒 Anonim.', 'evoting' )
                        : __( 'Jawne', 'evoting' );
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $poll->id ) ); ?>">
                                    <?php echo esc_html( $poll->title ); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <span class="evoting-status evoting-status--<?php echo esc_attr( $poll->status ); ?>">
                                <?php echo esc_html( $status_labels[ $poll->status ] ?? $poll->status ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $mode_label ); ?></td>
                        <td><?php echo esc_html( count( $questions ) ); ?></td>
                        <td><?php echo esc_html( $poll->date_start ); ?></td>
                        <td><?php echo esc_html( $poll->date_end ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $poll->id ) ); ?>">
                                <?php esc_html_e( 'Edytuj', 'evoting' ); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=results&poll_id=' . $poll->id ) ); ?>">
                                <?php esc_html_e( 'Wyniki', 'evoting' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
