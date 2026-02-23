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

<?php
/**
 * Render one poll block (active: form or already voted or ineligible; ended: results).
 *
 * @param object $poll    Poll with questions.
 * @param int    $user_id Current user ID.
 */
function evoting_render_single_poll( object $poll, int $user_id ): void {
    $poll_id      = (int) $poll->id;
    $is_active    = Evoting_Poll::is_active( $poll );
    $is_ended     = Evoting_Poll::is_ended( $poll );
    $has_voted    = Evoting_Vote::has_voted( $poll_id, $user_id );
    $eligible_check = $is_active && ! $has_voted ? Evoting_Eligibility::can_vote( $user_id, $poll_id ) : null;
    $eligible_error = ( $eligible_check && ! $eligible_check['eligible'] ) ? $eligible_check['reason'] : null;

    $end_raw = $poll->date_end;
    if ( strlen( $end_raw ) === 10 ) {
        $end_raw .= ' 23:59:59';
    }
    $end_dt = new DateTimeImmutable( $end_raw, wp_timezone() );
    $end_ts = $end_dt->getTimestamp();
    ?>
    <div class="evoting-poll-block evoting-poll-block--single" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
        <h3 class="evoting-poll__title"><?php echo esc_html( $poll->title ); ?></h3>
        <?php if ( ! empty( $poll->description ) ) : ?>
            <p class="evoting-poll__description"><?php echo esc_html( $poll->description ); ?></p>
        <?php endif; ?>

        <?php if ( $is_ended ) : ?>
            <div class="evoting-poll__results" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
                <p class="evoting-poll__status"><?php esc_html_e( 'Głosowanie zakończone. Ładowanie wyników…', 'evoting' ); ?></p>
            </div>

        <?php elseif ( $is_active && $has_voted ) : ?>
            <p class="evoting-poll__already-voted">
                <?php esc_html_e( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'evoting' ); ?>
            </p>
            <div class="evoting-poll__countdown">
                <?php esc_html_e( 'Głosowanie kończy się: ', 'evoting' ); ?>
                <span class="evoting-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>
            </div>

        <?php elseif ( $is_active && $eligible_error ) : ?>
            <div class="evoting-poll__questions-readonly">
                <?php foreach ( $poll->questions as $i => $question ) : ?>
                    <div class="evoting-poll__question-readonly">
                        <p class="evoting-poll__question-text">
                            <strong><?php echo esc_html( ( $i + 1 ) . '. ' . $question->body ); ?></strong>
                        </p>
                        <ul class="evoting-poll__answers-list">
                            <?php foreach ( $question->answers as $answer ) : ?>
                                <li><?php echo esc_html( $answer->body ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="evoting-poll__ineligible"><?php echo esc_html( $eligible_error ); ?></p>

        <?php elseif ( $is_active ) : ?>
            <form class="evoting-poll__form" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
                <div class="evoting-poll__countdown">
                    <?php esc_html_e( 'Pozostało: ', 'evoting' ); ?>
                    <span class="evoting-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>
                </div>
                <?php foreach ( $poll->questions as $i => $question ) : ?>
                    <fieldset class="evoting-poll__question">
                        <legend><?php echo esc_html( ( $i + 1 ) . '. ' . $question->body ); ?></legend>
                        <?php foreach ( $question->answers as $answer ) : ?>
                            <label class="evoting-poll__option">
                                <input type="radio"
                                       name="question_<?php echo esc_attr( $question->id ); ?>"
                                       value="<?php echo esc_attr( $answer->id ); ?>"
                                       required>
                                <?php echo esc_html( $answer->body ); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
                <fieldset class="evoting-poll__vote-mode" required>
                    <legend><?php esc_html_e( 'Sposób oddania głosu', 'evoting' ); ?></legend>
                    <label class="evoting-poll__option">
                        <input type="radio" name="evoting_vote_visibility" value="0" required>
                        <?php esc_html_e( 'Głosuj Jawnie — Twoje dane zostaną umieszczone w wynikach', 'evoting' ); ?>
                    </label>
                    <label class="evoting-poll__option">
                        <input type="radio" name="evoting_vote_visibility" value="1">
                        <?php esc_html_e( 'Głosuj Anonimowo — Twoje dane zostaną ukryte w wynikach', 'evoting' ); ?>
                    </label>
                </fieldset>
                <button type="submit" class="evoting-poll__submit wp-element-button">
                    <?php esc_html_e( 'Oddaj głos', 'evoting' ); ?>
                </button>
                <div class="evoting-poll__message" aria-live="polite"></div>
            </form>

        <?php else : ?>
            <p class="evoting-poll__not-started">
                <?php
                printf(
                    /* translators: %s: start date */
                    esc_html__( 'Głosowanie rozpocznie się: %s', 'evoting' ),
                    esc_html( $poll->date_start )
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
