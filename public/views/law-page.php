<?php
/**
 * Publiczny widok strony przepisów prawnych.
 * Renderowany w kontekście aktywnego motywu WordPress, dostępny bez logowania.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="evoting-law-page-wrap">
    <?php require EVOTING_PLUGIN_DIR . 'includes/evoting-law-content.php'; ?>

    <p class="evoting-law-back">
        <a href="<?php echo esc_url( evoting_get_vote_page_url() ); ?>">
            ← <?php esc_html_e( 'Powrót do głosowań', 'evoting' ); ?>
        </a>
    </p>
</div>

<?php get_footer(); ?>
