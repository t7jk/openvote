<?php
defined( 'ABSPATH' ) || exit;

$list_table = new Openvote_Surveys_List();
// Bulk action jest obsługiwany w admin_init (handle_bulk_surveys_action), żeby redirect działał przed outputem motywu.
$list_table->prepare_items();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Ankiety', 'openvote' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=openvote-surveys&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Dodaj nową', 'openvote' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Utworzono kopię ankiety. Możesz ją teraz edytować.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['duplicate_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Błąd podczas duplikowania ankiety.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['closed'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została zakończona.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) || isset( $_GET['bulk_deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została usunięta.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została utworzona.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została zaktualizowana.', 'openvote' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['bulk_closed'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankiety zostały zakończone.', 'openvote' ); ?></p></div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'openvote_survey_admin_error' );
    if ( $error ) :
        delete_transient( 'openvote_survey_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <?php
    $surv_slug  = class_exists( 'Openvote_Survey_Page' ) ? Openvote_Survey_Page::get_slug() : get_option( 'openvote_survey_page_slug', 'ankieta' );
    $surv_page  = get_page_by_path( $surv_slug, OBJECT, 'page' );
    $surv_url   = ( $surv_page && 'publish' === $surv_page->post_status ) ? get_permalink( $surv_page->ID ) : ( class_exists( 'Openvote_Survey_Page' ) ? Openvote_Survey_Page::get_url() : home_url( '/' . $surv_slug . '/' ) );
    $subm_slug  = get_option( 'openvote_submissions_page_slug', 'zgloszenia' );
    $subm_page  = get_page_by_path( $subm_slug, OBJECT, 'page' );
    $subm_url   = ( $subm_page && 'publish' === $subm_page->post_status ) ? get_permalink( $subm_page->ID ) : home_url( '/' . $subm_slug . '/' );
    ?>
    <div class="notice notice-info inline openvote-survey-list-info" id="openvote-survey-list-info" style="max-width:560px;margin-bottom:16px;padding:8px 32px 8px 12px;position:relative;">
        <button type="button" class="openvote-survey-list-info-dismiss" aria-label="<?php esc_attr_e( 'Zamknij', 'openvote' ); ?>" style="position:absolute;top:6px;right:8px;background:none;border:none;padding:0;cursor:pointer;font-size:18px;line-height:1;color:#2271b1;opacity:.8;">&times;</button>
        <p style="margin:0;font-size:13px;">
            <?php esc_html_e( 'Ankiety:', 'openvote' ); ?>
            <a href="<?php echo esc_url( $surv_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $surv_url ); ?></a>
        </p>
        <p style="margin:4px 0 0;font-size:13px;">
            <?php esc_html_e( 'Zgłoszenia:', 'openvote' ); ?>
            <a href="<?php echo esc_url( $subm_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $subm_url ); ?></a>
        </p>
    </div>
    <script>
    (function(){
        var box = document.getElementById('openvote-survey-list-info');
        var btn = box && box.querySelector('.openvote-survey-list-info-dismiss');
        var key = 'openvote_survey_list_info_closed';
        if ( box && btn ) {
            if ( sessionStorage.getItem(key) === '1' ) { box.style.display = 'none'; }
            btn.addEventListener('click', function() {
                box.style.display = 'none';
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
            });
        }
    })();
    </script>

    <form method="post">
        <?php $list_table->search_box( __( 'Szukaj ankiety', 'openvote' ), 'survey-search' ); ?>
        <?php $list_table->display(); ?>
    </form>

    <?php
    $surveys_audit_all    = openvote_surveys_audit_log_get();
    $surveys_audit_per    = 20;
    $surveys_audit_total  = count( $surveys_audit_all );
    $surveys_audit_pages  = $surveys_audit_total > 0 ? (int) ceil( $surveys_audit_total / $surveys_audit_per ) : 1;
    $surveys_audit_page   = isset( $_GET['audit_page'] ) ? max( 1, absint( $_GET['audit_page'] ) ) : 1;
    $surveys_audit_page   = min( $surveys_audit_page, $surveys_audit_pages );
    $surveys_audit_offset = ( $surveys_audit_page - 1 ) * $surveys_audit_per;
    $surveys_audit_entries = array_slice( $surveys_audit_all, $surveys_audit_offset, $surveys_audit_per );
    $surveys_audit_base_url = add_query_arg( [ 'page' => 'openvote-surveys' ], admin_url( 'admin.php' ) );
    ?>
    <section class="openvote-surveys-audit-log" style="margin-top:32px; max-width:900px;">
        <h2 class="openvote-section-title" style="margin:0 0 8px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Log czynności ankiet', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Kto i kiedy utworzył, edytował, wystartował, zakończył, zduplikował lub usunął ankietę. Lista niekasowalna, w celach bezpieczeństwa.', 'openvote' ); ?></p>
        <div class="openvote-audit-log-box" style="background:#1d2327; color:#f0f0f1; padding:12px 16px; border-radius:4px; max-height:220px; overflow-y:auto; font-family:Consolas, Monaco, monospace; font-size:12px; line-height:1.5;">
            <?php
            if ( empty( $surveys_audit_entries ) ) {
                echo '<p style="margin:0; color:#a7aaad;">' . esc_html__( 'Brak wpisów.', 'openvote' ) . '</p>';
            } else {
                foreach ( $surveys_audit_entries as $e ) {
                    $t     = isset( $e['t'] ) ? $e['t'] : '';
                    $actor = isset( $e['actor'] ) ? $e['actor'] : '—';
                    $line  = isset( $e['line'] ) ? $e['line'] : '';
                    echo '<div style="margin:2px 0;">' . esc_html( $t . ' ' . $actor . ' ' . $line ) . '</div>';
                }
            }
            ?>
        </div>
        <?php if ( $surveys_audit_pages > 1 ) : ?>
        <p class="openvote-audit-log-nav" style="margin:8px 0 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span class="displaying-num" style="color:#646970; font-size:13px;">
                <?php
                echo esc_html( sprintf( __( 'Strona %1$d z %2$d (%3$d wpisów)', 'openvote' ), $surveys_audit_page, $surveys_audit_pages, $surveys_audit_total ) );
                ?>
            </span>
            <?php if ( $surveys_audit_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $surveys_audit_page - 1, $surveys_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Poprzedni', 'openvote' ); ?></a>
            <?php endif; ?>
            <?php if ( $surveys_audit_page < $surveys_audit_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'audit_page', $surveys_audit_page + 1, $surveys_audit_base_url ) ); ?>" class="button button-small"><?php esc_html_e( 'Następny', 'openvote' ); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </section>
</div>
