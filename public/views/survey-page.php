<?php
/**
 * Fallback template dla publicznej strony ankiet.
 * Używany gdy WordPress Page z blokiem nie istnieje.
 * Renderowany w kontekście aktywnego motywu (get_header/get_footer).
 */
defined( 'ABSPATH' ) || exit;
?>
<?php get_header(); ?>

<div class="openvote-survey-page-wrap">
    <div class="openvote-survey-page-content">
        <?php
        // Renderuj ten sam HTML co blok Gutenberg (przez include).
        $attributes = [];
        $content    = '';
        include OPENVOTE_PLUGIN_DIR . 'blocks/openvote-survey-form/render.php';
        ?>
    </div>
</div>

<?php get_footer(); ?>
