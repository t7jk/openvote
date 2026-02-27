<?php
defined( 'ABSPATH' ) || exit;

/** @var object $poll */
/** @var array  $results */
/** @var array  $voters */

$total_eligible = (int) $results['total_eligible'];
$total_voters   = (int) $results['total_voters'];
$non_voters     = (int) $results['non_voters'];
$pct_voted   = $total_eligible > 0 ? round( $total_voters / $total_eligible * 100, 1 ) : 0;
$pct_absent  = $total_eligible > 0 ? round( $non_voters  / $total_eligible * 100, 1 ) : 0;
?>
<div class="wrap">
    <h1><?php printf( esc_html__( 'Wyniki: %s', 'evoting' ), esc_html( $poll->title ) ); ?></h1>

    <?php if ( 'draft' === ( $poll->status ?? '' ) ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $poll->id ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Edytuj głosowanie', 'evoting' ); ?>
        </a>
    <?php endif; ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Wszystkie głosowania', 'evoting' ); ?>
    </a>
    <?php if ( 'open' === ( $poll->status ?? '' ) ) : ?>
        <button type="button" class="page-title-action button-primary" id="evoting-send-invitations-btn"
                data-poll-id="<?php echo (int) $poll->id; ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'evoting_send_invitations' ) ); ?>">
            <?php esc_html_e( 'Wyślij zaproszenia', 'evoting' ); ?>
        </button>
        <span id="evoting-invitations-status" style="margin-left:12px;font-weight:500;font-size:13px;"></span>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php /* Kontener paska postępu wysyłki */ ?>
    <div id="evoting-invitations-progress" style="display:none;max-width:600px;margin:12px 0 0;"></div>

    <?php if ( 'open' === ( $poll->status ?? '' ) ) : ?>
    <script>
    (function(){
        var btn      = document.getElementById('evoting-send-invitations-btn');
        var statusEl = document.getElementById('evoting-invitations-status');
        var progressEl = document.getElementById('evoting-invitations-progress');
        var pollId   = btn ? btn.dataset.pollId : '';
        var nonce    = btn ? btn.dataset.nonce  : '';
        var apiRoot  = window.evotingBatch?.apiRoot || '/wp-json/evoting/v1';
        var batchNonce = window.evotingBatch?.nonce || '';
        var emailDelayMs = (window.evotingBatch?.emailDelay || 3) * 1000;

        function startOrResume(jobId) {
            if (btn) btn.disabled = true;
            progressEl.style.display = '';
            statusEl.textContent = '<?php echo esc_js( __( 'Wysyłanie…', 'evoting' ) ); ?>';
            statusEl.style.color = '#666';

            evotingRunBatchJob(
                jobId,
                function(processed, total, pct) {
                    evotingRenderProgress(progressEl, processed, total, pct);
                    statusEl.textContent = processed + ' / ' + total;
                },
                function(job) {
                    statusEl.textContent = '<?php echo esc_js( __( 'Wysyłka zakończona!', 'evoting' ) ); ?>';
                    statusEl.style.color = 'green';
                    evotingClearJobId(pollId);
                    if (btn) { btn.textContent = '<?php echo esc_js( __( 'Wyślij ponownie', 'evoting' ) ); ?>'; btn.disabled = false; }
                },
                function(err) {
                    statusEl.textContent = '<?php echo esc_js( __( 'Błąd:', 'evoting' ) ); ?> ' + err.message;
                    statusEl.style.color = '#c00';
                    if (btn) btn.disabled = false;
                },
                emailDelayMs
            );
        }

        // Sprawdź czy jest wznowienie z localStorage.
        var savedJob = evotingGetSavedJobId(pollId);
        if (savedJob && btn) {
            btn.textContent = '<?php echo esc_js( __( 'Wznów wysyłkę', 'evoting' ) ); ?>';
            btn.style.background = '#f0a500';
            btn.addEventListener('click', function() {
                startOrResume(savedJob);
            }, { once: true });
        }

        if (btn && !savedJob) {
            btn.addEventListener('click', function() {
                fetch(apiRoot + '/polls/<?php echo (int) $poll->id; ?>/send-invitations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': batchNonce,
                    },
                    body: JSON.stringify({ nonce: nonce }),
                })
                .then(r => r.json())
                .then(function(data) {
                    if (!data.job_id) throw new Error(data.message || 'Brak job_id');
                    evotingSaveJobId(pollId, data.job_id);
                    startOrResume(data.job_id);
                })
                .catch(function(err) {
                    statusEl.textContent = '<?php echo esc_js( __( 'Błąd startu:', 'evoting' ) ); ?> ' + err.message;
                    statusEl.style.color = '#c00';
                    if (btn) btn.disabled = false;
                });
            });
        }
    })();
    </script>
    <?php endif; ?>

    <?php // ── Participation summary ── ?>
    <div class="evoting-results-summary">
        <h2><?php esc_html_e( 'Frekwencja', 'evoting' ); ?></h2>

        <p>
            <strong><?php esc_html_e( 'Status:', 'evoting' ); ?></strong> <?php echo esc_html( $poll->status ); ?>
            &nbsp;|&nbsp;
            <strong><?php esc_html_e( 'Okres:', 'evoting' ); ?></strong>
            <?php echo esc_html( $poll->date_start . ' — ' . $poll->date_end ); ?>
        </p>

        <table class="evoting-freq-table">
            <tbody>
                <tr>
                    <td class="evoting-freq-label"><?php esc_html_e( 'Uprawnionych do głosowania', 'evoting' ); ?></td>
                    <td class="evoting-freq-count"><?php echo esc_html( $total_eligible ); ?></td>
                    <td class="evoting-freq-pct">100%</td>
                    <td class="evoting-freq-bar-cell">
                        <div class="evoting-bar evoting-bar--eligible" style="width:100%"></div>
                    </td>
                </tr>
                <tr>
                    <td class="evoting-freq-label"><?php esc_html_e( 'Uczestniczyło w głosowaniu', 'evoting' ); ?></td>
                    <td class="evoting-freq-count"><?php echo esc_html( $total_voters ); ?></td>
                    <td class="evoting-freq-pct"><?php echo esc_html( $pct_voted ); ?>%</td>
                    <td class="evoting-freq-bar-cell">
                        <div class="evoting-bar evoting-bar--voted" style="width:<?php echo esc_attr( $pct_voted ); ?>%"></div>
                    </td>
                </tr>
                <tr>
                    <td class="evoting-freq-label"><?php esc_html_e( 'Nie uczestniczyło (liczone jako abstencja)', 'evoting' ); ?></td>
                    <td class="evoting-freq-count"><?php echo esc_html( $non_voters ); ?></td>
                    <td class="evoting-freq-pct"><?php echo esc_html( $pct_absent ); ?>%</td>
                    <td class="evoting-freq-bar-cell">
                        <div class="evoting-bar evoting-bar--absent" style="width:<?php echo esc_attr( $pct_absent ); ?>%"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php // ── Per-question results ── ?>
    <?php if ( ! empty( $results['questions'] ) ) : ?>
        <h2><?php esc_html_e( 'Wyniki pytań', 'evoting' ); ?></h2>

        <?php foreach ( $results['questions'] as $i => $q ) : ?>
            <div class="evoting-result-question">
                <h3><?php echo esc_html( ( $i + 1 ) . '. ' . $q['question_text'] ); ?></h3>

                <table class="widefat fixed" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Odpowiedź', 'evoting' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Głosy', 'evoting' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Procent', 'evoting' ); ?></th>
                            <th><?php esc_html_e( 'Wykres', 'evoting' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $q['answers'] as $ai => $answer ) :
                            $bar_class = $answer['is_abstain']
                                ? 'evoting-bar--wstrzymuje'
                                : ( 0 === $ai ? 'evoting-bar--za' : 'evoting-bar--przeciw' );
                        ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( $answer['text'] ); ?>
                                    <?php if ( $answer['is_abstain'] ) : ?>
                                        <em style="color:#999;font-size:11px;"><?php esc_html_e( '(inc. brak głosu)', 'evoting' ); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $answer['count'] ); ?></td>
                                <td><?php echo esc_html( $answer['pct'] ); ?>%</td>
                                <td>
                                    <div class="evoting-bar <?php echo esc_attr( $bar_class ); ?>"
                                         style="width:<?php echo esc_attr( $answer['pct'] ); ?>%"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?php esc_html_e( 'Brak pytań w tym głosowaniu.', 'evoting' ); ?></p>
    <?php endif; ?>

    <?php // ── Voter list (per-vote: jawnie = dane, anonimowo = Anonimowy) ── ?>
    <?php if ( ! empty( $voters ) ) :
        $voters_total = count( $voters );
        $page_size    = 100;
    ?>
        <h2><?php printf( esc_html__( 'Lista głosujących (%d)', 'evoting' ), $voters_total ); ?></h2>
        <p class="description"><?php esc_html_e( 'Widoczne: imię i nazwisko oraz zanonimizowany adres e-mail. Pozostałe dane są utajnione.', 'evoting' ); ?></p>
        <table class="widefat fixed striped evoting-paginated-table" id="evoting-voters-table" style="max-width:700px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Imię i Nazwisko', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'E-mail (zanonimizowany)', 'evoting' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Data głosowania', 'evoting' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $voters as $idx => $voter ) : ?>
                    <tr<?php echo $idx >= $page_size ? ' class="evoting-row-hidden" style="display:none;"' : ''; ?>>
                        <td><?php echo esc_html( $voter['name'] ); ?></td>
                        <td><code><?php echo esc_html( $voter['email_anon'] ); ?></code></td>
                        <td><?php echo esc_html( $voter['voted_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ( $voters_total > $page_size ) : ?>
            <p style="margin-top:8px;">
                <button type="button" class="button evoting-load-more" data-table="evoting-voters-table" data-page-size="<?php echo (int) $page_size; ?>">
                    <?php printf( esc_html__( 'Załaduj więcej (pokazano %1$d z %2$d)', 'evoting' ), min( $page_size, $voters_total ), $voters_total ); ?>
                </button>
            </p>
        <?php endif; ?>
    <?php else : ?>
        <p class="description"><?php esc_html_e( 'Nikt jeszcze nie głosował.', 'evoting' ); ?></p>
    <?php endif; ?>

    <?php // ── Non-voter list ── ?>
    <?php if ( ! empty( $non_voters_list ) ) :
        $non_voters_total = count( $non_voters_list );
        $page_size_nv     = 100;
    ?>
        <h2><?php printf( esc_html__( 'Nie głosowali (%d)', 'evoting' ), $non_voters_total ); ?></h2>
        <p class="description"><?php esc_html_e( 'Uprawnieni użytkownicy, którzy nie oddali głosu. Pseudonimy zanonimizowane.', 'evoting' ); ?></p>
        <table class="widefat fixed striped evoting-paginated-table" id="evoting-non-voters-table" style="max-width:400px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Pseudonim (zanonimizowany)', 'evoting' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $non_voters_list as $idx => $nv ) : ?>
                    <tr<?php echo $idx >= $page_size_nv ? ' class="evoting-row-hidden" style="display:none;"' : ''; ?>>
                        <td><?php echo esc_html( $nv['nicename'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ( $non_voters_total > $page_size_nv ) : ?>
            <p style="margin-top:8px;">
                <button type="button" class="button evoting-load-more" data-table="evoting-non-voters-table" data-page-size="<?php echo (int) $page_size_nv; ?>">
                    <?php printf( esc_html__( 'Załaduj więcej (pokazano %1$d z %2$d)', 'evoting' ), min( $page_size_nv, $non_voters_total ), $non_voters_total ); ?>
                </button>
            </p>
        <?php endif; ?>
    <?php elseif ( isset( $non_voters_list ) ) : ?>
        <h2><?php esc_html_e( 'Nie głosowali (0)', 'evoting' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Wszyscy uprawnieni użytkownicy oddali głos.', 'evoting' ); ?></p>
    <?php endif; ?>

    <?php if ( Evoting_Results_Pdf::is_available() ) : ?>
        <p class="submit" style="margin-top:24px;">
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'evoting', 'action' => 'results', 'poll_id' => $poll->id, 'evoting_pdf' => '1' ], admin_url( 'admin.php' ) ), 'evoting_results_pdf_' . $poll->id ) ); ?>"
               class="button button-primary">
                <?php esc_html_e( 'Pobierz wyniki (PDF)', 'evoting' ); ?>
            </a>
        </p>
    <?php else : ?>
        <p class="description" style="margin-top:16px;">
            <?php esc_html_e( 'Aby pobrać wyniki w PDF, uruchom w katalogu wtyczki: composer install', 'evoting' ); ?>
        </p>
    <?php endif; ?>

    <?php
    // ── Zaproszenia e-mail ───────────────────────────────────────────────────
    global $wpdb;
    $eq = $wpdb->prefix . 'evoting_email_queue';

    // Pobierz statystyki niezależnie od tego czy są rekordy
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
    ?>

    <hr id="invitations" style="margin:32px 0 20px;">
    <h2><?php esc_html_e( 'Zaproszenia e-mail', 'evoting' ); ?></h2>

    <?php if ( in_array( $poll->status, [ 'open', 'closed' ], true ) && current_user_can( 'manage_options' ) ) : ?>
    <p>
        <button type="button" class="button button-primary" id="evoting-send-invitations-btn2"
                data-poll-id="<?php echo (int) $poll->id; ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'evoting_send_invitations' ) ); ?>">
            <?php echo $eq_exists
                ? esc_html__( 'Wyślij ponownie', 'evoting' )
                : esc_html__( 'Wyślij zaproszenia', 'evoting' ); ?>
        </button>
        <span id="evoting-invitations-status2" style="margin-left:12px;font-weight:500;font-size:13px;"></span>
    </p>
    <div id="evoting-invitations-progress2" style="display:none;max-width:600px;margin:8px 0 16px;"></div>
    <script>
    (function(){
        var btn2      = document.getElementById('evoting-send-invitations-btn2');
        var statusEl2 = document.getElementById('evoting-invitations-status2');
        var progressEl2 = document.getElementById('evoting-invitations-progress2');
        var pollId    = btn2 ? btn2.dataset.pollId : '';
        var nonce     = btn2 ? btn2.dataset.nonce  : '';
        var apiRoot   = window.evotingBatch?.apiRoot || '/wp-json/evoting/v1';
        var batchNonce = window.evotingBatch?.nonce || '';
        var emailDelayMs = (window.evotingBatch?.emailDelay || 3) * 1000;

        function startOrResume(jobId) {
            if (!jobId) return;
            if (typeof evotingRunBatchJob === 'undefined') return;
            btn2.disabled = true;
            btn2.textContent = '<?php esc_html_e( 'Wysyłanie…', 'evoting' ); ?>';
            evotingRunBatchJob(
                jobId,
                function(processed, total) {
                    progressEl2.style.display = '';
                    progressEl2.innerHTML = '<progress style="width:100%" value="' + processed + '" max="' + (total||1) + '"></progress> ' + processed + ' / ' + (total||'?');
                    statusEl2.textContent = processed + ' / ' + (total||'?') + ' <?php esc_html_e( 'wysłanych', 'evoting' ); ?>';
                },
                function() {
                    statusEl2.style.color = '#0a6b2e';
                    statusEl2.textContent = '<?php esc_html_e( 'Wysyłanie zakończone. Odśwież stronę, aby zobaczyć wyniki.', 'evoting' ); ?>';
                    btn2.disabled = false;
                    btn2.textContent = '<?php esc_html_e( 'Wyślij ponownie', 'evoting' ); ?>';
                    if (typeof evotingClearJobId !== 'undefined') evotingClearJobId(pollId);
                },
                function(err) {
                    statusEl2.style.color = '#c00';
                    statusEl2.textContent = '<?php esc_html_e( 'Błąd wysyłki:', 'evoting' ); ?> ' + err;
                    btn2.disabled = false;
                },
                emailDelayMs
            );
        }

        if (btn2) {
            var savedJob = (typeof evotingGetSavedJobId !== 'undefined') ? evotingGetSavedJobId(pollId) : null;
            if (savedJob) {
                statusEl2.textContent = '<?php esc_html_e( 'Wznawianie przerwanej wysyłki…', 'evoting' ); ?>';
                startOrResume(savedJob);
            }

            btn2.addEventListener('click', function() {
                fetch(apiRoot + '/polls/' + pollId + '/send-invitations', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': batchNonce, 'Content-Type': 'application/json' }
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.job_id) {
                        if (typeof evotingSaveJobId !== 'undefined') evotingSaveJobId(pollId, data.job_id);
                        startOrResume(data.job_id);
                    } else {
                        statusEl2.style.color = '#c00';
                        statusEl2.textContent = data.message || '<?php esc_html_e( 'Błąd.', 'evoting' ); ?>';
                    }
                })
                .catch(function(e){
                    statusEl2.style.color = '#c00';
                    statusEl2.textContent = e.message;
                });
            });
        }
    })();
    </script>
    <?php endif; ?>

    <?php if ( ! $eq_exists ) : ?>
        <p style="color:#555;"><?php esc_html_e( 'Żadne zaproszenia nie zostały jeszcze wysłane dla tego głosowania.', 'evoting' ); ?></p>
    <?php else : ?>
    <table class="evoting-freq-table" style="max-width:680px;">
        <tbody>
            <tr>
                <td class="evoting-freq-label"><?php esc_html_e( 'Łącznie w kolejce', 'evoting' ); ?></td>
                <td class="evoting-freq-count" style="font-weight:600;"><?php echo (int) $cnt_total; ?></td>
                <td class="evoting-freq-pct"></td>
            </tr>
            <tr>
                <td class="evoting-freq-label"><?php esc_html_e( 'Wysłanych pomyślnie', 'evoting' ); ?></td>
                <td class="evoting-freq-count" style="color:#0a6b2e;font-weight:600;"><?php echo (int) $cnt_sent; ?></td>
                <td class="evoting-freq-pct" style="min-width:110px;">
                    <?php if ( $last_sent ) : ?>
                        <small style="color:#555;"><?php echo esc_html( $last_sent ); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="evoting-freq-label"><?php esc_html_e( 'Oczekujących w kolejce', 'evoting' ); ?></td>
                <td class="evoting-freq-count" style="color:<?php echo $cnt_pending > 0 ? '#c0730a' : '#555'; ?>;font-weight:600;"><?php echo (int) $cnt_pending; ?></td>
                <td class="evoting-freq-pct">
                    <?php if ( $cnt_pending > 0 ) : ?>
                        <small style="color:#c0730a;"><?php esc_html_e( 'Wznów wysyłkę przyciskiem powyżej', 'evoting' ); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="evoting-freq-label"><?php esc_html_e( 'Błędy wysyłki', 'evoting' ); ?></td>
                <td class="evoting-freq-count" style="color:<?php echo $cnt_failed > 0 ? '#c00' : '#555'; ?>;font-weight:600;"><?php echo (int) $cnt_failed; ?></td>
                <td class="evoting-freq-pct">
                    <?php if ( $cnt_failed > 0 ) : ?>
                        <button type="button" class="button button-small" id="evoting-toggle-failed">
                            <?php esc_html_e( 'Pokaż błędy', 'evoting' ); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if ( $cnt_failed > 0 ) :
        $failed_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT email, name, error_msg, created_at
             FROM {$eq} WHERE poll_id = %d AND status = 'failed'
             ORDER BY created_at DESC LIMIT 500",
            $poll->id
        ) );
    ?>
    <div id="evoting-failed-emails" style="display:none;margin-top:12px;">
        <table class="widefat fixed striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th style="width:240px;"><?php esc_html_e( 'E-mail', 'evoting' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Imię i nazwisko', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Komunikat błędu', 'evoting' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Data próby', 'evoting' ); ?></th>
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
    <?php endif; // cnt_failed ?>
    <?php endif; // eq_exists ?>

    <?php if ( $cnt_failed > 0 ) : ?>
    <script>
    (function(){
        var btn = document.getElementById('evoting-toggle-failed');
        var box = document.getElementById('evoting-failed-emails');
        if(btn && box){
            btn.addEventListener('click', function(){
                var hidden = box.style.display === 'none';
                box.style.display = hidden ? '' : 'none';
                btn.textContent = hidden
                    ? '<?php echo esc_js( __( 'Ukryj błędy', 'evoting' ) ); ?>'
                    : '<?php echo esc_js( __( 'Pokaż błędy', 'evoting' ) ); ?>';
            });
        }
    })();
    </script>
    <?php endif; // cnt_failed ?>

</div>
