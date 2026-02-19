<?php
defined( 'ABSPATH' ) || exit;

$is_edit      = isset( $poll ) && $poll;
$title        = $is_edit ? $poll->title : '';
$desc         = $is_edit ? $poll->description : '';
$status       = $is_edit ? $poll->status : 'draft';
$date_start   = $is_edit ? $poll->date_start : '';
$date_end     = $is_edit ? $poll->date_end : '';
$join_mode    = $is_edit ? ( $poll->join_mode ?? 'open' ) : 'open';
$vote_mode    = $is_edit ? ( $poll->vote_mode ?? 'public' ) : 'public';
$notify_start = $is_edit ? (bool) $poll->notify_start : false;
$notify_end   = $is_edit ? (bool) $poll->notify_end : false;
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
$all_groups   = $wpdb->get_results( "SELECT id, name FROM {$groups_table} ORDER BY name ASC" );
?>
<div class="wrap">
    <h1><?php echo $is_edit ? esc_html__( 'Edytuj głosowanie', 'evoting' ) : esc_html__( 'Nowe głosowanie', 'evoting' ); ?></h1>

    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało utworzone.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało zaktualizowane.', 'evoting' ); ?></p></div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'evoting_admin_error' );
    if ( $error ) :
        delete_transient( 'evoting_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" id="evoting-poll-form">
        <?php wp_nonce_field( 'evoting_save_poll', 'evoting_poll_nonce' ); ?>

        <?php if ( $is_edit ) : ?>
            <input type="hidden" name="poll_id" value="<?php echo esc_attr( $poll->id ); ?>">
            <input type="hidden" name="evoting_action" value="update">
        <?php else : ?>
            <input type="hidden" name="evoting_action" value="create">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="poll_title"><?php esc_html_e( 'Tytuł', 'evoting' ); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="text" id="poll_title" name="poll_title"
                           value="<?php echo esc_attr( $title ); ?>"
                           maxlength="512" class="large-text" required>
                    <p class="description"><?php esc_html_e( 'Maks. 512 znaków.', 'evoting' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="poll_description"><?php esc_html_e( 'Opis', 'evoting' ); ?></label></th>
                <td><textarea id="poll_description" name="poll_description" rows="4" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="poll_status"><?php esc_html_e( 'Status', 'evoting' ); ?></label></th>
                <td>
                    <select id="poll_status" name="poll_status">
                        <option value="draft"  <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Szkic', 'evoting' ); ?></option>
                        <option value="open"   <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Otwarte', 'evoting' ); ?></option>
                        <option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Zamknięte', 'evoting' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="date_start"><?php esc_html_e( 'Data rozpoczęcia', 'evoting' ); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="date" id="date_start" name="date_start"
                           value="<?php echo esc_attr( $date_start ); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="date_end"><?php esc_html_e( 'Data zakończenia', 'evoting' ); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="date" id="date_end" name="date_end"
                           value="<?php echo esc_attr( $date_end ); ?>" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Tryb dołączania', 'evoting' ); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="join_mode" value="open" <?php checked( $join_mode, 'open' ); ?>>
                            <?php esc_html_e( 'Otwarte — nowi użytkownicy mogą dołączyć do aktywnego głosowania', 'evoting' ); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="join_mode" value="closed" <?php checked( $join_mode, 'closed' ); ?>>
                            <?php esc_html_e( 'Zamknięte — lista uprawnionych ustalana przy otwarciu (snapshot)', 'evoting' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Tryb głosowania', 'evoting' ); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="vote_mode" value="public" <?php checked( $vote_mode, 'public' ); ?>>
                            <?php esc_html_e( 'Jawne — zanonimizowane nicki widoczne w wynikach', 'evoting' ); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="vote_mode" value="anonymous" <?php checked( $vote_mode, 'anonymous' ); ?>>
                            🔒 <?php esc_html_e( 'Anonimowe — UWAGA: decyzja jest nieodwracalna, zero danych osobowych w wynikach', 'evoting' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Grupy docelowe', 'evoting' ); ?></th>
                <td>
                    <?php if ( ! empty( $all_groups ) ) : ?>
                        <select name="target_groups[]" multiple size="6" style="min-width:280px;">
                            <?php foreach ( $all_groups as $group ) : ?>
                                <option value="<?php echo esc_attr( $group->id ); ?>"
                                        <?php echo in_array( (int) $group->id, $selected_group_ids, true ) ? 'selected' : ''; ?>>
                                    <?php echo esc_html( $group->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Ctrl+klik aby wybrać wiele grup. Zostaw puste = wszyscy uprawnieni użytkownicy.', 'evoting' ); ?></p>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Brak grup. Dodaj grupy w sekcji Grupy, a głosowanie będzie dostępne dla wszystkich uprawnionych.', 'evoting' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Powiadomienia e-mail', 'evoting' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="notify_start" value="1" <?php checked( $notify_start ); ?>>
                        <?php esc_html_e( 'Wyślij e-mail przy otwarciu głosowania', 'evoting' ); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="notify_end" value="1" <?php checked( $notify_end ); ?>>
                        <?php esc_html_e( 'Wyślij e-mail przypomnienie 24h przed zakończeniem', 'evoting' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Pytania', 'evoting' ); ?> <small>(<?php esc_html_e( '1–24 pytania, 3–12 odpowiedzi per pytanie', 'evoting' ); ?>)</small></h2>
        <p class="description"><?php esc_html_e( 'Ostatnia odpowiedź każdego pytania to obowiązkowe "Wstrzymuję się" (doliczane do nieuczestniczących).', 'evoting' ); ?></p>

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
                                   placeholder="<?php esc_attr_e( 'Treść pytania', 'evoting' ); ?>">
                            <button type="button" class="button evoting-remove-question">&times;</button>
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
                                           <?php echo $answer->is_abstain ? 'data-abstain="1"' : ''; ?>>
                                    <?php if ( ! $answer->is_abstain ) : ?>
                                        <button type="button" class="button evoting-remove-answer">&times;</button>
                                    <?php else : ?>
                                        <span class="evoting-abstain-label"><?php esc_html_e( '(wstrzymanie – auto)', 'evoting' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-small evoting-add-answer">
                            + <?php esc_html_e( 'Dodaj odpowiedź', 'evoting' ); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="evoting-question-block" data-question-index="0">
                    <div class="evoting-question-header">
                        <span class="evoting-question-number">1.</span>
                        <input type="text" name="questions[0][text]"
                               maxlength="512" class="large-text"
                               placeholder="<?php esc_attr_e( 'Treść pytania', 'evoting' ); ?>">
                        <button type="button" class="button evoting-remove-question">&times;</button>
                    </div>
                    <div class="evoting-answers-container">
                        <div class="evoting-answer-row">
                            <span class="evoting-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][0]"
                                   value="<?php esc_attr_e( 'Jestem za', 'evoting' ); ?>"
                                   maxlength="512" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'evoting' ); ?>">
                            <button type="button" class="button evoting-remove-answer">&times;</button>
                        </div>
                        <div class="evoting-answer-row">
                            <span class="evoting-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][1]"
                                   value="<?php esc_attr_e( 'Jestem przeciw', 'evoting' ); ?>"
                                   maxlength="512" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'evoting' ); ?>">
                            <button type="button" class="button evoting-remove-answer">&times;</button>
                        </div>
                        <div class="evoting-answer-row evoting-answer-row--locked">
                            <span class="evoting-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][2]"
                                   value="<?php esc_attr_e( 'Wstrzymuję się', 'evoting' ); ?>"
                                   maxlength="512" class="regular-text"
                                   data-abstain="1"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'evoting' ); ?>">
                            <span class="evoting-abstain-label"><?php esc_html_e( '(wstrzymanie – auto)', 'evoting' ); ?></span>
                        </div>
                    </div>
                    <button type="button" class="button button-small evoting-add-answer">
                        + <?php esc_html_e( 'Dodaj odpowiedź', 'evoting' ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <p style="margin-top:12px;">
            <button type="button" id="evoting-add-question" class="button">
                + <?php esc_html_e( 'Dodaj pytanie', 'evoting' ); ?>
            </button>
        </p>

        <?php if ( $is_edit ) : ?>
            <p class="submit">
                <?php submit_button( __( 'Zapisz zmiany', 'evoting' ), 'primary', 'submit', false ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=results&poll_id=' . $poll->id ) ); ?>"
                   class="button"><?php esc_html_e( 'Zobacz wyniki', 'evoting' ); ?></a>
            </p>
            <hr>
            <h3><?php esc_html_e( 'Usuń głosowanie', 'evoting' ); ?></h3>
            <p>
                <button type="submit" name="evoting_action" value="delete" class="button button-link-delete"
                        onclick="return confirm('<?php esc_attr_e( 'Czy na pewno chcesz usunąć to głosowanie?', 'evoting' ); ?>');">
                    <?php esc_html_e( 'Usuń głosowanie', 'evoting' ); ?>
                </button>
            </p>
        <?php else : ?>
            <?php submit_button( __( 'Utwórz głosowanie', 'evoting' ) ); ?>
        <?php endif; ?>
    </form>
</div>
