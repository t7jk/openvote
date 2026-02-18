<?php
defined( 'ABSPATH' ) || exit;

/** @var object $poll */
/** @var array  $results */
/** @var array  $voters */
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

    <div class="evoting-results-summary">
        <h2><?php esc_html_e( 'Podsumowanie', 'evoting' ); ?></h2>
        <p><strong><?php esc_html_e( 'Uprawnionych do głosowania:', 'evoting' ); ?></strong> <?php echo esc_html( $results['total_eligible'] ); ?></p>
        <p><strong><?php esc_html_e( 'Oddało głos:', 'evoting' ); ?></strong> <?php echo esc_html( $results['total_voters'] ); ?></p>
        <p><strong><?php esc_html_e( 'Nie wzięło udziału (liczone jako wstrzymanie):', 'evoting' ); ?></strong> <?php echo esc_html( $results['non_voters'] ); ?></p>
        <p><strong><?php esc_html_e( 'Status:', 'evoting' ); ?></strong> <?php echo esc_html( $poll->status ); ?></p>
        <p><strong><?php esc_html_e( 'Okres:', 'evoting' ); ?></strong> <?php echo esc_html( $poll->start_date . ' — ' . $poll->end_date ); ?></p>
        <?php if ( 'group' === $poll->target_type && $poll->target_group ) : ?>
            <p><strong><?php esc_html_e( 'Grupa docelowa:', 'evoting' ); ?></strong> <?php echo esc_html( $poll->target_group ); ?></p>
        <?php endif; ?>
    </div>

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
                            $bar_class = $answer['is_abstain'] ? 'evoting-bar--wstrzymuje' : ( $ai === 0 ? 'evoting-bar--za' : 'evoting-bar--przeciw' );
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

    <?php if ( ! empty( $voters ) ) : ?>
        <h2><?php esc_html_e( 'Lista głosujących', 'evoting' ); ?></h2>
        <table class="widefat fixed striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Login (nicename)', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Pseudonim', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'GSM', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Miejsce spotkania', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Data głosowania', 'evoting' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $voters as $voter ) : ?>
                    <tr>
                        <td><?php echo esc_html( $voter['nicename'] ); ?></td>
                        <td><?php echo esc_html( $voter['pseudonym'] ); ?></td>
                        <td><?php echo esc_html( $voter['gsm'] ); ?></td>
                        <td><?php echo esc_html( $voter['location'] ); ?></td>
                        <td><?php echo esc_html( $voter['voted_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
