<?php
/**
 * Widok wirtualnej strony „Oddaj głos”.
 * Bez szablonu WordPress — minimalna strona HTML z listą głosowań.
 *
 * Wymagane w kontekście: $vote_page_url (URL powrotu po logowaniu).
 * evoting_render_single_poll() załadowane przez Evoting_Vote_Page::maybe_serve_vote_page().
 */
defined( 'ABSPATH' ) || exit;

$is_logged      = is_user_logged_in();
$user_id        = $is_logged ? get_current_user_id() : 0;
$polls_for_user = [];
if ( $is_logged ) {
    $active_polls   = Evoting_Poll::get_active_polls();
    $polls_for_user = array_values( array_filter( $active_polls, function ( $poll ) use ( $user_id ) {
        return Evoting_Poll::user_in_target_groups( $user_id, $poll );
    } ) );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Oddaj głos', 'evoting' ); ?></title>
    <?php wp_head(); ?>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 2rem; max-width: 720px; }
        .evoting-page-title { margin-bottom: 1.5rem; }
        .evoting-poll-block { margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #ddd; }
        .evoting-poll-block:last-child { border-bottom: 0; }
    </style>
</head>
<body class="evoting-vote-page">
    <h1 class="evoting-page-title"><?php esc_html_e( 'Oddaj głos', 'evoting' ); ?></h1>

    <?php if ( ! $is_logged ) : ?>
        <p class="evoting-poll__login-notice">
            <?php
            printf(
                /* translators: %s: login link with text "zaloguj się" */
                esc_html__( 'Aby głosować, %s.', 'evoting' ),
                '<a href="' . esc_url( wp_login_url( $vote_page_url ) ) . '">' . esc_html__( 'zaloguj się', 'evoting' ) . '</a>'
            );
            ?>
        </p>
    <?php elseif ( empty( $polls_for_user ) ) : ?>
        <p class="evoting-poll__no-polls"><?php esc_html_e( 'Brak głosowań w tym momencie.', 'evoting' ); ?></p>
    <?php else : ?>
        <div class="evoting-poll-block evoting-poll-wrapper" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
            <?php foreach ( $polls_for_user as $poll ) : ?>
                <?php evoting_render_single_poll( $poll, $user_id ); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>
</html>
