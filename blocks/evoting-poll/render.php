<?php
/**
 * Server-side render for the evoting/poll block.
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

require_once EVOTING_PLUGIN_DIR . 'includes/evoting-render-poll.php';

$is_logged = is_user_logged_in();
$user_id   = $is_logged ? get_current_user_id() : 0;

// ─── Gość: tylko zachęta do logowania ─────────────────────────────────────
if ( ! $is_logged ) {
    ?>
    <div <?php echo get_block_wrapper_attributes( [ 'class' => 'evoting-poll-block evoting-poll-block--login' ] ); ?>>
        <p class="evoting-poll__login-notice">
            <?php
            printf(
                /* translators: %s: login link */
                esc_html__( 'Aby zobaczyć dostępne głosowania i oddać głos, %s.', 'evoting' ),
                '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'zaloguj się', 'evoting' ) . '</a>'
            );
            ?>
        </p>
    </div>
    <?php
    return;
}

// ─── Zalogowany: aktywne głosowania pasujące do użytkownika (czas + grupa) ─
$active_polls   = Evoting_Poll::get_active_polls();
$polls_for_user = array_filter( $active_polls, function ( $poll ) use ( $user_id ) {
    return Evoting_Poll::user_in_target_groups( $user_id, $poll );
} );
$polls_for_user = array_values( $polls_for_user );

if ( ! empty( $polls_for_user ) ) {
    ?>
    <div <?php echo get_block_wrapper_attributes( [ 'class' => 'evoting-poll-block evoting-poll-wrapper' ] ); ?> data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
        <?php
        foreach ( $polls_for_user as $poll ) {
            evoting_render_single_poll( $poll, $user_id );
        }
        ?>
    </div>
    <?php
    return;
}

// ─── Brak aktywnych: komunikaty o zakończonych (nie głosował) lub brak dostępu ─
$ended_not_voted = Evoting_Poll::get_ended_polls_eligible_not_voted( $user_id );

if ( ! empty( $ended_not_voted ) ) {
    ?>
    <div <?php echo get_block_wrapper_attributes( [ 'class' => 'evoting-poll-block evoting-poll-wrapper' ] ); ?> data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
        <?php
        foreach ( $ended_not_voted as $poll ) {
            $end_display = strlen( $poll->date_end ) > 10 ? $poll->date_end : $poll->date_end . ' 23:59:59';
            ?>
        <div class="evoting-poll-block evoting-poll-block--ended">
            <h3 class="evoting-poll__title"><?php echo esc_html( $poll->title ); ?></h3>
            <p class="evoting-poll__ended-notice">
                <?php
                printf(
                    /* translators: 1: end date/time, 2: link text */
                    esc_html__( 'Głosowanie dobiegło końca dnia %1$s, %2$s.', 'evoting' ),
                    esc_html( $end_display ),
                    '<a href="#evoting-results-' . esc_attr( $poll->id ) . '">' . esc_html__( 'zobacz wyniki', 'evoting' ) . '</a>'
                );
                ?>
            </p>
            <div id="evoting-results-<?php echo esc_attr( $poll->id ); ?>" class="evoting-poll__results" data-poll-id="<?php echo esc_attr( $poll->id ); ?>">
                <p class="evoting-poll__status"><?php esc_html_e( 'Ładowanie wyników…', 'evoting' ); ?></p>
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
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'evoting-poll-block evoting-poll-block--no-access' ] ); ?>>
    <p class="evoting-poll__no-access">
        <?php esc_html_e( 'Nie możesz brać udziału w żadnym obecnie trwającym głosowaniu.', 'evoting' ); ?>
    </p>
</div>
