<?php
/**
 * Server-side render for the evoting/poll block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */
defined( 'ABSPATH' ) || exit;

$poll_id = absint( $attributes['pollId'] ?? 0 );

if ( ! $poll_id ) {
    return;
}

$poll = Evoting_Poll::get( $poll_id );

if ( ! $poll ) {
    return;
}

$is_active = Evoting_Poll::is_active( $poll );
$is_ended  = Evoting_Poll::is_ended( $poll );
$is_logged = is_user_logged_in();
$has_voted = $is_logged ? Evoting_Vote::has_voted( $poll_id, get_current_user_id() ) : false;

// Check eligibility for logged-in, active, not-yet-voted users.
$eligible_error = null;
if ( $is_logged && $is_active && ! $has_voted ) {
    $eligible = Evoting_Vote::is_eligible( $poll_id, get_current_user_id() );
    if ( is_wp_error( $eligible ) ) {
        $eligible_error = $eligible->get_error_message();
    }
}

// End-of-day timestamp for countdown (poll ends at 23:59:59 on end_date).
$end_dt = new DateTimeImmutable( $poll->end_date . ' 23:59:59', wp_timezone() );
$end_ts = $end_dt->getTimestamp();
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'evoting-poll-block' ] ); ?>
     data-poll-id="<?php echo esc_attr( $poll_id ); ?>"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">

    <h3 class="evoting-poll__title"><?php echo esc_html( $poll->title ); ?></h3>

    <?php if ( $poll->description ) : ?>
        <p class="evoting-poll__description"><?php echo esc_html( $poll->description ); ?></p>
    <?php endif; ?>

    <?php if ( $is_ended ) : ?>
        <?php // Results container – loaded via JS. ?>
        <div class="evoting-poll__results" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
            <p class="evoting-poll__status"><?php esc_html_e( 'Głosowanie zakończone. Ładowanie wyników…', 'evoting' ); ?></p>
        </div>

    <?php elseif ( $is_active ) : ?>

        <?php if ( $has_voted ) : ?>
            <p class="evoting-poll__already-voted">
                <?php esc_html_e( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'evoting' ); ?>
            </p>
            <div class="evoting-poll__countdown">
                <?php esc_html_e( 'Głosowanie kończy się: ', 'evoting' ); ?>
                <span class="evoting-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>
            </div>

        <?php elseif ( ! $is_logged ) : ?>
            <?php // Non-logged-in: show questions in read-only, then login prompt. ?>
            <div class="evoting-poll__questions-readonly">
                <?php foreach ( $poll->questions as $i => $question ) : ?>
                    <div class="evoting-poll__question-readonly">
                        <p class="evoting-poll__question-text">
                            <strong><?php echo esc_html( ( $i + 1 ) . '. ' . $question->question_text ); ?></strong>
                        </p>
                        <ul class="evoting-poll__answers-list">
                            <?php foreach ( $question->answers as $answer ) : ?>
                                <li><?php echo esc_html( $answer->answer_text ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="evoting-poll__login-notice">
                <?php
                printf(
                    /* translators: %s: login link */
                    esc_html__( 'Aby oddać głos, %s.', 'evoting' ),
                    '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'zaloguj się', 'evoting' ) . '</a>'
                );
                ?>
            </p>

        <?php elseif ( $eligible_error ) : ?>
            <?php // Logged-in but ineligible – show questions read-only + reason. ?>
            <div class="evoting-poll__questions-readonly">
                <?php foreach ( $poll->questions as $i => $question ) : ?>
                    <div class="evoting-poll__question-readonly">
                        <p class="evoting-poll__question-text">
                            <strong><?php echo esc_html( ( $i + 1 ) . '. ' . $question->question_text ); ?></strong>
                        </p>
                        <ul class="evoting-poll__answers-list">
                            <?php foreach ( $question->answers as $answer ) : ?>
                                <li><?php echo esc_html( $answer->answer_text ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="evoting-poll__ineligible"><?php echo esc_html( $eligible_error ); ?></p>

        <?php else : ?>
            <?php // Voting form for eligible, logged-in user. ?>
            <form class="evoting-poll__form" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
                <div class="evoting-poll__countdown">
                    <?php esc_html_e( 'Pozostało: ', 'evoting' ); ?>
                    <span class="evoting-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>
                </div>

                <?php foreach ( $poll->questions as $i => $question ) : ?>
                    <fieldset class="evoting-poll__question">
                        <legend><?php echo esc_html( ( $i + 1 ) . '. ' . $question->question_text ); ?></legend>
                        <?php foreach ( $question->answers as $answer ) : ?>
                            <label class="evoting-poll__option">
                                <input type="radio"
                                       name="question_<?php echo esc_attr( $question->id ); ?>"
                                       value="<?php echo esc_attr( $answer->id ); ?>"
                                       required>
                                <?php echo esc_html( $answer->answer_text ); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>

                <button type="submit" class="evoting-poll__submit wp-element-button">
                    <?php esc_html_e( 'Oddaj głos', 'evoting' ); ?>
                </button>
                <div class="evoting-poll__message" aria-live="polite"></div>
            </form>
        <?php endif; ?>

    <?php else : ?>
        <?php // Poll not started yet. ?>
        <p class="evoting-poll__not-started">
            <?php
            printf(
                /* translators: %s: start date */
                esc_html__( 'Głosowanie rozpocznie się: %s', 'evoting' ),
                esc_html( $poll->start_date )
            );
            ?>
        </p>
    <?php endif; ?>
</div>
