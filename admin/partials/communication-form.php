<?php
defined( 'ABSPATH' ) || exit;

$message_id         = $message ? (int) $message->id : 0;
$title              = $message ? $message->title : '';
$body               = $message ? $message->body : '';
$selected_group_ids = $message ? Openvote_Message::get_target_group_ids( $message ) : [];

if ( '' === $body && ! $message ) {
    $body = openvote_get_default_message_body();
}

$is_preview = ! empty( $is_preview );
if ( $is_preview && $message ) {
    $group_names = [];
    if ( ! empty( $all_groups ) && ! empty( $selected_group_ids ) ) {
        $groups_by_id = [];
        foreach ( $all_groups as $g ) {
            $groups_by_id[ (int) $g->id ] = $g;
        }
        foreach ( $selected_group_ids as $gid ) {
            if ( isset( $groups_by_id[ $gid ] ) ) {
                $group_names[] = $groups_by_id[ $gid ]->name . ' (' . ( (int) ( $groups_by_id[ $gid ]->member_count ?? 0 ) ) . ')';
            }
        }
    }
    ?>
    <div class="openvote-communication-preview" style="opacity:0.85; background:#f6f7f7; padding:16px 20px; border:1px solid #c3c4c7; border-radius:4px; max-width:900px;">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row" style="width:180px;"><?php esc_html_e( 'Tytuł wysyłki', 'openvote' ); ?></th>
                <td><?php echo esc_html( $title ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Treść wiadomości', 'openvote' ); ?></th>
                <td>
                    <div class="openvote-preview-body" style="min-height:200px; padding:12px; background:#fff; border:1px solid #dcdcde; border-radius:2px; color:#1d2327;">
                        <?php echo wp_kses_post( $body ); ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Grupa docelowa', 'openvote' ); ?></th>
                <td>
                    <?php echo $group_names ? esc_html( implode( ', ', $group_names ) ) : '—'; ?>
                </td>
            </tr>
        </table>
        <p class="description" style="margin-top:12px;">
            <strong><?php esc_html_e( 'Obsługiwane kody (zostaną zastąpione przy wysyłce):', 'openvote' ); ?></strong><br>
            <code>{Nadawca}</code>, <code>{Skrót nazwy}</code>, <code>{moja_grupa}</code>, <code>{grupa_docelowa}</code>
        </p>
    </div>
    <?php
    return;
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=openvote-communication' ) ); ?>">
    <?php wp_nonce_field( 'openvote_save_message', 'openvote_save_message_nonce' ); ?>
    <input type="hidden" name="openvote_communication_action" value="save">
    <input type="hidden" name="openvote_message_id" value="<?php echo esc_attr( $message_id ); ?>">

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="openvote_message_title"><?php esc_html_e( 'Tytuł wysyłki', 'openvote' ); ?></label></th>
            <td>
                <input type="text" name="openvote_message_title" id="openvote_message_title" class="large-text" value="<?php echo esc_attr( $title ); ?>" required>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Treść wiadomości', 'openvote' ); ?></th>
            <td>
                <?php
                wp_editor(
                    $body,
                    'openvote_message_body',
                    [
                        'textarea_name' => 'openvote_message_body',
                        'media_buttons' => true,
                        'textarea_rows' => 15,
                        'teeny'         => false,
                        'quicktags'     => true,
                        'tinymce'       => [
                            'toolbar1' => 'formatselect,bold,italic,underline,blockquote,bullist,numlist,link,unlink,wp_more,fullscreen',
                        ],
                    ]
                );
                ?>
                <p style="margin-top:8px;">
                    <button type="button" id="openvote-reset-message-body" class="button"><?php esc_html_e( 'Reset', 'openvote' ); ?></button>
                </p>
                <p class="description" style="margin-top:12px;">
                    <strong><?php esc_html_e( 'Obsługiwane kody (zostaną zastąpione przy wysyłce):', 'openvote' ); ?></strong><br>
                    <code>{Nadawca}</code> — <?php esc_html_e( 'imię i nazwisko osoby zalogowanej, autor', 'openvote' ); ?><br>
                    <code>{Skrót nazwy}</code> — <?php esc_html_e( 'skrót nazwy organizacji (z Konfiguracji)', 'openvote' ); ?><br>
                    <code>{moja_grupa}</code> — <?php esc_html_e( 'nazwa grupy odbiorcy (z listy grup docelowych)', 'openvote' ); ?><br>
                    <code>{grupa_docelowa}</code> — <?php esc_html_e( 'lista nazw wszystkich grup docelowych wysyłki (alfabetycznie, po przecinku)', 'openvote' ); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Grupa docelowa', 'openvote' ); ?></th>
            <td>
                <?php if ( ! empty( $all_groups ) ) : ?>
                    <select name="target_groups[]" id="openvote-message-target-groups" multiple size="8" style="min-width:280px;">
                        <?php foreach ( $all_groups as $group ) : ?>
                            <option value="<?php echo esc_attr( $group->id ); ?>"
                                    <?php echo in_array( (int) $group->id, $selected_group_ids, true ) ? 'selected' : ''; ?>>
                                <?php echo esc_html( $group->name . ' (' . ( (int) ( $group->member_count ?? 0 ) ) . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Ctrl+klik: wiele grup. Przewiń listę.', 'openvote' ); ?></p>
                <?php else : ?>
                    <p class="description"><?php esc_html_e( 'Brak grup. Dodaj grupy w sekcji Grupy.', 'openvote' ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="submit" name="openvote_save_message_submit" class="button button-primary">
            <?php esc_html_e( 'Zapisz', 'openvote' ); ?>
        </button>
    </p>
</form>
