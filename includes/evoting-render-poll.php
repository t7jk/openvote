<?php
/**
 * Współdzielona funkcja renderująca pojedyncze głosowanie (formularz / wyniki / komunikat).
 * Używana przez blok evoting/poll oraz wirtualną stronę głosowania.
 *
 * @param object $poll    Poll with questions.
 * @param int    $user_id Current user ID.
 */
defined( 'ABSPATH' ) || exit;

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
    <?php
    $poll_title       = isset( $poll->title ) ? trim( (string) $poll->title ) : '';
    $poll_description = isset( $poll->description ) ? trim( (string) $poll->description ) : '';
    $title_display    = $poll_title !== '' ? $poll_title : __( '— nie podano.', 'evoting' );
    $desc_display     = $poll_description !== '' ? $poll_description : __( '— nie podano.', 'evoting' );
    ?>
    <div class="evoting-poll-block evoting-poll-block--single" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
        <h3 class="evoting-poll__title"><?php echo esc_html__( 'Tytuł:', 'evoting' ); ?> <?php echo esc_html( $title_display ); ?></h3>
        <p class="evoting-poll__description"><?php echo esc_html__( 'Opis:', 'evoting' ); ?> <?php echo esc_html( $desc_display ); ?></p>

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
                <?php
                $user = get_userdata( $user_id );
                $open_label = '';
                if ( $user && $user->exists() ) {
                    $first = Evoting_Field_Map::get_user_value( $user, 'first_name' );
                    $last  = Evoting_Field_Map::get_user_value( $user, 'last_name' );
                    $city  = Evoting_Field_Map::get_user_value( $user, 'city' );
                    $email = Evoting_Field_Map::get_user_value( $user, 'email' );
                    $email_anon = $email !== '' ? Evoting_Vote::anonymize_email( $email ) : '…';
                    $name_part = trim( $first . ' ' . $last );
                    if ( $name_part === '' ) {
                        $name_part = '—';
                    }
                    if ( $city === '' ) {
                        $city = '—';
                    }
                    $open_label = sprintf(
                        /* translators: 1: full name, 2: city in parentheses, 3: masked email */
                        __( 'Głosuj jawnie - wyniki będą zawierać %1$s (%2$s) %3$s.', 'evoting' ),
                        $name_part,
                        $city,
                        $email_anon
                    );
                } else {
                    $open_label = __( 'Głosuj jawnie - wyniki będą zawierać imię nazwisko (miasto) email.', 'evoting' );
                }
                $nickname = ( $user && $user->exists() ) ? Evoting_Field_Map::get_user_value( $user, 'nickname' ) : '';
                $anon_label = ( $nickname !== '' )
                    ? sprintf( __( 'Głosuj Anonimowo - wyniki zawierają tylko nazwę: %s.', 'evoting' ), $nickname )
                    : __( 'Głosuj Anonimowo - wyniki zawierają tylko nazwę: —.', 'evoting' );
                ?>
                <fieldset class="evoting-poll__vote-mode" required>
                    <legend><?php esc_html_e( 'Sposób oddania głosu', 'evoting' ); ?></legend>
                    <label class="evoting-poll__option">
                        <input type="radio" name="evoting_vote_visibility" value="0" required>
                        <?php echo esc_html( $open_label ); ?>
                    </label>
                    <label class="evoting-poll__option">
                        <input type="radio" name="evoting_vote_visibility" value="1">
                        <?php echo esc_html( $anon_label ); ?>
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
