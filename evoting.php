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
