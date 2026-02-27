<?php
defined( 'ABSPATH' ) || exit;

$is_read_only = ! empty( $is_read_only );
$is_edit      = isset( $poll ) && $poll;
$title        = $is_edit ? $poll->title : '';
$desc         = $is_edit ? $poll->description : '';
// Data rozpoczęcia: zawsze „teraz” (wyszarzona). Czas trwania: menu wyboru.
$now_ts       = current_time( 'timestamp' );
$date_start_display = wp_date( 'Y-m-d\TH:i', $now_ts );
$duration_options  = [
    '1h'   => __( '1h', 'evoting' ),
    '1d'   => __( '1 dzień', 'evoting' ),
    '2d'   => __( '2 dni', 'evoting' ),
    '3d'   => __( '3 dni', 'evoting' ),
    '7d'   => __( '7 dni', 'evoting' ),
    '14d'  => __( '14 dni', 'evoting' ),
    '21d'  => __( '21 dni', 'evoting' ),
];
$selected_duration = '7d';
if ( $is_edit && ! empty( $poll->date_start ) && ! empty( $poll->date_end ) ) {
    $start_ts = strtotime( $poll->date_start );
    $end_ts   = strtotime( $poll->date_end );
    if ( $start_ts && $end_ts && $end_ts > $start_ts ) {
        $diff = $end_ts - $start_ts;
        $hours = $diff / 3600;
        $days  = $diff / 86400;
        if ( $hours <= 1.1 ) {
            $selected_duration = '1h';
        } elseif ( $days <= 1.1 ) {
            $selected_duration = '1d';
        } elseif ( $days <= 2.1 ) {
            $selected_duration = '2d';
        } elseif ( $days <= 3.1 ) {
            $selected_duration = '3d';
        } elseif ( $days <= 7.1 ) {
            $selected_duration = '7d';
        } elseif ( $days <= 14.1 ) {
            $selected_duration = '14d';
        } else {
            $selected_duration = '21d';
        }
    }
}
$notify_start = $is_edit ? (bool) $poll->notify_start : true;
$questions    = $is_edit ? $poll->questions : [];

// target_groups: stored as JSON array of group IDs
$selected_group_ids = [];
if ( $is_edit && ! empty( $poll->target_groups ) ) {
    $decoded = json_decode( $poll->target_groups, true );
    if ( is_array( $decoded ) ) {
        $selected_group_ids = array_map( 'absint', $decoded );
    }
}

// Get all groups for multiselect.
global $wpdb;
$groups_table = $wpdb->prefix . 'evoting_groups';
$all_groups   = $wpdb->get_results( "SELECT id, name, member_count FROM {$groups_table} ORDER BY name ASC" );
?>
<div class="wrap">
    <h1><?php
        if ( $is_read_only ) {
            esc_html_e( 'Podgląd głosowania', 'evoting' );
        } else {
            echo $is_edit ? esc_html__( 'Edytuj głosowanie', 'evoting' ) : esc_html__( 'Nowe głosowanie', 'evoting' );
        }
    ?></h1>

    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało utworzone.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało zaktualizowane.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['started'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało uruchomione. Data rozpoczęcia ustawiona na dziś.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Utworzono kopię głosowania. Możesz ją teraz edytować.', 'evoting' ); ?></p></div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'evoting_admin_error' );
    if ( $error ) :
        delete_transient( 'evoting_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" id="evoting-poll-form"<?php echo $is_read_only ? ' class="evoting-form-readonly"' : ''; ?>>
        <?php if ( ! $is_read_only ) : ?>
            <?php wp_nonce_field( 'evoting_save_poll', 'evoting_poll_nonce' ); ?>
            <?php if ( $is_edit ) : ?>
                <input type="hidden" name="poll_id" value="<?php echo esc_attr( $poll->id ); ?>">
                <input type="hidden" name="evoting_action" value="update">
            <?php else : ?>
                <input type="hidden" name="evoting_action" value="create">
            <?php endif; ?>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="poll_title"><?php esc_html_e( 'Tytuł', 'evoting' ); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="text" id="poll_title" name="poll_title"
                           value="<?php echo esc_attr( $title ); ?>"
                           maxlength="512" class="large-text" <?php echo $is_read_only ? 'readonly disabled' : 'required'; ?>>
                    <?php if ( ! $is_read_only ) : ?>
                        <p class="description"><?php esc_html_e( 'Maks. 512 znaków.', 'evoting' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="poll_description"><?php esc_html_e( 'Opis', 'evoting' ); ?></label></th>
                <td><textarea id="poll_description" name="poll_description" rows="4" class="large-text" <?php echo $is_read_only ? 'readonly disabled' : ''; ?>><?php echo esc_textarea( $desc ); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="date_start_display"><?php esc_html_e( 'Data i godzina rozpoczęcia', 'evoting' ); ?></label></th>
                <td>
                    <input type="datetime-local" id="date_start_display" value="<?php echo esc_attr( $date_start_display ); ?>"
                           disabled readonly class="evoting-date-readonly">
                    <p class="description"><?php esc_html_e( 'Zawsze „teraz”. Przy „Wystartuj głosowanie” data startu ustawiana jest na bieżący moment.', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="poll_duration"><?php esc_html_e( 'Czas trwania głosowania', 'evoting' ); ?> <span class="required">*</span></label></th>
                <td>
                    <select id="poll_duration" name="poll_duration" <?php echo $is_read_only ? 'disabled' : 'required'; ?>>
                        <?php foreach ( $duration_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected_duration, $val ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Od momentu startu głosowania.', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Grupy docelowe', 'evoting' ); ?></th>
                <td>
                    <?php if ( ! empty( $all_groups ) ) : ?>
                        <select name="target_groups[]" id="evoting-target-groups" multiple size="6" style="min-width:280px;" <?php echo $is_read_only ? 'disabled' : ''; ?>>
                            <?php foreach ( $all_groups as $group ) : ?>
                                <option value="<?php echo esc_attr( $group->id ); ?>"
                                        data-member-count="<?php echo esc_attr( (int) ( $group->member_count ?? 0 ) ); ?>"
                                        <?php echo in_array( (int) $group->id, $selected_group_ids, true ) ? 'selected' : ''; ?>>
                                    <?php echo esc_html( $group->name . ' (' . ( (int) ( $group->member_count ?? 0 ) ) . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( ! $is_read_only ) : ?>
                            <p class="description"><?php esc_html_e( 'Ctrl+klik aby wybrać wiele grup. Zostaw puste = wszyscy uprawnieni użytkownicy.', 'evoting' ); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Brak grup. Dodaj grupy w sekcji Grupy, a głosowanie będzie dostępne dla wszystkich uprawnionych.', 'evoting' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Powiadomienia e-mail', 'evoting' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="notify_start" value="1" <?php checked( $notify_start ); ?> <?php echo $is_read_only ? 'disabled' : ''; ?>>
                        <?php esc_html_e( 'Wyślij e-mail przy otwarciu głosowania', 'evoting' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Zaznaczone domyślnie.', 'evoting' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Pytania', 'evoting' ); ?> <small>(<?php esc_html_e( '1–24 pytania, 3–12 odpowiedzi per pytanie', 'evoting' ); ?>)</small></h2>
        <?php if ( ! $is_read_only ) : ?>
            <p class="description"><?php esc_html_e( 'Ostatnia odpowiedź każdego pytania to obowiązkowe "Wstrzymuję się" (doliczane do nieuczestniczących).', 'evoting' ); ?></p>
        <?php endif; ?>

        <div id="evoting-questions-container">
            <?php if ( ! empty( $questions ) ) : ?>
                <?php foreach ( $questions as $qi => $q ) : ?>
                    <div class="evoting-question-block" data-question-index="<?php echo esc_attr( $qi ); ?>">
                        <div class="evoting-question-header">
                            <span class="evoting-question-number"><?php echo esc_html( $qi + 1 ); ?>.</span>
                            <input type="text" name="questions[<?php echo esc_attr( $qi ); ?>][text]"
                                   value="<?php echo esc_attr( $q->body ); ?>"
                                   maxlength="512"
                                   class="large-text"
                                   placeholder="<?php esc_attr_e( 'Treść pytania', 'evoting' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <?php if ( ! $is_read_only ) : ?>
                                <button type="button" class="button evoting-remove-question">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div class="evoting-answers-container">
                            <?php foreach ( $q->answers as $ai => $answer ) : ?>
                                <div class="evoting-answer-row<?php echo $answer->is_abstain ? ' evoting-answer-row--locked' : ''; ?>">
                                    <span class="evoting-answer-bullet">&#8226;</span>
                                    <input type="text"
                                           name="questions[<?php echo esc_attr( $qi ); ?>][answers][<?php echo esc_attr( $ai ); ?>]"
                                           value="<?php echo esc_attr( $answer->body ); ?>"
                                           maxlength="512"
                                           class="regular-text"
                                           placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'evoting' ); ?>"
                                           <?php echo $answer->is_abstain ? 'data-abstain="1"' : ''; ?>
                                           <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                                    <?php if ( ! $answer->is_abstain && ! $is_read_only ) : ?>
                                        <button type="button" class="button evoting-remove-answer">&times;</button>
                                    <?php elseif ( $answer->is_abstain ) : ?>
                                        <span class="evoting-abstain-label"><?php esc_html_e( '(wstrzymanie – auto)', 'evoting' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( ! $is_read_only ) : ?>
                            <button type="button" class="button button-small evoting-add-answer">
                                + <?php esc_html_e( 'Dodaj odpowiedź', 'evoting' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="evoting-question-block" data-question-index="0">
                    <div class="evoting-question-header">
                        <span class="evoting-question-number">1.</span>
                        <input type="text" name="questions[0][text]"
                               maxlength="512" class="large-text"
                               placeholder="<?php esc_attr_e( 'Treść pytania', 'evoting' ); ?>"
                               <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                        <?php if ( ! $is_read_only ) : ?>
                            <button type="button" class="button evoting-remove-question">&times;</button>
                        <?php endif; ?>
                    </div>
                    <div class="evoting-answers-container">
                        <div class="evoting-answer-row">
                            <span class="evoting-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][0]"
                                   value="<?php esc_attr_e( 'Jestem za', 'evoting' ); ?>"
                                   maxlength="512" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'evoting' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <?php if ( ! $is_read_only ) : ?>
                                <button type="button" class="button evoting-remove-answer">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div class="evoting-answer-row">
                            <span class="evoting-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][1]"
                                   value="<?php esc_attr_e( 'Jestem przeciw', 'evoting' ); ?>"
                                   maxlength="512" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'evoting' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <?php if ( ! $is_read_only ) : ?>
                                <button type="button" class="button evoting-remove-answer">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div class="evoting-answer-row evoting-answer-row--locked">
                            <span class="evoting-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][2]"
                                   value="<?php esc_attr_e( 'Wstrzymuję się', 'evoting' ); ?>"
                                   maxlength="512" class="regular-text"
                                   data-abstain="1"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'evoting' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <span class="evoting-abstain-label"><?php esc_html_e( '(wstrzymanie – auto)', 'evoting' ); ?></span>
                        </div>
                    </div>
                    <?php if ( ! $is_read_only ) : ?>
                        <button type="button" class="button button-small evoting-add-answer">
                            + <?php esc_html_e( 'Dodaj odpowiedź', 'evoting' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( ! $is_read_only ) : ?>
            <p style="margin-top:12px;">
                <button type="button" id="evoting-add-question" class="button">
                    + <?php esc_html_e( 'Dodaj pytanie', 'evoting' ); ?>
                </button>
            </p>
        <?php endif; ?>

        <?php if ( $is_read_only ) : ?>
            <p class="submit">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting' ) ); ?>" class="button"><?php esc_html_e( 'Wróć do listy', 'evoting' ); ?></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=results&poll_id=' . $poll->id ) ); ?>"
                   class="button button-primary"><?php esc_html_e( 'Zobacz wyniki', 'evoting' ); ?></a>
            </p>
        <?php else : ?>
            <p class="submit evoting-poll-actions">
                <button type="submit" name="evoting_submit_action" value="save_draft" class="button button-primary">
                    <?php esc_html_e( 'Zapisz jako szkic', 'evoting' ); ?>
                </button>
                <button type="submit" name="evoting_submit_action" value="start_now" id="evoting-btn-start-poll" class="button evoting-btn-start-poll" disabled>
                    <?php esc_html_e( 'Wystartuj głosowanie', 'evoting' ); ?>
                </button>
            </p>
            <?php if ( $is_edit ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Usuń głosowanie', 'evoting' ); ?></h3>
                <p>
                    <button type="submit" name="evoting_action" value="delete" class="button button-link-delete"
                            onclick="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć to głosowanie?', 'evoting' ) ); ?>');">
                        <?php esc_html_e( 'Usuń głosowanie', 'evoting' ); ?>
                    </button>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </form>
</div>
