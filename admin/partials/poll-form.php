<?php
defined( 'ABSPATH' ) || exit;

$is_edit   = isset( $poll ) && $poll;
$title     = $is_edit ? $poll->title : '';
$desc      = $is_edit ? $poll->description : '';
$status    = $is_edit ? $poll->status : 'draft';
$start     = $is_edit ? $poll->start_date : '';
$end       = $is_edit ? $poll->end_date : '';
$notify    = $is_edit ? (bool) $poll->notify_users : false;
$questions = $is_edit ? $poll->questions : [];
?>
<div class="wrap">
    <h1><?php echo $is_edit ? esc_html__( 'Edytuj głosowanie', 'evoting' ) : esc_html__( 'Nowe głosowanie', 'evoting' ); ?></h1>

    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Głosowanie zostało utworzone.', 'evoting' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Głosowanie zostało zaktualizowane.', 'evoting' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $error = get_transient( 'evoting_admin_error' );
    if ( $error ) :
        delete_transient( 'evoting_admin_error' );
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="" id="evoting-poll-form">
        <?php wp_nonce_field( 'evoting_save_poll', 'evoting_poll_nonce' ); ?>

        <?php if ( $is_edit ) : ?>
            <input type="hidden" name="poll_id" value="<?php echo esc_attr( $poll->id ); ?>">
            <input type="hidden" name="evoting_action" value="update">
        <?php else : ?>
            <input type="hidden" name="evoting_action" value="create">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="poll_title"><?php esc_html_e( 'Tytuł', 'evoting' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="poll_title" name="poll_title"
                           value="<?php echo esc_attr( $title ); ?>"
                           class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="poll_description"><?php esc_html_e( 'Opis', 'evoting' ); ?></label>
                </th>
                <td>
                    <textarea id="poll_description" name="poll_description"
                              rows="4" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="poll_status"><?php esc_html_e( 'Status', 'evoting' ); ?></label>
                </th>
                <td>
                    <select id="poll_status" name="poll_status">
                        <option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Szkic', 'evoting' ); ?></option>
                        <option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Otwarte', 'evoting' ); ?></option>
                        <option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Zamknięte', 'evoting' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="start_date"><?php esc_html_e( 'Data rozpoczęcia', 'evoting' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="datetime-local" id="start_date" name="start_date"
                           value="<?php echo esc_attr( $start ? date( 'Y-m-d\TH:i', strtotime( $start ) ) : '' ); ?>"
                           required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="end_date"><?php esc_html_e( 'Data zakończenia', 'evoting' ); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="datetime-local" id="end_date" name="end_date"
                           value="<?php echo esc_attr( $end ? date( 'Y-m-d\TH:i', strtotime( $end ) ) : '' ); ?>"
                           required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Powiadomienia e-mail', 'evoting' ); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="notify_users" value="1" <?php checked( $notify ); ?>>
                        <?php esc_html_e( 'Wyślij powiadomienie e-mail do wszystkich użytkowników', 'evoting' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Pytania', 'evoting' ); ?> <small>(<?php esc_html_e( 'od 1 do 12', 'evoting' ); ?>)</small></h2>
        <p class="description">
            <?php esc_html_e( 'Dla każdego pytania użytkownicy wybierają odpowiedź: Jestem za, Jestem przeciw, Wstrzymuję się od głosu.', 'evoting' ); ?>
        </p>

        <div id="evoting-questions-container">
            <?php if ( ! empty( $questions ) ) : ?>
                <?php foreach ( $questions as $i => $q ) : ?>
                    <div class="evoting-question-row">
                        <span class="evoting-question-number"><?php echo esc_html( $i + 1 ); ?>.</span>
                        <input type="text" name="questions[]"
                               value="<?php echo esc_attr( $q->question_text ); ?>"
                               class="regular-text" placeholder="<?php esc_attr_e( 'Treść pytania', 'evoting' ); ?>">
                        <button type="button" class="button evoting-remove-question">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="evoting-question-row">
                    <span class="evoting-question-number">1.</span>
                    <input type="text" name="questions[]"
                           class="regular-text" placeholder="<?php esc_attr_e( 'Treść pytania', 'evoting' ); ?>">
                    <button type="button" class="button evoting-remove-question">&times;</button>
                </div>
            <?php endif; ?>
        </div>

        <p>
            <button type="button" id="evoting-add-question" class="button">
                + <?php esc_html_e( 'Dodaj pytanie', 'evoting' ); ?>
            </button>
        </p>

        <?php if ( $is_edit ) : ?>
            <p class="submit">
                <?php submit_button( __( 'Zapisz zmiany', 'evoting' ), 'primary', 'submit', false ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=evoting&action=results&poll_id=' . $poll->id ) ); ?>"
                   class="button"><?php esc_html_e( 'Zobacz wyniki', 'evoting' ); ?></a>
            </p>

            <hr>
            <h3><?php esc_html_e( 'Usuń głosowanie', 'evoting' ); ?></h3>
            <p>
                <button type="submit" name="evoting_action" value="delete" class="button button-link-delete"
                        onclick="return confirm('<?php esc_attr_e( 'Czy na pewno chcesz usunąć to głosowanie?', 'evoting' ); ?>');">
                    <?php esc_html_e( 'Usuń głosowanie', 'evoting' ); ?>
                </button>
            </p>
        <?php else : ?>
            <?php submit_button( __( 'Utwórz głosowanie', 'evoting' ) ); ?>
        <?php endif; ?>
    </form>
</div>
