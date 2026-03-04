<?php
defined( 'ABSPATH' ) || exit;

/** @var object $poll */

global $wpdb;
$eq = $wpdb->prefix . 'openvote_email_queue';

$eq_stats = $wpdb->get_results( $wpdb->prepare(
    "SELECT status, COUNT(*) AS cnt, MAX(sent_at) AS last_sent
     FROM {$eq} WHERE poll_id = %d GROUP BY status",
    $poll->id
) );

$cnt_sent    = 0;
$cnt_failed  = 0;
$cnt_pending = 0;
$last_sent   = null;
$eq_exists   = false;

foreach ( $eq_stats as $row ) {
    $eq_exists = true;
    if ( 'sent'    === $row->status ) { $cnt_sent    = (int) $row->cnt; $last_sent = $row->last_sent; }
    if ( 'failed'  === $row->status ) { $cnt_failed  = (int) $row->cnt; }
    if ( 'pending' === $row->status ) { $cnt_pending = (int) $row->cnt; }
}
$cnt_total = $cnt_sent + $cnt_failed + $cnt_pending;

// Liczba osób uprawnionych do zaproszenia (członkowie grup docelowych z e-mailem, lub wszyscy).
$eligible_count = 0;
$target_groups   = $poll->target_groups ? json_decode( $poll->target_groups, true ) : [];
if ( ! empty( $target_groups ) && is_array( $target_groups ) ) {
    $gm_table  = $wpdb->prefix . 'openvote_group_members';
    $ids_clean = array_map( 'absint', $target_groups );
    $placeholders = implode( ',', array_fill( 0, count( $ids_clean ), '%d' ) );
    $eligible_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
             INNER JOIN {$gm_table} gm ON u.ID = gm.user_id
             WHERE gm.group_id IN ({$placeholders}) AND u.user_email != '' AND u.user_email IS NOT NULL",
            ...$ids_clean
        )
    );
} else {
    $eligible_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email != '' AND user_email IS NOT NULL"
    );
}

$status_labels = [
    'draft'  => __( 'Szkic', 'openvote' ),
    'open'   => __( 'Rozpoczęte', 'openvote' ),
    'closed' => __( 'Zakończone', 'openvote' ),
];

// Szablony zaproszenia (HTML i tekst) do kopiowania na media społecznościowe.
$invitation_html  = openvote_render_email_template( openvote_get_email_body_html_template(), $poll, 'html' );
$invitation_plain = openvote_render_email_template( openvote_get_email_body_plain_template(), $poll, 'plain' );
?>
<div class="wrap">
    <h1><?php printf( esc_html__( 'Zaproszenia: %s', 'openvote' ), esc_html( $poll->title ) ); ?></h1>

    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Wszystkie głosowania', 'openvote' ); ?>
    </a>
    <?php if ( 'closed' === $poll->status ) : ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=results&poll_id=' . $poll->id ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Wyniki głosowania', 'openvote' ); ?>
    </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <p style="color:#555;margin-bottom:20px;">
        <?php printf(
            esc_html__( 'Status głosowania: %s', 'openvote' ),
            '<strong>' . esc_html( $status_labels[ $poll->status ] ?? $poll->status ) . '</strong>'
        ); ?>
    </p>

    <div class="notice notice-info inline" style="max-width:580px;margin-bottom:20px;">
        <p>
            <?php
            printf(
                /* translators: %s: full URL of the voting page (e.g. https://example.com/glosuj/) */
                esc_html__( 'Aby zagłosować, należy wejść na stronę %s (zgodnie z ustawieniami w Konfiguracji).', 'openvote' ),
                '<strong><a href="' . esc_url( openvote_get_vote_page_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( openvote_get_vote_page_url() ) . '</a></strong>'
            );
            ?>
        </p>
    </div>

    <?php if ( ( current_user_can( 'edit_others_posts' ) || current_user_can( 'publish_posts' ) || Openvote_Admin::user_can_access_coordinators() ) && 'open' === ( $poll->status ?? '' ) ) :
        $all_sent = $eq_exists && $cnt_pending === 0 && $cnt_failed === 0 && $cnt_sent > 0;
        if ( $all_sent ) {
            $btn_label = __( 'Wszyscy powiadomieni', 'openvote' );
        } elseif ( $eq_exists ) {
            $btn_label = __( 'Wyślij zaproszenia e-mailem', 'openvote' );
        } else {
            $n = $eligible_count;
            if ( $n === 1 ) {
                $btn_label = __( 'Wyślij 1 zaproszenie do głosowania', 'openvote' );
            } elseif ( $n % 10 >= 2 && $n % 10 <= 4 && ( $n < 10 || $n > 21 ) ) {
                $btn_label = sprintf( __( 'Wyślij %d zaproszenia do głosowania', 'openvote' ), $n );
            } else {
                $btn_label = sprintf( __( 'Wyślij %d zaproszeń do głosowania', 'openvote' ), $n );
            }
        }
    ?>
    <div style="margin-bottom:24px;">
        <button type="button"
                class="button<?php echo $all_sent ? '' : ' button-primary'; ?>"
                id="openvote-send-invitations-btn"
                data-poll-id="<?php echo (int) $poll->id; ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'openvote_send_invitations' ) ); ?>"
                <?php disabled( $all_sent ); ?>
                style="<?php echo $all_sent ? 'opacity:.55;cursor:default;' : ''; ?>">
            <?php echo esc_html( $btn_label ); ?>
        </button>
        <?php if ( $all_sent ) : ?>
        <span style="margin-left:12px;color:#0a6b2e;font-weight:500;font-size:13px;">
            &#10003; <?php esc_html_e( 'Wszystkie zaproszenia zostały wysłane.', 'openvote' ); ?>
        </span>
        <?php endif; ?>
        <span id="openvote-invitations-status" style="margin-left:14px;font-weight:500;font-size:13px;"></span>
        <div id="openvote-invitations-progress" style="display:none;max-width:560px;margin:10px 0 0;"></div>
        <div id="openvote-invitations-limit-msg" style="display:none;margin-top:12px;padding:10px 12px;background:#fcf0f1;border-left:4px solid #c00;max-width:560px;"></div>
    </div>
    <?php endif; ?>

    <?php if ( current_user_can( 'edit_others_posts' ) || current_user_can( 'publish_posts' ) || Openvote_Admin::user_can_access_coordinators() ) : ?>
    <div class="openvote-invitations-copy-box" style="max-width:560px;margin-bottom:24px;padding:16px 20px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 1px 1px rgba(0,0,0,.04);">
        <p style="margin:0 0 12px;font-size:13px;color:#1d2327;line-height:1.5;">
            <?php esc_html_e( 'Możesz skopiować treść zaproszenia do schowka i wkleić je samodzielnie na mediach społecznościowych lub w innym kanale (np. komunikator, forum).', 'openvote' ); ?>
        </p>
        <p style="margin:0 0 14px;font-size:12px;color:#50575e;">
            <?php esc_html_e( 'Wybierz wersję HTML (do wklejenia w edytorze obsługującym formatowanie) lub tekstową (zwykły tekst).', 'openvote' ); ?>
        </p>
        <button type="button" class="button" id="openvote-copy-invitation-html">
            <?php esc_html_e( 'Wyślij samodzielnie (HTML)', 'openvote' ); ?>
        </button>
        <button type="button" class="button" id="openvote-copy-invitation-plain" style="margin-left:8px;">
            <?php esc_html_e( 'Wyślij samodzielnie (Text)', 'openvote' ); ?>
        </button>
        <span id="openvote-copy-feedback" style="margin-left:12px;font-size:13px;color:#0a6b2e;font-weight:500;display:none;"></span>
    </div>
    <script>
    (function(){
        var invitationHtml  = <?php echo wp_json_encode( $invitation_html ); ?>;
        var invitationPlain = <?php echo wp_json_encode( $invitation_plain ); ?>;
        var feedbackEl = document.getElementById('openvote-copy-feedback');
        var copyMsg = <?php echo wp_json_encode( __( 'Skopiowano do schowka.', 'openvote' ) ); ?>;
        function showFeedback() {
            if ( ! feedbackEl ) return;
            feedbackEl.textContent = copyMsg;
            feedbackEl.style.display = 'inline';
            setTimeout(function(){ feedbackEl.style.display = 'none'; }, 2500);
        }
        var btnHtml = document.getElementById('openvote-copy-invitation-html');
        var btnPlain = document.getElementById('openvote-copy-invitation-plain');
        if ( btnHtml ) {
            btnHtml.addEventListener('click', function() {
                if ( navigator.clipboard && navigator.clipboard.writeText ) {
                    navigator.clipboard.writeText( invitationHtml ).then( showFeedback ).catch(function() { feedbackEl.textContent = ''; });
                } else {
                    feedbackEl.textContent = <?php echo wp_json_encode( __( 'Skopiuj ręcznie (Ctrl+C).', 'openvote' ) ); ?>;
                    feedbackEl.style.display = 'inline';
                }
            });
        }
        if ( btnPlain ) {
            btnPlain.addEventListener('click', function() {
                if ( navigator.clipboard && navigator.clipboard.writeText ) {
                    navigator.clipboard.writeText( invitationPlain ).then( showFeedback ).catch(function() { feedbackEl.textContent = ''; });
                } else {
                    feedbackEl.textContent = <?php echo wp_json_encode( __( 'Skopiuj ręcznie (Ctrl+C).', 'openvote' ) ); ?>;
                    feedbackEl.style.display = 'inline';
                }
            });
        }
    })();
    </script>
    <?php endif; ?>

    <h2 style="margin-top:0;"><?php esc_html_e( 'Status wysyłki', 'openvote' ); ?></h2>

    <?php if ( ! $eq_exists ) : ?>
        <div class="notice notice-info inline" style="max-width:580px;">
            <p><?php esc_html_e( 'Żadne zaproszenia nie zostały jeszcze wysłane dla tego głosowania.', 'openvote' ); ?></p>
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
                    <?php if ( $cnt_pending > 0 ) : ?>
                        <small style="color:#c0730a;font-weight:400;font-size:12px;margin-left:8px;">
                            <?php esc_html_e( '— użyj przycisku powyżej, aby wznowić', 'openvote' ); ?>
                        </small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight:500;"><?php esc_html_e( 'Błędy wysyłki', 'openvote' ); ?></td>
                <td style="color:<?php echo $cnt_failed > 0 ? '#c00' : '#555'; ?>;font-weight:700;font-size:15px;">
                    <?php echo (int) $cnt_failed; ?>
                    <?php if ( $cnt_failed > 0 ) : ?>
                        <small style="margin-left:8px;">
                            <button type="button" class="button button-small" id="openvote-toggle-failed">
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
             FROM {$eq} WHERE poll_id = %d AND status = 'failed'
             ORDER BY created_at DESC LIMIT 500",
            $poll->id
        ) );
    ?>
    <div id="openvote-failed-emails" style="display:none;max-width:860px;margin-bottom:20px;">
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
                <?php foreach ( $failed_rows as $fr ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $fr->email ); ?></code></td>
                    <td><?php echo esc_html( $fr->name ); ?></td>
                    <td><small style="color:#c00;"><?php echo esc_html( $fr->error_msg ?: '—' ); ?></small></td>
                    <td><small><?php echo esc_html( $fr->created_at ); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    (function(){
        var btn = document.getElementById('openvote-toggle-failed');
        var box = document.getElementById('openvote-failed-emails');
        if ( btn && box ) {
            btn.addEventListener('click', function() {
                var hidden = box.style.display === 'none';
                box.style.display = hidden ? '' : 'none';
                btn.textContent = hidden
                    ? '<?php echo esc_js( __( 'Ukryj szczegóły', 'openvote' ) ); ?>'
                    : '<?php echo esc_js( __( 'Pokaż szczegóły', 'openvote' ) ); ?>';
            });
        }
    })();
    </script>
    <?php endif; // cnt_failed ?>

    <?php endif; // eq_exists ?>

    <?php if ( ( current_user_can( 'edit_others_posts' ) || current_user_can( 'publish_posts' ) || Openvote_Admin::user_can_access_coordinators() ) && 'open' === ( $poll->status ?? '' ) ) : ?>
    <script>
    (function(){
        var btn       = document.getElementById('openvote-send-invitations-btn');
        var statusEl  = document.getElementById('openvote-invitations-status');
        var progressEl = document.getElementById('openvote-invitations-progress');
        var limitMsgEl = document.getElementById('openvote-invitations-limit-msg');
        var pollId    = btn ? btn.dataset.pollId : '';
        var apiRoot   = '<?php echo esc_js( esc_url_raw( rest_url( 'openvote/v1' ) ) ); ?>';
        var batchNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
        var emailDelayMs = <?php echo (int) ( openvote_get_email_batch_delay() * 1000 ); ?>;

        function showLimitExceeded( message, waitSeconds, limitType ) {
            progressEl.style.display = 'none';
            statusEl.style.color = '#c00';
            statusEl.textContent = message;
            var html = '<p style="margin:0 0 8px;">' + ( message || '' ).replace( /</g, '&lt;' ) + '</p>';
            if ( waitSeconds > 0 && limitType !== 'day' ) {
                var waitMin = Math.ceil( waitSeconds / 60 );
                html += '<p style="margin:0;font-size:12px;color:#555;"><?php echo esc_js( __( 'Wznowienie za', 'openvote' ) ); ?> ' + waitMin + ' <?php echo esc_js( __( 'min', 'openvote' ) ); ?>.</p>';
            }
            if ( limitType === 'day' ) {
                html += '<button type="button" class="button button-primary" id="openvote-schedule-resume-btn" style="margin-top:8px;"><?php echo esc_js( __( 'Zaplanuj automatyczne wznowienie', 'openvote' ) ); ?></button>';
            }
            limitMsgEl.innerHTML = html;
            limitMsgEl.style.display = '';
            btn.disabled = false;
            btn.style.opacity = '';
            btn.textContent = '<?php echo esc_js( __( 'Wyślij zaproszenia e-mailem', 'openvote' ) ); ?>';
            if ( limitType === 'day' ) {
                var scheduleBtn = document.getElementById('openvote-schedule-resume-btn');
                if ( scheduleBtn ) {
                    scheduleBtn.addEventListener('click', function() {
                        scheduleBtn.disabled = true;
                        scheduleBtn.textContent = '<?php echo esc_js( __( 'Zapisywanie…', 'openvote' ) ); ?>';
                        fetch( apiRoot + '/polls/' + pollId + '/schedule-email-resume', { method: 'POST', headers: { 'X-WP-Nonce': batchNonce, 'Content-Type': 'application/json' } } )
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if ( data && ( data.success !== false ) ) {
                                    limitMsgEl.innerHTML = '<p style="margin:0;color:#0a6b2e;">&#10003; <?php echo esc_js( __( 'Zaplanowano. E-maile zostaną automatycznie wznowione o północy (strefa głosowań).', 'openvote' ) ); ?></p>';
                                } else {
                                    limitMsgEl.innerHTML = '<p style="margin:0;color:#c00;">' + ( ( data && data.message ) ? data.message : '<?php echo esc_js( __( 'Błąd zapisu.', 'openvote' ) ); ?>' ) + '</p>';
                                    scheduleBtn.disabled = false;
                                    scheduleBtn.textContent = '<?php echo esc_js( __( 'Zaplanuj automatyczne wznowienie', 'openvote' ) ); ?>';
                                }
                            })
                            .catch(function() {
                                limitMsgEl.innerHTML = '<p style="margin:0;color:#c00;"><?php echo esc_js( __( 'Błąd połączenia.', 'openvote' ) ); ?></p>';
                                scheduleBtn.disabled = false;
                                scheduleBtn.textContent = '<?php echo esc_js( __( 'Zaplanuj automatyczne wznowienie', 'openvote' ) ); ?>';
                            });
                    });
                }
            }
        }

        function startOrResume( jobId ) {
            if ( ! jobId ) return;
            if ( typeof openvoteRunBatchJob === 'undefined' ) return;
            limitMsgEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = '<?php esc_html_e( 'Wysyłanie…', 'openvote' ); ?>';
            openvoteRunBatchJob(
                jobId,
                function( processed, total ) {
                    progressEl.style.display = '';
                    progressEl.innerHTML = '<progress style="width:100%;max-width:540px" value="' + processed + '" max="' + ( total || 1 ) + '"></progress>'
                        + '<br><small>' + processed + ' / ' + ( total || '?' ) + ' <?php esc_html_e( 'wysłanych', 'openvote' ); ?></small>';
                    statusEl.textContent = processed + ' / ' + ( total || '?' );
                },
                function() {
                    if ( typeof openvoteClearJobId !== 'undefined' ) openvoteClearJobId( pollId );
                    statusEl.style.color = '#0a6b2e';
                    statusEl.textContent = '<?php esc_html_e( 'Zakończono. Odświeżam…', 'openvote' ); ?>';
                    var cleanUrl = location.href
                        .replace( /&autostart=1/, '' )
                        .replace( /\?autostart=1&/, '?' )
                        .replace( /\?autostart=1$/, '' );
                    setTimeout( function(){ location.href = cleanUrl; }, 1200 );
                },
                function( err ) {
                    if ( typeof openvoteClearJobId !== 'undefined' ) openvoteClearJobId( pollId );
                    statusEl.style.color = '#c00';
                    statusEl.textContent = '<?php esc_html_e( 'Błąd:', 'openvote' ); ?> ' + ( err.message || err );
                    btn.disabled = false;
                    btn.style.opacity = '';
                    btn.textContent = '<?php esc_html_e( 'Wyślij zaproszenia e-mailem', 'openvote' ); ?>';
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
            fetch( apiRoot + '/polls/' + pollId + '/send-invitations', {
                method: 'POST',
                headers: { 'X-WP-Nonce': batchNonce, 'Content-Type': 'application/json' }
            })
            .then( function(r) {
                var ct = r.headers.get('Content-Type') || '';
                if ( ct.indexOf('json') !== -1 ) {
                    return r.json().then( function(d){ return { ok: r.ok, data: d }; } );
                }
                return r.text().then( function(t){ return { ok: false, data: { message: 'Odpowiedź serwera: ' + t.substring(0,200) } }; } );
            })
            .then( function( result ) {
                if ( result.ok && result.data.job_id ) {
                    if ( typeof openvoteSaveJobId !== 'undefined' ) openvoteSaveJobId( pollId, result.data.job_id );
                    startOrResume( result.data.job_id );
                } else {
                    var d = result.data || {};
                    var isRateLimit = ( ! result.ok && ( d.code === 'rate_limit_exceeded' || ( d.data && d.data.status === 429 ) ) );
                    if ( isRateLimit && d.message ) {
                        var extra = d.data || {};
                        var waitSec = extra.wait_seconds ? extra.wait_seconds : 0;
                        var limitType = extra.limit_type ? extra.limit_type : '';
                        showLimitExceeded( d.message, waitSec, limitType );
                        statusEl.style.color = '#c00';
                        statusEl.textContent = d.message;
                    } else {
                        statusEl.style.color = '#c00';
                        statusEl.textContent = d.message || d.code || '<?php esc_html_e( 'Nieznany błąd.', 'openvote' ); ?>';
                        btn.disabled = false;
                    }
                }
            })
            .catch( function(e) {
                statusEl.style.color = '#c00';
                statusEl.textContent = e.message;
            });
        }

        if ( btn ) {
            var savedJob = ( typeof openvoteGetSavedJobId !== 'undefined' ) ? openvoteGetSavedJobId( pollId ) : null;
            if ( savedJob ) {
                statusEl.textContent = '<?php esc_html_e( 'Wznawianie przerwanej wysyłki…', 'openvote' ); ?>';
                startOrResume( savedJob );
            }
            btn.addEventListener( 'click', triggerSend );
        }
    })();
    </script>
    <?php endif; ?>

</div>
