<?php
/**
 * Server-side render dla bloku evoting/survey-responses.
 * Wyświetla zgłoszenia ankiet ze statusem „nie spam”.
 */
defined( 'ABSPATH' ) || exit;

wp_enqueue_style(
    'evoting-public',
    EVOTING_PLUGIN_URL . 'public/css/evoting-public.css',
    [],
    EVOTING_VERSION
);

$per_page = isset( $attributes['per_page'] ) ? max( 1, min( 100, (int) $attributes['per_page'] ) ) : 20;
$survey_id = isset( $attributes['survey_id'] ) ? absint( $attributes['survey_id'] ) : 0;
$page = 1;

$responses = Evoting_Survey::get_responses_not_spam( $survey_id, $page, $per_page );
$questions_cache = [];

// W nagłówku karty tylko Imię i Nazwisko. W odpowiedziach — wszystkie wypełnione dane,
// z wyjątkiem pól oznaczonych w konfiguracji jako wrażliwe (E-mail, Miasto, Telefon, PESEL, Dowód, Ulica, Kod pocztowy, Miejscowość).
?>
<div class="evoting-survey-responses-block">
    <h2 class="evoting-survey-responses-block__title"><?php esc_html_e( 'Zgłoszenia ankiet', 'evoting' ); ?></h2>
    <?php if ( empty( $responses ) ) : ?>
        <p class="evoting-survey-responses-block__empty"><?php esc_html_e( 'Brak zgłoszeń oznaczonych jako „Nie spam”.', 'evoting' ); ?></p>
    <?php else : ?>
        <div class="evoting-survey-responses-block__list">
            <?php foreach ( $responses as $resp ) :
                if ( ! isset( $questions_cache[ (int) $resp->survey_id ] ) ) {
                    $questions_cache[ (int) $resp->survey_id ] = Evoting_Survey::get_questions( (int) $resp->survey_id );
                }
                $questions = $questions_cache[ (int) $resp->survey_id ];
                ?>
                <div class="evoting-survey-responses-block__card">
                    <div class="evoting-survey-responses-block__card-header">
                        <span class="evoting-survey-responses-block__survey-title"><?php echo esc_html( $resp->survey_title ); ?></span>
                        <strong class="evoting-survey-responses-block__user-name"><?php echo esc_html( trim( $resp->user_first_name . ' ' . $resp->user_last_name ) ); ?></strong>
                    </div>
                    <dl class="evoting-survey-responses-block__answers">
                        <?php foreach ( $questions as $q ) :
                            $answer = $resp->answers[ (int) $q->id ] ?? '';
                            $profile_field = isset( $q->profile_field ) ? trim( (string) $q->profile_field ) : '';
                            $is_sensitive = $profile_field !== '' && Evoting_Field_Map::is_sensitive_for_public( $profile_field );
                            ?>
                            <div class="evoting-survey-responses-block__row">
                                <dt class="evoting-survey-responses-block__q"><?php echo esc_html( $q->body ); ?></dt>
                                <dd class="evoting-survey-responses-block__a"><?php echo $is_sensitive ? '—' : ( $answer !== '' ? esc_html( $answer ) : '—' ); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
