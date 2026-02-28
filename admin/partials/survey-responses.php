<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widok odpowiedzi dla ankiety (lub zbiorczy dla wszystkich zamkniętych).
 * Zmienna $survey (object|null) — jeśli ustawiona: widok pojedynczej ankiety.
 * Widok zbiorczy: pobiera wszystkie zamknięte ankiety posortowane A-Z po tytule.
 */

$per_page = 15;
$page_num = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

if ( isset( $survey ) && $survey ) {
    // ── Widok pojedynczej ankiety ────────────────────────────────────────
    $surveys_to_show = [ $survey ];
    $page_title      = sprintf( __( 'Odpowiedzi: %s', 'openvote' ), $survey->title );
} else {
    // ── Widok zbiorczy — wszystkie zamknięte ankiety ─────────────────────
    $surveys_to_show = Openvote_Survey::get_all( [ 'status' => 'closed', 'orderby' => 'title', 'order' => 'ASC' ] );
    $page_title      = __( 'Odpowiedzi — zakończone ankiety', 'openvote' );
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-surveys' ) ); ?>" class="page-title-action">
        ← <?php esc_html_e( 'Powrót do listy', 'openvote' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['marked_not_spam'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Zgłoszenie oznaczono jako „Nie spam”.', 'openvote' ); ?></p></div>
    <?php endif; ?>

    <?php if ( empty( $surveys_to_show ) ) : ?>
        <p><?php esc_html_e( 'Brak zamkniętych ankiet.', 'openvote' ); ?></p>
    <?php endif; ?>

    <?php foreach ( $surveys_to_show as $s ) :
        $total     = Openvote_Survey::count_responses( (int) $s->id );
        $responses = Openvote_Survey::get_responses( (int) $s->id, $page_num, $per_page );
        $questions = Openvote_Survey::get_questions( (int) $s->id );
        $q_map     = [];
        foreach ( $questions as $q ) {
            $q_map[ (int) $q->id ] = $q->body;
        }
        ?>
        <div class="openvote-survey-responses-group" style="margin-bottom:36px;">

            <h2 style="padding:10px 16px;background:#f0f0f1;border-left:4px solid #0073aa;margin:0 0 0;">
                <?php echo esc_html( $s->title ); ?>
                <span style="font-size:.75em;font-weight:400;color:#666;margin-left:8px;">
                    <?php printf(
                        esc_html__( '%d odpowiedzi (gotowe)', 'openvote' ),
                        $total
                    ); ?>
                </span>
            </h2>
            <?php if ( $s->description ) : ?>
                <p style="margin:8px 0 12px;color:#666;font-style:italic;padding:0 16px;">
                    <?php echo esc_html( $s->description ); ?>
                </p>
            <?php endif; ?>

            <?php if ( empty( $responses ) ) : ?>
                <p style="padding:12px 16px;color:#888;"><?php esc_html_e( 'Brak gotowych odpowiedzi.', 'openvote' ); ?></p>
            <?php else : ?>

                <?php foreach ( $responses as $resp ) : ?>
                    <div style="border:1px solid #ddd;border-radius:4px;margin-bottom:12px;overflow:hidden;">

                        <!-- Nagłówek karty uczestnika -->
                        <div style="background:#f9f9f9;padding:10px 16px;border-bottom:1px solid #ddd;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
                            <strong style="font-size:1em;">
                                <?php echo esc_html( trim( $resp->user_first_name . ' ' . $resp->user_last_name ) ); ?>
                            </strong>
                            <?php if ( $resp->user_nickname ) : ?>
                                <span style="color:#666;">@<?php echo esc_html( $resp->user_nickname ); ?></span>
                            <?php endif; ?>
                            <?php if ( $resp->user_phone ) : ?>
                                <span style="color:#555;">📞 <?php echo esc_html( $resp->user_phone ); ?></span>
                            <?php endif; ?>
                            <?php if ( $resp->user_email ) : ?>
                                <span style="color:#555;">✉ <?php echo esc_html( $resp->user_email ); ?></span>
                            <?php endif; ?>
                            <span style="margin-left:auto;color:#999;font-size:.85em;">
                                <?php echo esc_html( $resp->updated_at ); ?>
                            </span>
                            <?php
                            $spam_status = $resp->spam_status ?? 'pending';
                            if ( 'not_spam' === $spam_status ) : ?>
                                <span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:3px;font-size:.8em;"><?php esc_html_e( 'Nie spam', 'openvote' ); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'openvote-surveys', 'action' => 'mark_not_spam', 'survey_id' => $s->id, 'response_id' => $resp->id ], admin_url( 'admin.php' ) ), 'openvote_mark_not_spam_' . $resp->id ) ); ?>"
                                   class="button button-small"><?php esc_html_e( 'To nie spam', 'openvote' ); ?></a>
                            <?php endif; ?>
                        </div>

                        <!-- Odpowiedzi -->
                        <table style="width:100%;border-collapse:collapse;">
                            <?php foreach ( $questions as $q ) :
                                $answer = $resp->answers[ (int) $q->id ] ?? '';
                                $is_url = ( $q->field_type ?? '' ) === 'url';
                                if ( $answer !== '' && $is_url ) {
                                    $href = ( strpos( $answer, '://' ) !== false ) ? $answer : 'https://' . $answer;
                                    $answer_cell = '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $answer ) . '</a>';
                                } elseif ( $answer !== '' ) {
                                    $answer_cell = esc_html( $answer );
                                } else {
                                    $answer_cell = '<span style="color:#aaa;">—</span>';
                                }
                                ?>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:8px 16px;width:35%;vertical-align:top;color:#666;font-style:italic;">
                                        <?php echo esc_html( $q->body ); ?>
                                    </td>
                                    <td style="padding:8px 16px;vertical-align:top;">
                                        <?php echo $answer_cell; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                    </div><!-- .card -->
                <?php endforeach; ?>

                <!-- Paginacja per ankieta -->
                <?php if ( $total > $per_page ) :
                    $total_pages = (int) ceil( $total / $per_page );
                    $base_url    = add_query_arg( [
                        'page'      => 'openvote-surveys',
                        'action'    => 'responses',
                        'survey_id' => $s->id,
                    ], admin_url( 'admin.php' ) );
                    ?>
                    <div style="margin-top:8px;">
                        <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                            $url = add_query_arg( 'paged', $p, $base_url );
                            ?>
                            <a href="<?php echo esc_url( $url ); ?>"
                               style="<?php echo $p === $page_num ? 'font-weight:bold;text-decoration:none;' : ''; ?>margin-right:6px;">
                                <?php echo esc_html( $p ); ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div><!-- .group -->
    <?php endforeach; ?>
</div>
