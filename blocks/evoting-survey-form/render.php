<?php
/**
 * Server-side render dla bloku evoting/survey-form.
 * Wywoływany przez WordPress przy każdym wyświetleniu bloku na froncie.
 *
 * Zmienne dostępne z WordPress: $attributes, $content, $block
 */
defined( 'ABSPATH' ) || exit;

// Załaduj style i JS ankiety.
wp_enqueue_style(
    'evoting-public',
    EVOTING_PLUGIN_URL . 'public/css/evoting-public.css',
    [],
    EVOTING_VERSION
);

wp_enqueue_script(
    'evoting-survey-public',
    EVOTING_PLUGIN_URL . 'public/js/survey-public.js',
    [],
    EVOTING_VERSION,
    true
);

wp_enqueue_script(
    'evoting-profile-complete',
    EVOTING_PLUGIN_URL . 'public/js/profile-complete.js',
    [],
    EVOTING_VERSION,
    true
);

wp_localize_script( 'evoting-survey-public', 'evotingSurveyPublic', [
    'restUrl'  => esc_url_raw( rest_url( 'evoting/v1' ) ),
    'nonce'    => wp_create_nonce( 'wp_rest' ),
    'loggedIn' => is_user_logged_in() ? 1 : 0,
    'loginUrl' => esc_url( wp_login_url( Evoting_Survey_Page::get_url() ) ),
    'i18n'     => [
        'saving'         => __( 'Zapisywanie…', 'evoting' ),
        'savedDraft'     => __( 'Szkic zapisany.', 'evoting' ),
        'savedReady'     => __( 'Odpowiedź zapisana jako Gotowa. Dziękujemy!', 'evoting' ),
        'error'          => __( 'Wystąpił błąd. Spróbuj ponownie.', 'evoting' ),
        'profileMissing' => __( 'Uzupełnij profil, aby wypełnić ankietę.', 'evoting' ),
    ],
] );

$user_id   = get_current_user_id();
$logged_in = is_user_logged_in();
$surveys   = Evoting_Survey::get_all( [ 'status' => 'open', 'orderby' => 'date_start', 'order' => 'ASC' ] );
$now       = current_time( 'mysql' );

$active_surveys = array_filter( $surveys, fn( $s ) => $s->date_start <= $now && $s->date_end >= $now );

?>
<div class="evoting-survey-wrap">

    <?php if ( ! $logged_in ) : ?>
        <div class="evoting-survey-login-notice">
            <p>
                <?php esc_html_e( 'Zaloguj się, aby wypełnić ankietę.', 'evoting' ); ?>
                <a href="<?php echo esc_url( wp_login_url( Evoting_Survey_Page::get_url() ) ); ?>">
                    <?php esc_html_e( 'Zaloguj się', 'evoting' ); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $active_surveys ) ) : ?>
        <div class="evoting-survey-empty">
            <p><?php esc_html_e( 'Aktualnie nie ma żadnych aktywnych ankiet.', 'evoting' ); ?></p>
        </div>
    <?php else : ?>

        <?php foreach ( $active_surveys as $survey ) :
            $survey_id = (int) $survey->id;
            $questions = Evoting_Survey::get_questions( $survey_id );
            $my_response = $logged_in ? Evoting_Survey::get_user_response( $survey_id, $user_id ) : null;

            // Sprawdź profil użytkownika.
            $profile_ok = true;
            $missing_fields = [];
            if ( $logged_in ) {
                $profile_check  = Evoting_Survey::check_user_profile( $user_id );
                $profile_ok     = $profile_check['ok'];
                $missing_fields = $profile_check['missing'];
            }
            ?>

            <div class="evoting-survey-card" id="survey-card-<?php echo esc_attr( $survey_id ); ?>">

                <div class="evoting-survey-card__header">
                    <h3 class="evoting-survey-card__title"><?php echo esc_html( $survey->title ); ?></h3>
                    <?php if ( $survey->description ) : ?>
                        <p class="evoting-survey-card__desc"><?php echo esc_html( $survey->description ); ?></p>
                    <?php endif; ?>
                    <p class="evoting-survey-card__meta">
                        <?php printf(
                            esc_html__( 'Ankieta aktywna do: %s', 'evoting' ),
                            esc_html( wp_date( 'd.m.Y H:i', strtotime( $survey->date_end ) ) )
                        ); ?>
                    </p>
                </div>

                <?php if ( $logged_in && ! $profile_ok ) : ?>
                    <?php
                    $context = 'survey';
                    $nonce   = wp_create_nonce( 'wp_rest' );
                    include EVOTING_PLUGIN_DIR . 'public/views/partials/profile-complete.php';
                    ?>
                <?php elseif ( $logged_in ) : ?>

                    <?php
                    // Status banner — jeśli użytkownik ma już odpowiedź.
                    if ( $my_response ) :
                        $banner_class = 'ready' === $my_response->response_status
                            ? 'evoting-survey-notice--success'
                            : 'evoting-survey-notice--draft';
                        $banner_text  = 'ready' === $my_response->response_status
                            ? __( 'Twoja odpowiedź: Gotowa (możesz ją edytować do zakończenia ankiety).', 'evoting' )
                            : __( 'Twoja odpowiedź: Szkic — tylko Ty ją widzisz.', 'evoting' );
                        ?>
                        <div class="evoting-survey-notice <?php echo esc_attr( $banner_class ); ?>">
                            <p><?php echo esc_html( $banner_text ); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Dane z profilu użytkownika (tylko do odczytu) + checkbox przy wymaganych.
                    $user            = get_userdata( $user_id );
                    $survey_required = Evoting_Field_Map::get_survey_required_fields();
                    if ( $user && $user->exists() ) :
                    ?>
                    <div class="evoting-survey-profile">
                        <h4 class="evoting-survey-profile__title"><?php esc_html_e( 'Dane z Twojego profilu', 'evoting' ); ?></h4>
                        <p class="evoting-survey-profile__desc"><?php esc_html_e( 'Poniższe dane są zapisane w systemie i nie można ich tutaj edytować.', 'evoting' ); ?></p>
                        <dl class="evoting-survey-profile__list">
                            <?php foreach ( $survey_required as $logical => $label ) :
                                $value   = Evoting_Field_Map::get_user_value( $user, $logical );
                                $display = trim( $value ) !== '' ? $value : '—';
                            ?>
                            <div class="evoting-survey-profile__row">
                                <dt class="evoting-survey-profile__label">
                                    <span class="evoting-survey-profile__required" title="<?php esc_attr_e( 'Wymagane do ankiety', 'evoting' ); ?>">
                                        <input type="checkbox" checked disabled aria-hidden="true">
                                        <?php echo esc_html( $label ); ?>
                                    </span>
                                </dt>
                                <dd class="evoting-survey-profile__value"><?php echo esc_html( $display ); ?></dd>
                            </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                    <?php endif; ?>

                    <!-- Formularz ankiety -->
                    <form class="evoting-survey-form"
                          data-survey-id="<?php echo esc_attr( $survey_id ); ?>"
                          novalidate>

                        <p class="evoting-survey-intro">
                            <em><?php esc_html_e( 'Proszę o uzupełnienie.', 'evoting' ); ?></em>
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
                            <div class="evoting-survey-field">
                                <label for="sq-<?php echo esc_attr( $survey_id ); ?>-<?php echo esc_attr( $qid ); ?>"
                                       class="evoting-survey-field__label">
                                    <?php echo esc_html( $q->body ); ?>
                                </label>

                                <?php if ( $tag === 'textarea' ) : ?>
                                    <textarea
                                        id="sq-<?php echo esc_attr( $survey_id ); ?>-<?php echo esc_attr( $qid ); ?>"
                                        name="answers[<?php echo esc_attr( $qid ); ?>]"
                                        class="evoting-survey-field__input"
                                        maxlength="<?php echo esc_attr( $q->max_chars ); ?>"
                                        rows="4"><?php echo esc_textarea( $saved_value ); ?></textarea>
                                <?php else : ?>
                                    <input
                                        type="<?php echo esc_attr( $input_type ); ?>"
                                        id="sq-<?php echo esc_attr( $survey_id ); ?>-<?php echo esc_attr( $qid ); ?>"
                                        name="answers[<?php echo esc_attr( $qid ); ?>]"
                                        class="evoting-survey-field__input"
                                        maxlength="<?php echo esc_attr( $q->max_chars ); ?>"
                                        value="<?php echo esc_attr( $saved_value ); ?>">
                                <?php endif; ?>
                                <span class="evoting-survey-field__limit">
                                    <?php printf( esc_html__( 'Max %d znaków', 'evoting' ), esc_html( $q->max_chars ) ); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>

                        <div class="evoting-survey-form__actions">
                            <button type="submit"
                                    class="evoting-survey-btn evoting-survey-btn--draft"
                                    data-status="draft">
                                <?php esc_html_e( 'Zapisz jako szkic', 'evoting' ); ?>
                            </button>
                            <button type="submit"
                                    class="evoting-survey-btn evoting-survey-btn--ready"
                                    data-status="ready">
                                <?php esc_html_e( 'Wyślij odpowiedź (Gotowa)', 'evoting' ); ?>
                            </button>
                        </div>

                        <div class="evoting-survey-form__message" style="display:none;"></div>

                    </form>

                <?php endif; // logged_in + profile_ok ?>

            </div><!-- .evoting-survey-card -->

        <?php endforeach; ?>

    <?php endif; // !empty active_surveys ?>

</div><!-- .evoting-survey-wrap -->
