<?php
/**
 * Współdzielona funkcja renderująca pojedyncze głosowanie (formularz / wyniki / komunikat).
 * Używana przez blok openvote/poll oraz wirtualną stronę głosowania.
 *
 * @param object $poll    Poll with questions.
 * @param int    $user_id Current user ID.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Jeden format daty dla głosowań: „16.12.2026, 13:35” (data bez godziny: sama data).
 */
if ( ! function_exists( 'openvote_fmt_poll_date' ) ) {
    function openvote_fmt_poll_date( $raw ): string {
        $raw = trim( (string) $raw );
        if ( $raw === '' ) {
            return '—';
        }
        try {
            $dt = new DateTimeImmutable( $raw, wp_timezone() );
        } catch ( Exception $e ) {
            return $raw;
        }
        return wp_date( strlen( $raw ) === 10 ? 'j.m.Y' : 'j.m.Y, H:i', $dt->getTimestamp() );
    }
}

function openvote_render_single_poll( object $poll, int $user_id ): void {
    $poll_id      = (int) $poll->id;
    $is_guest     = $user_id <= 0;
    if ( $is_guest ) {
        // Gość widzi pytania; głosuje po zalogowaniu albo po weryfikacji danych (panel JS).
        openvote_identity_enqueue();
    }
    $is_active    = Openvote_Poll::is_active( $poll );
    $is_ended     = Openvote_Poll::is_ended( $poll );
    $has_voted    = $user_id > 0 && Openvote_Vote::has_voted( $poll_id, $user_id );
    $eligible_check = $user_id > 0 && $is_active && ! $has_voted ? Openvote_Eligibility::can_vote( $user_id, $poll_id ) : null;
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
    $title_display    = $poll_title !== '' ? $poll_title : __( 'Głosowanie bez tytułu', 'openvote' );
    ?>
    <div class="openvote-poll-block openvote-poll-block--single" data-poll-id="<?php echo esc_attr( $poll_id ); ?>" <?php echo $is_guest ? '' : ' data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"'; ?>>
        <?php if ( ! ( $is_active && $has_voted ) ) : ?>
        <h3 class="openvote-poll__title"><?php echo esc_html( $title_display ); ?></h3>
        <?php if ( $poll_description !== '' ) : ?>
        <p class="openvote-poll__description"><?php echo esc_html( $poll_description ); ?></p>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ( $is_ended ) : ?>
            <div class="openvote-poll__results" role="status" aria-live="polite" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
                <p class="openvote-poll__status"><?php esc_html_e( 'Głosowanie zakończone. Ładowanie wyników…', 'openvote' ); ?></p>
            </div>

        <?php elseif ( $is_active && $has_voted ) : ?>
            <details class="openvote-poll__voted-collapsed">
                <summary class="openvote-poll__voted-summary">
                    <span class="openvote-poll__voted-check" aria-hidden="true">✓</span>
                    <span class="openvote-poll__voted-title"><?php echo esc_html( $title_display ); ?></span>
                    <span class="openvote-poll__voted-badge"><?php esc_html_e( 'Głos oddany', 'openvote' ); ?></span>
                </summary>
                <?php if ( $poll_description !== '' ) : ?>
                <p class="openvote-poll__description"><?php echo esc_html( $poll_description ); ?></p>
                <?php endif; ?>
                <p class="openvote-poll__already-voted">
                    <?php esc_html_e( 'Już oddałeś głos w tym głosowaniu. Dziękujemy!', 'openvote' ); ?>
                </p>
                <div class="openvote-poll__countdown">
                    <?php esc_html_e( 'Głosowanie kończy się: ', 'openvote' ); ?>
                    <?php echo esc_html( wp_date( 'j.m.Y, H:i', $end_ts ) ); ?>
                    (<span class="openvote-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>)
                </div>
            </details>

        <?php elseif ( $is_active && $eligible_error ) : ?>
            <div class="openvote-poll__questions-readonly">
                <?php
                $abstain_label_readonly = __( 'Wstrzymuję się', 'openvote' );
                foreach ( $poll->questions as $i => $question ) : ?>
                    <div class="openvote-poll__question-readonly">
                        <p class="openvote-poll__question-text">
                            <strong><?php echo esc_html( ( $i + 1 ) . '. ' . $question->body ); ?></strong>
                        </p>
                        <ul class="openvote-poll__answers-list">
                            <?php foreach ( $question->answers as $answer ) :
                                $body_trim = trim( (string) $answer->body );
                                if ( ! empty( $answer->is_abstain ) ) {
                                    $display_text = $abstain_label_readonly;
                                } elseif ( $body_trim === $abstain_label_readonly ) {
                                    continue;
                                } else {
                                    $display_text = $answer->body;
                                }
                                ?>
                                <li><?php echo esc_html( $display_text ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="openvote-poll__ineligible"><?php echo esc_html( $eligible_error ); ?></p>

        <?php elseif ( $is_active ) : ?>
            <?php if ( $is_guest ) { openvote_identity_render_slot( 'poll', $poll_id ); } ?>
            <form class="openvote-poll__form<?php echo $is_guest ? ' openvote-poll__form--locked' : ''; ?>" data-poll-id="<?php echo esc_attr( $poll_id ); ?>"<?php echo $is_guest ? ' data-identity-required="1"' : ''; ?>>
                <div class="openvote-poll__countdown">
                    <?php esc_html_e( 'Do końca głosowania: ', 'openvote' ); ?>
                    <span class="openvote-countdown" data-end="<?php echo esc_attr( gmdate( 'c', $end_ts ) ); ?>"></span>
                </div>
                <?php
                $abstain_label_form = __( 'Wstrzymuję się', 'openvote' );
                foreach ( $poll->questions as $i => $question ) : ?>
                    <fieldset class="openvote-poll__question">
                        <legend><?php echo esc_html( ( $i + 1 ) . '. ' . $question->body ); ?></legend>
                        <?php foreach ( $question->answers as $answer ) :
                            $body_trim = trim( (string) $answer->body );
                            if ( ! empty( $answer->is_abstain ) ) {
                                $display_text = $abstain_label_form;
                            } elseif ( $body_trim === $abstain_label_form ) {
                                continue;
                            } else {
                                $display_text = $answer->body;
                            }
                            ?>
                            <label class="openvote-poll__option">
                                <input type="radio"
                                       name="question_<?php echo esc_attr( $question->id ); ?>"
                                       value="<?php echo esc_attr( $answer->id ); ?>"
                                       required<?php echo $is_guest ? ' disabled' : ''; ?>>
                                <span class="openvote-poll__option-text"><?php echo esc_html( $display_text ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
                <?php
                $user = $is_guest ? false : get_userdata( $user_id );
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
                        __( 'Jawnie — w wynikach pojawi się: %1$s (%2$s), %3$s.', 'openvote' ),
                        $name_part,
                        $city,
                        $email_anon
                    );
                } else {
                    $open_label = __( 'Jawnie — w wynikach pojawi się Twoje imię, nazwisko, miasto i zamaskowany e-mail.', 'openvote' );
                }
                $nickname = ( $user && $user->exists() ) ? Openvote_Field_Map::get_user_value( $user, 'nickname' ) : '';
                $anon_label = ( $nickname !== '' )
                    ? sprintf( __( 'Anonimowo — w wynikach pojawi się tylko pseudonim: %s.', 'openvote' ), $nickname )
                    : __( 'Anonimowo — w wynikach pojawi się tylko Twój pseudonim, bez imienia i nazwiska.', 'openvote' );
                ?>
                <fieldset class="openvote-poll__vote-mode">
                    <legend><?php esc_html_e( 'Sposób oddania głosu', 'openvote' ); ?></legend>
                    <label class="openvote-poll__option">
                        <input type="radio" name="openvote_vote_visibility" value="0" required<?php echo $is_guest ? ' disabled' : ''; ?>>
                        <span class="openvote-poll__option-text" data-ov-label="open" data-ov-default="<?php echo esc_attr( $open_label ); ?>"><?php echo esc_html( $open_label ); ?></span>
                    </label>
                    <label class="openvote-poll__option">
                        <input type="radio" name="openvote_vote_visibility" value="1"<?php echo $is_guest ? ' disabled' : ''; ?>>
                        <span class="openvote-poll__option-text" data-ov-label="anon" data-ov-default="<?php echo esc_attr( $anon_label ); ?>"><?php echo esc_html( $anon_label ); ?></span>
                    </label>
                </fieldset>
                <p class="openvote-poll__hint" id="openvote-hint-<?php echo esc_attr( $poll_id ); ?>" aria-live="polite"></p>
                <button type="submit" class="openvote-poll__submit wp-element-button" aria-describedby="openvote-hint-<?php echo esc_attr( $poll_id ); ?>"<?php echo $is_guest ? ' disabled' : ''; ?>>
                    <?php esc_html_e( 'Oddaj głos', 'openvote' ); ?>
                </button>
                <div class="openvote-poll__message" role="alert" aria-live="polite" tabindex="-1"></div>
            </form>

        <?php else : ?>
            <p class="openvote-poll__not-started">
                <?php
                printf(
                    /* translators: %s: start date */
                    esc_html__( 'Głosowanie rozpocznie się: %s', 'openvote' ),
                    esc_html( openvote_fmt_poll_date( $poll->date_start ) )
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
