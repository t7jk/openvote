<?php
/**
 * Panel admina — strona Przepisy prawne.
 * Dostępna dla wszystkich zalogowanych użytkowników WordPress.
 */
defined( 'ABSPATH' ) || exit;

?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Przepisy prawne', 'openvote' ); ?></h1>
    <hr class="wp-header-end">

    <div style="max-width:860px;background:#fff;border:1px solid #e2e4e7;border-radius:4px;padding:28px 36px;margin-top:16px;line-height:1.8;font-size:15px;">
        <?php require OPENVOTE_PLUGIN_DIR . 'includes/openvote-law-content.php'; ?>
    </div>
</div>
