<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;

$error = get_transient( 'openvote_uninstall_error' );
if ( $error ) {
    delete_transient( 'openvote_uninstall_error' );
}

// Gather table sizes for info display.
$tables = [
    $wpdb->prefix . 'openvote_polls',
    $wpdb->prefix . 'openvote_questions',
    $wpdb->prefix . 'openvote_answers',
    $wpdb->prefix . 'openvote_votes',
];

$table_rows = [];
foreach ( $tables as $table ) {
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
    $table_rows[ $table ] = $count;
}
?>
    <?php if ( $error ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>

    <div class="openvote-danger-zone">
        <div class="openvote-danger-zone__header">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e( 'Strefa zagrożenia — operacja nieodwracalna', 'openvote' ); ?>
        </div>

        <div class="openvote-danger-zone__body">
            <p><?php esc_html_e(
                'Poniższa operacja nieodwracalnie usunie wszystkie dane wtyczki z bazy danych '
                . 'i dezaktywuje wtyczkę. Pliki wtyczki pozostaną na serwerze — '
                . 'możesz je usunąć ręcznie ze strony Wtyczki.',
                'openvote'
            ); ?></p>

            <h3><?php esc_html_e( 'Co zostanie usunięte:', 'openvote' ); ?></h3>

            <table class="widefat fixed" style="max-width:560px;margin-bottom:20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Tabela / opcja', 'openvote' ); ?></th>
                        <th style="width:140px;"><?php esc_html_e( 'Zawartość', 'openvote' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $table_rows as $table => $count ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $table ); ?></code></td>
                            <td>
                                <?php
                                printf(
                                    /* translators: %d: row count */
                                    esc_html( _n( '%d rekord', '%d rekordów', $count, 'openvote' ) ),
                                    $count
                                );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><code>openvote_version</code>, <code>openvote_db_version</code>, <code>openvote_field_map</code>, <code>openvote_vote_page_slug</code>, <code>openvote_time_offset_hours</code></td>
                        <td><?php esc_html_e( 'Opcje WordPress', 'openvote' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="" id="openvote-uninstall-form">
                <?php wp_nonce_field( 'openvote_uninstall', 'openvote_uninstall_nonce' ); ?>

                <label class="openvote-confirm-label">
                    <input type="checkbox" name="openvote_confirm_uninstall" id="openvote_confirm_uninstall" value="1">
                    <?php esc_html_e(
                        'Rozumiem, że operacja jest nieodwracalna i wszystkie dane głosowań zostaną trwale usunięte.',
                        'openvote'
                    ); ?>
                </label>

                <p style="margin-top:20px;">
                    <button type="submit"
                            id="openvote-uninstall-btn"
                            class="button openvote-btn-danger"
                            disabled>
                        <span class="dashicons dashicons-trash" style="margin-top:3px;"></span>
                        <?php esc_html_e( 'Usuń dane i dezaktywuj wtyczkę', 'openvote' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote' ) ); ?>"
                       class="button" style="margin-left:8px;">
                        <?php esc_html_e( 'Anuluj', 'openvote' ); ?>
                    </a>
                </p>
            </form>
        </div>
    </div>

<script>
(function () {
    var cb  = document.getElementById('openvote_confirm_uninstall');
    var btn = document.getElementById('openvote-uninstall-btn');
    if (!cb || !btn) return;
    cb.addEventListener('change', function () {
        btn.disabled = !cb.checked;
    });
})();
</script>
