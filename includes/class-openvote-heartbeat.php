<?php
defined( 'ABSPATH' ) || exit;

/**
 * Heartbeat — serwerowy cron (cPanel / crontab) wywołujący wtyczkę co ~15 minut, także gdy nikt nie jest zalogowany.
 *
 * Zadania jednego uderzenia (tick):
 *  (-) migracja schematu DB, jeśli wtyczkę zaktualizowano bez wejścia do panelu (np. przez FTP) — Openvote_Activator::maybe_upgrade(),
 *  (0) domknięcie głosowań i ankiet ze statusem „open”, których termin zakończenia minął (open → closed),
 *      oraz samonaprawa harmonogramu auto-synchronizacji grup (Openvote_Cron_Sync::ensure_scheduled),
 *  (0') grupa systemowa system.Wszyscy: utworzenie, sprzątanie wycofanych grup ról, uzgodnienie członkostwa partiami
 *      (Openvote_System_Groups, własny podbudżet ~10 s) — przed zaproszeniami, by trafiały do aktualnych członków,
 *  (a) automatyczne zaproszenia dla trwających głosowań z notify_start=1 (tylko głosowania wystartowane PO zainstalowaniu funkcji
 *      i bez żadnego wpisu w kolejce zaproszeń — patrz OPT_SINCE),
 *  (b) wznowienie zadań wysyłki wstrzymanych limitami oraz porzuconych (status „running”, bez partii od STALE_AFTER s —
 *      np. zamknięta karta panelu) — zaproszenia i wiadomości z „Wysyłki”,
 *  (c) uruchomienie zaległych zdarzeń WP-Cron wtyczki (hooki openvote_*),
 *  (d) zapis czasu i krótkiego podsumowania ostatniego uruchomienia.
 *
 * Wysyłka korzysta z istniejącego mechanizmu: Openvote_Batch_Processor (start_job / process_batch) wraz z limitami
 * Openvote_Email_Rate_Limits — heartbeat nie wysyła niczego sam.
 */
class Openvote_Heartbeat {

	const OPT_SINCE    = 'openvote_auto_invite_since';   // Y-m-d H:i:s w strefie głosowań.
	const OPT_INVITED  = 'openvote_auto_invited_polls';  // [ poll_id => timestamp ] — raz wystartowane automatycznie.
	const OPT_TRACKED  = 'openvote_heartbeat_tracked';   // [ 'p12' => job_id, 'm3' => job_id ].
	const OPT_LAST     = 'openvote_heartbeat_last';      // ostatnie wywołanie z zewnętrznego crona.
	const OPT_LAST_MAN = 'openvote_heartbeat_last_manual'; // ostatnie „Uruchom teraz” z panelu.
	const LOCK_NAME    = 'openvote_heartbeat_lock';
	const LOCK_TTL     = 180;   // sekund — po tym czasie blokada uznawana za porzuconą.
	const BUDGET       = 40;    // sekund pracy w jednym ticku.
	const STALE_AFTER  = 300;   // sekund bez partii, po których zadanie „running” z panelu uznajemy za porzucone.
	const NOTICE_AFTER = 20 * 60; // sekund od ostatniego ticku, po których panel pokazuje ostrzeżenie.

	public static function register(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_action( 'admin_init', [ __CLASS__, 'ensure_since' ] );
		add_action( 'admin_notices', [ __CLASS__, 'render_stale_notice' ], 20 );
	}

	/**
	 * Ostrzeżenie na ekranach OpenVote (tylko manage_options): zewnętrzny cron nie uruchomił się od NOTICE_AFTER s lub nigdy.
	 */
	public static function render_stale_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( (string) $screen->id, 'openvote' ) ) {
			return;
		}
		$last = self::status()['last'];
		if ( ! self::is_tick_stale( $last, time() ) ) {
			return;
		}
		$msg = $last > 0
			? sprintf(
				/* translators: %s: date and time of the last cron run */
				__( 'OpenVote: zewnętrzny cron (heartbeat) nie uruchomił się od ponad 20 minut (ostatnio: %s). Automatyczne zamykanie głosowań i ankiet, zaproszenia oraz dokańczanie wysyłek nie działają.', 'openvote' ),
				wp_date( 'Y-m-d H:i:s', $last )
			)
			: __( 'OpenVote: zewnętrzny cron (heartbeat) nie został jeszcze ani razu uruchomiony. Automatyczne zamykanie głosowań i ankiet, zaproszenia oraz dokańczanie wysyłek nie działają.', 'openvote' );
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( $msg ),
			esc_url( admin_url( 'admin.php?page=openvote-settings#tab-cron' ) ),
			esc_html__( 'Skonfiguruj zadanie cron (Konfiguracja → Cron)', 'openvote' )
		);
	}

	/** Czy ostatni tick crona (timestamp, 0 = nigdy) jest za stary. */
	public static function is_tick_stale( int $last_ts, int $now ): bool {
		return $last_ts <= 0 || ( $now - $last_ts ) >= self::NOTICE_AFTER;
	}

	// ─── Konfiguracja / klucz ───────────────────────────────────────────────

	/** Zapisz moment instalacji funkcji (raz). Głosowania wystartowane wcześniej nigdy nie dostaną auto-zaproszeń. */
	public static function ensure_since(): string {
		$since = get_option( self::OPT_SINCE, '' );
		if ( ! is_string( $since ) || $since === '' ) {
			$since = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
			add_option( self::OPT_SINCE, $since, '', false );
			$since = (string) get_option( self::OPT_SINCE, $since );
		}
		return $since;
	}

	/** Polecenie do wklejenia w cPanel → Zadania Cron (co 15 minut). */
	public static function get_cron_command(): string {
		$script = wp_normalize_path( OPENVOTE_PLUGIN_DIR . 'cron/openvote-cron.php' );
		return '/usr/local/bin/php84 -q ' . $script . ' >/dev/null 2>&1';
	}

	// ─── REST (tylko administrator) ─────────────────────────────────────────

	public static function register_routes(): void {
		register_rest_route( 'openvote/v1', '/cron/run-now', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'rest_run_now' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		] );
	}

	public static function rest_run_now(): WP_REST_Response {
		$res = self::run( 'manual' );
		return new WP_REST_Response( [
			'ok'      => true,
			'ran'     => $res['ran'],
			'summary' => $res['summary'],
			'status'  => self::status(),
		], 200 );
	}

	/**
	 * Stan dla ekranu Konfiguracji.
	 *
	 * @return array{ last: int, summary: string, ok: bool, manual_last: int, manual_summary: string }
	 */
	public static function status(): array {
		$last = get_option( self::OPT_LAST, [] );
		$man  = get_option( self::OPT_LAST_MAN, [] );
		$ts   = is_array( $last ) ? (int) ( $last['ts'] ?? 0 ) : 0;
		return [
			'last'           => $ts,
			'summary'        => is_array( $last ) ? (string) ( $last['summary'] ?? '' ) : '',
			'ok'             => ! self::is_tick_stale( $ts, time() ),
			'manual_last'    => is_array( $man ) ? (int) ( $man['ts'] ?? 0 ) : 0,
			'manual_summary' => is_array( $man ) ? (string) ( $man['summary'] ?? '' ) : '',
		];
	}

	// ─── Blokada ────────────────────────────────────────────────────────────

	private static function acquire_lock(): bool {
		global $wpdb;
		$now = time();
		// Atomowo: INSERT IGNORE — tylko jedno wywołanie utworzy wiersz.
		$ins = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			self::LOCK_NAME,
			(string) $now
		) );
		if ( $ins ) {
			return true;
		}
		// Blokada istnieje — przejmij ją tylko jeśli stara (atomowy UPDATE z warunkiem na starą wartość).
		$old = (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			self::LOCK_NAME
		) );
		if ( $old !== '' && ( $now - (int) $old ) > self::LOCK_TTL ) {
			$upd = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				(string) $now,
				self::LOCK_NAME,
				$old
			) );
			return (bool) $upd;
		}
		return false;
	}

	private static function release_lock(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_NAME ) );
	}

	// ─── Główna pętla ───────────────────────────────────────────────────────

	/**
	 * @param string $source 'cron' (zewnętrzny crontab) lub 'manual' (przycisk w panelu).
	 * @return array{ ran: bool, summary: string }
	 */
	public static function run( string $source = 'cron' ): array {
		@ignore_user_abort( true );
		@set_time_limit( self::BUDGET + 30 );
		$started = microtime( true );
		delete_option( 'openvote_cron_key' ); // pozostałość po wcześniejszej wersji (klucz URL).
		$option  = ( $source === 'manual' ) ? self::OPT_LAST_MAN : self::OPT_LAST;

		if ( ! self::acquire_lock() ) {
			$summary = __( 'Pominięto: poprzednie uruchomienie jeszcze trwa.', 'openvote' );
			// Blokada = poprzedni tick działa; to też „sygnał życia” crona.
			update_option( $option, [ 'ts' => time(), 'summary' => $summary ], false );
			return [ 'ran' => false, 'summary' => $summary ];
		}

		$parts = [];
		try {
			$deadline = time() + self::BUDGET;
			$parts[] = self::task_schema_upgrade();
			self::ensure_since();
			$parts[] = self::task_close_expired( $deadline );
			$parts[] = self::task_sync_schedule_health();
			$parts[] = self::task_system_groups( $deadline );
			$parts[] = self::task_auto_invitations();
			$parts[] = self::task_resume_paused();
			$parts[] = self::task_process_jobs( $deadline );
			$parts[] = self::task_wp_cron( $deadline );
		} catch ( \Throwable $e ) {
			$parts[] = 'ERR: ' . $e->getMessage();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Openvote heartbeat: ' . $e->getMessage() );
			}
		}
		self::release_lock();

		$parts   = array_values( array_filter( $parts ) );
		$summary = $parts ? implode( '; ', $parts ) : __( 'Brak zadań do wykonania.', 'openvote' );
		$summary .= sprintf( ' (%.1f s)', microtime( true ) - $started );
		update_option( $option, [ 'ts' => time(), 'summary' => $summary ], false );
		return [ 'ran' => true, 'summary' => $summary ];
	}

	// ─── (0) Domknięcie wygasłych głosowań i ankiet ─────────────────────────

	/**
	 * open → closed dla głosowań i ankiet z minionym terminem zakończenia (date_end bez zmian).
	 * Zamknięcie atomowe (WHERE status = 'open'): przy wyścigu z ręcznym „Zakończ” tylko jeden proces
	 * dostaje affected rows = 1 i tylko on nalicza „pominięte głosowania” — naliczanie jest więc jednokrotne.
	 */
	private static function task_close_expired( int $deadline ): string {
		global $wpdb;
		$out = [];

		// Głosowania.
		$pt     = $wpdb->prefix . 'openvote_polls';
		$polls  = $wpdb->get_results( "SELECT id, title, status, date_end FROM {$pt} WHERE status = 'open' ORDER BY date_end ASC, id ASC" );
		$closed = [];
		$left   = 0;
		foreach ( (array) $polls as $poll ) {
			if ( ! Openvote_Poll::is_time_expired( $poll ) ) {
				continue;
			}
			if ( time() >= $deadline ) {
				$left++;
				continue;
			}
			$id  = (int) $poll->id;
			$aff = $wpdb->query( $wpdb->prepare( "UPDATE {$pt} SET status = 'closed' WHERE id = %d AND status = 'open'", $id ) );
			if ( 1 !== (int) $aff ) {
				continue; // zamknięte w międzyczasie (ręcznie lub równoległy tick) — nic nie naliczamy.
			}
			$closed[] = $id;
			if ( function_exists( 'openvote_increment_missed_for_poll_non_voters' ) ) {
				openvote_increment_missed_for_poll_non_voters( $id );
			}
			if ( function_exists( 'openvote_polls_audit_log_append' ) ) {
				/* translators: %s: poll title */
				openvote_polls_audit_log_append( 0, sprintf( __( 'automatycznie zakończono głosowanie %s (minął termin)', 'openvote' ), (string) $poll->title ) );
			}
		}
		if ( $closed ) {
			/* translators: %s: comma-separated poll IDs */
			$out[] = sprintf( __( 'zamknięto wygasłe głosowania: %s', 'openvote' ), implode( ',', $closed ) );
		}

		// Ankiety.
		$st      = $wpdb->prefix . 'openvote_surveys';
		$surveys = $wpdb->get_results( "SELECT id, title, status, date_end FROM {$st} WHERE status = 'open' ORDER BY date_end ASC, id ASC" );
		$closed  = [];
		foreach ( (array) $surveys as $survey ) {
			if ( ! self::survey_time_expired( $survey ) ) {
				continue;
			}
			if ( time() >= $deadline ) {
				$left++;
				continue;
			}
			$id  = (int) $survey->id;
			$aff = $wpdb->query( $wpdb->prepare( "UPDATE {$st} SET status = 'closed' WHERE id = %d AND status = 'open'", $id ) );
			if ( 1 !== (int) $aff ) {
				continue;
			}
			$closed[] = $id;
			if ( function_exists( 'openvote_surveys_audit_log_append' ) ) {
				/* translators: %s: survey title */
				openvote_surveys_audit_log_append( 0, sprintf( __( 'automatycznie zakończono ankietę %s (minął termin)', 'openvote' ), (string) $survey->title ) );
			}
		}
		if ( $closed ) {
			/* translators: %s: comma-separated survey IDs */
			$out[] = sprintf( __( 'zamknięto wygasłe ankiety: %s', 'openvote' ), implode( ',', $closed ) );
		}
		if ( $left > 0 ) {
			/* translators: %d: number of items left for the next run */
			$out[] = sprintf( __( 'do zamknięcia w kolejnym ticku: %d', 'openvote' ), $left );
		}
		return implode( '; ', $out );
	}

	/** Openvote_Survey::is_time_expired() (gdy dostępne) lub to samo obliczenie lokalnie. */
	public static function survey_time_expired( object $survey ): bool {
		if ( class_exists( 'Openvote_Survey' ) && method_exists( 'Openvote_Survey', 'is_time_expired' ) ) {
			return Openvote_Survey::is_time_expired( $survey );
		}
		if ( 'open' !== ( $survey->status ?? '' ) || empty( $survey->date_end ) ) {
			return false;
		}
		$end = (string) $survey->date_end;
		if ( strlen( $end ) === 10 ) {
			$end .= ' 23:59:59';
		}
		return openvote_current_time_for_voting( 'Y-m-d H:i:s' ) > $end;
	}

	/** Samonaprawa harmonogramu auto-synchronizacji grup (WP-Cron). */
	private static function task_sync_schedule_health(): string {
		if ( ! class_exists( 'Openvote_Cron_Sync' ) || ! method_exists( 'Openvote_Cron_Sync', 'ensure_scheduled' ) ) {
			return '';
		}
		$fixed = Openvote_Cron_Sync::ensure_scheduled();
		/* translators: %s: comma-separated hook names */
		return $fixed ? sprintf( __( 'przywrócono harmonogram: %s', 'openvote' ), implode( ',', $fixed ) ) : '';
	}

	/** Migracja schematu, gdy brakuje kolumny system_key lub wersja DB jest starsza (aktualizacja przez FTP, cron CLI). */
	private static function task_schema_upgrade(): string {
		if ( ! class_exists( 'Openvote_System_Groups' ) || ! class_exists( 'Openvote_Activator' ) ) {
			return '';
		}
		if ( Openvote_System_Groups::schema_ready() && Openvote_System_Groups::column_exists() ) {
			// Jednorazowe zmiany danych poza numerem wersji (np. dev, gdzie 4.9.0 już przeszło).
			return Openvote_Activator::ensure_city_required_default() ? __( 'pole Grupa ustawione jako wymagane do głosowania', 'openvote' ) : '';
		}
		$before = (string) get_option( 'openvote_db_version', '' );
		Openvote_Activator::maybe_upgrade();
		Openvote_System_Groups::flush_cache();
		$after = (string) get_option( 'openvote_db_version', '' );
		/* translators: 1: old DB version, 2: new DB version */
		return $before !== $after ? sprintf( __( 'migracja bazy %1$s → %2$s', 'openvote' ), $before, $after ) : '';
	}

	/** Grupa system.Wszyscy: utwórz, jeśli brak; jednorazowo usuń wycofane grupy ról; uzgodnij członkostwo (podbudżet). */
	private static function task_system_groups( int $deadline ): string {
		if ( ! class_exists( 'Openvote_System_Groups' ) || ! Openvote_System_Groups::schema_ready() ) {
			return '';
		}
		$out     = [];
		$created = Openvote_System_Groups::ensure_groups();
		if ( $created > 0 ) {
			/* translators: %d: number of created groups */
			$out[] = sprintf( __( 'utworzono grupy systemowe: %d', 'openvote' ), $created );
		}
		$cleanup = Openvote_System_Groups::cleanup_legacy_role_groups();
		if ( ! empty( $cleanup['deleted'] ) || ! empty( $cleanup['skipped'] ) ) {
			/* translators: 1: deleted groups count, 2: skipped groups count */
			$out[] = sprintf( __( 'wycofane grupy ról: usunięto %1$d, pozostawiono %2$d', 'openvote' ), count( $cleanup['deleted'] ), count( $cleanup['skipped'] ) );
		}
		$res = Openvote_System_Groups::reconcile( min( $deadline, time() + Openvote_System_Groups::BUDGET ) );
		if ( '' !== $res ) {
			$out[] = $res;
		}
		return implode( '; ', $out );
	}

	// ─── (a) Automatyczne zaproszenia ───────────────────────────────────────

	private static function task_auto_invitations(): string {
		global $wpdb;
		$since = self::ensure_since();
		$now   = openvote_current_time_for_voting( 'Y-m-d H:i:s' );
		$pt    = $wpdb->prefix . 'openvote_polls';
		$eq    = $wpdb->prefix . 'openvote_email_queue';

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$pt}
			 WHERE status = 'open' AND notify_start = 1
			   AND date_start >= %s AND date_start <= %s AND date_end > %s",
			$since,
			$now,
			$now
		) );
		if ( empty( $ids ) ) {
			return '';
		}
		$invited = get_option( self::OPT_INVITED, [] );
		if ( ! is_array( $invited ) ) {
			$invited = [];
		}
		$tracked = self::get_tracked();
		$orig    = $tracked;
		$started = [];
		foreach ( $ids as $poll_id ) {
			$poll_id = (int) $poll_id;
			if ( isset( $invited[ $poll_id ] ) ) {
				continue;
			}
			// Jakikolwiek wpis w kolejce = zaproszenia już rozpoczęte (ręcznie lub wcześniej) — nie dublujemy.
			$has_queue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$eq} WHERE poll_id = %d", $poll_id ) );
			if ( $has_queue > 0 ) {
				$invited[ $poll_id ] = time();
				continue;
			}
			// Znacznik PRZED startem: nawet awaria w trakcie nie spowoduje drugiego masowego startu.
			$invited[ $poll_id ] = time();
			update_option( self::OPT_INVITED, $invited, false );
			try {
				$job_id = Openvote_Batch_Processor::start_job( 'send_invitations', [ 'poll_id' => $poll_id ], 'heartbeat' );
				$tracked[ 'p' . $poll_id ] = $job_id;
				$started[] = $poll_id;
				if ( function_exists( 'openvote_polls_audit_log_append' ) ) {
					$poll = Openvote_Poll::get( $poll_id );
					if ( $poll ) {
						openvote_polls_audit_log_append( 0, sprintf( __( 'automatycznie rozpoczęto wysyłkę zaproszeń na głosowanie %s', 'openvote' ), $poll->title ) );
					}
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Openvote heartbeat auto-invite: ' . $e->getMessage() );
				}
			}
		}
		update_option( self::OPT_INVITED, $invited, false );
		self::commit_tracked( $orig, $tracked );
		return $started ? sprintf( __( 'rozpoczęto zaproszenia dla głosowań: %s', 'openvote' ), implode( ',', $started ) ) : '';
	}

	// ─── (b) Wznowienie zadań wstrzymanych limitami ─────────────────────────

	private static function task_resume_paused(): string {
		global $wpdb;
		$tracked = self::get_tracked();
		$orig    = $tracked;
		$added   = [];
		$now     = time();

		// Zadania wstrzymane limitem (limit_exceeded) lub porzucone (running bez partii od STALE_AFTER s) — zaproszenia i wiadomości.
		// Uwaga: przy zewnętrznym cache obiektów transienty nie trafiają do wp_options — wtedy działa rejestracja w start_job().
		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_openvote\\_job\\_%' LIMIT 200" );
		foreach ( (array) $rows as $name ) {
			$job_id = substr( $name, strlen( '_transient_' ) );
			$job    = get_transient( $job_id );
			if ( ! is_array( $job ) ) {
				continue;
			}
			$st = $job['status'] ?? '';
			if ( $st !== 'limit_exceeded' && ! ( $st === 'running' && self::is_job_stale( $job, $now ) ) ) {
				continue;
			}
			$type = $job['type'] ?? '';
			if ( $type === 'send_invitations' && ! empty( $job['params']['poll_id'] ) ) {
				$key = 'p' . absint( $job['params']['poll_id'] );
			} elseif ( $type === 'send_mass_message' && ! empty( $job['params']['message_id'] ) ) {
				$key = 'm' . absint( $job['params']['message_id'] );
			} else {
				continue;
			}
			if ( ! isset( $tracked[ $key ] ) ) {
				$tracked[ $key ] = $job_id;
				$added[]         = $key;
			}
		}

		// Lista „wznów o północy” z Openvote_Email_Resume_Cron (dzienny limit) — przejmujemy ją.
		$ids = get_option( Openvote_Email_Resume_Cron::OPTION_IDS, [] );
		if ( is_array( $ids ) && $ids ) {
			foreach ( array_unique( array_map( 'absint', $ids ) ) as $pid ) {
				if ( $pid && ! isset( $tracked[ 'p' . $pid ] ) ) {
					$tracked[ 'p' . $pid ] = '';
					$added[]               = 'p' . $pid;
				}
			}
			update_option( Openvote_Email_Resume_Cron::OPTION_IDS, [], false );
		}

		self::commit_tracked( $orig, $tracked );
		return $added ? sprintf( __( 'do wznowienia: %s', 'openvote' ), implode( ',', $added ) ) : '';
	}

	// ─── Przetwarzanie śledzonych zadań ─────────────────────────────────────

	private static function task_process_jobs( int $deadline ): string {
		global $wpdb;
		$tracked = self::get_tracked();
		if ( ! $tracked ) {
			return '';
		}
		$orig = $tracked;
		$eq  = $wpdb->prefix . 'openvote_email_queue';
		$mq  = $wpdb->prefix . 'openvote_message_queue';
		$out = [];

		foreach ( $tracked as $key => $job_id ) {
			$key    = (string) $key;
			$job_id = (string) $job_id;
			if ( time() >= $deadline ) {
				$out[] = $key . ': ' . __( 'czeka na kolejny tick', 'openvote' );
				continue;
			}
			$kind = $key[0];
			$id   = (int) substr( $key, 1 );
			if ( $kind === 'p' ) {
				$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$eq} WHERE poll_id = %d AND status = 'pending'", $id ) );
				$type    = 'send_invitations';
				$params  = [ 'poll_id' => $id ];
				$poll    = Openvote_Poll::get( $id );
				if ( ! $poll || ! in_array( $poll->status, [ 'open', 'closed' ], true ) ) {
					unset( $tracked[ $key ] );
					continue;
				}
			} else {
				$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$mq} WHERE message_id = %d AND status = 'pending'", $id ) );
				$type    = 'send_mass_message';
				$params  = [ 'message_id' => $id ];
			}
			if ( $pending <= 0 ) {
				unset( $tracked[ $key ] );
				$out[] = $key . ': ' . __( 'zakończone', 'openvote' );
				continue;
			}

			$job = $job_id !== '' ? Openvote_Batch_Processor::get_job( $job_id ) : false;
			if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'cancelled' ) {
				unset( $tracked[ $key ] ); // zatrzymane przez człowieka — szanujemy.
				continue;
			}
			if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'running' && ! self::is_job_stale( $job, time() ) ) {
				// Zadanie z panelu z niedawną partią — panel (AJAX) nadal je prowadzi; przejmiemy je, gdy ucichnie.
				$out[] = $key . ': ' . __( 'prowadzone z panelu', 'openvote' );
				continue;
			}
			if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'running' && ( $job['owner'] ?? '' ) !== 'heartbeat' ) {
				// Porzucone w panelu (np. zamknięta karta) — przejmujemy; blokada per cel w process_batch chroni przed dublem.
				$job['owner']  = 'heartbeat';
				$job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Przejęte przez cron (brak aktywności panelu).', 'openvote' );
				set_transient( $job_id, $job, HOUR_IN_SECONDS );
				$out[] = $key . ': ' . __( 'przejęto porzucone', 'openvote' );
			}
			if ( ! is_array( $job ) || ( $job['status'] ?? '' ) !== 'running' ) {
				if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'done' ) {
					// „done”, a nadal są oczekujące — nic więcej nie da się wysłać automatycznie.
					unset( $tracked[ $key ] );
					continue;
				}
				// Brak joba (wygasł) albo wstrzymany limitem: wznów tylko gdy limity pozwalają.
				$batch   = function_exists( 'openvote_get_email_batch_size' ) ? openvote_get_email_batch_size() : 100;
				$check_n = min( max( 1, $batch ), $pending );
				$limit   = Openvote_Email_Rate_Limits::would_exceed_limits( $check_n );
				if ( ! empty( $limit['exceeded'] ) ) {
					$out[] = $key . ': ' . __( 'wstrzymane limitem', 'openvote' ) . ' (' . $limit['limit_type'] . ')';
					continue;
				}
				if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'limit_exceeded' ) {
					// Stary (martwy) job zastępujemy nowym — tak samo robi ekran zaproszeń po ponowieniu.
					$job['status'] = 'cancelled';
					$job['logs'][] = gmdate( 'Y-m-d H:i:s' ) . ' ' . __( 'Wznowione automatycznie przez cron.', 'openvote' );
					set_transient( $job_id, $job, HOUR_IN_SECONDS );
				}
				$job_id = Openvote_Batch_Processor::start_job( $type, $params, 'heartbeat' );
				$tracked[ $key ] = $job_id;
				$out[] = $key . ': ' . __( 'wznowiono', 'openvote' );
			}

			// Partie do wyczerpania budżetu czasu, limitu lub kolejki.
			$batches = 0;
			$delay   = function_exists( 'openvote_get_email_batch_delay' ) ? max( 0, (int) openvote_get_email_batch_delay() ) : 0; // ms
			$status  = 'running';
			$res     = null;
			while ( time() < $deadline ) {
				$res = Openvote_Batch_Processor::process_batch( $job_id );
				if ( ! is_array( $res ) ) {
					$status = 'expired';
					break;
				}
				if ( ! empty( $res['lock_busy'] ) ) {
					$status = 'running (' . __( 'równolegle wysyła inny proces', 'openvote' ) . ')';
					break; // blokada per cel zajęta (np. panel) — nie kręcimy pętli, wrócimy w kolejnym ticku.
				}
				$batches++;
				$status = (string) ( $res['status'] ?? '' );
				if ( $status !== 'running' ) {
					break;
				}
				if ( $delay > 0 ) {
					if ( time() + (int) ceil( $delay / 1000 ) >= $deadline ) {
						break;
					}
					usleep( $delay * 1000 );
				}
			}
			$sent_now = is_array( $res ) ? (int) ( $res['processed'] ?? 0 ) : 0;
			$tracked[ $key ] = $job_id;
			$out[] = sprintf( '%s: %s, %d partii, wysłano łącznie %d', $key, $status, $batches, $sent_now );
			if ( $status === 'done' ) {
				unset( $tracked[ $key ] );
			}
		}
		self::commit_tracked( $orig, $tracked );
		return $out ? implode( '; ', $out ) : '';
	}

	// ─── (c) WP-Cron wtyczki ────────────────────────────────────────────────

	/** Wykonuje zaległe zdarzenia openvote_* (tak jak wp-cron.php, ale bez zależności od odwiedzin). */
	private static function task_wp_cron( int $deadline ): string {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return '';
		}
		$now = time();
		$ran = [];
		foreach ( $crons as $ts => $hooks ) {
			if ( (int) $ts > $now ) {
				break;
			}
			foreach ( $hooks as $hook => $events ) {
				if ( strpos( (string) $hook, 'openvote_' ) !== 0 ) {
					continue;
				}
				foreach ( $events as $sig => $event ) {
					if ( time() >= $deadline ) {
						break 3;
					}
					$args = $event['args'] ?? [];
					if ( ! empty( $event['schedule'] ) ) {
						wp_reschedule_event( (int) $ts, $event['schedule'], $hook, $args );
					}
					wp_unschedule_event( (int) $ts, $hook, $args );
					do_action_ref_array( $hook, $args );
					$ran[ $hook ] = true;
				}
			}
		}
		return $ran ? sprintf( __( 'wp-cron: %s', 'openvote' ), implode( ',', array_keys( $ran ) ) ) : '';
	}

	// ─── Pomocnicze ─────────────────────────────────────────────────────────

	private static function get_tracked(): array {
		$t = get_option( self::OPT_TRACKED, [] );
		return is_array( $t ) ? $t : [];
	}

	private static function set_tracked( array $t ): void {
		update_option( self::OPT_TRACKED, $t, false );
	}

	/**
	 * Zarejestruj zadanie wysyłki do śledzenia przez heartbeat (wołane z Openvote_Batch_Processor::start_job).
	 * Dzięki temu zadanie z panelu zostanie dokończone przez cron, gdy karta przeglądarki zostanie zamknięta.
	 *
	 * @param string $key    'p{poll_id}' lub 'm{message_id}'.
	 * @param string $job_id ID zadania (transient).
	 */
	public static function track_job( string $key, string $job_id ): void {
		if ( ! preg_match( '/^[pm][1-9]\d*$/', $key ) ) {
			return;
		}
		$t         = self::get_tracked();
		$t[ $key ] = $job_id;
		self::set_tracked( $t );
	}

	/**
	 * Zapis zmian w śledzonych bez gubienia wpisów dodanych równolegle (np. start_job z panelu w trakcie ticka).
	 */
	private static function commit_tracked( array $original, array $final ): void {
		$current = self::get_tracked();
		$merged  = self::merge_tracked( $original, $final, $current );
		if ( $merged !== $current ) {
			self::set_tracked( $merged );
		}
	}

	/**
	 * Nałóż zmiany ($original → $final) na stan bieżący $current. Zmiana klucza jest stosowana tylko wtedy,
	 * gdy w międzyczasie nikt inny go nie zmienił ($current[$k] === $original[$k]); inaczej wygrywa $current.
	 *
	 * @return array<string,string>
	 */
	public static function merge_tracked( array $original, array $final, array $current ): array {
		$keys = array_unique( array_merge( array_keys( $original ), array_keys( $final ) ) );
		foreach ( $keys as $k ) {
			$had_o = array_key_exists( $k, $original );
			$had_f = array_key_exists( $k, $final );
			if ( $had_o && $had_f && $original[ $k ] === $final[ $k ] ) {
				continue; // bez zmian po naszej stronie.
			}
			$had_c     = array_key_exists( $k, $current );
			$untouched = ( $had_o === $had_c ) && ( ! $had_o || $current[ $k ] === $original[ $k ] );
			if ( ! $untouched ) {
				continue; // ktoś inny zmienił ten wpis — nie nadpisujemy.
			}
			if ( $had_f ) {
				$current[ $k ] = $final[ $k ];
			} else {
				unset( $current[ $k ] );
			}
		}
		return $current;
	}

	/**
	 * Czy zadanie „running” jest porzucone: właściciel ≠ heartbeat i brak partii od STALE_AFTER s.
	 * Zadania uruchomione przez heartbeat są zawsze „do wzięcia”.
	 */
	public static function is_job_stale( array $job, int $now ): bool {
		if ( ( $job['owner'] ?? '' ) === 'heartbeat' ) {
			return true;
		}
		$last = (int) ( $job['last_activity'] ?? ( $job['started_at'] ?? 0 ) );
		return $last <= 0 || ( $now - $last ) >= self::STALE_AFTER;
	}
}
