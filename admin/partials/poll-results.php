<?php
defined( 'ABSPATH' ) || exit;

/** @var object $poll */
/** @var array  $results */
/** @var array  $voters */

$total_eligible = (int) $results['total_eligible'];
$total_voters   = (int) $results['total_voters'];
$non_voters     = (int) $results['non_voters'];
$pct_voted      = $total_eligible > 0 ? round( $total_voters / $total_eligible * 100, 1 ) : 0;
$pct_absent     = $total_eligible > 0 ? round( $non_voters  / $total_eligible * 100, 1 ) : 0;
$is_anonymous   = 'anonymous' === ( $poll->vote_mode ?? 'public' );
?>
<div class="wrap">
    <h1><?php printf( esc_html__( 'Wyniki: %s', 'evoting' ), esc_html( $poll->title ) ); ?></h1>

    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=edit&poll_id=' . $poll->id ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Edytuj głosowanie', 'evoting' ); ?>
    </a>
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
            &nbsp;|&nbsp;
            <strong><?php esc_html_e( 'Tryb:', 'evoting' ); ?></strong>
            <?php echo $is_anonymous
                ? esc_html__( '🔒 Anonimowe', 'evoting' )
                : esc_html__( 'Jawne', 'evoting' );
            ?>
        </p>

        <?php if ( $is_anonymous ) : ?>
            <div class="notice notice-info inline" style="margin:0 0 16px;">
                <p><?php esc_html_e( 'Głosowanie odbyło się w trybie anonimowym. Wyświetlane są wyłącznie zbiorcze wyniki.', 'evoting' ); ?></p>
            </div>
        <?php endif; ?>

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

    <?php // ── Voter list ── ?>
    <?php if ( $is_anonymous ) : ?>
        <?php // Tryb anonimowy — zero danych osobowych ?>
    <?php elseif ( ! empty( $voters ) ) : ?>
        <h2><?php esc_html_e( 'Lista głosujących', 'evoting' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Widoczne: imię i nazwisko oraz zanonimizowany adres e-mail. Pozostałe dane są utajnione.', 'evoting' ); ?></p>
        <table class="widefat fixed striped" style="max-width:700px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Imię i Nazwisko', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'E-mail (zanonimizowany)', 'evoting' ); ?></th>
                    <th style="width:160px;"><?php esc_html_e( 'Data głosowania', 'evoting' ); ?></th>
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
    <?php else : ?>
        <p class="description"><?php esc_html_e( 'Nikt jeszcze nie głosował.', 'evoting' ); ?></p>
    <?php endif; ?>
</div>
