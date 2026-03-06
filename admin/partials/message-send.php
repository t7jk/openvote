<?php
defined( 'ABSPATH' ) || exit;

/** @var object $message */

global $wpdb;
$mq = $wpdb->prefix . 'openvote_message_queue';

$mq_stats = $wpdb->get_results( $wpdb->prepare(
    "SELECT status, COUNT(*) AS cnt, MAX(sent_at) AS last_sent
     FROM {$mq} WHERE message_id = %d GROUP BY status",
    $message->id
) );

$cnt_sent    = 0;
$cnt_failed  = 0;
$cnt_pending = 0;
$last_sent   = null;
$mq_exists   = false;

foreach ( $mq_stats as $row ) {
    $mq_exists = true;
    if ( 'sent'    === $row->status ) { $cnt_sent    = (int) $row->cnt; $last_sent = $row->last_sent; }
    if ( 'failed'  === $row->status ) { $cnt_failed  = (int) $row->cnt; }
    if ( 'pending' === $row->status ) { $cnt_pending = (int) $row->cnt; }
}
$cnt_total = $cnt_sent + $cnt_failed + $cnt_pending;

$eligible_count   = 0;
$group_ids        = Openvote_Message::get_target_group_ids( $message );
$target_group_names = [];
$preview_recipient_id = 0;

if ( ! empty( $group_ids ) ) {
    $gm_table     = $wpdb->prefix . 'openvote_group_members';
    $groups_table = $wpdb->prefix . 'openvote_groups';
    $placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
    $eligible_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
             INNER JOIN {$gm_table} gm ON u.ID = gm.user_id
             WHERE gm.group_id IN ({$placeholders}) AND u.user_email != '' AND u.user_email IS NOT NULL",
            ...$group_ids
        )
    );
    $target_group_names = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT name FROM {$groups_table} WHERE id IN ({$placeholders}) ORDER BY name ASC",
            ...$group_ids
        )
    );
    $target_group_names = is_array( $target_group_names ) ? array_map( 'trim', $target_group_names ) : [];
    if ( $eligible_count > 0 ) {
        $preview_recipient_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT u.ID FROM {$wpdb->users} u
                 INNER JOIN {$gm_table} gm ON u.ID = gm.user_id
                 WHERE gm.group_id IN ({$placeholders}) AND u.user_email != '' AND u.user_email IS NOT NULL
                 LIMIT 1",
                ...$group_ids
            )
        );
    }
}

$preview_body = $message->body ? $message->body : __( '(Brak treści)', 'openvote' );
if ( function_exists( 'openvote_replace_message_placeholders' ) && $preview_body !== __( '(Brak treści)', 'openvote' ) ) {
    $replaced = openvote_replace_message_placeholders(
        $preview_body,
        $message->title ?? '',
        $preview_recipient_id,
        $message,
        $target_group_names
    );
    $preview_body = $replaced['body'];
}
?>
<div class="wrap">
    <h1><?php printf( esc_html__( 'Wysyłka: %s', 'openvote' ), esc_html( $message->title ) ); ?></h1>

    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-communication' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Lista wysyłek', 'openvote' ); ?>
    </a>
    <hr class="wp-header-end">

    <h2 style="margin-top:24px;"><?php esc_html_e( 'Podgląd przed wysyłką', 'openvote' ); ?></h2>
    <div class="openvote-message-preview" style="max-width:720px;padding:16px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;margin-bottom:24px;">
        <div style="font-size:14px;line-height:1.6;white-space:pre-line;">
            <?php echo wp_kses_post( $preview_body ); ?>
        </div>
    </div>

    <?php if ( current_user_can( 'edit_others_posts' ) || Openvote_Admin::user_can_access_coordinators() ) :
        $all_sent = $mq_exists && $cnt_pending === 0 && $cnt_failed === 0 && $cnt_sent > 0;
        $no_recipients = ( $eligible_count <= 0 );
        if ( $all_sent ) {
            $btn_label = __( 'Wszystkie wiadomości wysłane', 'openvote' );
        } elseif ( $mq_exists ) {
            $btn_label = __( 'Wyślij wiadomości e-mailem', 'openvote' );
        } else {
            $n = $eligible_count;
            if ( $n === 0 ) {
                $btn_label = __( 'Wyślij wiadomości', 'openvote' );
            } elseif ( $n === 1 ) {
                $btn_label = __( 'Wyślij 1 wiadomość', 'openvote' );
            } elseif ( $n % 10 >= 2 && $n % 10 <= 4 && ( $n < 10 || $n > 21 ) ) {
                $btn_label = sprintf( __( 'Wyślij %d wiadomości', 'openvote' ), $n );
            } else {
                $btn_label = sprintf( __( 'Wyślij %d wiadomości', 'openvote' ), $n );
            }
        }
    ?>
    <div style="margin-bottom:24px;">
        <?php if ( $no_recipients && ! $all_sent ) : ?>
        <p class="description" style="margin:0 0 8px; color:#b32d2e;">
            <?php esc_html_e( 'Brak odbiorców w wybranych grupach (grupy puste lub bez adresu e-mail). Wybierz inne grupy lub dodaj użytkowników z e-mailem.', 'openvote' ); ?>
        </p>
        <?php endif; ?>
        <button type="button"
                class="button<?php echo ( $all_sent || $no_recipients ) ? '' : ' button-primary'; ?>"
                id="openvote-send-messages-btn"
                data-message-id="<?php echo (int) $message->id; ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
                <?php disabled( $all_sent || $no_recipients ); ?>
                style="<?php echo ( $all_sent || $no_recipients ) ? 'opacity:.55;cursor:default;' : ''; ?>">
            <?php echo esc_html( $btn_label ); ?>
        </button>
        <?php if ( $all_sent ) : ?>
        <span style="margin-left:12px;color:#0a6b2e;font-weight:500;font-size:13px;">
            &#10003; <?php esc_html_e( 'Wszystkie wiadomości zostały wysłane.', 'openvote' ); ?>
        </span>
        <?php endif; ?>
        <span id="openvote-messages-status" style="margin-left:14px;font-weight:500;font-size:13px;"></span>
        <div id="openvote-messages-progress" style="display:none;max-width:560px;margin:10px 0 0;"></div>
        <div id="openvote-messages-limit-msg" style="display:none;margin-top:12px;padding:10px 12px;background:#fcf0f1;border-left:4px solid #c00;max-width:560px;"></div>
    </div>
    <?php endif; ?>

    <h2 style="margin-top:0;"><?php esc_html_e( 'Status wysyłki', 'openvote' ); ?></h2>

    <?php if ( ! $mq_exists ) : ?>
        <div class="notice notice-info inline" style="max-width:580px;">
            <p><?php esc_html_e( 'Żadne wiadomości nie zostały jeszcze wysłane dla tej wysyłki.', 'openvote' ); ?></p>
        </div>
    <?php else : ?>

    <table class="widefat striped" style="max-width:580px;margin-bottom:24px;">
        <tbody>
            <tr>
                <td style="width:260px;font-weight:500;"><?php esc_html_e( 'Łącznie w kolejce', 'openvote' ); ?></td>
                <td style="font-weight:700;font-size:15px;"><?php echo (int) $cnt_total; ?></td>
            </tr>
            <tr>
                <td style="font-weight:500;"><?php esc_html_e( 'Wysłanych pomyślnie', 'openvote' ); ?></td>
                <td style="color:#0a6b2e;font-weight:700;font-size:15px;">
                    <?php echo (int) $cnt_sent; ?>
                    <?php if ( $last_sent ) : ?>
                        <small style="color:#555;font-weight:400;font-size:12px;margin-left:8px;">
                            <?php printf( esc_html__( 'ostatnia: %s', 'openvote' ), esc_html( $last_sent ) ); ?>
                        </small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight:500;"><?php esc_html_e( 'Oczekujących (nie wysłanych)', 'openvote' ); ?></td>
                <td style="color:<?php echo $cnt_pending > 0 ? '#c0730a' : '#555'; ?>;font-weight:700;font-size:15px;">
                    <?php echo (int) $cnt_pending; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight:500;"><?php esc_html_e( 'Błędy wysyłki', 'openvote' ); ?></td>
                <td style="color:<?php echo $cnt_failed > 0 ? '#c00' : '#555'; ?>;font-weight:700;font-size:15px;">
                    <?php echo (int) $cnt_failed; ?>
                    <?php if ( $cnt_failed > 0 ) : ?>
                        <small style="margin-left:8px;">
                            <button type="button" class="button button-small" id="openvote-toggle-failed-messages">
                                <?php esc_html_e( 'Pokaż szczegóły', 'openvote' ); ?>
                            </button>
                        </small>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if ( $cnt_total > 0 ) :
        $pct_sent = $cnt_total > 0 ? round( $cnt_sent / $cnt_total * 100 ) : 0;
    ?>
    <div style="max-width:580px;margin-bottom:20px;">
        <div style="background:#e0e0e0;border-radius:4px;height:12px;overflow:hidden;">
            <div style="background:#0a6b2e;height:100%;width:<?php echo (int) $pct_sent; ?>%;transition:width .3s;"></div>
        </div>
        <p style="font-size:12px;color:#555;margin-top:4px;">
            <?php printf(
                esc_html__( 'Postęp: %d%% wysłanych (%d z %d)', 'openvote' ),
                $pct_sent, $cnt_sent, $cnt_total
            ); ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if ( $cnt_failed > 0 ) :
        $failed_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT email, name, error_msg, created_at
             FROM {$mq} WHERE message_id = %d AND status = 'failed'
             ORDER BY created_at DESC LIMIT 500",
            $message->id
        ) );
    ?>
    <div id="openvote-failed-messages" style="display:none;max-width:860px;margin-bottom:20px;">
        <h3><?php esc_html_e( 'Lista błędów wysyłki', 'openvote' ); ?></h3>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:220px;"><?php esc_html_e( 'E-mail', 'openvote' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Imię i nazwisko', 'openvote' ); ?></th>
                    <th><?php esc_html_e( 'Komunikat błędu', 'openvote' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Data próby', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $failed_rows as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r->email ); ?></td>
                    <td><?php echo esc_html( $r->name ); ?></td>
                    <td><?php echo esc_html( $r->error_msg ?? '' ); ?></td>
                    <td><?php echo esc_html( $r->created_at ?? '' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    (function(){
        var btn = document.getElementById('openvote-toggle-failed-messages');
        var box = document.getElementById('openvote-failed-messages');
        if ( btn && box ) {
            btn.addEventListener('click', function(){ box.style.display = box.style.display === 'none' ? 'block' : 'none'; });
        }
    })();
    </script>
    <?php endif; ?>

    <?php endif; ?>

    <?php
    $messages_audit_all   = openvote_messages_audit_log_get();
    $audit_per            = 20;
    $audit_total          = count( $messages_audit_all );
    $audit_pages          = $audit_total > 0 ? (int) ceil( $audit_total / $audit_per ) : 1;
    $audit_page           = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
    $audit_page           = min( $audit_page, $audit_pages );
    $audit_offset         = ( $audit_page - 1 ) * $audit_per;
    $audit_entries        = array_slice( $messages_audit_all, $audit_offset, $audit_per );
    $audit_base_url       = add_query_arg( [ 'page' => 'openvote-communication', 'action' => 'send', 'message_id' => $message->id ], admin_url( 'admin.php' ) );
    ?>
    <section class="openvote-messages-audit-log" style="margin-top:32px; max-width:900px;">
        <h2 class="openvote-section-title" style="margin:0 0 8px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Log wysyłki', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Kto i kiedy uruchomił wysyłkę lub wykonał inne działania na wysyłkach. Lista niekasowalna, w celach bezpieczeństwa.', 'openvote' ); ?></p>
        <div class="openvote-audit-log-box" style="background:#1d2327; color:#f0f0f1; padding:12px 16px; border-radius:4px; max-height:220px; overflow-y:auto; font-family:Consolas, Monaco, monospace; font-size:12px; line-height:1.5;">
            <?php
            if ( empty( $audit_entries ) ) {
                echo '<p style="margin:0; color:#a7aaad;">' . esc_html__( 'Brak wpisów.', 'openvote' ) . '</p>';
            } else {
                foreach ( $audit_entries as $e ) {
                    $t     = isset( $e['t'] ) ? $e['t'] : '';
                    $actor = isset( $e['actor'] ) ? $e['actor'] : '—';
                    $line  = isset( $e['line'] ) ? $e['line'] : '';
                    echo '<div style="margin:2px 0;">' . esc_html( $t . ' ' . $actor . ' ' . $line ) . '</div>';
                }
            }
            ?>
        </div>
        <?php if ( $audit_pages > 1 ) : ?>
        <p class="openvote-audit-log-nav" style="margin:8px 0 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span class="displaying-num" style="color:#646970; font-size:13px;">
                <?php echo esc_html( sprintf( __( 'Strona %1$d z %2$d (%3$d wpisów)', 'openvote' ), $audit_page, $audit_pages, $audit_total ) ); ?>
            </span>
            <?php if ( $audit_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $audit_page - 1, $audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Poprzedni', 'openvote' ); ?></a>
            <?php endif; ?>
            <?php if ( $audit_page < $audit_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $audit_page + 1, $audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Następny', 'openvote' ); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </section>

</div>

<?php if ( current_user_can( 'edit_others_posts' ) || Openvote_Admin::user_can_access_coordinators() ) : ?>
<script>
(function(){
    var apiRoot = <?php echo wp_json_encode( esc_url_raw( rest_url( 'openvote/v1' ) ) ); ?>;
    var batchNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
    var messageId = <?php echo (int) $message->id; ?>;
    var btn = document.getElementById('openvote-send-messages-btn');
    var statusEl = document.getElementById('openvote-messages-status');
    var progressEl = document.getElementById('openvote-messages-progress');
    var limitMsgEl = document.getElementById('openvote-messages-limit-msg');
    var emailDelayMs = typeof openvoteBatch !== 'undefined' && openvoteBatch.emailDelay ? openvoteBatch.emailDelay : 300;

    function showLimitExceeded( msg, waitSec, limitType ) {
        if ( limitMsgEl ) {
            limitMsgEl.innerHTML = msg || '';
            limitMsgEl.style.display = msg ? 'block' : 'none';
        }
    }

    var logPanel = null;
    function startOrResume( jobId ) {
        if ( progressEl ) {
            progressEl.style.display = 'block';
            if ( typeof openvoteCreateSyncLogPanel === 'function' && !logPanel ) {
                logPanel = openvoteCreateSyncLogPanel( progressEl );
            }
        }
        if ( typeof openvoteRunBatchJob === 'undefined' ) {
            statusEl.textContent = '<?php esc_html_e( 'Skrypt wysyłki niedostępny.', 'openvote' ); ?>';
            return;
        }
        openvoteRunBatchJob(
            jobId,
            function( job ) {
                if ( logPanel && logPanel.update ) logPanel.update( job );
                var pct = job.total > 0 ? Math.round( job.processed / job.total * 100 ) : 0;
                statusEl.textContent = pct + '% (' + job.processed + ' / ' + job.total + ')';
            },
            function( job ) {
                if ( job && job.total === 0 ) {
                    statusEl.style.color = '#c00';
                    statusEl.textContent = '<?php esc_html_e( 'Pominięto użytkowników nieaktywnych. Wysyłka liczy 0 osób.', 'openvote' ); ?>';
                    if ( btn ) btn.disabled = false;
                    return;
                }
                statusEl.style.color = '#0a6b2e';
                statusEl.textContent = '<?php esc_html_e( 'Wysłano. Odświeżam stronę…', 'openvote' ); ?>';
                setTimeout( function() { window.location.reload(); }, 1500 );
            },
            function( err ) {
                statusEl.style.color = '#c00';
                statusEl.textContent = ( err && err.message ) ? err.message : ( err && typeof err.toString === 'function' ? err.toString() : String( err || '' ) ) || '<?php esc_html_e( 'Błąd.', 'openvote' ); ?>';
                if ( btn ) btn.disabled = false;
            },
            emailDelayMs,
            function( job ) {
                showLimitExceeded( job.limit_message || '', job.wait_seconds || 0, job.limit_type || '' );
            }
        );
    }

    function triggerSend() {
        if ( btn && btn.disabled ) return;
        statusEl.style.color = '#555';
        statusEl.textContent = '<?php esc_html_e( 'Łączenie…', 'openvote' ); ?>';
        fetch( apiRoot + '/messages/' + messageId + '/send', {
            method: 'POST',
            headers: { 'X-WP-Nonce': batchNonce, 'Content-Type': 'application/json' }
        })
        .then( function(r) {
            var ct = r.headers.get('Content-Type') || '';
            if ( ct.indexOf('json') !== -1 ) {
                return r.json().then( function(d){ return { ok: r.ok, data: d }; } );
            }
            return r.text().then( function(t){ return { ok: false, data: { message: t.substring(0,200) } }; } );
        })
        .then( function( result ) {
            if ( result.ok && result.data.job_id ) {
                startOrResume( result.data.job_id );
            } else {
                var d = result.data || {};
                var msg = (d.message || d.code || '<?php esc_html_e( 'Nieznany błąd.', 'openvote' ); ?>') + '';
                var div = document.createElement('div');
                div.innerHTML = msg;
                statusEl.style.color = '#c00';
                statusEl.textContent = div.textContent || div.innerText || msg;
                btn.disabled = false;
            }
        })
        .catch( function(e) {
            statusEl.style.color = '#c00';
            statusEl.textContent = e.message;
            if ( btn ) btn.disabled = false;
        });
    }

    if ( btn ) btn.addEventListener( 'click', triggerSend );
})();
</script>
<?php endif; ?>
