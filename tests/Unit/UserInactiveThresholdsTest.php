<?php

namespace Openvote\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Nieaktywność = wyłącznie liczba opuszczonych głosowań; próg ze skali 1,2,3,4,5,8,10,∞ (0 = ∞).
 * Funkcje są wczytywane z openvote.php (plik nie jest ładowany w całości w testach jednostkowych).
 */
class UserInactiveThresholdsTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if ( function_exists( 'openvote_is_user_inactive' ) ) {
            return;
        }
        $src = file_get_contents( dirname( __DIR__, 2 ) . '/openvote.php' );
        foreach ( [ 'OPENVOTE_STAT_MISSED_DEFAULT', 'OPENVOTE_STAT_MISSED_STEPS' ] as $const ) {
            if ( ! defined( $const ) && preg_match( '/^const ' . $const . ' = (.*?);$/m', $src, $m ) ) {
                define( $const, eval( 'return ' . $m[1] . ';' ) );
            }
        }
        foreach ( [ 'openvote_snap_stat_missed_votes', 'openvote_get_stat_missed_votes', 'openvote_get_missed_polls_count', 'openvote_is_user_inactive' ] as $fn ) {
            preg_match( '/^function ' . $fn . '\(.*?^\}/ms', $src, $m );
            eval( $m[0] );
        }
    }

    private function setup_user( $missed_prog, int $missed ): void {
        Functions\when( 'get_option' )->alias( fn( $k, $d = false ) => $missed_prog === null ? $d : $missed_prog );
        Functions\when( 'get_user_meta' )->alias( fn( $id, $k, $s = false ) => $k === 'openvote_missed_polls_count' ? $missed : '' );
    }

    public function test_snap_to_scale(): void {
        $this->assertSame( [ 1, 2, 3, 4, 5, 8, 10, 0 ], OPENVOTE_STAT_MISSED_STEPS );
        $this->assertSame( 5, openvote_snap_stat_missed_votes( 5 ) );
        $this->assertSame( 8, openvote_snap_stat_missed_votes( 6 ), 'poza skalą → najbliższy wyższy próg' );
        $this->assertSame( 10, openvote_snap_stat_missed_votes( 9 ) );
        $this->assertSame( 0, openvote_snap_stat_missed_votes( 11 ), 'powyżej 10 → ∞' );
        $this->assertSame( 0, openvote_snap_stat_missed_votes( 24 ) );
        $this->assertSame( 0, openvote_snap_stat_missed_votes( 0 ) );
        $this->assertSame( 0, openvote_snap_stat_missed_votes( -3 ) );
    }

    public function test_infinity_nobody_inactive(): void {
        $this->setup_user( 0, 999 );
        $this->assertFalse( openvote_is_user_inactive( 1 ) );
    }

    public function test_threshold_counts_only_missed_polls(): void {
        $this->setup_user( 3, 2 );
        $this->assertFalse( openvote_is_user_inactive( 1 ) );
        $this->setup_user( 3, 3 );
        $this->assertTrue( openvote_is_user_inactive( 1 ) );
        $this->setup_user( 1, 0 );
        $this->assertFalse( openvote_is_user_inactive( 1 ), 'nigdy nie głosował, ale nic nie opuścił = aktywny' );
    }

    public function test_default_and_legacy_values(): void {
        $this->setup_user( null, 10 );
        $this->assertSame( 10, openvote_get_stat_missed_votes() );
        $this->assertTrue( openvote_is_user_inactive( 1 ) );
        $this->setup_user( 6, 7 );
        $this->assertSame( 8, openvote_get_stat_missed_votes(), 'stara wartość 6 → 8' );
        $this->assertFalse( openvote_is_user_inactive( 1 ) );
    }
}
