<?php
/**
 * Plugin Name:       Open Vote
 * Plugin URI:        https://wordpress.org/plugins/open-vote/
 * Description:       Organisation polls and surveys: create votes with questions, manage groups, send invitations, view results (with optional anonymity).
 * Version:           1.0.0
 * Requires at least: 6.4
 * Tested up to:      6.7
 * Requires PHP:      8.1
 * Author:            Tomasz Kalinowski
 * Author URI:        mailto:t7jk@protonmail.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       openvote
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'OPENVOTE_VERSION', '1.0.0' );
define( 'OPENVOTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPENVOTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OPENVOTE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( OPENVOTE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OPENVOTE_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-field-map.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-role-manager.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-batch-processor.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-eligibility.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-activator.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-deactivator.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-loader.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-i18n.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote.php';
require_once OPENVOTE_PLUGIN_DIR . 'models/class-openvote-poll.php';
require_once OPENVOTE_PLUGIN_DIR . 'models/class-openvote-vote.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-results-pdf.php';

register_activation_hook( __FILE__, [ 'Openvote_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Openvote_Deactivator', 'deactivate' ] );

/**
 * Starts the plugin.
 */
function openvote_run(): void {
    $plugin = new Openvote();
    $plugin->run();
}

openvote_run();

/**
 * Dodaje link "Ustawienia" obok "Dezaktywuj" na liście wtyczek.
 *
 * @param string[] $links Akcje wtyczki (np. Deactivate).
 * @return string[] Zmodyfikowana tablica linków.
 */
function openvote_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=openvote-settings' ) ),
		esc_html__( 'Ustawienia', 'openvote' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . OPENVOTE_PLUGIN_BASENAME, 'openvote_plugin_action_links' );

// Administrator WordPress tylko z grupy "Administratorzy".
add_action( 'profile_update', [ 'Openvote_Role_Manager', 'enforce_wp_admin_group' ], 99, 2 );
add_action( 'user_register', [ 'Openvote_Role_Manager', 'enforce_wp_admin_group_on_register' ], 10, 1 );

/**
 * Zwraca slug ścieżki strony głosowania (np. „glosuj”).
 * Zapisane w opcji openvote_vote_page_slug, domyślnie „glosuj”.
 */
function openvote_get_vote_page_slug(): string {
	$slug = get_option( 'openvote_vote_page_slug', 'glosuj' );
	return is_string( $slug ) && $slug !== '' ? $slug : 'glosuj';
}

/**
 * Zwraca pełny URL strony głosowania (domena z instalacji WordPress + slug w ścieżce).
 * Np. https://mojadomena.pl/glosuj/
 */
function openvote_get_vote_page_url(): string {
	return user_trailingslashit( home_url( '/' . openvote_get_vote_page_slug() ) );
}

/**
 * URL ikony witryny WordPress (Site Icon) w rozmiarze 32×32.
 * Zastępuje dawne logo z Konfiguracji — teraz pobierane z Ustawienia → Ogólne.
 *
 * @return string URL lub pusty string gdy ikona nie jest ustawiona.
 */
function openvote_get_logo_url(): string {
	$url = get_site_icon_url( 32 );
	return is_string( $url ) ? $url : '';
}

/**
 * Zachowane dla kompatybilności wstecznej (m.in. klasa PDF).
 * Ikona witryny pochodzi z WordPress, nie z opcji wtyczki.
 *
 * @return int Zawsze 0.
 */
function openvote_get_logo_attachment_id(): int {
	return 0;
}

/**
 * Zachowane dla kompatybilności wstecznej.
 * Baner został usunięty — funkcja zwraca zawsze pusty string.
 *
 * @return string Zawsze ''.
 */
function openvote_get_banner_url(): string {
	return '';
}

/**
 * Zachowane dla kompatybilności wstecznej.
 *
 * @return int Zawsze 0.
 */
function openvote_get_banner_attachment_id(): int {
	return 0;
}

/**
 * Skrót nazwy systemu (do 12 znaków). Używany w menu admina i nagłówku.
 * Opcja: openvote_brand_short_name, domyślnie „OpenVote”.
 *
 * @return string
 */
function openvote_get_brand_short_name(): string {
	$v = get_option( 'openvote_brand_short_name', 'OpenVote' );
	$v = is_string( $v ) ? trim( $v ) : 'OpenVote';
	if ( $v === '' ) {
		return 'OpenVote';
	}
	return mb_substr( $v, 0, 12 );
}

/**
 * Pełna nazwa systemu. Używana w nagłówku panelu i np. w PDF.
 * Opcja: openvote_brand_full_name, domyślnie „E-Parlament Wolnych Ludzi”.
 *
 * @return string
 */
function openvote_get_brand_full_name(): string {
	$title = get_bloginfo( 'name' );
	return is_string( $title ) && trim( $title ) !== '' ? trim( $title ) : 'Open Vote';
}

/**
 * Adres e-mail nadawcy zaproszeń do głosowań.
 * Opcja: openvote_from_email, domyślnie noreply@<domena>.
 *
 * @return string Prawidłowy adres e-mail.
 */
function openvote_get_from_email(): string {
	$saved = get_option( 'openvote_from_email', '' );
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
function openvote_get_email_subject_template(): string {
	$saved = get_option( 'openvote_email_subject', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		return trim( $saved );
	}
	return 'Zaproszenie do głosowania: {poll_title}';
}

/**
 * Domyślna nazwa nadawcy w szablonie.
 * Używa placeholderów {brand_short} i {from_email}.
 */
function openvote_get_email_from_template(): string {
	$saved = get_option( 'openvote_email_from_template', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		return trim( $saved );
	}
	return '{brand_short} ({from_email})';
}

/**
 * Domyślna treść e-maila zaproszenia.
 * Używa placeholderów: {poll_title}, {vote_url}, {date_end}, {questions}, {brand_short}.
 */
function openvote_get_email_body_template(): string {
	$saved = get_option( 'openvote_email_body', '' );
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
function openvote_render_email_template( string $template, object $poll ): string {
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
		'{brand_short}' => openvote_get_brand_short_name(),
		'{from_email}'  => openvote_get_from_email(),
		'{vote_url}'    => openvote_get_vote_page_url(),
		'{date_end}'    => $date_end,
		'{questions}'   => $questions_text,
	];

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
}

/**
 * Metoda wysyłki e-maili: 'wordpress' (domyślna), 'smtp' lub 'sendgrid'.
 */
function openvote_get_mail_method(): string {
	$v = get_option( 'openvote_mail_method', 'wordpress' );
	return in_array( $v, [ 'wordpress', 'smtp', 'sendgrid' ], true ) ? $v : 'wordpress';
}

/**
 * Klucz API SendGrid.
 *
 * @return string Pusty string gdy nie skonfigurowano.
 */
function openvote_get_sendgrid_api_key(): string {
	return (string) get_option( 'openvote_sendgrid_api_key', '' );
}

/**
 * Liczba e-maili wysyłanych w jednej partii.
 * Domyślnie: 20 dla WP/SMTP, 100 dla SendGrid.
 *
 * @return int
 */
function openvote_get_email_batch_size(): int {
	$saved   = (int) get_option( 'openvote_email_batch_size', 0 );
	if ( $saved > 0 ) {
		return $saved;
	}
	return openvote_get_mail_method() === 'sendgrid' ? 100 : 20;
}

/**
 * Opóźnienie między partiami e-maili w sekundach.
 * Domyślnie: 2 s dla SendGrid, 3 s dla WP/SMTP.
 *
 * @return int
 */
function openvote_get_email_batch_delay(): int {
	$saved = (int) get_option( 'openvote_email_batch_delay', 0 );
	if ( $saved > 0 ) {
		return $saved;
	}
	return openvote_get_mail_method() === 'sendgrid' ? 2 : 3;
}

/**
 * Konfiguracja SMTP (tablica lub wartość jednego klucza).
 * Klucze: host, port, encryption (tls|ssl|none), username, password.
 */
function openvote_get_smtp_config(): array {
	return [
		'host'       => (string) get_option( 'openvote_smtp_host', '' ),
		'port'       => (int)    get_option( 'openvote_smtp_port', 587 ),
		'encryption' => (string) get_option( 'openvote_smtp_encryption', 'tls' ),
		'username'   => (string) get_option( 'openvote_smtp_username', '' ),
		'password'   => (string) get_option( 'openvote_smtp_password', '' ),
	];
}

// Strona głosowania (np. /?glosuj) — renderowana przez aktywny motyw WordPress.
add_filter( 'query_vars',          [ 'Openvote_Vote_Page', 'register_query_var' ] );
add_action( 'init',                [ 'Openvote_Vote_Page', 'add_rewrite_rule' ] );
add_filter( 'template_include',    [ 'Openvote_Vote_Page', 'filter_template' ] );
add_action( 'wp_enqueue_scripts',  [ 'Openvote_Vote_Page', 'enqueue_assets' ] );
add_filter( 'body_class',          [ 'Openvote_Vote_Page', 'add_body_class' ] );
add_filter( 'pre_get_document_title', [ 'Openvote_Vote_Page', 'filter_document_title' ] );
add_filter( 'the_title',              [ 'Openvote_Vote_Page', 'suppress_page_title' ], 10, 2 );

// Strona przepisów prawnych (np. /przepisy/) — dostępna dla wszystkich.
add_filter( 'query_vars',    [ 'Openvote_Law_Page', 'register_query_var' ] );
add_action( 'init',          [ 'Openvote_Law_Page', 'add_rewrite_rule' ] );
add_filter( 'template_include', [ 'Openvote_Law_Page', 'filter_template' ] );
add_filter( 'body_class',    [ 'Openvote_Law_Page', 'add_body_class' ] );


/**
 * Zwraca przesunięcie czasu dla głosowań (w godzinach, od -12 do +12).
 * Używane, gdy strefa czasowa serwera różni się od strefy administratora.
 */
function openvote_get_time_offset_hours(): int {
	$offset = (int) get_option( 'openvote_time_offset_hours', 0 );
	return max( -12, min( 12, $offset ) );
}

/**
 * Zwraca aktualny czas z uwzględnieniem przesunięcia z Konfiguracji.
 * Używaj przy sprawdzaniu okresu głosowania i licznikach.
 *
 * @param string $format Format daty (np. 'Y-m-d H:i:s' lub 'mysql').
 * @return string
 */
function openvote_current_time_for_voting( string $format = 'Y-m-d H:i:s' ): string {
	$offset_h = openvote_get_time_offset_hours();
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
function openvote_vote_page_exists(): bool {
	$slug = openvote_get_vote_page_slug();
	if ( $slug === '' ) {
		return false;
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page && $page->post_status === 'publish';
}

/**
 * Sprawdza, czy istniejąca strona głosowania zawiera aktualny blok zakładek.
 *
 * @return bool true gdy strona istnieje i ma blok openvote/voting-tabs.
 */
function openvote_vote_page_has_tabs_block(): bool {
	$slug = openvote_get_vote_page_slug();
	if ( $slug === '' ) {
		return false;
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page || $page->post_status !== 'publish' ) {
		return false;
	}
	return str_contains( $page->post_content, 'wp:openvote/voting-tabs' );
}

