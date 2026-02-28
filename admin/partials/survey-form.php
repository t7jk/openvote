<?php
defined( 'ABSPATH' ) || exit;

$is_read_only = ! empty( $is_read_only );
$is_edit      = isset( $survey ) && $survey;
$title        = $is_edit ? $survey->title : '';
$desc         = $is_edit ? $survey->description : '';
$status       = $is_edit ? $survey->status : 'draft';
$questions    = $is_edit ? Evoting_Survey::get_questions( (int) $survey->id ) : [];

// Data rozpoczęcia: zawsze „teraz" (wyszarzona).
$now_ts              = current_time( 'timestamp' );
$date_start_display  = wp_date( 'Y-m-d\TH:i', $now_ts );

// Czas trwania: dropdown.
$duration_options = [
    '1h'  => __( '1h', 'evoting' ),
    '1d'  => __( '1 dzień', 'evoting' ),
    '2d'  => __( '2 dni', 'evoting' ),
    '3d'  => __( '3 dni', 'evoting' ),
    '7d'  => __( '7 dni', 'evoting' ),
    '14d' => __( '14 dni', 'evoting' ),
    '21d' => __( '21 dni', 'evoting' ),
    '30d' => __( '30 dni', 'evoting' ),
];

$selected_duration = '7d';
if ( $is_edit && ! empty( $survey->date_start ) && ! empty( $survey->date_end ) ) {
    $start_ts = strtotime( $survey->date_start );
    $end_ts   = strtotime( $survey->date_end );
    if ( $start_ts && $end_ts && $end_ts > $start_ts ) {
        $diff  = $end_ts - $start_ts;
        $hours = $diff / 3600;
        $days  = $diff / 86400;
        if ( $hours <= 1.1 )       $selected_duration = '1h';
        elseif ( $days <= 1.1 )    $selected_duration = '1d';
        elseif ( $days <= 2.1 )    $selected_duration = '2d';
        elseif ( $days <= 3.1 )    $selected_duration = '3d';
        elseif ( $days <= 7.1 )    $selected_duration = '7d';
        elseif ( $days <= 14.1 )   $selected_duration = '14d';
        elseif ( $days <= 21.1 )   $selected_duration = '21d';
        else                       $selected_duration = '30d';
    }
}

$field_type_labels = [
    'text_short' => __( 'Krótki tekst do 100 znaków', 'evoting' ),
    'text_long'  => __( 'Długi tekst do 2000 znaków', 'evoting' ),
    'numeric'    => __( 'Numer do 30 cyfr', 'evoting' ),
    'url'        => __( 'Adres URL', 'evoting' ),
    'email'      => __( 'E-mail', 'evoting' ),
];

$profile_field_options = [ '' => __( '— brak (pole dowolne)', 'evoting' ) ] + Evoting_Field_Map::LABELS;

$page_title = $is_read_only
    ? __( 'Podgląd ankiety', 'evoting' )
    : ( $is_edit ? __( 'Edytuj ankietę', 'evoting' ) : __( 'Nowa ankieta', 'evoting' ) );
?>
<div class="wrap">
    <h1><?php echo esc_html( $page_title ); ?></h1>

    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting-surveys' ) ); ?>" class="page-title-action">
        ← <?php esc_html_e( 'Powrót do listy', 'evoting' ); ?>
    </a>
    <hr class="wp-header-end" style="margin-top:8px;">

    <?php if ( isset( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Utworzono kopię ankiety. Możesz ją teraz edytować.', 'evoting' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" id="evoting-survey-form">
        <?php wp_nonce_field( 'evoting_save_survey', 'evoting_survey_nonce' ); ?>
        <?php if ( $is_edit ) : ?>
            <input type="hidden" name="survey_id" value="<?php echo esc_attr( $survey->id ); ?>">
        <?php endif; ?>

        <table class="form-table" style="max-width:700px;">
            <tr>
                <th scope="row"><label for="survey_title"><?php esc_html_e( 'Tytuł', 'evoting' ); ?> <span style="color:red">*</span></label></th>
                <td>
                    <input type="text"
                           id="survey_title"
                           name="survey_title"
                           value="<?php echo esc_attr( $title ); ?>"
                           class="large-text"
                           maxlength="512"
                           <?php echo $is_read_only ? 'readonly' : 'required'; ?>>
                </td>
            </tr>
            <tr>
                <th scope="row" style="vertical-align:top;padding-top:12px;">
                    <label for="survey_description"><?php esc_html_e( 'Opis', 'evoting' ); ?></label>
                </th>
                <td>
                    <textarea id="survey_description"
                              name="survey_description"
                              rows="10"
                              class="large-text"
                              maxlength="5000"
                              <?php echo $is_read_only ? 'readonly' : ''; ?>><?php echo esc_textarea( $desc ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Opisz cel ankiety (np. wybór kandydata na przewodniczącego ruchu).', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="survey_date_start_display"><?php esc_html_e( 'Data i godzina rozpoczęcia', 'evoting' ); ?></label></th>
                <td>
                    <input type="datetime-local"
                           id="survey_date_start_display"
                           value="<?php echo esc_attr( $date_start_display ); ?>"
                           disabled readonly class="evoting-date-readonly">
                    <p class="description"><?php esc_html_e( 'Zawsze „teraz". Przy „Wystartuj ankietę" data startu ustawiana jest na bieżący moment.', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="survey_duration"><?php esc_html_e( 'Czas trwania ankiety', 'evoting' ); ?> <span style="color:red">*</span></label></th>
                <td>
                    <select id="survey_duration" name="survey_duration" <?php echo $is_read_only ? 'disabled' : 'required'; ?>>
                        <?php foreach ( $duration_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected_duration, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Od momentu startu ankiety.', 'evoting' ); ?></p>
                </td>
            </tr>
        </table>

        <!-- ── Pola ankiety ─────────────────────────────────────── -->

        <h2 style="margin-top:28px;"><?php esc_html_e( 'Pola ankiety', 'evoting' ); ?>
            <span id="evoting-survey-field-count" style="font-size:.8em;font-weight:400;color:#666;margin-left:8px;">
                (<?php echo count( $questions ); ?> / <?php echo esc_html( Evoting_Survey::MAX_QUESTIONS ); ?>)
            </span>
        </h2>
        <p class="description" style="max-width:700px;">
            <?php esc_html_e( 'Stały nagłówek ankiety widoczny na stronie publicznej: "Proszę o uzupełnienie."', 'evoting' ); ?>
            <?php printf(
                esc_html__( 'Możesz dodać od 1 do %d pól.', 'evoting' ),
                esc_html( Evoting_Survey::MAX_QUESTIONS )
            ); ?>
        </p>

        <div id="evoting-survey-fields" style="max-width:700px;margin-top:12px;">
            <?php if ( empty( $questions ) && ! $is_read_only ) : ?>
                <!-- Pusty formularz — jeden placeholder -->
                <div class="evoting-survey-field-row" data-index="0">
                    <?php evoting_render_survey_field_row( 0, null, $field_type_labels, $profile_field_options, $is_read_only ); ?>
                </div>
            <?php else : ?>
                <?php foreach ( $questions as $i => $q ) : ?>
                    <div class="evoting-survey-field-row" data-index="<?php echo esc_attr( $i ); ?>">
                        <?php evoting_render_survey_field_row( $i, $q, $field_type_labels, $profile_field_options, $is_read_only ); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ( ! $is_read_only ) : ?>
        <p style="margin-top:12px;">
            <button type="button" id="evoting-add-survey-field" class="button"
                    <?php echo count( $questions ) >= Evoting_Survey::MAX_QUESTIONS ? 'disabled' : ''; ?>>
                + <?php esc_html_e( 'Dodaj pole', 'evoting' ); ?>
            </button>
        </p>

        <!-- ── Przyciski zapisu ──────────────────────────────────── -->
        <div style="margin-top:28px;display:flex;gap:12px;flex-wrap:wrap;">
            <button type="submit" name="evoting_submit_action" value="draft" class="button button-secondary button-large">
                <?php esc_html_e( 'Zapisz jako szkic', 'evoting' ); ?>
            </button>
            <button type="submit" name="evoting_submit_action" value="start_now" class="button button-primary button-large"
                    style="background:#b32d2e;border-color:#a02020;"
                    id="evoting-survey-start-btn">
                <?php esc_html_e( 'Wystartuj ankietę', 'evoting' ); ?>
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php
/**
 * Wyrenderuj jeden wiersz pola ankiety.
 *
 * @param array $profile_options [ '' => '— brak', 'first_name' => 'Imię', ... ]
 */
function evoting_render_survey_field_row( int $i, ?object $q, array $labels, array $profile_options, bool $read_only ): void {
    $body          = $q ? esc_attr( $q->body ) : '';
    $field_type    = $q ? esc_attr( $q->field_type ) : 'text_short';
    $max_chars     = $q ? (int) $q->max_chars : 100;
    $profile_field = $q && isset( $q->profile_field ) ? (string) $q->profile_field : '';
    $disabled      = $read_only ? 'disabled' : '';
    ?>
    <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;padding:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
        <span style="margin-top:8px;font-weight:600;color:#666;min-width:24px;"><?php echo esc_html( $i + 1 ); ?>.</span>

        <div style="flex:1;display:flex;flex-direction:column;gap:6px;">
            <input type="text"
                   name="survey_questions[<?php echo esc_attr( $i ); ?>][body]"
                   value="<?php echo $body; ?>"
                   placeholder="<?php esc_attr_e( 'Etykieta / tytuł pola', 'evoting' ); ?>"
                   maxlength="512"
                   style="width:100%;"
                   class="evoting-survey-field-body"
                   <?php echo $disabled; ?>>

            <select name="survey_questions[<?php echo esc_attr( $i ); ?>][field_type]"
                    class="evoting-survey-field-type"
                    <?php echo $disabled; ?>>
                <?php foreach ( $labels as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"
                            <?php selected( $field_type, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label style="font-size:11px;color:#666;"><?php esc_html_e( 'Pole profilu (na stronie /zgłoszenia/ dane wrażliwe są ukrywane)', 'evoting' ); ?></label>
            <select name="survey_questions[<?php echo esc_attr( $i ); ?>][profile_field]"
                    class="evoting-survey-field-profile"
                    <?php echo $disabled; ?>>
                <?php foreach ( $profile_options as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"
                            <?php selected( $profile_field, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ( ! $read_only ) : ?>
        <button type="button"
                class="button evoting-remove-survey-field"
                style="margin-top:4px;"
                title="<?php esc_attr_e( 'Usuń pole', 'evoting' ); ?>">✕</button>
        <?php endif; ?>
    </div>
    <?php
}
?>
