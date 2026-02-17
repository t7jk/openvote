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
        <p>
            <strong><?php esc_html_e( 'Łączna liczba głosujących:', 'evoting' ); ?></strong>
            <?php echo esc_html( $results['total_voters'] ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Status:', 'evoting' ); ?></strong>
            <?php echo esc_html( $poll->status ); ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Okres:', 'evoting' ); ?></strong>
            <?php echo esc_html( $poll->start_date . ' — ' . $poll->end_date ); ?>
        </p>
    </div>

    <?php if ( ! empty( $results['questions'] ) ) : ?>
        <h2><?php esc_html_e( 'Wyniki pytań', 'evoting' ); ?></h2>

        <?php foreach ( $results['questions'] as $i => $q ) :
            $total = max( $q['total'], 1 );
            ?>
            <div class="evoting-result-question">
                <h3><?php echo esc_html( ( $i + 1 ) . '. ' . $q['question_text'] ); ?></h3>

                <table class="widefat fixed">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Odpowiedź', 'evoting' ); ?></th>
                            <th><?php esc_html_e( 'Głosy', 'evoting' ); ?></th>
                            <th><?php esc_html_e( 'Procent', 'evoting' ); ?></th>
                            <th><?php esc_html_e( 'Wykres', 'evoting' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e( 'Jestem za', 'evoting' ); ?></td>
                            <td><?php echo esc_html( $q['za'] ); ?></td>
                            <td><?php echo esc_html( round( $q['za'] / $total * 100, 1 ) ); ?>%</td>
                            <td>
                                <div class="evoting-bar evoting-bar--za" style="width: <?php echo esc_attr( round( $q['za'] / $total * 100, 1 ) ); ?>%"></div>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Jestem przeciw', 'evoting' ); ?></td>
                            <td><?php echo esc_html( $q['przeciw'] ); ?></td>
                            <td><?php echo esc_html( round( $q['przeciw'] / $total * 100, 1 ) ); ?>%</td>
                            <td>
                                <div class="evoting-bar evoting-bar--przeciw" style="width: <?php echo esc_attr( round( $q['przeciw'] / $total * 100, 1 ) ); ?>%"></div>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Wstrzymuję się', 'evoting' ); ?></td>
                            <td><?php echo esc_html( $q['wstrzymuje_sie'] ); ?></td>
                            <td><?php echo esc_html( round( $q['wstrzymuje_sie'] / $total * 100, 1 ) ); ?>%</td>
                            <td>
                                <div class="evoting-bar evoting-bar--wstrzymuje" style="width: <?php echo esc_attr( round( $q['wstrzymuje_sie'] / $total * 100, 1 ) ); ?>%"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?php esc_html_e( 'Brak oddanych głosów.', 'evoting' ); ?></p>
    <?php endif; ?>

    <?php if ( ! empty( $voters ) ) : ?>
        <h2><?php esc_html_e( 'Lista głosujących (anonimowo)', 'evoting' ); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Pseudonim', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'GSM', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Miejsce spotkania', 'evoting' ); ?></th>
                    <th><?php esc_html_e( 'Data głosowania', 'evoting' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $voters as $voter ) : ?>
                    <tr>
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
