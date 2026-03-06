<?php
/**
 * Plugin Name:       Open Vote
 * Plugin URI:        https://github.com/t7jk/openvote
 * Description:       Organisation polls and surveys: create votes with questions, manage groups, send invitations, view results (with optional anonymity).
 * Version:           1.0.20
 * Requires at least: 6.4
 * Tested up to:      6.7
 * Requires PHP:      8.1
 * Author:            Tomasz Kalinowski
 * Author URI:        https://x.com/tomas3man
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       openvote
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'OPENVOTE_VERSION', '1.0.20' );
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
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-email-rate-limits.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-batch-processor.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-cron-sync.php';
require_once OPENVOTE_PLUGIN_DIR . 'includes/class-openvote-email-resume-cron.php';
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
Openvote_Email_Resume_Cron::register();
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

/**
 * Zwraca nickname użytkownika (wg mapowania pól: np. user_nicename).
 * Używane w kolumnie Autor na listach głosowań i ankiet.
 *
 * @param int $user_id ID użytkownika WordPress.
 * @return string Nickname lub '—' gdy użytkownik nie istnieje; fallback na user_nicename/display_name gdy pole puste.
 */
function openvote_get_user_nickname( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return '—';
	}
	$user = get_userdata( $user_id );
	if ( ! $user instanceof WP_User ) {
		return '—';
	}
	$nick = Openvote_Field_Map::get_user_value( $user, 'nickname' );
	if ( $nick !== '' ) {
		return $nick;
	}
	if ( ! empty( $user->user_nicename ) ) {
		return $user->user_nicename;
	}
	if ( ! empty( $user->display_name ) ) {
		return $user->display_name;
	}
	return '—';
}

/** Maks. liczba wpisów w logu audytu koordynatorów (niekasowalna lista). */
const OPENVOTE_COORDINATOR_AUDIT_LOG_MAX = 200;

/** Wpisów w logu koordynatorów nie przechowujemy dłużej niż 365 dni. */
const OPENVOTE_COORDINATOR_AUDIT_LOG_MAX_DAYS = 365;

/**
 * Dopisuje wpis do logu audytu koordynatorów (kto kogo promował / komu odebrał).
 * Automatycznie usuwa wpisy starsze niż OPENVOTE_COORDINATOR_AUDIT_LOG_MAX_DAYS dni.
 *
 * @param int    $actor_id  ID użytkownika wykonującego akcję.
 * @param string $action    'promoted' lub 'removed'.
 * @param int    $target_id ID użytkownika (promowanego lub odbieranego).
 * @param string $groups    Nazwy grup (np. "Gdańsk, Wrocław") lub pusty przy pełnym usunięciu.
 */
function openvote_coordinator_audit_log_append( int $actor_id, string $action, int $target_id, string $groups = '' ): void {
	$log = get_option( 'openvote_coordinator_audit_log', [] );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_COORDINATOR_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$log       = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	$entry = [
		't'      => current_time( 'Y-m-d H:i:s' ),
		'actor'  => openvote_get_user_nickname( $actor_id ),
		'action' => $action === 'removed' ? 'removed' : 'promoted',
		'target' => openvote_get_user_nickname( $target_id ),
		'groups' => is_string( $groups ) ? $groups : '',
	];
	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, OPENVOTE_COORDINATOR_AUDIT_LOG_MAX );
	update_option( 'openvote_coordinator_audit_log', $log, false );
}

/**
 * Zwraca wpisy logu audytu koordynatorów (najnowsze pierwsze).
 * Przy odczycie usuwa wpisy starsze niż OPENVOTE_COORDINATOR_AUDIT_LOG_MAX_DAYS dni.
 *
 * @return array<int, array{t: string, actor: string, action: string, target: string, groups: string}>
 */
function openvote_coordinator_audit_log_get(): array {
	$log = get_option( 'openvote_coordinator_audit_log', [] );
	if ( ! is_array( $log ) ) {
		return [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_COORDINATOR_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$original  = $log;
	$log        = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	if ( count( $log ) !== count( $original ) ) {
		update_option( 'openvote_coordinator_audit_log', $log, false );
	}
	return $log;
}

/** Maks. liczba wpisów w logu audytu grup (Członkowie i grupy). */
const OPENVOTE_GROUPS_AUDIT_LOG_MAX = 200;

/** Wpisów w logu grup nie przechowujemy dłużej niż 365 dni. */
const OPENVOTE_GROUPS_AUDIT_LOG_MAX_DAYS = 365;

/**
 * Dopisuje wpis do logu audytu grup (utworzenie/usunięcie grupy, dodanie/usunięcie członka, synchronizacja).
 * Automatycznie usuwa wpisy starsze niż OPENVOTE_GROUPS_AUDIT_LOG_MAX_DAYS dni.
 *
 * @param int    $actor_id ID użytkownika wykonującego akcję.
 * @param string $line     Treść wpisu po nicku (np. "utworzył grupę Kraków", "dodał jan do Wrocław").
 */
function openvote_groups_audit_log_append( int $actor_id, string $line ): void {
	$log = get_option( 'openvote_groups_audit_log', [] );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_GROUPS_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$log       = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	$entry = [
		't'     => current_time( 'Y-m-d H:i:s' ),
		'actor' => openvote_get_user_nickname( $actor_id ),
		'line'  => is_string( $line ) ? $line : '',
	];
	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, OPENVOTE_GROUPS_AUDIT_LOG_MAX );
	update_option( 'openvote_groups_audit_log', $log, false );
}

/**
 * Zwraca wpisy logu audytu grup (najnowsze pierwsze).
 * Przy odczycie usuwa wpisy starsze niż OPENVOTE_GROUPS_AUDIT_LOG_MAX_DAYS dni.
 *
 * @return array<int, array{t: string, actor: string, line: string}>
 */
function openvote_groups_audit_log_get(): array {
	$log = get_option( 'openvote_groups_audit_log', [] );
	if ( ! is_array( $log ) ) {
		return [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_GROUPS_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$original  = $log;
	$log        = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	if ( count( $log ) !== count( $original ) ) {
		update_option( 'openvote_groups_audit_log', $log, false );
	}
	return $log;
}

/** Maks. wpisów w logu audytu głosowań. */
const OPENVOTE_POLLS_AUDIT_LOG_MAX = 200;

/** Wpisów w logu głosowań nie przechowujemy dłużej niż 365 dni. */
const OPENVOTE_POLLS_AUDIT_LOG_MAX_DAYS = 365;

/**
 * Dopisuje wpis do logu audytu głosowań (utworzenie, start, zakończenie, zaproszenia).
 * Automatycznie usuwa wpisy starsze niż OPENVOTE_POLLS_AUDIT_LOG_MAX_DAYS dni.
 *
 * @param int    $actor_id ID użytkownika wykonującego akcję.
 * @param string $line     Treść wpisu (np. "utworzył głosowanie Tytuł").
 */
function openvote_polls_audit_log_append( int $actor_id, string $line ): void {
	$log = get_option( 'openvote_polls_audit_log', [] );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_POLLS_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$log       = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	$entry = [
		't'     => current_time( 'Y-m-d H:i:s' ),
		'actor' => openvote_get_user_nickname( $actor_id ),
		'line'  => is_string( $line ) ? $line : '',
	];
	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, OPENVOTE_POLLS_AUDIT_LOG_MAX );
	update_option( 'openvote_polls_audit_log', $log, false );
}

/**
 * Zwraca wpisy logu audytu głosowań (najnowsze pierwsze).
 * Przy odczycie usuwa wpisy starsze niż OPENVOTE_POLLS_AUDIT_LOG_MAX_DAYS dni.
 *
 * @return array<int, array{t: string, actor: string, line: string}>
 */
function openvote_polls_audit_log_get(): array {
	$log = get_option( 'openvote_polls_audit_log', [] );
	if ( ! is_array( $log ) ) {
		return [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_POLLS_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$original  = $log;
	$log        = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	if ( count( $log ) !== count( $original ) ) {
		update_option( 'openvote_polls_audit_log', $log, false );
	}
	return $log;
}

/** Maks. wpisów w logu audytu ankiet. */
const OPENVOTE_SURVEYS_AUDIT_LOG_MAX = 200;

/** Wpisów w logu ankiet nie przechowujemy dłużej niż 365 dni. */
const OPENVOTE_SURVEYS_AUDIT_LOG_MAX_DAYS = 365;

/**
 * Dopisuje wpis do logu audytu ankiet (utworzenie, start, zakończenie).
 * Automatycznie usuwa wpisy starsze niż OPENVOTE_SURVEYS_AUDIT_LOG_MAX_DAYS dni.
 *
 * @param int    $actor_id ID użytkownika wykonującego akcję.
 * @param string $line     Treść wpisu (np. "utworzył ankietę Tytuł").
 */
function openvote_surveys_audit_log_append( int $actor_id, string $line ): void {
	$log = get_option( 'openvote_surveys_audit_log', [] );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_SURVEYS_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$log       = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	$entry = [
		't'     => current_time( 'Y-m-d H:i:s' ),
		'actor' => openvote_get_user_nickname( $actor_id ),
		'line'  => is_string( $line ) ? $line : '',
	];
	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, OPENVOTE_SURVEYS_AUDIT_LOG_MAX );
	update_option( 'openvote_surveys_audit_log', $log, false );
}

/**
 * Zwraca wpisy logu audytu ankiet (najnowsze pierwsze).
 * Przy odczycie usuwa wpisy starsze niż OPENVOTE_SURVEYS_AUDIT_LOG_MAX_DAYS dni.
 *
 * @return array<int, array{t: string, actor: string, line: string}>
 */
function openvote_surveys_audit_log_get(): array {
	$log = get_option( 'openvote_surveys_audit_log', [] );
	if ( ! is_array( $log ) ) {
		return [];
	}
	$cutoff_ts = current_time( 'timestamp' ) - ( OPENVOTE_SURVEYS_AUDIT_LOG_MAX_DAYS * DAY_IN_SECONDS );
	$cutoff    = wp_date( 'Y-m-d H:i:s', $cutoff_ts );
	$original  = $log;
	$log        = array_values( array_filter( $log, function ( $e ) use ( $cutoff ) {
		return isset( $e['t'] ) && $e['t'] >= $cutoff;
	} ) );
	if ( count( $log ) !== count( $original ) ) {
		update_option( 'openvote_surveys_audit_log', $log, false );
	}
	return $log;
}

// ─── Licznik wysłanych e-maili (per miesiąc) ────────────────────────────────

/**
 * Atomicznie zwiększa licznik wysłanych e-maili dla bieżącego miesiąca.
 * Klucz opcji: openvote_emails_sent_YYYY_MM (UTC).
 *
 * @param int $count Liczba wysłanych wiadomości do dodania.
 */
function openvote_increment_emails_sent( int $count = 1 ): void {
	if ( $count <= 0 ) {
		return;
	}
	global $wpdb;
	$key = 'openvote_emails_sent_' . gmdate( 'Y_m' );
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
			 VALUES (%s, %d, 'no')
			 ON DUPLICATE KEY UPDATE option_value = option_value + %d",
			$key,
			$count,
			$count
		)
	);
	wp_cache_delete( $key, 'options' );
}

/**
 * Zwraca historię wysłanych e-maili za ostatnie $months miesięcy (UTC).
 * Klucze tablicy wynikowej: 'YYYY-MM', wartości: int.
 *
 * @param int $months Liczba miesięcy wstecz (włącznie z bieżącym).
 * @return array<string, int>
 */
function openvote_get_emails_sent_history( int $months = 12 ): array {
	$result = [];
	for ( $i = 0; $i < $months; $i++ ) {
		$ts    = strtotime( "-{$i} months", time() );
		$key   = gmdate( 'Y_m', $ts );
		$label = gmdate( 'Y-m', $ts );
		$result[ $label ] = (int) get_option( 'openvote_emails_sent_' . $key, 0 );
	}
	return $result;
}

// ─── Statystyka i nieaktywni użytkownicy ───────────────────────────────────

/** Opcja: próg opuszczonych głosowań (1–24); gdy obie opcje = 24, oznaczanie nieaktywnych wyłączone. */
const OPENVOTE_STAT_MISSED_DEFAULT = 10;
/** Opcja: próg miesięcy od ostatniego głosu (1–24). */
const OPENVOTE_STAT_MONTHS_DEFAULT = 12;

/**
 * Zwraca wartość opcji „ilość opuszczonych głosowań” (prog nieaktywności).
 *
 * @return int 1–24
 */
function openvote_get_stat_missed_votes(): int {
	$v = (int) get_option( 'openvote_stat_missed_votes', OPENVOTE_STAT_MISSED_DEFAULT );
	return max( 1, min( 24, $v ) );
}

/**
 * Zwraca wartość opcji „ilość miesięcy” (prog nieaktywności).
 *
 * @return int 1–24
 */
function openvote_get_stat_months_inactive(): int {
	$v = (int) get_option( 'openvote_stat_months_inactive', OPENVOTE_STAT_MONTHS_DEFAULT );
	return max( 1, min( 24, $v ) );
}

/**
 * Data ostatniego głosu użytkownika (MySQL datetime lub null).
 *
 * @param int $user_id
 * @return string|null
 */
function openvote_get_last_voted_at( int $user_id ): ?string {
	if ( $user_id <= 0 ) {
		return null;
	}
	$v = get_user_meta( $user_id, 'openvote_last_voted_at', true );
	return is_string( $v ) && $v !== '' ? $v : null;
}

/**
 * Liczba zakończonych głosowań (date_end <= now), w których użytkownik był uprawniony i nie oddał głosu.
 *
 * @param int $user_id
 * @return int
 */
function openvote_get_missed_polls_count( int $user_id ): int {
	if ( $user_id <= 0 ) {
		return 0;
	}
	$v = get_user_meta( $user_id, 'openvote_missed_polls_count', true );
	return is_numeric( $v ) ? max( 0, (int) $v ) : 0;
}

/**
 * Czy użytkownik jest uznawany za nieaktywnego (OR: opuszczone >= próg LUB miesiące od ostatniego głosu >= próg).
 * Gdy obie opcje mają wartość 24, zwraca false (wyłączenie oznaczania).
 *
 * @param int $user_id
 * @return bool
 */
function openvote_is_user_inactive( int $user_id ): bool {
	$missed_prog = openvote_get_stat_missed_votes();
	$months_prog = openvote_get_stat_months_inactive();
	if ( $missed_prog >= 24 && $months_prog >= 24 ) {
		return false;
	}
	$missed = openvote_get_missed_polls_count( $user_id );
	if ( $missed >= $missed_prog ) {
		return true;
	}
	$last = openvote_get_last_voted_at( $user_id );
	if ( $last === null || $last === '' ) {
		return $months_prog >= 1;
	}
	$last_ts = strtotime( $last );
	$now_ts  = current_time( 'timestamp' );
	$months  = (int) floor( ( $now_ts - $last_ts ) / ( 30 * DAY_IN_SECONDS ) );
	return $months >= $months_prog;
}

/**
 * Aktualizuje statystyki użytkownika po oddaniu głosu: ustawia openvote_last_voted_at.
 * Opcjonalnie przelicza openvote_missed_polls_count (tylko głosowania z date_end < now()).
 *
 * @param int $user_id
 */
function openvote_update_user_vote_stats( int $user_id ): void {
	if ( $user_id <= 0 ) {
		return;
	}
	update_user_meta( $user_id, 'openvote_last_voted_at', current_time( 'mysql' ) );
	openvote_recalculate_missed_polls_count( $user_id );
}

/**
 * Przelicza openvote_missed_polls_count dla jednego użytkownika (tylko głosowania z date_end <= now()).
 *
 * @param int $user_id
 */
function openvote_recalculate_missed_polls_count( int $user_id ): void {
	global $wpdb;
	$polls_table = $wpdb->prefix . 'openvote_polls';
	$votes_table = $wpdb->prefix . 'openvote_votes';
	$now         = current_time( 'mysql' );
	$count       = 0;
	$poll_ids    = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT id FROM {$polls_table} WHERE status = 'closed' AND date_end <= %s ORDER BY id ASC",
			$now
		)
	);
	foreach ( (array) $poll_ids as $poll_id ) {
		$poll_id = (int) $poll_id;
		$poll    = Openvote_Poll::get( $poll_id );
		if ( ! $poll ) {
			continue;
		}
		if ( ! Openvote_Vote::user_was_eligible_for_poll( $user_id, $poll ) ) {
			continue;
		}
		if ( Openvote_Vote::has_voted( $poll_id, $user_id ) ) {
			continue;
		}
		++$count;
	}
	update_user_meta( $user_id, 'openvote_missed_polls_count', $count );
}

/**
 * Dla zakończonego głosowania (date_end <= now): zwiększa openvote_missed_polls_count o 1
 * dla każdego uprawnionego użytkownika, który nie oddał głosu. Partiami po 100.
 * Wywoływać tylko gdy date_end danego głosowania jest w przeszłości.
 *
 * @param int $poll_id
 */
function openvote_increment_missed_for_poll_non_voters( int $poll_id ): void {
	$poll = Openvote_Poll::get( $poll_id );
	if ( ! $poll ) {
		return;
	}
	$now = current_time( 'mysql' );
	if ( ! isset( $poll->date_end ) || $poll->date_end > $now ) {
		return;
	}
	$batch_size = 100;
	$offset     = 0;
	do {
		$user_ids = Openvote_Vote::get_eligible_non_voter_user_ids( $poll_id, $batch_size, $offset );
		foreach ( $user_ids as $uid ) {
			$cur = openvote_get_missed_polls_count( $uid );
			update_user_meta( $uid, 'openvote_missed_polls_count', $cur + 1 );
		}
		$offset += $batch_size;
	} while ( count( $user_ids ) === $batch_size );
}

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
 * Zwraca pełny URL strony głosowania.
 * Gdy istnieje opublikowana strona o slugu głosowania — zwraca get_permalink() (poprawny URL także przy permalinkach z index.php).
 * W przeciwnym razie: home_url + slug.
 */
function openvote_get_vote_page_url(): string {
	$slug = openvote_get_vote_page_slug();
	if ( $slug !== '' ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page && $page->post_status === 'publish' ) {
			$url = get_permalink( $page );
			return is_string( $url ) ? $url : user_trailingslashit( home_url( '/' . $slug ) );
		}
	}
	return user_trailingslashit( home_url( '/' . $slug ) );
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
 * Zakres dostępu koordynatora: 'all' = wszystkie grupy, 'own' = tylko przypisane.
 */
function openvote_coordinator_poll_access_scope(): string {
	$v = get_option( 'openvote_coordinator_poll_access', 'all' );
	return ( $v === 'own' ) ? 'own' : 'all';
}

/**
 * Czy bieżący użytkownik jest koordynatorem z ograniczeniem do własnych grup?
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

/**
 * Czy w Ustawieniach włączona jest grupa testowa „Test”?
 *
 * @return bool
 */
function openvote_create_test_group_enabled(): bool {
	return (int) get_option( 'openvote_create_test_group', 1 ) === 1;
}

/**
 * ID grupy Test (jeśli opcja włączona i grupa istnieje). 0 gdy brak.
 *
 * @return int
 */
function openvote_get_test_group_id(): int {
	if ( ! openvote_create_test_group_enabled() ) {
		return 0;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'openvote_groups';
	$id    = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$table} WHERE name = %s AND is_test_group = 1",
		Openvote_Activator::TEST_GROUP_NAME
	) );
	return $id ? (int) $id : 0;
}

/**
 * Czy grupa o podanym ID to grupa Test (is_test_group)?
 *
 * @param int $group_id
 * @return bool
 */
function openvote_is_test_group( int $group_id ): bool {
	if ( ! $group_id ) {
		return false;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'openvote_groups';
	$val   = $wpdb->get_var( $wpdb->prepare(
		"SELECT is_test_group FROM {$table} WHERE id = %d",
		$group_id
	) );
	return (int) $val === 1;
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
	$v = get_option( 'openvote_email_template_type', 'html' );
	return ( $v === 'html' ) ? 'html' : 'plain';
}

/** Wartość opcji openvote_email_body_html oznaczająca „treść w pliku” (omija filtry typu wp_kses_post przy zapisie). */
define( 'OPENVOTE_EMAIL_HTML_OPTION_FILE_MARKER', '__OPENVOTE_FILE__' );

/**
 * Ścieżka do pliku z szablonem HTML e-maila (w katalogu uploadów).
 * Zapis w pliku omija filtry pre_update_option, które mogłyby usuwać <style>/<head>.
 *
 * @return string Ścieżka bezwzględna do pliku.
 */
function openvote_get_email_body_html_storage_path(): string {
	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) ) {
		return '';
	}
	return $upload_dir['basedir'] . '/openvote/email-body-html.html';
}

/**
 * Zapisuje szablon HTML e-maila do pliku. Tworzy katalog openvote w uploads, jeśli trzeba.
 *
 * @param string $html Pełna treść HTML (z <!DOCTYPE>, <style> itd.).
 * @return bool True, jeśli zapis się udał.
 */
function openvote_write_email_body_html_to_file( string $html ): bool {
	$path = openvote_get_email_body_html_storage_path();
	if ( $path === '' ) {
		return false;
	}
	$dir = dirname( $path );
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return false;
	}
	return file_put_contents( $path, $html, LOCK_EX ) !== false;
}

/**
 * Odczytuje szablon HTML e-maila z pliku.
 *
 * @return string Treść pliku lub pusty string, jeśli plik nie istnieje / nie da się odczytać.
 */
function openvote_read_email_body_html_from_file(): string {
	$path = openvote_get_email_body_html_storage_path();
	if ( $path === '' || ! is_readable( $path ) ) {
		return '';
	}
	$content = file_get_contents( $path );
	return is_string( $content ) ? $content : '';
}

/**
 * Domyślna treść e-maila (wersja czysty tekst).
 * Zawiera stopkę z {plugin_author}, {github_url}.
 */
function openvote_get_email_body_plain_default(): string {
	$footer = "\n\n\n──────────────────────────────────────────────────\n" .
		"Otwarte Głosowanie (Open Vote)\n" .
		'Autor systemu: ' . OPENVOTE_PLUGIN_AUTHOR . "\n" .
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
      System: <em>Otwarte Głosowanie (Open Vote)</em> &mdash; autor: ' . OPENVOTE_PLUGIN_AUTHOR . '
    </span>
  </div>
</div>
</body>
</html>';
}

/**
 * Treść e-maila zaproszenia (wersja czysty tekst). Zapis lub domyślna.
 * Wykrywa szablon zanieczyszczony wklejonym HTML (np. body { font...), resetuje do domyślnego.
 */
function openvote_get_email_body_plain_template(): string {
	$saved = get_option( 'openvote_email_body_plain', '' );
	if ( is_string( $saved ) && trim( $saved ) !== '' ) {
		$saved = trim( $saved );
		// Wykryj szablon będący wklejonym HTML po wp_strip_all_tags (zostaje surowy CSS w treści).
		if ( str_contains( $saved, 'body { font' ) && str_contains( $saved, '.wrapper {' ) ) {
			delete_option( 'openvote_email_body_plain' );
			return openvote_get_email_body_plain_default();
		}
		return $saved;
	}
	// Kompatybilność: stara opcja openvote_email_body jako plain.
	$legacy = get_option( 'openvote_email_body', '' );
	if ( is_string( $legacy ) && trim( $legacy ) !== '' ) {
		$legacy = trim( $legacy );
		if ( str_contains( $legacy, 'body { font' ) && str_contains( $legacy, '.wrapper {' ) ) {
			delete_option( 'openvote_email_body' );
			return openvote_get_email_body_plain_default();
		}
		return $legacy;
	}
	return openvote_get_email_body_plain_default();
}

/**
 * Treść e-maila zaproszenia (wersja HTML). Zapis w pliku (gdy opcja = marker) lub w opcji, inaczej domyślna.
 * Zapis w pliku omija filtry pre_update_option (np. wp_kses_post), które usuwałyby <style>/<head>.
 */
function openvote_get_email_body_html_template(): string {
	$option_value = get_option( 'openvote_email_body_html', '' );

	// Treść w pliku — filtr przy zapisie nie niszczy <style>.
	if ( $option_value === OPENVOTE_EMAIL_HTML_OPTION_FILE_MARKER ) {
		$saved = openvote_read_email_body_html_from_file();
		if ( $saved !== '' ) {
			$saved = trim( $saved );
			if ( str_starts_with( $saved, '<' ) && ( ! preg_match( '/\bbody\s*\{\s*font/i', $saved ) || preg_match( '/<style[\s>]/i', $saved ) ) ) {
				$old_header = '<p>{brand_short}</p>';
				$new_header = '<p>{brand_short} — {site_name}</p>' . "\n    " . '<p class="header__tagline">{site_tagline}</p>';
				if ( str_contains( $saved, $old_header ) && ! str_contains( $saved, '{site_name}' ) ) {
					$saved = str_replace( $old_header, $new_header, $saved );
				}
				return $saved;
			}
		}
		// Plik pusty lub uszkodzony — zwróć domyślny, bez zmiany opcji (następny zapis z formularza nadpisze plik).
		return openvote_get_email_body_html_default();
	}

	// Treść w opcji (stara metoda).
	$saved = is_string( $option_value ) ? trim( $option_value ) : '';
	if ( $saved !== '' ) {
		// Wykryj szablon uszkodzony przez wp_kses_post() (stripuje <style>/<head>/<html>).
		if ( ! str_starts_with( $saved, '<' ) ) {
			delete_option( 'openvote_email_body_html' );
			return openvote_get_email_body_html_default();
		}
		if ( preg_match( '/\bbody\s*\{\s*font/i', $saved ) && ! preg_match( '/<style[\s>]/i', $saved ) ) {
			delete_option( 'openvote_email_body_html' );
			return openvote_get_email_body_html_default();
		}
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
 * Metoda wysyłki e-maili: 'wordpress', 'smtp', 'sendgrid', 'brevo', 'brevo_paid', 'freshmail', 'getresponse'.
 */
function openvote_get_mail_method(): string {
	$allowed = [ 'wordpress', 'smtp', 'sendgrid', 'brevo', 'brevo_paid', 'freshmail', 'getresponse' ];
	$v       = get_option( 'openvote_mail_method', 'wordpress' );
	return in_array( $v, $allowed, true ) ? $v : 'wordpress';
}

/**
 * Klucz API Brevo (wspólny dla BREVO free i paid).
 *
 * @return string Pusty string gdy nie skonfigurowano.
 */
function openvote_get_brevo_api_key(): string {
	return (string) get_option( 'openvote_brevo_api_key', '' );
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
 * Klucz API Freshmail (Subskrypcja → Zaawansowane → API).
 *
 * @return string
 */
function openvote_get_freshmail_api_key(): string {
	return (string) get_option( 'openvote_freshmail_api_key', '' );
}

/**
 * Sekret API Freshmail (do podpisu żądań).
 *
 * @return string
 */
function openvote_get_freshmail_api_secret(): string {
	return (string) get_option( 'openvote_freshmail_api_secret', '' );
}

/**
 * Klucz API GetResponse (Integracje → API).
 *
 * @return string
 */
function openvote_get_getresponse_api_key(): string {
	return (string) get_option( 'openvote_getresponse_api_key', '' );
}

/**
 * ID pola nadawcy GetResponse (From Field ID z panelu lub API from-fields).
 *
 * @return string
 */
function openvote_get_getresponse_from_field_id(): string {
	return (string) get_option( 'openvote_getresponse_from_field_id', '' );
}

/** Limity wysyłki masowej per metoda (max na partię, min pauza w sekundach). */
const OPENVOTE_EMAIL_BATCH_WP_MAX       = 80;   // PHP-mail: 80 na partię, 1 partia / 15 min, max 4 partie/h
const OPENVOTE_EMAIL_BATCH_WP_SMTP_MAX  = 100;
const OPENVOTE_EMAIL_BATCH_WP_DELAY_MIN = 900; // 15 minut między partiami, max 4 partie/h
const OPENVOTE_EMAIL_BATCH_WP_SMTP_DELAY_MIN = 900;
const OPENVOTE_EMAIL_BATCH_BREVO_FREE_MAX = 100;
const OPENVOTE_EMAIL_BATCH_BREVO_FREE_DELAY_MIN = 1200;
const OPENVOTE_EMAIL_BATCH_SENDGRID_DEFAULT = 100;
const OPENVOTE_EMAIL_BATCH_SENDGRID_DELAY_DEFAULT = 2;
const OPENVOTE_EMAIL_BATCH_BREVO_PAID_DEFAULT = 100;
const OPENVOTE_EMAIL_BATCH_BREVO_PAID_DELAY_DEFAULT = 2;
const OPENVOTE_EMAIL_BATCH_FRESHMAIL_DEFAULT   = 100;
const OPENVOTE_EMAIL_BATCH_FRESHMAIL_DELAY     = 2;
const OPENVOTE_EMAIL_BATCH_GETRESPONSE_DEFAULT = 100;
const OPENVOTE_EMAIL_BATCH_GETRESPONSE_DELAY   = 2;

/**
 * Liczba e-maili wysyłanych w jednej partii.
 * Domyślne i cap per metoda: WP 80, SMTP 100, brevo free 100, sendgrid/brevo_paid 100 (bez cap).
 *
 * @return int
 */
function openvote_get_email_batch_size(): int {
	$method = openvote_get_mail_method();

	if ( $method === 'wordpress' ) {
		$default = OPENVOTE_EMAIL_BATCH_WP_MAX;
		$cap     = OPENVOTE_EMAIL_BATCH_WP_MAX;
		$saved   = (int) get_option( 'openvote_batch_wp_size', 0 );
		$val     = $saved > 0 ? $saved : $default;
		return min( $cap, $val );
	}
	if ( $method === 'smtp' ) {
		$default = OPENVOTE_EMAIL_BATCH_WP_SMTP_MAX;
		$cap     = OPENVOTE_EMAIL_BATCH_WP_SMTP_MAX;
		$saved   = (int) get_option( 'openvote_batch_smtp_size', 0 );
		$val     = $saved > 0 ? $saved : $default;
		return min( $cap, $val );
	}
	if ( $method === 'brevo' ) {
		$default = OPENVOTE_EMAIL_BATCH_BREVO_FREE_MAX;
		$cap     = OPENVOTE_EMAIL_BATCH_BREVO_FREE_MAX;
		$saved   = (int) get_option( 'openvote_batch_brevo_free_size', 0 );
		$val     = $saved > 0 ? $saved : $default;
		return min( $cap, $val );
	}
	$saved = (int) get_option( 'openvote_email_batch_size', 0 );
	if ( $method === 'brevo_paid' ) {
		$default = OPENVOTE_EMAIL_BATCH_BREVO_PAID_DEFAULT;
		return $saved > 0 ? $saved : $default;
	}
	if ( $method === 'freshmail' || $method === 'getresponse' ) {
		$default = $method === 'freshmail' ? OPENVOTE_EMAIL_BATCH_FRESHMAIL_DEFAULT : OPENVOTE_EMAIL_BATCH_GETRESPONSE_DEFAULT;
		$val     = $saved > 0 ? $saved : $default;
		return min( 1000, max( 1, $val ) );
	}
	// sendgrid
	return $saved > 0 ? $saved : OPENVOTE_EMAIL_BATCH_SENDGRID_DEFAULT;
}

/**
 * Opóźnienie między partiami e-maili w sekundach.
 * Domyślne i min per metoda: WP/SMTP 900 s, brevo free 1200 s, sendgrid/brevo_paid 2 s.
 *
 * @return int
 */
function openvote_get_email_batch_delay(): int {
	$method = openvote_get_mail_method();

	if ( $method === 'wordpress' ) {
		$default = OPENVOTE_EMAIL_BATCH_WP_DELAY_MIN;
		$min     = OPENVOTE_EMAIL_BATCH_WP_DELAY_MIN;
		$saved   = (int) get_option( 'openvote_batch_wp_delay', 0 );
		$val     = $saved > 0 ? $saved : $default;
		return max( $min, $val );
	}
	if ( $method === 'smtp' ) {
		$default = OPENVOTE_EMAIL_BATCH_WP_SMTP_DELAY_MIN;
		$min     = OPENVOTE_EMAIL_BATCH_WP_SMTP_DELAY_MIN;
		$saved   = (int) get_option( 'openvote_batch_smtp_delay', 0 );
		$val     = $saved > 0 ? $saved : $default;
		return max( $min, $val );
	}
	if ( $method === 'brevo' ) {
		$default = OPENVOTE_EMAIL_BATCH_BREVO_FREE_DELAY_MIN;
		$min     = OPENVOTE_EMAIL_BATCH_BREVO_FREE_DELAY_MIN;
		$saved   = (int) get_option( 'openvote_batch_brevo_free_delay', 0 );
		$val     = $saved > 0 ? $saved : $default;
		return max( $min, $val );
	}
	$saved = (int) get_option( 'openvote_email_batch_delay', 0 );
	if ( $method === 'brevo_paid' ) {
		return $saved > 0 ? $saved : OPENVOTE_EMAIL_BATCH_BREVO_PAID_DELAY_DEFAULT;
	}
	if ( $method === 'freshmail' ) {
		return $saved > 0 ? max( 1, min( 86400, $saved ) ) : OPENVOTE_EMAIL_BATCH_FRESHMAIL_DELAY;
	}
	if ( $method === 'getresponse' ) {
		return $saved > 0 ? max( 1, min( 86400, $saved ) ) : OPENVOTE_EMAIL_BATCH_GETRESPONSE_DELAY;
	}
	// sendgrid
	return $saved > 0 ? $saved : OPENVOTE_EMAIL_BATCH_SENDGRID_DELAY_DEFAULT;
}

/**
 * Limit e-maili na 15 minut dla bieżącej metody wysyłki.
 * 0 = nie egzekwować. Tylko brevo (free), wordpress, smtp mają konfigurowalne limity.
 *
 * @return int
 */
function openvote_get_email_limit_per_15min(): int {
	$method = openvote_get_mail_method();
	if ( $method === 'brevo' ) {
		$saved = (int) get_option( 'openvote_batch_brevo_free_per_15min', 0 );
		return $saved > 0 ? min( 1000, $saved ) : 100;
	}
	if ( $method === 'wordpress' ) {
		$saved = (int) get_option( 'openvote_batch_wp_per_15min', 0 );
		return $saved > 0 ? min( 1000, $saved ) : 80;
	}
	if ( $method === 'smtp' ) {
		$saved = (int) get_option( 'openvote_batch_smtp_per_15min', 0 );
		return $saved > 0 ? min( 1000, $saved ) : 100;
	}
	return 0;
}

/**
 * Limit e-maili na godzinę dla bieżącej metody wysyłki.
 * 0 = nie egzekwować.
 *
 * @return int
 */
function openvote_get_email_limit_per_hour(): int {
	$method = openvote_get_mail_method();
	if ( $method === 'brevo' ) {
		$saved = (int) get_option( 'openvote_batch_brevo_free_per_hour', 0 );
		return $saved > 0 ? min( 10000, $saved ) : 100;
	}
	if ( $method === 'wordpress' ) {
		$saved = (int) get_option( 'openvote_batch_wp_per_hour', 0 );
		return $saved > 0 ? min( 10000, $saved ) : 320;
	}
	if ( $method === 'smtp' ) {
		$saved = (int) get_option( 'openvote_batch_smtp_per_hour', 0 );
		return $saved > 0 ? min( 10000, $saved ) : 400;
	}
	return 0;
}

/**
 * Limit e-maili na dobę dla bieżącej metody wysyłki.
 * 0 = nie egzekwować.
 *
 * @return int
 */
function openvote_get_email_limit_per_day(): int {
	$method = openvote_get_mail_method();
	if ( $method === 'brevo' ) {
		$saved = (int) get_option( 'openvote_batch_brevo_free_per_day', 0 );
		return $saved > 0 ? min( 10000, $saved ) : 300;
	}
	if ( $method === 'wordpress' ) {
		$saved = (int) get_option( 'openvote_batch_wp_per_day', 0 );
		return $saved > 0 ? min( 100000, $saved ) : 7680;
	}
	if ( $method === 'smtp' ) {
		$saved = (int) get_option( 'openvote_batch_smtp_per_day', 0 );
		return $saved > 0 ? min( 100000, $saved ) : 500;
	}
	return 0;
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

