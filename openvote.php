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
define( 'OPENVOTE_GITHUB_URL', 'https://github.com/t7jk/openvote' );
define( 'OPENVOTE_PLUGIN_AUTHOR', 'Tomasz Kalinowski' );

if ( file_exists( OPENVOTE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OPENVOTE_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-field-map.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-role-manager.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-role-map.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-batch-processor.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-cron-sync.php';
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

Openvote_Cron_Sync::register();
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

/**
 * Zwraca mapę rola → ekrany (z domyślnymi), do UI Konfiguracji i sprawdzania dostępu.
 *
 * @return array<string, array<string, int>>
 */
function openvote_get_role_screen_map(): array {
	return Openvote_Role_Map::get_map();
}

/**
 * Czy użytkownik ma dostęp do danego ekranu według mapy ról.
 */
function openvote_user_can_access_screen( int $user_id, string $screen_slug ): bool {
	return Openvote_Role_Map::user_can_access_screen( $user_id, $screen_slug );
}

/**
 * Zakres dostępu koordynatora: 'all' = wszystkie sejmiki, 'own' = tylko przypisane.
 */
function openvote_coordinator_poll_access_scope(): string {
	$v = get_option( 'openvote_coordinator_poll_access', 'all' );
	return ( $v === 'own' ) ? 'own' : 'all';
}

/**
 * Czy bieżący użytkownik jest koordynatorem z ograniczeniem do własnych sejmików?
 * (Nie administrator/edytor/autor WP — tylko rola koordynatora i opcja „own”.)
 */
function openvote_is_coordinator_restricted_to_own_groups(): bool {
	if ( openvote_coordinator_poll_access_scope() !== 'own' ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' ) ) {
		return false;
	}
	return Openvote_Role_Map::user_is_coordinator( get_current_user_id() );
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
 * Typ szablonu e-maila: 'plain' (czysty tekst) lub 'html'.
 */
function openvote_get_email_template_type(): string {
	$v = get_option( 'openvote_email_template_type', 'plain' );
	return ( $v === 'html' ) ? 'html' : 'plain';
}

/**
 * Domyślna treść e-maila (wersja czysty tekst).
 * Zawiera stopkę z {plugin_author}, {github_url}.
 */
function openvote_get_email_body_plain_default(): string {
	$footer = "\n\n\n──────────────────────────────────────────────────\n" .
		"Otwarte Głosowanie (Open Vote)\n" .
		'Autor systemu: ' . OPENVOTE_PLUGIN_AUTHOR . "\n" .
		'Kod źródłowy (Open Source): ' . OPENVOTE_GITHUB_URL . "\n" .
		"──────────────────────────────────────────────────";
	return "Szanowni Państwo,\n\nmamy zaszczyt zaprosić Państwa do udziału w głosowaniu elektronicznym:\n\n  „{poll_title}\"\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nZAGADNIENIA PODDANE POD GŁOSOWANIE:\n\n{questions}\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n  Głosowanie dostępne pod adresem:\n  {vote_url}\n\n  Termin głosowania: do {date_end}\n\nKażdy głos ma znaczenie – zachęcamy do wzięcia udziału.\n\nZapraszamy do głosowania!\n\nZespół {brand_short} - {site_name}\n{site_tagline}" . $footer;
}

/**
 * Domyślna treść e-maila (wersja HTML).
 */
function openvote_get_email_body_html_default(): string {
	return '<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #2c2c2c; background: #f5f5f5; margin: 0; padding: 20px; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  .header { background: #1a3c6e; color: #ffffff; padding: 32px 40px; text-align: center; }
  .header h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: 0.5px; }
  .header p { margin: 8px 0 0; font-size: 14px; opacity: 0.8; }
  .header p.header__tagline { margin-top: 4px; font-size: 13px; opacity: 0.9; }
  .header p:empty { display: none; }
  .body { padding: 36px 40px; }
  .body p { line-height: 1.7; font-size: 15px; }
  .poll-title { font-size: 18px; font-weight: 700; color: #1a3c6e; margin: 16px 0; }
  .questions { background: #f0f4fa; border-left: 4px solid #1a3c6e; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
  .questions h3 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
  .questions ul { margin: 0; padding-left: 18px; }
  .questions ul li { margin-bottom: 6px; font-size: 15px; }
  .deadline { font-size: 14px; color: #666; margin: 16px 0; }
  .deadline strong { color: #c0392b; }
  .cta { text-align: center; margin: 28px 0; }
  .cta a { background: #1a3c6e; color: #ffffff; padding: 14px 36px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600; display: inline-block; letter-spacing: 0.3px; }
  .footer { text-align: center; padding: 20px 40px; background: #f5f5f5; font-size: 13px; color: #999; border-top: 1px solid #e0e0e0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Zaproszenie do głosowania</h1>
    <p>{brand_short} — {site_name}</p>
    <p class="header__tagline">{site_tagline}</p>
  </div>
  <div class="body">
    <p>Szanowni Państwo,</p>
    <p>mamy zaszczyt zaprosić Państwa do udziału w głosowaniu elektronicznym:</p>
    <div class="poll-title">„{poll_title}"</div>
    <div class="questions">
      <h3>Zagadnienia poddane pod głosowanie</h3>
      {questions}
    </div>
    <div class="deadline">Termin głosowania: <strong>do {date_end}</strong></div>
    <div class="cta">
      <a href="{vote_url}">Przejdź do głosowania →</a>
    </div>
    <p>Każdy głos ma znaczenie. Dziękujemy za zaangażowanie.</p>
  </div>
  <div class="footer">
    © {brand_short} &nbsp;|&nbsp; Wiadomość wygenerowana automatycznie<br><br>
    <span style="font-size:12px; color:#bbb;">
      Głosowanie przeprowadzono na stronie <a href="{site_url}" style="color:#bbb;">{site_url}</a><br>
      System: <em>Otwarte Głosowanie (Open Vote)</em> &mdash; autor: ' . OPENVOTE_PLUGIN_AUTHOR . ' &mdash; <a href="' . OPENVOTE_GITHUB_URL . '" style="color:#bbb;">kod źródłowy na GitHub</a>
    </span>
  </div>
</div>
</body>
</html>';
}

/**
 * Treść e-maila zaproszenia (wersja czysty tekst). Zapis lub domyślna.
 */
function openvote_get_email_body_plain_template(): string {
	$saved = get_option( 'openvote_email_body_plain', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		return trim( $saved );
	}
	// Kompatybilność: stara opcja openvote_email_body jako plain.
	$legacy = get_option( 'openvote_email_body', '' );
	if ( is_string( $legacy ) && trim( $legacy ) !== '' ) {
		return trim( $legacy );
	}
	return openvote_get_email_body_plain_default();
}

/**
 * Treść e-maila zaproszenia (wersja HTML). Zapis lub domyślna.
 * Aktualizuje zapisany szablon ze starym nagłówkiem (tylko {brand_short}) do wersji z {site_name} i {site_tagline}.
 */
function openvote_get_email_body_html_template(): string {
	$saved = get_option( 'openvote_email_body_html', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		$saved = trim( $saved );
		// Uaktualnij stary nagłówek (tylko {brand_short}) do wersji z nazwą witryny i sloganem.
		$old_header = '<p>{brand_short}</p>';
		$new_header = '<p>{brand_short} — {site_name}</p>' . "\n    " . '<p class="header__tagline">{site_tagline}</p>';
		if ( str_contains( $saved, $old_header ) && ! str_contains( $saved, '{site_name}' ) ) {
			$saved = str_replace( $old_header, $new_header, $saved );
		}
		return $saved;
	}
	return openvote_get_email_body_html_default();
}

/**
 * Treść e-maila zaproszenia dla aktualnie wybranego typu (plain/html).
 */
function openvote_get_email_body_template(): string {
	return openvote_get_email_template_type() === 'html'
		? openvote_get_email_body_html_template()
		: openvote_get_email_body_plain_template();
}

/**
 * Podmień placeholdery w szablonie e-maila na rzeczywiste wartości.
 *
 * Dostępne zmienne: {poll_title}, {brand_short}, {from_email}, {vote_url}, {date_start}, {date_end},
 * {questions}, {site_url}, {site_name}, {site_tagline}, {plugin_author}, {github_url}.
 *
 * @param string $template Szablon z placeholderami.
 * @param object $poll     Obiekt głosowania (z ->title, ->date_start, ->date_end, ->questions).
 * @param string $format   'plain' lub 'html' — format listy {questions}.
 * @return string Gotowy tekst po podstawieniu.
 */
function openvote_render_email_template( string $template, object $poll, string $format = 'plain' ): string {
	$questions_text = '';
	if ( ! empty( $poll->questions ) ) {
		if ( $format === 'html' ) {
			$questions_text .= '<ul>';
			foreach ( $poll->questions as $q ) {
				$questions_text .= '<li>' . esc_html( $q->body );
				if ( ! empty( $q->answers ) ) {
					$questions_text .= '<ul>';
					foreach ( $q->answers as $a ) {
						$questions_text .= '<li>' . esc_html( $a->body ) . '</li>';
					}
					$questions_text .= '</ul>';
				}
				$questions_text .= '</li>';
			}
			$questions_text .= '</ul>';
		} else {
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
	}

	$start_raw = $poll->date_start ?? '';
	if ( strlen( $start_raw ) === 10 ) {
		$start_raw .= ' 00:00:00';
	}
	try {
		$start_dt   = new DateTimeImmutable( $start_raw, wp_timezone() );
		$date_start = $start_dt->format( 'd.m.Y H:i' );
	} catch ( \Exception $e ) {
		$date_start = $poll->date_start ?? '';
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

	$site_name   = get_bloginfo( 'name' );
	$site_tagline = get_bloginfo( 'description' );
	$replacements = [
		'{poll_title}'    => $poll->title ?? '',
		'{brand_short}'   => openvote_get_brand_short_name(),
		'{from_email}'    => openvote_get_from_email(),
		'{vote_url}'      => openvote_get_vote_page_url(),
		'{date_start}'    => $date_start,
		'{date_end}'      => $date_end,
		'{questions}'     => $questions_text,
		'{site_url}'      => home_url( '/' ),
		'{site_name}'     => is_string( $site_name ) ? $site_name : '',
		'{site_tagline}'  => is_string( $site_tagline ) ? $site_tagline : '',
		'{plugin_author}' => OPENVOTE_PLUGIN_AUTHOR,
		'{github_url}'    => OPENVOTE_GITHUB_URL,
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

// Tytuł strony zgłoszeń (np. /zgloszenia/) — zawsze w tłumaczeniu (PL: Zgłoszenia, EN: Submissions).
add_filter( 'the_title', 'openvote_submissions_page_title', 10, 2 );
add_filter( 'pre_get_document_title', 'openvote_submissions_document_title', 10, 1 );

/**
 * Filtr the_title: na stronie zgłoszeń zwraca przetłumaczalny tytuł „Zgłoszenia”.
 *
 * @param string $title   Obecny tytuł.
 * @param int    $post_id ID wpisu.
 * @return string
 */
function openvote_submissions_page_title( string $title, int $post_id ): string {
	$slug = get_option( 'openvote_submissions_page_slug', 'zgloszenia' );
	if ( $slug === '' ) {
		return $title;
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page || (int) $page->ID !== (int) $post_id ) {
		return $title;
	}
	return __( 'Zgłoszenia', 'openvote' );
}

/**
 * Filtr pre_get_document_title: na stronie zgłoszeń tytuł w zakładce przeglądarki to „Zgłoszenia”.
 *
 * @param string $title Obecny tytuł dokumentu.
 * @return string
 */
function openvote_submissions_document_title( string $title ): string {
	$slug = get_option( 'openvote_submissions_page_slug', 'zgloszenia' );
	if ( $slug === '' || ! is_singular( 'page' ) ) {
		return $title;
	}
	$post = get_queried_object();
	if ( ! $post || ! isset( $post->post_name ) || $post->post_name !== $slug ) {
		return $title;
	}
	return __( 'Zgłoszenia', 'openvote' );
}

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

