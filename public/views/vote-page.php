<?php
/**
 * Widok wirtualnej strony „Oddaj głos”.
 * Bez szablonu WordPress — minimalna strona HTML z listą głosowań.
 *
 * Wymagane w kontekście: $vote_page_url (URL powrotu po logowaniu).
 * openvote_render_single_poll() załadowane przez Openvote_Vote_Page::maybe_serve_vote_page().
 */
defined( 'ABSPATH' ) || exit;

$is_logged      = is_user_logged_in();
$user_id        = $is_logged ? get_current_user_id() : 0;
$polls_for_user = [];
$poll_missing_fields = [];
if ( $is_logged ) {
    $active_polls   = Openvote_Poll::get_active_polls();
    $polls_for_user = array_values( array_filter( $active_polls, function ( $poll ) use ( $user_id ) {
        return Openvote_Poll::user_in_target_groups( $user_id, $poll );
    } ) );
    $poll_missing_fields = Openvote_Field_Map::get_missing_fields_for_user( $user_id );
}
if ( ! empty( $poll_missing_fields ) ) {
    wp_enqueue_script(
        'openvote-profile-complete',
        OPENVOTE_PLUGIN_URL . 'public/js/profile-complete.js',
        [],
        OPENVOTE_VERSION,
        true
    );
    wp_enqueue_style(
        'openvote-public',
        OPENVOTE_PLUGIN_URL . 'public/css/openvote-public.css',
        [],
        OPENVOTE_VERSION
    );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Trwające głosowania', 'openvote' ); ?></title>
    <?php wp_head(); ?>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 2rem auto; max-width: 720px; padding: 0 1rem; }
        .openvote-vote-page-brand { text-align: center; margin-bottom: 1.5rem; font-size: 1rem; color: #333; display: flex; align-items: center; justify-content: center; gap: 0.4rem; flex-wrap: wrap; min-height: 1.5em; }
        .openvote-vote-page-header { display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .openvote-page-title { margin: 0; }
        .openvote-poll-block { margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #ddd; }
        .openvote-poll-block:last-child { border-bottom: 0; }
        .openvote-poll__vote-mode { font-size: 0.75rem !important; margin-top: 12px; }
        .openvote-poll__vote-mode legend { font-size: 1em !important; font-weight: 600; }
        .openvote-poll__vote-mode .openvote-poll__option { font-size: 1em !important; }
        .openvote-poll__question { background-color: #e8f4f8; border: 1px solid #e2e4e7; border-radius: 6px; }
    </style>
</head>
<body class="openvote-vote-page">
    <?php
    $logo_url    = openvote_get_logo_url();
    $brand_short = openvote_get_brand_short_name();
    $site_title  = get_bloginfo( 'name' );
    ?>
    <p class="openvote-vote-page-brand">
        <?php if ( $logo_url !== '' ) : ?>
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="openvote-vote-page-logo" style="width:32px;height:32px;object-fit:contain;" />
        <?php endif; ?>
        <?php echo esc_html( $brand_short . ' — ' . $site_title ); ?>
    </p>
    <header class="openvote-vote-page-header">
        <h1 class="openvote-page-title"><?php esc_html_e( 'Trwające głosowania', 'openvote' ); ?></h1>
    </header>

    <?php if ( ! $is_logged ) : ?>
        <p class="openvote-poll__login-notice">
            <?php
            printf(
                /* translators: %1$s: login link, %2$s: lost password link, %3$s: register link */
                esc_html__( 'Aby głosować, %1$s. %2$s. %3$s', 'openvote' ),
                '<a href="' . esc_url( wp_login_url( $vote_page_url ) ) . '">' . esc_html__( 'zaloguj się', 'openvote' ) . '</a>',
                '<a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'nie pamiętam hasła', 'openvote' ) . '</a>',
                '<a href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'zarejestruj się', 'openvote' ) . '</a>'
            );
            ?>
        </p>
    <?php elseif ( ! empty( $poll_missing_fields ) ) : ?>
        <?php
        $context        = 'poll';
        $missing_fields = $poll_missing_fields;
        $nonce          = wp_create_nonce( 'wp_rest' );
        include OPENVOTE_PLUGIN_DIR . 'public/views/partials/profile-complete.php';
        ?>
    <?php elseif ( empty( $polls_for_user ) ) : ?>
        <p class="openvote-poll__no-polls"><?php esc_html_e( 'Brak głosowań w tym momencie.', 'openvote' ); ?></p>
    <?php else : ?>
        <div class="openvote-poll-block openvote-poll-wrapper" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
            <?php foreach ( $polls_for_user as $poll ) : ?>
                <?php openvote_render_single_poll( $poll, $user_id ); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>
</html>
