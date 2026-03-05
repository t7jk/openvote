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
    '1h'   => __( '1h', 'openvote' ),
    '12h'  => __( '12h', 'openvote' ),
    '1d'   => __( '1 dzień', 'openvote' ),
    '7d'   => __( '7 dni', 'openvote' ),
    '14d'  => __( '14 dni', 'openvote' ),
    '21d'  => __( '21 dni', 'openvote' ),
    '28d'  => __( '28 dni', 'openvote' ),
];
$selected_duration = '7d';
if ( $is_edit && ! empty( $poll->date_start ) && ! empty( $poll->date_end ) ) {
    $start_ts = strtotime( $poll->date_start );
    $end_ts   = strtotime( $poll->date_end );
    if ( $start_ts && $end_ts && $end_ts > $start_ts ) {
        $diff   = $end_ts - $start_ts;
        $hours  = $diff / 3600;
        $days   = $diff / 86400;
        if ( $hours <= 1.1 ) {
            $selected_duration = '1h';
        } elseif ( $hours <= 12.1 ) {
            $selected_duration = '12h';
        } elseif ( $days <= 1.1 ) {
            $selected_duration = '1d';
        } elseif ( $days <= 7.1 ) {
            $selected_duration = '7d';
        } elseif ( $days <= 14.1 ) {
            $selected_duration = '14d';
        } elseif ( $days <= 21.1 ) {
            $selected_duration = '21d';
        } else {
            $selected_duration = '28d';
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

// Get all groups for multiselect (dla koordynatora z ograniczeniem „własne” tylko jego grupy).
global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';
$all_groups   = $wpdb->get_results( "SELECT id, name, member_count, is_test_group FROM {$groups_table} ORDER BY is_test_group DESC, name ASC" );
if ( openvote_is_coordinator_restricted_to_own_groups() ) {
    $my_group_ids = array_flip( Openvote_Role_Manager::get_user_groups( get_current_user_id() ) );
    if ( openvote_create_test_group_enabled() ) {
        $test_gid = openvote_get_test_group_id();
        if ( $test_gid ) {
            $my_group_ids[ $test_gid ] = 0;
        }
    }
    $all_groups = array_filter( $all_groups, function ( $g ) use ( $my_group_ids ) {
        return isset( $my_group_ids[ (int) $g->id ] );
    } );
}
?>
<div class="wrap">
    <h1><?php
        if ( $is_read_only ) {
            esc_html_e( 'Podgląd głosowania', 'openvote' );
        } else {
            echo $is_edit ? esc_html__( 'Edytuj głosowanie', 'openvote' ) : esc_html__( 'Nowe głosowanie', 'openvote' );
        }
    ?></h1>

    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało utworzone.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało zaktualizowane.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['started'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Głosowanie zostało uruchomione. Data rozpoczęcia ustawiona na dziś.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Utworzono kopię głosowania. Możesz ją teraz edytować.', 'openvote' ); ?></p></div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'openvote_admin_error' );
    if ( $error ) :
        delete_transient( 'openvote_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" id="openvote-poll-form"<?php echo $is_read_only ? ' class="openvote-form-readonly"' : ''; ?>
          <?php if ( ! $is_read_only ) : ?>onsubmit="var b=this.querySelectorAll('button[type=submit],input[type=submit]');for(var i=0;i<b.length;i++)b[i].disabled=true;"<?php endif; ?>>
        <?php if ( $is_read_only && $is_edit && $poll && in_array( $poll->status ?? '', [ 'open', 'closed' ], true ) ) : ?>
            <?php wp_nonce_field( 'openvote_save_poll', 'openvote_poll_nonce' ); ?>
            <input type="hidden" name="poll_id" value="<?php echo esc_attr( $poll->id ); ?>">
            <input type="hidden" name="openvote_action" value="update">
            <input type="hidden" name="openvote_extend_duration" value="1">
            <input type="hidden" name="openvote_submit_action" id="openvote-poll-submit-action" value="save_draft">
        <?php endif; ?>
        <?php if ( ! $is_read_only ) : ?>
            <?php wp_nonce_field( 'openvote_save_poll', 'openvote_poll_nonce' ); ?>
            <input type="hidden" name="openvote_submit_action" id="openvote-poll-submit-action" value="save_draft">
            <?php if ( $is_edit ) : ?>
                <input type="hidden" name="poll_id" value="<?php echo esc_attr( $poll->id ); ?>">
                <input type="hidden" name="openvote_action" value="update">
            <?php else : ?>
                <input type="hidden" name="openvote_action" value="create">
            <?php endif; ?>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="poll_title"><?php esc_html_e( 'Tytuł', 'openvote' ); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="text" id="poll_title" name="poll_title"
                           value="<?php echo esc_attr( $title ); ?>"
                           maxlength="512" class="large-text" <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                    <?php if ( ! $is_read_only ) : ?>
                        <p class="description"><?php esc_html_e( 'Maks. 512 znaków.', 'openvote' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="poll_description"><?php esc_html_e( 'Opis', 'openvote' ); ?></label></th>
                <td><textarea id="poll_description" name="poll_description" rows="4" class="large-text" <?php echo $is_read_only ? 'readonly disabled' : ''; ?>><?php echo esc_textarea( $desc ); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="date_start_display"><?php esc_html_e( 'Data i godzina rozpoczęcia', 'openvote' ); ?></label></th>
                <td>
                    <input type="datetime-local" id="date_start_display" value="<?php echo esc_attr( $date_start_display ); ?>"
                           disabled readonly class="openvote-date-readonly">
                    <p class="description"><?php esc_html_e( 'Zawsze „teraz”. Przy „Wystartuj głosowanie” data startu ustawiana jest na bieżący moment.', 'openvote' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="poll_duration"><?php esc_html_e( 'Czas trwania głosowania', 'openvote' ); ?> <span class="required">*</span></label></th>
                <td>
                    <select id="poll_duration" name="poll_duration" <?php echo $is_read_only ? '' : ''; ?>>
                        <?php foreach ( $duration_options as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected_duration, $val ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Od momentu startu głosowania.', 'openvote' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Grupy docelowe', 'openvote' ); ?></th>
                <td>
                    <?php if ( ! empty( $all_groups ) ) : ?>
                        <select name="target_groups[]" id="openvote-target-groups" multiple size="6" style="min-width:280px;" <?php echo $is_read_only ? 'disabled' : ''; ?>>
                            <?php foreach ( $all_groups as $group ) : ?>
                                <option value="<?php echo esc_attr( $group->id ); ?>"
                                        data-member-count="<?php echo esc_attr( (int) ( $group->member_count ?? 0 ) ); ?>"
                                        <?php echo in_array( (int) $group->id, $selected_group_ids, true ) ? 'selected' : ''; ?>>
                                    <?php echo esc_html( $group->name . ' (' . ( (int) ( $group->member_count ?? 0 ) ) . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( ! $is_read_only ) : ?>
                            <p class="description"><?php esc_html_e( 'Ctrl+klik aby wybrać wiele grup. Zostaw puste = wszyscy uprawnieni użytkownicy.', 'openvote' ); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Brak grup. Dodaj grupy w sekcji Grupy, a głosowanie będzie dostępne dla wszystkich uprawnionych.', 'openvote' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Powiadomienia e-mail', 'openvote' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="notify_start" value="1" <?php checked( $notify_start ); ?>>
                        <?php esc_html_e( 'Wyślij e-mail przy otwarciu głosowania', 'openvote' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Zaznaczone domyślnie.', 'openvote' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Pytania', 'openvote' ); ?> <small>(<?php esc_html_e( '1–24 pytania, 3–12 odpowiedzi per pytanie', 'openvote' ); ?>)</small></h2>
        <?php if ( ! $is_read_only ) : ?>
            <p class="description"><?php esc_html_e( 'Ostatnia odpowiedź każdego pytania to obowiązkowe "Wstrzymuję się" (doliczane do nieuczestniczących).', 'openvote' ); ?></p>
        <?php endif; ?>

        <div id="openvote-questions-container">
            <?php if ( ! empty( $questions ) ) : ?>
                <?php foreach ( $questions as $qi => $q ) : ?>
                    <div class="openvote-question-block" data-question-index="<?php echo esc_attr( $qi ); ?>">
                        <div class="openvote-question-header">
                            <span class="openvote-question-number"><?php echo esc_html( $qi + 1 ); ?>.</span>
                            <input type="text" name="questions[<?php echo esc_attr( $qi ); ?>][text]"
                                   value="<?php echo esc_attr( $q->body ); ?>"
                                   maxlength="512"
                                   class="large-text"
                                   placeholder="<?php esc_attr_e( 'Treść pytania', 'openvote' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <?php if ( ! $is_read_only ) : ?>
                                <button type="button" class="button openvote-remove-question">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div class="openvote-answers-container">
                            <?php foreach ( $q->answers as $ai => $answer ) : ?>
                                <div class="openvote-answer-row<?php echo $answer->is_abstain ? ' openvote-answer-row--locked' : ''; ?>">
                                    <span class="openvote-answer-bullet">&#8226;</span>
                                    <input type="text"
                                           name="questions[<?php echo esc_attr( $qi ); ?>][answers][<?php echo esc_attr( $ai ); ?>]"
                                           value="<?php echo esc_attr( $answer->body ); ?>"
                                           maxlength="512"
                                           class="regular-text"
                                           placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'openvote' ); ?>"
                                           <?php echo $answer->is_abstain ? 'data-abstain="1"' : ''; ?>
                                           <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                                    <?php if ( ! $answer->is_abstain && ! $is_read_only ) : ?>
                                        <button type="button" class="button openvote-remove-answer">&times;</button>
                                    <?php elseif ( $answer->is_abstain ) : ?>
                                        <span class="openvote-abstain-label"><?php esc_html_e( '(wstrzymanie – auto)', 'openvote' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( ! $is_read_only ) : ?>
                            <button type="button" class="button button-small openvote-add-answer">
                                + <?php esc_html_e( 'Dodaj odpowiedź', 'openvote' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="openvote-question-block" data-question-index="0">
                    <div class="openvote-question-header">
                        <span class="openvote-question-number">1.</span>
                        <input type="text" name="questions[0][text]"
                               maxlength="512" class="large-text"
                               placeholder="<?php esc_attr_e( 'Treść pytania', 'openvote' ); ?>"
                               <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                        <?php if ( ! $is_read_only ) : ?>
                            <button type="button" class="button openvote-remove-question">&times;</button>
                        <?php endif; ?>
                    </div>
                    <div class="openvote-answers-container">
                        <div class="openvote-answer-row">
                            <span class="openvote-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][0]"
                                   value="<?php esc_attr_e( 'Jestem za', 'openvote' ); ?>"
                                   maxlength="512" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'openvote' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <?php if ( ! $is_read_only ) : ?>
                                <button type="button" class="button openvote-remove-answer">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div class="openvote-answer-row">
                            <span class="openvote-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][1]"
                                   value="<?php esc_attr_e( 'Jestem przeciw', 'openvote' ); ?>"
                                   maxlength="512" class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'openvote' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <?php if ( ! $is_read_only ) : ?>
                                <button type="button" class="button openvote-remove-answer">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div class="openvote-answer-row openvote-answer-row--locked">
                            <span class="openvote-answer-bullet">&#8226;</span>
                            <input type="text" name="questions[0][answers][2]"
                                   value="<?php esc_attr_e( 'Wstrzymuję się', 'openvote' ); ?>"
                                   maxlength="512" class="regular-text"
                                   data-abstain="1"
                                   placeholder="<?php esc_attr_e( 'Treść odpowiedzi', 'openvote' ); ?>"
                                   <?php echo $is_read_only ? 'readonly disabled' : ''; ?>>
                            <span class="openvote-abstain-label"><?php esc_html_e( '(wstrzymanie – auto)', 'openvote' ); ?></span>
                        </div>
                    </div>
                    <?php if ( ! $is_read_only ) : ?>
                        <button type="button" class="button button-small openvote-add-answer">
                            + <?php esc_html_e( 'Dodaj odpowiedź', 'openvote' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( ! $is_read_only ) : ?>
            <p style="margin-top:12px;">
                <button type="button" id="openvote-add-question" class="button">
                    + <?php esc_html_e( 'Dodaj pytanie', 'openvote' ); ?>
                </button>
            </p>
        <?php endif; ?>

        <?php if ( $is_read_only ) : ?>
            <p class="submit">
                <?php if ( $is_edit && $poll && in_array( $poll->status ?? '', [ 'open', 'closed' ], true ) ) : ?>
                <button type="submit" class="button button-primary" name="openvote_extend_duration_submit" value="1"><?php esc_html_e( 'Zapisz zmiany czasu trwania', 'openvote' ); ?></button>
                <?php endif; ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote' ) ); ?>" class="button"><?php esc_html_e( 'Wróć do listy', 'openvote' ); ?></a>
                <?php if ( 'closed' === ( $poll->status ?? '' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=results&poll_id=' . $poll->id ) ); ?>"
                   class="button button-primary"><?php esc_html_e( 'Zobacz wyniki', 'openvote' ); ?></a>
                <?php endif; ?>
            </p>
        <?php else : ?>
            <p class="submit openvote-poll-actions">
                <button type="submit" class="button button-primary" onmousedown="var h=document.getElementById('openvote-poll-submit-action');if(h){h.value='save_draft';}">
                    <?php esc_html_e( 'Zapisz jako szkic', 'openvote' ); ?>
                </button>
                <button type="submit" id="openvote-btn-start-poll" class="button openvote-btn-start-poll" disabled onmousedown="var h=document.getElementById('openvote-poll-submit-action');if(h){h.value='start_now';}">
                    <?php esc_html_e( 'Wystartuj głosowanie', 'openvote' ); ?>
                </button>
            </p>
            <?php if ( $is_edit ) : ?>
                <hr>
                <h3><?php esc_html_e( 'Usuń głosowanie', 'openvote' ); ?></h3>
                <p>
                    <button type="submit" name="openvote_action" value="delete" class="button button-link-delete"
                            onclick="return confirm('<?php echo esc_js( __( 'Czy na pewno chcesz usunąć to głosowanie?', 'openvote' ) ); ?>');">
                        <?php esc_html_e( 'Usuń głosowanie', 'openvote' ); ?>
                    </button>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </form>

    <?php
    $polls_audit_all    = openvote_polls_audit_log_get();
    $polls_audit_per    = 20;
    $polls_audit_total  = count( $polls_audit_all );
    $polls_audit_pages  = $polls_audit_total > 0 ? (int) ceil( $polls_audit_total / $polls_audit_per ) : 1;
    $polls_audit_page   = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
    $polls_audit_page   = min( $polls_audit_page, $polls_audit_pages );
    $polls_audit_offset = ( $polls_audit_page - 1 ) * $polls_audit_per;
    $polls_audit_entries = array_slice( $polls_audit_all, $polls_audit_offset, $polls_audit_per );
    $polls_audit_base_url = add_query_arg( [ 'page' => 'openvote' ], admin_url( 'admin.php' ) );
    if ( ! empty( $_GET['action'] ) && ! empty( $_GET['poll_id'] ) ) {
        $polls_audit_base_url = add_query_arg( [ 'action' => sanitize_text_field( $_GET['action'] ), 'poll_id' => absint( $_GET['poll_id'] ) ], $polls_audit_base_url );
    }
    ?>
    <section class="openvote-polls-audit-log" style="margin-top:32px; max-width:900px;">
        <h2 class="openvote-section-title" style="margin:0 0 8px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Log czynności głosowań', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Kto i kiedy utworzył, edytował, wystartował, zakończył, skopiował lub usunął głosowanie albo wysłał zaproszenia. Lista niekasowalna, w celach bezpieczeństwa.', 'openvote' ); ?></p>
        <div class="openvote-audit-log-box" style="background:#1d2327; color:#f0f0f1; padding:12px 16px; border-radius:4px; max-height:220px; overflow-y:auto; font-family:Consolas, Monaco, monospace; font-size:12px; line-height:1.5;">
            <?php
            if ( empty( $polls_audit_entries ) ) {
                echo '<p style="margin:0; color:#a7aaad;">' . esc_html__( 'Brak wpisów.', 'openvote' ) . '</p>';
            } else {
                foreach ( $polls_audit_entries as $e ) {
                    $t     = isset( $e['t'] ) ? $e['t'] : '';
                    $actor = isset( $e['actor'] ) ? $e['actor'] : '—';
                    $line  = isset( $e['line'] ) ? $e['line'] : '';
                    echo '<div style="margin:2px 0;">' . esc_html( $t . ' ' . $actor . ' ' . $line ) . '</div>';
                }
            }
            ?>
        </div>
        <?php if ( $polls_audit_pages > 1 ) : ?>
        <p class="openvote-audit-log-nav" style="margin:8px 0 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span class="displaying-num" style="color:#646970; font-size:13px;">
                <?php
                echo esc_html( sprintf( __( 'Strona %1$d z %2$d (%3$d wpisów)', 'openvote' ), $polls_audit_page, $polls_audit_pages, $polls_audit_total ) );
                ?>
            </span>
            <?php if ( $polls_audit_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $polls_audit_page - 1, $polls_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Poprzedni', 'openvote' ); ?></a>
            <?php endif; ?>
            <?php if ( $polls_audit_page < $polls_audit_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $polls_audit_page + 1, $polls_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Następny', 'openvote' ); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </section>
</div>
