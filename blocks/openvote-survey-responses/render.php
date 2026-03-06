<?php
/**
 * Server-side render dla bloku openvote/survey-responses.
 * Wyświetla zgłoszenia ankiet ze statusem „nie spam”.
 */
defined( 'ABSPATH' ) || exit;

wp_enqueue_style(
    'openvote-public',
    OPENVOTE_PLUGIN_URL . 'public/css/openvote-public.css',
    [],
    OPENVOTE_VERSION
);

$per_page = isset( $attributes['per_page'] ) ? max( 1, min( 100, (int) $attributes['per_page'] ) ) : 20;
$survey_id = isset( $attributes['survey_id'] ) ? absint( $attributes['survey_id'] ) : 0;
$page = 1;

$responses = Openvote_Survey::get_responses_not_spam( $survey_id, $page, $per_page );
$pending   = Openvote_Survey::get_responses_pending( $survey_id, $page, $per_page );
$questions_cache = [];

// W nagłówku karty tylko Imię i Nazwisko. W odpowiedziach — wszystkie wypełnione dane,
// z wyjątkiem pól oznaczonych w ankiecie jako informacja wrażliwa (checkbox przy pytaniu).
?>
<div class="openvote-survey-responses-block">
    <h2 class="openvote-survey-responses-block__title"><?php esc_html_e( 'Zgłoszenia (zatwierdzone)', 'openvote' ); ?></h2>
    <?php if ( empty( $responses ) ) : ?>
        <p class="openvote-survey-responses-block__empty"><?php esc_html_e( 'Brak zgłoszeń zatwierdzonych.', 'openvote' ); ?></p>
    <?php else : ?>
        <div class="openvote-survey-responses-block__list">
            <?php foreach ( $responses as $resp ) :
                if ( ! isset( $questions_cache[ (int) $resp->survey_id ] ) ) {
                    $questions_cache[ (int) $resp->survey_id ] = Openvote_Survey::get_questions( (int) $resp->survey_id );
                }
                $questions = $questions_cache[ (int) $resp->survey_id ];
                ?>
                <div class="openvote-survey-responses-block__card">
                    <div class="openvote-survey-responses-block__card-header">
                        <span class="openvote-survey-responses-block__survey-title"><?php echo esc_html( $resp->survey_title ); ?></span>
                        <strong class="openvote-survey-responses-block__user-name"><?php echo esc_html( trim( $resp->user_first_name . ' ' . $resp->user_last_name ) ); ?></strong>
                    </div>
                    <dl class="openvote-survey-responses-block__answers">
                        <?php foreach ( $questions as $q ) :
                            $is_sensitive = trim( (string) ( $q->profile_field ?? '' ) ) !== '';
                            if ( $is_sensitive ) {
                                continue;
                            }
                            $answer = $resp->answers[ (int) $q->id ] ?? '';
                            $is_url = ( $q->field_type ?? '' ) === 'url';
                            if ( $answer !== '' && $is_url ) {
                                $href = ( strpos( $answer, '://' ) !== false ) ? $answer : 'https://' . $answer;
                                $cell = '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $answer ) . '</a>';
                            } elseif ( $answer !== '' ) {
                                $cell = esc_html( $answer );
                            } else {
                                $cell = '—';
                            }
                            ?>
                            <div class="openvote-survey-responses-block__row">
                                <dt class="openvote-survey-responses-block__q"><?php echo esc_html( $q->body ); ?></dt>
                                <dd class="openvote-survey-responses-block__a"><?php echo $cell; ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="openvote-survey-responses-block__title openvote-survey-responses-block__title--pending"><?php esc_html_e( 'Zgłoszenia nie zatwierdzone', 'openvote' ); ?></h2>
    <?php if ( empty( $pending ) ) : ?>
        <p class="openvote-survey-responses-block__empty"><?php esc_html_e( 'Brak zgłoszeń oczekujących na zatwierdzenie.', 'openvote' ); ?></p>
    <?php else : ?>
        <div class="openvote-survey-responses-block__list openvote-survey-responses-block__list--pending">
            <?php foreach ( $pending as $resp ) :
                $submitted_date = ! empty( $resp->submitted_at ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $resp->submitted_at ) ) : '—';
                ?>
                <div class="openvote-survey-responses-block__card openvote-survey-responses-block__card--pending">
                    <div class="openvote-survey-responses-block__pending-row">
                        <span class="openvote-survey-responses-block__pending-label"><?php esc_html_e( 'Tytuł', 'openvote' ); ?>:</span>
                        <strong class="openvote-survey-responses-block__pending-title"><?php echo esc_html( $resp->survey_title ?? '—' ); ?></strong>
                    </div>
                    <?php if ( ! empty( $resp->survey_description ) ) : ?>
                    <div class="openvote-survey-responses-block__pending-row">
                        <span class="openvote-survey-responses-block__pending-label"><?php esc_html_e( 'Opis', 'openvote' ); ?>:</span>
                        <span class="openvote-survey-responses-block__pending-desc"><?php echo esc_html( $resp->survey_description ); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="openvote-survey-responses-block__pending-row openvote-survey-responses-block__pending-row--right">
                        <span class="openvote-survey-responses-block__pending-label"><?php esc_html_e( 'Data zgłoszenia', 'openvote' ); ?>:</span>
                        <span><?php echo esc_html( $submitted_date ); ?></span>
                    </div>
                    <div class="openvote-survey-responses-block__pending-row openvote-survey-responses-block__pending-row--right">
                        <span class="openvote-survey-responses-block__pending-label"><?php esc_html_e( 'Nickname', 'openvote' ); ?>:</span>
                        <span><?php echo esc_html( trim( $resp->user_nickname ?? '' ) !== '' ? $resp->user_nickname : '—' ); ?></span>
                    </div>
                    <p class="openvote-survey-responses-block__pending-notice"><?php esc_html_e( 'Oczekuje na zatwierdzenie.', 'openvote' ); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
