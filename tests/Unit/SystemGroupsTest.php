<?php

namespace Openvote\Tests\Unit;

use Openvote_System_Groups as SG;

/**
 * Czyste funkcje Openvote_System_Groups (bez bazy i bez WordPressa): grupa systemowa Wszyscy (kryteria członkostwa,
 * pole grupujące), nazwy zarezerwowane, konflikty nazw, porcjowanie ID, dopasowanie roli w zserializowanym meta,
 * polityka grup docelowych (przypisana grupa systemowa, puste cele), sprzątanie grup ról, kolejka użytkowników.
 */
class SystemGroupsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        SG::reset_state();
    }

    protected function tearDown(): void {
        SG::reset_state();
        parent::tearDown();
    }

    // ── nazwa i klucze ───────────────────────────────────────────────────────

    public function test_all_group_name_and_legacy_role_keys(): void {
        $this->assertSame( 'Wszyscy', SG::all_group_name() );
        $this->assertTrue( SG::is_legacy_role_key( 'role:editor' ) );
        $this->assertFalse( SG::is_legacy_role_key( 'all' ) );
    }

    public function test_is_reserved_name(): void {
        $this->assertTrue( SG::is_reserved_name( 'system.Wszyscy' ) );
        $this->assertTrue( SG::is_reserved_name( 'SYSTEM.cokolwiek' ) );
        $this->assertTrue( SG::is_reserved_name( '  System.x' ) );
        $this->assertFalse( SG::is_reserved_name( 'systemx' ) );
        $this->assertFalse( SG::is_reserved_name( 'Mój system.' ) );
        $this->assertFalse( SG::is_reserved_name( 'Administratorzy' ) );
        // Nazwa grupy systemowej „Wszyscy” — bez względu na wielkość liter i otaczające spacje.
        $this->assertTrue( SG::is_reserved_name( 'Wszyscy' ) );
        $this->assertTrue( SG::is_reserved_name( 'wszyscy' ) );
        $this->assertTrue( SG::is_reserved_name( '  WSZYSCY ' ) );
        $this->assertFalse( SG::is_reserved_name( 'Wszyscy (ręczna)' ) );
        $this->assertFalse( SG::is_reserved_name( 'Wszyscy członkowie' ) );
        $this->assertFalse( SG::is_reserved_name( 'Prawie wszyscy' ) );
    }

    public function test_resolve_conflict_name(): void {
        $this->assertSame( 'Wszyscy (ręczna)', SG::resolve_conflict_name( 'Wszyscy', [ 'Wszyscy' ] ) );
        $this->assertSame( 'wszyscy (ręczna 2)', SG::resolve_conflict_name( 'wszyscy', [ 'wszyscy', 'Wszyscy (ręczna)' ] ) );
        $this->assertSame( 'system.Edytorzy (ręczna)', SG::resolve_conflict_name( 'system.Edytorzy', [ 'system.Edytorzy' ] ) );
        $this->assertSame(
            'system.Edytorzy (ręczna 3)',
            SG::resolve_conflict_name( 'system.Edytorzy', [ 'system.Edytorzy', 'SYSTEM.edytorzy (RĘCZNA)', 'system.Edytorzy (ręczna 2)' ] )
        );
    }

    // ── porcjowanie ID ───────────────────────────────────────────────────────

    public function test_chunk_ranges(): void {
        $this->assertSame( [], SG::chunk_ranges( 0, 2000 ) );
        $this->assertSame( [ [ 1, 1 ] ], SG::chunk_ranges( 1, 2000 ) );
        $this->assertSame( [ [ 1, 2 ], [ 3, 4 ], [ 5, 5 ] ], SG::chunk_ranges( 5, 2 ) );
        $this->assertSame( [ [ 1, 2000 ], [ 2001, 4000 ] ], SG::chunk_ranges( 4000, 2000 ) );
        $this->assertSame( [ [ 1, 1 ], [ 2, 2 ] ], SG::chunk_ranges( 2, 0 ) ); // rozmiar 0 → 1
    }

    // ── dopasowanie roli w zserializowanym meta ─────────────────────────────

    public function test_role_needle_does_not_match_prefixed_or_suffixed_roles(): void {
        $needle = SG::role_meta_needle( 'author' );
        $this->assertSame( 's:6:"author";', $needle );
        $this->assertFalse( str_contains( serialize( [ 'co-author' => true ] ), $needle ) );
        $this->assertFalse( str_contains( serialize( [ 'author_plus' => true ] ), $needle ) );
        $this->assertTrue( str_contains( serialize( [ 'co-author' => true, 'author' => true ] ), $needle ) );
        $this->assertTrue( str_contains( serialize( [ 'editor' => true, 'author' => true ] ), $needle ) );
    }

    public function test_roles_from_caps_only_existing_role_keys(): void {
        $existing = [ 'administrator', 'editor', 'author', 'subscriber' ];
        $this->assertSame( [ 'author' ], SG::roles_from_caps( serialize( [ 'author' => true, 'co-author' => true, 'read' => true ] ), $existing ) );
        $this->assertSame( [ 'editor', 'subscriber' ], SG::roles_from_caps( [ 'editor' => true, 'subscriber' => true ], $existing ) );
        $this->assertSame( [], SG::roles_from_caps( serialize( [ 'read' => true ] ), $existing ) );
        $this->assertSame( [], SG::roles_from_caps( '', $existing ) );
        $this->assertSame( [], SG::roles_from_caps( 'O:8:"stdClass":0:{}', $existing ) );
        $this->assertSame( [], SG::roles_from_caps( null, $existing ) );
    }

    // ── kryteria grupy Wszyscy ──────────────────────────────────────────────

    public function test_member_of_all_requires_role_group_value_and_no_admin(): void {
        $this->assertTrue( SG::is_member_of_all( [ 'subscriber' ], true ) );
        $this->assertTrue( SG::is_member_of_all( [ 'editor', 'author' ], true ) );
        // brak pola Grupa
        $this->assertFalse( SG::is_member_of_all( [ 'subscriber' ], false ) );
        // administrator (także z inną rolą) — obsługa, nie członek
        $this->assertFalse( SG::is_member_of_all( [ 'administrator' ], true ) );
        $this->assertFalse( SG::is_member_of_all( [ 'subscriber', 'administrator' ], true ) );
        // brak roli (konto zablokowane)
        $this->assertFalse( SG::is_member_of_all( [], true ) );
    }

    public function test_grouping_spec(): void {
        $core     = [ 'user_email', 'user_login', 'display_name', 'user_url' ];
        $disabled = [ '__openvote_no_city__', '__openvote_not_set__' ];
        $this->assertSame( [ 'meta', 'user_registration_miejsce_spotkania' ], SG::grouping_spec( 'user_registration_miejsce_spotkania', $core, $disabled ) );
        $this->assertSame( [ 'core', 'user_url' ], SG::grouping_spec( 'user_url', $core, $disabled ) );
        $this->assertNull( SG::grouping_spec( '__openvote_no_city__', $core, $disabled ) );
        $this->assertNull( SG::grouping_spec( '__openvote_not_set__', $core, $disabled ) );
        $this->assertNull( SG::grouping_spec( '  ', $core, $disabled ) );
        // kolumna spoza białej listy znaków nie trafia do SQL
        $this->assertNull( SG::grouping_spec( 'bad;col', [ 'bad;col' ], $disabled ) );
    }

    public function test_empty_targets_allowed_only_for_admin_when_restricted(): void {
        $this->assertTrue( SG::empty_targets_allowed( false, false ) );
        $this->assertTrue( SG::empty_targets_allowed( true, true ) );
        $this->assertFalse( SG::empty_targets_allowed( true, false ) );
    }

    // ── polityka grup docelowych ────────────────────────────────────────────

    public function test_split_targets_admin_can_choose_system_groups(): void {
        $r = SG::split_targets( [ 5, 9, 9 ], [], [ 9 ], true );
        $this->assertSame( [ 5, 9 ], $r['ids'] );
        $this->assertSame( [], $r['denied'] );
    }

    public function test_split_targets_restricted_denies_new_system_groups(): void {
        $r = SG::split_targets( [ 5, 9 ], [], [ 9, 10 ], false );
        $this->assertSame( [ 5 ], $r['ids'] );
        $this->assertSame( [ 9 ], $r['denied'] );
    }

    public function test_split_targets_restricted_keeps_system_groups_already_saved(): void {
        // Administrator zapisał [5, 10]; koordynator edytuje — UI nie pokazuje grupy 10, a backend ją zachowuje.
        $r = SG::split_targets( [ 5, 7 ], [ 5, 10 ], [ 9, 10 ], false );
        $this->assertSame( [ 5, 7, 10 ], $r['ids'] );
        $this->assertSame( [], $r['denied'] );
        // Ponowne przesłanie już zapisanej grupy systemowej nie jest odmową.
        $r = SG::split_targets( [ 10 ], [ 10 ], [ 10 ], false );
        $this->assertSame( [ 10 ], $r['ids'] );
        $this->assertSame( [], $r['denied'] );
    }

    public function test_split_targets_restricted_allows_assigned_system_group(): void {
        // Koordynator z przypisaną grupą systemową Wszyscy (9) może ją wybrać; nieprzypisana systemowa (10) — odmowa.
        $r = SG::split_targets( [ 5, 9 ], [], [ 9, 10 ], false, [ 5, 9 ] );
        $this->assertSame( [ 5, 9 ], $r['ids'] );
        $this->assertSame( [], $r['denied'] );
        $r = SG::split_targets( [ 10 ], [], [ 9, 10 ], false, [ 9 ] );
        $this->assertSame( [], $r['ids'] );
        $this->assertSame( [ 10 ], $r['denied'] );
    }

    // ── sprzątanie wycofanych grup ról ──────────────────────────────────────

    public function test_plan_group_removal_removes_id_when_other_group_remains(): void {
        $polls    = [ [ 'id' => 1, 'title' => 'A', 'target_groups' => '[7,3]' ], [ 'id' => 2, 'title' => 'B', 'target_groups' => '[3]' ] ];
        $messages = [ (object) [ 'id' => 4, 'title' => 'M', 'target_groups' => '[3,7,8]' ] ];
        $plan     = SG::plan_group_removal( 7, $polls, $messages, [ 11 => [ 7, 5 ], 12 => [ 5 ] ] );
        $this->assertSame( [], $plan['only'] );
        $this->assertSame( [ 1 => [ 3 ] ], $plan['poll_updates'] );
        $this->assertSame( [ 4 => [ 3, 8 ] ], $plan['message_updates'] );
        $this->assertSame( [ 11 => [ 5 ] ], $plan['meta_updates'] );
    }

    public function test_plan_group_removal_blocks_when_group_is_the_only_target(): void {
        $polls = [ [ 'id' => 1, 'title' => 'A', 'target_groups' => '[7,3]' ], [ 'id' => 2, 'title' => 'Jedyna', 'target_groups' => '[7]' ] ];
        $plan  = SG::plan_group_removal( 7, $polls, [], [] );
        $this->assertCount( 1, $plan['only'] );
        $this->assertStringContainsString( '#2', $plan['only'][0] );
        // Żadnych zmian — nigdy puste target_groups.
        $this->assertSame( [], $plan['poll_updates'] );
        $plan = SG::plan_group_removal( 7, [], [], [ 11 => [ 7 ] ] );
        $this->assertSame( [ 'koordynator #11' ], $plan['only'] );
        $this->assertSame( [], $plan['meta_updates'] );
    }

    public function test_plan_group_removal_ignores_invalid_json_and_other_groups(): void {
        $plan = SG::plan_group_removal( 7, [ [ 'id' => 1, 'title' => 'X', 'target_groups' => '' ], [ 'id' => 2, 'title' => 'Y', 'target_groups' => 'oops' ] ], [], [] );
        $this->assertSame( [ 'only' => [], 'poll_updates' => [], 'message_updates' => [], 'meta_updates' => [] ], $plan );
    }

    // ── kolejka ──────────────────────────────────────────────────────────────

    public function test_queue_has_no_duplicates_and_ignores_invalid_ids(): void {
        SG::queue_user( 3 );
        SG::queue_user( '3' );
        SG::queue_user( 7 );
        SG::queue_user( 0 );
        SG::queue_user( -1 );
        SG::queue_user( 3 );
        $this->assertSame( [ 3, 7 ], SG::get_queue() );
        $this->assertNotFalse( has_action( 'shutdown', [ SG::class, 'flush_queue' ] ) );
    }
}
