<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;

$error = get_transient( 'evoting_uninstall_error' );
if ( $error ) {
    delete_transient( 'evoting_uninstall_error' );
}

// Gather table sizes for info display.
$tables = [
    $wpdb->prefix . 'evoting_polls',
    $wpdb->prefix . 'evoting_questions',
    $wpdb->prefix . 'evoting_answers',
    $wpdb->prefix . 'evoting_votes',
];

$table_rows = [];
foreach ( $tables as $table ) {
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
    $table_rows[ $table ] = $count;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Odinstaluj wtyczkę', 'evoting' ); ?></h1>

    <?php if ( $error ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>

    <div class="evoting-danger-zone">
        <div class="evoting-danger-zone__header">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e( 'Strefa zagrożenia — operacja nieodwracalna', 'evoting' ); ?>
        </div>

        <div class="evoting-danger-zone__body">
            <p><?php esc_html_e(
                'Poniższa operacja nieodwracalnie usunie wszystkie dane wtyczki z bazy danych '
                . 'i dezaktywuje wtyczkę. Pliki wtyczki pozostaną na serwerze — '
                . 'możesz je usunąć ręcznie ze strony Wtyczki.',
                'evoting'
            ); ?></p>

            <h3><?php esc_html_e( 'Co zostanie usunięte:', 'evoting' ); ?></h3>

            <table class="widefat fixed" style="max-width:560px;margin-bottom:20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Tabela / opcja', 'evoting' ); ?></th>
                        <th style="width:140px;"><?php esc_html_e( 'Zawartość', 'evoting' ); ?></th>
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
                                    esc_html( _n( '%d rekord', '%d rekordów', $count, 'evoting' ) ),
                                    $count
                                );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><code>evoting_version</code>, <code>evoting_db_version</code>, <code>evoting_field_map</code>, <code>evoting_vote_page_slug</code>, <code>evoting_time_offset_hours</code></td>
                        <td><?php esc_html_e( 'Opcje WordPress', 'evoting' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="" id="evoting-uninstall-form">
                <?php wp_nonce_field( 'evoting_uninstall', 'evoting_uninstall_nonce' ); ?>

                <label class="evoting-confirm-label">
                    <input type="checkbox" name="evoting_confirm_uninstall" id="evoting_confirm_uninstall" value="1">
                    <?php esc_html_e(
                        'Rozumiem, że operacja jest nieodwracalna i wszystkie dane głosowań zostaną trwale usunięte.',
                        'evoting'
                    ); ?>
                </label>

                <p style="margin-top:20px;">
                    <button type="submit"
                            id="evoting-uninstall-btn"
                            class="button evoting-btn-danger"
                            disabled>
                        <span class="dashicons dashicons-trash" style="margin-top:3px;"></span>
                        <?php esc_html_e( 'Usuń dane i dezaktywuj wtyczkę', 'evoting' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting' ) ); ?>"
                       class="button" style="margin-left:8px;">
                        <?php esc_html_e( 'Anuluj', 'evoting' ); ?>
                    </a>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var cb  = document.getElementById('evoting_confirm_uninstall');
    var btn = document.getElementById('evoting-uninstall-btn');
    if (!cb || !btn) return;
    cb.addEventListener('change', function () {
        btn.disabled = !cb.checked;
    });
})();
</script>
