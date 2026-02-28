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

$status_labels = [
    'draft'  => __( 'Szkic', 'openvote' ),
    'open'   => __( 'Rozpoczęte', 'openvote' ),
    'closed' => __( 'Zakończone', 'openvote' ),
];
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

    <?php if ( current_user_can( 'manage_options' ) && 'open' === ( $poll->status ?? '' ) ) :
        $all_sent   = $eq_exists && $cnt_pending === 0 && $cnt_failed === 0 && $cnt_sent > 0;
        $btn_label  = $all_sent
            ? __( 'Wszyscy powiadomieni', 'openvote' )
            : ( $eq_exists ? __( 'Wyślij ponownie', 'openvote' ) : __( 'Wyślij zaproszenia', 'openvote' ) );
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
    </div>
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

    <?php if ( current_user_can( 'manage_options' ) && 'open' === ( $poll->status ?? '' ) ) : ?>
    <script>
    (function(){
        var btn       = document.getElementById('openvote-send-invitations-btn');
        var statusEl  = document.getElementById('openvote-invitations-status');
        var progressEl = document.getElementById('openvote-invitations-progress');
        var pollId    = btn ? btn.dataset.pollId : '';
        // URL i nonce generowane bezpośrednio z PHP — niezależne od window.openvoteBatch
        var apiRoot   = '<?php echo esc_js( esc_url_raw( rest_url( 'openvote/v1' ) ) ); ?>';
        var batchNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
        var emailDelayMs = <?php echo (int) ( openvote_get_email_batch_delay() * 1000 ); ?>;

        function startOrResume( jobId ) {
            if ( ! jobId ) return;
            if ( typeof openvoteRunBatchJob === 'undefined' ) return;
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
                    // Zawsze usuwamy ?autostart=1 z URL — zapobiega nieskończonej pętli.
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
                    btn.textContent = '<?php esc_html_e( 'Wyślij ponownie', 'openvote' ); ?>';
                },
                emailDelayMs
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
                    statusEl.style.color = '#c00';
                    statusEl.textContent = result.data.message || result.data.code || '<?php esc_html_e( 'Nieznany błąd.', 'openvote' ); ?>';
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
            } else if ( <?php echo isset( $_GET['autostart'] ) ? 'true' : 'false'; ?> ) {
                // Auto-start po wystartowaniu głosowania z włączonymi powiadomieniami
                statusEl.textContent = '<?php esc_html_e( 'Autostart wysyłki zaproszeń…', 'openvote' ); ?>';
                setTimeout( triggerSend, 800 );
            }

            btn.addEventListener('click', triggerSend);
        }
    })();
    </script>
    <?php endif; ?>

</div>
