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

$is_active   = Evoting_Poll::is_active( $poll );
$is_ended    = Evoting_Poll::is_ended( $poll );
$is_logged   = is_user_logged_in();
$has_voted   = $is_logged ? Evoting_Vote::has_voted( $poll_id, get_current_user_id() ) : false;
$end_date_ts = strtotime( $poll->end_date );
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'evoting-poll-block' ] ); ?>
     data-poll-id="<?php echo esc_attr( $poll_id ); ?>"
     data-end-date="<?php echo esc_attr( gmdate( 'c', $end_date_ts ) ); ?>"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">

    <h3 class="evoting-poll__title"><?php echo esc_html( $poll->title ); ?></h3>

    <?php if ( $poll->description ) : ?>
        <p class="evoting-poll__description"><?php echo esc_html( $poll->description ); ?></p>
    <?php endif; ?>

    <?php if ( $is_ended ) : ?>
        <?php // Show results. ?>
        <div class="evoting-poll__results" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
            <p class="evoting-poll__status"><?php esc_html_e( 'Głosowanie zakończone. Ładowanie wyników…', 'evoting' ); ?></p>
        </div>

    <?php elseif ( ! $is_logged ) : ?>
        <p class="evoting-poll__login-notice">
            <?php
            printf(
                /* translators: %s: login URL */
                esc_html__( 'Aby oddać głos, %s.', 'evoting' ),
                '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'zaloguj się', 'evoting' ) . '</a>'
            );
            ?>
        </p>

    <?php elseif ( $has_voted ) : ?>
        <p class="evoting-poll__already-voted">
            <?php esc_html_e( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'evoting' ); ?>
        </p>
        <?php if ( $is_active ) : ?>
            <div class="evoting-poll__countdown">
                <span class="evoting-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_date_ts ) ); ?>"></span>
            </div>
        <?php endif; ?>

    <?php elseif ( $is_active ) : ?>
        <?php // Voting form. ?>
        <form class="evoting-poll__form" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
            <div class="evoting-poll__countdown">
                <?php esc_html_e( 'Pozostało: ', 'evoting' ); ?>
                <span class="evoting-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_date_ts ) ); ?>"></span>
            </div>

            <?php foreach ( $poll->questions as $i => $question ) : ?>
                <fieldset class="evoting-poll__question">
                    <legend><?php echo esc_html( ( $i + 1 ) . '. ' . $question->question_text ); ?></legend>
                    <label class="evoting-poll__option">
                        <input type="radio" name="question_<?php echo esc_attr( $question->id ); ?>" value="za" required>
                        <?php esc_html_e( 'Jestem za', 'evoting' ); ?>
                    </label>
                    <label class="evoting-poll__option">
                        <input type="radio" name="question_<?php echo esc_attr( $question->id ); ?>" value="przeciw">
                        <?php esc_html_e( 'Jestem przeciw', 'evoting' ); ?>
                    </label>
                    <label class="evoting-poll__option">
                        <input type="radio" name="question_<?php echo esc_attr( $question->id ); ?>" value="wstrzymuje_sie">
                        <?php esc_html_e( 'Wstrzymuję się od głosu', 'evoting' ); ?>
                    </label>
                </fieldset>
            <?php endforeach; ?>

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
                esc_html( $poll->start_date )
            );
            ?>
        </p>
    <?php endif; ?>
</div>
