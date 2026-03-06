<?php
/**
 * PHPUnit bootstrap for unit tests (no database, no WordPress loaded).
 *
 * Key ordering rules:
 *  1. Define stub classes BEFORE loading vendor/autoload.php so the Composer
 *     classmap autoloader never gets to load the real implementations.
 *  2. Load vendor/autoload.php (PHPUnit, Brain Monkey, Mockery).
 *  3. Define plugin helper functions that are normally in openvote.php.
 *  4. Require only the plugin class files actually exercised by unit tests.
 */

// ── WordPress-like constants ────────────────────────────────────────────────

define('ABSPATH',               dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('WPINC',                 'wp-includes');
define('OPENVOTE_VERSION',      '1.0.20');
define('OPENVOTE_PLUGIN_DIR',   dirname(__DIR__) . DIRECTORY_SEPARATOR);

// ── Stub classes (must be defined BEFORE autoloader so it never fires) ──────
//
// These are declared globally (no namespace) so they're in the same namespace
// as the plugin code under test.

class OpenvoteTestConfig {
    /** openvote_get_email_limit_per_15min() return value */
    public static int $limit_15min = 0;
    /** openvote_get_email_limit_per_hour() return value */
    public static int $limit_hour  = 0;
    /** openvote_get_email_limit_per_day() return value */
    public static int $limit_day   = 0;
    /**
     * Unix timestamp for openvote_current_time_for_voting().
     * 0 = use time() (real current time).
     */
    public static int $mock_time   = 0;

    public static function reset(): void {
        self::$limit_15min = 0;
        self::$limit_hour  = 0;
        self::$limit_day   = 0;
        self::$mock_time   = 0;
    }
}

class WP_Error {
    public string $code;
    public string $message;
    public array  $data;

    public function __construct(string $code = '', string $message = '', mixed $data = '') {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = is_array($data) ? $data : [];
    }

    public function get_error_code(): string { return $this->code; }
    public function get_error_data(): array  { return $this->data; }
}

/** Always grants eligibility — lets cast() reach the transaction block. */
class Openvote_Eligibility {
    public static function can_vote_or_error(int $user_id, int $poll_id): true|\WP_Error {
        return true;
    }

    public static function can_vote(int $user_id, int $poll_id): array {
        return ['eligible' => true, 'reason' => ''];
    }
}

class Openvote_Field_Map {
    public const WSZYSCY_NAME = 'Wszyscy';

    public static function get(): array {
        return [
            'email'      => 'user_email',
            'nickname'   => 'nickname',
            'first_name' => 'first_name',
            'last_name'  => 'last_name',
            'city'       => 'city',
        ];
    }

    public static function is_core_field(string $field): bool { return true; }

    public static function get_required_fields(): array { return []; }

    public static function get_user_value(object $user, string $logical): string { return 'test'; }

    public static function is_city_disabled(): bool { return false; }
}

// ── Composer autoloader (PHPUnit, Brain Monkey, Mockery) ────────────────────
// Plugin production classes are in the classmap but are already defined above
// as stubs, so the autoloader will never reload them.

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ── Plugin helper functions (normally in openvote.php) ───────────────────────

function openvote_current_time_for_voting(string $format = 'Y-m-d H:i:s'): string {
    $ts = OpenvoteTestConfig::$mock_time > 0 ? OpenvoteTestConfig::$mock_time : time();
    return date($format, $ts);
}

function openvote_get_email_limit_per_15min(): int { return OpenvoteTestConfig::$limit_15min; }
function openvote_get_email_limit_per_hour(): int  { return OpenvoteTestConfig::$limit_hour;  }
function openvote_get_email_limit_per_day(): int   { return OpenvoteTestConfig::$limit_day;   }

// ── Load plugin class files exercised by unit tests ──────────────────────────
// Only files actually needed; Openvote_Eligibility and Openvote_Field_Map are
// already defined as stubs above.

require_once dirname(__DIR__) . '/models/class-openvote-poll.php';
require_once dirname(__DIR__) . '/models/class-openvote-vote.php';
require_once dirname(__DIR__) . '/includes/class-openvote-email-rate-limits.php';
