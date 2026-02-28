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
    <h1><?php printf( esc_html__( 'Wyniki: %s', 'openvote' ), esc_html( $poll->title ) ); ?></h1>

    <?php if ( 'draft' === ( $poll->status ?? '' ) ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote&action=edit&poll_id=' . $poll->id ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Edytuj głosowanie', 'openvote' ); ?>
        </a>
    <?php endif; ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Wszystkie głosowania', 'openvote' ); ?>
    </a>
    <?php if ( 'open' === ( $poll->status ?? '' ) ) : ?>
        <button type="button" class="page-title-action button-primary" id="openvote-send-invitations-btn"
                data-poll-id="<?php echo (int) $poll->id; ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'openvote_send_invitations' ) ); ?>">
            <?php esc_html_e( 'Wyślij zaproszenia', 'openvote' ); ?>
        </button>
        <span id="openvote-invitations-status" style="margin-left:12px;font-weight:500;font-size:13px;"></span>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php /* Kontener paska postępu wysyłki */ ?>
    <div id="openvote-invitations-progress" style="display:none;max-width:600px;margin:12px 0 0;"></div>

    <?php if ( 'open' === ( $poll->status ?? '' ) ) : ?>
    <script>
    (function(){
        var btn      = document.getElementById('openvote-send-invitations-btn');
        var statusEl = document.getElementById('openvote-invitations-status');
        var progressEl = document.getElementById('openvote-invitations-progress');
        var pollId   = btn ? btn.dataset.pollId : '';
        var nonce    = btn ? btn.dataset.nonce  : '';
        var apiRoot  = window.openvoteBatch?.apiRoot || '/wp-json/openvote/v1';
        var batchNonce = window.openvoteBatch?.nonce || '';
        var emailDelayMs = (window.openvoteBatch?.emailDelay || 3) * 1000;

        function startOrResume(jobId) {
            if (btn) btn.disabled = true;
            progressEl.style.display = '';
            statusEl.textContent = '<?php echo esc_js( __( 'Wysyłanie…', 'openvote' ) ); ?>';
            statusEl.style.color = '#666';

            openvoteRunBatchJob(
                jobId,
                function(processed, total, pct) {
                    openvoteRenderProgress(progressEl, processed, total, pct);
                    statusEl.textContent = processed + ' / ' + total;
                },
                function(job) {
                    statusEl.textContent = '<?php echo esc_js( __( 'Wysyłka zakończona!', 'openvote' ) ); ?>';
                    statusEl.style.color = 'green';
                    openvoteClearJobId(pollId);
                    if (btn) { btn.textContent = '<?php echo esc_js( __( 'Wyślij ponownie', 'openvote' ) ); ?>'; btn.disabled = false; }
                },
                function(err) {
                    statusEl.textContent = '<?php echo esc_js( __( 'Błąd:', 'openvote' ) ); ?> ' + err.message;
                    statusEl.style.color = '#c00';
                    if (btn) btn.disabled = false;
                },
                emailDelayMs
            );
        }

        // Sprawdź czy jest wznowienie z localStorage.
        var savedJob = openvoteGetSavedJobId(pollId);
        if (savedJob && btn) {
            btn.textContent = '<?php echo esc_js( __( 'Wznów wysyłkę', 'openvote' ) ); ?>';
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
                    openvoteSaveJobId(pollId, data.job_id);
                    startOrResume(data.job_id);
                })
                .catch(function(err) {
                    statusEl.textContent = '<?php echo esc_js( __( 'Błąd startu:', 'openvote' ) ); ?> ' + err.message;
                    statusEl.style.color = '#c00';
                    if (btn) btn.disabled = false;
                });
            });
        }
    })();
    </script>
    <?php endif; ?>

    <?php // ── Participation summary ── ?>
    <div class="openvote-results-summary">
        <h2><?php esc_html_e( 'Frekwencja', 'openvote' ); ?></h2>

        <p>
            <strong><?php esc_html_e( 'Status:', 'openvote' ); ?></strong> <?php echo esc_html( $poll->status ); ?>
            &nbsp;|&nbsp;
            <strong><?php esc_html_e( 'Okres:', 'openvote' ); ?></strong>
            <?php echo esc_html( $poll->date_start . ' — ' . $poll->date_end ); ?>
        </p>

        <table class="openvote-freq-table">
            <tbody>
                <tr>
                    <td class="openvote-freq-label"><?php esc_html_e( 'Uprawnionych do głosowania', 'openvote' ); ?></td>
                    <td class="openvote-freq-count"><?php echo esc_html( $total_eligible ); ?></td>
                    <td class="openvote-freq-pct">100%</td>
                    <td class="openvote-freq-bar-cell">
                        <div class="openvote-bar openvote-bar--eligible" style="width:100%"></div>
                    </td>
                </tr>
                <tr>
                    <td class="openvote-freq-label"><?php esc_html_e( 'Uczestniczyło w głosowaniu', 'openvote' ); ?></td>
                    <td class="openvote-freq-count"><?php echo esc_html( $total_voters ); ?></td>
                    <td class="openvote-freq-pct"><?php echo esc_html( $pct_voted ); ?>%</td>
                    <td class="openvote-freq-bar-cell">
                        <div class="openvote-bar openvote-bar--voted" style="width:<?php echo esc_attr( $pct_voted ); ?>%"></div>
                    </td>
                </tr>
                <tr>
                    <td class="openvote-freq-label"><?php esc_html_e( 'Nie uczestniczyło (liczone jako abstencja)', 'openvote' ); ?></td>
                    <td class="openvote-freq-count"><?php echo esc_html( $non_voters ); ?></td>
                    <td class="openvote-freq-pct"><?php echo esc_html( $pct_absent ); ?>%</td>
                    <td class="openvote-freq-bar-cell">
                        <div class="openvote-bar openvote-bar--absent" style="width:<?php echo esc_attr( $pct_absent ); ?>%"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php // ── Per-question results ── ?>
    <?php if ( ! empty( $results['questions'] ) ) : ?>
        <h2><?php esc_html_e( 'Wyniki pytań', 'openvote' ); ?></h2>

        <?php foreach ( $results['questions'] as $i => $q ) : ?>
            <div class="openvote-result-question">
                <h3><?php echo esc_html( ( $i + 1 ) . '. ' . $q['question_text'] ); ?></h3>

                <table class="openvote-results-questions-table widefat" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Odpowiedź', 'openvote' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Głosy', 'openvote' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Procent', 'openvote' ); ?></th>
                            <th style="width:240px;"><?php esc_html_e( 'Wykres', 'openvote' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $q['answers'] as $ai => $answer ) :
                            $bar_class = $answer['is_abstain']
                                ? 'openvote-bar--wstrzymuje'
                                : ( 0 === $ai ? 'openvote-bar--za' : 'openvote-bar--przeciw' );
                        ?>
                            <tr>
                                <td>
                                    <?php if ( $answer['is_abstain'] ) : ?>
                                        <?php echo esc_html( __( 'Wstrzymuję się', 'openvote' ) ); ?>
                                        <em style="color:#999;font-size:11px;"><?php esc_html_e( '(inc. brak głosu)', 'openvote' ); ?></em>
                                    <?php else : ?>
                                        <?php echo esc_html( $answer['text'] ); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $answer['count'] ); ?></td>
                                <td><?php echo esc_html( $answer['pct'] ); ?>%</td>
                                <td class="openvote-result-bar-cell">
                                    <div class="openvote-bar-track">
                                        <div class="openvote-bar <?php echo esc_attr( $bar_class ); ?>"
                                             style="width:<?php echo esc_attr( $answer['pct'] ); ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?php esc_html_e( 'Brak pytań w tym głosowaniu.', 'openvote' ); ?></p>
    <?php endif; ?>

    <?php // ── Voter list (per-vote: jawnie = dane, anonimowo = Anonimowy). Paginacja po 100 ze względu na wydajność. ── ?>
    <?php
    $voters_total = (int) ( $results['total_voters'] ?? 0 );
    $voters_page_size = isset( $voters_page_size ) ? (int) $voters_page_size : 100;
    $voters_offset   = isset( $voters_offset ) ? (int) $voters_offset : 0;
    ?>
    <?php if ( $voters_total > 0 ) : ?>
        <h2><?php printf( esc_html__( 'Lista głosujących (%d)', 'openvote' ), $voters_total ); ?></h2>
        <p class="description"><?php esc_html_e( 'Widoczne: imię i nazwisko oraz zanonimizowany adres e-mail. Pozostałe dane są utajnione. Wyświetlanie partiami po 100.', 'openvote' ); ?></p>
        <table class="widefat fixed striped" id="openvote-voters-table" style="max-width:700px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Imię i Nazwisko', 'openvote' ); ?></th>
                    <th><?php esc_html_e( 'E-mail (zanonimizowany)', 'openvote' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Data głosowania', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $voters as $voter ) : ?>
                    <tr>
                        <td><?php echo esc_html( $voter['name'] ); ?></td>
                        <td><code><?php echo esc_html( $voter['email_anon'] ); ?></code></td>
                        <td><?php echo esc_html( $voter['voted_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ( ( $voters_offset + $voters_page_size ) < $voters_total ) : ?>
            <?php
            $next_voters_offset = $voters_offset + $voters_page_size;
            $base_results_url = add_query_arg( [ 'page' => 'openvote', 'action' => 'results', 'poll_id' => (int) $poll->id ], admin_url( 'admin.php' ) );
            if ( $non_voters_offset > 0 ) {
                $base_results_url = add_query_arg( 'non_voters_offset', $non_voters_offset, $base_results_url );
            }
            $next_url = add_query_arg( 'voters_offset', $next_voters_offset, $base_results_url );
            ?>
            <p style="margin-top:8px;">
                <a href="<?php echo esc_url( $next_url ); ?>" class="button"><?php printf( esc_html__( 'Załaduj więcej (pokazano %1$d–%2$d z %3$d)', 'openvote' ), $voters_offset + 1, min( $voters_offset + $voters_page_size, $voters_total ), $voters_total ); ?></a>
            </p>
        <?php elseif ( $voters_offset > 0 ) : ?>
            <?php
            $base_results_url = add_query_arg( [ 'page' => 'openvote', 'action' => 'results', 'poll_id' => (int) $poll->id ], admin_url( 'admin.php' ) );
            if ( $non_voters_offset > 0 ) {
                $base_results_url = add_query_arg( 'non_voters_offset', $non_voters_offset, $base_results_url );
            }
            ?>
            <p style="margin-top:8px;">
                <a href="<?php echo esc_url( $base_results_url ); ?>" class="button"><?php esc_html_e( 'Pokaż od początku', 'openvote' ); ?></a>
            </p>
        <?php endif; ?>
    <?php else : ?>
        <p class="description"><?php esc_html_e( 'Nikt jeszcze nie głosował.', 'openvote' ); ?></p>
    <?php endif; ?>

    <?php // ── Non-voter list. Paginacja po 100. ── ?>
    <?php
    $non_voters_total     = (int) ( $results['non_voters'] ?? 0 );
    $non_voters_page_size = isset( $non_voters_page_size ) ? (int) $non_voters_page_size : 100;
    $non_voters_offset   = isset( $non_voters_offset ) ? (int) $non_voters_offset : 0;
    ?>
    <?php if ( $non_voters_total > 0 ) : ?>
        <h2><?php printf( esc_html__( 'Nie głosowali (%d)', 'openvote' ), $non_voters_total ); ?></h2>
        <p class="description"><?php esc_html_e( 'Uprawnieni użytkownicy, którzy nie oddali głosu. Pseudonimy zanonimizowane. Wyświetlanie partiami po 100.', 'openvote' ); ?></p>
        <table class="widefat fixed striped" id="openvote-non-voters-table" style="max-width:400px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Pseudonim (zanonimizowany)', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $non_voters_list as $nv ) : ?>
                    <tr>
                        <td><?php echo esc_html( $nv['nicename'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ( ( $non_voters_offset + $non_voters_page_size ) < $non_voters_total ) : ?>
            <?php
            $next_nv_offset = $non_voters_offset + $non_voters_page_size;
            $base_results_url_nv = add_query_arg( [ 'page' => 'openvote', 'action' => 'results', 'poll_id' => (int) $poll->id ], admin_url( 'admin.php' ) );
            if ( $voters_offset > 0 ) {
                $base_results_url_nv = add_query_arg( 'voters_offset', $voters_offset, $base_results_url_nv );
            }
            $next_nv_url = add_query_arg( 'non_voters_offset', $next_nv_offset, $base_results_url_nv );
            ?>
            <p style="margin-top:8px;">
                <a href="<?php echo esc_url( $next_nv_url ); ?>" class="button"><?php printf( esc_html__( 'Załaduj więcej (pokazano %1$d–%2$d z %3$d)', 'openvote' ), $non_voters_offset + 1, min( $non_voters_offset + $non_voters_page_size, $non_voters_total ), $non_voters_total ); ?></a>
            </p>
        <?php elseif ( $non_voters_offset > 0 ) : ?>
            <?php
            $base_results_url_nv = add_query_arg( [ 'page' => 'openvote', 'action' => 'results', 'poll_id' => (int) $poll->id ], admin_url( 'admin.php' ) );
            if ( $voters_offset > 0 ) {
                $base_results_url_nv = add_query_arg( 'voters_offset', $voters_offset, $base_results_url_nv );
            }
            ?>
            <p style="margin-top:8px;">
                <a href="<?php echo esc_url( $base_results_url_nv ); ?>" class="button"><?php esc_html_e( 'Pokaż od początku', 'openvote' ); ?></a>
            </p>
        <?php endif; ?>
    <?php elseif ( isset( $results['non_voters'] ) && (int) $results['non_voters'] === 0 ) : ?>
        <h2><?php esc_html_e( 'Nie głosowali (0)', 'openvote' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Wszyscy uprawnieni użytkownicy oddali głos.', 'openvote' ); ?></p>
    <?php endif; ?>

    <?php if ( Openvote_Results_Pdf::is_available() ) : ?>
        <p class="submit" style="margin-top:24px;">
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'openvote', 'action' => 'results', 'poll_id' => $poll->id, 'openvote_pdf' => '1' ], admin_url( 'admin.php' ) ), 'openvote_results_pdf_' . $poll->id ) ); ?>"
               class="button button-primary">
                <?php esc_html_e( 'Pobierz wyniki (PDF)', 'openvote' ); ?>
            </a>
        </p>
    <?php else : ?>
        <p class="description" style="margin-top:16px;">
            <?php esc_html_e( 'Aby pobrać wyniki w PDF, uruchom w katalogu wtyczki: composer install', 'openvote' ); ?>
        </p>
    <?php endif; ?>

    <?php
    // ── Zaproszenia e-mail ───────────────────────────────────────────────────
    global $wpdb;
    $eq = $wpdb->prefix . 'openvote_email_queue';

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
    <h2><?php esc_html_e( 'Zaproszenia e-mail', 'openvote' ); ?></h2>

    <?php if ( 'open' === ( $poll->status ?? '' ) && current_user_can( 'manage_options' ) ) : ?>
    <p>
        <button type="button" class="button button-primary" id="openvote-send-invitations-btn2"
                data-poll-id="<?php echo (int) $poll->id; ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'openvote_send_invitations' ) ); ?>">
            <?php echo $eq_exists
                ? esc_html__( 'Wyślij ponownie', 'openvote' )
                : esc_html__( 'Wyślij zaproszenia', 'openvote' ); ?>
        </button>
        <span id="openvote-invitations-status2" style="margin-left:12px;font-weight:500;font-size:13px;"></span>
    </p>
    <div id="openvote-invitations-progress2" style="display:none;max-width:600px;margin:8px 0 16px;"></div>
    <script>
    (function(){
        var btn2      = document.getElementById('openvote-send-invitations-btn2');
        var statusEl2 = document.getElementById('openvote-invitations-status2');
        var progressEl2 = document.getElementById('openvote-invitations-progress2');
        var pollId    = btn2 ? btn2.dataset.pollId : '';
        var nonce     = btn2 ? btn2.dataset.nonce  : '';
        var apiRoot   = window.openvoteBatch?.apiRoot || '/wp-json/openvote/v1';
        var batchNonce = window.openvoteBatch?.nonce || '';
        var emailDelayMs = (window.openvoteBatch?.emailDelay || 3) * 1000;

        function startOrResume(jobId) {
            if (!jobId) return;
            if (typeof openvoteRunBatchJob === 'undefined') return;
            btn2.disabled = true;
            btn2.textContent = '<?php esc_html_e( 'Wysyłanie…', 'openvote' ); ?>';
            openvoteRunBatchJob(
                jobId,
                function(processed, total) {
                    progressEl2.style.display = '';
                    progressEl2.innerHTML = '<progress style="width:100%" value="' + processed + '" max="' + (total||1) + '"></progress> ' + processed + ' / ' + (total||'?');
                    statusEl2.textContent = processed + ' / ' + (total||'?') + ' <?php esc_html_e( 'wysłanych', 'openvote' ); ?>';
                },
                function() {
                    statusEl2.style.color = '#0a6b2e';
                    statusEl2.textContent = '<?php esc_html_e( 'Wysyłanie zakończone. Odśwież stronę, aby zobaczyć wyniki.', 'openvote' ); ?>';
                    btn2.disabled = false;
                    btn2.textContent = '<?php esc_html_e( 'Wyślij ponownie', 'openvote' ); ?>';
                    if (typeof openvoteClearJobId !== 'undefined') openvoteClearJobId(pollId);
                },
                function(err) {
                    statusEl2.style.color = '#c00';
                    statusEl2.textContent = '<?php esc_html_e( 'Błąd wysyłki:', 'openvote' ); ?> ' + err;
                    btn2.disabled = false;
                },
                emailDelayMs
            );
        }

        if (btn2) {
            var savedJob = (typeof openvoteGetSavedJobId !== 'undefined') ? openvoteGetSavedJobId(pollId) : null;
            if (savedJob) {
                statusEl2.textContent = '<?php esc_html_e( 'Wznawianie przerwanej wysyłki…', 'openvote' ); ?>';
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
                        if (typeof openvoteSaveJobId !== 'undefined') openvoteSaveJobId(pollId, data.job_id);
                        startOrResume(data.job_id);
                    } else {
                        statusEl2.style.color = '#c00';
                        statusEl2.textContent = data.message || '<?php esc_html_e( 'Błąd.', 'openvote' ); ?>';
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
        <p style="color:#555;"><?php esc_html_e( 'Żadne zaproszenia nie zostały jeszcze wysłane dla tego głosowania.', 'openvote' ); ?></p>
    <?php else : ?>
    <table class="openvote-freq-table" style="max-width:680px;">
        <tbody>
            <tr>
                <td class="openvote-freq-label"><?php esc_html_e( 'Łącznie w kolejce', 'openvote' ); ?></td>
                <td class="openvote-freq-count" style="font-weight:600;"><?php echo (int) $cnt_total; ?></td>
                <td class="openvote-freq-pct"></td>
            </tr>
            <tr>
                <td class="openvote-freq-label"><?php esc_html_e( 'Wysłanych pomyślnie', 'openvote' ); ?></td>
                <td class="openvote-freq-count" style="color:#0a6b2e;font-weight:600;"><?php echo (int) $cnt_sent; ?></td>
                <td class="openvote-freq-pct" style="min-width:110px;">
                    <?php if ( $last_sent ) : ?>
                        <small style="color:#555;"><?php echo esc_html( $last_sent ); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="openvote-freq-label"><?php esc_html_e( 'Oczekujących w kolejce', 'openvote' ); ?></td>
                <td class="openvote-freq-count" style="color:<?php echo $cnt_pending > 0 ? '#c0730a' : '#555'; ?>;font-weight:600;"><?php echo (int) $cnt_pending; ?></td>
                <td class="openvote-freq-pct">
                    <?php if ( $cnt_pending > 0 ) : ?>
                        <small style="color:#c0730a;"><?php esc_html_e( 'Wznów wysyłkę przyciskiem powyżej', 'openvote' ); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="openvote-freq-label"><?php esc_html_e( 'Błędy wysyłki', 'openvote' ); ?></td>
                <td class="openvote-freq-count" style="color:<?php echo $cnt_failed > 0 ? '#c00' : '#555'; ?>;font-weight:600;"><?php echo (int) $cnt_failed; ?></td>
                <td class="openvote-freq-pct">
                    <?php if ( $cnt_failed > 0 ) : ?>
                        <button type="button" class="button button-small" id="openvote-toggle-failed">
                            <?php esc_html_e( 'Pokaż błędy', 'openvote' ); ?>
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
    <div id="openvote-failed-emails" style="display:none;margin-top:12px;">
        <table class="widefat fixed striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th style="width:240px;"><?php esc_html_e( 'E-mail', 'openvote' ); ?></th>
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
    <?php endif; // cnt_failed ?>
    <?php endif; // eq_exists ?>

    <?php if ( $cnt_failed > 0 ) : ?>
    <script>
    (function(){
        var btn = document.getElementById('openvote-toggle-failed');
        var box = document.getElementById('openvote-failed-emails');
        if(btn && box){
            btn.addEventListener('click', function(){
                var hidden = box.style.display === 'none';
                box.style.display = hidden ? '' : 'none';
                btn.textContent = hidden
                    ? '<?php echo esc_js( __( 'Ukryj błędy', 'openvote' ) ); ?>'
                    : '<?php echo esc_js( __( 'Pokaż błędy', 'openvote' ) ); ?>';
            });
        }
    })();
    </script>
    <?php endif; // cnt_failed ?>

</div>
