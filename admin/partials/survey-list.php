<?php
defined( 'ABSPATH' ) || exit;

$list_table = new Evoting_Surveys_List();
$list_table->process_bulk_action();
$list_table->prepare_items();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Ankiety', 'evoting' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting-surveys&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Dodaj nową', 'evoting' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['duplicated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Utworzono kopię ankiety. Możesz ją teraz edytować.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['duplicate_error'] ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Błąd podczas duplikowania ankiety.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['closed'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została zakończona.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) || isset( $_GET['bulk_deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została usunięta.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została utworzona.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta została zaktualizowana.', 'evoting' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['bulk_closed'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankiety zostały zakończone.', 'evoting' ); ?></p></div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'evoting_survey_admin_error' );
    if ( $error ) :
        delete_transient( 'evoting_survey_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php $list_table->search_box( __( 'Szukaj ankiety', 'evoting' ), 'survey-search' ); ?>
        <?php $list_table->display(); ?>
    </form>
</div>
