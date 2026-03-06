<?php
defined( 'ABSPATH' ) || exit;

/**
 * Liczniki wysłanych e-maili w oknach 15 min / 1 h / doba z resetem co 15 min / co godzinę / co dobę.
 * Używane do egzekwowania limitów z Konfiguracji (Warunki wysyłki e-maili).
 */
class Openvote_Email_Rate_Limits {

	private const OPT_SLOT_15  = 'openvote_email_sent_15min_slot';
	private const OPT_COUNT_15 = 'openvote_email_sent_15min_count';
	private const OPT_SLOT_H   = 'openvote_email_sent_hour_slot';
	private const OPT_COUNT_H  = 'openvote_email_sent_hour_count';
	private const OPT_SLOT_D   = 'openvote_email_sent_day_slot';
	private const OPT_COUNT_D  = 'openvote_email_sent_day_count';

	/**
	 * Zwraca aktualne sloty czasowe (strefa głosowań).
	 *
	 * @return array{ slot_15: string, slot_hour: string, slot_day: string }
	 */
	public static function get_current_slots(): array {
		$now = function_exists( 'openvote_current_time_for_voting' )
			? openvote_current_time_for_voting( 'Y-m-d H:i:s' )
			: current_time( 'Y-m-d H:i:s' );
		$dt = date_create_from_format( 'Y-m-d H:i:s', $now );
		if ( ! $dt ) {
			$dt = date_create( $now );
		}
		if ( ! $dt ) {
			$slot_day  = substr( $now, 0, 10 );
			$slot_hour = substr( $now, 0, 13 );
			$min       = (int) substr( $now, 14, 2 );
			$slot_15   = $slot_hour . '-' . str_pad( (string) ( floor( $min / 15 ) * 15 ), 2, '0', STR_PAD_LEFT );
			return [ 'slot_15' => $slot_15, 'slot_hour' => $slot_hour, 'slot_day' => $slot_day ];
		}
		$slot_day  = $dt->format( 'Y-m-d' );
		$slot_hour = $dt->format( 'Y-m-d-H' );
		$min       = (int) $dt->format( 'i' );
		$slot_15   = $dt->format( 'Y-m-d-H' ) . '-' . str_pad( (string) ( floor( $min / 15 ) * 15 ), 2, '0', STR_PAD_LEFT );
		return [ 'slot_15' => $slot_15, 'slot_hour' => $slot_hour, 'slot_day' => $slot_day ];
	}

	/**
	 * Zwiększa liczniki o $n (po faktycznej wysyłce).
	 *
	 * @param int $n Liczba wysłanych e-maili.
	 */
	public static function increment( int $n ): void {
		if ( $n <= 0 ) {
			return;
		}
		$slots = self::get_current_slots();

		global $wpdb;
		$opt_pairs = [
			'15'   => [ self::OPT_SLOT_15, self::OPT_COUNT_15, $slots['slot_15'] ],
			'hour' => [ self::OPT_SLOT_H,  self::OPT_COUNT_H,  $slots['slot_hour'] ],
			'day'  => [ self::OPT_SLOT_D,  self::OPT_COUNT_D,  $slots['slot_day'] ],
		];

		$wpdb->query( 'START TRANSACTION' );
		foreach ( $opt_pairs as $opt_pair ) {
			list( $opt_slot, $opt_count, $current_slot ) = $opt_pair;

			$stored_slot = $wpdb->get_var( $wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
				$opt_slot
			) );
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
				$opt_count
			) );

			$new_count = ( $stored_slot === $current_slot ) ? ( $count + $n ) : $n;

			// Use INSERT ... ON DUPLICATE KEY UPDATE so rows are created on first run.
			// A plain UPDATE silently does nothing when the row does not yet exist.
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				 VALUES (%s, %s, 'no')
				 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
				$opt_slot,
				(string) $current_slot
			) );
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				 VALUES (%s, %d, 'no')
				 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
				$opt_count,
				$new_count
			) );
		}
		$wpdb->query( 'COMMIT' );

		// Invalidate object cache so subsequent reads see the updated counts.
		foreach ( $opt_pairs as $opt_pair ) {
			wp_cache_delete( $opt_pair[0], 'options' );
			wp_cache_delete( $opt_pair[1], 'options' );
		}
	}

	/**
	 * Zwraca bieżące liczny (po ewentualnym resecie slotów).
	 *
	 * @return array{ count_15: int, count_hour: int, count_day: int }
	 */
	public static function get_counts(): array {
		$slots = self::get_current_slots();
		$out   = [ 'count_15' => 0, 'count_hour' => 0, 'count_day' => 0 ];

		$pairs = [
			'count_15'  => [ self::OPT_SLOT_15, self::OPT_COUNT_15, $slots['slot_15'] ],
			'count_hour' => [ self::OPT_SLOT_H, self::OPT_COUNT_H, $slots['slot_hour'] ],
			'count_day'  => [ self::OPT_SLOT_D, self::OPT_COUNT_D, $slots['slot_day'] ],
		];
		foreach ( $pairs as $key => $opt_pair ) {
			list( $opt_slot, $opt_count, $current_slot ) = $opt_pair;
			$stored_slot = get_option( $opt_slot, '' );
			if ( $stored_slot !== $current_slot ) {
				$out[ $key ] = 0;
			} else {
				$out[ $key ] = (int) get_option( $opt_count, 0 );
			}
		}
		return $out;
	}

	/**
	 * Sprawdza, czy wysłanie dodatkowych $n e-maili przekroczyłoby którykolwiek limit.
	 * Używa openvote_get_email_limit_per_15min/hour/day() dla bieżącej metody.
	 *
	 * @param int $n Planowana liczba e-maili do wysłania.
	 * @return array{ exceeded: bool, limit_type: string, limit_max: int, wait_seconds: int, message: string } limit_type: '15min'|'hour'|'day' lub ''.
	 */
	public static function would_exceed_limits( int $n ): array {
		$limit_15  = function_exists( 'openvote_get_email_limit_per_15min' ) ? openvote_get_email_limit_per_15min() : 0;
		$limit_h   = function_exists( 'openvote_get_email_limit_per_hour' ) ? openvote_get_email_limit_per_hour() : 0;
		$limit_day = function_exists( 'openvote_get_email_limit_per_day' ) ? openvote_get_email_limit_per_day() : 0;

		$counts = self::get_counts();
		$after_15  = $counts['count_15'] + $n;
		$after_hour = $counts['count_hour'] + $n;
		$after_day  = $counts['count_day'] + $n;

		if ( $limit_15 > 0 && $after_15 > $limit_15 ) {
			$wait = self::seconds_until_next_slot_15();
			$msg  = sprintf(
				/* translators: 1: max count, 2: wait time in minutes */
				__( 'Przekroczono limit e-maili na 15 minut (max %1$d). Wznowienie za %2$d min.', 'openvote' ),
				$limit_15,
				(int) ceil( $wait / 60 )
			);
			return [ 'exceeded' => true, 'limit_type' => '15min', 'limit_max' => $limit_15, 'wait_seconds' => $wait, 'message' => $msg ];
		}
		if ( $limit_h > 0 && $after_hour > $limit_h ) {
			$wait = self::seconds_until_next_slot_hour();
			$msg  = sprintf(
				/* translators: 1: max count, 2: wait time in minutes */
				__( 'Przekroczono limit e-maili na godzinę (max %1$d). Wznowienie za %2$d min.', 'openvote' ),
				$limit_h,
				(int) ceil( $wait / 60 )
			);
			return [ 'exceeded' => true, 'limit_type' => 'hour', 'limit_max' => $limit_h, 'wait_seconds' => $wait, 'message' => $msg ];
		}
		if ( $limit_day > 0 && $after_day > $limit_day ) {
			$wait = self::seconds_until_next_slot_day();
			$msg  = sprintf(
				/* translators: 1: max count */
				__( 'Przekroczono limit dzienny (max %1$d). Wznowienie możliwe od północy (strefa głosowań).', 'openvote' ),
				$limit_day
			);
			return [ 'exceeded' => true, 'limit_type' => 'day', 'limit_max' => $limit_day, 'wait_seconds' => $wait, 'message' => $msg ];
		}

		return [ 'exceeded' => false, 'limit_type' => '', 'limit_max' => 0, 'wait_seconds' => 0, 'message' => '' ];
	}

	private static function seconds_until_next_slot_15(): int {
		$now = function_exists( 'openvote_current_time_for_voting' ) ? openvote_current_time_for_voting( 'Y-m-d H:i:s' ) : current_time( 'Y-m-d H:i:s' );
		$min = (int) substr( $now, 14, 2 );
		$sec = (int) substr( $now, 17, 2 );
		$elapsed_in_window = ( $min % 15 ) * 60 + $sec;
		return max( 0, 15 * 60 - $elapsed_in_window );
	}

	private static function seconds_until_next_slot_hour(): int {
		$now = function_exists( 'openvote_current_time_for_voting' ) ? openvote_current_time_for_voting( 'Y-m-d H:i:s' ) : current_time( 'Y-m-d H:i:s' );
		$min = (int) substr( $now, 14, 2 );
		$sec = (int) substr( $now, 17, 2 );
		return max( 0, 3600 - ( $min * 60 + $sec ) );
	}

	private static function seconds_until_next_slot_day(): int {
		$now = function_exists( 'openvote_current_time_for_voting' ) ? openvote_current_time_for_voting( 'Y-m-d H:i:s' ) : current_time( 'Y-m-d H:i:s' );
		$hour = (int) substr( $now, 11, 2 );
		$min  = (int) substr( $now, 14, 2 );
		$sec  = (int) substr( $now, 17, 2 );
		return max( 0, 86400 - ( $hour * 3600 + $min * 60 + $sec ) );
	}
}
