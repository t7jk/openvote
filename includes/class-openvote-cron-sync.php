<?php
defined( 'ABSPATH' ) || exit;

/**
 * Automatyczna synchronizacja grup-miast przez wp-cron.
 *
 * Harmonogram: openvote_auto_sync_schedule (manual | first_sunday | second_sunday | weekly | daily).
 * Cron uruchamia się codziennie o 00:00 w strefie WordPress; callback sprawdza harmonogram i startuje job.
 * Tick (openvote_cron_sync_tick) przetwarza partiami do ~25 s i planuje kolejny tick aż job się skończy.
 */
class Openvote_Cron_Sync {

    const HOOK_SYNC  = 'openvote_cron_sync_sejmiki';
    const HOOK_TICK  = 'openvote_cron_sync_tick';
    const OPTION_JOB = 'openvote_cron_sync_job_id';
    const TICK_DELAY = 120;      // sekund między tickami
    const TICK_LIMIT = 25;        // max sekund na przetwarzanie w jednym ticku
    const OPTION_RUN_MARK = 'openvote_cron_sync_run_date'; // Y-m-d (strefa WP) ostatniego startu z harmonogramu — blokada + idempotencja.
    const LOCK_TICK       = 'openvote_cron_sync_tick_lock';

    /**
     * Rejestruje hooki i wywołuje reschedule (np. z loadera i activatora).
     */
    public static function register(): void {
        add_action( self::HOOK_SYNC, [ __CLASS__, 'run_scheduled_sync' ] );
        add_action( self::HOOK_TICK, [ __CLASS__, 'run_tick' ] );
    }

    /**
     * Usuwa zaplanowane zdarzenia i planuje codzienny cron o 00:00, jeśli harmonogram ≠ manual.
     * Wywołać po zapisie ustawień i przy aktywacji wtyczki.
     */
    public static function reschedule(): void {
        wp_clear_scheduled_hook( self::HOOK_SYNC );

        $schedule = get_option( 'openvote_auto_sync_schedule', 'first_sunday' );
        if ( $schedule === 'manual' ) {
            return;
        }

        $tz = wp_timezone();
        if ( ! $tz ) {
            return;
        }
        $now     = new DateTimeImmutable( 'now', $tz );
        $midnight = $now->setTime( 0, 0, 0 );
        if ( $midnight <= $now ) {
            $midnight = $midnight->modify( '+1 day' );
        }
        $timestamp = $midnight->getTimestamp();
        wp_schedule_event( $timestamp, 'daily', self::HOOK_SYNC );
    }

    /**
     * Samonaprawa harmonogramu (wołana przy każdym ticku heartbeatu):
     *  - harmonogram ≠ manual i brak zaplanowanego HOOK_SYNC → reschedule(),
     *  - trwający job synchronizacji (OPTION_JOB) bez zaplanowanego HOOK_TICK → zaplanuj tick.
     *
     * @return string[] Nazwy przywróconych hooków.
     */
    public static function ensure_scheduled(): array {
        $fixed    = [];
        $schedule = get_option( 'openvote_auto_sync_schedule', 'first_sunday' );
        if ( $schedule !== 'manual' && ! wp_next_scheduled( self::HOOK_SYNC ) ) {
            self::reschedule();
            if ( wp_next_scheduled( self::HOOK_SYNC ) ) {
                $fixed[] = self::HOOK_SYNC;
            }
        }
        $job_id = get_option( self::OPTION_JOB, '' );
        if ( is_string( $job_id ) && $job_id !== '' && ! wp_next_scheduled( self::HOOK_TICK ) ) {
            $job = Openvote_Batch_Processor::get_job( $job_id );
            if ( is_array( $job ) && ( $job['status'] ?? '' ) === 'running' ) {
                wp_schedule_single_event( time() + 5, self::HOOK_TICK );
                $fixed[] = self::HOOK_TICK;
            } else {
                delete_option( self::OPTION_JOB );
            }
        }
        return $fixed;
    }

    /**
     * Atomowe zajęcie znacznika w wp_options (INSERT IGNORE / UPDATE z warunkiem na starą wartość).
     * Bez get_option/update_option — omija cache opcji, więc dwa procesy nie zobaczą „wolnego” jednocześnie.
     *
     * @param string   $name    Nazwa opcji-znacznika.
     * @param string   $value   Nowa wartość.
     * @param callable $may_take fn( string $old ): bool — czy wolno przejąć istniejący znacznik.
     */
    private static function claim_marker( string $name, string $value, callable $may_take ): bool {
        global $wpdb;
        $ins = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $name,
            $value
        ) );
        if ( $ins ) {
            return true;
        }
        $old = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
        if ( null === $old || ! $may_take( (string) $old ) ) {
            return false;
        }
        return (bool) $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            $value,
            $name,
            (string) $old
        ) );
    }

    /**
     * Callback crona o 00:00: sprawdza harmonogram i w razie dopasowania startuje job, zapisuje job_id, planuje tick.
     * Ten sam hook może odpalić WP-Cron (wp-cron.php) i heartbeat (task_wp_cron) — znacznik dnia gwarantuje jeden start na dobę.
     */
    public static function run_scheduled_sync(): void {
        $schedule = get_option( 'openvote_auto_sync_schedule', 'first_sunday' );
        if ( $schedule === 'manual' ) {
            return;
        }

        $tz = wp_timezone();
        if ( ! $tz ) {
            return;
        }
        $now = new DateTimeImmutable( 'now', $tz );
        $w  = (int) $now->format( 'w' );   // 0 = niedziela
        $d  = (int) $now->format( 'j' );   // dzień miesiąca

        $run = false;
        switch ( $schedule ) {
            case 'daily':
                $run = true;
                break;
            case 'weekly':
                $run = ( $w === 0 );
                break;
            case 'first_sunday':
                $run = ( $w === 0 && $d >= 1 && $d <= 7 );
                break;
            case 'second_sunday':
                $run = ( $w === 0 && $d >= 8 && $d <= 14 );
                break;
            default:
                break;
        }

        if ( ! $run ) {
            return;
        }

        $today = $now->format( 'Y-m-d' );
        if ( ! self::claim_marker( self::OPTION_RUN_MARK, $today, static fn( string $old ): bool => $old !== $today ) ) {
            return; // dziś już wystartowano (lub właśnie startuje równoległy proces).
        }

        $job_id = Openvote_Batch_Processor::start_job( 'sync_all_city_groups', [] );
        update_option( self::OPTION_JOB, $job_id, false );
        wp_schedule_single_event( time() + self::TICK_DELAY, self::HOOK_TICK );
    }

    /**
     * Callback ticka: pobiera job_id z opcji, przetwarza partiami do TICK_LIMIT s, przy done czyści opcję; przy running planuje kolejny tick.
     */
    public static function run_tick(): void {
        $job_id = get_option( self::OPTION_JOB, '' );
        if ( $job_id === '' || ! is_string( $job_id ) ) {
            return;
        }

        $job = Openvote_Batch_Processor::get_job( $job_id );
        if ( false === $job || $job['status'] === 'done' || $job['status'] === 'cancelled' ) {
            delete_option( self::OPTION_JOB );
            return;
        }

        // Blokada przed równoległym tickiem (WP-Cron + heartbeat) — przejęcie po 2× TICK_LIMIT (porzucona).
        $now = time();
        if ( ! self::claim_marker( self::LOCK_TICK, (string) $now, static fn( string $old ): bool => ( $now - (int) $old ) > 2 * self::TICK_LIMIT ) ) {
            return;
        }

        try {
            $deadline = time() + self::TICK_LIMIT;
            while ( time() < $deadline ) {
                $job = Openvote_Batch_Processor::process_batch( $job_id );
                if ( false === $job ) {
                    delete_option( self::OPTION_JOB );
                    return;
                }
                if ( $job['status'] === 'done' || $job['status'] === 'cancelled' ) {
                    delete_option( self::OPTION_JOB );
                    return;
                }
            }

            if ( ! wp_next_scheduled( self::HOOK_TICK ) ) {
                wp_schedule_single_event( time() + self::TICK_DELAY, self::HOOK_TICK );
            }
        } finally {
            global $wpdb;
            $wpdb->delete( $wpdb->options, [ 'option_name' => self::LOCK_TICK ], [ '%s' ] );
        }
    }
}
