<?php
defined( 'ABSPATH' ) || exit;

/**
 * Heartbeat — serwerowy cron (cPanel / crontab) wywołujący wtyczkę co ~15 minut, także gdy nikt nie jest zalogowany.
 *
 * Zadania jednego uderzenia (tick):
 *  (a) automatyczne zaproszenia dla trwających głosowań z notify_start=1 (tylko głosowania wystartowane PO zainstalowaniu funkcji
 *      i bez żadnego wpisu w kolejce zaproszeń — patrz OPT_SINCE),
 *  (b) wznowienie zadań wysyłki wstrzymanych limitami (zaproszenia i wiadomości z „Wysyłki”),
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

	public static function register(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_action( 'admin_init', [ __CLASS__, 'ensure_since' ] );
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
		return '/usr/local/bin/php -q ' . $script . ' >/dev/null 2>&1';
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
			'ok'             => $ts > 0 && ( time() - $ts ) < 20 * MINUTE_IN_SECONDS,
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
			self::ensure_since();
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
				$job_id = Openvote_Batch_Processor::start_job( 'send_invitations', [ 'poll_id' => $poll_id ] );
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
		self::set_tracked( $tracked );
		return $started ? sprintf( __( 'rozpoczęto zaproszenia dla głosowań: %s', 'openvote' ), implode( ',', $started ) ) : '';
	}

	// ─── (b) Wznowienie zadań wstrzymanych limitami ─────────────────────────

	private static function task_resume_paused(): string {
		global $wpdb;
		$tracked = self::get_tracked();
		$added   = [];

		// Zadania wstrzymane limitem (transient joba ze statusem limit_exceeded) — zaproszenia i wiadomości.
		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_openvote\\_job\\_%' LIMIT 200" );
		foreach ( (array) $rows as $name ) {
			$job_id = substr( $name, strlen( '_transient_' ) );
			$job    = get_transient( $job_id );
			if ( ! is_array( $job ) || ( $job['status'] ?? '' ) !== 'limit_exceeded' ) {
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

		self::set_tracked( $tracked );
		return $added ? sprintf( __( 'do wznowienia: %s', 'openvote' ), implode( ',', $added ) ) : '';
	}

	// ─── Przetwarzanie śledzonych zadań ─────────────────────────────────────

	private static function task_process_jobs( int $deadline ): string {
		global $wpdb;
		$tracked = self::get_tracked();
		if ( ! $tracked ) {
			return '';
		}
		$eq  = $wpdb->prefix . 'openvote_email_queue';
		$mq  = $wpdb->prefix . 'openvote_message_queue';
		$out = [];

		foreach ( $tracked as $key => $job_id ) {
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
				$job_id = Openvote_Batch_Processor::start_job( $type, $params );
				$tracked[ $key ] = $job_id;
				$out[] = $key . ': ' . __( 'wznowiono', 'openvote' );
			}

			// Partie do wyczerpania budżetu czasu, limitu lub kolejki.
			$batches = 0;
			$delay   = function_exists( 'openvote_get_email_batch_delay' ) ? max( 0, (int) openvote_get_email_batch_delay() ) : 0; // ms
			$status  = 'running';
			while ( time() < $deadline ) {
				$res = Openvote_Batch_Processor::process_batch( $job_id );
				if ( ! is_array( $res ) ) {
					$status = 'expired';
					break;
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
			$sent_now = is_array( $res ?? null ) ? (int) ( $res['processed'] ?? 0 ) : 0;
			$tracked[ $key ] = $job_id;
			$out[] = sprintf( '%s: %s, %d partii, wysłano łącznie %d', $key, $status, $batches, $sent_now );
			if ( $status === 'done' ) {
				unset( $tracked[ $key ] );
			}
		}
		self::set_tracked( $tracked );
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
}
