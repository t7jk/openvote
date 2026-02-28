<?php
/**
 * Server-side render dla bloku openvote/voting-tabs.
 * Wywoływany przez WordPress przy każdym wyświetleniu bloku na froncie.
 *
 * Zmienne dostępne z WordPress: $attributes, $content, $block
 */
defined( 'ABSPATH' ) || exit;

require_once OPENVOTE_PLUGIN_DIR . 'includes/openvote-render-poll.php';

// Załaduj style i JS głosowania gdy blok jest na stronie.
wp_enqueue_style(
    'openvote-public',
    OPENVOTE_PLUGIN_URL . 'public/css/openvote-public.css',
    [],
    OPENVOTE_VERSION
);

// Dopasuj rozmiar zakładek do h2 aktywnego motywu.
$_openvote_h2_size = 'clamp(1.25rem, 2.5vw + 0.25rem, 1.75rem)';
if ( function_exists( 'wp_get_global_styles' ) ) {
    $_h2_styles = wp_get_global_styles( [ 'elements', 'h2', 'typography' ] );
    if ( ! empty( $_h2_styles['fontSize'] ) && is_string( $_h2_styles['fontSize'] ) ) {
        $_openvote_h2_size = $_h2_styles['fontSize'];
    }
}
wp_add_inline_style( 'openvote-public', sprintf(
    '.openvote-tab { font-size: %s; line-height: 1.2; }',
    $_openvote_h2_size
) );
wp_enqueue_script(
    'openvote-public',
    OPENVOTE_PLUGIN_URL . 'public/js/openvote-public.js',
    [],
    OPENVOTE_VERSION,
    true
);
wp_localize_script( 'openvote-public', 'openvotePublic', [
    'restUrl' => esc_url_raw( rest_url( 'openvote/v1' ) ),
    'nonce'   => wp_create_nonce( 'wp_rest' ),
    'i18n'    => [
        'voteSuccess'      => __( 'Twój głos został zapisany. Dziękujemy!', 'openvote' ),
        'voteError'        => __( 'Wystąpił błąd. Spróbuj ponownie.', 'openvote' ),
        'answerAll'        => __( 'Odpowiedz na wszystkie pytania.', 'openvote' ),
        'chooseVisibility' => __( 'Wybierz sposób oddania głosu (jawnie lub anonimowo).', 'openvote' ),
        'participation'    => __( 'Frekwencja', 'openvote' ),
        'totalEligible'    => __( 'Uprawnionych do głosowania', 'openvote' ),
        'totalVoters'      => __( 'Uczestniczyło w głosowaniu', 'openvote' ),
        'totalAbsent'      => __( 'Nie uczestniczyło', 'openvote' ),
        'inclAbsent'       => __( 'inc. brak głosu', 'openvote' ),
        'voterList'        => __( 'Głosujący (anonimowo):', 'openvote' ),
        'showVoters'       => __( 'Pokaż głosujących', 'openvote' ),
        'hideVoters'       => __( 'Ukryj głosujących', 'openvote' ),
        'nonVoterList'     => __( 'Nieobecni (nie głosowali):', 'openvote' ),
        'showNonVoters'    => __( 'Pokaż nieobecnych', 'openvote' ),
        'hideNonVoters'    => __( 'Ukryj nieobecnych', 'openvote' ),
        'days'             => __( 'd', 'openvote' ),
        'hours'            => __( 'godz.', 'openvote' ),
        'minutes'          => __( 'min.', 'openvote' ),
        'seconds'          => __( 'sek.', 'openvote' ),
        'ended'            => __( 'Głosowanie zakończone', 'openvote' ),
    ],
] );

$is_logged     = is_user_logged_in();
$user_id       = $is_logged ? get_current_user_id() : 0;
$vote_page_url = openvote_get_vote_page_url();

// Aktywna zakładka: 'active' (trwające) lub 'closed' (zakończone).
$active_tab = isset( $_GET['tab'] ) && sanitize_key( $_GET['tab'] ) === 'closed' ? 'closed' : 'active';

$polls_active = [];
$polls_closed = [];

if ( $is_logged ) {
    if ( 'active' === $active_tab ) {
        $all_active   = Openvote_Poll::get_active_polls();
        $polls_active = array_values( array_filter(
            $all_active,
            function ( $poll ) use ( $user_id ) {
                return Openvote_Poll::user_in_target_groups( $user_id, $poll );
            }
        ) );
    } else {
        $polls_closed = Openvote_Poll::get_closed_polls_for_user( $user_id );
    }
}

// URL zakładek — używamy zaufanego URL strony głosowania (bez HTTP_HOST/REQUEST_URI).
$vote_slug   = openvote_get_vote_page_slug();
$wp_vote_page = $vote_slug !== '' ? get_page_by_path( $vote_slug, OBJECT, 'page' ) : null;
$base_url    = ( $wp_vote_page && 'publish' === $wp_vote_page->post_status )
    ? get_permalink( $wp_vote_page )
    : openvote_get_vote_page_url();
$base_url    = strtok( (string) $base_url, '?' );

$tab_active_url = esc_url( add_query_arg( 'tab', 'active', $base_url ) );
$tab_closed_url = esc_url( add_query_arg( 'tab', 'closed', $base_url ) );
$law_page_url   = class_exists( 'Openvote_Law_Page' ) ? Openvote_Law_Page::get_url() : '';
?>
<div class="openvote-vote-page-wrap">

    <nav class="openvote-tabs" role="tablist" style="position:relative;">
        <a href="<?php echo esc_url( $tab_active_url ); ?>"
           class="openvote-tab<?php echo 'active' === $active_tab ? ' openvote-tab--active' : ''; ?>"
           role="tab"
           aria-selected="<?php echo 'active' === $active_tab ? 'true' : 'false'; ?>">
            <?php esc_html_e( 'Trwające głosowania', 'openvote' ); ?>
        </a>
        <a href="<?php echo esc_url( $tab_closed_url ); ?>"
           class="openvote-tab<?php echo 'closed' === $active_tab ? ' openvote-tab--active' : ''; ?>"
           role="tab"
           aria-selected="<?php echo 'closed' === $active_tab ? 'true' : 'false'; ?>">
            <?php esc_html_e( 'Zakończone głosowania', 'openvote' ); ?>
        </a>
        <?php if ( $law_page_url !== '' ) : ?>
        <a href="<?php echo esc_url( $law_page_url ); ?>"
           class="openvote-law-link"
           title="<?php esc_attr_e( 'Przepisy prawne', 'openvote' ); ?>">
            ⚖️ <?php esc_html_e( 'Przepisy', 'openvote' ); ?>
        </a>
        <?php endif; ?>
    </nav>

    <div class="openvote-tab-content">

    <?php if ( ! $is_logged ) : ?>

        <p class="openvote-poll__login-notice">
            <?php printf(
                esc_html__( 'Aby głosować, %s.', 'openvote' ),
                '<a href="' . esc_url( wp_login_url( get_permalink() ?: $vote_page_url ) ) . '">'
                    . esc_html__( 'zaloguj się', 'openvote' )
                . '</a>'
            ); ?>
        </p>

    <?php elseif ( 'active' === $active_tab ) : ?>

        <?php if ( empty( $polls_active ) ) : ?>
            <p class="openvote-poll__no-polls">
                <?php esc_html_e( 'Brak trwających głosowań w tym momencie.', 'openvote' ); ?>
            </p>
        <?php else : ?>
            <div class="openvote-poll-wrapper"
                 data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
                <?php foreach ( $polls_active as $poll ) : ?>
                    <?php openvote_render_single_poll( $poll, $user_id ); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else : ?>

        <?php if ( empty( $polls_closed ) ) : ?>
            <p class="openvote-poll__no-polls">
                <?php esc_html_e( 'Nie brałeś/aś udziału w żadnym zakończonym głosowaniu.', 'openvote' ); ?>
            </p>
        <?php else : ?>
            <?php foreach ( $polls_closed as $poll ) :
                $poll_id   = (int) $poll->id;
                $has_voted = Openvote_Vote::has_voted( $poll_id, $user_id );
                $results   = Openvote_Vote::get_results( $poll_id );
                $can_pdf   = current_user_can( 'edit_others_posts' );
                $pdf_url   = $can_pdf ? wp_nonce_url(
                    add_query_arg( [
                        'page'       => 'openvote',
                        'action'     => 'results',
                        'poll_id'    => $poll_id,
                        'openvote_pdf'=> '1',
                    ], admin_url( 'admin.php' ) ),
                    'openvote_results_pdf_' . $poll_id
                ) : '';

                $total_eligible = (int) ( $results['total_eligible'] ?? 0 );
                $total_voters   = (int) ( $results['total_voters']   ?? 0 );
                $pct_turnout    = $total_eligible > 0 ? round( $total_voters / $total_eligible * 100 ) : 0;
            ?>
            <div class="openvote-poll-block openvote-closed-poll-block">

                <div class="openvote-closed-poll-block__topbar">
                    <span class="openvote-closed-poll__status <?php echo $has_voted ? 'openvote-closed-poll__status--voted' : 'openvote-closed-poll__status--absent'; ?>">
                        <?php echo $has_voted ? esc_html__( 'Głosowałeś/aś', 'openvote' ) : esc_html__( 'Nie głosowałeś/aś', 'openvote' ); ?>
                    </span>
                    <span class="openvote-closed-poll-block__date">
                        <?php printf( esc_html__( 'Zakończono: %s', 'openvote' ), esc_html( substr( $poll->date_end, 0, 10 ) ) ); ?>
                    </span>
                </div>

                <h3 class="openvote-poll__title">
                    <span class="openvote-closed-poll-block__label"><?php esc_html_e( 'Tytuł:', 'openvote' ); ?></span>
                    <?php echo esc_html( $poll->title ); ?>
                </h3>
                <p class="openvote-poll__description">
                    <span class="openvote-closed-poll-block__label"><?php esc_html_e( 'Opis:', 'openvote' ); ?></span>
                    <?php echo esc_html( ! empty( $poll->description ) ? $poll->description : '—' ); ?>
                </p>

                <div class="openvote-closed-poll-block__turnout">
                    <span><?php printf(
                        esc_html__( 'Frekwencja: %1$d%% · głosowało %2$d z %3$d uprawnionych', 'openvote' ),
                        $pct_turnout, $total_voters, $total_eligible
                    ); ?></span>
                    <div class="openvote-closed-poll-block__turnout-bar">
                        <div class="openvote-closed-poll-block__turnout-fill" style="width:<?php echo $pct_turnout; ?>%"></div>
                    </div>
                </div>

                <?php $q_num = 0; foreach ( $results['questions'] as $q ) : $q_num++; ?>
                <fieldset class="openvote-poll__question openvote-closed-poll-block__question">
                    <legend>
                        <span class="openvote-closed-poll-block__label">
                            <?php printf( esc_html__( 'Pytanie %d:', 'openvote' ), $q_num ); ?>
                        </span>
                        <?php echo esc_html( $q['question_text'] ); ?>
                    </legend>
                    <?php foreach ( $q['answers'] as $a ) :
                        $bar_class = $a['is_abstain'] ? 'openvote-rbar--abstain' : ( $a['pct'] === max( array_column( $q['answers'], 'pct' ) ) && $a['count'] > 0 ? 'openvote-rbar--winner' : 'openvote-rbar--normal' );
                    ?>
                    <div class="openvote-closed-poll-block__answer">
                        <div class="openvote-closed-poll-block__answer-label">
                            <span><?php echo esc_html( $a['text'] ); ?></span>
                            <span class="openvote-closed-poll-block__answer-stat">
                                <strong><?php echo esc_html( $a['pct'] ); ?>%</strong>
                                <small>(<?php echo (int) $a['count']; ?>)</small>
                            </span>
                        </div>
                        <div class="openvote-closed-poll-block__bar-track">
                            <div class="openvote-closed-poll-block__bar-fill <?php echo esc_attr( $bar_class ); ?>"
                                 style="width:<?php echo esc_attr( $a['pct'] ); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
                <?php endforeach; ?>

                <?php if ( $can_pdf && $pdf_url ) : ?>
                <div class="openvote-closed-poll-block__footer">
                    <a href="<?php echo esc_url( $pdf_url ); ?>" class="openvote-closed-poll-block__pdf-btn">
                        ↓ <?php esc_html_e( 'Pobierz wyniki (PDF)', 'openvote' ); ?>
                    </a>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

    </div>
</div>
