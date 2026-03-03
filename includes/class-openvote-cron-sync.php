<?php
defined( 'ABSPATH' ) || exit;

/**
 * Automatyczna synchronizacja sejmików-miast przez wp-cron.
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

        $schedule = get_option( 'openvote_auto_sync_schedule', 'manual' );
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
     * Callback crona o 00:00: sprawdza harmonogram i w razie dopasowania startuje job, zapisuje job_id, planuje tick.
     */
    public static function run_scheduled_sync(): void {
        $schedule = get_option( 'openvote_auto_sync_schedule', 'manual' );
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

        wp_schedule_single_event( time() + self::TICK_DELAY, self::HOOK_TICK );
    }
}
