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
    <title><?php esc_html_e( 'Trwające głosowania', 'evoting' ); ?></title>
    <?php wp_head(); ?>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 2rem auto; max-width: 720px; padding: 0 1rem; }
        .evoting-vote-page-banner { text-align: center; margin-bottom: 0.75rem; }
        .evoting-vote-page-banner img { margin: 0 auto; display: block; max-width: 100%; max-height: 260px; object-fit: contain; }
        .evoting-vote-page-brand { text-align: center; margin-bottom: 1.5rem; font-size: 1rem; color: #333; display: flex; align-items: center; justify-content: center; gap: 0.4rem; flex-wrap: wrap; min-height: 1.5em; }
        .evoting-vote-page-header { display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .evoting-page-title { margin: 0; }
        .evoting-poll-block { margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #ddd; }
        .evoting-poll-block:last-child { border-bottom: 0; }
        .evoting-poll__vote-mode { font-size: 0.75rem !important; margin-top: 12px; }
        .evoting-poll__vote-mode legend { font-size: 1em !important; font-weight: 600; }
        .evoting-poll__vote-mode .evoting-poll__option { font-size: 1em !important; }
        .evoting-poll__question { background-color: #e8f4f8; border: 1px solid #e2e4e7; border-radius: 6px; }
    </style>
</head>
<body class="evoting-vote-page">
    <?php
    $banner_url  = evoting_get_banner_url();
    $logo_url    = evoting_get_logo_url();
    $brand_short = evoting_get_brand_short_name();
    $brand_full  = evoting_get_brand_full_name();
    ?>
    <?php if ( $banner_url !== '' ) : ?>
        <div class="evoting-vote-page-banner">
            <img src="<?php echo esc_url( $banner_url ); ?>" alt="" />
        </div>
    <?php endif; ?>
    <p class="evoting-vote-page-brand">
        <?php if ( $logo_url !== '' ) : ?>
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="evoting-vote-page-logo" style="width:32px;height:32px;object-fit:contain;" />
        <?php endif; ?>
        <?php echo esc_html( $brand_short . ' — ' . $brand_full ); ?>
    </p>
    <header class="evoting-vote-page-header">
        <h1 class="evoting-page-title"><?php esc_html_e( 'Trwające głosowania', 'evoting' ); ?></h1>
    </header>

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
