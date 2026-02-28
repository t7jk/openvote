<?php
/**
 * Server-side render for the openvote/poll block.
 *
 * Blok bez wyboru konkretnego głosowania: dla gości — zachęta do logowania;
 * dla zalogowanych — wyświetlane są tylko aktywne głosowania, do których użytkownik
 * należy (kryteria: czas trwania + przynależność do grupy docelowej).
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */
defined( 'ABSPATH' ) || exit;

require_once OPENVOTE_PLUGIN_DIR . 'includes/openvote-render-poll.php';

$is_logged = is_user_logged_in();
$user_id   = $is_logged ? get_current_user_id() : 0;

// ─── Gość: tylko zachęta do logowania ─────────────────────────────────────
if ( ! $is_logged ) {
    ?>
    <div <?php echo get_block_wrapper_attributes( [ 'class' => 'openvote-poll-block openvote-poll-block--login' ] ); ?>>
        <p class="openvote-poll__login-notice">
            <?php
            printf(
                /* translators: %s: login link */
                esc_html__( 'Aby zobaczyć dostępne głosowania i oddać głos, %s.', 'openvote' ),
                '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'zaloguj się', 'openvote' ) . '</a>'
            );
            ?>
        </p>
    </div>
    <?php
    return;
}

// ─── Zalogowany: aktywne głosowania pasujące do użytkownika (czas + grupa) ─
$active_polls   = Openvote_Poll::get_active_polls();
$polls_for_user = array_filter( $active_polls, function ( $poll ) use ( $user_id ) {
    return Openvote_Poll::user_in_target_groups( $user_id, $poll );
} );
$polls_for_user = array_values( $polls_for_user );

if ( ! empty( $polls_for_user ) ) {
    ?>
    <div <?php echo get_block_wrapper_attributes( [ 'class' => 'openvote-poll-block openvote-poll-wrapper' ] ); ?> data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
        <?php
        foreach ( $polls_for_user as $poll ) {
            openvote_render_single_poll( $poll, $user_id );
        }
        ?>
    </div>
    <?php
    return;
}

// ─── Brak aktywnych: komunikaty o zakończonych (nie głosował) lub brak dostępu ─
$ended_not_voted = Openvote_Poll::get_ended_polls_eligible_not_voted( $user_id );

if ( ! empty( $ended_not_voted ) ) {
    ?>
    <div <?php echo get_block_wrapper_attributes( [ 'class' => 'openvote-poll-block openvote-poll-wrapper' ] ); ?> data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
        <?php
        foreach ( $ended_not_voted as $poll ) {
            $end_display = strlen( $poll->date_end ) > 10 ? $poll->date_end : $poll->date_end . ' 23:59:59';
            ?>
        <div class="openvote-poll-block openvote-poll-block--ended">
            <h3 class="openvote-poll__title"><?php echo esc_html( $poll->title ); ?></h3>
            <p class="openvote-poll__ended-notice">
                <?php
                printf(
                    /* translators: 1: end date/time, 2: link text */
                    esc_html__( 'Głosowanie dobiegło końca dnia %1$s, %2$s.', 'openvote' ),
                    esc_html( $end_display ),
                    '<a href="#openvote-results-' . esc_attr( $poll->id ) . '">' . esc_html__( 'zobacz wyniki', 'openvote' ) . '</a>'
                );
                ?>
            </p>
            <div id="openvote-results-<?php echo esc_attr( $poll->id ); ?>" class="openvote-poll__results" data-poll-id="<?php echo esc_attr( $poll->id ); ?>">
                <p class="openvote-poll__status"><?php esc_html_e( 'Ładowanie wyników…', 'openvote' ); ?></p>
            </div>
        </div>
        <?php
        }
    ?>
    </div>
    <?php
    return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'openvote-poll-block openvote-poll-block--no-access' ] ); ?>>
    <p class="openvote-poll__no-access">
        <?php esc_html_e( 'Nie możesz brać udziału w żadnym obecnie trwającym głosowaniu.', 'openvote' ); ?>
    </p>
</div>
