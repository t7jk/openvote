<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cron wznowienia wysyłki zaproszeń po resecie limitu dziennego (o północy w strefie głosowań).
 */
class Openvote_Email_Resume_Cron {

	const HOOK_RESUME = 'openvote_resume_scheduled_invitations';
	const OPTION_IDS  = 'openvote_email_auto_resume_poll_ids';

	public static function register(): void {
		add_action( self::HOOK_RESUME, [ __CLASS__, 'run_resume' ] );
	}

	/**
	 * Zaplanuj jednorazowe uruchomienie o następnej północy (strefa głosowań).
	 * Wywołać po dopisaniu poll_id do opcji.
	 */
	public static function schedule_next_midnight(): void {
		if ( wp_next_scheduled( self::HOOK_RESUME ) ) {
			return;
		}
		$offset_h = function_exists( 'openvote_get_time_offset_hours' ) ? openvote_get_time_offset_hours() : 0;
		$voting_now = time() + $offset_h * 3600;
		$next_midnight_voting = ( (int) floor( $voting_now / 86400 ) + 1 ) * 86400;
		$timestamp = $next_midnight_voting - $offset_h * 3600;
		$timestamp = max( time() + 60, $timestamp );
		wp_schedule_single_event( $timestamp, self::HOOK_RESUME );
	}

	/**
	 * Callback: dla każdego poll_id z listy uruchom job send_invitations i usuń z listy.
	 */
	public static function run_resume(): void {
		$ids = get_option( self::OPTION_IDS, [] );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return;
		}
		$ids = array_unique( array_map( 'absint', $ids ) );
		foreach ( $ids as $poll_id ) {
			if ( ! $poll_id ) {
				continue;
			}
			$poll = Openvote_Poll::get( $poll_id );
			if ( ! $poll || ! in_array( $poll->status, [ 'open', 'closed' ], true ) ) {
				continue;
			}
			try {
				Openvote_Batch_Processor::start_job( 'send_invitations', [ 'poll_id' => $poll_id ] );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Openvote email resume: ' . $e->getMessage() );
				}
			}
		}
		update_option( self::OPTION_IDS, [], false );
	}

	/**
	 * Dopisz poll_id do listy i zaplanuj cron na północ (jeśli jeszcze nie zaplanowany).
	 *
	 * @param int $poll_id ID głosowania.
	 * @return bool True jeśli dodano i zaplanowano.
	 */
	public static function add_poll_and_schedule( int $poll_id ): bool {
		$poll_id = absint( $poll_id );
		if ( ! $poll_id ) {
			return false;
		}
		$ids = get_option( self::OPTION_IDS, [] );
		if ( ! is_array( $ids ) ) {
			$ids = [];
		}
		if ( in_array( $poll_id, $ids, true ) ) {
			return true;
		}
		$ids[] = $poll_id;
		update_option( self::OPTION_IDS, $ids, false );
		self::schedule_next_midnight();
		return true;
	}
}
