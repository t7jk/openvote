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

if ( file_exists( EVOTING_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once EVOTING_PLUGIN_DIR . 'vendor/autoload.php';
}

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
require_once EVOTING_PLUGIN_DIR . 'includes/class-evoting-results-pdf.php';

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
	return home_url( '/?' . evoting_get_vote_page_slug() );
}

/**
 * URL ikony witryny WordPress (Site Icon) w rozmiarze 32×32.
 * Zastępuje dawne logo z Konfiguracji — teraz pobierane z Ustawienia → Ogólne.
 *
 * @return string URL lub pusty string gdy ikona nie jest ustawiona.
 */
function evoting_get_logo_url(): string {
	$url = get_site_icon_url( 32 );
	return is_string( $url ) ? $url : '';
}

/**
 * Zachowane dla kompatybilności wstecznej (m.in. klasa PDF).
 * Ikona witryny pochodzi z WordPress, nie z opcji wtyczki.
 *
 * @return int Zawsze 0.
 */
function evoting_get_logo_attachment_id(): int {
	return 0;
}

/**
 * Zachowane dla kompatybilności wstecznej.
 * Baner został usunięty — funkcja zwraca zawsze pusty string.
 *
 * @return string Zawsze ''.
 */
function evoting_get_banner_url(): string {
	return '';
}

/**
 * Zachowane dla kompatybilności wstecznej.
 *
 * @return int Zawsze 0.
 */
function evoting_get_banner_attachment_id(): int {
	return 0;
}

/**
 * Skrót nazwy systemu (do 6 znaków). Używany w menu admina i nagłówku.
 * Opcja: evoting_brand_short_name, domyślnie „EP-RWL”.
 *
 * @return string
 */
function evoting_get_brand_short_name(): string {
	$v = get_option( 'evoting_brand_short_name', 'EP-RWL' );
	$v = is_string( $v ) ? trim( $v ) : 'EP-RWL';
	if ( $v === '' ) {
		return 'EP-RWL';
	}
	return mb_substr( $v, 0, 6 );
}

/**
 * Pełna nazwa systemu. Używana w nagłówku panelu i np. w PDF.
 * Opcja: evoting_brand_full_name, domyślnie „E-Parlament Wolnych Ludzi”.
 *
 * @return string
 */
function evoting_get_brand_full_name(): string {
	$title = get_bloginfo( 'name' );
	return is_string( $title ) && trim( $title ) !== '' ? trim( $title ) : 'E-Głosowania';
}

/**
 * Adres e-mail nadawcy zaproszeń do głosowań.
 * Opcja: evoting_from_email, domyślnie noreply@<domena>.
 *
 * @return string Prawidłowy adres e-mail.
 */
function evoting_get_from_email(): string {
	$saved = get_option( 'evoting_from_email', '' );
	if ( is_string( $saved ) && is_email( trim( $saved ) ) ) {
		return trim( $saved );
	}
	$domain = wp_parse_url( home_url(), PHP_URL_HOST );
	return 'noreply@' . ( $domain ?: 'example.com' );
}

// ── Szablon e-maila zapraszającego ───────────────────────────────────────

/**
 * Domyślny temat e-maila zaproszenia.
 * Używa placeholdera {poll_title}.
 */
function evoting_get_email_subject_template(): string {
	$saved = get_option( 'evoting_email_subject', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		return trim( $saved );
	}
	return 'Zaproszenie do głosowania: {poll_title}';
}

/**
 * Domyślna nazwa nadawcy w szablonie.
 * Używa placeholderów {brand_short} i {from_email}.
 */
function evoting_get_email_from_template(): string {
	$saved = get_option( 'evoting_email_from_template', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		return trim( $saved );
	}
	return '{brand_short} ({from_email})';
}

/**
 * Domyślna treść e-maila zaproszenia.
 * Używa placeholderów: {poll_title}, {vote_url}, {date_end}, {questions}, {brand_short}.
 */
function evoting_get_email_body_template(): string {
	$saved = get_option( 'evoting_email_body', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		return trim( $saved );
	}
	return 'Zapraszamy do wzięcia udziału w głosowaniu pod tytułem: {poll_title}.

Głosowanie jest przeprowadzane na stronie: {vote_url}
i potrwa do: {date_end}.

Oto lista pytań w głosowaniu:
{questions}

Zapraszamy do głosowania!
Zespół {brand_short}';
}

/**
 * Podmień placeholdery w szablonie e-maila na rzeczywiste wartości.
 *
 * Dostępne zmienne:
 *   {poll_title}  — tytuł głosowania
 *   {brand_short} — skrót nazwy systemu
 *   {from_email}  — adres e-mail nadawcy
 *   {vote_url}    — URL strony głosowania
 *   {date_end}    — data i godzina zakończenia głosowania
 *   {questions}   — lista pytań z odpowiedziami
 *
 * @param string   $template Szablon z placeholderami.
 * @param object   $poll     Obiekt głosowania (z ->title, ->date_end, ->questions).
 * @return string  Gotowy tekst po podstawieniu.
 */
function evoting_render_email_template( string $template, object $poll ): string {
	$questions_text = '';
	if ( ! empty( $poll->questions ) ) {
		foreach ( $poll->questions as $i => $q ) {
			$questions_text .= ( $i + 1 ) . '. ' . $q->body . "\n";
			if ( ! empty( $q->answers ) ) {
				foreach ( $q->answers as $a ) {
					$questions_text .= '   - ' . $a->body . "\n";
				}
			}
			$questions_text .= "\n";
		}
		$questions_text = rtrim( $questions_text );
	}

	$end_raw = $poll->date_end ?? '';
	if ( strlen( $end_raw ) === 10 ) {
		$end_raw .= ' 23:59:59';
	}
	try {
		$end_dt   = new DateTimeImmutable( $end_raw, wp_timezone() );
		$date_end = $end_dt->format( 'd.m.Y H:i' );
	} catch ( \Exception $e ) {
		$date_end = $poll->date_end ?? '';
	}

	$replacements = [
		'{poll_title}'  => $poll->title ?? '',
		'{brand_short}' => evoting_get_brand_short_name(),
		'{from_email}'  => evoting_get_from_email(),
		'{vote_url}'    => evoting_get_vote_page_url(),
		'{date_end}'    => $date_end,
		'{questions}'   => $questions_text,
	];

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
}

/**
 * Metoda wysyłki e-maili: 'wordpress' (domyślna), 'smtp' lub 'sendgrid'.
 */
function evoting_get_mail_method(): string {
	$v = get_option( 'evoting_mail_method', 'wordpress' );
	return in_array( $v, [ 'wordpress', 'smtp', 'sendgrid' ], true ) ? $v : 'wordpress';
}

/**
 * Klucz API SendGrid.
 *
 * @return string Pusty string gdy nie skonfigurowano.
 */
function evoting_get_sendgrid_api_key(): string {
	return (string) get_option( 'evoting_sendgrid_api_key', '' );
}

/**
 * Liczba e-maili wysyłanych w jednej partii.
 * Domyślnie: 20 dla WP/SMTP, 100 dla SendGrid.
 *
 * @return int
 */
function evoting_get_email_batch_size(): int {
	$saved   = (int) get_option( 'evoting_email_batch_size', 0 );
	if ( $saved > 0 ) {
		return $saved;
	}
	return evoting_get_mail_method() === 'sendgrid' ? 100 : 20;
}

/**
 * Opóźnienie między partiami e-maili w sekundach.
 * Domyślnie: 2 s dla SendGrid, 3 s dla WP/SMTP.
 *
 * @return int
 */
function evoting_get_email_batch_delay(): int {
	$saved = (int) get_option( 'evoting_email_batch_delay', 0 );
	if ( $saved > 0 ) {
		return $saved;
	}
	return evoting_get_mail_method() === 'sendgrid' ? 2 : 3;
}

/**
 * Konfiguracja SMTP (tablica lub wartość jednego klucza).
 * Klucze: host, port, encryption (tls|ssl|none), username, password.
 */
function evoting_get_smtp_config(): array {
	return [
		'host'       => (string) get_option( 'evoting_smtp_host', '' ),
		'port'       => (int)    get_option( 'evoting_smtp_port', 587 ),
		'encryption' => (string) get_option( 'evoting_smtp_encryption', 'tls' ),
		'username'   => (string) get_option( 'evoting_smtp_username', '' ),
		'password'   => (string) get_option( 'evoting_smtp_password', '' ),
	];
}

// Strona głosowania (np. /?glosuj) — renderowana przez aktywny motyw WordPress.
add_filter( 'query_vars',          [ 'Evoting_Vote_Page', 'register_query_var' ] );
add_action( 'init',                [ 'Evoting_Vote_Page', 'add_rewrite_rule' ] );
add_filter( 'template_include',    [ 'Evoting_Vote_Page', 'filter_template' ] );
add_action( 'wp_enqueue_scripts',  [ 'Evoting_Vote_Page', 'enqueue_assets' ] );
add_filter( 'body_class',          [ 'Evoting_Vote_Page', 'add_body_class' ] );
add_filter( 'pre_get_document_title', [ 'Evoting_Vote_Page', 'filter_document_title' ] );
add_filter( 'the_title',              [ 'Evoting_Vote_Page', 'suppress_page_title' ], 10, 2 );

// Strona przepisów prawnych (np. /przepisy/) — dostępna dla wszystkich.
add_filter( 'query_vars',    [ 'Evoting_Law_Page', 'register_query_var' ] );
add_action( 'init',          [ 'Evoting_Law_Page', 'add_rewrite_rule' ] );
add_filter( 'template_include', [ 'Evoting_Law_Page', 'filter_template' ] );
add_filter( 'body_class',    [ 'Evoting_Law_Page', 'add_body_class' ] );


/**
 * Zwraca przesunięcie czasu dla głosowań (w godzinach, od -12 do +12).
 * Używane, gdy strefa czasowa serwera różni się od strefy administratora.
 */
function evoting_get_time_offset_hours(): int {
	$offset = (int) get_option( 'evoting_time_offset_hours', 0 );
	return max( -12, min( 12, $offset ) );
}

/**
 * Zwraca aktualny czas z uwzględnieniem przesunięcia z Konfiguracji.
 * Używaj przy sprawdzaniu okresu głosowania i licznikach.
 *
 * @param string $format Format daty (np. 'Y-m-d H:i:s' lub 'mysql').
 * @return string
 */
function evoting_current_time_for_voting( string $format = 'Y-m-d H:i:s' ): string {
	$offset_h = evoting_get_time_offset_hours();
	$wp_now   = current_time( 'Y-m-d H:i:s' );
	$tz       = wp_timezone();
	$dt       = date_create( $wp_now, $tz );
	if ( ! $dt ) {
		return $wp_now;
	}
	$dt->modify( ( $offset_h >= 0 ? '+' : '' ) . $offset_h . ' hours' );
	$fmt = ( $format === 'mysql' ) ? 'Y-m-d H:i:s' : $format;
	return $dt->format( $fmt );
}

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

/**
 * Sprawdza, czy istniejąca strona głosowania zawiera aktualny blok zakładek.
 *
 * @return bool true gdy strona istnieje i ma blok evoting/voting-tabs.
 */
function evoting_vote_page_has_tabs_block(): bool {
	$slug = evoting_get_vote_page_slug();
	if ( $slug === '' ) {
		return false;
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page || $page->post_status !== 'publish' ) {
		return false;
	}
	return str_contains( $page->post_content, 'wp:evoting/voting-tabs' );
}

