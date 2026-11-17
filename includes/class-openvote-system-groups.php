<?php
defined( 'ABSPATH' ) || exit;

/**
 * Grupa systemowa „Wszyscy” (system_key = 'all'; do 1.0.37 nazwa „system.Wszyscy”) — tworzona i utrzymywana automatycznie.
 *
 * Kolumna system_key w wp_openvote_groups (typ 'system'); jedyna obsługiwana wartość: 'all'.
 * „Wszyscy” (systemowa) = członkowie stowarzyszenia:
 *   (a) niepuste pole grupujące — pole logiczne 'city' = „Grupa” (miasto, sejmik, dzielnica, partia…; dowolny klucz
 *       wp_users lub usermeta wg Openvote_Field_Map); warunek odpada w trybie „Nie używaj miast” / gdy pole niezmapowane,
 *   (b) brak roli administrator (administratorzy to obsługa strony),
 *   (c) jakakolwiek rola na tej stronie (bezpiecznik dla kont zablokowanych przez odebranie roli).
 * Kryterium jest pole grupujące, nie rola WP — członek może mieć dowolną rolę (subskrybent, edytor…).
 *
 * Zasady:
 *  - członkostwa nie edytuje się ręcznie (UI i REST blokują),
 *  - grupy systemowej nigdy nie usuwamy automatycznie (puste target_groups = „głosować mogą wszyscy”),
 *  - uzgadnianie pełne (reconcile) idzie partiami po zakresach ID użytkowników, z kursorem i blokadą;
 *    zmiany pojedynczych użytkowników (role, pole grupujące) są kolejkowane i wykonywane raz, na 'shutdown',
 *  - wycofane grupy ról (system_key 'role:*', wersja rozwojowa 4.9.0) są jednorazowo sprzątane
 *    (cleanup_legacy_role_groups) — bez zerowania target_groups.
 *
 * Multisite: obsługiwana jest tylko bieżąca strona (tabele z $wpdb->prefix, meta {blog_prefix}capabilities).
 */
class Openvote_System_Groups {

	const PREFIX             = 'system.';
	const KEY_ALL            = 'all';
	/** Prefiks kluczy wycofanych grup ról (tylko do sprzątania). */
	const LEGACY_ROLE_PREFIX = 'role:';
	const SCHEMA_VERSION     = '4.9.0';

	const OPT_CURSOR         = 'openvote_system_groups_cursor';
	const OPT_LEGACY_CLEANUP = 'openvote_system_groups_roles_cleanup_done';
	const LOCK_NAME          = 'openvote_system_groups_lock';
	const LOCK_TTL           = 120;   // sekund — po tym czasie blokada uznawana za porzuconą.
	const BUDGET             = 10;    // sekund pracy uzgadniania w jednym wywołaniu.
	const CHUNK              = 2000;  // zakres ID użytkowników w jednej partii.

	const ALL_NAME = 'Wszyscy';

	/** Rola wykluczona z Wszyscy (konta obsługi strony). */
	const EXCLUDED_ROLE = 'administrator';

	/** @var array<int,true> Kolejka użytkowników do synchronizacji na 'shutdown'. */
	private static array $queue = [];
	private static bool $shutdown_hooked = false;
	private static ?bool $schema_ready   = null;
	/** @var array<int,string>|null Cache: id grupy → system_key. */
	private static ?array $map_cache = null;
	private static bool $ensured = false;
	/** @var array{0:string,1:string}|null|false Cache specyfikacji pola grupującego (false = nieobliczone). */
	private static array|null|false $grouping_cache = false;
	private static bool $reconcile_on_shutdown = false;

	// ─── Rejestracja hooków ─────────────────────────────────────────────────

	public static function register(): void {
		// Role (w tym nadanie / odebranie administratora) i pola wp_users (wp_update_user → profile_update).
		add_action( 'user_register', [ __CLASS__, 'queue_user' ], 20, 1 );
		add_action( 'profile_update', [ __CLASS__, 'queue_user' ], 20, 1 );
		add_action( 'set_user_role', [ __CLASS__, 'queue_user' ], 20, 1 );
		add_action( 'add_user_role', [ __CLASS__, 'queue_user' ], 20, 1 );
		add_action( 'remove_user_role', [ __CLASS__, 'queue_user' ], 20, 1 );
		add_action( 'add_user_to_blog', [ __CLASS__, 'queue_user' ], 20, 1 );
		add_action( 'remove_user_from_blog', [ __CLASS__, 'queue_user' ], 20, 1 );
		add_action( 'deleted_user', [ __CLASS__, 'on_user_deleted' ], 20, 1 );
		add_action( 'wpmu_delete_user', [ __CLASS__, 'on_user_deleted' ], 20, 1 );
		// Pole grupujące w usermeta oraz capabilities zapisane bezpośrednio w meta.
		add_action( 'added_user_meta', [ __CLASS__, 'on_user_meta_changed' ], 20, 3 );
		add_action( 'updated_user_meta', [ __CLASS__, 'on_user_meta_changed' ], 20, 3 );
		add_action( 'deleted_user_meta', [ __CLASS__, 'on_user_meta_changed' ], 20, 3 );
		// Zmiana mapowania pola „Grupa” w Konfiguracji → pełne ponowne uzgodnienie.
		add_action( 'add_option_openvote_field_map', [ __CLASS__, 'on_field_map_added' ], 10, 2 );
		add_action( 'update_option_openvote_field_map', [ __CLASS__, 'on_field_map_updated' ], 10, 2 );
	}

	// ─── Czyste funkcje (testowane jednostkowo) ─────────────────────────────

	/** Nazwa grupy systemowej „Wszyscy” (system_key 'all'; do 1.0.37: „system.Wszyscy”). Grupę identyfikuje system_key, nie nazwa. */
	public static function all_group_name(): string {
		return self::ALL_NAME;
	}

	/** Czy klucz należy do wycofanej grupy roli ('role:<slug>'). */
	public static function is_legacy_role_key( string $key ): bool {
		return str_starts_with( $key, self::LEGACY_ROLE_PREFIX );
	}

	/**
	 * Czy nazwa jest zarezerwowana dla grup systemowych: prefiks „system.” albo nazwa grupy systemowej „Wszyscy”
	 * (bez względu na wielkość liter i otaczające spacje).
	 */
	public static function is_reserved_name( string $name ): bool {
		$name = trim( $name );
		return 0 === stripos( $name, self::PREFIX ) || 0 === strcasecmp( self::fold( $name ), self::fold( self::ALL_NAME ) );
	}

	/** Małe litery (UTF-8, gdy dostępne mbstring) — do porównań nazw bez względu na wielkość liter. */
	private static function fold( string $s ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/**
	 * Nowa nazwa dla ręcznej grupy kolidującej z nazwą systemową: „X (ręczna)”, „X (ręczna 2)”, …
	 *
	 * @param string[] $taken Zajęte nazwy (porównanie bez względu na wielkość liter).
	 */
	public static function resolve_conflict_name( string $name, array $taken ): string {
		$taken = array_flip( array_map( 'mb_strtolower', $taken ) );
		$base  = mb_substr( $name, 0, 230 );
		for ( $i = 1; $i < 1000; $i++ ) {
			$cand = $base . ( 1 === $i ? ' (ręczna)' : ' (ręczna ' . $i . ')' );
			if ( ! isset( $taken[ mb_strtolower( $cand ) ] ) ) {
				return $cand;
			}
		}
		return $base . ' (ręczna ' . uniqid() . ')';
	}

	/**
	 * Zakresy ID [od, do] (włącznie) pokrywające 1..max_id partiami po $size.
	 *
	 * @return array<int,array{0:int,1:int}>
	 */
	public static function chunk_ranges( int $max_id, int $size ): array {
		$size = max( 1, $size );
		$out  = [];
		for ( $from = 1; $from <= $max_id; $from += $size ) {
			$out[] = [ $from, min( $max_id, $from + $size - 1 ) ];
		}
		return $out;
	}

	/** Fragment zserializowanego meta capabilities, który oznacza obecność klucza roli (z długością — „author” ≠ „co-author”). */
	public static function role_meta_needle( string $slug ): string {
		return 's:' . strlen( $slug ) . ':"' . $slug . '";';
	}

	/**
	 * Role użytkownika z wartości meta {prefix}capabilities (tablica lub zserializowany string),
	 * ograniczone do ról istniejących — tak jak WP_User::get_role_caps() (liczą się klucze).
	 *
	 * @param mixed    $caps
	 * @param string[] $existing_roles
	 * @return string[]
	 */
	public static function roles_from_caps( mixed $caps, array $existing_roles ): array {
		if ( is_string( $caps ) ) {
			$caps = @unserialize( $caps, [ 'allowed_classes' => false ] ); // phpcs:ignore
		}
		if ( ! is_array( $caps ) ) {
			return [];
		}
		$existing = array_flip( $existing_roles );
		$out      = [];
		foreach ( array_keys( $caps ) as $k ) {
			if ( is_string( $k ) && isset( $existing[ $k ] ) ) {
				$out[] = $k;
			}
		}
		return $out;
	}

	/**
	 * Czy użytkownik należy do Wszyscy: ma jakąkolwiek rolę, nie jest administratorem
	 * i ma niepuste pole grupujące (gdy pole jest wyłączone — przekazujemy $has_group_value = true).
	 *
	 * @param string[] $user_roles Role istniejące na stronie.
	 */
	public static function is_member_of_all( array $user_roles, bool $has_group_value ): bool {
		if ( empty( $user_roles ) || ! $has_group_value ) {
			return false;
		}
		return ! in_array( self::EXCLUDED_ROLE, array_map( 'strval', $user_roles ), true );
	}

	/**
	 * Specyfikacja pola grupującego: [ 'core', kolumna wp_users ] | [ 'meta', meta_key ] | null (warunek (a) odpada).
	 *
	 * @param string   $actual_key      Klucz z mapowania pola 'city'.
	 * @param string[] $core_fields     Dozwolone kolumny wp_users.
	 * @param string[] $disabled_values Wartości oznaczające „wyłączone / niezmapowane”.
	 * @return array{0:string,1:string}|null
	 */
	public static function grouping_spec( string $actual_key, array $core_fields, array $disabled_values ): ?array {
		$actual_key = trim( $actual_key );
		if ( '' === $actual_key || in_array( $actual_key, $disabled_values, true ) ) {
			return null;
		}
		if ( in_array( $actual_key, $core_fields, true ) ) {
			// Kolumna trafia do SQL — tylko znane, bezpieczne nazwy.
			return preg_match( '/^[a-z_]+$/', $actual_key ) ? [ 'core', $actual_key ] : null;
		}
		return [ 'meta', $actual_key ];
	}

	/**
	 * Czy można zapisać / wysłać obiekt BEZ grup docelowych (głosowanie bez grup = „głosować mogą wszyscy”).
	 * Bez ograniczenia koordynatorów — każdy uprawniony; z ograniczeniem („own”) — tylko manage_options.
	 */
	public static function empty_targets_allowed( bool $restricted_scope, bool $is_site_admin ): bool {
		return ! $restricted_scope || $is_site_admin;
	}

	/**
	 * Polityka wyboru grup docelowych (opcja „koordynatorzy tylko własne grupy”).
	 * Gdy użytkownik nie może wskazywać grup systemowych: nowe grupy systemowe są odrzucane ($denied) — chyba że
	 * są mu przypisane ($own_ids); już zapisane w obiekcie grupy systemowe (wybrane przez administratora) są zachowywane.
	 *
	 * @param int[] $requested  ID z formularza.
	 * @param int[] $existing   ID zapisane dotąd w obiekcie (edycja) — [] dla nowego.
	 * @param int[] $system_ids ID wszystkich grup systemowych.
	 * @param int[] $own_ids    ID grup przypisanych użytkownikowi (koordynatorowi).
	 * @return array{ids:int[], denied:int[]}
	 */
	public static function split_targets( array $requested, array $existing, array $system_ids, bool $can_target_system, array $own_ids = [] ): array {
		$requested = array_values( array_unique( array_filter( array_map( 'intval', $requested ) ) ) );
		if ( $can_target_system ) {
			return [ 'ids' => $requested, 'denied' => [] ];
		}
		$system = array_flip( array_map( 'intval', $system_ids ) );
		foreach ( array_map( 'intval', $own_ids ) as $own ) {
			unset( $system[ $own ] ); // przypisana koordynatorowi — traktowana jak jego zwykła grupa.
		}
		$existing = array_map( 'intval', $existing );
		$denied   = [];
		$ids      = [];
		foreach ( $requested as $id ) {
			if ( isset( $system[ $id ] ) ) {
				if ( ! in_array( $id, $existing, true ) ) {
					$denied[] = $id;
				}
				continue;
			}
			$ids[] = $id;
		}
		foreach ( $existing as $id ) {
			if ( isset( $system[ $id ] ) && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return [ 'ids' => $ids, 'denied' => $denied ];
	}

	/**
	 * Plan bezpiecznego usunięcia wycofanej grupy (nigdy nie prowadzi do pustych target_groups / openvote_groups).
	 * Jeśli grupa jest JEDYNĄ grupą w jakimkolwiek głosowaniu, wiadomości lub przypisaniu koordynatora — 'only' niepuste,
	 * grupy nie wolno usuwać i plan nie zawiera żadnych zmian. W przeciwnym razie ID jest usuwane z odwołań.
	 *
	 * @param object[]|array[] $polls        Wiersze: id, title, target_groups (JSON).
	 * @param object[]|array[] $messages     Wiersze: id, title, target_groups (JSON).
	 * @param array<int,int[]> $coordinators user_id → ID grup z openvote_groups.
	 * @return array{only:string[], poll_updates:array<int,int[]>, message_updates:array<int,int[]>, meta_updates:array<int,int[]>}
	 */
	public static function plan_group_removal( int $group_id, array $polls, array $messages, array $coordinators ): array {
		$out    = [ 'only' => [], 'poll_updates' => [], 'message_updates' => [], 'meta_updates' => [] ];
		$ids_of = static function ( $tg ): array {
			$d = is_string( $tg ) && '' !== $tg ? json_decode( $tg, true ) : ( is_array( $tg ) ? $tg : [] );
			return is_array( $d ) ? array_values( array_map( 'intval', $d ) ) : [];
		};
		$walk = static function ( array $rows, string $label, string $bucket ) use ( $group_id, $ids_of, &$out ): void {
			foreach ( $rows as $row ) {
				$row = (object) $row;
				$ids = $ids_of( $row->target_groups ?? '' );
				if ( ! in_array( $group_id, $ids, true ) ) {
					continue;
				}
				$rest = array_values( array_unique( array_filter( $ids, static fn( $id ) => $id !== $group_id ) ) );
				if ( empty( $rest ) ) {
					$out['only'][] = sprintf( '%s „%s” (#%d)', $label, (string) ( $row->title ?? '' ), (int) $row->id );
				} else {
					$out[ $bucket ][ (int) $row->id ] = $rest;
				}
			}
		};
		$walk( $polls, 'głosowanie', 'poll_updates' );
		$walk( $messages, 'wiadomość', 'message_updates' );
		foreach ( $coordinators as $uid => $ids ) {
			$ids = array_values( array_map( 'intval', (array) $ids ) );
			if ( ! in_array( $group_id, $ids, true ) ) {
				continue;
			}
			$rest = array_values( array_unique( array_filter( $ids, static fn( $id ) => $id !== $group_id ) ) );
			if ( empty( $rest ) ) {
				$out['only'][] = sprintf( 'koordynator #%d', (int) $uid );
			} else {
				$out['meta_updates'][ (int) $uid ] = $rest;
			}
		}
		if ( ! empty( $out['only'] ) ) {
			$out['poll_updates']    = [];
			$out['message_updates'] = [];
			$out['meta_updates']    = [];
		}
		return $out;
	}

	/** Dodaj użytkownika do kolejki (bez duplikatów); przetwarzanie raz na 'shutdown'. */
	public static function queue_user( $user_id ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		self::$queue[ $user_id ] = true;
		if ( ! self::$shutdown_hooked ) {
			self::$shutdown_hooked = true;
			add_action( 'shutdown', [ __CLASS__, 'flush_queue' ] );
		}
	}

	/** @return int[] */
	public static function get_queue(): array {
		return array_keys( self::$queue );
	}

	/** Tylko testy. */
	public static function reset_state(): void {
		self::$queue                 = [];
		self::$shutdown_hooked       = false;
		self::$schema_ready          = null;
		self::$map_cache             = null;
		self::$ensured               = false;
		self::$grouping_cache        = false;
		self::$reconcile_on_shutdown = false;
	}

	// ─── Schemat ────────────────────────────────────────────────────────────

	/** Czy migracja 4.9.0 (kolumna system_key) jest wykonana (wg openvote_db_version; cache na żądanie). */
	public static function schema_ready(): bool {
		if ( null === self::$schema_ready ) {
			self::$schema_ready = version_compare( (string) get_option( 'openvote_db_version', '1.0.0' ), self::SCHEMA_VERSION, '>=' );
		}
		return self::$schema_ready;
	}

	/** Fizyczne sprawdzenie kolumny system_key (heartbeat / cron CLI). */
	public static function column_exists(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'openvote_groups';
		$col   = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'system_key' ) );
		return 'system_key' === $col;
	}

	/** Po migracji / wyczyszczeniu danych: unieważnij cache. */
	public static function flush_cache(): void {
		self::$schema_ready   = null;
		self::$map_cache      = null;
		self::$ensured        = false;
		self::$grouping_cache = false;
	}

	/** Wołane przez migrację po dodaniu kolumny (opcja wersji jest zapisywana dopiero po wszystkich migracjach). */
	public static function mark_schema_ready(): void {
		self::$schema_ready = true;
		self::$map_cache    = null;
		self::$ensured      = false;
	}

	// ─── Odczyt ─────────────────────────────────────────────────────────────

	/**
	 * Mapa id → system_key grup systemowych (pusta przed migracją). Zawiera też niesprzątnięte grupy 'role:*'
	 * (zablokowane do edycji, nieuzgadniane).
	 *
	 * @return array<int,string>
	 */
	public static function get_map(): array {
		if ( null !== self::$map_cache ) {
			return self::$map_cache;
		}
		if ( ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$table = $wpdb->prefix . 'openvote_groups';
		$rows  = $wpdb->get_results( "SELECT id, system_key FROM {$table} WHERE system_key IS NOT NULL" );
		$map   = [];
		foreach ( (array) $rows as $r ) {
			$map[ (int) $r->id ] = (string) $r->system_key;
		}
		self::$map_cache = $map;
		return $map;
	}

	/** @return int[] */
	public static function get_system_group_ids(): array {
		return array_keys( self::get_map() );
	}

	public static function is_system_group( int $group_id ): bool {
		return $group_id > 0 && isset( self::get_map()[ $group_id ] );
	}

	/** ID grupy Wszyscy (0 gdy brak). */
	public static function get_all_group_id(): int {
		$id = array_search( self::KEY_ALL, self::get_map(), true );
		return false === $id ? 0 : (int) $id;
	}

	/** Czy bieżący użytkownik może wskazywać DOWOLNE grupy systemowe jako docelowe (bez ograniczenia lub manage_options). */
	public static function current_user_can_target(): bool {
		if ( function_exists( 'openvote_current_user_can_target_system_groups' ) ) {
			return openvote_current_user_can_target_system_groups();
		}
		return true;
	}

	/** ID grup przypisanych bieżącemu użytkownikowi jako koordynatorowi (user meta openvote_groups). */
	public static function current_user_own_group_ids(): array {
		if ( ! class_exists( 'Openvote_Role_Manager' ) || ! function_exists( 'get_current_user_id' ) ) {
			return [];
		}
		return array_map( 'intval', Openvote_Role_Manager::get_user_groups( get_current_user_id() ) );
	}

	/**
	 * Grupy systemowe z listy, których bieżący użytkownik NIE może wskazać (np. przy starcie wysyłki):
	 * przy ograniczeniu koordynatorów — systemowe nieprzypisane w openvote_groups.
	 *
	 * @return int[]
	 */
	public static function denied_system_targets( array $group_ids ): array {
		if ( self::current_user_can_target() ) {
			return [];
		}
		$own = self::current_user_own_group_ids();
		$out = [];
		foreach ( array_map( 'intval', $group_ids ) as $gid ) {
			if ( self::is_system_group( $gid ) && ! in_array( $gid, $own, true ) ) {
				$out[] = $gid;
			}
		}
		return $out;
	}

	/**
	 * Polityka grup docelowych dla bieżącego użytkownika.
	 *
	 * @return array{ids:int[], denied:int[]}
	 */
	public static function apply_target_policy( array $requested, array $existing = [] ): array {
		$can = self::current_user_can_target();
		return self::split_targets( $requested, $existing, self::get_system_group_ids(), $can, $can ? [] : self::current_user_own_group_ids() );
	}

	/** Odfiltruj wiersze grup (obiekty z ->id) niedozwolone dla bieżącego użytkownika jako cele (przypisane systemowe zostają). */
	public static function filter_selectable_rows( array $rows ): array {
		if ( self::current_user_can_target() ) {
			return $rows;
		}
		$own = array_flip( self::current_user_own_group_ids() );
		return array_values( array_filter( $rows, static fn( $g ) => ! self::is_system_group( (int) $g->id ) || isset( $own[ (int) $g->id ] ) ) );
	}

	// ─── Pole grupujące / kryteria członkostwa ──────────────────────────────

	/**
	 * Specyfikacja pola grupującego bieżącej konfiguracji (cache na żądanie).
	 *
	 * @return array{0:string,1:string}|null
	 */
	public static function grouping(): ?array {
		if ( false !== self::$grouping_cache ) {
			return self::$grouping_cache;
		}
		if ( ! class_exists( 'Openvote_Field_Map' ) || ! defined( 'Openvote_Field_Map::NO_CITY_KEY' ) ) {
			self::$grouping_cache = null;
			return null;
		}
		self::$grouping_cache = self::grouping_spec(
			Openvote_Field_Map::get_field( 'city' ),
			Openvote_Field_Map::CORE_USER_FIELDS,
			[ Openvote_Field_Map::NO_CITY_KEY, Openvote_Field_Map::NOT_SET_KEY ]
		);
		return self::$grouping_cache;
	}

	/** Czy użytkownik ma niepuste pole grupujące (true, gdy pole jest wyłączone — warunek odpada). */
	public static function user_has_group_value( int $user_id ): bool {
		$spec = self::grouping();
		if ( null === $spec ) {
			return true;
		}
		if ( $user_id <= 0 ) {
			return false;
		}
		global $wpdb;
		if ( 'core' === $spec[0] ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- kolumna z białej listy (grouping_spec)
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->users} WHERE ID = %d AND TRIM({$spec[1]}) <> '' LIMIT 1", $user_id ) );
		}
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s AND TRIM(meta_value) <> '' LIMIT 1",
			$user_id,
			$spec[1]
		) );
	}

	/** Role (istniejące) użytkownika na bieżącej stronie. */
	public static function user_roles( int $user_id ): array {
		global $wpdb;
		if ( $user_id <= 0 || ! function_exists( 'wp_roles' ) ) {
			return [];
		}
		$caps = get_user_meta( $user_id, $wpdb->get_blog_prefix() . 'capabilities', true );
		return self::roles_from_caps( $caps, array_keys( wp_roles()->get_names() ) );
	}

	/** Czy użytkownik (wg bieżących ról i pola grupującego) należy do Wszyscy. */
	public static function user_is_member_of_all( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$roles = self::user_roles( $user_id );
		return ! empty( $roles ) && self::is_member_of_all( $roles, self::user_has_group_value( $user_id ) );
	}

	/**
	 * Prawo głosu wg grup docelowych (niepusta lista): Wszyscy sprawdzana NA ŻYWO (rola, brak administratora,
	 * pole grupujące — zapisane członkostwo nie ma znaczenia, prawo głosu nie czeka na uzgodnienie), pozostałe grupy
	 * (ręczne, miast, niesprzątnięte 'role:*') — wg wp_openvote_group_members. Nieistniejące ID nie dają prawa głosu.
	 * Przed migracją 4.9.0: zgodność wstecz z dawną grupą „Wszyscy” (tryb bez miast, jedyna grupa docelowa).
	 */
	public static function user_in_groups( int $user_id, array $group_ids ): bool {
		$group_ids = array_values( array_unique( array_filter( array_map( 'intval', $group_ids ) ) ) );
		if ( $user_id <= 0 || empty( $group_ids ) ) {
			return false;
		}
		$stored = $group_ids;
		if ( ! self::schema_ready() ) {
			if ( self::legacy_wszyscy_match( $group_ids ) ) {
				return true;
			}
		} else {
			$all_id = self::get_all_group_id();
			if ( $all_id && in_array( $all_id, $group_ids, true ) ) {
				if ( self::user_is_member_of_all( $user_id ) ) {
					return true;
				}
				$stored = array_values( array_diff( $group_ids, [ $all_id ] ) );
			}
		}
		if ( empty( $stored ) ) {
			return false;
		}
		global $wpdb;
		$gm           = $wpdb->prefix . 'openvote_group_members';
		$placeholders = implode( ',', array_fill( 0, count( $stored ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$gm} WHERE user_id = %d AND group_id IN ({$placeholders})",
			array_merge( [ $user_id ], $stored )
		) );
	}

	private static function legacy_wszyscy_match( array $group_ids ): bool {
		if ( ! class_exists( 'Openvote_Field_Map' ) || ! Openvote_Field_Map::is_city_disabled() || count( $group_ids ) !== 1 ) {
			return false;
		}
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}openvote_groups WHERE name = %s",
			self::ALL_NAME
		) );
		return $id > 0 && $group_ids[0] === $id;
	}

	// ─── Tworzenie grupy ────────────────────────────────────────────────────

	/**
	 * Utwórz grupę systemową „Wszyscy”, jeśli brak (po system_key). Istniejącej grupie o innej nazwie (np. „system.Wszyscy”
	 * sprzed 1.0.38) idempotentnie ustawia nazwę — jedno UPDATE … WHERE system_key = 'all', tylko gdy nazwa się różni.
	 * Ręczna grupa (system_key IS NULL) o kolidującej nazwie dostaje „ (ręczna)” — bez usuwania i bez przejmowania członków.
	 *
	 * @return int Liczba utworzonych grup (0/1).
	 */
	public static function ensure_groups( bool $force = false ): int {
		if ( ( self::$ensured && ! $force ) || ! self::schema_ready() ) {
			return 0;
		}
		self::$ensured = true;
		global $wpdb;
		$table = $wpdb->prefix . 'openvote_groups';
		$name  = self::all_group_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM {$table} WHERE system_key = %s", self::KEY_ALL ) );
		if ( $row ) {
			if ( (string) $row->name !== $name ) {
				self::rename_all_group( (string) $row->name, $name );
			}
			return 0;
		}
		self::free_name_for_system( $name );
		$ok = $wpdb->insert(
			$table,
			[ 'name' => $name, 'type' => 'system', 'description' => null, 'member_count' => 0, 'is_test_group' => 0, 'system_key' => self::KEY_ALL ],
			[ '%s', '%s', '%s', '%d', '%d', '%s' ]
		);
		self::$map_cache = null;
		if ( ! $ok ) {
			return 0;
		}
		if ( function_exists( 'openvote_groups_audit_log_append' ) ) {
			/* translators: %s: group name */
			openvote_groups_audit_log_append( 0, sprintf( __( 'utworzono grupę systemową %s', 'openvote' ), $name ) );
		}
		return 1;
	}

	/** Zmiana nazwy grupy systemowej (system_key 'all') na $name — wpis w dzienniku tylko, gdy UPDATE coś zmienił. */
	private static function rename_all_group( string $old, string $name ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'openvote_groups';
		self::free_name_for_system( $name );
		// BINARY: zmiana także samej wielkości liter; warunek name <> … czyni UPDATE idempotentnym.
		$changed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET name = %s WHERE system_key = %s AND BINARY name <> %s",
			$name,
			self::KEY_ALL,
			$name
		) );
		if ( $changed && function_exists( 'openvote_groups_audit_log_append' ) ) {
			/* translators: 1: old group name, 2: new group name */
			openvote_groups_audit_log_append( 0, sprintf( __( 'zmieniono nazwę grupy systemowej %1$s na %2$s', 'openvote' ), $old, $name ) );
		}
	}

	/**
	 * Zmień nazwę ręcznych grup (system_key IS NULL), które zajmują nazwę potrzebną grupie systemowej
	 * (porównanie bez względu na wielkość liter i otaczające spacje). Grupa i jej członkowie zostają.
	 */
	private static function free_name_for_system( string $name ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'openvote_groups';
		$rows  = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT id, name FROM {$table} WHERE LOWER(TRIM(name)) = LOWER(%s) AND system_key IS NULL",
			trim( $name )
		) );
		foreach ( $rows as $row ) {
			$like  = $wpdb->esc_like( (string) $row->name ) . '%';
			$taken = (array) $wpdb->get_col( $wpdb->prepare( "SELECT name FROM {$table} WHERE name LIKE %s", $like ) );
			$new   = self::resolve_conflict_name( (string) $row->name, $taken );
			$wpdb->update( $table, [ 'name' => $new ], [ 'id' => (int) $row->id ], [ '%s' ], [ '%d' ] );
			if ( function_exists( 'openvote_groups_audit_log_append' ) ) {
				/* translators: 1: old group name, 2: new group name */
				openvote_groups_audit_log_append( 0, sprintf( __( 'zmieniono nazwę ręcznej grupy %1$s na %2$s (nazwa zarezerwowana dla grupy systemowej)', 'openvote' ), $row->name, $new ) );
			}
		}
	}

	// ─── Sprzątanie wycofanych grup ról ─────────────────────────────────────

	/**
	 * Jednorazowe sprzątanie grup 'role:*' (wersja rozwojowa 4.9.0). Bezpiecznie: grupa, która jest jedyną grupą
	 * w jakimkolwiek głosowaniu, wiadomości lub przypisaniu koordynatora, NIE jest usuwana (zgłoszenie w dzienniku
	 * i w wyniku); w pozostałych przypadkach ID jest usuwane z odwołań (zostaje inna grupa), potem członkostwa i grupa.
	 * Bez remove_group_from_all_polls().
	 *
	 * @return array{deleted:string[], skipped:array<string,string[]>}
	 */
	public static function cleanup_legacy_role_groups( bool $force = false ): array {
		$report = [ 'deleted' => [], 'skipped' => [] ];
		if ( ! self::schema_ready() || ( ! $force && false !== get_option( self::OPT_LEGACY_CLEANUP, false ) ) ) {
			return $report;
		}
		global $wpdb;
		$groups_table = $wpdb->prefix . 'openvote_groups';
		$rows         = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT id, name FROM {$groups_table} WHERE system_key LIKE %s ORDER BY id",
			$wpdb->esc_like( self::LEGACY_ROLE_PREFIX ) . '%'
		) );
		if ( ! empty( $rows ) ) {
			$polls_table = $wpdb->prefix . 'openvote_polls';
			$msg_table   = $wpdb->prefix . 'openvote_messages';
			foreach ( $rows as $g ) {
				$gid      = (int) $g->id;
				$polls    = (array) $wpdb->get_results( "SELECT id, title, target_groups FROM {$polls_table} WHERE target_groups IS NOT NULL AND target_groups <> ''" );
				$messages = (array) $wpdb->get_results( "SELECT id, title, target_groups FROM {$msg_table} WHERE target_groups IS NOT NULL AND target_groups <> ''" );
				$coords   = [];
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", 'openvote_groups' ) ) as $m ) {
					$raw = maybe_unserialize( $m->meta_value );
					$ids = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
					if ( is_array( $ids ) ) {
						$coords[ (int) $m->user_id ] = array_map( 'intval', $ids );
					}
				}
				$plan = self::plan_group_removal( $gid, $polls, $messages, $coords );
				if ( ! empty( $plan['only'] ) ) {
					$report['skipped'][ (string) $g->name ] = $plan['only'];
					continue;
				}
				foreach ( $plan['poll_updates'] as $pid => $ids ) {
					$wpdb->update( $polls_table, [ 'target_groups' => wp_json_encode( $ids ) ], [ 'id' => (int) $pid ], [ '%s' ], [ '%d' ] );
				}
				foreach ( $plan['message_updates'] as $mid => $ids ) {
					$wpdb->update( $msg_table, [ 'target_groups' => wp_json_encode( $ids ) ], [ 'id' => (int) $mid ], [ '%s' ], [ '%d' ] );
				}
				foreach ( $plan['meta_updates'] as $uid => $ids ) {
					update_user_meta( (int) $uid, 'openvote_groups', wp_json_encode( $ids ) );
				}
				$wpdb->delete( $wpdb->prefix . 'openvote_group_members', [ 'group_id' => $gid ], [ '%d' ] );
				$wpdb->delete( $groups_table, [ 'id' => $gid ], [ '%d' ] );
				$report['deleted'][] = (string) $g->name;
			}
			if ( function_exists( 'openvote_groups_audit_log_append' ) ) {
				if ( ! empty( $report['deleted'] ) ) {
					/* translators: %s: comma-separated group names */
					openvote_groups_audit_log_append( 0, sprintf( __( 'usunięto wycofane grupy ról: %s', 'openvote' ), implode( ', ', $report['deleted'] ) ) );
				}
				foreach ( $report['skipped'] as $name => $refs ) {
					/* translators: 1: group name, 2: list of references */
					openvote_groups_audit_log_append( 0, sprintf( __( 'nie usunięto wycofanej grupy %1$s — jest jedyną grupą w: %2$s', 'openvote' ), $name, implode( '; ', $refs ) ) );
				}
			}
		}
		self::$map_cache = null;
		update_option( self::OPT_LEGACY_CLEANUP, $report, false );
		return $report;
	}

	// ─── Uzgadnianie pełne ──────────────────────────────────────────────────

	/**
	 * Warunek SQL członkostwa w Wszyscy dla wiersza capabilities ($caps_col) użytkownika $uid_col
	 * (alias wp_users: $users_alias): jakakolwiek istniejąca rola, bez administratora, niepuste pole grupujące.
	 * Zwraca [ sql, args ] (placeholdery %s; nazwy kolumn z białej listy).
	 *
	 * @return array{0:string,1:array}
	 */
	private static function all_condition( string $uid_col, string $caps_col, string $users_alias ): array {
		global $wpdb;
		$parts = [];
		$args  = [];
		foreach ( array_keys( wp_roles()->get_names() ) as $slug ) {
			$parts[] = "{$caps_col} LIKE %s";
			$args[]  = '%' . $wpdb->esc_like( self::role_meta_needle( (string) $slug ) ) . '%';
		}
		if ( empty( $parts ) ) {
			return [ '1=0', [] ];
		}
		$sql = '(' . implode( ' OR ', $parts ) . ')';
		if ( wp_roles()->is_role( self::EXCLUDED_ROLE ) ) {
			$sql   .= " AND {$caps_col} NOT LIKE %s";
			$args[] = '%' . $wpdb->esc_like( self::role_meta_needle( self::EXCLUDED_ROLE ) ) . '%';
		}
		$spec = self::grouping();
		if ( null !== $spec ) {
			if ( 'core' === $spec[0] ) {
				$sql .= " AND TRIM({$users_alias}.{$spec[1]}) <> ''";
			} else {
				$sql   .= " AND EXISTS ( SELECT 1 FROM {$wpdb->usermeta} gv WHERE gv.user_id = {$uid_col} AND gv.meta_key = %s AND TRIM(gv.meta_value) <> '' )";
				$args[] = $spec[1];
			}
		}
		return [ $sql, $args ];
	}

	/**
	 * Uzgodnij Wszyscy w zakresie ID użytkowników. Zwraca [ dodani, usunięci ].
	 * Parametr $key zachowany dla zgodności wywołań (obsługiwany tylko 'all').
	 *
	 * @return array{0:int,1:int}
	 */
	public static function reconcile_range( int $group_id, string $key, int $from, ?int $to ): array {
		global $wpdb;
		if ( self::KEY_ALL !== $key || ! function_exists( 'wp_roles' ) ) {
			return [ 0, 0 ];
		}
		$gm       = $wpdb->prefix . 'openvote_group_members';
		$meta_key = $wpdb->get_blog_prefix() . 'capabilities';
		$to_sql   = null === $to ? PHP_INT_MAX : $to;
		[ $cond, $cargs ] = self::all_condition( 'um.user_id', 'um.meta_value', 'u' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $cond: placeholdery %s i nazwy z białej listy
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$gm} (group_id, user_id, source, added_at)
			 SELECT %d, um.user_id, 'auto', %s
			 FROM {$wpdb->usermeta} um
			 INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
			 WHERE um.meta_key = %s AND um.user_id BETWEEN %d AND %d AND {$cond}",
			array_merge( [ $group_id, current_time( 'mysql' ), $meta_key, $from, $to_sql ], $cargs )
		) );
		$added = max( 0, (int) $wpdb->rows_affected );

		[ $cond2, $cargs2 ] = self::all_condition( 'gm.user_id', 'um.meta_value', 'u' );
		$wpdb->query( $wpdb->prepare(
			"DELETE gm FROM {$gm} gm
			 LEFT JOIN {$wpdb->users} u ON u.ID = gm.user_id
			 LEFT JOIN {$wpdb->usermeta} um ON um.user_id = gm.user_id AND um.meta_key = %s AND {$cond2}
			 WHERE gm.group_id = %d AND gm.user_id BETWEEN %d AND %d AND ( u.ID IS NULL OR um.umeta_id IS NULL )",
			array_merge( [ $meta_key ], $cargs2, [ $group_id, $from, $to_sql ] )
		) );
		// phpcs:enable
		$removed = max( 0, (int) $wpdb->rows_affected );
		return [ $added, $removed ];
	}

	/** Przelicz member_count grupy. */
	public static function recount( int $group_id ): void {
		global $wpdb;
		$gm    = $wpdb->prefix . 'openvote_group_members';
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$gm} WHERE group_id = %d", $group_id ) );
		$wpdb->update( $wpdb->prefix . 'openvote_groups', [ 'member_count' => $count ], [ 'id' => $group_id ], [ '%d' ], [ '%d' ] );
	}

	/**
	 * Uzgodnienie Wszyscy partiami do $deadline (własny podbudżet BUDGET s).
	 * Postęp w kursorze — kolejne wywołanie kontynuuje. Jeden wpis w dzienniku na zakończony przebieg, który coś zmienił.
	 *
	 * @return string Krótkie podsumowanie (puste, gdy nic do zrobienia).
	 */
	public static function reconcile( int $deadline ): string {
		if ( ! self::schema_ready() || ! function_exists( 'wp_roles' ) ) {
			return '';
		}
		$deadline = min( $deadline, time() + self::BUDGET );
		if ( ! self::acquire_lock() ) {
			return __( 'grupy systemowe: uzgadnianie trwa w innym procesie', 'openvote' );
		}
		try {
			return self::reconcile_locked( $deadline );
		} finally {
			self::release_lock();
		}
	}

	private static function reconcile_locked( int $deadline ): string {
		global $wpdb;
		self::$map_cache = null;
		$gid             = self::get_all_group_id();
		if ( ! $gid ) {
			return '';
		}
		$gm     = $wpdb->prefix . 'openvote_group_members';
		$max_id = max(
			(int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->users}" ),
			(int) $wpdb->get_var( "SELECT MAX(user_id) FROM {$gm}" )
		);

		$cur = get_option( self::OPT_CURSOR, [] );
		if ( ! is_array( $cur ) ) {
			$cur = [];
		}
		$from    = max( 1, (int) ( $cur['from'] ?? 1 ) );
		$added   = (int) ( $cur['added'] ?? 0 );
		$removed = (int) ( $cur['removed'] ?? 0 );

		$done = false;
		while ( time() < $deadline ) {
			$to   = $from + self::CHUNK - 1;
			$last = $to >= $max_id;
			[ $a, $r ] = self::reconcile_range( $gid, self::KEY_ALL, $from, $last ? null : $to );
			$added   += $a;
			$removed += $r;
			if ( $last ) {
				self::recount( $gid );
				$done = true;
				break;
			}
			$from = $to + 1;
			update_option( self::OPT_CURSOR, [ 'key' => self::KEY_ALL, 'from' => $from, 'added' => $added, 'removed' => $removed ], false );
		}

		if ( ! $done ) {
			/* translators: %d: next user ID to process */
			return sprintf( __( 'grupy systemowe: uzgadnianie grupy Wszyscy do ID %d, ciąg dalszy w kolejnym uruchomieniu', 'openvote' ), $from );
		}
		delete_option( self::OPT_CURSOR );
		if ( $added > 0 || $removed > 0 ) {
			/* translators: 1: added memberships, 2: removed memberships */
			$line = sprintf( __( 'uzgodniono grupę systemową Wszyscy: +%1$d / −%2$d', 'openvote' ), $added, $removed );
			if ( function_exists( 'openvote_groups_audit_log_append' ) ) {
				openvote_groups_audit_log_append( 0, $line );
			}
			return 'grupy systemowe: +' . $added . ' / −' . $removed;
		}
		return '';
	}

	private static function acquire_lock(): bool {
		global $wpdb;
		$now = time();
		$ins = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
			self::LOCK_NAME,
			(string) $now
		) );
		if ( $ins ) {
			return true;
		}
		$old = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_NAME ) );
		if ( '' !== $old && ( $now - (int) $old ) > self::LOCK_TTL ) {
			return (bool) $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				(string) $now,
				self::LOCK_NAME,
				$old
			) );
		}
		return false;
	}

	private static function release_lock(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_NAME ) );
	}

	// ─── Synchronizacja pojedynczych użytkowników (hooki) ──────────────────

	/** 'shutdown': przetwórz kolejkę jeden raz. */
	public static function flush_queue(): void {
		$ids         = self::get_queue();
		self::$queue = [];
		if ( empty( $ids ) || ! self::schema_ready() ) {
			return;
		}
		try {
			self::ensure_groups();
			$touched = [];
			foreach ( $ids as $uid ) {
				foreach ( self::sync_user( (int) $uid ) as $gid ) {
					$touched[ $gid ] = true;
				}
			}
			foreach ( array_keys( $touched ) as $gid ) {
				self::recount( (int) $gid );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Openvote system groups: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Dopasuj członkostwo jednego użytkownika w Wszyscy (bez przeliczania licznika).
	 *
	 * @return int[] ID grup, w których coś się zmieniło.
	 */
	public static function sync_user( int $user_id ): array {
		global $wpdb;
		$gid = self::get_all_group_id();
		if ( $user_id <= 0 || ! $gid ) {
			return [];
		}
		$gm     = $wpdb->prefix . 'openvote_group_members';
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID = %d", $user_id ) );
		$want   = $exists && self::user_is_member_of_all( $user_id );
		$has    = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$gm} WHERE group_id = %d AND user_id = %d", $gid, $user_id ) );
		if ( $want && ! $has ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$gm} (group_id, user_id, source, added_at) VALUES (%d, %d, 'auto', %s)",
				$gid,
				$user_id,
				current_time( 'mysql' )
			) );
			return [ $gid ];
		}
		if ( ! $want && $has ) {
			$wpdb->delete( $gm, [ 'group_id' => $gid, 'user_id' => $user_id ], [ '%d', '%d' ] );
			return [ $gid ];
		}
		return [];
	}

	/** Usunięcie użytkownika: usuń go ze WSZYSTKICH grup bieżącej strony i przelicz liczniki. */
	public static function on_user_deleted( $user_id ): void {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		unset( self::$queue[ $user_id ] );
		$gm     = $wpdb->prefix . 'openvote_group_members';
		$groups = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT group_id FROM {$gm} WHERE user_id = %d", $user_id ) ) );
		if ( empty( $groups ) ) {
			return;
		}
		$wpdb->delete( $gm, [ 'user_id' => $user_id ], [ '%d' ] );
		foreach ( $groups as $gid ) {
			self::recount( $gid );
		}
	}

	/**
	 * Hook added/updated/deleted_user_meta: zmiana pola grupującego (usermeta) lub capabilities → kolejka.
	 *
	 * @param mixed $meta_id ID (lub tablica ID przy deleted_user_meta) — nieużywane.
	 */
	public static function on_user_meta_changed( $meta_id, $user_id, $meta_key ): void {
		global $wpdb;
		$meta_key = (string) $meta_key;
		if ( '' === $meta_key ) {
			return;
		}
		$spec = self::grouping();
		if ( ( null !== $spec && 'meta' === $spec[0] && $spec[1] === $meta_key )
			|| $meta_key === $wpdb->get_blog_prefix() . 'capabilities' ) {
			self::queue_user( $user_id );
		}
	}

	/** add_option_openvote_field_map */
	public static function on_field_map_added( $option, $value ): void {
		self::on_field_map_updated( [], $value );
	}

	/**
	 * update_option_openvote_field_map: gdy zmieniło się mapowanie pola „Grupa” — reset kursora (pełne ponowne
	 * uzgodnienie od początku) i uzgodnienie w podbudżecie na 'shutdown'; resztę dokończy heartbeat / „Synchronizuj teraz”.
	 */
	public static function on_field_map_updated( $old_value, $value ): void {
		$def = class_exists( 'Openvote_Field_Map' ) ? (string) ( Openvote_Field_Map::DEFAULTS['city'] ?? '' ) : '';
		$old = is_array( $old_value ) ? (string) ( $old_value['city'] ?? '' ) : '';
		$new = is_array( $value ) ? (string) ( $value['city'] ?? '' ) : '';
		$old = '' === $old ? $def : $old;
		$new = '' === $new ? $def : $new;
		self::$grouping_cache = false;
		if ( $old === $new || ! self::schema_ready() ) {
			return;
		}
		delete_option( self::OPT_CURSOR );
		if ( function_exists( 'openvote_groups_audit_log_append' ) && function_exists( 'get_current_user_id' ) ) {
			/* translators: 1: old field key, 2: new field key */
			openvote_groups_audit_log_append( get_current_user_id(), sprintf( __( 'zmienił pole „Grupa” (%1$s → %2$s) — ponowne uzgodnienie grupy Wszyscy', 'openvote' ), $old, $new ) );
		}
		if ( ! self::$reconcile_on_shutdown ) {
			self::$reconcile_on_shutdown = true;
			add_action( 'shutdown', [ __CLASS__, 'reconcile_on_shutdown' ], 20 );
		}
	}

	/** 'shutdown' po zmianie mapowania pola: uzgodnienie w podbudżecie (połowa BUDGET). */
	public static function reconcile_on_shutdown(): void {
		try {
			self::$grouping_cache = false;
			self::reconcile( time() + (int) ceil( self::BUDGET / 2 ) );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Openvote system groups (field map): ' . $e->getMessage() );
			}
		}
	}

	/** Ręczne „Synchronizuj teraz” (panel / REST). */
	public static function run_manual(): array {
		$created = self::ensure_groups( true );
		$summary = self::reconcile( time() + self::BUDGET );
		$pending = is_array( get_option( self::OPT_CURSOR, false ) );
		return [
			'created' => $created,
			'summary' => $summary,
			'done'    => ! $pending,
		];
	}
}
