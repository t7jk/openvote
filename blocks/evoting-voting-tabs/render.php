<?php
/**
 * Server-side render dla bloku evoting/voting-tabs.
 * Wywoływany przez WordPress przy każdym wyświetleniu bloku na froncie.
 *
 * Zmienne dostępne z WordPress: $attributes, $content, $block
 */
defined( 'ABSPATH' ) || exit;

require_once EVOTING_PLUGIN_DIR . 'includes/evoting-render-poll.php';

// Załaduj style i JS głosowania gdy blok jest na stronie.
wp_enqueue_style(
    'evoting-public',
    EVOTING_PLUGIN_URL . 'public/css/evoting-public.css',
    [],
    EVOTING_VERSION
);

// Dopasuj rozmiar zakładek do h2 aktywnego motywu.
$_evoting_h2_size = 'clamp(1.25rem, 2.5vw + 0.25rem, 1.75rem)';
if ( function_exists( 'wp_get_global_styles' ) ) {
    $_h2_styles = wp_get_global_styles( [ 'elements', 'h2', 'typography' ] );
    if ( ! empty( $_h2_styles['fontSize'] ) && is_string( $_h2_styles['fontSize'] ) ) {
        $_evoting_h2_size = $_h2_styles['fontSize'];
    }
}
wp_add_inline_style( 'evoting-public', sprintf(
    '.evoting-tab { font-size: %s; line-height: 1.2; }',
    $_evoting_h2_size
) );
wp_enqueue_script(
    'evoting-public',
    EVOTING_PLUGIN_URL . 'public/js/evoting-public.js',
    [],
    EVOTING_VERSION,
    true
);
wp_localize_script( 'evoting-public', 'evotingPublic', [
    'restUrl' => esc_url_raw( rest_url( 'evoting/v1' ) ),
    'nonce'   => wp_create_nonce( 'wp_rest' ),
    'i18n'    => [
        'voteSuccess'      => __( 'Twój głos został zapisany. Dziękujemy!', 'evoting' ),
        'voteError'        => __( 'Wystąpił błąd. Spróbuj ponownie.', 'evoting' ),
        'answerAll'        => __( 'Odpowiedz na wszystkie pytania.', 'evoting' ),
        'chooseVisibility' => __( 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'evoting' ),
        'participation'    => __( 'Frekwencja', 'evoting' ),
        'totalEligible'    => __( 'Uprawnionych do głosowania', 'evoting' ),
        'totalVoters'      => __( 'Uczestniczyło w głosowaniu', 'evoting' ),
        'totalAbsent'      => __( 'Nie uczestniczyło', 'evoting' ),
        'inclAbsent'       => __( 'inc. brak głosu', 'evoting' ),
        'voterList'        => __( 'Głosujący (anonimowo):', 'evoting' ),
        'showVoters'       => __( 'Pokaż głosujących', 'evoting' ),
        'hideVoters'       => __( 'Ukryj głosujących', 'evoting' ),
        'nonVoterList'     => __( 'Nieobecni (nie głosowali):', 'evoting' ),
        'showNonVoters'    => __( 'Pokaż nieobecnych', 'evoting' ),
        'hideNonVoters'    => __( 'Ukryj nieobecnych', 'evoting' ),
        'days'             => __( 'd', 'evoting' ),
        'hours'            => __( 'godz.', 'evoting' ),
        'minutes'          => __( 'min.', 'evoting' ),
        'seconds'          => __( 'sek.', 'evoting' ),
        'ended'            => __( 'Głosowanie zakończone', 'evoting' ),
    ],
] );

$is_logged     = is_user_logged_in();
$user_id       = $is_logged ? get_current_user_id() : 0;
$vote_page_url = evoting_get_vote_page_url();

// Aktywna zakładka: 'active' (trwające) lub 'closed' (zakończone).
$active_tab = isset( $_GET['tab'] ) && sanitize_key( $_GET['tab'] ) === 'closed' ? 'closed' : 'active';

$polls_active = [];
$polls_closed = [];

if ( $is_logged ) {
    if ( 'active' === $active_tab ) {
        $all_active   = Evoting_Poll::get_active_polls();
        $polls_active = array_values( array_filter(
            $all_active,
            function ( $poll ) use ( $user_id ) {
                return Evoting_Poll::user_in_target_groups( $user_id, $poll );
            }
        ) );
    } else {
        $polls_closed = Evoting_Poll::get_closed_polls_for_user( $user_id );
    }
}

// URL zakładek — dodajemy parametr ?tab= do aktualnego URL strony.
$current_page_url = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) )
    . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
$current_page_url = strtok( $current_page_url, '?' );

$tab_active_url = esc_url( add_query_arg( 'tab', 'active', $current_page_url ) );
$tab_closed_url = esc_url( add_query_arg( 'tab', 'closed', $current_page_url ) );
?>
<div class="evoting-vote-page-wrap">

    <nav class="evoting-tabs" role="tablist">
        <a href="<?php echo $tab_active_url; ?>"
           class="evoting-tab<?php echo 'active' === $active_tab ? ' evoting-tab--active' : ''; ?>"
           role="tab"
           aria-selected="<?php echo 'active' === $active_tab ? 'true' : 'false'; ?>">
            <?php esc_html_e( 'Trwające głosowania', 'evoting' ); ?>
        </a>
        <a href="<?php echo $tab_closed_url; ?>"
           class="evoting-tab<?php echo 'closed' === $active_tab ? ' evoting-tab--active' : ''; ?>"
           role="tab"
           aria-selected="<?php echo 'closed' === $active_tab ? 'true' : 'false'; ?>">
            <?php esc_html_e( 'Zakończone głosowania', 'evoting' ); ?>
        </a>
    </nav>

    <div class="evoting-tab-content">

    <?php if ( ! $is_logged ) : ?>

        <p class="evoting-poll__login-notice">
            <?php printf(
                esc_html__( 'Aby głosować, %s.', 'evoting' ),
                '<a href="' . esc_url( wp_login_url( get_permalink() ?: $vote_page_url ) ) . '">'
                    . esc_html__( 'zaloguj się', 'evoting' )
                . '</a>'
            ); ?>
        </p>

    <?php elseif ( 'active' === $active_tab ) : ?>

        <?php if ( empty( $polls_active ) ) : ?>
            <p class="evoting-poll__no-polls">
                <?php esc_html_e( 'Brak trwających głosowań w tym momencie.', 'evoting' ); ?>
            </p>
        <?php else : ?>
            <div class="evoting-poll-wrapper"
                 data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
                <?php foreach ( $polls_active as $poll ) : ?>
                    <?php evoting_render_single_poll( $poll, $user_id ); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else : ?>

        <?php if ( empty( $polls_closed ) ) : ?>
            <p class="evoting-poll__no-polls">
                <?php esc_html_e( 'Nie brałeś/aś udziału w żadnym zakończonym głosowaniu.', 'evoting' ); ?>
            </p>
        <?php else : ?>
            <?php foreach ( $polls_closed as $poll ) :
                $poll_id   = (int) $poll->id;
                $has_voted = Evoting_Vote::has_voted( $poll_id, $user_id );
                $results   = Evoting_Vote::get_results( $poll_id );
                $can_pdf   = current_user_can( 'edit_others_posts' );
                $pdf_url   = $can_pdf ? wp_nonce_url(
                    add_query_arg( [
                        'page'       => 'evoting',
                        'action'     => 'results',
                        'poll_id'    => $poll_id,
                        'evoting_pdf'=> '1',
                    ], admin_url( 'admin.php' ) ),
                    'evoting_results_pdf_' . $poll_id
                ) : '';

                $total_eligible = (int) ( $results['total_eligible'] ?? 0 );
                $total_voters   = (int) ( $results['total_voters']   ?? 0 );
                $pct_turnout    = $total_eligible > 0 ? round( $total_voters / $total_eligible * 100 ) : 0;
            ?>
            <div class="evoting-poll-block evoting-closed-poll-block">

                <div class="evoting-closed-poll-block__topbar">
                    <span class="evoting-closed-poll__status <?php echo $has_voted ? 'evoting-closed-poll__status--voted' : 'evoting-closed-poll__status--absent'; ?>">
                        <?php echo $has_voted ? esc_html__( 'Głosowałeś/aś', 'evoting' ) : esc_html__( 'Nie głosowałeś/aś', 'evoting' ); ?>
                    </span>
                    <span class="evoting-closed-poll-block__date">
                        <?php printf( esc_html__( 'Zakończono: %s', 'evoting' ), esc_html( substr( $poll->date_end, 0, 10 ) ) ); ?>
                    </span>
                </div>

                <h3 class="evoting-poll__title">
                    <span class="evoting-closed-poll-block__label"><?php esc_html_e( 'Tytuł:', 'evoting' ); ?></span>
                    <?php echo esc_html( $poll->title ); ?>
                </h3>
                <p class="evoting-poll__description">
                    <span class="evoting-closed-poll-block__label"><?php esc_html_e( 'Opis:', 'evoting' ); ?></span>
                    <?php echo esc_html( ! empty( $poll->description ) ? $poll->description : '—' ); ?>
                </p>

                <div class="evoting-closed-poll-block__turnout">
                    <span><?php printf(
                        esc_html__( 'Frekwencja: %1$d%% · głosowało %2$d z %3$d uprawnionych', 'evoting' ),
                        $pct_turnout, $total_voters, $total_eligible
                    ); ?></span>
                    <div class="evoting-closed-poll-block__turnout-bar">
                        <div class="evoting-closed-poll-block__turnout-fill" style="width:<?php echo $pct_turnout; ?>%"></div>
                    </div>
                </div>

                <?php $q_num = 0; foreach ( $results['questions'] as $q ) : $q_num++; ?>
                <fieldset class="evoting-poll__question evoting-closed-poll-block__question">
                    <legend>
                        <span class="evoting-closed-poll-block__label">
                            <?php printf( esc_html__( 'Pytanie %d:', 'evoting' ), $q_num ); ?>
                        </span>
                        <?php echo esc_html( $q['question_text'] ); ?>
                    </legend>
                    <?php foreach ( $q['answers'] as $a ) :
                        $bar_class = $a['is_abstain'] ? 'evoting-rbar--abstain' : ( $a['pct'] === max( array_column( $q['answers'], 'pct' ) ) && $a['count'] > 0 ? 'evoting-rbar--winner' : 'evoting-rbar--normal' );
                    ?>
                    <div class="evoting-closed-poll-block__answer">
                        <div class="evoting-closed-poll-block__answer-label">
                            <span><?php echo esc_html( $a['text'] ); ?></span>
                            <span class="evoting-closed-poll-block__answer-stat">
                                <strong><?php echo esc_html( $a['pct'] ); ?>%</strong>
                                <small>(<?php echo (int) $a['count']; ?>)</small>
                            </span>
                        </div>
                        <div class="evoting-closed-poll-block__bar-track">
                            <div class="evoting-closed-poll-block__bar-fill <?php echo esc_attr( $bar_class ); ?>"
                                 style="width:<?php echo esc_attr( $a['pct'] ); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
                <?php endforeach; ?>

                <?php if ( $can_pdf && $pdf_url ) : ?>
                <div class="evoting-closed-poll-block__footer">
                    <a href="<?php echo esc_url( $pdf_url ); ?>" class="evoting-closed-poll-block__pdf-btn">
                        ↓ <?php esc_html_e( 'Pobierz wyniki (PDF)', 'evoting' ); ?>
                    </a>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

    </div>
</div>
