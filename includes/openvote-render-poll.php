<?php
/**
 * Współdzielona funkcja renderująca pojedyncze głosowanie (formularz / wyniki / komunikat).
 * Używana przez blok openvote/poll oraz wirtualną stronę głosowania.
 *
 * @param object $poll    Poll with questions.
 * @param int    $user_id Current user ID.
 */
defined( 'ABSPATH' ) || exit;

function openvote_render_single_poll( object $poll, int $user_id ): void {
    $poll_id      = (int) $poll->id;
    $is_active    = Openvote_Poll::is_active( $poll );
    $is_ended     = Openvote_Poll::is_ended( $poll );
    $has_voted    = Openvote_Vote::has_voted( $poll_id, $user_id );
    $eligible_check = $is_active && ! $has_voted ? Openvote_Eligibility::can_vote( $user_id, $poll_id ) : null;
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
    $title_display    = $poll_title !== '' ? $poll_title : __( '— nie podano.', 'openvote' );
    $desc_display     = $poll_description !== '' ? $poll_description : __( '— nie podano.', 'openvote' );
    ?>
    <div class="openvote-poll-block openvote-poll-block--single" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
        <h3 class="openvote-poll__title"><?php echo esc_html__( 'Tytuł:', 'openvote' ); ?> <?php echo esc_html( $title_display ); ?></h3>
        <p class="openvote-poll__description"><?php echo esc_html__( 'Opis:', 'openvote' ); ?> <?php echo esc_html( $desc_display ); ?></p>

        <?php if ( $is_ended ) : ?>
            <div class="openvote-poll__results" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
                <p class="openvote-poll__status"><?php esc_html_e( 'Głosowanie zakończone. Ładowanie wyników…', 'openvote' ); ?></p>
            </div>

        <?php elseif ( $is_active && $has_voted ) : ?>
            <p class="openvote-poll__already-voted">
                <?php esc_html_e( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'openvote' ); ?>
            </p>
            <div class="openvote-poll__countdown">
                <?php esc_html_e( 'Głosowanie kończy się: ', 'openvote' ); ?>
                <span class="openvote-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>
            </div>

        <?php elseif ( $is_active && $eligible_error ) : ?>
            <div class="openvote-poll__questions-readonly">
                <?php foreach ( $poll->questions as $i => $question ) : ?>
                    <div class="openvote-poll__question-readonly">
                        <p class="openvote-poll__question-text">
                            <strong><?php echo esc_html( ( $i + 1 ) . '. ' . $question->body ); ?></strong>
                        </p>
                        <ul class="openvote-poll__answers-list">
                            <?php foreach ( $question->answers as $answer ) : ?>
                                <li><?php echo esc_html( $answer->body ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="openvote-poll__ineligible"><?php echo esc_html( $eligible_error ); ?></p>

        <?php elseif ( $is_active ) : ?>
            <form class="openvote-poll__form" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
                <div class="openvote-poll__countdown">
                    <?php esc_html_e( 'Pozostało: ', 'openvote' ); ?>
                    <span class="openvote-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>
                </div>
                <?php foreach ( $poll->questions as $i => $question ) : ?>
                    <fieldset class="openvote-poll__question">
                        <legend><?php echo esc_html( ( $i + 1 ) . '. ' . $question->body ); ?></legend>
                        <?php foreach ( $question->answers as $answer ) : ?>
                            <label class="openvote-poll__option">
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
                    $first = Openvote_Field_Map::get_user_value( $user, 'first_name' );
                    $last  = Openvote_Field_Map::get_user_value( $user, 'last_name' );
                    $city  = Openvote_Field_Map::get_user_value( $user, 'city' );
                    $email = Openvote_Field_Map::get_user_value( $user, 'email' );
                    $email_anon = $email !== '' ? Openvote_Vote::anonymize_email( $email ) : '…';
                    $name_part = trim( $first . ' ' . $last );
                    if ( $name_part === '' ) {
                        $name_part = '—';
                    }
                    if ( $city === '' ) {
                        $city = '—';
                    }
                    $open_label = sprintf(
                        /* translators: 1: full name, 2: city in parentheses, 3: masked email */
                        __( 'Głosuj jawnie - wyniki będą zawierać %1$s (%2$s) %3$s.', 'openvote' ),
                        $name_part,
                        $city,
                        $email_anon
                    );
                } else {
                    $open_label = __( 'Głosuj jawnie - wyniki będą zawierać imię nazwisko (miasto) email.', 'openvote' );
                }
                $nickname = ( $user && $user->exists() ) ? Openvote_Field_Map::get_user_value( $user, 'nickname' ) : '';
                $anon_label = ( $nickname !== '' )
                    ? sprintf( __( 'Głosuj Anonimowo - wyniki zawierają tylko nazwę: %s.', 'openvote' ), $nickname )
                    : __( 'Głosuj Anonimowo - wyniki zawierają tylko nazwę: —.', 'openvote' );
                ?>
                <fieldset class="openvote-poll__vote-mode" required>
                    <legend><?php esc_html_e( 'Sposób oddania głosu', 'openvote' ); ?></legend>
                    <label class="openvote-poll__option">
                        <input type="radio" name="openvote_vote_visibility" value="0" required>
                        <?php echo esc_html( $open_label ); ?>
                    </label>
                    <label class="openvote-poll__option">
                        <input type="radio" name="openvote_vote_visibility" value="1">
                        <?php echo esc_html( $anon_label ); ?>
                    </label>
                </fieldset>
                <button type="submit" class="openvote-poll__submit wp-element-button">
                    <?php esc_html_e( 'Oddaj głos', 'openvote' ); ?>
                </button>
                <div class="openvote-poll__message" aria-live="polite"></div>
            </form>

        <?php else : ?>
            <p class="openvote-poll__not-started">
                <?php
                printf(
                    /* translators: %s: start date */
                    esc_html__( 'Głosowanie rozpocznie się: %s', 'openvote' ),
                    esc_html( $poll->date_start )
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
