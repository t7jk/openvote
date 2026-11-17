<?php
defined( 'ABSPATH' ) || exit;

class Openvote_Deactivator {

    /**
     * Hooki WP-Cron planowane przez wtyczkę (nazwy literalnie — przy dezaktywacji/odinstalowaniu
     * klasy Openvote_Cron_Sync / Openvote_Email_Resume_Cron mogą nie być załadowane).
     * Ponowna aktywacja: Openvote_Activator::activate() → Openvote_Cron_Sync::reschedule();
     * tick synchronizacji i wznowienia zaproszeń przywraca heartbeat (Openvote_Cron_Sync::ensure_scheduled,
     * przejęcie listy openvote_email_auto_resume_poll_ids).
     */
    const CRON_HOOKS = [
        'openvote_cron_sync_sejmiki',
        'openvote_cron_sync_tick',
        'openvote_resume_scheduled_invitations',
    ];

    public static function deactivate(): void {
        foreach ( self::CRON_HOOKS as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }
    }
}
