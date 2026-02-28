<?php
/**
 * Server-side render dla bloku openvote/survey-form.
 * Wywoływany przez WordPress przy każdym wyświetleniu bloku na froncie.
 *
 * Zmienne dostępne z WordPress: $attributes, $content, $block
 */
defined( 'ABSPATH' ) || exit;

// Załaduj style i JS ankiety.
wp_enqueue_style(
    'openvote-public',
    OPENVOTE_PLUGIN_URL . 'public/css/openvote-public.css',
    [],
    OPENVOTE_VERSION
);

wp_enqueue_script(
    'openvote-survey-public',
    OPENVOTE_PLUGIN_URL . 'public/js/survey-public.js',
    [],
    OPENVOTE_VERSION,
    true
);

wp_enqueue_script(
    'openvote-profile-complete',
    OPENVOTE_PLUGIN_URL . 'public/js/profile-complete.js',
    [],
    OPENVOTE_VERSION,
    true
);

wp_localize_script( 'openvote-survey-public', 'openvoteSurveyPublic', [
    'restUrl'  => esc_url_raw( rest_url( 'openvote/v1' ) ),
    'nonce'    => wp_create_nonce( 'wp_rest' ),
    'loggedIn' => is_user_logged_in() ? 1 : 0,
    'loginUrl' => esc_url( wp_login_url( Openvote_Survey_Page::get_url() ) ),
    'i18n'     => [
        'saving'         => __( 'Zapisywanie…', 'openvote' ),
        'savedDraft'     => __( 'Szkic zapisany.', 'openvote' ),
        'savedReady'     => __( 'Odpowiedź zapisana jako Gotowa. Dziękujemy!', 'openvote' ),
        'error'          => __( 'Wystąpił błąd. Spróbuj ponownie.', 'openvote' ),
        'profileMissing' => __( 'Uzupełnij profil, aby wypełnić ankietę.', 'openvote' ),
    ],
] );

$user_id   = get_current_user_id();
$logged_in = is_user_logged_in();
$surveys   = Openvote_Survey::get_all( [ 'status' => 'open', 'orderby' => 'date_start', 'order' => 'ASC' ] );
$now       = current_time( 'mysql' );

$active_surveys = array_filter( $surveys, fn( $s ) => $s->date_start <= $now && $s->date_end >= $now );

?>
<div class="openvote-survey-wrap">

    <?php if ( ! $logged_in ) : ?>
        <div class="openvote-survey-login-notice">
            <p>
                <?php esc_html_e( 'Zaloguj się, aby wypełnić ankietę.', 'openvote' ); ?>
                <a href="<?php echo esc_url( wp_login_url( Openvote_Survey_Page::get_url() ) ); ?>">
                    <?php esc_html_e( 'Zaloguj się', 'openvote' ); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $active_surveys ) ) : ?>
        <div class="openvote-survey-empty">
            <p><?php esc_html_e( 'Aktualnie nie ma żadnych aktywnych ankiet.', 'openvote' ); ?></p>
        </div>
    <?php else : ?>

        <?php foreach ( $active_surveys as $survey ) :
            $survey_id = (int) $survey->id;
            $questions = Openvote_Survey::get_questions( $survey_id );
            $my_response = $logged_in ? Openvote_Survey::get_user_response( $survey_id, $user_id ) : null;

            // Sprawdź profil użytkownika.
            $profile_ok = true;
            $missing_fields = [];
            if ( $logged_in ) {
                $profile_check  = Openvote_Survey::check_user_profile( $user_id );
                $profile_ok     = $profile_check['ok'];
                $missing_fields = $profile_check['missing'];
            }
            ?>

            <div class="openvote-survey-card" id="survey-card-<?php echo esc_attr( $survey_id ); ?>">

                <div class="openvote-survey-card__header">
                    <h3 class="openvote-survey-card__title"><?php echo esc_html( $survey->title ); ?></h3>
                    <?php if ( $survey->description ) : ?>
                        <p class="openvote-survey-card__desc"><?php echo esc_html( $survey->description ); ?></p>
                    <?php endif; ?>
                    <p class="openvote-survey-card__meta">
                        <?php printf(
                            esc_html__( 'Ankieta aktywna do: %s', 'openvote' ),
                            esc_html( wp_date( 'd.m.Y H:i', strtotime( $survey->date_end ) ) )
                        ); ?>
                    </p>
                </div>

                <?php if ( $logged_in && ! $profile_ok ) : ?>
                    <?php
                    $context = 'survey';
                    $nonce   = wp_create_nonce( 'wp_rest' );
                    include OPENVOTE_PLUGIN_DIR . 'public/views/partials/profile-complete.php';
                    ?>
                <?php elseif ( $logged_in ) : ?>

                    <?php
                    // Status banner — jeśli użytkownik ma już odpowiedź.
                    if ( $my_response ) :
                        $banner_class = 'ready' === $my_response->response_status
                            ? 'openvote-survey-notice--success'
                            : 'openvote-survey-notice--draft';
                        $banner_text  = 'ready' === $my_response->response_status
                            ? __( 'Twoja odpowiedź: Gotowa (możesz ją edytować do zakończenia ankiety).', 'openvote' )
                            : __( 'Twoja odpowiedź: Szkic — tylko Ty ją widzisz.', 'openvote' );
                        ?>
                        <div class="openvote-survey-notice <?php echo esc_attr( $banner_class ); ?>">
                            <p><?php echo esc_html( $banner_text ); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Dane z profilu użytkownika (tylko do odczytu) + checkbox przy wymaganych.
                    $user            = get_userdata( $user_id );
                    $survey_required = Openvote_Field_Map::get_survey_required_fields();
                    if ( $user && $user->exists() ) :
                    ?>
                    <div class="openvote-survey-profile">
                        <h4 class="openvote-survey-profile__title"><?php esc_html_e( 'Dane z Twojego profilu', 'openvote' ); ?></h4>
                        <p class="openvote-survey-profile__desc"><?php esc_html_e( 'Poniższe dane są zapisane w systemie i nie można ich tutaj edytować.', 'openvote' ); ?></p>
                        <dl class="openvote-survey-profile__list">
                            <?php foreach ( $survey_required as $logical => $label ) :
                                $value   = Openvote_Field_Map::get_user_value( $user, $logical );
                                $display = trim( $value ) !== '' ? $value : '—';
                            ?>
                            <div class="openvote-survey-profile__row">
                                <dt class="openvote-survey-profile__label">
                                    <span class="openvote-survey-profile__required" title="<?php esc_attr_e( 'Wymagane do ankiety', 'openvote' ); ?>">
                                        <input type="checkbox" checked disabled aria-hidden="true">
                                        <?php echo esc_html( $label ); ?>
                                    </span>
                                </dt>
                                <dd class="openvote-survey-profile__value"><?php echo esc_html( $display ); ?></dd>
                            </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                    <?php endif; ?>

                    <!-- Formularz ankiety -->
                    <form class="openvote-survey-form"
                          data-survey-id="<?php echo esc_attr( $survey_id ); ?>"
                          novalidate>

                        <p class="openvote-survey-intro">
                            <em><?php esc_html_e( 'Proszę o uzupełnienie.', 'openvote' ); ?></em>
                        </p>

                        <?php foreach ( $questions as $q ) :
                            $qid         = (int) $q->id;
                            $saved_value = $my_response ? ( $my_response->answers[ $qid ] ?? '' ) : '';
                            $input_type  = 'text';
                            $tag         = 'input';
                            if ( $q->field_type === 'text_long' ) {
                                $tag = 'textarea';
                            } elseif ( $q->field_type === 'url' ) {
                                $input_type = 'url';
                            } elseif ( $q->field_type === 'numeric' ) {
                                $input_type = 'tel';
                            } elseif ( $q->field_type === 'email' ) {
                                $input_type = 'email';
                            }
                            ?>
                            <div class="openvote-survey-field">
                                <label for="sq-<?php echo esc_attr( $survey_id ); ?>-<?php echo esc_attr( $qid ); ?>"
                                       class="openvote-survey-field__label">
                                    <?php echo esc_html( $q->body ); ?>
                                </label>

                                <?php if ( $tag === 'textarea' ) : ?>
                                    <textarea
                                        id="sq-<?php echo esc_attr( $survey_id ); ?>-<?php echo esc_attr( $qid ); ?>"
                                        name="answers[<?php echo esc_attr( $qid ); ?>]"
                                        class="openvote-survey-field__input"
                                        maxlength="<?php echo esc_attr( $q->max_chars ); ?>"
                                        rows="4"><?php echo esc_textarea( $saved_value ); ?></textarea>
                                <?php else : ?>
                                    <input
                                        type="<?php echo esc_attr( $input_type ); ?>"
                                        id="sq-<?php echo esc_attr( $survey_id ); ?>-<?php echo esc_attr( $qid ); ?>"
                                        name="answers[<?php echo esc_attr( $qid ); ?>]"
                                        class="openvote-survey-field__input"
                                        maxlength="<?php echo esc_attr( $q->max_chars ); ?>"
                                        value="<?php echo esc_attr( $saved_value ); ?>">
                                <?php endif; ?>
                                <span class="openvote-survey-field__limit">
                                    <?php printf( esc_html__( 'Max %d znaków', 'openvote' ), esc_html( $q->max_chars ) ); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>

                        <div class="openvote-survey-form__actions">
                            <button type="submit"
                                    class="openvote-survey-btn openvote-survey-btn--draft"
                                    data-status="draft">
                                <?php esc_html_e( 'Zapisz jako szkic', 'openvote' ); ?>
                            </button>
                            <button type="submit"
                                    class="openvote-survey-btn openvote-survey-btn--ready"
                                    data-status="ready">
                                <?php esc_html_e( 'Wyślij odpowiedź (Gotowa)', 'openvote' ); ?>
                            </button>
                        </div>

                        <div class="openvote-survey-form__message" style="display:none;"></div>

                    </form>

                <?php endif; // logged_in + profile_ok ?>

            </div><!-- .openvote-survey-card -->

        <?php endforeach; ?>

    <?php endif; // !empty active_surveys ?>

</div><!-- .openvote-survey-wrap -->
