<?php
/**
 * Plugin Name:       EP-RWL
 * Plugin URI:        https://example.com/evoting
 * Description:       E-Parlament Ruch Wolnych Ludzi — system e-głosowania dla WordPress. Twórz głosowania z pytaniami, pozwól zalogowanym użytkownikom głosować i przeglądaj anonimowe wyniki.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            EP-RWL
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       evoting
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'EVOTING_VERSION', '1.0.0' );
define( 'EVOTING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVOTING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EVOTING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-field-map.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-role-manager.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-batch-processor.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-eligibility.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-activator.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-deactivator.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-loader.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-i18n.php';
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting.php';
require_once EVOTING_PLUGIN_DIR . 'models/class-evoting-poll.php';
require_once EVOTING_PLUGIN_DIR . 'models/class-evoting-vote.php';

register_activation_hook( __FILE__, [ 'Evoting_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Evoting_Deactivator', 'deactivate' ] );

/**
 * Starts the plugin.
 */
function evoting_run(): void {
    $plugin = new Evoting();
    $plugin->run();
}

evoting_run();

/**
 * Dodaje link "Ustawienia" obok "Dezaktywuj" na liście wtyczek.
 *
 * @param string[] $links Akcje wtyczki (np. Deactivate).
 * @return string[] Zmodyfikowana tablica linków.
 */
function evoting_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=evoting-settings' ) ),
		esc_html__( 'Ustawienia', 'evoting' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . EVOTING_PLUGIN_BASENAME, 'evoting_plugin_action_links' );

// Administrator WordPress tylko z grupy "Administratorzy".
add_action( 'profile_update', [ 'Evoting_Role_Manager', 'enforce_wp_admin_group' ], 99, 2 );
add_action( 'user_register', [ 'Evoting_Role_Manager', 'enforce_wp_admin_group_on_register' ], 10, 1 );

/**
 * Zwraca slug ścieżki strony głosowania (np. „glosuj”).
 * Zapisane w opcji evoting_vote_page_slug, domyślnie „glosuj”.
 */
function evoting_get_vote_page_slug(): string {
	$slug = get_option( 'evoting_vote_page_slug', 'glosuj' );
	return is_string( $slug ) && $slug !== '' ? $slug : 'glosuj';
}

/**
 * Zwraca pełny URL strony głosowania (domena z instalacji WordPress + slug).
 * Np. https://mojadomena.pl/glosuj
 */
function evoting_get_vote_page_url(): string {
	return home_url( '/' . evoting_get_vote_page_slug() );
}

// Wirtualna strona głosowania (np. /glosuj) — bez szablonu WordPress.
add_filter( 'query_vars', [ 'Evoting_Vote_Page', 'register_query_var' ] );
add_action( 'init', [ 'Evoting_Vote_Page', 'add_rewrite_rule' ] );
add_action( 'template_redirect', [ 'Evoting_Vote_Page', 'maybe_serve_vote_page' ] );

/**
 * Sprawdza, czy istnieje opublikowana strona o slugu strony głosowania.
 * Używane do pokazywania przycisku „Dodaj podstronę” w konfiguracji.
 */
function evoting_vote_page_exists(): bool {
	$slug = evoting_get_vote_page_slug();
	if ( $slug === '' ) {
		return false;
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page && $page->post_status === 'publish';
}

