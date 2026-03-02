<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';

$poll_admins = Openvote_Role_Manager::get_poll_admins();
$editors     = Openvote_Role_Manager::get_poll_editors();
$current_uid = get_current_user_id();

// Lista do wyboru koordynatora — limit 300 ze względu na wydajność przy dużej bazie (np. 10k+).
$openvote_roles_list_limit = 300;
$all_users_for_role = get_users( [
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'number'  => $openvote_roles_list_limit,
    'exclude' => array_map( fn( $u ) => $u->ID, $poll_admins ),
] );

$groups = $wpdb->get_results( "SELECT * FROM {$groups_table} ORDER BY name ASC" );

/**
 * Format: Imię Nazwisko - login (Miasto). Bez (Miasto) gdy miasto nieużywane lub puste.
 *
 * @param \WP_User $user
 * @return string
 */
function openvote_roles_format_user_display( \WP_User $user ): string {
    $first = trim( (string) Openvote_Field_Map::get_user_value( $user, 'first_name' ) );
    $last  = trim( (string) Openvote_Field_Map::get_user_value( $user, 'last_name' ) );
    $nick  = trim( (string) Openvote_Field_Map::get_user_value( $user, 'nickname' ) );
    if ( $nick === '' ) {
        $nick = $user->user_login;
    }
    $full_name = trim( $first . ' ' . $last );
    $parts     = $full_name !== '' ? [ $full_name, $nick ] : [ $nick ];
    $display   = implode( ' - ', $parts );
    if ( ! Openvote_Field_Map::is_city_disabled() ) {
        $city = trim( (string) Openvote_Field_Map::get_user_value( $user, 'city' ) );
        if ( $city !== '' ) {
            $display .= ' (' . $city . ')';
        }
    }
    return $display;
}
?>
<div class="wrap openvote-roles-page">
    <h1><?php esc_html_e( 'Koordynatorzy i Sejmiki', 'openvote' ); ?></h1>
    <p class="description" style="max-width:720px; margin:8px 0 24px;">
        <?php esc_html_e( 'Dodanie użytkownika do sejmiku powoduje, że staje się koordynatorem tego sejmiku (jednego lub wielu) i może uruchamiać dla tych sejmików głosowania dla członków sejmiku. Jeden koordynator może być przypisany do jednego lub wielu sejmików.', 'openvote' ); ?>
    </p>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Zmiany zostały zapisane.', 'openvote' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $role_error = get_transient( 'openvote_roles_error' );
    if ( $role_error ) {
        delete_transient( 'openvote_roles_error' );
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $role_error ); ?></p>
        </div>
    <?php } ?>

    <!-- 1. Lista obecnych koordynatorów -->
    <section class="openvote-roles-list" style="margin-bottom:32px;">
        <h2 class="openvote-section-title" style="margin:0 0 12px; font-size:1.1em; font-weight:600;"><?php esc_html_e( 'Obecni koordynatorzy', 'openvote' ); ?></h2>
        <table class="widefat striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Koordynator', 'openvote' ); ?></th>
                    <th><?php esc_html_e( 'Sejmiki', 'openvote' ); ?></th>
                    <th style="width:120px;"><?php esc_html_e( 'Akcja', 'openvote' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $editors ) ) : ?>
                    <tr><td colspan="3"><?php esc_html_e( 'Brak Koordynatorów.', 'openvote' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $editors as $u ) :
                        $group_ids = Openvote_Role_Manager::get_user_groups( $u->ID );
                        $user_groups = [];
                        foreach ( $groups as $g ) {
                            if ( in_array( (int) $g->id, $group_ids, true ) ) {
                                $user_groups[] = $g;
                            }
                        }
                        $can_remove = Openvote_Role_Manager::can_remove( $current_uid, $u->ID );
                        ?>
                        <tr>
                            <td><?php echo esc_html( openvote_roles_format_user_display( $u ) ); ?></td>
                            <td>
                                <?php
                                if ( empty( $user_groups ) ) {
                                    echo '—';
                                } else {
                                    $parts = [];
                                    foreach ( $user_groups as $g ) {
                                        if ( $can_remove ) {
                                            $parts[] = '<form method="post" action="" style="display:inline;">' . wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce', false, false ) . '<input type="hidden" name="openvote_roles_action" value="remove_group"><input type="hidden" name="user_id" value="' . esc_attr( $u->ID ) . '"><input type="hidden" name="group_id" value="' . esc_attr( $g->id ) . '"><button type="submit" class="button-link">' . esc_html( $g->name ) . '</button></form>';
                                        } else {
                                            $parts[] = esc_html( $g->name );
                                        }
                                    }
                                    echo implode( ', ', $parts );
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ( $can_remove ) : ?>
                                    <form method="post" action="" style="display:inline;">
                                        <?php wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce' ); ?>
                                        <input type="hidden" name="openvote_roles_action" value="remove_role">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr( $u->ID ); ?>">
                                        <button type="submit" class="button button-small"><?php esc_html_e( 'Odłącz wszystko', 'openvote' ); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <?php if ( ! empty( $groups ) ) : ?>
    <!-- 2. Dodaj koordynatora — jedna sekcja formularza -->
    <section class="openvote-roles-add" style="max-width:800px; border:1px solid #c3c4c7; border-radius:4px; padding:20px 24px; background:#fff; box-shadow:0 1px 1px rgba(0,0,0,.04);">
        <h2 class="openvote-section-title" style="margin:0 0 16px; font-size:1.1em; font-weight:600; padding-bottom:8px; border-bottom:1px solid #eee;"><?php esc_html_e( 'Dodaj koordynatora', 'openvote' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce' ); ?>
            <input type="hidden" name="openvote_roles_action" value="add_poll_editor">

            <div style="display:flex; align-items:stretch; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <h3 style="margin:0 0 6px; font-size:13px; font-weight:600; color:#1d2327;"><?php esc_html_e( 'Użytkownicy', 'openvote' ); ?></h3>
                    <p class="description" style="margin:0 0 8px;"><?php printf( esc_html__( 'Wybierz użytkownika (max %d).', 'openvote' ), (int) $openvote_roles_list_limit ); ?></p>
                    <select name="user_id" id="openvote_add_editor_user" size="12" style="min-width:100%; display:block;">
                        <option value="">— <?php esc_html_e( 'Wybierz', 'openvote' ); ?> —</option>
                        <?php foreach ( $all_users_for_role as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( openvote_roles_format_user_display( $u ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex; align-items:center; flex-shrink:0;">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Dodaj >>', 'openvote' ); ?></button>
                </div>

                <div style="flex:1; min-width:200px;">
                    <h3 style="margin:0 0 6px; font-size:13px; font-weight:600; color:#1d2327;"><?php esc_html_e( 'Sejmiki', 'openvote' ); ?></h3>
                    <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Ctrl+klik: wiele sejmików.', 'openvote' ); ?></p>
                    <select name="openvote_editor_groups[]" id="openvote_editor_groups" multiple size="12" style="min-width:100%; display:block;">
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </section>

    <!-- 3. Dodaj koordynatora po e-mailu (wyszukiwarka, bazy 10k+) -->
    <section class="openvote-roles-add-by-email" style="max-width:800px; margin-top:24px; border:1px solid #c3c4c7; border-radius:4px; padding:20px 24px; background:#fff; box-shadow:0 1px 1px rgba(0,0,0,.04);">
        <h2 class="openvote-section-title" style="margin:0 0 16px; font-size:1.1em; font-weight:600; padding-bottom:8px; border-bottom:1px solid #eee;"><?php esc_html_e( 'Dodaj koordynatora po adresie e-mail', 'openvote' ); ?></h2>
        <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Wpisz fragment adresu e-mail (min. 2 znaki). Po opóźnieniu wyniki wyszukiwania pojawią się poniżej. Wybierz użytkownika i sejmiki, następnie kliknij Dodaj >>.', 'openvote' ); ?></p>
        <form method="post" action="" id="openvote-add-coordinator-by-email-form">
            <?php wp_nonce_field( 'openvote_roles_action', 'openvote_roles_nonce' ); ?>
            <input type="hidden" name="openvote_roles_action" value="add_poll_editor">
            <input type="hidden" name="user_id" id="openvote_email_search_user_id" value="">

            <div style="display:flex; align-items:stretch; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <label for="openvote_email_search_input" class="screen-reader-text"><?php esc_html_e( 'Szukaj po e-mailu', 'openvote' ); ?></label>
                    <input type="text"
                           id="openvote_email_search_input"
                           autocomplete="off"
                           placeholder="<?php esc_attr_e( 'Wpisz adres e-mail lub fragment...', 'openvote' ); ?>"
                           style="width:100%; padding:8px 12px; margin-bottom:6px;">
                    <div id="openvote_email_search_status" aria-live="polite" style="min-height:20px; font-size:12px; color:#646970;"></div>
                    <div id="openvote_email_search_results" style="min-height:80px; border:1px solid #c3c4c7; border-radius:4px; background:#f6f7f7; max-height:200px; overflow-y:auto;"></div>
                    <div id="openvote_email_selected_user" style="margin-top:8px; font-weight:600; color:#1d2327;"></div>
                </div>

                <div style="display:flex; align-items:center; flex-shrink:0;">
                    <button type="submit" class="button button-primary" id="openvote_add_by_email_btn" disabled><?php esc_html_e( 'Dodaj >>', 'openvote' ); ?></button>
                </div>

                <div style="flex:1; min-width:200px;">
                    <h3 style="margin:0 0 6px; font-size:13px; font-weight:600; color:#1d2327;"><?php esc_html_e( 'Sejmiki', 'openvote' ); ?></h3>
                    <p class="description" style="margin:0 0 8px;"><?php esc_html_e( 'Ctrl+klik: wiele sejmików.', 'openvote' ); ?></p>
                    <select name="openvote_editor_groups[]" id="openvote_editor_groups_by_email" multiple size="12" style="min-width:100%; display:block;">
                        <?php foreach ( $groups as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </section>
    <?php else : ?>
        <p class="description"><?php esc_html_e( 'Najpierw dodaj sejmiki w sekcji Sejmiki, aby móc przypisywać Koordynatorów.', 'openvote' ); ?></p>
    <?php endif; ?>
</div>

<?php if ( ! empty( $groups ) ) : ?>
<script>
(function() {
    var DEBOUNCE_MS = 400;
    var MIN_CHARS = 2;
    var searchInput = document.getElementById('openvote_email_search_input');
    var searchStatus = document.getElementById('openvote_email_search_status');
    var searchResults = document.getElementById('openvote_email_search_results');
    var selectedDisplay = document.getElementById('openvote_email_selected_user');
    var userIdInput = document.getElementById('openvote_email_search_user_id');
    var addBtn = document.getElementById('openvote_add_by_email_btn');
    var timer = null;
    var lastQuery = '';

    function setStatus(text) {
        if (searchStatus) searchStatus.textContent = text || '';
    }

    function clearSelection() {
        if (userIdInput) userIdInput.value = '';
        if (selectedDisplay) selectedDisplay.textContent = '';
        if (addBtn) addBtn.disabled = true;
    }

    function selectUser(id, label) {
        if (userIdInput) userIdInput.value = id;
        if (selectedDisplay) selectedDisplay.textContent = label;
        if (addBtn) addBtn.disabled = false;
        if (searchResults) searchResults.innerHTML = '';
        setStatus('');
    }

    function runSearch() {
        var q = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
        if (q.length < MIN_CHARS) {
            setStatus('');
            if (searchResults) searchResults.innerHTML = '';
            clearSelection();
            return;
        }
        lastQuery = q;
        setStatus('<?php echo esc_js( __( 'Szukam…', 'openvote' ) ); ?>');
        if (searchResults) searchResults.innerHTML = '';

        var apiRoot = <?php echo json_encode( esc_url_raw( rest_url( 'openvote/v1' ) ) ); ?>;
        var nonce = <?php echo json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

        fetch( apiRoot + '/users/search-by-email?email=' + encodeURIComponent(q), {
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce },
            credentials: 'same-origin'
        } )
            .then(function(r) {
                if (!r.ok) {
                    return r.json().then(function(err) {
                        throw new Error(err && err.message ? err.message : r.statusText || 'Błąd ' + r.status);
                    }).catch(function() {
                        throw new Error(r.statusText || 'Błąd ' + r.status);
                    });
                }
                return r.json();
            })
            .then(function(data) {
                if (lastQuery !== (searchInput && searchInput.value ? searchInput.value.trim() : '')) return;
                setStatus('');
                if (!searchResults) return;
                if (!Array.isArray(data) || data.length === 0) {
                    searchResults.innerHTML = '<p style="margin:8px 12px; color:#646970;"><?php echo esc_js( __( 'Brak wyników.', 'openvote' ) ); ?></p>';
                    return;
                }
                searchResults.innerHTML = '';
                data.forEach(function(item) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'button button-small';
                    btn.style.cssText = 'display:block; width:100%; text-align:left; margin:4px 8px; padding:6px 10px;';
                    btn.textContent = item.label + ' (' + item.email + ')';
                    btn.addEventListener('click', function() { selectUser(item.id, item.label); });
                    searchResults.appendChild(btn);
                });
            })
            .catch(function(err) {
                if (lastQuery !== (searchInput && searchInput.value ? searchInput.value.trim() : '')) return;
                setStatus(err && err.message ? err.message : '<?php echo esc_js( __( 'Błąd wyszukiwania.', 'openvote' ) ); ?>');
            });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearSelection();
            if (timer) clearTimeout(timer);
            var q = searchInput.value.trim();
            if (q.length < MIN_CHARS) {
                setStatus('');
                if (searchResults) searchResults.innerHTML = '';
                return;
            }
            timer = setTimeout(runSearch, DEBOUNCE_MS);
        });
        searchInput.addEventListener('focus', function() {
            if (searchInput.value.trim().length >= MIN_CHARS && searchResults && !searchResults.innerHTML) runSearch();
        });
    }
})();
</script>
<?php endif; ?>
