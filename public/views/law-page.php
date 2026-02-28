<?php
/**
 * Publiczny widok strony przepisów prawnych.
 * Renderowany w kontekście aktywnego motywu WordPress, dostępny bez logowania.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="openvote-law-page-wrap">
    <?php require OPENVOTE_PLUGIN_DIR . 'includes/openvote-law-content.php'; ?>

    <p class="openvote-law-back">
        <a href="<?php echo esc_url( openvote_get_vote_page_url() ); ?>">
            ← <?php esc_html_e( 'Powrót do głosowań', 'openvote' ); ?>
        </a>
    </p>
</div>

<?php get_footer(); ?>
