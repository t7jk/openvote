<?php

namespace Openvote\Tests\Unit;

/**
 * Tests for Openvote_Vote::anonymize_nicename() and ::anonymize_email().
 *
 * These are pure string-transformation methods with no external dependencies,
 * so no WP functions or database mocks are needed.
 */
class VoteAnonymizeTest extends TestCase {

    // ── anonymize_nicename ───────────────────────────────────────────────────

    public function test_empty_string_nicename(): void {
        $this->assertSame('', \Openvote_Vote::anonymize_nicename(''));
    }

    public function test_short_nicename_returns_all_dots(): void {
        // len = 2 ≤ 6 → str_repeat('.', 2)
        $this->assertSame('..', \Openvote_Vote::anonymize_nicename('Ab'));
    }

    public function test_nicename_exactly_six_chars_returns_all_dots(): void {
        // len = 6 ≤ 6 → str_repeat('.', 6)
        $this->assertSame('......', \Openvote_Vote::anonymize_nicename('Abcdef'));
    }

    public function test_nicename_longer_than_six_chars(): void {
        // 'Jan Kowalski' = 12 chars → 'Jan' + '...' + 'ski'
        $this->assertSame('Jan...ski', \Openvote_Vote::anonymize_nicename('Jan Kowalski'));
    }

    public function test_nicename_multibyte_chars(): void {
        // 'Józef' = 5 multibyte chars (mb_strlen=5, ≤6) → '.....'
        $this->assertSame('.....', \Openvote_Vote::anonymize_nicename('Józef'));
    }

    // ── anonymize_email ──────────────────────────────────────────────────────

    public function test_email_without_at_sign_returns_stars(): void {
        // 'noemail' = 7 chars → '*******'
        $this->assertSame('*******', \Openvote_Vote::anonymize_email('noemail'));
    }

    public function test_email_all_segments_two_chars_or_less(): void {
        // local='ab' (≤2), domain='xy'.'pl' (both ≤2) → unchanged
        $this->assertSame('ab@xy.pl', \Openvote_Vote::anonymize_email('ab@xy.pl'));
    }

    public function test_email_standard_address(): void {
        // 'jan@example.com'
        // local: 'jan' (3) → 'ja.'
        // domain: 'example'(7)→'ex.....' + sep '.' + 'com'(3)→'co.' = 'ex......co.'
        $this->assertSame('ja.@ex......co.', \Openvote_Vote::anonymize_email('jan@example.com'));
    }

    public function test_email_long_address(): void {
        // 'Janusz.Kowalski@uniwersytet.edu.pl'
        // local parts: 'Janusz'(6)→'Ja....', 'Kowalski'(8)→'Ko......'
        //   joined: 'Ja....' + '.' + 'Ko......' = 'Ja.....Ko......'
        // domain parts: 'uniwersytet'(11)→'un.........', 'edu'(3)→'ed.', 'pl'(2)→'pl'
        //   joined: 'un.........' + '.' + 'ed.' + '.' + 'pl' = 'un..........ed..pl'
        $this->assertSame(
            'Ja.....Ko......@un..........ed..pl',
            \Openvote_Vote::anonymize_email('Janusz.Kowalski@uniwersytet.edu.pl')
        );
    }
}
