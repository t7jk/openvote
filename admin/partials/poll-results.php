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
    <hr class="wp-header-end">

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

</div>
